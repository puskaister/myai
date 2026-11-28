<?php
// Kiberbiztonsági gyorsellenőrzés (my-ai.hu/kiberbiztonsag/gyorsellenorzes/):
// 15 kérdés + domain-vizsgálat (../api/domain.php) → pontszám és teendőlista.
// A válaszok csak a böngészőben maradnak (localStorage), a szerverre nem kerülnek.
declare(strict_types=1);

require dirname(__DIR__, 2) . '/_inc/layout.php';
require dirname(__DIR__, 2) . '/_inc/kiber-questions.php';

$v = fn (string $f) => $f . '?v=' . @filemtime(dirname(__DIR__) . '/assets/' . basename($f));

page_start(
    'Ingyenes kiberbiztonsági gyorsellenőrzés kiscégeknek – my-ai.hu',
    '10 perces ingyenes ellenőrzés: 15 kérdés és a céges domain automatikus vizsgálata (SPF, DKIM, DMARC, HTTPS). Az eredmény egy pontszám és egy fontossági sorrendbe tett teendőlista.',
    'kiberbiztonsag'
);
?>
  <link rel="stylesheet" href="<?= e($v('/kiberbiztonsag/assets/kiber.css')) ?>">

  <section class="page-head">
    <div class="wrap">
      <span class="eyebrow">Ingyenes gyorsellenőrzés</span>
      <h1>Mennyire védett a vállalkozásod?</h1>
      <p class="lead">Válaszolj 15 kérdésre, és add meg a céged domainjét. Kb. 10 perc, regisztráció nélkül. Az eredményt rögtön látod, a részletes teendőlistát pedig e-mailben küldjük el.</p>
    </div>
  </section>

  <section class="kb-section">
    <div class="wrap kb-layout">
      <div class="kb-main">

        <form class="card kb-domain" id="kb-domain-form" novalidate>
          <h2><span class="kb-num">1</span> A céged domainje</h2>
          <p class="kb-muted">Nyilvános adatokból ellenőrizzük, hamisítható-e a cég nevében küldött e-mail (SPF, DKIM, DMARC), és rendben van-e a weboldal titkosítása. Semmit nem telepítünk, és a domain rendszereit nem támadjuk.</p>
          <div class="kb-domain-row">
            <label class="kb-sr" for="kb-domain">Domain</label>
            <input id="kb-domain" name="d" type="text" inputmode="url" autocomplete="url" spellcheck="false" placeholder="pl. cegem.hu" required>
            <button class="btn btn-primary" type="submit" id="kb-domain-btn">Vizsgálat</button>
          </div>
          <div id="kb-domain-result" aria-live="polite"></div>
        </form>

        <div class="card kb-questions">
          <h2><span class="kb-num">2</span> Kérdések</h2>
          <p class="kb-muted">Ha nem tudod a választ, nyugodtan jelöld a „Nem tudom”-ot: a teendőlistában leírjuk, mit kérdezz meg az informatikusodtól. A válaszaid ebben a böngészőben maradnak.</p>
<?php $n = 0; foreach (KIBER_CATEGORIES as $cat => $catLabel): ?>
          <fieldset class="kb-cat">
            <legend><?= e($catLabel) ?></legend>
<?php foreach (KIBER_QUESTIONS as $q): if ($q['cat'] !== $cat) continue; $n++; ?>
            <div class="kb-q" data-q="<?= e($q['id']) ?>">
              <p class="kb-q-text"><b><?= $n ?>.</b> <?= e($q['q']) ?></p>
<?php if ($q['hint'] !== ''): ?>              <p class="kb-q-hint"><?= e($q['hint']) ?></p>
<?php endif; ?>
              <div class="kb-opts" role="radiogroup" aria-label="<?= e($n . '. kérdés') ?>">
<?php foreach (['igen' => 'Igen', 'reszben' => 'Részben', 'nem' => 'Nem', 'nemtudom' => 'Nem tudom'] as $val => $lbl): ?>
                <label class="kb-opt kb-opt-<?= $val ?>"><input type="radio" name="<?= e($q['id']) ?>" value="<?= $val ?>"><span><?= $lbl ?></span></label>
<?php endforeach; ?>
              </div>
            </div>
<?php endforeach; ?>
          </fieldset>
<?php endforeach; ?>
        </div>
      </div>

      <aside class="kb-side" aria-label="Eredmény">
        <div class="card kb-score" id="kb-score">
          <div class="kb-gauge" id="kb-gauge" style="--p:0"><span id="kb-score-num">–</span><small>/ 100</small></div>
          <p class="kb-level" id="kb-level">Válaszolj a kérdésekre</p>
          <p class="kb-progress" id="kb-progress">0 / <?= count(KIBER_QUESTIONS) ?> kérdés megválaszolva</p>
          <div class="kb-sub">
            <div><span>Kérdőív</span><b id="kb-sub-q">–</b></div>
            <div><span>Domain</span><b id="kb-sub-d">–</b></div>
          </div>
          <h3>Teendők</h3>
          <ul class="kb-counts" id="kb-counts"><li class="kb-muted">Itt jelenik meg, hány teendőt találtunk.</li></ul>
          <a class="btn btn-primary kb-full-link" href="#kb-report">Teendőlista e-mailben ↓</a>
        </div>
      </aside>
    </div>
  </section>

  <section class="soft kb-report-section" id="kb-report">
    <div class="wrap">
      <div class="kb-report-grid">
        <div>
          <span class="eyebrow">Teendőlista</span>
          <h2>Kérd e-mailben a részletes teendőlistát</h2>
          <p class="kb-muted kb-lead2" id="kb-report-summary">Válaszolj legalább egy kérdésre, vagy vizsgáld meg a domaint, és összeállítjuk a teendőidet.</p>
          <ul class="checks kb-report-checks">
            <li>Minden teendő fontossági sorrendben: azonnal, 30 napon belül, később</li>
            <li>Lépésenkénti útmutató, informatikai tudás nélkül is követhető</li>
            <li>A domain-vizsgálat részletes eredménye</li>
            <li>Továbbküldheted az informatikusodnak vagy a kollégáidnak</li>
          </ul>
        </div>
        <form class="card form kb-mail-form" id="kb-mail-form" novalidate>
          <label>E-mail-cím<input type="email" name="email" id="kb-email" autocomplete="email" required placeholder="nev@cegem.hu"></label>
          <label>Cég neve <span class="kb-opt-label">(nem kötelező)</span><input type="text" name="company" id="kb-company" autocomplete="organization" maxlength="120"></label>
          <label class="kb-hp" aria-hidden="true">Weboldal<input type="text" name="website" id="kb-website" tabindex="-1" autocomplete="off"></label>
          <label class="kb-check"><input type="checkbox" id="kb-consent" required><span>Elolvastam az <a href="/adatkezeles/#gyorsellenorzes" target="_blank">adatkezelési tájékoztatót</a>, és kérem a teendőlistát erre a címre.</span></label>
          <label class="kb-check"><input type="checkbox" id="kb-newsletter"><span>Időnként írhattok a kiscégeket érintő kiberveszélyekről és az új eszközeitekről. <span class="kb-opt-label">(nem kötelező, bármikor leiratkozhatok)</span></span></label>
          <button class="btn btn-primary" type="submit" id="kb-send">Kérem a teendőlistát</button>
          <p class="kb-form-msg" id="kb-form-msg" aria-live="polite"></p>
        </form>
      </div>
      <p class="kb-note">A gyorsellenőrzés tájékoztató jellegű, nem helyettesíti a teljes biztonsági auditot. A pontszámot és a domain-vizsgálat eredményét e-mail-cím nélkül is látod. A kérdőív válaszait csak akkor kapjuk meg, ha kéred a teendőlistát. <button type="button" class="kb-linkbtn" id="kb-reset">Válaszok törlése, újrakezdés</button></p>
    </div>
  </section>

<?php contact_section('Segítsünk a javításban?', 'A teendők többsége egy délután alatt elvégezhető. Ha nincs informatikusod, szívesen beállítjuk helyetted, vagy átnézzük veled az eredményt.'); ?>

  <script type="application/json" id="kb-data"><?= json_encode(['categories' => KIBER_CATEGORIES, 'questions' => KIBER_QUESTIONS], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
  <script src="<?= e($v('/kiberbiztonsag/assets/gyorsellenorzes.js')) ?>" defer></script>
<?php
page_end();
