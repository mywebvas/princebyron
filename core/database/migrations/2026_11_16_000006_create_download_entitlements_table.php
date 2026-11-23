<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Download entitlements for music purchases.
|
| Security requirements (PRD/SECURITY):
| - An entitlement is created server-side only after a verified paid order.
| - Manual bank transfers do NOT create entitlements until owner confirms receipt.
| - Refunds/reversals suspend is_active immediately.
| - Every download request checks: authenticated, entitlement active, not expired.
| - Use short-lived signed tokens (not permanent public URLs) for delivery.
| - Log every download attempt (success + blocked) with correlation ID.
| - Revocation on refund is enforced: set is_active=false and log the event.
|--------------------------------------------------------------------------
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('download_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('song_id')->constrained()->cascadeOnDelete();
            $table->string('format', 10)
                ->comment('mp3 | wav');
            $table->boolean('is_active')->default(true)
                ->comment('false after refund/reversal; check on every download request');
            $table->timestamp('expires_at')->nullable()
                ->comment('NULL = no expiry. If set, download link must be regenerated after this date.');
            $table->unsignedInteger('download_count')->default(0)
                ->comment('Informational; not a hard limit by default');
            $table->timestamp('last_downloaded_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'order_id', 'song_id', 'format'], 'entitlement_unique');
            $table->index(['user_id', 'is_active']);
            $table->index(['song_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('download_entitlements');
    }
};
