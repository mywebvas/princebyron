<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Order authorization policy.
 *
 * Rules (SECURITY.md):
 * - Customers can only view their own orders.
 * - Owner can view and manage all orders.
 * - Cross-account access must be denied — always query through authenticated relation.
 * - State transitions (mark paid, refund, etc.) are owner-only.
 */
class OrderPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true; // customers see their own (filtered in controller); owner sees all
    }

    public function view(User $user, Order $order): bool
    {
        return $user->isOwner() || $order->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true; // any authenticated user may place an order
    }

    public function update(User $user, Order $order): bool
    {
        return $user->isOwner(); // only owner may change order state/notes
    }

    public function delete(User $user, Order $order): bool
    {
        return $user->isOwner();
    }

    /** Transition an order state (mark paid, refund, cancel). Owner-only. */
    public function transition(User $user, Order $order): bool
    {
        return $user->isOwner();
    }
}
