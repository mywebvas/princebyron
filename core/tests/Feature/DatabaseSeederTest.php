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

        // 1. NO ACCOUNTS.
        //
        // DatabaseSeeder is what the installer and `db:seed --force` run on a
        // live host. A seeder cannot know the owner's password, so any account
        // it creates necessarily has a password that is in the repository —
        // which means anyone who can read the source can sign in as the owner.
        // The installer creates the owner with the password the owner typed.
        //
        // This assertion is the guard: re-adding UsersSeeder to DatabaseSeeder
        // fails the suite rather than quietly shipping a known owner password.
        $this->assertSame(
            0,
            User::count(),
            'DatabaseSeeder must not create accounts — the installer owns that, with the owner\'s password.'
        );

        // The demo seeders must still exist and still be runnable by name, so
        // a developer can log in locally; they are simply not on the production path.
        $this->assertTrue(class_exists(\Database\Seeders\UsersSeeder::class));
        $this->assertTrue(class_exists(\Database\Seeders\ReviewSampleDataSeeder::class));

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
