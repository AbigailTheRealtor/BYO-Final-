# Ask AI Wiring Gap Audit — Buyer & Tenant Fields
**Date:** June 19, 2026
**Scope:** Audit only — no code changes made.

---

## Methodology

For each field:
1. Searched all blade files under `resources/views/livewire/offer-listing/offer-buyer-tabs/` and `offer-tenant-tabs/` for any `wire:model` binding.
2. Traced the `saveMeta()` call in `BuyerOfferListing.php`, `BuyerOfferListingEdit.php`, `TenantOfferListing.php`, and `TenantOfferListingEdit.php`.
3. Cross-referenced the save key with `CANONICAL_SOURCE_MAP` in `AskAiContextBuilderService.php` (buyer block lines 259–315, tenant block lines 496–581).

Verdict options: **Working correctly**, **Wrong save key**, **Wrong context mapping**, **Field not rendered**.

---

## Buyer Fields

### 1. `monthly_income`

| Dimension | Finding |
|---|---|
| Blade file | None — not found in any file under `offer-buyer-tabs/` |
| `wire:model` binding | None |
| Livewire property | `public $monthly_income = ''` (BuyerOfferListing.php line 219, BuyerOfferListingEdit.php line 226) |
| `saveMeta()` key | `'monthly_income'` — confirmed in both components |
| CANONICAL_SOURCE_MAP | `'monthly_income' => 'monthly_income'` |
| Key alignment | ✅ saveMeta key matches CANONICAL_SOURCE_MAP |

**Verdict: Field not rendered.**

The Livewire property exists and the `saveMeta`→`CANONICAL_SOURCE_MAP` chain is correctly aligned end-to-end. The gap is entirely in the presentation layer: no form input in any of the six buyer wizard tabs (listing-details, property-preferences, purchasing-terms, buyer-info, broker-compensation, additional-details) renders this field. The EAV key is therefore always empty.

---

### 2. `year_built_preference`

| Dimension | Finding |
|---|---|
| Blade file | None — not found in any file under `offer-buyer-tabs/` |
| `wire:model` binding | None |
| Livewire property | **Does not exist** — no `$year_built` or `$year_built_preference` property declared in either buyer component |
| `saveMeta()` key | **None** — no `saveMeta('year_built', ...)` call exists anywhere in the buyer Livewire components |
| CANONICAL_SOURCE_MAP | `'year_built_preference' => 'year_built'` |
| Key alignment | ❌ CANONICAL_SOURCE_MAP reads EAV key `year_built`, which is never written |

**Verdict: Field not rendered.**

This is the most severe gap of the five. Unlike `monthly_income` and `number_of_occupants`, there is no Livewire property, no `saveMeta` call, and no blade input — the feature does not exist at any layer of the buyer wizard. The CANONICAL_SOURCE_MAP entry (`'year_built'`) references an EAV key the buyer form has never populated. Context will always resolve to `null` for this field regardless of what a buyer fills in elsewhere.

---

### 3. `number_of_occupants`

| Dimension | Finding |
|---|---|
| Blade file | None — not found in any file under `offer-buyer-tabs/` |
| `wire:model` binding | None |
| Livewire property | `public $number_occupant = ''` (BuyerOfferListing.php line 220, BuyerOfferListingEdit.php line 227) |
| `saveMeta()` key | `'number_occupant'` — confirmed in both components |
| CANONICAL_SOURCE_MAP | `'number_of_occupants' => 'number_occupant'` |
| Key alignment | ✅ saveMeta key matches CANONICAL_SOURCE_MAP |

**Verdict: Field not rendered.**

Same pattern as `monthly_income`: the Livewire property and the saveMeta→CANONICAL_SOURCE_MAP chain are internally consistent, but no blade input exists to accept this value from the user. The EAV key is always empty.

---

## Tenant Fields

### 4. `bathrooms`

| Dimension | Finding |
|---|---|
| Blade file | `offer-tenant-tabs/commission-based/property-details.blade.php` |
| Tab name | "Property Preferences" |
| `wire:model` binding | `wire:model="bathrooms"` at line 420; `wire:model="other_bathrooms"` at line 434 |
| Visibility condition | Rendered unconditionally (not inside a `@if ($property_type ...)` guard within the outer `wire:key` container). The "Other" input at line 432 is conditionally hidden when `$bathrooms !== 'Other'` via CSS class. |
| Livewire property | `public $bathrooms = ''` (TenantOfferListing.php line 87; TenantOfferListingEdit.php line 88) |
| `saveMeta()` key | `'bathrooms'` (TenantOfferListing.php line 4279; TenantOfferListingEdit.php line 3209) |
| CANONICAL_SOURCE_MAP | `'bathrooms' => ['bathrooms', 'other_bathrooms']` |
| Key alignment | ✅ First cascade key (`bathrooms`) matches saveMeta key; second cascade key (`other_bathrooms`) matches the `other_bathrooms` property saved elsewhere |

**Verdict: Working correctly.**

The full chain — blade input → wire:model → Livewire property → saveMeta → CANONICAL_SOURCE_MAP → context assembly — is intact and correctly aligned. The 0% coverage observed in the Phase 3 audit is attributable to test data not having this field populated, not any wiring defect.

---

### 5. `pet_information`

| Dimension | Finding |
|---|---|
| Blade file | None — not found in any file under `offer-tenant-tabs/` |
| `wire:model` binding | None |
| Livewire property | `public $pet_information = ''` (TenantOfferListing.php line 588; TenantOfferListingEdit.php also declares it) |
| `saveMeta()` key | `'pet_information'` (TenantOfferListing.php line 4782; TenantOfferListingEdit.php line 3729) |
| CANONICAL_SOURCE_MAP | `'pet_information' => 'pet_information'` |
| Key alignment | ✅ saveMeta key matches CANONICAL_SOURCE_MAP |
| Related fields rendered | `pets`, `number_of_pets`, `type_of_pets`, `breed_of_pets`, `weight_of_pets` are rendered in the tenant wizard — but these are separate EAV keys, not the composite `pet_information` key the context builder reads |

**Verdict: Field not rendered.**

The Livewire property exists and the `saveMeta`→`CANONICAL_SOURCE_MAP` chain is correctly aligned. The gap is that no form input collects a value for the `pet_information` EAV key. The tenant wizard does collect individual pet detail fields (`pets`, `type_of_pets`, `breed_of_pets`, etc.) via blade inputs, but none of those save under the `pet_information` key that Ask AI reads. The key is therefore always empty, even for tenants who fill in pet details.

---

## Summary Table

| Field | Role | Blade rendered? | saveMeta key | CANONICAL_SOURCE_MAP key | Key match? | Verdict |
|---|---|---|---|---|---|---|
| `monthly_income` | Buyer | ❌ No | `monthly_income` | `monthly_income` | ✅ | Field not rendered |
| `year_built_preference` | Buyer | ❌ No | *(none)* | `year_built` | ❌ | Field not rendered |
| `number_of_occupants` | Buyer | ❌ No | `number_occupant` | `number_occupant` | ✅ | Field not rendered |
| `bathrooms` | Tenant | ✅ Yes | `bathrooms` | `bathrooms` | ✅ | **Working correctly** |
| `pet_information` | Tenant | ❌ No | `pet_information` | `pet_information` | ✅ | Field not rendered |

---

## Recommended Follow-up Actions (not in scope for this task)

| Field | Recommended fix |
|---|---|
| Buyer `monthly_income` | Add a form input in an appropriate buyer wizard tab (e.g., buyer-info) wired to `$monthly_income` |
| Buyer `year_built_preference` | Add `public $year_built = ''` property, add `saveMeta('year_built', ...)` call, and add a blade input in the buyer wizard |
| Buyer `number_of_occupants` | Add a form input in an appropriate buyer wizard tab wired to `$number_occupant` |
| Tenant `pet_information` | Either add a composite text input wired to `$pet_information`, or update CANONICAL_SOURCE_MAP to cascade through the existing individual pet fields (`pets`, `type_of_pets`, etc.) |
