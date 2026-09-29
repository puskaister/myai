#!/usr/bin/env bash
# Végponttól végpontig teszt a látogatószámlálóhoz (követés + irányítópult).
set -u
BASE="${BASE:-http://127.0.0.1:8003}"
DB_PASS="${DB_PASS:?DB_PASS kell}"
JAR="$(mktemp)"
FAILS=0
CHROME='Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36'
IPHONE='Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1'
pass() { echo "  ok  $1"; }
fail() { echo "  HIBA $1"; echo "::error title=Látogatószámláló teszt hiba::$1"; FAILS=$((FAILS + 1)); }
check() { if eval "$2"; then pass "$1"; else fail "$1"; fi; }
track() { curl -s -w '\n%{http_code}' "$BASE/track.php" -A "$1" -H 'Content-Type: text/plain' -d "$2"; }
api_get()  { curl -s "$BASE/api/?r=$1" -b "$JAR" -c "$JAR"; }
api_post() { curl -s -w '\n%{http_code}' "$BASE/api/?r=$1" -b "$JAR" -c "$JAR" -H 'X-Requested-With: fetch' -H 'Content-Type: application/json' -d "$2"; }
body()   { sed '$d' <<<"$1"; }
status() { tail -n1 <<<"$1"; }

echo "== Telepítés"
check "telepítés előtt átirányít" '[ "$(curl -s -o /dev/null -w "%{http_code}" "$BASE/")" = 302 ]'
out=$(curl -s "$BASE/install.php" -d site=teszt.hu -d name=Admin -d email=admin@example.com -d password=titkos123 -d password2=titkos123 --data-urlencode dbpass="$DB_PASS")
check "telepítés sikerül" 'grep -q "Kész!" <<<"$out"'
check "követő szkript elérhető" 'curl -s "$BASE/t.js" | grep -q "track.php"'

echo "== Követés"
r=$(track "$CHROME" '{"t":"view","v":"abcdefgh12345678","h":"teszt.hu","p":"/arak","ti":"Árak","r":"https://www.google.com/search?q=x","s":"1920x1080","l":"hu-HU"}')
check "megtekintés rögzítve (pv + aláírás)" '[ "$(status "$r")" = 200 ] && jq -e ".pv > 0 and (.k | length) == 20" <<<"$(body "$r")" >/dev/null'
PV=$(body "$r" | jq .pv); K=$(body "$r" | jq -r .k)
check "CORS fejléc" 'curl -s -D - -o /dev/null -X OPTIONS "$BASE/track.php" | grep -qi "access-control-allow-origin: \*"'
r=$(track "$CHROME" "{\"t\":\"ping\",\"pv\":$PV,\"k\":\"$K\",\"a\":42}"); check "eltöltött idő frissítés" '[ "$(status "$r")" = 204 ]'
r=$(track "$CHROME" "{\"t\":\"ping\",\"pv\":$PV,\"k\":\"$K\",\"a\":30}"); check "idő nem csökken (42 marad)" '[ "$(status "$r")" = 204 ]'
r=$(track "$CHROME" "{\"t\":\"click\",\"pv\":$PV,\"k\":\"$K\",\"tg\":\"a\",\"lb\":\"Próbáld ki\",\"hr\":\"https://teszt.hu/foglalas/\"}"); check "kattintás rögzítve" '[ "$(status "$r")" = 204 ]'
r=$(track "$CHROME" "{\"t\":\"click\",\"pv\":$PV,\"k\":\"rossz\",\"tg\":\"a\",\"lb\":\"x\"}"); check "rossz aláírással elutasítva" '[ "$(status "$r")" = 403 ]'
r=$(track "Googlebot/2.1 (+http://www.google.com/bot.html)" '{"t":"view","v":"botbotbotbot1234","h":"teszt.hu","p":"/"}'); check "robot nem számít" '[ "$(status "$r")" = 204 ]'
r=$(track "$IPHONE" '{"t":"view","v":"abcdefgh12345678","h":"teszt.hu","p":"/","r":"https://teszt.hu/arak"}'); check "második oldal ugyanattól a látogatótól" '[ "$(status "$r")" = 200 ]'
r=$(track "$IPHONE" '{"t":"view","v":"zzzzzzzz99999999","h":"teszt.hu","p":"/"}'); check "másik látogató" '[ "$(status "$r")" = 200 ]'

echo "== Irányítópult"
check "belépés nélkül tiltott" '[ "$(curl -s -o /dev/null -w "%{http_code}" "$BASE/api/?r=admin/stats")" = 401 ]'
r=$(api_post login '{"email":"admin@example.com","password":"titkos123"}'); check "belépés" '[ "$(status "$r")" = 200 ]'
st=$(api_get "admin/stats&days=7")
check "összesítő: 3 megtekintés, 2 látogató, 2 új, 1 kattintás, átlag 42 mp" 'jq -e ".totals.pageviews == 3 and .totals.visitors == 2 and .totals.new == 2 and .totals.clicks == 1 and .totals.avg_seconds == 42" <<<"$st" >/dev/null'
check "idősor: 7 nap, ma 3" 'jq -e "(.series | length) == 7 and .series[-1].pv == 3" <<<"$st" >/dev/null'
check "hivatkozó: google.com (belső nem)" 'jq -e ".referrers == [{\"name\":\"google.com\",\"n\":1}]" <<<"$st" >/dev/null'
check "böngészők: Chrome és Safari" 'jq -e "[.browsers[].name] | sort == [\"Chrome\",\"Safari\"]" <<<"$st" >/dev/null'
check "eszközök: mobil és asztali" 'jq -e "[.devices[].name] | sort == [\"asztali\",\"mobil\"]" <<<"$st" >/dev/null'
check "kattintás toplista" 'jq -e ".clicks[0].name == \"Próbáld ki\"" <<<"$st" >/dev/null'
check "mai nézet óránként" 'api_get "admin/stats&days=1" | jq -e "(.series | length) >= 1 and (.series[-1].label | test(\":00\"))" >/dev/null'
vs=$(api_get "admin/visits&days=7")
check "látogatások: IP, böngésző, idő, katt." 'jq -e "(.visits | length) == 3 and (.visits[-1].ip | length) > 0 and .visits[-1].browser == \"Chrome\" and .visits[-1].active_seconds == 42 and .visits[-1].clicks == 1" <<<"$vs" >/dev/null'
check "iPhone felismerés" 'jq -e ".visits[0].os == \"iOS\" and .visits[0].device == \"mobil\"" <<<"$vs" >/dev/null'
check "látogatás részletei: kattintás + út" 'api_get "admin/visit&id=$PV" | jq -e "(.clicks | length) == 1 and (.journey | length) == 2" >/dev/null'
check "keresés látogatóra" 'api_get "admin/visits&days=7&q=abcdefgh12345678" | jq -e "(.visits | length) == 2" >/dev/null'

MYIP=$(api_get admin/settings | jq -r .my_ip)
r=$(api_post admin/settings "{\"site_name\":\"teszt.hu\",\"anonymize_ip\":true,\"exclude_ips\":[],\"retention_days\":395}"); check "anonimizálás bekapcsolva" '[ "$(status "$r")" = 200 ]'
track "$CHROME" '{"t":"view","v":"anonanonanon1234","h":"teszt.hu","p":"/anon"}' >/dev/null
check "anonimizált IP (.0)" 'api_get "admin/visits&days=7&q=/anon" | jq -e ".visits[0].ip | endswith(\".0\")" >/dev/null'
r=$(api_post admin/settings "{\"site_name\":\"teszt.hu\",\"anonymize_ip\":false,\"exclude_ips\":[\"$MYIP\"],\"retention_days\":395}"); check "saját IP kizárva" '[ "$(status "$r")" = 200 ]'
r=$(track "$CHROME" '{"t":"view","v":"kizartkizart1234","h":"teszt.hu","p":"/kizart"}'); check "kizárt IP-ről nem mér" '[ "$(status "$r")" = 204 ]'
r=$(api_post admin/settings '{"site_name":"x","exclude_ips":["nem-ip"]}'); check "hibás IP elutasítva" '[ "$(status "$r")" = 400 ]'
r=$(api_post admin/reset-data '{}'); check "adatok törlése" '[ "$(status "$r")" = 200 ] && api_get "admin/stats&days=7" | jq -e ".totals.pageviews == 0" >/dev/null'

echo
if [ "$FAILS" -gt 0 ]; then echo "$FAILS teszt HIBÁS"; exit 1; fi
echo "Látogatószámláló: minden teszt rendben."
