#!/usr/bin/env bash
# Végponttól végpontig teszt az Ügyintézési Segédhez: demó és chat (statikus),
# megrendelés, előfizetés-kezelés, tudásbázis csak érvényes előfizetésnél,
# lejárati emlékeztetők. A levelek a MAIL_LOG fájlba mennek.
set -u
BASE="${BASE:-http://127.0.0.1:8007}"
DB_PASS="${DB_PASS:?DB_PASS kell}"
MAIL_LOG="${MAIL_LOG:?MAIL_LOG kell}"
J="$(mktemp)"
FAILS=0
pass() { echo "  ok  $1"; }
fail() { echo "  HIBA $1"; echo "::error title=Ügyintézési Segéd teszt hiba::$1"; FAILS=$((FAILS + 1)); }
check() { if eval "$2"; then pass "$1"; else fail "$1"; fi; }
get()  { curl -s "$BASE/api/?r=$1" -b "$J" -c "$J"; }
post() { curl -s -w '\n%{http_code}' "$BASE/api/?r=$1" -b "$J" -c "$J" -H 'X-Requested-With: fetch' -H 'Content-Type: application/json' -d "$2"; }
body()   { sed '$d' <<<"$1"; }
status() { tail -n1 <<<"$1"; }
KEY=$(php -r 'echo substr(hash_hmac("sha256", "cron|seged_", "tesztjelszo"), 0, 32);')
TODAY=$(TZ=Europe/Budapest date +%F)

echo "== Demó és chat (telepítés előtt is)"
check "demó oldal, chat, widget" '[ "$(curl -s -o /dev/null -w "%{http_code}" "$BASE/")" = 200 ] && curl -s "$BASE/chat.html" | grep -q "kb.php" && curl -s "$BASE/widget.js" | grep -q "chat.html"'
check "a my-ai.hu saját tudásbázisa mindig elérhető" 'curl -s "$BASE/kb.php?u=my-ai" | jq -e "(.temak | length) > 5" >/dev/null && curl -s "$BASE/kb.php?u=minta" | jq -e ".temak | length > 0" >/dev/null'
check "a saját válasza már nem „ingyenes”" '! curl -s "$BASE/kb.php?u=my-ai" | grep -q "ingyenes, szabályalapú" && curl -s "$BASE/kb.php?u=my-ai" | grep -q "5 000 Ft + ÁFA"'
check "a my-ai.hu tudástára: logó, minden ár, cégadatok" 'curl -s "$BASE/kb.php?u=my-ai" | jq -e ".logo == \"/assets/immobilis-logo.svg\" and (.temak | length) >= 30" >/dev/null && curl -s "$BASE/kb.php?u=my-ai" | grep -q "30 000 Ft + ÁFA" && curl -s "$BASE/kb.php?u=my-ai" | grep -q "01-09-684414"'
check "a chat fejléce logót tud mutatni" 'curl -s "$BASE/chat.html" | grep -q "className=.logo."'
check "cron telepítés előtt: kihagyva" 'curl -s "$BASE/cron.php" | jq -e ".skipped" >/dev/null'

echo "== Telepítés"
out=$(curl -s "$BASE/install.php" -d name=Admin -d email=admin@example.com -d password=titkos123 -d password2=titkos123 --data-urlencode dbpass="$DB_PASS")
check "telepítés" 'grep -q "Kész!" <<<"$out"'
check "díj: 5000 + 27% ÁFA = 6350" 'get price | jq -e ".net == 5000 and .gross == 6350" >/dev/null'

echo "== Megrendelés"
r=$(post order '{"company":"Kovács Iroda Kft.","contact_name":"Kovács Anna","email":"anna@kovacs.hu"}')
check "feltételek elfogadása nélkül 422" '[ "$(status "$r")" = 422 ]'
r=$(post order '{"company":"Spam Kft.","contact_name":"Spam","email":"spam@example.com","accept":true,"website_url_hp":"http://spam"}')
check "csapda mezővel érkező (robot) megrendelés csendben eldobva" '[ "$(status "$r")" = 200 ]'
: > "$MAIL_LOG"
r=$(post order '{"company":"Kovács Iroda Kft.","contact_name":"Kovács Anna","email":"anna@kovacs.hu","phone":"+36 30 111 2222","website":"https://kovacs.hu","billing_address":"1111 Budapest, Fő u. 1.","tax_number":"12345678-1-41","message":"Nyitvatartás, árak","accept":true}')
check "megrendelés elküldve" '[ "$(status "$r")" = 200 ]'
check "visszaigazolás az ügyfélnek, értesítés az adminnak" 'grep -q "To: anna@kovacs.hu" "$MAIL_LOG" && grep -q "To: admin@example.com" "$MAIL_LOG" && grep -q "Köszönjük, megkaptuk" "$MAIL_LOG"'

echo "== Admin: ügyfél, tudásbázis, befizetés"
check "belépés nélkül tiltott" '[ "$(curl -s -o /dev/null -w "%{http_code}" "$BASE/api/?r=admin/customers")" = 401 ]'
r=$(post login '{"email":"admin@example.com","password":"titkos123"}'); check "belépés" '[ "$(status "$r")" = 200 ]'
L=$(get admin/customers)
CID=$(jq '.customers[0].id' <<<"$L"); SLUG=$(jq -r '.customers[0].slug' <<<"$L")
check "a robot megrendelése nem került be" '[ "$(jq ".customers | length" <<<"$L")" = 1 ]'
check "megrendelés a listán, állapot: megrendelve, azonosító a cégnévből" 'jq -e ".customers[0].status == \"pending\" and .customers[0].slug == \"kovacs-iroda-kft\"" <<<"$L" >/dev/null'
check "tudásbázis nélkül a chat nem kap semmit (404)" '[ "$(curl -s -o /dev/null -w "%{http_code}" "$BASE/kb.php?u=$SLUG")" = 404 ]'
C=$(get "admin/customer&id=$CID")
check "beépítő kód csak az adminban" 'jq -e ".embed | test(\"widget.js\") and test(\"data-ugyfel=\\\"kovacs-iroda-kft\\\"\")" <<<"$C" >/dev/null'
save() { post admin/customer/save "{\"id\":$CID,\"name\":\"Kovács Iroda Kft.\",\"email\":\"anna@kovacs.hu\",\"contact_name\":\"Kovács Anna\",\"status\":\"$1\",\"paid_until\":\"$2\",\"kb_json\":$3}"; }
KB=$(jq -Rs . <<<'{"nev":"Kovács Iroda","udvozles":"Jó napot!","temak":[{"title":"Nyitvatartás","keys":["nyitva"],"answer":"H–P 8–16"}]}')
r=$(save pending "" '"{nem json"'); check "hibás tudásbázis elutasítva" '[ "$(status "$r")" = 400 ]'
r=$(save pending "" "$KB"); check "tudásbázis mentve" '[ "$(status "$r")" = 200 ]'
check "befizetés előtt a chat szünetel" 'curl -s "$BASE/kb.php?u=$SLUG" | jq -e ".inactive == true" >/dev/null'
r=$(post admin/payment/add "{\"id\":$CID,\"months\":1,\"note\":\"átutalás\"}")
check "befizetés: +1 hónap, aktív" '[ "$(status "$r")" = 200 ] && [ "$(body "$r" | jq -r .paid_until)" \> "$TODAY" ]'
check "érvényes előfizetésnél a chat a tudásbázisból válaszol" 'curl -s "$BASE/kb.php?u=$SLUG" | jq -e ".temak[0].title == \"Nyitvatartás\" and .nev == \"Kovács Iroda\"" >/dev/null'
check "havi bevétel (nettó) a listán" 'get admin/customers | jq -e ".mrr_net == 5000" >/dev/null'
P1=$(get "admin/customer&id=$CID" | jq -r .customer.paid_until)
post admin/payment/add "{\"id\":$CID,\"months\":3}" >/dev/null
P2=$(get "admin/customer&id=$CID" | jq -r .customer.paid_until)
check "hosszabbítás a meglévő lejárattól (+3 hónap)" '[ "$(TZ=Europe/Budapest date -d "$P1 +3 month" +%F)" = "$P2" ]'
r=$(save paused "$P2" "$KB"); check "szüneteltetve → a chat szünetel" 'curl -s "$BASE/kb.php?u=$SLUG" | jq -e ".inactive == true" >/dev/null'

echo "== Lejárati kör"
check "rossz kulcs 403" '[ "$(curl -s -o /dev/null -w "%{http_code}" "$BASE/cron.php?key=rossz")" = 403 ]'
IN7=$(TZ=Europe/Budapest date -d "$TODAY +7 day" +%F)
save active "$IN7" "$KB" >/dev/null
: > "$MAIL_LOG"
c=$(curl -s "$BASE/cron.php?key=$KEY")
check "7 nappal a lejárat előtt emlékeztető" 'jq -e ".reminders == 1 and .failed == 0" <<<"$c" >/dev/null && grep -q "7 nap múlva lejár" "$MAIL_LOG" && grep -q "6 350 Ft" "$MAIL_LOG"'
check "ugyanaznap másodszor nem küld" 'curl -s "$BASE/cron.php?key=$KEY" | jq -e ".reminders == 0" >/dev/null'
YDAY=$(TZ=Europe/Budapest date -d "$TODAY -1 day" +%F)
save active "$YDAY" "$KB" >/dev/null
check "lejárt → a chat szünetel" 'curl -s "$BASE/kb.php?u=$SLUG" | jq -e ".inactive == true" >/dev/null'
: > "$MAIL_LOG"
c=$(curl -s "$BASE/cron.php?key=$KEY")
check "lejárati értesítés (ügyfél + admin)" 'jq -e ".expired == 1" <<<"$c" >/dev/null && grep -q "lejárt" "$MAIL_LOG" && grep -q "To: admin@example.com" "$MAIL_LOG"'
check "lejárati értesítés egyszer" 'curl -s "$BASE/cron.php?key=$KEY" | jq -e ".expired == 0" >/dev/null'

echo
if [ "$FAILS" -gt 0 ]; then echo "$FAILS teszt HIBÁS"; exit 1; fi
echo "Ügyintézési Segéd: minden teszt rendben."
