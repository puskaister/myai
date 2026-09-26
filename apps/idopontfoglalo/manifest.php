<?php
// Web App Manifest a cég aktuális nevével és színével — ettől telepíthető
// az oldal appként Androidon és iOS-en.
declare(strict_types=1);

require __DIR__ . '/api/lib/core.php';

$s = app_installed() ? get_settings() : default_settings();
$name = $s['business']['name'];
$version = substr(md5($s['theme']['primary'] . $s['theme']['logo']), 0, 8);

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: no-cache');

echo json_encode([
    'name'             => $name,
    'short_name'       => mb_strlen($name) > 14 ? mb_substr($name, 0, 14) : $name,
    'description'      => 'Online időpontfoglalás – ' . $name,
    'lang'             => 'hu',
    'start_url'        => './',
    'scope'            => './',
    'display'          => 'standalone',
    'background_color' => '#ffffff',
    'theme_color'      => $s['theme']['primary'],
    'icons'            => [
        ['src' => "icon.php?s=192&v=$version", 'sizes' => '192x192', 'type' => 'image/png'],
        ['src' => "icon.php?s=512&v=$version", 'sizes' => '512x512', 'type' => 'image/png'],
        ['src' => "icon.php?s=512&m=1&v=$version", 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
