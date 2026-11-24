<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Asset integrity — the files the markup points at must exist, and the manifest
 * must tell the truth about them.
 *
 * WHY THIS EXISTS
 * ---------------
 * Sprint 3 shipped a favicon set, a web manifest and responsive derivatives. All
 * three are promises made in HTML: <link rel="manifest">, <link rel="icon">, and
 * srcset entries the browser will request. Nothing checks them at render time —
 * a missing icon or a stale derivative path is a silent 404, and a manifest that
 * advertises sizes the files do not have produces a blurry or upscaled icon on
 * someone's home screen rather than an error.
 */
class AssetIntegrityTest extends TestCase
{
    public function test_the_referenced_icons_exist_and_are_not_empty(): void
    {
        $icons = [
            'favicon.ico',
            'favicon.svg',
            'icons/favicon-32.png',
            'icons/favicon-48.png',
            'icons/apple-touch-icon.png',
            'icons/icon-192.png',
            'icons/icon-512.png',
        ];

        foreach ($icons as $icon) {
            $path = public_path($icon);

            $this->assertFileExists($path, "{$icon} is referenced by the layout but missing.");
            $this->assertGreaterThan(
                100,
                filesize($path),
                "{$icon} exists but is effectively empty — a 0-byte favicon renders as nothing."
            );
        }
    }

    public function test_the_web_manifest_is_valid_and_every_icon_it_lists_exists(): void
    {
        $path = public_path('manifest.webmanifest');
        $this->assertFileExists($path);

        $manifest = json_decode(File::get($path), true);

        $this->assertIsArray($manifest, 'manifest.webmanifest is not valid JSON.');
        $this->assertSame('/', $manifest['start_url'] ?? null);
        $this->assertArrayHasKey('name', $manifest);
        $this->assertArrayHasKey('icons', $manifest);

        foreach ($manifest['icons'] as $icon) {
            $this->assertArrayHasKey('src', $icon);
            $this->assertFileExists(
                public_path(ltrim($icon['src'], '/')),
                "The manifest lists {$icon['src']} but the file is missing."
            );
        }
    }

    public function test_the_manifest_does_not_advertise_an_apple_touch_icon(): void
    {
        // Apple reads <link rel="apple-touch-icon"> and ignores the manifest. A
        // 180x180 entry in `icons` is a size no launcher asks for, and listing it
        // alongside 192/512 invites an upscale.
        $manifest = json_decode(File::get(public_path('manifest.webmanifest')), true);

        $sources = array_column($manifest['icons'], 'src');

        $this->assertNotContains(
            '/icons/apple-touch-icon.png',
            $sources,
            'The apple-touch-icon belongs in the <link> tag only, not in the manifest.'
        );
    }

    public function test_the_manifest_offers_a_maskable_icon(): void
    {
        // Android crops a regular icon into its adaptive shape and can clip the
        // artwork. A maskable entry lets the launcher use the safe zone instead.
        $manifest = json_decode(File::get(public_path('manifest.webmanifest')), true);

        $purposes = array_column($manifest['icons'], 'purpose');

        $this->assertContains('maskable', $purposes);
    }

    public function test_the_error_pages_stylesheet_lives_outside_the_vite_build_directory(): void
    {
        // A Vite build EMPTIES its output directory (emptyOutDir defaults to true
        // when the root sits inside public/). errors.css was parked in
        // public/build/, so the release step — `npm run build`, which Sprint 4's
        // packaging runs — deleted it, and every branded error page would deploy
        // unstyled. There is no error for that; the pages render, just wrong.
        $this->assertFileExists(
            public_path('errors.css'),
            'errors.css must live in public/, not public/build/, or a Vite build deletes it.'
        );

        $this->assertFileDoesNotExist(
            public_path('build/errors.css'),
            'errors.css has crept back into public/build/, where `npm run build` will delete it.'
        );

        $layout = File::get(resource_path('views/errors/layout.blade.php'));

        $this->assertStringContainsString(
            "asset('errors.css')",
            $layout,
            'The error layout must reference the stylesheet outside build/.'
        );

        foreach (['404', '403', '419', '429', '500', '503'] as $status) {
            $this->assertFileExists(resource_path("views/errors/{$status}.blade.php"));
        }
    }

    public function test_every_responsive_derivative_that_exists_is_a_real_image(): void
    {
        $checked = 0;

        foreach (File::glob(public_path('images/**/*-{480,960,1600,2400}.{jpg,webp}'), GLOB_BRACE) as $file) {
            $checked++;
            $this->assertGreaterThan(
                1000,
                filesize($file),
                basename($file).' is too small to be a real derivative.'
            );
        }

        // If this ever drops to zero the generator silently stopped running and
        // every <x-img> is falling back to the master file.
        $this->assertGreaterThan(0, $checked, 'No responsive derivatives found at all.');
    }
}
