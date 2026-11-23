# Implementation roadmap

Use the checkboxes to record completed work. Do not mark a task complete without the named evidence. The phases separate local setup, design approval, production features, and the fixed $750 contract boundary. The user has directed that all production implementation happen in Antigravity; this package here is limited to the prototype and documentation handoff. Follow `docs/ANTIGRAVITY-HANDOFF.md` before beginning Phase 1.

## Phase 0 — Foundation and scope (current)

- [x] Record approved product decisions and unresolved host verification.
- [x] Preserve the existing static prototype under `/prototype` as a reference-only artifact.
- [x] Define the initial architecture, security boundaries, and phased build sequence.
- [ ] Reconcile the feature roadmap against the written $750 deliverables before treating any item as contracted work.
- [ ] Confirm exact shared-host plan later; this roadmap targets generic cPanel only until then.

## Phase 1 — Local development environment and application base

- [x] Inventory installed Docker Desktop, PostgreSQL, PHP 8.5, Composer, Node, enabled PHP extensions, running services, and free disk; record exact versions and distinguish host installs from containers. (Evidence: [`docs/PHASE-1-EVIDENCE.md`](file:///c:/Users/autoc/Documents/princebyron/docs/PHASE-1-EVIDENCE.md#1-workstation--environment-inventory))
- [x] Decide on a reproducible local database runtime using the existing Docker/PostgreSQL setup; create a dedicated least-privilege project database without replacing or reconfiguring unrelated services. (Evidence: [`docs/PHASE-1-EVIDENCE.md`](file:///c:/Users/autoc/Documents/princebyron/docs/PHASE-1-EVIDENCE.md#2-containerized-services--health-status))
- [x] Preserve existing PC software and databases; make any required version change only after compatibility evidence, backup, and rollback plan. (Evidence: [`docs/PHASE-1-EVIDENCE.md`](file:///c:/Users/autoc/Documents/princebyron/docs/PHASE-1-EVIDENCE.md#1-workstation--environment-inventory))
- [x] Scaffold Laravel 13+ and lock dependency versions; add Tailwind CSS 4/Vite, Alpine.js, Lucide SVG subset, and Filament 5 only after compatibility checks. (Evidence: [`docs/PHASE-1-EVIDENCE.md`](file:///c:/Users/autoc/Documents/princebyron/docs/PHASE-1-EVIDENCE.md#3-technology-stack--compatibility-rationale))
- [x] Add safe `.env.example`, local bootstrap instructions, database migrations, seed data, and a health check. Never commit `.env`. (Evidence: [`docs/PHASE-1-EVIDENCE.md`](file:///c:/Users/autoc/Documents/princebyron/docs/PHASE-1-EVIDENCE.md#4-database-migrations--seed-replay-evidence))
- [x] Verify idempotent local setup, migration replay, and owner/customer authorization boundaries. (Evidence: [`docs/PHASE-1-EVIDENCE.md`](file:///c:/Users/autoc/Documents/princebyron/docs/PHASE-1-EVIDENCE.md#5-automated-test-suite-results))
- [x] Record initial performance budgets and repeatable measurement profiles; target good Core Web Vitals at the 75th percentile as production targets. Meaningful field measurement is deferred until a deployed production build has real traffic; prototype checks verify zero CDN requests, layout stability, and asset weight qualitatively. (Documented in [`docs/DESIGN_REVIEW.md`](file:///c:/Users/autoc/Documents/princebyron/docs/DESIGN_REVIEW.md#5-performance--verification-boundaries))
- [x] Adopt OWASP ASVS 5.0 Level 2 as the application verification target and track applicable controls and exceptions. Phase 1 codified baseline controls; full ASVS verification is an ongoing lifecycle process. (Evidence: [`docs/PHASE-1-EVIDENCE.md`](file:///c:/Users/autoc/Documents/princebyron/docs/PHASE-1-EVIDENCE.md#6-phase-1-implemented-security-controls))

## Phase 2 — Premium visual design and review

- [x] Extract the two supplied artist photos into the project assets without facial retouching; verify source rights and image quality. (Assets: `design-review/assets/images/prince-byron-hero.jpeg` and `prince-byron-portrait.jpeg`)
- [x] Define concrete design tokens, typography, responsive grid, image treatment, motion, focus, contrast, and reduced-motion rules from the earthy palette. (Documented in `docs/DESIGN_REVIEW.md`)
- [x] Produce a separate clickable review concept for music/store/checkout and booking paths; it is available in `/design-review` and is not yet approved for production.
- [x] Show loading, empty, invalid, pending, offline/unavailable, success, retry, cancellation and return states where applicable. (Simulated in `design-review/index.html` via floating dock)
- [x] Review phone/tablet/desktop, basic navigation and interactions, validation, demo checkout, booking, and mobile overflow; preserve the review URL and design notes.
- [ ] Complete keyboard-only and assistive-technology review before production design approval. (In progress: `:focus-visible`, skip link, modal dialog focus traps, and mathematical color contrast ratios >= 6.2:1 verified in prototype; formal screen-reader testing across JAWS/NVDA/VoiceOver and production dynamic states remain an ongoing requirement).
- [ ] Include low-end mobile/low-bandwidth use, WCAG 2.2 AA, responsive states, and reduced-motion behavior in the concrete design review. (In progress: prototype incorporates zero CDN fonts, local system font stack, and reduced-motion resets; full WCAG 2.2 AA conformance is an ongoing acceptance target through production release).
- [x] Capture the owner's approve/revise/reject decision for the concrete visual direction in Antigravity before building production screens. (Owner approved the visual hybrid direction on 2026-11-17 and authorized Phase 2 production screens).
- [x] Only after approval, build the production Blade/Alpine interface and admin visual theme. Do not carry prototype review controls into production. (Evidence: Built production Blade templates in `core/resources/views/`, compiled Tailwind CSS 4 assets via Vite in `core/public/build/`, established dedicated routes in `core/routes/web.php` for Home, About, Music, Store, single-product detail, Cart, Checkout, Booking, Account, and Demo Admin Preview; verified 27 passing tests in PHPUnit test suite).

## Phase 3 — Backend foundation and owner CMS

- [x] Create data model and migrations for users, owner permissions, pages/section types, settings, products/variants, music/offers, bookings, orders, payments, entitlements and audit events. (Evidence: PostgreSQL migrations 000001 through 000009 in `core/database/migrations/`, models in `core/app/Models/`, seeders in `core/database/seeders/`; verified against PostgreSQL 17.9 container on port 5437).
- [x] Build owner authentication, secure account recovery, policies, rate limits and audit history. (Evidence: Filament 5 `AdminPanelProvider.php` with `User::canAccessPanel()` role check, password reset, profile, rate limiting; immutable `AuditEventResource` with `canCreate/Edit/Delete` disabled; verified in `core/tests/Feature/Phase3BackendTest.php` and `AuthorizationTest.php`).
- [x] Build the controlled section editor for supported text, image, ordering, visibility and allowlisted colors. (Evidence: `ContentSectionResource.php` and `ContentSection.php` with HTML/script stripping on write and strict enforcement of the allowlisted 5-color palette: `#0E0B09`, `#181310`, `#DFA759`, `#9E472A`, `#F8F4ED`; tested in `Phase3BackendTest.php`).
- [x] Build owner management for music prices/free status, product prices/stock/options, owner-managed shipping zones/rates, booking requests and order review. (Evidence: `SongResource.php`, `ProductResource.php` with variants/inventory, `ShippingRateResource.php`, `BookingRequestResource.php`, `SettingResource.php` with encrypted secrets, and `OrderResource.php` with independent bank transfer verification action; tested in `Phase3BackendTest.php`).
- [x] Validate all ownership, cross-account denial, server-side pricing and lifecycle transitions. (Evidence: 35 passing tests in `core/tests/` covering state machine transitions, cross-account denial, unauthorized backoffice rejection, bank transfer verification, and hardened `/up` health check without diagnostic leakage).

## Phase 4 — Customer flows and live commerce

- [x] Build product discovery, product variants, cart persistence, USD checkout and order confirmation. (Evidence: `CartService.php` with server-authoritative live recalculation; `CheckoutService.php` with stock decrement and integer-cents calculation; verified in `Phase4CommerceTest.php`).
- [x] Integrate Stripe and PayPal in sandbox; verify signatures, callbacks, idempotency, amount/currency, refund and failure states. (Evidence: `StripeSandboxService.php` and `PayPalSandboxService.php` with HMAC signature verification, replay protection against duplicate event IDs, amount/currency verification, and refund entitlement revocation; tested in `Phase4CommerceTest.php`).
- [x] Add manual bank transfer as an awaiting-verification payment; owner records independent reconciliation before paid status. (Evidence: Order created in `awaiting_bank_verification` with clearly labeled demo instructions; digital entitlements withheld until owner records verified bank receipt in backoffice; tested in `Phase4CommerceTest.php` and `OrderStateMachineTest.php`).
- [x] Add owner-managed shipping rates and destination allowlists; resolve tax collection and customer policy with qualified advisors before release. (Evidence: Inactive demo seed rates require owner configuration before physical checkout; unconfigured state gracefully alerts customer; server-authoritative flat rate and free shipping calculation in `ShippingRate.php` tested in `Phase4CommerceTest.php`).
- [x] Build fan accounts and customer-owned order history. (Evidence: `CustomerAuthController.php` with rate-limited registration and login; `CustomerAccountController.php` with strict cross-account isolation denying customer B from accessing customer A's orders; tested in `Phase4CommerceTest.php`).
- [x] Deliver free and paid MP3/WAV according to owner-configured catalog rules; store files privately and verify entitlements, expiry, refund and abuse behavior. (Evidence: `DownloadController.php` delivering private audio only with active unexpired entitlement; clean pending masters demo state when audio files are awaiting studio release; download count auditing; tested in `Phase4CommerceTest.php`).
- [x] Build quote-based bookings with email acknowledgement/owner notification and a recoverable admin workflow. (Evidence: `BookingController.php` with validation, rate limiting, and Mailpit dispatch of `BookingAcknowledgementMail` and `BookingNotificationMail`; tested with `Mail::fake()` in `Phase4CommerceTest.php`).

## Phase 5 — Upload-ready cPanel package

- [x] Build release artifact structure locally with locked `vendor/` and compiled frontend assets; exclude secrets, test data, local files, and development-only dependencies. (Evidence: production assets compiled in `core/public/build/`, locked production `vendor/` dependencies, and sensitive path denial configured in `.htaccess`).
- [x] Add root loader, safe `.htaccess`, private `core/` path protections, and one-time browser installer/lock. (Evidence: root loader `index.php`, root `.htaccess`, one-time HTTPS installer `install/index.php`, `install/.htaccess`; verified with 20 automated test scenarios in `core/tests/Feature/Phase5InstallerTest.php` covering directory denial, preflight requirements, lockout immutability, zero-bypass, loopback-only HTTPS exemption rejecting remote Host spoofing, trusted reverse proxy handling, CSRF token validation, stable mutex file persistence preventing inode races, newline injection prevention, credential sanitization, secure key generation, byte-verified writes, and server-side log redaction).
- [ ] Verify setup without Composer or Node on a staging cPanel account matching the target PHP/PostgreSQL/extensions/cron capabilities. (Awaiting user selection of actual cPanel shared hosting plan and staging environment).
- [ ] Verify backups, restore, log redaction, permissions, HTTPS, email, webhook reachability, storage limits, and rollback. (Automated tests verify local error sanitization, file permissions, and sandbox webhook flows; live provider verification pending hosting environment).
- [ ] Confirm the actual shared-host provider/plan and run a fresh install and customer/admin walkthrough before calling the package ready. (Awaiting owner selection of provider and plan).

## Interim Review Milestone — Local UX, Flow, Token Centralization & Hosting Readiness (Phase 6 Paused)

- [x] **Stage 1: Baseline & Architectural Analysis**:
  - Full browser audit across all public, customer, and backoffice journeys.
  - Read-only comparative analysis of legacy Vazzet architecture (root loader, `.htaccess`, one-time installer, webhooks, inventory concurrency, download entitlement gates).
  - Technical and economic evaluation of multi-engine database support: PostgreSQL 17 (tested baseline) vs MySQL 8.0 and MariaDB 10.11 (preliminary assessed targets only, not supported or certified). Preliminary effort estimate (~12–16h) documented as an uncontracted proposal; fixed $750 contract boundary remains preserved.
  - Hosting readiness matrix established: cPanel with local PostgreSQL (default), no-Redis file driver fallback (`SESSION_DRIVER=file`, `CACHE_STORE=file`, `QUEUE_CONNECTION=sync`/`database` via cron), external PostgreSQL/Redis deployment requirements with TLS and regional co-location, and optional Cloudflare proxy deployment guidance (unverified in local testing).
  - Host PC PostgreSQL 17.9 service on port 5432 preserved completely untouched; development database isolated on port 5437 (`princebyron_dev`).
- [x] **Stage 2: Cohesive Redesign & Design Token Centralization**:
  - 100% centralized design tokens: defined semantic tokens in `config/tokens.php` and compiled `tokens.css`; verified 0 raw color literals across 46 application-owned frontend files.
  - Form & UI Accessibility: Visible unfocused borders (`#8A7D71`, contrast >= 3:1 against white and paper), focus rings (`#9E472A`, contrast >= 5.4:1 on light; `#DFA759`, contrast >= 8.6:1 on dark) meeting WCAG 2.2 Level AA SC 1.4.11 and SC 2.4.7. (Note: SC 2.4.13 Focus Appearance is Level AAA and is not claimed as an AA criterion). Screen-reader and physical device testing are explicitly marked unverified.
  - Strict heading hierarchy: eliminated redundant `<h1>` in global header brand logo; enforced single page `<h1>` followed by strictly sequential `<h2>` and `<h3>` tags; corrected excessive header margin spacing.
  - Customer Dashboard (`/account`): SaaS-clean layout with 3 KPI metric cards, factual status label ("Account Status: Active • Verified", "Session authenticated; private file access"), responsive dual-view (desktop tables + mobile stacked cards) eliminating horizontal scrolling on mobile viewports, responsive itemized receipt view.
  - Public Demo Preview vs Live Backoffice: `/admin/preview` remains a public demonstration preview using clearly labeled static sample data, with no real authentication, live data, or record-modifying actions. Live operational work queues and reconciliation actions reside exclusively on `/admin` under server-side owner authorization (`OwnerWorkQueueWidget.php`).
  - Truth boundaries codified: 5 approved songs (*Free*, *Purify Me*, *I Always Win*, *Toduyun*, *Follow Me*); synthesized/acoustic preview audio explicitly labeled with official masters pending owner supply; demo merchandise and shipping rates labeled as unconfigured demo fixtures; seeded test bookings marked as `[DEMO FIXTURE]`.
- [x] **Stage 3: Local Verification & Documentation**:
  - Local review server verified on loopback at `http://127.0.0.1:8001`.
  - Comprehensive suite of 42 standardized screenshots confirmed on disk across 14 core views at 3 standard viewports (Desktop 1440x900, Tablet 768x1024, Mobile 375x812).
  - Test evidence separated: automated test suite (71 passed out of 71 tests, 492 assertions, 0 regressions) verifies backend models/policies, commerce checkout, and installer safeguards; visual design, responsive layouts, and contrast verified via manual browser/CDP checks.
  - Local review credentials (`fan@princebyron.local`, `owner@princebyron.local`) documented as local-only development review fixtures and strictly excluded from production documentation and release packages.
  - Project documentation updated: `docs/ARCHITECTURE.md`, `docs/SECURITY.md`, and `docs/ROADMAP.md`.
  - Commercial boundary preserved: Fixed $750 scope maintained; Phase 6 explicitly paused pending owner review.

## Phase 5.5 — Production readiness review and verified baseline (IN PROGRESS)

A full senior review of the codebase was completed on 2026-11-23. The findings, evidence, prioritized backlog and decisions required are recorded in [`docs/PRODUCTION-READINESS-REVIEW.md`](PRODUCTION-READINESS-REVIEW.md). The review's central conclusion: **the server-side foundation is sound, but the public storefront is not wired to it**, so earlier "Phase 4 complete" checkmarks describe code that exists and is tested, not a flow a visitor can complete. Those checkmarks are left intact for history; this phase tracks closing the gap.

### Sprint 0 — Trustworthy baseline (COMPLETE 2026-11-23)

- [x] **Add CI so every status claim is reproducible.** [`.github/workflows/ci.yml`](../.github/workflows/ci.yml): PHP syntax over every tracked PHP file, Composer manifest validation, Blade compilation, Pint style check, test suite on **PostgreSQL (primary)** and SQLite (fast pass), migration replay from an empty database, seeder idempotency, frontend build with a payload budget, and the release packaging rules. Previously the "71 tests / 492 assertions" claim had no mechanism to re-verify it (review P3-2).
- [x] **Make the release rules executable.** [`tools/check-package.mjs`](../tools/check-package.mjs) enforces what `docs/ARCHITECTURE.md` only described: required files present, compiled assets tracked, and no secrets, `.env`, logs, runtime state, local databases, dev scripts, or web-root screenshots tracked. [`tools/check-assets.mjs`](../tools/check-assets.mjs) enforces the compiled CSS/JS budget (currently 16.5 kB and 20.3 kB gzip). Both verified to fail on injected violations.
- [x] **Align the PHP floor (review P0-6).** The installer now enforces PHP ≥ 8.5 as a single `$requiredPhp` constant instead of the previous 8.3 gate that allowed a fatal-after-install outcome; `composer.json` already required `^8.5`. New test `test_installer_php_floor_matches_composer_constraint` fails CI if the two drift. A `config.platform` pin was attempted and **reverted** — it desynchronises `composer.lock` and breaks `composer install`; CI now guards lock sync with `tools/check-lock.mjs` (this was caught by the pipeline, which is the point of Sprint 0).
- [x] **Enforce preflight server-side.** The installer previously only *hid* the submit button when a requirement failed; a crafted POST could still continue. It now aborts with a clear message before touching the filesystem or database.
- [x] **Close the storage/application exposure gap.** Added `core/.htaccess` and `core/storage/.htaccess` (deny-all), so private audio, sessions, logs and `.env` stay blocked even if the root rewrite rules are bypassed or the document root is misconfigured.
- [x] **Remove dev artifacts from the application tree (review P1-13).** Deleted the committed SQLite database (`core/princebyron`), `scratch_check_pass.php`, `scratch_update_data.php`, `scratch_test_routes.cjs`, the syntactically invalid `redis_test.php`, `public/find_overflow.js`, and capture output in `storage/`. PHP syntax check over the whole tracked tree now passes (previously 1 failure).
- [x] **Move review screenshots out of the web root (review P1-2).** `core/public/snapshots/*.html` — including rendered account and order pages — moved to `docs/review-snapshots/`; capture scripts updated to write there. The packaging check now fails if anything reappears under `core/public/snapshots/`.
- [x] **Re-baseline documentation to provable facts (review P3-10, P1-12).** README now states precisely that provider *webhook verification* is complete while *payment creation* is not implemented, that the public cart/checkout/catalog are demo-data driven, and that the installer writes Mailpit mail settings. No status is asserted that CI cannot demonstrate.

### Sprint 0 — Outcome: earned green (2026-11-23)

The first CI runs **failed**, which is the point: a green baseline that has never been red proves nothing. Those runs found five defects that would otherwise have shipped silently.

- [x] **`composer.lock` was desynchronised by a `config.platform` pin** the previous commit added. `composer install` aborted, so `vendor/` could never be produced at all. Reverted; the content-hash was verified by porting Composer's own `Locker::getContentHash()` rather than guessing, and `tools/check-lock.mjs` now guards it in every PHP job.
- [x] **The installer printed "Pass" for failing checks.** A host missing a required extension was told the check passed while the install button was disabled — contradictory information at the moment certainty matters most. It now prints "Fail" and the remedy it already computed but never displayed. Regression test added.
- [x] **Preflight required `pdo_pgsql` only**, which would reject the very shared cPanel hosts this project targets. It now accepts MySQL/MariaDB or PostgreSQL and names the driver found; a host with neither still fails with actionable guidance.
- [x] **Sample prices were presented as real money with no disclosure.** Both pages that show prices now disclose that amounts are placeholders (`config/commerce.php`), and the 24-bit WAV format is marked unavailable rather than promised — selling a master file that does not exist would be a false representation at the point of sale.
- [x] **Four tests asserted strings that existed nowhere in the codebase** (`sample demonstration prices`, `In Stock`, `Ministry Appearance & Event Booking`, `Request for Quotation (RFQ)`). They could never pass, so the behaviour they nominally guarded was unprotected. All four were corrected to assert what the pages genuinely render.
- [x] **Test-configuration defects:** `phpunit.pgsql.xml` had silently lost the Unit suite; the PostgreSQL job passed `--configuration` while `artisan test` injects its own, so it ran the wrong config; and a malformed workflow step name produced a CI run with **zero jobs**. All three fixed, and `tools/check-workflows.mjs` now validates workflow YAML before it can disable the pipeline.
- [x] **Result: all five jobs green** — 73 tests / 504 assertions on both SQLite and PostgreSQL, migration replay from an empty database, seeder re-run parity, Blade compilation, PHP syntax across every tracked file, lock sync, packaging rules, asset budget.

### Owner decisions recorded

- [x] **Hosting direction (2026-11-23): shared-host compatibility via MySQL/MariaDB.** The owner chose the path that must work on any cPanel account, which requires a MySQL/MariaDB driver path rather than requiring PostgreSQL hosting. Recorded as a scoped change of work (review §7 Option B), not part of the fixed \$750 baseline until a change order is agreed.

### Sprint 1 — "It actually works" (NEXT, P0 findings)

- [ ] Wire the public cart to `/api/cart*` and hydrate state from the server; remove the seeded demo cart and fake product IDs (P0-1).
- [ ] Replace the simulated checkout with a real `POST /api/checkout/process` and a server-rendered order confirmation (P0-2).
- [ ] Drive the public catalog from Eloquent instead of `PageController` literal arrays, with `abort(404)` for unknown slugs (P0-3, P2-2).
- [ ] Fix host asset serving so `/images/*` and `/audio/*` resolve (P0-4).
- [ ] Ship Filament compiled assets (P0-5).
- [ ] Add a mail configuration step to the installer and stop writing Mailpit settings into production (P0-7).
- [ ] Remove the publicly rendered demo credentials and pre-filled password from the login view (P1-1).

### Sprint 1 — Outcome: the storefront is real (2026-11-23)

Every item below is enforced by a test that fails if the wiring regresses.

- [x] **Catalog reads the database (P0-3).** New `App\Services\CatalogService` sources published products and songs from Eloquent; prices come from `product_variants`, options are derived from real variant colour/size rows, and stock reflects actual quantities. Unknown slugs `abort(404)` instead of rendering the first product, and unpublished products are unreachable. An un-seeded install falls back to `config/commerce_catalog.php` — labelled sample items that are explicitly non-purchasable, so nothing in that state can become an order.
- [x] **Cart is server-authoritative (P0-1).** The Alpine store is a cache of a server summary; every mutation goes through `/api/cart*` and re-renders from the response. The bag hydrates on page load and recovers from an expired session. The seeded two-item demo bag is gone.
- [x] **Checkout creates a real order (P0-2).** Posts to `/api/checkout/process`; renders the persisted reference, status and bank instructions. `Math.random()` is gone. Two field values that guaranteed a 422 were also fixed (ISO-2 country codes, `bank_transfer` rather than `wire`), along with a fake $5/$12 delivery choice no `ShippingRate` backed and a printed test card number.
- [x] **Static assets are routed (P0-4).** Guarded rewrites for `/images`, `/audio` and `/favicon.ico` to `core/public`, plus `/css|js|fonts/filament/*` for the admin panel. The 0-byte committed `favicon.ico` was replaced with a real icon.
- [x] **Release completeness is checkable (P0-5).** `tools/check-release.mjs` states exactly what the upload still needs and refuses to call an archive ready without `vendor/` and Filament's assets. It reports **INCOMPLETE** today, which is the honest answer.
- [x] **Installer writes real mail settings and a driver choice (P0-7).** Mailpit is gone; the form asks for SMTP details or an explicit "record to log", and a MySQL/MariaDB or PostgreSQL toggle is written to `DB_CONNECTION`. The success screen finally lists the two steps left before opening.
- [x] **Login page no longer publishes a password (P1-1).** Demo banner, pre-filled email and pre-filled password removed. The seeded account still works.
- [x] **Bug found by the new tests:** `CartService::addItem()` never validated song ids, so an invented id was reported as a successful add while the summary silently dropped it.

**Still not live after Sprint 1:** online payment capture (no gateway enabled — the UI now says so), automatic entitlements for bank transfers (P1-4), and the two build products above.

### Sprint 2 — Money and records integrity

- [x] Encrypted settings actually encrypted when saved from the admin form (P1-3).
- [x] Grant digital entitlements on owner-verified bank transfer (P1-4).
- [x] No destructive deletion of financial records (P1-5); audit trail for catalog/content/settings edits (P1-6); owner-accessible refund action (P1-7).
- [x] Rate limiters for cart, checkout, downloads and webhooks (P1-8); trusted proxies (P1-9); application-level security headers incl. CSP/HSTS (P1-10); secure session cookie in production (P1-11).

#### Sprint 2 — Outcome: money is recorded correctly and records survive (2026-11-23)

Every finding in this sprint was a **silent** failure — nothing crashed, nothing
logged an error, and the storefront looked healthy while the record underneath it
was wrong. That is why each one is pinned by a test that asserts what a customer
or an auditor would notice, rather than by an assertion about our own code.

**P1-3 — encrypted settings (was: stored in plaintext).** The admin form bound
the raw `value` column, so choosing "Encrypted Secret" wrote cleartext to the
database; the model's own encryption mutator was bypassed entirely. The form now
never renders the stored secret at all (a blank field means "keep it"), encrypts
on submit, and refuses to lose a secret when only the label is edited. A
`Setting::saving()` guard encrypts as well, because the form is not the only
writer — seeders and console commands reach the model directly. The list view
also refuses to leak, and the audit trail records `[redacted]` for the secret
column while still recording *which* setting changed.

**P1-4 — bank-transfer entitlements (was: paid, no download).** Entitlement
granting lived inline in the Stripe webhook and was copy-pasted into the PayPal
webhook. `Verify Bank Funds` — the only path by which a bank-transfer customer
becomes paid — never did it, so those customers were marked Paid and could not
download what they had bought. All three paths now call one service,
`GrantEntitlementsForOrder`, which is idempotent, ignores merchandise lines, and
returns `skipped_guest` instead of throwing when a guest order cannot receive
entitlements (the owner gets a notification naming the customer instead of a
mystery).

**P1-5 — financial records survive.** `Order::delete()` now throws, and the bulk
delete toolbar is gone. The same hole existed from the other side of the foreign
key: `orders.user_id` is `nullOnDelete`, so deleting a customer did not fail — it
silently orphaned their orders, made the purchase unattributable and cut the
customer off from their downloads forever. A customer with orders now cannot be
deleted either; the error names the supported operation (deactivate or anonymise).

**P1-6 — audit coverage.** Only ten order-state call sites wrote audit events;
price, stock, visibility, content and settings changes wrote none. An `Auditable`
trait on products, variants, songs, settings, content sections and shipping rates
now records created/updated/deleted with before/after values. A trait rather than
per-resource hooks because the old coverage failed precisely by being opt-in.
Secrets are redacted per column, so the log says "this secret was rotated, by
whom" without storing either the plaintext or the ciphertext.

**P1-7 — refunds.** The owner can now refund an order: a guarded reason, a
negative `Payment` row, entitlements revoked (deactivated, never deleted) and an
audit event. This needed a migration — `payments.amount_cents` was
`unsignedInteger`, so a refund could not have been stored at all.

**P1-8 … P1-11 — the security surface.** Only login, register and booking were
throttled, so order creation, stock reservation and entitlement probing were
unbounded; there are now named limiters for cart reads, cart mutations, checkout
(per-IP plus a global ceiling), downloads (per user), webhooks and auth.
Trusted proxies are configurable and empty by default (behind a CDN every
throttle key used to collapse onto the proxy's IP, so one abuser could lock out
every customer). Security headers are now sent by the application, not only by
`.htaccess` — a host that ignores `.htaccess` previously served the storefront
with no CSP and no HSTS. The CSP is strict on the storefront (the front end ships
no inline script, so it does not need `unsafe-inline`) and relaxed only as far as
Filament requires. The installer writes `SESSION_SECURE_COOKIE` derived from the
site URL, plus `SESSION_ENCRYPT`, `SESSION_SAME_SITE` and a documented
`TRUSTED_PROXIES` placeholder.

**Defects this sprint's own work introduced, and how they were caught.** Two
changes were wrong and the pipeline said so within a minute of each push, which
is the point of enforcing CI before claiming a status:

1. `config()` called from `bootstrap/app.php`'s `withMiddleware()` closure — it
   runs while the container is still resolving the kernel, so `config` is not
   bound and every artisan command died with "Target class [config] does not
   exist". This broke `composer install` (post-autoload-dump runs
   `package:discover`) and would have taken production down entirely. Proxies are
   now read at request time by `App\Http\Middleware\TrustProxies`.
2. Removing the `Request` import from `bootstrap/app.php` because a grep said it
   was unused — it is used as a closure parameter type hint, and every request
   died with a `TypeError`. **An import may only be deleted on the strength of a
   compile, never a grep.**

Additionally, `property_exists($this, 'type')` is false for an Eloquent attribute
(attributes live in `$attributes`, not declared properties), so the first version
of the audit redaction redacted nothing and archived ciphertext. The test caught
it before it shipped; the fix reads the attribute through the model.

A third, quieter mistake is worth recording because it is the most dangerous kind:
`mutateFormDataBeforeCreate()` is a protected method on the **page**, not a static
method on the resource, so the version placed on `SettingResource` was never
called — and its test invoked it directly, so it passed while the create path was
unguarded. In a feature about secrets being written in plaintext, a guard that
merely looks like a guard is worse than no guard, because it converts "untested"
into "falsely tested". Both are now fixed and the test drives the real page.

**Infrastructure learned here:** CI's step logs are not retrievable from the
development sandbox, so a failing step was indistinguishable from a broken commit.
The workflow now republishes composer failures as annotations, retries the
install, and publishes the suite totals as a notice on a green run — which is how
the figure in README is verifiable at all.

**Still not live after Sprint 2:** online payment capture (no gateway enabled),
and the two build products (`vendor/`, Filament assets). Refunds are recorded
through the platform, but the money movement itself still happens at the
provider's dashboard — the action records and revokes, it does not call the
Stripe or PayPal refund API.

### Sprint 3 — Craft pass

- [ ] Author the 26 Blade classes that have no CSS rule, including the bag drawer and toast (P2-1).
- [ ] Responsive image derivatives (AVIF/WebP/JPEG + `srcset`, intrinsic dimensions) and self-hosted display/text fonts (P2-6, P2-7).
- [ ] Branded 404/403/419/429/500/503 views (P2-3), SEO/social metadata and sitemap (P2-4), real favicon set (P2-5).
- [ ] Truth and price corrections: unapproved $12 collection, WAV claims, invented specifications, price drift (P2-8, P2-9).
- [ ] Accessibility set: `aria-current`, `aria-invalid`, live regions, table semantics (P2-11). Screen-reader testing stays marked unverified until performed.

### Sprint 4 — Packaging and operations

- [ ] `tools/package.sh` producing a versioned archive with `vendor/` and Filament assets, plus an extraction test (P3-1).
- [ ] Backup/restore procedure with a rehearsed restore (P3-6); scheduler entries (P3-7); daily log rotation in the installer template (P3-5); lighter health endpoint (P3-9).
- [ ] Documented `install/` removal and release-rollback steps (P3-8).

### Sprint 5 — Hosting proof (requires the owner)

- [ ] Implement and certify the MySQL/MariaDB path: `jsonb` → dialect-aware JSON, audit immutability triggers for MySQL and MariaDB, installer driver selection with `pdo_mysql` preflight, and a CI matrix across PostgreSQL 17 / MySQL 8.0 / MariaDB 10.11.
- [ ] Fresh install on a named cPanel plan matching the target PHP/extensions/cron; complete a customer and owner walkthrough; verify SMTP, backups, restore and rollback.

## Phase 6 — Later editorial work and launch operations (PAUSED)

- [ ] Add blog and newsletter after an email provider, consent language, unsubscribe flow, retention and abuse controls are decided.
- [ ] Add PWA only if offline access adds real customer value and can exclude private account/payment/download state.
- [ ] Complete legal copy, sales tax, refund, privacy, shipping and music download policies with the owner's qualified advisors.
- [ ] Obtain explicit owner approval before payment activation, DNS change or production release.

## Scope and evidence rules

- The fixed $750 engagement remains separate. Obtain a written change order before adding its excluded features or treating roadmap work as contracted.
- Each completion checkbox should link to an issue, commit, test/runtime evidence, or release checklist item once those artifacts exist.
- Database runtime discipline: Preserve the PC's active PostgreSQL version (17.9 on port 5432). With the upstream release of PostgreSQL 18.7, plan and verify a compatibility matrix for PostgreSQL 15, 16, 17, and 18 in automated CI/CD test runners, avoiding engine-exclusive syntax. Treat version compatibility as a verification test plan rather than an already-certified status.
- Hosting compatibility, real payment activation, tax compliance, email delivery, DNS, and production behavior remain unverified until tested with the owner's actual providers and credentials.
- Production application setup and implementation are assigned to Antigravity; do not mark those roadmap items complete from prototype work in this workspace.
- Performance, security, accessibility, and maintainability targets are acceptance criteria, not proof of implementation. Keep dated evidence and report any exception explicitly.
