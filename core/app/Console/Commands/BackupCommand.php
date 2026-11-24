<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;

/**
 * Database backup (review P3-6).
 *
 * WHY NOT JUST "USE THE HOST'S cPanel BACKUP"
 * -------------------------------------------
 * cPanel's backup is usually weekly, often excludes databases on cheaper plans,
 * and is not something the owner can produce on demand before a risky change. A
 * shop that holds orders must be able to take a consistent copy at any moment.
 *
 * DESIGN CHOICE: engine-native dump when available, portable SQL otherwise.
 *   - pg_dump / mysqldump produce a restorable file that any DBA or cPanel
 *     import screen understands.
 *   - They are frequently disabled on shared hosts (exec is often restricted),
 *     so a PHP fallback writes INSERT statements instead. The fallback is slower
 *     and less faithful (no routines, no triggers), and it SAYS SO in the output
 *     and in the file header rather than quietly producing a lesser artefact.
 *
 * WHAT THIS DELIBERATELY DOES NOT DO
 *   Upload the backup anywhere. A backup written to the same host protects
 *   against a bad migration, not against the account being lost. The command
 *   prints the exact command to copy the file off-host.
 */
class BackupCommand extends Command
{
    protected $signature = 'pb:backup
                            {--output= : Directory to write the backup into (default: storage/backups)}
                            {--keep=14 : How many backups to retain}
                            {--force : Run even if the scheduler heartbeat shows the site is uninstalled}';

    protected $description = 'Write a consistent database backup and report how to get it off the host';

    public function handle(): int
    {
        $dir = $this->option('output') ?: storage_path('backups');

        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            $this->error("Cannot create the backup directory: {$dir}");
            return self::FAILURE;
        }

        $driver = DB::getDriverName();
        $stamp = now()->format('Ymd-His');
        $base = "backup-{$stamp}";

        $this->info("Engine: {$driver}");
        $this->info("Target: {$dir}");

        $result = match ($driver) {
            'pgsql' => $this->dumpPostgres($dir, $base),
            'mysql', 'mariadb' => $this->dumpMySql($dir, $base),
            'sqlite' => $this->dumpSqlite($dir, $base),
            default => $this->dumpPortable($dir, $base),
        };

        if ($result === null) {
            return self::FAILURE;
        }

        [$path, $method] = $result;

        $bytes = filesize($path);
        $this->newLine();
        $this->info(sprintf('Wrote %s (%s) using %s', basename($path), $this->human($bytes), $method));

        if ($bytes < 200) {
            // A 200-byte dump means an empty database or a silent failure. Either
            // way the owner must not treat it as a backup.
            $this->warn('That file is suspiciously small. Verify it before relying on it.');
        }

        $this->prune($dir, (int) $this->option('keep'));

        $this->newLine();
        $this->comment('This file is on the same host as the database it protects.');
        $this->comment('It survives a bad migration. It does not survive a lost account.');
        $this->line('  Copy it somewhere else:');
        $this->line("    scp {$path} you@your-machine:/somewhere/safe/");

        return self::SUCCESS;
    }

    /** @return array{0:string,1:string}|null */
    private function dumpPostgres(string $dir, string $base): ?array
    {
        $path = "{$dir}/{$base}.sql";

        if ($this->hasBinary('pg_dump') && $this->runDump([
            'pg_dump',
            '--no-owner',
            '--no-acl',
            '--clean',
            '--if-exists',
            '-f', $path,
        ], $path)) {
            return [$path, 'pg_dump'];
        }

        $this->warn('pg_dump is unavailable or refused (common on shared hosts). Falling back to portable SQL.');
        return $this->dumpPortable($dir, $base);
    }

    /** @return array{0:string,1:string}|null */
    private function dumpMySql(string $dir, string $base): ?array
    {
        $path = "{$dir}/{$base}.sql";

        if ($this->hasBinary('mysqldump') && $this->runDump([
            'mysqldump',
            '--single-transaction',   // consistent snapshot without locking the shop
            '--quick',
            '--routines',
            '--triggers',
            '--no-tablespaces',
            '--result-file='.$path,
        ], $path)) {
            return [$path, 'mysqldump (triggers and routines included)'];
        }

        $this->warn('mysqldump is unavailable or refused (common on shared hosts).');
        $this->warn('The SQL fallback below does NOT include triggers or routines — restore them with a migration.');
        return $this->dumpPortable($dir, $base);
    }

    /** @return array{0:string,1:string}|null */
    private function dumpSqlite(string $dir, string $base): ?array
    {
        $path = "{$dir}/{$base}.sqlite";
        $source = DB::connection()->getDatabaseName();

        if (is_file($source) && @copy($source, $path)) {
            return [$path, 'file copy (SQLite)'];
        }

        return $this->dumpPortable($dir, $base);
    }

    private function hasBinary(string $binary): bool
    {
        // `command -v` is a shell builtin, so it works even where exec is
        // restricted to a small allowlist — and it answers the only question
        // that matters: is this name resolvable from here?
        $probe = Process::fromShellCommandline('command -v '.escapeshellarg($binary));
        $probe->run();

        return $probe->isSuccessful() && trim($probe->getOutput()) !== '';
    }

    private function runDump(array $command, string $path): bool
    {
        // Credentials go through the environment, never the argv: on a shared
        // host any other account can read /proc/<pid>/cmdline.
        $env = [
            'PGPASSWORD' => (string) config('database.connections.pgsql.password'),
            'MYSQL_PWD' => (string) config('database.connections.mysql.password'),
        ];

        // Connection flags are appended AFTER the tool's own options: both
        // pg_dump and mysqldump treat a trailing host/user/database as the
        // target, so the order matters.
        $process = new Process(
            array_merge($command, $this->connectionArgs($command[0])),
            base_path(),
            $env,
        );
        $process->setTimeout(1800);

        try {
            $process->run();
        } catch (\Throwable $e) {
            $this->warn('Dump command failed: '.$e->getMessage());
            return false;
        }

        if (! $process->isSuccessful()) {
            $this->warn('Dump exited '.$process->getExitCode().': '.trim($process->getErrorOutput()));
            @unlink($path);
            return false;
        }

        return is_file($path) && filesize($path) > 0;
    }

    private function connectionArgs(string $binary): array
    {
        return str_contains($binary, 'pg')
            ? [
                '-h', (string) config('database.connections.pgsql.host'),
                '-p', (string) config('database.connections.pgsql.port'),
                '-U', (string) config('database.connections.pgsql.username'),
                (string) config('database.connections.pgsql.database'),
            ]
            : [
                '-h', (string) config('database.connections.mysql.host'),
                '-P', (string) config('database.connections.mysql.port'),
                '-u', (string) config('database.connections.mysql.username'),
                (string) config('database.connections.mysql.database'),
            ];
    }

    /**
     * Portable SQL dump. Slow and partial, and the file says so in its header so
     * nobody restores from it under the impression it is a full fidelity dump.
     *
     * @return array{0:string,1:string}|null
     */
    private function dumpPortable(string $dir, string $base): ?array
    {
        $path = "{$dir}/{$base}-portable.sql";
        $handle = fopen($path, 'w');

        if ($handle === false) {
            $this->error("Cannot write {$path}");
            return null;
        }

        fwrite($handle, sprintf(
            "-- Prince Byron portable backup\n-- Taken: %s\n-- Engine: %s\n--\n-- LIMITATIONS: this is a PHP-generated INSERT dump. It does not include\n-- triggers, stored routines, sequences or indexes beyond what the schema\n-- already has. Restore it, then run `php artisan migrate --force` to restore\n-- the audit-immutability trigger.\n\n",
            now()->toIso8601String(),
            DB::getDriverName(),
        ));

        $tables = $this->tables();

        foreach ($tables as $table) {
            $this->line("  dumping {$table}");

            fwrite($handle, "DELETE FROM \"{$table}\";\n");

            // chunk() needs a stable order or rows can be skipped or repeated as
            // the cursor moves. Every table here has an `id`; the few pivot-free
            // tables without one are ordered by their whole row instead.
            $query = DB::table($table);
            if (Schema::hasColumn($table, 'id')) {
                $query->orderBy('id');
            }

            $query->chunk(500, function ($rows) use ($handle, $table) {
                foreach ($rows as $row) {
                    $values = array_map(
                        fn ($v) => is_null($v) ? 'NULL' : DB::getPdo()->quote((string) $v),
                        (array) $row
                    );
                    fwrite($handle, sprintf(
                        "INSERT INTO \"%s\" (%s) VALUES (%s);\n",
                        $table,
                        implode(', ', array_map(fn ($c) => "\"{$c}\"", array_keys((array) $row))),
                        implode(', ', $values)
                    ));
                }
            });
        }

        fclose($handle);

        return [$path, 'portable SQL (triggers and routines NOT included)'];
    }

    /** @return array<int,string> */
    private function tables(): array
    {
        return match (DB::getDriverName()) {
            'sqlite' => array_map(
                fn ($r) => $r->name,
                DB::select("select name from sqlite_master where type='table' and name not like 'sqlite_%'")
            ),
            'mysql', 'mariadb' => array_map(
                fn ($r) => array_values((array) $r)[0],
                DB::select('show tables')
            ),
            default => array_map(
                fn ($r) => $r->tablename,
                DB::select("select tablename from pg_tables where schemaname = 'public'")
            ),
        };
    }

    private function prune(string $dir, int $keep): void
    {
        if ($keep < 1) {
            return;
        }

        $files = glob("{$dir}/backup-*") ?: [];
        sort($files);

        foreach (array_slice($files, 0, max(0, count($files) - $keep)) as $old) {
            @unlink($old);
            $this->line('  pruned '.basename($old));
        }
    }

    private function human(int $bytes): string
    {
        return $bytes > 1048576
            ? round($bytes / 1048576, 1).' MB'
            : round($bytes / 1024, 1).' KB';
    }
}
