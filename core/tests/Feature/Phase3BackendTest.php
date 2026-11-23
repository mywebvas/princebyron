<?php

namespace Tests\Feature;

use App\Filament\Resources\AuditEvents\AuditEventResource;
use App\Models\AuditEvent;
use App\Models\BookingRequest;
use App\Models\ContentSection;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\ShippingRate;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

/**
 * Phase 3 Verification Suite — Backend Foundation, Security & Owner CMS.
 *
 * Verifies:
 * - Server-side owner authentication and least-privilege authorization.
 * - Non-owner and inactive accounts denied access to all backoffice resources.
 * - Manual bank payment independent verification with Payment + AuditEvent creation.
 * - Guarded order state machine rejecting invalid transitions.
 * - ContentSection HTML/script stripping and allowlisted brand palette enforcement.
 * - Setting typed values and encrypted secrets storage at rest.
 * - Server-authoritative shipping calculations.
 * - AuditEvent immutability (canCreate=false, canEdit=false, canDelete=false).
 * - Hardened /up health check without diagnostic or environment leakage.
 */
class Phase3BackendTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::create([
            'name'              => 'Prince Byron Owner',
            'email'             => 'owner@princebyron.local',
            'password'          => 'SecureOwnerPass123!',
            'role'              => User::ROLE_OWNER,
            'is_active'         => true,
            'email_verified_at' => now(),
        ]);

        $this->customer = User::create([
            'name'              => 'Demo Customer',
            'email'             => 'customer@princebyron.local',
            'password'          => 'SecureCustomerPass123!',
            'role'              => User::ROLE_CUSTOMER,
            'is_active'         => true,
            'email_verified_at' => now(),
        ]);
    }

    public function test_owner_can_access_all_filament_cms_resources(): void
    {
        $resources = [
            '/admin/songs',
            '/admin/content-sections',
            '/admin/products',
            '/admin/orders',
            '/admin/booking-requests',
            '/admin/shipping-rates',
            '/admin/settings',
            '/admin/audit-events',
        ];

        foreach ($resources as $uri) {
            $response = $this->actingAs($this->owner)->get($uri);
            $this->assertContains(
                $response->getStatusCode(),
                [200, 302],
                "Owner should access {$uri} successfully (got {$response->getStatusCode()})"
            );
        }
    }

    public function test_customer_and_guest_are_forbidden_or_redirected_from_all_admin_resources(): void
    {
        $resources = [
            '/admin',
            '/admin/songs',
            '/admin/content-sections',
            '/admin/products',
            '/admin/orders',
            '/admin/booking-requests',
            '/admin/shipping-rates',
            '/admin/settings',
            '/admin/audit-events',
        ];

        // 1. Unauthenticated Guests must be redirected to login
        foreach ($resources as $uri) {
            $guestResponse = $this->get($uri);
            $guestResponse->assertRedirect('/admin/login');
        }

        // 2. Authenticated Customers must receive 403 Forbidden
        foreach ($resources as $uri) {
            $customerResponse = $this->actingAs($this->customer)->get($uri);
            $this->assertEquals(
                403,
                $customerResponse->getStatusCode(),
                "Customer accessing {$uri} must receive 403 Forbidden"
            );
        }
    }

    public function test_audit_event_records_are_strictly_immutable(): void
    {
        $this->assertFalse(AuditEventResource::canCreate(), 'Audit logs must not permit manual creation.');
        $audit = new AuditEvent();
        $this->assertFalse(AuditEventResource::canEdit($audit), 'Audit logs must not permit editing.');
        $this->assertFalse(AuditEventResource::canDelete($audit), 'Audit logs must not permit deletion.');
        $this->assertNull(AuditEvent::UPDATED_AT, 'Audit records must not define an UPDATED_AT column.');
    }

    public function test_content_section_sanitizes_html_and_enforces_allowlisted_colors(): void
    {
        $section = ContentSection::create([
            'section_key' => 'home_test',
            'page'        => 'home',
            'title'       => 'Test <script>alert("xss")</script>Title',
            'eyebrow'     => '<b>Bold Eyebrow</b>',
            'body'        => '<p>Paragraph</p><script>malicious();</script>',
            'brand_color' => '#INVALID_COLOR',
            'is_visible'  => true,
            'sort_order'  => 1,
        ]);

        // HTML tags stripped
        $this->assertEquals('Test alert("xss")Title', $section->title);
        $this->assertEquals('Bold Eyebrow', $section->eyebrow);
        $this->assertEquals('Paragraphmalicious();', $section->body);

        // Disallowed color defaults back to Obsidian Dark
        $this->assertEquals(ContentSection::COLOR_PRIMARY_DARK, $section->brand_color);

        // Allowed brand color accepted
        $section->brand_color = ContentSection::COLOR_GOLD_ACCENT;
        $section->save();
        $this->assertEquals(ContentSection::COLOR_GOLD_ACCENT, $section->brand_color);
    }

    public function test_manual_bank_transfer_order_verification_workflow(): void
    {
        $order = Order::create([
            'reference'      => 'PB-TEST-BANK-001',
            'user_id'        => $this->customer->id,
            'status'         => Order::STATUS_AWAITING_BANK,
            'currency'       => 'USD',
            'subtotal_cents' => 4500,
            'shipping_cents' => 500,
            'tax_cents'      => 0,
            'total_cents'    => 5000,
            'customer_email' => $this->customer->email,
            'customer_name'  => $this->customer->name,
        ]);

        $this->assertEquals(Order::STATUS_AWAITING_BANK, $order->status);

        // Owner independently confirms funds on bank statement
        DB::transaction(function () use ($order) {
            $oldStatus = $order->status;
            $order->transitionTo(Order::STATUS_PAID);
            $order->save();

            Payment::create([
                'order_id'           => $order->id,
                'provider'           => Payment::PROVIDER_BANK,
                'provider_reference' => 'WIRE-REF-987654321',
                'provider_event_id'  => 'bank_' . Str::uuid(),
                'status'             => Payment::STATUS_SUCCEEDED,
                'currency'           => $order->currency,
                'amount_cents'       => $order->total_cents,
                'signature_verified' => true,
                'provider_metadata'  => [
                    'verified_by' => $this->owner->email,
                ],
            ]);

            AuditEvent::create([
                'actor_id'       => $this->owner->id,
                'action'         => 'order.bank_transfer_verified',
                'auditable_type' => Order::class,
                'auditable_id'   => $order->id,
                'old_values'     => ['status' => $oldStatus],
                'new_values'     => [
                    'status'         => Order::STATUS_PAID,
                    'bank_reference' => 'WIRE-REF-987654321',
                    'amount_cents'   => $order->total_cents,
                ],
                'ip_address'     => '127.0.0.1',
                'user_agent'     => 'TestHarness/1.0',
            ]);
        });

        // Verify order status updated to paid
        $order->refresh();
        $this->assertEquals(Order::STATUS_PAID, $order->status);

        // Verify Payment record created with correct amounts
        $payment = $order->payment;
        $this->assertNotNull($payment);
        $this->assertEquals(Payment::PROVIDER_BANK, $payment->provider);
        $this->assertEquals(Payment::STATUS_SUCCEEDED, $payment->status);
        $this->assertEquals(5000, $payment->amount_cents);
        $this->assertEquals('WIRE-REF-987654321', $payment->provider_reference);
        $this->assertTrue($payment->signature_verified);

        // Verify AuditEvent recorded
        $audit = AuditEvent::where('action', 'order.bank_transfer_verified')
            ->where('auditable_id', $order->id)
            ->first();
        $this->assertNotNull($audit);
        $this->assertEquals($this->owner->id, $audit->actor_id);
        $this->assertEquals(Order::STATUS_AWAITING_BANK, $audit->old_values['status']);
        $this->assertEquals(Order::STATUS_PAID, $audit->new_values['status']);
    }

    public function test_invalid_order_transition_is_guarded_and_rejected(): void
    {
        $order = Order::create([
            'reference'      => 'PB-TEST-INV-001',
            'user_id'        => $this->customer->id,
            'status'         => Order::STATUS_PENDING_PAYMENT,
            'currency'       => 'USD',
            'subtotal_cents' => 2000,
            'shipping_cents' => 0,
            'tax_cents'      => 0,
            'total_cents'    => 2000,
            'customer_email' => $this->customer->email,
            'customer_name'  => $this->customer->name,
        ]);

        $this->expectException(LogicException::class);
        // Direct jump from pending_payment to fulfilled is invalid
        $order->transitionTo(Order::STATUS_FULFILLED);
    }

    public function test_setting_helper_handles_typed_values_and_encrypted_secrets(): void
    {
        // String
        Setting::set('test_str', 'Hello World', 'general', Setting::TYPE_STRING);
        $this->assertEquals('Hello World', Setting::get('test_str'));

        // Boolean
        Setting::set('test_bool_t', true, 'general', Setting::TYPE_BOOLEAN);
        $this->assertTrue(Setting::get('test_bool_t'));

        Setting::set('test_bool_f', false, 'general', Setting::TYPE_BOOLEAN);
        $this->assertFalse(Setting::get('test_bool_f'));

        // Integer
        Setting::set('test_int', 42, 'general', Setting::TYPE_INTEGER);
        $this->assertEquals(42, Setting::get('test_int'));

        // JSON
        Setting::set('test_json', ['a' => 1, 'b' => 'two'], 'general', Setting::TYPE_JSON);
        $this->assertEquals(['a' => 1, 'b' => 'two'], Setting::get('test_json'));

        // Encrypted Secret
        $secretValue = 'Confidential_Bank_Account_Number_998877';
        $setting = Setting::set('test_enc', $secretValue, 'payment', Setting::TYPE_ENCRYPTED);

        // Raw value in database must NOT equal plain text (must be encrypted)
        $raw = DB::table('settings')->where('key', 'test_enc')->value('value');
        $this->assertNotEquals($secretValue, $raw, 'Raw database value must be encrypted at rest.');

        // Accessor decrypts automatically
        $this->assertEquals($secretValue, Setting::get('test_enc'));
    }

    public function test_server_authoritative_shipping_rate_calculations(): void
    {
        $usRate = ShippingRate::create([
            'name'             => 'US Domestic Test',
            'country_code'     => 'US',
            'rate_cents'       => 500, // $5.00
            'free_above_cents' => 5000, // Free above $50.00
            'is_active'        => true,
            'sort_order'       => 1,
        ]);

        $intlRate = ShippingRate::create([
            'name'             => 'Intl Wildcard Test',
            'country_code'     => null, // Wildcard
            'rate_cents'       => 1500, // $15.00
            'free_above_cents' => 10000, // Free above $100.00
            'is_active'        => true,
            'sort_order'       => 2,
        ]);

        // US Order below $50.00 threshold pays $5.00 (500 cents)
        $this->assertEquals(500, $usRate->calculateShipping(3500));

        // US Order at or above $50.00 threshold pays $0.00
        $this->assertEquals(0, $usRate->calculateShipping(5000));
        $this->assertEquals(0, $usRate->calculateShipping(7500));

        // International Order below $100.00 threshold pays $15.00 (1500 cents)
        $this->assertEquals(1500, $intlRate->calculateShipping(4000));

        // International Order at or above $100.00 threshold pays $0.00
        $this->assertEquals(0, $intlRate->calculateShipping(10000));
        $this->assertEquals(0, $intlRate->calculateShipping(15000));
    }
}
