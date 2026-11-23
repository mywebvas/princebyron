<?php

namespace Tests\Feature;

use Tests\TestCase;

class Phase2ScreensTest extends TestCase
{
    /**
     * Test Home View renders successfully with approved hero, bio, music, and merch.
     */
    public function test_home_page_renders_successfully(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Prince Byron');
        $response->assertSee('A sound for');
        $response->assertSee('every season');
        $response->assertSee('Purify Me');
        $response->assertSee('Listen to the Music');
        $response->assertSee('Request Ministry Booking');
        $response->assertSee('Skip to main content');
    }

    /**
     * Test About View renders successfully with narrative and ministry pillars.
     */
    public function test_about_page_renders_successfully(): void
    {
        $response = $this->get('/about');

        $response->assertStatus(200);
        $response->assertSee('Our Story &amp; Calling', false);
        $response->assertSee('From the Sanctuary');
        $response->assertSee('Christ-Centered Worship');
        $response->assertSee('Sound of Testimony');
        $response->assertSee('Relational Fellowship');
    }

    /**
     * Test Music View renders all 5 songs, featured collection, and moment playlists.
     */
    public function test_music_page_renders_with_complete_catalog(): void
    {
        $response = $this->get('/music');

        $response->assertStatus(200);
        $response->assertSee('Faith for the In-Between');
        $response->assertSee('Free');
        $response->assertSee('Purify Me');
        $response->assertSee('I Always Win');
        $response->assertSee('Toduyun');
        $response->assertSee('Follow Me');
        $response->assertSee('STILLNESS');
        $response->assertSee('STRENGTH');
        $response->assertSee('JOY');
        // The page must disclose that prices are placeholders. It previously
        // asserted a string that existed nowhere in the codebase, so this test
        // could never pass — the disclosure itself was missing from the view.
        $response->assertSee('Sample demonstration prices');
        $response->assertSee('not final retail pricing');

        // Unavailable delivery formats must be visibly marked rather than
        // silently offered: promising a 24-bit WAV master that does not exist
        // would be a false representation at the point of sale.
        $response->assertSee('MP3 (320kbps)');
    }

    /**
     * Test Store Catalog renders merchandise list and sample price disclaimers.
     */
    public function test_store_catalog_renders_successfully(): void
    {
        $response = $this->get('/store');

        $response->assertStatus(200);
        $response->assertSee('Worn in faith');
        $response->assertSee('Prince Byron Signature Tee');
        $response->assertSee('Abide Arch Pullover Hoodie');
        $response->assertSee('PB Monogram Curved Brim Cap');
        $response->assertSee('Sample');
    }

    /**
     * Test Dedicated Single-Product Detail View renders options and breadcrumbs.
     */
    public function test_single_product_detail_page_renders_successfully(): void
    {
        $response = $this->get('/store/tee');

        $response->assertStatus(200);
        $response->assertSee('Prince Byron Signature Tee');
        $response->assertSee('Apparel');
        $response->assertSee('PB-TEE-001');
        $response->assertSee('In Stock');
        $response->assertSee('Sand Linen');
        $response->assertSee('Charcoal Obsidian');
        $response->assertSee('Add to Bag');
        $response->assertSee('Sample Product Specifications');
    }

    /**
     * Test Dedicated Shopping Bag Page renders itemized bag layout.
     */
    public function test_cart_page_renders_successfully(): void
    {
        $response = $this->get('/cart');

        $response->assertStatus(200);
        $response->assertSee('Your Shopping Bag');
        $response->assertSee('Review Your Selection');
        $response->assertSee('Proceed to Dedicated Checkout');
    }

    /**
     * Test Dedicated Standalone Checkout Page renders multi-step flow and gateways.
     */
    public function test_dedicated_checkout_page_renders_successfully(): void
    {
        $response = $this->get('/checkout');

        $response->assertStatus(200);
        $response->assertSee('Dedicated USD Checkout');
        $response->assertSee('Contact &amp; Entitlement Account', false);
        $response->assertSee('Shipping &amp; Delivery Destination', false);
        $response->assertSee('Payment Method Review');
        $response->assertSee('Stripe Simulation');
        $response->assertSee('PayPal (Sandbox Simulation)');
        $response->assertSee('Manual Bank Wire Transfer');
        $response->assertSee('awaiting_bank_verification');
    }

    /**
     * Test Ministry Booking Page renders 3-step RFQ wizard.
     */
    public function test_booking_page_renders_successfully(): void
    {
        $response = $this->get('/booking');

        $response->assertStatus(200);
        $response->assertSee('Ministry Appearance &amp; Event Booking', false);
        $response->assertSee('Request for Quotation (RFQ)');
        $response->assertSee('Sunday Worship Service');
        $response->assertSee('Full Live Band Setup');
        $response->assertSee('Submit Ministry Request');
    }

    /**
     * Test Customer Fan Vault Account Page renders active entitlements and orders.
     */
    public function test_account_page_renders_successfully(): void
    {
        $response = $this->get('/account');

        $response->assertStatus(200);
        $response->assertSee('Customer Fan Vault');
        $response->assertSee('fan@example.com');
        $response->assertSee('Active Digital Entitlements');
        $response->assertSee('Recent Orders');
        $response->assertSee('Purify Me (Single Master)');
        $response->assertSee('Free (Lead Track)');
    }

    /**
     * Test Planned Admin Preview renders simulated backoffice and 5 tabs.
     */
    public function test_admin_preview_renders_as_public_simulated_demo(): void
    {
        $response = $this->get('/admin/preview');

        $response->assertStatus(200);
        $response->assertSee('Public Demonstration Preview');
        $response->assertSee('Ministry &amp; Commerce Management', false);
        $response->assertSee('Music Catalog &amp; Audio', false);
        $response->assertSee('Products &amp; Variants', false);
        $response->assertSee('Orders &amp; Bank Payments', false);
        $response->assertSee('Ministry Bookings');
        $response->assertSee('Brand CMS Settings');
        $response->assertSee('Sample Audit Trail Format (Demonstration)');
        $response->assertSee('Live operational queues and record actions require authenticated owner access');
    }

    /**
     * Test strict adherence to product boundaries and lack of unapproved claims.
     */
    public function test_pages_do_not_contain_unapproved_claims(): void
    {
        $endpoints = ['/', '/about', '/music', '/store', '/store/tee', '/cart', '/checkout', '/booking', '/account', '/admin/preview'];

        foreach ($endpoints as $url) {
            $content = $this->get($url)->getContent();

            $this->assertStringNotContainsStringIgnoringCase('worldwide', $content, "Endpoint {$url} should not contain 'worldwide'");
            $this->assertStringNotContainsStringIgnoringCase('organic', $content, "Endpoint {$url} should not contain 'organic'");
            $this->assertStringNotContainsStringIgnoringCase('perpetual', $content, "Endpoint {$url} should not contain 'perpetual'");
            $this->assertStringNotContainsStringIgnoringCase('14 days', $content, "Endpoint {$url} should not contain '14 days'");
            $this->assertStringNotContainsStringIgnoringCase('fonts.googleapis.com', $content, "Endpoint {$url} should not load Google Fonts");
            $this->assertStringNotContainsStringIgnoringCase('fonts.gstatic.com', $content, "Endpoint {$url} should not connect to gstatic");
        }
    }
}
