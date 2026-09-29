<?php
declare(strict_types=1);

// Beállítások: egyetlen 'options' csoport a settings táblában.

function default_options(): array {
    return [
        'site_name'      => 'my-ai.hu',
        'anonymize_ip'   => false, // IPv4 utolsó bájtja / IPv6 vége nullázva
        'exclude_ips'    => [],    // pl. a saját IP-d, hogy ne számoljon
        'retention_days' => 395,   // ennél régebbi adatok automatikusan törlődnek
    ];
}

function get_options(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $row = q_one("SELECT v FROM {settings} WHERE k = 'options'");
    $saved = $row ? (json_decode((string) $row['v'], true) ?: []) : [];
    return $cache = array_merge(default_options(), $saved);
}

function save_options(array $o): void {
    q_exec("INSERT INTO {settings} (k, v) VALUES ('options', ?) ON DUPLICATE KEY UPDATE v = VALUES(v)", [json_encode($o, JSON_UNESCAPED_UNICODE)]);
}

// A látogató valódi IP-je. A DiMán nginx áll az Apache előtt: ha a közvetlen
// cím belső hálózati, a továbbított fejlécből olvassuk ki.
function visitor_ip(): string {
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $private = fn (string $a) => !filter_var($a, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    if ($ip === '' || $private($ip)) {
        foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP'] as $h) {
            foreach (array_map('trim', explode(',', (string) ($_SERVER[$h] ?? ''))) as $cand) {
                if (filter_var($cand, FILTER_VALIDATE_IP) && !$private($cand)) return $cand;
            }
        }
    }
    return substr($ip, 0, 45);
}

function anonymize_ip(string $ip): string {
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return preg_replace('/\.\d+$/', '.0', $ip);
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $parts = explode(':', inet_ntop(inet_pton($ip)));
        return implode(':', array_slice($parts, 0, 3)) . '::';
    }
    return '';
}
