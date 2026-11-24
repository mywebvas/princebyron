<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled work (review P3-7)
|--------------------------------------------------------------------------
|
| One cron line on the host drives all of this:
|
|     * * * * * cd /home/USER/public_html/core && php artisan schedule:run >> /dev/null 2>&1
|
| The installer writes the exact line for the chosen path, and the readiness
| page shows it. Without that line none of the tasks below run and nothing
| says so — which is why `pb:readiness` reports the scheduler state.
|
*/

/*
| Failed queue jobs.
|
| The installer sets QUEUE_CONNECTION=sync, so on a fresh install this table is
| empty and the task is a no-op. It earns its place the moment the owner moves
| mail or anything else onto a queue — which is the normal next step when a
| shared host's 30-second mail timeout starts biting. A failed_jobs table that
| grows forever on a host with no database-maintenance routine is how a small
| plan runs out of disk.
|
| Seven days, not thirty: a failed job older than a week is an incident report,
| not a retry candidate, and the owner has the audit log for that.
*/
/*
| Heartbeat.
|
| `pb:readiness` cannot read the host's crontab, and guessing whether a cron
| line exists would be worse than admitting it cannot tell. Instead the scheduler
| writes a marker whenever it runs, so the readiness check reports what actually
| happened rather than what the owner believes they configured.
*/
Schedule::call(function () {
    cache()->put('pb:schedule:heartbeat', now()->toIso8601String(), now()->addDays(2));
})->everyMinute()->name('heartbeat');

Schedule::command('queue:prune-failed', ['--hours' => 168])
    ->daily()
    ->at('03:10')
    ->withoutOverlapping();

/*
| NOT scheduled: sitemap refresh.
|
| An earlier draft of this file cleared a `sitemap.xml` cache key daily. Reading
| routes/web.php showed that key does not exist — /sitemap.xml is generated from
| the live catalogue on every request and is not cached at all. The task would
| have been a no-op that looked like maintenance, which is worse than nothing:
| it invites the next person to trust a cache that was never there.
|
| If the sitemap ever proves expensive enough to cache, this is where the
| invalidation belongs — with the cache added at the same time, not before.
*/

/*
| Deliberately NOT scheduled: an "expired entitlements" sweep.
|
| DownloadEntitlement::scopeActive() filters on expires_at at READ time, so an
| expired row grants nothing whether or not a job flips its flag. A background
| job that rewrote is_active would add a second source of truth for the same
| fact, and the two would eventually disagree — the exact class of bug this
| project has spent three sprints removing. If the table ever grows enough to
| matter, the correct fix is pruning rows (model:prune), not rewriting flags.
*/
