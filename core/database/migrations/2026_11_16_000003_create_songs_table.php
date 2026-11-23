<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Music catalog — songs, pricing, preview, download entitlements.
|
| Rules (from PRD/SECURITY):
| - Owner configures free/paid status and price (integer cents, USD).
| - Paid purchases create server-verified entitlements (see download_entitlements).
| - Audio files are stored outside public web root; never served from public/.
| - Preview URLs are short-lived signed links or controlled responses.
|--------------------------------------------------------------------------
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('songs', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_free')->default(false)
                ->comment('true = any authenticated user may download; false = paid entitlement required');
            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('price_cents')->nullable()
                ->comment('Price in USD cents. NULL or 0 when is_free=true. Set by owner only.');
            $table->string('preview_storage_path')->nullable()
                ->comment('Private storage path for the 30-60s preview clip; signed URL served');
            $table->string('download_storage_path_mp3')->nullable()
                ->comment('Private storage path for MP3 download; entitlement checked on every request');
            $table->string('download_storage_path_wav')->nullable()
                ->comment('Private storage path for WAV download; entitlement checked on every request');
            $table->boolean('has_mp3')->default(true);
            $table->boolean('has_wav')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('cover_storage_path')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('songs');
    }
};
