# Bevásárlólista – telepítési útmutató

Telefonra telepíthető közös bevásárlólista magyar szóbeli bevitellel. Mondd be, hogy „kenyér, tej meg két kiló alma”, és felírja. A tételek bolti sorrendbe kerülnek, a lista a családdal megosztható. Minden adat a saját tárhelyeden marad.

## Mit tud?

- **Szóbeli bevitel magyarul:** egyszerre több tétel, mennyiséggel („két kiló”, „fél”, „másfél liter”, „20 deka”, „egy csomag”).
- **Bolti sorrend:** zöldség, pékáru, tejtermék, hús, tartós, ital, háztartás.
- **Közös lista:** meghívó linkkel lehet csatlakozni. A változások pár másodperc alatt mindenkinél megjelennek.
- **Gyenge térerőnél is:** az utolsó lista megmarad, a módosítások a kapcsolat visszatérésekor elmennek.
- **Egyéb:** gyakran vett tételek egy koppintással, „Kosárban” rész, visszavonás.

## Követelmények

- PHP 7.4 vagy újabb (8.x ajánlott), `mysqli` és `mbstring` kiterjesztéssel. Ezek szinte minden tárhelyen alapból megvannak. A `gd` kiterjesztéssel az app ikonja is elkészül.
- MySQL vagy MariaDB adatbázis. Lehet egy meglévő is, a táblák saját előtagot kapnak.
- **HTTPS** (SSL-tanúsítvány) a domainen. Enélkül a böngésző nem engedi a mikrofont, és a telefon nem engedi telepíteni az appot.

## Telepítés

1. **Csomagold ki** a zipet, és töltsd fel a `bevasarlolista` mappát a tárhelyedre FTP-vel vagy a tárhely fájlkezelőjével. Például a weboldalad gyökerébe, így a címe `https://a-domained.hu/bevasarlolista/` lesz.
2. **Hozz létre egy adatbázist** a tárhely kezelőfelületén, vagy használj egy meglévőt.
3. **Másold** az `api/config.example.php` fájlt `api/config.php` néven, és írd bele az adatbázis adatait. Az adatbázis-szerver neve a tárhelyszolgáltatótól függ: gyakran `localhost`, néha egy IP-cím.
4. **Nyisd meg** a `https://a-domained.hu/bevasarlolista/install.php` oldalt. Hozd létre a saját fiókodat. Ellenőrzésképp az adatbázis jelszavát kéri. A regisztráció alapból engedélyezett, hogy a család tagjai saját fiókkal csatlakozhassanak; ez később is átállítható.
5. **Oszd meg a listát:** az appban a lista neve melletti „Megosztás” gombbal kapsz egy meghívó linket. Aki megnyitja és belép, ugyanazt a listát látja.
6. **Telepítsd a telefonodra:**
   - **iPhone:** Safari → Megosztás gomb → „Főképernyőhöz adás”
   - **Android:** Chrome menü → „Alkalmazás telepítése”

## A hangfelismerésről

A szóbeli bevitel a böngésző beépített hangfelismerését használja. Chrome-ban (Android, számítógép) és Safariban működik. Ahol nem elérhető (például a főképernyőre tett app egyes iPhone-okon), a billentyűzet diktálás-gombjával ugyanígy bemondhatod a tételeket, az app ugyanúgy szétbontja őket.

## Jelszó-emlékeztető levél

Az elfelejtett jelszóhoz az app emailt küld. Alapból a tárhely PHP `mail()` funkciója küldi. Ha nem érkezik meg, töltsd ki az `api/config.php` `smtp` részét egy valódi postafiók adataival (szerver, port 465, felhasználónév, jelszó, feladó).

## Kérdésed van?

info@my-ai.hu · https://my-ai.hu
