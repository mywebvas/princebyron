# First install on a cPanel host

This is the checklist for installing this build on shared cPanel hosting. Read the
"what you must build first" section before uploading anything — the repository does
**not** contain the files a running site needs, and uploading it as-is will produce a
blank page.

---

## 1. What you must build first — this is the one real gate

The repository is source. It has no `vendor/`, no Filament admin assets and no
`.env`. A fresh clone knows this and says so:

```
$ ./tools/package.sh --verify-only
  missing core/vendor/
  missing Filament admin assets (core/public/css|js/filament)
  Vite build output present
```

Those two gaps must be closed on a machine with PHP and Node — **not** on the cPanel
host, because `composer install` needs more memory and time than shared hosting
usually allows. On the build machine:

```bash
cd core
composer install --no-dev --optimize-autoloader   # produces vendor/
php artisan filament:assets                        # produces public/css|js/filament/
npm ci && npm run build                            # produces public/build/ (already committed)
```

Then produce the upload archive:

```bash
./tools/package.sh
```

It writes `dist/princebyron-<version>.zip` plus a `.sha256` checksum beside it. Verify
the archive before uploading — a truncated upload fails on the host, not here:

```bash
cd dist && sha256sum -c princebyron-<version>.zip.sha256
```

`package.sh` refuses to build an archive that is missing `vendor/` or the Filament
assets, so if it succeeds you have a deployable artefact. **Upload the archive it
produces, not the git repository.**

---

## 2. Server requirements

| Requirement | Value | Why |
|---|---|---|
| PHP | **8.5 or newer** | `composer.json` requires `^8.5`. Anything lower fails at install. |
| `pdo_mysql` | required | The database driver. MariaDB 10.6+ or MySQL 8.0+. |
| `mbstring`, `openssl`, `json`, `fileinfo`, `ctype`, `tokenizer`, `xml`, `curl`, `dom` | required | Laravel's baseline; nearly always present. |
| `intl` | recommended | Locale-aware formatting. |
| Memory limit | **≥ 256 MB** | Composer and `php artisan` commands need headroom. |
| Disk | ~300 MB | `vendor/` is large. |
| Node | **not needed on the host** | The frontend is pre-built. |

Ask your host to confirm the exact PHP version before buying a plan. PHP 8.5 is
recent; a host still defaulting to 8.2 or 8.3 cannot run this build, and many let you
select the version per domain in cPanel → **MultiPHP Manager**. If they cannot offer
8.5, that is a hosting change, not a code change — do not try to talk the code down
to an older PHP, because the dependency set is built for 8.5.

---

## 3. Upload — into the document root, as-is

This release uses a **root loader**, so you do *not* move the document root:

1. Upload the archive and extract it directly into the document root —
   `public_html/` for the main domain, or `public_html/subdomain/` for a subdomain.
   Extract **inside** that directory, not beside it.
2. The layout after extraction is exactly:

```
public_html/
├── index.php          <- front controller / installer entry
├── .htaccess          <- blocks /core, /vendor, /.env, /storage, /.git
├── install/           <- first-run installer, self-locking
└── core/              <- the application (app, config, public, vendor, storage)
```

3. Visit the site. With no `.env` and no `core/storage/installed` file, `index.php`
   **redirects you to `install/` automatically** — there is nothing to guess.

`index.php` blocks direct requests to `/core`, `/vendor`, `/storage`, `/.env` and
`/.git`, and `.htaccess` repeats the block as defence in depth. Both matter: if
either were missing, `core/.env` would be downloadable and the database password
and `APP_KEY` would be exposed.

**Do not** point the document root at `core/public/`. That would work for the site
but leave `install/` unreachable and bypass the root loader's protections.

**Verify the protection after install** — this is worth thirty seconds:

```
https://yourdomain.com/core/.env        -> must be 403 or 404
https://yourdomain.com/.env             -> must be 403 or 404
```

If either downloads a file, stop and fix `.htaccess` before going further.

---

## 4. Database

In cPanel → **MySQL Databases**:

1. Create a database, e.g. `user_store`.
2. Create a user with a strong generated password; add it to the database with
   **All Privileges**.
3. Note the full names — cPanel prefixes them, so `store` becomes `user_store`.

**Do not run these by hand — use the installer.** It creates the `.env`, the owner
account and the catalogue from a web form, which avoids three problems a manual
install has: no `.env` typo, no known-password account, and no half-seeded database.

1. Just visit your domain. `index.php` redirects to `install/` when the site is not
   yet installed. If you land on an error page instead, go to
   `https://yourdomain.com/install/index.php` directly.
2. Fill in the database details from step 4, the owner name/email/password, the
   site URL and SMTP.
3. The installer **requires HTTPS on a remote host** — it refuses to accept
   database and administrator passwords over plain HTTP. Get the certificate
   first.
4. It then writes `.env`, runs migrations, seeds the catalogue and creates the
   owner account, and finally writes `core/storage/installed`, which locks the
   installer permanently. Reloading the page after that shows a "permanently
   disabled" notice; removing the lock is documented there and requires file
   access.

**Why not `php artisan db:seed --force` instead?** Because the seeder cannot know
your password. `DatabaseSeeder` therefore creates **no account at all** — it seeds
the catalogue, content sections (including the four legal policy rows) and
settings only, and `DatabaseSeederTest` fails if anyone adds an account seeder
back to it. The owner account comes from the installer, with the password you
type.

If you do run the artisan path manually, create the owner afterwards:

```bash
php artisan migrate --force
php artisan db:seed --force          # catalogue + content, no accounts
php artisan tinker                   # then create the owner by hand
```

---

## 5. Environment file

Copy `core/.env.example` to `core/.env` and set, at minimum:

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com
APP_KEY=                      # php artisan key:generate

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=user_store
DB_USERNAME=user_store
DB_PASSWORD=...

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=database

MAIL_MAILER=smtp
MAIL_HOST=mail.yourdomain.com
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=orders@yourdomain.com
```

`APP_DEBUG=false` is not optional. With it on, any error page prints environment
variables and database credentials to the visitor.

Leave `STRIPE_*` and `PAYPAL_*` blank. Payment creation is not implemented yet; blank
keys mean nothing can accidentally take money.

Generate the key with `php artisan key:generate`. **Back it up.** Losing `APP_KEY`
makes every encrypted session and stored secret unrecoverable.

---

## 6. Cron and queue

cPanel → **Cron Jobs**, one entry:

```
* * * * * cd /home/USER/princebyron/core && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

Without it, scheduled backups, entitlement expiry and cleanup never run — and
nothing visibly breaks, so it is easy to forget. Afterwards confirm the schedule is
registered:

```bash
php artisan schedule:list
```

The queue runs on the database driver, so order emails are queued. Either set
`QUEUE_CONNECTION=sync` to send inline on shared hosting (simpler, slower) or add a
second cron running `php artisan queue:work --stop-when-empty`.

---

## 7. First-run checks

```bash
php artisan pb:readiness
```

This is the launch gate. It lists every blocker — unwritten legal policies, missing
storage link, absent backups, insecure config. **A production release should not
start until it passes**, and it will fail today on the four legal policies until you
write them (see `PHASE-6-DECISIONS.md`).

Then:

```bash
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan pb:backup          # proves backups actually write
```

Visit the site over HTTPS, load an image, place a test order, and check the admin
panel at `/admin`.

---

## 8. If something goes wrong

| Symptom | Likely cause |
|---|---|
| Blank page, 500, no error | `APP_DEBUG=false` hides it — read `core/storage/logs/laravel.log`. |
| "No application encryption key" | `php artisan key:generate` was not run. |
| 404 on every page except `/` | Document root points at the wrong directory. |
| Assets 404, page unstyled | `core/public/build/` missing — rebuild, or the archive was made from a dirty tree. |
| "could not find driver" | `pdo_mysql` absent — ask the host. |
| Admin panel unstyled | Filament assets not published — `php artisan filament:assets`. |

---

## 9. What is still not implemented

Stated plainly, because discovering it after launch is worse:

- **Payment creation is not implemented.** Orders are recorded; no money moves.
  Refunds are recorded, not transmitted to a payment provider.
- **Sales tax is not calculated.** `tax_cents` defaults to 0.
- **Restore has not been rehearsed.** Backups are written and tested; an actual
  restore into a clean database has never been performed.
- **Legal policies need your text.** Until then they are marked templates and
  `pb:readiness` fails.
