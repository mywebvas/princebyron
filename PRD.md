# Product requirements — Prince Byron website and store

## Product goal

Create a memorable, trustworthy home for Prince Byron's gospel music and ministry, where listeners can discover music, purchase merchandise and digital releases, follow the artist, and request an appearance or ministry booking.

## Users and goals

- **Listener:** understand the artist, discover and preview songs, then stream or buy eligible releases.
- **Customer:** choose merchandise options, pay in USD, and understand delivery and order status.
- **Fan:** create and manage an account, see purchases, and access eligible downloads.
- **Event organiser:** submit a complete event request and receive a quotation follow-up.
- **Owner:** manage the catalog, prices, supported page content, brand colors, orders, downloads, bookings, and customer-facing settings without editing code.

## Approved scope

### Public experience

- Independent, responsive pages for Home, About, Music, Store, product details, Cart/Checkout, Booking, Account, and policy/support content.
- Premium hybrid art direction with a dark cinematic hero and elegant light editorial sections; premium, modern, spiritual, soulful, warm, and internationally appealing. Use an earthy palette, expressive typography, strong artist photography, purposeful motion, accessible controls, and low-bandwidth-friendly assets.
- Music catalog for the five approved songs. Owner-configurable free/paid status, price, preview, format availability, and bundle offers. Paid purchases create server-verified download entitlements.
- Merchandise catalog using the approved demo prices as initial editable prices. Product variants include size and color where applicable; owner-managed inventory and shipping rates.
- USD checkout with Stripe and PayPal integrations and a manual bank-transfer option. Manual transfers remain pending until the owner verifies and records receipt.
- Guided booking request with event details, location, date, audience/requirements, contact details, validation, acknowledgement, and owner follow-up. Public rates remain hidden; bookings request a quotation.
- Customer accounts for order history, profile, and authorized music downloads.
- Responsive, accessible interactions with explicit empty, invalid, pending, unavailable, success, and recovery states.

### Owner experience

- Owner-only authenticated admin area with role checks enforced on the server.
- Structured page/section editor: edit supported text, images, order, visibility, metadata, and design tokens. No arbitrary PHP, HTML, or CSS execution in content fields.
- Manage products, variants, inventory, music, pricing, offers, orders, payment states, download entitlements, booking enquiries, shipping rates, and site settings.
- Audit trail for consequential admin edits and payment/order status changes.

## Later phase

- Blog and newsletter signup, only after the core launch flows are stable and an email provider, consent wording, unsubscribe handling, and data retention are defined.
- PWA/offline behavior only if a real listener/customer need is demonstrated. The commerce and admin surfaces must not cache private account, order, or download data offline.
- Automated shipping-carrier quotes, tax automation, staff roles, and advanced visual page-building are not part of the first admin slice unless separately approved.

## Quality attributes and acceptance targets

These are day-one design and implementation constraints, not post-launch polish. Record evidence against them as each flow is built. Reference baselines: [OWASP ASVS](https://asvs.owasp.org/), [Core Web Vitals](https://web.dev/articles/vitals), and [WCAG 2.2](https://www.w3.org/TR/WCAG22/).

- **Performance:** aim for good Core Web Vitals at the 75th percentile: LCP ≤2.5 seconds, INP ≤200 milliseconds, and CLS ≤0.1. Measure mobile and desktop separately using a documented device/network profile. Include server response and database query budgets under a defined representative load before release; do not claim performance without that evidence.
- **Security:** use OWASP ASVS 5.0 Level 2 as the application security verification target, with focused review of account, payment, webhook, admin, and download paths. “Bank-grade” describes the owner's security ambition; it is not a certification claim or promise that vulnerabilities are impossible. Keep all payment card data with Stripe/PayPal hosted or tokenized flows.
- **Experience:** achieve a distinctive, high-craft premium hybrid design with a warm, soulful, spiritual and internationally appealing identity. Target WCAG 2.2 AA, keyboard and screen-reader usability, reduced-motion support, clear loading/error/recovery states, and responsive behavior on low-end phones and unstable connections.
- **Maintainability and adaptability:** keep modules cohesive, interfaces explicit, dependencies limited, and data changes reversible. Prefer short, clear code over unnecessary layers or duplication. Do not use code-golf or line-count quotas; clarity, correctness, security, and maintainability outrank raw line count.
- **Reliability:** payment and order transitions must be idempotent and auditable; backups must have a demonstrated restore procedure; failed external services must produce safe, recoverable states.

Targets are measurable acceptance goals, not warranties. Revisit them only with recorded evidence and owner agreement; never silently lower a target to make a failing implementation appear complete.

## Non-goals and explicit boundaries

- The prototype is not production code or the visual design to copy. Preserve its approved facts and interactions, then create and review a more premium production design.
- No invented biography, testimonials, metrics, release dates, merchandise specifications, or unapproved claims.
- No public booking prices.
- No storage of card data; use payment-provider-hosted/tokenized flows and verified server notifications.
- No claiming “upload and works” until a named cPanel plan passes compatibility checks.
- The $750 agreement is a separate fixed scope. This roadmap does not amend it or declare the broader features included.

## Acceptance outcomes

- A visitor can navigate each dedicated page at mobile and desktop sizes and use keyboard and assistive-technology basics.
- Music, merchandise, booking, account, and admin flows have clear pending/error/empty/success behavior and safe recovery.
- Server-side price, stock, shipping, entitlement, and permission checks remain authoritative; browser values cannot set paid status or prices.
- Every payment state is idempotent and auditable; manual transfers cannot grant paid downloads until an owner records a verified payment.
- Customer details and protected audio are not exposed in public paths or logs.
- A release archive runs without Composer or Node on the host, after host-specific configuration and database setup, and has a documented rollback and backup procedure.
- Performance budgets, accessibility checks, security verification, and recovery evidence are recorded for the release candidate; no vague “optimized” or “secure” claim substitutes for results.
- Blog/newsletter remain outside the first core launch unless re-approved.

## Decisions and assumptions

| Status | Decision | Notes |
|---|---|---|
| APPROVED | Laravel 13+ and PHP 8.5 locally where compatible | Use the latest compatible versions if the host requires a lower supported version; verify exact requirements at setup and deployment. |
| APPROVED | PostgreSQL for local and production data | Shared host PostgreSQL support is not yet verified. |
| APPROVED | No Composer or Node commands on the host | Build dependencies and assets before packaging. |
| APPROVED | cPanel-compatible root loader and app in `core/` | Keep secrets and application internals out of public web access. |
| APPROVED | Core commerce, music, bookings, and accounts are launch goals | Payment credentials, provider onboarding, policies, tax, and hosting still need release gates. |
| APPROVED | Stripe, PayPal, and manual bank transfer | Country: USA. Bank transfers require owner reconciliation; never trust customer-submitted proof alone. |
| APPROVED | Owner-only structured admin | Blog/newsletter later; no unrestricted code-capable page builder. |
| APPROVED | Antigravity is the production implementation environment | This workspace is for the prototype and documentation handoff; production app setup and feature implementation happen in Antigravity. |
| ASSUMED | Owner-managed shipping zones and rates initially | Owner configures allowed destinations/rates after fulfillment is known. |
| ASSUMED | Manual bank flow begins as “awaiting verification” | Show bank instructions only after selecting this method; owner marks paid after independent reconciliation. |
| ASSUMED | Sales tax and legal policy review are launch gates | Final rules require advice from the business's qualified US tax/legal advisors. |

## Scope control

No production feature should be represented as delivered under the $750 agreement unless it appears in that agreement's written deliverables. Obtain a separate written estimate and approval for added scope before production implementation or release.
