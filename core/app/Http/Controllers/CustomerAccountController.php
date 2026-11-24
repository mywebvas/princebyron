<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomerAccountController extends Controller
{
    public function dashboard(Request $request)
    {
        $user = Auth::user();

        if ($user) {
            $orders = $user->orders()
                ->with(['items', 'payment'])
                ->latest()
                ->get();

            $entitlements = $user->downloadEntitlements()
                ->with('song')
                ->active()
                ->get();
        } else {
            $orders = collect();
            $entitlements = collect();
        }

        if ($request->wantsJson()) {
            return response()->json([
                'user'         => $this->safeUser($user),
                'orders'       => $orders,
                'entitlements' => $entitlements,
            ]);
        }

        return view('pages.account', [
            'title'        => 'Customer Fan Vault & Entitlements — Prince Byron',
            'user'         => $user,
            'orders'       => $orders,
            'entitlements' => $entitlements,
        ]);
    }

    public function orderDetail(string $reference, Request $request)
    {
        $user = Auth::user();

        $order = Order::with(['items', 'payment'])
            ->where('reference', $reference)
            ->firstOrFail();

        // Strict cross-account authorization check (SECURITY.md & ASVS 5.0 L2)
        if ($order->user_id !== $user->id && ! $user->isOwner()) {
            abort(403, 'Unauthorized access to customer order.');
        }

        if ($request->wantsJson()) {
            return response()->json([
                'order' => $order,
            ]);
        }

        $orders = $user->orders()
            ->with(['items', 'payment'])
            ->latest()
            ->get();

        $entitlements = $user->downloadEntitlements()
            ->with('song')
            ->active()
            ->get();

        return view('pages.account', [
            'title'         => 'Order ' . $order->reference . ' — Prince Byron',
            'user'          => $user,
            'orders'        => $orders,
            'entitlements'  => $entitlements,
            'selectedOrder' => $order,
        ]);
    }

    /**
     * Return only the fields safe to expose to the client.
     * Never return the full model: role, is_active, email_verified_at etc.
     * are internal state the client does not need.
     */
    protected function safeUser(?\App\Models\User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
        ];
    }
}
