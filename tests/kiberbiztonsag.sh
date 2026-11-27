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

echo
if [ "$FAILS" -gt 0 ]; then echo "$FAILS hiba"; exit 1; fi
echo "Minden kiberbiztonság-teszt rendben."
