<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Staff fields on users — multi-owner staff with levels and permissions
|--------------------------------------------------------------------------
|
| Existing users keep `role` = 'owner' or 'customer'. Staff members are
| created via invitation and get `role` = 'staff' plus:
|
| - `staff_level`    — owner | manager | staff | viewer (defaults to 'viewer')
| - `permissions`    — JSON array of granular permission slugs
| - `invited_by`     — FK to the user who invited them
| - `invited_at`     — when the invitation was accepted
|
| Authorization is level-first, permission-second: a viewer can never
#   perform a mutation even if they somehow hold a write permission slug,
|   and a manager can never demote an owner. Every admin action is checked
|   server-side via User::can() — never trusted from the client.
|
| `permissions` is nullable because owners use role-based access
| (canAccessPanel returns true for any owner) and don't need a permission
| list. Staff members always have a non-null permissions list.
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('staff_level')->nullable()
                ->after('role')
                ->index();
            $table->json('permissions')->nullable()
                ->after('staff_level');
            $table->foreignId('invited_by')->nullable()
                ->after('permissions')
                ->constrained('users')
                ->onDelete('set null');
            $table->timestamp('invited_at')->nullable()
                ->after('invited_by');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['invited_by']);
            $table->dropColumn([
                'invited_at',
                'invited_by',
                'permissions',
                'staff_level',
            ]);
        });
    }
};
