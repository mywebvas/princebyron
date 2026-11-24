<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Post-deploy readiness self-check (review P3-8).
 *
 * WHY THIS IS A COMMAND AND NOT A DOCUMENT
 * ----------------------------------------
 * The install instructions ended with "delete install/ when you are done". That
 * is the single most-skipped step in any deployment, because nothing breaks if
 * you skip it — and the directory contains a form that writes .env. A checklist
 * item nobody can verify is a checklist item nobody does.
 *
 * Every check here is a fact about the running installation, not about the
 * source tree, so it can only give an honest answer on the host itself.
 */
class ReadinessCommand extends Command
{
    protected $signature = 'pb:readiness
                            {--strict : Exit non-zero when any check fails (for deploy scripts)}';

    protected $description = 'Verify this installation is safe to leave running in production';

    /** @var array<int, array{0:string,1:bool,2:string}> */
    private array $results = [];

    public function handle(): int
    {
        $this->check('Application key is set', filled(config('app.key')), 'Run `php artisan key:generate` — without it sessions and encrypted settings fail silently at random.');
        $this->check('Debug mode is off', ! config('app.debug'), 'APP_DEBUG=true prints stack traces and environment values to visitors on any error.');
        $this->check('Installer has been removed', ! is_dir(base_path('../install')), 'Delete the install/ directory. It contains a form that rewrites .env.');
        $this->check('Production environment', app()->environment('production'), 'APP_ENV is "'.app()->environment().'", not "production" — caching and some guards behave differently.');

        $this->check('Compiled config is present', file_exists(base_path('bootstrap/cache/config.php')) || app()->configurationIsCached(), 'Not required, but `php artisan config:cache` makes a shared host noticeably faster. Run it after every .env change.');
        $this->check('Compiled views are present', (bool) glob(storage_path('framework/views/*.php')), 'Run `php artisan view:cache` so the first visitor after a deploy does not pay for Blade compilation.');

        $this->checkStorage();
        $this->checkDatabase();
        $this->checkScheduler();
        $this->checkQueue();
        $this->checkLogging();
        $this->checkAuditTrigger();
        $this->checkUrl();
        $this->checkLegalPages();

        return $this->report();
    }

    private function check(string $label, bool $ok, string $remedy): void
    {
        $this->results[] = [$label, $ok, $remedy];
    }

    private function checkStorage(): void
    {
        $paths = [
            'storage/framework/cache' => storage_path('framework/cache'),
            'storage/framework/sessions' => storage_path('framework/sessions'),
            'storage/framework/views' => storage_path('framework/views'),
            'storage/logs' => storage_path('logs'),
            'bootstrap/cache' => base_path('bootstrap/cache'),
        ];

        foreach ($paths as $label => $path) {
            // Writable is the only thing that matters: a missing-but-writable
            // parent is recoverable, an unwritable one is a 500 on the first
            // request that touches it.
            $this->check(
                "{$label} is writable",
                is_dir($path) ? is_writable($path) : is_writable(dirname($path)),
                "chmod the directory to 0755 (or 0775 if the host runs PHP as a different user than the FTP account): {$path}"
            );
        }
    }

    private function checkDatabase(): void
    {
        $connected = true;
        $driver = 'unknown';
        $pending = 0;

        try {
            DB::connection()->getPdo();
            $driver = DB::getDriverName();
            // -1 means "cannot tell", which is reported as a failure because a
            // schema you cannot inspect is not a schema you can vouch for.
            $pending = Schema::hasTable('migrations') ? $this->countPendingMigrations() : -1;
        } catch (\Throwable) {
            $connected = false;
        }

        $this->check(
            "Database is reachable ({$driver})",
            $connected,
            'Check the DB_* values in .env. On shared hosts the database name is usually prefixed with the account name.'
        );

        if ($connected) {
            $this->check(
                'Migrations are up to date',
                $pending === 0,
                $pending > 0
                    ? "{$pending} migration(s) have not run. Run `php artisan migrate --force`."
                    : 'Run `php artisan migrate --force`, then `php artisan db:seed --class=\'Database\\Seeders\\SettingsSeeder\' --force`.'
            );
        }
    }

    private function countPendingMigrations(): int
    {
        $files = glob(database_path('migrations/*.php')) ?: [];
        $ran = app('migrator')->getRepository()->getRan();

        $names = array_map(
            fn (string $file) => str_replace('.php', '', basename($file)),
            $files
        );

        return count(array_diff($names, $ran));
    }

    private function checkScheduler(): void
    {
        // There is no portable way to read another user's crontab from PHP, and
        // guessing would be worse than saying so. What CAN be checked is whether
        // the schedule has ever actually run: schedule:run touches this key when
        // it fires a due task.
        $lastRun = cache()->get('pb:schedule:heartbeat');

        $this->check(
            'The scheduler has run recently',
            $lastRun !== null,
            'Add the cron line from the readiness page (or docs/DEPLOYMENT.md). Until then queue:prune-failed never runs.'
        );
    }

    private function checkQueue(): void
    {
        $connection = config('queue.default');

        // sync is a legitimate choice on a shared host: mail is sent inline. It
        // is reported as a fact rather than a failure so it cannot be mistaken
        // for a misconfiguration.
        $this->check(
            "Queue connection is '{$connection}'",
            true,
            $connection === 'sync'
                ? 'Mail is sent inline during the request. A slow SMTP host will make checkout slow; see docs/DEPLOYMENT.md if that happens.'
                : 'Make sure a worker is running, or queued mail will never send.'
        );
    }

    private function checkLogging(): void
    {
        $stack = config('logging.channels.stack.channels');
        $rotates = is_array($stack) && in_array('daily', $stack, true);

        $this->check(
            'Logs rotate daily',
            $rotates,
            'Set LOG_STACK=daily and LOG_DAILY_DAYS=14 in .env, or storage/logs grows without bound until the disk quota stops the site.'
        );
    }

    private function checkAuditTrigger(): void
    {
        // The database-level append-only guard is the one security control that
        // a migration can silently fail to install — see the per-driver branches
        // in the audit migration. Reporting it here means the owner does not need
        // to trust that CI's MariaDB job matches their host.
        $driver = DB::getDriverName();
        $present = false;

        try {
            $rows = match ($driver) {
                'pgsql' => DB::select("select tgname from pg_trigger where tgrelid = 'audit_events'::regclass and not tgisinternal"),
                'mysql', 'mariadb' => DB::select('select trigger_name from information_schema.triggers where event_object_table = ?', ['audit_events']),
                'sqlite' => DB::select("select name from sqlite_master where type = 'trigger' and tbl_name = 'audit_events'"),
                default => [],
            };
            $present = count($rows) > 0;
        } catch (\Throwable) {
            $present = false;
        }

        $this->check(
            'Audit trail is append-only at the database layer',
            $present,
            'The trigger is missing. Re-run `php artisan migrate --force`; if it still fails, contact support with the database engine name.'
        );
    }

    private function checkUrl(): void
    {
        $url = config('app.url');

        $this->check(
            'APP_URL is an absolute URL',
            is_string($url) && preg_match('#^https?://#', $url) === 1,
            'Set APP_URL to the real public address, e.g. https://example.com. Links in emails and the sitemap are built from it.'
        );

        $this->check(
            'APP_URL uses HTTPS',
            is_string($url) && str_starts_with($url, 'https://'),
            'Serve the site over HTTPS and update APP_URL. Checkout and session cookies depend on it.'
        );
    }

    /**
     * Legal pages (Phase 6).
     *
     * A store taking money without a published refund and privacy policy is not
     * production-ready, regardless of how well the code runs. Card processors
     * check for these during review and several jurisdictions require a stated
     * cancellation right for digital goods.
     *
     * Reported as FAILURES, not warnings: this is the one readiness item that is
     * a commercial and legal blocker rather than a technical one.
     */
    private function checkLegalPages(): void
    {
        try {
            $outstanding = [];

            foreach (\App\Support\LegalPage::requiredKeys() as $key) {
                $section = \App\Models\ContentSection::where('section_key', $key)->first();

                if ($section === null || trim((string) $section->body) === '') {
                    $label = str_replace('legal_', '', $key);
                    $outstanding[] = $label;
                }
            }

            $this->check(
                'Refund, privacy, terms and shipping policies are written',
                $outstanding === [],
                $outstanding === []
                    ? 'All policies are written.'
                    : 'Still showing template text: '.implode(', ', $outstanding)
                      .'. Write these in the admin panel under Content Sections before taking payments.'
            );
        } catch (\Throwable) {
            // The table may not exist yet on a half-installed site; do not let a
            // readiness check crash over the thing it is meant to report.
            $this->check(
                'Refund, privacy, terms and shipping policies are written',
                false,
                'Could not read the content sections table. Run `php artisan migrate --force` first.'
            );
        }
    }

    private function report(): int
    {
        $this->newLine();
        $failures = 0;

        foreach ($this->results as [$label, $ok, $remedy]) {
            if ($ok) {
                $this->line("  <fg=green>PASS</>  {$label}");
                continue;
            }
            $failures++;
            $this->line("  <fg=red>FAIL</>  {$label}");
            $this->line("         {$remedy}");
        }

        $this->newLine();

        if ($failures === 0) {
            $this->info('This installation looks safe to run in production.');
            return self::SUCCESS;
        }

        $this->warn("{$failures} check(s) need attention before this can be called production-ready.");

        // Without --strict this is advisory: on a shared host the owner may be
        // mid-setup and should not have every command abort.
        return $this->option('strict') ? self::FAILURE : self::SUCCESS;
    }
}
