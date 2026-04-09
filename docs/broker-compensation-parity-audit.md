# Broker Compensation & Services Parity Audit

**Date:** 2026-04-09  
**Scope:** All four roles (Tenant, Buyer, Seller, Landlord) across listing creation, agent bid, and agent counter bid forms — broker compensation fields, services sections, Livewire preload/save behavior, and match-score field alignment.  
**Source of Truth:** Listing creation blade for each role is the canonical reference. Bid and counter blades must match it.

---

## Summary

| Role | Surface | Area | Issue | Status |
|------|---------|------|-------|--------|
| Tenant | Bid blade | Lease Fee Flat Fee | Placeholder `5000` (no comma), no `data-error-id`, error span outside div — diverged from listing | **Fixed** |
| Tenant | Bid blade | Purchase Fee Flat Fee | Same divergence from listing creation | **Fixed** |
| Tenant | Counter bid | Lease Fee Flat Fee | `$/%` dual-select instead of `$`-only; placeholder/structure diverged from listing | **Fixed** |
| Tenant | Counter bid | Purchase Fee Flat Fee | Same `$/%` dual-select issue | **Fixed** |
| Tenant | Counter bid | Services fields | `$servicesConfig` in PHP component matches listing/bid service arrays exactly | No issue |
| Tenant | Counter bid | Livewire mount/save | All broker compensation fields preloaded and saved correctly | No issue |
| Buyer | All surfaces | Broker compensation | Internally consistent across listing, bid, and counter | No issue |
| Buyer | All surfaces | Services | Counter blade uses same hardcoded arrays as bid blade | No issue |
| Seller | Counter bid | All fields | Counter `@include`s listing blade — always in parity | No issue |
| Landlord | Counter bid | All fields | Counter `@include`s bid tab blades — always in parity | No issue |
| All roles | Match Score Helpers | Field key alignment | Helper field keys match keys saved by Livewire components | No issue |

---

## Source of Truth: Listing Creation Blade (Tenant)

`resources/views/livewire/tenant-agent-auction-tabs/commission-based/broker-compensation.blade.php`

Canonical Flat Fee input pattern (both Lease Fee and Purchase Fee):

```blade
<div class="input-group">
    <span class="input-group-text">$</span>
    <input type="text" wire:model.lazy="lease_fee_flat" class="form-control"
        placeholder="Enter flat fee amount (e.g., 5,000)"
        data-error-id="lease_fee_flat_error" oninput="formatWithCommas(this)" onblur="formatWithCommas(this)"
        onpaste="handlePaste(event)">
    <span class="error mt-2" id="lease_fee_flat_error"></span>
</div>
```

Key characteristics:
- `type="text"` (no `step="any"`)
- Placeholder: `"(e.g., 5,000)"` with comma
- `data-error-id` attribute on the input element
- Error span `<span class="error mt-2" id="..."></span>` inside the `.input-group` div

---

## Fixes Applied

### Fix 1 — Tenant Bid Blade: Lease Fee Flat Fee

**File:** `resources/views/livewire/tenant-agent-auction-bid-tabs/commission-based/broker-compensation.blade.php`

| Attribute | Before | After (matches listing) |
|-----------|--------|------------------------|
| `type="text"` with `step="any"` | `step="any"` present | Removed |
| Placeholder | `(e.g., 5000)` | `(e.g., 5,000)` |
| `data-error-id` | Missing | Added |
| Error span location | Outside `.input-group` div | Inside `.input-group` div |

### Fix 2 — Tenant Bid Blade: Purchase Fee Flat Fee

**File:** `resources/views/livewire/tenant-agent-auction-bid-tabs/commission-based/broker-compensation.blade.php`

Same four divergences corrected. Now matches listing creation pattern exactly.

### Fix 3 — Tenant Counter Blade: Lease Fee Flat Fee

**File:** `resources/views/livewire/tenant-agent-auction-bid-counter-tabs/broker-compensation.blade.php`

| Attribute | Before | After (matches listing) |
|-----------|--------|------------------------|
| Input type | `$/%` type-selector dropdown + dynamic input | `$`-only prefix + text input |
| `step="any"` | Present | Removed |
| Placeholder | `(e.g., 5000)` or dynamic | `(e.g., 5,000)` |
| `data-error-id` | Missing | Added |
| Error span location | Outside `.input-group` div | Inside `.input-group` div |

### Fix 4 — Tenant Counter Blade: Purchase Fee Flat Fee

**File:** `resources/views/livewire/tenant-agent-auction-bid-counter-tabs/broker-compensation.blade.php`

Same fixes: `$/%` dual-select replaced with `$`-only, all attributes aligned to listing creation pattern.

---

## Full Audit Detail

### Tenant

**Files audited:**
- Listing creation: `resources/views/livewire/tenant-agent-auction-tabs/commission-based/broker-compensation.blade.php`
- Agent bid: `resources/views/livewire/tenant-agent-auction-bid-tabs/commission-based/broker-compensation.blade.php`
- Agent counter bid: `resources/views/livewire/tenant-agent-auction-bid-counter-tabs/broker-compensation.blade.php`
- Agent bid services: `resources/views/livewire/tenant-agent-auction-bid-tabs/commission-based/services.blade.php`
- Agent counter services: `resources/views/livewire/tenant-agent-auction-bid-counter-tabs/services.blade.php`
- Counter component: `app/Http/Livewire/Tenant/TenantAgentAuctionBidCounter.php`
- Counter term component: `app/Http/Livewire/Tenant/TenantAgentAuctionCounterTerm.php`
- Match score helper: `app/Helpers/TenantBidMatchScoreHelper.php`

#### Broker Compensation — Listing vs Bid vs Counter Parity (post-fix)

| Field/Section | Listing | Bid | Counter | Match |
|--------------|---------|-----|---------|-------|
| Commission Structure dropdown | ✓ | ✓ | ✓ | ✅ |
| Lease Fee Type dropdown options | ✓ | ✓ | ✓ | ✅ |
| Lease Fee Flat Fee — `$`-only prefix | ✓ | Fixed ✓ | Fixed ✓ | ✅ |
| Lease Fee Flat Fee — placeholder `(e.g., 5,000)` | ✓ | Fixed ✓ | Fixed ✓ | ✅ |
| Lease Fee Flat Fee — `data-error-id` | ✓ | Fixed ✓ | Fixed ✓ | ✅ |
| Lease Fee Flat Fee — error span inside div | ✓ | Fixed ✓ | Fixed ✓ | ✅ |
| Lease Fee Percentage of Gross Lease Value | ✓ | ✓ | ✓ | ✅ |
| Lease Fee Flat + Gross combo | ✓ | ✓ | ✓ | ✅ |
| Lease Fee Percentage of Net Aggregate | ✓ | ✓ | ✓ | ✅ |
| Lease Fee Flat + Net combo | ✓ | ✓ | ✓ | ✅ |
| Lease Fee other | ✓ | ✓ | ✓ | ✅ |
| Payment Timing dropdown options | ✓ | ✓ | ✓ | ✅ |
| Purchase Fee — "Interested in Purchasing" toggle | ✓ | ✓ | ✓ | ✅ |
| Purchase Fee Type dropdown options | ✓ | ✓ | ✓ | ✅ |
| Purchase Fee Flat Fee — `$`-only prefix | ✓ | Fixed ✓ | Fixed ✓ | ✅ |
| Purchase Fee Flat Fee — placeholder `(e.g., 5,000)` | ✓ | Fixed ✓ | Fixed ✓ | ✅ |
| Purchase Fee Flat Fee — `data-error-id` | ✓ | Fixed ✓ | Fixed ✓ | ✅ |
| Purchase Fee Flat Fee — error span inside div | ✓ | Fixed ✓ | Fixed ✓ | ✅ |
| Purchase Fee Percentage | ✓ | ✓ | ✓ | ✅ |
| Purchase Fee Combo | ✓ | ✓ | ✓ | ✅ |
| Purchase Fee other | ✓ | ✓ | ✓ | ✅ |
| Lease-Option Agreement section | ✓ | ✓ | ✓ | ✅ |
| Protection Period, ETF, Retainer | ✓ | ✓ | ✓ | ✅ |
| Agency Agreement Timeframe | ✓ | ✓ | ✓ | ✅ |
| Brokerage Relationship | ✓ | ✓ | ✓ | ✅ |
| Additional Terms / `additional_details_broker` | ✓ | ✓ | ✓ | ✅ |

#### Tenant Services — Listing vs Bid vs Counter Parity

The counter blade (`tenant-agent-auction-bid-counter-tabs/services.blade.php`) renders services using `@foreach ($servicesConfig as $category)` where `$servicesConfig` is a computed property on `TenantAgentAuctionBidCounter.php`.

The bid blade (`tenant-agent-auction-bid-tabs/commission-based/services.blade.php`) uses hardcoded arrays directly in the template.

**Comparison of service strings (Residential, all 7 categories):**

Both surfaces render identical service checkbox strings for Residential and Commercial property types. The PHP component uses PHP Unicode escape `\u{2019}` (right single quotation mark) which renders identically to the curly apostrophe in the bid blade template. All category titles, section headers, and service option strings match exactly. ✅

- `wire:model="services"` (array) bound correctly on both surfaces ✅
- "Additional Services" / "Other" field: both surfaces have `other_services_enabled` checkbox + dynamic text inputs ✅

#### Livewire Preload (mount) Parity

`TenantAgentAuctionBidCounter::mount()` (lines 525–666 of `TenantAgentAuctionBidCounter.php`):
- Loads source data in priority order: Tenant's latest counter terms → listing → original bid
- Preloads all broker compensation fields including: `commission_structure`, `lease_fee_type`, `lease_fee_flat`, all combo/net/percentage variants, all purchase fee fields, lease-option fields, protection period, termination/retainer fees, agency timeframe, brokerage relationship, `additional_details_broker`, broker fee timing with legacy fallback (`payment_timing` → `broker_fee_timing`), and services array with catalog filtering

All fields that appear in the blade are preloaded. ✅

#### Livewire Save Parity

`TenantAgentAuctionBidCounter::saveAllMetaData()` (lines 781–910):
- Saves all broker compensation fields including `lease_fee_flat`, `purchase_fee_flat`, all combo/net/percentage variants, `lease_fee_other`, `purchase_fee_other`, lease-option fields, protection period, termination/retainer fees, agency timeframe, brokerage relationship, `additional_details_broker`, `broker_fee_timing` fields, services, and other_services

Note: `lease_fee_flat_type` and `purchase_fee_flat_type` are still declared in the component and saved (value `'$'`) — they became UI-unbound after the `$/%` selector was removed in the counter blade fix. The values remain `'$'` which is correct; cleanup is safe as a follow-up task. ✅

#### Match Score Helper — Field Key Alignment

`TenantBidMatchScoreHelper` uses the following field groups:
- `lease_fee_type` group: `lease_fee_type`, `lease_fee_flat`, `lease_fee_percentage`, `lease_fee_percentage_monthly_rent`, `lease_fee_percentage_monthly_number`, `lease_fee_flat_combo`, `lease_fee_percentage_combo`, `lease_fee_percentage_net`, `lease_fee_flat_combo_net`, `lease_fee_percentage_combo_net`, `lease_fee_other`
- `purchase_fee_type` group: `purchase_fee_type`, `purchase_fee_flat`, `purchase_fee_percentage`, `purchase_fee_flat_combo`, `purchase_fee_percentage_combo`, `purchase_fee_other`

All these keys match the `saveMeta()` calls in `TenantAgentAuctionBidCounter::saveAllMetaData()` and the `wire:model` names in the blade. ✅

`normalizeForMatch()` strips `$` and `%` characters to prevent formatting mismatches between values with/without currency symbols. ✅

---

### Buyer

**Files audited:**
- Listing creation: `resources/views/livewire/hire-buyer-agent/buyer-agent-auction-tabs/commission-based/broker-compensation.blade.php`
- Agent bid: `resources/views/livewire/buyer-agent-auction-bid-tabs/commission-based/broker-compensation.blade.php`
- Agent counter bid: `resources/views/livewire/buyer-agent-auction-bid-counter-tabs/broker-compensation.blade.php`
- Agent bid services: `resources/views/livewire/buyer-agent-auction-bid-tabs/commission-based/services.blade.php`
- Agent counter services: `resources/views/livewire/buyer-agent-auction-bid-counter-tabs/services.blade.php`
- Match score helper: `app/Helpers/BuyerBidMatchScoreHelper.php`

#### Broker Compensation Parity

All three Buyer surfaces use `(e.g., 5,000)` placeholder, `data-error-id` on inputs, and error spans inside input-group divs. The bid and counter blades use Alpine.js `x-data="moneyInput()"` and `x-on:input` handlers (vs listing creation's `validateInput`/`reformatNumber`), but the rendered HTML structure and all `wire:model` field names are consistent. Internally consistent across all three surfaces. ✅

| Field | Listing | Bid | Counter | Match |
|-------|---------|-----|---------|-------|
| `value="flat"` for Flat Fee in lease_fee_type | ✓ | ✓ | ✓ | ✅ |
| Purchase Fee Flat Fee — `$`-prefix, `data-error-id`, `(e.g., 5,000)` | ✓ | ✓ | ✓ | ✅ |
| Lease Fee Flat Fee — `$`-prefix, `data-error-id`, `(e.g., 5,000)` | ✓ | ✓ | ✓ | ✅ |
| Commission Structure options | ✓ | ✓ | ✓ | ✅ |
| Lease-Option, Protection Period, ETF, Retainer, Agency, Brokerage | ✓ | ✓ | ✓ | ✅ |

**Services:** Both Buyer bid and counter services blades use identical hardcoded arrays. ✅

#### Match Score Helper — Buyer

`BuyerBidMatchScoreHelper` field groups match `saveMeta()` keys in the Buyer counter component. ✅

**No changes required for Buyer.**

---

### Seller

**Files audited:**
- Listing creation: `resources/views/livewire/hire-seller-agent/seller-agent-auction-tabs/commission-based/broker-compensation.blade.php`
- Counter bid: `resources/views/livewire/seller-agent-auction-counter-tabs/broker-compensation.blade.php`
- Counter services: `resources/views/livewire/seller-agent-auction-counter-tabs/services.blade.php`

Both counter blades are single-line `@include` of the listing creation blade:
```blade
@include('livewire.hire-seller-agent.seller-agent-auction-tabs.commission-based.broker-compensation')
@include('livewire.hire-seller-agent.seller-agent-auction-tabs.commission-based.services')
```

Guaranteed to be in parity with listing creation — they are the same file. ✅

**No changes required for Seller.**

---

### Landlord

**Files audited:**
- Agent bid: `resources/views/livewire/landlord-agent-auction-bid-tabs/commission-based/broker-compensation.blade.php`
- Counter bid: `resources/views/livewire/landlord/landlord-agent-auction-bid-counter.blade.php`

Counter blade directly includes all bid tab blades:
```blade
@include('livewire.landlord-agent-auction-bid-tabs.commission-based.broker-compensation')
@include('livewire.landlord-agent-auction-bid-tabs.commission-based.additional-details')
@include('livewire.landlord-agent-auction-bid-tabs.commission-based.services')
```

Guaranteed to be in parity with the agent bid form — they are the same files. ✅

**No changes required for Landlord.**

---

## Match Score Helper Field Key Alignment Summary

| Role | Helper | Key Fields Used | Alignment |
|------|--------|----------------|-----------|
| Tenant | `TenantBidMatchScoreHelper` | `lease_fee_type` group, `purchase_fee_type` group | ✅ All keys match `saveMeta` calls |
| Buyer | `BuyerBidMatchScoreHelper` | `purchase_fee_type` group, `lease_fee_type` group | ✅ All keys match `saveMeta` calls |
| Seller | `SellerBidMatchScoreHelper` | `purchase_fee_type` group, `seller_leasing_fee_type` group | ✅ All keys match `saveMeta` calls |
| Landlord | `LandlordBidMatchScoreHelper` | `purchase_fee_type` group (used for primary leasing fee) | ✅ Internally consistent |

All helpers use `normalizeForMatch()` which strips `$` and `%` for format safety.

---

## Regression Checklist

After deploying fixes, verify the following for the Tenant role:

### Flat Fee Rendering
- [ ] Agent bid form: Lease Fee → select "Flat Fee" → renders `$` prefix input only (no `%` selector)
- [ ] Agent bid form: Purchase Fee → select "Flat Fee" → renders `$` prefix input only (no `%` selector)
- [ ] Agent counter bid: same as above for both sections
- [ ] Placeholder shows `(e.g., 5,000)` with comma in all four locations
- [ ] `data-error-id` attribute is present on the input in all four locations
- [ ] Error span appears visually below the field (inside the input-group border)

### Preload / Save Roundtrip
- [ ] Create a Tenant listing with Lease Fee type = Flat Fee, amount = 5,000
- [ ] Agent submits a bid, selects Flat Fee, enters amount — value is saved
- [ ] Agent opens the counter bid form — Flat Fee amount is pre-filled from the source data
- [ ] Submit counter bid — value is saved correctly without `$/%` type remnant

### Match Score
- [ ] `lease_fee_flat` and `purchase_fee_flat` values round-trip through match score helper without mismatch due to `$` or `,` formatting

---

## Recommendations

1. **Extract shared partials**: Consider extracting Tenant and Buyer broker compensation and services as shared partials (as Seller and Landlord do via `@include`) to eliminate future drift risk between listing, bid, and counter surfaces.
2. **Clean up orphaned props**: `TenantAgentAuctionBidCounter.php` retains `lease_fee_flat_type` and `purchase_fee_flat_type` properties (default `'$'`) — now UI-unbound after the fix. Safe to remove in a follow-up task.
3. **Landlord field naming**: `purchase_fee_type` is used for the primary leasing fee in Landlord components, which is semantically confusing. Consider renaming in a future refactor (requires DB migration and helper update).

---

## Task #53 — Counter Terms Display Fixes (2026-04-09)

**Scope:** Display-layer only — `view_counter_terms.blade.php` for all four roles. No submission logic, schema, or non-display files modified.

### Bugs Fixed

| File | Location | Bug | Fix Applied |
|------|----------|-----|-------------|
| `hire_buyer_agent/view_counter_terms.blade.php` | D) Legal Terms | `early_termination_fee_option` and `retainer_fee_option` rendered as raw `yes`/`no` | Wrapped with `ucfirst()`; sub-field `=== 'Yes'` guard changed to `strtolower() === 'yes'` |
| `hire_tenant_agent/view_counter_terms.blade.php` | D) Legal Terms | Same `yes`/`no` display issue for `early_termination_fee_option` and `retainer_fee_option` | Same fix applied |
| `hire_landlord_agent/view_counter_terms.blade.php` | E) Legal Terms | `early_termination_fee_option` rendered as raw `yes`/`no` | Wrapped with `ucfirst()`; sub-field guard changed to `strtolower() === 'yes'` |
| `hire_seller_agent/view_counter_terms.blade.php` | Services section | Photo enhancement sub-options (`photo_enhancements[]`, `custom_enhancement`) not shown under "Provide digital photo enhancements" | Added inline sub-list rendering after each matched service item, consistent with existing bid history display pattern |
| `hire_landlord_agent/view_counter_terms.blade.php` | Services section | Same photo enhancement sub-options missing | Same fix applied |
| `hire_seller_agent/view_counter_terms.blade.php` | A) Seller's Broker Compensation | `commission_structure_type` showed type label only (e.g., "Flat Fee") — no associated entered value | Added `$ctCommStructTypeDisplay` computation: composes full value string from associated sub-fields (`commission_structure_type_fee_flat`, `_fee_percentage`, `_fee_percentage_combo`, `_fee_flat_combo`, `_fee_other`) based on type; render target updated to `$ctCommStructTypeDisplay` |
| `hire_landlord_agent/view_counter_terms.blade.php` | D) Purchase Fee Details | `interested_in_selling_type` showed type label only — no associated entered value | Added `$ctSellingTypeDisplay` computation via `@php` block: composes full value from `landlord_broker_purchase_price`, `landlord_broker_percentage_price`, `landlord_broker_dollar_price`, `landlord_broker_flate_fee`, `landlord_broker_other` based on type; render target updated to `$ctSellingTypeDisplay` |

### Verified Not Broken

- Seller `early_termination_fee_option` already used `ucfirst()` — no change needed.
- Landlord `percentage_gross_lease` fee display uses `purchase_fee_rental_period` — this is correct per the Livewire component's property naming convention.
- Buyer and Tenant services sections do not include "Provide digital photo enhancements" in their service catalogs — no photo enhancement sub-option display needed there.
