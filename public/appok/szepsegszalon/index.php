<?php
// Szépségszalon időpontfoglaló – részletes oldal (my-ai.hu/appok/szepsegszalon/).
// Ugyanaz az időpontfoglaló, a „Fodrászat / szépségszalon” kiinduló csomaggal beállítva.
declare(strict_types=1);

require dirname(__DIR__, 2) . '/_inc/layout.php';

page_start('Szépségszalon időpontfoglaló – my-ai.hu', 'Online időpontfoglaló szépségszalonoknak: a vendég a telefonjáról foglal, csak a szabad időpontok választhatók, email visszaigazolás. Nálunk üzemeltetve 30 000 Ft + ÁFA beállítás és 3 000 Ft + ÁFA / hó, vagy önálló változat egyszeri 49 000 Ft + ÁFA.', 'appok');
app_hero('szepsegszalon', '30 000 Ft + ÁFA beállítás, 3 000 Ft + ÁFA / hó · szépségszalonoknak',
    'Szépségszalonoknak: a vendégeid akkor foglalnak, amikor eszükbe jut — este a kanapéról is —, te pedig nem a telefont nyomkodod vendég közben.',
    [['#arak', 'Árak és változatok'], ['#kapcsolat', 'Ajánlatot kérek']]);
?>
  <section class="soft">
    <div class="wrap">
      <h2>A szalonod saját foglalója</h2>
      <p class="lead" style="margin-top:12px">A szalonod nevével, logójával és színeivel. A szolgáltatásokat, árakat és a nyitvatartást te módosíthatod az admin felületen — programozás nélkül.</p>
      <div style="margin-top:22px">
      <ul class="checks feature-list">
        <li>Szolgáltatások saját időtartammal és árral: hajvágás, festés, melír, arckezelés, manikűr…</li>
        <li>Csak a valóban szabad időpontok foglalhatók — ütközés és dupla foglalás nélkül</li>
        <li>Több szék vagy munkatárs egyszerre: párhuzamos foglalások kezelése</li>
        <li>A vendég megadhatja, kihez szeretne menni, és más fontos adatot is (pl. hajhossz)</li>
        <li>Email visszaigazolás a vendégnek, értesítés neked minden új foglalásról</li>
        <li>A vendég online lemondhatja és a naptárába mentheti az időpontot</li>
        <li>Telefonos foglalások rögzítése az admin felületen, hogy minden egy helyen legyen</li>
      </ul>
      </div>
    </div>
  </section>

  <section>
    <div class="wrap">
      <h2>Mit nyersz vele?</h2>
      <div class="grid four">
        <div class="card">
          <div class="icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/></svg></div>
          <h3>Kevesebb telefon</h3>
          <p>Nem kell vendég közben felvenni a telefont és a határidőnaplót lapozni.</p>
        </div>
        <div class="card">
          <div class="icon"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></div>
          <h3>Foglalás éjjel-nappal</h3>
          <p>A vendég akkor foglal, amikor eszébe jut — zárás után is, nem csak nyitvatartásban.</p>
        </div>
        <div class="card">
          <div class="icon"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M8 3v4M16 3v4M3 10h18"/></svg></div>
          <h3>Kevesebb üres időpont</h3>
          <p>Ha a vendég nem tud jönni, egy kattintással lemondja, és az időpont újra foglalható.</p>
        </div>
        <div class="card">
          <div class="icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7z"/><path d="M9 12l2 2 4-4"/></svg></div>
          <h3>Megosztható mindenhol</h3>
          <p>Weboldal, Facebook, Instagram-bio, Google Cégprofil vagy QR-kód a recepción.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="soft">
    <div class="wrap">
      <h2>Hogyan kapod meg?</h2>
      <ol class="steps-list">
        <li><strong>Elküldöd</strong> a szolgáltatásaidat, árakat, nyitvatartást és a logódat.</li>
        <li><strong>Beállítjuk a szalonod arculatával</strong> — munkatársak, szolgáltatások, lemondási feltételek.</li>
        <li><strong>Megkapod a saját címedet</strong>, amit megoszthatsz a weboldaladon, Facebookon, Instagramon vagy QR-kódon.</li>
      </ol>
    </div>
  </section>

  <section id="arak">
    <div class="wrap">
      <h2>Két változat: havidíjjal nálunk, vagy egyszeri díjjal a saját tárhelyeden</h2>
      <div class="grid two" style="margin-top:22px">
        <div class="card">
          <h3>Nálunk üzemeltetve</h3>
          <p style="font-size:1.6rem;font-weight:800;margin:6px 0 2px">30 000 Ft + ÁFA</p>
          <p class="muted" style="margin:0 0 2px">egyszeri beállítási díj (bruttó 38 100 Ft)</p>
          <p style="font-weight:700;margin:0 0 2px">+ 3 000 Ft + ÁFA / hó</p>
          <p class="muted" style="margin:0 0 12px">üzemeltetés, tárhely, frissítések (bruttó 3 810 Ft / hó)</p>
          <ul class="checks">
            <li>Mi állítjuk be a szalonod arculatával</li>
            <li>A my-ai.hu-n fut (pl. my-ai.hu/szalonneved), tárhely nélkül</li>
            <li>Weboldalba építhető gombbal vagy linkkel</li>
          </ul>
          <a class="btn btn-primary" href="#kapcsolat">Ajánlatot kérek</a>
        </div>
        <div class="card">
          <h3>Önálló változat a saját tárhelyedre</h3>
          <p style="font-size:1.6rem;font-weight:800;margin:6px 0 2px">49 000 Ft + ÁFA</p>
          <p class="muted" style="margin:0 0 12px">egyszeri díj (bruttó 62 230 Ft)</p>
          <ul class="checks">
            <li>A teljes rendszer telepítőcsomagja, benne a szépségszalon kiinduló beállítással</li>
            <li>Az adatok nálad maradnak, havidíj nélkül</li>
            <li>Kell hozzá: PHP 7.4+ és MySQL tárhely, HTTPS</li>
          </ul>
          <a class="btn btn-primary" href="/appok/onallo-idopontfoglalo/">Részletek →</a>
        </div>
      </div>
    </div>
  </section>

<?php
contact_section('Szeretnél saját foglalót a szalonodnak?', 'Írj vagy hívj — rövid határidővel beállítjuk a szalonod arculatával.');
page_end();
