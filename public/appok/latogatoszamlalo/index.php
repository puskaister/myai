<?php
// Látogatószámláló – részletes oldal és letöltés (my-ai.hu/appok/latogatoszamlalo/).
// A csomagot a deploy készíti a /letoltes/files/ mappába; a letöltés a
// /letoltes/?fajl=latogatoszamlalo címen megy (később egyedi kódhoz köthető).
declare(strict_types=1);

require dirname(__DIR__, 2) . '/_inc/layout.php';

$dl = download_info('latogatoszamlalo');

page_start('Látogatószámláló – ingyenes letöltés – my-ai.hu', 'Saját webstatisztika a weboldaladra: látogatók, eltöltött idő, kattintások, böngészők — külső szolgáltatás nélkül, a saját tárhelyeden. Ingyenesen letölthető.', 'appok');
app_hero('latogatoszamlalo', 'Ingyenesen letölthető',
    'Lásd, hányan jönnek, honnan érkeznek, mennyi időt töltenek az oldaladon és mire kattintanak — külső szolgáltatás nélkül, minden adat a saját tárhelyeden marad.',
    $dl['size'] !== '' ? [[$dl['url'], $dl['button']], ['#telepites', 'Telepítés']] : [['#telepites', 'Telepítés']]);
?>
  <section class="soft">
    <div class="wrap">
      <h2>Mit tud?</h2>
      <div class="grid three">
        <div class="card"><h3>Látogatók és oldalak</h3><p>Egyedi és új látogatók, oldalmegtekintések napi és óránkénti grafikonon, a legnézettebb oldalak.</p></div>
        <div class="card"><h3>Valódi eltöltött idő</h3><p>Csak azt az időt méri, amíg a lap tényleg látható volt — nem a háttérben nyitva felejtett füleket.</p></div>
        <div class="card"><h3>Kattintások</h3><p>Melyik gombra, linkre kattintanak a látogatók, és hova vezetett.</p></div>
        <div class="card"><h3>Honnan jöttek</h3><p>Hivatkozó oldalak (Google, Facebook…), böngészők, rendszerek, eszközök (asztali, mobil, tablet).</p></div>
        <div class="card"><h3>Egyenkénti látogatások</h3><p>IP-cím, időpont, böngésző, eltöltött idő, kattintások — és egy látogató teljes útja az oldalon.</p></div>
        <div class="card"><h3>GDPR-barát</h3><p>Beépített hozzájárulás-sáv, IP-anonimizálás, kizárható saját IP, automatikus adattörlés, robotszűrés.</p></div>
      </div>
    </div>
  </section>

  <section id="telepites">
    <div class="wrap">
      <h2>Telepítés 5 perc alatt</h2>
      <div class="req">
        <span class="chip">PHP 7.4+ (8.x ajánlott)</span>
        <span class="chip">MySQL / MariaDB</span>
        <span class="chip">Bármely megosztott tárhely</span>
        <span class="chip">Nincs Composer, nincs build</span>
      </div>
      <ol class="steps-list">
        <li>Töltsd fel a kicsomagolt <code>latogatoszamlalo</code> mappát a tárhelyedre (pl. <code>/stats</code> néven).</li>
        <li>Másold az <code>api/config.example.php</code>-t <code>api/config.php</code> néven, és írd bele az adatbázis adatait.</li>
        <li>Nyisd meg a <code>/stats/install.php</code> oldalt, és hozd létre az admin fiókot.</li>
        <li>Illeszd be a két soros kódot a weboldalad <code>&lt;head&gt;</code> részébe — a pontos kódot az irányítópult Beállítások fülén is megtalálod.</li>
      </ol>
      <?php download_block($dl, 'Letöltés: látogatószámláló'); ?>
    </div>
  </section>

<?php
contact_section('Elakadtál, vagy beállítsuk helyetted?', 'Írj vagy hívj — segítünk a telepítésben, vagy beállítjuk a weboldaladra.');
page_end();
