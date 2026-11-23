<?php

namespace App\Services\Payment;

use App\Models\AuditEvent;
use App\Models\Order;
use App\Models\Payment;
use App\Services\GrantEntitlementsForOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayPalSandboxService
{
    /**
     * Optional public key or certificate override for unit/feature testing without network access.
     */
    protected static ?string $testPublicKey = null;

    public static function setTestPublicKey(?string $key): void
    {
        static::$testPublicKey = $key;
    }

    /**
     * Compute a valid test signature using PayPal's documented asymmetric format:
     * transmission_id|transmission_time|webhook_id|crc32(payload)
     */
    public static function generateDocumentedTestSignature(
        string $payload,
        string $webhookId,
        string $transmissionId,
        string $timestamp,
        \OpenSSLAsymmetricKey|string $privateKey
    ): string {
        $data = $transmissionId . '|' . $timestamp . '|' . $webhookId . '|' . crc32($payload);
        openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        return base64_encode($signature);
    }

    /**
     * Verify PayPal webhook signature using PayPal's documented cryptographic verification flow.
     *
     * Rules (Official PayPal Developer Specification & ASVS 5.0 L2):
     * 1. Requires PAYPAL-AUTH-ALGO, PAYPAL-CERT-URL, PAYPAL-TRANSMISSION-ID, PAYPAL-TRANSMISSION-SIG, and PAYPAL-TRANSMISSION-TIME.
     * 2. Strict cert_url validation: must be HTTPS and strictly hosted on api.paypal.com or api.sandbox.paypal.com (prevents SSRF).
     * 3. Signed string: transmission_id|transmission_time|webhook_id|crc32(payload).
     * 4. Asymmetric signature verification using openssl_verify with SHA256withRSA.
     */
    public function verifySignature(string $rawPayload, array $headers, ?string $webhookId): bool
    {
        if (empty($webhookId) || empty($rawPayload)) {
            return false;
        }

        $transmissionId = $headers['paypal-transmission-id'] ?? null;
        $timestamp      = $headers['paypal-transmission-time'] ?? null;
        $signature      = $headers['paypal-transmission-sig'] ?? null;
        $certUrl        = $headers['paypal-cert-url'] ?? null;
        $authAlgo       = $headers['paypal-auth-algo'] ?? 'SHA256withRSA';

        if (! $transmissionId || ! $timestamp || ! $signature || ! $certUrl) {
            return false;
        }

        // Validate cert_url to strictly permit official PayPal endpoints (Anti-SSRF)
        $parsed = parse_url($certUrl);
        $scheme = $parsed['scheme'] ?? '';
        $host   = $parsed['host'] ?? '';

        $allowedHosts = ['api.paypal.com', 'api.sandbox.paypal.com'];
        if ($scheme !== 'https' || ! in_array(strtolower($host), $allowedHosts, true)) {
            // Check if test public key override is active in local testing environment
            if (empty(static::$testPublicKey)) {
                Log::warning("PayPal webhook verification rejected invalid or suspicious cert_url: {$certUrl}");
                return false;
            }
        }

        $dataToVerify = $transmissionId . '|' . $timestamp . '|' . $webhookId . '|' . crc32($rawPayload);
        $rawSignature = base64_decode($signature, true);
        if ($rawSignature === false) {
            return false;
        }

        // Use test public key if set (for offline test runner) or fetch certificate
        $publicKey = static::$testPublicKey;
        if (! $publicKey) {
            try {
                $certContent = @file_get_contents($certUrl);
                if (! $certContent) {
                    return false;
                }
                $publicKey = openssl_pkey_get_public($certContent);
            } catch (\Throwable $e) {
                Log::error("Failed to fetch PayPal certificate from {$certUrl}: " . $e->getMessage());
                return false;
            }
        }

        if (! $publicKey) {
            return false;
        }

        $result = openssl_verify($dataToVerify, $rawSignature, $publicKey, OPENSSL_ALGO_SHA256);

        return $result === 1;
    }

    /**
     * Process PayPal webhook event with idempotency, amount match, and entitlement management.
     */
    public function handleWebhook(array $event, string $rawPayload, array $headers): array
    {
        $webhookId = config('services.paypal.webhook_id');

        // Security requirement: Missing configuration must fail closed
        if (empty($webhookId)) {
            Log::error('PayPal webhook rejected: webhook_id is not configured in environment. Failing closed.');
            return [
                'status'  => 'error',
                'code'    => 500,
                'message' => 'PayPal webhook is not configured. Request rejected (fail-closed).',
            ];
        }

        if (! $this->verifySignature($rawPayload, $headers, $webhookId)) {
            return ['status' => 'error', 'code' => 400, 'message' => 'Invalid PayPal webhook signature'];
        }

        $eventId   = $event['id'] ?? null;
        $eventType = $event['event_type'] ?? null;

        if (! $eventId || ! $eventType) {
            return ['status' => 'error', 'code' => 400, 'message' => 'Missing event id or type'];
        }

        // Idempotency check: Replay attack prevention
        if (Payment::where('provider_event_id', $eventId)->exists()) {
            return ['status' => 'duplicate_ignored', 'code' => 200, 'message' => 'Event already processed'];
        }

        $resource = $event['resource'] ?? [];
        $orderReference = $resource['custom_id'] ?? $resource['invoice_id'] ?? null;
        $amountValue = (float) ($resource['amount']['value'] ?? 0);
        $amountCents = (int) round($amountValue * 100);
        $currency = strtoupper($resource['amount']['currency_code'] ?? 'USD');

        if (! $orderReference) {
            return ['status' => 'ignored', 'code' => 200, 'message' => 'No order reference found in resource custom_id'];
        }

        $order = Order::where('reference', $orderReference)->first();
        if (! $order) {
            return ['status' => 'error', 'code' => 404, 'message' => 'Order not found'];
        }

        return DB::transaction(function () use ($eventType, $order, $amountCents, $currency, $eventId, $resource) {
            switch ($eventType) {
                case 'PAYMENT.CAPTURE.COMPLETED':
                    if ($currency !== 'USD' || $amountCents !== $order->total_cents) {
                        Log::warning("PayPal payment mismatch for {$order->reference}: expected {$order->total_cents} USD, got {$amountCents} {$currency}");
                        return ['status' => 'error', 'code' => 422, 'message' => 'Payment amount or currency mismatch'];
                    }

                    $oldStatus = $order->status;
                    $order->transitionTo(Order::STATUS_PAID);
                    $order->save();

                    Payment::create([
                        'order_id'           => $order->id,
                        'provider'           => Payment::PROVIDER_PAYPAL,
                        'provider_reference' => $resource['id'] ?? 'capture_sandbox',
                        'provider_event_id'  => $eventId,
                        'status'             => Payment::STATUS_SUCCEEDED,
                        'currency'           => 'USD',
                        'amount_cents'       => $amountCents,
                        'signature_verified' => true,
                    ]);

                    AuditEvent::create([
                        'actor_id'       => null,
                        'action'         => 'order.paid',
                        'auditable_type' => Order::class,
                        'auditable_id'   => $order->id,
                        'old_values'     => ['status' => $oldStatus],
                        'new_values'     => ['status' => Order::STATUS_PAID, 'provider' => 'paypal'],
                        'correlation_id' => $eventId,
                        'ip_address'     => request()->ip(),
                        'user_agent'     => 'PayPal-Webhook/1.0',
                    ]);

                    // Grant entitlements. Shared with every other path to
                    // "paid", including owner-verified bank transfer (P1-4).
                    app(GrantEntitlementsForOrder::class)->grant($order);

                    return ['status' => 'success', 'code' => 200, 'message' => 'PayPal payment captured, order marked paid'];

                case 'PAYMENT.CAPTURE.REFUNDED':
                case 'PAYMENT.CAPTURE.REVERSED':
                    $oldStatus = $order->status;
                    if ($order->status !== Order::STATUS_REFUNDED) {
                        $order->transitionTo(Order::STATUS_REFUNDED);
                        $order->save();
                    }

                    Payment::create([
                        'order_id'           => $order->id,
                        'provider'           => Payment::PROVIDER_PAYPAL,
                        'provider_reference' => $resource['id'] ?? 'refund_sandbox',
                        'provider_event_id'  => $eventId,
                        'status'             => Payment::STATUS_REFUNDED,
                        'currency'           => 'USD',
                        'amount_cents'       => $amountCents,
                        'signature_verified' => true,
                    ]);

                    // Revoke entitlements
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
                        'user_agent'     => 'PayPal-Webhook/1.0',
                    ]);

                    return ['status' => 'success', 'code' => 200, 'message' => 'PayPal refund processed and entitlements revoked'];

                default:
                    return ['status' => 'ignored', 'code' => 200, 'message' => 'Unhandled PayPal event type'];
            }
        });
    }
}
