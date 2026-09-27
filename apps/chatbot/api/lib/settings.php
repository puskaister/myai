<?php
declare(strict_types=1);

// A chatbot minden beállítása a settings táblában él, csoportonként egy JSON
// sorban. Az admin felületről minden átírható: a bot "betanítása" (tudásanyag,
// kötelező kérdések, hangnem) is itt tárolódik.

const SETTING_GROUPS = ['business', 'bot', 'questions', 'theme', 'notify', 'ai'];

// Választható modellek (az admin dönt; az alapértelmezés a legokosabb).
const AI_MODELS = [
    'claude-opus-5'    => 'Claude Opus 5 – legokosabb (kb. 50–90 Ft / beszélgetés)',
    'claude-sonnet-5'  => 'Claude Sonnet 5 – kiegyensúlyozott (kb. 25–35 Ft / beszélgetés)',
    'claude-haiku-4-5' => 'Claude Haiku 4.5 – leggyorsabb, legolcsóbb (kb. 12–18 Ft / beszélgetés)',
];

// USD / 1M token (bemenet, kimenet, cache-olvasás) — csak a költségbecsléshez.
const AI_PRICES = [
    'claude-opus-5'    => [5.00, 25.00, 0.50],
    'claude-sonnet-5'  => [2.00, 10.00, 0.20],
    'claude-haiku-4-5' => [1.00, 5.00, 0.10],
];

function default_settings(): array {
    return [
        'business' => [
            'name'    => 'Ügyfélszolgálat',
            'website' => '',
            'phone'   => '',
            'email'   => '',
        ],
        'bot' => [
            'name'        => 'Asszisztens',
            'greeting'    => 'Szia! Miben segíthetek?',
            'tone'        => 'Barátságos, közvetlen, tegeződő; rövid, érthető mondatok.',
            'knowledge'   => '',   // amit a botnak tudnia kell: nyitvatartás, árak, GYIK…
            'instructions'=> '',   // extra szabályok
            'completion'  => 'Köszönöm, minden szükséges adatot megkaptam! Kollégánk hamarosan felveszi veled a kapcsolatot.',
        ],
        // A "betanított" fő kérdések: a bot ezeket mindenképp felteszi, és a
        // válaszokat külön rögzíti. [{key, question, hint, required}]
        'questions' => [],
        'theme' => [
            'primary' => '#4f46e5',
        ],
        'notify' => [
            'admin_email' => '',
            'send_admin'  => true,
        ],
        'ai' => [
            'model'             => 'claude-opus-5',
            'max_user_messages' => 30,   // beszélgetésenként ennyi látogatói üzenet
            'daily_limit'       => 300,  // naponta legfeljebb ennyi új beszélgetés
        ],
    ];
}

function get_settings(): array {
    static $cache = null;
    if ($cache !== null) return $cache;

    $settings = default_settings();
    $res = db()->query(sql_tables('SELECT k, v FROM {settings}'));
    while ($res && ($row = $res->fetch_assoc())) {
        if (!in_array($row['k'], SETTING_GROUPS, true)) continue;
        $value = json_decode((string) $row['v'], true);
        if (!is_array($value)) continue;
        $settings[$row['k']] = $row['k'] === 'questions' ? $value : array_merge($settings[$row['k']], $value);
    }
    $cache = $settings;
    return $settings;
}

function save_setting_group(string $group, array $value): void {
    if (!in_array($group, SETTING_GROUPS, true)) throw new AppError("Ismeretlen beállítás: $group");
    q_exec(
        'INSERT INTO {settings} (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)',
        [$group, json_encode($value, JSON_UNESCAPED_UNICODE)]
    );
}

// A látogatói oldalnak szánt, nyilvános beállítások (nincs benne tudásanyag,
// kérdéslista vagy értesítési cím).
function public_settings(): array {
    $s = get_settings();
    return [
        'business' => ['name' => $s['business']['name'], 'website' => $s['business']['website'], 'phone' => $s['business']['phone']],
        'bot'      => ['name' => $s['bot']['name'], 'greeting' => $s['bot']['greeting']],
        'theme'    => ['primary' => $s['theme']['primary']],
        'questions_total' => count(array_filter($s['questions'], fn ($q) => !empty($q['required']))),
    ];
}

function on_primary(string $hex): string {
    [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');
    return (0.299 * $r + 0.587 * $g + 0.114 * $b) > 160 ? '#111827' : '#ffffff';
}
