#!/usr/bin/env bash
# Egy előtag nélküli (régi) telepítés átvétele előtaggal: a táblák átnevezése
# után az adatok és a belépés is megmarad.
set -u
BASE="${BASE:-http://127.0.0.1:8000}"
DB_PASS="${DB_PASS:?DB_PASS kell}"
FAILS=0
pass() { echo "  ok  $1"; }
fail() { echo "  HIBA $1"; echo "::error title=Teszt hiba (átvétel)::$1"; FAILS=$((FAILS + 1)); }
check() { if eval "$2"; then pass "$1"; else fail "$1"; fi; }

bash tests/set-config.sh ""
out=$(curl -s "$BASE/install.php" --data-urlencode business='Régi Cég' -d preset=fodraszat -d name=Regi \
  -d email=regi@example.com -d password=regijelszo1 -d password2=regijelszo1 --data-urlencode dbpass="$DB_PASS")
check "előtag nélküli telepítés" 'grep -q "Kész!" <<<"$out"'

bash tests/set-config.sh atvett_
page=$(curl -s "$BASE/install.php")
check "az előtagos telepítő felajánlja az átvételt" 'grep -q "Korábbi telepítés átvétele" <<<"$page"'
out=$(curl -s "$BASE/install.php" -d action=adopt -d dbpass=rossz)
check "rossz jelszóval nem vehető át" 'grep -q "nem egyezik" <<<"$out"'
out=$(curl -s "$BASE/install.php" -d action=adopt --data-urlencode dbpass="$DB_PASS")
check "átvétel sikerül" 'grep -q "Kész!" <<<"$out"'
check "az átvett cég beállításai megvannak" 'curl -s "$BASE/api/?r=config" | jq -e ".settings.business.name == \"Régi Cég\" and (.services | length) == 4" >/dev/null'
code=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/api/?r=login" -H 'X-Requested-With: fetch' -H 'Content-Type: application/json' -d '{"email":"regi@example.com","password":"regijelszo1"}')
check "a régi admin be tud lépni" '[ "$code" = 200 ]'

if [ "$FAILS" -gt 0 ]; then echo "$FAILS teszt HIBÁS"; exit 1; fi
echo "Átvétel rendben."
