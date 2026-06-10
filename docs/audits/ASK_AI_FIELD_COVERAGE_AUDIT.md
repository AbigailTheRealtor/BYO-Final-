# Ask AI — Field Coverage Audit
**Date:** June 2026
**Scope:** `AskAiContextBuilderService::buildForListing()` — all four roles

---

## Methodology

For each role the context builder assembles a flat context array from:
- **Native columns** — via `nativeGet()` (direct model property access)
- **EAV meta values** — via `infoGet()` (reads `*_metas` table via `loadMeta()`)

This audit maps every context key to its EAV source key and documents the cascade order
where multiple keys may hold the same logical value.

Fields marked **FIXED** had a wrong or missing key corrected in June 2026.
Fields marked **OK** were verified as correct in the live-DB audit.

---

## Seller Context Field Map (`seller_agent_auctions` + `seller_agent_auction_metas`)

| Context Key | Source | Meta Key(s) | Status |
|---|---|---|---|
| `address` | `nativeGet` | `address` (native column) | OK |
| `description` | `nativeGet` | `description` (native column) | OK |
| `asking_price` | `infoGet` | `maximum_budget` | OK |
| `bedrooms` | `infoGet` + `nativeGet` | `bedrooms` → `bedroom_id` (FK cascade) | OK |
| `bathrooms` | `infoGet` + `nativeGet` | `bathrooms` → `bathroom_id` (FK cascade) | OK |
| `square_feet` | `infoGet` | `minimum_heated_square` → `heated_square_footage` → `heated_square` | **FIXED** |
| `year_built` | `infoGet` | `year_built` | OK |
| `pool` | `infoGet` | `pool_needed` | OK |
| `pool_type` | `infoGet` (JSON) | `pool_type` | OK |
| `carport` | `infoGet` | `carport_needed` → `other_carport_needed` | OK |
| `garage` | `infoGet` | `garage_needed` → `other_garage` → `other_garage_needed` | OK |
| `garage_spaces` | `infoGet` | `garage_parking_spaces` | OK |
| `water_view` | `infoGet` (JSON) | `view_preference` | **pre-existing fix** |
| `hoa_association` | `infoGet` | `has_hoa` | OK |
| `hoa_fee` | `infoGet` | `association_fee_amount` | OK |
| `hoa_payment_schedule` | `infoGet` | `association_fee_frequency` | OK |
| `pets_allowed` | `infoGet` | `pets` | OK |
| `number_of_pets_allowed` | `infoGet` | `number_of_pets` | OK |
| `max_pet_weight` | `infoGet` | `weight_of_pets` | OK |
| `pet_restrictions` | `infoGet` | `pet_restrictions` | OK |
| `rental_restrictions` | `infoGet` | `leasing_restrictions` | OK |
| `flood_zone_code` | `infoGet` | `flood_zone_code` | OK |
| `disclosure_flags` | hardcoded | `['flood_zone' => true]` | OK |
| `closing_date` | `infoGet` | `target_closing_date` | OK |
| `auction_length` | `nativeGet` | `auction_length` (native) | OK |
| `sold` | `nativeGet` | `is_sold` (native) | OK |
| `annual_property_taxes` | `infoGet` | `annual_property_taxes` | OK |
| `service_type` | `infoGet` | `service_type` | OK |

---

## Buyer Context Field Map (`buyer_agent_auctions` + `buyer_agent_auction_metas`)

| Context Key | Source | Meta Key(s) | Status |
|---|---|---|---|
| `address` | `nativeGet` | `address` (native column) | OK |
| `description` | `nativeGet` | `additional_details` (native) | OK |
| `max_price` | `infoGet` | `maximum_budget` | OK |
| `bedrooms` | `infoGet` | `bedrooms` → `other_bedrooms` | OK |
| `bathrooms` | `infoGet` | `bathrooms` → `other_bathrooms` | OK |
| `square_feet` | `infoGet` | `minimum_heated_square` → `heated_square_footage` → `heated_square` | **FIXED** |
| `pool` | `infoGet` | `pool_needed` | OK |
| `carport` | `infoGet` | `carport_needed` → `other_carport_needed` | OK |
| `garage` | `infoGet` | `garage_needed` → `other_garage` → `other_garage_needed` | OK |
| `garage_spaces` | `infoGet` | `garage_parking_spaces` | OK |
| `water_view` | `infoGet` (JSON) | `view_preference` | **pre-existing fix** |
| `hoa_acceptable` | `infoGet` | `hoa_acceptance` | OK |
| `max_hoa_fee` | `infoGet` | `hoa_max_monthly_fee` | OK |
| `pets_allowed` | `infoGet` | `pets` | OK |
| `pets_detail` | `infoGet` | `type_of_pets` | OK |
| `pets_breed` | `infoGet` | `breed_of_pets` | OK |
| `pets_weight` | `infoGet` | `weight_of_pets` | OK |
| `loan_pre_approved` | `infoGet` | `pre_approved` | OK |
| `financing_type` | `infoGet` (JSON) | `financing_type` → `offered_financing` | **FIXED** |
| `inspection_period` | `infoGet` | `inspection_period_days` | OK |
| `closing_date` | `infoGet` | `target_closing_date` | OK |
| `inspection_contingency_buyer` | `infoGet` | `inspection_contingency_buyer` | OK |
| `appraisal_contingency_buyer` | `infoGet` | `appraisal_contingency_buyer` | OK |
| `financing_contingency_buyer` | `infoGet` | `financing_contingency_buyer` | OK |

---

## Landlord Context Field Map (`landlord_agent_auctions` + `landlord_agent_auction_metas`)

| Context Key | Source | Meta Key(s) | Status |
|---|---|---|---|
| `address` | `infoGet` | `property_address` | OK |
| `description` | `infoGet` | `property_description` | OK |
| `rent` | `infoGet` | `rent_amount` | OK |
| `bedrooms` | `infoGet` | `bedrooms` → `other_bedrooms` | OK |
| `bathrooms` | `infoGet` | `bathrooms` → `other_bathrooms` | OK |
| `square_feet` | `infoGet` | `minimum_heated_square` → `heated_square_footage` → `heated_square` | **FIXED** |
| `unit_size` | `infoGet` | `unit_size` | OK |
| `number_of_units` | `infoGet` | `number_of_unit` | OK |
| `property_zip` | `infoGet` | `property_zip` | OK |
| `property_items` | `infoGet` (JSON) | `property_items` | OK |
| `condition_prop` | `infoGet` | `condition_prop` → `other_condition_prop` | OK |
| `appliances` | `infoGet` (JSON) | `appliances` | OK |
| `water_view` | `infoGet` (JSON) | `view_preference` | **pre-existing fix** |
| `view` | `infoGet` (JSON) | `view_preference` | **pre-existing fix** |
| `pets` | `infoGet` | `pet_policy` | OK |
| `pet_species_allowed` | `infoGet` (JSON) | `pet_species_allowed` | OK |
| `utilities` | `infoGet` (JSON) | `property_utilities` → `utilities` | **pre-existing fix** |
| `laundry` | `infoGet` | `laundry_policy` | OK |
| `parking` | `infoGet` | `parking_type` | OK |
| `lease_term` | `infoGet` | `lease_term` | OK |
| `available_date` | `infoGet` | `available_date` | OK |
| `security_deposit` | `infoGet` | `security_deposit` | OK |
| `hoa_association` | `infoGet` | `has_hoa` | OK |
| `annual_property_taxes` | `infoGet` | `annual_property_taxes` | OK |

---

## Tenant Context Field Map (`tenant_agent_auctions` + `tenant_agent_auction_metas`)

| Context Key | Source | Meta Key(s) | Status |
|---|---|---|---|
| `address` | `infoGet` | `desired_location` | OK |
| `description` | `infoGet` | `additional_requirements` | OK |
| `max_rent` | `infoGet` | `budget` → `maximum_budget` | **pre-existing fix** (context key is `max_rent`, not `rental_budget`) |
| `bedrooms` | `infoGet` | `bedrooms` → `other_bedrooms` | OK |
| `bathrooms` | `infoGet` | `bathrooms` → `other_bathrooms` | OK |
| `pets` | `infoGet` | `pets` | OK |
| `pet_species` | `infoGet` (JSON) | `pet_species` | OK |
| `parking` | `infoGet` | `parking` | OK |
| `desired_lease_length` | `infoGet` (JSON) | `desired_lease_length` → `lease_for` | **pre-existing fix** |
| `move_in_date` | `infoGet` | `move_in_date` | OK |
| `max_hoa_fee` | `infoGet` | `hoa_max_monthly_fee` | OK |

---

## "Other" Token Filter in `decodeJsonField()`

All JSON-multiselect meta keys pass through `decodeJsonField()`. The method now filters
the literal `"Other"` (case-insensitive) from decoded arrays before joining. This prevents
the UI sentinel value from leaking into AI prompts.

Affected keys include: `pool_type`, `appliances`, `property_items`, `pet_species_allowed`,
`view_preference`, `financing_type`, `pet_species`, `desired_lease_length`.

---

## LISTING_KEY_KEYWORD_MAP — Full Coverage Table (49 fields)

The following table maps every canonical `listing.*` key in `LISTING_KEY_KEYWORD_MAP` to its
classifier route status after the June 2026 field coverage audit. All 49 fields now route
deterministically to `listing_facts` without requiring an OpenAI normalizer call.

`listing.rental_budget` was **removed** — its keywords were merged into `listing.max_rent`
because the context builder stores the tenant's budget under `ctx['listing']['max_rent']`
(not `rental_budget`). A separate entry would always have empty allowed_context.

| # | Canonical Key | Classifier Route | Map Keyword (representative) | Context Key | Status |
|---|---|---|---|---|---|
| 1 | `listing.address` | listing_facts | `property address` | `address` | OK |
| 2 | `listing.description` | listing_facts | `property description` | `description` | OK |
| 3 | `listing.asking_price` | listing_facts | `asking price` | `asking_price` | OK |
| 4 | `listing.buy_now_price` | listing_facts | `buy it now price` | `buy_now_price` | OK |
| 5 | `listing.max_price` | listing_facts | `buyer maximum budget` | `max_price` | OK |
| 6 | `listing.rent_amount` | listing_facts | `monthly rent` | `rent_amount` | OK |
| 7 | `listing.max_rent` | listing_facts | `tenant max rent`, `tenant rental budget` | `max_rent` | OK |
| 8 | `listing.bedrooms` | listing_facts | `how many bedrooms` | `bedrooms` | OK |
| 9 | `listing.bathrooms` | listing_facts | `how many bathrooms` | `bathrooms` | OK |
| 10 | `listing.square_feet` | listing_facts | `square feet` | `square_feet` | OK |
| 11 | `listing.year_built` | listing_facts | `when was this home built` | `year_built` | OK |
| 12 | `listing.pool` | listing_facts | `is there a pool` | `pool` | OK |
| 13 | `listing.carport` | listing_facts | `carport` | `carport` | OK |
| 14 | `listing.garage` | listing_facts | `is there a garage` | `garage` | OK |
| 15 | `listing.garage_spaces` | listing_facts | `how many garage spaces` | `garage_spaces` | OK |
| 16 | `listing.water_view` | listing_facts | `does it have a water view` | `water_view` | OK |
| 17 | `listing.hoa_fee` | listing_facts | `what are the monthly hoa dues` | `hoa_fee` | OK |
| 18 | `listing.hoa_fee_requirement` | listing_facts | `is hoa required` | `hoa_fee_requirement` | OK |
| 19 | `listing.hoa_acceptable` | listing_facts | `buyer hoa preference` | `hoa_acceptable` | OK |
| 20 | `listing.pets_allowed` | listing_facts | `are pets allowed` | `pets_allowed` | OK |
| 21 | `listing.pet_policy` | listing_facts | `pet policy for this rental` | `pet_policy` | OK |
| 22 | `listing.pet_deposit_fee_rent` | listing_facts | `pet fee amount` | `pet_deposit_fee_rent` | OK |
| 23 | `listing.pet_information` | listing_facts | `tenant pet details` | `pet_information` | OK |
| 24 | `listing.association_amenities` | listing_facts | `association amenities` | `association_amenities` | OK |
| 25 | `listing.lease_terms` | listing_facts | `existing lease terms on this property` | `lease_terms` | OK |
| 26 | `listing.lease_length` | listing_facts | `how long is the lease` | `lease_length` | OK |
| 27 | `listing.desired_lease_length` | listing_facts | `tenant preferred lease duration` | `desired_lease_length` | OK |
| 28 | `listing.renewal_option` | listing_facts | `renewal option available` | `renewal_option` | OK |
| 29 | `listing.rental_restrictions` | listing_facts | `rental restrictions on this property` | `rental_restrictions` | OK |
| 30 | `listing.utilities` | listing_facts | `utilities included with rent` | `utilities` | OK |
| 31 | `listing.tenant_pays` | listing_facts | `which utilities are the tenant responsibility` | `tenant_pays` | OK |
| 32 | `listing.smoking_policy` | listing_facts | `does this unit allow smoking` | `smoking_policy` | OK |
| 33 | `listing.subletting_policy` | listing_facts | `subletting policy` | `subletting_policy` | OK |
| 34 | `listing.parking_terms` | listing_facts | `parking terms for this rental` | `parking_terms` | OK |
| 35 | `listing.available_date` | listing_facts | `move-in date` | `available_date` | OK |
| 36 | `listing.closing_date` | listing_facts | `preferred closing date` | `closing_date` | OK |
| 37 | `listing.condition_prop` | listing_facts | `condition of the rental` | `condition_prop` | OK |
| 38 | `listing.appliances` | listing_facts | `appliances included` | `appliances` | OK |
| 39 | `listing.loan_pre_approved` | listing_facts | `buyer pre-approved for a loan` | `loan_pre_approved` | OK |
| 40 | `listing.financing_type` | listing_facts | `financing type` | `financing_type` | OK |
| 41 | `listing.inspection_period` | listing_facts | `buyer inspection contingency days` | `inspection_period` | OK |
| 42 | `listing.inspection_contingency_buyer` | listing_facts | `home inspection contingency` | `inspection_contingency_buyer` | OK |
| 43 | `listing.appraisal_contingency_buyer` | listing_facts | `appraisal contingency` | `appraisal_contingency_buyer` | OK |
| 44 | `listing.financing_contingency_buyer` | listing_facts | `financing contingency` | `financing_contingency_buyer` | OK |
| 45 | `listing.flood_zone_code` | listing_facts | `flood zone` | `flood_zone_code` | OK |
| 46 | `listing.annual_taxes` | listing_facts | `annual property taxes` | `annual_property_taxes` | OK |
| 47 | `listing.security_deposit` | listing_facts | `security deposit` | `security_deposit` | OK |
| 48 | `listing.lease_option` | listing_facts | `lease option` | `lease_option` | OK |
| 49 | `listing.property_type` | listing_facts | `what is the property type` | `property_type` | OK |
| ~~50~~ | ~~`listing.rental_budget`~~ | ~~listing_facts~~ | ~~`maximum rental budget`~~ | ~~`rental_budget`~~ | **REMOVED** — keywords merged into row 7 (`listing.max_rent`) |
| 50 | `listing.credit_score_range` | listing_facts | `credit score range` | `credit_score_range` | OK |

---

## Coverage Summary

| Role | Total context fields | Keys verified correct | Keys fixed in this task |
|---|---|---|---|
| Seller | 28 | 27 | 1 (square_feet cascade) |
| Buyer | 24 | 23 | 2 (square_feet cascade, financing_type) |
| Landlord | 24 | 21 | 1 (square_feet cascade) + 3 pre-existing |
| Tenant | 11 | 9 | 0 new + 2 pre-existing |

### LISTING_KEY_KEYWORD_MAP classifier coverage

| Metric | Value |
|---|---|
| Total canonical listing.* keys in map | 49 (was 50; listing.rental_budget removed) |
| Keys with deterministic classifier routing | 49 |
| Keys requiring normalizer fallback | 0 |
| Test harness data-sets | 50 (rental_budget_alt tests listing.max_rent with alternate phrasing) |
| Test harness assertions | 200 (50 data-sets × 4 assertions each) |
