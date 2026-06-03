# Ask AI — Suggested Questions Engine Specification

**Document ID:** ASK_AI_SUGGESTED_QUESTIONS_ENGINE_SPEC_V1  
**Version:** 1.0  
**Status:** Authoritative  
**Effective Date:** 2026-06-03  
**Applies to:** Bid Your Offer — Ask AI feature (listing page surfaces)  

---

## Table of Contents

1. [Purpose](#1-purpose)
2. [Supported Listing Types](#2-supported-listing-types)
3. [Safe Suggested Question Categories](#3-safe-suggested-question-categories)
4. [Prohibited Suggested Question Categories](#4-prohibited-suggested-question-categories)
5. [Suggested Questions Per Listing Type](#5-suggested-questions-per-listing-type)
   - [5.1 Seller Offer Listings](#51-seller-offer-listings)
   - [5.2 Buyer Criteria Listings](#52-buyer-criteria-listings)
   - [5.3 Landlord Offer Listings](#53-landlord-offer-listings)
   - [5.4 Tenant Criteria Listings](#54-tenant-criteria-listings)
6. [Dynamic Generation Rules](#6-dynamic-generation-rules)
7. [Static Fallback Questions](#7-static-fallback-questions)
8. [UI Behavior](#8-ui-behavior)
9. [Compliance Guardrails](#9-compliance-guardrails)
10. [Future Implementation Plan](#10-future-implementation-plan)

---

## 1. Purpose

This document is the authoritative specification for the **Ask AI Suggested Questions Engine** on the Bid Your Offer platform. Its purpose is to define exactly how the system surfaces pre-generated, role-appropriate, compliant suggested questions to users on listing pages — questions the user can tap to quickly launch an Ask AI conversation without needing to compose a question themselves.

The Suggested Questions Engine is **not** a freeform AI question generator. It is a curated, rule-governed selection layer that:

- **Selects and surfaces** safe, pre-approved question templates based on listing type, available platform data, and the viewing user's role.
- **Personalizes** suggestions dynamically by substituting real listing data, Property DNA outputs, Location DNA outputs, Avatar outputs, and Compatibility data into approved question templates.
- **Falls back** gracefully to static question templates when dynamic data is sparse or unavailable.
- **Blocks** any suggested question that falls into a prohibited category before it is ever displayed to a user.

### What This Spec Governs

This spec governs:

- Which question categories are safe to suggest.
- Which question categories are prohibited and must never be suggested.
- What specific question text is suggested per listing type.
- How questions are generated dynamically from platform data.
- What static fallback questions are displayed when dynamic data is unavailable.
- How the suggestion UI behaves and what it renders.
- What compliance guardrails govern all suggested questions.
- How the engine will be extended in future phases.

### Relationship to Other Specs

This document is subordinate to and consistent with:

- `ASK_AI_ROADMAP_AND_GUARDRAILS.md` — phase gating, hard guardrails, stop rules.
- `ASK_AI_KNOWLEDGE_MAP.md` — approved data sources, prohibited roles, hierarchy.
- `ASK_AI_QUESTION_CLASSIFICATION_SPEC.md` — question type contracts, classification rules.
- `ASK_AI_REFUSAL_POLICY.md` — refusal categories and required refusal templates.

In any conflict between this document and the documents above, the document higher in the list governs.

---

## 2. Supported Listing Types

The Suggested Questions Engine must support all four listing types on the platform. Each listing type has a distinct set of data sources, field shapes, and user audiences that determine which question suggestions are appropriate.

| Listing Type | Model / Table | Typical Listing Owner | Typical Viewer |
|---|---|---|---|
| **Seller Offer Listing** | `seller_agent_auctions` + `seller_agent_auction_metas` | Seller or seller's agent | Buyers, buyer agents |
| **Buyer Criteria Listing** | `buyer_agent_auctions` + `buyer_agent_auction_metas` | Buyer or buyer's agent | Seller agents, landlords |
| **Landlord Offer Listing** | `landlord_agent_auctions` + `landlord_agent_auction_metas` | Landlord or landlord's agent | Tenants, tenant agents |
| **Tenant Criteria Listing** | `tenant_criteria_auctions` + `tenant_criteria_auction_metas` | Tenant or tenant's agent | Landlords, landlord agents |

The engine must resolve the listing type from the `listing_type` context key passed in the Ask AI context object. Suggested questions are scoped to the resolved listing type. Cross-listing-type suggestions (e.g., showing a question about lease terms on a seller listing) are not permitted.

---

## 3. Safe Suggested Question Categories

The following question categories are approved for the Suggested Questions Engine. Every suggested question displayed to a user must resolve to exactly one of these categories. Categories map directly to the question types defined in `ASK_AI_QUESTION_CLASSIFICATION_SPEC.md`.

### 3.1 `property_standout`

**What it covers:** Questions about observable, documented property attributes — features, size, condition, amenities, and how they compare to similar listings using aggregated platform market data.

**Why it is safe:** Answers are grounded exclusively in listing fields and Property DNA outputs. No demographic data, no forward-looking predictions, no valuation conclusions.

**Approved data sources:** Listing fields (square footage, lot size, bedrooms, bathrooms, year built, amenities, condition), Property DNA profiles, Property DNA narratives, Property DNA scores, Property DNA tags and categories.

---

### 3.2 `suited_audience`

**What it covers:** Questions about which broad, objective lifestyle profiles this property's physical characteristics may suit — based solely on documented property attributes (size, layout, accessibility features, parking, proximity to transit).

**Why it is safe:** Lifestyle profiles are generated by the Avatar Engine and defined using objective, non-demographic attributes. No protected class characteristics are referenced. Responses carry the required fair housing disclosure.

**Approved data sources:** Listing fields (layout, accessibility features, bedroom count, floor plan type, garage/parking), Location DNA distance and proximity outputs, Avatar Engine outputs, Compatibility Engine lifestyle categories.

---

### 3.3 `buyer_tenant_match`

**What it covers:** Questions about how closely a specific buyer's or tenant's stated requirements align with the listing's documented terms — compared on objective criteria only (price range, square footage, bedroom count, lease term, pet policy, etc.).

**Why it is safe:** Match scoring is computed exclusively on objective, non-demographic, platform-stored fields. No protected class characteristics are factored in. The authenticated user's own data is used and never exposed to other users.

**Approved data sources:** Authenticated user's saved bid terms or search profile, listing fields (price, size, lease length, pet policy, utilities, available date, property type), Compatibility Engine outputs.

---

### 3.4 `compatibility_signals`

**What it covers:** Questions about whether an agent's stated service offering or bid terms are compatible with a seller's or landlord's listing requirements — used in agent auction contexts.

**Why it is safe:** Compatibility is computed on objective bid and listing fields only. No qualitative judgment about personal character or protected class characteristics is made.

**Approved data sources:** Listing requirement fields (service type, commission cap, required credentials, geographic coverage), agent bid fields (commission rate, services offered, credentials, referral percentage), `AgentDefaultProfile.profile_data` (read-only).

---

### 3.5 `missing_data`

**What it covers:** Questions identifying fields in a listing or bid that are blank, incomplete, or inconsistent — and that are typically required for a complete submission or for accurate AI analysis.

**Why it is safe:** Answers describe the state of the user's own record. No private data from other users is exposed. No fabrication of field values is permitted.

**Approved data sources:** Current listing or bid record's field values (null/empty detection), platform validation rule set for the relevant form type.

---

### 3.6 `marketing_angles`

**What it covers:** Questions requesting AI-generated marketing copy suggestions, headline ideas, or positioning strategies for a listing, based on the listing's documented attributes.

**Why it is safe:** Generated copy is grounded in documented listing data only. Demographic steering language is prohibited. Marketing Intelligence Engine outputs are the approved source when available.

**Approved data sources:** Non-private listing fields (description, amenities, size, age, upgrades, location at city/state level, listing price/rent range), agent-supplied notes, Marketing Intelligence Engine outputs.

---

### 3.7 `educational`

**What it covers:** Questions about how the platform works, auction mechanics, terminology definitions, process steps, or general real estate concepts the platform covers.

**Why it is safe:** Answers are grounded in platform documentation and general real estate terminology. No transaction-specific advice, no legal conclusions, no predictions.

**Approved data sources:** Platform documentation and this specification, general real estate terminology from publicly authoritative sources, the authenticated user's own record when the question concerns interpreting their specific record.

---

## 4. Prohibited Suggested Question Categories

The following categories must **never** be surfaced as suggested questions. If a question template during development falls into any category below, it must be removed or rewritten before it can be included in the suggestion pool. These categories are consistent with `ASK_AI_REFUSAL_POLICY.md` Section 2.

### 4.1 Legal Advice Questions

**Prohibited:** Any question that would prompt Ask AI to interpret, apply, or recommend a course of action based on law, regulation, statute, contract enforceability, disclosure obligations, or legal rights.

**Examples of prohibited question text:**
- "Do I have to disclose the flooding issue?"
- "Is this contract enforceable?"
- "What are my legal rights if the seller backs out?"
- "Am I legally required to accept this offer?"

**Rationale:** Ask AI holds no legal license. Responses would constitute unauthorized practice of law and could cause significant harm to users acting on incorrect legal information.

---

### 4.2 Brokerage and Negotiation Advice Questions

**Prohibited:** Any question that would prompt Ask AI to advise on agency relationships, listing strategy, negotiation tactics, or whether to accept, reject, or counter an offer.

**Examples of prohibited question text:**
- "Should I accept this offer?"
- "What price should I counter at?"
- "Is this a good deal for me?"
- "How do I negotiate the commission down?"

**Rationale:** These topics require guidance from a licensed real estate broker or agent. Ask AI is not licensed and must not substitute for brokerage judgment.

---

### 4.3 Lending and Mortgage Advice Questions

**Prohibited:** Any question that would prompt Ask AI to recommend loan products, estimate qualification likelihood, advise on financing structures, or comment on a user's creditworthiness.

**Examples of prohibited question text:**
- "Will I qualify for a mortgage on this property?"
- "What loan type should I use to buy this home?"
- "How much should I put down?"
- "Can I get approved with my debt-to-income ratio?"

**Rationale:** Mortgage and lending advice requires a licensed loan officer or mortgage professional. Incorrect lending information can cause significant financial harm.

---

### 4.4 Tax Advice Questions

**Prohibited:** Any question that would prompt Ask AI to advise on capital gains treatment, 1031 exchange eligibility, depreciation, deductibility of costs, or any other tax consequence of a real estate transaction.

**Examples of prohibited question text:**
- "How do I avoid capital gains tax when I sell?"
- "Is this a good 1031 exchange property?"
- "What can I deduct from this transaction?"

**Rationale:** Tax advice requires a licensed CPA, enrolled agent, or tax attorney. Tax consequences vary significantly by individual situation and jurisdiction.

---

### 4.5 Investment Advice Questions

**Prohibited:** Any question that would prompt Ask AI to evaluate whether a property is a good investment, project return on investment, or compare investment properties.

**Examples of prohibited question text:**
- "Is this a good investment property?"
- "What is the cap rate on this rental?"
- "Will this appreciate faster than the market?"
- "Should I buy this as a rental?"

**Rationale:** Investment advice requires a licensed financial advisor. Ask AI is not equipped to evaluate individual investment goals, risk tolerance, or financial circumstances.

---

### 4.6 Market Prediction Questions

**Prohibited:** Any question that would prompt Ask AI to forecast future property values, rental rates, market trends, or any other forward-looking assertion about real estate market conditions.

**Examples of prohibited question text:**
- "Will this property go up in value?"
- "Is now a good time to buy in this area?"
- "What will rents be here in two years?"
- "Is the market going to crash?"

**Rationale:** No AI system can reliably predict market movements. Presenting such predictions as Ask AI output would expose users to financial risk.

---

### 4.7 Fair Housing and Demographic Questions

**Prohibited:** Any question that references, implies, or could elicit information about the demographic composition of a neighborhood, community, or area — or that could facilitate steering based on any protected class characteristic.

**Examples of prohibited question text:**
- "What kind of people live in this neighborhood?"
- "Is this area good for families like mine?"
- "What are the school demographics here?"
- "Is this a safe area for someone with my background?"

**Rationale:** These questions, if answered, could facilitate violations of the Fair Housing Act (42 U.S.C. § 3604) and equivalent state and local laws. The prohibition is unconditional regardless of how the question is framed.

---

### 4.8 Protected Class Suitability Questions

**Prohibited:** Any question that attempts to determine whether a property is appropriate, suitable, or available for a specific person based on any protected class characteristic — including questions framed as lifestyle fit, community culture, or neighborhood character.

**Rationale:** Suitability conclusions based on protected class characteristics constitute illegal housing discrimination regardless of the framing.

---

## 5. Suggested Questions Per Listing Type

This section provides the approved question sets for each listing type. Each question is presented with its question category, the data fields or engine outputs it requires, and the conditions under which it should be shown.

### 5.1 Seller Offer Listings

Seller offer listings describe a property that a seller is offering for sale and is seeking agent bids for representation. Suggested questions should help buyers, buyer agents, and prospective bidding agents understand the property and the listing's terms.

---

**Q1: What are this property's most notable features?**
- Category: `property_standout`
- Required data: Property DNA profile, listing fields (bedrooms, bathrooms, square footage, year built, lot size, amenities)
- Condition: Show when Property DNA output is available. Use static fallback Q-S1 when absent.
- Example dynamic text: "The Property DNA profile for this [3-bedroom / 2,100 sq ft / built 2003] home highlights [hardwood floors, updated kitchen, and a corner lot] as its most distinguishing attributes compared to similar listings in [City, State]."

---

**Q2: How does this property's square footage compare to similar listings in the area?**
- Category: `property_standout`
- Required data: Listing square footage field, Location DNA aggregated comparable data
- Condition: Show when both square footage and Location DNA comparables are available.
- Example dynamic text: "At 2,100 sq ft, this home is [above / at / below] the median of [X] sq ft for [property type] listings in [zip code/city] currently tracked on this platform."

---

**Q3: What does the Property DNA score for this home mean?**
- Category: `property_standout`
- Required data: Property DNA score output
- Condition: Show when a Property DNA score has been generated for this listing.
- Example dynamic text: "This property received a Property DNA score of [X] out of 100. This score reflects [key scoring factors from Property DNA narrative]."

---

**Q4: What lifestyle profiles does this property match based on its layout and location?**
- Category: `suited_audience`
- Required data: Avatar Engine outputs, listing layout fields, Location DNA proximity data
- Condition: Show when Avatar Engine outputs are available.
- Example dynamic text: "Based on this property's layout and location attributes, the Avatar Engine identifies [Remote Worker, Growing Family] as likely-fit lifestyle profiles."

---

**Q5: What information is missing from this listing that could improve AI analysis?**
- Category: `missing_data`
- Required data: Listing record field values (null/empty detection), validation rule set
- Condition: Show when one or more recommended fields are null or empty.
- Example dynamic text: "This listing is missing [year built] and [HOA details], which are used by the Property DNA and Compatibility engines. Completing these fields will improve AI-generated insights."

---

**Q6: What are the strongest marketing angles for this property?**
- Category: `marketing_angles`
- Required data: Marketing Intelligence Engine output, listing description, listing fields
- Condition: Show when Marketing Intelligence Engine output is available. Use static fallback Q-S6 when absent.
- Example dynamic text: "The Marketing Intelligence Engine suggests leading with [large lot size] and [updated kitchen] as this property's primary marketing angles, with [walk-to-transit access] as a secondary differentiator."

---

**Q7: How does the seller auction process work on this platform?**
- Category: `educational`
- Required data: None (platform documentation)
- Condition: Always show for users viewing a seller listing for the first time (session-scoped, max once per session).
- Example dynamic text: "The seller auction process on Bid Your Offer works as follows: [educational summary drawn from platform documentation]."

---

**Q8: What sale terms has the seller specified in this listing?**
- Category: `property_standout`
- Required data: Listing fields (preferred closing timeline, contingencies allowed, earnest money requirements, preferred financing types, as-is status)
- Condition: Show when any of the sale term fields are populated.
- Example dynamic text: "This seller has specified [a 45-day closing window], [no inspection contingencies], and [pre-approved buyers only] as their stated sale preferences."

---

### 5.2 Buyer Criteria Listings

Buyer criteria listings describe what a buyer is looking for in a property and is seeking agent bids to represent them. Suggested questions should help agents understand the buyer's requirements and help the buyer understand how well their criteria align with available inventory.

---

**Q1: How complete is my buyer criteria listing?**
- Category: `missing_data`
- Required data: Listing record field values, validation rule set
- Condition: Show when the buyer is viewing their own listing and one or more recommended fields are null.
- Example dynamic text: "Your buyer criteria listing is missing [maximum price], [preferred move-in date], and [bedroom minimum], which are used by the Compatibility Engine to match your criteria against available properties."

---

**Q2: What does my buyer avatar profile say about my stated preferences?**
- Category: `suited_audience`
- Required data: Avatar Engine output for this buyer's criteria listing
- Condition: Show when an Avatar Engine output has been generated for this listing.
- Example dynamic text: "Based on your stated requirements, the Avatar Engine classifies your buyer profile as [Upsizing Family / Remote Worker / First-Time Buyer]. This profile is used by agents and the Compatibility Engine when evaluating listings against your criteria."

---

**Q3: How compatible is this buyer profile with available seller listings?**
- Category: `buyer_tenant_match`
- Required data: Compatibility Engine outputs comparing buyer criteria against available seller listing inventory
- Condition: Show when Compatibility Engine outputs are available for this buyer's criteria against at least one seller listing.
- Example dynamic text: "The Compatibility Engine has evaluated your buyer criteria against [X] active seller listings. [Y] listings score above the compatibility threshold on your top-priority fields."

---

**Q4: What is a buyer agent auction and how does bidding work?**
- Category: `educational`
- Required data: None (platform documentation)
- Condition: Always show for first-time viewers of a buyer criteria listing (session-scoped, max once per session).

---

**Q5: What are the strongest purchase criteria I've stated in this listing?**
- Category: `buyer_tenant_match`
- Required data: Listing fields (price range, bedroom/bathroom requirements, desired square footage, preferred neighborhoods, property type, timeline)
- Condition: Show when core criteria fields are populated.
- Example dynamic text: "Your listing specifies [3+ bedrooms], [under $650,000], and [within 20 miles of downtown] as your top-priority criteria. These are the fields weighted most heavily in Compatibility Engine scoring."

---

**Q6: What should I add to help agents better understand what I'm looking for?**
- Category: `missing_data`
- Required data: Listing record fields, validation rule set, Agent field-use statistics (aggregated platform data only, no individual agent data)
- Condition: Show when one or more fields frequently used by agents in bids are empty on this listing.
- Example dynamic text: "Agents frequently reference [preferred closing timeline], [flexibility on inspection contingencies], and [pre-approval status] when preparing buyer bids. These fields are currently blank on your listing."

---

### 5.3 Landlord Offer Listings

Landlord offer listings describe a rental property that a landlord is offering and for which they are seeking tenant or agent bids. Suggested questions should help tenants, tenant agents, and prospective bidding agents understand the rental terms and property characteristics.

---

**Q1: What are the key features of this rental property?**
- Category: `property_standout`
- Required data: Property DNA profile, listing fields (bedrooms, bathrooms, square footage, year built, amenities, pet policy, included utilities)
- Condition: Show when Property DNA output is available. Use static fallback Q-L1 when absent.
- Example dynamic text: "The Property DNA profile for this [2-bedroom / 950 sq ft] rental highlights [in-unit laundry, covered parking, and a private balcony] as its most distinguishing attributes among comparable rentals in [City, State]."

---

**Q2: What lifestyle profiles suit this rental based on its layout and location?**
- Category: `suited_audience`
- Required data: Avatar Engine outputs, listing layout fields, Location DNA proximity data
- Condition: Show when Avatar Engine outputs are available.
- Example dynamic text: "Based on this rental's attributes, the Avatar Engine identifies [Young Professional, Remote Worker] as likely-fit lifestyle profiles based on size, location, and transit access."

---

**Q3: What lease terms has the landlord specified?**
- Category: `property_standout`
- Required data: Listing fields (lease term length, available date, pet policy, smoking policy, included utilities, security deposit amount, rent amount, late fee policy)
- Condition: Show when lease term fields are populated.
- Example dynamic text: "This landlord has specified a [12-month minimum lease], [no pets], [water and trash included], and [first/last month deposit]. The unit is available [date]."

---

**Q4: What information is missing from this rental listing?**
- Category: `missing_data`
- Required data: Listing record field values, validation rule set
- Condition: Show when recommended fields are null or empty.
- Example dynamic text: "This landlord listing is missing [pet policy], [utility inclusions], and [available date]. These fields are used by the Compatibility Engine to match tenant criteria. Completing them increases qualified tenant inquiries."

---

**Q5: How does the landlord auction process work on this platform?**
- Category: `educational`
- Required data: None (platform documentation)
- Condition: Always show for first-time viewers of a landlord listing (session-scoped, max once per session).

---

**Q6: What are the strongest marketing angles for this rental?**
- Category: `marketing_angles`
- Required data: Marketing Intelligence Engine output, listing description, listing fields
- Condition: Show when Marketing Intelligence Engine output is available. Use static fallback Q-L6 when absent.
- Example dynamic text: "The Marketing Intelligence Engine suggests leading with [in-unit laundry] and [covered parking] as primary differentiators, with [transit proximity] as a secondary angle for this rental."

---

**Q7: How does this rental compare to similar listings in this area?**
- Category: `property_standout`
- Required data: Property DNA score, Location DNA aggregated comparable rental data
- Condition: Show when both are available.
- Example dynamic text: "At [$1,850/month for 950 sq ft], this rental is [above / at / below] the median rent-per-square-foot for [property type] rentals in [zip code/city] tracked on this platform."

---

### 5.4 Tenant Criteria Listings

Tenant criteria listings describe what a renter is looking for in a rental property and for which they are seeking landlord or agent bids. Suggested questions should help landlords understand the tenant's requirements and help the tenant understand how their criteria aligns with available rentals.

---

**Q1: How complete is my tenant criteria listing?**
- Category: `missing_data`
- Required data: Listing record field values, validation rule set
- Condition: Show when the tenant is viewing their own listing and recommended fields are null.
- Example dynamic text: "Your tenant criteria listing is missing [maximum monthly rent], [preferred move-in date], and [minimum bedroom count]. These are used by the Compatibility Engine to match your criteria against available rentals."

---

**Q2: What does my tenant avatar profile say about my stated rental preferences?**
- Category: `suited_audience`
- Required data: Avatar Engine output for this tenant's criteria listing
- Condition: Show when an Avatar Engine output has been generated.
- Example dynamic text: "Based on your stated requirements, the Avatar Engine classifies your renter profile as [Remote Worker / Urban Professional / Relocating Family]. This profile is used by landlords and the Compatibility Engine when evaluating tenant applications."

---

**Q3: How compatible are my criteria with available rental listings?**
- Category: `buyer_tenant_match`
- Required data: Compatibility Engine outputs comparing tenant criteria against available landlord listings
- Condition: Show when Compatibility Engine outputs are available for this tenant's criteria against at least one landlord listing.
- Example dynamic text: "The Compatibility Engine has evaluated your tenant criteria against [X] active rental listings. [Y] listings score above the compatibility threshold on your stated priority fields."

---

**Q4: What are my stated lease requirements?**
- Category: `buyer_tenant_match`
- Required data: Listing fields (budget range, desired lease term, move-in date, bedroom/bathroom requirements, pet needs, desired utilities inclusions)
- Condition: Show when core criteria fields are populated.
- Example dynamic text: "Your listing specifies [up to $2,000/month], [12-month lease preferred], [1 small dog], and [washer/dryer required]. These are the fields weighted most heavily in Compatibility Engine scoring."

---

**Q5: What is a tenant agent auction and how does it work?**
- Category: `educational`
- Required data: None (platform documentation)
- Condition: Always show for first-time viewers of a tenant criteria listing (session-scoped, max once per session).

---

**Q6: What should I add to help landlords better understand what I need?**
- Category: `missing_data`
- Required data: Listing record fields, validation rule set
- Condition: Show when one or more fields frequently referenced by landlords are empty.
- Example dynamic text: "Landlords frequently look for [employment status], [desired lease term], and [number of occupants] when evaluating tenant applications. These fields are currently blank on your listing."

---

**Q7: What compatibility factors matter most when landlords evaluate tenant criteria?**
- Category: `educational`
- Required data: None (platform documentation); optionally augmented with Compatibility Engine factor weights if available
- Condition: Show for tenants who have not yet received any bid responses.
- Example dynamic text: "The Compatibility Engine weighs [budget alignment], [lease term match], and [move-in date compatibility] most heavily when comparing tenant criteria to landlord listings. Pet policy and utility preferences are secondary factors."

---

## 6. Dynamic Generation Rules

The Suggested Questions Engine generates personalized question text by combining approved question templates with real data from the Ask AI context object. This section defines the rules governing that process.

### 6.1 Context Object Dependency

The engine receives a structured context object assembled by the Ask AI context assembly layer (Phase 1 of the Ask AI build order). The context object includes the following keys relevant to question generation:

| Context Key | Source | Used For |
|---|---|---|
| `listing_type` | Resolved from URL / listing record | Selecting the correct question set |
| `listing_id` | Request context | Fetching listing fields for substitution |
| `user_role` | Authenticated user session | Role-scoping which questions are shown |
| `property_intelligence` | Property DNA engine output | `property_standout` question personalization |
| `location_intelligence` | Location DNA engine output | Proximity and comparison questions |
| `avatar_output` | Avatar Engine output | `suited_audience` question personalization |
| `compatibility_data` | Compatibility Engine output | `buyer_tenant_match` and `compatibility_signals` personalization |
| `marketing_intelligence` | Marketing Intelligence Engine output | `marketing_angles` question personalization |
| `listing_fields` | Listing database record | Field-level null detection and substitution |

### 6.2 Template Substitution Rules

When dynamic data is available, approved question templates use substitution placeholders to produce personalized question text. Substitution must follow these rules:

1. **Use only approved context keys.** No data source outside the context object may be used for substitution.
2. **Substitute real values, never invented values.** If a placeholder's value is null or absent, do not guess or approximate — apply the fallback rule (see Section 6.3).
3. **Substitution is display-only.** Substituted values are used to render the question text shown to the user. They do not change the question's classification type.
4. **Substituted values must not introduce prohibited content.** If a substituted field value contains demographic steering language, the question must be withheld and the static fallback used instead.
5. **Numeric values must be formatted for readability.** Square footage: comma-separated integer (e.g., "2,100 sq ft"). Currency: dollar-formatted (e.g., "$1,850/month"). Percentages: one decimal place (e.g., "3.5%").

### 6.3 Fallback Rules

When one or more required context keys are missing or null, the engine must apply the following fallback rules in order:

1. **Partial substitution:** If some — but not all — required fields for a question are available, use the available fields and omit the missing ones from the question text, provided the resulting question still makes sense and passes compliance checks.
2. **Static fallback:** If partial substitution is not possible (the question requires the missing data to be coherent), replace the dynamic question with the corresponding static fallback from Section 7.
3. **Question suppression:** If no static fallback exists for a question category and the required data is unavailable, suppress the question entirely. Never display a question with a blank or placeholder substitution visible to the user.

### 6.4 Role-Scoping Rules

The engine must scope suggested questions to the viewing user's role. Not all questions are appropriate for all viewers of a given listing.

| Listing Type | Listing Owner Role | Third-Party Viewer Role | Questions Shown to Owner | Questions Shown to Third-Party |
|---|---|---|---|---|
| Seller Offer | Seller | Buyer, buyer agent | All categories | `property_standout`, `suited_audience`, `educational` |
| Buyer Criteria | Buyer | Seller agent | All categories | `buyer_tenant_match`, `compatibility_signals`, `educational` |
| Landlord Offer | Landlord | Tenant, tenant agent | All categories | `property_standout`, `suited_audience`, `educational` |
| Tenant Criteria | Tenant | Landlord, landlord agent | All categories | `buyer_tenant_match`, `compatibility_signals`, `educational` |

`missing_data` and `marketing_angles` questions are shown only to the listing owner (or their authorized agent), never to third-party viewers.

### 6.5 Question Count and Ordering

- **Maximum displayed:** The engine surfaces a maximum of **five (5)** suggested questions at one time.
- **Ordering priority:**
  1. Questions with all required dynamic data available and no fallback needed.
  2. Questions using partial substitution.
  3. Static fallback questions.
  4. Educational questions (shown last).
- Within each priority tier, questions are ordered by category in this sequence: `property_standout`, `suited_audience`, `buyer_tenant_match`, `compatibility_signals`, `missing_data`, `marketing_angles`, `educational`.
- If more than five eligible questions exist after filtering and role-scoping, the engine selects the top five by priority order.

### 6.6 Session Deduplication

- A question that has already been submitted by the user in the current session must not be re-surfaced as a suggestion until the page is refreshed.
- Educational questions are suppressed after the first session in which they are shown to a given user on a given listing type (session-scoped suppression; do not require persistent storage for initial implementation).

---

## 7. Static Fallback Questions

Static fallback questions are used when dynamic data is unavailable or insufficient for personalization. They are pre-written, approved, and require no data substitution. They must pass all compliance checks before display.

### Seller Listing Fallbacks

| ID | Question Text | Category |
|---|---|---|
| Q-S1 | "What are the key features of this property?" | `property_standout` |
| Q-S2 | "How does this home compare to similar listings on the platform?" | `property_standout` |
| Q-S3 | "What type of buyer might find this property a practical fit?" | `suited_audience` |
| Q-S4 | "What information is missing from this listing?" | `missing_data` |
| Q-S5 | "How does the seller agent auction process work?" | `educational` |
| Q-S6 | "What marketing angles could work for this property?" | `marketing_angles` |
| Q-S7 | "What sale terms has the seller specified?" | `property_standout` |

### Buyer Criteria Listing Fallbacks

| ID | Question Text | Category |
|---|---|---|
| Q-B1 | "How complete is my buyer criteria listing?" | `missing_data` |
| Q-B2 | "What are the strongest criteria I've stated in this listing?" | `buyer_tenant_match` |
| Q-B3 | "What should I add to help agents better understand what I'm looking for?" | `missing_data` |
| Q-B4 | "How does the buyer agent auction process work?" | `educational` |
| Q-B5 | "What factors do agents consider most important in a buyer criteria listing?" | `educational` |

### Landlord Listing Fallbacks

| ID | Question Text | Category |
|---|---|---|
| Q-L1 | "What are the key features of this rental property?" | `property_standout` |
| Q-L2 | "How does this rental compare to similar listings in the area?" | `property_standout` |
| Q-L3 | "What type of renter might find this rental a practical fit?" | `suited_audience` |
| Q-L4 | "What information is missing from this listing?" | `missing_data` |
| Q-L5 | "How does the landlord auction process work on this platform?" | `educational` |
| Q-L6 | "What marketing angles could work for this rental?" | `marketing_angles` |
| Q-L7 | "What lease terms has the landlord specified?" | `property_standout` |

### Tenant Criteria Listing Fallbacks

| ID | Question Text | Category |
|---|---|---|
| Q-T1 | "How complete is my tenant criteria listing?" | `missing_data` |
| Q-T2 | "What are the strongest lease requirements I've stated in this listing?" | `buyer_tenant_match` |
| Q-T3 | "What should I add to help landlords better understand what I need?" | `missing_data` |
| Q-T4 | "How does the tenant agent auction process work?" | `educational` |
| Q-T5 | "What compatibility factors matter most when landlords evaluate tenant criteria?" | `educational` |

---

## 8. UI Behavior

This section describes the required behavior of the Suggested Questions UI component as it would appear on listing pages. It is written to give a frontend developer all information needed to implement the component without ambiguity.

### 8.1 Component Location

The Suggested Questions component appears within the Ask AI panel on a listing page, directly above the freeform question input field. It is visible when the Ask AI panel is open and a conversation has not yet been started (empty state), or when the conversation has ended and the user has not typed a new question.

### 8.2 Component States

The component has three states:

| State | Trigger | Display |
|---|---|---|
| **Loading** | Engine is assembling context and scoring questions | Show a skeleton loader with 3 placeholder rows. Do not show question text until ready. |
| **Ready** | Engine has returned ≥1 eligible question | Show up to 5 question chips/buttons. |
| **Empty** | Engine returns 0 eligible questions after all filtering | Show a single static line: "Ask me anything about this listing." No question chips. |

### 8.3 Question Chip / Button Rendering

Each suggested question is rendered as a tappable chip or button with the following properties:

- **Text:** Full question text, truncated at 80 characters with an ellipsis if longer. Full text shown on hover/focus via tooltip.
- **Icon:** A question mark icon (e.g., `?`) or chat bubble icon to the left of the text.
- **Category badge (optional, Phase 2+):** A small label beneath the question text indicating the category (e.g., "Property", "How it works"). Do not show in Phase 1 implementation.
- **Tap/click behavior:** Selecting a chip populates the freeform question input with the full question text and submits it immediately. The chip disappears from the suggestion list for the remainder of the session.
- **Keyboard accessibility:** Each chip must be focusable and activatable via the Enter or Space key.
- **Hover/active state:** Chips must have a distinct hover and active visual state to confirm interactivity.

### 8.4 Refresh Behavior

- Suggested questions are fetched once when the Ask AI panel opens.
- They are not re-fetched automatically during a session unless the user explicitly closes and reopens the panel.
- If the listing record changes (e.g., the owner completes a missing field and saves), the suggestions are refreshed on the next panel open.

### 8.5 Chip Dismissal

- There is no individual "dismiss" control on chips. A chip is removed only when the user selects it.
- There is no "refresh suggestions" control in Phase 1. This may be added in a future phase.

### 8.6 Accessibility Requirements

- The suggestions block must have an ARIA label: `aria-label="Suggested questions for this listing"`.
- Each chip must have `role="button"` and a descriptive `aria-label` matching the full (non-truncated) question text.
- The suggestions block must be announced to screen readers when it loads (use `aria-live="polite"` on the container).
- Focus must not auto-move to the suggestions block on panel open; the user must tab into it.

### 8.7 Mobile Behavior

- On mobile viewports (< 768px), chips are displayed in a horizontal scroll row if there are 3 or more, or stacked vertically if there are 1–2.
- Horizontal scroll must not trap keyboard focus.
- Touch targets must be at least 44×44px.

### 8.8 Error Handling

- If the engine call fails (network error, timeout, server error), display the empty state ("Ask me anything about this listing."). Do not show an error message in the suggestions area — log the error server-side.
- If the engine call takes longer than 2 seconds, transition from the loading skeleton to the empty state and allow the user to type a freeform question. Do not block interaction.

---

## 9. Compliance Guardrails

### 9.1 Fair Housing Act Compliance

All suggested questions and their resulting AI responses must comply with the **Fair Housing Act (42 U.S.C. § 3604)** and all applicable state and local fair housing laws.

**Before any question is added to the approved template pool:**
- It must be reviewed against the prohibited categories in Section 4.
- It must not contain language that references, implies, or could elicit protected class characteristics.
- It must not use coded language that functions as a proxy for demographic characteristics.

**At runtime:**
- If dynamic substitution would introduce protected class language (e.g., a listing description field contains demographic references), the question must be withheld and the static fallback used.
- The Suggested Questions Engine must never produce a question that triggers a refusal under `ASK_AI_REFUSAL_POLICY.md`. Prohibited questions are screened out before display, not after.

**Required fair housing notice:** The fair housing notice defined in `ASK_AI_QUESTION_CLASSIFICATION_SPEC.md` Section "Fair Housing Guardrails" must appear on the listing page near the Ask AI panel. It need not be displayed per-suggestion, only once per page session.

### 9.2 No Legal, Financial, or Professional Advice

The Suggested Questions Engine must never surface a question that, if answered, would require Ask AI to provide legal, brokerage, lending, tax, or investment advice. This prohibition applies even if the question is phrased as educational or informational in intent but would produce a prohibited response.

**Pre-submission compliance check:** Before surfacing any question — dynamic or static — the engine must verify that the question text does not match any prohibited pattern from `ASK_AI_REFUSAL_POLICY.md`. If a match is found, the question is suppressed without substituting a fallback from the prohibited category.

### 9.3 No Prediction Language

Suggested question text must not contain forward-looking language (will, would, might, going to, expected to) applied to market values, property appreciation, rent trends, or transaction outcomes. The only exception is language referring to the user's own intentions (e.g., "What am I looking for?" is permissible). Questions like "Will this property increase in value?" must never appear in the suggestion pool.

### 9.4 No Cross-User Data Exposure

Suggested questions must not expose one user's private data (bid terms, search profile, contact information) to another user. Role-scoping rules in Section 6.4 enforce this at the question selection layer. The AI response layer enforces this at the response layer. Both layers must enforce the rule independently.

### 9.5 Immutability of Engine Outputs

Suggested questions may reference Property DNA outputs, Location DNA outputs, Avatar outputs, and Compatibility data as subjects of questions (i.e., "What does my Property DNA score mean?"). They must not suggest questions that instruct Ask AI to recalculate, modify, or override any engine output. Suggested questions that imply the user can change a score, avatar classification, or compatibility result by asking Ask AI are prohibited.

### 9.6 Disclosure Requirements

The following disclaimer must appear within the Ask AI panel whenever the Suggested Questions component is visible:

> "Ask AI provides informational summaries based on listing data and platform content only. It is not a licensed real estate broker, attorney, lender, tax advisor, or financial advisor. Nothing here constitutes professional advice. Always consult a qualified professional before making real estate decisions."

This disclaimer is the same as the panel-level disclaimer defined in `ASK_AI_QUESTION_CLASSIFICATION_SPEC.md` and must not be removed, hidden, or minimized when the Suggested Questions component is visible.

### 9.7 Template Governance

All approved question templates are managed in this document and in the platform's approved template configuration. No question template may be added to the live suggestion pool without:

1. Classification review against Section 3 (safe categories) and Section 4 (prohibited categories).
2. Compliance review against fair housing, legal advice, and prediction prohibitions (this section).
3. Explicit approval recorded in this document or in an approved addendum.

Ad hoc or developer-authored questions injected at runtime without template review are prohibited.

---

## 10. Future Implementation Plan

This section outlines the phased rollout plan for the Suggested Questions Engine, consistent with the overall Ask AI build order defined in `ASK_AI_ROADMAP_AND_GUARDRAILS.md`.

### Phase Dependency

The Suggested Questions Engine is a Phase 4 feature (Read-Only UI Surface). It must not be implemented — not even as a stub, skeleton, or disabled component — before Phase 4 is confirmed complete. Specifically:

- Phase 1 (Context Assembly) must be complete: the Ask AI context object must be available and tested.
- Phase 2 (Prompt Engineering) must be complete: response contracts must be defined.
- Phase 3 (OpenAI Wiring) must be complete: AI responses must be generated from structured context.
- Phase 4 (Read-Only UI) must be active: the Ask AI panel must be live and in use.

The Suggested Questions Engine launches as a sub-feature of Phase 4, not as a separate phase.

### Phase 4 — Initial Launch (Static Pool Only)

**Scope:** Surface static fallback questions only (Section 7). No dynamic data substitution. No engine calls for personalization beyond listing type resolution.

**What is built:**
- Suggested Questions UI component in its Ready and Empty states (Section 8).
- Static question pool loaded from configuration, scoped by `listing_type`.
- Role-scoping logic (Section 6.4): owner vs. third-party view.
- Session deduplication: questions disappear after selection within a session.
- All compliance guardrails (Section 9) enforced on the static pool.

**What is not built:**
- Dynamic substitution.
- Per-question engine calls.
- Category badge labels.
- Refresh suggestions control.

**Acceptance criteria:**
- Static suggestions appear for all four listing types.
- Role-scoping correctly hides `missing_data` and `marketing_angles` from third-party viewers.
- Clicking a chip submits the question to Ask AI and removes the chip from the list.
- The panel-level disclaimer is always visible.
- All prohibited question categories are absent from the static pool.

---

### Phase 4+ — Dynamic Personalization Layer

**Scope:** Replace static question text with dynamically personalized versions using real listing data, Property DNA outputs, Location DNA outputs, Avatar outputs, and Compatibility data.

**Prerequisites:**
- Phase 1 context object confirmed stable and passing all data audits.
- Property DNA, Location DNA, and Avatar Engine outputs available for the target listing types.
- Dynamic substitution logic reviewed against compliance guardrails before deployment.

**What is built:**
- Template substitution engine (Section 6.2).
- Fallback logic (Section 6.3): partial substitution → static fallback → suppression.
- Priority ordering (Section 6.5) using dynamic vs. static signal.
- Null detection for all context keys used in substitution.

**Acceptance criteria:**
- Dynamic questions substitute real values correctly for all four listing types.
- Null context keys trigger fallback correctly with no visible blank placeholders.
- Substituted values containing prohibited content are caught and replaced by static fallbacks.
- Question priority ordering matches Section 6.5.

---

### Phase 5 — Question Relevance Scoring

**Scope:** Introduce a lightweight relevance scorer that ranks suggested questions by predicted usefulness to the current user — based on their role, listing completeness, and which engine outputs are available.

**Prerequisites:**
- Dynamic personalization layer (Phase 4+) confirmed stable.
- Minimum 30 days of session data from Phase 4+ (question selection rates per question type per listing type) available for scoring calibration.

**What is built:**
- Relevance scoring model (rule-based in Phase 5; ML-augmented in Phase 6+).
- Scoring inputs: listing completeness score, presence of engine outputs, user role, prior question selections (session-scoped only; no persistent profiling in Phase 5).
- Re-ranked suggestion order replacing the static priority order from Section 6.5.

**Acceptance criteria:**
- `missing_data` questions are surfaced first when listing completeness is below a defined threshold.
- `property_standout` questions with Property DNA data score above generic static fallbacks.
- Educational questions appear last for repeat users who have already seen them.
- Relevance scoring does not incorporate any protected class signal or user demographic data.

---

### Phase 6 — Category Badge Labels and User Controls

**Scope:** Add visible category labels to each suggestion chip, and give users a lightweight control to dismiss suggestions they are not interested in.

**What is built:**
- Category badge display (the optional Phase 2+ element from Section 8.3).
- Per-chip dismiss control (Section 8.5 future capability).
- "Refresh suggestions" control: re-fetches suggestions if the listing record has been updated.
- Persistent session log of dismissed questions (stored in session only; no persistent DB record in Phase 6 unless explicitly scoped and approved).

**Acceptance criteria:**
- Category badges match the question category classification for all suggestions.
- Dismissed chips do not reappear within the session.
- Refresh control re-fetches suggestions and reflects any new engine outputs or listing field completions.

---

### Phase 7 — ML-Augmented Relevance and Feedback Loop

**Scope:** Replace the rule-based relevance scorer with a machine-learning-augmented model trained on anonymized session data. Add a lightweight thumbs-up/thumbs-down signal on Ask AI responses to provide feedback signal for future question pool quality.

**Prerequisites:**
- Phase 5 relevance scoring in place and confirmed stable.
- Platform data retention and privacy policy reviewed for training data use.
- PII stripped from all training examples before model training.

**What is built:**
- ML relevance scoring model consuming anonymized session data.
- Response feedback signal (thumbs up/down) on Ask AI responses.
- Feedback signal pipeline feeding into question pool quality review (human review required before any template changes are made based on feedback).

**Hard guardrails for Phase 7:**
- No persistent user profiling beyond aggregated, anonymized session signals.
- No protected class signal may be used in training data or scoring inputs.
- Human review is mandatory before any training data is incorporated into the model.
- The feedback loop may inform template quality review but may not automatically alter the approved template pool.

---

*This document is authoritative for the Suggested Questions Engine. Any implementation of suggested question logic must be consistent with this specification and with the broader Ask AI governance documents listed in Section 1. In any conflict between this document and implementation code, this document governs.*
