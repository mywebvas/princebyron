<?php

/*
|--------------------------------------------------------------------------
| Presentation catalogue for an un-seeded install
|--------------------------------------------------------------------------
|
| These entries appear on the storefront ONLY when the database has no
| published products or songs yet — i.e. the moment between uploading the
| package and the owner entering real inventory.
|
| They are explicitly NOT purchasable. Every entry renders with
| `purchasable => false`, the storefront hides the "add to bag" action, and
| the server-side cart rejects invented ids. Nothing here can become an order.
|
| Once the owner seeds (php artisan db:seed) or adds products in the admin
| panel, the database takes over and this file stops being used.
|
| Prices below are the approved sample figures and are labelled as samples by
| config/commerce.php.
|
*/

return [

    'products' => [
        [
            'slug' => 'prince-byron-t-shirt',
            'title' => 'Prince Byron Signature Tee',
            'category' => 'Apparel',
            'priceCents' => 3200,
            'sku' => 'PB-TEE-001',
            'image' => 'images/prince-byron-hero.jpeg',
            'desc' => 'Premium quality gospel-inspired tee. Sample catalogue item — the owner has not published inventory yet.',
            'colors' => ['Sample Colour'],
            'sizes' => ['Sample Size'],
        ],
        [
            'slug' => 'prince-byron-hoodie',
            'title' => 'Abide Arch Pullover Hoodie',
            'category' => 'Apparel',
            'priceCents' => 5800,
            'sku' => 'PB-HD-002',
            'image' => 'images/prince-byron-hero.jpeg',
            'desc' => 'Heavyweight brushed fleece hoodie. Sample catalogue item.',
            'colors' => ['Sample Colour'],
            'sizes' => ['Sample Size'],
        ],
        [
            'slug' => 'prince-byron-cap',
            'title' => 'PB Monogram Curved Brim Cap',
            'category' => 'Accessories',
            'priceCents' => 3000,
            'sku' => 'PB-CAP-003',
            'image' => 'images/prince-byron-hero.jpeg',
            'desc' => 'Embroidered signature cap. Sample catalogue item.',
            'colors' => ['Sample Colour'],
            'sizes' => ['One Size'],
        ],
        [
            'slug' => 'prince-byron-tote-bag',
            'title' => 'Carry Hope Heavyweight Canvas Tote',
            'category' => 'Accessories',
            'priceCents' => 2500,
            'sku' => 'PB-TOTE-004',
            'image' => 'images/prince-byron-hero.jpeg',
            'desc' => 'Canvas tote with gospel-inspired artwork. Sample catalogue item.',
            'colors' => ['Natural'],
            'sizes' => ['One Size'],
        ],
        [
            'slug' => 'prince-byron-art-print',
            'title' => 'Faith in the In-Between Art Print',
            'category' => 'Music Art',
            'priceCents' => 3500,
            'sku' => 'PB-ART-005',
            'image' => 'images/music/faith-for-the-in-between-album.jpg',
            'desc' => 'Archival art print from the album artwork. Sample catalogue item.',
            'colors' => ['Natural'],
            'sizes' => ['12x18 in'],
        ],
    ],

    'songs' => [
        [
            'id' => 'free',
            'title' => 'Free',
            'subtitle' => 'Lead single',
            'note' => 'Sample catalogue item.',
            'priceCents' => 0,
            'isFree' => true,
        ],
        [
            'id' => 'purify-me',
            'title' => 'Purify Me',
            'subtitle' => 'Worship single',
            'note' => 'Sample catalogue item.',
            'priceCents' => 199,
            'isFree' => false,
        ],
        [
            'id' => 'i-always-win',
            'title' => 'I Always Win',
            'subtitle' => 'Praise anthem',
            'note' => 'Sample catalogue item.',
            'priceCents' => 199,
            'isFree' => false,
        ],
        [
            'id' => 'toduyun',
            'title' => 'Toduyun',
            'subtitle' => 'Afro-gospel celebration',
            'note' => 'Sample catalogue item.',
            'priceCents' => 199,
            'isFree' => false,
        ],
        [
            'id' => 'follow-me',
            'title' => 'Follow Me',
            'subtitle' => 'Soulful discipleship',
            'note' => 'Sample catalogue item.',
            'priceCents' => 199,
            'isFree' => false,
        ],
    ],
];
