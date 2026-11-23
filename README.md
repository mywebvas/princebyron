# Prince Byron — Website and Store

This is the production project workspace for **princebyron.com**. The `/prototype` folder contains the existing static prototype as a behavior and content reference only. `/design-review` is a separate, interactive design concept for owner review; it has not been approved as the production design. Rebuild the public experience with a separately reviewed premium visual direction; do not copy either prototype's layouts or styling as the production design.

## Current status & local review

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

- **Payment Mode:** Stripe and PayPal operate in sandbox mode; no live transactions are processed.
- **Bank Transfer:** Visually flagged as a demonstration flow; orders remain pending until approved in owner panel.
- **Email:** Routed to local Mailpit (`127.0.0.1:1025`/`8025`); no live external emails are sent.
- **Shipping Rates:** The owner has not finalized physical shipping zones or carrier rates. Live checkout requires configured destinations in `/admin/shipping-rates`.
- **Master Audio Files:** Studio WAV masters are pending final artist delivery; test placeholder files are securely streamed for review.


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

“No Composer/Node” does not remove the host's need for a compatible PHP runtime, required PHP extensions, a reachable PostgreSQL service, writable Laravel runtime folders, and HTTPS. The actual cPanel plan must be verified before calling a release upload-ready. See [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md).

## Commercial boundary

The previously agreed **$750 production scope remains fixed and separate**. The production features listed in this project's roadmap are not silently treated as included in that agreement. Confirm the written deliverables and separately approve any expanded scope, pricing, or production release.

## Prototype and AI handoff

Read `/docs/ANTIGRAVITY-HANDOFF.md` first when starting production work in Antigravity. Then read `AGENTS.md`, `PRD.md`, `docs/ARCHITECTURE.md`, `docs/SECURITY.md`, and `docs/ROADMAP.md`. Read `/prototype/README.md` and `/prototype/docs/PROTOTYPE-SPEC.md` for approved content facts and demo boundaries. Treat `/prototype` and `/design-review` as reference material, not code or design templates. Follow the phase sequence and never activate real payments, customer authentication, email, protected downloads, DNS, or deployment without the required credentials and explicit release approval.
