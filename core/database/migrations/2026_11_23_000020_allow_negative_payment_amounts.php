<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Allow negative payment amounts (refunds)
|--------------------------------------------------------------------------
|
| `payments.amount_cents` was unsigned, so a refund could not be recorded as a
| negative amount. The refund action therefore had only two options: record a
| positive amount indistinguishable from an incoming payment, or invent a
| separate "refunds" table that duplicates every column.
|
| Signed amounts are the normal representation for a payments ledger: the sum
| of a row's amounts is the net position. Recording a refund as a positive
| amount would make revenue reporting overstate income by the refund value.
|
| Guarded for both engines: Laravel's `change()` needs doctrine/dbal on older
| versions but is native for these column types on current Laravel.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->bigInteger('amount_cents')->change();
        });
    }

    public function down(): void
    {
        // Reverting is only sensible if no negative rows exist. Rather than
        // silently corrupt data, refuse when refunds are present.
        $hasNegative = \Illuminate\Support\Facades\DB::table('payments')
            ->where('amount_cents', '<', 0)
            ->exists();

        if ($hasNegative) {
            throw new \RuntimeException(
                'Cannot revert: refund rows with negative amounts exist. '
                .'Resolve or archive them first.'
            );
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('amount_cents')->change();
        });
    }
};
