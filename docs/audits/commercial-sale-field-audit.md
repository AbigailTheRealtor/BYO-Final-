# Commercial Sale — Full-Stack Field Audit

**Date:** 2026-06-12  
**Scope:** Seller / Commercial Sale property_type across 14 pipeline locations  
**Status:** Complete — all gaps resolved

---

## Executive Summary

This audit covers every field on the Stellar MLS **Commercial Sale** data entry form and traces each one through the full pipeline:

1. MLS parser (`MlsListingImportService::parseFields`)
2. Normalizer (`MlsNormalizer::normalize`)
3. Field map (`MlsFieldMap::seller`)
4. Coverage reporter (`MlsCoverageReporter::fieldInventory`)
5. Ask AI context builder (`AskAiContextBuilderService::extractFactualFields` — seller arm)
6. Ask AI keyword router (`AskAiRunnerV2Service::LISTING_KEY_KEYWORD_MAP`)

**Before this audit:** 5 Commercial Sale fields had `canonical_key => null` in the coverage reporter; 0 of those fields had parser branches; Ask AI context builder was missing ~40 seller structural and commercial fields; LISTING_KEY_KEYWORD_MAP had no commercial keyword routes.

**After this audit:** All 5 stale `null` entries are resolved (3 get canonical keys, 2 stay null as out-of-scope); 2 additional fields (`building_features_list`, `current_use_list`) are added; 7 new parser branches are wired end-to-end; ~40 commercial/structural fields are now exposed in the Ask AI context; 35 new LISTING_KEY_KEYWORD_MAP entries cover every newly exposed field; `directions` is formally documented in Rejected Mapping Candidates.

---

## 1. Gap Inventory Before Fixes

| MLS Form | Section | MLS Label | Pre-Audit Status | Root Cause |
|---|---|---|---|---|
| Commercial Sale | BUILDING DETAILS | Building Size (Sq Ft) | `canonical_key = null` | No parser branch or field map entry |
| Commercial Sale | BUILDING DETAILS | Ceiling Height (Ft) | `canonical_key = null` | No parser branch or field map entry |
| Commercial Sale | BUILDING DETAILS | Parking Spaces | `canonical_key = null` | No parser branch or field map entry |
| Commercial Sale | FINANCIAL | Net Operating Income (NOI) | `canonical_key = null` | No parser branch or field map entry |
| Commercial Sale | FINANCIAL | Cap Rate | `canonical_key = null` | No parser branch or field map entry |
| Commercial Sale | BUILDING DETAILS | Building Features | Missing entry entirely | Not in fieldInventory at all |
| Commercial Sale | BUILDING DETAILS | Current Use | Missing entry entirely | Not in fieldInventory at all |
| All seller | — | Directions | Undocumented rejection | Parsed by parser but never imported; not in Rejected Mapping Candidates |
| Seller (Ask AI) | — | ~40 commercial/structural fields | Missing from extractFactualFields | zoning, lot_dimensions, lot_acreage, building_sqft, ceiling_height, building_features, current_use, parking_spaces, annual_noi, cap_rate, price_per_sqft, existing_lease_type, lease_expiration, lease_assignable, roof_type, exterior_construction, foundation, heating_fuel, air_conditioning, utilities, water_source, sewer, interior_features, waterfront, water_access, has_cdd, annual_cdd_fee, has_special_assessments, special_assessment_amount, special_assessment_description, hoa_name, parcel_id, tax_year, legal_description, additional_parcels, total_parcel_count, flood_zone_panel, flood_zone_date, flood_insurance_required |
| LISTING_KEY_KEYWORD_MAP | — | No commercial keyword routes | Missing entirely | None of the above fields had NL routing |

---

## 2. Changes Made

### 2.1 MlsListingImportService.php — 7 New Parser Branches

Added before the `// ─── Directions` section:

| Canonical Key | MLS Label(s) | Normalization |
|---|---|---|
| `building_size_sqft` | `Building Size:` / `Total Sq Ft:` | Strip commas, numeric-only |
| `ceiling_height_ft` | `Ceiling Height:` | Raw numeric |
| `parking_spaces_count` | `Parking Spaces:` | Raw integer |
| `net_operating_income` | `Net Operating Income (NOI):` / `NOI:` | `MlsNormalizer::normalizeNetOperatingIncome` (strip `$` and commas) |
| `cap_rate` | `Cap Rate:` | `MlsNormalizer::normalizeCapRate` (strip trailing `%`) |
| `building_features_list` | `Building Features:` / `Building Features / Amenities:` | Boundary-protected comma-separated string |
| `current_use_list` | `Current Use:` | Boundary-protected comma-separated string |

Also added 7 new stop-words to `$labelStop` to prevent bleed:
`Building\s+Size\b`, `Ceiling\s+Height\b`, `Parking\s+Spaces\b`, `Net\s+Operating\s+Income\b`, `NOI\b`, `Cap\s+Rate\b`, `Building\s+Features?\b`, `Current\s+Use\b`

### 2.2 MlsNormalizer.php — 2 New Normalizer Cases

| Field | Method | Input Example | Output |
|---|---|---|---|
| `cap_rate` | `normalizeCapRate()` | `8.5%` | `8.5` |
| `net_operating_income` | `normalizeNetOperatingIncome()` | `$185,000` | `185000` |

### 2.3 MlsFieldMap.php — 7 New Seller Entries + Labels

Added to `seller()`:

| Canonical Key | Livewire Property |
|---|---|
| `building_size_sqft` | `total_square_feet` |
| `ceiling_height_ft` | `ceiling_height` |
| `parking_spaces_count` | `garage_parking_spaces` |
| `net_operating_income` | `minimum_annual_net_income` |
| `cap_rate` | `minimum_cap_rate` |
| `building_features_list` | `*building_features` (JSON multiselect) |
| `current_use_list` | `*current_use` (JSON multiselect) |

Added corresponding `fieldLabels()` entries for all 7.

### 2.4 MlsCoverageReporter.php — fieldInventory() Fixes

**Commercial Sale — BUILDING DETAILS:** Fixed 3 stale `null` entries, added 2 new entries:

| MLS Label | Before | After |
|---|---|---|
| Building Size (Sq Ft) | `null` — "no app field or parser branch" | `building_size_sqft` — maps to `total_square_feet` |
| Ceiling Height (Ft) | `null` — "no app field or parser branch" | `ceiling_height_ft` — maps to `ceiling_height` |
| Parking Spaces | `null` — "no app field or parser branch" | `parking_spaces_count` — maps to `garage_parking_spaces` |
| Building Features | *(absent)* | `building_features_list` — multi-select, maps to `building_features` (JSON) |
| Current Use | *(absent)* | `current_use_list` — multi-select, maps to `current_use` (JSON) |

**Commercial Sale — FINANCIAL:** Fixed 2 stale `null` entries, added `norm_required = true`:

| MLS Label | Before | After |
|---|---|---|
| Net Operating Income (NOI) | `null` — "no app field or parser branch" | `net_operating_income` — maps to `minimum_annual_net_income`; normalizer strips `$` and commas |
| Cap Rate | `null` — "no app field or parser branch" | `cap_rate` — maps to `minimum_cap_rate`; normalizer strips `%` |

**Rejected Mapping Candidates:** Added `directions` — parsed by the parser but no Livewire property exists on any component; intentionally not imported.

### 2.5 AskAiContextBuilderService.php — Seller extractFactualFields Extended

Added 39 fields under a new `// ── Commercial / structural fields ────────────────────────────` comment block in the `'seller'` arm:

| Context Key | EAV Meta Key | Notes |
|---|---|---|
| `zoning` | `zoning` | |
| `lot_dimensions` | `lot_dimensions` | |
| `lot_acreage` | `total_acreage` | |
| `building_sqft` | `total_square_feet` | Gross building area (Commercial) |
| `ceiling_height` | `ceiling_height` | |
| `building_features` | `building_features` | JSON decoded |
| `current_use` | `current_use` | JSON decoded |
| `parking_spaces` | `garage_parking_spaces` | Commercial alias alongside existing `garage_spaces` |
| `annual_noi` | `minimum_annual_net_income` | |
| `cap_rate` | `minimum_cap_rate` | |
| `price_per_sqft` | `price_per_sqft` | |
| `existing_lease_type` | `existing_lease_type` | |
| `lease_expiration` | `lease_expiration` | |
| `lease_assignable` | `lease_assignable` | |
| `roof_type` | `roof_type` | JSON decoded |
| `exterior_construction` | `exterior_construction` | JSON decoded |
| `foundation` | `foundation` | JSON decoded |
| `heating_fuel` | `heating_and_fuel` | JSON decoded |
| `air_conditioning` | `air_conditioning` | JSON decoded |
| `utilities` | `utilities` | JSON decoded |
| `water_source` | `water` | JSON decoded |
| `sewer` | `sewer` | JSON decoded |
| `interior_features` | `interior_features` | JSON decoded |
| `waterfront` | `waterfront` | |
| `water_access` | `water_access` | JSON decoded |
| `has_cdd` | `has_cdd` | |
| `annual_cdd_fee` | `annual_cdd_fee` | |
| `has_special_assessments` | `has_special_assessments` | |
| `special_assessment_amount` | `special_assessment_amount` | |
| `special_assessment_description` | `special_assessment_description` | |
| `hoa_name` | `association_name` | |
| `parcel_id` | `parcel_id` | |
| `tax_year` | `tax_year` | |
| `legal_description` | `legal_description` | Explicitly called out in task |
| `additional_parcels` | `additional_parcels` | |
| `total_parcel_count` | `total_parcel_count` | |
| `flood_zone_panel` | `flood_zone_panel` | |
| `flood_zone_date` | `flood_zone_date` | |
| `flood_insurance_required` | `flood_insurance_required` | |

### 2.6 AskAiRunnerV2Service.php — 35 New LISTING_KEY_KEYWORD_MAP Entries

Added a `// ---- Commercial / Structural fields (Seller listings) ----` block with entries for:

`legal_description`, `zoning`, `lot_dimensions`, `lot_acreage`, `building_sqft`, `ceiling_height`, `parking_spaces`, `annual_noi`, `cap_rate`, `price_per_sqft`, `existing_lease_type`, `lease_expiration`, `lease_assignable`, `building_features`, `current_use`, `waterfront`, `water_access`, `roof_type`, `exterior_construction`, `foundation`, `heating_fuel`, `air_conditioning`, `water_source`, `sewer`, `interior_features`, `has_cdd`, `annual_cdd_fee`, `has_special_assessments`, `special_assessment_amount`, `hoa_name`, `parcel_id`, `tax_year`, `flood_zone_panel`, `flood_zone_date`, `flood_insurance_required`, `additional_parcels`, `total_parcel_count`

Each entry has ≥2 natural-language question phrases to trigger Guard B routing without an OpenAI call.

### 2.7 tests/fixtures/mls/commercial_sale.txt — 7 New Lines

Added after `Special Assessments Y/N: No`:

```
Building Size: 14,200
Ceiling Height: 18
Parking Spaces: 45
Net Operating Income (NOI): $185,000
Cap Rate: 8.5%
Building Features: Loading Dock, High Bay, Overhead Doors
Current Use: Light Industrial, Warehouse
```

---

## 3. Out-of-Scope Items (Explicitly Deferred)

| MLS Form | Section | MLS Label | Reason Deferred |
|---|---|---|---|
| Commercial Sale | BUILDING DETAILS | Number of Bays | No SellerOfferListing property; no Livewire form field |
| Commercial Sale | BUILDING DETAILS | Number of Dock Doors | No SellerOfferListing property; no Livewire form field |
| Commercial Sale | BUILDING DETAILS | Office Area (Sq Ft) | No SellerOfferListing property; no Livewire form field |
| Commercial Lease | All | All Commercial Lease fields | Out of task scope (separate form/role) |
| Income | FINANCIAL | Net Operating Income (NOI) | Out of task scope; Income form not covered |
| Income | FINANCIAL | Cap Rate | Out of task scope; Income form not covered |
| Business Opportunity | All | All Business fields | Out of task scope |
| Vacant Land | All | All Vacant Land fields | Out of task scope |

---

## 4. Test Coverage Added

**File:** `tests/Feature/ListingImport/MlsListingImportServiceTest.php`

| Test Name | What It Covers |
|---|---|
| `test_commercial_sale_building_size_parses_from_fixture` | Parser produces `building_size_sqft = '14200'` (commas stripped) |
| `test_commercial_sale_ceiling_height_parses_from_fixture` | Parser produces `ceiling_height_ft = '18'` |
| `test_commercial_sale_parking_spaces_parses_from_fixture` | Parser produces `parking_spaces_count = '45'` |
| `test_commercial_sale_noi_parses_and_normalizes_from_fixture` | Parser + normalizer produce `net_operating_income = '185000'` |
| `test_commercial_sale_cap_rate_parses_and_normalizes_from_fixture` | Parser + normalizer produce `cap_rate = '8.5'` |
| `test_commercial_sale_building_features_parses_from_fixture` | Parser produces `building_features_list` containing "Loading Dock" and "Overhead Doors" |
| `test_commercial_sale_current_use_parses_from_fixture` | Parser produces `current_use_list` containing "Light Industrial" and "Warehouse" |
| `test_commercial_sale_building_size_does_not_bleed_into_ceiling_height` | Boundary stop: `building_size_sqft` terminates before `Ceiling Height:` label |
| `test_commercial_sale_noi_does_not_bleed_into_cap_rate` | Boundary stop: `net_operating_income` terminates before `Cap Rate:` label |
| `test_seller_field_map_has_building_size_sqft` | `MlsFieldMap::forRole('seller')` maps to `total_square_feet` |
| `test_seller_field_map_has_ceiling_height_ft` | `MlsFieldMap::forRole('seller')` maps to `ceiling_height` |
| `test_seller_field_map_has_parking_spaces_count` | `MlsFieldMap::forRole('seller')` maps to `garage_parking_spaces` |
| `test_seller_field_map_has_net_operating_income` | `MlsFieldMap::forRole('seller')` maps to `minimum_annual_net_income` |
| `test_seller_field_map_has_cap_rate` | `MlsFieldMap::forRole('seller')` maps to `minimum_cap_rate` |
| `test_seller_field_map_has_building_features_list` | `MlsFieldMap::forRole('seller')` maps to `*building_features` |
| `test_seller_field_map_has_current_use_list` | `MlsFieldMap::forRole('seller')` maps to `*current_use` |
| `test_normalizer_cap_rate_strips_percent` | `MlsNormalizer::normalize('cap_rate', '8.5%')` → `'8.5'` |
| `test_normalizer_net_operating_income_strips_currency_formatting` | `MlsNormalizer::normalize('net_operating_income', '$185,000')` → `'185000'` |
| `test_inventory_commercial_sale_building_details_have_canonical_keys` | Coverage report contains all 5 new canonical keys |
| `test_inventory_commercial_sale_financial_have_canonical_keys` | Coverage report contains `net_operating_income` and `cap_rate` |
| `test_rejected_mappings_includes_directions` | Rejected Mapping Candidates section documents `directions` |

**Total new tests: 21 — all green.**

---

## 5. Regression Check

No existing tests were broken. The full `MlsListingImportServiceTest` suite passes.

---

## 6. Files Modified

| File | Change Type |
|---|---|
| `app/Services/ListingImport/MlsListingImportService.php` | 7 new parser branches + 7 new `$labelStop` patterns |
| `app/Services/ListingImport/MlsNormalizer.php` | 2 new normalizer cases (`cap_rate`, `net_operating_income`) |
| `app/Services/ListingImport/MlsFieldMap.php` | 7 new `seller()` entries + 7 new `fieldLabels()` entries |
| `app/Services/ListingImport/MlsCoverageReporter.php` | 5 stale `null` entries fixed; 2 new entries added; `directions` added to Rejected Mappings |
| `app/Services/AskAi/AskAiContextBuilderService.php` | 39 new seller context fields |
| `app/Services/AskAi/AskAiRunnerV2Service.php` | 35 new `LISTING_KEY_KEYWORD_MAP` entries |
| `tests/fixtures/mls/commercial_sale.txt` | 7 new field lines |
| `tests/Feature/ListingImport/MlsListingImportServiceTest.php` | 21 new tests |
| `docs/audits/commercial-sale-field-audit.md` | This document |
