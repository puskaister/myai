<?php
declare(strict_types=1);

// Telepítéskor választható kiinduló csomagok. Csak az első feltöltést adják:
// utána minden az admin felületen szabadon átírható.

function presets(): array {
    $week = fn (array $day, array $sat = []) => [
        '1' => [$day], '2' => [$day], '3' => [$day], '4' => [$day], '5' => [$day],
        '6' => $sat ? [$sat] : [], '0' => [],
    ];

    return [
        'gumiszerviz' => [
            'label'    => 'Gumiszerviz',
            'primary'  => '#e4572e',
            'tagline'  => 'Gumicsere, centírozás, defektjavítás — várakozás nélkül.',
            'rules'    => ['slot_step' => 30, 'capacity' => 2, 'lead_hours' => 2, 'max_days' => 45],
            'hours'    => $week(['08:00', '17:00'], ['08:00', '12:00']),
            'services' => [
                ['Szezonális gumicsere (felnire szerelt)', 'Kerekek cseréje, nyomásellenőrzéssel.', 30, '8 000 Ft'],
                ['Gumiszerelés + centírozás (4 kerék)', 'Gumi le- és felszerelése felnire, kiegyensúlyozással.', 60, '16 000 Ft'],
                ['Centírozás (4 kerék)', 'Kerekek kiegyensúlyozása.', 30, '8 000 Ft'],
                ['Defektjavítás', 'Egy kerék javítása belülről foltozva.', 30, '4 000 Ft-tól'],
                ['Gumitárolás leadás', 'Szezonális gumik beadása tárolásra, cserével együtt.', 30, '12 000 Ft / szezon'],
            ],
            'fields' => [
                ['key' => 'plate', 'label' => 'Rendszám', 'type' => 'text', 'required' => true, 'options' => []],
                ['key' => 'car', 'label' => 'Autó típusa', 'type' => 'text', 'required' => false, 'options' => []],
                ['key' => 'tyre', 'label' => 'Gumiméret (pl. 205/55 R16)', 'type' => 'text', 'required' => false, 'options' => []],
            ],
        ],
        'fodraszat' => [
            'label'    => 'Fodrászat / szépségszalon',
            'primary'  => '#b5487d',
            'tagline'  => 'Foglalj időpontot pár kattintással.',
            'rules'    => ['slot_step' => 15, 'capacity' => 1, 'lead_hours' => 3, 'max_days' => 60],
            'hours'    => $week(['09:00', '18:00'], ['09:00', '13:00']),
            'services' => [
                ['Női hajvágás', 'Mosással, szárítással.', 60, '9 000 Ft'],
                ['Férfi hajvágás', '', 30, '5 000 Ft'],
                ['Festés', 'Tőfestés vagy teljes festés.', 120, '18 000 Ft-tól'],
                ['Szárítás, styling', '', 30, '5 000 Ft'],
            ],
            'fields' => [],
        ],
        'rendelo' => [
            'label'    => 'Rendelő / tanácsadás',
            'primary'  => '#0f766e',
            'tagline'  => 'Online időpontfoglalás.',
            'rules'    => ['slot_step' => 20, 'capacity' => 1, 'lead_hours' => 12, 'max_days' => 90],
            'hours'    => $week(['08:00', '16:00']),
            'services' => [
                ['Első konzultáció', 'Részletes állapotfelmérés.', 40, ''],
                ['Kontroll vizsgálat', '', 20, ''],
            ],
            'fields' => [
                ['key' => 'reason', 'label' => 'A panasz / megkeresés rövid leírása', 'type' => 'textarea', 'required' => false, 'options' => []],
            ],
        ],
        'altalanos' => [
            'label'    => 'Általános',
            'primary'  => '#2563eb',
            'tagline'  => '',
            'rules'    => ['slot_step' => 30, 'capacity' => 1, 'lead_hours' => 2, 'max_days' => 60],
            'hours'    => $week(['09:00', '17:00']),
            'services' => [
                ['Konzultáció', '', 60, ''],
            ],
            'fields' => [],
        ],
    ];
}

// A választott csomag beírása egy üres (frissen telepített) adatbázisba.
function apply_preset(string $key, string $businessName, string $adminEmail): void {
    $preset = presets()[$key] ?? presets()['altalanos'];
    $defaults = default_settings();

    save_setting_group('business', array_merge($defaults['business'], [
        'name'    => $businessName,
        'tagline' => $preset['tagline'],
        'email'   => $adminEmail,
    ]));
    save_setting_group('theme', array_merge($defaults['theme'], ['primary' => $preset['primary']]));
    save_setting_group('rules', array_merge($defaults['rules'], $preset['rules']));
    save_setting_group('hours', $preset['hours']);
    save_setting_group('fields', $preset['fields']);
    save_setting_group('notify', array_merge($defaults['notify'], ['admin_email' => $adminEmail]));

    foreach ($preset['services'] as $i => [$name, $desc, $duration, $price]) {
        q_exec(
            'INSERT INTO {services} (name, description, duration_min, price, active, sort) VALUES (?, ?, ?, ?, 1, ?)',
            [$name, $desc, $duration, $price, $i]
        );
    }
}
