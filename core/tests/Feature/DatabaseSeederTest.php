<?php

namespace Tests\Feature;

use App\Models\BookingRequest;
use App\Models\Product;
use App\Models\Song;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_expected_entities(): void
    {
        $this->seed(DatabaseSeeder::class);

        // 1. Users
        $owner = User::where('email', 'owner@princebyron.local')->first();
        $this->assertNotNull($owner);
        $this->assertTrue($owner->isOwner());

        $fan = User::where('email', 'fan@princebyron.local')->first();
        $this->assertNotNull($fan);
        $this->assertTrue($fan->isCustomer());

        // 2. The 5 approved songs
        $expectedSongs = ['Free', 'Purify Me', 'I Always Win', 'Toduyun', 'Follow Me'];
        foreach ($expectedSongs as $title) {
            $song = Song::where('title', $title)->first();
            $this->assertNotNull($song, "Song '{$title}' must be present in seeded catalog");
            $this->assertTrue($song->is_published);
        }

        // 'Free' song is free
        $freeSong = Song::where('title', 'Free')->first();
        $this->assertTrue($freeSong->is_free);
        $this->assertEquals(0, $freeSong->effectivePriceCents());

        // 3. Merchandise products
        $expectedProducts = [
            'Prince Byron T-Shirt',
            'Prince Byron Hoodie',
            'Prince Byron Cap',
            'Prince Byron Tote Bag',
            'Prince Byron Art Print',
            'Prince Byron Sticker Pack',
            'Fan Bundle',
            'Super Fan Bundle',
        ];
        foreach ($expectedProducts as $productName) {
            $product = Product::where('name', $productName)->first();
            $this->assertNotNull($product, "Product '{$productName}' must be present");
            $this->assertGreaterThan(0, $product->variants()->count(), "Product '{$productName}' must have variants");
        }

        // Verify starting demo prices (in USD cents)
        $tshirt = Product::where('name', 'Prince Byron T-Shirt')->first();
        $this->assertEquals(3200, $tshirt->variants()->first()->price_cents); // $32.00

        $hoodie = Product::where('name', 'Prince Byron Hoodie')->first();
        $this->assertEquals(5800, $hoodie->variants()->first()->price_cents); // $58.00

        $cap = Product::where('name', 'Prince Byron Cap')->first();
        $this->assertEquals(3000, $cap->variants()->first()->price_cents); // $30.00

        // 4. Sample booking request
        $booking = BookingRequest::where('reference', 'PB-BK-DEMO-001')->first();
        $this->assertNotNull($booking);
        $this->assertEquals(BookingRequest::STATUS_NEW, $booking->status);
    }
}
