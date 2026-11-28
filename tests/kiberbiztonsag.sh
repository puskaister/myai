#!/usr/bin/env bash
# Kiberbiztonság oldalak + domain-vizsgáló API (public/kiberbiztonsag/, public/_inc/domaincheck.php).
# Valódi DNS-sel fut (a CI gépről): a my-ai.hu és a google.com beállításait nézi.
set -u
BASE="${BASE:-http://127.0.0.1:8010}"
FAILS=0
pass() { echo "  ok  $1"; }
fail() { echo "  HIBA $1"; echo "::error title=Kiberbiztonság teszt hiba::$1"; FAILS=$((FAILS + 1)); }
check() { if eval "$2"; then pass "$1"; else fail "$1"; fi; }
api() { curl -s -w '\n%{http_code}' --max-time 90 "$BASE/kiberbiztonsag/api/domain.php?d=$1"; }
body()   { sed '$d' <<<"$1"; }
status() { tail -n1 <<<"$1"; }

echo "== Oldalak"
for u in /kiberbiztonsag/ /kiberbiztonsag/gyorsellenorzes/; do
  P=$(curl -s -w '\n%{http_code}' "$BASE$u")
  check "$u betölt (200, PHP-hiba nélkül)" '[ "$(status "$P")" = 200 ] && ! grep -qiE "fatal error|warning:|parse error|notice:|deprecated:" <<<"$P"'
  check "$u: canonical" 'grep -q "<link rel=\"canonical\" href=\"https://my-ai.hu$u\">" <<<"$P"'
  check "$u: aktív menüpont" 'grep -q "href=\"/kiberbiztonsag/\" aria-current=\"page\"" <<<"$P"'
done
P=$(curl -s "$BASE/kiberbiztonsag/gyorsellenorzes/")
check "15 kérdés, kérdésenként 4 válasz" '[ "$(grep -c "class=\"kb-q\"" <<<"$P")" = 15 ] && [ "$(grep -c "type=\"radio\"" <<<"$P")" = 60 ]'
check "kérdés-adatok JSON-ként beágyazva, súlyok összege 100" '[ "$(sed -n "s/.*<script type=\"application\/json\" id=\"kb-data\">\(.*\)<\/script>.*/\1/p" <<<"$P" | jq "[.questions[].w] | add")" = 100 ]'
check "a főoldal menüjében ott a Kiberbiztonság" 'curl -s "$BASE/" | grep -q "href=\"/kiberbiztonsag/\">Kiberbiztonság"'
check "stíluslap és szkript elérhető" '[ "$(curl -s -o /dev/null -w "%{http_code}" "$BASE/kiberbiztonsag/assets/kiber.css")" = 200 ] && [ "$(curl -s -o /dev/null -w "%{http_code}" "$BASE/kiberbiztonsag/assets/gyorsellenorzes.js")" = 200 ]'
check "a _inc mappa kívülről tiltott (.htaccess létezik)" 'grep -q "Require all denied" public/_inc/.htaccess'

H=$(curl -s -D - -o /dev/null "$BASE/kiberbiztonsag/")
check "biztonsági fejlécek a saját oldalakon" 'grep -qi "^x-content-type-options: nosniff" <<<"$H" && grep -qi "^x-frame-options: sameorigin" <<<"$H" && grep -qi "^referrer-policy:" <<<"$H"'

echo "== Domain API – bemenet"
r=$(api ''); check "üres domain → 400" '[ "$(status "$r")" = 400 ] && jq -e .error <<<"$(body "$r")" >/dev/null'
r=$(api 'nem%20domain'); check "hibás domain → 400" '[ "$(status "$r")" = 400 ]'
r=$(api 'localhost'); check "localhost → 400 (nincs TLD)" '[ "$(status "$r")" = 400 ]'
check "csak GET" '[ "$(curl -s -o /dev/null -w "%{http_code}" -X POST "$BASE/kiberbiztonsag/api/domain.php?d=my-ai.hu")" = 405 ]'

echo "== Domain API – my-ai.hu"
r=$(api 'https%3A%2F%2Fwww.my-ai.hu%2Fappok%2F')
B=$(body "$r")
check "200, normalizált domain" '[ "$(status "$r")" = 200 ] && [ "$(jq -r .domain <<<"$B")" = my-ai.hu ]'
check "pontszám 0–100" 'jq -e ".score >= 0 and .score <= 100" <<<"$B" >/dev/null'
check "MX, SPF, DMARC, HTTPS tételek" 'jq -e "[.items[].id] | contains([\"mx\",\"spf\",\"dmarc\",\"https\"])" <<<"$B" >/dev/null'
check "HTTPS érvényes (ok)" '[ "$(jq -r ".items[] | select(.id==\"https\") | .status" <<<"$B")" = ok ]'
check "http → https átirányítás felismerve" '[ "$(jq -r ".items[] | select(.id==\"redirect\") | .status" <<<"$B")" = ok ]'
check "HSTS felismerve (a DiMa beállítja)" '[ "$(jq -r ".items[] | select(.id==\"hsts\") | .status" <<<"$B")" = ok ]'
check "minden hibás/figyelmeztető pontozott tételhez van teendő" 'jq -e "[.items[] | select((.status==\"bad\" or .status==\"warn\") and .max > 0 and .fix == null)] | length == 0" <<<"$B" >/dev/null'
echo "    my-ai.hu: $(jq -c "{score, items: [.items[] | {id, status, points, max}]}" <<<"$B")"
echo "::notice title=my-ai.hu domain-vizsgálat::$(jq -r '"\(.score)/100 – " + ([.items[] | "\(.id): \(.status)"] | join(", "))' <<<"$B")"

echo "== Domain API – google.com (szigorú beállítások)"
B=$(body "$(api 'google.com')")
check "SPF ok" '[ "$(jq -r ".items[] | select(.id==\"spf\") | .status" <<<"$B")" = ok ]'
check "DMARC rekord felismerve (nem bad)" '[ "$(jq -r ".items[] | select(.id==\"dmarc\") | .status" <<<"$B")" != bad ]'
# a google.com dátum alapú DKIM-szelektort használ, amit kívülről nem lehet kitalálni: elég, ha a tétel megvan
check "DKIM tétel megvan (ok vagy warn, teendővel)" 'jq -e ".items[] | select(.id==\"dkim\") | (.status==\"ok\") or (.status==\"warn\" and .fix != null)" <<<"$B" >/dev/null'

echo "== Domain API – nem létező domain"
B=$(body "$(api 'nemletezo-domain-teszt-8f3k2.hu')")
check "nincs SPF/DMARC → bad, nincs weboldal → info" '[ "$(jq -r ".items[] | select(.id==\"spf\") | .status" <<<"$B")" = bad ] && [ "$(jq -r ".items[] | select(.id==\"web\") | .status" <<<"$B")" = info ]'

echo "== Belső címre nem csatlakozik"
check "magán IP-re mutató név → nincs weboldal-vizsgálat" 'php -r "require \"public/_inc/domaincheck.php\"; echo dc_public_ip(\"localtest.me\") === null && dc_public_ip(\"10.0.0.1.nip.io\") === null ? \"ok\" : \"x\";" | grep -qx ok'
check "domain-normalizálás (IDN, e-mail, www, port)" 'php -r "require \"public/_inc/domaincheck.php\"; echo dc_normalize_domain(\"info@Cég.HU\").\"|\".dc_normalize_domain(\"http://www.pelda.hu:8080/x?y\").\"|\".var_export(dc_normalize_domain(\"127.0.0.1\"), true);" | grep -qx "xn--cg-bja.hu|pelda.hu|NULL"'

echo "== Visszaélés elleni korlát"
codes=""
for i in $(seq 1 16); do codes="$codes $(curl -s -o /dev/null -w '%{http_code}' --max-time 90 "$BASE/kiberbiztonsag/api/domain.php?d=x")"; done
check "érvénytelen kérés nem számít bele" '! grep -q 429 <<<"$codes"'
# eddig 3 érvényes vizsgálat volt; 12 további még belefér (15 / 10 perc), a 16. már 429
codes=""
for i in $(seq 1 13); do codes="$codes $(curl -s -o /dev/null -w '%{http_code}' --max-time 90 "$BASE/kiberbiztonsag/api/domain.php?d=nemletezo-$i-teszt-8f3k2.hu")"; done
check "15 vizsgálat után 429 (kapott: $codes)" '[ "$(tr " " "\n" <<<"$codes" | grep -c 200)" = 12 ] && [ "${codes##* }" = 429 ]'

# ---------------------------------------------------------------------------
# Teendőlista e-mailben, érdeklődők, hírlevél, admin (MySQL + naplófájlba írt levelek)
if [ -n "${MAIL_LOG:-}" ] && [ -n "${DB_PASS:-}" ]; then
sql() { mysql -h127.0.0.1 -ukiber -p"$DB_PASS" kiber -N -B -e "$1" 2>/dev/null; }
report() { curl -s -w '\n%{http_code}' --max-time 90 -H 'Content-Type: application/json' -d "$1" "$BASE/kiberbiztonsag/api/report.php"; }
EMAIL=teszt.elek@example.com
ANS='{"mfa_email":"nem","backup":"nemtudom","bank_verify":"igen","updates":"reszben"}'

echo "== Teendőlista – bemenet"
r=$(report '{"email":"rossz","consent":true,"answers":{"mfa_email":"nem"}}'); check "hibás e-mail → 400" '[ "$(status "$r")" = 400 ]'
r=$(report "{\"email\":\"$EMAIL\",\"consent\":false,\"answers\":$ANS}"); check "hozzájárulás nélkül → 400" '[ "$(status "$r")" = 400 ]'
r=$(report "{\"email\":\"$EMAIL\",\"consent\":true,\"answers\":{\"mfa_email\":\"<script>\",\"kitalalt\":\"igen\"}}"); check "csak érvénytelen válaszok → 400" '[ "$(status "$r")" = 400 ]'
check "GET → 405" '[ "$(curl -s -o /dev/null -w "%{http_code}" "$BASE/kiberbiztonsag/api/report.php")" = 405 ]'
r=$(report "{\"email\":\"robot@example.com\",\"consent\":true,\"answers\":$ANS,\"website\":\"http://spam\"}")
check "robotcsapda: látszólag ok, de nincs mentés" '[ "$(status "$r")" = 200 ] && [ "$(sql "SELECT COUNT(*) FROM kibertest_leads WHERE email=\"robot@example.com\"")" = 0 ]'

echo "== Teendőlista – küldés"
: > "$MAIL_LOG"
r=$(report "{\"email\":\"Teszt.Elek@Example.com\",\"company\":\"Teszt Kft.\",\"consent\":true,\"newsletter\":true,\"answers\":$ANS,\"domain\":\"https://www.my-ai.hu\"}")
check "200, ok (válasz: $(body "$r" | head -c 200))" '[ "$(status "$r")" = 200 ] && jq -e ".ok == true" <<<"$(body "$r")" >/dev/null'
row=$(sql "SELECT email, company, domain, answered, newsletter, IFNULL(confirmed_at,'-'), mail_ok, todos > 0 FROM kibertest_leads WHERE email='$EMAIL'")
check "mentve: kisbetűs e-mail, cég, normalizált domain, 4 válasz, hírlevél kérve, nincs megerősítve, levél ok ($row)" '[ "$row" = "$(printf "%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s" "$EMAIL" "Teszt Kft." my-ai.hu 4 1 - 1 1)" ]'
check "a levél a teendőkkel megérkezett" 'grep -q "Itt a teendőlistád" "$MAIL_LOG" && grep -q "Kétlépcsős azonosítás bekapcsolása az e-mail-fiókokon" "$MAIL_LOG" && grep -q "Először derítsd ki" "$MAIL_LOG" && grep -q "Részben már megvan" "$MAIL_LOG"'
check "a „rendben” válasz nem teendő" '! grep -q "Szabály: új bankszámlaszámot" "$MAIL_LOG"'
check "a domain-eredmény is a levélben (gyorsítótárból)" 'grep -q "Domain (my-ai.hu)" "$MAIL_LOG"'
check "megerősítő és leiratkozó link a levélben" 'grep -q "/kiberbiztonsag/hirlevel/?a=megerosites&t=" "$MAIL_LOG" && grep -q "/kiberbiztonsag/hirlevel/?a=leiratkozas&t=" "$MAIL_LOG"'
check "értesítés az admin címre az új érdeklődőről" 'grep -q "^To: admin@example.com" "$MAIL_LOG" && grep -q "Új teendőlista-kérés" "$MAIL_LOG" && grep -q "Hírlevél: kérte" "$MAIL_LOG"'
TOKEN=$(sql "SELECT token FROM kibertest_leads WHERE email='$EMAIL' LIMIT 1")

echo "== Hírlevél: megerősítés, leiratkozás"
P=$(curl -s "$BASE/kiberbiztonsag/hirlevel/?a=megerosites&t=$TOKEN")
check "a link megnyitása még nem erősít meg (linkellenőrzők ellen)" 'grep -q "Igen, feliratkozom" <<<"$P" && [ "$(sql "SELECT confirmed_at IS NULL FROM kibertest_leads WHERE token=\"$TOKEN\"")" = 1 ]'
P=$(curl -s "$BASE/kiberbiztonsag/hirlevel/" -d "a=megerosites&t=$TOKEN&do=confirm")
check "gombra megerősítve" 'grep -q "a feliratkozás megerősítve" <<<"$P" && [ "$(sql "SELECT confirmed_at IS NOT NULL FROM kibertest_leads WHERE token=\"$TOKEN\"")" = 1 ]'
check "rossz token → hibaüzenet" 'curl -s "$BASE/kiberbiztonsag/hirlevel/?a=leiratkozas&t=00000000000000000000000000000000" | grep -q "érvénytelen"'

echo "== Korlátok"
r=$(report "{\"email\":\"$EMAIL\",\"consent\":true,\"answers\":$ANS}"); check "2. küldés ugyanarra a címre ok" '[ "$(status "$r")" = 200 ]'
r=$(report "{\"email\":\"$EMAIL\",\"consent\":true,\"answers\":$ANS}"); check "3. küldés ok" '[ "$(status "$r")" = 200 ]'
r=$(report "{\"email\":\"$EMAIL\",\"consent\":true,\"answers\":$ANS}"); check "4. küldés ugyanarra a címre egy napon belül → 429" '[ "$(status "$r")" = 429 ] && grep -q "ma már" <<<"$(body "$r")"'
r=$(report "{\"email\":\"masik@example.com\",\"consent\":true,\"answers\":$ANS}"); check "másik cím még mehet (5. kérés az IP-ről)" '[ "$(status "$r")" = 200 ]'
r=$(report "{\"email\":\"harmadik@example.com\",\"consent\":true,\"answers\":$ANS}"); check "6. kérés egy órán belül az IP-ről → 429" '[ "$(status "$r")" = 429 ]'

P=$(curl -s "$BASE/kiberbiztonsag/hirlevel/" -d "a=leiratkozas&t=$TOKEN&do=unsub")
check "leiratkozás: minden bejegyzésnél" 'grep -q "Leiratkoztál" <<<"$P" && [ "$(sql "SELECT SUM(newsletter) FROM kibertest_leads WHERE email=\"$EMAIL\"")" = 0 ]'

echo "== Admin (belépőlink)"
JAR=$(mktemp)
check "belépés nélkül nincs lista, csak belépőlink-kérés" 'P=$(curl -s "$BASE/kiberbiztonsag/admin/"); grep -q "Belépőlink küldése" <<<"$P" && ! grep -q "$EMAIL" <<<"$P"'
check "belépés nélkül nincs CSV" '! curl -s "$BASE/kiberbiztonsag/admin/?csv=1" | grep -q "$EMAIL"'
: > "$MAIL_LOG"
curl -s "$BASE/kiberbiztonsag/admin/" -d do=send-link >/dev/null
LT=$(grep -o "belepes=[0-9a-f]*" "$MAIL_LOG" | head -1 | cut -d= -f2)
check "belépőlink az admin címre" '[ ${#LT} = 48 ] && grep -q "^To: admin@example.com" "$MAIL_LOG"'
check "a link megnyitása csak egy gombot mutat" 'curl -s "$BASE/kiberbiztonsag/admin/?belepes=$LT" | grep -q "value=\"login\""'
code=$(curl -s -o /dev/null -w '%{http_code}' -c "$JAR" -b "$JAR" "$BASE/kiberbiztonsag/admin/?belepes=$LT" -d "do=login&t=$LT")
check "belépés a gombbal (303 + süti)" '[ "$code" = 303 ] && grep -q kiber_admin "$JAR"'
check "a belépőlink másodszor nem használható" 'curl -s "$BASE/kiberbiztonsag/admin/" -d "do=login&t=$LT" | grep -q "lejárt vagy már felhasználták"'
P=$(curl -s -b "$JAR" "$BASE/kiberbiztonsag/admin/")
check "belépve: lista és statisztika" 'grep -q "$EMAIL" <<<"$P" && grep -q "masik@example.com" <<<"$P" && grep -q "különböző e-mail-cím" <<<"$P"'
check "csak feliratkozók: üres (mindenki leiratkozott vagy nem erősített meg)" '! curl -s -b "$JAR" "$BASE/kiberbiztonsag/admin/?f=sub" | grep -q "mailto:$EMAIL"'
CSV=$(curl -s -b "$JAR" "$BASE/kiberbiztonsag/admin/?csv=1")
check "CSV: fejléc és sorok" 'grep -q "E-mail;Cég;Domain" <<<"$CSV" && grep -q "masik@example.com" <<<"$CSV"'
CSRF=$(grep -o 'name="csrf" value="[0-9a-f]*"' <<<"$P" | head -1 | grep -o '[0-9a-f]\{32\}')
check "törlés CSRF-token nélkül nem megy" 'curl -s -b "$JAR" "$BASE/kiberbiztonsag/admin/" -d "do=delete&email=masik@example.com&csrf=x" | grep -q "Lejárt űrlap" && [ "$(sql "SELECT COUNT(*) FROM kibertest_leads WHERE email=\"masik@example.com\"")" = 1 ]'
check "törlés tokennel" 'curl -s -b "$JAR" "$BASE/kiberbiztonsag/admin/" -d "do=delete&email=masik@example.com&csrf=$CSRF" | grep -q "1 bejegyzés törölve" && [ "$(sql "SELECT COUNT(*) FROM kibertest_leads WHERE email=\"masik@example.com\"")" = 0 ]'

echo "== Adatok törlése a levél linkjével"
P=$(curl -s "$BASE/kiberbiztonsag/hirlevel/" -d "a=leiratkozas&t=$TOKEN&do=delete")
check "minden bejegyzés törölve" 'grep -q "Töröltük" <<<"$P" && [ "$(sql "SELECT COUNT(*) FROM kibertest_leads WHERE email=\"$EMAIL\"")" = 0 ]'
check "adatkezelési tájékoztató: gyorsellenőrzés és hírlevél szakasz" 'P=$(curl -s "$BASE/adatkezeles/"); grep -q "id=\"gyorsellenorzes\"" <<<"$P" && grep -q "Kiberbiztonsági hírlevél" <<<"$P"'
fi

echo
if [ "$FAILS" -gt 0 ]; then echo "$FAILS hiba"; exit 1; fi
echo "Minden kiberbiztonság-teszt rendben."
