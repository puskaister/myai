<?php
// Önálló időpontfoglaló – részletes oldal (my-ai.hu/appok/onallo-idopontfoglalo/).
// A csomag személyes letöltőkóddal tölthető le (/ugyintezes/letoltes.php); a kódot
// az Ügyintézési Segéd admin „Letöltőkódok” fülén lehet kiadni.
declare(strict_types=1);

require dirname(__DIR__, 2) . '/_inc/layout.php';

$order = 'mailto:info@my-ai.hu?subject=' . rawurlencode('Önálló időpontfoglaló megrendelése');
page_start('Önálló időpontfoglaló a saját weboldaladra – my-ai.hu', 'Az online időpontfoglaló teljes rendszere a saját tárhelyedre: egyszeri 49 000 Ft + ÁFA, havidíj nélkül. Egy sorral beépíthető a weboldaladba (WordPress is).', 'appok');
app_hero('onallo-idopontfoglalo', 'Egyszeri 49 000 Ft + ÁFA · a saját tárhelyedre',
    'Megveszed, elviszed, és a saját weboldaladon fut: az ügyfeleid ott foglalnak időpontot, ahol eddig is kerestek — a foglalások és az ügyféladatok nálad maradnak.',
    [[$order, 'Megrendelem'], ['#mire-van-szukseg', 'Mire van szükség?']]);
?>
  <section class="soft">
    <div class="wrap">
      <h2>Saját foglalási rendszer, a saját weboldaladon</h2>
      <p class="lead" style="margin-top:12px">Ugyanaz az időpontfoglaló, mint a <a href="/appok/idopontfoglalo/">nálunk üzemeltetett változat</a> — csak a teljes rendszert megkapod, és a saját tárhelyedre telepíted.</p>
      <div style="margin-top:22px">
        <ul class="checks feature-list">
          <li>A teljes rendszer telepítőcsomagja: foglalási oldal, admin felület, email-értesítések</li>
          <li>Egy sorral beépíthető a weboldaladba: lebegő „Időpontfoglalás” gomb vagy az oldalba ágyazva</li>
          <li>WordPress, saját oldal vagy bármely weboldal, ahova HTML-kódot illeszthetsz</li>
          <li>Az ügyféladatok a te adatbázisodban maradnak, havidíj és külső szolgáltató nélkül</li>
          <li>A saját neveddel, logóddal és színeiddel, programozás nélkül beállítható</li>
          <li>Részletes magyar telepítési útmutató — a telepítésben szívesen segítünk</li>
        </ul>
      </div>
    </div>
  </section>

  <section id="mire-van-szukseg">
    <div class="wrap">
      <h2>Mire van szükség?</h2>
      <p class="lead" style="margin-top:12px">Egy átlagos webtárhely elég hozzá — a legtöbb magyar tárhelycsomag (pl. amin a weboldalad is fut) mindent tud, ami kell.</p>
      <div class="grid three" style="margin-top:22px">
        <div class="card"><h3>Tárhely PHP-val</h3><p>PHP 7.4 vagy újabb (8.x ajánlott). Nem kell hozzá Composer vagy külön szerver.</p></div>
        <div class="card"><h3>MySQL adatbázis</h3><p>Egy MySQL vagy MariaDB adatbázis — a tárhely kezelőfelületén pár kattintással létrehozható.</p></div>
        <div class="card"><h3>HTTPS a domainen</h3><p>SSL-tanúsítvány (pl. ingyenes Let's Encrypt) a biztonságos belépéshez és a telefonos telepítéshez.</p></div>
        <div class="card"><h3>Email küldés</h3><p>A visszaigazoló leveleket a tárhely küldi; ha kell, egy postafiók SMTP adataival megbízhatóbb.</p></div>
        <div class="card"><h3>Hozzáférés a weboldaladhoz</h3><p>Ahol be tudod illeszteni a kódot: WordPressben pl. egy „Egyéni HTML” blokkban.</p></div>
        <div class="card"><h3>Nincs tárhelyed?</h3><p>Válaszd a <a href="/appok/idopontfoglalo/">nálunk üzemeltetett változatot</a> (30 000 Ft + ÁFA) — ott mi futtatjuk, neked semmit nem kell telepítened.</p></div>
      </div>
    </div>
  </section>

  <section class="soft">
    <div class="wrap">
      <h2>Így kerül a weboldaladra</h2>
      <ol class="steps-list">
        <li><strong>Megrendeled</strong> emailben, mi elküldjük a díjbekérőt.</li>
        <li><strong>Letöltőkódot kapsz</strong> emailben a kifizetés után — ezzel töltöd le a csomagot.</li>
        <li><strong>Feltöltöd a tárhelyedre</strong>, megadod az adatbázis adatait, és megnyitod a telepítőt (az útmutató lépésről lépésre végigvezet).</li>
        <li><strong>Beilleszted a kódot</strong> a weboldaladba — az admin felületen kimásolhatod:</li>
      </ol>
      <pre class="card" style="margin-top:14px;white-space:pre-wrap;word-break:break-all;font-size:.85rem">&lt;script src="https://a-weboldalad.hu/foglalas/widget.js" defer&gt;&lt;/script&gt;</pre>
      <div class="card" style="margin-top:22px;max-width:520px">
        <h3>Ár</h3>
        <p style="font-size:1.6rem;font-weight:800;margin:6px 0 2px">49 000 Ft + ÁFA</p>
        <p class="muted" style="margin:0 0 12px">egyszeri díj (bruttó 62 230 Ft), havidíj nélkül</p>
        <a class="btn btn-primary" href="<?= e($order) ?>">Megrendelem</a>
        <p class="meta" style="margin-top:12px">A vásárlás feltételei: <a href="/aszf/">ÁSZF</a> 13. pont.</p>
      </div>
    </div>
  </section>

  <section id="letoltes">
    <div class="wrap">
      <form class="card form" method="get" action="/ugyintezes/letoltes.php" style="max-width:620px">
        <h3 style="margin:0">Már megvásároltad? Letöltés kóddal</h3>
        <div class="row" style="display:flex;gap:10px;flex-wrap:wrap">
          <input name="kod" required placeholder="IDO-XXXX-XXXX-XXXX" aria-label="Letöltőkód" autocomplete="off" style="flex:1;min-width:200px;text-transform:uppercase">
          <button class="btn btn-primary" type="submit">Letöltés</button>
        </div>
      </form>
    </div>
  </section>

<?php
contact_section('Kérdésed van, vagy telepítsük helyetted?', 'Írj vagy hívj — megnézzük, megfelel-e a tárhelyed, és szívesen segítünk a telepítésben.');
page_end();
