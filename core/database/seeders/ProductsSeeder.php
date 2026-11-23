<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Seeds the approved demo merchandise.
|
| Starting prices (USD cents) from docs/ANTIGRAVITY-HANDOFF.md.
| All prices are editable by the owner — these are initial values only.
| Variants and stock are placeholders; owner configures before launch.
|--------------------------------------------------------------------------
*/
class ProductsSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'name' => 'Prince Byron T-Shirt',
                'type' => 'physical',
                'description' => 'Premium quality gospel-inspired tee.',
                'variants' => [
                    // size/color matrix — owner configures exact options
                    ['label' => 'Black / S',  'price_cents' => 3200, 'color' => 'Black', 'size' => 'S'],
                    ['label' => 'Black / M',  'price_cents' => 3200, 'color' => 'Black', 'size' => 'M'],
                    ['label' => 'Black / L',  'price_cents' => 3200, 'color' => 'Black', 'size' => 'L'],
                    ['label' => 'Black / XL', 'price_cents' => 3200, 'color' => 'Black', 'size' => 'XL'],
                    ['label' => 'White / S',  'price_cents' => 3200, 'color' => 'White', 'size' => 'S'],
                    ['label' => 'White / M',  'price_cents' => 3200, 'color' => 'White', 'size' => 'M'],
                    ['label' => 'White / L',  'price_cents' => 3200, 'color' => 'White', 'size' => 'L'],
                    ['label' => 'White / XL', 'price_cents' => 3200, 'color' => 'White', 'size' => 'XL'],
                ],
            ],
            [
                'name' => 'Prince Byron Hoodie',
                'type' => 'physical',
                'description' => 'Heavyweight hoodie, ideal for cooler weather.',
                'variants' => [
                    ['label' => 'Black / S',  'price_cents' => 5800, 'color' => 'Black', 'size' => 'S'],
                    ['label' => 'Black / M',  'price_cents' => 5800, 'color' => 'Black', 'size' => 'M'],
                    ['label' => 'Black / L',  'price_cents' => 5800, 'color' => 'Black', 'size' => 'L'],
                    ['label' => 'Black / XL', 'price_cents' => 5800, 'color' => 'Black', 'size' => 'XL'],
                ],
            ],
            [
                'name' => 'Prince Byron Cap',
                'type' => 'physical',
                'description' => 'Embroidered signature cap, one size fits most.',
                'variants' => [
                    ['label' => 'Black / One Size', 'price_cents' => 3000, 'color' => 'Black', 'size' => 'One Size'],
                    ['label' => 'White / One Size', 'price_cents' => 3000, 'color' => 'White', 'size' => 'One Size'],
                ],
            ],
            [
                'name' => 'Prince Byron Tote Bag',
                'type' => 'physical',
                'description' => 'Canvas tote with gospel-inspired artwork.',
                'variants' => [
                    ['label' => 'Natural / One Size', 'price_cents' => 2500, 'color' => 'Natural', 'size' => 'One Size'],
                ],
            ],
            [
                'name' => 'Prince Byron Art Print',
                'type' => 'physical',
                'description' => 'High-quality art print, ready to frame.',
                'variants' => [
                    ['label' => '12×18 in', 'price_cents' => 2500, 'size' => '12x18'],
                ],
            ],
            [
                'name' => 'Prince Byron Sticker Pack',
                'type' => 'physical',
                'description' => 'Set of premium vinyl gospel stickers.',
                'variants' => [
                    ['label' => 'Standard Pack', 'price_cents' => 600],
                ],
            ],
            [
                'name' => 'Fan Bundle',
                'type' => 'bundle',
                'description' => 'T-Shirt + Sticker Pack — great value starter bundle.',
                'variants' => [
                    ['label' => 'Bundle', 'price_cents' => 3500],
                ],
            ],
            [
                'name' => 'Super Fan Bundle',
                'type' => 'bundle',
                'description' => 'Hoodie + Cap + Sticker Pack — the full experience.',
                'variants' => [
                    ['label' => 'Bundle', 'price_cents' => 8000],
                ],
            ],
        ];

        foreach ($products as $i => $data) {
            $variants = $data['variants'];
            unset($data['variants']);

            $product = Product::firstOrCreate(
                ['slug' => Str::slug($data['name'])],
                array_merge($data, [
                    'is_published' => true,
                    'sort_order'   => $i + 1,
                ])
            );

            foreach ($variants as $vData) {
                ProductVariant::firstOrCreate(
                    [
                        'product_id' => $product->id,
                        'label'      => $vData['label'],
                    ],
                    array_merge([
                        'is_active'      => true,
                        'stock_quantity' => -1, // unlimited until owner sets real stock
                    ], $vData)
                );
            }
        }
    }
}
