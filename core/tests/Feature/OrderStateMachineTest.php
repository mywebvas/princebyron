<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class OrderStateMachineTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_order_transitions_succeed(): void
    {
        $order = new Order([
            'reference'      => 'PB-TEST-SM1',
            'status'         => Order::STATUS_PENDING_PAYMENT,
            'currency'       => 'USD',
            'subtotal_cents' => 3200,
            'shipping_cents' => 0,
            'tax_cents'      => 0,
            'total_cents'    => 3200,
            'customer_email' => 'fan@test.local',
            'customer_name'  => 'Fan',
        ]);

        // pending_payment -> paid
        $order->transitionTo(Order::STATUS_PAID);
        $this->assertEquals(Order::STATUS_PAID, $order->status);

        // paid -> fulfillment_pending
        $order->transitionTo(Order::STATUS_FULFILLMENT_PENDING);
        $this->assertEquals(Order::STATUS_FULFILLMENT_PENDING, $order->status);

        // fulfillment_pending -> fulfilled
        $order->transitionTo(Order::STATUS_FULFILLED);
        $this->assertEquals(Order::STATUS_FULFILLED, $order->status);

        // fulfilled -> refunded
        $order->transitionTo(Order::STATUS_REFUNDED);
        $this->assertEquals(Order::STATUS_REFUNDED, $order->status);
    }

    public function test_bank_transfer_order_transition_flow(): void
    {
        $order = new Order(['status' => Order::STATUS_PENDING_PAYMENT]);

        // pending_payment -> awaiting_bank_verification
        $order->transitionTo(Order::STATUS_AWAITING_BANK);
        $this->assertEquals(Order::STATUS_AWAITING_BANK, $order->status);

        // Owner independently confirms funds -> paid
        $order->transitionTo(Order::STATUS_PAID);
        $this->assertEquals(Order::STATUS_PAID, $order->status);
    }

    public function test_invalid_order_transition_throws_logic_exception(): void
    {
        $order = new Order(['status' => Order::STATUS_PENDING_PAYMENT]);

        $this->expectException(LogicException::class);
        // Cannot jump directly from pending_payment to fulfilled
        $order->transitionTo(Order::STATUS_FULFILLED);
    }

    public function test_terminal_order_state_cannot_be_transitioned(): void
    {
        $order = new Order(['status' => Order::STATUS_CANCELLED]);

        $this->expectException(LogicException::class);
        $order->transitionTo(Order::STATUS_PAID);
    }

    public function test_server_authoritative_total_recalculation(): void
    {
        $order = new Order([
            'subtotal_cents' => 5800,
            'shipping_cents' => 500,
            'tax_cents'      => 0,
            'total_cents'    => 100, // untrusted browser value
        ]);

        $order->recalculateTotal();

        // Server recalculates 5800 + 500 + 0 = 6300 cents ($63.00 USD)
        $this->assertEquals(6300, $order->total_cents);
    }
}
