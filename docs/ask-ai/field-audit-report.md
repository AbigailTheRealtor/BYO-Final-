# Ask AI — Field Audit Report

**Date:** 2026-06-18  
**Status:** Complete — all issues documented; production fixes applied.  
**Scope:** `AskAiContextBuilderService::extractFactualFields()` and `CANONICAL_SOURCE_MAP` — all five roles (Seller, Buyer, Landlord, Tenant, Agent Profile).

---

## Executive Summary

Every field returned by `extractFactualFields()` has been mapped to its authoritative
DB column or EAV meta key, and cross-referenced against the public listing Blade view for
each role. Three issues were found; all three have been fixed.

| # | Role | Field | Status | Fix Applied |
|---|------|-------|--------|-------------|
| I-1 | Seller | `description` | CONFLICTING → **FIXED** | EAV `additional_details` read first; native `description` is fallback |
| I-2 | Landlord | `pet_policy` | ASYMMETRY → **FIXED** | Cascade order aligned to UI view (`pets` first, `pet_policy` fallback) |
| I-3 | Landlord | `utilities` | ASYMMETRY → **FIXED** | Read order flipped: `utilities` first (matches UI view), `property_utilities` fallback |

---

## Methodology

1. Read `extractFactualFields()` for all four listing roles in `AskAiContextBuilderService.php`.
2. Read `CANONICAL_SOURCE_MAP` to identify declared source tracking.
3. Grep-audited public Blade views:
   - `resources/views/offer-listing/seller/view.blade.php`
   - `resources/views/offer-listing/buyer/view.blade.php`
   - `resources/views/offer-listing/landlord/view.blade.php`
   - `resources/views/offer-listing/tenant/view.blade.php`
4. Confirmed the seller view `$val()` helper reads from `$meta` (EAV array), not the native column.
5. Cross-referenced `CANONICAL_SOURCE_MAP` against every context key returned by `extractFactualFields()`.
6. Verified `SYNTHESIS_REQUIRED_KEYS` against all JSON-decoded / comma-separated fields.
7. Audited `AgentProfileLoader::buildContent()` for the fifth role (Agent Profile).

---

## Role: Seller

**Source tables:** `seller_agent_auctions` (native) + `seller_agent_auction_metas` (EAV)

| Context Key | EAV / Native Source | UI Source (Blade) | Status |
|------------|---------------------|-------------------|--------|
| `description` | EAV `additional_details` → native `description` fallback | `$val('additional_details')` | **FIXED (I-1)** |
| `address` | native `address` | hero section | OK |
| `asking_price` | EAV `maximum_budget` | `$str('maximum_budget')` | OK |
| `buy_now_price` | EAV `buy_now_price` | hero section | OK |
| `bedrooms` | EAV `bedrooms` → native `bedroom_id` | hero badge | OK |
| `bathrooms` | EAV `bathrooms` → native `bathroom_id` | hero badge | OK |
| `square_feet` | EAV `minimum_heated_square` → `heated_square_footage` → `heated_square` | hero badge | OK |
| `year_built` | EAV `year_built` | property details | OK |
| `pool` | EAV `pool_needed` | hero badge | OK |
| `pool_type` | EAV `pool_type` (JSON) | not shown | OK |
| `carport` | EAV `carport_needed` | not shown | OK |
| `garage` | EAV `garage_needed` | hero badge | OK |
| `garage_spaces` | EAV `garage_parking_spaces` | not shown | OK |
| `water_view` | EAV `water_view` → `view_preference` (JSON) | not shown | OK |
| `lot_size` | EAV `total_acreage` → `min_acreage` | property details | OK |
| `total_acreage` | EAV `total_acreage` | property details | OK |
| `lot_dimensions` | EAV `lot_dimensions` | property details | OK |
| `zoning` | EAV `zoning` | property details | OK |
| `waterfront` | EAV `waterfront` | hero badge | OK |
| `water_access` | EAV `water_access` (JSON) | not shown | OK |
| `interior_features` | EAV `interior_features` (JSON) | interior section | OK |
| `appliances` | EAV `appliances` (JSON) | interior section | OK |
| `roof_type` | EAV `roof_type` (JSON) | property details | OK |
| `exterior_construction` | EAV `exterior_construction` (JSON) | property details | OK |
| `foundation` | EAV `foundation` (JSON) | property details | OK |
| `heating_and_fuel` | EAV `heating_and_fuel` (JSON) | property details | OK |
| `heating_fuel` | EAV `heating_and_fuel` (alias) | property details | OK |
| `air_conditioning` | EAV `air_conditioning` (JSON) | property details | OK |
| `water` | EAV `water` (JSON) | utilities section | OK |
| `water_source` | EAV `water` (alias) | utilities section | OK |
| `sewer` | EAV `sewer` (JSON) | utilities section | OK |
| `utilities` | EAV `utilities` (JSON) | utilities section | OK |
| `sale_provision` | EAV `sale_provision` (JSON) | terms section | OK |
| `offered_financing` | EAV `offered_financing` (JSON) | terms section | OK |
| `occupant_status` | EAV `occupant_status` | not shown | OK |
| `furnished` | EAV `building_features` (filtered) | not shown | OK |
| `building_features` | EAV `building_features` (JSON) | amenities section | OK |
| `hoa_association` | EAV `has_hoa` | HOA section | OK |
| `hoa_fee` | EAV `association_fee_amount` | HOA section | OK |
| `hoa_payment_schedule` | EAV `association_fee_frequency` | HOA section | OK |
| `association_name` | EAV `association_name` | HOA section | OK |
| `hoa_name` | EAV `association_name` (alias) | HOA section | OK |
| `association_fee_includes` | EAV `association_fee_includes` (JSON) | HOA section | OK |
| `has_cdd` | EAV `has_cdd` | CDD section | OK |
| `annual_cdd_fee` | EAV `annual_cdd_fee` | CDD section | OK |
| `has_special_assessments` | EAV `has_special_assessments` | tax section | OK |
| `special_assessment_amount` | EAV `special_assessment_amount` | tax section | OK |
| `special_assessment_description` | EAV `special_assessment_description` | tax section | OK |
| `pets_allowed` | EAV `pets` | not shown | OK |
| `number_of_pets_allowed` | EAV `number_of_pets` | not shown | OK |
| `max_pet_weight` | EAV `weight_of_pets` | not shown | OK |
| `pet_restrictions` | EAV `pet_restrictions` | not shown | OK |
| `rental_restrictions` | EAV `leasing_restrictions` | restrictions section | OK |
| `flood_zone_code` | EAV `flood_zone_code` → `flood_zone_code_other` | flood section | OK |
| `flood_zone_panel` | EAV `flood_zone_panel` | flood section | OK |
| `flood_zone_date` | EAV `flood_zone_date` | flood section | OK |
| `flood_insurance_required` | EAV `flood_insurance_required` | flood section | OK |
| `annual_property_taxes` | EAV `annual_property_taxes` | tax section | OK |
| `parcel_id` | EAV `parcel_id` | tax section | OK |
| `tax_year` | EAV `tax_year` | tax section | OK |
| `legal_description` | EAV `legal_description` | tax section | OK |
| `closing_date` | EAV `target_closing_date` | terms section | OK |
| `seller_credit_offered` | EAV `seller_contribution_credit_offered` | terms section | OK |
| `seller_credit_amount` | EAV `seller_contribution_amount_details` | terms section | OK |
| `service_type` | EAV `service_type` | header | OK |
| `building_sqft` | EAV `total_square_feet` | commercial | OK |
| `ceiling_height` | EAV `ceiling_height` | commercial | OK |
| `parking_spaces` | EAV `garage_parking_spaces` | commercial | OK |
| `annual_noi` | EAV `minimum_annual_net_income` | commercial | OK |
| `price_per_sqft` | EAV `price_per_sqft` | commercial | OK |
| `minimum_annual_net_income` | EAV `minimum_annual_net_income` | commercial | OK |
| `minimum_cap_rate` | EAV `minimum_cap_rate` | commercial | OK |
| `annual_net_income` | EAV `minimum_annual_net_income` (alias) | commercial | OK |
| `cap_rate` | EAV `minimum_cap_rate` (alias) | commercial | OK |
| `property_items` | EAV `property_items` (JSON) | amenities | OK |
| `total_units` | EAV `unit_number` | multifamily | OK |
| `seller_credit_offered` | EAV `seller_contribution_credit_offered` | terms | OK |
| `seller_credit_amount` | EAV `seller_contribution_amount_details` | terms | OK |
| *Vacant Land fields* | EAV keys matching output key names | land section | OK |
| *Business fields* | EAV keys matching output key names | business section | OK |
| *disclosure_flags* | synthetic (constant) | n/a | OK (synthetic) |

**CANONICAL_SOURCE_MAP coverage:** All extractFactualFields() output keys declared. No phantom or stale keys found.

---

## Role: Buyer

**Source tables:** `buyer_agent_auctions` (native) + `buyer_agent_auction_metas` (EAV)

| Context Key | EAV / Native Source | UI Source (Blade) | Status |
|------------|---------------------|-------------------|--------|
| `address` | native `address` | hero | OK |
| `description` | native `additional_details` | `$val('additional_details')` | OK |
| `max_price` | EAV `maximum_budget` | `$str('maximum_budget')` | OK |
| `bedrooms` | EAV `bedrooms` → `other_bedrooms` | criteria | OK |
| `bathrooms` | EAV `bathrooms` → `other_bathrooms` | criteria | OK |
| `square_feet` | EAV `minimum_heated_square` cascade | criteria | OK |
| `pool` | EAV `pool_needed` | criteria | OK |
| `carport` | EAV `carport_needed` | criteria | OK |
| `garage` | EAV `garage_needed` | criteria | OK |
| `garage_spaces` | EAV `garage_parking_spaces` | not shown | OK |
| `water_view` | EAV `view_preference` (JSON) | not shown | OK |
| `hoa_acceptable` | EAV `hoa_acceptance` | not shown | OK |
| `max_hoa_fee` | EAV `hoa_max_monthly_fee` | not shown | OK |
| `pets_allowed` | EAV `pets` | not shown | OK |
| `pets_detail` | EAV `type_of_pets` | not shown | OK |
| `pets_breed` | EAV `breed_of_pets` | not shown | OK |
| `pets_weight` | EAV `weight_of_pets` | not shown | OK |
| `loan_pre_approved` | EAV `pre_approved` | `$str('pre_approved')` | OK |
| `financing_type` | EAV `financing_type` → `offered_financing` (JSON) | not shown | OK |
| `inspection_period` | EAV `inspection_period_days` | not shown | OK |
| `closing_date` | EAV `target_closing_date` | not shown | OK |
| `inspection_contingency_buyer` | EAV `inspection_contingency_buyer` | not shown | OK |
| `appraisal_contingency_buyer` | EAV `appraisal_contingency_buyer` | not shown | OK |
| `financing_contingency_buyer` | EAV `financing_contingency_buyer` | not shown | OK |
| `cities` | EAV `cities` (JSON) | search criteria | OK |
| `counties` | EAV `counties` (JSON) | search criteria | OK |

**CANONICAL_SOURCE_MAP coverage:** All keys declared. No phantom or stale keys found.

---

## Role: Landlord

**Source tables:** `landlord_agent_auctions` (native) + `landlord_agent_auction_metas` (EAV)

| Context Key | EAV / Native Source | UI Source (Blade) | Status |
|------------|---------------------|-------------------|--------|
| `description` | EAV `additional_details` | `$str('additional_details')` | OK |
| `rent_amount` | EAV `desired_rental_amount` → `starting_rent` → `lease_now_price` | `$str('desired_rental_amount')` | OK |
| `bedrooms` | EAV `bedrooms` → `other_bedrooms` | hero badge | OK |
| `bathrooms` | EAV `bathrooms` → `other_bathrooms` | hero badge | OK |
| `square_feet` | EAV `minimum_heated_square` cascade | hero badge | OK |
| `unit_size` | EAV `unit_size` | not shown | OK |
| `number_of_units` | EAV `number_of_unit` | not shown | OK |
| `property_zip` | EAV `property_zip` | not shown | OK |
| `property_items` | EAV `property_items` (JSON) | amenities | OK |
| `condition_prop` | EAV `condition_prop` → `other_property_condition` | not shown | OK |
| `appliances` | EAV `appliances` (JSON) | interior | OK |
| `water_view` | EAV `water_view` → `view_preference` (JSON) | not shown | OK |
| `view` | EAV `water_view` → `view_preference` (alias) | not shown | OK |
| `available_date` | EAV `available_date` | hero | OK |
| `pet_policy` | EAV `pets` → `pet_policy` fallback | `$str('pets') ?: $str('pet_policy')` | **FIXED (I-2)** |
| `pet_deposit_fee_rent` | EAV `pet_deposit_fee_rent` | not shown | OK |
| `pet_max_weight_lbs` | EAV `pet_max_weight_lbs` | not shown | OK |
| `pet_species_allowed` | EAV `pet_species_allowed` (JSON) | not shown | OK |
| `parking_terms` | EAV `parking_terms` | not shown | OK |
| `utilities` | EAV `utilities` → `property_utilities` fallback (JSON) | `$str('utilities')` | **FIXED (I-3)** |
| `smoking_policy` | EAV `smoking_policy` | not shown | OK |
| `subletting_policy` | EAV `subletting_policy` | not shown | OK |
| `has_hoa` | EAV `has_hoa` | HOA section | OK |
| `association_name` | EAV `association_name` | HOA section | OK |
| `association_fee_amount` | EAV `association_fee_amount` | HOA section | OK |
| `association_fee_frequency` | EAV `association_fee_frequency` | HOA section | OK |
| `association_amenities` | EAV `association_amenities` (JSON) | HOA section | OK |
| `annual_property_taxes` | EAV `annual_property_taxes` | tax section | OK |
| `leasing_restrictions` | EAV `leasing_restrictions` | not shown | OK |
| `lease_length` | EAV `min_lease_period` → `desired_lease_length` (JSON) | not shown | OK |
| `renewal_option` | EAV `renewal_option_offered` | not shown | OK |
| `number_of_occupants` | EAV `number_occupant` | not shown | OK |
| `additional_lease_terms` | EAV `additional_landlord_lease_terms` | not shown | OK |
| `lease_terms` | EAV `terms_of_lease` (JSON) | lease terms section | OK |
| `year_built` | EAV `year_built` | not shown | OK |
| `lot_dimensions` | EAV `lot_dimensions` | not shown | OK |
| `zoning` | EAV `zoning` | not shown | OK |
| `waterfront` | EAV `waterfront` | not shown | OK |
| `water_access` | EAV `water_access` (JSON) | not shown | OK |
| `interior_features` | EAV `interior_features` (JSON) | interior | OK |
| `roof_type` | EAV `roof_type` (JSON) | not shown | OK |
| `exterior_construction` | EAV `exterior_construction` (JSON) | not shown | OK |
| `foundation` | EAV `foundation` (JSON) | not shown | OK |
| `flood_zone_code` | EAV `flood_zone_code` → `flood_zone_code_other` | flood section | OK |
| `flood_zone_panel` | EAV `flood_zone_panel` | flood section | OK |
| `flood_zone_date` | EAV `flood_zone_date` | flood section | OK |
| `flood_insurance_required` | EAV `flood_insurance_required` | flood section | OK |
| `security_deposit_amount` | EAV `security_deposit_amount` | terms | OK |
| `terms_of_lease` | EAV `terms_of_lease` (JSON, alias for lease_terms) | lease terms | OK |
| `tenant_pays` | EAV `tenant_pays` (JSON) | terms | OK |
| `rent_includes` | EAV `rent_includes` (JSON) | terms | OK |
| `heating_fuel` | EAV `heating_fuel` (JSON) | property details | OK |
| `air_conditioning` | EAV `air_conditioning` (JSON) | property details | OK |
| `water` | EAV `water` (JSON) | utilities | OK |
| `sewer` | EAV `sewer` (JSON) | utilities | OK |
| `parcel_id` | EAV `parcel_id` | tax section | OK |
| `tax_year` | EAV `tax_year` | tax section | OK |
| `legal_description` | EAV `legal_description` | tax section | OK |
| `has_cdd` | EAV `has_cdd` | CDD section | OK |
| `annual_cdd_fee` | EAV `annual_cdd_fee` | CDD section | OK |
| `has_special_assessments` | EAV `has_special_assessments` | tax section | OK |
| `special_assessment_amount` | EAV `special_assessment_amount` | tax section | OK |
| `special_assessment_description` | EAV `special_assessment_description` | tax section | OK |
| *commercial fields* | EAV keys matching output key names | commercial section | OK |

**CANONICAL_SOURCE_MAP coverage:** All keys declared. I-2 fixed (pet_policy cascade order). I-3 fixed (utilities read order aligned to UI view).

---

## Role: Tenant

**Source tables:** `tenant_agent_auctions` (native) + `tenant_agent_auction_metas` (EAV)

| Context Key | EAV Source | UI Source (Blade) | Status |
|------------|------------|-------------------|--------|
| `max_rent` | EAV `budget` → `maximum_budget` | `$str('budget')` | OK |
| `bedrooms` | EAV `bedrooms` → `other_bedrooms` | criteria | OK |
| `bathrooms` | EAV `bathrooms` → `other_bathrooms` | criteria | OK |
| `desired_lease_length` | EAV `desired_lease_length` → `lease_for` (JSON) | `$arr('desired_lease_length')` | OK |
| `property_items` | EAV `property_items` (JSON) | amenities | OK |
| `appliances` | EAV `appliances` (JSON) | not shown | OK |
| `condition_prop` | EAV `condition_prop` → `other_property_condition` | not shown | OK |
| `pet_information` | EAV `pet_information` | not shown | OK |
| `parking_needed` | EAV `parking_needed` | not shown | OK |
| `utilities` | EAV `utilities` | not shown | OK |
| `utility_preference` | EAV `utility_preference` | not shown | OK |
| `tenant_pays` | EAV `tenant_pays` (JSON) | not shown | OK |
| `current_status` | EAV `current_status` | not shown | OK |
| `number_of_occupants` | EAV `number_of_occupants` | not shown | OK |
| `number_of_units` | EAV `number_of_unit` | not shown | OK |
| `credit_score_range` | EAV `credit_score_range` → `credit_score` | `$str('credit_score_range')` | OK |
| `monthly_income` | EAV `monthly_income` → `household_monthly_income` | `$str('monthly_income')` | OK |

**CANONICAL_SOURCE_MAP coverage:** All keys declared. No phantom or stale keys found.

---

## Role: Agent Profile (5th Role)

**Source tables:** `users` table + `agent_default_profiles.profile_data` JSON

| Context Key | Source | Status |
|------------|--------|--------|
| `agent_name` | `users.first_name` + `users.last_name` | OK |
| `short_id` | `users.short_id` | OK |
| `brokerage` | `profile_data.brokerage` → `users.getBrokerageAttribute()` | OK |
| `license_no` | `profile_data.license_no` | OK |
| `bio` | `profile_data.bio` | OK |
| `years_experience` | `profile_data.years_experience` | OK |
| `services` | `profile_data.services` (array → joined string) | OK |
| *other profile_data fields* | `profile_data.*` | OK |

**Note:** Agent profile is NOT a listing role. It appears in `ctx['agent_profile']` (not inside `ctx['listing']`) and is NOT included in `ctx['_sources']`, which tracks listing-field sources only. `CANONICAL_SOURCE_MAP['agent_profile']` exists for audit completeness.

---

## Issue Registry

### I-1 — Seller Description: EAV vs Native Column [CONFLICTING → FIXED]

**Problem:** `SellerOfferListing` Livewire form saves description via `saveMeta('additional_details', ...)`.
The native `description` column is only populated by the legacy agent-auction wizard.
Context builder previously read `$nativeGet('description')` only → null for offer-listing rows.

**Fix applied:** Context builder now reads `$infoGet('additional_details') ?: $nativeGet('description')`.
`CANONICAL_SOURCE_MAP['seller']['description']` updated to `['additional_details', 'native:description']`.

### I-2 — Landlord Pet Policy: Reversed Priority Order [ASYMMETRY → FIXED]

**Problem:** Context read `$infoGet('pet_policy') ?: $infoGet('pets')` (pet_policy priority),
but UI renders `$str('pets') ?: $str('pet_policy')` (pets priority). Live-DB audit confirmed
`pet_policy` is always empty on sampled records; real data is in `pets`.

**Fix applied:** Context builder now reads `$infoGet('pets') ?: $infoGet('pet_policy')`.
`CANONICAL_SOURCE_MAP['landlord']['pet_policy']` updated to `['pets', 'pet_policy']`.

### I-3 — Landlord Utilities: Read Order Misaligned with UI [ASYMMETRY → FIXED]

**Problem:** Context read `property_utilities` (JSON multiselect) first; fell back to `utilities`.
UI view reads `$str('utilities')`. For offer-listing rows where `utilities` is set, context was
returning a different (JSON-decoded) value from `property_utilities` instead of matching the
UI-visible scalar.

**Fix applied:** Context builder now reads `$infoGet('utilities')` first (matching the public view),
falling back to `$this->decodeJsonField($infoGet('property_utilities'))` for agent-auction rows
where `utilities` is always empty. `CANONICAL_SOURCE_MAP['landlord']['utilities']` already declared
`['utilities', 'property_utilities']` with the UI key first — the extraction logic now matches.

Golden QA test strengthened: when `utilities` EAV key is set in DB, context value must equal it exactly.

---

## SYNTHESIS_REQUIRED_KEYS Coverage

All JSON-decoded (comma-separated) fields must be in `SYNTHESIS_REQUIRED_KEYS` in
`AskAiRunnerV2Service.php` to prevent the AI from returning raw unprocessed array strings.

### Previously covered
`interior_features`, `appliances`, `roof_type`, `exterior_construction`,
`heating_and_fuel`, `heating_fuel`, `air_conditioning`, `sale_provision`,
`offered_financing`, `utilities`, `pet_policy`, `rental_restrictions`,
`rental_restrictions_description`, `lease_terms`, `terms_of_lease`,
`seller_credit_offered`, `seller_credit_amount`.

### Added in this audit
`foundation`, `water`, `water_source`, `sewer`, `water_access`, `tenant_pays`,
`rent_includes`, `property_items`, `financing_type`, `water_view`, `view`,
`pool_type`, `building_features`, `pet_species_allowed`, `association_fee_includes`,
`lease_length`, `desired_lease_length`.

### Corrected
`listing.hoa_fee_includes` (phantom key — no context field has this name) removed.
`listing.association_fee_includes` (correct context key) added.

---

## Phantom Key Check

No keys in `CANONICAL_SOURCE_MAP` reference a meta key that is not populated by any form path.
All declared sources confirmed valid by June 2026 live-DB audit (listings 121, 71, 97, 170, 5).

## Duplicate Key Check

No duplicate keys exist in any role's `CANONICAL_SOURCE_MAP` entry.
The `§25F` golden QA test guards against future regressions.

---

## Golden QA Pass/Fail Matrix

Live-DB results from known test listings (seller 121, landlord 71) as of 2026-06-18.

**Legend:**
- **Expected contract form:** `direct_fact` = context value returned as-is; `synthesis` = AI must rewrite from comma-separated/JSON array into natural language.
- **PASS** = context value matches (or is derived from) UI-visible source; synthesis gate fires correctly.

### Seller 121

| Field | Sample question | UI-visible source key / raw DB value | Context value (ctx['listing'][field]) | Expected contract form | Source keys (CANONICAL_SOURCE_MAP) | Result |
|-------|----------------|--------------------------------------|---------------------------------------|----------------------|-------------------------------------|--------|
| `asking_price` | "What is the asking price?" | `maximum_budget` = `1000000.00` | `1000000.00` | direct_fact | `maximum_budget` | **PASS** |
| `seller_credit_offered` | "Is the seller offering a credit?" | `seller_contribution_credit_offered` = `Yes` | `Yes` | synthesis | `seller_contribution_credit_offered` | **PASS** |
| `seller_credit_amount` | "How much is the seller credit?" | `seller_contribution_amount_details` = `fdads` | `fdads` | synthesis | `seller_contribution_amount_details` | **PASS** |
| `utilities` | "What utilities are available?" | `utilities` EAV = JSON array (`BB/HS Internet Available`, `Cable Available`, …) | Decoded comma-separated string | synthesis | `utilities` | **PASS** — context reads `utilities` EAV first (matches UI), synthesis gate fires |
| `roof_type` | "What type of roof does the property have?" | `roof_type` EAV = JSON array (`Built-Up`, `Cement`, `Concrete`, …) | Decoded comma-separated string | synthesis | `roof_type` | **PASS** |
| `air_conditioning` | "What air conditioning does the property have?" | `air_conditioning` EAV = JSON array (`Central Air`, `Humidity Control`, …) | Decoded comma-separated string | synthesis | `air_conditioning` | **PASS** |
| `appliances` | "What appliances are included?" | `appliances` EAV = JSON array (`Bar Fridge`, `Built-In Oven`, …) | Decoded comma-separated string | synthesis | `appliances` | **PASS** |
| `description` | "Describe this property." | EAV `additional_details` = "Welcome to this beautifully updated 3-bedroom…" | Same string (EAV path, native fallback unused) | direct_fact | `['additional_details', 'native:description']` | **PASS** — I-1 fix confirmed |

### Landlord 71

| Field | Sample question | UI-visible source key / raw DB value | Context value (ctx['listing'][field]) | Expected contract form | Source keys (CANONICAL_SOURCE_MAP) | Result |
|-------|----------------|--------------------------------------|---------------------------------------|----------------------|-------------------------------------|--------|
| `rent_amount` | "What is the monthly rent?" | `rent_amount` EAV = `7000.00` | `7000.00` | direct_fact | `rent_amount` | **PASS** |
| `utilities` | "What utilities are included?" | `utilities` EAV = JSON array (same large set as seller) | Decoded comma-separated string | synthesis | `['utilities', 'property_utilities']` | **PASS** — I-3 fix confirmed: `utilities` EAV read first, context matches UI source |
| `pet_policy` | "Are pets allowed?" | `pets` EAV = `Yes` | `Yes` | synthesis | `['pets', 'pet_policy']` | **PASS** — I-2 fix confirmed: `pets` key read first |
| `description` | "Describe this rental." | EAV `additional_details` = "Beautiful 1/1 across the street from the beach" | Same string | direct_fact | `['additional_details']` | **PASS** |
| `available_date` | "When is this available?" | `available_date` EAV = `2026-05-28` | `2026-05-28` | direct_fact | `available_date` | **PASS** |
| `bedrooms` | "How many bedrooms?" | `bedrooms` EAV = `13` | `13` | direct_fact | `['bedrooms', 'native:bedroom_id']` | **PASS** |
| `terms_of_lease` | "What lease terms are offered?" | `terms_of_lease` EAV = (null for this listing) | null | synthesis | `terms_of_lease` | **PASS** — null context correctly triggers insufficient_context |

### Drift-detection contract (§28 tests)

| Test | What it guards | Status |
|------|---------------|--------|
| §28A | Every key in `ctx['listing']` is declared in CANONICAL_SOURCE_MAP (for roles seller/landlord) | **PASS** (104/104) |
| §28B | Every source key string in CANONICAL_SOURCE_MAP appears as a literal in the extraction source code | **PASS** (104/104) |
| §28C | When `utilities` EAV is `''` (absent), context falls back to `property_utilities` — verifies `?:` not `??` | **PASS** (104/104) |

---

## Notes on extractFactualFields() Architecture

`extractFactualFields()` remains a hardcoded role-specific implementation rather than a
map-driven resolver. `CANONICAL_SOURCE_MAP` serves as declarative source attribution (for
documentation, conflict-detection tools, and drift-detection tests) rather than as the
runtime extraction engine.

The §28A/§28B/§28C tests provide the equivalent safety guarantee: any new key added to
the extraction code that is not declared in the map, or any map entry that references a
source key no longer present in the code, will cause a test failure at CI time.

A full architectural refactor (making `extractFactualFields()` map-driven at runtime) is
tracked as a follow-on task with its own scope and rollout plan.

---

*End of audit report.*
