<?php
// Kiberbiztonság kiscégeknek (my-ai.hu/kiberbiztonsag/): a témakör bemutató oldala,
// az ingyenes gyorsellenőrzés (gyorsellenorzes/) és a készülő eszközök.
declare(strict_types=1);

require dirname(__DIR__) . '/_inc/layout.php';

$css = '/kiberbiztonsag/assets/kiber.css?v=' . @filemtime(__DIR__ . '/assets/kiber.css');
$mail = fn (string $subject) => 'mailto:' . SITE['email'] . '?subject=' . rawurlencode($subject);

page_start(
    'Kiberbiztonság kiscégeknek – ingyenes gyorsellenőrzés | my-ai.hu',
    'Védd meg a vállalkozásodat az adathalászattól, a hamis számláktól és a zsarolóvírustól, informatikus nélkül is. Ingyenes 10 perces gyorsellenőrzés pontszámmal és teendőlistával.',
    'kiberbiztonsag',
    ['title' => 'Mennyire védett a vállalkozásod? – ingyenes gyorsellenőrzés', 'description' => '15 kérdés és a céges domain automatikus vizsgálata. Az eredmény egy pontszám és egy egyszerű teendőlista.']
);
?>
  <link rel="stylesheet" href="<?= e($css) ?>">

  <section class="hero">
    <div class="wrap">
      <div>
        <span class="eyebrow">Kiberbiztonság kiscégeknek</span>
        <h1>Informatikus nélkül is <span class="grad">védhető a vállalkozásod.</span></h1>
        <p class="lead">A kiscégeket ritkán célzott támadás éri. Sokkal gyakoribb egy átverős levél, egy hamis számla vagy egy régóta nem frissített gép. Ezek ellen néhány egyszerű beállítás is sokat véd, és megmutatjuk, melyikkel kezdd.</p>
        <div class="cta">
          <a class="btn btn-primary" href="/kiberbiztonsag/gyorsellenorzes/">Ingyenes gyorsellenőrzés →</a>
          <a class="btn btn-ghost" href="#mit-vizsgalunk">Mit vizsgálunk?</a>
        </div>
        <div class="platforms">
          <span class="chip">10 perc</span>
          <span class="chip">Regisztráció nélkül</span>
          <span class="chip">Semmit nem kell telepíteni</span>
        </div>
      </div>

      <div class="kb-hero-card" aria-label="Minta eredmény">
        <div class="kb-gauge"><span>62</span><small>/ 100</small></div>
        <p style="text-align:center;font-weight:700;color:var(--kb-mid)">Közepes védelem, vannak fontos hiányok</p>
        <ul class="kb-mini">
          <li><span class="kb-dot bad"></span>Nincs DMARC: a cég nevében küldött levél hamisítható</li>
          <li><span class="kb-dot bad"></span>Új bankszámlaszámot nem ellenőriznek visszahívással</li>
          <li><span class="kb-dot warn"></span>A mentést még soha nem próbálták visszaállítani</li>
          <li><span class="kb-dot ok"></span>Érvényes HTTPS-tanúsítvány</li>
          <li><span class="kb-dot ok"></span>Kétlépcsős azonosítás az e-mailen</li>
        </ul>
      </div>
    </div>
  </section>

  <section>
    <div class="wrap">
      <div class="center">
        <span class="eyebrow">A leggyakoribb veszélyek</span>
        <h2>Ezek érik a kiscégeket a leggyakrabban</h2>
        <p class="lead">Egyik ellen sem kell drága rendszer: legtöbbször egy jól beállított fiók, egy mentés és egy közös szabály is elég.</p>
      </div>
      <div class="grid three kb-threats">
        <div class="card">
          <div class="icon"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg></div>
          <h3>Adathalász levelek</h3>
          <p>„Lejár a jelszavad”, „csomagod érkezett”, „nézd meg a számlát”. Egy kattintás, és a támadó bejut a céges e-mail-fiókba.</p>
        </div>
        <div class="card">
          <div class="icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h9l3 3v15H6z"/><path d="M9 10h6M9 14h6M9 18h3"/></svg></div>
          <h3>Hamis számla, új bankszámlaszám</h3>
          <p>A „partner” levélben közli, hogy megváltozott a bankszámlaszáma. A pénz egy csaló számlájára megy, és ritkán jön vissza.</p>
        </div>
        <div class="card">
          <div class="icon"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 018 0v4"/></svg></div>
          <h3>Zsarolóvírus</h3>
          <p>Titkosítja a gépeken és a közös mappákban lévő fájlokat, és váltságdíjat kér. Ellene a működő, elkülönített mentés véd a legjobban.</p>
        </div>
        <div class="card">
          <div class="icon"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="8" cy="15" r="4"/><path d="M11 12l9-9M17 6l3 3M14 9l2 2"/></svg></div>
          <h3>Kiszivárgott jelszavak</h3>
          <p>Ha ugyanaz a jelszó több helyen is használatban van, egy idegen oldal adatszivárgása után a céges fiók is veszélybe kerül.</p>
        </div>
        <div class="card">
          <div class="icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7z"/><path d="M12 8v5M12 16h.01"/></svg></div>
          <h3>Hamisított céges e-mail</h3>
          <p>A cég domainjéről bárki küldhet levelet, ha nincs beállítva az SPF, a DKIM és a DMARC. Így a te ügyfeleidet is átverhetik a nevedben.</p>
        </div>
        <div class="card">
          <div class="icon"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="4"/><path d="M2 21c0-4 3-6 7-6s7 2 7 6M17 8l4 4M21 8l-4 4"/></svg></div>
          <h3>Megmaradt hozzáférések</h3>
          <p>A kilépett dolgozó még hónapokig be tud lépni a közös fiókokba, mert senki nem cserélte le a jelszót.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="soft" id="mit-vizsgalunk">
    <div class="wrap">
      <div class="center">
        <span class="eyebrow">Ingyenes gyorsellenőrzés</span>
        <h2>10 perc, és tudod, hol tartasz</h2>
        <p class="lead">Nem kell informatikusnak lenned: egyszerű kérdések, érthető magyarázatok és teendők fontossági sorrendben.</p>
      </div>
      <div class="grid three steps">
        <div class="card step">
          <h3>15 egyszerű kérdés</h3>
          <p>Fiókok és jelszavak, mentés, gépek és telefonok, emberek és folyamatok. Igen, részben, nem vagy „nem tudom” a válasz.</p>
        </div>
        <div class="card step">
          <h3>A domain automatikus vizsgálata</h3>
          <p>Hamisítható-e a céged nevében küldött e-mail (SPF, DKIM, DMARC)? Érvényes-e a weboldal tanúsítványa, és be vannak-e állítva a biztonsági fejlécek?</p>
        </div>
        <div class="card step">
          <h3>Pontszám és teendőlista</h3>
          <p>0–100-as pontszám és teendők „Azonnal / 30 napon belül / Később” sorrendben, lépésenkénti útmutatóval. Kinyomtathatod vagy elküldheted az informatikusodnak.</p>
        </div>
      </div>
      <div class="center" style="margin-top:32px">
        <a class="btn btn-primary" href="/kiberbiztonsag/gyorsellenorzes/">Elkezdem az ellenőrzést →</a>
        <p class="kb-muted" style="margin-top:12px">A kérdőív válaszai csak a te böngésződben maradnak. A domain-vizsgálat csak nyilvános adatokat néz.</p>
      </div>
    </div>
  </section>

  <section class="kb-soon">
    <div class="wrap">
      <div class="center">
        <span class="eyebrow">Fejlesztés alatt</span>
        <h2>Hamarosan: folyamatos védelem</h2>
        <p class="lead">A gyorsellenőrzés az első lépés. Ezeken az eszközökön dolgozunk. Ha érdekel valamelyik, írj, és szólunk, amikor kipróbálható.</p>
      </div>
      <div class="grid three">
        <div class="card">
          <span class="badge">Hamarosan</span>
          <h3>Havi biztonsági figyelés</h3>
          <p>Hetente újravizsgáljuk a domaint, és szólunk, ha lejár a tanúsítvány, elromlik a DMARC, vagy egy céges cím megjelenik egy adatszivárgásban.</p>
          <p style="margin-top:14px"><a href="<?= e($mail('Érdekel: havi biztonsági figyelés')) ?>">Érdekel →</a></p>
        </div>
        <div class="card">
          <span class="badge">Hamarosan</span>
          <h3>Hamis számla figyelő</h3>
          <p>Jelez, ha egy ismert szállító levelében vagy számláján új bankszámlaszám jelenik meg, és visszahívást javasol az utalás előtt.</p>
          <p style="margin-top:14px"><a href="<?= e($mail('Érdekel: hamis számla figyelő')) ?>">Érdekel →</a></p>
        </div>
        <div class="card">
          <span class="badge">Hamarosan</span>
          <h3>Adathalász-tréning</h3>
          <p>Havonta egy ártalmatlan próba-adathalász levél a dolgozóknak. Aki kattint, kétperces magyarázatot kap, te pedig egyszerű kimutatást látsz.</p>
          <p style="margin-top:14px"><a href="<?= e($mail('Érdekel: adathalász-tréning')) ?>">Érdekel →</a></p>
        </div>
      </div>
    </div>
  </section>

<?php contact_section('Nincs informatikusod? Beállítjuk helyetted.', 'SPF, DKIM, DMARC, kétlépcsős azonosítás, mentés, frissítések: a gyorsellenőrzés teendőinek többségét egy délután alatt elvégezzük, és elmagyarázzuk, mit miért csináltunk.'); ?>
<?php
page_end();
