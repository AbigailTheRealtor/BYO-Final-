# Stellar Native Column Expansion Strategy

> Document type: Architecture decision record  
> Date: 2026-06-16  
> Source audits: `docs/audits/STELLAR_MATCHING_READINESS_AUDIT.md` · `docs/audits/STELLAR_BRIDGE_FIELD_AUDIT.md`  
> Migration baseline: `database/migrations/2026_06_15_000010_create_bridge_properties_table.php`  
> Scope: Analysis only — no migrations, no schema changes, no code changes

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Current State](#2-current-state)
3. [Buyer Matching Requirements](#3-buyer-matching-requirements)
4. [Tenant Matching Requirements](#4-tenant-matching-requirements)
5. [Alert System Requirements](#5-alert-system-requirements)
6. [Ask AI Requirements](#6-ask-ai-requirements)
7. [Geospatial Strategy](#7-geospatial-strategy)
8. [Recommended Phase 1 Promotions](#8-recommended-phase-1-promotions)
9. [Estimated Matching Readiness](#9-estimated-matching-readiness)

---

## 1. Executive Summary

The `bridge_properties` table currently has **13 native columns** mapping Stellar MLS fields directly. The readiness audit classified **553 total Stellar fields** into six tiers; **66 are Tier 1 Core Matching** fields essential for buyer/tenant scoring queries. Only 9 of those 66 are already native columns — the remaining **57 Tier 1 fields exist only in `raw_json`**.

This is the central problem: no matching engine built on JSON extraction can run at acceptable query performance once record counts scale. Every buyer match query that touches `raw_json->>'Latitude'` instead of a native indexed `latitude` column requires a full-table sequential scan or slow PostgreSQL JSON extraction on every row.

### Key Findings

| Finding | Impact |
|---|---|
| 57 of 66 Tier 1 fields are raw_json only | Matching engine blocked or unacceptably slow without promotion |
| Latitude and Longitude are raw_json only | No radius/map-based search is possible with native column indexes |
| All tenant-specific rental fields are 0/25 in current sample | Tenant matching engine needs rental feed before data is available |
| PetsAllowed, Furnished, LeaseTerm, AvailabilityDate are all raw_json | Core tenant match dimensions are not indexable |
| Alert triggers (PreviousListPrice, StatusChangeTimestamp) are raw_json | Price-change and status-change alerts require JSON extraction at trigger time |
| Ask AI context fields are acceptable as raw_json for per-record queries | Ask AI does not require column promotion — per-listing JSON extraction is O(1) |

### Recommended Action

Promote the **top 20 fields** listed in Section 8 before building either the buyer or tenant matching engine. This single migration will take buyer matching from 21% to ~60% Tier 1 field coverage and unlock the core matching query patterns needed for launch.

---

## 2. Current State

### 2.1 — The 13 Current Native Columns

| Native Column | Stellar Field | Tier | SQL Type | Population |
|---|---|---|---|---|
| `listing_key` | `ListingKey` | 4 | `varchar` (unique) | 25/25 |
| `listing_id` | `ListingId` | 4 | `varchar` | 25/25 |
| `standard_status` | `StandardStatus` | 1 | `varchar` | 25/25 |
| `property_type` | `PropertyType` | 1 | `varchar` | 25/25 |
| `list_price` | `ListPrice` | 1 | `decimal(15,2)` | 25/25 |
| `unparsed_address` | `UnparsedAddress` | 3 | `text` | 25/25 |
| `city` | `City` | 1 | `varchar` | 25/25 |
| `state_or_province` | `StateOrProvince` | 1 | `varchar` | 25/25 |
| `postal_code` | `PostalCode` | 1 | `varchar` | 25/25 |
| `bedrooms_total` | `BedroomsTotal` | 1 | `integer` | 25/25 |
| `bathrooms_total_integer` | `BathroomsTotalInteger` | 1 | `integer` | 25/25 |
| `living_area` | `LivingArea` | 1 | `integer` | 25/25 |
| `modification_timestamp` | `ModificationTimestamp` | 4 | `timestamp` | 25/25 |

Of the 66 Tier 1 Core Matching fields, **9 are covered by these native columns** (`StandardStatus`, `PropertyType`, `ListPrice`, `City`, `StateOrProvince`, `PostalCode`, `BedroomsTotal`, `BathroomsTotalInteger`, `LivingArea`). The remaining 4 native columns (`listing_key`, `listing_id`, `unparsed_address`, `modification_timestamp`) serve Tier 3/4 roles — identifiers, display, and sync timestamps.

### 2.2 — Tier 1 Matching-Critical Fields That Are Raw JSON Only

These 57 Tier 1 fields must be queried via `raw_json->>'FieldName'` at present, making indexed WHERE/ORDER BY/range scans impossible:

**Location & Geography (raw_json only):**
`Latitude`, `Longitude`, `CountyOrParish`, `SubdivisionName`, `MLSAreaMajor`

**Property Classification (raw_json only):**
`PropertySubType`, `MlsStatus`, `OriginalListPrice`

**Financial (raw_json only):**
`TaxAnnualAmount`, `AssociationFee`, `AssociationYN`, `STELLAR_CDDYN`, `CapRate`, `GrossIncome`, `GrossScheduledIncome`, `STELLAR_EstAnnualMarketIncome`, `STELLAR_AnnualNetIncome`, `STELLAR_AnnualExpenses`, `STELLAR_CondoFees`, `STELLAR_MonthlyCondoFeeAmount`

**Size Detail (raw_json only):**
`BathroomsFull`, `BathroomsHalf`, `LotSizeSquareFeet`, `LotSizeAcres`, `GarageSpaces`

**Feature Flags (raw_json only):**
`GarageYN`, `PoolPrivateYN`, `WaterfrontYN`, `ViewYN`, `STELLAR_WaterViewYN`, `SeniorCommunityYN`, `NewConstructionYN`, `YearBuilt`, `Zoning`

**Rental/Tenant Specific (raw_json only — all 0/25 in sale sample):**
`LeaseConsideredYN`, `STELLAR_ForLeaseYN`, `STELLAR_LongTermYN`, `LeaseAmountFrequency`, `STELLAR_CurrencyMonthlyRentAmt`, `Furnished`, `PetsAllowed`, `LeaseTerm`, `STELLAR_MinimumLease`, `AvailabilityDate`, `TenantPays`, `STELLAR_SecurityDeposit`, `STELLAR_NumberOfPets`, `STELLAR_MaxPetWeight`, `STELLAR_PetSize`, `STELLAR_PetDepositFee`, `STELLAR_PetMonthlyFee`, `TotalActualRent`, `ZoningDescription`

### 2.3 — Alert-Critical Fields That Are Raw JSON Only

These fields are needed to trigger or populate alerts but have no native column:

| Field | Alert Type | Why Native Column Needed |
|---|---|---|
| `PreviousListPrice` | Price change | Must compare to `list_price` to detect reduction |
| `PriceChangeTimestamp` | Price change | Trigger timestamp for price alert job |
| `StatusChangeTimestamp` | Status change | Trigger timestamp for status alert job |
| `MlsStatus` | Status change | Board-specific status vocab (Sold, Active, etc.) |
| `OriginalListPrice` | Price reduction | Delta from original to current |
| `DaysOnMarket` | New listing freshness | Alert freshness decay signal |
| `NewConstructionYN` | New construction milestone | Gate for construction milestone alerts |
| `AvailabilityDate` | Rental available | Upcoming availability alerts |

### 2.4 — Ask AI Fields That Are Raw JSON Only

Ask AI loads listing context at query time (per-record retrieval by `listing_key` index), so raw_json extraction is acceptable — this is O(1) per query, not O(n) across the table. However, the following high-priority structured Ask AI fields would benefit from native column promotion to simplify context building:

`YearBuilt`, `AssociationFee`, `TaxAnnualAmount`, `PropertySubType`, `GarageSpaces`, `GarageYN`, `PoolPrivateYN`, `WaterfrontYN`, `STELLAR_WaterViewYN`, `SeniorCommunityYN`, `NewConstructionYN`, `LotSizeSquareFeet`, `PetsAllowed`, `Furnished`, `STELLAR_CDDYN`

Free-text and array fields (`PublicRemarks`, `Appliances`, `InteriorFeatures`, `ExteriorFeatures`, `CommunityFeatures`, `Heating`, `Cooling`, `Flooring`, etc.) are best left in `raw_json` — they are passed wholesale into the AI context payload and do not need to be individually indexed.

---

## 3. Buyer Matching Requirements

### Purpose
Match buyer criteria (price range, location, property type, size, features) against active Stellar listings. Every field used in a WHERE clause, range scan, or ORDER BY on the `bridge_properties` table must be a native indexed column.

### Field Classification

| Field | Stellar Field | Classification | Reason |
|---|---|---|---|
| `standard_status` | `StandardStatus` | **Already native** | Gates all queries — active listings only |
| `property_type` | `PropertyType` | **Already native** | Primary type filter |
| `city` | `City` | **Already native** | Core geography filter |
| `postal_code` | `PostalCode` | **Already native** | ZIP-code filter |
| `state_or_province` | `StateOrProvince` | **Already native** | State filter |
| `list_price` | `ListPrice` | **Already native** | Price range WHERE clause |
| `bedrooms_total` | `BedroomsTotal` | **Already native** | Bedroom min filter |
| `bathrooms_total_integer` | `BathroomsTotalInteger` | **Already native** | Bathroom min filter |
| `living_area` | `LivingArea` | **Already native** | Square footage range filter |
| `latitude` | `Latitude` | **Must become native** | Radius search requires indexed decimal column |
| `longitude` | `Longitude` | **Must become native** | Radius search requires indexed decimal column |
| `county_or_parish` | `CountyOrParish` | **Must become native** | County-level WHERE clause |
| `property_sub_type` | `PropertySubType` | **Must become native** | SFR vs. condo vs. townhouse filter |
| `year_built` | `YearBuilt` | **Must become native** | Decade/era range filter |
| `garage_yn` | `GarageYN` | **Must become native** | Boolean filter (top-5 buyer preference) |
| `pool_private_yn` | `PoolPrivateYN` | **Must become native** | Boolean filter (FL primary feature) |
| `waterfront_yn` | `WaterfrontYN` | **Must become native** | Boolean filter (FL premium) |
| `new_construction_yn` | `NewConstructionYN` | **Must become native** | Boolean filter (large buyer segment) |
| `senior_community_yn` | `SeniorCommunityYN` | **Must become native** | Legal filter (55+ communities require indexable query) |
| `association_fee` | `AssociationFee` | **Must become native** | HOA cost range filter |
| `association_yn` | `AssociationYN` | **Must become native** | HOA exists boolean gate |
| `cdd_yn` | `STELLAR_CDDYN` | **Must become native** | FL CDD cost gate |
| `tax_annual_amount` | `TaxAnnualAmount` | **Must become native** | True ownership cost filter |
| `lot_size_sqft` | `LotSizeSquareFeet` | **Must become native** | Lot size range filter |
| `view_yn` | `ViewYN` | **Must become native** | View feature toggle |
| `water_view_yn` | `STELLAR_WaterViewYN` | **Must become native** | Water view toggle |
| `mls_status` | `MlsStatus` | **Must become native** | Board-specific status for alert joins |
| `subdivision_name` | `SubdivisionName` | **Must become native** | Community-level search (WHERE + text search) |
| `mls_area_major` | `MLSAreaMajor` | **Must become native** | Sub-market filter |
| `original_list_price` | `OriginalListPrice` | **Must become native** | Price reduction filter (delta from current price) |
| `bathrooms_full` | `BathroomsFull` | **Must become native** | Full-bath precision filter |
| `garage_spaces` | `GarageSpaces` | **Must become native** | Garage count range filter |
| `lot_size_acres` | `LotSizeAcres` | **Can remain JSON** | Redundant with `lot_size_sqft`; display convenience only |
| `bathrooms_half` | `BathroomsHalf` | **Can remain JSON** | Half-bath rarely drives match decisions; display only |
| `zoning` | `Zoning` | **Can remain JSON** | Low-frequency lookup; full-text JSON extraction acceptable |
| `zoning_description` | `ZoningDescription` | **Can remain JSON** | 0/25 in sample; display context only when present |
| `cap_rate` | `CapRate` | **Can remain JSON** | 0/25 in sale sample; investment feed only; low frequency |
| `gross_income` | `GrossIncome` | **Can remain JSON** | 0/25 in sample; investment context display |
| `gross_scheduled_income` | `GrossScheduledIncome` | **Can remain JSON** | 0/25 in sample; investment context display |
| `stellar_est_annual_market_income` | `STELLAR_EstAnnualMarketIncome` | **Can remain JSON** | 0/25; investment display |
| `stellar_annual_net_income` | `STELLAR_AnnualNetIncome` | **Can remain JSON** | 0/25; investment display |
| `stellar_annual_expenses` | `STELLAR_AnnualExpenses` | **Can remain JSON** | 0/25; investment display |
| `stellar_condo_fees` | `STELLAR_CondoFees` | **Can remain JSON** | 0/25; condo feed display |
| `stellar_monthly_condo_fee_amount` | `STELLAR_MonthlyCondoFeeAmount` | **Can remain JSON** | 0/25; condo feed display |

### Buyer Matching Gaps (Fields Requiring External Data)

| Gap | Impact | Recommended Resolution |
|---|---|---|
| School district/quality | High | Geocode-to-district API; `ElementarySchool`/`HighSchool` exist but are not normalized |
| Walk Score / Transit Score | Medium | Walk Score API using native `latitude`/`longitude` after promotion |
| Flood zone risk | High | Translate `STELLAR_FloodZoneCode` (FEMA X/AE/VE codes) to a risk tier column |
| Investment financials | High (for investor buyers) | `CapRate`, `GrossIncome` only populate in investment/multifamily feeds; add columns when feed enabled |

---

## 4. Tenant Matching Requirements

### Purpose
Match tenant search criteria (monthly rent, beds/baths, pets, furnished, lease term, move-in date) against active rental listings. The current 25-record sample is residential-for-sale only — most rental-specific fields are 0/25 and will only populate after Stellar's **For Lease feed** (`STELLAR_ForLeaseYN=true` or `LeaseConsideredYN=true`) is enabled.

### Field Classification

| Field | Stellar Field | Classification | Reason |
|---|---|---|---|
| `standard_status` | `StandardStatus` | **Already native** | Active rentals only |
| `city` | `City` | **Already native** | Core location filter |
| `postal_code` | `PostalCode` | **Already native** | ZIP-code filter |
| `list_price` | `ListPrice` | **Already native** | Rent amount proxy (pending `monthly_rent` column) |
| `bedrooms_total` | `BedroomsTotal` | **Already native** | Bedroom filter |
| `bathrooms_total_integer` | `BathroomsTotalInteger` | **Already native** | Bathroom filter |
| `living_area` | `LivingArea` | **Already native** | Size filter |
| `property_type` | `PropertyType` | **Already native** | Type filter |
| `latitude` | `Latitude` | **Must become native** | Radius search |
| `longitude` | `Longitude` | **Must become native** | Radius search |
| `county_or_parish` | `CountyOrParish` | **Must become native** | County WHERE clause |
| `property_sub_type` | `PropertySubType` | **Must become native** | SFR vs. condo tenant preference |
| `mls_status` | `MlsStatus` | **Must become native** | Board-specific rental status filter |
| `furnished` | `Furnished` | **Must become native** | Critical tenant filter (furnished/unfurnished toggle) |
| `pets_allowed` | `PetsAllowed` | **Must become native** | Critical pet policy gate |
| `senior_community_yn` | `SeniorCommunityYN` | **Must become native** | Legal age-restriction compliance |
| `garage_yn` | `GarageYN` | **Must become native** | Parking preference |
| `pool_private_yn` | `PoolPrivateYN` | **Must become native** | Amenity filter |
| `waterfront_yn` | `WaterfrontYN` | **Must become native** | Waterfront preference |
| `association_yn` | `AssociationYN` | **Must become native** | HOA approval gate |
| `association_fee` | `AssociationFee` | **Must become native** | May be included in rent; cost filter |
| `year_built` | `YearBuilt` | **Must become native** | Condition preference |
| `lease_considered_yn` | `LeaseConsideredYN` | **Must become native** | Rental-vs-sale gate (0/25 now; critical for rental feed) |
| `for_lease_yn` | `STELLAR_ForLeaseYN` | **Must become native** | Rental listing gate (0/25 now; critical for rental feed) |
| `long_term_yn` | `STELLAR_LongTermYN` | **Must become native** | Separate long-term from vacation rentals |
| `lease_amount_frequency` | `LeaseAmountFrequency` | **Must become native** | Monthly/weekly qualifier for `list_price` |
| `monthly_rent` | `STELLAR_CurrencyMonthlyRentAmt` | **Must become native** | Canonical rent amount (disambiguates from sale price) |
| `lease_term` | `LeaseTerm` | **Must become native** | Min lease length filter |
| `minimum_lease` | `STELLAR_MinimumLease` | **Must become native** | Explicit min lease months filter |
| `availability_date` | `AvailabilityDate` | **Must become native** | Move-in date range filter |
| `garage_spaces` | `GarageSpaces` | **Must become native** | Garage count preference |
| `subdivision_name` | `SubdivisionName` | **Must become native** | Community preference |
| `stellar_number_of_pets` | `STELLAR_NumberOfPets` | **Can remain JSON** | Max pet count — display detail, low filter frequency |
| `stellar_max_pet_weight` | `STELLAR_MaxPetWeight` | **Can remain JSON** | Pet weight limit — display detail, low filter frequency |
| `stellar_pet_size` | `STELLAR_PetSize` | **Can remain JSON** | Pet size text — display detail |
| `stellar_pet_deposit_fee` | `STELLAR_PetDepositFee` | **Can remain JSON** | Display cost detail, not a match filter |
| `stellar_pet_monthly_fee` | `STELLAR_PetMonthlyFee` | **Can remain JSON** | Display cost detail |
| `stellar_additional_pet_fees` | `STELLAR_AdditionalPetFees` | **Can remain JSON** | Display cost detail |
| `stellar_month_to_month_yn` | `STELLAR_MonthToMonthOrWeeklyYN` | **Can remain JSON** | Flexible lease flag — display context |
| `tenant_pays` | `TenantPays` | **Can remain JSON** | Utility responsibility display |
| `rent_includes` | `RentIncludes` | **Can remain JSON** | Inclusion display text |
| `stellar_lease_restrictions_yn` | `STELLAR_LeaseRestrictionsYN` | **Can remain JSON** | HOA restriction flag — display context |
| `stellar_security_deposit` | `STELLAR_SecurityDeposit` | **Can remain JSON** | Cost planning display; not a filter |
| `stellar_annual_rent` | `STELLAR_AnnualRent` | **Can remain JSON** | Display context; `monthly_rent` is canonical |

### Special Attention: Rental-Specific Fields

The following rental extension fields are all 0/25 in the current residential-for-sale sample. They are classified as "Must become native" for the rental matching engine but **their column promotion should happen in a separate dedicated rental-feed migration** once the Stellar For Lease feed is confirmed active and populating data:

`lease_considered_yn`, `for_lease_yn`, `long_term_yn`, `lease_amount_frequency`, `monthly_rent`, `lease_term`, `minimum_lease`, `availability_date`

Promoting these columns before the rental feed is active wastes schema space and creates nullable columns that will be empty for all existing records.

### Tenant Matching Gaps

| Gap | Impact | Recommended Resolution |
|---|---|---|
| No rental feed data in current sample | Critical | Must enable Stellar For Lease feed before any tenant matching |
| Utility inclusion clarity | High | `TenantPays` and `RentIncludes` are 0/25; tenants cannot assess true monthly cost |
| HOA approval process | Medium | `STELLAR_AssociationApprovalRequiredYN` is 0/25; renters need to know if HOA must approve |
| Short-term vs. long-term separation | High | `STELLAR_LongTermYN` and `STELLAR_MonthToMonthOrWeeklyYN` both 0/25 |

---

## 5. Alert System Requirements

### Alert Types and Trigger Field Analysis

| Alert Type | Trigger Fields | Native Column Required? | Recommendation |
|---|---|---|---|
| **New Listing** | `StandardStatus`, `OriginalEntryTimestamp`, `OnMarketDate` | `StandardStatus` ✅ already native; `OriginalEntryTimestamp` can stay JSON (per-record lookup at import time, not a scan) | **No new column needed** — detect at import time when `listing_key` is new |
| **Price Reduction** | `ListPrice` vs. `PreviousListPrice`, `PriceChangeTimestamp` | `ListPrice` ✅ native; `PreviousListPrice` and `PriceChangeTimestamp` are raw_json | **Promote `previous_list_price` and `price_change_timestamp`** — comparison requires both columns to be native for efficient delta detection |
| **Price Increase** | Same fields as price reduction | Same as above | **Same as price reduction** |
| **Status Change** | `StandardStatus`, `MlsStatus`, `StatusChangeTimestamp`, `STELLAR_PreviousStatus` | `StandardStatus` ✅ native; others are raw_json | **Promote `mls_status` and `status_change_timestamp`** — event-driven status diffing requires both |
| **Back on Market** | `STELLAR_BOMDate`, `StandardStatus`, `DaysOnMarket` | `StandardStatus` ✅ native; others raw_json | **JSON extraction at trigger time is acceptable** — BOM is a rare event; on-import detection via `STELLAR_BOMDate` presence is sufficient |
| **New Match** | All Tier 1 match fields | All buyer/tenant Tier 1 fields must be native | **Phase 1 promotions required** — match alerts are the most query-intensive alert type |
| **Coming Soon** | `STELLAR_ComingSoonDate`, `StandardStatus`, `ListPrice` | `StandardStatus` ✅ and `ListPrice` ✅ native | **JSON extraction acceptable** — coming-soon is detected at import; `STELLAR_ComingSoonDate` does not need a native column |
| **Photos Updated** | `PhotosChangeTimestamp`, `PhotosCount` | Both raw_json | **JSON extraction at import time is acceptable** — photo updates detected per-record on sync |
| **Open House Scheduled** | `STELLAR_ActiveOpenHouseCount`, `STELLAR_OpenHouseCount` | Both raw_json | **JSON extraction acceptable** — open house count checked per-record on sync |
| **New Construction Milestone** | `STELLAR_ProjectedCompletionDate`, `PropertyCondition`, `NewConstructionYN` | `NewConstructionYN` should become native; others raw_json | **Promote `new_construction_yn`** to gate this alert type efficiently |
| **Listing Expired/Cancelled** | `StandardStatus`, `OffMarketDate` | `StandardStatus` ✅ native | **JSON extraction acceptable** — detected per-record on sync |
| **Rental Available** | `AvailabilityDate`, `STELLAR_ExpectedLeaseDate` | Both raw_json | **Promote `availability_date`** when rental feed is active; until then JSON extraction is acceptable |
| **Special Condition** | `SpecialListingConditions` | Raw_json | **JSON extraction acceptable** — rare event; per-record detection at import |

### Summary Decision on Alert JSON Extraction

**JSON extraction at alert-trigger time is acceptable for all alert types EXCEPT:**

1. **Price change alerts** — require native `previous_list_price` to efficiently compute `list_price < previous_list_price` across the table in a scheduled comparison job.
2. **Status change alerts** — require native `status_change_timestamp` and `mls_status` for the alert diffing job to detect changes since last sync.
3. **Match alerts** — require all Tier 1 match fields to be native columns (these are the same Phase 1 promotions that unblock the matching engine).

All other alert types fire on a per-record basis at import time (new record, or changed `modification_timestamp`), so raw_json extraction per record is O(1) and fully acceptable.

---

## 6. Ask AI Requirements

### Classification Framework

Ask AI queries individual listings by `listing_key` (already indexed), so raw_json extraction per record is **O(1) — acceptable for all Ask AI fields**. The performance boundary is not "native vs. JSON" but rather "is the field populated reliably enough to be useful in context."

| Field | Classification | Reason |
|---|---|---|
| `BedroomsTotal` | **Native** (already) | Frequently cited structured answer; already a native column |
| `BathroomsTotalInteger` | **Native** (already) | Frequently cited structured answer; already a native column |
| `LivingArea` | **Native** (already) | Frequently cited structured answer; already a native column |
| `ListPrice` | **Native** (already) | Price answer; already a native column |
| `StandardStatus` | **Native** (already) | Status context; already a native column |
| `YearBuilt` | **Searchable JSON** → promote via Phase 1 | Fast context loading; frequently asked "how old is this home?" |
| `AssociationFee` | **Searchable JSON** → promote via Phase 1 | HOA cost is top-3 buyer Ask AI question |
| `TaxAnnualAmount` | **Searchable JSON** → promote via Phase 1 | Annual tax burden is top-5 buyer question |
| `PropertySubType` | **Searchable JSON** → promote via Phase 1 | Needed for context header ("3BR Single Family Residence") |
| `GarageYN` / `GarageSpaces` | **Searchable JSON** → promote via Phase 1 | "Does this home have a garage?" is frequent |
| `PoolPrivateYN` | **Searchable JSON** → promote via Phase 1 | FL top Ask AI question |
| `WaterfrontYN` | **Searchable JSON** → promote via Phase 1 | FL waterfront question |
| `STELLAR_WaterViewYN` | **Searchable JSON** → promote via Phase 1 | Water view question |
| `SeniorCommunityYN` | **Searchable JSON** → promote via Phase 1 | Compliance-sensitive answer |
| `NewConstructionYN` | **Searchable JSON** → promote via Phase 1 | Condition context |
| `LotSizeSquareFeet` | **Searchable JSON** → promote via Phase 1 | Lot size question |
| `STELLAR_CDDYN` | **Searchable JSON** → promote via Phase 1 | FL CDD question |
| `PetsAllowed` | **Searchable JSON** → promote via Phase 1 | Rental/buyer pet question |
| `Furnished` | **Searchable JSON** → promote via Phase 1 | Rental furnished question |
| `PublicRemarks` | **Embedded AI context only** | Primary free-text; passed wholesale to model; no indexing needed |
| `Appliances` | **Embedded AI context only** | Array; passed as context payload; no indexing needed |
| `InteriorFeatures` | **Embedded AI context only** | Array; context payload |
| `ExteriorFeatures` | **Embedded AI context only** | Array; context payload |
| `CommunityFeatures` | **Embedded AI context only** | Array; context payload |
| `AssociationAmenities` | **Embedded AI context only** | Array; context payload |
| `Heating` / `Cooling` | **Embedded AI context only** | HVAC arrays; context payload |
| `Flooring` | **Embedded AI context only** | Array; context payload |
| `LotFeatures` | **Embedded AI context only** | Array; context payload |
| `ConstructionMaterials` | **Embedded AI context only** | Array; context payload |
| `Roof` | **Embedded AI context only** | Array; context payload |
| `Directions` | **Embedded AI context only** | Free text; context payload |
| `ListingTerms` | **Embedded AI context only** | Array; financing context |
| `ElementarySchool` / `HighSchool` | **Embedded AI context only** | School names; display context |
| `ClosePrice` | **Embedded AI context only** | Historical price context |
| `Zoning` / `ZoningDescription` | **Embedded AI context only** | Zoning detail; infrequent question |
| `Ownership` | **Embedded AI context only** | Fee simple / condo context |
| `STELLAR_CDDYN` + `TaxOtherAnnualAssessmentAmount` | **Embedded AI context only** (amount field) | CDD amount display context |
| `AssociationFeeFrequency` | **Embedded AI context only** | Qualifier for AssociationFee |
| `HomeWarrantyYN` | **Embedded AI context only** | Sale terms context |
| `OccupantType` | **Embedded AI context only** | Vacancy context |
| `STELLAR_FloodZoneCode` | **Embedded AI context only** | Risk context (requires FEMA code translation before use) |
| All Tier 6 / Compliance fields | **Hard excluded** | Must never appear in any Ask AI prompt or response |

### Ask AI Hard Exclusion List (Must Never Appear in Any Context Payload)

`ListAgentEmail`, `ListAgentPreferredPhone`, `ListOfficePhone`, `ListAgentStateLicense`, `BuyerAgentStateLicense`, `CoListAgentStateLicense`, `CoBuyerAgentStateLicense`, `License1`, `License2`, `License3`, `STELLAR_BuilderLicenseNumber`, `LockBoxLocation`, `LockBoxSerialNumber`, `LockBoxType`, `STELLAR_ShowingRequirements`, `STELLAR_ShowingConsiderations`, `STELLAR_CallCenterPhoneNumber`, `STELLAR_EscrowAgentEmail`, `STELLAR_EscrowAgentPhone`, `STELLAR_ListOfficeContactPreferred`, `STELLAR_PropertyManagerPhone`, `STELLAR_TenantPhone`, `Telephone`, `STELLAR_TenantName`, `STELLAR_RealtorInfoConfidential`, `STELLAR_SoldRemarks`

---

## 7. Geospatial Strategy

### Fields Under Evaluation

| Field | Stellar Field | Population | Current State |
|---|---|---|---|
| `latitude` | `Latitude` | 25/25 | Raw JSON float |
| `longitude` | `Longitude` | 25/25 | Raw JSON float |
| `county_or_parish` | `CountyOrParish` | 25/25 | Raw JSON string |
| `city` | `City` | 25/25 | **Already native `varchar`** |
| `postal_code` | `PostalCode` | 25/25 | **Already native `varchar`** |

### Recommended Column Types and Indexing

| Column | Recommended SQL Type | Index Type | Rationale |
|---|---|---|---|
| `latitude` | `DECIMAL(10, 7)` | Standard B-tree | 7 decimal places ≈ 1.1 cm precision; sufficient for radius filtering using Haversine formula |
| `longitude` | `DECIMAL(10, 7)` | Standard B-tree | Same as latitude |
| `county_or_parish` | `VARCHAR(100)` | Standard B-tree | Text equality filter; county names are short and stable |
| `city` | Already `VARCHAR` with index | Existing | No change needed |
| `postal_code` | Already `VARCHAR` with index | Existing | No change needed |

**Composite index recommendation:** After promoting `latitude` and `longitude`, add a composite B-tree index on `(latitude, longitude)`. This index supports Haversine-based bounding-box queries (`WHERE latitude BETWEEN ? AND ? AND longitude BETWEEN ? AND ?`) which serve as the first-pass radius filter before precise distance calculation.

### Radius Query Pattern (Interim — Before PostGIS)

```sql
SELECT *, (
    3959 * ACOS(
        COS(RADIANS(?)) * COS(RADIANS(latitude))
        * COS(RADIANS(longitude) - RADIANS(?))
        + SIN(RADIANS(?)) * SIN(RADIANS(latitude))
    )
) AS distance_miles
FROM bridge_properties
WHERE
    standard_status = 'Active'
    AND latitude BETWEEN (? - ?/69.0) AND (? + ?/69.0)
    AND longitude BETWEEN (? - ?/53.0) AND (? + ?/53.0)
HAVING distance_miles <= ?
ORDER BY distance_miles ASC;
```

The bounding-box WHERE clause hits the composite B-tree index; the Haversine HAVING clause filters precise matches from that reduced set. This pattern is accurate to within ~0.5% and requires no PostGIS extension.

### Future PostGIS Migration Path

When query volume or precision requirements exceed what Haversine on decimal columns can deliver:

1. **Add a `geo_point` column** of type `GEOGRAPHY(POINT, 4326)` to `bridge_properties`.
2. **Populate it** via: `UPDATE bridge_properties SET geo_point = ST_SetSRID(ST_MakePoint(longitude, latitude), 4326)`.
3. **Add a GIST index**: `CREATE INDEX bridge_properties_geo_point_gist ON bridge_properties USING GIST(geo_point)`.
4. **Replace the Haversine query** with: `WHERE ST_DWithin(geo_point::geography, ST_MakePoint(?, ?)::geography, ? * 1609.34)` (radius in meters).
5. **Do not drop the decimal columns** — keep `latitude` and `longitude` as they are also used individually for display, map centering, and non-radius filters.

**Recommended interim approach:** Promote `latitude` and `longitude` as `DECIMAL(10, 7)` columns immediately (Phase 1). This does not block the matching engine launch and leaves a clean migration path to PostGIS later without requiring a schema overhaul.

---

## 8. Recommended Phase 1 Promotions

### Ranking Methodology

Each field is scored across four feature dimensions:
- **Buyer match weight:** 0 (not used) / 1 (medium) / 2 (high) / 3 (critical)
- **Tenant match weight:** 0 / 1 / 2 / 3
- **Alert utility:** 0 (not needed as native) / 1 (needed for alert trigger)
- **Ask AI utility:** 0 (display only) / 1 (structured context value)

**Total score = buyer + tenant + alert + ask_ai**. Ties broken by population rate (higher population ranked first), then by compliance sensitivity (legally-gated fields ranked higher to ensure they are never missed).

### Top 20 Fields to Promote

| # | Field Name | SQL Type | Reason | Feature Unlocked | Score |
|---|---|---|---|---|---|
| 1 | `latitude` | `DECIMAL(10,7)` | Critical for buyer + tenant radius/map search; 25/25 populated | Map-based search, radius matching for both engines | 6 |
| 2 | `longitude` | `DECIMAL(10,7)` | Critical for buyer + tenant radius/map search; 25/25 populated | Map-based search, radius matching for both engines | 6 |
| 3 | `county_or_parish` | `VARCHAR(100)` | Critical county-level WHERE filter for buyer + tenant; 25/25 | County search, market-level filtering | 6 |
| 4 | `property_sub_type` | `VARCHAR(100)` | High for buyer + tenant (SFR vs. condo vs. townhouse); 25/25 | Property subtype filter + Ask AI context header | 5 |
| 5 | `senior_community_yn` | `BOOLEAN` | High for buyer + tenant — legal compliance filter; 25/25 | 55+ community legal gate for both engines | 5 |
| 6 | `mls_status` | `VARCHAR(50)` | Critical for tenant status; high for alert trigger; 25/25 | Rental status filter + price/status alert diffing | 5 |
| 7 | `year_built` | `SMALLINT` | High for buyer (decade filter); 25/25 populated; Ask AI | Age-range filter + "how old is this home?" Ask AI | 4 |
| 8 | `association_fee` | `DECIMAL(10,2)` | High for buyer (HOA cost range); 22/25; Ask AI | HOA fee filter + Ask AI ownership cost answers | 4 |
| 9 | `pets_allowed` | `VARCHAR(50)` | Critical for tenant (pet policy gate); 24/25 | Pet policy filter for rental matching + Ask AI | 4 |
| 10 | `furnished` | `VARCHAR(50)` | Critical for tenant (furnished toggle); 9/25 sale sample (higher in rental feed) | Furnished/unfurnished filter for rental matching + Ask AI | 4 |
| 11 | `garage_yn` | `BOOLEAN` | High for buyer (top-5 national filter); 25/25 | Garage toggle filter for buyer matching | 3 |
| 12 | `pool_private_yn` | `BOOLEAN` | High for buyer (FL primary feature); 25/25 | Pool toggle filter for buyer matching | 3 |
| 13 | `waterfront_yn` | `BOOLEAN` | High for buyer (FL premium differentiator); 25/25 | Waterfront toggle for buyer matching | 3 |
| 14 | `tax_annual_amount` | `DECIMAL(10,2)` | High for buyer (true ownership cost filter); 25/25; Ask AI | Tax cost filter + Ask AI cost-of-ownership answers | 3 |
| 15 | `lot_size_sqft` | `INTEGER` | High for buyer (lot size range filter); 25/25; Ask AI | Lot size range filter + Ask AI lot dimension answers | 3 |
| 16 | `association_yn` | `BOOLEAN` | Medium for buyer + tenant (HOA existence gate); 25/25 | HOA filter — gates `association_fee` queries | 2 |
| 17 | `new_construction_yn` | `BOOLEAN` | Medium for buyer; alert trigger for construction milestone; 25/25 | New construction filter + construction milestone alerts | 2 |
| 18 | `view_yn` | `BOOLEAN` | Medium for buyer + tenant (view feature toggle); 25/25 | View toggle filter for both engines | 2 |
| 19 | `water_view_yn` | `BOOLEAN` | Medium for buyer + tenant (FL water view toggle); 25/25 | Water view toggle for both engines | 2 |
| 20 | `cdd_yn` | `BOOLEAN` | Medium for buyer (FL CDD cost gate); 25/25; Ask AI | CDD filter for buyer matching + Ask AI cost answers | 2 |

### Phase 2 Promotions (Recommended After Phase 1 Lands)

These fields are valuable but lower priority — either because they are below the matching critical path or because their source data (rental feed) is not yet active:

**General (Phase 2):**
`subdivision_name` (VARCHAR 150), `mls_area_major` (VARCHAR 200), `original_list_price` (DECIMAL 15,2), `bathrooms_full` (TINYINT), `garage_spaces` (TINYINT), `previous_list_price` (DECIMAL 15,2), `price_change_timestamp` (TIMESTAMP), `status_change_timestamp` (TIMESTAMP), `days_on_market` (SMALLINT)

**Rental Feed (Promote when For Lease feed is active — Phase 2R):**
`lease_considered_yn` (BOOLEAN), `for_lease_yn` (BOOLEAN), `long_term_yn` (BOOLEAN), `lease_amount_frequency` (VARCHAR 50), `monthly_rent` (DECIMAL 10,2), `lease_term` (VARCHAR 100), `minimum_lease` (VARCHAR 50), `availability_date` (DATE), `security_deposit` (DECIMAL 10,2), `max_pets_allowed` (TINYINT)

---

## 9. Estimated Matching Readiness

### Methodology

Readiness is calculated as: **(native Tier 1 fields after Phase 1 promotions) ÷ (total Tier 1 fields required for that feature)**

Fields counted as "native after Phase 1" include both existing native columns and the 20 promoted columns from Section 8. Fields that remain raw_json (investment fields, low-population rental fields, display-only fields) are not counted.

### Before Phase 1 (Current State)

| Feature | Native Tier 1 Fields | Total Tier 1 Fields Required | Readiness |
|---|---|---|---|
| Buyer matching | 9 | 43 | **21%** |
| Tenant matching | 8 | 44 | **18%** |
| Alert system | 3 | 10 key trigger fields | **30%** |
| Ask AI MLS context | 5 | 43 high/medium priority | **12%** |

### After Phase 1 (Post-Promotion)

| Feature | Native Tier 1 Fields | Total Tier 1 Fields Required | Readiness | Remaining Gap |
|---|---|---|---|---|
| Buyer matching | 26 | 43 | **~60%** | MLSAreaMajor, SubdivisionName, OriginalListPrice, BathroomsFull, GarageSpaces, investment fields, Zoning |
| Tenant matching | 22 | 44 | **~50%** | All rental-specific fields require For Lease feed (LeaseConsideredYN, LeaseTerm, AvailabilityDate, MonthlyRent, etc.) |
| Alert system | 5 | 10 | **~50%** | PreviousListPrice, PriceChangeTimestamp, StatusChangeTimestamp, OriginalListPrice, DaysOnMarket remain raw_json |
| Ask AI MLS context | 19 | 43 | **~44%** | Free-text + array fields intentionally remain raw_json (context payload — no indexing needed) |

### After Phase 1 + Phase 2 (Full Promotion Target)

| Feature | Estimated Readiness | Notes |
|---|---|---|
| Buyer matching | **~85%** | Remaining 15% = investment-feed-only fields (CapRate, GrossIncome) with 0/25 population |
| Tenant matching | **~80%** | Requires Phase 2R rental feed promotions; remaining 20% = display-only and utility fields |
| Alert system | **~90%** | After Phase 2 alert trigger columns; remaining 10% = rare event types needing only per-import detection |
| Ask AI MLS context | **~60%** | Ceiling — remaining 40% are array/free-text fields that are intentionally embedded AI context only, not native |

### Critical Readiness Notes

1. **Buyer matching at 60% is sufficient to launch a basic matching engine** — the 26 native fields after Phase 1 cover all critical dimensions (location, price, size, type, key amenity flags). The remaining 17 fields are secondary filters that can be applied post-query using raw_json extraction on the already-filtered result set.

2. **Tenant matching at 50% is not sufficient to launch a tenant matching engine** — the missing rental-specific fields (`LeaseConsideredYN`, `LeaseTerm`, `AvailabilityDate`, `monthly_rent`) are the gating dimensions. Tenant matching launch is blocked on both Phase 2R column promotions AND enabling the Stellar For Lease feed.

3. **Alert system at 50% can send new listing and match alerts** — price-change and status-change alerts require Phase 2 promotions before they can run as efficient scheduled comparison jobs.

4. **Ask AI context readiness does not follow the same curve as matching** — the 44% figure reflects which fields are native, but Ask AI actually has access to all populated raw_json fields at query time. The practical Ask AI readiness (access to the data, even if via JSON extraction) is closer to **100% for reliably-populated fields** and limited only by fields with 0/25 population in the current sample.
