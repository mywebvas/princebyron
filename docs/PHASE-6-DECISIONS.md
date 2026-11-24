# Phase 6 — what needs a decision, and what does not

Phase 6 is marked *paused* in the roadmap, and it is paused for a reason: three of
its four items cannot be finished by writing code. This document separates what is
buildable from what needs the owner, so the difference is not discovered halfway
through a sprint.

---

## 1. Legal copy, tax, refund, privacy, shipping and music-download policies

**Status: infrastructure built, text needs you and your advisor.**

What exists now:

- Four pages at `/refund-policy`, `/privacy`, `/terms` and `/shipping`, linked from
  the footer of every page and from the checkout at the point of sale.
- Owner-editable in the admin panel under **Content Sections → page: "Legal / Policies"**.
- Each page shows your wording when written. Until then it shows a **template that
  says what the page must cover**, with a visible banner and a `noindex` so a search
  engine never publishes unfinished terms as your store's policy.
- `php artisan pb:readiness` lists any policy still on template text as a blocker.

What the code deliberately does **not** do: invent the policy. The templates name
the questions each page must answer — digital-goods cancellation rights, return
windows, data retention periods, governing law — and say plainly that a qualified
person must review the result. They do not guess answers, because a customer could
rely on a guess.

**You need to decide:**

1. **The legal jurisdiction** you sell from. This determines most of the refund and
   privacy wording, and cannot be inferred.
2. **Your business entity** (sole trader, registered company) and the contact
   address to publish on the Terms page.
3. **Digital-goods refund stance.** Many stores state downloads are non-refundable
   once accessed, because the product cannot be returned. Whether you can rely on
   that depends on where your customers are — in the EU and UK it requires express
   consent and acknowledgement at purchase. Your advisor needs to confirm which
   applies.
4. **Sales tax.** The schema has a `tax_cents` column defaulting to 0 and a comment
   saying calculation is pending advisor review. Nothing computes tax today. Selling
   physical goods across borders without it is a real exposure.
5. **Retention period for order records.** Tax authorities set a floor; you need to
   know yours.

---

## 2. Blog and newsletter

**Status: not started, and should not start yet.**

The roadmap's own condition is that these wait until "an email provider, consent
language, unsubscribe flow, retention and abuse controls are decided." That list is
the whole reason to wait. A newsletter signup without a documented consent basis and
an unsubscribe path is a compliance problem, and building the form first makes it
easy to ship without them.

**You need to decide:**

1. **Email provider.** The host's SMTP is fine for order receipts; it is not fine for
   bulk sending — shared hosts rate-limit and will suspend an account that trips it.
   The provider choice affects the unsubscribe mechanics and the data-processing
   terms you must disclose in the privacy policy.
2. **Consent model** — single opt-in or confirmed double opt-in. Double opt-in is
   the defensible choice and adds a step to the flow.
3. **Whether you want a blog at all.** It needs an editorial owner, not just a
   content type; an empty blog is worse for the site than no blog.

**What is buildable without those answers:** almost nothing useful. This is the one
item where waiting is the correct engineering decision.

---

## 3. PWA

**Status: decided, and the decision is documented in code.**

The site ships a web manifest with `display: browser` **on purpose**. It supplies the
home-screen icon, name and theme colour — which work without installability — while
not claiming to be an app. There is no service worker, so `display: standalone` would
promise a standalone launch that has nothing to serve offline.

The blocker is not effort. It is that a service worker caches responses, and this
site has account, checkout, download and webhook traffic that **must never** be
cached. Specifying which routes are cacheable, and proving the rest are not, is a
security exercise before it is a feature. `AssetIntegrityTest` currently fails if
someone sets `display: standalone` or adds a `sw.js`, so the claim cannot drift back.

**You need to decide:** whether offline access gives a customer anything real. For a
store, the honest answer is usually "no" — and that is a complete answer.

---

## 4. Owner approval before payment activation, DNS change, production release

**Status: this is a gate, not a task. It cannot be closed by anyone but you.**

Nothing in this repository should be read as approval to take real money. Payment
creation is not implemented at all (see README's limitations), refunds are recorded
rather than transmitted to the provider, and no live payment keys exist anywhere in
the tree.

---

## What is left that is buildable without a decision

| Item | Status |
|---|---|
| Legal page routes, templates, admin editing, readiness gate | **Done** |
| Footer and checkout policy links | **Done** |
| Manifest de-claimed from PWA to browser | **Done** |
| Tax calculation | Blocked on decision 4 |
| Newsletter | Blocked on decisions 1–3 |
| Blog | Blocked on decision 3 |
| Service worker | Blocked on a caching security spec |

Nothing in the "buildable" column remains open. Phase 6 is now genuinely waiting on
you, rather than on us.
