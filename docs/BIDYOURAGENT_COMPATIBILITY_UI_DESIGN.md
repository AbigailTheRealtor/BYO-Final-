# BidYourAgent — Professional Representation Compatibility: Consumer UI Design

**Document Status:** Read-only planning document. No code, schema, migration, route, controller, Blade, Livewire, config, or database file was created or modified to produce this document. No implementation occurs in this phase.
**Document Date:** 2026-05-28
**Phase:** F — Consumer Compatibility UI Design
**Preceding Phase:** E — AI Explanation Layer Design (`docs/BIDYOURAGENT_AI_EXPLANATION_LAYER.md`)
**Succeeding Phase:** G — Implementation Architecture (blocked — see Section 7 and closing statement)

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Section 1 — Consumer-Facing Compatibility Summary](#section-1--consumer-facing-compatibility-summary)
3. [Section 2 — Bid Card Integration](#section-2--bid-card-integration)
4. [Section 3 — Side-by-Side Comparison View](#section-3--side-by-side-comparison-view)
5. [Section 4 — Per-Trait Explanation Access](#section-4--per-trait-explanation-access)
6. [Section 5 — AI Explanation Display Rules](#section-5--ai-explanation-display-rules)
7. [Section 6 — Visual and UI Standards](#section-6--visual-and-ui-standards)
8. [Section 7 — Governance and Compliance Notes](#section-7--governance-and-compliance-notes)
9. [Section 8 — Implementation Handoff Notes for Phase G](#section-8--implementation-handoff-notes-for-phase-g)

---

## 1. Executive Summary

This document is the Phase F deliverable of the BidYourAgent Professional Representation Compatibility system. Its sole purpose is to design the consumer-facing interface through which representation compatibility information is presented when a consumer reviews agent bids. **No implementation occurs in this phase.** This document produces no code, no schema, no migration, no Livewire component change, no Blade markup, no validation rule, no scoring formula, no AI prompt, and no database column.

### Phases A–E: Findings Restated

The five preceding phases established the complete foundation that Phase F's UI design must surface to consumers.

**Phase A** (`docs/BIDYOURAGENT_COMPATIBILITY_AUDIT.md`) conducted an exhaustive audit of all consumer-side compatibility fields across the four Hire Agent listing flows. The central finding: **75 consumer-side compatibility fields are collected across all four roles** (22 Seller, 17 Buyer, 16 Landlord, 20 Tenant), while all four agent bid Livewire components collect zero matching fields. Consumer compatibility data is gathered and persisted but drives no matching, scoring, or display logic. The `listing_compatibility_scores` table has no `representation_compatibility_score` column.

**Phase B** (`docs/BIDYOURAGENT_COMPATIBILITY_TRAIT_DESIGN.md`) took the 75 raw consumer fields and normalized them into a **12-trait ontology** called Professional Representation Compatibility. Phase B resolved four confirmed naming inconsistencies in the consumer-side raw data, produced per-role raw field crosswalks, and confirmed that zero agent-side counterparts exist for any of the 12 normalized traits.

The 12 normalized traits are:

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

**Phase C** (`docs/BIDYOURAGENT_AGENT_RESPONSE_DESIGN.md`) designed the conceptual agent-side counterpart response structure organized into seven logical sub-sections under `compatibility_preferences.agent_response.*`. Phase C designated all 12 traits as score-eligible or conditionally score-eligible, with `risk_tolerance` and `representation_philosophy` designated conditionally pending Phase D determination.

**Phase D** (`docs/BIDYOURAGENT_COMPATIBILITY_SCORING_FRAMEWORK.md`) conceptually designed the four-layer compatibility scoring framework: Layer 1 (consumer normalized traits), Layer 2 (agent normalized responses), Layer 3 (per-trait comparison results), and Layer 4 (explainability layer outputs). Phase D defined five alignment categories: full alignment, partial alignment, adjacent compatibility, neutral compatibility, and incompatible alignment. Phase D flagged `risk_tolerance` and `property_strategy_fit` as requiring proxy-risk analysis before any scored use. Phase D confirmed that the `listing_compatibility_scores` table will require a `representation_compatibility_score` column, a per-trait comparison result storage schema, and a `scoring_framework_version` increment.

**Phase E** (`docs/BIDYOURAGENT_AI_EXPLANATION_LAYER.md`) designed the AI explanation layer: the system that translates per-trait comparison results into plain-language explanations. Phase E established that AI is strictly downstream from scoring — it consumes Layer 4 outputs and produces plain-language explanations — and may not override, re-weight, or substitute for comparison results. Phase E defined the complete governance framework for AI outputs, including the absolute prohibition on recommendations, rankings, "best agent" language, demographic profiling, and steering.

### Phase F: Sole Purpose

Phase F designs the consumer-facing UI through which the compatibility signal from Phase D and the plain-language explanations from Phase E are presented to consumers when they review agent bids. Phase F is a planning document. It defines layout, information hierarchy, interaction patterns, display rules, disclosure placement, and visual standards — none of which are implemented here.

---

## Section 1 — Consumer-Facing Compatibility Summary

### 1.1 Qualitative Label System

The consumer-facing compatibility summary must be expressed as a qualitative label. Qualitative labels communicate the overall picture of alignment across scored traits in a form that is immediately readable without requiring the consumer to interpret a number, a percentage, or a composite score.

The following four labels are proposed for the consumer-facing compatibility summary. These labels directly reflect the aggregate distribution of per-trait alignment results produced by the Phase D Layer 4 explainability layer.

| Label | Intended Meaning | When to Use |
|---|---|---|
| **Strong Alignment** | Most scored traits show full or partial alignment between the consumer's stated preferences and the agent's stated working style. Few or no traits show adjacent, neutral, or incompatible alignment. | The majority of scored traits are at full alignment or partial alignment, with no significant misalignments present. |
| **Broad Compatibility** | A meaningful number of scored traits show alignment, but some show adjacent compatibility or neutral compatibility. No severe misalignments. The agent's overall working style is broadly consistent with the consumer's preferences. | A mix of full alignment, partial alignment, and adjacent/neutral results, with no incompatible alignment results. |
| **Notable Differences** | Several scored traits show adjacent compatibility or incompatible alignment. The consumer and agent have meaningful differences in stated preferences that are worth reviewing and discussing before a hiring decision. | One or more traits show incompatible alignment, or a pattern of adjacent compatibility suggests meaningful working-style differences. |
| **Insufficient Data** | Not enough scored trait comparisons exist to produce a meaningful summary — either the consumer answered very few optional compatibility questions, the agent left most response fields unanswered, or a combination of both. | Fewer than the minimum number of scored trait comparisons required to produce a reliable summary. The minimum threshold is a Phase G implementation decision. |

**Label design rules:**

- Labels must be text-only or accompanied by a neutral visual indicator (see Section 6). They must not carry rank-implying language.
- No label may be "best," "top," "highest," "recommended," or any superlative.
- The "Strong Alignment" label does not mean the agent is the best agent for the consumer — it means that stated preferences and agent responses are closely aligned on the scored traits. This must be stated in the accompanying advisory note (see Section 1.3).
- The "Notable Differences" label does not mean the agent is a poor choice — it means the consumer should review the differences and consider discussing them directly with the agent. This must be stated in the accompanying advisory note.
- The label set is intentionally small and non-granular. A four-label set preserves the advisory nature of the signal and avoids creating a false impression of scoring precision.

### 1.2 Optional Range or Percentage Display

A percentage or range display — for example, "approximately 70% of working-style traits aligned" — may be appropriate as secondary supplementary context alongside the qualitative label, subject to the following conditions:

**Conditions under which percentage display is appropriate:**

1. A sufficient number of scored trait comparisons exist (minimum threshold to be determined in Phase G). Displaying a percentage from two or three scored traits is misleading.
2. The percentage is visually secondary to the qualitative label — it supplements the label, not replaces it.
3. The percentage is accompanied by a plain-language explanation of what it represents and what it does not represent (see required disclosure in Section 5.3).
4. The percentage is not displayed with false precision. A rounded value or a range (e.g., "roughly 6 of 9 areas aligned") is preferable to a precise decimal percentage.
5. The percentage cannot be used to rank agents in any automatic or algorithmic way. The consumer may use it as one piece of information, but the platform must not sort, order, or filter agent bid lists by compatibility percentage without explicit consumer-initiated sort control.

**Conditions under which percentage display is NOT appropriate:**

- When the `Insufficient Data` label applies.
- When one or both of the proxy-risk-flagged traits (`risk_tolerance`, `property_strategy_fit`) are the primary or sole drivers of the percentage value and proxy-risk analysis has not yet been completed.
- When a percentage would suggest a level of analytical precision that the current trait coverage does not support.

The decision about whether to implement percentage display is deferred to Phase G (implementation architecture). Phase F notes it as a conditional option, not a requirement.

### 1.3 Advisory-Only Language Requirements

Every consumer-facing compatibility summary must be accompanied by an advisory statement that makes the non-ranking, non-recommendation nature of the information explicit. The following rules govern advisory language requirements at the summary level:

**Required advisory statement elements:**

1. The compatibility summary is based on a comparison of the consumer's stated preferences and the agent's stated responses to a defined set of professional working-style traits.
2. The summary is advisory only. It does not recommend, rank, or endorse any agent.
3. The consumer's hiring decision remains entirely theirs.
4. Compatibility is one factor among many. Other important factors include the agent's services, compensation, experience, credentials, and the consumer's own direct conversation with the agent.

Compliant advisory statement example (conceptual illustration, not final UI copy):

> *"This compatibility summary reflects how your stated working-style preferences compare to this agent's stated approach. It is advisory only and does not rank agents or recommend one over another. Please review this agent's full proposal, services, compensation, and credentials before making any decision."*

**Prohibited summary language (at all times):**

- "Best match" or "top match" — ranking language, prohibited.
- "Recommended" or "platform recommends" — endorsement language, prohibited.
- "Most compatible" in comparison to other agents — cross-bid ranking, prohibited.
- "Perfect fit" or "ideal for you" — certainty and endorsement, prohibited.
- "Not compatible" — disqualification language, prohibited. Differences exist on certain traits; the agent is not incompatible as a professional.
- Any language that implies the consumer should or should not hire this agent based on compatibility information alone.

---

## Section 2 — Bid Card Integration

### 2.1 Placement Within the Bid Card

The compatibility summary must appear within each agent's bid card in the existing bid list view. Its placement must be designed to prevent it from being perceived as the primary or most important dimension of the agent's bid. Compatibility is one advisory signal; the agent's services, compensation, and qualifications are independent factors of equal or greater weight for most consumers.

**Proposed placement:**

The compatibility summary should appear below the agent's core proposal elements — services listed, compensation terms, and experience/credentials — and above or adjacent to the bid's call-to-action elements (contact agent, view full bid). This placement communicates that compatibility is supplementary context, not the leading criterion for evaluation.

The compatibility summary must not appear:
- At the top of the bid card, above services and compensation.
- In a visually dominant position that draws the eye before other bid factors.
- In a way that implies a total bid score or aggregate bid quality assessment.

### 2.2 Compatibility Summary on the Bid Card

The bid card should display the following compatibility elements at the card level:

1. **The qualitative label** (e.g., "Broad Compatibility") using the label system from Section 1.1.
2. **A one-line advisory gloss** — a single sentence clarifying that the label reflects working-style preference alignment, not a recommendation.
3. **An expand/detail trigger** — a link, button, or chevron that the consumer can tap or click to access the full per-trait explanation (see Section 4). The trigger must use plain language such as "See how working styles compare" or "View compatibility details" — not "View score" or "Compatibility breakdown" (which implies scoring precision).

The compatibility summary on the bid card must not display:
- A percentage or numerical score as the primary element (percentage may appear as secondary context only — see Section 1.2).
- A bar, meter, or gauge that implies a relative rank compared to other bids.
- Individual trait results — these belong in the expanded per-trait view (Section 4), not on the card summary.

### 2.3 Positioning Relative to Services, Compensation, Experience, and Terms

The bid card contains multiple information layers. Compatibility information must be positioned to avoid creating the impression that working-style alignment outweighs practical bid factors. The following ordering principle applies:

**Recommended information hierarchy within the bid card:**

1. Agent identity, credentials, and experience (always at top — establishes who this agent is)
2. Services offered (what the agent will do)
3. Compensation and terms (what the consumer pays and on what terms)
4. Compatibility summary — advisory working-style alignment signal (supplementary context)
5. Call to action (contact agent, view full bid, expand proposal)

This ordering ensures that a consumer reads the agent's qualifications, services, and compensation before they reach the compatibility summary. It prevents compatibility from substituting for evaluation of the agent's actual proposal.

### 2.4 Suggested Expand Trigger Language

The trigger that opens the per-trait compatibility detail (Section 4) should use language that is:
- Plain and descriptive (not numeric or algorithmic)
- Non-leading (does not imply what the consumer will find)
- Brief (one short phrase)

Compliant trigger examples:
- "See working-style comparison →"
- "View compatibility details"
- "How working styles compare"
- "Explore representation preferences"

Non-compliant trigger examples:
- "View score" — implies numerical precision, prohibited
- "Why this is your best match" — ranking language, prohibited
- "See compatibility rating" — implies a rating system, prohibited
- "87% compatible" as a trigger — leads with a number and no context, prohibited

---

## Section 3 — Side-by-Side Comparison View

### 3.1 Purpose and Design Principle

The side-by-side comparison view allows the consumer to compare compatibility trait-by-trait across multiple bidding agents. Its purpose is to help the consumer understand, at a detailed level, where their stated preferences align with each agent's stated working style and where meaningful differences exist.

The side-by-side view is an optional, consumer-initiated view. It should not be the default presentation of bid information. The default is the individual bid card (Section 2). The comparison view is a deeper exploration tool.

**Design principle:** The comparison view must be organized around the consumer's stated preferences — not around agent scores or rankings. The consumer's stated preferences appear once (as column or row headers on the consumer side). Each agent's responses appear in parallel columns or rows. The consumer reads across to understand how each agent compares to their own preferences.

The comparison view must never visually imply a winner or a ranked order. Agents must appear in the same order as they do in the standard bid list (which is not determined by compatibility data). The platform may not automatically reorder agents in the comparison view by compatibility signal.

### 3.2 Seven-Trait Comparison Set

The side-by-side comparison view should cover the seven traits that are most universally informative across all four consumer roles and most directly reflect how the working relationship will function day to day:

| # | Trait | What It Compares |
|---|---|---|
| 1 | `communication_frequency` (communication style) | How often the consumer expects proactive contact versus how often the agent's standard practice is to reach out |
| 2 | `negotiation_style` | The consumer's desired negotiation posture versus the agent's stated negotiation approach |
| 3 | `decision_making_style` (decision speed) | The consumer's decision pace — how quickly and through what process they make commitments — versus the agent's stated decision-support approach. Phase B defines this trait as measuring "speed, deliberation, and use of data," making it the direct counterpart to "decision speed." Consumer-side data exists for Seller, Buyer, and Tenant (not Landlord — see Section 3.6). |
| 4 | `risk_tolerance` | The consumer's risk appetite versus the agent's comfort level with risk — displayed as informational only pending proxy-risk analysis (see Section 7.2) |
| 5 | `responsiveness_expectation` (availability/responsiveness) | How quickly the consumer expects responses versus the agent's stated response time commitment |
| 6 | `representation_philosophy` | The consumer's values-level beliefs about what good representation requires, compared to the agent's stated professional values — the structured multi-select component is conditionally score-eligible for Seller and Tenant listings; the narrative/freeform component and all data for Buyer and Landlord listings are displayed as informational context only (see Phase D Section 7 and Section 3.6 below) |
| 7 | `property_strategy_fit` (strategy fit) | The consumer's primary transaction goal versus the agent's stated areas of strategic specialization — displayed as informational only pending proxy-risk analysis (see Section 7.2) |

This seven-trait set represents the core working-relationship dimensions. The remaining five normalized traits (`communication_channel`, `transaction_pace`, `guidance_level`, `collaboration_style`, `representation_priorities`) may be included in the full per-trait expanded view (Section 4) but are secondary for the summary comparison table. `transaction_pace` (timeline flexibility) was intentionally excluded from this seven-trait summary in favor of `decision_making_style` (decision speed) because the seven required traits explicitly call for "decision speed" — the consumer's cognitive and behavioral decision pace — which Phase B maps to `decision_making_style`. `transaction_pace` is nonetheless a scored trait for Seller, Buyer, and Tenant listings and must appear in the full expanded view.

### 3.3 Alignment Category Display Per Cell

Each cell in the side-by-side comparison table represents one trait for one agent, compared against the consumer's stated preference for that same trait. Each cell must display:

1. **The alignment category label** — one of the five Phase D categories (full alignment, partial alignment, adjacent compatibility, neutral compatibility, incompatible alignment), expressed in plain consumer language (see Section 3.4 for plain-language label translations).
2. **A brief one-line summary** — what the consumer stated (abbreviated) and what the agent responded (abbreviated), in plain language.
3. **An expand trigger** — the consumer can tap or click the cell to open the full per-trait explanation (Section 4).

Cell display must not include:
- Numerical scores per cell.
- Color-coded ranking gradients that visually rank agents against each other.
- Any indicator implying the agent is better or worse than another agent on that trait.

### 3.4 Plain-Language Alignment Category Labels

The five Phase D alignment categories must be translated into plain consumer language for the UI. The following translations are proposed:

| Phase D Category | Consumer-Facing Label | Brief Description Shown to Consumer |
|---|---|---|
| Full alignment | **Aligned** | Your preference and this agent's approach closely match on this aspect. |
| Partial alignment | **Broadly aligned** | Your preference and this agent's approach are similar, with minor differences. |
| Adjacent compatibility | **Some differences** | Your preference and this agent's approach differ on this aspect — worth a direct conversation. |
| Neutral compatibility | **No strong preference** | Either you expressed no strong preference here, or the agent's approach is fully flexible. |
| Incompatible alignment | **Meaningful difference** | Your stated preference and this agent's approach differ meaningfully on this aspect. |

Note that "Meaningful difference" is the strongest negative label. It must not imply disqualification. The cell must include a brief note that differences in a working-style trait do not prevent a successful working relationship, but they are worth discussing directly.

### 3.5 Missing Data Display

Not all consumers answer all optional compatibility questions. Not all agents answer all response fields. The side-by-side comparison view must handle missing data transparently.

**Consumer unanswered trait:**
- The cell displays "You did not answer this question" or equivalent plain language.
- No comparison is shown. The agent's response for this trait may be displayed as informational context (what this agent stated about this aspect of their working style) without a comparison result.
- The cell must not imply that the missing data means the agent is a poor fit.

**Agent unanswered trait:**
- The cell displays "This agent did not respond to this question" or equivalent plain language.
- No comparison is shown. The consumer's preference for this trait may be displayed as a reminder of what they indicated.
- The fact that an agent did not respond to a question is informative — it means the consumer does not have this agent's stated approach on file for this trait.

**Role-specific missing trait:**
- For traits where no consumer-side data exists for the consumer's role (e.g., `responsiveness_expectation` for Buyer listings; `risk_tolerance` for Seller listings), the cell displays "Not applicable for your listing type" or equivalent plain language.
- No comparison is attempted. The agent's response for this trait is not displayed as it has no counterpart in the consumer's expressed preferences.

### 3.6 Informational-Only Traits and Mixed-Status Traits in the Comparison View

Per Phase D Section 7 (Trait Eligibility Matrix), the seven-trait comparison set contains three types of display treatment, and the UI must render them distinctly:

**Scored traits** (those producing Layer 3 comparison results) display alignment category labels per Section 3.4. These are traits where both a consumer value and an agent value exist and the trait is score-eligible for the consumer's role.

**Informational-only traits** display a distinct visual marker — for example, an information icon (ⓘ) or a label such as "Context only" or "For your information" — instead of an alignment category label. A brief tooltip clarifies that these traits are shown for context, not as scored compatibility dimensions. The proxy-risk-flagged traits (`risk_tolerance` and `property_strategy_fit`) fall into this category until proxy-risk analysis is complete (see Section 7.2).

**Conditionally scored traits with mixed component treatment** require split display. `representation_philosophy` is the primary example of this type. Per Phase D Section 7, it has the following component-level and role-level scoring status:

- **Structured multi-select component (professional values selection):** Conditionally score-eligible for **Seller and Tenant listings** where consumer-side data exists (`qualities_most_important` for Seller; `most_important_agent_traits` for Tenant). For these roles, the structured component produces an alignment category label.
- **Structured component for Buyer and Landlord listings:** Informational-only — no consumer-side structured data exists for these roles. The agent's stated professional values are displayed as context, not compared.
- **Narrative/freeform component:** Informational-only for all roles across all listings. The freeform text feeds AI explanation context (Phase E) but does not produce a Layer 3 comparison result.

The comparison view cell for `representation_philosophy` must therefore render differently depending on the consumer's role:
- For Seller and Tenant consumers: display an alignment category label for the structured component alongside an "ⓘ Context only" indicator for the narrative component.
- For Buyer and Landlord consumers: display the "ⓘ Context only" indicator for the entire trait, with the agent's stated professional values shown as informational context.

A tooltip must explain the distinction in plain language (e.g., "For your listing type, this is provided as context — this agent's professional values are shown for you to review, but no comparison score is generated from them.").

The distinction between scored and informational-only elements must be clear enough that a consumer cannot confuse an informational-only cell for a scored alignment result.

---

## Section 4 — Per-Trait Explanation Access

### 4.1 Expandable Detail Pattern

Each trait — whether viewed from the bid card (Section 2) or the side-by-side comparison view (Section 3) — must support an expandable detail panel that shows the full per-trait explanation when the consumer taps or clicks the expand trigger.

The expandable detail pattern must present three elements in sequence for every scored trait (the three-element explainability standard from Phase D Section 11.2):

1. **What you stated** — The consumer's own stated preference for this trait, expressed in the same plain language used when they answered the question. This element is labeled clearly (e.g., "Your preference:" or "What you indicated:").

2. **What this agent responded** — The agent's stated response for this trait, expressed in the same plain language the agent used. This element is labeled clearly (e.g., "This agent's approach:" or "What this agent described:").

3. **What the comparison found** — The alignment category for this trait (using the plain-language label from Section 3.4) and a one-to-three sentence explanation of what the comparison means for this specific trait pair. This element is labeled clearly (e.g., "Compatibility note:" or "How they compare:").

These three elements must always appear together. Displaying only the comparison result without the consumer's stated preference and the agent's stated response would violate the explainability standard established in Phase D and Phase E.

### 4.2 "Why This Aligns" Display Pattern

For traits showing full alignment or partial alignment, the per-trait explanation should include a brief positive framing of what the alignment means in practical terms:

Compliant example for full alignment on `communication_frequency`:
> *"Your preference: You indicated you prefer weekly updates from your agent.*
> *This agent's approach: This agent describes their standard practice as checking in with clients every few days.*
> *Compatibility note: Aligned — This agent contacts clients more frequently than your stated preference, which is generally a positive signal. You should expect proactive outreach from this agent without needing to initiate."*

The "why this aligns" framing must:
- Reference the specific trait, not use generic language about fit.
- State what each party indicated, not infer what they prefer.
- Not promise outcomes or assert certainty about how the relationship will go.
- Not compare this agent favorably against other agents.

### 4.3 "Where Expectations May Differ" Display Pattern

For traits showing adjacent compatibility, neutral compatibility, or incompatible alignment, the per-trait explanation should include a constructive framing that informs the consumer without alarming them or steering them away from the agent:

Compliant example for adjacent compatibility on `negotiation_style`:
> *"Your preference: You indicated you want an agent who negotiates aggressively and pushes for maximum terms.*
> *This agent's approach: This agent describes their negotiation style as balanced — they advocate strongly for clients while keeping negotiations constructive and professional.*
> *Compatibility note: Some differences — Your preference for assertive advocacy and this agent's balanced style are close but not identical. If the strength of your agent's negotiating posture is important to you, this is worth discussing directly with this agent before making your decision."*

The "where expectations may differ" framing must:
- Present the difference as a topic to explore, not a disqualifying characteristic.
- Use language that encourages the consumer to have a direct conversation with the agent.
- Not advise the consumer to avoid this agent.
- Not compare this agent unfavorably against other agents.

### 4.4 Missing Data Display in Per-Trait Detail

The per-trait expanded view must handle missing data clearly and without inference.

**Consumer unanswered:**
> *"You did not answer this question when creating your listing. If this aspect of working with an agent is important to you, consider asking this agent directly about their approach."*

**Agent unanswered:**
> *"This agent did not provide a response to this question. This means you do not have this agent's stated approach on file for this aspect of their working style. Consider asking this agent directly if this aspect is important to your decision."*

**Role-specific missing (no consumer-side field exists for this role):**
> *"This question was not included in the listing form for your role. No comparison is available on this aspect."*

In all three missing-data cases, no comparison result label is shown. No inference is made about what the missing answer implies. The consumer is encouraged to seek clarification directly if they want this information.

---

## Section 5 — AI Explanation Display Rules

### 5.1 Advisory-Only Framing

Every AI-generated explanation that is surfaced to consumers must be presented within an advisory-only frame. The UI must make clear, through both labeling and visual design, that AI-generated text is an explanation tool — not a recommendation, endorsement, or autonomous assessment.

**Required advisory-only framing elements:**

- A visible label identifying the explanation as AI-generated or as an automatically generated summary (e.g., "Generated compatibility summary" or "AI-assisted explanation").
- A brief adjacent note stating that the summary is advisory only (the wording may be condensed from the full disclosure statement, but the advisory-only principle must be present at the explanation level, not only in a general disclosure).
- No visual design element (e.g., stars, checkmarks, badges) that implies endorsement of the agent or a positive overall judgment.

### 5.2 No Autonomous Recommendations

The AI explanation display must not present any text that functions as a recommendation, even if the text is framed as an observation. The test is functional: does this sentence, in the context of how it is displayed, function as a recommendation? If yes, it must not be displayed.

Non-compliant display patterns:
- Displaying AI explanation text under a heading such as "Our recommendation" or "Best fit for you" — prohibited.
- Displaying AI explanation text immediately adjacent to a "Hire this agent" button in a way that implies the explanation supports hiring — prohibited.
- Using AI explanation text as the primary content of a filtered "Top picks" or "Best match" section — prohibited.

### 5.3 No "Best Agent," "Top Match," Winner, or Ranking Language

The display layer must not introduce ranking language even if the AI explanation layer produces compliant text. Ranking language is prohibited at both the AI output level (Phase E) and at the display level (Phase F). Display-level additions of ranking language — for example, a UI badge or header added around an AI explanation — are prohibited regardless of whether the AI text itself is compliant.

The following labels, badges, sections, and headings are explicitly prohibited anywhere in the compatibility display:
- "Best match"
- "Top match"
- "Most compatible"
- "Recommended by compatibility"
- "Highest alignment"
- Any numbered ranking of agents by compatibility signal

### 5.4 No Protected-Class References

The display layer must not render any AI-generated text containing protected-class references. If the AI explanation system produces any text referencing race, color, national origin, religion, sex, familial status, disability, or any other protected characteristic under the Fair Housing Act or applicable state law, that text must not reach the consumer-facing UI. A moderation layer (designed in Phase G/H) is responsible for catching this. Phase F's responsibility is to define that such text must be blocked before display.

### 5.5 No Steering Language

The display layer must not render any AI-generated text that directs the consumer toward or away from any agent. Steering language is prohibited at the AI output level (Phase E) and at the display level. If AI-generated text passes moderation but is displayed in a context that functions as steering (for example, appearing in a "Recommended for you" section), that display context is non-compliant.

### 5.6 No Hidden Weighting Disclosure

The display layer must not render any text that references hidden weighting, undisclosed scoring formulas, or algorithmic factors not visible to the consumer. If the compatibility signal was computed from multiple traits, those traits must be individually identifiable through the per-trait explanation interface (Section 4). A summary statement such as "based on a weighted analysis of your profile" without disclosing the weights is prohibited.

### 5.7 Human Decision Authority Statement Placement

The principle that all hiring decisions remain with the consumer must be stated explicitly in the compatibility UI. This statement must be visible — not hidden in a collapsed section or a secondary footer — in any context where the compatibility summary or AI explanation is shown. The statement must appear before or within the first screen of the compatibility detail panel.

Compliant human decision authority statement (conceptual illustration):
> *"This compatibility information is provided as a decision-support tool. The decision of which agent to hire, if any, is entirely yours. No part of this system makes a decision on your behalf."*

### 5.8 Required Disclosure Text Accompanying AI Explanations

Every AI-generated explanation shown to a consumer must be accompanied by the following required disclosures, derived from Phase E Section 15:

1. **Compatibility is advisory only** — the explanation does not recommend or rank agents.
2. **Explanations are based on structured trait comparisons** — the explanation does not incorporate agent credentials, production history, market expertise, service scope, or compensation terms.
3. **Explanations are not recommendations** — no part of the compatibility explanation constitutes a recommendation to hire or not hire any agent.
4. **Compatibility is one factor among many** — the consumer is encouraged to review the agent's full bid, services, compensation, qualifications, and to have direct conversations with agents.

The format of these disclosures may be condensed for display (e.g., collapsed behind a "What is this?" link or presented as a compact tooltip), as long as all four points are accessible to the consumer without leaving the compatibility panel. The disclosures must not require navigation to a separate help page as the only means of access.

---

## Section 6 — Visual and UI Standards

### 6.1 Score Badge Appearance

The qualitative compatibility label (Section 1.1) should be presented as a badge or pill element. The following visual standards apply:

**Label-to-color mapping (proposed):**

| Label | Proposed Color Register | Rationale |
|---|---|---|
| Strong Alignment | Calm green / teal | Signals positive alignment without competitive framing |
| Broad Compatibility | Neutral blue | Signals workable alignment; neutral, not celebratory |
| Notable Differences | Warm amber / yellow | Signals areas requiring attention; not alarming |
| Insufficient Data | Light gray | Signals absence of information; neutral |

**Color design rules:**

- No label may use red, which carries a disqualification connotation that is incompatible with the non-disqualifying design principle.
- Color must not be the sole differentiator — each badge must also carry the text label, so that the meaning is available without color (WCAG 1.4.1: use of color).
- The color register for Strong Alignment must not be more visually prominent than the agent's service and compensation information. The badge draws attention to compatibility without dominating the bid card.

**Neutral and warning states:**

- The "Notable Differences" amber badge is a warning state, not a rejection state. Its tooltip or adjacent text must clarify: "This agent's working style differs from your stated preferences on some aspects. These differences are worth reviewing and discussing directly with the agent."
- The "Insufficient Data" gray badge must clarify: "There isn't enough compatibility data to show a meaningful summary. Review this agent's full proposal and ask them about their working style directly."

### 6.2 Plain-Language Tooltip Standards

Tooltips and hover/tap popups that appear on compatibility elements must follow these standards:

- Plain language — no technical terms, algorithmic references, or scoring jargon.
- Brief — one to three sentences maximum.
- Non-ranking — no tooltip may imply a ranked result.
- Actionable — where appropriate, tooltips should suggest what the consumer can do with the information (e.g., "Ask this agent directly about their communication approach").

The per-trait alignment labels (Section 3.4) must each have a standard tooltip that explains what the label means in plain terms. Example:

> Tooltip for "Some differences": *"Your preference and this agent's approach differ on this aspect of working together. This doesn't mean the agent isn't a good fit — it means this is a topic worth discussing before you decide."*

### 6.3 Accessibility Requirements

All compatibility UI elements must meet the following accessibility standards:

**WCAG contrast:** All text against its background must meet WCAG 2.1 Level AA contrast requirements (4.5:1 for normal text, 3:1 for large text). The badge colors defined in Section 6.1 must be validated against WCAG contrast ratios before implementation.

**Screen-reader labels:** Every compatibility badge, cell, and expand trigger must have a descriptive `aria-label` or accessible text equivalent. Screen readers must be able to convey the full meaning of each element, including the label text and its context. Example: a badge reading "Broad Compatibility" should have screen-reader text such as "Compatibility summary: Broad Compatibility — your stated preferences and this agent's working style are broadly aligned with some differences."

**Keyboard navigation:** All expand triggers, comparison table cells, and detail panels must be fully operable by keyboard. No compatibility feature may be accessible only by mouse or touch gesture. Expand/collapse interactions must be operable via Enter or Space key. Tab order must follow the visual reading order.

**No motion-only indicators:** If animation is used in the compatibility display (e.g., an expanding panel), a `prefers-reduced-motion` media query must be respected. The expand behavior must be available without animation.

### 6.4 Mobile Behavior

The compatibility UI must be designed mobile-first. The following mobile-specific standards apply:

**Collapsed default state on mobile:** The compatibility summary on the bid card should be collapsed by default on mobile, showing only the qualitative label badge. The consumer taps the badge or a clearly labeled expand trigger to reveal the one-line advisory gloss and the "See how working styles compare" trigger.

**Side-by-side comparison on mobile:** The comparison table (Section 3) should scroll horizontally on mobile rather than wrapping or compressing agent columns into an unreadable width. Each agent column should be a fixed minimum width that allows the alignment label to be readable without truncation.

**Tap targets:** All interactive elements in the compatibility UI — expand triggers, comparison cells, disclosure links — must have a minimum tap target size of 44×44 CSS pixels (per WCAG 2.5.5 Target Size) to be reliably tappable on mobile devices.

**Expanded detail panels on mobile:** When a consumer taps to expand a per-trait explanation (Section 4), the expanded panel should open as a full-width accordion below the tapped element (not as a modal that obscures the bid card). The consumer should be able to close the panel by tapping a clear close trigger or by tapping the trigger again (toggle behavior).

---

## Section 7 — Governance and Compliance Notes

### 7.1 Fair Housing Safeguards Restated in UI Terms

The Fair Housing safeguards established across Phases A through E apply with full force to every element of the Phase F UI design. The following safeguards are restated here in display-level terms:

**No protected-class framing in any UI element** — No label, badge, tooltip, expand trigger, comparison cell, per-trait explanation, or disclosure text may reference or imply a protected class. This applies to direct references and to proxy references. If any display element uses language that functions as a proxy for race, color, national origin, religion, sex, familial status, disability, age, marital status, source of income, or any other class protected under applicable law, that element is non-compliant and must be revised.

**No cultural compatibility framing** — The UI must not frame compatibility as cultural compatibility, lifestyle compatibility, community compatibility, or any other framing that could introduce cultural, demographic, or neighborhood-based comparison. Compatibility is professional working-style alignment only.

**No neighborhood or location-based scoring display** — The compatibility UI must not incorporate, imply, or display any signal derived from the consumer's or agent's geographic location, neighborhood demographics, or area characteristics.

**No demographic similarity display** — The UI must not surface any signal or label suggesting that compatibility is partly based on shared demographic characteristics between the consumer and agent.

**No "people like you" framing** — The compatibility display must never use language that references a consumer category, consumer segment, or group identity. Compatibility statements are always about what this specific consumer stated and what this specific agent responded.

**No steering displays** — The overall bid display context must not arrange compatibility information in a way that functions as steering. For example: a UI that always shows "high compatibility" agents first (when sorted by compatibility), followed by a visible gap before "lower compatibility" agents, could function as steering depending on how agents in protected-class contexts are distributed. Display designs that risk steering patterns must be identified and reviewed before implementation.

### 7.2 Proxy-Risk Warning — Blocking Items for Production Implementation

The following two traits are flagged as proxy-risk risks in Phase D Section 14.3 and carry forward as blocking items for any production implementation of the compatibility scoring and display system:

**`risk_tolerance`** — The Landlord role's risk tolerance options (Low – Strict Screening Only through High – Willing to Work With Most Tenants) map directly to tenant screening strictness. Scoring this trait in a way that disadvantages agents who serve tenants with non-standard financial profiles may function as a proxy for screening against protected classes, given that tenants with non-standard financial backgrounds are disproportionately members of protected classes in many markets. **No production display of a scored `risk_tolerance` comparison may appear in the UI until proxy-risk analysis for this trait has been completed and reviewed by a qualified Fair Housing compliance professional.**

Until that review is complete, `risk_tolerance` must be displayed in the comparison view as informational context only (see Section 3.6) — the consumer can see what the agent stated, but no alignment category label is applied and no comparison result is produced.

**`property_strategy_fit`** — Certain transaction type options in this trait (e.g., investment property acquisition, fix & flip) may correlate with transaction patterns associated with specific demographic groups in some markets. Scoring this trait without a proxy-risk analysis risks introducing demographic correlation into the compatibility signal. **No production display of a scored `property_strategy_fit` comparison may appear in the UI until proxy-risk analysis for this trait has been completed and reviewed by a qualified Fair Housing compliance professional.**

Until that review is complete, `property_strategy_fit` must be displayed in the comparison view as informational context only (see Section 3.6).

### 7.3 Compliance Review Required Before Production

No Phase F design element — no badge, no comparison table, no per-trait explanation panel, no AI explanation display, no tooltip — may be implemented in a production environment without a formal compliance review process. That review must include:

- Assessment of all display elements for prohibited language patterns.
- Review of the overall display arrangement for steering risk.
- Evaluation of the proxy-risk-flagged traits' display treatment.
- Review by a qualified Fair Housing compliance professional or legal counsel.
- Documentation of the review, findings, and any remediation taken.

The compliance review requirement applies to both the general display design and to the AI explanation display rules (Section 5). Phase H (production AI integration and compliance testing, per Phase E Section 14.3) is the designated phase for the AI-specific compliance review. The general UI compliance review should precede Phase G implementation.

---

## Section 8 — Implementation Handoff Notes for Phase G

### 8.1 Schema Items Phase G Will Eventually Need

The following schema additions are required before any element of the compatibility display can be implemented. These are listed here as handoff notes for Phase G — none of them are implemented in Phase F.

**`representation_compatibility_score` column** — A new column on the existing `listing_compatibility_scores` table is needed to store the composite compatibility advisory signal for a given listing-bid pair. The data type, range, and format (qualitative label, percentage, or both) must be determined in Phase G based on the Phase D framework and the Phase F display design.

**Per-trait comparison result storage** — A schema for storing the per-trait comparison results (the Layer 4 explainability layer outputs from Phase D) alongside the composite score is needed. Each record must contain, for each scored trait: the consumer's normalized preference value, the agent's normalized response value, and the alignment category label. These records are the source data for both the per-trait explanation panels (Section 4) and the audit trail.

**`scoring_framework_version` increment** — The existing `scoring_framework_version` column in `listing_compatibility_scores` must be incremented when the `representation_compatibility_score` dimension is added to the framework. Prior scores (computed without representation compatibility) must not be directly compared to scores computed with it.

**AI explanation storage** — The AI-generated plain-language explanation text (Layer 5 output from Phase E) must be stored persistently. Phase G must determine the storage location, caching strategy, and invalidation rules (per Phase E Section 17 — caching strategy deferred to Phase G).

### 8.2 Livewire Agent Bid Components That Will Need Agent-Side Response Fields

Phase G implementation architecture will eventually need to add agent-side compatibility response fields to the four agent bid Livewire components. These are the components confirmed in Phase A Section 2.2 to currently have zero compatibility fields:

| Component | Role |
|---|---|
| `app/Http/Livewire/Seller/SellerAgentAuctionBid.php` | Seller agent bid |
| `app/Http/Livewire/Buyer/BuyerAgentAuctionBid.php` | Buyer agent bid |
| `app/Http/Livewire/Landlord/LandlordAgentAuctionBid.php` | Landlord agent bid |
| `app/Http/Livewire/Tenant/TenantAgentAuctionBid.php` | Tenant agent bid |

The agent-side response fields to be added are defined at the Phase C normalized trait level, organized into seven sub-sections under `compatibility_preferences.agent_response.*`. Phase G will determine the specific Livewire component architecture, property declarations, validation rules, and EAV `saveMeta`/`loadMeta` integration required to store agent compatibility responses.

**No code changes to any of these components occur in Phase F or any prior phase.**

### 8.3 What Remains Explicitly Out of Scope for Phase F

The following items are explicitly outside the scope of Phase F and are deferred to Phase G or later:

| Item | Deferred To |
|---|---|
| Blade markup for compatibility display components | Phase G |
| Livewire component logic for expanding/collapsing trait panels | Phase G |
| AlpineJS interaction code for comparison table | Phase G |
| TailwindCSS styling implementation for badges and panels | Phase G |
| Database schema additions and migrations | Phase G |
| Agent bid Livewire component modifications | Phase G |
| Scoring computation service or job | Phase G |
| AI explanation service integration | Phase G / Phase H |
| AI explanation caching strategy | Phase G |
| AI moderation pipeline | Phase G / Phase H |
| Proxy-risk analysis for `risk_tolerance` and `property_strategy_fit` | Required before Phase G scoring implementation |
| Fair Housing compliance review | Required before production use |
| Consumer-controlled sorting by compatibility signal | Phase G |
| Analytics for compatibility UI engagement | Post-Phase H |

### 8.4 Closing Statement

Phase F is documentation-only. The consumer UI design described in this document defines the display architecture, information hierarchy, interaction patterns, visual standards, and governance constraints for the compatibility consumer experience. No element of this design has been implemented, and no element may be implemented before the proxy-risk analysis for `risk_tolerance` and `property_strategy_fit` is completed and the required Fair Housing compliance review is conducted.

**Phase G implementation architecture may proceed only after proxy-risk and Fair Housing review items are resolved.**

---

*Document prepared as Phase F deliverable. Read-only. No implementation artifact was produced in this phase.*
