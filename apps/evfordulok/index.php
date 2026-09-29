<?php
// Évfordulók — egyoldalas, telefonra telepíthető app (PWA).
declare(strict_types=1);

require __DIR__ . '/api/lib/core.php';

if (!app_installed()) {
    header('Location: install.php');
    exit;
}
$name = get_options()['app_name'];
$v = fn (string $file) => $file . '?v=' . @filemtime(__DIR__ . '/' . $file);

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
<meta name="theme-color" content="#db2777">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="<?= h(mb_substr($name, 0, 12)) ?>">
<link rel="manifest" href="manifest.php">
<link rel="icon" type="image/png" href="icon.php?s=192">
<link rel="apple-touch-icon" href="icon.php?s=180">
<link rel="stylesheet" href="<?= $v('assets/style.css') ?>">
<style>
  :root { --primary: #db2777; --on-primary: #ffffff; }
  @media (prefers-color-scheme: dark) { :root { --primary: #f472b6; --on-primary: #1a0710; } }
</style>
</head>
<body>
<header class="topbar">
  <div class="topbar-inner">
    <div>
      <div class="brand"><?= h($name) ?></div>
      <div class="tagline" id="who">Fontos dátumok, időben</div>
    </div>
  </div>
</header>
<main id="app" class="container" aria-live="polite"></main>
<script src="<?= $v('assets/app.js') ?>"></script>
</body>
</html>
