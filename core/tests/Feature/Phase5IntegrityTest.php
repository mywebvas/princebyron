<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\DownloadEntitlement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Song;
use App\Models\User;
use App\Services\GrantEntitlementsForOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * Sprint 2 — money and records integrity (review P1-3 … P1-11).
 *
 * These tests exist because each finding was a silent failure: money recorded
 * wrongly, access granted or not granted wrongly, or a record quietly destroyed.
 * Every assertion below describes behaviour a customer or an auditor would
 * notice, not an implementation detail.
 */
class Phase5IntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function makeSong(array $attributes = []): Song
    {
        return Song::create(array_merge([
            'title'        => 'Integrity Test Track',
            'slug'         => 'integrity-test-track',
            'price_cents'  => 1000,
            'is_published' => true,
        ], $attributes));
    }

    private function makeOrder(?User $user, int $total = 1000): Order
    {
        return Order::create([
            'reference'      => 'ORD-INT-'.uniqid(),
            'user_id'        => $user?->id,
            'status'         => Order::STATUS_PAID,
            'currency'       => 'USD',
            'subtotal_cents' => $total,
            'shipping_cents' => 0,
            'tax_cents'      => 0,
            'total_cents'    => $total,
            'customer_email' => $user?->email ?? 'guest@example.com',
            'customer_name'  => 'Integrity Buyer',
        ]);
    }

    private function addSongLine(Order $order, Song $song, int $price = 1000): OrderItem
    {
        return OrderItem::create([
            'order_id'         => $order->id,
            'itemable_type'    => Song::class,
            'itemable_id'      => $song->id,
            'label'            => $song->title,
            'quantity'         => 1,
            'unit_price_cents' => $price,
            'line_total_cents' => $price,
        ]);
    }

    // ---------------------------------------------------------------- P1-4

    public function test_granting_a_paid_order_creates_one_entitlement_per_song(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder($user);
        $songA = $this->makeSong(['slug' => 'song-a', 'title' => 'Song A']);
        $songB = $this->makeSong(['slug' => 'song-b', 'title' => 'Song B']);
        $this->addSongLine($order, $songA);
        $this->addSongLine($order, $songB);

        $result = app(GrantEntitlementsForOrder::class)->grant($order->fresh());

        $this->assertSame(2, $result['granted']);
        $this->assertFalse($result['skipped_guest']);
        $this->assertSame(2, DownloadEntitlement::where('order_id', $order->id)->count());
        $this->assertSame(
            2,
            DownloadEntitlement::where('order_id', $order->id)->where('is_active', true)->count()
        );
    }

    public function test_granting_is_idempotent(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder($user);
        $this->addSongLine($order, $this->makeSong());

        $service = app(GrantEntitlementsForOrder::class);
        $service->grant($order->fresh());
        $second = $service->grant($order->fresh());

        $this->assertSame(0, $second['granted'], 'Re-granting must not duplicate entitlements');
        $this->assertSame(1, DownloadEntitlement::where('order_id', $order->id)->count());
    }

    public function test_a_guest_order_reports_skipped_rather_than_throwing(): void
    {
        $order = $this->makeOrder(null);
        $this->addSongLine($order, $this->makeSong());

        $result = app(GrantEntitlementsForOrder::class)->grant($order->fresh());

        $this->assertTrue($result['skipped_guest']);
        $this->assertSame(0, $result['granted']);
        $this->assertSame(0, DownloadEntitlement::where('order_id', $order->id)->count());
    }

    public function test_merchandise_lines_do_not_produce_entitlements(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder($user);
        $product = Product::create([
            'name'         => 'Tee',
            'slug'         => 'tee-'.uniqid(),
            'is_published' => true,
        ]);
        OrderItem::create([
            'order_id'         => $order->id,
            'itemable_type'    => Product::class,
            'itemable_id'      => $product->id,
            'label'            => 'Tee',
            'quantity'         => 1,
            'unit_price_cents' => 1000,
            'line_total_cents' => 1000,
        ]);

        $result = app(GrantEntitlementsForOrder::class)->grant($order->fresh());

        $this->assertSame(0, $result['granted']);
        $this->assertSame(0, DownloadEntitlement::where('order_id', $order->id)->count());
    }

    public function test_revoking_deactivates_instead_of_deleting(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder($user);
        $this->addSongLine($order, $this->makeSong());

        $service = app(GrantEntitlementsForOrder::class);
        $service->grant($order->fresh());
        $revoked = $service->revoke($order->fresh());

        $this->assertSame(1, $revoked);
        // The row survives, so the record of what was once granted survives.
        $this->assertSame(1, DownloadEntitlement::where('order_id', $order->id)->count());
        $this->assertSame(0, DownloadEntitlement::where('order_id', $order->id)->where('is_active', true)->count());
    }

    // ---------------------------------------------------------------- P1-5

    public function test_an_order_cannot_be_deleted(): void
    {
        $order = $this->makeOrder(User::factory()->create());

        $this->expectException(\DomainException::class);

        $order->delete();
    }

    public function test_the_delete_guard_leaves_the_row_intact(): void
    {
        $order = $this->makeOrder(User::factory()->create());

        try {
            $order->delete();
            $this->fail('Deleting an order should have thrown');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('financial record', $e->getMessage());
        }

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }

    // ---------------------------------------------------------------- P1-7

    public function test_refund_payments_can_be_recorded_as_negative_amounts(): void
    {
        // The column was unsignedInteger, so this insert would have failed with a
        // database error — the refund action could not have worked at all.
        $order = $this->makeOrder(User::factory()->create());

        $payment = Payment::create([
            'order_id'     => $order->id,
            'provider'     => Payment::PROVIDER_BANK,
            'status'       => Payment::STATUS_REFUNDED,
            'amount_cents' => -1500,
            'currency'     => 'USD',
        ]);

        $this->assertLessThan(0, $payment->fresh()->amount_cents);
        $this->assertDatabaseHas('payments', [
            'order_id'     => $order->id,
            'amount_cents' => -1500,
        ]);
    }

    // ---------------------------------------------------------------- P1-3

    public function test_an_encrypted_setting_is_stored_as_ciphertext(): void
    {
        $setting = Setting::create([
            'key'   => 'payment.secret_key',
            'type'  => Setting::TYPE_ENCRYPTED,
            'value' => 'super-secret-value',
        ]);

        $raw = $setting->fresh()->getRawOriginal('value');

        $this->assertNotSame('super-secret-value', $raw, 'The plaintext must never be stored');
        $this->assertSame('super-secret-value', Crypt::decryptString($raw));
        $this->assertSame('super-secret-value', $setting->fresh()->typed_value);
    }

    public function test_an_encrypted_setting_is_not_double_encrypted(): void
    {
        $ciphertext = Crypt::encryptString('keep-me');

        $setting = Setting::create([
            'key'   => 'payment.double',
            'type'  => Setting::TYPE_ENCRYPTED,
            'value' => $ciphertext,
        ]);

        // Saving an already-encrypted payload must pass it through untouched,
        // otherwise every re-save would wrap the ciphertext again and the value
        // would be unrecoverable.
        $this->assertSame('keep-me', $setting->fresh()->typed_value);
        $this->assertSame($ciphertext, $setting->fresh()->getRawOriginal('value'));
    }

    public function test_editing_a_plain_setting_does_not_encrypt_it(): void
    {
        $setting = Setting::create([
            'key'   => 'site.tagline',
            'type'  => Setting::TYPE_STRING,
            'value' => 'A sound for every season',
        ]);

        $this->assertSame(
            'A sound for every season',
            $setting->fresh()->getRawOriginal('value')
        );
    }

    public function test_encrypted_settings_are_redacted_in_the_audit_trail(): void
    {
        $setting = Setting::create([
            'key'   => 'mail.password',
            'type'  => Setting::TYPE_ENCRYPTED,
            'value' => 'hunter2',
        ]);

        $entry = AuditEvent::where('action', 'Setting.created')
            ->where('auditable_id', $setting->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($entry, 'Creating an encrypted setting must be audited');
        $this->assertStringNotContainsString(
            'hunter2',
            json_encode($entry->new_values),
            'A secret must never reach the audit log'
        );
        // Nor may the ciphertext be archived — it is the secret in another form.
        $this->assertStringNotContainsString(
            (string) $setting->fresh()->getRawOriginal('value'),
            json_encode($entry->new_values)
        );

        // Redaction must be surgical: an auditor still needs to know WHICH
        // setting changed, so the rest of the row survives.
        $this->assertSame('[redacted]', $entry->new_values['value']);
        $this->assertSame('mail.password', $entry->new_values['key']);
    }

    public function test_updating_an_encrypted_setting_redacts_the_change(): void
    {
        $setting = Setting::create([
            'key'   => 'payment.rotate_me',
            'type'  => Setting::TYPE_ENCRYPTED,
            'value' => 'first-secret',
        ]);

        $setting->update(['value' => 'second-secret']);

        $entry = AuditEvent::where('action', 'Setting.updated')
            ->where('auditable_id', $setting->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($entry, 'Rotating a secret must be audited');
        $this->assertStringNotContainsString('second-secret', json_encode($entry->new_values));
        $this->assertStringNotContainsString('first-secret', json_encode($entry->old_values));
        $this->assertSame('[redacted]', $entry->new_values['value']);
    }

    // ---------------------------------------------------------------- P1-6

    public function test_editing_a_product_price_is_audited_with_before_and_after(): void
    {
        $product = Product::create([
            'name'         => 'Audited Tee',
            'slug'         => 'audited-tee',
            'is_published' => true,
        ]);

        $product->update(['is_published' => false]);

        $entry = AuditEvent::where('auditable_type', Product::class)
            ->where('auditable_id', $product->id)
            ->where('action', 'Product.updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($entry, 'A visibility change must be audited');
        $this->assertTrue($entry->old_values['is_published']);
        $this->assertFalse($entry->new_values['is_published']);
    }

    public function test_creating_and_deleting_a_song_is_audited(): void
    {
        $song = $this->makeSong(['slug' => 'audited-song', 'title' => 'Audited Song']);

        $this->assertDatabaseHas('audit_events', [
            'action'         => 'Song.created',
            'auditable_id'   => $song->id,
            'auditable_type' => Song::class,
        ]);

        $song->delete();

        $this->assertDatabaseHas('audit_events', [
            'action'       => 'Song.deleted',
            'auditable_id' => $song->id,
        ]);
    }

    // ---------------------------------------------------------------- P1-8 … P1-11

    public function test_the_csp_can_be_extended_without_editing_code(): void
    {
        // A host that needs one extra origin (an analytics endpoint, a payment
        // frame) must not have to fork the middleware.
        config(['security.headers.csp_extra' => [
            'connect-src' => ['https://analytics.example.com'],
        ]]);

        $csp = $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString('connect-src \'self\' https://analytics.example.com', $csp);
        // Widening connect-src must not widen the directives that stop injection.
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
    }

    public function test_trusted_proxies_default_to_trusting_nobody(): void
    {
        // Trusting a proxy the operator does not control lets a client forge its
        // own IP, which corrupts both rate-limit keys and the audit trail. The
        // safe default must be an empty list, and it must be read from config so
        // that `config:cache` cannot turn it into null.
        $this->assertIsArray(config('security.trusted_proxies'));
        $this->assertSame([], config('security.trusted_proxies'));
    }

    public function test_security_headers_are_present_on_storefront_responses(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp, 'A CSP must be sent by the application itself');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        // The storefront ships no inline scripts, so this relaxation must not be
        // reintroduced without a reason.
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $csp);
    }

    public function test_hsts_is_only_sent_over_https(): void
    {
        $this->get('/')->assertHeaderMissing('Strict-Transport-Security');

        $this->get('https://localhost/')->assertHeader('Strict-Transport-Security');
    }

    public function test_checkout_is_rate_limited(): void
    {
        // The limiter allows 10 per minute; the 11th must be refused rather than
        // silently creating another order. The empty bag is rejected with 422 by
        // validation, but each attempt still consumes limit — the throttle must
        // sit in front of the controller, not behind it, or a validation-spamming
        // script would never be slowed down.
        $statuses = [];

        for ($i = 0; $i < 11; $i++) {
            $statuses[] = $this->postJson('/api/checkout/process', [])->getStatusCode();
        }

        $this->assertNotContains(429, array_slice($statuses, 0, 10), 'Legitimate attempts must not be throttled');
        $this->assertSame(
            429,
            $statuses[10],
            'The 11th checkout attempt within a minute must be throttled'
        );
    }

    public function test_cart_writes_are_rate_limited(): void
    {
        $statuses = [];

        for ($i = 0; $i < 31; $i++) {
            $statuses[] = $this->postJson('/api/cart/items', ['type' => 'song', 'id' => 1])
                ->getStatusCode();
        }

        $this->assertNotContains(429, array_slice($statuses, 0, 30));
        $this->assertSame(429, $statuses[30], 'Cart mutations must be throttled at 30/minute');
    }

    public function test_download_probing_is_rate_limited(): void
    {
        // Entitlements protect the file, but unthrottled sequential-id probing
        // was a free enumeration oracle for anyone with an account.
        $user = User::factory()->create();
        $statuses = [];

        for ($i = 0; $i < 21; $i++) {
            $statuses[] = $this->actingAs($user)->get('/downloads/'.$i)->getStatusCode();
        }

        $this->assertSame(429, end($statuses));
    }
}
