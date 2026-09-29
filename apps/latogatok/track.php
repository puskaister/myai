<?php
// A követő szkript (t.js) ide küldi az adatokat. Típusok:
//   view  – új oldalmegtekintés → válasz: {pv, k} (k: aláírás a további hívásokhoz)
//   ping  – eltöltött (látható) idő frissítése
//   click – kattintás egy linkre / gombra
// Más oldalakról is fogad adatot (CORS), de sütit nem használ.
declare(strict_types=1);

require __DIR__ . '/api/lib/core.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }

function done(int $code = 204, ?array $body = null): void {
    http_response_code($code);
    if ($body !== null) {
        header('Content-Type: application/json');
        echo json_encode($body);
    }
    exit;
}

function sign(int $pv): string {
    $secret = (string) (app_config()['db']['pass'] ?? '') . '|' . table_prefix();
    return substr(hash_hmac('sha256', 'pv' . $pv, $secret), 0, 20);
}

function clip(?string $s, int $max): string {
    return mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $s)), 0, $max);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !app_installed()) done(204);

try {
    $in = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($in)) done(400);
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    if (is_bot($ua)) done(204);

    $options = get_options();
    $ip = visitor_ip();
    if (in_array($ip, $options['exclude_ips'], true)) done(204);

    $type = (string) ($in['t'] ?? '');

    if ($type === 'view') {
        // Árasztás ellen: IP-címenként óránként legfeljebb 300 megtekintés.
        $n = q_one('SELECT COUNT(*) AS n FROM {pageviews} WHERE ip = ? AND started_at > ?', [$options['anonymize_ip'] ? anonymize_ip($ip) : $ip, date('Y-m-d H:i:s', time() - 3600)]);
        if ((int) $n['n'] >= 300) done(429);

        $vid = preg_match('/^[a-z0-9]{16}$/', (string) ($in['v'] ?? '')) ? (string) $in['v'] : bin2hex(random_bytes(8));
        $isNew = !q_one('SELECT id FROM {pageviews} WHERE visitor_id = ? LIMIT 1', [$vid]);
        $u = parse_ua($ua);
        $ref = clip($in['r'] ?? '', 500);
        $host = clip($in['h'] ?? '', 120);
        if ($ref !== '' && parse_url($ref, PHP_URL_HOST) === $host) $ref = ''; // belső navigáció nem hivatkozó

        q_exec(
            'INSERT INTO {pageviews} (visitor_id, is_new, ip, host, path, title, referrer, browser, browser_version, os, device, screen, lang)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$vid, $isNew ? 1 : 0, $options['anonymize_ip'] ? anonymize_ip($ip) : $ip, $host, clip($in['p'] ?? '/', 500) ?: '/',
             clip($in['ti'] ?? '', 200), $ref, $u['browser'], $u['version'], $u['os'], $u['device'], clip($in['s'] ?? '', 20), clip($in['l'] ?? '', 20)]
        );
        $pv = (int) db()->insert_id;

        // Régi adatok törlése (kb. minden 200. megtekintésnél).
        if (random_int(1, 200) === 1) {
            $cut = date('Y-m-d H:i:s', time() - max(30, (int) $options['retention_days']) * 86400);
            q_exec('DELETE FROM {clicks} WHERE created_at < ?', [$cut]);
            q_exec('DELETE FROM {pageviews} WHERE started_at < ?', [$cut]);
        }
        done(200, ['pv' => $pv, 'k' => sign($pv), 'v' => $vid]);
    }

    $pv = (int) ($in['pv'] ?? 0);
    if ($pv <= 0 || !hash_equals(sign($pv), (string) ($in['k'] ?? ''))) done(403);

    if ($type === 'ping') {
        $active = max(0, min(4 * 3600, (int) ($in['a'] ?? 0)));
        q_exec('UPDATE {pageviews} SET active_seconds = GREATEST(active_seconds, ?), last_seen_at = NOW() WHERE id = ?', [$active, $pv]);
        done(204);
    }

    if ($type === 'click') {
        $row = q_one('SELECT clicks FROM {pageviews} WHERE id = ?', [$pv]);
        if (!$row || (int) $row['clicks'] >= 500) done(204);
        q_exec('INSERT INTO {clicks} (pageview_id, tag, label, href) VALUES (?, ?, ?, ?)',
            [$pv, clip($in['tg'] ?? '', 20), clip($in['lb'] ?? '', 120), clip($in['hr'] ?? '', 500)]);
        q_exec('UPDATE {pageviews} SET clicks = clicks + 1, last_seen_at = NOW() WHERE id = ?', [$pv]);
        done(204);
    }
    done(400);
} catch (Throwable $e) {
    error_log('[latogatok] track: ' . $e->getMessage());
    done(500);
}
