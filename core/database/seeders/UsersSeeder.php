<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        // LOCAL DEV ONLY — this file is not called by DatabaseSeeder.
        //
        // It exists so a developer can log in immediately. It is not safe on a
        // host that serves customers: the password below is in the repository,
        // so anyone who can read the source can sign in as the owner. The
        // installer creates the real owner account with a password the owner
        // chooses; there is no production path through this file.
        //
        // firstOrCreate (not updateOrCreate) on purpose: running it twice, or
        // after the owner has changed their password, must not reset it.
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
