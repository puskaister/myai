# Látogatószámláló

Saját, egyszerű webstatisztika. Oldalanként rögzíti:
- a látogató IP-címét, időpontját, böngészőjét, operációs rendszerét és eszközét
- a ténylegesen eltöltött időt (csak amíg a lap látható volt)
- a kattintásokat (linkek, gombok, `data-track` elemek)

Az irányítópult (`/stats/`) ezeket mutatja:
- összesítők és napi vagy óránkénti grafikon
- toplisták: oldalak, hivatkozók, kattintások, böngészők, rendszerek, eszközök
- a látogatások listája a részletekkel és a látogató útjával

**Beillesztés** a mért oldalak `<head>` részébe:

```html
<script src="https://my-ai.hu/stats/consent.js" data-privacy="/adatkezeles/" defer></script>
<script src="https://my-ai.hu/stats/t.js" defer></script>
```

- **Hozzájárulás-sáv (`consent.js`):** a mérés csak az „Elfogadom” után indul. Elutasítás vagy visszavonás esetén nincs mérés, és a tárolt azonosító törlődik.
- **Beállítás módosítása:** `myaiConsent.open()`.
- **Ha az oldal maga kezeli a hozzájárulást:** a `consent.js` elhagyható, és a `t.js`-hez `data-consent="off"` adható.

- A mérés sütit nem használ. A visszatérő látogató felismeréséhez egy véletlen azonosítót tesz a `localStorage`-ba.
- A robotokat kiszűri.
- A pingek és kattintások csak a megtekintéshez kapott HMAC-aláírással fogadhatók el.
- Az IP-címenkénti óránkénti keret 300 megtekintés.
- Beállítható: IP-anonimizálás, kizárt IP-címek (pl. a saját géped) és az adatmegőrzés ideje (alapból 395 nap).

**Adatvédelem:** az IP-cím és a látogatóazonosító személyes adat (GDPR). A my-ai.hu tájékoztatója a `public/adatkezeles/index.php` fájlban van.
