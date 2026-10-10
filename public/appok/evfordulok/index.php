<?php
// Évfordulók – részletes oldal (my-ai.hu/appok/evfordulok/).
declare(strict_types=1);

require dirname(__DIR__, 2) . '/_inc/layout.php';

$dl = download_info('evfordulok');

page_start('Évfordulók – ingyenes letöltés – my-ai.hu', 'Születésnapok, névnapok, évfordulók egy helyen: előtte napon emailben szól, a telefonodon mindig látod, mi következik. Telefonra telepíthető app, ingyenesen letölthető a saját tárhelyedre.', 'appok');
app_hero('evfordulok', 'Ingyenesen letölthető · telefonra telepíthető',
    'Születésnapok, névnapok, évfordulók és minden fontos dátum egy helyen — előtte napon emailben szólunk, a telefonodon pedig mindig látod, mi következik.',
    [['/evfordulok/', 'App megnyitása →'], $dl['size'] !== '' ? [$dl['url'], $dl['button']] : ['#letoltes', 'Letöltés'], ['#hogyan-telepit', 'Telepítés a telefonra']]);
?>
  <section class="soft">
    <div class="wrap">
      <h2>Soha többé elfelejtett születésnap</h2>
      <p class="lead" style="margin-top:12px">Rögzítsd egyszer, és minden évben időben szól. Telefonon úgy működik, mint egy app, számítógépen böngészőből.</p>
      <div style="margin-top:22px">
      <ul class="checks feature-list">
        <li>Egyszeri, éves és havi alkalmak — a február 29-ét is jól kezeli</li>
        <li>Email-emlékeztető előtte napon (vagy aznap, pár nappal, egy héttel előtte)</li>
        <li>Ma és holnap kiemelve, a következő 12 hónap egy pillantással</li>
        <li>Kiírja, hányadik születésnap vagy évforduló következik</li>
        <li>Megjegyzés minden alkalomhoz, pl. ajándékötlet</li>
        <li>Feliratkozás a telefon naptárában is, emlékeztetővel</li>
      </ul>
      </div>
      <div class="uses" aria-label="Kinek ajánljuk">
        <span class="chip">Család</span><span class="chip">Barátok</span><span class="chip">Párok</span><span class="chip">Ügyfél-születésnapok</span><span class="chip">Munkatársak</span><span class="chip">Egyesületek</span>
      </div>
    </div>
  </section>

  <section>
    <div class="wrap">
      <h2>Miért jó?</h2>
      <div class="grid four">
        <div class="card">
          <div class="icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16v12H4z"/><path d="M4 7l8 6 8-6"/></svg></div>
          <h3>Email előtte napon</h3>
          <p>Reggel megjön a levél: holnap van anyukád születésnapja — még van idő virágot venni.</p>
        </div>
        <div class="card">
          <div class="icon"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="7" y="2" width="10" height="20" rx="2.5"/><path d="M11 18h2"/></svg></div>
          <h3>A telefonodon, mint egy app</h3>
          <p>Saját ikonnal a kezdőképernyőn; az ikonon a jelvény mutatja, ha ma vagy holnap van valami.</p>
        </div>
        <div class="card">
          <div class="icon"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M8 3v4M16 3v4M3 10h18"/></svg></div>
          <h3>A naptáradban is</h3>
          <p>Egy személyes linkkel minden alkalom megjelenik az iPhone, a Google vagy az Outlook naptárában.</p>
        </div>
        <div class="card">
          <div class="icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7z"/><path d="M9 12l2 2 4-4"/></svg></div>
          <h3>Mindenkinek a sajátja</h3>
          <p>Saját fiók, saját lista — más nem látja a te dátumaidat. Jelszóval védve.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="soft" id="hogyan-telepit">
    <div class="wrap">
      <h2>Telepítés a telefonra</h2>
      <ol class="steps-list">
        <li>Nyisd meg a <a href="/evfordulok/">my-ai.hu/evfordulok</a> oldalt a telefonodon, és lépj be (vagy regisztrálj, ha engedélyezett).</li>
        <li><strong>iPhone:</strong> Safari → Megosztás gomb → „Főképernyőhöz adás”. <strong>Android:</strong> Chrome menü → „Alkalmazás telepítése”.</li>
        <li>Kész: az Évfordulók saját ikonnal ott van a kezdőképernyőn, és az ikonon a jelvény mutatja, ha ma vagy holnap van valami.</li>
      </ol>
    </div>
  </section>

  <section id="letoltes">
    <div class="wrap">
      <h2>Ingyenes letöltés a saját tárhelyedre</h2>
      <p class="lead" style="margin-top:12px">Futtasd a saját domaineden: a dátumok és az email címek nálad maradnak, és a családod vagy a céged is használhatja.</p>
      <div class="req">
        <span class="chip">PHP 7.4+ (8.x ajánlott)</span>
        <span class="chip">MySQL / MariaDB</span>
        <span class="chip">HTTPS a domainen</span>
        <span class="chip">Napi CRON az emlékeztetőkhöz</span>
      </div>
      <ol class="steps-list">
        <li>Töltsd fel a kicsomagolt <code>evfordulok</code> mappát a tárhelyedre.</li>
        <li>Másold az <code>api/config.example.php</code>-t <code>api/config.php</code> néven, és írd bele az adatbázis adatait.</li>
        <li>Nyisd meg az <code>/evfordulok/install.php</code> oldalt, és hozd létre a saját fiókodat.</li>
        <li>Állítsd be a tárhely CRON-jában a napi emlékeztető címét — az app Beállítások fülén, a Felhasználók (admin) részben találod.</li>
      </ol>
      <?php download_block($dl, 'Letöltés: évfordulók'); ?>
    </div>
  </section>

<?php
contact_section('Kérdésed van az Évfordulókkal kapcsolatban?', 'Írj vagy hívj — segítünk a beállításban, vagy saját változatot készítünk a cégednek.');
page_end();
