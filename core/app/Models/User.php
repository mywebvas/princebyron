<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
    const ROLE_STAFF    = 'staff';

    // Staff levels — least to most privileged.
    const STAFF_LEVEL_OWNER   = 'owner';
    const STAFF_LEVEL_MANAGER = 'manager';
    const STAFF_LEVEL_STAFF   = 'staff';
    const STAFF_LEVEL_VIEWER  = 'viewer';

    // Granular permission slugs. A staff member's `permissions` JSON column
    // holds a subset of these; levels imply a baseline but never override.
    const PERM_VIEW_ORDERS      = 'view_orders';
    const PERM_MANAGE_ORDERS    = 'manage_orders';
    const PERM_VIEW_PRODUCTS    = 'view_products';
    const PERM_MANAGE_PRODUCTS  = 'manage_products';
    const PERM_VIEW_CONTENT     = 'view_content';
    const PERM_MANAGE_CONTENT   = 'manage_content';
    const PERM_VIEW_BOOKINGS    = 'view_bookings';
    const PERM_MANAGE_BOOKINGS  = 'manage_bookings';
    const PERM_MANAGE_USERS     = 'manage_users';
    const PERM_VIEW_REPORTS     = 'view_reports';
    const PERM_EXPORT_DATA      = 'export_data';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'is_active',
        'staff_level',
        'permissions',
        'invited_by',
        'invited_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'permissions',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at'     => 'datetime',
            'is_active'         => 'boolean',
            'password'          => 'hashed',
            'permissions'       => 'array',
        ];
    }

    // -------------------------------------------------------------------------
    // Filament panel access — owner or active staff accounts only.
    // This is enforced server-side on every request to /admin.
    // -------------------------------------------------------------------------
    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->is_active) {
            return false;
        }

        // Owners always have panel access.
        if ($this->isOwner()) {
            return true;
        }

        // Staff members need a staff_level to access the panel.
        return $this->isStaff() && $this->staff_level !== null;
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

    public function isStaff(): bool
    {
        return $this->role === self::ROLE_STAFF;
    }

    /**
     * Granular permission check. Levels imply a baseline but permissions are
     * the source of truth for staff members. Owners implicitly have all
     * permissions.
     *
     * @param string $permission One of the PERM_* constants on this model.
     */
    public function can(string $permission): bool
    {
        if ($this->isOwner()) {
            return true;
        }

        if (! $this->isStaff()) {
            return false;
        }

        $permissions = $this->permissions ?? [];

        return is_array($permissions) && in_array($permission, $permissions, true);
    }

    /**
     * Staff level check. Owners implicitly satisfy any level requirement.
     */
    public function hasStaffLevel(string $minimumLevel): bool
    {
        if ($this->isOwner()) {
            return true;
        }

        if (! $this->isStaff()) {
            return false;
        }

        $order = [
            self::STAFF_LEVEL_VIEWER  => 0,
            self::STAFF_LEVEL_STAFF   => 1,
            self::STAFF_LEVEL_MANAGER => 2,
            self::STAFF_LEVEL_OWNER   => 3,
        ];

        $userLevel = $this->staff_level ?? self::STAFF_LEVEL_VIEWER;
        $minLevel = $minimumLevel ?? self::STAFF_LEVEL_VIEWER;

        return ($order[$userLevel] ?? 0) >= ($order[$minLevel] ?? 0);
    }

    // Convenience checks for UI gating.

    public function canViewOrders(): bool
    {
        return $this->can(self::PERM_VIEW_ORDERS);
    }

    public function canManageOrders(): bool
    {
        return $this->can(self::PERM_MANAGE_ORDERS);
    }

    public function canViewProducts(): bool
    {
        return $this->can(self::PERM_VIEW_PRODUCTS);
    }

    public function canManageProducts(): bool
    {
        return $this->can(self::PERM_MANAGE_PRODUCTS);
    }

    public function canViewContent(): bool
    {
        return $this->can(self::PERM_VIEW_CONTENT);
    }

    public function canManageContent(): bool
    {
        return $this->can(self::PERM_MANAGE_CONTENT);
    }

    public function canViewBookings(): bool
    {
        return $this->can(self::PERM_VIEW_BOOKINGS);
    }

    public function canManageBookings(): bool
    {
        return $this->can(self::PERM_MANAGE_BOOKINGS);
    }

    public function canManageUsers(): bool
    {
        return $this->can(self::PERM_MANAGE_USERS);
    }

    public function canViewReports(): bool
    {
        return $this->can(self::PERM_VIEW_REPORTS);
    }

    public function canExportData(): bool
    {
        return $this->can(self::PERM_EXPORT_DATA);
    }

    /**
     * Default permission set for each staff level. Used when creating a
     * staff user from an invitation.
     */
    public static function defaultPermissionsForLevel(string $level): array
    {
        return match ($level) {
            self::STAFF_LEVEL_VIEWER  => [
                self::PERM_VIEW_ORDERS,
                self::PERM_VIEW_PRODUCTS,
                self::PERM_VIEW_CONTENT,
                self::PERM_VIEW_BOOKINGS,
            ],
            self::STAFF_LEVEL_STAFF   => [
                self::PERM_VIEW_ORDERS,
                self::PERM_MANAGE_ORDERS,
                self::PERM_VIEW_PRODUCTS,
                self::PERM_MANAGE_PRODUCTS,
                self::PERM_VIEW_CONTENT,
                self::PERM_VIEW_BOOKINGS,
            ],
            self::STAFF_LEVEL_MANAGER => [
                self::PERM_VIEW_ORDERS,
                self::PERM_MANAGE_ORDERS,
                self::PERM_VIEW_PRODUCTS,
                self::PERM_MANAGE_PRODUCTS,
                self::PERM_VIEW_CONTENT,
                self::PERM_MANAGE_CONTENT,
                self::PERM_VIEW_BOOKINGS,
                self::PERM_MANAGE_BOOKINGS,
                self::PERM_VIEW_REPORTS,
            ],
            self::STAFF_LEVEL_OWNER   => [
                self::PERM_VIEW_ORDERS,
                self::PERM_MANAGE_ORDERS,
                self::PERM_VIEW_PRODUCTS,
                self::PERM_MANAGE_PRODUCTS,
                self::PERM_VIEW_CONTENT,
                self::PERM_MANAGE_CONTENT,
                self::PERM_VIEW_BOOKINGS,
                self::PERM_MANAGE_BOOKINGS,
                self::PERM_MANAGE_USERS,
                self::PERM_VIEW_REPORTS,
                self::PERM_EXPORT_DATA,
            ],
            default                   => [],
        };
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function downloadEntitlements(): HasMany
    {
        return $this->hasMany(DownloadEntitlement::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function invitationsSent(): HasMany
    {
        return $this->hasMany(StaffInvitation::class, 'invited_by');
    }
}
