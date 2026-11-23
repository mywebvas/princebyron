<?php

namespace App\Services;

use App\Models\DownloadEntitlement;
use App\Models\Order;
use App\Models\Song;

/**
 * GrantEntitlementsForOrder — the single place digital access is granted or
 * revoked for an order.
 *
 * WHY THIS EXISTS (review P1-4)
 * -----------------------------
 * Entitlement creation was inlined in the Stripe webhook and copied in the
 * PayPal webhook. The owner's "Verify Bank Funds" action — the only path by
 * which a bank-transfer customer becomes paid — never did it at all. A customer
 * who paid by bank transfer was marked Paid and then could not download what
 * they bought. Three copies of the same rule, one of them missing, is exactly
 * the bug that sharing one implementation prevents.
 *
 * Rules encoded here:
 *   - Only song line items produce entitlements. Merchandise does not.
 *   - A guest order (no user) cannot receive entitlements, because a download
 *     is authorised against an authenticated user. This is surfaced to the
 *     caller rather than silently skipped.
 *   - Granting is idempotent: firstOrCreate on (user, order, song, format).
 *   - Revocation on refund deactivates rather than deletes, so the record of
 *     what was once granted survives.
 */
class GrantEntitlementsForOrder
{
    /**
     * Grant entitlements for every digital line item on an order.
     *
     * @return array{granted: int, skipped_guest: bool}
     */
    public function grant(Order $order): array
    {
        if (! $order->user_id) {
            // Not an error to throw: the order is valid, it just belongs to a
            // guest. The owner needs to know this so they can invite the
            // customer to create an account rather than wonder why "Paid"
            // produced no download.
            return ['granted' => 0, 'skipped_guest' => true];
        }

        $granted = 0;

        foreach ($order->items as $item) {
            if ($item->itemable_type !== Song::class) {
                continue;
            }

            $entitlement = DownloadEntitlement::firstOrCreate(
                [
                    'user_id'  => $order->user_id,
                    'order_id' => $order->id,
                    'song_id'  => $item->itemable_id,
                    'format'   => DownloadEntitlement::FORMAT_MP3,
                ],
                [
                    'is_active'      => true,
                    'expires_at'     => null,
                    'download_count' => 0,
                ]
            );

            if ($entitlement->wasRecentlyCreated) {
                $granted++;
            }
        }

        return ['granted' => $granted, 'skipped_guest' => false];
    }

    /**
     * Revoke every active entitlement belonging to an order (used on refund).
     *
     * Deactivates rather than deletes: the fact that access was once granted is
     * part of the financial record.
     *
     * @return int number of entitlements revoked
     */
    public function revoke(Order $order): int
    {
        if (! $order->user_id) {
            return 0;
        }

        return DownloadEntitlement::query()
            ->where('order_id', $order->id)
            ->where('is_active', true)
            ->update(['is_active' => false]);
    }
}
