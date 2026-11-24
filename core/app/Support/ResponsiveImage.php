<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * ResponsiveImage — build `srcset` lists from the derivatives that exist on disk.
 *
 * WHY THIS EXISTS (review P2-6)
 * -----------------------------
 * Every image on the site shipped as one huge file: a 927 KB portrait, a 1 MB
 * hoodie, a 710 KB album cover, with no `srcset`, no `sizes`, no `<picture>` and
 * no intrinsic `width`/`height`. On a phone that meant downloading a 1463px
 * portrait to fill a 390px screen, and paying a layout shift because the browser
 * did not know the aspect ratio until the bytes arrived.
 *
 * DESIGN
 * ------
 * The derivative files are generated ahead of time by `tools/images.php` (see
 * public/images/README.md), and this class reads the directory rather than a
 * hardcoded list. That matters: a hardcoded `srcset` in a template silently rots
 * the moment a source image is replaced, and the failure mode is a 404 on the
 * largest screen — the one nobody tests on. Discovering the files means the
 * markup can only reference images that are actually there.
 *
 * If no derivatives exist (a fresh checkout that has not run the generator) the
 * class falls back to the original file alone. Degrading to "the old behaviour"
 * is correct: a broken `srcset` breaks the image entirely.
 */
class ResponsiveImage
{
    /** Widths the generator produces, largest first. */
    public const WIDTHS = [2400, 1600, 960, 480];

    /** @var array<string, array<string, int>> memo of path => [width => bytes] */
    private static array $cache = [];

    /**
     * Absolute URL for the widest available derivative at or below $maxWidth.
     *
     * Used for the plain `src` fallback, which only very old browsers read —
     * they should get something modest rather than the 2400px master.
     */
    public static function fallback(string $path, int $maxWidth = 960): string
    {
        $available = self::available($path);

        foreach (self::WIDTHS as $width) {
            if ($width <= $maxWidth && isset($available['webp'][$width])) {
                return asset(self::derivativePath($path, $width, 'jpg'));
            }
        }

        return asset($path);
    }

    /**
     * `srcset` for a format, e.g. "…-480.webp 480w, …-960.webp 960w".
     */
    public static function srcset(string $path, string $format = 'webp'): ?string
    {
        $available = self::available($path)[$format] ?? [];

        if ($available === []) {
            return null;
        }

        $parts = [];

        foreach ($available as $width => $bytes) {
            $parts[] = asset(self::derivativePath($path, $width, $format)).' '.$width.'w';
        }

        return implode(', ', $parts);
    }

    /**
     * Intrinsic pixel dimensions of the original, for `width`/`height`
     * attributes. Reserving space is what removes the layout shift (CLS): the
     * browser can lay the page out before the image arrives.
     *
     * @return array{0:int,1:int}|null
     */
    public static function dimensions(string $path): ?array
    {
        $file = public_path($path);

        if (! is_file($file)) {
            return null;
        }

        // getimagesize is part of core PHP and needs no extension for JPEG/WebP;
        // it reads only the header, so this is cheap enough to call per request.
        $info = @getimagesize($file);

        if ($info === false || empty($info[0]) || empty($info[1])) {
            return null;
        }

        return [(int) $info[0], (int) $info[1]];
    }

    /** @return array<string, array<int, int>> */
    private static function available(string $path): array
    {
        if (isset(self::$cache[$path])) {
            return self::$cache[$path];
        }

        $found = ['webp' => [], 'jpg' => []];

        foreach (self::WIDTHS as $width) {
            foreach (['webp', 'jpg'] as $format) {
                $candidate = public_path(self::derivativePath($path, $width, $format));

                if (is_file($candidate)) {
                    $found[$format][$width] = (int) filesize($candidate);
                }
            }
        }

        // Largest first reads better in devtools and matches `sizes` ordering.
        foreach (['webp', 'jpg'] as $format) {
            krsort($found[$format]);
        }

        return self::$cache[$path] = $found;
    }

    private static function derivativePath(string $path, int $width, string $format): string
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $base = substr($path, 0, -(strlen($extension) + 1));

        return $base.'-'.$width.'.'.$format;
    }
}
