<?php
// Admin felület (egyoldalas app, az adatokat az api/?r=admin/* végpontokról tölti).
declare(strict_types=1);

require dirname(__DIR__) . '/api/lib/core.php';

if (!app_installed()) {
    header('Location: ../install.php');
    exit;
}

$s = get_settings();
$primary = $s['theme']['primary'];

$onPrimary = on_primary($primary);
$v = fn (string $file) => '../' . $file . '?v=' . @filemtime(dirname(__DIR__) . '/' . $file);
$iconV = substr(md5($primary), 0, 8);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex">
<title>Admin – <?= h($s['business']['name']) ?> chatbot</title>
<meta name="theme-color" content="<?= h($primary) ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<link rel="icon" type="image/png" href="../icon.php?s=192&v=<?= $iconV ?>">
<link rel="apple-touch-icon" href="../icon.php?s=180&v=<?= $iconV ?>">
<link rel="stylesheet" href="<?= $v('assets/style.css') ?>">
<style>:root { --primary: <?= h($primary) ?>; --on-primary: <?= $onPrimary ?>; }</style>
</head>
<body>
<header class="topbar wide">
  <div class="topbar-inner">
    <div>
      <div class="brand"><?= h($s['business']['name']) ?></div>
      <div class="tagline">Chatbot – admin felület</div>
    </div>
    <span class="spacer"></span>
    <a class="btn small ghost" style="color:var(--on-primary);border-color:currentColor" href="../" target="_blank" rel="noopener">Chat megnyitása ↗</a>
  </div>
</header>
<main id="admin" class="container wide"></main>
<script src="<?= $v('assets/admin.js') ?>"></script>
</body>
</html>
