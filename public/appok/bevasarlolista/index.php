<?php
// Bevásárlólista – részletes oldal (my-ai.hu/appok/bevasarlolista/).
declare(strict_types=1);

require dirname(__DIR__, 2) . '/_inc/layout.php';

$dl = download_info('bevasarlolista');

page_start('Bevásárlólista – szóbeli bevitellel – my-ai.hu', 'Közös bevásárlólista magyar szóbeli bevitellel: mondd be, hogy „kenyér, tej meg két kiló alma”, és felírja. Bolti sorrendben, a családdal megosztva, telefonra telepíthető. Ingyenesen letölthető.', 'appok');
app_hero('bevasarlolista', 'Ingyenesen letölthető · szóbeli bevitel',
    'Mondd be, és felírja: „kenyér, tej meg két kiló alma”. A tételek bolti sorrendbe kerülnek, a családdal közösen használhatod, és gyenge térerőnél is működik.',
    [['/bevasarlas/', 'App megnyitása →'], $dl['size'] !== '' ? [$dl['url'], $dl['button']] : ['#letoltes', 'Letöltés'], ['#hogyan-mukodik', 'Hogyan működik?']]);
?>
  <section class="soft">
    <div class="wrap">
      <h2>Kevesebb gépelés, gyorsabb bevásárlás</h2>
      <div style="margin-top:22px">
        <ul class="checks feature-list">
          <li>Szóbeli bevitel magyarul — egyszerre több tétel, mennyiséggel</li>
          <li>Érti a „két kiló”, „fél”, „másfél liter”, „20 deka”, „egy csomag” kifejezéseket</li>
          <li>Bolti sorrend: zöldség, pékáru, tejtermék, hús, tartós, ital, háztartás</li>
          <li>Közös lista meghívó linkkel — a változások pár másodperc alatt mindenkinél</li>
          <li>Gyakran vett tételek egy koppintással</li>
          <li>Gyenge térerőnél is működik: a módosítások később szinkronizálódnak</li>
        </ul>
      </div>
      <div class="uses" aria-label="Kinek ajánljuk">
        <span class="chip">Család</span><span class="chip">Pár</span><span class="chip">Lakótársak</span><span class="chip">Nagyszülők</span><span class="chip">Iroda</span><span class="chip">Egyesület</span>
      </div>
    </div>
  </section>

  <section id="hogyan-mukodik">
    <div class="wrap">
      <h2>Hogyan működik?</h2>
      <div class="grid four">
        <div class="card">
          <div class="icon"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/></svg></div>
          <h3>Mondd be</h3>
          <p>Nyomd meg a mikrofont: „vegyél tejet, kenyeret meg két kiló almát” — három tétel, mennyiséggel.</p>
        </div>
        <div class="card">
          <div class="icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h10M4 18h7"/></svg></div>
          <h3>Rendezi</h3>
          <p>A tételek kategóriák szerint, bolti sorrendben jelennek meg — nem kell ide-oda járni a boltban.</p>
        </div>
        <div class="card">
          <div class="icon"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6M14 20c.3-2.5 1.8-4.5 4-4.5 2 0 3 1.5 3 4.5"/></svg></div>
          <h3>Megosztod</h3>
          <p>Egy linkkel a párod, a családod is csatlakozik — amit ők felírnak, azonnal nálad is ott van.</p>
        </div>
        <div class="card">
          <div class="icon"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M8 12l3 3 5-6"/></svg></div>
          <h3>Kipipálod</h3>
          <p>A boltban egy koppintás, és a tétel a kosárba kerül. Vásárlás után egy gombbal letisztítod.</p>
        </div>
      </div>
      <p class="meta" style="margin-top:18px">A hangfelismerés Chrome-ban (Android, számítógép) és Safariban működik. Ahol nem elérhető, a billentyűzet diktálás-gombjával ugyanígy bemondhatod a tételeket.</p>
    </div>
  </section>

  <section class="soft" id="letoltes">
    <div class="wrap">
      <h2>Ingyenes letöltés a saját tárhelyedre</h2>
      <p class="lead" style="margin-top:12px">Futtasd a saját domaineden: a listák nálad maradnak, és a családod saját fiókkal csatlakozhat.</p>
      <div class="req">
        <span class="chip">PHP 7.4+ (8.x ajánlott)</span>
        <span class="chip">MySQL / MariaDB</span>
        <span class="chip">HTTPS a domainen (mikrofonhoz)</span>
        <span class="chip">Nincs Composer, nincs build</span>
      </div>
      <ol class="steps-list">
        <li>Töltsd fel a kicsomagolt <code>bevasarlolista</code> mappát a tárhelyedre.</li>
        <li>Másold az <code>api/config.example.php</code>-t <code>api/config.php</code> néven, és írd bele az adatbázis adatait.</li>
        <li>Nyisd meg a <code>/bevasarlolista/install.php</code> oldalt, és hozd létre a saját fiókodat.</li>
        <li>Az appban a „Megosztás” gombbal hívd meg a családot, és tedd ki a telefonod főképernyőjére.</li>
      </ol>
      <?php download_block($dl, 'Letöltés: bevásárlólista'); ?>
    </div>
  </section>

<?php
contact_section('Kérdésed van a Bevásárlólistával kapcsolatban?', 'Írj vagy hívj — segítünk, vagy saját változatot készítünk (pl. céges beszerzési listát).');
page_end();
