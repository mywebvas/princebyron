<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 3 — the craft pass (review P2-1 … P2-11).
 *
 * These tests assert things a visitor can perceive: that the page declares a
 * canonical URL, that a share link carries an image, that the display font is
 * actually fetched, that a screen reader is told which nav item is current.
 *
 * They deliberately do not assert "the CSS file contains .toast-notification" —
 * tools/check-classes.mjs owns that, because it can check every class at once
 * and cannot forget.
 */
class Phase6CraftTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\ProductsSeeder::class);
        $this->seed(\Database\Seeders\SongsSeeder::class);
    }

    // ------------------------------------------------------------ P2-7 fonts

    public function test_the_display_font_is_preloaded_and_actually_exists(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('/fonts/cinzel-latin.woff2', false);

        // A preload for a missing file is worse than no preload: the browser
        // makes a request, gets a 404, and the font swap fails silently.
        $this->assertFileExists(public_path('fonts/cinzel-latin.woff2'));
        $this->assertFileExists(public_path('fonts/source-sans-latin.woff2'));
    }

    public function test_the_fonts_are_self_hosted_and_small(): void
    {
        // No CDN: a third-party request in front of first paint, plus a visitor
        // IP logged by a company they never chose to visit.
        $total = 0;

        foreach (glob(public_path('fonts/*.woff2')) as $file) {
            $total += filesize($file);
        }

        $this->assertGreaterThan(0, $total);
        $this->assertLessThan(
            150 * 1024,
            $total,
            'Both families, both scripts, should stay under 150 KB in total'
        );
    }

    public function test_the_font_licences_ship_with_the_fonts(): void
    {
        // The SIL Open Font License requires the licence to travel with the
        // font. Shipping the woff2 without OFL.txt is a licence violation, not
        // a documentation omission.
        $this->assertFileExists(public_path('fonts/OFL-Cinzel.txt'));
        $this->assertFileExists(public_path('fonts/OFL-SourceSans3.txt'));
    }

    // ----------------------------------------------------------- P2-6 images

    public function test_the_homepage_offers_a_responsive_hero(): void
    {
        $response = $this->get('/');
        $html = $response->getContent();

        $this->assertStringContainsString('<picture>', $html, 'The hero should be served through <picture>');
        $this->assertStringContainsString('srcset=', $html);
        $this->assertStringContainsString('type="image/webp"', $html);
        // CLS: without intrinsic dimensions the layout shifts when the image
        // arrives, which is the single most visible performance defect on a
        // phone.
        $this->assertMatchesRegularExpression('/<img[^>]+width="\d+"/', $html);
        $this->assertMatchesRegularExpression('/<img[^>]+height="\d+"/', $html);
    }

    public function test_the_hero_is_eager_and_everything_else_is_lazy(): void
    {
        $html = $this->get('/')->getContent();

        // The LCP element must not be lazy-loaded.
        $this->assertMatchesRegularExpression('/<img[^>]+fetchpriority="high"[^>]*>/', $html);
        $this->assertStringContainsString('loading="eager"', $html);
        $this->assertStringContainsString('loading="lazy"', $html);
    }

    public function test_the_derivatives_that_srcset_advertises_exist(): void
    {
        // A hand-written srcset rots into a 404 on the largest screen. The
        // component reads the directory, so this asserts the directory is
        // complete for the images the site actually references.
        foreach (['images/prince-byron-hero.jpeg', 'images/prince-byron-portrait.jpeg'] as $source) {
            foreach ([480, 960] as $width) {
                foreach (['webp', 'jpg'] as $format) {
                    $path = public_path(str_replace('.jpeg', "-{$width}.{$format}", $source));

                    $this->assertFileExists($path, "Missing derivative for {$source} at {$width}px");
                    $this->assertLessThan(
                        250 * 1024,
                        filesize($path),
                        "{$path} exceeds the per-file budget"
                    );
                }
            }
        }
    }

    public function test_the_480px_hero_is_small_enough_for_a_phone(): void
    {
        $file = public_path('images/prince-byron-hero-480.webp');

        $this->assertFileExists($file);
        // The review set this target: the LCP hero should be <= 180 KB at the
        // 375px viewport. It is 31 KB; the budget leaves room for a heavy
        // replacement photograph without silently regressing.
        $this->assertLessThan(180 * 1024, filesize($file));
    }

    // ------------------------------------------------------------- P2-5 icons

    public function test_the_favicon_is_a_real_icon_and_not_a_placeholder(): void
    {
        $file = public_path('favicon.ico');

        $this->assertFileExists($file);
        // It was a 0-byte file for the entire review period. A byte-count floor
        // catches "committed an empty placeholder" without pinning a size.
        $this->assertGreaterThan(1000, filesize($file));
        $this->assertFileExists(public_path('icons/apple-touch-icon.png'));
        $this->assertFileExists(public_path('manifest.webmanifest'));
    }

    public function test_the_web_manifest_is_valid_json_with_icons(): void
    {
        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true);

        $this->assertIsArray($manifest);
        $this->assertSame('/', $manifest['start_url']);
        $this->assertNotEmpty($manifest['icons']);

        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')), "Manifest references a missing icon: {$icon['src']}");
        }
    }

    // --------------------------------------------------------------- P2-4 SEO

    public function test_every_page_declares_a_canonical_url(): void
    {
        foreach (['/', '/about', '/music', '/store', '/booking'] as $path) {
            $response = $this->get($path);

            $response->assertOk();
            $this->assertMatchesRegularExpression(
                '/<link rel="canonical" href="[^"]+">/',
                $response->getContent(),
                "Missing canonical on {$path}"
            );
        }
    }

    public function test_the_homepage_carries_open_graph_and_structured_data(): void
    {
        $html = $this->get('/')->getContent();

        $this->assertStringContainsString('property="og:title"', $html);
        $this->assertStringContainsString('property="og:image"', $html);
        $this->assertStringContainsString('property="og:type"', $html);
        $this->assertStringContainsString('name="twitter:card"', $html);

        // JSON-LD must name the artist and link the profiles, or a search engine
        // has no way to connect this domain to the existing audience.
        $this->assertStringContainsString('application/ld+json', $html);
        $this->assertStringContainsString('"@type":"Organization"', $html);
        $this->assertStringContainsString('open.spotify.com', $html);
    }

    public function test_a_product_page_publishes_a_product_offer(): void
    {
        $products = app(\App\Services\CatalogService::class)->products();
        $slug = array_key_first($products);

        $html = $this->get('/store/'.$slug)->getContent();

        $this->assertStringContainsString('property="og:type" content="product"', $html);
        $this->assertStringContainsString('"@type":"Product"', $html);
        $this->assertStringContainsString('"priceCurrency":"USD"', $html);
    }

    public function test_the_sitemap_lists_public_pages_and_products(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        $xml = $response->getContent();
        $this->assertStringContainsString('<urlset', $xml);
        $this->assertStringContainsString('<loc>'.url('/music').'</loc>', $xml);
        // Private surfaces must never appear.
        $this->assertStringNotContainsString('/admin', $xml);
        $this->assertStringNotContainsString('/account', $xml);
    }

    public function test_robots_txt_keeps_private_areas_out_of_the_index(): void
    {
        $robots = file_get_contents(public_path('robots.txt'));

        foreach (['/admin', '/account', '/install', '/downloads/', '/checkout'] as $private) {
            $this->assertStringContainsString('Disallow: '.$private, $robots, "robots.txt must disallow {$private}");
        }

        $this->assertStringContainsString('Sitemap:', $robots);
    }

    // ------------------------------------------------------ P2-3 error pages

    public function test_an_unknown_url_renders_the_branded_404(): void
    {
        $response = $this->get('/this-page-does-not-exist');

        $response->assertNotFound();
        // The default Laravel page is a grey exception screen. Assert on the
        // branded copy instead of the status code, which both pages share.
        $response->assertSee("This page isn't here");
        $response->assertSee('Back to the homepage');
    }

    public function test_the_error_page_offers_a_way_forward(): void
    {
        $response = $this->get('/this-page-does-not-exist');

        $response->assertSee('Browse the store');
        $response->assertSee('Listen to the music');
        // A dead end is where a customer stops being a customer.
        $response->assertSee('href="/"', false);
    }

    public function test_error_views_exist_for_every_status_a_customer_can_meet(): void
    {
        foreach (['401', '402', '403', '404', '419', '429', '500', '503'] as $status) {
            $this->assertFileExists(
                resource_path("views/errors/{$status}.blade.php"),
                "No branded view for {$status}"
            );
        }
    }

    public function test_the_error_page_is_not_indexable(): void
    {
        $this->get('/this-page-does-not-exist')
            ->assertSee('name="robots" content="noindex"', false);
    }

    // ------------------------------------------------- P2-8 / P2-9 truth pass

    public function test_the_unapproved_collection_price_is_gone(): void
    {
        $html = $this->get('/music')->getContent();

        // $12.00 was never an approved product (approved catalogue = 5 songs).
        $this->assertStringNotContainsString('$12.00', $html);
        $this->assertStringNotContainsString('12.00 USD', $html);
    }

    public function test_the_music_page_does_not_promise_wav_masters_it_does_not_have(): void
    {
        $html = $this->get('/music')->getContent();

        // Every song row has has_wav = false and the masters are pending.
        $this->assertStringNotContainsString('24-bit studio WAV masters', $html);
        $this->assertStringContainsString('WAV master pending', $html);
    }

    public function test_the_store_does_not_make_uncertified_sustainability_claims(): void
    {
        $html = $this->get('/store')->getContent();

        // "certified sustainable" is a regulated claim nobody in this project
        // has evidence for.
        $this->assertStringNotContainsString('certified sustainable', $html);
        $this->assertStringNotContainsString('Hahnemühle', $html);
        $this->assertStringNotContainsString('260 GSM', $html);
    }

    // ---------------------------------------------------------- P2-11 a11y

    public function test_the_current_page_is_marked_for_screen_readers(): void
    {
        // aria-current="page" is what tells a screen-reader user where they are
        // in the navigation. Visual users get the .active class; non-visual
        // users previously got nothing.
        $this->get('/music')->assertSee('aria-current="page"', false);
        $this->get('/about')->assertSee('aria-current="page"', false);
    }

    public function test_the_checkout_form_announces_invalid_fields(): void
    {
        $html = $this->get('/cart')->getContent();

        $this->get('/checkout')->assertOk();

        $checkout = $this->get('/checkout')->getContent();

        $this->assertStringContainsString('aria-describedby="checkout-error"', $checkout);
        $this->assertStringContainsString('aria-invalid', $checkout);
        $this->assertStringContainsString('role="alert"', $checkout);
    }

    public function test_the_order_confirmation_is_a_live_region(): void
    {
        $html = $this->get('/checkout')->getContent();

        // The confirmation replaces the form with no navigation; without a live
        // region a screen-reader user is told nothing happened.
        $this->assertStringContainsString('role="status"', $html);
        $this->assertStringContainsString('aria-live="polite"', $html);
    }
}
