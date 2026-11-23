<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail, FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * A customer with financial history cannot be deleted (review P1-5).
     *
     * `orders.user_id` is `nullOnDelete`, so deleting a user account does not
     * fail — it silently rewrites history: the order survives with no owner, so
     * the purchase is no longer attributable, the customer can never reach their
     * downloads again, and a chargeback investigation has no account to point
     * at. The database's referential integrity is intact and the business record
     * is not.
     *
     * The right operation for "customer wants their account gone" is
     * anonymisation (clear name/email/address, keep the order), which is a
     * deliberate, reviewable action — not a cascade side effect of a Delete
     * button. Until that exists, refuse the delete and say why.
     */
    protected static function booted(): void
    {
        static::deleting(function (User $user) {
            if ($user->orders()->exists()) {
                throw new \DomainException(
                    'This customer has orders and cannot be deleted: orders are financial records. '
                    .'Deactivate or anonymise the account instead.'
                );
            }
        });
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    // Role constants — the only valid role values.
    const ROLE_OWNER    = 'owner';
    const ROLE_CUSTOMER = 'customer';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at'     => 'datetime',
            'is_active'         => 'boolean',
            'password'          => 'hashed',
        ];
    }

    // -------------------------------------------------------------------------
    // Filament panel access — owner-only, active accounts only.
    // This is enforced server-side on every request to /admin.
    // -------------------------------------------------------------------------
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isOwner() && $this->is_active;
    }

    // -------------------------------------------------------------------------
    // Role helpers — always check server-side, never rely on client claims.
    // -------------------------------------------------------------------------

    public function isOwner(): bool
    {
        return $this->role === self::ROLE_OWNER;
    }

    public function isCustomer(): bool
    {
        return $this->role === self::ROLE_CUSTOMER;
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function downloadEntitlements(): HasMany
    {
        return $this->hasMany(DownloadEntitlement::class);
    }
}
