# BidYourAgent — Professional Representation Compatibility: Agent-Side Response Design

**Document Status:** Read-only planning document. No code, schema, migration, route, controller, Blade, Livewire, config, or database file was modified to produce this document. No implementation occurs in this phase.
**Document Date:** 2026-05-28
**Phase:** C — Agent-Side Compatibility Response Design
**Preceding Phase:** B — Normalized Trait Design (`docs/BIDYOURAGENT_COMPATIBILITY_TRAIT_DESIGN.md`)
**Succeeding Phase:** D — Compatibility Scoring Framework (not yet started)

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Source References](#2-source-references)
3. [Phase C Goals](#3-phase-c-goals)
4. [Agent-Side Compatibility Architecture](#4-agent-side-compatibility-architecture)
5. [Proposed Agent Response Structure](#5-proposed-agent-response-structure)
6. [Unified Trait Response Definitions](#6-unified-trait-response-definitions)
7. [Unified Option Set Recommendations](#7-unified-option-set-recommendations)
8. [Trait-by-Trait Agent Response Design](#8-trait-by-trait-agent-response-design)
9. [Informational Context vs Structured Traits](#9-informational-context-vs-structured-traits)
10. [Traits Excluded From Agent Compatibility](#10-traits-excluded-from-agent-compatibility)
11. [Compatibility Visibility Rules](#11-compatibility-visibility-rules)
12. [Governance Rules](#12-governance-rules)
13. [Fair Housing Safeguards](#13-fair-housing-safeguards)
14. [Future Phase Dependencies](#14-future-phase-dependencies)
15. [Implementation Deferred Items](#15-implementation-deferred-items)
16. [Phase D Readiness Checklist](#16-phase-d-readiness-checklist)

---

## 1. Executive Summary

This document is the Phase C deliverable of the BidYourAgent Professional Representation Compatibility system.

**Phase A** (`docs/BIDYOURAGENT_COMPATIBILITY_AUDIT.md`) performed an exhaustive audit of all consumer-side compatibility fields across the four Hire Agent listing flows — Seller, Buyer, Landlord, and Tenant. The Phase A finding was unambiguous: **75 consumer-side compatibility fields are collected across all four roles** (22 Seller, 17 Buyer, 16 Landlord, 20 Tenant), while **all four agent bid Livewire components collect zero matching fields**. Agents currently bid blind. Consumer compatibility preference data is gathered and persisted via the EAV `saveMeta`/`loadMeta` pattern but drives no matching, scoring, or display logic whatsoever.

**Phase B** (`docs/BIDYOURAGENT_COMPATIBILITY_TRAIT_DESIGN.md`) took the 75 raw consumer fields and normalized them into a **12-trait ontology** called Professional Representation Compatibility. Phase B resolved four confirmed naming inconsistencies in the consumer-side raw data, produced per-role raw field crosswalks, generated unified option value alignment tables for all multi-role traits, and confirmed that zero agent-side counterparts exist for any of the 12 normalized traits in any of the four bid components.

**Phase C** — this document — designs the conceptual agent-side counterpart response structure that will, in a future implementation phase, enable compatibility symmetry between consumers and agents. The central objective is to define what agents should be asked, how those answers should be organized, what option sets they should see, and which traits should eventually contribute to a scored compatibility dimension versus which should remain informational context only.

**No implementation occurs in this phase.** This document produces no code, no schema, no migration, no Livewire component change, no Blade markup, no validation rule, no scoring formula, no AI prompt design, and no database column. The sole deliverable of Phase C is this planning document.

---

## 2. Source References

### 2.1 Authoritative Prior Phase Documents

| Document | Phase | Purpose |
|---|---|---|
| `docs/BIDYOURAGENT_COMPATIBILITY_AUDIT.md` | A | Consumer-side field audit, gap analysis, governance rules |
| `docs/BIDYOURAGENT_COMPATIBILITY_TRAIT_DESIGN.md` | B | Normalized 12-trait ontology, crosswalks, naming inconsistency resolutions |

### 2.2 Phase A Summary: 75 Consumer Compatibility Fields

| Role | Total Fields | Required | Optional |
|---|---|---|---|
| Seller | 22 | 5 | 17 |
| Buyer | 17 | 5 | 12 |
| Landlord | 16 | 4 | 12 |
| Tenant | 20 | 4 | 16 |
| **Total** | **75** | **18** | **57** |

Companion "Other" free-text inputs that are conditional on a parent select value are included in these counts but are not counted as independent fields for trait-mapping purposes.

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

### 2.4 Phase A and B Confirmed: Zero Agent-Side Counterparts

Phase B Section 9 confirmed that all 12 normalized traits are absent from all four agent bid components (`SellerAgentAuctionBid`, `BuyerAgentAuctionBid`, `LandlordAgentAuctionBid`, `TenantAgentAuctionBid`). No `compatibility_preferences` property, no `compatibility_response` property, and no field corresponding to any consumer compatibility question exists in any bid component. The `listing_compatibility_scores` table has no `representation_compatibility_score` column.

---

## 3. Phase C Goals

Phase C has seven design goals. All seven must be satisfied by this document's conceptual framework before Phase D (scoring) can begin.

### Goal 1 — Compatibility Symmetry

Design agent-side responses that form a coherent counterpart to the consumer-side normalized traits. Every structured consumer trait that is scored should have a corresponding agent response field so that a comparison can be made. Symmetry does not require identical option vocabularies — it requires that agent responses be interpretable against consumer inputs at the same normalized trait level.

### Goal 2 — Structured Agent-Side Responses

The agent-side response system must be structured. Free-text narratives are appropriate for informational context and narrative self-description, but the scoring-eligible traits must use structured, bounded option sets that can be reliably compared. Structured options also reduce cognitive burden on agents completing bid forms and reduce the risk of responses that cannot be processed by a future scoring layer.

### Goal 3 — Explainable Compatibility

Every compatibility signal that Phase D may score must be explainable to both parties. An agent should be able to understand what their response implies about their working style. A consumer should be able to understand why a particular agent's responses were or were not aligned with their stated preferences. No black-box scoring inputs are permissible.

### Goal 4 — Role-Neutral Architecture

Agent response fields should be defined at the normalized trait level, not at the role-specific raw field level. A single agent should be able to express their `collaboration_style` once and have that response contribute to compatibility scoring for Seller, Buyer, Landlord, and Tenant listings. This avoids forcing agents to re-answer semantically identical questions four times with different role-specific option labels.

Where role-specific context genuinely matters — for example, an agent's `representation_priorities` for a Seller listing differs in content from their priorities for a Tenant listing — the architecture may support role-scoped sub-responses within the normalized trait container. The default is role-neutral; role-scoping is an exception for cases where role context is essential.

### Goal 5 — Future Scoring Readiness

The agent response design must be structured in a way that Phase D can compare agent responses to consumer responses without requiring redesign of the response field set. This means: option sets must be anchored to unified semantic clusters (from Phase B crosswalks), field naming must be stable and unambiguous, and the response container must be clearly separated from other agent bid data.

### Goal 6 — AI Explanation Readiness

Phase E will design an AI-generated plain-language explanation of compatibility scores. The agent response fields designed in Phase C must be legible to an AI explanation layer — their option labels must be self-explanatory, their trait groupings must be logical, and their informational context fields must be clearly distinguishable from scored fields so that an AI explanation system can accurately characterize what each compatibility signal means.

### Goal 7 — Governance-Safe Design

The agent response design must be governance-safe from inception. This means: no protected-class data collected, no Fair Housing-sensitive framing, no hidden weighting baked into option sequences, no demographic profiling through the back door of "strategy preference" questions, and full compliance with the governance rules established in Phase A Section 15 and Phase B Section 11. Governance is not a review step at the end — it is a design constraint from the beginning.

---

## 4. Agent-Side Compatibility Architecture

### 4.1 Conceptual Storage Path

Agent-side compatibility responses should eventually be stored under a new sub-key within the existing `compatibility_preferences` JSON blob used by the platform's EAV `saveMeta`/`loadMeta` pattern. The proposed path is:

```
compatibility_preferences.agent_response.*
```

This mirrors the consumer-side architecture, where consumer data is stored at:
```
compatibility_preferences.{role}_specific.*
```

The `agent_response` sub-key distinguishes agent data from consumer data while maintaining structural consistency with the existing pattern. When a future implementation phase adds agent-side compatibility responses, they should live at paths such as:
- `compatibility_preferences.agent_response.communication_preferences.*`
- `compatibility_preferences.agent_response.negotiation_approach.*`
- `compatibility_preferences.agent_response.working_style.*`
- (and so on for each sub-section defined in Section 5)

Agent responses should be stored in the bid meta table for the corresponding role — for example, `seller_agent_auction_bid_metas` for Seller agent bids. This is consistent with the existing EAV pattern used by agent bid components across all four roles.

### 4.2 Trait-Level, Not Raw-Field-Level

Agent response fields must be designed at the **normalized trait level**, not at the raw consumer field level. This is critical.

The 75 consumer-side raw fields use inconsistent key names for semantically equivalent data (e.g., `communication_style` stores frequency data for Seller/Buyer but channel data for Landlord/Tenant). Phase B's normalization layer resolved these inconsistencies. Agent responses must not replicate the same inconsistencies — they must answer at the trait level so that a single agent response can be compared against any of the four consumer role variants.

For example: an agent's `communication_channel` response (what channels they use most reliably) should be a single, role-neutral response that is compared against the Seller's `preferred_contact_method`, the Buyer's `preferred_contact_method`, the Landlord's `communication_style` (mapped to `communication_channel`), and the Tenant's `communication_style` (mapped to `communication_channel`) — all through the normalized trait layer.

### 4.3 Role-Neutral by Default, Role-Scoped by Exception

The architecture is role-neutral by default. An agent fills in their compatibility response profile once, and those responses can be matched against listings from any role. This minimizes agent data entry burden and maximizes the coverage of the response set.

Role-scoping should be considered only for `representation_priorities` and `property_strategy_fit`, where the role-specific content of the trait options is so different across roles that a single unified response would not be meaningful. For example, an agent's statement of representation priorities for Seller listings (marketing, negotiation, coordination) differs in content from their priorities for Tenant listings (neighborhood knowledge, budget management, speed). In these cases, the architecture may support role-specific sub-responses within the normalized trait container.

All other traits — `communication_channel`, `communication_frequency`, `responsiveness_expectation`, `negotiation_style`, `guidance_level`, `decision_making_style`, `transaction_pace`, `risk_tolerance`, `collaboration_style`, and `representation_philosophy` — should be collected as role-neutral single responses.

### 4.4 Explainability and Auditability Requirements

Every field in the agent response set must satisfy two requirements before it can contribute to scoring in Phase D:

1. **Explainability:** The relationship between the agent's response and the consumer's preference must be describable in plain language without reference to opaque algorithms. "This agent prefers to update clients weekly; you indicated you want daily updates" is explainable. A weighted composite score with undisclosed inputs is not.

2. **Auditability:** The agent's response, the consumer's preference, and the comparison result must all be inspectable by platform administrators for compliance review. No scoring input may be hidden from audit.

These requirements do not constrain Phase C's design directly — they are design prerequisites that Phase C must not violate. No field should be designed in a way that would make it impossible to satisfy these requirements in Phase D.

---

## 5. Proposed Agent Response Structure

The agent-side compatibility responses should be organized into seven logical sub-sections under `compatibility_preferences.agent_response.*`. Each sub-section groups related traits and provides a coherent, label-friendly unit for the eventual bid form UI.

### 5.1 Sub-Section Map

| Sub-Section Key | Purpose | Normalized Traits It Feeds |
|---|---|---|
| `communication_preferences` | How the agent communicates with clients | `communication_channel`, `communication_frequency`, `responsiveness_expectation` |
| `negotiation_approach` | The agent's negotiation philosophy and posture | `negotiation_style` |
| `working_style` | The agent's overall operating mode and availability | `collaboration_style` |
| `guidance_approach` | How much direction and involvement the agent provides | `guidance_level` |
| `transaction_strategy` | The agent's alignment with consumer transaction goals and timelines | `transaction_pace`, `property_strategy_fit` |
| `representation_priorities` | The specific capabilities and outcomes the agent delivers | `representation_priorities` |
| `representation_philosophy` | The agent's values-level beliefs about professional representation | `representation_philosophy`, `decision_making_style`, `risk_tolerance` |

### 5.2 Sub-Section Descriptions

**`communication_preferences`** collects the three communication-related traits together. An agent's responses here tell consumers how the agent will reach out, how often, and how fast they respond. This sub-section directly mirrors the Communication Preferences sections present in all four consumer-side compatibility blades.

**`negotiation_approach`** is a single-trait sub-section because negotiation style is consequential enough to warrant its own logical grouping. An agent's negotiation posture has a direct bearing on whether their style will be compatible with a consumer's stated approach.

**`working_style`** captures how the agent operates as a professional presence — whether they are proactive and initiative-driven, consultative, responsive-on-demand, or process-focused. This feeds the `collaboration_style` trait and is the agent-side counterpart to the `preferred_agent_working_style` questions collected across all four consumer roles.

**`guidance_approach`** describes how much direct involvement and decision-making the agent typically provides. This feeds `guidance_level` and enables comparison against the consumer's `involvement_level`, `support_level`, `property_management_involvement`, or `desired_level_of_agent_involvement` depending on the role.

**`transaction_strategy`** covers both timeline compatibility (`transaction_pace`) and strategic goal alignment (`property_strategy_fit`). An agent who specializes in quick sales is strategically aligned with sellers who prioritize speed; an agent who focuses on maximizing sale price serves a different consumer profile. Both dimensions — timeline flexibility and strategic goal alignment — live here.

**`representation_priorities`** asks the agent which capabilities and service outcomes they are best positioned to deliver, using role-contextual option sets. Because the content of representation priorities differs substantially by role (marketing expertise vs. tenant screening vs. lease negotiation), this sub-section is the primary candidate for role-scoped sub-responses.

**`representation_philosophy`** covers the values-level and self-reflective dimensions of the agent's professional identity — their decision-support style, their comfort level with different consumer risk postures, and their overall professional philosophy. This sub-section also provides the narrative context fields that will assist the AI explanation layer in Phase E without contributing to scored dimensions.

---

## 6. Unified Trait Response Definitions

For each of the 12 normalized traits, this section defines: what the agent response represents, what it does not represent, what consumers are comparing against, and what future scoring would conceptually compare.

### 6.1 `communication_channel`

**What the agent response represents:** The primary channels through which the agent routinely communicates with clients during a transaction. An agent's response here reflects how they actually work — which channels they are most responsive on, most organized about, and most likely to use proactively.

**What it does NOT represent:** How often the agent communicates (that is `communication_frequency`), how quickly they respond (that is `responsiveness_expectation`), or the agent's working style or personality (that is `collaboration_style`).

**What consumers are comparing against:** Seller and Buyer `preferred_contact_method` (multi-select: Phone Call, Text/SMS, Email, Video Call, In-Person); Landlord `communication_style` (normalized to channel: Email Only, Phone Calls Preferred, Text/SMS Preferred, etc.); Tenant `communication_style` (normalized to channel: Email, Phone calls, Text/SMS, Video calls, In-person).

**What future scoring would conceptually compare:** Whether the agent's preferred or available channels overlap with the consumer's stated channel preference(s). Multi-select overlap scoring is the natural comparison approach — a consumer requesting email and text who receives a response from an agent listing email and text as primary channels has a higher channel alignment than a consumer requesting in-person meetings who receives an agent response listing only platform messaging.

---

### 6.2 `communication_frequency`

**What the agent response represents:** The cadence at which the agent proactively initiates client contact and provides status updates during a transaction, without the client needing to ask. An agent's response here reflects their standard professional practice — not a commitment adjustable to every client, but their baseline operating rhythm.

**What it does NOT represent:** The channel the agent uses (that is `communication_channel`), how fast they respond to inbound messages (that is `responsiveness_expectation`), or the agent's broader collaboration style.

**What consumers are comparing against:** Seller `communication_style` (frequency options: Frequent & Proactive, As-Needed Updates, etc.); Buyer `communication_style` (frequency options: Frequent Updates Daily, Weekly, etc.); Landlord `preferred_contact_method` (normalized to frequency: Daily Updates, Weekly Check-Ins, etc.); Tenant `contact_frequency` (Daily, Every few days, Weekly, etc.).

**What future scoring would conceptually compare:** Alignment between the consumer's expected proactive contact cadence and the agent's stated standard contact cadence. A consumer expecting daily contact who selects an agent who describes their standard as weekly check-ins has a meaningful frequency mismatch worth surfacing.

---

### 6.3 `responsiveness_expectation`

**What the agent response represents:** The agent's realistic commitment to response time when a client initiates contact — how quickly they can be expected to return a call, reply to a message, or respond to a question during a transaction.

**What it does NOT represent:** How often the agent proactively reaches out (that is `communication_frequency`), which channels they use (that is `communication_channel`), or how involved they are in guiding the consumer through decisions (that is `guidance_level`).

**What consumers are comparing against:** Seller `response_time_expectation` (Within 1 Hour, Within a Few Hours, Same Day, Next Business Day); Landlord `response_time_expectation` (Within 1 Hour, Within a Few Hours, Same Business Day, Within 24 Hours, Within 48 Hours, Flexible). Note: Buyer and Tenant do not have a direct equivalent consumer field for this trait; the agent's response against these roles would be informational only unless Phase D determines otherwise.

**What future scoring would conceptually compare:** Whether the agent's stated response time commitment meets or exceeds the consumer's stated expectation. A consumer expecting same-day responses from an agent who commits to next-business-day turnaround is a compatibility signal that scoring can surface.

---

### 6.4 `negotiation_style`

**What the agent response represents:** The strategic posture and philosophical approach the agent brings to negotiations on their client's behalf — how aggressively, collaboratively, or flexibly they typically advocate. An agent's response here is their professional self-description, not a role-playing claim. It should reflect how they genuinely approach negotiation situations.

**What it does NOT represent:** The agent's communication style, their decision-support approach, their timeline management style, or any dimension of their service scope.

**What consumers are comparing against:** Seller `negotiation_style` (Aggressive — Push for Maximum Profit, Balanced — Fair & Reasonable, Flexible — Prioritize Quick Sale, Collaborative — Seller & Buyer Both Win); Buyer `negotiation_style` (Aggressive Negotiator, Firm but Fair, Collaborative, Offer Full Price to Win, Guided by Agent); Landlord `negotiation_style` (Firm on Terms, Open to Negotiation, Collaborative Win-Win, Market-Rate Anchored, Flexible Case-by-Case); Tenant `negotiation_style` (Aggressive, Collaborative, Conservative — prioritize securing, Flexible).

**What future scoring would conceptually compare:** Alignment between the consumer's desired negotiation posture and the agent's stated negotiation approach. A consumer who wants an aggressive negotiator selecting an agent who describes themselves as balanced and collaborative has a clear posture mismatch — one the consumer deserves to know about before awarding a bid.

---

### 6.5 `guidance_level`

**What the agent response represents:** The degree to which the agent typically leads, manages, and decides on the client's behalf during a transaction — how much autonomy they take versus how much they consult the client at every step. An agent's response here tells consumers how hands-on or hands-off the agent's default operating mode is.

**What it does NOT represent:** The agent's communication frequency, their negotiation posture, their working style personality, or how they manage their own decision-making pace.

**What consumers are comparing against:** Seller `involvement_level` (Very Involved, Moderately Involved, Mostly Hands-Off); Buyer `support_level` (Minimal, Moderate, High, Full White-Glove); Landlord `property_management_involvement` (Hands-Off, Minimal Involvement, Occasional Check-Ins, Actively Involved, Self-Manage After Placement); Tenant `desired_level_of_agent_involvement` (Fully Delegated, Mostly Delegated, Collaborative, Mostly Hands-On).

**What future scoring would conceptually compare:** Whether the agent's default guidance level is compatible with the consumer's preferred involvement level. A consumer who wants to be hands-off and fully delegate paired with an agent who expects the client to lead is a structurally mismatched working relationship.

---

### 6.6 `decision_making_style`

**What the agent response represents:** The agent's approach to supporting client decision-making — how they pace recommendations, how much information they provide before expecting a decision, and how they handle clients who need more time or more data before committing.

**What it does NOT represent:** The agent's negotiation posture, their transaction pace, their communication frequency, or their guidance level.

**What consumers are comparing against:** Seller `decision_making_style` (Independent, Collaborative, Cautious, Data-Driven); Buyer `decision_making_style` (Quick Decisions, Careful & Deliberate, Collaborative with Agent, Research-Driven, Flexible/Situational); Tenant `decision_making_style` (Quick, Deliberate, Research-driven, Collaborative). Note: Landlord has no direct equivalent consumer field for this trait.

**What future scoring would conceptually compare:** Alignment between the consumer's decision-making pace and the agent's stated approach to pacing client decisions. A consumer who describes themselves as deliberate and data-driven should be matched with agents who state they are comfortable providing comprehensive information and allowing adequate deliberation time.

---

### 6.7 `transaction_pace`

**What the agent response represents:** The agent's ability and typical approach to managing transaction timelines — how they handle urgent situations, whether they are effective at accelerating deals when a consumer needs speed, and how comfortable they are working within firm deadlines versus flexible timelines.

**What it does NOT represent:** The agent's strategic goal alignment (that is `property_strategy_fit`), their guidance level, their negotiation posture, or any communication-related trait.

**What consumers are comparing against:** Seller `flexibility_on_timeline` (Very Flexible, Somewhat Flexible, Firm on Timeline); Buyer `timeline_flexibility` (Very Flexible, Somewhat Flexible, Limited Flexibility, Strict Timeline); Tenant `timeline_urgency` (Immediate Within 2 Weeks, Within 30 Days, 1–2 Months, 2–3 Months, 3–6 Months, 6+ Months, Exploring Options Only). Note: Landlord has no direct equivalent consumer field for this trait.

**What future scoring would conceptually compare:** Whether the agent's stated timeline capability is compatible with the consumer's timeline context. A tenant with an immediate move-in need (within 2 weeks) requires an agent who can operate under compressed timelines and has access to inventory that can move quickly.

---

### 6.8 `risk_tolerance`

**What the agent response represents:** The agent's professional comfort level with transactional and financial risk situations — how they advise clients in competitive or non-standard scenarios, whether they push conservative or more aggressive strategies in ambiguous situations, and their experience navigating deals that require flexibility beyond standard criteria.

**What it does NOT represent:** The agent's negotiation style (though risk tolerance and negotiation posture are related, they are distinct — an agent can be a collaborative negotiator and have a high risk appetite, or be an aggressive negotiator and be conservative about financial risk).

**What consumers are comparing against:** Buyer `risk_tolerance` (Very Conservative through Very Aggressive); Landlord `risk_tolerance` (Low – Strict Screening Only through High – Willing to Work With Most Tenants). Note: Seller and Tenant do not have direct equivalent consumer fields for this trait.

**What future scoring would conceptually compare:** Whether the agent's risk posture is compatible with the consumer's stated appetite for risk. A landlord who wants strict tenant screening deserves to know if an agent typically advocates for more flexible acceptance criteria.

---

### 6.9 `collaboration_style`

**What the agent response represents:** The agent's overall operating mode as a professional presence — the interpersonal style and initiative level that characterizes how they work with clients. This is the agent's self-description of their professional personality as it shows up during a transaction.

**What it does NOT represent:** How frequently the agent contacts clients (that is `communication_frequency`), what specific tasks they prioritize (that is `representation_priorities`), how involved they keep the client in decisions (that is `guidance_level`), or their negotiation posture.

**What consumers are comparing against:** All four roles use `preferred_agent_working_style` as the consumer-side field feeding this trait. Options vary by role but cluster around: Proactive/Initiative-driven, Consultative/Advisory, Responsive/On-demand, Process-oriented/Detail-focused, Full-service/Concierge, and Collaborative/Relationship-focused (Phase B Section 6.2 crosswalk).

**What future scoring would conceptually compare:** Whether the agent's self-described working mode matches what the consumer selected as their preferred agent style. This is the most direct "personality compatibility" signal in the system and is likely to be one of the highest-weight scored dimensions.

---

### 6.10 `representation_priorities`

**What the agent response represents:** The specific professional capabilities, service categories, and outcome types that the agent is best positioned to deliver. An agent's response here is a self-assessment of where their expertise and effort are most concentrated — not a listing of all services they offer (that belongs in the services scope fields of the bid form).

**What it does NOT represent:** The agent's compensation or service scope (those are transaction terms), their philosophy about representation values (that is `representation_philosophy`), or their working style personality.

**What consumers are comparing against:** Seller `representation_priorities` (Market Expertise, Strong Negotiator, High Communication & Responsiveness, Local Connections, Marketing Strategy, Staging, Digital Marketing, Transaction Management); Buyer `representation_priorities` (Price Negotiation, Speed of Transaction, Off-Market Properties, Contract Protection, Communication & Updates, Neighborhood Expertise, Investment Analysis, First-Time Buyer Guidance, Relocation Assistance); Landlord `representation_priorities` (Tenant Screening, Marketing & Advertising, Lease Negotiation, Legal & Lease Documentation, Showings, Market Pricing Guidance, Move-In Coordination, Ongoing Communication); Tenant `representation_priorities` (Neighborhood/location, Budget management, Speed of placement, Lease negotiation, Property condition, Pet-friendly options, Accessibility features, School district).

**What future scoring would conceptually compare:** Overlap between the consumer's stated top priorities and the agent's stated primary strengths. Because representation priorities are role-specific in content, this trait is the primary candidate for role-scoped agent sub-responses (see Section 4.3).

---

### 6.11 `representation_philosophy`

**What the agent response represents:** The agent's values-level professional beliefs about what good representation requires — the qualities, commitments, and principles they consider non-negotiable. This is distinct from their operating style (`collaboration_style`) and their specific deliverables (`representation_priorities`). It is their professional identity at its most fundamental level.

**What it does NOT represent:** The agent's specific task priorities, their communication cadence, their negotiation posture, or any transactional characteristic.

**What consumers are comparing against:** Seller `qualities_most_important` (Honesty & Transparency, Patience, Assertiveness, Attention to Detail, Tech-Savvy, Empathy, Proactivity) and `past_agent_experience` context; Tenant `most_important_agent_traits` (Honesty and Transparency, Strong Communication, Market Knowledge, Negotiation Skills, Responsiveness, Local Expertise, Client-Focused Approach, Technology-Savvy, Attention to Detail, Problem-Solving Ability, Professional Network). Note: Buyer and Landlord do not have direct structured equivalents for this trait on the consumer side.

**What future scoring would conceptually compare:** Because consumer-side data for this trait is sparse and present only in two of four roles, and because it is inherently values-level rather than behavioral, `representation_philosophy` is a strong candidate for informational-only status rather than a scored compatibility dimension. Phase D should evaluate this carefully. Agent responses here are still valuable for AI explanation purposes in Phase E.

---

### 6.12 `property_strategy_fit`

**What the agent response represents:** The types of transactions, strategic goals, and client contexts in which the agent has experience and which they are best positioned to serve effectively. An agent's response here tells consumers whether the agent has meaningful experience with the consumer's specific situation — quick sale under time pressure, first investment property acquisition, placing a stable long-term tenant, securing a short-term rental under urgent circumstances.

**What it does NOT represent:** The agent's negotiation posture (that is `negotiation_style`), their timeline management (that is `transaction_pace`), their service scope (those are transaction terms), or their communication style.

**What consumers are comparing against:** Seller `primary_transaction_goal` (Maximum Sale Price, Quick Sale, Minimal Disruption, Specific Closing Timeline, Other); Buyer `primary_transaction_goal` (Primary Residence, Vacation Home, Investment Property, Fix & Flip, Commercial, Land); Landlord `primary_leasing_goal` (Maximize Monthly Rent, Long-Term Stable Tenant, Minimize Vacancy, High-Quality Tenant Profile, Portfolio Cash Flow, Property Appreciation) and `tenant_type_preference`; Tenant `primary_rental_goal` (Find a long-term home, Temporary/short-term, Relocating for work, Downsizing, Upsizing, Investment search).

**What future scoring would conceptually compare:** Whether the agent's stated areas of experience and specialization align with the consumer's primary transaction goal. An agent whose stated strength is investment property acquisition is well-positioned for a buyer looking for an investment property; they may be a weaker match for a first-time buyer seeking a primary residence with hand-holding through the process.

---

## 7. Unified Option Set Recommendations

This section proposes conceptual unified option sets for seven traits where agent-side options must be answerable across all four consumer roles simultaneously. Options are drawn from the Phase B crosswalks (Sections 6.1 through 6.7 of the Trait Design document) and use role-neutral, professional language. No manipulative framing, psychological profiling language, or protected-class proxies are used.

### 7.1 `communication_frequency` — Proposed Unified Agent Option Set

The agent is asked: *How often do you proactively reach out to clients during a transaction?*

| Option | Semantic Cluster | Phase B Crosswalk Coverage |
|---|---|---|
| Daily — I check in with clients every day | Daily / Constant | Seller: "Frequent & Proactive"; Buyer: "Frequent Updates (Daily)"; Landlord: "Daily Updates"; Tenant: "Daily" |
| Every few days — I reach out proactively every 2–3 days | Every few days | Buyer: "Regular Updates (Every Few Days)"; Landlord: "Every Few Days"; Tenant: "Every few days" |
| Weekly — I schedule weekly check-ins | Weekly | Seller: "Structured Check-Ins"; Buyer: "Weekly Updates"; Landlord: "Weekly Check-Ins"; Tenant: "Weekly" |
| At major milestones — I contact clients when something meaningful happens | Major milestones only | Seller: "As-Needed Updates"; Buyer: "Only When Necessary"; Landlord: "Only Major Milestones"; Tenant: "Only on major updates" |
| When the client reaches out — I respond promptly but don't initiate unless there's news | On-demand / Consumer-initiated | Seller: "Available On-Demand"; Buyer: "As-Needed / On-Demand"; Landlord: "Only When I Ask"; Tenant: "As needed" |

### 7.2 `negotiation_style` — Proposed Unified Agent Option Set

The agent is asked: *How would you describe your approach to negotiation on behalf of clients?*

| Option | Semantic Cluster | Phase B Crosswalk Coverage |
|---|---|---|
| Assertive — I push hard for the best possible terms for my client | Aggressive / Maximum advantage | All four roles: aggressive/firm cluster |
| Balanced — I pursue strong terms while keeping negotiations productive and professional | Balanced / Firm but fair | Seller: "Balanced — Fair & Reasonable"; Buyer: "Firm but Fair"; Landlord: "Market-Rate Anchored" |
| Collaborative — I seek outcomes that work well for all parties without sacrificing my client's core interests | Collaborative / Win-win | All four roles: collaborative cluster |
| Flexible — I adapt my approach to what best serves my client's specific goal in this transaction | Flexible / Speed-priority or adaptive | Seller: "Flexible — Prioritize Quick Sale"; Landlord: "Flexible Case-by-Case"; Tenant: "Flexible" |
| Conservative — I focus on securing the deal first and optimizing terms within that | Conservative / Secure-first | Tenant: "Conservative — prioritize securing a property"; Landlord: "Open to Negotiation" cluster |
| Client-guided — I present the full picture and follow my client's direction on negotiation stance | Agent-guided (mirrored) | Buyer: "Guided by Agent" mirrored to agent side |

### 7.3 `collaboration_style` — Proposed Unified Agent Option Set

The agent is asked: *How would you describe your working style when representing clients?*

| Option | Semantic Cluster | Phase B Crosswalk Coverage |
|---|---|---|
| Proactive — I anticipate needs and act before clients need to ask | Proactive / Takes initiative | All four roles: proactive cluster |
| Consultative — I explain options thoroughly and guide clients through decisions | Consultative / Advisory | Seller: "Consultative & Guides Me"; Buyer: "Advisor / Consultant"; Landlord: "Consultative & Advisory" |
| Responsive — I am available and responsive when clients reach out, without over-communicating | Responsive / On-demand | Seller: "Responsive & Available"; Buyer: "Responsive Partner"; Tenant: "Efficient" |
| Process-oriented — I follow a thorough, organized process and focus on getting every detail right | Process / Detail-focused | Seller: "Process-Oriented & Detail-Focused"; Landlord: "Data-Driven & Analytical" |
| Full-service — I manage everything end-to-end and keep clients informed without burdening them | Full-service / Concierge | Buyer: "Full-Service Concierge"; Tenant: "Full service — handle everything" |
| Collaborative — I work alongside clients as a partner, checking in frequently and making decisions together | Collaborative / Joint decisions | Landlord: "Relationship-Focused"; Tenant: "Collaborative — frequent check-ins" |

### 7.4 `guidance_level` — Proposed Unified Agent Option Set

The agent is asked: *How do you typically approach the level of direction and involvement you provide to clients?*

| Option | Semantic Cluster | Phase B Crosswalk Coverage |
|---|---|---|
| Fully managed — I handle all details and decisions, keeping clients informed without requiring their constant input | Fully delegated / Hands-off | All four roles: hands-off / fully delegated cluster |
| Mostly managed — I lead and handle the bulk of the work; clients approve key decisions | Mostly delegated / High support | All four roles: mostly delegated cluster |
| Collaborative — We work together throughout; I provide guidance and the client stays actively engaged | Collaborative / Shared | Buyer: "Moderate – Key Touchpoints"; Landlord: "Occasional Check-Ins"; Tenant: "Collaborative" |
| Client-led — I advise, support, and execute, but the client sets direction and makes all calls | Hands-on / Self-sufficient | All four roles: hands-on / self-sufficient cluster |

### 7.5 `transaction_pace` — Proposed Unified Agent Option Set

The agent is asked: *How do you approach transaction timelines and deadline management?*

| Option | Semantic Cluster | Phase B Crosswalk Coverage |
|---|---|---|
| Urgent timelines are my specialty — I can move fast when the situation requires it | Strict / Immediate | Seller: "Firm on Timeline"; Buyer: "Strict Timeline"; Tenant: "Immediate (Within 2 Weeks)" / "Within 30 Days" |
| I work well with firm timelines and plan systematically to meet them | Somewhat constrained | Buyer: "Limited Flexibility"; Seller: "Somewhat Flexible" |
| I am comfortable with flexible timelines and can adapt as circumstances evolve | Very flexible / Exploring | All four roles: very flexible cluster |
| I adapt to the client's timeline — whether urgent or unhurried, I match the pace needed | Fully adaptive | Cross-role adaptive option |

### 7.6 `decision_making_style` — Proposed Unified Agent Option Set

The agent is asked: *How do you support clients in reaching decisions during a transaction?*

| Option | Semantic Cluster | Phase B Crosswalk Coverage |
|---|---|---|
| I present a clear recommendation and help clients act decisively when timing matters | Quick / Independent support | All three roles: quick/independent cluster |
| I take time to ensure clients fully understand their options before recommending a path | Deliberate / Cautious support | All three roles: cautious/deliberate cluster |
| I provide comprehensive data and analysis so clients can make evidence-based decisions | Data-driven / Research support | All three roles: data-driven cluster |
| I check in with all decision-makers and ensure everyone is aligned before moving forward | Collaborative / Multi-party | Tenant: "Collaborative — involve family/partner"; Seller: "Collaborative — I Value Agent Input" |
| I read what each client needs and adjust how I present options to match their decision style | Flexible / Situational | Buyer: "Flexible / Situational" |

### 7.7 `representation_priorities` — Proposed Unified Agent Option Set

Because `representation_priorities` options are role-specific in content, this trait requires role-contextual agent responses. The following presents the proposed unified option set per role. The agent selects the options that best reflect their primary areas of strength and focus for each role context.

**For Seller Representation:**
Market Expertise & Pricing Analysis, Listing Presentation & Staging, Digital & Social Media Marketing, Negotiation & Offer Management, Contract Management & Transaction Coordination, Network & Off-Market Access, Communication & Client Updates, Timeline & Logistics Management

**For Buyer Representation:**
Offer Strategy & Price Negotiation, Off-Market & Early Listing Access, Contract Review & Buyer Protections, First-Time Buyer Education & Guidance, Investment & Cash Flow Analysis, Relocation & Area Expertise, Speed of Transaction & Competitive Market Support, Communication & Progress Updates

**For Landlord Representation:**
Tenant Screening & Qualification, Marketing & Listing Placement, Lease Negotiation & Documentation, Market Pricing & Rent Analysis, Showing Coordination & Open Houses, Move-In Coordination, Ongoing Landlord Communication & Reporting, Concession Strategy & Vacancy Reduction

**For Tenant Representation:**
Property Search & Neighborhood Matching, Budget Management & Negotiation, Speed of Placement, Lease Review & Tenant Protections, Property Condition Assessment, Pet-Friendly & Special-Needs Property Search, School District & Community Research, Relocation Support

---

## 8. Trait-by-Trait Agent Response Design

For each of the 12 normalized traits, this section defines: the conceptual agent response field name, the response purpose, whether structured or freeform is recommended, whether multi-select or single-select is appropriate, whether the trait should contribute to future scoring, and whether it should remain informational-only.

| # | Normalized Trait | Conceptual Agent Field | Purpose | Structured or Freeform | Select Type | Score-Eligible? | Informational Only? |
|---|---|---|---|---|---|---|---|
| 1 | `communication_channel` | `agent_communication_channels` | Which channels the agent reliably uses with clients | Structured | Multi-select | Yes | No |
| 2 | `communication_frequency` | `agent_communication_frequency` | The agent's standard proactive contact cadence | Structured | Single-select | Yes | No |
| 3 | `responsiveness_expectation` | `agent_response_time_commitment` | The agent's realistic inbound response time | Structured | Single-select | Yes | No |
| 4 | `negotiation_style` | `agent_negotiation_style` | The agent's negotiation posture and philosophy | Structured | Single-select | Yes | No |
| 5 | `guidance_level` | `agent_guidance_level` | The agent's default direction/involvement level | Structured | Single-select | Yes | No |
| 6 | `decision_making_style` | `agent_decision_support_style` | How the agent supports client decision-making | Structured | Single-select | Yes | No |
| 7 | `transaction_pace` | `agent_transaction_pace` | The agent's timeline management capability | Structured | Single-select | Yes | No |
| 8 | `risk_tolerance` | `agent_risk_posture` | The agent's comfort with transactional risk situations | Structured | Single-select | Conditional — see note | See note |
| 9 | `collaboration_style` | `agent_collaboration_style` | The agent's overall professional operating mode | Structured | Single-select | Yes | No |
| 10 | `representation_priorities` | `agent_representation_priorities` | The agent's primary capability strengths (role-scoped) | Structured | Multi-select (per role) | Yes | No |
| 11 | `representation_philosophy` | `agent_representation_philosophy` | The agent's values-level professional beliefs | Structured + Freeform | Multi-select + Narrative | Conditional — see note | See note |
| 12 | `property_strategy_fit` | `agent_strategy_experience` | Transaction types and goals the agent has meaningful experience with | Structured | Multi-select | Yes | No |

**Notes on `risk_tolerance` (Trait 8):** Consumer-side data for this trait exists only for Buyer and Landlord roles; Seller and Tenant have no direct equivalent field. The agent response is still worth collecting for its informational value even where a direct consumer comparison is unavailable. Phase D should determine whether `risk_tolerance` contributes to scoring for all four roles or only for Buyer and Landlord listings.

**Notes on `representation_philosophy` (Trait 11):** Consumer-side structured data for this trait exists only for Seller (`qualities_most_important`) and Tenant (`most_important_agent_traits`). Buyer and Landlord have no direct structured equivalent. The structured component (multi-select professional values) may be score-eligible for Seller and Tenant listings. The narrative component (free-text professional statement) should be informational only and should feed the AI explanation layer in Phase E rather than direct scoring. Phase D should determine the scoring scope.

---

## 9. Informational Context vs Structured Traits

Not every field that provides useful context about an agent or consumer should become a scored compatibility dimension. This section explicitly separates (A) structured compatibility traits — those designed for comparison and potential scoring — from (B) informational context fields — those that provide useful background but are not appropriate for structured scoring.

### 9.1 Structured Compatibility Traits

Structured traits are the 12 normalized traits defined in Phase B and specified in Sections 6 and 8 of this document. They share these characteristics:

- They have bounded option sets with meaningful semantic clusters.
- They can be compared between an agent response and a consumer preference at the same normalized level.
- Their comparison produces a signal that is explainable in plain language.
- They do not contain protected-class data or Fair Housing-sensitive content.

These traits are the primary inputs to the Phase D scoring framework. All 12 should have agent-side counterpart response fields, as designed in this document.

### 9.2 Informational Context Fields

Informational context fields provide useful background on an agent or consumer's situation, preferences, or constraints, but they are not appropriate for automatic scoring for one or more of the following reasons:

- Their content is free-text narrative that cannot be reliably structured for comparison.
- They describe scheduling logistics or operational preferences rather than professional compatibility.
- Their consumer-side data exists for only one or two roles, making cross-role scoring incoherent.
- Their content is too situation-specific to produce a generalizable compatibility signal.

The following are examples of informational context fields and why they should not automatically become scored dimensions:

| Field | Why Informational Only |
|---|---|
| Seller `target_sale_timeline` (free text) | Free-text; no structured mapping. Contextually informs `transaction_pace` but is not itself a structured input. |
| Buyer `availability_windows` (free text) | Scheduling logistics; no compatibility trait equivalent. Useful for agent awareness, not scoring. |
| Tenant `preferred_contact_method` (time-of-day) | Time-of-day preference (Morning, Afternoon, Evening, Anytime). Not a representation compatibility signal. Retained as informational context. Explicitly excluded from trait scoring in Phase B. |
| Seller `open_house_preference` | Tactical marketing preference; not a representation compatibility trait. Relevant for consumer awareness but not agent-consumer compatibility scoring. |
| Seller `showing_availability` | Operational scheduling logistics; no compatibility trait. |
| Seller `additional_decision_makers` | Contextual note about who else is involved; no structured trait. |
| Buyer `deal_breakers` (free text) | Useful agent awareness context; too unstructured and situation-specific for scoring. Partially informs `representation_philosophy` contextually. |
| All `additional_compatibility_notes` / `additional_representation_notes` (textareas) | Free-text narrative fields exist in all four roles. They provide rich context for agent self-description and consumer expectation-setting. They should feed the AI explanation layer in Phase E but should not be auto-scored. |
| `past_agent_experience` + `what_did_not_work_before` | Useful context for why the consumer has specific preferences; not itself a compatibility scoring dimension. |
| Agent narrative bio or philosophy statement | A free-text narrative field in the agent response is valuable for AI explanation and consumer reading, but it should not be structured for automatic scoring. |
| Agent `concerns_or_barriers` equivalent | Any agent-side field collecting situation-specific concerns is informational context, not a scored dimension. |

### 9.3 AI Explanation and Informational Context

Informational context fields may assist the AI explanation layer in Phase E without contributing to scored dimensions. For example: a consumer's narrative note that they had a negative experience with an agent who was unresponsive could inform an AI explanation that highlights the selected agent's stated same-day response commitment — without that narrative becoming a scoring input. The distinction between "scores this" and "informs the explanation of this" is important and must be preserved through Phase D and Phase E design.

---

## 10. Traits Excluded From Agent Compatibility

The following categories of data must be permanently excluded from the agent-side compatibility response system. These exclusions are not optional and are restated from Phase A Section 13 and Phase B Section 10.

### 10.1 Compensation, Commission, and Fee Fields

Agent response fields must not include any question related to the agent's compensation expectations, commission structure, fee preferences, or referral percentage. Specific excluded fields include but are not limited to: `purchase_fee_type`, `purchase_fee_percentage`, `purchase_fee_flat`, `lease_fee_type`, `commission_structure`, `commission_structure_type`, `referral_percentage`, `retainer_fee_option`, `early_termination_fee_option`, and all their sub-variants.

Compensation compatibility, if ever measured, belongs in the existing `financial_match_score` or `terms_match_score` dimensions. It must never appear in the representation compatibility layer.

### 10.2 Services Lists and Scope of Work

Fields describing the agent's service deliverables — `services`, `other_services`, `flat_fee_services`, and any equivalent — describe what tactical tasks the agent will perform. These are already scored through existing match score helpers. They must not be re-scored under the compatibility dimension.

### 10.3 Agency Agreement and Brokerage Relationship Terms

Fields describing the structure and duration of the agency contract — `agency_agreement_timeframe`, `brokerage_relationship`, `protection_period`, and equivalents — are legal agreement terms, not professional style compatibility dimensions. They are excluded.

### 10.4 Demographic and Protected-Class Data

**This exclusion is absolute and unconditional.** The following data must never be collected, stored, scored, or displayed under any compatibility field at any phase:

- Race, color, national origin
- Religion or religious affiliation
- Sex or gender identity
- Familial status (presence of children, pregnancy, etc.)
- Disability or medical condition
- Age
- Sexual orientation or marital status
- Source of income
- Lifestyle preferences that correlate with any of the above
- Neighborhood demographic composition or resident profile

### 10.5 Fair Housing-Sensitive Framing

Beyond legally protected classes, any question whose option set could produce systematically disparate outcomes along protected-class lines — even without intent — is prohibited. An option is Fair Housing-sensitive if a consumer choosing it would be predictably more likely to select or de-select agents who serve protected-class clients, or if an agent selecting it would be predictably advantaged or disadvantaged on the basis of the communities they serve.

### 10.6 Lifestyle Profiling

Questions that profile an agent's or consumer's personal lifestyle, values outside professional practice, political or social views, religious observance patterns, or family structure are prohibited. Compatibility is limited to professional representation style: how agent and consumer prefer to communicate, negotiate, make decisions, and collaborate within a real estate transaction.

### 10.7 Neighborhood and Demographic Clustering

No agent response field may ask about the agent's preferred client demographics, neighborhood demographic composition, or community characteristics. Questions such as "What types of neighborhoods do you specialize in?" or "What types of clients do you typically represent?" are prohibited if the option set could function as demographic filtering by any means.

---

## 11. Compatibility Visibility Rules

This section defines what each party may eventually see from the compatibility system, and what they should not see.

### 11.1 What Consumers May Eventually See

- **Compatibility summary:** A high-level indication of how well a given agent's responses align with the consumer's stated preferences, expressed as a percentage, score, or qualitative label (e.g., Strong Match, Good Alignment, Some Differences).
- **Trait-level alignment:** For each structured trait where the agent responded, whether the agent's response is aligned, partially aligned, or different from the consumer's preference — expressed in plain, non-judgmental language.
- **Plain-language explanation:** An AI-generated explanation (Phase E) that describes in plain language why a particular score was produced, citing specific trait comparisons (e.g., "You prefer weekly updates; this agent typically provides check-ins every few days").
- **Agent's representation style summary:** A consumer-facing summary of how the agent describes their own working style and priorities, drawn from the agent's structured responses and narrative fields. This is a presentation of the agent's self-description, not a platform evaluation.

Compatibility information presented to consumers is advisory only. It is one factor among many — including bid terms, services offered, agent credentials, and consumer judgment — in the decision to award a bid.

### 11.2 What Agents Should NOT See

- **Raw consumer psychological interpretation:** Agents should not receive a report stating that the consumer is "risk-averse" or "anxious" or "has trust issues with agents" based on the consumer's raw compatibility inputs.
- **Hidden compatibility scores assigned to them:** Agents should not receive a compatibility score that they cannot understand or contest. If compatibility scores are surfaced to agents at all, the methodology must be disclosed.
- **Protected or sensitive inferences:** No inference about the consumer's demographics, lifestyle, financial vulnerability, or personal circumstances derived from compatibility inputs should be surfaced to agents.
- **Consumer's raw preference data in ways the consumer did not intend to disclose:** A consumer's free-text notes about negative past agent experiences, for example, should not be shown to the agent bidding on their listing. Compatibility processing happens on the platform's side; raw consumer input is not a transparency window for agents.

### 11.3 Compatibility Is Advisory Only

The platform must clearly and consistently communicate to consumers that the compatibility system is an advisory signal, not a definitive recommendation. The system does not make decisions on the consumer's behalf. The system does not rank agents. The system does not endorse or disqualify any agent. It presents a data-informed perspective that the consumer is free to weigh as they see fit alongside all other factors in their decision.

---

## 12. Governance Rules

The following governance rules apply to the Professional Representation Compatibility system at every phase of design, implementation, and operation. They are restated in full from Phase A and Phase B and carry forward into Phase C and all subsequent phases.

**Rule 1 — AI Advisory Only.** The compatibility score is an advisory signal. It informs the consumer's evaluation; it does not decide on the consumer's behalf. No compatibility score, ranking, or recommendation produced by this system is autonomous.

**Rule 2 — No Hidden Weighting.** Every factor that contributes to a compatibility score must be disclosed to the consumer. No trait may be silently weighted more than another without the weighting methodology being visible. If weights are used, they must be documented in the `scoring_framework_version` field and disclosed to the consumer in plain language.

**Rule 3 — Explainability Required.** Every scored dimension must be explainable in plain language without reference to opaque algorithms. A consumer must be able to understand, for each trait, what their preference was, what the agent's response was, and what the comparison found. An agent must be able to understand what each of their responses implies about their working style.

**Rule 4 — No Scoring Implementation in This Document.** This document is a planning document. It does not specify scoring algorithms, match thresholds, weighting formulas, or compatibility calculation logic. Scoring design belongs to Phase D.

**Rule 5 — No Protected-Class Traits.** No trait may use, correlate with, or proxy for any protected-class characteristic under the Fair Housing Act, the Equal Credit Opportunity Act, or applicable state or local law. This applies to every phase: data collection, trait design, scoring logic, and display.

**Rule 6 — No Fair Housing-Sensitive Traits.** Beyond legally protected classes, any field that could produce discriminatory patterns in agent selection — even without intent — must be excluded. A field is Fair Housing-sensitive if its use would predictably disadvantage agents who serve protected-class consumers or favor agents who do not.

**Rule 7 — No Demographic Profiling.** The system must not create or maintain profiles of agents or consumers based on demographic characteristics. Strategic context signals (e.g., type of transaction goal) are not demographic filters.

**Rule 8 — Professional Representation Compatibility Scope Only.** The 12 normalized traits defined in Phase B and specified in this document constitute the complete and bounded scope of what this system measures. The system measures how an agent and consumer prefer to work together as professionals. It does not measure property characteristics, financial qualifications, neighborhood preferences, agent production volume, brokerage affiliation, or any factor outside the 12 defined traits.

**Rule 9 — Consumer Data Confidentiality.** A consumer's compatibility preferences are private inputs. They may be used to compute a compatibility score visible to the consumer, but the raw preference data must not be displayed to agents in a way that reveals information the consumer did not intend to disclose.

**Rule 10 — Iterative and Versioned.** The scoring framework must support versioning consistent with the existing `scoring_framework_version` architecture. Any change to trait definitions, option sets, or weighting logic must increment the framework version so that scores computed under different frameworks are not compared directly.

**Rule 11 — Human Decision Authority.** The compatibility system is a decision-support tool. All consequential decisions — including which agent to hire — remain with the human parties. No algorithmic output may substitute for human judgment.

**Rule 12 — Auditability Required.** The agent's response, the consumer's preference, and the comparison result must all be inspectable by platform administrators for compliance review. No scoring input may be hidden from audit.

**Rule 13 — Fair Housing Review Required.** Before any phase of this system is released to production, a Fair Housing compliance review must be completed by a qualified reviewer. The governance rules stated here are a precondition for that review, not a substitute for it.

---

## 13. Fair Housing Safeguards

This section is a dedicated Fair Housing compliance framework for the BidYourAgent Professional Representation Compatibility system. It applies at every phase from Phase C forward and must be referenced during Phase D and Phase E design.

### 13.1 Prohibited Traits — Absolute Prohibitions

The following characteristics must never appear in any compatibility field, option label, option value, scoring dimension, or AI-generated explanation at any phase:

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

### 13.2 Prohibited Inferences

The system must not draw, store, or display any inference about a consumer or agent based on characteristics that correlate with protected classes, even if the inference is derived from seemingly neutral inputs. Prohibited inferences include:

- Any inference about a person's religion from their name, language, neighborhood, or stated cultural practices.
- Any inference about a person's national origin from their language preference or neighborhood location.
- Any inference about familial status from timeline urgency (e.g., assuming a tenant with an urgent timeline has a family circumstance).
- Any inference about disability or accessibility needs beyond what the consumer explicitly discloses for their own property search.

### 13.3 Proxy-Risk Warning

A proxy field is a field that does not ask about a protected characteristic directly but whose options or scoring correlate with a protected characteristic in practice. The following field categories carry elevated proxy risk and must be evaluated for proxy risk before any scoring use:

- `property_strategy_fit` — "transaction type" options may correlate with consumer economic status or national origin in some markets.
- `risk_tolerance` — "strict screening" options for Landlords could, if scored, disadvantage agents who serve tenants with non-standard financial backgrounds, who are disproportionately members of protected classes.
- `representation_priorities` — role-specific priority options may correlate with transaction types associated with specific demographic groups in some markets.

No proxy risk analysis sufficient for production release is performed in this document. This analysis is required as part of the Fair Housing compliance review before any scoring use of these fields.

### 13.4 Demographic Clustering Prohibition

The system must not be used to cluster consumers or agents by demographic group, explicitly or implicitly. Specific prohibitions:

- The system must not recommend agents to consumers on the basis of any demographic similarity between the agent and the consumer.
- The system must not penalize agents for serving geographically or demographically diverse client bases.
- The system must not produce compatibility scores that are systematically higher for agent-consumer pairs that share demographic characteristics.
- No "community fit" or "cultural alignment" dimension may be added to the compatibility system under any label.

### 13.5 Neighborhood Demographic Scoring Prohibition

The system must not incorporate neighborhood demographic data — including census demographic composition, neighborhood racial composition, language prevalence, income distribution by race or ethnicity, or any proxy for the same — into any compatibility dimension, scoring formula, or AI explanation. This prohibition covers both direct use and indirect use through third-party data sources.

### 13.6 Explainability as a Fair Housing Requirement

Explainability is not only a user experience goal — it is a Fair Housing safeguard. An opaque scoring algorithm that produces systematically disparate outcomes cannot be detected, audited, or corrected. Every compatibility score must be explainable in terms of specific, documented, non-protected trait comparisons. If an explanation cannot be given, the score cannot be produced.

### 13.7 Compliance Review Requirement

A Fair Housing compliance review by a qualified reviewer is required before any element of the compatibility system — fields, scoring, display, AI explanation — is made available in a production environment. This review must:

- Assess all option labels and values for discriminatory potential.
- Evaluate all proposed scoring dimensions for disparate impact risk.
- Review all AI explanation prompt templates for Fair Housing-compliant language.
- Certify that no prohibited trait, prohibited inference, or proxy field has been incorporated.

This planning document does not constitute a Fair Housing review and does not authorize production use of any system element described herein.

---

## 14. Future Phase Dependencies

Phase C is complete when this document is finalized. The following phases remain to be designed and implemented in future work. No implementation detail for any of these phases is specified here.

### Phase D — Compatibility Scoring Framework

Phase D will design the scoring logic that compares normalized consumer traits against the agent-side responses defined in Phase C to produce a `representation_compatibility_score`. Phase D will determine: the scoring methodology for each of the 12 traits, how to handle missing or unanswered fields on either side, how partial matches are treated, how the score is integrated into the `listing_compatibility_scores` table (which requires a new column and a new `scoring_framework_version`), and whether any traits from the 12 are excluded from scoring in favor of informational-only status. Phase D requires this Phase C document as its primary input.

### Phase E — AI Explanation Layer

Phase E will design the AI-generated plain-language compatibility explanation shown to consumers on the bid card. Phase E will define: the prompt structure and inputs, Fair Housing compliance guardrails for AI-generated text, tone guidelines, how the AI explanation distinguishes between scored and informational-only fields, and the technical mechanism for surfacing the explanation in the UI. Phase E requires the scored output from Phase D and the informational context field set from Phase C.

### Phase F — Consumer Comparison UI

Phase F will design the consumer-facing interface that displays each bidding agent's representation compatibility score and explanation alongside their existing match scores (physical, financial, location, terms). Phase F will define: how scores are presented (numerical, visual, qualitative), how consumers can explore the reasoning behind a compatibility rating, whether consumers can see a side-by-side trait comparison view, and how compatibility information is integrated into the existing bid card layout without overweighting it relative to other bid factors. Phase F requires the scoring infrastructure from Phase D and the explanation layer from Phase E.

---

## 15. Implementation Deferred Items

The following items are explicitly deferred to future implementation phases. None of the items listed here may be built, prototyped, or partially implemented based on this Phase C document alone.

| Deferred Item | Why Deferred |
|---|---|
| Database schema changes | Phase D scope — requires scoring column design and framework versioning |
| Migration files | Phase D scope — no schema changes occur in Phase C |
| Blade tab markup for agent bid forms | Future implementation phase — no UI changes occur in Phase C |
| Livewire component additions or modifications | Future implementation phase — no code changes occur in Phase C |
| Validation rules for agent response fields | Future implementation phase — requires Livewire component design |
| EAV meta save/load plumbing for agent responses | Future implementation phase — requires Blade and Livewire design |
| Scoring formulas and trait weighting logic | Phase D scope |
| Match threshold definitions | Phase D scope |
| `representation_compatibility_score` column | Phase D scope — requires schema design and migration |
| `scoring_framework_version` increment | Phase D scope — no version change occurs without scoring implementation |
| AI prompt design for compatibility explanation | Phase E scope |
| AI explanation guardrails and Fair Housing filters | Phase E scope |
| Consumer-facing compatibility score display | Phase F scope |
| Bid card compatibility UI integration | Phase F scope |
| Side-by-side trait comparison view | Phase F scope |
| Compatibility analytics or reporting dashboards | Post-Phase F scope |
| Compatibility score filtering or sorting tools | Phase F scope |
| Agent-facing compatibility score visibility | Phase D or F scope — pending governance decision |

---

## 16. Phase D Readiness Checklist

The following conditions must all be true before Phase D (compatibility scoring framework design) can begin. This checklist reflects the Phase C deliverables.

| Condition | Status |
|---|---|
| Normalized traits complete — all 12 traits defined with definitions, boundaries, and agent-side response design | Done — Sections 6 and 8 |
| Option crosswalks complete — unified option sets proposed for all 7 multi-role traits using Phase B crosswalks | Done — Section 7 |
| Unified response concepts complete — all 12 agent response fields named, typed, and described | Done — Section 8 |
| Informational-only fields identified — explicitly separated from scored dimensions | Done — Section 9 |
| Role-neutral architecture defined — primary fields are role-neutral; role-scoped exceptions documented | Done — Section 4.3 |
| Storage path defined — `compatibility_preferences.agent_response.*` confirmed as target path | Done — Section 4.1 |
| Sub-section structure defined — 7 logical sub-sections mapped to normalized traits | Done — Section 5 |
| Trait-by-trait response design complete — field name, purpose, select type, and score-eligibility documented for all 12 traits | Done — Section 8 |
| Exclusions documented — compensation, services, legal terms, protected-class data, and lifestyle profiling all explicitly excluded | Done — Section 10 |
| Governance rules restated in full | Done — Section 12 |
| Fair Housing safeguards documented — prohibited traits, inferences, proxy risk, demographic clustering, neighborhood scoring, and compliance review requirement all addressed | Done — Section 13 |
| No hidden weighting introduced — no scoring formula, threshold, or weight is specified in this document | Confirmed — this document contains no scoring formulas |
| No scoring implemented — this document is a planning document only | Confirmed — no code, schema, or implementation detail is provided |
| Compatibility visibility rules defined — what consumers may see, what agents should not see, advisory-only framing | Done — Section 11 |
| Future phase dependencies documented — Phase D, E, F scope described without implementation detail | Done — Section 14 |
| Deferred items explicitly enumerated | Done — Section 15 |
| Fair Housing compliance review planned | Pending — required before any element enters production use |

**Phase D can begin.** All agent-side response design prerequisites are satisfied. The Phase D deliverable is the scoring framework: for each of the 12 normalized traits, the algorithm that compares a consumer's preference to an agent's response and produces a `representation_compatibility_score`, together with the `listing_compatibility_scores` schema change and a new `scoring_framework_version` identifier.

---

*End of document. This document is read-only. No code, schema, migration, route, controller, Blade, Livewire, config, or database file was modified to produce it.*
