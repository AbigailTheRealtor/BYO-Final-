# Property DNA Phase W — AI Marketing Intelligence Report Contract

**Document Date:** 2026-05-29
**Phase:** W — AI Marketing Intelligence Report Contract
**Preceding Phases:** P — Deterministic Marketing Context Builder, Q — Marketing Brief Readiness Plan, R — Deterministic Property Marketing Brief Builder, S — Internal Brief Inspector / Admin Preview, T — Agent-Reviewed Brief UI, U — AI Drafting Guardrails Plan, V — AI Marketing Intelligence Governance & Readiness Plan
**Type:** Contract and planning document only — no code, no routes, no schema changes, no AI calls, no UI

---

## 1. Purpose

Phases P through V have established a complete, deterministic, auditable pipeline for producing structured marketing context (Phase P), a named-section marketing brief (Phase R), a readiness assessment (Phase U), and a comprehensive governance plan (Phase V) that governs all future AI-assisted phases. These foundations are entirely AI-free, produce no narrative text, and serve as the exclusive factual input for any AI system introduced in a later phase.

Phase W defines the exact structure, contract, and constraints of the AI Marketing Intelligence Report — the structured output object that any future AI-assisted phase must produce when called with Phase P–U service outputs as input. Before a single line of AI integration code is written, this document establishes:

- Which deterministic service outputs are the sole approved inputs to the report generation call
- The exact JSON shape of the report object, including all always-present keys and their types
- The required content and prohibited content for each named output section
- The source attribution contract that traces every statement to a specific Phase R context key
- The hallucination prevention rules that govern what the AI may and may not assert
- The explainability requirements that allow agents and reviewers to audit every output claim
- The human review gates that must be in place before any report section is used or surfaced
- The audit record fields that must be created for each report generation event
- The implementation requirements that Phase X and all subsequent phases must satisfy

This is a contract and planning document only. No PHP files are modified, no routes are added, no UI is created, no AI or OpenAI integration is introduced, and no database schema changes are made in Phase W. Implementation of the AI report generation feature begins in a separately approved later phase and must not proceed without satisfying every constraint documented here.

---

## 2. Approved Inputs

The AI Marketing Intelligence Report may only be generated when the following three structured service outputs are available and have been freshly computed for the target listing. No other data source, database field, request parameter, session value, or external API response may be provided to any AI model as part of the report generation call.

### 2.1 Approved Input: `PropertyMarketingBriefService::build()` Output (Phase R)

The full structured array produced by `PropertyMarketingBriefService::build(PropertyDnaProfile $profile)` is the primary factual input to the AI report generation call. All nine named sections of the Phase R brief may be provided to the AI as structured context.

| Section Key | What It Contains | Approved for AI Input |
|---|---|---|
| `property_attribute_context` | Named attribute bucket arrays (property type, style, condition, amenities, parking, features, policies, community, use classification, governance) | Yes |
| `transaction_context` | Named transaction bucket arrays (timing, transaction structure, financing, presentation) | Yes |
| `quantitative_context` | Measurable property attributes (bedroom count, bathroom count, square footage, lot size, price, year built) | Yes |
| `marketing_asset_checklist` | Presence/absence checklist of available marketing assets | Yes |
| `missing_information_checklist` | Checklist of profile dimensions with no recorded data | Yes |
| `seller_landlord_questions` | Pre-written questions for empty or sparse profile dimensions | Yes — as context only, not as questions for the AI to answer autonomously |
| `listing_preparation_notes` | Factual preparation notes derived from timing, transaction structure, and financing tags | Yes |
| `neutral_feature_summary` | Factual attribute and quantitative summary entries | Yes |
| `summary` | Integer counts (attribute group count, transaction group count, quantitative record count, etc.) | Yes — as metadata only |

### 2.2 Approved Input: `PropertyMarketingReadinessService::build()` Output (Phase U)

The structured readiness review array produced by `PropertyMarketingReadinessService::build(PropertyDnaProfile $profile)` is approved as a supplemental input that governs whether a report generation call is permitted at all.

| Field | What It Contains | Approved for AI Input |
|---|---|---|
| `is_marketing_ready` | Boolean — all three required groups present | Yes — as the readiness gate condition only |
| `present_groups` | Names of information groups detected as present | Yes |
| `missing_groups` | Names of information groups detected as missing | Yes — to inform the AI which data is absent |
| `review_items` | Per-group status and optional missing reason | Yes |
| `summary.present_group_count` | Integer count of present groups | Yes — as metadata only |
| `summary.missing_group_count` | Integer count of missing groups | Yes — as metadata only |

### 2.3 Approved Input: `PropertyMarketingContextService::build()` Output (Phase P)

The Phase P marketing context array produced by `PropertyMarketingContextService::build(PropertyDnaProfile $profile)` is approved as a supplemental source for resolving individual tag-level attribution during report generation, specifically when an AI output claim must be traced to a specific tag record in `attribute_context` or `transaction_context`.

| Section Key | Approved for AI Input |
|---|---|
| `attribute_context` (all named buckets) | Yes — for source attribution resolution only |
| `transaction_context` (all named buckets) | Yes — for source attribution resolution only |
| `quantitative_context` | Yes — for source attribution resolution only |
| `summary` | Yes — as metadata only |

### 2.4 Readiness Gate — Prerequisite for Any Report Generation Call

A report generation call is permitted only when `PropertyMarketingReadinessService::build($profile)['is_marketing_ready']` equals `true`. This means all three required information groups — Property Attributes, Transaction Details, and Quantitative Data — must be present. A readiness gate failure must abort the report generation call entirely. The gate must be re-evaluated freshly on every call; a cached or session-persisted gate result must never be used as a substitute.

### 2.5 Prohibited Input Sources

The following data sources are categorically prohibited as inputs to any report generation call under Phase W or any subsequent phase, regardless of availability.

| Prohibited Input | Prohibition Basis |
|---|---|
| Neighborhood demographic data (census, ACS, or similar) | Fair Housing Act §804(c); HUD steering guidelines |
| School demographic or rating data | Fair Housing Act; HUD established case law on school-based steering |
| Buyer or tenant identity, credit history, or financial records | FCRA; ECOA; CFPB guidance |
| Protected-class signals of any kind (race, color, national origin, religion, sex, familial status, disability) | Fair Housing Act; 42 U.S.C. §3604 |
| Income tier, wealth tier, or socioeconomic classification | Fair Housing Act disparate impact doctrine; ECOA |
| External real estate market data not present in the Phase R brief | Out of scope; introduces unaudited inference |
| Any data not produced by an approved Phase P, R, or U service output | Scope boundary — the AI may only see what the deterministic pipeline has already produced |

---

## 3. Report Structure

The AI Marketing Intelligence Report is a structured JSON object. When a report generation call succeeds, it must produce exactly this object shape. All seven top-level keys are always present — no key may be null, absent, or conditionally omitted based on input completeness or AI model behavior. If the AI call fails or the attribution layer is unavailable, no partial report object may be stored or surfaced.

### 3.1 Exact JSON Report Contract

```json
{
  "report_id": "<string — unique identifier for this report instance>",
  "generated_at": "<string — UTC ISO 8601 timestamp of the AI call>",
  "listing_context": {
    "listing_id": "<string/integer — identifier of the property listing for which this report was generated>",
    "profile_id": "<string/integer — identifier of the PropertyDnaProfile used as input>"
  },
  "readiness_snapshot": {
    "is_marketing_ready": "<boolean — value from Phase U output at generation time>",
    "present_groups": ["<string>"],
    "missing_groups": ["<string>"]
  },
  "sections": {
    "property_feature_narrative": {
      "draft_text": "<string — AI-generated draft paragraph; empty string if AI produced no output for this section>",
      "status": "<string — always 'pending_review' at generation time>",
      "source_attribution": [
        {
          "source_section": "<string — Phase R output key, e.g. 'property_attribute_context', 'quantitative_context'>",
          "source_records": ["<string — specific tag, trait, or value string from Phase R>"]
        }
      ]
    },
    "transaction_terms_summary": {
      "draft_text": "<string — AI-generated draft summary; empty string if AI produced no output for this section>",
      "status": "<string — always 'pending_review' at generation time>",
      "source_attribution": [
        {
          "source_section": "<string — Phase R output key, e.g. 'transaction_context'>",
          "source_records": ["<string — specific tag or note string from Phase R>"]
        }
      ]
    },
    "marketing_asset_statement": {
      "draft_text": "<string — AI-generated asset availability statement; empty string if no assets present>",
      "status": "<string — always 'pending_review' at generation time>",
      "source_attribution": [
        {
          "source_section": "<string — always 'marketing_asset_checklist' when populated>",
          "source_records": ["<string — checklist entry tag strings from Phase R>"]
        }
      ]
    },
    "missing_information_note": {
      "draft_text": "<string — AI-generated internal note for agent; empty string if no missing groups>",
      "status": "<string — always 'internal_note' — not subject to agent approval gate>",
      "source_attribution": [
        {
          "source_section": "<string — 'missing_information_checklist' or 'missing_groups'>",
          "source_records": ["<string — checklist entry strings or group name strings from Phase R/U>"]
        }
      ]
    },
    "listing_preparation_summary": {
      "draft_text": "<string — AI-generated preparation summary for agent; empty string if no preparation notes>",
      "status": "<string — always 'pending_review' at generation time>",
      "source_attribution": [
        {
          "source_section": "<string — always 'listing_preparation_notes' when populated>",
          "source_records": ["<string — preparation note strings from Phase R>"]
        }
      ]
    }
  },
  "generation_metadata": {
    "ai_model": "<string — model identifier and version, e.g. 'gpt-4o-2024-08-06'>",
    "prompt_template_version": "<string — version identifier of the prompt template used>",
    "phase_r_brief_version": "<string — hash or version token of the Phase R brief snapshot provided>",
    "phase_u_readiness_version": "<string — hash or version token of the Phase U readiness snapshot provided>"
  },
  "attribution_verified": "<boolean — true only when all sections with non-empty draft_text have at least one source_attribution entry>"
}
```

### 3.2 Key Definitions

| Key | Type | Always Present | Description |
|---|---|---|---|
| `report_id` | string | Yes | Unique identifier assigned at generation time. Must be stored as the primary key of the generation audit record. |
| `generated_at` | string | Yes | UTC ISO 8601 timestamp of the AI call. Must match the `generated_at` field of the corresponding generation audit record. |
| `listing_context` | object | Yes | Contains `listing_id` and `profile_id` — the identifiers of the property listing and the `PropertyDnaProfile` used as input. Makes the report object self-describing; must match the corresponding fields in the generation audit record. |
| `readiness_snapshot` | object | Yes | A copy of the relevant Phase U fields at the moment the call was made. Must not be updated after generation. |
| `sections` | object | Yes | Always contains exactly five named section keys. Each section is always present regardless of whether the AI produced content for it. |
| `generation_metadata` | object | Yes | Model and prompt provenance information. Must be populated before the report object is stored. |
| `attribution_verified` | boolean | Yes | Set to `true` only when every section with a non-empty `draft_text` carries at least one `source_attribution` entry. Set to `false` if any attributed section is missing attribution. A report with `attribution_verified: false` must not be surfaced to any user. |

### 3.3 Section Status Values

The `status` field within each section entry is constrained to the following values. No other status value is permitted.

| Status Value | Meaning | Who Sets It |
|---|---|---|
| `pending_review` | Draft generated by AI; awaiting agent review | Set at generation time; never set by any automated process after that |
| `approved` | Agent has reviewed and explicitly approved this section | Set only by an explicit, recorded agent approval action |
| `revised` | Agent has edited and approved a modified version of this section | Set only when agent submits a modified version |
| `rejected` | Agent has explicitly rejected this section | Set only by an explicit, recorded agent rejection action |
| `internal_note` | Internal-only content; not subject to the agent approval gate | Used exclusively for `missing_information_note` |

---

## 4. Required Output Sections

This section defines the allowed content, prohibited content, and derivation rules for each of the five named report sections.

### 4.1 `property_feature_narrative`

**Purpose:** A factual, neutral paragraph describing the physical features of the property.

**Allowed content:**
- Factual statements about physical property attributes derived from `property_attribute_context` records (property type, style, condition, amenities, parking, features, policies, community, use classification, governance)
- Factual statements about measurable attributes derived from `quantitative_context` records (bedroom count, bathroom count, square footage, lot size, year built)
- Neutral descriptive language that mirrors the Phase R tag strings without embellishment

**Prohibited content:**
- Any reference to the surrounding neighborhood, community demographics, or local population
- Any characterization of who the property is suited to, designed for, or likely to appeal to
- Any protected-class language or coded demographic proxy language
- Any prediction of sale price, days on market, or transaction outcome
- Any data not traceable to a Phase R `property_attribute_context` or `quantitative_context` record
- Superlatives, marketing hyperbole, or persuasive framing (e.g., "stunning," "rare opportunity," "ideal for")

**Source attribution requirement:** Every factual claim in this section must be traced to one or more records in `property_attribute_context` or `quantitative_context`. The `source_attribution` array for this section must enumerate those records.

### 4.2 `transaction_terms_summary`

**Purpose:** A factual summary of the transaction or lease terms applicable to this listing.

**Allowed content:**
- Factual statements about timing, availability dates, and move-in conditions derived from `transaction_context.timing` records
- Factual statements about transaction or lease structure derived from `transaction_context.transaction_structure` records (e.g., lease option, lease-purchase arrangements)
- Factual statements about financing arrangements derived from `transaction_context.financing` records (e.g., seller financing, assumable loan availability)

**Prohibited content:**
- Any characterization of who the transaction terms are designed for, who would benefit from them, or who is most suited given the terms
- Any suitability scoring, buyer ranking, or tenant qualification inference
- Any prediction of financing approval likelihood or creditworthiness assessment
- Any data not traceable to a Phase R `transaction_context` record
- Any negotiation advice or strategic recommendations

**Source attribution requirement:** Every factual claim in this section must be traced to one or more records in `transaction_context`. The `source_attribution` array for this section must enumerate those records.

### 4.3 `marketing_asset_statement`

**Purpose:** A factual, neutral statement listing which marketing assets are available for this listing.

**Allowed content:**
- Factual statements about which marketing assets are present, derived from `marketing_asset_checklist` entries

**Prohibited content:**
- Any claims about marketing strategy, advertising approach, audience reach, or expected campaign results
- Any recommendation of advertising channels, platforms, or spending
- Any data not traceable to a Phase R `marketing_asset_checklist` entry
- Any statement about assets that are absent — this section describes presence only

**Empty case:** If `marketing_asset_checklist` is empty (no assets recorded), `draft_text` must be an empty string. The section must still be present in the report object.

**Source attribution requirement:** Every statement in this section must be traced to a `marketing_asset_checklist` entry. The `source_attribution` array must list the tag strings of those entries.

### 4.4 `missing_information_note`

**Purpose:** An internal note for the listing agent identifying which profile dimensions are incomplete.

**Allowed content:**
- A plain-language summary of which named information groups or buckets are missing, derived from `missing_information_checklist` and `missing_groups`
- An internal-only recommendation that the agent complete the missing dimensions before requesting a final brief

**Prohibited content:**
- Any content intended for sellers, buyers, tenants, or the public
- Any suitability characterization or audience implication based on what data is absent
- Any data not traceable to a Phase R `missing_information_checklist` entry or a Phase U `missing_groups` value

**Visibility constraint:** This section must never be surfaced in any seller-facing, buyer-facing, tenant-facing, or public view. It is an internal agent tool only.

**Status constraint:** This section uses `status: "internal_note"` and is not subject to the agent approval gate. It does not require affirmative agent approval before being displayed to the agent in an internal context.

**Empty case:** If no information groups or buckets are missing, `draft_text` must be an empty string. The section must still be present in the report object.

**Source attribution requirement:** Every reference to a missing dimension must be traced to a `missing_information_checklist` entry or a Phase U `missing_groups` value.

### 4.5 `listing_preparation_summary`

**Purpose:** A factual, internal summary of preparation steps the listing agent should complete before publishing.

**Allowed content:**
- A plain-language summary of preparation actions derived from `listing_preparation_notes` entries
- Factual restatements of preparation note strings already present in Phase R output

**Prohibited content:**
- Any negotiation advice, pricing strategy, or market timing recommendation
- Any characterization of prospective buyers, tenants, or their likely behavior
- Any data not traceable to a Phase R `listing_preparation_notes` entry

**Visibility constraint:** This section is agent-facing and internal only. It must not be surfaced in any seller-facing, buyer-facing, tenant-facing, or public view without a separately approved visibility phase.

**Empty case:** If `listing_preparation_notes` is empty, `draft_text` must be an empty string. The section must still be present in the report object.

**Source attribution requirement:** Every preparation action referenced must be traced to a `listing_preparation_notes` entry. The `source_attribution` array must enumerate those entries.

---

## 5. Source Attribution

Every non-empty `draft_text` in the AI Marketing Intelligence Report must be fully attributable to specific records in the Phase R brief or Phase U readiness output. This section defines the attribution contract that governs how that traceability is established and enforced.

### 5.1 Attribution Contract

Each `source_attribution` entry within a section must contain:

| Field | Type | Description |
|---|---|---|
| `source_section` | string | The Phase R or Phase U output key that provided the factual basis (e.g., `property_attribute_context`, `transaction_context`, `quantitative_context`, `marketing_asset_checklist`, `missing_information_checklist`, `listing_preparation_notes`, `missing_groups`) |
| `source_records` | array of strings | The specific tag, trait, value, or entry strings from Phase R or Phase U that are reflected in the corresponding `draft_text` |

### 5.2 Approved Source Section Values

The `source_section` field is constrained to the following values. No other value is permitted.

| Approved `source_section` Value | Phase | Report Section(s) That May Reference It |
|---|---|---|
| `property_attribute_context` | R | `property_feature_narrative` |
| `transaction_context` | R | `transaction_terms_summary` |
| `quantitative_context` | R | `property_feature_narrative` |
| `marketing_asset_checklist` | R | `marketing_asset_statement` |
| `missing_information_checklist` | R | `missing_information_note` |
| `listing_preparation_notes` | R | `listing_preparation_summary` |
| `missing_groups` | U | `missing_information_note` |

### 5.3 Unattributable Claims Must Be Excluded

If an AI-generated `draft_text` contains any factual claim that cannot be traced to a Phase R or Phase U source record, that claim must be excluded from the draft before the report object is stored. The implementation layer responsible for generating the report must provide a mechanism for detecting and removing unattributable claims before the report is persisted.

### 5.4 Attribution Verification Flag

The `attribution_verified` boolean in the report object root must be set to `true` only when all sections with non-empty `draft_text` carry at least one `source_attribution` entry. If any non-empty section lacks attribution entries, `attribution_verified` must be set to `false` and the report must not be surfaced to any user until the attribution gap is resolved or the affected `draft_text` is cleared.

### 5.5 Neutral Feature Summary as Verification Reference

The Phase R `neutral_feature_summary` section — which contains pre-computed, deterministic factual summaries of attribute and quantitative records — serves as a verification reference for the `property_feature_narrative` section. Any claim in `property_feature_narrative` that cannot be matched to a `neutral_feature_summary` entry or a raw `property_attribute_context` / `quantitative_context` record is presumptively unattributable and must be excluded.

---

## 6. Hallucination Prevention

The AI Marketing Intelligence Report must contain only facts that originate directly from Phase R and Phase U service outputs. This section defines the rules that prevent the introduction of fabricated, inferred, or estimated content.

### 6.1 No Facts Outside Phase R and Phase U Input

The AI model generating the report must be instructed, at the prompt level, that it may only assert facts that are present in the structured Phase R and Phase U inputs provided to it. The AI must not:

- Add property facts that are plausible but not recorded (e.g., inferring a garage because the property type is "single family")
- Fill empty Phase R buckets with estimated or industry-average data
- Use training data knowledge about the property's location, neighborhood, or market
- Assert specific values (bedroom count, square footage, price) that differ from those in `quantitative_context`
- Extrapolate from partial data (e.g., inferring a full bathroom count from a partial record)

### 6.2 Empty Input Sections Must Produce Empty Draft Text

When a Phase R section that a report section depends on is empty, the corresponding `draft_text` must be an empty string. The AI must not generate content for a section whose source data is entirely absent. This rule applies to all five report sections.

| Report Section | Phase R Dependency | Required Behavior When Dependency Is Empty |
|---|---|---|
| `property_feature_narrative` | `property_attribute_context`, `quantitative_context` | `draft_text` must be empty string |
| `transaction_terms_summary` | `transaction_context` | `draft_text` must be empty string |
| `marketing_asset_statement` | `marketing_asset_checklist` | `draft_text` must be empty string |
| `missing_information_note` | `missing_information_checklist`, `missing_groups` | `draft_text` must be empty string |
| `listing_preparation_summary` | `listing_preparation_notes` | `draft_text` must be empty string |

### 6.3 Prompt-Level Hallucination Prohibitions

The prompt template used to generate the AI Marketing Intelligence Report must include explicit, unconditional prohibitions against each of the following:

- Asserting any property fact not present in the provided Phase R input
- Describing the neighborhood, surrounding area, or community composition
- Characterizing any prospective buyer, tenant, or party
- Using any protected-class language or demographic proxy
- Generating predictions, estimates, or probabilities of any kind
- Fabricating values for empty or missing data fields

These prohibitions must be versioned alongside the prompt template and must appear in the generation audit record as part of the `prompt_template_version` field.

### 6.4 No Confidence Scores or Probability Statements

The AI must not include confidence scores, probability estimates, certainty qualifiers (e.g., "likely," "probably," "approximately"), or any hedged claim that implies the AI is estimating rather than reporting a recorded fact. All statements in the report must be grounded in Phase R records and expressed as direct, factual descriptions of those records.

### 6.5 Fabricated Data Creates Disclosure Liability

Any AI-generated report section that contains a fabricated or inferred property fact creates material misrepresentation liability under real estate disclosure law. The implementation layer must treat hallucination prevention as a legal compliance requirement, not a quality preference.

---

## 7. Explainability

Every AI-generated section of the Marketing Intelligence Report must be explainable to a listing agent who is not a developer. An agent reviewing a draft must be able to identify, for each statement in the draft, which specific Phase R record served as its factual source.

### 7.1 Source Attribution Must Be Agent-Readable

The `source_attribution` entries for each section must be rendered in the review UI in a format that a non-technical agent can read and verify. Tag strings, trait keys, and Phase R checklist entries must be displayed alongside the draft text so the agent can confirm the correspondence without accessing raw JSON.

### 7.2 Unexplainable Statements Must Be Removed Before Agent Review

Any statement in a generated `draft_text` that does not correspond to a `source_attribution` entry must be removed from the draft before it is displayed to any agent. This removal must occur at the implementation layer, not at the agent review layer. An agent must never be asked to approve a draft that contains unexplained claims.

### 7.3 Model and Prompt Version Are Required Explainability Metadata

The `ai_model` and `prompt_template_version` fields in `generation_metadata` are not optional metadata — they are required components of the explainability contract. An agent or reviewer who questions why a particular statement appeared in a draft must be able to identify which model version and prompt template produced it. These fields must be stored with the report object and retained for the life of the listing.

### 7.4 No Black-Box Output Storage

The system must not store a report object that was generated without corresponding `generation_metadata`. If the AI call completes but the model identifier or prompt template version cannot be determined, the report object must be discarded and not persisted. A generation call that produces output without traceable provenance is a contract violation.

### 7.5 Phase R Brief Snapshot as Explainability Anchor

The full Phase R brief input provided to the AI at the time of the call must be stored as a serialized snapshot in the generation audit record (see Section 9). This snapshot serves as the authoritative reference for attribution verification. If a discrepancy arises between a draft claim and the current Phase R brief state (because the profile was updated after the call), the snapshot — not the current brief — is the correct reference for evaluating the draft.

---

## 8. Human Review

No AI-generated section of the Marketing Intelligence Report may be used, surfaced publicly, transmitted externally, or included in any listing packet without passing the human review gates defined in this section. These gates apply regardless of how confident the AI output appears, how complete the Phase R input was, or how little time is available.

### 8.1 Agent Review Gate — Required Before Any External Use

No `draft_text` from `property_feature_narrative`, `transaction_terms_summary`, `marketing_asset_statement`, or `listing_preparation_summary` may be submitted to any listing platform, MLS, syndication service, or external partner without explicit review and approval by a licensed real estate agent or broker. The approval must be an affirmative action recorded in the system that sets the section `status` to `approved` or `revised`.

### 8.2 Seller / Landlord Review Gate — Required Before Publication

No AI-generated property description, listing narrative, or marketing content from this report may be published or distributed publicly without review and approval by the seller or landlord whose property it describes. This gate is separate from and in addition to the agent review gate in Section 8.1.

### 8.3 No Auto-Approve Path

The system must not include any code path, scheduled job, timeout-based fallback, or implicit state transition that moves a section `status` from `pending_review` to `approved` without an explicit human action. This prohibition applies to all five report sections.

### 8.4 No Approval by Inaction

A section is not approved because no one has rejected it within a time window. A section status of `pending_review` must remain `pending_review` indefinitely until an affirmative human action is recorded. Systems that auto-approve based on elapsed time are prohibited.

### 8.5 Draft Label Persistence

Every section with a `status` of `pending_review` must be displayed with a visible "AI-generated draft — pending agent review" label in any agent-facing view. This label must not disappear, fade, be removed, or be toggled off by any automated process. The label must remain until the section status transitions to `approved`, `revised`, or `rejected` via an explicit human action.

### 8.6 `missing_information_note` Is Exempt from Agent Approval Gate

The `missing_information_note` section uses `status: "internal_note"` and is not subject to the agent approval gate defined in Section 8.1. It may be displayed to the listing agent in an internal context without requiring an affirmative approval action. It must never be displayed to sellers, buyers, tenants, or the public regardless of its status.

### 8.7 Fair Housing Compliance Review — Required Before Public Distribution

Any section content from this report that will appear in advertising, printed materials, or any form of public distribution must be reviewed specifically for Fair Housing compliance by a qualified reviewer before publication. This is a separate gate from the agent approval in Section 8.1 and must be completed in addition to it.

### 8.8 Revision Tracking

If a reviewing agent modifies a section's `draft_text`, the modified version must be stored as a distinct, versioned record. The original AI-generated text must be retained alongside all intermediate revisions in the audit trail. The final approved text must be clearly distinguished from the original AI draft in all stored records and all agent-facing views.

---

## 9. Audit Requirements

Every AI Marketing Intelligence Report generation event must produce a complete set of audit records. These records must be created atomically with the report object — if the audit record cannot be created, the report generation call must be treated as failed and the report object must not be persisted.

### 9.1 Generation Audit Record

For each successful report generation call, the following fields must be recorded:

| Field | Type | Description |
|---|---|---|
| `report_id` | string | The unique identifier of the generated report object |
| `listing_id` | string/integer | The identifier of the property listing for which the report was generated |
| `profile_id` | string/integer | The identifier of the `PropertyDnaProfile` used as input |
| `generated_at` | string | UTC ISO 8601 timestamp of the AI call |
| `ai_model` | string | The AI model identifier and version string (e.g., `gpt-4o-2024-08-06`) |
| `prompt_template_version` | string | The version identifier of the prompt template used |
| `report_version` | string | The version identifier of the report contract specification this record was generated against (e.g., `phase-w-v1`) |
| `phase_r_brief_snapshot` | text/JSON | A serialized snapshot of the Phase R brief array provided to the AI as input |
| `phase_u_readiness_snapshot` | text/JSON | A serialized snapshot of the Phase U readiness review array at the time of the call |
| `attribution_verified` | boolean | The value of `attribution_verified` from the generated report object |

### 9.2 Agent Review Audit Record

For each agent review action (approval, rejection, or revision submission), the following fields must be recorded:

| Field | Type | Description |
|---|---|---|
| `report_id` | string | The report object being reviewed |
| `section_key` | string | The name of the section being reviewed (e.g., `property_feature_narrative`) |
| `reviewing_agent_id` | string/integer | The identifier of the agent performing the review |
| `action` | string | One of: `approved`, `rejected`, `revised` |
| `reviewed_at` | string | UTC ISO 8601 timestamp of the review action |
| `original_draft_text` | text | The AI-generated `draft_text` at the time of review (snapshot) |
| `final_text` | text | The final text submitted by the agent — identical to `original_draft_text` for approvals; agent-modified text for revisions; empty or null for rejections |

### 9.3 Readiness Gate Failure Audit Record

When a report generation call is aborted because the readiness gate was not satisfied, the following fields must be recorded:

| Field | Type | Description |
|---|---|---|
| `listing_id` | string/integer | The listing for which the call was attempted |
| `profile_id` | string/integer | The profile evaluated by the readiness gate |
| `gate_evaluated_at` | string | UTC ISO 8601 timestamp |
| `missing_groups` | array of strings | The group names that were absent |
| `gate_result` | boolean | Always `false` for this record type |

### 9.4 Attribution Failure Audit Record

When a report generation call produces a report object with `attribution_verified: false`, the following fields must be recorded:

| Field | Type | Description |
|---|---|---|
| `report_id` | string | The report object with the attribution failure |
| `unattributed_sections` | array of strings | The section keys where `draft_text` was non-empty but `source_attribution` was empty |
| `detected_at` | string | UTC ISO 8601 timestamp |
| `action_taken` | string | One of: `report_discarded`, `report_held_pending_resolution` |

### 9.5 Retention Period

All audit records must be retained for a minimum of five years from the date of creation, consistent with HUD Fair Housing recordkeeping guidance and applicable state real estate law. Deletion of audit records is prohibited unless required by a specific legal obligation.

### 9.6 Audit Record Integrity

All audit records must be append-only. No existing audit record may be modified or deleted by any application process. If an error correction is required, a new corrective record must be created referencing the original, not overwriting it.

### 9.7 Report Version Field

Every generation audit record must include a `report_version` field identifying which version of this report contract specification was active at the time of the call. This field enables future auditors to determine which contract constraints governed a given generation event, even if this document is later revised. The version identifier for the contract defined in this document is `phase-w-v1`.

---

## 10. Future Implementation Requirements

Phase X and all subsequent phases that implement or extend the AI Marketing Intelligence Report must satisfy all of the constraints listed in this section. No implementation phase may begin without its own approved governance document, and no governance document for a subsequent phase may relax any constraint defined in Phases V or W without explicit justification reviewed by a qualified compliance authority.

### 10.1 Phase V Governance Must Be Fully Respected

Every implementation phase must comply with the full Phase V AI Marketing Intelligence Governance Plan, including:

- All approved and prohibited AI input sources (Phase V Section 2)
- All approved and prohibited AI output types (Phase V Sections 4 and 5)
- All Fair Housing safeguards (Phase V Section 6)
- All explainability requirements (Phase V Section 7)
- All human review gates (Phase V Section 8)
- All auditability requirements (Phase V Section 9)

No implementation phase may treat Phase V as advisory. Every Phase V constraint is a hard requirement.

### 10.2 Readiness Gate Is Non-Negotiable

Every implementation phase that triggers a report generation call must enforce the readiness gate defined in Phase V Section 3 and restated in Phase W Section 2.4. The gate must be evaluated freshly on every call from the live Phase U service output. A gate failure must abort the call unconditionally.

### 10.3 Report Contract Shape Is Non-Negotiable

Every implementation phase must produce a report object that conforms exactly to the JSON contract defined in Phase W Section 3. All seven top-level keys must be present. All five section keys within `sections` must be present. No key may be null or absent. No additional keys may be added to the contract without a Phase W amendment.

### 10.4 Fair Housing Safeguards Apply at Every Layer

Fair Housing safeguards must be enforced at three independent layers: in the prompt template (prohibiting prohibited content), in the attribution verification step (detecting unattributable claims), and in the human review gate (requiring qualified reviewer sign-off before public distribution). No single layer may be relied upon as the sole Fair Housing enforcement mechanism.

### 10.5 Explainability Contract Is Binding on All Sections

The source attribution contract defined in Phase W Section 5 applies to every section in every report object generated under any implementation phase. No section may contain a factual claim without a corresponding `source_attribution` entry. A report with `attribution_verified: false` must not be surfaced to any user.

### 10.6 Hallucination Prevention Rules Apply to Prompt Design

The hallucination prevention rules defined in Phase W Section 6 must be implemented at the prompt level in every implementation phase. The prompt template must include explicit, unconditional prohibitions against each category of fabricated, inferred, or estimated content listed in Section 6.3. Prompt templates must be version-controlled and referenced in every generation audit record.

### 10.7 Human Review Gates Cannot Be Bypassed

The human review gates defined in Phase W Section 8 — agent approval, seller/landlord approval, and Fair Housing compliance review — must be implemented as affirmative-action-required state transitions in every implementation phase. No automated process, timeout, or fallback path may substitute for any of these gates.

### 10.8 Audit Records Must Be Created Before Report Is Surfaced

The generation audit record defined in Phase W Section 9.1 must be created and persisted before the report object is surfaced to any user. If the audit record cannot be created, the report generation must be treated as failed. No report object may be displayed, transmitted, or stored without a corresponding audit record.

### 10.9 This Document Is the Authoritative Contract Reference

When an implementation phase produces code that generates, stores, reviews, or transmits an AI Marketing Intelligence Report, the specification in this document is the authoritative reference for the report's structure, content rules, attribution rules, and audit requirements. If any conflict arises between an implementation phase's governance document and this Phase W contract, this document takes precedence unless a formal Phase W amendment has been approved and documented.

---

## Verification Report

The following checklist confirms the scope and completeness of this Phase W contract document.

### Document Completeness

- [x] Document created: `docs/PROPERTY_DNA_PHASE_W_AI_MARKETING_INTELLIGENCE_REPORT_CONTRACT.md`
- [x] Section 1 — Purpose: Present
- [x] Section 2 — Approved Inputs: Present
- [x] Section 3 — Report Structure: Present (exact JSON contract with all seven always-present keys)
- [x] Section 4 — Required Output Sections: Present (allowed and prohibited content per each of the five sections)
- [x] Section 5 — Source Attribution: Present
- [x] Section 6 — Hallucination Prevention: Present
- [x] Section 7 — Explainability: Present
- [x] Section 8 — Human Review: Present
- [x] Section 9 — Audit Requirements: Present
- [x] Section 10 — Future Implementation Requirements: Present
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

### Contract Content Confirmation

- [x] Source attribution contract documented (Section 5 — `source_section` and `source_records` fields required per section, approved `source_section` values enumerated, unattributable claims exclusion rule, `attribution_verified` flag semantics)
- [x] Hallucination prevention documented (Section 6 — no facts outside Phase R/U, empty input produces empty draft, prompt-level prohibitions, no confidence scores, disclosure liability acknowledgment)
- [x] JSON report contract shape documented (Section 3 — all seven keys: `report_id`, `generated_at`, `listing_context`, `readiness_snapshot`, `sections`, `generation_metadata`, `attribution_verified`, with exact types and constraints)
- [x] Five named output sections documented (Section 4 — `property_feature_narrative`, `transaction_terms_summary`, `marketing_asset_statement`, `missing_information_note`, `listing_preparation_summary`)
- [x] Approved inputs documented with Phase P, R, and U service outputs enumerated and prohibited input sources listed (Section 2)
- [x] Explainability requirements documented (Section 7 — agent-readable attribution, unexplainable claims removed before review, model/prompt version as required metadata, no black-box storage, Phase R snapshot as anchor)
- [x] Human review requirements documented (Section 8 — agent-reviewed, editable via revision tracking, approval-gated, no auto-publish, no approval by inaction)
- [x] Audit requirements documented (Section 9 — generation audit with model/prompt/report version, timestamp, and Phase R/U snapshots; agent review audit; gate failure audit; attribution failure audit; retention and integrity rules)
- [x] Future implementation requirements documented (Section 10 — Phase X and all subsequent phases must respect Phase V governance, readiness gate, Fair Housing safeguards, report contract, explainability contract, hallucination prevention, human review gates, and auditability requirements)

---

**Document confirmed complete. No code was written. No AI was implemented. This document is a contract and planning specification only.**
