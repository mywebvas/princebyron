# Deployment, backup and rollback

This is the operator's document. It assumes you are the site owner, not a
developer, and that the host has **no Composer and no Node** — the archive is
built elsewhere and uploaded.

Everything here has been executed at least once during development, except where
an item is explicitly marked **NOT YET REHEARSED**. Nothing in this file is a
plan we have not run.

---

## 1. Build the release archive

On a machine with PHP 8.5, Composer and Node (your PC, or a CI runner):

```bash
cd core
composer install --no-dev --optimize-autoloader
php artisan filament:assets
npm ci && npm run build

cd ..
./tools/package.sh
```

The script refuses to build if `vendor/` or the Filament assets are missing,
because an archive without them installs and then fails in the browser — where
there is no terminal to debug it.

It produces:

| File | Purpose |
|---|---|
| `dist/princebyron-<version>.zip` | Upload this |
| `dist/princebyron-<version>.zip.sha256` | Verify the upload was not corrupted |
| `dist/VERSION` | The version, for tracing what is live |

It then **extracts its own archive** into a clean directory and checks the
structure, so a broken zip is caught on your machine rather than on the host.

---

## 2. Upload and install

1. In cPanel → **File Manager**, upload the zip to your home directory.
2. **Extract** it. You get `princebyron-<version>/`.
3. Point the domain's document root at `princebyron-<version>/` (or move its
   contents up, keeping `index.php` and `.htaccess` at the root).
4. Visit `https://yourdomain.com/install/` and complete the form.
5. **Delete the `install/` directory.** This is not optional; see §5.

---

## 3. After installing

Open cPanel → **Terminal** (or the "Cron Jobs" screen) and:

```bash
cd ~/princebyron-<version>/core
php artisan pb:readiness
```

`pb:readiness` checks the things that are silently wrong rather than loudly
broken: whether the installer is still there, whether debug mode is on, whether
logs rotate, whether the audit trigger was actually installed on your database
engine, whether `APP_URL` is HTTPS, and whether the scheduler has ever run.

Add `--strict` in a script to make it exit non-zero on any failure.

### The cron line

Without this, `queue:prune-failed` never runs and the failed-jobs table grows
forever. `pb:readiness` reports whether the scheduler has actually fired.

```
* * * * * cd /home/YOURUSER/princebyron-<version>/core && php artisan schedule:run >> /dev/null 2>&1
```

Replace `YOURUSER` and the version directory. In cPanel, the *Command* field is
everything after the five asterisks.

---

## 4. Backup and restore

### Take a backup

```bash
cd ~/princebyron-<version>/core
php artisan pb:backup
```

Writes `storage/backups/backup-<timestamp>.sql` and prunes to the newest 14.

The command prefers `pg_dump`/`mysqldump`. On shared hosts those are frequently
disabled, in which case it falls back to a PHP-generated SQL dump and **says so**
— that fallback does not include triggers, so the audit-immutability trigger
must be restored by running `php artisan migrate --force` afterwards.

### Get the backup off the host

A backup on the same host protects you from a bad migration. It does not protect
you from losing the account. The command prints the `scp` line; use it, or
download the file through File Manager.

### Restore — **NOT YET REHEARSED**

> A restore that has never been executed is a plan, not a capability. This
> procedure is written from the engine documentation and has **not** been run
> against a real database in this project. Treat Sprint 5 as incomplete until
> someone performs it and records the result.

1. Take a backup of the *current* (broken) state first, so you can go back.
2. PostgreSQL:
   ```bash
   psql -h HOST -U USER -d DATABASE -f backup-<timestamp>.sql
   ```
3. MySQL/MariaDB:
   ```bash
   mysql -h HOST -u USER -p DATABASE < backup-<timestamp>.sql
   ```
4. If you restored from the **portable** dump, restore the audit trigger:
   ```bash
   php artisan migrate --force
   ```
5. Verify:
   ```bash
   php artisan pb:readiness --strict
   ```
   and place one test order end to end.

---

## 5. Deleting the installer

The `install/` directory contains a form that rewrites `.env`. After a successful
install the archive's `.htaccess` returns 403 for it, but **defence in depth is
not the same as removal**:

```bash
cd ~/princebyron-<version>
rm -rf install
```

`pb:readiness` fails until this is done, so you cannot forget quietly.

If you ever need to re-run the installer, re-upload just that directory.

---

## 6. Rollback

The safest rollback is a version directory, not a `git` command:

1. **Before** deploying a new version, keep the old one:
   ```bash
   mv princebyron-<old> princebyron-<old>.previous
   ```
2. Extract and install the new version alongside it.
3. Repoint the document root at the new directory.
4. If anything is wrong, repoint the document root back. The old directory still
   has its own `vendor/`, its own build and its own `.env`.

Because each version directory is self-contained, rollback is a document-root
change and takes seconds. **Do not** roll back the database this way — the old
code may not understand a newer schema. Migrations are forward-only; restore from
a backup instead (§4).

---

## 7. What is still unproven

Recorded here rather than buried, because a deployment doc that claims more than
was tested is worse than none:

- **The restore procedure has never been executed.** (§4)
- **No release archive has been built with a real `vendor/` and real Filament
  assets** in this project's history. `tools/package.sh` has been executed
  end-to-end against stand-in directories, so the script itself works, but the
  real build products have not existed in the build environment.
- The health endpoint and `pb:readiness` have been exercised by the test suite on
  SQLite, PostgreSQL and MariaDB. They have not been run against a real cPanel
  account.
