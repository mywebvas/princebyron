<?php

namespace Tests\Feature;

use App\Models\ContentSection;
use App\Support\LegalPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Legal pages (Phase 6).
 *
 * The risk this guards against is not a crash — it is a page that LOOKS like a
 * finished policy. A customer could rely on it, and a card processor could approve
 * the store on its strength. Every assertion below exists to keep a template
 * visibly unfinished.
 */
class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The seeded rows are what a real install has after SettingsSeeder and
        // ContentSectionsSeeder run.
        $this->seed(\Database\Seeders\ContentSectionsSeeder::class);
    }

    public function test_every_required_legal_page_responds_successfully(): void
    {
        // Dead policy links are a review failure for any card processor, and the
        // footer links them from every page.
        foreach (LegalPage::slugs() as $slug) {
            $this->get("/{$slug}")->assertOk();
        }
    }

    public function test_a_page_with_no_written_body_shows_the_template_banner(): void
    {
        $response = $this->get('/refund-policy');

        $response->assertOk();
        $response->assertSee('This page is a template, not a policy', false);
    }

    public function test_a_template_page_is_noindex(): void
    {
        // A search engine must not publish unfinished terms as this store's
        // policy. This is the part that cannot be seen in a browser, which is
        // exactly why it needs a test.
        $response = $this->get('/privacy');

        $response->assertOk();
        $response->assertSee('noindex', false);
    }

    public function test_owner_written_content_replaces_the_template(): void
    {
        ContentSection::where('section_key', 'legal_refund_policy')->update([
            'body' => "Digital downloads are non-refundable once the file has been downloaded.\n\nPhysical items may be returned within 30 days.",
        ]);

        $response = $this->get('/refund-policy');

        $response->assertOk();
        $response->assertSee('non-refundable once the file has been downloaded', false);
        $response->assertDontSee('This page is a template, not a policy', false);
    }

    public function test_owner_content_is_escaped_not_rendered_as_html(): void
    {
        // A legal page is the last place that should accept pasted markup. If this
        // ever regresses it is stored XSS on a page linked from every footer.
        ContentSection::where('section_key', 'legal_terms')->update([
            'body' => '<script>alert("xss")</script> and <b>bold</b>',
        ]);

        $response = $this->get('/terms');

        $response->assertOk();
        $response->assertDontSee('<script>alert("xss")</script>', false);
        $response->assertSee('&lt;script&gt;', false);
    }

    public function test_an_owner_written_page_is_indexable_again(): void
    {
        ContentSection::where('section_key', 'legal_privacy')->update([
            'body' => 'We collect your name and email address to fulfil orders.',
        ]);

        $response = $this->get('/privacy');

        $response->assertOk();
        $response->assertDontSee('noindex', false);
    }

    public function test_a_hidden_policy_returns_404_rather_than_an_empty_shell(): void
    {
        ContentSection::where('section_key', 'legal_shipping')->update(['is_visible' => false]);

        $this->get('/shipping')->assertNotFound();
    }

    public function test_an_unknown_slug_is_not_matched_by_the_legal_route(): void
    {
        // The route is constrained to exactly the four defined slugs, so it cannot
        // shadow anything else or invent pages that have no definition.
        $this->get('/not-a-real-policy')->assertNotFound();
    }

    public function test_the_checkout_links_to_the_terms_at_the_point_of_sale(): void
    {
        // A footer link two screens away is not agreement. This asserts the terms
        // are reachable where the order is actually placed.
        $response = $this->get('/checkout');

        $response->assertOk();
        $response->assertSee('/terms', false);
        $response->assertSee('Terms of Sale', false);
    }

    public function test_readiness_reports_unwritten_policies_as_a_blocker(): void
    {
        $this->artisan('pb:readiness')
            ->expectsOutputToContain('Still showing template text')
            ->assertSuccessful();
    }

    public function test_readiness_passes_the_policy_check_once_all_four_are_written(): void
    {
        foreach (LegalPage::requiredKeys() as $key) {
            ContentSection::where('section_key', $key)->update(['body' => 'Written policy text.']);
        }

        $this->artisan('pb:readiness')
            ->expectsOutputToContain('Refund, privacy, terms and shipping policies are written')
            ->assertSuccessful();
    }
}
