<?php
/**
 * Image derivative generator — Prince Byron platform.
 *
 * WHY THIS IS A SCRIPT AND NOT A VENDOR PACKAGE (review P2-6)
 * -----------------------------------------------------------
 * The deployment target is a shared host where nobody runs Composer. So the
 * derivatives are committed as ordinary files and generated ahead of release by
 * this script, on any machine that has PHP + GD/Imagick. No runtime dependency,
 * no thumbnail service, no CDN.
 *
 * Every image on the site previously shipped as a single enormous file: the
 * portrait was 927 KB, the hoodie 1 MB, the album art 710 KB — for a page whose
 * hero is 390 px wide on the phone most of these customers actually use.
 *
 * Usage:
 *   php tools/images.php            # generate what is missing
 *   php tools/images.php --force    # regenerate everything
 *
 * Widths are capped at the source width: upscaling a 1463 px master to 2400 px
 * produces a bigger file with no extra detail, which is worse than useless.
 */

$root = dirname(__DIR__).'/core';
$public = $root.'/public';

$widths = [480, 960, 1600, 2400];

$sources = array_merge(
    glob($public.'/images/*.jpeg') ?: [],
    glob($public.'/images/*.jpg') ?: [],
    glob($public.'/images/*/*.jpg') ?: [],
);

$force = in_array('--force', $argv, true);
$made = 0;
$skipped = 0;

foreach ($sources as $source) {
    $info = @getimagesize($source);

    if ($info === false) {
        fwrite(STDERR, "  skip (unreadable): {$source}\n");
        continue;
    }

    [$sourceWidth] = $info;
    $dir = dirname($source);
    $base = pathinfo($source, PATHINFO_FILENAME);
    $ext = strtolower(pathinfo($source, PATHINFO_EXTENSION));
    $original = $base.'.'.$ext;

    echo basename($source)." ({$sourceWidth}px)\n";

    foreach ($widths as $width) {
        if ($width > $sourceWidth) {
            continue; // never upscale
        }

        foreach (['webp', 'jpg'] as $format) {
            $target = "{$dir}/{$base}-{$width}.{$format}";

            if (! $force && is_file($target)) {
                $skipped++;
                continue;
            }

            $image = @imagecreatefromstring(file_get_contents($source));

            if ($image === false) {
                fwrite(STDERR, "  failed to decode {$source}\n");
                continue 2;
            }

            $height = (int) round(imagesy($image) * ($width / imagesx($image)));
            $scaled = imagescale($image, $width, $height, IMG_BICUBIC);

            if ($format === 'webp' && function_exists('imagewebp')) {
                imagewebp($scaled, $target, 78);
            } else {
                // No WebP support in this PHP build: write a JPEG under the .jpg
                // name and skip the webp slot rather than emitting a mislabelled
                // file that browsers will refuse.
                if ($format === 'webp') {
                    imagedestroy($scaled);
                    imagedestroy($image);
                    continue;
                }
                imagejpeg($scaled, $target, 82);
            }

            imagedestroy($scaled);
            imagedestroy($image);
            $made++;
        }
    }
}

echo "\nGenerated {$made} derivative(s), {$skipped} already present.\n";

if ($made === 0 && $skipped === 0) {
    echo "No source images found under core/public/images/.\n";
    exit(1);
}
