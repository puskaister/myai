<?php
declare(strict_types=1);

// Szabad időpontok számítása: nyitvatartás − zárva tartott napok − foglaltság.
// A párhuzamos kapacitás (pl. 2 emelő) azt jelenti, hogy egy időpontban
// legfeljebb ennyi aktív foglalás lehet egyszerre.

const ACTIVE_STATUSES = ['pending', 'confirmed'];

function valid_date(string $date): bool {
    $d = DateTime::createFromFormat('!Y-m-d', $date);
    return $d !== false && $d->format('Y-m-d') === $date;
}

function is_closed_day(array $settings, string $date): bool {
    foreach ($settings['closures'] as $c) {
        if (!empty($c['from']) && $date >= $c['from'] && $date <= ($c['to'] ?: $c['from'])) return true;
    }
    return false;
}

// A nap nyitvatartási sávjai unix időbélyegként: [[kezdet, vég], ...]
function day_intervals(array $settings, string $date): array {
    if (is_closed_day($settings, $date)) return [];
    $weekday = date('w', strtotime($date . ' 12:00'));
    $out = [];
    foreach ($settings['hours'][$weekday] ?? [] as $range) {
        if (!is_array($range) || count($range) !== 2) continue;
        $start = strtotime("$date {$range[0]}");
        $end = strtotime("$date {$range[1]}");
        if ($start !== false && $end !== false && $end > $start) $out[] = [$start, $end];
    }
    return $out;
}

// Aktív foglalások egy napokra bontva: ['YYYY-MM-DD' => [[kezdet, vég], ...]]
function load_busy(string $fromDate, string $toDate, ?int $excludeId = null): array {
    $placeholders = implode(',', array_fill(0, count(ACTIVE_STATUSES), '?'));
    $rows = q_all(
        "SELECT id, start_at, end_at FROM bookings
         WHERE status IN ($placeholders) AND start_at < ? AND end_at > ?",
        array_merge(ACTIVE_STATUSES, [$toDate . ' 23:59:59', $fromDate . ' 00:00:00'])
    );
    $busy = [];
    foreach ($rows as $r) {
        if ($excludeId !== null && (int) $r['id'] === $excludeId) continue;
        $busy[substr((string) $r['start_at'], 0, 10)][] = [strtotime((string) $r['start_at']), strtotime((string) $r['end_at'])];
    }
    return $busy;
}

// A [start, end) ablakban egyszerre futó foglalások legnagyobb száma.
function max_concurrent(array $busy, int $start, int $end): int {
    $points = [$start];
    foreach ($busy as [$bs, $be]) {
        if ($bs > $start && $bs < $end) $points[] = $bs;
    }
    $max = 0;
    foreach ($points as $p) {
        $n = 0;
        foreach ($busy as [$bs, $be]) {
            if ($bs <= $p && $be > $p) $n++;
        }
        $max = max($max, $n);
    }
    return $max;
}

// Az adott napon a szolgáltatás hosszával szabad kezdési időpontok ('HH:MM').
// $asAdmin: az admin kézi rögzítésénél nem számít az előrefoglalási korlát.
function available_slots(array $settings, int $duration, string $date, ?array $busyByDay = null, bool $asAdmin = false): array {
    if (!valid_date($date) || $duration <= 0) return [];

    $rules = $settings['rules'];
    $today = date('Y-m-d');
    if (!$asAdmin) {
        if ($date < $today) return [];
        $lastDay = date('Y-m-d', strtotime('+' . (int) $rules['max_days'] . ' days'));
        if ($date > $lastDay) return [];
    }

    $intervals = day_intervals($settings, $date);
    if (!$intervals) return [];

    $busyByDay = $busyByDay ?? load_busy($date, $date);
    $busy = $busyByDay[$date] ?? [];
    $minStart = $asAdmin ? 0 : time() + (int) round((float) $rules['lead_hours'] * 3600);
    $step = max(5, (int) $rules['slot_step']) * 60;
    $capacity = max(1, (int) $rules['capacity']);
    $length = $duration * 60;

    $slots = [];
    foreach ($intervals as [$open, $close]) {
        for ($t = $open; $t + $length <= $close; $t += $step) {
            if ($t < $minStart) continue;
            if (max_concurrent($busy, $t, $t + $length) < $capacity) $slots[] = date('H:i', $t);
        }
    }
    return $slots;
}
