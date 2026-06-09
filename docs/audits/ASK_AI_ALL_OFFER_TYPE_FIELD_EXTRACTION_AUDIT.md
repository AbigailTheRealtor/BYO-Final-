# Ask AI — All Offer Type Field Extraction Audit

**Date:** 2026-06-09  
**Scope:** `AskAiContextBuilderService::extractFactualFields()` — all four offer listing types (Seller, Buyer, Landlord, Tenant)  
**Out of scope:** Extraction fixes, Blade/Livewire changes, `saveMeta()` logic  

---

## Column Definitions

| Column | Meaning |
|--------|---------|
| **Display Label** | Human-readable field label shown in the UI |
| **Form/Input Key** | Livewire property name on the form component |
| **Livewire Property** | `$this->property_name` Livewire component property |
| **Native Column** | Actual column in the SQL table (or `—` if EAV only) |
| **Saved Meta Key** | EAV key written by `saveMeta()` (or `—` if native only) |
| **MLS/Import Key** | Source key from `MlsFieldMap` that lands at save (or `—`) |
| **Ask AI Current Key** | Accessor used in `extractFactualFields()` |
| **Actual Value Found?** | Yes / No / Not Saved / Unknown |
| **Mismatch?** | Yes / No / Key-Only / Accessor-Only |
| **Status** | **PASS** / **FAIL** / **BLOCKED** / **EXCLUDE** |
| **Notes** | Explanation of issue or compliance reason |

**Status key:**  
- **PASS** — key and accessor both correct; value is findable at runtime  
- **FAIL** — wrong column name, wrong accessor type (`nativeGet` vs `infoGet`), or key not saved  
- **BLOCKED** — field exists in neither native schema nor EAV (phantom key / not collected by the form)  
- **EXCLUDE** — field intentionally omitted (PII, internal workflow, protected-class-adjacent)

---

## Preamble: Accessor Architecture

`extractListingFields()` provides two accessor closures:

- **`$nativeGet(key)`** — reads `$listing->{key}` (Eloquent native attribute only; returns null if the column does not exist)
- **`$infoGet(key)`** — calls `$listing->info(key)`, which queries the `*_metas` EAV table

For **Seller** and **Buyer**, the `extractFactualFields()` branch overwhelmingly uses `nativeGet()`. However, the Seller and Buyer forms save almost every property-detail field via `saveMeta()` into EAV, **not** into native columns. This means `nativeGet()` silently returns null for the majority of those fields. This is the root cause of the pervasive FAIL status in both tables below.

For **Landlord** and **Tenant**, the branch correctly uses `infoGet()` throughout, which matches the EAV-only storage of those two models.

---

## 1. Seller (`SellerAgentAuction` → `seller_agent_auctions` + `seller_agent_auction_metas`)

**Native columns confirmed in `seller_agent_auctions`:**  
`id, user_id, address, auction_type, auction_length, city_id, county_id, state_id, bathroom_id, bedroom_id, sqft, min_price, max_commission, financings, additional_services, important_info, contract_terms, description, prop_condition, description_ideal_agent, need_cma, photos, video_url, video_file, audio_file, is_approved, is_sold, is_paid, sold_date, created_at, updated_at, listing_id, title, is_draft, referring_agent_id, referral_source_code, referral_captured_at, referral_locked`

All other property-detail fields are stored in **`seller_agent_auction_metas`** (EAV) via `saveMeta()`.

| Display Label | Form/Input Key | Livewire Property | Native Column | Saved Meta Key | MLS/Import Key | Ask AI Current Key | Actual Value Found? | Mismatch? | Status | Notes |
|---|---|---|---|---|---|---|---|---|---|---|
| Address | `address` | `$address` | `address` ✓ | — | — | `nativeGet('address')` | Yes | No | **PASS** | Native column present |
| Description | `description` | *(not a form field)* | `description` ✓ | — | — | `nativeGet('description')` | Yes | No | **PASS** | Native column `description` present |
| Asking / Sale Price | `maximum_budget` | `$maximum_budget` | — | `maximum_budget` | `price → maximum_budget` | `nativeGet('starting_price')` | No | Yes — key name + accessor | **FAIL** | No column `starting_price` exists. Form saves EAV `maximum_budget`. MLS maps `price → maximum_budget`. Fix: `infoGet('maximum_budget')` |
| Buy Now Price | `buy_now_price` | `$buy_now_price` | — | *(not saved)* | — | `nativeGet('buy_now_price')` | No | Yes — not saved | **BLOCKED** | Seller form does not call `saveMeta('buy_now_price', ...)`. No native column. |
| Bedrooms | `bedrooms` | `$bedrooms` | `bedroom_id` (FK) | `bedrooms` | `bedrooms → bedrooms` | `infoGet('bedrooms') ?? nativeGet('bedroom_id')` | Yes (via infoGet) | No | **PASS** | `infoGet` resolves correctly. Native fallback returns a raw FK integer, not a label. |
| Bathrooms | `bathrooms` | `$bathrooms` | `bathroom_id` (FK) | `bathrooms` | `bathrooms → bathrooms` | `infoGet('bathrooms') ?? nativeGet('bathroom_id')` | Yes (via infoGet) | No | **PASS** | Same as bedrooms — `infoGet` resolves correctly. |
| Square Feet | `minimum_heated_square` | `$minimum_heated_square` | — | `minimum_heated_square` | `heated_sqft → minimum_heated_square` | `nativeGet('heated_sqft')` | No | Yes — key name + accessor | **FAIL** | No column `heated_sqft` in seller_agent_auctions. Form saves `minimum_heated_square` as EAV. MLS key also lands as `minimum_heated_square`. Fix: `infoGet('minimum_heated_square')` |
| Year Built | `year_built` | `$year_built` | — | `year_built` | — | `nativeGet('year_built')` | No | Yes — accessor | **FAIL** | No native `year_built` column. Form saves EAV `year_built`. Fix: `infoGet('year_built')` |
| Pool | `pool_needed` | `$pool_needed` | — | `pool_needed` | — | `nativeGet('pool')` | No | Yes — key name + accessor | **FAIL** | No native `pool` column. Form saves `pool_needed`. Fix: `infoGet('pool_needed')` |
| Pool Type | `pool_type` | `$pool_type` | — | `pool_type` (JSON) | — | `nativeGet('pool_type')` | No | Yes — accessor | **FAIL** | No native `pool_type` column. Form saves JSON EAV. Fix: `decodeJsonField(infoGet('pool_type'))` |
| Carport | `carport_needed` | `$carport_needed` | — | `carport_needed` | — | `nativeGet('carport')` | No | Yes — key name + accessor | **FAIL** | No native `carport` column. Form saves `carport_needed`. Fix: `infoGet('carport_needed')` |
| Garage | `garage_needed` | `$garage_needed` | — | `garage_needed` | — | `nativeGet('garage')` | No | Yes — key name + accessor | **FAIL** | No native `garage` column. Form saves `garage_needed`. Fix: `infoGet('garage_needed')` |
| Garage Spaces | `garage_parking_spaces` | `$garage_parking_spaces` | — | `garage_parking_spaces` | — | `nativeGet('garage_spaces')` | No | Yes — key name + accessor | **FAIL** | No native `garage_spaces` column. Form saves `garage_parking_spaces`. Fix: `infoGet('garage_parking_spaces')` |
| Water View | — | *(not collected)* | — | — | — | `nativeGet('water_view')` | No | Yes — not saved | **BLOCKED** | Seller offer listing form does not collect or save a `water_view` field. Phantom key. |
| Water Extras | — | *(not collected)* | — | — | — | `nativeGet('water_extras')` | No | Yes — not saved | **BLOCKED** | Same as water_view — not collected by the form. Phantom key. |
| HOA Flag | `has_hoa` | `$has_hoa` | — | `has_hoa` | — | `nativeGet('hoa_association')` | No | Yes — key name + accessor | **FAIL** | No native `hoa_association`. Form saves `has_hoa`. Fix: `infoGet('has_hoa')` |
| HOA Fee | `association_fee_amount` | `$association_fee_amount` | — | `association_fee_amount` | — | `nativeGet('hoa_fee')` | No | Yes — key name + accessor | **FAIL** | No native `hoa_fee`. Form saves `association_fee_amount`. Fix: `infoGet('association_fee_amount')` |
| HOA Fee Requirement | — | *(not collected)* | — | — | — | `nativeGet('hoa_fee_requirement')` | No | Yes — not saved | **BLOCKED** | Seller form does not save an `hoa_fee_requirement` meta key. Phantom key from old property_auctions schema. |
| HOA Payment Schedule | `association_fee_frequency` | `$association_fee_frequency` | — | `association_fee_frequency` | — | `nativeGet('hoa_payment_schedule')` | No | Yes — key name + accessor | **FAIL** | No native `hoa_payment_schedule`. Form saves `association_fee_frequency`. Fix: `infoGet('association_fee_frequency')` |
| Condo Fee | — | *(not collected)* | — | — | — | `nativeGet('condo_fee')` | No | Yes — not saved | **BLOCKED** | Seller form does not save a `condo_fee` field. Phantom key from old schema. |
| Condo Fee Schedule | — | *(not collected)* | — | — | — | `nativeGet('condo_fee_schedule')` | No | Yes — not saved | **BLOCKED** | Same — phantom key from old schema. |
| Pets Allowed | `pets` | `$pets` | — | `pets` | — | `nativeGet('pets_allowed')` | No | Yes — key name + accessor | **FAIL** | No native `pets_allowed`. Form saves `pets`. Fix: `infoGet('pets')` |
| # Pets Allowed | `number_of_pets` | `$number_of_pets` | — | `number_of_pets` | — | `nativeGet('number_of_pets_allowed')` | No | Yes — key name + accessor | **FAIL** | No native `number_of_pets_allowed`. Form saves `number_of_pets`. Fix: `infoGet('number_of_pets')` |
| Max Pet Weight | `weight_of_pets` | `$weight_of_pets` | — | `weight_of_pets` | — | `nativeGet('max_pet_weight')` | No | Yes — key name + accessor | **FAIL** | No native `max_pet_weight`. Form saves `weight_of_pets`. Fix: `infoGet('weight_of_pets')` |
| Pet Restrictions | `pet_restrictions` | `$pet_restrictions` | — | `pet_restrictions` | — | `nativeGet('pet_restrictions')` | No | Yes — accessor only | **FAIL** | No native `pet_restrictions` column. Key name matches but accessor type is wrong. Fix: `infoGet('pet_restrictions')` |
| Rental Restrictions | `leasing_restrictions` | `$leasing_restrictions` | — | `leasing_restrictions` | — | `nativeGet('rental_restrictions')` | No | Yes — key name + accessor | **FAIL** | No native `rental_restrictions`. Form saves `leasing_restrictions`. Fix: `infoGet('leasing_restrictions')` |
| Rental Restrictions Description | — | *(not collected)* | — | — | — | `nativeGet('rental_restrictions_desription')` | No | Yes — not saved | **BLOCKED** | The misspelled column `rental_restrictions_desription` (missing 'c') exists only in the legacy `property_auctions` table, not in `seller_agent_auctions`. Seller form does not save this field. Phantom key. |
| In Flood Zone | — | *(not collected)* | — | — | — | `nativeGet('is_in_flood_zone')` | No | Yes — not saved | **BLOCKED** | Seller form saves `flood_zone_code` but not an `is_in_flood_zone` boolean. Phantom key. |
| Flood Zone Code | `flood_zone_code` | `$flood_zone_code` | — | `flood_zone_code` | — | `nativeGet('flood_zone_code')` | No | Yes — accessor only | **FAIL** | No native `flood_zone_code`. Key name matches EAV key but accessor type is wrong. Fix: `infoGet('flood_zone_code')` |
| Lease Terms | — | *(not collected)* | — | — | — | `nativeGet('lease_terms')` | No | Yes — not saved | **BLOCKED** | Seller offer listing form does not save a `lease_terms` meta key. Phantom from old schema. |
| Tenant Pays | — | *(not collected)* | — | — | — | `nativeGet('tenant_pays')` | No | Yes — not saved | **BLOCKED** | Seller form does not save a `tenant_pays` meta key (commercial-only field on landlord form). Phantom key. |
| Landlord Pays | — | *(not collected)* | — | — | — | `nativeGet('landlord_pays')` | No | Yes — not saved | **BLOCKED** | Not collected or saved by Seller form. Phantom key. |
| Closing Date | `target_closing_date` | `$target_closing_date` | — | `target_closing_date` | — | `nativeGet('closing_date')` | No | Yes — key name + accessor | **FAIL** | No native `closing_date`. Form saves `target_closing_date`. Fix: `infoGet('target_closing_date')` |
| Auction/Listing Length | — | `$auction_length` *(meta)* | `auction_length` ✓ | `auction_length` (meta) | — | `nativeGet('auction_length')` | Yes | No | **PASS** | `auction_length` is both a native column and saved as EAV meta. Native read resolves correctly. |
| MLS ID | — | *(not collected by offer form)* | — | — | — | `nativeGet('mls_id')` | No | Yes — not saved | **BLOCKED** | Seller offer listing form does not save `mls_id`. Not a native column. Phantom key. |
| Sold | — | *(status)* | `is_sold` ✓ | — | — | `nativeGet('sold')` | No | Yes — key name | **FAIL** | Native column is `is_sold`, not `sold`. Fix: `nativeGet('is_sold')` or cast via `$listing->is_sold` |
| Annual Property Taxes | `annual_property_taxes` | `$annual_property_taxes` | — | `annual_property_taxes` | — | `infoGet('annual_property_taxes')` | Yes | No | **PASS** | EAV key matches. Confirmed saved by form (line 3546). |
| Showing Instructions | — | *(not collected by offer form)* | — | — | — | `infoGet('showing_instructions')` | No | Yes — not saved | **BLOCKED** | Seller offer listing form does not save `showing_instructions`. This meta key exists on hire-agent workflow listings, not offer listings. |
| Service Type | `service_type` | `$service_type` | — | `service_type` | — | `infoGet('service_type')` | Yes (hire-agent) | No | **PASS** | Saved as EAV by both hire-agent and offer-listing flows. `infoGet` resolves correctly. |
| Disclosure Flags | *(synthetic)* | *(hardcoded)* | — | — | — | `['flood_zone' => true]` (always) | Yes | No | **PASS** | Governance marker; always set for seller. Not extracted from DB. |
| Listing Status | — | `$listing_status` | `is_approved` ✓ | — | — | `nativeGet('is_approved')` → ternary | Yes | No | **PASS** | Native `is_approved` boolean ✓ |
| City / State / County | varies | varies | — | `city`, `state`, `county`, `property_city`, etc. | — | `resolve('city')` = nativeGet ?? infoGet | Yes (via infoGet) | No | **PASS** | `$resolve` tries native then EAV; EAV keys present |
| Created At / Updated At | — | — | `created_at`, `updated_at` ✓ | — | — | direct attribute access | Yes | No | **PASS** | Native timestamps |

**Seller PASS count: 12 | FAIL count: 17 | BLOCKED count: 10**

---

## 2. Buyer (`BuyerAgentAuction` → `buyer_agent_auctions` + `buyer_agent_auction_metas`)

**Native columns confirmed in `buyer_agent_auctions`:**  
`id, user_id, address, title, auction_type, auction_length, city_id, county_id, state_id, bathroom_id, bedroom_id, property_type_id, concession, financing_currency, financing_approved, need_lender, preapproval_amount, additional_details, other, cash_budget, crypto_budget, is_approved, is_sold, is_paid, sold_date, created_at, updated_at, listing_id, is_draft, referring_agent_id, referral_source_code, referral_captured_at, referral_locked`

All property-detail and buyer-criteria fields are stored in **`buyer_agent_auction_metas`** (EAV) via `saveMeta()`.

| Display Label | Form/Input Key | Livewire Property | Native Column | Saved Meta Key | MLS/Import Key | Ask AI Current Key | Actual Value Found? | Mismatch? | Status | Notes |
|---|---|---|---|---|---|---|---|---|---|---|
| Address | `address` | `$address` | `address` ✓ | — | — | `nativeGet('address')` | Yes | No | **PASS** | Native column ✓ |
| Description | `additional_details` | *(native input)* | `additional_details` ✓ | — | — | `nativeGet('description')` | No | Yes — key name | **FAIL** | Native column is `additional_details`, not `description`. Fix: `nativeGet('additional_details')` |
| Max Budget / Price | `maximum_budget` | `$maximum_budget` | — | `maximum_budget` | `price → maximum_budget` | `nativeGet('max_price')` | No | Yes — key name + accessor | **FAIL** | No native `max_price`. Form saves EAV `maximum_budget`. MLS also maps to `maximum_budget`. Fix: `infoGet('maximum_budget')` |
| Bedrooms | `bedrooms` | `$bedrooms` | `bedroom_id` (FK) | `bedrooms` | `bedrooms → bedrooms` | `nativeGet('bedrooms')` | No | Yes — accessor | **FAIL** | No native `bedrooms` column (native is `bedroom_id` FK). Form saves EAV `bedrooms`. Fix: `infoGet('bedrooms')` |
| Bathrooms | `bathrooms` | `$bathrooms` | `bathroom_id` (FK) | `bathrooms` | `bathrooms → bathrooms` | `nativeGet('bathrooms')` | No | Yes — accessor | **FAIL** | No native `bathrooms` column. Form saves EAV `bathrooms`. Fix: `infoGet('bathrooms')` |
| Square Feet (min) | `minimum_heated_square` | `$minimum_heated_square` | — | `minimum_heated_square` | `heated_sqft → minimum_heated_square` | `nativeGet('sqft')` | No | Yes — key name + accessor | **FAIL** | No native `sqft` in buyer table. Form saves EAV `minimum_heated_square`. Fix: `infoGet('minimum_heated_square')` |
| Pool | `pool_needed` | `$pool_needed` | — | `pool_needed` | — | `nativeGet('pool')` | No | Yes — key name + accessor | **FAIL** | No native `pool`. Form saves `pool_needed`. Fix: `infoGet('pool_needed')` |
| Carport | `carport_needed` | `$carport_needed` | — | `carport_needed` | — | `nativeGet('carport')` | No | Yes — key name + accessor | **FAIL** | No native `carport`. Form saves `carport_needed`. Fix: `infoGet('carport_needed')` |
| Garage | `garage_needed` | `$garage_needed` | — | `garage_needed` | — | `nativeGet('garage')` | No | Yes — key name + accessor | **FAIL** | No native `garage`. Form saves `garage_needed`. Fix: `infoGet('garage_needed')` |
| Garage Spaces | `garage_parking_spaces` | `$garage_parking_spaces` | — | `garage_parking_spaces` | — | `nativeGet('garage_spaces')` | No | Yes — key name + accessor | **FAIL** | No native `garage_spaces`. Form saves `garage_parking_spaces`. Fix: `infoGet('garage_parking_spaces')` |
| Water View | — | *(not collected)* | — | — | — | `nativeGet('water_view')` | No | Yes — not saved | **BLOCKED** | Buyer criteria form does not collect or save `water_view`. Phantom key. |
| HOA Acceptable | `hoa_acceptance` | `$hoa_acceptance` | — | `hoa_acceptance` | — | `nativeGet('hoa')` | No | Yes — key name + accessor | **FAIL** | No native `hoa`. Form saves `hoa_acceptance`. Fix: `infoGet('hoa_acceptance')` |
| HOA Fee Requirement | — | *(not collected)* | — | — | — | `nativeGet('hoa_fee_requirement')` | No | Yes — not saved | **BLOCKED** | Not collected by Buyer form. Phantom key. |
| Max HOA Fee | `hoa_max_monthly_fee` | `$hoa_max_monthly_fee` | — | `hoa_max_monthly_fee` | — | `nativeGet('max_hoa_fee')` | No | Yes — key name + accessor | **FAIL** | No native `max_hoa_fee`. Form saves `hoa_max_monthly_fee`. Fix: `infoGet('hoa_max_monthly_fee')` |
| Pets Allowed | `pets` | `$pets` | — | `pets` | — | `nativeGet('pets_allowed')` | No | Yes — key name + accessor | **FAIL** | No native `pets_allowed`. Form saves `pets`. Fix: `infoGet('pets')` |
| Pets Detail | `type_of_pets` | `$type_of_pets` | — | `type_of_pets` | — | `nativeGet('pets_detail')` | No | Yes — key name + accessor | **FAIL** | No native `pets_detail`. Closest form save is `type_of_pets`. Fix: `infoGet('type_of_pets')` |
| Pets Breed | `breed_of_pets` | `$breed_of_pets` | — | `breed_of_pets` | — | `nativeGet('pets_breed')` | No | Yes — key name + accessor | **FAIL** | No native `pets_breed`. Form saves `breed_of_pets`. Fix: `infoGet('breed_of_pets')` |
| Pets Weight | `weight_of_pets` | `$weight_of_pets` | — | `weight_of_pets` | — | `nativeGet('pets_weight')` | No | Yes — key name + accessor | **FAIL** | No native `pets_weight`. Form saves `weight_of_pets`. Fix: `infoGet('weight_of_pets')` |
| Loan Pre-Approved | `pre_approved` | `$pre_approved` | — | `pre_approved` | — | `nativeGet('loan_pre_approved')` | No | Yes — key name + accessor | **FAIL** | No native `loan_pre_approved`. Form saves `pre_approved`. Fix: `infoGet('pre_approved')` |
| Financing Type | `offered_financing` | `$offered_financing` | — | `offered_financing` (JSON array of labels) | — | `resolveFinancingType(nativeGet('financing_id'))` | No | Yes — fundamentally wrong approach | **FAIL** | No `financing_id` FK column or EAV key. Buyer saves `offered_financing` as a JSON array of financing type name strings (e.g., `["Conventional","FHA"]`). `resolveFinancingType` expects a numeric FK. Fix: `decodeJsonField(infoGet('offered_financing'))` |
| Inspection Period | `inspection_period_days` | `$inspection_period_days` | — | `inspection_period_days` | — | `nativeGet('inspection_period')` | No | Yes — key name + accessor | **FAIL** | No native `inspection_period`. Form saves `inspection_period_days`. Fix: `infoGet('inspection_period_days')` |
| Closing Days | — | *(not a direct field)* | — | *(saved as `target_closing_date` or contingency sub-fields)* | — | `nativeGet('closing_days')` | No | Yes — not saved | **BLOCKED** | Buyer form does not save a `closing_days` meta key. The closing preference is captured as `target_closing_date` or `possession_preference`. Phantom key. |
| Contingencies | — | *(split fields)* | — | `inspection_contingency_buyer`, `appraisal_contingency_buyer`, `financing_contingency_buyer` (separate keys) | — | `nativeGet('contingencies')` | No | Yes — not saved | **BLOCKED** | Contingency data is stored as individual boolean meta keys, not a single `contingencies` value. Phantom key. |
| Listing Status | — | — | `is_approved` ✓ | — | — | `nativeGet('is_approved')` → ternary | Yes | No | **PASS** | Native column ✓ |
| City / State / County | varies | varies | — | `state`, `cities`, `property_city`, etc. | — | `resolve()` = nativeGet ?? infoGet | Yes (via infoGet) | No | **PASS** | EAV keys present |
| Created At / Updated At | — | — | `created_at`, `updated_at` ✓ | — | — | direct attribute access | Yes | No | **PASS** | Native timestamps |

**Buyer PASS count: 4 | FAIL count: 17 | BLOCKED count: 4**

---

## 3. Landlord (`LandlordAgentAuction` → `landlord_agent_auctions` + `landlord_agent_auction_metas`)

**Native columns confirmed in `landlord_agent_auctions`:**  
`id, user_id, auction_type, is_approved, is_draft, is_sold, sold_date, created_at, updated_at, display_bids, auction_ended, listing_id, referring_agent_id, referral_source_code, referral_captured_at, referral_locked, title`

All property and lease fields are stored in **`landlord_agent_auction_metas`** (EAV). `extractFactualFields()` correctly uses `infoGet()` throughout this branch.

| Display Label | Form/Input Key | Livewire Property | Native Column | Saved Meta Key | MLS/Import Key | Ask AI Current Key | Actual Value Found? | Mismatch? | Status | Notes |
|---|---|---|---|---|---|---|---|---|---|---|
| Rent Amount | `maximum_budget` | `$maximum_budget` | — | `maximum_budget` | `price → maximum_budget` | `infoGet('maximum_budget')` | Yes | No | **PASS** | EAV key match ✓ |
| Bedrooms | `bedrooms` | `$bedrooms` | — | `bedrooms` | `bedrooms → bedrooms` | `infoGet('bedrooms')` | Yes | No | **PASS** | EAV key match ✓ |
| Bathrooms | `bathrooms` | `$bathrooms` | — | `bathrooms` | `bathrooms → bathrooms` | `infoGet('bathrooms')` | Yes | No | **PASS** | EAV key match ✓ |
| Square Feet | `minimum_heated_square` | `$minimum_heated_square` | — | `minimum_heated_square` | `heated_sqft → minimum_heated_square` | `infoGet('minimum_heated_square')` | Yes | No | **PASS** | EAV key match ✓ |
| Unit Size | `unit_size` | `$unit_size` | — | `unit_size` | — | `infoGet('unit_size')` | Yes | No | **PASS** | EAV key match ✓ |
| Number of Units | `number_of_unit` | `$number_of_unit` | — | `number_of_unit` | — | `infoGet('number_of_unit')` | Yes | No | **PASS** | Note: display label is "number_of_units" but saved key is `number_of_unit` (no trailing 's'). Ask AI correctly reads `number_of_unit`. ✓ |
| Property Zip | `property_zip` | `$property_zip` | — | `property_zip` | — | `infoGet('property_zip')` | Yes | No | **PASS** | EAV key match ✓ |
| Property Items | `property_items` | `$property_items` | — | `property_items` (JSON) | — | `decodeJsonField(infoGet('property_items'))` | Yes | No | **PASS** | EAV JSON decode ✓ |
| Property Condition | `condition_prop` | `$condition_prop` | — | `condition_prop` | — | `infoGet('condition_prop')` | Yes | No | **PASS** | EAV key match ✓ |
| Appliances | `appliances` | `$appliances` | — | `appliances` (JSON) | — | `decodeJsonField(infoGet('appliances'))` | Yes | No | **PASS** | EAV JSON decode ✓ |
| Available Date | `available_date` | `$available_date` | — | `available_date` | — | `infoGet('available_date')` | Yes | No | **PASS** | EAV key match ✓. Note: form also saves `lease_available_date`; Ask AI reads `available_date` which is the correct residential key. |
| Pet Policy | `pet_policy` | `$pet_policy` | — | `pet_policy` | — | `infoGet('pet_policy')` | Yes | No | **PASS** | EAV key match ✓ |
| Pet Deposit | `pet_deposit_fee_rent` | `$pet_deposit_fee_rent` | — | `pet_deposit_fee_rent` | — | `infoGet('pet_deposit_fee_rent')` | Yes | No | **PASS** | EAV key match ✓ |
| Max Pet Weight | `pet_max_weight_lbs` | `$pet_max_weight_lbs` | — | `pet_max_weight_lbs` | — | `infoGet('pet_max_weight_lbs')` | Yes | No | **PASS** | EAV key match ✓ |
| Pet Species Allowed | `pet_species_allowed` | `$pet_species_allowed` | — | `pet_species_allowed` (JSON) | — | `decodeJsonField(infoGet('pet_species_allowed'))` | Yes | No | **PASS** | EAV JSON decode ✓ |
| Parking Terms | `parking_terms` | `$parking_terms` | — | `parking_terms` | — | `infoGet('parking_terms')` | Yes | No | **PASS** | EAV key match ✓ |
| Utilities | `utilities` | `$utilities` | — | `utilities` | — | `infoGet('utilities')` | Yes | No | **PASS** | Form saves `utilities` EAV (line 3075). Key match ✓ |
| Smoking Policy | `smoking_policy` | `$smoking_policy` | — | `smoking_policy` | — | `infoGet('smoking_policy')` | Yes | No | **PASS** | EAV key match ✓ |
| Subletting Policy | `subletting_policy` | `$subletting_policy` | — | `subletting_policy` | — | `infoGet('subletting_policy')` | Yes | No | **PASS** | EAV key match ✓ |
| Has HOA | `has_hoa` | `$has_hoa` | — | `has_hoa` | — | `infoGet('has_hoa')` | Yes | No | **PASS** | EAV key match ✓ |
| Association Name | `association_name` | `$association_name` | — | `association_name` | — | `infoGet('association_name')` | Yes | No | **PASS** | EAV key match ✓ |
| Association Fee | `association_fee_amount` | `$association_fee_amount` | — | `association_fee_amount` | — | `infoGet('association_fee_amount')` | Yes | No | **PASS** | EAV key match ✓ |
| Association Frequency | `association_fee_frequency` | `$association_fee_frequency` | — | `association_fee_frequency` | — | `infoGet('association_fee_frequency')` | Yes | No | **PASS** | EAV key match ✓ |
| Association Amenities | `association_amenities` | `$association_amenities` | — | `association_amenities` (JSON) | — | `decodeJsonField(infoGet('association_amenities'))` | Yes | No | **PASS** | EAV JSON decode ✓. Confirmed in DB. |
| Annual Property Taxes | `annual_property_taxes` | `$annual_property_taxes` | — | `annual_property_taxes` | — | `infoGet('annual_property_taxes')` | Yes | No | **PASS** | EAV key match ✓. Confirmed in DB. |
| Leasing Restrictions | `leasing_restrictions` | `$leasing_restrictions` | — | `leasing_restrictions` | — | `infoGet('leasing_restrictions')` | Yes | No | **PASS** | EAV key match ✓ |
| Min Lease Period | `min_lease_period` | `$min_lease_period` | — | `min_lease_period` | — | `infoGet('min_lease_period') ?? infoGet('minimum_lease_period')` | Yes | No | **PASS** | Form saves `min_lease_period`. Dual-alias read handles legacy `minimum_lease_period` key. ✓ |
| Renewal Option | `renewal_option_offered` | `$renewal_option_offered` | — | `renewal_option_offered` | — | `infoGet('renewal_option_offered')` | Yes | No | **PASS** | EAV key match ✓ |
| Number of Occupants | `number_occupant` | `$number_occupant` | — | `number_occupant` | — | `infoGet('number_of_occupants_allowed')` | No | Yes — key name | **FAIL** | Form saves `number_occupant`. Ask AI reads `number_of_occupants_allowed`. These keys do not match. Fix: `infoGet('number_occupant')` |
| Additional Lease Terms | `additional_landlord_lease_terms` *(saved as)* | `$additional_landlord_lease_terms` | — | `additional_landlord_lease_terms` | — | `infoGet('additional_landlord_lease_terms')` | Yes | No | **PASS** | EAV key match ✓. Confirmed in DB. |
| Listing Status | — | `$listing_status` | `is_approved` ✓ | — | — | `nativeGet('is_approved')` → ternary | Yes | No | **PASS** | Native column ✓ |
| City / State / County | varies | varies | — | `state`, `property_city`, etc. | — | `resolve()` | Yes | No | **PASS** | EAV keys present |
| Created At / Updated At | — | — | `created_at`, `updated_at` ✓ | — | — | direct attribute access | Yes | No | **PASS** | Native timestamps |

**Landlord PASS count: 32 | FAIL count: 1 | BLOCKED count: 0**

---

## 4. Tenant (`TenantAgentAuction` → `tenant_agent_auctions` + `tenant_agent_auction_metas`)

**Native columns confirmed in `tenant_agent_auctions`:**  
`id, user_id, auction_type, is_approved, is_draft, is_sold, sold_date, auction_ended, created_at, updated_at, listing_id, referring_agent_id, referral_source_code, referral_captured_at, referral_locked, title`

All tenant-criteria fields are stored in **`tenant_agent_auction_metas`** (EAV). `extractFactualFields()` correctly uses `infoGet()` throughout this branch.

| Display Label | Form/Input Key | Livewire Property | Native Column | Saved Meta Key | MLS/Import Key | Ask AI Current Key | Actual Value Found? | Mismatch? | Status | Notes |
|---|---|---|---|---|---|---|---|---|---|---|
| Max Rent | `maximum_budget` | `$maximum_budget` | — | `maximum_budget` | `price → maximum_budget` | `infoGet('maximum_budget')` | Yes | No | **PASS** | EAV key match ✓ |
| Bedrooms | `bedrooms` | `$bedrooms` | — | `bedrooms` | `bedrooms → bedrooms` | `infoGet('bedrooms')` | Yes | No | **PASS** | EAV key match ✓. Confirmed in DB. |
| Bathrooms | `bathrooms` | `$bathrooms` | — | `bathrooms` | `bathrooms → bathrooms` | `infoGet('bathrooms')` | Yes | No | **PASS** | EAV key match ✓. Confirmed in DB. |
| Desired Lease Length | `tenant_desired_lease_length` | `$tenant_desired_lease_length` | — | `tenant_desired_lease_length` | — | `infoGet('tenant_desired_lease_length')` | Yes | No | **PASS** | EAV key match ✓. Note: form also saves `desired_lease_length` (JSON multi-select array) separately; Ask AI reads the scalar `tenant_desired_lease_length` which is correct. |
| Property Items | `property_items` | `$property_items` | — | `property_items` (JSON) | — | `decodeJsonField(infoGet('property_items'))` | Yes | No | **PASS** | EAV JSON decode ✓ |
| Appliances | `appliances` | `$appliances` | — | `appliances` (JSON) | — | `decodeJsonField(infoGet('appliances'))` | Yes | No | **PASS** | EAV JSON decode ✓ |
| Property Condition | `condition_prop` | `$condition_prop` | — | `condition_prop` | — | `infoGet('condition_prop')` | Yes | No | **PASS** | EAV key match ✓ |
| Pet Information | `pet_information` | `$pet_information` | — | `pet_information` | — | `infoGet('pet_information')` | Yes | No | **PASS** | EAV key match ✓ |
| Parking Needed | `parking_needed` | `$parking_needed` | — | `parking_needed` | — | `infoGet('parking_needed')` | Yes | No | **PASS** | EAV key match ✓ |
| Utilities | `utilities` | `$utilities` | — | `utilities` | — | `infoGet('utilities')` | Yes | No | **PASS** | Form saves `utilities` at line 4247. EAV key match ✓ |
| Utility Preference | `utility_preference` | `$utility_preference` | — | `utility_preference` | — | `infoGet('utility_preference')` | Yes | No | **PASS** | EAV key match ✓ |
| Tenant Pays | `tenant_pays` | `$tenant_pays` | — | `tenant_pays` (JSON) | — | `decodeJsonField(infoGet('tenant_pays'))` | Yes | No | **PASS** | EAV JSON decode ✓ |
| Current Status | `current_status` | `$current_status` | — | `current_status` | — | `infoGet('current_status')` | Yes | No | **PASS** | EAV key match ✓ |
| Number of Occupants | `number_of_occupants` | `$number_of_occupants` | — | `number_of_occupants` | — | `infoGet('number_of_occupants')` | Yes | No | **PASS** | EAV key match ✓. Note: Tenant uses `number_of_occupants`; Landlord uses `number_occupant`. Different keys in different models. |
| Number of Units | `number_of_unit` | `$number_of_unit` | — | `number_of_unit` | — | `infoGet('number_of_unit')` | Yes | No | **PASS** | EAV key match ✓ |
| Listing Status | — | `$listing_status` | `is_approved` ✓ | — | — | `nativeGet('is_approved')` → ternary | Yes | No | **PASS** | Native column ✓ |
| City / State / County | varies | varies | — | `state`, `property_city`, etc. | — | `resolve()` | Yes | No | **PASS** | EAV keys present |
| Created At / Updated At | — | — | `created_at`, `updated_at` ✓ | — | — | direct attribute access | Yes | No | **PASS** | Native timestamps |

**Tenant PASS count: 18 | FAIL count: 0 | BLOCKED count: 0**

---

## 5. Compliance / Privacy Exclusions

The following fields are intentionally absent from Ask AI context. No fix required.

| Field | Reason |
|---|---|
| `first_name`, `last_name`, `phone_number`, `email` | PII — governance contract explicitly prohibits |
| `agent_brokerage`, `agent_license_number`, `agent_nar_member_id` | Internal agent-identity workflow fields |
| `pre_approval_amount` | Financial pre-approval specifics; not a factual property field |
| `draft_version`, `parent_draft_id`, `draft_payload_hash` | Internal draft-versioning system fields |
| `referral_percentage`, `referral_source_code` | Internal referral-system fields |
| `fees`, `enable`, `custom_services` | Internal compensation/service-catalog fields |

---

## 6. MLS Import Cross-Check Summary

`MlsFieldMap` (all four roles) maps import keys to the following EAV meta keys:

| MLS Source Key | Landed EAV Key | Ask AI Reads (Seller) | Ask AI Reads (Buyer) | Ask AI Reads (Landlord) | Ask AI Reads (Tenant) | Verdict |
|---|---|---|---|---|---|---|
| `price` | `maximum_budget` | `nativeGet('starting_price')` | `nativeGet('max_price')` | `infoGet('maximum_budget')` | `infoGet('maximum_budget')` | Seller/Buyer FAIL; Landlord/Tenant PASS |
| `heated_sqft` | `minimum_heated_square` | `nativeGet('heated_sqft')` | `nativeGet('sqft')` | `infoGet('minimum_heated_square')` | — | Seller/Buyer FAIL; Landlord PASS |
| `bedrooms` | `bedrooms` | `infoGet('bedrooms')` | `nativeGet('bedrooms')` | `infoGet('bedrooms')` | `infoGet('bedrooms')` | Buyer FAIL; others PASS |
| `bathrooms` | `bathrooms` | `infoGet('bathrooms')` | `nativeGet('bathrooms')` | `infoGet('bathrooms')` | `infoGet('bathrooms')` | Buyer FAIL; others PASS |
| `air_conditioning` | `air_conditioning` (JSON) | not read | not read | not read | not read | Not in factual fields for any type (FAQ only) |
| `heating_fuel` | `heating_and_fuel` (JSON) | not read | not read | not read | not read | Not in factual fields for any type (FAQ only) |
| `roof_type` | `roof_type` (JSON) | not read | not read | not read | not read | Not in factual fields for any type (FAQ only) |
| `sewer` | `sewer` (JSON) | not read | not read | not read | not read | Not in factual fields for any type (FAQ only) |

**Finding:** MLS-imported structural fields (`roof_type`, `heating_and_fuel`, `air_conditioning`, `sewer`) are saved as EAV meta but are not surfaced in `extractFactualFields()` for any type. These are FAQ-answer fields, not factual context fields — this is intentional per design.

---

## 7. DB Runtime Verification

Actual EAV meta records were queried for at least one saved record per type. Findings:

| Type | Listing ID Sampled | Key Storage Confirmed |
|---|---|---|
| Seller | ID 4 (hire-agent flow) | `workflow_type`, `service_type`, `listing_status`, `property_type`, `expiration_date` present ✓ |
| Buyer | ID 5 | `bathrooms`, `bedrooms`, `minimum_heated_square`, `condition_prop`, `maximum_budget` present ✓; native `address` accessible ✓ |
| Landlord | ID 5 | `annual_property_taxes`, `appliances`, `association_amenities`, `association_fee_amount`, `association_fee_frequency`, `additional_landlord_lease_terms`, `air_conditioning` present ✓ |
| Tenant | ID 133 | `bedrooms`, `bathrooms`, `condition_prop`, `property_type`, `property_items`, `tenant_desired_lease_length` (inferred) present ✓ |

**Note:** No Seller offer-listing (`workflow_type = offer_listing`) records with full property data were found in this environment. The Seller table sample is a hire-agent listing. The FAIL findings above are based on code inspection (schema + form `saveMeta` calls) and are reliable regardless of record availability.

---

## 8. Prioritized Fix Plan

### Group 1 — Seller Fixes (17 FAILs, 10 BLOCKEDs)

The root cause is a **single architectural error**: the entire Seller `extractFactualFields()` branch was written against the old `property_auctions` native-column schema, but `SellerAgentAuction` stores all property details in EAV (`seller_agent_auction_metas`). All `nativeGet()` calls in the Seller branch must be converted to `infoGet()`, and key names must be reconciled.

**Priority 1 — Data fields with exact key-name fixes (accessor swap only):**

| Output Key | Current (broken) | Correct fix |
|---|---|---|
| `year_built` | `nativeGet('year_built')` | `infoGet('year_built')` |
| `pet_restrictions` | `nativeGet('pet_restrictions')` | `infoGet('pet_restrictions')` |
| `flood_zone_code` | `nativeGet('flood_zone_code')` | `infoGet('flood_zone_code')` |
| `auction_length` | `nativeGet('auction_length')` | Already works (native ✓); no change |

**Priority 2 — Data fields with key-name AND accessor fixes:**

| Output Key | Current (broken) | Correct fix |
|---|---|---|
| `asking_price` | `nativeGet('starting_price')` | `infoGet('maximum_budget')` |
| `square_feet` | `nativeGet('heated_sqft')` | `infoGet('minimum_heated_square')` |
| `pool` | `nativeGet('pool')` | `infoGet('pool_needed')` |
| `pool_type` | `nativeGet('pool_type')` | `decodeJsonField(infoGet('pool_type'))` |
| `carport` | `nativeGet('carport')` | `infoGet('carport_needed')` |
| `garage` | `nativeGet('garage')` | `infoGet('garage_needed')` |
| `garage_spaces` | `nativeGet('garage_spaces')` | `infoGet('garage_parking_spaces')` |
| `hoa_association` | `nativeGet('hoa_association')` | `infoGet('has_hoa')` |
| `hoa_fee` | `nativeGet('hoa_fee')` | `infoGet('association_fee_amount')` |
| `hoa_payment_schedule` | `nativeGet('hoa_payment_schedule')` | `infoGet('association_fee_frequency')` |
| `pets_allowed` | `nativeGet('pets_allowed')` | `infoGet('pets')` |
| `number_of_pets_allowed` | `nativeGet('number_of_pets_allowed')` | `infoGet('number_of_pets')` |
| `max_pet_weight` | `nativeGet('max_pet_weight')` | `infoGet('weight_of_pets')` |
| `rental_restrictions` | `nativeGet('rental_restrictions')` | `infoGet('leasing_restrictions')` |
| `closing_date` | `nativeGet('closing_date')` | `infoGet('target_closing_date')` |
| `sold` | `nativeGet('sold')` | `nativeGet('is_sold')` |

**Priority 3 — Remove or replace BLOCKED phantom keys:**

| Output Key | Action |
|---|---|
| `buy_now_price` | Remove from output (field not collected by offer form) |
| `water_view` | Remove (not collected) |
| `water_extras` | Remove (not collected) |
| `hoa_fee_requirement` | Remove (not collected by offer form) |
| `condo_fee` | Remove (phantom from old schema) |
| `condo_fee_schedule` | Remove (phantom from old schema) |
| `rental_restrictions_description` | Remove (misspelled legacy column; not in seller_agent_auctions) |
| `is_in_flood_zone` | Remove or replace with `infoGet('flood_zone_code')` presence check |
| `lease_terms` | Remove (phantom from old schema) |
| `tenant_pays` | Remove (not a Seller field; belongs to Landlord only) |
| `landlord_pays` | Remove (not a Seller field) |
| `mls_id` | Remove (not collected by offer form) |
| `showing_instructions` | Remove from Seller branch (only valid for hire-agent workflow, already present) |

---

### Group 2 — Buyer Fixes (17 FAILs, 4 BLOCKEDs)

Same root cause as Seller: all `nativeGet()` calls must be converted to `infoGet()` and key names reconciled.

**Priority 1 — Accessor + key name fixes:**

| Output Key | Current (broken) | Correct fix |
|---|---|---|
| `description` | `nativeGet('description')` | `nativeGet('additional_details')` |
| `max_price` | `nativeGet('max_price')` | `infoGet('maximum_budget')` |
| `bedrooms` | `nativeGet('bedrooms')` | `infoGet('bedrooms')` |
| `bathrooms` | `nativeGet('bathrooms')` | `infoGet('bathrooms')` |
| `square_feet` | `nativeGet('sqft')` | `infoGet('minimum_heated_square')` |
| `pool` | `nativeGet('pool')` | `infoGet('pool_needed')` |
| `carport` | `nativeGet('carport')` | `infoGet('carport_needed')` |
| `garage` | `nativeGet('garage')` | `infoGet('garage_needed')` |
| `garage_spaces` | `nativeGet('garage_spaces')` | `infoGet('garage_parking_spaces')` |
| `hoa_acceptable` | `nativeGet('hoa')` | `infoGet('hoa_acceptance')` |
| `max_hoa_fee` | `nativeGet('max_hoa_fee')` | `infoGet('hoa_max_monthly_fee')` |
| `pets_allowed` | `nativeGet('pets_allowed')` | `infoGet('pets')` |
| `pets_detail` | `nativeGet('pets_detail')` | `infoGet('type_of_pets')` |
| `pets_breed` | `nativeGet('pets_breed')` | `infoGet('breed_of_pets')` |
| `pets_weight` | `nativeGet('pets_weight')` | `infoGet('weight_of_pets')` |
| `loan_pre_approved` | `nativeGet('loan_pre_approved')` | `infoGet('pre_approved')` |
| `inspection_period` | `nativeGet('inspection_period')` | `infoGet('inspection_period_days')` |
| `financing_type` | `resolveFinancingType(nativeGet('financing_id'))` | `decodeJsonField(infoGet('offered_financing'))` |

**Priority 2 — Remove BLOCKED phantom keys:**

| Output Key | Action |
|---|---|
| `water_view` | Remove (not collected by Buyer form) |
| `hoa_fee_requirement` | Remove (not collected) |
| `closing_days` | Remove or replace with `infoGet('target_closing_date')` |
| `contingencies` | Remove or replace with individual keys: `infoGet('inspection_contingency_buyer')`, `infoGet('appraisal_contingency_buyer')`, `infoGet('financing_contingency_buyer')` |

---

### Group 3 — Landlord Fixes (1 FAIL)

Minimal work needed. Only one key mismatch:

| Output Key | Current (broken) | Correct fix |
|---|---|---|
| `number_of_occupants` | `infoGet('number_of_occupants_allowed')` | `infoGet('number_occupant')` |

---

### Group 4 — Tenant Fixes (0 FAILs)

No fixes required. All Tenant factual fields extract correctly.

---

### Group 5 — Shared / Structural Improvements

1. **Seller and Buyer branches need full `nativeGet → infoGet` migration** (Groups 1 & 2 above). Consider extracting a shared `$sharedPropertyFields()` helper to avoid future divergence.

2. **`decodeJsonField()` coverage:** Several Seller array fields (e.g., `pool_type`) will need the JSON decoder wrapper after the accessor fix — audit each JSON-stored EAV key and apply `decodeJsonField()` accordingly.

3. **Guard for `infoGet` returning `false`:** `info()` returns `false` (not null) on miss. The `$infoGet` closure correctly coerces `false → null`, so this is already handled — no change needed.

4. **Seller `hoa_fee_requirement` phantom key** is almost certainly a leftover from the old `property_auctions` schema where HOA data had a separate fee-requirement field. Recommend removing entirely from the Seller branch and confirming the old data-model field no longer applies.

5. **Add integration test coverage** for Seller and Buyer factual field extraction once fixes in Groups 1–2 are applied (currently no failing tests because the test harness mocks the listing model attributes).

---

## 9. Validation Results

### `php artisan test --filter AskAi`

```
Tests: 1672 passed
Time:  12.31s
```

All 1672 Ask AI tests pass. **No regressions introduced by this audit** (audit is read-only; no code changes made).

### `php artisan view:cache`

```
Compiled views cleared!
Blade templates cached successfully!
```

View compilation completes without errors.

---

*Audit prepared 2026-06-09. Out of scope: extraction fixes (those are task #2392B). No code was modified during this audit.*
