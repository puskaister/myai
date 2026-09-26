<?php
declare(strict_types=1);

// A cég minden beállítása a settings táblában él, csoportonként egy JSON
// sorban (business, theme, rules, hours, closures, fields, contact, texts,
// notify). Így az admin felületről minden átszabható, kódmódosítás nélkül.

const SETTING_GROUPS = ['business', 'theme', 'rules', 'hours', 'closures', 'fields', 'contact', 'texts', 'notify'];

function default_settings(): array {
    $workday = [['08:00', '17:00']];
    return [
        'business' => [
            'name'    => 'Időpontfoglalás',
            'tagline' => '',
            'phone'   => '',
            'email'   => '',
            'address' => '',
            'website' => '',
        ],
        'theme' => [
            'primary' => '#2563eb',
            'logo'    => '', // uploads/ alatti fájlnév
        ],
        'rules' => [
            'slot_step'      => 30,   // perc: ilyen lépésközzel kínálunk kezdési időpontot
            'capacity'       => 1,    // ennyi foglalás futhat párhuzamosan (pl. emelők, székek száma)
            'lead_hours'     => 2,    // legalább ennyi órával előre lehet foglalni
            'max_days'       => 60,   // legfeljebb ennyi napra előre
            'approval'       => 'auto', // auto | manual
            'customer_cancel'=> true,
            'cancel_hours'   => 24,   // az ügyfél eddig mondhatja le a kezdés előtt
        ],
        // A kulcs a hét napja PHP date('w') szerint: 0 = vasárnap … 6 = szombat.
        // Naponta több sáv is megadható (pl. ebédszünettel).
        'hours' => [
            '1' => $workday, '2' => $workday, '3' => $workday, '4' => $workday, '5' => $workday,
            '6' => [], '0' => [],
        ],
        'closures' => [], // [{from: 'YYYY-MM-DD', to: 'YYYY-MM-DD', reason: ''}]
        'fields'   => [], // extra mezők: [{key, label, type: text|textarea|select, required, options: []}]
        'contact'  => [
            'phone_required' => true,
            'email_required' => true,
        ],
        'texts' => [
            'intro'   => 'Válassz szolgáltatást és időpontot — pár kattintás az egész.',
            'success' => 'Köszönjük a foglalást! A részleteket emailben is elküldtük.',
            'terms'   => '',
        ],
        'notify' => [
            'admin_email'   => '',
            'send_customer' => true,
            'send_admin'    => true,
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
        // Listaszerű csoportokat egyben cserélünk, a többit kulcsonként
        // egyesítjük az alapértékekkel (így egy új beállítás sem hiányzik).
        $settings[$row['k']] = in_array($row['k'], ['hours', 'closures', 'fields'], true)
            ? $value
            : array_merge($settings[$row['k']], $value);
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

// Az ügyféloldalnak szánt, nyilvános beállítások (nincs benne pl. admin email).
function public_settings(): array {
    $s = get_settings();
    return [
        'business' => $s['business'],
        'theme'    => [
            'primary' => $s['theme']['primary'],
            'logo'    => $s['theme']['logo'] !== '' ? 'uploads/' . $s['theme']['logo'] : '',
        ],
        'rules' => [
            'max_days'        => (int) $s['rules']['max_days'],
            'approval'        => $s['rules']['approval'],
            'customer_cancel' => (bool) $s['rules']['customer_cancel'],
            'cancel_hours'    => (int) $s['rules']['cancel_hours'],
        ],
        'fields'  => $s['fields'],
        'contact' => $s['contact'],
        'texts'   => $s['texts'],
    ];
}
