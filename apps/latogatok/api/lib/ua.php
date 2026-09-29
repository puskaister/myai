<?php
declare(strict_types=1);

// Egyszerű User-Agent felismerés: böngésző + verzió, rendszer, eszköztípus.
// A sorrend számít (pl. az Edge és az Opera is "Chrome"-nak mondja magát).

function is_bot(string $ua): bool {
    return $ua === '' || (bool) preg_match('/bot|crawl|spider|slurp|headless|lighthouse|pingdom|uptime|monitor|preview|facebookexternalhit|embedly|curl\/|wget|python-requests|go-http|java\//i', $ua);
}

function parse_ua(string $ua): array {
    $browsers = [
        'Edge'             => '/Edg(?:e|A|iOS)?\/([\d.]+)/',
        'Opera'            => '/(?:OPR|Opera)\/([\d.]+)/',
        'Samsung Internet' => '/SamsungBrowser\/([\d.]+)/',
        'Firefox'          => '/(?:Firefox|FxiOS)\/([\d.]+)/',
        'Chrome'           => '/(?:Chrome|CriOS)\/([\d.]+)/',
        'Safari'           => '/Version\/([\d.]+).*Safari/',
    ];
    $browser = 'Egyéb';
    $version = '';
    foreach ($browsers as $name => $re) {
        if (preg_match($re, $ua, $m)) {
            $browser = $name;
            $version = explode('.', $m[1])[0];
            break;
        }
    }

    $os = 'Egyéb';
    foreach (['iOS' => '/iPhone|iPad|iPod/', 'Android' => '/Android/', 'Windows' => '/Windows/', 'ChromeOS' => '/CrOS/', 'macOS' => '/Mac OS X|Macintosh/', 'Linux' => '/Linux/'] as $name => $re) {
        if (preg_match($re, $ua)) { $os = $name; break; }
    }

    if (preg_match('/iPad|Tablet|(Android(?!.*Mobile))/i', $ua)) $device = 'tablet';
    elseif (preg_match('/Mobi|iPhone|iPod|Android.*Mobile/i', $ua)) $device = 'mobil';
    else $device = 'asztali';

    return ['browser' => $browser, 'version' => $version, 'os' => $os, 'device' => $device];
}
