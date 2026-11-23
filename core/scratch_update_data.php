<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\BookingRequest;
use Illuminate\Support\Facades\Hash;

// 1. Update Booking Request 4 to clearly be demo
$b = BookingRequest::find(4);
if ($b) {
    $b->update([
        'reference'     => 'PB-BK-DEMO-002',
        'organisation'  => '[DEMO FIXTURE] Sample Ministry Fellowship',
        'contact_name'  => 'Demo Ministry Leader (Test)',
        'contact_email' => 'demo-booking@princebyron.local',
        'owner_notes'   => 'Automated UI test booking. Demo fixture only — not a real ministry invitation.',
    ]);
    echo "Updated Booking 4 to demo fixture.\n";
}

// 2. Set Fan password to TemporaryFanPass2026!
$fan = User::where('email', 'fan@princebyron.local')->first();
if ($fan) {
    $fan->update(['password' => Hash::make('TemporaryFanPass2026!')]);
    echo "Set fan@princebyron.local password to TemporaryFanPass2026!\n";
}

// 3. Set Owner password to TemporaryOwnerPass2026!
$owner = User::where('email', 'owner@princebyron.local')->first();
if ($owner) {
    $owner->update(['password' => Hash::make('TemporaryOwnerPass2026!')]);
    echo "Set owner@princebyron.local password to TemporaryOwnerPass2026!\n";
}

echo "Done!\n";
