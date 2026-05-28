# Property DNA Phase F — Compatibility Computation Rules

## Overview

Phase F introduces the first internal compatibility layer between Property DNA profiles (seller/landlord) and Buyer/Tenant DNA profiles. It computes deterministic, field-to-field compatibility dimensions and persists append-only snapshots into the `listing_compatibility_scores` table.

Phase F produces **no public-facing output of any kind**. All computed records are internal-only. No compatibility data appears in any public page, API endpoint, PDF, email, dashboard view, admin search result, or recommendation widget.

---

## 1. Deterministic-Only Compatibility Rule

Every compatibility dimension value computed by `CompatibilityEngine` must be derived by an **explicit, rule-based mapping** from encoded DNA profile data. No probability estimates, no weighted formulas, no inference engines, no machine learning, and no AI calls are permitted in Phase F.

**Explainability requirement:** Every dimension result (`aligned`, `conflicting`, or `unresolved`) must be traceable to a specific named source signal in the supply-side `PropertyDnaProfile` or demand-side `BuyerTenantDnaProfile`. If a signal is unavailable or absent, the corresponding dimension result is `unresolved`. An `unresolved` result does not abort computation — the remaining dimensions are evaluated normally.

---

## 2. The 14 Approved Compatibility Dimensions

The following 14 dimensions are approved for Phase F. No additional dimensions may be added without a separate governance approval.

| Dimension | Supply Signal Source | Demand Signal Source | Notes |
|---|---|---|---|
| `property_type_alignment` | `ai_buyer_archetype_tags`: `type:{value}` | `lifestyle_tags`: `prefers-type:{value}` | Exact string match required for aligned |
| `financing_alignment` | `ai_buyer_archetype_tags`: `financing:seller-financed`, `financing:assumable` | `lifestyle_tags`: `open-to:seller-financing`, `open-to:assumable-loan` | Demand interest without matching supply → conflicting |
| `lease_structure_alignment` | `ai_buyer_archetype_tags`: `structure:lease-option`, `structure:lease-purchase` | `lifestyle_tags`: `open-to:lease-option`, `open-to:lease-purchase` | Demand interest without matching supply → conflicting |
| `occupancy_alignment` | `ai_marketing_hooks`: `occupant_status` | No Phase E demand encoding | Unresolved until demand-side occupancy preference is encoded |
| `pet_policy_alignment` | `ai_buyer_archetype_tags`: `policy:pets-allowed` | `lifestyle_tags`: `has-pets` | Demand has pets + supply allows → aligned; demand has pets + supply absent → conflicting |
| `smoking_policy_alignment` | `ai_buyer_archetype_tags`: `policy:restrictions-specified` | `lifestyle_tags`: `preference:restrictions-specified` | Both present → aligned; partial signal → unresolved |
| `parking_alignment` | `ai_buyer_archetype_tags`: `parking:garage`, `parking:carport` | `lifestyle_tags`: `requires:garage`, `requires:carport`; `deal_breaker_flags`: `garage_required`, `carport_required` | Demand requires parking that supply lacks → conflicting |
| `furnishing_alignment` | `ai_buyer_archetype_tags`: `feature:furnishing-terms-specified` | No Phase E demand encoding | Unresolved until demand-side furnishing preference is encoded |
| `timeline_alignment` | `ai_buyer_archetype_tags`: `timing:move-in-specified` | No Phase E demand tag | Unresolved until demand-side timeline tag is emitted |
| `hoa_alignment` | `ai_buyer_archetype_tags`: `governance:hoa` | `lifestyle_tags`: `preference:hoa-community-aware` | Both present → aligned; partial signal → unresolved |
| `commercial_alignment` | `ai_buyer_archetype_tags`: `use:commercial` | `lifestyle_tags`: `prefers-type:{value}` (indirect) | Supply commercial + demand prefers residential → conflicting |
| `amenity_alignment` | `ai_buyer_archetype_tags`: `amenity:pool` | `lifestyle_tags`: `requires:pool`; `deal_breaker_flags`: `pool_required` | Demand requires pool that supply lacks → conflicting |
| `budget_alignment` | No Phase E supply price encoding | `deal_breaker_flags`: `budget_ceiling_specified` | Unresolved until supply-side price dimension is added to PropertyDnaProfile |
| `lease_term_alignment` | `ai_marketing_hooks`: `lease_length` | No Phase E demand encoding | Unresolved until demand-side lease term flag is emitted |

### Dimension Result Semantics

- **`aligned`**: Both supply and demand have data on this dimension and the values are compatible by the explicit rule for that dimension.
- **`conflicting`**: Both supply and demand have data and the values are deterministically incompatible by the explicit rule for that dimension.
- **`unresolved`**: One or both sides have no signal for this dimension. `unresolved` is not a negative result — it means insufficient data to determine alignment or conflict.

---

## 3. Prohibited Inference Categories

The following categories are **permanently prohibited** from compatibility computation at any phase. They must never be introduced in any future extension of Phase F logic:

- **Protected class inference:** Race, color, national origin, religion, sex, familial status, disability, or any characteristic protected under the Fair Housing Act or applicable state law.
- **Behavioral prediction:** Predicted payment behavior, likelihood to vacate, likelihood to default, likelihood to renew, or any predictive scoring about future tenant/buyer/seller conduct.
- **Demographic inference:** Age estimation from occupant count, income-level inference, household composition inference, or any surrogate for protected characteristics.
- **Creditworthiness scoring:** No credit risk model, no scoring derived from eviction history fields, prior felony fields, or income fields.
- **Accessibility classification:** The `accessibility_requirements` field is permanently off-limits and must never be read, tokenized, mapped, or referenced in `CompatibilityEngine`, `ComputeCompatibilityScore`, either observer, or any downstream phase.

---

## 4. `accessibility_requirements` — Permanent Hard Exclusion

The meta key `accessibility_requirements` **must never be read** by `CompatibilityEngine` or any Phase F component. This field contains free-form user-authored text describing disability-related needs and is subject to Fair Housing protections. Reading, tokenizing, mapping, or including this field in any compatibility dimension, score, or explanation payload is a governance violation — regardless of phase.

---

## 5. `overall_score` — Coverage/Completeness Metric Only

The `overall_score` column in `listing_compatibility_scores` stores the `compatibility_coverage_metric`, defined as:

```
(resolved eligible dimensions / eligible_dimension_count) × 100
```

where:
- **resolved eligible dimensions** = count of aligned + conflicting results among structurally eligible dimensions only
- **eligible_dimension_count** = count of dimensions in `STRUCTURALLY_ELIGIBLE_DIMENSIONS` (currently 8 of 14)

### Why the denominator is not 14

Of the 14 approved Phase F dimensions, 6 are structurally ineligible in the current Phase H DNA encoding — their `unresolved` result is caused by missing generator architecture on one or both sides, not by absent listing data. Counting them in the denominator would systematically deflate the metric for every listing pair regardless of how complete that pair's actual data is. The eligible denominator ensures the metric accurately reflects data coverage for dimensions that can actually be computed.

### Structurally ineligible dimensions (excluded from denominator)

| Dimension | Reason |
|---|---|
| `occupancy_alignment` | Demand side: no occupant preference lifestyle tag in BuyerTenantDnaGenerator |
| `furnishing_alignment` | Demand side: no furnishing preference lifestyle tag in BuyerTenantDnaGenerator |
| `timeline_alignment` | Demand side: no timeline_flexibility lifestyle tag in BuyerTenantDnaGenerator |
| `budget_alignment` | Supply side: no price/asking-value dimension in PropertyDnaGenerator |
| `lease_term_alignment` | Demand side: no desired_lease_length deal_breaker_flag in BuyerTenantDnaGenerator |
| `hoa_alignment` | Demand side: no dedicated buyer/tenant HOA preference field in BuyerTenantDnaGenerator |

These dimensions are still computed and stored in `score_explanation.dimension_match_map` and `unresolved_dimensions` for audit completeness. They are excluded only from the metric denominator.

### Updating the eligible set

When a future phase adds the missing generator signals for an ineligible dimension:
1. Move the dimension name from `STRUCTURALLY_INELIGIBLE_DIMENSIONS` to `STRUCTURALLY_ELIGIBLE_DIMENSIONS` in `CompatibilityEngine`.
2. Bump `SCORING_FRAMEWORK_VERSION` in `ComputeCompatibilityScore` (e.g., `'phase-f-v2'`) so historical rows remain interpretable against the version of the engine that produced them.
3. Update this table and the dimension source field mapping table above.

### `eligible_dimension_count` in `score_explanation`

Each persisted row records `eligible_dimension_count` inside `score_explanation` so the denominator used at computation time is self-describing in the stored row — even after future phases expand the eligible set and bump the framework version.

### Prohibited interpretations

`overall_score` must **never** be interpreted or surfaced as:
- Ranking quality
- Recommendation strength
- User desirability
- Approval likelihood
- Tenant quality or screening score
- Buyer quality
- Investment quality
- Transactional probability
- Match quality score of any kind

Every code location that writes or reads `overall_score` must carry an inline comment stating this explicitly.

---

## 6. `deal_breaker_triggered` — Conflict-Presence Indicator Only

The `deal_breaker_triggered` column is `true` when `conflicting_dimensions` is non-empty (at least one deterministic field conflict was detected).

`deal_breaker_triggered` is a **conflict-presence indicator only**. It must **not** be interpreted as:
- Rejection of a listing or user
- Approval or disapproval signal
- Suitability assessment
- Tenant qualification or disqualification
- Buyer worthiness
- Recommendation or anti-recommendation logic
- Decision-making input of any kind

The schema column name is frozen from Phase A and must not be renamed. Documentation and code comments make the restricted semantics unambiguous.

---

## 7. Append-Only Persistence Mechanics

Compatibility score records in `listing_compatibility_scores` are **never overwritten or deleted**. The persistence contract for `ComputeCompatibilityScore` is:

1. Within a `DB::transaction()` isolated to `listing_compatibility_scores` only, acquire a per-pair advisory lock using `pg_advisory_xact_lock` (PostgreSQL) keyed on `crc32('compat:{demandType}:{demandId}:{supplyType}:{supplyId}')`.
2. Query for the highest-versioned existing row matching all four identifier columns, regardless of `archived_at`.
3. If a prior row exists with `archived_at IS NULL`, set `archived_at = now()` and save it.
4. Create a new row with `version = prior_version + 1` (or `version = 1` if no prior row exists) and `archived_at = null`.

This transaction must **never** be nested inside, chained to, or share a transaction boundary with any listing save, draft save, or DNA profile generation transaction.

---

## 8. No Public Exposure

Compatibility dimensions, `score_explanation` payloads, `overall_score`, `deal_breaker_triggered`, and any content of `listing_compatibility_scores` must **never** appear in:
- Public listing pages or search results
- API resources or JSON responses
- PDF listing packets or email templates
- Admin dashboards or admin search tooling
- Internal debug panels, Telescope, or exception payloads
- Recommendation or ranking widgets of any kind

Internal tooling exposure is also prohibited in Phase F. Visibility access to this data requires a separately approved visibility phase.

---

## 9. No Narrative Generation from Dimension Arrays

The `aligned_dimensions`, `conflicting_dimensions`, `unresolved_dimensions`, and `dimension_match_map` arrays stored in `score_explanation` are **structured deterministic metadata only**. They must **never** be used during Phase F to:
- Generate narrative explanations or conversational summaries
- Produce recommendation text or persuasion copy
- Generate matchmaking language or human-readable compatibility descriptions
- Drive any user-facing language model prompt

Narrative generation from compatibility arrays requires a separate governance-approved phase.

---

## 10. No AI Interpretation

Phase F compatibility output must never be passed to a language model, embedding model, classification model, or any AI system for interpretation, summarization, ranking, or recommendation — during Phase F. AI integration with compatibility data requires a separate governance-approved phase.

---

## 11. Queue Isolation and Failure Isolation

- `ComputeCompatibilityScore` declares `$tries = 3` and `$timeout = 60` explicitly on the job class. Queue driver defaults are not relied upon.
- Failed jobs beyond the retry limit are logged (with identifiers only, no dimension arrays) and discarded. They are never re-queued indefinitely.
- Job failure is silent to users and never surfaces through any user-facing or workflow-blocking mechanism.
- Compatibility jobs must never synchronously wait on, call, or chain-dispatch additional compatibility computations. Each job is fully self-contained.
- Compatibility jobs must never execute inside a transaction that mutates listing workflows or DNA profile persistence.

---

## 12. Idempotency Contract

Running `ComputeCompatibilityScore` for the same supply/demand profile pair multiple times is safe and produces correct append-only history:
- First run: creates a row with `version = 1`, `archived_at = null`.
- Second run: archives the version 1 row (`archived_at = now()`), creates version 2 with `archived_at = null`.
- At all times, at most one active row (`archived_at IS NULL`) exists per supply/demand pair.

The advisory lock prevents concurrent jobs from racing on the same pair.

---

## 13. Fanout Cap — Prototype-Scale Dispatch Only

Phase F compatibility dispatch is **intentionally limited to prototype-scale behavior**. Both observers enforce a hard cap of **500** counterpart profiles per observer invocation.

If the total count of active counterpart profiles exceeds the cap, the observer:
1. Logs a warning containing: listing type, listing ID, counterpart type, total counterpart count, and the cap value.
2. Dispatches jobs for at most 500 counterparts — never more.

**Prohibited at Phase F scale:**
- Full-market fanout
- Broad-market recomputation sweeps
- N×N marketplace scans
- Large-scale compatibility sweeps across all listings

Any future large-scale fanout requires a **separate scalability architecture phase** with its own approval.

---

## 14. No Recursive Compatibility Enqueuing

Compatibility jobs must **never** recursively enqueue additional compatibility jobs for the same profile pair during the same execution chain.

The `ListingCompatibilityScore` model save **never** triggers any observer or hook that dispatches further compatibility computation. Only the two DNA profile observers (`PropertyDnaProfileCompatibilityObserver` and `BuyerTenantDnaProfileCompatibilityObserver`) may dispatch compatibility jobs, and only in response to DNA profile saves — never in response to `ListingCompatibilityScore` saves.

---

## 15. No Listing or User Suppression, Prioritization, or Reordering

Compatibility dimensions, `overall_score`, `deal_breaker_triggered`, and `score_explanation` payloads must **never** be used to:
- Suppress, hide, or remove listings from search results
- Boost or prioritize listings in any sort order
- Reorder search results or user-visible lists
- Throttle or gate any workflow routing

Compatibility data is entirely passive internal storage during Phase F. It has no influence on any user-visible sort order, search result set, listing visibility, or workflow routing.

---

## 16. No Caching of Compatibility Payloads

Compatibility arrays, `score_explanation` payloads, and dimension maps must **never** be stored in:
- Redis or any key-value cache layer
- Session storage or request cache
- View cache or compiled template cache
- Any application cache layer

Compatibility data is internal append-only storage only. If ever needed internally, it must be read directly from `listing_compatibility_scores` — never from a cache layer.

---

## 17. No Serialization into Broadcasts, Websockets, or Analytics

Compatibility arrays, `score_explanation` payloads, and dimension maps must **never** be serialized into:
- Queue event broadcasts or websocket payloads
- Telemetry systems or analytics events
- Monitoring instrumentation or operational dashboards
- Any event bus or observability pipeline

Compatibility computation output remains entirely within the `listing_compatibility_scores` table.

---

## 18. No Locking on Listing Workflow Tables

Compatibility jobs must never lock, block, or wait on listing workflow tables (`property_auctions`, `landlord_auctions`, `buyer_criteria_auctions`, `tenant_criteria_auctions`, or any associated meta tables) beyond reading active DNA profile records at job start.

The compatibility persistence transaction is isolated to `listing_compatibility_scores` only.

---

## 19. No Operational Metrics Exposure

Compatibility persistence timing, queue throughput, counterpart counts, and compatibility-generation metrics must **never** be exposed through admin dashboards, monitoring widgets, or operational analytics during Phase F.

---

## 20. No Bulk Backfills

Phase F introduces no bulk backfill, scheduled recomputation sweep, or full-table matching loop. Compatibility scores are generated only in response to DNA profile saves via the two registered observers.

---

## Implementation Components

| Component | Path |
|---|---|
| Compatibility engine | `app/Services/Dna/Compatibility/CompatibilityEngine.php` |
| Compatibility job | `app/Jobs/ComputeCompatibilityScore.php` |
| Supply-side observer | `app/Observers/Dna/PropertyDnaProfileCompatibilityObserver.php` |
| Demand-side observer | `app/Observers/Dna/BuyerTenantDnaProfileCompatibilityObserver.php` |
| Observer registration | `app/Providers/AppServiceProvider.php` |
| Score model | `app/Models/ListingCompatibilityScore.php` |
| Score table | `listing_compatibility_scores` (Phase A schema — no changes in Phase F) |

---

## Scoring Framework Version

The current Phase F implementation uses `scoring_framework_version = 'phase-f-v1'`. This constant must be updated whenever the computation logic changes in a future phase, ensuring historical rows remain interpretable against the version of the engine that produced them.
