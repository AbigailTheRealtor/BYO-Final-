# Listing View Parity Audit — Phase 1 Findings (Functional Gaps Only)

**Date:** 2026-05-31
**Auditor:** Agent Task #1623
**Status:** BASELINE LOCKED — no further changes without a new audit task

---

## Purpose

Audit of functional gaps across the four public offer-listing view pages (Seller, Buyer,
Landlord, Tenant) using Seller as the reference implementation. This document records
verified findings, distinguishes true functional gaps from intentional design choices,
cross-references already-planned work, and establishes a recommended implementation
order for follow-on tasks.

**No code changes are made by this audit.** It exists to lock in the baseline before
any implementation work begins.

---

## Out of Scope

- Color differences and brand palettes (teal vs blue)
- Icon choices
- CSS refactoring
- UX opinion items (button placement preferences)
- Any styling difference not affecting function

---

## Files Audited

| Role | View File |
|---|---|
| Seller (baseline) | `resources/views/offer-listing/seller/view.blade.php` |
| Buyer | `resources/views/offer-listing/buyer/view.blade.php` |
| Landlord | `resources/views/offer-listing/landlord/view.blade.php` |
| Tenant | `resources/views/offer-listing/tenant/view.blade.php` |

> **Note:** The Livewire files under `resources/views/livewire/offer-listing/*/` are the
> create/edit wizard components. The public-facing listing detail pages are conventional
> Blade templates rendered by the `*OfferListingController::view()` methods above.

---

## PASS / FAIL Matrix

| Feature | Seller | Buyer | Landlord | Tenant |
|---|---|---|---|---|
| Hero photo carousel (multi-photo) | PASS | FAIL | PASS | FAIL — single file only |
| Hero uses `property_photos` JSON array | PASS | N/A | PASS | FAIL — uses old `$str('photo')` meta key |
| Photos & Tours section card | PASS | FAIL | PASS | FAIL |
| Lightbox modal + prev/next nav | PASS | FAIL | PASS | FAIL |
| Thumbnail grid in gallery section | PASS | FAIL | PASS | FAIL |
| Video / virtual tour embed | PASS | FAIL | PASS | FAIL |
| Documents & Disclosures section | PASS | FAIL | FAIL | FAIL |
| Tax / Legal / HOA section | PASS | FAIL | FAIL | FAIL |
| Financial Details section | PASS | FAIL | FAIL | FAIL |
| Contact section CTA row (buttons) | PASS | PASS | PARTIAL — email-redirect only | FAIL — absent |
| "Ask a Question" form modal | PASS | PASS | FAIL | FAIL |
| "Schedule Showing" CTA | PASS | N/A — criteria listing, not a property | FAIL | FAIL |
| Sidebar: Schedule Showing button | PASS | N/A — criteria listing, not a property | FAIL | FAIL |
| Sidebar: Ask AI button | PASS | FAIL | FAIL | FAIL |
| Sidebar: Ask a Question button | PASS | PASS — already present | FAIL | FAIL |
| Sidebar primary CTA is engagement action | PASS | PARTIAL — Coming Soon modal | PARTIAL — placeholder modal | FAIL — primary is "Back to Search" |
| Mobile bar primary CTA highlighted | PASS | PASS | FAIL — no button highlighted | PARTIAL — highlights "Edit" (owner-only) |
| Mobile bar includes engagement CTA for non-owners | PASS | PASS | PASS | FAIL |
| Auction Time field in Overview section | PASS | PASS | PASS | FAIL — missing |
| BP timer fallback to model columns | FAIL | FAIL | FAIL | PASS — most robust |
| Interaction Hub (6-panel) | PASS | FAIL | FAIL | FAIL |
| AI Summary card | PASS | FAIL | FAIL | FAIL |
| Smooth-scroll with px offset (avoids sticky header) | PASS 82px | PASS 82px | FAIL — scrollIntoView() only | PASS 80px |
| Conditional nav tabs | FAIL — always shown | PASS | PASS | PASS |
| Leaked CSS namespace | clean | clean | clean | FAIL — `.sol-contact-cta-row` inside Tenant scope |

---

## Gap Classification

### True Functional Gaps — Scheduled in Phase 1A (task #1624)

These gaps break or degrade the visitor experience for non-owner users. All are
targeted in the immediately downstream implementation task.

| Gap ID | Description |
|---|---|
| GAP-4 | Tenant contact section has no CTA row at all + leaked `.sol-contact-cta-row` CSS class |
| GAP-5 | Landlord "Send Inquiry" opens an email-redirect modal (no form submission); missing Schedule Showing CTA entirely |
| GAP-6 | **Buyer sidebar:** "Ask a Question" already present; "Schedule Showing" is N/A (criteria listing, not a property); real gap is Ask AI button only. **Landlord sidebar:** missing both "Ask a Question" form modal and "Schedule Showing" button (a rental property showing is a valid action). |
| GAP-7 | Tenant sidebar: "Back to Search" is the primary CTA; no visitor engagement action present |
| GAP-8 | Landlord mobile bar: no button highlighted; Tenant mobile bar: highlights "Edit" for all visitors (not just owner) |
| GAP-9 | Landlord smooth-scroll uses bare `scrollIntoView()` with no pixel offset — content clips behind the sticky header |
| GAP-10 | Tenant Overview section is missing the Auction Time field shown in Seller/Buyer/Landlord |
| GAP-11 | BP countdown timer on Seller, Buyer, and Landlord lacks model-column fallback when expected EAV timer values are absent; Tenant already includes this fallback and serves as the reference implementation |

### Already Covered by Existing Planned Tasks (do not duplicate)

| Gap | Covered By |
|---|---|
| Photo gallery on public listing page — all roles | Tasks #250, #253 |
| Landlord photo upload infrastructure | Task #270 |
| Documents & Disclosures display section | Task #290 |
| Tax / Legal / HOA display section | Task #277 |
| Seller Financial Details display | Task #304 |
| Lease terms on landlord listing page | Task #159 |
| Lease terms on tenant listing page | Task #161 |

### Confirmed Intentional Design Choices (do not build)

| Item | Decision |
|---|---|
| Buyer has no fixed address — shows city/county ranges | Intentional — Buyer criteria listings express wants/needs, not properties |
| GAP-3: Buyer photo gallery / hero carousel | **NOT REQUIRED** — Buyer criteria listings are wants/needs, not properties; no photo gallery will be built |
| Buyer "Schedule Showing" CTA absent | **NOT REQUIRED** — A Buyer Criteria listing is not a property. "Schedule Showing" has no meaning here. The correct engagement action is "Ask a Question" (already present). |
| Interaction Hub absent from Buyer/Landlord/Tenant | **OUT OF SCOPE** — separate AI roadmap (Property DNA, Listing Intelligence, Ask AI) tracked in tasks #175–#180 |
| AI Summary card absent from Buyer/Landlord/Tenant | **OUT OF SCOPE** — role-specific AI rollout tracked separately (#175–#180) |
| Landlord/Tenant teal brand color vs Seller blue | Intentional — role-based branding system |
| Buyer/Landlord/Tenant use conditional nav tabs | Intentional — and superior to Seller's always-shown tabs; Seller should eventually adopt this pattern (P3) |
| Tenant catch-all Additional Information section | Intentional — useful safety net for edge cases |

---

## Recommended Implementation Order

### P1 — Phase 1A (broken visitor paths — scheduled, task #1624)
GAP-4, GAP-5, GAP-6, GAP-7, GAP-8, GAP-9, GAP-10, GAP-11

### P2 — Later phases (coordinate with existing tasks)
- Tenant and Landlord Photos & Tours section expansion (coordinate with tasks #250/#253)
- Documents & Disclosures display (tracked by task #290)
- Tax/Legal/HOA display (tracked by task #277)
- Financial Details display (tracked by task #304)

### P3 — Nice-to-have backports
- Seller: adopt conditional nav tabs pattern from Buyer/Landlord/Tenant
- Seller/Buyer/Landlord: adopt active tab scroll-highlighting from Tenant

---

## Notes

- The Tenant view is paradoxically the most robust on BP timer fallback (GAP-11) but
  the weakest on contact/engagement UX (GAP-4, GAP-7, GAP-8). The Tenant timer
  implementation (model-column fallback for `auction_type`, `auction_time`,
  `auction_length` when EAV meta is absent) is the reference implementation for the
  GAP-11 fix on Seller, Buyer, and Landlord. Fix order should prioritize
  contact/engagement (P1) since the timer gap is a silent internal failure.
- The `.sol-contact-cta-row` CSS class in the Tenant view is a namespace leak from the
  Seller/Owner listing scope (`sol-` prefix). It should be renamed when GAP-4 is fixed.
- Conditional nav tabs (Buyer/Landlord/Tenant) are architecturally superior to Seller's
  always-shown tabs. The P3 backport to Seller is low-urgency but worth tracking.
