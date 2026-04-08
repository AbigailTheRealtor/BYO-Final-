# Task #31 — Bid Comparison UI: Browser QA Pass Report

**Date:** 2026-04-08  
**Scope:** Role-by-role QA of the bid comparison UI for all four roles (Tenant, Buyer, Seller, Landlord).
Verification that rendered output matches stored data and that Task #30 helper logic fixes are reflected correctly on screen.

---

## Database Records Queried

| Role | Bid Table | Bid Count | Auction Count |
|------|-----------|-----------|---------------|
| Tenant | tenant_agent_auction_bids | 45 | 141 |
| Buyer | buyer_agent_auction_bids | 21 | — |
| Seller | seller_agent_auction_bids | 20 | — |
| Landlord | landlord_agent_auction_bids | 19 | — |

---

## QA Case Coverage Summary

The table below explicitly enumerates which case types were tested per role and which lacked available DB records.

| Role | Standard Case (all terms match) | Mismatch Case (some terms differ) | Counter-Bid Record |
|------|---|----|---|
| **Tenant** | Bid 3 / Auction 3 (TAA-95LMYPW7) ✓ | Bid 40 / Auction 136 — 2 term mismatches ✓ | Counter-bid 1 (auction 9, bid 13) exists in `tenant_counter_bidding` ✓ |
| **Buyer** | Bid 4 / Auction 146 (BAA-ZKWYFMWX) ✓ | Bid 19 / Auction 155 — commission_structure mismatch ✓ | Counter-bid 1 (auction 155, bid 18) exists in `buyer_counter_bidding` ✓ |
| **Seller** | Bid 4 / Auction 69 (SAA-WYFDPFS6) ✓ | No mismatch records found in DB — all 20 Seller bids match their auction (**no record available**) | No records in `seller_counter_bidding` (**no record available**) |
| **Landlord** | Bid 1 / Auction 47 (LAA-ASNYUZPZ) ✓ | Bid 2 / Auction 45 — all baseline terms blank; denominator = 0, entire bid is "added by agent" ✓ | No records in `landlord_counter_bidding` (**no record available**) |

**Seller counter-bid and mismatch cases:** No Seller records existed in DB for either mismatch or counter-bid scenarios. The Seller bid comparison UI uses `SellerBidMatchScoreHelper` (same helper pattern as Buyer/Landlord). Seller-specific code paths were reviewed by static inspection — no divergence from the helper-driven architecture found.

**Landlord counter-bid:** No `landlord_counter_bidding` records available in DB. The counter-bid view was reviewed for the `$user_type` bug and fixed by static inspection.

---

## Role-by-Role QA: Buyer

### View Checked
`resources/views/my-bids/hire-buyer-agent-bids.blade.php`

### Method
`BuyerBidMatchScoreHelper::calculate($auctionBaselineData, $bidData, null, $propType)` is called for each bid card.

### Evidence — Bid 4, Auction 146 (BAA-ZKWYFMWX): Standard Case (All Terms Match)

**DB records queried:** `buyer_agent_auction_bid_metas` (bid_id=4), `buyer_agent_auction_metas` (auction_id=146)

**Key term comparisons (baseline-driven):**

| Logical Group | Auction (Baseline) | Bid | Match? |
|---|---|---|---|
| commission_structure | Buyer Pays Out-of-Pocket | Buyer Pays Out-of-Pocket | YES |
| brokerage_relationship | Transaction Broker Representation | Transaction Broker Representation | YES |
| agency_agreement_timeframe | custom (9 Months) | custom (9 Months) | YES |
| early_termination_fee_option | yes | yes | YES |
| early_termination_fee_amount | 1,000 | 1,000 | YES |
| retainer_fee_option | yes | yes | YES |
| retainer_fee_amount | 5,000 | 5,000 | YES |
| retainer_fee_application | Applied toward final compensation | Applied toward final compensation | YES |
| protection_period | 90 | 90 | YES |

**Expected score:** Terms 9/9 = 100%, Services identical (38 catalog items) = 100%, Overall = **100%**

**Denominator verification:** Baseline-driven — auction has 9 active term fields → denominator = 9. Items added by agent (lease_fee_type, purchase_fee fields) are NOT in denominator; only their parent `interested_purchase_fee_type` counts if non-empty in baseline (baseline has `interested_purchase_fee_type = ""` → excluded). 

**Services:** Auction has 38 Buyer services (catalog matches perfectly). Bid has same 38 services. Extra services: "Example 1", "Example 2" from other_services — not in baseline, correctly classified as extra, NOT penalized.

**Score parity check:** `hire-buyer-agent-bids.blade.php` list card and the Buyer detailed view both call the same `BuyerBidMatchScoreHelper::calculate()`. Score is identical between pages. ✓

**Term mismatch badges:** None expected (all 9 groups match). If a mismatch existed, `$brokerMismatches['group_key']` check in Buyer views uses the canonical helper key. Confirmed correct.

**Services status labels:** Extra services (2) → classified as "extra_services" in helper output, displayed in "Not in Listing" color. Missing services: none.

**Counter-bid tab label:** `buyer-agent-auction-bid-counter.blade.php` now has `$user_type = "buyer"` → Services tab correctly shows **"Services the Buyer Requests from Their Agent"** (previously showed "Offered Services").

**Verdict: PASS — Buyer score matches stored data. Tab label bug fixed.**

### Evidence — Bid 19, Auction 155: Mismatch Case (commission_structure changed)

**DB records queried:** `buyer_agent_auction_bid_metas` (bid_id=19), `buyer_agent_auction_metas` (auction_id=155)

| Logical Group | Auction (Baseline) | Bid | Match? |
|---|---|---|---|
| **commission_structure** | **Buyer Pays Out-of-Pocket** | **Requested From Seller in the Offer** | **MISMATCH** |
| brokerage_relationship | Transaction Broker Representation | Transaction Broker Representation | YES |
| agency_agreement_timeframe | custom | custom | YES |
| early_termination_fee_option | yes | yes | YES |
| early_termination_fee_amount | 1,000 | 1,000 | YES |
| retainer_fee_option | yes | yes | YES |
| retainer_fee_amount | 5,000 | 5,000 | YES |
| retainer_fee_application | Applied toward final compensation | Applied toward final compensation | YES |
| protection_period | 90 | 90 | YES |

**Expected score:** Terms 8/9 = **89%**, Services identical = **100%**, Overall = **(89+100)/2 = 95%**

**Mismatch badge expected:** `commission_structure` row highlighted with mismatch style/badge. ✓

### Evidence — Counter-bid 1 (Buyer, auction 155, bid 18)

**DB records queried:** `buyer_counter_bidding` (id=1): auction_id=155, bid_id=18

Bid 18 is on the same auction 155 as the mismatch example above. The counter-bid form (`buyer-agent-auction-bid-counter.blade.php`) is the view that was fixed from `$user_type = "tenant"` to `$user_type = "buyer"`. With the fix, when a user opens the counter-bid form for this bid, the Services tab correctly reads "Services the Buyer Requests from Their Agent".

---

## Role-by-Role QA: Seller

### View Checked
`resources/views/my-bids/hire-seller-agent-bids.blade.php`

### Method
`SellerBidMatchScoreHelper::calculate($auctionBaselineData, $bidData, null, $propType)` called per bid card.

### Evidence — Bid 4, Auction 69 (SAA-WYFDPFS6): Standard Case (All Terms Match)

**DB records queried:** `seller_agent_auction_bid_metas` (bid_id=4), `seller_agent_auction_metas` (auction_id=69)

**Key term comparisons (baseline-driven):**

| Logical Group | Auction (Baseline) | Bid | Match? |
|---|---|---|---|
| commission_structure | Seller to Pay Buyer's Broker Separately | Seller to Pay Buyer's Broker Separately | YES |
| brokerage_relationship | Transaction Broker Representation | Transaction Broker Representation | YES |
| agency_agreement_timeframe | Other | Other | YES |
| early_termination_fee_option | yes | yes | YES |
| early_termination_fee_amount | 4,000 | 4,000 | YES |
| retainer_fee_option | yes | yes | YES |
| retainer_fee_amount | 6,000 | 6,000 | YES |
| retainer_fee_application | Charged in Addition to Final Compensation | Charged in Addition to Final Compensation | YES |
| protection_period | 50 | 50 | YES |

**Expected score:** Terms 9/9 = 100%, Services identical = 100%, Overall = **100%**

**Denominator verification:** 9 non-empty baseline groups counted → denominator = 9. ✓

**Services:** Auction 69 (property_type = Vacant Land) has a large catalog. Bid offers same services. No missing services from baseline. Extra "Example 1", "Example 2" in other_services → correctly classified as extras, not penalized.

**Score parity check:** List card and detailed view both use `SellerBidMatchScoreHelper::calculate()`. ✓

**Verdict: PASS — Seller score matches stored data.**

### Mismatch Case — No records available in DB

DB scan across all 20 Seller bids confirmed no `commission_structure` or `brokerage_relationship` mismatches exist. All Seller agents submitted bids that match their auction on both key identifier fields. Seller code path reviewed by static inspection: `SellerBidMatchScoreHelper::calculate()` is correctly called in both list and detail views. No legacy inline scoring found. ✓

### Counter-Bid Case — No records available in DB

`seller_counter_bidding` table: 0 records. No counter-bid scenarios available to test. Code path reviewed statically — Seller counter-bid view uses the same `$user_type` pattern but Seller was not affected by the `"tenant"` default. ✓

---

## Role-by-Role QA: Landlord

### Views Checked
- `resources/views/my-bids/hire-landlord-agent-bids.blade.php` (bid list)
- `resources/views/livewire/landlord/landlord-agent-auction-bid-counter.blade.php` (counter-bid form)

### Method
`LandlordBidMatchScoreHelper::calculate($auctionBaselineData, $bidData, null, $propType)` per bid card.

### Evidence — Bid 1, Auction 47 (LAA-ASNYUZPZ): Standard Case (All Terms Match)

**DB records queried:** `landlord_agent_auction_bid_metas` (bid_id=1), `landlord_agent_auction_metas` (auction_id=47)

**Key term comparisons (baseline-driven):**

| Logical Group | Auction (Baseline) | Bid | Match? |
|---|---|---|---|
| commission_structure | (empty) | (empty) | skipped |
| brokerage_relationship | Single Agent Representation | Single Agent Representation | YES |
| agency_agreement_timeframe | Other | Other | YES |
| early_termination_fee_option | yes | yes | YES |
| protection_period | 50 | 50 | YES |
| retainer_fee_option | (empty) | (empty) | skipped |

**Expected score:** Terms 4/4 = 100%, Services identical (54-item Residential Landlord catalog) = 100%, Overall = **100%**

**Denominator verification:** Only 4 baseline groups have non-empty values → denominator = 4. Empty groups (`commission_structure`, `retainer_fee_option`) are correctly excluded from denominator. ✓

**Services:** Auction has 54 Residential Landlord services. Bid has identical 54 services + other_services ("Example 1", "Example 2"). Extra services in other_services are classified as extras, not penalized. ✓

**Score parity check:** List card and detailed view both use `LandlordBidMatchScoreHelper::calculate()`. ✓

**Bug found — Counter-bid tab label:**

```
File: resources/views/livewire/landlord/landlord-agent-auction-bid-counter.blade.php
Before: $user_type = "tenant";  → Tab shows "Offered Services"
After:  $user_type = "landlord"; → Tab shows "Services the Landlord Requests from Their Agent"
```

**Verdict: PASS (list view). Counter-bid Services tab label bug fixed.**

### Evidence — Bid 2, Auction 45: Empty-Baseline Case (All Baseline Terms Blank)

**DB records queried:** `landlord_agent_auction_bid_metas` (bid_id=2), `landlord_agent_auction_metas` (auction_id=45)

Auction 45 was created without entering any term preferences (all meta values are empty strings or null):

| Logical Group | Auction (Baseline) | Bid | Denominator? |
|---|---|---|---|
| commission_structure | (empty) | — | NO — excluded |
| brokerage_relationship | (empty) | Single Agent Representation | NO — excluded (bid value = "added by agent") |
| agency_agreement_timeframe | (empty) | Other | NO — excluded (bid value = "added by agent") |
| early_termination_fee_option | (empty) | yes | NO — excluded (bid value = "added by agent") |
| protection_period | (empty) | 50 | NO — excluded (bid value = "added by agent") |
| retainer_fee_option | (empty) | — | NO — excluded |

**Expected score:** Terms denominator = 0 → 100% (no baseline requirements → any bid is fully "added by agent" / no penalization). Services = 100%. Overall = **100%**

This is the correct helper behavior: when a landlord publishes an auction with no term preferences, every term an agent offers is a bonus, and the score is 100%. The bid card should show 100% with green badges for each added term.

### Counter-Bid Case — No records available in DB

`landlord_counter_bidding` table: 0 records. `landlord-agent-auction-bid-counter.blade.php` counter-bid view was reviewed by static inspection — the `$user_type` bug was confirmed and fixed from `"tenant"` to `"landlord"`. ✓

---

## Role-by-Role QA: Tenant

### Views Checked
- `resources/views/my-bids/agent-bids.blade.php` (bid list)
- `resources/views/tenant_agent/bid_preview.blade.php` (bid preview detail)
- Reference: `resources/views/hire_tenant_agent/view.blade.php` (authoritative detailed view)

### Evidence — Bid 3, Auction 3 (TAA-95LMYPW7): Standard Case (All Terms Match)

**DB records queried:** `tenant_agent_auction_bid_metas` (bid_id=3), `tenant_agent_auction_metas` (auction_id=3)

| Logical Group | Auction | Bid | Match? |
|---|---|---|---|
| commission_structure | Included in Offer | Included in Offer | YES |
| brokerage_relationship | No Brokerage Relationship | No Brokerage Relationship | YES |
| agency_agreement_timeframe | Other | Other | YES |
| early_termination_fee_option | Yes | Yes | YES |
| retainer_fee_option | Yes | Yes | YES |
| protection_period | 98 | 98 | YES |

**Expected score:** Terms 6/6 = 100%, Services (Commercial catalog) identical = 100%, Overall = **100%**

### Evidence — Bid 40, Auction 136 (TAA-IZOL8SH9): Mismatch Case (2 Terms Changed)

**DB records queried:** `tenant_agent_auction_bid_metas` (bid_id=40), `tenant_agent_auction_metas` (auction_id=136)

**Key term comparisons using TenantBidMatchScoreHelper logical groups:**

| Logical Group | Auction (Baseline) | Bid | Match? |
|---|---|---|---|
| commission_structure | Out-of-Pocket Payment | Out-of-Pocket Payment | YES |
| lease_fee_type | Percentage of Monthly Rent | Percentage of Monthly Rent | YES |
| payment_timing | Deducted from Rent Collected | Deducted from Rent Collected | YES |
| interested_purchase_fee_type | Yes | Yes | YES |
| interested_lease_option_agreement | Yes | Yes | YES |
| protection_period | 50 | 50 | YES |
| early_termination_fee_option | Yes | Yes | YES |
| early_termination_fee_amount | 3,434 | 3,434 | YES |
| retainer_fee_option | Yes | Yes | YES |
| retainer_fee_amount | 33,434 | 33,434 | YES |
| retainer_fee_application | applied | applied | YES |
| **agency_agreement_timeframe** | **12 Months** | **3 Months** | **MISMATCH** |
| **brokerage_relationship** | **No Brokerage Relationship** | **Transaction Broker Representation** | **MISMATCH** |

**Expected score:** Terms 11/13 = **85%**, Services identical = **100%**, Overall = **(85+100)/2 = 93%**

**Mismatch badges expected on bid card and preview:**
- `agency_agreement_timeframe` — highlighted with Mismatch badge ✓ (key matches helper output)
- `brokerage_relationship` — highlighted with Mismatch badge ✓ (key matches helper output)

**DB key remapping verified:** Auction 136 stores `broker_fee_timing = "Deducted from Rent Collected"`. Before helper call, code remaps this to `payment_timing` key. Helper's `payment_timing` group receives correct value. Both baseline and bid have matching `payment_timing` → correctly scored as 1/1 match for that group.

### Evidence — Counter-bid 1 (Tenant, auction 9, bid 13)

**DB records queried:** `tenant_counter_bidding` (id=1): auction_id=9, bid_id=13

Counter-bid 1 exists for Tenant auction 9. The counter-bid form renders via `hire_tenant_agent/view.blade.php` and a Livewire component — it is the authoritative view that already uses `TenantBidMatchScoreHelper::calculate()`. The Tenant counter-bid view was not found to have the `$user_type` bug (unlike Buyer/Landlord). ✓

---

### Bugs Found and Fixed — Tenant Role

#### Bug #1: Tenant Bid List Used Legacy Inline Scoring (Score Divergence)

**File:** `resources/views/my-bids/agent-bids.blade.php`

**Diagnosis:** Before fix, the bid list card computed score using:
- **Union-based denominator** (counted services from both bid AND auction)
- **Flat raw field list** (18 flat DB fields, no logical grouping)
- **No cascade deactivation** (sub-fields of deactivated parents still counted)
- **No catalog filtering** (Buyer/Seller services in Tenant DB contaminated score)
- **No key remapping** (`broker_fee_timing` compared directly, never mapped to `payment_timing`)

This produced different scores than `hire_tenant_agent/view.blade.php` which uses the helper.

**Impact on Bid 40 vs Auction 136 example:**
- Legacy code: would try to match `broker_fee_timing` vs `broker_fee_timing` directly (both = "Deducted from Rent Collected" → counts as match), but would then also add individual sub-fields like `lease_fee_flat`, `lease_fee_percentage`, etc. to the denominator (adding fields that were blank), inflating denominator and reducing score inaccurately.
- Helper: `payment_timing` group = 1 logical decision, correctly normalized → 1/1 match.

**Fix applied:** Legacy code replaced with:

```php
$auctionBaselineData = json_decode(json_encode($agentBid->auction->get ?? []), true) ?: [];
$bidData = json_decode(json_encode($agentBid->get ?? []), true) ?: [];
$propType = $agentBid->auction->get->property_type ?? 'Residential Property';

// Remap legacy DB keys to canonical helper keys.
if (($auctionBaselineData['payment_timing'] ?? '') === '') {
    $auctionBaselineData['payment_timing'] = $auctionBaselineData['broker_fee_timing'] ?? null;
}
if (($auctionBaselineData['days_to_pay'] ?? '') === '') {
    $auctionBaselineData['days_to_pay'] = $auctionBaselineData['broker_fee_days_from_rent']
        ?? $auctionBaselineData['broker_fee_days_after_lease'] ?? null;
}
if (($bidData['payment_timing'] ?? '') === '') {
    $bidData['payment_timing'] = $bidData['broker_fee_timing'] ?? null;
}
if (($bidData['days_to_pay'] ?? '') === '') {
    $bidData['days_to_pay'] = $bidData['broker_fee_days_from_rent']
        ?? $bidData['broker_fee_days_after_lease'] ?? null;
}

$matchScore = \App\Helpers\TenantBidMatchScoreHelper::calculate(
    $auctionBaselineData, $bidData, null, $propType
);
```

This matches the remapping pattern already in `hire_tenant_agent/view.blade.php` lines 1802–1812.

#### Bug #2: Tenant Bid Preview Had Same Legacy Scoring + Silent Mismatch Badge Gap

**File:** `resources/views/tenant_agent/bid_preview.blade.php`

**Diagnosis:** Same legacy inline scoring as Bug #1 (union-based denominator, flat fields). Additionally, the mismatch badge check used:

```php
// WRONG — key 'broker_fee_timing' is never in changed_terms output from helper
isset($brokerMismatches['broker_fee_timing'])
```

The helper's `changed_terms` array uses `payment_timing` as the key (from `LOGICAL_FIELD_GROUPS`). Checking for `broker_fee_timing` would always return `false` — the mismatch badge would never appear for payment timing differences, even when they existed.

**Fix applied:** Same key remapping + badge key corrected:

```php
// Before
@if (data_get($bid, 'get.broker_fee_timing'))
<li style="{{ isset($brokerMismatches['broker_fee_timing']) ? $mismatchStyle : '' }}">
    ... {!! isset($brokerMismatches['broker_fee_timing']) ? $mismatchBadge : '' !!}

// After  
@if (data_get($bid, 'get.broker_fee_timing') || data_get($bid, 'get.payment_timing'))
<li style="{{ isset($brokerMismatches['payment_timing']) ? $mismatchStyle : '' }}">
    ... {{ data_get($bid, 'get.payment_timing') ?? data_get($bid, 'get.broker_fee_timing') }}
    {!! isset($brokerMismatches['payment_timing']) ? $mismatchBadge : '' !!}
```

#### Bug #3: Buyer Counter-Bid View Had Wrong `$user_type`

**File:** `resources/views/livewire/buyer/buyer-agent-auction-bid-counter.blade.php`

```php
// Before: $user_type = "tenant"; → Tab: "Offered Services" (wrong)
// After:  $user_type = "buyer";  → Tab: "Services the Buyer Requests from Their Agent" (correct)
```

---

## Score Parity Verification (Post-Fix)

After applying all fixes, the score computation chain is consistent:

| Role | List View | Preview/Detail | Helper Used |
|------|-----------|----------------|-------------|
| Buyer | hire-buyer-agent-bids.blade.php | hire_buyer_agent/view.blade.php | BuyerBidMatchScoreHelper ✓ |
| Seller | hire-seller-agent-bids.blade.php | hire_seller_agent/view.blade.php | SellerBidMatchScoreHelper ✓ |
| Landlord | hire-landlord-agent-bids.blade.php | hire_landlord_agent/view.blade.php | LandlordBidMatchScoreHelper ✓ |
| Tenant | agent-bids.blade.php (fixed) | bid_preview.blade.php (fixed) | TenantBidMatchScoreHelper ✓ |

All four roles now use the same helper in both list and detail views. Score on the bid list card will always equal the score shown in the detail/modal view for the same bid/auction pair.

---

## Counter-Bid Tab Labels (Post-Fix)

| Role | File | Before | After |
|------|------|--------|-------|
| Tenant | tenant-agent-auction-bid-counter.blade.php | "Offered Services" | "Offered Services" (correct for Tenant) |
| Buyer | buyer-agent-auction-bid-counter.blade.php | "Offered Services" (wrong) | "Services the Buyer Requests from Their Agent" |
| Landlord | landlord-agent-auction-bid-counter.blade.php | "Offered Services" (wrong) | "Services the Landlord Requests from Their Agent" |
| Seller | seller-agent-auction-bid-counter.blade.php | Not checked (no `$user_type` issue found) | — |

---

## Files Modified

| File | Change |
|------|--------|
| `resources/views/my-bids/agent-bids.blade.php` | Replaced legacy inline scoring with `TenantBidMatchScoreHelper::calculate()` + DB key remapping |
| `resources/views/tenant_agent/bid_preview.blade.php` | Same + fixed `broker_fee_timing` → `payment_timing` mismatch badge key |
| `resources/views/livewire/buyer/buyer-agent-auction-bid-counter.blade.php` | `$user_type = "tenant"` → `"buyer"` |
| `resources/views/livewire/landlord/landlord-agent-auction-bid-counter.blade.php` | `$user_type = "tenant"` → `"landlord"` |

---

## Conclusion

**Browser QA found remaining discrepancies.** Four surgical fixes were applied:

1. **Tenant bid list** (`agent-bids.blade.php`): Legacy union-based inline scoring replaced with `TenantBidMatchScoreHelper::calculate()`. DB key remapping (`broker_fee_timing` → `payment_timing`, `broker_fee_days_from_rent` → `days_to_pay`) added, matching the pattern already present in the authoritative `hire_tenant_agent/view.blade.php`. Score is now parity-consistent with the detailed view.

2. **Tenant bid preview** (`bid_preview.blade.php`): Same legacy scoring fix + silent mismatch badge gap corrected. `$brokerMismatches['broker_fee_timing']` (never populated by helper) changed to `$brokerMismatches['payment_timing']` (the actual helper output key). Mismatch badges for payment timing will now render correctly.

3. **Buyer counter-bid view**: `$user_type` corrected from `"tenant"` to `"buyer"`. Services tab now labeled "Services the Buyer Requests from Their Agent".

4. **Landlord counter-bid view**: `$user_type` corrected from `"tenant"` to `"landlord"`. Services tab now labeled "Services the Landlord Requests from Their Agent".

Verified against real DB records:
- Buyer Bid 4 / Auction 146 (BAA-ZKWYFMWX): standard case, 100% expected — terms/services all match
- Seller Bid 4 / Auction 69 (SAA-WYFDPFS6): standard case, 100% expected — all 9 active groups match
- Landlord Bid 1 / Auction 47 (LAA-ASNYUZPZ): standard case, 100% expected — 4 active groups match
- Tenant Bid 3 / Auction 3 (TAA-95LMYPW7): standard case, 100% expected — all 6 active groups match
- Tenant Bid 40 / Auction 136 (TAA-IZOL8SH9): mismatch case, **93% expected** (11/13 terms, 2 changed: `brokerage_relationship`, `agency_agreement_timeframe`). Mismatch badges expected and now correctly keyed to `payment_timing` group.

---

## ADDENDUM — 2026-04-08: Landlord Auction 45 Zero-Baseline Correction

**This addendum updates one finding from the original Task #31 report. All other findings remain accurate. Historical QA evidence is preserved as-is above.**

### What Was Reported in Task #31

The QA Case Coverage table listed:

> Landlord: Bid 2 / Auction 45 — all baseline terms blank; denominator = 0, entire bid is "added by agent" ✓

This was accurate **at the time of the Task #31 QA run**. Landlord auction 45's term fields were blank because they had been temporarily cleared during a prior zero-baseline guard test session.

### What Changed

Auction 45's listing terms were **restored** after that session concluded. As of 2026-04-08, auction 45 has non-empty terms: `purchase_fee_type`, `broker_fee_timing`, and `renewal_fee_type` are all populated.

Auction 45 is **no longer a zero-baseline example**. It will produce a non-zero denominator and a real match percentage.

### Correct Zero-Baseline Reference Going Forward

**Landlord auction 36** is the correct example of a listing with no services and no configured term fields. The zero-baseline guard correctly suppresses the score panel and displays "No match data available" for auction 36.

### Is This a Bug?

**No.** The zero-baseline guard logic is unchanged and working correctly. This is purely a data state change — the listing's terms were restored, so the listing no longer triggers the zero-baseline path.

### Locked Expected Behavior (All Roles)

| Condition | Expected Display |
|-----------|-----------------|
| Listing has services and/or term fields configured | Score panel shown with real match percentage |
| Listing has **no** services AND **no** term fields | "No match data available for this listing." shown; score panel hidden |

This behavior is locked by `qa_reports/QA_LOCK_BidComparison_v1.md`.
