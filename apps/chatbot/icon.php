<?php
// App ikon PNG-ben: chat-buborék a cég színén. Az eredményt az uploads/
// mappában gyorsítótárazzuk (színenként).
declare(strict_types=1);

require __DIR__ . '/api/lib/core.php';

$size = (int) ($_GET['s'] ?? 192);
$size = in_array($size, [180, 192, 512], true) ? $size : 192;
$maskable = !empty($_GET['m']);
$s = app_installed() ? get_settings() : default_settings();
$primary = $s['theme']['primary'];

if (!function_exists('imagecreatetruecolor')) {
    header('Content-Type: image/svg+xml');
    echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" fill="' . h($primary) . '"/>'
        . '<rect x="20" y="24" width="60" height="42" rx="12" fill="#fff"/><path d="M34 62 L30 78 L48 64 Z" fill="#fff"/></svg>';
    exit;
}

$cache = APP_ROOT . '/uploads/icon-' . $size . ($maskable ? 'm' : '') . '-' . substr(md5($primary), 0, 10) . '.png';
if (!is_file($cache)) {
    $scale = 4; // nagyban rajzolunk, majd kicsinyítünk: élsimítás GD-ben
    $big = $size * $scale;
    $img = imagecreatetruecolor($big, $big);
    [$r, $g, $b] = sscanf($primary, '#%02x%02x%02x');
    $bg = imagecolorallocate($img, $r, $g, $b);
    $white = imagecolorallocate($img, 255, 255, 255);
    imagefilledrectangle($img, 0, 0, $big, $big, $bg);

    $u = $big / 100 * ($maskable ? 0.8 : 1);
    $o = ($big - 100 * $u) / 2;
    $px = fn (float $v): int => (int) round($o + $v * $u);

    // lekerekített buborék
    [$x1, $y1, $x2, $y2, $rad] = [20, 24, 80, 66, 12];
    imagefilledrectangle($img, $px($x1 + $rad), $px($y1), $px($x2 - $rad), $px($y2), $white);
    imagefilledrectangle($img, $px($x1), $px($y1 + $rad), $px($x2), $px($y2 - $rad), $white);
    foreach ([[$x1 + $rad, $y1 + $rad], [$x2 - $rad, $y1 + $rad], [$x1 + $rad, $y2 - $rad], [$x2 - $rad, $y2 - $rad]] as [$cx, $cy]) {
        imagefilledellipse($img, $px($cx), $px($cy), (int) round($rad * 2 * $u), (int) round($rad * 2 * $u), $white);
    }
    // farok
    imagefilledpolygon($img, [$px(32), $px(62), $px(28), $px(80), $px(50), $px(64)], $white);
    // három pötty
    foreach ([37, 50, 63] as $cx) imagefilledellipse($img, $px($cx), $px(45), (int) round(8 * $u), (int) round(8 * $u), $bg);

    $out = imagecreatetruecolor($size, $size);
    imagecopyresampled($out, $img, 0, 0, 0, 0, $size, $size, $big, $big);
    if (!@imagepng($out, $cache)) {
        header('Content-Type: image/png');
        imagepng($out);
        exit;
    }
}

header('Content-Type: image/png');
header('Cache-Control: public, max-age=604800');
readfile($cache);
