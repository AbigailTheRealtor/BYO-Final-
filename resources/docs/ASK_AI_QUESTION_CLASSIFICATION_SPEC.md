# Ask AI — Question Classification Specification

**Version:** 1.0  
**Status:** Approved  
**Applies to:** Bid Your Offer — Ask AI feature  

---

## Table of Contents

1. [Purpose](#purpose)
2. [Approved Question Types](#approved-question-types)
   - [property_standout](#1-property_standout)
   - [suited_audience](#2-suited_audience)
   - [buyer_tenant_match](#3-buyer_tenant_match)
   - [compatibility_signals](#4-compatibility_signals)
   - [missing_data](#5-missing_data)
   - [marketing_angles](#6-marketing_angles)
   - [educational](#7-educational)
   - [prohibited](#8-prohibited)
3. [Unsupported Question Behavior](#unsupported-question-behavior)
4. [Future Classifier Service Plan](#future-classifier-service-plan)
5. [Fair Housing Guardrails](#fair-housing-guardrails)
6. [No Legal / Brokerage / Lending / Tax / Investment Advice](#no-legal--brokerage--lending--tax--investment-advice)

---

## Purpose

This document defines the classification contract for the **Ask AI** feature on the Bid Your Offer platform. It is the authoritative reference for:

- **Developers** implementing or extending the AI question-routing layer.
- **Reviewers and QA engineers** validating that AI responses stay within approved boundaries.
- **AI integrators** configuring prompts, retrieval pipelines, or external model APIs.
- **Compliance stakeholders** confirming that fair housing rules and professional-advice limits are consistently enforced.

The Ask AI feature allows users (sellers, buyers, landlords, tenants, and agents) to ask natural-language questions about a specific listing or about the platform's auction mechanics. Every incoming question must be resolved to exactly one of the eight approved question types below before a response is generated. Questions that cannot be mapped to an approved type are treated as **unsupported** (see [Unsupported Question Behavior](#unsupported-question-behavior)).

---

## Approved Question Types

### 1. `property_standout`

**Definition**  
Questions asking what makes a specific property notable, distinctive, or competitive relative to comparable listings or the current market. The focus is on observable property attributes, not on demographic suitability or investment projections.

**Example Questions**
- "What stands out about this property compared to similar listings?"
- "Does this home have any features that are uncommon in this price range?"
- "Why might a buyer choose this listing over others at the same price point?"
- "Is the lot size here above or below average for this zip code?"

**Allowed Data Sources**
- Listing fields on the current record (square footage, lot size, bedroom/bathroom count, year built, amenities, condition notes, property type).
- Platform-aggregated statistics for comparable listings in the same market segment (price range, property type, geographic area) — aggregate only, never individual third-party records.
- Agent-supplied listing description and marketing notes.

**Prohibited Handling**
- Must not reference neighborhood demographics, school district rankings tied to racial or ethnic composition, or crime statistics.
- Must not make investment or appreciation projections ("this property will increase in value").
- Must not compare the listing to specific competing listings by address or owner identity.

**Required Disclosures**
> "This summary is based on the listing data provided and aggregated market statistics. It is not a professional appraisal or comparative market analysis. Consult a licensed appraiser or real estate professional for a formal valuation."

---

### 2. `suited_audience`

**Definition**  
Questions about which broad lifestyle profiles or household compositions might find this property a practical fit, based solely on objective property characteristics (size, layout, accessibility features, proximity to transit, etc.).

**Example Questions**
- "Who is this property generally suited for?"
- "Would this work well for someone who works from home?"
- "Is this layout practical for a multi-generational household?"
- "Is this property accessible for someone with mobility limitations?"

**Allowed Data Sources**
- Listing fields: layout, bedroom/bathroom count, floor plan type, accessibility features noted by the seller/agent, garage or parking details, proximity metadata (transit stop distance, walkability score if available in listing data).
- Agent-supplied notes about the property's practical attributes.

**Prohibited Handling**
- Must not suggest or imply that the property is more or less suited for any person based on race, color, national origin, religion, sex, familial status, disability, or any other class protected under the Fair Housing Act or applicable state law.
- Must not use coded language that serves as a proxy for protected class characteristics (e.g., "great for young professionals in a vibrant neighborhood" when "vibrant" carries demographic connotation in context).
- Must not comment on neighborhood "feel," "character," or "community" in ways that implicitly signal protected-class composition.

**Required Disclosures**
> "These observations are based on the property's physical characteristics as described in the listing. They do not constitute a recommendation for or against any individual or group. All buyers and renters are welcome to inquire about any property regardless of background."

---

### 3. `buyer_tenant_match`

**Definition**  
Questions about how closely a specific buyer's or tenant's stated requirements align with a specific listing's documented terms and attributes. Matching is performed on objective criteria only (price range, square footage, bedroom count, lease term, pet policy, etc.).

**Example Questions**
- "Does this listing match my saved search criteria?"
- "How well does this property fit what I said I'm looking for?"
- "Does the asking price fall within the budget I submitted in my bid?"
- "Does the landlord's pet policy allow for the pet type I listed?"

**Allowed Data Sources**
- The authenticated user's saved search profile or submitted bid terms.
- The current listing's documented terms (price, size, lease length, pet policy, included utilities, available date, property type).

**Prohibited Handling**
- Match scoring must be computed only on objective, non-demographic fields.
- Must not factor in, display, or infer any match score component based on a protected class characteristic of the buyer/tenant or of the listing's surrounding population.
- Must not expose another user's private bid data to the inquiring user.

**Required Disclosures**
> "Match scoring compares your submitted requirements against the listing's documented terms. It does not reflect any personal assessment of you as an applicant and is not a guarantee of approval or suitability."

---

### 4. `compatibility_signals`

**Definition**  
Questions about whether an agent's stated service offering or bid terms are compatible with a seller's or landlord's listing requirements — used primarily in the agent auction context to help sellers evaluate agent bids.

**Example Questions**
- "Does this agent's commission structure match what I said I'm willing to pay?"
- "Is this agent's proposed marketing plan consistent with the services I requested?"
- "Do the agent's credentials meet the minimum requirements I set in my listing?"
- "Are there any gaps between what this agent is offering and what I asked for?"

**Allowed Data Sources**
- The listing owner's documented listing requirements (service type, commission cap, required credentials, geographic coverage).
- The agent's submitted bid fields (commission rate, services offered, credentials, referral percentage, turnaround commitments).
- `AgentDefaultProfile.profile_data` fields as populated in the bid (read-only; never modify).

**Prohibited Handling**
- Must not evaluate agent bids based on the agent's or client's demographic characteristics.
- Must not make qualitative judgments about an agent's personal character, reputation, or reviews beyond the objective fields present in the bid record.
- Must not expose one agent's bid data to another agent.

**Required Disclosures**
> "Compatibility signals are computed from the submitted bid and listing requirement fields only. They are informational and do not constitute a professional recommendation. The listing owner is solely responsible for selecting an agent."

---

### 5. `missing_data`

**Definition**  
Questions or system-triggered prompts identifying fields in a listing or bid that are blank, incomplete, or inconsistent, and that are typically required for a complete submission or for accurate AI analysis.

**Example Questions**
- "What information is missing from this listing?"
- "Are there any fields I haven't filled in yet?"
- "Why can't the AI give me a full analysis of this property?"
- "What do I still need to add before I can submit?"

**Allowed Data Sources**
- The current listing or bid record's field values (null, empty string, or placeholder detection).
- The platform's validation rule set for the relevant form type (seller auction, buyer auction, landlord auction, tenant auction, agent bid).

**Prohibited Handling**
- Must not fabricate or guess field values when they are missing.
- Must not reveal another user's record state to the inquiring user.
- Must not block the user from continuing based on missing optional fields — only flag required fields.

**Required Disclosures**
> "This completeness check is based on the current state of the form fields. Completing all recommended fields improves the accuracy of AI-generated summaries and increases visibility to interested parties."

---

### 6. `marketing_angles`

**Definition**  
Questions requesting AI-generated marketing copy suggestions, headline ideas, or positioning strategies for a listing, based on the listing's documented attributes.

**Example Questions**
- "Can you suggest a headline for this listing?"
- "What are the strongest selling points I should highlight in my description?"
- "Help me write a short paragraph that describes this property's best features."
- "What marketing angle would resonate most for this type of property?"

**Allowed Data Sources**
- All non-private listing fields: property description, amenities, size, age, upgrades, location (city/state level), listing price or rent range.
- Agent-supplied notes.

**Prohibited Handling**
- Generated copy must not include demographic steering language (e.g., "perfect for families with children," "ideal for young couples," "great for professionals of a certain background").
- Must not reference school district names in ways that correlate with racial composition.
- Must not make legally actionable claims about the property (e.g., "flood-free," "structurally sound") without the seller's documented disclosure.
- Must not fabricate amenities or features not present in the listing data.

**Required Disclosures**
> "Marketing copy suggestions are AI-generated based on the listing data you provided. Review all suggested text for accuracy before publishing. Do not include claims you cannot support with documentation."

---

### 7. `educational`

**Definition**  
Questions about how the Bid Your Offer platform works, auction mechanics, terminology definitions, process steps, or general real estate concepts that the platform covers.

**Example Questions**
- "How does the seller agent auction process work?"
- "What is a buyer agent auction?"
- "What does 'accepted bid summary' mean?"
- "How long does the bidding period last?"
- "What is a referral percentage and when does it apply?"

**Allowed Data Sources**
- Platform documentation, help center content, and this specification.
- General real estate terminology definitions from publicly authoritative sources (NAR glossary equivalents).
- The authenticated user's own listing or bid data, when the question is about interpreting their specific record.

**Prohibited Handling**
- Must not provide legal interpretations of contracts, disclosures, or statutes.
- Must not give advice on whether a specific auction structure is right for the user's situation.
- Must not fabricate platform features or policies that do not exist.

**Required Disclosures**
> "This explanation describes general platform mechanics and common real estate terminology. For legal, tax, or professional advice specific to your situation, consult a licensed professional."

---

### 8. `prohibited`

**Definition**  
Questions that explicitly request, or whose likely response would constitute, one or more of the following: fair housing violations, legal advice, brokerage advice, lending advice, tax advice, investment advice, or any other category explicitly prohibited by this specification. Questions in this category must never receive a substantive AI-generated answer.

**Example Questions**
- "Is this a good neighborhood for people like me?" *(demographic steering)*
- "Should I avoid this area because of the school demographics?" *(protected class signal)*
- "Can I legally break my lease if the landlord doesn't fix the heat?" *(legal advice)*
- "Will I qualify for a mortgage on this property?" *(lending advice)*
- "Is this a good investment? Will it appreciate?" *(investment advice)*
- "How do I avoid capital gains tax when I sell?" *(tax advice)*
- "Do I need a lawyer to review this contract?" *(legal referral boundary — redirect only)*

**Allowed Data Sources**
- None. No listing or user data is retrieved or referenced in the response.

**Prohibited Handling**
- Must not generate a substantive answer to any question in this category.
- Must not use retrieved listing data to partially answer a prohibited question.
- Must not soft-answer by framing prohibited content as "general information."

**Required Response**
The system must return a fixed, non-AI-generated refusal message. See [No Legal / Brokerage / Lending / Tax / Investment Advice](#no-legal--brokerage--lending--tax--investment-advice) for the exact refusal language. For fair-housing-related questions, also include the fair housing notice from [Fair Housing Guardrails](#fair-housing-guardrails).

---

## Unsupported Question Behavior

A question is **unsupported** when it cannot be confidently mapped to any of the eight approved types above. This includes ambiguous, out-of-domain, or nonsensical inputs.

### Classification Threshold

The classifier must assign a confidence score to each candidate type. If no type reaches the minimum confidence threshold (implementation-defined; recommended ≥ 0.65), the question is treated as unsupported.

### Required Response for Unsupported Questions

The system must return a static fallback message — no AI-generated content, no retrieval, no hallucination:

> "I'm not sure how to help with that question in the context of this platform. I can answer questions about property details, how the auction process works, or how your requirements compare to a listing. If you need help with a legal, tax, or financial matter, please consult a licensed professional."

### No Hallucination Rule

The system must never generate a speculative or fabricated answer when classification fails. Returning the fallback message is always the correct behavior for unsupported questions.

### Escalation Path

If a user receives the fallback message and still needs help:
1. The UI must display a link to the platform's help center or contact page.
2. The question (stripped of any PII) may be logged for review by the product team to inform future classifier training.
3. No escalation to a human agent is automated — the user must initiate contact through the standard support channel.

---

## Future Classifier Service Plan

### Overview

The current classification approach is embedded in the Ask AI prompt layer. The long-term plan is to extract classification into a **standalone classifier module** that can be versioned, tested, and swapped independently of the response-generation layer.

### Planned Architecture

| Component | Description |
|---|---|
| **Classifier Input** | Raw user question string + context object (listing ID, user role, current form step) |
| **Classifier Output** | `{ type: string, confidence: float, requires_data: boolean, prohibited: boolean }` |
| **Transport** | Internal HTTP call or queue message to a dedicated classifier endpoint |
| **Fallback** | If the classifier is unreachable, treat the question as `educational` if it contains platform keywords, otherwise return the unsupported fallback message |

### Input Schema (Draft)

```json
{
  "question": "string (max 1000 chars)",
  "context": {
    "listing_id": "integer | null",
    "user_role": "seller | buyer | landlord | tenant | agent | admin",
    "form_step": "string | null",
    "session_id": "string (opaque, for dedup)"
  }
}
```

### Output Schema (Draft)

```json
{
  "type": "property_standout | suited_audience | buyer_tenant_match | compatibility_signals | missing_data | marketing_angles | educational | prohibited | unsupported",
  "confidence": 0.0,
  "requires_data": true,
  "prohibited": false,
  "classifier_version": "string"
}
```

### Versioning Approach

- The classifier is versioned with a **semver string** (e.g., `1.0.0`) included in every output payload.
- Breaking changes to the output schema (new required fields, removed fields, renamed types) increment the **major** version.
- New question types or confidence tuning changes increment the **minor** version.
- Bug fixes and threshold adjustments increment the **patch** version.
- The consuming response layer must check `classifier_version` and reject responses from incompatible major versions rather than silently misrouting questions.
- All prior classifier versions must be logged and retained for at least 90 days to support auditability of past AI responses.

### Training Data Requirements

- Each unsupported or misclassified question (flagged by product review) becomes a candidate training example.
- Training data must be reviewed and approved by a human before inclusion.
- No PII may be present in training examples.

---

## Fair Housing Guardrails

### Governing Authority

The AI must comply with the **Fair Housing Act (42 U.S.C. § 3604)** and all applicable state and local fair housing laws. The platform operates exclusively in the United States.

### Protected Classes

The AI must never produce output that discriminates against, steers toward or away from, or implies preference based on any of the following characteristics:

| Category | Examples |
|---|---|
| Race | Any racial classification |
| Color | Skin tone or complexion |
| National Origin | Country of birth, ancestry, language |
| Religion | Any religious affiliation or lack thereof |
| Sex | Gender, gender identity, sexual orientation (where state law extends protection) |
| Familial Status | Presence of children under 18, pregnancy, custody |
| Disability | Physical or mental disability, use of assistive devices |
| State/Local Extensions | Marital status, source of income, immigration status, age (varies by jurisdiction) |

### Prohibited AI Behaviors

1. **Demographic steering** — suggesting that a property is more or less desirable based on the demographic composition of residents, neighbors, or the surrounding community.
2. **Coded language** — using terms that function as proxies for protected characteristics (e.g., "exclusive," "quiet," "urban," "diverse," "traditional" when used to signal protected-class composition rather than factual property attributes).
3. **School district signaling** — referencing school district rankings or names in a context that correlates with racial or ethnic composition rather than factual academic performance data.
4. **Selective disclosure** — describing neighborhood attributes differently for different users based on the user's inferred or stated demographic characteristics.
5. **Redlining proxies** — using zip code, census tract, or geographic identifiers as a basis for limiting or expanding the information presented, unless the limitation is strictly tied to the listing's actual location data.

### Required Neutral Language Standards

- Property descriptions must use physical, measurable, or documented attributes only.
- Neighborhood references must be limited to factual, verifiable data points present in the listing (e.g., distance to transit, walk score if in listing data) — not qualitative assessments of community character.
- Any AI-generated marketing copy (type: `marketing_angles`) must be reviewed against these standards before it is presented to the user.

### Fair Housing Notice

The following notice must be appended to any AI response that touches neighborhood, community, or suitability topics:

> "Bid Your Offer is committed to fair housing. We do not discriminate on the basis of race, color, national origin, religion, sex, familial status, disability, or any other characteristic protected by law. All properties on this platform are available to all qualified buyers and renters."

---

## No Legal / Brokerage / Lending / Tax / Investment Advice

### Scope of Prohibition

The Ask AI feature is an **informational tool only**. It does not hold any professional license and is not a substitute for advice from a licensed professional. The following advice categories are strictly prohibited:

| Advice Category | Examples |
|---|---|
| **Legal advice** | Contract interpretation, disclosure obligations, lease break rights, eviction procedures, title disputes |
| **Brokerage advice** | Whether to list, when to list, which offer to accept, negotiation strategy specific to the user's situation |
| **Lending / mortgage advice** | Loan qualification, mortgage rate recommendations, financing structure, debt-to-income assessment |
| **Tax advice** | Capital gains treatment, 1031 exchange eligibility, deductibility of costs, depreciation schedules |
| **Investment advice** | Property appreciation projections, return on investment estimates, buy-vs-rent recommendations for investment purposes |

### Refusal Language

When a question is classified as `prohibited` due to one or more of the above categories, the system must return the following message verbatim (substituting the bracketed category as appropriate):

> "I can't provide [legal / brokerage / lending / tax / investment] advice. For questions like this, please consult a licensed [attorney / real estate broker / mortgage professional / tax advisor / financial advisor]. I'm happy to help with questions about this listing's features, how the platform works, or how your requirements compare to what's available."

### Redirect Obligation

After delivering the refusal, the AI must offer to re-engage on an approved question type. It must not:
- Attempt to partially answer the prohibited question as "general information."
- Suggest that the user rephrase in a way that would produce the same substantive prohibited answer.
- Provide links to third-party content that constitutes prohibited advice.

### Disclaimer on All Responses

The following disclaimer must appear on every Ask AI response panel, regardless of question type:

> "Ask AI provides informational summaries based on listing data and platform content only. It is not a licensed real estate broker, attorney, lender, tax advisor, or financial advisor. Nothing here constitutes professional advice. Always consult a qualified professional before making real estate decisions."
