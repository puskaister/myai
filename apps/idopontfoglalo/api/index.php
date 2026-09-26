<?php
// Egyetlen belépési pont az összes API-híváshoz: api/?r=<útvonal>
// Nyilvános útvonalak: config, days, slots, book, booking, cancel, ics
// Admin útvonalak (bejelentkezés után): login, logout, me, admin/*
declare(strict_types=1);

require __DIR__ . '/lib/core.php';
require __DIR__ . '/lib/slots.php';
require __DIR__ . '/lib/notify.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function respond($data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function input(): array {
    if (!empty($_POST)) return $_POST;
    $data = json_decode((string) file_get_contents('php://input'), true);
    return is_array($data) ? $data : [];
}

function str_in(array $in, string $key, int $max = 500): string {
    $v = trim((string) ($in[$key] ?? ''));
    return mb_substr($v, 0, $max);
}

function client_ip(): string {
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

// Egyszerű, adatbázis-alapú számláló: $max kérés / $window másodperc kulcsonként.
function rate_limit(string $key, int $max, int $window): void {
    q_exec('DELETE FROM rate_limits WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
    $row = q_one('SELECT COUNT(*) AS n FROM rate_limits WHERE k = ? AND created_at > ?', [$key, date('Y-m-d H:i:s', time() - $window)]);
    if ((int) ($row['n'] ?? 0) >= $max) throw new AppError('Túl sok próbálkozás, kérjük, próbáld újra később.', 429);
    q_exec('INSERT INTO rate_limits (k) VALUES (?)', [$key]);
}

function start_session(): void {
    $config = app_config();
    session_name($config['session_name'] ?? 'idopont_session');
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
    return q_one('SELECT id, name, email FROM admins WHERE id = ?', [(int) $_SESSION['admin_id']]);
}

function require_admin(): array {
    $admin = current_admin();
    if (!$admin) throw new AppError('Bejelentkezés szükséges.', 401);
    return $admin;
}

function find_service(int $id, bool $activeOnly = true): array {
    $s = q_one('SELECT * FROM services WHERE id = ?' . ($activeOnly ? ' AND active = 1' : ''), [$id]);
    if (!$s) throw new AppError('Ismeretlen szolgáltatás.');
    return $s;
}

function find_booking_by_token(string $token): array {
    if (!preg_match('/^[a-f0-9]{32}$/', $token)) throw new AppError('Érvénytelen foglalási azonosító.', 404);
    $b = q_one('SELECT * FROM bookings WHERE token = ?', [$token]);
    if (!$b) throw new AppError('A foglalás nem található.', 404);
    return $b;
}

function find_booking(int $id): array {
    $b = q_one('SELECT * FROM bookings WHERE id = ?', [$id]);
    if (!$b) throw new AppError('A foglalás nem található.', 404);
    return $b;
}

function public_booking(array $b): array {
    $settings = get_settings();
    $start = strtotime((string) $b['start_at']);
    $canCancel = in_array($b['status'], ACTIVE_STATUSES, true)
        && $settings['rules']['customer_cancel']
        && $start - time() >= (int) $settings['rules']['cancel_hours'] * 3600;
    return [
        'service_name' => $b['service_name'],
        'start_at'     => $b['start_at'],
        'end_at'       => $b['end_at'],
        'when'         => booking_when($b),
        'status'       => $b['status'],
        'name'         => $b['name'],
        'can_cancel'   => $canCancel,
    ];
}

// Ügyfél- és admin-foglalásnál közös mezőellenőrzés.
function booking_fields_from_input(array $in, array $settings, bool $asAdmin): array {
    $name = str_in($in, 'name', 150);
    $email = str_in($in, 'email', 190);
    $phone = str_in($in, 'phone', 60);
    $note = str_in($in, 'note', 2000);
    $errors = [];

    if (mb_strlen($name) < 2) $errors['name'] = 'Add meg a neved.';
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Érvénytelen email cím.';
    if (!$asAdmin && $settings['contact']['email_required'] && $email === '') $errors['email'] = 'Add meg az email címed.';
    if ($phone !== '' && !preg_match('/^[0-9 +()\/-]{6,}$/', $phone)) $errors['phone'] = 'Érvénytelen telefonszám.';
    if (!$asAdmin && $settings['contact']['phone_required'] && $phone === '') $errors['phone'] = 'Add meg a telefonszámod.';

    $given = is_array($in['fields'] ?? null) ? $in['fields'] : [];
    $fields = [];
    foreach ($settings['fields'] as $f) {
        $v = mb_substr(trim((string) ($given[$f['key']] ?? '')), 0, 500);
        if ($v === '' && !empty($f['required']) && !$asAdmin) $errors['fields.' . $f['key']] = 'Kötelező mező: ' . $f['label'];
        if ($v !== '' && ($f['type'] ?? 'text') === 'select' && !in_array($v, $f['options'] ?? [], true)) {
            $errors['fields.' . $f['key']] = 'Válassz a listából: ' . $f['label'];
        }
        if ($v !== '') $fields[$f['key']] = $v;
    }

    if ($errors) {
        http_response_code(422);
        echo json_encode(['error' => reset($errors), 'fields' => $errors], JSON_UNESCAPED_UNICODE);
        exit;
    }
    return [$name, $email, $phone, $note, json_encode($fields, JSON_UNESCAPED_UNICODE)];
}

// Foglalás-ütközés elleni zár: két egyidejű kérés ne kaphassa meg ugyanazt
// az utolsó szabad helyet.
function with_booking_lock(callable $fn) {
    $name = 'idopont_' . substr(md5((string) (app_config()['db']['name'] ?? '')), 0, 16);
    $row = q_one('SELECT GET_LOCK(?, 10) AS l', [$name]);
    if ((int) ($row['l'] ?? 0) !== 1) throw new AppError('A rendszer most foglalt, kérjük, próbáld újra.', 503);
    try {
        return $fn();
    } finally {
        q_one('SELECT RELEASE_LOCK(?) AS r', [$name]);
    }
}

// ---------------------------------------------------------------------------
// Beállítások ellenőrzése csoportonként (admin mentésnél)
// ---------------------------------------------------------------------------

function valid_time(string $t): bool {
    return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t);
}

function sanitize_group(string $group, $value): array {
    if (!is_array($value)) throw new AppError('Érvénytelen adat.');
    $defaults = default_settings()[$group] ?? null;
    if ($defaults === null) throw new AppError("Ismeretlen beállítás: $group");

    switch ($group) {
        case 'business':
        case 'texts':
            $out = [];
            foreach ($defaults as $k => $_) $out[$k] = mb_substr(trim((string) ($value[$k] ?? '')), 0, $group === 'texts' ? 3000 : 250);
            if ($group === 'business' && $out['name'] === '') throw new AppError('A cég neve nem lehet üres.');
            if ($group === 'business' && $out['email'] !== '' && !filter_var($out['email'], FILTER_VALIDATE_EMAIL)) throw new AppError('Érvénytelen email cím.');
            return $out;

        case 'theme':
            $primary = (string) ($value['primary'] ?? '');
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $primary)) throw new AppError('A szín formátuma: #RRGGBB');
            return ['primary' => strtolower($primary), 'logo' => get_settings()['theme']['logo']];

        case 'rules':
            $approval = ($value['approval'] ?? 'auto') === 'manual' ? 'manual' : 'auto';
            return [
                'slot_step'       => max(5, min(240, (int) ($value['slot_step'] ?? 30))),
                'capacity'        => max(1, min(50, (int) ($value['capacity'] ?? 1))),
                'lead_hours'      => max(0, min(720, (float) ($value['lead_hours'] ?? 2))),
                'max_days'        => max(1, min(365, (int) ($value['max_days'] ?? 60))),
                'approval'        => $approval,
                'customer_cancel' => !empty($value['customer_cancel']),
                'cancel_hours'    => max(0, min(720, (int) ($value['cancel_hours'] ?? 24))),
            ];

        case 'hours':
            $out = [];
            foreach (['0', '1', '2', '3', '4', '5', '6'] as $d) {
                $out[$d] = [];
                foreach ((array) ($value[$d] ?? []) as $range) {
                    if (!is_array($range) || count($range) !== 2) continue;
                    [$from, $to] = [(string) $range[0], (string) $range[1]];
                    if (!valid_time($from) || !valid_time($to) || $to <= $from) {
                        throw new AppError('Hibás nyitvatartási sáv: ' . HU_DAYS[(int) $d] . " $from–$to");
                    }
                    $out[$d][] = [$from, $to];
                }
                usort($out[$d], fn ($a, $b) => strcmp($a[0], $b[0]));
            }
            return $out;

        case 'closures':
            $out = [];
            foreach ($value as $c) {
                $from = (string) ($c['from'] ?? '');
                $to = (string) ($c['to'] ?? '') ?: $from;
                if (!valid_date($from) || !valid_date($to) || $to < $from) throw new AppError('Hibás zárva tartási időszak.');
                $out[] = ['from' => $from, 'to' => $to, 'reason' => mb_substr(trim((string) ($c['reason'] ?? '')), 0, 120)];
            }
            usort($out, fn ($a, $b) => strcmp($a['from'], $b['from']));
            return $out;

        case 'fields':
            $out = [];
            $keys = [];
            foreach ($value as $f) {
                $label = mb_substr(trim((string) ($f['label'] ?? '')), 0, 120);
                if ($label === '') continue;
                $key = preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($f['key'] ?? '')));
                if ($key === '' || isset($keys[$key])) $key = 'f' . substr(md5($label . count($out)), 0, 8);
                $keys[$key] = true;
                $type = in_array($f['type'] ?? '', ['text', 'textarea', 'select'], true) ? $f['type'] : 'text';
                $options = array_values(array_filter(array_map(fn ($o) => mb_substr(trim((string) $o), 0, 120), (array) ($f['options'] ?? []))));
                if ($type === 'select' && !$options) throw new AppError("A(z) \"$label\" listához adj meg választási lehetőségeket.");
                $out[] = ['key' => $key, 'label' => $label, 'type' => $type, 'required' => !empty($f['required']), 'options' => $options];
            }
            return $out;

        case 'contact':
            return [
                'phone_required' => !empty($value['phone_required']),
                'email_required' => !empty($value['email_required']),
            ];

        case 'notify':
            $email = trim((string) ($value['admin_email'] ?? ''));
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new AppError('Érvénytelen értesítési email cím.');
            return [
                'admin_email'   => $email,
                'send_customer' => !empty($value['send_customer']),
                'send_admin'    => !empty($value['send_admin']),
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
    // CSRF-védelem: minden POST-nak a saját frontendünk egyedi fejlécét kell
    // hoznia — egy idegen oldalról küldött űrlap ezt nem tudja beállítani.
    if ($method === 'POST' && ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'fetch') {
        throw new AppError('Érvénytelen kérés.', 400);
    }
    $isPost = $method === 'POST';
    $settings = get_settings();

    if (strpos($route, 'admin/') === 0) {
        require_admin();
    }

    switch ($route) {
        // ------------------------------------------------------------- nyilvános
        case 'config':
            $services = q_all('SELECT id, name, description, duration_min, price FROM services WHERE active = 1 ORDER BY sort, id');
            respond(['settings' => public_settings(), 'services' => $services]);

        case 'days': {
            $service = find_service((int) ($_GET['service_id'] ?? 0));
            $month = (string) ($_GET['month'] ?? date('Y-m'));
            if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) throw new AppError('Hibás hónap.');
            $first = "$month-01";
            $last = date('Y-m-t', strtotime($first));
            $busy = load_busy($first, $last);
            $days = [];
            for ($d = $first; $d <= $last; $d = date('Y-m-d', strtotime("$d +1 day"))) {
                $days[$d] = count(available_slots($settings, (int) $service['duration_min'], $d, $busy)) > 0;
            }
            respond(['days' => $days]);
        }

        case 'slots': {
            $service = find_service((int) ($_GET['service_id'] ?? 0));
            $date = (string) ($_GET['date'] ?? '');
            respond(['slots' => available_slots($settings, (int) $service['duration_min'], $date)]);
        }

        case 'book': {
            if (!$isPost) throw new AppError('Method not allowed', 405);
            $in = input();
            if (trim((string) ($in['website'] ?? '')) !== '') respond(['ok' => true]); // honeypot: robot
            rate_limit('book:' . client_ip(), 6, 3600);

            $service = find_service((int) ($in['service_id'] ?? 0));
            $date = (string) ($in['date'] ?? '');
            $time = (string) ($in['time'] ?? '');
            [$name, $email, $phone, $note, $fieldsJson] = booking_fields_from_input($in, $settings, false);

            $booking = with_booking_lock(function () use ($settings, $service, $date, $time, $name, $email, $phone, $note, $fieldsJson) {
                if (!in_array($time, available_slots($settings, (int) $service['duration_min'], $date), true)) {
                    throw new AppError('Ez az időpont közben betelt. Kérjük, válassz másikat.', 409);
                }
                $start = "$date $time:00";
                $end = date('Y-m-d H:i:s', strtotime($start) + (int) $service['duration_min'] * 60);
                $status = $settings['rules']['approval'] === 'manual' ? 'pending' : 'confirmed';
                $token = bin2hex(random_bytes(16));
                q_exec(
                    'INSERT INTO bookings (service_id, service_name, start_at, end_at, status, name, email, phone, note, fields, token, source, ip)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [(int) $service['id'], $service['name'], $start, $end, $status, $name, $email, $phone, $note, $fieldsJson, $token, 'online', client_ip()]
                );
                return find_booking((int) db()->insert_id);
            });

            notify_booking($booking, 'created');
            respond(['ok' => true, 'token' => $booking['token'], 'booking' => public_booking($booking)]);
        }

        case 'booking':
            respond(['booking' => public_booking(find_booking_by_token((string) ($_GET['token'] ?? '')))]);

        case 'cancel': {
            if (!$isPost) throw new AppError('Method not allowed', 405);
            $in = input();
            $b = find_booking_by_token((string) ($in['token'] ?? ''));
            if (!public_booking($b)['can_cancel']) throw new AppError('Ez a foglalás már nem mondható le online. Kérjük, hívj minket telefonon.', 409);
            q_exec("UPDATE bookings SET status = 'cancelled', updated_at = NOW() WHERE id = ?", [(int) $b['id']]);
            $b = find_booking((int) $b['id']);
            notify_booking($b, 'cancelled_customer');
            respond(['ok' => true, 'booking' => public_booking($b)]);
        }

        case 'ics': {
            $b = find_booking_by_token((string) ($_GET['token'] ?? ''));
            $fmt = fn (string $dt) => gmdate('Ymd\THis\Z', strtotime($dt));
            $esc = fn (string $s) => addcslashes(str_replace(["\r\n", "\n"], '\n', $s), ',;\\');
            $biz = $settings['business'];
            $lines = [
                'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//idopontfoglalo//HU', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH',
                'BEGIN:VEVENT',
                'UID:' . $b['token'] . '@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'),
                'DTSTAMP:' . gmdate('Ymd\THis\Z'),
                'DTSTART:' . $fmt((string) $b['start_at']),
                'DTEND:' . $fmt((string) $b['end_at']),
                'SUMMARY:' . $esc($b['service_name'] . ' – ' . $biz['name']),
                'DESCRIPTION:' . $esc(booking_manage_url($b)),
            ];
            if ($biz['address'] !== '') $lines[] = 'LOCATION:' . $esc($biz['address']);
            $lines = array_merge($lines, ['END:VEVENT', 'END:VCALENDAR']);
            header('Content-Type: text/calendar; charset=utf-8');
            header('Content-Disposition: attachment; filename="foglalas.ics"');
            echo implode("\r\n", $lines) . "\r\n";
            exit;
        }

        // ------------------------------------------------------------- belépés
        case 'login': {
            if (!$isPost) throw new AppError('Method not allowed', 405);
            rate_limit('login:' . client_ip(), 10, 900);
            $in = input();
            $admin = q_one('SELECT * FROM admins WHERE email = ?', [str_in($in, 'email', 190)]);
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

        // ------------------------------------------------------------- admin
        case 'admin/bookings': {
            $from = (string) ($_GET['from'] ?? date('Y-m-d'));
            $to = (string) ($_GET['to'] ?? $from);
            if (!valid_date($from) || !valid_date($to)) throw new AppError('Hibás dátum.');
            $rows = q_all(
                'SELECT id, service_id, service_name, start_at, end_at, status, name, email, phone, note, fields, admin_note, token, source, created_at
                 FROM bookings WHERE start_at >= ? AND start_at <= ? ORDER BY start_at, id',
                [$from . ' 00:00:00', $to . ' 23:59:59']
            );
            foreach ($rows as &$r) $r['fields'] = json_decode((string) $r['fields'], true) ?: new stdClass();
            unset($r);
            $pending = q_all(
                "SELECT id, service_name, start_at, end_at, name, phone FROM bookings
                 WHERE status = 'pending' AND start_at >= ? ORDER BY start_at LIMIT 50",
                [date('Y-m-d 00:00:00')]
            );
            respond(['bookings' => $rows, 'pending' => $pending]);
        }

        case 'admin/booking': {
            $b = find_booking((int) ($_GET['id'] ?? 0));
            $b['fields'] = json_decode((string) $b['fields'], true) ?: new stdClass();
            unset($b['ip']);
            respond(['booking' => $b]);
        }

        case 'admin/search': {
            $term = '%' . str_in($_GET, 'q', 100) . '%';
            $rows = q_all(
                'SELECT id, service_name, start_at, end_at, status, name, email, phone FROM bookings
                 WHERE name LIKE ? OR email LIKE ? OR phone LIKE ? OR fields LIKE ? ORDER BY start_at DESC LIMIT 50',
                [$term, $term, $term, $term]
            );
            respond(['bookings' => $rows]);
        }

        case 'admin/slots': {
            $service = find_service((int) ($_GET['service_id'] ?? 0), false);
            $date = (string) ($_GET['date'] ?? '');
            if (!valid_date($date)) throw new AppError('Hibás dátum.');
            $exclude = isset($_GET['exclude']) ? (int) $_GET['exclude'] : null;
            respond(['slots' => available_slots($settings, (int) $service['duration_min'], $date, load_busy($date, $date, $exclude), true)]);
        }

        case 'admin/booking/status': {
            if (!$isPost) throw new AppError('Method not allowed', 405);
            $in = input();
            $b = find_booking((int) ($in['id'] ?? 0));
            $status = (string) ($in['status'] ?? '');
            if (!in_array($status, ['pending', 'confirmed', 'rejected', 'cancelled', 'completed', 'noshow'], true)) throw new AppError('Ismeretlen állapot.');
            $adminNote = array_key_exists('admin_note', $in) ? str_in($in, 'admin_note', 2000) : (string) $b['admin_note'];
            q_exec('UPDATE bookings SET status = ?, admin_note = ?, updated_at = NOW() WHERE id = ?', [$status, $adminNote, (int) $b['id']]);
            $updated = find_booking((int) $b['id']);
            if (!empty($in['notify']) && $status !== $b['status']) {
                $event = ['confirmed' => 'confirmed', 'rejected' => 'rejected', 'cancelled' => 'cancelled_admin'][$status] ?? null;
                if ($event) notify_booking($updated, $event);
            }
            respond(['ok' => true]);
        }

        case 'admin/booking/save': {
            if (!$isPost) throw new AppError('Method not allowed', 405);
            $in = input();
            $id = (int) ($in['id'] ?? 0);
            $existing = $id ? find_booking($id) : null;
            $service = find_service((int) ($in['service_id'] ?? 0), false);
            $date = (string) ($in['date'] ?? '');
            $time = (string) ($in['time'] ?? '');
            if (!valid_date($date) || !valid_time($time)) throw new AppError('Add meg a dátumot és az időpontot.');
            $duration = max(5, (int) ($in['duration_min'] ?? $service['duration_min']));
            [$name, $email, $phone, $note, $fieldsJson] = booking_fields_from_input($in, $settings, true);
            $adminNote = str_in($in, 'admin_note', 2000);
            $force = !empty($in['force']);

            $booking = with_booking_lock(function () use ($existing, $service, $date, $time, $duration, $name, $email, $phone, $note, $fieldsJson, $adminNote, $force, $settings) {
                $start = "$date $time:00";
                $startTs = strtotime($start);
                $end = date('Y-m-d H:i:s', $startTs + $duration * 60);
                $busy = load_busy($date, $date, $existing ? (int) $existing['id'] : null)[$date] ?? [];
                if (!$force && max_concurrent($busy, $startTs, $startTs + $duration * 60) >= max(1, (int) $settings['rules']['capacity'])) {
                    throw new AppError('Ebben az időpontban már nincs szabad hely. Mentés mégis?', 409);
                }
                if ($existing) {
                    q_exec(
                        'UPDATE bookings SET service_id = ?, service_name = ?, start_at = ?, end_at = ?, name = ?, email = ?, phone = ?, note = ?, fields = ?, admin_note = ?, updated_at = NOW() WHERE id = ?',
                        [(int) $service['id'], $service['name'], $start, $end, $name, $email, $phone, $note, $fieldsJson, $adminNote, (int) $existing['id']]
                    );
                    return find_booking((int) $existing['id']);
                }
                q_exec(
                    'INSERT INTO bookings (service_id, service_name, start_at, end_at, status, name, email, phone, note, fields, admin_note, token, source, ip)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [(int) $service['id'], $service['name'], $start, $end, 'confirmed', $name, $email, $phone, $note, $fieldsJson, $adminNote, bin2hex(random_bytes(16)), 'admin', client_ip()]
                );
                return find_booking((int) db()->insert_id);
            });

            if (!empty($in['notify'])) notify_booking($booking, $existing ? 'confirmed' : 'created');
            respond(['ok' => true, 'id' => (int) $booking['id']]);
        }

        case 'admin/booking/delete':
            if (!$isPost) throw new AppError('Method not allowed', 405);
            q_exec('DELETE FROM bookings WHERE id = ?', [(int) (input()['id'] ?? 0)]);
            respond(['ok' => true]);

        case 'admin/settings':
            if ($isPost) {
                $in = input();
                $group = (string) ($in['group'] ?? '');
                save_setting_group($group, sanitize_group($group, $in['value'] ?? null));
                respond(['ok' => true]);
            }
            respond([
                'settings' => get_settings(),
                'services' => q_all('SELECT * FROM services ORDER BY sort, id'),
                'admins'   => q_all('SELECT id, name, email, created_at FROM admins ORDER BY id'),
                'me'       => current_admin(),
                'base_url' => app_base_url(),
            ]);

        case 'admin/services/save': {
            if (!$isPost) throw new AppError('Method not allowed', 405);
            $in = input();
            $name = str_in($in, 'name', 150);
            if ($name === '') throw new AppError('A szolgáltatás neve nem lehet üres.');
            $duration = max(5, min(1440, (int) ($in['duration_min'] ?? 30)));
            $values = [$name, str_in($in, 'description', 2000), $duration, str_in($in, 'price', 60), !empty($in['active']) ? 1 : 0];
            if (!empty($in['id'])) {
                q_exec('UPDATE services SET name = ?, description = ?, duration_min = ?, price = ?, active = ? WHERE id = ?', array_merge($values, [(int) $in['id']]));
            } else {
                $max = q_one('SELECT COALESCE(MAX(sort), -1) + 1 AS s FROM services');
                q_exec('INSERT INTO services (name, description, duration_min, price, active, sort) VALUES (?, ?, ?, ?, ?, ?)', array_merge($values, [(int) $max['s']]));
            }
            respond(['ok' => true]);
        }

        case 'admin/services/delete':
            if (!$isPost) throw new AppError('Method not allowed', 405);
            q_exec('DELETE FROM services WHERE id = ?', [(int) (input()['id'] ?? 0)]);
            respond(['ok' => true]);

        case 'admin/services/order':
            if (!$isPost) throw new AppError('Method not allowed', 405);
            foreach (array_values((array) (input()['ids'] ?? [])) as $i => $sid) {
                q_exec('UPDATE services SET sort = ? WHERE id = ?', [$i, (int) $sid]);
            }
            respond(['ok' => true]);

        case 'admin/logo': {
            if (!$isPost) throw new AppError('Method not allowed', 405);
            $theme = $settings['theme'];
            $old = $theme['logo'];
            if (!empty($_POST['remove'])) {
                $theme['logo'] = '';
            } else {
                $file = $_FILES['logo'] ?? null;
                if (!$file || $file['error'] !== UPLOAD_ERR_OK) throw new AppError('Nem érkezett fájl.');
                if ($file['size'] > 2 * 1024 * 1024) throw new AppError('A logó legfeljebb 2 MB lehet.');
                $info = @getimagesize($file['tmp_name']);
                $ext = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp'][$info[2] ?? 0] ?? null;
                if (!$ext) throw new AppError('Csak PNG, JPG vagy WEBP kép tölthető fel.');
                $name = 'logo-' . bin2hex(random_bytes(6)) . '.' . $ext;
                if (!move_uploaded_file($file['tmp_name'], APP_ROOT . '/uploads/' . $name)) {
                    throw new AppError('Nem sikerült menteni a képet (ellenőrizd az uploads/ mappa írási jogát).', 500);
                }
                $theme['logo'] = $name;
            }
            if ($old !== '' && $old !== $theme['logo']) @unlink(APP_ROOT . '/uploads/' . basename($old));
            foreach (glob(APP_ROOT . '/uploads/icon-*.png') ?: [] as $cached) @unlink($cached);
            save_setting_group('theme', $theme);
            respond(['ok' => true, 'logo' => $theme['logo']]);
        }

        case 'admin/admins/save': {
            if (!$isPost) throw new AppError('Method not allowed', 405);
            $in = input();
            $name = str_in($in, 'name', 120);
            $email = str_in($in, 'email', 190);
            $password = (string) ($in['password'] ?? '');
            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new AppError('Add meg a nevet és egy érvényes email címet.');
            if (strlen($password) < 8) throw new AppError('A jelszó legalább 8 karakter legyen.');
            if (q_one('SELECT id FROM admins WHERE email = ?', [$email])) throw new AppError('Ezzel az email címmel már van admin.');
            q_exec('INSERT INTO admins (name, email, password_hash) VALUES (?, ?, ?)', [$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
            respond(['ok' => true]);
        }

        case 'admin/admins/delete': {
            if (!$isPost) throw new AppError('Method not allowed', 405);
            $id = (int) (input()['id'] ?? 0);
            if ($id === (int) $_SESSION['admin_id']) throw new AppError('Saját magadat nem törölheted.');
            q_exec('DELETE FROM admins WHERE id = ?', [$id]);
            respond(['ok' => true]);
        }

        case 'admin/password': {
            if (!$isPost) throw new AppError('Method not allowed', 405);
            $in = input();
            $me = q_one('SELECT * FROM admins WHERE id = ?', [(int) $_SESSION['admin_id']]);
            if (!password_verify((string) ($in['current'] ?? ''), (string) $me['password_hash'])) throw new AppError('A jelenlegi jelszó hibás.');
            if (strlen((string) ($in['new'] ?? '')) < 8) throw new AppError('Az új jelszó legalább 8 karakter legyen.');
            q_exec('UPDATE admins SET password_hash = ? WHERE id = ?', [password_hash((string) $in['new'], PASSWORD_DEFAULT), (int) $me['id']]);
            respond(['ok' => true]);
        }

        default:
            throw new AppError('Ismeretlen útvonal.', 404);
    }
} catch (AppError $e) {
    respond(['error' => $e->getMessage()], $e->status);
} catch (Throwable $e) {
    error_log('[idopontfoglalo] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    respond(['error' => 'Váratlan hiba történt.'], 500);
}
