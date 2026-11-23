<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Reconfigure the default users table for Prince Byron customer accounts.
|
| Changes from Laravel default:
| - Add: role (owner | customer), email_verified_at, phone, is_active, last_login_at
| - Keep: id, name, email, password, remember_token, timestamps
|
| All roles are enforced server-side. The 'owner' role grants admin access.
| Authorization policies check role on every protected action.
|--------------------------------------------------------------------------
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('customer')->after('email')
                ->comment('customer | owner — enforced by server-side policies');
            $table->boolean('is_active')->default(true)->after('role');
            $table->string('phone', 30)->nullable()->after('is_active');
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'is_active', 'phone', 'last_login_at']);
        });
    }
};
