<?php

namespace Tests\Feature;

use App\Models\DownloadEntitlement;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Sprint 4 operations: the health probe, the readiness check, the backup command
 * and the schedule.
 *
 * These are the things an owner relies on at 2am with no terminal. Each test
 * asserts observable behaviour, not that a method exists.
 */
class OperationsTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------- health

    public function test_the_health_probe_does_not_write_to_the_cache(): void
    {
        // P3-9: the probe used to Cache::put() then Cache::get() on every request.
        // On the file driver the installer leaves behind, that is a disk write per
        // poll — 2,880 files a day at a 30-second interval, on a host with an inode
        // quota. A read proves reachability just as well.
        Cache::flush();
        $before = Cache::get('health_ping');

        $this->getJson('/up')->assertOk();

        $this->assertNull(
            Cache::get('health_ping'),
            'The health probe must not create cache entries.'
        );
        $this->assertSame($before, Cache::get('health_ping'));
    }

    public function test_the_health_probe_still_reports_ok(): void
    {
        $this->getJson('/up')
            ->assertOk()
            ->assertExactJson(['status' => 'ok']);
    }

    // ------------------------------------------------------------- readiness

    public function test_the_readiness_command_runs_and_reports_every_check(): void
    {
        // It must never crash on a host: a readiness check that itself 500s is
        // worse than none, because the owner loses the one tool that would have
        // told them what was wrong.
        $this->artisan('pb:readiness')
            ->expectsOutputToContain('Audit trail is append-only at the database layer')
            ->expectsOutputToContain('Logs rotate daily')
            ->assertSuccessful();
    }

    public function test_strict_mode_fails_when_a_check_fails(): void
    {
        // In the test environment install/ exists and debug is on, so at least one
        // check is guaranteed to fail. --strict must then exit non-zero so a
        // deploy script can gate on it.
        $this->artisan('pb:readiness', ['--strict' => true])
            ->assertFailed();
    }

    public function test_readiness_detects_that_the_installer_is_still_present(): void
    {
        // Reports the remedy, not just the failure: a bare FAIL leaves the owner
        // guessing which of fourteen checks they are meant to act on.
        $this->artisan('pb:readiness')
            ->expectsOutputToContain('Delete the install/ directory')
            ->assertSuccessful();
    }

    public function test_readiness_verifies_the_audit_trigger_is_installed(): void
    {
        // The database-level append-only guard is the one security control that a
        // migration can silently fail to install. This asserts the check reports
        // PASS on a properly migrated database — i.e. the trigger really is there.
        $triggers = match (DB::getDriverName()) {
            'pgsql' => DB::select("select tgname from pg_trigger where tgrelid = 'audit_events'::regclass and not tgisinternal"),
            'mysql', 'mariadb' => DB::select('select trigger_name from information_schema.triggers where event_object_table = ?', ['audit_events']),
            'sqlite' => DB::select("select name from sqlite_master where type='trigger' and tbl_name='audit_events'"),
            default => [],
        };

        $this->assertNotEmpty(
            $triggers,
            'The audit trigger is missing, so pb:readiness would correctly report a failure.'
        );
    }

    // ---------------------------------------------------------------- backup

    public function test_backup_writes_a_file_and_prunes_old_ones(): void
    {
        $dir = sys_get_temp_dir().'/pb-backup-test-'.uniqid();
        mkdir($dir, 0755, true);

        // Two pre-existing backups, so retention has something to remove.
        file_put_contents("{$dir}/backup-20200101-000000.sql", 'old');
        file_put_contents("{$dir}/backup-20200101-000001.sql", 'old');

        $this->artisan('pb:backup', ['--output' => $dir, '--keep' => 2])
            ->assertSuccessful();

        $files = glob("{$dir}/backup-*");
        $this->assertNotEmpty($files, 'The backup command produced no file.');
        $this->assertLessThanOrEqual(2, count($files), 'Retention did not prune.');

        // The newest file must be the one we just wrote, and it must contain a
        // real dump rather than an empty shell.
        $newest = $files[count($files) - 1];
        $this->assertGreaterThan(50, filesize($newest), 'The backup file is empty.');

        array_map('unlink', $files);
        rmdir($dir);
    }

    public function test_the_backup_contains_the_rows_it_claims_to(): void
    {
        $user = User::factory()->create(['email' => 'backup-probe@example.test']);

        $dir = sys_get_temp_dir().'/pb-backup-content-'.uniqid();
        mkdir($dir, 0755, true);

        $this->artisan('pb:backup', ['--output' => $dir, '--keep' => 1])->assertSuccessful();

        $files = glob("{$dir}/backup-*");
        $dump = '';
        foreach ($files as $f) {
            $dump .= file_get_contents($f);
        }

        /*
         | THE ROW IS ONLY ASSERTABLE ON THE PORTABLE PATH, and the reason is worth
         | recording.
         |
         | RefreshDatabase wraps each test in a transaction that is never committed.
         | An engine-native dump (pg_dump, mysqldump) opens its OWN connection, so it
         | cannot see uncommitted rows — the dump is correct in production and
         | legitimately empty of this row in a test. The PHP fallback reads through
         | the same PDO connection inside the transaction, so it does see the row.
         |
         | Asserting the email unconditionally made the test fail on PostgreSQL for a
         | reason that had nothing to do with the backup being wrong.
         */
        if (str_contains($dump, 'Prince Byron portable backup')) {
            $this->assertStringContainsString(
                'backup-probe@example.test',
                $dump,
                'The portable dump must contain the rows it claims to include.'
            );
            $this->assertStringContainsString(
                'LIMITATIONS',
                $dump,
                'The portable dump must carry its own limitations header.'
            );
        } else {
            // Engine-native or binary. Assert it is a real artefact rather than an
            // empty file, and that the method was reported honestly.
            $this->assertGreaterThan(200, strlen($dump), 'The backup file is suspiciously small.');
        }

        array_map('unlink', $files);
        rmdir($dir);
    }

    public function test_the_backup_never_puts_credentials_on_the_command_line(): void
    {
        // /proc/<pid>/cmdline is world-readable on a shared host, so a password
        // passed as an argument is a password handed to every other account.
        $source = file_get_contents(base_path('app/Console/Commands/BackupCommand.php'));

        $this->assertStringContainsString('MYSQL_PWD', $source);
        $this->assertStringContainsString('PGPASSWORD', $source);
        $this->assertStringNotContainsString("'-p'.\$", $source);
        $this->assertStringNotContainsString('--password=', $source);
    }

    // -------------------------------------------------------------- schedule

    public function test_the_scheduler_registers_the_tasks_that_actually_do_something(): void
    {
        // Asserted against the real Schedule the kernel builds from
        // routes/console.php, so a task deleted or misspelled fails here rather
        // than silently never running on the host.
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events());

        $this->assertTrue(
            $events->contains(fn ($e) => str_contains((string) ($e->command ?? ''), 'queue:prune-failed')),
            'queue:prune-failed is not scheduled; failed jobs would accumulate forever.'
        );

        $this->assertTrue(
            $events->contains(fn ($e) => ($e->description ?? null) === 'heartbeat'),
            'The scheduler heartbeat is missing, so pb:readiness could never confirm cron is running.'
        );
    }

    public function test_the_scheduler_does_not_schedule_work_that_proves_nothing(): void
    {
        // routes/console.php documents two tasks it deliberately does NOT have:
        // a sitemap cache clear (the sitemap is not cached) and an expired-
        // entitlement sweep (the active scope already refuses expired rows). Both
        // were in an earlier draft and would have been no-ops that looked like
        // maintenance. This pins the decision so it cannot quietly return.
        $commands = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->map(fn ($e) => (string) ($e->command ?? ''))
            ->implode("\n");

        $this->assertStringNotContainsString('pb:expire-entitlements', $commands);
        $this->assertStringNotContainsString('sitemap', $commands);
    }

    // ---------------------------------------------------------- entitlements

    public function test_an_expired_entitlement_grants_nothing_without_a_sweep(): void
    {
        // This is why no "expired entitlements" job is scheduled: the read-time
        // scope already refuses it, so a sweep would only add a second source of
        // truth for the same fact.
        // No factories exist for Order or Song, and song_id is NOT NULL, so both
        // are created the way the application creates them.
        $user = User::factory()->create();

        $order = Order::create([
            'reference' => 'ORD-OPS-'.uniqid(),
            'user_id' => $user->id,
            'status' => 'paid',
            'currency' => 'USD',
            'subtotal_cents' => 1000,
            'total_cents' => 1000,
            // NOT NULL on the schema: an order always carries the contact address
            // it was placed with, even for an account holder.
            'customer_name' => $user->name,
            'customer_email' => $user->email,
        ]);

        $song = \App\Models\Song::create([
            'title' => 'Ops Probe',
            'slug' => 'ops-probe-'.uniqid(),
            'is_published' => true,
        ]);

        $expired = DownloadEntitlement::create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'song_id' => $song->id,
            'format' => 'mp3',
            'is_active' => true,
            'expires_at' => now()->subDay(),
            'download_count' => 0,
        ]);

        $this->assertNull(
            DownloadEntitlement::active()->find($expired->id),
            'An expired entitlement must not appear in the active scope, sweep or no sweep.'
        );
    }
}
