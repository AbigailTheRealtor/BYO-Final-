# BidYourAgent — Professional Representation Compatibility: AI Explanation Layer Design

**Document Status:** Read-only planning document. No code, schema, migration, route, controller, Blade, Livewire, config, database file, AI prompt, API integration, or production logic was created or modified to produce this document. No implementation occurs in this phase.
**Document Date:** 2026-05-28
**Phase:** E — AI Compatibility Explanation Layer Design
**Preceding Phase:** D — Compatibility Scoring Framework (`docs/BIDYOURAGENT_COMPATIBILITY_SCORING_FRAMEWORK.md`)
**Succeeding Phase:** F — Consumer Comparison UI (not yet started)

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Source References](#2-source-references)
3. [Phase E Goals](#3-phase-e-goals)
4. [AI Explanation Philosophy](#4-ai-explanation-philosophy)
5. [Explanation Architecture Overview](#5-explanation-architecture-overview)
6. [AI Explanation Inputs](#6-ai-explanation-inputs)
7. [AI Explanation Outputs](#7-ai-explanation-outputs)
8. [Trait-Level Explanation Design](#8-trait-level-explanation-design)
9. [Informational Context Usage Rules](#9-informational-context-usage-rules)
10. [Consumer-Facing Explanation Rules](#10-consumer-facing-explanation-rules)
11. [Agent-Facing Explanation Rules](#11-agent-facing-explanation-rules)
12. [Explainability and Transparency Standards](#12-explainability-and-transparency-standards)
13. [AI Governance Rules](#13-ai-governance-rules)
14. [Fair Housing Safeguards](#14-fair-housing-safeguards)
15. [Disclosure and Advisory Rules](#15-disclosure-and-advisory-rules)
16. [Future Implementation Dependencies](#16-future-implementation-dependencies)
17. [Deferred Implementation Items](#17-deferred-implementation-items)
18. [Phase F Readiness Checklist](#18-phase-f-readiness-checklist)

---

## 1. Executive Summary

This document is the Phase E deliverable of the BidYourAgent Professional Representation Compatibility system. Its purpose is to conceptually design the AI-generated compatibility explanation layer — the system that translates structured, per-trait comparison results into plain-language explanations that consumers can understand. **No implementation occurs in this phase.** This document produces no code, no AI prompt, no API integration, no embedding system, no vector database, no ranking or recommendation engine, and no production logic of any kind.

The four preceding phases established the complete foundation that Phase E consumes:

**Phase A** (`docs/BIDYOURAGENT_COMPATIBILITY_AUDIT.md`) conducted an exhaustive audit of all consumer-side compatibility fields across the four Hire Agent listing flows — Seller, Buyer, Landlord, and Tenant. **75 consumer-side compatibility fields are collected across all four roles** (22 Seller, 17 Buyer, 16 Landlord, 20 Tenant), while all four agent bid Livewire components collect zero matching fields. Consumer compatibility preference data is gathered and persisted via the EAV `saveMeta`/`loadMeta` pattern but drives no matching, scoring, or display logic. The `listing_compatibility_scores` table has no `representation_compatibility_score` column.

**Phase B** (`docs/BIDYOURAGENT_COMPATIBILITY_TRAIT_DESIGN.md`) took the 75 raw consumer fields and normalized them into a **12-trait ontology** called Professional Representation Compatibility. Phase B resolved four confirmed naming inconsistencies in the consumer-side raw data, produced per-role raw field crosswalks, generated unified option value alignment tables for all multi-role traits, and confirmed that zero agent-side counterparts exist for any of the 12 normalized traits in any of the four bid components.

**Phase C** (`docs/BIDYOURAGENT_AGENT_RESPONSE_DESIGN.md`) designed the conceptual agent-side counterpart response structure. Phase C defined what agents should be asked, how answers should be organized, what option sets they should see, and which traits should eventually contribute to a scored compatibility dimension versus which should remain informational context only.

**Phase D** (`docs/BIDYOURAGENT_COMPATIBILITY_SCORING_FRAMEWORK.md`) conceptually designed the compatibility scoring framework — the methodology for comparing normalized consumer traits to agent-side responses and producing per-trait alignment results (full alignment, partial alignment, adjacent compatibility, neutral compatibility, or incompatible alignment). Phase D confirmed all framework prerequisites are satisfied and that Phase E can begin.

**Phase E — this document** — conceptually designs the AI explanation layer: what inputs the AI explanation system may consume, what outputs it is permitted to produce, how trait-level explanations should be structured, what consumer-facing and agent-facing explanation standards apply, what governance and Fair Housing constraints bind every aspect of the explanation system, and what disclosures must accompany every explanation surfaced to a user.

The governing principle of the entire AI explanation layer is stated here and repeated throughout this document: **AI explanations are advisory only. AI does not recommend, rank, or autonomously decide compatibility. Human decision authority is final.**

---

## 2. Source References

### 2.1 Authoritative Prior Phase Documents

| Document | Phase | Key Deliverables |
|---|---|---|
| `docs/BIDYOURAGENT_COMPATIBILITY_AUDIT.md` | A | Consumer-side field audit across all four roles; 75 consumer compatibility fields catalogued; gap analysis confirming zero agent-side counterparts; governance rules; Fair Housing exclusions |
| `docs/BIDYOURAGENT_COMPATIBILITY_TRAIT_DESIGN.md` | B | 12 normalized traits defined role-neutrally; four naming inconsistency resolutions; per-role raw field crosswalks; option value alignment tables; fields excluded from compatibility |
| `docs/BIDYOURAGENT_AGENT_RESPONSE_DESIGN.md` | C | Agent-side response architecture; unified option sets for all 12 traits; trait-by-trait score-eligibility designation; informational context vs. structured trait separation; governance and Fair Housing safeguards |
| `docs/BIDYOURAGENT_COMPATIBILITY_SCORING_FRAMEWORK.md` | D | Per-trait comparison methodology; four-layer scoring architecture; alignment result types; missing data handling; explainability requirements; governance and Fair Housing safeguards |

### 2.2 Phase A Summary: 75 Consumer Compatibility Fields

| Role | Total Fields | Required | Optional |
|---|---|---|---|
| Seller | 22 | 5 | 17 |
| Buyer | 17 | 5 | 12 |
| Landlord | 16 | 4 | 12 |
| Tenant | 20 | 4 | 16 |
| **Total** | **75** | **18** | **57** |

Phase A's central finding, restated: *All four consumer-side listing flows collect between 16 and 22 compatibility fields each, while all four agent bid Livewire components collect zero matching fields.*

### 2.3 Phase B Summary: 12 Normalized Traits

The following 12 normalized traits constitute the complete Professional Representation Compatibility layer as defined in Phase B. They are role-neutral and apply identically across all four consumer roles.

| # | Normalized Trait | One-Line Description |
|---|---|---|
| 1 | `communication_channel` | The medium(s) through which the consumer prefers to communicate with their agent |
| 2 | `communication_frequency` | How often the consumer expects proactive contact and updates from their agent |
| 3 | `responsiveness_expectation` | How quickly the consumer expects their agent to respond to inbound messages |
| 4 | `negotiation_style` | The posture and philosophy the consumer brings to negotiation situations |
| 5 | `guidance_level` | How much hands-on direction and decision-making involvement the consumer wants from their agent |
| 6 | `decision_making_style` | How the consumer approaches decisions — speed, deliberation, and use of data |
| 7 | `transaction_pace` | The consumer's timeline sensitivity and flexibility around deadlines |
| 8 | `risk_tolerance` | The consumer's appetite for transactional and financial risk |
| 9 | `collaboration_style` | The consumer's preferred operating mode for the agent — proactive, consultative, responsive, or process-focused |
| 10 | `representation_priorities` | The specific tasks, capabilities, and outcomes the consumer most wants their agent to deliver |
| 11 | `representation_philosophy` | The consumer's high-level beliefs about what good representation looks like |
| 12 | `property_strategy_fit` | The consumer's primary goal for the transaction and how it shapes the agent's strategic approach |

### 2.4 Phase D Summary: Scoring Architecture and Explanation Relationship

Phase D designed a four-layer scoring architecture:

- **Layer 1** — Consumer normalized trait values (derived from raw EAV data via Phase B normalization rules)
- **Layer 2** — Agent normalized response values (collected at the trait level per the Phase C architecture)
- **Layer 3** — Per-trait comparison results (alignment labels: full alignment, partial alignment, adjacent compatibility, neutral compatibility, incompatible alignment)
- **Layer 4** — Explainability layer outputs (per-trait alignment summaries, composite advisory signal, audit records)

**AI explanations consume Layer 4 outputs. AI does not produce scoring results, does not modify Layer 3 comparison results, and does not create scores.** The AI explanation layer is strictly downstream from the scoring framework and cannot override, re-weight, or substitute for comparison results.

---

## 3. Phase E Goals

Phase E has eight design goals. All eight must be satisfied by this document's conceptual framework before Phase F (consumer comparison UI) can begin.

### Goal 1 — Plain-Language Compatibility Explanations

Design a conceptual explanation system capable of translating per-trait comparison results into plain, understandable language that a consumer can read without any knowledge of how the compatibility system works internally. Explanations must be approachable, specific, and grounded in the visible traits the consumer themselves provided.

### Goal 2 — Explainable Trait-Level Summaries

Every explanation must be traceable to the specific trait comparison results that generated it. A consumer must be able to connect any explanation sentence to the trait it describes and the values being compared. No explanation may introduce reasoning that cannot be traced to a specific structured input.

### Goal 3 — Advisory-Only AI Behavior

The AI explanation system must behave in a strictly advisory capacity at all times. It translates structured comparison results into language. It does not rank agents, recommend agents, predict outcomes, or make decisions on behalf of any party. The advisory constraint is non-negotiable and is the foundational principle of every design choice in this document.

### Goal 4 — Governance-Safe and Fair Housing-Safe Language Generation

The AI explanation system must not produce language that violates the governance rules established across Phases A through D. Specifically: no demographic references, no protected-class references, no cultural-compatibility framing, no psychological profiling language, no "people like you" language, and no language that functions as a proxy for a prohibited category. Compliance review is required before any AI-generated explanation reaches production.

### Goal 5 — Consumer Understanding

Explanations must be written to be understood by a consumer encountering the compatibility system for the first time. They must use accessible language, avoid jargon, avoid algorithmic references, and communicate what the explanation means in practical terms for the consumer's real estate transaction.

### Goal 6 — Non-Ranking Interpretation

The explanation system must not frame its outputs in any way that implies ranking. Explanations describe alignment and misalignment. They do not declare winners, rank agents from best to worst, use superlatives, or imply that any agent is the "best" or "most compatible" agent for the consumer.

### Goal 7 — Transparency and Traceability

Every explanation must be reproducible from the stored comparison records. The explanation system must not generate outputs that cannot be verified against the underlying structured data. If a comparison record is inspected, the explanation produced from it must be consistent with what that record contains.

### Goal 8 — Auditability

The explanation system must be designed so that the inputs to every explanation and the outputs of every explanation are inspectable by platform administrators without re-running the explanation process. No hidden reasoning, no undisclosed inputs, and no black-box outputs are permissible.

---

## 4. AI Explanation Philosophy

### 4.1 What AI Does in This System

The AI explanation system has one function: it translates structured, per-trait comparison results produced by the Phase D scoring framework into plain-language explanations that are legible to consumers.

Specifically, AI does the following:

- Reads the per-trait comparison results from the Phase D Layer 4 explainability outputs, which contain the consumer's stated preference value, the agent's stated response value, and the alignment label for each scored trait.
- Reads the approved informational context fields (defined in Section 6) that provide relevant non-scored narrative context.
- Produces a plain-language summary of what the comparison results mean in practical terms for the consumer's working relationship with the agent.
- Produces trait-level summaries that describe aligned and misaligned traits in understandable language.
- Produces an "areas to discuss" summary that highlights traits where the consumer and agent would benefit from a direct conversation before a hiring decision.

### 4.2 What AI Does NOT Do in This System

The AI explanation system is explicitly prohibited from doing any of the following:

- **Producing compatibility scores.** AI does not generate, modify, re-weight, or substitute for the numerical or categorical compatibility signals produced by the Phase D scoring framework.
- **Ranking agents.** AI does not produce any output that implies, suggests, or functions as a ranking of agents relative to one another.
- **Recommending agents.** AI does not recommend that a consumer hire, prefer, or avoid any specific agent.
- **Making decisions.** AI does not make hiring decisions, bid award decisions, or any consequential decision on behalf of any party.
- **Profiling consumers or agents demographically.** AI does not infer, reference, or make use of demographic characteristics, protected-class attributes, lifestyle characteristics, or cultural identity in any explanation.
- **Profiling consumers or agents psychologically.** AI does not infer personality types, mental states, emotional dispositions, behavioral tendencies, or psychological profiles from the structured inputs it receives.
- **Optimizing for compensation outcomes.** AI does not incorporate compensation data, commission data, fee structures, or financial optimization logic in any explanation.
- **Generating explanations from non-approved inputs.** AI may only consume the inputs defined in Section 6. It may not access unstructured web data, social media, browsing history, or any data source not explicitly authorized by this document.
- **Replacing human judgment.** No AI explanation constitutes a substitute for the consumer's own evaluation of an agent's bid, qualifications, services, compensation terms, or suitability.

### 4.3 Explainability, Traceability, and Non-Demographic Principles

Every AI explanation must satisfy three foundational principles:

**Explainable:** Every statement in an explanation must trace to a visible, structured comparison result. "This agent states they provide weekly updates; you indicated you prefer daily contact" is explainable. An assertion that an agent is "likely to be a good fit for your personality" without a corresponding structured trait comparison is not explainable and is prohibited.

**Traceable:** The connection between any explanation statement and the structured data that generated it must be auditable. If platform administrators inspect the underlying comparison record, they must be able to confirm that the explanation accurately reflects the data.

**Non-demographic:** Explanations must be derived entirely from the professional working-style traits the consumer and agent explicitly provided. No explanation may reference, imply, or function as a proxy for any demographic characteristic, protected-class attribute, neighborhood characteristic, or socioeconomic indicator.

### 4.4 Advisory Language Standard

All explanations must use language that preserves the advisory nature of the compatibility system. Explanations describe what the structured comparison found. They do not predict what will happen, assert certainty about the working relationship, or claim that any agent will perform better or worse than any other.

---

## 5. Explanation Architecture Overview

The AI explanation architecture consists of five conceptual layers that operate in sequence. AI enters at Layer 4 and produces Layer 5 outputs. It does not participate in Layers 1 through 3.

### Layer 1 — Normalized Consumer Traits

The consumer's raw compatibility preferences, stored in the role-specific `*_agent_auction_metas` table under the `compatibility_preferences` JSON blob, are translated into normalized trait values at the 12-trait level by applying the Phase B normalization rules. This layer produces a per-listing set of normalized consumer trait values, one per trait that the consumer answered. Unanswered traits produce a missing-value marker.

**AI has no access to Layer 1. AI does not read raw consumer preference data directly.**

### Layer 2 — Normalized Agent Responses

The agent's compatibility responses, stored under `compatibility_preferences.agent_response.*` in the bid-specific meta table per the Phase C architecture, are read as normalized trait values. Because agent responses are designed at the normalized trait level, no secondary normalization is required.

**AI has no access to Layer 2. AI does not read raw agent response data directly.**

### Layer 3 — Per-Trait Comparison Results

For each trait where both a consumer value and an agent value exist, the Phase D comparison layer evaluates alignment and produces a per-trait alignment result: full alignment, partial alignment, adjacent compatibility, neutral compatibility, or incompatible alignment. Informational-only traits are flagged as such and are not processed through Layer 3 comparison logic.

**AI has no access to Layer 3. AI does not produce, modify, or override comparison results.**

### Layer 4 — Structured Explanation Payload (AI Input)

The Phase D Layer 4 explainability outputs form the structured explanation payload that AI receives as its sole input for structured trait data. This payload contains, for each scored trait:
- The trait identifier (e.g., `communication_frequency`)
- The consumer's stated preference value (normalized label, not raw key)
- The agent's stated response value (normalized label)
- The alignment result label
- Whether the trait is scored or informational-only
- Any approved informational context values (as defined in Section 6)

This payload is fully structured. It contains no unstructured text from external sources, no demographic data, no compensation data, and no data not explicitly authorized by this document.

### Layer 5 — AI-Generated Natural Language Explanation (AI Output)

AI consumes the Layer 4 payload and produces the plain-language explanation delivered to the consumer. The explanation is strictly bounded by what the Layer 4 payload contains. AI may not augment, supplement, or embellish explanations with information outside the payload.

**AI is downstream from scoring. AI cannot override comparison results, re-score traits, or contradict the alignment results produced by Layer 3.**

---

## 6. AI Explanation Inputs

This section defines the complete, exhaustive set of inputs the AI explanation system is authorized to consume. Any input not listed here is prohibited.

### 6.1 Approved AI Inputs

**Normalized consumer trait values** — The normalized representation of the consumer's stated preferences for each of the 12 traits, as produced by the Phase B normalization layer from the consumer's raw EAV data. These values are structured labels corresponding to the unified option sets defined in Phase B and C, not raw meta key values or free-text entries.

**Per-trait comparison results** — The alignment result labels produced by the Phase D Layer 3 comparison layer for each scored trait: full alignment, partial alignment, adjacent compatibility, neutral compatibility, or incompatible alignment. These results are the primary basis for all AI explanation content.

**Normalized agent response values** — The agent's stated responses for each of the 12 traits, structured at the normalized trait level per the Phase C architecture. These are presented alongside the consumer values in the Layer 4 payload so that the AI explanation can accurately state what each party indicated.

**Informational-context field values** — A defined subset of informational-only fields from the consumer-side compatibility tab that provide narrative context without generating scored comparisons. The approved informational context fields are:
- Narrative notes and additional compatibility notes (free-text fields that the consumer filled in to clarify their preferences in their own words)
- Timeline notes and target timeline descriptions (free-text timeline context)
- Scheduling and availability preferences (available times, showing preferences)
- Representation philosophy statements (the consumer's high-level beliefs about what good representation looks like)
- Concerns, barriers, and past experience notes (where the consumer described prior agent experience or stated concerns)

Informational context fields are used only to add relevant context to an explanation that is already grounded in structured comparison results. They do not generate scored dimensions, do not override structured comparisons, and must not introduce demographic, protected-class, or Fair Housing-sensitive content into explanations.

**Alignment and misalignment labels** — Structured categorical labels summarizing the comparison results in human-readable form, produced by the Layer 4 explainability layer (e.g., "aligned", "partially aligned", "areas to discuss").

### 6.2 Prohibited AI Inputs

The following data categories are explicitly prohibited as AI explanation inputs under any circumstances:

- **Demographic data** — Age, gender, race, ethnicity, national origin, religion, familial status, disability status, sex, or any other characteristic protected under the Fair Housing Act, Equal Credit Opportunity Act, or applicable state law.
- **Protected-class data** — Any data field that names, encodes, or proxies a protected class.
- **Inferred demographic data** — Any data field that, while not explicitly demographic, can be used to infer or estimate a protected-class characteristic for any consumer or agent.
- **Neighborhood demographic data** — Census data, neighborhood composition data, school district demographic data, or any other area-level demographic information.
- **Unstructured web data** — Search engine results, web pages, social media profiles, news articles, review sites, or any internet source not explicitly enumerated in Section 6.1.
- **Social media data** — Any content from social media platforms, regardless of whether it is publicly available.
- **Hidden metadata** — File metadata, browser fingerprinting data, device identifiers, IP geolocation data, or any data that the consumer did not explicitly provide through the compatibility preference form.
- **Compensation or fee data** — Commission rates, referral fees, flat fees, service package pricing, or any financial compensation structure.
- **Ranking or sorting data** — Any signal, score, or order that implies a relative ranking of agents.
- **Behavioral profiling data** — Browsing history, click patterns, time-on-page data, search history, or any data derived from tracking user behavior.
- **Third-party data** — Any data sourced from a third party not explicitly enumerated in Section 6.1.

---

## 7. AI Explanation Outputs

This section defines the approved outputs the AI explanation system may produce and the output types it is explicitly prohibited from generating.

### 7.1 Approved AI Outputs

**Plain-language alignment summaries** — A high-level, readable summary of how the consumer's compatibility preferences compare to the agent's responses across the scored traits. The summary must reference specific traits, must use the advisory language standard defined in Section 4.4, and must not imply a ranking or recommendation.

*Compliant example:* "Based on your stated preferences and this agent's responses, your communication expectations appear to be well aligned. You indicated a preference for daily updates, and this agent describes their standard practice as proactive daily or near-daily contact."

*Non-compliant example:* "This agent is your best match for communication style." *(Implies ranking — prohibited.)*

**Trait-level explanations** — For each scored trait, a brief, plain-language statement describing what the consumer indicated, what the agent indicated, and what the comparison found. Each trait-level explanation must be self-contained and must not reference other traits in a way that creates a hidden composite evaluation.

*Compliant example:* "Negotiation style: You indicated you want an agent who negotiates aggressively to maximize your outcome. This agent describes their negotiation approach as balanced and collaborative. This is an area you may want to discuss directly with the agent."

*Non-compliant example:* "Because this agent is collaborative rather than aggressive, combined with their weekly check-in preference, they score lower overall." *(Combines traits into an unexplained composite; implies scoring — prohibited.)*

**"Areas to discuss" summaries** — A structured list of traits where the comparison found partial alignment, adjacent compatibility, or misalignment, presented as topics the consumer should raise directly with the agent before making a hiring decision. These summaries are informational and advisory only.

**Neutral observations** — Factual, neutral statements about what each party indicated for a given trait, without evaluative judgment. Used for traits where no comparison can be made (e.g., a trait with no consumer-side data for a given role, or an informational-only trait).

**Informational-context acknowledgements** — Brief references to relevant context provided by the consumer in free-text informational fields, where that context clarifies or adds nuance to a structured comparison result. These acknowledgements must not introduce new scoring logic and must not override structured comparison results.

*Compliant example:* "You mentioned in your notes that responsiveness is especially important to you during the offer acceptance period. This agent indicates they maintain same-day response times during active negotiation phases."

**Advisory guidance** — Brief, general advisory statements reminding the consumer that compatibility explanations are one input among many, that direct conversation with the agent is encouraged, and that the hiring decision remains entirely with the consumer.

### 7.2 Prohibited AI Outputs

The following output types are explicitly prohibited under any circumstances:

- **Recommendations** — Any statement that tells a consumer to hire, prefer, consider, or avoid a specific agent.
- **Endorsements** — Any statement that characterizes an agent as suitable, appropriate, or good for a consumer in a way that goes beyond describing structured comparison results.
- **"Best agent" language** — Any superlative framing, including "best match", "top match", "most compatible", "highest compatibility", "most aligned", or equivalent.
- **Definitive rankings** — Any output that ranks agents from most to least compatible, even implicitly through comparative language.
- **Psychological or demographic interpretations** — Any statement that infers, implies, or references a consumer's or agent's personality type, demographic characteristic, cultural background, or lifestyle.
- **Hidden score explanations** — Any statement that references the compatibility score itself in a way that implies the score is a definitive judgment rather than an advisory signal. Explanations must describe the underlying comparison results, not the score as an endpoint.
- **Fabricated reasoning** — Any explanation statement that introduces reasoning not traceable to the structured inputs in the Layer 4 payload.
- **Certainty claims** — Any statement that predicts how the working relationship will unfold, asserts that compatibility will lead to a successful transaction, or claims with certainty that a match is good or bad.

---

## 8. Trait-Level Explanation Design

For all 12 normalized traits, this section conceptually describes how explanations should be constructed for each alignment category: aligned, partially aligned, adjacent, incompatible, and informational-only. No prompts, prompt templates, implementation code, or AI API specifications are provided. All examples are compliant illustration only.

### 8.1 `communication_channel`

This trait compares the channels through which the consumer prefers to communicate (e.g., phone, text, email, video, in-person) against the channels the agent describes as their primary working channels. It uses a multi-select overlap model.

**Aligned:** The consumer's preferred channels are fully represented in the agent's stated primary channels.
*Compliant:* "Your preferred contact methods — email and text message — are both listed as primary channels by this agent."

**Partially aligned:** Some but not all of the consumer's preferred channels appear in the agent's response.
*Compliant:* "You prefer to communicate by phone and in person. This agent identifies email and video calls as their primary channels. There is partial channel overlap; you may want to ask directly about their phone and in-person availability."

**Incompatible:** The consumer's preferred channels are entirely absent from the agent's stated channels.
*Compliant:* "You indicated a preference for in-person communication. This agent describes their standard as platform messaging only. This is a potential working style mismatch worth discussing."

**Non-compliant examples (prohibited regardless of alignment):**
- "This agent communicates in the style that people like you tend to prefer." *(Demographic inference — prohibited.)*
- "Their communication style makes them a poor fit." *(Evaluative judgment — prohibited.)*

### 8.2 `communication_frequency`

This trait compares the consumer's expected proactive contact cadence against the agent's stated standard update rhythm.

**Aligned:** The consumer's expectation and the agent's standard cadence match (e.g., both indicate weekly updates).
*Compliant:* "You indicated you prefer weekly updates. This agent describes their standard practice as weekly client check-ins."

**Partially aligned:** The consumer expects somewhat more frequent contact than the agent's standard, but the gap is bridgeable.
*Compliant:* "You indicated a preference for updates every few days. This agent describes their standard as weekly check-ins. There is a modest gap in expected frequency that you may want to discuss."

**Incompatible:** The consumer's expected cadence and the agent's stated standard are significantly misaligned (e.g., consumer expects daily contact, agent describes contact only at major milestones).
*Compliant:* "You indicated you prefer daily updates. This agent describes their standard as reaching out only at major milestones. This is a meaningful difference in communication expectations worth discussing directly."

### 8.3 `responsiveness_expectation`

This trait compares the consumer's expected response time for inbound messages against the agent's stated response time commitment.

**Aligned:** The agent's stated response time meets or exceeds the consumer's stated expectation.
*Compliant:* "You indicated you expect same-day responses. This agent states they typically respond within a few hours during business hours."

**Partially aligned:** The agent's stated response time is close to but slightly slower than the consumer's expectation.
*Compliant:* "You indicated you expect a response within a few hours. This agent describes their standard as same-business-day responses. There is a modest gap in responsiveness expectations."

**Incompatible:** The agent's stated response time is meaningfully slower than the consumer's expectation.
*Compliant:* "You indicated you expect a response within one hour. This agent describes their standard turnaround as up to 48 hours. This is a significant gap in responsiveness expectations worth discussing before proceeding."

**Note on roles without consumer-side data:** For Buyer and Tenant roles, where no consumer-side `responsiveness_expectation` field exists, this trait cannot be scored. Any agent response for this trait is presented as informational context only.

### 8.4 `negotiation_style`

This trait compares the consumer's preferred negotiation posture (e.g., aggressive, collaborative, flexible) against the agent's stated negotiation approach.

**Aligned:** The consumer's preferred posture and the agent's stated approach are concordant.
*Compliant:* "You indicated you want an agent who negotiates aggressively on your behalf. This agent describes their approach as assertive and results-focused."

**Partially aligned:** The postures are compatible in some respects but differ in emphasis.
*Compliant:* "You indicated you prefer a balanced, fair approach to negotiation. This agent describes their style as firm on key terms but collaborative overall. These approaches are broadly compatible, though you may want to discuss specific scenarios."

**Incompatible:** The consumer's preferred posture and the agent's stated approach are clearly misaligned.
*Compliant:* "You indicated you want aggressive negotiation to maximize your outcome. This agent describes their approach as collaborative, prioritizing outcomes that work for both parties. This is a meaningful difference in negotiation philosophy."

### 8.5 `guidance_level`

This trait compares the consumer's desired level of involvement and delegation against the agent's stated operating mode.

**Aligned:** The consumer's delegation preference matches the agent's stated approach.
*Compliant:* "You indicated you prefer a mostly hands-off arrangement where your agent manages the process. This agent describes their standard as handling most coordination and keeping clients informed at key steps."

**Partially aligned:** The consumer's preference and the agent's approach overlap but differ in degree.
*Compliant:* "You indicated you want to be involved in all major decisions. This agent describes their approach as leading clients through the process and consulting on significant choices. There is reasonable alignment, though you may want to clarify expectations for routine decisions."

**Incompatible:** The consumer expects more or less involvement than the agent's stated approach provides.
*Compliant:* "You indicated you want to manage every decision yourself with the agent in a supporting role. This agent describes their standard as taking the lead and managing most steps autonomously. This is a meaningful difference in the expected working dynamic."

### 8.6 `decision_making_style`

This trait compares the consumer's self-described decision-making approach (fast/independent, deliberate, data-driven, collaborative) against the agent's stated capacity to support that approach.

**Aligned:** The agent's stated approach to presenting information and supporting decisions is compatible with the consumer's stated decision-making style.
*Compliant:* "You indicated you make decisions quickly and independently. This agent describes their approach as presenting concise options and respecting the client's autonomy in deciding."

**Partially aligned:** There is general compatibility but some difference in pace or style that may require adjustment.
*Compliant:* "You indicated you make decisions carefully and need time to deliberate. This agent describes their approach as thorough and advisory, though they note they typically maintain a steady transaction pace. There is reasonable alignment, with some variation in pacing expectations."

**Incompatible:** The consumer's decision-making approach and the agent's stated support style are misaligned.
*Compliant:* "You indicated you are data-driven and want comprehensive analysis before deciding. This agent describes their approach as relationship-focused and intuition-guided. You may want to ask directly about how they support data-oriented clients."

**Note on roles without consumer-side data:** For Landlord role, where no consumer-side `decision_making_style` field exists, this trait cannot be scored. Any agent response is informational only.

### 8.7 `transaction_pace`

This trait compares the consumer's timeline flexibility and urgency against the agent's stated capacity to work within that timeline.

**Aligned:** The consumer's timeline expectations and the agent's stated pace are compatible.
*Compliant:* "You indicated your timeline is flexible. This agent describes their standard as adapting to the client's schedule and not imposing a pace."

**Partially aligned:** The consumer has some timeline sensitivity that the agent's approach can partially accommodate.
*Compliant:* "You indicated your timeline has limited flexibility. This agent describes their approach as responsive to client timelines, though they note they work best with a few weeks of preparation time."

**Incompatible:** The consumer's timeline is firm and the agent's stated approach is not aligned with that urgency, or vice versa.
*Compliant:* "You indicated you need to complete this transaction within two weeks. This agent describes their standard pace as deliberate, with most transactions taking six to eight weeks. This is a significant timeline mismatch worth discussing before proceeding."

### 8.8 `risk_tolerance`

This trait compares the consumer's stated appetite for transactional risk against the agent's stated comfort level working with clients at different risk postures.

**Aligned:** The consumer's risk posture and the agent's stated approach are concordant.
*Compliant:* "You indicated a conservative approach, prioritizing standard protections and criteria. This agent describes their practice as thorough and standards-based."

**Partially aligned:** There is general compatibility but some difference in the degree of caution applied.
*Compliant:* "You indicated a moderate risk tolerance. This agent describes their approach as flexible and case-by-case. These are broadly compatible postures, though you may want to discuss your specific non-negotiables."

**Incompatible:** The consumer's risk posture and the agent's stated approach are clearly misaligned.
*Compliant:* "You indicated you are conservative and want strict criteria applied throughout. This agent describes their approach as highly flexible and willing to work with most situations. This is a meaningful difference in risk approach."

**Note on roles without consumer-side data:** For Seller and Tenant roles, where no direct consumer-side `risk_tolerance` field exists, this trait cannot be scored from consumer input. Agent responses are informational only for these roles.

### 8.9 `collaboration_style`

This trait compares the consumer's preferred agent operating mode (proactive/initiative-driven, consultative, responsive/on-demand, process-focused, full-service concierge) against the agent's stated working style.

**Aligned:** The consumer's preferred agent persona matches the agent's self-description.
*Compliant:* "You indicated you want an agent who is highly proactive and anticipates your needs without being asked. This agent describes their working style as initiative-driven and proactive client communication."

**Partially aligned:** The consumer's preference and the agent's style are broadly compatible but emphasize different elements.
*Compliant:* "You indicated you prefer a consultative agent who explains options and guides decisions. This agent describes their style as advisor-focused with a responsive presence. These styles are compatible, with some variation in how proactively they initiate guidance."

**Incompatible:** The consumer's preferred operating mode and the agent's stated style are clearly misaligned.
*Compliant:* "You indicated you want a full-service agent who manages all details and keeps you informed. This agent describes their approach as hands-off facilitation, making connections and leaving execution to the client. This is a significant working style difference."

### 8.10 `representation_priorities`

This trait compares the consumer's stated representation priorities — the specific tasks and capabilities they most value — against the agent's stated areas of capability and focus. Because the content of representation priorities differs substantially across roles, comparisons are made against the role-appropriate option set.

**Aligned:** The agent's stated areas of capability align with the consumer's top priorities.
*Compliant (Seller role):* "You identified marketing strategy and strong negotiation as your top priorities. This agent identifies both as central to their seller representation approach."

**Partially aligned:** The agent covers some but not all of the consumer's top priorities.
*Compliant (Buyer role):* "You identified neighborhood expertise and contract protection as your top priorities. This agent emphasizes neighborhood knowledge and transaction management, with less specific mention of contract detail focus. You may want to ask directly about their approach to contract protection."

**Incompatible:** The agent's stated focus does not cover the consumer's stated priorities.
*Compliant (Landlord role):* "You identified tenant screening and vetting as your top priority. This agent emphasizes market pricing guidance and marketing as their primary landlord representation strengths. You may want to ask specifically about their screening process."

### 8.11 `representation_philosophy`

This trait is designated as an informational-only trait in Phase C. It does not generate a scored comparison. It appears in explanations only as context.

**Informational-only treatment:**
*Compliant:* "In their professional philosophy statement, this agent describes their approach as client-first and transparency-focused. This is provided for your information and is not part of the structured compatibility comparison."

**Non-compliant example (prohibited):**
*"Because this agent's philosophy emphasizes transparency, their score is higher in the governance-safe range."* *(References scoring in relation to informational content — prohibited.)*

### 8.12 `property_strategy_fit`

This trait compares the consumer's primary transaction goal (maximize sale price, quick sale, long-term stable tenant, etc.) against the agent's stated areas of strategic specialization.

**Aligned:** The agent's stated strategic strengths match the consumer's primary goal.
*Compliant:* "You indicated your primary goal is to maximize your sale price. This agent describes their seller representation approach as focused on pricing strategy and negotiating to achieve top-of-market outcomes."

**Partially aligned:** The agent's strengths are relevant but not specifically optimized for the consumer's primary goal.
*Compliant:* "You indicated your primary goal is a quick sale. This agent describes a balanced approach that includes speed of transaction as one of several priorities. You may want to discuss their specific approach to accelerating timelines."

**Incompatible:** The agent's stated strategy is misaligned with the consumer's primary goal.
*Compliant:* "You indicated your primary goal is minimal disruption and a smooth process. This agent describes their approach as maximizing leverage and pushing aggressively for the best terms. These strategic emphases differ meaningfully."

---

## 9. Informational Context Usage Rules

Informational-only fields — those that do not generate scored comparisons but provide relevant narrative context — may be referenced by the AI explanation system subject to the rules in this section.

### 9.1 Approved Informational Context Fields

The following consumer-side informational fields may be consumed by the AI explanation system as context inputs:

- **Additional compatibility notes** (all roles): Free-text fields where consumers elaborated on their preferences in their own words.
- **Target timeline and post-transaction plan** (Seller): Narrative description of the consumer's desired timeline and plans after the transaction, providing context for the `transaction_pace` explanation.
- **Availability windows and scheduling preferences** (Buyer, Tenant): Notes about when the consumer is available for showings, calls, or meetings, providing context for `communication_channel` and `collaboration_style` explanations.
- **Representation philosophy statements** (agent-side): The agent's narrative professional philosophy statement, providing context for `representation_philosophy` explanations.
- **Past agent experience and concerns** (Seller, Buyer): Notes about the consumer's past experience with agents, including what did not work well, providing context for explanations where that history is relevant to a current comparison result.
- **Deal breakers and non-negotiable requirements** (Buyer): Free-text descriptions of requirements the consumer will not compromise on, providing context for areas-to-discuss summaries.

### 9.2 Rules for Using Informational Context

**Informational context adds nuance; it does not generate new scores.** If an informational context field mentions a preference that is not covered by a structured trait comparison, that preference may be acknowledged in the explanation as context, but it may not be treated as generating an alignment or misalignment result.

**Informational context does not override structured comparisons.** If a consumer's free-text note expresses a preference that contradicts the structured comparison result for a related trait, the structured comparison result governs. The free-text note may be acknowledged, but the AI explanation must not reverse or qualify the structured result based solely on narrative content.

**Informational context must not introduce prohibited categories.** If a consumer's free-text note contains language that references a protected class, a demographic characteristic, or a culturally sensitive preference, that language must not be incorporated into the AI explanation. The informational context is used only where it adds relevant, permitted context to a structured comparison.

**Informational context must be accurately attributed.** When the AI explanation references an informational context field, it must be clear that the reference is to the consumer's own notes or the agent's narrative statement, not to a structured comparison result.

### 9.3 Informational Context vs. Structured Comparison: Display Distinction

Consumer-facing explanations must clearly distinguish between statements derived from structured comparison results and statements derived from informational context fields. The design of this distinction is a Phase F UI concern; Phase E requires only that the AI output preserve the distinction in its language so that Phase F can surface it appropriately.

---

## 10. Consumer-Facing Explanation Rules

This section defines the standards that govern all AI-generated explanations delivered to consumers.

### 10.1 Language Standards

- Explanations must be written in plain, accessible English (or the consumer's language, in a future localization context) that a first-time homebuyer or renter can understand without any background in compatibility systems or real estate technology.
- Explanations must avoid jargon, algorithmic terminology, scoring terminology, and internal system references.
- Explanations must be specific — each statement must reference the actual trait and the actual values being compared, not generic observations about compatibility.
- Explanations must be concise. Trait-level summaries should typically be one to three sentences. Overall summaries should typically be one paragraph.

### 10.2 False Certainty Prohibition

Explanations must not assert or imply certainty about how the working relationship will proceed. Compatibility describes stated preferences at a point in time. It does not predict outcomes, guarantee satisfaction, or account for factors outside the structured comparison.

*Compliant:* "Based on your stated preferences and this agent's responses, your communication expectations appear to be broadly aligned."

*Non-compliant:* "You will have excellent communication with this agent." *(Certainty claim — prohibited.)*

### 10.3 No "Best Match" Framing

Explanations must not use any superlative or comparative language that implies ranking across agents.

*Compliant:* "This agent's stated update cadence aligns with your preference for regular contact."

*Non-compliant:* "This agent is your best match for communication style." *(Ranking language — prohibited.)*
*Non-compliant:* "This agent has the highest compatibility of the bids you received." *(Cross-bid comparison — prohibited.)*

### 10.4 Trait Reference Requirement

Every substantive explanation statement must reference the specific trait it is describing. Explanations that make general observations about "fit" or "compatibility" without reference to a specific trait are prohibited.

*Compliant:* "Negotiation style: You indicated you prefer a collaborative approach. This agent describes their style as collaborative and focused on outcomes that work for both parties."

*Non-compliant:* "Overall, this agent seems like a good fit for you." *(No trait reference — prohibited.)*

### 10.5 Encouragement of Direct Communication

Explanations for traits showing partial alignment, adjacent compatibility, or misalignment should encourage the consumer to discuss those specific topics directly with the agent before making a hiring decision. The framing must be constructive and informational, not alarming or dissuasive.

*Compliant:* "Communication frequency: There is some difference between your preferred contact cadence and this agent's stated standard. This is a topic worth discussing directly with the agent to clarify expectations."

*Non-compliant:* "This agent does not communicate frequently enough for your needs. You should consider other bids." *(Dissuasive recommendation — prohibited.)*

### 10.6 Compliant vs. Non-Compliant Examples Summary

| Scenario | Compliant | Non-Compliant |
|---|---|---|
| Full alignment on negotiation | "Your negotiation preferences align with this agent's stated approach." | "This agent is the best negotiator for your goals." |
| Partial alignment on communication | "There is some difference in communication frequency expectations worth discussing." | "This agent communicates too infrequently for your needs." |
| Misalignment on guidance level | "Your preference for hands-on involvement differs from this agent's standard autonomous approach." | "This agent is not a good fit for you." |
| Informational context reference | "You mentioned in your notes that timing is critical. This agent notes they prioritize responsive timelines." | "Because you are in a hurry, this agent is the right choice." |

---

## 11. Agent-Facing Explanation Rules

This section defines what agents may eventually be shown through an agent-facing explanation view, and what must never be surfaced to agents.

### 11.1 What Agents May Eventually See

In a future phase where agent-facing explanation views are implemented, agents may be shown:

- **Professional alignment summaries** — A general summary of how the agent's compatibility responses compared to the consumer's stated preferences, described at the trait level. The purpose is to help agents understand whether their stated working style is a good fit for a particular consumer's stated expectations.
- **Communication expectation context** — A description of the consumer's stated communication preferences (channel, frequency, responsiveness), framed in a way that helps the agent understand what the consumer has indicated they expect without disclosing the consumer's private notes.
- **Representation preference summaries** — A description of the consumer's stated representation priorities and philosophy, framed to help the agent understand whether the consumer's goals align with the agent's stated capabilities.

Agent-facing explanations, if implemented, serve the goal of helping agents self-assess whether a listing is a strong fit for their working style before deciding to pursue a bid. They are informational and advisory, not directives.

### 11.2 What Agents Must Never See

The following categories of information must never be surfaced to agents through any explanation system:

- **Raw consumer profiling data** — Agents must not receive a detailed breakdown of the consumer's compatibility responses framed as a profile or personality assessment.
- **Hidden trait analysis** — Agents must not receive AI-generated inferences about the consumer's character, psychology, motivations, or unspoken preferences.
- **Demographic inference** — Agents must not receive any explanation that draws inferences about a consumer's demographic characteristics, protected-class attributes, or lifestyle from their stated preferences.
- **Manipulation guidance** — Agents must not receive any explanation that advises them how to adjust their behavior, presentation, or communication in order to appear more compatible or improve their bid outcome. Compatibility is a transparent, structured comparison — not a system for strategic manipulation.
- **Consumer's private notes in full** — Free-text notes that consumers added to their compatibility preferences are informational context for explanation generation only. They are not displayed verbatim to agents.

### 11.3 Agent Explanation Advisory Standard

All agent-facing explanations must use the same advisory language standard as consumer-facing explanations. They do not declare the agent compatible or incompatible — they describe what the comparison found and encourage direct communication with the consumer about areas of difference.

---

## 12. Explainability and Transparency Standards

This section defines the minimum explainability and transparency requirements that bind every component of the AI explanation system.

### 12.1 Trace-to-Comparison Requirement

Every statement in an AI-generated explanation must be traceable to a specific comparison result in the Layer 4 structured explanation payload. This means:

- If the explanation states that communication frequency is aligned, there must be a Layer 4 record showing a full alignment or partial alignment result for the `communication_frequency` trait.
- If the explanation states that negotiation style is an area to discuss, there must be a Layer 4 record showing a misalignment or adjacent compatibility result for the `negotiation_style` trait.
- If the explanation references an informational context field, that field must be present in the approved inputs for the explanation and must have been included in the Layer 4 payload.

Explanations that introduce reasoning not traceable to a Layer 4 record are classified as fabricated reasoning and are prohibited.

### 12.2 No Black-Box Explanations

The AI explanation system must not produce outputs whose reasoning cannot be audited. This means:

- The inputs provided to the AI explanation system for any given explanation must be logged and stored alongside the explanation output.
- Platform administrators must be able to inspect the inputs and the output for any given explanation and verify that the output is consistent with the inputs.
- The explanation must not reference any reasoning that is not derivable from the logged inputs.

### 12.3 No Hidden Weighting References

Explanations must not reference weighting, scoring scales, scoring thresholds, or numerical values that imply the consumer's traits are being weighted against each other in a hidden formula. The existence of a composite advisory signal (from Phase D Layer 4) must not be explained in terms of hidden weighting.

*Compliant:* "Based on the structured comparison of your stated preferences and this agent's responses, most traits appear broadly aligned."

*Non-compliant:* "Because communication is weighted more heavily than negotiation style in your profile, this agent scores well despite the negotiation mismatch." *(References hidden weighting — prohibited.)*

### 12.4 No Fabricated Reasoning

The AI explanation system must not generate reasoning that goes beyond what the structured inputs contain. Specifically:

- AI may not infer that an agent is likely to be good at something that is not addressed by their structured responses.
- AI may not infer that a consumer is likely to have a preference that they did not explicitly state.
- AI may not produce "common sense" additions to explanations that introduce new evaluation dimensions not present in the structured input.

### 12.5 Reproducibility Requirement

An explanation produced for a given comparison record must be reproducible from that record. If the comparison record is stored and the explanation is regenerated at a later time, the regenerated explanation must be substantively consistent with the original — it must reference the same traits, the same alignment results, and the same informational context fields. Explanations must not be sensitive to factors outside the stored comparison record.

---

## 13. AI Governance Rules

This section states the complete governance framework binding the AI explanation system. These rules carry forward from Phases A through D and are expanded here to address AI-specific risks.

### 13.1 Advisory-Only Requirement

AI explanations are advisory only, always and without exception. No AI explanation constitutes a recommendation, endorsement, ranking, or decision. No AI explanation may be used as the sole basis for a hiring decision by any party. All consequential decisions remain with the human parties.

This principle is non-negotiable. No implementation phase, performance optimization, or product feature may relax or circumvent it.

### 13.2 No Autonomous Recommendations or Ranking

The AI explanation system must not produce any output that functions as an autonomous recommendation or ranking, regardless of how that output is labeled. Labeling a prohibited output as a "suggestion" or an "observation" does not make it compliant. The test is functional: does the output function as a recommendation or ranking? If yes, it is prohibited.

### 13.3 No Hidden Optimization

The AI explanation system must not be configured, fine-tuned, or prompted in a way that optimizes for any hidden objective — including maximizing bid acceptance rates, reducing time-on-platform, matching consumers to agents based on demographic similarity, or any other objective not explicitly disclosed to users. All objectives that influence AI explanation outputs must be disclosed and auditable.

### 13.4 No Demographic or Psychological Profiling

The AI explanation system must not produce outputs that characterize a consumer's or agent's demographic profile, psychological type, personality traits, or cultural characteristics — explicitly or implicitly. The prohibition applies to direct characterization ("this consumer values community identity") and to proxy characterization ("consumers in this area tend to prefer this style").

### 13.5 No Manipulation or Coercive Language

The AI explanation system must not produce language designed to move a consumer toward or away from a particular agent through psychological pressure, urgency framing, social proof appeals, or loss-aversion language. Explanation language must be informational and neutral in its emotional register.

*Non-compliant example:* "Agents with this communication style are in high demand. Act quickly to secure this agent." *(Urgency and social proof — prohibited.)*
*Non-compliant example:* "If you miss this opportunity for a high-compatibility agent, finding another may be difficult." *(Loss aversion — prohibited.)*

### 13.6 Human Decision Authority Requirement

The governance framework requires, as a non-negotiable design principle, that no AI explanation output may substitute for, override, or significantly constrain human decision authority. Platform design must ensure that explanations are presented as one input among many and that consumers always retain full control over their hiring decision.

### 13.7 Explainability and Auditability Requirements

All AI explanation inputs and outputs must be logged and auditable. No AI explanation may be surfaced to users without a corresponding audit record that allows platform administrators to verify the explanation's traceability to its structured inputs. Audit records must be retained for a period sufficient to support compliance review.

### 13.8 Compliance Review Before Production

No AI-generated explanation may be surfaced to real users in a production environment without a formal compliance review process that verifies the explanation system's outputs against all governance and Fair Housing rules defined in this document. The compliance review process must include review of AI explanation outputs across all four consumer roles, multiple trait alignment scenarios, and all prohibited output categories.

---

## 14. Fair Housing Safeguards

This section explicitly states the Fair Housing constraints that bind every aspect of the AI explanation system. These constraints carry forward from Phases A through D and are expanded here to address AI-specific Fair Housing risks.

### 14.1 Prohibited Explanation Categories

The following categories of AI explanation are explicitly prohibited because they create Fair Housing risk:

**Cultural compatibility explanations** — Explanations that reference cultural fit, cultural values, cultural preferences, or community values as a basis for compatibility. All compatibility comparisons are bounded by the 12 normalized professional working-style traits. Culture is not a permitted dimension.

**Demographic similarity explanations** — Explanations that suggest, imply, or reference any form of consumer-agent demographic similarity as a compatibility factor. Similarity in age, background, neighborhood, language, or any other demographic dimension is not a permitted compatibility input.

**Neighborhood lifestyle explanations** — Explanations that reference neighborhood lifestyle, community character, neighborhood demographics, area composition, or area identity. Property location is not a permitted compatibility dimension in the representation compatibility layer.

**Family-status, religious, or disability assumptions** — Explanations that reference or infer a consumer's family composition, religious practice, disability status, or any related characteristic. These characteristics are not relevant to professional working-style compatibility and are protected categories under the Fair Housing Act.

**Socioeconomic inference** — Explanations that infer or reference socioeconomic status, income level, financial stability, or class indicators as a compatibility dimension. Financial compatibility, where it is assessed at all, belongs in the existing `financial_match_score` dimension — not in the representation compatibility explanation layer.

**"People like you" language** — Any language that references a group identity, consumer segment, or consumer category as a basis for explaining compatibility. Compatibility is always described in terms of what this consumer specifically stated and what this agent specifically responded — never in terms of what consumers "like this" typically prefer.

**Protected-class proxy explanations** — Explanations that use non-demographic language to imply a protected-class dimension. For example, referencing a consumer's "neighborhood network preferences" in a way that functions as a proxy for racial or ethnic neighborhood composition is prohibited.

### 14.2 Affirmative Requirements

In addition to the prohibitions above, all AI explanations must satisfy the following affirmative Fair Housing requirements:

**Explainable trait grounding** — Every compatibility explanation must be grounded in one or more of the 12 normalized professional working-style traits. If an explanation cannot be grounded in a permitted trait, it must not be generated.

**Consistent application** — The AI explanation system must produce explanations using the same methodology regardless of the consumer's demographic characteristics, location, property type, or any other consumer attribute not relevant to the 12 normalized traits.

**No steering** — The AI explanation system must not produce outputs that function as steering — directing consumers toward or away from agents in ways that are correlated with protected-class characteristics. If any explanation output pattern is found to be correlated with consumer or agent demographic characteristics, it must be investigated, addressed, and reviewed before that explanation type continues to be used in production.

### 14.3 Compliance Review Before Production Use

Before any AI-generated explanation is surfaced to real users in a production environment, a formal Fair Housing compliance review must be conducted. This review must include:

- Review of all output categories for prohibited language patterns.
- Review of explanation outputs across multiple roles, property types, and geographic regions for evidence of disparate treatment or steering patterns.
- Review by a qualified Fair Housing compliance professional or legal counsel.
- Documentation of the review, its findings, and any remediation taken.

Phase H (production AI integration and compliance testing) is the designated phase for this review. No production AI explanation system may operate without completing Phase H compliance testing.

---

## 15. Disclosure and Advisory Rules

This section defines the required disclosures that must accompany AI-generated compatibility explanations whenever they are surfaced to users.

### 15.1 Required Disclosures

The following disclosures are required and must be presented to consumers at or before the point where AI-generated explanations are shown:

**Compatibility is advisory only** — The compatibility explanation is an advisory tool. It is not a recommendation, an endorsement, or a ranking. It describes how stated preferences compare — it does not predict how the working relationship will unfold or guarantee any outcome.

**Explanations are generated from structured trait comparisons** — The explanation is based on a structured comparison of the consumer's stated preferences and the agent's stated responses to a defined set of professional working-style traits. The explanation does not incorporate agent credentials, production history, market expertise, service scope, or compensation terms.

**Explanations are not recommendations** — No part of the compatibility explanation constitutes a recommendation to hire or not hire any agent. The decision of which agent to hire, if any, remains entirely with the consumer.

**Explanations do not replace human judgment** — Compatibility is one factor among many. The consumer is encouraged to review the agent's full bid, their services, their compensation terms, and their qualifications, and to have direct conversations with agents about any questions or concerns before making a decision.

**Compatibility is one factor among many** — Other important factors in evaluating an agent bid include the agent's experience, local market knowledge, specific service offerings, compensation structure, references, and the consumer's own judgment from direct interaction.

### 15.2 Compliant Disclosure Language Examples

The following examples illustrate compliant disclosure language. These are conceptual illustrations only, not final UI copy.

*"This compatibility summary is generated from a structured comparison of your stated representation preferences and this agent's stated working style responses. It is advisory only and does not constitute a recommendation. Other factors — including this agent's experience, services, compensation, and your direct conversation with them — are equally important in your decision."*

*"Compatibility signals are one input among many. They describe alignment in professional working-style traits only. Please review this agent's full bid and speak with them directly before making any decision."*

*"The areas listed as 'worth discussing' reflect differences in stated preferences. They are not disqualifying — they are topics to address directly with the agent."*

### 15.3 Disclosure Placement Requirements

Disclosures must be present in any context where AI-generated compatibility explanations are shown. The specific placement, format, and visual design of disclosures is a Phase F UI concern. Phase E requires only that:

- Disclosures must not be hidden, collapsed by default, or accessible only through secondary navigation.
- Disclosures must be visible without requiring additional user action to reveal them.
- Disclosures must be written in the same language and at the same reading level as the explanations they accompany.

---

## 16. Future Implementation Dependencies

This section describes the phases that follow Phase E at a conceptual level only. No implementation details, architecture specifications, or technology choices are provided.

### Phase F — Consumer Comparison UI

Phase F will design the consumer-facing interface through which compatibility explanations are displayed. Phase F is responsible for:

- The visual design and layout of compatibility explanation panels within the bid comparison interface.
- The information hierarchy for displaying overall alignment summaries, trait-level summaries, and areas-to-discuss lists.
- The placement and formatting of required disclosures.
- The design of any interactive elements (e.g., expand/collapse for trait detail, direct links to agent contact for follow-up questions).
- The design of any agent-facing explanation views, subject to the agent-facing rules defined in Section 11 of this document.

Phase F is a UI and experience design phase. It consumes the explanation outputs defined in Phase E and the advisory signals defined in Phase D. It does not redesign the AI explanation architecture or the scoring framework.

### Phase G — Implementation Architecture

Phase G will design the technical implementation architecture for the full compatibility system — the database schema additions, the PHP/Livewire component design, the service layer for normalization and comparison, the API integration architecture for the AI explanation system, and the caching and performance considerations. Phase G is strictly architectural planning. No code is written in Phase G.

Phase G is responsible for determining:

- The specific AI service or model used for explanation generation (subject to governance approval and Fair Housing compliance review).
- The caching strategy for AI-generated explanations.
- The schema for storing structured explanation payloads and audit records.
- The integration pattern between the Laravel application and the AI explanation service.
- The moderation pipeline for AI outputs before they reach users.

### Phase H — Production AI Integration and Compliance Testing

Phase H is the implementation and validation phase. It is responsible for:

- Building the actual AI explanation service integration within the Laravel application.
- Implementing the moderation pipeline for AI outputs.
- Running the formal Fair Housing compliance review required by Section 14.3 of this document before any AI explanation is surfaced to real users.
- Running end-to-end validation of the full five-layer explanation pipeline against real and synthetic data.
- Validating that all governance rules, disclosure requirements, and output prohibitions defined in this document are enforced in the production system.

No AI-generated explanation may be surfaced to any real user before Phase H compliance testing is complete and approved.

---

## 17. Deferred Implementation Items

The following items are explicitly outside the scope of Phase E and are deferred to later phases. Their deferral is intentional. Specifying implementation details for these items in Phase E would constitute premature implementation work that this phase explicitly prohibits.

**AI prompts and prompt templates** — The specific prompts, instructions, or system messages used to elicit explanations from an AI model are deferred to Phase G (implementation architecture) and Phase H (implementation). No prompts are designed, drafted, or implied in this document.

**OpenAI API or other AI API integration** — The specific AI service provider, model selection, API key management, rate limiting, token budget, and cost model are deferred to Phase G. No AI API integration is designed or implied in this document.

**Embeddings and vector similarity** — The question of whether any portion of the compatibility or explanation system uses embedding-based similarity is deferred to Phase G. No embedding approach, vector database, or similarity search architecture is designed or implied in this document.

**Vector databases** — No vector database or semantic search infrastructure is designed or implied in this document.

**AI model selection** — The choice of AI model (including version, fine-tuning approach, temperature settings, or other model-level parameters) is deferred to Phase G and Phase H.

**Moderation pipelines** — The design of content moderation, output filtering, and safety layers for AI-generated explanation text is deferred to Phase G.

**Laravel integration** — The specific Laravel service classes, jobs, queues, cache layers, and component integration for the AI explanation system are deferred to Phase G.

**Caching strategy** — The caching design for AI-generated explanations (when to cache, how long to cache, when to invalidate, where to store cached outputs) is deferred to Phase G.

**UI rendering of explanations** — The Blade components, Livewire components, AlpineJS interactions, and CSS presentation of AI-generated explanations are deferred to Phase F and Phase G.

**Analytics and feedback loops** — Any analytics system for measuring explanation quality, consumer engagement with explanations, or agent response patterns is deferred beyond Phase H.

**Feedback collection from users** — Any mechanism for users to rate, flag, or provide feedback on AI-generated explanations is deferred beyond Phase H.

**Autonomous AI actions** — No autonomous AI action — any action taken by the AI system on behalf of a consumer or agent without explicit human initiation — is designed or implied in this document. All AI system actions are strictly response-generation to explicit human-initiated requests.

**Ranking and recommendation systems** — No system for ranking agents, sorting bid lists, or recommending agents based on compatibility scores is designed or implied in this document. Compatibility is advisory. Ranking and recommendation systems are prohibited by the governance framework.

**Personalization systems** — No system for personalizing the bid experience, filtering agents, or modifying the consumer's view of available bids based on compatibility signals is designed or implied in this document.

---

## 18. Phase F Readiness Checklist

This section confirms that all Phase E deliverables are complete and that Phase F (consumer comparison UI design) may proceed.

### Deliverable 1 — Explanation Architecture Complete

- [x] A conceptual five-layer AI explanation architecture is defined (Layers 1–5 in Section 5).
- [x] The role of AI within the architecture is precisely bounded: AI enters at Layer 4 (consuming structured explanation payloads) and produces Layer 5 (plain-language explanations).
- [x] AI's inability to override, modify, or create scoring results is explicitly stated.

### Deliverable 2 — Approved AI Inputs Documented

- [x] All approved AI input categories are enumerated in Section 6.1.
- [x] All prohibited AI input categories are enumerated in Section 6.2.
- [x] The prohibition on demographic data, protected-class data, inferred demographics, neighborhood data, unstructured web data, social media, behavioral profiling data, compensation data, and ranking data is explicitly stated.

### Deliverable 3 — Approved AI Outputs Documented

- [x] All approved AI output types are enumerated in Section 7.1.
- [x] All prohibited AI output types are enumerated in Section 7.2.
- [x] The prohibition on recommendations, endorsements, "best agent" language, rankings, psychological interpretations, and fabricated reasoning is explicitly stated.

### Deliverable 4 — Informational Context Usage Rules Documented

- [x] Approved informational context fields are defined in Section 9.1.
- [x] Rules governing the use of informational context are defined in Section 9.2.
- [x] The prohibition on informational context overriding structured comparisons is explicitly stated.
- [x] The prohibition on informational context introducing prohibited categories is explicitly stated.

### Deliverable 5 — Consumer-Facing Explanation Standards Documented

- [x] Consumer-facing language standards are defined in Section 10.1.
- [x] The prohibition on false certainty is defined in Section 10.2.
- [x] The prohibition on "best match" framing is defined in Section 10.3.
- [x] The trait reference requirement is defined in Section 10.4.
- [x] The encouragement of direct communication for areas of difference is defined in Section 10.5.
- [x] Compliant and non-compliant examples are provided in Section 10.6.

### Deliverable 6 — Agent-Facing Explanation Standards Documented

- [x] What agents may eventually see is defined in Section 11.1.
- [x] What agents must never see is defined in Section 11.2.
- [x] The advisory standard for agent-facing explanations is defined in Section 11.3.

### Deliverable 7 — Explainability Requirements Documented

- [x] The trace-to-comparison requirement is defined in Section 12.1.
- [x] The no-black-box-explanations requirement is defined in Section 12.2.
- [x] The no-hidden-weighting-references requirement is defined in Section 12.3.
- [x] The no-fabricated-reasoning requirement is defined in Section 12.4.
- [x] The reproducibility requirement is defined in Section 12.5.

### Deliverable 8 — AI Governance Documented

- [x] Advisory-only requirement is stated in Section 13.1.
- [x] No autonomous recommendations or ranking is stated in Section 13.2.
- [x] No hidden optimization is stated in Section 13.3.
- [x] No demographic or psychological profiling is stated in Section 13.4.
- [x] No manipulation or coercive language is stated in Section 13.5.
- [x] Human decision authority requirement is stated in Section 13.6.
- [x] Explainability and auditability requirements are stated in Section 13.7.
- [x] Compliance review before production is stated in Section 13.8.

### Deliverable 9 — Fair Housing Safeguards Documented

- [x] All prohibited explanation categories are enumerated in Section 14.1.
- [x] Affirmative Fair Housing requirements are defined in Section 14.2.
- [x] The compliance review requirement before production use is stated in Section 14.3.

### Deliverable 10 — Disclosure Rules Documented

- [x] All required disclosures are defined in Section 15.1.
- [x] Compliant disclosure language examples are provided in Section 15.2.
- [x] Disclosure placement requirements are defined in Section 15.3.

### Deliverable 11 — No Prompts, APIs, or Production AI Logic Implemented

- [x] No AI prompt or prompt template was created in this phase.
- [x] No AI API integration was designed or configured in this phase.
- [x] No embedding, vector database, or semantic similarity architecture was designed in this phase.
- [x] No Laravel integration code, service class, or job was written in this phase.
- [x] No production AI logic of any kind was implemented in this phase.
- [x] The sole deliverable of Phase E is this planning document.

### Phase F May Proceed

All Phase E deliverables are confirmed complete. Phase F (consumer comparison UI design) may proceed with the assurance that the AI explanation architecture, input/output boundaries, governance framework, Fair Housing safeguards, and disclosure requirements are fully defined and documented.

---

*Document prepared as Phase E deliverable. Read-only. No implementation artifact was produced in this phase.*
