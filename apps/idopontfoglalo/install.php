<?php
// Egyszeri telepítő: táblák létrehozása, kiinduló csomag, első admin.
// Védelem: csak az tudja lefuttatni, aki ismeri az adatbázis jelszavát
// (ez a config.php-ban van), és csak amíg a rendszer nincs telepítve.
declare(strict_types=1);

require __DIR__ . '/api/lib/core.php';
require __DIR__ . '/api/lib/schema.php';
require __DIR__ . '/api/lib/presets.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');

$error = '';
$done = false;
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

const APP_TABLES = ['settings', 'admins', 'services', 'bookings', 'rate_limits'];

function table_exists(string $name): bool {
    $row = q_one('SELECT COUNT(*) AS n FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$name]);
    return (int) ($row['n'] ?? 0) > 0;
}

// Egy korábbi, előtag nélküli telepítés, amit át lehet venni ide (táblák
// átnevezésével), hogy a közös adatbázisban minden cég előtaggal szerepeljen.
function legacy_install_exists(): bool {
    if (table_prefix() === '' || table_exists(table_prefix() . 'settings') || !table_exists('settings')) return false;
    $row = q_one("SELECT v FROM settings WHERE k = 'installed'");
    return ($row['v'] ?? '') === 'true';
}

$legacy = $state === 'form' && legacy_install_exists();

if ($state === 'form' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'adopt') {
    if (!$legacy) {
        $error = 'Nincs átvehető előtag nélküli telepítés.';
    } elseif (!hash_equals((string) app_config()['db']['pass'], (string) ($_POST['dbpass'] ?? ''))) {
        $error = 'Az adatbázis jelszava nem egyezik.';
    } else {
        $renames = [];
        foreach (APP_TABLES as $t) {
            if (table_exists($t)) $renames[] = "`$t` TO `" . table_prefix() . $t . '`';
        }
        if (db()->query('RENAME TABLE ' . implode(', ', $renames))) {
            $state = 'done';
        } else {
            $error = 'Az átnevezés nem sikerült: ' . db()->error;
        }
    }
} elseif ($state === 'form' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $in = $_POST;
    $business = trim((string) ($in['business'] ?? ''));
    $preset = (string) ($in['preset'] ?? 'altalanos');
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
            apply_preset($preset, $business, $email);
            q_exec('INSERT INTO {admins} (name, email, password_hash) VALUES (?, ?, ?)', [$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
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
<title>Telepítés – Időpontfoglaló</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="plain">
<main class="narrow">
  <div class="card">
    <h1>Időpontfoglaló telepítése</h1>
    <?php if ($state !== 'noconfig' && table_prefix() !== ''): ?>
      <p class="muted">Táblanév-előtag: <code><?= h(table_prefix()) ?></code></p>
    <?php endif; ?>
    <?php if ($state === 'noconfig'): ?>
      <p>Hiányzik az <code>api/config.php</code>. Másold át az <code>api/config.example.php</code> fájlt ezen a néven, és írd bele az adatbázis adatait. GitHub-os deploynál ezt a workflow a secretekből automatikusan elkészíti.</p>
    <?php elseif ($state === 'nodb'): ?>
      <p class="alert"><?= h($error) ?></p>
      <?php // Csak telepítés előtt látszik; jelszót nem tartalmaz (pl. "Access denied for user …", "Unknown database …"). ?>
      <?php if (!empty($GLOBALS['db_connect_error'])): ?>
        <p>MySQL üzenet: <code><?= h($GLOBALS['db_connect_error']) ?></code></p>
      <?php endif; ?>
      <p>Adatbázis: <code><?= h((string) app_config()['db']['name']) ?></code>, felhasználó: <code><?= h((string) app_config()['db']['user']) ?></code>, szerver: <code><?= h((string) app_config()['db']['host']) ?></code></p>
      <p>Ellenőrizd ezeket a GitHub secretekben (<code>DB_NAME</code>, <code>DB_USER</code>, <code>DB_PASS</code>, <code>DB_HOST</code>), majd futtasd újra a deployt.</p>
    <?php elseif ($state === 'installed'): ?>
      <p>A rendszer már telepítve van.</p>
      <p><a class="btn" href="admin/">Admin felület</a> <a class="btn ghost" href="./">Foglalási oldal</a></p>
    <?php elseif ($state === 'done'): ?>
      <p class="ok">Kész! A rendszer telepítve.</p>
      <p>Lépj be az admin felületre, és szabd testre: logó, színek, szolgáltatások, nyitvatartás.</p>
      <p><a class="btn" href="admin/">Tovább az admin felületre</a></p>
    <?php else: ?>
      <?php if ($error): ?><p class="alert"><?= h($error) ?></p><?php endif; ?>
      <?php if ($legacy): ?>
        <form method="post" class="form note" style="margin-bottom:20px">
          <input type="hidden" name="action" value="adopt">
          <strong>Van egy korábbi, előtag nélküli telepítés ebben az adatbázisban.</strong>
          <span>Átveheted ide: a táblái <code><?= h(table_prefix()) ?>…</code> nevet kapnak, az adatok (foglalások, beállítások, admin fiók) megmaradnak.</span>
          <label>Adatbázis jelszava
            <input name="dbpass" type="password" required autocomplete="off">
          </label>
          <button class="btn" type="submit">Korábbi telepítés átvétele</button>
        </form>
        <p class="muted">…vagy telepíts egy újat alább:</p>
      <?php endif; ?>
      <form method="post" class="form">
        <label>Cég / vállalkozás neve
          <input name="business" required value="<?= h($_POST['business'] ?? '') ?>" placeholder="pl. Kovács Gumiszerviz">
        </label>
        <label>Kiinduló csomag
          <select name="preset">
            <?php foreach (presets() as $key => $p): ?>
              <option value="<?= h($key) ?>" <?= ($_POST['preset'] ?? '') === $key ? 'selected' : '' ?>><?= h($p['label']) ?></option>
            <?php endforeach; ?>
          </select>
          <small>Szolgáltatások, nyitvatartás és extra mezők kezdőértékei — később minden átírható.</small>
        </label>
        <hr>
        <label>Admin neve
          <input name="name" required value="<?= h($_POST['name'] ?? '') ?>">
        </label>
        <label>Admin email (ide jönnek az értesítések is)
          <input name="email" type="email" required value="<?= h($_POST['email'] ?? '') ?>">
        </label>
        <label>Admin jelszó (min. 8 karakter)
          <input name="password" type="password" required minlength="8" autocomplete="new-password">
        </label>
        <label>Jelszó még egyszer
          <input name="password2" type="password" required minlength="8" autocomplete="new-password">
        </label>
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
