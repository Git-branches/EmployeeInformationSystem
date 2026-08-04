<?php
/**
 * installer/make-icon.php
 * Draws installer/eis.ico — the application icon used by the installer,
 * Desktop and Start Menu shortcuts.
 *
 * The mark is drawn here rather than loaded from an image so the project
 * ships no third-party branding: a teal rounded square, an amber border
 * and the letters EIS.
 */

$out = __DIR__ . '/eis.ico';

// Brand colours (must match assets/css/style.css)
$TEAL  = [0x0f, 0x76, 0x6e];
$AMBER = [0xf5, 0x9e, 0x0b];

/** Rounded rectangle filled with $rgb. */
function rounded_rect($im, float $x1, float $y1, float $x2, float $y2, float $r, int $color): void
{
    imagefilledrectangle($im, (int)($x1 + $r), (int)$y1, (int)($x2 - $r), (int)$y2, $color);
    imagefilledrectangle($im, (int)$x1, (int)($y1 + $r), (int)$x2, (int)($y2 - $r), $color);
    $d = (int)($r * 2);
    imagefilledellipse($im, (int)($x1 + $r), (int)($y1 + $r), $d, $d, $color);
    imagefilledellipse($im, (int)($x2 - $r), (int)($y1 + $r), $d, $d, $color);
    imagefilledellipse($im, (int)($x1 + $r), (int)($y2 - $r), $d, $d, $color);
    imagefilledellipse($im, (int)($x2 - $r), (int)($y2 - $r), $d, $d, $color);
}

$sizes  = [16, 32, 48, 64, 128, 256];
$images = [];

foreach ($sizes as $size) {
    // Draw at 4x then downsample, so edges and text stay smooth
    $ss = $size * 4;
    $hi = imagecreatetruecolor($ss, $ss);
    imagealphablending($hi, false);
    imagesavealpha($hi, true);
    imagefill($hi, 0, 0, imagecolorallocatealpha($hi, 0, 0, 0, 127));
    imagealphablending($hi, true);

    $teal  = imagecolorallocate($hi, ...$TEAL);
    $amber = imagecolorallocate($hi, ...$AMBER);
    $white = imagecolorallocate($hi, 255, 255, 255);

    $pad = $ss * 0.02;
    rounded_rect($hi, $pad, $pad, $ss - $pad - 1, $ss - $pad - 1, $ss * 0.22, $amber);
    $b = $ss * 0.075;
    rounded_rect($hi, $b, $b, $ss - $b - 1, $ss - $b - 1, $ss * 0.18, $teal);

    // "EIS" — built-in font on the small sizes, scaled to fit
    $label = 'EIS';
    $font  = 5;
    $fw    = imagefontwidth($font) * strlen($label);
    $fh    = imagefontheight($font);
    $tmp   = imagecreatetruecolor($fw, $fh);
    imagealphablending($tmp, false);
    imagesavealpha($tmp, true);
    imagefill($tmp, 0, 0, imagecolorallocatealpha($tmp, 0, 0, 0, 127));
    imagealphablending($tmp, true);
    imagestring($tmp, $font, 0, 0, $label, imagecolorallocate($tmp, 255, 255, 255));

    $tw = (int)($ss * 0.62);
    $th = (int)($tw * $fh / $fw);
    imagecopyresampled($hi, $tmp, (int)(($ss - $tw) / 2), (int)(($ss - $th) / 2), 0, 0, $tw, $th, $fw, $fh);

    $canvas = imagecreatetruecolor($size, $size);
    imagealphablending($canvas, false);
    imagesavealpha($canvas, true);
    imagecopyresampled($canvas, $hi, 0, 0, 0, 0, $size, $size, $ss, $ss);

    // ---- 32-bit BGRA bottom-up DIB + AND mask ----
    $xor = '';
    for ($y = $size - 1; $y >= 0; $y--) {
        for ($x = 0; $x < $size; $x++) {
            $c = imagecolorat($canvas, $x, $y);
            $a = ($c >> 24) & 0x7F;              // GD: 0 opaque .. 127 transparent
            $xor .= chr($c & 0xFF)
                  . chr(($c >> 8) & 0xFF)
                  . chr(($c >> 16) & 0xFF)
                  . chr($a === 127 ? 0 : 255 - (int)round($a * 255 / 127));
        }
    }
    $and = str_repeat("\0", (int)ceil($size / 32) * 4 * $size);

    $header = pack('VVVvvVVVVVV', 40, $size, $size * 2, 1, 32, 0, strlen($xor) + strlen($and), 0, 0, 0, 0);
    $images[] = ['size' => $size, 'data' => $header . $xor . $and];
}

// ---- ICONDIR + ICONDIRENTRY table ----
$ico    = pack('vvv', 0, 1, count($images));
$offset = 6 + 16 * count($images);
foreach ($images as $img) {
    $dim = $img['size'] === 256 ? 0 : $img['size'];
    $ico .= pack('CCCCvvVV', $dim, $dim, 0, 0, 1, 32, strlen($img['data']), $offset);
    $offset += strlen($img['data']);
}
foreach ($images as $img) {
    $ico .= $img['data'];
}

file_put_contents($out, $ico);
printf("Created %s (%s KB, %d sizes)\n", basename($out), number_format(strlen($ico) / 1024, 1), count($images));
