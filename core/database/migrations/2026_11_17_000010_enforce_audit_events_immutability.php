<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Database-level append-only enforcement for audit_events (ASVS 5.0 Level 2)
|--------------------------------------------------------------------------
| Ensures that even raw SQL or direct DB queries cannot UPDATE or DELETE
| existing audit trail records across PostgreSQL and test environments.
|--------------------------------------------------------------------------
*/
return new class extends Migration
{
    public function up(): void
    {
        // Enforce trigger on PostgreSQL (Production / Container runtime)
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<SQL
                CREATE OR REPLACE FUNCTION prevent_audit_events_mutation()
                RETURNS TRIGGER AS $$
                BEGIN
                    RAISE EXCEPTION 'Audit events table is strictly append-only. Updates and deletes are forbidden at the database layer.';
                END;
                $$ LANGUAGE plpgsql;

                DROP TRIGGER IF EXISTS trg_audit_events_immutable ON audit_events;
                CREATE TRIGGER trg_audit_events_immutable
                BEFORE UPDATE OR DELETE ON audit_events
                FOR EACH ROW
                EXECUTE FUNCTION prevent_audit_events_mutation();
            SQL);
        }

        // Enforce trigger on SQLite (Local in-memory testing harness)
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared(<<<SQL
                CREATE TRIGGER IF NOT EXISTS trg_audit_events_immutable_update
                BEFORE UPDATE ON audit_events
                BEGIN
                    SELECT RAISE(ABORT, 'Audit events table is strictly append-only. Updates are forbidden at the database layer.');
                END;

                CREATE TRIGGER IF NOT EXISTS trg_audit_events_immutable_delete
                BEFORE DELETE ON audit_events
                BEGIN
                    SELECT RAISE(ABORT, 'Audit events table is strictly append-only. Deletions are forbidden at the database layer.');
                END;
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<SQL
                DROP TRIGGER IF EXISTS trg_audit_events_immutable ON audit_events;
                DROP FUNCTION IF EXISTS prevent_audit_events_mutation();
            SQL);
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared(<<<SQL
                DROP TRIGGER IF EXISTS trg_audit_events_immutable_update;
                DROP TRIGGER IF EXISTS trg_audit_events_immutable_delete;
            SQL);
        }
    }
};
