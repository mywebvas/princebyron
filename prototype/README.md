# Prince Byron — Website & Store Prototype

A self-contained, responsive HTML/CSS/JavaScript prototype for princebyron.com. It is a visual and interaction brief for a later production build, not a live commerce, booking, authentication, streaming, or download service.

## Run locally

1. Open this folder.
2. Double-click index.html to preview it in a modern browser.
3. For a local web server, open PowerShell in this folder and run: py -m http.server 8000
4. Visit http://localhost:8000.

The pages are static and do not need an install step or build process. Google Fonts are optional; if the computer is offline, the site falls back to system serif and sans-serif fonts. The two artist photos are included under assets/images/.

## What's in the prototype

- Responsive artist home, dedicated About, Music, Store, product detail, Checkout and Booking pages, plus newsletter experiences.
- Five approved songs: Free, Purify Me, I Always Win, Toduyun, and Follow Me.
- Dedicated Music page with song filters, MP3/WAV demo selection, simulated player, mood playlists and digital demo bag.
- Track previews in a simulated player; MP3/WAV download and digital purchase affordances.
- Dedicated Store page with product filters, price sorting, colour and apparel size choices, adjustable bag quantities, and a link to the standalone USD checkout preview.
- Single product detail page reached from Store item links, with full sample details, colour/size selection, quantity control, related products and a current-page demo bag.
- Standalone Checkout page with contact, merchandise delivery and order review steps; it loads a labelled example cart when opened directly, while bag checkout passes the selected items. All payment choices are simulations.
- Dedicated three-step booking page with required-field checks, a future event date and a quote-by-request message.
- Dedicated Account page with a simulated fan sign-in and saved music/download/order preview.
- Separate Admin page with fictional sample metrics, booking enquiries and activity; it has no real controls or authentication.
- Official Spotify, YouTube, TikTok, Facebook and Instagram links.
- Visible prototype notices and explicit demo-only feedback around commerce, downloads, account, booking and newsletter actions.

## Prototype boundaries

Nothing here contacts a server. Carts stay in the current page session; checkout carries only item IDs and options in the navigation URL, never personal information. Checkout does not process payment or create an order. Account forms do not authenticate, create accounts, or persist credentials. Booking and newsletter forms do not send or store submissions. Track playback is a visual preview state; there are no audio files. MP3/WAV controls demonstrate interface intent only. Admin figures and activity are fictional examples.

The product names, product illustrations, track descriptions, release language, durations, sample item prices and discount amounts are prototype content. Confirm rights, merchandise specifications, inventory, shipping, taxes, music metadata, release dates and public-facing copy before a production launch.

## Project map

Prince-Byron-Website-Prototype/
  index.html
  about.html
  music.html
  store.html
  product.html
  booking.html
  checkout.html
  account.html
  admin.html
  css/styles.css
  css/catalog.css
  css/about.css
  css/music.css
  css/store.css
  css/product.css
  css/booking.css
  css/checkout.css
  css/account.css
  css/admin.css
  js/catalog.js
  js/app.js
  js/about.js
  js/music.js
  js/store.js
  js/product.js
  js/booking.js
  js/checkout-link.js
  js/checkout.js
  js/account.js
  js/admin.js
  assets/images/prince-byron-hero.jpeg
  assets/images/prince-byron-portrait.jpeg
  docs/PROTOTYPE-SPEC.md
  README.md

## Hand off to Antigravity or Google AI Studio

1. Copy the complete Prince-Byron-Website-Prototype folder into the development environment or upload it as a ZIP. Keep the relative file structure intact.
2. Include docs/PROTOTYPE-SPEC.md and this README as the product brief.
3. Ask the tool to first inspect the current files and summarize the implementation plan. Preserve the approved visual direction and the demo-only boundaries.
4. Ask it to implement production features as a separately scoped phase. Before enabling payments, account data, booking submissions, downloads or admin actions, define the required provider, server-side validation, permissions, privacy, operational monitoring and recovery behavior.
5. Compare the result with the static prototype at mobile and desktop sizes. Confirm the production work against the agreed commercial scope in writing before deployment.

Suggested handoff prompt:

Treat this folder as the approved, clickable frontend prototype for Prince Byron's website. Read README.md and docs/PROTOTYPE-SPEC.md first. Preserve the warm earthy palette, premium dark-hero/light-editorial-section direction, supplied unaltered artist photos, approved song titles, merchandise sample prices, USD market and quotation-based booking. Keep demo-only behavior visibly separate from production behavior. Before changing the stack or adding services, explain the smallest production architecture and identify which work is inside the separately agreed $750 production scope versus an additional requirement. Do not activate payment, authentication, email, downloads, DNS or production deployment without explicit authorization and configured credentials.

## Production scope reminder

The previously agreed $750 production scope remains separate from this prototype. The prior conversation recorded Option 1, one year of hosting and domain, and one month of free maintenance/updates. This frontend intentionally demonstrates customer and administrative surfaces more deeply than it implements them. Do not present its additional demo states as included production deliverables or use this prototype to silently change the price or contract.

## Accessibility and browser notes

The prototype uses semantic sections and controls, visible keyboard focus, form labels and native validation, Escape/backdrop dismissal for overlays, and reduced-motion CSS. Review with keyboard and assistive technology before production. Browser zoom, operating-system contrast settings and mobile devices should be checked in the target browsers before launch.
