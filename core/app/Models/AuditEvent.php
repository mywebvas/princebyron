<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditEvent extends Model
{
    public $timestamps = true;

    // Intentionally no updatedAt — audit records are immutable.
    const UPDATED_AT = null;

    protected $fillable = [
        'actor_id',
        'action',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'correlation_id',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    /**
     |--------------------------------------------------------------------------
     | Append-Only Enforcement (ASVS 5.0 Level 2)
     |--------------------------------------------------------------------------
     | Model layer: throw LogicException on any attempted update or deletion.
     */
    protected static function booted(): void
    {
        static::updating(function (AuditEvent $event) {
            throw new \LogicException('Audit events are strictly append-only. Updates are forbidden.');
        });

        static::deleting(function (AuditEvent $event) {
            throw new \LogicException('Audit events are strictly append-only. Deletions are forbidden.');
        });
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
