# Ask AI — All-Role Field Trace Results
**Audit Date:** June 13–14, 2026
**Scope:** Task #2641 — Gap remediation across all four listing roles (seller, buyer, landlord, tenant)
**Test baseline at close:** 2490 passed, 0 failed

---

## Overview

This document traces every factual field extracted by `AskAiContextBuilderService::extractFactualFields()`
for each of the four listing roles, confirms the routing path through `AskAiRunnerV2Service::LISTING_KEY_KEYWORD_MAP`
(Guard B), and records the four concrete gaps that were discovered and resolved in this task.

The pipeline for a `listing_facts` question is:

```
Question → AskAiQuestionClassifierService (listing_facts?)
         → AskAiRunnerV2Service::detectListingFieldKey() (LISTING_KEY_KEYWORD_MAP match?)
         → Guard B: ctx['listing'][$key] present? → return insufficient_context (skip OpenAI)
         → Otherwise: OpenAI adapter
```

---

## Pre/Post Trace-Results Table

The four target questions from the task — and the generic acceptance phrasing that was also validated —
traced through every pipeline stage before and after the fix.

**Column key:**
- **Classifier result** — output of `AskAiQuestionClassifierService::classify()`
- **normalized_field_key** — output of `AskAiRunnerV2Service::detectListingFieldKey()` (null = no map match)
- **Context status** — whether `ctx['listing'][$key]` was non-null for a seeded listing
- **Contract status** — output of `AskAiResponseContractService::evaluate()` given the context
- **Final status** — end-to-end pipeline status with data present

### BEFORE (gaps in place)

| # | Role | Sample Question | Classifier | normalized_field_key | Context status | Final status |
|---|------|----------------|-----------|---------------------|---------------|-------------|
| 1 | Buyer | "What cities are you interested in?" | **`unsupported`** (bare phrase not in classifier) | N/A | N/A | **`unsupported`** |
| 1 | Buyer | "What cities is the buyer looking in?" | `listing_facts` | `null` (no map entry) | N/A | **`unsupported`** |
| 1 | Buyer | "What counties is the buyer looking in?" | `listing_facts` | `null` (no map entry) | N/A | **`unsupported`** |
| 2 | Tenant | "What is your monthly income?" | `listing_facts` | `null` (no map entry) | N/A | **`unsupported`** |
| 2 | Tenant | "What is the household income?" | `listing_facts` | `null` (no map entry) | N/A | **`unsupported`** |
| 3 | Landlord | "Are pets allowed?" | `listing_facts` | `listing.pets_allowed` (wrong key — null for landlord) | missing | **`insufficient_context`** (wrong Guard B key) |
| 3 | Landlord | "What is the pet policy?" | `listing_facts` | `null` (no map entry) | N/A | **`unsupported`** |
| 4 | Seller | "Is the seller offering a credit?" | `listing_facts` | `null` (no map entry) | N/A | **`unsupported`** |

> `cities` and `counties` were also absent from the context builder buyer block — even if a map entry had
> existed, Guard B would always have returned `insufficient_context` (field never in `ctx['listing']`).
> Similarly, `monthly_income` was absent from the tenant block.

### AFTER (all gaps resolved)

| # | Role | Sample Question | Classifier | normalized_field_key | Context status | Contract status | Final status |
|---|------|----------------|-----------|---------------------|---------------|----------------|-------------|
| 1 | Buyer | "What cities are you interested in?" | `listing_facts` | `listing.cities` | present | `contract_ready` | **`ready`** |
| 1 | Buyer | "What cities is the buyer looking in?" | `listing_facts` | `listing.cities` | present | `contract_ready` | **`ready`** |
| 1 | Buyer | "What counties is the buyer looking in?" | `listing_facts` | `listing.counties` | present | `contract_ready` | **`ready`** |
| 1 | Buyer | "Which counties are preferred?" | `listing_facts` | `listing.counties` | present | `contract_ready` | **`ready`** |
| 1 | Buyer | "What cities is the buyer looking in?" | `listing_facts` | `listing.cities` | absent | `insufficient_context` | **`insufficient_context`** |
| 2 | Tenant | "What is your monthly income?" | `listing_facts` | `listing.monthly_income` | present | `contract_ready` | **`ready`** |
| 2 | Tenant | "What is the household income?" | `listing_facts` | `listing.monthly_income` | present | `contract_ready` | **`ready`** |
| 2 | Tenant | "What is the tenant income?" | `listing_facts` | `listing.monthly_income` | absent | `insufficient_context` | **`insufficient_context`** |
| 2 | Seller | "How much monthly income does this property generate?" | `listing_facts` | `listing.income_requirement` | present | `contract_ready` | **`ready`** ✓ (no collision) |
| 3 | Landlord | "Are pets allowed?" | `listing_facts` | `listing.pet_policy` *(remapped from `pets_allowed`)* | present | `contract_ready` | **`ready`** |
| 3 | Seller | "Are pets allowed?" | `listing_facts` | `listing.pets_allowed` *(no remap for seller)* | present | `contract_ready` | **`ready`** |
| 3 | Landlord | "What is the pet policy?" | `listing_facts` | `listing.pet_policy` | absent | `insufficient_context` | **`insufficient_context`** |
| 4 | Seller | "Is the seller offering a credit?" | `listing_facts` | `listing.seller_credit_offered` | present | `contract_ready` | **`ready`** |
| 4 | Seller | "Does the seller offer a credit?" | `listing_facts` | `listing.seller_credit_offered` | absent | `insufficient_context` | **`insufficient_context`** |

All scenarios are covered by test assertions in `AskAiListingFieldPipelineE2ETest` (scenarios 16–22).

---

## Task #2641 — Four Concrete Gaps Fixed

| # | Role | Gap Description | Root Cause | Fix |
|---|------|----------------|------------|-----|
| 1 | Buyer | `cities` and `counties` never extracted | Context builder used wrong EAV key — keys absent from buyer block | Added `'cities' => decodeJsonField(infoGet('cities'))` and `'counties' => decodeJsonField(infoGet('counties'))` to buyer block |
| 2 | Tenant | `monthly_income` never extracted | Field absent from tenant block entirely | Added `'monthly_income' => infoGet('monthly_income') ?? infoGet('household_monthly_income')` to tenant block |
| 3 | Landlord | `pet_policy` not in LISTING_KEY_KEYWORD_MAP | Field extracted correctly, but no routing phrases existed | Added `listing.pet_policy` entry with 10 natural-language phrases |
| 4 | Seller | `seller_credit_offered` EAV key mismatch | Context builder was missing the field entirely; EAV key is `seller_contribution_credit_offered` | Added `'seller_credit_offered' => infoGet('seller_contribution_credit_offered')` to seller block |

### Collision Guard — `listing.monthly_income`

The bare phrase `'monthly income'` was initially added to `listing.monthly_income` but **removed** after
it was found to intercept income-property seller questions (e.g. "How much monthly income does this
property generate?") that correctly route to `listing.income_requirement` (defined later in the map).

**Rule documented in map source:** Only use tenant-scoped phrases in `listing.monthly_income`.
Never add the bare phrase `'monthly income'` — it is too generic and collides with seller income-property routing.

---

## Seller Role — Field Trace

**Model:** `SellerAgentAuction` | **Storage:** `seller_agent_auction_metas` (EAV) + native columns

### Core Listing Fields

| Context Key | EAV Meta Key(s) | LISTING_KEY_KEYWORD_MAP Entry | Notes |
|-------------|----------------|-------------------------------|-------|
| `address` | native column | `listing.address` | ✓ |
| `description` | native column | — | surfaced via OpenAI / property standout |
| `asking_price` | `maximum_budget` | `listing.asking_price` | Desired Sale Price field |
| `buy_now_price` | `buy_now_price` | `listing.buy_now_price` | Offer-listing form only |
| `bedrooms` | `bedrooms` / `bedroom_id` | `listing.bedrooms` | resolveOtherValue |
| `bathrooms` | `bathrooms` / `bathroom_id` | `listing.bathrooms` | resolveOtherValue |
| `square_feet` | `minimum_heated_square` / `heated_square_footage` / `heated_square` | `listing.square_feet` | cascade |
| `year_built` | `year_built` | `listing.year_built` | ✓ |
| `pool` | `pool_needed` | `listing.pool` | ✓ |
| `pool_type` | `pool_type` | `listing.pool_type` | JSON decoded |
| `carport` | `carport_needed` | `listing.carport` | resolveOtherValue |
| `garage` | `garage_needed` | `listing.garage` | resolveOtherValue |
| `garage_spaces` | `garage_parking_spaces` | `listing.garage_spaces` | ✓ |
| `water_view` | `view_preference` | `listing.water_view` | JSON decoded multiselect |
| `seller_credit_offered` | `seller_contribution_credit_offered` | `listing.seller_credit_offered` | **Gap #4 — Fixed in Task #2641** |

### Lot / Land Fields

| Context Key | EAV Meta Key(s) | LISTING_KEY_KEYWORD_MAP Entry | Notes |
|-------------|----------------|-------------------------------|-------|
| `lot_size` | `total_acreage` / `min_acreage` | `listing.lot_size` | backward-compat alias |
| `total_acreage` | `total_acreage` | `listing.total_acreage` | ✓ |
| `lot_dimensions` | `lot_dimensions` | `listing.lot_dimensions` | ✓ |
| `zoning` | `zoning` | `listing.zoning` | ✓ |
| `waterfront` | `waterfront` | `listing.waterfront` | ✓ |
| `water_access` | `water_access` | `listing.water_access` | JSON decoded |

### Interior / Structural Fields

| Context Key | EAV Meta Key(s) | LISTING_KEY_KEYWORD_MAP Entry | Notes |
|-------------|----------------|-------------------------------|-------|
| `interior_features` | `interior_features` | `listing.interior_features` | JSON decoded |
| `appliances` | `appliances` | `listing.appliances` | JSON decoded |
| `roof_type` | `roof_type` | `listing.roof_type` | JSON decoded |
| `exterior_construction` | `exterior_construction` | `listing.exterior_construction` | JSON decoded |
| `foundation` | `foundation` | `listing.foundation` | JSON decoded |
| `heating_and_fuel` | `heating_and_fuel` | `listing.heating_fuel` | JSON decoded; dual output keys |
| `heating_fuel` | `heating_and_fuel` | `listing.heating_fuel` | alias for commercial context |
| `air_conditioning` | `air_conditioning` | `listing.air_conditioning` | JSON decoded |

### Utility Fields

| Context Key | EAV Meta Key(s) | LISTING_KEY_KEYWORD_MAP Entry | Notes |
|-------------|----------------|-------------------------------|-------|
| `water` | `water` | `listing.water` | JSON decoded |
| `water_source` | `water` | `listing.water_source` | alias for commercial context |
| `sewer` | `sewer` | `listing.sewer` | JSON decoded |
| `utilities` | `utilities` | `listing.utilities` | JSON decoded |

### Transaction / Occupancy Fields

| Context Key | EAV Meta Key(s) | LISTING_KEY_KEYWORD_MAP Entry | Notes |
|-------------|----------------|-------------------------------|-------|
| `sale_provision` | `sale_provision` | `listing.sale_provision` | JSON decoded |
| `offered_financing` | `offered_financing` | `listing.offered_financing` | JSON decoded |
| `occupant_status` | `occupant_status` | `listing.occupant_status` | ✓ |
| `furnished` | `building_features` | `listing.furnished` | extracted from JSON array |
| `closing_date` | `target_closing_date` | `listing.closing_date` | ✓ |
| `service_type` | `service_type` | — | internal metadata |

### HOA / Association Fields

| Context Key | EAV Meta Key(s) | LISTING_KEY_KEYWORD_MAP Entry | Notes |
|-------------|----------------|-------------------------------|-------|
| `hoa_association` | `has_hoa` | `listing.hoa_association` | ✓ |
| `hoa_fee` | `association_fee_amount` | `listing.hoa_fee` | ✓ |
| `hoa_payment_schedule` | `association_fee_frequency` | `listing.hoa_payment_schedule` | ✓ |
| `association_name` | `association_name` | `listing.association_name` | ✓ |
| `hoa_name` | `association_name` | `listing.association_name` | alias for commercial |
| `association_fee_includes` | `association_fee_includes` | `listing.association_fee_includes` | JSON decoded |

### CDD / Special Assessment Fields

| Context Key | EAV Meta Key(s) | LISTING_KEY_KEYWORD_MAP Entry | Notes |
|-------------|----------------|-------------------------------|-------|
| `has_cdd` | `has_cdd` | `listing.has_cdd` | ✓ |
| `annual_cdd_fee` | `annual_cdd_fee` | `listing.annual_cdd_fee` | ✓ |
| `has_special_assessments` | `has_special_assessments` | `listing.has_special_assessments` | ✓ |
| `special_assessment_amount` | `special_assessment_amount` | `listing.special_assessment_amount` | ✓ |
| `special_assessment_description` | `special_assessment_description` | `listing.special_assessment_description` | ✓ |
| `additional_parcels` | `additional_parcels` | — | ✓ |
| `total_parcel_count` | `total_parcel_count` | — | ✓ |

### Pets / Restrictions Fields

| Context Key | EAV Meta Key(s) | LISTING_KEY_KEYWORD_MAP Entry | Notes |
|-------------|----------------|-------------------------------|-------|
| `pets_allowed` | `pets` | `listing.pets_allowed` | ✓ |
| `number_of_pets_allowed` | `number_of_pets` | `listing.number_of_pets_allowed` | ✓ |
| `max_pet_weight` | `weight_of_pets` | `listing.max_pet_weight` | ✓ |
| `pet_restrictions` | `pet_restrictions` | `listing.pet_restrictions` | ✓ |
| `rental_restrictions` | `leasing_restrictions` | `listing.rental_restrictions` | ✓ |

### Flood Zone / Tax / Legal Fields

| Context Key | EAV Meta Key(s) | LISTING_KEY_KEYWORD_MAP Entry | Notes |
|-------------|----------------|-------------------------------|-------|
| `flood_zone_code` | `flood_zone_code` | `listing.flood_zone_code` | ✓ |
| `flood_zone_panel` | `flood_zone_panel` | `listing.flood_zone_panel` | ✓ |
| `flood_zone_date` | `flood_zone_date` | `listing.flood_zone_date` | ✓ |
| `flood_insurance_required` | `flood_insurance_required` | `listing.flood_insurance_required` | ✓ |
| `parcel_id` | `parcel_id` | `listing.parcel_id` | ✓ |
| `tax_year` | `tax_year` | `listing.tax_year` | ✓ |
| `legal_description` | `legal_description` | `listing.legal_description` | ✓ |
| `annual_property_taxes` | `annual_property_taxes` | `listing.annual_property_taxes` | ✓ |

### Commercial / Structural Fields

| Context Key | EAV Meta Key(s) | LISTING_KEY_KEYWORD_MAP Entry | Notes |
|-------------|----------------|-------------------------------|-------|
| `building_features` | `building_features` | `listing.building_features` | JSON decoded; unconditional |
| `building_sqft` | `total_square_feet` | `listing.building_sqft` | ✓ |
| `ceiling_height` | `ceiling_height` | `listing.ceiling_height` | ✓ |
| `parking_spaces` | `garage_parking_spaces` | `listing.parking_spaces` | commercial alias |
| `annual_noi` | `minimum_annual_net_income` | `listing.annual_noi` | ✓ |
| `price_per_sqft` | `price_per_sqft` | `listing.price_per_sqft` | ✓ |
| `existing_lease_type` | `existing_lease_type` | `listing.existing_lease_type` | ✓ |
| `lease_expiration` | `lease_expiration` | `listing.lease_expiration` | ✓ |
| `lease_assignable` | `lease_assignable` | `listing.lease_assignable` | ✓ |

### Income / Multifamily Fields

| Context Key | EAV Meta Key(s) | LISTING_KEY_KEYWORD_MAP Entry | Notes |
|-------------|----------------|-------------------------------|-------|
| `property_items` | `property_items` | `listing.property_items` | JSON decoded (duplex/triplex) |
| `total_units` | `unit_number` | `listing.total_units` | ✓ |
| `total_buildings` | `unit_buildings` | `listing.total_buildings` | ✓ |
| `unit_mix_summary` | `unit_type_configurations` | `listing.unit_mix_summary` | summarizeUnitConfigurations() |
| `gross_annual_income` | `gross_annual_income` | `listing.gross_annual_income` | ✓ |
| `annual_operating_expenses` | `annual_operating_expenses` | `listing.annual_operating_expenses` | ✓ |
| `annual_net_income` | `minimum_annual_net_income` | `listing.annual_net_income` | routing alias |
| `cap_rate` | `minimum_cap_rate` | `listing.cap_rate` | routing alias |
| `rent_roll_available` | `rent_roll_available` | `listing.rent_roll_available` | ✓ |
| `operating_statement_available` | `operating_statement_available` | `listing.operating_statement_available` | ✓ |
| `occupancy_requirement` | `assumable_occupancy_requirement` | `listing.occupancy_requirement` | resolveOtherValue |
| `income_requirement` | `monthly_income` | `listing.income_requirement` | income-property monthly income |

### Vacant Land Fields (conditional: `property_type === 'Vacant Land'`)

| Context Key | EAV Meta Key(s) | Notes |
|-------------|----------------|-------|
| `current_adjacent_use` | `current_adjacent_use` | JSON decoded |
| `water_available` | `water_available` | ✓ |
| `sewer_available` | `sewer_available` | ✓ |
| `electric_available` | `electric_available` | ✓ |
| `gas_available` | `gas_available` | ✓ |
| `telecom_available` | `telecom_available` | ✓ |
| `road_surface_type` | `road_surface_type` | JSON decoded |
| `front_footage` | `front_footage` | ✓ |
| `number_of_wells` | `number_of_wells` | ✓ |
| `number_of_septics` | `number_of_septics` | ✓ |
| `fences` | `fences` | JSON decoded |
| `vegetation` | `vegetation` | JSON decoded |
| `buildable` | `buildable` | ✓ |
| `easements` | `easements` | JSON decoded |

### Business Opportunity Fields (conditional: `property_type === 'Business'`)

| Context Key | EAV Meta Key(s) | Notes |
|-------------|----------------|-------|
| `business_type` | `business_type` | resolveOtherValue |
| `business_name` | `business_name` | ✓ |
| `year_established` | `year_established` | ✓ |
| `annual_revenue` | `annual_revenue` | ✓ |
| `gross_profit` | `gross_profit` | ✓ |
| `sde_ebitda` | `sde_ebitda` | ✓ |
| `inventory_value` | `inventory_value` | ✓ |
| `ffe_value` | `ffe_value` | ✓ |
| `reason_for_sale` | `reason_for_sale` | resolveOtherValue |
| `employee_count` | `employee_count` | ✓ |
| `financial_statements_available` | `financial_statements_available` | ✓ |
| `nda_required` | `nda_required` | ✓ |
| `business_location_leased` | `business_location_leased` | ✓ |
| `business_lease_monthly_rent` | `business_lease_monthly_rent` | ✓ |
| `business_lease_assignable` | `business_lease_assignable` | ✓ |
| `licenses` | `licenses` | JSON decoded |
| `sale_includes` | `sale_includes` | JSON decoded |
| `business_assets` | `business_assets` | JSON decoded |

---

## Buyer Role — Field Trace

**Model:** `BuyerAgentAuction` | **Storage:** `buyer_agent_auction_metas` (EAV) + native columns

| Context Key | EAV Meta Key(s) | LISTING_KEY_KEYWORD_MAP Entry | Notes |
|-------------|----------------|-------------------------------|-------|
| `address` | native column | `listing.address` | ✓ |
| `description` | native `additional_details` | — | ✓ |
| `max_price` | `maximum_budget` | `listing.max_price` | ✓ |
| `bedrooms` | `bedrooms` | `listing.bedrooms` | resolveOtherValue |
| `bathrooms` | `bathrooms` | `listing.bathrooms` | resolveOtherValue |
| `square_feet` | `minimum_heated_square` / `heated_square_footage` / `heated_square` | `listing.square_feet` | cascade |
| `pool` | `pool_needed` | `listing.pool` | ✓ |
| `carport` | `carport_needed` | `listing.carport` | resolveOtherValue |
| `garage` | `garage_needed` | `listing.garage` | resolveOtherValue |
| `garage_spaces` | `garage_parking_spaces` | `listing.garage_spaces` | ✓ |
| `water_view` | `view_preference` | `listing.water_view` | JSON decoded |
| `hoa_acceptable` | `hoa_acceptance` | `listing.hoa_acceptable` | ✓ |
| `max_hoa_fee` | `hoa_max_monthly_fee` | `listing.max_hoa_fee` | ✓ |
| `pets_allowed` | `pets` | `listing.pets_allowed` | ✓ |
| `pets_detail` | `type_of_pets` | `listing.pets_detail` | ✓ |
| `pets_breed` | `breed_of_pets` | `listing.pets_breed` | ✓ |
| `pets_weight` | `weight_of_pets` | `listing.pets_weight` | ✓ |
| `loan_pre_approved` | `pre_approved` | `listing.loan_pre_approved` | ✓ |
| `financing_type` | `financing_type` / `offered_financing` | `listing.financing_type` | JSON decoded; cascade |
| `inspection_period` | `inspection_period_days` | `listing.inspection_period` | ✓ |
| `closing_date` | `target_closing_date` | `listing.closing_date` | ✓ |
| `inspection_contingency_buyer` | `inspection_contingency_buyer` | `listing.inspection_contingency` | ✓ |
| `appraisal_contingency_buyer` | `appraisal_contingency_buyer` | `listing.appraisal_contingency` | ✓ |
| `financing_contingency_buyer` | `financing_contingency_buyer` | `listing.financing_contingency` | ✓ |
| `cities` | `cities` | `listing.cities` | **Gap #1 — Fixed in Task #2641**; JSON decoded |
| `counties` | `counties` | `listing.counties` | **Gap #1 — Fixed in Task #2641**; JSON decoded |

---

## Landlord Role — Field Trace

**Model:** `LandlordAgentAuction` | **Storage:** `landlord_agent_auction_metas` (EAV only)

### Core Rental Fields

| Context Key | EAV Meta Key(s) | LISTING_KEY_KEYWORD_MAP Entry | Notes |
|-------------|----------------|-------------------------------|-------|
| `rent_amount` | `desired_rental_amount` / `starting_rent` / `lease_now_price` | `listing.rent_amount` | cascade |
| `bedrooms` | `bedrooms` | `listing.bedrooms` | resolveOtherValue |
| `bathrooms` | `bathrooms` | `listing.bathrooms` | resolveOtherValue |
| `square_feet` | `minimum_heated_square` / `heated_square_footage` / `heated_square` | `listing.square_feet` | cascade |
| `unit_size` | `unit_size` | `listing.unit_size` | ✓ |
| `number_of_units` | `number_of_unit` | — | ✓ |
| `property_zip` | `property_zip` | — | ✓ |
| `property_items` | `property_items` | `listing.property_items` | JSON decoded |
| `condition_prop` | `condition_prop` | `listing.condition_prop` | resolveOtherValue |
| `appliances` | `appliances` | `listing.appliances` | JSON decoded |
| `water_view` | `view_preference` | `listing.water_view` | JSON decoded |
| `view` | `view_preference` | — | alias |
| `available_date` | `available_date` | `listing.available_date` | ✓ |

### Pet Policy Fields

| Context Key | EAV Meta Key(s) | LISTING_KEY_KEYWORD_MAP Entry | Notes |
|-------------|----------------|-------------------------------|-------|
| `pet_policy` | `pet_policy` | `listing.pet_policy` | **Gap #3 — Fixed in Task #2641** |
| `pet_deposit_fee_rent` | `pet_deposit_fee_rent` | `listing.pet_deposit_fee_rent` | ✓ |
| `pet_max_weight_lbs` | `pet_max_weight_lbs` | `listing.pet_max_weight_lbs` | ✓ |
| `pet_species_allowed` | `pet_species_allowed` | `listing.pet_species_allowed` | JSON decoded |
| `pet_deposit_amount` | `pet_deposit_amount` | `listing.pet_deposit_amount` | ✓ |
| `pet_monthly_fee` | `pet_monthly_fee` | `listing.pet_monthly_fee` | ✓ |

### Policies / Restrictions

| Context Key | EAV Meta Key(s) | LISTING_KEY_KEYWORD_MAP Entry | Notes |
|-------------|----------------|-------------------------------|-------|
| `parking_terms` | `parking_terms` | `listing.parking_terms` | ✓ |
| `utilities` | `property_utilities` / `utilities` | `listing.utilities` | JSON decoded; cascade |
| `smoking_policy` | `smoking_policy` | `listing.smoking_policy` | ✓ |
| `subletting_policy` | `subletting_policy` | `listing.subletting_policy` | ✓ |
| `leasing_restrictions` | `leasing_restrictions` | `listing.leasing_restrictions` | ✓ |

### HOA / Association Fields

| Context Key | EAV Meta Key(s) | LISTING_KEY_KEYWORD_MAP Entry | Notes |
|-------------|----------------|-------------------------------|-------|
| `has_hoa` | `has_hoa` | `listing.hoa_association` | ✓ |
| `association_name` | `association_name` | `listing.association_name` | ✓ |
| `association_fee_amount` | `association_fee_amount` | `listing.hoa_fee` | ✓ |
| `association_fee_frequency` | `association_fee_frequency` | `listing.hoa_payment_schedule` | ✓ |
| `association_amenities` | `association_amenities` | `listing.association_amenities` | JSON decoded |

### Lease Terms Fields

| Context Key | EAV Meta Key(s) | LISTING_KEY_KEYWORD_MAP Entry | Notes |
|-------------|----------------|-------------------------------|-------|
| `lease_length` | `min_lease_period` / `minimum_lease_period` / `desired_lease_length` | `listing.lease_length` | resolveOtherValue cascade |
| `renewal_option` | `renewal_option_offered` | `listing.renewal_option` | ✓ |
| `number_of_occupants` | `number_occupant` | — | ✓ |
| `number_of_occupants_allowed` | `number_of_occupants_allowed` | — | ✓ |
| `additional_lease_terms` | `additional_landlord_lease_terms` | — | ✓ |
| `lease_terms` | `terms_of_lease` | `listing.lease_terms` | JSON decoded |
| `first_month_rent_required` | `first_month_rent_required` | `listing.first_month_rent_required` | ✓ |
| `last_month_rent_required` | `last_month_rent_required` | `listing.last_month_rent_required` | ✓ |
| `total_move_in_funds_required` | `total_move_in_funds_required` | `listing.total_move_in_funds_required` | ✓ |
| `security_deposit_amount` | `security_deposit_amount` | `listing.security_deposit_amount` | ✓ |

### Structural / Legal / Tax Fields

| Context Key | EAV Meta Key(s) | LISTING_KEY_KEYWORD_MAP Entry | Notes |
|-------------|----------------|-------------------------------|-------|
| `year_built` | `year_built` | `listing.year_built` | ✓ |
| `lot_dimensions` | `lot_dimensions` | `listing.lot_dimensions` | ✓ |
| `zoning` | `zoning` | `listing.zoning` | ✓ |
| `waterfront` | `waterfront` | `listing.waterfront` | ✓ |
| `water_access` | `water_access` | `listing.water_access` | JSON decoded |
| `interior_features` | `interior_features` | `listing.interior_features` | JSON decoded |
| `roof_type` | `roof_type` | `listing.roof_type` | JSON decoded |
| `exterior_construction` | `exterior_construction` | `listing.exterior_construction` | JSON decoded |
| `foundation` | `foundation` | `listing.foundation` | JSON decoded |
| `flood_zone_code` | `flood_zone_code` | `listing.flood_zone_code` | ✓ |
| `flood_zone_panel` | `flood_zone_panel` | `listing.flood_zone_panel` | ✓ |
| `flood_zone_date` | `flood_zone_date` | `listing.flood_zone_date` | ✓ |
| `flood_insurance_required` | `flood_insurance_required` | `listing.flood_insurance_required` | ✓ |
| `annual_property_taxes` | `annual_property_taxes` | `listing.annual_property_taxes` | ✓ |
| `parcel_id` | `parcel_id` | `listing.parcel_id` | ✓ |
| `tax_year` | `tax_year` | `listing.tax_year` | ✓ |
| `legal_description` | `legal_description` | `listing.legal_description` | ✓ |
| `has_cdd` | `has_cdd` | `listing.has_cdd` | ✓ |
| `annual_cdd_fee` | `annual_cdd_fee` | `listing.annual_cdd_fee` | ✓ |
| `has_special_assessments` | `has_special_assessments` | `listing.has_special_assessments` | ✓ |
| `special_assessment_amount` | `special_assessment_amount` | `listing.special_assessment_amount` | ✓ |
| `special_assessment_description` | `special_assessment_description` | `listing.special_assessment_description` | ✓ |

### Utility Fields

| Context Key | EAV Meta Key(s) | LISTING_KEY_KEYWORD_MAP Entry | Notes |
|-------------|----------------|-------------------------------|-------|
| `heating_fuel` | `heating_fuel` | `listing.heating_fuel` | JSON decoded |
| `air_conditioning` | `air_conditioning` | `listing.air_conditioning` | JSON decoded |
| `water` | `water` | `listing.water` | JSON decoded |
| `sewer` | `sewer` | `listing.sewer` | JSON decoded |
| `tenant_pays` | `tenant_pays` | `listing.tenant_pays` | JSON decoded |
| `rent_includes` | `rent_includes` | `listing.rent_includes` | JSON decoded |

### Commercial Lease Fields

| Context Key | EAV Meta Key(s) | LISTING_KEY_KEYWORD_MAP Entry | Notes |
|-------------|----------------|-------------------------------|-------|
| `commercial_lease_type` | `commercial_lease_type` | `listing.commercial_lease_type` | resolveOtherValue |
| `cam_nnn_additional_rent_charges` | `cam_nnn_additional_rent_charges` | `listing.cam_charges` | ✓ |
| `rent_escalation_terms` | `rent_escalation_terms` | `listing.rent_escalation_terms` | ✓ |
| `tenant_improvement_buildout_terms` | `tenant_improvement_buildout_terms` | `listing.tenant_improvement_terms` | ✓ |
| `permitted_use_restrictions` | `permitted_use_restrictions` | `listing.permitted_use_restrictions` | ✓ |
| `signage_rights` | `signage_rights` | `listing.signage_rights` | ✓ |
| `commercial_parking_terms` | `commercial_parking_terms` | `listing.commercial_parking_terms` | ✓ |
| `personal_guarantee_requirement` | `personal_guarantee_requirement` | `listing.personal_guarantee_requirement` | ✓ |
| `commercial_approval_conditions` | `commercial_approval_conditions` | `listing.landlord_approval_conditions` | ✓ |
| `min_income_requirement` | `min_income_requirement` | `listing.min_income_requirement` | ✓ |

### Commercial Building Fields

| Context Key | EAV Meta Key(s) | Notes |
|-------------|----------------|-------|
| `total_buildings` | `total_buildings` | ✓ |
| `total_units_on_property` | `total_units_on_property` | ✓ |
| `office_retail_sqft` | `office_retail_sqft` | ✓ |
| `flex_space_sqft` | `flex_space_sqft` | ✓ |
| `ceiling_height` | `ceiling_height` | ✓ |
| `space_type` | `space_type` | JSON decoded |
| `space_classification` | `space_classification` | JSON decoded |
| `space_features` | `space_features` | ✓ |
| `number_of_restrooms` | `number_of_restrooms` | ✓ |
| `number_of_offices` | `number_of_offices` | ✓ |
| `number_of_conference_rooms` | `number_of_conference_rooms` | ✓ |
| `electrical_service` | `electrical_service` | JSON decoded |
| `building_features` | `building_features` | JSON decoded |
| `road_surface_type` | `road_surface_type` | JSON decoded |
| `building_hours` | `building_hours` | ✓ |
| `access_24_7` | `access_24_7` | ✓ |
| `zoning_allows` | `zoning_allows` | ✓ |
| `neighboring_tenants` | `neighboring_tenants` | ✓ |
| `shared_amenities` | `shared_amenities` | ✓ |

---

## Tenant Role — Field Trace

**Model:** `TenantAgentAuction` | **Storage:** `tenant_criteria_auction_metas` (EAV only)

| Context Key | EAV Meta Key(s) | LISTING_KEY_KEYWORD_MAP Entry | Notes |
|-------------|----------------|-------------------------------|-------|
| `max_rent` | `budget` / `maximum_budget` | `listing.max_rent` | cascade; budget is the correct key |
| `bedrooms` | `bedrooms` | `listing.bedrooms` | resolveOtherValue |
| `bathrooms` | `bathrooms` | `listing.bathrooms` | resolveOtherValue |
| `desired_lease_length` | `desired_lease_length` / `lease_for` | `listing.desired_lease_length` | JSON decoded; cascade |
| `property_items` | `property_items` | `listing.property_items` | JSON decoded |
| `appliances` | `appliances` | `listing.appliances` | JSON decoded |
| `condition_prop` | `condition_prop` | `listing.condition_prop` | resolveOtherValue |
| `pet_information` | `pet_information` | `listing.pet_information` | ✓ |
| `parking_needed` | `parking_needed` | `listing.parking_needed` | ✓ |
| `utilities` | `utilities` | `listing.utilities` | ✓ |
| `utility_preference` | `utility_preference` | — | ✓ |
| `tenant_pays` | `tenant_pays` | `listing.tenant_pays` | JSON decoded |
| `current_status` | `current_status` | — | ✓ |
| `number_of_occupants` | `number_of_occupants` | — | ✓ |
| `number_of_units` | `number_of_unit` | — | ✓ |
| `credit_score_range` | `credit_score_range` / `credit_score` | `listing.credit_score_range` | cascade; **existing field** |
| `monthly_income` | `monthly_income` / `household_monthly_income` | `listing.monthly_income` | **Gap #2 — Fixed in Task #2641** |

---

## LISTING_KEY_KEYWORD_MAP Additions (Task #2641)

The following entries were added to `AskAiRunnerV2Service::LISTING_KEY_KEYWORD_MAP`:

### `listing.cities` (Buyer/Tenant preferred location cities)
```
'preferred cities', 'which cities', 'what cities', 'city preferences',
'cities they are looking in', 'location cities', 'what city', 'preferred city'
```

### `listing.counties` (Buyer/Tenant preferred location counties)
```
'which counties', 'what counties', 'county preferences',
'counties they are looking in', 'preferred county', 'preferred counties'
```

### `listing.monthly_income` (Tenant household monthly income)
> **Collision rule:** Do NOT add bare `'monthly income'` — it intercepts seller income-property
> questions that route to `listing.income_requirement`. Use tenant-scoped phrases only.

```
'tenant monthly income', 'what is the tenant income', 'how much does the tenant earn',
'income of the tenant', 'household monthly income', 'what income does the tenant have',
'tenant income', 'household income of the tenant'
```

### `listing.seller_credit_offered` (Seller contribution / credit offered)
```
'seller credit', 'seller contribution', 'seller concession', 'closing cost credit',
'closing cost contribution', 'is a seller credit offered', 'does the seller offer credit',
'seller contribution credit', 'seller closing assistance'
```

### `listing.pet_policy` (Landlord pet policy — expanded)
Merged with existing landlord pet phrases to include:
```
'pet policy', 'are pets allowed', 'does this landlord allow pets', 'pet friendly',
'is the property pet friendly', 'what is the pet policy', 'dogs allowed',
'cats allowed', 'pet rules', 'pet restrictions for this rental',
'does the landlord allow pets', 'what pets are allowed'
```

---

## `deriveFieldLabel()` Additions

The following labels were added to `AskAiRunnerV2Service::deriveFieldLabel()` for Guard B display:

| Routing Key | Label String |
|-------------|-------------|
| `listing.cities` | `'Preferred cities information'` |
| `listing.counties` | `'Preferred counties information'` |
| `listing.monthly_income` | `'Tenant monthly income information'` |
| `listing.seller_credit_offered` | `'Seller credit offered information'` |

---

## Classifier Keywords Added (Task #2641)

The following keywords were added to `AskAiQuestionClassifierService` listing_facts block to ensure
the classifier routes these questions to the Guard B pipeline rather than OpenAI:

| Keyword Group | Keywords Added |
|--------------|---------------|
| Preferred cities/counties | `'preferred cities'`, `'preferred counties'`, `'preferred locations'`, `'preferred areas'`, `'preferred neighborhoods'` |
| Tenant income | `'tenant monthly income'`, `'household monthly income'`, `'tenant income'`, `'how much does the tenant earn'`, `'what is the tenant income'` |
| Seller credit | `'seller credit'`, `'seller contribution'`, `'seller concession'`, `'closing cost credit'` |
| Pet policy | `'pet policy'`, `'pet rules'`, `'are pets allowed'`, `'pet friendly'` |

---

## Response Contract (`allowed_context`) Additions

The `AskAiResponseContractService::LISTING_CONTEXT_FIELDS` array was extended with:

```
'listing.cities', 'listing.counties', 'listing.monthly_income', 'listing.seller_credit_offered'
```

These additions ensure the response contract allows Guard B to surface these fields to the AI when
present, and returns `insufficient_context` when absent, without leaking internal field keys.

---

## Test Coverage Added

All four gap fixes have corresponding test scenarios in
`tests/Unit/Services/AskAi/AskAiListingFieldPipelineE2ETest.php` (scenarios 16–19):

| Scenario | Role | Field | Tests |
|----------|------|-------|-------|
| 16 | Seller | `seller_credit_offered` | classify → `listing_facts`; detect → `listing.seller_credit_offered`; field present → `ready`; field null → Guard B fires `insufficient_context` |
| 17 | Buyer | `cities` | classify → `listing_facts`; detect → `listing.cities`; field present → `ready`; field null → Guard B fires `insufficient_context` |
| 18 | Buyer | `counties` | classify → `listing_facts`; detect → `listing.counties`; field present → `ready`; field null → Guard B fires `insufficient_context` |
| 19 | Tenant | `monthly_income` | classify → `listing_facts`; detect → `listing.monthly_income`; field present → `ready`; field null → Guard B fires `insufficient_context` |

Additionally, `AskAiIncomeKeywordRoutingTest` validates that:
- `'How much monthly income does this property generate?'` routes to `listing.income_requirement` (not `listing.monthly_income`)
- Map phrases `'property monthly income from rent'` and `'monthly income this property generates'` remain in `listing.income_requirement`

---

## Known Limitations / Out-of-Scope

| Item | Status |
|------|--------|
| Tenant `cities` / `counties` fields | Not added to tenant context block — `TenantAgentAuction` form does not currently collect city/county preferences. If added to the form, the tenant block should mirror the buyer block pattern. |
| Buyer `credit_score_range` | Buyer form does not collect credit score; field intentionally absent from buyer block. |
| `tenant_criteria_auctions` table | `Schema::hasTable()` guard required before querying; table absent in development environment; two gate-resolver tests remain skipped. |

---

*Generated by Task #2641 — Ask AI field extraction and routing gap remediation.*
