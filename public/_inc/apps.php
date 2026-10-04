<?php
// Az appok egy helyen: ebből épül az Appok oldal kártyarácsa, a főoldal
// „Appjaink” blokkja és a részletes oldalak fejléce. Új app = új elem itt,
// egy új /appok/<slug>/index.php oldal, és egy mockup_<slug>() makett.
declare(strict_types=1);

const APPS = [
    'idopontfoglalo' => [
        'name'    => 'Online időpontfoglaló',
        'short'   => 'Az ügyfeleid pár koppintással foglalnak, te egy helyen látsz mindent — a vállalkozásod saját arculatával.',
        'badge'   => 'Élő demó',
        'mockup'  => 'mockup_booking',
        'for'     => 'Gumiszerviz, fodrászat, rendelő, szerviz',
    ],
    'evfordulok' => [
        'name'    => 'Évfordulók',
        'short'   => 'Születésnapok, névnapok, évfordulók egy helyen — előtte napon emailben szólunk. Telefonra telepíthető.',
        'badge'   => 'Új',
        'mockup'  => 'mockup_evfordulok',
        'for'     => 'Család, barátok, ügyfél-születésnapok',
    ],
    'bevasarlolista' => [
        'name'    => 'Bevásárlólista',
        'short'   => 'Mondd be, és felírja: „kenyér, tej meg két kiló alma”. Közös lista a családdal, bolti sorrendben.',
        'badge'   => 'Új · szóbeli bevitel',
        'mockup'  => 'mockup_bevasarlolista',
        'for'     => 'Család, pár, lakótársak',
    ],
    'ugyintezesi-seged' => [
        'name'    => 'Ügyintézési Segéd',
        'short'   => 'Chatbot a weboldaladra, ami a te válaszaidból felel az érdeklődők kérdéseire — éjjel-nappal, AI-díj nélkül. A beállítást és a beépítést mi végezzük.',
        'badge'   => '5 000 Ft + ÁFA / hó',
        'mockup'  => 'mockup_ugyintezes',
        'for'     => 'Szolgáltató, webshop, iroda, ügyfélszolgálat',
    ],
    'latogatoszamlalo' => [
        'name'    => 'Látogatószámláló',
        'short'   => 'Saját webstatisztika: látogatók, eltöltött idő, kattintások — külső szolgáltatás nélkül, a saját tárhelyeden.',
        'badge'   => 'Ingyenesen letölthető',
        'mockup'  => 'mockup_stats',
        'for'     => 'Bármely weboldal',
    ],
];

// Kártyarács: kép (makett), cím, pár szó, Tovább gomb.
function apps_grid(): void { ?>
      <div class="app-grid">
        <?php foreach (APPS as $slug => $app): ?>
          <article class="app-card">
            <a class="app-thumb" href="/appok/<?= e($slug) ?>/" tabindex="-1" aria-hidden="true">
              <div class="thumb-inner"><?php ($app['mockup'])(); ?></div>
            </a>
            <div class="app-card-body">
              <span class="badge"><?= e($app['badge']) ?></span>
              <h3><a href="/appok/<?= e($slug) ?>/"><?= e($app['name']) ?></a></h3>
              <p><?= e($app['short']) ?></p>
              <p class="app-for"><?= e($app['for']) ?></p>
              <a class="btn btn-primary" href="/appok/<?= e($slug) ?>/" aria-label="Tovább: <?= e($app['name']) ?>">Tovább →</a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
<?php
}

// A részletes oldalak fejléce: cím, bevezető, gombok, makett.
function app_hero(string $slug, string $eyebrow, string $lead, array $buttons): void {
    $app = APPS[$slug]; ?>
  <section class="app-hero">
    <div class="wrap">
      <nav class="crumbs" aria-label="Morzsamenü"><a href="/appok/">Appok</a> <span aria-hidden="true">›</span> <?= e($app['name']) ?></nav>
      <div class="app-hero-grid">
        <div>
          <span class="eyebrow"><?= e($eyebrow) ?></span>
          <h1><?= e($app['name']) ?></h1>
          <p class="lead"><?= e($lead) ?></p>
          <div class="cta">
            <?php foreach ($buttons as $i => [$href, $label]): ?>
              <a class="btn <?= $i === 0 ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e($href) ?>"><?= $label ?></a>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="app-hero-art"><?php ($app['mockup'])(); ?></div>
      </div>
    </div>
  </section>
<?php
}
