#!/usr/bin/env bash
# Végponttól végpontig teszt az Évfordulók apphoz. A kiküldött leveleket a PHP
# sendmail_path egy naplófájlba írja (MAIL_LOG).
set -u
BASE="${BASE:-http://127.0.0.1:8005}"
DB_PASS="${DB_PASS:?DB_PASS kell}"
MAIL_LOG="${MAIL_LOG:?MAIL_LOG kell}"
APP="${APP:-apps/evfordulok}"
J1="$(mktemp)"; J2="$(mktemp)"
FAILS=0
pass() { echo "  ok  $1"; }
fail() { echo "  HIBA $1"; echo "::error title=Évfordulók teszt hiba::$1"; FAILS=$((FAILS + 1)); }
check() { if eval "$2"; then pass "$1"; else fail "$1"; fi; }
get()  { curl -s "$BASE/api/?r=$2" -b "$1" -c "$1"; }
post() { curl -s -w '\n%{http_code}' "$BASE/api/?r=$2" -b "$1" -c "$1" -H 'X-Requested-With: fetch' -H 'Content-Type: application/json' -d "$3"; }
body()   { sed '$d' <<<"$1"; }
status() { tail -n1 <<<"$1"; }

TZ=Europe/Budapest
TODAY=$(TZ=$TZ date +%F); TOM=$(TZ=$TZ date -d tomorrow +%F)
TM=$((10#$(TZ=$TZ date -d tomorrow +%m))); TD=$((10#$(TZ=$TZ date -d tomorrow +%d))); TY=$(TZ=$TZ date -d tomorrow +%Y)

echo "== Dátumlogika"
out=$(APP="$APP" php -r '
  require getenv("APP") . "/api/lib/core.php";
  $t = [
    [["month"=>2,"day"=>29,"year"=>null,"recurrence"=>"yearly"], "2027-01-10", "2027-02-28"],
    [["month"=>2,"day"=>29,"year"=>null,"recurrence"=>"yearly"], "2028-01-10", "2028-02-29"],
    [["month"=>1,"day"=>31,"year"=>null,"recurrence"=>"monthly"], "2027-02-01", "2027-02-28"],
    [["month"=>1,"day"=>15,"year"=>null,"recurrence"=>"monthly"], "2027-12-20", "2028-01-15"],
    [["month"=>3,"day"=>5,"year"=>2020,"recurrence"=>"once"], "2027-01-01", null],
    [["month"=>6,"day"=>1,"year"=>2030,"recurrence"=>"yearly"], "2027-01-01", "2030-06-01"],
    [["month"=>12,"day"=>31,"year"=>null,"recurrence"=>"yearly"], "2027-12-31", "2027-12-31"],
  ];
  foreach ($t as [$e, $from, $want]) { $got = next_occurrence($e, $from); if ($got !== $want) { echo "HIBA $from: $got != $want\n"; } }
  $o = ordinal_for(["recurrence"=>"yearly","year"=>1990], "2027-05-01"); if ($o !== 37) echo "HIBA ordinal $o\n";
  echo "vege";')
check "következő előfordulás: szökőnap, hónapvég, egyszeri, jövőbeli kezdés, sorszám" '[ "$out" = "vege" ]'
[ "$out" = "vege" ] || echo "$out"

echo "== Telepítés, belépés"
check "telepítés előtt átirányít" '[ "$(curl -s -o /dev/null -w "%{http_code}" "$BASE/")" = 302 ]'
out=$(curl -s "$BASE/install.php" -d name=Anna -d email=anna@example.com -d password=titkos123 -d password2=titkos123 --data-urlencode dbpass="$DB_PASS")
check "telepítés sikerül" 'grep -q "Kész!" <<<"$out"'
check "app oldal, manifest, ikon" 'curl -s "$BASE/" | grep -q "app.js" && curl -s "$BASE/manifest.php" | jq -e ".display == \"standalone\"" >/dev/null && curl -s "$BASE/icon.php?s=192" | head -c 4 | od -An -tx1 | grep -q "89 50 4e 47"'
check "regisztráció alapból tiltva" '[ "$(status "$(post "$J2" register "{\"name\":\"X\",\"email\":\"x@example.com\",\"password\":\"titkos123\"}")")" = 403 ]'
check "belépés nélkül tiltott" '[ "$(curl -s -o /dev/null -w "%{http_code}" "$BASE/api/?r=events")" = 401 ]'
r=$(post "$J1" login '{"email":"anna@example.com","password":"titkos123"}'); check "belépés" '[ "$(status "$r")" = 200 ]'

echo "== Alkalmak"
save() { post "$J1" events/save "$1"; }
r=$(save "{\"title\":\"Anya szülinapja\",\"category\":\"birthday\",\"recurrence\":\"yearly\",\"month\":$TM,\"day\":$TD,\"year\":1960,\"remind_days\":1,\"note\":\"virág\"}"); check "éves születésnap" '[ "$(status "$r")" = 200 ]'
BID=$(body "$r" | jq .id)
r=$(save "{\"title\":\"Fogorvos\",\"category\":\"other\",\"recurrence\":\"once\",\"month\":$TM,\"day\":$TD,\"year\":$TY,\"remind_days\":1}"); check "egyszeri alkalom" '[ "$(status "$r")" = 200 ]'
r=$(save "{\"title\":\"Havi befizetés\",\"category\":\"other\",\"recurrence\":\"monthly\",\"month\":1,\"day\":$TD,\"remind_days\":1}"); check "havi alkalom" '[ "$(status "$r")" = 200 ]'
r=$(save "{\"title\":\"Csendes nap\",\"category\":\"memorial\",\"recurrence\":\"yearly\",\"month\":$TM,\"day\":$TD,\"remind_days\":-1}"); check "email nélküli alkalom" '[ "$(status "$r")" = 200 ]'
r=$(save '{"title":"Rossz","recurrence":"yearly","month":2,"day":30}'); check "nem létező nap elutasítva" '[ "$(status "$r")" = 400 ]'
r=$(save '{"title":"Évszám nélkül","recurrence":"once","month":5,"day":1}'); check "egyszeri évszám nélkül elutasítva" '[ "$(status "$r")" = 400 ]'
r=$(save '{"title":"Szökőnap","recurrence":"yearly","month":2,"day":29}'); check "febr. 29. évszám nélkül elfogadva" '[ "$(status "$r")" = 200 ]'

echo "== Emlékeztető-kör (cron)"
KEY=$(php -r 'echo substr(hash_hmac("sha256", "cron|evf_", "tesztjelszo"), 0, 32);')
check "rossz kulccsal tiltott" '[ "$(curl -s -o /dev/null -w "%{http_code}" "$BASE/cron.php?key=rossz")" = 403 ]'
: > "$MAIL_LOG"
c=$(curl -s "$BASE/cron.php?key=$KEY")
check "egy összefoglaló levél, 3 alkalommal (az email nélküli kimarad)" 'jq -e ".emails == 1 and .occasions == 3" <<<"$c" >/dev/null'
check "a levél tartalma" 'grep -q "anna@example.com" "$MAIL_LOG" && grep -q "Anya szülinapja" "$MAIL_LOG" && grep -q "Fogorvos" "$MAIL_LOG" && ! grep -q "Csendes nap" "$MAIL_LOG"'
c=$(curl -s "$BASE/cron.php?key=$KEY"); check "másodszor nem küld újra" 'jq -e ".emails == 0" <<<"$c" >/dev/null'
c=$(curl -s "$BASE/cron.php?key=$KEY&date=$TOM"); check "aznapi emlékeztető nincs beállítva → holnap sem küld" 'jq -e ".emails == 0" <<<"$c" >/dev/null'

echo "== Kijelző"
ev=$(get "$J1" events)
check "holnapi alkalmak elöl, 'még 1 nap'" 'jq -e "[.events[] | select(.days_until == 1)] | length == 4" <<<"$ev" >/dev/null'
ok1=$(jq -e --argjson id "$BID" --argjson want $((TY - 1960)) '[.events[] | select(.id == $id)][0] | .ordinal == $want and (.label | test("születésnap"))' <<<"$ev" >/dev/null && echo y)
check "hányadik születésnap" '[ "$ok1" = y ]'
ok2=$(jq -e --arg d "$TOM" '[.events[] | select(.title == "Havi befizetés")][0].next == $d' <<<"$ev" >/dev/null && echo y)
check "havi alkalom következő dátuma holnap" '[ "$ok2" = y ]'

echo "== Naptár (ICS)"
CAL=$(get "$J1" me | jq -r .user.calendar_url | sed "s#^https\?://[^/]*/*#$BASE/#")
ics=$(curl -s "$CAL")
check "ICS: éves ismétlés, riasztás" 'grep -q "BEGIN:VCALENDAR" <<<"$ics" && grep -q "RRULE:FREQ=YEARLY" <<<"$ics" && grep -q "RRULE:FREQ=MONTHLY" <<<"$ics" && grep -q "TRIGGER:-PT" <<<"$ics"'
check "ICS rossz tokennel 404" '[ "$(curl -s -o /dev/null -w "%{http_code}" "$BASE/ics.php?t=00000000000000000000000000000000")" = 404 ]'

echo "== Több felhasználó"
r=$(post "$J1" admin/users/save '{"name":"Béla","email":"bela@example.com","password":"titkos456"}'); check "admin új felhasználót vesz fel" '[ "$(status "$r")" = 200 ]'
r=$(post "$J2" login '{"email":"bela@example.com","password":"titkos456"}'); check "második felhasználó belép" '[ "$(status "$r")" = 200 ]'
check "a második nem látja az első alkalmait" 'get "$J2" events | jq -e "(.events | length) == 0" >/dev/null'
post "$J2" events/delete "{\"id\":$BID}" >/dev/null
ok3=$(get "$J1" events | jq -e --argjson id "$BID" '[.events[] | select(.id == $id)] | length == 1' >/dev/null && echo y)
check "más alkalmát nem törölheti" '[ "$ok3" = y ]'
check "nem admin nem kezelhet felhasználókat" '[ "$(curl -s -o /dev/null -w "%{http_code}" -b "$J2" "$BASE/api/?r=admin/users")" = 403 ]'
r=$(post "$J1" events/delete "{\"id\":$BID}")
ok4=$(get "$J1" events | jq -e --argjson id "$BID" '[.events[] | select(.id == $id)] | length == 0' >/dev/null && echo y)
check "saját alkalom törlése" '[ "$(status "$r")" = 200 ] && [ "$ok4" = y ]'

echo
if [ "$FAILS" -gt 0 ]; then echo "$FAILS teszt HIBÁS"; exit 1; fi
echo "Évfordulók: minden teszt rendben."
