<?php
// Web App Manifest — ettől telepíthető az app Androidon és iOS-en.
declare(strict_types=1);

require __DIR__ . '/api/lib/core.php';

$name = app_installed() ? get_options()['app_name'] : 'Bevásárlólista';
header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: no-cache');
echo json_encode([
    'name' => $name, 'short_name' => mb_substr($name, 0, 12), 'description' => 'Közös bevásárlólista, szóbeli bevitellel',
    'lang' => 'hu', 'start_url' => './', 'scope' => './', 'display' => 'standalone',
    'background_color' => '#ffffff', 'theme_color' => '#16a34a',
    'icons' => [
        ['src' => 'icon.php?s=192', 'sizes' => '192x192', 'type' => 'image/png'],
        ['src' => 'icon.php?s=512', 'sizes' => '512x512', 'type' => 'image/png'],
        ['src' => 'icon.php?s=512&m=1', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
