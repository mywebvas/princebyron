<?php

namespace Database\Seeders;

use App\Models\Song;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Seeds the five approved songs from docs/ANTIGRAVITY-HANDOFF.md.
|
| Owner sets final free/paid status and prices before launch.
| Download/preview storage paths are placeholders — no real audio seeded.
| The owner states Prince Byron owns these songs.
|--------------------------------------------------------------------------
*/
class SongsSeeder extends Seeder
{
    public function run(): void
    {
        $songs = [
            [
                'title'       => 'Free',
                'description' => 'A gospel declaration of freedom in Christ.',
                'is_free'     => true,   // Owner configures; placeholder
                'price_cents' => null,
                'has_mp3'     => true,
                'has_wav'     => false,
            ],
            [
                'title'       => 'Purify Me',
                'description' => 'A heartfelt prayer for spiritual purification.',
                'is_free'     => false,
                'price_cents' => 199,    // $1.99 USD — editable starting price
                'has_mp3'     => true,
                'has_wav'     => false,
            ],
            [
                'title'       => 'I Always Win',
                'description' => 'A bold declaration of faith and victory.',
                'is_free'     => false,
                'price_cents' => 199,
                'has_mp3'     => true,
                'has_wav'     => false,
            ],
            [
                'title'       => 'Toduyun',
                'description' => 'An internationally inspired praise anthem.',
                'is_free'     => false,
                'price_cents' => 199,
                'has_mp3'     => true,
                'has_wav'     => false,
            ],
            [
                'title'       => 'Follow Me',
                'description' => 'An invitation to discipleship and ministry.',
                'is_free'     => false,
                'price_cents' => 199,
                'has_mp3'     => true,
                'has_wav'     => false,
            ],
        ];

        foreach ($songs as $i => $data) {
            Song::firstOrCreate(
                ['slug' => Str::slug($data['title'])],
                array_merge($data, [
                    'is_published' => true,
                    'sort_order'   => $i + 1,
                ])
            );
        }
    }
}
