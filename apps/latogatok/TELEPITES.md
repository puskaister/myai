# Látogatószámláló – telepítési útmutató

Saját, egyszerű webstatisztika a weboldaladhoz. Megmutatja, hányan látogatnak, honnan jönnek, mennyi időt töltenek az oldalon és mire kattintanak. Minden adat a saját tárhelyeden marad, külső szolgáltatás (pl. Google Analytics) nélkül.

## Mit mér?

- **Látogatások:** egyedi és új látogatók, oldalmegtekintések, napi és óránkénti grafikonon
- **Eltöltött idő:** csak amíg a lap ténylegesen látható
- **Kattintások:** melyik linkre vagy gombra kattintottak
- **Látogatók adatai:** IP-cím (kérésre anonimizálva), böngésző, rendszer, eszköz (asztali, mobil, tablet), hivatkozó oldal
- **Egyenkénti látogatások:** egy látogató útja az oldalon, keresés IP-címre és oldalra

A robotokat kiszűri. A beépített süti-sáv miatt csak a látogató hozzájárulása után mér (GDPR).

## Követelmények

- PHP 7.4 vagy újabb (8.x ajánlott), `mysqli` és `mbstring` kiterjesztéssel. Ezek szinte minden tárhelyen alapból megvannak.
- MySQL vagy MariaDB adatbázis. Lehet egy meglévő is, a táblák saját előtagot kapnak.

## Telepítés

1. **Csomagold ki** a zipet, és töltsd fel a `latogatoszamlalo` mappát a tárhelyedre FTP-vel vagy a tárhely fájlkezelőjével. Például a weboldalad gyökerébe `stats` néven, így a címe `https://a-domained.hu/stats/` lesz.
2. **Hozz létre egy adatbázist** a tárhely kezelőfelületén, vagy használj egy meglévőt.
3. **Másold** az `api/config.example.php` fájlt `api/config.php` néven, és írd bele az adatbázis adatait.
4. **Nyisd meg** a `https://a-domained.hu/stats/install.php` oldalt. Hozd létre az admin fiókot, ellenőrzésképp az adatbázis jelszavát kéri.
5. **Illeszd be** ezt a két sort a mérni kívánt oldalak `<head>` részébe (a címet írd át a sajátodra):

   ```html
   <script src="https://a-domained.hu/stats/consent.js" data-privacy="/adatkezeles" defer></script>
   <script src="https://a-domained.hu/stats/t.js" defer></script>
   ```

   A `data-privacy` a saját adatkezelési tájékoztatód címe. Ha az oldaladnak már van saját süti-hozzájárulás kezelése, az első sort hagyd el, a második sorba pedig írd bele: `data-consent="off"`.

6. **Az irányítópult** a `https://a-domained.hu/stats/` címen érhető el. A Beállításokban zárd ki a saját IP-címedet, hogy a saját látogatásaid ne torzítsák a számokat.

## Adatvédelem

Az IP-cím és a látogatóazonosító személyes adat. Az adatkezelési tájékoztatódban tüntesd fel a mérést: milyen adatokat, milyen célból, meddig őrzöl. Az adatok alapból 395 nap után automatikusan törlődnek, ez a Beállításokban módosítható. Ugyanitt bekapcsolható az IP-címek anonimizálása is.

## Kérdésed van?

info@my-ai.hu · https://my-ai.hu
