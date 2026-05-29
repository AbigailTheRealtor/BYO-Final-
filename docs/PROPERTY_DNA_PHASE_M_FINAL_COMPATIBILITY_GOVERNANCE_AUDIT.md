# Property DNA Phase M — Final Compatibility Governance Audit

**Audit Date:** 2026-05-29
**Auditor:** Task Agent (read-only; audit and hardening only)
**Audit Scope:** Phases H–L infrastructure — deterministic compatibility engine, persistence job, score model, observers
**Type:** Final governance audit — no schema changes, no migrations, no UI changes, no scoring redesign
**PHP Modifications:** None — this is a documentation-only audit; no PHP files were modified.
**Revision:** 2026-05-29 — Post-review corrections: M-02 retracted (null-check already present in code); R-01 and R-02 closed (Phase I added explicit `$tries`, `$timeout`, and `failed()` to both DNA generation jobs).

---

## Table of Contents

1. [Dimension Inventory and Classification](#1-dimension-inventory-and-classification)
2. [Denominator Integrity Verification](#2-denominator-integrity-verification)
3. [Deterministic-Only Behavior Verification](#3-deterministic-only-behavior-verification)
4. [Conflict Persistence Integrity Verification](#4-conflict-persistence-integrity-verification)
5. [Versioning and Archive Integrity Verification](#5-versioning-and-archive-integrity-verification)
6. [Governance Terminology Audit](#6-governance-terminology-audit)
7. [Grep Verification Results](#7-grep-verification-results)
8. [Documentation Inconsistencies Requiring Correction](#8-documentation-inconsistencies-requiring-correction)
9. [Inherited Open Risks (Phase G/H)](#9-inherited-open-risks-phase-gh)
10. [Future Risks](#10-future-risks)
11. [Overall Audit Verdict](#11-overall-audit-verdict)

---

## 1. Dimension Inventory and Classification

### 1.1 Files Reviewed

| File | Role |
|------|------|
| `app/Services/Dna/Compatibility/CompatibilityEngine.php` | 14-dimension compatibility computation engine |
| `app/Jobs/ComputeCompatibilityScore.php` | Queue job — compatibility persistence |
| `app/Models/ListingCompatibilityScore.php` | Compatibility score model |
| `app/Observers/Dna/PropertyDnaProfileCompatibilityObserver.php` | Supply-side fanout observer |
| `app/Observers/Dna/BuyerTenantDnaProfileCompatibilityObserver.php` | Demand-side fanout observer |
| `docs/PROPERTY_DNA_PHASE_F_COMPATIBILITY_RULES.md` | Phase F governance rules |
| `docs/PROPERTY_DNA_PHASE_G_READINESS_AUDIT.md` | Phase G readiness audit |
| `docs/PROPERTY_DNA_PHASE_H_INTERNAL_INSPECTOR_PLAN.md` | Phase H inspector planning |
| `docs/PROPERTY_DNA_PHASE_E_INTERNAL_GENERATION_RULES.md` | Phase E generation rules |
| `docs/BIDYOURAGENT_COMPATIBILITY_SCORING_FRAMEWORK.md` | Phase D scoring framework |

### 1.2 Full Dimension Inventory

All 14 approved dimensions are documented below. Classification follows the definitions established in Phase F and enforced in `CompatibilityEngine::STRUCTURALLY_ELIGIBLE_DIMENSIONS` and `STRUCTURALLY_INELIGIBLE_DIMENSIONS`.

**Classification key:**
- **Eligible** — both supply-side (`PropertyDnaGenerator`) and demand-side (`BuyerTenantDnaGenerator`) emit the required source signals; this dimension is counted in the coverage metric denominator.
- **Ineligible** — one or both generators do not yet emit the required source signals; always returns `unresolved`; excluded from the coverage metric denominator.
- **Unresolved-capable** — the dimension can produce `aligned` or `conflicting` when both sides have signals; without full signal it returns `unresolved`.

| # | Dimension Name | Classification | Supply Source | Demand Source | Deterministic | Currently Active | Conflict-Capable |
|---|---|---|---|---|---|---|---|
| 1 | `property_type_alignment` | **Eligible** | `ai_buyer_archetype_tags`: `type:{value}` | `lifestyle_tags`: `prefers-type:{value}` | Yes | Yes | Yes |
| 2 | `financing_alignment` | **Eligible** | `ai_buyer_archetype_tags`: `financing:seller-financed`, `financing:assumable` | `lifestyle_tags`: `open-to:seller-financing`, `open-to:assumable-loan` | Yes | Yes | Yes |
| 3 | `lease_structure_alignment` | **Eligible** | `ai_buyer_archetype_tags`: `structure:lease-option`, `structure:lease-purchase` | `lifestyle_tags`: `open-to:lease-option`, `open-to:lease-purchase` | Yes | Yes | Yes |
| 4 | `pet_policy_alignment` | **Eligible** | `ai_buyer_archetype_tags`: `policy:pets-allowed` | `lifestyle_tags`: `has-pets`; `deal_breaker_flags`: `pet_required` | Yes | Yes | Yes |
| 5 | `smoking_policy_alignment` | **Eligible** | `ai_buyer_archetype_tags`: `policy:restrictions-specified` | `lifestyle_tags`: `preference:restrictions-specified` | Yes | Yes | No (partial-signal only) |
| 6 | `parking_alignment` | **Eligible** | `ai_buyer_archetype_tags`: `parking:garage`, `parking:carport` | `lifestyle_tags`: `requires:garage`, `requires:carport`; `deal_breaker_flags`: `garage_required`, `carport_required` | Yes | Yes | Yes |
| 7 | `commercial_alignment` | **Eligible** | `ai_buyer_archetype_tags`: `use:commercial` | `lifestyle_tags`: `prefers-type:{value}` (keyword check) | Yes | Yes | Yes |
| 8 | `amenity_alignment` | **Eligible** | `ai_buyer_archetype_tags`: `amenity:pool` | `lifestyle_tags`: `requires:pool`; `deal_breaker_flags`: `pool_required` | Yes | Yes | Yes |
| 9 | `occupancy_alignment` | **Ineligible** | `ai_marketing_hooks`: `occupant_status` | No demand-side occupant preference tag emitted in Phase E | Yes (always `unresolved`) | Computed; excluded from denominator | No |
| 10 | `furnishing_alignment` | **Ineligible** | `ai_buyer_archetype_tags`: `feature:furnishing-terms-specified` | No demand-side furnishing preference tag in Phase E | Yes (always `unresolved`) | Computed; excluded from denominator | No |
| 11 | `timeline_alignment` | **Ineligible** | `ai_buyer_archetype_tags`: `timing:move-in-specified` | No demand-side timeline lifestyle tag emitted in Phase E | Yes (always `unresolved`) | Computed; excluded from denominator | No |
| 12 | `budget_alignment` | **Ineligible** | No price/value dimension in Phase E `PropertyDnaProfile` | `deal_breaker_flags`: `budget_ceiling_specified` | Yes (always `unresolved`) | Computed; excluded from denominator | No |
| 13 | `lease_term_alignment` | **Ineligible** | `ai_marketing_hooks`: `lease_length` | No `minimum_lease_length_specified` flag emitted in Phase E | Yes (always `unresolved`) | Computed; excluded from denominator | No |
| 14 | `hoa_alignment` | **Ineligible** | `ai_buyer_archetype_tags`: `governance:hoa` | No HOA preference field in `BuyerTenantDnaGenerator` (Phase J / R-03 resolved) | Yes (always `unresolved`) | Computed; excluded from denominator | No |

**Summary:** 8 eligible, 6 ineligible. All 14 dimensions are computed and stored in `score_explanation.dimension_match_map` and `unresolved_dimensions`. Only the 8 eligible dimensions contribute to the coverage metric denominator. This is confirmed by the runtime constant `STRUCTURALLY_ELIGIBLE_DIMENSIONS` (8 entries) and `STRUCTURALLY_INELIGIBLE_DIMENSIONS` (6 entries) in `CompatibilityEngine.php`.

### 1.3 Dimension Method Isolation Confirmation

Each of the 14 dimension computation methods in `CompatibilityEngine` is implemented as a standalone `private` method (`computePropertyTypeAlignment`, `computeFinancingAlignment`, etc.). Each method is individually wrapped in a `try/catch (\Throwable $e)` block that returns `'unresolved'` on failure. A failed slot never aborts computation of the remaining 13 dimensions. This isolation is confirmed by code review of all 14 methods (lines 207–576 of `CompatibilityEngine.php`).

---

## 2. Denominator Integrity Verification

### 2.1 Denominator Definition

The `compatibility_coverage_metric` denominator is defined as `count(STRUCTURALLY_ELIGIBLE_DIMENSIONS)`, which equals **8** in Phase H. This is the constant `$eligibleCount` computed at runtime in `CompatibilityEngine::compute()` (lines 158–159).

### 2.2 Denominator Computation Path

```
$eligibleDimensions = self::STRUCTURALLY_ELIGIBLE_DIMENSIONS;  // 8 entries
$eligibleCount      = count($eligibleDimensions);               // 8

$resolvedEligibleCount = 0;
foreach ($eligibleDimensions as $dim) {
    $result = $dimensionMatchMap[$dim] ?? 'unresolved';
    if ($result === 'aligned' || $result === 'conflicting') {
        $resolvedEligibleCount++;
    }
}

$coverageMetric = $eligibleCount > 0
    ? round(($resolvedEligibleCount / $eligibleCount) * 100, 2)
    : 0.0;
```

**Verified:** The denominator is constructed exclusively from the `STRUCTURALLY_ELIGIBLE_DIMENSIONS` array. Structurally ineligible dimensions are never iterated in the denominator computation loop. Their `'unresolved'` results have no influence on the metric.

### 2.3 Ineligible Dimension Exclusion Confirmation

All 6 ineligible dimensions (`occupancy_alignment`, `furnishing_alignment`, `timeline_alignment`, `budget_alignment`, `lease_term_alignment`, `hoa_alignment`) are confirmed to always return `'unresolved'` — verified by code inspection of each corresponding method. Because `'unresolved'` is not counted in `$resolvedEligibleCount`, and because these 6 dimensions are not in the `$eligibleDimensions` iteration loop, they contribute neither to the numerator nor to the denominator. The denominator cannot be inflated by missing generator signals.

### 2.4 Edge Case: Eligible Dimension Returns `unresolved`

When an eligible dimension lacks signal on one or both sides (e.g., neither supply nor demand has a `type:` tag), it returns `'unresolved'` and is **not** counted in `$resolvedEligibleCount`. This correctly means the metric reflects data completeness for that pair — an eligible dimension without data does not produce a false coverage signal.

**Verdict: PASS.** Denominator integrity is confirmed. Only eligible dimensions contribute. Ineligible dimensions are correctly excluded. The denominator accurately reflects structural encoding capability, not listing-pair-specific data gaps.

---

## 3. Deterministic-Only Behavior Verification

### 3.1 Scope of Review

The following components were examined for hidden weights, AI-generated scoring, fuzzy ranking, recommendation behavior, demographic inference, protected-class proxy logic, behavioral prediction, or autonomous matching logic:

- `CompatibilityEngine::compute()` and all 14 dimension methods
- `ComputeCompatibilityScore::handle()` and `persist()`
- `ListingCompatibilityScore` model (casts and fillable only — no computed logic)
- `PropertyDnaProfileCompatibilityObserver` and `BuyerTenantDnaProfileCompatibilityObserver`

### 3.2 Computation Logic Verification

Every dimension method uses only deterministic tag-presence checks (`hasTag`, `hasDealBreakerFlag`) and exact string comparisons (`extractTagValue` with `strtolower` normalization). No dimension method:

- Applies a numeric weight to any tag or flag
- Calls any external service, API, or AI endpoint
- Uses probabilistic thresholds, fuzzy matching, or partial-credit formulas
- References any protected-class attribute (race, religion, sex, national origin, familial status, disability, age)
- Reads `accessibility_requirements` (confirmed absent from all DNA infrastructure files)
- Produces a recommendation, ranking, or preference order

The three helper methods (`hasTag`, `extractTagValue`, `hasDealBreakerFlag`) are pure deterministic array operations with no side effects.

### 3.3 `compatibility_coverage_metric` Is Not a Ranking Score

The engine docblock, the `compute()` return docblock, and the inline comment at lines 169–184 of `CompatibilityEngine.php` all explicitly state:

> "This is a deterministic coverage/completeness metric ONLY — it measures what fraction of encodable dimensions were resolved for this listing pair. It must NEVER be interpreted as ranking quality, recommendation strength, user desirability, approval likelihood, tenant quality, buyer quality, investment quality, or transactional probability."

This prohibition is restated in `ComputeCompatibilityScore::persist()` at the `overall_score` assignment (lines 191–196).

### 3.4 Observer Fanout Is Not Ranking

Both compatibility observers (`PropertyDnaProfileCompatibilityObserver`, `BuyerTenantDnaProfileCompatibilityObserver`) dispatch compatibility jobs for **all** active counterpart profiles up to the FANOUT_CAP, in no particular order. There is no scoring, weighting, or prioritization applied to which counterpart profiles receive jobs first. The fanout is an exhaustive compute trigger, not a ranked selection.

### 3.5 No Autonomous Decision Output

`ComputeCompatibilityScore` produces one row in `listing_compatibility_scores` per invocation. This row:
- Is not used to suppress, hide, or reorder any listing
- Is not broadcast to any user-facing surface
- Is not read by any controller, Livewire component, route, or API resource
- Does not trigger any workflow, notification, email, or user-visible event

**Verdict: PASS.** The entire compatibility pipeline is deterministic and rule-based. No AI scoring, fuzzy ranking, hidden weights, recommendation behavior, demographic inference, or autonomous decision logic was found in any component.

---

## 4. Conflict Persistence Integrity Verification

### 4.1 `deal_breaker_flags` Column

As of Phase H implementation, `ComputeCompatibilityScore::persist()` writes the raw array of conflicting dimension identifier strings (e.g. `["pet_policy_alignment", "parking_alignment"]`) to `deal_breaker_flags`. When no conflicts are detected, it writes an empty array (`[]`). The column is never persisted as `null`.

**Note:** Phase G Readiness Audit (R-04) and the Phase H out-of-scope list both stated that `deal_breaker_flags` was "always null" and "deferred." Code review of `ComputeCompatibilityScore::persist()` (lines 174–210) confirms this has since been resolved: `deal_breaker_flags` is now populated with `$conflictingDimensions`. **R-04 is closed.**

### 4.2 Conflict Identifier Semantics

`deal_breaker_flags` stores structured dimension identifier strings only — names from the fixed set of 14 approved dimension names (e.g. `pet_policy_alignment`). No narrative text, AI-generated description, severity score, or recommendation language is stored. The column is populated by direct assignment from `$result['conflicting_dimensions']`, which is an array produced by the deterministic comparison logic in `CompatibilityEngine::compute()`.

### 4.3 `deal_breaker_triggered` Column

`deal_breaker_triggered` is set to `count($conflictingDimensions) > 0` — a boolean conflict-presence indicator. It is not:
- A rejection signal
- A disqualification flag
- A suitability assessment
- A recommendation or anti-recommendation

The inline comment at lines 197–202 of `ComputeCompatibilityScore.php` states this explicitly.

### 4.4 `score_explanation` Column

`score_explanation` stores the full structured dimension map:
- `aligned_dimensions`: array of dimension names
- `conflicting_dimensions`: array of dimension names
- `unresolved_dimensions`: array of dimension names
- `dimension_match_map`: keyed map of dimension → result string
- `eligible_dimension_count`: integer denominator used at computation time

All values are structured identifiers and integers derived deterministically. No AI narrative, no hidden severity weighting, no recommendation output is stored. The field is explicitly restricted from public exposure in Phase F rules (Section 8) and Phase H plan (Section 4c).

### 4.5 No Narrative Parsing

The conflict persistence layer reads no narrative text, interprets no free-form user content, and parses no AI output. All conflict detection is boolean tag-presence comparison. The Phase F governance rule (Section 9) prohibiting narrative generation from dimension arrays is confirmed respected.

**Verdict: PASS.** Conflict persistence is structured identifiers only. No AI narrative parsing, no hidden severity weighting, no recommendation layer affecting persistence. `deal_breaker_flags` is now correctly populated (R-04 resolved).

---

## 5. Versioning and Archive Integrity Verification

### 5.1 Append-Only Contract

`ComputeCompatibilityScore::persist()` implements the following sequence inside a `DB::transaction()`:

1. Acquires a per-pair PostgreSQL advisory lock via `pg_advisory_xact_lock(crc32('compat:{type}:{id}:{type}:{id}'))`.
2. Queries for the highest-versioned row matching all four identifier columns (`demand_listing_type`, `demand_listing_id`, `supply_listing_type`, `supply_listing_id`) using `orderByDesc('version')->first()` — regardless of `archived_at`.
3. If a prior row exists with `archived_at IS NULL`, sets `prior->archived_at = now()` and saves it.
4. Creates a new row with `version = prior_version + 1` (or `version = 1` if no prior exists) and `archived_at = null`.

This produces the invariant: at all times, at most one row per supply/demand pair has `archived_at IS NULL`. Prior rows are immutable after archiving — no field other than `archived_at` is modified on them.

### 5.2 Immutability of Archived Rows

Once a row's `archived_at` is set to a non-null timestamp, no subsequent code path modifies it. The query in `persist()` (`orderByDesc('version')->first()`) selects any existing row, regardless of `archived_at`, only to determine the next version number and to archive the current active row. No update is applied to rows that already have `archived_at IS NOT NULL`.

### 5.3 New Version Appending

`$newVersion = $prior ? ($prior->version + 1) : 1`. Version numbers are monotonically increasing integers starting at 1. There is no version reuse, no version gap filling, and no version rollback logic.

### 5.4 Transaction Isolation

The `DB::transaction()` in `persist()` is explicitly documented as isolated to `listing_compatibility_scores` only. The job docblock and inline comments confirm it must never be nested inside or share a transaction with listing saves or DNA profile generation transactions.

### 5.5 Advisory Lock Correctness

The lock key is `crc32('compat:{demandType}:{demandId}:{supplyType}:{supplyId}')`. The `compat:` prefix is distinct from the DNA profile lock prefixes (`pdna:`, `btdna:`), preventing any cross-table lock collision. The lock is only acquired on PostgreSQL (the project's primary database); other drivers proceed without locking (no-op branch confirmed in `acquirePairLock()`).

### 5.6 Current vs. Archived Selection Rules

For any consumer of `listing_compatibility_scores`:
- Current (active) row: `archived_at IS NULL`
- Archived rows: `archived_at IS NOT NULL`, ordered descending by `version` for history display
- Current version number: the highest `version` value for a given four-column key pair

These selection rules are deterministic and parameterless. No probabilistic selection, no AI-assisted row selection, and no hidden ordering logic exists.

**Verdict: PASS.** Versioning and archive behavior is deterministic and immutable. Archived rows are never mutated after archiving. New versions append correctly. Current-vs-archived selection rules are deterministic.

---

## 6. Governance Terminology Audit

### 6.1 Approved Terms

The following approved governance terms are used throughout the compatibility infrastructure:

| Term | Where Used | Correct |
|------|-----------|---------|
| **Coverage** | `compatibility_coverage_metric`, docblocks | Yes |
| **Alignment** | Dimension names, result values | Yes |
| **Conflict** | `conflicting_dimensions`, `deal_breaker_triggered` comments | Yes |
| **Unresolved** | `unresolved_dimensions`, dimension return values | Yes |
| **Deterministic** | Engine docblock, governance comments | Yes |

### 6.2 Prohibited Term Scan — Compatibility Infrastructure

Grep was run across `CompatibilityEngine.php`, `ComputeCompatibilityScore.php`, `ListingCompatibilityScore.php`, and both compatibility observers for the following prohibited terms (used affirmatively, not in negation/prohibition comments):

| Prohibited Term | Result |
|----------------|--------|
| Best Match | **No affirmative match** |
| Recommended / Recommendation | **No affirmative match** (matches are all in prohibition comments) |
| Ideal | **No affirmative match** |
| Most Compatible | **No affirmative match** |
| Approved | **No affirmative match** (matches are in prohibition comments) |
| Qualified / Disqualified | **No affirmative match** (one match in `BuyerTenantDnaGenerator` for `has_preapproval` — field maps mortgage pre-approval status, not a compatibility qualifier; outside the compatibility infrastructure) |
| Pass/Fail | **No affirmative match** |
| Suitable / Unsuitable | **No affirmative match** |

**Verdict: PASS.** No prohibited qualification or recommendation terminology is used affirmatively in the compatibility infrastructure.

### 6.3 `deal_breaker_triggered` and `deal_breaker_flags` Naming

Both column names contain the phrase "deal_breaker" — a schema name frozen from Phase A that governance documentation has consistently acknowledged cannot be renamed. The semantics are corrected in all code comments: both columns are described as conflict-presence metadata, not rejection or disqualification indicators. The prohibition on using "Deal Breaker" as a display label in the Phase H inspector plan (Section 11c) correctly addresses the UI surface. No violation is present in current code.

### 6.4 `overall_score` Column Name

The column name `overall_score` carries a "score" suffix that could imply quality ranking. However, the inline governance comment on every assignment of this column (in `ComputeCompatibilityScore::persist()`) explicitly states it must not be interpreted as ranking quality or recommendation strength. The Phase H inspector plan requires this column to be displayed with the label "Coverage Score" and a tooltip clarifying it is a data completeness indicator only. No violation is present in current code.

---

## 7. Grep Verification Results

### 7.1 Scan 1 — Prohibited Recommendation/Qualification Terms

**Scope:** `app/` + `resources/` + `docs/`
**Terms searched:** Best Match, Recommended, Ideal Match, Most Compatible, Approved, Qualified, Disqualified, Pass/Fail, Suitable, Unsuitable

**Files with any match:**

| File | Terms Found | Assessment |
|------|------------|-----------|
| `app/Services/Dna/BuyerTenantDnaGenerator.php` | `Approved` (in `pre_approved` field name) | **NOT a violation** — refers to mortgage pre-approval; unrelated to compatibility qualification |
| `resources/views/admin/` (multiple blade files) | `Approved`, `Qualified`, `Suitable` | **NOT violations** — these are listing status labels in existing admin views, not compatibility infrastructure |
| `resources/views/livewire/` (multiple blade files) | `Qualified`, `Suitable` | **NOT violations** — pre-existing listing workflow status fields, not compatibility output |
| `docs/BIDYOURAGENT_COMPATIBILITY_SCORING_FRAMEWORK.md` | `Best Match`, `Recommended`, `Ideal` | **NOT violations** — these appear exclusively in prohibition lists (Section 13: "Prohibited Terms") documenting what the framework must not say |
| `docs/PROPERTY_DNA_PHASE_H_INTERNAL_INSPECTOR_PLAN.md` | `Recommended` | **NOT violations** — appears in "Section 12: Recommended Future Implementation Sequence" (section heading) and nowhere near compatibility score output |
| `docs/PROPERTY_DNA_BUYER_TENANT_DNA_PHASE_1_AUDIT.md` | `Ideal`, `Recommended` | **NOT violations** — planning document language in pre-implementation audit; contains "Ideal tenant profile" in a planning table describing future marketing copy categories (not scoring output) |

**Compatibility infrastructure matches (CompatibilityEngine, ComputeCompatibilityScore, observers, models):** ZERO affirmative uses of any prohibited term.

**Verdict: PASS.** No prohibited terminology is used affirmatively in any operational compatibility code. All matches in docs are in prohibition/planning contexts.

### 7.2 Scan 2 — AI/OpenAI/ChatGPT References in Compatibility Infrastructure

**Scope:** `app/Services/Dna/`, `app/Jobs/Compute*`, `app/Models/ListingCompatibilityScore.php`, `app/Models/PropertyDnaProfile.php`, `app/Models/BuyerTenantDnaProfile.php`
**Terms searched:** openai, chatgpt, gpt, llm, language model, embedding, ml_, mlmodel, torch, tensorflow, sklearn, neural, probabilistic, fuzzy, weight (in computational context), inference engine

**Result:** ZERO matches across all files in scope.

**Verdict: PASS.** The deterministic compatibility infrastructure has no AI library dependencies, no AI API calls, no embedding lookups, and no probabilistic or ML-based logic of any kind.

### 7.3 Scan 3 — Hidden Weight / Ranking Logic

**Scope:** `app/Services/Dna/Compatibility/CompatibilityEngine.php`, `app/Jobs/ComputeCompatibilityScore.php`
**Terms searched:** weight, fuzzy, probabilistic, rank, sort_by_score, score.*order, order.*score, neural, predict

**Matches:** All matches are in prohibition comments ("NEVER be interpreted as ranking quality", "No AI, ML, probabilistic scoring, or weighted inference of any kind"). Zero affirmative uses.

**Verdict: PASS.** No hidden weights, fuzzy matching, or ranking behavior is implemented in the compatibility engine or persistence job.

### 7.4 Scan 4 — `accessibility_requirements` in DNA Infrastructure

**Scope:** `app/Services/Dna/`, `app/Jobs/Compute*`, `app/Models/`
**Terms searched:** accessibility_requirements

**Match:** One match in `CompatibilityEngine.php` line 26:
```
// - `accessibility_requirements` is permanently excluded and must never be read here.
```

This is a prohibition reminder comment, not a read. The field is never passed to any method, never referenced in any dimension computation, and never assigned to any output key.

**Verdict: PASS.** The `accessibility_requirements` hard exclusion is intact.

---

## 8. Documentation Inconsistencies Requiring Correction

The following documentation inconsistencies were identified during this audit. They do not represent code defects — the runtime behavior is correct — but they create misleading or inaccurate inline documentation that future maintainers could misinterpret.

### 8.1 Stale "9 are structurally eligible" Count — Class Docblock

**File:** `app/Services/Dna/Compatibility/CompatibilityEngine.php`, lines 14–17
**Current text:**
> "Of the 14 approved dimensions, 9 are structurally eligible in the current Phase E DNA encoding"

**Actual count:** 8. `STRUCTURALLY_ELIGIBLE_DIMENSIONS` has 8 entries (confirmed by code review and the constant comment at line 50 which correctly states "8 of 14"). This discrepancy arose when `hoa_alignment` was moved from `STRUCTURALLY_ELIGIBLE_DIMENSIONS` to `STRUCTURALLY_INELIGIBLE_DIMENSIONS` during Phase J (R-03 resolution). The class docblock was not updated.

**Risk:** A maintainer reading only the class docblock would believe the denominator is 9, not 8, and might incorrectly patch the eligible list.

**Recommended correction:** Update line 15 to read "8 are structurally eligible" and update line 20 to reflect Phase H rather than Phase E.

### 8.2 Stale "currently 9 of 14" Count — Inline Coverage Comment

**File:** `app/Services/Dna/Compatibility/CompatibilityEngine.php`, line 171
**Current text:**
```php
// Denominator = count of structurally eligible dimensions only (currently 9 of 14).
```

**Actual count:** 8. Same root cause as 8.1 — the `hoa_alignment` move was not reflected in this inline comment.

**Recommended correction:** Update to "currently 8 of 14".

### 8.3 Inaccurate Denominator Description in Persistence Job

**File:** `app/Jobs/ComputeCompatibilityScore.php`, line 192
**Current text:**
```php
// coverage/completeness metric ONLY (non-unresolved dimensions / 14 × 100).
```

**Actual formula:** `(resolved eligible dimensions / eligible_dimension_count) × 100` where `eligible_dimension_count = 8`, not 14. The denominator is never 14 — it is always `count(STRUCTURALLY_ELIGIBLE_DIMENSIONS)`. Using "/ 14" misrepresents the denominator as the total dimension count rather than the structurally eligible dimension count.

**Recommended correction:** Update to `(resolved eligible dimensions / eligible_dimension_count × 100)` to match the actual computation and the accurate description in `CompatibilityEngine` (lines 106–112 and 169–184).

### 8.4 Stale `scoring_framework_version` Reference in Phase F Doc

**File:** `docs/PROPERTY_DNA_PHASE_F_COMPATIBILITY_RULES.md`, Section "Scoring Framework Version" (end of document)
**Current text:**
> "The current Phase F implementation uses `scoring_framework_version = 'phase-f-v1'`."

**Actual current value:** `'phase-h-v1'` (set in `ComputeCompatibilityScore::SCORING_FRAMEWORK_VERSION`). The Phase F document was not updated when Phase H bumped the version. This is a documentation artifact — the constant in code is authoritative.

**Note:** This inconsistency is in a historical governance document. It is acceptable to leave Phase F's final section as a snapshot of that phase's state, but future readers should be aware that the active version is now `'phase-h-v1'`.

---

## 9. Inherited Open Risks (Phase G/H)

The following risks were documented in prior phases. Items marked **Closed** have been resolved by Phase I or later work; this Phase M audit confirmed closure by direct code review.

| Ref | Description | Severity | Status |
|-----|-------------|----------|--------|
| R-01 | `ComputePropertyDnaProfile` has no explicit `$tries` or `$timeout` | Medium | **Closed** — Phase I added `public int $tries = 3;`, `public int $timeout = 120;`, and a `failed(Throwable)` handler. Confirmed present at lines 18–19 and 35–37 of `app/Jobs/ComputePropertyDnaProfile.php`. |
| R-02 | `ComputeBuyerTenantDnaProfile` has no explicit `$tries` or `$timeout` | Medium | **Closed** — Phase I added the same three items. Confirmed present at lines 18–19 and 35–37 of `app/Jobs/ComputeBuyerTenantDnaProfile.php`. |
| R-03 | `hoa_alignment` eligible/ineligible discrepancy | Low | **Closed** — Phase J confirmed `hoa_alignment` moved to STRUCTURALLY_INELIGIBLE; coverage denominator accurate at 8 |
| R-04 | `deal_breaker_flags` always null on `listing_compatibility_scores` | Low | **Closed** — code review confirms `deal_breaker_flags` is now populated from `$result['conflicting_dimensions']` in `ComputeCompatibilityScore::persist()` |
| R-05 | FANOUT_CAP (500) is prototype-scale only | Medium | **Open** |
| R-06 | `DnaMarketingOutput` table and model are inert | Low | **Open** |
| R-07 | Six reserved columns on `property_dna_profiles` never populated | Low | **Open** |
| R-08 | `commute_polygon_cache` on `buyer_tenant_dna_profiles` reserved | Low | **Open** |
| R-09 | `location_score`, `condition_score`, `legal_score`, `compatibility_score` always null | Low | **Open** |
| R-10 | `crc32()` advisory lock key collision risk at high volume | Low | **Open** |
| R-11 | Non-PostgreSQL drivers receive no advisory lock | Informational | **Open** |

---

## 10. Future Risks

The following new risks are identified by this Phase M audit.

| Ref | Description | Severity | Recommended Path |
|-----|-------------|----------|-----------------|
| M-01 | Three stale inline comments (class docblock count "9", inline comment "9 of 14", job comment "/ 14") misrepresent the current denominator (Section 8.1–8.3). No code defect, but creates maintenance confusion. | Low | Correct the three inline comments in the next implementation phase that touches `CompatibilityEngine.php` or `ComputeCompatibilityScore.php`. |
| M-02 | ~~`commercial_alignment` returns `'conflicting'` when demand type is absent~~ **Retracted.** Post-review code inspection confirms that `computeCommercialAlignment()` already performs a null-check at lines 497–499: `if ($demandType === null) { return 'unresolved'; }`. The null-absent-demand path is correctly handled. No defect exists. | — | No action required. |
| M-03 | If a future phase adds a new dimension to `STRUCTURALLY_ELIGIBLE_DIMENSIONS` without incrementing `SCORING_FRAMEWORK_VERSION`, historical rows will be incomparable to new rows — both will share `'phase-h-v1'` but have different denominators. The version bump requirement is documented but not enforced by the code. | Low | Consider adding a unit test that asserts `SCORING_FRAMEWORK_VERSION !== 'phase-h-v1'` after any change to either dimension constant, forcing a deliberate version bump. |
| M-04 | `score_explanation` stores `eligible_dimension_count` as a snapshot at computation time. If `SCORING_FRAMEWORK_VERSION` is not bumped when the eligible set changes, this snapshot provides the only defense against denominator ambiguity in historical rows. This makes the snapshot critical — but it is currently not validated on read. | Low | Future inspector implementations should surface `eligible_dimension_count` from `score_explanation` alongside `scoring_framework_version` so auditors can reconstruct the exact denominator used for any archived row. |

---

## 11. Overall Audit Verdict

| Audit Area | Verdict | Notes |
|------------|---------|-------|
| Dimension inventory complete | **PASS** | All 14 dimensions documented, classified, and verified |
| Denominator integrity | **PASS** | Eligible denominator (8) correctly excludes all 6 ineligible dimensions |
| Structurally ineligible dimensions excluded | **PASS** | All 6 always return `unresolved`; none contribute to numerator or denominator |
| Deterministic-only scoring | **PASS** | No AI, ML, weights, fuzzy logic, or probabilistic scoring found anywhere |
| No hidden recommendation behavior | **PASS** | Output is append-only internal metadata; no ranking, sorting, or listing suppression |
| Protected-class inference absent | **PASS** | `accessibility_requirements` excluded; no demographic inference; no behavioral prediction |
| Conflict persistence as structured identifiers | **PASS** | `deal_breaker_flags` stores dimension name strings only; no narrative; no severity weighting |
| `deal_breaker_triggered` as conflict-presence indicator | **PASS** | Boolean derived from `count($conflictingDimensions) > 0`; not a rejection or disqualification signal |
| `score_explanation` not publicly exposed | **PASS** | No controller, Blade, Livewire, API, or route reads this column |
| Versioning deterministic and immutable | **PASS** | Append-only with advisory locking; archived rows never mutated |
| Current-vs-archived selection deterministic | **PASS** | `archived_at IS NULL` = current; descending version = history |
| Governance terminology — prohibited terms absent | **PASS** | Zero affirmative uses in operational code |
| Governance terminology — approved terms used | **PASS** | Coverage, Alignment, Conflict, Unresolved, Deterministic throughout |
| AI/OpenAI/LLM dependencies absent | **PASS** | Zero matches across entire compatibility infrastructure |
| No recursive compatibility enqueuing | **PASS** | `ListingCompatibilityScore` has no observer; chain terminates deterministically |
| FANOUT_CAP enforced | **PASS** | Hard cap of 500 confirmed in both observers |
| Transaction isolation | **PASS** | Compatibility transaction isolated to `listing_compatibility_scores` only |
| R-01 (`ComputePropertyDnaProfile` no `$tries`/`$timeout`) | **CLOSED** | Phase I added `$tries = 3`, `$timeout = 120`, and `failed()` — confirmed at lines 18–19, 35 of the job file |
| R-02 (`ComputeBuyerTenantDnaProfile` no `$tries`/`$timeout`) | **CLOSED** | Phase I added the same — confirmed at lines 18–19, 35 of the job file |
| R-03 (`hoa_alignment` discrepancy) | **CLOSED** | Phase J confirmed resolution; ineligible set is accurate |
| R-04 (`deal_breaker_flags` always null) | **CLOSED** | Code review confirms population is now implemented |
| Documentation inconsistencies (M-01) | **FLAG** | Three stale inline count comments; no code defect; correct in next phase |
| `commercial_alignment` null-demand edge case (M-02) | **RETRACTED** | Post-review code inspection confirmed null-check already present at lines 497–499; not a defect |

### 11.1 PHP Syntax Check

No PHP files were modified during this audit. The audit was read-only. No `php -l` syntax check is required. This is explicitly stated here per task specification Step 9.

### 11.2 Final Summary

The Property DNA compatibility engine is in a clean, governance-compliant state. All scoring is deterministic and fully traceable to named source fields. No AI, ML, probabilistic, or recommendation logic is present anywhere in the compatibility pipeline. Conflict persistence is structured identifiers only. Versioning is append-only and immutable. The coverage metric denominator is accurate at 8 eligible dimensions. All prohibited terminology is absent from operational code.

Three stale inline comments (Section 8.1–8.3) are the only items requiring future attention. These are documentation inconsistencies only — the runtime behaviour is correct in all cases. M-02 was retracted after post-review code inspection confirmed the null-check for absent demand type is already present. R-01 and R-02 were closed after confirming Phase I added explicit `$tries`, `$timeout`, and `failed()` to both DNA generation jobs.

The compatibility infrastructure is ready for the next approved phase.

---

*End of Document — Property DNA Phase M Final Compatibility Governance Audit*
*Audit completed: 2026-05-29 | Auditor: Task Agent | No PHP files modified*
