<?php
// App ikon PNG-ben: naptárlap szívvel, rózsaszín alapon (az uploads/-ban gyorsítótárazva).
declare(strict_types=1);

$size = (int) ($_GET['s'] ?? 192);
$size = in_array($size, [180, 192, 512], true) ? $size : 192;
$maskable = !empty($_GET['m']);
$cache = __DIR__ . '/uploads/icon-' . $size . ($maskable ? 'm' : '') . '-v1.png';

if (!is_file($cache)) {
    if (!function_exists('imagecreatetruecolor')) {
        header('Content-Type: image/svg+xml');
        echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" fill="#db2777"/><rect x="22" y="26" width="56" height="52" rx="8" fill="#fff"/></svg>';
        exit;
    }
    $big = $size * 4;
    $img = imagecreatetruecolor($big, $big);
    $bg = imagecolorallocate($img, 0xdb, 0x27, 0x77);
    $white = imagecolorallocate($img, 255, 255, 255);
    imagefilledrectangle($img, 0, 0, $big, $big, $bg);
    $u = $big / 100 * ($maskable ? 0.8 : 1);
    $o = ($big - 100 * $u) / 2;
    $px = fn (float $v): int => (int) round($o + $v * $u);
    $rrect = function (float $x1, float $y1, float $x2, float $y2, float $r, int $c) use ($img, $px, $u) {
        imagefilledrectangle($img, $px($x1 + $r), $px($y1), $px($x2 - $r), $px($y2), $c);
        imagefilledrectangle($img, $px($x1), $px($y1 + $r), $px($x2), $px($y2 - $r), $c);
        foreach ([[$x1 + $r, $y1 + $r], [$x2 - $r, $y1 + $r], [$x1 + $r, $y2 - $r], [$x2 - $r, $y2 - $r]] as [$cx, $cy]) {
            imagefilledellipse($img, $px($cx), $px($cy), (int) round($r * 2 * $u), (int) round($r * 2 * $u), $c);
        }
    };
    $rrect(22, 27, 78, 79, 8, $white);      // naptárlap
    $rrect(33, 20, 40, 33, 3, $white);      // gyűrűk
    $rrect(60, 20, 67, 33, 3, $white);
    imagefilledrectangle($img, $px(22), $px(38), $px(78), $px(40), $bg); // fejléc vonal
    // szív: két kör + háromszög
    imagefilledellipse($img, $px(43), $px(53), (int) round(15 * $u), (int) round(15 * $u), $bg);
    imagefilledellipse($img, $px(57), $px(53), (int) round(15 * $u), (int) round(15 * $u), $bg);
    imagefilledpolygon($img, [$px(36), $px(56), $px(64), $px(56), $px(50), $px(71)], $bg);
    $out = imagecreatetruecolor($size, $size);
    imagecopyresampled($out, $img, 0, 0, 0, 0, $size, $size, $big, $big);
    if (!@imagepng($out, $cache)) { header('Content-Type: image/png'); imagepng($out); exit; }
}
header('Content-Type: image/png');
header('Cache-Control: public, max-age=604800');
readfile($cache);
