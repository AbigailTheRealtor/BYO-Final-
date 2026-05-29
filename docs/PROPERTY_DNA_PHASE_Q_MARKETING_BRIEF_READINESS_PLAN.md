# Property DNA Phase Q — Marketing Brief Readiness Plan

**Document Date:** 2026-05-29
**Phase:** Q — Marketing Brief Readiness Plan
**Preceding Phases:** N — Compatibility Explanation Engine, O — Property DNA Explanation Engine, P — Deterministic Marketing Context Builder
**Type:** Planning document only — no code, no routes, no schema changes, no AI calls, no UI

---

## 1. Purpose

Phases N through P now produce deterministic, structured explanation and marketing context arrays from persisted DNA profile data. Phase P in particular provides two services — `PropertyMarketingContextService` and `BuyerTenantMarketingContextService` — whose output is organized, complete, and ready to be consumed by downstream processes.

Before any marketing brief, ad copy, listing copy, or Ask AI output is generated, this document defines how Phase Q must safely consume Phase P context. It establishes:

- What Phase P inputs are available and what they contain
- Which internal marketing brief sections are permitted
- Which outputs are categorically prohibited
- Fair Housing restrictions that govern all marketing content
- AI boundary rules for any future AI-assisted phases
- Human-review requirements before any content is surfaced
- The recommended MVP structure for the next implementation phase (Phase R)
- The future phase sequence from Phase R through Phase W

This is a planning document only. No code changes, no AI integrations, no routes, no schema changes, and no UI elements are introduced in Phase Q.

---

## 2. Available Phase P Inputs

Phase Q may consume the structured output of both Phase P composing services. These are the only permitted inputs to any Phase Q or subsequent marketing brief process.

### 2.1 `PropertyMarketingContextService::build(PropertyDnaProfile $profile): array`

Produces a structured array with the following keys:

#### `attribute_context` — named bucket arrays of archetype tag explanation records

| Bucket | Tag Prefix | What it covers |
|--------|-----------|----------------|
| `property_type` | `type` | Property type archetype tags |
| `property_style` | `style` | Architectural style archetype tags |
| `property_condition` | `condition` | Property condition archetype tags |
| `amenities` | `amenity` | On-site amenity archetype tags |
| `parking` | `parking` | Parking facility archetype tags |
| `features` | `feature` | Physical feature and specified-terms archetype tags |
| `policies` | `policy` | Occupancy and use policy archetype tags |
| `community` | `community` | Community designation archetype tags |
| `use_classification` | `use` | Use classification archetype tags |
| `governance` | `governance` | Governance and association archetype tags |
| `unrecognized` | *(fallback)* | Tags with unrecognized prefixes |

Each record shape: `['tag' => string, 'explanation' => string]`

#### `transaction_context` — named bucket arrays of archetype tag explanation records

| Bucket | Tag Prefix | What it covers |
|--------|-----------|----------------|
| `timing` | `timing` | Timing and availability archetype tags |
| `transaction_structure` | `structure` | Lease and transaction structure archetype tags |
| `financing` | `financing` | Financing option archetype tags |
| `presentation` | `marketing` | Marketing asset and presentation archetype tags |
| `unrecognized` | *(fallback)* | Tags with unrecognized prefixes |

Each record shape: `['tag' => string, 'explanation' => string]`

#### `quantitative_context` — flat array of marketing hook explanation records

Each record shape: `['trait' => string, 'value' => string, 'explanation' => string]`

Examples: bedroom count, bathroom count, square footage, lot size, price, year built.

#### `summary` — integer counts

| Field | Type | Meaning |
|-------|------|---------|
| `total_archetype_tags` | `int` | Total archetype tag records from Phase O |
| `total_marketing_hooks` | `int` | Total marketing hook records from Phase O |
| `non_empty_attribute_groups` | `int` | Count of non-empty attribute_context buckets |
| `non_empty_transaction_groups` | `int` | Count of non-empty transaction_context buckets |

---

### 2.2 `BuyerTenantMarketingContextService::build(BuyerTenantDnaProfile $profile): array`

Produces a structured array with the following keys:

#### `preference_context` — named bucket arrays of lifestyle tag explanation records

| Bucket | Tag Prefix / Bare Tag | What it covers |
|--------|----------------------|----------------|
| `property_type_preference` | `prefers-type` | Property type preference tags |
| `property_condition_preference` | `prefers-condition` | Property condition preference tags |
| `occupant_signals` | `has-pets` *(bare tag)* | Occupant characteristic bare tags |
| `community_signals` | `seeks` | Community preference tags |
| `required_amenities` | `requires` | Required amenity tags |
| `transaction_openness` | `open-to` | Transaction structure interest tags |
| `financial_signals` | `financial` | Financial status and qualification tags |
| `policy_preferences` | `preference` | Policy and community awareness tags |
| `unrecognized` | *(fallback)* | Tags with unrecognized prefixes |

Each record shape: `['tag' => string, 'explanation' => string]`

#### `requirements_context` — flat array of deal-breaker flag explanation records

Each record shape: `['flag' => string, 'source_field' => string, 'value' => string|null, 'explanation' => string]`

#### `summary` — integer counts and one boolean

| Field | Type | Meaning |
|-------|------|---------|
| `total_lifestyle_tags` | `int` | Total lifestyle tag records from Phase O |
| `total_deal_breaker_flags` | `int` | Total deal-breaker flag records from Phase O |
| `non_empty_preference_groups` | `int` | Count of non-empty preference_context buckets |
| `has_hard_requirements` | `bool` | `true` when requirements_context has at least one record |

---

### 2.3 Input Availability Guarantees from Phase P

All named bucket arrays are always present in Phase P output, even when empty. Phase Q consumers must never assume a key is missing. Phase P guarantees:

- All `explanation` strings originate from Phase O constant maps — they are neutral, deterministic, and non-demographic.
- All tag, trait, value, and flag strings are passed through verbatim from persisted profile data.
- No text generation, ranking, scoring, or AI inference has occurred upstream.
- No protected-class information is surfaced in any explanation string.

---

## 3. Permitted Internal Marketing Brief Sections

A Phase Q (and Phase R) marketing brief is a **structured internal planning document** intended for use by listing agents, sellers, and landlords to prepare a property for market. All sections below are permitted because they derive solely from neutral, factual Phase P context and contain no prohibited content.

### 3.1 Property Attribute Context

A formatted presentation of `attribute_context` bucket records. Surfaces property type, style, condition, amenities, parking, features, policies, community designations, use classifications, and governance facts. Content is limited to the `explanation` strings from Phase O — no additional narrative is generated.

**Permitted use:** Internal agent review, seller/landlord preparation review.
**Prohibited use:** Any public-facing listing copy, ad copy, or audience-targeting input.

### 3.2 Transaction Context

A formatted presentation of `transaction_context` bucket records. Surfaces timing constraints, transaction structure details, financing options, and marketing asset readiness indicators. Content is limited to Phase O explanation strings.

**Permitted use:** Internal agent review, listing preparation checklist.
**Prohibited use:** Any public-facing copy or ad targeting.

### 3.3 Quantitative Context

A formatted presentation of `quantitative_context` records. Surfaces measurable property attributes (bedroom count, bathroom count, square footage, lot size, list price, year built, and other numeric marketing hooks) as neutral factual statements.

**Permitted use:** Internal data summary for the listing agent, factual inputs to a listing checklist.
**Prohibited use:** Suitability inference, buyer/tenant scoring, affordability targeting.

### 3.4 Marketing Asset Checklist

A deterministic checklist derived from the `presentation` bucket within `transaction_context`. Lists which marketing assets (photos, virtual tour, floor plan, staging, etc.) the property's DNA profile indicates are available or recommended for preparation. This section is checklist-only — no copy is generated.

**Permitted use:** Agent and seller preparation guidance.
**Prohibited use:** Ad platform asset upload instructions, audience upload recommendations.

### 3.5 Missing Information Checklist

A deterministic checklist of DNA profile dimensions that are empty (zero records in a bucket). Generated by inspecting `summary.non_empty_attribute_groups`, `summary.non_empty_transaction_groups`, and empty individual buckets. Lists what information should be gathered to complete the property profile.

**Permitted use:** Agent-facing listing preparation tool.
**Prohibited use:** Any automated data-gathering, external API query, or AI inference.

### 3.6 Seller / Landlord Questions

A deterministic list of clarifying questions surfaced based on which DNA profile dimensions are incomplete or contain specific flag combinations. Questions are pre-written, neutral, and factual — not generated at runtime.

**Permitted use:** Agent-to-seller/landlord interview preparation, internal workflow aid.
**Prohibited use:** Any automated outreach, email generation, or AI drafting.

### 3.7 Listing Preparation Notes

Internal notes derived from `transaction_context.timing`, `transaction_context.transaction_structure`, and `transaction_context.financing` records. Surfaces factual constraints an agent should be aware of before listing (e.g., "seller financing indicated," "lease-back arrangement present," "immediate availability indicated").

**Permitted use:** Internal agent preparation notes only.
**Prohibited use:** Any public copy, ad copy, or buyer-facing disclosure.

### 3.8 Neutral Property Feature Summary

A structured, factual, non-demographic feature summary composed from `attribute_context` and `quantitative_context` records. This summary presents property facts only — it does not characterize the neighborhood, the surrounding community, nearby schools, local demographics, or the "type" of buyer or tenant who would suit the property.

**Permitted use:** Internal agent review; may serve as factual input to a separately approved listing copy drafting phase (Phase V or later), subject to human review before use.
**Prohibited use:** Direct ad copy, demographic-based audience targeting, school rating or demographic analysis.

---

## 4. Prohibited Marketing Outputs

The following outputs are categorically prohibited in Phase Q and in all future phases that build upon it, unless a separately approved phase explicitly defines guardrails, human-review gates, and legal review for that specific output type.

### 4.1 Demographic Targeting

Any output that characterizes a property as suited to, preferred by, or marketed toward a demographic group. This includes age group targeting, family status targeting, national origin assumptions, and religious community targeting.

**Prohibition basis:** Fair Housing Act; Equal Credit Opportunity Act; HUD advertising guidelines.

### 4.2 Protected-Class Targeting

Any output that selects, segments, ranks, or implies a preference for buyers or tenants based on race, color, national origin, religion, sex, familial status, disability, or any other class protected under federal, state, or local fair housing law.

**Prohibition basis:** Fair Housing Act §804(c); HUD regulations; state and local equivalents.

### 4.3 "Ideal Buyer" / "Best Buyer" Claims

Any output that characterizes a specific type of buyer or tenant as the "ideal," "best," "most suitable," "most qualified," or "perfect" match for a property, whether based on DNA context or any other signal.

**Prohibition basis:** Such characterizations imply suitability screening and may constitute discriminatory steering.

### 4.4 Suitability, Ranking, and Predictive Language

Any output that ranks buyers, tenants, sellers, or properties relative to one another; predicts likelihood of transaction success; infers buyer or tenant qualification; or evaluates acceptance or rejection probability for any party.

**Prohibition basis:** Fair Housing Act; CFPB guidelines on algorithmic decision-making in housing.

### 4.5 Autonomous Ad Launch

Any automated action that publishes, launches, schedules, or activates an advertisement on any platform (digital, print, or otherwise) without explicit human review and approval at the individual ad level.

**Prohibition basis:** Removes required human accountability; creates liability for platform and agent.

### 4.6 Audience Upload Recommendations

Any output that generates, suggests, composes, or formats a custom audience list, lookalike audience seed file, or demographic segment file intended for upload to any advertising platform (Meta, Google, Zillow, Realtor.com, or others).

**Prohibition basis:** HUD's 2019 settlement with Facebook; ongoing enforcement on algorithmic ad targeting in housing.

### 4.7 School Demographic Analysis

Any output that analyzes, scores, characterizes, or references school demographics, school racial composition, school economic composition, or related neighborhood indicators for marketing purposes.

**Prohibition basis:** Fair Housing Act; HUD steering guidelines; established case law on school-based steering.

### 4.8 Neighborhood Demographic Assumptions

Any output that makes assumptions about, infers, or characterizes the demographic composition of the neighborhood, community, or surrounding area — regardless of the source of that inference.

**Prohibition basis:** Fair Housing Act §804(c) prohibition on indicating preferences or limitations based on protected class.

### 4.9 Income or Class Targeting

Any output that targets buyers or tenants by income bracket, wealth tier, or socioeconomic class in a manner that functions as a proxy for a protected class.

**Prohibition basis:** Fair Housing Act; ECOA; HUD guidance on facially neutral policies with discriminatory effect.

### 4.10 Creditworthiness Inference

Any output that infers, scores, predicts, or characterizes the creditworthiness, financial qualification, or approval likelihood of any individual buyer or tenant.

**Prohibition basis:** FCRA; ECOA; CFPB guidance; Fair Housing Act disparate impact doctrine.

---

## 5. Fair Housing Restrictions

All marketing brief content produced in Phase Q and all subsequent phases must comply with the following Fair Housing requirements. These restrictions apply to deterministic (non-AI) outputs as well as any future AI-assisted outputs.

### 5.1 No Protected-Class Language

No marketing brief section, field, label, or generated string may contain, imply, or suggest a preference, limitation, or statement of availability based on race, color, national origin, religion, sex, familial status, or disability. This prohibition extends to coded language, dog-whistles, and language that functions as a proxy for a protected class.

### 5.2 No Steering Language

No output may steer a prospective buyer or tenant toward or away from a property, neighborhood, or listing based on any protected characteristic, whether explicit or implied.

### 5.3 Factual-Only Property Descriptions

Property feature descriptions must be factual and property-specific only. They must not characterize the surrounding area's population, community composition, or demographic makeup.

### 5.4 No "Type of Person" Language

No output may describe or imply what "type of person" would be suited to, comfortable in, or likely to enjoy a property. Features and amenities must be described as property attributes — not as signals of who belongs there.

### 5.5 Equal Availability Statements

Any listing copy or marketing content produced in later phases must be consistent with equal opportunity availability. No language may imply that a property is available only to certain types of buyers or tenants.

### 5.6 HUD Advertising Guidelines Compliance

All marketing content produced in any phase must comply with HUD's Fair Housing Advertising Guidelines (24 C.F.R. Part 109), including the prohibition on selective use of human models and the requirement for the Equal Housing Opportunity logo in applicable materials.

### 5.7 State and Local Law

Marketing brief content must also comply with applicable state and local fair housing laws, which may extend protections beyond federal law (e.g., source of income, sexual orientation, gender identity, marital status, ancestry).

---

## 6. AI Boundary Rules

These rules govern how AI systems may and may not interact with Phase P context in any future phase. They apply regardless of which AI system, language model, or AI-assisted service is proposed.

### 6.1 Phase P Context Is Structured Input Only

Phase P output (`PropertyMarketingContextService` and `BuyerTenantMarketingContextService` arrays) is structured, neutral, and deterministic. If AI is introduced in a later approved phase, Phase P context serves as factual input only — it is not a directive, a recommendation, or a targeting signal.

### 6.2 AI Introduced Only in a Later Approved Phase

No AI call, language model inference, embedding lookup, or ML logic may be introduced until a dedicated AI phase (Phase U or later) has been approved with its own governance document. Phase Q, Phase R (Deterministic Brief Builder), Phase S (Admin Preview), and Phase T (Agent-Reviewed Brief UI) must be entirely AI-free.

### 6.3 All AI Output Must Be Human-Reviewed Before Use

No AI-generated content may be surfaced to any seller, landlord, buyer, tenant, agent, or third party without explicit human review and approval at the individual content item level. Automated publishing of AI output is prohibited.

### 6.4 AI May Not Access Prohibited Fields

Any AI system introduced in a future phase must be explicitly prohibited from reading, inferring from, or incorporating: neighborhood demographic data, school demographic data, protected-class signals, creditworthiness signals, income tier signals, or any field listed in Section 4 of this document.

### 6.5 AI May Not Choose Audiences

AI must not select, compose, rank, or recommend target audiences for any advertisement or marketing distribution. Audience selection must remain a human decision at every step.

### 6.6 AI May Not Publish, Launch, or Schedule Ads

AI systems must have no write access to any advertising platform, email distribution system, listing syndication service, or social media platform. All publish/launch actions must be executed by a human after review.

### 6.7 AI May Not Rank Buyers, Tenants, or Properties

AI must not produce rankings, scores, or orderings of buyers, tenants, sellers, landlords, agents, or properties for any marketing or matching purpose. Ranking decisions must remain entirely outside the scope of any marketing-context AI phase.

### 6.8 All Generated Content Is Advisory and Draft-Only

Any AI-generated content must be clearly labeled as a draft requiring human review. No AI output may be treated as final, approved, or publication-ready without an explicit human approval action.

### 6.9 AI Phases Require Separate Governance Documents

Each AI-assisted phase (Phase U, Phase V, Phase W, and any subsequent phases) requires its own governance document specifying: permitted inputs, prohibited fields, output format, human-review gates, Fair Housing compliance checks, and scope boundaries. No AI capability may be added by extending an existing phase without a new approved governance document.

---

## 7. Human-Review Requirements

The following human-review gates are required before any marketing content derived from Phase P context is used, displayed, or transmitted outside the internal system.

### 7.1 Agent Review Before Any Listing Copy Is Used

No listing copy, marketing brief section, or property feature summary may be submitted to any listing platform, MLS, or syndication service without explicit review and approval by a licensed real estate agent or broker.

### 7.2 Seller / Landlord Review Before Publication

No marketing brief, listing copy, or property description may be published or distributed without review and approval by the seller or landlord whose property it describes.

### 7.3 Compliance Review for Ad Copy

Any advertising copy (display ads, social ads, email campaigns, print materials) must be reviewed for Fair Housing compliance by a qualified reviewer before publication. Automated compliance checks are supplemental only — they do not substitute for human review.

### 7.4 Human Approval Gate for AI-Generated Drafts

In any future phase introducing AI-generated content, an explicit human approval action must be recorded in the system before content transitions from "draft" to "approved" status. The system must not have an "auto-approve" path for AI-generated content.

### 7.5 No Implicit Approval by Inaction

A content item is not approved simply because no one has rejected it within a time window. Approval must be an affirmative action.

### 7.6 Audit Trail

The system must record who approved each piece of marketing content, when they approved it, and what version they approved. This audit trail is required for Fair Housing compliance recordkeeping.

---

## 8. Recommended MVP Structure — Phase R

The recommended next implementation step is **Phase R: Deterministic Property Marketing Brief Builder**.

### 8.1 What Phase R Is

Phase R is a deterministic, AI-free PHP service that consumes `PropertyMarketingContextService` output and produces a structured array of named brief sections. It generates no narrative text, calls no AI system, and produces no ad copy.

### 8.2 Phase R Service Contract

**Service:** `PropertyMarketingBriefService`
**Method:** `build(PropertyDnaProfile $profile): array`
**Input:** A `PropertyDnaProfile` model instance
**Dependency:** Calls `PropertyMarketingContextService::build()` internally
**Output:** A structured array of named brief sections

**Output structure:**

```
[
    'property_attribute_context'    => [...],  // formatted attribute_context records
    'transaction_context'           => [...],  // formatted transaction_context records
    'quantitative_context'          => [...],  // formatted quantitative_context records
    'marketing_asset_checklist'     => [...],  // derived from presentation bucket
    'missing_information_checklist' => [...],  // derived from empty buckets
    'seller_landlord_questions'     => [...],  // pre-written questions for incomplete dimensions
    'listing_preparation_notes'     => [...],  // derived from timing/structure/financing buckets
    'neutral_feature_summary'       => [...],  // factual attribute + quantitative summary
    'summary'                       => [...],  // counts and completeness indicators
]
```

### 8.3 Phase R Constraints

- Phase R must be entirely deterministic — identical Phase P input always produces identical output.
- Phase R must produce no narrative text — all content derives from Phase O explanation strings or pre-written neutral question/checklist templates.
- Phase R must call no AI system, language model, or external API.
- Phase R output must not be surfaced in any public page, agent-facing view, or client-facing view without a separately approved visibility phase (Phase S or later).
- Phase R must contain no prohibited outputs as defined in Section 4 of this document.
- Phase R must pass the same grep verification pattern as Phase P (no prohibited language, no AI dependencies).

### 8.4 Why Phase R First

Phase R establishes a fully auditable, deterministic foundation. It proves the brief structure is correct and compliant before any AI layer is introduced. It gives agents and reviewers a concrete artifact to evaluate without AI-generated text, ensuring that the data structure — not AI variance — is the subject of review.

---

## 9. Future Phase Sequence

The following sequence is recommended for all phases following Phase Q. Each phase requires its own approved governance document before implementation begins.

### Phase R — Deterministic Property Marketing Brief Builder

**Type:** New PHP service — no AI, no UI, no routes, no schema changes
**What it does:** Consumes `PropertyMarketingContextService` output; produces a structured array of named brief sections (see Section 8)
**Outputs:** Internal structured array only; not surfaced in any view
**AI:** None
**Human review:** Not yet required (internal data structure only; no user-facing output)

---

### Phase S — Internal Brief Inspector / Admin Preview

**Type:** Admin-only read-only view — no public routes, no agent-facing UI
**What it does:** Renders Phase R brief output in an internal admin page for inspection and validation
**Outputs:** Admin-only HTML view; no PDF, no export, no copy-paste templates
**AI:** None
**Human review:** Admin inspection is the review mechanism; no approval gate required at this phase

---

### Phase T — Agent-Reviewed Brief UI

**Type:** Agent-facing read-only brief display
**What it does:** Surfaces Phase R brief output to the listing agent on the listing detail page (internal section only, not visible to sellers/buyers/tenants)
**Outputs:** Agent-facing HTML section within the listing management UI
**AI:** None
**Human review:** Agent review is the primary gate before any content derived from the brief is used externally

---

### Phase U — AI Drafting Guardrails Plan

**Type:** Planning document only — no code, no AI calls
**What it does:** Defines precise guardrails for introducing AI into any marketing content drafting flow — permitted inputs, prohibited fields, output format, human-review gate design, Fair Housing compliance checks, audit trail requirements, and rollback plan
**Outputs:** Governance document only
**AI:** None (planning phase)
**Human review:** Document must be reviewed and approved before Phase V begins

---

### Phase V — AI Listing Copy Drafting MVP

**Type:** AI-assisted service — internal draft generation only
**What it does:** Calls an approved AI system with Phase R brief sections as structured input; produces a draft listing description marked as "pending human review"
**Outputs:** Draft text stored internally; not published or transmitted without explicit human approval
**AI:** Introduced here for the first time — subject to Phase U guardrails
**Human review:** Mandatory human approval gate before any AI draft is used; audit trail required; no auto-approval path

---

### Phase W — Ask AI Listing Context Pack

**Type:** Agent-interactive AI tool
**What it does:** Allows a listing agent to ask clarifying questions about a property's marketing context, with Phase R brief sections as the structured context pack provided to the AI
**Outputs:** Agent-facing Q&A responses, marked as AI-generated and advisory only
**AI:** Introduced here — subject to Phase U guardrails
**Human review:** All AI responses are advisory; agent must not submit AI Q&A responses as factual representations without independent verification

---

## 10. Compliance Verification Checklist

Before any Phase R (or later) implementation is released, the following checklist must be verified:

### 10.1 Structural

- [ ] Phase R service calls only `PropertyMarketingContextService::build()` as its data source
- [ ] Phase R service produces no explanation strings not originating from Phase O
- [ ] Phase R service calls no AI system, language model, or external API
- [ ] Phase R output is not surfaced in any public route, agent view, or client view
- [ ] All named output sections are always present, even when the input profile is empty

### 10.2 Fair Housing

- [ ] No output section contains protected-class language, implied or explicit
- [ ] No output section characterizes neighborhood demographics
- [ ] No output section characterizes what "type of person" the property suits
- [ ] No output section ranks, scores, or orders any buyer, tenant, or property
- [ ] No output section generates an audience list or targeting file

### 10.3 AI Boundaries

- [ ] No AI call is present in Phase R, Phase S, or Phase T
- [ ] Phase U governance document exists and is approved before Phase V begins
- [ ] Phase V includes an explicit human-approval gate with audit trail
- [ ] No AI output path bypasses human review

### 10.4 Data Integrity

- [ ] All tag, trait, value, and flag strings are passed through verbatim from Phase P
- [ ] No tag string is reformatted, normalized, or label-converted
- [ ] Summary counts are integers only; no floats, percentages, or ratios

---

## 11. Relevant Files

The following files are the Phase P outputs that Phase Q and Phase R will consume. No modifications to these files are permitted.

| File | Role |
|------|------|
| `app/Services/Dna/PropertyMarketingContextService.php` | Phase P — groups `PropertyDnaProfile` explanation records into attribute, transaction, and quantitative context |
| `app/Services/Dna/BuyerTenantMarketingContextService.php` | Phase P — groups `BuyerTenantDnaProfile` explanation records into preference context and requirements context |
| `app/Services/Dna/PropertyDnaExplanationService.php` | Phase O dependency — source of all explanation strings |
| `app/Services/Dna/BuyerTenantDnaExplanationService.php` | Phase O dependency — source of all explanation strings |
| `app/Models/PropertyDnaProfile.php` | Input model for `PropertyMarketingContextService` |
| `app/Models/BuyerTenantDnaProfile.php` | Input model for `BuyerTenantMarketingContextService` |
| `app/Services/Dna/PropertyDnaGenerator.php` | Source of truth for archetype tag prefixes |
| `app/Services/Dna/BuyerTenantDnaGenerator.php` | Source of truth for lifestyle tag prefixes and bare tags |

The file to be created in Phase R:

| File | Role |
|------|------|
| `app/Services/Dna/PropertyMarketingBriefService.php` | Phase R (not yet created) — deterministic brief builder consuming Phase P output |

---

## 12. Confirmation of Scope

This document confirms the following regarding Phase Q:

- **File created:** `docs/PROPERTY_DNA_PHASE_Q_MARKETING_BRIEF_READINESS_PLAN.md`
- **PHP files changed:** None
- **Routes changed:** None
- **UI changed:** None
- **Schema or migrations changed:** None
- **AI calls introduced:** None
- **Recommended MVP:** Phase R — Deterministic Property Marketing Brief Builder (`PropertyMarketingBriefService`)
- **Allowed brief sections:** Property Attribute Context, Transaction Context, Quantitative Context, Marketing Asset Checklist, Missing Information Checklist, Seller/Landlord Questions, Listing Preparation Notes, Neutral Property Feature Summary
- **Prohibited outputs:** Demographic targeting, protected-class targeting, "ideal buyer" / "best buyer" claims, suitability/ranking/predictive language, autonomous ad launch, audience upload recommendations, school demographic analysis, neighborhood demographic assumptions, income/class targeting, creditworthiness inference
- **Future phase sequence:** Phase R (Deterministic Brief Builder) → Phase S (Admin Preview) → Phase T (Agent-Reviewed Brief UI) → Phase U (AI Guardrails Plan) → Phase V (AI Listing Copy Drafting MVP) → Phase W (Ask AI Listing Context Pack)
