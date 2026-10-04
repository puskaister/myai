<?php
// Ügyintézési Segéd – admin felület (megrendelések, előfizetések, tudásbázisok).
declare(strict_types=1);

require dirname(__DIR__) . '/api/lib/core.php';

if (!app_installed()) {
    header('Location: ../install.php');
    exit;
}
$v = fn (string $file) => '../' . $file . '?v=' . @filemtime(dirname(__DIR__) . '/' . $file);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex">
<title>Ügyintézési Segéd – admin</title>
<meta name="theme-color" content="#17695f">
<link rel="stylesheet" href="<?= $v('assets/style.css') ?>">
<style>
  :root { --primary: #17695f; --on-primary: #ffffff; }
  @media (prefers-color-scheme: dark) { :root { --primary: #4fb3a5; --on-primary: #06201d; } }
  .kb { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .82rem; min-height: 220px; }
  .snippet { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .82rem; background: var(--soft); border: 1px solid var(--border); border-radius: 10px; padding: 10px 12px; word-break: break-all; user-select: all; }
  .badge.active { background: color-mix(in srgb, var(--success) 15%, var(--card)); color: var(--success); }
  .badge.expired, .badge.paused { background: color-mix(in srgb, var(--danger) 13%, var(--card)); color: var(--danger); }
</style>
</head>
<body>
<header class="topbar wide">
  <div class="topbar-inner">
    <div>
      <div class="brand">Ügyintézési Segéd</div>
      <div class="tagline">Megrendelések és előfizetések</div>
    </div>
    <span class="spacer"></span>
    <a class="btn small ghost" style="color:var(--on-primary);border-color:currentColor" href="../" target="_blank" rel="noopener">Demó ↗</a>
  </div>
</header>
<main id="admin" class="container wide"></main>
<script src="<?= $v('assets/admin.js') ?>"></script>
</body>
</html>
