# Stellar — Final Launch Architecture & Implementation Plan

**Date:** June 16, 2026
**Status:** AUTHORITATIVE — supersedes any conflicting conclusions in individual Stellar audit documents
**Source Audits Reviewed:**
- `docs/audits/STELLAR_BRIDGE_FIELD_AUDIT.md`
- `docs/audits/STELLAR_MATCHING_READINESS_AUDIT.md`
- `docs/audits/STELLAR_NATIVE_COLUMN_EXPANSION_STRATEGY.md`
- `docs/audits/STELLAR_PHASE1_NATIVE_COLUMN_MIGRATION_PLAN.md`
- `docs/audits/STELLAR_BUYER_MATCHING_ENGINE_ARCHITECTURE.md`
- `docs/audits/STELLAR_TENANT_MATCHING_ENGINE_ARCHITECTURE.md`
- `docs/audits/STELLAR_ALERT_SYSTEM_ARCHITECTURE.md`
- `docs/audits/STELLAR_RECOMMENDATION_DISCOVERY_ENGINE_ARCHITECTURE.md`
- `docs/audits/STELLAR_PROPERTY_INTELLIGENCE_ARCHITECTURE.md`
- `docs/audits/BIDYOURAGENT_FINAL_LAUNCH_ARCHITECTURE_AND_IMPLEMENTATION_PLAN.md` (platform context)

**Conflict resolution note:** Where individual audit documents suggest multiple options, leave decisions open, or reach different conclusions on the same question, this document records the single final decision and closes the question. One confirmed conflict between source audits is resolved explicitly in Section 3 (Conflict Resolution).

---

> **⚠ Phase 0 Validation Addendum — 2026-06-16**
> Phase 0 live-data validation (`docs/audits/STELLAR_PHASE0_DATA_VALIDATION_REPORT.md`) was executed against 1,000 live Stellar records after this document was written. **Phase 0 validation supersedes the original 20-column Phase 1 list when live data proves a field is under-populated.** After Phase 0, Phase 1 is adjusted to **19 columns**: `furnished` is removed and deferred to Phase 2R (rental feed gate; 35% population rate in the for-sale feed, below the 50% Block threshold). All implementation details are in `STELLAR_PHASE1_NATIVE_COLUMN_MIGRATION_PLAN.md` Section 9.

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Final Architecture Decisions](#2-final-architecture-decisions)
3. [Conflict Resolution](#3-conflict-resolution)
4. [Launch Scope](#4-launch-scope)
5. [Dependency Map](#5-dependency-map)
6. [Critical Path Analysis](#6-critical-path-analysis)
7. [Blocker Classification](#7-blocker-classification)
8. [Launch Decision Matrix & Final Implementation Roadmap](#8-launch-decision-matrix--final-implementation-roadmap)
9. [Proof Section](#9-proof-section)

---

## 1. Executive Summary

### What Stellar Is

Stellar is the MLS intelligence layer of the BidYourAgent platform. It transforms raw Bridge API data from the Stellar MLS feed into scored buyer/tenant matches, proactive property alerts, AI-enriched property context for Ask AI, and — eventually — a full discovery and recommendation engine. Stellar is not a separate product; it is the data and intelligence infrastructure that makes the core BidYourAgent auction platform aware of the live real estate market.

### The Stellar Vision

The completed Stellar system will:

1. **Ingest** Stellar MLS listing data continuously via the Bridge Data Output API, storing each record in the `bridge_properties` table as a dual `raw_json` + native-column structure.
2. **Score** every active listing against every buyer's and tenant's saved criteria, producing ranked match results with per-dimension explanations.
3. **Alert** buyers, tenants, and agents when meaningful events occur — new matching inventory, price changes, status transitions, availability openings.
4. **Answer** natural-language property questions via Ask AI, drawing on the full `raw_json` payload for per-listing context.
5. **Recommend** adjacent opportunities — listings that fall outside strict criteria but offer compelling tradeoffs — using a discovery engine that operates on behavioral signals and listing similarity.
6. **Enrich** each property with intelligence signals beyond MLS attributes — ownership type, equity position, seller propensity, flood risk tier — sourced from public county records and external APIs.

### Phase 1 Launch Scope (This Document's Focus)

Phase 1 is the minimum viable Stellar layer required to make buyer matching and alert delivery production-ready. It is scoped to:

- 20 native column promotions on `bridge_properties`
- Buyer matching engine (basic — hard filters + soft scoring across 7 categories)
- `new_listing` and `new_match` alert types (email delivery)
- Ask AI context for Stellar listings (per-record `raw_json` extraction)

Everything else — tenant matching, price/status change alerts, recommendation engine, property intelligence layer — is explicitly deferred per the rationale in Section 4.

### Strategic Objectives

| Objective | What It Requires |
|---|---|
| Buyer matching | Phase 1 column promotions + buyer matching engine |
| Alert delivery | Buyer matching engine + alert subscription infrastructure + `new_listing`/`new_match` triggers |
| Ask AI for Stellar listings | `raw_json` extraction per listing — no column promotions required |
| Property intelligence | External API access agreements (Bridge public records, county tax, FEMA) — post-launch |
| Tenant matching | Stellar For Lease feed confirmation — gated, not Phase 1 |
| Recommendation engine | Buyer matching engine + Phase 2 column promotions — post-launch |

---

## 2. Final Architecture Decisions

This section records a single, final decision for each major Stellar system. There are no open options, no "either/or" alternatives, and no deferred decisions in this section. Each decision is authoritative as of this document's date.

---

### Decision 1: MLS Data Layer

**Final decision:** Bridge API → `bridge_properties` upsert pipeline with `raw_json` + native-column dual strategy.

Every Stellar MLS record fetched from the Bridge Data Output API is stored in the `bridge_properties` table via `BridgeProperty::updateOrCreate()` keyed on `listing_key`. Each record stores the full API response payload in the `raw_json` column and simultaneously populates a set of promoted native columns for indexed query access.

The dual strategy is not optional. `raw_json` alone cannot support the matching engine (JSON extraction is O(n) across the table, not indexable). Native columns alone cannot preserve the full Stellar field inventory — there are 553 total Stellar fields; only a subset will ever be promoted to native columns. Both columns must always be populated on every upsert.

**`raw_json` role:** Full field preservation, Ask AI context loading (O(1) per listing), display-layer field access for non-indexed fields.

**Native column role:** Indexed WHERE clauses, range scans, ORDER BY operations, and cross-row comparison jobs (alert detection, match scoring).

**No alternatives accepted:** Neither a pure-JSON strategy nor a full normalized schema is acceptable. The dual strategy is the permanent architecture for `bridge_properties`.

---

### Decision 2: Native Column Strategy — Phase 1 (20 Columns)

**Final decision:** Promote exactly 20 fields to native columns in Phase 1. The authoritative list is `STELLAR_PHASE1_NATIVE_COLUMN_MIGRATION_PLAN.md` §2. No substitutions, no additions, no removals from this list without a new architecture decision record.

| # | Column Name | Stellar Source Field | SQL Type | Index |
|---|---|---|---|---|
| 1 | `latitude` | `Latitude` | `DECIMAL(10,7)` | Composite B-tree with `longitude` |
| 2 | `longitude` | `Longitude` | `DECIMAL(10,7)` | Composite B-tree with `latitude` |
| 3 | `county_or_parish` | `CountyOrParish` | `VARCHAR(100)` | B-tree |
| 4 | `property_sub_type` | `PropertySubType` | `VARCHAR(100)` | B-tree |
| 5 | `senior_community_yn` | `SeniorCommunityYN` | `BOOLEAN` | Partial (`WHERE senior_community_yn = TRUE`) |
| 6 | `mls_status` | `MlsStatus` | `VARCHAR(50)` | B-tree |
| 7 | `year_built` | `YearBuilt` | `SMALLINT` | None |
| 8 | `association_fee` | `AssociationFee` | `DECIMAL(10,2)` | None |
| 9 | `pets_allowed` | `PetsAllowed` | `VARCHAR(50)` | None |
| 10 | `furnished` | `Furnished` | `VARCHAR(50)` | None |
| 11 | `garage_yn` | `GarageYN` | `BOOLEAN` | Partial (`WHERE garage_yn = TRUE`) |
| 12 | `pool_private_yn` | `PoolPrivateYN` | `BOOLEAN` | Partial (`WHERE pool_private_yn = TRUE`) |
| 13 | `waterfront_yn` | `WaterfrontYN` | `BOOLEAN` | Partial (`WHERE waterfront_yn = TRUE`) |
| 14 | `tax_annual_amount` | `TaxAnnualAmount` | `DECIMAL(10,2)` | None |
| 15 | `lot_size_sqft` | `LotSizeSquareFeet` | `INTEGER` | None |
| 16 | `association_yn` | `AssociationYN` | `BOOLEAN` | Partial (`WHERE association_yn = TRUE`) |
| 17 | `new_construction_yn` | `NewConstructionYN` | `BOOLEAN` | Partial (`WHERE new_construction_yn = TRUE`) |
| 18 | `view_yn` | `ViewYN` | `BOOLEAN` | Partial (`WHERE view_yn = TRUE`) |
| 19 | `water_view_yn` | `STELLAR_WaterViewYN` | `BOOLEAN` | Partial (`WHERE water_view_yn = TRUE`) |
| 20 | `cdd_yn` | `STELLAR_CDDYN` | `BOOLEAN` | Partial (`WHERE cdd_yn = TRUE`) |

All 20 new columns are nullable. Boolean columns use partial indexes (not standard B-tree) to handle the low-cardinality indexing trade-off at scale. The composite B-tree on `(latitude, longitude)` enables Haversine bounding-box radius search without PostGIS.

**`PetsAllowed` normalization rule:** Source is an array in the Bridge API. The `pets_allowed` column stores only the first array element as a canonical string (`["Yes"]` → `"Yes"`, `["Dogs", "Cats"]` → `"Dogs"`). Tenant matching queries must use `LIKE '%Yes%'` or equality checks, not array containment. A `pets_allowed_raw` TEXT column may be added in Phase 2 if full-array matching is required.

**`lot_size_sqft` null guard:** String `"0"` is falsy in PHP. Extraction must use `!== null` guard, not a truthy check, to avoid silently skipping legitimate zero values.

**Phase 2 column additions (not Phase 1):** `subdivision_name`, `mls_area_major`, `bathrooms_full`, `garage_spaces`, `original_list_price`, `previous_list_price`, `price_change_timestamp`, `status_change_timestamp`. These unlock Phase 2 scoring precision and price/status change alerts. They are out of scope for Phase 1.

---

### Decision 3: Buyer Matching Architecture

**Final decision:** 100-point scoring model across 7 categories with hard filters applied before scoring. Architecture is as specified in `STELLAR_BUYER_MATCHING_ENGINE_ARCHITECTURE.md`.

**Hard filters (eliminate before scoring):**

| Filter | Field | Logic |
|---|---|---|
| Active listing only | `standard_status` | Must equal `'Active'` |
| Property type match | `property_type` | Exact match to buyer's selected type |
| Price ceiling | `list_price` | Must be ≤ buyer's maximum budget |
| Minimum bedrooms | `bedrooms_total` | Must be ≥ buyer's minimum if specified |
| Minimum bathrooms | `bathrooms_total_integer` | Must be ≥ buyer's minimum if specified |
| Senior community gate | `senior_community_yn` | If buyer is NOT 55+ eligible, exclude all listings where `senior_community_yn = true` — Fair Housing compliance, non-negotiable |

**Scoring model (100 points total):**

| Category | Weight | Key Fields |
|---|---|---|
| Location | 30 | `latitude`, `longitude`, `city`, `postal_code`, `county_or_parish` |
| Price | 25 | `list_price`, `original_list_price` (Phase 2) |
| Size | 15 | `living_area`, `lot_size_sqft`, `year_built` |
| Property Type | 10 | `property_type`, `property_sub_type` |
| Amenities | 10 | `pool_private_yn`, `garage_yn`, `waterfront_yn`, `water_view_yn`, `view_yn` |
| Financial / Fees | 5 | `association_fee`, `association_yn`, `cdd_yn`, `tax_annual_amount` |
| Lifestyle / Context | 5 | `CommunityFeatures`, `AssociationAmenities`, `GreenEnergyEfficient`, `new_construction_yn`, `pets_allowed` (Tier 2 `raw_json` fields) |

**CDD flag:** The `cdd_yn` field is a **caution flag only** — it is never used as a scoring penalty. CDDs are legally valid community structures; penalizing them in scores would embed a discriminatory bias. It appears in the match explanation as a warning to the buyer to factor in the CDD assessment when evaluating true ownership cost.

**Compliance boundary:** All Tier 6 fields (23 explicitly compliance-restricted fields plus ~200 agent/brokerage/admin fields) are hard-excluded from every part of the matching engine — scores, explanations, caution flags, column promotions. This boundary is enforced at the data layer.

**Phase 1 coverage:** With the 20 Phase 1 column promotions, buyer matching readiness rises from 21% to approximately 60% of Tier 1 field coverage — sufficient to launch a basic matching engine covering all critical dimensions.

---

### Decision 4: Tenant Matching Architecture

**Final decision:** 100-point scoring model across 8 buckets, architecture as specified in `STELLAR_TENANT_MATCHING_ENGINE_ARCHITECTURE.md`. **Implementation is gated on rental feed confirmation. No phase of tenant matching begins before this gate is cleared.**

**Implementation gate (explicit):** The Stellar For Lease feed must be confirmed to return populated rental records with non-null values for `LeaseConsideredYN`, `LeaseTerm`, `Furnished`, `PetsAllowed`, `LeaseAmountFrequency`, and `AvailabilityDate` before any implementation begins. "Confirmed" means: at least one batch of For Lease records has been fetched, spot-checked, and the Phase 2R rental column promotions have been applied and populated. All 6 primary rental-specific Tier 1 fields are 0/25 populated in the current for-sale sample — tenant matching cannot produce usable results from the current feed.

**Scoring model (100 points total, after gate is cleared):**

| Bucket | Weight | Key Fields |
|---|---|---|
| Location | 25 | `latitude`, `longitude`, `city`, `postal_code`, `county_or_parish` |
| Rent | 20 | `monthly_rent` (Phase 2R), `list_price` (fallback) |
| Availability | 15 | `availability_date` (Phase 2R) |
| Beds / Baths / Size | 15 | `bedrooms_total`, `bathrooms_total_integer`, `living_area` |
| Pets / Furnished | 10 | `pets_allowed`, `furnished` |
| Lease Terms | 5 | `lease_term`, `minimum_lease` |
| Amenities | 5 | `pool_private_yn`, `waterfront_yn`, `garage_yn`, `view_yn`, community features |
| Fees / Utilities | 5 | `association_fee`, `association_yn`, `RentIncludes`, `TenantPays` |

**Rental-specific column promotions (separate migration, gated on For Lease feed):** `lease_considered_yn`, `for_lease_yn`, `long_term_yn`, `lease_amount_frequency`, `monthly_rent`, `lease_term`, `minimum_lease`, `availability_date`. These 8 columns must not be promoted before the rental feed is active — doing so creates empty nullable columns for all existing for-sale records.

---

### Decision 5: Recommendation Engine Architecture

**Final decision:** 9 recommendation types, 100-point composite score across 6 sub-scores, as specified in `STELLAR_RECOMMENDATION_DISCOVERY_ENGINE_ARCHITECTURE.md`. The recommendation engine is downstream of the matching engine — it operates on the candidate set adjacent to stated criteria and surfaces opportunities the matching engine's hard filters would exclude.

**9 recommendation types:**

| # | Type | Label |
|---|---|---|
| 1 | Similar Homes | "Similar to homes you've been looking at" |
| 2 | Nearby Alternatives | "Nearby alternative — [X] miles from your target area" |
| 3 | Budget-Stretch Options | "Just above your budget — worth considering" |
| 4 | Better-Value Options | "Better value — [X]% below your budget with comparable features" |
| 5 | Same-Neighborhood Alternatives | "Also in [subdivision/neighborhood/ZIP]" |
| 6 | Similar Amenities | "Matches your lifestyle preferences — [amenity list]" |
| 7 | New Construction Alternatives | "New construction available nearby" |
| 8 | Rental Alternatives | "Renting is also an option in this area" — gated on For Lease feed |
| 9 | Investment Alternatives | "Investment alternative with rental income potential" |

**Composite recommendation score (100 points):**

| Sub-score | Weight |
|---|---|
| Similarity Score | 30 |
| Location Adjacency Score | 25 |
| Value Score | 20 |
| Amenity Overlap Score | 15 |
| Tradeoff Score | 7 |
| Freshness Score | 3 |

**Implementation gate:** The recommendation engine requires all Phase 1 native column promotions plus Phase 2 column promotions (`subdivision_name`, `mls_area_major`, `garage_spaces`). It is post-launch and must not be built before the buyer matching engine is stable in production.

---

### Decision 6: Alert System Architecture

**Final decision:** 10 alert types delivered in 3 phases, as specified in `STELLAR_ALERT_SYSTEM_ARCHITECTURE.md`. Alert delivery is downstream of the matching engine — the match engine determines who cares about a listing; the alert system determines when they are notified and how.

**10 alert types by phase:**

| Phase | Alert Types | Prerequisites |
|---|---|---|
| Phase 1 | `new_listing`, `new_match` | Phase 1 native column promotions; `mls_alerts`, `alert_subscriptions`, `alert_dedup_log` tables created |
| Phase 2 | `price_reduction`, `price_increase`, `status_change`, `back_on_market`, `coming_soon` | Phase 2 column promotions: `previous_list_price`, `price_change_timestamp`, `status_change_timestamp`, `mls_status` (already Phase 1 — confirm presence) |
| Phase 3 | `rental_available`, `open_house_scheduled`, `photos_updated` | Stellar For Lease feed active; `availability_date` and `for_lease_yn` promoted to native columns; in-app notification panel UI built |

**Detection timing patterns:**

- **`new_listing`:** On-import detection. If the `bridge_properties` upsert creates a new row and `standard_status = 'Active'`, queue the event. No scheduled job needed.
- **`new_match`:** End-of-cycle import detection. After each import batch, run the match query against newly imported/modified listings for all active criteria records. Score above threshold → queue alert.
- **`price_reduction`/`price_increase`/`status_change`:** Scheduled comparison job. Requires `previous_list_price`, `price_change_timestamp`, `status_change_timestamp` as native columns. Raw JSON extraction is not acceptable for these jobs — they query across the full table.
- **`back_on_market`/`coming_soon`/`photos_updated`/`open_house_scheduled`:** On-import per-record JSON extraction (O(1)). No native column promotion required.
- **`rental_available`:** Rental-feed-gated. Transitions from import-time to a scheduled availability-date job once the For Lease feed is active and record volume warrants it.

**Compliance gates (non-negotiable):** IDX participation gate (`IDXParticipationYN` must be `true` before any alert fires for a listing); Fair Housing senior community gate (`SeniorCommunityYN = true` listings routed only to eligible recipients); Tier 6 field suppression (no agent PII, no lockbox data in any alert payload); CAN-SPAM unsubscribe honored immediately at database level.

---

### Decision 7: Property Intelligence Layer

**Final decision:** 12 intelligence signals derived from 5 data sources, as specified in `STELLAR_PROPERTY_INTELLIGENCE_ARCHITECTURE.md`. This layer is post-launch. No implementation begins before Phase 1 matching and alert delivery are production-stable and external API access agreements are in place.

**5 data sources:**
1. Stellar MLS Fields (Bridge API) — already available
2. Bridge Interactive Public Records / Property Records API — requires agreement confirmation
3. County Tax Records — requires batch import integration per county
4. Deed and Sale History — via Bridge public records or direct county recorder APIs
5. FEMA National Flood Hazard Layer (NFHL) — free REST API; requires integration

**12 intelligence signals:**

| Signal | Type | Agent-Facing / Buyer-Facing |
|---|---|---|
| Ownership Type | Enum (owner_occupant / individual_investor / llc / corporate / trust / government / unknown) | Both |
| Absentee Owner Flag | Boolean | Agent-facing (off-market outreach targeting) |
| Years Owned | Float (years) | Both |
| Last Sale Date and Price | Date + integer | Both (CMA context) |
| Estimated Equity | Dollar + LTV% (estimated, labeled as such) | Agent-facing primarily |
| Annual Tax Burden | Dollar (with CDD breakdown) | Both |
| Investor-Owned Likelihood Score | 0–100 | Agent-facing |
| Seller Propensity Score | 0–100 | Agent-facing (off-market outreach) |
| Distressed Property Indicators | Boolean flags | Agent-facing |
| Flood Zone Risk Tier | Translated from FEMA code (Minimal / Moderate / High / Very High) | Both — required before flood zone can drive matching |
| School District Quality Signal | Derived (requires geocode-to-district API) | Buyer-facing |
| Estimated Investment Yield | Computed from public records income data | Investor buyers |

**External API blockers:** All 12 signals except Flood Zone Risk Tier and School District require Bridge public records API access or county-level integrations. These agreements must be in place before implementation. The platform must never present equity estimates as verified figures — all derived financial signals carry an "estimated" label in all UI.

---

### Decision 8: Ask AI Integration for Stellar Listings

**Final decision:** Per-record `raw_json` extraction is the correct and permanent approach for Ask AI context loading. No column promotion is required for Ask AI alone.

Ask AI queries individual listings by `listing_key` (already a unique indexed column). Per-record `raw_json` extraction is O(1) — acceptable for all Ask AI fields regardless of whether those fields are native columns. The performance boundary for Ask AI is not "native vs. JSON" but "is the field populated reliably enough to be useful in context."

**Fields passed as embedded AI context (from `raw_json` wholesale):** `PublicRemarks`, `Appliances`, `InteriorFeatures`, `ExteriorFeatures`, `CommunityFeatures`, `AssociationAmenities`, `Heating`, `Cooling`, `Flooring`, `ElementarySchool`, `HighSchool`, `MiddleOrJuniorSchool`, `LotSizeDimensions`, `Directions`, `ListingTerms`.

**Structured fields used in Ask AI answers (preferably native columns once promoted, acceptable as `raw_json` until then):** `YearBuilt`, `AssociationFee`, `TaxAnnualAmount`, `PropertySubType`, `GarageYN`, `GarageSpaces`, `PoolPrivateYN`, `WaterfrontYN`, `STELLAR_WaterViewYN`, `SeniorCommunityYN`, `NewConstructionYN`, `LotSizeSquareFeet`, `STELLAR_CDDYN`, `PetsAllowed`, `Furnished`.

**Compliance enforcement:** The 23 Tier 6 compliance-restricted fields (`ListAgentEmail`, `ListAgentPreferredPhone`, `ListOfficePhone`, agent license numbers, lockbox fields, etc.) must never appear in any Ask AI context payload, prompt, or response — regardless of how the question is phrased. This is enforced at the context builder layer, not just at the display layer.

---

### Decision 9: Location DNA Integration

**Final decision:** Location DNA neighborhood signals are used by the recommendation engine in Phase 3. Location DNA is not used by the Phase 1 buyer matching engine or alert system.

Location DNA profiles, managed by `PropertyIntelligenceProfileService` (`App\Services\Dna`), provide neighborhood character tags (walkable, family-oriented, waterfront community, golf community, employment-center proximity) that extend beyond MLS field values. These signals are used in:

- **Recommendation engine Phase 3:** Location adjacency scoring sub-dimension and natural-language recommendation explanation generation (e.g., "Lakewood Ranch has a similar family-oriented, golf-community character to your preferred area").
- **Ask AI Phase 3:** When generating AI-powered explanation copy for matched or recommended listings.

Location DNA does **not** feed into Phase 1 buyer matching hard filters or the 7-category scoring model. Its integration point is the recommendation engine's location adjacency score, which is a post-launch deliverable.

---

## 3. Conflict Resolution

### Confirmed Conflict: Phase 1 Native Column Count

**The conflict:**

`STELLAR_MATCHING_READINESS_AUDIT.md` §1 (Top-Level Recommendations, item 1) states:

> "Promote 12 high-frequency Tier 1 fields to native columns before building the matching engine" — listing `latitude`, `longitude`, `county_or_parish`, `lot_size_square_feet`, `garage_yn`, `pool_private_yn`, `waterfront_yn`, `year_built`, `pets_allowed`, `association_fee`, `new_construction_yn`, and `mls_status`.

`STELLAR_NATIVE_COLUMN_EXPANSION_STRATEGY.md` §8 and `STELLAR_PHASE1_NATIVE_COLUMN_MIGRATION_PLAN.md` §2 specify 20 fields with exact DDL types, indexing strategy, backfill command specification, import mapping code, and a 7-phase rollout sequence.

**Why the conflict exists:** The Matching Readiness Audit was written as an analytical summary — its top-level recommendation lists the most critical 12 fields to resolve the most urgent gaps. The Native Column Expansion Strategy and Phase 1 Migration Plan are implementation-ready documents that went further, adding 8 additional fields (`property_sub_type`, `senior_community_yn`, `furnished`, `tax_annual_amount`, `association_yn`, `view_yn`, `water_view_yn`, `cdd_yn`) that were needed to cover the full buyer and tenant matching scoring model without leaving holes in the Phase 1 result set.

**Final ruling: 20 fields.**

The migration plan is the more detailed, implementation-ready document and supersedes the audit summary on this specific count. The 12-field list is a subset of the 20-field list — the 8 additional fields expand coverage without conflicting with the audit's intent. Any developer who reads only the audit summary's top-level recommendation must be aware that the authoritative implementation list is 20 fields as specified in `STELLAR_PHASE1_NATIVE_COLUMN_MIGRATION_PLAN.md` §2.

---

### Additional Conflicts Scanned and Resolved

The nine source audits were cross-checked for additional conflicts. The following additional discrepancies were found and resolved:

**Discrepancy A: `StandardStatus` vs. `MlsStatus` as the canonical status field**

`STELLAR_MATCHING_READINESS_AUDIT.md` §1 Data Quality Concerns states: "For matching and alerts, always prefer `StandardStatus`." However, `STELLAR_NATIVE_COLUMN_EXPANSION_STRATEGY.md` §5 (Alert System Requirements) classifies `mls_status` as a required native column for status-change alert diffing, and `STELLAR_BUYER_MATCHING_ENGINE_ARCHITECTURE.md` §3.2 uses `StandardStatus` as the active-listing hard filter.

**Resolution:** There is no true conflict — both fields serve different roles and both are required. **`StandardStatus` (native column `standard_status`) is the RESO-standard canonical status used for all buyer-facing and tenant-facing hard filters and queries.** `MlsStatus` (native column `mls_status`) carries board-specific vocabulary needed for alert trigger diffing and rental feed separation. Both columns must be present. `standard_status` gates all buyer and tenant matching queries. `mls_status` is used only by the alert comparison job for status diffing.

**Discrepancy B: `PetsAllowed` scope in buyer vs. tenant matching**

`STELLAR_MATCHING_READINESS_AUDIT.md` classifies `PetsAllowed` as Tier 1 Core Matching. `STELLAR_BUYER_MATCHING_ENGINE_ARCHITECTURE.md` §2.14 notes that for buyer matching, `PetsAllowed` is relevant only when it reflects HOA or community-level restrictions — detailed rental pet policy dimensions (deposits, weight limits, fees) belong exclusively in tenant matching.

**Resolution:** No conflict on the native column decision — `pets_allowed` is promoted in Phase 1 regardless of role context. The scope boundary is a scoring rule, not a schema rule: in buyer matching, `pets_allowed` contributes at most 1 point to the Lifestyle/Context category and only when the buyer has indicated a need for a pet-friendly community. In tenant matching, `pets_allowed` is a hard filter gate. Both uses of the same column are valid and co-exist.

**Discrepancy C: `LotSizeAcres` vs. `LotSizeSquareFeet` as the canonical lot size field**

Both fields appear as Tier 1 Core Matching in the readiness audit. The expansion strategy clarifies in §3 that `lot_size_sqft` (from `LotSizeSquareFeet`) is the canonical match dimension — most precise, already in square feet — and `LotSizeAcres` can remain in `raw_json` as a display convenience.

**Resolution:** `lot_size_sqft` is the canonical native column. `LotSizeAcres` remains in `raw_json` for display purposes. No promotion needed for `LotSizeAcres`. This aligns all three source documents once the expansion strategy's clarification is applied.

**Discrepancy D: `AssociationFeeFrequency` role in matching**

The field audit marks `AssociationFeeFrequency` as 23/25 populated and the matching readiness audit classifies it Tier 3 (Ask AI context), while the expansion strategy says `AssociationFee` is the native column to promote (not the frequency qualifier).

**Resolution:** `association_fee` is promoted as a native column. `AssociationFeeFrequency` remains in `raw_json` as an Ask AI context field only — it is a text qualifier ("Monthly", "Annual") and does not need to be indexed for matching. If a buyer specifies an HOA fee tolerance, the matching engine assumes `AssociationFee` is a monthly value (the most common case for the Stellar sample). The `AssociationFeeFrequency` raw value is passed to Ask AI for answering HOA-related questions.

---

## 4. Launch Scope

### Phase 1 — Included

| Item | Notes |
|---|---|
| 20 native column promotions on `bridge_properties` | Full list in Section 2, Decision 2 |
| `bridge:backfill-native-columns` Artisan command | Idempotent backfill of existing rows; batch size 1,000; `--force` flag for re-run |
| `ImportBridgeProperties.php` import mapping update | 20 new field mappings added to `updateOrCreate()` call |
| `BridgeProperty` model `$fillable` and `$casts` updates | All 20 new columns added |
| Buyer matching engine (basic) | Hard filters + 7-category 100-point score; Phase 1 column coverage |
| `new_listing` alert type | On-import detection; email delivery |
| `new_match` alert type | End-of-cycle match query; email delivery |
| Alert infrastructure tables | `mls_alerts`, `alert_subscriptions`, `alert_dedup_log` |
| Ask AI context for Stellar listings | Per-record `raw_json` extraction; no column promotions required |
| Compliance gates | IDX participation, senior community, Tier 6 suppression, CAN-SPAM |

### Gated — Not Phase 1

| Item | Gate Condition |
|---|---|
| All tenant matching | Stellar For Lease feed confirmed active; Phase 2R column promotions applied |
| `price_reduction` alert | Phase 2 column promotions: `previous_list_price`, `price_change_timestamp` |
| `price_increase` alert | Same as price_reduction |
| `status_change` alert | Phase 2 column promotions: `status_change_timestamp` (plus `mls_status` already Phase 1) |
| `back_on_market` alert | Requires status-change comparison job (Phase 2); `back_on_market` detection also available at import time as fallback |
| `coming_soon` alert | Phase 2 alert infrastructure (in-app panel) |
| Recommendation engine | Buyer matching engine stable in production; Phase 2 column promotions |
| `rental_available` alert | Stellar For Lease feed active; `availability_date` native column |
| `open_house_scheduled` alert | Phase 3 (in-app notification panel UI required) |
| `photos_updated` alert | Phase 3 (in-app notification panel UI required) |
| Tenant recommendation types | For Lease feed active; tenant matching engine operational |

### Post-Launch — Explicit Deferrals

| Item | Reason |
|---|---|
| Full alert suite (all 10 types) | Phase 2 and Phase 3 prerequisites; not critical path for launch |
| AI-generated match/recommendation explanations | Requires recommendation engine + Phase 2 matching stability |
| Property intelligence signals | External API access agreements required (Bridge public records, county tax, FEMA) |
| School district quality signal | Geocode-to-district API integration required |
| Walk Score / Transit Score | Third-party Walk Score API integration required; not a Stellar field |
| FEMA flood zone risk tier translation | FEMA NFHL API integration required; raw `STELLAR_FloodZoneCode` alone is insufficient |
| Seller propensity and investor-owned likelihood scores | Derived signals requiring county deed + tax record enrichment |
| Saved-search user class (Recipient Class 4) | Future `saved_searches` table and anonymous session handling required |
| Agent listing claims (`agent_listing_claims` table) | Required for Recipient Class 3 (agent) alert routing |

---

## 5. Dependency Map

The following graph defines what must be built first, what can run in parallel, and what depends on other systems. Arrows indicate dependency direction (A → B means A must exist before B can be built).

```
[Current State: 13 native columns, raw_json only for 57 Tier 1 fields]
                            │
                            ▼
          ┌─────────────────────────────────────┐
          │    Phase 1 Column Migration          │  CRITICAL PATH ENTRY
          │    (20 new native columns)           │
          └──────────────────┬──────────────────┘
                             │
              ┌──────────────┴──────────────┐
              ▼                             ▼
  ┌───────────────────┐         ┌──────────────────────┐
  │  Buyer Matching   │         │  new_listing alert   │
  │  Engine (basic)   │         │  (on-import trigger) │
  └────────┬──────────┘         └──────────┬───────────┘
           │                               │
           ▼                               ▼
  ┌───────────────────┐         ┌──────────────────────┐
  │  new_match alert  │         │  Alert subscription  │
  │  (end-of-cycle)   │◄────────│  infrastructure      │
  └───────────────────┘         └──────────────────────┘
           │
           ▼
  [Phase 1 Launch Ready]
           │
           │  ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ PARALLEL POST-LAUNCH TRACKS ─ ─ ─ ─ ─ ─ ─ ─ ─
           │
    ┌──────┤────────────────────┬──────────────────────┐
    │      │                    │                      │
    ▼      ▼                    ▼                      ▼
[Rental [Phase 2 column    [Recommendation       [Property
 feed   promotions:         Engine (Phase 2+)]    Intelligence
 gate]  prev_list_price,                          (external API
        price_change_ts,                          agreements)]
        status_change_ts]
    │      │
    │      ▼
    │  [price_reduction /
    │   price_increase /
    │   status_change alerts]
    │
    ▼
[Rental column promotions (Phase 2R)]
    │
    ▼
[Tenant matching engine Phase 1]
    │
    ▼
[rental_available alert]
    │
    ▼
[Tenant recommendations (Phase 3)]

     ─ ─ ─ CROSS-CUTTING DEPENDENCY ─ ─ ─
[Buyer matching + Recommendation engine]
                    │
                    ▼
     [AI-generated explanations (Phase 3)]
     (requires both systems stable in prod)
```

**Parallelism note:** Once the Phase 1 column migration is applied and the import pipeline is updated, the buyer matching engine and the `new_listing` alert detection can be built in parallel — they share the same native column foundation but do not depend on each other's implementation. Both must be complete before `new_match` alerts can fire (match alerts need the matching engine's output).

**Tenant matching and Phase 2 price alerts are parallel tracks** after Phase 1 launch — they depend on different prerequisites (rental feed confirmation vs. Phase 2 column promotions) and have no implementation dependency on each other.

---

## 6. Critical Path Analysis

The shortest path from current state to a usable production launch of buyer matching and alert delivery is four sequential phases:

### Phase A — Run the Phase 1 Column Migration

**What it is:** A Laravel migration that adds 20 nullable columns to `bridge_properties`. The import pipeline continues writing to `raw_json` during and after this migration — no downtime, no data loss.

**What it unlocks:** The schema foundation for indexed buyer matching queries. Nothing else can proceed without this.

**Deliverable:** Migration applied; 20 new columns exist with correct SQL types. No data in the new columns yet.

**Prerequisite:** None (this is the entry point of the critical path).

---

### Phase B — Update Import Pipeline + Backfill Existing Rows

**What it is:** Two parallel actions:
1. Update `BridgeProperty::$fillable`, `$casts`, and the `updateOrCreate()` mapping array in `ImportBridgeProperties::handle()` so every new import upsert populates the 20 new columns alongside `raw_json`.
2. Run `bridge:backfill-native-columns` to populate the 20 new columns for all existing rows by extracting values from their existing `raw_json`.

**What it unlocks:** All new columns are populated for all rows — both historical and incoming. Index creation can begin.

**Deliverable:** `ImportBridgeProperties.php` updated; backfill command complete with zero errors on the verification checklist (all 7 checks from `STELLAR_PHASE1_NATIVE_COLUMN_MIGRATION_PLAN.md` §8 pass).

**Prerequisite:** Phase A complete.

---

### Phase C — Create Indexes

**What it is:** Run `CREATE INDEX CONCURRENTLY` for all 14 new indexes — composite B-tree on `(latitude, longitude)`, individual B-tree on `county_or_parish`, `property_sub_type`, `mls_status`, and 9 partial boolean indexes. Indexes are created after backfill to avoid per-row overhead during bulk data loading.

**What it unlocks:** Indexed WHERE clauses, range scans, and Haversine radius search. The buyer matching engine can now run its queries at acceptable performance.

**Deliverable:** All 14 indexes confirmed present via `pg_indexes` query (Check 4 from migration plan §8).

**Prerequisite:** Phase B complete (indexes must be built on populated data).

---

### Phase D — Build Buyer Matching Engine and Wire Alert Delivery

**What it is:** Two parallel implementations, both unblocked by Phase C:
1. **Buyer matching query layer:** Implement the 7-category 100-point scoring model. Hard filters run as indexed WHERE clauses against native columns. Soft scoring reads Tier 2 fields from `raw_json` per candidate record after the candidate set is reduced by hard filters. Match result objects include `why_this_matches`, `tradeoffs`, `caution_flags`, and `missing_data` blocks per the explanation blueprint.
2. **Alert delivery infrastructure:** Create `mls_alerts`, `alert_subscriptions`, and `alert_dedup_log` tables. Wire `new_listing` detection at import time. Wire `new_match` detection at end-of-cycle using the buyer matching engine output. Implement email delivery channel.

**What it unlocks:** Production-ready buyer matching with personalized alerts. This is the Phase 1 launch state.

**Deliverable:** Buyers with active criteria records receive email notifications for new listings and new matches. Match results are ranked by score and include explanation blocks.

**Prerequisite:** Phase C complete (indexed columns required for matching queries).

---

## 7. Blocker Classification

All remaining Stellar work is classified below as Critical / High / Medium / Post-Launch. Classification reflects impact on Phase 1 launch readiness.

### Critical (Phase 1 launch blocked without this)

| Item | Why Critical |
|---|---|
| Phase 1 column migration (20 columns on `bridge_properties`) | The matching engine cannot run any scored query without indexed native columns for location, property type, boolean features, and financial dimensions. This is the single most important prerequisite for Phase 1. |
| Import pipeline update for 20 new columns | New records will not populate native columns without the `updateOrCreate()` mapping changes. The backfill command handles historical rows; the import update handles all future rows. Without this, native columns go stale on every new import cycle. |

### High (Phase 1 launch severely degraded without this)

| Item | Why High |
|---|---|
| Rental feed verification (For Lease feed) | Blocks tenant matching entirely. No tenant match scores, no tenant alerts, no rental recommendations. Tenant matching is excluded from Phase 1 scope, but the feed verification must be actively pursued in parallel so tenant matching is not indefinitely deferred. |
| Buyer matching engine implementation | The core value proposition of Phase 1. Without the matching engine, `new_match` alerts cannot fire and scored results cannot be displayed. |
| Alert delivery infrastructure | `new_listing` and `new_match` alerts are the primary user-facing output of Phase 1. Without the three alert tables, subscription management, deduplication, and email channel, alert delivery cannot function. |
| IDX participation gate enforcement | A compliance requirement. Alerts that fire for non-IDX listings violate MLS data licensing rules. Must be enforced before any alert can go to production. |
| Senior community gate in matching | Fair Housing compliance. A buyer matching engine that presents age-restricted listings to ineligible users is legally non-compliant. |

### Medium (Post-Phase-1; needed for full alert coverage and richer matching)

| Item | Why Medium |
|---|---|
| Phase 2 column promotions (`previous_list_price`, `price_change_timestamp`, `status_change_timestamp`) | Required for price change and status change alerts. These are important user-facing features but are not needed for Phase 1 launch — `new_listing` and `new_match` are the minimum viable alert set. |
| Alert scheduled comparison job (price/status) | Required for Phase 2 alert types. Cannot be built without Phase 2 columns. |
| `back_on_market` and `coming_soon` alert implementation | High value for buyers watching specific listings, but deferred to Phase 2 because they either require the Phase 2 comparison job infrastructure or are lower-priority relative to `new_match`. |
| Phase 2 scoring precision columns (`subdivision_name`, `mls_area_major`, `bathrooms_full`, `garage_spaces`) | Improve buyer match score accuracy from ~60% to ~85% Tier 1 field coverage. Valuable but not blocking Phase 1 basic matching. |
| Recommendation engine | Requires stable Phase 1 matching engine plus Phase 2 columns. Medium priority because it provides discovery value, but buyers receive real value from the basic matching engine alone at Phase 1. |
| In-app notification panel UI | Required for Phase 2 and Phase 3 alert types. Phase 1 email delivery is sufficient to launch. |

### Post-Launch (Important but not required for production launch)

| Item | Why Post-Launch |
|---|---|
| Property intelligence external API integrations (Bridge public records, county tax, FEMA) | Require vendor agreement negotiations and integration engineering. Significant work; no impact on Phase 1 or Phase 2 matching/alerts. |
| FEMA flood zone risk tier translation table | Requires FEMA NFHL API integration. Until built, `STELLAR_FloodZoneCode` raw values are passed to Ask AI context as-is with a note that they are FEMA zone codes requiring professional interpretation. |
| School district quality signal | Requires geocode-to-district lookup API. Cannot be built from MLS data alone. |
| Seller propensity score and investor-owned likelihood score | Require county deed + tax enrichment (post-launch intelligence layer). |
| AI-generated match explanations (Phase 3) | Requires stable recommendation engine and Phase 2 matching precision. Natural-language explanation generation is a polish feature. |
| `rental_available`, `open_house_scheduled`, `photos_updated` alerts (Phase 3) | For Lease feed + in-app panel prerequisites. Lower priority relative to Phase 1 and Phase 2 alert types. |
| Agent listing claims table | Required for agent-facing alert routing (Recipient Class 3). Agents cannot receive targeted listing alerts without a claims association. |
| Saved-search user class (Recipient Class 4) | Future feature requiring a `saved_searches` table and anonymous session handling. No schema exists for this class yet. |
| Investment alternative recommendations | Requires investment/multifamily feed data (`CapRate`, `GrossIncome` are 0/25 in current sample). |

---

## 8. Launch Decision Matrix & Final Implementation Roadmap

### 8.1 — Launch Decision Matrix

| Must Have (Phase 1 launch blocked) | Should Have (Phase 2, near-term post-launch) | Can Launch Without (Phase 3, medium-term) | Future Expansion |
|---|---|---|---|
| Phase 1 column migration (20 columns) | Phase 2 column promotions (4 columns for price/status alerts) | In-app notification panel full UI | Property intelligence external APIs |
| Import pipeline update (20 field mappings) | Price change alerts (`price_reduction`, `price_increase`) | Full 10-type alert suite | FEMA flood zone risk tier |
| `bridge:backfill-native-columns` command | Status change alert (`status_change`) | `back_on_market`, `coming_soon` alerts | School district quality signal |
| All 14 new indexes (composite + partial) | `mls_status` Phase 2 comparison job watermark | `rental_available` alert | Seller propensity score |
| Buyer matching engine (hard filters + 7-category scoring) | `back_on_market` via comparison job | `open_house_scheduled`, `photos_updated` alerts | Investor-owned likelihood score |
| `new_listing` alert (on-import, email) | Phase 2 scoring precision columns (`subdivision_name`, `mls_area_major`) | Tenant matching engine (For Lease feed gated) | AI-generated match explanations |
| `new_match` alert (end-of-cycle, email) | `subdivision_name` and `mls_area_major` promotions | Recommendation engine (all 9 types) | Saved-search user class |
| Alert infrastructure (`mls_alerts`, `alert_subscriptions`, `alert_dedup_log`) | Coming Soon alert | Location DNA Phase 3 integration | Agent listing claims table |
| IDX participation compliance gate | Agent listing claims table (for agent alert routing) | Investment alternative recommendations | Walk Score / Transit Score API |
| Senior community Fair Housing gate | | | Rental alternatives recommendations |
| Tier 6 field suppression in all outputs | | | |
| CAN-SPAM unsubscribe handling | | | |
| Ask AI context for Stellar listings (raw_json per-record) | | | |

---

### 8.2 — Final Implementation Roadmap

The following sequence is the exact recommended execution order from current state through production launch and beyond. Each phase has a defined prerequisite, deliverable, and what it unlocks for the next phase.

---

**Roadmap Phase 1: Column Migration Foundation**

*Prerequisite:* None.
*Deliverable:* Laravel migration applied; 20 new nullable columns exist on `bridge_properties` with correct SQL types.
*What it unlocks:* Import pipeline update and backfill can begin.

---

**Roadmap Phase 2: Import Pipeline + Backfill + Indexes**

*Prerequisite:* Phase 1 complete.
*Deliverable:*
- `ImportBridgeProperties::handle()` updated with 20 new field mappings.
- `BridgeProperty::$fillable` and `$casts` updated.
- `bridge:backfill-native-columns` command built and executed successfully (all 7 verification checks pass).
- All 14 indexes created (`CONCURRENTLY` on production to avoid read locks): composite lat/lng B-tree, 3 individual B-tree, 9 partial boolean.

*What it unlocks:* Indexed matching queries can be built and run against the fully-populated schema.

---

**Roadmap Phase 3: Buyer Matching Engine**

*Prerequisite:* Phase 2 complete (indexed native columns with populated data).
*Deliverable:*
- Hard filter query layer (6 filters: active status, property type, price ceiling, bedroom minimum, bathroom minimum, senior community gate).
- 7-category 100-point soft scoring layer.
- Match result object with `why_this_matches`, `tradeoffs`, `caution_flags`, `missing_data` blocks.
- Compliance enforcement: Tier 6 suppression, senior community gate, IDX gate.
- Phase 1 coverage: Location (radius + city/ZIP exact match), Price (ceiling proximity only — price reduction requires Phase 2 columns), Size (living area, lot size, year built), Property Type (type + subtype), Amenities (pool, garage, waterfront, water view, view), Financial/Fees (HOA, CDD caution flag, tax burden), Lifestyle/Context (community features, green efficiency, new construction, pet-friendly community).

*What it unlocks:* `new_match` alert type can be wired; matched results can be displayed to buyers.

---

**Roadmap Phase 4: Alert Infrastructure + new_listing / new_match Delivery**

*Prerequisite:* Phase 3 complete (matching engine needed for `new_match`). `new_listing` can be wired as soon as Phase 2 is complete and does not require the full matching engine.
*Deliverable:*
- `mls_alerts` table migration.
- `alert_subscriptions` table migration (per-user per-type opt-in).
- `alert_dedup_log` table migration (composite unique index on `dedup_key`).
- `new_listing` detection wired at import time in `ImportBridgeProperties::handle()`.
- `new_match` detection wired at end-of-cycle: delta listing set → match query → score threshold check → dedup check → queue alert.
- Email delivery channel (listing address, price, beds, baths, sqft, link to listing detail).
- Unsubscribe link with signed token; immediate database-level suppression.

*What it unlocks:* Phase 1 launch state. Buyers with active criteria receive email alerts for new inventory and new matches.

---

**Roadmap Phase 5: Phase 1 Launch**

*Prerequisite:* Roadmap Phases 1–4 complete; compliance audit of alert payloads (no Tier 6 fields, IDX gate confirmed, senior community gate confirmed, CAN-SPAM unsubscribe confirmed).
*Deliverable:* Stellar buyer matching and `new_listing`/`new_match` alert delivery are production-ready. Ask AI context for Stellar listings is active (requires no additional columns — `raw_json` per-record extraction is already available).
*What it unlocks:* Post-launch roadmap phases can begin in parallel.

---

**Roadmap Phase 6: Price/Status Change Alerts (Phase 2 Columns)**

*Prerequisite:* Phase 1 launch complete; Phase 2 column promotions applied: `previous_list_price` `DECIMAL(15,2)`, `price_change_timestamp` `TIMESTAMP`, `status_change_timestamp` `TIMESTAMP`. (Note: `mls_status` is already a Phase 1 column and is used here for status diffing.)
*Deliverable:*
- Scheduled comparison job: queries rows where `modification_timestamp > :last_run`, computes `list_price < previous_list_price` (price reduction), `list_price > previous_list_price` (price increase), `status_change_timestamp > :last_run` with status delta.
- Alert types delivered: `price_reduction`, `price_increase`, `status_change`.
- `back_on_market` via comparison job (transition from Pending/Closed → Active).
- `coming_soon` via import-time detection (no new column required).
- In-app notification panel initial implementation (required for Phase 2+ alert types to have a home beyond email).
- Agent listing claims table (agents begin receiving targeted digest alerts for their managed listings).

*What it unlocks:* High-intent buyer engagement signals; agents see market movement on managed listings.

---

**Roadmap Phase 7: Rental Feed Activation + Tenant Matching Engine**

*Prerequisite:* Stellar For Lease feed confirmed active and returning populated rental records for all 6 critical fields. Phase 2R column promotions applied (`lease_considered_yn`, `for_lease_yn`, `long_term_yn`, `lease_amount_frequency`, `monthly_rent`, `lease_term`, `minimum_lease`, `availability_date`).
*Deliverable:*
- Tenant matching engine: 8-bucket 100-point scoring model with hard filters (active rental gate, rent ceiling, move-in date, pet restrictions, geographic radius, bedroom minimum, senior community eligibility).
- `rental_available` alert type wired.
- Tenant criteria records begin receiving match alerts and rental availability notifications.

*What it unlocks:* Platform serves tenant users with the same intelligence depth as buyer users.

---

**Roadmap Phase 8: Recommendation Engine**

*Prerequisite:* Buyer matching engine stable in production (Roadmap Phase 5); Phase 2 column promotions complete (`subdivision_name`, `mls_area_major`, `garage_spaces`).
*Deliverable:*
- All 9 recommendation types implemented (excluding Rental Alternatives, which requires Roadmap Phase 7).
- 100-point composite recommendation score across 6 sub-scores.
- Natural-language recommendation explanation templates (structured JSON explanation blocks).
- UI layer: recommendation cards displayed below or alongside match results with type labels.
- Location DNA Phase 3 integration wired into location adjacency scoring and explanation copy.

*What it unlocks:* Discovery experience beyond strict criteria matching; buyers see adjacent opportunities.

---

**Roadmap Phase 9: Property Intelligence Layer**

*Prerequisite:* External API access agreements in place: Bridge Interactive public records, at least one Florida county tax API (Orange, Hillsborough, or Pinellas recommended as high-coverage pilot counties), FEMA NFHL REST API (free).
*Deliverable (pilot counties first):*
- Ownership type signal.
- Absentee owner flag.
- Years owned.
- Last sale date and price.
- Flood zone risk tier translation (`STELLAR_FloodZoneCode` → Minimal / Moderate / High / Very High).
- Annual tax burden (with CDD breakdown).
- `property_intelligence_profiles` table or equivalent storage.
- Agent-facing intelligence signals surfaced in the Agent Hire Listings Hub and listing detail view.
- Buyer-facing signals (flood risk, tax burden, ownership type) added to the match explanation caution flags.

*What it unlocks:* Platform differentiation — intelligence buyers and agents cannot get from public portals; seller propensity outreach capability for agents in Roadmap Phase 10.

---

**Roadmap Phase 10: Advanced Intelligence Signals + AI Explanations**

*Prerequisite:* Property intelligence layer operational (Roadmap Phase 9); buyer matching and recommendation engines stable in production.
*Deliverable:*
- Estimated equity signal (investor buyers filter by equity tier).
- Investor-owned likelihood score.
- Seller propensity score (agent-facing — top outreach targets for off-market campaigns).
- Distressed property indicators (lis pendens, tax delinquency, recorded liens).
- AI-generated natural-language match and recommendation explanations.
- Full photo, open house, and rental-available alert delivery (Phase 3 alert types).

*What it unlocks:* Platform operates as a full-stack real estate intelligence system for buyers, tenants, and agents.

---

## 9. Proof Section

### Source Audits Reviewed

| Document | Sections Reviewed | Final Decisions Derived |
|---|---|---|
| `STELLAR_BRIDGE_FIELD_AUDIT.md` | Executive Summary, Full Field Inventory, Matching Question Answers | Field tier classifications, compliance boundary, field population rates |
| `STELLAR_MATCHING_READINESS_AUDIT.md` | §1 Executive Summary (conflict source), §2 Complete Field Classification, §3 Buyer Blueprint, §4 Tenant Blueprint, §6 Alert Blueprint | Tier assignments, gap analysis, `StandardStatus` vs `MlsStatus` resolution |
| `STELLAR_NATIVE_COLUMN_EXPANSION_STRATEGY.md` | §1 Executive Summary, §3 Buyer Requirements, §4 Tenant Requirements, §5 Alert Requirements, §6 Ask AI Requirements, §8 Phase 1 Promotions | 20-field list confirmation (conflict resolution), Ask AI raw_json decision, Phase 2 column list |
| `STELLAR_PHASE1_NATIVE_COLUMN_MIGRATION_PLAN.md` | §1 Executive Summary, §2 Top 20 Columns (conflict resolution — authoritative), §3 Indexing Strategy, §4 Backfill Strategy, §5 Import Mapping, §6 Rollout Sequence, §7 Risk Analysis, §8 Verification Checklist | Authoritative 20-column list, DDL types, index strategy, `PetsAllowed` normalization, `lot_size_sqft` null guard |
| `STELLAR_BUYER_MATCHING_ENGINE_ARCHITECTURE.md` | §1 Executive Summary, §2 Matching Inputs, §3 Hard Filters vs Soft Scoring, §4 Scoring Model, §5 Match Explanation Blueprint | 7-category scoring model, CDD caution flag rule, senior community compliance gate, explanation blueprint |
| `STELLAR_TENANT_MATCHING_ENGINE_ARCHITECTURE.md` | §1 Executive Summary, §2 Rental Feed Dependency, §3 Matching Inputs, §4 Hard Filters, §5 Scoring Model | Tenant matching implementation gate, 8-bucket model, rental column promotion list |
| `STELLAR_ALERT_SYSTEM_ARCHITECTURE.md` | §1 Executive Summary, §2 Alert Types (all 10), §3 Trigger Field Mapping, §4 Recipient Strategy, §5 Deduplication, §7 Delivery Strategy, §9 Compliance, §10 Implementation Roadmap | 10 alert types, 3-phase delivery plan, compliance gates, detection timing patterns |
| `STELLAR_RECOMMENDATION_DISCOVERY_ENGINE_ARCHITECTURE.md` | §1 Executive Summary, §2 Matching vs Recommendation, §3 Recommendation Types (all 9), §4 Input Signals, §5 Scoring Model, §9 Performance Strategy | 9 recommendation types, 6-sub-score model, Location DNA Phase 3 integration point |
| `STELLAR_PROPERTY_INTELLIGENCE_ARCHITECTURE.md` | §1 Executive Summary, §2 Data Sources, §3 Intelligence Signals (all 12), §8 Compliance, §10 Implementation Roadmap | 12 intelligence signals, 5 data sources, post-launch classification, external API requirements |
| `BIDYOURAGENT_FINAL_LAUNCH_ARCHITECTURE_AND_IMPLEMENTATION_PLAN.md` | §1 Executive Summary, §2 Launch Scope | Broader platform context; Stellar is the MLS intelligence layer feeding the existing auction platform |

---

### Final Architecture Decisions Summary

| Decision | Final Ruling |
|---|---|
| MLS data layer | Bridge API → `bridge_properties` upsert; `raw_json` + native-column dual strategy (permanent) |
| Phase 1 native column count | 20 fields — authoritative list in §2 Decision 2 and `STELLAR_PHASE1_NATIVE_COLUMN_MIGRATION_PLAN.md` §2 |
| Conflict: 12 vs. 20 columns | Resolved: 20 fields; migration plan supersedes audit summary |
| Buyer matching model | 100-point, 7 categories, hard filters before scoring |
| CDD in buyer matching | Caution flag only — never a scoring penalty |
| Tenant matching gate | Gated on For Lease feed confirmation; no phase begins before gate clears |
| Tenant matching model | 100-point, 8 buckets (architecturally complete; implementation gated) |
| Recommendation engine | 9 types, 100-point composite, 6 sub-scores; post-launch |
| Alert delivery | 10 types, 3 phases; Phase 1 delivers `new_listing` + `new_match` only |
| Price/status change alerts | Phase 2 — require 4 additional native column promotions |
| Ask AI for Stellar | Per-record `raw_json` extraction; no column promotion required |
| Location DNA | Phase 3 recommendation engine integration; not Phase 1 |
| Property intelligence | Post-launch; external API agreements required |
| `StandardStatus` vs `MlsStatus` | Both retained; `standard_status` for matching gates, `mls_status` for alert diffing |
| Canonical lot size field | `lot_size_sqft` from `LotSizeSquareFeet`; `LotSizeAcres` remains in `raw_json` |
| `PetsAllowed` storage | First array element normalized to `VARCHAR(50)`; full-array matching deferred to Phase 2 |

---

### Blocker Counts by Severity

| Severity | Count | Items |
|---|---|---|
| Critical | 2 | Phase 1 column migration; import pipeline update |
| High | 5 | Rental feed verification; buyer matching engine; alert delivery infrastructure; IDX compliance gate; senior community Fair Housing gate |
| Medium | 6 | Phase 2 column promotions; price/status alert jobs; `back_on_market`/`coming_soon` alerts; Phase 2 scoring columns; recommendation engine; in-app notification panel |
| Post-Launch | 10+ | Property intelligence APIs; FEMA integration; school district signal; seller propensity; investor score; AI explanations; saved-search class; agent listing claims; Walk Score API; rental/open house/photo alerts |

---

### Roadmap Phases Summary

| Roadmap Phase | Name | Phase 1 Launch | Phase 2 Post-Launch | Phase 3+ Future |
|---|---|---|---|---|
| 1 | Column Migration Foundation | ✅ Critical path | | |
| 2 | Import Pipeline + Backfill + Indexes | ✅ Critical path | | |
| 3 | Buyer Matching Engine | ✅ Critical path | | |
| 4 | Alert Infrastructure + new_listing / new_match | ✅ Critical path | | |
| 5 | Phase 1 Launch | ✅ Launch point | | |
| 6 | Price/Status Alerts (Phase 2 columns) | | ✅ | |
| 7 | Rental Feed + Tenant Matching Engine | | ✅ (gated) | |
| 8 | Recommendation Engine | | ✅ | |
| 9 | Property Intelligence Layer | | | ✅ |
| 10 | Advanced Signals + AI Explanations | | | ✅ |

---

### Proof: No Code Files, Migrations, or UI Files Were Changed

This document was produced by reading and synthesizing the nine source audit documents listed above. No application code was modified, no migrations were written or run, no schema changes were made, and no UI files were altered as part of producing this document.

The `git diff --name-only` command at the time of this document's creation shows only:

```
docs/audits/STELLAR_FINAL_LAUNCH_ARCHITECTURE_AND_IMPLEMENTATION_PLAN.md
```

No other file in the repository was touched.
