#!/usr/bin/env bash
# Végponttól végpontig teszt az időpontfoglalóhoz. Egy futó PHP szervert vár
# a $BASE címen, üres adatbázissal (a CI workflow így indítja).
# Használat: BASE=http://127.0.0.1:8000 DB_PASS=... tests/idopontfoglalo.sh
set -u  # pipefail nélkül: a `curl | grep -q` korai zárása ne jelezzen hamis hibát

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
check "a beépítő script elérhető" 'curl -s "$BASE/widget.js" | grep -q "beagyazva=1"'
check "a szakmai csomagok a telepítőben" 'F=$(curl -s "$BASE/install.php"); grep -q "Fodrászat / szépségszalon" <<<"$F" && grep -q "Autószerelő műhely" <<<"$F" && grep -q "Fogászat / fogorvosi rendelő" <<<"$F"'
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
for spec in "s=192" "s=512" "s=512&m=1" "s=180"; do
  resp=$(curl -s -D - -o "$JAR.icon" "$BASE/icon.php?$spec" | tr -d '\r' | grep -i '^content-type' )
  if head -c 8 "$JAR.icon" | od -An -tx1 | grep -q "89 50 4e 47"; then pass "ikon PNG ($spec)"
  else fail "ikon PNG ($spec): $resp — $(head -c 300 "$JAR.icon" | tr '\n' ' ')"; fi
done

echo "== Nyilvános API"
check "beágyazott mód: nincs telepítés gomb, magasságjelzés" 'P=$(curl -s "$BASE/?beagyazva=1"); grep -q "class=\"embedded\"" <<<"$P" && grep -q "idopont: .height." <<<"$P" && ! grep -q "install-btn" <<<"$P"'
cfg=$(api_get config)
check "config: 5 szolgáltatás a gumiszerviz csomagból" '[ "$(jq ".services | length" <<<"$cfg")" = 5 ]'
check "config: a rendszám kötelező extra mező" 'jq -e ".settings.fields[0].key == \"plate\" and .settings.fields[0].required" <<<"$cfg" >/dev/null'
check "config: nem szivárog ki az admin email" '! grep -q "admin_email" <<<"$cfg"'
SID=$(jq '.services[0].id' <<<"$cfg")

# Az első olyan szabad nap keresése (ez és a következő hónap), amelyen legalább
# 2 szabad időpont van: késő délután a mai napon már csak egy maradhat, a teszt
# pedig később egy második időpontra is foglal.
DATE=""
for m in "$(date +%Y-%m)" "$(date -d '+1 month' +%Y-%m)"; do
  for d in $(api_get "days&service_id=$SID&month=$m" | jq -r '.days | to_entries | map(select(.value)) | .[].key'); do
    if [ "$(api_get "slots&service_id=$SID&date=$d" | jq '.slots | length')" -ge 2 ]; then DATE=$d; break; fi
  done
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

echo "== Jelszó-emlékeztető"
r=$(api_post forgot '{"email":"admin@example.com"}')
check "emlékeztető kérése létező fiókra" '[ "$(status "$r")" = 200 ]'
r=$(api_post forgot '{"email":"nincs@example.com"}')
check "nem létező fióknál ugyanaz a válasz (nem árulja el)" '[ "$(status "$r")" = 200 ]'
r=$(api_post reset '{"token":"0000000000000000000000000000000000000000000000000000000000000000","password":"ujjelszo123"}')
check "érvénytelen tokennel nem állítható jelszó" '[ "$(status "$r")" = 400 ]'
# A levelet a CI nem kapja meg, ezért egy ismert tokent közvetlenül az adatbázisba írunk.
TOK=$(printf 'a%.0s' {1..64})
mysql -h127.0.0.1 -uidopont -ptesztjelszo idopont -e "INSERT INTO teszt_password_resets (admin_id, token_hash, expires_at) SELECT id, SHA2('$TOK', 256), NOW() + INTERVAL 1 DAY FROM teszt_admins WHERE email = 'admin@example.com'" 2>/dev/null
r=$(api_post reset "{\"token\":\"$TOK\",\"password\":\"rovid\"}")
check "túl rövid új jelszó elutasítva" '[ "$(status "$r")" = 400 ]'
r=$(api_post reset "{\"token\":\"$TOK\",\"password\":\"ujjelszo123\"}")
check "új jelszó beállítása tokennel" '[ "$(status "$r")" = 200 ]'
r=$(api_post login '{"email":"admin@example.com","password":"ujjelszo123"}')
check "belépés az új jelszóval" '[ "$(status "$r")" = 200 ]'
r=$(api_post reset "{\"token\":\"$TOK\",\"password\":\"masikjelszo1\"}")
check "a token másodszor nem használható" '[ "$(status "$r")" = 400 ]'

echo "== Havi díj: felfüggesztett foglaló (ÁSZF 14.5.)"
HSQL() { mysql -h127.0.0.1 -uidopont -ptesztjelszo idopont -e "$1" 2>/dev/null; }
bookx() { api_post book "{\"service_id\":$SID,\"date\":\"$DATE\",\"time\":\"$SLOT2\",\"name\":\"Felfüggesztés Teszt\",\"phone\":\"+36301234567\",\"email\":\"s@example.com\",\"fields\":{\"plate\":\"SUS-001\"}}"; }
SLOT2=$(api_get "slots&service_id=$SID&date=$DATE" | jq -r '.slots[0] // empty')
check "nyilvántartó tábla nélkül működik" '! curl -s "$BASE/" | grep -q "átmenetileg szünetel"'
HSQL "CREATE TABLE kozos_hosting (dir VARCHAR(40) NOT NULL PRIMARY KEY, paid_until DATE NULL, mode VARCHAR(10) NOT NULL DEFAULT 'auto')"
HSQL "INSERT INTO kozos_hosting (dir, paid_until, mode) VALUES ('teszt', CURDATE() - INTERVAL 15 DAY, 'auto')"
check "a 10. késedelmi napon még működik" '! curl -s "$BASE/" | grep -q "átmenetileg szünetel"'
HSQL "UPDATE kozos_hosting SET paid_until = CURDATE() - INTERVAL 16 DAY"
check "utána: a foglalási oldal felfüggesztve" 'P=$(curl -s "$BASE/"); grep -q "átmenetileg szünetel" <<<"$P" && ! grep -q "assets/app.js" <<<"$P"'
check "felfüggesztve: foglalás 503" '[ "$(status "$(bookx)")" = 503 ]'
check "felfüggesztve: az admin figyelmeztetést lát" 'curl -s "$BASE/admin/" | grep -q "A foglalási oldal felfüggesztve"'
HSQL "UPDATE kozos_hosting SET mode = 'active'"
check "kézzel bekapcsolva: újra foglalható" '! curl -s "$BASE/" | grep -q "átmenetileg szünetel" && [ "$(status "$(bookx)")" = 200 ]'
HSQL "UPDATE kozos_hosting SET mode = 'suspended', paid_until = CURDATE() + INTERVAL 20 DAY"
check "kézzel felfüggesztve: fizetett időszakban is szünetel" 'curl -s "$BASE/" | grep -q "átmenetileg szünetel"'
HSQL "DROP TABLE kozos_hosting"
# a felületi teszt is foglal ugyanerről a címről: a foglalási korlát (6/óra) ne teljen be miattunk
HSQL "DELETE FROM teszt_rate_limits WHERE k LIKE 'book:%'"

echo
if [ "$FAILS" -gt 0 ]; then echo "$FAILS teszt HIBÁS"; exit 1; fi
echo "Minden teszt rendben."
