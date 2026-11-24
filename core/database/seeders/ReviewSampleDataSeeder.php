<?php

namespace Database\Seeders;

use App\Models\DownloadEntitlement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Song;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * DEMO DATA — never run this on a host that serves customers.
 *
 * It creates an owner account whose password is the literal string in this
 * file, plus a demo customer and sample orders. Anyone who can read the
 * repository can sign in as the owner afterwards. It is not called by
 * DatabaseSeeder; run it by class name on a development machine only.
 */
class ReviewSampleDataSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ensure Owner Account Exists
        $owner = User::updateOrCreate(
            ['email' => 'owner@princebyron.local'],
            [
                'name'              => 'Prince Byron (Owner)',
                'password'          => Hash::make('changeme-dev-only'),
                'role'              => User::ROLE_OWNER,
                'is_active'         => true,
                'email_verified_at' => now(),
            ]
        );

        // 2. Ensure Demo Customer Account Exists
        $fan = User::updateOrCreate(
            ['email' => 'fan@princebyron.local'],
            [
                'name'              => 'Demo Fan',
                'password'          => Hash::make('changeme-dev-only'),
                'role'              => User::ROLE_CUSTOMER,
                'is_active'         => true,
                'email_verified_at' => now(),
            ]
        );

        $purifyMeSong = Song::where('slug', 'purify-me')->first();
        $freeSong     = Song::where('slug', 'free')->first();
        $product      = Product::first();

        // 3. Sample Order 1: Paid digital release via Stripe Sandbox ($1.99)
        $order1 = Order::updateOrCreate(
            ['reference' => 'PB-202611-001'],
            [
                'user_id'         => $fan->id,
                'status'          => Order::STATUS_PAID,
                'currency'        => 'USD',
                'subtotal_cents'  => 199,
                'shipping_cents'  => 0,
                'tax_cents'       => 0,
                'total_cents'     => 199,
                'customer_name'   => 'Demo Fan',
                'customer_email'  => 'fan@princebyron.local',
                'notes'           => 'Simulated demo purchase via Stripe Sandbox',
                'created_at'      => now()->subDay(),
            ]
        );

        Payment::updateOrCreate(
            ['order_id' => $order1->id],
            [
                'provider'           => Payment::PROVIDER_STRIPE,
                'provider_reference' => 'ch_demo_stripe_purifyme_001',
                'status'             => Payment::STATUS_SUCCEEDED,
                'currency'           => 'USD',
                'amount_cents'       => 199,
                'signature_verified' => true,
                'provider_metadata'  => ['card_last4' => '4242', 'brand' => 'Visa', 'mode' => 'sandbox'],
            ]
        );

        if ($purifyMeSong) {
            OrderItem::updateOrCreate(
                ['order_id' => $order1->id, 'label' => 'Purify Me (Single Master WAV + MP3)'],
                [
                    'itemable_type'    => Song::class,
                    'itemable_id'      => $purifyMeSong->id,
                    'quantity'         => 1,
                    'unit_price_cents' => 199,
                    'line_total_cents' => 199,
                ]
            );

            DownloadEntitlement::updateOrCreate(
                ['user_id' => $fan->id, 'song_id' => $purifyMeSong->id],
                [
                    'order_id'           => $order1->id,
                    'format'             => DownloadEntitlement::FORMAT_WAV,
                    'is_active'          => true,
                    'download_count'     => 0,
                    'last_downloaded_at' => null,
                ]
            );
        }

        // 4. Sample Order 2: Awaiting Manual Bank Verification ($93.00)
        $order2 = Order::updateOrCreate(
            ['reference' => 'PB-202611-002'],
            [
                'user_id'                => $fan->id,
                'status'                 => Order::STATUS_AWAITING_BANK,
                'currency'               => 'USD',
                'subtotal_cents'         => 8500,
                'shipping_cents'         => 800,
                'tax_cents'              => 0,
                'total_cents'            => 9300,
                'shipping_name'          => 'Demo Fan',
                'shipping_address_line1' => '123 Faith Way',
                'shipping_city'          => 'Nashville',
                'shipping_state'         => 'TN',
                'shipping_postal_code'   => '37201',
                'shipping_country'       => 'US',
                'customer_name'          => 'Demo Fan',
                'customer_email'         => 'fan@princebyron.local',
                'notes'                  => 'Awaiting independent owner bank verification',
                'created_at'             => now()->subHours(6),
            ]
        );

        Payment::updateOrCreate(
            ['order_id' => $order2->id],
            [
                'provider'           => Payment::PROVIDER_BANK,
                'provider_reference' => 'BANK-DEMO-REF-9300',
                'status'             => Payment::STATUS_AWAITING_VERIFICATION,
                'currency'           => 'USD',
                'amount_cents'       => 9300,
                'signature_verified' => false,
                'provider_metadata'  => ['bank_name' => 'Demo Gospel Fellowship Bank', 'mode' => 'simulated'],
            ]
        );

        if ($product) {
            OrderItem::updateOrCreate(
                ['order_id' => $order2->id, 'label' => $product->name],
                [
                    'itemable_type'    => Product::class,
                    'itemable_id'      => $product->id,
                    'quantity'         => 2,
                    'unit_price_cents' => 4250,
                    'line_total_cents' => 8500,
                ]
            );
        }

        // 5. Free Track Order & Download Entitlement ("Free" - $0.00 Ministry Gift)
        if ($freeSong) {
            $freeOrder = Order::updateOrCreate(
                ['reference' => 'PB-202611-000'],
                [
                    'user_id'         => $fan->id,
                    'status'          => Order::STATUS_PAID,
                    'currency'        => 'USD',
                    'subtotal_cents'  => 0,
                    'shipping_cents'  => 0,
                    'tax_cents'       => 0,
                    'total_cents'     => 0,
                    'customer_name'   => 'Demo Fan',
                    'customer_email'  => 'fan@princebyron.local',
                    'notes'           => 'Free Ministry Offering ($0.00)',
                    'created_at'      => now()->subDays(2),
                ]
            );

            OrderItem::updateOrCreate(
                ['order_id' => $freeOrder->id, 'label' => 'Free (Lead Ministry Offering)'],
                [
                    'itemable_type'    => Song::class,
                    'itemable_id'      => $freeSong->id,
                    'quantity'         => 1,
                    'unit_price_cents' => 0,
                    'line_total_cents' => 0,
                ]
            );

            DownloadEntitlement::updateOrCreate(
                [
                    'user_id'  => $fan->id,
                    'order_id' => $freeOrder->id,
                    'song_id'  => $freeSong->id,
                    'format'   => DownloadEntitlement::FORMAT_MP3,
                ],
                [
                    'is_active'          => true,
                    'download_count'     => 2,
                    'last_downloaded_at' => now()->subHours(3),
                ]
            );
        }
    }
}
