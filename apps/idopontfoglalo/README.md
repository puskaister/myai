# Időpontfoglaló (PWA)

Cégenként külön telepíthető időpontfoglaló. Böngészőben weboldalként fut, és iOS-en és Androidon appként telepíthető a kezdőképernyőre. Natív PHP + MySQL, Composer és build lépés nélkül, így bármilyen megosztott PHP-tárhelyen elfut (pl. DiMa).

Mindent az admin felületről lehet a céghez igazítani, kódolás nélkül: név, logó, fő szín (ebből készül az app ikon is), szolgáltatások, nyitvatartás, zárva tartás, párhuzamos helyek száma, extra űrlapmezők (pl. rendszám), jóváhagyási mód, szövegek és értesítések.

## Oldalak

| Cím | Mi ez |
|---|---|
| `/` | Foglalási oldal (ügyfeleknek) |
| `/?foglalas=<azonosító>` | Egy foglalás megtekintése és lemondása (az emailben lévő link) |
| `/admin/` | Admin felület |
| `/install.php` | Egyszeri telepítő |

## Új cég telepítése

1. Hozz létre egy MySQL adatbázist a tárhelyen.
2. Töltsd fel a mappa tartalmát a cég webhelyére (akár almappába, pl. `/foglalas`).
3. Hozd létre az `api/config.php`-t az `api/config.example.php` alapján. A GitHub-os deploy ezt a repó secretjeiből maga elkészíti: `DB_NAME`, `DB_USER`, `DB_PASS`, opcionálisan `DB_HOST`.
4. Nyisd meg az `install.php`-t. Add meg a cég nevét, válassz kiinduló csomagot (gumiszerviz, fodrászat, rendelő, általános), és hozd létre az admin fiókot. Ellenőrzésképp az adatbázis jelszavát kéri, hogy idegen ne tudja elsőként telepíteni.
5. Az admin felületen szabd testre.

Az `uploads/` mappának írhatónak kell lennie (logó, ikon-gyorsítótár).

## Emailek

Alapból a PHP `mail()` függvényével küld. Ha a tárhelyen ez nem kézbesít megbízhatóan, töltsd ki az `smtp` részt a `config.php`-ban egy postafiók adataival.

## Technikai jegyzetek

- **Ütközésvédelem:** a foglalás MySQL `GET_LOCK` alatt ellenőrzi újra a szabad helyet, így két egyidejű kérés nem kaphatja meg ugyanazt az utolsó helyet.
- **Kapacitás:** egy időpontban legfeljebb `capacity` aktív (függő vagy visszaigazolt) foglalás lehet. Az ellenőrzés az egyidejű átfedést nézi, nem az összes átfedést.
- **Védelem:** a POST kéréseknél a frontend egyedi fejléce CSRF ellen véd. Van honeypot mező a robotok ellen, és IP-alapú korlát a foglalásra (6/óra) és a belépésre (10/15 perc).
- **Offline:** a service worker (`sw.js`) csak a statikus fájlokat tárolja. Az API-t soha, hogy a szabad időpont mindig friss legyen.
- **Tesztek:** `.github/workflows/test-idopontfoglalo.yml` valódi PHP-val és MySQL-lel végigfuttatja a telepítést, a foglalást, a kapacitást, a lemondást és az admin műveleteket (`tests/idopontfoglalo.sh`).
