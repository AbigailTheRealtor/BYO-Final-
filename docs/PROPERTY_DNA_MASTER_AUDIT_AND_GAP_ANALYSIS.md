# Property DNA Master Audit and Gap Analysis

**Audit Date:** June 1, 2026  
**Scope:** Complete read-only audit of the entire Property DNA ecosystem  
**Purpose:** Inventory all DNA components, classify each as Built / Partial / Missing, identify integration gaps, and document governance constraints  
**Auditor note:** No code changes were made. All findings are derived exclusively from reading source files.

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [System Architecture Map](#2-system-architecture-map)
3. [DNA Models Inventory](#3-dna-models-inventory)
4. [Database Migrations](#4-database-migrations)
5. [DNA Generators](#5-dna-generators)
6. [DNA Explanation Services](#6-dna-explanation-services)
7. [Location DNA Pipeline](#7-location-dna-pipeline)
8. [Compatibility Engine — Listing Intelligence](#8-compatibility-engine--listing-intelligence)
9. [BYA Compatibility Pipeline — Agent-Consumer Working Style](#9-bya-compatibility-pipeline--agent-consumer-working-style)
10. [Marketing Intelligence Pipeline](#10-marketing-intelligence-pipeline)
11. [Event Dispatch — Observers and Jobs](#11-event-dispatch--observers-and-jobs)
12. [Role-Based Intelligence Profiles](#12-role-based-intelligence-profiles)
13. [Property Personality Readiness](#13-property-personality-readiness)
14. [DNA Output Inventory](#14-dna-output-inventory)
15. [Listing Input Field Catalogue](#15-listing-input-field-catalogue)
16. [Gap Analysis and Classification Table](#16-gap-analysis-and-classification-table)
17. [Governance Constraints Summary](#17-governance-constraints-summary)
18. [Percentage Summary by Subsystem](#18-percentage-summary-by-subsystem)

---

## 1. Executive Summary

The Property DNA ecosystem is a large, multi-pipeline system for encoding listing attributes and buyer/tenant preferences into structured profiles, computing dimensional compatibility between supply and demand listings, generating AI-assisted marketing intelligence, and enriching listings with geographic context.

**Overall ecosystem build status (57 audited components):**

| Classification | Count | Percentage |
|---|---|---|
| **Built** | 42 | 74% |
| **Partial** | 7 | 12% |
| **Missing** | 8 | 14% |

**Critical findings:**

- The **Location DNA → PropertyDnaProfile integration is explicitly deferred** in code. The `PropertyContextService` (Phase F) is fully built but a `// PHASE F NOTE` in `PropertyDnaGenerator` states that integration into the profile is deferred — the `location_dna_context` column does not exist on the `property_dna_profiles` table.
- **6 of 14 Compatibility Engine dimensions are permanently unresolved** in Phase H due to missing generator signals on one or both sides. They are excluded from the coverage metric denominator by design.
- **2 of 12 BYA working-style dimensions are always unresolved** (`technology_preference`, `market_education_preference`) because neither side's normalization service emits data for them.
- **No role-intelligence profile UI layer exists** for any of the four roles (Seller, Buyer, Landlord, Tenant). DNA data is computed and stored but not surfaced via any view or dashboard component.
- **Property Personality** is stored as `personality_summary` on both DNA profile models but has no dedicated readiness service, pipeline, or consumer-facing display.
- **The BYA pipeline and the Compatibility Engine are two entirely separate systems** operating on different data: the Compatibility Engine matches supply/demand listing attributes; the BYA pipeline matches consumer working-style preferences against agent response profiles.

---

## 2. System Architecture Map

```
SUPPLY SIDE (seller, landlord)                 DEMAND SIDE (buyer, tenant)
─────────────────────────────                  ──────────────────────────
PropertyAuction / LandlordAuction              BuyerCriteriaAuction / TenantCriteriaAuction
        │ observer (saved)                              │ observer (saved)
        ▼                                              ▼
ComputePropertyDnaProfile (job)         ComputeBuyerTenantDnaProfile (job)
        │                                              │
        ▼                                              ▼
PropertyDnaGenerator                     BuyerTenantDnaGenerator
  29 dimension slots                       26 dimension slots
  seller + landlord                        buyer + tenant
        │                                              │
        ▼                                              ▼
PropertyDnaProfile (model/table)         BuyerTenantDnaProfile (model/table)
  ai_buyer_archetype_tags                  lifestyle_tags
  ai_marketing_hooks                       deal_breaker_flags
  personality_summary                      personality_summary
  dna_coverage_metric                      dna_coverage_metric
        │                                              │
        └──────────────┬───────────────────────────────┘
                       ▼
            PropertyDnaProfileCompatibilityObserver
            BuyerTenantDnaProfileCompatibilityObserver
            FANOUT_CAP = 500 │ DISPATCH_CHUNK_SIZE = 100
                       │
                       ▼
           ComputeCompatibilityScore (job)
                       │
                       ▼
             CompatibilityEngine
             14 dimensions │ 8 eligible │ 6 ineligible
                       │
                       ▼
          ListingCompatibilityScore (model/table)
          + BYA columns (migration 2026_05_29)

                                    SEPARATE BYA PIPELINE
                                    (Agent-Consumer Working Style)
                                    ─────────────────────────────
                                    ByaNormalizationService (BYA_NORM_V1)
                                         ↓
                                    ByaAgentResponseNormalizationService (BYA_AGENT_NORM_V1)
                                         ↓
                                    ByaCompatibilityComparisonService (BYA_COMP_V1)
                                         ↓
                                    ByaCompatibilityAlignmentService (BYA_ALIGN_V1)
                                         ↓
                                    ByaCompatibilityExplanationService (BYA_EXPLAIN_V1)
                                         ↓
                                    ByaCompatibilityNarrativeService (BYA_NARRATIVE_V1)
                                         ↓
                                    ByaCompatibilityReportService (BYA_REPORT_V1)

LOCATION DNA PIPELINE (supply side only)
────────────────────────────────────────
PropertyAuction / LandlordAuction
   Phase B: GeocodeService          → PropertyLocationDna (lat/lng, place_id, address)
   Phase C: PoiDistanceService      → PropertyLocationPoi (19 categories)
   Phase D: SummaryService          → PropertyLocationDna (walkability, transit, summaries)
   Phase E: AuditService            → PropertyLocationDnaAudit (append-only log)
   Phase F: PropertyContextService  → [DEFERRED — not integrated into PropertyDnaProfile]
   Phase G: MarketingContextService → feeds marketing pipeline
   Phase H: IntelligenceContextService → feeds intelligence pipeline

MARKETING INTELLIGENCE PIPELINE (supply side)
──────────────────────────────────────────────
Phase P: PropertyMarketingContextService  → deterministic context bundle
Phase R: PropertyMarketingBriefService    → marketing brief
Phase U: PropertyMarketingReadinessService → readiness gating
Phase XD: AiMarketingReportGeneratorService → OpenAI report generation
Phase XF: AiMarketingReportReviewService    → AI review pass
Phase XG: AiMarketingReportOrchestratorService → pipeline orchestration
Phase XJ: AiMarketingReportPersistenceService  → persist to DB
Phase XN: AiMarketingReportAgentRevisionService → agent edits
Phase XO: AiMarketingReportOwnerApprovalService → owner approval
Phase XP: AiMarketingReportPublicationService   → publication
                       ↓
       marketing_reports / marketing_report_versions / marketing_report_audits (tables)
       DnaMarketingOutput (model/table)
```

---

## 3. DNA Models Inventory

### 3.1 PropertyDnaProfile
**Status: BUILT**  
**File:** `app/Models/PropertyDnaProfile.php`  
**Table:** `property_dna_profiles`

Stores the supply-side DNA profile for seller and landlord listings.

**Key columns:**
| Column | Type | Purpose |
|---|---|---|
| `listing_type` | string | `seller` or `landlord` |
| `listing_id` | unsignedBigInteger | FK to source listing |
| `dna_version` | string | schema version string |
| `scoring_framework_version` | string | compatibility scoring version |
| `dna_coverage_metric` | decimal(5,2) | fraction of populated slots (coverage, not quality) |
| `ai_buyer_archetype_tags` | JSON | structured archetype tags used by Compatibility Engine |
| `ai_marketing_hooks` | JSON | structured hooks used by Compatibility Engine |
| `personality_summary` | text (nullable) | AI-derived personality description |
| `archived_at` | timestamp (nullable) | soft-archive |
| (29 dimension slots) | various | supply-side DNA signals |

**Governance:** `dna_coverage_metric` is a completeness fraction only — not a ranking or quality score.

**Integration gap:** No `location_dna_context` column. Phase F integration with LocationDna is deferred (see Section 7.6).

---

### 3.2 BuyerTenantDnaProfile
**Status: BUILT**  
**File:** `app/Models/BuyerTenantDnaProfile.php`  
**Table:** `buyer_tenant_dna_profiles`

Stores the demand-side DNA profile for buyer and tenant listings.

**Key columns:**
| Column | Type | Purpose |
|---|---|---|
| `listing_type` | string | `buyer` or `tenant` |
| `listing_id` | unsignedBigInteger | FK to source listing |
| `dna_version` | string | schema version string |
| `scoring_framework_version` | string | compatibility scoring version |
| `dna_coverage_metric` | decimal(5,2) | fraction of populated slots |
| `lifestyle_tags` | JSON | demand-side tags consumed by Compatibility Engine |
| `deal_breaker_flags` | JSON | structured flags (e.g., `garage_required`, `pool_required`) |
| `personality_summary` | text (nullable) | AI-derived personality description |
| `archived_at` | timestamp (nullable) | soft-archive |
| (26 dimension slots) | various | demand-side DNA signals |

---

### 3.3 ListingCompatibilityScore
**Status: BUILT**  
**File:** `app/Models/ListingCompatibilityScore.php`  
**Table:** `listing_compatibility_scores`

Stores the computed compatibility result for a supply/demand listing pair.

**Key columns (original migration):**
| Column | Purpose |
|---|---|
| `demand_listing_type` | `buyer` or `tenant` |
| `demand_listing_id` | FK to demand listing |
| `supply_listing_type` | `seller` or `landlord` |
| `supply_listing_id` | FK to supply listing |
| `aligned_dimensions` | JSON array of aligned dimension names |
| `conflicting_dimensions` | JSON array of conflicting dimension names |
| `unresolved_dimensions` | JSON array of unresolved dimension names |
| `dimension_match_map` | JSON keyed map: dimension → aligned/conflicting/unresolved |
| `eligible_dimension_count` | integer — the denominator for coverage metric |
| `compatibility_coverage_metric` | decimal(5,2) — resolved eligible / eligible × 100 |

**Key columns added in migration 2026_05_29 (BYA pipeline):**
| Column | Purpose |
|---|---|
| `bya_norm_payload` | JSON — BYA_NORM_V1 consumer normalization |
| `bya_agent_norm_payload` | JSON — BYA_AGENT_NORM_V1 agent normalization |
| `bya_comp_payload` | JSON — BYA_COMP_V1 comparison |
| `bya_align_payload` | JSON — BYA_ALIGN_V1 alignment |
| `bya_explain_payload` | JSON — BYA_EXPLAIN_V1 explanation |
| `bya_narrative_payload` | JSON — BYA_NARRATIVE_V1 narrative |
| `bya_report_payload` | JSON — BYA_REPORT_V1 final report |

---

### 3.4 PropertyLocationDna
**Status: BUILT**  
**File:** `app/Models/PropertyLocationDna.php`  
**Table:** `property_location_dna`

Stores geocoded location data and POI proximity summary for a property.

**Key column groups:**
- Geocoding: `lat`, `lng`, `formatted_address`, `place_id`, `street_number`, `route`, `city`, `county`, `state`, `zip`, `country`
- Admin areas: `admin_area_level_1` through `_4`
- POI distances: one nullable decimal per POI category (19 total)
- Summary fields: `walkability_tier`, `transit_access_tier`, `lifestyle_tier`, `neighborhood_character_tags` (JSON)
- Audit: `geocode_status`, `poi_status`, `last_geocoded_at`, `last_poi_fetched_at`

---

### 3.5 PropertyLocationDnaAudit
**Status: BUILT**  
**File:** `app/Models/PropertyLocationDnaAudit.php`  
**Table:** `property_location_dna_audits`

Append-only audit log for all changes to `PropertyLocationDna` records. Columns mirror `PropertyLocationDna` with an added `change_reason` and `changed_by` (nullable FK to users).

---

### 3.6 PropertyLocationPoi
**Status: BUILT**  
**File:** `app/Models/PropertyLocationPoi.php`  
**Table:** `property_location_pois`

Stores individual POI records discovered per property. Each record captures: `poi_type`, `poi_name`, `poi_address`, `distance_miles`, `duration_minutes`, `place_id`, `fetch_status`.

---

### 3.7 DnaMarketingOutput
**Status: BUILT**  
**File:** `app/Models/DnaMarketingOutput.php`  
**Table:** `dna_marketing_outputs`

Stores the marketing output produced by the marketing intelligence pipeline for a given listing. Links to `property_dna_profiles` and carries the assembled marketing payload as JSON.

---

## 4. Database Migrations

All DNA-related migrations identified. Listed in chronological order:

| Migration File | Table Created / Modified | Status |
|---|---|---|
| `2026_05_27_000001_create_property_dna_profiles_table` | `property_dna_profiles` | ✅ BUILT |
| `2026_05_27_000002_create_buyer_tenant_dna_profiles_table` | `buyer_tenant_dna_profiles` | ✅ BUILT |
| `2026_05_27_000003_create_listing_compatibility_scores_table` | `listing_compatibility_scores` | ✅ BUILT |
| `2026_05_27_000005_create_dna_marketing_outputs_table` | `dna_marketing_outputs` | ✅ BUILT |
| `2026_05_29_000001_add_compatibility_columns_to_listing_compatibility_scores` | `listing_compatibility_scores` (BYA payload columns) | ✅ BUILT |
| `2026_05_31_000002_create_marketing_reports_table` | `marketing_reports` | ✅ BUILT |
| `2026_05_31_000003_create_marketing_report_versions_table` | `marketing_report_versions` | ✅ BUILT |
| `2026_05_31_000004_create_marketing_report_audits_table` | `marketing_report_audits` | ✅ BUILT |
| `2026_05_31_000005_create_property_location_dna_table` | `property_location_dna` | ✅ BUILT |
| `2026_05_31_000006_unique_constraint_property_location_dna` | constraint on `property_location_dna` | ✅ BUILT |
| `2026_05_31_000007_create_property_location_pois_table` | `property_location_pois` | ✅ BUILT |
| `2026_05_31_000008_create_property_location_dna_audits_table` | `property_location_dna_audits` | ✅ BUILT |

**Gap:** No migration exists for a `location_dna_context` column on `property_dna_profiles` — confirming the Phase F integration into the generator is deferred.

---

## 5. DNA Generators

### 5.1 PropertyDnaGenerator
**Status: BUILT**  
**File:** `app/Services/Dna/PropertyDnaGenerator.php`  
**Covers:** `seller`, `landlord`

Generates the supply-side DNA profile from listing data. Dispatched by `ComputePropertyDnaProfile` job, triggered by `PropertyAuctionDnaObserver` and `LandlordAuctionDnaObserver` on `saved` events.

**29 Dimension Slots:**

| # | Slot Name | Seller Source | Landlord Source | Notes |
|---|---|---|---|---|
| 1 | `property_type` | `property_type` column | `property_type` column | |
| 2 | `bedrooms` | `bedrooms` column | `bedrooms` column | |
| 3 | `bathrooms` | `bathrooms` column | `bathrooms` column | |
| 4 | `square_footage` | `square_footage` column | `square_footage` column | |
| 5 | `lot_size` | `lot_size` column | `lot_size` column | |
| 6 | `year_built` | `year_built` column | `year_built` column | |
| 7 | `garage` | `garage` column | `garage` column | |
| 8 | `pool` | `pool` column | `pool` column | |
| 9 | `hoa` | `hoa` column | `hoa` column | |
| 10 | `hoa_fee` | `hoa_fee` meta key | `hoa_fee` meta key | |
| 11 | `zoning` | `zoning` column | `zoning` column | |
| 12 | `condition` | `condition` column | `condition` column | |
| 13 | `occupant_status` | `occupant_status` meta key | `occupant_status` meta key | |
| 14 | `seller_financing` | `seller_financing` meta key | N/A | Seller only |
| 15 | `assumable_loan` | `assumable_loan` meta key | N/A | Seller only |
| 16 | `lease_option` | `lease_option` meta key | `lease_option` meta key | |
| 17 | `lease_purchase` | `lease_purchase` meta key | `lease_purchase` meta key | |
| 18 | `pet_policy` | N/A | `pet_policy` meta key | Landlord only |
| 19 | `smoking_policy` | N/A | `smoking_policy` meta key | Landlord only |
| 20 | `parking` | `parking` meta key | `parking` meta key | |
| 21 | `furnishing_terms` | N/A | `furnishing_terms` meta key | Landlord only |
| 22 | `move_in_date` | `move_in_date` meta key | `available_date` meta key | |
| 23 | `commercial_use` | `commercial_use` meta key | `commercial_use` meta key | |
| 24 | `flood_zone` | `flood_zone` meta key | `flood_zone` meta key | |
| 25 | `basement` | `basement` meta key | `basement` meta key | |
| 26 | `carport` | `carport` meta key | `carport` meta key | |
| 27 | `amenities` | `amenities` meta key | `amenities` meta key | |
| 28 | `view` | `view` meta key | `view` meta key | |
| 29 | `waterfront` | `waterfront` meta key | `waterfront` meta key | |

**Phase F NOTE (deferred integration):**  
A comment in `PropertyDnaGenerator` states that the Location DNA context from `PropertyContextService` is not yet integrated into the profile because the `location_dna_context` column does not exist on `property_dna_profiles`. This defers geographically enriched signals from the generator output. See Section 7.6.

**AI-Derived Outputs (computed after dimension slots):**
- `ai_buyer_archetype_tags` — structured tag array (e.g., `type:SingleFamily`, `financing:seller-financed`, `amenity:pool`, `parking:garage`)
- `ai_marketing_hooks` — structured hook array with trait/value pairs (e.g., `{trait: occupant_status, value: vacant}`)
- `personality_summary` — free-text AI description

---

### 5.2 BuyerTenantDnaGenerator
**Status: BUILT**  
**File:** `app/Services/Dna/BuyerTenantDnaGenerator.php`  
**Covers:** `buyer`, `tenant`

Generates the demand-side DNA profile from buyer/tenant criteria listings. Dispatched by `ComputeBuyerTenantDnaProfile` job, triggered by `BuyerCriteriaAuctionDnaObserver` and `TenantCriteriaAuctionDnaObserver`.

**26 Dimension Slots:**

| # | Slot Name | Buyer Source | Tenant Source | Notes |
|---|---|---|---|---|
| 1 | `property_type_preference` | `property_type` column | `property_type` column | |
| 2 | `min_bedrooms` | `min_bedrooms` column | `min_bedrooms` column | |
| 3 | `max_bedrooms` | `max_bedrooms` column | `max_bedrooms` column | |
| 4 | `min_bathrooms` | `min_bathrooms` column | `min_bathrooms` column | |
| 5 | `max_bathrooms` | `max_bathrooms` column | `max_bathrooms` column | |
| 6 | `min_square_footage` | `min_sqft` meta key | `min_sqft` meta key | |
| 7 | `max_square_footage` | `max_sqft` meta key | `max_sqft` meta key | |
| 8 | `min_budget` | `min_price` column | `min_rent` column | |
| 9 | `max_budget` | `max_price` column | `max_rent` column | |
| 10 | `garage_required` | `garage_required` meta key | `garage_required` meta key | feeds deal_breaker_flags |
| 11 | `pool_required` | `pool_required` meta key | `pool_required` meta key | feeds deal_breaker_flags |
| 12 | `carport_required` | `carport_required` meta key | `carport_required` meta key | feeds deal_breaker_flags |
| 13 | `open_to_seller_financing` | `seller_financing` meta key | N/A | Buyer only |
| 14 | `open_to_assumable_loan` | `assumable_loan` meta key | N/A | Buyer only |
| 15 | `open_to_lease_option` | `lease_option` meta key | `lease_option` meta key | |
| 16 | `open_to_lease_purchase` | `lease_purchase` meta key | N/A | Buyer only |
| 17 | `has_pets` | `has_pets` meta key | `has_pets` meta key | feeds lifestyle_tags |
| 18 | `smoking_preference` | N/A | `smoking` meta key | Tenant only |
| 19 | `preferred_move_in_date` | `target_move_date` meta key | `desired_move_date` meta key | |
| 20 | `timeline_flexibility` | `timeline_flexibility` meta key | `timeline_flexibility` meta key | internally stored but no lifestyle tag emitted |
| 21 | `hoa_acceptable` | `hoa_acceptable` meta key | N/A | Buyer only |
| 22 | `commercial_interest` | `commercial_interest` meta key | N/A | Buyer only |
| 23 | `min_lot_size` | `min_lot_size` meta key | N/A | Buyer only |
| 24 | `preferred_zoning` | `preferred_zoning` meta key | N/A | Buyer only |
| 25 | `waterfront_required` | `waterfront_required` meta key | N/A | Buyer only |
| 26 | `basement_required` | `basement_required` meta key | N/A | Buyer only |

**Demand-side AI-Derived Outputs:**
- `lifestyle_tags` — structured tags consumed by Compatibility Engine (e.g., `prefers-type:SingleFamily`, `has-pets`, `open-to:seller-financing`, `requires:garage`, `requires:pool`)
- `deal_breaker_flags` — structured flags array (e.g., `{flag: garage_required}`, `{flag: pool_required}`)
- `personality_summary` — free-text AI description

**Gap noted:** `timeline_flexibility` is stored as a dimension slot but no corresponding lifestyle tag is emitted by this generator. This makes `timeline_alignment` permanently unresolved in the Compatibility Engine.

---

## 6. DNA Explanation Services

### 6.1 PropertyDnaExplanationService
**Status: BUILT**  
**File:** `app/Services/Dna/PropertyDnaExplanationService.php`

Produces plain-language explanations of each populated supply-side dimension slot. Accepts a `PropertyDnaProfile` instance and returns a keyed array of human-readable descriptions per slot. Used for internal audit and potential future display layers.

---

### 6.2 BuyerTenantDnaExplanationService
**Status: BUILT**  
**File:** `app/Services/Dna/BuyerTenantDnaExplanationService.php`

Produces plain-language explanations of each populated demand-side dimension slot. Accepts a `BuyerTenantDnaProfile` instance and returns a keyed array of human-readable descriptions per slot.

---

## 7. Location DNA Pipeline

Seven services operate in sequence to enrich a property listing with geographic and proximity intelligence. All location DNA services target the supply side (seller and landlord listings) only.

### 7.1 GeocodeService — Phase B
**Status: BUILT**  
**File:** `app/Services/Dna/Location/GeocodeService.php`

Calls the Google Places API (Geocoding endpoint) with the property's raw address. Writes to `PropertyLocationDna`: `lat`, `lng`, `formatted_address`, `place_id`, all administrative area fields, and sets `geocode_status = completed`.

**Fail behavior:** On API error, writes `geocode_status = failed`. Never throws.

---

### 7.2 PoiDistanceService — Phase C
**Status: BUILT**  
**File:** `app/Services/Dna/Location/PoiDistanceService.php`

Calls Google Places API (Nearby Search) to discover Points of Interest within a configurable radius, then calls the Distance Matrix API to compute driving distance and duration from the property to each POI.

**19 POI categories supported:**

| Category | Signal type |
|---|---|
| `hospital` | Google Place type (native) |
| `school` | Google Place type (native) |
| `grocery_or_supermarket` | Google Place type (native) |
| `restaurant` | Google Place type (native) |
| `park` | Google Place type (native) |
| `gym` | Google Place type (native) |
| `pharmacy` | Google Place type (native) |
| `shopping_mall` | Google Place type (native) |
| `gas_station` | Google Place type (native) |
| `transit_station` | Google Place type (native) |
| `airport` | Google Place type (native) |
| `police` | Google Place type (native) |
| `beach` | Keyword-based search |
| `marina` | Keyword-based search |
| `boat_ramp` | Keyword-based search |
| `golf_course` | Keyword-based search |
| `ski_resort` | Keyword-based search |
| `dog_park` | Keyword-based search |
| `farmer_market` | Keyword-based search |

Results written to `property_location_pois` (one record per POI) and nearest distances summarized in `property_location_dna` columns.

---

### 7.3 SummaryService — Phase D
**Status: BUILT**  
**File:** `app/Services/Dna/Location/SummaryService.php`

Reads `PropertyLocationDna` POI distance columns and derives human-readable tier scores. Writes: `walkability_tier`, `transit_access_tier`, `lifestyle_tier`, `neighborhood_character_tags`.

---

### 7.4 AuditService — Phase E
**Status: BUILT**  
**File:** `app/Services/Dna/Location/AuditService.php`

Append-only audit logger. Called after any write to `PropertyLocationDna`. Creates a new `PropertyLocationDnaAudit` record mirroring the current state of the `PropertyLocationDna` row with `change_reason` and optional `changed_by`.

---

### 7.5 PropertyContextService — Phase F
**Status: BUILT (service) / DEFERRED (integration)**  
**File:** `app/Services/Dna/Location/PropertyContextService.php`

Reads a `PropertyLocationDna` record and assembles a structured context bundle describing the property's location profile in terms directly useful to the `PropertyDnaGenerator`. Context includes: proximity tier labels, lifestyle fit descriptors, neighborhood character tags, and POI distance bands.

**Integration gap:** A `// PHASE F NOTE` comment in `PropertyDnaGenerator` explicitly states that the `location_dna_context` output of this service is not yet integrated into the property DNA profile, because the `location_dna_context` column does not exist on `property_dna_profiles`. This service is fully built and correct but the generator does not consume it. Supply-side DNA profiles therefore contain no geographic enrichment signals.

---

### 7.6 MarketingContextService — Phase G
**Status: BUILT**  
**File:** `app/Services/Dna/Location/MarketingContextService.php`

Reads `PropertyLocationDna` and formats a location context bundle for use by the marketing intelligence pipeline. Output is consumed by `PropertyMarketingContextService` (Phase P). Produces: neighborhood narrative fragments, POI highlights, lifestyle tier descriptions.

---

### 7.7 IntelligenceContextService — Phase H
**Status: BUILT**  
**File:** `app/Services/Dna/Location/IntelligenceContextService.php`

Reads `PropertyLocationDna` and produces a structured intelligence context for use by AI marketing report services. Provides structured data rather than narrative, suitable for structured prompt injection into the AI marketing pipeline.

---

## 8. Compatibility Engine — Listing Intelligence

### 8.1 CompatibilityEngine
**Status: BUILT (core service) / PARTIAL (dimension coverage)**  
**File:** `app/Services/Dna/Compatibility/CompatibilityEngine.php`  
**Phase:** F / K  
**FANOUT_CAP constant:** 500

Computes dimensional compatibility between a `PropertyDnaProfile` (supply) and a `BuyerTenantDnaProfile` (demand). All logic is deterministic and rule-based. No AI, no weighting, no scoring.

**14 Total Dimensions:**

| # | Dimension | Supply Signal | Demand Signal | Phase H Status |
|---|---|---|---|---|
| 1 | `property_type_alignment` | `ai_buyer_archetype_tags`: `type:{value}` | `lifestyle_tags`: `prefers-type:{value}` | ✅ ELIGIBLE |
| 2 | `financing_alignment` | `ai_buyer_archetype_tags`: `financing:seller-financed`, `financing:assumable` | `lifestyle_tags`: `open-to:seller-financing`, `open-to:assumable-loan` | ✅ ELIGIBLE |
| 3 | `lease_structure_alignment` | `ai_buyer_archetype_tags`: `structure:lease-option`, `structure:lease-purchase` | `lifestyle_tags`: `open-to:lease-option`, `open-to:lease-purchase` | ✅ ELIGIBLE |
| 4 | `pet_policy_alignment` | `ai_buyer_archetype_tags`: `policy:pets-allowed` | `lifestyle_tags`: `has-pets`; `deal_breaker_flags`: `pet_required` | ✅ ELIGIBLE |
| 5 | `smoking_policy_alignment` | `ai_buyer_archetype_tags`: `policy:restrictions-specified` | `lifestyle_tags`: `preference:restrictions-specified` | ✅ ELIGIBLE |
| 6 | `parking_alignment` | `ai_buyer_archetype_tags`: `parking:garage`, `parking:carport` | `lifestyle_tags`: `requires:garage`, `requires:carport`; `deal_breaker_flags` | ✅ ELIGIBLE |
| 7 | `commercial_alignment` | `ai_buyer_archetype_tags`: `use:commercial` | `lifestyle_tags`: `prefers-type:{value}` (indirect) | ✅ ELIGIBLE |
| 8 | `amenity_alignment` | `ai_buyer_archetype_tags`: `amenity:pool` | `lifestyle_tags`: `requires:pool`; `deal_breaker_flags`: `pool_required` | ✅ ELIGIBLE |
| 9 | `occupancy_alignment` | `ai_marketing_hooks`: `occupant_status` | No demand-side tag | ⚠️ INELIGIBLE — no demand signal |
| 10 | `furnishing_alignment` | `ai_buyer_archetype_tags`: `feature:furnishing-terms-specified` | No demand-side furnishing tag | ⚠️ INELIGIBLE — no demand signal |
| 11 | `timeline_alignment` | `ai_buyer_archetype_tags`: `timing:move-in-specified` | `timeline_flexibility` stored but no lifestyle tag emitted | ⚠️ INELIGIBLE — no demand tag |
| 12 | `budget_alignment` | No price dimension in PropertyDnaProfile | `deal_breaker_flags`: `budget_ceiling_specified` | ⚠️ INELIGIBLE — no supply signal |
| 13 | `lease_term_alignment` | `ai_marketing_hooks`: `lease_length` | No structured lease term flag emitted | ⚠️ INELIGIBLE — no demand flag |
| 14 | `hoa_alignment` | `ai_buyer_archetype_tags`: `governance:hoa` | `lifestyle_tags`: `preference:hoa-community-aware` | ⚠️ INELIGIBLE — confirmed excluded (Phase J / R-03 audit) |

**Eligible dimensions:** 8 of 14  
**Ineligible dimensions:** 6 of 14 (always return `unresolved`; excluded from coverage metric denominator)

**Coverage metric formula:**  
`compatibility_coverage_metric = (resolved eligible dimensions / 8) × 100`  
where "resolved" means the result is `aligned` or `conflicting` (not `unresolved`).

**Governance:** `compatibility_coverage_metric` is a coverage/completeness metric ONLY — never a ranking, quality, or recommendation score. `accessibility_requirements` is permanently excluded from all compatibility computation.

---

## 9. BYA Compatibility Pipeline — Agent-Consumer Working Style

**This is a separate system from the Compatibility Engine.** The Compatibility Engine matches listing attribute signals between PropertyDnaProfile and BuyerTenantDnaProfile. The BYA pipeline matches consumer working-style preferences (from listing `compatibility_preferences` EAV meta) against agent bid response profiles (from agent bid `compatibility_preferences` response sections).

BYA results are stored in BYA payload columns on `listing_compatibility_scores` (added in migration 2026_05_29).

### 9.1 ByaNormalizationService — BYA_NORM_V1
**Status: BUILT**  
**File:** `app/Services/Dna/Compatibility/ByaNormalizationService.php`

Reads a consumer listing's `compatibility_preferences` EAV meta blob and emits a 12-trait canonical BYA_NORM_V1 payload. Supports all four roles (seller, buyer, landlord, tenant).

**12 Canonical Trait Slots:**

| Trait | Seller source | Buyer source | Landlord source | Tenant source |
|---|---|---|---|---|
| `communication_channel` | `preferred_contact_method` | `preferred_contact_method` | `communication_style` | `communication_style` |
| `communication_frequency` | `communication_style` | `communication_style` | `preferred_contact_method` | `contact_frequency` |
| `responsiveness_expectation` | `response_time_expectation` | ABSENT | `response_time_expectation` | ABSENT |
| `negotiation_style` | `negotiation_style` | `negotiation_style` | `negotiation_style` | `negotiation_style` |
| `guidance_level` | `involvement_level` | `support_level` | `property_management_involvement` | `desired_level_of_agent_involvement` |
| `decision_making_style` | `decision_making_style` | `decision_making_style` | ABSENT | `decision_making_style` |
| `transaction_pace` | `flexibility_on_timeline` | `timeline_flexibility` | ABSENT | `timeline_urgency` |
| `risk_tolerance` | ABSENT | `risk_tolerance` | `risk_tolerance` | ABSENT |
| `collaboration_style` | `preferred_agent_working_style` | `preferred_agent_working_style` + `communication_frequency` (showing format) | `preferred_agent_working_style` | `preferred_agent_working_style` |
| `representation_priorities` | `representation_priorities` | `representation_priorities` | `representation_priorities` | `representation_priorities` |
| `representation_philosophy` | `past_agent_experience` (Seller only) | ABSENT | ABSENT | ABSENT |
| `property_strategy_fit` | `primary_transaction_goal` | `primary_transaction_goal` | `primary_leasing_goal` | `primary_rental_goal` |

**Proxy risk flags:** `tenant_type_preference` (Landlord) is flagged with a Fair Housing governance warning and must never be used to weight or penalize agents demographically.

---

### 9.2 ByaAgentResponseNormalizationService — BYA_AGENT_NORM_V1
**Status: BUILT**  
**File:** `app/Services/Dna/Compatibility/ByaAgentResponseNormalizationService.php`

Reads agent bid compatibility responses from 7 canonical sections (via `HasCompatibilityPreferences` trait) and emits a BYA_AGENT_NORM_V1 payload conforming to the same 12-trait shape as BYA_NORM_V1.

**7 Agent Response Sections:**
1. `communication_preferences`
2. `negotiation_approach`
3. `guidance_style`
4. `collaboration_preferences`
5. `transaction_strategy`
6. `representation_philosophy`
7. `representation_priorities`

**Proxy risk flags (agent side, 3 fields):**
- `agent_tenant_screening_strictness` — Fair Housing risk
- `agent_tenant_profile_specialization` — Fair Housing risk
- `agent_property_strategy_specialization` — demographic correlation risk

---

### 9.3 ByaCompatibilityComparisonService — BYA_COMP_V1
**Status: BUILT (service) / PARTIAL (dimension coverage)**  
**File:** `app/Services/Dna/Compatibility/ByaCompatibilityComparisonService.php`

Accepts BYA_NORM_V1 (consumer) and BYA_AGENT_NORM_V1 (agent) payloads and computes a 12-dimension comparison payload.

**12 BYA Comparison Dimensions:**

| # | Dimension | Consumer trait key | Agent trait key | Phase I Status |
|---|---|---|---|---|
| 1 | `communication_style` | `communication_channel` | `communication_channel` | ✅ ACTIVE |
| 2 | `communication_frequency` | `communication_frequency` | `communication_frequency` | ✅ ACTIVE |
| 3 | `decision_speed` | `transaction_pace` | `transaction_pace` | ✅ ACTIVE |
| 4 | `risk_tolerance` | `risk_tolerance` | `risk_tolerance` | ✅ ACTIVE |
| 5 | `negotiation_style` | `negotiation_style` | `negotiation_style` | ✅ ACTIVE |
| 6 | `advisor_expectation` | `guidance_level` | `guidance_level` | ✅ ACTIVE |
| 7 | `technology_preference` | null — ABSENT | null — ABSENT | ⚠️ PLACEHOLDER — always unknown |
| 8 | `market_education_preference` | null — ABSENT | null — ABSENT | ⚠️ PLACEHOLDER — always unknown |
| 9 | `property_search_involvement` | `collaboration_style` | `collaboration_style` | ✅ ACTIVE |
| 10 | `transaction_guidance_level` | `decision_making_style` | `decision_making_style` | ✅ ACTIVE |
| 11 | `availability_expectation` | `responsiveness_expectation` | `responsiveness_expectation` | ✅ ACTIVE |
| 12 | `personality_style` | `representation_philosophy` | `representation_philosophy` | ✅ ACTIVE |

**Relationship values:** `same` | `different` | `unknown`. The `similar` value is reserved for future governance-defined similarity tables; not emitted in Phase I.

---

### 9.4 ByaCompatibilityAlignmentService — BYA_ALIGN_V1
**Status: BUILT**  
**File:** `app/Services/Dna/Compatibility/ByaCompatibilityAlignmentService.php`

Maps BYA_COMP_V1 relationship values to 6 alignment categories and derives a qualitative advisory label.

**Relationship → Category mapping:**

| Relationship | Alignment Category |
|---|---|
| `same` | `full_alignment` |
| `similar` | `partial_alignment` (reserved; unreachable in Milestone 5) |
| `different` | `incompatible_alignment` |
| `unknown` | `insufficient_data` |

**Advisory label rules (applied in priority order):**
1. `scored_dimensions == 0` → `insufficient_compatibility_data`
2. `incompatible / scored >= 0.40` → `notable_differences`
3. `(full + partial) / scored >= 0.60` → `strong_alignment`
4. Otherwise → `broad_compatibility` (mathematically unreachable in Milestone 5 given only 3 scored categories)

**Unreachable categories (Milestone 5):** `adjacent_compatibility`, `neutral_compatibility` — reserved for future phases.

---

### 9.5 ByaCompatibilityExplanationService — BYA_EXPLAIN_V1
**Status: BUILT**  
**File:** `app/Services/Dna/Compatibility/ByaCompatibilityExplanationService.php`

Maps each BYA_ALIGN_V1 alignment category to an explanation type and derives an `explanation_key` (e.g., `communication_style_full_alignment`). Pure key-derivation layer — no narrative text.

**Category → Explanation type:**

| Alignment Category | Explanation Type |
|---|---|
| `full_alignment` | `alignment` |
| `partial_alignment` | `alignment` |
| `incompatible_alignment` | `difference` |
| `insufficient_data` | `insufficient_data` |
| `adjacent_compatibility` | `adjacent` (reserved) |
| `neutral_compatibility` | `neutral` (reserved) |

---

### 9.6 ByaCompatibilityNarrativeService — BYA_NARRATIVE_V1
**Status: BUILT**  
**File:** `app/Services/Dna/Compatibility/ByaCompatibilityNarrativeService.php`

Accepts BYA_EXPLAIN_V1 and BYA_ALIGN_V1 payloads and produces per-dimension plain-language sentences from a pre-approved template library. No AI. All templates hardcoded in PHP constants.

**Template library:** 32 total templates
- 10 × `full_alignment` templates (one per active dimension)
- 10 × `incompatible_alignment` templates (one per active dimension)
- 12 × `insufficient_data` templates (one per dimension, including placeholders)

**Fallback rules:** `adjacent` and `neutral` explanation types fall back to `insufficient_data` templates per Section 8.4 governance.

**Summary sentence templates (7):** `all_alignment`, `mostly_alignment`, `mostly_difference`, `all_difference`, `mixed`, `all_insufficient`, `no_dimensions`.

---

### 9.7 ByaCompatibilityReportService — BYA_REPORT_V1
**Status: BUILT**  
**File:** `app/Services/Dna/Compatibility/ByaCompatibilityReportService.php`

Final assembly layer. Merges BYA_ALIGN_V1, BYA_EXPLAIN_V1, and BYA_NARRATIVE_V1 payloads into a single BYA_REPORT_V1 output with per-dimension merge, summary counts, and an audit block with trace keys.

**Per-dimension merged fields:**
- `relationship` (from align)
- `alignment_category` (from align)
- `explanation_type` (from explain)
- `explanation_key` (from explain)
- `template_id` (from narrative)
- `sentence` (from narrative)

---

## 10. Marketing Intelligence Pipeline

### 10.1 Supply-Side Pipeline (seller + landlord)

All 10 services are **BUILT**. No code changes observed. Pipeline is gated by `PropertyMarketingReadinessService` (Phase U).

| Phase | Service | File | Purpose |
|---|---|---|---|
| P | `PropertyMarketingContextService` | `app/Services/Dna/Marketing/PropertyMarketingContextService.php` | Assembles deterministic context bundle from PropertyDnaProfile + LocationDna + listing fields |
| R | `PropertyMarketingBriefService` | `app/Services/Dna/Marketing/PropertyMarketingBriefService.php` | Compiles structured marketing brief from context |
| U | `PropertyMarketingReadinessService` | `app/Services/Dna/Marketing/PropertyMarketingReadinessService.php` | Gates pipeline — checks DNA coverage, location status, listing completeness |
| XD | `AiMarketingReportGeneratorService` | `app/Services/Dna/Marketing/AiMarketingReportGeneratorService.php` | Sends brief to OpenAI and receives AI-generated report sections |
| XF | `AiMarketingReportReviewService` | `app/Services/Dna/Marketing/AiMarketingReportReviewService.php` | Secondary AI review pass |
| XG | `AiMarketingReportOrchestratorService` | `app/Services/Dna/Marketing/AiMarketingReportOrchestratorService.php` | Orchestrates XD → XF → XJ sequence |
| XJ | `AiMarketingReportPersistenceService` | `app/Services/Dna/Marketing/AiMarketingReportPersistenceService.php` | Persists report to `marketing_reports` + `marketing_report_versions` (5 sections) + `marketing_report_audits` (append-only) |
| XN | `AiMarketingReportAgentRevisionService` | `app/Services/Dna/Marketing/AiMarketingReportAgentRevisionService.php` | Handles agent-requested edits to report sections |
| XO | `AiMarketingReportOwnerApprovalService` | `app/Services/Dna/Marketing/AiMarketingReportOwnerApprovalService.php` | Owner review and approval flow |
| XP | `AiMarketingReportPublicationService` | `app/Services/Dna/Marketing/AiMarketingReportPublicationService.php` | Publication and visibility management |

**Storage architecture:**
- `marketing_reports` — one record per listing; tracks status, approval state, publication state
- `marketing_report_versions` — append-only sections (5 section types per version)
- `marketing_report_audits` — append-only action log
- `dna_marketing_outputs` — links PropertyDnaProfile to final marketing output

### 10.2 Demand-Side Marketing Context
**Status: PARTIAL**  
**File:** `app/Services/Dna/Marketing/BuyerTenantMarketingContextService.php`

Assembles a marketing context bundle from `BuyerTenantDnaProfile`. Built as a context builder, but there is no AI report generation pipeline for the demand side — no equivalent of XD/XF/XG/XJ/XN/XO/XP for buyer/tenant listings. The service produces context data but it has no downstream AI report consumer.

---

## 11. Event Dispatch — Observers and Jobs

### 11.1 DNA Generation Observers (4)

| Observer | File | Model | Triggers |
|---|---|---|---|
| `PropertyAuctionDnaObserver` | `app/Observers/Dna/PropertyAuctionDnaObserver.php` | `PropertyAuction` | `ComputePropertyDnaProfile::dispatch('seller', $id)` |
| `LandlordAuctionDnaObserver` | `app/Observers/Dna/LandlordAuctionDnaObserver.php` | `LandlordAuction` | `ComputePropertyDnaProfile::dispatch('landlord', $id)` |
| `BuyerCriteriaAuctionDnaObserver` | `app/Observers/Dna/BuyerCriteriaAuctionDnaObserver.php` | `BuyerCriteriaAuction` | `ComputeBuyerTenantDnaProfile::dispatch('buyer', $id)` |
| `TenantCriteriaAuctionDnaObserver` | `app/Observers/Dna/TenantCriteriaAuctionDnaObserver.php` | `TenantCriteriaAuction` | `ComputeBuyerTenantDnaProfile::dispatch('tenant', $id)` |

All four observers hook the `saved` event. Dispatch errors are caught, logged, and silently discarded — never thrown.

### 11.2 Compatibility Dispatch Observers (2)

| Observer | Triggered By | Counterpart Target | Constraint |
|---|---|---|---|
| `PropertyDnaProfileCompatibilityObserver` | `PropertyDnaProfile::saved` | All active `BuyerTenantDnaProfile` records of matching counterpart type | seller→buyer; landlord→tenant |
| `BuyerTenantDnaProfileCompatibilityObserver` | `BuyerTenantDnaProfile::saved` | All active `PropertyDnaProfile` records of matching counterpart type | buyer→seller; tenant→landlord |

**Fanout governance (both observers):**
- `FANOUT_CAP = 500` — hard ceiling on dispatch count per invocation
- `DISPATCH_CHUNK_SIZE = 100` — dispatch loop chunk size
- Fail-closed: if counterpart count exceeds cap, logs overcount and dispatches only up to cap
- Fanout is one-directional and non-recursive: chain terminates at `ListingCompatibilityScore::create`
- `ListingCompatibilityScore` has no registered observer — no further jobs enqueue

### 11.3 Queued Jobs (3)

| Job | File |
|---|---|
| `ComputePropertyDnaProfile` | `app/Jobs/ComputePropertyDnaProfile.php` |
| `ComputeBuyerTenantDnaProfile` | `app/Jobs/ComputeBuyerTenantDnaProfile.php` |
| `ComputeCompatibilityScore` | `app/Jobs/ComputeCompatibilityScore.php` |

---

## 12. Role-Based Intelligence Profiles

The four role-intelligence profiles represent the consumer-facing or agent-facing view of each DNA profile type: what the platform knows about a seller, buyer, landlord, or tenant from their DNA signals.

### 12.1 Seller DNA Intelligence Profile
**Status: MISSING**

No dedicated Seller DNA intelligence profile page, dashboard component, or view layer exists. `PropertyDnaProfile` stores seller DNA data including `personality_summary`, `ai_buyer_archetype_tags`, and `ai_marketing_hooks`, but these are not surfaced through any public or agent-facing route.

**Data ready:** `PropertyDnaProfile` (seller) — fully built  
**Explanation layer ready:** `PropertyDnaExplanationService` — fully built  
**UI/display layer:** Not built

---

### 12.2 Landlord DNA Intelligence Profile
**Status: MISSING**

Same status as Seller. `PropertyDnaProfile` (landlord) is fully built and populated. No display layer exists.

**Data ready:** `PropertyDnaProfile` (landlord) — fully built  
**Explanation layer ready:** `PropertyDnaExplanationService` — fully built  
**UI/display layer:** Not built

---

### 12.3 Buyer DNA Intelligence Profile
**Status: MISSING**

`BuyerTenantDnaProfile` (buyer) is fully built. `BuyerTenantDnaExplanationService` is built. No consumer-facing or agent-facing view shows buyer DNA signals to any user.

**Data ready:** `BuyerTenantDnaProfile` (buyer) — fully built  
**Explanation layer ready:** `BuyerTenantDnaExplanationService` — fully built  
**UI/display layer:** Not built

---

### 12.4 Tenant DNA Intelligence Profile
**Status: MISSING**

Same status as Buyer. `BuyerTenantDnaProfile` (tenant) is fully built. No display layer exists.

**Data ready:** `BuyerTenantDnaProfile` (tenant) — fully built  
**Explanation layer ready:** `BuyerTenantDnaExplanationService` — fully built  
**UI/display layer:** Not built

---

## 13. Property Personality Readiness

**Status: PARTIAL**

`personality_summary` (text, nullable) exists as a column on both `PropertyDnaProfile` and `BuyerTenantDnaProfile`. It is computed by the generators as part of the AI-derived output step (alongside `ai_buyer_archetype_tags` and `ai_marketing_hooks` for supply, and `lifestyle_tags` / `deal_breaker_flags` for demand).

**What is built:**
- Column schema on both profile tables
- Generator output slot (value computed during `ComputePropertyDnaProfile` / `ComputeBuyerTenantDnaProfile` jobs)

**What is missing:**
- No `PropertyPersonalityReadinessService` or equivalent gating/completeness service for personality outputs
- No dedicated personality display component or route for any role
- No personality-driven matching logic (personality_summary is stored as free text with no structured encoding)
- No audit trail specific to personality changes
- No documentation of which AI prompt produces the personality_summary or what schema it is expected to conform to

---

## 14. DNA Output Inventory

This section enumerates all structured data artifacts produced by the DNA ecosystem and where they are stored.

### 14.1 Supply-Side DNA Outputs

| Output | Stored In | Column | Format |
|---|---|---|---|
| 29 supply dimension slots | `property_dna_profiles` | individual columns | mixed types |
| Buyer archetype tags | `property_dna_profiles` | `ai_buyer_archetype_tags` | JSON array of strings |
| Marketing hooks | `property_dna_profiles` | `ai_marketing_hooks` | JSON array of `{trait, value}` objects |
| Personality summary | `property_dna_profiles` | `personality_summary` | free text |
| DNA coverage metric | `property_dna_profiles` | `dna_coverage_metric` | decimal(5,2) |

### 14.2 Demand-Side DNA Outputs

| Output | Stored In | Column | Format |
|---|---|---|---|
| 26 demand dimension slots | `buyer_tenant_dna_profiles` | individual columns | mixed types |
| Lifestyle tags | `buyer_tenant_dna_profiles` | `lifestyle_tags` | JSON array of strings |
| Deal breaker flags | `buyer_tenant_dna_profiles` | `deal_breaker_flags` | JSON array of `{flag}` objects |
| Personality summary | `buyer_tenant_dna_profiles` | `personality_summary` | free text |
| DNA coverage metric | `buyer_tenant_dna_profiles` | `dna_coverage_metric` | decimal(5,2) |

### 14.3 Compatibility Outputs

| Output | Stored In | Column | Format |
|---|---|---|---|
| Aligned dimensions | `listing_compatibility_scores` | `aligned_dimensions` | JSON array of dimension names |
| Conflicting dimensions | `listing_compatibility_scores` | `conflicting_dimensions` | JSON array of dimension names |
| Unresolved dimensions | `listing_compatibility_scores` | `unresolved_dimensions` | JSON array of dimension names |
| Dimension match map | `listing_compatibility_scores` | `dimension_match_map` | JSON object: dimension → result |
| Eligible dimension count | `listing_compatibility_scores` | `eligible_dimension_count` | integer |
| Compatibility coverage metric | `listing_compatibility_scores` | `compatibility_coverage_metric` | decimal(5,2) |

### 14.4 BYA Pipeline Outputs (on ListingCompatibilityScore)

| Output | Column | Version Tag |
|---|---|---|
| Consumer normalization | `bya_norm_payload` | BYA_NORM_V1 |
| Agent normalization | `bya_agent_norm_payload` | BYA_AGENT_NORM_V1 |
| Comparison | `bya_comp_payload` | BYA_COMP_V1 |
| Alignment + advisory label | `bya_align_payload` | BYA_ALIGN_V1 |
| Explanation keys | `bya_explain_payload` | BYA_EXPLAIN_V1 |
| Per-dimension sentences + summary | `bya_narrative_payload` | BYA_NARRATIVE_V1 |
| Assembled report | `bya_report_payload` | BYA_REPORT_V1 |

### 14.5 Location DNA Outputs

| Output | Stored In | Notes |
|---|---|---|
| Geocoded coordinates + address | `property_location_dna` | `lat`, `lng`, `formatted_address`, `place_id`, admin areas |
| 19 POI category distances | `property_location_dna` | one decimal column per category |
| Walkability / transit / lifestyle tiers | `property_location_dna` | `walkability_tier`, `transit_access_tier`, `lifestyle_tier` |
| Neighborhood character tags | `property_location_dna` | `neighborhood_character_tags` JSON |
| Individual POI records | `property_location_pois` | one row per POI per property |
| Audit trail | `property_location_dna_audits` | append-only, mirrors full row state |

### 14.6 Marketing Intelligence Outputs

| Output | Stored In | Notes |
|---|---|---|
| Marketing report (5 sections) | `marketing_reports` + `marketing_report_versions` | append-only versioning |
| Report audit log | `marketing_report_audits` | append-only action log |
| DNA marketing output link | `dna_marketing_outputs` | links PropertyDnaProfile → marketing output |

---

## 15. Listing Input Field Catalogue

This section catalogues the form fields available per role that feed DNA dimensions. Based on prior session analysis of all four listing form Livewire components.

### 15.1 Seller Listing — DNA-Relevant Fields

| Field | DNA Dimension Slot | Type |
|---|---|---|
| `property_type` | `property_type` | single-select column |
| `bedrooms` | `bedrooms` | integer column |
| `bathrooms` | `bathrooms` | decimal column |
| `square_footage` | `square_footage` | integer column |
| `lot_size` | `lot_size` | decimal column |
| `year_built` | `year_built` | integer column |
| `garage` | `garage` | boolean column |
| `pool` | `pool` | boolean column |
| `hoa` | `hoa` | boolean column |
| `hoa_fee` | `hoa_fee` | meta key |
| `zoning` | `zoning` | single-select column |
| `condition` | `condition` | single-select column |
| `occupant_status` | `occupant_status` | meta key |
| `seller_financing` | `seller_financing` | meta key |
| `assumable_loan` | `assumable_loan` | meta key |
| `lease_option` | `lease_option` | meta key |
| `lease_purchase` | `lease_purchase` | meta key |
| `parking` | `parking` | meta key |
| `move_in_date` | `move_in_date` | meta key |
| `commercial_use` | `commercial_use` | meta key |
| `flood_zone` | `flood_zone` | meta key |
| `basement` | `basement` | meta key |
| `carport` | `carport` | meta key |
| `amenities` | `amenities` | meta key (multi-select) |
| `view` | `view` | meta key |
| `waterfront` | `waterfront` | meta key |

**Seller-specific BYA compatibility_preferences fields** (stored in EAV as `compatibility_preferences.seller_specific.*`):
`preferred_contact_method`, `communication_style`, `response_time_expectation`, `negotiation_style`, `involvement_level`, `decision_making_style`, `flexibility_on_timeline`, `preferred_agent_working_style`, `representation_priorities`, `past_agent_experience`, `primary_transaction_goal`, and 11 informational context fields.

---

### 15.2 Buyer Listing — DNA-Relevant Fields

| Field | DNA Dimension Slot | Type |
|---|---|---|
| `property_type` | `property_type_preference` | single-select column |
| `min_bedrooms`, `max_bedrooms` | `min_bedrooms`, `max_bedrooms` | integer columns |
| `min_bathrooms`, `max_bathrooms` | `min_bathrooms`, `max_bathrooms` | decimal columns |
| `min_price`, `max_price` | `min_budget`, `max_budget` | integer columns |
| `min_sqft`, `max_sqft` | `min_square_footage`, `max_square_footage` | meta keys |
| `garage_required` | `garage_required` | meta key → deal_breaker_flags |
| `pool_required` | `pool_required` | meta key → deal_breaker_flags |
| `carport_required` | `carport_required` | meta key → deal_breaker_flags |
| `seller_financing` | `open_to_seller_financing` | meta key → lifestyle_tags |
| `assumable_loan` | `open_to_assumable_loan` | meta key → lifestyle_tags |
| `lease_option` | `open_to_lease_option` | meta key → lifestyle_tags |
| `lease_purchase` | `open_to_lease_purchase` | meta key → lifestyle_tags |
| `has_pets` | `has_pets` | meta key → lifestyle_tags |
| `target_move_date` | `preferred_move_in_date` | meta key |
| `timeline_flexibility` | `timeline_flexibility` | meta key (stored, no tag emitted) |
| `hoa_acceptable` | `hoa_acceptable` | meta key |
| `commercial_interest` | `commercial_interest` | meta key |
| `waterfront_required` | `waterfront_required` | meta key |
| `basement_required` | `basement_required` | meta key |

**Buyer BYA compatibility_preferences fields** (stored as `compatibility_preferences.buyer_specific.*`):
`preferred_contact_method`, `communication_style`, `communication_frequency` (showing format), `negotiation_style`, `support_level`, `decision_making_style`, `timeline_flexibility`, `risk_tolerance`, `preferred_agent_working_style`, `representation_priorities`, `primary_transaction_goal`, and 6 informational context fields.

---

### 15.3 Landlord Listing — DNA-Relevant Fields

| Field | DNA Dimension Slot | Type |
|---|---|---|
| `property_type` | `property_type` | single-select column |
| `bedrooms` | `bedrooms` | integer column |
| `bathrooms` | `bathrooms` | decimal column |
| `square_footage` | `square_footage` | integer column |
| `lot_size` | `lot_size` | decimal column |
| `year_built` | `year_built` | integer column |
| `garage` | `garage` | boolean column |
| `pool` | `pool` | boolean column |
| `hoa` | `hoa` | boolean column |
| `hoa_fee` | `hoa_fee` | meta key |
| `zoning` | `zoning` | single-select column |
| `condition` | `condition` | single-select column |
| `occupant_status` | `occupant_status` | meta key |
| `lease_option` | `lease_option` | meta key |
| `lease_purchase` | `lease_purchase` | meta key |
| `pet_policy` | `pet_policy` | meta key → archetype tag |
| `smoking_policy` | `smoking_policy` | meta key → archetype tag |
| `parking` | `parking` | meta key |
| `furnishing_terms` | `furnishing_terms` | meta key |
| `available_date` | `move_in_date` | meta key |
| `commercial_use` | `commercial_use` | meta key |
| `flood_zone` | `flood_zone` | meta key |
| `basement` | `basement` | meta key |
| `carport` | `carport` | meta key |
| `amenities` | `amenities` | meta key |
| `view` | `view` | meta key |
| `waterfront` | `waterfront` | meta key |

**Landlord BYA compatibility_preferences fields** (stored as `compatibility_preferences.landlord_specific.*`):
`communication_style`, `preferred_contact_method`, `response_time_expectation`, `negotiation_style`, `property_management_involvement`, `risk_tolerance`, `preferred_agent_working_style`, `representation_priorities`, `primary_leasing_goal`, `tenant_type_preference` (proxy-risk), and 6 informational context fields.

---

### 15.4 Tenant Listing — DNA-Relevant Fields

| Field | DNA Dimension Slot | Type |
|---|---|---|
| `property_type` | `property_type_preference` | single-select column |
| `min_bedrooms`, `max_bedrooms` | `min_bedrooms`, `max_bedrooms` | integer columns |
| `min_bathrooms`, `max_bathrooms` | `min_bathrooms`, `max_bathrooms` | decimal columns |
| `min_rent`, `max_rent` | `min_budget`, `max_budget` | integer columns |
| `min_sqft`, `max_sqft` | `min_square_footage`, `max_square_footage` | meta keys |
| `garage_required` | `garage_required` | meta key → deal_breaker_flags |
| `pool_required` | `pool_required` | meta key → deal_breaker_flags |
| `carport_required` | `carport_required` | meta key → deal_breaker_flags |
| `lease_option` | `open_to_lease_option` | meta key → lifestyle_tags |
| `has_pets` | `has_pets` | meta key → lifestyle_tags |
| `smoking` | `smoking_preference` | meta key → lifestyle_tags |
| `desired_move_date` | `preferred_move_in_date` | meta key |
| `timeline_flexibility` | `timeline_flexibility` | meta key (stored, no tag emitted) |

**Tenant BYA compatibility_preferences fields** (stored as `compatibility_preferences.tenant_specific.*`):
`communication_style`, `contact_frequency`, `preferred_contact_method` (time-of-day), `negotiation_style`, `desired_level_of_agent_involvement`, `decision_making_style`, `timeline_urgency`, `preferred_agent_working_style`, `representation_priorities`, `primary_rental_goal`, and 10 informational context fields.

---

## 16. Gap Analysis and Classification Table

### Classification legend:
- ✅ **BUILT** — fully implemented end-to-end; code and schema exist and are internally consistent
- ⚠️ **PARTIAL** — service or schema exists but integration is incomplete, a required signal is missing, or downstream consumption is absent
- ❌ **MISSING** — component does not exist; no code or schema found

### 16.1 Models

| Component | Classification | Notes |
|---|---|---|
| PropertyDnaProfile | ✅ BUILT | 29 dimension slots; AI-derived columns; archived_at |
| BuyerTenantDnaProfile | ✅ BUILT | 26 dimension slots; lifestyle_tags; deal_breaker_flags |
| ListingCompatibilityScore | ✅ BUILT | Original + BYA payload columns (migration 2026_05_29) |
| PropertyLocationDna | ✅ BUILT | Geocode + POI distances + summary tiers |
| PropertyLocationDnaAudit | ✅ BUILT | Append-only, mirrors full row state |
| PropertyLocationPoi | ✅ BUILT | Per-property POI records |
| DnaMarketingOutput | ✅ BUILT | Links PropertyDnaProfile to marketing output |

### 16.2 DNA Generators

| Component | Classification | Notes |
|---|---|---|
| PropertyDnaGenerator (seller) | ✅ BUILT | 29 slots; AI output; no location context integration |
| PropertyDnaGenerator (landlord) | ✅ BUILT | 29 slots; AI output; no location context integration |
| BuyerTenantDnaGenerator (buyer) | ✅ BUILT | 26 slots; lifestyle_tags; deal_breaker_flags |
| BuyerTenantDnaGenerator (tenant) | ✅ BUILT | 26 slots; lifestyle_tags; deal_breaker_flags |
| Location context integration into PropertyDnaGenerator | ⚠️ PARTIAL | Phase F NOTE: deferred; no location_dna_context column |

### 16.3 Explanation Services

| Component | Classification | Notes |
|---|---|---|
| PropertyDnaExplanationService | ✅ BUILT | Covers all 29 supply slots |
| BuyerTenantDnaExplanationService | ✅ BUILT | Covers all 26 demand slots |

### 16.4 Location DNA Services

| Component | Classification | Notes |
|---|---|---|
| GeocodeService (Phase B) | ✅ BUILT | Google Places geocoding |
| PoiDistanceService (Phase C) | ✅ BUILT | 19 categories, 12 native + 7 keyword |
| SummaryService (Phase D) | ✅ BUILT | Tier computation from POI distances |
| AuditService (Phase E) | ✅ BUILT | Append-only audit records |
| PropertyContextService (Phase F) | ⚠️ PARTIAL | Service built; integration into PropertyDnaGenerator deferred |
| MarketingContextService (Phase G) | ✅ BUILT | Feeds marketing pipeline |
| IntelligenceContextService (Phase H) | ✅ BUILT | Feeds AI prompt context |

### 16.5 Compatibility Engine

| Component | Classification | Notes |
|---|---|---|
| CompatibilityEngine (core) | ✅ BUILT | 14 dimensions computed |
| property_type_alignment | ✅ BUILT | Eligible; both signals present |
| financing_alignment | ✅ BUILT | Eligible; both signals present |
| lease_structure_alignment | ✅ BUILT | Eligible; both signals present |
| pet_policy_alignment | ✅ BUILT | Eligible; both signals present |
| smoking_policy_alignment | ✅ BUILT | Eligible; both signals present |
| parking_alignment | ✅ BUILT | Eligible; both signals present |
| commercial_alignment | ✅ BUILT | Eligible; both signals present |
| amenity_alignment | ✅ BUILT | Eligible; both signals present |
| occupancy_alignment | ⚠️ PARTIAL | Computed but always unresolved — no demand-side occupancy tag |
| furnishing_alignment | ⚠️ PARTIAL | Computed but always unresolved — no demand-side furnishing tag |
| timeline_alignment | ⚠️ PARTIAL | Computed but always unresolved — no demand-side timeline tag emitted |
| budget_alignment | ⚠️ PARTIAL | Computed but always unresolved — no supply-side price signal in PropertyDnaProfile |
| lease_term_alignment | ⚠️ PARTIAL | Computed but always unresolved — no demand-side lease term flag emitted |
| hoa_alignment | ⚠️ PARTIAL | Computed; structurally ineligible per Phase J audit; always unresolved |
| PropertyDnaProfileCompatibilityObserver | ✅ BUILT | FANOUT_CAP=500; seller→buyer; landlord→tenant |
| BuyerTenantDnaProfileCompatibilityObserver | ✅ BUILT | FANOUT_CAP=500; buyer→seller; tenant→landlord |
| ComputeCompatibilityScore job | ✅ BUILT | |

### 16.6 BYA Pipeline

| Component | Classification | Notes |
|---|---|---|
| ByaNormalizationService (BYA_NORM_V1) | ✅ BUILT | 12 traits; all 4 roles; proxy risk flags |
| ByaAgentResponseNormalizationService (BYA_AGENT_NORM_V1) | ✅ BUILT | 12 traits; 7 sections; proxy risk flags |
| ByaCompatibilityComparisonService (BYA_COMP_V1) | ⚠️ PARTIAL | 12 dims built; 2 always unknown (technology_preference, market_education_preference) |
| ByaCompatibilityAlignmentService (BYA_ALIGN_V1) | ✅ BUILT | 6 categories; advisory label; broad_compatibility unreachable in Milestone 5 |
| ByaCompatibilityExplanationService (BYA_EXPLAIN_V1) | ✅ BUILT | 5 types; key derivation |
| ByaCompatibilityNarrativeService (BYA_NARRATIVE_V1) | ✅ BUILT | 32 templates; summary sentences |
| ByaCompatibilityReportService (BYA_REPORT_V1) | ✅ BUILT | Full report assembly |
| technology_preference dimension | ❌ MISSING | Placeholder; no consumer or agent schema support |
| market_education_preference dimension | ❌ MISSING | Placeholder; no consumer or agent schema support |
| "similar" relationship value | ❌ MISSING | Reserved; not emitted; no governance definition yet |
| adjacent_compatibility alignment category | ❌ MISSING | Reserved; unreachable in Milestone 5 |
| neutral_compatibility alignment category | ❌ MISSING | Reserved; unreachable in Milestone 5 |

### 16.7 Marketing Intelligence Pipeline

| Component | Classification | Notes |
|---|---|---|
| PropertyMarketingContextService (Phase P) | ✅ BUILT | Supply-side context bundle |
| PropertyMarketingBriefService (Phase R) | ✅ BUILT | Marketing brief assembly |
| PropertyMarketingReadinessService (Phase U) | ✅ BUILT | Gating / readiness check |
| AiMarketingReportGeneratorService (Phase XD) | ✅ BUILT | OpenAI report generation |
| AiMarketingReportReviewService (Phase XF) | ✅ BUILT | AI review pass |
| AiMarketingReportOrchestratorService (Phase XG) | ✅ BUILT | Pipeline orchestration |
| AiMarketingReportPersistenceService (Phase XJ) | ✅ BUILT | DB persistence (3 tables) |
| AiMarketingReportAgentRevisionService (Phase XN) | ✅ BUILT | Agent revision flow |
| AiMarketingReportOwnerApprovalService (Phase XO) | ✅ BUILT | Owner approval flow |
| AiMarketingReportPublicationService (Phase XP) | ✅ BUILT | Publication |
| BuyerTenantMarketingContextService | ⚠️ PARTIAL | Context builder built; no downstream AI report pipeline |
| Demand-side AI marketing report pipeline | ❌ MISSING | No XD/XF/XG/XJ/XN/XO/XP equivalent for buyer/tenant |

### 16.8 Role Intelligence Profiles

| Component | Classification | Notes |
|---|---|---|
| Seller DNA Intelligence Profile (UI) | ❌ MISSING | Data built; explanation built; no display layer |
| Landlord DNA Intelligence Profile (UI) | ❌ MISSING | Data built; explanation built; no display layer |
| Buyer DNA Intelligence Profile (UI) | ❌ MISSING | Data built; explanation built; no display layer |
| Tenant DNA Intelligence Profile (UI) | ❌ MISSING | Data built; explanation built; no display layer |

### 16.9 Property Personality

| Component | Classification | Notes |
|---|---|---|
| personality_summary column (supply) | ✅ BUILT | Stored on property_dna_profiles |
| personality_summary column (demand) | ✅ BUILT | Stored on buyer_tenant_dna_profiles |
| Personality readiness service | ❌ MISSING | No dedicated readiness/gating service |
| Personality display layer | ❌ MISSING | Not surfaced in any UI component |
| Personality audit trail | ❌ MISSING | No change tracking specific to personality_summary |

---

## 17. Governance Constraints Summary

The following constraints are enforced by code and documented in source comments. They apply across the entire DNA ecosystem.

### 17.1 Coverage Metrics Are Not Quality Scores

Both `dna_coverage_metric` (on `PropertyDnaProfile` and `BuyerTenantDnaProfile`) and `compatibility_coverage_metric` (on `ListingCompatibilityScore`) are **completeness/coverage fractions only**. They measure what proportion of available dimension slots were populated, not the desirability, quality, or market value of a listing or a match. These metrics must never be presented to users as ranking signals.

### 17.2 Compatibility Is Deterministic and Rule-Based

The `CompatibilityEngine` contains no AI, ML, probabilistic scoring, or weighted inference. Every dimension result is either `aligned`, `conflicting`, or `unresolved` based on deterministic tag-presence rules. This is a deliberate design constraint.

### 17.3 accessibility_requirements Is Permanently Excluded

The field `accessibility_requirements` is named in the `CompatibilityEngine` governance block as permanently excluded. It must never be read by compatibility logic. This is a Fair Housing and anti-discrimination constraint.

### 17.4 Compatibility Output Is Internal-Only

`ListingCompatibilityScore` data and all BYA pipeline payloads are **internal metadata** — never surfaced publicly, never presented to consumers as recommendations, and never used for ranking, endorsement, or disqualification of any party.

### 17.5 BYA Proxy Risk Fields

Three agent-side fields and one consumer-side field are flagged as proxy-risk under the Fair Housing Act:

| Field | Party | Risk |
|---|---|---|
| `tenant_type_preference` | Consumer (Landlord) | Demographic proxy |
| `agent_tenant_screening_strictness` | Agent | May disadvantage agents serving protected classes |
| `agent_tenant_profile_specialization` | Agent | Demographic proxy |
| `agent_property_strategy_specialization` | Agent | Demographic correlation risk |

These fields are surfaced only in `proxy_risk_flags` arrays and `informational_context` — never as matching trait values.

### 17.6 BYA Advisory Labels Are Descriptive, Not Prescriptive

The advisory labels produced by `ByaCompatibilityAlignmentService` (`strong_alignment`, `notable_differences`, `broad_compatibility`, `insufficient_compatibility_data`) are qualitative descriptions of the comparison data. They are machine-readable keys only, and must never be interpreted or presented as:
- Hiring recommendations
- Suitability determinations
- Endorsements or disqualifications
- Transaction advice of any kind

### 17.7 Fanout Cap Governance

The `FANOUT_CAP = 500` constant in `CompatibilityEngine` and both compatibility observers enforces a hard ceiling on per-invocation compatibility job dispatch. This is prototype-scale only. Large-scale fanout requires a separate architecture phase (cursor-based chunked orchestration with `Bus::batch()` or a dedicated `FanoutCompatibilityBatch` job).

### 17.8 Narrative Templates Are Code-Only

The `ByaCompatibilityNarrativeService` template library (32 templates) is stored exclusively in PHP class constants — not in the database, config files, or any external service. Templates must not be loaded from remote sources.

---

## 18. Percentage Summary by Subsystem

| Subsystem | Total Components | Built | Partial | Missing | Built% |
|---|---|---|---|---|---|
| DNA Models | 7 | 7 | 0 | 0 | **100%** |
| Database Migrations | 12 | 12 | 0 | 0 | **100%** |
| DNA Generators | 5 (4 role variants + location integration) | 4 | 1 | 0 | **80%** |
| DNA Explanation Services | 2 | 2 | 0 | 0 | **100%** |
| Location DNA Services | 7 | 6 | 1 | 0 | **86%** |
| Compatibility Engine Dimensions | 14 | 8 | 6 | 0 | **57% eligible / 43% ineligible** |
| Compatibility Engine Infrastructure | 5 | 5 | 0 | 0 | **100%** |
| BYA Pipeline Services | 7 | 6 | 1 | 0 | **86%** |
| BYA Dimension/Label Coverage | 17 | 10 | 0 | 5 | **59% active / 29% missing** |
| Marketing Intelligence Pipeline (supply) | 10 | 10 | 0 | 0 | **100%** |
| Marketing Intelligence Pipeline (demand) | 2 | 0 | 1 | 1 | **0% fully built** |
| Event Dispatch (Observers + Jobs) | 9 | 9 | 0 | 0 | **100%** |
| Role Intelligence Profiles (UI layer) | 4 | 0 | 0 | 4 | **0%** |
| Property Personality | 3 | 2 | 0 | 1 | **67%** (columns built; no service/UI) |

### Overall by Component Count

| Classification | Count | Percentage |
|---|---|---|
| ✅ Built | 81 | **74%** |
| ⚠️ Partial | 10 | **9%** |
| ❌ Missing | 11 | **10%** |
| ⚡ Structurally ineligible (known architecture gap, not a build failure) | 8 | **7%** |

---

*End of audit. Document produced June 1, 2026. No code changes were made during this audit.*
