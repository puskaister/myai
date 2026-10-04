<?php
// API: api/?r=<útvonal>
// Nyilvános: order (megrendelés a my-ai.hu oldalról), price
// Belépés: login, logout, me, forgot, reset
// Admin: admin/customers, admin/customer, admin/customer/save, admin/customer/delete,
//        admin/payment/add, admin/settings (+/save), admin/password
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

function embed_code(array $c): string {
    $attrs = ' data-ugyfel="' . $c['slug'] . '"' . ($c['color'] !== '' ? ' data-szin="' . $c['color'] . '"' : '');
    return '<script src="' . app_base_url() . '/widget.js"' . $attrs . ' defer></script>';
}

function customer_row(array $c): array {
    $c['active_now'] = is_subscription_active($c);
    $c['days_left'] = !empty($c['paid_until']) ? (int) round((strtotime($c['paid_until'] . ' 12:00') - strtotime(date('Y-m-d') . ' 12:00')) / 86400) : null;
    $c['has_kb'] = !empty($c['kb_json']);
    unset($c['kb_json']);
    return $c;
}

try {
    if (!app_installed()) throw new AppError('A rendszer még nincs telepítve (install.php).', 503);
    session_name(app_config()['session_name'] ?? 'ugyintezes_session');
    session_set_cookie_params(['lifetime' => 0, 'path' => dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/api/index.php')) ?: '/',
        'secure' => is_https_request(), 'httponly' => true, 'samesite' => 'Lax']);
    session_start();

    $route = (string) ($_GET['r'] ?? '');
    $isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    if ($isPost && ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'fetch') throw new AppError('Érvénytelen kérés.', 400);
    if (strpos($route, 'admin/') === 0 && !current_admin()) throw new AppError('Bejelentkezés szükséges.', 401);
    $getOnly = ['price', 'me', 'admin/customers', 'admin/customer', 'admin/settings'];
    if (!in_array($route, $getOnly, true) && !$isPost) throw new AppError('Method not allowed', 405);

    switch ($route) {
        // ------------------------------------------------------------- nyilvános
        case 'price':
            respond(['net' => (int) get_options()['price_net'], 'vat' => (int) get_options()['vat_percent'], 'gross' => price_gross(), 'text' => price_text()]);

        case 'order': {
            $in = input();
            if (trim((string) ($in['website_url_hp'] ?? '')) !== '') respond(['ok' => true]); // honeypot
            rate_limit('order:' . client_ip(), 5, 3600);
            $name = str_in($in, 'company', 150);
            $contact = str_in($in, 'contact_name', 120);
            $email = str_in($in, 'email', 190);
            $errors = [];
            if (mb_strlen($name) < 2) $errors['company'] = 'Add meg a cég vagy vállalkozás nevét.';
            if (mb_strlen($contact) < 2) $errors['contact_name'] = 'Add meg a kapcsolattartó nevét.';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Érvénytelen email cím.';
            if (empty($in['accept'])) $errors['accept'] = 'A megrendeléshez fogadd el az ÁSZF-et.';
            if ($errors) respond(['error' => reset($errors), 'fields' => $errors], 422);

            $slug = make_slug($name);
            q_exec('INSERT INTO {customers} (slug, name, contact_name, email, phone, website, billing_name, billing_address, tax_number, message, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$slug, $name, $contact, $email, str_in($in, 'phone', 60), str_in($in, 'website', 255), str_in($in, 'billing_name', 150) ?: $name,
                 str_in($in, 'billing_address', 255), str_in($in, 'tax_number', 40), str_in($in, 'message', 3000), 'pending']);
            $id = (int) db()->insert_id;
            $o = get_options();
            $details = "Cég: $name\nKapcsolattartó: $contact\nEmail: $email\nTelefon: " . str_in($in, 'phone', 60)
                . "\nWeboldal: " . str_in($in, 'website', 255) . "\nSzámlázási név: " . (str_in($in, 'billing_name', 150) ?: $name)
                . "\nSzámlázási cím: " . str_in($in, 'billing_address', 255) . "\nAdószám: " . str_in($in, 'tax_number', 40)
                . (str_in($in, 'message', 3000) !== '' ? "\n\nÜzenet:\n" . str_in($in, 'message', 3000) : '');
            notify_admin("Új megrendelés: Ügyintézési Segéd – $name", "Új megrendelés érkezett.\n\n$details\n\nDíj: " . price_text() . "\nAzonosító: $slug\n\nAdmin: " . app_base_url() . "/admin/#c$id");
            send_app_email(mail_cfg(), $email, 'Megrendelés visszaigazolása – Ügyintézési Segéd',
                "Kedves $contact!\n\nKöszönjük, megkaptuk az Ügyintézési Segéd megrendelését.\n\nA havidíj: " . price_text() . ".\n"
                . "Hamarosan felvesszük Önnel a kapcsolatot: egyeztetjük a chatbot témáit és válaszait, és elküldjük a díjbekérőt.\n"
                . 'A chatbot az első befizetés után indul, és a beépítést is mi végezzük.'
                . "\nA szerződési feltételek: https://my-ai.hu/aszf/\n\nA megadott adatok:\n$details\n\nKérdés esetén írjon: " . ($o['admin_email'] ?: 'info@my-ai.hu') . "\n\nmy-ai.hu");
            respond(['ok' => true]);
        }

        // ------------------------------------------------------------- belépés
        case 'login': {
            rate_limit('login:' . client_ip(), 10, 900);
            $in = input();
            $a = q_one('SELECT * FROM {admins} WHERE email = ?', [str_in($in, 'email', 190)]);
            if (!$a || !password_verify((string) ($in['password'] ?? ''), (string) $a['password_hash'])) throw new AppError('Hibás email cím vagy jelszó.', 401);
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int) $a['id'];
            respond(['ok' => true]);
        }

        case 'logout':
            $_SESSION = [];
            session_destroy();
            respond(['ok' => true]);

        case 'me':
            respond(['admin' => current_admin()]);

        case 'forgot': {
            rate_limit('forgot:' . client_ip(), 5, 3600);
            $a = q_one('SELECT id, name, email FROM {admins} WHERE email = ?', [str_in(input(), 'email', 190)]);
            if ($a) {
                $token = bin2hex(random_bytes(32));
                q_exec('DELETE FROM {password_resets} WHERE admin_id = ? OR expires_at < NOW()', [(int) $a['id']]);
                q_exec('INSERT INTO {password_resets} (admin_id, token_hash, expires_at) VALUES (?, ?, ?)', [(int) $a['id'], hash('sha256', $token), date('Y-m-d H:i:s', time() + 3600)]);
                send_app_email(mail_cfg(), $a['email'], 'Jelszó visszaállítása – Ügyintézési Segéd admin',
                    "Kedves {$a['name']}!\n\nÚj jelszót ezen a linken állíthatsz be (1 óráig érvényes):\n\n" . app_base_url() . "/admin/?reset=$token\n\nHa nem te kérted, hagyd figyelmen kívül ezt a levelet.");
            }
            respond(['ok' => true]);
        }

        case 'reset': {
            rate_limit('reset:' . client_ip(), 10, 3600);
            $in = input();
            $token = (string) ($in['token'] ?? '');
            $row = preg_match('/^[a-f0-9]{64}$/', $token) ? q_one('SELECT id, admin_id FROM {password_resets} WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()', [hash('sha256', $token)]) : null;
            if (!$row) throw new AppError('A link érvénytelen vagy lejárt.');
            if (strlen((string) ($in['password'] ?? '')) < 8) throw new AppError('A jelszó legalább 8 karakter legyen.');
            q_exec('UPDATE {admins} SET password_hash = ? WHERE id = ?', [password_hash((string) $in['password'], PASSWORD_DEFAULT), (int) $row['admin_id']]);
            q_exec('UPDATE {password_resets} SET used_at = NOW() WHERE id = ?', [(int) $row['id']]);
            respond(['ok' => true]);
        }

        // ------------------------------------------------------------- admin
        case 'admin/customers': {
            $rows = q_all('SELECT * FROM {customers} ORDER BY FIELD(status, ?, ?, ?), paid_until, id DESC', ['pending', 'active', 'paused']);
            $active = array_filter($rows, fn ($c) => is_subscription_active($c));
            respond(['customers' => array_map('customer_row', $rows), 'price' => price_text(), 'gross' => price_gross(),
                     'mrr_net' => count($active) * (int) get_options()['price_net'],
                     'last_result' => json_decode((string) setting_value('last_run_result'), true)]);
        }

        case 'admin/customer': {
            $c = q_one('SELECT * FROM {customers} WHERE id = ?', [(int) ($_GET['id'] ?? 0)]);
            if (!$c) throw new AppError('Nincs ilyen ügyfél.', 404);
            $kb = (string) ($c['kb_json'] ?? '');
            respond(['customer' => customer_row($c), 'kb_json' => $kb, 'embed' => embed_code($c),
                     'payments' => q_all('SELECT months, amount, note, period_until, created_at FROM {payments} WHERE customer_id = ? ORDER BY id DESC', [(int) $c['id']])]);
        }

        case 'admin/customer/save': {
            $in = input();
            $id = (int) ($in['id'] ?? 0);
            $c = q_one('SELECT * FROM {customers} WHERE id = ?', [$id]);
            if (!$c) throw new AppError('Nincs ilyen ügyfél.', 404);
            $name = str_in($in, 'name', 150);
            $email = str_in($in, 'email', 190);
            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new AppError('A név és egy érvényes email cím kötelező.');
            $status = in_array($in['status'] ?? '', ['pending', 'active', 'paused'], true) ? $in['status'] : $c['status'];
            $color = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($in['color'] ?? '')) ? strtolower($in['color']) : '';
            $kb = trim((string) ($in['kb_json'] ?? ''));
            if ($kb !== '') $kb = json_encode(validate_kb($kb), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $paidUntil = (string) ($in['paid_until'] ?? '');
            $paidUntil = preg_match('/^\d{4}-\d{2}-\d{2}$/', $paidUntil) ? $paidUntil : ($c['paid_until'] ?? null);
            q_exec('UPDATE {customers} SET name = ?, contact_name = ?, email = ?, phone = ?, website = ?, billing_name = ?, billing_address = ?, tax_number = ?,
                    admin_note = ?, color = ?, kb_json = ?, status = ?, paid_until = ? WHERE id = ?',
                [$name, str_in($in, 'contact_name', 120), $email, str_in($in, 'phone', 60), str_in($in, 'website', 255), str_in($in, 'billing_name', 150),
                 str_in($in, 'billing_address', 255), str_in($in, 'tax_number', 40), str_in($in, 'admin_note', 3000), $color, $kb !== '' ? $kb : null, $status, $paidUntil ?: null, $id]);
            respond(['ok' => true]);
        }

        case 'admin/customer/delete': {
            $id = (int) (input()['id'] ?? 0);
            foreach (['{payments}', '{reminders}'] as $t) q_exec("DELETE FROM $t WHERE customer_id = ?", [$id]);
            q_exec('DELETE FROM {customers} WHERE id = ?', [$id]);
            respond(['ok' => true]);
        }

        case 'admin/payment/add': {
            $in = input();
            $months = (int) ($in['months'] ?? 1);
            if (!in_array($months, [1, 2, 3, 6, 12], true)) throw new AppError('Érvénytelen időszak.');
            $amount = isset($in['amount']) && $in['amount'] !== '' ? max(0, (int) $in['amount']) : null;
            $until = add_payment((int) ($in['id'] ?? 0), $months, str_in($in, 'note', 200), $amount);
            respond(['ok' => true, 'paid_until' => $until]);
        }

        case 'admin/settings':
            respond(['options' => get_options(), 'cron_url' => app_base_url() . '/cron.php?key=' . cron_key(), 'me' => current_admin()]);

        case 'admin/settings/save': {
            $in = input();
            $email = str_in($in, 'admin_email', 190);
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new AppError('Érvénytelen email cím.');
            save_options([
                'price_net'    => max(0, (int) ($in['price_net'] ?? 5000)),
                'vat_percent'  => max(0, min(50, (int) ($in['vat_percent'] ?? 27))),
                'admin_email'  => $email,
                'payment_info' => str_in($in, 'payment_info', 1000),
                'remind_days'  => [7, 1],
            ]);
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
    error_log('[ugyintezes] ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    respond(['error' => 'Váratlan hiba történt.'], 500);
}
