# Offer Entry Point Audit

**Date:** 2026-06-05  
**Scope:** All public-facing buttons and links that should enter the Offer workflow across the four role listing view pages.  
**Purpose:** Catalogue every offer CTA, identify placeholder/legacy wiring, and recommend fix tasks with risk levels.  
**Out of scope:** Code changes to views, routes, or controllers.

---

## Summary Table

| # | Role | Location | Button / Link Text | Status | Connected to OfferController | Risk |
|---|------|----------|--------------------|--------|------------------------------|------|
| 1 | Seller | Hero bar (`view.blade.php` ~L981) | "Submit Offer" | Placeholder modal | No | High |
| 2 | Seller | Interaction hub card (~L1028–1032) | "Submit Offer" | Placeholder modal | No | High |
| 3 | Seller | Sticky sidebar (~L2256) | "Submit Offer" | Placeholder modal | No | High |
| 4 | Seller | Mobile bottom bar (~L2501) | "Submit Offer" | Placeholder modal | No | High |
| 5 | Seller | `#solOfferModal` body (~L2312–2331) | Modal content | Placeholder — "Coming Soon" message, no form | No | High |
| 6 | Buyer | Hero bar (~L658) | "Respond to Buyer Criteria" | Placeholder modal | No | High |
| 7 | Buyer | Interaction hub card (~L683–686) | "Respond" | Placeholder modal | No | High |
| 8 | Buyer | Sticky sidebar (~L1469) | "Respond to Buyer Criteria" | Placeholder modal | No | High |
| 9 | Buyer | Mobile bottom bar (~L1785) | "Respond to Buyer Criteria" | Placeholder modal | No | High |
| 10 | Buyer | `#bolRespondModal` body (~L1571–1590) | Modal content | Placeholder — "Coming Soon" message, no form | No | High |
| 11 | Landlord | Entire page | *(no offer CTA exists)* | Gap — no offer entry point | No | High |
| 12 | Landlord | Interaction hub (~L628, 631) | "QR Code" / "Embed" (share utility) | Coming Soon `<span>` elements | n/a | Low |
| 13 | Tenant | Entire page | *(no offer CTA exists)* | Gap — no offer entry point | No | High |
| 14 | Tenant | `#tclShowingModal` (~L1753–1762) | "Schedule a Showing" modal form | Wiring Pending — `form action="#"`, route `offer.listing.tenant.showing` does not exist | No | Medium |
| 15 | Tenant | Interaction hub (~L758, 761) | "QR Code" / "Embed" (share utility) | Coming Soon `<span>` elements | n/a | Low |

---

## OfferController Route Inventory

Routes registered in `routes/web.php` (auth-gated group, lines ~1085–1090) that **none of the four listing view pages currently reference**:

| Route Name | Method | URI | Notes |
|------------|--------|-----|-------|
| `offers.store` | POST | `/offers/store` | Creates a draft Offer record; auth-gated |
| `offers.submit` | POST | `/offers/{offer}/submit` | Advances draft → submitted; auth-gated |
| `offers.accept` | POST | `/offers/{offer}/accept` | auth-gated |
| `offers.reject` | POST | `/offers/{offer}/reject` | auth-gated |
| `offers.withdraw` | POST | `/offers/{offer}/withdraw` | auth-gated |
| `offers.counter` | POST | `/offers/{offer}/counter` | auth-gated |
| `offers.show` | GET | `/offers/{offer}` | Public; used only in `resources/views/offers/index.blade.php` "View Offer" link — not referenced from any listing view |

`offers.submit` is referenced in `resources/views/offers/show.blade.php` as a PHP string key in the `$actionButtons` array (the available-actions card), but it is **not wired from any of the four listing view pages**.

---

## Detailed Findings by Role

---

### 1. Seller Listing — `resources/views/offer-listing/seller/view.blade.php`

#### Finding S-1 — Hero bar "Submit Offer" button
- **Line:** ~981
- **Element:** `<button type="button" … data-sol-modal="#solOfferModal">`  
  Note: uses a custom `data-sol-modal` attribute (not Bootstrap's `data-bs-target`). A JS listener on the page translates this to a Bootstrap modal open. Net effect is identical to Bootstrap `data-bs-target`.
- **Button text:** "Submit Offer"
- **Current target:** Opens `#solOfferModal` (placeholder modal)
- **Connected to OfferController:** No
- **Status:** Placeholder
- **Risk:** High

#### Finding S-2 — Interaction hub card "Submit Offer" button
- **Line:** ~1028–1032
- **Element:** `<button … data-bs-toggle="modal" data-bs-target="#solOfferModal">`
- **Button text:** "Submit Offer"
- **Current target:** Opens `#solOfferModal` (placeholder modal)
- **Connected to OfferController:** No
- **Status:** Placeholder
- **Risk:** High

#### Finding S-3 — Sticky desktop sidebar "Submit Offer" button
- **Line:** ~2256
- **Element:** `<button class="sol-action-btn sol-action-primary" data-bs-toggle="modal" data-bs-target="#solOfferModal">`
- **Button text:** "Submit Offer"
- **Current target:** Opens `#solOfferModal` (placeholder modal)
- **Connected to OfferController:** No
- **Status:** Placeholder
- **Risk:** High

#### Finding S-4 — Mobile bottom bar "Submit Offer" button
- **Line:** ~2501
- **Element:** `<button class="sol-mobile-bar-btn sol-mobile-bar-offer" data-bs-toggle="modal" data-bs-target="#solOfferModal">`
- **Button text:** "Submit Offer" (implied by `.sol-mobile-bar-offer` styling)
- **Current target:** Opens `#solOfferModal` (placeholder modal)
- **Connected to OfferController:** No
- **Status:** Placeholder
- **Risk:** High

#### Finding S-5 — `#solOfferModal` modal body
- **Lines:** ~2312–2331
- **Comment in source:** `{{-- Modal: Submit Offer (placeholder) --}}`
- **Displayed message:** "Secure online offer submission is coming soon. In the meantime, please use the contact details in the listing to reach the agent directly."
- **Form action / route:** None — modal body contains only a "Coming Soon" badge and a "Close" button; no `<form>` element
- **Connected to OfferController:** No
- **Status:** Placeholder
- **Risk:** High

**Recommended fix (Seller):** Replace all four buttons' `data-bs-target` values and the `#solOfferModal` content with a real offer-entry flow. The `offers.store` route (POST `/offers/store`) and `offers.show` route (GET `/offers/{offer}`) are both available. The fix task should wire these four entry points to POST `offers.store` (passing `offer_auction_id` and `role=seller`), then redirect to `offers.show`.

---

### 2. Buyer Criteria Listing — `resources/views/offer-listing/buyer/view.blade.php`

#### Finding B-1 — Hero bar "Respond to Buyer Criteria" button
- **Line:** ~658
- **Element:** `<button … data-bs-toggle="modal" data-bs-target="#bolRespondModal">`
- **Button text:** "Respond to Buyer Criteria"
- **Current target:** Opens `#bolRespondModal` (placeholder modal)
- **Connected to OfferController:** No
- **Status:** Placeholder
- **Risk:** High

#### Finding B-2 — Interaction hub card "Respond" button
- **Line:** ~683–686
- **Element:** `<button … data-bs-toggle="modal" data-bs-target="#bolRespondModal">`
- **Button text:** "Respond"
- **Current target:** Opens `#bolRespondModal` (placeholder modal)
- **Connected to OfferController:** No
- **Status:** Placeholder
- **Risk:** High

#### Finding B-3 — Sticky desktop sidebar "Respond to Buyer Criteria" button
- **Line:** ~1469
- **Element:** `<button class="bol-action-btn bol-action-primary" data-bs-toggle="modal" data-bs-target="#bolRespondModal">`
- **Button text:** "Respond to Buyer Criteria"
- **Current target:** Opens `#bolRespondModal` (placeholder modal)
- **Connected to OfferController:** No
- **Status:** Placeholder
- **Risk:** High

#### Finding B-4 — Mobile bottom bar "Respond to Buyer Criteria" button
- **Line:** ~1785
- **Element:** `<button class="bol-mobile-bar-btn bol-mobile-bar-respond" data-bs-toggle="modal" data-bs-target="#bolRespondModal">`
- **Button text:** "Respond to Buyer Criteria" (implied by `.bol-mobile-bar-respond` styling)
- **Current target:** Opens `#bolRespondModal` (placeholder modal)
- **Connected to OfferController:** No
- **Status:** Placeholder
- **Risk:** High

#### Finding B-5 — `#bolRespondModal` modal body
- **Lines:** ~1571–1590
- **Comment in source:** `{{-- Modal: Respond to Buyer Criteria (placeholder) --}}`
- **Displayed message:** "Online response submission for Buyer Criteria listings is coming soon. In the meantime, please use the contact details in the listing to reach the listing owner directly."
- **Form action / route:** None — no `<form>` element; only a "Coming Soon" badge and a "Close" button
- **Connected to OfferController:** No
- **Status:** Placeholder
- **Risk:** High

**Recommended fix (Buyer):** Identical pattern to Seller. Replace `#bolRespondModal` with a real form posting to `offers.store` with `role=buyer`, then redirect to `offers.show`. All four entry-point buttons need their target updated.

---

### 3. Landlord Listing — `resources/views/offer-listing/landlord/view.blade.php`

#### Finding L-1 — No offer submission entry point exists (gap)
- **Location:** Entire page
- **Primary CTA buttons present:** "Schedule Showing" (`#lolShowingModal` — wired to real route `offer.listing.landlord.showing`), "Ask a Question" (`#lolQuestionModal` — wired to real route `offer.listing.landlord.question`)
- **Interaction hub panel 1:** "Schedule Showing" — real route wired
- **Sticky sidebar:** "Ask a Question" (primary), "Schedule Showing" — both wired
- **Mobile bottom bar:** "Ask a Question" (primary highlight), "Schedule Showing" — both wired
- **Missing:** There is no "Submit Offer", "Apply to Rent", "Respond", or equivalent offer-submission CTA anywhere on the page. The hub's activity row at line ~653 shows `"Offers/Bids" → "Coming Soon"` but this is inside an `@if(false)` block and is never rendered.
- **Connected to OfferController:** No
- **Status:** Gap (entry point absent entirely)
- **Risk:** High

#### Finding L-2 — "QR Code" and "Embed" utility spans (Coming Soon)
- **Lines:** ~628, 631
- **Element:** `<span class="lol-interaction-cta lol-interaction-cta-muted" aria-label="QR Code — coming soon">` and `aria-label="Embed widget — coming soon">`
- **Nature:** Share-utility features, not offer entry points. These are muted, non-clickable `<span>` elements. They are not offer-submission gaps.
- **Connected to OfferController:** No (not applicable)
- **Status:** Coming Soon UI (utility feature)
- **Risk:** Low

**Recommended fix (Landlord):** Add a new primary CTA — "Submit Application" or "Submit Offer" — to the hero bar, interaction hub, sticky sidebar, and mobile bar. Wire it to `offers.store` with `role=landlord`. This requires creating the CTA in four locations, which parallels the Seller fix task.

---

### 4. Tenant Criteria Listing — `resources/views/offer-listing/tenant/view.blade.php`

#### Finding T-1 — No offer submission entry point exists (gap)
- **Location:** Entire page
- **Primary CTA buttons present:** "Ask a Question" (`#tclQuestionModal` — wired to real route `offer.listing.tenant.question`), "Schedule Showing" (`#tclShowingModal` — UI-only, see T-2), email link
- **Missing:** There is no "Respond to Tenant Criteria", "Submit Offer", or equivalent offer-submission CTA anywhere on the page. The hub's activity row at line ~783 shows `"Offers/Bids" → "Coming Soon"` but this is rendered inside dead code (visually shown but behind `@if(false)` equivalent structure).
- **Connected to OfferController:** No
- **Status:** Gap (entry point absent entirely)
- **Risk:** High

#### Finding T-2 — "Schedule a Showing" modal form — wiring pending
- **Line:** ~1753
- **Comment in source:** `{{-- Modal: Schedule a Showing (UI only — route offer.listing.tenant.showing does not exist. Wiring pending.) --}}`
- **Inner comment:** `{{-- Route offer.listing.tenant.showing does not exist. Wiring pending. --}}`
- **Element:** `<form action="#" method="POST">` — form submits to `#` (no-op); the route `offer.listing.tenant.showing` is not registered in `routes/web.php`
- **Nature:** Adjacent wiring gap, not an offer entry point per se. For comparison: the Landlord showing modal is correctly wired to `offer.listing.landlord.showing`.
- **Connected to OfferController:** No (not applicable — this is a showing/scheduling modal, not an offer submission)
- **Status:** Wiring Pending
- **Risk:** Medium

#### Finding T-3 — "QR Code" and "Embed" utility spans (Coming Soon)
- **Lines:** ~758, 761
- **Element:** `<span class="tcl-interaction-cta tcl-interaction-cta-muted" aria-label="QR Code — coming soon">` and `aria-label="Embed widget — coming soon">`
- **Nature:** Share-utility features, not offer entry points. Non-clickable `<span>` elements.
- **Connected to OfferController:** No (not applicable)
- **Status:** Coming Soon UI (utility feature)
- **Risk:** Low

**Recommended fix (Tenant):** Two separate tasks: (1) Add a new primary "Respond to Tenant Criteria" CTA to hero bar, interaction hub, sticky sidebar, and mobile bar, wired to `offers.store` with `role=tenant`. (2) Register the missing route `offer.listing.tenant.showing` and update the `tclShowingModal` form action.

---

## Recommendations by Priority

### Priority 1 — High: Wire existing placeholder modals (Seller & Buyer)
All eight High-risk placeholder findings (S-1 through S-5, B-1 through B-5) share the same fix pattern. For each role:
1. Delete the placeholder `#solOfferModal` / `#bolRespondModal` modal.
2. Replace each of the four button targets with either: (a) a direct link to a new Offer creation page or (b) a lightweight modal that POSTs to `offers.store` with the appropriate `offer_auction_id` and `role`, then redirects to `offers.show`.
3. No new controller methods are needed — `offers.store` and `offers.show` are already registered and functional.

### Priority 2 — High: Add missing offer CTAs (Landlord & Tenant)
Landlord (L-1) and Tenant (T-1) have zero offer entry points. Each requires adding CTAs in four UI locations (hero bar, interaction hub, sticky sidebar, mobile bar), following the same four-button pattern already established on the Seller and Buyer pages. The `offers.store` route already accepts the `role` field.

### Priority 3 — Medium: Fix Tenant showing modal route (T-2)
Register the missing `offer.listing.tenant.showing` route in `routes/web.php` (analogous to the existing `offer.listing.landlord.showing` route), implement the controller action in `TenantOfferListingController`, and update the `tclShowingModal` form action from `#` to `route('offer.listing.tenant.showing', ...)`.

### Priority 4 — Low: QR Code / Embed utility features (L-2, T-3, and analogous spans on Seller/Buyer)
Backlog items; no offer-submission impact. These should be addressed when the share/embed feature is scheduled, independently of the offer workflow.

---

## File Reference

| File | Role |
|------|------|
| `resources/views/offer-listing/seller/view.blade.php` | Seller listing view |
| `resources/views/offer-listing/buyer/view.blade.php` | Buyer criteria view |
| `resources/views/offer-listing/landlord/view.blade.php` | Landlord listing view |
| `resources/views/offer-listing/tenant/view.blade.php` | Tenant criteria view |
| `app/Http/Controllers/OfferController.php` | Offer workflow controller (all routes exist) |
| `routes/web.php` | Route definitions (lines ~300, ~1085–1090 for offer routes) |
| `resources/views/offers/show.blade.php` | Offer detail page (references `offers.submit` in available-actions card) |
| `resources/views/offers/index.blade.php` | My Offers index (references `offers.show` per-row link) |
