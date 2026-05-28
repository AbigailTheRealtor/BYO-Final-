# BidYourAgent — Professional Representation Compatibility: Normalized Trait Design

**Document Status:** Read-only design document. No code, schema, migration, route, controller, Blade, Livewire, config, or database file was modified to produce this document.
**Document Date:** 2026-05-28
**Phase:** B — Normalized Trait Design
**Preceding Phase:** A — Compatibility Field Audit (`docs/BIDYOURAGENT_COMPATIBILITY_AUDIT.md`)
**Succeeding Phase:** C — Agent-Side Field Design (not yet started)

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Source Audit Reference](#2-source-audit-reference)
3. [Normalized Trait List](#3-normalized-trait-list)
4. [Trait Definitions](#4-trait-definitions)
   - 4.1 `communication_channel`
   - 4.2 `communication_frequency`
   - 4.3 `responsiveness_expectation`
   - 4.4 `negotiation_style`
   - 4.5 `guidance_level`
   - 4.6 `decision_making_style`
   - 4.7 `transaction_pace`
   - 4.8 `risk_tolerance`
   - 4.9 `collaboration_style`
   - 4.10 `representation_priorities`
   - 4.11 `representation_philosophy`
   - 4.12 `property_strategy_fit`
5. [Role-by-Role Raw Field Mapping](#5-role-by-role-raw-field-mapping)
6. [Option Value Crosswalks](#6-option-value-crosswalks)
7. [Known Naming Inconsistencies](#7-known-naming-inconsistencies)
8. [Fields Requiring Normalization](#8-fields-requiring-normalization)
9. [Missing Agent-Side Counterparts](#9-missing-agent-side-counterparts)
10. [Traits Excluded From Compatibility](#10-traits-excluded-from-compatibility)
11. [Governance Rules](#11-governance-rules)
12. [Phase C Readiness Checklist](#12-phase-c-readiness-checklist)

---

## 1. Executive Summary

This document is the Phase B deliverable of the BidYourAgent compatibility system. Its purpose is to take the raw consumer-side compatibility fields catalogued in the Phase A audit and map them into a stable, role-neutral normalized trait layer called **Professional Representation Compatibility**.

Phase A established a clear and troubling baseline: all four consumer-side listing flows collect between 16 and 22 compatibility fields each, while all four agent bid Livewire components collect zero matching fields. The consumer data is being gathered and persisted, but it drives nothing — no agent-side mirror exists, no normalized representation exists, and no scoring column for representation compatibility exists in the `listing_compatibility_scores` table.

Phase A also surfaced four specific naming inconsistencies in the consumer-side raw data. These inconsistencies mean that the same key name stores semantically different data depending on which role's listing is being read. Before any scoring, matching, or agent-side field design can proceed, these inconsistencies must be authoritatively resolved at the normalized layer. This document resolves them.

The central deliverable of Phase B is the definition of **12 normalized traits** that form the Professional Representation Compatibility layer. Each trait:

- Has a single, unambiguous definition that applies identically across all four roles.
- Is mapped to the specific raw consumer-side sub-keys that feed it, per role.
- Identifies where a role has no direct equivalent raw field for that trait.
- Confirms the total absence of any agent-side counterpart across all four bid components.
- Is free of protected-class data and Fair Housing-sensitive content.

These 12 traits represent the complete, bounded scope of Professional Representation Compatibility. They measure how an agent and consumer prefer to work together — their communication patterns, negotiation postures, decision-making approaches, and representation philosophies. They do not measure compensation terms, service scope, legal agreement structure, property characteristics, or any demographic attribute.

This document is the required prerequisite for Phase C (agent-side field design). Phase C cannot produce a coherent, role-consistent agent response field set without the normalized trait definitions, raw field crosswalks, option value alignments, and naming inconsistency resolutions contained here.

---

## 2. Source Audit Reference

**Authoritative Phase A source:** `docs/BIDYOURAGENT_COMPATIBILITY_AUDIT.md`

### 2.1 Phase A Field Counts Per Role

| Role | Total Compatibility Fields | Required Fields | Optional Fields |
|---|---|---|---|
| Seller | 22 | 5 | 17 |
| Buyer | 17 | 5 | 12 |
| Landlord | 16 | 4 | 12 |
| Tenant | 20 | 4 | 16 |
| **Total** | **75** | **18** | **57** |

These counts include companion "Other" free-text inputs that are conditional on a parent select value. Companion inputs are not counted as independent fields for trait-mapping purposes; they extend the parent field they accompany.

### 2.2 Phase A Central Finding Restated

> **All four consumer-side listing flows collect between 16 and 22 compatibility fields each, while all four agent bid Livewire components collect zero matching fields.**

Specifically: the four agent bid components (`SellerAgentAuctionBid`, `BuyerAgentAuctionBid`, `LandlordAgentAuctionBid`, `TenantAgentAuctionBid`) declare no `compatibility_preferences` property, no `compatibility_response` property, and no field that corresponds to any consumer-side compatibility question. This was confirmed by full component inspection in Phase A, Section 11.

The `listing_compatibility_scores` table contains four score dimensions (`physical_match_score`, `financial_match_score`, `location_match_score`, `terms_match_score`) but has no `representation_compatibility_score` column. No scoring infrastructure for representation compatibility exists.

### 2.3 Phase A Naming Inconsistencies Identified

Phase A identified four naming inconsistencies in the consumer-side raw data. All four are fully resolved in Section 7 of this document. In brief:

1. Seller/Buyer `communication_style` stores **frequency** data; Landlord/Tenant `communication_style` stores **channel/method** data.
2. Landlord `preferred_contact_method` stores contact **frequency** options (not method options).
3. Tenant `preferred_contact_method` stores preferred contact **time of day** options (not method options).
4. Buyer `communication_frequency` stores meeting/showing **format preference** (not update frequency).

---

## 3. Normalized Trait List

The following 12 normalized traits constitute the complete Professional Representation Compatibility layer. Each trait is role-neutral — it applies identically to all four consumer roles (Seller, Buyer, Landlord, Tenant) and will have a corresponding agent-side response field in Phase C.

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

---

## 4. Trait Definitions

---

### 4.1 `communication_channel`

**Definition:** The medium or set of mediums through which the consumer prefers to exchange information with their agent during the course of the transaction.

**What it measures:** The consumer's practical channel preference — whether they want phone calls, texts, emails, video calls, in-person meetings, platform messaging, or some combination. This trait determines how an agent should reach the consumer and how the consumer plans to initiate contact.

**What it does NOT measure:** How often contact happens (that is `communication_frequency`), how quickly the agent responds (that is `responsiveness_expectation`), or the consumer's negotiation posture, decision style, or any other behavioral trait.

**Seller raw field(s):** `preferred_contact_method` (multi-select: Phone Call, Text/SMS, Email, Video Call, In-Person Meeting)

**Buyer raw field(s):** `preferred_contact_method` (multi-select: Phone Call, Text Message, Email, Video Call, In-Person Meetings, Any Method)

**Landlord raw field(s):** `communication_style` (single-select: Email Only, Phone Calls Preferred, Text / SMS Preferred, Video Calls Preferred, In-Person Meetings, Platform Messaging, Flexible / Any Method)

> Note: Landlord `communication_style` stores channel/method data, not frequency data. It feeds `communication_channel`, not `communication_frequency`. See Section 7 for the full inconsistency resolution.

**Tenant raw field(s):** `communication_style` (single-select: Email, Phone calls, Text / SMS, Video calls, In-person meetings, Other)

> Note: Tenant `communication_style` stores channel/method data identically to the Landlord pattern. It feeds `communication_channel`, not `communication_frequency`.

**Agent-side counterpart status:** No — absent from all four bid components.

---

### 4.2 `communication_frequency`

**Definition:** The cadence at which the consumer expects their agent to proactively initiate contact and provide status updates, without the consumer needing to ask.

**What it measures:** The consumer's update cadence expectation — whether they want daily contact, contact every few days, weekly check-ins, updates only at major milestones, or contact only when they reach out. This trait tells the agent how often to touch base proactively.

**What it does NOT measure:** The channel used to communicate (that is `communication_channel`), the response time for inbound messages (that is `responsiveness_expectation`), or the consumer's showing and meeting format preference (which is stored in Buyer `communication_frequency` but feeds `collaboration_style` — see Section 7).

**Seller raw field(s):** `communication_style` (single-select: Frequent & Proactive, As-Needed Updates, Available On-Demand, Structured Check-Ins)

> Note: Seller `communication_style` stores frequency-oriented options despite the key name implying style. It feeds `communication_frequency`. See Section 7.

**Buyer raw field(s):** `communication_style` (single-select: Frequent Updates (Daily), Regular Updates (Every Few Days), Weekly Updates, Only When Necessary, As-Needed / On-Demand)

> Note: Buyer `communication_style` stores frequency-oriented options, consistent with the Seller pattern. It feeds `communication_frequency`. See Section 7.

**Landlord raw field(s):** `preferred_contact_method` (single-select: Daily Updates, Every Few Days, Weekly Check-Ins, Only Major Milestones, Only When I Ask)

> Note: Landlord `preferred_contact_method` stores contact frequency options despite the key name implying contact method. It feeds `communication_frequency`. See Section 7.

**Tenant raw field(s):** `contact_frequency` (single-select: Daily, Every few days, Weekly, Only on major updates, As needed)

> Note: Tenant uses the correctly named `contact_frequency` key. No normalization renaming is needed for this role's data.

**Agent-side counterpart status:** No — absent from all four bid components.

---

### 4.3 `responsiveness_expectation`

**Definition:** The maximum acceptable elapsed time between the consumer sending a message or placing a call to their agent and receiving a substantive response.

**What it measures:** The consumer's inbound response time standard — whether they expect a callback within one hour, within a few hours, same day, or by the next business day. This trait defines the speed floor the agent must meet in order to satisfy the consumer's working style.

**What it does NOT measure:** How often the agent proactively initiates contact (that is `communication_frequency`), which channel the agent uses (that is `communication_channel`), or the consumer's transaction timeline (that is `transaction_pace`).

**Seller raw field(s):** `response_time_expectation` (single-select: Within 1 Hour, Within a Few Hours, Same Day, Next Business Day)

**Buyer raw field(s):** No direct equivalent field. The Buyer compatibility tab does not collect a standalone response time expectation.

**Landlord raw field(s):** `response_time_expectation` (single-select: Within 1 Hour, Within a Few Hours, Same Business Day, Within 24 Hours, Within 48 Hours, Flexible)

**Tenant raw field(s):** No direct equivalent field. Tenant `preferred_contact_method` stores time-of-day preference, not response time expectation — it does not feed this trait (see Section 7). The Tenant compatibility tab does not collect a standalone response time standard.

**Agent-side counterpart status:** No — absent from all four bid components.

---

### 4.4 `negotiation_style`

**Definition:** The strategic posture and philosophical approach the consumer brings to negotiation situations during their transaction — how aggressively, collaboratively, or cautiously they want their interests advanced.

**What it measures:** The consumer's preferred negotiation mode — whether they want their agent to push hard for maximum advantage, seek balanced and fair outcomes, prioritize speed or certainty over maximum gain, or take a fully collaborative approach that also considers the other party. This trait tells the agent whether their negotiation approach is compatible with the consumer's expectations.

**What it does NOT measure:** What specific items the consumer is willing to negotiate on (Seller `willing_to_negotiate_on`, which feeds `property_strategy_fit`), how firm the consumer is on price (Seller `firm_on_price`, which also feeds `property_strategy_fit`), or the consumer's risk tolerance or transaction pace.

**Seller raw field(s):** `negotiation_style` (single-select: Aggressive — Push for Maximum Profit, Balanced — Fair & Reasonable, Flexible — Prioritize Quick Sale, Collaborative — Seller & Buyer Both Win)

**Buyer raw field(s):** `negotiation_style` (single-select: Aggressive Negotiator, Firm but Fair, Collaborative, Offer Full Price to Win, Guided by Agent)

**Landlord raw field(s):** `negotiation_style` (single-select: Firm on Terms, Open to Negotiation, Collaborative Win-Win, Market-Rate Anchored, Flexible Case-by-Case)

**Tenant raw field(s):** `negotiation_style` (single-select: Aggressive – push hard for the best deal, Collaborative – find mutually beneficial terms, Conservative – prioritize securing a property over terms, Flexible – adapt based on property and market)

**Agent-side counterpart status:** No — absent from all four bid components.

---

### 4.5 `guidance_level`

**Definition:** The degree to which the consumer wants their agent to lead, manage, and decide on their behalf versus keeping the consumer actively involved at every step.

**What it measures:** The consumer's delegation preference — ranging from fully hands-off (the agent manages everything) to highly involved (the consumer participates in every decision). This trait tells the agent how much autonomy they will have and how much they need to consult the consumer before acting.

**What it does NOT measure:** The consumer's decision-making speed or style (that is `decision_making_style`), how proactively the agent should communicate (that is `communication_frequency`), or the consumer's preferred operating mode for the agent's personality (that is `collaboration_style`).

**Seller raw field(s):** `involvement_level` (single-select: Very Involved — Part of every decision, Moderately Involved — Major steps only, Mostly Hands-Off — I trust my agent)

**Buyer raw field(s):** `support_level` (single-select: Minimal – Self-Sufficient, Moderate – Key Touchpoints, High – Guided Throughout, Full White-Glove Service)

**Landlord raw field(s):** `property_management_involvement` (single-select: Hands-Off (Agent Manages All), Minimal Involvement, Occasional Check-Ins, Actively Involved, Self-Manage After Placement)

**Tenant raw field(s):** `desired_level_of_agent_involvement` (single-select: Fully Delegated – Agent manages everything, Mostly Delegated – Agent leads, I approve key decisions, Collaborative – We work together equally, Mostly Hands-On – I lead, Agent supports, Other)

**Agent-side counterpart status:** No — absent from all four bid components.

---

### 4.6 `decision_making_style`

**Definition:** The process by which the consumer reaches decisions — how quickly they commit, how much deliberation they require, and what inputs (agent guidance, personal research, collaborative discussion) most influence their choices.

**What it measures:** The consumer's cognitive and behavioral decision pattern — whether they decide quickly and independently, take time to deliberate, rely heavily on data and research, defer to agent guidance, or involve other household or business partners. This trait tells the agent how to pace recommendations and how to present options.

**What it does NOT measure:** How involved the consumer wants to be in managing the transaction (that is `guidance_level`), how quickly the transaction itself should move (that is `transaction_pace`), or the consumer's negotiation posture (that is `negotiation_style`).

**Seller raw field(s):** `decision_making_style` (single-select: Independent — I Decide Quickly, Collaborative — I Value Agent Input, Cautious — I Need Time to Think, Data-Driven — Show Me the Numbers)

**Buyer raw field(s):** `decision_making_style` (single-select: Quick Decisions, Careful & Deliberate, Collaborative with Agent, Research-Driven, Flexible / Situational)

**Landlord raw field(s):** No direct equivalent field. The Landlord compatibility tab does not collect a standalone decision-making style question.

**Tenant raw field(s):** `decision_making_style` (single-select: Quick – ready to commit fast, Deliberate – need time to consider options, Research-driven – want all facts before deciding, Collaborative – involve family / partner in decisions)

**Agent-side counterpart status:** No — absent from all four bid components.

---

### 4.7 `transaction_pace`

**Definition:** The consumer's sensitivity to time pressure in the transaction — how urgently they need to complete the deal, how firm their target date is, and how they expect the agent to respond to timeline constraints.

**What it measures:** The consumer's schedule urgency and timeline rigidity — whether their deadline is immovable or flexible, whether they are operating under an immediate need or exploring at leisure, and what tradeoffs they are willing to make (e.g., accepting a lower price to close faster). This trait tells the agent whether they need to operate with urgency and whether the consumer's timeline is compatible with how the agent typically works.

**What it does NOT measure:** What specific timeline the consumer has in mind (free-text fields like Seller `target_sale_timeline` are informational context, not a compatibility dimension), the consumer's risk tolerance, or their negotiation style.

**Seller raw field(s):** `flexibility_on_timeline` (single-select: Very Flexible, Somewhat Flexible, Firm on Timeline)

**Buyer raw field(s):** `timeline_flexibility` (single-select: Very Flexible, Somewhat Flexible, Limited Flexibility, Strict Timeline)

**Landlord raw field(s):** No direct equivalent field. The Landlord compatibility tab does not collect a standalone timeline flexibility question.

**Tenant raw field(s):** `timeline_urgency` (single-select: Immediate (Within 2 Weeks), Within 30 Days, 1–2 Months, 2–3 Months, 3–6 Months, 6+ Months, Exploring Options Only, Other)

> Note: Tenant `timeline_urgency` captures absolute urgency rather than flexibility framing, but it feeds the same normalized trait. Phase C agent-side design should produce an option set that is compatible with all four framings.

**Agent-side counterpart status:** No — absent from all four bid components.

---

### 4.8 `risk_tolerance`

**Definition:** The consumer's appetite for transactional and financial risk — how willing they are to accept uncertainty, waive protections, or work with situations that fall outside standard criteria in pursuit of their goal.

**What it measures:** The consumer's risk posture — whether they are conservative and protective, moderate and standards-bound, or flexible and willing to stretch beyond typical safeguards. For buyers, this maps to willingness to waive contingencies or compete in multiple-offer situations. For landlords, it maps to screening strictness and openness to tenants with non-standard financial profiles.

**What it does NOT measure:** The consumer's negotiation style (that is `negotiation_style`), their timeline sensitivity (that is `transaction_pace`), or their decision-making speed (that is `decision_making_style`).

**Seller raw field(s):** No direct equivalent field. The Seller compatibility tab does not collect a standalone risk tolerance question. Seller `firm_on_price` and `willing_to_negotiate_on` capture related financial posture but feed `property_strategy_fit`, not this trait.

**Buyer raw field(s):** `risk_tolerance` (single-select: Very Conservative, Conservative, Moderate, Aggressive, Very Aggressive)

**Landlord raw field(s):** `risk_tolerance` (single-select: Low – Strict Screening Only, Moderate – Standard Criteria, Flexible – Case-by-Case, High – Willing to Work With Most Tenants)

**Tenant raw field(s):** No direct equivalent field. The Tenant compatibility tab does not collect a standalone risk tolerance question. Tenant `budget_flexibility` captures related financial posture but feeds `property_strategy_fit`, not this trait.

**Agent-side counterpart status:** No — absent from all four bid components.

---

### 4.9 `collaboration_style`

**Definition:** The overall operating mode and interpersonal style the consumer wants their agent to embody — the personality, initiative level, and interaction model that makes the consumer feel well-served.

**What it measures:** The consumer's preferred agent persona — whether they want an agent who acts without being asked (proactive and initiative-driven), one who explains and advises (consultative), one who is simply available when needed (responsive/on-demand), one who emphasizes thoroughness and process (detail-focused), one who takes a concierge approach (full-service), or a data and tech-forward style. This trait tells the agent whether their natural working personality fits the consumer's expectations.

**What it does NOT measure:** How often the agent communicates (that is `communication_frequency`), what tasks the agent should prioritize (that is `representation_priorities`), or how involved the consumer wants to be in decisions (that is `guidance_level`).

**Seller raw field(s):** `preferred_agent_working_style` (single-select: Proactive & Takes Initiative, Consultative & Guides Me, Responsive & Available, Process-Oriented & Detail-Focused)

**Buyer raw field(s):** `preferred_agent_working_style` (single-select: Highly Proactive, Responsive Partner, Advisor / Consultant, Full-Service Concierge, Hands-Off Facilitator, Other)

**Landlord raw field(s):** `preferred_agent_working_style` (single-select: Proactive & Assertive, Consultative & Advisory, Data-Driven & Analytical, Relationship-Focused, Tech-Forward & Efficient, Traditional & Personalized)

**Tenant raw field(s):** `preferred_agent_working_style` (single-select: Highly proactive – send regular updates without prompting, Collaborative – frequent check-ins and joint decisions, Efficient – contact me only when needed, Full service – handle everything and keep me informed)

> Additional contributing field — Buyer only: `communication_frequency` (stored key name) captures the consumer's showing/meeting format preference (In-Person Only, Virtual Tours Accepted, Agent Pre-Screens for Me, Flexible / No Preference). Despite the misleading key name, this behavioral preference about how the agent should arrange property viewings maps most naturally to `collaboration_style` rather than `communication_frequency`. See Section 7 for the full inconsistency resolution.

**Agent-side counterpart status:** No — absent from all four bid components.

---

### 4.10 `representation_priorities`

**Definition:** The specific task categories, agent capabilities, and outcome types the consumer most values and most wants their agent to deliver during the transaction.

**What it measures:** The consumer's prioritized list of what good representation means in practical terms — the activities and outcomes they want their agent to focus on. Options are role-specific in content (e.g., "Marketing Strategy" for Sellers vs. "Tenant Screening & Vetting" for Landlords) but the structural pattern is consistent: the consumer ranks what they care about most so the agent knows where to concentrate effort.

**What it does NOT measure:** The consumer's philosophy about what good representation looks like at a high level (that is `representation_philosophy`), the agent's compensation or service scope (which are transaction terms, not compatibility traits), or the consumer's negotiation or communication preferences.

**Seller raw field(s):** `representation_priorities` (multi-select: Market Expertise, Strong Negotiator, High Communication & Responsiveness, Local Connections & Network, Marketing Strategy, Staging / Presentation Expertise, Digital & Social Media Marketing, Transaction Management & Coordination)

**Buyer raw field(s):** `representation_priorities` (multi-select: Price Negotiation, Speed of Transaction, Finding Off-Market Properties, Contract Protection, Communication & Updates, Neighborhood Expertise, Investment Analysis, First-Time Buyer Guidance, Relocation Assistance, Other)

**Landlord raw field(s):** `representation_priorities` (multi-select: Tenant Screening & Vetting, Marketing & Advertising, Lease Negotiation, Legal & Lease Documentation, Showings & Open Houses, Market Pricing Guidance, Move-In Coordination, Ongoing Communication & Updates)

**Tenant raw field(s):** `representation_priorities` (multi-select: Neighborhood / location, Budget management, Speed of placement, Lease negotiation, Property condition, Pet-friendly options, Accessibility features, School district, Other)

**Agent-side counterpart status:** No — absent from all four bid components.

---

### 4.11 `representation_philosophy`

**Definition:** The consumer's overarching beliefs and values about what good professional real estate representation looks like — the non-task-specific principles and qualities they consider essential in any agent relationship.

**What it measures:** The consumer's values-level view of agent quality — honesty, transparency, patience, assertiveness, empathy, responsiveness, proactivity, tech-savviness, and similar qualities that define a trustworthy professional relationship rather than specific deliverables. This trait also includes the consumer's past experience context (positive, negative, or mixed prior agent relationships) that frames what they most need from a new agent.

**What it does NOT measure:** The specific tasks the consumer wants the agent to prioritize (that is `representation_priorities`), the consumer's preferred operating style for the agent (that is `collaboration_style`), or their negotiation posture.

**Seller raw field(s):** `qualities_most_important` (multi-select: Honesty & Transparency, Patience, Assertiveness, Attention to Detail, Tech-Savvy, Empathy, Proactivity); `past_agent_experience` (single-select: First Time, Positive, Negative, Mixed); `what_did_not_work_before` (textarea)

**Buyer raw field(s):** No single direct equivalent field capturing agent personal qualities. Buyer `deal_breakers` (free text) captures the consumer's absolute non-negotiables and may partially inform this trait, but it is not a structured philosophy field.

**Landlord raw field(s):** No direct equivalent field. The Landlord compatibility tab does not collect a standalone agent-quality or representation philosophy question.

**Tenant raw field(s):** `most_important_agent_traits` (multi-select: Honesty and Transparency, Strong Communication, Market Knowledge, Negotiation Skills, Responsiveness, Local Expertise, Client-Focused Approach, Technology-Savvy, Attention to Detail, Problem-Solving Ability, Professional Network, Other)

**Agent-side counterpart status:** No — absent from all four bid components.

---

### 4.12 `property_strategy_fit`

**Definition:** The consumer's primary strategic goal for the transaction and the specific context (property type, situation, and constraints) that must shape how the agent approaches the engagement.

**What it measures:** Whether the agent's experience, specialization, and strategic approach align with what the consumer is trying to accomplish — a maximum-price sale, a quick sale, a long-term stable tenancy, a primary residence purchase, an investment acquisition, or a short-term rental need. This trait tells the agent whether they have handled similar strategic goals and can credibly serve this consumer's primary outcome.

**What it does NOT measure:** The consumer's negotiation posture (that is `negotiation_style`), their timeline sensitivity (that is `transaction_pace`), their risk tolerance (that is `risk_tolerance`), or their communication preferences.

**Seller raw field(s):** `primary_transaction_goal` (single-select: Maximum Sale Price, Quick Sale, Minimal Disruption, Specific Closing Timeline, Other); `firm_on_price` (single-select: Yes, Somewhat, Flexible); `willing_to_negotiate_on` (multi-select: Price Reductions, Closing Costs, Repairs / Credits, Possession Date, etc.); `post_sale_plan` (single-select: Purchasing Another Property, Renting, Relocating, Moving to Family, Undecided)

**Buyer raw field(s):** `primary_transaction_goal` (single-select: Primary Residence, Vacation / Secondary Home, Investment Property, Fix & Flip, Commercial Use, Land Purchase, Other); `risk_tolerance` partially (Buyer risk tolerance also reflects strategic appetite in the context of competitive offer scenarios)

**Landlord raw field(s):** `primary_leasing_goal` (single-select: Maximize Monthly Rent, Long-Term Stable Tenant, Minimize Vacancy Time, High-Quality Tenant Profile, Build Portfolio Cash Flow, Property Appreciation & Upkeep, Other); `tenant_type_preference` (single-select); `lease_duration_preference` (single-select); `concessions_willingness` (single-select); `lease_terms_flexibility` (single-select)

**Tenant raw field(s):** `primary_rental_goal` (single-select: Find a long-term home, Temporary / short-term housing, Relocating for work, Downsizing, Upsizing, Investment search, Other); `budget_flexibility` (single-select: Fixed, Slightly flexible, Moderately flexible, Very flexible)

**Agent-side counterpart status:** No — absent from all four bid components.

---

## 5. Role-by-Role Raw Field Mapping

The following tables map every consumer-side raw sub-key to the normalized trait it feeds, including any renaming notes and semantic mismatch flags.

### 5.1 Seller Raw Field Mapping

| Raw Sub-key | Label | Maps To Normalized Trait | Notes |
|---|---|---|---|
| `communication_style` | Preferred Communication Style | `communication_frequency` | Key name implies style; options measure frequency. Semantic mismatch — see Section 7. |
| `preferred_contact_method` | Preferred Contact Method(s) | `communication_channel` | Key name and content are aligned for Seller. No mismatch. |
| `response_time_expectation` | Expected Agent Response Time | `responsiveness_expectation` | Direct, clean mapping. |
| `negotiation_style` | Negotiation Style | `negotiation_style` | Direct mapping. |
| `willing_to_negotiate_on` | Areas Willing to Negotiate On | `property_strategy_fit` | Describes transaction strategy context, not posture. |
| `firm_on_price` | Firm on Asking Price | `property_strategy_fit` | Transaction strategy context field. |
| `primary_transaction_goal` | Primary Transaction Goal | `property_strategy_fit` | Primary goal drives strategic fit. |
| `primary_transaction_goal_other` | *(companion)* | `property_strategy_fit` | Companion to `primary_transaction_goal`. |
| `target_sale_timeline` | Target Sale Timeline | *(informational context)* | Free-text; no structured mapping. Informs `transaction_pace` contextually. |
| `flexibility_on_timeline` | Timeline Flexibility | `transaction_pace` | Direct mapping. |
| `post_sale_plan` | Post-Sale Plans | `property_strategy_fit` | Contextual strategy field informing agent approach. |
| `representation_priorities` | Representation Priorities | `representation_priorities` | Direct mapping. |
| `qualities_most_important` | Agent Qualities Most Important | `representation_philosophy` | Values-level quality preference. |
| `past_agent_experience` | Past Experience with Agent | `representation_philosophy` | Experience context informs philosophy expectations. |
| `what_did_not_work_before` | What Did Not Work Before | `representation_philosophy` | Negative experience qualifier informs philosophy. |
| `decision_making_style` | Decision-Making Style | `decision_making_style` | Direct mapping. |
| `involvement_level` | Involvement Level | `guidance_level` | Direct mapping under normalized name. |
| `additional_decision_makers` | Decision Makers Involved | *(informational context)* | Free-text; no structured trait mapping. Informs `guidance_level` contextually. |
| `preferred_agent_working_style` | Preferred Agent Working Style | `collaboration_style` | Direct mapping under normalized name. |
| `showing_availability` | Showing Availability | *(informational context)* | Operational scheduling; no direct trait. Contextually relevant to `collaboration_style`. |
| `open_house_preference` | Open House Preference | *(informational context)* | Tactical preference; no direct trait. Contextually relevant to `property_strategy_fit`. |
| `additional_compatibility_notes` | Additional Compatibility Notes | *(informational context)* | Free-text narrative; no structured trait mapping. |

### 5.2 Buyer Raw Field Mapping

| Raw Sub-key | Label | Maps To Normalized Trait | Notes |
|---|---|---|---|
| `primary_transaction_goal` | Primary Transaction Goal | `property_strategy_fit` | Direct mapping. |
| `primary_transaction_goal_other` | *(companion)* | `property_strategy_fit` | Companion to `primary_transaction_goal`. |
| `representation_priorities` | Representation Priorities | `representation_priorities` | Direct mapping. |
| `representation_priorities_other` | *(companion)* | `representation_priorities` | Companion input for "Other" selection. |
| `risk_tolerance` | Risk Tolerance Level | `risk_tolerance` | Direct mapping. Also contributes contextually to `property_strategy_fit`. |
| `decision_making_style` | Decision-Making Style | `decision_making_style` | Direct mapping. |
| `timeline_flexibility` | Timeline Flexibility | `transaction_pace` | Direct mapping under normalized name. |
| `communication_style` | Communication Style | `communication_frequency` | Key name implies style; options measure frequency. Semantic mismatch — see Section 7. |
| `preferred_contact_method` | Preferred Contact Method(s) | `communication_channel` | Key name and content are aligned for Buyer. No mismatch. |
| `availability_windows` | Availability / Best Times to Reach | *(informational context)* | Free-text scheduling preference; no structured trait mapping. |
| `communication_frequency` | Meeting / Showing Preference | `collaboration_style` | Key name implies frequency; options measure showing format preference. Semantic mismatch — see Section 7. Maps to `collaboration_style` because it captures how the agent should conduct property viewings. |
| `negotiation_style` | Negotiation Style | `negotiation_style` | Direct mapping. |
| `preferred_agent_working_style` | Preferred Agent Working Style | `collaboration_style` | Direct mapping under normalized name. |
| `preferred_agent_working_style_other` | *(companion)* | `collaboration_style` | Companion to `preferred_agent_working_style`. |
| `support_level` | Expected Level of Agent Support | `guidance_level` | Direct mapping under normalized name. |
| `deal_breakers` | Non-Negotiable Requirements | `representation_philosophy` | Partially informs philosophy; primarily informational context. |
| `additional_compatibility_notes` | Additional Notes for Agent | *(informational context)* | Free-text narrative; no structured trait mapping. |

### 5.3 Landlord Raw Field Mapping

| Raw Sub-key | Label | Maps To Normalized Trait | Notes |
|---|---|---|---|
| `primary_leasing_goal` | Primary Leasing Goal | `property_strategy_fit` | Direct mapping. |
| `primary_leasing_goal_other` | *(companion)* | `property_strategy_fit` | Companion to `primary_leasing_goal`. |
| `tenant_type_preference` | Preferred Tenant Type | `property_strategy_fit` | Specialization context feeding strategic fit. |
| `tenant_type_preference_other` | *(companion)* | `property_strategy_fit` | Companion to `tenant_type_preference`. |
| `lease_duration_preference` | Preferred Lease Duration | `property_strategy_fit` | Transaction context field. |
| `property_management_involvement` | Level of Day-to-Day Involvement | `guidance_level` | Direct mapping under normalized name. |
| `communication_style` | Preferred Communication Style | `communication_channel` | Key name implies style; options are channel/method options. Semantic mismatch — see Section 7. Maps to `communication_channel`, not `communication_frequency`. |
| `preferred_contact_method` | Preferred Contact Frequency | `communication_frequency` | Key name implies method; options measure frequency. Semantic mismatch — see Section 7. Maps to `communication_frequency`, not `communication_channel`. |
| `response_time_expectation` | Expected Agent Response Time | `responsiveness_expectation` | Direct mapping. |
| `preferred_agent_working_style` | Preferred Agent Working Style | `collaboration_style` | Direct mapping under normalized name. |
| `negotiation_style` | Negotiation Style | `negotiation_style` | Direct mapping. |
| `representation_priorities` | Representation Priorities | `representation_priorities` | Direct mapping. |
| `risk_tolerance` | Risk Tolerance | `risk_tolerance` | Direct mapping. |
| `concessions_willingness` | Willingness to Offer Concessions | `property_strategy_fit` | Transaction strategy context field. |
| `lease_terms_flexibility` | Flexibility on Lease Terms | `property_strategy_fit` | Transaction strategy context field. |
| `additional_representation_notes` | Additional Notes | *(informational context)* | Free-text narrative; no structured trait mapping. |

### 5.4 Tenant Raw Field Mapping

| Raw Sub-key | Label | Maps To Normalized Trait | Notes |
|---|---|---|---|
| `primary_rental_goal` | Primary Rental Goal | `property_strategy_fit` | Direct mapping. |
| `other_primary_rental_goal` | *(companion)* | `property_strategy_fit` | Companion to `primary_rental_goal`. |
| `representation_priorities` | Representation Priorities | `representation_priorities` | Direct mapping. |
| `other_representation_priorities` | *(companion)* | `representation_priorities` | Companion input for "Other" selection. |
| `timeline_urgency` | Move-In Timeline Urgency | `transaction_pace` | Direct mapping. Urgency framing rather than flexibility framing — see trait definition note. |
| `other_timeline_urgency` | *(companion)* | `transaction_pace` | Companion to `timeline_urgency`. |
| `budget_flexibility` | Budget Flexibility | `property_strategy_fit` | Financial context field informing strategic fit. |
| `communication_style` | Preferred Communication Style | `communication_channel` | Key name implies style; options are channel/method options. Semantic mismatch — see Section 7. Maps to `communication_channel`. |
| `other_communication_style` | *(companion)* | `communication_channel` | Companion to `communication_style`. |
| `contact_frequency` | Preferred Contact Frequency | `communication_frequency` | Key name and content are aligned. No mismatch. Direct mapping. |
| `preferred_contact_method` | Preferred Contact Time of Day | *(informational context)* | Key name implies method; options are time-of-day values (Morning, Afternoon, Evening, Anytime). Does not map to any normalized trait as a structured compatibility dimension. See Section 7. |
| `preferred_agent_working_style` | Preferred Agent Working Style | `collaboration_style` | Direct mapping under normalized name. |
| `most_important_agent_traits` | Most Important Agent Traits | `representation_philosophy` | Direct mapping. |
| `other_most_important_agent_traits` | *(companion)* | `representation_philosophy` | Companion input for "Other" selection. |
| `desired_level_of_agent_involvement` | Desired Level of Agent Involvement | `guidance_level` | Direct mapping under normalized name. |
| `other_desired_level_of_agent_involvement` | *(companion)* | `guidance_level` | Companion to `desired_level_of_agent_involvement`. |
| `negotiation_style` | Negotiation Style | `negotiation_style` | Direct mapping. |
| `decision_making_style` | Decision-Making Style | `decision_making_style` | Direct mapping. |
| `concerns_or_barriers` | Concerns or Barriers | *(informational context)* | Free-text narrative; no structured trait mapping. |
| `additional_compatibility_notes` | Additional Compatibility Notes | *(informational context)* | Free-text narrative; no structured trait mapping. |

---

## 6. Option Value Crosswalks

The following tables show the per-role option sets for each trait that is present in multiple roles, aligned by semantic intent. These crosswalks are the primary input Phase C will use when designing unified agent-side option sets that must be answerable for all four consumer roles simultaneously.

### 6.1 `negotiation_style` Option Crosswalk

| Semantic Cluster | Seller Option | Buyer Option | Landlord Option | Tenant Option |
|---|---|---|---|---|
| Aggressive / Maximum advantage | Aggressive — Push for Maximum Profit | Aggressive Negotiator | Firm on Terms | Aggressive – push hard for the best deal |
| Balanced / Firm but fair | Balanced — Fair & Reasonable | Firm but Fair | Market-Rate Anchored | *(no direct equivalent)* |
| Collaborative / Win-win | Collaborative — Seller & Buyer Both Win | Collaborative | Collaborative Win-Win | Collaborative – find mutually beneficial terms |
| Flexible / Speed-priority | Flexible — Prioritize Quick Sale | *(no direct equivalent)* | Flexible Case-by-Case | Flexible – adapt based on property and market |
| Conservative / Secure-first | *(no direct equivalent)* | *(no direct equivalent)* | Open to Negotiation | Conservative – prioritize securing a property over terms |
| Agent-guided | *(no direct equivalent)* | Guided by Agent | *(no direct equivalent)* | *(no direct equivalent)* |
| Full-price offer | *(no direct equivalent)* | Offer Full Price to Win | *(no direct equivalent)* | *(no direct equivalent)* |

### 6.2 `collaboration_style` (`preferred_agent_working_style`) Option Crosswalk

| Semantic Cluster | Seller Option | Buyer Option | Landlord Option | Tenant Option |
|---|---|---|---|---|
| Proactive / Takes initiative | Proactive & Takes Initiative — Anticipates needs before I ask | Highly Proactive | Proactive & Assertive | Highly proactive – send regular updates without prompting |
| Consultative / Advisory | Consultative & Guides Me — Explains options and leads me through decisions | Advisor / Consultant | Consultative & Advisory | *(no direct equivalent — covered by Collaborative)* |
| Responsive / On-demand | Responsive & Available — I reach out and they respond promptly | Responsive Partner | *(no direct equivalent)* | Efficient – contact me only when needed |
| Process / Detail-focused | Process-Oriented & Detail-Focused — Thorough, organized, and precise | *(no direct equivalent)* | Data-Driven & Analytical | *(no direct equivalent)* |
| Full-service / Concierge | *(no direct equivalent)* | Full-Service Concierge | *(no direct equivalent)* | Full service – handle everything and keep me informed |
| Collaborative / Joint decisions | *(no direct equivalent)* | *(no direct equivalent)* | Relationship-Focused | Collaborative – frequent check-ins and joint decisions |
| Tech-forward | *(no direct equivalent)* | *(no direct equivalent)* | Tech-Forward & Efficient | *(no direct equivalent)* |
| Traditional / Personalized | *(no direct equivalent)* | *(no direct equivalent)* | Traditional & Personalized | *(no direct equivalent)* |
| Hands-off facilitator | *(no direct equivalent)* | Hands-Off Facilitator | *(no direct equivalent)* | *(no direct equivalent)* |

### 6.3 `decision_making_style` Option Crosswalk

| Semantic Cluster | Seller Option | Buyer Option | Landlord Option | Tenant Option |
|---|---|---|---|---|
| Quick / Independent | Independent — I Decide Quickly | Quick Decisions | *(no field)* | Quick – ready to commit fast |
| Deliberate / Cautious | Cautious — I Need Time to Think | Careful & Deliberate | *(no field)* | Deliberate – need time to consider options |
| Data-driven / Research | Data-Driven — Show Me the Numbers | Research-Driven | *(no field)* | Research-driven – want all facts before deciding |
| Collaborative / Agent-guided | Collaborative — I Value Agent Input | Collaborative with Agent | *(no field)* | Collaborative – involve family / partner in decisions |
| Flexible / Situational | *(no equivalent)* | Flexible / Situational | *(no field)* | *(no equivalent)* |

### 6.4 `guidance_level` Option Crosswalk

| Semantic Cluster | Seller Option (`involvement_level`) | Buyer Option (`support_level`) | Landlord Option (`property_management_involvement`) | Tenant Option (`desired_level_of_agent_involvement`) |
|---|---|---|---|---|
| Fully delegated / Hands-off | Mostly Hands-Off — I trust my agent | Full White-Glove Service | Hands-Off (Agent Manages All) | Fully Delegated – Agent manages everything |
| Mostly delegated / High support | Moderately Involved — Major steps only | High – Guided Throughout | Minimal Involvement | Mostly Delegated – Agent leads, I approve key decisions |
| Collaborative / Shared | *(no direct equivalent)* | Moderate – Key Touchpoints | Occasional Check-Ins | Collaborative – We work together equally |
| Hands-on / Self-sufficient | Very Involved — Part of every decision | Minimal – Self-Sufficient | Actively Involved | Mostly Hands-On – I lead, Agent supports |
| Self-manage after placement | *(no equivalent)* | *(no equivalent)* | Self-Manage After Placement | *(no equivalent)* |

### 6.5 `communication_frequency` Option Crosswalk

| Semantic Cluster | Seller (`communication_style`) | Buyer (`communication_style`) | Landlord (`preferred_contact_method`) | Tenant (`contact_frequency`) |
|---|---|---|---|---|
| Daily / Constant | Frequent & Proactive | Frequent Updates (Daily) | Daily Updates | Daily |
| Every few days | *(no direct equivalent)* | Regular Updates (Every Few Days) | Every Few Days | Every few days |
| Weekly | Structured Check-Ins | Weekly Updates | Weekly Check-Ins | Weekly |
| Major milestones only | As-Needed Updates | Only When Necessary | Only Major Milestones | Only on major updates |
| On-demand / Consumer-initiated | Available On-Demand | As-Needed / On-Demand | Only When I Ask | As needed |

### 6.6 `transaction_pace` Option Crosswalk

| Semantic Cluster | Seller (`flexibility_on_timeline`) | Buyer (`timeline_flexibility`) | Landlord | Tenant (`timeline_urgency`) |
|---|---|---|---|---|
| Strict / Immediate | Firm on Timeline | Strict Timeline | *(no field)* | Immediate (Within 2 Weeks) / Within 30 Days |
| Somewhat flexible | Somewhat Flexible | Limited Flexibility | *(no field)* | 1–2 Months / 2–3 Months |
| Very flexible / Exploring | Very Flexible | Very Flexible | *(no field)* | 3–6 Months / 6+ Months / Exploring Options Only |

### 6.7 `risk_tolerance` Option Crosswalk

| Semantic Cluster | Seller | Buyer (`risk_tolerance`) | Landlord (`risk_tolerance`) | Tenant |
|---|---|---|---|---|
| Very conservative / Strict | *(no field)* | Very Conservative | Low – Strict Screening Only | *(no field)* |
| Conservative / Standard | *(no field)* | Conservative | *(no direct equivalent)* | *(no field)* |
| Moderate / Standard criteria | *(no field)* | Moderate | Moderate – Standard Criteria | *(no field)* |
| Flexible / Case-by-case | *(no field)* | Aggressive | Flexible – Case-by-Case | *(no field)* |
| High / Wide acceptance | *(no field)* | Very Aggressive | High – Willing to Work With Most Tenants | *(no field)* |

---

## 7. Known Naming Inconsistencies

The Phase A audit identified four naming inconsistencies in the consumer-side raw data. Each is documented here with a full resolution.

| Key Name | Affected Role(s) | What the Key Name Implies | What It Actually Stores | Normalized Trait It Feeds | Resolution |
|---|---|---|---|---|---|
| `communication_style` | Seller, Buyer | Communication style (ambiguous) | Contact **frequency** options (Frequent & Proactive, As-Needed Updates, etc.) | `communication_frequency` | At the normalized layer, Seller and Buyer `communication_style` is mapped to `communication_frequency`. The key name inconsistency exists only in the raw storage layer and is resolved by the normalized trait. Phase C must not introduce an agent-side field called `communication_style` for frequency data. |
| `communication_style` | Landlord, Tenant | Communication style (ambiguous) | Contact **channel/method** options (Email Only, Phone Calls Preferred, Text / SMS, etc.) | `communication_channel` | At the normalized layer, Landlord and Tenant `communication_style` is mapped to `communication_channel`. The same key name stores semantically opposite data depending on the role. The normalized layer eliminates this by routing each role's data to its correct trait. |
| `preferred_contact_method` | Landlord | Preferred contact **method** | Contact **frequency** options (Daily Updates, Weekly Check-Ins, Only When I Ask, etc.) | `communication_frequency` | At the normalized layer, Landlord `preferred_contact_method` is mapped to `communication_frequency`. The key name is misleading; the UI label ("Preferred Contact Frequency") is accurate. The raw data is usable as-is; the normalization layer must apply the correct trait assignment. |
| `preferred_contact_method` | Tenant | Preferred contact **method** | Preferred contact **time of day** options (Morning, Afternoon, Evening, Anytime) | *(informational context — no normalized trait)* | At the normalized layer, Tenant `preferred_contact_method` does not map to any of the 12 normalized traits as a structured compatibility dimension. Time-of-day preference is not a representation compatibility signal; it is a scheduling convenience preference. It is retained as informational context available on the listing record but excluded from trait scoring. Phase C has no obligation to collect an agent-side counterpart for this field. |
| `communication_frequency` | Buyer | Contact **frequency** | Meeting / showing **format preference** (In-Person Only, Virtual Tours Accepted, Agent Pre-Screens for Me, Flexible) | `collaboration_style` | At the normalized layer, Buyer `communication_frequency` is mapped to `collaboration_style` because its options describe how the agent should conduct property viewings — a behavioral/operational mode, not a contact cadence. The key name is entirely misleading; the UI label ("Meeting / Showing Preference") is accurate. Phase C agent-side field design should treat showing format preference as part of collaboration style, not communication frequency. |

---

## 8. Fields Requiring Normalization

The following raw sub-keys cannot be directly mapped to a single normalized trait without transformation, renaming, or disambiguation logic. Phase C must handle these explicitly.

### 8.1 `communication_style` — Role-Dependent Semantic Split

**Fields:** Seller `communication_style`, Buyer `communication_style`, Landlord `communication_style`, Tenant `communication_style`

**Normalization required:** The same key name must be routed to different normalized traits depending on the source role. A normalization function reading `compatibility_preferences.{role}_specific.communication_style` must inspect the role context and apply:
- Seller role → `communication_frequency`
- Buyer role → `communication_frequency`
- Landlord role → `communication_channel`
- Tenant role → `communication_channel`

No raw value transformation is needed — only correct trait routing by role.

### 8.2 `preferred_contact_method` — Three-Way Semantic Split

**Fields:** Seller `preferred_contact_method`, Buyer `preferred_contact_method`, Landlord `preferred_contact_method`, Tenant `preferred_contact_method`

**Normalization required:** The same key name stores three semantically different types of data across four roles:
- Seller `preferred_contact_method` → `communication_channel` (contact method options — correct key usage)
- Buyer `preferred_contact_method` → `communication_channel` (contact method options — correct key usage)
- Landlord `preferred_contact_method` → `communication_frequency` (contact frequency options — misnamed key)
- Tenant `preferred_contact_method` → *(informational context only)* (time-of-day options — misnamed key, excluded from traits)

A normalization function must inspect the role context and apply the correct routing. Tenant data from this key must be marked as informational-only and must never be scored against an agent-side trait.

### 8.3 `communication_frequency` (Buyer) — Showing Preference Misrouting

**Field:** Buyer `communication_frequency`

**Normalization required:** Despite its key name, this field stores showing and meeting format preferences (In-Person Only, Virtual Tours Accepted, Agent Pre-Screens for Me, Flexible). Normalization must route this to `collaboration_style`, not `communication_frequency`. The key name is the only misleading element — the stored options are semantically consistent and do not require value transformation.

### 8.4 `primary_transaction_goal` / `primary_leasing_goal` / `primary_rental_goal` — Different Key Names, Same Trait

**Fields:** Seller `primary_transaction_goal`, Buyer `primary_transaction_goal`, Landlord `primary_leasing_goal`, Tenant `primary_rental_goal`

**Normalization required:** All three distinct key names (and their "Other" companion inputs) feed the same normalized trait `property_strategy_fit`. The normalization layer must treat these as equivalent inputs routed to the same trait regardless of key name variation.

### 8.5 `involvement_level` / `support_level` / `property_management_involvement` / `desired_level_of_agent_involvement` — Role-Specific Names, Same Trait

**Fields:** Seller `involvement_level`, Buyer `support_level`, Landlord `property_management_involvement`, Tenant `desired_level_of_agent_involvement`

**Normalization required:** All four role-specific key names feed the single normalized trait `guidance_level`. The normalization layer must treat them as equivalent inputs despite having four different storage key names.

### 8.6 `preferred_agent_working_style` — Consistent Key Name, Divergent Option Sets

**Field:** Seller, Buyer, Landlord, Tenant all use `preferred_agent_working_style`

**Normalization required:** The key name is consistent across all four roles, which is positive. However, the option sets differ substantially (Landlord includes "Data-Driven & Analytical" and "Tech-Forward & Efficient" with no equivalents elsewhere; Buyer includes "Full-Service Concierge" with no equivalent elsewhere). Phase C must produce a unified agent-side option set drawn from the crosswalk in Section 6.2. No raw value transformation is needed at the consumer side — only agent-side unification.

---

## 9. Missing Agent-Side Counterparts

The following table consolidates the Phase A gap analysis finding across all 12 normalized traits. Every trait is confirmed absent from all four agent bid components.

| # | Normalized Trait | Absent from Seller Bid? | Absent from Buyer Bid? | Absent from Landlord Bid? | Absent from Tenant Bid? | Phase A Reference |
|---|---|---|---|---|---|---|
| 1 | `communication_channel` | Yes | Yes | Yes | Yes | §11.1, §11.2, §11.3, §11.4 |
| 2 | `communication_frequency` | Yes | Yes | Yes | Yes | §11.1, §11.2, §11.3, §11.4 |
| 3 | `responsiveness_expectation` | Yes | Yes | Yes | Yes | §11.1, §11.2, §11.3, §11.4 |
| 4 | `negotiation_style` | Yes | Yes | Yes | Yes | §11.1, §11.2, §11.3, §11.4 |
| 5 | `guidance_level` | Yes | Yes | Yes | Yes | §11.1, §11.2, §11.3, §11.4 |
| 6 | `decision_making_style` | Yes | Yes | Yes | Yes | §11.1, §11.2, §11.3, §11.4 |
| 7 | `transaction_pace` | Yes | Yes | Yes | Yes | §11.1, §11.2, §11.3, §11.4 |
| 8 | `risk_tolerance` | Yes | Yes | Yes | Yes | §11.1, §11.2, §11.3, §11.4 |
| 9 | `collaboration_style` | Yes | Yes | Yes | Yes | §11.1, §11.2, §11.3, §11.4 |
| 10 | `representation_priorities` | Yes | Yes | Yes | Yes | §11.1, §11.2, §11.3, §11.4 |
| 11 | `representation_philosophy` | Yes | Yes | Yes | Yes | §11.1, §11.2, §11.3, §11.4 |
| 12 | `property_strategy_fit` | Yes | Yes | Yes | Yes | §11.1, §11.2, §11.3, §11.4 |

**Summary:** Zero agent-side compatibility response fields exist across all four bid components for all 12 normalized traits. This is the definitive confirmation that Phase C must design 12 new response areas from scratch, with no pre-existing agent-side data to migrate or build upon.

The `listing_compatibility_scores` table has no `representation_compatibility_score` column and no scoring framework version that accounts for any of the 12 traits. The agent bid meta tables (`seller_agent_auction_bid_metas`, `buyer_agent_auction_bid_metas`, `landlord_agent_auction_bid_metas`, `tenant_agent_auction_bid_metas`) contain no `compatibility_response` meta keys of any kind.

---

## 10. Traits Excluded From Compatibility

The following field categories exist on the platform and must be kept strictly separate from the Professional Representation Compatibility system. These exclusions are permanent and must be enforced at every subsequent phase.

### 10.1 Broker Compensation and Commission Fields

Fields such as `purchase_fee_type`, `purchase_fee_percentage`, `purchase_fee_flat`, `lease_fee_type`, `commission_structure`, `commission_structure_type`, `referral_percentage`, `retainer_fee_option`, `early_termination_fee_option`, and all their sub-variants are present in both the consumer listing forms and agent bid forms. These fields define the financial terms of the broker representation agreement — what a consumer pays and what an agent earns.

These are transaction terms, not representation compatibility traits. Including them in the compatibility system would conflate "does this agent suit my working style?" with "does this agent's price match my budget?" — two entirely different evaluative questions. Compensation compatibility, if scored at all, belongs in the existing `financial_match_score` or `terms_match_score` dimensions, not in the representation compatibility layer.

### 10.2 Services Lists

Fields such as `services`, `other_services`, and `flat_fee_services` describe the tactical tasks the agent will perform — the deliverables and scope-of-work items of the engagement. These are already incorporated into the existing match score helpers (`SellerBidMatchScoreHelper`, `BuyerBidMatchScoreHelper`, `LandlordBidMatchScoreHelper`, `TenantBidMatchScoreHelper`). They must not be re-scored under the compatibility dimension.

### 10.3 Agency Agreement and Brokerage Relationship Fields

Fields such as `agency_agreement_timeframe`, `brokerage_relationship`, and `protection_period` are legal-agreement terms. They describe the structure and duration of the agency contract, not the interpersonal or professional style compatibility between agent and consumer.

### 10.4 Protected-Class and Fair Housing-Sensitive Data

**This exclusion is absolute.** No field that relates to race, color, national origin, religion, sex, familial status, disability, age, or any other class protected under the Fair Housing Act, the Equal Credit Opportunity Act, or applicable state law may be introduced into the compatibility system at any phase. This prohibition applies to:

- Collecting such data as a consumer input under any compatibility tab.
- Collecting such data as an agent response under any bid component.
- Using any proxy field that correlates with a protected class.
- Using any scoring algorithm that produces disparate outcomes on the basis of a protected class.

The `tenant_type_preference` Landlord field (which includes options such as "Individual / Family", "Young Professionals", "Students", "Corporate / Relocation", "Small Business") sits at the boundary of this exclusion. While it feeds `property_strategy_fit` as a strategic context field (commercial vs. residential tenant type), it must never be used to weight or penalize agents who do not specialize in a particular demographic group. Scoring use of this field is restricted to matching agent stated specialization, not consumer demographic preference.

---

## 11. Governance Rules

The following rules govern the Professional Representation Compatibility system at every phase of design, implementation, and operation.

**Rule 1 — AI Advisory Only.** The compatibility score produced by this system is an advisory signal. It informs the consumer's evaluation of agent bids; it does not make decisions on the consumer's behalf. The system must present compatibility as a suggested data point, not a definitive ranking or recommendation.

**Rule 2 — No Hidden Weighting.** Every factor that contributes to a compatibility score must be disclosed to the consumer. No trait may be silently weighted more than another without the weighting methodology being visible to the consumer. If weights are used, they must be configurable and transparent.

**Rule 3 — No Scoring Implementation in This Document.** This document defines traits and maps fields. It does not specify scoring algorithms, match thresholds, weighting formulas, or compatibility calculation logic. Scoring design belongs to Phases D and E.

**Rule 4 — No Protected-Class Traits.** No trait may use, correlate with, or proxy for any protected-class characteristic under the Fair Housing Act, the Equal Credit Opportunity Act, or applicable state or local law. This applies to every phase of the system: data collection, trait design, scoring logic, and display. See Section 10.4 for the absolute prohibition.

**Rule 5 — No Fair Housing-Sensitive Traits.** Beyond legally protected classes, any field that could produce discriminatory patterns in agent selection — even without intent — must be excluded. A field is Fair Housing-sensitive if its use in scoring would predictably disadvantage agents who serve protected-class consumers or would predictably favor agents who do not.

**Rule 6 — No Demographic Profiling.** The system must not create or maintain profiles of agents or consumers based on demographic characteristics. Consumer inputs about desired tenant type or buyer profile are strategic context signals, not demographic filters.

**Rule 7 — Professional Representation Compatibility Scope Only.** The 12 normalized traits defined in this document constitute the complete and bounded scope of what this system measures. The system measures how an agent and consumer prefer to work together as professionals. It does not measure: property characteristics, financial qualifications, neighborhood preferences, agent production volume, brokerage affiliation, or any factor outside the 12 defined traits.

**Rule 8 — Consumer Data Confidentiality.** A consumer's compatibility preferences are private inputs. They may be used to compute a compatibility score visible to the consumer, but the raw preference data must not be displayed to agents in a way that reveals information the consumer did not intend to disclose.

**Rule 9 — Iterative and Versioned.** The scoring framework must support versioning (consistent with the existing `scoring_framework_version` architecture in `listing_compatibility_scores`). Any change to trait definitions, option sets, or weighting logic must increment the framework version so that scores computed under different frameworks are not compared directly.

**Rule 10 — Fair Housing Review Required.** Before any phase of this system is released to production, a Fair Housing compliance review must be completed by a qualified reviewer. The governance rules stated here are a precondition for that review, not a substitute for it.

---

## 12. Phase C Readiness Checklist

The following conditions must all be true before Phase C (agent-side field design) can begin. This checklist reflects the Phase B deliverables.

| Condition | Status |
|---|---|
| All 12 normalized traits fully defined (definition, what it measures, what it does NOT measure) | Done — Section 4 |
| All four naming inconsistencies from Phase A explicitly resolved | Done — Section 7 |
| All raw consumer-side fields mapped to normalized traits, per role | Done — Section 5 |
| Option value crosswalks produced for all multi-role traits | Done — Section 6 |
| Fields requiring normalization logic explicitly identified | Done — Section 8 |
| Agent-side gap confirmed: zero counterparts exist for all 12 traits across all four bid components | Done — Section 9 |
| Fields that must NOT drive compatibility explicitly documented | Done — Section 10 |
| Governance rules restated in full | Done — Section 11 |
| Informational-context fields distinguished from trait-mapped fields | Done — Sections 5 and 7 |
| Tenant `preferred_contact_method` explicitly excluded from trait scoring | Done — Sections 7 and 8 |
| Buyer `communication_frequency` correctly rerouted to `collaboration_style` | Done — Sections 7 and 8 |
| Fair Housing exclusion restated and absolute prohibition confirmed | Done — Sections 10.4 and 11 |
| Fair Housing compliance review planned (not yet completed) | Pending — required before Phase C output enters production |

**Phase C can begin.** All design prerequisites are satisfied. The Phase C deliverable is the agent-side field specification: for each of the 12 normalized traits, a set of agent-facing questions with unified option sets that can be answered once and matched against any of the four consumer-role variants.

---

*End of document. This document is read-only. No code, schema, migration, route, controller, Blade, Livewire, config, or database file was modified to produce it.*
