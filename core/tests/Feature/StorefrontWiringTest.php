<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Sprint 1 — the storefront must be wired to the server.
 *
 * These tests exist because the previous suite only asserted that pages
 * rendered. It never checked that a visitor could actually buy anything, which
 * is why a hardcoded demo catalogue and a client-only cart looked "green"
 * (review P0-1, P0-2, P0-3).
 *
 * Every test here exercises the real HTTP surface end to end: the page, the
 * cart API, and — where money is involved — the order row that results.
 */
class StorefrontWiringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\ProductsSeeder::class);
        $this->seed(\Database\Seeders\SongsSeeder::class);
    }

    // =========================================================================
    // Catalogue is database-driven (P0-3)
    // =========================================================================

    public function test_store_page_lists_products_from_the_database(): void
    {
        $this->get('/store')
            ->assertStatus(200)
            ->assertSee('Prince Byron T-Shirt');

        // Unpublishing everything must leave the store empty. It must NOT
        // resurrect the demo catalogue: an owner taking a product down would
        // otherwise find a sample item standing in its place.
        Product::query()->update(['is_published' => false]);

        $this->get('/store')
            ->assertStatus(200)
            ->assertDontSee('Prince Byron T-Shirt')
            ->assertDontSee('Sample catalogue item', false);
    }

    public function test_product_page_shows_the_database_price_not_a_hardcoded_one(): void
    {
        $product = Product::where('slug', 'prince-byron-t-shirt')->firstOrFail();

        ProductVariant::where('product_id', $product->id)->update(['price_cents' => 9999]);

        $this->get('/store/'.$product->slug)
            ->assertStatus(200)
            ->assertSee('99.99');
    }

    public function test_unknown_product_slug_returns_404(): void
    {
        // Previously an unknown slug silently rendered the first product, so a
        // stale or mistyped link sold the wrong item.
        $this->get('/store/this-does-not-exist')->assertStatus(404);
    }

    public function test_unpublished_product_is_not_reachable(): void
    {
        $product = Product::where('slug', 'prince-byron-t-shirt')->firstOrFail();
        $product->update(['is_published' => false]);

        $this->get('/store/'.$product->slug)->assertStatus(404);
    }

    public function test_product_options_come_from_real_variants_only(): void
    {
        $product = Product::where('slug', 'prince-byron-t-shirt')->firstOrFail();

        $variant = ProductVariant::where('product_id', $product->id)->firstOrFail();
        $variant->update(['color' => 'Terracotta', 'size' => 'XXL', 'price_cents' => 4100]);

        // Offer sizes that do not exist must not be advertised.
        $this->get('/store/'.$product->slug)
            ->assertStatus(200)
            ->assertSee('Terracotta')
            ->assertSee('41.00');
    }

    // =========================================================================
    // Cart is server-authoritative (P0-1)
    // =========================================================================

    public function test_cart_starts_empty_for_a_new_visitor(): void
    {
        // The old store bootstrapped with two pre-seeded demo items.
        $this->getJson('/api/cart')
            ->assertStatus(200)
            ->assertJson([
                'item_count' => 0,
                'subtotal_cents' => 0,
                'items' => [],
            ]);
    }

    public function test_adding_a_variant_prices_it_from_the_database(): void
    {
        $product = Product::where('slug', 'prince-byron-t-shirt')->firstOrFail();
        $variant = ProductVariant::where('product_id', $product->id)->firstOrFail();
        $variant->update(['price_cents' => 3333, 'stock_quantity' => 5]);

        $response = $this->postJson('/api/cart/items', [
            'type' => 'variant',
            'id' => $variant->id,
            'quantity' => 2,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('cart.item_count', 2)
            ->assertJsonPath('cart.subtotal_cents', 6666)
            ->assertJsonPath('cart.items.0.unit_price_cents', 3333);

        // The price the browser was told is the price the server will charge.
        $this->getJson('/api/cart')->assertJsonPath('subtotal_cents', 6666);
    }

    public function test_cart_rejects_invented_item_ids(): void
    {
        $this->postJson('/api/cart/items', [
            'type' => 'variant',
            'id' => 999999,
            'quantity' => 1,
        ])->assertStatus(422)->assertJsonPath('success', false);

        $this->postJson('/api/cart/items', [
            'type' => 'song',
            'id' => 999999,
            'quantity' => 1,
        ])->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_quantity_is_clamped_to_available_stock(): void
    {
        $product = Product::where('slug', 'prince-byron-t-shirt')->firstOrFail();
        $variant = ProductVariant::where('product_id', $product->id)->firstOrFail();
        $variant->update(['stock_quantity' => 2]);

        $this->postJson('/api/cart/items', [
            'type' => 'variant',
            'id' => $variant->id,
            'quantity' => 10,
        ])->assertStatus(200)->assertJsonPath('cart.item_count', 2);
    }

    public function test_removing_the_last_variant_empties_the_cart(): void
    {
        $product = Product::where('slug', 'prince-byron-t-shirt')->firstOrFail();
        $variant = ProductVariant::where('product_id', $product->id)->firstOrFail();

        $this->postJson('/api/cart/items', ['type' => 'variant', 'id' => $variant->id, 'quantity' => 1]);

        $this->deleteJson('/api/cart/items', ['type' => 'variant', 'id' => $variant->id])
            ->assertStatus(200)
            ->assertJsonPath('cart.item_count', 0);
    }

    // =========================================================================
    // Checkout creates a real order (P0-2)
    // =========================================================================

    public function test_checkout_creates_a_real_order_row_with_a_server_reference(): void
    {
        $product = Product::where('slug', 'prince-byron-t-shirt')->firstOrFail();
        $variant = ProductVariant::where('product_id', $product->id)->firstOrFail();
        $variant->update(['price_cents' => 3200, 'stock_quantity' => 10]);

        \App\Models\ShippingRate::create([
            'name' => 'US Domestic',
            'country_code' => 'US',
            'rate_cents' => 500,
            'free_above_cents' => null,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->postJson('/api/cart/items', ['type' => 'variant', 'id' => $variant->id, 'quantity' => 1])
            ->assertStatus(200);

        $response = $this->postJson('/api/checkout/process', [
            'name' => 'Ada Buyer',
            'email' => 'ada@example.com',
            'address_line1' => '1 Grace Way',
            'city' => 'Lagos',
            'postal_code' => '100001',
            'country' => 'US',
            'payment_method' => 'bank_transfer',
        ]);

        $response->assertStatus(201)->assertJsonPath('success', true);

        $reference = $response->json('order_reference');

        // The reference is generated by the server, not Math.random() in the
        // browser, and it must correspond to a persisted order.
        $this->assertMatchesRegularExpression('/^PB-\d{8}-[A-Z0-9]{6}$/', $reference);
        $this->assertDatabaseHas('orders', [
            'reference' => $reference,
            'customer_email' => 'ada@example.com',
        ]);

        // Bank transfer must not grant downloads before the owner verifies.
        $this->assertDatabaseCount('download_entitlements', 0);

        // Server pricing: 3200 + 500 shipping.
        $this->assertSame(3700, $response->json('total_cents'));
    }

    public function test_checkout_rejects_an_empty_cart_instead_of_faking_an_order(): void
    {
        $this->postJson('/api/checkout/process', [
            'name' => 'Nobody',
            'email' => 'nobody@example.com',
            'payment_method' => 'stripe',
        ])->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_checkout_rejects_the_wire_payment_value_the_old_form_sent(): void
    {
        // The old form posted "wire"; the API contract is "bank_transfer".
        // This guards the contract so a UI regression cannot silently pass.
        $product = Product::where('slug', 'prince-byron-t-shirt')->firstOrFail();
        $variant = ProductVariant::where('product_id', $product->id)->firstOrFail();

        $this->postJson('/api/cart/items', ['type' => 'variant', 'id' => $variant->id, 'quantity' => 1]);

        $this->postJson('/api/checkout/process', [
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'address_line1' => '1 Way',
            'city' => 'Lagos',
            'postal_code' => '100001',
            'country' => 'US',
            'payment_method' => 'wire',
        ])->assertStatus(422);
    }

    public function test_checkout_requires_a_two_letter_country_code(): void
    {
        $product = Product::where('slug', 'prince-byron-t-shirt')->firstOrFail();
        $variant = ProductVariant::where('product_id', $product->id)->firstOrFail();

        $this->postJson('/api/cart/items', ['type' => 'variant', 'id' => $variant->id, 'quantity' => 1]);

        // The old form posted "United States", which the API rejected.
        $this->postJson('/api/checkout/process', [
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'address_line1' => '1 Way',
            'city' => 'Lagos',
            'postal_code' => '100001',
            'country' => 'United States',
            'payment_method' => 'bank_transfer',
        ])->assertStatus(422);
    }

    public function test_checkout_calculate_returns_server_totals_for_a_country(): void
    {
        \App\Models\ShippingRate::create([
            'name' => 'Nigeria',
            'country_code' => 'NG',
            'rate_cents' => 900,
            'free_above_cents' => null,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $product = Product::where('slug', 'prince-byron-t-shirt')->firstOrFail();
        $variant = ProductVariant::where('product_id', $product->id)->firstOrFail();
        $variant->update(['price_cents' => 2000]);

        $this->postJson('/api/cart/items', ['type' => 'variant', 'id' => $variant->id, 'quantity' => 1]);

        $this->getJson('/api/checkout/calculate?country=NG')
            ->assertStatus(200)
            ->assertJsonPath('subtotal_cents', 2000)
            ->assertJsonPath('shipping_cents', 900)
            ->assertJsonPath('total_cents', 2900);
    }

    // =========================================================================
    // Demo data is not buyable
    // =========================================================================

    public function test_fallback_catalogue_is_present_but_not_purchasable(): void
    {
        // An un-seeded install must still look intentional. Children are
        // deleted before parents so foreign keys are satisfied on every engine;
        // PRAGMA-based tricks are SQLite-only and would break the PostgreSQL
        // job, which is the primary target.
        \Illuminate\Support\Facades\DB::table('product_images')->delete();
        \Illuminate\Support\Facades\DB::table('product_variants')->delete();
        \Illuminate\Support\Facades\DB::table('products')->delete();
        \Illuminate\Support\Facades\DB::table('songs')->delete();

        $response = $this->get('/store');
        $response->assertStatus(200);

        // The demo items are labelled as samples...
        $response->assertSee('Sample catalogue item', false);

        // ...and the cart cannot accept a made-up id.
        $this->postJson('/api/cart/items', ['type' => 'variant', 'id' => 1, 'quantity' => 1])
            ->assertStatus(422);
    }

    public function test_music_page_does_not_offer_unpublished_tracks_for_sale(): void
    {
        Song::query()->update(['is_published' => false]);

        $this->get('/music')
            ->assertStatus(200)
            ->assertDontSee('Purify Me');
    }

    // =========================================================================
    // Login page no longer publishes credentials (P1-1)
    // =========================================================================

    public function test_login_page_does_not_render_demo_credentials_or_prefill_a_password(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertDontSee('TemporaryFanPass2026!');
        $response->assertDontSee('changeme-dev-only');
        $response->assertDontSee('fan@princebyron.local');
        $response->assertDontSee('Local Demo Access');
    }

    public function test_seeded_customer_can_still_sign_in_after_the_banner_is_removed(): void
    {
        // Removing the advertised password must not break the account itself.
        $user = User::create([
            'name' => 'Fan',
            'email' => 'fan@example.com',
            'password' => Hash::make('a-real-password-123'),
            'role' => User::ROLE_CUSTOMER,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->post('/login', [
            'email' => 'fan@example.com',
            'password' => 'a-real-password-123',
        ]);

        $this->assertAuthenticatedAs($user);
    }
}
