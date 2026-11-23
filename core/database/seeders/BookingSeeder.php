<?php

namespace Database\Seeders;

use App\Models\BookingRequest;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BookingSeeder extends Seeder
{
    public function run(): void
    {
        BookingRequest::firstOrCreate(
            ['reference' => 'PB-BK-DEMO-001'],
            [
                'contact_name'      => 'Pastor James Sample',
                'contact_email'     => 'pastor@demo-church.local',
                'contact_phone'     => '+1 555 000 0001',
                'organisation'      => 'Demo Community Church',
                'event_type'        => 'church service',
                'event_name'        => 'Annual Gospel Sunday',
                'event_date'        => now()->addMonths(3)->toDateString(),
                'event_city'        => 'Atlanta',
                'event_state'       => 'GA',
                'event_country'     => 'US',
                'expected_audience' => 350,
                'event_description' => 'Full Sunday morning service with praise and worship, 90 minutes.',
                'requirements'      => 'Full PA system provided. Requesting 2 backing tracks.',
                'status'            => BookingRequest::STATUS_NEW,
            ]
        );
    }
}
