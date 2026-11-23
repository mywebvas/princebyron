<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id', 'sku', 'label', 'price_cents',
        'stock_quantity', 'is_active', 'color', 'size',
    ];

    protected function casts(): array
    {
        return [
            'price_cents'    => 'integer',
            'stock_quantity' => 'integer',
            'is_active'      => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Whether this variant can be added to cart. Stock -1 = unlimited. */
    public function isAvailable(): bool
    {
        return $this->is_active && ($this->stock_quantity < 0 || $this->stock_quantity > 0);
    }
}
