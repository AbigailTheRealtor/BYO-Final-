# Stellar Tenant Matching Engine Architecture

> Document type: Architecture design record  
> Date: 2026-06-16  
> Source audits: `docs/audits/STELLAR_MATCHING_READINESS_AUDIT.md` · `docs/audits/STELLAR_NATIVE_COLUMN_EXPANSION_STRATEGY.md` · `docs/audits/STELLAR_BRIDGE_FIELD_AUDIT.md`  
> Scope: Architecture and documentation only — no code changes, no migrations, no implementation

---

> **Implementation gate:** Tenant matching must not be implemented until the Stellar rental/for-lease feed is confirmed to return populated rental records with non-null values for the core rental-specific fields (`LeaseConsideredYN`, `LeaseTerm`, `Furnished`, `PetsAllowed`, `LeaseAmountFrequency`, `AvailabilityDate`). This gate applies to all three roadmap phases — Phase 1 does not begin until live rental data is verified.

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Rental Feed Dependency](#2-rental-feed-dependency)
3. [Matching Inputs](#3-matching-inputs)
4. [Hard Filters vs Soft Scoring](#4-hard-filters-vs-soft-scoring)
5. [Recommended Scoring Model](#5-recommended-scoring-model)
6. [Tenant Match Explanation Blueprint](#6-tenant-match-explanation-blueprint)
7. [Data Requirements](#7-data-requirements)
8. [Rental-Specific Edge Cases](#8-rental-specific-edge-cases)
9. [Implementation Roadmap](#9-implementation-roadmap)

---

## 1. Executive Summary

### Purpose

This document establishes the full architecture for a tenant-to-Stellar-rental matching engine — covering matching inputs, scoring model, explanation templates, data requirements, edge cases, and a phased implementation roadmap. Its goal is that when the Stellar rental/for-lease feed is confirmed active and populating data, implementation can begin immediately without additional design work.

### Why Tenant Matching Is Different From Buyer Matching

Buyer matching operates against residential-for-sale listings where critical dimensions (price, location, beds/baths, sqft, pool, garage) are reliably populated across the current 25-record sample and mostly already native `bridge_properties` columns. Tenant matching depends on a fundamentally different field set — lease-specific fields that are not present in for-sale records:

| Field | Buyer Matching | Tenant Matching |
|---|---|---|
| `ListPrice` | Sale price | Rent proxy only (ambiguous without `LeaseAmountFrequency`) |
| `LeaseConsideredYN` | Irrelevant | Rental-vs-sale gate |
| `LeaseTerm` | Irrelevant | Minimum lease length filter |
| `Furnished` | Irrelevant | Critical toggle (furnished/unfurnished) |
| `PetsAllowed` | Background context only | Hard gate filter |
| `AvailabilityDate` | Irrelevant | Move-in date compatibility filter |
| `LeaseAmountFrequency` | Irrelevant | Weekly/monthly qualifier for rent amount |
| `STELLAR_CurrencyMonthlyRentAmt` | Irrelevant | Canonical rent (disambiguates from sale price) |

### Current Data Gap

All six primary rental-specific Tier 1 fields are **0/25 populated** in the current residential-for-sale sample:

| Field | Population in For-Sale Sample | Notes |
|---|---|---|
| `LeaseConsideredYN` | 0/25 | Only present in For Lease feed records |
| `LeaseTerm` | 0/25 | Only present in For Lease feed records |
| `Furnished` | 9/25 | Partially populated in for-sale sample (furnished homes); but context differs from rental furnished status |
| `PetsAllowed` | 24/25 | Present but reflects for-sale context (HOA rules), not rental policy |
| `LeaseAmountFrequency` | 0/25 | Only present in For Lease feed records |
| `AvailabilityDate` | 0/25 | Only present in For Lease feed records |

> Note: `Furnished` (9/25) and `PetsAllowed` (24/25) appear in the for-sale sample, but their semantics in a for-sale record differ from a rental record. The `Furnished` value on a for-sale listing describes whether the home is sold furnished; on a rental listing it describes the rental condition. `PetsAllowed` on a for-sale listing reflects HOA rules for owner-occupied units; on a rental listing it reflects landlord policy.

### Three-Phase Roadmap Headline

- **Phase 1** (After rental feed confirmed): Column promotions for rental-specific fields, hard-filter query layer, basic scored result set.
- **Phase 2** (Richer rental terms): Lease flexibility scoring, utility inclusion parsing, furnished/pet explanation copy, move-in alert triggers.
- **Phase 3** (AI explanations and proactive alerts): Natural-language match explanation via AI, proactive move-in date alerts, HOA approval flag warnings.

---

## 2. Rental Feed Dependency

### The Blocking Problem

The current `bridge_properties` table is populated exclusively from Stellar MLS residential-for-sale records. None of the rental-specific Tier 1 fields carry usable data from this feed:

| Field | Stellar API Key | Population (For-Sale Sample) | Why It's Critical |
|---|---|---|---|
| `LeaseConsideredYN` | `LeaseConsideredYN` | 0/25 | Without this, the engine cannot separate rental listings from sale listings |
| `LeaseTerm` | `LeaseTerm` | 0/25 | Without this, minimum lease length matching is impossible |
| `Furnished` | `Furnished` | 0/25 (rental context) | Core tenant preference dimension |
| `PetsAllowed` | `PetsAllowed` | 0/25 (rental context) | Hard gate — pet owners cannot evaluate a rental without this |
| `LeaseAmountFrequency` | `LeaseAmountFrequency` | 0/25 | `ListPrice` is ambiguous without the frequency qualifier (is it monthly or weekly?) |
| `AvailabilityDate` | `AvailabilityDate` | 0/25 | Move-in date range matching is impossible without this |

Because all six of these fields live only in `raw_json` and carry zero values from the current feed, **no meaningful tenant match query can be executed today**. Even a basic filter ("show me active rentals under $2,000/month that allow pets") would return 0 results or uninterpretable results.

### The For Lease Feed

Stellar MLS separates its listing universe by the `STELLAR_ForLeaseYN` flag and the `LeaseConsideredYN` boolean. Records with `LeaseConsideredYN = true` or `STELLAR_ForLeaseYN = true` are rental/for-lease listings. The current API configuration fetches residential-for-sale records only.

Enabling the For Lease feed requires:
1. Confirming that the Stellar API subscription/agreement covers the For Lease dataset.
2. Adding a query filter (`STELLAR_ForLeaseYN eq true` or `LeaseConsideredYN eq true`) to the Bridge Data Output fetch.
3. Verifying that returned records populate the six critical fields above with non-null values.
4. Running the Phase 2R column promotion migration (see `STELLAR_NATIVE_COLUMN_EXPANSION_STRATEGY.md` Section 8, "Rental Feed" block) before indexing the rental records.

### Column Promotion Pre-Requisite

`STELLAR_NATIVE_COLUMN_EXPANSION_STRATEGY.md` Section 4 establishes the full list of rental-specific fields that must be promoted from `raw_json` to native columns before the matching engine can run indexed queries against them. The eight gating fields are:

`lease_considered_yn`, `for_lease_yn`, `long_term_yn`, `lease_amount_frequency`, `monthly_rent`, `lease_term`, `minimum_lease`, `availability_date`

These must not be promoted before the rental feed is active — doing so creates nullable empty columns for all existing for-sale records and wastes schema space. The promotion should be executed in a dedicated rental-feed migration immediately after the feed is confirmed active and populated.

### Implementation Gate — Explicit Statement

**Tenant matching must not be implemented until the Stellar rental/for-lease feed is confirmed to return populated rental records.** "Confirmed" means: at least one batch of records has been fetched from the For Lease feed, spot-checked to verify non-null values for `LeaseConsideredYN`, `LeaseTerm`, `LeaseAmountFrequency`, and `AvailabilityDate`, and the Phase 2R column promotions have been applied and populated. This gate applies to all three roadmap phases — Phase 1 does not begin until live rental data is verified.

---

## 3. Matching Inputs

The following table maps each of the twelve tenant criteria dimensions to the corresponding Stellar field key, its current data type in the Bridge API response, its population rate in the 25-record for-sale audit sample, and whether it is currently a native `bridge_properties` column or lives only in `raw_json`.

| Dimension | Tenant Preference Field | Stellar Field Key | Data Type | Population (For-Sale Sample) | Storage |
|---|---|---|---|---|---|
| **Rent / Price** | `max_monthly_rent` | `STELLAR_CurrencyMonthlyRentAmt` | integer | 0/25 | `raw_json` only |
| **Rent frequency qualifier** | *(derived)* | `LeaseAmountFrequency` | string | 0/25 | `raw_json` only |
| **Rent fallback** | `max_monthly_rent` | `ListPrice` | integer | 25/25 | Native column (`list_price`) |
| **Location — city** | `preferred_city` | `City` | string | 25/25 | Native column (`city`) |
| **Location — ZIP** | `preferred_postal_code` | `PostalCode` | string | 25/25 | Native column (`postal_code`) |
| **Location — county** | `preferred_county` | `CountyOrParish` | string | 25/25 | `raw_json` only |
| **Location — radius** | `search_radius_miles` + `lat`/`lng` | `Latitude` / `Longitude` | float | 25/25 | `raw_json` only |
| **Property type** | `preferred_property_type` | `PropertyType` | string | 25/25 | Native column (`property_type`) |
| **Property subtype** | `preferred_property_subtype` | `PropertySubType` | string | 25/25 | `raw_json` only |
| **Bedrooms** | `min_bedrooms` | `BedroomsTotal` | integer | 25/25 | Native column (`bedrooms_total`) |
| **Bathrooms** | `min_bathrooms` | `BathroomsTotalInteger` | integer | 25/25 | Native column (`bathrooms_total_integer`) |
| **Square footage** | `min_sqft` | `LivingArea` | integer | 25/25 | Native column (`living_area`) |
| **Pets** | `has_pets` / `pet_type` | `PetsAllowed` | array | 24/25 (for-sale context) | `raw_json` only |
| **Furnished** | `requires_furnished` | `Furnished` | string | 9/25 (for-sale context) | `raw_json` only |
| **Lease term** | `preferred_lease_length_months` | `LeaseTerm` | string | 0/25 | `raw_json` only |
| **Minimum lease** | `preferred_lease_length_months` | `STELLAR_MinimumLease` | string | 0/25 | `raw_json` only |
| **Availability date** | `target_move_in_date` | `AvailabilityDate` | date | 0/25 | `raw_json` only |
| **Utilities / rent includes** | `requires_utilities_included` | `RentIncludes` | array | 0/25 | `raw_json` only |
| **Utilities / tenant pays** | `acceptable_utility_responsibility` | `TenantPays` | array | 0/25 | `raw_json` only |
| **Parking / garage** | `requires_garage` | `GarageYN` | boolean | 25/25 | `raw_json` only |
| **Garage count** | `min_garage_spaces` | `GarageSpaces` | integer | 25/25 | `raw_json` only |
| **Pool** | `requires_pool` | `PoolPrivateYN` | boolean | 25/25 | `raw_json` only |
| **Waterfront** | `requires_waterfront` | `WaterfrontYN` | boolean | 25/25 | `raw_json` only |
| **HOA / association approval** | `can_meet_hoa_requirements` | `STELLAR_AssociationApprovalRequiredYN` | boolean | 0/25 | `raw_json` only |
| **Senior community** | `is_senior_eligible` | `SeniorCommunityYN` | boolean | 25/25 | `raw_json` only |

### Field Availability Summary

| Dimension Group | Fields Available Now | Fields Requiring Rental Feed |
|---|---|---|
| Location | 5 (city, ZIP, state, beds, baths, sqft — native) | lat/lng, county (must be promoted) |
| Rent / price | `ListPrice` (native, ambiguous) | `monthly_rent`, `LeaseAmountFrequency` |
| Property classification | `PropertyType` (native) | `PropertySubType` (must be promoted) |
| Rental status gate | None | `LeaseConsideredYN`, `STELLAR_ForLeaseYN` |
| Pet / furnished / lease | None (0/25 in rental context) | `PetsAllowed`, `Furnished`, `LeaseTerm`, `AvailabilityDate` |
| Utilities | None (0/25) | `RentIncludes`, `TenantPays` |
| Parking / amenities | Available in `raw_json` (25/25) | Must be promoted for indexed queries |

---

## 4. Hard Filters vs Soft Scoring

### Hard Filters (Eliminate a Listing Outright)

A hard filter is a binary gate: if the rental fails this check, it is excluded from the result set entirely before scoring begins. These filters run in the SQL WHERE clause and must be native columns to perform efficiently.

| Filter | Stellar Field(s) | Rationale |
|---|---|---|
| **Active rental status** | `StandardStatus = 'Active'` AND (`LeaseConsideredYN = true` OR `for_lease_yn = true`) | A non-active listing cannot be rented; a for-sale listing cannot be matched to a tenant |
| **Rent ceiling** | `monthly_rent <= tenant.max_monthly_rent` (falling back to `list_price` when `monthly_rent` is null, only when `lease_amount_frequency = 'Monthly'`) | Tenants cannot afford listings above their stated maximum; showing out-of-budget listings harms trust |
| **Move-in date compatibility** | `availability_date <= tenant.target_move_in_date + buffer_days` | A rental not available until after the tenant's move-in window is unusable regardless of score |
| **Pet restrictions** | When `tenant.has_pets = true`, exclude listings where `PetsAllowed` array does not include an affirmative value (`Yes`, `Cats OK`, `Dogs OK`, etc.) | A tenant with pets cannot legally occupy a no-pets property; no score can overcome this |
| **Geographic radius** | Haversine distance from (`latitude`, `longitude`) to tenant's target location ≤ `search_radius_miles` | Properties outside the tenant's acceptable travel radius are not actionable matches |
| **Minimum bedrooms** | `bedrooms_total >= tenant.min_bedrooms` | Hard occupancy requirement; no score enhancement compensates for too few rooms |
| **Senior community eligibility** | When listing has `senior_community_yn = true`, only match tenants where `tenant.is_senior_eligible = true` | Fair Housing compliance — age-restricted communities cannot be shown to ineligible renters without qualification |

### Soft Scoring Criteria (Contribute to Ranked Score)

A soft criterion contributes points to the match score but does not eliminate the listing. Listings that partially satisfy a soft criterion still appear in results, ranked lower.

| Criterion | Stellar Field(s) | Scoring Approach | Rationale |
|---|---|---|---|
| **Size — beds/baths/sqft** | `BedroomsTotal`, `BathroomsTotalInteger`, `LivingArea` | Range proximity (exact match = full points; one unit below preference = partial credit; above preference = full points) | More rooms than needed is acceptable; fewer requires a trade-off the tenant may accept |
| **Interior amenities** | `InteriorFeatures`, `Appliances`, `Flooring` | Array intersection bonus | Tenants list nice-to-haves; matching more items scores higher |
| **Furnished state** | `Furnished` | Exact match bonus; partial match (semi-furnished) = half credit | Tenants with a furnished preference strongly benefit from exact match; unfurnished preference is unaffected by a furnished rental |
| **Lease term flexibility** | `LeaseTerm`, `STELLAR_MinimumLease` | Proximity scoring (preferred term ± 2 months = partial credit; exact match = full) | Tenants often have a preferred lease length but will accept nearby options |
| **Community features** | `CommunityFeatures`, `AssociationAmenities` | Array intersection bonus | Pool in community, gym, security — valued but not required |
| **Parking type** | `ParkingFeatures`, `GarageSpaces` | Attribute match bonus (covered > open; garage > carport) | Tenants may prefer but not require a specific parking type |
| **Utilities included** | `RentIncludes`, `TenantPays` | Array intersection with tenant's desired inclusions | Utility inclusion reduces effective cost and is highly valued, but a tenant can still rent without it |
| **HOA / association fees** | `AssociationFee`, `AssociationYN` | Penalty when HOA fee is unexpectedly high relative to rent; bonus when HOA is low/absent | HOA fees affect total monthly cost; low HOA is preferable but not a hard block |
| **Waterfront / view** | `WaterfrontYN`, `ViewYN`, `STELLAR_WaterViewYN` | Boolean bonus per matched preference | Lifestyle preferences — valued but not required |
| **Pool** | `PoolPrivateYN` | Boolean bonus | Highly valued FL amenity; scored softly unless tenant marks as required |
| **Year built / condition** | `YearBuilt`, `PropertyCondition` | Recency bonus (newer = more points up to cap); condition match bonus | Tenants prefer newer/well-maintained properties but rarely hard-filter by year |

---

## 5. Recommended Scoring Model

### Overview

The model is a **100-point weighted scoring system** across eight weight buckets. Each bucket evaluates specific Stellar fields and contributes a capped number of points to the total score. A perfect score of 100 represents a listing that exactly matches every stated preference.

### Scoring Buckets

| # | Bucket | Max Points | Stellar Fields Involved | Scoring Logic |
|---|---|---|---|---|
| 1 | **Location** | 25 | `Latitude`, `Longitude`, `City`, `PostalCode`, `CountyOrParish`, `SubdivisionName`, `MLSAreaMajor` | Distance decay: exact city/ZIP = 25 pts; within 2 miles = 20 pts; within 5 miles = 15 pts; within 10 miles = 10 pts; beyond 10 miles but within radius = 5 pts |
| 2 | **Rent** | 20 | `STELLAR_CurrencyMonthlyRentAmt` (primary), `ListPrice` (fallback when `LeaseAmountFrequency = 'Monthly'`) | Rent at or below max = 20 pts; rent within 5% above max = 12 pts; rent within 10% above max = 5 pts |
| 3 | **Availability** | 15 | `AvailabilityDate` | Available on or before target move-in = 15 pts; available 1–7 days after = 10 pts; available 8–14 days after = 5 pts; available 15–30 days after = 2 pts |
| 4 | **Beds / Baths / Size** | 15 | `BedroomsTotal`, `BathroomsTotalInteger`, `LivingArea` | Exact match on beds + baths = 10 pts; one unit above = 10 pts (bonus space); one unit below = 5 pts; sqft within 10% of preference = 5 pts; more than 10% below = 0 pts for size sub-bucket |
| 5 | **Pets / Furnished** | 10 | `PetsAllowed`, `Furnished` | Pets: if tenant has pets and listing is pet-friendly = 5 pts; not applicable = 5 pts (neutral). Furnished: exact match = 5 pts; partial (semi-furnished when tenant wants furnished) = 2 pts; mismatch = 0 pts |
| 6 | **Lease Terms** | 5 | `LeaseTerm`, `STELLAR_MinimumLease` | Exact match on preferred term = 5 pts; within ±2 months = 3 pts; within ±4 months = 1 pt; no data = 2 pts (neutral, not penalized) |
| 7 | **Amenities** | 5 | `PoolPrivateYN`, `WaterfrontYN`, `GarageYN`, `GarageSpaces`, `ViewYN`, `CommunityFeatures`, `AssociationAmenities`, `InteriorFeatures`, `Appliances` | 1 pt per matched preference from tenant's amenity checklist; capped at 5 pts total |
| 8 | **Fees / Utilities** | 5 | `AssociationFee`, `AssociationYN`, `RentIncludes`, `TenantPays`, `STELLAR_SecurityDeposit` | Utilities match (tenant's desired inclusions ∩ listing's `RentIncludes`): 1 pt per matched utility up to 3 pts; low/no HOA when tenant prefers it: 1 pt; security deposit within tenant's budget: 1 pt |

**Total: 100 points**

### Score Interpretation

| Score | Label | Meaning |
|---|---|---|
| 85–100 | Excellent match | Meets all critical criteria; strong alignment on preferences |
| 70–84 | Good match | Meets all hard criteria; most soft preferences satisfied |
| 55–69 | Fair match | Passes all hard filters; notable gaps on preferences |
| 40–54 | Weak match | Passes hard filters; significant preference mismatches |
| < 40 | Poor match | Technically eligible but far from stated preferences |

### Worked Example

A tenant has: max rent $2,200/mo, target move-in 2026-07-15, 2 beds, 1.5 baths, has a dog, wants furnished, prefers annual lease, no pool preference, 5-mile radius of ZIP 34747.

| Bucket | Listing Value | Points Awarded | Reason |
|---|---|---|---|
| Location | 2.1 miles from ZIP centroid | 20/25 | Within 2 miles (city match, not exact address match) |
| Rent | `monthly_rent = $2,100` | 20/20 | At or below max |
| Availability | `availability_date = 2026-07-18` | 10/15 | 3 days after target move-in |
| Beds/Baths/Size | 2 beds / 2 baths / 1,050 sqft | 12/15 | Exact beds match (5 pts), one bath above preference (5 pts), sqft within 10% (not specified — neutral 2 pts) |
| Pets/Furnished | `PetsAllowed = ["Dogs OK", "Cats OK"]`, `Furnished = "Furnished"` | 10/10 | Pet-friendly (5 pts) + exact furnished match (5 pts) |
| Lease Terms | `LeaseTerm = "12 Months"` | 5/5 | Exact match |
| Amenities | Garage (match), community pool (no preference) | 1/5 | 1 amenity matched |
| Fees/Utilities | `RentIncludes = ["Water", "Trash"]`, no HOA | 2/5 | 2 utility matches |
| **Total** | | **80/100** | **Good match** |

---

## 6. Tenant Match Explanation Blueprint

Each match result should be accompanied by a natural-language explanation that communicates why the rental ranks where it does. The explanation has four parts: top strengths, gaps, warnings, and timing notes.

### 6.1 — Top Strengths Template

**Template:** "This rental is a strong match because: [comma-separated list of top-scoring attributes]."

| Condition | Explanation Clause | Stellar Fields Driving It |
|---|---|---|
| Rent at or below max | "the rent ($X/mo) fits your budget" | `STELLAR_CurrencyMonthlyRentAmt`, `ListPrice` |
| Pet-friendly confirmed | "pets are welcome" | `PetsAllowed` |
| Furnished match | "the unit is [furnished / unfurnished] as you prefer" | `Furnished` |
| Exact lease term | "the lease length ([X] months) matches your preference" | `LeaseTerm`, `STELLAR_MinimumLease` |
| Available on time | "it is available [date], on or before your target move-in of [date]" | `AvailabilityDate` |
| Exact location | "it is [X] miles from your target area" | `Latitude`, `Longitude` |
| Utilities included | "water and trash are included in rent" | `RentIncludes` |
| Pool / waterfront | "the unit has a private pool" / "it is waterfront" | `PoolPrivateYN`, `WaterfrontYN` |

### 6.2 — Gap List Template

**Template:** "Heads up: [comma-separated list of gaps]."

| Condition | Gap Clause | Stellar Fields Driving It |
|---|---|---|
| Slightly over rent max | "rent is $[X] above your stated maximum" | `STELLAR_CurrencyMonthlyRentAmt`, `ListPrice` |
| Fewer beds than preferred | "this unit has [N] bedroom(s) — you prefer [M]" | `BedroomsTotal` |
| Unfurnished when furnished preferred | "this unit is unfurnished — you indicated a furnished preference" | `Furnished` |
| Lease term mismatch | "the minimum lease ([X] months) differs from your preference ([Y] months)" | `LeaseTerm`, `STELLAR_MinimumLease` |
| No utilities included | "utilities are not included — you will need to budget for them separately" | `RentIncludes` (absent or empty) |
| No garage | "this listing does not have a garage" | `GarageYN` |

### 6.3 — Pet / Fee Warnings Template

**Template:** "[Warning phrase]. Confirm before applying."

| Condition | Warning Copy | Stellar Fields Driving It |
|---|---|---|
| Pet approval required (not outright allowed) | "This property requires pet approval — confirm breed, size, and weight limits before applying." | `PetsAllowed` (contains "Call", "Negotiable", or similar conditional value), `STELLAR_PetSize`, `STELLAR_MaxPetWeight` |
| Pet deposit / monthly pet fee | "A pet deposit of $[X] and/or a monthly pet fee of $[Y]/mo applies." | `STELLAR_PetDepositFee`, `STELLAR_PetMonthlyFee` |
| HOA approval required | "This property requires HOA approval before move-in — the approval process may take [N] days." | `STELLAR_AssociationApprovalRequiredYN`, `STELLAR_ApprovalProcess` |
| Security deposit amount | "A security deposit of $[X] is required at lease signing." | `STELLAR_SecurityDeposit` |
| Application fee | "An application fee of $[X] applies." | `STELLAR_ApplicationFee` |

### 6.4 — Move-In Timing Notes Template

**Template:** "Timing: [availability statement relative to target move-in]."

| Condition | Copy | Stellar Fields Driving It |
|---|---|---|
| Available on or before target | "Available [date] — ready for your target move-in of [date]." | `AvailabilityDate` |
| Available shortly after target | "Available [date] — [N] days after your target move-in. A short-term arrangement may be needed to bridge the gap." | `AvailabilityDate` |
| Available significantly after target | "Available [date] — this is [N] days after your target move-in date of [date]. Consider whether this timeline works for you." | `AvailabilityDate` |
| No availability date on record | "Move-in availability is not listed — contact the property manager to confirm timing." | `AvailabilityDate` (null) |

---

## 7. Data Requirements

### Table A — Native Columns Already Available for Tenant Matching

These fields are native `bridge_properties` columns and can be used in indexed WHERE clauses today without any schema change:

| Native Column | Stellar Field | SQL Type | Tier | Notes |
|---|---|---|---|---|
| `standard_status` | `StandardStatus` | `VARCHAR` | 1 | Active rental gate |
| `property_type` | `PropertyType` | `VARCHAR` | 1 | Type filter |
| `city` | `City` | `VARCHAR` | 1 | Location filter |
| `state_or_province` | `StateOrProvince` | `VARCHAR` | 1 | State filter |
| `postal_code` | `PostalCode` | `VARCHAR` | 1 | ZIP code filter |
| `bedrooms_total` | `BedroomsTotal` | `INTEGER` | 1 | Bedroom min filter |
| `bathrooms_total_integer` | `BathroomsTotalInteger` | `INTEGER` | 1 | Bathroom min filter |
| `living_area` | `LivingArea` | `INTEGER` | 1 | Square footage filter |
| `list_price` | `ListPrice` | `DECIMAL(15,2)` | 1 | Rent proxy (ambiguous without monthly_rent; use only when `lease_amount_frequency = 'Monthly'`) |

**Total native columns available for tenant matching today: 9 of 44 required Tier 1 fields (20% readiness)**

### Table B — Rental-Specific Fields That Must Be Promoted From `raw_json`

These fields must be promoted to native `bridge_properties` columns before the matching engine can run. They are organized by promotion priority tier.

**Priority 1 — Blocking (must be promoted before any rental matching query can run):**

| Target Column | Stellar Field | SQL Type | Population (Rental Feed) | Reason |
|---|---|---|---|---|
| `lease_considered_yn` | `LeaseConsideredYN` | `BOOLEAN` | 0/25 for-sale (expected: high in rental feed) | Rental-vs-sale gate |
| `for_lease_yn` | `STELLAR_ForLeaseYN` | `BOOLEAN` | 0/25 for-sale | Rental listing gate |
| `monthly_rent` | `STELLAR_CurrencyMonthlyRentAmt` | `DECIMAL(10,2)` | 0/25 for-sale | Canonical rent amount |
| `lease_amount_frequency` | `LeaseAmountFrequency` | `VARCHAR(50)` | 0/25 for-sale | Monthly/weekly qualifier for list_price fallback |
| `availability_date` | `AvailabilityDate` | `DATE` | 0/25 for-sale | Move-in date filter |
| `lease_term` | `LeaseTerm` | `VARCHAR(100)` | 0/25 for-sale | Minimum lease filter |
| `pets_allowed` | `PetsAllowed` | `VARCHAR(100)` | 24/25 (for-sale context; rental context = 0/25) | Pet policy hard gate |
| `furnished` | `Furnished` | `VARCHAR(50)` | 9/25 (for-sale context) | Furnished toggle |

**Priority 2 — High value (needed for scoring and radius search; shared with buyer matching Phase 1):**

| Target Column | Stellar Field | SQL Type | Population | Reason |
|---|---|---|---|---|
| `latitude` | `Latitude` | `DECIMAL(10,7)` | 25/25 | Radius search (composite index with longitude) |
| `longitude` | `Longitude` | `DECIMAL(10,7)` | 25/25 | Radius search |
| `county_or_parish` | `CountyOrParish` | `VARCHAR(100)` | 25/25 | County WHERE clause |
| `property_sub_type` | `PropertySubType` | `VARCHAR(100)` | 25/25 | SFR vs. condo subtype filter |
| `garage_yn` | `GarageYN` | `BOOLEAN` | 25/25 | Parking filter |
| `pool_private_yn` | `PoolPrivateYN` | `BOOLEAN` | 25/25 | Amenity filter |
| `waterfront_yn` | `WaterfrontYN` | `BOOLEAN` | 25/25 | Waterfront filter |
| `association_yn` | `AssociationYN` | `BOOLEAN` | 25/25 | HOA existence gate |
| `association_fee` | `AssociationFee` | `DECIMAL(10,2)` | 22/25 | Monthly HOA cost |
| `senior_community_yn` | `SeniorCommunityYN` | `BOOLEAN` | 25/25 | Age-restriction compliance |

**Priority 3 — Phase 2R (promote alongside rental-specific Phase 1 promotion):**

| Target Column | Stellar Field | SQL Type | Population | Reason |
|---|---|---|---|---|
| `minimum_lease` | `STELLAR_MinimumLease` | `VARCHAR(50)` | 0/25 for-sale | Explicit minimum lease months |
| `long_term_yn` | `STELLAR_LongTermYN` | `BOOLEAN` | 0/25 for-sale | Separate long-term from vacation rentals |
| `mls_status` | `MlsStatus` | `VARCHAR(50)` | 25/25 | Board-specific rental status vocabulary |
| `year_built` | `YearBuilt` | `SMALLINT` | 25/25 | Condition/age preference |

**Fields that can remain in `raw_json` (display detail, not match filters):**

`STELLAR_NumberOfPets`, `STELLAR_MaxPetWeight`, `STELLAR_PetSize`, `STELLAR_PetDepositFee`, `STELLAR_PetMonthlyFee`, `STELLAR_AdditionalPetFees`, `STELLAR_MonthToMonthOrWeeklyYN`, `TenantPays`, `RentIncludes`, `STELLAR_LeaseRestrictionsYN`, `STELLAR_SecurityDeposit`, `STELLAR_AnnualRent`, `STELLAR_ApplicationFee`, `STELLAR_ApprovalProcess`, `STELLAR_AssociationApprovalRequiredYN`

Reference: `STELLAR_NATIVE_COLUMN_EXPANSION_STRATEGY.md` Section 4 (Tenant Matching Requirements) and Section 8, "Rental Feed (Phase 2R)" for the full promotion priority list and recommended SQL types.

---

## 8. Rental-Specific Edge Cases

### 8.1 — Weekly / Monthly Rent Ambiguity (`LeaseAmountFrequency` Normalization)

**Problem:** The `ListPrice` field on Stellar records is the canonical price field, but its meaning differs for rental vs. for-sale listings. On a rental listing, `ListPrice` may represent the monthly rent or the weekly rent depending on the `LeaseAmountFrequency` value. Without normalization, comparing a $500/week rental to a tenant's $2,200/month maximum will produce a false match.

**Handling rule:**
1. Always prefer `STELLAR_CurrencyMonthlyRentAmt` (`monthly_rent` column) as the canonical rent value — it is explicitly the monthly figure.
2. When `monthly_rent` is null, fall back to `ListPrice` **only** when `LeaseAmountFrequency = 'Monthly'`.
3. When `LeaseAmountFrequency = 'Weekly'`, convert: `effective_monthly_rent = ListPrice * 52 / 12`.
4. When `LeaseAmountFrequency` is null or any other value (e.g., `'Annually'`), do not apply the rent filter to that listing — flag it as "rent frequency unknown" in the explanation and exclude it from rent scoring.
5. Never compare a raw `ListPrice` from a rental listing directly to a tenant's monthly maximum without first checking `LeaseAmountFrequency`.

### 8.2 — `Furnished` Multi-Value Arrays

**Problem:** In some Stellar rental records, `Furnished` is returned as an array (e.g., `["Furnished", "Negotiable"]`) rather than a simple string. A naive string comparison will fail to detect the `Furnished` value.

**Handling rule:**
1. After fetching `Furnished` from `raw_json`, always normalize to a string array before comparison — even if the API returns a plain string, wrap it in an array for uniform processing.
2. Affirmative furnished values: `["Furnished", "Partially Furnished", "Turnkey"]`. Map all three to a "furnished available" match.
3. Negative furnished values: `["Unfurnished", "Negotiable"]`. Map `Negotiable` to a soft match (partial score) rather than a hard yes/no.
4. The promoted `furnished` native column should store the normalized string (`Furnished`, `Unfurnished`, `Partially Furnished`, `Negotiable`) — not the raw array — extracted at import time.
5. When computing scores for the Pets/Furnished bucket: exact match (tenant wants furnished, listing is Furnished) = full points; tenant wants furnished, listing is Partially Furnished = half points; tenant wants furnished, listing is Negotiable = quarter points.

### 8.3 — `PetsAllowed` Array Values vs Boolean Interpretation

**Problem:** Stellar returns `PetsAllowed` as an array of strings (e.g., `["Dogs OK", "Cats OK", "Small Pets Only", "Breed Restrictions", "Number Limit", "Yes", "No", "Call"]`). This is neither a simple boolean nor a normalized enum. A naive check for `true` or `"Yes"` will miss conditional approvals and produce incorrect results.

**Handling rule:**
1. **Hard NO group** (exclude listing for pet-owning tenants): `["No"]`, `["No Pets"]`, empty array, null.
2. **Hard YES group** (listing is pet-friendly — no further action needed): `["Yes"]`, `["Dogs OK"]`, `["Cats OK"]`, `["Dogs OK", "Cats OK"]`.
3. **Conditional group** (listing may allow pets — requires verification): `["Call"]`, `["Breed Restrictions"]`, `["Number Limit"]`, `["Size Limit"]`, `["Small Pets Only"]`, `["Negotiable"]`, `["Pet Approval Required"]`.
4. For hard NO: exclude from results when `tenant.has_pets = true` (hard filter).
5. For hard YES: include normally; mark pet-friendly in explanation.
6. For conditional: include in results; add pet approval warning (see Section 6.3); do not award full pet-score points — award partial points and flag the warning.
7. The promoted `pets_allowed` native column should store a normalized single-value enum: `Yes`, `No`, `Conditional` — extracted from the array at import time using the groupings above.

### 8.4 — Null / Absent `AvailabilityDate`

**Problem:** `AvailabilityDate` is 0/25 populated in the for-sale sample and may be absent or null even in rental feed records (e.g., when a rental is available immediately or the landlord has not updated the field).

**Handling rule:**
1. `AvailabilityDate = null` should **not** be treated as a hard filter failure — it means the date is unknown, not that the property is unavailable.
2. In the SQL WHERE clause, the availability filter should be `availability_date <= :target_date + :buffer_days OR availability_date IS NULL`.
3. In scoring: null `AvailabilityDate` awards 2/15 points (neutral — neither a full miss nor a strong match).
4. In the explanation: trigger the "Move-in availability is not listed" copy from Section 6.4.
5. Never exclude a listing solely because `AvailabilityDate` is null — doing so would hide valid rentals that are immediately available.

### 8.5 — Short-Term vs Annual Lease (`LeaseTerm` Value Vocabulary)

**Problem:** Stellar uses a string vocabulary for `LeaseTerm` that spans both short-term and long-term concepts, and the values are not normalized integers. Example values seen in Stellar for-lease records include: `"Month-To-Month"`, `"6 Months"`, `"12 Months"`, `"1-2 Years"`, `"No Minimum"`, `"Week to Week"`, `"Season"`, `"Short Term Lease"`.

**Handling rule:**
1. At import time, map `LeaseTerm` string values to a canonical month count or category:

| Raw `LeaseTerm` Value | Canonical Months | Category |
|---|---|---|
| `Week to Week` | 0.25 | short_term |
| `Month-To-Month` | 1 | short_term |
| `Season` | 3 | short_term |
| `Short Term Lease` | 3 | short_term |
| `6 Months` | 6 | medium_term |
| `12 Months` | 12 | long_term |
| `1-2 Years` | 18 | long_term |
| `No Minimum` | 0 | flexible |

2. Also check `STELLAR_MinimumLease` — it is often a more precise value (e.g., `"6"` meaning 6 months).
3. Tenant lease preference should be stored as a minimum required months integer in the tenant criteria table.
4. The promoted `lease_term` native column should store the canonical months integer (null if unmappable); the raw string is kept in `raw_json` for display purposes.
5. When `STELLAR_MonthToMonthOrWeeklyYN = true`, treat the listing as `short_term` regardless of other `LeaseTerm` values.
6. For scoring purposes, `flexible` (`No Minimum`) matches any tenant preference at full score. `short_term` matches only tenants who prefer short-term at full score; match tenants with longer preferences at half score with an explanation note.

### 8.6 — HOA Approval Requirement Detection

**Problem:** `STELLAR_AssociationApprovalRequiredYN` is 0/25 populated in the current sample (rental records may populate it). Even when the field is false or absent, the HOA approval requirement may be described only in free text in `STELLAR_AdditionalLeaseRestrictions` or `PublicRemarks`.

**Handling rule:**
1. Check `STELLAR_AssociationApprovalRequiredYN` first. If `true`, trigger the HOA approval warning (Section 6.3).
2. If the field is null or absent, scan `STELLAR_AdditionalLeaseRestrictions` and `PublicRemarks` for indicator phrases: `"HOA approval"`, `"association approval"`, `"board approval"`, `"application required"`, `"background check required"`. If any phrase is found, trigger the HOA approval warning with a note that the requirement was detected from listing remarks and should be confirmed.
3. When `AssociationYN = true` and `AssociationFee > 0` but `STELLAR_AssociationApprovalRequiredYN` is null, include a soft advisory in the explanation: "This property has an HOA — confirm whether approval is required before applying."
4. Never silently suppress the HOA approval warning. A tenant who moves forward without knowing approval is required may face significant delays or rejection.
5. The `STELLAR_AssociationApprovalRequiredYN` field should remain in `raw_json` (not promoted) since it drives only explanation copy, not indexed match queries.

---

## 9. Implementation Roadmap

All three phases are gated behind the Stellar For Lease feed confirmation requirement stated in Section 2. Phase 1 does not begin until live rental data is verified.

---

### Phase 1 — Foundation (After Rental Feed Confirmed)

**Trigger:** Stellar For Lease feed confirmed active; at least one batch of rental records fetched with non-null `LeaseConsideredYN`, `LeaseTerm`, `LeaseAmountFrequency`, and `AvailabilityDate`.

**Stellar Fields Unlocked This Phase:**

`LeaseConsideredYN`, `STELLAR_ForLeaseYN`, `STELLAR_CurrencyMonthlyRentAmt`, `LeaseAmountFrequency`, `AvailabilityDate`, `LeaseTerm`, `PetsAllowed` (rental context), `Furnished` (rental context), `Latitude`, `Longitude`, `CountyOrParish`, `PropertySubType`, `GarageYN`, `PoolPrivateYN`, `WaterfrontYN`, `AssociationYN`, `AssociationFee`, `SeniorCommunityYN`

**Matching Capability Enabled:**

- **Hard-filter query layer:** Active rental listing gate, rent ceiling filter, move-in date compatibility filter, pet restriction gate, geographic radius filter, senior community eligibility gate. These run in indexed SQL WHERE clauses against the promoted native columns.
- **Basic scored result set:** 100-point scoring model applied to filtered results using the eight weight buckets from Section 5. Buckets 1–5 (Location, Rent, Availability, Beds/Baths/Size, Pets/Furnished) are fully operational.
- **Edge case handling:** `LeaseAmountFrequency` normalization (Section 8.1), `Furnished` array normalization (Section 8.2), `PetsAllowed` group mapping (Section 8.3), null `AvailabilityDate` safe handling (Section 8.4).
- **Basic match explanation:** Top strengths copy for rent, location, pets, furnished, availability. No AI — template-based.

**Migrations required:**
- Phase 2R rental promotion migration: add `lease_considered_yn`, `for_lease_yn`, `monthly_rent`, `lease_amount_frequency`, `availability_date`, `lease_term`, `pets_allowed`, `furnished` columns to `bridge_properties`.
- Phase 1 shared promotion migration (if not already run for buyer matching): add `latitude`, `longitude`, `county_or_parish`, `property_sub_type`, `garage_yn`, `pool_private_yn`, `waterfront_yn`, `association_yn`, `association_fee`, `senior_community_yn`.

---

### Phase 2 — Richer Rental Terms

**Trigger:** Phase 1 in production; rental feed data quality confirmed stable over at least one week of sync cycles.

**Stellar Fields Unlocked This Phase:**

`LeaseTerm` (canonicalized), `STELLAR_MinimumLease`, `STELLAR_LongTermYN`, `STELLAR_MonthToMonthOrWeeklyYN`, `TenantPays`, `RentIncludes`, `STELLAR_SecurityDeposit`, `STELLAR_NumberOfPets`, `STELLAR_MaxPetWeight`, `STELLAR_PetSize`, `STELLAR_PetDepositFee`, `STELLAR_PetMonthlyFee`, `STELLAR_AssociationApprovalRequiredYN`, `STELLAR_AdditionalLeaseRestrictions`

**Matching Capability Enabled:**

- **Lease flexibility scoring:** Canonical month count mapping for `LeaseTerm` (Section 8.5), proximity scoring for Lease Terms bucket (Bucket 6 in Section 5). `STELLAR_MinimumLease` used as precision fallback.
- **Utility inclusion parsing:** `TenantPays` and `RentIncludes` arrays parsed and intersected with tenant's preferred inclusions for Fees/Utilities bucket (Bucket 8). Explanation copy for which utilities are included vs. tenant-paid.
- **Pet detail explanation copy:** Pet deposit, monthly pet fee, maximum pet weight, pet size restriction pulled from `raw_json` and surfaced in the match explanation warnings (Section 6.3).
- **HOA approval flag detection:** `STELLAR_AssociationApprovalRequiredYN` check plus `PublicRemarks` phrase scan (Section 8.6). HOA approval warning copy activated.
- **Security deposit display:** `STELLAR_SecurityDeposit` surfaced in explanation as an upfront cost warning.
- **Short-term vs. annual lease separation:** `STELLAR_LongTermYN` and `STELLAR_MonthToMonthOrWeeklyYN` used to accurately categorize listing lease type.
- **Rental available alert:** When a watched listing's `availability_date` falls within a tenant's target move-in window, trigger a proactive availability alert.

**Migrations required:**
- Promote `minimum_lease`, `long_term_yn`, `mls_status`, `year_built` (if not already promoted for buyer matching).
- No additional rental-specific migrations needed — remaining fields stay in `raw_json` and are extracted at explanation render time.

---

### Phase 3 — AI Explanations and Proactive Alerts

**Trigger:** Phase 2 in production; tenant engagement metrics show sufficient usage to justify AI cost per match explanation.

**Stellar Fields Unlocked This Phase:**

`PublicRemarks`, `CommunityFeatures`, `AssociationAmenities`, `InteriorFeatures`, `ExteriorFeatures`, `STELLAR_AdditionalLeaseRestrictions`, `STELLAR_ApprovalProcess`, `Directions`, all Phase 1 and Phase 2 fields as AI context.

**Matching Capability Enabled:**

- **Natural-language AI match explanations:** The template-based explanation system from Sections 6.1–6.4 is augmented or replaced by an LLM-generated explanation that synthesizes all match dimensions into a coherent, tenant-facing narrative. The AI is given the tenant's criteria, the listing's structured fields, and the template framework as a system prompt — it generates a fluid explanation rather than template-assembled bullet points. Free-text fields (`PublicRemarks`, `STELLAR_AdditionalLeaseRestrictions`) are included in the AI context payload.
- **Proactive move-in date alerts:** A scheduled job monitors watched rentals for `availability_date` changes (via `modification_timestamp` polling). When a listing's availability date moves into a tenant's target window, an alert is sent.
- **HOA approval flag warnings with process detail:** When `STELLAR_ApprovalProcess` is non-null (e.g., "Submit application to HOA board within 30 days of lease execution"), the AI explanation incorporates the specific process language.
- **Community and amenity narrative:** `CommunityFeatures` and `AssociationAmenities` arrays are synthesized by the AI into a community description paragraph rather than a bullet list, making the explanation feel personalized rather than algorithmic.
- **Competing listing comparison:** When multiple rentals are returned for a tenant, the AI can generate a "why this ranks above the next result" comparison note using the delta between their scores across each bucket.

**Infrastructure requirements:**
- OpenAI (or equivalent) API integration for explanation generation — one API call per match result surfaced to the tenant.
- A cost-per-explanation budget guard: AI explanation generation should be gated to tenants who have engaged with at least N match results (avoiding AI cost for one-time visitors).
- Explanation caching keyed on `(listing_key, tenant_criteria_hash)` — same tenant criteria against the same listing version should not re-generate the explanation until `modification_timestamp` changes.
- Alert job scheduler (e.g., a daily artisan command or queue worker) to poll watched listings for `availability_date` and `modification_timestamp` changes.
