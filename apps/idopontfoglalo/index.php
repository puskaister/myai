<?php
// Ügyféloldali foglalási app. A cég beállításait már a HTML-be ágyazzuk,
// így az első betöltéshez nem kell külön API-hívás.
declare(strict_types=1);

require __DIR__ . '/api/lib/core.php';

if (!app_installed()) {
    header('Location: install.php');
    exit;
}

$s = get_settings();
$public = public_settings();
$services = q_all('SELECT id, name, description, duration_min, price FROM {services} WHERE active = 1 ORDER BY sort, id');
$name = $s['business']['name'];
$primary = $s['theme']['primary'];
$v = fn (string $file) => $file . '?v=' . @filemtime(__DIR__ . '/' . $file);
$iconV = substr(md5($primary . $s['theme']['logo']), 0, 8);

// Olvasható szövegszín a cég színén (fehér vagy sötét).
[$r, $g, $b] = sscanf($primary, '#%02x%02x%02x');
$onPrimary = (0.299 * $r + 0.587 * $g + 0.114 * $b) > 160 ? '#111827' : '#ffffff';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= h($name) ?> – Időpontfoglalás</title>
<meta name="description" content="<?= h($s['business']['tagline'] ?: 'Online időpontfoglalás – ' . $name) ?>">
<meta name="theme-color" content="<?= h($primary) ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="<?= h(mb_substr($name, 0, 14)) ?>">
<link rel="manifest" href="manifest.php">
<link rel="icon" type="image/png" href="icon.php?s=192&v=<?= $iconV ?>">
<link rel="apple-touch-icon" href="icon.php?s=180&v=<?= $iconV ?>">
<link rel="stylesheet" href="<?= $v('assets/style.css') ?>">
<style>:root { --primary: <?= h($primary) ?>; --on-primary: <?= $onPrimary ?>; }</style>
</head>
<body>
<header class="topbar">
  <div class="topbar-inner">
    <?php if ($public['theme']['logo']): ?>
      <img class="logo" src="<?= h($public['theme']['logo']) ?>" alt="<?= h($name) ?>">
    <?php endif; ?>
    <div>
      <div class="brand"><?= h($name) ?></div>
      <?php if ($s['business']['tagline']): ?><div class="tagline"><?= h($s['business']['tagline']) ?></div><?php endif; ?>
    </div>
  </div>
</header>

<main id="app" class="container" aria-live="polite">
  <noscript><p class="card">Az időpontfoglaláshoz engedélyezd a JavaScriptet.</p></noscript>
</main>

<footer class="footer container">
  <?php $biz = $s['business']; ?>
  <?php if ($biz['address']): ?>
    <div><a href="https://www.google.com/maps/search/?api=1&query=<?= rawurlencode($biz['address']) ?>" target="_blank" rel="noopener"><?= h($biz['address']) ?></a></div>
  <?php endif; ?>
  <div>
    <?php if ($biz['phone']): ?><a href="tel:<?= h(preg_replace('/[^0-9+]/', '', $biz['phone'])) ?>"><?= h($biz['phone']) ?></a><?php endif; ?>
    <?php if ($biz['phone'] && $biz['email']): ?> · <?php endif; ?>
    <?php if ($biz['email']): ?><a href="mailto:<?= h($biz['email']) ?>"><?= h($biz['email']) ?></a><?php endif; ?>
  </div>
  <button type="button" id="install-btn" class="link-btn" hidden>Telepítés appként</button>
</footer>

<script>window.BOOT = <?= json_encode(['settings' => $public, 'services' => $services], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="<?= $v('assets/app.js') ?>"></script>
</body>
</html>
