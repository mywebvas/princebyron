<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Booking requests — quote-based appearance/ministry enquiries.
|
| Rules (PRD/ARCHITECTURE):
| - No public booking fee is ever exposed.
| - All booking requests are quote-only; owner responds with a quotation.
| - States: new → reviewing → quoted → accepted | declined | cancelled
| - Owner is notified of new submissions; customer receives acknowledgement.
| - Contact details are personal data; minimise collection and define
|   retention policy before launch.
|--------------------------------------------------------------------------
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32)->unique();

            // Submitter details
            $table->string('contact_name');
            $table->string('contact_email');
            $table->string('contact_phone', 30)->nullable();
            $table->string('organisation')->nullable();

            // Event details
            $table->string('event_type', 100)
                ->comment('e.g. church service, conference, concert, special occasion');
            $table->string('event_name')->nullable();
            $table->date('event_date')->nullable();
            $table->string('event_city')->nullable();
            $table->string('event_state', 10)->nullable();
            $table->string('event_country', 2)->default('US');
            $table->unsignedInteger('expected_audience')->nullable();
            $table->text('event_description')->nullable();
            $table->text('requirements')->nullable()
                ->comment('Technical rider, AV, travel needs etc.');

            // Workflow state
            $table->string('status', 30)->default('new')
                ->comment('new|reviewing|quoted|accepted|declined|cancelled');
            $table->text('owner_notes')->nullable()
                ->comment('Internal; never shown to submitter');
            $table->timestamp('owner_reviewed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at']);
            $table->index('event_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_requests');
    }
};
