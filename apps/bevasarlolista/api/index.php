<?php
// API: api/?r=<útvonal>
// Nyilvános: config, login, register, logout, me, forgot, reset
// Bejelentkezve: lists, lists/save, lists/delete, lists/invite, join, list,
//                items/add, items/update, items/delete, items/clear, suggestions,
//                account/save, account/password
// Admin: admin/users, admin/users/save, admin/users/delete, admin/options/save
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
    return q_one('SELECT id, name, email, is_admin FROM {users} WHERE id = ?', [(int) $_SESSION['user_id']]);
}

function public_user(array $u): array {
    return ['id' => (int) $u['id'], 'name' => $u['name'], 'email' => $u['email'], 'is_admin' => (bool) $u['is_admin']];
}

function create_user(string $name, string $email, string $password, bool $admin): int {
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new AppError('Add meg a nevet és egy érvényes email címet.');
    if (strlen($password) < 8) throw new AppError('A jelszó legalább 8 karakter legyen.');
    if (q_one('SELECT id FROM {users} WHERE email = ?', [$email])) throw new AppError('Ezzel az email címmel már van fiók.');
    q_exec('INSERT INTO {users} (name, email, password_hash, is_admin) VALUES (?, ?, ?, ?)',
        [$name, $email, password_hash($password, PASSWORD_DEFAULT), $admin ? 1 : 0]);
    $id = (int) db()->insert_id;
    create_list($id, 'Bevásárlólista'); // mindenki egy üres listával indul
    return $id;
}

function login_user(int $id): void {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $id;
}

function create_list(int $userId, string $name): int {
    q_exec('INSERT INTO {lists} (name, owner_id, invite_token) VALUES (?, ?, ?)', [$name, $userId, bin2hex(random_bytes(16))]);
    $id = (int) db()->insert_id;
    q_exec('INSERT INTO {list_members} (list_id, user_id, role) VALUES (?, ?, ?)', [$id, $userId, 'owner']);
    return $id;
}

// Egy tétel ellenőrzése a kliens által küldött adatokból (a kategóriát a szerver adja,
// kivéve, ha a felhasználó kézzel választott).
function item_from_input(array $in): array {
    $name = str_in($in, 'name', 120);
    if ($name === '') throw new AppError('Add meg a tétel nevét.');
    $name = mb_strtoupper(mb_substr($name, 0, 1)) . mb_substr($name, 1);
    $cat = (string) ($in['category'] ?? '');
    if (!in_array($cat, category_keys(), true)) $cat = categorize($name);
    return [$name, str_in($in, 'qty', 40), $cat, str_in($in, 'note', 200)];
}

try {
    if (!app_installed()) throw new AppError('A rendszer még nincs telepítve (install.php).', 503);
    session_name(app_config()['session_name'] ?? 'bevasarlolista_session');
    session_set_cookie_params(['lifetime' => 60 * 60 * 24 * 180, 'path' => dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/api/index.php')) ?: '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'httponly' => true, 'samesite' => 'Lax']);
    session_start();

    $route = (string) ($_GET['r'] ?? '');
    $isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    if ($isPost && ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'fetch') throw new AppError('Érvénytelen kérés.', 400);
    $public = ['config', 'login', 'register', 'logout', 'me', 'forgot', 'reset'];
    $user = current_user();
    if (!in_array($route, $public, true) && !$user) throw new AppError('Bejelentkezés szükséges.', 401);
    if (strpos($route, 'admin/') === 0 && empty($user['is_admin'])) throw new AppError('Ehhez nincs jogosultságod.', 403);
    $readOnly = ['config', 'me', 'lists', 'list', 'suggestions', 'admin/users'];
    if (!in_array($route, $readOnly, true) && !$isPost) throw new AppError('Method not allowed', 405);
    $uid = $user ? (int) $user['id'] : 0;

    switch ($route) {
        case 'config':
            respond(['app_name' => get_options()['app_name'], 'allow_register' => (bool) get_options()['allow_register']]);

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

        // ------------------------------------------------------------- listák
        case 'lists':
            respond(['lists' => q_all('SELECT l.id, l.name, l.owner_id, l.version, m.role,
                        (SELECT COUNT(*) FROM {items} i WHERE i.list_id = l.id AND i.checked = 0) AS open_items,
                        (SELECT COUNT(*) FROM {list_members} x WHERE x.list_id = l.id) AS members
                     FROM {lists} l JOIN {list_members} m ON m.list_id = l.id AND m.user_id = ? ORDER BY l.updated_at DESC', [$uid])]);

        case 'lists/save': {
            $in = input();
            $name = str_in($in, 'name', 80);
            if ($name === '') throw new AppError('Add meg a lista nevét.');
            $id = (int) ($in['id'] ?? 0);
            if ($id) {
                require_list($id, $uid);
                q_exec('UPDATE {lists} SET name = ? WHERE id = ?', [$name, $id]);
                bump_version($id);
            } else {
                if ((int) q_one('SELECT COUNT(*) AS n FROM {list_members} WHERE user_id = ?', [$uid])['n'] >= 50) throw new AppError('Legfeljebb 50 lista lehet.');
                $id = create_list($uid, $name);
            }
            respond(['ok' => true, 'id' => $id]);
        }

        case 'lists/delete': {
            // tulajdonos: törli a listát mindenkinél; tag: csak kilép belőle
            $l = require_list((int) (input()['id'] ?? 0), $uid);
            if ($l['role'] === 'owner') {
                foreach (['{items}', '{item_history}', '{list_members}'] as $t) q_exec("DELETE FROM $t WHERE list_id = ?", [(int) $l['id']]);
                q_exec('DELETE FROM {lists} WHERE id = ?', [(int) $l['id']]);
            } else {
                q_exec('DELETE FROM {list_members} WHERE list_id = ? AND user_id = ?', [(int) $l['id'], $uid]);
            }
            respond(['ok' => true]);
        }

        case 'lists/invite': {
            $in = input();
            $l = require_list((int) ($in['id'] ?? 0), $uid);
            if (!empty($in['reset'])) {
                if ($l['role'] !== 'owner') throw new AppError('Új meghívó linket csak a lista tulajdonosa készíthet.', 403);
                q_exec('UPDATE {lists} SET invite_token = ? WHERE id = ?', [bin2hex(random_bytes(16)), (int) $l['id']]);
                $l = require_list((int) $l['id'], $uid);
            }
            respond(['url' => app_base_url() . '/?join=' . $l['invite_token']]);
        }

        case 'join': {
            rate_limit('join:' . client_ip(), 20, 3600);
            $token = (string) (input()['token'] ?? '');
            $l = preg_match('/^[a-f0-9]{32}$/', $token) ? q_one('SELECT id, name FROM {lists} WHERE invite_token = ?', [$token]) : null;
            if (!$l) throw new AppError('A meghívó link érvénytelen vagy már lecserélték.', 404);
            q_exec('INSERT IGNORE INTO {list_members} (list_id, user_id, role) VALUES (?, ?, ?)', [(int) $l['id'], $uid, 'member']);
            bump_version((int) $l['id']);
            respond(['ok' => true, 'id' => (int) $l['id'], 'name' => $l['name']]);
        }

        case 'list': {
            $l = require_list((int) ($_GET['id'] ?? 0), $uid);
            // ha a kliensnél már a legfrissebb van, csak a verziót küldjük (gyors lekérdezés)
            if (isset($_GET['v']) && (int) $_GET['v'] === (int) $l['version']) respond(['unchanged' => true, 'version' => (int) $l['version']]);
            respond(list_payload((int) $l['id']));
        }

        // ------------------------------------------------------------- tételek
        case 'items/add': {
            $in = input();
            $l = require_list((int) ($in['list_id'] ?? 0), $uid);
            $rows = is_array($in['items'] ?? null) ? array_slice($in['items'], 0, 50) : [];
            if (!$rows) throw new AppError('Nincs mit hozzáadni.');
            if ((int) q_one('SELECT COUNT(*) AS n FROM {items} WHERE list_id = ?', [(int) $l['id']])['n'] + count($rows) > 500) throw new AppError('Egy listán legfeljebb 500 tétel lehet.');
            $pos = (int) q_one('SELECT COALESCE(MAX(position), 0) AS p FROM {items} WHERE list_id = ?', [(int) $l['id']])['p'];
            $ids = [];
            foreach ($rows as $row) {
                [$name, $qty, $cat, $note] = item_from_input(is_array($row) ? $row : []);
                // ha ugyanez a tétel már rajta van (nincs kipipálva), nem vesszük fel kétszer — a mennyiséget frissítjük
                $dup = q_one('SELECT id FROM {items} WHERE list_id = ? AND checked = 0 AND LOWER(name) = LOWER(?)', [(int) $l['id'], $name]);
                if ($dup) {
                    if ($qty !== '') q_exec('UPDATE {items} SET qty = ? WHERE id = ?', [$qty, (int) $dup['id']]);
                    $ids[] = (int) $dup['id'];
                } else {
                    q_exec('INSERT INTO {items} (list_id, name, qty, category, note, position, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)',
                        [(int) $l['id'], $name, $qty, $cat, $note, ++$pos, $uid]);
                    $ids[] = (int) db()->insert_id;
                }
                remember_item((int) $l['id'], $name, $qty);
            }
            bump_version((int) $l['id']);
            respond(['ok' => true, 'ids' => $ids] + list_payload((int) $l['id']));
        }

        case 'items/update': {
            $in = input();
            $item = q_one('SELECT * FROM {items} WHERE id = ?', [(int) ($in['id'] ?? 0)]);
            if (!$item) throw new AppError('A tétel nem található (lehet, hogy más már törölte).', 404);
            require_list((int) $item['list_id'], $uid);
            if (array_key_exists('checked', $in)) {
                q_exec('UPDATE {items} SET checked = ?, checked_at = ? WHERE id = ?', [!empty($in['checked']) ? 1 : 0, !empty($in['checked']) ? date('Y-m-d H:i:s') : null, (int) $item['id']]);
            }
            if (array_key_exists('name', $in)) {
                [$name, $qty, $cat, $note] = item_from_input($in);
                q_exec('UPDATE {items} SET name = ?, qty = ?, category = ?, note = ? WHERE id = ?', [$name, $qty, $cat, $note, (int) $item['id']]);
            }
            bump_version((int) $item['list_id']);
            respond(['ok' => true] + list_payload((int) $item['list_id']));
        }

        case 'items/delete': {
            $in = input();
            $item = q_one('SELECT id, list_id FROM {items} WHERE id = ?', [(int) ($in['id'] ?? 0)]);
            if ($item) {
                require_list((int) $item['list_id'], $uid);
                q_exec('DELETE FROM {items} WHERE id = ?', [(int) $item['id']]);
                bump_version((int) $item['list_id']);
            }
            respond(['ok' => true]);
        }

        case 'items/clear': {
            $l = require_list((int) (input()['list_id'] ?? 0), $uid);
            $n = q_exec('DELETE FROM {items} WHERE list_id = ? AND checked = 1', [(int) $l['id']]);
            bump_version((int) $l['id']);
            respond(['ok' => true, 'removed' => $n] + list_payload((int) $l['id']));
        }

        case 'suggestions': {
            $l = require_list((int) ($_GET['list_id'] ?? 0), $uid);
            // a gyakran vett tételek közül azok, amelyek most nincsenek a listán
            respond(['suggestions' => q_all('SELECT h.name, h.qty, h.uses FROM {item_history} h
                WHERE h.list_id = ? AND NOT EXISTS (SELECT 1 FROM {items} i WHERE i.list_id = h.list_id AND i.checked = 0 AND LOWER(i.name) = LOWER(h.name))
                ORDER BY h.uses DESC, h.last_used DESC LIMIT 12', [(int) $l['id']])]);
        }

        // ------------------------------------------------------------- fiók
        case 'account/save': {
            $in = input();
            $name = str_in($in, 'name', 120);
            $email = str_in($in, 'email', 190);
            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new AppError('Add meg a nevet és egy érvényes email címet.');
            if (q_one('SELECT id FROM {users} WHERE email = ? AND id <> ?', [$email, $uid])) throw new AppError('Ezzel az email címmel már van fiók.');
            q_exec('UPDATE {users} SET name = ?, email = ? WHERE id = ?', [$name, $email, $uid]);
            respond(['user' => public_user(current_user())]);
        }

        case 'account/password': {
            $in = input();
            $me = q_one('SELECT password_hash FROM {users} WHERE id = ?', [$uid]);
            if (!password_verify((string) ($in['current'] ?? ''), (string) $me['password_hash'])) throw new AppError('A jelenlegi jelszó hibás.');
            if (strlen((string) ($in['new'] ?? '')) < 8) throw new AppError('Az új jelszó legalább 8 karakter legyen.');
            q_exec('UPDATE {users} SET password_hash = ? WHERE id = ?', [password_hash((string) $in['new'], PASSWORD_DEFAULT), $uid]);
            respond(['ok' => true]);
        }

        // ------------------------------------------------------------- admin
        case 'admin/users':
            respond(['users' => q_all('SELECT u.id, u.name, u.email, u.is_admin, u.created_at FROM {users} u ORDER BY u.id'), 'options' => get_options()]);

        case 'admin/users/save': {
            $in = input();
            create_user(str_in($in, 'name', 120), str_in($in, 'email', 190), (string) ($in['password'] ?? ''), !empty($in['is_admin']));
            respond(['ok' => true]);
        }

        case 'admin/users/delete': {
            $id = (int) (input()['id'] ?? 0);
            if ($id === $uid) throw new AppError('Saját magadat nem törölheted.');
            foreach (q_all('SELECT list_id FROM {list_members} WHERE user_id = ? AND role = ?', [$id, 'owner']) as $own) {
                foreach (['{items}', '{item_history}', '{list_members}'] as $t) q_exec("DELETE FROM $t WHERE list_id = ?", [(int) $own['list_id']]);
                q_exec('DELETE FROM {lists} WHERE id = ?', [(int) $own['list_id']]);
            }
            q_exec('DELETE FROM {list_members} WHERE user_id = ?', [$id]);
            q_exec('DELETE FROM {users} WHERE id = ?', [$id]);
            respond(['ok' => true]);
        }

        case 'admin/options/save': {
            $in = input();
            save_options(['app_name' => str_in($in, 'app_name', 60) ?: 'Bevásárlólista', 'allow_register' => !empty($in['allow_register'])]);
            respond(['ok' => true]);
        }

        default:
            throw new AppError('Ismeretlen útvonal.', 404);
    }
} catch (AppError $e) {
    respond(['error' => $e->getMessage()], $e->status);
} catch (Throwable $e) {
    error_log('[bevasarlolista] ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    respond(['error' => 'Váratlan hiba történt.'], 500);
}
