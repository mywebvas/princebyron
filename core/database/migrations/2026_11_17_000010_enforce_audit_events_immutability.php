<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Database-level append-only enforcement for audit_events
|--------------------------------------------------------------------------
| ASVS 5.0 Level 2 requires the audit trail to be tamper-evident even against
| code that bypasses the application — a direct SQL console, a compromised admin
| script, a mistaken manual DELETE. The model layer already refuses updates and
| deletes (App\Models\AuditEvent); this is the second lock.
|
| WHY ALL THREE DRIVERS (change: MySQL/MariaDB support)
| ----------------------------------------------------
| This migration was PostgreSQL + SQLite only. On MySQL/MariaDB — the default on
| the shared cPanel hosting this project targets — it silently did nothing, so
| the audit trail had **no** database-level protection on the most likely
| production database. Silent degradation is the worst outcome for a security
| control: the documentation says "enforced at the database layer" and, on the
| host that actually ships, it is not.
|
| Each driver gets the strongest mechanism it has:
|
|   PostgreSQL  A BEFORE UPDATE OR DELETE row trigger, raising an exception.
|   MySQL/MariaDB A trigger that SIGNALs SQLSTATE 45000. MySQL allows only one
|               trigger per event per table, so this cannot coexist with anything
|               else touching the table — nothing else does.
|   SQLite      Two triggers, one per event (SQLite has no combined trigger).
|
| The triggers are removed in down() for all three, so a rollback is clean.
*/

return new class extends Migration
{
    public function up(): void
    {
        match (DB::getDriverName()) {
            'pgsql' => $this->createPostgresTrigger(),
            'mysql', 'mariadb' => $this->createMysqlTriggers(),
            'sqlite' => $this->createSqliteTriggers(),
            // Any other driver: no database-level enforcement available. The
            // application still refuses. Logged rather than silent so an
            // operator on an exotic driver knows what they are relying on.
            default => logger()->warning('No audit immutability trigger for driver: '.DB::getDriverName()),
        };
    }

    private function createPostgresTrigger(): void
    {
        DB::unprepared(<<<'SQL'
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

    private function createMysqlTriggers(): void
    {
        // ONE STATEMENT PER CALL, deliberately.
        //
        // PDO's MySQL driver does not enable multi-statement execution by
        // default, so a DROP and a CREATE in a single unprepared() call fails
        // with a syntax error at the second statement. (PostgreSQL's driver does
        // accept a multi-statement string, which is why that branch above can
        // batch — and why this bit CI on MariaDB and nowhere else.)
        DB::unprepared('DROP TRIGGER IF EXISTS trg_audit_events_immutable_update');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_audit_events_immutable_update
            BEFORE UPDATE ON audit_events
            FOR EACH ROW
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Audit events table is strictly append-only. Updates are forbidden at the database layer.'
        SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS trg_audit_events_immutable_delete');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_audit_events_immutable_delete
            BEFORE DELETE ON audit_events
            FOR EACH ROW
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Audit events table is strictly append-only. Deletions are forbidden at the database layer.'
        SQL);
    }

    private function createSqliteTriggers(): void
    {
        // Also one call per statement: SQLite's exec() accepts multiple
        // statements, but keeping every driver uniform removes a class of
        // "works here, fails there" surprise for whoever edits this next.
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER IF NOT EXISTS trg_audit_events_immutable_update
            BEFORE UPDATE ON audit_events
            BEGIN
                SELECT RAISE(ABORT, 'Audit events table is strictly append-only. Updates are forbidden at the database layer.');
            END
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER IF NOT EXISTS trg_audit_events_immutable_delete
            BEFORE DELETE ON audit_events
            BEGIN
                SELECT RAISE(ABORT, 'Audit events table is strictly append-only. Deletions are forbidden at the database layer.');
            END
        SQL);
    }

    public function down(): void
    {
        match (DB::getDriverName()) {
            'pgsql' => DB::unprepared(<<<'SQL'
                DROP TRIGGER IF EXISTS trg_audit_events_immutable ON audit_events;
                DROP FUNCTION IF EXISTS prevent_audit_events_mutation();
            SQL),
            // Separate calls: PDO MySQL is single-statement (see createMysqlTriggers).
            'mysql', 'mariadb' => [
                DB::unprepared('DROP TRIGGER IF EXISTS trg_audit_events_immutable_update'),
                DB::unprepared('DROP TRIGGER IF EXISTS trg_audit_events_immutable_delete'),
            ],
            'sqlite' => DB::unprepared(<<<'SQL'
                DROP TRIGGER IF EXISTS trg_audit_events_immutable_update;
                DROP TRIGGER IF EXISTS trg_audit_events_immutable_delete;
            SQL),
            default => null,
        };
    }
};
