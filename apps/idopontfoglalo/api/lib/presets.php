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
            'tagline'  => 'Hajvágás, festés, styling — foglalj időpontot pár kattintással.',
            'rules'    => ['slot_step' => 15, 'capacity' => 2, 'lead_hours' => 3, 'max_days' => 60],
            'hours'    => $week(['09:00', '18:00'], ['09:00', '14:00']),
            'services' => [
                ['Női hajvágás', 'Mosással, vágással, szárítással.', 60, '9 500 Ft'],
                ['Férfi hajvágás', 'Mosással, vágással.', 30, '5 500 Ft'],
                ['Gyermek hajvágás', '10 éves korig.', 30, '4 000 Ft'],
                ['Tőfestés + szárítás', 'Lenövés festése, mosással, szárítással.', 90, '16 000 Ft-tól'],
                ['Teljes festés', 'Mosással, szárítással; az ár a hajhossztól függ.', 120, '19 000 Ft-tól'],
                ['Melír / balayage', 'Konzultációval; az ár a hajhossztól függ.', 150, '25 000 Ft-tól'],
                ['Szárítás, styling', '', 30, '5 500 Ft'],
                ['Alkalmi frizura', 'Esküvőre, rendezvényre — kérésre próbafrizurával.', 60, '12 000 Ft-tól'],
            ],
            'fields' => [
                ['key' => 'stylist', 'label' => 'Kihez szeretnél menni?', 'type' => 'select', 'required' => false, 'options' => ['Mindegy', 'Anna', 'Bence']],
                ['key' => 'hair', 'label' => 'Hajhossz', 'type' => 'select', 'required' => false, 'options' => ['Rövid', 'Közepes (vállig)', 'Hosszú']],
            ],
            'texts' => [
                'terms' => 'Ha mégsem tudsz jönni, kérjük, legalább 24 órával előtte mondd le az időpontot (a visszaigazoló levélben lévő linkkel).',
            ],
        ],
        'autoszerelo' => [
            'label'    => 'Autószerelő műhely',
            'primary'  => '#2f5d8a',
            'tagline'  => 'Szerviz, javítás, diagnosztika — foglalj időpontot, és nem kell sorban állni.',
            'rules'    => ['slot_step' => 30, 'capacity' => 2, 'lead_hours' => 4, 'max_days' => 45],
            'hours'    => $week(['07:30', '17:00']),
            'services' => [
                ['Olajcsere + szűrők', 'Motorolaj és olajszűrő cseréje, levegő- és pollenszűrő ellenőrzésével.', 60, '25 000 Ft-tól (anyaggal)'],
                ['Kis szerviz', 'Olaj, szűrők, folyadékszintek, fékek és futómű átvizsgálása.', 90, '35 000 Ft-tól'],
                ['Fékbetét csere (tengelyenként)', 'A féktárcsák ellenőrzésével.', 60, '15 000 Ft + alkatrész'],
                ['Hibakód-olvasás, diagnosztika', 'Számítógépes hibakeresés, a hibák magyarázatával.', 30, '8 000 Ft'],
                ['Műszaki vizsga felkészítés', 'Átvizsgálás és a szükséges javítások, vizsgára kísérés kérésre.', 120, '20 000 Ft-tól'],
                ['Klímatöltés, fertőtlenítés', '', 60, '18 000 Ft'],
                ['Átvizsgálás vásárlás előtt', 'Használt autó állapotfelmérése, írásos összefoglalóval.', 60, '15 000 Ft'],
            ],
            'fields' => [
                ['key' => 'plate', 'label' => 'Rendszám', 'type' => 'text', 'required' => true, 'options' => []],
                ['key' => 'car', 'label' => 'Autó márkája, típusa', 'type' => 'text', 'required' => true, 'options' => []],
                ['key' => 'year', 'label' => 'Évjárat', 'type' => 'text', 'required' => false, 'options' => []],
                ['key' => 'km', 'label' => 'Kilométeróra-állás', 'type' => 'text', 'required' => false, 'options' => []],
                ['key' => 'problem', 'label' => 'A hiba vagy a kérés leírása', 'type' => 'textarea', 'required' => false, 'options' => []],
            ],
            'texts' => [
                'terms' => 'Az ár tájékoztató jellegű: a pontos költséget az átvizsgálás után, a javítás előtt egyeztetjük veled.',
            ],
        ],
        'fogorvos' => [
            'label'    => 'Fogászat / fogorvosi rendelő',
            'primary'  => '#0e7490',
            'tagline'  => 'Fogászati kezelések előre egyeztetett időpontban, várakozás nélkül.',
            'rules'    => ['slot_step' => 15, 'capacity' => 1, 'lead_hours' => 12, 'max_days' => 90],
            'hours'    => ['1' => [['08:00', '14:00']], '2' => [['12:00', '19:00']], '3' => [['08:00', '14:00']], '4' => [['12:00', '19:00']], '5' => [['08:00', '13:00']], '6' => [], '0' => []],
            'services' => [
                ['Fogászati kontroll, állapotfelmérés', 'Átvizsgálás és kezelési terv.', 30, '12 000 Ft'],
                ['Fogkőeltávolítás, polírozás', 'Ultrahangos fogkőeltávolítás és polírozás.', 45, '18 000 Ft'],
                ['Fogtömés (esztétikus)', 'Fogszínű tömés.', 45, '20 000 Ft-tól'],
                ['Foghúzás', '', 30, '15 000 Ft-tól'],
                ['Rendelői fogfehérítés', '', 90, '60 000 Ft'],
                ['Gyermekfogászat', 'Kontroll és kezelés gyermekeknek.', 30, '10 000 Ft'],
                ['Sürgős ellátás (fogfájás)', 'Fájdalomcsillapító ellátás. Nagyon erős fájdalomnál hívj telefonon!', 30, '15 000 Ft-tól'],
            ],
            'fields' => [
                ['key' => 'visited', 'label' => 'Jártál már nálunk?', 'type' => 'select', 'required' => true, 'options' => ['Nem, ez az első alkalom', 'Igen']],
                ['key' => 'note', 'label' => 'Panasz vagy kérés röviden (nem kötelező)', 'type' => 'textarea', 'required' => false, 'options' => []],
            ],
            'texts' => [
                'terms' => 'Kérjük, egészségügyi adatot csak a szükséges mértékben adj meg. Ha mégsem tudsz jönni, legalább 24 órával előtte mondd le az időpontot.',
            ],
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
    if (!empty($preset['texts'])) save_setting_group('texts', array_merge($defaults['texts'], $preset['texts']));

    foreach ($preset['services'] as $i => [$name, $desc, $duration, $price]) {
        q_exec(
            'INSERT INTO {services} (name, description, duration_min, price, active, sort) VALUES (?, ?, ?, ?, 1, ?)',
            [$name, $desc, $duration, $price, $i]
        );
    }
}
