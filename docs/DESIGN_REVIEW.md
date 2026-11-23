# Premium visual review — Sanctuary & Stage Direction

**Status:** Ready for Owner Review & Decision. This document describes the interactive design review in [`design-review/index.html`](file:///c:/Users/autoc/Documents/princebyron/design-review/index.html). It has **not** yet been approved for production. The owner must approve, revise, or reject this concrete design review before production Blade screens are implemented.

---

## 1. Art Direction Brief & Palette

- **Goal:** Present a distinctive, warm, soulful, spiritual, and internationally appealing aesthetic for Prince Byron's music and merchandise ministry.
- **Atmosphere:** Deep cinematic espresso/charcoal opening (`#0E0B09`, `#181310`) with golden ambient radiance (`#DFA759`, `#C8883B`) transitioning into tactile linen and warm terracotta clay (`#9E472A`, `#F8F4ED`).
- **Signature Elements:** Authentic artist photography without facial retouching (`prince-byron-hero.jpeg` and `prince-byron-portrait.jpeg`), typographic discipline pairing classical high-craft display serif (`Cinzel`, `Playfair Display`, Didot fallback stack) with refined grotesque sans (`Plus Jakarta Sans`, system-ui), and restrained, purposeful motion.
- **Strictly Rejecting:** Generic black-and-gold gospel cliches, default bootstrap/card-grid aesthetics, artificial urgency banners, fake social proof counters, and runtime CDN dependencies.

---

## 2. Requested Prototype Revisions (Implemented & Verified)

### 1. Orderly 2-Column Responsive Footer
- **Layout Hierarchy:** The artist/ministry name and mission description appear first across full width.
- **Navigation Structure:** Links are arranged after the branding into two clear, balanced columns:
  - **Column 1 (Music & Ministry):** All 5 Music Releases, Free Download (Lead Track), Ministry Booking & RFQ, Fan Vault & Entitlements, Owner Admin Preview (Demo).
  - **Column 2 (Store & Official Channels):** Apparel & Merch Store, Signature Tee, and official external channels (Spotify, YouTube, Instagram, TikTok, Facebook).
- **Mobile Responsive Treatment:** On mobile viewports (`max-width: 600px`), the brand block stays top-aligned, and the two navigation columns sit side-by-side in a 2-column grid (`grid-template-columns: 1fr 1fr; gap: 28px 20px;`). This prevents an excessively tall single-column scroll and creates an organized, orderly footer.

### 2. Main-Menu Header Branding
- **Tagline Removal:** Removed `Gospel • Faith • Ministry` from beneath the site logo/name in `<header class="site-header">`.
- **Typographic Alignment:** Cleanly centered `<h1>Prince Byron</h1>` alongside the illuminated `PB` monogram. Artist and ministry descriptions are preserved in the footer and narrative body sections.

### 3. Mobile Cart Drawer (95% Viewport Width)
- **Viewport Dimension:** On mobile screens, the sliding drawer width is constrained to `95vw` (instead of 100%), leaving a visible 5% slice of the darkened backdrop page on the left.
- **Backdrop & Dismissal:** Tapping the visible backdrop dismisses the drawer immediately. Added keyboard `Escape` key dismissal.
- **Horizontal Stability:** Verified `overflow-x: hidden` with adjusted item grid spacing (`56px 1fr auto; gap: 12px;`) so contents never trigger horizontal scrolling.

### 4. Hero Action Buttons on Mobile
- **Responsive Stacking:** On mobile viewports (`max-width: 600px`), the hero action buttons stack vertically (`flex-direction: column; width: 100%; gap: 12px;`).
- **Full-Width Touch Targets:** Both "Listen to the Music &rarr;" and "Request Ministry Booking" expand to 100% width of their container with centered text and comfortable 44px+ tap targets.
- **Desktop/Tablet Preservation:** Horizontal inline flex arrangement is preserved on desktop and tablet screens.

### 5. Mobile Navigation Menu (Diagnosed & Fixed)
- **Root Cause Diagnosis:** The mobile hamburger button (`#btn-menu-toggle`) lacked an event listener, and there was no mobile navigation drawer markup in the DOM.
- **Interactive Fix:** Built a dedicated mobile navigation drawer (`#mobile-nav-overlay` with `#mobile-nav-panel` at `z-index: 1050`) containing all page routes, a public demo admin link, and direct social links.
- **Accessibility & Interaction:**
  - Configured with `role="dialog"`, `aria-modal="true"`, and `aria-label="Mobile Navigation Menu"`.
  - Added a strict focus trap (`trapFocusInDialog()`) keeping `Tab` and `Shift+Tab` cycles constrained within the menu.
  - Dynamically applies `inert` and `aria-hidden="true"` to background content (`header`, `main`, `footer`, `.review-dock`, `.demo-ribbon`, `.skip-link`) while open, preventing focus leakage to background elements.
  - Clicking `#btn-menu-toggle` sets `aria-expanded="true"`, reveals the panel, and places initial focus on the close button (`#btn-close-mobile-nav`).
  - Dismissal resets `aria-expanded="false"`, sets `aria-hidden="true"`, removes background `inert` states, and programmatically restores focus to `#btn-menu-toggle`.
  - Dismissal verified via `Escape` key, close button click, backdrop click, or route navigation.
  - Analogous accessible modal dialog treatment implemented for the shopping bag drawer (`#bag-drawer`).

### 6. Separate Public Demo Admin Preview (`#/admin-preview`)
- **Public Simulated Demo Boundary:** Added a public demonstration preview of planned Filament backoffice screens, completely separate from the customer/fan account.
- **Zero Real Authentication or Live Records:** Prominently badged as a public simulated demo. All pricing adjustments, inventory counts, and reconciliation actions operate in local browser state with fictional demo data. No real authentication, database records, payment gateways, or customer communications are touched.
- **Strict Reservation of "Owner-Only":** The designation "owner-only" is strictly reserved for the future production admin interface, where server-side authentication, role policies, and middleware will enforce authentic owner authorization.
- **5 Comprehensive Management Tabs:**
  1. **Music Catalog & Audio:** Table of all 5 approved songs ('Free', 'Purify Me', 'I Always Win', 'Toduyun', 'Follow Me') showing pricing mode (Free vs Paid USD), prices in cents, MP3/WAV format flags, private audio storage paths (`storage/private/audio/...`), active entitlement counts, and an interactive "Toggle Mode" simulation button.
  2. **Products & Variants:** Catalog table of merchandise (Signature Tee, Arch Hoodie, Monogram Cap, Canvas Tote, Giclée Print, Sticker Pack, Bundles) showing SKUs, variants, prices in USD, inventory counts, and an interactive "Toggle OOS" simulation button.
  3. **Orders & Bank Payment Verification:** Shows customer orders across Stripe, PayPal, and manual bank wire. Includes an interactive reconciliation simulation for Order `PB-202611-002`: clicking **"Verify Bank Funds & Mark Paid"** transitions the order to `Paid & Settled`, updates status badges, and appends a verified entry to the simulated immutable audit trail.
  4. **Ministry Booking RFQ:** Table of booking requests (Grace Community Fellowship, Hope City Worship) showing event types, dates, locations, audio technical specs, and quotation action triggers.
  5. **Controlled Brand CMS:** Shows structured content fields (Hero Eyebrow, Main Headline, Bio Quote) and the approved, non-arbitrary 5-color brand palette swatches.

---

## 3. Interactive Views & Routing Map

| Flow / View | URL Hash | Key Features & Safeguards |
|---|---|---|
| **Home** | `#/home` | Cinematic dark hero, persistent audio teaser, narrative bio band with authentic portrait, 5 approved songs quick-play, and merchandise highlights. |
| **Our Story (About)** | `#/about` | Dedicated narrative view on Prince Byron's background, worship ministry vision, authentic studio portrait, foundational pillars, and ministry booking CTA. |
| **Music Catalog** | `#/music` | Featured Collection ("Faith for the In-Between"), all 5 singles ("Free", "Purify Me", "I Always Win", "Toduyun", "Follow Me"), format selector (MP3 / 24-bit WAV), and 3 moment playlists ("Stillness", "Strength", "Joy"). |
| **Store & Merch** | `#/store` | Apparel, accessories, and bundles with demo placeholder prices, category filters, and direct links to dedicated single-product detail views. |
| **Product Detail** | `#/product?id=tee` | Dedicated single-product page with breadcrumbs, high-res visual switcher, color selector, size picker (S-XXL), live stock badge, quantity stepper, and craftsmanship specs. |
| **Shopping Bag** | `#/cart` | **Dedicated Standalone Cart Page:** Itemized table, variant details, live quantity stepper, sample pricing disclaimer, and seamless transition to checkout. |
| **Mobile Bag Drawer** | Slide-Over | 95% mobile width with visible backdrop slice, accessible modal focus trap, live line-item subtotal in USD cents, quantity stepper, and direct checkout link. |
| **Dedicated USD Checkout** | `#/checkout` | **Dedicated Standalone Checkout Page (Not Modal-Only):** Multi-step stepper (Contact &rarr; Delivery &rarr; Payment Review) with Stripe card decline simulation, PayPal, and manual bank wire (`awaiting_bank_verification` lifecycle state). |
| **Booking RFQ** | `#/booking` | Guided 3-step Request-for-Quotation for ministry appearances, event types, technical audio rider specs, and submission confirmation. No public booking fee exposed; booking policies, lead times, and geographic feasibility are evaluated upon receipt. |
| **Fan Vault** | `#/account` | Customer-owned active digital entitlements with MP3/WAV streaming simulation, download tokens, and recent order status tracking. Distinct from admin preview. |
| **Demo Admin Preview** | `#/admin-preview` | **Public Simulated Demo (Not Owner-Only):** Public demonstration preview of backoffice management flows (5 tabs: music pricing, product inventory, manual bank payment reconciliation simulation, booking RFQ queue, and controlled brand CMS). No real authentication, records, or live payment gateways. |

---

## 4. Official Artist Social Links

All external artist profiles are wired to verified official links with `target="_blank" rel="noopener noreferrer"` and descriptive screen-reader labels:
- **Spotify:** `https://open.spotify.com/artist/089TupQTFoR8OvKrDOp1NA`
- **YouTube:** `https://www.youtube.com/channel/UCeeZS5FkmYj6F4fwykc7f0g`
- **Instagram:** `https://www.instagram.com/prince_byron_official`
- **TikTok:** `https://www.tiktok.com/@princebyron94`
- **Facebook:** `https://www.facebook.com/princebyron89/`

---

## 5. Performance & Verification Boundaries

> [!IMPORTANT]
> **Prototype Verification vs. Production Measurement:**  
> Field Core Web Vitals (CWV) cannot be authentically measured on a static local design prototype; meaningful field metrics (LCP, INP, CLS at the 75th percentile) are **deferred until a deployed production build receives real user traffic**. For this prototype review, performance was audited qualitatively:
> - **Zero External Network Font Requests:** Completely removed all Google Fonts CDN links. The prototype renders instantaneously using a high-craft local system font stack.
> - **Asset Weight & Requests:** HTML is lightweight (~190 KB), with zero runtime JavaScript framework dependencies. External network requests: **0**.
> - **Layout Stability:** Explicit aspect ratios and image dimensions (`width` and `height` attributes) are declared on artist photography to eliminate Cumulative Layout Shift (CLS).

### Color Contrast Verification (WCAG 2.2 AA Target >= 4.5:1)
Relative luminance and contrast ratios were mathematically calculated using sRGB linearized luminance formulas:

| Foreground / Background Pair | Calculated Ratio | WCAG 2.2 AA Result |
|---|---|---|
| Off-White (`#FDFBF7`) on Obsidian Dark (`#0E0B09`) | **18.98:1** | PASS (Exceeds 7.0:1 AAA) |
| Deep Charcoal (`#181310`) on Pure White (`#FFFFFF`) | **18.43:1** | PASS (Exceeds 7.0:1 AAA) |
| Deep Charcoal (`#181310`) on Linen Paper (`#F8F4ED`) | **16.81:1** | PASS (Exceeds 7.0:1 AAA) |
| Warm Gold (`#DFA759`) on Obsidian Dark (`#0E0B09`) | **9.16:1** | PASS (Exceeds 7.0:1 AAA) |
| Muted Earth (`#594C44`) on Pure White (`#FFFFFF`) | **8.26:1** | PASS (Exceeds 7.0:1 AAA) |
| Muted Earth (`#594C44`) on Linen (`#F8F4ED`) | **7.54:1** | PASS (Exceeds 7.0:1 AAA) |
| Muted Warm Gray (`#A89F91`) on Obsidian Dark (`#0E0B09`) | **7.50:1** | PASS (Exceeds 7.0:1 AAA) |
| Amber Radiant (`#C8883B`) on Obsidian Dark (`#0E0B09`) | **6.58:1** | PASS (Exceeds 4.5:1 AA) |
| Pure White (`#FFFFFF`) on Terracotta Clay (`#9E472A`) | **6.20:1** | PASS (Exceeds 4.5:1 AA) |
| Terracotta Clay (`#9E472A`) on Pure White (`#FFFFFF`) | **6.20:1** | PASS (Exceeds 4.5:1 AA) |

---

## 6. PostgreSQL Runtime & Compatibility Planning

- **Active PC PostgreSQL Version:** PostgreSQL **17.9** (running as Windows Service `postgresql-x64-17` on port 5432; preserved untouched).
- **Project Runtime Database:** PostgreSQL **17.9** (`postgres:17-alpine` container on port 5437; verified with 11 migrations, seeders, and 16 tests).
- **Upstream Release Status:** PostgreSQL **18.7** has been released upstream. Host system PostgreSQL remains unchanged at 17.9.
- **Planned Compatibility Matrix & Test Plan:**
  - **Active Runtime:** PostgreSQL 17 (verified with migrations, seeds, and test suite).
  - **Supported Preceding Releases:** PostgreSQL 16 and PostgreSQL 15 (both officially supported by Laravel 13 and `pdo_pgsql`).
  - **Upcoming / Target Release:** PostgreSQL 18 (tracked for future validation as container images and driver compatibility stabilize).
- **Verification Discipline:** Avoid any version-specific syntax or proprietary extensions. The compatibility matrix across versions (15, 16, 17, 18) is an automated CI/CD test plan to be proven with test runs before production release, not an already-certified status.

---

## 7. Regression Diagnosis & Resolution Summary

### Blank-Page Defect Diagnosis
- **Symptom:** Direct hash navigation and menu clicks to routes such as `#/home`, `#/store`, `#/booking`, and `#/account` rendered empty/blank screens.
- **Root Cause:** A maintenance cleanup script (`clean_admin.js`) utilized a non-greedy regular expression `/\s*<!-- =+[\s\S]*?VIEW: OWNER ADMIN PREVIEW[\s\S]*?<\/section>/g` across repeating section boundaries. Because of a duplicate comment marker, the match greedily crossed multiple section boundaries, unintentionally excising five core view panels (`view-home`, `view-store`, `view-product`, `view-booking`, `view-account`).
- **Correction:** The pristine markup for all views was recovered from version transcript history (turn snapshot line 407), integrated with the new dedicated About (`#/about`), Cart (`#/cart`), and Checkout (`#/checkout`) views, and recompiled using a structured assembly script (`build_complete_prototype.js`). All 10 views are verified present and syntactically sound.

### Full Music Experience Verification
- **Featured Collection:** "Faith for the In-Between" (5 master tracks, digital booklet, sample price $12.00 USD).
- **5 Singles Catalog:**
  1. *Free (Lead Track)* — Free Download (01 / 4:18)
  2. *Purify Me* — $1.99 USD sample price (02 / 5:02)
  3. *I Always Win* — $1.99 USD sample price (03 / 3:45)
  4. *Toduyun* — $1.99 USD sample price (04 / 4:32)
  5. *Follow Me* — $1.99 USD sample price (05 / 4:54)
- **Audio Scrubber & Format Support:** Filter chips for All/Free/Paid, audio format switcher (MP3 320kbps vs 24-bit Studio WAV), and curated moment playlists (*Stillness*, *Strength*, *Joy*).
- **Persistent Bottom Player Bar:** Docked at the viewport bottom (`#music-player-bar`) featuring play/pause toggle, track metadata, interactive progress scrubber, and close control.

### Demo & Commercial Boundaries
- **Simulated Backoffice:** Admin area (`#/admin-preview`) is explicitly badged as a **Public Simulated Demo** with zero live authentication, no payment gateways, and no real customer or booking record persistence. "Owner-only" is reserved strictly for the future production system.
- **Bank Payment Reconciliation:** Demonstrates manual fund verification where transfers remain `awaiting_bank_verification` until owner confirms funds.
- **Sample Pricing:** Track pricing ($1.99), collection pricing ($12.00), merchandise prices, and demo bag totals ($33.99) are explicitly disclaimed as demonstration samples, not owner-approved rates.
- **Performance Targets vs. Prototype:** Field Core Web Vitals (75th percentile) and OWASP ASVS 5.0 L2 are codified production acceptance targets, deferred to production traffic. Prototype qualitative checks confirm 0 external font/CDN requests and rock-solid layout stability.

---

## 8. Fictional Demo Placeholders & Policy Boundaries

To preserve strict product boundary discipline, all unapproved facts, specifications, and commercial policies are explicitly treated as demonstration placeholders:
- **Booking Availability & Geographic Scope:** Removed all "worldwide" availability claims. Geographic feasibility, travel logistics, and artist scheduling are evaluated upon RFQ submission.
- **Booking Notice & Policies:** Removed the arbitrary 14-day booking rule. The prototype validates upcoming dates generally; actual booking advance-notice policies and honoraria terms will be decided by the artist.
- **Merchandise Materials & Sustainability:** Removed all unapproved claims regarding organic cotton, fair-trade certifications, plastic-free packaging, or carbon neutrality. Product descriptions are framed as representative demo placeholders subject to final owner manufacturing and sourcing decisions.
- **Inventory & Stock Counts:** Batch and inventory numbers are explicitly labeled as fictional demo placeholders.
- **Release Dates:** Fixed commercial release dates (e.g. 2026 releases) are marked as demo / to be announced.
- **Digital Download Entitlements:** Removed references to "perpetual" entitlements. Entitlement duration, re-download policies, and expiration rules are subject to final owner commercial terms.

---

## 9. Current Project Phase Status

- **Phase 1 (Application Foundation & Dev Environment):** Complete with evidence in [`docs/PHASE-1-EVIDENCE.md`](file:///c:/Users/autoc/Documents/princebyron/docs/PHASE-1-EVIDENCE.md).
- **Phase 2 (Design Review Prototype & Production Screens):** **Visual Direction Formally Approved by Owner (2026-11-17).** Production Blade/Alpine screens implemented across dedicated routes (`/`, `/about`, `/music`, `/store`, `/store/{slug}`, `/cart`, `/checkout`, `/booking`, `/account`, `/admin/preview`) with 27 green tests.
- **Phase 3 (Backend Foundation & Owner CMS):** Ready to commence. Real owner authentication, database persistence, and lifecycle transitions remain to be implemented.
