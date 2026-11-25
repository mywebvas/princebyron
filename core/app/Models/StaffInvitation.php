<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| StaffInvitation — multi-owner staff onboarding
|--------------------------------------------------------------------------
|
| An invitation lets an existing owner invite a staff member to the admin
| panel at a specific level with specific permissions. The invitation is
| claimed once via a secure link containing a 256-bit token.
|
| Levels (least to most privileged):
|   viewer    — read-only admin access
|   staff     — orders + products (no users, no settings, no exports)
|   manager   — orders + products + content + bookings (no users, no exports)
|   owner     — full access (equivalent to the account owner)
|
| Security notes
| --------------
| - `token` is 256 bits of entropy — unguessable, single-use.
| - `expires_at` defaults to 7 days; never accepted after expiry.
| - `accepted_at` makes the token one-time use.
| - `level` and `permissions` are snapshot values from acceptance time.
*/

class StaffInvitation extends Model
{
    const LEVEL_OWNER   = 'owner';
    const LEVEL_MANAGER = 'manager';
    const LEVEL_STAFF   = 'staff';
    const LEVEL_VIEWER  = 'viewer';

    protected $fillable = [
        'email',
        'token',
        'level',
        'permissions',
        'invited_by',
        'expires_at',
        'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'permissions'   => 'array',
            'expires_at'    => 'datetime',
            'accepted_at'   => 'datetime',
            'created_at'    => 'datetime',
            'updated_at'    => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Lifecycle
    |--------------------------------------------------------------------------
    */

    public static function boot(): void
    {
        parent::boot();

        static::creating(function (self $invitation) {
            if (blank($invitation->token)) {
                $invitation->token = bin2hex(random_bytes(32));
            }

            if (blank($invitation->expires_at)) {
                $invitation->expires_at = now()->addDays(7);
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function isClaimable(): bool
    {
        return ! $this->isAccepted() && ! $this->isExpired();
    }

    /**
     * Mark the invitation as accepted and return the staff-level + permissions
     * that should be applied to the new user record.
     */
    public function accept(): array
    {
        $this->update([
            'accepted_at' => now(),
        ]);

        return [
            'level'       => $this->level,
            'permissions' => $this->permissions ?? [],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopePending($query)
    {
        return $query->whereNull('accepted_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }

    public function scopeExpired($query)
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
    }
}
