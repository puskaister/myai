#!/usr/bin/env bash
# Végponttól végpontig teszt az időpontfoglalóhoz. Egy futó PHP szervert vár
# a $BASE címen, üres adatbázissal (a CI workflow így indítja).
# Használat: BASE=http://127.0.0.1:8000 DB_PASS=... tests/idopontfoglalo.sh
set -uo pipefail

BASE="${BASE:-http://127.0.0.1:8000}"
DB_PASS="${DB_PASS:?DB_PASS kell}"
JAR="$(mktemp)"
FAILS=0
H=(-H 'X-Requested-With: fetch' -H 'Content-Type: application/json')

pass() { echo "  ok  $1"; }
fail() { echo "  HIBA $1"; echo "::error title=Teszt hiba::$1"; FAILS=$((FAILS + 1)); }
check() { if eval "$2"; then pass "$1"; else fail "$1"; fi; }

api_get()  { curl -s "$BASE/api/?r=$1" -b "$JAR" -c "$JAR"; }
api_post() { curl -s -w '\n%{http_code}' "$BASE/api/?r=$1" -b "$JAR" -c "$JAR" "${H[@]}" -d "$2"; }
body()   { sed '$d' <<<"$1"; }
status() { tail -n1 <<<"$1"; }

echo "== Telepítés"
code=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/")
check "a főoldal telepítés előtt átirányít" '[ "$code" = 302 ]'
check "a telepítő űrlapja megjelenik" 'curl -s "$BASE/install.php" | grep -q "Időpontfoglaló telepítése"'
out=$(curl -s "$BASE/install.php" --data-urlencode business='Teszt Gumiszerviz' -d preset=gumiszerviz -d name=Admin \
  -d email=admin@example.com -d password=titkos123 -d password2=titkos123 -d dbpass=rossz)
check "rossz adatbázis-jelszóval nem telepít" 'grep -q "nem egyezik" <<<"$out"'
out=$(curl -s "$BASE/install.php" --data-urlencode business='Teszt Gumiszerviz' -d preset=gumiszerviz -d name=Admin \
  -d email=admin@example.com -d password=titkos123 -d password2=titkos123 --data-urlencode dbpass="$DB_PASS")
check "a telepítés sikerül" 'grep -q "Kész!" <<<"$out"'
check "másodszor már nem telepíthető" 'curl -s "$BASE/install.php" | grep -q "már telepítve"'

echo "== Oldalak"
check "a foglalási oldal betölt, benne a beállításokkal" 'curl -s "$BASE/" | grep -q "window.BOOT"'
check "az admin oldal betölt" 'curl -s "$BASE/admin/" | grep -q "admin.js"'
check "a manifest érvényes JSON" 'curl -s "$BASE/manifest.php" | jq -e ".name == \"Teszt Gumiszerviz\"" >/dev/null'
check "az ikon PNG" 'curl -s "$BASE/icon.php?s=192" | head -c 8 | od -An -tx1 | grep -q "89 50 4e 47"'
check "a maskable ikon PNG" 'curl -s "$BASE/icon.php?s=512&m=1" | head -c 8 | od -An -tx1 | grep -q "89 50 4e 47"'

echo "== Nyilvános API"
cfg=$(api_get config)
check "config: 5 szolgáltatás a gumiszerviz csomagból" '[ "$(jq ".services | length" <<<"$cfg")" = 5 ]'
check "config: a rendszám kötelező extra mező" 'jq -e ".settings.fields[0].key == \"plate\" and .settings.fields[0].required" <<<"$cfg" >/dev/null'
check "config: nem szivárog ki az admin email" '! grep -q "admin_email" <<<"$cfg"'
SID=$(jq '.services[0].id' <<<"$cfg")

# Az első szabad nap keresése (ez és a következő hónap).
DATE=""
for m in "$(date +%Y-%m)" "$(date -d '+1 month' +%Y-%m)"; do
  DATE=$(api_get "days&service_id=$SID&month=$m" | jq -r '.days | to_entries | map(select(.value)) | .[0].key // empty')
  [ -n "$DATE" ] && break
done
check "van szabad nap" '[ -n "$DATE" ]'
SLOT=$(api_get "slots&service_id=$SID&date=$DATE" | jq -r '.slots[0] // empty')
check "van szabad időpont ($DATE $SLOT)" '[ -n "$SLOT" ]'

book() { api_post book "{\"service_id\":$SID,\"date\":\"$DATE\",\"time\":\"$SLOT\",\"name\":\"$1\",\"phone\":\"+36 30 123 4567\",\"email\":\"ugyfel@example.com\",\"fields\":{\"plate\":\"ABC-123\"}}"; }

r=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/api/?r=book" -H 'Content-Type: application/json' -d '{}')
check "egyedi fejléc nélkül a POST tiltott (CSRF)" '[ "$r" = 400 ]'

r=$(api_post book "{\"service_id\":$SID,\"date\":\"$DATE\",\"time\":\"$SLOT\",\"name\":\"Hiányos\",\"phone\":\"+36301234567\",\"email\":\"a@example.com\"}")
check "kötelező extra mező nélkül 422" '[ "$(status "$r")" = 422 ] && jq -e ".fields[\"fields.plate\"]" <<<"$(body "$r")" >/dev/null'

r=$(book "Első Ügyfél")
check "első foglalás sikerül, visszaigazolva" '[ "$(status "$r")" = 200 ] && jq -e ".booking.status == \"confirmed\"" <<<"$(body "$r")" >/dev/null'
TOKEN=$(body "$r" | jq -r .token)
r=$(book "Második Ügyfél")
check "második foglalás ugyanarra (kapacitás 2) sikerül" '[ "$(status "$r")" = 200 ]'
r=$(book "Harmadik Ügyfél")
check "harmadik foglalás ugyanarra 409 (betelt)" '[ "$(status "$r")" = 409 ]'
check "a betelt időpont eltűnik a kínálatból" '! api_get "slots&service_id=$SID&date=$DATE" | jq -e --arg s "$SLOT" ".slots | index(\$s)" >/dev/null'

check "foglalás lekérdezése azonosítóval" 'api_get "booking&token=$TOKEN" | jq -e ".booking.name == \"Első Ügyfél\"" >/dev/null'
check "naptárfájl (.ics)" 'api_get "ics&token=$TOKEN" | grep -q "BEGIN:VCALENDAR"'
check "hibás azonosító 404" '[ "$(curl -s -o /dev/null -w "%{http_code}" "$BASE/api/?r=booking&token=zzz")" = 404 ]'

echo "== Admin"
r=$(api_post login '{"email":"admin@example.com","password":"rossz"}')
check "rossz jelszóval nem lép be" '[ "$(status "$r")" = 401 ]'
check "belépés nélkül az admin API tiltott" '[ "$(curl -s -o /dev/null -w "%{http_code}" "$BASE/api/?r=admin/settings")" = 401 ]'
r=$(api_post login '{"email":"admin@example.com","password":"titkos123"}')
check "belépés sikerül" '[ "$(status "$r")" = 200 ]'
check "me: bejelentkezett admin" 'api_get me | jq -e ".admin.email == \"admin@example.com\"" >/dev/null'
list=$(api_get "admin/bookings&from=$DATE&to=$DATE")
check "admin: 2 foglalás a napon" '[ "$(jq ".bookings | length" <<<"$list")" = 2 ]'
BID=$(jq '.bookings[0].id' <<<"$list")
check "admin: egy foglalás részletei" 'api_get "admin/booking&id=$BID" | jq -e ".booking.fields.plate == \"ABC-123\"" >/dev/null'
check "admin: keresés rendszámra" 'api_get "admin/search&q=ABC-123" | jq -e ".bookings | length == 2" >/dev/null'

r=$(api_post admin/settings '{"group":"rules","value":{"slot_step":30,"capacity":2,"lead_hours":2,"max_days":45,"approval":"manual","customer_cancel":true,"cancel_hours":0}}')
check "admin: kézi jóváhagyásra állítás" '[ "$(status "$r")" = 200 ]'
r=$(api_post admin/settings '{"group":"hours","value":{"1":[["08:00","25:00"]]}}')
check "admin: hibás nyitvatartás elutasítva" '[ "$(status "$r")" = 400 ]'
r=$(api_post admin/settings '{"group":"theme","value":{"primary":"#123abc"}}')
check "admin: szín mentése" '[ "$(status "$r")" = 200 ] && curl -s "$BASE/manifest.php" | jq -e ".theme_color == \"#123abc\"" >/dev/null'

SLOT2=$(api_get "slots&service_id=$SID&date=$DATE" | jq -r '.slots[0] // empty')
r=$(api_post book "{\"service_id\":$SID,\"date\":\"$DATE\",\"time\":\"$SLOT2\",\"name\":\"Függő Ügyfél\",\"phone\":\"+36301234567\",\"email\":\"f@example.com\",\"fields\":{\"plate\":\"XYZ-999\"}}")
check "kézi módban a foglalás függőben marad" 'jq -e ".booking.status == \"pending\"" <<<"$(body "$r")" >/dev/null'
TOKEN2=$(body "$r" | jq -r .token)
check "a függő foglalás megjelenik a jóváhagyási listában" 'api_get "admin/bookings&from=$DATE&to=$DATE" | jq -e ".pending | length >= 1" >/dev/null'
PID=$(api_get "admin/bookings&from=$DATE&to=$DATE" | jq '.pending[0].id')
r=$(api_post admin/booking/status "{\"id\":$PID,\"status\":\"confirmed\",\"notify\":false}")
check "admin: jóváhagyás" '[ "$(status "$r")" = 200 ] && api_get "booking&token=$TOKEN2" | jq -e ".booking.status == \"confirmed\"" >/dev/null'

r=$(api_post cancel "{\"token\":\"$TOKEN2\"}")
check "ügyfél lemondja (0 órás lemondási határidő)" '[ "$(status "$r")" = 200 ] && jq -e ".booking.status == \"cancelled\"" <<<"$(body "$r")" >/dev/null'

r=$(api_post admin/booking/save "{\"service_id\":$SID,\"date\":\"$DATE\",\"time\":\"$SLOT\",\"name\":\"Telefonos Ügyfél\",\"notify\":false}")
check "admin: teli időpontra rögzítés figyelmeztet (409)" '[ "$(status "$r")" = 409 ]'
r=$(api_post admin/booking/save "{\"service_id\":$SID,\"date\":\"$DATE\",\"time\":\"$SLOT\",\"name\":\"Telefonos Ügyfél\",\"notify\":false,\"force\":true}")
check "admin: kényszerített rögzítés sikerül" '[ "$(status "$r")" = 200 ]'

r=$(api_post admin/services/save '{"name":"Új szolgáltatás","duration_min":45,"price":"","active":true}')
check "admin: új szolgáltatás" '[ "$(status "$r")" = 200 ] && [ "$(api_get config | jq ".services | length")" = 6 ]'
r=$(api_post admin/settings '{"group":"fields","value":[{"key":"plate","label":"Rendszám","type":"text","required":true},{"label":"Márka","type":"select","options":["Opel","Suzuki"]}]}')
check "admin: extra mezők mentése" '[ "$(status "$r")" = 200 ] && [ "$(api_get config | jq ".settings.fields | length")" = 2 ]'

r=$(api_post logout '{}')
check "kijelentkezés után az admin API tiltott" '[ "$(curl -s -o /dev/null -w "%{http_code}" -b "$JAR" "$BASE/api/?r=admin/settings")" = 401 ]'

echo
if [ "$FAILS" -gt 0 ]; then echo "$FAILS teszt HIBÁS"; exit 1; fi
echo "Minden teszt rendben."
