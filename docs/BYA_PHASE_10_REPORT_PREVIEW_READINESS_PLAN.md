# BYA_PHASE_10_REPORT_PREVIEW_READINESS_PLAN

**Milestone 10 — BYA_REPORT_V1 Internal Admin Preview Readiness**

This document defines the governance rules for internal admin and legal preview of BYA_REPORT_V1 outputs. This is a planning-only document. No code, routes, UI, database changes, or AI integrations are introduced by this milestone.

---

## 1. Purpose of Internal Preview

The purpose of the internal preview phase is to allow authorized administrators and internal reviewers to inspect BYA_REPORT_V1 output structures — including their explanation templates, alignment categories, and audit traces — before any controlled beta or consumer-facing display is considered.

Internal preview exists to:

- Verify that the report generation pipeline produces structurally correct, auditable output.
- Enable legal and fair housing review of explanation templates and relationship metadata before public exposure.
- Confirm that no protected-class data, prohibited proxy characteristics, or ranking/recommendation language appears in report outputs.
- Establish a documented baseline that must be signed off before Milestone 11 (Internal Admin Preview UI) begins.

Internal preview is **not** a product feature. It is a governance checkpoint accessible only to authorized internal personnel.

---

## 2. Authorized Inputs

### Allowed

- **BYA_REPORT_V1 outputs only** — the structured report object as defined by the BYA_REPORT_V1 contract, including its top-level `report_version`, `alignment_version`, `explanation_version`, `narrative_version`, `dimensions`, `summary`, and `audit` fields.

### Prohibited

The following data types must never appear as inputs to, or be derivable from, the internal preview:

- Raw consumer profile data (buyer or tenant personally identifiable information, preferences beyond what is encoded in BYA_REPORT_V1 dimension keys)
- Raw agent profile data (individual agent identity, compensation figures, personal contact details)
- Raw compensation or fee data in unaggregated form
- Data sourced from external third-party APIs or data brokers
- Demographic data of any kind (race, color, national origin, religion, sex, familial status, disability, or any characteristic protected under the Fair Housing Act or applicable state/local law)
- Proxy characteristics that may correlate with protected classes (neighborhood names used as proxies, school district identifiers, etc.)
- AI-generated text, summaries, or interpretations of any report field

---

## 3. Allowed Internal Preview Outputs

During internal preview, reviewers may inspect the following fields from a BYA_REPORT_V1 output:

| Field | Description |
|---|---|
| `report_version` | Version identifier of the report schema (e.g., `BYA_REPORT_V1`) |
| `alignment_version` | Version identifier of the upstream alignment payload (e.g., `BYA_ALIGN_V1`) |
| `explanation_version` | Version identifier of the upstream explanation payload (e.g., `BYA_EXPLAIN_V1`) |
| `narrative_version` | Version identifier of the upstream narrative payload (e.g., `BYA_NARRATIVE_V1`) |
| `dimension names` | The 12 canonical behavioral dimension names included in the report (e.g., `communication_style`, `negotiation_style`, `decision_speed`) |
| `relationship` | The raw comparison result for a dimension pair — one of: `same`, `similar`, `different`, `unknown` |
| `alignment_category` | The derived alignment bucket for a dimension pair — one of: `full_alignment`, `partial_alignment`, `incompatible_alignment`, `insufficient_data` |
| `explanation_type` | The explanation narrative strategy applied — one of: `alignment`, `difference`, `adjacent`, `neutral`, `insufficient_data` |
| `explanation_key` | The key identifying the explanation template used |
| `template_id` | The unique identifier of the sentence template |
| `sentence` | The rendered explanation sentence from the template |
| `summary.alignment_category_counts` | Aggregate count of dimensions per alignment category |
| `summary.explanation_type_counts` | Aggregate count of dimensions per explanation type |
| `summary.narrative_type_counts` | Aggregate count of dimensions per narrative type |
| `summary_sentence` | The top-level summary sentence generated from a template |
| `audit.source_versions` | Map of the three upstream payload version identifiers (`alignment_version`, `explanation_version`, `narrative_version`) |
| `audit.trace_keys` | Per-dimension map of `alignment_category`, `explanation_key`, and `template_id` used to derive each explanation, for auditability |

No other fields, raw inputs, or derived computations beyond the above list are permitted in internal preview outputs.

---

## 4. Prohibited Preview Behavior

The internal preview must not, under any circumstances:

- Display rankings of agents, listings, buyers, tenants, or any other party
- Display match scores, compatibility scores, suitability scores, or any numerical ranking of parties
- Include language identifying any party as "best," "top," "most compatible," "recommended," or similar superlatives
- Include "hire this agent" calls to action, agent recommendation language, or disqualification language applied to any party
- Sort, order, or filter report outputs by compatibility, match quality, or any metric that implies relative ranking of parties
- Display any content on consumer-facing pages, agent-facing dashboards, or any route accessible to non-internal users

---

## 5. Access Control Requirements

- Access to internal preview outputs is restricted to **authorized administrators and internal reviewers only**, as defined by the platform's internal access control policy.
- No public-facing route may serve BYA_REPORT_V1 output during this milestone or until a separate access control governance milestone has been completed and approved.
- No consumer-facing route (any route accessible to buyers, tenants, sellers, or landlords without explicit internal-reviewer credentials) may serve BYA_REPORT_V1 output.
- No agent-facing route may serve BYA_REPORT_V1 output.
- Any internal preview UI (implemented in Milestone 11) must require explicit admin-level authentication and must be gated behind a server-side authorization check, not merely a front-end guard.
- Access logs must be maintained for all internal preview sessions, recording the reviewer's identity, the report identifiers accessed, and the timestamp of access.

---

## 6. Audit and Traceability Review

Each BYA_REPORT_V1 explanation must be traceable through the following chain:

```
sentence → template_id → explanation_key → alignment_category → relationship
```

Reviewers performing an audit trace must be able to verify:

1. **sentence**: The rendered output matches the template exactly, with no freeform or AI-generated text interpolated beyond allowed template variables.
2. **template_id**: The template identifier resolves to a known, approved template in the explanation template registry.
3. **explanation_key**: The key matches a defined entry in the explanation key dictionary and correctly maps to the stated `alignment_category`.
4. **alignment_category**: The category is one of the four defined values — `full_alignment`, `partial_alignment`, `incompatible_alignment`, or `insufficient_data` — and is consistent with the `relationship` field.
5. **relationship**: The relationship type is one of the four defined values — `same`, `similar`, `different`, or `unknown` — and is derived deterministically from dimension inputs without ranking or scoring logic.

Any break in this chain — a sentence that cannot be traced to an approved template, or an `explanation_key` that does not resolve — constitutes a traceability failure and must be flagged before the preview can be considered complete.

---

## 7. Fair Housing Review Requirements

Prior to any controlled beta or consumer-facing display, a fair housing review must be performed against all BYA_REPORT_V1 explanation templates and dimension definitions. The 12 canonical dimensions are behavioral and personality-based traits (e.g., `communication_style`, `decision_speed`, `negotiation_style`, `risk_tolerance`), not property-transaction or financial characteristics. The review must examine these dimensions — and all templates derived from them — for the following:

### Protected-Class References

- Confirm that no dimension name, explanation key, template, or rendered sentence references any characteristic protected under the Fair Housing Act (race, color, national origin, religion, sex, familial status, disability) or applicable state or local fair housing law.

### Proxy Characteristics

- Confirm that no dimension or template relies on data that serves as a proxy for a protected class, including but not limited to: neighborhood or ZIP code used as a demographic proxy, school district identifiers, crime statistics correlated with protected-class demographics, or any other characteristic identified as a proxy by HUD guidance or applicable case law.

### Steering, Recommendation, and Ranking Language

- Confirm that no explanation template contains language that steers a consumer toward or away from a particular agent, listing, or transaction based on any characteristic.
- Confirm that no template implies a recommendation, ranking, or suitability judgment about any party.
- Confirm that no template uses comparative language that could be construed as ranking parties against one another.

### Suitability Language

- Confirm that no template characterizes any party as "suitable," "ideal," "preferred," or any equivalent term that implies a judgment of fit between parties in a way that could constitute steering.

The fair housing review must be documented, signed off by authorized legal or compliance personnel, and retained as a record before Milestone 12 (Legal/Fair Housing Review Workflow) is marked complete.

---

## 8. UI Placement Rules for Future Preview

When an internal preview UI is implemented (Milestone 11), the following placement rules apply:

- Every page or panel displaying BYA_REPORT_V1 output must include a prominently visible **"Internal Review Only"** label, rendered in a visually distinct style (e.g., a banner or badge that cannot be missed).
- A warning banner must appear at the top of any preview page, stating clearly that the displayed content is not approved for consumer or agent use and is subject to ongoing legal and fair housing review.
- BYA_REPORT_V1 preview output must never appear in:
  - Consumer dashboards (buyer, tenant, seller, landlord views)
  - Agent dashboards or agent-facing listing views
  - Any page reachable without explicit admin-level authentication
- No share link, download link, or export mechanism for BYA_REPORT_V1 preview output may be provided for public or external use. Any internal export (e.g., for legal review) must be gated behind the same access control requirements defined in Section 5 and must be logged per the audit requirements defined in Section 6.

---

## 9. AI Boundary

During internal preview (Milestone 10) and until a separate AI governance milestone has been completed and approved:

- **No AI system may summarize, rewrite, enhance, paraphrase, interpret, or otherwise process BYA_REPORT_V1 output** during the preview phase.
- This prohibition applies to all AI modalities: large language models, embedding-based summarization, generative image models, and any other AI-driven transformation.
- All sentences displayed during internal preview must be rendered directly from approved deterministic templates, with no AI involvement in text generation or interpolation.
- Any future use of AI to generate, augment, or interpret BYA_REPORT_V1 output requires a dedicated AI governance milestone, including a separate fair housing review of AI-generated outputs, before implementation may begin.

---

## 10. Future Implementation Sequence

The following milestones define the planned sequence after this document is approved. Each milestone is contingent on the successful completion and sign-off of all preceding milestones.

| Milestone | Name | Description |
|---|---|---|
| **Milestone 11** | Internal Admin Preview UI | Implement a restricted admin-only UI that renders BYA_REPORT_V1 output according to the placement rules in Section 8. No consumer or agent access. |
| **Milestone 12** | Legal / Fair Housing Review Workflow | Implement a structured workflow enabling legal and compliance reviewers to formally approve or flag explanation templates and dimension definitions. Requires documented sign-off before Milestone 13. |
| **Milestone 13** | Hidden Beta | Deploy BYA_REPORT_V1 output to a hidden, invitation-only internal beta environment. Access is controlled by explicit allow-list. No public URLs. |
| **Milestone 14** | Consumer Beta | After legal/fair housing sign-off and successful hidden beta, release BYA_REPORT_V1 to a limited consumer beta. Requires additional governance review of consumer-facing display rules, consent language, and data use disclosures. |
| **Milestone 15** | GA Controls | Define and implement general availability controls: feature flags, rollout percentage controls, monitoring and alerting for report output anomalies, and a rollback plan. No GA release may occur without all prior milestones signed off. |

Each milestone transition requires explicit written approval from authorized governance personnel before implementation begins. No milestone may be skipped or combined without a documented exception approved by the same governance authority.

---

*Document created: Milestone 10 — BYA_REPORT_V1 Internal Admin Preview Readiness*
*Status: Planning only. No code, routes, UI, database changes, or AI integrations are introduced by this document.*
