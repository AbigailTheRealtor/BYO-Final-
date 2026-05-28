# BidYourAgent — Professional Representation Compatibility: Scoring Framework

**Document Status:** Read-only planning document. No code, schema, migration, route, controller, Blade, Livewire, config, or database file was modified to produce this document. No implementation occurs in this phase.
**Document Date:** 2026-05-28
**Phase:** D — Compatibility Scoring Framework
**Preceding Phase:** C — Agent-Side Response Design (`docs/BIDYOURAGENT_AGENT_RESPONSE_DESIGN.md`)
**Succeeding Phase:** E — AI Explanation Layer (not yet started)

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Source References](#2-source-references)
3. [Phase D Goals](#3-phase-d-goals)
4. [Compatibility Scoring Philosophy](#4-compatibility-scoring-philosophy)
5. [Scoring Architecture Overview](#5-scoring-architecture-overview)
6. [Trait Comparison Methodology](#6-trait-comparison-methodology)
7. [Trait Eligibility Matrix](#7-trait-eligibility-matrix)
8. [Partial Match Concepts](#8-partial-match-concepts)
9. [Missing Data Handling](#9-missing-data-handling)
10. [Informational-Only Trait Rules](#10-informational-only-trait-rules)
11. [Explainability Requirements](#11-explainability-requirements)
12. [Compatibility Interpretation Guidelines](#12-compatibility-interpretation-guidelines)
13. [Governance Rules](#13-governance-rules)
14. [Fair Housing Safeguards](#14-fair-housing-safeguards)
15. [Versioning and Auditability](#15-versioning-and-auditability)
16. [Future Implementation Dependencies](#16-future-implementation-dependencies)
17. [Deferred Implementation Items](#17-deferred-implementation-items)
18. [Phase E Readiness Checklist](#18-phase-e-readiness-checklist)

---

## 1. Executive Summary

This document is the Phase D deliverable of the BidYourAgent Professional Representation Compatibility system. Its purpose is to conceptually design the compatibility scoring framework — the methodology for comparing normalized consumer traits to agent-side responses and producing an advisory compatibility signal — without implementing any code, schema, migration, formula, scoring algorithm, or user interface.

**Phase A** (`docs/BIDYOURAGENT_COMPATIBILITY_AUDIT.md`) conducted an exhaustive audit of all consumer-side compatibility fields across the four Hire Agent listing flows. The Phase A finding was stark: **75 consumer-side compatibility fields are collected across all four roles** (22 Seller, 17 Buyer, 16 Landlord, 20 Tenant), while **all four agent bid Livewire components collect zero matching fields**. Consumer compatibility preference data is gathered and persisted via the EAV `saveMeta`/`loadMeta` pattern but drives no matching, scoring, or display logic. The `listing_compatibility_scores` table has no `representation_compatibility_score` column.

**Phase B** (`docs/BIDYOURAGENT_COMPATIBILITY_TRAIT_DESIGN.md`) took the 75 raw consumer fields and normalized them into a **12-trait ontology** called Professional Representation Compatibility. Phase B resolved four confirmed naming inconsistencies in the consumer-side raw data, produced per-role raw field crosswalks, generated unified option value alignment tables for all multi-role traits, and confirmed that zero agent-side counterparts exist for any of the 12 normalized traits in any of the four bid components.

**Phase C** (`docs/BIDYOURAGENT_AGENT_RESPONSE_DESIGN.md`) designed the conceptual agent-side counterpart response structure. Phase C defined what agents should be asked, how answers should be organized, what option sets they should see, and which traits should eventually contribute to a scored compatibility dimension versus which should remain informational context only. Phase C confirmed all agent-side response prerequisites are satisfied and that Phase D can begin.

**Phase D — this document** — conceptually designs the compatibility scoring framework: how consumer traits are compared to agent responses, how comparison results are composed into an advisory compatibility signal, what explainability standards apply to every comparison, how missing data is handled, which traits are score-eligible versus informational only, and what governance and Fair Housing constraints bind every aspect of the framework.

**No implementation occurs in this phase.** This document produces no code, schema, migration, Livewire component change, Blade markup, validation rule, scoring formula, AI prompt design, ranking logic, or database column. The sole deliverable of Phase D is this planning document.

---

## 2. Source References

### 2.1 Authoritative Prior Phase Documents

| Document | Phase | Key Deliverables |
|---|---|---|
| `docs/BIDYOURAGENT_COMPATIBILITY_AUDIT.md` | A | Consumer-side field audit across all four roles; 75 consumer compatibility fields catalogued; gap analysis confirming zero agent-side counterparts; governance rules; Fair Housing exclusions |
| `docs/BIDYOURAGENT_COMPATIBILITY_TRAIT_DESIGN.md` | B | 12 normalized traits defined role-neutrally; four naming inconsistency resolutions; per-role raw field crosswalks; option value alignment tables; fields excluded from compatibility |
| `docs/BIDYOURAGENT_AGENT_RESPONSE_DESIGN.md` | C | Agent-side response architecture; unified option sets for all 12 traits; trait-by-trait score-eligibility designation; informational context vs. structured trait separation; governance and Fair Housing safeguards |

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

### 2.4 Phase C Summary: Agent-Side Response Architecture

Phase C designed the conceptual agent-side counterpart response structure organized into seven logical sub-sections under `compatibility_preferences.agent_response.*`:

| Sub-Section | Normalized Traits |
|---|---|
| `communication_preferences` | `communication_channel`, `communication_frequency`, `responsiveness_expectation` |
| `negotiation_approach` | `negotiation_style` |
| `working_style` | `collaboration_style` |
| `guidance_approach` | `guidance_level` |
| `transaction_strategy` | `transaction_pace`, `property_strategy_fit` |
| `representation_priorities` | `representation_priorities` |
| `representation_philosophy` | `representation_philosophy`, `decision_making_style`, `risk_tolerance` |

Phase C designated `communication_channel`, `communication_frequency`, `responsiveness_expectation`, `negotiation_style`, `guidance_level`, `decision_making_style`, `transaction_pace`, `collaboration_style`, `representation_priorities`, and `property_strategy_fit` as score-eligible. It designated `risk_tolerance` and `representation_philosophy` as conditionally score-eligible pending Phase D determination.

---

## 3. Phase D Goals

Phase D has seven design goals. All seven must be satisfied by this document's conceptual framework before Phase E (AI explanation layer) can begin.

### Goal 1 — Explainable Scoring

Every trait comparison that contributes to the compatibility signal must be explainable to both the consumer and the agent in plain, non-technical language. The comparison methodology for each trait must be describable without reference to opaque algorithms, hidden weights, or undisclosed logic. "You indicated you prefer daily updates; this agent describes their standard as weekly check-ins" is an explainable comparison. A composite weighted index with undisclosed factor interactions is not.

### Goal 2 — Trait-Level Comparison Consistency

The comparison methodology must apply consistently at the normalized trait level, not at the raw consumer field level. Because the same normalized trait is fed by different raw sub-keys depending on the consumer's role (e.g., `communication_frequency` is fed by `communication_style` for Seller and Buyer, `preferred_contact_method` for Landlord, and `contact_frequency` for Tenant), the scoring framework must operate on normalized inputs so that the comparison logic is identical regardless of which role's listing is being evaluated.

### Goal 3 — Role-Neutral Scoring Architecture

The scoring architecture must be role-neutral by default. An agent's compatibility response profile, filled out once at the normalized trait level, must be comparable against listings from any of the four consumer roles without requiring role-specific scoring variants for role-neutral traits. Role-scoping is an exception reserved for `representation_priorities`, where the option content is so role-specific that a single unified comparison is not meaningful.

### Goal 4 — Auditability

The framework must be designed so that the consumer's preference, the agent's response, and the comparison result for every trait are all inspectable by platform administrators for compliance review. No comparison input may be hidden from audit. No comparison result may be produced without a corresponding record of the inputs that produced it.

### Goal 5 — Governance-Safe Scoring

The framework must not introduce hidden weighting, demographic profiling, autonomous ranking, or Fair Housing-sensitive comparison logic at any point. The governance rules established in Phases A, B, and C carry forward with full force. This document must not undermine or relax any prior governance constraint.

### Goal 6 — Future AI Explanation Readiness

The trait comparison outputs must be structured in a way that Phase E (AI explanation layer) can consume them without requiring redesign of the scoring outputs. This means each trait comparison must produce a discrete, labelled result — not a single aggregate number with no semantic decomposition. Phase E needs to know which traits aligned and which did not, at a per-trait level, so that it can construct accurate plain-language explanations.

### Goal 7 — Advisory-Only Interpretation

The scoring framework must produce a compatibility signal that is explicitly advisory. The framework must be designed in a way that prevents its outputs from being used as autonomous rankings, definitive recommendations, or decision substitutes. Compatibility is one advisory dimension among many factors a consumer weighs when evaluating an agent bid.

---

## 4. Compatibility Scoring Philosophy

### 4.1 What Compatibility Measures

Professional Representation Compatibility measures one specific dimension: **how well an agent's professional working style, communication approach, negotiation posture, and representation philosophy align with a consumer's stated preferences for those same dimensions**. It measures the working relationship fit — how these two parties will collaborate, communicate, and make decisions together during a real estate transaction.

Compatibility does not measure:
- Whether the agent is the "best" agent in absolute terms.
- Whether the agent's commission or fee is appropriate or competitive.
- Whether the agent's services list is comprehensive.
- Whether the agent has superior market expertise, production volume, or credentials.
- Whether the agent is trustworthy, honest, or ethical in any general sense.
- Any characteristic of the consumer's or agent's identity, background, demographics, or lifestyle.

### 4.2 What Compatibility Is Not

**Compatibility is not a ranking system.** A compatibility signal does not rank agents from best to worst. Two agents with identical compatibility signals may differ substantially on compensation, services, experience, and other dimensions that the consumer must weigh independently.

**Compatibility is not a recommendation engine.** The platform does not recommend agents based on compatibility scores. It presents a compatibility signal that the consumer is free to interpret, weight, and use as one factor among many in a decision that remains entirely theirs.

**Compatibility is not psychological profiling.** The system does not infer personality types, character traits, psychological states, cognitive styles, or any personal characteristic beyond the specific professional working-style preferences that consumers explicitly state and agents explicitly respond to.

**Compatibility is not commission optimization.** The framework does not incorporate compensation, fee, or service scope data. Financial compatibility, if ever measured, belongs in existing score dimensions (`financial_match_score`, `terms_match_score`), not in the representation compatibility layer.

**Compatibility is not a demographic matching system.** The system does not match consumers and agents on the basis of demographic similarity, cultural alignment, shared neighborhood background, or any characteristic that correlates with a protected class. See Section 14 for the complete Fair Housing safeguards.

### 4.3 The Advisory Principle

Compatibility is advisory only. It is one signal among many that a consumer considers when evaluating agent bids. Other factors — the agent's specific service offerings, compensation terms, experience and credentials, responses to listing-specific questions, negotiation outcomes, and the consumer's own judgment — all carry independent weight.

The platform must communicate this principle clearly and consistently at every point where compatibility information is surfaced. The compatibility system does not make decisions on the consumer's behalf. No compatibility score, no matter how high, constitutes an endorsement. No compatibility score, no matter how low, constitutes a disqualification.

### 4.4 Human Decision Authority

All consequential decisions — including which agent to hire — remain with the human parties. No algorithmic output may substitute for human judgment. The compatibility framework is a decision-support tool, not a decision-making tool. This principle is non-negotiable and carries forward from Phases A, B, and C.

### 4.5 Prohibition on Hidden Ranking

The scoring framework must not introduce any mechanism — through weighting, normalization, composite scoring, or display ordering — that functions as a hidden ranking of agents. If scores are computed, they may be displayed to the consumer alongside all other bid information. They may not be used to automatically sort, filter, or re-order the agent list without the consumer's explicit control over that sorting.

---

## 5. Scoring Architecture Overview

### 5.1 Four Conceptual Layers

The compatibility scoring architecture consists of four conceptual layers that operate in sequence. No implementation detail for any layer is provided in this document.

**Layer 1 — Consumer Normalized Traits**

The consumer's raw compatibility preferences, collected via the EAV `saveMeta`/`loadMeta` pattern and stored in the role-specific `*_agent_auction_metas` table under the `compatibility_preferences` JSON blob, are translated into normalized trait values at the 12-trait level. This translation applies the normalization rules established in Phase B — resolving the four naming inconsistencies and routing each raw sub-key to its correct normalized trait.

The output of Layer 1 is a per-listing set of normalized consumer trait values, one per trait that the consumer answered. Unanswered traits produce a missing-value marker rather than a default assumption (see Section 9).

**Layer 2 — Agent Normalized Responses**

The agent's compatibility responses, collected via the Phase C-designed response structure and stored under `compatibility_preferences.agent_response.*` in the bid-specific meta table, are read as normalized trait values. Because agent responses are designed at the normalized trait level from Phase C's architecture, no secondary normalization translation is required — agent responses are already trait-native.

The output of Layer 2 is a per-bid set of normalized agent trait values, one per trait that the agent responded to. Unanswered traits produce a missing-value marker rather than a default assumption.

**Layer 3 — Comparison Layer**

For each trait where both a consumer value and an agent value exist, the comparison layer evaluates the degree of alignment between the two values and produces a per-trait alignment result. The alignment result is one of a small set of conceptual outcomes: full alignment, partial alignment, adjacent compatibility, neutral compatibility, or incompatible alignment (see Section 8 for definitions).

The output of Layer 3 is a per-trait comparison result set. This result set is the atomic unit of the scoring system — every subsequent output (the composite signal, the AI explanation, the consumer-facing display) is derived from these per-trait results.

Traits designated as informational-only (see Section 7 and Section 10) do not produce comparison results in Layer 3. Their values are preserved in the data record for AI explanation use in Phase E but are not fed into Layer 3 processing.

**Layer 4 — Explainability Layer**

The explainability layer takes the per-trait comparison results from Layer 3 and produces the outputs that can be surfaced to consumers and auditors:

- A per-trait alignment summary: for each scored trait, what the consumer stated, what the agent responded, and whether the comparison found alignment, partial alignment, or misalignment.
- A composite advisory signal: a high-level summary of the overall trait-level alignment, expressed in a form that is advisory and non-ranking (a qualitative label, a percentage, or a structured summary — to be determined in Phase F design).
- An audit record: a structured log of all inputs and outputs that is inspectable by administrators without running the comparison again.

The Phase E AI explanation layer will consume the Layer 4 outputs and produce the plain-language compatibility explanation shown to consumers.

### 5.2 Structured Comparison Model

The comparison model operates trait-by-trait. Each trait comparison is independent. There is no cross-trait interaction logic — a high alignment on `negotiation_style` does not modify the comparison result for `communication_frequency`. Each trait contributes its comparison result to the overall signal on its own merits.

This structure is intentional. It prevents hidden interactions between traits from producing unexplainable composite effects. It ensures that every component of the overall signal can be individually explained and audited. It makes the framework extensible: a future change to how one trait is compared does not require redesigning the comparison logic for any other trait.

### 5.3 Integration Point: `listing_compatibility_scores`

The existing `listing_compatibility_scores` table uses an append-only versioned architecture (`version`, `scoring_framework_version`, `archived_at`, `computed_at`) that is well-suited to accommodate a `representation_compatibility_score` column. The table currently contains `physical_match_score`, `financial_match_score`, `location_match_score`, and `terms_match_score`.

A future implementation phase will need to:
- Add a `representation_compatibility_score` column to the table.
- Increment the `scoring_framework_version` identifier to distinguish scores computed with this framework from scores computed without it.
- Design the schema for storing per-trait comparison results alongside the composite score.

None of these schema changes occur in Phase D. This section identifies the integration point for Phase G (implementation architecture) without specifying the implementation.

### 5.4 Role-Neutral by Default

The scoring framework applies the same comparison logic for all four consumer roles for all role-neutral traits. The role matters only in two specific contexts:

1. **`representation_priorities`**: Because the option content for this trait differs substantially across roles (e.g., Seller priorities include marketing and staging; Landlord priorities include tenant screening and lease documentation), the comparison for this trait must operate against the role-appropriate option set. The agent's role-scoped sub-response (from Phase C Section 4.3) is compared against the consumer's role-specific selection.

2. **Role-specific missing traits**: Some traits have no consumer-side data source for certain roles (e.g., `responsiveness_expectation` has no consumer-side field for Buyer or Tenant; `risk_tolerance` has no consumer-side field for Seller or Tenant). See Section 9 for how missing-data situations are handled.

---

## 6. Trait Comparison Methodology

For each of the 12 normalized traits, this section describes conceptually how an alignment comparison would be made between a consumer's normalized trait value and an agent's normalized response value. No formulas, percentages, weights, or scoring algorithms are specified. The descriptions are conceptual only.

### 6.1 `communication_channel`

**What is being compared:** The channels through which the consumer prefers to communicate (e.g., phone call, text/SMS, email, video call, in-person meeting) against the channels the agent identifies as their primary working channels.

**Exact match:** The consumer's preferred channel(s) are fully represented in the agent's stated primary channels. If a consumer lists email and text as their preferred channels and the agent lists both as their primary channels, exact alignment is achieved.

**Partial alignment:** Some but not all of the consumer's preferred channels are represented in the agent's primary channels. If a consumer lists phone and in-person but the agent lists primarily email and text, partial alignment exists — at least one channel overlaps, but the consumer's preference is not fully met.

**Broad compatibility:** The consumer listed multiple acceptable channels and at least one matches a channel the agent works reliably through, but the primary preference may not be the agent's primary channel.

**Incompatibility/misalignment:** The consumer's preferred channel is entirely absent from the agent's stated primary channels. A consumer who exclusively wants in-person meetings selecting an agent who describes their standard as platform messaging only represents a channel mismatch worth surfacing.

**Note:** This trait uses multi-select inputs on the consumer side. The comparison is inherently an overlap assessment — the greater the overlap between consumer preference set and agent channel set, the stronger the alignment.

---

### 6.2 `communication_frequency`

**What is being compared:** How often the consumer expects proactive contact from their agent (e.g., daily, every few days, weekly, major milestones only, on-demand) against the cadence at which the agent describes their standard proactive contact practice.

**Exact match:** The consumer's stated frequency preference matches the agent's stated standard cadence. A consumer who expects weekly updates paired with an agent who describes their standard as weekly check-ins is a direct frequency alignment.

**Partial alignment:** The consumer expects a somewhat more frequent cadence than the agent's standard, but the gap is bridgeable. A consumer who expects contact every few days paired with an agent who describes weekly check-ins as their standard is adjacent but not exact.

**Broad compatibility:** The consumer's preference and the agent's standard are within one step of each other on the frequency spectrum. Some adjustment would be needed but the gap is not severe.

**Incompatibility/misalignment:** The consumer expects frequent daily contact and the agent's standard is major-milestones-only, or vice versa. A consumer who expects proactive daily updates paired with an agent who contacts clients only when there is news represents a meaningful frequency mismatch.

**Note:** The frequency spectrum has a natural ordinal structure (daily → every few days → weekly → major milestones → on-demand). Comparison logic can leverage this ordering without requiring formulas — proximity on the spectrum informs the degree of alignment conceptually.

---

### 6.3 `responsiveness_expectation`

**What is being compared:** The maximum response time the consumer expects when they initiate contact (e.g., within 1 hour, within a few hours, same day, next business day) against the response time commitment the agent states they can realistically meet.

**Exact match:** The agent's stated response time commitment meets or exceeds the consumer's expectation. A consumer who expects same-day responses paired with an agent who commits to same-day responses, or who commits to a few hours, has their expectation fully met.

**Partial alignment:** The agent's stated response time is one step slower than the consumer's expectation. A consumer who expects within a few hours paired with an agent who commits to same-day response is close but not exact.

**Broad compatibility:** The gap between consumer expectation and agent commitment is narrow enough that it would rarely produce friction in practice.

**Incompatibility/misalignment:** The agent's stated response time is meaningfully slower than the consumer's expectation. A consumer who expects responses within 1 hour paired with an agent who commits to next business day turnaround has a clear responsiveness mismatch.

**Role-specific note:** Consumer-side data for this trait exists only for Seller and Landlord roles. Buyer and Tenant have no direct equivalent consumer field. For Buyer and Tenant listings, the agent's response time commitment is informational context for the consumer but does not produce a scored comparison result unless Phase D or a future phase determines otherwise. See Section 9 for missing-data handling.

---

### 6.4 `negotiation_style`

**What is being compared:** The negotiation posture the consumer wants their agent to take (e.g., assertive/aggressive, balanced, collaborative, flexible, conservative) against the negotiation posture the agent describes as their own.

**Exact match:** The consumer's desired negotiation posture and the agent's self-described posture fall within the same semantic cluster. A consumer who wants an assertive negotiator paired with an agent who describes their style as pushing hard for the best terms is a direct negotiation style alignment.

**Partial alignment:** The consumer's desired posture and the agent's self-described posture are adjacent on the assertiveness spectrum. A consumer who wants a balanced negotiator paired with an agent who describes themselves as collaborative is close — both are non-aggressive orientations, though the emphasis differs.

**Broad compatibility:** The consumer's and agent's postures are broadly compatible in direction (both leaning collaborative, or both leaning firm) even if the specific labels are not identical.

**Incompatibility/misalignment:** The consumer's desired posture and the agent's self-described posture are at opposite ends of the spectrum. A consumer who wants aggressive negotiation who selects an agent who describes themselves as collaborative and win-win oriented has a clear posture mismatch. A consumer who wants a conservative secure-the-deal approach paired with an agent who always pushes for maximum advantage is equally misaligned.

**Note:** Negotiation style is one of the highest-signal compatibility traits because misalignment directly affects how the agent represents the consumer's interests in the most consequential moments of the transaction.

---

### 6.5 `guidance_level`

**What is being compared:** How involved the consumer wants to be in managing the transaction (ranging from fully hands-off/delegated to highly hands-on/self-directed) against how much direction and involvement the agent typically provides to clients by default.

**Exact match:** The consumer's preferred involvement level and the agent's default guidance approach are directly compatible. A consumer who wants to be fully hands-off paired with an agent who manages all details with minimal client burden is a direct guidance level alignment. A consumer who wants to be actively involved in every decision paired with an agent who takes a client-led approach is equally aligned.

**Partial alignment:** The consumer's preferred involvement and the agent's default approach are one step apart on the spectrum. A consumer who wants mostly-delegated management paired with an agent whose standard is collaborative (joint decisions at key steps) is close but requires some adjustment.

**Broad compatibility:** The consumer and agent are broadly compatible in their involvement expectations, even if the precise degree differs.

**Incompatibility/misalignment:** The consumer wants full delegation and the agent expects the client to set direction and make all calls, or vice versa. A consumer who wants to be hands-off paired with an agent who requires constant client input is a structural mismatch in how the relationship would actually function day-to-day.

---

### 6.6 `decision_making_style`

**What is being compared:** How the consumer approaches decisions — speed, deliberation style, use of data, and involvement of other parties — against how the agent approaches supporting client decision-making.

**Exact match:** The consumer's decision-making pattern and the agent's stated decision-support approach are directly compatible. A consumer who is data-driven and wants comprehensive information before deciding paired with an agent who provides detailed analysis and paces recommendations accordingly is a direct alignment.

**Partial alignment:** The consumer's decision style and the agent's support approach are compatible in overall orientation but differ in degree. A consumer who is deliberate and cautious paired with an agent who focuses on decisive action when timing matters is adjacent — both prefer thoroughness over impulsiveness, but the agent may need to adapt their pacing.

**Broad compatibility:** The consumer and agent are broadly compatible in decision-pace orientation.

**Incompatibility/misalignment:** The consumer is a slow, deliberate, research-driven decision-maker paired with an agent who characterizes their style as presenting a clear recommendation and helping clients act immediately when timing matters. This is a meaningful pacing mismatch that could generate friction at critical moments.

**Role-specific note:** Consumer-side data for this trait exists for Seller, Buyer, and Tenant but not Landlord. For Landlord listings, the agent's decision-support style is informational context but does not produce a scored comparison result. See Section 9.

---

### 6.7 `transaction_pace`

**What is being compared:** The consumer's timeline sensitivity and urgency (ranging from immediate/strict to very flexible/exploring) against the agent's stated capability for managing transaction timelines — whether they specialize in urgent situations, work effectively with firm deadlines, or adapt to any pace.

**Exact match:** The consumer's timeline context and the agent's stated timeline capability are directly compatible. A consumer with an immediate move-in need (within 2 weeks) paired with an agent who states urgent timelines are their specialty is a direct alignment. A consumer who is very flexible paired with an agent who adapts to the client's pace is equally aligned.

**Partial alignment:** The consumer's timeline context and the agent's stated capability are compatible but not optimally matched. A consumer with a firm deadline of 30 days paired with an agent who works well with firm timelines (but does not specialize in urgent scenarios) is close.

**Broad compatibility:** The consumer's urgency and the agent's stated pace range overlap, even if the agent is not specialized for the consumer's specific timeline context.

**Incompatibility/misalignment:** A consumer with an immediate, non-negotiable move-in need paired with an agent who describes their approach as best suited to flexible timelines and deliberate pacing is a meaningful mismatch — the agent's operational mode may not serve the consumer's timeline needs.

**Role-specific note:** Consumer-side data for this trait exists for Seller, Buyer, and Tenant. Landlord has no direct equivalent consumer field. See Section 9.

---

### 6.8 `risk_tolerance`

**What is being compared:** The consumer's appetite for transactional and financial risk (ranging from very conservative/strict to flexible/high-tolerance) against the agent's stated professional comfort level with transactional risk situations.

**Exact match:** The consumer's risk posture and the agent's professional risk approach are directly compatible. A landlord who wants strict tenant screening paired with an agent who describes their practice as cautious and standards-based is a direct alignment. A buyer who is very aggressive in competitive situations paired with an agent who is equally comfortable advising on aggressive offer strategies is equally aligned.

**Partial alignment:** The consumer's risk appetite and the agent's risk posture are adjacent. A consumer who is moderately risk-tolerant paired with an agent who skews slightly more cautious may find most situations compatible, with occasional differences of approach.

**Broad compatibility:** The consumer's risk appetite and the agent's risk posture are broadly workable — both fall on the same general side of the conservative-to-flexible spectrum, even if the degree of alignment is not precise. A moderately risk-tolerant consumer paired with an agent who describes their approach as flexible and case-by-case is broadly compatible; both accept some degree of non-standard situations, though the consumer may want to discuss how the agent handles specific risk scenarios before committing.

**Incompatibility/misalignment:** The consumer wants a conservative, by-the-book approach and the agent describes their comfort with risk as high and flexible. Or vice versa — a consumer willing to take aggressive positions paired with an agent whose advice consistently favors caution.

**Role-specific note:** Consumer-side data for this trait exists for Buyer and Landlord only. Seller and Tenant have no direct equivalent consumer field. For Seller and Tenant listings, the agent's risk posture is informational context but does not produce a scored comparison result unless a future phase determines otherwise. See also Section 7 for the conditional eligibility designation.

---

### 6.9 `collaboration_style`

**What is being compared:** The overall operating mode and professional persona the consumer wants their agent to embody (proactive, consultative, responsive, process-oriented, full-service, collaborative) against the working style the agent self-describes.

**Exact match:** The consumer's preferred agent persona and the agent's self-described style fall within the same semantic cluster. A consumer who wants a highly proactive agent who anticipates needs paired with an agent who describes their style as proactive and initiative-driven is a direct collaboration style alignment.

**Partial alignment:** The consumer's preferred persona and the agent's self-described style are adjacent. A consumer who prefers a consultative advisor paired with an agent who describes a collaborative joint-decision style is close — both involve the agent actively guiding the consumer, though the consultative model keeps the consumer at more of a distance than the collaborative model.

**Broad compatibility:** The consumer and agent are broadly compatible in operating mode even if the specific labels differ.

**Incompatibility/misalignment:** The consumer wants a proactive agent who acts before being asked and the agent describes themselves as responsive — available when the client initiates, but not proactive without being prompted. This is a meaningful persona mismatch that would likely generate friction around the consumer's expectation of proactive service delivery.

**Note:** Phase C identified `collaboration_style` as likely one of the highest-signal compatibility dimensions because it is the most direct "professional personality compatibility" signal in the system — it captures whether the agent's way of showing up as a professional matches what the consumer needs from their representative.

---

### 6.10 `representation_priorities`

**What is being compared:** The specific task categories and outcome types the consumer most values from their agent (e.g., for Sellers: marketing strategy, negotiation strength; for Buyers: off-market access, contract protection) against the specific strengths and focus areas the agent identifies as their primary capabilities for that role.

**Exact match:** The consumer's top priorities are among the agent's stated primary strengths for the same role. A consumer who prioritizes strong negotiation and fast transaction speed paired with an agent who identifies negotiation strategy and speed of transaction as their primary capability areas is a direct representation priority alignment.

**Partial alignment:** Some of the consumer's top priorities overlap with the agent's stated strengths, but not all. The consumer's highest-ranked priorities may not all be represented in the agent's stated focus areas.

**Broad compatibility:** There is meaningful overlap between the consumer's priority list and the agent's strength list, even if the top-priority items are not perfectly matched.

**Incompatibility/misalignment:** The consumer's priorities are dominated by areas the agent does not identify as strengths. A consumer who primarily values staging expertise and digital marketing paired with an agent who identifies transaction coordination and legal documentation as their primary strengths has a priorities gap — the agent may not deliver what the consumer most cares about.

**Role-scoping note:** This trait uses role-scoped comparison because the option content differs substantially across roles. The comparison for a Seller listing uses the Seller-specific option set; the comparison for a Tenant listing uses the Tenant-specific option set. The comparison logic is conceptually identical across roles; only the option vocabulary changes.

---

### 6.11 `representation_philosophy`

**What is being compared:** The consumer's values-level beliefs about what good representation requires — which agent qualities they consider essential (e.g., honesty and transparency, patience, assertiveness, responsiveness, empathy) — against the agent's statement of their professional values and non-negotiable commitments.

**Exact match:** The qualities the consumer identifies as most important are qualities the agent explicitly claims as core to their professional identity. A consumer who most values honesty and transparency paired with an agent who identifies those qualities as their primary professional commitments is a direct philosophy alignment.

**Partial alignment:** Some of the consumer's most important qualities are represented in the agent's stated values, but not all. The consumer selected honesty and empathy as top priorities and the agent identifies honesty and assertiveness as their primary values — one priority is shared but the other differs, which may or may not matter depending on the consumer's situation.

**Broad compatibility:** The consumer's and agent's values-level priorities share enough common ground to be broadly compatible, even where the specific qualities listed do not fully overlap. A consumer who values honesty, responsiveness, and attention to detail paired with an agent who identifies honesty, thoroughness, and proactivity shares the underlying orientation toward careful, honest representation even if the exact labels differ.

**Incompatibility/misalignment:** The consumer's most important agent qualities are not reflected in the agent's stated philosophy, or the agent's philosophy is dominated by values the consumer did not prioritize.

**Informational-only consideration:** Phase C flagged `representation_philosophy` as a strong candidate for informational-only or conditional status for three reasons: (1) consumer-side structured data exists for only Seller (`qualities_most_important`) and Tenant (`most_important_agent_traits`); (2) it is inherently values-level rather than behavioral, making structured comparison less reliable; (3) narrative components of this trait should feed AI explanation rather than direct scoring. Phase D confirms this designation: the narrative (freeform) component of `representation_philosophy` is informational only. The structured multi-select component (professional values selection) is conditionally score-eligible for Seller and Tenant listings where consumer-side data exists. See Section 7.

---

### 6.12 `property_strategy_fit`

**What is being compared:** The consumer's primary strategic goal for the transaction (e.g., maximum sale price, quick sale, long-term stable tenant, first investment property acquisition) against the transaction types, strategic goals, and client contexts the agent describes as their areas of experience and strength.

**Exact match:** The consumer's primary transaction goal aligns with a transaction type the agent identifies as a primary area of experience. A consumer whose primary goal is finding a long-term stable tenant for a residential property paired with an agent who identifies long-term residential tenancy placement as a strength is a direct strategy fit alignment.

**Partial alignment:** The consumer's primary goal partially overlaps with the agent's stated experience areas. A consumer seeking a quick sale paired with an agent who identifies both quick-sale scenarios and maximum-price strategies as areas of experience has partial overlap — the agent can serve the goal but it may not be their primary specialization.

**Broad compatibility:** The consumer's transaction context falls within the general type of work the agent describes as their practice area, even if not an exact specialization match.

**Incompatibility/misalignment:** The consumer's primary goal and transaction context fall outside the agent's stated experience areas. A consumer seeking a first-time buyer experience with substantial guidance paired with an agent whose stated strengths are investment property analysis and portfolio strategy is a meaningful strategy fit gap — the consumer needs something the agent may not be positioned to provide well.

---

## 7. Trait Eligibility Matrix

This matrix classifies each of the 12 normalized traits as score-eligible, conditionally score-eligible, or informational-only. The classification is based on the Phase C trait-by-trait response design (Section 8 of the Agent Response Design document), the presence or absence of consumer-side data across roles, and the suitability of the trait for structured comparison.

| # | Normalized Trait | Eligibility Classification | Rationale |
|---|---|---|---|
| 1 | `communication_channel` | **Score-eligible** | Consumer-side data present in all four roles (via `preferred_contact_method` for Seller/Buyer; via `communication_style` for Landlord/Tenant). Multi-select overlap comparison is straightforward and explainable. No Fair Housing sensitivity. |
| 2 | `communication_frequency` | **Score-eligible** | Consumer-side data present in all four roles (via different key names, normalized in Phase B). Ordinal spectrum comparison is conceptually clean. No Fair Housing sensitivity. Naming inconsistencies fully resolved at normalized layer. |
| 3 | `responsiveness_expectation` | **Conditionally score-eligible** | Consumer-side data present for Seller and Landlord only. For Buyer and Tenant listings: agent response is collected and displayed as informational context, but no scored comparison result is produced (no consumer-side value to compare against). For Seller and Landlord listings: score-eligible. |
| 4 | `negotiation_style` | **Score-eligible** | Consumer-side data present in all four roles. Semantic cluster comparison is well-defined (Phase B crosswalk, Phase C unified option set). High signal value — direct bearing on how the agent represents the consumer's interests. No Fair Housing sensitivity in option framing. |
| 5 | `guidance_level` | **Score-eligible** | Consumer-side data present in all four roles (via four different raw key names, all normalized to `guidance_level` in Phase B). Spectrum comparison is conceptually clean. Directly affects working relationship structure. No Fair Housing sensitivity. |
| 6 | `decision_making_style` | **Conditionally score-eligible** | Consumer-side data present for Seller, Buyer, and Tenant. Landlord has no direct equivalent consumer field. For Landlord listings: agent response is informational context only. For Seller, Buyer, and Tenant listings: score-eligible. |
| 7 | `transaction_pace` | **Conditionally score-eligible** | Consumer-side data present for Seller, Buyer, and Tenant. Landlord has no direct equivalent consumer field. For Landlord listings: agent response is informational context only. For Seller, Buyer, and Tenant listings: score-eligible. |
| 8 | `risk_tolerance` | **Conditionally score-eligible** | Consumer-side data present for Buyer and Landlord only. Seller and Tenant have no direct equivalent consumer field. For Seller and Tenant listings: agent response is informational context only. For Buyer and Landlord listings: score-eligible, but carries elevated proxy risk (see Section 14.3) — Fair Housing compliance review required before scoring use. |
| 9 | `collaboration_style` | **Score-eligible** | Consumer-side data present in all four roles via `preferred_agent_working_style`. Semantic cluster comparison is well-defined (Phase B crosswalk, Phase C unified option set). High signal value — most direct professional personality compatibility signal. No Fair Housing sensitivity in option framing. |
| 10 | `representation_priorities` | **Score-eligible (role-scoped comparison)** | Consumer-side data present in all four roles. Options are role-specific in content, requiring role-scoped comparison. Agent provides role-specific sub-responses (from Phase C Section 4.3). Overlap comparison between consumer priority list and agent strength list is explainable and meaningful. |
| 11 | `representation_philosophy` | **Conditionally score-eligible (structured component only)** | Consumer-side structured data (multi-select professional values) exists for Seller and Tenant only. For Seller and Tenant listings: the structured multi-select component is conditionally score-eligible. For Buyer and Landlord listings: informational only. The narrative (freeform text) component is informational only for all roles — it informs AI explanation (Phase E) but does not produce scored comparison results. |
| 12 | `property_strategy_fit` | **Score-eligible** | Consumer-side data present in all four roles (via different key names reflecting the role-specific framing of primary transaction goal). Multi-select overlap comparison between consumer goal and agent stated experience areas is explainable. Carries proxy risk for certain option values (see Section 14.3) — proxy risk assessment required before scoring use. |

**Summary:**
- **Score-eligible (all roles):** `communication_channel`, `communication_frequency`, `negotiation_style`, `guidance_level`, `collaboration_style`, `representation_priorities`, `property_strategy_fit` — 7 traits
- **Conditionally score-eligible:** `responsiveness_expectation` (Seller/Landlord only), `decision_making_style` (Seller/Buyer/Tenant only), `transaction_pace` (Seller/Buyer/Tenant only), `risk_tolerance` (Buyer/Landlord only, with Fair Housing review required), `representation_philosophy` structured component (Seller/Tenant only) — 5 traits with role-conditional or component-conditional scoring
- **Informational-only:** Narrative/freeform components of `representation_philosophy` across all roles; conditionally scored traits for roles where consumer-side data is absent

---

## 8. Partial Match Concepts

This section defines the conceptual alignment categories used in Layer 3 (comparison layer) of the scoring architecture. These categories apply across all scored traits. No formulas or numerical thresholds are specified.

### 8.1 Full Alignment

**Definition:** The agent's response and the consumer's preference are directly compatible at the same semantic level. The comparison finds that what the consumer asked for and what the agent described match within the same option or semantic cluster.

**Illustrative examples:**
- Consumer expects weekly updates → Agent states their standard is weekly check-ins.
- Consumer wants a balanced negotiator → Agent describes their style as balanced: strong terms while keeping negotiations productive.
- Consumer prefers email and text → Agent lists email and text as primary communication channels.
- Consumer wants a fully hands-off managed experience → Agent states they manage all details with minimal client burden.

**What this means for the consumer:** The comparison found that this agent's stated approach matches what you said you wanted for this aspect of the working relationship.

---

### 8.2 Partial Alignment

**Definition:** The agent's response and the consumer's preference are related and broadly compatible but not exactly matched. The comparison finds a meaningful overlap or adjacency that suggests the working relationship would function well, though some adjustment may occur.

**Illustrative examples:**
- Consumer expects contact every few days → Agent states their standard is weekly check-ins. The gap is one step on the frequency spectrum; it is notable but not severe.
- Consumer prefers a consultative advisor → Agent describes a collaborative joint-decision style. Both involve active agent guidance; the emphasis differs but both serve informed decision-making.
- Consumer wants urgent timeline capability → Agent states they work well with firm timelines but does not specialize in immediate-need scenarios. Compatible in direction, not fully specialized for the consumer's context.
- Consumer wants an assertive negotiator → Agent describes a balanced approach — strong terms while remaining professional. Adjacent on the assertiveness spectrum.

**What this means for the consumer:** This agent's approach is close to what you described wanting but is not an exact match for this aspect. You may want to discuss this directly with the agent before making your decision.

---

### 8.3 Adjacent Compatibility

**Definition:** The agent's response and the consumer's preference are not identical and not adjacent on the primary dimension, but share enough directional overlap that they are broadly workable with conscious communication. Adjacent compatibility is a softer form of partial alignment, typically involving traits where the consumer expressed a preference that the agent does not optimally serve but does not directly contradict.

**Illustrative examples:**
- Consumer expects daily contact → Agent's standard is weekly check-ins. The gap is larger than partial alignment, but both parties are expecting proactive communication from the agent (as opposed to on-demand only).
- Consumer wants a process-oriented, detail-focused agent → Agent describes a full-service concierge style. Both involve high levels of agent engagement; the consumer's preference emphasizes thoroughness and the agent's emphasis is end-to-end management.
- Consumer prefers conservative negotiation → Agent describes a flexible, adaptive approach. Adjacent in that both avoid pure aggression, but the consumer's preference for caution is not the agent's primary orientation.

**What this means for the consumer:** This agent's approach is workable for this aspect but differs meaningfully from what you described preferring. Open communication about expectations at the start of the relationship would help.

---

### 8.4 Neutral Compatibility

**Definition:** The comparison finds no meaningful alignment or misalignment because one side's input is too general, the consumer opted for a "flexible / no preference" response, or the trait comparison produces no signal in either direction. Neutral compatibility is distinct from missing data — both values are present, but the consumer's response is effectively a non-preference or the agent's response signals full adaptability.

**Illustrative examples:**
- Consumer selected "Flexible / No Preference" for a scheduling or format-related preference.
- Agent responded "I adapt to the client's pace — whether urgent or unhurried" to the transaction pace question. The agent's response is fully adaptive, producing no misalignment signal regardless of the consumer's timeline.
- Consumer listed all available communication channels as acceptable; any agent channel preference produces neutral compatibility.

**What this means for the consumer:** No meaningful compatibility signal exists for this aspect. Either you expressed no strong preference, or the agent is fully adaptable, so there is nothing to evaluate here.

---

### 8.5 Incompatible Alignment

**Definition:** The agent's response and the consumer's preference are at meaningfully different positions and the gap is large enough to be worth surfacing explicitly as a potential working relationship challenge.

**Illustrative examples:**
- Consumer expects daily proactive updates → Agent's standard is contact only when there is significant news. The consumer's expectation for frequent contact is structurally at odds with the agent's practice.
- Consumer wants an aggressive negotiator → Agent describes their style as collaborative and win-win focused. The consumer wants maximum-advantage advocacy; the agent leads with mutual benefit. This is a negotiation philosophy mismatch.
- Consumer wants to be fully hands-off → Agent expects the client to set direction and make all calls. The consumer's preference for delegation is structurally incompatible with the agent's expected client engagement level.
- Consumer's primary goal is finding off-market investment properties → Agent's stated strengths are staging, listing presentation, and open-house strategy. The consumer's primary need (investment acquisition, off-market access) is not what the agent identifies as their strength area.

**What this means for the consumer:** This aspect of the working relationship shows a meaningful difference between what you said you wanted and what this agent described. This does not disqualify the agent, but it is worth discussing directly before making your decision.

---

## 9. Missing Data Handling

### 9.1 Unanswered Consumer Traits

Some compatibility traits are optional for consumers. A consumer who did not answer a given compatibility question produces a missing-value marker for that trait at the normalized layer.

**Handling principle:** A missing consumer response must not be treated as a default preference and must not be compared against the agent's response as if a preference existed. The trait comparison is skipped for that trait. The agent's response for that trait, if provided, is preserved as informational context that may be displayed to the consumer but does not contribute to the scored compatibility signal.

**Rationale:** A consumer who did not answer a question did not express a preference. Inferring a default preference and comparing it against the agent's response would produce a comparison the consumer never made. Missing consumer data should not penalize the agent or artificially inflate compatibility scores.

### 9.2 Unanswered Agent Traits

Agents may leave some compatibility response fields unanswered. An agent who did not respond to a given trait produces a missing-value marker for that trait.

**Handling principle:** A missing agent response must not be treated as a default capability and must not be compared against the consumer's preference as if a response existed. The trait comparison is skipped. The consumer's preference for that trait is noted as unanswered by this agent — which is itself useful information for the consumer.

**Rationale:** An agent who did not answer a question did not express a stance. Inferring a default position and comparing it against the consumer's preference would produce a comparison the agent never provided. Missing agent data should not penalize the consumer by artificially deflating compatibility.

**Display consideration:** A future consumer-facing display (Phase F) may choose to distinguish between "agent's response is aligned with your preference," "agent's response differs from your preference," and "agent did not respond to this question." The last state is informative: it tells the consumer that this agent has not described their approach for this aspect, which is itself a factor the consumer may weigh.

### 9.3 Role-Specific Missing Traits

As documented in the Trait Eligibility Matrix (Section 7), some traits have no consumer-side data source for certain roles. This produces a systematic missing-value condition for those role-trait combinations — not because the consumer failed to answer, but because the question does not exist in their listing flow.

**Handling principle:** For traits where consumer-side data does not exist for a given role (e.g., `responsiveness_expectation` for Buyer listings; `risk_tolerance` for Seller listings), the trait comparison is skipped for listings of that role. The agent's response for the missing trait is preserved as informational context but does not contribute to the scored signal.

**Rationale:** A consumer cannot be compared against an agent on a dimension they were never asked about. Systematically missing consumer data should not create a scoring penalty or bonus for any agent.

### 9.4 Optional Traits and Sparse Profiles

Some traits are collected as optional questions on the consumer side. Sparse profiles (consumers who answered few optional questions) produce fewer scored trait comparisons. A consumer who answered only required fields will have a compatibility signal based only on those traits where consumer data exists.

**Handling principle:** The scoring framework should produce a meaningful signal based on whatever data exists, without artificially inflating the signal by making assumptions about unanswered optional fields. A sparse profile produces a narrower signal, not a worse or better one.

### 9.5 Future Incomplete Profiles

As the agent-side response system matures, some agents may have incomplete compatibility response profiles (missing some trait responses). Incomplete agent profiles should be handled using the same principle as unanswered agent traits (Section 9.2): skip the comparison for missing traits, note the absence as informational context for the consumer, and do not inflate or deflate the signal by assigning defaults.

---

## 10. Informational-Only Trait Rules

### 10.1 Definition of Informational-Only

An informational-only field is one that provides useful context about an agent's or consumer's situation, preferences, or approach but is not appropriate for structured comparison and scored output. Informational-only fields are preserved in the data record, may be displayed to the consumer as context, and may inform the AI explanation layer (Phase E) — but they do not produce Layer 3 comparison results and do not contribute to the compatibility signal.

### 10.2 Narrative and Freeform Fields

All freeform narrative text fields are informational only. They cannot be reliably structured for comparison, and their content is too varied and situation-specific to produce a generalizable compatibility signal. These fields include:

- Consumer `target_sale_timeline` (Seller free text) — contextual note about timeline, not a structured trait value.
- Consumer `additional_decision_makers` (Seller free text) — operational context about who else is involved; no trait mapping.
- Consumer `availability_windows` (Buyer free text) — scheduling logistics; not a representation compatibility signal.
- Consumer `deal_breakers` (Buyer free text) — unstructured and situation-specific; too variable for automatic comparison.
- Consumer `concerns_or_barriers` (Tenant textarea) — freeform situation notes; no structured trait mapping.
- All `additional_compatibility_notes` / `additional_representation_notes` textareas across all four roles — rich context for agent reading and AI explanation, not for scoring.
- Consumer `past_agent_experience` context and `what_did_not_work_before` — experience framing useful for consumer context; not a direct scoring input.
- Agent narrative bio or professional philosophy statement — valuable for Phase E AI explanation and consumer reading; should not be auto-scored.

### 10.3 Scheduling and Operational Preferences

Fields that describe scheduling logistics or operational preferences rather than representation style are informational only. They do not measure professional compatibility between agent and consumer; they describe operational constraints or preferences that may change over time. These include:

- Consumer `showing_availability` (Seller) — which days and times the seller permits showings. This is an operational constraint, not a representation compatibility signal.
- Consumer `open_house_preference` (Seller) — preference for or against open houses as a sales tactic. This is a tactical marketing preference, not a representation style compatibility dimension.
- Consumer `preferred_contact_method` (Tenant, time-of-day) — stores Morning / Afternoon / Evening / Anytime options. This is a scheduling convenience preference, not a representation compatibility signal. Explicitly excluded from trait scoring in Phase B Section 7 and confirmed informational-only here.

### 10.4 Cross-Role Coverage Gaps

Fields where consumer-side data exists for only one role, with no structured equivalent in the other three, are generally informational only for the roles where data is absent. As documented in Section 7, the conditionally score-eligible traits handle this at the role level — the trait is scored where consumer data exists and treated as informational context where it does not.

### 10.5 The `representation_philosophy` Narrative Component

The freeform narrative component of `representation_philosophy` — including any agent professional statement field and the consumer's past-experience narrative — is informational only across all roles. It provides rich context for:

- Consumer reading and evaluation of the agent's professional identity.
- Phase E AI explanation, which can reference the agent's stated values when explaining why a compatibility signal was or was not strong.

It must not be processed for structured comparison. The diversity of expression in freeform professional statements makes automated comparison unreliable and subject to inputs that could correlate with non-compatibility factors.

### 10.6 AI Explanation and Informational Context

Informational context fields may assist the Phase E AI explanation layer without contributing to scored dimensions. For example:

- A consumer's narrative note that they had a negative experience with an agent who was unresponsive could inform an AI explanation that highlights the current agent's same-day response commitment — without that narrative becoming a scoring input.
- An agent's professional statement that they specialize in working with first-time buyers could inform an AI explanation that references the consumer's stated goal of first-time buyer guidance — without the narrative being scored.

The distinction between "scores this" and "informs the explanation of this" is fundamental to the Phase D framework and must be preserved through Phase E design.

---

## 11. Explainability Requirements

### 11.1 Core Explainability Principle

Every compatibility score or signal component that is surfaced to consumers must be traceable to specific, visible trait comparisons. No output of the compatibility system may exist without a corresponding explanation of what produced it. Black-box outputs, undisclosed weighting, and opaque composite scores are prohibited.

### 11.2 Per-Trait Explainability Standard

For each scored trait, the explainability standard requires that the following information be derivable and presentable:

1. **What the consumer stated:** The consumer's normalized preference for this trait, expressed in the same language the consumer used when answering the question.
2. **What the agent responded:** The agent's normalized response for this trait, expressed in the same language the agent used when completing their response.
3. **What the comparison found:** The alignment category (full alignment, partial alignment, adjacent compatibility, neutral compatibility, incompatible alignment) and a plain-language statement of what that means for this specific trait pair.

These three elements must be available for every trait that contributes to the scored signal, and they must be producible without running the comparison again — they must be stored as part of the comparison record.

### 11.3 No Black-Box Outputs

No compatibility output may be produced that cannot be explained in terms of specific, documented, non-protected trait comparisons. If an explanation cannot be given, the score cannot be produced. This principle extends to any future AI involvement in scoring — if an AI component is ever introduced into the scoring layer (not the explanation layer), its outputs must still be explainable at the per-trait level.

The AI explanation layer (Phase E) is part of the explanation system, not the scoring system. Phase E takes per-trait comparison results as inputs and produces plain-language explanations of them. Phase E does not produce scores; it explains scores that were already produced by the comparison layer.

### 11.4 No Undisclosed Weighting

If the future implementation of the scoring framework applies differential weighting to traits (e.g., `negotiation_style` counting for more of the overall signal than `communication_frequency`), that weighting must be:

- Documented in the `scoring_framework_version` identifier.
- Disclosed to the consumer in plain language as part of the compatibility explanation.
- Available for administrator audit.

Hidden weighting — where one trait silently contributes more than another without disclosure — is prohibited under the governance rules (Section 13) and under this explainability standard.

### 11.5 Consumer-Facing Explainability

Consumers must be able to understand why a compatibility signal exists. A consumer should not see a compatibility rating without being able to access an explanation of which traits aligned and which did not. The consumer-facing explanation must:

- Be in plain, non-technical language.
- Reference the consumer's own stated preferences (what they asked for).
- Reference the agent's stated responses (what the agent described).
- Avoid jargon, algorithm references, or statistical framing.
- Clearly identify where the comparison found alignment and where it found differences.

**Example of a compliant consumer-facing explanation:**

> "You indicated you prefer weekly updates. This agent typically reaches out to clients every few days — more frequently than your stated preference. This is likely to work well for you. On negotiation style, you indicated you want an assertive negotiator who pushes hard for the best terms. This agent describes their approach as balanced — pursuing strong terms while keeping negotiations productive and professional. These are adjacent styles, but if aggressive advocacy is important to you, you may want to discuss this directly."

**Example of a non-compliant explanation:**

> "Compatibility score: 74. Weighted trait composite based on 9 active dimensions."

The second example discloses nothing about what drove the score and provides no basis for the consumer to evaluate or challenge it.

### 11.6 Agent-Facing Explainability

Agents must be able to understand what their compatibility responses imply about their professional working style. The agent response system must clearly communicate:

- What each question is measuring and why.
- What their selection implies — not in terms of scoring, but in terms of the professional style they are representing to consumers.

If compatibility scores are ever surfaced to agents (a governance decision deferred to a future phase), agents must be able to understand the methodology. They must not receive a compatibility rating they cannot understand or contest.

### 11.7 Examples of Compliant Explanations by Trait

The following examples illustrate compliant plain-language explanations for each major trait type. These are planning examples only — not final UI copy.

| Trait | Compliant Explanation Example |
|---|---|
| `communication_frequency` | "You prefer weekly updates. This agent's standard is to check in every few days — more frequent than your preference, which is generally a positive signal." |
| `communication_channel` | "You prefer email and phone calls. This agent lists email and text/SMS as their primary channels. Email aligns fully; the phone/text difference is minor." |
| `responsiveness_expectation` | "You expect responses within a few hours. This agent commits to same-day responses. This is close but slightly slower than your expectation." |
| `negotiation_style` | "You want an agent who pushes hard for maximum advantage. This agent describes their approach as balanced — strong terms, professional conduct. These are adjacent styles; if aggressive advocacy is your priority, discuss this directly." |
| `guidance_level` | "You indicated you want to be fully hands-off. This agent states they manage all details with minimal client burden. This is a strong alignment on involvement level." |
| `collaboration_style` | "You prefer a proactive agent who anticipates your needs. This agent describes themselves as consultative — they explain options and guide you through decisions. These are close but distinct: proactive means acting without being asked, consultative means explaining and advising when decisions arise." |
| `representation_priorities` | "You ranked strong negotiation and fast transaction speed as your top priorities. This agent identifies negotiation strategy and speed of transaction as primary strengths. Strong alignment on your top priorities." |
| `property_strategy_fit` | "Your primary goal is a quick sale. This agent identifies both quick-sale scenarios and maximum-price strategies as experience areas. Your primary goal is well-covered by this agent's stated strengths." |

---

## 12. Compatibility Interpretation Guidelines

### 12.1 What Compatibility Is

Compatibility, as produced by this framework, is:

- **An alignment indicator:** A signal showing how well the agent's stated professional working style matches the consumer's stated preferences for how they want to work with an agent.
- **An advisory insight:** A data-informed perspective that the consumer is free to weigh alongside other bid factors.
- **A representation fit signal:** One measure of whether this particular agent-consumer working relationship is likely to operate smoothly based on their stated preferences and approaches.

### 12.2 What Compatibility Is Not

Compatibility is not:

- **A recommendation engine:** The system does not recommend agents. It presents a compatibility signal. The consumer decides.
- **An endorsement system:** A high compatibility signal is not a platform endorsement of the agent. A low compatibility signal is not a platform disqualification.
- **A definitive ranking:** Compatibility is one signal among many. A consumer might hire an agent with lower compatibility on this dimension but superior skills, better compensation terms, stronger local expertise, or a better fit on dimensions outside this framework.
- **A substitute for consumer judgment:** The consumer's own evaluation — their conversation with the agent, their assessment of the agent's credentials, their reaction to the agent's proposal — carries independent authority that no algorithmic signal can replace.

### 12.3 Compatibility Among Multiple Factors

Compatibility is one signal among a set of factors consumers use to evaluate agent bids. Other independent factors include:

- **Services and service scope:** What specific tasks the agent will perform. Already incorporated in existing match score helpers.
- **Compensation and fee structure:** What the consumer pays and on what terms. Already reflected in `financial_match_score` and `terms_match_score`.
- **Experience and credentials:** The agent's professional history, certifications, local expertise, and track record.
- **Property-specific alignment:** The agent's knowledge of the property's market, neighborhood, and comparable properties. Reflected in existing `physical_match_score` and `location_match_score`.
- **Terms alignment:** How the agent's proposed agreement terms compare to the consumer's requirements. Reflected in `terms_match_score`.
- **Consumer's own judgment:** The consumer's assessment of the agent's communication quality, responsiveness in the bidding process, and overall fit as a professional they want to work with.

Compatibility is one advisory input into a human decision — not a decision itself.

### 12.4 Compatibility Level Descriptions

A future consumer-facing display (Phase F) will determine how compatibility levels are labelled and presented. This document provides planning guidance on what the labels should and should not imply.

**Compliant label examples:**
- "Strong alignment across most areas" (descriptive, non-endorsing)
- "Good alignment with some differences worth discussing" (actionable, advisory)
- "Several differences — worth reviewing before deciding" (informative, non-disqualifying)
- "Limited compatibility data available" (transparent about data gaps)

**Non-compliant label examples:**
- "Best Match" (ranking implication)
- "Recommended" (endorsement implication)
- "Not Compatible" (disqualification implication)
- "Score: 87/100" (precision without explainability)

---

## 13. Governance Rules

The following governance rules apply to the Professional Representation Compatibility system at every phase of design, implementation, and operation. They are carried forward in full from Phases A, B, and C, with Phase D additions where noted.

**Rule 1 — AI Advisory Only.** The compatibility signal is an advisory output. It informs the consumer's evaluation of agent bids; it does not make decisions on the consumer's behalf. No compatibility score, ranking, or signal produced by this system is autonomous or self-executing.

**Rule 2 — No Hidden Weighting.** Every factor that contributes to a compatibility signal must be disclosed to the consumer. No trait may be silently weighted more than another without the weighting methodology being visible. If weights are used in a future implementation phase, they must be documented in the `scoring_framework_version` field and disclosed to the consumer in plain language.

**Rule 3 — Explainability Required.** Every scored dimension must be explainable in plain language without reference to opaque algorithms (see Section 11 for the full explainability standard). A consumer must be able to understand, for each trait, what their preference was, what the agent's response was, and what the comparison found. An agent must be able to understand what each of their responses implies about their working style.

**Rule 4 — No Autonomous Ranking.** The compatibility framework must not produce or imply an agent ranking. Compatibility signals may be displayed to consumers alongside other bid information, but the system must not automatically sort, filter, or re-order the agent list based on compatibility scores without the consumer's explicit control over that action.

**Rule 5 — No Protected-Class Traits.** No trait may use, correlate with, or proxy for any protected-class characteristic under the Fair Housing Act, the Equal Credit Opportunity Act, or applicable state or local law. This applies to every layer of the framework: data collection, trait normalization, comparison logic, signal composition, and display.

**Rule 6 — No Fair Housing-Sensitive Traits.** Beyond legally protected classes, any comparison that could produce discriminatory patterns in agent selection — even without intent — is prohibited. A comparison is Fair Housing-sensitive if its use would predictably disadvantage agents who serve protected-class consumers or favor agents who do not.

**Rule 7 — No Demographic Profiling.** The system must not create or maintain profiles of agents or consumers based on demographic characteristics. Strategic context signals (e.g., type of transaction goal) are not demographic filters and must not be used as such.

**Rule 8 — Professional Representation Compatibility Scope Only.** The 12 normalized traits defined in Phase B constitute the complete and bounded scope of what this system measures. The framework measures how an agent and consumer prefer to work together as professionals. It does not measure property characteristics, financial qualifications, neighborhood preferences, agent production volume, brokerage affiliation, lifestyle similarity, or any factor outside the 12 defined traits.

**Rule 9 — Consumer Data Confidentiality.** A consumer's compatibility preferences are private inputs. They may be used to compute a compatibility signal visible to the consumer, but the raw preference data must not be displayed to agents in a way that reveals information the consumer did not intend to disclose. The comparison layer operates on the platform side; raw consumer input is not a transparency window for agents.

**Rule 10 — Iterative and Versioned.** The scoring framework must support versioning consistent with the existing `scoring_framework_version` architecture. Any change to trait definitions, option sets, comparison methodology, or weighting logic must increment the framework version so that signals computed under different frameworks are not directly compared.

**Rule 11 — Human Decision Authority.** The compatibility system is a decision-support tool. All consequential decisions — including which agent to hire — remain with the human parties. No algorithmic output may substitute for human judgment.

**Rule 12 — Auditability Required.** The consumer's preference, the agent's response, and the comparison result for every trait must all be inspectable by platform administrators for compliance review. No comparison input may be hidden from audit. No comparison result may be produced without a stored record of the inputs that produced it.

**Rule 13 — Fair Housing Review Required.** Before any phase of this system is released to production, a Fair Housing compliance review must be completed by a qualified reviewer. The governance rules stated here are a precondition for that review, not a substitute for it.

**Rule 14 — No Formulas in Planning Documents (Phase D addition).** Phase D is a conceptual design document. No scoring formulas, numerical thresholds, percentage calculations, or weighting values are specified. Phase D defines the framework conceptually. Implementation-level specifications are deferred to Phase G (implementation architecture).

---

## 14. Fair Housing Safeguards

This section is a dedicated Fair Housing compliance framework for the BidYourAgent Professional Representation Compatibility scoring system. It carries forward all safeguards from Phase C Section 13 and adds Phase D-specific scoring context where relevant.

### 14.1 Prohibited Traits — Absolute Prohibitions

The following characteristics must never appear in any compatibility field, option label, option value, comparison dimension, scoring component, or AI-generated explanation at any phase:

- Race or color
- National origin or ethnicity
- Religion or religious practices
- Sex, gender identity, or sexual orientation
- Familial status (presence of children in the household, pregnancy, custodial status)
- Disability or medical condition (physical or mental)
- Age (except as legally permitted in senior housing contexts)
- Marital status (where protected by state law)
- Source of income (where protected by state law)
- Military or veteran status (where protected by state law)

These prohibitions apply at every layer of the framework: consumer input collection, trait normalization, comparison logic, signal composition, and consumer-facing display.

### 14.2 Prohibited Inferences

The scoring framework must not draw, store, or display any inference about a consumer or agent based on characteristics that correlate with protected classes, even if the inference is derived from seemingly neutral inputs. Prohibited inferences include:

- Any inference about a person's religion from their name, language, neighborhood, or stated cultural practices.
- Any inference about a person's national origin from their language preference, neighborhood location, or transaction context.
- Any inference about familial status from timeline urgency (e.g., assuming a tenant with an immediate move-in need has a family circumstance).
- Any inference about disability or accessibility needs beyond what the consumer explicitly discloses for their own property search.
- Any inference about source of income from budget flexibility or risk tolerance inputs.

### 14.3 Proxy-Risk Awareness

A proxy field is a field that does not ask about a protected characteristic directly but whose options or scoring behavior correlate with a protected characteristic in practice. The following traits carry elevated proxy risk and require explicit proxy risk evaluation before any scoring use:

**`risk_tolerance`** — The "strict screening" option for Landlord listings (Low – Strict Screening Only) could, if scored in a way that disadvantages agents who serve tenants with non-standard financial profiles, function as a proxy for screening against protected classes. Tenants with non-standard financial backgrounds are disproportionately members of protected classes in many markets. The scoring comparison for this trait must be framed as "does the agent's comfort level with risk align with the landlord's stated preference?" — not as "does the agent prefer strict screening?" Strict scoring of this trait requires Fair Housing review.

**`property_strategy_fit`** — Certain transaction type options (e.g., "investment property acquisition," "fix & flip") may correlate with transaction patterns associated with specific demographic groups in some markets. The comparison for this trait must evaluate agent-stated specialization alignment with consumer stated goals — not consumer or agent demographic profiles.

**`representation_priorities`** — Role-specific priority options may correlate with transaction types associated with specific demographic groups in some markets. Priority comparison must evaluate capability alignment — not demographic sorting.

No proxy risk analysis sufficient for production release is performed in this document. This analysis is required as part of the Fair Housing compliance review before any element of the scoring system enters production.

### 14.4 Demographic Clustering Prohibition

The scoring framework must not be used, directly or indirectly, to cluster consumers or agents by demographic group. Specific prohibitions that apply to scoring implementation:

- The system must not produce compatibility signals that are systematically higher for agent-consumer pairs that share demographic characteristics.
- The system must not produce compatibility signals that penalize agents for serving geographically or demographically diverse client bases.
- No "community fit," "cultural alignment," or "lifestyle compatibility" dimension may be added to the scoring framework under any label at any future phase.
- The system must not recommend agents to consumers on the basis of any demographic similarity between the agent and the consumer.

### 14.5 Neighborhood Demographic Scoring Prohibition

The scoring framework must not incorporate neighborhood demographic data — including census demographic composition, neighborhood racial composition, language prevalence, income distribution by race or ethnicity, or any proxy for the same — into any comparison, scoring component, or AI explanation. This prohibition covers both direct use and indirect use through third-party data sources.

This prohibition applies explicitly to the `property_strategy_fit` trait: a consumer's primary transaction goal and property location cannot be combined to infer or weight neighborhood demographic context in any scoring output.

### 14.6 Explainability as a Fair Housing Requirement

Explainability is not only a user experience standard — it is a Fair Housing compliance safeguard. An opaque scoring algorithm that produces systematically disparate outcomes cannot be detected, audited, or corrected. Every comparison result must be explainable in terms of specific, documented, non-protected trait alignments. If an explanation cannot be given for a comparison, that comparison must not be used in the scoring output.

This principle creates a structural barrier against discriminatory proxy use: if a comparison input cannot be explained without reference to protected characteristics, the comparison design is non-compliant and must be revised before use.

### 14.7 Compliance Review Requirement

A Fair Housing compliance review by a qualified reviewer is required before any element of the compatibility scoring system — comparisons, scoring, signal composition, display, AI explanation — is made available in a production environment. This review must:

- Assess all option labels and comparison logic for discriminatory potential.
- Evaluate all proposed scoring dimensions and comparison methodologies for disparate impact risk.
- Review all proxy-risk-flagged traits for compliance before scoring use.
- Certify that no prohibited trait, prohibited inference, or unchecked proxy field has been incorporated.
- Review all AI explanation prompt templates (Phase E deliverable) for Fair Housing-compliant language before production use.

This planning document does not constitute a Fair Housing review and does not authorize production use of any system element described herein.

---

## 15. Versioning and Auditability

### 15.1 Scoring Framework Version

Every compatibility scoring output must be tagged with a `scoring_framework_version` identifier that specifies which version of the framework produced it. The `listing_compatibility_scores` table already supports a `scoring_framework_version` column in its append-only architecture.

The first implementation of the compatibility scoring framework will require a new version identifier that distinguishes scores produced with the `representation_compatibility_score` dimension from all prior scores produced without it.

**Conceptual versioning rules:**
- A new `scoring_framework_version` must be assigned whenever the trait definitions, option sets, comparison methodology, or weighting logic change in any way that would produce different results for the same input data.
- A version change does not invalidate prior scores — the prior scores remain in the append-only table under the prior version identifier.
- Direct comparison between scores produced under different framework versions must be avoided without explicit acknowledgment that the methodologies differ.

### 15.2 Append-Only Scoring Evolution

The existing append-only architecture of `listing_compatibility_scores` ensures that historical scoring records are preserved. New scores do not overwrite old scores — they are appended with new `computed_at` timestamps and version identifiers. Old scores are archived (marked via `archived_at`) rather than deleted.

This architecture enables: (1) tracing how a listing's compatibility profile evolved over time as the framework was updated; (2) auditing what methodology was in effect when a particular score was produced; (3) detecting anomalies by comparing scores across versions for the same input data.

### 15.3 Historical Score Traceability

Every compatibility score ever produced for a listing-bid pair must be traceable back to:
- The `scoring_framework_version` that produced it.
- The normalized consumer trait values that were inputs.
- The normalized agent response values that were inputs.
- The per-trait comparison results that were produced.
- The timestamp at which the score was computed.

This traceability record is the audit record. It must be stored in a durable, administrator-accessible form. It is not a consumer-facing display.

### 15.4 Audit Visibility

Platform administrators must be able to inspect, for any listing-bid pair and any scoring event:
- What consumer traits were active (what the consumer answered).
- What agent responses were active (what the agent responded).
- What per-trait comparison results were produced.
- What composite signal was computed.
- What framework version was in effect.

This audit capability is a compliance requirement, not an optional feature. It enables the platform to respond to compliance inquiries, identify scoring anomalies, and demonstrate governance compliance.

### 15.5 Admin Reviewability

Administrators reviewing a compatibility scoring output must be able to reproduce the conceptual reasoning that produced it — even if they cannot re-run the exact computation. The per-trait comparison result record (from Layer 4) is the primary audit artifact. It captures: what was compared, what was found, and what the output was — in terms that a non-technical reviewer can understand.

### 15.6 Explainable Trait History

If trait definitions, option sets, or comparison methodologies change between framework versions, administrators must be able to understand what changed and why. Version-level change documentation (maintained alongside the `scoring_framework_version` identifier) must describe:
- Which traits were modified.
- What the prior and new comparison approaches were.
- Why the change was made.
- What Fair Housing or governance review, if any, covered the change.

---

## 16. Future Implementation Dependencies

Phase D is complete when this document is finalized. The following phases remain to be designed and implemented. No implementation detail for any of these phases is specified in Phase D.

### Phase E — AI Explanation Layer

Phase E will design the AI-generated plain-language compatibility explanation shown to consumers. Phase E will define:
- The prompt structure and inputs (consuming the Layer 4 per-trait comparison result record from Phase D's architecture).
- Fair Housing compliance guardrails for AI-generated text.
- Tone and framing guidelines that ensure the explanation is advisory, non-ranking, and non-endorsing.
- How the AI explanation distinguishes between scored trait comparisons and informational-only context fields.
- How informational context fields (narrative notes, freeform statements) may inform the explanation without becoming scoring inputs.
- The technical mechanism for surfacing the explanation in the consumer UI.

Phase E requires: the per-trait comparison result structure from Phase D, the informational context field set from Phase C, and governance and Fair Housing guardrails from both Phase C and Phase D.

### Phase F — Consumer Comparison UI

Phase F will design the consumer-facing interface that presents each bidding agent's representation compatibility signal alongside existing match scores. Phase F will define:
- How the compatibility signal is presented (qualitative label, percentage, visual indicator).
- How consumers access the per-trait explanation backing the signal.
- Whether consumers can see a side-by-side trait comparison view across multiple bidding agents.
- How compatibility information is integrated into the existing bid card layout without overweighting it relative to other bid factors.
- How the display clearly communicates advisory-only status and human decision authority.

Phase F requires: the scored signal from Phase D, the AI explanation from Phase E, and governance constraints from all prior phases.

### Phase G — Implementation Architecture

Phase G will design the technical implementation architecture for the compatibility scoring system. Phase G will define:
- The schema changes required (including the `representation_compatibility_score` column addition, the per-trait comparison result storage schema, and the `scoring_framework_version` increment).
- The Laravel service or job that executes the comparison layer.
- The event triggers for when compatibility scoring is computed or recomputed.
- The data access patterns for audit visibility and administrator review.
- Integration with the existing `listing_compatibility_scores` table's append-only architecture.

Phase G requires: the conceptual comparison methodology from Phase D, the agent response data model from Phase C, and the consumer normalized trait data model from Phase B.

---

## 17. Deferred Implementation Items

The following items are explicitly deferred to future implementation phases. None of the items listed here may be built, prototyped, or partially implemented based on this Phase D document alone.

| Deferred Item | Deferred To |
|---|---|
| Scoring formulas and numerical thresholds | Phase G (implementation architecture) |
| Trait weighting values or weighting logic | Phase G — only if weighting is adopted; must be disclosed per governance Rule 2 |
| Match threshold definitions (e.g., what constitutes "strong alignment") | Phase F (UI design) and Phase G (implementation) |
| Database schema changes (new columns, new tables) | Phase G |
| Migration files | Phase G |
| `representation_compatibility_score` column | Phase G |
| Per-trait comparison result storage schema | Phase G |
| `scoring_framework_version` increment | Phase G — no version increment occurs without implementation |
| Laravel service or job for comparison execution | Phase G |
| Event triggers for scoring computation | Phase G |
| Agent bid Livewire component changes | Future implementation phase — no code changes occur in Phase D |
| Blade markup for compatibility display | Phase F and future implementation |
| Validation rules | Future implementation |
| EAV meta save/load plumbing for scoring outputs | Phase G |
| AI prompt design for compatibility explanation | Phase E |
| AI explanation Fair Housing filters and guardrails | Phase E |
| Consumer-facing compatibility score display | Phase F |
| Bid card compatibility UI integration | Phase F |
| Side-by-side trait comparison view | Phase F |
| Agent-facing compatibility signal visibility | Governance decision deferred to Phase D review or later |
| Compatibility analytics or reporting dashboards | Post-Phase F |
| Compatibility signal filtering or sorting tools | Phase F |
| Proxy risk analysis for flagged traits | Required before Phase G for `risk_tolerance` and `property_strategy_fit` |
| Fair Housing compliance review | Required before any production use |
| Automated scoring pipeline | Phase G |
| Compatibility score caching strategy | Phase G |

---

## 18. Phase E Readiness Checklist

The following conditions must all be true before Phase E (AI explanation layer design) can begin. This checklist reflects the Phase D deliverables.

| Condition | Status |
|---|---|
| Trait comparison concepts complete — all 12 normalized traits have a conceptual comparison methodology described | Done — Section 6 |
| Alignment categories defined — full alignment, partial alignment, adjacent compatibility, neutral compatibility, and incompatible alignment defined with illustrative examples | Done — Section 8 |
| Informational-only fields identified and separated — narrative fields, scheduling preferences, cross-role coverage gaps, and the `representation_philosophy` narrative component explicitly designated as informational only | Done — Sections 10 and 7 |
| Conditional traits documented — all five conditionally score-eligible traits have role-specific scope defined | Done — Section 7 |
| Trait eligibility matrix complete — all 12 traits classified as score-eligible, conditionally score-eligible, or informational only with rationale | Done — Section 7 |
| Partial match concepts defined — conceptual alignment categories that Layer 3 comparison results will use, with examples | Done — Section 8 |
| Missing data handling defined — consumer missing, agent missing, role-specific missing, optional/sparse, and future incomplete profile situations all addressed | Done — Section 9 |
| Explainability standards documented — per-trait explainability requirements, no-black-box standard, no-undisclosed-weighting standard, consumer-facing explanation standard, compliant and non-compliant examples | Done — Section 11 |
| Governance rules restated in full — all 14 rules including Phase D additions | Done — Section 13 |
| Fair Housing safeguards documented — prohibited traits, prohibited inferences, proxy-risk awareness for flagged traits, demographic clustering prohibition, neighborhood demographic scoring prohibition, explainability as Fair Housing requirement, compliance review requirement | Done — Section 14 |
| Scoring philosophy documented — what compatibility measures, what it does not measure, advisory-only principle, human decision authority, ranking prohibition | Done — Section 4 |
| Scoring architecture described — four conceptual layers, structured comparison model, role-neutral defaults, `listing_compatibility_scores` integration point | Done — Section 5 |
| Versioning and auditability requirements documented — `scoring_framework_version`, append-only evolution, historical traceability, audit visibility, admin reviewability, explainable trait history | Done — Section 15 |
| Future phase dependencies documented — Phase E, F, G scope described without implementation detail | Done — Section 16 |
| Deferred items explicitly enumerated — all formulas, weights, schema changes, code, UI, AI prompts, and automation explicitly deferred | Done — Section 17 |
| No hidden weighting introduced — no scoring formula, threshold, or weight is specified in this document | Confirmed — this document contains no scoring formulas, numerical thresholds, or weighting values |
| No formulas implemented — this document is a conceptual planning document only | Confirmed — no code, schema, or implementation detail is provided |
| No production logic implemented — no routes, controllers, Livewire components, Blade files, migrations, or database changes | Confirmed — this document modifies no application files |
| Fair Housing compliance review planned | Pending — required before any element of the scoring or explanation system enters production use |
| Proxy risk analysis planned for flagged traits | Pending — required for `risk_tolerance` and `property_strategy_fit` before Phase G scoring implementation |

**Phase E can begin.** All compatibility scoring framework design prerequisites are satisfied. The Phase E deliverable is the AI explanation layer: the prompt structure, Fair Housing guardrails, tone guidelines, and technical mechanism for generating and surfacing plain-language compatibility explanations to consumers, consuming the per-trait comparison result record produced by the Phase D framework.

---

*End of document. This document is read-only. No code, schema, migration, route, controller, Blade, Livewire, config, or database file was modified to produce it.*
