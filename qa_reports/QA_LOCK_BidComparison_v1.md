# QA LOCK — Bid Comparison Scoring v1

**Reference Task:** Task #31 — Browser QA, Bid Comparison UI all roles  
**Date Locked:** 2026-04-08  
**Status:** LOCKED — Do not modify scoring logic without updating this document

---

## Purpose

This document serves as the permanent QA baseline for bid match score
display across all four listing roles: Tenant, Buyer, Seller, Landlord.

Any change to scoring helpers, Blade views, or counter-bid logic that
affects match score output MUST be re-verified against the cases below
and this document updated.

---

## Scoring Rule — Authoritative

**All scoring must come from the four helper classes. No inline scoring
logic is permitted in any Blade view.**

| Role     | Helper Class                         |
|----------|--------------------------------------|
| Tenant   | `app/Helpers/TenantBidMatchScoreHelper.php`  |
| Buyer    | `app/Helpers/BuyerBidMatchScoreHelper.php`   |
| Seller   | `app/Helpers/SellerBidMatchScoreHelper.php`  |
| Landlord | `app/Helpers/LandlordBidMatchScoreHelper.php` |

**Rule:** No inline scoring logic is allowed in Blade views. All scoring
must come from the helper classes above. Any scoring change must be applied
across ALL four roles simultaneously.

**`additional_details_broker` is NEVER counted in any helper, for any role.**

---

## Locked Terms Maxima

| Role     | Terms Max |
|----------|-----------|
| Tenant   | 16        |
| Buyer    | 14        |
| Seller   | 18        |
| Landlord | 17        |

---

## Zero-Baseline Guard Rule

When **both** `terms_baseline_total = 0` AND `services_baseline_total = 0`,
the match score block is hidden and the text "No match data available for
this listing." is shown in its place.

This guard is implemented in all four bid detail views and all private
modal panels.

---

## Verified Cases Per Role

### Tenant

| Case | Bid ID | Auction ID | Expected Score | Key Fields |
|------|--------|------------|----------------|------------|
| Standard — full match | 3 | 3 (TAA-95LMYPW7) | 100% (6/6 terms, services match) | All terms align |
| Mismatch | 40 | 136 | ~93% (11/13 terms) | `agency_agreement_timeframe`: 12 Months (listing) → 3 Months (bid); `brokerage_relationship`: No Brokerage (listing) → Transaction Broker (bid) |
| Counter-bid | counter_bidding id=1 | Auction 9, Bid 13 | View renders correctly | Counter view already correct pre-Task #31 |

### Buyer

| Case | Bid ID | Auction ID | Expected Score | Key Fields |
|------|--------|------------|----------------|------------|
| Standard — full match | 4 | 146 (BAA-ZKWYFMWX) | 100% (9/9 terms, services match) | All terms align |
| Mismatch | 19 | 155 | ~95% (8/9 terms) | `commission_structure`: "Buyer Pays Out-of-Pocket" (listing) → "Requested From Seller in the Offer" (bid) |
| Counter-bid | buyer_counter_bidding id=1 | Auction 155, Bid 18 | View renders correctly | `$user_type` fixed to "buyer" in Task #31 |

### Seller

| Case | Bid ID | Auction ID | Expected Score | Key Fields |
|------|--------|------------|----------------|------------|
| Standard — full match | 4 | 69 (SAA-WYFDPFS6) | 100% (9/9 terms, services match) | All terms align |
| Mismatch | N/A | N/A | N/A | No mismatch records in DB at time of QA lock |
| Counter-bid | N/A | N/A | N/A | No records in seller_counter_bidding at time of QA lock |

### Landlord

| Case | Bid ID | Auction ID | Expected Score | Key Fields |
|------|--------|------------|----------------|------------|
| Standard — full match | 1 | 47 (LAA-ASNYUZPZ) | 100% (4/4 active terms match) | All terms align |
| Zero-baseline | N/A | 36 | "No match data available" | Auction 36 has no services and no term fields configured — the guard correctly suppresses the score panel and shows the no-data message |
| Counter-bid | N/A | N/A | N/A | No records in landlord_counter_bidding at time of QA lock |

> **Note — Auction 45 correction (2026-04-08):** The original Task #31 report referenced Landlord auction 45 as the zero-baseline example because its terms were blank at the time of that QA run (they had been temporarily cleared during a zero-baseline test). Auction 45's terms were subsequently restored. It is **no longer a zero-baseline example**. The correct zero-baseline reference is auction 36. This is not a bug — it is an expected state change after data restoration.

---

## Empty Baseline Behavior (Current — Post Zero-Baseline Guard)

When a listing has no services and no terms configured:

- **Old behavior (pre-guard):** 100% (denominator was 0, division resulted in 100%)
- **Current behavior (post-guard):** "No match data available for this listing." is displayed; no percentage shown

This guard was implemented in the zero-baseline QA pass (prior to Task #31)
and verified across all four roles.

---

## Files Where Inline Scoring Was Removed (Task #31)

The following files previously used legacy inline union-based scoring.
They were corrected in Task #31 and must not reintroduce inline logic:

1. `resources/views/my-bids/agent-bids.blade.php`
2. `resources/views/tenant_agent/bid_preview.blade.php`
3. `resources/views/livewire/buyer/buyer-agent-auction-bid-counter.blade.php` — `$user_type` fixed to `"buyer"`
4. `resources/views/livewire/landlord/landlord-agent-auction-bid-counter.blade.php` — `$user_type` fixed to `"landlord"`

---

## Anti-Regression Checklist

Before any merge touching scoring or bid views, verify:

- [ ] Buyer mismatch case (bid 19 / auction 155) shows reduced % — NOT 100%
- [ ] Tenant mismatch case (bid 40 / auction 136) shows ~93% — NOT 100%
- [ ] A listing with zero services AND zero terms shows "No match data available" — NOT 0% or a crash
- [ ] No Blade errors or undefined variable warnings on any bid detail page
- [ ] All four counter-bid views render without errors
- [ ] Dual score panel (Original Match + Latest Counter Match) displays correctly when a counter-back exists

---

## Change Log

| Date | Task | Change |
|------|------|--------|
| 2026-04-08 | Task #31 | Initial QA baseline locked. Legacy inline scoring removed from agent-bids.blade.php and bid_preview.blade.php. user_type corrected in buyer and landlord counter views. |
| 2026-04-08 | Task #32 | Seller counter terms view: dual score panel standardized to match Buyer/Landlord/Tenant pattern. |
| 2026-04-08 | Clarification | Landlord auction 45 no longer qualifies as zero-baseline example (terms restored). Auction 36 is the canonical zero-baseline reference. Addendum added to task31_bid_comparison_qa_report.md. |
