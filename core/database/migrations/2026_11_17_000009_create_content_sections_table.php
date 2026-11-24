<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Content sections — owner-managed structured page copy and imagery.
|
| Rules (PRD/SECURITY):
| - Content editors accept structured fields only (eyebrow, title, body, cta).
| - Never execute user-controlled markup, inline CSS, or PHP.
| - Brand colors must be strictly selected from the allowlisted palette.
|--------------------------------------------------------------------------
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_sections', function (Blueprint $table) {
            $table->id();
            $table->string('section_key', 60)->unique()
                ->comment('Unique identifier, e.g. home_hero, about_bio, booking_info');
            $table->string('page', 40)->default('home')
                ->comment('Page namespace: home | about | music | store | booking');
            $table->string('title');
            $table->string('eyebrow', 100)->nullable();
            $table->text('body')->nullable()
                ->comment('Structured plain text or safe paragraphs; sanitized on write');
            $table->string('button_text', 100)->nullable();
            $table->string('button_url', 255)->nullable();
            $table->string('image_path', 500)->nullable();
            $table->string('brand_color', 20)->default('#0E0B09')
                ->comment('Allowlisted brand hex code');
            $table->boolean('is_visible')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('metadata')->nullable()
                ->comment('Structured attributes like quote attribution, scripture ref, etc.');
            $table->timestamps();

            $table->index(['page', 'sort_order']);
            $table->index('is_visible');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_sections');
    }
};
