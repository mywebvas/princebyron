<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Database portability — the shared-host promise.
 *
 * WHY THIS EXISTS
 * ---------------
 * The installer has offered MySQL/MariaDB since Sprint 0 while the schema used
 * `jsonb` in four migrations and the audit-immutability migration was PostgreSQL
 * + SQLite only. On MariaDB the install would have half-completed and the audit
 * trail would have shipped with no database-level protection — on the database
 * most of the target hosts actually provide.
 *
 * The real proof is the CI matrix (PostgreSQL, MySQL/MariaDB, SQLite each running
 * the full suite). These tests pin the parts a single-driver run cannot: that no
 * migration still asks for a PostgreSQL-only column type, and that the installer
 * and the test harness agree about which drivers are supported.
 */
class DatabasePortabilityTest extends TestCase
{
    // The JSON round-trip test writes a row, so it needs a schema.
    use RefreshDatabase;

    public function test_no_migration_uses_a_postgres_only_column_type(): void
    {
        $offenders = [];

        foreach (glob(database_path('migrations/*.php')) as $file) {
            $source = file_get_contents($file);

            // jsonb, and PostgreSQL-only helpers that have no portable
            // Blueprint equivalent.
            foreach (['->jsonb(', '->geography(', '->geometry(', '->tsvector('] as $needle) {
                if (str_contains($source, $needle)) {
                    $offenders[] = basename($file).' uses '.$needle;
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "PostgreSQL-only column types break MySQL/MariaDB installs:\n  ".implode("\n  ", $offenders)
        );
    }

    public function test_the_audit_trigger_migration_handles_every_supported_driver(): void
    {
        $source = file_get_contents(database_path('migrations/2026_11_17_000010_enforce_audit_events_immutability.php'));

        // The trigger is the one construct that cannot be expressed portably, so
        // it must branch per driver. A missing branch is a silent security gap,
        // not a crash — which is exactly why it needs an assertion.
        foreach (['pgsql', 'mysql', 'sqlite'] as $driver) {
            $this->assertStringContainsString(
                "'{$driver}'",
                $source,
                "The audit-immutability migration has no branch for {$driver}"
            );
        }

        $this->assertStringContainsString('SIGNAL SQLSTATE', $source, 'MySQL needs SIGNAL to reject a tampering attempt');
    }

    public function test_the_installer_offers_the_drivers_the_schema_supports(): void
    {
        $installer = file_get_contents(base_path('../install/index.php'));

        $this->assertStringContainsString('value="mysql"', $installer);
        $this->assertStringContainsString('value="pgsql"', $installer);
    }

    public function test_a_phpunit_profile_exists_for_every_supported_driver(): void
    {
        // A driver with no phpunit profile is a driver CI cannot exercise, and a
        // capability CI cannot exercise is a claim rather than a feature.
        $this->assertFileExists(base_path('phpunit.pgsql.xml'));
        $this->assertFileExists(base_path('phpunit.mysql.xml'));

        $mysql = file_get_contents(base_path('phpunit.mysql.xml'));
        $this->assertStringContainsString('name="DB_CONNECTION" value="mysql"', $mysql);
    }

    public function test_json_columns_round_trip_on_this_driver(): void
    {
        // The portability risk of swapping jsonb for json is that a driver might
        // store it as text and hand back a string instead of an array. Eloquent
        // casts it, so the observable behaviour must be identical everywhere.
        $event = \App\Models\AuditEvent::create([
            'action' => 'portability.probe',
            'new_values' => ['nested' => ['a' => 1, 'b' => [true, null]]],
        ]);

        $fresh = \App\Models\AuditEvent::find($event->id);

        $this->assertIsArray($fresh->new_values);
        $this->assertSame(1, $fresh->new_values['nested']['a']);
        $this->assertSame([true, null], $fresh->new_values['nested']['b']);
    }
}
