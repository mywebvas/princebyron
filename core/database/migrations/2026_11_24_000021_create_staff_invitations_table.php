<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Staff invitations — multi-owner staff onboarding
|--------------------------------------------------------------------------
|
| Staff members (non-owner admins) are invited by an existing owner. Each
| invitation carries a single-use, unguessable token, a staff level, and an
| expiry. The invited email is the only way to claim the invitation — there
| is no public registration path to /admin.
|
| `level` and `permissions` are duplicated from the eventual user record at
| acceptance time so the invitation is self-documenting even if the user
| record is later edited.
|
| Security notes
| --------------
| - `token` is bin2hex(random_bytes(32)) — 256 bits of entropy, unguessable.
| - `expires_at` defaults to 7 days; stale invitations are never accepted.
| - `accepted_at` is set once, making the token one-time use.
| - `invited_by` is a foreign key to users so every invitation is attributable.
| - `email` is unique among pending invitations to prevent re-invitation spam.
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_invitations', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique()->index();
            $table->string('token', 64)->unique()->index();
            $table->string('level')->index(); // owner | manager | staff | viewer
            $table->json('permissions')->nullable();
            $table->foreignId('invited_by')->constrained('users')->onDelete('cascade');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_invitations');
    }
};
