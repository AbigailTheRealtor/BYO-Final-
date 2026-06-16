# Stellar Buyer Matching Engine Architecture

> Document type: Architecture design record
> Date: 2026-06-16
> Source audits:
>   - `docs/audits/STELLAR_MATCHING_READINESS_AUDIT.md` (field source of truth)
>   - `docs/audits/STELLAR_NATIVE_COLUMN_EXPANSION_STRATEGY.md` (column promotion strategy)
>   - `docs/audits/STELLAR_BRIDGE_FIELD_AUDIT.md` (raw field inventory)
> Scope: Documentation only — no code, no migrations, no schema changes

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Matching Inputs — Buyer Criteria to Stellar Field Mapping](#2-matching-inputs--buyer-criteria-to-stellar-field-mapping)
3. [Hard Filters vs Soft Scoring](#3-hard-filters-vs-soft-scoring)
4. [Recommended Scoring Model](#4-recommended-scoring-model)
5. [Match Explanation Blueprint](#5-match-explanation-blueprint)
6. [Location DNA Integration](#6-location-dna-integration)
7. [Data Requirements](#7-data-requirements)
8. [Performance Strategy](#8-performance-strategy)
9. [Edge Cases](#9-edge-cases)
10. [Implementation Roadmap](#10-implementation-roadmap)

---

## 1. Executive Summary

### What the Engine Does

The Stellar Buyer Matching Engine scores active Stellar MLS listings against a buyer's saved criteria — location, price, property type, size, and feature preferences — and returns a ranked list of listings ordered by match quality. Each result carries a numeric score, a human-readable explanation of why it matches, any tradeoffs, and any caution flags (CDD present, flood zone data absent, age-restricted community, etc.).

The engine is designed exclusively for **for-sale residential buyer matching**. Tenant (rental) matching is a separate, future document and is explicitly out of scope here.

### Field Universe

The readiness audit classified 553 total Stellar/Bridge API fields. The buyer matching engine draws from the following tiers:

| Tier | Label | Count | Role in Engine |
|---|---|---|---|
| 1 | Core Matching | 66 | Primary scoring inputs; must be native columns for WHERE/ORDER BY |
| 2 | Match Enhancement | 85 | Soft scoring signals; extracted from raw_json on candidate set |
| 3 | Ask AI Context | 116 | Passed to Ask AI for explanation generation; not used in scoring |
| 4 | Alert & Recommendation | 51 | Alert triggers; not used in match scoring |
| 5 | Search Filters (primary) | 12 | Discrete search filters; not scored |
| 6 | Compliance / Excluded | 223 | **Hard excluded — must never appear in any scoring or explanation section** |

### Current Native Column Gap

The `bridge_properties` table currently has **13 native columns**. Of the **66 Tier 1 Core Matching** fields, only **9 are already native columns**. The remaining 57 Tier 1 fields live only in `raw_json`, which makes indexed WHERE clauses, range scans, and ORDER BY operations impossible at scale.

**Current Tier 1 coverage: 9 of 66 fields (14%)**

The Phase 1 promotion of 20 fields (documented in `STELLAR_NATIVE_COLUMN_EXPANSION_STRATEGY.md`) will raise buyer matching readiness from 21% to approximately 60% — sufficient to launch a basic matching engine covering all critical dimensions.

### Three-Phase Approach

| Phase | Name | Gate | Buyer Matching Readiness |
|---|---|---|---|
| Phase 1 | Basic Matching | 20 native column promotions landed | ~60% Tier 1 coverage |
| Phase 2 | Enhanced Scoring | Phase 2 column promotions + Location DNA radius search | ~85% Tier 1 coverage |
| Phase 3 | AI Explanations and Recommendations | Phase 1 + Phase 2 complete | Full scored results with natural-language copy |

### Compliance Boundary

**No Tier 6 field may appear in any scoring category, match explanation, caution flag, or column promotion for matching purposes.** The 23 explicitly compliance-flagged fields — plus ~200 additional agent, brokerage, admin, and commercial fields classified Tier 6 — are hard-excluded from every part of this engine. This boundary is non-negotiable and is enforced at the data layer, not the application layer.

---

## 2. Matching Inputs — Buyer Criteria to Stellar Field Mapping

This section documents how each of the 14 buyer criteria dimensions maps to Stellar MLS fields and which fields are already native columns vs. raw_json only at the time of writing.

### 2.1 — Location

| Buyer Criterion | Stellar Field(s) | Native Column? | Notes |
|---|---|---|---|
| City | `City` | **Yes** — `city` | Exact match filter |
| County | `CountyOrParish` | **No** — raw_json | Phase 1 promotion to `county_or_parish` |
| ZIP code | `PostalCode` | **Yes** — `postal_code` | Exact match filter |
| Latitude (radius search) | `Latitude` | **No** — raw_json | Phase 1 promotion to `latitude DECIMAL(10,7)` |
| Longitude (radius search) | `Longitude` | **No** — raw_json | Phase 1 promotion to `longitude DECIMAL(10,7)` |
| Subdivision / Community | `SubdivisionName` | **No** — raw_json | Phase 2 promotion to `subdivision_name` |
| MLS sub-market area | `MLSAreaMajor` | **No** — raw_json | Phase 2 promotion to `mls_area_major` |

### 2.2 — Price

| Buyer Criterion | Stellar Field(s) | Native Column? | Notes |
|---|---|---|---|
| List price (current) | `ListPrice` | **Yes** — `list_price` | Hard filter: must be ≤ buyer maximum |
| Original list price | `OriginalListPrice` | **No** — raw_json | Phase 2 promotion; used to compute price reduction percentage for soft scoring |

### 2.3 — Property Type and Subtype

| Buyer Criterion | Stellar Field(s) | Native Column? | Notes |
|---|---|---|---|
| Property type | `PropertyType` | **Yes** — `property_type` | Hard filter: exact match required |
| Property subtype | `PropertySubType` | **No** — raw_json | Phase 1 promotion to `property_sub_type`; SFR vs. Condo vs. Townhouse vs. Villa |

### 2.4 — Bedrooms and Bathrooms

| Buyer Criterion | Stellar Field(s) | Native Column? | Notes |
|---|---|---|---|
| Total bedrooms | `BedroomsTotal` | **Yes** — `bedrooms_total` | Hard filter: listing must have ≥ buyer minimum |
| Total bathrooms | `BathroomsTotalInteger` | **Yes** — `bathrooms_total_integer` | Hard filter: listing must have ≥ buyer minimum (use integer, not decimal) |
| Full bathrooms | `BathroomsFull` | **No** — raw_json | Phase 2 promotion to `bathrooms_full`; precision soft-scoring dimension |
| Half bathrooms | `BathroomsHalf` | **No** — raw_json | Remains raw_json; display detail only, not a filter |

### 2.5 — Square Footage (Living Area)

| Buyer Criterion | Stellar Field(s) | Native Column? | Notes |
|---|---|---|---|
| Living area (sqft) | `LivingArea` | **Yes** — `living_area` | Range soft-scoring; no hard minimum unless buyer specifies |

### 2.6 — Lot Size

| Buyer Criterion | Stellar Field(s) | Native Column? | Notes |
|---|---|---|---|
| Lot size (sqft) | `LotSizeSquareFeet` | **No** — raw_json | Phase 1 promotion to `lot_size_sqft`; canonical lot size field |
| Lot size (acres) | `LotSizeAcres` | **No** — raw_json | Remains raw_json; redundant with sqft; display convenience only |

### 2.7 — Year Built

| Buyer Criterion | Stellar Field(s) | Native Column? | Notes |
|---|---|---|---|
| Year built (decade/era range) | `YearBuilt` | **No** — raw_json | Phase 1 promotion to `year_built SMALLINT` |

### 2.8 — Garage

| Buyer Criterion | Stellar Field(s) | Native Column? | Notes |
|---|---|---|---|
| Has garage (boolean) | `GarageYN` | **No** — raw_json | Phase 1 promotion to `garage_yn BOOLEAN` |
| Garage spaces (count) | `GarageSpaces` | **No** — raw_json | Phase 2 promotion to `garage_spaces TINYINT` |

### 2.9 — Pool

| Buyer Criterion | Stellar Field(s) | Native Column? | Notes |
|---|---|---|---|
| Private pool (boolean) | `PoolPrivateYN` | **No** — raw_json | Phase 1 promotion to `pool_private_yn BOOLEAN`; top-5 Florida buyer preference |

### 2.10 — Waterfront and Water View

| Buyer Criterion | Stellar Field(s) | Native Column? | Notes |
|---|---|---|---|
| Waterfront (boolean) | `WaterfrontYN` | **No** — raw_json | Phase 1 promotion to `waterfront_yn BOOLEAN` |
| Water view (boolean) | `STELLAR_WaterViewYN` | **No** — raw_json | Phase 1 promotion to `water_view_yn BOOLEAN` |
| Has any view | `ViewYN` | **No** — raw_json | Phase 1 promotion to `view_yn BOOLEAN` |

### 2.11 — HOA / CDD / Taxes

| Buyer Criterion | Stellar Field(s) | Native Column? | Notes |
|---|---|---|---|
| HOA exists | `AssociationYN` | **No** — raw_json | Phase 1 promotion to `association_yn BOOLEAN` |
| Monthly HOA fee | `AssociationFee` | **No** — raw_json | Phase 1 promotion to `association_fee DECIMAL(10,2)` |
| CDD exists (Florida) | `STELLAR_CDDYN` | **No** — raw_json | Phase 1 promotion to `cdd_yn BOOLEAN`; CDD amount estimated from `TaxOtherAnnualAssessmentAmount` (raw_json) |
| Annual tax burden | `TaxAnnualAmount` | **No** — raw_json | Phase 1 promotion to `tax_annual_amount DECIMAL(10,2)` |

### 2.12 — Senior Community (Legal Compliance)

| Buyer Criterion | Stellar Field(s) | Native Column? | Notes |
|---|---|---|---|
| Age-restricted community | `SeniorCommunityYN` | **No** — raw_json | Phase 1 promotion to `senior_community_yn BOOLEAN` |

**Legal compliance note:** `SeniorCommunityYN` must be treated as a **hard filter**, not a soft scoring dimension. When a buyer does not indicate they are 55+ eligible, listings where `SeniorCommunityYN = true` must be excluded from results entirely. Fair Housing Act and Housing for Older Persons Act (HOPA) requirements apply. Under no circumstances may this flag be used to boost or penalize scores for non-55+ buyers — it is a binary eligibility gate only.

### 2.13 — New Construction

| Buyer Criterion | Stellar Field(s) | Native Column? | Notes |
|---|---|---|---|
| New construction | `NewConstructionYN` | **No** — raw_json | Phase 1 promotion to `new_construction_yn BOOLEAN` |

### 2.14 — Pet Policy (Buyer Context Only)

| Buyer Criterion | Stellar Field(s) | Native Column? | Notes |
|---|---|---|---|
| Pet-friendly community | `PetsAllowed` | **No** — raw_json | Phase 1 promotion to `pets_allowed VARCHAR(50)` |

**Scope boundary:** For buyer matching, `PetsAllowed` is relevant **only when it reflects HOA or community-level restrictions on pet ownership** (e.g., a deed-restricted community that prohibits pets entirely). Detailed rental pet policy dimensions — pet deposits, monthly pet fees, weight limits, maximum pet count — belong exclusively in the Tenant Matching Engine document. These fields (`STELLAR_PetDepositFee`, `STELLAR_PetMonthlyFee`, `STELLAR_MaxPetWeight`, `STELLAR_NumberOfPets`, `STELLAR_PetSize`) are not scoring inputs for buyer matching.

---

## 3. Hard Filters vs Soft Scoring

### 3.1 — Definitions

**Hard filter:** A listing is excluded from results entirely if it fails this criterion. Hard filters reduce the candidate set before any scoring occurs. A listing that fails a hard filter receives no score and is never shown.

**Soft scoring dimension:** A listing is included in results but receives a lower score. The buyer may see it ranked lower or see a "tradeoff" note in the match explanation. Soft scoring reflects preference fit, not eligibility.

### 3.2 — Hard Exclusion Filters

These criteria are binary eligibility gates. A listing that fails any one of them is not shown.

| Filter | Stellar Field | Native Column | Logic |
|---|---|---|---|
| Active status | `StandardStatus` | **Yes** — `standard_status` | Must equal `'Active'` — Closed, Pending, Withdrawn listings are never shown |
| Property type match | `PropertyType` | **Yes** — `property_type` | Must exactly match buyer's selected property type |
| Price within buyer maximum | `ListPrice` | **Yes** — `list_price` | Must be ≤ buyer's maximum budget; listings above the ceiling are excluded |
| Minimum bedroom count | `BedroomsTotal` | **Yes** — `bedrooms_total` | Must be ≥ buyer's minimum if specified; omitted if buyer has no bedroom requirement |
| Minimum bathroom count | `BathroomsTotalInteger` | **Yes** — `bathrooms_total_integer` | Must be ≥ buyer's minimum if specified; omitted if buyer has no bathroom requirement |
| Senior community gate | `SeniorCommunityYN` | Phase 1 → `senior_community_yn` | If buyer is NOT 55+ eligible, listings where `senior_community_yn = true` are excluded; see legal compliance note in Section 2.12 |

### 3.3 — Soft Scoring Dimensions

Listings that pass all hard filters enter the scoring stage. These dimensions each contribute to the final score:

| Dimension | Stellar Fields | Scoring Mechanism |
|---|---|---|
| Price fit within buyer range | `ListPrice`, `OriginalListPrice` | How close the price is to the buyer's ideal (not just under ceiling); proximity decay |
| Geographic proximity | `Latitude`, `Longitude`, `City`, `PostalCode`, `CountyOrParish` | Distance from buyer's preferred center point; decreasing score with distance |
| Location quality match | `MLSAreaMajor`, `SubdivisionName`, `CountyOrParish` | Exact match bonuses for buyer's preferred county / MLS area / subdivision |
| Living area fit | `LivingArea` | Range fit percentage against buyer's preferred sqft range |
| Lot size preference | `LotSizeSquareFeet` | Range fit against buyer's preferred lot size range |
| Year built preference | `YearBuilt` | Decade-era match against buyer's preferred construction era |
| Garage preference | `GarageYN`, `GarageSpaces` | Boolean match; garage count bonus |
| Pool preference | `PoolPrivateYN` | Boolean match |
| Waterfront / water view preference | `WaterfrontYN`, `STELLAR_WaterViewYN`, `ViewYN` | Boolean match; tiered: waterfront > water view > view |
| HOA / CDD financial burden | `AssociationFee`, `AssociationYN`, `STELLAR_CDDYN`, `TaxAnnualAmount` | Total monthly ownership cost vs. buyer's acceptable fee tolerance |
| New construction preference | `NewConstructionYN` | Boolean match; only scores if buyer indicated preference |
| Pet-friendly community | `PetsAllowed` | Boolean community-level match; only scores if buyer indicated need |
| Property subtype match | `PropertySubType` | Exact match bonus (SFR, Condo, Townhouse, etc.) |
| Lifestyle / context signals | `CommunityFeatures`, `AssociationAmenities`, `GreenEnergyEfficient`, `SecurityFeatures` | Enhancement-layer signals from Tier 2 raw_json fields; contribute to Lifestyle/Context category score |

### 3.4 — Fields Explicitly Excluded from Hard Filters and Scoring

The following fields are **never used as hard filters or soft scoring inputs**, regardless of buyer preferences expressed:

- All Tier 6 (Compliance/Excluded) fields — including all agent PII, license numbers, lockbox details, showing instructions
- `ElementarySchool`, `HighSchool`, `MiddleOrJuniorSchool`, `HighSchoolDistrict` — school data is not normalized; passed to Ask AI context only
- `STELLAR_FloodZoneCode` — raw FEMA zone codes (X, AE, VE) are not a risk tier; passed as caution flag context only until a translation table is built
- Rental-specific fields (`LeaseTerm`, `Furnished`, `AvailabilityDate`, `STELLAR_CurrencyMonthlyRentAmt`) — irrelevant to for-sale buyer matching

---

## 4. Recommended Scoring Model

### 4.1 — Category Weights

All seven categories sum to exactly **100 points**. Each listed category score contributes to the total match score for a listing.

| # | Category | Weight | Rationale |
|---|---|---|---|
| 1 | Location | 30 | Location is the primary non-negotiable buyer criterion; proximity and geography drive more decisions than any other factor |
| 2 | Price | 25 | Budget fit is the second most critical dimension; buyers rarely stretch beyond their ceiling and reward close price-to-budget proximity |
| 3 | Size | 15 | Bedroom count is already a hard filter; living area and lot size are soft preferences that vary widely by buyer type |
| 4 | Property Type | 10 | Type is often a hard filter (buyers know if they want a condo vs. SFR); subtype precision scoring adds the residual 10 points |
| 5 | Amenities | 10 | Pool, garage, waterfront, and water view are top-5 Florida buyer differentiators; individually binary but collectively decisive |
| 6 | Financial / Fees | 5 | HOA, CDD, and tax burden are real ownership costs but rarely disqualify a listing; they refine the ranking |
| 7 | Lifestyle / Context | 5 | Community features, energy efficiency, security, and new construction signal lifestyle fit; enhancement layer from Tier 2 fields |
| **Total** | | **100** | |

### 4.2 — Location (30 points)

**Contributing Stellar fields:** `City`, `PostalCode`, `CountyOrParish`, `Latitude`, `Longitude`, `MLSAreaMajor`, `SubdivisionName`

| Sub-dimension | Max Points | Computation |
|---|---|---|
| Radius proximity | 18 | Haversine distance from buyer's preferred center point; full 18 points at 0 miles; linear decay to 0 points at buyer's maximum radius; listings beyond max radius were already excluded by hard filter |
| City / ZIP exact match | 6 | +6 if listing city matches any of buyer's preferred cities or ZIP matches any preferred ZIP; +3 if county matches only |
| MLS sub-market match | 3 | +3 if `MLSAreaMajor` matches buyer's preferred MLS area (Phase 2 — requires `mls_area_major` column) |
| Subdivision match | 3 | +3 if `SubdivisionName` matches buyer's specified subdivision preference (Phase 2 — requires `subdivision_name` column) |

**Phase 1 behavior:** Only radius proximity and city/ZIP exact match are available. Sub-market and subdivision bonuses require Phase 2 column promotions.

### 4.3 — Price (25 points)

**Contributing Stellar fields:** `ListPrice`, `OriginalListPrice`

| Sub-dimension | Max Points | Computation |
|---|---|---|
| Price proximity to ideal | 20 | If buyer specifies a target price (not just ceiling): full 20 at exact match; linear decay to 10 points at 10% below ideal; linear decay to 0 points at buyer's ceiling. If buyer specifies ceiling only: full 20 if listing is ≤90% of ceiling; 15 if 90–100%; 0 if at ceiling |
| Price reduction signal | 5 | +5 if `original_list_price > list_price` (listing has been reduced); indicates seller motivation; +0 if no price change or no original price data |

**Phase 1 behavior:** Price reduction signal requires `original_list_price` native column (Phase 2). Phase 1 uses ceiling proximity only.

### 4.4 — Property Type (10 points)

**Contributing Stellar fields:** `PropertyType`, `PropertySubType`

| Sub-dimension | Max Points | Computation |
|---|---|---|
| Property type exact match | 5 | Type match is usually a hard filter; these 5 points reward listings where type is an exact match when buyer's type preference is "flexible" rather than a strict filter |
| Property subtype match | 5 | +5 if `PropertySubType` exactly matches buyer's subtype preference (e.g., "Single Family Residence" vs. "Condominium"); +2 if same broad class (Phase 1: requires `property_sub_type` column) |

### 4.5 — Size (15 points)

**Contributing Stellar fields:** `LivingArea`, `LotSizeSquareFeet`, `YearBuilt`

| Sub-dimension | Max Points | Computation |
|---|---|---|
| Living area fit | 7 | If buyer specifies a range: full 7 if listing is within range; decay from 7→0 as listing deviates from range edges (linear, capped at ±30% outside range = 0); full 7 if buyer has no sqft preference |
| Lot size fit | 4 | Same range-fit percentage calculation as living area; 0 if buyer has no lot size preference |
| Year built fit | 4 | +4 if `year_built` is within buyer's preferred decade/era range; +2 if within ±10 years of range edge; +0 if outside range or year_built is null (Phase 1: requires `year_built` column) |

### 4.6 — Amenities (10 points)

**Contributing Stellar fields:** `GarageYN`, `GarageSpaces`, `PoolPrivateYN`, `WaterfrontYN`, `STELLAR_WaterViewYN`, `ViewYN`

Each amenity preference is an independent boolean match. The 10 points are distributed across whichever amenities the buyer indicated as preferences. If a buyer has no amenity preferences, this category defaults to full score (10 points).

| Amenity | Points (when buyer prefers it) | Computation |
|---|---|---|
| Private pool | Up to 4 | +4 if buyer prefers pool and `pool_private_yn = true`; −4 if buyer prefers pool and pool absent |
| Garage | Up to 3 | +3 if buyer prefers garage and `garage_yn = true`; garage space count adds +0.5 per space beyond minimum (Phase 2), max +1 bonus |
| Waterfront | Up to 2 | +2 if buyer prefers waterfront and `waterfront_yn = true`; +1 partial if water view only (`water_view_yn = true`) |
| Any view | Up to 1 | +1 if buyer prefers a view and `view_yn = true` or `water_view_yn = true` |

**Normalization:** The total amenity sub-scores are normalized to the 10-point category maximum. If buyer selected only one amenity (e.g., pool), pool scoring scales to 10.

### 4.7 — Financial / Fees (5 points)

**Contributing Stellar fields:** `AssociationYN`, `AssociationFee`, `STELLAR_CDDYN`, `TaxAnnualAmount`

| Sub-dimension | Max Points | Computation |
|---|---|---|
| Total monthly financial burden fit | 5 | Compute total estimated monthly burden: `(association_fee ?? 0) + (tax_annual_amount ?? 0) / 12`. If buyer specifies a maximum monthly fee tolerance, score on a decay curve: full 5 if burden is 0; linear decay to 0 if burden equals buyer's tolerance ceiling. If buyer has no fee preference, this sub-dimension scores full 5 for all listings |
| CDD flag penalty | 0 (no score contribution) | CDD presence is a **caution flag** (see Section 5), not a scoring penalty; scoring must not embed a discriminatory penalty for a legally valid community type |

### 4.8 — Lifestyle / Context (5 points)

**Contributing Stellar fields (Tier 2 raw_json):** `CommunityFeatures`, `AssociationAmenities`, `GreenEnergyEfficient`, `GreenBuildingVerificationType`, `SecurityFeatures`, `NewConstructionYN`, `PetsAllowed`

This category is computed after the candidate set is already reduced by hard filters and primary scoring. Fields are extracted from `raw_json` per record.

| Sub-dimension | Points | Computation |
|---|---|---|
| Community features overlap | 2 | Match buyer's lifestyle keywords (e.g., "Golf", "Tennis", "Pool", "Clubhouse", "Playground") against `CommunityFeatures` and `AssociationAmenities` arrays; +2 if ≥2 matches; +1 if 1 match; +0 if none or buyer has no preference |
| Green / energy efficiency | 1 | +1 if `GreenEnergyEfficient` or `GreenBuildingVerificationType` is populated and buyer indicated interest in energy-efficient homes |
| New construction preference | 1 | +1 if buyer prefers new construction and `new_construction_yn = true`; requires `new_construction_yn` native column (Phase 1) |
| Pet-friendly community | 1 | +1 if buyer needs a pet-friendly community and `pets_allowed` indicates pets are allowed; requires `pets_allowed` native column (Phase 1) |

---

## 5. Match Explanation Blueprint

Every matched listing returns four explanation output blocks. These blocks are produced after scoring is complete and are consumed by both the UI display layer and Phase 3 Ask AI natural-language generation.

### 5.1 — "Why This Matches" (Positive Signals)

Lists the dimensions where the listing scored strongly. Each entry is a structured object that maps directly to a scoring dimension.

```
{
  "why_this_matches": [
    {
      "dimension": "location",
      "label": "In Lake Nona, your preferred area",
      "fields_used": ["city", "postal_code"],
      "score_contribution": 24
    },
    {
      "dimension": "price",
      "label": "Listed at $360,000 — 10% below your $400,000 budget",
      "fields_used": ["list_price"],
      "score_contribution": 20
    },
    {
      "dimension": "amenities",
      "label": "Private pool — matches your preference",
      "fields_used": ["pool_private_yn"],
      "score_contribution": 4
    }
  ]
}
```

**Rules:**
- Only include dimensions where `score_contribution > 0`.
- Label language is descriptive and non-technical (no Stellar field names visible to users).
- `fields_used` is internal metadata for debugging and Phase 3 Ask AI prompt building only.

### 5.2 — "Tradeoffs" (Partial Matches)

Lists dimensions where the listing is a partial match — it passed the hard filter but scored below its maximum possible contribution.

```
{
  "tradeoffs": [
    {
      "dimension": "price",
      "label": "Price is 5% above your ideal — at the upper end of your range",
      "fields_used": ["list_price"],
      "deviation": "5%_above_ideal"
    },
    {
      "dimension": "size",
      "label": "Living area is 2,800 sqft — slightly below your 3,000 sqft preference",
      "fields_used": ["living_area"],
      "deviation": "-200_sqft"
    },
    {
      "dimension": "amenities",
      "label": "No private pool — but community pool is available",
      "fields_used": ["pool_private_yn", "community_features_raw"],
      "deviation": "no_private_pool"
    }
  ]
}
```

**Rules:**
- Only include dimensions where `0 < score_contribution < max_for_dimension`.
- A tradeoff entry is not a rejection — the listing still matched. Language must be neutral ("slightly below" not "fails to meet").
- Community-level amenities sourced from `CommunityFeatures` (raw_json) may be offered as a compensating note.

### 5.3 — "Missing Data" (Fields That Would Have Improved the Score)

Lists fields that are null or unpopulated for this specific listing and would have contributed to a higher score if present.

```
{
  "missing_data": [
    {
      "field": "YearBuilt",
      "label": "Year built not available — age of home could not be evaluated",
      "dimension": "size",
      "impact": "up_to_4_points"
    },
    {
      "field": "LotSizeSquareFeet",
      "label": "Lot size not listed — lot size preference could not be scored",
      "dimension": "size",
      "impact": "up_to_4_points"
    }
  ]
}
```

**Rules:**
- Only include fields that the buyer actually has a preference for (i.e., those fields contributed to the scoring model for this buyer's query).
- Do not surface missing data for fields the buyer did not indicate a preference for.
- `impact` is expressed as a point range, not an exact number (data is missing so exact impact cannot be known).

### 5.4 — "Caution Flags" (Risk Signals)

Lists known risk signals or data gaps that a buyer should be aware of before proceeding. Caution flags are not score penalties — they are informational.

```
{
  "caution_flags": [
    {
      "flag": "cdd_present",
      "label": "CDD fee applies — contact listing agent for annual CDD amount",
      "field": "cdd_yn",
      "severity": "informational"
    },
    {
      "flag": "flood_zone_data_absent",
      "label": "Flood zone data unavailable — verify flood insurance requirements",
      "field": "STELLAR_FloodZoneCode",
      "severity": "informational"
    },
    {
      "flag": "school_district_not_normalized",
      "label": "School information present but not verified — confirm district boundaries directly",
      "field": "ElementarySchool",
      "severity": "informational"
    },
    {
      "flag": "senior_community",
      "label": "Age-restricted community (55+) — verify eligibility before proceeding",
      "field": "senior_community_yn",
      "severity": "eligibility_warning"
    },
    {
      "flag": "listing_stale",
      "label": "Listing has been on market for 90+ days — verify current status with agent",
      "field": "DaysOnMarket",
      "severity": "informational"
    }
  ]
}
```

**Flag inventory:**

| Flag Key | Trigger Condition | Severity |
|---|---|---|
| `cdd_present` | `cdd_yn = true` | `informational` |
| `flood_zone_data_absent` | `STELLAR_FloodZoneCode` present but FEMA translation table not yet built | `informational` |
| `school_district_not_normalized` | `ElementarySchool` or `HighSchool` populated but no normalized district lookup | `informational` |
| `senior_community` | `senior_community_yn = true` and buyer eligibility unknown | `eligibility_warning` |
| `listing_stale` | `DaysOnMarket` ≥ threshold (configurable; suggested: 60 days) | `informational` |
| `price_reduced` | `original_list_price > list_price` | `informational` (positive signal) |
| `reduced_confidence_geo_match` | `latitude` or `longitude` is null; match was geo-degraded to ZIP/county | `informational` |

**Rules:**
- `eligibility_warning` flags must be shown prominently and never hidden.
- `informational` flags may be collapsed or summarized in compact list views.
- No Tier 6 field name may appear in any caution flag label shown to users.
- Caution flag presence does not reduce the listing's score.

---

## 6. Location DNA Integration

The platform's Location DNA system captures buyer geographic preferences at multiple levels of precision: city, county, ZIP code, radius (miles from a center point), polygon boundary, subdivision, and MLS area. This section defines how each DNA layer interacts with Stellar listing coordinates.

### 6.1 — Radius-Based Search (Primary Method)

**Requires:** `latitude` and `longitude` native columns (Phase 1 promotions).

After Phase 1, the matching engine applies a Haversine bounding-box pre-filter as the first step in the WHERE clause, then computes precise distance for each candidate:

```sql
-- Bounding-box pre-filter (uses B-tree index on latitude, longitude)
WHERE
    standard_status = 'Active'
    AND latitude  BETWEEN (:center_lat - :radius_miles / 69.0)
                      AND (:center_lat + :radius_miles / 69.0)
    AND longitude BETWEEN (:center_lng - :radius_miles / 53.0)
                      AND (:center_lng + :radius_miles / 53.0)

-- Haversine precise distance (applied as HAVING on the bounded result set)
HAVING (
    3959 * ACOS(
        COS(RADIANS(:center_lat)) * COS(RADIANS(latitude))
        * COS(RADIANS(longitude) - RADIANS(:center_lng))
        + SIN(RADIANS(:center_lat)) * SIN(RADIANS(latitude))
    )
) <= :radius_miles
```

The bounding-box degrees-per-mile constants (69.0 for latitude, 53.0 for longitude) are approximate for central Florida at ~28°N latitude and are accurate to within 2% across Florida's latitude range.

**Distance is fed into the Location scoring category** (Section 4.2) as the primary proximity decay signal.

### 6.2 — Polygon / Boundary Matching

For buyer DNA expressed as a map-drawn polygon (e.g., "only show listings within this school boundary or neighborhood boundary"), the matching engine uses a two-step coarse-to-fine approach:

1. **Coarse gate:** Filter candidates using `county_or_parish` and `mls_area_major` native columns (Phase 2). This eliminates listings that are clearly outside the region without requiring per-row polygon intersection.
2. **Fine filter:** For candidates that pass the coarse gate, compute point-in-polygon using the listing's `latitude` / `longitude` against the buyer's polygon geometry in PHP. At the candidate-set scale (post hard-filter), this is O(n) on a small set and is acceptable without PostGIS.

**Future PostGIS migration path** (when volume or precision demands it): Add a `geo_point GEOGRAPHY(POINT, 4326)` column with a GIST index and replace the PHP polygon check with `ST_Within(geo_point, ST_GeomFromText(?))` as a native SQL predicate. The decimal `latitude` and `longitude` columns remain in place for display and Haversine use.

### 6.3 — ZIP Code Exact Match (Fallback)

When a listing's `latitude` and `longitude` are null (geo-data absent), the engine falls back to ZIP code exact match using the existing `postal_code` native column. This is a hard equality filter — the listing is included only if its ZIP is in the buyer's preferred ZIP list. The match is flagged with the `reduced_confidence_geo_match` caution flag (see Section 5.4).

ZIP fallback is also used as a coarse gate before radius search when the buyer specifies specific ZIP codes as the primary location preference (not a radius).

### 6.4 — County / MLS Area as Coarse Gates

`county_or_parish` (Phase 1 column) and `mls_area_major` (Phase 2 column) serve as cheap pre-filters before expensive geo-operations:

- **County gate:** If buyer's DNA includes county preferences, add `AND county_or_parish IN (...)` before the radius filter. This eliminates listings from irrelevant counties without computing distance.
- **MLS area gate:** If buyer specifies MLS sub-market preferences (`MLSAreaMajor`), add `AND mls_area_major IN (...)` as a secondary coarse gate. Available in Phase 2.

### 6.5 — Future Walk Score and Transit Score Integration

Walk Score and transit score data are **completely absent** from Stellar MLS — there is no Stellar field for walkability or transit access. If these signals are needed for buyer matching, a third-party API (Walk Score API at `https://api.walkscore.com/`) must be integrated separately.

The integration hook is designed as follows:
- After Phase 1, `latitude` and `longitude` are native columns — these are the required input for Walk Score API lookups.
- Walk scores should be fetched and cached per `listing_key` at sync time (not at query time) to avoid per-request API latency.
- Cached scores would be stored in a future `bridge_property_scores` table keyed by `listing_key`.
- Walk score and transit score would become additional Lifestyle/Context scoring inputs (Phase 3).

---

## 7. Data Requirements

### 7.1 — Field Classification Table

| Field | Stellar Field | Classification | Column Type | Notes |
|---|---|---|---|---|
| `standard_status` | `StandardStatus` | **Already native column** | `VARCHAR` | Hard filter gate; 25/25 populated |
| `property_type` | `PropertyType` | **Already native column** | `VARCHAR` | Hard filter; 25/25 populated |
| `list_price` | `ListPrice` | **Already native column** | `DECIMAL(15,2)` | Hard filter + price scoring; 25/25 |
| `city` | `City` | **Already native column** | `VARCHAR` | Location filter; 25/25 |
| `state_or_province` | `StateOrProvince` | **Already native column** | `VARCHAR` | State gate; 25/25 |
| `postal_code` | `PostalCode` | **Already native column** | `VARCHAR` | ZIP filter + geo fallback; 25/25 |
| `bedrooms_total` | `BedroomsTotal` | **Already native column** | `INTEGER` | Hard filter; 25/25 |
| `bathrooms_total_integer` | `BathroomsTotalInteger` | **Already native column** | `INTEGER` | Hard filter; 25/25 |
| `living_area` | `LivingArea` | **Already native column** | `INTEGER` | Size scoring; 25/25 |
| `latitude` | `Latitude` | **Must become native — Phase 1** | `DECIMAL(10,7)` | Radius search; 25/25 |
| `longitude` | `Longitude` | **Must become native — Phase 1** | `DECIMAL(10,7)` | Radius search; 25/25 |
| `county_or_parish` | `CountyOrParish` | **Must become native — Phase 1** | `VARCHAR(100)` | County filter + coarse geo gate; 25/25 |
| `property_sub_type` | `PropertySubType` | **Must become native — Phase 1** | `VARCHAR(100)` | Type scoring; 25/25 |
| `senior_community_yn` | `SeniorCommunityYN` | **Must become native — Phase 1** | `BOOLEAN` | Legal compliance hard filter; 25/25 |
| `mls_status` | `MlsStatus` | **Must become native — Phase 1** | `VARCHAR(50)` | Alert joins; board-specific status; 25/25 |
| `year_built` | `YearBuilt` | **Must become native — Phase 1** | `SMALLINT` | Size/era scoring; 25/25 |
| `association_fee` | `AssociationFee` | **Must become native — Phase 1** | `DECIMAL(10,2)` | Financial scoring; 22/25 |
| `association_yn` | `AssociationYN` | **Must become native — Phase 1** | `BOOLEAN` | HOA gate; 25/25 |
| `cdd_yn` | `STELLAR_CDDYN` | **Must become native — Phase 1** | `BOOLEAN` | CDD caution flag; 25/25 |
| `tax_annual_amount` | `TaxAnnualAmount` | **Must become native — Phase 1** | `DECIMAL(10,2)` | Financial scoring; 25/25 |
| `lot_size_sqft` | `LotSizeSquareFeet` | **Must become native — Phase 1** | `INTEGER` | Size scoring; 25/25 |
| `garage_yn` | `GarageYN` | **Must become native — Phase 1** | `BOOLEAN` | Amenity scoring; 25/25 |
| `pool_private_yn` | `PoolPrivateYN` | **Must become native — Phase 1** | `BOOLEAN` | Amenity scoring; 25/25 |
| `waterfront_yn` | `WaterfrontYN` | **Must become native — Phase 1** | `BOOLEAN` | Amenity scoring; 25/25 |
| `view_yn` | `ViewYN` | **Must become native — Phase 1** | `BOOLEAN` | Amenity scoring; 25/25 |
| `water_view_yn` | `STELLAR_WaterViewYN` | **Must become native — Phase 1** | `BOOLEAN` | Amenity scoring; 25/25 |
| `new_construction_yn` | `NewConstructionYN` | **Must become native — Phase 1** | `BOOLEAN` | Lifestyle scoring + alert trigger; 25/25 |
| `pets_allowed` | `PetsAllowed` | **Must become native — Phase 1** | `VARCHAR(50)` | Lifestyle scoring; 24/25 |
| `subdivision_name` | `SubdivisionName` | **Must become native — Phase 2** | `VARCHAR(150)` | Location scoring bonus; 25/25 |
| `mls_area_major` | `MLSAreaMajor` | **Must become native — Phase 2** | `VARCHAR(200)` | Location scoring + coarse geo gate; 25/25 |
| `original_list_price` | `OriginalListPrice` | **Must become native — Phase 2** | `DECIMAL(15,2)` | Price reduction scoring; 25/25 |
| `bathrooms_full` | `BathroomsFull` | **Must become native — Phase 2** | `TINYINT` | Size precision scoring; 25/25 |
| `garage_spaces` | `GarageSpaces` | **Must become native — Phase 2** | `TINYINT` | Amenity scoring (count bonus); 25/25 |
| `previous_list_price` | `PreviousListPrice` | **Must become native — Phase 2** | `DECIMAL(15,2)` | Price change alert trigger; 22/25 |
| `price_change_timestamp` | `PriceChangeTimestamp` | **Must become native — Phase 2** | `TIMESTAMP` | Price alert trigger; 22/25 |
| `status_change_timestamp` | `StatusChangeTimestamp` | **Must become native — Phase 2** | `TIMESTAMP` | Status alert trigger; 25/25 |
| `PublicRemarks` | `PublicRemarks` | **Can remain raw_json** | — | Ask AI text context; per-record extraction is O(1); 25/25 |
| `CommunityFeatures` | `CommunityFeatures` | **Can remain raw_json** | — | Lifestyle scoring; array; 23/25 |
| `AssociationAmenities` | `AssociationAmenities` | **Can remain raw_json** | — | Lifestyle scoring; array; 18/25 |
| `InteriorFeatures` | `InteriorFeatures` | **Can remain raw_json** | — | Ask AI context; array; 24/25 |
| `ExteriorFeatures` | `ExteriorFeatures` | **Can remain raw_json** | — | Ask AI context; array; 22/25 |
| `Appliances` | `Appliances` | **Can remain raw_json** | — | Ask AI context; array; 25/25 |
| `Heating` / `Cooling` | `Heating`, `Cooling` | **Can remain raw_json** | — | Ask AI context; arrays; 25/25 |
| `Flooring` | `Flooring` | **Can remain raw_json** | — | Ask AI context; array; 25/25 |
| `ElementarySchool` | `ElementarySchool` | **Can remain raw_json** | — | Ask AI context only; not normalized; 20/25 |
| `HighSchool` | `HighSchool` | **Can remain raw_json** | — | Ask AI context only; not normalized; 21/25 |
| `STELLAR_FloodZoneCode` | `STELLAR_FloodZoneCode` | **Can remain raw_json** | — | Caution flag context; FEMA code not yet translated; 17/25 |
| `LotSizeAcres` | `LotSizeAcres` | **Can remain raw_json** | — | Redundant with `lot_size_sqft`; display only; 25/25 |
| `BathroomsHalf` | `BathroomsHalf` | **Can remain raw_json** | — | Display detail; low filter frequency; 25/25 |
| `GreenEnergyEfficient` | `GreenEnergyEfficient` | **Can remain raw_json** | — | Lifestyle scoring; array; 23/25 |
| `SecurityFeatures` | `SecurityFeatures` | **Can remain raw_json** | — | Lifestyle scoring; array; 23/25 |
| `CapRate` | `CapRate` | **Can remain raw_json** | — | Investment buyers only; 0/25 in sale sample |
| `GrossIncome` | `GrossIncome` | **Can remain raw_json** | — | Investment context; 0/25 in sale sample |
| All Tier 6 fields | (23 compliance + ~200 admin) | **Hard excluded — never promote** | — | No Tier 6 field may appear in any column promotion for matching purposes |

### 7.2 — External Data Requirements

| Data Gap | Impact | Recommended Source | Integration Phase |
|---|---|---|---|
| School district / school quality | High — top buyer concern | Geocode-to-district API (e.g., GreatSchools API) using native `latitude`/`longitude` after Phase 1 | Phase 3 |
| Walk Score / Transit Score | Medium — urban and suburban buyers | Walk Score API using native `latitude`/`longitude` after Phase 1; cache per `listing_key` at sync time | Phase 3 |
| Flood zone risk tier | High — Florida specific | FEMA NFHL API or zone-code-to-risk translation table; `STELLAR_FloodZoneCode` provides the FEMA zone code (X, AE, VE) but human-readable risk tier is not yet computed | Phase 3 |

### 7.3 — Compliance Boundary Enforcement

No Tier 6 field may appear in any column promotion for matching, any scoring formula, any match explanation, or any caution flag label shown to users. The specific fields that are hard-excluded include but are not limited to:

All agent and brokerage PII (`ListAgentEmail`, `ListAgentPreferredPhone`, `ListOfficePhone`, `ListAgentStateLicense`, `BuyerAgentStateLicense`, `CoListAgentStateLicense`, `CoBuyerAgentStateLicense`), license numbers (`License1`, `License2`, `License3`, `STELLAR_BuilderLicenseNumber`), lockbox and access details (`LockBoxLocation`, `LockBoxSerialNumber`, `LockBoxType`, `STELLAR_ShowingRequirements`, `STELLAR_ShowingConsiderations`), contact numbers (`STELLAR_CallCenterPhoneNumber`, `STELLAR_PropertyManagerPhone`, `STELLAR_TenantPhone`, `Telephone`), and internal-only content (`STELLAR_TenantName`, `STELLAR_RealtorInfoConfidential`, `STELLAR_SoldRemarks`).

---

## 8. Performance Strategy

### 8.1 — Two-Layer Architecture

The matching engine uses a two-layer approach to ensure acceptable performance as the `bridge_properties` table grows:

**Layer 1 — Database-side hard filtering (indexed native columns):**
The SQL query uses only native indexed columns in the WHERE clause. This reduces the full table to a small candidate set of listings that pass all hard filters. At this layer, no `raw_json` extraction occurs.

**Layer 2 — PHP service-layer soft scoring (raw_json extraction per candidate):**
The candidate set returned by Layer 1 is scored in PHP. For each candidate record, `raw_json` extraction is performed to read the Tier 2 enhancement fields needed for soft scoring (e.g., `CommunityFeatures`, `GreenEnergyEfficient`, `AssociationAmenities`). This extraction is O(1) per record. Because the candidate set is small (post hard-filter), the total cost is O(n) on a small n — fully acceptable.

### 8.2 — Recommended Query-First Pattern

```
Step 1: WHERE clause on native columns
         → Hard filters only (status, type, price, beds, baths, senior community, geo bounding-box)
         → Reduces table to a candidate set

Step 2: ORDER BY price proximity
         → ORDER BY ABS(list_price - :buyer_ideal_price) ASC
         → Ranks candidates by price closeness within the candidate set

Step 3: LIMIT to top N candidates
         → LIMIT :candidate_cap (suggested default: 200)
         → Caps the PHP scoring layer input at a manageable size

Step 4: Score in PHP
         → Soft scoring across all seven categories
         → raw_json extraction per record for Tier 2 fields
         → Haversine precise distance computation per record
         → Build explanation blocks per record

Step 5: Rank and return
         → Sort scored results by total_score DESC
         → Apply result-page pagination
         → Return scored listings with explanation payloads
```

**The `:candidate_cap` value** balances completeness against PHP processing time. A cap of 200 candidates means the PHP layer never processes more than 200 `raw_json` extractions regardless of how many listings pass the hard filters. If the buyer's query would produce more than 200 hard-filter passes, the ORDER BY in Step 2 ensures the 200 nearest-price candidates are scored (the most likely to rank highly anyway). The cap is tunable without schema changes.

### 8.3 — Indexing Requirements

| Column | Index Type | Rationale |
|---|---|---|
| `standard_status` | B-tree | First hard filter; every query includes this |
| `list_price` | B-tree | Range filter and ORDER BY in every price query |
| `property_type` | B-tree | Hard filter in every query |
| `city` | B-tree | Exact match filter |
| `postal_code` | B-tree | Exact match filter |
| `bedrooms_total` | B-tree | Range filter (≥ minimum) |
| `bathrooms_total_integer` | B-tree | Range filter (≥ minimum) |
| `(latitude, longitude)` | Composite B-tree | Bounding-box pre-filter for radius search; **required after Phase 1 promotion** |
| `county_or_parish` | B-tree | County equality filter |
| `senior_community_yn` | B-tree | Boolean hard filter; small cardinality but legally required as indexed gate |
| `property_sub_type` | B-tree | Type equality filter |
| `pool_private_yn`, `garage_yn`, `waterfront_yn` | B-tree (individual) | Boolean hard filters when buyer specifies amenity requirement |

### 8.4 — Anti-Pattern: Raw JSON Extraction Without Pre-Filtering

Running buyer matching queries that extract from `raw_json` on the full `bridge_properties` table — without first narrowing via native column WHERE clauses — is unacceptable at scale. Examples of the prohibited pattern:

```sql
-- PROHIBITED: Full table scan with JSON extraction
SELECT * FROM bridge_properties
WHERE (raw_json->>'PoolPrivateYN')::boolean = true
  AND (raw_json->>'Latitude')::float BETWEEN 27.0 AND 29.0;

-- ACCEPTABLE: Native column pre-filter, then candidate-set JSON extraction in PHP
SELECT * FROM bridge_properties
WHERE standard_status = 'Active'
  AND pool_private_yn = true
  AND latitude BETWEEN 27.0 AND 29.0
  AND longitude BETWEEN -82.0 AND -80.0
LIMIT 200;
-- Then extract CommunityFeatures, GreenEnergyEfficient, etc. from raw_json in PHP per record
```

This anti-pattern prohibition applies equally to reporting queries, alert scans, and any scheduled jobs that touch the `bridge_properties` table.

---

## 9. Edge Cases

### 9.1 — Missing Latitude / Longitude

**Scenario:** A listing has null `latitude` and/or `longitude` after Phase 1 promotion.

**Handling:**
1. Skip the Haversine radius filter for this listing.
2. Fall back to ZIP code exact match: include the listing only if `postal_code` is in the buyer's preferred ZIP list.
3. If ZIP match succeeds, include the listing with a score penalty: the Location/proximity sub-score (18 points) defaults to 0; city/ZIP match sub-scores are still awarded normally.
4. Append the `reduced_confidence_geo_match` caution flag to this listing's explanation.
5. If ZIP also fails and the buyer's DNA is radius-only (no explicit city or ZIP specified), exclude the listing entirely.

**Population note:** `Latitude` and `Longitude` are 25/25 in the current residential-for-sale sample. Missing lat/lng is expected to be rare for active for-sale listings but must be handled gracefully.

### 9.2 — Closed / Sold Listings

**Scenario:** A listing's `StandardStatus` is `Closed`, `Pending`, `Withdrawn`, `Expired`, or anything other than `Active`.

**Handling:** The `WHERE standard_status = 'Active'` hard filter in Layer 1 (Section 8.2) excludes these listings entirely. They never reach the scoring layer and never appear in match results, regardless of any other criteria match. There is no fallback, partial display, or "recently sold" context mode — that is a separate display feature outside the matching engine scope.

### 9.3 — Stale Listings

**Scenario:** A listing is `Active` but has been on the market for an unusually long time.

**Handling:**
1. The listing is **not excluded** — it remains in results.
2. `DaysOnMarket` is extracted from `raw_json` for candidates in the scoring layer.
3. If `DaysOnMarket` ≥ a configurable staleness threshold (suggested default: 60 days), append the `listing_stale` caution flag to the explanation.
4. If `CumulativeDaysOnMarket` (also raw_json) is significantly higher than `DaysOnMarket`, both are surfaced in the caution context for Phase 3 Ask AI explanation generation.
5. No score penalty is applied — a stale listing may still be the best match by score; the buyer is informed, not blocked.

### 9.4 — Missing List Price

**Scenario:** A listing has a null `list_price` (the field is not populated).

**Handling:**
1. If the buyer has specified a maximum price, exclude the listing from results (the hard filter `list_price <= :buyer_max` cannot be evaluated — exclude for safety).
2. If the buyer has **no price ceiling** specified, include the listing but assign it a price category score of 0 (price cannot be scored without data).
3. Append a missing data entry for `ListPrice` in the explanation block (Section 5.3).

**Population note:** `ListPrice` is 25/25 in the current sample. This edge case is a defensive guard for data completeness issues in future feed expansions.

### 9.5 — Multiple Property Types in Buyer Criteria

**Scenario:** A buyer's DNA includes multiple property type preferences (e.g., "Single Family Residence" and "Townhouse").

**Handling:**
1. Run a **separate candidate query** per property type: one WHERE clause for `property_type = 'Residential' AND property_sub_type = 'Single Family Residence'`, another for `property_sub_type = 'Townhouse'`.
2. Collect all candidate sets.
3. Deduplicate by `listing_key` before scoring (a listing will not appear in multiple type result sets, but defensive deduplication is required).
4. Score the merged candidate set as a unified pool.
5. Do not reduce the score for listings of "secondary" preferred types — all listed type preferences are equal.

### 9.6 — Flood Zone Data Gap

**Scenario:** `STELLAR_FloodZoneCode` is present for a listing (populated in 17/25 of current sample), but the FEMA zone code-to-risk-tier translation table has not yet been built.

**Handling:**
1. Never use the raw FEMA zone code (X, AE, VE, etc.) as a scoring input or hard filter — buyers are not expected to interpret FEMA codes.
2. Append the `flood_zone_data_absent` caution flag to all listings where `STELLAR_FloodZoneCode` is populated, until the translation table is built.
3. Once the translation table exists (Phase 3 external data integration), the zone code maps to a risk tier: Minimal Risk (X), Moderate Risk (AE), High Risk (VE, AH, AO). The risk tier is then surfaced as a caution flag severity level (not a score).
4. Listings where `STELLAR_FloodZoneCode` is null receive no flood zone caution flag — absence of data is not the same as high flood risk.

### 9.7 — School District Data Gap

**Scenario:** `ElementarySchool` and `HighSchool` fields are populated (20/25 and 21/25) but contain free-text school names, not normalized district identifiers.

**Handling:**
1. School fields are **never used as hard filters or scoring inputs**.
2. The raw school name strings are passed to the Ask AI context payload for Phase 3 natural-language explanation generation only.
3. Buyers who specify school preferences receive a caution flag: `school_district_not_normalized` — informing them that school information comes from the listing and should be verified directly.
4. A geocode-to-school-district API integration (e.g., GreatSchools) is the recommended Phase 3 resolution. After integration, the returned school district and ratings are stored per listing_key and may become Phase 3 scoring inputs.
5. `HighSchoolDistrict` is 0/25 in the current sample and must not be used at all until population improves.

---

## 10. Implementation Roadmap

### Phase 1 — Basic Matching

**Gate:** 20 native column promotions from `STELLAR_NATIVE_COLUMN_EXPANSION_STRATEGY.md` Section 8 must be migrated and indexed before this phase can launch.

**Deliverables:**

1. **Column promotion migration** — Add the 20 Phase 1 columns to `bridge_properties`:
   `latitude`, `longitude`, `county_or_parish`, `property_sub_type`, `senior_community_yn`, `mls_status`, `year_built`, `association_fee`, `pets_allowed`, `furnished`, `garage_yn`, `pool_private_yn`, `waterfront_yn`, `tax_annual_amount`, `lot_size_sqft`, `association_yn`, `new_construction_yn`, `view_yn`, `water_view_yn`, `cdd_yn`.
   Add composite B-tree index on `(latitude, longitude)`.

2. **Stellar sync update** — Update the Bridge API sync job to populate all 20 new native columns from `raw_json` at import time.

3. **Hard-filter query** — Implement `BuyerMatchQueryBuilder` service that constructs the Layer 1 WHERE clause using native columns: `standard_status`, `property_type`, `list_price`, `bedrooms_total`, `bathrooms_total_integer`, `senior_community_yn`, and the bounding-box lat/lng filter.

4. **Basic scoring** — Implement `BuyerMatchScorer` PHP service that receives the candidate set and scores Location (city/ZIP match + Haversine proximity) and Price (ceiling proximity). Total score from these two categories: up to 55 points.

5. **Ranked results** — Return listings sorted by score descending. No explanation blocks in Phase 1 — return score and matched dimension labels only.

**Buyer matching readiness after Phase 1:** ~60% Tier 1 coverage. All critical dimensions available (location, price, size, type, key amenity flags).

---

### Phase 2 — Enhanced Scoring

**Gate:** Phase 1 must be live. Phase 2 column promotions must be migrated: `subdivision_name`, `mls_area_major`, `original_list_price`, `bathrooms_full`, `garage_spaces`, `previous_list_price`, `price_change_timestamp`, `status_change_timestamp`.

**Deliverables:**

1. **Full seven-category scoring** — Extend `BuyerMatchScorer` to compute all seven scoring categories (Section 4) using the full field set now available as native columns and raw_json extraction on the candidate set.

2. **Location DNA radius search** — Activate the Haversine radius scoring sub-dimension using the now-indexed `latitude`/`longitude` columns. Add county and MLS area coarse gate layers.

3. **Amenity scoring** — Implement amenity preference matching for pool, garage, waterfront, view, water view using the Phase 1 native boolean columns. Add garage spaces count bonus using Phase 2 `garage_spaces` column.

4. **HOA / CDD / tax burden scoring** — Implement financial burden computation: `association_fee + (tax_annual_amount / 12)` vs. buyer's fee tolerance.

5. **Lifestyle / Context scoring** — Implement Tier 2 raw_json extraction for `CommunityFeatures`, `AssociationAmenities`, `GreenEnergyEfficient`, and `SecurityFeatures` per candidate record. Score community feature overlap.

6. **Match explanation blocks** — Implement all four explanation output types (Section 5): "Why this matches," "Tradeoffs," "Missing data," and "Caution flags."

7. **Caution flag system** — Implement the full caution flag inventory from Section 5.4: CDD present, senior community eligibility warning, flood zone data absent, school district not normalized, listing stale.

8. **Price-change and status-change alerts** — Use the Phase 2 `previous_list_price`, `price_change_timestamp`, and `status_change_timestamp` columns to power price reduction and status change buyer alert jobs.

**Buyer matching readiness after Phase 2:** ~85% Tier 1 coverage. Remaining 15% = investment-feed-only fields (CapRate, GrossIncome) with 0/25 population in the residential sample.

---

### Phase 3 — AI Explanations and Recommendations

**Gate:** Phase 1 + Phase 2 must be complete. External data integrations (Walk Score, flood zone translation, school district API) are additive and do not block Phase 3 launch.

**Deliverables:**

1. **Ask AI natural-language match explanations** — Pass the Phase 2 structured explanation blocks (Section 5) to the existing Ask AI pipeline as context. Generate natural-language "Why this matches your criteria" copy per listing. The Ask AI prompt receives: buyer criteria summary, listing's structured explanation blocks, and the listing's Tier 3 Ask AI context fields from `raw_json` (PublicRemarks, CommunityFeatures, InteriorFeatures, etc.).

2. **"Why this matches your criteria" copy** — Display AI-generated explanation copy on listing cards and listing detail pages. Each listing card shows a 1–2 sentence match summary. The detail page shows the full structured explanation with the natural-language overlay.

3. **Walk Score integration** — After Walk Score API is integrated (separate task), cached walk score and transit score per listing_key are added as Lifestyle/Context scoring sub-dimensions. Walk score ≥ 70 adds +1 to the Lifestyle category for buyers who indicated walkability preference.

4. **Flood zone risk tier** — After FEMA zone-code translation table is built, replace the `flood_zone_data_absent` caution flag with a tiered risk caution flag: Minimal / Moderate / High. High-risk zones receive an `eligibility_warning` severity flag.

5. **School district scoring** — After geocode-to-school-district API integration, school district and ratings are added as a Phase 3 scoring input. Implement as an optional Lifestyle/Context sub-dimension (not a hard filter).

6. **Recommendation engine hooks** — Expose `match_score`, `score_breakdown`, and `explanation` payloads via internal API for the property alert system to use when sending "New match found" buyer alert emails.

---

*This document is the authoritative architecture specification for the Stellar Buyer Matching Engine. All field references use canonical names from `STELLAR_MATCHING_READINESS_AUDIT.md`. All native column promotion decisions are consistent with `STELLAR_NATIVE_COLUMN_EXPANSION_STRATEGY.md`. No implementation, migration, or schema change is part of this document.*
