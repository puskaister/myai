<?php
// Letöltések (my-ai.hu/letoltes/?fajl=<név>). A csomagokat a deploy készíti a
// files/ mappába. A letöltés ezen a végponton keresztül megy, hogy később egyedi
// letöltőkódhoz köthessük; most minden termék ingyenes. Paraméter nélkül a
// termékoldalra irányít (/appok/latogatoszamlalo/).
declare(strict_types=1);

$products = [
    'latogatoszamlalo' => [
        'name'  => 'Látogatószámláló',
        'price' => 'Ingyenes',
    ],
];

$file = (string) ($_GET['fajl'] ?? '');
if ($file !== '') {
    $path = __DIR__ . '/files/' . basename($file) . '.zip';
    if (!isset($products[$file]) || !is_file($path)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'A kért csomag nem található.';
        exit;
    }
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $file . '.zip"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: no-store');
    readfile($path);
    exit;
}

// A termékoldal a /appok/latogatoszamlalo/ címre költözött; itt csak a letöltés maradt.
header('Location: /appok/latogatoszamlalo/', true, 301);
exit;
