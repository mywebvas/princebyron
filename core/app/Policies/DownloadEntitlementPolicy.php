<?php

namespace App\Policies;

use App\Models\DownloadEntitlement;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Download entitlement policy.
 *
 * Rules (SECURITY.md):
 * - Customer can only download their own active entitlements.
 * - Entitlement must be active (is_active=true) and not expired.
 * - Owner can view and revoke entitlements.
 * - Never serve audio based solely on a URL guess — always check here.
 */
class DownloadEntitlementPolicy
{
    use HandlesAuthorization;

    public function download(User $user, DownloadEntitlement $entitlement): bool
    {
        if (! $entitlement->is_active) {
            return false;
        }

        if ($entitlement->expires_at && $entitlement->expires_at->isPast()) {
            return false;
        }

        return $user->isOwner() || $entitlement->user_id === $user->id;
    }

    public function revoke(User $user, DownloadEntitlement $entitlement): bool
    {
        return $user->isOwner();
    }
}
