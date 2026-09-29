<?php
// Irányítópult (egyoldalas app, az adatokat az api/?r=admin/* végpontokról tölti).
declare(strict_types=1);

require __DIR__ . '/api/lib/core.php';

if (!app_installed()) {
    header('Location: install.php');
    exit;
}
$site = get_options()['site_name'];
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
<title>Látogatók – <?= h($site) ?></title>
<meta name="theme-color" content="#4f46e5">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='14' fill='%234f46e5'/%3E%3Cpath d='M16 46V30M28 46V18M40 46V26M52 46V36' stroke='white' stroke-width='6' stroke-linecap='round'/%3E%3C/svg%3E">
<link rel="stylesheet" href="<?= $v('assets/style.css') ?>">
<style>
  :root { --primary: #4f46e5; --on-primary: #ffffff; --chart: #4f46e5; }
  @media (prefers-color-scheme: dark) { :root { --chart: #8b87ff; } }
</style>
</head>
<body>
<header class="topbar wide">
  <div class="topbar-inner">
    <div>
      <div class="brand">Látogatók</div>
      <div class="tagline"><?= h($site) ?></div>
    </div>
  </div>
</header>
<main id="admin" class="container wide"></main>
<script src="<?= $v('assets/dash.js') ?>"></script>
</body>
</html>
