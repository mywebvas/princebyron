<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\CartService;
use App\Services\CheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class CheckoutController extends Controller
{
    public function __construct(
        protected CartService $cartService,
        protected CheckoutService $checkoutService
    ) {}

    public function calculate(Request $request): JsonResponse
    {
        $countryCode = $request->query('country');
        $calculation = $this->checkoutService->calculate($countryCode);

        return response()->json($calculation);
    }

    public function process(Request $request): JsonResponse
    {
        $summary = $this->cartService->getSummary();
        if (empty($summary['items'])) {
            return response()->json([
                'success' => false,
                'message' => 'Your bag is empty.',
            ], 422);
        }

        $rules = [
            'name'           => 'required|string|max:255',
            'email'          => 'required|email|max:255',
            'payment_method' => 'required|string|in:stripe,paypal,bank_transfer',
        ];

        if ($summary['has_physical']) {
            $rules['address_line1'] = 'required|string|max:255';
            $rules['address_line2'] = 'nullable|string|max:255';
            $rules['city']          = 'required|string|max:100';
            $rules['state']         = 'nullable|string|max:100';
            $rules['postal_code']   = 'required|string|max:20';
            $rules['country']       = 'required|string|size:2';
        }

        $validated = $request->validate($rules);

        $customerData = [
            'name'  => $validated['name'],
            'email' => $validated['email'],
        ];

        $shippingData = [
            'name'          => $validated['name'],
            'address_line1' => $validated['address_line1'] ?? null,
            'address_line2' => $validated['address_line2'] ?? null,
            'city'          => $validated['city'] ?? null,
            'state'         => $validated['state'] ?? null,
            'postal_code'   => $validated['postal_code'] ?? null,
            'country'       => $validated['country'] ?? null,
        ];

        try {
            $order = $this->checkoutService->createOrder(
                auth()->user(),
                $customerData,
                $shippingData,
                $validated['payment_method'],
                $shippingData['country']
            );

            $response = [
                'success'         => true,
                'order_reference' => $order->reference,
                'status'          => $order->status,
                'total_cents'     => $order->total_cents,
                'currency'        => $order->currency,
                'payment_method'  => $validated['payment_method'],
            ];

            if ($validated['payment_method'] === 'bank_transfer') {
                $response['bank_instructions'] = [
                    'is_demo'      => true,
                    'notice'       => '[DEMO / TEST INSTRUCTIONS] Please do not transfer real money. This platform is in development.',
                    'bank_name'    => 'First Devotion Bank (Demo)',
                    'account_name' => 'Prince Byron Ministry Demo',
                    'reference'    => $order->reference,
                    'note'         => 'Order will remain in awaiting_bank_verification until the owner independently confirms funds on the bank statement.',
                ];
            } elseif ($validated['payment_method'] === 'stripe') {
                $response['stripe_sandbox'] = [
                    'mode'           => 'sandbox',
                    'payment_intent' => 'pi_sandbox_' . $order->reference,
                    'client_secret'  => 'seti_sandbox_' . $order->reference . '_secret_mock',
                    'publishable_key'=> config('services.stripe.key'),
                ];
            } elseif ($validated['payment_method'] === 'paypal') {
                $response['paypal_sandbox'] = [
                    'mode'      => 'sandbox',
                    'order_id'  => 'PAYPAL-SANDBOX-' . $order->reference,
                    'client_id' => config('services.paypal.client_id'),
                ];
            }

            return response()->json($response, 201);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
