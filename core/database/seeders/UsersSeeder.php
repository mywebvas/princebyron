<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        // Owner account — LOCAL DEV ONLY.
        // Change password immediately after first login; never use this in production.
        User::firstOrCreate(
            ['email' => 'owner@princebyron.local'],
            [
                'name'              => 'Prince Byron (Owner)',
                'password'          => Hash::make('changeme-dev-only'),
                'role'              => User::ROLE_OWNER,
                'is_active'         => true,
                'email_verified_at' => now(),
            ]
        );

        // Demo customer account
        User::firstOrCreate(
            ['email' => 'fan@princebyron.local'],
            [
                'name'              => 'Demo Fan',
                'password'          => Hash::make('changeme-dev-only'),
                'role'              => User::ROLE_CUSTOMER,
                'is_active'         => true,
                'email_verified_at' => now(),
            ]
        );
    }
}
