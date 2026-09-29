<?php
// Naptár-feliratkozás (iCalendar): a telefon / Google / Outlook naptárba
// felvehető személyes link, a Beállítások fülön. ics.php?t=<token>
declare(strict_types=1);

require __DIR__ . '/api/lib/core.php';

$token = (string) ($_GET['t'] ?? '');
$user = preg_match('/^[a-f0-9]{32}$/', $token) && app_installed() ? q_one('SELECT id, name FROM {users} WHERE cal_token = ?', [$token]) : null;
if (!$user) {
    http_response_code(404);
    exit;
}

$esc = fn (string $s) => addcslashes(str_replace(["\r\n", "\n"], '\n', $s), ',;\\');
$fold = function (string $line): string { // RFC 5545: 75 bájtos sorok
    $out = '';
    while (strlen($line) > 75) {
        $cut = 75;
        while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) $cut--; // ne vágjunk UTF-8 karakter közepén
        $out .= substr($line, 0, $cut) . "\r\n ";
        $line = substr($line, $cut);
    }
    return $out . $line;
};

$lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//my-ai.hu//Evfordulok//HU', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH',
          'X-WR-CALNAME:' . $esc(get_options()['app_name'] . ' – ' . $user['name']), 'X-WR-TIMEZONE:Europe/Budapest'];
foreach (q_all('SELECT * FROM {events} WHERE user_id = ?', [(int) $user['id']]) as $e) {
    $year = $e['year'] ? (int) $e['year'] : (int) date('Y') - 1;
    $start = clamp_date($year, (int) $e['month'], (int) $e['day']);
    $icon = CATEGORIES[$e['category']][1] ?? '';
    $ev = ['BEGIN:VEVENT', 'UID:evf-' . $e['id'] . '@' . ($_SERVER['HTTP_HOST'] ?? 'my-ai.hu'), 'DTSTAMP:' . gmdate('Ymd\THis\Z'),
           'DTSTART;VALUE=DATE:' . str_replace('-', '', $start),
           'DTEND;VALUE=DATE:' . date('Ymd', strtotime("$start +1 day")),
           'SUMMARY:' . $esc(trim($icon . ' ' . $e['title'])), 'TRANSP:TRANSPARENT'];
    if ($e['recurrence'] === 'yearly') $ev[] = 'RRULE:FREQ=YEARLY';
    if ($e['recurrence'] === 'monthly') $ev[] = 'RRULE:FREQ=MONTHLY';
    if (trim((string) $e['note']) !== '') $ev[] = 'DESCRIPTION:' . $esc((string) $e['note']);
    if ((int) $e['remind_days'] >= 0) {
        // riasztás az emlékeztető napján reggel 9-kor (a kezdés az adott nap 0:00)
        $offset = 9 * 60 - (int) $e['remind_days'] * 1440;
        array_push($ev, 'BEGIN:VALARM', 'ACTION:DISPLAY', 'DESCRIPTION:' . $esc($e['title']), 'TRIGGER:' . ($offset < 0 ? '-PT' . -$offset : 'PT' . $offset) . 'M', 'END:VALARM');
    }
    $ev[] = 'END:VEVENT';
    $lines = array_merge($lines, $ev);
}
$lines[] = 'END:VCALENDAR';

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: inline; filename="evfordulok.ics"');
header('Cache-Control: no-cache');
echo implode("\r\n", array_map($fold, $lines)) . "\r\n";
