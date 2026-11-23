# Security requirements

This file defines production requirements; it is not evidence that any safeguard has been implemented.

## Security assurance target

Use [OWASP ASVS 5.0](https://asvs.owasp.org/) Level 2 as the application's day-one verification baseline, tracked requirement-by-requirement in implementation evidence. Threat-model high-impact flows and apply additional review to owner authentication, account recovery, payment callbacks, manual bank reconciliation, and private downloads. “Bank-grade” is the owner's ambition for rigor; this project must not claim bank certification or absolute security without an independent assessment and applicable certification.

## Actors and boundaries

| Actor | Allowed capabilities | Required enforcement |
|---|---|---|
| Visitor | Browse public pages, catalog and booking form | Public routes expose approved published content only. |
| Customer | Manage own account, orders and eligible downloads | Server-side authentication, ownership checks, secure recovery and rate limits. |
| Owner | Manage site content, catalog, orders, bookings and settings | Strong owner authentication, least privilege, CSRF protection, audit trail, re-authentication for sensitive settings. |
| Payment provider | Send signed payment events | Signature verification, exact order/amount/currency match, replay protection and idempotency. |

## Required safeguards

- Use Laravel's framework validation, CSRF protections, output escaping, password hashing, secure cookies, and authorization policies. Do not bypass them with raw SQL or unsafe template rendering.
- Use HTTPS everywhere; secure session cookies; rotate application keys safely; keep secrets in host environment configuration outside the release package.
- Require MFA for owner/admin access where the selected authentication stack supports it safely; protect recovery and high-risk setting/payment actions with re-authentication or a second factor. Do not rely on obscurity or a non-public admin URL.
- Apply rate limits to login, password reset, booking, checkout, download and webhook endpoints. Add abuse controls without blocking legitimate low-bandwidth users.
- Keep prices and permissions server-authoritative. Validate product IDs, variants, quantities, addresses, dates, uploads, webhook payloads and every state transition.
- Never store payment card details. Store only provider references and minimal reconciliation data.
- Treat bank-transfer proof and references as untrusted. Do not grant fulfillment/downloads from user-submitted proof; an owner independently verifies the bank statement.
- Keep purchased audio outside public paths; authorize every download and use expiring delivery URLs or a controlled download response. Avoid permanent public links.
- Minimize customer data; define retention and deletion policy before launch. Do not log passwords, full payment payloads, bank details, download tokens, or unnecessary personal data.
- Protect admin content fields from script injection. Use allowlisted blocks, upload MIME/size validation, safe image processing, and non-executable file storage.
- Lock the setup wizard after successful installation. Deny direct HTTP access to `core/`, `.env`, `vendor/`, logs, private storage, backups, and installer files.
- Fail closed on unconfigured or incomplete payment webhook secrets (HTTP 500 error, reject unverified payloads).
- Verify PayPal webhooks using official asymmetric RSA signatures (`SHA256withRSA`) over the 5 transmission headers; enforce strict domain allowlisting (`api.paypal.com` or `api.sandbox.paypal.com`) on certificate URLs to prevent SSRF attacks.
- Prevent stock overselling under concurrent checkout requests using pessimistic row locking (`lockForUpdate()`) and atomic conditional decrements (`where('stock_quantity', '>=', $qty)->decrement(...)`) within database transactions.
- Preserve customer download entitlements when digital master files are not yet staged on disk; do not increment consumption counters or update `last_downloaded_at` until the physical audio file is successfully streamed.
- Enforce immutability on audit logs (`audit_events` table) at both the Eloquent application layer (blocking `updating`, `deleting`) and the database layer: PostgreSQL utilizes a dedicated PL/pgSQL function and `BEFORE UPDATE OR DELETE` trigger; SQLite uses native abort triggers; any future MySQL/MariaDB driver migration requires distinct `BEFORE UPDATE` and `BEFORE DELETE` triggers with `SIGNAL SQLSTATE '45000'`.
- Enforce permanent installer lockout with zero query-parameter bypass (`?force=1` or `?bypass=true` are strictly rejected with HTTP 403 Forbidden). Recovery requires authenticated server-side filesystem access to delete the lock file.
- Centralize all design tokens and color values in `config/tokens.php` and compiled `tokens.css`; enforce zero raw color literals across application frontend views, styles, and scripts to prevent unreviewed styling overrides or broken high-contrast states (automated token scanner verified across 46 application files).
- Form inputs maintain visible unfocused boundaries and distinct focus rings meeting WCAG 2.2 Level AA criteria:
  - **SC 1.4.11 Non-text Contrast (Level AA, minimum 3:1)**: Measured contrast ratios:
    - Light input border (`#8A7D71`) vs White card (`#FFFFFF`): **4.00:1**
    - Light input border (`#8A7D71`) vs Paper canvas (`#F3EFE6`): **3.48:1**
    - Dark input border (`#7E6B5A`) vs Obsidian hero (`#0E0B09`): **3.87:1**
    - Light focus ring (`#9E472A`) vs White card (`#FFFFFF`): **6.20:1**
    - Light focus ring (`#9E472A`) vs Paper canvas (`#F3EFE6`): **5.40:1**
    - Dark focus ring (`#DFA759`) vs Obsidian hero (`#0E0B09`): **9.16:1**
  - **SC 2.4.7 Focus Visible (Level AA)**: High-contrast `:focus-visible` styling applied across all interactive elements. *(Note: SC 2.4.13 Focus Appearance is a Level AAA criterion and is not claimed as part of the Level AA baseline).*
  - **SC 1.3.1 Info and Relationships (Level AA)**: Strictly sequential single-`h1` heading hierarchy (`h1` -> `h2` -> `h3` -> `h4`), explicit form `<label for="...">` associations, and semantic tables.
  - **SC 2.1.1 Keyboard & SC 2.1.2 No Keyboard Trap (Level AA)**: Full keyboard tab traversal and modal dismissal verified in browser testing.
  - *Unverified Boundary*: Formal screen-reader audits with NVDA, JAWS, or VoiceOver, and physical mobile hardware testing, remain unverified and require dedicated assistive technology evaluation.
- Segregate review credentials and demo fixtures from real operational records:
  - Seeded test bookings are visibly labeled `[DEMO FIXTURE]` to prevent simulated inquiries from being mistaken for real customer RFQs.
  - Review credentials (`fan@princebyron.local` / `TemporaryFanPass2026!`, `owner@princebyron.local` / `TemporaryOwnerPass2026!`) are local-only development review fixtures and must never be committed to production documentation, deployment archives, or live databases.
  - Customer dashboard displays plain, factual status labels ("Account Status: Active • Verified", "Session authenticated; private file access") without claiming unverified at-rest envelope encryption or signed URL tokens.

## High-impact failure cases

| Failure | Impact | Defense and recovery |
|---|---|---|
| Forged/replayed webhook marks an order paid | Lost revenue or free digital goods | Verify provider signature, amount, currency and state; enforce idempotency; alert on mismatches. |
| Manual payment screenshot is treated as proof | Fraudulent fulfillment/download | Require independent owner reconciliation, retain audit record, provide pending state to customer. |
| Private song is exposed by a guessed URL | Unpaid distribution | Store outside public root; check entitlement on every access; expire links and log abnormal attempts. |
| Cross-account order/download access | Customer data or content disclosure | Query through authenticated owner relation and test cross-object denial. |
| Installer remains active or app files are public | Account takeover or secret exposure | One-time installation lock, path denial, release verification and external HTTP checks. |
| cPanel lacks PostgreSQL/PDO_PGSQL or required cron | Install or background workflow fails | Preflight capability check; stop install with actionable message; document compatible host requirement. |
| Shared host storage or limits are exceeded | Upload, email, or checkout interruption | Set upload limits, validate quotas, monitor disk/database, alert owner, keep safe recovery/backup path. |

## Release checks

Before launch: verify authorization with real owner/customer/unauthenticated callers, payment sandbox and webhook replay paths, manual transfer reconciliation, download authorization and expiry, XSS/CSRF/input abuse cases, login/reset throttling, backup restore, installer lockout, log redaction, and host-specific public-path isolation. Production credentials and payment activation are supplied by the owner through a secure channel and are never committed.

Track the applicable ASVS controls and any exceptions, with owner-visible evidence. Recheck framework, runtime, and dependency advisories during release preparation; patch or document mitigations before deployment. Security is an ongoing process, not a one-time checklist or certification statement.
