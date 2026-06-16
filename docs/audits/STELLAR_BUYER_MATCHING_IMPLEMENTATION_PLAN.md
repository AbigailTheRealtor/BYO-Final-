# Stellar Buyer Matching Engine — Implementation Plan

> Document type: Implementation plan — ready to hand to implementing developer  
> Date: 2026-06-16  
> Status: **Documentation only — no code, migrations, schema, or UI changes in this document**  
> Phase gate: Phase 1 native column migration must be complete before engine build begins  
>
> Source audits (do not modify these documents as part of engine implementation):
> - `docs/audits/STELLAR_BUYER_MATCHING_ENGINE_ARCHITECTURE.md` — scoring model, hard filter definitions, explanation blueprint, edge cases, performance strategy
> - `docs/audits/STELLAR_PHASE1_NATIVE_COLUMN_MIGRATION_PLAN.md` — exact DDL, backfill command spec, import mapping, rollout phases, verification checklist
> - `docs/audits/STELLAR_MATCHING_READINESS_AUDIT.md` — complete 553-field classification, tier assignments, native column gap analysis
> - `docs/audits/STELLAR_NATIVE_COLUMN_EXPANSION_STRATEGY.md` — column promotion strategy, Phase 1/2/3 promotion lists
> - `docs/audits/STELLAR_PHASE0_DATA_VALIDATION_REPORT.md` — live population rates for all 20 Phase 1 candidate fields; GO WITH ADJUSTMENTS verdict
> - `docs/audits/STELLAR_BRIDGE_FIELD_AUDIT.md` — raw field inventory, example values, 25-record population counts
> - `docs/audits/BUYER_CRITERIA_BID_FORM_FIELD_AUDIT.md` — buyer criteria form fields and wizard step structure
> - `app/Services/LocationDna/LocationMatchEngine.php` — existing radius/polygon/city/ZIP overlap engine (reuse for location signal detection)
> - `app/Helpers/BuyerBidMatchScoreHelper.php` — existing agent-bid match score engine (for context on existing scoring conventions; do NOT extend this class for Stellar matching)
> - `app/Models/BridgeProperty.php` — current `$fillable` and `$casts` (already updated with Phase 1 columns)
> - `database/migrations/2026_06_15_000010_create_bridge_properties_table.php` — baseline schema before Phase 1 column additions

---

## Table of Contents

1. [Service Names and Class Responsibilities](#1-service-names-and-class-responsibilities)
2. [Buyer Criteria Fields](#2-buyer-criteria-fields)
3. [Native Column Inventory](#3-native-column-inventory)
4. [Hard Filter Order](#4-hard-filter-order)
5. [100-Point Scoring Model](#5-100-point-scoring-model)
6. [Null Rules for Sparse Native Columns](#6-null-rules-for-sparse-native-columns)
7. [Senior Community Gate](#7-senior-community-gate)
8. [IDX Gate](#8-idx-gate)
9. [Explanation Payload Contract](#9-explanation-payload-contract)
10. [Test Cases](#10-test-cases)
11. [Rollout Sequence](#11-rollout-sequence)

---

## 1. Service Names and Class Responsibilities

### 1.1 — Class Map

| Class | Namespace | Responsibility |
|---|---|---|
| `BuyerMatchQueryBuilder` | `App\Services\Stellar\Matching` | Constructs the Layer 1 SQL WHERE clause using only native indexed columns. Accepts a `BuyerCriteriaPayload` DTO and returns a `Builder` (Eloquent query builder instance). Never touches `raw_json` directly. |
| `BuyerMatchScorer` | `App\Services\Stellar\Matching` | Receives the candidate set (array of `BridgeProperty` model instances) from `BuyerMatchQueryBuilder` and scores each listing across all seven scoring categories. Reads `raw_json` per record only within the Tier 2 scoring pass. Returns an array of `BuyerMatchResult` DTOs. |
| `BuyerMatchResultBuilder` | `App\Services\Stellar\Matching` | Assembles the four explanation output blocks (why_this_matches, tradeoffs, caution_flags, missing_data) for each scored listing. Runs after `BuyerMatchScorer` has computed scores. |
| `BuyerMatchService` | `App\Services\Stellar\Matching` | Orchestrates the full pipeline: accepts buyer criteria, invokes `BuyerMatchQueryBuilder` → `BuyerMatchScorer` → `BuyerMatchResultBuilder`, returns the paginated final result set. This is the public-facing API for controllers and queue jobs. |
| `BuyerCriteriaPayload` | `App\Services\Stellar\Matching\DTO` | Immutable DTO carrying all buyer criteria inputs (see Section 2 for field list). Validated and constructed by `BuyerMatchService` before query construction. |
| `BuyerMatchResult` | `App\Services\Stellar\Matching\DTO` | Per-listing result DTO: `listing_key`, `total_score`, `category_scores` (array), explanation blocks, `BridgeProperty` model instance. |

### 1.2 — Pipeline Flow

```
BuyerMatchService::match(BuyerCriteriaPayload $criteria)
  │
  ├── BuyerMatchQueryBuilder::build($criteria)
  │     → WHERE on native columns only (no raw_json)
  │     → LIMIT to candidate_cap (default 200)
  │     → returns Eloquent Builder
  │
  ├── $query->get()   (Layer 1 DB query — returns candidate set)
  │
  ├── BuyerMatchScorer::scoreAll($candidates, $criteria)
  │     → per-record raw_json extraction for Tier 2 fields
  │     → Haversine distance per record
  │     → 7-category scoring
  │     → returns BuyerMatchResult[] (unsorted)
  │
  ├── BuyerMatchResultBuilder::buildAll($results, $criteria)
  │     → assemble 4 explanation blocks per result
  │     → returns BuyerMatchResult[] with explanation data attached
  │
  └── sort by total_score DESC → paginate → return
```

### 1.3 — What NOT to Build

- Do not create a Livewire component, controller, or route as part of the matching engine itself. The engine is a pure service layer consumed by controllers and queue jobs that already exist or will be wired in a separate UI task.
- Do not extend `BuyerBidMatchScoreHelper` — that class scores agent bids against buyer criteria in the platform's auction system. The Stellar matching engine is a separate, unrelated concern.
- Do not create a new Eloquent model. Use the existing `BridgeProperty` model.
- Do not modify `LocationMatchEngine` — that class may be used internally by `BuyerMatchScorer` for polygon/radius signal detection, but must not be modified.

---

## 2. Buyer Criteria Fields

These are the fields from the buyer criteria form (sourced from `BUYER_CRITERIA_BID_FORM_FIELD_AUDIT.md` and `STELLAR_BUYER_MATCHING_ENGINE_ARCHITECTURE.md` Section 2) that the engine accepts as inputs. They map to the `BuyerCriteriaPayload` DTO fields.

### 2.1 — Location Criteria

| DTO Field | Type | Source | Notes |
|---|---|---|---|
| `preferred_cities` | `string[]` | Buyer DNA | One or more city names; matched against `city` native column |
| `preferred_zip_codes` | `string[]` | Buyer DNA | One or more ZIP codes; matched against `postal_code` native column |
| `preferred_counties` | `string[]` | Buyer DNA | One or more county names; matched against `county_or_parish` native column (Phase 1) |
| `radius_searches` | `array[]` | Buyer DNA / LocationDNA | Each entry: `{center: {lat, lng}, radius_miles: float}`; used for Haversine bounding-box filter |
| `polygons` | `array[]` | Buyer DNA / LocationDNA | Each entry: `{path: [{lat, lng}, ...]}` (≥3 points); post-filter PHP point-in-polygon |
| `preferred_subdivisions` | `string[]` | Buyer criteria form | Matched against `subdivision_name` native column (Phase 2) |
| `preferred_mls_areas` | `string[]` | Buyer criteria form | Matched against `mls_area_major` native column (Phase 2) |

### 2.2 — Price Criteria

| DTO Field | Type | Source | Notes |
|---|---|---|---|
| `max_price` | `int\|null` | Buyer criteria form — "purchase price" step | Hard filter ceiling: `list_price <= max_price`; null means no ceiling |
| `ideal_price` | `int\|null` | Buyer criteria form | Optional target; used for proximity decay scoring within price category |

### 2.3 — Property Type Criteria

| DTO Field | Type | Source | Notes |
|---|---|---|---|
| `property_types` | `string[]` | Buyer criteria form step 5 | One or more RESO standard values (`"Residential"`, etc.); hard filter OR across types |
| `property_sub_types` | `string[]` | Buyer criteria form step 7 | Sub-type preferences (`"Single Family Residence"`, `"Condominium"`, `"Townhouse"`, etc.); soft scoring |

### 2.4 — Size Criteria

| DTO Field | Type | Source | Notes |
|---|---|---|---|
| `min_bedrooms` | `int\|null` | Buyer criteria form step 8 | Hard filter: `bedrooms_total >= min_bedrooms` |
| `min_bathrooms` | `int\|null` | Buyer criteria form step 8 | Hard filter: `bathrooms_total_integer >= min_bathrooms` |
| `min_sqft` | `int\|null` | Buyer criteria form | Soft scoring lower bound on `living_area` |
| `max_sqft` | `int\|null` | Buyer criteria form | Soft scoring upper bound on `living_area` |
| `min_lot_sqft` | `int\|null` | Buyer criteria form step 12 | Soft scoring lower bound on `lot_size_sqft` |
| `max_lot_sqft` | `int\|null` | Buyer criteria form step 12 | Soft scoring upper bound on `lot_size_sqft` |
| `year_built_min` | `int\|null` | Buyer criteria form step 10 | Soft scoring lower bound on `year_built` |
| `year_built_max` | `int\|null` | Buyer criteria form step 10 | Soft scoring upper bound on `year_built` |

### 2.5 — Amenity Preferences

| DTO Field | Type | Source | Notes |
|---|---|---|---|
| `wants_pool` | `bool\|null` | Buyer criteria form step 14 | null = no preference; true = prefers pool; false = does not want pool |
| `wants_garage` | `bool\|null` | Buyer criteria form step 13 | null = no preference |
| `min_garage_spaces` | `int\|null` | Buyer criteria form step 13 | Minimum garage spaces when `wants_garage = true` |
| `wants_waterfront` | `bool\|null` | Buyer criteria form step 15 | null = no preference |
| `wants_water_view` | `bool\|null` | Buyer criteria form step 15 | null = no preference |
| `wants_any_view` | `bool\|null` | Buyer criteria form | null = no preference |

### 2.6 — Financial / HOA Criteria

| DTO Field | Type | Source | Notes |
|---|---|---|---|
| `max_monthly_hoa` | `int\|null` | Buyer criteria form step 9 | Soft scoring ceiling for total HOA burden |
| `hoa_preference` | `string\|null` | Buyer criteria form step 9 | `"required"`, `"none"`, or null for no preference |
| `cdd_preference` | `string\|null` | Buyer criteria form step 9 | `"none"` means buyer prefers no CDD; null = no preference; see Section 6 for null handling |
| `max_monthly_total_burden` | `int\|null` | Buyer criteria form | Combined HOA + monthly tax ceiling |

### 2.7 — Eligibility and Community Criteria

| DTO Field | Type | Source | Notes |
|---|---|---|---|
| `is_55_plus_eligible` | `bool` | Buyer criteria form or user profile | **Required** — drives the senior community gate (Section 7). Defaults to `false` if not specified. |
| `wants_pet_friendly` | `bool\|null` | Buyer criteria form step 16 | null = no preference; true = community-level pet friendliness required |
| `wants_new_construction` | `bool\|null` | Buyer criteria form | null = no preference |

### 2.8 — Lifestyle Criteria

| DTO Field | Type | Source | Notes |
|---|---|---|---|
| `community_feature_keywords` | `string[]` | Buyer criteria form step 17 (desired features) | Keywords matched against `CommunityFeatures` and `AssociationAmenities` arrays in `raw_json`; e.g. `["Golf", "Pool", "Tennis", "Clubhouse"]` |
| `wants_energy_efficient` | `bool\|null` | Buyer criteria form step 17 | null = no preference |

---

## 3. Native Column Inventory

### 3.1 — Existing Native Columns (Pre-Phase 1)

These 13 columns exist in `bridge_properties` as of the baseline migration `2026_06_15_000010_create_bridge_properties_table.php`:

| Column | Stellar Source | SQL Type | Matching Role |
|---|---|---|---|
| `listing_key` | `ListingKey` | `VARCHAR` (unique) | Record identifier |
| `listing_id` | `ListingId` | `VARCHAR` | Display identifier |
| `standard_status` | `StandardStatus` | `VARCHAR` | **Hard filter** — `= 'Active'` on every query |
| `property_type` | `PropertyType` | `VARCHAR` | **Hard filter** — must match buyer type preference |
| `list_price` | `ListPrice` | `DECIMAL(15,2)` | **Hard filter** (price ceiling) + Price scoring |
| `unparsed_address` | `UnparsedAddress` | `TEXT` | Display only |
| `city` | `City` | `VARCHAR` | Location scoring — exact match |
| `state_or_province` | `StateOrProvince` | `VARCHAR` | Location pre-filter |
| `postal_code` | `PostalCode` | `VARCHAR` | Location scoring — exact match + geo fallback |
| `bedrooms_total` | `BedroomsTotal` | `INTEGER` | **Hard filter** — `>= min_bedrooms` |
| `bathrooms_total_integer` | `BathroomsTotalInteger` | `INTEGER` | **Hard filter** — `>= min_bathrooms` |
| `living_area` | `LivingArea` | `INTEGER` | Size scoring |
| `modification_timestamp` | `ModificationTimestamp` | `TIMESTAMP` | Alert sync |

### 3.2 — Phase 1 Native Column Promotions (19 columns)

These 19 columns must be present (migrated and backfilled) before the matching engine build begins. Their DDL, indexing strategy, backfill command spec, and import mapping are fully documented in `STELLAR_PHASE1_NATIVE_COLUMN_MIGRATION_PLAN.md`.

| Column | Stellar Source | SQL Type | Nullable | Index | Matching Role |
|---|---|---|---|---|---|
| `latitude` | `Latitude` | `DECIMAL(10,7)` | YES | Composite B-tree with `longitude` | Haversine radius search |
| `longitude` | `Longitude` | `DECIMAL(10,7)` | YES | Composite B-tree with `latitude` | Haversine radius search |
| `county_or_parish` | `CountyOrParish` | `VARCHAR(100)` | YES | B-tree | County filter + coarse geo gate |
| `property_sub_type` | `PropertySubType` | `VARCHAR(100)` | YES | B-tree | Property type soft scoring |
| `senior_community_yn` | `SeniorCommunityYN` | `BOOLEAN` | YES | Partial (WHERE = TRUE) | **Legal hard filter** — Section 7 |
| `mls_status` | `MlsStatus` | `VARCHAR(50)` | YES | B-tree | Alert joins (not a match filter) |
| `year_built` | `YearBuilt` | `SMALLINT` | YES | None | Size/era soft scoring |
| `association_fee` | `AssociationFee` | `DECIMAL(10,2)` | YES | None | Financial soft scoring; 72.2% populated — see Section 6 |
| `pets_allowed` | `PetsAllowed` | `VARCHAR(50)` | YES | None | Lifestyle soft scoring; array-normalised to first element |
| `garage_yn` | `GarageYN` | `BOOLEAN` | YES | Partial (WHERE = TRUE) | Amenity soft scoring |
| `pool_private_yn` | `PoolPrivateYN` | `BOOLEAN` | YES | Partial (WHERE = TRUE) | Amenity soft scoring |
| `waterfront_yn` | `WaterfrontYN` | `BOOLEAN` | YES | Partial (WHERE = TRUE) | Amenity soft scoring |
| `tax_annual_amount` | `TaxAnnualAmount` | `DECIMAL(10,2)` | YES | None | Financial soft scoring |
| `lot_size_sqft` | `LotSizeSquareFeet` | `INTEGER` | YES | None | Size soft scoring |
| `association_yn` | `AssociationYN` | `BOOLEAN` | YES | Partial (WHERE = TRUE) | HOA gate for financial scoring |
| `new_construction_yn` | `NewConstructionYN` | `BOOLEAN` | YES | Partial (WHERE = TRUE) | Lifestyle soft scoring |
| `view_yn` | `ViewYN` | `BOOLEAN` | YES | Partial (WHERE = TRUE) | Amenity soft scoring |
| `water_view_yn` | `STELLAR_WaterViewYN` | `BOOLEAN` | YES | Partial (WHERE = TRUE) | Amenity soft scoring |
| `cdd_yn` | `STELLAR_CDDYN` | `BOOLEAN` | YES | Partial (WHERE = TRUE) | Caution flag; 76.2% populated — see Section 6 |

**Note on `furnished`:** `Furnished` was removed from Phase 1 DDL per Phase 0 Block verdict (35% population rate). It remains `raw_json` only until Phase 2 rental-feed activation. The `BridgeProperty` model `$fillable` and `$casts` do not include `furnished`. Do not add it until the Phase 2 gate condition documented in `STELLAR_PHASE1_NATIVE_COLUMN_MIGRATION_PLAN.md` Section 9 is met.

### 3.3 — Phase 2 Native Column Promotions (for engine enhancement, not launch gate)

These columns are needed for full seven-category scoring but are not required to launch Phase 1 basic matching. They are documented here so the implementing developer can plan for them:

| Column | Stellar Source | SQL Type | Scoring Role |
|---|---|---|---|
| `subdivision_name` | `SubdivisionName` | `VARCHAR(150)` | Location bonus (+3 pts) |
| `mls_area_major` | `MLSAreaMajor` | `VARCHAR(200)` | Location bonus (+3 pts) + coarse geo gate |
| `original_list_price` | `OriginalListPrice` | `DECIMAL(15,2)` | Price reduction signal (+5 pts) |
| `bathrooms_full` | `BathroomsFull` | `TINYINT` | Size precision scoring |
| `garage_spaces` | `GarageSpaces` | `TINYINT` | Amenity count bonus |
| `previous_list_price` | `PreviousListPrice` | `DECIMAL(15,2)` | Alert trigger (not a match filter) |
| `price_change_timestamp` | `PriceChangeTimestamp` | `TIMESTAMP` | Alert trigger (not a match filter) |
| `status_change_timestamp` | `StatusChangeTimestamp` | `TIMESTAMP` | Alert trigger (not a match filter) |

### 3.4 — Fields That Must Remain in `raw_json`

These fields are used in the Tier 2 soft scoring pass (extracted per candidate record in PHP, never in SQL WHERE clauses) and must never be promoted to native columns for matching purposes:

| Field | `raw_json` Key | Used In |
|---|---|---|
| `CommunityFeatures` | `CommunityFeatures` | Lifestyle category scoring — keyword overlap |
| `AssociationAmenities` | `AssociationAmenities` | Lifestyle category scoring — keyword overlap |
| `GreenEnergyEfficient` | `GreenEnergyEfficient` | Lifestyle category scoring — energy efficiency |
| `GreenBuildingVerificationType` | `GreenBuildingVerificationType` | Lifestyle category scoring |
| `SecurityFeatures` | `SecurityFeatures` | Lifestyle context (Phase 3 Ask AI) |
| `PublicRemarks` | `PublicRemarks` | Ask AI context only (Phase 3) |
| `InteriorFeatures` | `InteriorFeatures` | Ask AI context only |
| `ExteriorFeatures` | `ExteriorFeatures` | Ask AI context only |
| `Appliances` | `Appliances` | Ask AI context only |
| `ElementarySchool` | `ElementarySchool` | Ask AI context only — not a scoring input |
| `HighSchool` | `HighSchool` | Ask AI context only — not a scoring input |
| `STELLAR_FloodZoneCode` | `STELLAR_FloodZoneCode` | Caution flag context only — not a scoring input |
| `DaysOnMarket` | `DaysOnMarket` | Caution flag context (`listing_stale` flag) |
| `CumulativeDaysOnMarket` | `CumulativeDaysOnMarket` | Caution flag context |

### 3.5 — Tier 6 Compliance Boundary — Never Promote

No Tier 6 field may be promoted for matching, used in a WHERE clause, included in a score formula, surfaced in a match explanation, or appear in any caution flag label visible to users. The hard-excluded fields include all agent/brokerage PII, license numbers, lockbox details, and the ~200 admin/commercial/internal fields classified Tier 6 in `STELLAR_MATCHING_READINESS_AUDIT.md`. See `STELLAR_BUYER_MATCHING_ENGINE_ARCHITECTURE.md` Section 7.3 for the exhaustive exclusion list.

---

## 4. Hard Filter Order

Hard filters are applied as SQL WHERE clauses. A listing that fails any one hard filter receives no score and is never shown. Apply them in this exact order within the `BuyerMatchQueryBuilder` WHERE clause for optimal index utilization:

### Step 1 — Active Status (B-tree hit, maximum selectivity first)

```sql
WHERE standard_status = 'Active'
```

This is always the first predicate. It eliminates Closed, Pending, Withdrawn, and Expired listings. Every index scan in the matching engine starts here.

### Step 2 — Property Type Match

```sql
AND property_type IN (?)   -- buyer's selected property types; OR across multiple values
```

Buyer may select multiple types (e.g., `['Residential']`). Use `IN (...)` with the buyer's type list. Property type is 100% populated and uses an existing B-tree index.

### Step 3 — Price Ceiling

```sql
AND (list_price <= ? OR list_price IS NULL)
```

Apply only if `max_price` is specified in buyer criteria. When `max_price` is null (buyer has no ceiling), omit this clause entirely. See Section 6.4 for the edge case where `list_price` itself is null.

### Step 4 — Minimum Bedroom Count

```sql
AND (bedrooms_total >= ? OR bedrooms_total IS NULL)
```

Apply only if `min_bedrooms` is specified. Omit if buyer has no bedroom requirement.

### Step 5 — Minimum Bathroom Count

```sql
AND (bathrooms_total_integer >= ? OR bathrooms_total_integer IS NULL)
```

Apply only if `min_bathrooms` is specified. Omit if buyer has no bathroom requirement.

### Step 6 — Senior Community Gate (Legal Compliance — see Section 7)

```sql
AND (senior_community_yn = FALSE OR senior_community_yn IS NULL)
```

Apply **only if `is_55_plus_eligible = false`** in buyer criteria. When `is_55_plus_eligible = true`, omit this clause entirely to allow senior community listings through. This is a legal compliance gate — see Section 7 for the full compliance specification.

### Step 7 — IDX Display Gate (see Section 8)

```sql
AND (raw_json::json->>'IDXParticipationYN')::boolean = TRUE
```

**Implementation note:** This predicate runs against `raw_json` because `IDXParticipationYN` is not a native column. It is applied as a post-filter in PHP after the native-column WHERE clauses, not as a SQL predicate, to avoid a full-table JSON extraction. See Section 8 for the full IDX gate specification.

### Step 8 — Geographic Bounding Box (Haversine pre-filter)

```sql
AND latitude  BETWEEN (:center_lat - :radius_miles / 69.0) AND (:center_lat + :radius_miles / 69.0)
AND longitude BETWEEN (:center_lng - :radius_miles / 53.0) AND (:center_lng + :radius_miles / 53.0)
```

Apply only when buyer criteria includes a radius search. Uses the composite B-tree index on `(latitude, longitude)`. When the buyer specifies city/ZIP only (no radius), replace with:

```sql
AND (city IN (?) OR postal_code IN (?))   -- city and/or ZIP list
```

When buyer specifies a county preference, add:

```sql
AND county_or_parish IN (?)
```

### Step 9 — ORDER BY and LIMIT

```sql
ORDER BY ABS(list_price - :buyer_ideal_price) ASC
LIMIT 200
```

After all hard filters, order by price proximity to the buyer's ideal price (or to the price ceiling midpoint if no ideal is specified). The LIMIT of 200 (configurable) caps the PHP scoring layer input size. This is not a hard filter but is the final step in the `BuyerMatchQueryBuilder` output.

---

## 5. 100-Point Scoring Model

All seven categories sum to exactly 100 points. Scoring is computed by `BuyerMatchScorer` on the candidate set returned by `BuyerMatchQueryBuilder`. The implementation must compute each category independently and then sum for `total_score`.

### 5.1 — Category Weights

| # | Category | Weight | Primary Stellar Fields |
|---|---|---|---|
| 1 | Location | 30 | `city`, `postal_code`, `county_or_parish`, `latitude`, `longitude` |
| 2 | Price | 25 | `list_price`, `original_list_price` (Phase 2) |
| 3 | Size | 15 | `living_area`, `lot_size_sqft`, `year_built` |
| 4 | Property Type | 10 | `property_type`, `property_sub_type` |
| 5 | Amenities | 10 | `pool_private_yn`, `garage_yn`, `waterfront_yn`, `view_yn`, `water_view_yn` |
| 6 | Financial / Fees | 5 | `association_fee`, `association_yn`, `tax_annual_amount`, `cdd_yn` |
| 7 | Lifestyle / Context | 5 | `new_construction_yn`, `pets_allowed`, `CommunityFeatures` (raw_json), `GreenEnergyEfficient` (raw_json) |
| **Total** | | **100** | |

### 5.2 — Category 1: Location (30 points)

Compute the four sub-dimensions below. If Phase 2 columns (`mls_area_major`, `subdivision_name`) are not yet available, award 0 for those sub-dimensions — the 24-point max from available Phase 1 dimensions is still correct relative scoring.

| Sub-dimension | Max Points | Computation Rule |
|---|---|---|
| Radius proximity | 18 | Compute Haversine distance between listing `(latitude, longitude)` and buyer center point. Award `max(0, 18 × (1 - distance / max_radius))` — linear decay from 18 at 0 miles to 0 at the buyer's maximum radius. If latitude/longitude is null, award 0 and append `reduced_confidence_geo_match` caution flag. |
| City / ZIP exact match | 6 | Award +6 if listing `city` is in `preferred_cities` OR listing `postal_code` is in `preferred_zip_codes`. Award +3 if `county_or_parish` matches `preferred_counties` but neither city nor ZIP matches. Award 0 if no geographic match. |
| MLS sub-market match | 3 | Award +3 if `mls_area_major` matches any entry in `preferred_mls_areas`. **Phase 2 only** — requires `mls_area_major` native column. Award 0 in Phase 1. |
| Subdivision match | 3 | Award +3 if `subdivision_name` matches any entry in `preferred_subdivisions` (case-insensitive). **Phase 2 only** — requires `subdivision_name` native column. Award 0 in Phase 1. |

**Phase 1 maximum achievable Location score: 24 points** (sub-market and subdivision bonuses unavailable).

### 5.3 — Category 2: Price (25 points)

| Sub-dimension | Max Points | Computation Rule |
|---|---|---|
| Price proximity to ideal | 20 | If `ideal_price` is set: award `max(0, 20 × (1 - abs(list_price - ideal_price) / ideal_price))`. If `ideal_price` is null and `max_price` is set: award 20 if `list_price <= 0.9 × max_price`; award 15 if `0.9 × max_price < list_price <= max_price`; award 0 at ceiling. If neither is set, award 20 (price is unconstrained). |
| Price reduction signal | 5 | Award +5 if `original_list_price > list_price` (price has been reduced — seller motivation signal). **Phase 2 only** — requires `original_list_price` native column. Award 0 in Phase 1. |

**Phase 1 maximum achievable Price score: 20 points** (price reduction signal unavailable).

### 5.4 — Category 3: Size (15 points)

For range-fit dimensions, use this formula: `score = max_pts × clamp(1 - deviation_ratio, 0, 1)` where `deviation_ratio = max(0, (value - range_max) / range_max, (range_min - value) / range_min)` for a value outside the range. A value inside the range always earns full points.

| Sub-dimension | Max Points | Computation Rule |
|---|---|---|
| Living area fit | 7 | If buyer specifies `min_sqft` or `max_sqft`: compute range deviation percentage; full 7 within range; linear decay to 0 at ±30% outside range edge. If buyer has no sqft preference, award full 7. |
| Lot size fit | 4 | Same range-fit computation as living area using `lot_size_sqft`. If buyer has no lot size preference, award full 4. If `lot_size_sqft` is null, award 2 (neutral mid-score). |
| Year built fit | 4 | If buyer specifies `year_built_min` / `year_built_max`: award +4 if `year_built` is within range; +2 if within ±10 years of the nearest range edge; +0 if outside ±10 years or if `year_built` is null. If buyer has no year built preference, award full 4. |

### 5.5 — Category 4: Property Type (10 points)

| Sub-dimension | Max Points | Computation Rule |
|---|---|---|
| Property type exact match | 5 | Award +5 if `property_type` matches buyer's preferred type. When property type is a hard filter, this is effectively always 5 for listings in the candidate set. Award 5 when buyer's type preference is flexible (multiple types allowed) and the listing matches. |
| Property subtype match | 5 | Award +5 if `property_sub_type` exactly matches any of buyer's `property_sub_types`. Award +2 if the buyer's sub-type is not specified (no preference expressed). Award 0 if buyer specified sub-types and this listing's sub-type is not in that list. |

### 5.6 — Category 5: Amenities (10 points)

Each amenity preference is scored independently. If the buyer has no amenity preferences at all, award the full 10 points. If the buyer has at least one amenity preference, score only the amenities the buyer indicated and normalize to 10:

| Amenity | Points (buyer prefers it) | Computation Rule |
|---|---|---|
| Private pool | Up to 4 | +4 if `pool_private_yn = true`; −0 (not −4) if pool absent — negative scoring within a category is not used |
| Garage | Up to 3 | +3 if `pool_private_yn` is true and `garage_yn = true`. In Phase 2 when `garage_spaces` is available: add +0.5 per space beyond buyer's minimum, capped at +1 bonus |
| Waterfront | Up to 2 | +2 if `waterfront_yn = true`; +1 partial if `water_view_yn = true` but `waterfront_yn` is false |
| Any view | Up to 1 | +1 if `view_yn = true` or `water_view_yn = true` |

**Normalization rule:** Identify which amenity preferences the buyer expressed. Sum the max possible points for those amenities only. Scale the earned score to 10 using `(earned / max_for_expressed_preferences) × 10`. Example: buyer wants only pool (max=4) and earns 4 → normalized score = `(4/4) × 10 = 10`.

### 5.7 — Category 6: Financial / Fees (5 points)

| Sub-dimension | Max Points | Computation Rule |
|---|---|---|
| Total monthly burden fit | 5 | Compute `monthly_burden = (association_fee ?? 0) + ((tax_annual_amount ?? 0) / 12)`. If buyer specifies `max_monthly_total_burden`: award `max(0, 5 × (1 - monthly_burden / max_monthly_total_burden))` — linear decay to 0 at ceiling. If buyer has no burden ceiling, award full 5. |
| CDD penalty | 0 | CDD presence (`cdd_yn = true`) is a **caution flag only** — never a score deduction. A legally valid community type must not be penalized. |

**Null handling:** See Section 6. When `association_fee` is null, treat it as 0 in the burden formula. When `tax_annual_amount` is null, treat it as 0.

### 5.8 — Category 7: Lifestyle / Context (5 points)

This category is computed after the candidate set is already scored on the first six categories. Fields are extracted from `raw_json` per record using `json_decode($record->raw_json, true)`.

| Sub-dimension | Max Points | Computation Rule |
|---|---|---|
| Community features overlap | 2 | Decode `CommunityFeatures` and `AssociationAmenities` arrays from `raw_json`. Count matches against buyer's `community_feature_keywords` (case-insensitive). Award +2 if ≥2 matches; +1 if 1 match; +0 if none or buyer has no keyword preferences. |
| Green / energy efficiency | 1 | Award +1 if buyer expressed `wants_energy_efficient = true` AND (`GreenEnergyEfficient` array from `raw_json` is non-empty OR `GreenBuildingVerificationType` is non-empty). |
| New construction preference | 1 | Award +1 if buyer expressed `wants_new_construction = true` AND `new_construction_yn = true`. Award 0 if buyer has no preference or if `new_construction_yn` is null/false. |
| Pet-friendly community | 1 | Award +1 if buyer expressed `wants_pet_friendly = true` AND `pets_allowed` column value is not null and not `"No"` (any non-No value indicates some level of pet allowance; see Section 6.3 for null rule). |

---

## 6. Null Rules for Sparse Native Columns

Phase 0 validation confirmed two Phase 1 columns with sub-100% population rates in the live Stellar feed. Both are retained in Phase 1 (above the 50% block threshold) but require specific null handling in the matching engine. All other Phase 1 columns are ≥80% populated and may be treated as present when non-null without special handling.

### 6.1 — `association_fee` (72.2% populated)

**Rule:** Do not exclude or penalize listings where `association_fee IS NULL`. A null HOA fee means the data is absent — it does not mean "no HOA" or "high HOA."

- In WHERE clauses when buyer specifies an HOA maximum: use `AND (association_fee <= :max_hoa OR association_fee IS NULL)` — include listings with unknown HOA fee in the candidate set.
- In financial burden scoring (Section 5.7): treat `null` as `0` in the burden formula — `(null ?? 0)` = 0 monthly HOA contribution.
- In the explanation payload: if `association_fee IS NULL` and `association_yn = TRUE`, include a missing_data entry: `{"field": "AssociationFee", "label": "HOA fee amount not listed — verify with listing agent"}`.
- Do not include `association_fee IS NULL` listings in results when buyer has explicitly specified `hoa_preference = "none"` and `association_yn = FALSE` is confirmed — in this case, the null is not a disqualifier (no HOA confirmed by `association_yn`).

### 6.2 — `cdd_yn` (76.2% populated)

**Rule:** Do not treat `cdd_yn IS NULL` as equivalent to `cdd_yn = FALSE`. A null CDD flag means the data is absent, not that the property has no CDD.

- When buyer criteria includes `cdd_preference = "none"`: use `AND (cdd_yn = FALSE OR cdd_yn IS NULL)` — include listings with unknown CDD status in the candidate set.
- Always append the caution flag `{"type": "cdd_status_unknown", "label": "CDD status not confirmed in listing data — verify with listing agent"}` for any listing where `cdd_yn IS NULL`.
- For listings where `cdd_yn = TRUE`, always append the caution flag `{"type": "cdd_present", "label": "This community has a Community Development District (CDD). Annual CDD fees apply in addition to HOA and property taxes."}`.
- `cdd_yn` contributes to the explanation payload (caution flags), not to the score. Do not penalize score for CDD presence.

### 6.3 — `pets_allowed` Null Rule

`pets_allowed` stores the first element of the `PetsAllowed` array from the Stellar API (see import mapping in `STELLAR_PHASE1_NATIVE_COLUMN_MIGRATION_PLAN.md` Section 7g).

- When `pets_allowed IS NULL`: treat as "unknown pet policy" — score as 0 for the pet-friendly lifestyle sub-dimension (not +1) and append caution flag `{"type": "pet_policy_unknown", "label": "Pet policy not confirmed in listing data — verify with listing agent or HOA."}`.
- When `pets_allowed = 'No'`: buyer who specified `wants_pet_friendly = true` should see this listing with a tradeoff entry.
- When `pets_allowed` contains any other value (`"Yes"`, `"Cats"`, `"Dogs"`, etc.): treat as pet-allowed community for scoring purposes.

### 6.4 — Missing `list_price`

`list_price` is 25/25 in the Phase 0 sample. This rule is a defensive guard for future feed expansions:

- If `list_price IS NULL` and buyer has `max_price` set: exclude from results (cannot evaluate hard filter without price data).
- If `list_price IS NULL` and buyer has no price ceiling: include in results, award Price category score = 0, and include a missing_data entry in the explanation.

### 6.5 — Missing `latitude` / `longitude`

- If `latitude IS NULL` or `longitude IS NULL`: skip the Haversine sub-score (award 0 for the 18-point proximity sub-dimension). Apply ZIP code fallback: include the listing if `postal_code` is in the buyer's `preferred_zip_codes`. Award city/ZIP match sub-score normally if applicable. Append `reduced_confidence_geo_match` caution flag. If the buyer's criteria is radius-only (no city or ZIP fallback configured), exclude the listing from results.

---

## 7. Senior Community Gate

### 7.1 — Legal Basis

The senior community gate enforces compliance with the **Fair Housing Act (FHA)** and the **Housing for Older Persons Act (HOPA)**. Under HOPA, housing communities may legally restrict residency to persons 55 or older provided they meet HOPA's registration and verification requirements. The matching engine must not show 55+ communities to buyers who have not indicated 55+ eligibility.

### 7.2 — Implementation Rule

**When `is_55_plus_eligible = false` (default):**

Add the following predicate to the WHERE clause as Hard Filter Step 6 (Section 4):

```sql
AND (senior_community_yn = FALSE OR senior_community_yn IS NULL)
```

This excludes all listings where `senior_community_yn = TRUE`. Listings where `senior_community_yn IS NULL` are **included** — absence of the flag does not indicate the community is age-restricted.

**When `is_55_plus_eligible = true`:**

Omit the senior community predicate entirely. All listings — including 55+ communities and non-restricted communities — may be shown.

### 7.3 — Scoring Constraint

`senior_community_yn` must **never** be used as a scoring input. It is a binary eligibility gate only.

- Do not boost the score for a listing because `senior_community_yn = TRUE` (even for a buyer who is 55+ eligible).
- Do not penalize the score for a listing because `senior_community_yn = TRUE`.
- Do not include `senior_community_yn` in any of the four explanation blocks.

The only valid engine role for this field is the WHERE-clause exclusion gate described above.

### 7.4 — `is_55_plus_eligible` Source of Truth

The `is_55_plus_eligible` field in `BuyerCriteriaPayload` must be explicitly set from the buyer's input — it must never default to `true`. If the source data does not contain an explicit indication of 55+ eligibility, `is_55_plus_eligible` must default to `false`, which applies the age-restriction exclusion and ensures the most conservative, legally compliant behavior.

### 7.5 — Index Note

`senior_community_yn` has a partial B-tree index `WHERE senior_community_yn = TRUE`. The WHERE clause predicate `senior_community_yn = FALSE OR senior_community_yn IS NULL` does not directly hit this partial index (which covers only `TRUE` rows). PostgreSQL will use the partial index if the query is rewritten as a NOT IN sub-query against the indexed subset, but for clarity and correctness, the IS NULL / FALSE form is preferred. At typical table sizes (< 500K rows), PostgreSQL's sequential scan on the remaining rows after status/type/price filtering is acceptable. If index utilization becomes a concern, add a full `WHERE senior_community_yn = FALSE` partial index alongside the existing TRUE partial index.

---

## 8. IDX Gate

### 8.1 — What Is the IDX Gate

`IDXParticipationYN` is a Stellar MLS field that indicates whether the listing has been authorized for Internet Data Exchange (IDX) display on member websites. A value of `false` means the listing may not be displayed publicly via IDX syndication.

In the Stellar/Bridge API sample, `IDXParticipationYN` is 25/25 populated with value `true` — all 25 sampled records are IDX-eligible. However, listings with `IDXParticipationYN = false` may exist in the feed and must never be shown to buyers via the matching engine.

### 8.2 — Why IDX Is Not a SQL Hard Filter

`IDXParticipationYN` is not a native column — it lives only in `raw_json`. Running `WHERE (raw_json::json->>'IDXParticipationYN')::boolean = TRUE` as a SQL predicate requires a per-row JSON extraction on the full candidate set and cannot be efficiently indexed.

Because `IDXParticipationYN` is 25/25 true in the current sample (and expected to be near-100% true in any Active residential for-sale feed), applying it as a PHP post-filter on the already-reduced candidate set is acceptable. The cost is O(n) on the small candidate set, not O(total rows).

### 8.3 — Implementation

After `BuyerMatchQueryBuilder` returns the candidate set from the database, `BuyerMatchService` applies the IDX gate before passing the candidates to `BuyerMatchScorer`:

```php
$candidates = $candidates->filter(function (BridgeProperty $listing) {
    $data = json_decode($listing->raw_json, true);
    $idxYN = $data['IDXParticipationYN'] ?? true;
    return (bool) $idxYN === true;
});
```

Listings where `IDXParticipationYN = false` are silently excluded — they do not appear in results and do not receive explanation blocks. Do not surface a "listing not available for display" message to the buyer for IDX-excluded listings.

**Future optimization:** If `IDXParticipationYN = false` listings become frequent enough to affect result quality, promote `idx_participation_yn` to a native boolean column and move it into the SQL WHERE clause. At current data rates, this is not necessary.

---

## 9. Explanation Payload Contract

Every listing in the scored results carries four explanation output blocks. These are assembled by `BuyerMatchResultBuilder` after scoring is complete. The blocks are stored in the `BuyerMatchResult` DTO and consumed by the display layer and Phase 3 Ask AI natural-language generation.

### 9.1 — Block 1: `why_this_matches` (Positive Signals)

One entry per scoring category where the listing earned > 0 points. Each entry is a structured object.

**Schema:**
```json
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
      "score_contribution": 8
    }
  ]
}
```

**Rules:**
- Only include entries where `score_contribution > 0`.
- `label` is human-readable; never include Stellar field names or internal column names in labels shown to users.
- `fields_used` is internal metadata — for debugging and Phase 3 Ask AI prompt building only; do not render this field in UI.
- Order entries by `score_contribution` descending (strongest signal first).

### 9.2 — Block 2: `tradeoffs` (Partial Matches)

One entry per scoring dimension where the listing passed all hard filters but scored below its maximum possible contribution in that dimension.

**Schema:**
```json
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
      "label": "No private pool listed — community pool may be available",
      "fields_used": ["pool_private_yn"],
      "deviation": "pool_absent"
    }
  ]
}
```

**Rules:**
- Include only where a buyer preference was expressed and the listing scores below maximum for that dimension.
- Do not include a tradeoff for a dimension where the buyer expressed no preference (the listing cannot "fall short" of a preference that was never stated).
- `deviation` is a machine-readable string in snake_case for the display layer to use if it needs to vary presentation by deviation type.

### 9.3 — Block 3: `caution_flags` (Disclosure Signals)

One entry per caution condition. These are not score penalties — they are informational disclosures.

**Schema:**
```json
{
  "caution_flags": [
    {
      "type": "cdd_present",
      "severity": "info",
      "label": "This community has a Community Development District (CDD). Annual CDD fees apply in addition to HOA and property taxes."
    },
    {
      "type": "flood_zone_data_absent",
      "severity": "info",
      "label": "Flood zone data not available from listing. Verify flood zone designation with the listing agent before making an offer."
    },
    {
      "type": "listing_stale",
      "severity": "warning",
      "label": "This listing has been on the market for 90 days."
    },
    {
      "type": "reduced_confidence_geo_match",
      "severity": "info",
      "label": "Exact location could not be confirmed — matched by ZIP code only."
    },
    {
      "type": "school_district_not_normalized",
      "severity": "info",
      "label": "School information is from the listing and has not been independently verified. Confirm school assignments directly with the school district."
    }
  ]
}
```

**Supported caution types:**

| Type | Trigger Condition | Severity |
|---|---|---|
| `cdd_present` | `cdd_yn = true` | `info` |
| `cdd_status_unknown` | `cdd_yn IS NULL` | `info` |
| `flood_zone_data_absent` | `STELLAR_FloodZoneCode` present in `raw_json` but translation table not yet built | `info` |
| `listing_stale` | `DaysOnMarket >= 60` (raw_json extraction; configurable threshold) | `warning` |
| `reduced_confidence_geo_match` | `latitude IS NULL` or `longitude IS NULL` | `info` |
| `pet_policy_unknown` | `pets_allowed IS NULL` and buyer specified `wants_pet_friendly = true` | `info` |
| `school_district_not_normalized` | Buyer specified school preference and `ElementarySchool`/`HighSchool` are present in raw_json | `info` |
| `hoa_fee_not_listed` | `association_yn = true` and `association_fee IS NULL` | `info` |

### 9.4 — Block 4: `missing_data` (Absent Fields)

One entry per field that was needed for scoring but is null in the listing data.

**Schema:**
```json
{
  "missing_data": [
    {
      "field": "AssociationFee",
      "label": "HOA fee amount not listed — verify with listing agent"
    },
    {
      "field": "YearBuilt",
      "label": "Year built not listed"
    }
  ]
}
```

**Rules:**
- Include only when the buyer expressed a preference for that dimension AND the listing's corresponding column is null.
- Do not include missing_data entries for fields where the buyer has no preference — the missing data is irrelevant to the scoring.
- `field` uses the Stellar source field name (not the column name) for consistency with the field audit.

### 9.5 — Top-Level Result Structure

The complete `BuyerMatchResult` DTO serializes to:

```json
{
  "listing_key": "25b3e48ee7095eed12688b25c10ea606",
  "total_score": 78,
  "category_scores": {
    "location": 24,
    "price": 20,
    "size": 13,
    "property_type": 8,
    "amenities": 8,
    "financial": 3,
    "lifestyle": 2
  },
  "why_this_matches": [...],
  "tradeoffs": [...],
  "caution_flags": [...],
  "missing_data": [...]
}
```

---

## 10. Test Cases

Implement these test cases as PHPUnit feature tests in `tests/Feature/Stellar/`. Each test should use `DatabaseTransactions` (not `RefreshDatabase`) per the project's SQLite-memory test pattern (see `.agents/memory/MEMORY.md`). Use `DB::table('bridge_properties')->insertGetId()` to seed test records with controlled `raw_json` payloads.

### TC-01: Active status hard filter excludes non-Active listings

**Given:** Two `bridge_properties` records — one with `standard_status = 'Active'`, one with `standard_status = 'Closed'`.  
**When:** `BuyerMatchService::match()` is called with any valid criteria.  
**Then:** Only the Active listing appears in results. The Closed listing is absent regardless of any other field match.

### TC-02: Price ceiling hard filter excludes over-budget listings

**Given:** Two Active listings — one with `list_price = 350000` (under budget), one with `list_price = 450001` (over budget).  
**When:** Buyer criteria has `max_price = 450000`.  
**Then:** Only the $350,000 listing appears in results.

### TC-03: Senior community gate excludes 55+ listings for non-eligible buyer

**Given:** Two Active listings — one with `senior_community_yn = true`, one with `senior_community_yn = false`.  
**When:** `is_55_plus_eligible = false` in buyer criteria.  
**Then:** Only the non-senior listing appears. The 55+ listing is absent.

### TC-04: Senior community gate allows 55+ listings for eligible buyer

**Given:** Same two listings as TC-03.  
**When:** `is_55_plus_eligible = true` in buyer criteria.  
**Then:** Both listings appear in results.

### TC-05: Senior community gate — NULL `senior_community_yn` is treated as non-senior (included)

**Given:** One Active listing with `senior_community_yn = NULL`.  
**When:** `is_55_plus_eligible = false`.  
**Then:** The listing appears in results (null is not treated as age-restricted).

### TC-06: IDX gate excludes non-IDX listings

**Given:** Two Active listings — one with `IDXParticipationYN: true` in `raw_json`, one with `IDXParticipationYN: false`.  
**When:** `BuyerMatchService::match()` is called.  
**Then:** Only the IDX-eligible listing appears.

### TC-07: Bedroom minimum hard filter

**Given:** Two Active listings — one with `bedrooms_total = 3`, one with `bedrooms_total = 2`.  
**When:** Buyer criteria has `min_bedrooms = 3`.  
**Then:** Only the 3-bedroom listing appears.

### TC-08: Bathroom minimum hard filter

**Given:** Two Active listings — one with `bathrooms_total_integer = 2`, one with `bathrooms_total_integer = 1`.  
**When:** Buyer criteria has `min_bathrooms = 2`.  
**Then:** Only the 2-bathroom listing appears.

### TC-09: Pool scoring — buyer prefers pool, listing has pool

**Given:** One Active listing with `pool_private_yn = true`.  
**When:** `wants_pool = true`.  
**Then:** The amenity category score for this listing is 10 (normalized from 4 possible pool points to 10).

### TC-10: Pool scoring — buyer prefers pool, listing has no pool

**Given:** One Active listing with `pool_private_yn = false`.  
**When:** `wants_pool = true`.  
**Then:** The amenity category score is 0. A tradeoff entry with `dimension = "amenities"` and `deviation = "pool_absent"` appears in the explanation.

### TC-11: Pool scoring — buyer has no pool preference

**Given:** One Active listing with `pool_private_yn = false`.  
**When:** `wants_pool = null` (no preference).  
**Then:** The amenity category score is 10 (full points — no preference means no penalty).

### TC-12: CDD caution flag — `cdd_yn = true`

**Given:** One Active listing with `cdd_yn = true`.  
**When:** Any buyer criteria.  
**Then:** The listing's `caution_flags` array contains an entry with `type = "cdd_present"`. The listing is not excluded from results. The total score is not reduced relative to an identical listing with `cdd_yn = false`.

### TC-13: CDD null handling — `cdd_yn = NULL` with buyer preferring no CDD

**Given:** One Active listing with `cdd_yn = NULL`.  
**When:** `cdd_preference = "none"`.  
**Then:** The listing is included in results (not excluded). The `caution_flags` array contains `type = "cdd_status_unknown"`.

### TC-14: `association_fee` null — included with neutral score

**Given:** One Active listing with `association_fee = NULL` and `association_yn = true`.  
**When:** Buyer criteria has `max_monthly_hoa = 200`.  
**Then:** The listing appears in results. The financial category score is the neutral burden score (0 HOA treated as $0). The `missing_data` array contains `field = "AssociationFee"`.

### TC-15: Haversine proximity scoring — closer listing scores higher than distant listing

**Given:** Two Active listings — one at `(latitude=28.35, longitude=-81.24)`, one at `(latitude=28.90, longitude=-81.60)`.  
**When:** Buyer radius search centered at `(28.35, -81.24)` with `radius_miles = 50`.  
**Then:** The nearby listing has a higher Location category score than the distant listing.

### TC-16: Radius hard filter — listing outside radius is excluded

**Given:** One Active listing at `(latitude=29.50, longitude=-80.50)` (more than 50 miles from center).  
**When:** Buyer radius search centered at `(28.35, -81.24)` with `radius_miles = 50`.  
**Then:** The listing does not appear in results (excluded by bounding-box AND Haversine having clause).

### TC-17: Missing latitude — ZIP fallback and reduced confidence flag

**Given:** One Active listing with `latitude = NULL`, `longitude = NULL`, `postal_code = "32827"`.  
**When:** Buyer criteria has `preferred_zip_codes = ["32827"]` and no radius search.  
**Then:** The listing appears in results. The Location proximity sub-score is 0 (no Haversine possible). The `caution_flags` contains `type = "reduced_confidence_geo_match"`.

### TC-18: Price scoring — ideal price proximity decay

**Given:** One Active listing with `list_price = 380000`.  
**When:** Buyer criteria has `ideal_price = 400000` and `max_price = 450000`.  
**Then:** The price proximity sub-score is approximately 19 (listing is 5% below ideal — `20 × (1 - 0.05) = 19`).

### TC-19: `total_score` does not exceed 100 for any listing

**Given:** One Active listing with all Phase 1 native columns populated at optimal values for a buyer's criteria.  
**When:** `BuyerMatchScorer::score()` is called.  
**Then:** `total_score <= 100` and `total_score >= 0`.

### TC-20: Results are sorted by `total_score` descending

**Given:** Three Active listings with different match profiles (different city, price, amenity combinations).  
**When:** `BuyerMatchService::match()` returns results.  
**Then:** The result array is sorted by `total_score` descending — the highest-scoring listing is first.

### TC-21: `candidate_cap` limits PHP layer input

**Given:** 250 Active listings all passing the hard filters (same property type, under budget, etc.).  
**When:** `BuyerMatchService::match()` is called with default `candidate_cap = 200`.  
**Then:** `BuyerMatchScorer` receives at most 200 records (the top-200 by price proximity from the ORDER BY in `BuyerMatchQueryBuilder`).

### TC-22: `why_this_matches` contains only entries with `score_contribution > 0`

**Given:** One Active listing that scores 0 in the amenities category (buyer wants pool, listing has no pool).  
**When:** Explanation is built.  
**Then:** The `why_this_matches` array does not contain an entry for `dimension = "amenities"`. The `tradeoffs` array does contain an entry for `dimension = "amenities"`.

### TC-23: Lifestyle community feature keyword overlap

**Given:** One Active listing with `raw_json` containing `"CommunityFeatures": ["Golf Course", "Tennis Court", "Pool", "Playground"]`.  
**When:** Buyer criteria has `community_feature_keywords = ["Golf", "Tennis"]`.  
**Then:** The Lifestyle category `community_features` sub-dimension awards +2 (≥2 keyword matches).

### TC-24: Stale listing caution flag

**Given:** One Active listing with `"DaysOnMarket": 90` in `raw_json`.  
**When:** `BuyerMatchResultBuilder` assembles the explanation.  
**Then:** `caution_flags` contains `type = "listing_stale"` with `severity = "warning"`. The listing is not excluded from results.

### TC-25: Multiple property types — both types matched

**Given:** Two Active listings — one with `property_type = 'Residential'` and `property_sub_type = 'Single Family Residence'`, one with `property_type = 'Residential'` and `property_sub_type = 'Condominium'`.  
**When:** Buyer criteria specifies `property_sub_types = ["Single Family Residence", "Condominium"]`.  
**Then:** Both listings appear in results. Each receives an equal property sub-type match score.

---

## 11. Rollout Sequence

### Pre-Condition: Phase 1 Migration Gate

The matching engine build must NOT begin until all items in this gate are confirmed:

| Gate Item | How to Verify | Source |
|---|---|---|
| All 19 Phase 1 native columns present in `bridge_properties` | `\d bridge_properties` in psql — all 19 columns listed | `STELLAR_PHASE1_NATIVE_COLUMN_MIGRATION_PLAN.md` Sections 2 and 8 (Check 1) |
| `(latitude, longitude)` composite B-tree index created | `pg_indexes` query shows `bridge_properties_lat_lng_idx` | Migration plan Section 3c |
| All 9 partial boolean indexes created | `pg_indexes` query shows all 9 partial indexes | Migration plan Section 3d |
| Backfill command completed with 0 errors | `php artisan bridge:backfill-native-columns` output | Migration plan Section 4 |
| Null coverage matches expected rates | Check 2 SQL query from migration plan Section 8 | Phase 0 expected: `association_fee` ≤ 28% null; `cdd_yn` ≤ 24% null; all others ≤ 20% null |
| Native column values match `raw_json` source | Check 3 SQL query from migration plan Section 8 | Spot-check 5 rows |
| Import command populates new columns on fresh import | Check 6 from migration plan Section 8 | Run `bridge:import-properties --limit=1` and query |
| `BridgeProperty::$fillable` includes all 19 Phase 1 columns | Review `app/Models/BridgeProperty.php` | Already updated — verify no column was accidentally removed |

### Phase 1 Engine Build — Basic Matching

**Prerequisite:** Migration gate above fully cleared.

**Deliverables in order:**

1. **Create `BuyerCriteriaPayload` DTO** — all fields from Section 2; validate in constructor (no nulls in required fields, no negative prices, `is_55_plus_eligible` has a boolean value).

2. **Create `BuyerMatchQueryBuilder`** — implements the WHERE clause from Section 4 (Steps 1–8). Unit-test the query builder in isolation by calling `->toSql()` and asserting the generated SQL contains the expected predicates.

3. **Create `BuyerMatchScorer`** — implements all seven scoring categories from Section 5. Must handle all null rules from Section 6. Must apply senior community gate logic from Section 7 (though this is actually enforced in the query builder — scorer receives only eligible listings). Must enforce the compliance boundary (no Tier 6 fields in scoring).

4. **Create `BuyerMatchResultBuilder`** — assembles the four explanation blocks from Section 9 per scored listing.

5. **Create `BuyerMatchService`** — orchestrates the pipeline: DTO → query builder → `->get()` → IDX gate (Section 8) → scorer → result builder → sort → return.

6. **Write all 25 test cases from Section 10** — run before any controller wiring.

7. **Run verification checklist** (Checks 1–7) from `STELLAR_PHASE1_NATIVE_COLUMN_MIGRATION_PLAN.md` Section 8 to confirm the data layer is in the expected state.

**Phase 1 engine capability:**
- Hard filters: status, type, price, beds, baths, senior community, bounding-box geography
- Soft scoring: Location (24/30 max — no subdivision/MLS area bonus), Price (20/25 max — no price reduction signal), Size (15/15), Property Type (10/10), Amenities (10/10), Financial (5/5), Lifestyle (5/5)
- Maximum Phase 1 score: 89/100 (missing 6 Location sub-market points, 5 Price reduction signal points)
- Explanation blocks: all four blocks operational

### Phase 2 Engine Enhancement — Full Scoring

**Prerequisite:** Phase 1 engine live. Phase 2 native column promotions migrated: `subdivision_name`, `mls_area_major`, `original_list_price`, `bathrooms_full`, `garage_spaces`, `previous_list_price`, `price_change_timestamp`, `status_change_timestamp`.

**Deliverables:**

1. **Extend `BuyerMatchQueryBuilder`** — add MLS area and subdivision coarse-gate predicates using new native columns.
2. **Extend `BuyerMatchScorer`** — activate the 3-point subdivision bonus, 3-point MLS area bonus, and 5-point price reduction signal. Add garage spaces count bonus. Add bathrooms_full precision scoring.
3. **Wire alert triggers** — `previous_list_price`, `price_change_timestamp`, and `status_change_timestamp` columns are consumed by the alert system (separate task, not the matching engine).

**Phase 2 engine capability:**
- Maximum score: 100/100
- Full seven-category scoring with all native columns available
- Location DNA polygon matching using PHP `LocationMatchEngine` (reuse existing service)

### Phase 3 — Ask AI Explanations and Recommendations

**Prerequisite:** Phase 1 AND Phase 2 complete.

**Deliverables:**
- Natural-language explanation generation using the four explanation blocks from Section 9 as structured input to the Ask AI pipeline.
- External data integrations: Walk Score API (geocode → walkability score per listing), GreatSchools API (geocode → school district data), FEMA flood zone translation table (FEMA code → risk tier).
- Walk/transit scores and school district ratings become additional Lifestyle/Context scoring inputs.
- Flood zone risk tier surfaces as a properly labeled caution flag severity level (Minimal / Moderate / High) replacing the raw `flood_zone_data_absent` placeholder.

### Rollback Plan

If a production issue is found after Phase 1 engine deployment:

1. The matching engine is a pure service layer — disabling it does not require a schema migration rollback.
2. To disable: remove the route or controller method that calls `BuyerMatchService::match()`. The `bridge_properties` table, native columns, and import pipeline are unaffected.
3. The Phase 1 native columns are append-only and do not break any existing queries or features — they can remain in place even if the matching engine is temporarily disabled.
4. To re-enable: restore the controller method. No data loss occurs.
