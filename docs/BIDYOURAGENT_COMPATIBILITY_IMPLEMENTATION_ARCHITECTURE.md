# BidYourAgent — Professional Representation Compatibility: Implementation Architecture

**Document Status:** Read-only architecture planning document. No code, schema, migration, route, controller, Blade, Livewire, config, or database file was created or modified to produce this document. No implementation occurs in this phase.
**Document Date:** 2026-05-28
**Phase:** G — Compatibility Implementation Architecture
**Preceding Phase:** F — Consumer Compatibility UI Design (`docs/BIDYOURAGENT_COMPATIBILITY_UI_DESIGN.md`)
**Succeeding Phase:** H — Production Implementation (blocked — see Section 10 and closing statement)

---

## Table of Contents

1. [System Architecture Overview](#1-system-architecture-overview)
2. [Data Architecture](#2-data-architecture)
3. [Proposed Schema Planning](#3-proposed-schema-planning)
4. [Livewire / UI Integration Planning](#4-livewire--ui-integration-planning)
5. [Scoring Pipeline Architecture](#5-scoring-pipeline-architecture)
6. [AI Explanation Pipeline](#6-ai-explanation-pipeline)
7. [Governance & Compliance Enforcement](#7-governance--compliance-enforcement)
8. [Rollout Strategy](#8-rollout-strategy)
9. [Risks & Deferred Decisions](#9-risks--deferred-decisions)
10. [Phase H Readiness](#10-phase-h-readiness)
11. [Closing Statement and Summary](#11-closing-statement-and-summary)

---

## 1. System Architecture Overview

### 1.1 Engine Purpose and Scope

The BidYourAgent Hire Agent Compatibility Engine is an **advisory-only** system. It compares a consumer's stated professional working-style preferences against an agent's stated working-style responses and surfaces the result as an informational signal. It does not make decisions, rank agents, or recommend one agent over another.

The engine is scoped exclusively to the **Hire Agent workflow** — the four flows through which consumers hire a Seller agent, Buyer agent, Landlord agent, or Tenant agent. It has no application to, and no data dependency on, any other part of the platform.

### 1.2 Complete Isolation from Other Matching Systems

The compatibility engine is completely isolated from the following existing platform systems. No data flows, score contributions, or display interactions cross these boundaries under any circumstances:

**Property DNA / Buyer-Tenant DNA matching** — The Property DNA and Buyer-Tenant DNA systems measure physical property characteristics, location attributes, financial parameters, and buyer/tenant terms against supply listings. These are distinct from Professional Representation Compatibility, which measures working-style alignment between consumers and agents in the Hire Agent flow. The two systems operate on different data, against different listing types, and must never share score dimensions, intermediate results, or composite signals.

**Offer ranking and compensation-based decisioning** — The compatibility engine does not incorporate compensation data (commission rates, referral fees, flat fees, service package pricing) in any form. Financial compatibility, if ever measured for the Hire Agent flow, belongs in the `financial_match_score` or `terms_match_score` columns of `listing_compatibility_scores` — not in the representation compatibility layer.

**Any listing flow outside the Hire Agent workflow** — The compatibility engine applies only to listings created in the Hire Seller Agent, Hire Buyer Agent, Hire Landlord Agent, and Hire Tenant Agent flows and to bids submitted against those listings by agents.

### 1.3 Advisory-Only Enforcement

The advisory-only nature of the engine is not a UI guideline — it is an architectural constraint enforced at every layer:

- The scoring pipeline produces labelled advisory signals, not rankings.
- The AI explanation pipeline is forbidden from producing ranking, recommendation, or decision-substitution outputs.
- The display layer must not sort, filter, or order agent bids based on compatibility signals without explicit, consumer-initiated sort control.
- Human decision authority over which agent to hire is non-negotiable and is never delegated to the compatibility system.

### 1.4 Phases A–F Foundation

This architecture document synthesizes the six preceding planning phases:

| Phase | Document | Key Deliverable |
|---|---|---|
| A | `docs/BIDYOURAGENT_COMPATIBILITY_AUDIT.md` | 75 consumer-side compatibility fields catalogued across all four roles; zero agent-side counterparts confirmed |
| B | `docs/BIDYOURAGENT_COMPATIBILITY_TRAIT_DESIGN.md` | 12 normalized traits defined; four naming inconsistencies resolved; raw field crosswalks produced |
| C | `docs/BIDYOURAGENT_AGENT_RESPONSE_DESIGN.md` | Agent-side response architecture; seven sub-sections; unified option sets; score-eligibility designations |
| D | `docs/BIDYOURAGENT_COMPATIBILITY_SCORING_FRAMEWORK.md` | Four-layer scoring architecture; five alignment categories; missing data rules; `listing_compatibility_scores` integration point identified |
| E | `docs/BIDYOURAGENT_AI_EXPLANATION_LAYER.md` | AI explanation pipeline; input/output boundaries; governance; disclosure rules; caching deferred to Phase G |
| F | `docs/BIDYOURAGENT_COMPATIBILITY_UI_DESIGN.md` | Consumer UI design; four qualitative labels; bid card integration; seven-trait side-by-side comparison view; per-trait explanation panels |

---

## 2. Data Architecture

### 2.1 Data Flow Overview

The compatibility engine's data flows through five sequential stages from consumer input to consumer-facing output. Each stage is distinct and ordered; no stage may consume data from a stage that succeeds it.

```
Stage 1: Consumer Preference Input
  → Stage 2: Agent Response Input
    → Stage 3: Normalized Trait Layer
      → Stage 4: Comparison Layer (Scoring)
        → Stage 5: Output Layer (Compatibility Summary + AI Explanation)
```

### 2.2 Stage 1 — Consumer Preference Inputs

**Source:** The four role-specific "Representation Preferences & Compatibility" tabs in the consumer Hire Agent listing flows.

**Storage path:** EAV `saveMeta`/`loadMeta` pattern → role-specific `*_agent_auction_metas` table under the `compatibility_preferences` JSON blob, keyed by role:
- Seller: `compatibility_preferences.seller_specific.*`
- Buyer: `compatibility_preferences.buyer_specific.*`
- Landlord: `compatibility_preferences.landlord_specific.*`
- Tenant: `compatibility_preferences.tenant_specific.*`

**Field counts (Phase A):** 22 Seller, 17 Buyer, 16 Landlord, 20 Tenant — 75 total across all roles.

**Current state:** Data is collected and persisted. It drives no matching, scoring, or display logic in the current system.

**Normalization requirement:** Four confirmed naming inconsistencies exist in the raw consumer-side sub-keys (Phase A Section 10 and Phase B Section 7). These must be resolved at Stage 3 via the Phase B normalization rules before any comparison can occur:
1. Seller/Buyer `communication_style` stores frequency data; the normalized trait is `communication_frequency`.
2. Landlord `communication_style` stores channel/method data; the normalized trait is `communication_channel`.
3. Tenant `communication_style` stores channel/method data; the normalized trait is `communication_channel`.
4. Landlord `preferred_contact_method` stores contact frequency options; the normalized trait is `communication_frequency`.
5. Tenant `preferred_contact_method` stores time-of-day data; it does not feed any scored trait.
6. Buyer `communication_frequency` stores showing/meeting format preference; the normalized trait is `collaboration_style`.

### 2.3 Stage 2 — Agent Response Inputs

**Source:** Agent bid Livewire components — currently zero compatibility fields exist in any of the four components (confirmed Phase A Section 2.2).

**Planned storage path:** EAV `saveMeta`/`loadMeta` pattern → bid-specific meta table per role, under:
```
compatibility_preferences.agent_response.*
```

**Planned sub-section structure (Phase C Section 5):**

| Sub-Section Key | Normalized Traits |
|---|---|
| `communication_preferences` | `communication_channel`, `communication_frequency`, `responsiveness_expectation` |
| `negotiation_approach` | `negotiation_style` |
| `working_style` | `collaboration_style` |
| `guidance_approach` | `guidance_level` |
| `transaction_strategy` | `transaction_pace`, `property_strategy_fit` |
| `representation_priorities` | `representation_priorities` (role-scoped sub-responses) |
| `representation_philosophy` | `representation_philosophy`, `decision_making_style`, `risk_tolerance` |

**Design principle:** Agent responses are defined at the normalized trait level, not at the raw consumer field level. A single agent fills out their compatibility response profile once; those responses are compared against listings from any of the four consumer roles.

**Exception for role-scoping:** `representation_priorities` uses role-scoped sub-responses because the content of priority options differs substantially across roles (e.g., Seller priorities include marketing and staging; Landlord priorities include tenant screening and lease documentation).

### 2.4 Stage 3 — Normalized Trait Layer

**Purpose:** Translate raw consumer inputs into the 12 normalized trait values defined in Phase B. Apply the naming inconsistency resolutions. Produce a per-listing set of normalized consumer trait values.

**The 12 normalized traits:**

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
| 9 | `collaboration_style` | The consumer's preferred operating mode for the agent |
| 10 | `representation_priorities` | The specific tasks, capabilities, and outcomes the consumer most wants their agent to deliver |
| 11 | `representation_philosophy` | The consumer's high-level beliefs about what good representation looks like |
| 12 | `property_strategy_fit` | The consumer's primary goal for the transaction and how it shapes the agent's strategic approach |

**Missing value rule:** An unanswered consumer trait produces a missing-value marker, not a default assumption. Missing traits are excluded from Layer 3 comparison processing.

### 2.5 Stage 4 — Comparison Layer (Scoring Pipeline)

**Purpose:** For each trait where both a normalized consumer value and a normalized agent response value exist, produce a per-trait alignment result. The five alignment categories defined in Phase D are:

| Category | Meaning |
|---|---|
| Full alignment | Consumer preference and agent response closely match |
| Partial alignment | Consumer preference and agent response are similar, with minor differences |
| Adjacent compatibility | Consumer preference and agent response differ — worth a direct conversation |
| Neutral compatibility | One or both parties expressed no strong preference, or the agent's approach is fully flexible |
| Incompatible alignment | Consumer preference and agent response differ meaningfully on this trait |

**Output:** A per-trait comparison result set. This is the atomic unit of the system — every subsequent output is derived from these per-trait results.

**Informational-only traits:** `representation_philosophy` (narrative/freeform component), `risk_tolerance` (pending proxy-risk analysis), and `property_strategy_fit` (pending proxy-risk analysis) are preserved for AI explanation context but do not produce Layer 3 comparison results until review is complete.

### 2.6 Stage 5 — Output Layer

**Compatibility summary output:** The Layer 4 explainability outputs from Phase D, including per-trait alignment summaries, the composite advisory signal expressed as one of the four Phase F qualitative labels (Strong Alignment, Broad Compatibility, Notable Differences, Insufficient Data), and audit records.

**AI explanation output:** Plain-language explanations generated from the Layer 4 structured payload by the Phase E AI pipeline. AI explanations are stored persistently and served from cache. (Caching and storage rules defined in Section 6.4.)

**Versioning/audit trail:** Every compatibility computation is linked to a `compatibility_framework_version` identifier that binds the result to the specific scoring rules and trait definitions in effect when it was computed. All inputs and outputs are preserved in an audit record inspectable by platform administrators.

---

## 3. Proposed Schema Planning

*This section is planning-only. No migrations, schema changes, or database writes are produced in Phase G. All schema items described here are candidates for Phase H design and review.*

### 3.1 `listing_compatibility_scores` Table — Planned Additions

The existing `listing_compatibility_scores` table (defined in `database/migrations/2026_05_27_000003_create_listing_compatibility_scores_table.php` and modeled by `app/Models/ListingCompatibilityScore.php`) is the designated integration point identified in Phase D Section 5.3. The table currently contains:

- `physical_match_score`, `financial_match_score`, `location_match_score`, `terms_match_score` — existing score dimensions
- `version`, `scoring_framework_version` — versioning columns
- `archived_at`, `computed_at` — append-only audit timestamps
- `deal_breaker_triggered`, `deal_breaker_flags`, `score_explanation` — existing supporting columns

**Planned new columns:**

| Column Name | Data Type | Purpose |
|---|---|---|
| `representation_compatibility_score` | `decimal(5,2)` nullable | The composite advisory compatibility signal for a listing-bid pair, expressed as a numeric value corresponding to a Phase F qualitative label |
| `representation_compatibility_label` | `string` nullable | The Phase F qualitative label (Strong Alignment, Broad Compatibility, Notable Differences, Insufficient Data) stored as a denormalized display value |
| `compatibility_trait_results` | `jsonb` nullable | Per-trait comparison results — for each scored trait: consumer normalized preference value, agent normalized response value, and alignment category label |
| `compatibility_framework_version` | `string` nullable | The specific version identifier of the compatibility scoring rules used to compute this record — distinct from the existing `scoring_framework_version` which governs the overall scoring framework |
| `ai_explanation_version` | `string` nullable | Version tag for the AI explanation pipeline that generated the stored explanation, enabling explanation invalidation when pipeline rules change |
| `moderation_status` | `string` nullable | Status of AI explanation moderation review: pending, approved, rejected, or bypassed (for internal/admin-only build gates) |

**Audit metadata fields** (planned):

| Column Name | Purpose |
|---|---|
| `compatibility_computed_at` | Timestamp for when the compatibility score was computed — separate from `computed_at` which may cover non-compatibility score computation |
| `compatibility_archived_at` | Nullable timestamp marking a compatibility result as superseded by a newer computation |

### 3.2 `scoring_framework_version` Increment Requirement

The existing `scoring_framework_version` column must be incremented when the `representation_compatibility_score` dimension is added to the framework. Any score record computed without representation compatibility data must not be directly compared to a record computed with it. Phase H must define the new version identifier and the migration logic that handles the transition.

### 3.3 AI Explanation Storage

The Phase E AI explanation pipeline produces plain-language Layer 5 outputs that must be stored persistently. Phase H must determine the storage location. Two candidate approaches for Phase H consideration:

1. **Column on `listing_compatibility_scores`** — A `jsonb` column (e.g., `ai_explanation_payload`) storing the full structured explanation payload alongside the score record. Simple and co-located with the score data.

2. **Separate `listing_compatibility_explanations` table** — A related table with one record per explanation generation event, supporting explanation versioning independently of score versioning. More flexible but adds a join.

Neither approach is prescribed here. Phase H must choose and document the decision.

### 3.4 Agent Response Meta Storage

Agent compatibility responses will be stored in the bid-specific meta table for each role, following the existing EAV `saveMeta`/`loadMeta` pattern used throughout the platform:

| Role | Bid Meta Table |
|---|---|
| Seller | `seller_agent_auction_bid_metas` |
| Buyer | `buyer_agent_auction_bid_metas` |
| Landlord | `landlord_agent_auction_bid_metas` |
| Tenant | `tenant_agent_auction_bid_metas` |

No new tables are required for agent response storage — the existing meta tables are sufficient. The agent response data will live under the `compatibility_preferences` meta key, at the `agent_response` sub-path, consistent with the consumer-side storage pattern.

### 3.5 `compatibility_framework_version` Locking

Every compatibility computation must record the `compatibility_framework_version` in use at the time of computation. If the normalization rules, option set definitions, scoring methodology, or trait eligibility matrix change in a future phase, stored results computed under an earlier framework version must be clearly distinguishable from results computed under the new version. Stale results must be archived (not deleted) and a fresh computation triggered under the new version.

---

## 4. Livewire / UI Integration Planning

*This section identifies all future-affected components and what each will need. No code changes to any component occur in Phase G.*

### 4.1 Agent-Side Bid Components — Compatibility Response Fields

The following four agent bid Livewire components currently have zero compatibility fields (confirmed Phase A Section 2.2). Each will eventually require:

**`app/Http/Livewire/Seller/SellerAgentAuctionBid.php`**
- New public property: `$compatibility_response` (array matching the Phase C seven-sub-section structure)
- New validation rules for structured response fields within the sub-sections
- `saveMeta` calls to persist `compatibility_preferences.agent_response.*` sub-keys to `seller_agent_auction_bid_metas`
- `loadMeta` / `loadDraft` logic to rehydrate agent response fields in edit mode

**`app/Http/Livewire/Buyer/BuyerAgentAuctionBid.php`**
- Same requirements as Seller, targeting `buyer_agent_auction_bid_metas`

**`app/Http/Livewire/Landlord/LandlordAgentAuctionBid.php`**
- Same requirements as Seller, targeting `landlord_agent_auction_bid_metas`

**`app/Http/Livewire/Tenant/TenantAgentAuctionBid.php`**
- Same requirements as Seller, targeting `tenant_agent_auction_bid_metas`

**Common requirements for all four components:**
- A new wizard tab or section within the existing bid form wizard, presenting the seven Phase C sub-sections as grouped, clearly labelled form fields
- Select2 + `wire:ignore` pattern or `wire:model` (consistent with each component's existing pattern) for single-select trait fields
- Multi-select pattern (Select2 + `wire:ignore` + `@this.set()` JSON bridge) for `communication_channel` and `representation_priorities`
- Optional free-text fields for informational context sub-fields (narrative notes, scheduling preferences, philosophy statements)
- Role-scoped sub-response fields for `representation_priorities`, using the role-appropriate option set from the Phase C architecture

### 4.2 Consumer Bid Review Pages — Compatibility Summary Display

The consumer-facing bid review pages for all four Hire Agent listing types will need:

- Retrieval of the `representation_compatibility_score`, `representation_compatibility_label`, and `compatibility_trait_results` for each agent bid displayed
- Rendering of the Phase F qualitative label within each bid card, positioned below services and compensation per Phase F Section 2.3
- A one-line advisory gloss accompanying the label
- An expand/collapse trigger opening the per-trait explanation panel (Section 4.3)
- The advisory disclosure statement per Phase F Section 1.3 requirements

### 4.3 Per-Trait Explanation Panels

When a consumer expands the compatibility detail for an agent bid, the UI must display:

- The seven-trait side-by-side comparison set defined in Phase F Section 3.2, organized as a table or structured list
- Per-trait cells showing the consumer's stated preference, the agent's stated response, and the alignment category label (translated to plain consumer language per Phase F Section 3.4)
- Missing data display per Phase F Section 3.5 — transparent "You did not answer this question" and "This agent did not respond to this question" labels
- Informational-only trait display (distinct visual treatment for `risk_tolerance` and `property_strategy_fit` pending proxy-risk analysis)
- AlpineJS `x-show` / `x-data` expand/collapse interaction for individual trait panels
- The AI-generated plain-language explanation for each trait where an explanation has been generated and moderated

### 4.4 Side-by-Side Comparison View

An optional, consumer-initiated comparison view covering multiple bidding agents simultaneously:

- Organized around the consumer's stated preferences (consumer preferences form the reference column)
- Each agent's responses displayed in parallel columns
- Must not imply a ranked order or reorder agents by compatibility signal automatically
- Requires AlpineJS interaction code for panel expansion, column toggle, and responsive layout
- TailwindCSS implementation of the badge and alignment indicator visual language from Phase F Section 6

### 4.5 `AgentDefaultProfile` and Preset Integration

The Agent Default Profile system (`AgentBidMapperService` / `AgentDefaultProfile`) should eventually be extended to allow agents to save their compatibility response profile as part of their default preset. This allows agents who use the preset system to have their compatibility responses pre-filled when starting a new bid, consistent with how the existing service pre-fills bio, credentials, compensation fields, and promotional materials.

This integration does not change the preset storage schema (`AgentDefaultProfile.profile_data` JSON column) — it adds a `compatibility_response` key within the existing JSON payload.

---

## 5. Scoring Pipeline Architecture

### 5.1 When Compatibility Is Calculated

Compatibility is calculated (or recalculated) in two triggering events:

**Trigger 1 — Agent bid submission (or edit save):** When an agent submits a new bid or saves an edit to an existing bid that includes compatibility response data, the compatibility score for that specific listing-bid pair is computed or updated.

**Trigger 2 — Listing update with new compatibility data:** When a consumer updates their listing and changes one or more compatibility preference fields, all existing bid compatibility scores for that listing are invalidated and must be recomputed. A recomputed score is written as a new append-only record; the prior record is archived (`compatibility_archived_at` is set), not deleted.

### 5.2 Recalculation Triggers

| Event | Action |
|---|---|
| Agent submits new bid with compatibility responses | Compute new compatibility score; write new record |
| Agent edits existing bid, changes compatibility responses | Archive prior record; compute new record |
| Consumer updates listing compatibility preferences | Archive all prior records for this listing; queue recomputation for all current bids |
| `compatibility_framework_version` change | Archive all prior records; queue recomputation for all active listing-bid pairs |
| AI explanation pipeline version change (`ai_explanation_version`) | Invalidate cached explanations; queue regeneration — score records are not invalidated |

### 5.3 Missing Data Handling

**Consumer missing trait:** If the consumer did not answer an optional compatibility question, the corresponding normalized trait produces a missing-value marker. No default assumption is made. The missing trait is excluded from Layer 3 comparison processing and contributes to a lower scored-trait count that may trigger the `Insufficient Data` label (see Section 5.4).

**Agent missing response:** If the agent did not answer one or more compatibility response fields, the affected traits produce missing-value markers on the agent side. No default assumption is made. The trait is excluded from comparison.

**Role-specific structural missing trait:** For traits with no consumer-side data source for a given role (e.g., `responsiveness_expectation` has no consumer field for Buyer or Tenant listings; `risk_tolerance` has no consumer field for Seller or Tenant listings), the trait is treated as absent for that role and excluded from comparison. The agent's response for the trait may be displayed as informational context only.

**The missing-value marker principle:** A missing value is never treated as a neutral, flexible, or default response. It is treated as absent data. Any comparison logic that would substitute a default value for a missing input is prohibited.

### 5.4 Minimum Scored-Trait Threshold

The Phase F Section 1.1 `Insufficient Data` label is applied when fewer than a minimum number of scored trait comparisons exist. The specific minimum threshold was deferred from Phase F to Phase G as an implementation decision.

**Phase G decision requirement:** Phase H must have a specific minimum threshold defined and documented before implementation begins. The threshold must be chosen to ensure that the qualitative label is meaningfully grounded in actual comparison data. A minimum of 3 scored trait comparisons is a candidate floor; a minimum of 5 is a candidate standard threshold. This decision requires input from compliance review.

### 5.5 Informational-Only Trait Handling

Traits designated as informational-only are handled as follows:

- Their consumer values and agent response values are preserved in the data record.
- They are passed to the AI explanation payload (Layer 4) as informational-context fields.
- They do not produce Layer 3 comparison results.
- They do not contribute to the scored-trait count.
- They are not reflected in the qualitative label or the composite advisory signal.
- In the consumer UI, they are displayed with a distinct visual treatment indicating their informational-only status (no alignment label, no alignment indicator).

**Current informational-only traits:**
- `representation_philosophy` (narrative/freeform component — the structured multi-select component is conditionally score-eligible for Seller and Tenant listings)
- `risk_tolerance` — blocked from scored use pending proxy-risk analysis
- `property_strategy_fit` — blocked from scored use pending proxy-risk analysis

### 5.6 Proxy-Risk Trait Blocking

The traits `risk_tolerance` and `property_strategy_fit` are flagged as proxy-risk risks in Phase D Section 14.3 and Phase F Section 7.2. These traits are blocked from any scored use — in Layer 3 comparison processing, in the composite advisory signal, and in the consumer-facing display — until a qualified Fair Housing compliance professional completes a proxy-risk analysis for each trait and the result of that analysis is documented and approved.

**The blocking is unconditional.** No implementation workaround, no temporary bypass, and no phased-in partial use is permissible. The traits are informational-only until the review is complete.

### 5.7 `compatibility_framework_version` Locking

Every scored result record must store the `compatibility_framework_version` identifier in effect at the time of computation. The version identifier governs:

- Which normalization rules were applied (Phase B rules)
- Which traits were scored vs. informational-only
- Which option value crosswalks were used to compare consumer and agent options
- Which minimum threshold applied to the `Insufficient Data` determination

If any of these rules change, the version must be incremented and all prior results archived. Historical results computed under an earlier version must be retrievable for audit purposes.

---

## 6. AI Explanation Pipeline

### 6.1 What AI Receives (Inputs)

The AI explanation system receives one structured payload — the Layer 4 explainability output from Phase D. AI never receives raw consumer EAV data, raw agent meta data, or any data that has not been processed through the normalization and comparison layers.

**Authorized AI inputs (exhaustive):**
- Normalized consumer trait values (structured labels, not raw meta key values)
- Normalized agent response values (structured labels at the trait level)
- Per-trait comparison result labels from Layer 3
- Approved informational-context field values: narrative notes, timeline notes, scheduling and availability preferences, representation philosophy statements, past experience notes
- Alignment and misalignment category labels from the Layer 4 explainability layer

**Explicitly prohibited AI inputs:**
- Raw EAV data from any `*_agent_auction_metas` or `*_agent_auction_bid_metas` table
- Demographic data of any kind
- Protected-class data of any kind
- Neighborhood demographic data
- Compensation or fee data
- Ranking or sorting signals
- Behavioral profiling data
- Unstructured web data
- Social media data
- Any data not explicitly listed above as authorized

### 6.2 What AI Produces (Outputs)

**Authorized AI outputs:**
- Plain-language alignment summaries describing what the structured comparison found across scored traits
- Trait-level explanations: for each scored trait, what the consumer indicated, what the agent indicated, and what the comparison found — in plain, accessible language
- "Areas to discuss" summaries: traits where the consumer and agent would benefit from a direct conversation before a hiring decision
- Advisory framing language accompanying every explanation

**Explicitly prohibited AI outputs:**
- Compatibility scores, percentages, or numerical signals (AI does not produce scoring results)
- Rankings of agents relative to each other
- Recommendations to hire or avoid any specific agent
- "Best match," "top match," "most compatible," "perfect fit," or any superlative language
- Demographic references of any kind
- Psychological profiling or personality type assertions
- Predictions about the success of the working relationship
- Any language functioning as a decision substitute for the consumer
- Any output derived from data not in the authorized input set

### 6.3 AI Position in the Pipeline

AI is strictly downstream from the scoring pipeline. It cannot:

- Override, re-weight, or contradict Layer 3 comparison results
- Produce a compatibility score independent of the Layer 3 results
- Access Layers 1, 2, or 3 directly
- Modify the Layer 4 payload it receives

AI enters at Layer 4 and produces Layer 5 outputs. The Layer 4 payload is the complete boundary of AI's knowledge about the comparison.

### 6.4 Caching and Storage Rules

**Caching strategy (deferred from Phase E to Phase G — Phase E Section 17):**

AI explanations are computationally expensive relative to score computation. The following caching rules apply:

- A generated explanation is stored persistently alongside the compatibility score record it describes.
- The explanation is keyed by a combination of: the `listing_compatibility_scores` record ID, the `compatibility_framework_version`, and the `ai_explanation_version`.
- An explanation is invalidated and must be regenerated when: (a) the underlying score record is archived and replaced, (b) the `compatibility_framework_version` changes, or (c) the `ai_explanation_version` changes (indicating a change to the AI explanation pipeline rules).
- An explanation is not regenerated solely because the consumer views it multiple times — a valid cached explanation is served directly.
- During the internal/admin-only build gate (Rollout Gate 1), explanation caching may be simpler or absent; caching infrastructure is required before any consumer-visible beta (Rollout Gate 3).

**Moderation before production use:**

Every AI-generated explanation must pass a moderation review before it is served to any consumer in a production environment. The `moderation_status` column (see Section 3.1) tracks the moderation state of each explanation. An explanation with `moderation_status = 'pending'` or `moderation_status = 'rejected'` must not be shown to consumers.

During the internal build and hidden beta gates, moderation may be performed manually by internal reviewers. An automated moderation pipeline is required before consumer-visible beta (Rollout Gate 3) launches.

### 6.5 Required Disclosures with Every Explanation

Every AI-generated explanation surfaced to a consumer must be accompanied by:

1. A statement that the explanation is generated by an AI system from structured comparison data.
2. A statement that the explanation is advisory only and does not rank agents or recommend one over another.
3. A statement that the consumer's hiring decision remains entirely theirs.
4. A statement that compatibility is one factor among many, including services, compensation, experience, and the consumer's own direct conversation with the agent.

These disclosures must be visible, adjacent to the explanation, and in plain language. They must not be hidden in collapsed sections, footnotes, or behind additional interaction steps.

---

## 7. Governance & Compliance Enforcement

### 7.1 Fair Housing Safeguards

The compatibility engine is subject to the Fair Housing Act, the Equal Credit Opportunity Act, and applicable state fair housing laws. The following safeguards are non-negotiable at every layer of the system:

**Absolute prohibition on protected-class references:** No data field, option label, scoring dimension, comparison result, AI input, AI output, or display element may reference, encode, proxy, or allow inference of any characteristic protected under the Fair Housing Act or applicable law, including but not limited to: race, color, national origin, religion, sex, familial status, disability, age, sexual orientation, marital status, source of income, or gender identity.

**No demographic data in any pipeline stage:** Demographic characteristics of consumers or agents may not enter the compatibility pipeline at any stage — not as inputs, not as scoring parameters, not as AI explanation inputs, and not as display elements.

**No inferred demographic data:** Fields that are neutral on their face but that correlate with or can be used to infer protected-class characteristics must be identified and excluded. The proxy-risk analysis requirement for `risk_tolerance` and `property_strategy_fit` exists specifically because these traits carry potential proxy-risk under this rule.

**No "people like you" framing:** The compatibility system must not use language or logic that groups consumers or agents into demographic categories, market segments, or profiles based on protected characteristics.

### 7.2 Proxy-Risk Blockers

**`risk_tolerance` — BLOCKED from scored use:**
The Landlord role's risk tolerance options (Low – Strict Screening Only through High – Willing to Work With Most Tenants) map directly to tenant screening strictness. Scoring this trait in a way that disadvantages agents who serve tenants with non-standard financial profiles may function as a proxy for screening against protected classes, given that tenants with non-standard financial backgrounds are disproportionately members of protected classes in many markets.

No production display of a scored `risk_tolerance` comparison may appear in any gate of the rollout until a proxy-risk analysis for this trait has been completed and reviewed by a qualified Fair Housing compliance professional. This block applies across all four consumer roles, not only Landlord.

**`property_strategy_fit` — BLOCKED from scored use:**
Certain transaction type options in this trait (e.g., investment property acquisition, fix & flip) may correlate with transaction patterns associated with specific demographic groups in some markets. Scoring this trait without a proxy-risk analysis risks introducing demographic correlation into the compatibility signal.

No production display of a scored `property_strategy_fit` comparison may appear in any gate of the rollout until the same proxy-risk analysis and compliance review requirement is met.

### 7.3 No Steering

The display architecture must not produce a steering effect. Specific rules:

- The platform must not automatically sort, filter, or order agent bid lists based on compatibility signals. Consumer-initiated sorting (where the consumer explicitly chooses to sort by compatibility) may be permitted after compliance review, subject to steering analysis.
- The compatibility UI must not arrange agents in a way that creates a visible scoring hierarchy that functionally excludes lower-compatibility agents from consideration.
- Display designs that could function as steering based on the distribution of compatibility scores across agents in protected-class contexts must be identified and reviewed before implementation.

### 7.4 No "Best Agent" Language

At no point in the system — in scoring logic, AI output, display copy, tooltips, advisory notes, disclosure text, or any other surface — may the compatibility system claim, suggest, or imply that any agent is the "best," "top," "most compatible," "recommended," or "ideal" agent for the consumer.

The qualitative labels (Strong Alignment, Broad Compatibility, Notable Differences, Insufficient Data) describe the distribution of alignment results. They do not declare winners.

### 7.5 No Automatic Winner Selection

The compatibility system must not select a winning agent, award a bid, or take any action that substitutes for the consumer's own hiring decision. Compatibility signals are inputs to the consumer's decision-making process. They are not decision outputs.

### 7.6 Human Decision Authority

All consequential decisions — including which agent to hire — remain with the human parties. This principle is non-negotiable and applies regardless of compatibility signal strength. No compatibility score, label, or AI explanation constitutes an endorsement, a disqualification, or a delegation of decision authority.

### 7.7 Audit Logging Requirements

Every compatibility computation must produce an audit record that contains:

- The listing identifier and listing type (Seller, Buyer, Landlord, Tenant)
- The bid identifier
- The `compatibility_framework_version` in effect
- The normalized consumer trait values used as inputs (per trait)
- The normalized agent response values used as inputs (per trait)
- The per-trait comparison result labels
- The composite advisory signal and qualitative label
- The `computed_at` timestamp
- Whether any traits were excluded due to missing data, proxy-risk blocking, or informational-only designation
- The `moderation_status` of any associated AI explanation

Audit records must be inspectable by platform administrators without re-running the comparison. They must be retained for compliance review purposes. No audit record may be deleted while a corresponding listing or bid is active.

---

## 8. Rollout Strategy

Implementation of the compatibility engine must follow a four-gate rollout. Each gate is a prerequisite for the next. No gate may be opened until all requirements for that gate are met.

### Gate 1 — Internal / Admin-Only Build

**What is visible:** Compatibility score columns populated in the database, per-trait comparison result records written, AI explanations generated and logged — but zero consumer-facing UI. No compatibility signal is visible to any consumer or agent in the application UI.

**Who can see it:** Platform administrators and internal engineers only, via database inspection and admin tooling.

**Purpose:** Verify that the scoring pipeline runs correctly end-to-end, that data is stored accurately, that the audit trail is complete, and that no governance rule violations appear in the scoring outputs or AI explanations before any user ever sees them.

**Gate 1 exit requirements:**
- Schema additions deployed and verified
- Agent-side response fields functional in all four bid components
- Scoring pipeline running on bid submission and listing update triggers
- Per-trait comparison results being stored correctly
- AI explanations being generated and stored with correct `moderation_status = 'pending'` initial state
- Audit records complete and inspectable
- Internal review of a sample of AI explanations for governance compliance

### Gate 2 — Hidden Beta with Opted-In Internal Test Users

**What is visible:** Compatibility summary and per-trait explanation panels visible to a small group of internal test users who have explicitly opted in. No consumer in the general population sees any compatibility information.

**Purpose:** Validate the end-to-end experience in the application UI, identify display bugs, validate AI explanation quality in context, and conduct a preliminary compliance review of the display design.

**Gate 2 exit requirements:**
- All Gate 1 requirements remain satisfied
- Display components rendering correctly in all four consumer role contexts
- AI explanation moderation process running (manual review acceptable at this gate)
- No governance rule violations identified in tested explanation samples
- Proxy-risk analysis status reviewed (may still be in progress at this gate, provided scored `risk_tolerance` and `property_strategy_fit` remain blocked)
- Fair Housing compliance professional has been engaged and has reviewed the system design

### Gate 3 — Consumer-Visible Beta with Explicit Advisory Disclosures

**What is visible:** Compatibility information visible to opted-in consumers and agents in the live application. Compatibility signals appear in the bid review UI, per the Phase F design, with all required advisory disclosures.

**Conditions:**
- No algorithmic sorting or filtering of agent lists by compatibility signal at this gate. Compatibility is displayed alongside bids, but bid order is not affected.
- All required advisory disclosures per Phase E and Phase F are present and visible.
- AI explanation moderation pipeline is operational (not solely manual).
- AI explanations have `moderation_status = 'approved'` before being served to consumers.

**Gate 3 exit requirements:**
- All Gate 2 requirements remain satisfied
- Fair Housing compliance review of the consumer-visible UI design is complete and documented
- Proxy-risk analysis for `risk_tolerance` and `property_strategy_fit` is complete (or those traits remain blocked with explicit documentation of continued deferral)
- Minimum scored-trait threshold is defined, implemented, and validated
- AI moderation pipeline is operational with documented review process
- No consumer-reported display issues or governance concerns unresolved

### Gate 4 — Production Release

**What is visible:** Compatibility information available to all consumers and agents in the platform.

**Conditions:**
- Fair Housing legal sign-off is obtained and documented.
- Proxy-risk analysis for both flagged traits is complete and the outcome is documented.
- All deferred decisions from Section 9 are resolved.
- Rollout gates 1, 2, and 3 have been completed in sequence.

**Gate 4 exit requirements:**
- All Gate 3 requirements remain satisfied
- Written Fair Housing legal review and sign-off on file
- Proxy-risk analysis findings documented and any required display changes implemented
- Performance impact of scoring pipeline assessed and optimized
- Monitoring and alerting for compatibility pipeline errors operational
- Documentation updated to reflect production state

---

## 9. Risks & Deferred Decisions

The following items are open and must be resolved before Phase H begins implementation. None may be defaulted, assumed, or bypassed.

### 9.1 Proxy-Risk Review — Required Before Any Scored Use

**`risk_tolerance` proxy-risk analysis:** A qualified Fair Housing compliance professional must analyze whether scoring `risk_tolerance` in the Landlord role context (and potentially other roles) creates a proxy mechanism for protected-class screening. The analysis must be documented and its recommendations implemented before any scored use of this trait.

**`property_strategy_fit` proxy-risk analysis:** A qualified Fair Housing compliance professional must analyze whether scoring `property_strategy_fit` (particularly investment property and fix & flip transaction types) creates a demographic correlation risk. The analysis must be documented and its recommendations implemented before any scored use of this trait.

**Risk if unresolved:** Scored use of either flagged trait without completed proxy-risk analysis creates Fair Housing legal exposure for the platform.

### 9.2 Fair Housing Legal Review — Required Before Production

A full Fair Housing legal review of the compatibility engine — its data inputs, scoring methodology, AI explanation pipeline, and consumer-facing display — must be completed by qualified legal counsel before any consumer-visible deployment. This is distinct from the compliance professional review required at Gate 2. The legal review is a Phase H entry gate requirement for Gate 4.

### 9.3 Minimum Scored-Trait Threshold Decision

Phase F Section 1.1 explicitly deferred the minimum scored-trait threshold (the number of trait comparisons below which the `Insufficient Data` label applies) to Phase G as an implementation decision.

**What must be decided:** A specific integer minimum, validated against the total possible scored-trait counts for each consumer role (noting that some roles lack consumer-side data for certain traits, reducing the possible maximum). The decision must consider both the statistical meaningfulness of the label and the user experience implications of showing `Insufficient Data` when most optional questions are unanswered.

**Risk if unresolved:** Without a defined threshold, the scoring pipeline cannot implement the `Insufficient Data` label correctly, and consumers may see qualitative labels based on fewer trait comparisons than are meaningful.

### 9.4 Percentage / Range Display Decision

Phase F Section 1.2 deferred the question of whether to implement a secondary percentage or range display (e.g., "approximately 6 of 9 areas aligned") alongside the qualitative label. The conditions under which percentage display is appropriate and inappropriate are defined in Phase F Section 1.2, but the implementation decision was deferred to Phase G.

**What must be decided:** Whether percentage display will be implemented at all, and if so: the format (percentage, ratio, or range), the precision level (rounded integer vs. range), and the minimum scored-trait count required before a percentage is shown.

**Risk if unresolved:** Without a decision, Phase H cannot implement the compatibility summary display component completely.

### 9.5 Sorting and Filtering Restrictions

The platform must define, before any consumer-visible display, the rules governing whether and how consumers may sort or filter agent bid lists by compatibility signal.

**What must be decided:**
- Whether consumer-initiated sorting by compatibility signal is permitted at all.
- If permitted: at which rollout gate, with which advisory disclosure, and subject to which steering analysis.
- Whether algorithmic sorting (platform-initiated reordering) by compatibility is permitted at any gate.

The default position, per the governance rules in Section 7.3, is that no automatic or algorithmic sorting by compatibility is permitted. Consumer-initiated sorting may be permitted only after compliance review confirms no steering risk.

### 9.6 Weighting Calibration for Partial-Match Scoring

Phase D defined the five alignment categories conceptually but did not assign weights to each category for composing the overall advisory signal. The `representation_compatibility_score` numeric value (which corresponds to a Phase F qualitative label) requires a formula that translates the distribution of per-trait alignment results into a single advisory signal.

**What must be decided:**
- Whether all scored traits are weighted equally or whether some traits receive higher weight.
- The mapping from the distribution of per-trait results to the four qualitative labels.
- Whether weighting decisions are subject to compliance review (they should be, to ensure no hidden demographic correlation is introduced through the weighting structure).

**Risk if unresolved:** Without a defined weighting approach, the scoring pipeline cannot produce a `representation_compatibility_score` value or assign a qualitative label.

### 9.7 Agent Response Field Option Set Finalization

The Phase C agent response architecture defines the trait structure and the general option set direction, but the exact option labels, option counts, and option ordering for each trait field must be finalized before implementation. Finalizing option sets is a Phase H implementation prerequisite because:

- Option labels are stored in both consumer data and agent response data and must be crosswalk-compatible.
- Option labels appear in AI explanation inputs and must be self-explanatory at the normalized trait level.
- Option labels appear in the consumer-facing comparison UI and must be readable without context.

### 9.8 AI Moderation Approach

Phase E requires that every AI-generated explanation pass a moderation review before being served to consumers. The moderation approach — manual, automated, or hybrid — must be decided and implemented before Gate 3 (consumer-visible beta).

**What must be decided:**
- The moderation workflow (who reviews, what criteria, what remediation path for rejected explanations).
- The automated moderation tooling, if any (content safety classifiers, rule-based filters, or human-in-the-loop hybrid).
- The SLA for moderation review (how quickly must a pending explanation be reviewed before the compatibility display falls back to showing only structured comparison data without an AI explanation).

---

## 10. Phase H Readiness

Phase H may not begin implementation until every item on the following gate checklist is completed, reviewed, and documented. This checklist is the authoritative definition of "Phase H ready."

### 10.1 Compliance and Legal Gate

- [ ] **Fair Housing legal review completed** — Written review and sign-off from qualified legal counsel on the compatibility engine design, scoring methodology, AI explanation pipeline, and consumer-facing display.
- [ ] **Proxy-risk analysis completed for `risk_tolerance`** — Written analysis from a qualified Fair Housing compliance professional, with documented outcome (cleared for scored use, cleared with conditions, or blocked with findings).
- [ ] **Proxy-risk analysis completed for `property_strategy_fit`** — Same requirement as above.
- [ ] **Proxy-risk findings implemented** — Any conditions or required changes from the proxy-risk analyses are designed, reviewed, and confirmed ready for implementation.

### 10.2 Schema and Data Gate

- [ ] **Schema change plan approved** — The planned additions to `listing_compatibility_scores` (Section 3.1), the AI explanation storage approach (Section 3.3), and the `scoring_framework_version` increment (Section 3.2) have been reviewed and approved.
- [ ] **EAV meta key naming for agent responses confirmed** — The `compatibility_preferences.agent_response.*` sub-key structure has been confirmed consistent with the existing EAV pattern for all four bid meta tables.
- [ ] **Audit logging schema approved** — The audit record structure (Section 7.7) has been confirmed adequate for compliance inspection.

### 10.3 Product and Design Gate

- [ ] **Agent response field option sets finalized** — The exact option labels for all 12 normalized trait response fields have been reviewed, finalized, and confirmed crosswalk-compatible with consumer-side options.
- [ ] **Minimum scored-trait threshold decided** — A specific integer minimum has been defined, justified, and documented.
- [ ] **Percentage/range display decision made** — The decision to implement or not implement secondary percentage display has been made and documented, with format and minimum threshold defined if implementing.
- [ ] **Sorting/filtering policy confirmed** — The rules for consumer-initiated and platform-initiated sorting by compatibility signal have been defined and confirmed compliant.

### 10.4 AI and Moderation Gate

- [ ] **AI moderation approach decided** — The moderation workflow, tooling, SLA, and fallback behavior are defined and ready for implementation.
- [ ] **AI explanation caching strategy confirmed** — The caching and invalidation rules from Section 6.4 have been reviewed and the storage approach (column on `listing_compatibility_scores` vs. separate table) has been decided.
- [ ] **AI explanation disclosure language reviewed** — The required disclosures from Section 6.5 and Phase F Section 5.3 have been reviewed by compliance counsel and confirmed compliant.

### 10.5 Implementation Readiness Gate

- [ ] **Rollout gate criteria defined for each of the four gates** — The specific, measurable exit criteria for each rollout gate are documented beyond the high-level descriptions in Section 8.
- [ ] **Audit logging design approved** — The implementation design for audit record creation and storage has been reviewed.
- [ ] **`compatibility_framework_version` identifier assigned** — The specific version string for Phase H implementation has been assigned and documented, distinct from prior versions.
- [ ] **Phase G architecture document reviewed and accepted** — This document has been reviewed by the team and accepted as the authoritative architecture reference for Phase H.

---

## 11. Closing Statement and Summary

### Closing Statement

**Phase G is architecture-planning only. No production implementation should begin until Fair Housing review and proxy-risk analysis are completed.**

---

### Summary

#### What Was Added

This document — `docs/BIDYOURAGENT_COMPATIBILITY_IMPLEMENTATION_ARCHITECTURE.md` — is the complete Phase G deliverable. It synthesizes the six preceding planning phases (A through F) into a full implementation architecture covering:

1. The system's advisory-only nature, its complete isolation from Property DNA / Buyer-Tenant DNA matching, its separation from compensation-based decisioning, and its exclusive scope to the Hire Agent workflow.
2. The end-to-end data flow from consumer preference inputs through agent response inputs, normalized trait processing, per-trait comparison results, compatibility summary outputs, and AI explanation outputs — including the versioning and audit trail layer.
3. Planned schema additions to `listing_compatibility_scores` (six new columns) and the approach for agent response storage via the existing EAV meta pattern.
4. All future-affected Livewire components, what each will need, and the agent-side response field architecture per the Phase C seven-sub-section design.
5. The scoring pipeline: calculation triggers, recalculation events, missing-data handling, the minimum scored-trait threshold (deferred decision), informational-only trait rules, proxy-risk blocking for `risk_tolerance` and `property_strategy_fit`, and `compatibility_framework_version` locking.
6. The AI explanation pipeline: authorized inputs, prohibited inputs, authorized outputs, prohibited outputs, AI's downstream-only position, caching and storage rules, moderation requirements, and required disclosures.
7. All Fair Housing safeguards, proxy-risk blockers, anti-steering requirements, the prohibition on "best agent" language, the prohibition on automatic winner selection, human decision authority as the non-negotiable principle, and audit logging requirements.
8. A four-gate rollout strategy: internal/admin-only build, hidden beta, consumer-visible beta with advisory disclosures and no algorithmic sorting, and production release only after compliance review and Fair Housing legal sign-off.
9. All open items that must be resolved before Phase H: proxy-risk reviews for both flagged traits, Fair Housing legal review, minimum scored-trait threshold, percentage/range display decision, sorting/filtering policy, weighting calibration, agent response option set finalization, and AI moderation approach.
10. A complete Phase H readiness gate checklist across five categories: compliance and legal, schema and data, product and design, AI and moderation, and implementation readiness.

#### What Remains Blocked

Phase H implementation is blocked on two categories of required prior action:

**Fair Housing review:** No element of the compatibility engine may be deployed to any consumer-visible environment without a full Fair Housing legal review from qualified legal counsel. This applies to the scoring methodology, the AI explanation pipeline, the consumer-facing display design, and any sorting or filtering logic that uses compatibility signals.

**Proxy-risk analysis:** The traits `risk_tolerance` and `property_strategy_fit` are blocked from any scored use in the pipeline and any scored display in the UI until a qualified Fair Housing compliance professional completes a proxy-risk analysis for each trait, documents findings, and those findings are implemented. This blocking is unconditional across all four consumer roles and all rollout gates.

#### What Phase H Should Do Next

Once all Phase H readiness gate items (Section 10) are cleared, Phase H should:

1. **Implement the schema additions** — Add the planned columns to `listing_compatibility_scores`, create any needed supporting tables (AI explanation storage), and increment `scoring_framework_version`.
2. **Add agent-side compatibility response fields** to all four agent bid Livewire components (`SellerAgentAuctionBid`, `BuyerAgentAuctionBid`, `LandlordAgentAuctionBid`, `TenantAgentAuctionBid`), following the Phase C seven-sub-section architecture and the EAV `saveMeta`/`loadMeta` pattern.
3. **Build the normalization service** — Implement the Phase B normalization rules that translate raw consumer EAV sub-keys into the 12 normalized trait values, resolving all four naming inconsistencies.
4. **Build the comparison layer** — Implement the Phase D per-trait comparison logic, producing the five alignment category results for each scored trait.
5. **Build the Layer 4 explainability outputs** — Assemble the structured explanation payload from per-trait results, composite advisory signal, and qualitative label.
6. **Integrate AI explanation generation** — Connect to the AI explanation service per Phase E pipeline rules, with authorized inputs only and the required moderation workflow.
7. **Build the consumer-facing display components** — Implement the Phase F UI design: bid card compatibility summary, per-trait explanation panels, seven-trait side-by-side comparison view, and all required advisory disclosures.
8. **Execute rollout gates in sequence** — Internal/admin-only build first, then hidden beta, then consumer-visible beta, then production — with all gate exit criteria met before advancing.

No implementation step may begin before the Phase H readiness gate checklist is complete.

---

*Document prepared as Phase G deliverable. Read-only. No code, schema, migration, Livewire component change, Blade file, route, controller, job, scoring logic, or database write was produced in this phase.*
