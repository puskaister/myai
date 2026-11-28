<?php
// Kiberbiztonsági gyorsellenőrzés – domain-vizsgálat, csak nyilvános adatokból:
// MX, SPF, DMARC, gyakori DKIM-szelektorok, HTTPS-tanúsítvány, http → https
// átirányítás és a weboldal biztonsági fejlécei. Port- vagy sebezhetőség-
// szkennelést NEM végez (idegen rendszeren engedély nélkül az jogilag aggályos).
//
// Belső hálózatra nem csatlakozik: a domain IP-címét előre feloldja, magán- és
// fenntartott tartományt elutasít, és a kapcsolatot erre az IP-re rögzíti
// (DNS-rebinding ellen). Használja: /kiberbiztonsag/api/domain.php.
declare(strict_types=1);

const DC_DKIM_SELECTORS = ['default', 'google', 'selector1', 'selector2', 'k1', 'k2', 'k3', 'mail', 'dkim', 's1', 's2', 'smtp', 'mx', 'key1', 'everlytickey1', 'mandrill', 'sendgrid', 'zoho'];
const DC_TIMEOUT = 6; // másodperc / hálózati lépés

// "https://www.Példa.hu/valami" → "pelda.hu" (IDN → punycode). Hibás bemenetnél null.
function dc_normalize_domain(string $input): ?string {
    $d = strtolower(trim($input));
    $d = preg_replace('~^[a-z][a-z0-9+.-]*://~', '', $d);
    $d = preg_replace('~[/?#:].*$~', '', $d);
    if (strpos($d, '@') !== false) $d = substr($d, strrpos($d, '@') + 1); // e-mail-címet is elfogad
    $d = rtrim($d, '.');
    if (strpos($d, 'www.') === 0) $d = substr($d, 4);
    if ($d !== '' && preg_match('/[^\x20-\x7e]/', $d) && function_exists('idn_to_ascii')) {
        $d = (string) idn_to_ascii($d, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
    }
    if (strlen($d) > 253 || !preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z][a-z0-9-]{1,62}$/', $d)) return null;
    return $d;
}

function dc_short(string $s, int $max = 160): string {
    if (strlen($s) <= $max) return $s;
    return (function_exists('mb_strcut') ? mb_strcut($s, 0, $max, 'UTF-8') : substr($s, 0, $max)) . '…';
}

function dc_txt(string $host): array {
    $recs = @dns_get_record($host, DNS_TXT);
    if (!is_array($recs)) return [];
    $out = [];
    foreach ($recs as $r) {
        if (($r['type'] ?? '') !== 'TXT') continue;
        $out[] = isset($r['entries']) && is_array($r['entries']) ? implode('', $r['entries']) : (string) ($r['txt'] ?? '');
    }
    return $out;
}

// Az első nyilvános IPv4/IPv6 cím, vagy null (nincs cím / csak belső cím van).
function dc_public_ip(string $host): ?string {
    $ips = [];
    foreach ((array) @dns_get_record($host, DNS_A) as $r) if (!empty($r['ip'])) $ips[] = $r['ip'];
    foreach ((array) @dns_get_record($host, DNS_AAAA) as $r) if (!empty($r['ipv6'])) $ips[] = $r['ipv6'];
    foreach ($ips as $ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return $ip;
    }
    return null;
}

// Egy ellenőrzési tétel. status: ok | warn | bad | info; points: elért / max pont.
function dc_item(string $id, string $label, string $status, int $points, int $max, string $detail, ?array $fix = null): array {
    return ['id' => $id, 'label' => $label, 'status' => $status, 'points' => $points, 'max' => $max, 'detail' => $detail, 'fix' => $fix];
}

function dc_fix(string $prio, string $title, array $steps): array {
    return ['prio' => $prio, 'title' => $title, 'steps' => $steps];
}

function dc_check_mail(string $d): array {
    $items = [];

    // MX
    $mx = [];
    foreach ((array) @dns_get_record($d, DNS_MX) as $r) if (!empty($r['target'])) $mx[] = ['host' => strtolower($r['target']), 'pri' => (int) ($r['pri'] ?? 0)];
    usort($mx, fn ($a, $b) => $a['pri'] <=> $b['pri']);
    $hasMail = (bool) $mx;
    $provider = '';
    $mxHosts = implode(' ', array_column($mx, 'host'));
    if (preg_match('/google(mail)?\.com|googlemail/', $mxHosts)) $provider = 'Google Workspace';
    elseif (strpos($mxHosts, 'outlook.com') !== false || strpos($mxHosts, 'protection.outlook') !== false) $provider = 'Microsoft 365';
    elseif (strpos($mxHosts, 'zoho') !== false) $provider = 'Zoho Mail';
    $items[] = dc_item('mx', 'Levelezőszerver (MX)', 'info', 0, 0,
        $hasMail ? 'A domain fogad e-mailt: ' . implode(', ', array_slice(array_column($mx, 'host'), 0, 3)) . ($provider ? " ($provider)" : '') . '.'
                 : 'A domainhez nincs MX-rekord, vagyis nem fogad e-mailt. A hamisítás ellen ilyenkor is érdemes tiltó SPF- és DMARC-rekordot beállítani.');

    // SPF
    $spf = array_values(array_filter(dc_txt($d), fn ($t) => stripos(ltrim($t), 'v=spf1') === 0));
    if (!$spf) {
        $items[] = dc_item('spf', 'SPF – ki küldhet levelet a domain nevében', 'bad', 0, 20,
            'Nincs SPF-rekord: bárki küldhet levelet a cég nevében, és a fogadó szerverek nem tudják kiszűrni.',
            dc_fix('now', 'SPF-rekord beállítása a domainen', [
                'Gyűjtsd össze, mi küld levelet a domain nevében (levelezés, webshop, hírlevél, számlázó).',
                $provider === 'Google Workspace' ? 'Google Workspace esetén a DNS-be: v=spf1 include:_spf.google.com -all'
                    : ($provider === 'Microsoft 365' ? 'Microsoft 365 esetén a DNS-be: v=spf1 include:spf.protection.outlook.com -all'
                    : ($hasMail ? 'Kérd el a levelezésszolgáltatótól a pontos SPF-rekordot, és vedd fel TXT-rekordként.' : 'Ha a domainről nem mennek levelek: v=spf1 -all')),
                'A tárhely- vagy domainszolgáltató DNS-kezelőjében vedd fel TXT-rekordként a domain gyökerére.',
            ]));
    } elseif (count($spf) > 1) {
        $items[] = dc_item('spf', 'SPF – ki küldhet levelet a domain nevében', 'bad', 5, 20,
            'Több SPF-rekord is van (' . count($spf) . ' db). Ez hibás beállítás, ilyenkor a fogadó szerverek érvénytelennek tekintik az SPF-et.',
            dc_fix('now', 'Az SPF-rekordok összevonása egyetlen rekordba', ['A DNS-kezelőben vonj össze minden v=spf1 kezdetű TXT-rekordot egyetlen rekordba (az include: részeket egymás után).', 'Ellenőrizd újra ezzel az oldallal.']));
    } else {
        $rec = trim($spf[0]);
        $lookups = preg_match_all('/\b(include:|a\b|a:|mx\b|mx:|ptr|exists:|redirect=)/i', $rec);
        if (preg_match('/[-]all\b/i', $rec)) { $st = 'ok'; $pts = 20; $txt = 'Szigorú SPF (-all): a felsoroltakon kívül senki nem küldhet a domain nevében.'; $fix = null; }
        elseif (preg_match('/~all\b/i', $rec)) { $st = 'ok'; $pts = 17; $txt = 'Van SPF-rekord (~all, „puha” elutasítás). Megfelelő, ha mellette DMARC is van.'; $fix = null; }
        elseif (preg_match('/redirect=/i', $rec)) { $st = 'ok'; $pts = 17; $txt = 'Van SPF-rekord (átirányítással egy másik rekordra).'; $fix = null; }
        else {
            $st = 'bad'; $pts = 6;
            $txt = 'Van SPF-rekord, de nem tiltja a többi küldőt (' . (preg_match('/[+?]all\b/i', $rec) ? 'a vége +all vagy ?all' : 'nincs a végén -all vagy ~all') . '), így szinte semmit nem véd.';
            $fix = dc_fix('now', 'Az SPF-rekord szigorítása', ['A rekord végét állítsd -all-ra (vagy átmenetileg ~all-ra).', 'Előtte ellenőrizd, hogy minden levélküldő szolgáltatás szerepel benne.']);
        }
        if ($lookups > 10) { $st = 'warn'; $pts = min($pts, 10); $txt .= ' Figyelem: több mint 10 DNS-lekérdezést igényel, ez túllépi a szabvány korlátját.'; }
        $items[] = dc_item('spf', 'SPF – ki küldhet levelet a domain nevében', $st, $pts, 20, $txt . ' Rekord: ' . dc_short($rec), $fix);
    }

    // DMARC
    $dmarc = array_values(array_filter(dc_txt("_dmarc.$d"), fn ($t) => stripos(ltrim($t), 'v=DMARC1') === 0));
    if (!$dmarc) {
        $items[] = dc_item('dmarc', 'DMARC – mi történjen a hamisított levelekkel', 'bad', 0, 25,
            'Nincs DMARC-rekord, így a cég nevében hamisított levelek nagy eséllyel bekerülnek a címzettek postafiókjába.',
            dc_fix('now', 'DMARC-rekord beállítása', [
                'Vegyél fel egy TXT-rekordot _dmarc néven: v=DMARC1; p=none; rua=mailto:dmarc@' . $d,
                '2–4 hét után, ha a jelentések szerint minden saját levél átmegy, szigoríts: p=quarantine, majd p=reject.',
                'A jelentések olvasásához ingyenes DMARC-elemző szolgáltatás is használható.',
            ]));
    } else {
        $rec = trim($dmarc[0]);
        preg_match('/\bp\s*=\s*(\w+)/i', $rec, $m);
        $p = strtolower($m[1] ?? '');
        $rua = (bool) preg_match('/\brua\s*=/i', $rec);
        if ($p === 'reject') { $st = 'ok'; $pts = 25; $txt = 'Szigorú DMARC (p=reject): a hamisított leveleket a fogadó szerverek elutasítják.'; $fix = null; }
        elseif ($p === 'quarantine') { $st = 'ok'; $pts = 22; $txt = 'DMARC p=quarantine: a hamisított levelek a spam mappába kerülnek.'; $fix = null; }
        else {
            $st = 'warn'; $pts = 10;
            $txt = 'Van DMARC-rekord, de csak megfigyel (p=none), a hamisított leveleket nem szűri ki.';
            $fix = dc_fix('soon', 'A DMARC szigorítása (p=quarantine, majd p=reject)', [
                $rua ? 'Nézd át a DMARC-jelentéseket: minden saját levélküldőd átmegy-e az SPF/DKIM ellenőrzésen?' : 'Adj a rekordhoz jelentéscímet (rua=mailto:…), és pár hétig figyeld a jelentéseket.',
                'Ha igen, állítsd át: p=quarantine, majd később p=reject.',
            ]);
        }
        $items[] = dc_item('dmarc', 'DMARC – mi történjen a hamisított levelekkel', $st, $pts, 25, $txt . ($rua ? '' : ' Jelentéscím (rua) nincs beállítva.') . ' Rekord: ' . dc_short($rec), $fix);
    }

    // DKIM (csak a gyakori szelektorokat lehet kívülről megnézni)
    if ($hasMail) {
        $found = [];
        foreach (DC_DKIM_SELECTORS as $sel) {
            $host = "$sel._domainkey.$d";
            $txt = dc_txt($host);
            if (array_filter($txt, fn ($t) => stripos($t, 'p=') !== false) || @dns_get_record($host, DNS_CNAME)) $found[] = $sel;
            if (count($found) >= 3) break;
        }
        $items[] = $found
            ? dc_item('dkim', 'DKIM – digitális aláírás a leveleken', 'ok', 10, 10, 'DKIM-kulcsot találtunk (szelektor: ' . implode(', ', $found) . ').')
            : dc_item('dkim', 'DKIM – digitális aláírás a leveleken', 'warn', 4, 10,
                'A gyakori szelektorokon nem találtunk DKIM-kulcsot. Lehet, hogy egyedi néven van beállítva, ezt kívülről nem lehet biztosan megállapítani.',
                dc_fix('soon', 'DKIM-aláírás bekapcsolása', [
                    $provider === 'Google Workspace' ? 'Google Admin → Alkalmazások → Gmail → E-mail hitelesítése: kulcs generálása, majd a TXT-rekord felvétele a DNS-be.'
                        : ($provider === 'Microsoft 365' ? 'Microsoft Defender portál → E-mail hitelesítés → DKIM: a két CNAME-rekord felvétele, majd az aláírás bekapcsolása.'
                        : 'Kérdezd meg a levelezésszolgáltatót, hogyan kapcsolható be a DKIM-aláírás, és vedd fel a kapott rekordot a DNS-be.'),
                    'Küldj egy próbalevelet egy Gmail-címre, és a „Eredeti megjelenítése” nézetben ellenőrizd: DKIM: PASS.',
                ]));
    }
    return $items;
}

function dc_check_web(string $d): array {
    $items = [];
    $host = dc_public_ip("www.$d") && !dc_public_ip($d) ? "www.$d" : $d;
    $ip = dc_public_ip($host);
    if ($ip === null) {
        $items[] = dc_item('web', 'Weboldal', 'info', 0, 0, 'A domainhez nem tartozik nyilvános weboldal-cím, a weboldal-ellenőrzés kimaradt.');
        return $items;
    }
    $ipUrl = strpos($ip, ':') !== false ? "[$ip]" : $ip;

    // HTTPS-tanúsítvány
    $cert = null; $certError = '';
    $ctx = stream_context_create(['ssl' => ['capture_peer_cert' => true, 'verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host, 'SNI_enabled' => true]]);
    $s = @stream_socket_client("ssl://$ipUrl:443", $errno, $errstr, DC_TIMEOUT, STREAM_CLIENT_CONNECT, $ctx);
    if ($s) {
        $cert = openssl_x509_parse(stream_context_get_params($s)['options']['ssl']['peer_certificate']);
        fclose($s);
    } else {
        // Miért nem? Ellenőrzés nélkül még egyszer: kiderül, van-e egyáltalán tanúsítvány.
        $ctx2 = stream_context_create(['ssl' => ['capture_peer_cert' => true, 'verify_peer' => false, 'verify_peer_name' => false, 'peer_name' => $host, 'SNI_enabled' => true]]);
        $s2 = @stream_socket_client("ssl://$ipUrl:443", $e2, $es2, DC_TIMEOUT, STREAM_CLIENT_CONNECT, $ctx2);
        if ($s2) {
            $c = openssl_x509_parse(stream_context_get_params($s2)['options']['ssl']['peer_certificate']);
            fclose($s2);
            $cn = (string) ($c['subject']['CN'] ?? '');
            $certError = ($c && ($c['validTo_time_t'] ?? 0) < time()) ? 'a tanúsítvány lejárt'
                : 'a tanúsítvány nem erre a domainre szól vagy nem megbízható kiadótól származik' . ($cn !== '' ? " (a tanúsítványban: $cn)" : '');
        } else {
            $certError = 'a weboldal nem érhető el https-en';
        }
    }
    if ($cert) {
        $days = (int) floor((($cert['validTo_time_t'] ?? 0) - time()) / 86400);
        $issuer = (string) ($cert['issuer']['O'] ?? $cert['issuer']['CN'] ?? '');
        $st = $days < 7 ? 'bad' : ($days < 21 ? 'warn' : 'ok');
        $items[] = dc_item('https', 'HTTPS-tanúsítvány', $st, $days < 7 ? 12 : ($days < 21 ? 16 : 20), 20,
            "Érvényes tanúsítvány" . ($issuer ? " ($issuer)" : '') . ", még $days napig érvényes.",
            $days < 21 ? dc_fix($days < 7 ? 'now' : 'soon', 'A HTTPS-tanúsítvány megújítása', ['Ellenőrizd a tárhelyszolgáltatónál, hogy be van-e kapcsolva az automatikus megújítás (pl. Let\'s Encrypt).', 'Lejárt tanúsítványnál a böngészők figyelmeztetést mutatnak, és a látogatók elfordulnak.']) : null);
    } else {
        $items[] = dc_item('https', 'HTTPS-tanúsítvány', 'bad', 0, 20, 'Nincs érvényes HTTPS: ' . $certError . '.',
            dc_fix('now', 'Érvényes HTTPS-tanúsítvány beállítása', ['A tárhelyszolgáltató felületén kérj ingyenes Let\'s Encrypt tanúsítványt a domainre és a www-s változatára is.', 'Utána ellenőrizd ezzel az oldallal.']));
    }

    if (!function_exists('curl_init')) return $items;
    $resolve = ["$host:80:$ip", "$host:443:$ip"];
    $fetch = function (string $url) use ($resolve): ?array {
        $ch = curl_init($url);
        $headers = [];
        curl_setopt_array($ch, [
            CURLOPT_RESOLVE => $resolve, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => DC_TIMEOUT, CURLOPT_CONNECTTIMEOUT => DC_TIMEOUT, CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; my-ai.hu biztonsagi gyorsellenorzes)',
            CURLOPT_WRITEFUNCTION => fn ($c, $chunk) => 0, // a törzs nem kell: a fejlécek után megszakítjuk
            CURLOPT_HEADERFUNCTION => function ($c, $line) use (&$headers) {
                if (strpos($line, ':') !== false) { [$k, $v] = explode(':', $line, 2); $headers[strtolower(trim($k))] = trim($v); }
                return strlen($line);
            },
        ]);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return $code ? ['code' => $code, 'headers' => $headers] : null;
    };

    // http → https átirányítás
    $http = $fetch("http://$host/");
    if ($http) {
        $loc = (string) ($http['headers']['location'] ?? '');
        $redir = $http['code'] >= 300 && $http['code'] < 400 && stripos($loc, 'https://') === 0;
        $items[] = $redir
            ? dc_item('redirect', 'Átirányítás http-ről https-re', 'ok', 10, 10, 'A http-s címről automatikusan a biztonságos https-re irányít.')
            : dc_item('redirect', 'Átirányítás http-ről https-re', 'warn', 3, 10, 'A http-s cím nem irányít át https-re, így a látogató titkosítatlan kapcsolaton is használhatja az oldalt.',
                dc_fix('soon', 'Automatikus átirányítás https-re', ['A tárhely beállításaiban (vagy .htaccess / PHP) állíts be 301-es átirányítást a http-s címekről a https-esekre.']));
    }

    // Biztonsági fejlécek (https-en)
    $https = $cert ? $fetch("https://$host/") : null;
    if ($https) {
        $h = $https['headers'];
        $hsts = isset($h['strict-transport-security']);
        $items[] = $hsts
            ? dc_item('hsts', 'HSTS (a böngésző mindig https-t használjon)', 'ok', 5, 5, 'Be van állítva.')
            : dc_item('hsts', 'HSTS (a böngésző mindig https-t használjon)', 'warn', 0, 5, 'Nincs beállítva.',
                dc_fix('later', 'HSTS fejléc bekapcsolása', ['Add hozzá a válaszokhoz: Strict-Transport-Security: max-age=31536000', 'Csak akkor kapcsold be, ha az oldal minden aloldala működik https-en.']));
        $missing = [];
        if (!isset($h['x-content-type-options'])) $missing[] = 'X-Content-Type-Options';
        if (!isset($h['x-frame-options']) && stripos((string) ($h['content-security-policy'] ?? ''), 'frame-ancestors') === false) $missing[] = 'X-Frame-Options (kattintáseltérítés ellen)';
        if (!isset($h['referrer-policy'])) $missing[] = 'Referrer-Policy';
        $pts = 10 - 3 * count($missing) - (isset($h['content-security-policy']) ? 0 : 1);
        $items[] = dc_item('headers', 'További biztonsági fejlécek', $missing ? 'warn' : 'ok', max(0, $pts), 10,
            $missing ? 'Hiányzik: ' . implode(', ', $missing) . '.' : 'A fontos biztonsági fejlécek be vannak állítva.',
            $missing ? dc_fix('later', 'Biztonsági fejlécek beállítása a weboldalon', ['Kérd meg a weboldal fejlesztőjét vagy a tárhelyszolgáltatót, hogy állítsa be: ' . implode(', ', $missing) . '.', 'Ez néhány sor a szerver beállításaiban, a weboldal működését nem változtatja meg.']) : null);
        $server = (string) ($h['x-powered-by'] ?? '');
        if (preg_match('/\d/', $server)) {
            $items[] = dc_item('version', 'Szoftververzió kiírása', 'info', 0, 0, "A szerver kiírja a használt szoftver verzióját ($server). Ez segíti a támadókat a gyenge pontok keresésében.",
                dc_fix('later', 'A szoftververzió elrejtése', ['PHP-nál: expose_php = Off (php.ini), vagy kérd a tárhelyszolgáltatót.']));
        }
    }
    return $items;
}

// A teljes vizsgálat: tételek + 0–100 pontszám (a max. pontok arányában).
function dc_run(string $domain): array {
    $items = array_merge(dc_check_mail($domain), dc_check_web($domain));
    $got = 0; $max = 0;
    foreach ($items as $it) { $got += $it['points']; $max += $it['max']; }
    return [
        'domain' => $domain,
        'checked_at' => date('c'),
        'score' => $max > 0 ? (int) round(100 * $got / $max) : null,
        'items' => $items,
    ];
}

// Ugyanaz, 15 perces gyorsítótárral (temp mappa): a teendőlista-levél így ugyanazt az
// eredményt kapja, amit a látogató az oldalon látott, újabb hálózati vizsgálat nélkül.
function dc_run_cached(string $domain, bool $onlyCache = false): ?array {
    $file = sys_get_temp_dir() . '/myai-kiber-dc-' . substr(hash('sha256', $domain), 0, 24) . '.json';
    $cached = @json_decode((string) @file_get_contents($file), true);
    if (is_array($cached) && ($cached['_t'] ?? 0) > time() - 900) { unset($cached['_t']); return $cached; }
    if ($onlyCache) return null;
    $res = dc_run($domain);
    @file_put_contents($file, json_encode($res + ['_t' => time()], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), LOCK_EX);
    return $res;
}
