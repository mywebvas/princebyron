<?php

namespace Tests\Feature;

use App\Models\DownloadEntitlement;
use App\Models\Order;
use App\Models\Song;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_admin_login_when_accessing_admin(): void
    {
        $response = $this->get('/admin');
        $response->assertRedirect('/admin/login');
    }

    public function test_customer_cannot_access_filament_admin_panel(): void
    {
        $customer = User::create([
            'name'      => 'Regular Fan',
            'email'     => 'fan@example.com',
            'password'  => 'password',
            'role'      => User::ROLE_CUSTOMER,
            'is_active' => true,
        ]);

        $panel = Filament::getPanel('admin');
        $this->assertFalse($customer->canAccessPanel($panel));

        $response = $this->actingAs($customer)->get('/admin');
        $response->assertForbidden();
    }

    public function test_active_owner_can_access_filament_admin_panel(): void
    {
        $owner = User::create([
            'name'      => 'Prince Byron Owner',
            'email'     => 'owner@example.com',
            'password'  => 'password',
            'role'      => User::ROLE_OWNER,
            'is_active' => true,
        ]);

        $panel = Filament::getPanel('admin');
        $this->assertTrue($owner->canAccessPanel($panel));

        $response = $this->actingAs($owner)->get('/admin');
        $response->assertSuccessful();
    }

    public function test_inactive_owner_cannot_access_filament_admin_panel(): void
    {
        $inactiveOwner = User::create([
            'name'      => 'Inactive Owner',
            'email'     => 'inactive@example.com',
            'password'  => 'password',
            'role'      => User::ROLE_OWNER,
            'is_active' => false,
        ]);

        $panel = Filament::getPanel('admin');
        $this->assertFalse($inactiveOwner->canAccessPanel($panel));

        $response = $this->actingAs($inactiveOwner)->get('/admin');
        $response->assertForbidden();
    }

    public function test_customer_can_view_own_order_but_not_another_customers_order(): void
    {
        $customerA = User::create([
            'name' => 'Customer A', 'email' => 'a@example.com', 'password' => 'secret', 'role' => User::ROLE_CUSTOMER,
        ]);
        $customerB = User::create([
            'name' => 'Customer B', 'email' => 'b@example.com', 'password' => 'secret', 'role' => User::ROLE_CUSTOMER,
        ]);

        $orderA = Order::create([
            'reference'             => 'PB-ORD-A1',
            'user_id'               => $customerA->id,
            'status'                => Order::STATUS_PAID,
            'currency'              => 'USD',
            'subtotal_cents'        => 3200,
            'shipping_cents'        => 500,
            'tax_cents'             => 0,
            'total_cents'           => 3700,
            'customer_email'        => 'a@example.com',
            'customer_name'         => 'Customer A',
        ]);

        // Customer A can view own order
        $this->assertTrue(Gate::forUser($customerA)->allows('view', $orderA));

        // Customer B cannot view Customer A's order
        $this->assertFalse(Gate::forUser($customerB)->allows('view', $orderA));

        // Customer cannot transition order status
        $this->assertFalse(Gate::forUser($customerA)->allows('transition', $orderA));
    }

    public function test_owner_can_view_and_transition_any_order(): void
    {
        $owner = User::create([
            'name' => 'Owner', 'email' => 'owner2@example.com', 'password' => 'secret', 'role' => User::ROLE_OWNER,
        ]);
        $customer = User::create([
            'name' => 'Customer', 'email' => 'cust@example.com', 'password' => 'secret', 'role' => User::ROLE_CUSTOMER,
        ]);

        $order = Order::create([
            'reference'      => 'PB-ORD-001',
            'user_id'        => $customer->id,
            'status'         => Order::STATUS_AWAITING_BANK,
            'currency'       => 'USD',
            'subtotal_cents' => 5800,
            'shipping_cents' => 0,
            'tax_cents'      => 0,
            'total_cents'    => 5800,
            'customer_email' => 'cust@example.com',
            'customer_name'  => 'Customer',
        ]);

        $this->assertTrue(Gate::forUser($owner)->allows('view', $order));
        $this->assertTrue(Gate::forUser($owner)->allows('transition', $order));
    }

    public function test_download_entitlement_enforces_ownership_active_state_and_expiry(): void
    {
        $userA = User::create(['name' => 'A', 'email' => 'ua@example.com', 'password' => 'secret', 'role' => User::ROLE_CUSTOMER]);
        $userB = User::create(['name' => 'B', 'email' => 'ub@example.com', 'password' => 'secret', 'role' => User::ROLE_CUSTOMER]);

        $song = Song::create([
            'title'        => 'Purify Me',
            'slug'         => 'purify-me',
            'price_cents'  => 199,
            'is_published' => true,
        ]);

        $order = Order::create([
            'reference'      => 'PB-ORD-ENT1',
            'user_id'        => $userA->id,
            'status'         => Order::STATUS_PAID,
            'currency'       => 'USD',
            'subtotal_cents' => 199,
            'total_cents'    => 199,
            'customer_email' => 'ua@example.com',
            'customer_name'  => 'User A',
        ]);

        // Active, unexpired entitlement
        $validEntitlement = DownloadEntitlement::create([
            'user_id'    => $userA->id,
            'order_id'   => $order->id,
            'song_id'    => $song->id,
            'format'     => DownloadEntitlement::FORMAT_MP3,
            'is_active'  => true,
            'expires_at' => now()->addDays(7),
        ]);

        // User A can download
        $this->assertTrue(Gate::forUser($userA)->allows('download', $validEntitlement));

        // User B cannot download User A's entitlement
        $this->assertFalse(Gate::forUser($userB)->allows('download', $validEntitlement));

        // Revoked / refunded entitlement (is_active = false) cannot be downloaded
        $validEntitlement->update(['is_active' => false]);
        $this->assertFalse(Gate::forUser($userA)->allows('download', $validEntitlement));

        // Expired entitlement cannot be downloaded
        $validEntitlement->update([
            'is_active'  => true,
            'expires_at' => now()->subMinute(),
        ]);
        $this->assertFalse(Gate::forUser($userA)->allows('download', $validEntitlement));
    }

    public function test_auth_json_response_exposes_only_safe_user_fields(): void
    {
        $user = User::create([
            'name'              => 'Victim',
            'email'             => 'victim@example.com',
            'password'          => 'secret',
            'role'              => User::ROLE_CUSTOMER,
            'is_active'         => true,
            'email_verified_at' => now(),
            'last_login_at'     => now(),
        ]);

        $response = $this->actingAs($user)->getJson('/account');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'user' => ['id', 'name', 'email'],
        ]);

        // Internal state must never appear in the JSON payload.
        $response->assertJsonMissing([
            'role',
            'is_active',
            'email_verified_at',
            'last_login_at',
            'password',
            'remember_token',
        ]);
    }

    public function test_download_controller_rejects_invalid_format(): void
    {
        $user = User::create([
            'name'     => 'Victim',
            'email'    => 'victim@example.com',
            'password' => 'secret',
            'role'     => User::ROLE_CUSTOMER,
        ]);

        $song = Song::create([
            'title'        => 'Purify Me',
            'slug'         => 'purify-me',
            'price_cents'  => 199,
            'is_published' => true,
        ]);

        $order = Order::create([
            'reference'      => 'PB-ORD-FMT',
            'user_id'        => $user->id,
            'status'         => Order::STATUS_PAID,
            'currency'       => 'USD',
            'subtotal_cents' => 199,
            'total_cents'    => 199,
            'customer_email' => 'victim@example.com',
            'customer_name'  => 'Victim',
        ]);

        // An entitlement with an invalid format must be refused by the controller,
        // not interpolated into a storage path.
        $entitlement = DownloadEntitlement::create([
            'user_id'    => $user->id,
            'order_id'   => $order->id,
            'song_id'    => $song->id,
            'format'     => '../../../etc/passwd',
            'is_active'  => true,
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->actingAs($user)->get("/downloads/{$entitlement->id}");

        $response->assertStatus(403);
    }
}
