# BidYourAgent — Professional Representation Compatibility: Phase H Implementation Readiness

**Document Status:** Read-only planning document. No code, schema, migration, route, controller, Blade, Livewire, config, database file, AI prompt, API integration, scoring logic, or production logic of any kind was created or modified to produce this document. No implementation occurs in this phase.
**Document Date:** 2026-05-28
**Phase:** H — Implementation Readiness
**Preceding Phase:** G — Compatibility Implementation Architecture (`docs/BIDYOURAGENT_COMPATIBILITY_IMPLEMENTATION_ARCHITECTURE.md`)
**Succeeding Phase:** Production Engineering (blocked — all Phase H readiness gates must be formally cleared before production engineering begins)

---

## Table of Contents

1. [Section 1 — Proxy-Risk Review Preparation](#section-1--proxy-risk-review-preparation)
2. [Section 2 — Fair Housing Compliance Review Preparation](#section-2--fair-housing-compliance-review-preparation)
3. [Section 3 — Option Set Finalization](#section-3--option-set-finalization)
4. [Section 4 — Minimum Threshold Decision Planning](#section-4--minimum-threshold-decision-planning)
5. [Section 5 — Compatibility Label Calibration Planning](#section-5--compatibility-label-calibration-planning)
6. [Section 6 — AI Moderation & Safety Workflow](#section-6--ai-moderation--safety-workflow)
7. [Section 7 — Rollout Execution Readiness](#section-7--rollout-execution-readiness)
8. [Section 8 — Engineering Sequencing Plan](#section-8--engineering-sequencing-plan)
9. [Section 9 — Deferred/Optional Features](#section-9--deferredoptional-features)
10. [Section 10 — Final Phase Transition](#section-10--final-phase-transition)
11. [Closing Statement and Summary](#closing-statement-and-summary)

---

## Section 1 — Proxy-Risk Review Preparation

Phase G Section 9.1 identified two traits — `risk_tolerance` and `property_strategy_fit` — as unconditionally blocked from any scored use pending a proxy-risk analysis by a qualified Fair Housing compliance professional. This blocking applies to all four consumer roles (Seller, Buyer, Landlord, Tenant) and to all four rollout gates. No scored use of either trait may occur at any gate, in any environment, for any consumer, until a completed analysis with documented findings exists for each trait.

This section documents, for each of the two blocked traits, the exact trait definition and option sets as established in Phases B, C, and D, the specific proxy-risk argument that requires analysis, what the qualified compliance professional must specifically evaluate, and the four possible outcomes of the review.

---

### 1.1 `risk_tolerance`

#### Trait Definition (Phase B Section 4.8)

`risk_tolerance` is defined as: the consumer's appetite for transactional and financial risk — how willing they are to accept uncertainty, waive protections, or work with situations that fall outside standard criteria in pursuit of their goal.

For **buyers**, this maps to willingness to waive contingencies or compete in multiple-offer situations. For **landlords**, this maps to screening strictness and openness to tenants with non-standard financial profiles.

#### Consumer-Side Option Sets by Role (Phase A)

**Buyer** — `risk_tolerance` (single-select):
- "Very Conservative"
- "Conservative"
- "Moderate"
- "Aggressive"
- "Very Aggressive"

**Landlord** — `risk_tolerance` (single-select):
- "Low – Strict Screening Only"
- "Moderate – Standard Criteria"
- "Flexible – Case-by-Case"
- "High – Willing to Work With Most Tenants"

**Seller** — No direct equivalent consumer-side field. Trait is absent.

**Tenant** — No direct equivalent consumer-side field. Trait is absent.

#### Agent-Side Proposed Option Set (Phase C)

The Phase C architecture places `risk_tolerance` within the `representation_philosophy` sub-section of the agent response structure (`compatibility_preferences.agent_response.representation_philosophy.*`). The agent responds to how comfortable they are working with clients across the full risk spectrum. Specific option labels for this sub-section were intentionally deferred for finalization, pending the proxy-risk outcome.

#### Why This Trait Carries Proxy Risk

The critical proxy-risk concern for `risk_tolerance` is concentrated in the **Landlord role context**.

The Landlord-side option set includes "Low – Strict Screening Only," "Moderate – Standard Criteria," "Flexible – Case-by-Case," and "High – Willing to Work With Most Tenants." A landlord who selects "Low – Strict Screening Only" is expressing a preference for strict tenant screening. If that preference were scored and used to produce a compatibility signal that surfaced agents who self-describe as comfortable with strict screening, the system could function as a mechanism through which landlord preferences for restrictive tenant screening are amplified and matched by the platform.

Strict tenant screening criteria — when applied in ways that disproportionately affect applicants on the basis of race, national origin, familial status, disability, or other protected characteristics — are a known and well-documented vector for Fair Housing violations. The platform must not create a system that operationalizes, endorses, or matches agents based on their stated comfort with screening strictness in a way that could correlate with discriminatory outcomes.

For the **Buyer role**, the risk is narrower but still present. Risk tolerance options in the buyer context ("Very Aggressive") could correlate with certain buyer profiles in ways that vary by demographic group.

#### What the Compliance Professional Must Evaluate

A qualified Fair Housing compliance professional must specifically evaluate:

1. Whether the Landlord `risk_tolerance` option set, as written, creates a mechanism that proxies for protected-class screening preferences — and whether an agent's self-described comfort with "strict screening" or "flexible screening" constitutes a Fair Housing-relevant signal.
2. Whether the Buyer `risk_tolerance` option set creates a statistically meaningful correlation with any protected-class attribute that could make scoring this trait produce disparate outcomes in agent matching.
3. Whether any scored use of `risk_tolerance` — even when framed as a working-style compatibility signal between consumer and agent — constitutes a prohibited practice or creates legal exposure under the Fair Housing Act, applicable state law, or platform-level obligations.
4. Whether the trait can be displayed as an informational-only comparison (showing what the consumer and agent each indicated, without a scored alignment result) without creating the same proxy risk.
5. Whether role-specific treatment is warranted — e.g., the trait might be cleared for scored use in the Buyer context but remain blocked in the Landlord context, or vice versa.

#### Four Possible Outcomes of the Review

**Outcome A — Approved for scored use (all roles):** The compliance professional finds that the trait as designed does not carry meaningful proxy risk in any consumer role context. The trait may proceed to scored use in the comparison engine, subject to any general fair housing conditions that apply to the engine as a whole.

**Outcome B — Approved with restrictions:** The compliance professional finds that the trait is safe for scored use in some consumer role contexts but not others (e.g., safe for Buyer, not for Landlord). For roles where scored use is cleared, the trait proceeds normally. For roles where it is not cleared, the trait is restricted to informational-only display (showing what each party indicated without producing a scored alignment result).

**Outcome C — Permanently informational-only:** The compliance professional finds that scored use of `risk_tolerance` carries unacceptable proxy risk across all consumer roles, but that displaying the trait's values side-by-side as informational context — without a scored comparison — does not constitute a prohibited practice. In this outcome, the trait's values are preserved in the Layer 4 explainability payload for AI explanation context but do not enter the Layer 3 comparison engine.

**Outcome D — Removed from the engine entirely:** The compliance professional finds that even informational-only display of `risk_tolerance` as a matched attribute creates proxy risk or could be perceived as endorsing screening preferences in ways that create Fair Housing exposure. In this outcome, the trait is removed from the compatibility engine entirely — its values are not displayed, not scored, and not fed to the AI explanation pipeline.

---

### 1.2 `property_strategy_fit`

#### Trait Definition (Phase B Section 4.12)

`property_strategy_fit` is defined as: the consumer's primary goal for the transaction and how it shapes the agent's strategic approach.

For sellers, this encompasses goals like maximizing sale price, achieving a quick sale, or minimizing disruption. For buyers, this includes primary residence purchase, vacation/secondary home, investment property acquisition, fix-and-flip, and commercial use. For landlords, this encompasses goals like maximizing monthly rent, securing a long-term stable tenant, or minimizing vacancy. For tenants, this includes finding a long-term home, temporary or short-term housing, and relocation.

#### Consumer-Side Option Sets by Role (Phase A)

**Seller** — `primary_transaction_goal` (single-select):
- "Maximum Sale Price"
- "Quick Sale"
- "Minimal Disruption"
- "Specific Closing Timeline"
- "Other"

Also contributing: `firm_on_price` (single-select: "Yes — Firm on Price," "Somewhat — Open to Reasonable Offers," "Flexible — Willing to Negotiate Significantly") and `willing_to_negotiate_on` (multi-select).

**Buyer** — `primary_transaction_goal` (single-select):
- "Primary Residence"
- "Vacation / Secondary Home"
- "Investment Property"
- "Fix & Flip"
- "Commercial Use"
- "Land Purchase"
- "Other"

**Landlord** — `primary_leasing_goal` (single-select):
- "Maximize Monthly Rent"
- "Long-Term Stable Tenant"
- "Minimize Vacancy Time"
- "High-Quality Tenant Profile"
- "Build Portfolio Cash Flow"
- "Property Appreciation & Upkeep"
- "Other"

**Tenant** — `primary_rental_goal` (single-select):
- "Find a long-term home"
- "Temporary / short-term housing"
- "Relocating for work"
- "Downsizing"
- "Upsizing"
- "Investment search"
- "Other"

#### Agent-Side Proposed Option Set (Phase C)

The Phase C architecture places `property_strategy_fit` within the `transaction_strategy` sub-section of the agent response structure (`compatibility_preferences.agent_response.transaction_strategy.*`). The agent describes their areas of strategic specialization — which transaction types and consumer goals they are best positioned to serve. Specific option labels were deferred pending the proxy-risk outcome.

#### Why This Trait Carries Proxy Risk

The proxy-risk concern for `property_strategy_fit` is concentrated in the **Buyer role context**, specifically in the option set that includes "Investment Property" and "Fix & Flip."

Investment property buyers and fix-and-flip buyers represent transaction types that are statistically concentrated within specific demographic groups in certain real estate markets. If the compatibility engine scores `property_strategy_fit` by matching buyers who indicate "Investment Property" or "Fix & Flip" with agents who self-describe as specializing in investment transactions, the engine could function as a mechanism that directs certain consumer profiles — defined by transaction type but potentially correlated with demographic characteristics — toward a specific subset of agents.

The Fair Housing Act prohibits practices that, while facially neutral, produce discriminatory effects. The concern is not that the transaction type categories are themselves discriminatory — they are not — but that scoring compatibility based on them could introduce demographic correlation into the engine's outputs through the back door of "transaction type" preference matching.

For the **Landlord role**, a parallel concern exists: a landlord whose primary leasing goal is "High-Quality Tenant Profile" or "Build Portfolio Cash Flow" could, depending on how agents self-describe their specializations, produce scored matching that correlates with discriminatory screening practices.

#### What the Compliance Professional Must Evaluate

A qualified Fair Housing compliance professional must specifically evaluate:

1. Whether the Buyer-side option set for `property_strategy_fit` — particularly "Investment Property" and "Fix & Flip" — creates a statistically meaningful correlation with any protected-class attribute that makes scored compatibility matching using this trait a Fair Housing-relevant practice.
2. Whether the Landlord-side option set for `property_strategy_fit` — particularly "High-Quality Tenant Profile" — functions as a proxy for discriminatory tenant screening preferences when used as a compatibility matching criterion.
3. Whether any scored use of `property_strategy_fit`, across any consumer role, constitutes a practice that could produce disparate impact on the basis of race, national origin, familial status, or any other protected characteristic.
4. Whether the trait can be displayed as an informational-only comparison without creating the same risk.
5. Whether role-specific treatment is warranted, and whether specific option values (rather than the entire trait) are the source of the risk.

#### Four Possible Outcomes of the Review

**Outcome A — Approved for scored use (all roles):** The compliance professional finds that the transaction-type and goal options in this trait do not carry meaningful proxy risk in any consumer role context. The trait proceeds to scored use in the comparison engine.

**Outcome B — Approved with restrictions:** The compliance professional finds that certain roles or certain option values carry proxy risk while others do not. Scored use is cleared for the safe subsets; the flagged roles or options are restricted to informational-only display.

**Outcome C — Permanently informational-only:** The compliance professional finds that scored use carries unacceptable proxy risk but that displaying each party's stated goal as informational context does not constitute a prohibited practice. The trait is displayed informational-only across all roles, with no Layer 3 comparison result produced.

**Outcome D — Removed from the engine entirely:** The compliance professional finds that even informational display creates exposure. The trait is removed from the compatibility engine entirely.

---

### 1.3 Unconditional Block — Applies Across All Four Consumer Roles

The blocking of both `risk_tolerance` and `property_strategy_fit` is **unconditional across all four consumer roles** pending completion of the proxy-risk analyses. This means:

- Neither trait may be scored at Gate 1, Gate 2, Gate 3, or Gate 4 until the corresponding analysis is complete and findings are implemented.
- Neither trait may be used as an input to the AI explanation pipeline's scoring summary until the analysis is complete.
- Both traits may continue to be collected on the consumer-side listing forms and stored in the EAV meta system as-is — data collection does not require proxy-risk clearance. Only scored use and scored display require clearance.
- Both traits must be displayed as informational-only during any beta gate where the analysis is still in progress, with explicit UI labeling indicating that these traits are under compliance review and are not scored.

---

## Section 2 — Fair Housing Compliance Review Preparation

In addition to the proxy-risk analyses in Section 1, the Phase H readiness gate checklist (Phase G Section 10) identifies six areas requiring specific Fair Housing compliance review before the engine may advance through its rollout gates. This section defines what each review area requires before the corresponding gate can be cleared.

---

### 2.1 Scoring Review

**What must be reviewed:** The trait eligibility matrix (Phase D Section 7), the alignment category definitions and their application to each scored trait, and the weighting approach for the composite advisory signal (Phase G Section 9.6).

**What the review must confirm:**
- No scored trait, in any consumer role context, introduces a demographic correlation risk through the comparison methodology.
- The alignment category labels (full alignment, partial alignment, adjacent compatibility, neutral compatibility, incompatible alignment) do not function as proxies for protected-class characteristics.
- The weighting approach — whether equal-weight or otherwise — does not introduce hidden demographic correlation. If any unequal weighting is proposed, the compliance review must address the specific weighting rationale and confirm it is free of discriminatory effect.
- The mapping from per-trait alignment results to the four Phase F qualitative labels does not produce systematically different outcomes across consumer groups defined by protected-class characteristics.

**Gate requirement:** Required before Gate 2 (hidden beta). The scoring review must be complete and documented before any test user sees compatibility results.

---

### 2.2 Display Review

**What must be reviewed:** The Phase F qualitative label language (Strong Alignment, Broad Compatibility, Notable Differences, Insufficient Data), the placement of compatibility information within the bid card (Phase F Section 2), all advisory disclosures (Phase F Section 1.3 and Phase E Section 15), and the sorting and filtering rules (Phase G Section 9.5).

**What the review must confirm:**
- The four qualitative labels do not carry ranking language, endorsement language, or language that could function as a steering signal.
- The bid card placement does not position compatibility as the primary or most important criterion for evaluating an agent.
- The advisory disclosures are adequate to communicate the non-ranking, non-recommendation nature of the compatibility system.
- The sorting and filtering rules prohibit algorithmic reordering of agent lists by compatibility signal, and that any consumer-initiated sort option is structured so as not to produce steering effects.
- The "Notable Differences" and "Meaningful difference" labels do not function as disqualification signals.

**Gate requirement:** Required before Gate 3 (consumer-visible beta). The display review must be complete and documented, with written acknowledgment from the compliance reviewer, before compatibility information is visible to any member of the consuming public.

---

### 2.3 AI Explanation Review

**What must be reviewed:** The prohibited output categories defined in Phase E Section 7.2, the moderation pipeline (Section 6 of this document), the disclosure language that accompanies AI-generated explanations (Phase E Section 15), and the authorized input set (Phase E Section 6.1).

**What the review must confirm:**
- The prohibited output categories are complete and cover all forms of ranking language, recommendation language, demographic references, psychological profiling assertions, predictions about working relationship success, "perfect fit" or "ideal for you" language, and any output derived from inputs not in the Phase E authorized input set.
- The moderation pipeline is adequate to detect and block prohibited outputs before they reach consumers.
- The disclosure language accurately represents what the AI system does and does not do, without overstating the system's capabilities or understating its limitations.
- The authorized input set does not include any data field that could expose the AI system to demographic or protected-class data through indirect means.

**Gate requirement:** Required before Gate 3 (consumer-visible beta). Manual review processes (Gate 1 and Gate 2) must be documented and followed; automated moderation must be operational before Gate 3.

---

### 2.4 Sorting/Filtering Review

**What must be reviewed:** The rules governing whether and how consumers may sort or filter agent bid lists using the compatibility signal, and whether any display ordering or visual prominence treatment creates a de facto sorting effect.

**What the review must confirm:**
- No algorithmic sorting of agent lists by compatibility signal is active at any gate before Gate 4 unless explicitly consumer-initiated and compliance-reviewed.
- Consumer-initiated sort, if permitted, does not produce steering effects — that is, the ability to sort by compatibility does not systematically disadvantage or exclude agents from protected-class groups.
- Visual treatments (e.g., badge colors, icon prominence) do not function as hidden ranking signals that effectively sort the consumer's attention before they consciously choose to sort.

**Gate requirement:** The no-algorithmic-sorting policy must be confirmed before Gate 3. Any consumer-initiated sort option requires a separate compliance finding before it may be activated at any gate.

---

### 2.5 Disclosure Review

**What must be reviewed:** The complete set of required disclosure elements from Phase E Section 15 and Phase F Section 5.3, the placement of disclosures within the UI, and the readability of disclosure language for a consumer encountering the compatibility system for the first time.

**What the review must confirm:**
- All required disclosure elements are present at every point where compatibility information is surfaced: the bid card summary, the per-trait expansion panel, the side-by-side comparison view, and any AI-generated explanation.
- The disclosure language accurately states: (a) the advisory nature of the signal, (b) that the system does not recommend or rank agents, (c) that the consumer's hiring decision remains entirely theirs, (d) what data the comparison is based on, and (e) the limitations of the system.
- Disclosures are not hidden, minimized, or placed in locations where consumers are unlikely to read them before acting on compatibility information.

**Gate requirement:** Required before Gate 3 (consumer-visible beta).

---

### 2.6 Auditability Review

**What must be reviewed:** The audit record structure (Phase G Section 7.7), the completeness of the audit logging implementation (Gate 1 exit requirement), and the accessibility of audit records for compliance inspection.

**What the review must confirm:**
- Every compatibility computation produces an audit record containing all required fields: listing identifier and type, bid identifier, `compatibility_framework_version`, normalized consumer trait values, normalized agent response values, per-trait comparison result labels, composite advisory signal, computed_at timestamp, trait exclusion reasons, and AI explanation moderation status.
- Audit records are retained for the full duration of the associated listing and bid's active period, and for a defined period thereafter.
- Audit records are inspectable by platform administrators without re-running the comparison.
- No audit record is deleted while a corresponding listing or bid is active.

**Gate requirement:** Audit logging must be operational at Gate 1. Audit record completeness must be confirmed by administrative inspection as a Gate 1 exit criterion.

---

## Section 3 — Option Set Finalization

The Phase C architecture defines the normalized trait structure and the general direction of agent-side response option sets. However, exact option labels, option counts, and option ordering have not been finalized. Finalization is a Phase H prerequisite because option labels appear in stored data, in AI explanation inputs, and in the consumer-facing comparison UI — inconsistencies or ambiguities in option labels at implementation time cannot be patched without data migration and re-computation of stored scores.

This section defines the four requirements that every option set must satisfy before implementation may begin.

---

### 3.1 Exact Normalized Option Structure

Every trait must have a **bounded, finalized list of options** satisfying the following structural requirements:

**Stable string identifiers:** Each option must have a stable machine-readable string identifier (e.g., `"weekly_checkins"`, `"daily_updates"`) that is stored in the EAV meta system and used in comparison logic. Identifiers must be lowercase, underscore-separated, and stable — they may not be changed after implementation without a `compatibility_framework_version` increment and a data migration plan.

**Human-readable labels:** Each option must have a corresponding human-readable label for display in the agent bid form UI and in the consumer-facing comparison display (e.g., `"Weekly check-ins"`). Labels may be updated with a version increment but must be consistent between the consumer-side and agent-side display at any given version.

**No proxy-protected-class options:** No option within any finalized option set may name, encode, or proxy a protected-class characteristic. This applies to both the stable identifiers and the human-readable labels. Any option whose wording is ambiguous on this point must be reviewed by the compliance professional before finalization.

**Bounded count:** Each single-select trait must have a finite, documented list of options. No single-select option set may use an open-ended "Other" response as the sole mechanism for capturing values that do not fit existing options. Where "Other" companion text inputs exist on the consumer side, the agent-side option set must include all semantically distinct categories captured by those companion inputs as first-class options.

---

### 3.2 Cross-Role Compatibility

Agent options must be **crosswalk-compatible** with the consumer-side options for that trait as established in the Phase B unified option crosswalks (Phase B Section 6). Crosswalk compatibility means:

- For each agent option, there exists at least one consumer-side option for each role that the comparison engine can meaningfully evaluate as aligned, partially aligned, or incompatible.
- No agent option exists that is incomparable to all consumer options for all roles — an option that cannot be compared against any consumer preference is structurally incoherent and must not be included.
- Where role-specific consumer options use different vocabulary for semantically equivalent concepts (the four naming inconsistencies documented in Phase B Section 7), the agent option set uses the normalized vocabulary established in Phase B, not the role-specific raw vocabulary.

---

### 3.3 Label Clarity

Each option label must be **self-explanatory to a consumer reading it in the per-trait UI without additional context**. Specifically:

- A consumer who sees "This agent states: [label]" in the side-by-side comparison UI must be able to understand what the label means without reading a tooltip, help text, or documentation.
- Labels must not use real estate industry jargon, abbreviations, or technical shorthand that a consumer encountering the system for the first time would not recognize.
- Labels must not be so abstract that a consumer cannot determine whether the agent's stated approach is compatible with their own preference.

---

### 3.4 AI Readability

Option labels must be **legible to the AI explanation pipeline as self-descriptive inputs without requiring lookup tables or additional decoding**. Specifically:

- When an option label is passed to the AI explanation pipeline as part of the Phase E authorized input payload (Phase E Section 6.1), the AI system must be able to describe what the label means in plain language without needing an external glossary.
- Labels that are acronyms, codes, or internal shorthand must not be used. Labels that are so terse they require contextual decoding (e.g., "Option A", "Standard", "Level 2") are not acceptable.
- This requirement does not mean labels must be long — it means they must be semantically complete at the label level.

---

### 3.5 Consumer Readability

Option labels must be **accessible to a consumer encountering the compatibility system for the first time**, avoiding jargon, acronyms, or technical shorthand. This requirement applies to labels as they appear in:

- The agent bid form (where agents see their own options and must understand what they are committing to)
- The consumer-facing comparison UI (where consumers see what the agent stated)
- The AI-generated plain-language explanation (where the label is the basis for a narrative description)

---

### 3.6 `representation_priorities` — Primary Role-Scoping Concern

The trait `representation_priorities` requires finalization of **four separate role-scoped option lists** — one for each consumer role — because the content of representation priorities differs substantially across roles. The Phase C architecture (Section 4.3) designated this as the primary exception to the role-neutral architecture.

Before implementation, the following four role-scoped lists must be finalized:

**Seller-role `representation_priorities` agent options:** Must cover the categories present in the Seller consumer option set (Market Expertise, Strong Negotiator, High Communication & Responsiveness, Local Connections & Network, Marketing Strategy, Staging / Presentation Expertise, Digital & Social Media Marketing, Transaction Management & Coordination) and must include all service categories an agent might legitimately claim as a representation priority for Seller listings.

**Buyer-role `representation_priorities` agent options:** Must cover the Buyer consumer categories (Price Negotiation, Speed of Transaction, Finding Off-Market Properties, Contract Protection, Communication & Updates, Neighborhood Expertise, Investment Analysis, First-Time Buyer Guidance, Relocation Assistance) and extend to include all relevant agent-side service categories.

**Landlord-role `representation_priorities` agent options:** Must cover the Landlord consumer categories (Tenant Screening & Vetting, Marketing & Advertising, Lease Negotiation, Legal & Lease Documentation, Showings & Open Houses, Market Pricing Guidance, Move-In Coordination, Ongoing Communication & Updates) with the same completeness requirement.

**Tenant-role `representation_priorities` agent options:** Must cover the Tenant consumer categories (Neighborhood / location, Budget management, Speed of placement, Lease negotiation, Property condition, Pet-friendly options, Accessibility features, School district) with the same completeness requirement.

Each of the four role-scoped lists must satisfy all four requirements in Sections 3.1 through 3.5.

---

## Section 4 — Minimum Threshold Decision Planning

The Phase F qualitative label "Insufficient Data" applies when not enough scored trait comparisons exist to produce a meaningful summary. Phase F Section 1.1 explicitly deferred the minimum threshold — the specific integer count of scored trait comparisons below which this label applies — to Phase G/H as an implementation decision. Phase G Section 9.3 confirmed the deferral. This section defines the decision framework.

---

### 4.1 Candidate Minimum Thresholds

The following candidates are the starting points for the threshold decision:

**3 scored traits — Candidate floor:** Three is the minimum candidate threshold. Below three scored trait comparisons, the composite advisory signal would be based on data so sparse that any qualitative label would be misleading — a "Strong Alignment" label assigned from two trait comparisons does not reflect meaningful working-style compatibility evidence.

**5 scored traits — Candidate standard:** Five is the candidate standard threshold. Five scored trait comparisons represent a meaningful picture of working-style alignment, covering at least some of the communication, negotiation, and guidance-related traits that are most relevant to day-to-day agent-client interaction.

**Role-specific maximum possible scored trait counts:** The maximum number of scored trait comparisons that can be produced for a listing-bid pair depends on the consumer's role, because some roles lack consumer-side data for certain traits:

| Normalized Trait | Seller | Buyer | Landlord | Tenant |
|---|---|---|---|---|
| `communication_channel` | Yes | Yes | Yes | Yes |
| `communication_frequency` | Yes | Yes | Yes | Yes |
| `responsiveness_expectation` | Yes | No | Yes | No |
| `negotiation_style` | Yes | Yes | Yes | Yes |
| `guidance_level` | Yes | Yes | Yes | Yes |
| `decision_making_style` | Yes | Yes | No | Yes |
| `transaction_pace` | Yes | Yes | No | Yes |
| `collaboration_style` | Yes | Yes | Yes | Yes |
| `representation_priorities` | Yes | Yes | Yes | Yes |
| `representation_philosophy` | Conditional | Conditional | Conditional | Conditional |
| `risk_tolerance` | No | Blocked | Blocked | No |
| `property_strategy_fit` | Blocked | Blocked | Blocked | Blocked |

Maximum scored trait counts (excluding blocked traits and informational-only `representation_philosophy`): Seller = 9, Buyer = 8, Landlord = 7, Tenant = 8. These counts represent the theoretical ceiling if both the consumer and the agent answer every applicable question. In practice, many optional questions will be unanswered.

---

### 4.2 Risks of a Threshold That Is Too Low

Setting the minimum threshold at 2 or 3 creates the following risks:

**False precision:** A "Strong Alignment" or "Broad Compatibility" label assigned from 2 or 3 trait comparisons creates the impression of meaningful working-style evidence when the comparison has covered only a narrow slice of the compatibility trait set. A consumer who sees "Strong Alignment" may believe the engine has thoroughly assessed compatibility when it has compared only the traits both parties happened to answer.

**Misleading summaries from required-field-only responses:** Both consumers and agents are more likely to answer required fields than optional ones. If only the required compatibility fields produce scored comparisons, and the threshold allows a label to be assigned from those few comparisons, the qualitative label reflects required-field answers only — which may not represent the full picture of working-style fit.

**Anchoring on insufficient evidence:** A consumer who sees a qualitative label may anchor on it and de-emphasize reviewing the per-trait detail, even though the label is based on minimal evidence. The lower the threshold, the greater this risk.

---

### 4.3 Risks of a Threshold That Is Too High

Setting the minimum threshold at 7 or 8 creates the following risks:

**Near-universal Insufficient Data labels:** Most consumers answer only the required compatibility questions. Optional questions are, by definition, optional — and completion rates for optional form fields are typically well below 100%. If the threshold is set at 7, and the median consumer answers 4 or 5 scored trait questions, the compatibility system would produce "Insufficient Data" labels for the majority of listings, making the compatibility system functionally invisible.

**Agent experience failure:** Agents who complete all compatibility response fields would see their responses go unused because the consumer side did not meet the threshold. This creates a disincentive for agents to complete response fields.

**System credibility damage:** A compatibility system that produces "Insufficient Data" as its most common output communicates to users that the feature is not yet ready for use, regardless of the engineering quality of the underlying engine.

---

### 4.4 Evaluation Methodology

The threshold should be set using the following evidence-based methodology:

1. **Analyze completion rates of optional compatibility questions** across all four consumer roles using existing listing data in the current platform. Because consumer-side compatibility data is already collected and stored in the EAV meta system, this analysis can be performed on the existing data without any schema changes.

2. **Determine the median number of answered optional compatibility questions per role.** This establishes the realistic baseline: the threshold should ensure that the median consumer who answers only required fields, plus a realistic number of optional fields, still receives a meaningful qualitative label rather than "Insufficient Data."

3. **Set the threshold so that the `Insufficient Data` label is not the default outcome for a consumer who answers only required compatibility fields.** If the required fields for a given role produce 3 or 4 scored trait comparisons, the threshold should be at or below that count.

4. **Document the threshold decision** with the completion-rate evidence that supports it, so that it can be revisited if completion rates change materially after the system is live and agents begin filling out response fields.

5. **Consider role-specific thresholds** if the maximum possible scored trait count differs substantially enough across roles that a single threshold would produce systematically different label distributions across roles.

---

## Section 5 — Compatibility Label Calibration Planning

The four Phase F qualitative labels — Strong Alignment, Broad Compatibility, Notable Differences, Insufficient Data — must eventually be mapped to specific patterns of per-trait alignment results. This mapping is a scoring pipeline implementation decision that requires careful calibration to avoid introducing bias, false precision, or non-advisory framing into the consumer-facing output.

---

### 5.1 Advisory-Only Calibration Principle

The mapping from a distribution of per-trait alignment results to a qualitative label must satisfy three conditions before implementation:

**Justified and documented:** The calibration must be grounded in a principled rationale that can be explained to a compliance reviewer. "Most scored traits show full or partial alignment" is a documentable condition. An arbitrary cutoff derived from trial-and-error testing of what looks good in the UI is not.

**Free of hidden demographic correlation:** The mapping must not introduce demographic correlation through the weighting structure. For example, if certain traits are more likely to show alignment for consumers in specific demographic groups, weighting those traits more heavily would cause the label distribution to correlate with consumer demographics. The compliance review (Section 2.1) must specifically address this risk for the chosen calibration.

**Subject to compliance review before implementation:** No calibration formula may be implemented in production code before a compliance professional has reviewed it. The calibration is a governance matter, not solely a technical one.

---

### 5.2 Anti-Ranking Safeguard

The label mapping must satisfy the following anti-ranking requirement:

The qualitative label must describe the **distribution of alignment results for a single listing-bid pair in isolation**. It must not compare one agent's alignment distribution against another agent's distribution. It must not produce a ranking of agents — two agents with the same qualitative label must be presented identically by the UI, and two agents with different qualitative labels must not be presented as ranked above or below each other.

The label is a description of one agent's compatibility profile relative to one consumer's stated preferences. It is not a score on a scale that positions agents relative to each other.

---

### 5.3 No Hidden Weighting Requirement

If the weighting calibration for the composite advisory signal uses unequal trait weights — that is, if some scored traits contribute more than others to the qualitative label assignment — the following requirements apply before implementation:

**Documented weights:** The specific weight assigned to each scored trait must be documented in the system's governance record. No undisclosed weight may be applied.

**Disclosed in governance record:** The weights must be recorded in a formal governance document (not code comments) that is accessible to compliance reviewers and auditors.

**Reviewed by compliance counsel:** The weighting rationale must be reviewed by compliance counsel before implementation to confirm that unequal weights do not introduce demographic correlation or steering risk.

**Equal weighting as the default:** Equal weighting of all scored traits is the default assumption and the starting point for calibration. Any deviation from equal weighting requires affirmative documentation and compliance review. The burden of justification is on departing from equal weights, not on maintaining them.

---

### 5.4 Proposed Label Mapping Framework (Conceptual — Not Implementation-Ready)

The following framework is a starting conceptual structure, not a finalized calibration. It is provided to frame the compliance review conversation and the engineering implementation discussion. No scoring pipeline should implement this framework without completing the compliance review in Section 2.1 and the equal-weighting vs. unequal-weighting decision in Section 5.3.

**Strong Alignment:** Applied when the distribution of per-trait comparison results shows a majority of scored traits at full alignment or partial alignment, with no traits at incompatible alignment and at most one trait at adjacent compatibility.

**Broad Compatibility:** Applied when a meaningful number of scored traits show alignment (full or partial), but some show adjacent compatibility or neutral compatibility. No scored trait shows incompatible alignment.

**Notable Differences:** Applied when one or more scored traits show incompatible alignment, or when a pattern of adjacent compatibility results across multiple traits suggests meaningful working-style differences that the consumer should review.

**Insufficient Data:** Applied when the number of scored trait comparisons is below the minimum threshold established in Section 4.

---

## Section 6 — AI Moderation & Safety Workflow

Every AI-generated explanation produced by the Phase E AI explanation pipeline must pass a moderation review before being served to any consumer. This section defines the moderation workflow, the distinction between manual and automated moderation, fallback behavior when moderation is unavailable or delayed, escalation handling for rejected explanations, prohibited output categories, and audit logging requirements.

---

### 6.1 Moderation Status Lifecycle

Every AI-generated explanation is assigned a `moderation_status` value when it is generated. The lifecycle is as follows:

**`pending`:** The explanation has been generated by the AI pipeline and stored. It has not yet been reviewed. No explanation with `moderation_status = 'pending'` may be served to any consumer under any circumstances. The consumer's bid review page shows only structured comparison data (qualitative label, per-trait alignment category labels, and each party's stated values) for listing-bid pairs where the explanation is pending.

**`approved`:** A human reviewer or an approved automated classifier has evaluated the explanation and confirmed it does not contain any prohibited output categories. An explanation becomes servable only when its status is `approved`. The approved explanation is served from cache.

**`rejected`:** A human reviewer or automated classifier has evaluated the explanation and found that it contains one or more prohibited output categories. A rejected explanation is never served to consumers and must not be automatically regenerated without a change to the structured inputs that produced it.

**`bypassed`:** Used exclusively for the Gate 1 internal/admin-only build phase. Explanations in this status are visible only to platform administrators for internal review. No `bypassed` explanation may advance to a consumer-visible environment.

---

### 6.2 Manual Moderation — Acceptable for Gate 1 and Gate 2

Manual review — a human reviewer reads the generated explanation and approves or rejects it — is the acceptable moderation approach for Gate 1 (internal/admin-only build) and Gate 2 (hidden beta with internal test users). The manual review process must satisfy the following requirements:

- The reviewer must evaluate the explanation against the complete list of prohibited output categories in Section 6.5.
- The reviewer must record their decision (approve or reject) with their reviewer identifier and a timestamp.
- If rejecting, the reviewer must record the specific prohibited output category or categories that triggered the rejection.
- The manual review SLA for Gate 1 and Gate 2 must be defined before Gate 1 begins. A reasonable starting point is 48-hour turnaround for pending explanations in the internal build environment.

---

### 6.3 Automated Moderation — Required Before Gate 3

Automated moderation — content safety classifiers, rule-based filters for prohibited output categories, or a hybrid human-in-the-loop approach — is required before Gate 3 (consumer-visible beta). Manual moderation alone is not adequate for a consumer-visible environment because:

- Manual review cannot scale to the volume of explanation generation that a consumer-visible deployment produces.
- Manual review introduces latency that would result in large numbers of explanations remaining in `pending` status, degrading the consumer experience.
- Manual review alone does not provide consistent enforcement of the prohibited output category list at scale.

The automated moderation pipeline must be operational, tested, and reviewed by the compliance professional before Gate 3 opens.

---

### 6.4 Fallback Behavior When Moderation Is Delayed or Unavailable

If no approved explanation exists for a listing-bid pair — because the explanation is pending moderation, because moderation has failed, or because no explanation has been generated yet — the compatibility display falls back to the following structured presentation:

**What is shown:** The qualitative label (e.g., "Broad Compatibility"), each per-trait alignment category label translated to plain consumer language per Phase F Section 3.4, and for each trait, the consumer's stated preference and the agent's stated response value.

**What is not shown:** The AI-generated plain-language narrative explanation. The consumer sees the structured comparison data immediately.

**When the AI explanation appears:** The AI explanation appears in the UI when moderation is completed and the explanation's `moderation_status` transitions to `approved`. The display updates dynamically or on page reload.

**What is never shown:** An explanation with `moderation_status = 'pending'` or `moderation_status = 'rejected'` is never surfaced to consumers in any form. The fact that an explanation is pending or has been rejected is not disclosed to the consumer — they see only the structured data fallback with no indication of what caused the explanation's absence.

---

### 6.5 Escalation Handling for Rejected Explanations

When an AI-generated explanation is rejected — whether by manual review or automated classifier — the following escalation rules apply:

**No automatic regeneration:** A rejected explanation must not be automatically regenerated without a change to the structured inputs that produced it. Regenerating from the same inputs will produce the same (or similarly problematic) output and waste moderation resources.

**Rejection reason logging:** The rejection reason — the specific prohibited output category or categories that triggered the rejection — must be logged alongside the rejected explanation in the audit record.

**Repeated rejection flag:** If two or more successive explanations for the same listing-bid pair are rejected, the pair is flagged for human review. Repeated rejection indicates either a systematic issue with the inputs, a gap in the prohibited output category list, or a failure of the AI pipeline to avoid a specific category despite repeated generation attempts. A flagged pair is escalated to a human reviewer who evaluates whether the inputs should be modified, whether the explanation pipeline has a defect, or whether the listing-bid pair is simply ineligible for AI explanation.

**Consumer experience during escalation:** During escalation, the consumer sees the structured data fallback. The absence of an AI explanation is not disclosed as being related to a rejection or escalation.

---

### 6.6 Prohibited Output Categories

The following output categories are explicitly prohibited at all times from any AI-generated explanation. This list is derived from Phase E Section 7.2 and applies to every output the AI explanation pipeline produces, at every gate, for every consumer role:

**Ranking language:** Any statement that implies one agent is better, higher, or more compatible than another agent. Examples: "This agent is your best match," "This agent ranks highest among your bids," "Among the agents who bid on your listing, this agent shows the strongest compatibility."

**Recommendation language:** Any statement that recommends, endorses, or advises the consumer to hire or prefer a specific agent. Examples: "We recommend this agent," "This agent is the right choice for you," "Based on this analysis, you should consider this agent first."

**Demographic references of any kind:** Any reference to the consumer's or agent's age, gender, race, ethnicity, national origin, religion, familial status, disability status, sex, or any other protected characteristic, whether stated or inferred.

**Psychological profiling assertions:** Any statement that characterizes the consumer's or agent's personality type, psychological state, emotional disposition, cognitive style, or behavioral tendency beyond what the structured comparison inputs explicitly contain.

**Predictions about working relationship success:** Any statement that predicts how the consumer and agent will work together, how their relationship will develop, or what outcomes the agent will achieve. Examples: "You and this agent will work well together," "This agent is likely to meet your expectations," "Your communication styles suggest a productive partnership."

**"Perfect fit" or "ideal for you" language:** Any superlative language that implies a definitive or optimal match. Examples: "This agent is a perfect fit for your needs," "This is your ideal agent match," "You and this agent are highly compatible."

**Any output derived from inputs not in the Phase E authorized input set:** Any statement that references data not explicitly included in the structured explanation payload — including neighborhood characteristics, web-sourced agent reputation data, social media content, demographic data inferred from location or property type, or any data not explicitly authorized by Phase E Section 6.1.

---

### 6.7 Audit Logging for Moderation Decisions

Every moderation decision — whether approve or reject, whether by human reviewer or automated classifier — must be logged with the following fields:

- The listing-bid pair identifier
- The AI explanation version tag (`ai_explanation_version`)
- The moderation decision (approved or rejected)
- The reviewer identifier: for human review, a unique reviewer identifier; for automated moderation, the classifier name and version
- The timestamp of the moderation decision
- For rejections: the specific prohibited output category or categories identified as the basis for rejection
- For escalated pairs: the escalation flag, the human reviewer identifier, and the escalation resolution

Moderation audit records must be retained for the same period as the associated compatibility computation audit records (Section 2.6). They must be inspectable by platform administrators without re-running the moderation process.

---

## Section 7 — Rollout Execution Readiness

Phase G Section 8 defined a four-gate rollout strategy. This section expands each gate with specific, measurable exit criteria that must be verified before advancing to the next gate.

---

### 7.1 Gate 1 — Internal / Admin-Only Build

**What is visible:** Compatibility score columns populated in the database, per-trait comparison result records written, AI explanations generated and logged — zero consumer-facing UI.

**Exit Criteria (all must be satisfied before Gate 2 opens):**

1. **Schema verified:** All planned additions to `listing_compatibility_scores` are deployed and confirmed present by database inspection. The `compatibility_framework_version` identifier string is assigned and present in every computed record.

2. **Agent response fields functional:** All four agent bid Livewire components (`SellerAgentAuctionBid`, `BuyerAgentAuctionBid`, `LandlordAgentAuctionBid`, `TenantAgentAuctionBid`) have functional compatibility response field sections covering all seven Phase C sub-sections. `saveMeta` and `loadMeta` calls are implemented and verified.

3. **Scoring pipeline operational:** The scoring pipeline runs on bid submission and listing-update triggers. Per-trait comparison results for all non-blocked scored traits are produced and stored correctly in `compatibility_trait_results`.

4. **`moderation_status = 'pending'` on generation:** Every AI-generated explanation is created with `moderation_status = 'pending'`. No pending explanation is served to any surface.

5. **Audit records complete:** Every compatibility computation produces an audit record containing all fields listed in Phase G Section 7.7. At least 10 audit records have been manually inspected by a platform administrator and confirmed complete.

6. **Internal test review completed:** At least 10 internal test listing-bid pairs have been scored, AI explanations generated, and the results manually reviewed by a platform administrator for governance compliance. No prohibited output categories have been identified in the reviewed explanations.

7. **Blocked traits confirmed absent from scoring:** `risk_tolerance` and `property_strategy_fit` produce no Layer 3 comparison results. Their values are preserved in the structured payload for AI explanation context only, with informational-only status confirmed in the stored records.

---

### 7.2 Gate 2 — Hidden Beta with Internal Test Users

**What is visible:** Compatibility summary and per-trait explanation panels visible to a small, explicitly opted-in group of internal test users only.

**Exit Criteria (all must be satisfied before Gate 3 opens):**

1. **All Gate 1 criteria remain met:** No Gate 1 exit criterion has regressed.

2. **Display components rendering correctly:** Qualitative label, per-trait alignment panels, advisory disclosures, and the expand/collapse interaction render correctly in the bid review UI for all four consumer roles (Seller, Buyer, Landlord, Tenant).

3. **At least one full moderation cycle completed:** At least one complete moderation cycle — generate, pending, manual review, approve or reject — has been executed and the resulting record confirms that the audit log captures the full moderation lifecycle correctly.

4. **Fair Housing compliance professional engaged:** A Fair Housing compliance professional has reviewed the system design document (the Phase A through G documents constitute the system design) and has provided written acknowledgment of the review. Written acknowledgment does not require full sign-off at Gate 2 — it confirms the review has been initiated and the professional is engaged.

5. **Proxy-risk in-progress status explicitly documented:** The proxy-risk analyses for `risk_tolerance` and `property_strategy_fit` are either complete (with findings documented) or their in-progress status is explicitly documented with a target completion date. Both traits remain blocked from scored use in either case.

6. **No display bugs or governance violations identified:** All display bugs identified during internal beta testing are resolved. No governance rule violations (prohibited label language, ranking framing, missing advisory disclosures) are present in the beta UI.

---

### 7.3 Gate 3 — Consumer-Visible Beta

**What is visible:** Compatibility information visible to opted-in consumers and agents in the live application.

**Exit Criteria (all must be satisfied before Gate 4 opens):**

1. **All Gate 2 criteria remain met:** No Gate 2 exit criterion has regressed.

2. **Fair Housing compliance review of consumer-visible UI complete:** A Fair Housing compliance professional has reviewed the consumer-visible UI design — including qualitative label language, advisory disclosures, bid card placement, and per-trait expansion panel content — and has provided written confirmation of the review with any required changes implemented.

3. **Proxy-risk analysis complete or blocked traits explicitly documented:** The proxy-risk analyses for both blocked traits are either complete with findings implemented, or both traits remain blocked with explicit documentation that they are deferred, the reason for continued deferral, and the expected completion timeline.

4. **Minimum scored-trait threshold implemented and validated:** The threshold decided in Section 4 is implemented in the scoring pipeline. The `Insufficient Data` label is correctly applied in test cases where the scored trait count is below the threshold, and a qualitative label is correctly applied in test cases where the count meets or exceeds the threshold.

5. **Automated moderation pipeline operational:** The automated moderation pipeline is deployed and operational. Its review process, tool chain, and SLA are documented. Manual-only moderation is no longer the sole moderation mechanism.

6. **All required advisory disclosures present and visible:** Every required disclosure element (Phase E Section 15 and Phase F Section 5.3) is present in the live UI at every point where compatibility information is surfaced.

7. **No algorithmic sorting active:** No automated or algorithmic reordering of agent bid lists by compatibility signal is present in the codebase at this gate. Bid list ordering is unaffected by compatibility signals.

---

### 7.4 Gate 4 — Production Release

**What is visible:** Compatibility information available to all consumers and agents on the platform.

**Exit Criteria (all must be satisfied before production release):**

1. **All Gate 3 criteria remain met:** No Gate 3 exit criterion has regressed.

2. **Written Fair Housing legal sign-off on file:** Written review and sign-off from qualified legal counsel — not a compliance professional, but legal counsel — is obtained and filed. The sign-off covers the scoring methodology, AI explanation pipeline, consumer-facing display, moderation approach, and advisory disclosure language.

3. **Proxy-risk analysis findings implemented:** Both proxy-risk analyses are complete. If either trait was cleared for scored use, the implementation matches the compliance findings. If either trait was restricted or permanently blocked, the implementation reflects those restrictions with documented rationale.

4. **Performance impact assessed and validated:** The scoring pipeline's impact on page load time, database query performance, and server resource consumption has been assessed under realistic load conditions. Any identified performance issues are resolved before production release.

5. **Monitoring and alerting operational:** Monitoring for compatibility pipeline errors (failed scoring computations, AI explanation generation failures, moderation pipeline failures) is operational with alerting configured for on-call response.

6. **Documentation updated:** Platform documentation reflects the production state of the compatibility engine, including the `compatibility_framework_version` in use, the scored trait set, the qualitative label definitions, and the moderation approach.

---

## Section 8 — Engineering Sequencing Plan

The safest implementation order for the compatibility engine follows a strict dependency chain. No step may begin until all steps it depends on are complete and verified. This section defines the dependency chain and explains why each step is a prerequisite for the next.

---

### 8.1 Step 1 — Schema Implementation (Prerequisite for Everything)

**What happens:** Add the planned columns to `listing_compatibility_scores` (Phase G Section 3.1): `representation_compatibility_score`, `representation_compatibility_label`, `compatibility_trait_results`, `compatibility_framework_version`, `ai_explanation_version`, `moderation_status`, `compatibility_computed_at`, and `compatibility_archived_at`. Confirm the AI explanation storage approach (column on `listing_compatibility_scores` vs. separate `listing_compatibility_explanations` table — Phase G Section 3.3). Increment `scoring_framework_version`. Assign the `compatibility_framework_version` identifier string.

**Why this must come first:** Nothing can be stored until the schema exists. The scoring pipeline, the AI explanation pipeline, the UI display, and the audit log all depend on the schema being in place. Schema implementation has zero consumer-facing impact — it adds columns that the application does not yet read or write — making it the safest possible first step. It can be completed before compliance reviews are finalized.

---

### 8.2 Step 2 — Agent Response Fields in Bid Components (Prerequisite for Normalization and Comparison)

**What happens:** Add the Phase C seven-sub-section compatibility response structure (`compatibility_preferences.agent_response.*`) to all four agent bid Livewire components — `SellerAgentAuctionBid`, `BuyerAgentAuctionBid`, `LandlordAgentAuctionBid`, `TenantAgentAuctionBid`. Follow the existing EAV `saveMeta`/`loadMeta` pattern. Implement validation rules for the structured response fields. Implement draft rehydration in edit mode.

**Why this depends on Step 1:** Agent response data is stored in the bid meta tables, not in `listing_compatibility_scores`, so strictly speaking this step does not depend on Step 1's schema changes. However, Steps 1 and 2 together constitute the consumer-invisible foundation layer — both can be built before compliance reviews are complete, and both should be built before any scoring logic because the normalization and comparison engines have no agent data to process until agents can submit responses.

**Why this must come before Step 3:** The normalization engine (Step 3) and the comparison engine (Step 4) have no agent data to operate on until agents can submit compatibility responses. Building the engines before the data path is established would require testing against synthetic data only and would not produce a verifiable end-to-end result.

---

### 8.3 Step 3 — Normalization Engine (Prerequisite for Comparison Engine)

**What happens:** Implement the Phase B normalization rules that translate raw consumer EAV sub-keys into the 12 normalized trait values. Resolve all four confirmed naming inconsistencies: Seller/Buyer `communication_style` → `communication_frequency`; Landlord `communication_style` → `communication_channel`; Landlord `preferred_contact_method` → `communication_frequency`; Buyer `communication_frequency` → `collaboration_style`. Apply missing-value markers for unanswered optional traits.

**Why this depends on Step 2:** The normalization engine translates consumer-side raw values into normalized form. Its inputs already exist in the database (consumer-side compatibility data is already collected and stored — Phase A finding). Step 2 is not a prerequisite for Step 3 in terms of data availability, but the agent response fields (Step 2) and normalization engine (Step 3) should be built together as the data-layer prerequisites for the comparison engine. They may be built in parallel if engineering resources permit.

**Why this must come before Step 4:** The comparison engine (Step 4) operates on normalized values, not on raw sub-keys. If the comparison engine were built before normalization, it would need to handle the four naming inconsistencies inline — duplicating the normalization logic and creating a maintenance risk. Building normalization first gives the comparison engine clean, consistent inputs.

---

### 8.4 Step 4 — Comparison Engine (Prerequisite for AI Pipeline and UI)

**What happens:** Implement the Phase D per-trait comparison logic producing the five alignment category results (full alignment, partial alignment, adjacent compatibility, neutral compatibility, incompatible alignment) for each scored trait. Implement the Layer 4 explainability output assembly: per-trait alignment summaries, composite advisory signal, and qualitative label assignment. Implement the audit record writer. Apply the minimum threshold from Section 4 to assign the `Insufficient Data` label when appropriate.

**Why this depends on Step 3:** The comparison engine operates on normalized values from the normalization engine. It cannot produce valid comparison results without clean, consistently named trait values.

**Why this must come before Step 5:** The AI explanation pipeline (Step 5) and the UI display (Step 6) both consume comparison engine outputs. The AI explanation pipeline receives the Layer 4 explainability payload, which is the direct output of the comparison engine's Layer 4 assembly step. Building either downstream component before the comparison engine would require stubbing comparison outputs and would not produce a testable end-to-end result.

---

### 8.5 Step 5 — AI Explanation Pipeline (Prerequisite for Moderation UI, but Not for Initial UI Display)

**What happens:** Connect to the AI explanation service using the Phase E authorized input payload only (Phase E Section 6.1). Implement `moderation_status = 'pending'` on generation. Implement caching and invalidation rules (Phase G Section 6.4). Implement the fallback behavior for pending or rejected explanations (Section 6.4 of this document).

**Why this depends on Step 4:** The AI explanation pipeline consumes Layer 4 explainability outputs. It cannot generate explanations without the structured comparison payload from Step 4.

**Why this does not block UI display:** The UI display (Step 6) does not require AI explanations to be operational. The structured comparison data (qualitative label and per-trait alignment results) can be displayed independently. The AI explanation appears in the UI as an enhancement — present when moderation is complete, absent when it is pending. This means Steps 5 and 6 can be built in parallel after Step 4 is complete.

---

### 8.6 Step 6 — UI Display (Depends on Steps 4 and 5 for Full Experience)

**What happens:** Implement the Phase F bid card compatibility summary (qualitative label, advisory gloss, expand trigger). Implement per-trait explanation panels with alignment category labels in plain consumer language (Phase F Section 3.4). Implement the seven-trait side-by-side comparison view (Phase F Section 3.2). Implement all required advisory disclosures. Implement the structured data fallback for pending or rejected AI explanations. Implement informational-only display treatment for `risk_tolerance` and `property_strategy_fit`.

**Why this depends on Step 4:** The UI display depends on the comparison engine's outputs. Without Layer 4 explainability outputs to render, the display components have no data to show.

**Why this should incorporate Step 5 outputs:** The AI explanation panels are part of the UI display component. Steps 5 and 6 should be built together, with the UI panel designed to show structured data alone (Step 4 outputs) as the fallback and the AI narrative (Step 5 outputs) as the enhancement when available.

---

### 8.7 Step 7 — Automated Moderation Pipeline (Required Before Gate 3)

**What happens:** Implement the automated moderation pipeline — content safety classifiers, rule-based filters for the prohibited output categories defined in Section 6.5, or a hybrid approach. Implement the escalation handling workflow (Section 6.5). Document the moderation SLA and the on-call process for escalated pairs.

**Why this can be deferred past Step 6:** Manual moderation is acceptable for Gate 1 and Gate 2. The automated pipeline is required before Gate 3 (consumer-visible beta). Steps 1 through 6 are sufficient to reach Gate 2.

---

## Section 9 — Deferred/Optional Features

This section explicitly separates MVP requirements (features that must be present at Gate 3 for consumer-visible beta) from features that are deferred to future engineering milestones and are not required for initial beta release.

---

### 9.1 MVP Requirements — Must Be Present at Gate 3

The following features and system components are required for the consumer-visible beta (Gate 3). They are not optional for Gate 3 purposes, and Gate 3 may not open without each item verified and operational.

**Schema additions:** All columns specified in Phase G Section 3.1 must be present in the `listing_compatibility_scores` table. The `compatibility_framework_version` must be assigned. `scoring_framework_version` must be incremented.

**Agent response fields in all four bid components:** All four agent bid Livewire components must have the Phase C seven-sub-section compatibility response structure implemented, with `saveMeta`/`loadMeta` persistence and validation rules.

**Normalization engine:** The Phase B normalization rules must be implemented, resolving all four naming inconsistencies and applying missing-value markers for unanswered traits.

**Comparison engine with all non-blocked scored traits:** The Phase D per-trait comparison logic must be implemented for all scored traits except `risk_tolerance` and `property_strategy_fit`, which remain blocked. Layer 4 explainability output assembly must be operational.

**Layer 4 explainability outputs:** The structured per-trait alignment summaries, composite advisory signal, and qualitative label must be produced correctly and stored in `compatibility_trait_results`.

**Qualitative label display on bid cards:** The Phase F qualitative label must appear on each agent bid card in the consumer bid review UI, positioned below services and compensation, with advisory gloss and expand trigger.

**Per-trait explanation panels:** The per-trait expansion panel must show, for each scored trait: the consumer's stated preference, the agent's stated response, and the alignment category label in plain consumer language.

**Advisory disclosures:** All required advisory disclosure elements from Phase E Section 15 and Phase F Section 1.3 must be present and visible at every surface where compatibility information is shown.

**Audit logging:** Every compatibility computation must produce a complete audit record. Audit records must be inspectable by platform administrators.

**Manual moderation (Gate 1 and Gate 2) / Automated moderation pipeline (Gate 3):** Manual moderation is adequate for Gate 1 and Gate 2. The automated moderation pipeline must be operational before Gate 3.

**Minimum scored-trait threshold:** The threshold decided in Section 4 must be implemented and the `Insufficient Data` label must function correctly.

---

### 9.2 Deferred Features — Not Required for MVP

The following features are explicitly deferred and are not required for Gate 3. They should not be scope-creep into the Gate 1 through Gate 3 implementation effort.

**Consumer-initiated sort by compatibility signal:** The ability for a consumer to explicitly sort the agent bid list by compatibility label or score. This feature requires a separate compliance review of the sorting and filtering policy (Section 2.4 and Phase G Section 9.5) before it may be activated at any gate. It is not available in the MVP.

**Side-by-side comparison view across multiple agents:** The Phase F Section 3 feature that allows a consumer to compare compatibility across multiple bidding agents simultaneously in a tabular layout. This view is valuable for consumers evaluating many bids but is not required for the initial consumer-visible beta. The per-trait expansion panel (single-agent view) is the MVP display surface.

**Percentage or range display as secondary context:** The Phase F Section 1.2 optional feature showing "approximately 6 of 9 areas aligned" alongside the qualitative label. The decision on whether to implement this is deferred (Phase G Section 9.4). It is not MVP-required.

**`AgentDefaultProfile` preset integration for compatibility responses:** The Phase G Section 4.5 feature that allows agents to save their compatibility response profile as part of their default `AgentDefaultProfile` preset, enabling pre-fill of compatibility fields when starting a new bid. This is useful for agents who frequently use the preset system but is not required for the initial implementation.

**Scored use of `risk_tolerance` and `property_strategy_fit`:** These two traits are permanently deferred from scored use until proxy-risk analysis is complete and findings are implemented. In the MVP, both traits are informational-only.

**Compatibility-based email or notification features:** Any feature that sends consumers or agents notifications based on compatibility signals is deferred. The compatibility system in the MVP surfaces information in the bid review UI only.

**Admin dashboard reporting on compatibility signal distributions:** Platform-level analytics showing how compatibility labels are distributed across the agent population, across listing types, or across time periods. This is a valuable governance tool but is not required for the initial beta.

**Compatibility score trend analysis across listing versions:** Features that track how a listing-bid pair's compatibility score changes as the listing is edited or the agent's bid is revised. The append-only architecture supports this analysis, but the reporting and display features are deferred.

---

## Section 10 — Final Phase Transition

This section defines the three specific conditions that mark each official transition state in the compatibility engine's lifecycle.

---

### 10.1 "Architecture Complete"

This transition state is defined as satisfied when all three of the following conditions are true:

**All seven Phase documents finalized:** All seven Phase architecture documents — Phase A (`docs/BIDYOURAGENT_COMPATIBILITY_AUDIT.md`), Phase B (`docs/BIDYOURAGENT_COMPATIBILITY_TRAIT_DESIGN.md`), Phase C (`docs/BIDYOURAGENT_AGENT_RESPONSE_DESIGN.md`), Phase D (`docs/BIDYOURAGENT_COMPATIBILITY_SCORING_FRAMEWORK.md`), Phase E (`docs/BIDYOURAGENT_AI_EXPLANATION_LAYER.md`), Phase F (`docs/BIDYOURAGENT_COMPATIBILITY_UI_DESIGN.md`), and Phase G (`docs/BIDYOURAGENT_COMPATIBILITY_IMPLEMENTATION_ARCHITECTURE.md`) — are finalized, read-only, and accepted as the authoritative reference set for all subsequent engineering work. No document in this set may be edited once this transition state is declared.

**No open architectural questions from Phases A–G:** All architectural questions raised within Phases A through G that were marked as unresolved or deferred to a later phase have been documented in Phase H (this document) with a specific resolution path. No unresolved architectural question may remain without a documented path to resolution before implementation begins.

**Phase H readiness gate checklist items documented, assigned, and tracked:** All five categories of the Phase G Section 10 gate checklist — compliance and legal, schema and data, product and design, AI and moderation, implementation readiness — are documented as specific action items, assigned to responsible parties, and being actively tracked toward completion.

---

### 10.2 "Implementation Approved"

This transition state is defined as satisfied when all five of the following conditions are true:

**All five Phase G Section 10 gate checklist categories fully cleared:**

1. *Compliance and legal:* Fair Housing legal review completed with written sign-off; proxy-risk analyses for both blocked traits complete with documented findings; proxy-risk findings designed and confirmed ready for implementation.

2. *Schema and data:* Schema change plan reviewed and approved; EAV meta key naming for agent responses confirmed; audit logging schema confirmed adequate.

3. *Product and design:* Agent response field option sets finalized per Section 3; minimum scored-trait threshold decided and documented per Section 4; percentage/range display decision made; sorting/filtering policy confirmed.

4. *AI and moderation:* AI moderation approach decided per Section 6; AI explanation caching strategy confirmed; AI explanation disclosure language reviewed by compliance counsel.

5. *Implementation readiness:* Rollout gate exit criteria documented per Section 7; audit logging design approved; `compatibility_framework_version` identifier assigned; Phase G architecture document reviewed and accepted.

**Proxy-risk analyses complete with documented findings:** Both analyses — for `risk_tolerance` and for `property_strategy_fit` — are complete. The findings for each trait are documented with the outcome (Outcome A, B, C, or D as defined in Section 1.1 and Section 1.2 of this document).

**Minimum scored-trait threshold decided and documented:** A specific integer minimum has been decided using the methodology in Section 4.4, and the decision is documented with supporting evidence.

**Agent response option sets finalized:** All option sets for all 12 normalized traits, including the four role-scoped lists for `representation_priorities`, satisfy all five requirements in Section 3.

**AI moderation approach decided:** The moderation workflow, tooling, SLA, fallback behavior, escalation handling, and audit logging requirements are all decided and documented per Section 6.

---

### 10.3 "MVP Ready"

This transition state is defined as satisfied when all three of the following conditions are true:

**All "Implementation Approved" conditions are met:** Every condition in Section 10.2 is confirmed satisfied.

**Gate 1 exit criteria confirmed:** Gate 1 (internal/admin-only build) has been completed and all seven Gate 1 exit criteria in Section 7.1 are confirmed satisfied by administrative inspection.

**Gate 2 exit criteria met and documented:** Gate 2 (hidden beta with internal test users) exit criteria in Section 7.2 are met and confirmed in writing. The compatibility system is verified to render correctly in all four consumer role contexts, moderation is functioning, and the compliance professional has engaged in written review.

When "MVP Ready" is declared, the system is ready to advance to Gate 3 (consumer-visible beta) with all required disclosures in place, subject to the final Gate 3 exit criteria in Section 7.3.

---

## Closing Statement and Summary

Phase H is implementation-readiness planning only. Production engineering may begin only after compliance, proxy-risk, and governance gates are formally cleared.

---

### (a) Unresolved Blockers

The following items remain unresolved and constitute blockers that prevent the start of production engineering. Each is an unconditional prerequisite for the gates indicated.

**Proxy-risk analysis for `risk_tolerance` (unconditional block on scored use):** A qualified Fair Housing compliance professional must complete the analysis defined in Section 1.1. Until this analysis is complete with findings documented and implemented, `risk_tolerance` may not be scored for any consumer role at any gate. The blocking applies across all four consumer roles.

**Proxy-risk analysis for `property_strategy_fit` (unconditional block on scored use):** A qualified Fair Housing compliance professional must complete the analysis defined in Section 1.2. Until this analysis is complete with findings documented and implemented, `property_strategy_fit` may not be scored for any consumer role at any gate. The blocking applies across all four consumer roles.

**Fair Housing legal review (unconditional block on Gate 4 / production):** Written review and sign-off from qualified legal counsel — covering the scoring methodology, AI explanation pipeline, consumer-facing display design, moderation approach, and advisory disclosure language — must be obtained before Gate 4 (production release).

**Minimum scored-trait threshold (required for scoring pipeline):** The specific integer threshold that triggers the `Insufficient Data` label must be decided using the methodology in Section 4.4 before the comparison engine can correctly implement the qualitative label assignment logic.

**Agent response option set finalization (required for all four bid components and AI pipeline):** The exact, finalized option labels and stable string identifiers for all 12 normalized trait response fields — including the four role-scoped lists for `representation_priorities` — must be finalized before any implementation of agent response fields or comparison engine logic. Changes to option labels after implementation require a `compatibility_framework_version` increment and a data migration.

**AI moderation approach decision (required before Gate 3):** The moderation workflow, automated pipeline tooling, SLA, escalation handling, and audit requirements must be decided and documented before Gate 3 (consumer-visible beta) may open.

**Weighting calibration for the composite advisory signal (required for scoring pipeline to produce a qualitative label):** The decision about whether all scored traits are weighted equally or whether some receive higher weight — and if unequal, the documented justification and compliance review — must be completed before the comparison engine can map per-trait results to a composite advisory signal and qualitative label.

---

### (b) MVP-Safe Scope

The following implementation scope is safe to begin for Gate 1 and Gate 2 without awaiting completion of the proxy-risk analyses:

**All scored traits except `risk_tolerance` and `property_strategy_fit`** may be implemented in the normalization engine, comparison engine, and Layer 4 explainability outputs without awaiting proxy-risk review, as long as both blocked traits are treated as informational-only throughout. The 10 non-blocked scored traits — `communication_channel`, `communication_frequency`, `responsiveness_expectation`, `negotiation_style`, `guidance_level`, `decision_making_style`, `transaction_pace`, `collaboration_style`, `representation_priorities`, and `representation_philosophy` (structured component where applicable) — may proceed to Gate 1 and Gate 2 immediately once option sets are finalized and schema is deployed.

**The consumer-facing UI** can show qualitative labels and per-trait comparisons for the safe scored traits from Gate 3 onwards. The two blocked traits are displayed as informational-only in the comparison panel, with explicit UI labeling that they are under compliance review and are not scored, until proxy-risk analysis findings determine their final treatment.

**The side-by-side comparison view and consumer-initiated sorting** are not required for the MVP. The per-trait expansion panel (single-agent view) is the MVP display surface for Gate 3.

---

### (c) Recommended First Engineering Milestone

The safest first engineering milestone is **schema implementation**: add the planned columns to `listing_compatibility_scores`, confirm the AI explanation storage approach, increment `scoring_framework_version`, and assign the `compatibility_framework_version` identifier string.

This milestone has zero consumer-facing impact. It adds database columns that the application does not yet read or write. It can be completed before compliance reviews are finalized, before option sets are finalized, and before any other implementation step. It is a prerequisite for every subsequent engineering step — nothing can be stored, scored, moderated, or displayed until the schema exists.

The schema milestone should be paired with the **agent response field additions** to the four bid Livewire components. These additions are also consumer-invisible until the UI display layer is built, can be built before compliance reviews are finalized, and are a prerequisite for the normalization and comparison engines. Together, schema implementation and agent response field additions constitute the complete, consumer-invisible foundation on which all subsequent engineering depends.

---

*Document prepared as Phase H deliverable. Read-only. No code, schema, migration, Livewire component change, Blade file, route, controller, job, scoring logic, AI prompt, API integration, or database write was produced in this phase.*
