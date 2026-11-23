<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Merchandise catalog — products, variants, inventory.
|
| Rules (from PRD/SECURITY/ARCHITECTURE):
| - Prices are integer cents, USD. Never trusted from browser.
| - Stock is decremented server-side at checkout, never by client.
| - Owner manages inventory and shipping rates.
| - Initial demo prices: T-shirt $3200, hoodie $5800, cap $3000,
|   tote $2500, art print $2500, sticker pack $600.
|   All prices are editable; these are starting values only.
|--------------------------------------------------------------------------
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('type', 50)->default('physical')
                ->comment('physical | bundle');
            $table->boolean('is_published')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 100)->unique()->nullable();
            $table->string('label')
                ->comment('e.g. "Black / M", "One Size", "12x18"');
            $table->unsignedInteger('price_cents')
                ->comment('Price in USD cents. Set and validated server-side only.');
            $table->integer('stock_quantity')->default(0)
                ->comment('Negative = unlimited. Zero = out of stock. Positive = tracked.');
            $table->boolean('is_active')->default(true);
            $table->string('color', 50)->nullable();
            $table->string('size', 50)->nullable();
            $table->timestamps();

            $table->index(['product_id', 'is_active']);
        });

        // Product images (multiple per product, ordered)
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('storage_path');
            $table->string('alt_text')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
    }
};
