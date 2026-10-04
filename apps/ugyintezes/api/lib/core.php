<?php
// Közös induló kód minden belépési ponthoz (api, telepítő, oldalak, manifest,
// ikon): konfiguráció, lusta DB-kapcsolat, lekérdező segédfüggvények.
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0'); // ne kerüljön PHP hibaszöveg a válaszba éles környezetben
date_default_timezone_set('Europe/Budapest');

define('APP_ROOT', dirname(__DIR__, 2));

require __DIR__ . '/db.php';
require __DIR__ . '/mailer.php';
require __DIR__ . '/settings.php';
require __DIR__ . '/billing.php';

class AppError extends Exception {
    public int $status;
    public function __construct(string $message, int $status = 400) {
        parent::__construct($message);
        $this->status = $status;
    }
}

function app_config(): ?array {
    static $config = false;
    if ($config === false) {
        $path = APP_ROOT . '/api/config.php';
        $config = is_file($path) ? require $path : null;
    }
    return $config;
}

function db(): mysqli {
    static $mysqli = null;
    if ($mysqli !== null) return $mysqli;

    $config = app_config();
    if (!$config) throw new AppError('Hiányzik az api/config.php (lásd api/config.example.php).', 500);

    mysqli_report(MYSQLI_REPORT_OFF);
    $conn = mysqli_init();
    $ok = @$conn->real_connect(
        $config['db']['host'],
        $config['db']['user'],
        $config['db']['pass'],
        $config['db']['name']
    );
    if (!$ok) {
        $GLOBALS['db_connect_error'] = (string) mysqli_connect_error();
        error_log('[ugyintezes] DB kapcsolódási hiba: ' . $GLOBALS['db_connect_error']);
        throw new AppError('Nem sikerült csatlakozni az adatbázishoz.', 500);
    }
    $conn->set_charset($config['db']['charset'] ?? 'utf8mb4');
    // A DATETIME mezők helyi (budapesti) időt tárolnak; a NOW() is ehhez igazodjon.
    $conn->query("SET time_zone = '" . date('P') . "'");
    $mysqli = $conn;
    return $mysqli;
}

// Táblanév-előtag: több cég is osztozhat egy adatbázison (pl. gumipont_bookings,
// fodrasz_bookings). Az SQL-ben a táblák {nev} alakban szerepelnek.
function table_prefix(): string {
    return preg_replace('/[^a-z0-9_]/', '', strtolower((string) (app_config()['prefix'] ?? '')));
}

function sql_tables(string $sql): string {
    return preg_replace_callback('/\{(settings|admins|customers|payments|reminders|rate_limits|password_resets)\}/', fn ($m) => table_prefix() . $m[1], $sql);
}

// Paraméterezett lekérdezés; a típusokat az értékekből állapítja meg.
function q(string $sql, array $params = []): mysqli_stmt {
    $sql = sql_tables($sql);
    $stmt = db()->prepare($sql);
    if (!$stmt) {
        error_log('[ugyintezes] SQL prepare hiba: ' . db()->error . " — $sql");
        throw new AppError('Adatbázis hiba.', 500);
    }
    if ($params) {
        $types = '';
        foreach ($params as $p) {
            $types .= is_int($p) || is_bool($p) ? 'i' : (is_float($p) ? 'd' : 's');
        }
        $values = array_values(array_map(fn ($p) => is_bool($p) ? (int) $p : $p, $params));
        $stmt->bind_param($types, ...$values);
    }
    if (!$stmt->execute()) {
        error_log('[ugyintezes] SQL hiba: ' . $stmt->error . " — $sql");
        throw new AppError('Adatbázis hiba.', 500);
    }
    return $stmt;
}

function q_all(string $sql, array $params = []): array {
    $stmt = q($sql, $params);
    $rows = stmt_fetch_all($stmt);
    $stmt->close();
    return $rows;
}

function q_one(string $sql, array $params = []): ?array {
    return q_all($sql, $params)[0] ?? null;
}

function q_exec(string $sql, array $params = []): int {
    $stmt = q($sql, $params);
    $affected = $stmt->affected_rows;
    $stmt->close();
    return $affected;
}

function app_installed(): bool {
    if (!app_config()) return false;
    try {
        $res = db()->query(sql_tables("SELECT v FROM {settings} WHERE k = 'installed'"));
        return $res && ($row = $res->fetch_row()) && $row[0] === 'true';
    } catch (AppError $e) {
        return false;
    }
}

// Az alkalmazás gyökerének URL-je (pl. https://my-ai.hu/foglalas), bármelyik
// belépési pontból hívva — alkönyvtárban is működik.
function app_base_url(): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $file = str_replace('\\', '/', (string) realpath($_SERVER['SCRIPT_FILENAME'] ?? ''));
    $root = str_replace('\\', '/', (string) realpath(APP_ROOT));
    $rel = ($root !== '' && strpos($file, $root) === 0) ? substr($file, strlen($root)) : '/' . basename($script);
    $basePath = substr($script, 0, max(0, strlen($script) - strlen($rel)));
    return ($https ? 'https' : 'http') . '://' . $host . rtrim($basePath, '/');
}

function h(?string $s): string {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

// http → https: a böngészőben megnyitott oldalakon (index.php, install.php) átirányítunk,
// az API-hívásokon és a háttérvégpontokon (cron, ics, track) nem. Helyi gépen
// (localhost / IP-cím, pl. a CI tesztszerverén) sem.
function is_https_request(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}
(function (): void {
    if (PHP_SAPI === 'cli' || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET' || is_https_request()) return;
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if ($host === '' || preg_match('/^(localhost|\d+\.\d+\.\d+\.\d+|\[[0-9a-f:]+\])(:\d+)?$/i', $host)) return;
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (strpos($script, '/api/') !== false || !in_array(basename($script), ['index.php', 'install.php'], true)) return;
    header('Location: https://' . $host . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
    exit;
})();
