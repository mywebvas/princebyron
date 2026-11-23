<?php

namespace Database\Seeders;

use App\Models\BookingRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Song;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Demo seed data — local development only.
|
| This seed creates:
|   - 1 owner account (password changeable via admin)
|   - 1 demo customer account
|   - The 5 approved songs with placeholder pricing
|   - The approved demo merchandise with editable starting prices
|   - 1 sample pending booking request
|
| Rules:
|   - All prices are starting values only — owner edits them before launch.
|   - No real audio files are seeded; storage paths are placeholders.
|   - Owner credentials here are LOCAL DEV ONLY — never commit real credentials.
|   - Running php artisan db:seed in production requires explicit owner action.
|--------------------------------------------------------------------------
*/
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UsersSeeder::class,
            SongsSeeder::class,
            ProductsSeeder::class,
            BookingSeeder::class,
            ContentSectionsSeeder::class,
            SettingsSeeder::class,
            ReviewSampleDataSeeder::class,
        ]);
    }
}
