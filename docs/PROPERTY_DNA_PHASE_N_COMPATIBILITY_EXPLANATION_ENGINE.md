# Property DNA Phase N — Compatibility Explanation Engine

**Document Date:** 2026-05-29
**Phase:** N — Compatibility Explanation Engine (Deterministic Explanation Layer)
**Preceding Phases:** H–M — Compatibility infrastructure, governance framework, versioning, and audit controls
**Type:** New service — translation layer only; no schema, no migrations, no UI, no scoring changes

---

## Table of Contents

1. [Purpose](#1-purpose)
2. [Allowed Behavior](#2-allowed-behavior)
3. [Prohibited Behavior](#3-prohibited-behavior)
4. [Explanation Output Structure](#4-explanation-output-structure)
5. [Dimension Mappings — All 14 Dimensions](#5-dimension-mappings--all-14-dimensions)
6. [Governance Restrictions](#6-governance-restrictions)
7. [Implementation File](#7-implementation-file)
8. [Verification Results](#8-verification-results)

---

## 1. Purpose

`CompatibilityExplanationService` translates persisted compatibility dimension identifiers (stored in `listing_compatibility_scores.score_explanation`) into neutral, plain-language explanation records — one per dimension, per result category.

The engine produces structured compatibility metadata (`aligned_dimensions`, `conflicting_dimensions`, `unresolved_dimensions`, and `dimension_match_map`) as opaque identifier arrays. These identifiers are meaningful to engineers reading the raw data but are not human-readable in a compliance or audit context. Phase N bridges that gap deterministically, without any AI, ranking, or recommendation logic.

**This service is a translation layer only.** It reads persisted data and returns explanation strings. It does not compute, modify, cache, or expose any compatibility score.

---

## 2. Allowed Behavior

The following behaviors are permitted and expected:

- **Reading persisted data:** The service reads `score_explanation` from an already-persisted `ListingCompatibilityScore` model instance passed as a method argument. It does not query the database itself.
- **Translating identifiers to strings:** Each dimension identifier (e.g., `pet_policy_alignment`) in each result category (`aligned`, `conflicting`, `unresolved`) is mapped to a corresponding neutral explanation string from the `DIMENSION_EXPLANATIONS` constant.
- **Returning structured output:** The service returns a plain PHP array with three keys (`aligned`, `conflicting`, `unresolved`), each containing an array of explanation records.
- **Deterministic output:** Given the same `ListingCompatibilityScore` input, the output is always identical. No randomness, session state, or external call influences the output.
- **Graceful fallback:** If a dimension identifier is not found in `DIMENSION_EXPLANATIONS` (e.g., a future dimension not yet mapped), the service returns a neutral fallback string and includes the record — no dimension is silently dropped.
- **Preserving input order:** Output dimension order matches the order in the persisted `score_explanation` arrays. No reordering, sorting by score, or weighting is applied.

---

## 3. Prohibited Behavior

The following behaviors are **permanently prohibited** in this service and must never be introduced by any future modification:

| Prohibited Behavior | Reason |
|---|---|
| Changing any compatibility score, metric, or column | This service is read-only with respect to scores. |
| Ranking, sorting, or weighting explanation output | Would introduce implicit scoring via display order. |
| Recommending any listing, buyer, tenant, seller, landlord, or agent | Fair Housing and governance constraint. |
| Determining suitability, qualification, approval, or rejection | Out of scope and a governance violation. |
| Predicting any outcome, likelihood, or transaction probability | Behavioral prediction is permanently prohibited. |
| AI reasoning, LLM inference, embedding lookup, or ML logic | No AI of any kind in the compatibility infrastructure. |
| Generating narrative persuasion copy, endorsements, or matchmaking language | Explanation strings are factual only. |
| Reading or writing any scoring model, DNA profile, or database row directly | The service only accepts a pre-loaded model instance. |
| Surfacing output in any user-facing view, API, PDF, email, or broadcast | Phase N output is internal audit use only. |
| Storing output in any cache, Redis, session, or analytics pipeline | No caching of compatibility payloads. |
| Reading `accessibility_requirements` from any source | Permanently hard-excluded from all compatibility infrastructure. |
| Protected-class inference of any kind | Fair Housing constraint carried forward from Phase F. |

---

## 4. Explanation Output Structure

The `generate(ListingCompatibilityScore $score): array` method returns:

```php
[
    'aligned' => [
        [
            'dimension'   => 'property_type_alignment',   // string — dimension identifier
            'explanation' => 'The property type recorded on the supply listing matches ...',  // string
        ],
        // ... one record per aligned dimension
    ],
    'conflicting' => [
        [
            'dimension'   => 'pet_policy_alignment',
            'explanation' => 'The demand listing indicates the occupant has pets, but ...',
        ],
        // ... one record per conflicting dimension
    ],
    'unresolved' => [
        [
            'dimension'   => 'budget_alignment',
            'explanation' => 'The supply-side price or value signal is not emitted by ...',
        ],
        // ... one record per unresolved dimension
    ],
]
```

**Key properties of the output:**
- Output order matches the order of dimensions in `score_explanation` — not sorted by score or weight.
- Each explanation string is a complete sentence, factual, neutral, and free of recommendation or predictive language.
- If a dimension identifier is not mapped in `DIMENSION_EXPLANATIONS`, the record is included with a neutral fallback string.
- Empty arrays are returned for any result category with no dimensions (e.g., `'conflicting' => []` when no conflicts exist).

---

## 5. Dimension Mappings — All 14 Dimensions

The following table documents all 14 approved compatibility dimensions and their three explanation strings. These strings are defined as the `DIMENSION_EXPLANATIONS` private constant in `CompatibilityExplanationService`.

---

### `property_type_alignment`

| State | Explanation |
|---|---|
| **aligned** | The property type recorded on the supply listing matches the property type preference recorded on the demand listing. |
| **conflicting** | The property type recorded on the supply listing does not match the property type preference recorded on the demand listing. |
| **unresolved** | A property type signal was absent on one or both sides; no property type comparison was made. |

---

### `financing_alignment`

| State | Explanation |
|---|---|
| **aligned** | The financing terms available on the supply listing (seller financing or assumable loan) correspond to the financing options expressed on the demand listing. |
| **conflicting** | The demand listing indicates interest in a financing type (seller financing or assumable loan) that the supply listing does not offer. |
| **unresolved** | A financing preference or availability signal was absent on one or both sides; no financing comparison was made. |

---

### `lease_structure_alignment`

| State | Explanation |
|---|---|
| **aligned** | The lease structure available on the supply listing (lease-option or lease-purchase) corresponds to the lease structure interest expressed on the demand listing. |
| **conflicting** | The demand listing indicates interest in a lease structure (lease-option or lease-purchase) that the supply listing does not offer. |
| **unresolved** | A lease structure preference or availability signal was absent on one or both sides; no lease structure comparison was made. |

---

### `occupancy_alignment`

**Classification:** Structurally ineligible in Phase N (demand-side occupancy preference signal not yet emitted by `BuyerTenantDnaGenerator`). Always returns `unresolved`.

| State | Explanation |
|---|---|
| **aligned** | The occupancy status recorded on the supply listing matches the occupancy preference recorded on the demand listing. |
| **conflicting** | The occupancy status recorded on the supply listing does not match the occupancy preference recorded on the demand listing. |
| **unresolved** | The demand-side occupancy preference signal is not emitted by the current generator architecture; no occupancy comparison was made. |

---

### `pet_policy_alignment`

| State | Explanation |
|---|---|
| **aligned** | The supply listing indicates pets are permitted, and the demand listing indicates the occupant has pets. |
| **conflicting** | The demand listing indicates the occupant has pets, but the supply listing does not record a pet-permitted signal. |
| **unresolved** | The demand listing does not record a pet signal; no pet policy comparison was made. |

---

### `smoking_policy_alignment`

| State | Explanation |
|---|---|
| **aligned** | Both the supply listing and the demand listing record that property restrictions are specified, indicating mutual awareness of policy terms. |
| **conflicting** | The supply listing and demand listing have differing signals regarding whether property restrictions are specified. |
| **unresolved** | A restrictions-specified signal was absent on one or both sides; no smoking policy comparison was made. |

---

### `parking_alignment`

| State | Explanation |
|---|---|
| **aligned** | The parking facilities available on the supply listing (garage or carport) satisfy the parking requirements recorded on the demand listing. |
| **conflicting** | The demand listing records a parking requirement (garage or carport) that the supply listing does not indicate is available. |
| **unresolved** | The demand listing does not record a parking requirement; no parking comparison was made. |

---

### `furnishing_alignment`

**Classification:** Structurally ineligible in Phase N (demand-side furnishing preference signal not yet emitted by `BuyerTenantDnaGenerator`). Always returns `unresolved`.

| State | Explanation |
|---|---|
| **aligned** | The furnishing terms recorded on the supply listing correspond to the furnishing preference recorded on the demand listing. |
| **conflicting** | The furnishing terms recorded on the supply listing do not correspond to the furnishing preference recorded on the demand listing. |
| **unresolved** | The demand-side furnishing preference signal is not emitted by the current generator architecture; no furnishing comparison was made. |

---

### `timeline_alignment`

**Classification:** Structurally ineligible in Phase N (demand-side timeline preference signal not yet emitted by `BuyerTenantDnaGenerator`). Always returns `unresolved`.

| State | Explanation |
|---|---|
| **aligned** | The move-in availability timing recorded on the supply listing corresponds to the timeline preference recorded on the demand listing. |
| **conflicting** | The move-in availability timing recorded on the supply listing does not correspond to the timeline preference recorded on the demand listing. |
| **unresolved** | The demand-side timeline preference signal is not emitted by the current generator architecture; no timeline comparison was made. |

---

### `hoa_alignment`

**Classification:** Structurally ineligible in Phase N (demand-side HOA preference signal not yet emitted by `BuyerTenantDnaGenerator`). Always returns `unresolved`.

| State | Explanation |
|---|---|
| **aligned** | The HOA or community governance status recorded on the supply listing corresponds to the HOA preference recorded on the demand listing. |
| **conflicting** | The HOA or community governance status recorded on the supply listing does not correspond to the HOA preference recorded on the demand listing. |
| **unresolved** | The demand-side HOA preference signal is not emitted by the current generator architecture; no HOA comparison was made. |

---

### `commercial_alignment`

| State | Explanation |
|---|---|
| **aligned** | The commercial use designation on the supply listing is consistent with the property type preference recorded on the demand listing. |
| **conflicting** | The supply listing is designated for commercial use, and the demand listing records a preference that does not include commercial property types. |
| **unresolved** | A commercial use or property type preference signal was absent on one or both sides; no commercial alignment comparison was made. |

---

### `amenity_alignment`

| State | Explanation |
|---|---|
| **aligned** | The amenities available on the supply listing (such as a pool) satisfy the amenity requirements recorded on the demand listing. |
| **conflicting** | The demand listing records an amenity requirement (such as a pool) that the supply listing does not indicate is available. |
| **unresolved** | The demand listing does not record an amenity requirement; no amenity comparison was made. |

---

### `budget_alignment`

**Classification:** Structurally ineligible in Phase N (supply-side price/value signal not yet emitted by `PropertyDnaGenerator`). Always returns `unresolved`.

| State | Explanation |
|---|---|
| **aligned** | The price or value signals on the supply listing are within the budget range recorded on the demand listing. |
| **conflicting** | The price or value signals on the supply listing fall outside the budget range recorded on the demand listing. |
| **unresolved** | The supply-side price or value signal is not emitted by the current generator architecture; no budget comparison was made. |

---

### `lease_term_alignment`

**Classification:** Structurally ineligible in Phase N (demand-side lease term preference signal not yet emitted by `BuyerTenantDnaGenerator`). Always returns `unresolved`.

| State | Explanation |
|---|---|
| **aligned** | The lease length available on the supply listing corresponds to the lease term preference recorded on the demand listing. |
| **conflicting** | The lease length available on the supply listing does not correspond to the lease term preference recorded on the demand listing. |
| **unresolved** | The demand-side lease term preference signal is not emitted by the current generator architecture; no lease term comparison was made. |

---

## 6. Governance Restrictions

The following governance restrictions apply to `CompatibilityExplanationService` in perpetuity. They carry forward from the Phase F governance rules and Phase M final audit.

### 6.1 Translation-Only Constraint

This service must remain a pure translation layer. It reads dimension identifiers and returns explanation strings. It does not read DNA profile fields, does not call `CompatibilityEngine`, does not dispatch any job, does not write any model, and does not access any table other than through the `ListingCompatibilityScore` instance passed to it.

### 6.2 No AI Interpretation

Explanation strings must never be generated by a language model, embedding model, classification model, or any AI system. All explanation strings must be static, pre-defined constants. Dynamic AI-generated explanations require a separately approved governance phase.

### 6.3 No Scoring Influence

The service must never change `overall_score`, `deal_breaker_triggered`, `deal_breaker_flags`, `score_explanation`, `compatibility_coverage_metric`, or any column on any model. It is read-only with respect to all persisted data.

### 6.4 No Protected-Class Language

No explanation string may reference race, color, national origin, religion, sex, familial status, disability, age, or any characteristic protected under the Fair Housing Act or applicable state law. The `accessibility_requirements` field is permanently off-limits and must never appear in any explanation string.

### 6.5 No Recommendation or Prediction Language

No explanation string may contain words or phrases that constitute a recommendation, endorsement, ranking, prediction, or suitability assessment. The following terms and their cognates must not appear affirmatively in any explanation string: `recommend`, `ideal`, `best match`, `suitable`, `qualified`, `approved`, `predict`.

### 6.6 No Public Exposure

Output from this service must not appear in any public listing page, API resource, PDF, email, broadcast, websocket, cache layer, or analytics pipeline. It is for internal audit use only. Exposing explanation output through any user-facing surface requires a separately approved visibility phase.

### 6.7 Output Order Is Deterministic and Non-Ranked

The order of explanation records in the output arrays must always reflect the order of dimension identifiers in the persisted `score_explanation` arrays. No sorting by score, weight, or any other signal may be applied to output order.

### 6.8 Versioning

This service does not carry its own version identifier. It reads the `scoring_framework_version` already recorded on the `ListingCompatibilityScore` row and reflects the dimension identifiers stored by that version of the engine. When `CompatibilityEngine` adds or modifies dimensions and bumps `SCORING_FRAMEWORK_VERSION`, the `DIMENSION_EXPLANATIONS` constant in this service must be updated to reflect the new or changed dimensions before the new engine version is deployed.

---

## 7. Implementation File

| Component | Path |
|---|---|
| Explanation service | `app/Services/Dna/Compatibility/CompatibilityExplanationService.php` |
| Compatibility engine (input source) | `app/Services/Dna/Compatibility/CompatibilityEngine.php` |
| Compatibility job (persists score_explanation) | `app/Jobs/ComputeCompatibilityScore.php` |
| Score model (accepted as method argument) | `app/Models/ListingCompatibilityScore.php` |

---

## 8. Verification Results

The following verification checks were run after creating the service file.

### 8.1 PHP Syntax Check

```
php -l app/Services/Dna/Compatibility/CompatibilityExplanationService.php
No syntax errors detected in app/Services/Dna/Compatibility/CompatibilityExplanationService.php
```

**Result: PASS**

### 8.2 Prohibited Language Grep

**Scope:** `app/Services/Dna/Compatibility/`
**Terms:** `recommend`, `ideal`, `best match`, `suitable`, `qualified`, `approved`, `predict`

Matches found in:
- `CompatibilityEngine.php` — all matches are in prohibition/governance comments (e.g., "No narrative generation, recommendation output, or ranking behavior", "NEVER a ranking, quality, recommendation, or desirability score", "approved dimensions" referring to the dimension set name).
- `CompatibilityExplanationService.php` — all matches are in the governance block comment (e.g., "Recommend any listing", "Predict any outcome", "no recommendations, no predictions").

**No affirmative uses of prohibited language exist in any operational code path in `app/Services/Dna/Compatibility/`.**

**Result: PASS**

### 8.3 AI Dependency Grep

**Scope:** `app/Services/Dna/Compatibility/`
**Terms:** `OpenAI`, `ChatGPT`, `GPT`, `LLM`

**Result:** No matches found.

**Result: PASS**

---

*End of Phase N documentation.*
