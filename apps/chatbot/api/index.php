<?php
// Egyetlen belépési pont az összes API-híváshoz: api/?r=<útvonal>
// Nyilvános: config, start, message, conversation
// Belépés: login, logout, me, forgot, reset — admin: admin/*
declare(strict_types=1);

require __DIR__ . '/lib/core.php';
require __DIR__ . '/lib/claude.php';

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function respond($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function input(): array {
    if (!empty($_POST)) return $_POST;
    $data = json_decode((string) file_get_contents('php://input'), true);
    return is_array($data) ? $data : [];
}

function str_in(array $in, string $key, int $max = 500): string {
    return mb_substr(trim((string) ($in[$key] ?? '')), 0, $max);
}

function client_ip(): string {
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function rate_limit(string $key, int $max, int $window): void {
    q_exec('DELETE FROM {rate_limits} WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
    $row = q_one('SELECT COUNT(*) AS n FROM {rate_limits} WHERE k = ? AND created_at > ?', [$key, date('Y-m-d H:i:s', time() - $window)]);
    if ((int) ($row['n'] ?? 0) >= $max) throw new AppError('Túl sok üzenet rövid idő alatt — kérlek, próbáld újra kicsit később.', 429);
    q_exec('INSERT INTO {rate_limits} (k) VALUES (?)', [$key]);
}

function start_session(): void {
    session_name(app_config()['session_name'] ?? 'chatbot_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/api/index.php')) ?: '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function current_admin(): ?array {
    if (empty($_SESSION['admin_id'])) return null;
    return q_one('SELECT id, name, email FROM {admins} WHERE id = ?', [(int) $_SESSION['admin_id']]);
}

function find_conversation(string $token): array {
    if (!preg_match('/^[a-f0-9]{32}$/', $token)) throw new AppError('A beszélgetés nem található.', 404);
    $c = q_one('SELECT * FROM {conversations} WHERE token = ?', [$token]);
    if (!$c) throw new AppError('A beszélgetés nem található.', 404);
    return $c;
}

function conversation_messages(int $id): array {
    return q_all('SELECT role, content, created_at FROM {messages} WHERE conversation_id = ? ORDER BY id', [$id]);
}

function progress(array $settings, array $answers): array {
    $total = count(required_questions($settings));
    return ['answered' => $total - count(missing_questions($settings, $answers)), 'total' => $total];
}

// Összefoglaló email a cégnek, amikor minden kötelező kérdésre megvan a válasz.
function notify_complete(array $settings, array $conv, array $answers): void {
    $to = $settings['notify']['admin_email'];
    if (!$settings['notify']['send_admin'] || !filter_var($to, FILTER_VALIDATE_EMAIL)) return;
    $lines = [];
    foreach ($settings['questions'] as $q) {
        $v = trim((string) ($answers[$q['key']] ?? ''));
        if ($v !== '') $lines[] = $q['question'] . "\n  " . $v;
    }
    $first = trim((string) (reset($answers) ?: ''));
    $transcript = [];
    foreach (conversation_messages((int) $conv['id']) as $m) {
        $transcript[] = ($m['role'] === 'user' ? 'Látogató: ' : $settings['bot']['name'] . ': ') . $m['content'];
    }
    $body = "Új érdeklődő a chatben — minden kötelező kérdésre válaszolt.\n\n" . implode("\n\n", $lines)
        . "\n\nAdmin felület:\n" . app_base_url() . "/admin/#c" . $conv['id']
        . "\n\n--- Beszélgetés ---\n" . implode("\n\n", $transcript);
    send_app_email(app_config() ?? [], $to, 'Új érdeklődő: ' . mb_substr($first, 0, 60) . ' – ' . $settings['business']['name'], $body);
}

// ---------------------------------------------------------------------------
// Beállítások ellenőrzése (admin mentés)
// ---------------------------------------------------------------------------

function sanitize_group(string $group, $value): array {
    if (!is_array($value)) throw new AppError('Érvénytelen adat.');
    $defaults = default_settings()[$group] ?? null;
    if ($defaults === null) throw new AppError("Ismeretlen beállítás: $group");

    switch ($group) {
        case 'business':
            $out = [];
            foreach ($defaults as $k => $_) $out[$k] = mb_substr(trim((string) ($value[$k] ?? '')), 0, 250);
            if ($out['name'] === '') throw new AppError('A cég neve nem lehet üres.');
            if ($out['email'] !== '' && !filter_var($out['email'], FILTER_VALIDATE_EMAIL)) throw new AppError('Érvénytelen email cím.');
            return $out;

        case 'bot':
            $limits = ['name' => 60, 'greeting' => 500, 'tone' => 500, 'knowledge' => 30000, 'instructions' => 5000, 'completion' => 1000];
            $out = [];
            foreach ($limits as $k => $max) $out[$k] = mb_substr(trim((string) ($value[$k] ?? '')), 0, $max);
            if ($out['name'] === '' || $out['greeting'] === '') throw new AppError('A bot neve és a köszöntés nem lehet üres.');
            return $out;

        case 'questions':
            $out = [];
            $keys = [];
            foreach ($value as $q) {
                $question = mb_substr(trim((string) ($q['question'] ?? '')), 0, 300);
                if ($question === '') continue;
                $key = preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($q['key'] ?? '')));
                if ($key === '' || isset($keys[$key])) $key = 'q' . (count($out) + 1) . '_' . substr(md5($question), 0, 4);
                $keys[$key] = true;
                $out[] = ['key' => $key, 'question' => $question, 'hint' => mb_substr(trim((string) ($q['hint'] ?? '')), 0, 300), 'required' => !empty($q['required'])];
            }
            if (count($out) > 20) throw new AppError('Legfeljebb 20 kérdés adható meg.');
            return $out;

        case 'theme':
            $primary = (string) ($value['primary'] ?? '');
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $primary)) throw new AppError('A szín formátuma: #RRGGBB');
            return ['primary' => strtolower($primary)];

        case 'notify':
            $email = trim((string) ($value['admin_email'] ?? ''));
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new AppError('Érvénytelen értesítési email cím.');
            return ['admin_email' => $email, 'send_admin' => !empty($value['send_admin'])];

        case 'ai':
            $model = (string) ($value['model'] ?? '');
            if (!array_key_exists($model, AI_MODELS)) throw new AppError('Ismeretlen modell.');
            return [
                'model'             => $model,
                'max_user_messages' => max(5, min(100, (int) ($value['max_user_messages'] ?? 30))),
                'daily_limit'       => max(1, min(5000, (int) ($value['daily_limit'] ?? 300))),
            ];
    }
    throw new AppError("Ismeretlen beállítás: $group");
}

// ---------------------------------------------------------------------------
// Útvonalak
// ---------------------------------------------------------------------------

try {
    if (!app_installed()) throw new AppError('A rendszer még nincs telepítve (install.php).', 503);
    start_session();

    $route = (string) ($_GET['r'] ?? '');
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'POST' && ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'fetch') throw new AppError('Érvénytelen kérés.', 400);
    $isPost = $method === 'POST';
    $settings = get_settings();

    if (strpos($route, 'admin/') === 0 && !current_admin()) throw new AppError('Bejelentkezés szükséges.', 401);
    $needPost = ['start', 'message', 'login', 'logout', 'forgot', 'reset'];
    if ((in_array($route, $needPost, true) || preg_match('#^admin/.+/(save|delete)$|^admin/(password)$#', $route)) && !$isPost) {
        throw new AppError('Method not allowed', 405);
    }

    switch ($route) {
        // ------------------------------------------------------------- látogató
        case 'config':
            respond(['settings' => public_settings()]);

        case 'start': {
            rate_limit('start:' . client_ip(), 20, 3600);
            $today = q_one('SELECT COUNT(*) AS n FROM {conversations} WHERE created_at >= ?', [date('Y-m-d 00:00:00')]);
            if ((int) $today['n'] >= (int) $settings['ai']['daily_limit']) {
                throw new AppError('A chat mára elérte a napi keretét. Kérlek, keress minket telefonon vagy emailben.', 429);
            }
            $token = bin2hex(random_bytes(16));
            q_exec('INSERT INTO {conversations} (token, answers, source_url, ip) VALUES (?, ?, ?, ?)',
                [$token, '{}', str_in(input(), 'source', 500), client_ip()]);
            respond(['token' => $token, 'greeting' => $settings['bot']['greeting'], 'progress' => progress($settings, [])]);
        }

        case 'conversation': {
            $c = find_conversation((string) ($_GET['token'] ?? ''));
            $answers = json_decode((string) $c['answers'], true) ?: [];
            respond([
                'greeting' => $settings['bot']['greeting'],
                'messages' => array_map(fn ($m) => ['role' => $m['role'], 'content' => $m['content']], conversation_messages((int) $c['id'])),
                'progress' => progress($settings, $answers),
                'status'   => $c['status'],
            ]);
        }

        case 'message': {
            $in = input();
            $c = find_conversation((string) ($in['token'] ?? ''));
            $text = str_in($in, 'text', 1500);
            if ($text === '') throw new AppError('Üres üzenet.');
            rate_limit('msg:' . client_ip(), 60, 3600);
            if ((int) $c['user_messages'] >= (int) $settings['ai']['max_user_messages']) {
                throw new AppError('Ez a beszélgetés elérte a maximális hosszt. Kérlek, keress minket közvetlenül, vagy kezdj új beszélgetést.', 429);
            }

            q_exec('INSERT INTO {messages} (conversation_id, role, content) VALUES (?, ?, ?)', [(int) $c['id'], 'user', $text]);
            q_exec('UPDATE {conversations} SET user_messages = user_messages + 1, seen = 0, updated_at = NOW() WHERE id = ?', [(int) $c['id']]);

            $answers = json_decode((string) $c['answers'], true) ?: [];
            $history = q_all('SELECT role, content FROM {messages} WHERE conversation_id = ? ORDER BY id', [(int) $c['id']]);
            // Hiba esetén a látogató üzenete megmarad (a bot a következő körben látja),
            // de nincs rögzített válasz; két egymást követő user üzenet az API-nak rendben van.
            $result = run_bot_turn($settings, $history, $answers);

            $u = $result['usage'];
            q_exec(
                'INSERT INTO {messages} (conversation_id, role, content, model, input_tokens, output_tokens, cache_read_tokens, cache_write_tokens) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [(int) $c['id'], 'assistant', $result['text'], $result['model'], $u['input'], $u['output'], $u['cache_read'], $u['cache_write']]
            );

            $answers = $result['answers'];
            $complete = !missing_questions($settings, $answers) && required_questions($settings);
            q_exec('UPDATE {conversations} SET answers = ?, status = ?, updated_at = NOW() WHERE id = ?',
                [json_encode($answers, JSON_UNESCAPED_UNICODE), $complete ? 'complete' : 'open', (int) $c['id']]);
            if ($complete && empty($c['notified_at'])) {
                notify_complete($settings, $c, $answers);
                q_exec('UPDATE {conversations} SET notified_at = NOW() WHERE id = ?', [(int) $c['id']]);
            }
            respond(['reply' => $result['text'], 'progress' => progress($settings, $answers), 'complete' => (bool) $complete]);
        }

        // ------------------------------------------------------------- belépés
        case 'login': {
            rate_limit('login:' . client_ip(), 10, 900);
            $in = input();
            $admin = q_one('SELECT * FROM {admins} WHERE email = ?', [str_in($in, 'email', 190)]);
            if (!$admin || !password_verify((string) ($in['password'] ?? ''), (string) $admin['password_hash'])) {
                throw new AppError('Hibás email cím vagy jelszó.', 401);
            }
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int) $admin['id'];
            respond(['admin' => ['id' => (int) $admin['id'], 'name' => $admin['name'], 'email' => $admin['email']]]);
        }

        case 'logout':
            $_SESSION = [];
            session_destroy();
            respond(['ok' => true]);

        case 'me':
            respond(['admin' => current_admin()]);

        case 'forgot': {
            rate_limit('forgot:' . client_ip(), 5, 3600);
            $admin = q_one('SELECT id, name, email FROM {admins} WHERE email = ?', [str_in(input(), 'email', 190)]);
            if ($admin) {
                $token = bin2hex(random_bytes(32));
                q_exec('DELETE FROM {password_resets} WHERE admin_id = ? OR expires_at < NOW()', [(int) $admin['id']]);
                q_exec('INSERT INTO {password_resets} (admin_id, token_hash, expires_at) VALUES (?, ?, ?)',
                    [(int) $admin['id'], hash('sha256', $token), date('Y-m-d H:i:s', time() + 3600)]);
                $biz = $settings['business']['name'];
                send_app_email(app_config() ?? [], $admin['email'], "Jelszó visszaállítása – $biz chatbot",
                    "Kedves {$admin['name']}!\n\nJelszó-visszaállítást kértek a(z) $biz chatbot admin felületéhez. Új jelszót ezen a linken állíthatsz be (1 óráig érvényes):\n\n"
                    . app_base_url() . "/admin/?reset=$token\n\nHa nem te kérted, hagyd figyelmen kívül ezt a levelet — a jelszavad nem változik.");
            }
            respond(['ok' => true]);
        }

        case 'reset': {
            rate_limit('reset:' . client_ip(), 10, 3600);
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

        // ------------------------------------------------------------- admin
        case 'admin/conversations': {
            $filter = (string) ($_GET['status'] ?? '');
            $where = in_array($filter, ['open', 'complete'], true) ? 'WHERE status = ?' : '';
            $params = $where ? [$filter] : [];
            $rows = q_all("SELECT id, status, answers, user_messages, source_url, seen, created_at, updated_at FROM {conversations} $where ORDER BY updated_at DESC LIMIT 200", $params);
            foreach ($rows as &$r) $r['answers'] = json_decode((string) $r['answers'], true) ?: new stdClass();
            unset($r);
            respond(['conversations' => $rows]);
        }

        case 'admin/conversation': {
            $id = (int) ($_GET['id'] ?? 0);
            $c = q_one('SELECT id, status, answers, user_messages, source_url, created_at, updated_at FROM {conversations} WHERE id = ?', [$id]);
            if (!$c) throw new AppError('A beszélgetés nem található.', 404);
            q_exec('UPDATE {conversations} SET seen = 1 WHERE id = ?', [$id]);
            $c['answers'] = json_decode((string) $c['answers'], true) ?: new stdClass();
            respond(['conversation' => $c, 'messages' => conversation_messages($id)]);
        }

        case 'admin/conversation/delete': {
            $id = (int) (input()['id'] ?? 0);
            q_exec('DELETE FROM {messages} WHERE conversation_id = ?', [$id]);
            q_exec('DELETE FROM {conversations} WHERE id = ?', [$id]);
            respond(['ok' => true]);
        }

        case 'admin/export': {
            $rows = q_all('SELECT id, status, answers, user_messages, source_url, created_at FROM {conversations} ORDER BY id DESC LIMIT 5000');
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="chatbot-valaszok-' . date('Y-m-d') . '.csv"');
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM, hogy az Excel jól nyissa meg
            $qs = $settings['questions'];
            fputcsv($out, array_merge(['Azonosító', 'Dátum', 'Állapot', 'Üzenetek'], array_column($qs, 'question'), ['Forrás oldal']), ';');
            foreach ($rows as $r) {
                $a = json_decode((string) $r['answers'], true) ?: [];
                fputcsv($out, array_merge(
                    [$r['id'], $r['created_at'], $r['status'] === 'complete' ? 'Teljes' : 'Folyamatban', $r['user_messages']],
                    array_map(fn ($q) => (string) ($a[$q['key']] ?? ''), $qs),
                    [$r['source_url']]
                ), ';');
            }
            fclose($out);
            exit;
        }

        case 'admin/usage': {
            $month = date('Y-m-01 00:00:00');
            $rows = q_all('SELECT model, SUM(input_tokens) AS i, SUM(output_tokens) AS o, SUM(cache_read_tokens) AS cr, SUM(cache_write_tokens) AS cw, COUNT(*) AS n
                           FROM {messages} WHERE role = ? AND created_at >= ? GROUP BY model', ['assistant', $month]);
            $usd = 0.0;
            foreach ($rows as $r) $usd += usage_cost_usd((string) $r['model'], (int) $r['i'], (int) $r['o'], (int) $r['cr'], (int) $r['cw']);
            $convs = q_one('SELECT COUNT(*) AS n, SUM(status = ?) AS done FROM {conversations} WHERE created_at >= ?', ['complete', $month]);
            respond(['month' => substr($month, 0, 7), 'usd' => round($usd, 2), 'replies' => array_sum(array_column($rows, 'n')),
                'conversations' => (int) $convs['n'], 'complete' => (int) $convs['done']]);
        }

        case 'admin/settings':
            if ($isPost) {
                $in = input();
                $group = (string) ($in['group'] ?? '');
                save_setting_group($group, sanitize_group($group, $in['value'] ?? null));
                respond(['ok' => true]);
            }
            respond([
                'settings' => get_settings(),
                'models'   => AI_MODELS,
                'admins'   => q_all('SELECT id, name, email, created_at FROM {admins} ORDER BY id'),
                'me'       => current_admin(),
                'base_url' => app_base_url(),
                'api_key'  => !empty(app_config()['anthropic']['api_key']),
            ]);

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
    error_log('[chatbot] ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    respond(['error' => 'Váratlan hiba történt.'], 500);
}
