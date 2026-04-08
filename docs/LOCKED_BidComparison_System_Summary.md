# LOCKED SYSTEM SUMMARY — Bid Comparison & Match Score

**Status:** LOCKED as of 2026-04-08  
**Locked by:** Task #31 QA Pass + Anti-Regression Documentation  
**Reference:** `qa_reports/QA_LOCK_BidComparison_v1.md`

---

## WARNING

> **Do not change bid comparison scoring, zero-baseline display, or helper logic unless a specific, reproducible bug is found and confirmed.**
>
> All behavior described in this file is locked. Any change must be applied consistently across all four roles (Tenant, Buyer, Seller, Landlord) and must update the QA lock documentation.

---

## What Is Locked

### 1. Scoring Architecture

All match score calculations are performed exclusively by four PHP helper classes. No inline scoring logic is permitted in any Blade view.

| Role | Authoritative Helper |
|------|----------------------|
| Tenant | `app/Helpers/TenantBidMatchScoreHelper.php` |
| Buyer | `app/Helpers/BuyerBidMatchScoreHelper.php` |
| Seller | `app/Helpers/SellerBidMatchScoreHelper.php` |
| Landlord | `app/Helpers/LandlordBidMatchScoreHelper.php` |

### 2. Terms Maxima (Locked — Do Not Change)

| Role | Terms Max |
|------|-----------|
| Tenant | 16 |
| Buyer | 14 |
| Seller | 18 |
| Landlord | 17 |

### 3. Excluded Field (All Roles)

`additional_details_broker` is **never** counted in any helper, for any role, under any circumstance.

---

## Expected Mismatch Behavior

When a bid's field values differ from the listing's baseline values:

- The overall match score is reduced proportionally
- Mismatched fields are highlighted with a red "Mismatch" badge in the Private Data Modal
- The score is computed entirely by the helper — the view only renders what the helper returns

**Locked test cases:**

| Role | Bid / Auction | Expected Score | Mismatch Fields |
|------|---------------|----------------|-----------------|
| Buyer | Bid 19 / Auction 155 | ~95% (8/9 terms) | `commission_structure` |
| Tenant | Bid 40 / Auction 136 | ~93% (11/13 terms) | `agency_agreement_timeframe`, `brokerage_relationship` |

---

## Expected Zero-Baseline Behavior

When a listing has **no services configured AND no term fields configured** (both `services_baseline_total = 0` AND `terms_baseline_total = 0`):

- The match score panel is **hidden entirely**
- The text **"No match data available for this listing."** is displayed in its place
- A score of 0% or 100% is **never** shown for a true zero-baseline listing

**Canonical zero-baseline example:** Landlord auction 36 (no services, no term fields)

> Note: Landlord auction 45 was previously referenced as the zero-baseline example in Task #31. That data was restored and auction 45 now has non-zero terms. It is no longer a zero-baseline example. See `qa_reports/task31_bid_comparison_qa_report.md` — Addendum section.

---

## Views Protected by Anti-Regression Comments

The following view files contain `QA LOCK — BID COMPARISON SCORING` comment blocks at the top, preventing reintroduction of inline scoring:

- `resources/views/my-bids/agent-bids.blade.php`
- `resources/views/tenant_agent/bid_preview.blade.php`

The following view files contain `ZERO-BASELINE / NO-DATA GUARD` comment blocks at the `$hasAnyBaseline` definition, locking the no-data display behavior:

| File | Role |
|------|------|
| `resources/views/hire_tenant_agent/view.blade.php` | Tenant |
| `resources/views/hire_seller_agent/view.blade.php` | Seller |
| `resources/views/hire_landlord_agent/view.blade.php` | Landlord |
| `resources/views/buyerAgentAuctionDetail.blade.php` | Buyer |
| `resources/views/hire_tenant_agent/view_counter_terms.blade.php` | Tenant counter |
| `resources/views/hire_seller_agent/view_counter_terms.blade.php` | Seller counter |
| `resources/views/hire_landlord_agent/view_counter_terms.blade.php` | Landlord counter |
| `resources/views/hire_buyer_agent/view_counter_terms.blade.php` | Buyer counter |

---

## Dual Score Panel (Counter-Bid Views)

When a counter-bid exists, all four counter-bid views display a dual score panel:

- **Original Match** — bid vs. the listing owner's original listing request
- **Latest Counter Match** — bid vs. the listing owner's most recent counter

This pattern is standardized across all four roles as of Task #32.

---

## Files Changed to Reach This Locked State

| Task | Change |
|------|--------|
| Task #30 | Helper logic fixes across all four helpers |
| Task #31 | Legacy inline scoring removed from `agent-bids.blade.php` and `bid_preview.blade.php`; `$user_type` corrected in buyer and landlord counter views |
| Task #32 | Dual score panel standardized in Seller counter terms view |
| QA Lock pass | Zero-baseline guard added to all 8 view files; anti-regression comments added; QA lock documentation written |

---

## Rules for Future Changes

1. **Do not modify helper formulas** without a confirmed, reproducible bug report
2. **Do not add inline scoring** to any Blade view under any circumstances
3. **Do not change terms maxima** without updating all four helpers and the QA lock doc
4. **Any change must cover all four roles** — partial changes are not acceptable
5. **After any change**, re-verify the locked mismatch cases (Buyer bid 19, Tenant bid 40) and the zero-baseline case (Landlord auction 36) still behave as documented
6. **Update** `qa_reports/QA_LOCK_BidComparison_v1.md` and this file after any verified change
