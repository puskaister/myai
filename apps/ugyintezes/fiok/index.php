<?php
// Ügyintézési Segéd – ügyfél-fiók: az előfizető itt tanítja a saját chatbotját
// (csak a sajátját látja). Belépés: a megrendeléskor megadott email cím + jelszó;
// a jelszót az adminból küldött vagy az „Elfelejtett jelszó” linken állítja be.
declare(strict_types=1);

require dirname(__DIR__) . '/api/lib/core.php';

if (!app_installed()) {
    http_response_code(503);
    exit('A szolgáltatás még nincs beállítva.');
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
<title>Chatbot tanítása – Ügyintézési Segéd</title>
<meta name="theme-color" content="#4f46e5">
<link rel="stylesheet" href="<?= $v('assets/style.css') ?>">
<style>
  :root { --primary: #4f46e5; --on-primary: #ffffff; }
  @media (prefers-color-scheme: dark) { :root { --primary: #8b87ff; --on-primary: #0b0e17; } }
</style>
</head>
<body>
<header class="topbar wide">
  <div class="topbar-inner">
    <div>
      <div class="brand">Ügyintézési Segéd</div>
      <div class="tagline" id="who">A chatbotod tanítása</div>
    </div>
    <span class="spacer"></span>
    <span id="top-actions"></span>
  </div>
</header>
<main id="portal" class="container wide"></main>
<script src="<?= $v('assets/common.js') ?>"></script>
<script src="<?= $v('assets/teach.js') ?>"></script>
<script src="<?= $v('assets/portal.js') ?>"></script>
</body>
</html>
