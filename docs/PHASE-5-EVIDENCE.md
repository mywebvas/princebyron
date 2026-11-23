# Phase 5 Implementation & Verification Evidence: cPanel Release Package & One-Time Installer

> **Commercial & Environment Boundary Notice:**
> - Preserves the fixed **\$750 contract boundary** for Prince Byron's gospel platform.
> - Development database runs in isolation on Docker PostgreSQL container (`127.0.0.1:5437`); the PC's primary PostgreSQL 17.9 service on port 5432 remains completely untouched and unaffected.
> - Stripe and PayPal integrations are strictly verified in **sandbox mode**; zero real payment credentials, live webhooks, or external payments are activated.
> - Email delivery is routed strictly to local Mailpit (`127.0.0.1:1025`); no live external emails are dispatched.

---

## 1. Read-Only Vazzet Architectural Comparison & Security Resolution

The legacy Vazzet codebase (`Files/`) was inspected strictly read-only to evaluate deployment conventions, payment callback implementations, and installer mechanics against OWASP ASVS 5.0 Level 2 and modern Laravel 13 standards.

| Architectural Dimension | Vazzet Legacy Mechanism | Identified Vulnerability / Risk | Prince Byron Modern Architecture | Verification Evidence |
|---|---|---|---|---|
| **Root Web Entry & Isolation** | Root `index.php` delegates to `core/public/index.php`. | Missing defense-in-depth URI sanitization; single point of failure if Apache rewrite rules are ignored or misconfigured. | Root `index.php` explicitly parses `$_SERVER['REQUEST_URI']`, normalizes the path, and rejects direct access to `/core`, `/.env*`, `/vendor`, `/storage`, `/.git`, `.sql`, `.log`, and `.lock` with **HTTP 403 Forbidden**. | `Phase5InstallerTest::test_root_loader_blocks_private_directories_and_sensitive_files` |
| **Installer Re-Run Gate** | Checked for presence of install lock file. | **CRITICAL FLAW:** Permitted re-installation bypass using `?force=1` query parameter, allowing unauthenticated attackers to re-run migrations, overwrite the production database, and hijack owner accounts. | **Permanent Lockout Gate:** Line 24 of `install/index.php` strictly returns **HTTP 403 Forbidden** whenever `core/storage/installed` exists. Absolutely NO query parameter (`?force=1`, `?bypass=true`, `?action=execute`) can bypass the lock. Recovery requires authenticated filesystem access via SSH or cPanel File Manager. | `Phase5InstallerTest::test_installer_lockout_cannot_be_bypassed_by_query_parameters` |
| **Stripe Gateway Handling** | Hardcoded sample fallback keys and demo webhook secrets in configuration. | Fail-open vulnerability: unconfigured webhook secrets allowed forged payloads to mark orders as paid without cryptographic verification. | **Fail-Closed Architecture:** Removed all hardcoded fallbacks from `config/services.php`. Missing webhook secrets immediately abort with **HTTP 500**. Payloads require exact raw body, `Stripe-Signature` timestamp/signature, and endpoint secret verification. | `Phase4CommerceTest::test_stripe_webhook_fails_closed_when_secret_is_unconfigured` |
| **PayPal Gateway Handling** | Proprietary shared-secret HMAC hashing. | Non-standard, failing to adhere to official PayPal webhook verification specifications. | **Official Asymmetric Verification:** Implemented `SHA256withRSA` asymmetric verification using `openssl_verify` across all 5 official PayPal headers (`PAYPAL-TRANSMISSION-ID`, `PAYPAL-TRANSMISSION-TIME`, `PAYPAL-TRANSMISSION-SIG`, `PAYPAL-CERT-URL`, `PAYPAL-AUTH-ALGO`). Validates certificate domain against `api.paypal.com` or `api.sandbox.paypal.com` to prevent SSRF. | `Phase4CommerceTest::test_paypal_webhook_verifies_asymmetric_signature_and_marks_order_paid` |
| **Inventory & Concurrency** | Non-atomic stock deduction outside database transactions. | Severe race conditions and overselling under concurrent checkout requests. | **Atomic Pessimistic Row Locking:** Uses `ProductVariant::lockForUpdate()` within `DB::transaction()` and atomic conditional decrements (`where('stock_quantity', '>=', $qty)->decrement(...)`) ensuring stock never drops below zero. | `Phase4CommerceTest::test_atomic_stock_reservation_prevents_overselling_under_race_conditions` |
| **Digital Download Quotas** | Incremented download count immediately when link was accessed, regardless of file availability. | Consumed customer download entitlements when high-resolution master studio audio was still pending release. | **Safe Entitlement Guard:** Download counter and `last_downloaded_at` are incremented ONLY when the physical audio file exists on disk and is streamed. Pending studio masters state preserves customer entitlement balance. | `Phase4CommerceTest::test_download_count_is_not_consumed_when_master_audio_is_pending` |
| **Audit Log Integrity** | Filament UI marked read-only, but records remained mutable at the Eloquent and DB layer. | Non-compliance with ASVS 5.0 audit logging requirements; administrative or SQL tampering could alter history. | **Append-Only Immutability:** Enforced at both Eloquent model layer (`updating` and `deleting` events fail closed) and database trigger layer (PostgreSQL and SQLite trigger rules raising exceptions on direct UPDATE or DELETE queries). | `Phase4CommerceTest::test_audit_events_are_strictly_immutable_and_append_only` |

---

## 2. Phase 5 Package Architecture

### 2.1 Root Loader (`index.php`)
- Positioned in `public_html/` on cPanel hosts.
- Inspects `$_SERVER['REQUEST_URI']` using `parse_url()` and `rawurldecode()`.
- Blocks private prefixes: `/core`, `/vendor`, `/storage`, `/.env`, `/.git`.
- Blocks sensitive file extensions: `.sql`, `.log`, `.lock`, `.env`.
- Auto-redirects to `/install/` on first-time deployment if uninstalled.
- Hands off all valid application requests to `core/public/index.php`.

### 2.2 Apache Server Protections (`.htaccess` and `install/.htaccess`)
- Employs Apache `mod_rewrite` rules with `[F,L]` flags denying direct access to `core/`, `vendor/`, `storage/`, `\.env`, and `\.git`.
- Strict `<FilesMatch>` directive denying sensitive extensions (`.lock`, `.json`, `.sql`, `.log`, `.md`, `composer.`).
- Direct routing of static build assets (`/build/*` and `/assets/*`) directly to `core/public/build/` and `core/public/assets/`.
- Injects baseline OWASP ASVS 5.0 Level 2 security headers:
  - `X-Content-Type-Options: "nosniff"`
  - `X-Frame-Options: "SAMEORIGIN"`
  - `X-XSS-Protection: "1; mode=block"`
  - `Referrer-Policy: "strict-origin-when-cross-origin"`
- Configures `mod_deflate` GZIP compression across HTML, CSS, JS, JSON, SVG, and WOFF2 fonts.
- Configures `mod_expires` caching: 1 year (`max-age=31536000, public, immutable`) for static assets, 1 month for compiled CSS/JS.

### 2.3 One-Time HTTPS Browser Installer (`install/index.php`)
- **Step 1: Environmental Preflight Verification:**
  - Validates PHP version >= 8.3 (tested on PHP 8.5.0-dev).
  - Validates required extensions: `pdo`, `pdo_pgsql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `curl`, `fileinfo`.
  - Validates writable storage and cache paths (`core/bootstrap/cache/`, `core/storage/`, and subdirectories).
- **Step 2: HTTPS & Trusted Proxy Enforcement:**
  - Enforces HTTPS on all remote hosts for both GET and POST requests; cleartext HTTP requests are strictly rejected with **HTTP 403 Forbidden**.
  - Local HTTP exception is strictly restricted to direct loopback client connections (`REMOTE_ADDR` is `127.0.0.1` or `::1` with no proxy forwarding headers). Remote clients attempting to spoof `Host: localhost` over cleartext HTTP are strictly rejected.
  - Safely handles reverse proxies by verifying `REMOTE_ADDR` against trusted loopback and private subnets (`127.0.0.1`, `::1`, `10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16`) before honoring `X-Forwarded-Proto`. Untrusted client proxy spoofing is ignored and rejected.
- **Step 3: Per-Session CSRF Protection:**
  - Generates a 32-byte CSPRNG session token (`installer_csrf_token`) on session start.
  - Form includes `<input type="hidden" name="csrf_token">` and validates submissions via `hash_equals()`; invalid or missing tokens fail closed.
- **Step 4: Single-Process Execution Mutex (Stable Inode):**
  - Uses `flock(LOCK_EX | LOCK_NB)` on `core/storage/install_execution.lock` to ensure only one setup process can execute at a time.
  - The lock file is permanently preserved on disk across executions (never unlinked), ensuring all concurrent requests lock the exact same filesystem inode and preventing delete/recreate race conditions.
  - Re-checks the installed marker under the lock to prevent duplicate migration runs or competing owner account creation.
- **Step 5: Safe .env Encoding & Byte-Verified Writes:**
  - Rejects newline characters across all input fields, preventing `.env` splitting attacks.
  - Validates numeric port ranges (1–65535).
  - Escapes quotes, backslashes, and variable symbols using `escapeEnv()`.
  - Verifies exact byte length on disk after write; fails closed if the file write is incomplete.
- **Step 6: Guarded Migrations, Owner Bootstrap & Permanent Lockout:**
  - Bootstraps Laravel console kernel and runs `Artisan::call('migrate', ['--force' => true])`.
  - Runs `SettingsSeeder` to establish default platform settings.
  - Idempotently creates or updates the owner administrator (`role=owner`).
  - Writes `core/storage/installed` containing timestamp, version, and owner email with `0644` permissions, permanently locking the installer.
  - If execution fails mid-flight, partial `.env` files are unlinked to enable a clean retry.
- **Step 7: Protected Server-Side Diagnostic Logging:**
  - Full exception traces are logged server-side to `core/storage/logs/install.log` (with passwords redacted).
  - Client response receives only safe, generic, actionable error messages with zero database or SQLSTATE leakage.

---

## 3. Automated Test Suite Execution Evidence

### 3.1 PostgreSQL Container Suite (`phpunit.pgsql.xml`)
- **Target Database:** PostgreSQL 17.9 running on `127.0.0.1:5437` (`princebyron_postgres`).
- **Command:** `C:\php85\php.exe vendor/phpunit/phpunit/phpunit -c phpunit.pgsql.xml`
- **Result:**
  ```text
  Tests: 70, Assertions: 491, Status: PASSED (Duration: 29.4s)
  ```

### 3.2 Standard Artisan Test Runner (`artisan test`)
- **Target:** Full application test suite including Phase 5 installer and root loader scenarios.
- **Command:** `C:\php85\php.exe artisan test`
- **Result:**
  ```text
  Tests: 71, Assertions: 492, Status: PASSED (Duration: 27.5s)
  ```

### 3.3 Phase 5 Installer Test Breakdown (`Phase5InstallerTest.php`)
| Test Scenario | Purpose | Assertions | Status |
|---|---|---|---|
| `test_root_loader_blocks_private_directories_and_sensitive_files` | Verifies root `index.php` denies `/core/`, `/.env*`, `/vendor/`, `/storage/`, `/.git/`, `.sql`, `.log`, `.lock` with HTTP 403. | 28 | PASSED |
| `test_root_htaccess_and_install_htaccess_security_rules` | Verifies Apache path denial, ASVS L2 security headers, GZIP compression, and asset routing. | 14 | PASSED |
| `test_installer_preflight_checks_pass_in_approved_environment` | Verifies preflight requirements detection for PHP >= 8.3, `pdo_pgsql`, required extensions, and writable folders. | 8 | PASSED |
| `test_installer_locks_permanently_when_installed_marker_exists` | Verifies `install/index.php` returns HTTP 403 Forbidden with lockout screen once installed. | 4 | PASSED |
| `test_installer_lockout_cannot_be_bypassed_by_query_parameters` | Verifies query parameters (`?force=1`, `?bypass=true`, `?action=execute`, etc.) cannot bypass lockout. | 14 | PASSED |
| `test_installer_lockout_provides_documented_recovery_path` | Verifies lockout screen directs administrator to filesystem deletion via SSH / cPanel File Manager. | 3 | PASSED |
| `test_installer_validation_fails_on_missing_required_fields` | Verifies field validation for database credentials, administrator fields, email format, and password length/confirmation. | 5 | PASSED |
| `test_installer_sanitizes_database_errors_and_never_leaks_raw_passwords` | Verifies wrong database credentials produce sanitized error without disclosing the cleartext password. | 2 | PASSED |
| `test_installer_app_key_generation_is_cryptographically_secure` | Verifies application key is 32-byte CSPRNG string prefixed with `base64:`. | 3 | PASSED |
| `test_installer_env_defaults_enforce_production_and_sandbox_modes` | Verifies `.env` template defaults to `production`, `debug=false`, `pgsql`, and `sandbox` gateways. | 5 | PASSED |
| `test_installer_enforces_https_and_rejects_cleartext_http_on_remote_hosts` | Verifies GET and POST requests over cleartext HTTP on remote hosts return HTTP 403 Forbidden. | 4 | PASSED |
| `test_installer_rejects_untrusted_client_forwarded_proto_headers` | Verifies spoofed `X-Forwarded-Proto: https` from untrusted client IPs is rejected with HTTP 403 Forbidden. | 2 | PASSED |
| `test_installer_accepts_https_from_trusted_reverse_proxy` | Verifies `X-Forwarded-Proto: https` from trusted reverse proxies (private subnets/loopback) is accepted. | 2 | PASSED |
| `test_installer_requires_and_validates_csrf_token` | Verifies POST submissions with missing or invalid CSRF tokens are rejected. | 2 | PASSED |
| `test_installer_single_process_safety_prevents_simultaneous_execution` | Verifies file lock mutex prevents concurrent setup execution. | 2 | PASSED |
| `test_installer_rejects_newline_injection_in_inputs` | Verifies newline characters in inputs are rejected to prevent `.env` header splitting. | 1 | PASSED |
| `test_installer_logs_diagnostics_to_server_log_without_leaking_to_client` | Verifies detailed connection traces are logged to `install.log` while client receives safe generic message. | 5 | PASSED |
| `test_installer_cleans_up_partial_env_on_failed_migration_for_clean_retry` | Verifies failed installations do not create `installed` lock and clean partial `.env` for retry. | 3 | PASSED |
| `test_installer_rejects_remote_http_request_with_spoofed_localhost_host_header` | Verifies remote HTTP requests with Host: localhost cannot bypass HTTPS enforcement. | 4 | PASSED |
| `test_installer_stable_lock_file_persists_and_prevents_delete_recreate_races` | Verifies mutex lock file persists on disk across executions, preventing delete/recreate races. | 4 | PASSED |

---

## 4. Remaining Shared Hosting Decisions & Host Assumptions

Before deploying this package to the client's actual production hosting:
1. **cPanel Hosting Plan Confirmation:** The owner must confirm the specific cPanel provider and plan. The host MUST provide:
   - PHP runtime >= 8.3 (with `pdo_pgsql`, `mbstring`, `openssl`, `curl`, `fileinfo`).
   - A reachable PostgreSQL database (version 15, 16, 17, or 18) with user privileges for tables, sequences, indexes, and triggers.
   - Cron job scheduler support for bounded queue / scheduled tasks.
   - Outbound HTTPS cURL capabilities for payment gateway webhook callbacks and SMTP access for Mailpit / production email.
2. **Production Gateway Activation:** Live Stripe and PayPal credentials must be supplied directly by the owner through a secure channel; they are never committed to version control.
3. **Domain & SSL Configuration:** A valid SSL/TLS certificate (e.g. Let's Encrypt / cPanel AutoSSL) must be active on the domain before running the installer.
