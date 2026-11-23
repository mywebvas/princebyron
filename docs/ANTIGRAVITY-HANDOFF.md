# Antigravity implementation handoff

## Ownership and boundary

The user has chosen Antigravity for all production implementation. This project package was prepared here for the static prototype, interactive review concept, and concise project documentation. Do not scaffold or implement the production Laravel application in this workspace.

The existing `/prototype` is a behavior and content reference. `/design-review` is a clickable concept to help the owner communicate preferences. The premium hybrid direction and earthy palette are approved at the strategy level; the concrete `/design-review` composition is not approved as the production design. Antigravity must create a distinct, more premium production interface and obtain the owner's approval of a concrete review before building production screens.

## Read before changing files

1. `AGENTS.md`
2. `PRD.md`
3. `docs/ARCHITECTURE.md`
4. `docs/SECURITY.md`
5. `docs/ROADMAP.md`
6. `prototype/README.md` and `prototype/docs/PROTOTYPE-SPEC.md` for existing prototype facts and demo limits
7. `docs/DESIGN_REVIEW.md` and `design-review/` for the separate design concept

If documents conflict, preserve the latest explicit owner decisions in the conversation and update the affected document before implementation. Do not treat prototype copy or sample data as verified biography, legal copy, inventory, shipping policy, or live provider configuration.

## Required execution sequence

1. Work in `C:\Users\autoc\Documents\princebyron`. Confirm the project files are present and inspect the target before writing; preserve `/prototype` unchanged. Do not overwrite unrelated files.
2. The owner reports that Docker Desktop, PostgreSQL, and PHP 8.5 are already installed on the PC. Inventory their exact versions, enabled PHP extensions, running services/containers, Composer/Node availability, and Windows state. Recheck official Laravel, PHP, PostgreSQL, Tailwind, and Filament compatibility; do not reinstall existing components without a demonstrated need.
3. Follow Phase 1 of `docs/ROADMAP.md` for local development setup. Prefer an isolated/reproducible project database. Keep existing runtimes and databases intact until the project setup is independently verified. Record exact changes, evidence, and rollback steps.
4. Build a distinct premium production design using the approved strategy: cinematic dark opening, elegant light editorial sections, earthy palette, modern spiritual tone, responsive behavior, restrained motion, WCAG 2.2 AA accessibility, and low-bandwidth optimization. Do not copy prototype layouts or styling.
5. Deliver a clickable visual review with representative public and commerce/booking flows and its important validation, empty, pending, unavailable, success, and recovery states. Stop for the owner's approve/revise/reject decision before building production screens.
6. Implement one flow at a time in roadmap order, keeping scope, migrations, authorization, validation, and rollback explicit. Keep blog/newsletter in a later phase.
7. Build and inspect a local release archive with `vendor/` and compiled assets. Do not claim cPanel readiness until it has been tested against the owner's actual host and plan, which is not yet known.

## Fixed decisions

- Artistic identity: a blend of gospel genres; premium, modern, spiritual, soulful, warm, and internationally appealing. The approved visual strategy is premium hybrid: cinematic dark opening with elegant light editorial sections and an earthy palette.
- Domain: `princebyron.com`; market: United States and international; checkout currency: USD.
- Music: Free, Purify Me, I Always Win, Toduyun, Follow Me. The owner sets free/paid status and prices. The owner states that Prince Byron owns these songs.
- Initial merchandise demo prices: T-shirt $32, hoodie $58, cap $30, tote $25, art print $25, sticker pack $6; bundles $35 and $80. These are editable starting prices, not a fulfillment or margin approval.
- Payments planned: Stripe, PayPal, and manual bank transfer. No credentials are supplied here. Manual bank orders stay unpaid until the owner independently reconciles receipt.
- Shipping: start with owner-managed rates and destination rules. Exact fulfillment and permitted countries remain undecided; do not invent them.
- Bookings: legitimate appearance/ministry invitations, guided request-for-quotation, no public fee.
- Admin: owner-only, controlled structured content sections, supported content and brand colors; no arbitrary executable content or unrestricted page builder.
- Blog and newsletter: after core commerce, music, bookings, and accounts.
- Hosting: generic cPanel target; provider and plan are not yet identified. Host supplies PHP and PostgreSQL; no Composer/Node commands on the host. Use a root loader and application under `core/`, with prebuilt `vendor/` and assets, only if the actual host supports the required runtime and PostgreSQL access.
- Commercial boundary: the previously agreed $750 production scope remains fixed. Roadmap additions are separate unless included in a written agreement; prepare a change order for expanded scope.

## Live-service gates

Use provider sandbox modes until separately configured and approved. Never put real credentials in chat, source control, or prototype files. Do not activate live payments, bank instructions, email, customer authentication, protected downloads, DNS changes, or a production release without owner-provided configuration and explicit release approval. Never claim a transaction is real in a prototype.

## Day-one quality expectations

- Performance: target good LCP, INP, and CLS at the 75th percentile, measured against documented mobile and desktop profiles. Record asset, query, server-response, and load measurements; optimize from the first implementation slice.
- Security: use OWASP ASVS 5.0 Level 2 as the verification target, with deeper threat review of accounts, owner admin, payment events, manual bank reconciliation, and protected music. “Bank-grade” expresses the desired rigor, not a certification claim or guarantee of zero vulnerabilities.
- UX: deliver internationally appealing, high-craft visual design and cohesive end-to-end flows, with accessible controls, clear errors/recovery, reduced motion, and resilience on low-end devices and unstable networks.
- Code: prefer the shortest clear and maintainable solution. “20 elite lines over 200” is not permission to golf or obscure code. Correctness, security, and maintainability take priority over line count.
- Adaptability: use explicit module boundaries, reversible migrations, limited dependencies, and measured scaling decisions. Do not promise that any architecture is future-proof in every aspect.

## Completion reporting

At each phase, summarize files changed, commands/actions taken, evidence, remaining assumptions, and rollback. Keep roadmap boxes unchecked until their evidence exists. Separate local verification from staging-host verification; report the actual target host as unverified until named and tested.
