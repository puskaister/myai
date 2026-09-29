# Évfordulók

Telefonra telepíthető app (PWA) a fontos dátumokhoz: születésnapok, névnapok, évfordulók, ünnepek, egyszeri alkalmak.

- **Ismétlődés:** egyszeri, évente vagy havonta. A február 29-ét nem szökőévben 28-án jelzi, a havi ismétlést rövidebb hónapban az utolsó napon.
- **Hányadik alkalom:** ha az évszám ismert, kiírja, pl. „35. születésnap”.
- **Email-emlékeztető:** alapból az előző napon, alkalmanként állítható (aznap, 1, 2, 3, 7 vagy 14 nappal előtte, vagy kikapcsolva). Egy nap több alkalma egyetlen összefoglaló levélbe kerül. Ugyanarról az alkalomról soha nem megy kétszer levél (`sent_reminders`).
- **Kijelző:** a mai és holnapi alkalmak kiemelve, alatta a következő 12 hónap hónapokra bontva. Telepített appnál a mai és holnapi alkalmak száma az ikonon is látszik, ahol a rendszer támogatja.
- **Naptár-feliratkozás:** személyes ICS-link, ismétléssel és riasztással.
- **Több felhasználó:** mindenki csak a sajátját látja. Az admin vehet fel felhasználót, a regisztráció kapcsolható.

## A napi emlékeztető időzítése

A `cron.php?key=…` végpontot naponta egyszer kell meghívni. Ezt három dolog biztosítja:

1. **GitHub Actions** (`.github/workflows/evfordulok-cron.yml`): minden reggel 05:00 UTC-kor meghívja. A kulcsot a `DB_PASS` secretből számolja, ahogy az app is.
2. **Tartalék:** a tárhely CRON funkciójában is beállítható. A pontos cím az app Beállítások → Felhasználók (admin) részében látszik.
3. **Második tartalék:** ha aznap még nem futott le, az app első megnyitása reggel 7 után lefuttatja.

## Új telepítés a my-ai.hu-n

Vegyél fel egy sort a `deploy/evfordulok.json`-ba, pushold, majd nyisd meg a `/<dir>/install.php` oldalt.
