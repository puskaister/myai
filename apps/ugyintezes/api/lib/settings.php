<?php
declare(strict_types=1);

// Beállítások: az 'options' csoport a settings táblában (az admin felületen szerkeszthető).

function default_options(): array {
    return [
        'price_net'     => 5000,  // havidíj, nettó Ft
        'vat_percent'   => 27,    // ÁFA %
        'admin_email'   => '',    // ide jönnek a megrendelések és a lejárati értesítések
        'payment_info'  => '',    // pl. kedvezményezett, számlaszám — bekerül a díjbekérő emlékeztetőkbe
        'remind_days'   => [7, 1], // ennyi nappal a lejárat előtt megy emlékeztető
    ];
}

function get_options(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $row = q_one("SELECT v FROM {settings} WHERE k = 'options'");
    return $cache = array_merge(default_options(), $row ? (json_decode((string) $row['v'], true) ?: []) : []);
}

function save_options(array $o): void {
    q_exec("INSERT INTO {settings} (k, v) VALUES ('options', ?) ON DUPLICATE KEY UPDATE v = VALUES(v)", [json_encode($o, JSON_UNESCAPED_UNICODE)]);
}

function setting_value(string $k): ?string {
    $row = q_one('SELECT v FROM {settings} WHERE k = ?', [$k]);
    return $row ? (string) $row['v'] : null;
}

function set_setting_value(string $k, string $v): void {
    q_exec('INSERT INTO {settings} (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)', [$k, $v]);
}

function client_ip(): string {
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

// A napi emlékeztető-végpont kulcsa (a DB_PASS-ból, mint az Évfordulóknál).
function cron_key(): string {
    return substr(hash_hmac('sha256', 'cron|' . table_prefix(), (string) (app_config()['db']['pass'] ?? '')), 0, 32);
}
