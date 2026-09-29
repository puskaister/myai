<?php
// App ikon PNG-ben: bevásárlókosár zöld alapon (az uploads/-ban gyorsítótárazva).
declare(strict_types=1);

$size = (int) ($_GET['s'] ?? 192);
$size = in_array($size, [180, 192, 512], true) ? $size : 192;
$maskable = !empty($_GET['m']);
$cache = __DIR__ . '/uploads/icon-' . $size . ($maskable ? 'm' : '') . '-v1.png';

if (!is_file($cache)) {
    if (!function_exists('imagecreatetruecolor')) {
        header('Content-Type: image/svg+xml');
        echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" fill="#16a34a"/><path d="M24 34h56l-7 30H31z" fill="#fff"/></svg>';
        exit;
    }
    $big = $size * 4;
    $img = imagecreatetruecolor($big, $big);
    $bg = imagecolorallocate($img, 0x16, 0xa3, 0x4a);
    $white = imagecolorallocate($img, 255, 255, 255);
    imagefilledrectangle($img, 0, 0, $big, $big, $bg);
    $u = $big / 100 * ($maskable ? 0.8 : 1);
    $o = ($big - 100 * $u) / 2;
    $px = fn (float $v): int => (int) round($o + $v * $u);
    imagesetthickness($img, (int) round(6 * $u));
    // kosár: fogantyú + trapéz test + két kerék
    imageline($img, $px(14), $px(26), $px(24), $px(26), $white);
    imageline($img, $px(24), $px(26), $px(30), $px(34), $white);
    imagefilledpolygon($img, [$px(28), $px(34), $px(84), $px(34), $px(76), $px(62), $px(35), $px(62)], $white);
    imagefilledrectangle($img, $px(35), $px(62), $px(76), $px(68), $white);
    imagefilledellipse($img, $px(40), $px(78), (int) round(12 * $u), (int) round(12 * $u), $white);
    imagefilledellipse($img, $px(71), $px(78), (int) round(12 * $u), (int) round(12 * $u), $white);
    // pipa a kosárban
    imagesetthickness($img, (int) round(5 * $u));
    imageline($img, $px(45), $px(47), $px(53), $px(55), $bg);
    imageline($img, $px(53), $px(55), $px(67), $px(41), $bg);
    $out = imagecreatetruecolor($size, $size);
    imagecopyresampled($out, $img, 0, 0, 0, 0, $size, $size, $big, $big);
    if (!@imagepng($out, $cache)) { header('Content-Type: image/png'); imagepng($out); exit; }
}
header('Content-Type: image/png');
header('Cache-Control: public, max-age=604800');
readfile($cache);
