#!/usr/bin/env bash
# Végponttól végpontig teszt a Bevásárlólista apphoz: listák, tételek és
# kategóriák, változáskövetés, közös lista meghívóval, jogosultságok.
set -u
BASE="${BASE:-http://127.0.0.1:8006}"
DB_PASS="${DB_PASS:?DB_PASS kell}"
J1="$(mktemp)"; J2="$(mktemp)"; J3="$(mktemp)"
FAILS=0
pass() { echo "  ok  $1"; }
fail() { echo "  HIBA $1"; echo "::error title=Bevásárlólista teszt hiba::$1"; FAILS=$((FAILS + 1)); }
check() { if eval "$2"; then pass "$1"; else fail "$1"; fi; }
get()  { curl -s "$BASE/api/?r=$2" -b "$1" -c "$1"; }
post() { curl -s -w '\n%{http_code}' "$BASE/api/?r=$2" -b "$1" -c "$1" -H 'X-Requested-With: fetch' -H 'Content-Type: application/json' -d "$3"; }
body()   { sed '$d' <<<"$1"; }
status() { tail -n1 <<<"$1"; }

echo "== Telepítés"
check "telepítés előtt átirányít" '[ "$(curl -s -o /dev/null -w "%{http_code}" "$BASE/")" = 302 ]'
out=$(curl -s "$BASE/install.php" -d name=Anna -d email=anna@example.com -d password=titkos123 -d password2=titkos123 -d allow_register=on --data-urlencode dbpass="$DB_PASS")
check "telepítés sikerül" 'grep -q "Kész!" <<<"$out"'
check "app oldal a szótárral, manifest, ikon, szótár" 'curl -s "$BASE/" | grep -q "parseShopping\|parse.js" && curl -s "$BASE/manifest.php" | jq -e ".display == \"standalone\"" >/dev/null && curl -s "$BASE/icon.php?s=192" | head -c 4 | od -An -tx1 | grep -q "89 50 4e 47" && curl -s "$BASE/assets/dict.json" | jq -e ".categories | length == 9" >/dev/null'

echo "== Lista és tételek"
r=$(post "$J1" login '{"email":"anna@example.com","password":"titkos123"}'); check "belépés" '[ "$(status "$r")" = 200 ]'
LID=$(get "$J1" lists | jq '.lists[0].id')
check "alapértelmezett lista" '[ "$LID" -gt 0 ] 2>/dev/null'
r=$(post "$J1" items/add "{\"list_id\":$LID,\"items\":[{\"name\":\"alma\",\"qty\":\"2 kg\"},{\"name\":\"Tej\"},{\"name\":\"wc-papír\"},{\"name\":\"csirkemell filé\"},{\"name\":\"almát\"},{\"name\":\"valami furcsa\"}]}")
check "hozzáadás" '[ "$(status "$r")" = 200 ]'
b=$(body "$r")
cat_of() { jq -r --arg n "$1" '[.items[] | select(.name == $n)][0].category' <<<"$b"; }
check "kategóriák: zöldség, tejtermék, háztartás, hús, egyéb" '[ "$(cat_of Alma)" = produce ] && [ "$(cat_of Tej)" = dairy ] && [ "$(cat_of Wc-papír)" = household ] && [ "$(cat_of "Csirkemell filé")" = meat ] && [ "$(cat_of "Valami furcsa")" = other ]'
check "ragozott alak is jó kategóriába kerül (Almát)" '[ "$(cat_of Almát)" = produce ]'
check "nagy kezdőbetű" 'jq -e "[.items[].name] | index(\"Alma\")" <<<"$b" >/dev/null'
r=$(post "$J1" items/add "{\"list_id\":$LID,\"items\":[{\"name\":\"alma\",\"qty\":\"3 kg\"}]}")
check "ugyanaz a tétel nem duplázódik, a mennyiség frissül" 'jq -e "[.items[] | select(.name == \"Alma\")] | length == 1 and .[0].qty == \"3 kg\"" <<<"$(body "$r")" >/dev/null'
V=$(get "$J1" "list&id=$LID" | jq .list.version)
check "változáskövetés: azonos verziónál 'unchanged'" 'get "$J1" "list&id=$LID&v=$V" | jq -e ".unchanged == true" >/dev/null'
TEJ=$(get "$J1" "list&id=$LID" | jq '[.items[] | select(.name == "Tej")][0].id')
r=$(post "$J1" items/update "{\"id\":$TEJ,\"checked\":true}")
check "kipipálás, a verzió nő" '[ "$(status "$r")" = 200 ] && jq -e "[.items[] | select(.name == \"Tej\")][0].checked == true and .list.version > $V" <<<"$(body "$r")" >/dev/null'
r=$(post "$J1" items/clear "{\"list_id\":$LID}")
check "kipipáltak törlése" 'jq -e ".removed == 1 and ([.items[] | select(.name == \"Tej\")] | length == 0)" <<<"$(body "$r")" >/dev/null'
check "javaslatok: a korábban vett Tej" 'get "$J1" "suggestions&list_id=$LID" | jq -e "[.suggestions[].name] | index(\"Tej\")" >/dev/null'
r=$(post "$J1" items/update "{\"id\":$TEJ,\"checked\":true}"); check "törölt tétel módosítása 404" '[ "$(status "$r")" = 404 ]'

echo "== Közös lista"
URL=$(body "$(post "$J1" lists/invite "{\"id\":$LID}")" | jq -r .url)
TOKEN=${URL##*join=}
check "meghívó link" '[ ${#TOKEN} = 32 ]'
r=$(post "$J2" register '{"name":"Béla","email":"bela@example.com","password":"titkos456"}'); check "regisztráció (engedélyezve)" '[ "$(status "$r")" = 200 ]'
check "a második felhasználó nem látja a listát csatlakozás előtt" '[ "$(curl -s -o /dev/null -w "%{http_code}" -b "$J2" "$BASE/api/?r=list&id=$LID")" = 404 ]'
r=$(post "$J2" join "{\"token\":\"$TOKEN\"}"); check "csatlakozás a meghívóval" '[ "$(status "$r")" = 200 ] && [ "$(body "$r" | jq .id)" = "$LID" ]'
V=$(get "$J1" "list&id=$LID" | jq .list.version)
post "$J2" items/add "{\"list_id\":$LID,\"items\":[{\"name\":\"kenyér\"}]}" >/dev/null
check "a társ által felvett tétel megjelenik (verzió változott)" 'get "$J1" "list&id=$LID&v=$V" | jq -e "(.unchanged // false) == false and ([.items[] | select(.name == \"Kenyér\" and .category == \"bakery\")] | length == 1)" >/dev/null'
check "két tag a listán" 'get "$J1" "list&id=$LID" | jq -e ".members | length == 2" >/dev/null'
r=$(post "$J2" lists/invite "{\"id\":$LID,\"reset\":true}"); check "új meghívót csak a tulajdonos készíthet" '[ "$(status "$r")" = 403 ]'
post "$J1" lists/invite "{\"id\":$LID,\"reset\":true}" >/dev/null
r=$(post "$J3" register '{"name":"Cili","email":"cili@example.com","password":"titkos789"}')
r=$(post "$J3" join "{\"token\":\"$TOKEN\"}"); check "a lecserélt meghívó már nem működik" '[ "$(status "$r")" = 404 ]'
IT=$(get "$J1" "list&id=$LID" | jq '.items[0].id')
r=$(post "$J3" items/update "{\"id\":$IT,\"checked\":true}"); check "idegen nem módosíthatja a tételt" '[ "$(status "$r")" = 404 ]'
r=$(post "$J2" lists/delete "{\"id\":$LID}"); check "tag törlése = kilépés" '[ "$(status "$r")" = 200 ] && get "$J1" "list&id=$LID" | jq -e ".members | length == 1" >/dev/null'
r=$(post "$J1" lists/save '{"name":"Barkácsbolt"}'); L2=$(body "$r" | jq .id); check "új lista" '[ "$L2" -gt 0 ] 2>/dev/null'
r=$(post "$J1" lists/delete "{\"id\":$L2}"); check "tulajdonos törli a listát" '[ "$(status "$r")" = 200 ] && [ "$(curl -s -o /dev/null -w "%{http_code}" -b "$J1" "$BASE/api/?r=list&id=$L2")" = 404 ]'

echo
if [ "$FAILS" -gt 0 ]; then echo "$FAILS teszt HIBÁS"; exit 1; fi
echo "Bevásárlólista: minden teszt rendben."
