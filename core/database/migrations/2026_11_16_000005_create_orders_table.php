<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Orders, order items, and payments.
|
| Security/architecture requirements:
| - Money values are integer cents + locked USD currency.
| - Order states have guarded transitions; see App\Models\Order for logic.
| - Stripe/PayPal: verify signature, amount, currency, idempotency server-side.
| - Manual bank transfer: order stays pending_bank_verification until owner
|   independently records a confirmed receipt (never trust customer proof).
| - Never store card data here. Store only provider references.
|
| Order states:
|   pending_payment → awaiting_bank_verification | paid | cancelled | failed
|   paid → fulfillment_pending → fulfilled | refunded
|   awaiting_bank_verification → paid (owner action) | cancelled
|--------------------------------------------------------------------------
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32)->unique()
                ->comment('Human-readable order ref, e.g. PB-20261116-0001');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()
                ->comment('Customer account; null for guest checkout (future)');

            // State machine — transitions enforced in App\Models\Order
            $table->string('status', 50)->default('pending_payment')
                ->comment('pending_payment|awaiting_bank_verification|paid|fulfillment_pending|fulfilled|cancelled|refunded|failed');

            // Money — all amounts in USD cents, never from browser
            $table->string('currency', 3)->default('USD');
            $table->unsignedInteger('subtotal_cents')
                ->comment('Sum of line items before shipping/tax, recalculated server-side');
            $table->unsignedInteger('shipping_cents')->default(0);
            $table->unsignedInteger('tax_cents')->default(0)
                ->comment('Tax calculation pending legal/advisor review; default 0 for now');
            $table->unsignedInteger('total_cents')
                ->comment('subtotal + shipping + tax — authoritative server value');

            // Shipping/delivery info
            $table->string('shipping_name')->nullable();
            $table->string('shipping_address_line1')->nullable();
            $table->string('shipping_address_line2')->nullable();
            $table->string('shipping_city')->nullable();
            $table->string('shipping_state', 10)->nullable();
            $table->string('shipping_postal_code', 20)->nullable();
            $table->string('shipping_country', 2)->nullable()
                ->comment('ISO 3166-1 alpha-2');

            // Contact
            $table->string('customer_email');
            $table->string('customer_name');

            $table->text('notes')->nullable()
                ->comment('Internal owner notes only; never shown to customer');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            // Itemable: product_variant or song (polymorphic)
            $table->string('itemable_type', 100);
            $table->unsignedBigInteger('itemable_id');
            $table->string('label')
                ->comment('Snapshot of product/variant name at time of purchase');
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->unsignedInteger('unit_price_cents')
                ->comment('Price snapshot at checkout — server-authoritative');
            $table->unsignedInteger('line_total_cents')
                ->comment('quantity × unit_price_cents — computed server-side');

            $table->index(['itemable_type', 'itemable_id']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 30)
                ->comment('stripe | paypal | bank_transfer');
            $table->string('provider_reference')->nullable()
                ->comment('Stripe payment_intent_id, PayPal order_id, or bank ref. NOT card data.');
            $table->string('provider_event_id')->nullable()->unique()
                ->comment('Idempotency key: Stripe event ID or PayPal event ID. Prevents replay.');
            $table->string('status', 30)
                ->comment('pending | succeeded | failed | refunded | awaiting_verification');
            $table->string('currency', 3)->default('USD');
            $table->unsignedInteger('amount_cents')
                ->comment('Amount verified server-side from provider callback — never from browser');
            $table->boolean('signature_verified')->default(false)
                ->comment('true only after HMAC/RSA signature verified against provider secret');
            $table->jsonb('provider_metadata')->nullable()
                ->comment('Non-sensitive event metadata for debugging. Redact card/PII before storing.');
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index(['provider', 'provider_reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
