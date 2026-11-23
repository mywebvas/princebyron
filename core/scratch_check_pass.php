<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$fan = App\Models\User::where('email', 'fan@princebyron.local')->first();
echo "Fan password checks:\n";
echo "changeme-dev-only: " . (Illuminate\Support\Facades\Hash::check('changeme-dev-only', $fan->password) ? 'YES' : 'NO') . "\n";
echo "TemporaryFanPass2026!: " . (Illuminate\Support\Facades\Hash::check('TemporaryFanPass2026!', $fan->password) ? 'YES' : 'NO') . "\n";

$owner = App\Models\User::where('email', 'owner@princebyron.local')->first();
echo "Owner password checks:\n";
echo "changeme-dev-only: " . (Illuminate\Support\Facades\Hash::check('changeme-dev-only', $owner->password) ? 'YES' : 'NO') . "\n";
echo "TemporaryOwnerPass2026!: " . (Illuminate\Support\Facades\Hash::check('TemporaryOwnerPass2026!', $owner->password) ? 'YES' : 'NO') . "\n";
