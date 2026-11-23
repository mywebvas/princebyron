# Architecture and deployment

## Initial stack

- Laravel 13 and PHP 8.5 on the development PC, PostgreSQL for local and production data. The owner reports Docker Desktop, PostgreSQL, and PHP 8.5 are already installed locally; Antigravity must verify exact versions, extensions, service state, and project requirements before setup. Prefer a reproducible, isolated project database/container and preserve existing local services unless there is a verified need to change them.
- Host must run **PHP 8.5**, matching `core/composer.json` (`"php": "^8.5"`, `config.platform.php = 8.5.0`) and the installer's `$requiredPhp` gate. Laravel 13's own floor is 8.3 and Filament 5's is 8.2, but the locked dependency set resolves symfony 8.1.x, which requires ≥ 8.4.1 — so 8.3/8.4 hosts must be rejected at install time rather than "upgraded" silently. Verify the host's extensions before committing to it.
- Server-rendered Laravel Blade for public pages, with Tailwind CSS 4 for the design system and Alpine.js only for small interactions. Avoid a separate SPA unless a measured interaction requires one.
- Filament 5 for the owner-only admin panel, branded to the site's design. Its documented baseline includes PHP 8.2+, Laravel 11.28+, and Tailwind CSS 4.1+ for custom panel themes; build its assets locally.
- Lucide icons as a small, locally included SVG subset to avoid a runtime icon CDN.
- Vite/Node only on the development machine or release builder; include production-compiled assets in each upload package.
- No PWA in the core release. Reconsider only after offline user needs and cache/privacy boundaries are specified.

Use responsive AVIF/WebP derivatives where supported, with an appropriate JPEG fallback, intrinsic dimensions, lazy loading below the fold, and a restrained font strategy. Do not introduce external runtime font/icon CDNs as required dependencies; the site should remain legible on a slow connection and when third-party assets fail.

This is the proposed baseline, not a promise that a generic cPanel host supports every requirement. Recheck official release compatibility at implementation time.

## Day-one quality budgets

- **Web performance:** target good Core Web Vitals at the 75th percentile: LCP ≤2.5 s, INP ≤200 ms, CLS ≤0.1. Report mobile and desktop separately. Measure with real-device field data when available and a documented repeatable lab profile before launch. Budget initial assets, image bytes, font loading, database queries, and server response under a named workload; never infer fast behavior from code review alone.
- **Security verification:** use OWASP ASVS 5.0 Level 2 as the application security target. Add focused threat modeling and review for account recovery, owner administration, payment callbacks, bank reconciliation, and protected downloads. This target is not a banking certification or a guarantee of zero defects.
- **Accessible premium UX:** target WCAG 2.2 AA, keyboard/screen-reader coverage, reduced motion, clear state transitions, and resilient low-bandwidth behavior. “Million-dollar UI/UX” means deliberate art direction, excellent craft, consistency, accessibility, and tested completion of real user goals—not visual effects or price claims.
- **Code quality:** prefer the smallest clear solution that preserves correctness and safety. The user's preference for “20 elite lines instead of 200” means eliminate needless complexity, not compress code or set line-count quotas. Avoid premature abstractions and microservices.

## Scale and change strategy

Start with a modular Laravel monolith and PostgreSQL. Keep catalog, checkout/orders, payments, music entitlements, bookings, accounts, and CMS boundaries explicit. Make the web tier stateless where practical, index from measured query patterns, bound and retry asynchronous work safely, and make payment/order jobs idempotent. Add caches, queues, read replicas, or separate services only when measured load or operational constraints justify them. Use backward-compatible migrations and release rollback notes. This creates room to change without claiming that any design is universally future-proof.

## Application shape

Use a modular Laravel monolith with server-rendered public pages, relational persistence, queued/cron-capable side effects, and a single owner admin panel. Keep separate modules for catalog, cart/checkout/orders, payments, digital entitlements, bookings, accounts, content sections, and settings. The database is the source of truth for product prices, inventory, order states, payment records, entitlements, and booking requests.

Suggested deployment layout:

```text
public_html/
  index.php                 # small loader into core/public/index.php
  .htaccess                 # deny private paths; route requests safely
  assets/                   # versioned, compiled, public frontend assets
  core/
    app/ bootstrap/ config/ database/ resources/ routes/ vendor/
    storage/                # writable runtime data; protect from direct HTTP access
    .env                    # created/configured on the host; never in release archive
```

The final directory layout must be checked against the actual cPanel document root. If the host allows the domain document root to point at Laravel's `public/`, that is preferable; otherwise the root loader must explicitly deny direct access to all `core/` files and send only web requests to Laravel's front controller.

## Zero Composer/Node deployment

1. On the development PC, install locked Composer dependencies and build static assets.
2. Create a clean release archive containing the root loader, safe rewrite rules, Laravel app, `vendor/`, compiled assets, and a version marker. Exclude `.env`, logs, caches containing secrets, local databases, development dependencies, and private media.
3. Upload/extract using cPanel File Manager or FTP/SFTP. No server Composer or Node execution.
4. A one-time HTTPS browser installer collects database credentials and owner bootstrap details, checks required PHP extensions/database connectivity, runs guarded migrations, creates the owner, then locks and removes its own setup capability. It must not permit repeated migrations or arbitrary command execution.
5. Configure HTTPS, mail transport, payment credentials, private storage, cron, backups, and monitoring through documented host settings.

“Upload & works” means no Composer/Node installation or build on the host. It still requires the host to provide a supported PHP runtime, PDO PostgreSQL driver, required Laravel extensions, a reachable PostgreSQL database, writable runtime storage, HTTPS, and any required cron capability. A generic shared-host archive cannot be declared ready until tested against the actual provider and plan.

## Comparative Pattern Analysis: Vazzet Evaluation

A read-only inspection of the legacy Vazzet codebase (`Files/`) was performed to evaluate its cPanel packaging, payment handling, and installer mechanics against Prince Byron's ASVS 5.0 Level 2 security and architecture requirements:

| Component | Vazzet Legacy Pattern | Identified Security Risk | Prince Byron Implementation |
|---|---|---|---|
| **Root Loader & Web Root** | Root `index.php` loads `core/public/index.php`. | Missing defense-in-depth URI sanitization; relies entirely on Apache `.htaccess`. | Defense-in-depth: Root `index.php` explicitly checks and rejects requests to `/core/`, `/.env*`, `/vendor/`, `/storage/`, `/.git/`, `.sql`, `.log`, and `.lock` with HTTP 403 before invoking the front controller. |
| **Apache `.htaccess`** | Rewrite rules for clean URLs. | Permissive file matching; missing modern security headers and caching headers. | Hardcoded rewrite blocks for `core/`, `vendor/`, `storage/`, `\.env`, `\.git`; strict `FilesMatch` denial on sensitive extensions; ASVS 5.0 L2 headers (`nosniff`, `SAMEORIGIN`, `1; mode=block`, `strict-origin-when-cross-origin`); mod_deflate and mod_expires. |
| **Browser Installer Lockout** | Checks for installed flag file. | **CRITICAL FLAW:** Permitted re-installation bypass via `?force=1` query parameter, exposing production databases to unauthorized overwrite and owner account hijacking. | **Permanent Lockout:** Immediate HTTP 403 Forbidden on line 24 if `core/storage/installed` exists. Absolutely NO URL parameter bypass (`?force=1`, `?reset=1`, etc.) is accepted. Safe documented recovery requires authenticated server filesystem access (SSH or cPanel File Manager). |
| **Stripe Webhooks** | Hardcoded demo fallback secrets. | Insecure fail-open behavior if webhook secret was missing from environment. | **Fail-Closed:** Missing webhook secret immediately aborts with HTTP 500. Raw request payload, `Stripe-Signature` header, and endpoint secret are strictly verified. |
| **PayPal Webhooks** | Custom shared-secret HMAC patterns. | Incompatible with official PayPal asymmetric signature verification. | **Official Asymmetric Verification:** Verifies RSA signature (`SHA256withRSA`) over 5 HTTP headers (`PAYPAL-TRANSMISSION-ID`, `PAYPAL-TRANSMISSION-TIME`, `PAYPAL-TRANSMISSION-SIG`, `PAYPAL-CERT-URL`, `PAYPAL-AUTH-ALGO`). Validates certificate URL against official PayPal domains (`api.paypal.com` / `api.sandbox.paypal.com`) to prevent SSRF. |
| **Stock Management** | Unchecked stock deduction. | Concurrency race conditions and overselling under simultaneous checkouts. | **Atomic Pessimistic Locking:** Uses `ProductVariant::lockForUpdate()` within database transactions and atomic conditional decrements (`where('stock_quantity', '>=', $qty)->decrement(...)`) to guarantee stock integrity. |
| **Download Entitlements** | Incremented download count even when files were missing. | Burned customer download quota when master audio was pending release. | **Count Protection:** Download count and `last_downloaded_at` are incremented ONLY when physical master audio files exist on disk and are streamed. Pending studio masters state leaves customer entitlement count intact. |

## cPanel Root Loader and One-Time Installer Architecture

### Root Loader (`/index.php`)
- Sits at `public_html/` (shared hosting root).
- Automatically redirects first-time visitors to `/install/` if neither `core/storage/installed` nor `core/.env` is detected.
- Evaluates `$_SERVER['REQUEST_URI']`, normalizes the path, and enforces defense-in-depth denial (HTTP 403) for `/core`, `/vendor`, `/storage`, `/.env`, `/.git`, and sensitive extensions (`.sql`, `.log`, `.lock`).
- Passes valid web requests to `core/public/index.php`.

### Root `.htaccess`
- Restricts direct HTTP access to `core/`, `vendor/`, `storage/`, `\.env`, and `\.git` using Apache `mod_rewrite` `[F,L]` flags.
- Denies sensitive file extensions (`.env`, `.git`, `.lock`, `.json`, `.sql`, `.log`, `.md`, `composer.`).
- Routes static build assets directly to `core/public/build/` and `core/public/assets/`.
- Injects baseline ASVS 5.0 L2 security headers: `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `X-XSS-Protection: 1; mode=block`, and `Referrer-Policy: strict-origin-when-cross-origin`.
- Applies `mod_deflate` GZIP compression and `mod_expires` caching (1 year for immutable images/fonts, 1 month for CSS/JS).

### One-Time Browser Installer (`/install/index.php`)
- **Gate 1: Permanent Lockout:** If `core/storage/installed` exists, returns HTTP 403 Forbidden with a styled lockout page and documented recovery path. No query parameters can bypass this gate.
- **Gate 2: Preflight Verification:** Verifies **PHP >= 8.5** (`$requiredPhp`, matching `core/composer.json`), `pdo_pgsql`, required extensions (`pdo`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `curl`, `fileinfo`), and directory write permissions (`core/bootstrap/cache/`, `core/storage/`, and subdirectories). A failed preflight disables setup in the UI **and is enforced server-side**, so a crafted POST cannot continue past it. The version gate is deliberately stricter than Laravel's own floor: because the release ships a prebuilt `vendor/` tree, Composer performs no platform check on the host, so this gate is the only thing preventing an "install succeeded, every request fatals" outcome. A CI test (`test_installer_php_floor_matches_composer_constraint`) fails if the installer and `composer.json` floors ever diverge.
- **Gate 3: Safe Credential Handling & Sanitization:** Connects to PostgreSQL PDO with provided host, port, dbname, user, and password. Exception messages are sanitized to ensure passwords are NEVER disclosed in error responses or logs.
- **Gate 4: Secure `.env` Generation:** Writes `core/.env` with production defaults (`APP_ENV=production`, `APP_DEBUG=false`, `STRIPE_MODE=sandbox`, `PAYPAL_MODE=sandbox`) and a cryptographically secure 32-byte CSPRNG application key (`base64:...`). Sets file permissions to `0600`.
- **Gate 5: Guarded Migrations & Owner Seeding:** Bootstraps Laravel console kernel, executes `Artisan::call('migrate', ['--force' => true])`, runs `SettingsSeeder`, and idempotently creates or updates the owner administrator (`role=owner`).
- **Gate 6: Lock File Establishment:** Writes JSON marker `core/storage/installed` containing timestamp, platform version, and owner email, with permissions `0644`.

## Data and flow boundaries

- Product, variant, price, stock, shipping-rate, currency, and discount totals are recalculated server-side at checkout.
- Orders use explicit states such as `pending_payment`, `awaiting_bank_verification`, `paid`, `fulfillment_pending`, `fulfilled`, `cancelled`, `refunded`, and `failed` with guarded transitions and audit events.
- Stripe and PayPal integrations verify server-side callbacks, signature, amount, currency, and event idempotency before marking an order paid.
- Manual bank transfer creates an unpaid order, shows owner-configured instructions, and requires owner reconciliation before fulfillment or download entitlement.
- Digital audio is private. Downloads require an authenticated owner and a valid paid entitlement; refunds/reversals suspend access.
- Bookings are quote requests with `new`, `reviewing`, `quoted`, `accepted`, `declined`, and `cancelled` states. No public fee is exposed.
- Owner edits use controlled page section types and an allowlisted palette. Record admin actor, object, timestamp, and consequential changes.

## Compatibility gates

Before declaring any upload package ready, verify the named cPanel plan for: PHP version and selectable handler, required extensions including `pdo_pgsql`, PostgreSQL availability and remote connection policy, document-root/rewrite behavior, storage permissions/quota, maximum upload/post sizes, cron support, outbound HTTPS, SMTP, SSL, and backup/restore options. If PostgreSQL is not available on the chosen plan, deployment is blocked until the user selects a compatible database host or approves a different architecture.

## Database engine compatibility evaluation (PostgreSQL vs MySQL vs MariaDB)

PostgreSQL 17 (with local container testing on port 5437 and target compatibility across PostgreSQL 15–18) is the **current tested baseline**. MySQL 8.0 and MariaDB 10.11 are **preliminary architectural assessment targets only, not supported or certified engines**. Prince Byron does not claim multi-driver support without an automated, passing test matrix across all candidate engines.

Supporting MySQL or MariaDB alongside PostgreSQL requires addressing four concrete technical friction points:

### 1. Immutability Triggers on Audit Logs
- **PostgreSQL (Tested Baseline)**: Implemented via a stored procedural language function (`prevent_audit_events_mutation()`) and a single combined `BEFORE UPDATE OR DELETE` trigger on `audit_events`.
- **MySQL 8.0 & MariaDB 10.6+ (Assessed Targets)**: Neither engine supports PL/pgSQL nor combined `BEFORE UPDATE OR DELETE` triggers. Dual-driver support requires two distinct triggers (`audit_events_before_update` and `audit_events_before_delete`) using `SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Direct modification or deletion of audit logs is forbidden.'`. Additionally, some shared cPanel hosts strictly revoke or disable `TRIGGER` privileges on MySQL databases, which would prevent migration completion.
- **SQLite (Local Testing)**: Handled via `BEFORE UPDATE` and `BEFORE DELETE` triggers raising `RAISE(ABORT, '...')`.

### 2. JSON Storage and Query Semantics
- **PostgreSQL (Tested Baseline)**: Native binary `jsonb` with GIN indexing capability, used for flexible metadata and event payloads.
- **MySQL 8.0+ (Assessed Target)**: Native binary `JSON` (RFC 7159) supporting functional indexing and JSON path expressions.
- **MariaDB 10.6–11.4 (Assessed Target)**: Does not have a native binary JSON type; it aliases `JSON` to `LONGTEXT` with a `CHECK (JSON_VALID(...))` constraint. Indexing requires generated virtual columns, behaving differently under high-throughput workloads.

### 3. Concurrency and Pessimistic Locking
- **PostgreSQL (Tested Baseline)**: `ProductVariant::lockForUpdate()` executes `SELECT ... FOR UPDATE` using MVCC row-level exclusive locks under the default `READ COMMITTED` transaction isolation level.
- **MySQL (InnoDB) (Assessed Target)**: Defaults to `REPEATABLE READ` isolation where `SELECT ... FOR UPDATE` acquires next-key locks (combining record locks and gap locks). Under simultaneous checkout of multiple items or variants, gap locks increase deadlock frequency unless queries are strictly ordered and indexed by unique primary keys.

### 4. Schema Dialects and Type Coercion
- PostgreSQL uses native 1-byte booleans (`BOOLEAN`, `true`/`false`), whereas MySQL/MariaDB use `TINYINT(1)` (`1`/`0`).
- PostgreSQL uses strictly typed `VARCHAR` / `TEXT` arrays and enums; MySQL uses column-level `ENUM` or raw strings.
- Auto-increment semantics differ: PostgreSQL uses schema sequences (`SERIAL` / `GENERATED ALWAYS AS IDENTITY`), whereas MySQL/MariaDB rely on table-level `AUTO_INCREMENT`.

### 5. Multi-Driver Installer Adaptations
Supporting MySQL/MariaDB in `install/index.php` requires:
- Adding a driver selection toggle (`PostgreSQL (Default)` vs `MySQL / MariaDB`).
- Preflight validation of `pdo_mysql` alongside `pdo_pgsql`.
- Dynamic port assignment (3306 for MySQL vs 5432 for PostgreSQL).
- Dialect-aware DSN generation (`mysql:host=...;port=3306;dbname=...;charset=utf8mb4`).
- Writing `DB_CONNECTION=mysql` and relevant host parameters into `core/.env`.

### 6. Preliminary Effort Estimate and Commercial Scope Boundary
- **Preliminary Estimate Only**: Approximately 12–16 engineering hours would be required to refactor migration triggers, update installer logic, configure automated multi-engine CI/CD test containers (PostgreSQL 17, MySQL 8.0, MariaDB 10.11), and execute the full test suite.
- **Contract Boundary**: The fixed $750 initial contract baseline committed to PostgreSQL. Because Prince Byron does not claim multi-driver support without an automated passing test matrix, dual-driver certification is a preliminary proposal only. No dual-driver work will begin, and the fixed $750 boundary remains unchanged, unless the owner explicitly approves a separate change order.

## External database and Redis architecture

When shared cPanel hosting is paired with external managed database services (e.g., Supabase, Neon, AWS RDS, DigitalOcean Managed PostgreSQL) or external Redis (Upstash, Redis Cloud):

### External PostgreSQL Safeguards
1. **Transport Security**: TLS 1.3/1.2 must be strictly enforced via `sslmode=require` or `sslmode=verify-full`. Unencrypted external database traffic across the public internet is strictly rejected under ASVS 5.0 L2.
2. **Network Topology & Latency Budget**: The cPanel host and external database must reside in the same physical cloud region / availability zone (e.g., US-East to US-East). Typical web requests execute 8–15 queries; cross-region round-trip latency (60–100ms per RTT) adds 500ms–1.5s to TTFB, violating the Core Web Vitals target (LCP ≤ 2.5s). Intra-region latency must measure < 2–5ms.
3. **Firewall & Security Groups**: The external database must restrict inbound connections via security group whitelisting to the dedicated static outbound IPv4/IPv6 address of the cPanel server.
4. **Credential Isolation**: Database credentials must strictly reside in `core/.env` on the host filesystem (`chmod 0600`). Database passwords must NEVER be entered into or exposed through CMS settings or database tables.
5. **Connection Resilience**: Connection pools and timeouts must be configured to gracefully return user-friendly maintenance notices rather than leaking connection strings or stack traces on temporary external network interruptions.

### External Redis Safeguards
1. **Transport Encryption**: Require TLS (`REDIS_SCHEME=tls`, default port 6380) with strong password authentication (`REDIS_PASSWORD`).
2. **Latency Considerations**: Every session verification, rate-limit check, and cache read incurs a round-trip network hop. If low-latency co-location cannot be guaranteed, local file drivers are preferred over remote Redis.
3. **Credential Storage**: Managed strictly through environment variables (`core/.env`).

## Hosting configuration matrix and no-Redis shared hosting profile

| Deployment Scenario | Database Driver | Session Driver | Cache Driver | Queue Driver | Cron Requirement | Suitability & Status |
|---|---|---|---|---|---|---|
| **A: Standard cPanel (Default)** | PostgreSQL 15–18 (Local/Managed) | `file` (`storage/framework/sessions`) | `file` (`storage/framework/cache`) | `sync` or `database` | `* * * * * php artisan schedule:run` | **Tested Baseline**. Zero external dependencies; self-contained within cPanel account. |
| **B: cPanel + MySQL/MariaDB** | MySQL 8.0 / MariaDB 10.11 | `file` | `file` | `sync` or `database` | `* * * * * php artisan schedule:run` | Assessed target only; not certified. Requires separate change order and passing multi-driver test suite. |
| **C: cPanel + External DB/Redis** | External Managed PostgreSQL (TLS) | `redis` (TLS co-located) or `file` | `redis` (TLS co-located) or `file` | `redis` or `database` | Systemd worker or cron daemon | High-performance decoupled tier. Requires regional co-location to satisfy TTFB budgets. |

### Baseline No-Redis Shared Hosting Configuration
For standard cPanel environments lacking Redis:
- **Sessions**: Stored in `core/storage/framework/sessions/` (`chmod 0750`). Direct web access is blocked by root `.htaccess` and root loader defense-in-depth rules.
- **Cache**: Stored in `core/storage/framework/cache/data/` (`chmod 0750`). Used for lightweight rate limiting and route caching.
- **Asynchronous Work & Queues**: Background tasks (booking notification emails, webhook reconciliation retries) utilize `QUEUE_CONNECTION=database` driven by a standard cPanel cron job executing every minute:
  ```bash
  * * * * * cd /home/username/public_html/core && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
  ```
  with queue worker bounded to avoid running out of memory:
  ```bash
  /usr/local/bin/php artisan queue:work --stop-when-empty --max-time=50 --tries=3
  ```

## Optional Cloudflare proxy deployment guidance (Unverified in Local Testing)

Cloudflare proxying is entirely optional; the application is designed to function directly behind standard Apache/cPanel web servers without an edge proxy (which was the exact direct-loopback architecture exercised during local development and testing). If the owner chooses to route traffic through Cloudflare:

### 1. SSL/TLS Encryption Mode
- **Guidance**: Must be configured to **Full (Strict)**.
- **Rule**: Cloudflare must validate the origin server's SSL certificate (issued via Let's Encrypt, cPanel AutoSSL, or a Cloudflare Origin CA certificate).
- **Prohibition**: Mode **Flexible** is strictly forbidden. Flexible mode terminates SSL at Cloudflare and forwards traffic over cleartext HTTP to the origin, which breaks HTTPS enforcement, creates endless redirect loops, and exposes checkout and authentication credentials across the public transit link.

### 2. Client IP Restoration
- Cloudflare acts as a reverse proxy, masking client IPs with Cloudflare IP ranges.
- If deployed behind Cloudflare, Laravel's `TrustProxies` middleware should be configured to trust Cloudflare's published IP ranges (IPv4 and IPv6) and extract the real visitor IP from the `CF-Connecting-IP` header.
- This ensures authentication rate limiting, booking rate limiting, and audit logs accurately record the customer's true IP rather than shared proxy nodes.

### 3. Page Rules and Cache Bypass Configuration
To prevent customer session state, CSRF tokens, or administrative interfaces from being cached at the edge:
- **Cache Bypass (Dynamic Routes)**:
  - `/admin*` -> Cache Level: Bypass
  - `/account*` -> Cache Level: Bypass
  - `/checkout*` -> Cache Level: Bypass
  - `/cart*` -> Cache Level: Bypass
  - `/webhooks/*` -> Cache Level: Bypass
  - `/livewire*` -> Cache Level: Bypass
  - `/api/*` -> Cache Level: Bypass
- **Edge Caching (Static Assets)**:
  - `/build/*` -> Cache Level: Cache Everything, Edge Cache TTL: 1 month (immutable hashed assets).
  - `/assets/*` -> Cache Level: Cache Everything, Edge Cache TTL: 1 month.

### 4. Narrow Webhook WAF Rule (No Broad Bypasses)
- Cloudflare's **Bot Fight Mode** or **Managed Challenge** can inadvertently challenge automated webhook HTTP POST requests dispatched by Stripe and PayPal servers.
- If enabled, a narrowly scoped WAF Skip Rule should be created specifically for webhook paths:
  - *Condition*: `(http.request.uri.path in {"/webhooks/stripe" "/webhooks/paypal"})`
  - *Action*: Skip Managed Challenge / Bot Fight Mode only.
  - Do NOT broadly disable WAF, DDoS protection, or rate-limiting for the whole domain.
  - Application-layer cryptographic signature verification (HMAC for Stripe, asymmetric RSA for PayPal), timestamp validation, replay protection, and IP rate limits remain fully enforced by Laravel.

## Backups and recovery

- Schedule database backups and retain encrypted off-host copies; test a restore before launch and periodically thereafter.
- Keep media backups separate and do not place private audio in public asset storage.
- Each upload release is versioned and reversible. Back up the current files and database before a migration; use backward-compatible migrations where possible and document rollback limits.
- Log payment, webhook, booking, and installer failures with correlation IDs, but redact passwords, tokens, bank credentials, and customer secrets.
- Use a database-backed queue only where shared hosting cannot run workers, with cron-driven bounded processing; if cron is unavailable, disable asynchronous claims and use a monitored fallback.
