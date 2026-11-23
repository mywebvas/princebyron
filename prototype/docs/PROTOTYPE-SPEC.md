# Prince Byron Prototype Specification

## Product goal

Give listeners, merchandise customers and event organisers a polished first destination for Prince Byron's music, story, store and ministry invitations. The prototype should feel contemporary, soulful, spiritual and internationally appealing without leaning on a generic black-and-gold gospel look.

## Approved direction

- Identity: Prince Byron; a blend of gospel genres.
- Brand feeling: premium, modern and spiritual in balance.
- Art direction: premium hybrid: cinematic dark hero followed by elegant light sections.
- Palette: warm clay, cocoa, sand, cream and olive, drawn from the supplied portraits.
- Photos: use the two user-supplied photos as received. Do not retouch or synthetically alter the artist's face.
- Domain: princebyron.com.
- Audience: US and international, with USD-only prototype pricing.
- Music titles: Free, Purify Me, I Always Win, Toduyun, Follow Me.
- Merchandise sample prices: T-shirt $32; hoodie $58; cap $30; tote $25; art print $25; sticker pack $6; tee-and-cap bundle $35; full bundle $80.
- Booking: legitimate appearance or ministry invitations, request a quote, no public booking fee, guided multi-step form.

## User flows and success signals

### Listener — understand the artist

Entry: the homepage's artist story or Our story navigation. Goal: understand Prince Byron's musical identity and the feeling behind the songs. Success: reads the faith-rooted artist introduction, discovers the range of gospel expression, and can continue to music or a booking enquiry. Content stays within the approved artist identity and supplied catalog; biographical facts not provided by the user are intentionally omitted.

### Listener — discover and preview music

Entry: home hero or Music navigation. Goal: understand the sound and preview a song. Success: reaches the five-song list, filters tracks, starts the simulated player, and can reach official listening profiles. Failure/recovery: empty filter shows a clear empty state; unavailable media is stated as a demo instead of implying audio is playing.

The dedicated Music page also offers a mock digital bag with sample MP3/WAV choices, demo-priced individual songs and collection bundle, and a no-payment checkout preview. No audio or download file is included, and no order or personal information is sent or stored.

### Customer — select merchandise or music

Entry: open the product page directly (defaults to the Everyday Faith Tee) or use a product's View details link in Store. Goal: inspect merchandise, choose variants and quantity, understand sample product details, and see related items before deciding. Home, Store and the single product page read from one shared sample catalog. Success: filters/sorts, selects size and colour, adjusts quantity, adds items, adjusts bag quantities, and opens USD demo checkout. Failure/recovery: an invalid explicit product ID shows a return path to Store; an omitted ID loads the default tee; apparel requires a selected size; quantity is clamped to 1–10; empty bag and checkout validation are clear; a persistent disclosure states that no payment is taken. The product page bag is limited to the current page session. Materials, measurements, stock, delivery, and return details remain unconfirmed and are labelled accordingly.

### Customer — review and complete demo checkout

Entry: open Checkout directly (shows a labelled sample cart) or continue from the merchandise or digital music bag. Goal: review the selected items, provide contact details, provide delivery details for physical merchandise, and complete the simulated order journey. Prices are recalculated from the local catalog; the URL carries only item IDs and selected options. Digital-only orders skip address entry. The final step offers simulated payment methods without collecting payment credentials. Shipping and taxes are labelled not calculated; the displayed subtotal is not an amount due. No contact details are transmitted or stored, and no order or payment is created. Empty or invalid cart data returns to Store/Music recovery links.

### Event organiser — request availability and a quote

Entry: Bookings navigation or artist call to action. Goal: complete a guided quote request for an appearance or ministry event. The dedicated Booking page collects event type, future date, attendance, location, optional details and contact information in three steps. Success: completes event type/date/attendance, location/requirements and contact steps. Failure/recovery: required fields are checked per step, date must be in the future, and prior steps remain reachable. Prototype submission confirms only the visual journey; no enquiry is transmitted.

### Fan — inspect an account experience

Entry: My account navigation. Goal: preview future fan tools. Success: reaches the dedicated Account page, sees a simulated sign-in form and saved music/download/order sections, and can open the separate Admin preview page. Failure/recovery: demo notices explain that no account or session is created and no credentials are sent or saved.

### Site administrator — review sample dashboard

Entry: Admin preview link from the Account page. Goal: understand the planned site-management surface. Success: sees fictional summary metrics, booking enquiries and store/music activity on a dedicated Admin page. The page has no authentication or real controls and is labelled as a public prototype; all records are sample data.

## Interaction and content limits

- Song previews are interface-only; no playback audio or licensed streams are included.
- MP3 and WAV purchase/download actions are illustrative only.
- Digital track and album prices are demonstration values, not locked commercial prices.
- Store visuals are CSS-drawn illustrative mockups rather than production SKU photography.
- Payment, shipping, tax, stock and fulfilment are not implemented. The checkout only previews cart review, contact and delivery fields.
- Booking, newsletter and account submissions do not call an API or persist information.
- Admin metrics and rows are fabricated sample data.
- Social buttons open the official artist profiles supplied by the user.

## Production boundary

The agreed $750 production engagement is a separate commercial scope. The prior discussion confirms Option 1, one year of hosting and domain and one month of free maintenance/updates. This prototype adds clickable demonstrations; it does not renegotiate, expand, or verify the production contract. Confirm the written deliverable list before treating any of the following as production commitments: payment gateway, secure accounts, licensed audio streaming, protected MP3/WAV delivery, merchandise logistics, transactional email, admin permissions, analytics, data protection, or booking operations.

## Red-team notes and safeguards for a real build

| Failure point | Prototype status | Production safeguard |
|---|---|---|
| A demo button is mistaken for payment or a completed order | UI displays prototype and checkout disclosures; no payment is invoked | Use a server-side payment provider, signed webhooks, idempotent order states and clear sandbox/production separation |
| Fake login implies real account protection | Account is an unconnected demonstration | Use a mature identity service, server-side authorization, secure session handling, recovery and rate limiting |
| Booking enquiry is lost or sent to the wrong recipient | No enquiry is transmitted | Validate server-side, persist an auditable enquiry, deliver via a monitored queue, and provide retry/acknowledgement |
| Digital audio is copied or sold without a real entitlement | No audio exists | Confirm rights and model purchase entitlements; use expiring protected delivery and audit access |
| Mobile shopping becomes difficult on a small screen | Layout adapts with a mobile menu and two-column merchandise grid | Exercise narrow screens, keyboard access, larger text, slow networks and real checkout provider behavior |
| Real stock, tax or delivery differs from sample totals | No inventory/shipping service exists | Make source of truth server-side and recalculate before order confirmation |
| Artist image or merchandise art lacks usage permission | Supplied artist photos are included; sample merchandise art is illustrative | Record image and artwork rights, product specifications and accessibility text before launch |

## Growth and scale

- Prototype: static assets and local interaction state; no vendors, account data or server costs.
- Initial production: add only the services required by the signed scope, with explicit demo/sandbox/production environment separation and monitored failure states.
- Growth: move music catalog, products, orders and booking workflow to authoritative managed services; add rate limits, backups, reconciliation, performance monitoring and ownership for customer support.
- Scale: keep media delivery/CDN separate from transactional data, queue email and booking side effects, make order/enquiry operations idempotent, and define rollback/recovery before releases.

## Evidence status

- Visual and interaction prototype: implemented in index.html, css/styles.css, and js/app.js.
- Production behavior, payment, authentication, data retention, provider integrations, deployed hosting and domain configuration: not implemented or verified.

