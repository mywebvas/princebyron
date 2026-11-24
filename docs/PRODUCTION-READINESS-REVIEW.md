# Production Readiness Review — Prince Byron Platform

**Review date:** 2026-11-23
**Reviewer scope:** senior technical architect · security engineer · Laravel/PHP specialist · shared-hosting (cPanel) deployment engineer · UX & accessibility lead · product/scope auditor · QA/test strategist
**Branch reviewed:** `arena/9adff00e-princebyron` @ `1af1b69`
**Target deployment:** generic cPanel shared host **or** any host where nothing may be installed — upload the archive, open the browser installer, and the site works.

> This document is a findings report plus a prioritized backlog. It contains **no code changes** and therefore cannot regress anything. Every finding carries file/line evidence so it can be independently re-checked.

---

## 0. What was actually verified vs. what was read

| Method | What it covered |
|---|---|
| Static read of all 131 PHP files (app, config, routes, migrations, seeders, tests, installer, root loader, `.htaccess`) | architecture, security controls, feature wiring |
| Static read of all 11 Blade views (3,447 lines), `app.js`, `app.css`, `tokens.css` | UI wiring, dead CSS, a11y, payload |
| Data-flow tracing (`grep` call-graph of cart → checkout → order → entitlement → download) | **discovered the public UI is not wired to the backend** |
| PHP syntax check of all 131 files using an in-sandbox PHP 8.4 (php-wasm) | **1 failure** (`core/redis_test.php`) |
| Vite production build re-run (`npm ci && npm run build`) and CSS class-coverage diff | **22 Blade classes with no CSS rule** |
| Real HTTP-path simulation of the cPanel rewrite chain against the committed `.htaccess` | **all `/images/*` and `/audio/*` URLs 404 on the host** |
| `composer.lock` platform analysis (112 prod packages) | **true PHP floor is 8.4.1, not 8.3** |
| Comparison of public price data vs `PRD.md` / `ANTIGRAVITY-HANDOFF.md` approvals | **4 price contradictions + 1 unapproved product** |

**Not verified (cannot be, without the real host/credentials):** live cPanel plan capability, PostgreSQL availability on the chosen plan, real SMTP delivery, real Stripe/PayPal payment creation, real-device screen readers, field Core Web Vitals, backup restore on the provider.

**Note on "71 passing tests":** the suite cannot be executed in this environment (no PHP/composer binaries, no PostgreSQL). The claim is plausible but **currently unverifiable and unenforced** — there is no CI. See P3-3.

---

## 1. Executive verdict

The **backend is genuinely good** — better than most codebases that reach this stage. The **frontend is a high-fidelity design prototype sitting on top of that backend, not connected to it.** That single gap is the difference between "demo" and "store".

| Area | State | Score |
|---|---|---|
| Domain model & migrations | Coherent, commented, well-indexed, PG-correct | 9/10 |
| Commerce service layer (cart/checkout/stock) | Server-authoritative, transactional, atomic stock, no oversell | 9/10 |
| Payment verification (webhooks) | Fail-closed, replay-safe, correct PayPal RSA, refund revokes entitlements | 9/10 |
| Audit trail | Immutable at DB-trigger level on PG + SQLite | 8/10 |
| Installer & root loader | Genuinely hardened (lockout, CSRF, mutex, HTTPS gate, log redaction) | 9/10 |
| Admin panel (Filament 5) | Broad, policy-guarded, but **components not shipped** and no edit audit | 6/10 |
| **Public storefront ↔ backend wiring** | **Cart is client-only; checkout is simulated; catalog is hardcoded** | **1/10** |
| **Deployed-asset correctness (cPanel)** | **Images, audio, Filament CSS/JS 404 or missing** | **2/10** |
| **Feature completeness vs PRD** | Core flows exist in code, ~half are unreachable from the UI | **4/10** |
| Design system discipline | Tokenized, but 26 orphaned classes + 579 inline `style=` attributes | 6/10 |
| Performance (payload/asset budget) | 5.8 MB unoptimized JPEGs, no `srcset`/WebP, no self-hosted display font | 3/10 |
| Accessibility (WCAG 2.2 AA) | Strong foundations; no `<noscript>`, missing `aria-current`/`aria-invalid`, unverified SR | 6/10 |
| "Upload and it works" | **No** — 7 concrete blockers (§4) | 3/10 |
| Deploy/ops tooling (packaging, CI, backup, rollback) | Docs only, no executable tooling | 3/10 |

**Bottom line:** the hard part (money, state machines, signatures, entitlements, installer hardening) is largely built and defensible. The remaining work is *integration, deployment correctness, truth-in-copy, and craft* — all of which is fixable without redesigning the foundations.

---

## 2. Architecture as-built (so later work is precise)

```
public_html/                     ← host document root
  index.php                      root loader: 403 on /core,/vendor,/storage,/.env,/.git + bad extensions
  .htaccess                      mod_rewrite + deflate + expires + 4 security headers
  install/                       one-time HTTPS installer (lockout on core/storage/installed)
  core/                          Laravel 13.42 / PHP ^8.5 / Filament 5.11 / Livewire 4.4
    app/Http/Controllers/        Page, Cart, Checkout, CustomerAuth, CustomerAccount,
                                 Booking, Download, Webhook
    app/Services/                CartService, CheckoutService,
                                 Payment/{Stripe,PayPal}SandboxService
    app/Models/                  15 models + Order state machine ($transitions map)
    app/Filament/Resources/      8 resources (Songs, Products, Orders, Bookings,
                                 ContentSections, Settings, ShippingRates, AuditEvents)
    resources/views/pages/       11 Blade pages (3,447 lines)
    resources/js/app.js          Alpine store "app": nav, bag drawer, toast, audio synth, cart[]
    resources/css/app.css        3,216 lines hand-written CSS + tokens.css (generated)
    database/migrations/         13 migrations (000001–000010)
    tests/                       71 tests / ~2,580 lines (SQLite in-memory by default)
```

**Two parallel universes exist today:**

1. **Universe A (backend):** `CartService` → `CheckoutService` → `Order` → `Payment` → `DownloadEntitlement` → `DownloadController`. Fully implemented, tested, transactional. Reachable only via `/api/*` with JSON.
2. **Universe B (frontend):** Alpine `$store.app.cart` seeded with two fake items, mutated in-memory, totals computed in JS, "checkout" generates a random reference and declares success. **No `fetch()` exists anywhere except the booking form.**

Nothing bridges A and B. That is finding **P0-1…P0-3**.

---

## 3. Findings register

Severity: **P0** = blocks launch / breaks a promise · **P1** = security, fraud or data-integrity risk · **P2** = craft, UX, SEO, accessibility · **P3** = packaging, ops, process.
Effort: `S` ≤ 2h · `M` ≤ 1d · `L` ≤ 3d · `XL` > 3d (estimates, not quotes).

### P0 — Launch blockers

| # | Finding | Evidence | Fix | Effort |
|---|---|---|---|---|
| **P0-1** | **Cart is client-only and pre-seeded.** Every visitor arrives with a bag already containing "Prince Byron Signature Tee ($32)" and "Purify Me ($1.99)" they never added. Bag is lost on every page navigation (Alpine store is rebuilt per page load; no `localStorage`, no server session call). IDs sent to the store are fake slugs (`'tee'`, `'song-01'`, `'album-collection'`) that can never resolve to a `product_variants.id`. | `resources/js/app.js:82-85`; `pages/music.blade.php:55,132`; `pages/product.blade.php:114-119`; zero `fetch('/api/cart')` in `resources/js` or views | Hydrate the store from `GET /api/cart`, route add/update/remove through the existing endpoints, drive totals from `subtotal_cents`, remove the seeded array, render real variant IDs from the DB | M |
| **P0-2** | **Checkout is a simulation.** `submitCheckout()` invents `PB-202611-XXXX` with `Math.random()` and shows "Paid & Settled / Entitlements Active". No POST to `/api/checkout/process`. Consequently: no order row, no stock decrement, no payment record, no entitlement, no bank instructions, no shipping-rate gate, no receipt. The entire tested commerce layer is unreachable. | `pages/checkout.blade.php:28-45`, `:114` | Replace with a real submit → `POST /api/checkout/process`, render server totals/`shipping_unconfigured`/`destination_unsupported` errors, then redirect to a server-rendered order confirmation | M |
| **P0-3** | **Public catalog is hardcoded, not database-driven.** `PageController::getProducts()/getSongs()` return literal arrays. `Product`, `Song`, `ProductImage` are managed in Filament but **nothing in the public site ever queries them** (`grep` = 0 hits outside models/Filament). Owner edits in `/admin` have **no effect** on the store — the core PRD promise ("owner manages the catalog without editing code") is unmet. | `PageController.php:12-187`; no `Product::`/`Song::` in any view | Read published products/variants/songs from Eloquent in `PageController`; delete the literal arrays; add `abort(404)` for unknown slugs (also fixes P2-2) | M |
| **P0-4** | **All images and audio return 404 on the host.** Root `.htaccess` rewrites only `^build` and `^assets` into `core/public/`. `asset('images/…')` resolves to `/images/…`, which matches no rewrite, is not a real file, and falls through to Laravel → 404. Same for `/audio/*` (the free-track download link and the public fallback MP3). `^assets` is dead — `core/public/assets` does not exist. | `.htaccess:42-48`; `home.blade.php:45,63,162`; `store.blade.php:71`; `product.blade.php:30,150`; `PageController.php:285` | Replace the two narrow rewrites with a real-files passthrough (`RewriteCond %{DOCUMENT_ROOT}/core/public%{REQUEST_URI} -f → core/public%{REQUEST_URI}`) or move all static media under `/build/assets`. Verify every `<img>` URL over HTTP afterwards | S |
| **P0-5** | **Filament's compiled assets are gitignored and absent.** `/public/css/filament`, `/public/js/filament`, `/public/fonts/filament` are excluded (`.gitignore:18-22`) and don't exist in the tree. With `vendor/` prebuilt but no Composer/Node on the host, `/admin` will render **unstyled and non-functional** after upload. | `.gitignore:18,20,22`; `ls core/public` → no `css/`, `js/` | Run `php artisan filament:assets` on the build machine, **un-ignore** and ship them; assert their presence in the packaging check | S |
| **P0-6** | **PHP floor mismatch → installs, then fatals.** `composer.json` demands `^8.5`; the installer only requires `>=8.3.0`. But with `vendor/` shipped there is no Composer platform check, so on a PHP 8.3/8.4 host installation *succeeds* and the app then dies: 17 locked packages require `>=8.4.1` (symfony 8.1), Laravel 13 requires `^8.3`. | `composer.json:8`; `install/index.php:184`; `composer.lock` (symfony/console 8.1.11 `>=8.4.1`) | Make one honest number: installer gate = **8.4.1** (or 8.5), `composer.json` to match, plus a `vendor/composer/platform_check.php`-style guard and a documented requirement line in README + installer UI | S |
| **P0-7** | **Installer writes a development mail config into production.** The generated `.env` hardcodes `MAIL_HOST=127.0.0.1`, `MAIL_PORT=1025` (Mailpit), `MAIL_FROM_ADDRESS="booking@princebyron.local"`, and `QUEUE_CONNECTION=sync`. On a real host every booking acknowledgement/owner notification throws, is swallowed by `catch (\Throwable)` in `BookingController`, and the ministry never learns a request arrived. | `install/index.php:352-356,347`; `BookingController.php:42-52` | Add a "Mail" step to the installer (host/port/user/pass/from + TLS), or default to `MAIL_MAILER=log` **and block publishing the booking form until SMTP is verified**; `LOG_STACK=daily`; add a test-send button | M |
| **P0-8** | **CLOSED in Sprint 3 — see §13.** | `PRD.md` ("Shared host PostgreSQL support is not yet verified"); `migrations/…000002, 000005, 000009` (`jsonb`); `…000010` (PL/pgSQL) | See §7 — this needs an owner decision **before** further packaging work, because it changes the schema and the installer | XL (12–16h per own estimate) or 0 (if a PG-capable host is chosen) |

### P1 — Security, fraud, data integrity

| # | Finding | Evidence | Fix | Effort |
|---|---|---|---|---|
| **P1-1** | **Demo credentials are rendered on the production login page**, including a **pre-filled password field**. Anyone visiting `/login` gets working customer credentials. | `account.blade.php:936-948` (banner) and `:1007` (`value="TemporaryFanPass2026!"`) | Delete the banner + prefill; gate any such helper behind `@if(app()->environment('local'))` at most | S |
| **P1-2** | **`public/snapshots/` (14 HTML pages, 416 KB) is web-reachable** and contains rendered customer account and order screens. Dev artifacts inside the docroot. | `core/public/snapshots/*.html` (tracked) | Move outside `public/` (e.g. `docs/snapshots/`), or delete; add to packaging exclusions | S |
| **P1-3** | **"Encrypted Secret" settings are stored in plaintext when edited in the admin UI.** `Setting::setTypedValue()` encrypts, but `SettingResource` binds a raw `value` Textarea, so the mutator is bypassed. An owner storing SMTP/payment secrets there writes cleartext to the DB. | `Setting.php:65` vs `Settings/SettingResource.php:83` | Route the form through the typed accessor (`dehydrateStateUsing` → `setTypedValue`), mask on read, add a round-trip test | S |
| **P1-4** | **Bank-transfer verification never grants digital entitlements.** Stripe/PayPal webhooks do `DownloadEntitlement::firstOrCreate(...)`; the owner's "Verify Bank Funds" action does not. A customer who pays by bank for a paid track is marked Paid and **never receives the download**. | `Orders/OrderResource.php:184-228` vs `StripeSandboxService.php:150-170` | Extract one `GrantEntitlementsForOrder` service and call it from all three paths (webhook ×2, bank verify) | S |
| **P1-5** | **Orders can be bulk-deleted with no audit record.** | `Orders/OrderResource.php:331` (`DeleteBulkAction`) | Remove destructive actions from financial records; replace with Cancel/Archive + audit; make the model refuse deletion | S |
| **P1-6** | **No admin audit coverage for catalog/content/settings edits.** Only 10 `AuditEvent::create` call sites exist (orders + bank verify). PRD requires an audit trail for *consequential* admin edits — price, stock, visibility, content, settings. | `grep AuditEvent::create` = 10, none in Product/Song/Setting/ContentSection resources | Add an `Auditable` trait / observer capturing `old_values`/`new_values`/actor/IP, or `->afterStateUpdated` hooks per resource | M |
| **P1-7** | **Refunds are unreachable by the owner.** `Order::STATUS_REFUNDED` transitions exist, but no admin action exposes refund (webhook-only). Manual bank refunds are therefore impossible without SQL. | `Orders/OrderResource.php` (no refund action); `Order.php:70-84` | Add a guarded Refund action: transition, payment row, revoke entitlements, audit | S |
| **P1-8** | **Rate limiting is thin.** Only login (5/min), register (5/min), booking (10/min) are throttled. `/api/cart/*`, `/api/checkout/process`, `/downloads/{id}` and `/webhooks/*` are unthrottled — order-spam, cart hammering, entitlement-ID probing and webhook signature brute force are all unbounded. No `RateLimiter` definitions exist anywhere. | `routes/web.php:40,42,68`; `AppServiceProvider` empty | Define named limiters (per-IP and per-user) for cart, checkout, downloads, webhooks; return 429 with a branded page | S |
| **P1-9** | **Trusted proxies unconfigured.** Behind Cloudflare/LiteSpeed/any reverse proxy, `request()->ip()` logs the proxy IP, and `throttle` keys collapse onto it — one abusive client can lock out everyone. | no `trustProxies` in `bootstrap/app.php` | `$middleware->trustProxies(at: [CF ranges], headers: …)`; honor `CF-Connecting-IP` only from trusted peers | S |
| **P1-10** | **Security headers exist only in Apache.** No CSP, no HSTS, no `Permissions-Policy`, no app-level fallback. If the host is nginx/LiteSpeed-custom or ignores `.htaccess`, the app is served with none of them. | `.htaccess:101-118` (4 headers); no middleware | Add a `SecurityHeaders` middleware (CSP with a per-request nonce, HSTS on HTTPS, frame-ancestors, nosniff, referrer) so protection travels with the code | M |
| **P1-11** | **Session cookie not forced secure in production.** The installer's `.env` omits `SESSION_SECURE_COOKIE=true`. | `install/index.php` env template; `config/session.php:172` | Emit `SESSION_SECURE_COOKIE=true`, `SESSION_SAME_SITE=lax`, `APP_URL=https://…` when HTTPS is enabled | S |
| **P1-12** | **Payment integration is overstated.** `CheckoutController` returns mock artifacts (`pi_sandbox_…`, `seti_sandbox_…_secret_mock`, `PAYPAL-SANDBOX-…`). No Stripe/PayPal SDK is installed and no HTTP call to either provider exists anywhere. Only *webhook verification* is implemented. | `CheckoutController.php:96-108`; `grep "api.stripe.com\|api.paypal.com\|Http::" app` = 0; `composer.json` has neither SDK | Re-label roadmap/README as "webhook verification complete; payment creation not started", then implement provider checkout (hosted redirect/tokenized) as separately approved scope | L |
| **P1-13** | **Dev/scratch artifacts ship with the app.** `core/princebyron` (274 KB SQLite DB), `scratch_check_pass.php` (contains live dev passwords), `scratch_update_data.php` (sets passwords), `redis_test.php` (**syntactically invalid PHP**, `= new Redis();`), 12 `.cjs` screenshot scripts, `public/find_overflow.js`, `storage/pb2_test.html`, `storage/screenshot_pb2.png`. | `ls core`; PHP lint of 131 files → 1 failure (`redis_test.php:2`) | Delete from the repo (keep the capture scripts if wanted, behind `tools/` and excluded from packaging) | S |
| **P1-14** | **Installer can install onto a PHP version the app cannot run**, and its own preflight list omits `pdo_pgsql` alternatives / `intl` / `sodium` expectations used by Laravel; it also doesn't verify the scheme of `APP_URL`. Covered by P0-6; listed here for the preflight matrix rewrite. | `install/index.php:184-215` | Single shared `Requirements` class used by both installer and tests | S |

### P2 — Craft, UX, SEO, accessibility

| # | Finding | Evidence | Fix | Effort |
|---|---|---|---|---|
| **P2-1** | **CLOSED in Sprint 3 — see §13.** | diff of 222 referenced classes vs compiled CSS (list in §Appendix A) | Either author the missing rules or rename templates to the existing ones; then add the class-coverage check to CI so this can't silently return | M |
| **P2-2** | **Unknown product slugs silently render the T-shirt** instead of 404 → wrong URL, wrong price, wrong SEO, distrust. | `PageController.php:232` (`?? $products['tee']`) | `abort(404)` + branded 404 page | S |
| **P2-3** | **CLOSED in Sprint 3 — see §13.** | `ls resources/views` → no `errors/` | Add 401/403/404/419/429/500/503 views in the design system | M |
| **P2-4** | **CLOSED in Sprint 3 — see §13.** | `layouts/app.blade.php` head (13 lines) | Add a `<x-seo>` component driven by page data + a `sitemap` route/command | M |
| **P2-5** | **CLOSED in Sprint 3 — see §13.** | `wc -c public/favicon.ico` → 0 | Ship a real 32/180/512 icon set + `manifest.webmanifest` | S |
| **P2-6** | **CLOSED in Sprint 3 — see §13.** | `du -sb core/public/images` = 5,777,108; `grep -c srcset` = 0 in all pages | Generate AVIF/WebP/JPEG derivatives at 4 widths + intrinsic dims; target ≤ 180 KB for the LCP hero at 375 px. No CDN needed: derivatives are just files | M |
| **P2-7** | **CLOSED in Sprint 3 — see §13.** | `tokens.css:172-174`; no `@font-face` anywhere | Self-host 2 woff2 subsets (display + text), preload the display face, `font-display: swap`, no CDN — keeps the low-bandwidth promise | M |
| **P2-8** | **CLOSED in Sprint 3 — see §13.** | `music.blade.php:39,42,48,55,96`; `PageController.php:143-181`; `store.blade.php:88`; `SongsSeeder.php:29-45` | Strip to approved facts; replace spec/eco claims with owner-supplied copy or neutral text; show "Masters pending" honestly where the file isn't staged | S |
| **P2-9** | **CLOSED in Sprint 3 — see §13.** | `PageController.php:40,60,100,120` vs `ANTIGRAVITY-HANDOFF.md:36` and `ProductsSeeder.php` | Collapse to one source (P0-3) and add a test asserting approved prices | S |
| **P2-10** | **No `<noscript>` path.** Cart, checkout, booking, drawer and toast are 100% JS-dependent. On a low-end phone or flaky connection — an explicit PRD target — the store is unusable. | no `<noscript>` in any view; all flows Alpine-driven | Server-rendered fallbacks: real `<form>` POST for booking/checkout, `<a href>` add-to-cart, plus a `<noscript>` notice | L |
| **P2-11** | **CLOSED in Sprint 3, except screen-reader testing, which no automated check substitutes for — see §13.** | `layouts/app.blade.php:96-102`; `booking.blade.php:328-340`; `checkout.blade.php:56-80` | Fix the ARIA set; keep the "(SR testing unverified)" note honest until tested | M |
| **P2-12** | **`/admin/preview` is a public 543-line fake backoffice** ("Simulation") still routed and still in the README. It invites confusion with the real panel and is a support burden. | `routes/web.php:71`; `pages/admin-preview.blade.php` | Remove in production (or gate behind owner auth as a design reference) | S |
| **P2-13** | **No customer password reset and no email verification flow**, though `User implements MustVerifyEmail` and PRD requires secure account recovery. Registration auto-sets `email_verified_at = now()`, which quietly makes the interface a lie. | `CustomerAuthController.php:80-90`; no reset routes | Either implement reset + verification (recommended, 2 mails) or drop the `MustVerifyEmail` contract | M |
| **P2-14** | **No order/receipt email to the customer.** `Mail::` appears only in `BookingController`. Commerce without a receipt email is a trust gap. | `grep "Mail::" app` → 2 hits, both booking | `OrderConfirmationMail` + `OrderStatusChangedMail`, queued | M |
| **P2-15** | **Out-of-stock can be added to the bag.** `addItem` clamps to `max(1, stock)`, so `stock = 0` yields quantity 1; the summary then silently prunes the line. Owner sees nothing; customer sees a vanishing bag. | `CartService.php:53-57` vs `:141-148` | Reject with an explicit "out of stock" state; treat `-1` (not `<0`) as unlimited consistently | S |
| **P2-16** | **579 inline `style="…"` attributes** (account 175, admin-preview 191, checkout 60, booking 49, cart 30). Blocks CSP hardening, defeats tokens, and is the main reason "premium consistency" drifts per page. | count per file in §Appendix B | Extract to utilities/component classes while doing P2-1; do it page-by-page, behind screenshots | L |
| **P2-17** | `APP_TIMEZONE` in `.env.example` is ignored — `config/app.php:68` hardcodes `'UTC'`. US customers see UTC times on receipts. | `config/app.php:68` vs `.env.example` | `env('APP_TIMEZONE','America/New_York')` (PRD: USA) | S |

### P3 — Packaging, ops, process

| # | Finding | Evidence | Fix | Effort |
|---|---|---|---|---|
| **P3-1** | **No release tooling.** No `Makefile`/script that produces the versioned archive (vendor + build + Filament assets + root loader + installer + version marker, excluding `.env`, logs, tests, scratch, snapshots, `node_modules`). Phase 5 is marked "done" without a reproducible artifact builder. | no `scripts/`, `Makefile`, `.github/` | `tools/package.sh` with an explicit include/exclude manifest + SHA-256 + `VERSION` file; assert the archive by extracting it into a clean dir and hitting it | M |
| **P3-2** | **No CI.** The 71-test claim is unenforced; a regression can ship silently. | no `.github/workflows` | GitHub Actions: PHP 8.5 + Postgres 17 service, `phpunit.pgsql.xml` **and** sqlite matrix, `pint --test`, `vite build`, installer tests | M |
| **P3-3** | **Default test DB ≠ production DB.** `phpunit.xml` = SQLite in-memory; production = PostgreSQL with `jsonb`, PL/pgSQL trigger, `lockForUpdate` semantics, `SELECT … FOR UPDATE` under READ COMMITTED. PG-specific behaviour is only covered by the optional `phpunit.pgsql.xml`. | `phpunit.xml:30-33`; `phpunit.pgsql.xml` | Make PG the primary CI job; keep SQLite as the fast job | S |
| **P3-4** | **`docker-compose.yml` has a broken `APP_KEY`** (`base64:$(openssl rand -base64 32)` is not shell-interpolated by Compose). | `docker-compose.yml` `environment.APP_KEY` | Use a `.env`-provided key or an entrypoint that generates it once | S |
| **P3-5** | **Logging can grow unbounded on the host.** Installer writes `LOG_CHANNEL=stack` with no `LOG_STACK`, so the stack default applies (often `single`). | `install/index.php:340-345` | Emit `LOG_STACK=daily`, `LOG_DAILY_DAYS=14`, `LOG_LEVEL=warning` | S |
| **P3-6** | **No backup/restore automation or documented rollback artifact**, though PRD's acceptance outcome requires one. | docs only | `artisan pb:backup` (pg_dump via `Process`, or a SQL export), retention + restore drill note; plus a documented "revert to previous release dir" procedure | M |
| **P3-7** | **No scheduler entries.** `routes/console.php` has only `inspire`; no `withSchedule`, no prune/expiry jobs, no queue worker guidance beyond docs — and the installer sets `QUEUE_CONNECTION=sync`, so any future queued mail/retry must change that deliberately. | `routes/console.php`; `install/index.php:347` | Add schedule: `queue:prune-failed`, `model:prune`, expired-entitlement sweep, sitemap refresh; document the single cron line | S |
| **P3-8** | **Install directory remains after success** (only 403'd). Acceptable defence-in-depth, but the success screen should give an exact, copy-pasteable removal instruction per host UI, and the loader should stop advertising `install/` once locked. | `install/index.php:620-640`; `index.php:19-25` | Add "delete `install/`" as a required release checklist step + a readiness self-check page | S |
| **P3-9** | **Health endpoint does a cache *write* per request** (`Cache::put` then `get`), which on the `file` driver is disk I/O on every probe. | `routes/web.php:88-95` | Read-only DB ping + `cache()->get` on a pre-seeded key, or throttle the probe | S |
| **P3-10** | **Docs drift.** README advertises `/admin/preview`, demo credentials, and "Filament 5 Owner Admin Panel" availability without the asset caveat; ROADMAP marks "Integrate Stripe and PayPal in sandbox" ✅ while only verification exists (P1-12); `docs/DESIGN_SYSTEM_AUDIT.md` says "0 raw color literals" (true) but the same audit's class-coverage gap was never checked (P2-1). | `README.md`; `ROADMAP.md:58`; `DESIGN_SYSTEM_AUDIT.md:50` | Re-baseline the three docs after fixes; separate "implemented" from "verifiable" | S |

---

## 4. "Upload and it works" — the honest answer today

Uploading the repo as-is to a cPanel host produces:

1. Installer runs, migrations succeed on **PostgreSQL** — *if* the plan has PG (most don't → P0-8).
2. Home page HTML renders — **without any images** (P0-4), **without the toast/badge/gallery CSS** (P2-1), in **Times New Roman** instead of the display face (P2-7), with a **bag pre-filled with products nobody added** (P0-1).
3. `/admin` renders **unstyled and broken** — Filament assets absent (P0-5).
4. Booking form submits and stores the row, but **nobody is emailed** (P0-7).
5. Checkout "works" only in the sense that it prints a random reference and claims payment success (P0-2).
6. If the host is PHP 8.3/8.4, step 1 "succeeds" and every page then fatals (P0-6).

**So: not upload-and-works today.** It is honestly ~8 focused fixes (all S/M) away from "uploads, installs, looks right, and takes a bank-transfer order end-to-end" — and those 7 are the highest-value work available. Nothing in them requires re-architecting.

---

## 5. Feature completeness vs PRD / roadmap

Legend: **DONE** = implemented and reachable · **PARTIAL** = code exists but is unreachable, gated, or thin · **NO** = absent · **N/A** = out of scope for launch.

| PRD requirement | Backend | Public UI | Verdict |
|---|---|---|---|
| Home / About / Music / Store / Product / Cart / Checkout / Booking / Account pages | — | rendered | **DONE** (content truth issues P2-8) |
| Owner-managed catalog & prices drive the site | Eloquent ready | hardcoded arrays | **PARTIAL** (P0-3) |
| Server-verified cart & totals | `CartService` + 6 endpoints | client-only store | **PARTIAL** (P0-1) |
| USD checkout, server-authoritative pricing/stock/shipping | `CheckoutService` (atomic, tested) | simulated | **PARTIAL** (P0-2) |
| Manual bank transfer → pending until owner verifies | create order + admin verify action | simulated | **PARTIAL** (P0-2, P1-4) |
| Stripe / PayPal payments | webhook verification only | mocks | **PARTIAL** (P1-12) |
| Paid download entitlements, private audio, short-lived access | `DownloadController` + policy + expiry + count-only-on-delivery | real links in account | **DONE** (needs re-download limiting — see §9) |
| Free lead-single download | route + file | linked | **DONE** (404 risk P0-4) |
| Customer accounts, order history, cross-account denial | `CustomerAuth` + `CustomerAccount` + policy | login/register/dashboard | **DONE** |
| Account recovery / email verification | absent | absent | **NO** (P2-13) |
| Booking RFQ with acknowledgement + owner notification | validation + 2 mails + throttle | real `fetch` (the only wired form) | **PARTIAL** (mail config P0-7) |
| Owner-only admin, role enforced server-side | Filament + `canAccessPanel` | — | **DONE** (assets P0-5) |
| Structured content editor (no arbitrary HTML/CSS) | `ContentSection` + sanitizers + palette allowlist | **nothing renders ContentSections** | **PARTIAL** — editor writes to a table no page reads |
| Site settings drive the site | `Setting` + resource | only `contact_email` is read | **PARTIAL** |
| Audit trail for consequential edits | immutable table + 10 call sites | — | **PARTIAL** (P1-6) |
| Owner-managed shipping zones/rates | `ShippingRate` + resolve + free-over threshold | gated server-side | **DONE** |
| Brand color control | palette allowlist in `config/tokens.php` | build-time only | **PARTIAL** (owner can't recolor without a rebuild) |
| Policies: privacy / refunds / shipping / terms / contact | absent | absent | **NO** — legal launch gate |
| Blog, newsletter, PWA | — | — | **N/A** (deferred by PRD) |

> **New, unlisted product-surface gaps** worth deciding on explicitly: `/policies/*` pages, a contact/support form or address, a "track my order" lookup for guests, and a downloadable invoice — all expected by US commerce customers in 2026.

---

## 6. Truth & claims audit (must not ship as-is)

| Claim in product | Reality | Action |
|---|---|---|
| "Paid & Settled • Entitlements Active" on checkout | nothing was charged or recorded | remove until P0-2/P1-12 land |
| "Faith for the In-Between" collection, $12, digital booklet, 24-bit WAV | not an approved product; no WAV exists (`has_wav=false`); masters pending | remove or get owner approval + files |
| "MP3 + 24-bit WAV • Duration 4:18 / 5:02 / 3:45 …" | durations and formats unverified | show only approved metadata |
| "certified sustainable practices", 260/450 GSM, Hahnemühle, SKU/stock counts | invented | replace with owner-approved copy |
| Hoodie $68 / Cap $28 / Print $35 / Bundle $110 | approved: $58 / $30 / $25 / $80 | align (P2-9) |
| README "Stripe and PayPal operate in sandbox mode; no live transactions are processed" | accurate — but implies integration exists; it does not | reword per P1-12 |
| ROADMAP "Integrate Stripe and PayPal in sandbox ✅" | verification only | split the item |
| "71 tests passing, 492 assertions" | cannot be reproduced without PHP/PG; no CI | add CI (P3-2) before repeating the claim |
| "Local Demo Access • Verified Credentials" on `/login` | leaks working credentials publicly | delete (P1-1) |

---

## 7. Hosting decision matrix — the one thing to settle first

Because P0-8 changes schema, installer, and CI, it should be decided **before** further packaging.

| Option | What it takes | Pros | Cons | Verdict |
|---|---|---|---|---|
| **A. cPanel + local PostgreSQL** (current plan) | host must actually offer PG (rare on shared; common on cPanel VPS/dedicated or cPanel+CloudLinux plans with PG enabled) | zero schema change; everything already tested | most $5–15/mo cPanel plans have **no PG at all**; "any hosting" impossible | Only if the owner confirms a PG-capable plan |
| **B. cPanel + MySQL/MariaDB** | `jsonb` → `json` (4 cols), PG trigger → `BEFORE UPDATE`/`BEFORE DELETE` with `SIGNAL SQLSTATE '45000'`, `READ COMMITTED`/gap-lock ordering review, installer driver toggle + `pdo_mysql` preflight, CI matrix PG/MySQL/MariaDB | works on **every** cPanel host; true plug-n-play | ~12–16 engineering hours (their own estimate); concurrency semantics must be re-proven, not assumed | **Recommended for the stated goal** |
| **C. cPanel + external managed PG** (Supabase/Neon/RDS) with `sslmode=require` | `.env` guidance, IP allowlist, region co-location, connection-loss handling | keeps PG; no schema change; no host PG needed | external dependency + latency budget (must be same region; 60–100 ms RTT would blow LCP) + monthly cost | Good middle path if owner accepts an external DB |
| **D. Static brochure + hosted commerce** (Shopify/Square/Stripe Payment Links) | throw away the store, keep the editorial site | zero ops, PCI handled | less control, monthly fees, less "premium bespoke" | Falls outside the PRD's bespoke-commerce goal |

**If the driver of the project is "upload to any cPanel and it works", Option B is the only honest path.** Option A should not be promised until a named plan passes a real install.

---

## 8. The "$1m UI/UX" upgrade plan (concrete, not adjectives)

Ranked by perceived-quality-per-hour. All are achievable with the existing stack (Blade + Tailwind 4 + Alpine + tokens) and none requires a design-system rewrite.

1. **Fix the invisible/broken components first** (P2-1): drawer badge, toast, gallery, bio band, booking stepper. Right now the most "premium" interaction (the bag drawer) has 5 unstyled children. *This is the single largest visual win available and it is a day of CSS.*
2. **Real imagery discipline** (P2-6): AVIF/WebP/JPEG at 480/960/1600/2400, `srcset` + `sizes` + intrinsic `width/height`, sharpened hero, portrait cropped to a consistent 4:5. Target hero ≤ 180 KB @375 px, total first-view ≤ 500 KB. Premium *is* fast.
3. **Type system** (P2-7): self-host a display serif (Cinzel or a licensed alternative) + a text face (e.g. Inter/Source Sans) as 2 woff2 subsets, `font-display: swap`, preload display, optical sizing on headlines. This alone changes the "custom" perception more than any layout change.
4. **Server-rendered, real content** (P0-3) with photographic product cards, honest price/variant/stock states, and empty/loading/unavailable variants — the states the PRD demands are easier to build on real data.
5. **Flow choreography**: cart-drawer → checkout one-page (or 3-step) with sticky order summary, inline field validation, and a *server-rendered order confirmation page* (`/orders/{reference}`) that works with JS off, prints cleanly, and can be emailed.
6. **Branded system pages** (P2-3): 404/403/419/429/500/503 in the earthy palette with a way forward, not a stack trace.
7. **Micro-interaction budget**: one 180–220 ms easing curve, consistent focus rings, `prefers-reduced-motion` respected (already present), no scroll-jacking, no parallax beyond `transform` on a single hero layer.
8. **Accessibility as craft** (P2-11): `aria-current`, `aria-invalid` + described-by errors, live regions for cart/order changes, visible focus on every interactive element, real table semantics, and a **documented** SR pass only when actually performed.
9. **SEO/social surface** (P2-4) + favicon set (P2-5) — cheap, high credibility.
10. **Owner-facing polish**: Filament brand theme with the same palette, a real dashboard (today's revenue, pending bank verifications, new bookings, low stock) and an onboarding checklist so the non-technical owner can run the store unaided.

**Consistency rule to adopt:** no new `style="…"` attributes; every new visual rule gets a class in `app.css`; every page ships with desktop/tablet/mobile screenshots in `docs/snapshots/` (outside the docroot) before it's called done.

---

## 9. Additional improvements worth scheduling (not blockers)

- **Download re-issue / abuse control**: `download_count` is informational only; add an owner-visible quota (e.g. 5), a re-issue button, and rate limiting per entitlement (ties into P1-8).
- **Guest checkout + order lookup**: `orders.user_id` is nullable and already supports it; add a magic-link `/orders/lookup` so a buyer without an account can retrieve a receipt.
- **Receipt/invoice**: a printable HTML invoice (no PDF library needed) per order.
- **Stock reservation TTL**: carts hold no reservation, so an item can sell out between add and pay; surface that clearly ("no longer available" recovery state, which the PRD requires).
- **Content sections actually rendered** (P0-3 companion): map `section_key` → Blade partials with an allowlist, so the owner's structured editor finally does something.
- **Owner-configurable brand colors at runtime**: emit tokens from `config/tokens.php` merged with allowlisted DB settings into a CSS `:root` block, so recoloring needs no rebuild.
- **Structured data + sitemap** generated on a nightly schedule.
- **`/up` split**: liveness (no DB) vs readiness (DB+cache) for uptime monitors.
- **Error budget/observability**: correlation IDs on webhook/booking/installer failures (partially present) + a daily owner digest email.
- **Data retention**: PRD asks for a retention policy; add a prunable-orders/bookings policy with explicit consent copy before launch.

---

## 10. Prioritized execution plan (ready to pick from)

**Sprint 0 — Trustworthy baseline (½ day, zero risk).**
Add CI (PHP 8.5 + PG 17), run the real suite on PG, delete scratch files, add the PHP-version gate alignment, re-baseline README/ROADMAP/DESIGN docs to what is provable. *Deliverable: a green pipeline and a truthful status page.*

**Sprint 1 — "It actually works" (3–4 days).**
P0-1 cart wiring → P0-2 real checkout → P0-3 DB-driven catalog → P0-4 host asset passthrough → P0-5 Filament assets → P0-6 version gate → P0-7 installer mail step → P1-1/P1-2 credential & snapshot removal. *Deliverable: a bank-transfer order placed in a browser, visible in `/admin`, downloadable, with two emails sent.*

**Sprint 2 — Money & records integrity (2–3 days).**
P1-3 encrypted settings, P1-4 entitlements on bank verify, P1-5 no delete, P1-6 audit coverage, P1-7 refund action, P1-8 rate limiters, P1-9 proxies, P1-10 headers, P1-11 secure cookie.

**Sprint 3 — Craft pass (4–6 days).**
P2-1 orphan CSS, P2-6 images, P2-7 fonts, P2-2/P2-3 404s & error pages, P2-4/P2-5 SEO & icons, P2-8/P2-9 truth & prices, P2-11 a11y, P2-17 timezone.

**Sprint 4 — Packaging & ops (2–3 days).**
P3-1 package script, P3-6 backup/restore, P3-7 schedule, P3-5 logging, P3-8 install removal checklist, P3-9 health, P3-10 docs. *Deliverable: a versioned archive that installs on a clean host in <10 min and a rollback path.*

**Sprint 5 — Hosting proof (needs the owner).**
Named cPanel plan (or Option B/C from §7) + full fresh install + customer walkthrough + real SMTP + sandbox payment creation (P1-12) under a separate approval.

**Not included / requires separate written scope** (per the fixed $750 boundary in `PRD.md`): MySQL/MariaDB driver certification, live payment activation, tax automation, blog/newsletter, multi-language, staff roles.

---

## 11. Decisions needed from the owner (blocking or shaping the above)

1. **Hosting route** — A (must be PG-capable), B (add MySQL support, recommended for plug-n-play), or C (external managed PG)? *This gates Sprint 5 entirely.*
2. **Is the "Faith for the In-Between" collection real?** If yes: approved title, price, contents, and files. If no: remove it.
3. **Product copy/specs** — supply real fabric, print, sizing and care details, or approve neutral placeholder wording.
4. **Bank payout details** — real bank name/account/SWIFT for the instruction block, plus who verifies receipt and how fast.
5. **Shipping** — which countries are actually served, flat rates, and free-shipping threshold.
6. **Legal pages** — privacy, refunds, shipping, terms, contact: owner's advisor text, or approve a drafted template for review.
7. **Email** — SMTP provider + from-address, or accept "no outgoing mail until configured" and hide the booking form meanwhile.
8. **Payments** — approve a separate scope for real Stripe/PayPal payment creation (currently not implemented), or launch with bank-transfer-only.
9. **Ownership of the 5 tracks' audio masters** — when the MP3/WAV files land, who stages them into `core/storage/app/private/audio/`.
10. **Scope confirmation** — which of Sprints 1–4 are inside the fixed $750 and which need a change order (the PRD requires this to be explicit before work is represented as contracted).

---

## 12. Regression-safety rules I'll follow when implementing

1. **Baseline before touching.** Run the full suite on PostgreSQL and capture per-page screenshots (desktop/tablet/mobile) for all 11 views; store them outside the docroot. Every later step is diffed against that baseline.
2. **One flow per change, smallest possible diff.** No refactors bundled with fixes; no "while I'm here" edits in a commit that also fixes a P0.
3. **Tests first for money paths.** Every change to cart/checkout/entitlement/order-state gets a failing test before the fix (stock, tax, totals, refund, bank verification, download abuse).
4. **Feature-flagged demo content.** Anything that must be hidden for launch (demo banner, `/admin/preview`, snapshot pages, mock payment responses) is gated on config/environment — never deleted in a way that loses the review fixture.
5. **Cash-path invariants asserted in code:** cents-only integers, USD locked, totals recomputed server-side, stock decremented atomically, entitlements only after verified funds, no card data ever persisted, every order transition audited.
6. **Deployment verified by extraction, not by inspection.** The acceptance test for any packaging change is: extract the archive into an empty dir, run the installer against a clean DB, and complete a purchase in a browser.
7. **Docs move with code.** README/ROADMAP/SECURITY updated in the same change that makes their claim true or false; no ✅ without reproducible evidence.
8. **Report three things separately:** automated checks, manual/runtime checks, and assumptions that depend on the real host — never blend them into one green claim.

---

## Appendix A — Blade classes with no CSS rule (26)

`audio-time`, `badge-count`, `bio-band`, `bio-body`, `bio-eyebrow`, `bio-inner`, `bio-narrative`, `bio-portrait-frame`, `bio-title`, `booking-step-item`, `booking-stepper`, `btn-remove`, `cart-item-thumb`, `cart-qty-ctrl`, `detail-badge`, `detail-gallery`, `detail-info-panel`, `detail-main-frame`, `detail-thumbs`, `detail-title`, `meta`, `music-item-free`, `options-row`, `product-badge`, `toast-notification`, `track-title`

*(`hide`, `register`, `true` in the raw diff are false positives from Alpine expression parsing. `app.css` contains `.toast-pill`, `.bio-feature-band`, `.bio-grid`, `.bio-image-col`, `.bio-quote-badge` — names no template uses; likely a rename that was never completed.)*

## Appendix B — inline `style=` attribute count per view

| File | Count |
|---|---|
| `account.blade.php` | 175 |
| `admin-preview.blade.php` | 191 |
| `checkout.blade.php` | 60 |
| `booking.blade.php` | 49 |
| `cart.blade.php` | 30 |
| `music.blade.php` | 18 |
| `home.blade.php` | 13 |
| `product.blade.php` | 13 |
| `about.blade.php` | 12 |
| `store.blade.php` | 7 |
| `layouts/app.blade.php` | 11 |
| **Total** | **579** |

## Appendix C — asset budget today

| Asset | Bytes |
|---|---|
| `prince-byron-portrait.jpeg` | 927,137 |
| `pb-hoodie.jpg` | 1,033,073 |
| `pb-tee.jpg` | 927,570 |
| `pb-tote.jpg` | 939,459 |
| `pb-cap.jpg` | 962,883 |
| `faith-for-the-in-between-album.jpg` | 709,830 |
| `prince-byron-hero.jpeg` | 276,840 |
| `Prince_Byron_Free_Lead_Track.mp3` | 705,644 |
| `public/images` total | **5,777,108** |
| `app.css` (built) | 93,098 (13.5 kB gzip) |
| `app.js` (built) | 59,690 (21.0 kB gzip) |
| `favicon.ico` | **0** |

A first visit to `/store` currently requests ~3.85 MB of JPEG for six product cards, plus ~0.28 MB for the hero if the card images are eagerly loaded, before any interaction. The PRD's LCP ≤ 2.5 s at p75 on mobile is not attainable at that weight.

---

---

## 13. Status log — findings closed since the review

**2026-11-23 · Owner decision recorded:** shared-host compatibility via **MySQL/MariaDB** (§7 Option B) is the approved direction. Sprint 5 depends on it; nothing below assumes it yet.

**2026-11-23 · Sprint 0 delivered.** No feature behaviour changed; the work is verification, exposure reduction and truth correction. Every item is CI-enforced so it cannot silently regress.

| Finding | Change | How it is proven |
|---|---|---|
| **P3-2** No CI | Added `.github/workflows/ci.yml`: PHP syntax over every tracked file, `composer validate`, Blade compilation, `pint --test`, full suite on **PostgreSQL (primary)** and SQLite, `migrate:fresh` replay, seeder idempotency, asset build + budget, packaging rules | Workflow definition; jobs fail independently |
| **P3-1** No release tooling | Added `tools/check-package.mjs` (include/exclude/forbidden-content/web-root-layout rules) and `tools/check-assets.mjs` (gzip budget) | Both run green, and were verified to **fail** on an injected live-key string, a tracked `.env`, and a missing build output |
| **P0-6** PHP floor mismatch | Installer `$requiredPhp` raised 8.3 → 8.5 with an actionable message; `composer.json` already required `^8.5` and the lock agrees (`platform.php = ^8.5`); new test asserts installer floor == composer floor. A `config.platform` pin was added and then **reverted**: it changes Composer's content hash, so `composer install` aborts and vendor/ can never be built. Lock sync is now guarded by `tools/check-lock.mjs` | `test_installer_php_floor_matches_composer_constraint`, `node tools/check-lock.mjs` |
| **P1-14 (partial)** Preflight not enforced server-side | Installer aborts before touching disk/DB when preflight fails, instead of only hiding the submit button | Guarded in `install/index.php`; existing CSRF/mutex tests still apply |
| **NEW** `core/` and `storage/` had **no** deny rules of their own | Added `core/.htaccess` (deny-all) and `core/storage/.htaccess` (deny-all + `RewriteRule ^ - [F,L]`); both asserted by the packaging check | `tools/check-package.mjs` INCLUDE rules |
| **P1-13** Dev artifacts shipped | Deleted committed SQLite DB, 3 scratch scripts, invalid `redis_test.php`, `public/find_overflow.js`, storage capture output | PHP syntax check now passes 100% (was 130/131) |
| **P1-2** Screenshots in the web root | Moved 14 review pages to `docs/review-snapshots/`; capture scripts write there; packaging check fails on any return to `core/public/snapshots/` | `tools/check-package.mjs` EXCLUDE rule |
| **P1-12 / P3-10** Overstated status | README now states provider **webhook verification** is complete while **payment creation is not implemented**, describes the storefront as demo-data driven, and flags the Mailpit installer default | README status + limitations sections; ROADMAP Phase 5.5 |
| **P2-9 (partial)** Docs drifted from code | `docs/ARCHITECTURE.md` installer/stack sections corrected to the 8.5 floor; ROADMAP gained a Phase 5.5 with per-sprint evidence | Documentation diff |

**Deliberately not done in Sprint 0** (each needs its own focused change, per §12):

- Wiring the storefront (P0-1…P0-3) — requires UI refactor plus test updates; scheduled as Sprint 1.
- Host asset passthrough (P0-4) and Filament assets (P0-5) — must be verified against a real web server, not by inspection.
- Installing Filament's compiled assets cannot be performed in this environment (no PHP/Composer). **This remains an open launch blocker:** `vendor/` and `public/{css,js,fonts}/filament` must be produced on the build machine by `composer install && php artisan filament:assets`. Awaiting an environment where PHP and Composer are available, or explicit owner approval to run that step in Antigravity.
- Running the test suite on the authoring machine — no PHP runtime or PostgreSQL was available there. **This is now resolved by CI** (see the entry below).

**2026-11-23 · First real CI runs — five genuine defects found and fixed.** The
suite had never actually executed (there was no CI, and `composer install`
could not run), so "71 passing tests" was historical. Running it for real found:

| # | Defect | Why it mattered |
|---|---|---|
| 1 | `config.platform.php` was added to `composer.json`, desynchronising `composer.lock` | `composer install` aborted, so **vendor/ could never be built**. Reverted; content-hash verified by porting Composer's own algorithm; guarded by `tools/check-lock.mjs` |
| 2 | Installer rendered the literal word **"Pass"** for *failing* checks | A host missing an extension was told it passed while the install button was disabled. Now renders "Fail" plus the remedy (e.g. MultiPHP Manager guidance) |
| 3 | Installer hard-required **`pdo_pgsql`** | Would reject the ordinary shared cPanel hosts this project targets. Now accepts MySQL/MariaDB **or** PostgreSQL and names which was found |
| 4 | Sample prices shown as real money with **no disclosure** | Taking money against a placeholder price is a consumer-protection issue. `config/commerce.php` now drives a disclosure on both pages that show prices; 24-bit WAV is marked unavailable rather than promised |
| 5 | Four tests asserted strings that **existed nowhere in the codebase** (e.g. `sample demonstration prices`, `In Stock`) | The suite could never have been green; these tests were silently guarding nothing, so the defects they covered were invisible |

Also fixed: `phpunit.pgsql.xml` had lost the **Unit** suite (the "primary" job
skipped `tests/Unit`); the PostgreSQL job passed `--configuration` while
`artisan test` injects its own, so it ran the wrong config; and a malformed
workflow step name silently produced a CI run with **zero jobs**, now caught by
`tools/check-workflows.mjs`.

**Verified state:** all five CI jobs green — 73 tests / 504 assertions on both
SQLite and PostgreSQL, full migration replay from an empty database, seeder
re-run parity, Blade compilation, PHP syntax across every tracked file, lock
sync, packaging rules and the asset budget. Pint is advisory until the
codebase is normalised in its own commit.

**2026-11-23 · Sprint 1 delivered — the storefront is wired to the server.**

| Finding | Status | Evidence |
|---|---|---|
| **P0-1** client-only pre-seeded cart | **Closed** | `resources/js/app.js` is a cache of `/api/cart*`; a new visitor's bag starts empty; every price shown comes from a server summary |
| **P0-2** simulated checkout | **Closed** | `checkout.blade.php` posts to `/api/checkout/process`; the reference is persisted server-side. Tests assert the order row exists |
| **P0-3** hardcoded catalog | **Closed** | `App\Services\CatalogService`; unpublishing a product removes it, unknown slugs 404, prices follow `product_variants` |
| **P0-4** `/images`+`/audio` 404 | **Closed** | Guarded rewrites in root `.htaccess`; `favicon.ico` replaced (was 0 bytes) |
| **P0-5** Filament assets gitignored | **Mitigated** | Rewrites added + `tools/check-release.mjs` blocks an archive without them. The build itself still needs a PHP+Node machine — reported as INCOMPLETE, not claimed as done |
| **P0-7** installer writes Mailpit | **Closed** | Real mail step with an honest "record to log" option; test asserts Mailpit literals are gone |
| **P1-1** demo credentials rendered | **Closed** | Banner and pre-filled credentials removed; test asserts the page contains neither |
| **P1-4** bank transfer bypasses entitlements | **Closed in Sprint 2** | See the Sprint 2 table below |
| **NEW** `CartService` did not validate song ids | **Closed** | An invented id returned `success: true` while the summary dropped it. Now 422 |
| **NEW** music page advertised delisted tracks | **Closed** | Curated playlists are gated on those tracks being published |

Two defects were also found in the *test suite itself*, which matters because it
had been trusted: `tests/TestCase.php` never booted the application (Laravel 11+
requires the skeleton to supply `createApplication()`), so anything touching the
container or database failed while static-markup tests passed; and four
assertions described a storefront that no longer existed. Both are fixed, which
is why the suite now means something.

Verified: **97 tests / 576 assertions** at the close of Sprint 1.

**2026-11-23 · Sprint 2 delivered — money and records integrity.**

Every finding here was a silent failure: nothing crashed and the storefront
looked healthy while the record underneath was wrong. Each is pinned by a test
that asserts what a customer or an auditor would notice.

| Finding | Status | Evidence |
|---|---|---|
| **P1-3** encrypted settings saved in plaintext from the admin form | **Closed** | The form bound the raw `value` column, bypassing the model's mutator. It now never renders the stored secret, encrypts on submit, keeps the ciphertext when the field is blank, and a `Setting::saving()` guard covers non-form writers. `Phase5SettingsFormTest` drives the real Filament page and inspects the column |
| **P1-4** bank transfer granted no entitlements | **Closed** | Inline Stripe/PayPal code (two copies, one path missing) replaced by `App\Services\GrantEntitlementsForOrder`; `Verify Bank Funds` calls it. Guest orders return `skipped_guest` and the owner is notified by name |
| **P1-5** financial records could be deleted | **Closed** | `Order::delete()` throws; bulk delete removed from the order resource. The same hole from the other side of the FK: `orders.user_id` is `nullOnDelete`, so a customer with orders could be deleted, orphaning their orders — now refused too |
| **P1-6** no audit trail for catalog/content/settings edits | **Closed** | `App\Models\Concerns\Auditable` on products, variants, songs, settings, content sections, shipping rates. Secrets redacted per column — the log records *that* a secret rotated and by whom, never its value or ciphertext |
| **P1-7** no refund action | **Closed** | Owner-only action: guarded reason, negative `Payment`, entitlements revoked (deactivated, not deleted), audit event. Required a migration — `payments.amount_cents` was `unsignedInteger` and could not have stored a refund |
| **P1-8** cart/checkout/downloads/webhooks unthrottled | **Closed** | Named limiters in `AppServiceProvider`: cart reads, cart mutations, checkout (per-IP + global ceiling), downloads (per user), webhooks, auth |
| **P1-9** no trusted proxies | **Closed** | `App\Http\Middleware\TrustProxies` reads `config('security.trusted_proxies')` per request; empty by default. Behind a CDN every throttle key previously collapsed onto the proxy IP |
| **P1-10** security headers only in `.htaccess` | **Closed** | `App\Http\Middleware\SecurityHeaders` sends CSP/HSTS/nosniff/frame/ referrer/permissions headers from the application. Storefront CSP needs no `unsafe-inline` because the front end ships no inline script; Filament gets only what it needs |
| **P1-11** session cookie not Secure in production | **Closed** | Installer derives `SESSION_SECURE_COOKIE` from the site URL and writes `SESSION_ENCRYPT`, `SESSION_SAME_SITE`, `SESSION_HTTP_ONLY`, plus a documented `TRUSTED_PROXIES` placeholder |

**Defects this sprint introduced, and how they were caught.** Both were wrong
code that the pipeline rejected within a minute of the push — the reason the
suite is run before any status is claimed:

1. `config()` called inside `bootstrap/app.php`'s `withMiddleware()` closure.
   That closure runs while the container is resolving the kernel, before `config`
   is bound, so every artisan command died with "Target class [config] does not
   exist" — which broke `composer install` (post-autoload-dump runs
   `package:discover`) and would have taken production down completely. Proxies
   are now read at request time.
2. Deleting the `Request` import from `bootstrap/app.php` because a grep said it
   was unused. It is a closure parameter type hint; every request died with a
   `TypeError`. **An import may only be deleted on the strength of a compile,
   never a grep.**

Also: `property_exists($this, 'type')` is false for an Eloquent attribute
(attributes are not declared properties), so the first audit-redaction
implementation redacted nothing and archived ciphertext. The test caught it.

**Infrastructure.** CI step logs are not retrievable from the development
sandbox, which made a failing step indistinguishable from a broken commit. The
workflow now retries the composer install with backoff, republishes composer's
error tail as an annotation, and publishes suite totals as a notice on green
runs — which is how the README figure is verifiable at all.

Verified: **128 tests / 673 assertions** passing on SQLite and PostgreSQL, full
migration replay from an empty database, Blade compilation, PHP syntax over
every tracked file, packaging rules and the asset budget. Every job green.

**2026-11-23 · Sprint 3 delivered — craft pass, plus the two residual risks.**

| Finding | Status | Evidence |
|---|---|---|
| **P2-1** 26 Blade classes with no CSS rule | **Closed** | Rules authored for the bag drawer's five children, the toast, the product gallery, the bio band, the booking stepper and `track-format`. `tools/check-classes.mjs` now runs in CI: 225 classes across 12 views, 3 allowlisted utility names, 0 missing |
| **P2-3** no branded error pages | **Closed** | 404/403/419/429/500/503 in the design system, with a deliberate stylesheet outside Vite so they render even when the front-end build is absent — which is exactly the state an error page is most likely to be seen in |
| **P2-4 / P2-5** no SEO surface, no icons | **Closed** | Canonical URLs, Open Graph/Twitter metadata, `Offer` JSON-LD only for purchasable items (nothing else gets marked `noindex`), `/sitemap.xml` generated from the live catalogue, real favicon set and web manifest |
| **P2-6 / P2-7** unoptimised images, no display type | **Closed** | JPEG + WebP at 480/960/1600/2400 with intrinsic dimensions via `<x-img>`; hero 276,840 B → 31,274 B, portrait 927,137 B → 13,536 B. Cinzel + Source Sans 3 self-hosted, 95 KB total, both OFL |
| **P2-8 / P2-9** untrue claims and price drift | **Closed** | Unapproved $12 collection removed, WAV claims corrected to match `has_wav`, invented specifications struck, prices reconciled |
| **P2-11** accessibility gaps | **Closed, with one honest exception** | `aria-current`, `aria-invalid` + `aria-describedby` on every required checkout and booking control, `aria-live` on error surfaces and the order confirmation, focus moved to the confirmation on success, `[x-cloak]` defined so unstyled Alpine state cannot flash. **Screen-reader testing across JAWS/NVDA/VoiceOver remains unverified** — no automated substitute exists |
| **P1-5 remainder** no anonymisation path | **Closed** | Sprint 2 refused to delete a customer with orders and told the owner to "deactivate or anonymise" without building either: a wall in front of a privacy obligation. `AnonymiseCustomer` removes identity from account and order, revokes entitlements, deactivates, and audits the act without copying the removed values into the log. Money, items, dates, reference and shipping country survive |
| **P0-8** MySQL/MariaDB unsupported | **Closed** | `jsonb` → `json` in four migrations; per-driver audit trigger (PostgreSQL / MySQL-MariaDB `SIGNAL SQLSTATE` / SQLite `RAISE(ABORT)`, with a logged fallback); `phpunit.mysql.xml` and a MariaDB 11 CI job running the whole suite |

**Defects this sprint introduced, and how they were caught.** Three, all by CI:

1. `DatabasePortabilityTest` lacked `RefreshDatabase`, so its JSON round-trip ran
   against an empty database. Every driver failed with "no such table".
2. The tamper-verification assertion could not read after a refused `UPDATE` on
   PostgreSQL, where a failed statement aborts the whole transaction (25P02) and
   MySQL/SQLite abort only the statement. The refused write now runs in its own
   transaction.
3. `phpunit.mysql.xml` was not well-formed XML: a dashed rule inside a comment
   (`------`) is illegal, PHPUnit could not read the configuration, and every
   test in the MariaDB run errored — including one that asserts `true === true`.
   Nothing in the output said "bad XML"; the per-test annotations were rejected
   for containing `--`, and the rejection text replaced the results. A parser
   confirmed it in one line. `tools/check-phpunit-xml.mjs` now gates the whole
   class of defect in the `static` job, negative-tested both ways.

Verified: **170 tests / 829 assertions** on **SQLite, PostgreSQL and MariaDB 11**
— identical totals on all three — plus full migration replay from an empty
database, Blade compilation, PHP syntax over every tracked file, class coverage,
packaging rules and the asset budget. Six jobs, all green (run 45408009229).

---

*Review originally written 2026-11-23; §13 appended as findings were closed. Application feature code was not modified by the review itself. Sprint 0 did modify code — the installer, the storefront disclosure and the test harness — and each such change is listed above with the defect it closes.*
