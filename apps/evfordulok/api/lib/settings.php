<?php
declare(strict_types=1);

// Beállítások: az 'options' csoport és az utolsó emlékeztető-kör napja a
// settings táblában.

function default_options(): array {
    return [
        'app_name'       => 'Évfordulók',
        'allow_register' => false, // bárki regisztrálhat-e saját fiókot
        'send_hour'      => 7,     // ennyi óra előtt nem küldünk (az oldalmegnyitásos tartalék-körnél)
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

// A napi emlékeztető-végpont kulcsa. Az adatbázis-jelszóból számoljuk, így a
// GitHub Actions ütemező a meglévő DB_PASS secretből ugyanezt ki tudja számolni.
function cron_key(): string {
    return substr(hash_hmac('sha256', 'cron|' . table_prefix(), (string) (app_config()['db']['pass'] ?? '')), 0, 32);
}

function client_ip(): string {
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    return substr($ip, 0, 45);
}
