<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/*
|--------------------------------------------------------------------------
| Seed data — the catalogue a real site needs to function.
|
| This entry point is what the installer and `php artisan db:seed --force`
| run, so it MUST be safe to run on a live host. It creates:
|   - The 5 approved songs with placeholder pricing
|   - The approved merchandise with editable starting prices
|   - 1 sample pending booking request
|   - The content sections, including the four legal policy rows
|   - Site settings
|
| It deliberately does NOT create any account. Accounts carry passwords, and a
| seeder cannot know the owner's. The installer creates the owner with the
| password the owner typed; nothing else creates one.
|
| Demo data — a demo customer, a known-password owner, sample orders — lives in
| ReviewSampleDataSeeder, which is NOT called here. Run it explicitly:
|     php artisan db:seed --class='Database\Seeders\UsersSeeder'
|     php artisan db:seed --class='Database\Seeders\ReviewSampleDataSeeder'
| and never on a host that serves customers.
|
| Rules:
|   - All prices are starting values only — owner edits them before launch.
|   - No real audio files are seeded; storage paths are placeholders.
|   - All seeders are idempotent (firstOrCreate/updateOrCreate), so re-running
|     this after a deploy will not duplicate or overwrite the owner's edits.
|--------------------------------------------------------------------------
*/
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SongsSeeder::class,
            ProductsSeeder::class,
            BookingSeeder::class,
            ContentSectionsSeeder::class,
            SettingsSeeder::class,
        ]);
    }
}
