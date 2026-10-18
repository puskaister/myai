# Online időpontfoglaló – telepítési útmutató

Köszönjük, hogy megvásároltad az önálló időpontfoglalót. Ez a csomag a teljes rendszer: a saját tárhelyeden fut, az adatok nálad maradnak, és a saját weboldaladba építheted.

## Mit tud?

- Az ügyfeleid pár koppintással foglalnak; csak a valóban szabad időpontok választhatók, dupla foglalás nélkül.
- Párhuzamos helyek (pl. 2 emelő, 3 szék), ebédszünet, szabadság és ünnepnapok.
- Automatikus vagy kézi jóváhagyás, email-visszaigazolás az ügyfélnek, értesítés neked.
- Az ügyfél online lemondhatja és a naptárába mentheti az időpontot.
- Saját mezők a foglalási űrlapon (pl. rendszám, gumiméret).
- Admin felület: foglalások, telefonos foglalások rögzítése, szolgáltatások, nyitvatartás, szövegek, logó és színek.
- Telefonon appként is telepíthető (saját ikon, teljes képernyő).

## Követelmények

- PHP 7.4 vagy újabb (8.x ajánlott), `mysqli` és `mbstring` kiterjesztéssel. A `gd` kiterjesztéssel az app ikonja a logódból/színedből készül.
- MySQL vagy MariaDB adatbázis.
- HTTPS (SSL-tanúsítvány) a domainen — a telefonos telepítéshez és a biztonságos belépéshez.

## Telepítés

1. **Csomagold ki** a zipet, és töltsd fel az `idopontfoglalo` mappát a tárhelyedre (FTP-vel vagy a tárhely fájlkezelőjével). Nevezd át, ahogy szeretnéd, pl. `foglalas` — így a címe `https://a-domained.hu/foglalas/` lesz.
2. **Hozz létre egy adatbázist** a tárhely kezelőfelületén.
3. **Másold** az `api/config.example.php` fájlt `api/config.php` néven, és írd bele az adatbázis adatait. Az adatbázis-szerver neve a tárhelyszolgáltatótól függ: gyakran `localhost`, néha egy IP-cím.
4. **Az `uploads` mappa** legyen írható (a logó és az ikonok ide kerülnek). A legtöbb tárhelyen alapból az.
5. **Nyisd meg** a `https://a-domained.hu/foglalas/install.php` oldalt. Add meg a vállalkozásod nevét, válassz kiinduló csomagot (gumiszerviz, fodrászat, autószerelő műhely, fogászat, rendelő vagy általános), és hozd létre az admin fiókodat. Ellenőrzésképp az adatbázis jelszavát kéri.
6. **Szabd testre** az admin felületen (`/foglalas/admin/`): szolgáltatások, nyitvatartás, logó, színek, szövegek, értesítési email.

## Beépítés a weboldaladba

Az admin felület **Beállítások** fülén, a „Beépítés a weboldaladba” részben megtalálod a pontos kódot a saját címeddel. Két lehetőség van:

**Lebegő „Időpontfoglalás” gomb** a jobb alsó sarokban — kattintásra a foglaló a weboldaladon ugrik fel. Illeszd be a `</body>` elé (WordPressben pl. egy „Egyéni HTML” blokkba vagy a lábléc-kód beállításba):

```html
<script src="https://a-domained.hu/foglalas/widget.js" data-szin="#e2552e" data-szoveg="Időpontfoglalás" defer></script>
```

**A foglaló az oldalba ágyazva**, egy adott helyen (pl. a „Foglalás” oldaladon):

```html
<div id="idopontfoglalo"></div>
<script src="https://a-domained.hu/foglalas/widget.js" data-hely="#idopontfoglalo" defer></script>
```

Bármely meglévő gombod is megnyithatja a felugró foglalót, ha az elemre ezt teszed: `data-idopontfoglalo`. Egyszerű linkként is megoszthatod: `https://a-domained.hu/foglalas/` (weboldal, Facebook, Google Cégprofil, QR-kód).

## Levelek kézbesítése

Alapból a tárhely PHP `mail()` funkciója küldi a leveleket. Ha nem érkeznek meg, vagy a spam mappába kerülnek, töltsd ki az `api/config.php` `smtp` részét egy valódi postafiók adataival (szerver, port 465, felhasználónév, jelszó, feladó).

## Adatvédelem

A foglaláskor az ügyfelek neve, telefonszáma, email címe és a megadott adatok a te adatbázisodba kerülnek — ezeknek te vagy az adatkezelője. Tüntesd fel az adatkezelési tájékoztatódban, és ha kell, a foglalási űrlap „Feltételek / tudnivalók” szövegében hivatkozz rá.

## Felhasználási feltételek

- A csomag egy vállalkozás saját weboldalán, saját foglalási rendszerként használható (egy telepítés; ugyanannak a vállalkozásnak egy tesztpéldánya megengedett).
- A csomag vagy annak módosított változata nem adható tovább, nem értékesíthető, és nem kínálható szolgáltatásként harmadik félnek.
- A rendszert a saját igényeid szerint módosíthatod; a módosított részekért a felelősség a tiéd.
- A szoftvert a vásárláskori állapotában adjuk át. A telepítésben szívesen segítünk.

## Kérdésed van?

info@my-ai.hu · +36 30 584 5937 (8:00–17:00) · https://my-ai.hu
Immobilis Partners KFT
