<?php
// Kiberbiztonsági gyorsellenőrzés – szerveroldali közös kód: konfiguráció, adatbázis
// (kiber_ előtagú táblák, első használatkor jönnek létre), levélküldés, pontszám és
// teendőlista (ugyanaz a logika, mint a böngészőben: kiberbiztonsag/assets/gyorsellenorzes.js).
//
// A konfigurációt (DB, SMTP) a deploy írja: _inc/kiber-config.php (deploy/build.php,
// a GitHub secretekből). A _inc mappa kívülről nem érhető el.
declare(strict_types=1);

require_once __DIR__ . '/kiber-questions.php';
require_once __DIR__ . '/domaincheck.php';

const KIBER_PRIO = ['now' => 'Azonnal', 'soon' => '30 napon belül', 'later' => 'Később'];
const KIBER_VALUE = ['igen' => 1.0, 'reszben' => 0.5, 'nem' => 0.0, 'nemtudom' => 0.0];

class KiberError extends Exception {
    public int $status;
    public function __construct(string $message, int $status = 400) { parent::__construct($message); $this->status = $status; }
}

function kiber_config(): array {
    static $c = null;
    if ($c === null) {
        $path = __DIR__ . '/kiber-config.php';
        $c = is_file($path) ? (array) (require $path) : [];
    }
    return $c;
}

// Titok az aláírásokhoz (admin-süti, leiratkozó link): a configból, annak hiányában a DB-jelszóból.
function kiber_secret(): string {
    $c = kiber_config();
    $s = (string) ($c['secret'] ?? '');
    if ($s === '') $s = hash('sha256', 'kiber|' . ($c['db']['pass'] ?? ''));
    return $s;
}

function kiber_db(): mysqli {
    static $db = null;
    if ($db) return $db;
    $c = kiber_config()['db'] ?? null;
    if (!$c || ($c['name'] ?? '') === '') throw new KiberError('A szolgáltatás átmenetileg nem érhető el (hiányzó beállítás).', 503);
    mysqli_report(MYSQLI_REPORT_OFF);
    $conn = mysqli_init();
    if (!@$conn->real_connect($c['host'], $c['user'], $c['pass'], $c['name'], (int) ($c['port'] ?? 3306))) {
        error_log('[kiberbiztonsag] DB kapcsolódási hiba: ' . mysqli_connect_error());
        throw new KiberError('A szolgáltatás átmenetileg nem érhető el. Próbáld újra később.', 503);
    }
    $conn->set_charset('utf8mb4');
    $conn->query("SET time_zone = '" . date('P') . "'");
    $db = $conn;
    kiber_schema();
    return $db;
}

function kiber_t(string $name): string {
    return preg_replace('/[^a-z0-9_]/', '', (string) (kiber_config()['prefix'] ?? 'kiber_')) . $name;
}

function kiber_q(string $sql, array $params = []): mysqli_stmt {
    $sql = preg_replace_callback('/\{(leads|logins)\}/', fn ($m) => kiber_t($m[1]), $sql);
    $stmt = kiber_db()->prepare($sql);
    if (!$stmt) { error_log('[kiberbiztonsag] SQL: ' . kiber_db()->error); throw new KiberError('Adatbázis hiba.', 500); }
    if ($params) {
        $types = '';
        foreach ($params as $p) $types .= is_int($p) ? 'i' : (is_float($p) ? 'd' : 's');
        $stmt->bind_param($types, ...array_values($params));
    }
    if (!$stmt->execute()) { error_log('[kiberbiztonsag] SQL: ' . $stmt->error); throw new KiberError('Adatbázis hiba.', 500); }
    return $stmt;
}

function kiber_all(string $sql, array $params = []): array {
    $st = kiber_q($sql, $params);
    // get_result() nélkül (mysqlnd nem mindenhol van): bind_result a mezőnevek alapján
    $meta = $st->result_metadata();
    $rows = [];
    if ($meta) {
        $row = []; $refs = [];
        foreach ($meta->fetch_fields() as $f) { $row[$f->name] = null; $refs[] = &$row[$f->name]; }
        $st->bind_result(...$refs);
        while ($st->fetch()) $rows[] = array_map(fn ($v) => $v, $row);
    }
    $st->close();
    return $rows;
}

function kiber_schema(): void {
    $db = kiber_db();
    $leads = kiber_t('leads');
    $logins = kiber_t('logins');
    $db->query("CREATE TABLE IF NOT EXISTS `$leads` (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        created_at DATETIME NOT NULL,
        email VARCHAR(190) NOT NULL,
        company VARCHAR(190) NOT NULL DEFAULT '',
        domain VARCHAR(253) NOT NULL DEFAULT '',
        score TINYINT UNSIGNED NULL,
        q_score TINYINT UNSIGNED NULL,
        d_score TINYINT UNSIGNED NULL,
        answered TINYINT UNSIGNED NOT NULL DEFAULT 0,
        todos SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        todos_now SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        answers TEXT NOT NULL,
        newsletter TINYINT(1) NOT NULL DEFAULT 0,
        confirmed_at DATETIME NULL,
        unsubscribed_at DATETIME NULL,
        token CHAR(32) NOT NULL,
        ip_hash CHAR(16) NOT NULL DEFAULT '',
        mail_ok TINYINT(1) NOT NULL DEFAULT 0,
        KEY email (email), KEY created (created_at), UNIQUE KEY token (token)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->query("CREATE TABLE IF NOT EXISTS `$logins` (
        token_hash CHAR(64) PRIMARY KEY,
        expires_at DATETIME NOT NULL,
        used TINYINT(1) NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// IP-nkénti korlát fájlokkal a temp mappában (a domain-vizsgálattal azonos módszer).
function kiber_rate_limit(string $bucket, int $max, int $window): bool {
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $file = sys_get_temp_dir() . '/myai-kiber-' . $bucket . '-' . substr(hash('sha256', "$bucket|$ip"), 0, 24);
    $now = time();
    $hits = array_filter((array) json_decode((string) @file_get_contents($file), true), fn ($t) => is_int($t) && $t > $now - $window);
    if (count($hits) >= $max) return false;
    $hits[] = $now;
    @file_put_contents($file, json_encode(array_values($hits)), LOCK_EX);
    return true;
}

function kiber_ip_hash(): string {
    return substr(hash_hmac('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? ''), kiber_secret()), 0, 16);
}

function kiber_site_url(): string {
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'my-ai.hu');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    if (preg_match('/^(localhost|\d+\.\d+\.\d+\.\d+)(:\d+)?$/', $host)) return "http://$host"; // helyi teszt
    return ($https || $host === 'my-ai.hu' ? 'https' : 'http') . '://' . $host;
}

// --- pontszám és teendők (a gyorsellenorzes.js-sel azonos szabályok)

function kiber_prio(int $w): string { return $w >= 8 ? 'now' : ($w >= 5 ? 'soon' : 'later'); }

function kiber_level(?int $score): string {
    if ($score === null) return '–';
    if ($score >= 75) return 'Jó alapok, kevés teendővel';
    if ($score >= 50) return 'Közepes védelem, vannak fontos hiányok';
    return 'Sérülékeny, érdemes most lépni';
}

// Csak az ismert kérdésekre adott, érvényes válaszok maradnak meg.
function kiber_clean_answers($answers): array {
    $out = [];
    if (!is_array($answers)) return $out;
    foreach (KIBER_QUESTIONS as $q) {
        $a = $answers[$q['id']] ?? null;
        if (is_string($a) && isset(KIBER_VALUE[$a])) $out[$q['id']] = $a;
    }
    return $out;
}

function kiber_evaluate(array $answers, ?array $domain): array {
    $got = 0.0; $max = 0;
    $todos = [];
    foreach (KIBER_QUESTIONS as $q) {
        $a = $answers[$q['id']] ?? null;
        if ($a === null) continue;
        $max += $q['w']; $got += $q['w'] * KIBER_VALUE[$a];
        if ($a === 'igen') continue;
        $steps = $q['steps'];
        if ($a === 'nemtudom') array_unshift($steps, 'Először derítsd ki: kérdezd meg az informatikusodat vagy a szolgáltatót, hogy ez be van-e állítva. Ha igen, ez a teendő kipipálható.');
        if ($a === 'reszben') array_unshift($steps, 'Részben már megvan: nézd át, hol hiányzik még, és ott pótold.');
        $todos[] = ['prio' => kiber_prio($q['w']), 'w' => $q['w'] * ($a === 'reszben' ? 0.5 : 1), 'title' => $q['fix'], 'steps' => $steps,
                    'src' => KIBER_CATEGORIES[$q['cat']] . ($a === 'nemtudom' ? ' · ellenőrizendő' : '')];
    }
    if ($domain) {
        foreach ($domain['items'] as $it) {
            if (empty($it['fix'])) continue;
            $todos[] = ['prio' => $it['fix']['prio'], 'w' => max($it['max'] - $it['points'], 1), 'title' => $it['fix']['title'], 'steps' => $it['fix']['steps'], 'src' => 'Domain: ' . $domain['domain']];
        }
    }
    $order = ['now' => 0, 'soon' => 1, 'later' => 2];
    usort($todos, fn ($a, $b) => ($order[$a['prio']] <=> $order[$b['prio']]) ?: ($b['w'] <=> $a['w']));
    $qScore = $answers ? (int) round(100 * $got / $max) : null;
    $dScore = $domain['score'] ?? null;
    $score = $qScore === null ? $dScore : ($dScore === null ? $qScore : (int) round($qScore * 0.7 + $dScore * 0.3));
    return ['score' => $score, 'q_score' => $qScore, 'd_score' => $dScore, 'answered' => count($answers), 'todos' => $todos];
}

function kiber_report_text(array $ev, ?array $domain, string $company, string $unsubUrl, ?string $confirmUrl): string {
    $site = SITE_PHONE_EMAIL;
    $L = [];
    $L[] = 'Szia!';
    $L[] = '';
    $L[] = 'Köszönjük, hogy kitöltötted a kiberbiztonsági gyorsellenőrzést' . ($company !== '' ? " ($company)" : '') . '. Itt a teendőlistád, kezdve a legfontosabbal.';
    $L[] = '';
    $L[] = '== EREDMÉNY ==';
    $L[] = 'Összpontszám: ' . ($ev['score'] ?? '–') . '/100 – ' . kiber_level($ev['score']);
    $L[] = 'Kérdőív: ' . ($ev['q_score'] ?? '–') . '/100 (' . $ev['answered'] . '/' . count(KIBER_QUESTIONS) . ' kérdés megválaszolva)';
    if ($domain) {
        $st = ['ok' => 'Rendben', 'warn' => 'Figyelmeztetés', 'bad' => 'Hiba', 'info' => 'Info'];
        $L[] = 'Domain (' . $domain['domain'] . '): ' . $domain['score'] . '/100';
        foreach ($domain['items'] as $it) $L[] = '  [' . ($st[$it['status']] ?? 'Info') . '] ' . $it['label'] . ': ' . $it['detail'];
    }
    $L[] = '';
    if (!$ev['todos']) {
        $L[] = 'A megadott válaszok alapján nincs teendő. Szép munka!';
        $L[] = '';
    }
    foreach (KIBER_PRIO as $p => $label) {
        $group = array_values(array_filter($ev['todos'], fn ($t) => $t['prio'] === $p));
        if (!$group) continue;
        $L[] = '== ' . mb_strtoupper($label, 'UTF-8') . ' (' . count($group) . ') ==';
        foreach ($group as $i => $t) {
            $L[] = '';
            $L[] = ($i + 1) . '. ' . $t['title'] . '  [' . $t['src'] . ']';
            foreach ($t['steps'] as $j => $s) $L[] = '   ' . chr(97 + $j) . ') ' . $s;
        }
        $L[] = '';
    }
    $L[] = '== SEGÍTSÉG ==';
    $L[] = 'Ha nincs informatikusod, a teendők többségét szívesen beállítjuk helyetted. Válaszolj erre a levélre, vagy hívj: ' . $site['phone'] . '.';
    $L[] = '';
    $L[] = 'Újraellenőrzés bármikor: https://my-ai.hu/kiberbiztonsag/gyorsellenorzes/';
    if ($confirmUrl !== null) {
        $L[] = '';
        $L[] = '== FELIRATKOZÁS MEGERŐSÍTÉSE ==';
        $L[] = 'Kérted, hogy időnként írjunk a kiscégeket érintő kiberveszélyekről és az új eszközeinkről. Ehhez kattints erre a linkre (ha nem kattintasz, nem küldünk ilyen levelet):';
        $L[] = $confirmUrl;
    }
    $L[] = '';
    $L[] = '--';
    $L[] = 'my-ai.hu – Immobilis Partners Kft. · ' . $site['email'] . ' · ' . $site['phone'];
    $L[] = 'Ezt a levelet azért kaptad, mert a my-ai.hu gyorsellenőrzésén ezt a címet adtad meg. Nem szeretnél több levelet, vagy töröljük az adataidat? ' . $unsubUrl;
    return implode("\n", $L);
}

const SITE_PHONE_EMAIL = ['email' => 'info@my-ai.hu', 'phone' => '+36 30 584 5937'];

// --- levélküldés (az appok SMTP-kliensének egyszerűsített változata: apps/*/api/lib/mailer.php)

function kiber_mail(string $to, string $subject, string $body, string $replyTo = ''): bool {
    $smtp = kiber_config()['smtp'] ?? [];
    $host = preg_replace('/^www\./', '', explode(':', (string) ($_SERVER['HTTP_HOST'] ?? 'my-ai.hu'))[0]);
    $from = !empty($smtp['from']) ? (string) $smtp['from'] : (!empty($smtp['username']) && strpos((string) $smtp['username'], '@') ? (string) $smtp['username'] : "no-reply@$host");
    $msgId = '<' . bin2hex(random_bytes(12)) . '@' . (strpos($from, '@') ? substr($from, strpos($from, '@') + 1) : 'my-ai.hu') . '>';
    $headers = 'From: my-ai.hu <' . $from . ">\r\n"
        . ($replyTo !== '' ? "Reply-To: $replyTo\r\n" : '')
        . 'Date: ' . date('r') . "\r\nMessage-ID: $msgId\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n";
    $subj = mb_encode_mimeheader($subject, 'UTF-8', 'B');

    if (!empty($smtp['host']) && !empty($smtp['username']) && !empty($smtp['password'])) {
        if (kiber_smtp($smtp, $from, $to, "To: $to\r\nSubject: $subj\r\n" . $headers, $body)) return true;
    }
    $ok = filter_var($from, FILTER_VALIDATE_EMAIL) && !preg_match('/@\d+\.\d+\.\d+\.\d+$/', $from)
        ? @mail($to, $subj, $body, rtrim($headers), '-f' . $from)
        : @mail($to, $subj, $body, rtrim($headers));
    if (!$ok) error_log("[kiberbiztonsag] mail() sikertelen: $to");
    return $ok;
}

function kiber_smtp(array $smtp, string $from, string $to, string $headers, string $body): bool {
    $port = (int) ($smtp['port'] ?? 465);
    $ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]]);
    $s = @stream_socket_client(($port === 465 ? 'ssl://' : '') . $smtp['host'] . ':' . $port, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$s) { error_log("[kiberbiztonsag smtp] csatlakozás: $errstr"); return false; }
    stream_set_timeout($s, 15);
    $read = function () use ($s): string {
        $r = '';
        while (($line = fgets($s, 515)) !== false) { $r .= $line; if (!isset($line[3]) || $line[3] !== '-') break; }
        return $r;
    };
    $ok = true; $fail = '';
    $step = function (?string $cmd, string $code) use ($s, $read, &$ok, &$fail): void {
        if ($cmd !== null) fwrite($s, $cmd . "\r\n");
        $r = $read();
        if (substr($r, 0, 3) !== $code && $ok) { $ok = false; $fail = trim($r); }
    };
    $step(null, '220');
    $step('EHLO ' . preg_replace('/[^a-z0-9.-]/i', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost')), '250');
    $step('AUTH LOGIN', '334');
    $step(base64_encode((string) $smtp['username']), '334');
    $step(base64_encode((string) $smtp['password']), '235');
    $step("MAIL FROM:<$from>", '250');
    $step("RCPT TO:<$to>", '250');
    $step('DATA', '354');
    $step($headers . "\r\n" . preg_replace('/^\./m', '..', str_replace(["\r\n", "\n"], ["\n", "\r\n"], $body)) . "\r\n.", '250');
    fwrite($s, "QUIT\r\n");
    fclose($s);
    if (!$ok) error_log("[kiberbiztonsag smtp] $fail");
    return $ok;
}

// --- admin-belépés: aláírt süti (a tárhely munkamenet-törlése nem jelentkezteti ki)

const KIBER_ADMIN_COOKIE = 'kiber_admin';

function kiber_admin_sign(int $exp): string { return $exp . '.' . hash_hmac('sha256', "admin|$exp", kiber_secret()); }

function kiber_is_admin(): bool {
    $c = (string) ($_COOKIE[KIBER_ADMIN_COOKIE] ?? '');
    if (!preg_match('/^(\d+)\.([0-9a-f]{64})$/', $c, $m) || (int) $m[1] < time()) return false;
    return hash_equals(kiber_admin_sign((int) $m[1]), $c);
}

function kiber_csrf(): string { return substr(hash_hmac('sha256', 'csrf|' . ($_COOKIE[KIBER_ADMIN_COOKIE] ?? ''), kiber_secret()), 0, 32); }
