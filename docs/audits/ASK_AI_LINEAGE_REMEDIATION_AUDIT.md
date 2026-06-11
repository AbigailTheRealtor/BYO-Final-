# Ask AI Lineage Remediation Audit — Phase 1

**Date:** June 11, 2026
**Scope:** Full pipeline chain audit for all four roles (Seller, Buyer, Landlord, Tenant):
Form → Livewire → Save → DB/Meta → Context Builder → Registry → Routing → Ask AI

---

## Executive Summary

Phase 1 identified and remediated three lineage breaks in the Ask AI pipeline:

| Issue | Type | Roles Affected | Action |
|-------|------|----------------|--------|
| `listing.buy_now_price` not extracted | Missing extractor wire | Seller | **FIXED** — added to seller extractor |
| `listing.hoa_fee_requirement` phantom routing | No data source | All | **REMOVED** from LISTING_KEY_KEYWORD_MAP + response contract |
| `listing.condo_fee` / `condo_fee_schedule` in response contract | No data source | All | **REMOVED** from response contract allowed_context |

Additionally confirmed correct (no action needed):
- Landlord `number_occupant` key: `infoGet('number_occupant')` — CONFIRMED OK
- Tenant `number_of_occupants` key: `infoGet('number_of_occupants')` — CONFIRMED OK

---

## Field-by-Field Matrix

Legend:
- **Stored** — confirmed meta key or native column saved by current forms
- **Context key** — key produced by `extractFactualFields()` in context builder
- **Registry** — present in `AskAiFieldQuestionRegistryService::listingFieldRegistry()`
- **LISTING_KEY_KEYWORD_MAP** — present in `AskAiRunnerV2Service::LISTING_KEY_KEYWORD_MAP`
- **Response contract** — present in `AskAiResponseContractService` listing_facts allowed_context
- **Status** — PASS / FIXED / REMOVED / NO DATA / CONFIRMED OK

---

### 1. `listing.buy_now_price`

| Layer | Before Phase 1 | After Phase 1 |
|-------|---------------|---------------|
| Stored | `saveMeta('buy_now_price', ...)` in `SellerOfferListing` + `SellerOfferListingEdit` | (unchanged) |
| Context key (seller) | **ABSENT** — not in seller extractor | `'buy_now_price' => $infoGet('buy_now_price')` **ADDED** |
| LISTING_KEY_KEYWORD_MAP | `listing.buy_now_price` present (line 925) | (unchanged) |
| Response contract | `listing.buy_now_price` present | (unchanged) |
| Guard B routing | Would route but never fire (key absent from context) | Now fires correctly when null |
| Direct-return fallback | Would fail (key absent from context) | Now returns value directly |

**Root Cause:** The seller extractor (`extractFactualFields()`, seller arm) did not include a read for the `buy_now_price` EAV meta key, even though:
- `SellerOfferListing.php` saves it via `saveMeta('buy_now_price', ...)`
- `LISTING_KEY_KEYWORD_MAP` has an entry for `listing.buy_now_price`
- `AskAiResponseContractService` includes it in listing_facts allowed_context

**Fix:** Added `'buy_now_price' => $infoGet('buy_now_price')` to the seller arm of `extractFactualFields()` in `AskAiContextBuilderService.php`.

**Status:** ✅ FIXED

---

### 2. `listing.hoa_fee_requirement`

| Layer | Before Phase 1 | After Phase 1 |
|-------|---------------|---------------|
| Stored | **NONE** — no `saveMeta('hoa_fee_requirement', ...)` call found anywhere in current Livewire forms | (unchanged) |
| Native column | Present in legacy `property_auctions` and `buyer_criteria_auctions`; **NOT** in `seller_agent_auctions` or `buyer_agent_auctions` | (unchanged) |
| Context key (all roles) | Never produced | (unchanged — field omitted by design) |
| LISTING_KEY_KEYWORD_MAP | `listing.hoa_fee_requirement` present (was line 1111–1116) | **REMOVED** |
| Context labels map | `listing.hoa_fee_requirement` present (was line 2037) | **REMOVED** |
| Response contract | `listing.hoa_fee_requirement` present | **REMOVED** |

**Root Cause:** `hoa_fee_requirement` was a phantom key — it existed in the legacy `property_auctions` table but was never ported to the current `seller_agent_auctions` or `buyer_agent_auctions` tables. No current Livewire wizard form saves this field via `saveMeta()`. The key was registered in the routing map but had no data source, meaning:
- Questions like "is the HOA mandatory?" would route to `listing.hoa_fee_requirement`
- Guard B would never fire (key absent from context)
- OpenAI would be called with context that cannot answer the question

**Fix:** Removed from `LISTING_KEY_KEYWORD_MAP`, context label description map, and `AskAiResponseContractService` listing_facts `allowed_context`.

**Note:** HOA presence questions are still served by `listing.hoa_association` (seller: `has_hoa` meta key) and `listing.has_hoa` (landlord). HOA fee questions are served by `listing.hoa_fee` (seller: `association_fee_amount` meta key) and related keys.

**Status:** ✅ REMOVED (phantom — no remediation path, correct behavior is removal)

---

### 3. `listing.condo_fee` / `listing.condo_fee_schedule`

| Layer | Before Phase 1 | After Phase 1 |
|-------|---------------|---------------|
| Stored | `saveMeta("condo_fee", ...)` only in legacy `LandlordAuctionController` and `TenantCriteriaAuctionBidController` (old, non-Livewire controllers) | (unchanged — legacy only) |
| Context key (all roles) | Never produced | (unchanged — field omitted by design) |
| LISTING_KEY_KEYWORD_MAP | **Not present** (already clean) | (unchanged) |
| Response contract | `listing.condo_fee` and `listing.condo_fee_schedule` present | **REMOVED** |

**Root Cause:** `condo_fee` and `condo_fee_schedule` existed as native columns on `property_auctions` (legacy) and were added to the response contract allowed_context, but they have no data source in any current model (`seller_agent_auctions`, `buyer_agent_auctions`, `landlord_agent_auctions`, `tenant_agent_auction`). The only saves found are in old non-Livewire controllers that are no longer the active form path.

**Fix:** Removed both from `AskAiResponseContractService` listing_facts `allowed_context`. They were already absent from `LISTING_KEY_KEYWORD_MAP` so no routing change was needed.

**Status:** ✅ REMOVED (legacy-only data — no current write path)

---

### 4. Landlord `number_occupant` key alignment

| Layer | Finding |
|-------|---------|
| Form saves | `saveMeta('number_occupant', ...)` in `LandLordAgentAuction.php` and `LandLordAgentAuctionEdit.php` |
| Context builder reads | `infoGet('number_occupant')` → context key `number_of_occupants` |
| Alignment | **CONFIRMED OK** — meta key matches, context key normalized for prompt consistency |

**Status:** ✅ CONFIRMED OK — no change needed

---

### 5. Tenant `number_of_occupants` key alignment

| Layer | Finding |
|-------|---------|
| Form saves | `TenantAgentAuction.php` saves both `number_of_occupants` (household size/max) and `number_occupant` (current occupants, separate concept) via two distinct `saveMeta()` calls |
| Context builder reads | `infoGet('number_of_occupants')` → context key `number_of_occupants` |
| Alignment | **CONFIRMED OK** — extractor reads `number_of_occupants` which is the correct household size key for the tenant role |

**Status:** ✅ CONFIRMED OK — no change needed

---

## Regression Tests Added

All regression tests added to `tests/Unit/Services/AskAi/AskAiContextBuilderServiceTest.php`:

| Test Method | What It Verifies |
|-------------|-----------------|
| `test_case_W_seller_buy_now_price_reads_buy_now_price_meta` | Seller extractor returns `buy_now_price` from EAV meta key `buy_now_price` |
| `test_case_W_seller_hoa_fee_requirement_is_absent_from_context` | `hoa_fee_requirement` does not appear in seller context (phantom key removed) |
| `test_case_W_seller_condo_fee_is_absent_from_context` | `condo_fee` and `condo_fee_schedule` do not appear in seller context |

Tests updated in `test_case_U_seller_listing_context_contains_complete_factual_key_set`:
- `buy_now_price` moved from `$removedPhantomKeys` to `$expectedKeys`
- Stub meta now includes `'buy_now_price' => '399000'` so the key-set assertion can pass

Harness updated in `tests/Unit/Services/AskAi/AskAiApprovedFieldCoverageHarnessTest.php`:
- `hoa_fee_requirement` entry removed from `approvedFieldProvider()`
- Field count updated from 50 to 49 throughout docblock and data provider comment

---

## Files Changed

| File | Change |
|------|--------|
| `app/Services/AskAi/AskAiContextBuilderService.php` | Added `'buy_now_price' => $infoGet('buy_now_price')` to seller extractor |
| `app/Services/AskAi/AskAiRunnerV2Service.php` | Removed `listing.hoa_fee_requirement` from LISTING_KEY_KEYWORD_MAP and context label map |
| `app/Services/AskAi/AskAiResponseContractService.php` | Removed `listing.hoa_fee_requirement`, `listing.condo_fee`, `listing.condo_fee_schedule` from listing_facts allowed_context |
| `tests/Unit/Services/AskAi/AskAiContextBuilderServiceTest.php` | Updated Case U seller test; added Case W regression tests |
| `tests/Unit/Services/AskAi/AskAiApprovedFieldCoverageHarnessTest.php` | Removed `hoa_fee_requirement` from data provider; updated count to 49 |

---

## Remaining Considerations for Future Phases

1. **`listing.hoa_fee_requirement` — restoration path (future):** If a future form sprint adds `saveMeta('hoa_fee_requirement', ...)` to the seller or landlord offer listing wizard, the field can be re-added to the extractor and LISTING_KEY_KEYWORD_MAP at that time. The Guard B label text is preserved in this document for reference: `'HOA fee requirement information'`.

2. **`listing.condo_fee` — landlord rental context (future):** Legacy `LandlordAuctionController` saves `condo_fee` to landlord meta. If a Livewire landlord form sprint picks up this field, a corresponding extractor read should be added to the landlord arm of `extractFactualFields()` and the key should be re-added to the response contract.

3. **`AskAiFieldQuestionRegistryService` sweep (Phase 2):** The listing field registry (`listingFieldRegistry()`) should be swept to confirm it does not contain entries for the removed phantom keys. Low risk since the registry is FAQ-routing only and does not affect Guard B, but consistency is desirable.
