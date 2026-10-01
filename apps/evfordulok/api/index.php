<?php
// API: api/?r=<útvonal>
// Nyilvános: config, login, register, logout, me, forgot, reset
// Bejelentkezve: events, events/save, events/delete, account, account/save,
//                account/password, account/calendar-reset
// Admin: admin/users, admin/users/save, admin/users/delete, admin/options
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

function current_user(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    return q_one('SELECT id, name, email, is_admin, notify, cal_token FROM {users} WHERE id = ?', [(int) $_SESSION['user_id']]);
}

function public_user(array $u): array {
    return ['id' => (int) $u['id'], 'name' => $u['name'], 'email' => $u['email'], 'is_admin' => (bool) $u['is_admin'],
            'notify' => (bool) $u['notify'], 'calendar_url' => app_base_url() . '/ics.php?t=' . $u['cal_token']];
}

function create_user(string $name, string $email, string $password, bool $admin): int {
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new AppError('Add meg a nevet és egy érvényes email címet.');
    if (strlen($password) < 8) throw new AppError('A jelszó legalább 8 karakter legyen.');
    if (q_one('SELECT id FROM {users} WHERE email = ?', [$email])) throw new AppError('Ezzel az email címmel már van fiók.');
    q_exec('INSERT INTO {users} (name, email, password_hash, is_admin, cal_token) VALUES (?, ?, ?, ?, ?)',
        [$name, $email, password_hash($password, PASSWORD_DEFAULT), $admin ? 1 : 0, bin2hex(random_bytes(16))]);
    return (int) db()->insert_id;
}

function login_user(int $id): void {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $id;
}

// Tartalék az ütemezett futás mellé: ha ma még nem ment ki az emlékeztető-kör
// (és elmúlt a küldési óra), az első oldalmegnyitás lefuttatja.
function maybe_run_reminders(): void {
    $today = date('Y-m-d');
    if (setting_value('last_run') === $today || (int) date('G') < (int) get_options()['send_hour']) return;
    try {
        run_reminders($today);
    } catch (Throwable $e) {
        error_log('[evfordulok] emlékeztető-kör hiba: ' . $e->getMessage());
    }
}

function event_from_input(array $in): array {
    $title = str_in($in, 'title', 150);
    if ($title === '') throw new AppError('Add meg az alkalom nevét.');
    $category = array_key_exists((string) ($in['category'] ?? ''), CATEGORIES) ? (string) $in['category'] : 'other';
    $recurrence = array_key_exists((string) ($in['recurrence'] ?? ''), RECURRENCES) ? (string) $in['recurrence'] : 'yearly';
    $month = (int) ($in['month'] ?? 0);
    $day = (int) ($in['day'] ?? 0);
    $year = isset($in['year']) && $in['year'] !== '' && $in['year'] !== null ? (int) $in['year'] : null;
    if ($recurrence === 'monthly') $month = $month ?: 1;
    if ($month < 1 || $month > 12 || $day < 1 || $day > 31) throw new AppError('Érvénytelen dátum.');
    if ($year !== null && ($year < 1800 || $year > 2200)) throw new AppError('Érvénytelen évszám.');
    if ($recurrence === 'once' && $year === null) throw new AppError('Egyszeri alkalomhoz add meg a teljes dátumot, évszámmal.');
    // létező nap-e (febr. 29. éves ismétlésnél évszám nélkül is megengedett)
    $checkYear = $year ?? 2024;
    if (!checkdate($month, $day, $checkYear)) throw new AppError('Ilyen nap nincs a naptárban.');
    $remind = (int) ($in['remind_days'] ?? 1);
    if (!in_array($remind, [-1, 0, 1, 2, 3, 7, 14], true)) $remind = 1;
    return [$title, $category, $month, $day, $year, $recurrence, $remind, str_in($in, 'note', 1000)];
}

try {
    if (!app_installed()) throw new AppError('A rendszer még nincs telepítve (install.php).', 503);
    session_name(app_config()['session_name'] ?? 'evfordulok_session');
    session_set_cookie_params(['lifetime' => 60 * 60 * 24 * 90, 'path' => dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/api/index.php')) ?: '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'httponly' => true, 'samesite' => 'Lax']);
    session_start();

    $route = (string) ($_GET['r'] ?? '');
    $isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    if ($isPost && ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'fetch') throw new AppError('Érvénytelen kérés.', 400);
    $public = ['config', 'login', 'register', 'logout', 'me', 'forgot', 'reset'];
    $user = current_user();
    if (!in_array($route, $public, true) && !$user) throw new AppError('Bejelentkezés szükséges.', 401);
    if (strpos($route, 'admin/') === 0 && empty($user['is_admin'])) throw new AppError('Ehhez nincs jogosultságod.', 403);
    if ((in_array($route, ['login', 'register', 'logout', 'forgot', 'reset'], true) || preg_match('#/(save|delete|password|calendar-reset|test-email)$#', $route)) && !$isPost) {
        throw new AppError('Method not allowed', 405);
    }

    switch ($route) {
        case 'config':
            respond(['app_name' => get_options()['app_name'], 'allow_register' => (bool) get_options()['allow_register'],
                     'categories' => CATEGORIES, 'recurrences' => RECURRENCES]);

        case 'login': {
            rate_limit('login:' . client_ip(), 10, 900);
            $in = input();
            $u = q_one('SELECT * FROM {users} WHERE email = ?', [str_in($in, 'email', 190)]);
            if (!$u || !password_verify((string) ($in['password'] ?? ''), (string) $u['password_hash'])) throw new AppError('Hibás email cím vagy jelszó.', 401);
            login_user((int) $u['id']);
            respond(['user' => public_user($u)]);
        }

        case 'register': {
            if (!get_options()['allow_register']) throw new AppError('A regisztráció nincs engedélyezve.', 403);
            rate_limit('register:' . client_ip(), 5, 3600);
            $in = input();
            $id = create_user(str_in($in, 'name', 120), str_in($in, 'email', 190), (string) ($in['password'] ?? ''), false);
            login_user($id);
            respond(['user' => public_user(current_user())]);
        }

        case 'logout':
            $_SESSION = [];
            session_destroy();
            respond(['ok' => true]);

        case 'me':
            respond(['user' => $user ? public_user($user) : null]);

        case 'forgot': {
            rate_limit('forgot:' . client_ip(), 5, 3600);
            $u = q_one('SELECT id, name, email FROM {users} WHERE email = ?', [str_in(input(), 'email', 190)]);
            if ($u) {
                $token = bin2hex(random_bytes(32));
                q_exec('DELETE FROM {password_resets} WHERE user_id = ? OR expires_at < NOW()', [(int) $u['id']]);
                q_exec('INSERT INTO {password_resets} (user_id, token_hash, expires_at) VALUES (?, ?, ?)',
                    [(int) $u['id'], hash('sha256', $token), date('Y-m-d H:i:s', time() + 3600)]);
                send_app_email(app_config() ?? [], $u['email'], 'Jelszó visszaállítása – ' . get_options()['app_name'],
                    "Kedves {$u['name']}!\n\nÚj jelszót ezen a linken állíthatsz be (1 óráig érvényes):\n\n" . app_base_url() . "/?reset=$token\n\nHa nem te kérted, hagyd figyelmen kívül ezt a levelet.");
            }
            respond(['ok' => true]);
        }

        case 'reset': {
            rate_limit('reset:' . client_ip(), 10, 3600);
            $in = input();
            $token = (string) ($in['token'] ?? '');
            $row = preg_match('/^[a-f0-9]{64}$/', $token)
                ? q_one('SELECT id, user_id FROM {password_resets} WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()', [hash('sha256', $token)])
                : null;
            if (!$row) throw new AppError('A link érvénytelen vagy lejárt. Kérj újat az „Elfelejtett jelszó?” linkkel.');
            if (strlen((string) ($in['password'] ?? '')) < 8) throw new AppError('A jelszó legalább 8 karakter legyen.');
            q_exec('UPDATE {users} SET password_hash = ? WHERE id = ?', [password_hash((string) $in['password'], PASSWORD_DEFAULT), (int) $row['user_id']]);
            q_exec('UPDATE {password_resets} SET used_at = NOW() WHERE id = ?', [(int) $row['id']]);
            respond(['ok' => true]);
        }

        // ------------------------------------------------------------- alkalmak
        case 'events':
            maybe_run_reminders();
            respond(['today' => date('Y-m-d'), 'events' => user_events((int) $user['id'], date('Y-m-d'))]);

        case 'events/save': {
            $in = input();
            [$title, $category, $month, $day, $year, $recurrence, $remind, $note] = event_from_input($in);
            $id = (int) ($in['id'] ?? 0);
            if ($id) {
                $n = q_exec('UPDATE {events} SET title = ?, category = ?, month = ?, day = ?, year = ?, recurrence = ?, remind_days = ?, note = ? WHERE id = ? AND user_id = ?',
                    [$title, $category, $month, $day, $year, $recurrence, $remind, $note, $id, (int) $user['id']]);
                if (!$n && !q_one('SELECT id FROM {events} WHERE id = ? AND user_id = ?', [$id, (int) $user['id']])) throw new AppError('Nem található.', 404);
            } else {
                if ((int) q_one('SELECT COUNT(*) AS n FROM {events} WHERE user_id = ?', [(int) $user['id']])['n'] >= 1000) throw new AppError('Legfeljebb 1000 alkalom rögzíthető.');
                q_exec('INSERT INTO {events} (user_id, title, category, month, day, year, recurrence, remind_days, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [(int) $user['id'], $title, $category, $month, $day, $year, $recurrence, $remind, $note]);
                $id = (int) db()->insert_id;
            }
            respond(['ok' => true, 'id' => $id]);
        }

        case 'events/delete': {
            $id = (int) (input()['id'] ?? 0);
            q_exec('DELETE FROM {events} WHERE id = ? AND user_id = ?', [$id, (int) $user['id']]);
            q_exec('DELETE FROM {sent_reminders} WHERE event_id = ? AND NOT EXISTS (SELECT 1 FROM {events} WHERE id = ?)', [$id, $id]);
            respond(['ok' => true]);
        }

        // ------------------------------------------------------------- fiók
        case 'account/save': {
            $in = input();
            $name = str_in($in, 'name', 120);
            $email = str_in($in, 'email', 190);
            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new AppError('Add meg a nevet és egy érvényes email címet.');
            if (q_one('SELECT id FROM {users} WHERE email = ? AND id <> ?', [$email, (int) $user['id']])) throw new AppError('Ezzel az email címmel már van fiók.');
            q_exec('UPDATE {users} SET name = ?, email = ?, notify = ? WHERE id = ?', [$name, $email, !empty($in['notify']) ? 1 : 0, (int) $user['id']]);
            respond(['user' => public_user(current_user())]);
        }

        case 'account/password': {
            $in = input();
            $me = q_one('SELECT password_hash FROM {users} WHERE id = ?', [(int) $user['id']]);
            if (!password_verify((string) ($in['current'] ?? ''), (string) $me['password_hash'])) throw new AppError('A jelenlegi jelszó hibás.');
            if (strlen((string) ($in['new'] ?? '')) < 8) throw new AppError('Az új jelszó legalább 8 karakter legyen.');
            q_exec('UPDATE {users} SET password_hash = ? WHERE id = ?', [password_hash((string) $in['new'], PASSWORD_DEFAULT), (int) $user['id']]);
            respond(['ok' => true]);
        }

        // Teszt levél a saját címre: kiderül, hogy elmegy-e, és milyen úton.
        case 'account/test-email': {
            rate_limit('testmail:' . (int) $user['id'], 5, 3600);
            $ok = send_app_email(app_config() ?? [], $user['email'], 'Teszt levél – ' . get_options()['app_name'],
                "Kedves {$user['name']}!\n\nEz egy teszt levél az Évfordulók appból. Ha megkaptad, az emlékeztetők is meg fognak érkezni.\n\n"
                . 'Küldés módja: ' . mail_transport() . "\nIdőpont: " . date('Y-m-d H:i') . "\n\n" . app_base_url() . '/');
            respond(['ok' => $ok, 'to' => $user['email'], 'transport' => mail_transport(), 'error' => $GLOBALS['mail_last']['error'] ?? '']);
        }

        case 'account/calendar-reset':
            q_exec('UPDATE {users} SET cal_token = ? WHERE id = ?', [bin2hex(random_bytes(16)), (int) $user['id']]);
            respond(['user' => public_user(current_user())]);

        // ------------------------------------------------------------- admin
        case 'admin/users':
            respond(['users' => q_all('SELECT u.id, u.name, u.email, u.is_admin, u.created_at, (SELECT COUNT(*) FROM {events} e WHERE e.user_id = u.id) AS events FROM {users} u ORDER BY u.id'),
                     'options' => get_options(), 'cron_url' => app_base_url() . '/cron.php?key=' . cron_key(), 'last_run' => setting_value('last_run'),
                     'last_result' => json_decode((string) setting_value('last_run_result'), true), 'transport' => mail_transport()]);

        case 'admin/users/save': {
            $in = input();
            create_user(str_in($in, 'name', 120), str_in($in, 'email', 190), (string) ($in['password'] ?? ''), !empty($in['is_admin']));
            respond(['ok' => true]);
        }

        case 'admin/users/delete': {
            $id = (int) (input()['id'] ?? 0);
            if ($id === (int) $user['id']) throw new AppError('Saját magadat nem törölheted.');
            q_exec('DELETE FROM {sent_reminders} WHERE event_id IN (SELECT id FROM {events} WHERE user_id = ?)', [$id]);
            q_exec('DELETE FROM {events} WHERE user_id = ?', [$id]);
            q_exec('DELETE FROM {users} WHERE id = ?', [$id]);
            respond(['ok' => true]);
        }

        case 'admin/options/save': {
            $in = input();
            save_options([
                'app_name'       => str_in($in, 'app_name', 60) ?: 'Évfordulók',
                'allow_register' => !empty($in['allow_register']),
                'send_hour'      => max(0, min(23, (int) ($in['send_hour'] ?? 7))),
            ]);
            respond(['ok' => true]);
        }

        default:
            throw new AppError('Ismeretlen útvonal.', 404);
    }
} catch (AppError $e) {
    respond(['error' => $e->getMessage()], $e->status);
} catch (Throwable $e) {
    error_log('[evfordulok] ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    respond(['error' => 'Váratlan hiba történt.'], 500);
}
