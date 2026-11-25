# Admin Dashboard — Deployment & Operations

## What ships

The owner admin panel is **Filament 5** at `/admin`, gated to owner accounts only
(`User::canAccessPanel()` → `role === 'owner' && is_active`).

Eight resources are live immediately:

| Resource | URL | What the owner controls |
|----------|-----|-------------------------|
| Dashboard | `/admin` | Stats, recent orders, pending actions, quick actions |
| Orders | `/admin/orders` | All orders, status transitions, bank verification, refunds |
| Products | `/admin/products` | Products, variants, stock, images, categories |
| Songs | `/admin/songs` | Songs, pricing, publish, audio masters, formats |
| Booking Requests | `/admin/booking-requests` | Ministry RFQs, quote, accept/decline |
| Content Sections | `/admin/content-sections` | Homepage hero, about bio, legal pages, footer copy |
| Settings | `/admin/settings` | Site name, SMTP, Stripe/PayPal keys, tax, currency |
| Shipping Rates | `/admin/shipping-rates` | Country-based rates, free-shipping thresholds |
| Users | `/admin/users` | Customer/owner accounts, roles, activation, password reset |
| Audit Events | `/admin/audit-events` | Read-only immutable log of every admin action |

## How to enable on a fresh install

The admin panel is registered in `core/bootstrap/app.php` via `->withProviders()`
and is active by default on every install. No manual setup is required.

## First owner account

The installer creates the first owner account with the password typed during
installation. That account is the only one that can access `/admin`.

To create additional owner accounts:
1. Sign in to `/admin` as the first owner.
2. Go to **Users** → **New User**.
3. Set role to **Owner**.

## Security model

- **Authentication:** Laravel `web` guard, session-based, CSRF-protected.
- **Authorization:** `User::canAccessPanel()` — owner role + active account,
  checked server-side on every `/admin` request.
- **Rate limiting:** Reuses `RateLimiter::for('auth')` (5/min) for admin login.
- **Audit trail:** Every admin action writes an immutable `AuditEvent` (who,
  what, when, old values, new values, IP).
- **Encrypted settings:** SMTP passwords and API keys stored encrypted
  (`Setting::TYPE_ENCRYPTED`).
- **No customer data leakage:** `/admin` is fully separate from the customer
  portal at `/account`.

## Mobile experience

The admin panel uses the same design system as the customer portal:
- Off-canvas sidebar drawer on mobile (hamburger toggle)
- Card-stacked tables (each row = tappable card)
- 44px+ touch targets throughout
- Single-column forms on narrow viewports
- `prefers-reduced-motion` respected

## Rollback

If anything is wrong, reverting `core/bootstrap/app.php` removes the admin
panel registration and restores the exact pre-admin state. All customer routes
and the `/account` portal are untouched.

## What is NOT in this phase

- Custom dashboard charts/widgets (revenue over time, low-stock alerts) — Phase 2
- Bulk actions (select multiple orders → mark paid) — Phase 2
- CSV export (orders, users, audit log) — Phase 2
- Multi-owner staff invitations — Phase 3
- Real-time activity feed — Phase 3

These are documented in `docs/ADMIN-DASHBOARD-PLAN.md` and will be implemented
in follow-up work after your approval.