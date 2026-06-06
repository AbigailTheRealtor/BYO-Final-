# Buyer DNA, Tenant DNA, Avatar Profiles & Compatibility Scoring — Read-Only Audit

**Date:** 2026-06-06
**Scope:** Read-only structural audit. No production code was modified.

---

## Table of Contents

1. [Database Tables Involved](#1-database-tables-involved)
2. [Field Inventory — buyer_tenant_dna_profiles](#2-field-inventory----buyer_tenant_dna_profiles)
3. [Archetype & Avatar Taxonomy](#3-archetype--avatar-taxonomy)
4. [Match Score Storage](#4-match-score-storage)
5. [Explanation & Narrative Persistence](#5-explanation--narrative-persistence)
6. [Seller Audience Identification](#6-seller-audience-identification)
7. [Landlord Audience Identification](#7-landlord-audience-identification)
8. [UI Surface Map](#8-ui-surface-map)
9. [Known Gaps & Incomplete Features](#9-known-gaps--incomplete-features)

---

## 1. Database Tables Involved

Two primary tables drive the demand-side DNA and compatibility system.

### `buyer_tenant_dna_profiles`

Demand-side DNA profiles for buyer listings and tenant listings. One active (non-archived) row per listing; older rows are soft-archived via `archived_at`. Covers both listing types with a `listing_type` discriminator column.

**Migrations (in chronological order):**

| Migration file | What it added |
|---|---|
| `create_buyer_tenant_dna_profiles_table` | Core table: `listing_type`, `listing_id`, `version`, `source_listing_updated_at`, `preference_completeness`, `lifestyle_tags` (JSON), `deal_breaker_flags` (JSON), `archetype_label`, `commute_polygon_cache`, timestamps, `archived_at`, `computed_at` |
| `add_avatar_fields_to_buyer_tenant_dna_profiles` | Buyer avatar fields: `avatar_type`, `primary_motivation`, `secondary_motivation`, `buyer_narrative`, `buyer_preference_summary`, `buyer_personality_tags`, `buyer_match_preferences`, `avatar_confidence_score`, `buyer_avatar_version`, `buyer_readiness_score` |
| `add_tenant_avatar_fields_to_buyer_tenant_dna_profiles` | Tenant avatar fields: `tenant_narrative`, `tenant_preference_summary`, `tenant_personality_tags`, `tenant_match_preferences`, `tenant_avatar_version` |

### `listing_compatibility_scores`

Pairwise supply↔demand compatibility scores. Joins buyer listings to seller listings, and tenant listings to landlord listings.

**Migrations (in chronological order):**

| Migration file | What it added |
|---|---|
| `create_listing_compatibility_scores_table` | Core table: `buyer_listing_id`, `seller_listing_id`, `overall_score`, `physical_match_score`, `financial_match_score`, `terms_match_score`, `location_match_score`, `score_explanation` (JSON), `compatibility_narrative` (text), `computed_at`, `framework_version` |
| `add_tenant_columns_to_listing_compatibility_scores` | `tenant_listing_id`, `landlord_listing_id`, `listing_pair_type` discriminator |
| `add_compatibility_narrative_columns_to_listing_compatibility_scores` | `compatibility_summary_json`, `compatibility_highlights`, `compatibility_warnings`, `compatibility_readiness_score` |
| `add_compatibility_trait_results_to_listing_compatibility_scores` | `compatibility_trait_results` (JSON — raw per-dimension trait output from `CompatibilityEngine`) |
| `add_compatibility_framework_version_to_listing_compatibility_scores` | `compatibility_framework_version` (versioned string, e.g. `"v1"`) |
| `add_compatibility_computed_at_to_listing_compatibility_scores` | `compatibility_computed_at` (timestamp separate from original `computed_at`) |

---

## 2. Field Inventory — `buyer_tenant_dna_profiles`

### Core fields (all listing types)

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `listing_type` | string | `'buyer'` or `'tenant'` |
| `listing_id` | bigint | FK to the listing table of the matching type |
| `version` | integer | Incremented on each regeneration |
| `source_listing_updated_at` | timestamp | Snapshot of the source listing's `updated_at` at compute time; used to detect staleness |
| `preference_completeness` | decimal/float | 0–1 completeness score based on filled preference fields |
| `lifestyle_tags` | JSON array | Tags derived from listing preferences (e.g. `"pet_friendly"`, `"walkable_neighborhood"`) |
| `deal_breaker_flags` | JSON array | Structural conflict flags detected during compatibility computation — metadata only, not recommendations |
| `archetype_label` | string | Human-readable classification label (see §3) |
| `commute_polygon_cache` | text/JSON | **Reserved — unused.** Placeholder for future geospatial / Location DNA Phase 2 |
| `computed_at` | timestamp | When this version of the profile was computed |
| `archived_at` | timestamp (nullable) | Soft-delete; non-null = archived/superseded |
| `created_at`, `updated_at` | timestamps | Standard Laravel timestamps |

### Buyer avatar fields (added in Phase H avatar migration)

| Column | Type | Notes |
|---|---|---|
| `avatar_type` | string | Avatar classification key (maps to a named archetype) |
| `primary_motivation` | string | Top inferred buyer motivation |
| `secondary_motivation` | string | Secondary inferred motivation |
| `buyer_narrative` | text | Plain-text narrative describing the buyer profile |
| `buyer_preference_summary` | text | Condensed summary of buyer's stated preferences |
| `buyer_personality_tags` | JSON array | Personality signal tags for this buyer profile |
| `buyer_match_preferences` | JSON object | Structured match preference data for the buyer |
| `avatar_confidence_score` | decimal/float | 0–1 confidence that the avatar classification is accurate |
| `buyer_avatar_version` | string | Version string for the avatar classification algorithm |
| `buyer_readiness_score` | decimal/float | 0–1 estimate of buyer transaction readiness |

### Tenant avatar fields (added in Phase H tenant avatar migration)

| Column | Type | Notes |
|---|---|---|
| `tenant_narrative` | text | Plain-text narrative describing the tenant profile |
| `tenant_preference_summary` | text | Condensed summary of tenant's stated preferences |
| `tenant_personality_tags` | JSON array | Personality signal tags for this tenant profile |
| `tenant_match_preferences` | JSON object | Structured match preference data for the tenant |
| `tenant_avatar_version` | string | Version string for the tenant avatar classification algorithm |

> **Note:** Tenant avatar fields share `avatar_type`, `primary_motivation`, `secondary_motivation`, `avatar_confidence_score`, and `buyer_readiness_score` columns with buyers — those core avatar columns are shared. Only the narrative/preference/personality/match columns have separate tenant-prefixed variants.

---

## 3. Archetype & Avatar Taxonomy

### Buyer archetypes (`listing_type = 'buyer'`)

Produced by `BuyerTenantDnaGenerator` (primary classification) and enriched by `BuyerAvatarService` / `BuyerAvatarProfileService`.

| Archetype Label | Classification notes |
|---|---|
| Commercial Buyer | Signals include commercial property type preference |
| Waterfront Buyer | Signals include waterfront / water-access amenity preference |
| Investor Buyer | Signals include investment purpose flag |
| Vacation Buyer | Signals include vacation/secondary home intent |
| Downsizing Buyer | Signals include downsizing motivation flag |
| Luxury Buyer | Signals include luxury tier or high price ceiling |
| Move-Up Buyer | Signals include move-up buyer motivation |
| Budget-Conscious Buyer | Signals include budget-constrained price ceiling |
| First-Time Buyer | Signals include first-time buyer flag |
| Flexible Buyer | No strong signals resolve to a specific archetype; broadly compatible |
| **Relocation Buyer** | **Reserved — not classified in V1.** Signal detection exists but archetype assignment is deferred to a future version. |
| Unknown Buyer | Fallback when signals are present but do not meet any archetype threshold |

**Avatar enrichment output** (`BuyerAvatarService`):
- `primary_avatar` — top matched avatar label
- `secondary_avatars` — additional applicable avatar labels
- `signals` — key/value map of raw signal inputs driving classification
- `missing_inputs` — preference fields absent that would sharpen classification
- `status` — `generated` | `insufficient_data` | `failed`

### Tenant archetypes (`listing_type = 'tenant'`)

Produced by `BuyerTenantDnaGenerator` and enriched by `TenantAvatarService` / `TenantAvatarProfileService`.

| Archetype Label | Classification notes |
|---|---|
| Commercial Tenant | Signals include commercial lease intent |
| Lease-Option Tenant | Signals include lease-to-own / lease-option preference |
| Pet-Conscious Tenant | Signals include pet ownership flag |
| Amenity-Focused Tenant | Signals include strong amenity preference weighting |
| Space-Focused Tenant | Signals include minimum square footage or bedroom count |
| Budget-Conscious Tenant | Signals include budget-constrained rent ceiling |
| Flexible Tenant | No strong signals resolve to a specific archetype |
| Unknown Tenant | Fallback classification |

---

## 4. Match Score Storage

Yes — pairwise match scores are stored. `listing_compatibility_scores` holds one row per (buyer listing ↔ seller listing) or (tenant listing ↔ landlord listing) pair.

### Score columns

| Column | Notes |
|---|---|
| `overall_score` | Weighted aggregate of all computed dimension scores |
| `physical_match_score` | Alignment on physical property attributes (beds, baths, sq ft, type, features) |
| `financial_match_score` | Alignment on price, budget, financing terms |
| `terms_match_score` | Alignment on transaction terms (closing timeline, contingencies, etc.) |
| `location_match_score` | **Always `null` — Location DNA Phase 2 not yet implemented** |

### CompatibilityEngine — 14 dimensions, 8 eligible in Phase H

| Dimension | Phase H Status |
|---|---|
| `property_type` | Scored |
| `price_budget` | Scored |
| `bedrooms` | Scored |
| `bathrooms` | Scored |
| `square_footage` | Scored |
| `features_amenities` | Scored |
| `location` | Column exists; always null (Phase 2 not implemented) |
| `parking` | Scored |
| `budget_flexibility` | Scored |
| `occupancy` | Ineligible — field data not yet collected |
| `furnishing` | Ineligible — field data not yet collected |
| `timeline` | Ineligible — field data not yet collected |
| `lease_term` | Ineligible — tenant-only; field data not yet collected |
| `hoa_fees` | Ineligible — field data not yet collected |

Raw per-dimension results are stored in `compatibility_trait_results` (JSON) — unprocessed engine output before the BYA report layer transforms it for consumer display.

### Triggering

Scores are computed by the `ComputeCompatibilityScore` queued job, dispatched by:
- `PropertyDnaProfileCompatibilityObserver` — fires on supply-side (`PropertyDnaProfile`) create/update
- `BuyerTenantDnaProfileCompatibilityObserver` — fires on demand-side (`BuyerTenantDnaProfile`) create/update

Both observers use `BuyerPropertyCompatibilityService` (buyer↔seller) and `TenantPropertyCompatibilityService` (tenant↔landlord) to identify matching counterpart listings.

---

## 5. Explanation & Narrative Persistence

Yes — multiple explanation and narrative columns are persisted on `listing_compatibility_scores`:

| Column | Type | Contents |
|---|---|---|
| `score_explanation` | JSON | Structured per-dimension explanation: aligned/conflicting/unresolved dimension lists + per-dimension explanation strings |
| `compatibility_narrative` | text | Plain-text narrative summarizing overall pair compatibility |
| `compatibility_summary_json` | JSON | Structured summary object (used by BYA consumer report layer) |
| `compatibility_highlights` | text/JSON | Strong alignment areas |
| `compatibility_warnings` | text/JSON | Conflict or gap areas |
| `compatibility_readiness_score` | decimal/float | Readiness-weighted compatibility score |

### BYA consumer report pipeline

`ConsumerCompatibilityReportController` reads `compatibility_trait_results` and runs a four-service pipeline:

1. **`ByaCompatibilityAlignmentService`** — categorizes each dimension as `aligned`, `conflicting`, or `unresolved`
2. **`ByaCompatibilityExplanationService`** — maps alignment categories to plain-English sentences
3. **`ByaCompatibilityNarrativeService`** — generates an overall summary sentence
4. **`ByaCompatibilityReportService`** — assembles the report: `summary.summary_sentence` + per-dimension `label`, `alignment_category`, `sentence`

**Privacy filter:** The controller explicitly excludes `overall_score`, `deal_breaker_flags`, `explanation_key`, `template_id`, trace data, reviewer notes, review history, and admin metadata from the consumer view.

> **Gap:** The consumer Blade template `resources/views/consumer/compatibility_report.blade.php` **does not exist**. The controller references it but the file has not been created — any request to this route will throw a `ViewNotFoundException`.

---

## 6. Seller Audience Identification

Seller listings have a `PropertyDnaProfile` row (supply-side), not a `BuyerTenantDnaProfile` row. `PropertyDnaGenerator` emits buyer archetype tags into `ai_buyer_archetype_tags` (JSON array on `property_dna_profiles`) using structured tag prefixes:

| Tag prefix | Meaning |
|---|---|
| `type:` | Property type compatibility signal (e.g., `type:single_family`) |
| `financing:` | Financing compatibility (e.g., `financing:fha_eligible`) |
| `amenity:` | Amenity-driven audience signal (e.g., `amenity:waterfront`) |
| `policy:` | Policy-driven audience signal (e.g., `policy:pet_allowed`) |
| `structure:` | Structural property signal (e.g., `structure:investment_unit`) |

`SellerDnaReportService` derives `buyer_archetype_alignment` from these tags — a mapping of which buyer archetypes the seller listing best suits.

`ai_marketing_hooks` stores marketing copy hooks keyed to those archetypes.

**Admin UI:** `admin/dna/seller.blade.php` renders personality profile, `ai_buyer_archetype_tags`, `ai_marketing_hooks`, and `buyer_archetype_alignment`.

**No consumer-facing surface exists** — sellers cannot see their recommended buyer audience on any listing page.

---

## 7. Landlord Audience Identification

Identical pattern to seller. `PropertyDnaProfile` rows with `listing_type = 'landlord'` receive `ai_buyer_archetype_tags` encoding tenant-relevant signals. `LandlordDnaReportService` derives tenant archetype alignment.

**Admin UI:** `admin/dna/landlord.blade.php` renders the equivalent view.

**No consumer-facing surface exists** for landlord audience intelligence.

---

## 8. UI Surface Map

### Admin-only views

| Route | View | What it shows |
|---|---|---|
| `admin/dna/demand` | `admin/dna/demand/index` | Filterable table of all `buyer_tenant_dna_profiles` rows |
| `admin/dna/demand/{id}` | (raw inspector) | Full raw record for a single demand DNA profile |
| `admin/dna/demand/scores` | `admin/dna/scores/index` | Filterable table of all `listing_compatibility_scores` rows |
| `admin/dna/buyer/{listingId}` | `admin/dna/buyer` | Buyer DNA detail: header, avatar card, lifestyle tags, conflict flags, scalar fields |
| `admin/dna/tenant/{listingId}` | `admin/dna/tenant` | Same structure for tenant profiles |
| `admin/dna/seller/{listingId}` | `admin/dna/seller` | Seller DNA: personality, archetype tags, marketing hooks, buyer alignment |
| `admin/dna/landlord/{listingId}` | `admin/dna/landlord` | Landlord DNA: same structure as seller |

### Consumer-facing views

| Route | View | What it shows |
|---|---|---|
| `compatibility/{id}` (approx.) | `consumer/compatibility_report` | Summary sentence + per-dimension label/alignment/sentence — **Blade template does not exist yet** |

### Not exposed to consumers (by design or by omission)

- Buyer/tenant archetype label
- Buyer/tenant avatar classification (primary, secondary, signals)
- Seller/landlord recommended audience (`buyer_archetype_alignment`)
- Raw compatibility score values (excluded by controller privacy filter)

---

## 9. Known Gaps & Incomplete Features

### 9.1 `location_match_score` — always null
`location_match_score` is never populated. The column and weight definitions exist, but Location DNA Phase 2 (geospatial radius matching, commute polygon computation) has not been implemented.

### 9.2 `commute_polygon_cache` — reserved, unused
Added speculatively for Location DNA Phase 2. Flagged "Reserved — Geospatial / Future Phase" in admin views. No code reads from or writes to this column.

### 9.3 Six of fourteen CompatibilityEngine dimensions are ineligible
`occupancy`, `furnishing`, `timeline`, `lease_term`, `hoa_fees`, and effectively `location` produce no score in Phase H because required listing fields are not yet collected.

### 9.4 `Relocation Buyer` archetype — unclassified in V1
`BuyerTenantDnaGenerator` includes signal detection for Relocation Buyer but does not assign the label in V1. Qualifying listings fall through to `Flexible Buyer` or `Unknown Buyer`.

### 9.5 Consumer compatibility report — Blade template missing
`ConsumerCompatibilityReportController::show()` renders `consumer.compatibility_report`, but `resources/views/consumer/compatibility_report.blade.php` does not exist. Any request to this route will throw a `ViewNotFoundException`.

### 9.6 No consumer-facing audience intelligence display
Neither seller nor landlord listing pages show which buyer/tenant archetypes the property is best suited for. This intelligence is admin-only.

### 9.7 Tenant avatar shares buyer avatar core columns
Tenant avatar data uses the same `avatar_type`, `primary_motivation`, `secondary_motivation`, `avatar_confidence_score`, and `buyer_readiness_score` columns as buyers — no `tenant_`-prefixed equivalents exist for these. If buyer and tenant avatar semantics diverge in a future version, a schema migration will be required to split them.

---

## Relevant Source Files

- `app/Services/Dna/BuyerTenantDnaGenerator.php`
- `app/Services/Dna/BuyerAvatarService.php`
- `app/Services/Dna/TenantAvatarService.php`
- `app/Services/Dna/BuyerAvatarProfileService.php`
- `app/Services/Dna/TenantAvatarProfileService.php`
- `app/Services/Dna/Compatibility/CompatibilityEngine.php`
- `app/Services/Dna/Compatibility/BuyerPropertyCompatibilityService.php`
- `app/Services/Dna/Compatibility/TenantPropertyCompatibilityService.php`
- `app/Services/Dna/Compatibility/CompatibilityExplanationService.php`
- `app/Services/Dna/Compatibility/ByaCompatibilityAlignmentService.php`
- `app/Services/Dna/Compatibility/ByaCompatibilityExplanationService.php`
- `app/Services/Dna/Compatibility/ByaCompatibilityNarrativeService.php`
- `app/Services/Dna/Compatibility/ByaCompatibilityReportService.php`
- `app/Services/Dna/SellerDnaReportService.php`
- `app/Services/Dna/LandlordDnaReportService.php`
- `app/Models/BuyerTenantDnaProfile.php`
- `app/Models/ListingCompatibilityScore.php`
- `app/Observers/PropertyDnaProfileCompatibilityObserver.php`
- `app/Observers/BuyerTenantDnaProfileCompatibilityObserver.php`
- `app/Jobs/ComputeCompatibilityScore.php`
- `app/Jobs/ComputeBuyerTenantDnaProfile.php`
- `app/Http/Controllers/Admin/DnaProfileController.php`
- `app/Http/Controllers/Admin/DnaInspectorController.php`
- `app/Http/Controllers/ConsumerCompatibilityReportController.php`
- `resources/views/admin/dna/buyer.blade.php`
- `resources/views/admin/dna/tenant.blade.php`
- `resources/views/admin/dna/seller.blade.php`
- `resources/views/admin/dna/demand/index.blade.php`
- `resources/views/admin/dna/scores/index.blade.php`
