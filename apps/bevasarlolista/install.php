<?php
// Egyszeri telepítő: táblák + első (admin) felhasználó. Csak az adatbázis jelszavával.
declare(strict_types=1);

require __DIR__ . '/api/lib/core.php';
require __DIR__ . '/api/lib/schema.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');

$error = '';
$state = 'form';
if (!app_config()) {
    $state = 'noconfig';
} elseif (app_installed()) {
    $state = 'installed';
} else {
    try { db(); } catch (AppError $e) { $state = 'nodb'; $error = $e->getMessage(); }
}

if ($state === 'form' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $in = $_POST;
    $name = trim((string) ($in['name'] ?? ''));
    $email = trim((string) ($in['email'] ?? ''));
    $password = (string) ($in['password'] ?? '');
    if (!hash_equals((string) app_config()['db']['pass'], (string) ($in['dbpass'] ?? ''))) $error = 'Az adatbázis jelszava nem egyezik.';
    elseif ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Add meg a nevet és egy érvényes email címet.';
    elseif (strlen($password) < 8) $error = 'A jelszó legalább 8 karakter legyen.';
    elseif ($password !== (string) ($in['password2'] ?? '')) $error = 'A két jelszó nem egyezik.';
    else {
        try {
            foreach (schema_statements() as $sql) {
                if (!db()->query(sql_tables($sql))) throw new AppError('Tábla létrehozási hiba: ' . db()->error, 500);
            }
            if (q_one('SELECT id FROM {users} LIMIT 1')) throw new AppError('A telepítés már megtörtént.');
            save_options(array_merge(default_options(), ['allow_register' => !empty($in['allow_register'])]));
            q_exec('INSERT INTO {users} (name, email, password_hash, is_admin) VALUES (?, ?, ?, 1)',
                [$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
            $uid = (int) db()->insert_id;
            q_exec('INSERT INTO {lists} (name, owner_id, invite_token) VALUES (?, ?, ?)', ['Bevásárlólista', $uid, bin2hex(random_bytes(16))]);
            q_exec('INSERT INTO {list_members} (list_id, user_id, role) VALUES (?, ?, ?)', [(int) db()->insert_id, $uid, 'owner']);
            q_exec("INSERT INTO {settings} (k, v) VALUES ('installed', 'true') ON DUPLICATE KEY UPDATE v = 'true'");
            $state = 'done';
        } catch (AppError $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Telepítés – Bevásárlólista</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="plain">
<main class="narrow">
  <div class="card">
    <h1>Bevásárlólista telepítése</h1>
    <?php if ($state !== 'noconfig' && table_prefix() !== ''): ?><p class="muted">Táblanév-előtag: <code><?= h(table_prefix()) ?></code></p><?php endif; ?>
    <?php if ($state === 'noconfig'): ?>
      <p>Hiányzik az <code>api/config.php</code>. Másold az <code>api/config.example.php</code>-t ezen a néven, és töltsd ki.</p>
    <?php elseif ($state === 'nodb'): ?>
      <p class="alert"><?= h($error) ?></p>
      <?php if (!empty($GLOBALS['db_connect_error'])): ?><p>MySQL üzenet: <code><?= h($GLOBALS['db_connect_error']) ?></code></p><?php endif; ?>
    <?php elseif ($state === 'installed'): ?>
      <p>A rendszer már telepítve van.</p>
      <p><a class="btn" href="./">Megnyitás</a></p>
    <?php elseif ($state === 'done'): ?>
      <p class="ok">Kész! Lépj be, és írd fel (vagy mondd be) az első tételeket.</p>
      <p><a class="btn" href="./">Megnyitás</a></p>
    <?php else: ?>
      <?php if ($error): ?><p class="alert"><?= h($error) ?></p><?php endif; ?>
      <form method="post" class="form">
        <label>A neved <input name="name" required value="<?= h($_POST['name'] ?? '') ?>"></label>
        <label>Email <input name="email" type="email" required value="<?= h($_POST['email'] ?? '') ?>"></label>
        <label>Jelszó (min. 8 karakter) <input name="password" type="password" required minlength="8" autocomplete="new-password"></label>
        <label>Jelszó még egyszer <input name="password2" type="password" required minlength="8" autocomplete="new-password"></label>
        <label class="check"><input type="checkbox" name="allow_register" <?= ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !empty($_POST['allow_register']) ? 'checked' : '' ?>> Bárki regisztrálhat saját fiókot — pl. hogy a család tagjai csatlakozhassanak (később is átállítható)</label>
        <hr>
        <label>Adatbázis jelszava (ellenőrzéshez)
          <input name="dbpass" type="password" required autocomplete="off">
          <small>Csak az tudja telepíteni, aki ismeri a tárhely adatbázis-jelszavát.</small>
        </label>
        <button class="btn" type="submit">Telepítés</button>
      </form>
    <?php endif; ?>
  </div>
</main>
</body>
</html>
