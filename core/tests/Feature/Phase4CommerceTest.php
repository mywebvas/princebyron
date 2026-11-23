<?php

namespace Tests\Feature;

use App\Mail\BookingAcknowledgementMail;
use App\Mail\BookingNotificationMail;
use App\Models\AuditEvent;
use App\Models\BookingRequest;
use App\Models\DownloadEntitlement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingRate;
use App\Models\Song;
use App\Models\User;
use App\Services\CartService;
use App\Services\Payment\PayPalSandboxService;
use App\Services\Payment\StripeSandboxService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use LogicException;
use Tests\TestCase;

class Phase4CommerceTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected User $otherCustomer;
    protected Product $product;
    protected ProductVariant $variant;
    protected Song $song;

    protected \OpenSSLAsymmetricKey $paypalPrivateKey;
    protected string $paypalPublicKey;

    const TEST_PAYPAL_PRIVATE_KEY = <<<PEM
-----BEGIN PRIVATE KEY-----
MIICdgIBADANBgkqhkiG9w0BAQEFAASCAmAwggJcAgEAAoGBAOCYXB66vLogt1Db
YEEDHs1LEv9Dt7XbQi93AusOMJMl8GPOt1SzlnJ7P/G/eLUL1pF6LBj9+SuCf7G/
9QhJ7XILlAAcnGpnNrRM6CkFmUCWXt9+cpC+1TTAFSFqegY1fkS6yJhRY9Oz+uJP
W3XQVmA/AjDuZjzvqbj0/WSq5C/NAgMBAAECgYAW3WwDdN9h05LWGH9kHv0zyUDV
2/8H9Jp4WpVheMBZOrOOda0UYnQek42e/u86/0mI5fc3VCPZ2h9F6VT40w3pLYrp
s60VbJP2VUWveeSU+vkueQ5IxwVTSdk5VlmkSowzFEFyRzf4TKsKF4NPdTINZnvG
9dVul7a+eVOQ3EvJwQJBAPfmNuWs+ZzcuhEaAdnXuHNlgmFQiP2WppMfsCckJ0kt
Vq9nluqiqt6Vdghry3sEg3wbqxrxYOr7WnR2bqr1iZ8CQQDn7zI/RQnlngJ5WVQA
oGyHHJuli41iKUlzFAQA60GaCJRlcywK446QgqGW2BHB7HfAtOTriZAAyvxGIKlE
NGcTAkBC6P9eRK7m+Oi73vIq8z/U3cwwKgWftL7nLQFlvytfY/5mv6YLVLab92lx
Ym78mD5JDErFresD4a4Liz/ZowZvAkEA4pVa2FbBEXKpZhWvrCENhX2cTUElcYV2
PReLxb4PS8Uf/HBk0Yp355PG+0/BIVu7ribLEZRFe5DSnkwgMQKkwwJAGSI6t8CQ
mZ9ZjnqRWk0R8gtuOVlkjwIY8EQ86pQG4JiWMLwFjxY/wYAJ+hJIEYo9a/KuP234
5g8HIYljpWbgdA==
-----END PRIVATE KEY-----
PEM;

    const TEST_PAYPAL_PUBLIC_KEY = <<<PEM
-----BEGIN PUBLIC KEY-----
MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDgmFweury6ILdQ22BBAx7NSxL/
Q7e120IvdwLrDjCTJfBjzrdUs5Zyez/xv3i1C9aReiwY/fkrgn+xv/UISe1yC5QA
HJxqZza0TOgpBZlAll7ffnKQvtU0wBUhanoGNX5EusiYUWPTs/riT1t10FZgPwIw
7mY876m49P1kquQvzQIDAQAB
-----END PUBLIC KEY-----
PEM;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure test secrets in test environment
        config([
            'services.stripe.webhook_secret' => 'whsec_test_isolated_secret_princebyron',
            'services.paypal.webhook_id'     => 'WH-TEST-SANDBOX-PB-2026',
        ]);

        $this->paypalPrivateKey = openssl_pkey_get_private(self::TEST_PAYPAL_PRIVATE_KEY);
        $this->paypalPublicKey = self::TEST_PAYPAL_PUBLIC_KEY;
        PayPalSandboxService::setTestPublicKey($this->paypalPublicKey);

        $this->customer = User::create([
            'name'              => 'Grace Hopper',
            'email'             => 'grace@example.com',
            'password'          => bcrypt('Password123!'),
            'role'              => User::ROLE_CUSTOMER,
            'is_active'         => true,
            'email_verified_at' => now(),
        ]);

        $this->otherCustomer = User::create([
            'name'              => 'Ada Lovelace',
            'email'             => 'ada@example.com',
            'password'          => bcrypt('Password123!'),
            'role'              => User::ROLE_CUSTOMER,
            'is_active'         => true,
            'email_verified_at' => now(),
        ]);

        $this->product = Product::create([
            'name'         => 'Heavyweight Ministry Tee',
            'slug'         => 'ministry-tee',
            'description'  => 'Official heavyweight gospel shirt.',
            'type'         => 'physical',
            'is_published' => true,
        ]);

        $this->variant = ProductVariant::create([
            'product_id'     => $this->product->id,
            'sku'            => 'PB-TEE-BLK-L',
            'label'          => 'Size L / Obsidian Black',
            'price_cents'    => 3500, // $35.00
            'stock_quantity' => 10,
            'is_active'      => true,
        ]);

        $this->song = Song::create([
            'title'        => 'Purify Me',
            'slug'         => 'purify-me',
            'price_cents'  => 199, // $1.99
            'is_free'      => false,
            'is_published' => true,
            'has_mp3'      => true,
        ]);
    }

    protected function tearDown(): void
    {
        PayPalSandboxService::setTestPublicKey(null);
        parent::tearDown();
    }

    /**
     |--------------------------------------------------------------------------
     | 1. Audit Immutability (Model & Database Layer)
     |--------------------------------------------------------------------------
     */

    public function test_audit_event_update_and_delete_are_blocked_at_model_and_database_layer(): void
    {
        $audit = AuditEvent::create([
            'actor_id'       => $this->customer->id,
            'action'         => 'test.action',
            'auditable_type' => User::class,
            'auditable_id'   => $this->customer->id,
            'old_values'     => ['status' => 'created'],
            'new_values'     => ['status' => 'verified'],
            'ip_address'     => '127.0.0.1',
        ]);

        // 1. Eloquent model update must throw LogicException
        try {
            $audit->update(['action' => 'tampered.action']);
            $this->fail('Expected LogicException on model update.');
        } catch (LogicException $e) {
            $this->assertStringContainsString('strictly append-only', $e->getMessage());
        }

        // 2. Eloquent model delete must throw LogicException
        try {
            $audit->delete();
            $this->fail('Expected LogicException on model delete.');
        } catch (LogicException $e) {
            $this->assertStringContainsString('strictly append-only', $e->getMessage());
        }

        // 3. Raw database UPDATE query must trigger PostgreSQL / database exception
        DB::beginTransaction();
        try {
            DB::table('audit_events')->where('id', $audit->id)->update(['action' => 'sql_tampered']);
            $this->fail('Expected QueryException on direct DB update.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('strictly append-only', $e->getMessage());
        } finally {
            DB::rollBack();
        }

        // 4. Raw database DELETE query must trigger PostgreSQL / database exception
        DB::beginTransaction();
        try {
            DB::table('audit_events')->where('id', $audit->id)->delete();
            $this->fail('Expected QueryException on direct DB delete.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('strictly append-only', $e->getMessage());
        } finally {
            DB::rollBack();
        }

        // Verify the record remains intact in the database
        $this->assertDatabaseHas('audit_events', [
            'id'     => $audit->id,
            'action' => 'test.action',
        ]);
    }

    /**
     |--------------------------------------------------------------------------
     | 2. Shipping Configuration & Unconfigured Handling
     |--------------------------------------------------------------------------
     */

    public function test_checkout_blocks_physical_orders_when_shipping_is_unconfigured(): void
    {
        // Ensure no active shipping rates exist
        ShippingRate::query()->update(['is_active' => false]);

        // Add physical item to cart
        $this->postJson('/api/cart/items', [
            'type'     => 'variant',
            'id'       => $this->variant->id,
            'quantity' => 1,
        ])->assertStatus(200);

        // Calculate checkout
        $calcResponse = $this->getJson('/api/checkout/calculate?country=US');
        $calcResponse->assertStatus(200)
            ->assertJson([
                'success'    => false,
                'error_code' => 'shipping_unconfigured',
            ]);

        // Attempting to checkout must fail with 422
        $processResponse = $this->postJson('/api/checkout/process', [
            'name'           => 'Grace Hopper',
            'email'          => 'grace@example.com',
            'address_line1'  => '123 Navy Way',
            'city'           => 'Arlington',
            'state'          => 'VA',
            'postal_code'    => '22202',
            'country'        => 'US',
            'payment_method' => 'stripe',
        ]);

        $processResponse->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_digital_only_order_succeeds_even_when_physical_shipping_is_unconfigured(): void
    {
        // No active shipping rates
        ShippingRate::query()->update(['is_active' => false]);

        // Add digital song to cart
        $this->postJson('/api/cart/items', [
            'type'     => 'song',
            'id'       => $this->song->id,
            'quantity' => 1,
        ])->assertStatus(200);

        // Calculate checkout — digital only has 0 shipping and succeeds
        $calcResponse = $this->getJson('/api/checkout/calculate');
        $calcResponse->assertStatus(200)
            ->assertJson([
                'success'        => true,
                'shipping_cents' => 0,
                'subtotal_cents' => 199,
                'total_cents'    => 199,
            ]);

        // Checkout process succeeds without requiring physical shipping address
        $processResponse = $this->actingAs($this->customer)->postJson('/api/checkout/process', [
            'name'           => 'Grace Hopper',
            'email'          => 'grace@example.com',
            'payment_method' => 'stripe',
        ]);

        $processResponse->assertStatus(201)
            ->assertJson([
                'success'     => true,
                'total_cents' => 199,
                'status'      => Order::STATUS_PENDING_PAYMENT,
            ]);
    }

    public function test_checkout_calculates_correct_shipping_when_owner_activates_rates(): void
    {
        ShippingRate::create([
            'name'             => 'US Domestic',
            'country_code'     => 'US',
            'rate_cents'       => 500,
            'free_above_cents' => 5000,
            'is_active'        => true,
            'sort_order'       => 1,
        ]);

        // Add 1 shirt ($35.00 < $50.00 threshold)
        $this->postJson('/api/cart/items', [
            'type'     => 'variant',
            'id'       => $this->variant->id,
            'quantity' => 1,
        ])->assertStatus(200);

        $calc = $this->getJson('/api/checkout/calculate?country=US');
        $calc->assertStatus(200)
            ->assertJson([
                'success'        => true,
                'subtotal_cents' => 3500,
                'shipping_cents' => 500,
                'total_cents'    => 4000,
            ]);

        // Increase quantity to 2 shirts ($70.00 >= $50.00 threshold -> Free Shipping)
        $this->postJson('/api/cart/items', [
            'type'     => 'variant',
            'id'       => $this->variant->id,
            'quantity' => 1,
        ]);

        $calcFree = $this->getJson('/api/checkout/calculate?country=US');
        $calcFree->assertStatus(200)
            ->assertJson([
                'success'        => true,
                'subtotal_cents' => 7000,
                'shipping_cents' => 0,
                'total_cents'    => 7000,
            ]);
    }

    /**
     |--------------------------------------------------------------------------
     | 3. Atomic Stock Reservation & Concurrency Protection
     |--------------------------------------------------------------------------
     */

    public function test_checkout_reserves_stock_atomically_and_prevents_overselling(): void
    {
        // Set variant stock to exactly 1
        $this->variant->update(['stock_quantity' => 1]);

        ShippingRate::create([
            'name'             => 'US Domestic',
            'country_code'     => 'US',
            'rate_cents'       => 500,
            'free_above_cents' => null,
            'is_active'        => true,
            'sort_order'       => 1,
        ]);

        // First checkout adds 1 to bag and orders successfully
        $this->postJson('/api/cart/items', [
            'type'     => 'variant',
            'id'       => $this->variant->id,
            'quantity' => 1,
        ]);

        $order1 = $this->postJson('/api/checkout/process', [
            'name'           => 'Buyer One',
            'email'          => 'buyer1@example.com',
            'address_line1'  => '123 St',
            'city'           => 'Dallas',
            'postal_code'    => '75001',
            'country'        => 'US',
            'payment_method' => 'stripe',
        ]);
        $order1->assertStatus(201);

        // Stock is now 0
        $this->variant->refresh();
        $this->assertEquals(0, $this->variant->stock_quantity);

        // Second checkout attempts to purchase the same variant
        $this->postJson('/api/cart/items', [
            'type'     => 'variant',
            'id'       => $this->variant->id,
            'quantity' => 1,
        ]);

        $order2 = $this->postJson('/api/checkout/process', [
            'name'           => 'Buyer Two',
            'email'          => 'buyer2@example.com',
            'address_line1'  => '456 Ave',
            'city'           => 'Dallas',
            'postal_code'    => '75001',
            'country'        => 'US',
            'payment_method' => 'stripe',
        ]);

        // Fails with 422 Insufficient Stock
        $order2->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        // Stock does NOT become negative!
        $this->variant->refresh();
        $this->assertEquals(0, $this->variant->stock_quantity);
    }

    /**
     |--------------------------------------------------------------------------
     | 4. Manual Bank Transfer Order & Reconciliation Flow
     |--------------------------------------------------------------------------
     */

    public function test_manual_bank_transfer_order_starts_in_awaiting_verification_and_withholds_entitlements(): void
    {
        $this->postJson('/api/cart/items', [
            'type'     => 'song',
            'id'       => $this->song->id,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($this->customer)->postJson('/api/checkout/process', [
            'name'           => 'Grace Hopper',
            'email'          => 'grace@example.com',
            'payment_method' => 'bank_transfer',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success'        => true,
                'status'         => Order::STATUS_AWAITING_BANK,
                'payment_method' => 'bank_transfer',
            ]);

        $ref = $response->json('order_reference');
        $order = Order::where('reference', $ref)->first();
        $this->assertNotNull($order);
        $this->assertEquals(Order::STATUS_AWAITING_BANK, $order->status);

        // Security requirement: Manual bank transfer MUST NOT grant download entitlement before verification!
        $this->assertFalse(
            DownloadEntitlement::where('order_id', $order->id)->where('is_active', true)->exists(),
            'Manual bank transfer order must not grant active downloads before owner reconciliation.'
        );

        $payment = $order->payment;
        $this->assertNotNull($payment);
        $this->assertEquals(Payment::STATUS_AWAITING_VERIFICATION, $payment->status);
        $this->assertFalse($payment->signature_verified);
    }

    /**
     |--------------------------------------------------------------------------
     | 5. Customer Accounts & Cross-Account Isolation
     |--------------------------------------------------------------------------
     */

    public function test_customer_registration_and_authentication(): void
    {
        $regResponse = $this->post('/register', [
            'name'                  => 'New Fan',
            'email'                 => 'newfan@example.com',
            'password'              => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
        ]);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'newfan@example.com',
            'role'  => User::ROLE_CUSTOMER,
        ]);

        // Logout
        $this->post('/logout');
        $this->assertGuest();

        // Login with invalid credentials fails
        $badLogin = $this->postJson('/login', [
            'email'    => 'newfan@example.com',
            'password' => 'WrongPass',
        ]);
        $badLogin->assertStatus(422);
        $this->assertGuest();

        // Login with correct credentials succeeds
        $goodLogin = $this->postJson('/login', [
            'email'    => 'newfan@example.com',
            'password' => 'StrongPassword123!',
        ]);
        $goodLogin->assertStatus(200);
        $this->assertAuthenticated();
    }

    public function test_customer_cannot_view_another_customers_order(): void
    {
        $order = Order::create([
            'reference'      => 'PB-SEC-CUST-A',
            'user_id'        => $this->customer->id,
            'status'         => Order::STATUS_PAID,
            'currency'       => 'USD',
            'subtotal_cents' => 3500,
            'total_cents'    => 3500,
            'customer_email' => $this->customer->email,
            'customer_name'  => $this->customer->name,
        ]);

        // Customer Grace can view own order
        $this->actingAs($this->customer)
            ->getJson("/account/orders/{$order->reference}")
            ->assertStatus(200);

        // Customer Ada cannot view Grace's order (403 Forbidden)
        $this->actingAs($this->otherCustomer)
            ->getJson("/account/orders/{$order->reference}")
            ->assertStatus(403);
    }

    /**
     |--------------------------------------------------------------------------
     | 6. Stripe Sandbox Webhook, Fail-Closed & Idempotency
     |--------------------------------------------------------------------------
     */

    public function test_stripe_fails_closed_when_unconfigured(): void
    {
        config(['services.stripe.webhook_secret' => null]);

        $response = $this->call('POST', '/webhooks/stripe', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => 't=' . time() . ',v1=test',
            'CONTENT_TYPE'          => 'application/json',
        ], json_encode(['id' => 'evt_test', 'type' => 'payment_intent.succeeded']));

        // Security requirement: Missing secret must fail closed with 500
        $response->assertStatus(500)
            ->assertJson(['status' => 'error']);
    }

    public function test_stripe_webhook_marks_order_paid_and_grants_entitlements(): void
    {
        $order = Order::create([
            'reference'      => 'PB-STRIPE-001',
            'user_id'        => $this->customer->id,
            'status'         => Order::STATUS_PENDING_PAYMENT,
            'currency'       => 'USD',
            'subtotal_cents' => 199,
            'total_cents'    => 199,
            'customer_email' => $this->customer->email,
            'customer_name'  => $this->customer->name,
        ]);

        OrderItem::create([
            'order_id'         => $order->id,
            'itemable_type'    => Song::class,
            'itemable_id'      => $this->song->id,
            'label'            => $this->song->title,
            'quantity'         => 1,
            'unit_price_cents' => 199,
            'line_total_cents' => 199,
        ]);

        $eventId = 'evt_test_stripe_' . uniqid();
        $payloadArray = [
            'id'   => $eventId,
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id'                   => 'pi_test_123',
                    'amount'               => 199,
                    'currency'             => 'usd',
                    'metadata'             => ['order_reference' => $order->reference],
                    'payment_method_types' => ['card'],
                ],
            ],
        ];

        $rawPayload = json_encode($payloadArray);
        $secret = config('services.stripe.webhook_secret');
        $timestamp = time();
        $signature = StripeSandboxService::generateTestSignature($rawPayload, $secret, $timestamp);

        // 1. Invalid signature must return 400
        $this->call('POST', '/webhooks/stripe', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => 't=' . $timestamp . ',v1=invalidsig',
            'CONTENT_TYPE'          => 'application/json',
        ], $rawPayload)->assertStatus(400);

        // 2. Valid webhook marks order paid and grants entitlement
        $response = $this->call('POST', '/webhooks/stripe', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $signature,
            'CONTENT_TYPE'          => 'application/json',
        ], $rawPayload);

        $response->assertStatus(200);

        $order->refresh();
        $this->assertEquals(Order::STATUS_PAID, $order->status);

        // Download entitlement granted
        $entitlement = DownloadEntitlement::where('order_id', $order->id)->first();
        $this->assertNotNull($entitlement);
        $this->assertTrue($entitlement->is_active);
        $this->assertEquals($this->song->id, $entitlement->song_id);

        // Audit event recorded
        $this->assertDatabaseHas('audit_events', [
            'auditable_id'   => $order->id,
            'action'         => 'order.paid',
            'correlation_id' => $eventId,
        ]);

        // 3. Replay attack / duplicate event must return duplicate_ignored without repeating
        $replayResponse = $this->call('POST', '/webhooks/stripe', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $signature,
            'CONTENT_TYPE'          => 'application/json',
        ], $rawPayload);

        $replayResponse->assertStatus(200)
            ->assertJson(['status' => 'duplicate_ignored']);
    }

    public function test_stripe_webhook_rejects_amount_tampering(): void
    {
        $order = Order::create([
            'reference'      => 'PB-STRIPE-TAMPER',
            'user_id'        => $this->customer->id,
            'status'         => Order::STATUS_PENDING_PAYMENT,
            'currency'       => 'USD',
            'subtotal_cents' => 5000,
            'total_cents'    => 5000, // $50.00
            'customer_email' => $this->customer->email,
            'customer_name'  => $this->customer->name,
        ]);

        $payloadArray = [
            'id'   => 'evt_tampered_' . uniqid(),
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id'       => 'pi_tampered',
                    'amount'   => 100, // Spoofed amount!
                    'currency' => 'usd',
                    'metadata' => ['order_reference' => $order->reference],
                ],
            ],
        ];

        $rawPayload = json_encode($payloadArray);
        $secret = config('services.stripe.webhook_secret');
        $signature = StripeSandboxService::generateTestSignature($rawPayload, $secret, time());

        $response = $this->call('POST', '/webhooks/stripe', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $signature,
            'CONTENT_TYPE'          => 'application/json',
        ], $rawPayload);

        $response->assertStatus(422);

        $order->refresh();
        $this->assertEquals(Order::STATUS_PENDING_PAYMENT, $order->status);
    }

    public function test_stripe_refund_webhook_revokes_digital_entitlements(): void
    {
        $order = Order::create([
            'reference'      => 'PB-REFUND-001',
            'user_id'        => $this->customer->id,
            'status'         => Order::STATUS_PAID,
            'currency'       => 'USD',
            'subtotal_cents' => 199,
            'total_cents'    => 199,
            'customer_email' => $this->customer->email,
            'customer_name'  => $this->customer->name,
        ]);

        $entitlement = DownloadEntitlement::create([
            'user_id'   => $this->customer->id,
            'order_id'  => $order->id,
            'song_id'   => $this->song->id,
            'format'    => DownloadEntitlement::FORMAT_MP3,
            'is_active' => true,
        ]);

        $payloadArray = [
            'id'   => 'evt_refund_' . uniqid(),
            'type' => 'charge.refunded',
            'data' => [
                'object' => [
                    'id'       => 're_123',
                    'amount'   => 199,
                    'currency' => 'usd',
                    'metadata' => ['order_reference' => $order->reference],
                ],
            ],
        ];

        $rawPayload = json_encode($payloadArray);
        $secret = config('services.stripe.webhook_secret');
        $signature = StripeSandboxService::generateTestSignature($rawPayload, $secret, time());

        $this->call('POST', '/webhooks/stripe', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $signature,
            'CONTENT_TYPE'          => 'application/json',
        ], $rawPayload)->assertStatus(200);

        $order->refresh();
        $this->assertEquals(Order::STATUS_REFUNDED, $order->status);

        // Entitlement revoked!
        $entitlement->refresh();
        $this->assertFalse($entitlement->is_active);
    }

    /**
     |--------------------------------------------------------------------------
     | 7. PayPal Webhook Verification (Documented Asymmetric Flow)
     |--------------------------------------------------------------------------
     */

    public function test_paypal_fails_closed_when_unconfigured(): void
    {
        config(['services.paypal.webhook_id' => null]);

        $response = $this->call('POST', '/webhooks/paypal', [], [], [], [], json_encode(['id' => 'evt_1']));

        $response->assertStatus(500)
            ->assertJson(['status' => 'error']);
    }

    public function test_paypal_webhook_marks_order_paid_and_prevents_duplicate_replay(): void
    {
        $order = Order::create([
            'reference'      => 'PB-PAYPAL-001',
            'user_id'        => $this->customer->id,
            'status'         => Order::STATUS_PENDING_PAYMENT,
            'currency'       => 'USD',
            'subtotal_cents' => 3500,
            'total_cents'    => 3500,
            'customer_email' => $this->customer->email,
            'customer_name'  => $this->customer->name,
        ]);

        $eventId = 'WH-PAYPAL-EVT-' . uniqid();
        $payloadArray = [
            'id'         => $eventId,
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource'   => [
                'id'        => 'CAP-12345',
                'custom_id' => $order->reference,
                'amount'    => [
                    'value'         => '35.00',
                    'currency_code' => 'USD',
                ],
            ],
        ];

        $rawPayload = json_encode($payloadArray);
        $webhookId = config('services.paypal.webhook_id');
        $transmissionId = 'trans-123-' . uniqid();
        $timestamp = (string) time();

        // Generate documented asymmetric RSA signature
        $signature = PayPalSandboxService::generateDocumentedTestSignature(
            $rawPayload,
            $webhookId,
            $transmissionId,
            $timestamp,
            $this->paypalPrivateKey
        );

        $headers = [
            'HTTP_PAYPAL_TRANSMISSION_ID'   => $transmissionId,
            'HTTP_PAYPAL_TRANSMISSION_TIME' => $timestamp,
            'HTTP_PAYPAL_TRANSMISSION_SIG'  => $signature,
            'HTTP_PAYPAL_CERT_URL'          => 'https://api.sandbox.paypal.com/v1/notifications/certs/CERT-123',
            'HTTP_PAYPAL_AUTH_ALGO'         => 'SHA256withRSA',
            'CONTENT_TYPE'                  => 'application/json',
        ];

        // 1. Invalid signature must return 400
        $badHeaders = $headers;
        $badHeaders['HTTP_PAYPAL_TRANSMISSION_SIG'] = base64_encode('invalidsig');
        $this->call('POST', '/webhooks/paypal', [], [], [], $badHeaders, $rawPayload)
            ->assertStatus(400);

        // 2. Valid signature succeeds
        $response = $this->call('POST', '/webhooks/paypal', [], [], [], $headers, $rawPayload);
        $response->assertStatus(200);

        $order->refresh();
        $this->assertEquals(Order::STATUS_PAID, $order->status);

        // 3. Replay must be ignored
        $replay = $this->call('POST', '/webhooks/paypal', [], [], [], $headers, $rawPayload);
        $replay->assertStatus(200)
            ->assertJson(['status' => 'duplicate_ignored']);
    }

    /**
     |--------------------------------------------------------------------------
     | 8. Digital Download Authorization & Non-Consumption on Pending Master
     |--------------------------------------------------------------------------
     */

    public function test_digital_download_enforces_active_entitlement_and_does_not_consume_count_on_pending_file(): void
    {
        $order = Order::create([
            'reference'      => 'PB-DOWN-001',
            'user_id'        => $this->customer->id,
            'status'         => Order::STATUS_PAID,
            'currency'       => 'USD',
            'subtotal_cents' => 199,
            'total_cents'    => 199,
            'customer_email' => $this->customer->email,
            'customer_name'  => $this->customer->name,
        ]);

        $entitlement = DownloadEntitlement::create([
            'user_id'        => $this->customer->id,
            'order_id'       => $order->id,
            'song_id'        => $this->song->id,
            'format'         => DownloadEntitlement::FORMAT_MP3,
            'is_active'      => true,
            'download_count' => 0,
        ]);

        // 1. Audio master not yet in private storage -> returns pending_masters
        $response = $this->actingAs($this->customer)->get("/downloads/{$entitlement->id}");
        $response->assertStatus(200)
            ->assertJson([
                'status'     => 'pending_masters',
                'song_title' => 'Purify Me',
            ]);

        // CRUCIAL REQUIREMENT: Download count must NOT be consumed when master file is unavailable!
        $entitlement->refresh();
        $this->assertEquals(0, $entitlement->download_count, 'Download count must not be incremented when file is unavailable.');

        // 2. Customer Ada cannot access Grace's download (403 Forbidden)
        $this->actingAs($this->otherCustomer)
            ->get("/downloads/{$entitlement->id}")
            ->assertStatus(403);

        // 3. Revoked entitlement cannot be downloaded (403 Forbidden)
        $entitlement->update(['is_active' => false]);
        $this->actingAs($this->customer)
            ->get("/downloads/{$entitlement->id}")
            ->assertStatus(403);

        // 4. When file actually exists in private storage, download delivers file and increments count
        $entitlement->update(['is_active' => true]);
        $dir = storage_path('app/private/audio');
        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }
        $testFile = "{$dir}/purify-me.mp3";
        File::put($testFile, 'MOCK_AUDIO_PAYLOAD');

        try {
            $downloadResponse = $this->actingAs($this->customer)->get("/downloads/{$entitlement->id}");
            $downloadResponse->assertStatus(200);
            $downloadResponse->assertHeader('Content-Disposition', 'attachment; filename=purify-me-prince-byron.mp3');

            // Now count is incremented!
            $entitlement->refresh();
            $this->assertEquals(1, $entitlement->download_count, 'Download count must be incremented upon successful file delivery.');
        } finally {
            File::delete($testFile);
        }
    }

    /**
     |--------------------------------------------------------------------------
     | 9. Booking Inquiry & Local Mailpit Acknowledgement
     |--------------------------------------------------------------------------
     */

    public function test_booking_submission_creates_record_and_dispatches_mail(): void
    {
        Mail::fake();

        $bookingData = [
            'contact_name'      => 'Pastor David Miller',
            'contact_email'     => 'pastor.david@gracechurch.local',
            'contact_phone'     => '+1-555-0199',
            'organisation'      => 'Grace Community Church',
            'event_type'        => 'Worship Night',
            'event_name'        => 'Annual Praise & Ministry Conference',
            'event_date'        => now()->addMonths(3)->format('Y-m-d'),
            'event_city'        => 'Dallas',
            'event_state'       => 'TX',
            'event_country'     => 'US',
            'expected_audience' => 450,
            'event_description' => 'Evening of congregational praise and worship ministry.',
            'requirements'      => 'Standard vocal mic and acoustic DI.',
        ];

        $response = $this->postJson('/booking', $bookingData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        $ref = $response->json('reference');
        $this->assertNotNull($ref);

        $this->assertDatabaseHas('booking_requests', [
            'reference'     => $ref,
            'contact_email' => 'pastor.david@gracechurch.local',
            'status'        => BookingRequest::STATUS_NEW,
        ]);

        Mail::assertSent(BookingAcknowledgementMail::class, function ($mail) {
            return $mail->hasTo('pastor.david@gracechurch.local');
        });

        Mail::assertSent(BookingNotificationMail::class);
    }
}
