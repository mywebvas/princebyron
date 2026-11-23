<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    // Payment providers
    const PROVIDER_STRIPE       = 'stripe';
    const PROVIDER_PAYPAL       = 'paypal';
    const PROVIDER_BANK         = 'bank_transfer';

    // Payment statuses
    const STATUS_PENDING              = 'pending';
    const STATUS_SUCCEEDED            = 'succeeded';
    const STATUS_FAILED               = 'failed';
    const STATUS_REFUNDED             = 'refunded';
    const STATUS_AWAITING_VERIFICATION = 'awaiting_verification';

    protected $fillable = [
        'order_id',
        'provider',
        'provider_reference',
        'provider_event_id',
        'status',
        'currency',
        'amount_cents',
        'signature_verified',
        'provider_metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents'       => 'integer',
            'signature_verified' => 'boolean',
            'provider_metadata'  => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
