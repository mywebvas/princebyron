<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\ShippingRate;
use App\Models\Song;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CheckoutService
{
    public function __construct(
        protected CartService $cartService
    ) {}

    /**
     * Authoritatively recalculate subtotal, shipping, tax, and total.
     */
    public function calculate(?string $countryCode = null): array
    {
        $summary = $this->cartService->getSummary();

        if (empty($summary['items'])) {
            return [
                'success'        => false,
                'error_code'     => 'cart_empty',
                'message'        => 'Your bag is empty.',
                'subtotal_cents' => 0,
                'shipping_cents' => 0,
                'tax_cents'      => 0,
                'total_cents'    => 0,
                'has_physical'   => false,
                'currency'       => 'USD',
            ];
        }

        $subtotalCents = $summary['subtotal_cents'];
        $shippingCents = 0;

        if ($summary['has_physical']) {
            // Check if owner has configured and activated live shipping rates
            if (! ShippingRate::hasActiveRates()) {
                return [
                    'success'               => false,
                    'error_code'            => 'shipping_unconfigured',
                    'message'               => 'Shipping destinations and rates are currently being configured by the owner. Physical order fulfillment is temporarily unavailable.',
                    'subtotal_cents'        => $subtotalCents,
                    'shipping_cents'        => null,
                    'tax_cents'             => 0,
                    'total_cents'           => null,
                    'has_physical'          => true,
                    'currency'              => 'USD',
                ];
            }

            // Find matching rate for destination
            $rate = ShippingRate::findActiveForCountry($countryCode);
            if (! $rate) {
                return [
                    'success'               => false,
                    'error_code'            => 'destination_unsupported',
                    'message'               => 'Shipping is currently unavailable to the selected destination country.',
                    'subtotal_cents'        => $subtotalCents,
                    'shipping_cents'        => null,
                    'tax_cents'             => 0,
                    'total_cents'           => null,
                    'has_physical'          => true,
                    'currency'              => 'USD',
                ];
            }

            $shippingCents = $rate->calculateShipping($subtotalCents);
        }

        $taxCents = 0; // Tax calculation pending owner tax advisor review
        $totalCents = $subtotalCents + $shippingCents + $taxCents;

        return [
            'success'        => true,
            'subtotal_cents' => $subtotalCents,
            'shipping_cents' => $shippingCents,
            'tax_cents'      => $taxCents,
            'total_cents'    => $totalCents,
            'has_physical'   => $summary['has_physical'],
            'currency'       => 'USD',
            'items'          => $summary['items'],
        ];
    }

    /**
     * Create an authoritative Order and line items.
     */
    public function createOrder(
        ?User $user,
        array $customerData,
        array $shippingData,
        string $paymentMethod = 'stripe',
        ?string $countryCode = null
    ): Order {
        $calculation = $this->calculate($countryCode);

        if (! ($calculation['success'] ?? false)) {
            throw new InvalidArgumentException($calculation['message'] ?? 'Checkout calculation failed.');
        }

        return DB::transaction(function () use ($user, $customerData, $shippingData, $paymentMethod, $calculation) {
            // 1. Recheck and reserve inventory atomically with pessimistic row locking to prevent overselling
            foreach ($calculation['items'] as $item) {
                if ($item['type'] === 'variant') {
                    $variant = ProductVariant::where('id', $item['id'])->lockForUpdate()->first();
                    if (! $variant || ! $variant->is_active) {
                        throw new InvalidArgumentException("Item '{$item['title']}' is no longer available.");
                    }

                    if ($variant->stock_quantity >= 0) {
                        if ($variant->stock_quantity < $item['quantity']) {
                            throw new InvalidArgumentException("Insufficient stock for '{$variant->label}'. Remaining stock: {$variant->stock_quantity}.");
                        }

                        // Atomic conditional decrement to guarantee zero overselling under race conditions
                        $affected = ProductVariant::where('id', $variant->id)
                            ->where('stock_quantity', '>=', $item['quantity'])
                            ->decrement('stock_quantity', $item['quantity']);

                        if (! $affected) {
                            throw new InvalidArgumentException("Inventory for '{$variant->label}' changed concurrently. Please try again.");
                        }
                    }
                }
            }

            // 2. Create authoritative order record
            $order = Order::create([
                'reference'              => 'PB-' . date('Ymd') . '-' . strtoupper(Str::random(6)),
                'user_id'                => $user?->id,
                'status'                 => $paymentMethod === 'bank_transfer'
                    ? Order::STATUS_AWAITING_BANK
                    : Order::STATUS_PENDING_PAYMENT,
                'currency'               => 'USD',
                'subtotal_cents'         => $calculation['subtotal_cents'],
                'shipping_cents'         => $calculation['shipping_cents'],
                'tax_cents'              => $calculation['tax_cents'],
                'total_cents'            => $calculation['total_cents'],
                'customer_email'         => $customerData['email'],
                'customer_name'          => $customerData['name'],
                'shipping_name'          => $shippingData['name'] ?? $customerData['name'],
                'shipping_address_line1' => $shippingData['address_line1'] ?? null,
                'shipping_address_line2' => $shippingData['address_line2'] ?? null,
                'shipping_city'          => $shippingData['city'] ?? null,
                'shipping_state'         => $shippingData['state'] ?? null,
                'shipping_postal_code'   => $shippingData['postal_code'] ?? null,
                'shipping_country'       => $shippingData['country'] ?? null,
            ]);

            // 3. Create immutable line item snapshots
            foreach ($calculation['items'] as $item) {
                OrderItem::create([
                    'order_id'         => $order->id,
                    'itemable_type'    => $item['type'] === 'variant' ? ProductVariant::class : Song::class,
                    'itemable_id'      => $item['id'],
                    'label'            => $item['title'] . (! empty($item['variant_label']) ? " ({$item['variant_label']})" : ''),
                    'quantity'         => $item['quantity'],
                    'unit_price_cents' => $item['unit_price_cents'],
                    'line_total_cents' => $item['line_total_cents'],
                ]);
            }

            // Manual bank transfer setup
            if ($paymentMethod === 'bank_transfer') {
                Payment::create([
                    'order_id'           => $order->id,
                    'provider'           => Payment::PROVIDER_BANK,
                    'provider_reference' => 'PENDING_BANK_' . $order->reference,
                    'status'             => Payment::STATUS_AWAITING_VERIFICATION,
                    'currency'           => 'USD',
                    'amount_cents'       => $order->total_cents,
                    'signature_verified' => false,
                    'provider_metadata'  => [
                        'instructions_note' => 'Awaiting manual owner bank reconciliation.',
                    ],
                ]);

                AuditEvent::create([
                    'actor_id'       => $user?->id,
                    'action'         => 'order.created_awaiting_bank',
                    'auditable_type' => Order::class,
                    'auditable_id'   => $order->id,
                    'new_values'     => [
                        'status'      => Order::STATUS_AWAITING_BANK,
                        'total_cents' => $order->total_cents,
                    ],
                    'ip_address'     => request()->ip(),
                    'user_agent'     => request()->userAgent(),
                ]);
            }

            // Clear the cart on successful checkout
            $this->cartService->clear();

            return $order;
        });
    }
}
