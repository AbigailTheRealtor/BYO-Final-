# Property DNA Phase XC — AI Prompt Contract & Template Specification

**Document Date:** 2026-05-29
**Phase:** XC — AI Prompt Contract & Template Specification
**Preceding Phases:** P — Deterministic Marketing Context Builder, Q — Marketing Brief Readiness Plan, R — Deterministic Property Marketing Brief Builder, S — Internal Brief Inspector / Admin Preview, T — Agent-Reviewed Brief UI, U — AI Drafting Guardrails Plan, V — AI Marketing Intelligence Governance & Readiness Plan, W — AI Marketing Intelligence Report Contract, X — AI Marketing Intelligence Implementation Architecture, XA — OpenAI Integration Layer Specification, XB — OpenAI Client Wrapper & Configuration Layer
**Type:** Specification document only — no code, no routes, no schema changes, no AI calls, no UI

---

## 1. Purpose

Phases P through XB have established a complete, deterministic, auditable pipeline for producing structured marketing context (Phase P), a named-section marketing brief (Phase R), a readiness assessment (Phase U), a comprehensive governance plan (Phase V), an exact report output contract (Phase W), a full implementation architecture (Phase X), an OpenAI integration layer specification (Phase XA), and a working client wrapper with configuration layer (Phase XB).

Before any report generation service is built that assembles and dispatches a prompt to the OpenAI API, this Phase XC document defines the exact prompt contract and template rules that must govern every future AI Marketing Intelligence report generation call. This document is the authoritative specification that every prompt template written for this feature must satisfy. No prompt template may be authored, deployed, or versioned without satisfying every rule defined here.

Specifically, this document defines:

- Which deterministic service outputs the prompt consumes as its input context, and which inputs the prompt is categorically prohibited from receiving
- The exact Phase W JSON report shape the prompt must instruct the AI to produce, including all seven top-level keys, all five report sections, and the empty-string rule for empty source data
- The required system instructions that every prompt template must include, stated as unconditional rules
- The required prohibited-output instructions that every prompt template must include, covering each category of prohibited content established in Phase V
- The source attribution instructions that govern how the AI identifies and records the factual basis for each generated section
- The hallucination prevention instructions that govern what the AI may and may not assert, infer, or estimate
- The prompt versioning rules, including the version identifier format, the conditions that require a MAJOR version bump, and the conditions that permit a MINOR version bump
- The future implementation requirements that every downstream phase building on this prompt contract must satisfy, including the human-review gate and the prohibition on auto-publication

This is a specification document only. No PHP files are modified, no routes are added, no UI is created, no AI or OpenAI integration is called, and no database schema changes are made in Phase XC. Prompt template authoring and report generation service implementation begin in separately approved downstream phases and must not proceed without satisfying every constraint documented here and in Phases V, W, X, and XA.

---

## 2. Prompt Scope

### 2.1 What This Prompt Does

The AI Marketing Intelligence prompt is a structured generation prompt. Its sole purpose is to receive approved deterministic service outputs from the Phase P, R, and U pipeline as structured input context and to return a JSON report object conforming exactly to the Phase W Section 3.1 report contract. The prompt does nothing else.

The prompt instructs the AI to:

- Read the provided Phase R and Phase U structured input data
- Produce a factual, neutral, data-derived draft text for each of the five named report sections, drawing only from the provided input
- Populate the `source_attribution` array for each section with the specific Phase R or Phase U records that served as the factual basis for that section's `draft_text`
- Return a complete, well-formed JSON object conforming to the Phase W Section 3.1 contract, with all seven top-level keys and all five section keys present
- Produce an empty string for `draft_text` when the source data for a section is empty
- Set `status` to `pending_review` for all sections except `missing_information_note`, which must use `internal_note`

### 2.2 What This Prompt Does Not Do

The prompt does not and must not:

- Produce ready-to-publish listing copy, advertisement text, or marketing collateral
- Make recommendations about advertising channels, platforms, budgets, or campaign strategies
- Characterize prospective buyers, tenants, or any audience segment
- Describe the surrounding neighborhood, community, or local area
- Draw on the AI model's training data knowledge about the property's location, market, or region
- Auto-approve, auto-publish, or finalize any content for external use
- Perform valuation, comparative market analysis, or financial analysis of any kind
- Perform legal analysis, disclosure guidance, or legal interpretation of any kind

### 2.3 Prompt Boundaries

The prompt operates exclusively within the following boundaries:

- **Input boundary:** Only approved Phase P, R, and U service outputs. No other data enters the prompt context.
- **Output boundary:** Only the Phase W Section 3.1 JSON report object. No free-form narrative, conversational response, or partial object is acceptable.
- **Factual boundary:** Only facts present in the provided input. No inference, estimation, or knowledge beyond the provided structured data.
- **Review boundary:** All generated output is marked `pending_review` or `internal_note` at generation time. No output transitions to `approved` status without an explicit, recorded human action.

---

## 3. Approved Inputs

### 3.1 Exclusive Input Sources

The prompt may only receive structured data produced by the following three Phase services. No other data source, database field, HTTP request parameter, session value, user-provided string, or external API response may appear in the prompt context under any circumstance.

| Approved Input Source | Phase Service | Constraint |
|---|---|---|
| `PropertyMarketingBriefService::build()` output | Phase R | All nine named sections are approved input context |
| `PropertyMarketingReadinessService::build()` output | Phase U | Approved fields only — see Section 3.3 |
| `PropertyMarketingContextService::build()` output | Phase P | For source attribution resolution only — see Section 3.4 |

### 3.2 Approved Phase R Input Sections

All nine named sections of the Phase R brief output are approved for inclusion in the prompt context. Each section's permitted use is constrained as follows:

| Phase R Section Key | Permitted Use in Prompt |
|---|---|
| `property_attribute_context` | Primary content input for `property_feature_narrative` section |
| `transaction_context` | Primary content input for `transaction_terms_summary` section |
| `quantitative_context` | Primary content input for `property_feature_narrative` section |
| `marketing_asset_checklist` | Primary content input for `marketing_asset_statement` section |
| `missing_information_checklist` | Primary content input for `missing_information_note` section |
| `listing_preparation_notes` | Primary content input for `listing_preparation_summary` section |
| `neutral_feature_summary` | Attribution verification reference — the AI uses this to cross-check its own output |
| `seller_landlord_questions` | Context only — not presented to the AI as questions it should answer autonomously |
| `summary` | Metadata context only — integer counts; the AI must not derive content claims from counts alone |

### 3.3 Approved Phase U Input Fields

The following Phase U output fields are approved for inclusion in the prompt context. Their permitted use is constrained to informing the AI about data availability, not as a basis for generating property descriptions.

| Phase U Field | Permitted Use in Prompt |
|---|---|
| `is_marketing_ready` | Gate confirmation context only — confirms that all three required groups are present |
| `present_groups` | Context — informs the AI which information groups have data available |
| `missing_groups` | Context — informs the AI which information groups have no data, so that it produces empty output for the corresponding sections |
| `review_items` | Supplemental context for attribution resolution |
| `summary.present_group_count` | Metadata only |
| `summary.missing_group_count` | Metadata only |

### 3.4 Approved Phase P Input Sections

The Phase P marketing context output is approved as a supplemental source for source attribution resolution only. The AI uses it to cross-reference specific tag-level records when populating `source_attribution` entries.

| Phase P Section Key | Permitted Use in Prompt |
|---|---|
| `attribute_context` (all named buckets) | Source attribution resolution only |
| `transaction_context` (all named buckets) | Source attribution resolution only |
| `quantitative_context` | Source attribution resolution only |
| `summary` | Metadata only |

### 3.5 Prohibited Input Sources

The following data sources are categorically prohibited from appearing in any prompt context under Phase XC and all subsequent phases. This prohibition applies regardless of technical availability, feature flags, configuration settings, or code paths.

| Prohibited Input | Prohibition Basis |
|---|---|
| Neighborhood demographic data (census, ACS, or similar) | Fair Housing Act §804(c); HUD steering guidelines |
| School demographic or rating data | Fair Housing Act; HUD established case law on school-based steering |
| Buyer or tenant identity, credit history, or financial records | FCRA; ECOA; CFPB guidance |
| Protected-class signals of any kind (race, color, national origin, religion, sex, familial status, disability) | Fair Housing Act; 42 U.S.C. §3604 |
| Income tier, wealth tier, or socioeconomic classification | Fair Housing Act disparate impact doctrine; ECOA |
| Prior transaction history or buyer/tenant behavioral profiles | Fair Housing; CFPB guidance on algorithmic decision-making |
| External real estate market data not present in the Phase R brief | Out of scope; introduces unaudited inference |
| Any user PII beyond `listing_id` and `profile_id` | Data minimization requirement |
| Any data not produced by an approved Phase P, R, or U service output | Scope boundary — the AI may only see what the deterministic pipeline has already produced |

### 3.6 Input Availability Guarantee

All approved Phase R and Phase U output keys are always present, even when the underlying profile data is empty. The AI must handle empty arrays and zero-count summaries by producing an empty `draft_text` string for the corresponding section. The AI must never infer missing data, fabricate plausible values for empty fields, or fill gaps using external knowledge about the property's location, neighborhood, or market.

---

## 4. Required Output Contract

### 4.1 JSON Report Object — Exact Shape

The prompt must instruct the AI to produce a response that is a valid JSON object conforming exactly to the following shape. Every field listed is always required. No key may be null, absent, or conditionally omitted based on input completeness or AI model behavior. If the AI cannot produce a conforming response, it must indicate failure rather than return a partial or non-conforming object.

```json
{
  "report_id": "<string — unique identifier for this report instance>",
  "generated_at": "<string — UTC ISO 8601 timestamp of the AI call>",
  "listing_context": {
    "listing_id": "<string/integer — identifier of the property listing>",
    "profile_id": "<string/integer — identifier of the PropertyDnaProfile used as input>"
  },
  "readiness_snapshot": {
    "is_marketing_ready": "<boolean — value from Phase U output at generation time>",
    "present_groups": ["<string>"],
    "missing_groups": ["<string>"]
  },
  "sections": {
    "property_feature_narrative": {
      "draft_text": "<string — AI-generated draft paragraph; empty string if source data is empty>",
      "status": "pending_review",
      "source_attribution": [
        {
          "source_section": "<string — Phase R output key>",
          "source_records": ["<string — specific tag, trait, or value string from Phase R>"]
        }
      ]
    },
    "transaction_terms_summary": {
      "draft_text": "<string — AI-generated draft summary; empty string if source data is empty>",
      "status": "pending_review",
      "source_attribution": [
        {
          "source_section": "<string — Phase R output key>",
          "source_records": ["<string — specific tag or note string from Phase R>"]
        }
      ]
    },
    "marketing_asset_statement": {
      "draft_text": "<string — AI-generated asset statement; empty string if no assets present>",
      "status": "pending_review",
      "source_attribution": [
        {
          "source_section": "marketing_asset_checklist",
          "source_records": ["<string — checklist entry tag strings from Phase R>"]
        }
      ]
    },
    "missing_information_note": {
      "draft_text": "<string — AI-generated internal note for agent; empty string if no missing groups>",
      "status": "internal_note",
      "source_attribution": [
        {
          "source_section": "<string — 'missing_information_checklist' or 'missing_groups'>",
          "source_records": ["<string — checklist entry strings or group name strings from Phase R/U>"]
        }
      ]
    },
    "listing_preparation_summary": {
      "draft_text": "<string — AI-generated preparation summary for agent; empty string if no preparation notes>",
      "status": "pending_review",
      "source_attribution": [
        {
          "source_section": "listing_preparation_notes",
          "source_records": ["<string — preparation note strings from Phase R>"]
        }
      ]
    }
  },
  "generation_metadata": {
    "ai_model": "<string — model identifier and version, e.g. 'gpt-5-2025-11-01'>",
    "prompt_template_version": "<string — version identifier of the prompt template used>",
    "phase_r_brief_version": "<string — hash or version token of the Phase R brief snapshot provided>",
    "phase_u_readiness_version": "<string — hash or version token of the Phase U readiness snapshot provided>"
  },
  "attribution_verified": "<boolean — true only when all sections with non-empty draft_text have at least one source_attribution entry>"
}
```

### 4.2 Seven Required Top-Level Keys

The prompt must instruct the AI that all seven top-level keys are required and must be present in every response. The prompt must explicitly name them:

1. `report_id`
2. `generated_at`
3. `listing_context`
4. `readiness_snapshot`
5. `sections`
6. `generation_metadata`
7. `attribution_verified`

A response missing any of these keys is a generation failure. The implementation layer must treat a non-conforming response as failed and must not attempt to populate missing keys with default values.

### 4.3 Five Required Report Sections

The prompt must instruct the AI that `sections` must always contain exactly five named section keys, all present regardless of input completeness:

1. `property_feature_narrative`
2. `transaction_terms_summary`
3. `marketing_asset_statement`
4. `missing_information_note`
5. `listing_preparation_summary`

A response with a missing section key is a generation failure. A response that omits a section because its source data was empty is a generation failure — the section must be present with an empty `draft_text` string.

### 4.4 Required Section Fields

Within each of the five sections, the prompt must instruct the AI to include the following three fields in every section, without exception:

| Field | Type | Rule |
|---|---|---|
| `draft_text` | string | Required in every section. Must be a string. Must be an empty string (`""`) when source data for the section is empty. Must not be `null`, `undefined`, or absent. |
| `status` | string | Required in every section. Must be `"pending_review"` for `property_feature_narrative`, `transaction_terms_summary`, `marketing_asset_statement`, and `listing_preparation_summary`. Must be `"internal_note"` for `missing_information_note`. No other status value is permitted at generation time. |
| `source_attribution` | array | Required in every section. Must be an array. Must be an empty array (`[]`) when `draft_text` is empty. Must contain at least one entry for every section with non-empty `draft_text`. |

### 4.5 Empty-String Rule for Empty Source Data

When the Phase R source data for a section is empty (an empty array, an empty checklist, or zero preparation notes), the AI must produce an empty string for `draft_text`. This rule applies to each section independently:

| Section | Source | Empty-String Condition |
|---|---|---|
| `property_feature_narrative` | `property_attribute_context`, `quantitative_context` | Both sources are empty |
| `transaction_terms_summary` | `transaction_context` | Source is empty |
| `marketing_asset_statement` | `marketing_asset_checklist` | Checklist is empty (no assets recorded) |
| `missing_information_note` | `missing_information_checklist`, `missing_groups` | Both sources are empty (no missing groups) |
| `listing_preparation_summary` | `listing_preparation_notes` | Source is empty |

The empty-string rule must be stated explicitly in the prompt. The AI must not substitute a placeholder message, a note saying "no data available," or any other non-empty string when the source data is empty.

### 4.6 `attribution_verified` Flag Rule

The prompt must instruct the AI to set `attribution_verified` at the root level according to the following rule:

- Set to `true` when every section with a non-empty `draft_text` carries at least one `source_attribution` entry
- Set to `false` when any section with a non-empty `draft_text` has no `source_attribution` entries
- Set to `true` when all sections have empty `draft_text` (an empty report is considered attributed)

This flag is a self-report by the AI about the completeness of its own attribution work. The implementation layer performs its own independent attribution verification and may override this value, but the prompt must instruct the AI to set it correctly.

---

## 5. Required System Instructions

The prompt template must include a system message (or equivalent instruction block at the beginning of the prompt) containing all of the following instructions. Each instruction is unconditional — no feature flag, configuration value, or input condition may disable or relax any of them.

### 5.1 Role and Purpose Instruction

The system instruction must state that the AI's role is to serve as a structured data formatter for a regulated real estate platform. Its only purpose in this call is to convert the provided deterministic service output into a structured JSON report object conforming to the specified contract. It is not an autonomous content creator, not a marketing copywriter, not a legal advisor, not a real estate broker, and not a valuation service.

### 5.2 Input Exclusivity Instruction

The system instruction must state that the AI may only assert facts that are explicitly present in the structured Phase R and Phase U input data provided in this call. The AI must not use any knowledge from its training data about the property's location, neighborhood, city, county, region, market, or any geographic context not present in the provided input. The Phase R and Phase U data constitute the complete and exclusive factual universe for this generation call.

### 5.3 Output Format Instruction

The system instruction must state that the AI's response must be a single, complete, valid JSON object conforming exactly to the report contract specified in the prompt. The response must contain no text before the opening `{` brace and no text after the closing `}` brace. No markdown code fences, no explanation, no commentary, and no apology text may be included. A response that is not a single valid JSON object is a failure.

### 5.4 Completeness Instruction

The system instruction must state that all seven top-level keys and all five section keys are required in every response, regardless of input completeness. No key may be absent, null, or conditionally omitted. When source data for a section is empty, the section must still appear in the output with `draft_text` set to an empty string and `source_attribution` set to an empty array.

### 5.5 Status Value Instruction

The system instruction must state that:

- `status` must be `"pending_review"` for the following sections: `property_feature_narrative`, `transaction_terms_summary`, `marketing_asset_statement`, and `listing_preparation_summary`
- `status` must be `"internal_note"` for `missing_information_note`
- No other `status` value is permitted at generation time

### 5.6 Source Attribution Completeness Instruction

The system instruction must state that every non-empty `draft_text` must be accompanied by at least one `source_attribution` entry. Each `source_attribution` entry must identify the specific Phase R or Phase U output key that provided the factual basis (`source_section`) and the specific tag, trait, value, or entry strings from that source that are reflected in the `draft_text` (`source_records`). The AI must not produce a non-empty `draft_text` without the corresponding attribution. If the AI cannot attribute a statement, it must not include that statement.

### 5.7 Human-Review Gate Acknowledgment Instruction

The system instruction must state that all output produced by this call is for internal human review only. All sections carry `pending_review` or `internal_note` status. None of the generated content may be published, distributed, or used externally without affirmative human review and approval by a licensed agent and, where applicable, the seller or landlord. The AI must not produce content in a form intended for direct publication, and it must not produce content that presupposes publication approval.

---

## 6. Required Prohibited-Output Instructions

The prompt template must include an explicit, unconditional prohibition against each of the following output categories. These prohibitions must be stated as flat prohibitions — not as suggestions, guidelines, or best practices. The absence of a user-level request for prohibited content is not sufficient protection; the prompt must actively prohibit each category.

### 6.1 Prohibition: Demographic Targeting Copy

The AI must not produce any text that characterizes a property as suited to, attractive to, designed for, or marketed toward any demographic group. This prohibition includes explicit demographic references and coded language that functions as a demographic proxy.

**Prohibition basis:** Fair Housing Act §804(c); HUD Advertising Guidelines (24 C.F.R. Part 109).

### 6.2 Prohibition: Protected-Class Targeting

The AI must not produce any text that selects, segments, ranks, implies a preference for, or implies a limitation on buyers or tenants based on race, color, national origin, religion, sex, familial status, disability, or any other class protected under federal, state, or local fair housing law.

**Prohibition basis:** Fair Housing Act §3604; HUD regulations; state and local fair housing equivalents.

### 6.3 Prohibition: "Ideal Buyer" or "Perfect Tenant" Characterizations

The AI must not produce any text that describes a specific type of buyer or tenant as the "ideal," "best fit," "most suitable," "most qualified," or "perfect" match for the property.

**Prohibition basis:** Such characterizations imply suitability screening and may constitute discriminatory steering under the Fair Housing Act.

### 6.4 Prohibition: Buyer or Tenant Suitability Scoring or Ranking

The AI must not produce any output that ranks, scores, orders, or evaluates prospective buyers or tenants relative to one another or relative to the property.

**Prohibition basis:** Fair Housing Act; CFPB guidance on algorithmic decision-making in housing.

### 6.5 Prohibition: Neighborhood or Community Demographic Characterization

The AI must not produce any text that describes, implies, or characterizes the demographic composition, character, or desirability of the surrounding neighborhood, community, or school district.

**Prohibition basis:** Fair Housing Act §804(c); HUD steering guidelines; established case law.

### 6.6 Prohibition: School Demographic Analysis or Rating Commentary

The AI must not produce any text that references, scores, or characterizes school demographics, school ratings, school racial or economic composition, or proximity to schools as a demographic signal.

**Prohibition basis:** Fair Housing Act; HUD steering guidelines; school-based steering case law.

### 6.7 Prohibition: Income or Wealth Tier Targeting

The AI must not produce any text that targets buyers or tenants by income bracket, wealth tier, or socioeconomic classification in a manner that functions as a proxy for a protected class.

**Prohibition basis:** Fair Housing Act disparate impact doctrine; ECOA; HUD guidance.

### 6.8 Prohibition: Audience Upload Recommendations or Segment Files

The AI must not produce any output that produces, formats, or recommends a custom audience list, lookalike audience seed, or demographic segment file for upload to any advertising platform.

**Prohibition basis:** HUD's 2019 settlement with Facebook; ongoing enforcement on algorithmic housing ad targeting.

### 6.9 Prohibition: Autonomous Ad Copy or Campaign Recommendations

The AI must not produce any output that constitutes ready-to-publish advertisement text, recommends advertising channels, suggests ad budgets, or recommends campaign targeting parameters.

**Prohibition basis:** Autonomous ad copy removes required human accountability and creates liability for the platform and agent.

### 6.10 Prohibition: Fabricated or Inferred Property Data

The AI must not produce any output that adds, assumes, or infers property facts not present in the Phase R or Phase U input provided in this call. The AI must not fill in missing data with plausible estimates, industry averages, or external knowledge.

**Prohibition basis:** Fabricated property data creates material misrepresentation liability under real estate disclosure law.

### 6.11 Prohibition: Predictions of Sale Price, Days on Market, or Transaction Outcome

The AI must not produce any output that predicts, estimates, or implies the likely sale price, rental rate, time on market, or probability of a successful transaction.

**Prohibition basis:** Out of scope for a marketing brief; creates unauthorized financial advice liability.

### 6.12 Prohibition: Superlatives and Persuasive Framing

The AI must not produce superlatives, marketing hyperbole, or persuasive framing in any section. Prohibited language includes but is not limited to: "stunning," "rare opportunity," "ideal for," "perfect for," "must-see," "exceptional," "one-of-a-kind," "turn-key," or any similar characterization that goes beyond a factual description of the property's recorded attributes.

**Prohibition basis:** Persuasive framing presupposes the agent's editorial judgment and cannot be reviewed as a factual claim.

---

## 7. Source Attribution Instructions

### 7.1 Attribution Requirement

The prompt must instruct the AI that every non-empty `draft_text` in every section must be fully attributable to specific records in the provided Phase R or Phase U input. The `source_attribution` array for each section is not optional metadata — it is a required factual record that the implementation layer uses to verify the report's integrity before persisting it.

### 7.2 Required Attribution Fields

Each entry in the `source_attribution` array must contain exactly two fields:

| Field | Type | Rule |
|---|---|---|
| `source_section` | string | Must be one of the approved values listed in Section 7.3. No other value is permitted. |
| `source_records` | array of strings | Must contain at least one specific tag, trait, value, checklist entry, or group name string from the identified Phase R or Phase U source. Must not be an empty array when the parent `draft_text` is non-empty. |

### 7.3 Approved `source_section` Values

The prompt must instruct the AI that `source_section` is constrained to the following values. Each value is permitted only in the report sections listed.

| Approved `source_section` Value | Phase | May Appear In |
|---|---|---|
| `property_attribute_context` | R | `property_feature_narrative` |
| `quantitative_context` | R | `property_feature_narrative` |
| `transaction_context` | R | `transaction_terms_summary` |
| `marketing_asset_checklist` | R | `marketing_asset_statement` |
| `missing_information_checklist` | R | `missing_information_note` |
| `listing_preparation_notes` | R | `listing_preparation_summary` |
| `missing_groups` | U | `missing_information_note` |

No other `source_section` value is permitted. The AI must not invent a source section name or use a free-form label.

### 7.4 Attribution for Every Non-Empty Section

The prompt must instruct the AI that for every section where `draft_text` is non-empty:

- The `source_attribution` array must contain at least one entry
- Each entry's `source_records` array must contain the specific tag, trait, value, or entry string from Phase R or Phase U that corresponds to the claim in `draft_text`
- The `source_records` values must be verbatim strings from the provided input — not paraphrases, summaries, or reformulations

### 7.5 Unattributable Claims Must Be Excluded

The prompt must instruct the AI that if it cannot trace a factual claim to a specific Phase R or Phase U source record, it must exclude that claim from `draft_text` entirely. The AI must not include a claim in `draft_text` and leave the corresponding `source_attribution` entry unpopulated or partially populated. An unattributable claim must not appear in the output.

### 7.6 Attribution for Empty Sections

The prompt must instruct the AI that when `draft_text` is an empty string, `source_attribution` must be an empty array (`[]`). The attribution array must not contain entries for sections that have no generated content.

### 7.7 `attribution_verified` Self-Report Instruction

The prompt must instruct the AI to set `attribution_verified` at the root level of the report object to `true` when every section with a non-empty `draft_text` has at least one `source_attribution` entry, and to `false` otherwise. The AI must evaluate this flag before finalizing its JSON response — it is a required part of the output contract, not an optional field.

---

## 8. Hallucination Prevention Instructions

### 8.1 Facts From Input Only

The prompt must instruct the AI, in explicit and unambiguous terms, that it may only assert facts that are present in the structured Phase R and Phase U input provided in this call. The following are unconditionally prohibited, and the prompt must state each prohibition explicitly:

- Adding property attributes that are plausible but not recorded in the input (e.g., inferring a garage because the property type is "single family home")
- Asserting a bedroom count, bathroom count, square footage, lot size, year built, or price that differs from or supplements the values in `quantitative_context`
- Inferring a full value from a partial record (e.g., deriving a full bathroom count from a half-bath entry)
- Describing amenities, features, parking arrangements, or policies that do not appear in `property_attribute_context`

### 8.2 No Inferences From Provided Data

The prompt must instruct the AI that it must not draw inferences from the data it is provided. Prohibited inferences include:

- Inferring the neighborhood's character, demographic composition, desirability, or market conditions from the property's location, price, type, or any other attribute
- Inferring buyer or tenant preferences, qualification, or suitability from the transaction terms
- Inferring that a missing data point has a typical, expected, or industry-average value
- Inferring what type of person would be suited to or interested in the property based on any combination of its recorded attributes

An absence of data for a profile dimension means there is nothing to say about that dimension. The AI must produce an empty `draft_text` for that section and must not fill that absence with an assumption.

### 8.3 No Estimates or Approximations

The prompt must instruct the AI that it must not produce estimates, approximations, or probabilistic assertions for any property attribute. If `quantitative_context` contains no square footage record, the AI must not write "approximately X square feet," "likely in the range of X–Y square feet," or any similar construction. An empty input for a quantitative attribute produces no quantitative claim in the output.

### 8.4 No Training Data Knowledge About the Property

The prompt must instruct the AI that it must not draw on its training data knowledge about the property's city, county, state, region, neighborhood, school district, market conditions, comparable properties, or any geographic or market context not present in the provided Phase R or Phase U input. The Phase R and Phase U data constitute the complete factual universe for this call.

### 8.5 Empty Input Produces Empty Output

The prompt must instruct the AI that when the source data for a section is empty, `draft_text` must be an empty string. The AI must not:

- Substitute a placeholder such as "No data available for this section"
- Substitute a generic description based on the property type
- Ask a question in place of a factual statement
- Produce a conditional statement such as "If the agent provides X, this section can be completed"

The section must be present in the output with `draft_text: ""` and `source_attribution: []`.

### 8.6 Prompt-Level and Attribution-Layer Redundancy

The prompt must explicitly acknowledge that hallucination prevention is enforced at two independent layers — first at the prompt level through these explicit prohibitions, and second at the attribution verification layer in the implementation service. Neither layer is a substitute for the other. Both must be satisfied for a report to be accepted and surfaced.

---

## 9. Prompt Versioning Rules

### 9.1 Version Identifier Format

Every prompt template used in an AI Marketing Intelligence generation call must be identified by a version string conforming to the following format:

```
property-dna-report-v{MAJOR}.{MINOR}
```

Examples: `property-dna-report-v1.0`, `property-dna-report-v1.1`, `property-dna-report-v2.0`

The identifier must be:

- A plain ASCII string — no symbols, spaces, or special characters beyond the hyphen separators and the dot separator between MAJOR and MINOR
- Readable and sortable without reference to a separate registry or lookup table
- Unique across all prompt template versions ever deployed for this feature
- Embedded directly in every generation audit record's `prompt_version` field and in the report object's `generation_metadata.prompt_template_version` field

### 9.2 MAJOR Version Bump Conditions

The MAJOR version number must be incremented whenever any of the following changes is made to the prompt template:

- Any change to the output JSON contract (adding, removing, renaming, or retyping any key at any level)
- Any change to the list of prohibited output categories (adding or removing a prohibition)
- Any change to the source attribution contract (adding, removing, or renaming approved `source_section` values)
- Any change to the status values permitted at generation time (`pending_review`, `internal_note`)
- Any change to the empty-string rule for empty source data
- Any change that could alter how the implementation layer validates, parses, or stores the report object
- Any change made in response to a newly identified compliance, legal, or Fair Housing requirement
- Any change to the approved AI provider or model family that requires corresponding prompt adjustments

A MAJOR version bump creates a clean audit boundary: all generation audit records made before the bump carry the prior version; all records made after the bump carry the new version. Previously generated reports are never retroactively reassigned to a new MAJOR version.

### 9.3 MINOR Version Bump Conditions

The MINOR version number may be incremented for changes that refine the prompt's instructions without altering the output contract or safety prohibitions. MINOR version bumps are appropriate for:

- Wording improvements that make existing instructions clearer without changing their meaning
- Adding examples or clarifying language within an existing instruction
- Reordering instructions for improved readability
- Fixing grammatical errors or ambiguities in existing text that do not change the instruction's intent

A MINOR version bump does not change the output contract and does not require a separate validation review of the report object schema. However, a MINOR version bump does require:

- An update to the `prompt_template_version` in `config/ai.php` (via the `OPENAI_PROMPT_VERSION` environment variable)
- All generation calls made after deployment carry the new MINOR version in their audit records
- Previously generated reports retain the MINOR version that was in effect at their generation time

### 9.4 Version Tracking Requirements

The prompt version identifier must be:

- Stored as a required field in every generation audit record
- Included in the `generation_metadata.prompt_template_version` field of every report object
- Included in the application log entry created for every generation event
- Immutable once a generation audit record has been created — no retroactive prompt version reassignment is permitted
- Readable from the `config/ai.php` configuration key `prompt_version`, which reads from the `OPENAI_PROMPT_VERSION` environment variable

### 9.5 Version-Controlled Storage

Every prompt template must be stored as a version-controlled artifact in the platform's source repository. Prompts assembled dynamically at runtime from unversioned fragments are prohibited. The complete, assembled prompt that will be sent to the OpenAI API must be reconstructable from the stored prompt template and the structured Phase R and Phase U inputs — no additional runtime assembly logic may alter the prompt's structure, safety instructions, or prohibited-output rules.

### 9.6 Starting Version

The first prompt template authored for this feature must use the version identifier `property-dna-report-v1.0`. This version must satisfy all requirements in Sections 5 through 8 of this specification.

---

## 10. Future Implementation Requirements

### 10.1 Human Review Is Mandatory Before Any External Use

Every section of every report generated by this prompt is for internal human review only. The following human review gates must be implemented by the downstream report generation service and agent review UI phases before any generated content is used, displayed publicly, or transmitted outside the internal system:

**Gate 1 — Agent Review:** No AI-generated draft listing copy, transaction summary, or marketing content may be submitted to any listing platform, MLS, syndication service, or external partner without explicit review and approval by a licensed real estate agent or broker. The approval must be an affirmative action recorded in the system. This gate is implemented in Phase XD.

**Gate 2 — Seller/Landlord Review:** No AI-generated property description, listing narrative, or marketing brief section may be published or distributed publicly without review and approval by the seller or landlord whose property it describes. This gate is implemented in Phase XE.

**Gate 3 — Fair Housing Compliance Review:** Any AI-generated content that will appear in advertising, printed materials, or any form of public distribution must be reviewed specifically for Fair Housing compliance by a qualified reviewer before publication. This is a separate gate from the agent approval gate.

### 10.2 Prohibition on Auto-Publish

The system must never include any code path, scheduled job, timeout-based fallback, or implicit state transition that moves an AI-generated section from `pending_review` status to `approved` status without an explicit, recorded human action. This prohibition is absolute and applies to every section at every stage of the review workflow.

The following are explicitly prohibited as approval mechanisms:

- Auto-approval after a time window expires without rejection
- Approval by absence of a rejection action within a defined period
- Batch approval without per-section agent review
- Automated compliance filter output as a substitute for human review
- Any programmatic transition from `pending_review` to `approved`

### 10.3 Draft Status Visibility Requirement

Every AI-generated section must be displayed with a visible "AI-generated draft — pending review" label at all times until it has received explicit human approval. The label must not disappear, fade, or be removed by any automated process. This requirement applies to agent-facing views, internal admin views, and any preview displayed to sellers or landlords before their approval gate is reached.

### 10.4 Immutable Audit Record Requirement

For every AI generation call, the implementation service must create a generation audit record before surfacing the report to any user. The audit record must be created and persisted before the report object is surfaced. If the audit record cannot be persisted, the generation must be treated as failed. Once created, audit records must not be modified.

The audit record must include, at minimum: `generation_id`, `listing_id`, `profile_id`, `model_version`, `prompt_version`, `requested_at`, `completed_at`, `attempt_count`, `outcome`, `readiness_result`, `attribution_verified`, `report_id`, a serialized `phase_r_brief_snapshot`, and a serialized `phase_u_readiness_snapshot`.

### 10.5 Prompt Version Propagation Requirement

Every downstream implementation phase that authors or modifies a prompt template must:

1. Assign the new template a version identifier conforming to Section 9.1
2. Update `OPENAI_PROMPT_VERSION` to the new version string before deploying the new template
3. Confirm that the new version identifier is correctly recorded in generation audit records after deployment
4. Retain the prior prompt template in version control for audit reference

### 10.6 Attribution Verification Before Storage

The implementation service must perform independent attribution verification after every AI generation call, before the report object is persisted. The implementation layer's verification is authoritative — if the implementation layer determines that a section with non-empty `draft_text` has no valid `source_attribution` entry, the `attribution_verified` flag in the stored report must be set to `false`, regardless of what value the AI reported. A report with `attribution_verified: false` must not be surfaced to any user.

### 10.7 No Partial Report Storage

If a generation call fails at any stage — including JSON parsing failure, contract validation failure, attribution verification failure, or audit record persistence failure — no partial report object may be stored or surfaced. A failed generation is a complete failure; there is no "best effort" or "partial success" outcome.

### 10.8 Readiness Gate Enforcement

The readiness gate (`PropertyMarketingReadinessService::build($profile)['is_marketing_ready'] === true`) must be evaluated fresh by the implementation service before any prompt is constructed or dispatched. The gate must be enforced server-side, within the generation service, and must not be evaluated only in UI or JavaScript layers. A gate failure aborts the generation entirely; no prompt is constructed, no API call is made, and no data is transmitted to any AI model.

---

## 11. Verification Report

This section confirms that all ten checklist items required by Phase XC are satisfied by this document.

---

### Checklist Item 1 — Document Created

**Status: PASS**

`docs/PROPERTY_DNA_PHASE_XC_AI_PROMPT_CONTRACT_SPECIFICATION.md` exists. This is that document.

---

### Checklist Item 2 — All Ten Sections Present

**Status: PASS**

The document contains all ten required sections:

| Section | Heading | Present |
|---|---|---|
| 1 | Purpose | Yes |
| 2 | Prompt Scope | Yes |
| 3 | Approved Inputs | Yes |
| 4 | Required Output Contract | Yes |
| 5 | Required System Instructions | Yes |
| 6 | Required Prohibited-Output Instructions | Yes |
| 7 | Source Attribution Instructions | Yes |
| 8 | Hallucination Prevention Instructions | Yes |
| 9 | Prompt Versioning Rules | Yes |
| 10 | Future Implementation Requirements | Yes |

---

### Checklist Item 3 — All Required Rules Explicitly Stated

**Status: PASS**

| Rule | Location in Document |
|---|---|
| Prompt consumes only Phase P, R, and U outputs | Sections 3.1 – 3.6 |
| Prompt requires exact Phase W JSON shape — all 7 top-level keys and all 5 report sections | Sections 4.1 – 4.3 |
| Prompt requires empty strings when source data is empty | Sections 4.5, 5.4 (system instruction), 8.5 |
| Prohibition: invented facts | Section 6.10, Section 8.1 |
| Prohibition: inferred facts | Sections 8.2, 8.4 |
| Prohibition: estimates | Section 8.3 |
| Prohibition: demographic language | Section 6.1 |
| Prohibition: protected-class language | Section 6.2 |
| Prohibition: ideal buyer/tenant language | Section 6.3 |
| Prohibition: neighborhood assumptions | Section 6.5 |
| Prohibition: school commentary | Section 6.6 |
| Prohibition: audience targeting | Section 6.8 |
| Prohibition: ad platform recommendations | Section 6.9 |
| Prohibition: rankings, scores, and predictions | Sections 6.4, 6.11 |
| Prompt requires `source_attribution` for every non-empty `draft_text` | Sections 7.1 – 7.7 |
| Prompt requires `pending_review` status for generated sections | Sections 4.4, 5.5 |
| Prompt requires `internal_note` for `missing_information_note` | Sections 4.4, 5.5 |
| Prompt version identifier format `property-dna-report-v{MAJOR}.{MINOR}` | Section 9.1 |
| Output is human-review only and must never auto-publish | Sections 10.1 – 10.2 |

---

### Checklist Item 4 — Output Contract Documented

**Status: PASS**

Section 4 documents the required output contract in full, including the exact Phase W JSON shape (Section 4.1), all seven required top-level keys (Section 4.2), all five required report sections (Section 4.3), the required fields within each section (Section 4.4), the empty-string rule (Section 4.5), and the `attribution_verified` flag rule (Section 4.6).

---

### Checklist Item 5 — Prohibited Content Documented

**Status: PASS**

Section 6 documents twelve explicit prohibited-output categories covering: demographic targeting copy (6.1), protected-class targeting (6.2), ideal buyer/tenant characterizations (6.3), suitability scoring and ranking (6.4), neighborhood demographic characterization (6.5), school commentary (6.6), income and wealth tier targeting (6.7), audience upload recommendations (6.8), autonomous ad copy and campaign recommendations (6.9), fabricated property data (6.10), sale price and transaction outcome predictions (6.11), and superlatives and persuasive framing (6.12).

---

### Checklist Item 6 — Source Attribution and Hallucination Rules Documented

**Status: PASS**

Section 7 documents the complete source attribution contract: the attribution requirement (7.1), required attribution fields (7.2), approved `source_section` values and permitted pairings (7.3), attribution for every non-empty section (7.4), the rule requiring exclusion of unattributable claims (7.5), attribution for empty sections (7.6), and the `attribution_verified` self-report instruction (7.7).

Section 8 documents the hallucination prevention instructions: facts from input only (8.1), no inferences from provided data (8.2), no estimates or approximations (8.3), no training data knowledge about the property (8.4), empty input produces empty output (8.5), and the redundancy requirement for both prompt-level and attribution-layer enforcement (8.6).

---

### Checklist Item 7 — Prompt Version Identifier Format Documented

**Status: PASS**

Section 9.1 documents the version identifier format `property-dna-report-v{MAJOR}.{MINOR}` with examples. Section 9.2 documents the conditions that require a MAJOR version bump. Section 9.3 documents the conditions that permit a MINOR version bump. Section 9.4 documents the version tracking requirements. Section 9.5 documents the version-controlled storage requirement. Section 9.6 documents that the starting version must be `property-dna-report-v1.0`.

---

### Checklist Item 8 — Human-Review-Only and Never-Auto-Publish Requirements Documented

**Status: PASS**

Section 10.1 documents the three mandatory human review gates (agent review, seller/landlord review, and Fair Housing compliance review) and states that all output is for internal human review only. Section 10.2 explicitly prohibits auto-publication and lists the specific mechanisms that are prohibited as approval substitutes. Section 10.3 documents the draft status visibility requirement. Section 5.7 includes a required system instruction that the AI must acknowledge the human-review gate.

---

### Checklist Item 9 — No PHP Files Modified

**Status: PASS**

No PHP files were modified, created, or deleted in Phase XC. This is a specification document only.

---

### Checklist Item 10 — No Routes, UI, Schema Changes, or AI Calls Made

**Status: PASS**

No routes were added, no controllers were created or modified, no Blade views were created or modified, no Livewire components were created or modified, no database migrations were created or modified, no schema changes were made, and no AI or OpenAI API calls were made in Phase XC. This is a specification document only.
