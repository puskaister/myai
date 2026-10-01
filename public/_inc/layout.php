<?php
// Közös elrendezés a my-ai.hu oldalaihoz: <head>, fejléc + menü, kapcsolat-sáv,
// lábléc. Használat:
//   require $_SERVER['DOCUMENT_ROOT'] . '/_inc/layout.php';   (vagy relatív úttal)
//   page_start('Cím', 'Leírás', 'appok');  … tartalom …  page_end();
declare(strict_types=1);

require_once __DIR__ . '/apps.php';
require_once __DIR__ . '/mockups.php';

// http → https (a böngészőben megnyitott oldalakon; helyi gépen / IP-címen nem)
(function (): void {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if (PHP_SAPI === 'cli' || $https || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET' || $host === ''
        || preg_match('/^(localhost|\d+\.\d+\.\d+\.\d+|\[[0-9a-f:]+\])(:\d+)?$/i', $host)) return;
    header('Location: https://' . $host . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
    exit;
})();


const SITE = [
    'name'    => 'my-ai.hu',
    'email'   => 'info@my-ai.hu',   // ha üres, a kapcsolat-sáv rejtve marad
    'phone'   => '+36 30 584 5937',
    'demoUrl' => '/foglalas/',
];

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function has_contact(): bool {
    return SITE['email'] !== '' || SITE['phone'] !== '';
}

// $active: a menüpont kulcsa, ami aktívként jelenik meg (fejlesztes, appok, hogyan, kapcsolat, adatkezeles)
function page_start(string $title, string $description, string $active = '', array $og = []): void {
    header('Content-Type: text/html; charset=utf-8');
    $css = '/assets/site.css?v=' . @filemtime(dirname(__DIR__) . '/assets/site.css');
    $nav = [
        'fejlesztes' => ['/#fejlesztes', 'Egyedi fejlesztés'],
        'appok'      => ['/appok/', 'Appok'],
        'hogyan'     => ['/#hogyan', 'Hogyan működik'],
        'kapcsolat'  => ['/#kapcsolat', 'Kapcsolat'],
        'adatkezeles'=> ['/adatkezeles/', 'Adatkezelés'],
    ];
    if (!has_contact()) unset($nav['kapcsolat']);
    ?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($description) ?>">
<meta property="og:title" content="<?= e($og['title'] ?? $title) ?>">
<meta property="og:description" content="<?= e($og['description'] ?? $description) ?>">
<meta property="og:type" content="website">
<meta name="theme-color" content="#4f46e5">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='16' fill='%234f46e5'/%3E%3Crect x='20' y='10' width='24' height='44' rx='6' fill='none' stroke='white' stroke-width='4'/%3E%3Ccircle cx='32' cy='46' r='2.5' fill='white'/%3E%3C/svg%3E">
<link rel="stylesheet" href="<?= e($css) ?>">
<script src="/stats/consent.js" data-privacy="/adatkezeles/" defer></script>
<script src="/stats/t.js" defer></script>
</head>
<body>

<header class="nav">
  <div class="wrap">
    <a class="logo" href="/"><span class="logo-mark" aria-hidden="true"></span><?= e(SITE['name']) ?></a>
    <div class="nav-right">
      <nav class="nav-links" id="nav-links" aria-label="Fő navigáció">
        <?php foreach ($nav as $key => [$href, $label]): ?>
          <a href="<?= e($href) ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
        <?php endforeach; ?>
        <a class="btn btn-primary" href="<?= e(SITE['demoUrl']) ?>">Demó</a>
      </nav>
      <button type="button" class="menu-btn" aria-controls="nav-links" aria-expanded="false" aria-label="Menü">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
      </button>
    </div>
  </div>
</header>

<main>
<?php
}

// Kapcsolat-sáv (a főoldal alján és a részletes oldalakon).
function contact_section(string $title = 'Egyedi weboldalt vagy appot szeretnél?', string $text = 'Írj vagy hívj — megbeszéljük, mire van szüksége a vállalkozásodnak, és rövid határidővel elkészítjük.'): void {
    if (!has_contact()) return; ?>
  <section id="kapcsolat">
    <div class="wrap">
      <div class="contact">
        <h2><?= e($title) ?></h2>
        <p><?= e($text) ?></p>
        <div class="cta" style="justify-content:center">
          <?php if (SITE['email'] !== ''): ?><a class="btn" href="mailto:<?= e(SITE['email']) ?>"><?= e(SITE['email']) ?></a><?php endif; ?>
          <?php if (SITE['phone'] !== ''): ?><a class="btn btn-ghost" href="tel:<?= e(preg_replace('/[^0-9+]/', '', SITE['phone'])) ?>"><?= e(SITE['phone']) ?></a><?php endif; ?>
        </div>
      </div>
    </div>
  </section>
<?php
}

function page_end(): void { ?>
</main>

<footer>
  <div class="wrap">
    <span>© <?= date('Y') ?> <?= e(SITE['name']) ?></span>
    <span class="footer-links">
      <a href="/appok/">Appok</a>
      <a href="/adatkezeles/">Adatkezelési tájékoztató</a>
      <button type="button" class="footer-btn" onclick="window.myaiConsent && myaiConsent.open()">Süti-beállítások</button>
    </span>
  </div>
</footer>

<script>
  // Mobil menü: nyitás/zárás, és zárás, ha egy menüpontra kattintanak.
  (function () {
    var btn = document.querySelector('.menu-btn');
    var nav = document.getElementById('nav-links');
    if (!btn || !nav) return;
    function set(open) { nav.classList.toggle('open', open); btn.setAttribute('aria-expanded', String(open)); }
    btn.addEventListener('click', function () { set(!nav.classList.contains('open')); });
    nav.addEventListener('click', function (e) { if (e.target.closest('a')) set(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') set(false); });
  })();
</script>
</body>
</html>
<?php
}
