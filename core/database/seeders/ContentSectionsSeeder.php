<?php

namespace Database\Seeders;

use App\Models\ContentSection;
use Illuminate\Database\Seeder;

class ContentSectionsSeeder extends Seeder
{
    public function run(): void
    {
        $sections = [
            [
                'section_key' => 'home_hero',
                'page'        => 'home',
                'eyebrow'     => 'Gospel Recording Artist & Worship Leader',
                'title'       => 'Sacred Sound. Unwavering Devotion.',
                'body'        => 'Experience gospel music crafted for intimate prayer, congregational worship, and reflective devotion. Featuring the latest single "Purify Me".',
                'button_text' => 'Listen Now',
                'button_url'  => '/music',
                'image_path'  => '/assets/hero-byron.jpg',
                'brand_color' => ContentSection::COLOR_PRIMARY_DARK,
                'is_visible'  => true,
                'sort_order'  => 1,
                'metadata'    => ['theme' => 'featured_release'],
            ],
            [
                'section_key' => 'home_mission',
                'page'        => 'home',
                'eyebrow'     => 'Ministry Calling',
                'title'       => 'Rooted in Reverence, Driven by Grace',
                'body'        => 'Prince Byron\'s music bridges heartfelt gospel acoustics with modern atmospheric worship, inviting listeners everywhere into transformative encounters with God.',
                'button_text' => 'Read Full Story',
                'button_url'  => '/about',
                'image_path'  => null,
                'brand_color' => ContentSection::COLOR_SURFACE_DARK,
                'is_visible'  => true,
                'sort_order'  => 2,
                'metadata'    => ['scripture_reference' => 'Psalm 51:10'],
            ],
            [
                'section_key' => 'about_bio',
                'page'        => 'about',
                'eyebrow'     => 'Biography & Heart',
                'title'       => 'The Journey of Worship and Service',
                'body'        => 'From church choirs to recording studio sessions, Prince Byron has dedicated his voice to uplifting communities and proclaiming Christ through song.',
                'button_text' => 'Book Ministry',
                'button_url'  => '/booking',
                'image_path'  => '/assets/byron-about.jpg',
                'brand_color' => ContentSection::COLOR_PRIMARY_DARK,
                'is_visible'  => true,
                'sort_order'  => 1,
                'metadata'    => [],
            ],
            [
                'section_key' => 'booking_banner',
                'page'        => 'booking',
                'eyebrow'     => 'Live Ministry & Conferences',
                'title'       => 'Bring Prince Byron to Your Next Worship Event',
                'body'        => 'Now accepting ministry inquiries for church conferences, worship retreats, and special concerts. Submit your request for availability and details.',
                'button_text' => 'Submit Request Below',
                'button_url'  => '#booking-form',
                'image_path'  => null,
                'brand_color' => ContentSection::COLOR_TERRACOTTA,
                'is_visible'  => true,
                'sort_order'  => 1,
                'metadata'    => [],
            ],
        ];

        /*
         | Legal pages (Phase 6).
         |
         | These are seeded as ROWS WITH AN EMPTY BODY, not with invented text.
         | App\Support\LegalPage renders a clearly-marked template and serves the
         | page `noindex` until the owner fills the body in, and `pb:readiness`
         | reports them as blockers. Seeding invented policy would be the single
         | most dangerous thing this file could do: a customer could rely on it.
         |
         | is_visible => true so the footer links resolve immediately; deleting or
         | hiding the row returns a 404 rather than an empty shell.
         */
        foreach (\App\Support\LegalPage::PAGES as $slug => $definition) {
            $sections[] = [
                'section_key' => $definition['key'],
                'page'        => 'legal',
                'eyebrow'     => 'Policies & Terms',
                'title'       => $definition['title'],
                'body'        => '',
                'button_text' => null,
                'button_url'  => null,
                'image_path'  => null,
                'brand_color' => ContentSection::COLOR_PRIMARY_DARK,
                'is_visible'  => true,
                'sort_order'  => 0,
                'metadata'    => ['legally_reviewed' => false],
            ];
        }

        foreach ($sections as $section) {
            ContentSection::updateOrCreate(
                ['section_key' => $section['section_key']],
                $section
            );
        }
    }
}
