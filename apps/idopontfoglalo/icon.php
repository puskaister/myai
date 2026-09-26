<?php
// App ikon PNG-ben (Android/iOS kezdőképernyő). Ha van feltöltött logó, az
// kerül fehér alapra; ha nincs, egy naptár-jel a cég színén. Az eredményt az
// uploads/ mappában gyorsítótárazzuk (logócserénél az API törli).
declare(strict_types=1);

require __DIR__ . '/api/lib/core.php';

$size = (int) ($_GET['s'] ?? 192);
$size = in_array($size, [180, 192, 512], true) ? $size : 192;
$maskable = !empty($_GET['m']);

$s = app_installed() ? get_settings() : default_settings();
$primary = $s['theme']['primary'];
$logo = $s['theme']['logo'] !== '' ? APP_ROOT . '/uploads/' . basename($s['theme']['logo']) : '';
if ($logo !== '' && !is_file($logo)) $logo = '';

if (!function_exists('imagecreatetruecolor')) {
    // GD nélkül egy SVG-t adunk (Androidon működik, iOS-en az alap ikon marad).
    header('Content-Type: image/svg+xml');
    echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" fill="' . h($primary) . '"/>'
        . '<rect x="22" y="28" width="56" height="50" rx="8" fill="#fff"/></svg>';
    exit;
}

$cache = APP_ROOT . '/uploads/icon-' . $size . ($maskable ? 'm' : '') . '-' . substr(md5($primary . $logo), 0, 10) . '.png';
if (!is_file($cache)) {
    $scale = 4; // nagyobb méretben rajzolunk, majd kicsinyítünk: élsimítás GD-ben
    $big = $size * $scale;
    $img = imagecreatetruecolor($big, $big);
    [$r, $g, $b] = sscanf($primary, '#%02x%02x%02x');
    $bg = imagecolorallocate($img, $r, $g, $b);
    $white = imagecolorallocate($img, 255, 255, 255);

    if ($logo !== '') {
        imagefilledrectangle($img, 0, 0, $big, $big, $white);
        $src = @imagecreatefromstring((string) file_get_contents($logo));
        if ($src) {
            $box = (int) ($big * ($maskable ? 0.62 : 0.8)); // maskable: a széleket a rendszer levághatja
            $w = imagesx($src);
            $hgt = imagesy($src);
            $ratio = min($box / $w, $box / $hgt);
            $dw = (int) ($w * $ratio);
            $dh = (int) ($hgt * $ratio);
            imagecopyresampled($img, $src, (int) (($big - $dw) / 2), (int) (($big - $dh) / 2), 0, 0, $dw, $dh, $w, $hgt);
            imagedestroy($src);
        }
    } else {
        imagefilledrectangle($img, 0, 0, $big, $big, $bg);
        $u = $big / 100 * ($maskable ? 0.8 : 1); // egység; maskable-nél kisebb jel
        $o = ($big - 100 * $u) / 2;               // középre igazítás
        $rect = function ($x1, $y1, $x2, $y2, $color, $radius = 0) use ($img, $u, $o) {
            [$x1, $y1, $x2, $y2, $rad] = [$o + $x1 * $u, $o + $y1 * $u, $o + $x2 * $u, $o + $y2 * $u, $radius * $u];
            if ($rad <= 0) {
                imagefilledrectangle($img, (int) $x1, (int) $y1, (int) $x2, (int) $y2, $color);
                return;
            }
            imagefilledrectangle($img, (int) ($x1 + $rad), (int) $y1, (int) ($x2 - $rad), (int) $y2, $color);
            imagefilledrectangle($img, (int) $x1, (int) ($y1 + $rad), (int) $x2, (int) ($y2 - $rad), $color);
            foreach ([[$x1 + $rad, $y1 + $rad], [$x2 - $rad, $y1 + $rad], [$x1 + $rad, $y2 - $rad], [$x2 - $rad, $y2 - $rad]] as [$cx, $cy]) {
                imagefilledellipse($img, (int) $cx, (int) $cy, (int) ($rad * 2), (int) ($rad * 2), $color);
            }
        };
        $rect(22, 27, 78, 79, $white, 8);   // naptár test
        $rect(27, 42, 73, 74, $bg, 3);      // belső terület
        $rect(33, 20, 40, 33, $white, 3);   // gyűrűk
        $rect(60, 20, 67, 33, $white, 3);
        foreach ([31, 45, 59] as $x) {      // napok
            foreach ([47, 60] as $y) $rect($x, $y, $x + 10, $y + 9, $white, 2);
        }
    }

    $out = imagecreatetruecolor($size, $size);
    imagecopyresampled($out, $img, 0, 0, 0, 0, $size, $size, $big, $big);
    if (!@imagepng($out, $cache)) {
        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=3600');
        imagepng($out);
        exit;
    }
    imagedestroy($img);
    imagedestroy($out);
}

header('Content-Type: image/png');
header('Cache-Control: public, max-age=604800');
readfile($cache);
