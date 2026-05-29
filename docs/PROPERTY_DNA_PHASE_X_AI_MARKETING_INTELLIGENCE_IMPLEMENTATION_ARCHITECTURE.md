# Property DNA Phase X — AI Marketing Intelligence Implementation Architecture

**Document Date:** 2026-05-29
**Phase:** X — AI Marketing Intelligence Implementation Architecture
**Preceding Phases:** P — Deterministic Marketing Context Builder, Q — Marketing Brief Readiness Plan, R — Deterministic Property Marketing Brief Builder, S — Internal Brief Inspector / Admin Preview, T — Agent-Reviewed Brief UI, U — AI Drafting Guardrails Plan, V — AI Marketing Intelligence Governance & Readiness Plan, W — AI Marketing Intelligence Report Contract
**Type:** Architecture and planning document only — no code, no routes, no schema changes, no AI calls, no UI

---

## 1. Generation Workflow

This section documents the complete end-to-end flow that governs the production of an AI Marketing Intelligence Report. Every step in this sequence is required. No step may be skipped, reordered, or substituted without a formal Phase X amendment. No code path, background job, or fallback mechanism may bypass any step.

### 1.1 Workflow Overview

The generation workflow consists of nine ordered stages:

```
PropertyDnaProfile
    → PropertyMarketingBriefService
    → PropertyMarketingReadinessService
    → Readiness Gate
    → AI Generation Request
    → Attribution Verification
    → Report Creation
    → Agent Review
    → Seller / Landlord Review
    → Publication
```

### 1.2 Stage 1 — PropertyDnaProfile

The workflow begins with a persisted `PropertyDnaProfile` record for the target property listing. The profile must belong to a known, valid listing. No report generation may be initiated for a listing that does not have an associated `PropertyDnaProfile`.

**Required state:** The `PropertyDnaProfile` must be retrievable by its primary key and must be associated with a listing identifier. The profile does not need to be complete — the readiness gate in Stage 3 determines whether the profile is sufficient for AI generation.

### 1.3 Stage 2 — PropertyMarketingBriefService

The system calls `PropertyMarketingBriefService::build(PropertyDnaProfile $profile)` (Phase R) to produce the structured marketing brief. This call must be made fresh at generation time from the live profile record. A cached or session-persisted brief must not be used as a substitute.

**Output:** A structured array with the nine named sections defined in Phase W Section 2.1 (`property_attribute_context`, `transaction_context`, `quantitative_context`, `marketing_asset_checklist`, `missing_information_checklist`, `seller_landlord_questions`, `listing_preparation_notes`, `neutral_feature_summary`, `summary`). All nine keys are always present.

### 1.4 Stage 3 — PropertyMarketingReadinessService

The system calls `PropertyMarketingReadinessService::build(PropertyDnaProfile $profile)` (Phase U) to produce the readiness review. This call must also be made fresh at generation time. A cached result must not be used.

**Output:** A structured array containing `is_marketing_ready` (boolean), `present_groups`, `missing_groups`, `review_items`, and `summary` counts as defined in Phase W Section 2.2.

### 1.5 Stage 4 — Readiness Gate

Before any AI call is constructed or dispatched, the system evaluates the readiness gate using the fresh Phase U output. If `is_marketing_ready` is `false`, the workflow is aborted unconditionally at this stage. A gate failure audit record is created (see Section 7.4) and a user-facing message is surfaced to the listing agent identifying the missing groups.

**Gate pass condition:** `is_marketing_ready === true`. All three required information groups — Property Attributes, Transaction Details, and Quantitative Data — must be present.

**Gate failure behavior:** The AI call is never initiated. No partial report is created. No data is passed to any AI model. See Section 2 for full gate behavior specification.

### 1.6 Stage 5 — AI Generation Request

When the readiness gate passes, the system constructs an AI generation request using only the approved Phase R brief output and Phase U readiness output as input context. The request is submitted to the configured AI model using the version-controlled prompt template.

**Inputs:** Phase R brief (approved sections only per Phase W Section 2.1), Phase U readiness output (approved fields only per Phase W Section 2.2), and Phase P marketing context (for attribution resolution per Phase W Section 2.3).

**Output:** A candidate report object conforming to the Phase W Section 3.1 JSON contract, with all seven top-level keys and all five section keys present.

See Section 3 for full AI Request Lifecycle specification.

### 1.7 Stage 6 — Attribution Verification

After the AI generation call returns, the system performs attribution verification on every report section with non-empty `draft_text`. Each factual claim in each section must be traceable to a specific Phase R or Phase U source record. Any claim that cannot be attributed is removed from `draft_text` before the report object is stored.

The `attribution_verified` boolean in the report object root is set to `true` only when all sections with non-empty `draft_text` carry at least one `source_attribution` entry. If attribution verification fails for any section, `attribution_verified` is set to `false` and the report is held (not surfaced to any user) until the gap is resolved or the affected `draft_text` is cleared.

See Section 4 for full Attribution Verification specification.

### 1.8 Stage 7 — Report Creation

When attribution verification succeeds (or all non-empty sections are attributed), the system persists the report object and creates the generation audit record. The generation audit record must be created and persisted before the report object is surfaced to any user. If the audit record cannot be created, the generation is treated as failed.

All section statuses are set to `pending_review` at creation time (except `missing_information_note`, which uses `internal_note`). No status transition occurs at this stage.

### 1.9 Stage 8 — Agent Review

The persisted report is surfaced to the listing agent in an internal review interface (implemented in Phase XD). The agent reviews each section's `draft_text` alongside its `source_attribution` entries. The agent may approve, revise, or reject each section. Each action is an affirmative, recorded human action; no auto-approval path exists.

A review audit record is created for each agent action (see Section 7.2). Only after all required sections are approved or revised does the report advance to the next stage.

### 1.10 Stage 9 — Seller / Landlord Review

After agent approval, the report is surfaced to the seller or landlord for review and approval (implemented in Phase XE). The seller or landlord must affirmatively approve the content before it may be published. An approval audit record is created. No auto-approval path exists.

Publication (implemented in Phase XF) occurs only after both the agent approval gate and the seller/landlord approval gate have been satisfied.

---

## 2. Readiness Gate

This section documents the readiness gate that must be evaluated before every AI generation request. The gate is the sole mechanism that determines whether the workflow may proceed past Stage 4. It is non-negotiable, non-bypassable, and must be evaluated fresh on every call.

### 2.1 Gate Condition

AI generation is permitted for a given property listing only when `PropertyMarketingReadinessService::build($profile)['is_marketing_ready']` equals `true`. This boolean is `true` only when all three required information groups are present in the profile:

- **Property Attributes Group:** At least one named attribute bucket contains at least one record.
- **Transaction Details Group:** At least one named transaction bucket contains at least one record.
- **Quantitative Data Group:** `quantitative_context` contains at least one record.

A partial pass — where one or two of the three groups are present — is not sufficient. All three must be present simultaneously.

### 2.2 Gate Failure — Mandatory Abort

When `is_marketing_ready` is `false`, the following outcomes are mandatory:

- The AI generation call is not initiated under any circumstance.
- No AI prompt is constructed, assembled, or dispatched.
- No data from Phase R or Phase U is transmitted to any AI model.
- No partial or best-effort report is generated from available data alone.
- No error handler, exception catcher, fallback, or retry mechanism may initiate an AI call when the gate has not passed.
- The `missing_groups` list from Phase U output is surfaced to the listing agent with a plain-language explanation that the profile is incomplete.
- A gate failure audit record is created (see Section 7.4).

### 2.3 Fresh Evaluation Requirement

The readiness gate must be re-evaluated on every AI generation request by calling `PropertyMarketingReadinessService::build($profile)` with the live, current profile record. The following are prohibited as substitutes for a fresh evaluation:

- A cached readiness result from a prior request or session
- A stored `is_marketing_ready` flag from a previous generation audit record
- A session variable, query parameter, or request attribute carrying a prior result
- Any assumption that a profile that was ready in a prior generation attempt remains ready

### 2.4 Gate Enforcement Point

The readiness gate must be evaluated within the generation service or controller that initiates the AI call. It must not be evaluated only in UI layer code or JavaScript — it must be enforced server-side, immediately before any AI request construction begins.

---

## 3. AI Request Lifecycle

This section documents the inputs supplied to the AI generation call, the report contract requirement, and the prohibitions that govern what the AI may and may not receive.

### 3.1 Inputs Supplied to the AI Model

The following and only the following structured data may be provided to the AI model as part of the generation request:

| Input | Source Service | Permitted Use |
|---|---|---|
| `property_attribute_context` | Phase R (`PropertyMarketingBriefService`) | Primary content input for `property_feature_narrative` |
| `transaction_context` | Phase R | Primary content input for `transaction_terms_summary` |
| `quantitative_context` | Phase R | Primary content input for `property_feature_narrative` |
| `marketing_asset_checklist` | Phase R | Primary content input for `marketing_asset_statement` |
| `missing_information_checklist` | Phase R | Primary content input for `missing_information_note` |
| `listing_preparation_notes` | Phase R | Primary content input for `listing_preparation_summary` |
| `neutral_feature_summary` | Phase R | Verification reference for attribution checking |
| `seller_landlord_questions` | Phase R | Context only — not provided as questions for the AI to answer autonomously |
| `summary` | Phase R | Metadata only — integer counts |
| `is_marketing_ready` | Phase U (`PropertyMarketingReadinessService`) | Gate confirmation only |
| `present_groups` | Phase U | Context — informs AI which data groups are available |
| `missing_groups` | Phase U | Context — informs AI which data is absent |
| `review_items` | Phase U | Supplemental context |
| `attribute_context` | Phase P (`PropertyMarketingContextService`) | Source attribution resolution only |
| `transaction_context` (tag-level) | Phase P | Source attribution resolution only |
| `quantitative_context` (tag-level) | Phase P | Source attribution resolution only |

### 3.2 Report Contract Requirement

The AI model must be instructed to produce a response that conforms exactly to the JSON report contract defined in Phase W Section 3.1. The response must include:

- All seven top-level keys: `report_id`, `generated_at`, `listing_context`, `readiness_snapshot`, `sections`, `generation_metadata`, `attribution_verified`
- All five section keys within `sections`: `property_feature_narrative`, `transaction_terms_summary`, `marketing_asset_statement`, `missing_information_note`, `listing_preparation_summary`
- Each section must include `draft_text`, `status`, and `source_attribution` keys
- `status` must be `pending_review` for all sections except `missing_information_note`, which must be `internal_note`

If the AI response does not conform to this contract shape, the generation must be treated as failed. No partial or non-conforming report object may be stored or surfaced.

### 3.3 Prompt Template Requirements

The prompt template used in every AI generation call must:

- Be version-controlled and identified by a version string referenced in every generation audit record
- Include explicit, unconditional prohibitions against each category of prohibited output defined in Phase V Section 5 (demographic targeting copy, protected-class targeting, ideal-buyer characterizations, suitability scoring, neighborhood demographic characterization, school commentary, income/wealth targeting, audience upload recommendations, autonomous ad copy, fabricated property data, transaction outcome predictions)
- Include an explicit instruction that the AI may only assert facts present in the provided Phase R and Phase U input — not inferred, estimated, or drawn from training data knowledge about the location, neighborhood, or market
- Include an explicit instruction that empty input sections must produce empty `draft_text` (empty string, not null, not absent)
- Include an explicit instruction requiring the AI to populate `source_attribution` for every non-empty `draft_text`

### 3.4 Strict Prohibition on External Data

The AI generation call must never include, reference, or be augmented with:

| Prohibited Input | Prohibition Basis |
|---|---|
| Neighborhood demographic data (census, ACS, or similar) | Fair Housing Act §804(c); HUD steering guidelines |
| School demographic or rating data | Fair Housing Act; school-based steering case law |
| Buyer or tenant identity, credit, or financial records | FCRA; ECOA; CFPB guidance |
| Protected-class signals of any kind | Fair Housing Act; 42 U.S.C. §3604 |
| Income tier, wealth tier, or socioeconomic classification | Fair Housing Act disparate impact doctrine; ECOA |
| External real estate market data not in the Phase R brief | Out of scope; introduces unaudited inference |
| Any data not produced by an approved Phase P, R, or U output | Scope boundary |

These prohibitions apply at every layer: in the prompt template, in the input assembly code, and in any preprocessing step that structures the request payload. No workaround, optional parameter, or configurable override may relax any prohibition.

### 3.5 AI Response Validation

Before the AI response is used to construct a report object, the following validations must be applied:

1. The response must parse as valid JSON.
2. All seven required top-level keys must be present and non-null.
3. All five section keys must be present within `sections`.
4. Each section must have `draft_text` (string), `status` (string), and `source_attribution` (array) keys.
5. `draft_text` must be a string (may be empty; must not be null or absent).
6. `status` values must conform to the allowed values defined in Phase W Section 3.3.

Any validation failure must cause the generation to be treated as failed (see Section 8.1).

---

## 4. Attribution Verification

This section documents the attribution verification step that must occur after every AI generation call and before the report object is persisted or surfaced to any user.

### 4.1 Attribution Requirement

Every non-empty `draft_text` in the report must have at least one `source_attribution` entry. Each `source_attribution` entry must contain:

| Field | Type | Requirement |
|---|---|---|
| `source_section` | string | Must be one of the approved values defined in Phase W Section 5.2 |
| `source_records` | array of strings | Must contain at least one specific tag, trait, or value string from Phase R or Phase U output |

The following are the only approved `source_section` values and their permitted report section pairings:

| `source_section` Value | Phase | May Appear In |
|---|---|---|
| `property_attribute_context` | R | `property_feature_narrative` |
| `transaction_context` | R | `transaction_terms_summary` |
| `quantitative_context` | R | `property_feature_narrative` |
| `marketing_asset_checklist` | R | `marketing_asset_statement` |
| `missing_information_checklist` | R | `missing_information_note` |
| `listing_preparation_notes` | R | `listing_preparation_summary` |
| `missing_groups` | U | `missing_information_note` |

### 4.2 Unattributable Content Is Removed, Not Inferred

If the AI-generated `draft_text` for any section contains a factual claim that cannot be traced to a Phase R or Phase U source record in the `source_attribution` array, the implementation layer must remove that claim from `draft_text` before the report object is stored. Unattributable content must never be retained, published, or transmitted.

The following actions are prohibited as responses to an attribution gap:

- Inferring a source record that was not provided by the AI
- Constructing a synthetic `source_attribution` entry to fill the gap
- Publishing the unattributed content with a disclaimer
- Allowing a human reviewer to retroactively "approve" unattributed content without first removing it or resolving the attribution

### 4.3 `attribution_verified` Flag

The `attribution_verified` boolean in the report object root governs whether the report may be surfaced to any user. The rules for setting this flag are:

| Condition | `attribution_verified` value |
|---|---|
| All sections with non-empty `draft_text` have at least one `source_attribution` entry | `true` |
| Any section has non-empty `draft_text` with no `source_attribution` entries | `false` |
| All sections have empty `draft_text` (no content generated) | `true` (empty report is considered attributed) |

A report with `attribution_verified: false` must not be surfaced to any user in any context — not to agents, not to sellers, not to administrators — until the attribution gap is resolved (either by removing the unattributed `draft_text` or by establishing valid attribution).

### 4.4 Attribution Verification Failure Handling

If attribution verification results in `attribution_verified: false` after unattributable claims have been removed, the following outcomes apply:

- An attribution failure audit record is created (see Section 7.4).
- The report object is stored with `attribution_verified: false`.
- The report is not surfaced to any user.
- The action taken (`report_discarded` or `report_held_pending_resolution`) is recorded in the attribution failure audit record.

See Section 8.2 for error handling behavior when attribution verification fails.

### 4.5 `neutral_feature_summary` as Verification Reference

The Phase R `neutral_feature_summary` section — which contains pre-computed, deterministic factual summaries of attribute and quantitative records — serves as a verification reference during attribution checking. Any claim in `property_feature_narrative` that cannot be matched to a `neutral_feature_summary` entry, a raw `property_attribute_context` record, or a `quantitative_context` record is presumptively unattributable and must be removed per Section 4.2.

---

## 5. Hallucination Prevention

This section documents the rules that prevent fabricated, inferred, or estimated content from appearing in any section of the AI Marketing Intelligence Report. These rules apply regardless of AI model, model version, or prompt template version used.

### 5.1 No Invented Facts

The AI model must not introduce any property fact that is not present in the structured Phase R and Phase U inputs provided to it. The following are unconditionally prohibited:

- Adding property attributes that are plausible but not recorded (e.g., inferring a garage because the property type is "single family home")
- Asserting a bedroom count, square footage, lot size, or price that differs from the value in `quantitative_context`
- Inferring a full value from a partial record (e.g., deriving a full bathroom count from a half-bath record)
- Describing amenities, features, or policies that do not appear in `property_attribute_context`

### 5.2 No Inferred Facts

The AI model must not draw inferences from the data it is provided. Inferences include:

- Inferring the neighborhood's character, demographic composition, or desirability from the property's location, price, or type
- Inferring buyer or tenant preferences from the transaction terms
- Inferring market conditions from the listing price or financing terms
- Inferring that a missing data point has a typical or expected value

An absence of data for a profile dimension means there is nothing to say about that dimension. The AI must not fill that absence with a typical-case assumption.

### 5.3 No Estimates

The AI model must not produce estimates, approximations, or probabilistic assertions for any property attribute. If `quantitative_context` contains no square footage record, the report must not state "approximately X square feet" or "likely in the range of X–Y square feet." An empty input produces an empty output for that attribute.

### 5.4 No Missing-Data Fill

The AI model must not fill empty or sparse Phase R sections with data from its training knowledge about the property's geographic location, market segment, or property type category. The Phase R brief is the exclusive factual universe. What is not in the brief does not exist for the purposes of this report.

### 5.5 Empty Input Produces Empty Output

Each of the five report sections has a defined Phase R source:

| Section | Source |
|---|---|
| `property_feature_narrative` | `property_attribute_context`, `quantitative_context` |
| `transaction_terms_summary` | `transaction_context` |
| `marketing_asset_statement` | `marketing_asset_checklist` |
| `missing_information_note` | `missing_information_checklist`, `missing_groups` |
| `listing_preparation_summary` | `listing_preparation_notes` |

If the source for a section is empty (empty array, zero records), the `draft_text` for that section must be an empty string. The section must still be present in the report object with an empty `draft_text`. The section must not be omitted, collapsed, or replaced with a placeholder message.

### 5.6 Prompt-Level Enforcement

The hallucination prevention rules in this section must be implemented at the prompt level. The prompt template must include explicit, unconditional prohibitions against:

- Adding facts not present in the provided inputs
- Inferring values for missing dimensions
- Producing estimates or approximations for any attribute
- Using training data knowledge about the property's location or market

Prompt-level enforcement is required in addition to the attribution verification step in Section 4. Neither layer may be relied upon as the sole hallucination prevention mechanism.

### 5.7 Superlatives and Persuasive Language Are Prohibited

The AI model must not produce superlatives, marketing hyperbole, or persuasive framing in any section. Prohibited language includes but is not limited to: "stunning," "rare opportunity," "ideal for," "perfect for," "must-see," "exceptional," "one-of-a-kind," "turn-key," or any similar characterization that goes beyond a factual description of the property's recorded attributes.

---

## 6. Human Review Workflow

This section documents the two-stage human review workflow that must be completed before any AI-generated content may be published or distributed. Both stages are required. No auto-approve path exists at any stage.

### 6.1 Overview

The two-stage review workflow is:

```
Report Created (all sections: pending_review / internal_note)
    → Stage 1: Agent Review (Revise / Approve / Reject per section)
    → Stage 2: Seller / Landlord Approval (Approve / Reject)
    → Publication Permitted
```

### 6.2 Stage 1 — Agent Review

After the report object is created and persisted, it is surfaced to the listing agent in an internal review interface (Phase XD). The agent reviews each section's `draft_text` alongside its `source_attribution` entries. The agent must take one of the following affirmative actions for each section requiring review (all sections except `missing_information_note`):

| Action | Section Status After Action | Audit Record Created |
|---|---|---|
| **Approve** | `approved` | Review audit record with `action: approved` |
| **Revise** | `revised` | Review audit record with `action: approved_with_revisions`; original AI text and revised text both stored |
| **Reject** | `rejected` | Review audit record with `action: rejected` |

**No auto-approve path:** No section transitions from `pending_review` to `approved` through any automated process, timeout, scheduled job, or fallback. Approval is an affirmative human action only.

**No approval by inaction:** A section does not become approved because the agent did not reject it within a time window. Approval requires a positive action.

**Draft label requirement:** Every section must display a visible "AI-generated draft — pending agent review" label until the agent has taken an affirmative action on that section. The label must not be removed by any automated process.

**Revision tracking:** When an agent revises a section, both the original AI-generated `draft_text` and the revised text must be stored as distinct versioned records. The final approved text must be clearly distinguishable from the original AI draft.

### 6.3 Stage 2 — Seller / Landlord Approval

After the agent has completed their review (all required sections approved or revised), the report advances to the seller or landlord approval stage (Phase XE). The seller or landlord is presented with the agent-reviewed content for their review and explicit approval.

| Action | Outcome | Audit Record Created |
|---|---|---|
| **Approve** | Report may proceed to publication | Seller/landlord approval audit record |
| **Reject** | Report is returned for agent revision | Rejection audit record |

**No auto-approve path:** No content transitions to the published state without an affirmative seller/landlord approval action. No timeout or inaction may substitute for this approval.

**Scope:** The seller/landlord approval gate applies to any section with `status: approved` or `status: revised` that is intended for any public-facing or externally transmitted use. The `missing_information_note` section (status: `internal_note`) is excluded from the seller/landlord approval requirement.

### 6.4 Fair Housing Compliance Review Gate

Any AI-generated content that will appear in advertising, printed materials, or any form of public distribution must be reviewed specifically for Fair Housing compliance by a qualified reviewer before publication. This is a third, separate gate that applies in addition to the agent review and seller/landlord approval gates.

Automated compliance filter outputs are supplemental only — they do not satisfy this gate. A qualified human reviewer must sign off before any external transmission.

### 6.5 No Bypass Paths

No code path may exist in the implementation of Phase X or any subsequent phase that:

- Moves a report section from `pending_review` to `approved` or `revised` without an explicit, recorded human action
- Publishes or externally transmits a report or any section of a report without both the agent approval and the seller/landlord approval gates having been satisfied
- Treats a rejection or non-response as an implicit approval
- Provides an administrator override that bypasses the seller/landlord approval requirement

---

## 7. Audit Workflow

This section documents the four required audit record types that must be created throughout the report generation and review lifecycle. All audit records are append-only and must be retained for a minimum of five years.

### 7.1 Generation Audit Record

A generation audit record must be created for each successful AI generation call, before the report object is surfaced to any user. If the audit record cannot be persisted, the generation must be treated as failed.

| Field | Type | Description |
|---|---|---|
| `report_id` | string | The unique identifier of the generated report object; serves as the primary key |
| `listing_id` | string/integer | The identifier of the property listing |
| `profile_id` | string/integer | The identifier of the `PropertyDnaProfile` used as input |
| `generated_at` | string | UTC ISO 8601 timestamp of the AI call |
| `ai_model` | string | The AI model identifier and version (e.g., `gpt-4o-2024-08-06`) |
| `prompt_template_version` | string | The version identifier of the prompt template used |
| `report_contract_version` | string | The Phase W contract version active at generation time (e.g., `phase-w-v1`) |
| `phase_r_brief_snapshot` | JSON | Serialized snapshot of the Phase R brief input provided to the AI |
| `phase_u_readiness_snapshot` | JSON | Serialized snapshot of the Phase U readiness output at generation time |
| `is_marketing_ready_at_call` | boolean | The readiness gate result at the time of the call |
| `attribution_verified` | boolean | The `attribution_verified` value of the generated report object |

### 7.2 Review Audit Record

A review audit record must be created for each affirmative agent review action taken on any report section.

| Field | Type | Description |
|---|---|---|
| `report_id` | string | Foreign key to the generation audit record |
| `section_key` | string | The report section key that was reviewed (e.g., `property_feature_narrative`) |
| `reviewed_by` | string/integer | The user identifier of the reviewing agent or broker |
| `reviewed_at` | string | UTC ISO 8601 timestamp of the review action |
| `action` | string | One of: `approved`, `approved_with_revisions`, `rejected` |
| `revisions_made` | boolean | Whether the reviewer modified the AI draft text |
| `original_ai_text` | string | The original AI-generated `draft_text` before any revisions |
| `approved_text` | string | The final approved or revised text (same as `original_ai_text` if no revisions were made) |

A seller/landlord approval action must also produce a review audit record with the seller/landlord's user identifier and `action: approved` or `action: rejected` as applicable.

### 7.3 Readiness Failure Audit Record

A readiness failure audit record must be created each time the readiness gate is evaluated and fails (i.e., when `is_marketing_ready` is `false`). This record must be created even though no AI call is initiated.

| Field | Type | Description |
|---|---|---|
| `listing_id` | string/integer | The identifier of the property listing |
| `profile_id` | string/integer | The identifier of the `PropertyDnaProfile` evaluated |
| `gate_evaluated_at` | string | UTC ISO 8601 timestamp |
| `missing_groups` | array of strings | The group names that were absent at evaluation time |
| `gate_result` | boolean | Always `false` for this record type |

### 7.4 Attribution Failure Audit Record

An attribution failure audit record must be created when a report generation call produces a report object with `attribution_verified: false` (after unattributable content has been removed per Section 4.2).

| Field | Type | Description |
|---|---|---|
| `report_id` | string | The identifier of the report object with the attribution failure |
| `unattributed_sections` | array of strings | The section keys where `draft_text` was non-empty but `source_attribution` was empty before removal |
| `detected_at` | string | UTC ISO 8601 timestamp |
| `action_taken` | string | One of: `report_discarded`, `report_held_pending_resolution` |

### 7.5 Audit Record Integrity

All four audit record types share the following integrity requirements:

- **Append-only:** No existing audit record may be modified or deleted by any application process. If an error correction is required, a new corrective record must be created referencing the original, not overwriting it.
- **Retention:** All audit records must be retained for a minimum of five years from creation date, consistent with HUD Fair Housing recordkeeping guidance and applicable state real estate law.
- **Queryability:** Authorized platform administrators must be able to query audit records by listing identifier, user identifier, date range, and gate result without direct database access.
- **Export:** Audit records must be exportable in CSV or JSON format for use as Fair Housing compliance evidence.

---

## 8. Error Handling

This section documents how OpenAI failures, attribution failures, readiness gate failures, and incomplete output conditions are handled. No partial reports may be published as a result of any error condition.

### 8.1 OpenAI / AI Model Failures

An AI model failure includes any of the following conditions: network timeout, API error response, rate limit exceeded, response that does not parse as valid JSON, response that does not conform to the Phase W Section 3.1 contract shape, and response that fails AI response validation per Section 3.5.

**Required behavior on AI model failure:**

- The generation is treated as failed.
- No report object is stored.
- No generation audit record is created for the failed call (or a failure-only audit record is created with a `failed` status, at implementation discretion).
- The listing agent is informed that report generation failed and may retry.
- No partial or malformed report object is stored, displayed, or transmitted.
- The error condition is logged with the listing identifier, the timestamp, and the failure reason.

**Retry behavior:** The implementation may permit the listing agent to manually retry a failed generation. Automated retries must not be initiated without an explicit agent action. No retry may skip the readiness gate re-evaluation.

### 8.2 Attribution Failures

An attribution failure occurs when the AI generation call returns a conforming response, but the attribution verification step determines that one or more sections contain `draft_text` that cannot be attributed to Phase R or Phase U source records.

**Required behavior on attribution failure:**

- Unattributable claims are removed from `draft_text` per Section 4.2.
- If all non-empty sections now have valid attribution, `attribution_verified` is set to `true` and the report proceeds normally.
- If any section still lacks attribution after removal (e.g., the entire `draft_text` was unattributable and has been cleared to an empty string but other sections remain unattributed), `attribution_verified` is set to `false`.
- An attribution failure audit record is created per Section 7.4.
- The report is not surfaced to any user while `attribution_verified` is `false`.
- The listing agent is informed that the report has been held due to an attribution issue.

### 8.3 Readiness Gate Failures

A readiness gate failure occurs when `PropertyMarketingReadinessService::build($profile)['is_marketing_ready']` returns `false` at the moment a generation is requested.

**Required behavior on readiness failure:**

- The AI call is aborted unconditionally.
- No AI prompt is constructed or dispatched.
- A readiness failure audit record is created per Section 7.3.
- The listing agent is shown the `missing_groups` list with a plain-language explanation.
- No report object is created for this attempt.
- The agent may update the profile and request a new generation; the gate will be re-evaluated fresh.

### 8.4 Incomplete Output Conditions

An incomplete output condition occurs when the AI generation call returns a valid, contract-conforming response, but all five section `draft_text` values are empty strings (the AI produced no content for any section).

**Required behavior on incomplete output:**

- The report object is stored with all empty `draft_text` values and `attribution_verified: true` (empty sections are considered attributed).
- A generation audit record is created normally.
- The listing agent is informed that the generated report contains no content and that the profile may need to be enriched before a meaningful report can be produced.
- The report is surfaced to the agent in the review interface with all empty sections visible — no sections are hidden or suppressed.
- The agent may choose to discard the empty report and enrich the profile before requesting a new generation.

### 8.5 No Partial Report Publication

Under no error condition, fallback path, or administrative override may a partial report be published. "Partial" means any report in which:

- `attribution_verified` is `false`
- Any section with non-empty `draft_text` has not received an affirmative agent approval action
- The seller/landlord approval gate has not been satisfied

These are hard rules with no exception paths.

---

## 9. Future Database Requirements

This section documents the future database entities required to support the AI Marketing Intelligence Report lifecycle. This section is planning-only — no tables are created, no migrations are written, and no schema changes are made in Phase X. The entities described here are design targets for future implementation phases.

### 9.1 `MarketingReport`

The primary record for each generated AI Marketing Intelligence Report.

**Purpose:** Stores the persisted report object and its top-level metadata for a given generation event.

**Required fields (design targets):**

| Field | Type | Description |
|---|---|---|
| `id` | string (UUID or similar) | Primary key; corresponds to `report_id` in the report object |
| `listing_id` | integer/string | Foreign key to the property listing |
| `profile_id` | integer/string | Foreign key to the `PropertyDnaProfile` |
| `generated_at` | timestamp | UTC timestamp of the AI call |
| `ai_model` | string | Model identifier and version |
| `prompt_template_version` | string | Version identifier of the prompt template |
| `report_contract_version` | string | Phase W contract version (e.g., `phase-w-v1`) |
| `readiness_snapshot` | JSON | Snapshot of Phase U output at generation time |
| `attribution_verified` | boolean | Whether attribution verification passed |
| `status` | string | Overall report status (e.g., `pending_review`, `agent_approved`, `seller_approved`, `published`, `rejected`) |
| `created_at` | timestamp | Record creation timestamp |
| `updated_at` | timestamp | Record last updated timestamp |

### 9.2 `MarketingReportVersion`

Versioned storage for each section's content across the report lifecycle, supporting the revision tracking required by the agent review workflow.

**Purpose:** Stores the original AI-generated `draft_text` and all subsequent revisions for each report section, enabling full revision history and compliance auditability.

**Required fields (design targets):**

| Field | Type | Description |
|---|---|---|
| `id` | integer | Primary key |
| `marketing_report_id` | string | Foreign key to `MarketingReport` |
| `section_key` | string | The report section key (e.g., `property_feature_narrative`) |
| `version_number` | integer | Incrementing version counter per section |
| `draft_text` | text | The content of this version of the section |
| `source_attribution` | JSON | The `source_attribution` array for this version |
| `status` | string | Section status at this version (e.g., `pending_review`, `approved`, `revised`, `rejected`, `internal_note`) |
| `created_by` | string | `ai_generated` for the initial version; user identifier for agent revisions |
| `created_at` | timestamp | Record creation timestamp |

### 9.3 `MarketingReportAudit`

The append-only audit log for all generation, review, gate failure, and attribution failure events in the report lifecycle.

**Purpose:** Provides a complete, tamper-evident audit trail for every event in the AI Marketing Intelligence Report workflow, covering all four audit record types defined in Section 7.

**Required fields (design targets):**

| Field | Type | Description |
|---|---|---|
| `id` | integer | Primary key |
| `event_type` | string | One of: `generation`, `review`, `readiness_failure`, `attribution_failure` |
| `report_id` | string/null | Foreign key to `MarketingReport`; null for `readiness_failure` events where no report was created |
| `listing_id` | integer/string | The property listing identifier |
| `profile_id` | integer/string | The `PropertyDnaProfile` identifier |
| `actor_id` | integer/string/null | User identifier for review events; null for system-generated events |
| `event_at` | timestamp | UTC timestamp of the event |
| `event_data` | JSON | Event-specific payload (varies by `event_type`; see Section 7 for field requirements per type) |
| `created_at` | timestamp | Record insertion timestamp |

**Integrity constraints (design targets):**

- No `UPDATE` or `DELETE` operations may be permitted on this table by any application role.
- A database-level trigger or application-level constraint must enforce the append-only requirement.
- The `event_data` JSON column must be validated against the required field set for each `event_type` before insertion.

---

## 10. Future Phase Sequence

This section documents the six implementation phases (XA through XF) that must be completed to deliver the full AI Marketing Intelligence Report feature. Each phase requires its own approved governance document before implementation begins. No phase may begin without that governance document. No phase may relax any constraint defined in Phases V or W without a formal amendment reviewed by a qualified compliance authority.

### Phase XA — OpenAI Integration Layer

**Type:** Internal service infrastructure — no public routes, no client-facing UI, no report generation logic
**What it does:** Establishes the authenticated connection to the OpenAI API (or approved equivalent), implements the prompt template version-control system, implements AI response parsing and contract-shape validation per Section 3.5, and provides the internal service interface that subsequent phases will call to initiate AI generation requests.
**Inputs:** Configured API credentials (stored as environment secrets, never in code); prompt template files under version control
**Outputs:** An internal service callable by Phase XB; no report objects created in this phase
**AI:** OpenAI API integration is introduced here; no report is generated in this phase — only connectivity and response parsing are validated
**Governance document required:** Yes — must document API credential management, prompt template storage and versioning, response validation rules, rate limit handling, and error handling behavior

---

### Phase XB — Report Generator Service

**Type:** Internal service — no public routes, no client-facing UI
**What it does:** Implements the full generation workflow defined in Section 1 of this document, including: invoking `PropertyMarketingBriefService` and `PropertyMarketingReadinessService`, evaluating the readiness gate, constructing the AI generation request, calling the Phase XA integration layer, performing attribution verification per Section 4, persisting the report object and generation audit record per Section 7.1, and handling all error conditions per Section 8.
**Inputs:** `PropertyDnaProfile` record (by identifier); Phase P, R, and U service outputs (computed fresh at generation time)
**Outputs:** A persisted `MarketingReport` record conforming to the Phase W contract; a `MarketingReportAudit` generation record
**AI:** Calls the Phase XA integration layer; subject to all Phase V and Phase W constraints
**Schema:** Creates `MarketingReport`, `MarketingReportVersion`, and `MarketingReportAudit` tables (migrations for the entities described in Section 9)
**Governance document required:** Yes — must document the service interface, generation workflow implementation, attribution verification implementation, error handling implementation, and audit record creation rules

---

### Phase XC — Internal Admin Report Review

**Type:** Internal admin-only interface
**What it does:** Provides authorized platform administrators with a read-only view of generated `MarketingReport` records, generation audit records, readiness failure records, and attribution failure records. Supports query by listing identifier, user identifier, date range, and audit event type. Provides CSV/JSON export of audit records for Fair Housing compliance evidence.
**Inputs:** `MarketingReport` and `MarketingReportAudit` records from Phase XB
**Outputs:** Read-only admin display; exportable audit data
**AI:** None
**Governance document required:** Yes — must document admin access controls, permissible query scope, export format, and data retention display

---

### Phase XD — Agent Report Review

**Type:** Agent-facing internal review UI
**What it does:** Surfaces the generated `MarketingReport` to the listing agent. Displays each section's `draft_text` alongside its `source_attribution` entries. Provides the agent approval, revision, and rejection workflow per Section 6.2. Creates review audit records per Section 7.2 for each affirmative agent action. Enforces the "AI-generated draft — pending agent review" label on all sections until the agent has acted. Stores original AI text and revised text as distinct `MarketingReportVersion` records.
**Inputs:** `MarketingReport` and `MarketingReportVersion` records from Phase XB
**Outputs:** Updated section `status` values; `MarketingReportAudit` review records; versioned `MarketingReportVersion` records for revisions
**AI:** None — review UI only
**Governance document required:** Yes — must document review UI specification, section-level approval flow, revision form, version storage, label and disclosure requirements, and audit record creation

---

### Phase XE — Seller / Landlord Approval

**Type:** Seller/landlord-facing review UI
**What it does:** Presents the agent-reviewed and agent-approved marketing report content to the seller or landlord for their explicit approval. The seller or landlord may approve or reject the content. An approval audit record is created for each action. No content advances to publication without an affirmative seller/landlord approval action.
**Inputs:** `MarketingReport` records where all required sections have `status: approved` or `status: revised` (agent review complete)
**Outputs:** Updated report `status`; `MarketingReportAudit` seller/landlord approval records
**AI:** None
**Governance document required:** Yes — must document disclosure language presented to seller/landlord, approval UI specification, rejection handling and return-to-agent flow, approval audit record schema, and applicable state-law review requirements

---

### Phase XF — Publication Controls

**Type:** Controlled publication layer — no autonomous publication path
**What it does:** Enables listing agents, with seller/landlord approval already satisfied, to initiate publication of the approved marketing report content to authorized channels (internal listing display, MLS submission under agent control, etc.). Enforces the Fair Housing compliance review gate per Section 6.4 before any external transmission is permitted. Provides a supplemental automated Fair Housing compliance scan (advisory only — not a substitute for the qualified human reviewer gate). All external transmissions are human-initiated only — no scheduled, automated, or fallback publication paths exist.
**Inputs:** `MarketingReport` records with both agent approval and seller/landlord approval satisfied; Fair Housing compliance scan results (advisory)
**Outputs:** Publication event records; qualified human reviewer sign-off records; controlled transmission to approved external channels
**AI:** Supplemental compliance scanning only — advisory; not decision-making
**Prohibited outputs:** Autonomous or scheduled publication; advertising platform audience uploads; lookalike seed files; demographic segment files; any publication path that bypasses the qualified human reviewer gate
**Governance document required:** Yes — must document compliance scan scope and advisory-only status, qualified reviewer definition and sign-off workflow, approved external channel list, prohibited channel list, publication event audit schema, and transmission controls

---

## Verification Report

The following checklist confirms the scope and completeness of this Phase X architecture document.

### Document Completeness

- [x] Document created: `docs/PROPERTY_DNA_PHASE_X_AI_MARKETING_INTELLIGENCE_IMPLEMENTATION_ARCHITECTURE.md`
- [x] Section 1 — Generation Workflow: Present (end-to-end nine-stage flow documented)
- [x] Section 2 — Readiness Gate: Present (gate condition, failure behavior, fresh evaluation requirement, enforcement point)
- [x] Section 3 — AI Request Lifecycle: Present (approved inputs, report contract requirement, prompt template requirements, prohibited external data, AI response validation)
- [x] Section 4 — Attribution Verification: Present (attribution requirement, unattributable content removal rule, `attribution_verified` flag, failure handling, `neutral_feature_summary` as reference)
- [x] Section 5 — Hallucination Prevention: Present (no invented facts, no inferred facts, no estimates, no missing-data fill, empty input → empty output, prompt-level enforcement, no superlatives)
- [x] Section 6 — Human Review Workflow: Present (two-stage: agent review then seller/landlord approval; no auto-approve; no approval by inaction; Fair Housing compliance gate; no bypass paths)
- [x] Section 7 — Audit Workflow: Present (four record types: generation audit, review audit, readiness failure audit, attribution failure audit; integrity and retention requirements)
- [x] Section 8 — Error Handling: Present (OpenAI failures, attribution failures, readiness failures, incomplete output; no partial report publication rule)
- [x] Section 9 — Future Database Requirements: Present (three entities: `MarketingReport`, `MarketingReportVersion`, `MarketingReportAudit`; planning-only, no migrations)
- [x] Section 10 — Future Phase Sequence: Present (six phases: XA through XF, each with type, what it does, inputs, outputs, AI presence, and governance document requirement)
- [x] All ten required sections are present

### Scope Boundary Confirmation

- [x] No PHP code files were created or modified
- [x] No routes were added or changed
- [x] No controllers were created or modified
- [x] No Blade views or UI elements were created or modified
- [x] No Livewire components were created or modified
- [x] No JavaScript was written
- [x] No database migrations were created
- [x] No schema changes were made
- [x] No AI system, language model, or ML inference was implemented
- [x] No OpenAI or LLM integration was introduced
- [x] No prompt was created or executed
- [x] No listing description was generated
- [x] No ad copy, social media content, or marketing output was generated
- [x] No audience targeting or advertising recommendations were implemented

### Architecture Content Confirmation

- [x] Generation workflow documented (Section 1 — nine stages from `PropertyDnaProfile` through publication, with each stage's required state and mandatory behavior)
- [x] Readiness gate documented (Section 2 — `is_marketing_ready === true` required; fresh evaluation mandatory; all failure outcomes specified)
- [x] AI request lifecycle documented (Section 3 — approved inputs enumerated by source service, report contract requirement, prompt template requirements, prohibited external data table, AI response validation steps)
- [x] Attribution verification documented (Section 4 — attribution requirement, approved `source_section` values, unattributable content removal rule, `attribution_verified` flag logic, failure handling)
- [x] Hallucination prevention documented (Section 5 — seven rules covering invented facts, inferred facts, estimates, missing-data fill, empty-input/empty-output, prompt-level enforcement, prohibited language)
- [x] Human review workflow documented (Section 6 — agent review actions and status transitions, seller/landlord approval gate, Fair Housing compliance gate, no-bypass rule)
- [x] Audit workflow documented (Section 7 — four record types with required fields, integrity constraints, retention period, queryability, export requirement)
- [x] Error handling documented (Section 8 — four error categories: AI model failure, attribution failure, readiness failure, incomplete output; no-partial-publication rule)
- [x] Future database requirements documented (Section 9 — three entities with field-level design targets; planning-only; no migrations)
- [x] Future phase sequence documented (Section 10 — phases XA through XF with type, purpose, inputs, outputs, AI presence, and governance document requirement for each)

---

**Document confirmed complete. No code was written. No AI was implemented. This document is an architecture and planning specification only.**
