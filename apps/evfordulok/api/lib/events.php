<?php
declare(strict_types=1);

// Alkalmak: következő előfordulás számítása és a napi emlékeztető-kör.

const CATEGORIES = [
    'birthday'    => ['Születésnap', '🎂'],
    'nameday'     => ['Névnap', '🌷'],
    'anniversary' => ['Évforduló', '💍'],
    'holiday'     => ['Ünnep', '🎉'],
    'memorial'    => ['Emléknap', '🕯️'],
    'other'       => ['Egyéb', '📌'],
];
const RECURRENCES = ['once' => 'Egyszeri', 'yearly' => 'Évente', 'monthly' => 'Havonta'];
const HU_MONTHS_GEN = ['január', 'február', 'március', 'április', 'május', 'június', 'július', 'augusztus', 'szeptember', 'október', 'november', 'december'];
const HU_WEEKDAYS = ['vasárnap', 'hétfő', 'kedd', 'szerda', 'csütörtök', 'péntek', 'szombat'];

// Adott hónap napja, a hónap hosszára vágva (pl. febr. 29. → nem szökőévben 28.,
// havi ismétlésnél a 31. → rövidebb hónapban az utolsó nap).
function clamp_date(int $year, int $month, int $day): string {
    $last = (int) date('t', mktime(12, 0, 0, $month, 1, $year));
    return sprintf('%04d-%02d-%02d', $year, $month, min($day, $last));
}

// Az alkalom első előfordulása a $from napon vagy utána ('Y-m-d'), vagy null.
function next_occurrence(array $e, string $from): ?string {
    [$fy, $fm] = array_map('intval', explode('-', $from));
    $m = (int) $e['month'];
    $d = (int) $e['day'];
    switch ($e['recurrence']) {
        case 'once':
            if (empty($e['year'])) return null;
            $date = clamp_date((int) $e['year'], $m, $d);
            return $date >= $from ? $date : null;
        case 'monthly':
            $date = clamp_date($fy, $fm, $d);
            if ($date < $from) {
                $nm = $fm === 12 ? 1 : $fm + 1;
                $date = clamp_date($fm === 12 ? $fy + 1 : $fy, $nm, $d);
            }
            break;
        default: // yearly
            $date = clamp_date($fy, $m, $d);
            if ($date < $from) $date = clamp_date($fy + 1, $m, $d);
    }
    // ne jelezzünk a kezdő év előtti előfordulást (pl. egy jövőbeli évfordulónál)
    if (!empty($e['year']) && $e['recurrence'] !== 'once' && (int) substr($date, 0, 4) < (int) $e['year']) {
        $date = clamp_date((int) $e['year'], $m, $d);
    }
    return $date;
}

function days_between(string $from, string $to): int {
    return (int) round((strtotime("$to 12:00") - strtotime("$from 12:00")) / 86400);
}

// Hányadik évforduló / születésnap lesz az adott napon (csak éves, ismert évszámnál).
function ordinal_for(array $e, string $date): ?int {
    if ($e['recurrence'] !== 'yearly' || empty($e['year'])) return null;
    $n = (int) substr($date, 0, 4) - (int) $e['year'];
    return $n > 0 ? $n : null;
}

function hu_long_date(string $date): string {
    $ts = strtotime("$date 12:00");
    return date('Y', $ts) . '. ' . HU_MONTHS_GEN[(int) date('n', $ts) - 1] . ' ' . (int) date('j', $ts) . '., ' . HU_WEEKDAYS[(int) date('w', $ts)];
}

function occasion_label(array $e, string $date): string {
    $n = ordinal_for($e, $date);
    $cat = CATEGORIES[$e['category']][0] ?? 'Alkalom';
    if ($n === null) return $cat;
    return $n . '. ' . mb_strtolower($cat);
}

// Egy felhasználó alkalmai, a következő előfordulással kiegészítve, időrendben.
function user_events(int $userId, string $today): array {
    $rows = q_all('SELECT * FROM {events} WHERE user_id = ? ORDER BY month, day, id', [$userId]);
    $out = [];
    foreach ($rows as $e) {
        $next = next_occurrence($e, $today);
        $e['next'] = $next;
        $e['days_until'] = $next !== null ? days_between($today, $next) : null;
        $e['ordinal'] = $next !== null ? ordinal_for($e, $next) : null;
        $e['label'] = $next !== null ? occasion_label($e, $next) : (CATEGORIES[$e['category']][0] ?? '');
        $out[] = $e;
    }
    usort($out, function ($a, $b) {
        if ($a['next'] === null || $b['next'] === null) return $a['next'] === null ? 1 : -1;
        return strcmp($a['next'], $b['next']);
    });
    return $out;
}

// A napi emlékeztető-kör: mindenkinek egy összefoglaló levél azokról az
// alkalmakról, amelyeknek ma van az emlékeztető napja. Egy alkalomról egy
// előfordulásra legfeljebb egy levél megy (sent_reminders egyedi kulcs).
function run_reminders(string $today): array {
    $stats = ['users' => 0, 'emails' => 0, 'occasions' => 0];
    $config = app_config() ?? [];
    $appName = get_options()['app_name'];
    $base = app_base_url();

    $users = q_all('SELECT id, name, email FROM {users} WHERE notify = 1');
    foreach ($users as $u) {
        $stats['users']++;
        $due = [];
        foreach (q_all('SELECT * FROM {events} WHERE user_id = ? AND remind_days >= 0', [(int) $u['id']]) as $e) {
            $next = next_occurrence($e, $today);
            if ($next === null || days_between($today, $next) !== (int) $e['remind_days']) continue;
            if (q_one('SELECT id FROM {sent_reminders} WHERE event_id = ? AND occurrence = ?', [(int) $e['id'], $next])) continue;
            $due[] = [$e, $next];
        }
        if (!$due) continue;

        $lines = [];
        foreach ($due as [$e, $next]) {
            $days = days_between($today, $next);
            $when = $days === 0 ? 'Ma' : ($days === 1 ? 'Holnap' : "$days nap múlva");
            $icon = CATEGORIES[$e['category']][1] ?? '📌';
            $lines[] = "$icon $when (" . hu_long_date($next) . "): {$e['title']} — " . occasion_label($e, $next)
                . (trim((string) $e['note']) !== '' ? "\n   " . trim((string) $e['note']) : '');
        }
        [$first, $firstDate] = $due[0];
        $d0 = days_between($today, $firstDate);
        $subject = ($d0 === 0 ? 'Ma: ' : ($d0 === 1 ? 'Holnap: ' : "$d0 nap múlva: ")) . $first['title']
            . (count($due) > 1 ? ' és még ' . (count($due) - 1) : '') . " – $appName";
        $body = "Kedves {$u['name']}!\n\nKözelgő alkalmak:\n\n" . implode("\n\n", $lines)
            . "\n\nMegnyitás: $base/\n\n(Az értesítéseket az app Beállítások fülén kapcsolhatod ki.)";

        if (send_app_email($config, $u['email'], $subject, $body)) {
            $stats['emails']++;
            foreach ($due as [$e, $next]) {
                q_exec('INSERT IGNORE INTO {sent_reminders} (event_id, occurrence) VALUES (?, ?)', [(int) $e['id'], $next]);
                $stats['occasions']++;
            }
        }
    }
    set_setting_value('last_run', $today);
    return $stats;
}
