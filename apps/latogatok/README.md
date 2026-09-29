# Látogatószámláló

Saját, egyszerű webstatisztika. Oldalanként rögzíti:
- a látogató IP-címét, időpontját, böngészőjét, operációs rendszerét és eszközét
- a ténylegesen eltöltött időt (csak amíg a lap látható volt)
- a kattintásokat (linkek, gombok, `data-track` elemek)

Az irányítópult (`/stats/`) ezeket mutatja:
- összesítők és napi vagy óránkénti grafikon
- toplisták: oldalak, hivatkozók, kattintások, böngészők, rendszerek, eszközök
- a látogatások listája a részletekkel és a látogató útjával

**Beillesztés:** `<script src="https://my-ai.hu/stats/t.js" defer></script>` a mért oldalak `<head>` részébe.

- A mérés sütit nem használ. A visszatérő látogató felismeréséhez egy véletlen azonosítót tesz a `localStorage`-ba.
- A robotokat kiszűri.
- A pingek és kattintások csak a megtekintéshez kapott HMAC-aláírással fogadhatók el.
- Az IP-címenkénti óránkénti keret 300 megtekintés.
- Beállítható: IP-anonimizálás, kizárt IP-címek (pl. a saját géped) és az adatmegőrzés ideje (alapból 395 nap).

**Adatvédelem:** az IP-cím és a látogatóazonosító személyes adatnak minősül (GDPR). Az adatkezelési tájékoztatóban szerepeljen a mérés. Szigorúbb értelmezés szerint a `localStorage`-azonosító használatához hozzájárulás (süti-sáv) kell.
