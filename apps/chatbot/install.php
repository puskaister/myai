<?php
// Egyszeri telepítő: táblák, kiinduló csomag, első admin.
// Csak az futtathatja, aki ismeri az adatbázis jelszavát (config.php).
declare(strict_types=1);

require __DIR__ . '/api/lib/core.php';
require __DIR__ . '/api/lib/schema.php';
require __DIR__ . '/api/lib/presets.php';
require __DIR__ . '/api/lib/claude.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');

$error = '';
$state = 'form';

if (!app_config()) {
    $state = 'noconfig';
} elseif (app_installed()) {
    $state = 'installed';
} else {
    try {
        db();
    } catch (AppError $e) {
        $state = 'nodb';
        $error = $e->getMessage();
    }
}

if ($state === 'form' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $in = $_POST;
    $business = trim((string) ($in['business'] ?? ''));
    $name = trim((string) ($in['name'] ?? ''));
    $email = trim((string) ($in['email'] ?? ''));
    $password = (string) ($in['password'] ?? '');

    if (!hash_equals((string) app_config()['db']['pass'], (string) ($in['dbpass'] ?? ''))) {
        $error = 'Az adatbázis jelszava nem egyezik.';
    } elseif ($business === '' || $name === '') {
        $error = 'Add meg a cég és az admin nevét.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Érvénytelen email cím.';
    } elseif (strlen($password) < 8) {
        $error = 'A jelszó legalább 8 karakter legyen.';
    } elseif ($password !== (string) ($in['password2'] ?? '')) {
        $error = 'A két jelszó nem egyezik.';
    } else {
        try {
            foreach (schema_statements() as $sql) {
                if (!db()->query(sql_tables($sql))) throw new AppError('Tábla létrehozási hiba: ' . db()->error, 500);
            }
            if (q_one('SELECT id FROM {admins} LIMIT 1')) throw new AppError('Az adatbázisban már van admin — a telepítés már megtörtént.');
            apply_preset((string) ($in['preset'] ?? 'erdeklodo'), $business, $email);
            q_exec('INSERT INTO {admins} (name, email, password_hash) VALUES (?, ?, ?)', [$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
            q_exec("INSERT INTO {settings} (k, v) VALUES ('installed', 'true') ON DUPLICATE KEY UPDATE v = 'true'");
            $state = 'done';
        } catch (AppError $e) {
            $error = $e->getMessage();
        }
    }
}
$hasKey = !empty(app_config()['anthropic']['api_key']);
// Az SDK kicsomagolása (vendor.zip → vendor/) már telepítéskor, hogy az első chat üzenet gyors legyen.
$sdkError = '';
try {
    ensure_vendor();
} catch (AppError $e) {
    $sdkError = $e->getMessage();
}
$hasSdk = is_file(__DIR__ . '/vendor/autoload.php');
?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Telepítés – Chatbot</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="plain">
<main class="narrow">
  <div class="card">
    <h1>Chatbot telepítése</h1>
    <?php if ($state !== 'noconfig' && table_prefix() !== ''): ?>
      <p class="muted">Táblanév-előtag: <code><?= h(table_prefix()) ?></code> · PHP <?= h(PHP_VERSION) ?></p>
    <?php endif; ?>
    <?php if (PHP_VERSION_ID < 80100): ?>
      <p class="alert">A szerveren PHP <?= h(PHP_VERSION) ?> fut, a chatbothoz legalább PHP 8.1 kell. A DiMa panelben (Webhelyek → PHP verzió) állítsd át.</p>
    <?php endif; ?>
    <?php if ($state !== 'noconfig' && (!$hasKey || !$hasSdk)): ?>
      <p class="alert">
        <?php if (!$hasSdk): ?>Hiányzik az Anthropic SDK (<code>vendor/</code>) — a deploy telepíti.<?= $sdkError !== '' ? ' ' . h($sdkError) : '' ?><br><?php endif; ?>
        <?php if (!$hasKey): ?>Nincs Anthropic API kulcs a konfigurációban (GitHub secret: <code>ANTHROPIC_API_KEY</code>). Telepíteni lehet, de a bot addig nem válaszol.<?php endif; ?>
      </p>
    <?php endif; ?>

    <?php if ($state === 'noconfig'): ?>
      <p>Hiányzik az <code>api/config.php</code>. GitHub-os deploynál a workflow a secretekből automatikusan elkészíti.</p>
    <?php elseif ($state === 'nodb'): ?>
      <p class="alert"><?= h($error) ?></p>
      <?php if (!empty($GLOBALS['db_connect_error'])): ?><p>MySQL üzenet: <code><?= h($GLOBALS['db_connect_error']) ?></code></p><?php endif; ?>
    <?php elseif ($state === 'installed'): ?>
      <p>A rendszer már telepítve van.</p>
      <p><a class="btn" href="admin/">Admin felület</a> <a class="btn ghost" href="./">Chat megnyitása</a></p>
    <?php elseif ($state === 'done'): ?>
      <p class="ok">Kész! A chatbot telepítve.</p>
      <p>Az admin felületen tanítsd be: tudásanyag, kötelező kérdések, hangnem.</p>
      <p><a class="btn" href="admin/">Tovább az admin felületre</a></p>
    <?php else: ?>
      <?php if ($error): ?><p class="alert"><?= h($error) ?></p><?php endif; ?>
      <form method="post" class="form">
        <label>Cég / vállalkozás neve
          <input name="business" required value="<?= h($_POST['business'] ?? '') ?>">
        </label>
        <label>Kiinduló csomag
          <select name="preset">
            <?php foreach (presets() as $key => $p): ?>
              <option value="<?= h($key) ?>" <?= ($_POST['preset'] ?? '') === $key ? 'selected' : '' ?>><?= h($p['label']) ?></option>
            <?php endforeach; ?>
          </select>
          <small>Köszöntés, tudásanyag-váz és kötelező kérdések — később minden átírható.</small>
        </label>
        <hr>
        <label>Admin neve <input name="name" required value="<?= h($_POST['name'] ?? '') ?>"></label>
        <label>Admin email (ide jönnek az értesítések is) <input name="email" type="email" required value="<?= h($_POST['email'] ?? '') ?>"></label>
        <label>Admin jelszó (min. 8 karakter) <input name="password" type="password" required minlength="8" autocomplete="new-password"></label>
        <label>Jelszó még egyszer <input name="password2" type="password" required minlength="8" autocomplete="new-password"></label>
        <hr>
        <label>Adatbázis jelszava (ellenőrzéshez)
          <input name="dbpass" type="password" required autocomplete="off">
          <small>Csak az tudja telepíteni a rendszert, aki ismeri a tárhely adatbázis-jelszavát.</small>
        </label>
        <button class="btn" type="submit">Telepítés</button>
      </form>
    <?php endif; ?>
  </div>
</main>
</body>
</html>
