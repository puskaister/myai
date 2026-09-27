<?php
// Látogatói chat. Önálló oldalként és telepíthető appként is fut; a
// ?embed=1 változatot a widget tölti be iframe-ben más weboldalakon.
declare(strict_types=1);

require __DIR__ . '/api/lib/core.php';

if (!app_installed()) {
    header('Location: install.php');
    exit;
}

$s = get_settings();
$public = public_settings();
$embed = !empty($_GET['embed']);
$primary = $s['theme']['primary'];
$v = fn (string $file) => $file . '?v=' . @filemtime(__DIR__ . '/' . $file);
$iconV = substr(md5($primary), 0, 8);
$botName = $s['bot']['name'];
$initial = mb_strtoupper(mb_substr($botName, 0, 1));

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
<title><?= h($botName) ?> – <?= h($s['business']['name']) ?></title>
<meta name="description" content="Chat – <?= h($s['business']['name']) ?>">
<meta name="theme-color" content="<?= h($primary) ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="<?= h(mb_substr($s['business']['name'], 0, 14)) ?>">
<?php if (!$embed): ?>
<link rel="manifest" href="manifest.php">
<?php endif; ?>
<link rel="icon" type="image/png" href="icon.php?s=192&v=<?= $iconV ?>">
<link rel="apple-touch-icon" href="icon.php?s=180&v=<?= $iconV ?>">
<link rel="stylesheet" href="<?= $v('assets/style.css') ?>">
<style>:root { --primary: <?= h($primary) ?>; --on-primary: <?= on_primary($primary) ?>; }</style>
</head>
<body class="chat<?= $embed ? ' embed' : '' ?>">
<header class="topbar">
  <div class="topbar-inner chat-head" style="max-width:760px">
    <span class="avatar" aria-hidden="true"><?= h($initial) ?></span>
    <div>
      <div class="brand"><?= h($botName) ?></div>
      <div class="tagline"><?= h($s['business']['name']) ?></div>
    </div>
    <span class="spacer"></span>
    <button type="button" class="head-btn" id="new-chat" hidden>Új beszélgetés</button>
    <?php if ($embed): ?><button type="button" class="head-btn" id="close-chat" aria-label="Chat bezárása">✕</button><?php endif; ?>
  </div>
  <div class="progress" id="progress" hidden style="max-width:760px;margin-left:auto;margin-right:auto"><i></i></div>
</header>

<div class="chat-shell">
  <div class="msgs" id="msgs" role="log" aria-live="polite" aria-label="Beszélgetés"></div>
  <form class="composer" id="composer">
    <textarea id="text" rows="1" placeholder="Írj üzenetet…" aria-label="Üzenet" maxlength="1500" required></textarea>
    <button class="btn" type="submit" aria-label="Küldés"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3.4 20.4l17.4-7.5c.8-.4.8-1.5 0-1.8L3.4 3.6c-.7-.3-1.4.3-1.2 1l1.9 6.6 8.9 .8-8.9.8-1.9 6.6c-.2.7.5 1.3 1.2 1z"/></svg></button>
  </form>
  <div class="powered">A válaszokat mesterséges intelligencia adja, tévedhet. · <a href="https://my-ai.hu" target="_blank" rel="noopener">my-ai.hu</a></div>
</div>

<script>window.BOOT = <?= json_encode(['settings' => $public, 'embed' => $embed, 'source' => (string) ($_GET['source'] ?? '')], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="<?= $v('assets/chat.js') ?>"></script>
</body>
</html>
