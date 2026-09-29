<?php
// Irányítópult API: api/?r=<útvonal>
// Belépés: login, logout, me, forgot, reset — admin: admin/*
declare(strict_types=1);

require __DIR__ . '/lib/core.php';

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function respond($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function input(): array {
    $data = json_decode((string) file_get_contents('php://input'), true);
    return is_array($data) ? $data : [];
}

function str_in(array $in, string $key, int $max = 500): string {
    return mb_substr(trim((string) ($in[$key] ?? '')), 0, $max);
}

function rate_limit(string $key, int $max, int $window): void {
    q_exec('DELETE FROM {rate_limits} WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
    $row = q_one('SELECT COUNT(*) AS n FROM {rate_limits} WHERE k = ? AND created_at > ?', [$key, date('Y-m-d H:i:s', time() - $window)]);
    if ((int) ($row['n'] ?? 0) >= $max) throw new AppError('Túl sok próbálkozás, próbáld újra később.', 429);
    q_exec('INSERT INTO {rate_limits} (k) VALUES (?)', [$key]);
}

function current_admin(): ?array {
    if (empty($_SESSION['admin_id'])) return null;
    return q_one('SELECT id, name, email FROM {admins} WHERE id = ?', [(int) $_SESSION['admin_id']]);
}

// Az időszak kezdete: 1 = ma, különben az utolsó N nap (a mai nappal együtt).
function range_start(int $days): string {
    return date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'));
}

function group_counts(string $column, string $from, int $limit = 8): array {
    return q_all("SELECT $column AS name, COUNT(*) AS n FROM {pageviews} WHERE started_at >= ? GROUP BY $column ORDER BY n DESC LIMIT $limit", [$from]);
}

try {
    if (!app_installed()) throw new AppError('A rendszer még nincs telepítve (install.php).', 503);
    session_name(app_config()['session_name'] ?? 'latogatok_session');
    session_set_cookie_params(['lifetime' => 0, 'path' => dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/api/index.php')) ?: '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'httponly' => true, 'samesite' => 'Lax']);
    session_start();

    $route = (string) ($_GET['r'] ?? '');
    $isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    if ($isPost && ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'fetch') throw new AppError('Érvénytelen kérés.', 400);
    if (strpos($route, 'admin/') === 0 && !current_admin()) throw new AppError('Bejelentkezés szükséges.', 401);
    if (in_array($route, ['login', 'logout', 'forgot', 'reset', 'admin/admins/save', 'admin/admins/delete', 'admin/password', 'admin/reset-data'], true) && !$isPost) {
        throw new AppError('Method not allowed', 405);
    }

    switch ($route) {
        // ------------------------------------------------------------- belépés
        case 'login': {
            rate_limit('login:' . visitor_ip(), 10, 900);
            $in = input();
            $admin = q_one('SELECT * FROM {admins} WHERE email = ?', [str_in($in, 'email', 190)]);
            if (!$admin || !password_verify((string) ($in['password'] ?? ''), (string) $admin['password_hash'])) throw new AppError('Hibás email cím vagy jelszó.', 401);
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int) $admin['id'];
            respond(['ok' => true]);
        }

        case 'logout':
            $_SESSION = [];
            session_destroy();
            respond(['ok' => true]);

        case 'me':
            respond(['admin' => current_admin()]);

        case 'forgot': {
            rate_limit('forgot:' . visitor_ip(), 5, 3600);
            $admin = q_one('SELECT id, name, email FROM {admins} WHERE email = ?', [str_in(input(), 'email', 190)]);
            if ($admin) {
                $token = bin2hex(random_bytes(32));
                q_exec('DELETE FROM {password_resets} WHERE admin_id = ? OR expires_at < NOW()', [(int) $admin['id']]);
                q_exec('INSERT INTO {password_resets} (admin_id, token_hash, expires_at) VALUES (?, ?, ?)',
                    [(int) $admin['id'], hash('sha256', $token), date('Y-m-d H:i:s', time() + 3600)]);
                send_app_email(app_config() ?? [], $admin['email'], 'Jelszó visszaállítása – látogatószámláló',
                    "Kedves {$admin['name']}!\n\nJelszó-visszaállítást kértek a látogatószámláló felületéhez. Új jelszót ezen a linken állíthatsz be (1 óráig érvényes):\n\n"
                    . app_base_url() . "/?reset=$token\n\nHa nem te kérted, hagyd figyelmen kívül ezt a levelet.");
            }
            respond(['ok' => true]);
        }

        case 'reset': {
            rate_limit('reset:' . visitor_ip(), 10, 3600);
            $in = input();
            $token = (string) ($in['token'] ?? '');
            $row = preg_match('/^[a-f0-9]{64}$/', $token)
                ? q_one('SELECT id, admin_id FROM {password_resets} WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()', [hash('sha256', $token)])
                : null;
            if (!$row) throw new AppError('A link érvénytelen vagy lejárt. Kérj újat az „Elfelejtett jelszó?” linkkel.');
            if (strlen((string) ($in['password'] ?? '')) < 8) throw new AppError('A jelszó legalább 8 karakter legyen.');
            q_exec('UPDATE {admins} SET password_hash = ? WHERE id = ?', [password_hash((string) $in['password'], PASSWORD_DEFAULT), (int) $row['admin_id']]);
            q_exec('UPDATE {password_resets} SET used_at = NOW() WHERE id = ?', [(int) $row['id']]);
            respond(['ok' => true]);
        }

        // ------------------------------------------------------------- statisztika
        case 'admin/stats': {
            $days = in_array((int) ($_GET['days'] ?? 7), [1, 7, 30, 90], true) ? (int) $_GET['days'] : 7;
            $from = range_start($days);

            $t = q_one('SELECT COUNT(*) AS pv, COUNT(DISTINCT visitor_id) AS uv, COALESCE(SUM(is_new), 0) AS nw,
                               COALESCE(AVG(NULLIF(active_seconds, 0)), 0) AS avg_s, COALESCE(SUM(clicks), 0) AS clicks
                        FROM {pageviews} WHERE started_at >= ?', [$from]);

            // Idősor: ma óránként, egyébként naponként; a hiányzó pontok nullák.
            $series = [];
            if ($days === 1) {
                $rows = q_all('SELECT HOUR(started_at) AS b, COUNT(*) AS pv, COUNT(DISTINCT visitor_id) AS uv FROM {pageviews} WHERE started_at >= ? GROUP BY b', [$from]);
                $by = array_column($rows, null, 'b');
                for ($h = 0; $h <= (int) date('G'); $h++) $series[] = ['label' => sprintf('%02d:00', $h), 'pv' => (int) ($by[$h]['pv'] ?? 0), 'uv' => (int) ($by[$h]['uv'] ?? 0)];
            } else {
                $rows = q_all('SELECT DATE(started_at) AS b, COUNT(*) AS pv, COUNT(DISTINCT visitor_id) AS uv FROM {pageviews} WHERE started_at >= ? GROUP BY b', [$from]);
                $by = array_column($rows, null, 'b');
                for ($i = $days - 1; $i >= 0; $i--) {
                    $d = date('Y-m-d', strtotime("-$i days"));
                    $series[] = ['label' => $d, 'pv' => (int) ($by[$d]['pv'] ?? 0), 'uv' => (int) ($by[$d]['uv'] ?? 0)];
                }
            }

            $pages = q_all('SELECT path AS name, COUNT(*) AS n, COALESCE(AVG(NULLIF(active_seconds, 0)), 0) AS avg_s FROM {pageviews}
                            WHERE started_at >= ? GROUP BY path ORDER BY n DESC LIMIT 10', [$from]);
            $refRows = q_all("SELECT referrer, COUNT(*) AS n FROM {pageviews} WHERE started_at >= ? AND referrer <> '' GROUP BY referrer", [$from]);
            $refs = [];
            foreach ($refRows as $r) {
                $h = preg_replace('/^www\./', '', (string) (parse_url((string) $r['referrer'], PHP_URL_HOST) ?: $r['referrer']));
                $refs[$h] = ($refs[$h] ?? 0) + (int) $r['n'];
            }
            arsort($refs);
            $clicks = q_all('SELECT c.label AS name, c.href, COUNT(*) AS n FROM {clicks} c WHERE c.created_at >= ?
                             GROUP BY c.label, c.href ORDER BY n DESC LIMIT 10', [$from]);

            respond([
                'days'   => $days,
                'totals' => ['pageviews' => (int) $t['pv'], 'visitors' => (int) $t['uv'], 'new' => (int) $t['nw'],
                             'avg_seconds' => (int) round((float) $t['avg_s']), 'clicks' => (int) $t['clicks']],
                'series' => $series,
                'pages'  => $pages,
                'referrers' => array_map(fn ($k, $v) => ['name' => $k, 'n' => $v], array_keys(array_slice($refs, 0, 8, true)), array_slice($refs, 0, 8, true)),
                'browsers'  => group_counts('browser', $from),
                'os'        => group_counts('os', $from),
                'devices'   => group_counts('device', $from, 3),
                'clicks'    => $clicks,
            ]);
        }

        case 'admin/visits': {
            $days = in_array((int) ($_GET['days'] ?? 7), [1, 7, 30, 90], true) ? (int) $_GET['days'] : 7;
            $params = [range_start($days)];
            $where = 'started_at >= ?';
            $q = str_in($_GET, 'q', 100);
            if ($q !== '') {
                $where .= ' AND (ip = ? OR visitor_id = ? OR path LIKE ?)';
                array_push($params, $q, $q, '%' . $q . '%');
            }
            $offset = max(0, (int) ($_GET['offset'] ?? 0));
            $rows = q_all("SELECT id, visitor_id, is_new, ip, path, title, referrer, browser, browser_version, os, device, active_seconds, clicks, started_at
                           FROM {pageviews} WHERE $where ORDER BY id DESC LIMIT 100 OFFSET $offset", $params);
            respond(['visits' => $rows]);
        }

        case 'admin/visit': {
            $v = q_one('SELECT * FROM {pageviews} WHERE id = ?', [(int) ($_GET['id'] ?? 0)]);
            if (!$v) throw new AppError('Nem található.', 404);
            respond([
                'visit'   => $v,
                'clicks'  => q_all('SELECT tag, label, href, created_at FROM {clicks} WHERE pageview_id = ? ORDER BY id', [(int) $v['id']]),
                'journey' => q_all('SELECT id, path, title, active_seconds, clicks, started_at FROM {pageviews} WHERE visitor_id = ? ORDER BY id DESC LIMIT 50', [$v['visitor_id']]),
            ]);
        }

        // ------------------------------------------------------------- beállítások, fiók
        case 'admin/settings':
            if ($isPost) {
                $in = input();
                $ips = [];
                foreach ((array) ($in['exclude_ips'] ?? []) as $ip) {
                    $ip = trim((string) $ip);
                    if ($ip === '') continue;
                    if (!filter_var($ip, FILTER_VALIDATE_IP)) throw new AppError("Érvénytelen IP-cím: $ip");
                    $ips[] = $ip;
                }
                save_options([
                    'site_name'      => str_in($in, 'site_name', 100) ?: 'Weboldal',
                    'anonymize_ip'   => !empty($in['anonymize_ip']),
                    'exclude_ips'    => array_values(array_unique($ips)),
                    'retention_days' => max(30, min(1100, (int) ($in['retention_days'] ?? 395))),
                ]);
                respond(['ok' => true]);
            }
            respond(['options' => get_options(), 'my_ip' => visitor_ip(), 'me' => current_admin(), 'base_url' => app_base_url(),
                     'admins' => q_all('SELECT id, name, email FROM {admins} ORDER BY id')]);

        case 'admin/reset-data':
            q_exec('DELETE FROM {clicks}');
            q_exec('DELETE FROM {pageviews}');
            respond(['ok' => true]);

        case 'admin/admins/save': {
            $in = input();
            $name = str_in($in, 'name', 120);
            $email = str_in($in, 'email', 190);
            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new AppError('Add meg a nevet és egy érvényes email címet.');
            if (strlen((string) ($in['password'] ?? '')) < 8) throw new AppError('A jelszó legalább 8 karakter legyen.');
            if (q_one('SELECT id FROM {admins} WHERE email = ?', [$email])) throw new AppError('Ezzel az email címmel már van admin.');
            q_exec('INSERT INTO {admins} (name, email, password_hash) VALUES (?, ?, ?)', [$name, $email, password_hash((string) $in['password'], PASSWORD_DEFAULT)]);
            respond(['ok' => true]);
        }

        case 'admin/admins/delete': {
            $id = (int) (input()['id'] ?? 0);
            if ($id === (int) $_SESSION['admin_id']) throw new AppError('Saját magadat nem törölheted.');
            q_exec('DELETE FROM {admins} WHERE id = ?', [$id]);
            respond(['ok' => true]);
        }

        case 'admin/password': {
            $in = input();
            $me = q_one('SELECT * FROM {admins} WHERE id = ?', [(int) $_SESSION['admin_id']]);
            if (!password_verify((string) ($in['current'] ?? ''), (string) $me['password_hash'])) throw new AppError('A jelenlegi jelszó hibás.');
            if (strlen((string) ($in['new'] ?? '')) < 8) throw new AppError('Az új jelszó legalább 8 karakter legyen.');
            q_exec('UPDATE {admins} SET password_hash = ? WHERE id = ?', [password_hash((string) $in['new'], PASSWORD_DEFAULT), (int) $me['id']]);
            respond(['ok' => true]);
        }

        default:
            throw new AppError('Ismeretlen útvonal.', 404);
    }
} catch (AppError $e) {
    respond(['error' => $e->getMessage()], $e->status);
} catch (Throwable $e) {
    error_log('[latogatok] ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    respond(['error' => 'Váratlan hiba történt.'], 500);
}
