<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Audit trail for consequential admin and payment/order status changes.
|
| Security requirements (ASVS 5.0 Level 2):
| - Record actor (admin user id), action, target type/id, payload (redacted
|   of secrets), and timestamp for every consequential change.
| - Do NOT log passwords, tokens, payment card data, or personal secrets.
| - Use this table for: admin edits, order/payment state changes, download
|   grant/revoke, booking status changes, installer events.
|--------------------------------------------------------------------------
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete()
                ->comment('Admin/owner who performed the action; null for system events');
            $table->string('action', 100)
                ->comment('e.g. order.paid, download.granted, catalog.price_changed');
            $table->string('auditable_type', 100)->nullable()
                ->comment('Model class name, e.g. App\\Models\\Order');
            $table->unsignedBigInteger('auditable_id')->nullable()
                ->comment('ID of the affected model');
            $table->jsonb('old_values')->nullable()
                ->comment('State before change — redact secrets before storing');
            $table->jsonb('new_values')->nullable()
                ->comment('State after change — redact secrets before storing');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('correlation_id', 64)->nullable()->index()
                ->comment('Ties related events together, e.g. a webhook + order update');
            $table->timestamps();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['actor_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
