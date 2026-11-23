<?php

namespace App\Http\Controllers;

use App\Services\Payment\PayPalSandboxService;
use App\Services\Payment\StripeSandboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function __construct(
        protected StripeSandboxService $stripeService,
        protected PayPalSandboxService $paypalService
    ) {}

    public function handleStripe(Request $request): JsonResponse
    {
        $rawPayload = $request->getContent();
        $sigHeader  = $request->header('Stripe-Signature', '');
        $event      = json_decode($rawPayload, true) ?? [];

        $result = $this->stripeService->handleWebhook($event, $rawPayload, $sigHeader);

        return response()->json($result, $result['code'] ?? 200);
    }

    public function handlePayPal(Request $request): JsonResponse
    {
        $rawPayload = $request->getContent();
        $headers    = array_change_key_case($request->headers->all(), CASE_LOWER);
        $flattenedHeaders = array_map(fn ($h) => is_array($h) ? $h[0] : $h, $headers);
        $event      = json_decode($rawPayload, true) ?? [];

        $result = $this->paypalService->handleWebhook($event, $rawPayload, $flattenedHeaders);

        return response()->json($result, $result['code'] ?? 200);
    }
}
