#!/usr/bin/env bash
# Felületi teszt a CI-ban: a puppeteer-core telepítése rögzített verzióval (package-lock.json),
# hálózati hiba esetén újrapróbálva, majd a teszt futtatása. Használat: bash tests/ui/run.sh <app>
set -u
cd "$(dirname "$0")"
ok=0
for i in 1 2 3; do
  if npm ci --no-audit --no-fund --loglevel=error; then ok=1; break; fi
  echo "::warning title=Felületi teszt::npm ci sikertelen ($i. próba), újrapróbálom"
  sleep $((i * 10))
done
if [ "$ok" != 1 ]; then echo "::error title=Felületi teszt ($1)::nem sikerült telepíteni a puppeteer-core-t (hálózati hiba?)"; exit 1; fi
exec node ui-test.js "$@"
