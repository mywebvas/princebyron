# Prince Byron — Website and Store

This is the production project workspace for **princebyron.com**. The `/prototype` folder contains the existing static prototype as a behavior and content reference only. `/design-review` is a separate, interactive design concept for owner review; it has not been approved as the production design. Rebuild the public experience with a separately reviewed premium visual direction; do not copy either prototype's layouts or styling as the production design.

## Current status & local review

> **Read this before quoting any status claim.** A full senior review of the codebase was completed on 2026-11-23: see [`docs/PRODUCTION-READINESS-REVIEW.md`](docs/PRODUCTION-READINESS-REVIEW.md). It separates three things that previous status summaries blended together:
>
> 1. **Implemented and reachable** — the commerce, payment-verification, entitlement, audit and installer layers are built and test-covered.
> 2. **Wired to the server as of Sprint 1** — the public catalog reads the database, the cart is server-authoritative, and checkout creates a real order. What is still not live: online payment capture (no gateway is enabled, so no money is taken) and the build products a release needs (`vendor/`, Filament assets).
> 3. **Not verified** — anything that depends on the real host, real SMTP, real provider credentials, screen readers, or field Core Web Vitals.
>
> Automated verification is now enforced by CI ([`.github/workflows/ci.yml`](.github/workflows/ci.yml)) rather than asserted in prose. The release rules in `docs/ARCHITECTURE.md` are executable via `node tools/check-package.mjs` and `node tools/check-assets.mjs`.
>
> **Do not describe the platform as production-ready or "upload and it works" until the P0 findings in the review are closed.** The fixed \$750 scope boundary is unchanged.

### Verified verification baseline (Sprint 0)

**Status: green.** All five CI jobs pass on `arena/9adff00e-princebyron`
(run [45174679053](https://github.com/mywebvas/princebyron/actions/runs/45174679053)),
having been proven to fail on injected violations. "CI-enforced" means the check
runs on every push and blocks a red build; it does not mean the behaviour is
correct in production, only that it is verified in the repository.

| Check | Command | State |
|---|---|---|
| Composer manifest/lock sync | `node tools/check-lock.mjs` | CI-enforced |
| Release completeness (vendor, Filament assets) | `node tools/check-release.mjs --strict` | **Reporter** — states what the upload still needs |
| PHP syntax, every tracked `.php` | `php -l` over `git ls-files '*.php'` | CI-enforced |
| Test suite (SQLite, fast pass) | `php artisan test` | CI-enforced — 170 tests, 829 assertions |
| Test suite (PostgreSQL, primary) | `phpunit.pgsql.xml` installed as `phpunit.xml` | CI-enforced — same suite |
| Test suite (MySQL/MariaDB) | `phpunit.mysql.xml` installed as `phpunit.xml` | CI-enforced against MariaDB 11 — same suite |
| Migration replay from empty DB | `php artisan migrate:fresh --force` | CI-enforced |
| Seeder idempotency | seeders run twice | CI-enforced |
| Blade compilation | `php artisan view:cache` | CI-enforced |
| Workflow YAML validity | `node tools/check-workflows.mjs` | CI-enforced |
| Frontend build + payload budget | `npm run build` + `node tools/check-assets.mjs` | CI-enforced |
| Release-archive rules (secrets, dev files, web-root layout) | `node tools/check-package.mjs` | CI-enforced |
| Code style | `vendor/bin/pint --test` | **Advisory** — pre-existing style debt; see below |

Three notes so the table is not read as more than it says:

1. **Code style is advisory, not enforced.** The tree predates Pint
   normalisation, so making it blocking would fail on untouched files. It
   becomes a gate in the commit that normalises the codebase.
2. **The CI runner satisfies PHP ≥ 8.5.** This is not assumed: the preflight
   test only passes when *every* requirement check succeeds, including the PHP
   floor and a database driver, so a green run is evidence that the runner
   meets the shipped requirement. The installer's *failure* path is also tested
   explicitly by raising the effective floor above the runtime.
3. **`vendor/` and Filament assets are build products** produced on the release
   machine, not in this repository — see review P0-5 / P3-1.

The Prince Byron application has completed Phases 1 through 5 and is currently prepared for complete local review on your PC on loopback only.

### Local review access

- **Base URL:** [http://127.0.0.1:8001](http://127.0.0.1:8001)
- **Local Review Credentials:**
  - **Customer Fan Vault:**
    - Email: `fan@princebyron.local`
    - Password: `changeme-dev-only`
    - Contains: Paid single order (`PB-202611-001`, $1.99 via Stripe sandbox), Pending merchandise order (`PB-202611-002`, $93.00 awaiting owner bank verification), Free ministry order (`PB-202611-000`), active download and streaming entitlements.
  - **Owner Admin Panel (Filament 5):**
    - Email: `owner@princebyron.local`
    - Password: `changeme-dev-only`
    - Access: Full owner administrative dashboard at [http://127.0.0.1:8001/admin](http://127.0.0.1:8001/admin) (Products, Songs, Orders, Booking RFQs, Content Sections, Settings, Audit Events, Shipping Rates).
  - **Simulated Admin Theme Preview:**
    - URL: [http://127.0.0.1:8001/admin/preview](http://127.0.0.1:8001/admin/preview) (Phase 2 static/visual mock preview).

### Startup & shutdown instructions

**To Start the Application:**
1. Ensure Docker Desktop is running with the isolated project containers:
   ```powershell
   docker start princebyron_postgres princebyron_redis princebyron_mailpit
   ```
2. Start the Laravel development server on loopback port 8001:
   ```powershell
   cd core
   C:\php85\php.exe artisan serve --host=127.0.0.1 --port=8001
   ```
3. Open [http://127.0.0.1:8001](http://127.0.0.1:8001) in your browser.

**To Stop the Application:**
1. Press `Ctrl + C` in the PowerShell terminal running `artisan serve`.
2. Stop the local Docker containers when finished:
   ```powershell
   docker stop princebyron_postgres princebyron_redis princebyron_mailpit
   ```

### Complete route inventory

| Category | Route / URL | Access / Purpose |
| :--- | :--- | :--- |
| **Public Editorial** | `GET /` | Home page — Cinematic dark opening, featured music & story |
| | `GET /about` | Our Story & Ministry vision |
| | `GET /music` | Music Releases catalog with audio previews & format options |
| | `GET /store` | Official Store merchandise grid |
| | `GET /store/{slug}` | Single-product detail page (e.g. `/store/tee` or `/store/prince-byron-t-shirt`) |
| | `GET /booking` | Ministry appearance / booking RFQ guided submission form |
| **Commerce & Bag** | `GET /cart` | Itemized shopping bag review page |
| | `GET /checkout` | Dedicated checkout page (Stripe Sandbox, PayPal Sandbox, Demo Bank Transfer) |
| **Customer Auth & Vault** | `GET /account` | Customer Fan Vault & Order History (interactive login / dashboard) |
| | `GET /login` | Customer login view |
| | `GET /register` | Customer account registration view |
| | `POST /logout` | Customer session logout |
| | `GET /downloads/{id}` | Authorized, short-lived digital download delivery stream |
| **Owner Administration** | `GET /admin` | Live Filament 5 Owner Panel (requires owner authentication) |
| | `GET /admin/login` | Filament 5 Owner login form |
| | `GET /admin/preview` | Visual demonstration theme preview |
| **System & Monitoring** | `GET /up` | Application health check endpoint |
| | Web Mailpit UI | [http://127.0.0.1:8025](http://127.0.0.1:8025) (Local email inbox) |

### Remaining review limitations & owner decisions

- **Payment Mode — precise wording, please use it:** *Webhook verification* for Stripe and PayPal is implemented and tested (signature verification, replay/idempotency, amount and currency matching, refund revocation; PayPal uses official asymmetric RSA verification). **Payment creation is not implemented**: checkout returns mock sandbox artifacts, no Stripe/PayPal SDK is installed, and no request is made to either provider. Do not describe payments as "integrated"; describe them as "verified callback handling complete, provider checkout not started" (review finding P1-12).
- ~~Public storefront wiring~~ — **closed in Sprint 1.** The catalog is read from the database via `App\Services\CatalogService`, the cart is a cache of a server summary (`/api/cart*`), and checkout creates a persisted order with a server-generated reference. Unpublishing a product removes it from the site and its URL 404s; an un-seeded install shows clearly-labelled sample items that cannot be purchased.
- **Online payment capture is still not enabled.** Stripe and PayPal run in sandbox verification mode: the webhook handlers verify signatures and grant entitlements, but order creation does not call a live gateway. The checkout page now says so plainly rather than implying a card charge, and no card data is collected.
- ~~Bank transfer ships without automatic entitlements~~ — **closed in Sprint 2.** An order still sits in `awaiting_bank_verification` until the owner confirms funds and nothing is released beforehand, but confirming funds now grants the digital entitlements through the same service the payment webhooks use. A guest order (no account) cannot receive a download; the owner is told so at verification time, by name, rather than being left to wonder.
- **A deployable archive needs two build steps on a machine with PHP, Composer and Node:** `composer install --no-dev` and `php artisan filament:assets`. Run `node tools/check-release.mjs --strict` to confirm both before zipping — it fails the build if either is missing, which is what stops the "unstyled admin panel" outcome (review P0-5).
- **Bank Transfer:** available in the admin panel as an owner verification action; orders stay `awaiting_bank_verification` until the owner records an independently verified receipt, at which point entitlements are granted (Sprint 2).
- **Refunds are recorded, not transmitted.** The owner can refund an order from the admin panel: the platform writes a negative payment, revokes the downloads and writes an audit event. The money movement itself still happens in the Stripe or PayPal dashboard — the action does not call either provider's refund API, so the two records must be reconciled by hand.
- **Financial records cannot be deleted.** Orders, and customers who have orders, refuse deletion at the model layer. For a departing customer the correct operation is **anonymisation**, available from the order's admin page: it removes name, email, phone, password and postal address from both the account and the order, revokes download entitlements, deactivates the account and writes an audit event recording what was done and why — without copying the removed values into the log. Money, line items, dates, order reference and shipping country survive, because those are the financial record and the tax aggregation. Owner accounts are refused, since anonymising one would strip the audit trail of its attributions.
- **Email:** Routed to local Mailpit (`127.0.0.1:1025`/`8025`); no live external emails are sent. The installer template also writes Mailpit settings, so booking notifications are silently dropped on a real host until SMTP is configured (review finding P0-7).
- **Shipping Rates:** The owner has not finalized physical shipping zones or carrier rates. Live checkout requires configured destinations in `/admin/shipping-rates`.
- **Master Audio Files:** Studio WAV masters are pending final artist delivery. The catalog advertises 24-bit WAV while no WAV masters exist (`has_wav = false`); downloadable MP3 files are streamed through an entitlement check, and the download counter is only consumed when a real file is delivered.
- **Hosting:** the schema now builds on **MySQL/MariaDB, PostgreSQL and SQLite**. The audit-immutability trigger has a real branch per driver (PostgreSQL row trigger, MySQL/MariaDB `SIGNAL SQLSTATE`, SQLite `RAISE(ABORT)`), column types are portable, and CI runs the full suite against MariaDB 11 — the database most shared cPanel plans actually provide — alongside the other two. The installer's MySQL option, which predates this support, is now truthful.


## Approved product direction

- Artist: Prince Byron; a blend of gospel genres; premium, modern, spiritual, soulful, warm, and internationally appealing.
- Visual direction: cinematic dark opening, elegant light editorial sections, earthy clay, cocoa, sand, cream, and olive.
- Domain: `princebyron.com`; audience: United States and international.
- Approved songs: Free, Purify Me, I Always Win, Toduyun, Follow Me. The owner controls which tracks are free or paid and their prices.
- Approved sample merchandise prices: T-shirt $32, hoodie $58, cap $30, tote $25, art print $25, sticker pack $6; tee-and-cap bundle $35; full bundle $80.
- Checkout currency: USD. Planned payment choices: Stripe, PayPal, and owner-verified manual bank transfer.
- Booking: guided legitimate appearance/ministry invitation and request-for-quotation flow; no public booking fee.
- Admin: owner-only at launch, with a controlled editor for supported page sections, copy, imagery, products, music, booking requests, and brand colors.
- Blog and newsletter are later-phase features.

## Local development

**Version requirement (authoritative): PHP 8.5.**
`core/composer.json` requires `"php": "^8.5"`. The lock file was resolved against PHP `^8.5` (see `platform` in `core/composer.lock`), and `composer.lock` is byte-in-sync with `composer.json` — enforced by `node tools/check-lock.mjs`, which reproduces Composer's own `Locker::getContentHash()`. Adding `config.platform` to `composer.json` was attempted and deliberately reverted: it changes the hashed payload and silently breaks `composer install`, so the vendor tree cannot be built at all. If a PHP-version pin is ever needed, it must be done with a real `composer update --lock` on the build machine in the same commit. The one-time installer enforces the same floor (`install/index.php`, `$requiredPhp`) and refuses to proceed below it — previously it accepted PHP 8.3, which allowed an install to "succeed" and then fatal on every request because locked dependencies (symfony 8.1) require PHP ≥ 8.4.1. A CI test (`test_installer_php_floor_matches_composer_constraint`) keeps the two declarations in lock-step, and the preflight result is now also enforced server-side so a crafted POST cannot skip it.

Planned local stack: Laravel 13, PHP 8.5, PostgreSQL, Blade, Tailwind CSS 4.1+, Alpine.js for small interactions, and a branded Filament 5 admin panel. The owner reports that Docker Desktop, PostgreSQL, and PHP 8.5 are already installed on the Windows PC. Their exact versions, service/container state, extensions, and Composer/Node availability have not been verified; Antigravity should inventory these before setup and avoid unnecessary global upgrades. Recheck framework and package compatibility at implementation time.

Use an isolated project database, preferably through the existing Docker setup when that is simpler to reproduce. Do not replace or reconfigure an existing PostgreSQL service without a recorded reason and rollback. Install dependencies and compile frontend assets **on the development PC**, run the local application against PostgreSQL, and upload a release package containing `vendor/` and compiled assets.

## Quality bar

- **Performance:** target good Core Web Vitals at the 75th percentile (LCP ≤2.5 s, INP ≤200 ms, CLS ≤0.1), measured on mobile and desktop. Define repeatable device/network and server-load profiles before accepting results; optimize images, fonts, queries, payloads, and caching from the design stage.
- **Security:** use OWASP ASVS 5.0 Level 2 as the application verification target, with stronger review for authentication, payments, and private downloads. This is an engineering target, not a claim of bank certification or a guarantee of zero vulnerabilities.
- **Experience:** aim for polished, distinctive, premium international art direction, WCAG 2.2 AA accessibility, clear feedback/recovery, reduced-motion support, and graceful behavior on low-bandwidth/mobile devices.
- **Maintainability:** prefer the simplest readable implementation that meets the requirement. “20 elite lines over 200” means remove avoidable complexity and duplication; it is not a line-count target and never justifies compressed or opaque code.
- **Future readiness:** favor documented interfaces, reversible decisions, safe migrations, dependency restraint, and a modular monolith that can scale vertically and horizontally when evidence requires it. No architecture can be future-proof in every possible respect.

## Shared-hosting deployment target

Target a generic cPanel shared host first. The package must not require Composer or Node on the server. It will use a minimal root loader and keep the Laravel application under `core/`, outside direct web access. A one-time browser setup can collect host-specific database configuration; it must be locked after setup. Secrets must never be committed or included in a release archive.

> **Approved hosting direction (2026-11-23):** the owner has chosen the path where the release must work on *any* shared cPanel host, which means adding **MySQL/MariaDB** support (schema dialect, audit trigger, installer driver selection, CI matrix) rather than requiring rare PostgreSQL hosting. This is a scoped change of work, estimated in the review at 12–16 engineering hours plus the policy/packaging work; it is recorded in [`docs/ROADMAP.md`](docs/ROADMAP.md) and is **not** silently folded into the fixed \$750 agreement.

**Verified packaging state (Sprint 0):** the release rules are executable, not just documented — `node tools/check-package.mjs` fails CI if a secret, a local database, a dev script, a log, or a web-root screenshot is ever tracked again, or if the root loader / `.htaccess` denial rules are removed. `core/.htaccess` and `core/storage/.htaccess` now deny direct access even if the root rewrite rules are bypassed.

“No Composer/Node” does not remove the host's need for a compatible PHP runtime, required PHP extensions, a reachable PostgreSQL service, writable Laravel runtime folders, and HTTPS. The actual cPanel plan must be verified before calling a release upload-ready. See [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md).

## Commercial boundary

The previously agreed **$750 production scope remains fixed and separate**. The production features listed in this project's roadmap are not silently treated as included in that agreement. Confirm the written deliverables and separately approve any expanded scope, pricing, or production release.

## Prototype and AI handoff

Read `/docs/ANTIGRAVITY-HANDOFF.md` first when starting production work in Antigravity. Then read `AGENTS.md`, `PRD.md`, `docs/ARCHITECTURE.md`, `docs/SECURITY.md`, and `docs/ROADMAP.md`. Read `/prototype/README.md` and `/prototype/docs/PROTOTYPE-SPEC.md` for approved content facts and demo boundaries. Treat `/prototype` and `/design-review` as reference material, not code or design templates. Follow the phase sequence and never activate real payments, customer authentication, email, protected downloads, DNS, or deployment without the required credentials and explicit release approval.
