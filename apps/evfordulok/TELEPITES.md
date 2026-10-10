# Évfordulók – telepítési útmutató

Telefonra telepíthető app a fontos dátumokhoz: születésnapok, névnapok, évfordulók, ünnepek, egyszeri alkalmak. Előtte emailben szól, a telefonodon pedig mindig látod, mi következik. Minden adat a saját tárhelyeden marad.

## Mit tud?

- **Ismétlődés:** egyszeri, évente vagy havonta. A február 29-ét nem szökőévben 28-án jelzi.
- **Hányadik alkalom:** ha megadod az évszámot, kiírja, pl. „35. születésnap”.
- **Email-emlékeztető:** alapból az előző napon, alkalmanként állítható (aznap, 1, 2, 3, 7 vagy 14 nappal előtte, vagy kikapcsolva).
- **Kijelző:** a mai és holnapi alkalmak kiemelve, alatta a következő 12 hónap.
- **Naptár-feliratkozás:** személyes link a telefon vagy a Google naptárához.
- **Több felhasználó:** mindenki csak a sajátját látja. Az admin vehet fel felhasználót, a regisztráció ki-be kapcsolható.

## Követelmények

- PHP 7.4 vagy újabb (8.x ajánlott), `mysqli` és `mbstring` kiterjesztéssel. Ezek szinte minden tárhelyen alapból megvannak. A `gd` kiterjesztéssel az app ikonja is elkészül.
- MySQL vagy MariaDB adatbázis. Lehet egy meglévő is, a táblák saját előtagot kapnak.
- **HTTPS** (SSL-tanúsítvány) a domainen. Enélkül a telefon nem engedi telepíteni az appot.
- Napi egyszeri időzített hívás (CRON) az emlékeztető levelekhez. A legtöbb tárhely kezelőfelületén beállítható.

## Telepítés

1. **Csomagold ki** a zipet, és töltsd fel az `evfordulok` mappát a tárhelyedre FTP-vel vagy a tárhely fájlkezelőjével. Például a weboldalad gyökerébe, így a címe `https://a-domained.hu/evfordulok/` lesz.
2. **Hozz létre egy adatbázist** a tárhely kezelőfelületén, vagy használj egy meglévőt.
3. **Másold** az `api/config.example.php` fájlt `api/config.php` néven, és írd bele az adatbázis adatait. Az adatbázis-szerver neve a tárhelyszolgáltatótól függ: gyakran `localhost`, néha egy IP-cím.
4. **Nyisd meg** a `https://a-domained.hu/evfordulok/install.php` oldalt. Hozd létre a saját (admin) fiókodat. Ellenőrzésképp az adatbázis jelszavát kéri. Itt döntheted el azt is, hogy bárki regisztrálhat-e.
5. **Állítsd be a napi emlékeztetőt.** Lépj be az appba, és a Beállítások fül alján, a „Felhasználók (admin)” részben megtalálod a napi kör címét (`…/cron.php?key=…`). Ezt a címet kell naponta egyszer, reggel meghívni a tárhely CRON funkciójával, például:

   ```
   wget -q -O /dev/null "https://a-domained.hu/evfordulok/cron.php?key=A_TE_KULCSOD"
   ```

   Ha a tárhelyeden nincs CRON, az app akkor is kiküldi az aznapi leveleket, amikor reggel 7 után valaki először megnyitja.

6. **Telepítsd a telefonodra:**
   - **iPhone:** Safari → Megosztás gomb → „Főképernyőhöz adás”
   - **Android:** Chrome menü → „Alkalmazás telepítése”

## Levelek kézbesítése

Alapból a tárhely PHP `mail()` funkciója küldi a leveleket. Ha a levelek nem érkeznek meg, vagy a spam mappába kerülnek, töltsd ki az `api/config.php` `smtp` részét egy valódi postafiók adataival (szerver, port 465, felhasználónév, jelszó, feladó). A Beállításokban egy gombbal teszt levelet is küldhetsz.

## Adatvédelem

Az app neveket, dátumokat és email címeket tárol. Ha másoknak is megnyitod (regisztráció), az adatkezelési tájékoztatódban tüntesd fel.

## Kérdésed van?

info@my-ai.hu · https://my-ai.hu
