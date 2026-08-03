<?php
/**
 * installer/make-icon.php
 * Builds installer/eis.ico from assets/img/logo.png.
 * Writes a multi-size ICO (16/32/48/64/128/256) so Windows shows a crisp
 * icon in the taskbar, desktop and Start Menu.
 */

$src = __DIR__ . '/../assets/img/logo.png';
$out = __DIR__ . '/eis.ico';

if (!is_file($src)) {
    exit("Source logo not found: $src\n");
}

$logo = imagecreatefrompng($src);
if (!$logo) {
    exit("Could not read the logo PNG.\n");
}
$lw = imagesx($logo);
$lh = imagesy($logo);

$sizes  = [16, 32, 48, 64, 128, 256];
$images = [];

foreach ($sizes as $size) {
    $canvas = imagecreatetruecolor($size, $size);
    imagealphablending($canvas, false);
    imagesavealpha($canvas, true);
    imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
    imagealphablending($canvas, true);

    // Fit the logo inside the square, keeping its proportions
    $scale = min($size / $lw, $size / $lh);
    $w = (int)round($lw * $scale);
    $h = (int)round($lh * $scale);
    imagecopyresampled($canvas, $logo, (int)(($size - $w) / 2), (int)(($size - $h) / 2), 0, 0, $w, $h, $lw, $lh);

    // ---- 32-bit BGRA bottom-up DIB + AND mask ----
    $xor = '';
    for ($y = $size - 1; $y >= 0; $y--) {
        for ($x = 0; $x < $size; $x++) {
            $c = imagecolorat($canvas, $x, $y);
            $a = ($c >> 24) & 0x7F;                 // GD alpha: 0 opaque .. 127 transparent
            $xor .= chr($c & 0xFF)                  // B
                  . chr(($c >> 8) & 0xFF)           // G
                  . chr(($c >> 16) & 0xFF)          // R
                  . chr($a === 127 ? 0 : 255 - (int)round($a * 255 / 127));
        }
    }
    $maskRow = (int)ceil($size / 32) * 4;           // padded to 4 bytes
    $and = str_repeat("\0", $maskRow * $size);

    $header = pack('VVVvvVVVVVV',
        40,            // biSize
        $size,         // biWidth
        $size * 2,     // biHeight (XOR + AND)
        1,             // biPlanes
        32,            // biBitCount
        0,             // biCompression
        strlen($xor) + strlen($and),
        0, 0, 0, 0
    );
    $images[] = ['size' => $size, 'data' => $header . $xor . $and];
    imagedestroy($canvas);
}
imagedestroy($logo);

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
