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
echo "== Tanítás: a my-ai.hu chatbotja"
K=$(get "admin/kb&bot=my-ai")
check "alap tudástár (fájlból)" 'jq -e ".source == \"file\" and (.kb.temak | length) >= 30" <<<"$K" >/dev/null'
NEW=$(jq -c '.kb | .temak = ([{"title":"Tanított téma","keys":["zebracsikos"],"answer":"Ezt az adminban tanítottuk.","elsobbseg":9}] + .temak)' <<<"$K")
r=$(post admin/kb/save "{\"bot\":\"my-ai\",\"kb\":$NEW}"); check "tanítás mentve" '[ "$(status "$r")" = 200 ]'
check "a chat azonnal a tanított tudással válaszol" 'curl -s "$BASE/kb.php?u=my-ai" | jq -e ".temak[0].title == \"Tanított téma\" and .temak[0].elsobbseg == 5 and .logo" >/dev/null'
check "az admin a tanított változatot mutatja" 'get "admin/kb&bot=my-ai" | jq -e ".source == \"db\"" >/dev/null'
r=$(post admin/kb/save '{"bot":"my-ai","kb":{"temak":[{"title":"","answer":"x"}]}}'); check "hiányos téma elutasítva" '[ "$(status "$r")" = 400 ]'
r=$(post admin/kb/save '{"bot":"my-ai","kb":{"logo":"javascript:alert(1)","temak":[{"title":"a","answer":"b"}]}}'); check "veszélyes logó-cím elutasítva" '[ "$(status "$r")" = 400 ]'
r=$(post admin/kb/reset '{"bot":"my-ai"}'); check "vissza az alapra" '[ "$(status "$r")" = 200 ] && curl -s "$BASE/kb.php?u=my-ai" | jq -e ".temak[0].title != \"Tanított téma\"" >/dev/null'
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
check "a tanító felület az ügyfél tudástárát mutatja" 'get "admin/kb&customer=$CID" | jq -e ".kb.temak[0].title == \"Nyitvatartás\" and .live == false" >/dev/null'
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

echo "== Ügyfél-fiók: saját belépés, csak a saját chatbot"
J2="$(mktemp)"
pget()  { curl -s "$BASE/api/?r=$1" -b "$J2" -c "$J2"; }
ppost() { curl -s -w '\n%{http_code}' "$BASE/api/?r=$1" -b "$J2" -c "$J2" -H 'X-Requested-With: fetch' -H 'Content-Type: application/json' -d "$2"; }
check "belépés nélkül nem éri el" '[ "$(curl -s -o /dev/null -w "%{http_code}" "$BASE/api/?r=portal/kb")" = 401 ]'
check "a fiók oldal betölt" 'curl -s "$BASE/fiok/" | grep -q "portal.js"'
: > "$MAIL_LOG"
r=$(post admin/customer/invite "{\"id\":$CID}"); check "belépési link elküldve az ügyfélnek" '[ "$(status "$r")" = 200 ] && grep -q "To: anna@kovacs.hu" "$MAIL_LOG"'
TOKEN=$(grep -oE "fiok/\?token=[a-f0-9]{64}" "$MAIL_LOG" | head -1 | cut -d= -f2)
check "a levélben ott a jelszóbeállító link" '[ ${#TOKEN} = 64 ]'
r=$(ppost portal/password '{"token":"'"$TOKEN"'","password":"rovid"}'); check "rövid jelszó elutasítva" '[ "$(status "$r")" = 400 ]'
r=$(ppost portal/password '{"token":"'"$TOKEN"'","password":"ugyfel123"}'); check "jelszó beállítva, belépve" '[ "$(status "$r")" = 200 ] && body "$r" | jq -e ".customer.slug == \"kovacs-iroda-kft\"" >/dev/null'
r=$(ppost portal/password '{"token":"'"$TOKEN"'","password":"masik1234"}'); check "a link csak egyszer használható" '[ "$(status "$r")" = 400 ]'
check "csak a saját tudástárát kapja" 'pget portal/kb | jq -e ".kb.temak[0].title == \"Nyitvatartás\"" >/dev/null'
check "az admin felületet nem éri el" '[ "$(curl -s -o /dev/null -w "%{http_code}" -b "$J2" "$BASE/api/?r=admin/kb&bot=my-ai")" = 401 ]'
r=$(ppost portal/kb/save '{"kb":{"nev":"Kovács Iroda","temak":[{"title":"Parkolás","keys":["parkol"],"answer":"Az udvarban ingyenes."},{"title":"Nyitvatartás","keys":["nyitva"],"answer":"H–P 8–16"}]}}')
check "az ügyfél menti a saját tanítását" '[ "$(status "$r")" = 200 ]'
check "a mentett tanítás megmarad (a chat aktív előfizetésnél ezt adja)" 'pget portal/kb | jq -e ".kb.temak[0].title == \"Parkolás\" and .live == false" >/dev/null'
check "a my-ai.hu chatbotja érintetlen" 'curl -s "$BASE/kb.php?u=my-ai" | jq -e "[.temak[].title] | index(\"Parkolás\") == null" >/dev/null'
check "az admin látja, hogy van belépése" 'get admin/customers | jq -e ".customers[0].has_portal == true and (.customers[0] | has(\"portal_password_hash\") | not)" >/dev/null'
r=$(ppost portal/logout '{}'); r=$(ppost portal/login '{"email":"anna@kovacs.hu","password":"rossz-jelszo"}'); check "rossz jelszó: 401" '[ "$(status "$r")" = 401 ]'
r=$(ppost portal/login '{"email":"anna@kovacs.hu","password":"ugyfel123"}'); check "belépés email + jelszóval" '[ "$(status "$r")" = 200 ]'
: > "$MAIL_LOG"
r=$(ppost portal/forgot '{"email":"anna@kovacs.hu"}'); check "elfelejtett jelszó: link emailben" '[ "$(status "$r")" = 200 ] && grep -q "1 óráig érvényes" "$MAIL_LOG"'

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
