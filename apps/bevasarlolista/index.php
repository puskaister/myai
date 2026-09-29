<?php
// Bevásárlólista — egyoldalas, telefonra telepíthető app (PWA), szóbeli bevitellel.
declare(strict_types=1);

require __DIR__ . '/api/lib/core.php';

if (!app_installed()) {
    header('Location: install.php');
    exit;
}
$name = get_options()['app_name'];
$v = fn (string $file) => $file . '?v=' . @filemtime(__DIR__ . '/' . $file);
$cats = array_map(fn ($c) => ['key' => $c['key'], 'label' => $c['label'], 'icon' => $c['icon']], dictionary());
$words = array_merge(...array_map(fn ($c) => $c['words'], dictionary()));

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex">
<title><?= h($name) ?></title>
<meta name="theme-color" content="#16a34a">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="<?= h(mb_substr($name, 0, 12)) ?>">
<link rel="manifest" href="manifest.php">
<link rel="icon" type="image/png" href="icon.php?s=192">
<link rel="apple-touch-icon" href="icon.php?s=180">
<link rel="stylesheet" href="<?= $v('assets/style.css') ?>">
<style>
  :root { --primary: #16a34a; --on-primary: #ffffff; }
  @media (prefers-color-scheme: dark) { :root { --primary: #4ade80; --on-primary: #06260f; } }
</style>
</head>
<body>
<header class="topbar">
  <div class="topbar-inner">
    <div>
      <div class="brand"><?= h($name) ?></div>
      <div class="tagline" id="who">Mondd be, és felírjuk</div>
    </div>
    <span class="spacer"></span>
    <button type="button" class="head-btn" id="menu-btn" hidden aria-label="Menü" style="background:rgba(255,255,255,.18);border:0;color:inherit;border-radius:10px;padding:8px 12px;font:inherit;font-weight:700;cursor:pointer">☰</button>
  </div>
</header>
<main id="app" class="container" aria-live="polite"></main>
<script>window.BOOT = <?= json_encode(['categories' => $cats, 'words' => $words], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="<?= $v('assets/parse.js') ?>"></script>
<script src="<?= $v('assets/app.js') ?>"></script>
</body>
</html>
