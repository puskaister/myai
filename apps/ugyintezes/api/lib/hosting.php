<?php
// Nálunk üzemeltetett időpontfoglalók havi díja (ÁSZF 14. pont).
// A havi díj minden hónapban előre, a tárgyhónap 5. napjáig fizetendő. A „fizetve eddig”
// (paid_until) az utolsó kifizetett hónap utolsó napja. Ha a határidő után 10 nappal sincs
// befizetés, a foglaló automatikusan felfüggesztődik: a foglalási oldal nem fogad új foglalást.
// A foglalók ugyanebből a táblából olvassák az állapotukat (api/config.php 'hosting' kulcs,
// apps/idopontfoglalo/api/lib/core.php hosting_suspended()) — a két számítás egyezzen.
declare(strict_types=1);

const HOSTING_SQL = "CREATE TABLE IF NOT EXISTS {hosting} (
    dir VARCHAR(40) NOT NULL PRIMARY KEY,
    paid_until DATE NULL,
    mode VARCHAR(10) NOT NULL DEFAULT 'auto',
    email VARCHAR(190) NOT NULL DEFAULT '',
    note VARCHAR(500) NOT NULL DEFAULT '',
    late_notice_for DATE NULL,
    suspend_notice_for DATE NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

const HOSTING_PRICE = '2 990 Ft + ÁFA / hó (bruttó 3 797 Ft)';
const HOSTING_DUE_DAYS = 5;    // a fizetett időszak vége után ennyi nappal jár le a határidő (a hónap 5-e)
const HOSTING_GRACE_DAYS = 10; // a határidő után ennyi nappal felfüggesztés
const HOSTING_MODES = ['auto', 'suspended', 'active'];

// A my-ai.hu-n futó foglalók listája (a deploy írja a config.php-ba a deploy/idopontfoglalo.json alapján).
function hosting_instances(): array {
    $list = [];
    foreach ((array) (app_config()['booking_instances'] ?? []) as $i) {
        $dir = (string) ($i['dir'] ?? '');
        $prefix = (string) ($i['prefix'] ?? '');
        if (preg_match('/^[a-z0-9-]{2,40}$/', $dir) && preg_match('/^[a-z0-9]{2,20}_$/', $prefix)) $list[$dir] = $prefix;
    }
    return $list;
}

function hosting_add_days(string $date, int $days): string {
    return date('Y-m-d', strtotime("$date +$days days"));
}

// Állapot: untracked (nincs nyilvántartva, nem függesztjük fel) | ok | due (a hónap 5-éig fizetendő)
// | late (késik, még működik) | suspended (felfüggesztve) | active (kézzel bekapcsolva).
function hosting_status(?array $row, string $today): string {
    $mode = $row['mode'] ?? 'auto';
    if ($mode === 'suspended') return 'suspended';
    if ($mode === 'active') return 'active';
    $paid = (string) ($row['paid_until'] ?? '');
    if ($paid === '') return 'untracked';
    if ($today <= $paid) return 'ok';
    $due = hosting_add_days($paid, HOSTING_DUE_DAYS);
    if ($today <= $due) return 'due';
    if ($today <= hosting_add_days($due, HOSTING_GRACE_DAYS)) return 'late';
    return 'suspended';
}

// A foglaló saját beállításaiból a cégnév és az értesítési email.
function hosting_business(string $prefix): array {
    $out = ['name' => '', 'email' => ''];
    $res = @db()->query("SELECT k, v FROM `{$prefix}settings` WHERE k IN ('business', 'notify')");
    while ($res && ($row = $res->fetch_assoc())) {
        $v = json_decode((string) $row['v'], true) ?: [];
        if ($row['k'] === 'business') {
            $out['name'] = (string) ($v['name'] ?? '');
            if ($out['email'] === '') $out['email'] = (string) ($v['email'] ?? '');
        } elseif (($v['admin_email'] ?? '') !== '') {
            $out['email'] = (string) $v['admin_email'];
        }
    }
    return $out;
}

function hosting_rows(string $today): array {
    $rows = [];
    foreach (q_all('SELECT * FROM {hosting}') as $r) $rows[$r['dir']] = $r;
    $out = [];
    foreach (hosting_instances() as $dir => $prefix) {
        $r = $rows[$dir] ?? null;
        $biz = hosting_business($prefix);
        $paid = (string) ($r['paid_until'] ?? '');
        $out[] = [
            'dir'          => $dir,
            'name'         => $biz['name'],
            'email'        => (string) ($r['email'] ?? '') !== '' ? $r['email'] : $biz['email'],
            'email_custom' => (string) ($r['email'] ?? ''),
            'paid_until'   => $paid,
            'mode'         => $r['mode'] ?? 'auto',
            'note'         => (string) ($r['note'] ?? ''),
            'status'       => hosting_status($r, $today),
            'due'          => $paid !== '' ? hosting_add_days($paid, HOSTING_DUE_DAYS) : '',
            'suspend_from' => $paid !== '' ? hosting_add_days($paid, HOSTING_DUE_DAYS + HOSTING_GRACE_DAYS + 1) : '',
            'installed'    => $biz['name'] !== '',
        ];
    }
    return $out;
}

function hosting_row(string $dir): array {
    if (!isset(hosting_instances()[$dir])) throw new AppError('Nincs ilyen foglaló.', 404);
    return q_one('SELECT * FROM {hosting} WHERE dir = ?', [$dir]) ?? ['dir' => $dir, 'paid_until' => null, 'mode' => 'auto', 'email' => '', 'note' => ''];
}

function hosting_save(string $dir, ?string $paidUntil, string $mode, string $email, string $note): void {
    hosting_row($dir);
    if (!in_array($mode, HOSTING_MODES, true)) throw new AppError('Érvénytelen állapot.');
    if ($paidUntil !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $paidUntil)) throw new AppError('Érvénytelen dátum.');
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new AppError('Érvénytelen email cím.');
    q_exec('INSERT INTO {hosting} (dir, paid_until, mode, email, note) VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE paid_until = VALUES(paid_until), mode = VALUES(mode), email = VALUES(email), note = VALUES(note)',
        [$dir, $paidUntil, $mode, $email, $note]);
}

// Befizetés: a fizetett időszak végétől (ha még nincs, az előző hónap végétől) N hónappal tovább,
// mindig hónap végére. Egy kézi felfüggesztést nem old fel magától.
function hosting_pay(string $dir, int $months, string $today): string {
    $r = hosting_row($dir);
    $base = (string) ($r['paid_until'] ?? '') ?: date('Y-m-t', strtotime(date('Y-m-01', strtotime($today)) . ' -1 day'));
    $until = date('Y-m-t', strtotime(date('Y-m-01', strtotime($base)) . " +$months months"));
    q_exec('INSERT INTO {hosting} (dir, paid_until) VALUES (?, ?) ON DUPLICATE KEY UPDATE paid_until = VALUES(paid_until)', [$dir, $until]);
    return $until;
}

// Napi kör: késés esetén emlékeztető, felfüggesztéskor értesítés (időszakonként egyszer).
function run_hosting(string $today): array {
    $stats = ['late' => 0, 'suspended' => 0, 'failed' => 0];
    $o = get_options();
    $pay = trim((string) $o['payment_info']);
    $contact = $o['admin_email'] ?: 'info@my-ai.hu';
    foreach (hosting_rows($today) as $h) {
        if ($h['mode'] !== 'auto' || $h['paid_until'] === '' || !in_array($h['status'], ['late', 'suspended'], true)) continue;
        $row = q_one('SELECT * FROM {hosting} WHERE dir = ?', [$h['dir']]);
        $col = $h['status'] === 'late' ? 'late_notice_for' : 'suspend_notice_for';
        if (($row[$col] ?? null) === $h['paid_until']) continue;

        $name = $h['name'] ?: $h['dir'];
        $due = date('Y. m. d.', strtotime($h['due']));
        if ($h['status'] === 'late') {
            $subject = "Fizetési emlékeztető – online időpontfoglaló ($name)";
            $body = "Tisztelt Ügyfelünk!\n\nAz online időpontfoglaló ({$name}) havi üzemeltetési díjának ($due) fizetési határideje lejárt, a befizetés még nem érkezett meg.\n"
                . 'A havi díj: ' . HOSTING_PRICE . ".\n"
                . 'Ha a díj a határidőt követő ' . HOSTING_GRACE_DAYS . " napon belül sem érkezik meg, a foglalási oldal felfüggesztésre kerül (ÁSZF 14.5. pont).\n"
                . ($pay !== '' ? "\nFizetési információ:\n$pay\n" : '')
                . "\nHa időközben fizetett, kérjük, tekintse tárgytalannak ezt a levelet.\nKérdés esetén írjon: $contact\n\nmy-ai.hu";
        } else {
            $subject = "Az online időpontfoglaló felfüggesztve – $name";
            $body = "Tisztelt Ügyfelünk!\n\nAz online időpontfoglaló ({$name}) havi díja a fizetési határidő ($due) után " . HOSTING_GRACE_DAYS . " nappal sem érkezett meg, ezért a foglalási oldal felfüggesztésre került: új foglalást nem fogad. A beállítások és a foglalások megmaradnak.\n"
                . "A díj beérkezése után a szolgáltatást haladéktalanul visszaállítjuk.\n"
                . ($pay !== '' ? "\nFizetési információ:\n$pay\n" : '')
                . "\nKérdés esetén írjon: $contact\n\nmy-ai.hu";
        }
        $sent = filter_var($h['email'], FILTER_VALIDATE_EMAIL) && send_app_email(mail_cfg(), $h['email'], $subject, $body);
        notify_admin(($h['status'] === 'late' ? 'Késik a foglaló díja: ' : 'Felfüggesztve: ') . $name,
            "Foglaló: /{$h['dir']}/ ($name)\nFizetve eddig: {$h['paid_until']}\nÜgyfél email: " . ($h['email'] ?: '— nincs megadva —')
            . ($sent ? "\nAz ügyfél értesítést kapott." : "\nAz ügyfélnek NEM ment levél (nincs vagy hibás email).")
            . "\n\nAdmin: " . app_base_url() . '/admin/');
        q_exec("UPDATE {hosting} SET $col = ? WHERE dir = ?", [$h['paid_until'], $h['dir']]);
        $stats[$h['status']]++;
        if (!$sent) $stats['failed']++;
    }
    return $stats;
}
