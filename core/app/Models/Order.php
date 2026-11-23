<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;

    // -------------------------------------------------------------------------
    // State constants — all transitions enforced here, never by the browser.
    // -------------------------------------------------------------------------
    const STATUS_PENDING_PAYMENT          = 'pending_payment';
    const STATUS_AWAITING_BANK            = 'awaiting_bank_verification';
    const STATUS_PAID                     = 'paid';
    const STATUS_FULFILLMENT_PENDING      = 'fulfillment_pending';
    const STATUS_FULFILLED                = 'fulfilled';
    const STATUS_CANCELLED                = 'cancelled';
    const STATUS_REFUNDED                 = 'refunded';
    const STATUS_FAILED                   = 'failed';

    protected $fillable = [
        'reference',
        'user_id',
        'status',
        'currency',
        'subtotal_cents',
        'shipping_cents',
        'tax_cents',
        'total_cents',
        'shipping_name',
        'shipping_address_line1',
        'shipping_address_line2',
        'shipping_city',
        'shipping_state',
        'shipping_postal_code',
        'shipping_country',
        'customer_email',
        'customer_name',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'subtotal_cents'  => 'integer',
            'shipping_cents'  => 'integer',
            'tax_cents'       => 'integer',
            'total_cents'     => 'integer',
        ];
    }

    // -------------------------------------------------------------------------
    // Guard state transitions — only valid moves allowed.
    // -------------------------------------------------------------------------

    /** Valid next states from each current state. */
    protected static array $transitions = [
        self::STATUS_PENDING_PAYMENT => [
            self::STATUS_AWAITING_BANK,
            self::STATUS_PAID,
            self::STATUS_CANCELLED,
            self::STATUS_FAILED,
        ],
        self::STATUS_AWAITING_BANK => [
            self::STATUS_PAID,
            self::STATUS_CANCELLED,
        ],
        self::STATUS_PAID => [
            self::STATUS_FULFILLMENT_PENDING,
            self::STATUS_REFUNDED,
        ],
        self::STATUS_FULFILLMENT_PENDING => [
            self::STATUS_FULFILLED,
            self::STATUS_REFUNDED,
        ],
        self::STATUS_FULFILLED  => [self::STATUS_REFUNDED],
        self::STATUS_CANCELLED  => [],
        self::STATUS_REFUNDED   => [],
        self::STATUS_FAILED     => [self::STATUS_CANCELLED],
    ];

    /**
     * Attempt a guarded status transition.
     * Throws \LogicException on invalid move; caller is responsible for
     * wrapping in a transaction and creating an audit event.
     */
    public function transitionTo(string $newStatus): void
    {
        $allowed = static::$transitions[$this->status] ?? [];

        if (! in_array($newStatus, $allowed, true)) {
            throw new \LogicException(
                "Invalid order transition: {$this->status} → {$newStatus} (order #{$this->id})"
            );
        }

        $this->status = $newStatus;
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    // -------------------------------------------------------------------------
    // Money helpers — always work in cents.
    // -------------------------------------------------------------------------

    /** Recalculate total from parts (call before saving, never trust browser). */
    public function recalculateTotal(): void
    {
        $this->total_cents = $this->subtotal_cents
                           + $this->shipping_cents
                           + $this->tax_cents;
    }
}
