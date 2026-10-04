<?php
declare(strict_types=1);

// Előfizetések: ár, érvényesség, befizetés rögzítése, lejárati emlékeztetők.
// A beépített chatbot csak érvényes előfizetésnél kapja meg a tudásbázist (kb.php).

// A my-ai.hu saját buboréka és a nyilvános demó mindig működik (fájlból, adatbázis nélkül).
const BUILTIN_KBS = ['minta', 'my-ai'];

function price_gross(): int {
    $o = get_options();
    return (int) round((int) $o['price_net'] * (1 + (int) $o['vat_percent'] / 100));
}

function huf(int $n): string {
    return number_format($n, 0, ',', ' ') . ' Ft';
}

function price_text(): string {
    $o = get_options();
    return huf((int) $o['price_net']) . ' + ÁFA / hó (bruttó ' . huf(price_gross()) . ')';
}

// Érvényes-e most az előfizetés: aktív állapot, és a fizetett időszak még tart.
function is_subscription_active(array $c, ?string $today = null): bool {
    $today = $today ?? date('Y-m-d');
    return $c['status'] === 'active' && !empty($c['paid_until']) && (string) $c['paid_until'] >= $today;
}

// Befizetés rögzítése: N hónappal hosszabbít — a mai naptól, vagy ha még érvényes,
// a meglévő lejárattól (így az időben fizető ügyfél nem veszít napokat).
function add_payment(int $customerId, int $months, string $note, ?int $amount = null): string {
    $c = q_one('SELECT paid_until FROM {customers} WHERE id = ?', [$customerId]);
    if (!$c) throw new AppError('Nincs ilyen ügyfél.', 404);
    $today = date('Y-m-d');
    $from = !empty($c['paid_until']) && (string) $c['paid_until'] >= $today ? (string) $c['paid_until'] : date('Y-m-d', strtotime('-1 day'));
    $until = date('Y-m-d', strtotime("$from +$months month"));
    $amount = $amount ?? price_gross() * $months;
    q_exec('INSERT INTO {payments} (customer_id, months, amount, note, period_until) VALUES (?, ?, ?, ?, ?)', [$customerId, $months, $amount, $note, $until]);
    q_exec("UPDATE {customers} SET paid_until = ?, status = 'active' WHERE id = ?", [$until, $customerId]);
    return $until;
}

// Ügyfél-azonosító (a beépítő kódban data-ugyfel) a cégnévből: "Kovács Iroda Kft." → "kovacs-iroda-kft".
function make_slug(string $name): string {
    $s = mb_strtolower(trim($name));
    $s = strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ö' => 'o', 'ő' => 'o', 'ú' => 'u', 'ü' => 'u', 'ű' => 'u']);
    $s = trim(preg_replace('/[^a-z0-9]+/', '-', $s), '-');
    $s = substr($s, 0, 32) ?: 'ugyfel';
    if (in_array($s, BUILTIN_KBS, true)) $s .= '-1';
    $base = $s;
    for ($i = 2; q_one('SELECT id FROM {customers} WHERE slug = ?', [$s]); $i++) $s = substr($base, 0, 30) . '-' . $i;
    return $s;
}

// A tudásbázis ellenőrzése (a demó szerkesztőjének kimásolt formátuma).
function validate_kb(string $json): array {
    $d = json_decode($json, true);
    if (!is_array($d)) throw new AppError('A tudásbázis nem érvényes JSON.');
    $temak = $d['temak'] ?? null;
    if (!is_array($temak) || !$temak) throw new AppError('A tudásbázisban nincs egyetlen téma sem ("temak").');
    foreach ($temak as $i => $t) {
        if (!is_array($t) || trim((string) ($t['title'] ?? '')) === '' || trim((string) ($t['answer'] ?? '')) === '') {
            throw new AppError('A(z) ' . ($i + 1) . '. témából hiányzik a cím vagy a válasz.');
        }
    }
    if (count($temak) > 300) throw new AppError('Legfeljebb 300 téma lehet.');
    return $d;
}

function mail_cfg(): array {
    return app_config() ?? [];
}

function notify_admin(string $subject, string $body): void {
    $to = get_options()['admin_email'];
    if (filter_var($to, FILTER_VALIDATE_EMAIL)) send_app_email(mail_cfg(), $to, $subject, $body);
}

// Napi kör: lejárat előtti emlékeztetők és a lejárt előfizetések jelzése.
// Egy ügyfél egy időszakára egy fajta levél csak egyszer megy ki (reminders tábla).
function run_billing(string $today): array {
    $stats = ['reminders' => 0, 'expired' => 0, 'failed' => 0, 'errors' => []];
    $o = get_options();
    $days = array_map('intval', (array) $o['remind_days']);
    foreach (q_all("SELECT * FROM {customers} WHERE status = 'active' AND paid_until IS NOT NULL") as $c) {
        $left = (int) round((strtotime($c['paid_until'] . ' 12:00') - strtotime("$today 12:00")) / 86400);
        $kind = in_array($left, $days, true) ? 'remind' . $left : ($left < 0 ? 'expired' : null);
        if ($kind === null) continue;
        if (q_one('SELECT id FROM {reminders} WHERE customer_id = ? AND kind = ? AND period_until = ?', [(int) $c['id'], $kind, $c['paid_until']])) continue;

        $until = date('Y. m. d.', strtotime($c['paid_until']));
        $pay = trim((string) $o['payment_info']);
        if ($kind === 'expired') {
            $subject = 'Az Ügyintézési Segéd előfizetése lejárt – ' . $c['name'];
            $body = "Kedves {$c['contact_name']}!\n\nAz Ügyintézési Segéd előfizetése $until-án lejárt, ezért a chatbot a weboldalán szünetel.\n"
                . 'A folytatáshoz a havidíj ' . price_text() . ".\n" . ($pay !== '' ? "\nFizetési információ:\n$pay\n" : "\nA díjbekérőt hamarosan elküldjük.\n")
                . "\nKérdés esetén írjon: " . ($o['admin_email'] ?: 'info@my-ai.hu') . "\n\nmy-ai.hu";
        } else {
            $subject = "Az Ügyintézési Segéd előfizetése $left nap múlva lejár – " . $c['name'];
            $body = "Kedves {$c['contact_name']}!\n\nAz Ügyintézési Segéd előfizetése $until-ig érvényes ($left nap múlva lejár).\n"
                . 'A folytatáshoz kérjük, fizesse be a következő időszak díját: ' . price_text() . ".\n"
                . ($pay !== '' ? "\nFizetési információ:\n$pay\n" : "\nA díjbekérőt emailben küldjük.\n")
                . "\nKérdés esetén írjon: " . ($o['admin_email'] ?: 'info@my-ai.hu') . "\n\nmy-ai.hu";
        }
        if (send_app_email(mail_cfg(), (string) $c['email'], $subject, $body)) {
            q_exec('INSERT INTO {reminders} (customer_id, kind, period_until) VALUES (?, ?, ?)', [(int) $c['id'], $kind, $c['paid_until']]);
            $stats[$kind === 'expired' ? 'expired' : 'reminders']++;
            notify_admin(($kind === 'expired' ? 'Lejárt: ' : 'Lejár ' . $left . ' nap múlva: ') . $c['name'],
                "Ügyfél: {$c['name']} ({$c['slug']})\nKapcsolattartó: {$c['contact_name']} <{$c['email']}>, {$c['phone']}\nÉrvényes: $until\n\nAdmin: " . app_base_url() . '/admin/');
        } else {
            $stats['failed']++;
            $stats['errors'][] = $c['email'] . ': ' . (($GLOBALS['mail_last']['error'] ?? '') ?: 'ismeretlen hiba');
        }
    }
    set_setting_value('last_run', $today);
    set_setting_value('last_run_result', json_encode(['at' => date('Y-m-d H:i')] + $stats, JSON_UNESCAPED_UNICODE));
    return $stats;
}
