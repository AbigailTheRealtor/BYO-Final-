# MLS-to-Website Field Mapping Audit

**Phase A — Audit Document Only (no code changes)**
**Date:** 2026-06-09
**Auditor:** Agent — cross-referenced `MlsListingImportService::parseFields()`, `MlsFieldMap::forRole()`, `MlsNormalizer::normalize()`, all four Livewire component public properties, and all four sets of Create/Edit tab blade files.

---

## How to Read This Document

### Columns

| Column | Meaning |
|---|---|
| **MLS Field** | Label as it appears on the Stellar MLS form |
| **Parsed Key** | Canonical key emitted by `MlsListingImportService::parseFields()`; `—` = no parser branch |
| **Website Roles** | Which roles have (or should have) a mapping: S=Seller, B=Buyer, L=Landlord, T=Tenant |
| **Website Field / Property** | Target Livewire public property name on the component (after `*` strip) |
| **DB Column / Meta Key** | How the field is persisted: DB column or EAV `saveMeta` key |
| **Current Mapping** | What `MlsFieldMap::forRole()` currently specifies |
| **Correct Mapping** | What the mapping should be (same as Current if no fix needed) |
| **Status** | Classification code (see legend below) |
| **Notes / Phase B Fix** | Context and exact fix instructions for any non-`mapped_correctly` row |

### Status Legend

| Status | Meaning |
|---|---|
| `mapped_correctly` | Parser branch ✓ · Field map entry ✓ · Livewire property ✓ · Blade `wire:model` ✓ |
| `mapped_wrong` | A mapping exists but points to the wrong property, uses the wrong type marker (`*`), or applies normalizer incorrectly |
| `missing_from_parser` | MLS field has no parser branch in `parseFields()` |
| `missing_from_field_map` | Parser emits the key but no `MlsFieldMap` entry exists for one or more applicable roles |
| `missing_from_website` | Parsed and field-mapped (or would be), but no Livewire property or blade binding exists |
| `intentionally_excluded` | Deliberately omitted — semantically wrong, property absent by design, or owner-disclosure rule |
| `needs_new_field` | Field has no app equivalent anywhere; a new DB column + Livewire property + blade input would be required |

---

## Section 1 — Address / Location

| MLS Field | Parsed Key | Website Roles | Website Field / Property | DB Column / Meta Key | Current Mapping | Correct Mapping | Status | Notes / Phase B Fix |
|---|---|---|---|---|---|---|---|---|
| Street Address | `address` | S, B, L, T | `address` | DB col `address` | S:`address`, B:`address`, L:`address`, T: not mapped | All four roles | `missing_from_field_map` | TenantOfferListing has `public $address` and blade has `wire:model="address"`, but the Tenant field map omits it. Phase B: add `'address' => 'address'` to tenant map. |
| City | `city` | S, B, L, T | `property_city` | DB col `property_city` | S:`property_city`, B:`property_city`, L:`property_city`, T: not mapped | All four roles | `missing_from_field_map` | TenantOfferListing has `public $property_city` and blade has the binding, but Tenant field map omits city. Same pattern for state/zip/county (see below). Phase B: add all four address fields to tenant map. |
| State | `state` | S, B, L, T | `property_state` | DB col `property_state` | S:`property_state`, B:`property_state`, L:`property_state`, T: not mapped | All four roles | `missing_from_field_map` | Same as City row above. |
| Zip Code | `zip` | S, B, L, T | `property_zip` | DB col `property_zip` | S:`property_zip`, B:`property_zip`, L:`property_zip`, T: not mapped | All four roles | `missing_from_field_map` | Same as City row above. |
| County | `county` | S, B, L, T | `property_county` | DB col `property_county` | S:`property_county`, B:`property_county`, L:`property_county`, T: not mapped | All four roles | `missing_from_field_map` | Same as City row above. |

**Buyer address note:** BuyerOfferListing has `property_city`, `property_state`, `property_zip`, `property_county` as public properties, and the Buyer field map maps to them correctly, but **no `wire:model` binding for any of these exists in the buyer blade tab files**. The CoverageReporter would show PropExists=Y, FormField=N → Safe=N for all buyer address fields. Phase B should confirm whether buyer address fields are intentionally form-hidden or whether blade bindings are missing.

---

## Section 2 — Listing Information (MLS # / Price)

| MLS Field | Parsed Key | Website Roles | Website Field / Property | DB Column / Meta Key | Current Mapping | Correct Mapping | Status | Notes / Phase B Fix |
|---|---|---|---|---|---|---|---|---|
| MLS # | `mls_number` | None | — | — | Parsed, not mapped | Intentionally no mapping | `intentionally_excluded` | No `mls_number` Livewire property exists on any component. Parsed as a signal but never imported. Documented in Rejected Mapping Candidates section of coverage report. |
| List Price (Residential) | `price` | S, B | `maximum_budget` | DB col `maximum_budget` | S:`maximum_budget`, B:`maximum_budget` | Same | `mapped_correctly` | Seller: maps to "Desired Sale Price" on Sale Terms tab — NOT `purchase_price` (seller-financing sub-field). Buyer: maps to budget cap. |
| Monthly Rent (Rental) | `price` | L | `desired_rental_amount` | DB col `desired_rental_amount` | L:`desired_rental_amount` | Same | `mapped_correctly` | Price canonical key is shared; for Rental MLS forms the parser branch `Monthly Rent / Rent Price / Rental Rate` overwrites the sale-price match with the same key. |
| List Price / Asking Price | `price` | T | — | — | Tenant: not mapped | Intentionally excluded | `intentionally_excluded` | MLS listing price = landlord's asking rent; importing it as a tenant's desired amount would be semantically wrong. Documented in Rejected Mapping Candidates. |

---

## Section 3 — Beds / Baths

| MLS Field | Parsed Key | Website Roles | Website Field / Property | DB Column / Meta Key | Current Mapping | Correct Mapping | Status | Notes / Phase B Fix |
|---|---|---|---|---|---|---|---|---|
| Bedrooms | `bedrooms` | S, B, L, T | `bedrooms` | DB col `bedrooms` | S:`bedrooms`, B:`bedrooms`, L:`bedrooms`, T:`bedrooms` | Same | `mapped_correctly` | |
| Bathrooms | `bathrooms` | S, B, L, T | `bathrooms` | DB col `bathrooms` | S:`bathrooms`, B:`bathrooms`, L:`bathrooms`, T:`bathrooms` | Same | `mapped_correctly` | |

---

## Section 4 — Heated Sq Ft / Total Sq Ft

| MLS Field | Parsed Key | Website Roles | Website Field / Property | DB Column / Meta Key | Current Mapping | Correct Mapping | Status | Notes / Phase B Fix |
|---|---|---|---|---|---|---|---|---|
| Heated Sq Ft | `heated_sqft` | S, B, L, T | `minimum_heated_square` | DB col `minimum_heated_square` | S:`minimum_heated_square`, B:`minimum_heated_square`, L:`minimum_heated_square`, T:`minimum_heated_square` | Same | `mapped_correctly` | |
| Sq Ft Heated Source | `sqft_heated_source` | S, L | `sqft_heated_source` | Meta key `sqft_heated_source` | S:`sqft_heated_source`, L:`sqft_heated_source` | Add T | `missing_from_field_map` | TenantOfferListing has `public $sqft_heated_source = ''` (line 102) and the tenant blade binds to it. The tenant field map omits it. Phase B: add `'sqft_heated_source' => 'sqft_heated_source'` to the tenant map. Buyer component does not appear to have this property — leave buyer excluded. |

---

## Section 5 — Lot Size / Acreage / Dimensions

| MLS Field | Parsed Key | Website Roles | Website Field / Property | DB Column / Meta Key | Current Mapping | Correct Mapping | Status | Notes / Phase B Fix |
|---|---|---|---|---|---|---|---|---|
| Lot Dimensions | `lot_dimensions` | S | `lot_dimensions` | DB col `lot_dimensions` | S:`lot_dimensions` | Same | `mapped_correctly` | Landlord blade has a `lot_dimensions` binding and LandlordOfferListing has `public $lot_dimensions` — but it is not in the Landlord field map. Whether landlord rental listings need lot dimensions is a product decision; classify as intentional omission pending review. |
| Lot Acreage | `lot_size_acres` | S | `total_acreage` | DB col `total_acreage` | S:`total_acreage` | Same for S; consider L | `missing_from_field_map` | Parser emits `lot_size_acres`. Seller correctly maps to `total_acreage`. LandlordOfferListing has `public $total_acreage = ''` (line 187) and the landlord blade binds to it, but the Landlord field map omits `lot_size_acres`. Phase B: add `'lot_size_acres' => 'total_acreage'` to the Landlord map (or confirm intentional exclusion). Buyer and Tenant: no `total_acreage` property — leave excluded. |
| Lot Size (Sq Ft) | `lot_size_sqft` | None | — | — | Not in any field map | Needs evaluation | `missing_from_field_map` | Parser correctly emits `lot_size_sqft` but NO role's field map includes it. `MlsFieldMap::fieldLabels()` lists it as `'lot_size_sqft' => 'Lot Size (Sq Ft)'`, confirming it was planned. No Livewire property named `lot_size_sqft` was found on any component. Phase B must decide: (a) map to `total_acreage` as a fallback when acreage is absent, (b) add a new `lot_size_sqft` property, or (c) intentionally exclude. |

---

## Section 6 — Year Built

| MLS Field | Parsed Key | Website Roles | Website Field / Property | DB Column / Meta Key | Current Mapping | Correct Mapping | Status | Notes / Phase B Fix |
|---|---|---|---|---|---|---|---|---|
| Year Built | `year_built` | S, L | `year_built` | Meta key `year_built` | S:`year_built`, L:`year_built` | Same | `mapped_correctly` | BuyerOfferListing has no `year_built` property — intentionally excluded (noted in Rejected Mapping Candidates). Tenant: same exclusion. |

---

## Section 7 — Pool / Garage / Carport / Parking

| MLS Field | Parsed Key | Website Roles | Website Field / Property | DB Column / Meta Key | Current Mapping | Correct Mapping | Status | Notes / Phase B Fix |
|---|---|---|---|---|---|---|---|---|
| Pool | `pool` | S, B, L, T | `pool_needed` | DB col `pool_needed` | S:`pool_needed`, B:`pool_needed`, L:`pool_needed`, T:`pool_needed` | Same | `mapped_correctly` | Normalizer coerces Yes/Y/No/N to `"yes"`/`"no"`; pass-through for pool-type strings (e.g. "In Ground", "Heated"). |
| Garage | `garage` | S, B, L, T | `garage_needed` | DB col `garage_needed` | S:`garage_needed`, B:`garage_needed`, L:`garage_needed`, T:`garage_needed` | Same | `mapped_correctly` | Same boolean normalizer behavior. |
| Carport | `carport` | S, B, L, T | `carport_needed` | DB col `carport_needed` | S:`carport_needed`, B:`carport_needed`, L:`carport_needed`, T:`carport_needed` | Same | `mapped_correctly` | Same boolean normalizer behavior. |

---

## Section 8 — HOA / Condo Fees

| MLS Field | Parsed Key | Website Roles | Website Field / Property | DB Column / Meta Key | Current Mapping | Correct Mapping | Status | Notes / Phase B Fix |
|---|---|---|---|---|---|---|---|---|
| Association Y/N | `has_hoa` | S, L | `has_hoa` | Meta key `has_hoa` | S:`has_hoa`, L:`has_hoa` | Same | `mapped_correctly` | Normalizer coerces Yes/Y/TRUE→`"yes"`, No/N/FALSE→`"no"`. Buyer/Tenant excluded — owner-disclosure rule. |
| Association Name | `association_name` | S, L | `association_name` | Meta key `association_name` | S:`association_name`, L:`association_name` | Same | `mapped_correctly` | |
| Association Fee | `association_fee_amount` | S, L | `association_fee_amount` | Meta key `association_fee_amount` | S:`association_fee_amount`, L:`association_fee_amount` | Same | `mapped_correctly` | |
| Association Fee Frequency | `association_fee_frequency` | S, L | `association_fee_frequency` | Meta key `association_fee_frequency` | S:`association_fee_frequency`, L:`association_fee_frequency` | Same | `mapped_correctly` | Normalizer maps Monthly/Quarterly/Annually/Semi-Annually/One-Time to lowercase slugs. |

---

## Section 9 — Taxes / Tax Year / Parcel ID

| MLS Field | Parsed Key | Website Roles | Website Field / Property | DB Column / Meta Key | Current Mapping | Correct Mapping | Status | Notes / Phase B Fix |
|---|---|---|---|---|---|---|---|---|
| Tax ID / Parcel ID | `tax_id` | S, L | `parcel_id` | Meta key `parcel_id` | S:`parcel_id`, L:`parcel_id` | Same | `mapped_correctly` | Canonical key is `tax_id`; target property is `parcel_id`. The name difference is intentional and documented in Rejected Mapping Candidates (using `tax_id` as the property name would silently skip the form field). Buyer/Tenant excluded — owner-disclosure rule. |
| Tax Year | `tax_year` | S, L | `tax_year` | Meta key `tax_year` | S:`tax_year`, L:`tax_year` | Same | `mapped_correctly` | |
| Annual Property Taxes | `annual_taxes` | S, L | `annual_property_taxes` | Meta key `annual_property_taxes` | S:`annual_property_taxes`, L:`annual_property_taxes` | Same | `mapped_correctly` | Canonical key is `annual_taxes`; property name includes "property". Correct. |
| Additional Parcels Y/N | `additional_parcels` | S, L | `additional_parcels` | Meta key `additional_parcels` | S:`additional_parcels`, L:`additional_parcels` | Same | `mapped_correctly` | Normalizer coerces Yes/Y→`"yes"`, No/N→`"no"`. Strips MLS "Y/N:" prefix. |
| Total Number of Parcels | `total_parcel_count` | S, L | `total_parcel_count` | Meta key `total_parcel_count` | S:`total_parcel_count`, L:`total_parcel_count` | Same | `mapped_correctly` | |
| Legal Description | `legal_description` | S, L | `legal_description` | Meta key `legal_description` | S:`legal_description`, L:`legal_description` | Same | `mapped_correctly` | |

---

## Section 10 — Flood Zone

| MLS Field | Parsed Key | Website Roles | Website Field / Property | DB Column / Meta Key | Current Mapping | Correct Mapping | Status | Notes / Phase B Fix |
|---|---|---|---|---|---|---|---|---|
| Flood Zone Code | `flood_zone_code` | S, L | `flood_zone_code` | Meta key `flood_zone_code` | S:`flood_zone_code`, L:`flood_zone_code` | Same | `mapped_correctly` | Normalizer uppercases zone codes (X, AE, A, AH, AO, VE, V, D). |
| Flood Zone Date | `flood_zone_date` | None | — | — | Not in any field map | Needs new field | `needs_new_field` | Parser correctly extracts `flood_zone_date` and `MlsFieldMap::fieldLabels()` lists it, but **no `flood_zone_date` property exists on SellerOfferListing or LandlordOfferListing, and no blade `wire:model` binding exists in either tab directory.** Full stack of work required before a field map entry can be added: (1) add `flood_zone_date` meta key to the model's `saveMeta`/`loadDraft` logic; (2) add `public $flood_zone_date = ''` to both components; (3) add a blade input in the Flood Zone tab; (4) then add the field map entries. **Deferred — not in Phase B scope without product sign-off on adding the new field.** |
| Flood Zone Panel | `flood_zone_panel` | S, L | `flood_zone_panel` | Meta key `flood_zone_panel` | S:`flood_zone_panel`, L:`flood_zone_panel` | Same | `mapped_correctly` | |
| Flood Insurance Required | `flood_insurance_required` | S, L | `flood_insurance_required` | Meta key `flood_insurance_required` | S:`flood_insurance_required`, L:`flood_insurance_required` | Same | `mapped_correctly` | **Note:** the parser at line 516 calls `MlsNormalizer::normalize('has_hoa', $v)` instead of the more explicit `MlsNormalizer::normalize('flood_insurance_required', $v)`. Functionally equivalent because `has_hoa` routes to `normalizeBoolean`, which produces the correct `"yes"`/`"no"` output. However, it is misleading. Phase B should add a `flood_insurance_required` case to `MlsNormalizer::normalize()` (routing to `normalizeBoolean`) and update the parser call. |

---

## Section 11 — Utilities

| MLS Field | Parsed Key | Website Roles | Website Field / Property | DB Column / Meta Key | Current Mapping | Correct Mapping | Status | Notes / Phase B Fix |
|---|---|---|---|---|---|---|---|---|
| Water (utility) | `water` | S, L | `water` (array) | Meta key `water` | S:`*water`, L:`*water` | Same | `mapped_correctly` | Both SellerOfferListing and LandlordOfferListing have `public $water = []`. The `*` prefix correctly signals array splitting. |
| Sewer | `sewer` | S, L | `sewer` (array) | Meta key `sewer` | S:`*sewer`, L:`*sewer` | Same | `mapped_correctly` | Same array pattern as Water. |
| Utilities | `utilities` | S, L | See Notes | Meta key `utilities` / `property_utilities` | S:`*utilities`, L:`utilities` (no `*`) | L should be `*property_utilities` | `mapped_wrong` | **Seller:** `SellerOfferListing` has `public $utilities = []` (array). Field map correctly uses `*utilities`. ✓ **Landlord:** `LandlordOfferListing` has TWO properties: `public $utilities = ''` (string, line 503) and `public $property_utilities = []` (array, line 601). The field map maps to `utilities` (no `*`, targets the string property). But the blade uses `wire:model="utilities"` and `wire:model="other_property_utilities"` — the multi-select utilities for Landlord is `property_utilities`, not `utilities`. Phase B fix: change Landlord map entry from `'utilities' => 'utilities'` to `'utilities' => '*property_utilities'` and confirm blade binding. |

---

## Section 12 — Appliances

| MLS Field | Parsed Key | Website Roles | Website Field / Property | DB Column / Meta Key | Current Mapping | Correct Mapping | Status | Notes / Phase B Fix |
|---|---|---|---|---|---|---|---|---|
| Appliances | `appliances` | S, L | `appliances` (array) | Meta key `appliances` | S:`*appliances`, L:`*appliances` | Same | `mapped_correctly` | Both components have `public $appliances = []`. Array split on comma. Buyer and Tenant not mapped — acceptable for search-criteria forms that don't import property details. |

---

## Section 13 — Heating / Cooling

| MLS Field | Parsed Key | Website Roles | Website Field / Property | DB Column / Meta Key | Current Mapping | Correct Mapping | Status | Notes / Phase B Fix |
|---|---|---|---|---|---|---|---|---|
| Air Conditioning | `air_conditioning` | S, L | `air_conditioning` (array) | Meta key `air_conditioning` | S:`*air_conditioning`, L:`*air_conditioning` | Same | `mapped_correctly` | Both components have `public $air_conditioning = []`. |
| Heating and Fuel | `heating_fuel` | S, L | S:`heating_and_fuel`, L:`heating_fuel` | Meta key (same as property name) | S:`*heating_and_fuel`, L:`*heating_fuel` | Same | `mapped_correctly` | Parser emits `heating_fuel` for the combined "Heating and Fuel:" / "Heating & Fuel:" MLS label. Seller property is `heating_and_fuel` (array); Landlord property is `heating_fuel` (array). Both verified to exist. |
| Heating (simple) | `heating` | None | — | — | Not in any field map | S and L | `missing_from_field_map` | Parser emits a **separate** `heating` key for plain "Heating:" labels (no "and Fuel" suffix). Neither Seller nor Landlord field map includes a `heating` entry. If an MLS listing has "Heating: Central" without the "and Fuel" suffix, the value is parsed but silently dropped. Phase B fix options: (a) merge `heating` into `heating_fuel` in the parser so both patterns write to the same key; or (b) add `'heating' => '*heating_and_fuel'` (Seller) and `'heating' => '*heating_fuel'` (Landlord) to the field maps. |

---

## Section 14 — Sewer / Water

*(Covered in Section 11 — Utilities. No additional rows.)*

---

## Section 15 — Construction / Roof

| MLS Field | Parsed Key | Website Roles | Website Field / Property | DB Column / Meta Key | Current Mapping | Correct Mapping | Status | Notes / Phase B Fix |
|---|---|---|---|---|---|---|---|---|
| Roof Type | `roof_type` | S | `roof_type` (array) | Meta key `roof_type` | S:`*roof_type` | Same | `mapped_correctly` | SellerOfferListing has `public $roof_type = []`. Not mapped for Landlord, Buyer, Tenant — acceptable; Rental and buyer-search forms don't expose this field. |
| Exterior Construction | `exterior_construction` | S | `exterior_construction` (array) | Meta key `exterior_construction` | S:`*exterior_construction` | Same | `mapped_correctly` | SellerOfferListing has `public $exterior_construction = []`. |
| Foundation | `foundation` | S | `foundation` (array) | Meta key `foundation` | S:`*foundation` | Same | `mapped_correctly` | SellerOfferListing has `public $foundation = []`. |
| Zoning | `zoning` | S, L | `zoning` | Meta key `zoning` | S:`zoning`, L:`zoning` | Same | `mapped_correctly` | |

---

## Section 16 — Public Remarks / Description

| MLS Field | Parsed Key | Website Roles | Website Field / Property | DB Column / Meta Key | Current Mapping | Correct Mapping | Status | Notes / Phase B Fix |
|---|---|---|---|---|---|---|---|---|
| Public Remarks | `description` | S, B, L, T | `additional_details` | DB col `additional_details` | S:`additional_details`, B:`additional_details`, L:`additional_details`, T:`additional_details` | Same | `mapped_correctly` | Parser matches "Public Remarks (English Only):", "Public Remarks:", "Remarks:", and "Description:" variants. |
| Directions | `directions` | None | — | — | Not in any field map | — | `missing_from_website` | Parser correctly emits `directions`. `MlsFieldMap::fieldLabels()` lists it. No Livewire property named `directions` exists on any component. Phase B must decide: (a) add a new `directions` property + meta key + blade input on Seller and Landlord forms; or (b) intentionally exclude. |

---

## Section 17 — Waterfront / Water View

| MLS Field | Parsed Key | Website Roles | Website Field / Property | DB Column / Meta Key | Current Mapping | Correct Mapping | Status | Notes / Phase B Fix |
|---|---|---|---|---|---|---|---|---|
| Waterfront Y/N | `waterfront` | None | — | — | Not in any field map | S, L | `missing_from_website` | Parser correctly emits `waterfront`. `MlsFieldMap::fieldLabels()` lists it. No `waterfront` Livewire property found on any component. Phase B must determine if a `waterfront` meta key / form field exists on Seller or Landlord forms, then add property + field map entry, or confirm intentional exclusion. |
| Water Access | `water_access` | None | — | — | Not in any field map | S, L | `missing_from_website` | Same situation as Waterfront. Parser emits key; no component property or field map entry. Phase B: same resolution path. |
| Water View | `water_view` | None | — | — | Not in any field map | S, L | `missing_from_website` | Same situation as Waterfront and Water Access. |

---

## Section 18 — Lease / Rental Terms

| MLS Field | Parsed Key | Website Roles | Website Field / Property | DB Column / Meta Key | Current Mapping | Correct Mapping | Status | Notes / Phase B Fix |
|---|---|---|---|---|---|---|---|---|
| Monthly Rent | `price` | L | `desired_rental_amount` | DB col `desired_rental_amount` | L:`desired_rental_amount` | Same | `mapped_correctly` | See Section 2. |
| Available Date | `available_date` | L | `available_date` | Meta key `available_date` | L:`available_date` | Same | `mapped_correctly` | |
| Minimum Security Deposit | `minimum_security_deposit` | L | `security_deposit_amount` | Meta key `security_deposit_amount` | L:`security_deposit_amount` | Same | `mapped_correctly` | Canonical key includes "minimum_" prefix; target property is `security_deposit_amount`. |
| Lease Amount Frequency | `lease_amount_frequency` | L | `lease_amount_frequency` | Meta key `lease_amount_frequency` | L:`lease_amount_frequency` | Same | `mapped_correctly` | Normalizer maps MLS frequency strings (Monthly, Annually, Month to Month, etc.) to lowercase slugs. |
| Terms of Lease | `terms_of_lease` | L | `terms_of_lease` (array) | Meta key `terms_of_lease` | L:`*terms_of_lease` | Same | `mapped_correctly` | LandlordOfferListing has `public $terms_of_lease = []`. |
| Tenant Pays | `tenant_pays` | L | `tenant_pays` (array) | Meta key `tenant_pays` | L:`*tenant_pays` | Same | `mapped_correctly` | LandlordOfferListing has `public $tenant_pays = []`. |
| Application Fee | `application_fee` | None | — | — | Not mapped | Intentionally excluded | `intentionally_excluded` | No `application_fee` property on LandlordOfferListing. Parsed but not imported. Documented in Rejected Mapping Candidates. |
| Rent Includes | `rent_includes` | L, T | `rent_includes` (array) | Meta key `rent_includes` | L:`*rent_includes`, T:`*rent_includes` | Same | `mapped_correctly` | Both LandlordOfferListing and TenantOfferListing have `public $rent_includes = []`. |
| Furnished / Furnishings | `furnished` | S, B, L, T | `tenant_require` | DB col `tenant_require` | S:`tenant_require`, B:`tenant_require`, L:`tenant_require`, T:`tenant_require` | Same | `mapped_correctly` | Normalizer maps MLS furnishing values (Furnished / Negotiable / Partial / Turnkey / Unfurnished) to lowercase. |

---

## Section 19 — Disclosures (CDD / Special Assessments)

| MLS Field | Parsed Key | Website Roles | Website Field / Property | DB Column / Meta Key | Current Mapping | Correct Mapping | Status | Notes / Phase B Fix |
|---|---|---|---|---|---|---|---|---|
| CDD Y/N | `has_cdd` | S, L | `has_cdd` | Meta key `has_cdd` | S:`has_cdd`, L:`has_cdd` | Same | `mapped_correctly` | Normalizer coerces Yes/Y/TRUE→`"yes"`, No/N/FALSE→`"no"`. |
| CDD Annual Amount | `annual_cdd_fee` | S, L | `annual_cdd_fee` | Meta key `annual_cdd_fee` | S:`annual_cdd_fee`, L:`annual_cdd_fee` | Same | `mapped_correctly` | |
| Special Assessments Y/N | `has_special_assessments` | S, L | `has_special_assessments` | Meta key `has_special_assessments` | S:`has_special_assessments`, L:`has_special_assessments` | Same | `mapped_correctly` | **Note:** Parser at line 525 calls `MlsNormalizer::normalize('has_hoa', $v)` for this field. Functionally correct (routes to `normalizeBoolean`), but misleadingly uses a different field name. Phase B should add `'has_special_assessments'` as a named case in `MlsNormalizer::normalize()` routing to `normalizeBoolean`. |
| Special Assessment Amount | `special_assessment_amount` | S, L | `special_assessment_amount` | Meta key `special_assessment_amount` | S:`special_assessment_amount`, L:`special_assessment_amount` | Same | `mapped_correctly` | |
| Special Assessment Description | `special_assessment_description` | S, L | `special_assessment_description` | Meta key `special_assessment_description` | S:`special_assessment_description`, L:`special_assessment_description` | Same | `mapped_correctly` | |

---

## Section 20 — Interior Features

| MLS Field | Parsed Key | Website Roles | Website Field / Property | DB Column / Meta Key | Current Mapping | Correct Mapping | Status | Notes / Phase B Fix |
|---|---|---|---|---|---|---|---|---|
| Interior Features | `interior_features` | None | — | — | Not in any field map | Undetermined | `missing_from_website` | Parser correctly emits `interior_features` (from "Interior Features:" label). `MlsFieldMap::fieldLabels()` lists it. No Livewire property named `interior_features` exists on any component; no field map entry. Phase B must determine: (a) if there is an equivalent property (e.g. a free-text "interior features" note field), add a mapping; or (b) intentionally exclude. |

---

## Section 21 — MLS Forms with No Website Equivalent (intentionally excluded)

The following MLS form types have fields with no parser branch and no website field. All are classified `missing_from_parser` / `needs_new_field` and are **out of scope for Phases B–D** unless a dedicated form type is added to the platform.

| MLS Form | Section | MLS Field | Status | Notes |
|---|---|---|---|---|
| Rental | RENTAL / LEASE | Pets Allowed | `missing_from_parser` | No parser branch; no app field. |
| Rental | RENTAL / LEASE | Minimum Lease (Months) | `missing_from_parser` | No parser branch; no app field. |
| Vacant Land | PROPERTY DETAILS | Lot Features | `missing_from_parser` | Vacant Land specific; no app equivalent. |
| Vacant Land | PROPERTY DETAILS | Road Surface Type | `missing_from_parser` | Vacant Land specific; no app equivalent. |
| Vacant Land | PROPERTY DETAILS | Utilities Available | `missing_from_parser` | Vacant Land specific; no app equivalent. |
| Vacant Land | PROPERTY DETAILS | Topography | `missing_from_parser` | Vacant Land specific; no app equivalent. |
| Income | PROPERTY DETAILS | Number of Units | `missing_from_parser` | Multi-family specific; no app equivalent. |
| Income | PROPERTY DETAILS | Unit Types | `missing_from_parser` | Multi-family specific; no app equivalent. |
| Income | FINANCIAL | Net Operating Income (NOI) | `missing_from_parser` | Income / commercial specific. |
| Income | FINANCIAL | Annual Gross Income | `missing_from_parser` | Income specific. |
| Income | FINANCIAL | Annual Expenses | `missing_from_parser` | Income specific. |
| Income | FINANCIAL | Cap Rate | `missing_from_parser` | Income / commercial specific. |
| Commercial Sale | BUILDING DETAILS | Building Size (Sq Ft) | `missing_from_parser` | Commercial specific. |
| Commercial Sale | BUILDING DETAILS | Number of Bays | `missing_from_parser` | Commercial specific. |
| Commercial Sale | BUILDING DETAILS | Number of Dock Doors | `missing_from_parser` | Commercial specific. |
| Commercial Sale | BUILDING DETAILS | Ceiling Height (Ft) | `missing_from_parser` | Commercial specific. |
| Commercial Sale | BUILDING DETAILS | Office Area (Sq Ft) | `missing_from_parser` | Commercial specific. |
| Commercial Sale | BUILDING DETAILS | Parking Spaces | `missing_from_parser` | Commercial specific. |
| Commercial Sale | FINANCIAL | Net Operating Income (NOI) | `missing_from_parser` | Commercial specific. |
| Commercial Sale | FINANCIAL | Cap Rate | `missing_from_parser` | Commercial specific. |
| Commercial Lease | BUILDING DETAILS | Building Size (Sq Ft) | `missing_from_parser` | Commercial specific. |
| Commercial Lease | BUILDING DETAILS | Office Area (Sq Ft) | `missing_from_parser` | Commercial specific. |
| Commercial Lease | BUILDING DETAILS | Parking Spaces | `missing_from_parser` | Commercial specific. |
| Commercial Lease | RENTAL / LEASE | Lease Rate Type (NNN/Gross) | `missing_from_parser` | Commercial specific. |
| Commercial Lease | RENTAL / LEASE | Minimum Lease Term | `missing_from_parser` | Commercial specific. |
| Commercial Lease | RENTAL / LEASE | Rent Rate (per Sq Ft) | `missing_from_parser` | Commercial specific. |
| Commercial Lease | RENTAL / LEASE | Build-Out Allowance | `missing_from_parser` | Commercial specific. |
| Business Opportunity | BUSINESS DETAILS | Business Type | `missing_from_parser` | Business specific. |
| Business Opportunity | BUSINESS DETAILS | Annual Revenue | `missing_from_parser` | Business specific. |
| Business Opportunity | BUSINESS DETAILS | Annual Net Income | `missing_from_parser` | Business specific. |
| Business Opportunity | BUSINESS DETAILS | Inventory Included Y/N | `missing_from_parser` | Business specific. |
| Business Opportunity | BUSINESS DETAILS | Number of Employees | `missing_from_parser` | Business specific. |
| Business Opportunity | BUSINESS DETAILS | Seller Financing Y/N | `missing_from_parser` | Business specific. |
| Business Opportunity | BUSINESS DETAILS | Lease Type | `missing_from_parser` | Business specific. |

---

## Summary of Issues for Phase B

The following table lists all rows that require code changes, ordered by priority.

**Phase B scope column key:**
- ✅ **Approved** — cleared for implementation in Phase B
- ⏸ **Deferred** — requires product review before new website fields can be added
- 🔍 **Investigate** — Phase B must confirm disposition before implementing

| # | Canonical Key | Issue | Affected Roles | Phase B Scope | Fix Required |
|---|---|---|---|---|---|
| 1 | `utilities` | **`mapped_wrong`** — Landlord field map targets `utilities` (string) instead of `property_utilities` (array) | L | ✅ Approved | Change Landlord entry from `'utilities' => 'utilities'` to `'utilities' => '*property_utilities'` in `MlsFieldMap::landlord()`. Confirm blade uses `wire:model="property_utilities"`. |
| 2 | `heating` | **`missing_from_field_map`** — Parser emits `heating` for simple "Heating:" labels; no field map entry exists | S, L | ✅ Approved | Consolidate parser so "Heating:" writes to `heating_fuel` key (same as "Heating and Fuel:" branch). Remove the separate `heating` branch and merge its regex into the existing `heating_fuel` branch so both patterns produce the same canonical key. Then no field map changes are needed. |
| 3 | `lot_size_sqft` | **`missing_from_field_map`** — Parsed but no field map entry and no Livewire property | S, L | 🔍 Investigate | No Livewire property named `lot_size_sqft` exists. Phase B must confirm: (a) map to existing `total_acreage` as a fallback when acreage absent; (b) add a new property; or (c) intentionally exclude and remove the parser branch. |
| 4 | `flood_zone_date` | **`needs_new_field`** — Parsed but no Livewire property and no blade binding on any component | S, L | ⏸ Deferred | No property, no blade input, and no meta key confirmed. Requires product sign-off to add the full stack (meta key → component property → blade input → field map entry). Out of Phase B scope. |
| 5 | `waterfront` | **`missing_from_website`** — Parsed, no Livewire property, no field map | S, L | ⏸ Deferred | Requires product review — new website fields needed. Out of Phase B scope. |
| 6 | `water_access` | **`missing_from_website`** — Same as waterfront | S, L | ⏸ Deferred | Same as row 5. Out of Phase B scope. |
| 7 | `water_view` | **`missing_from_website`** — Same as waterfront | S, L | ⏸ Deferred | Same as row 5. Out of Phase B scope. |
| 8 | `interior_features` | **`missing_from_website`** — Parsed, no Livewire property, no field map | S, L | ⏸ Deferred | Requires product review — new website field needed. Out of Phase B scope. |
| 9 | `directions` | **`missing_from_website`** — Parsed, no Livewire property, no field map | S, L | ⏸ Deferred | Requires product review — new website field needed. Out of Phase B scope. |
| 10 | `lot_size_acres` (Landlord) | **`missing_from_field_map`** — Property exists on LandlordOfferListing, blade has binding, field map omits it | L | ✅ Approved | Add `'lot_size_acres' => 'total_acreage'` to `MlsFieldMap::landlord()`. |
| 11 | `sqft_heated_source` (Tenant) | **`missing_from_field_map`** — Property exists on TenantOfferListing, blade has binding, field map omits it | T | ✅ Approved | Add `'sqft_heated_source' => 'sqft_heated_source'` to `MlsFieldMap::tenant()`. |
| 12 | address / city / state / zip / county (Tenant) | **`missing_from_field_map`** — All five address properties exist on TenantOfferListing with blade bindings, but Tenant field map has none of them | T | ✅ Approved | Add five entries to `MlsFieldMap::tenant()`: `'address'→'address'`, `'city'→'property_city'`, `'state'→'property_state'`, `'zip'→'property_zip'`, `'county'→'property_county'`. |
| 13 | address / city / state / zip / county (Buyer) | **`missing_from_field_map`** (form field side) — Component properties exist and field map entries exist, but buyer blade has no `wire:model` binding for any of these | B | ✅ Approved (investigate) | Phase B must inspect buyer blade tabs to confirm no address inputs exist. If confirmed absent: remove the five address entries from `MlsFieldMap::buyer()` and document as `intentionally_excluded`. If blade inputs exist but wire:model is absent: add the missing bindings instead. |
| 14 | `flood_insurance_required` normalizer | **Code smell** — Parser calls `normalize('has_hoa', $v)` instead of a named case | — | ✅ Approved | Add `'flood_insurance_required'` case to the `match` in `MlsNormalizer::normalize()` routing to `self::normalizeBoolean($v)`. Update the parser call on line 516 to pass `'flood_insurance_required'`. |
| 15 | `has_special_assessments` normalizer | **Code smell** — Same as above; parser calls `normalize('has_hoa', $v)` | — | ✅ Approved | Add `'has_special_assessments'` case to `MlsNormalizer::normalize()` routing to `self::normalizeBoolean($v)`. Update the parser call on line 525. |

---

## Rejected Mapping Candidates (reproduced from `MlsCoverageReporter`)

| Canonical Key | Rejected Target Property | Rejected For Role(s) | Reason |
|---|---|---|---|
| `mls_number` | `mls_number` | All | No Livewire property named `mls_number` exists on any component. Parsed but not imported. |
| `application_fee` | `application_fee` | Landlord | Property does not exist on `LandlordOfferListing`. Parsed but not mapped. |
| `year_built` | `year_built` | Buyer | Property does not exist on `BuyerOfferListing`. |
| `price` | `desired_rental_amount` | Tenant | MLS listing price is the landlord's asking rent, not a tenant's desired amount — semantically wrong direction. |
| `tax_id` (canonical key) | `tax_id` (as property name) | All | App property is `parcel_id`, not `tax_id`. Using the wrong name silently skips the form field. |

---

*End of Phase A Audit. No PHP files, parser branches, field map entries, or normalizer rules were modified to produce this document.*
