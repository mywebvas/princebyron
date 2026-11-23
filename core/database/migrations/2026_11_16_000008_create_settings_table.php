<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Site settings — owner-managed key/value configuration.
|
| Rules (PRD/SECURITY):
| - Values are structured; owner uses a controlled admin form.
| - No arbitrary PHP/HTML/CSS execution allowed in content fields.
| - Sensitive values (e.g. bank instructions) are cast to encrypted strings.
|--------------------------------------------------------------------------
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 60)->default('general')
                ->comment('Namespace: general | payment | shipping | social | appearance');
            $table->string('key', 100);
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string')
                ->comment('string|boolean|integer|json|encrypted — drives casting and form type');
            $table->string('label')->nullable()
                ->comment('Human label for admin UI');
            $table->boolean('is_public')->default(false)
                ->comment('true = may be exposed to public pages via config helper');
            $table->timestamps();

            $table->unique(['group', 'key']);
        });

        // Shipping zones and rates — owner-managed
        Schema::create('shipping_rates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('country_code', 2)->nullable()
                ->comment('ISO 3166-1 alpha-2; NULL = any country (wildcard)');
            $table->unsignedInteger('rate_cents')
                ->comment('Flat shipping rate in USD cents. No browser calculation trusted.');
            $table->unsignedInteger('free_above_cents')->nullable()
                ->comment('Free shipping when order subtotal >= this value. NULL = never free.');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['country_code', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_rates');
        Schema::dropIfExists('settings');
    }
};
