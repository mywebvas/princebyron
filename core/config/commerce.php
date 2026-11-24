<?php

/*
|--------------------------------------------------------------------------
| Prince Byron — Commercial Policy
|--------------------------------------------------------------------------
|
| One place for the rules that decide what a customer may buy and what they
| actually receive. Today these values appear inline in Blade templates and
| in hardcoded controller fixtures, which is why the storefront cannot be
| wired to the database without touching every view.
|
| Review references: P0-3 (hardcoded catalog), P0-1 (client-side cart),
| P0-2 (simulated checkout), P2-11 (sample pricing presented as real).
|
| NOTE ON SAMPLE PRICING
| The "sample" prices are owner-approved placeholders, not final retail
| prices. While sample_pricing is true the storefront must say so on every
| page that shows a price, because taking real money against a placeholder
| price is a consumer-protection problem, not a design preference.
|
*/

return [

    /*
    |----------------------------------------------------------------------
    | Sample pricing disclosure
    |----------------------------------------------------------------------
    | Switch to false only when the owner has supplied final retail prices.
    */
    'sample_pricing' => (bool) env('COMMERCE_SAMPLE_PRICING', true),

    'sample_pricing_notice' => 'Sample demonstration prices — not final retail pricing.',

    /*
    |----------------------------------------------------------------------
    | Delivery formats
    |----------------------------------------------------------------------
    | `available` controls what a customer can actually select at checkout.
    | A 24-bit WAV master is only offerable when the owner has uploaded real
    | master files; until then the storefront must not promise them, because
    | the deliverable would not exist after payment.
    */
    'formats' => [
        'mp3' => [
            'label' => 'MP3 (320kbps)',
            'available' => true,
            'description' => 'High-quality MP3, plays on every device.',
        ],
        'wav' => [
            'label' => '24-bit Studio WAV',
            'available' => (bool) env('COMMERCE_WAV_AVAILABLE', false),
            'description' => 'Uncompressed 24-bit master for studio and archival use.',
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | Digital catalog
    |----------------------------------------------------------------------
    | Temporary source of truth until the database-backed catalog lands
    | (Sprint 1, review P0-3). Prices are integer cents throughout the
    | codebase — never floats — so currency maths stays exact.
    */
    'catalog' => [
        'album' => [
            'sku' => 'album-collection',
            'title' => 'Faith for the In-Between (Digital Collection)',
            'price_cents' => 1200,
            'track_count' => 5,
            'includes' => ['high-res digital booklet', '5 tracks'],
        ],
        'currency' => [
            'code' => 'USD',
            'symbol' => '$',
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | Fulfilment guarantees
    |----------------------------------------------------------------------
    | Only claim what the code can actually do. Instant delivery requires the
    | entitlement + vault download path to be live for the payment method in
    | use; see review P1-4 (bank transfer bypasses entitlement creation).
    */
    'fulfilment' => [
        'instant_download' => true,
        'manual_review_methods' => ['bank_transfer'],
    ],
];
