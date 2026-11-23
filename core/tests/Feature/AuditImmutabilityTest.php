<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The audit trail must be append-only at the DATABASE layer, on every driver.
 *
 * WHY THIS IS A TEST AND NOT A MIGRATION DETAIL
 * ---------------------------------------------
 * The model layer already refuses update/delete, so a test that goes through
 * Eloquent proves nothing about the trigger — Eloquent would throw first. These
 * tests deliberately go through the query builder, which bypasses the model, so
 * what they actually verify is the database trigger.
 *
 * The trigger was PostgreSQL + SQLite only for the entire review period. On
 * MySQL/MariaDB — the database most shared cPanel hosts provide, and the one the
 * installer defaults to — there was no database-level protection at all. That is
 * the worst shape for a security control: documented as enforced, and silently
 * absent on the deployment that ships.
 *
 * `phpunit.mysql.xml` plus the MySQL job in CI is what keeps this honest.
 */
class AuditImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    private function insertAuditRow(): int
    {
        // Query builder: no model events, no fillable checks, no guard.
        return DB::table('audit_events')->insertGetId([
            'action' => 'ci.probe',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_the_database_refuses_to_update_an_audit_event(): void
    {
        $id = $this->insertAuditRow();

        $this->expectException(\Throwable::class);

        DB::table('audit_events')->where('id', $id)->update(['action' => 'tampered']);
    }

    public function test_the_database_refuses_to_delete_an_audit_event(): void
    {
        $id = $this->insertAuditRow();

        $this->expectException(\Throwable::class);

        DB::table('audit_events')->where('id', $id)->delete();
    }

    public function test_the_row_survives_a_refused_tamper_attempt(): void
    {
        $id = $this->insertAuditRow();

        // DRIVER-SPECIFIC BEHAVIOUR, discovered the hard way.
        //
        // On PostgreSQL a failed statement poisons the whole transaction: every
        // later command in it returns "current transaction is aborted" (25P02)
        // until a rollback. MySQL/MariaDB and SQLite abort only the statement.
        // So the verification read has to happen in its own transaction, or this
        // test proves nothing on the primary driver and fails for a reason that
        // has nothing to do with the audit trail.
        try {
            DB::transaction(fn () => DB::table('audit_events')->where('id', $id)->update(['action' => 'tampered']));
        } catch (\Throwable) {
            // expected: the trigger refused it
        }

        $this->assertSame(
            'ci.probe',
            DB::table('audit_events')->where('id', $id)->value('action'),
            'The row must be unchanged after a refused tamper attempt'
        );
    }

    public function test_inserting_is_still_allowed(): void
    {
        // A trigger that blocked inserts too would take the shop offline the
        // moment anything was audited. The constraint is append-only, not frozen.
        $id = $this->insertAuditRow();

        $this->assertGreaterThan(0, $id);
        $this->assertDatabaseHas('audit_events', ['id' => $id]);
    }

    public function test_a_trigger_exists_on_this_driver(): void
    {
        $driver = DB::getDriverName();
        $triggers = match ($driver) {
            'pgsql' => DB::select(
                "select tgname as name from pg_trigger where tgrelid = 'audit_events'::regclass and not tgisinternal"
            ),
            'mysql', 'mariadb' => DB::select(
                'select trigger_name as name from information_schema.triggers where event_object_table = ?',
                ['audit_events']
            ),
            'sqlite' => DB::select("select name from sqlite_master where type = 'trigger' and tbl_name = 'audit_events'"),
            default => [],
        };

        $this->assertNotEmpty(
            $triggers,
            "No audit-immutability trigger exists on the {$driver} driver."
        );
    }
}
