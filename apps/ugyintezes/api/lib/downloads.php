<?php
// Letöltésszámláló: melyik appot hányszor töltik le (napra és appra összesítve).
// Személyes adatot nem tárol (sem IP-t, sem böngészőt), ezért hozzájárulás nem kell hozzá.
// Számol: az ingyenes csomagok (my-ai.hu/letoltes/?fajl=…, public/letoltes/index.php)
// és a letöltőkóddal letöltött fizetős csomag (letoltes.php). Az admin „Letöltések” fülén látszik.
declare(strict_types=1);

const DOWNLOADS_SQL = "CREATE TABLE IF NOT EXISTS {downloads} (
    day DATE NOT NULL,
    product VARCHAR(40) NOT NULL,
    n INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (day, product)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

const DOWNLOAD_PRODUCTS = [
    'latogatoszamlalo' => 'Látogatószámláló',
    'evfordulok'       => 'Évfordulók',
    'bevasarlolista'   => 'Bevásárlólista',
    'idopontfoglalo'   => 'Önálló időpontfoglaló (letöltőkóddal)',
];

// Keresőrobotok, linkelőnézetek (Facebook, Messenger…) és HEAD kérések nem számítanak.
function is_bot_request(): bool {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') return true;
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    return $ua === '' || (bool) preg_match('/bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp|telegram|discord|curl|wget|python|httpclient|monitor|headless/i', $ua);
}

// A letöltést soha nem akaszthatja meg: hiba esetén csak naplóz.
function count_download(string $product): void {
    if (!isset(DOWNLOAD_PRODUCTS[$product]) || is_bot_request()) return;
    $sql = 'INSERT INTO {downloads} (day, product, n) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE n = n + 1';
    try {
        try {
            q_exec($sql, [date('Y-m-d'), $product]);
        } catch (Throwable $e) {
            db()->query(sql_tables(DOWNLOADS_SQL)); // frissítés után még nincs tábla
            q_exec($sql, [date('Y-m-d'), $product]);
        }
    } catch (Throwable $e) {
        error_log('[letöltésszámláló] ' . $e->getMessage());
    }
}

function download_stats(string $today): array {
    $d7 = date('Y-m-d', strtotime("$today -6 days"));
    $d30 = date('Y-m-d', strtotime("$today -29 days"));
    $rows = [];
    foreach (DOWNLOAD_PRODUCTS as $id => $name) $rows[$id] = ['id' => $id, 'name' => $name, 'today' => 0, 'd7' => 0, 'd30' => 0, 'total' => 0, 'last' => ''];
    foreach (q_all('SELECT product, day, n FROM {downloads} ORDER BY day') as $r) {
        if (!isset($rows[$r['product']])) continue;
        $x = &$rows[$r['product']];
        $n = (int) $r['n'];
        $x['total'] += $n;
        if ($r['day'] >= $d30) $x['d30'] += $n;
        if ($r['day'] >= $d7) $x['d7'] += $n;
        if ($r['day'] === $today) $x['today'] += $n;
        $x['last'] = $r['day'];
        unset($x);
    }
    // napi bontás az utolsó 30 napra (minden app együtt és külön)
    $days = [];
    for ($i = 29; $i >= 0; $i--) $days[date('Y-m-d', strtotime("$today -$i days"))] = [];
    foreach (q_all('SELECT product, day, n FROM {downloads} WHERE day >= ?', [$d30]) as $r) {
        if (isset($days[$r['day']])) $days[$r['day']][$r['product']] = (int) $r['n'];
    }
    $daily = [];
    foreach ($days as $day => $by) $daily[] = ['day' => $day, 'total' => array_sum($by), 'by' => (object) $by];
    usort($rows, fn ($a, $b) => $b['total'] <=> $a['total']);
    return ['products' => array_values($rows), 'daily' => $daily, 'since' => (string) (q_one('SELECT MIN(day) AS d FROM {downloads}')['d'] ?? '')];
}
