<?php

namespace App\Services;

use App\Models\ProductVariant;
use App\Models\Song;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;

class CartService
{
    const SESSION_KEY = 'pb_cart';

    /**
     * Retrieve raw session cart array.
     */
    public function getRawCart(): array
    {
        return Session::get(self::SESSION_KEY, []);
    }

    /**
     * Add an item (product variant or song) to cart.
     */
    public function addItem(string $type, int $id, int $quantity = 1): array
    {
        if (! in_array($type, ['variant', 'song'], true)) {
            throw new \InvalidArgumentException("Invalid item type: {$type}");
        }

        $cart = $this->getRawCart();
        $key = "{$type}_{$id}";

        if ($type === 'song') {
            // Songs were previously accepted without any existence check, so an
            // invented id was stored in the session and reported as a success
            // while the summary silently dropped it. Validate here so the API
            // can answer honestly.
            $song = Song::find($id);
            if (! $song || ! $song->is_published) {
                throw new \InvalidArgumentException('This track is not available for purchase.');
            }

            // Digital music single copy only
            $cart[$key] = [
                'type'     => 'song',
                'id'       => $id,
                'quantity' => 1,
            ];
        } else {
            // Merchandise variant
            $currentQty = isset($cart[$key]) ? (int) $cart[$key]['quantity'] : 0;
            $newQty = max(1, $currentQty + max(1, $quantity));

            // Verify stock limit server-side
            $variant = ProductVariant::find($id);
            if (! $variant || ! $variant->is_active) {
                throw new \InvalidArgumentException("Variant is not available.");
            }

            if ($variant->stock_quantity >= 0 && $newQty > $variant->stock_quantity) {
                $newQty = max(1, $variant->stock_quantity);
            }

            $cart[$key] = [
                'type'     => 'variant',
                'id'       => $id,
                'quantity' => $newQty,
            ];
        }

        Session::put(self::SESSION_KEY, $cart);

        return $this->getSummary();
    }

    /**
     * Update quantity of an item in cart.
     */
    public function updateItem(string $type, int $id, int $quantity): array
    {
        $cart = $this->getRawCart();
        $key = "{$type}_{$id}";

        if ($quantity <= 0) {
            return $this->removeItem($type, $id);
        }

        if (isset($cart[$key])) {
            if ($type === 'song') {
                $cart[$key]['quantity'] = 1;
            } else {
                $variant = ProductVariant::find($id);
                if ($variant && $variant->stock_quantity >= 0 && $quantity > $variant->stock_quantity) {
                    $quantity = max(1, $variant->stock_quantity);
                }
                $cart[$key]['quantity'] = $quantity;
            }
            Session::put(self::SESSION_KEY, $cart);
        }

        return $this->getSummary();
    }

    /**
     * Remove an item from cart.
     */
    public function removeItem(string $type, int $id): array
    {
        $cart = $this->getRawCart();
        $key = "{$type}_{$id}";

        unset($cart[$key]);
        Session::put(self::SESSION_KEY, $cart);

        return $this->getSummary();
    }

    /**
     * Clear all items in cart.
     */
    public function clear(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    /**
     * Build server-authoritative cart summary with fresh database pricing and stock.
     */
    public function getSummary(): array
    {
        $cart = $this->getRawCart();

        if (empty($cart)) {
            return [
                'items'          => [],
                'item_count'     => 0,
                'subtotal_cents' => 0,
                'has_physical'   => false,
                'currency'       => 'USD',
            ];
        }

        // --- Batch fetch all variants and songs in two queries (no N+1) ---
        $variantIds = [];
        $songIds = [];

        foreach ($cart as $cartItem) {
            if (($cartItem['type'] ?? null) === 'variant') {
                $variantIds[] = (int) ($cartItem['id'] ?? 0);
            } elseif (($cartItem['type'] ?? null) === 'song') {
                $songIds[] = (int) ($cartItem['id'] ?? 0);
            }
        }

        $variants = ProductVariant::with('product')
            ->whereIn('id', array_unique($variantIds))
            ->get()
            ->keyBy('id');

        $songs = Song::whereIn('id', array_unique($songIds))
            ->get()
            ->keyBy('id');

        $items = [];
        $subtotalCents = 0;
        $totalQuantity = 0;
        $hasPhysical = false;

        foreach ($cart as $key => $cartItem) {
            $type = $cartItem['type'] ?? null;
            $id = (int) ($cartItem['id'] ?? 0);
            $qty = (int) ($cartItem['quantity'] ?? 1);

            if ($type === 'variant') {
                $variant = $variants[$id] ?? null;

                if (! $variant || ! $variant->is_active || ! $variant->product || ! $variant->product->is_published) {
                    // Out of stock / unpublished — prune from session
                    unset($cart[$key]);
                    continue;
                }

                $availableQty = $variant->stock_quantity >= 0 ? min($qty, $variant->stock_quantity) : $qty;
                if ($availableQty <= 0) {
                    unset($cart[$key]);
                    continue;
                }

                $lineTotal = $availableQty * $variant->price_cents;
                $subtotalCents += $lineTotal;
                $totalQuantity += $availableQty;
                if ($variant->product->type === 'physical') {
                    $hasPhysical = true;
                }

                $items[] = [
                    'key'              => $key,
                    'type'             => 'variant',
                    'id'               => $variant->id,
                    'product_id'       => $variant->product->id,
                    'title'            => $variant->product->name,
                    'variant_label'    => $variant->label,
                    'sku'              => $variant->sku,
                    'unit_price_cents' => $variant->price_cents,
                    'quantity'         => $availableQty,
                    'line_total_cents' => $lineTotal,
                    'is_physical'      => $variant->product->type === 'physical',
                    'color'            => $variant->color,
                    'size'             => $variant->size,
                ];
            } elseif ($type === 'song') {
                $song = $songs[$id] ?? null;

                if (! $song || ! $song->is_published) {
                    unset($cart[$key]);
                    continue;
                }

                $unitPrice = $song->effectivePriceCents();
                $subtotalCents += $unitPrice;
                $totalQuantity += 1;

                $items[] = [
                    'key'              => $key,
                    'type'             => 'song',
                    'id'               => $song->id,
                    'title'            => $song->title,
                    'variant_label'    => 'Digital Single (' . ($song->is_free ? 'Free' : 'USD') . ')',
                    'sku'              => 'SONG-' . $song->slug,
                    'unit_price_cents' => $unitPrice,
                    'quantity'         => 1,
                    'line_total_cents' => $unitPrice,
                    'is_physical'      => false,
                    'color'            => null,
                    'size'             => null,
                ];
            }
        }

        // Keep session synchronized with any pruned out-of-stock items
        Session::put(self::SESSION_KEY, $cart);

        return [
            'items'          => $items,
            'item_count'     => $totalQuantity,
            'subtotal_cents' => $subtotalCents,
            'has_physical'   => $hasPhysical,
            'currency'       => 'USD',
        ];
    }
}
