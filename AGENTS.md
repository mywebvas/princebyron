# Project build rules

## Source of truth

Read `PRD.md`, `docs/ARCHITECTURE.md`, `docs/SECURITY.md`, and `docs/ROADMAP.md` before making changes. The user's current instructions take precedence over this file. Preserve approved product facts and the commercial boundary in `PRD.md`.

`/prototype` is a behavior/content reference only. Do not copy its source code, page layouts, CSS, or visual compositions into the production experience. Recreate the approved purpose with a separately reviewed premium aesthetic.

## Build discipline

- Work one user flow at a time, in roadmap order. Keep changes narrow and understandable.
- Treat exceptional performance, rigorous security, and premium UX as day-one acceptance requirements. Measure rather than make unverified “optimized,” “bank-grade,” “million-dollar,” or “future-proof” claims.
- Use good Core Web Vitals (75th percentile) as performance targets, OWASP ASVS 5.0 Level 2 as the application security verification target, and WCAG 2.2 AA as the accessibility target. Record test profiles, results, exceptions, and evidence; recheck current standards and framework requirements at implementation/release.
- The owner reports Docker Desktop, PostgreSQL, and PHP 8.5 are installed on the PC. Inventory versions and running services before setup; prefer an isolated project database and preserve existing global services unless change is necessary and reversible.
- Prefer the simplest clear code that satisfies correctness, safety, and maintainability. “20 elite lines instead of 200” is a simplicity preference, never a line-count quota or reason to compress/obscure code.
- Keep decisions reversible where practical: modular monolith first, safe migrations, minimal dependencies, explicit boundaries, and scale mechanisms added in response to measured need. Do not promise universal future-proofing.
- Before major production UI, prepare an interactive review prototype and obtain approval for the concrete direction.
- Keep public, owner, and payment-provider responsibilities explicit. Validate and authorize every write on the server.
- Money values are integer cents plus a locked USD currency. Never trust browser-calculated prices, stock, shipping, or payment status.
- Do not store card data. Verify Stripe/PayPal callbacks, signatures, amount, currency, and idempotency before changing an order.
- Manual bank payments remain pending until the owner independently verifies funds and records the decision.
- Keep audio outside public storage; grant downloads only for a paid, non-refunded entitlement. Use short-lived authorized delivery links or controller-streamed files.
- Do not expose `.env`, `vendor`, logs, private storage, installer, or database backups from the public web root.
- Admin-only actions require authenticated owner permission and audit records. Content editors accept structured fields only; never execute user-controlled markup or styles.
- No real payment activation, email sending, DNS changes, production release, or destructive data action without exact user authorization.
- Do not add dependencies without a concrete requirement and a current compatibility check. Build all frontend assets before deployment; no Node runtime on the host.
- Report automated checks, runtime checks, and unverified host/provider assumptions separately.

## Release boundary

The approved prototype does not authorize production launch. The fixed $750 engagement remains separate. Do not imply that additional roadmap work or integrations fall inside its price unless the written contract says so.
