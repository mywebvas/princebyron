<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\DownloadEntitlement;
use App\Models\Order;
use App\Models\Song;
use App\Models\User;
use App\Services\AnonymiseCustomer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Anonymisation — the supported alternative to deleting a customer (P1-5).
 *
 * The guard added in Sprint 2 refuses to delete a customer who has orders, and
 * its error message promises "deactivate or anonymise". This test suite exists
 * to prove that promise is real, and to pin the exact boundary between what is
 * removed and what survives — because getting that boundary wrong is either a
 * privacy failure (too little removed) or a compliance failure (financial
 * record destroyed).
 */
class CustomerAnonymisationTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): User
    {
        return User::factory()->create([
            'name' => 'Grace Adeyemi',
            'email' => 'grace@example.com',
            'phone' => '+234 800 000 0000',
        ]);
    }

    private function paidOrder(User $user): Order
    {
        return Order::create([
            'reference' => 'ORD-ANON-1',
            'user_id' => $user->id,
            'status' => Order::STATUS_PAID,
            'currency' => 'USD',
            'subtotal_cents' => 4000,
            'shipping_cents' => 500,
            'tax_cents' => 0,
            'total_cents' => 4500,
            'customer_name' => 'Grace Adeyemi',
            'customer_email' => 'grace@example.com',
            'shipping_name' => 'Grace Adeyemi',
            'shipping_address_line1' => '12 Marina Road',
            'shipping_city' => 'Lagos',
            'shipping_country' => 'NG',
        ]);
    }

    public function test_personal_data_is_removed_from_the_account(): void
    {
        $user = $this->customer();

        app(AnonymiseCustomer::class)->handle($user);

        $fresh = $user->fresh();

        $this->assertSame('Anonymised customer', $fresh->name);
        $this->assertStringNotContainsString('grace', $fresh->email);
        $this->assertNull($fresh->phone);
        $this->assertFalse((bool) $fresh->is_active, 'The account must not be able to sign in');
    }

    public function test_the_removed_data_no_longer_appears_anywhere_in_the_database(): void
    {
        $user = $this->customer();
        $order = $this->paidOrder($user);

        app(AnonymiseCustomer::class)->handle($user);

        // The real test of anonymisation is not "the name changed on the user
        // row" — it is that the name is gone from every table that held it.
        $fresh = $user->fresh();
        $orderFresh = $order->fresh();

        $this->assertStringNotContainsString('Grace', $orderFresh->customer_name);
        $this->assertStringNotContainsString('Adeyemi', $orderFresh->customer_name);
        $this->assertStringNotContainsString('grace@example.com', $orderFresh->customer_email);
        $this->assertNull($orderFresh->shipping_address_line1, 'The postal address must be removed');
        $this->assertStringNotContainsString('grace@example.com', $fresh->email);
    }

    public function test_the_financial_record_survives_intact(): void
    {
        $user = $this->customer();
        $order = $this->paidOrder($user);

        app(AnonymiseCustomer::class)->handle($user);

        $fresh = $order->fresh();

        // What an auditor needs: what was sold, for how much, when, and the
        // reference the customer would quote.
        $this->assertSame('ORD-ANON-1', $fresh->reference);
        $this->assertSame(4500, $fresh->total_cents);
        $this->assertSame(4000, $fresh->subtotal_cents);
        $this->assertSame('paid', $fresh->status);
        $this->assertSame('NG', $fresh->shipping_country, 'Country is kept for tax aggregation');
        $this->assertNotNull($fresh->created_at);
        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }

    public function test_download_entitlements_are_revoked_not_deleted(): void
    {
        $user = $this->customer();
        $order = $this->paidOrder($user);
        $song = Song::create([
            'title' => 'Anon Track',
            'slug' => 'anon-track',
            'price_cents' => 1000,
            'is_published' => true,
        ]);
        DownloadEntitlement::create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'song_id' => $song->id,
            'format' => DownloadEntitlement::FORMAT_MP3,
            'is_active' => true,
            'download_count' => 0,
        ]);

        $result = app(AnonymiseCustomer::class)->handle($user);

        $this->assertSame(1, $result['entitlements']);
        // Deactivated, not removed: the record that access once existed is part
        // of the financial history.
        $this->assertSame(1, DownloadEntitlement::where('order_id', $order->id)->count());
        $this->assertSame(0, DownloadEntitlement::where('order_id', $order->id)->where('is_active', true)->count());
    }

    public function test_the_operation_is_recorded_without_copying_the_removed_data(): void
    {
        $user = $this->customer();

        $result = app(AnonymiseCustomer::class)->handle(
            $user,
            AnonymiseCustomer::REASON_DELETION_REQUEST,
            'Ticket 4471',
        );

        $entry = AuditEvent::where('action', 'customer.anonymised')
            ->where('auditable_id', $user->id)
            ->first();

        $this->assertNotNull($entry, 'Anonymisation must be auditable');
        $this->assertSame($result['reference'], $entry->new_values['reference']);
        $this->assertSame('Ticket 4471', $entry->new_values['note']);

        // The whole point is that the deleted data is gone. If the audit log
        // kept a copy, the request would not have been honoured at all.
        $serialised = json_encode([$entry->old_values, $entry->new_values]);
        $this->assertStringNotContainsString('Grace', $serialised);
        $this->assertStringNotContainsString('grace@example.com', $serialised);
        $this->assertStringNotContainsString('800 000 0000', $serialised);
    }

    public function test_an_owner_account_cannot_be_anonymised(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->expectException(\DomainException::class);

        app(AnonymiseCustomer::class)->handle($owner);
    }

    public function test_anonymisation_satisfies_the_deletion_request_guard(): void
    {
        // The customer asked to be deleted. Sprint 2 refused because the order is
        // a financial record and pointed here. Afterwards the personal data is
        // gone, so the request is honoured even though the row still exists.
        $user = $this->customer();
        $this->paidOrder($user);

        try {
            $user->delete();
            $this->fail('Deletion should have been refused');
        } catch (\DomainException) {
            // expected
        }

        app(AnonymiseCustomer::class)->handle($user);

        $this->assertDatabaseMissing('users', ['email' => 'grace@example.com']);
        $this->assertDatabaseMissing('orders', ['customer_email' => 'grace@example.com']);
    }

    public function test_the_result_reports_what_was_done(): void
    {
        $user = $this->customer();
        $this->paidOrder($user);

        $result = app(AnonymiseCustomer::class)->handle($user);

        $this->assertSame(1, $result['orders']);
        $this->assertStringStartsWith('ANON-', $result['reference']);
    }
}
