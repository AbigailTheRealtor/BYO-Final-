# BidYourAgent — Milestone 7: Narrative Readiness Plan

**Document Status:** Read-only governance document. No code, schema, migration, route, controller, Blade, Livewire, config, database file, AI prompt, API integration, or production logic was created or modified to produce this document. No implementation occurs in this milestone.
**Document Date:** 2026-05-29
**Milestone:** 7 — Narrative Readiness Plan
**Preceding Milestone:** Phase H — Implementation Readiness (`docs/BIDYOURAGENT_COMPATIBILITY_PHASE_H_IMPLEMENTATION_READINESS.md`)
**Succeeding Milestone:** 8 — Deterministic Template Service (blocked on this document's rules being formally accepted)

---

## Table of Contents

1. [Purpose of the Narrative Layer](#section-1--purpose-of-the-narrative-layer)
2. [Allowed Inputs](#section-2--allowed-inputs)
3. [Allowed Outputs](#section-3--allowed-outputs)
4. [Prohibited Outputs](#section-4--prohibited-outputs)
5. [Fair Housing and Protected-Class Safeguards](#section-5--fair-housing-and-protected-class-safeguards)
6. [Recommendation Boundary](#section-6--recommendation-boundary)
7. [Explainability and Auditability Rules](#section-7--explainability-and-auditability-rules)
8. [Future Deterministic Template Architecture](#section-8--future-deterministic-template-architecture)
9. [AI Boundary](#section-9--ai-boundary)
10. [Human Review Requirement](#section-10--human-review-requirement)
11. [Consumer-Facing Disclaimer Draft](#section-11--consumer-facing-disclaimer-draft)
12. [Future Implementation Sequence](#section-12--future-implementation-sequence)

---

## Section 1 — Purpose of the Narrative Layer

### 1.1 What the Narrative Layer Is

The BYA Narrative Layer is a future system that converts the structured, machine-readable output of the `BYA_EXPLAIN_V1` explainability payload — combined with the `consumer` and `agent` normalized values carried by the upstream `BYA_ALIGN_V1` payload — into plain-language sentences that a consumer can read without any knowledge of the underlying compatibility system.

`BYA_EXPLAIN_V1` is the versioned structure produced by `ByaCompatibilityExplanationService`. For each of the 12 comparison dimensions, it contains: a `relationship` value (passthrough from the comparison layer), an `alignment_category` (the `BYA_ALIGN_V1` categorization), an `explanation_type` (the narrative type bucket), and an `explanation_key` (the unique template lookup key for that dimension and alignment combination). These four fields are the sole structured outputs of `ByaCompatibilityExplanationService`. The `consumer` and `agent` normalized values needed to fill template placeholders are carried by the upstream `BYA_ALIGN_V1` payload and are available alongside `BYA_EXPLAIN_V1` when the narrative layer runs.

The narrative layer uses the `explanation_key` from `BYA_EXPLAIN_V1` to select a pre-approved sentence template from the template library, and fills that template's placeholders with the `consumer` and `agent` values from `BYA_ALIGN_V1`. The result is a sentence such as: "You indicated a preference for daily contact; this agent describes their standard practice as proactive daily or near-daily updates."

The narrative layer does not produce alignment categories, explanation keys, advisory labels, rankings, recommendations, or decisions of any kind. It is strictly a translation system — structured keys in, plain-language sentences out.

### 1.2 Why a Dedicated Narrative Layer Is Necessary

The `BYA_EXPLAIN_V1` payload is designed for system-to-system use. Its `explanation_key` values (e.g., `communication_frequency_full_alignment`) and `explanation_type` values (e.g., `alignment`, `difference`) are not consumer-legible on their own. A consumer reading raw payload output would encounter machine identifiers, not meaningful guidance.

The narrative layer bridges this gap by applying a fixed, governance-reviewed template map: given an `explanation_key`, the template library returns an approved sentence pattern, which is then filled with the `consumer` and `agent` normalized values from `BYA_ALIGN_V1`. The result is a legible sentence that describes what the comparison found — and nothing more.

### 1.3 What the Narrative Layer Is Not

The narrative layer is not:

- A comparison system. It does not compare consumer and agent values or produce `relationship` values.
- A categorization system. It does not produce, modify, or reinterpret `alignment_category` or `explanation_type` values.
- A ranking system. It does not order, rank, sort, or prioritize agents relative to one another.
- A recommendation system. It does not advise a consumer to hire, prefer, contact, or avoid any agent.
- An AI system. The baseline narrative layer is a deterministic template system. Section 9 defines the conditions under which AI may be introduced in a future milestone; no AI is present in the Milestone 8 template implementation.
- A decision-making system. Final hiring decisions remain entirely with the consumer.

### 1.4 Relationship to Implemented System Components

The narrative layer is downstream from the following implemented system components. The pipeline runs left to right; each layer consumes the output of the preceding layer.

| Version Key | Service | Output |
|---|---|---|
| `BYA_NORM_V1` | `ByaNormalizationService` | Normalized consumer profile — `traits` array with one key per dimension |
| `BYA_AGENT_NORM_V1` | `ByaAgentResponseNormalizationService` | Normalized agent profile — `traits` array with one key per dimension |
| `BYA_COMP_V1` | `ByaCompatibilityComparisonService` | Per-dimension comparison — `{consumer, agent, relationship}` where `relationship` is `same`, `similar`, `different`, or `unknown` |
| `BYA_ALIGN_V1` | `ByaCompatibilityAlignmentService` | Per-dimension alignment — `{relationship, alignment_category, consumer, agent}` — plus `advisory_label` summary |
| `BYA_EXPLAIN_V1` | `ByaCompatibilityExplanationService` | Per-dimension explanation keys — `{relationship, alignment_category, explanation_type, explanation_key}` — plus `explanation_type_counts` summary |

The narrative layer is the first layer that produces human-readable output. Every sentence it generates must trace to a specific `BYA_EXPLAIN_V1` dimension entry and the corresponding `BYA_ALIGN_V1` `consumer` / `agent` values. No sentence may introduce reasoning, context, or content not derivable from these two authorized input sources.

---

## Section 2 — Allowed Inputs

### 2.1 Governing Principle

The narrative layer may consume only the inputs enumerated in this section. Any data source not explicitly listed here is prohibited. The allowed input set is intentionally narrow to ensure that no protected-class, demographic, or externally sourced data can enter the narrative pipeline through any pathway.

### 2.2 BYA_EXPLAIN_V1 Keys and Types

The narrative layer is authorized to read the following keys from `BYA_EXPLAIN_V1`. These are the complete fields emitted by `ByaCompatibilityExplanationService`.

**Top-level payload keys:**

| Key | Type | Description |
|---|---|---|
| `explanation_version` | string | Version identifier of the explanation payload. Value is always `BYA_EXPLAIN_V1`. |
| `alignment_version` | string or null | Version identifier of the upstream `BYA_ALIGN_V1` payload this explanation was derived from. |
| `dimensions` | array | Keyed array of per-dimension explanation entries, indexed by dimension name. |
| `summary.total_dimensions` | int | Total number of dimensions present in the payload. |
| `summary.explained_dimensions` | int | Number of dimensions whose `alignment_category` is not `insufficient_data`. |
| `summary.explanation_type_counts` | array | Count of each `explanation_type` value across all dimensions. Keys: `alignment`, `difference`, `adjacent`, `neutral`, `insufficient_data`. |

**Per-dimension entry keys** (one entry per dimension under `dimensions`):

| Key | Type | Description |
|---|---|---|
| `relationship` | string or null | The raw comparison relationship value from `BYA_COMP_V1`, passed through `BYA_ALIGN_V1`. Values: `same`, `similar`, `different`, `unknown`, or `null`. |
| `alignment_category` | string | The `BYA_ALIGN_V1` alignment category assigned to this dimension. See Section 2.3. |
| `explanation_type` | string | The narrative type bucket mapped from `alignment_category`. See Section 2.4. |
| `explanation_key` | string | The template lookup key for this dimension and alignment combination. Format: `{dimension_name}_{alignment_category}` — e.g., `communication_frequency_full_alignment`. |

### 2.3 BYA_ALIGN_V1 Authorized Fields

Because `ByaCompatibilityExplanationService` drops the `consumer` and `agent` fields when producing `BYA_EXPLAIN_V1`, the narrative layer must also consume the upstream `BYA_ALIGN_V1` payload to access the normalized consumer and agent values needed to fill template placeholders.

The narrative layer is authorized to read the following per-dimension fields from `BYA_ALIGN_V1`:

| Key | Type | Description |
|---|---|---|
| `consumer` | mixed | The normalized consumer value for this dimension, as produced by `BYA_NORM_V1`. Used to fill template placeholders that show what the consumer indicated. |
| `agent` | mixed | The normalized agent value for this dimension, as produced by `BYA_AGENT_NORM_V1`. Used to fill template placeholders that show what the agent indicated. |

The narrative layer must not read any other field from `BYA_ALIGN_V1` beyond `consumer` and `agent` per dimension. In particular, it must not re-derive `alignment_category` values from `BYA_ALIGN_V1` summary counts, must not use the `advisory_label` summary value to alter narrative content, and must not read the `BYA_COMP_V1` payload directly.

### 2.4 BYA_ALIGN_V1 Alignment Categories

The `alignment_category` field in `BYA_EXPLAIN_V1` per-dimension entries contains one of the following values, which correspond to the authoritative `BYA_ALIGN_V1` alignment category vocabulary defined in `ByaCompatibilityAlignmentService::ALL_ALIGNMENT_CATEGORIES`.

| `alignment_category` Value | Description | Currently Reachable |
|---|---|---|
| `full_alignment` | Consumer and agent values match on this dimension (`relationship: same`). | Yes — Milestone 5+ |
| `partial_alignment` | Consumer and agent values partially overlap (`relationship: similar`). | Reserved — future milestone |
| `incompatible_alignment` | Consumer and agent values differ meaningfully (`relationship: different`). | Yes — Milestone 5+ |
| `insufficient_data` | No comparison was possible — one or both parties did not provide a value (`relationship: unknown` or unrecognized). | Yes — Milestone 5+ |
| `adjacent_compatibility` | Reserved for when a future milestone adds an `adjacent` relationship value to `BYA_COMP_V1`. | Not yet reachable |
| `neutral_compatibility` | Reserved for when a future milestone adds a `neutral` relationship value to `BYA_COMP_V1`. | Not yet reachable |

### 2.5 BYA_EXPLAIN_V1 Explanation Types

The `explanation_type` field in `BYA_EXPLAIN_V1` per-dimension entries contains one of the following values. This is the narrative type bucket derived from `alignment_category` by `ByaCompatibilityExplanationService::CATEGORY_TYPE_MAP`.

| `explanation_type` Value | Derived From | Description |
|---|---|---|
| `alignment` | `full_alignment` or `partial_alignment` | The comparison found alignment or partial overlap on this dimension. |
| `difference` | `incompatible_alignment` | The comparison found a meaningful difference on this dimension. |
| `adjacent` | `adjacent_compatibility` | Reserved for future use when adjacent compatibility is introduced. |
| `neutral` | `neutral_compatibility` | Reserved for future use when neutral compatibility is introduced. |
| `insufficient_data` | `insufficient_data` or unrecognized category | No comparison result was available for this dimension. |

The `explanation_type` is used by the narrative layer to select the general template category. The `explanation_key` is used to select the specific template entry. See Section 8.2.

### 2.6 BYA_COMP_V1 Relationship Values

The `relationship` field passed through from `BYA_COMP_V1` appears in each `BYA_EXPLAIN_V1` per-dimension entry. The narrative layer may read this field for context but must not use it to re-derive or override the `alignment_category` or `explanation_type` already computed by upstream layers. The complete vocabulary of `relationship` values currently emitted by `ByaCompatibilityComparisonService` is:

| `relationship` Value | Meaning |
|---|---|
| `same` | Consumer and agent provided identical or equivalent normalized values. |
| `similar` | Reserved — not emitted in current milestones; will become active when the comparison service adds similarity logic. |
| `different` | Consumer and agent provided values that do not match. |
| `unknown` | One or both parties did not provide a value for this dimension. |

### 2.7 Dimension Names

The narrative layer is authorized to reference all 12 comparison dimension names defined in `ByaCompatibilityComparisonService::DIMENSIONS`. These are the dimension names that appear as keys in the `BYA_EXPLAIN_V1` and `BYA_ALIGN_V1` `dimensions` arrays, and as the prefix segment of every `explanation_key`.

| # | Dimension Name | Profile Trait Source |
|---|---|---|
| 1 | `communication_style` | `communication_channel` (consumer and agent) |
| 2 | `communication_frequency` | `communication_frequency` (consumer and agent) |
| 3 | `decision_speed` | `transaction_pace` (consumer and agent) |
| 4 | `risk_tolerance` | `risk_tolerance` (consumer and agent) |
| 5 | `negotiation_style` | `negotiation_style` (consumer and agent) |
| 6 | `advisor_expectation` | `guidance_level` (consumer and agent) |
| 7 | `technology_preference` | Placeholder — no profile trait source currently. Always resolves to `relationship: unknown`. |
| 8 | `market_education_preference` | Placeholder — no profile trait source currently. Always resolves to `relationship: unknown`. |
| 9 | `property_search_involvement` | `collaboration_style` (consumer and agent) |
| 10 | `transaction_guidance_level` | `decision_making_style` (consumer and agent) |
| 11 | `availability_expectation` | `responsiveness_expectation` (consumer and agent) |
| 12 | `personality_style` | `representation_philosophy` (consumer and agent) |

Dimension names appear in narrative outputs only as human-readable labels (e.g., "Communication Frequency"), never as raw machine identifiers.

Dimensions 7 (`technology_preference`) and 8 (`market_education_preference`) always produce `alignment_category: insufficient_data` in current milestones because neither `BYA_NORM_V1` nor `BYA_AGENT_NORM_V1` currently emits the required trait keys. Templates for these dimensions must use the `insufficient_data` fallback form (see Section 8.4) and must not speculate about either party's values.

### 2.8 Prohibited Inputs

The following data categories are prohibited as narrative layer inputs under any circumstances:

- Age, gender, race, ethnicity, national origin, religion, familial status, disability status, sex, sexual orientation, source of income, or any other characteristic protected under the Fair Housing Act, the Equal Credit Opportunity Act, or applicable state law.
- Any field that names, encodes, or is likely to proxy a protected-class characteristic.
- Any field not included in the authorized inputs defined in Sections 2.2–2.6.
- Any field from `BYA_COMP_V1` other than the `relationship` value passed through to `BYA_EXPLAIN_V1`.
- Unstructured data from the web, social media, news sources, or any external system.
- Compensation, commission, referral fee, or pricing data of any kind.
- Behavioral tracking data, browsing history, click data, device identifiers, or IP-derived location data.
- Agent license numbers, brokerage affiliations, or business entity information.
- Any data not explicitly provided by the consumer or agent through the compatibility preference forms.

---

## Section 3 — Allowed Outputs

### 3.1 Governing Principle

The narrative layer may produce only the output types defined in this section. Every sentence in any permitted output must trace to a specific `BYA_EXPLAIN_V1` dimension entry and the corresponding `BYA_ALIGN_V1` `consumer` / `agent` values for that dimension. No sentence may introduce reasoning, context, or characterizations not present in these authorized inputs.

### 3.2 Alignment Observations

The narrative layer may produce plain-language observations describing what the comparison found for dimensions whose `explanation_type` is `alignment`. Alignment observations cover both `full_alignment` and `partial_alignment` alignment categories.

**Full alignment — example language:**
> "You indicated a preference for daily updates; this agent describes their standard practice as proactive daily or near-daily contact. Your communication frequency expectations appear to be well aligned."

**Partial alignment — example language:**
> "You indicated a preference for weekly check-ins; this agent describes their standard as bi-weekly contact with availability on request. Your preferences partially overlap on communication frequency."

### 3.3 Difference Observations

The narrative layer may produce plain-language observations for dimensions whose `explanation_type` is `difference`. These correspond to `incompatible_alignment` alignment categories and must be framed as factual descriptions of what each party indicated, not as negative assessments of the agent.

**Difference observation — example language:**
> "You indicated a preference for very rapid response times; this agent describes their standard response as within one business day. This is a dimension worth discussing directly before making a hiring decision."

**Difference observations may not:**
- State or imply that the agent is unsuitable, inferior, or unlikely to meet the consumer's needs.
- Use comparative language that functions as a ranking relative to other agents.
- Characterize the difference as a disqualifying factor.

### 3.4 Insufficient Information Observations

The narrative layer may produce a plain-language observation for dimensions whose `explanation_type` is `insufficient_data`. This covers cases where one or both parties provided no value for the dimension, as well as placeholder dimensions that have no profile trait source yet.

**Insufficient information — example language (consumer did not provide a value):**
> "You did not provide a preference for negotiation style. No comparison is available for this dimension."

**Insufficient information — example language (agent did not provide a value):**
> "This agent did not provide a response for negotiation style. No comparison is available for this dimension."

**Insufficient information — example language (placeholder dimension):**
> "This dimension is not yet supported and is not included in this compatibility summary."

Insufficient information observations must not speculate about what either party might have answered, infer values from other dimensions, or imply that the absence of data is negative for either party.

### 3.5 Neutral and Adjacent Observations (Reserved)

The narrative layer may produce neutral observations for dimensions whose `explanation_type` is `neutral` and adjacent observations for dimensions whose `explanation_type` is `adjacent`. These observation types are defined here for completeness but are not reachable in current milestones, since `adjacent_compatibility` and `neutral_compatibility` alignment categories are reserved pending future pipeline changes.

**Neutral observation — example language (reserved, future):**
> "Both you and this agent described compatible but distinct approaches for decision-making style. No meaningful difference was found on this dimension."

**Adjacent observation — example language (reserved, future):**
> "Your stated communication style and this agent's approach differ in emphasis but are generally compatible."

### 3.6 Summary Observations

The narrative layer may produce an overall summary observation that describes the pattern of explanation types across all dimensions at a high level. Summary observations must:

- Reference only what the per-dimension comparisons found.
- Be consistent with the advisory-only standard defined in Section 6.
- Not imply a ranking, recommendation, or fitness judgment.
- Not reproduce the `advisory_label` value from `BYA_ALIGN_V1` as if it were a score.

The `advisory_label` values emitted by `ByaCompatibilityAlignmentService` (`strong_alignment`, `broad_compatibility`, `notable_differences`, `insufficient_compatibility_data`) are machine-readable keys for system use. If the narrative layer references these concepts in a summary observation, it must translate them into plain-language advisory sentences using approved templates — not display the raw key values.

**Summary observation — example language:**
> "Across the dimensions where both you and this agent provided responses, several areas of alignment were found — particularly in communication frequency and advisor expectation. One area of notable difference was identified in availability expectation, which may be worth discussing directly."

---

## Section 4 — Prohibited Outputs

### 4.1 Complete Enumerated List

The following output types are explicitly prohibited. This list is exhaustive. Any output type not affirmatively permitted by Section 3 is also prohibited by this rule, regardless of whether it appears in this list.

**Hire, engage, or select language:**
- Any sentence that recommends, suggests, advises, or encourages the consumer to hire a specific agent.
- Any sentence containing "you should hire," "we recommend," "this agent is a good choice," "consider hiring," "we suggest," "best suited for you," or equivalent phrasing.
- Any sentence that advises the consumer to contact a specific agent on the basis of compatibility results.

**Ranking or superiority language:**
- Any sentence that ranks agents relative to one another.
- Any sentence that uses superlatives ("most compatible," "best match," "highest alignment," "top agent for you") or comparatives ("more compatible than," "better aligned than") in reference to a specific agent.
- Any sentence that implies a numerical or ordinal position (e.g., "this agent ranks third in compatibility").
- Any sentence that uses compatibility results to order a list of agents, even implicitly.

**Endorsement language:**
- Any sentence that endorses an agent's qualifications, competence, character, or professional reputation on the basis of compatibility results.
- Any sentence that uses compatibility results to vouch for an agent's trustworthiness or reliability.

**Disqualification language:**
- Any sentence that disqualifies, eliminates, or advises against hiring a specific agent.
- Any sentence containing "you should avoid," "not a good fit," "incompatible with your needs," "this agent is unlikely to meet your expectations," or equivalent phrasing.
- Any sentence that frames a `difference` explanation type as a reason not to hire an agent.

**Prediction language:**
- Any sentence that predicts how the working relationship will proceed.
- Any sentence that asserts a likelihood of success or failure in the transaction.
- Any sentence that claims an alignment result will lead to a specific outcome.

**Demographic or protected-class language:**
- Any sentence that references, implies, or characterizes any demographic attribute of the consumer or agent.
- Any sentence that uses phrasing such as "people like you," "consumers in your situation," "agents who work with your demographic," or any phrasing that segments consumers or agents by identity characteristics.
- Any sentence that uses neighborhood, geographic, or area-level characterizations that function as proxies for demographic attributes.

**Psychological profiling language:**
- Any sentence that characterizes a consumer's or agent's personality, emotional style, mental disposition, behavioral tendencies, or psychological profile, beyond what the consumer or agent explicitly stated in their compatibility preference form.

**Numeric compatibility scoring language:**
- Any sentence that presents compatibility as a numeric score, percentage, grade, or quantified rating.
- Any sentence such as "your compatibility score is 78%" or "this agent scored well."
- Any sentence that displays or interprets `explanation_type_counts` as a numeric compatibility rating.

**Advisory label display as a score:**
- Any sentence that displays the raw `advisory_label` value from `BYA_ALIGN_V1` as a consumer-facing label without translation into an approved plain-language template.

**AI or source misrepresentation:**
- Any sentence that misrepresents the source of an observation (e.g., implies human review when AI generated the sentence, or implies AI involvement when a deterministic template produced the sentence).

---

## Section 5 — Fair Housing and Protected-Class Safeguards

### 5.1 Governing Principle

The narrative layer operates in the context of real estate transactions regulated by the Fair Housing Act (42 U.S.C. §§ 3601–3619), the Equal Credit Opportunity Act, and applicable state and local fair housing laws. The narrative layer must not produce any output that constitutes, enables, or facilitates a violation of these laws, and must not produce any output that creates legal exposure for BidYourAgent or any party using the platform.

### 5.2 Full Enumerated List of Protected Classes

The following characteristics are protected under federal law, and many receive additional protection under state or local law. The narrative layer must not reference, infer, characterize, or proxy any of these characteristics in any output:

| Protected Class | Governing Authority |
|---|---|
| Race | Fair Housing Act |
| Color | Fair Housing Act |
| National Origin | Fair Housing Act |
| Religion | Fair Housing Act |
| Sex | Fair Housing Act |
| Familial Status (including presence of children under 18, pregnancy, adoption) | Fair Housing Act |
| Disability (physical or mental) | Fair Housing Act |
| Genetic information | State law (varies) |
| Sexual orientation | State law (varies; 24+ states and many localities) |
| Gender identity or expression | State law (varies) |
| Marital status | State law (varies) |
| Source of income (including housing vouchers / Section 8) | State law (varies) |
| Age (in credit-related contexts) | Equal Credit Opportunity Act |
| Military or veteran status | State law (varies) |
| Ancestry | State law (varies) |
| Immigration status | State law (varies) |

### 5.3 Prohibited Proxy Characteristics

The following characteristics are not legally designated protected classes in all jurisdictions but are well-documented proxies for the classes enumerated in Section 5.2. The narrative layer must not reference or imply any of these characteristics:

- Neighborhood name, subdivision name, zip code, or school district, when used in a way that correlates with race, national origin, or familial status.
- English language proficiency or fluency.
- Surname, given name, or name origin inferences.
- Credit history patterns that correlate with race or national origin.
- Criminal history, to the extent it functions as a proxy for race or national origin under applicable law.
- Tenant screening strictness or selectivity, when framed in a way that correlates with familial status, disability, race, or national origin.
- Transaction type or investment strategy, to the extent it correlates with demographic characteristics in a given market context.
- Occupancy patterns or household composition language that encodes familial status.
- Any characteristic that a reasonable fair housing compliance professional would identify as a proxy for a protected class in the real estate context.

### 5.4 Dimension-Specific Proxy Concerns

The following dimensions have been identified as carrying heightened proxy risk and are subject to additional safeguards at the template authoring and compliance review stages.

**`risk_tolerance`** — This dimension's normalized values (e.g., strict screening preferences in the landlord context) may correlate with protected-class characteristics depending on how consumer or agent values are phrased. Templates for `risk_tolerance` must be reviewed with particular care to confirm they do not amplify or endorse screening preferences that function as proxies for familial status, disability, race, or national origin. Per Phase H Section 1.1, a proxy-risk compliance analysis for this dimension is required before any template involving `risk_tolerance` comparison values is approved for consumer-visible use.

**`personality_style`** — This dimension derives from `representation_philosophy` in both profiles. Templates must not introduce psychological profiling language or characterize either party's values in ways that imply demographic identity.

No template for any dimension carrying heightened proxy risk may be added to the approved library until it has received human compliance review per Section 10.

### 5.5 Screening-Related Language Prohibition

Regardless of which dimension generated the underlying comparison, the narrative layer must never produce any sentence that:

- Characterizes a landlord's preference for tenant screening as a compatibility attribute.
- Implies that an agent's comfort with strict or flexible tenant screening is a positive or negative alignment signal.
- Uses language that could function as a mechanism for steering tenant applicants toward or away from agents or properties on the basis of any characteristic listed in Section 5.2 or Section 5.3.

### 5.6 Review and Remediation Obligation

Any narrative template draft that contains language ambiguously related to a protected class or proxy characteristic must be flagged for human compliance review (per Section 10) before being added to the approved template library. Templates must not be deployed pending review.

---

## Section 6 — Recommendation Boundary

### 6.1 The Advisory-Only Standard

The narrative layer is advisory and explanatory only. Its sole function is to translate structured comparison and alignment results into plain language that helps a consumer understand what the compatibility system found. The consumer's hiring decision remains entirely and exclusively theirs.

This principle is non-negotiable. No future milestone, feature request, product change, or performance optimization may authorize the narrative layer to cross from advisory into recommendatory behavior.

### 6.2 What Advisory Means in Practice

"Advisory" means the narrative layer:

- Describes what the comparison found for each dimension.
- Provides factual context about what each party indicated.
- Identifies dimensions where differences may warrant a direct conversation.
- Presents this information without directing, implying, or nudging the consumer toward any conclusion about which agent to hire.

Advisory observations inform. They do not guide, steer, persuade, or decide.

### 6.3 What Recommendatory Behavior Looks Like

The following behaviors are examples of recommendatory behavior that the narrative layer must never exhibit:

- Framing `alignment` explanation types as reasons to hire an agent.
- Framing `difference` explanation types as reasons to pass on an agent.
- Emphasizing certain dimensions over others in a way that functions as a hidden recommendation.
- Using summary language that cumulatively implies a hiring recommendation even if no single sentence explicitly recommends hiring.
- Ordering or presenting dimension-level summaries in a sequence designed to produce a favorable or unfavorable impression of a specific agent.

### 6.4 Final-Decision Locus Remains with the Consumer

The narrative layer must be designed so that a consumer who reads its output fully understands:

1. The output describes what two data sets found when compared — it does not assess agent quality or predict working relationship success.
2. Compatibility information is one input among many factors a consumer should consider, including agent qualifications, services offered, track record, compensation terms, and personal interaction.
3. The consumer, not the platform, makes the final hiring decision.
4. An `alignment` result does not guarantee a successful working relationship. A `difference` result does not disqualify an agent.

These principles must be reinforced by the consumer-facing disclaimer in Section 11 and must not be contradicted by any narrative output.

---

## Section 7 — Explainability and Auditability Rules

### 7.1 Full Traceability Requirement

Every sentence produced by the narrative layer must be traceable through the following deterministic pipeline:

```
BYA_NORM_V1 (consumer normalization)
  +
BYA_AGENT_NORM_V1 (agent normalization)
  ↓
BYA_COMP_V1 (comparison — relationship: same|similar|different|unknown)
  ↓
BYA_ALIGN_V1 (alignment — alignment_category + consumer + agent values)
  ↓
BYA_EXPLAIN_V1 (explanation keys — explanation_type + explanation_key)
  ↓
Narrative template lookup (explanation_key → approved template)
  ↓
Narrative sentence (template filled with consumer + agent values from BYA_ALIGN_V1)
```

No sentence may introduce a claim, characterization, or observation that cannot be derived from this chain for the specific listing-and-bid pair being described.

### 7.2 Sentence-Level Traceability Standard

The traceability requirement applies at the sentence level, not at the document level. For every sentence in a narrative output, it must be possible to identify:

- Which dimension the sentence describes.
- Which `explanation_key` from `BYA_EXPLAIN_V1` triggered the selection of the template used to produce the sentence.
- Which `consumer` and `agent` values from `BYA_ALIGN_V1` were used to fill the template placeholders.
- Which template identifier in the approved template library was used.

If any of these four identifiers cannot be specified for a sentence, the sentence must be removed from the output.

### 7.3 No Hidden Weighting

The narrative layer must not apply hidden weights to dimension outputs. Specifically:

- All dimensions present in `BYA_EXPLAIN_V1` must be treated equivalently unless the dimension is a placeholder with no profile trait source (in which case the `insufficient_data` fallback template applies).
- The narrative layer must not programmatically emphasize, de-emphasize, expand, or collapse any dimension's output based on undisclosed logic.
- If future milestones introduce consumer-configurable dimension priorities (where a consumer may mark certain dimensions as higher priority), those priorities must be visible to the consumer, stored in the audit record, and reflected accurately in the narrative output.

### 7.4 Audit Record Requirements

Every narrative output generation event must produce an audit record containing:

- The listing identifier and type (seller, buyer, landlord, or tenant).
- The bid identifier.
- The `BYA_EXPLAIN_V1` payload snapshot used (including all per-dimension entries: `relationship`, `alignment_category`, `explanation_type`, `explanation_key`).
- The `consumer` and `agent` values from `BYA_ALIGN_V1` used for each dimension's template placeholder fill.
- The template identifier from the approved template library selected for each dimension sentence.
- The `explanation_version` and `alignment_version` values in effect at the time of generation.
- A `generated_at` timestamp.
- Whether human review occurred before the output was displayed to the consumer (see Section 10).
- If AI is involved (per the conditions in Section 9): the AI moderation status, the moderation result, and the moderation timestamp.

Audit records must be retained for the duration of the associated listing and bid's active period and for a minimum of 24 months thereafter. Audit records must not be deleted while a corresponding listing or bid is active.

### 7.5 Administrator Inspectability

Audit records must be inspectable by platform administrators at any time without re-running the narrative generation process. Inspectability means:

- The stored `BYA_EXPLAIN_V1` snapshot and `BYA_ALIGN_V1` consumer/agent values are sufficient to reproduce the expected output using the template identifiers recorded in the audit entry.
- The administrator can confirm that the displayed output matches what the audit record would produce.
- No audit inspection requires access to AI model states, external services, or real-time computation.

### 7.6 Version Consistency

The `explanation_version` and `alignment_version` recorded in an audit entry must correspond to the versions active at the time of narrative generation. If any upstream version changes, existing audit records must retain the version identifiers under which they were generated, and the narrative layer must re-generate outputs only when explicitly triggered — not automatically.

---

## Section 8 — Future Deterministic Template Architecture

### 8.1 Design Principle

The baseline narrative layer, to be implemented beginning in Milestone 8, is a deterministic template system. "Deterministic" means that for any given combination of `explanation_key` (from `BYA_EXPLAIN_V1`) and `consumer` / `agent` values (from `BYA_ALIGN_V1`), the system produces the same output every time it is run, without variation, probabilism, or randomness. No AI, language model, or probabilistic generation is present in the Milestone 8 implementation.

### 8.2 Fixed Template Map Pattern

The template architecture uses a fixed template map: a lookup table indexed by `explanation_key`. Each `explanation_key` value uniquely identifies the dimension and alignment category combination, using the format `{dimension_name}_{alignment_category}` (e.g., `communication_frequency_full_alignment`, `negotiation_style_incompatible_alignment`).

For each `explanation_key`, the map contains an approved sentence template expressed as a string pattern with named placeholders for `{consumer_value}` and `{agent_value}`.

**Structural form of the template map:**

```
TemplateMap[explanation_key] → TemplateSentence(consumer_value, agent_value)
```

**Example entries (conceptual — not yet authored):**

| `explanation_key` | Template |
|---|---|
| `communication_frequency_full_alignment` | "You indicated a preference for {consumer_value}; this agent describes their standard as {agent_value}. Your communication frequency expectations appear to be well aligned." |
| `communication_frequency_incompatible_alignment` | "You indicated a preference for {consumer_value}; this agent describes their standard as {agent_value}. This difference in communication frequency may be worth discussing directly before making a hiring decision." |
| `communication_frequency_insufficient_data` | "No comparison is available for communication frequency." |

The `explanation_type` field in `BYA_EXPLAIN_V1` may be used to organize the template library into type buckets (`alignment`, `difference`, `adjacent`, `neutral`, `insufficient_data`) for authoring and review purposes, but the `explanation_key` is the definitive lookup key at runtime.

### 8.3 Template Map Constraints

Every template in the map must satisfy the following constraints before it is added to the approved template library:

1. It must reference only `{consumer_value}`, `{agent_value}`, and the human-readable dimension label as content. No other variables may be interpolated.
2. It must comply with all Section 4 prohibited output rules.
3. It must comply with all Section 5 fair housing safeguard rules.
4. It must comply with the advisory-only standard in Section 6.
5. The `{consumer_value}` and `{agent_value}` placeholders must produce a compliant sentence for every valid normalized value in the `BYA_NORM_V1` and `BYA_AGENT_NORM_V1` option sets for that dimension.
6. It must have passed human review per Section 10 before being admitted to the approved library.
7. It must have a unique template identifier recorded in the template registry, so that audit records can reference which template was used to produce each sentence.

### 8.4 Handling Missing Templates

If the template map does not contain an entry for an `explanation_key` encountered during narrative generation, the system must fall back to the `insufficient_data` template for that dimension rather than generating an improvised sentence. Falling back to an approved template is always preferable to generating unreviewed text.

This fallback rule also applies to `explanation_key` values containing the reserved categories `adjacent_compatibility` and `neutral_compatibility`: because those categories are not yet reachable in current milestones, no templates for them will exist at Milestone 8 launch. If they are encountered, the `insufficient_data` fallback applies until a future milestone adds approved templates for those types.

### 8.5 Service Not Yet Implemented

The deterministic template service (`BYA_NARRATIVE_V1` or equivalent) does not exist as of this document. No class, interface, method, route, controller, Blade component, or Livewire component implementing this service has been created. Milestone 8 is the earliest point at which implementation may begin, and only after the human review requirements in Section 10 are satisfied for an initial template set.

---

## Section 9 — AI Boundary

### 9.1 Current State: AI Is Not Authorized

As of Milestone 7, AI and large language models (LLMs) are not authorized for use in any component of the narrative layer. The Milestone 8 baseline implementation is a deterministic template system only. No AI-generated text, no LLM API calls, no embedding generation, and no probabilistic text synthesis may be introduced into the narrative pipeline at Milestone 8.

This prohibition is absolute for Milestone 8. It is not a preference — it is a governance rule.

### 9.2 Condition for Future AI Authorization

AI may be introduced into the narrative layer only after a future governance milestone explicitly authorizes it. That milestone must produce a written governance document that:

1. Identifies the specific component(s) of the narrative layer for which AI is authorized (e.g., fallback generation for dimensions without template coverage, natural language variation within a reviewed template class).
2. Defines the authorized AI inputs (which must be a subset of the allowed inputs in Section 2) and confirms that no prohibited inputs can reach the AI system through any pathway.
3. Defines the prohibited AI outputs (which must be at least as restrictive as Section 4) and specifies the moderation pipeline that enforces these prohibitions before any AI output reaches a consumer.
4. Confirms that the AI moderation pipeline has been reviewed by a qualified Fair Housing compliance professional.
5. Establishes the audit record requirements for AI-generated outputs (which must extend, not replace, the requirements in Section 7.4).
6. Defines the human review process for AI-generated output templates or prompt structures (which must meet or exceed the requirements in Section 10).
7. Identifies which future milestone implements this authorization and confirms it has not begun implementation before the governance document is complete.

No AI introduction may occur before items 1–7 above are documented and accepted.

### 9.3 Constraints If AI Is Ever Authorized

If a future governance milestone authorizes AI for a specific component of the narrative layer, the following constraints apply unconditionally:

- AI may not modify, override, or re-derive `alignment_category`, `explanation_type`, or `explanation_key` values. AI is downstream from the comparison and alignment pipeline; it does not participate in those layers.
- AI may not consume any input not present in the Section 2 authorized input set.
- AI may not produce any output listed in Section 4.
- Every AI-generated sentence must pass through a moderation pipeline before reaching a consumer.
- The moderation pipeline must be capable of detecting all Section 4 prohibited output types.
- AI-generated outputs must be labeled as AI-generated in audit records.
- AI model version and prompt version must be recorded in the audit record for every AI-generated output.
- A human review process must exist for reviewing and approving new AI prompt structures before they are deployed.
- AI must not be used for real-time generation in contexts where moderation latency would cause the output to bypass review.

### 9.4 Specific AI Prohibitions That Persist Regardless of Authorization

The following prohibitions apply permanently, regardless of any future governance milestone authorizing AI use:

- AI may never produce ranking language, recommendation language, or language that functions as a hiring endorsement or disqualification.
- AI may never produce language referencing or implying any characteristic listed in Section 5.2 or Section 5.3.
- AI may never produce language that contradicts the advisory-only standard in Section 6.
- AI may never be given access to `BYA_NORM_V1` or `BYA_AGENT_NORM_V1` raw profile data, `BYA_COMP_V1` payloads, compensation data, or any data outside the Section 2 authorized input set.

---

## Section 10 — Human Review Requirement

### 10.1 Scope of Review Requirement

Human review is required at three distinct stages:

1. **Template authoring review** — Before any sentence template is added to the approved template library.
2. **Fair Housing and legal review** — Before the first consumer-visible deployment of the narrative layer at any gate.
3. **Ongoing spot review** — After each template library update and at defined intervals during production operation.

### 10.2 Template Wording Review

Every sentence template proposed for the approved template library must be reviewed by a qualified individual before admission. The reviewer must confirm:

- The template does not produce prohibited outputs per Section 4.
- The template does not reference or imply any protected class or proxy characteristic per Section 5.
- The template is consistent with the advisory-only standard per Section 6.
- The `{consumer_value}` and `{agent_value}` placeholders cannot be filled in a way that would produce a prohibited output for any valid normalized value in the `BYA_NORM_V1` and `BYA_AGENT_NORM_V1` option sets for that dimension.
- The template is legible to a consumer encountering the compatibility system for the first time.
- The template accurately represents what the underlying comparison found without overstating or understating the finding.

Templates that do not pass all six criteria must be revised before re-submission to the review process. No template may be merged into the approved library while review is pending.

### 10.3 Fair Housing Review

Before the narrative layer is deployed in any consumer-visible context, the complete template library must be reviewed by a qualified Fair Housing compliance professional. The review must confirm:

- No approved template, for any `explanation_key`, produces language that violates the Fair Housing Act, the Equal Credit Opportunity Act, or applicable state law.
- No template, when applied to any valid `consumer` and `agent` value pair, would produce a sentence that functions as a steering statement, a discriminatory characterization, or a proxy-class signal.
- The consumer-facing disclaimer in Section 11 is adequate to communicate the advisory nature of the narrative output and does not misrepresent the system's capabilities or limitations.

Findings from the Fair Housing review must be documented and retained. Any template flagged by the review must be removed from the approved library until revised and re-reviewed.

### 10.4 Legal Disclaimers Review

The consumer-facing disclaimer text drafted in Section 11 must be reviewed by legal counsel before its first deployment in any consumer-visible context. Legal review must confirm:

- The disclaimer is accurate and does not expose BidYourAgent to liability for reliance on narrative outputs.
- The disclaimer is consistent with applicable consumer protection laws in the jurisdictions where the platform operates.
- The disclaimer satisfies any disclosure requirements imposed by applicable real estate licensing, brokerage, or consumer protection law.

Legal review findings must be documented and retained alongside the Fair Housing review findings.

### 10.5 UI Placement Review

Before any consumer-visible deployment, the UI placement of narrative outputs must be reviewed to confirm:

- The disclaimer in Section 11 is visible at every point where narrative output is displayed.
- The narrative output does not occupy visual prominence that causes consumers to treat it as a ranking or recommendation.
- The narrative output is not placed in a position that causes it to function as a primary hiring signal, separate from agent qualifications, services, and compensation terms.

### 10.6 Audit Trail for Reviews

Every review conducted under this section must be documented in a record that includes: the reviewer's name and qualification, the review date, the scope of materials reviewed, the findings, and the outcome (approved, approved with modifications, or rejected). Review records must be retained for the full production life of the narrative layer plus a minimum of 24 months.

---

## Section 11 — Consumer-Facing Disclaimer Draft

### 11.1 Purpose of This Section

This section provides the draft text of the consumer-facing disclaimer that must accompany every narrative output displayed to a consumer. The disclaimer text in Section 11.2 is a draft for future legal and Fair Housing review. It must not be used in production before the reviews required by Section 10.3 and Section 10.4 are complete.

### 11.2 Draft Disclaimer Text

> **About this compatibility summary**
>
> The information below describes how the preferences you provided compare to the responses provided by this agent. It is based solely on the working-style preferences you both chose to share through this platform's compatibility questionnaire.
>
> This summary is for informational purposes only. It does not recommend, rank, or endorse any agent. It does not disqualify or advise against any agent. The comparison describes working-style preferences — not agent qualifications, professional history, services offered, or compensation terms.
>
> An alignment result does not guarantee a successful working relationship. A difference in preferences does not mean an agent is unsuitable for your needs. Many consumers successfully work with agents whose stated preferences differ from their own.
>
> The hiring decision is yours alone. This platform does not make or influence that decision on your behalf. You are encouraged to speak directly with any agent before making a hiring decision, regardless of what this summary shows.
>
> This summary was generated from structured data you and this agent provided through this platform's compatibility questionnaire. It does not incorporate artificial intelligence, external data sources, demographic information, or any data beyond the compatibility preferences collected through this questionnaire.

### 11.3 Placement Requirements for the Disclaimer

When implemented, the disclaimer must appear:

- Immediately preceding the narrative output on the compatibility summary view.
- On the per-dimension expansion panel, if compatibility detail is expanded separately.
- In any downloadable or shareable version of a compatibility summary.
- In any email or notification that surfaces compatibility narrative content to a consumer.

The disclaimer must not be hidden behind a toggle, collapsed by default, or placed in a location a consumer is unlikely to read before acting on compatibility information.

---

## Section 12 — Future Implementation Sequence

### 12.1 Overview

This section defines the planned implementation sequence for Milestones 8 through 12. This sequence is subject to change as compliance reviews, engineering assessments, and product decisions evolve, but represents the intended order of operations as established by this governance document.

### 12.2 Milestone 8 — Deterministic Template Service

**Objective:** Implement the `BYA_NARRATIVE_V1` deterministic template service as described in Section 8.

**Scope:**
- Author an initial template library covering the `alignment` and `difference` explanation types for all 10 dimensions currently capable of producing a comparison result, plus `insufficient_data` fallback templates for all 12 dimensions (including the two placeholder dimensions `technology_preference` and `market_education_preference`).
- All templates must pass human review (Section 10.2) before admission to the approved library.
- The service must consume only the `BYA_EXPLAIN_V1` payload and the `consumer` / `agent` values from `BYA_ALIGN_V1` as inputs.
- The service must produce audit records per Section 7.4.
- No AI components may be included.
- No consumer-facing display occurs at Milestone 8. Output is generated and stored for administrative review only.

**Exit criterion:** The template service generates correct, audit-compliant output for all `explanation_key` values reachable in current milestones and passes administrative review.

### 12.3 Milestone 9 — Fair Housing and Legal Review

**Objective:** Complete the Fair Housing review (Section 10.3) and legal disclaimer review (Section 10.4) of the Milestone 8 template library and Section 11 disclaimer text.

**Scope:**
- Engage a qualified Fair Housing compliance professional to review the complete template library, with particular attention to the `risk_tolerance` and `personality_style` dimension templates identified in Section 5.4.
- Engage legal counsel to review the Section 11 disclaimer text.
- Document all review findings.
- Revise and re-review any flagged templates.
- Finalize the approved template library and the approved disclaimer text.

**Exit criterion:** Written approval from the Fair Housing reviewer and legal counsel, with all review records retained.

### 12.4 Milestone 10 — Hidden Beta

**Objective:** Deploy the narrative layer to a hidden beta cohort (internal users and invited testers only) for quality assurance and spot review.

**Scope:**
- Enable narrative output display for the hidden beta cohort, accompanied by the approved disclaimer text.
- Conduct spot review of narrative outputs per Section 10.3's ongoing review requirement.
- Validate that audit records are generated correctly for all output events, including `explanation_key`, `consumer` value, `agent` value, and template identifier.
- Collect and address any quality, accuracy, or compliance issues identified during the beta period.

**Exit criterion:** No open compliance findings, audit records verified complete, spot review satisfactory.

### 12.5 Milestone 11 — Consumer-Visible Beta

**Objective:** Deploy the narrative layer to a limited consumer-visible audience.

**Scope:**
- Enable narrative output display for the consumer-visible beta cohort.
- Complete UI placement review per Section 10.5.
- Monitor audit records and consumer interactions for compliance signals.
- Evaluate whether to author templates for the reserved `adjacent` and `neutral` explanation types if `adjacent_compatibility` and `neutral_compatibility` alignment categories become reachable through upstream pipeline changes.

**Exit criterion:** No open compliance findings, UI placement review complete and satisfactory, no consumer-reported concerns indicating misunderstanding of the advisory-only nature of the output.

### 12.6 Milestone 12 — General Availability and AI Authorization Evaluation

**Objective:** Move the narrative layer to general availability and evaluate whether conditions exist to begin the AI authorization process defined in Section 9.2.

**Scope:**
- General availability deployment for all eligible consumer roles and listing types.
- Resolution of the `risk_tolerance` proxy-risk compliance analysis (Phase H Section 1.1), with template library updated to reflect findings — approved templates added if cleared, or confirmed `insufficient_data` fallback maintained if not.
- Optional: Begin the AI authorization governance milestone process per Section 9.2 if product requirements exist for AI-generated narrative variation. No AI components may be implemented until the authorization document is complete.

**Exit criterion:** General availability deployment stable, `risk_tolerance` proxy-risk findings implemented, audit infrastructure confirmed operational at scale.

---

*End of BYA Milestone 7 — Narrative Readiness Plan*
