# Admin SaaS Dashboard — Plan (for approval)

**Status:** Phase 1 IMPLEMENTED — admin panel active at `/admin` (owner-only).  
**Branch:** `arena/9adff00e-princebyron` (level with `main` at `829dc27`, CI green).  
**See also:** `docs/ADMIN-DEPLOYMENT.md` for operations.  
**Starting point:** Existing customer portal at `/account` (custom Blade + Alpine SaaS portal).  
**Existing assets:** 8 Filament resources already authored (Orders, Products, Songs, BookingRequests, ContentSections, Settings, ShippingRates, AuditEvents) but the panel provider is **not registered** and no admin routes exist — they are dead code.

---

## 1. Decision: Admin = Filament panel, Customer = custom portal

**Do not merge admin into the customer portal.** The `/account` page is a customer-facing Fan Vault (orders, entitlements, downloads). It must stay customer-scoped.

**Activate the Filament admin panel at `/admin`** — register `AdminPanelProvider` in `bootstrap/app.php`, add admin routes, protect with `User::canAccessPanel()` (owner role only). All 8 existing Filament resources become live immediately.

**Why:** Filament is the Laravel admin standard. The resources are already written. Registering them is ~20 lines of config, not a rebuild. The customer portal stays untouched — zero regression risk.

---

## 2. What the admin controls (everything)

| Domain | Resource | Admin actions |
|--------|----------|---------------|
| **Dashboard** | Custom overview | Stats cards, recent orders, low-stock alerts, pending bank verifications, quick actions |
| **Users** | `User` | List all customers/owners, create owner accounts, deactivate/reactivate, reset password, view order history |
| **Songs** | `Song` | CRUD, publish/unpublish, pricing, free vs paid, audio file paths, sort order |
| **Products** | `Product` + `ProductVariant` + `ProductImage` | CRUD, variants (color/size/price/stock), images, categories, featured toggle |
| **Orders** | `Order` + `OrderItem` + `Payment` | View all orders, status transitions (pending → paid → fulfilled → refunded), verify bank payments, issue refunds, download invoices |
| **Booking Requests** | `BookingRequest` | View RFQs, quote (add price/notes), accept/decline, mark reviewed |
| **Content Sections** | `ContentSection` | Edit homepage hero, about bio, legal pages (refund, privacy, terms, shipping), footer text — no code deploys for copy changes |
| **Settings** | `Setting` | Site name, email (SMTP), Stripe/PayPal keys, tax %, currency, maintenance mode |
| **Shipping Rates** | `ShippingRate` | CRUD country-based rates, free-shipping thresholds, active/inactive |
| **Download Entitlements** | `DownloadEntitlement` | View all entitlements, revoke (refund), expire, re-grant |
| **Audit Events** | `AuditEvent` | Read-only immutable log — who changed what and when |

---

## 3. Information architecture (navigation)

Grouped sidebar navigation (same rail pattern as `/account` for consistency):

```
┌─ Prince Byron Backoffice (brand)
│
├─ Overview          (dashboard stats)
├─ Commerce          ┬─ Orders
│                    └─ Products
├─ Music             ┬─ Songs
│                    └─ Download Entitlements
├─ Ministry          ┬─ Booking Requests
├─ Content           ┬─ Content Sections (pages/legal)
│                    └─ Settings (site config)
├─ People            ┬─ Users
└─ Audit             ┬─ Audit Events (read-only)
```

**Global header:** Search (⌘K), notifications bell, owner profile dropdown, logout.  
**Mobile:** Off-canvas sidebar drawer (same as `/account`), hamburger toggle, bottom action bar for primary actions.

---

## 4. Page-by-page UX specification

### 4.1 Dashboard (overview)
- **Stat cards** (top row): Total orders, pending bank verifications, total revenue (paid), active entitlements, low-stock products, new users (7-day).
- **Recent orders table** (5 rows, clickable to order detail).
- **Pending actions panel:** "3 orders awaiting bank verification" — each with a "Verify payment" button.
- **Quick actions grid:** New song, new product, add shipping rate, edit legal page.
- **Empty states** with illustrated CTAs when no data.

### 4.2 Orders (the most-used page)
**Table view:** reference, customer, date, status badge, total, payment method, actions (view).
**Status badges:** pending (amber), awaiting bank (blue), paid (green), fulfilled (emerald), refunded (rose), cancelled (gray), failed (red).
**Order detail modal/page:** customer info, itemized list, shipping address, payment details, status transition dropdown (owner can move pending→paid→fulfilled→refunded), bank verification evidence upload, refund button, audit trail below.

**Frictionless flow:** Click "Verify Payment" → modal with amount/reference fields → save → order moves to `awaiting_bank_verification` or `paid` → toast confirms → row badge updates optimistically.

### 4.3 Products & Variants
**Product table:** thumbnail, title, category, price, stock, active toggle, actions.
**Product detail:** tabs — General (title, desc, category, price), Variants (color/size/stock grid), Images (reorderable gallery), SEO (slug, meta).
**Variant quick-edit:** Inline stock adjustment without leaving the page.

### 4.4 Songs
**Table:** #, title, price, free badge, publish toggle, format badges (MP3/WAV), actions (play preview, edit, delete).
**Form:** title, slug, description, price, is_free, is_published, upload MP3/WAV, cover art, sort order.

### 4.5 Booking Requests (Ministry)
**Table:** reference, event name, event date, status (new/reviewing/quoted/accepted/declined), customer, actions.
**Detail:** full form submission, internal quote price field, internal notes, accept/decline buttons (trigger email to customer).

### 4.6 Content Sections & Legal Pages
**List:** section key, title, last edited, publish toggle, edit button.
**Edit:** rich text (or markdown) editor for HTML content, publish/unpublish. Covers: homepage hero, about bio, refund policy, privacy, terms, shipping, footer copy.

### 4.7 Settings
**Groups:** Site (name, tagline, currency, timezone), Email (SMTP host/port/user/pass, from address), Payments (Stripe keys, PayPal client ID, test/live toggle), Tax (default %), Maintenance (on/off + message).
Encrypted fields (passwords, API keys) masked with reveal toggle.

### 4.8 Users
**Table:** name, email, role badge (owner/customer), active toggle, orders count, last login, actions (edit role, deactivate, reset password).
**Create owner:** name, email, password (min 8), role=owner. First owner must exist before anyone can sign in to admin.

### 4.9 Shipping Rates
**Table:** country, rate, free threshold, active toggle.
**Form:** country (ISO code selector), rate cents, free shipping above amount, active.

### 4.10 Audit Events (read-only)
**Table:** timestamp, actor (user), action (order.paid, product.created, etc.), auditable type, old/new values diff, IP, correlation ID. Export CSV. No delete/edit — immutable by design.

---

## 5. Mobile experience (first-class, not an afterthought)

| Pattern | Desktop | Mobile (< 640px) |
|---------|---------|------------------|
| Navigation | Fixed left rail (270px) | Off-canvas drawer (hamburger → slide in) |
| Tables | Full columns | Stack as cards: each row = card with labeled fields |
| Forms | Multi-column grids | Single column, full-width inputs |
| Buttons | Side-by-side | Stacked, full-width, 48px touch targets |
| Modal/Detail | Centered modal | Bottom sheet (slide up from bottom) |
| Search | Top bar inline | Top bar + ⌘K hint |
| Dashboard stat cards | 4-col grid | 1-col stacked |
| Quick actions | 4-col grid | 2-col grid |

**All touch targets ≥ 44px.**  
**No hover-only actions** — everything reachable by tap.  
**Backdrop tap closes drawers/modals.**  
**Escape key closes modals.**  
**Focus trapping** in modals and drawers (same as customer portal).

---

## 6. Design system (extends existing tokens)

Reuse `config/tokens.php` + `resources/css/tokens.css` exactly — no new color palette. Admin uses the same obsidian/charcoal/clay/gold family.

**New admin-specific tokens:**
- `--admin-surface`: `#181310` (charcoal) for admin sidebar/header
- `--admin-surface-elevated`: `#221B16`
- `--admin-border`: `rgba(223, 167, 89, 0.30)`
- `--admin-text-primary`: `#FDFBF7`
- `--admin-text-muted`: `#C5BCAD`

**Components (Blade + Alpine, same as customer portal):**
- `.admin-sidebar` — fixed rail / off-canvas drawer
- `.admin-topbar` — sticky header with search + profile
- `.admin-table` — striped, sticky header, mobile-card fallback
- `.admin-badge` — status variants (paid/pending/refunded/failed)
- `.admin-btn-primary` / `.admin-btn-secondary` / `.admin-btn-danger`
- `.admin-modal` / `.admin-bottom-sheet`
- `.admin-toast` — same as customer portal
- `.admin-skeleton` — loading states for async actions

**No GSAP.** CSS keyframes (fadeInUp, slideInRight, pulseSoft) — hardware accelerated, ~0 KB JS, respects `prefers-reduced-motion`. Already proven in the customer portal.

---

## 7. Tech stack & implementation approach

| Layer | Technology | Rationale |
|-------|-----------|-----------|
| Admin panel | Filament 5 (existing resources) | Already authored, Laravel-native, owner-role gated |
| Frontend | Blade + Alpine.js (same as `/account`) | Consistency, zero new dependencies, no build step for admin views |
| Styling | Existing CSS tokens + new admin-specific classes | No new CSS framework |
| Icons | Heroicons SVG (already in use) | Consistent with customer portal |
| Notifications | Alpine toasts (already built) | Same pattern as customer portal |
| Data | Eloquent models (already exist) | No new migrations |
| Auth | Existing `web` guard + `canAccessPanel()` | Owner-only gate already implemented |
| Rate limiting | Existing `RateLimiter::for('auth')` | Reused for admin login |

**Files to touch (minimal):**
1. `core/bootstrap/app.php` — register `AdminPanelProvider`
2. `core/routes/web.php` — add `Route::prefix('admin')` group (or rely on Filament's path)
3. `core/resources/views/layouts/app.blade.php` — add "Admin" link in user dropdown when `auth()->user()->isOwner()` (discoverability from customer portal)
4. `core/app/Providers/Filament/AdminPanelProvider.php` — may need minor tweaks (navigation groups, widgets)
5. New: `docs/ADMIN-DEPLOYMENT.md` — how to enable admin on a fresh install

**Files NOT touched (zero regression):**
- All customer portal views (`/account`, `/checkout`, `/music`, `/store`, `/about`, `/booking`)
- All Filament resource PHP files (already correct)
- All models, migrations, seeders
- All tests (202 passing)

---

## 8. Security & permissions

- **Gate:** `User::canAccessPanel()` returns true only when `role === 'owner'`.
- **Route:** `/admin` behind Filament's `Authenticate` middleware + session auth.
- **CSRF:** Filament's middleware stack includes `PreventRequestForgery`.
- **No customer data leakage:** Admin views only — customer portal (`/account`) unchanged and still customer-scoped.
- **Audit trail:** Every admin action (order status change, user deactivation, product edit) writes to `AuditEvent` — already wired in Filament resources.
- **Encrypted settings:** SMTP passwords, Stripe keys stored encrypted via `Setting::TYPE_ENCRYPTED` (already implemented).

---

## 9. Migration / no-regression guarantee

| Item | Before | After |
|------|--------|-------|
| Customer portal (`/account`) | Live | Unchanged |
| Admin panel (`/admin`) | Dead code (not registered) | Live, owner-only |
| CI tests | 202 passing | 202+ passing (no existing test modified) |
| Local checkers | 5/5 PASSED | 5/5 PASSED |
| Public routes | Unchanged | Unchanged |
| Installer | Unchanged | Unchanged |

**Rollback:** If anything is wrong, reverting `bootstrap/app.php` + `routes/web.php` restores the exact pre-admin state. Filament resources are additive.

---

## 10. Phased rollout (after approval)

**Phase 1 — Activate admin panel (this PR):**
- Register `AdminPanelProvider`, add admin routes, owner-only gate.
- All 8 Filament resources live: Orders, Products, Songs, BookingRequests, ContentSections, Settings, ShippingRates, AuditEvents.
- Admin link discoverable from customer portal user dropdown (owner only).

**Phase 2 — Polish (follow-up, not this PR):**
- Custom dashboard widgets (revenue chart, low-stock alerts).
- Bulk actions (select multiple orders → mark paid).
- Export CSV (orders, users, audit log).
- Dark/light admin theme toggle.

**Phase 3 — Advanced (future):**
- Activity feed (real-time via Laravel Echo or polling).
- Scheduled reports (email owner daily summary).
- Multi-owner support (invite staff with limited permissions).

---

## 11. Approval checklist (owner to confirm)

- [ ] Activate Filament admin panel at `/admin` (owner-only)
- [ ] Keep customer portal at `/account` completely unchanged
- [ ] Reuse existing 8 Filament resources (no rewrites)
- [ ] Mobile-first: off-canvas drawer, card-stacked tables, 44px touch targets
- [ ] No GSAP — CSS animations only
- [ ] No new CSS framework — extend existing tokens
- [ ] All admin actions write to `AuditEvent` (immutable log)
- [ ] Zero regression: customer routes, tests, CI, installers untouched
- [ ] Rollback is a 2-file revert

---

**Reply with approval and I'll implement Phase 1.** If you want changes to the scope (add/remove resources, different URL, different navigation grouping), say so now before any code is written.