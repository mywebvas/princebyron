<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DownloadEntitlement extends Model
{
    const FORMAT_MP3 = 'mp3';
    const FORMAT_WAV = 'wav';

    protected $fillable = [
        'user_id',
        'order_id',
        'song_id',
        'format',
        'is_active',
        'expires_at',
        'download_count',
        'last_downloaded_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active'          => 'boolean',
            'expires_at'         => 'datetime',
            'last_downloaded_at' => 'datetime',
            'download_count'     => 'integer',
        ];
    }

    // -------------------------------------------------------------------------
    // Active scope — always check this before serving any download.
    // -------------------------------------------------------------------------
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                     ->where(function ($q) {
                         $q->whereNull('expires_at')
                           ->orWhere('expires_at', '>', now());
                     });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function song(): BelongsTo
    {
        return $this->belongsTo(Song::class);
    }
}
