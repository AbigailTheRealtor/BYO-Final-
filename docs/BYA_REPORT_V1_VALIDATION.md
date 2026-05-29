# BYA_REPORT_V1 Validation Report
**Milestone 11A — Pipeline Validation & Audit Readiness**
**Date:** 2026-05-29
**Pipeline:** BYA_COMP_V1 → BYA_ALIGN_V1 → BYA_EXPLAIN_V1 → BYA_NARRATIVE_V1 → BYA_REPORT_V1
**Validated by:** PHPUnit compatibility test suite (267 tests, 2424 assertions — all pass)
**Remediation date:** 2026-05-29 (Issue 1 — alignment_category_counts mismatch resolved)

---

## Overview

This report documents end-to-end validation of the `BYA_REPORT_V1` internal compatibility
report pipeline against synthetic profiles that represent the full range of trait states
produced by `ByaNormalizationService` and `ByaAgentResponseNormalizationService`. No real
`listing_compatibility_scores` records with `compatibility_trait_results` exist in the
development database at the time of this validation (count = 0); all validation was
performed using structurally representative synthetic profiles exercised through the full
live service stack.

**Services exercised (in pipeline order):**
1. `ByaCompatibilityComparisonService::compare()` → BYA_COMP_V1
2. `ByaCompatibilityAlignmentService::categorize()` → BYA_ALIGN_V1
3. `ByaCompatibilityExplanationService::explain()` → BYA_EXPLAIN_V1
4. `ByaCompatibilityNarrativeService::generate()` → BYA_NARRATIVE_V1
5. `ByaCompatibilityReportService::generate()` → BYA_REPORT_V1

---

## Synthetic Profiles Used

**Record set identifier:** synthetic-seller-pair-999-777 (no PII — no real user data)

**Consumer profile (BYA_NORM_V1 — seller role, listing_id: 999):**

| Trait | Value |
|-------|-------|
| communication_channel | email |
| communication_frequency | weekly |
| responsiveness_expectation | within_24h |
| negotiation_style | collaborative |
| guidance_level | hands_on |
| decision_making_style | data_driven |
| transaction_pace | flexible |
| risk_tolerance | **null (missing: true)** |
| collaboration_style | proactive |
| representation_priorities | [pricing, negotiation] |
| representation_philosophy | client_first |
| property_strategy_fit | maximize_sale_price |

**Agent profile (BYA_AGENT_NORM_V1 — seller role, bid_id: 777):**

| Trait | Value |
|-------|-------|
| communication_channel | email |
| communication_frequency | **daily** (differs) |
| responsiveness_expectation | **within_1h** (differs) |
| negotiation_style | collaborative |
| guidance_level | hands_on |
| decision_making_style | data_driven |
| transaction_pace | flexible |
| risk_tolerance | moderate |
| collaboration_style | proactive |
| representation_priorities | [marketing, communication] (different array) |
| representation_philosophy | client_first |
| property_strategy_fit | quick_sale (different) |

Designed outcome: 7 `same`, 2 `different`, 3 `unknown` (technology_preference and
market_education_preference are structurally null on both sides; risk_tolerance consumer
is null).

---

## Goal Area Results

### G1 — BYA_REPORT_V1 Generates Successfully

**Result: PASS**

The pipeline completed without exceptions. The generated `BYA_REPORT_V1` contains all
required top-level keys (`report_version`, `alignment_version`, `explanation_version`,
`narrative_version`, `dimensions`, `summary`, `audit`) with correct types. `report_version`
is `"BYA_REPORT_V1"`.

---

### G2 — All 12 Dimensions Flow Through Without Errors or Silent Drops

**Result: PASS**

All 12 canonical dimensions are present at every layer:

| Layer | Dimension count | Missing |
|-------|----------------|---------|
| BYA_COMP_V1 | 12 | none |
| BYA_ALIGN_V1 | 12 | none |
| BYA_EXPLAIN_V1 | 12 | none |
| BYA_NARRATIVE_V1 | 12 | none |
| BYA_REPORT_V1 | 12 | none |

All 12 report dimension entries contain all six required fields: `relationship`,
`alignment_category`, `explanation_type`, `explanation_key`, `template_id`, `sentence`.

---

### G3 — Summary Counts Match Per-Dimension Data

**Result: PASS** *(Issue 1 resolved — see Remediation section)*

#### alignment_category_counts: PASS ✓

`ByaCompatibilityAlignmentService::buildSummary()` now emits an `alignment_category_counts`
nested sub-array alongside the existing flat count keys, matching the structure that
`ByaCompatibilityReportService` reads. Both services now use the same canonical key shape,
consistent with `explanation_type_counts` and `narrative_type_counts` in the pipeline.

| Category | Actual in dimensions | Reported in summary | Match |
|----------|---------------------|---------------------|-------|
| full_alignment | 7 | 7 | ✓ |
| partial_alignment | 0 | 0 | ✓ |
| adjacent_compatibility | 0 | 0 | ✓ |
| neutral_compatibility | 0 | 0 | ✓ |
| incompatible_alignment | 2 | 2 | ✓ |
| insufficient_data | 3 | 3 | ✓ |
| **Total** | **12** | **12** | ✓ |

#### explanation_type_counts: PASS ✓

Upstream value from `ByaCompatibilityExplanationService` summary is correctly nested
under `explanation_type_counts` and the report service reads it correctly.

| Type | Count (actual) | Count (report) | Match |
|------|---------------|----------------|-------|
| alignment | 7 | 7 | ✓ |
| difference | 2 | 2 | ✓ |
| adjacent | 0 | 0 | ✓ |
| neutral | 0 | 0 | ✓ |
| insufficient_data | 3 | 3 | ✓ |
| **Total** | **12** | **12** | ✓ |

#### narrative_type_counts: PASS ✓

Upstream value from `ByaCompatibilityNarrativeService` summary is correctly nested under
`narrative_type_counts` and the report service reads it correctly.

| Type | Count (actual) | Count (report) | Match |
|------|---------------|----------------|-------|
| alignment | 7 | 7 | ✓ |
| difference | 2 | 2 | ✓ |
| adjacent | 0 | 0 | ✓ |
| neutral | 0 | 0 | ✓ |
| insufficient_data | 3 | 3 | ✓ |
| **Total** | **12** | **12** | ✓ |

---

### G4 — audit.trace_keys Fully Populated

**Result: PASS**

`audit.trace_keys` contains exactly 12 entries — one per canonical dimension. Each entry
has all three required fields populated. Scored (non-insufficient_data) dimensions have
non-null values for all three fields.

Sample trace keys from validation run:

```json
"communication_style": {
  "alignment_category": "full_alignment",
  "explanation_key": "communication_style_full_alignment",
  "template_id": "communication_style_full_alignment"
},
"communication_frequency": {
  "alignment_category": "incompatible_alignment",
  "explanation_key": "communication_frequency_incompatible_alignment",
  "template_id": "communication_frequency_incompatible_alignment"
},
"risk_tolerance": {
  "alignment_category": "insufficient_data",
  "explanation_key": "risk_tolerance_insufficient_data",
  "template_id": "risk_tolerance_insufficient_data"
},
"technology_preference": {
  "alignment_category": "insufficient_data",
  "explanation_key": "technology_preference_insufficient_data",
  "template_id": "technology_preference_insufficient_data"
}
```

`trace_keys[dim].alignment_category` matches `dimensions[dim].alignment_category` for
all 12 dimensions.

---

### G5 — Null Handling: Absent Fields Return Documented Values, No Exceptions

**Result: PASS**

**Null consumer trait value → `relationship: "unknown"`, not null:**
When `risk_tolerance` consumer value is null, `ByaCompatibilityComparisonService`
emits `relationship: "unknown"` (the defined vocabulary value for null comparisons) and
`consumer: null`. No invented value is produced. This is correct per the service's
documented contract: `null` values yield `"unknown"`, not a fabricated relationship.

**Structurally null dimension (technology_preference):**
Both consumer and agent trait keys map to `null` for `technology_preference` and
`market_education_preference`. The pipeline correctly produces:
- `comparison: {consumer: null, agent: null, relationship: "unknown"}`
- `alignment: {alignment_category: "insufficient_data"}`
- `narrative: {sentence: "This dimension is not yet supported and is not included in this compatibility summary."}`

**Null upstream payloads:**
Passing `null` for `alignV1Payload` or `explainV1Payload` to `ByaCompatibilityReportService`
does not throw. The corresponding `alignment_version` / `explanation_version` fields in the
report are `null`. No exceptions escape.

**Malformed inputs (empty array, non-array, missing keys):**
Six tested input variants — `null`, `""`, `42`, `[]`, `["no_dimensions_key"=>true]`,
`["dimensions"=>"string"]` — all return a valid `BYA_REPORT_V1` stub array with no
exceptions.

**Insufficient_data dimensions have non-null sentences:**
All three insufficient_data dimensions (`technology_preference`,
`market_education_preference`, `risk_tolerance`) produce non-null, non-empty sentences
from the approved template library — no null gaps in narrative output.

---

### G6 — Stub Payload: Invalid Input Produces Expected Zero-Filled Stub

**Result: PASS**

`ByaCompatibilityReportService::generate(null, null, null)` produces:

```json
{
  "report_version": "BYA_REPORT_V1",
  "alignment_version": null,
  "explanation_version": null,
  "narrative_version": null,
  "dimensions": [],
  "summary": {
    "alignment_category_counts": {"full_alignment": 0, "partial_alignment": 0,
      "adjacent_compatibility": 0, "neutral_compatibility": 0,
      "incompatible_alignment": 0, "insufficient_data": 0},
    "explanation_type_counts": {"alignment": 0, "difference": 0,
      "adjacent": 0, "neutral": 0, "insufficient_data": 0},
    "narrative_type_counts": {"alignment": 0, "difference": 0,
      "adjacent": 0, "neutral": 0, "insufficient_data": 0},
    "summary_sentence": null
  },
  "audit": {
    "source_versions": {
      "alignment_version": null,
      "explanation_version": null,
      "narrative_version": null
    },
    "trace_keys": []
  }
}
```

All five invalid-input variants (string, integer, empty array, missing `dimensions` key,
non-array `dimensions`) produce `dimensions: []` with no exceptions.

---

### G7 — Determinism: Identical Inputs Produce Identical Output

**Result: PASS**

Two consecutive runs of the full pipeline from identical consumer + agent profiles produce
byte-for-byte identical `BYA_REPORT_V1` JSON. The stub path (`generate(null, null, null)`)
is also deterministic. No randomness, no timestamps, no external state influences the output.

---

### G8 — No Prohibited Language in Sentences

**Result: PASS**

All 12 dimension `sentence` values and the `summary_sentence` were checked against the
following prohibited-language patterns:

- rank / ranked / ranking
- score / scored / scoring
- recommend / recommended / recommendation
- match score / match percentage / match rating
- sort / sorted / sorting
- best match / best fit / best agent / best choice
- top match / top agent / top choice
- most compatible / most suitable / most likely
- strongly recommend
- suggest / suggested / suggestion

No violations found. All sentences are advisory observations of factual preference
differences or similarities between the two parties.

---

## Sample BYA_REPORT_V1 Payload (Full — redacted of PII)

Inputs: synthetic-seller-pair-999-777 (see profile tables above). No real user data.

```json
{
  "report_version": "BYA_REPORT_V1",
  "alignment_version": "BYA_ALIGN_V1",
  "explanation_version": "BYA_EXPLAIN_V1",
  "narrative_version": "BYA_NARRATIVE_V1",
  "dimensions": {
    "communication_style": {
      "relationship": "same",
      "alignment_category": "full_alignment",
      "explanation_type": "alignment",
      "explanation_key": "communication_style_full_alignment",
      "template_id": "communication_style_full_alignment",
      "sentence": "You indicated a preference for email as your primary communication channel; this agent describes their standard approach as email. Your communication style preferences appear to be well aligned on this dimension."
    },
    "communication_frequency": {
      "relationship": "different",
      "alignment_category": "incompatible_alignment",
      "explanation_type": "difference",
      "explanation_key": "communication_frequency_incompatible_alignment",
      "template_id": "communication_frequency_incompatible_alignment",
      "sentence": "You indicated a preference for weekly contact; this agent describes their standard practice as daily. A difference was found on this dimension."
    },
    "decision_speed": {
      "relationship": "same",
      "alignment_category": "full_alignment",
      "explanation_type": "alignment",
      "explanation_key": "decision_speed_full_alignment",
      "template_id": "decision_speed_full_alignment",
      "sentence": "You indicated a flexible transaction pace; this agent describes their approach as flexible. Your expectations regarding decision speed appear to be well aligned."
    },
    "risk_tolerance": {
      "relationship": "unknown",
      "alignment_category": "insufficient_data",
      "explanation_type": "insufficient_data",
      "explanation_key": "risk_tolerance_insufficient_data",
      "template_id": "risk_tolerance_insufficient_data",
      "sentence": "No comparison is available for risk tolerance. One or both parties did not provide a response for this dimension."
    },
    "negotiation_style": {
      "relationship": "same",
      "alignment_category": "full_alignment",
      "explanation_type": "alignment",
      "explanation_key": "negotiation_style_full_alignment",
      "template_id": "negotiation_style_full_alignment",
      "sentence": "You described your negotiation style as collaborative; this agent describes their approach as collaborative. Your negotiation style preferences appear to be well aligned."
    },
    "advisor_expectation": {
      "relationship": "same",
      "alignment_category": "full_alignment",
      "explanation_type": "alignment",
      "explanation_key": "advisor_expectation_full_alignment",
      "template_id": "advisor_expectation_full_alignment",
      "sentence": "You indicated you are looking for hands_on guidance; this agent describes their standard level of involvement as hands_on. Your advisor expectation appears to be well aligned with this agent's described approach."
    },
    "technology_preference": {
      "relationship": "unknown",
      "alignment_category": "insufficient_data",
      "explanation_type": "insufficient_data",
      "explanation_key": "technology_preference_insufficient_data",
      "template_id": "technology_preference_insufficient_data",
      "sentence": "This dimension is not yet supported and is not included in this compatibility summary."
    },
    "market_education_preference": {
      "relationship": "unknown",
      "alignment_category": "insufficient_data",
      "explanation_type": "insufficient_data",
      "explanation_key": "market_education_preference_insufficient_data",
      "template_id": "market_education_preference_insufficient_data",
      "sentence": "This dimension is not yet supported and is not included in this compatibility summary."
    },
    "property_search_involvement": {
      "relationship": "same",
      "alignment_category": "full_alignment",
      "explanation_type": "alignment",
      "explanation_key": "property_search_involvement_full_alignment",
      "template_id": "property_search_involvement_full_alignment",
      "sentence": "You described your preferred collaboration style as proactive; this agent describes their working style as proactive. Your property search involvement preferences appear to be well aligned."
    },
    "transaction_guidance_level": {
      "relationship": "same",
      "alignment_category": "full_alignment",
      "explanation_type": "alignment",
      "explanation_key": "transaction_guidance_level_full_alignment",
      "template_id": "transaction_guidance_level_full_alignment",
      "sentence": "You described your decision-making approach as data_driven; this agent describes how they support client decisions as data_driven. Your transaction guidance level preferences appear to be well aligned."
    },
    "availability_expectation": {
      "relationship": "different",
      "alignment_category": "incompatible_alignment",
      "explanation_type": "difference",
      "explanation_key": "availability_expectation_incompatible_alignment",
      "template_id": "availability_expectation_incompatible_alignment",
      "sentence": "You indicated an availability expectation of within_24h; this agent describes their standard responsiveness as within_1h. A difference was found on this dimension."
    },
    "personality_style": {
      "relationship": "same",
      "alignment_category": "full_alignment",
      "explanation_type": "alignment",
      "explanation_key": "personality_style_full_alignment",
      "template_id": "personality_style_full_alignment",
      "sentence": "You described your representation philosophy as client_first; this agent describes their professional approach as client_first. Your working style and representation philosophy appear to be well aligned."
    }
  },
  "summary": {
    "alignment_category_counts": {
      "full_alignment": 7, "partial_alignment": 0, "adjacent_compatibility": 0,
      "neutral_compatibility": 0, "incompatible_alignment": 2, "insufficient_data": 3
    },
    "explanation_type_counts": {
      "alignment": 7, "difference": 2, "adjacent": 0, "neutral": 0, "insufficient_data": 3
    },
    "narrative_type_counts": {
      "alignment": 7, "difference": 2, "adjacent": 0, "neutral": 0, "insufficient_data": 3
    },
    "summary_sentence": "Across the dimensions where both you and this agent provided responses, several areas of alignment were found, and one or more areas of difference were also identified."
  },
  "audit": {
    "source_versions": {
      "alignment_version": "BYA_ALIGN_V1",
      "explanation_version": "BYA_EXPLAIN_V1",
      "narrative_version": "BYA_NARRATIVE_V1"
    },
    "trace_keys": {
      "communication_style":         {"alignment_category": "full_alignment",         "explanation_key": "communication_style_full_alignment",         "template_id": "communication_style_full_alignment"},
      "communication_frequency":     {"alignment_category": "incompatible_alignment", "explanation_key": "communication_frequency_incompatible_alignment", "template_id": "communication_frequency_incompatible_alignment"},
      "decision_speed":              {"alignment_category": "full_alignment",         "explanation_key": "decision_speed_full_alignment",              "template_id": "decision_speed_full_alignment"},
      "risk_tolerance":              {"alignment_category": "insufficient_data",      "explanation_key": "risk_tolerance_insufficient_data",           "template_id": "risk_tolerance_insufficient_data"},
      "negotiation_style":           {"alignment_category": "full_alignment",         "explanation_key": "negotiation_style_full_alignment",           "template_id": "negotiation_style_full_alignment"},
      "advisor_expectation":         {"alignment_category": "full_alignment",         "explanation_key": "advisor_expectation_full_alignment",         "template_id": "advisor_expectation_full_alignment"},
      "technology_preference":       {"alignment_category": "insufficient_data",      "explanation_key": "technology_preference_insufficient_data",    "template_id": "technology_preference_insufficient_data"},
      "market_education_preference": {"alignment_category": "insufficient_data",      "explanation_key": "market_education_preference_insufficient_data", "template_id": "market_education_preference_insufficient_data"},
      "property_search_involvement": {"alignment_category": "full_alignment",         "explanation_key": "property_search_involvement_full_alignment", "template_id": "property_search_involvement_full_alignment"},
      "transaction_guidance_level":  {"alignment_category": "full_alignment",         "explanation_key": "transaction_guidance_level_full_alignment",  "template_id": "transaction_guidance_level_full_alignment"},
      "availability_expectation":    {"alignment_category": "incompatible_alignment", "explanation_key": "availability_expectation_incompatible_alignment", "template_id": "availability_expectation_incompatible_alignment"},
      "personality_style":           {"alignment_category": "full_alignment",         "explanation_key": "personality_style_full_alignment",           "template_id": "personality_style_full_alignment"}
    }
  }
}
```

---

## Fair Housing Checklist

All generated `sentence` and `summary_sentence` values from the validation run were
reviewed against the following six items:

### 1. Protected-Class References
**Result: PASS**

No sentence references or implies race, color, national origin, religion, sex, familial
status, disability, or any other protected class. All sentences describe communication
channel, timing, style, or philosophy preferences only.

### 2. Proxy Characteristics
**Result: PASS**

No sentence uses language that could serve as a proxy for protected-class characteristics.
The `technology_preference` and `market_education_preference` dimensions are explicitly
marked as unsupported and emit a neutral notice only. Trait values like `email`, `weekly`,
`collaborative`, `flexible`, `hands_on`, `data_driven`, `proactive`, `client_first`,
`within_24h` and `within_1h` carry no demographic proxy risk.

### 3. Steering Language
**Result: PASS**

No sentence directs the consumer toward or away from any agent, neighborhood, property
type, or transaction category. All sentences are factual observations of stated preferences
paired with stated practices.

### 4. Recommendation Language
**Result: PASS**

No sentence contains words such as "recommend," "suggest," "ideal," "best," "perfect,"
or any other advisory-direction language. The pipeline governance constraints forbid
recommendation language and the template library complies.

### 5. Ranking Language
**Result: PASS**

No sentence contains words such as "rank," "top," "highest," "above," "better than," or
any numeric ranking indicator. The advisory label `strong_alignment` in the upstream
BYA_ALIGN_V1 payload is an internal machine-readable key that never surfaces in report
sentences.

### 6. Suitability Language
**Result: PASS**

No sentence asserts that an agent is or is not "suitable," "qualified," "appropriate,"
"a fit," or similar. Sentences use the fixed phrase "A difference was found on this
dimension." for mismatches and "appear to be well aligned" for agreements — both of which
describe observed trait comparison results, not suitability determinations.

---

## Remediation Log

### Issue 1 — alignment_category_counts always zero-filled (RESOLVED)

**Severity at discovery:** High
**Status:** Resolved 2026-05-29

**Root cause:** `ByaCompatibilityAlignmentService::buildSummary()` emitted alignment
category counts only as flat top-level summary keys (`full_alignment_count`, etc.),
while `ByaCompatibilityReportService` looked for a nested `alignment_category_counts`
sub-array. No exception was raised — the report service silently fell back to all zeros.

**Fix applied:** `ByaCompatibilityAlignmentService::buildSummary()` and
`buildStubPayload()` were updated to also emit an `alignment_category_counts` nested
sub-array (keys match `ALL_ALIGNMENT_CATEGORIES` without the `_count` suffix),
consistent with how `explanation_type_counts` and `narrative_type_counts` are structured
in the downstream pipeline. The existing flat keys are preserved; no downstream code
was broken.

**PHPUnit coverage added:** Four new tests added to `ByaCompatibilityAlignmentServiceTest`:
- `summary_contains_alignment_category_counts_as_a_nested_array`
- `alignment_category_counts_matches_flat_summary_counts_for_all_same_input`
- `alignment_category_counts_nested_values_are_correct_for_mixed_input`
- `stub_payload_has_alignment_category_counts_all_zero`

**Re-validated result (per-dimension vs. report summary):**

| Category | Per-dimension count | Report summary | Match |
|----------|-------------------|----------------|-------|
| full_alignment | 7 | 7 | ✓ |
| partial_alignment | 0 | 0 | ✓ |
| adjacent_compatibility | 0 | 0 | ✓ |
| neutral_compatibility | 0 | 0 | ✓ |
| incompatible_alignment | 2 | 2 | ✓ |
| insufficient_data | 3 | 3 | ✓ |
| **Total** | **12** | **12** | ✓ |

---

## Issues Identified

No outstanding issues. All eight goal areas now pass. See Remediation Log above for
the resolved High Severity issue.

---

## Pipeline Readiness Statement

**The pipeline is ready for admin preview.**

All eight goal areas pass with no outstanding issues:

- G1 PASS — BYA_REPORT_V1 generates successfully with correct shape.
- G2 PASS — All 12 dimensions flow through all pipeline stages without drops.
- G3 PASS — All three count blocks (`alignment_category_counts`,
  `explanation_type_counts`, `narrative_type_counts`) accurately reflect per-dimension
  data. Issue 1 resolved.
- G4 PASS — `audit.trace_keys` fully populated for all 12 dimensions.
- G5 PASS — Null inputs return documented vocabulary values; no exceptions escape.
- G6 PASS — Invalid inputs return zero-filled stub without throwing.
- G7 PASS — Identical inputs produce identical output across consecutive runs.
- G8 PASS — No prohibited language in any sentence or summary_sentence.
- Fair Housing checklist — all six items pass.

The Admin Preview UI (Milestone 11) may proceed.

---

## Validation Method Notes

- PHPUnit compatibility test suite — 267 tests, 2424 assertions, all pass (full
  `tests/Unit/Services/Dna/Compatibility/` directory, no mocking)
- All five services exercised through live production code with structurally correct
  synthetic profiles (slot format: `{value, missing}` per normalization service contract)
- Synthetic profiles represent all three relationship categories: `same`, `different`, `unknown`
- Stub path validated with six distinct invalid-input variants
- Determinism validated with back-to-back identical runs
- Fair Housing review conducted manually against all generated sentences
- Database records: 0 real BYA-enabled records exist; validation used synthetic profiles
- Four new `alignment_category_counts` tests added to `ByaCompatibilityAlignmentServiceTest`
  as permanent coverage (not removed — these test the remediated production behaviour)
