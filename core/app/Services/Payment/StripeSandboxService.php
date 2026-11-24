<?php

namespace App\Services\Payment;

use App\Models\AuditEvent;
use App\Models\Order;
use App\Models\Payment;
use App\Services\GrantEntitlementsForOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StripeSandboxService
{
    /**
     * Compute valid test signature for sandbox / testing environments.
     */
    public static function generateTestSignature(string $payload, string $secret, int $timestamp): string
    {
        $signedPayload = "{$timestamp}.{$payload}";
        $signature = hash_hmac('sha256', $signedPayload, $secret);
        return "t={$timestamp},v1={$signature}";
    }

    /**
     * Verify Stripe webhook signature header using exact raw payload and configured secret (ASVS 5.0 L2).
     */
    public function verifySignature(string $payload, string $header, ?string $secret): bool
    {
        if (empty($secret) || empty($header) || empty($payload)) {
            return false;
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $item) {
            $parts = explode('=', trim($item), 2);
            if (count($parts) === 2) {
                if ($parts[0] === 't') {
                    $timestamp = (int) $parts[1];
                } elseif ($parts[0] === 'v1') {
                    $signatures[] = $parts[1];
                }
            }
        }

        if (! $timestamp || empty($signatures)) {
            return false;
        }

        // Replay tolerance: 300 seconds
        if (abs(time() - $timestamp) > 300) {
            return false;
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

        foreach ($signatures as $sig) {
            if (hash_equals($expected, $sig)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Process Stripe webhook event with strict verification, idempotency, and amount matching.
     */
    public function handleWebhook(array $event, string $rawPayload, string $sigHeader): array
    {
        $secret = config('services.stripe.webhook_secret');

        // Security requirement: Missing configuration must fail closed
        if (empty($secret)) {
            Log::error('Stripe webhook rejected: webhook secret is not configured in environment. Failing closed.');
            return [
                'status'  => 'error',
                'code'    => 500,
                'message' => 'Stripe webhook secret is not configured. Request rejected (fail-closed).',
            ];
        }

        if (! $this->verifySignature($rawPayload, $sigHeader, $secret)) {
            return ['status' => 'error', 'code' => 400, 'message' => 'Invalid webhook signature'];
        }

        $eventId   = $event['id'] ?? null;
        $eventType = $event['type'] ?? null;

        if (! $eventId || ! $eventType) {
            return ['status' => 'error', 'code' => 400, 'message' => 'Missing event id or type'];
        }

        // Idempotency & Replay Protection: Check if event was already processed
        if (Payment::where('provider_event_id', $eventId)->exists()) {
            return ['status' => 'duplicate_ignored', 'code' => 200, 'message' => 'Event already processed'];
        }

        $object = $event['data']['object'] ?? [];
        $orderReference = $object['metadata']['order_reference'] ?? null;
        $amountCents = (int) ($object['amount'] ?? 0);
        $currency = strtoupper($object['currency'] ?? '');

        if (! $orderReference) {
            return ['status' => 'ignored', 'code' => 200, 'message' => 'No order reference in metadata'];
        }

        $order = Order::where('reference', $orderReference)->first();
        if (! $order) {
            return ['status' => 'error', 'code' => 404, 'message' => 'Order not found'];
        }

        return DB::transaction(function () use ($eventType, $order, $amountCents, $currency, $eventId, $object) {
            switch ($eventType) {
                case 'payment_intent.succeeded':
                    if ($currency !== 'USD' || $amountCents !== $order->total_cents) {
                        Log::warning("Stripe payment mismatch for {$order->reference}: expected {$order->total_cents} USD, got {$amountCents} {$currency}");
                        return ['status' => 'error', 'code' => 422, 'message' => 'Payment amount or currency mismatch'];
                    }

                    $oldStatus = $order->status;
                    $order->transitionTo(Order::STATUS_PAID);
                    $order->save();

                    Payment::create([
                        'order_id'           => $order->id,
                        'provider'           => Payment::PROVIDER_STRIPE,
                        'provider_reference' => $object['id'] ?? 'pi_sandbox',
                        'provider_event_id'  => $eventId,
                        'status'             => Payment::STATUS_SUCCEEDED,
                        'currency'           => 'USD',
                        'amount_cents'       => $amountCents,
                        'signature_verified' => true,
                        'provider_metadata'  => [
                            'payment_method' => $object['payment_method_types'][0] ?? 'card',
                        ],
                    ]);

                    AuditEvent::create([
                        'actor_id'       => null,
                        'action'         => 'order.paid',
                        'auditable_type' => Order::class,
                        'auditable_id'   => $order->id,
                        'old_values'     => ['status' => $oldStatus],
                        'new_values'     => ['status' => Order::STATUS_PAID, 'provider' => 'stripe'],
                        'correlation_id' => $eventId,
                        'ip_address'     => request()->ip(),
                        'user_agent'     => 'Stripe-Webhook/1.0',
                    ]);

                    // Grant digital download entitlements for any music line
                    // items. Shared with the bank-transfer verification path so
                    // every route to "paid" grants access identically (P1-4).
                    app(GrantEntitlementsForOrder::class)->grant($order);

                    return ['status' => 'success', 'code' => 200, 'message' => 'Payment succeeded, order marked paid'];

                case 'charge.refunded':
                    $oldStatus = $order->status;
                    if ($order->status !== Order::STATUS_REFUNDED) {
                        $order->transitionTo(Order::STATUS_REFUNDED);
                        $order->save();
                    }

                    Payment::create([
                        'order_id'           => $order->id,
                        'provider'           => Payment::PROVIDER_STRIPE,
                        'provider_reference' => $object['id'] ?? 're_sandbox',
                        'provider_event_id'  => $eventId,
                        'status'             => Payment::STATUS_REFUNDED,
                        'currency'           => 'USD',
                        'amount_cents'       => $amountCents,
                        'signature_verified' => true,
                    ]);

                    // Revoke digital entitlements upon refund
                    app(GrantEntitlementsForOrder::class)->revoke($order);

                    AuditEvent::create([
                        'actor_id'       => null,
                        'action'         => 'order.refunded',
                        'auditable_type' => Order::class,
                        'auditable_id'   => $order->id,
                        'old_values'     => ['status' => $oldStatus],
                        'new_values'     => ['status' => Order::STATUS_REFUNDED],
                        'correlation_id' => $eventId,
                        'ip_address'     => request()->ip(),
                        'user_agent'     => 'Stripe-Webhook/1.0',
                    ]);

                    return ['status' => 'success', 'code' => 200, 'message' => 'Order refunded and entitlements revoked'];

                case 'payment_intent.payment_failed':
                    if ($order->status === Order::STATUS_PENDING_PAYMENT) {
                        $order->transitionTo(Order::STATUS_FAILED);
                        $order->save();
                    }

                    Payment::create([
                        'order_id'           => $order->id,
                        'provider'           => Payment::PROVIDER_STRIPE,
                        'provider_reference' => $object['id'] ?? 'pi_failed',
                        'provider_event_id'  => $eventId,
                        'status'             => Payment::STATUS_FAILED,
                        'currency'           => 'USD',
                        'amount_cents'       => $amountCents,
                        'signature_verified' => true,
                    ]);

                    AuditEvent::create([
                        'actor_id'       => null,
                        'action'         => 'order.payment_failed',
                        'auditable_type' => Order::class,
                        'auditable_id'   => $order->id,
                        'new_values'     => ['status' => Order::STATUS_FAILED],
                        'correlation_id' => $eventId,
                        'ip_address'     => request()->ip(),
                        'user_agent'     => 'Stripe-Webhook/1.0',
                    ]);

                    return ['status' => 'success', 'code' => 200, 'message' => 'Payment failed status recorded'];

                default:
                    return ['status' => 'ignored', 'code' => 200, 'message' => 'Unhandled event type'];
            }
        });
    }
}
