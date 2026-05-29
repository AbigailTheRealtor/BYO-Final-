# BidYourAgent — Normalization Engine Output Schema

**Document Status:** Read-only schema definition. No code, schema, migration, route, controller, Blade, Livewire, config, or database file was created or modified to produce this document.
**Document Date:** 2026-05-29
**Phase:** Milestone 2 — Normalization Engine Payload Contract
**Upstream Authorities:**
- `docs/BIDYOURAGENT_COMPATIBILITY_AUDIT.md` (Phase A — field inventory, gap analysis, governance)
- `docs/BIDYOURAGENT_COMPATIBILITY_TRAIT_DESIGN.md` (Phase B — normalized trait definitions, crosswalks, naming inconsistency resolutions)

---

## Table of Contents

1. [Purpose and Scope](#1-purpose-and-scope)
2. [Normalization Version Constant](#2-normalization-version-constant)
3. [Payload Envelope](#3-payload-envelope)
4. [Per-Trait Slot Schema](#4-per-trait-slot-schema)
   - 4.1 Standard slot shape
   - 4.2 Three-state value model
   - 4.3 Documented slot exceptions
5. [The `informational_context` Bag](#5-the-informational_context-bag)
   - 5.1 Seller fields
   - 5.2 Buyer fields
   - 5.3 Landlord fields
   - 5.4 Tenant fields
6. [The `proxy_risk_flags` Array](#6-the-proxy_risk_flags-array)
7. [Worked Example Payloads](#7-worked-example-payloads)
   - 7.1 Seller — partial submission
   - 7.2 Buyer — fully populated
   - 7.3 Landlord — proxy risk flag active, structural gaps present
   - 7.4 Tenant — multiple structural gaps, sparse optional fields
8. [Governance Rules at the Normalization Layer](#8-governance-rules-at-the-normalization-layer)

---

## 1. Purpose and Scope

This document defines the **exact output payload** that the Milestone 2 normalization engine must produce when it reads a consumer-side `compatibility_preferences` record and emits a role-normalized representation-compatibility payload.

The payload shape defined here is the **frozen contract** shared by:

- The normalization engine itself (Milestone 2)
- Any future scoring layer (Phase D) that reads normalized trait slots to produce a `representation_compatibility_score`
- Any future AI explanation layer (Phase E) that reads trait slots to generate plain-language compatibility summaries
- Any future consumer UI layer (Phase F) that surfaces normalized compatibility data alongside bid cards

No implementation detail for scoring, explanation, or UI is specified in this document. This document is a data-shape contract only.

**Out of scope:**
- Scoring weights, match thresholds, or compatibility calculation logic
- Agent-side field design (Phase C / Milestone 3+)
- Any UI, Blade template, Livewire component, migration, or route

---

## 2. Normalization Version Constant

The normalization version string identifies the trait definition set and crosswalk rules in force when the payload was produced. Any change to a trait definition, a canonical option value, or a crosswalk mapping requires a new version constant.

```
BYA_NORM_V1
```

This string appears as the value of the `normalization_version` key at the top of every payload (see Section 3). Scoring and explanation consumers must reject payloads whose `normalization_version` they do not recognise, rather than silently processing them under incorrect assumptions.

---

## 3. Payload Envelope

Every payload the normalization engine produces has the following top-level structure. All six top-level keys are **always present**, regardless of role, listing completeness, or how many fields the consumer answered.

```json
{
  "normalization_version": "BYA_NORM_V1",
  "role": "<seller|buyer|landlord|tenant>",
  "listing_id": 12345,
  "traits": {
    "communication_channel":       { ... },
    "communication_frequency":     { ... },
    "responsiveness_expectation":  { ... },
    "negotiation_style":           { ... },
    "guidance_level":              { ... },
    "decision_making_style":       { ... },
    "transaction_pace":            { ... },
    "risk_tolerance":              { ... },
    "collaboration_style":         { ... },
    "representation_priorities":   { ... },
    "representation_philosophy":   { ... },
    "property_strategy_fit":       { ... }
  },
  "informational_context": { ... },
  "proxy_risk_flags": [ ... ]
}
```

### Key presence rules

| Key | Type | Always present? | Notes |
|---|---|---|---|
| `normalization_version` | string | Yes | Always `"BYA_NORM_V1"` for this schema version |
| `role` | string | Yes | Lowercase: `"seller"`, `"buyer"`, `"landlord"`, or `"tenant"` |
| `listing_id` | integer | Yes | Primary key of the listing record being normalized |
| `traits` | object | Yes | Always contains all 12 trait slots; slots may be missing or null (see Section 4) |
| `informational_context` | object | Yes | May be empty `{}` if no informational fields are populated; never absent |
| `proxy_risk_flags` | array | Yes | May be empty `[]` if no flags apply; never absent |

### The 12 trait slot keys within `traits`

The `traits` object always contains exactly these 12 keys, in this order, regardless of role:

1. `communication_channel`
2. `communication_frequency`
3. `responsiveness_expectation`
4. `negotiation_style`
5. `guidance_level`
6. `decision_making_style`
7. `transaction_pace`
8. `risk_tolerance`
9. `collaboration_style`
10. `representation_priorities`
11. `representation_philosophy`
12. `property_strategy_fit`

---

## 4. Per-Trait Slot Schema

### 4.1 Standard slot shape

Every trait slot conforms to the following base shape:

```json
{
  "value": <string | array | null>,
  "missing": <boolean>
}
```

- **`value`** — The normalized canonical value for this trait. A string for single-select traits; an array of strings for multi-select traits (`communication_channel`, `representation_priorities`). `null` when the consumer did not answer the field or when the field is structurally absent for this role.
- **`missing`** — `true` when the consumer's role has **no raw field** that maps to this trait (a structural gap, not an unanswered question). `false` in all other cases, even when `value` is `null`.

### 4.2 Three-state value model

The combination of `value` and `missing` encodes three distinct states that **must not be collapsed**:

| State | `value` | `missing` | Meaning |
|---|---|---|---|
| **Answered** | `"balanced"` (or non-null) | `false` | The consumer answered this field; `value` holds the normalized canonical string |
| **Skipped** | `null` | `false` | A raw field exists for this trait in this role, but the consumer left it blank or unanswered |
| **Absent** | `null` | `true` | No raw field exists for this trait in this role; the question was never asked |

A downstream scoring layer must treat **Skipped** and **Absent** differently. A skipped field can reasonably be treated as "no preference" and scored conservatively; an absent field means the role's form architecture never collected this dimension and no inference is possible.

### 4.3 Documented slot exceptions

Three slots carry additional sub-keys beyond the standard `value` / `missing` pair. These sub-keys are **conditionally present** — they appear only when the role and data conditions described below are met.

#### 4.3.1 `collaboration_style` — Buyer only: `showing_format_preference`

For Buyer listings, the raw key `communication_frequency` stores showing and meeting format preference (In-Person Only, Virtual Tours Accepted, Agent Pre-Screens for Me, Flexible / No Preference) despite its misleading key name. This data maps to `collaboration_style`, not to `communication_frequency`. To preserve this secondary signal alongside the primary collaboration style value (drawn from `preferred_agent_working_style`), the `collaboration_style` slot carries an optional `showing_format_preference` sub-key for Buyer payloads.

```json
"collaboration_style": {
  "value": "responsive_partner",
  "missing": false,
  "showing_format_preference": "In-Person Only"
}
```

`showing_format_preference` is absent from Seller, Landlord, and Tenant payloads. Its absence must not be treated as an error by consumers of the payload.

#### 4.3.2 `representation_philosophy` — Seller only: `past_agent_experience`

For Seller listings, the raw key `past_agent_experience` (First Time Working with an Agent, Positive Experience, Negative Experience, Mixed Experience) is the primary raw field feeding this trait's `value`. To preserve the original raw value for audit, explanation, and future agent-side matching, the slot carries an optional `past_agent_experience` sub-key that echoes the raw string before crosswalk normalization.

```json
"representation_philosophy": {
  "value": "positive_prior_experience",
  "missing": false,
  "past_agent_experience": "Positive Experience with Past Agent(s)"
}
```

`past_agent_experience` is absent from Buyer, Landlord, and Tenant payloads. For those roles, `representation_philosophy` will be `{ "value": null, "missing": true }` unless Phase C introduces agent-side fields that inform this trait for other roles.

#### 4.3.3 `property_strategy_fit` — Landlord only, when `tenant_type_preference` is present: `proxy_risk_flags`

When a Landlord listing has a non-null `tenant_type_preference` value, the `property_strategy_fit` slot carries an embedded `proxy_risk_flags` sub-key. This in-slot array mirrors the corresponding entry in the top-level `proxy_risk_flags` array (see Section 6), providing consumers the same flag in context at the trait level.

```json
"property_strategy_fit": {
  "value": "long_term_stable_tenant",
  "missing": false,
  "proxy_risk_flags": [
    {
      "field": "tenant_type_preference",
      "trait": "property_strategy_fit",
      "reason": "tenant_type_preference includes options that may correlate with protected-class characteristics. Scoring use is restricted to agent stated specialization; this value must never weight or penalize agents on a demographic basis."
    }
  ]
}
```

`proxy_risk_flags` is absent from this slot for Seller, Buyer, and Tenant payloads, and for Landlord payloads where `tenant_type_preference` is null or unanswered.

---

## 5. The `informational_context` Bag

The `informational_context` object carries fields that provide human-readable context about the listing but are **not trait values and must never be used as scoring inputs**. These fields travel alongside the trait payload so that explanation layers and UI consumers have full context, but they are architecturally separated from the `traits` object to make the scoring boundary explicit and machine-enforceable.

All keys in `informational_context` are `null` when the consumer did not populate that field. Keys that do not apply to the current role are **absent** from the bag (not present as `null`).

### 5.1 Seller `informational_context` fields

| Key in bag | Source raw sub-key | Type | Description |
|---|---|---|---|
| `post_sale_plan` | `post_sale_plan` | string\|null | What the seller plans to do after closing |
| `target_sale_timeline` | `target_sale_timeline` | string\|null | Free-text target timeline (e.g., "30–60 days") |
| `showing_availability` | `showing_availability` | array\|null | Days and times property showings are available |
| `open_house_preference` | `open_house_preference` | string\|null | Consumer's open-house preference |
| `additional_compatibility_notes` | `additional_compatibility_notes` | string\|null | Free-text notes the consumer added |
| `what_did_not_work_before` | `what_did_not_work_before` | string\|null | Specific negative past-agent behaviours to avoid |
| `additional_decision_makers` | `additional_decision_makers` | string\|null | Other parties involved in the selling decision |
| `primary_transaction_goal_other` | `primary_transaction_goal_other` | string\|null | Free-text expansion when primary goal = "Other" |
| `qualities_most_important` | `qualities_most_important` | array\|null | Agent personal qualities valued (multi-select) |
| `willing_to_negotiate_on` | `willing_to_negotiate_on` | array\|null | Transaction elements open to negotiation (multi-select) |
| `firm_on_price` | `firm_on_price` | string\|null | Seller's price firmness stance |

### 5.2 Buyer `informational_context` fields

| Key in bag | Source raw sub-key | Type | Description |
|---|---|---|---|
| `availability_windows` | `availability_windows` | string\|null | Free-text best times to reach the buyer |
| `deal_breakers` | `deal_breakers` | string\|null | Free-text non-negotiable requirements |
| `additional_compatibility_notes` | `additional_compatibility_notes` | string\|null | Free-text notes the consumer added |
| `primary_transaction_goal_other` | `primary_transaction_goal_other` | string\|null | Free-text expansion when primary goal = "Other" |
| `representation_priorities_other` | `representation_priorities_other` | string\|null | Free-text expansion when "Other" included in priorities |
| `preferred_agent_working_style_other` | `preferred_agent_working_style_other` | string\|null | Free-text expansion when working style = "Other" |

### 5.3 Landlord `informational_context` fields

| Key in bag | Source raw sub-key | Type | Description |
|---|---|---|---|
| `additional_representation_notes` | `additional_representation_notes` | string\|null | Free-text notes the consumer added |
| `primary_leasing_goal_other` | `primary_leasing_goal_other` | string\|null | Free-text expansion when leasing goal = "Other" |
| `tenant_type_preference_other` | `tenant_type_preference_other` | string\|null | Free-text expansion when tenant type = "Other" |
| `lease_duration_preference` | `lease_duration_preference` | string\|null | Preferred lease length (strategic context, not scored) |
| `concessions_willingness` | `concessions_willingness` | string\|null | Openness to tenant incentives (strategic context, not scored) |
| `lease_terms_flexibility` | `lease_terms_flexibility` | string\|null | Flexibility on lease terms (strategic context, not scored) |

### 5.4 Tenant `informational_context` fields

| Key in bag | Source raw sub-key | Type | Description |
|---|---|---|---|
| `preferred_contact_time_of_day` | `preferred_contact_method` | string\|null | Preferred time of day to be reached (remapped from the misleadingly named raw key; time-of-day preference is scheduling convenience, not a compatibility trait) |
| `most_important_agent_traits` | `most_important_agent_traits` | array\|null | Agent personal qualities valued most (multi-select) |
| `concerns_or_barriers` | `concerns_or_barriers` | string\|null | Free-text rental-search concerns or circumstances |
| `additional_compatibility_notes` | `additional_compatibility_notes` | string\|null | Free-text notes the consumer added |
| `other_primary_rental_goal` | `other_primary_rental_goal` | string\|null | Free-text expansion when rental goal = "Other" |
| `other_representation_priorities` | `other_representation_priorities` | string\|null | Free-text expansion when "Other" included in priorities |
| `other_timeline_urgency` | `other_timeline_urgency` | string\|null | Free-text expansion when timeline = "Other" |
| `other_communication_style` | `other_communication_style` | string\|null | Free-text expansion when communication style = "Other" |
| `other_most_important_agent_traits` | `other_most_important_agent_traits` | string\|null | Free-text expansion when "Other" included in traits |
| `other_desired_level_of_agent_involvement` | `other_desired_level_of_agent_involvement` | string\|null | Free-text expansion when involvement = "Other" |

---

## 6. The `proxy_risk_flags` Array

The top-level `proxy_risk_flags` array enumerates every field in the current payload whose inclusion in compatibility scoring requires special governance treatment due to Fair Housing proximity risk. Each entry is an object with three required keys.

### Schema for a single flag object

```json
{
  "field": "<raw_sub_key_name>",
  "trait": "<normalized_trait_key>",
  "reason": "<plain-language governance constraint>"
}
```

| Key | Type | Description |
|---|---|---|
| `field` | string | The raw consumer-side sub-key name that carries the risk |
| `trait` | string | The normalized trait slot this field contributes to |
| `reason` | string | Plain-language statement of the governance constraint that scoring and explanation layers must enforce |

### Currently flagged fields

At `BYA_NORM_V1`, one field is flagged:

```json
"proxy_risk_flags": [
  {
    "field": "tenant_type_preference",
    "trait": "property_strategy_fit",
    "reason": "tenant_type_preference includes options (Individual / Family, Young Professionals, Students, Corporate / Relocation, Small Business, Retail Business, Office Tenant) that may correlate with protected-class characteristics under the Fair Housing Act. Scoring use is restricted to matching an agent's stated commercial-versus-residential tenant specialization. This field must never be used to weight, penalize, or filter agents on the basis of which demographic group they serve."
  }
]
```

When no flags apply to a payload (all roles except Landlord with a populated `tenant_type_preference`, and all Landlord payloads where `tenant_type_preference` is null or unanswered), the top-level array is empty:

```json
"proxy_risk_flags": []
```

The array is always present. A missing `proxy_risk_flags` key is a malformed payload and must be rejected by consumers.

---

## 7. Worked Example Payloads

Each example represents a plausible partially-filled consumer submission. They are illustrative only — canonical option values shown here are normalized identifiers, not raw display strings. Real crosswalk tables are defined in `docs/BIDYOURAGENT_COMPATIBILITY_TRAIT_DESIGN.md` Section 6.

---

### 7.1 Seller — Partial Submission

**Scenario:** A seller completed all required fields and several optional ones. They skipped `decision_making_style` and left `responsiveness_expectation` answered. `risk_tolerance` is absent because no Seller raw field exists for this trait.

```json
{
  "normalization_version": "BYA_NORM_V1",
  "role": "seller",
  "listing_id": 4821,
  "traits": {
    "communication_channel": {
      "value": ["phone_call", "text_sms"],
      "missing": false
    },
    "communication_frequency": {
      "value": "frequent_proactive",
      "missing": false
    },
    "responsiveness_expectation": {
      "value": "within_few_hours",
      "missing": false
    },
    "negotiation_style": {
      "value": "balanced_fair",
      "missing": false
    },
    "guidance_level": {
      "value": "moderately_involved",
      "missing": false
    },
    "decision_making_style": {
      "value": null,
      "missing": false
    },
    "transaction_pace": {
      "value": "somewhat_flexible",
      "missing": false
    },
    "risk_tolerance": {
      "value": null,
      "missing": true
    },
    "collaboration_style": {
      "value": "proactive_initiative",
      "missing": false
    },
    "representation_priorities": {
      "value": ["market_expertise", "strong_negotiator", "high_communication"],
      "missing": false
    },
    "representation_philosophy": {
      "value": "positive_prior_experience",
      "missing": false,
      "past_agent_experience": "Positive Experience with Past Agent(s)"
    },
    "property_strategy_fit": {
      "value": "maximum_sale_price",
      "missing": false
    }
  },
  "informational_context": {
    "post_sale_plan": "Purchasing Another Property",
    "target_sale_timeline": "60–90 days",
    "showing_availability": ["Weekday Afternoons", "Weekend Mornings", "Weekend Afternoons"],
    "open_house_preference": "Open to It",
    "additional_compatibility_notes": "We have two small children so advance notice for showings is appreciated.",
    "what_did_not_work_before": null,
    "additional_decision_makers": "Spouse",
    "primary_transaction_goal_other": null,
    "qualities_most_important": ["Honesty & Transparency", "Proactivity"],
    "willing_to_negotiate_on": ["Closing Costs", "Possession Date"],
    "firm_on_price": "Somewhat — Open to Reasonable Offers"
  },
  "proxy_risk_flags": []
}
```

**What to observe:**
- `decision_making_style`: `value: null, missing: false` — the field exists for Seller but the consumer did not answer it (skipped state).
- `risk_tolerance`: `value: null, missing: true` — no Seller raw field maps to this trait; structurally absent.
- `representation_philosophy` carries the `past_agent_experience` sub-key alongside its normalized `value`.
- `proxy_risk_flags` is present but empty.

---

### 7.2 Buyer — Fully Populated

**Scenario:** A buyer answered every field available to their role. `responsiveness_expectation` is absent because no Buyer raw field exists for this trait.

```json
{
  "normalization_version": "BYA_NORM_V1",
  "role": "buyer",
  "listing_id": 7034,
  "traits": {
    "communication_channel": {
      "value": ["email", "text_message"],
      "missing": false
    },
    "communication_frequency": {
      "value": "regular_every_few_days",
      "missing": false
    },
    "responsiveness_expectation": {
      "value": null,
      "missing": true
    },
    "negotiation_style": {
      "value": "firm_but_fair",
      "missing": false
    },
    "guidance_level": {
      "value": "high_guided_throughout",
      "missing": false
    },
    "decision_making_style": {
      "value": "careful_deliberate",
      "missing": false
    },
    "transaction_pace": {
      "value": "somewhat_flexible",
      "missing": false
    },
    "risk_tolerance": {
      "value": "moderate",
      "missing": false
    },
    "collaboration_style": {
      "value": "responsive_partner",
      "missing": false,
      "showing_format_preference": "In-Person Only"
    },
    "representation_priorities": {
      "value": ["price_negotiation", "contract_protection", "communication_updates"],
      "missing": false
    },
    "representation_philosophy": {
      "value": null,
      "missing": true
    },
    "property_strategy_fit": {
      "value": "primary_residence",
      "missing": false
    }
  },
  "informational_context": {
    "availability_windows": "Weekday evenings after 6pm and weekends",
    "deal_breakers": "Must have at least 3 bedrooms and a two-car garage.",
    "additional_compatibility_notes": null,
    "primary_transaction_goal_other": null,
    "representation_priorities_other": null,
    "preferred_agent_working_style_other": null
  },
  "proxy_risk_flags": []
}
```

**What to observe:**
- `responsiveness_expectation`: `missing: true` — no Buyer raw field maps to this trait.
- `representation_philosophy`: `missing: true` — no Buyer raw field maps to this trait.
- `collaboration_style` carries the Buyer-only `showing_format_preference` sub-key.
- `risk_tolerance` is present for Buyer (`moderate`).
- Several `informational_context` keys are `null` (consumer left them blank).

---

### 7.3 Landlord — Proxy Risk Flag Active, Structural Gaps Present

**Scenario:** A landlord completed the required fields and several optional ones, including `tenant_type_preference`. `decision_making_style` and `transaction_pace` are absent because no Landlord raw fields exist for these traits.

```json
{
  "normalization_version": "BYA_NORM_V1",
  "role": "landlord",
  "listing_id": 2290,
  "traits": {
    "communication_channel": {
      "value": "phone_calls_preferred",
      "missing": false
    },
    "communication_frequency": {
      "value": "weekly_check_ins",
      "missing": false
    },
    "responsiveness_expectation": {
      "value": "same_business_day",
      "missing": false
    },
    "negotiation_style": {
      "value": "collaborative_win_win",
      "missing": false
    },
    "guidance_level": {
      "value": "minimal_involvement",
      "missing": false
    },
    "decision_making_style": {
      "value": null,
      "missing": true
    },
    "transaction_pace": {
      "value": null,
      "missing": true
    },
    "risk_tolerance": {
      "value": "moderate_standard_criteria",
      "missing": false
    },
    "collaboration_style": {
      "value": "proactive_assertive",
      "missing": false
    },
    "representation_priorities": {
      "value": ["tenant_screening_vetting", "lease_negotiation", "market_pricing_guidance"],
      "missing": false
    },
    "representation_philosophy": {
      "value": null,
      "missing": true
    },
    "property_strategy_fit": {
      "value": "long_term_stable_tenant",
      "missing": false,
      "proxy_risk_flags": [
        {
          "field": "tenant_type_preference",
          "trait": "property_strategy_fit",
          "reason": "tenant_type_preference includes options that may correlate with protected-class characteristics under the Fair Housing Act. Scoring use is restricted to matching an agent's stated commercial-versus-residential tenant specialization. This field must never be used to weight, penalize, or filter agents on the basis of which demographic group they serve."
        }
      ]
    }
  },
  "informational_context": {
    "additional_representation_notes": "Property is a 4-unit residential building. Need someone with multi-unit leasing experience.",
    "primary_leasing_goal_other": null,
    "tenant_type_preference_other": null,
    "lease_duration_preference": "1 Year",
    "concessions_willingness": "Open to Minor Concessions",
    "lease_terms_flexibility": "Somewhat Flexible"
  },
  "proxy_risk_flags": [
    {
      "field": "tenant_type_preference",
      "trait": "property_strategy_fit",
      "reason": "tenant_type_preference includes options that may correlate with protected-class characteristics under the Fair Housing Act. Scoring use is restricted to matching an agent's stated commercial-versus-residential tenant specialization. This field must never be used to weight, penalize, or filter agents on the basis of which demographic group they serve."
    }
  ]
}
```

**What to observe:**
- `decision_making_style` and `transaction_pace`: both `missing: true` — no Landlord raw fields exist for either trait.
- `representation_philosophy`: `missing: true` — no Landlord raw field maps to this trait.
- `property_strategy_fit` carries the embedded `proxy_risk_flags` sub-key because `tenant_type_preference` was populated.
- The top-level `proxy_risk_flags` array echoes the same flag for payload-level discoverability.
- `communication_channel` for Landlord is a single string (not an array) because the Landlord raw field is a single-select.

---

### 7.4 Tenant — Multiple Structural Gaps, Sparse Optional Fields

**Scenario:** A tenant answered required fields but skipped most optional fields. `responsiveness_expectation` and `risk_tolerance` are absent because no Tenant raw fields exist for these traits.

```json
{
  "normalization_version": "BYA_NORM_V1",
  "role": "tenant",
  "listing_id": 9155,
  "traits": {
    "communication_channel": {
      "value": "text_sms",
      "missing": false
    },
    "communication_frequency": {
      "value": "every_few_days",
      "missing": false
    },
    "responsiveness_expectation": {
      "value": null,
      "missing": true
    },
    "negotiation_style": {
      "value": "collaborative_mutually_beneficial",
      "missing": false
    },
    "guidance_level": {
      "value": "mostly_delegated",
      "missing": false
    },
    "decision_making_style": {
      "value": null,
      "missing": false
    },
    "transaction_pace": {
      "value": "within_30_days",
      "missing": false
    },
    "risk_tolerance": {
      "value": null,
      "missing": true
    },
    "collaboration_style": {
      "value": "highly_proactive",
      "missing": false
    },
    "representation_priorities": {
      "value": ["neighborhood_location", "budget_management", "speed_of_placement"],
      "missing": false
    },
    "representation_philosophy": {
      "value": null,
      "missing": true
    },
    "property_strategy_fit": {
      "value": "find_long_term_home",
      "missing": false
    }
  },
  "informational_context": {
    "preferred_contact_time_of_day": "Evening",
    "most_important_agent_traits": ["Responsiveness", "Local Expertise"],
    "concerns_or_barriers": "I have a large dog and have had difficulty finding pet-friendly rentals in the past.",
    "additional_compatibility_notes": null,
    "other_primary_rental_goal": null,
    "other_representation_priorities": null,
    "other_timeline_urgency": null,
    "other_communication_style": null,
    "other_most_important_agent_traits": null,
    "other_desired_level_of_agent_involvement": null
  },
  "proxy_risk_flags": []
}
```

**What to observe:**
- `responsiveness_expectation` and `risk_tolerance`: both `missing: true` — no Tenant raw fields exist for either trait.
- `representation_philosophy`: `missing: true` — no Tenant raw field maps to this trait.
- `decision_making_style`: `value: null, missing: false` — the field exists for Tenant but this consumer skipped it (skipped state, distinct from absent).
- `preferred_contact_time_of_day` in `informational_context` is remapped from the raw Tenant key `preferred_contact_method`, which stores time-of-day preference data despite its misleading key name (see `docs/BIDYOURAGENT_COMPATIBILITY_TRAIT_DESIGN.md` Section 7 for the full inconsistency resolution).
- `communication_channel` for Tenant is a single string (not an array) because the Tenant raw field `communication_style` is a single-select.

---

## 8. Governance Rules at the Normalization Layer

The following four rules apply specifically at the normalization layer — i.e., to the engine that produces the payload defined by this document. They are restated from the upstream governance documents at `docs/BIDYOURAGENT_COMPATIBILITY_AUDIT.md` Section 15 and `docs/BIDYOURAGENT_COMPATIBILITY_TRAIT_DESIGN.md` Section 11 with the specific implications that apply here.

**Rule 1 — Role-branch first.** The normalization engine must inspect the `role` of the source record before reading any raw sub-key. The same key name stores semantically different data depending on the role. Specifically: `communication_style` routes to `communication_frequency` for Seller and Buyer, and to `communication_channel` for Landlord and Tenant; `preferred_contact_method` routes to `communication_channel` for Seller and Buyer, to `communication_frequency` for Landlord, and to `informational_context.preferred_contact_time_of_day` (never a trait) for Tenant; Buyer `communication_frequency` routes to `collaboration_style`, not to `communication_frequency`. A normalization engine that reads sub-keys without first branching by role will silently produce incorrect trait assignments.

**Rule 2 — Canonical identifiers only.** The `value` in every trait slot must be a canonical normalized identifier drawn from the crosswalk tables in `docs/BIDYOURAGENT_COMPATIBILITY_TRAIT_DESIGN.md` Section 6. Raw display strings (e.g., `"Balanced — Fair & Reasonable"`) must never appear as `value`. Downstream scoring layers depend on stable, role-neutral identifiers — raw strings differ by role and will break cross-role comparison. The `past_agent_experience` sub-key on `representation_philosophy` and the `showing_format_preference` sub-key on `collaboration_style` are the only sanctioned locations for raw strings in the payload.

**Rule 3 — Missing ≠ null ≠ empty.** The three-state value model defined in Section 4.2 is not optional. An absent field (`missing: true, value: null`) and an unanswered field (`missing: false, value: null`) are different facts and must be encoded differently. An empty array (`value: [], missing: false`) would indicate a multi-select field was answered with zero selections, which is also distinct from a null. Collapsing any of these states loses information that scoring and explanation layers require to behave correctly.

**Rule 4 — Informational fields travel alongside but are never trait values.** Every field enumerated in Section 5 must be placed in `informational_context` only. No field in `informational_context` may appear as the primary `value` of any trait slot (with the sole exceptions of `past_agent_experience` and `showing_format_preference`, which appear as named sub-keys on their respective slots for secondary traceability, not as the primary trait value). If a future revision of this schema promotes an informational field to trait status, the `normalization_version` constant must be incremented and this document must be updated before any engine produces payloads under the new shape.

---

*End of document. This document is read-only. No code, schema, migration, route, controller, Blade, Livewire, config, or database file was created or modified to produce it.*
