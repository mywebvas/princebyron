<?php

namespace App\Models;

use App\Models\Concerns\Auditable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Song extends Model
{
    use SoftDeletes;
    use Auditable;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'is_free',
        'is_published',
        'price_cents',
        'preview_storage_path',
        'download_storage_path_mp3',
        'download_storage_path_wav',
        'has_mp3',
        'has_wav',
        'sort_order',
        'cover_storage_path',
    ];

    // Internal storage paths and soft-delete timestamps are never exposed
    // through JSON APIs.
    protected $hidden = [
        'preview_storage_path',
        'download_storage_path_mp3',
        'download_storage_path_wav',
        'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'is_free'      => 'boolean',
            'is_published' => 'boolean',
            'has_mp3'      => 'boolean',
            'has_wav'      => 'boolean',
            'price_cents'  => 'integer',
        ];
    }

    // -------------------------------------------------------------------------
    // Business rules — enforced server-side only.
    // -------------------------------------------------------------------------

    /** Effective price in cents. Free songs are always 0. */
    public function effectivePriceCents(): int
    {
        return $this->is_free ? 0 : (int) $this->price_cents;
    }

    /** Whether a given user has an active entitlement for this song + format. */
    public function userHasEntitlement(User $user, string $format): bool
    {
        return $this->entitlements()
            ->where('user_id', $user->id)
            ->where('format', $format)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(DownloadEntitlement::class);
    }
}
