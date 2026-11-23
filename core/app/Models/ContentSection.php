<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentSection extends Model
{
    /** Allowlisted brand palette hex colors */
    const COLOR_PRIMARY_DARK  = '#0E0B09';
    const COLOR_SURFACE_DARK  = '#181310';
    const COLOR_GOLD_ACCENT   = '#DFA759';
    const COLOR_TERRACOTTA    = '#9E472A';
    const COLOR_CREAM_LIGHT   = '#F8F4ED';

    const ALLOWED_COLORS = [
        self::COLOR_PRIMARY_DARK  => 'Obsidian Dark (#0E0B09)',
        self::COLOR_SURFACE_DARK  => 'Deep Card (#181310)',
        self::COLOR_GOLD_ACCENT   => 'Earthy Gold (#DFA759)',
        self::COLOR_TERRACOTTA    => 'Terracotta Ochre (#9E472A)',
        self::COLOR_CREAM_LIGHT   => 'Warm Cream (#F8F4ED)',
    ];

    protected $fillable = [
        'section_key',
        'page',
        'title',
        'eyebrow',
        'body',
        'button_text',
        'button_url',
        'image_path',
        'brand_color',
        'is_visible',
        'sort_order',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
            'sort_order' => 'integer',
            'metadata'   => 'array',
        ];
    }

    /**
     |--------------------------------------------------------------------------
     | Security Sanitizers
     |--------------------------------------------------------------------------
     | Strip tags on text fields to strictly prevent HTML/CSS/JS execution.
     | Never trust or render executable markup in content fields.
     */

    public function setTitleAttribute(?string $value): void
    {
        $this->attributes['title'] = $value !== null ? trim(strip_tags($value)) : null;
    }

    public function setEyebrowAttribute(?string $value): void
    {
        $this->attributes['eyebrow'] = $value !== null ? trim(strip_tags($value)) : null;
    }

    public function setBodyAttribute(?string $value): void
    {
        $this->attributes['body'] = $value !== null ? trim(strip_tags($value)) : null;
    }

    public function setBrandColorAttribute(?string $value): void
    {
        // Enforce allowlisted brand colors; fallback to primary dark if unrecognized
        $color = strtoupper(trim((string) $value));
        $this->attributes['brand_color'] = array_key_exists($color, self::ALLOWED_COLORS)
            ? $color
            : self::COLOR_PRIMARY_DARK;
    }

    /** Scope to visible items in sort order */
    public function scopeVisible($query)
    {
        return $query->where('is_visible', true)->orderBy('sort_order');
    }

    /** Scope by page */
    public function scopeForPage($query, string $page)
    {
        return $query->where('page', $page);
    }
}
