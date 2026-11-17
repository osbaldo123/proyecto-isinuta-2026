<?php

$path = __DIR__.'/public/images/fondo-gota.png';

$src = imagecreatefrompng($path);
if ($src === false) {
    fwrite(STDERR, "No se pudo abrir {$path}\n");
    exit(1);
}

$width = imagesx($src);
$height = imagesy($src);

$img = imagecreatetruecolor($width, $height);
imagealphablending($img, false);
imagesavealpha($img, true);

$transparent = imagecolorallocatealpha($img, 255, 255, 255, 127);
imagefill($img, 0, 0, $transparent);
imagecopy($img, $src, 0, 0, 0, 0, $width, $height);
imagedestroy($src);

$isBackground = static function (int $rgba): bool {
    $alpha = ($rgba & 0x7F000000) >> 24;
    if ($alpha > 60) {
        return true;
    }

    $r = ($rgba >> 16) & 0xFF;
    $g = ($rgba >> 8) & 0xFF;
    $b = $rgba & 0xFF;

    return $r >= 244 && $g >= 244 && $b >= 244 && (max($r, $g, $b) - min($r, $g, $b)) <= 20;
};

$visited = array_fill(0, $width * $height, false);
$queue = [];
$queueIndex = 0;

$enqueue = static function (int $x, int $y) use (&$queue, &$visited, $width, $height, $img, $isBackground): void {
    if ($x < 0 || $y < 0 || $x >= $width || $y >= $height) {
        return;
    }

    $i = $y * $width + $x;
    if ($visited[$i]) {
        return;
    }

    $visited[$i] = true;
    if ($isBackground(imagecolorat($img, $x, $y))) {
        $queue[] = [$x, $y];
    }
};

for ($x = 0; $x < $width; $x++) {
    $enqueue($x, 0);
    $enqueue($x, $height - 1);
}
for ($y = 0; $y < $height; $y++) {
    $enqueue(0, $y);
    $enqueue($width - 1, $y);
}

$cleared = 0;
while ($queueIndex < count($queue)) {
    [$x, $y] = $queue[$queueIndex++];
    imagesetpixel($img, $x, $y, $transparent);
    $cleared++;

    $enqueue($x + 1, $y);
    $enqueue($x - 1, $y);
    $enqueue($x, $y + 1);
    $enqueue($x, $y - 1);
    $enqueue($x + 1, $y + 1);
    $enqueue($x + 1, $y - 1);
    $enqueue($x - 1, $y + 1);
    $enqueue($x - 1, $y - 1);
}

// Suavizar el borde de la gota (anti-alias contra el blanco).
for ($y = 1; $y < $height - 1; $y++) {
    for ($x = 1; $x < $width - 1; $x++) {
        $rgba = imagecolorat($img, $x, $y);
        $alpha = ($rgba & 0x7F000000) >> 24;
        if ($alpha >= 127) {
            continue;
        }

        $hasTransparentNeighbor = false;
        for ($dy = -1; $dy <= 1 && ! $hasTransparentNeighbor; $dy++) {
            for ($dx = -1; $dx <= 1; $dx++) {
                if ($dx === 0 && $dy === 0) {
                    continue;
                }
                $n = imagecolorat($img, $x + $dx, $y + $dy);
                if ((($n & 0x7F000000) >> 24) >= 127) {
                    $hasTransparentNeighbor = true;
                    break;
                }
            }
        }

        if (! $hasTransparentNeighbor) {
            continue;
        }

        $r = ($rgba >> 16) & 0xFF;
        $g = ($rgba >> 8) & 0xFF;
        $b = $rgba & 0xFF;
        $min = min($r, $g, $b);
        if ($min < 210) {
            continue;
        }

        $fade = (255 - $min) / 45;
        $fade = max(0.0, min(1.0, $fade));
        $newAlpha = (int) round(127 - (127 * $fade));
        $color = imagecolorallocatealpha($img, $r, $g, $b, $newAlpha);
        imagesetpixel($img, $x, $y, $color);
    }
}

imagepng($img, $path, 9);
imagedestroy($img);

echo "OK {$width}x{$height}, fondo transparente: {$cleared} px\n";
