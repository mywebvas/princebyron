<?php

namespace App\Http\Controllers;

use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(
        protected CartService $cartService
    ) {}

    public function data(): JsonResponse
    {
        return response()->json($this->cartService->getSummary());
    }

    public function add(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type'     => 'required|string|in:variant,song',
            'id'       => 'required|integer|min:1',
            'quantity' => 'nullable|integer|min:1|max:10',
        ]);

        try {
            $summary = $this->cartService->addItem(
                $validated['type'],
                (int) $validated['id'],
                (int) ($validated['quantity'] ?? 1)
            );

            return response()->json([
                'success' => true,
                'cart'    => $summary,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type'     => 'required|string|in:variant,song',
            'id'       => 'required|integer|min:1',
            'quantity' => 'required|integer|min:0|max:10',
        ]);

        $summary = $this->cartService->updateItem(
            $validated['type'],
            (int) $validated['id'],
            (int) $validated['quantity']
        );

        return response()->json([
            'success' => true,
            'cart'    => $summary,
        ]);
    }

    public function remove(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|in:variant,song',
            'id'   => 'required|integer|min:1',
        ]);

        $summary = $this->cartService->removeItem(
            $validated['type'],
            (int) $validated['id']
        );

        return response()->json([
            'success' => true,
            'cart'    => $summary,
        ]);
    }

    public function clear(): JsonResponse
    {
        $this->cartService->clear();

        return response()->json([
            'success' => true,
            'cart'    => $this->cartService->getSummary(),
        ]);
    }
}
