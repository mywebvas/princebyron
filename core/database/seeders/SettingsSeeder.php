<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Models\ShippingRate;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            [
                'group'     => 'general',
                'key'       => 'site_name',
                'value'     => 'Prince Byron Official Platform',
                'type'      => Setting::TYPE_STRING,
                'label'     => 'Site Name',
                'is_public' => true,
            ],
            [
                'group'     => 'general',
                'key'       => 'contact_email',
                'value'     => 'booking@princebyron.local',
                'type'      => Setting::TYPE_STRING,
                'label'     => 'Contact / Ministry Inquiries Email',
                'is_public' => true,
            ],
            [
                'group'     => 'payment',
                'key'       => 'bank_transfer_enabled',
                'value'     => '1',
                'type'      => Setting::TYPE_BOOLEAN,
                'label'     => 'Enable Manual Bank Transfer Option',
                'is_public' => true,
            ],
            [
                'group'     => 'payment',
                'key'       => 'bank_instructions',
                'value'     => 'Bank: First Devotion Bank | Account Name: Prince Byron Ministry | Swift: PBYRUS33 | Reference: Use Your Order Reference PB-XXXX',
                'type'      => Setting::TYPE_STRING,
                'label'     => 'Bank Wire / ACH Instructions',
                'is_public' => false,
            ],
            [
                'group'     => 'commerce',
                'key'       => 'currency',
                'value'     => 'USD',
                'type'      => Setting::TYPE_STRING,
                'label'     => 'Locked Base Currency',
                'is_public' => true,
            ],
        ];

        foreach ($settings as $item) {
            Setting::updateOrCreate(
                ['group' => $item['group'], 'key' => $item['key']],
                $item
            );
        }

        // Demo shipping rates — marked INACTIVE until owner configures live rates & destinations
        $rates = [
            [
                'name'             => '[DEMO ONLY - INACTIVE] US Domestic Standard Shipping',
                'country_code'     => 'US',
                'rate_cents'       => 500, // $5.00 USD
                'free_above_cents' => 5000, // Free above $50.00 USD
                'is_active'        => false,
                'sort_order'       => 1,
            ],
            [
                'name'             => '[DEMO ONLY - INACTIVE] International Standard Shipping',
                'country_code'     => null, // Wildcard worldwide
                'rate_cents'       => 1500, // $15.00 USD
                'free_above_cents' => 10000, // Free above $100.00 USD
                'is_active'        => false,
                'sort_order'       => 2,
            ],
        ];

        foreach ($rates as $rate) {
            ShippingRate::updateOrCreate(
                ['name' => $rate['name']],
                $rate
            );
        }
    }
}
