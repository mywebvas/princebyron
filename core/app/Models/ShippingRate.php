<?php

namespace App\Models;

use App\Models\Concerns\Auditable;

use Illuminate\Database\Eloquent\Model;

class ShippingRate extends Model
{
    use Auditable;

    protected $fillable = [
        'name',
        'country_code',
        'rate_cents',
        'free_above_cents',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'rate_cents'       => 'integer',
            'free_above_cents' => 'integer',
            'is_active'        => 'boolean',
            'sort_order'       => 'integer',
        ];
    }

    /**
     |--------------------------------------------------------------------------
     | Scopes
     |--------------------------------------------------------------------------
     */

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    public function scopeForCountry($query, ?string $countryCode)
    {
        if (! $countryCode) {
            return $query->whereNull('country_code');
        }

        return $query->where(function ($q) use ($countryCode) {
            $q->where('country_code', strtoupper($countryCode))
              ->orWhereNull('country_code');
        })->orderByRaw('country_code IS NULL ASC'); // country-specific matches first
    }

    /**
     |--------------------------------------------------------------------------
     | Static Helpers (Owner Configuration Verification)
     |--------------------------------------------------------------------------
     */

    public static function hasActiveRates(): bool
    {
        return static::active()->exists();
    }

    public static function findActiveForCountry(?string $countryCode): ?static
    {
        return static::active()->forCountry($countryCode)->first();
    }

    /**
     |--------------------------------------------------------------------------
     | Shipping Cost Calculation (Server-Authoritative)
     |--------------------------------------------------------------------------
     | Returns shipping cost in USD cents. Never trust client calculations.
     */
    public function calculateShipping(int $subtotalCents): int
    {
        if ($this->free_above_cents !== null && $subtotalCents >= $this->free_above_cents) {
            return 0;
        }

        return $this->rate_cents;
    }
}
