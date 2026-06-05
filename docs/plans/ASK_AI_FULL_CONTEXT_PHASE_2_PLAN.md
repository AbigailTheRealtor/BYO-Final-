# Ask AI Full Context — Phase 2 Master Plan

**Date:** 2026-06-05  
**Status:** Authoritative reference for Phase 2 planning and execution  
**Audit Sources:**
- [`docs/audits/ASK_AI_NATURAL_LANGUAGE_ROUTING_AUDIT.md`](../audits/ASK_AI_NATURAL_LANGUAGE_ROUTING_AUDIT.md)
- [`docs/audits/ASK_AI_FULL_CONTEXT_MAP.md`](../audits/ASK_AI_FULL_CONTEXT_MAP.md)

**Channel Contract Spec (produced by Task E):**
- [`docs/specs/ASK_AI_CHANNEL_AGNOSTIC_CONTRACT.md`](../specs/ASK_AI_CHANNEL_AGNOSTIC_CONTRACT.md) *(planned dependency — Task E is responsible for creating this file; it may not yet exist if Task E has not been completed)*

---

## Section 1 — Phase 1 vs. Phase 2 Scope

### 1.1 What Phase 1 Shipped

Phase 1 addressed the three root-cause failures identified in the routing audit — classifier gap, context starvation, and FAQ disconnection — with a working end-to-end `listing_facts` path. Specifically, Phase 1 delivered:

| Area | What Was Done |
|---|---|
| **Classifier** | Added `listing_facts` question type to `AskAiQuestionClassifierService::KEYWORD_RULES`. Covers 15 factual topic clusters (bedrooms, bathrooms, rent, price, HOA, pets, pool, parking, appliances, utilities, roof, showing instructions, utility costs, lease length, move-in ready). Removed ambiguous overlap keywords (`bedrooms`, `bathrooms`, `lease length`, `parking preference`) from `buyer_tenant_match` bucket. |
| **Context builder — core fields** | Extended `extractListingFields()` with 14 core factual fields: `bedrooms`, `bathrooms`, `asking_price`, `rent_amount`, `lease_length`, `pets_allowed`, `hoa_fee`, `pool`, `parking_spaces`, `appliances_included`, `utilities_included`, `showing_instructions`, `square_feet`, `year_built`. |
| **FAQ wiring (basic)** | Added `buildFaqAnswers()` to `AskAiContextBuilderService`. Reads `listing_ai_faq` JSON from EAV meta (seller, landlord, buyer) or native column (tenant). Returns raw answer strings keyed by question key as `context['faq_answers']`. |
| **Contract service** | Added `listing_facts` entry to `TYPE_CONTRACTS` in `AskAiResponseContractService`. Defines `required_sources`, `allowed_context` paths (all 14 Phase 1 fields + `faq_answers.*`), and disclosure templates for flood-zone fields. |
| **Suggested questions (static)** | Added `listing_facts` chip pool to `AskAiSuggestedQuestionsService::POOLS` for each role with a static question set. Static means chips are selected without checking whether the underlying field is populated. |

**Phase 1 readiness after delivery:** The basic `listing_facts` path works end-to-end — a user asking "How many bedrooms?" now receives an answer pulled from `context['listing']['bedrooms']` when the field is populated. All 15 audited questions are correctly classified. The pipeline no longer silently returns `unsupported` for factual questions.

### 1.2 What Remains for Phase 2

Phase 1 closed the critical path. Phase 2 raises the quality ceiling: richer FAQ data, broader field coverage, smarter chip filtering, ambiguous question normalization, and multi-channel delivery.

| Phase 2 Task | What It Adds |
|---|---|
| **A — FAQ Enrichment** | Promotes raw `faq_answers` string values to structured objects with `question_label`, `question_group`, `intelligence_category`, and `config_key`. Adds `AskAiFaqEnrichmentService` + Artisan backfill command. |
| **B — Extended Fields** | Adds all remaining approved Public-Factual fields from the full context map audit — dozens of additional fields across all four roles. Phase 1 covered 14 core fields; Phase 2 closes the remaining gap. |
| **C — Intent Normalization** | Adds a feature-flagged OpenAI pre-classification step for ambiguous questions (e.g., "Does the A/C work well?") that the deterministic classifier cannot match. Returns a canonical field key; never generates a final answer. |
| **D — Suggested Questions (context-aware)** | Makes `listing_facts` chips data-aware: chips are only surfaced when the required field or FAQ key is actually populated in the listing data. Expands chip pools to cover Task B fields. |
| **E — Channel-Agnostic API** | Produces the channel contract specification (`docs/specs/ASK_AI_CHANNEL_AGNOSTIC_CONTRACT.md`) and implements two routes (`POST /ask-ai/ask` and `POST /api/ask-ai/ask`) backed by a shared `AskAiApiController`. Enables non-web channels (SMS, Messenger, mobile, CRM) to invoke the same governed pipeline. |

---

## Section 2 — Task A–E Overview Table

| Task | Title | Key Files / Deliverables | Risk Level | Depends On | Rollout Phase |
|---|---|---|---|---|---|
| **A** | FAQ Answer Enrichment | `AskAiFaqEnrichmentService.php`, `AskAiContextBuilderService.php`, Artisan command `ask-ai:sync-faq-answers` | Low | Phase 1 (`buildFaqAnswers()` must exist) | Phase 2-1 (ship with B) |
| **B** | Extended Factual Field Coverage | `AskAiContextBuilderService.php`, `AskAiResponseContractService.php` | Low | Phase 1 (`extractListingFields()` must exist) | Phase 2-1 (ship with A) |
| **C** | OpenAI Intent Normalization | `AskAiIntentNormalizerService.php`, `AskAiRunnerV2Service.php`, `config/ask_ai.php` | Medium | A + B (normalizer needs enriched context paths and FAQ keys; D is not a hard dependency) | Phase 2-3 (ships after D in rollout sequence; flag ships off; enable only after governance sign-off) |
| **D** | Context-Aware Suggested Questions | `AskAiSuggestedQuestionsService.php` | Low | B (needs extended field paths for chip mapping) | Phase 2-2 (after A+B) |
| **E** | Channel-Agnostic API | `AskAiApiController.php`, `routes/web.php`, `routes/api.php`, `docs/specs/ASK_AI_CHANNEL_AGNOSTIC_CONTRACT.md` | Low | **Hard:** A + B (routes + runner are all that is needed to function) — **Recommended before launch:** C + D (normalization and context-aware chips are not blockers but raise answer quality) | Phase 2-4 (after C) |
| **F** | QA Plan & Master Plan Document | `docs/plans/ASK_AI_FULL_CONTEXT_PHASE_2_PLAN.md`, `docs/plans/ASK_AI_PHASE_2_QA_PLAN.md` | None | — | Anytime |

---

## Section 3 — Dependency Graph

```
Phase 1 (baseline — already shipped)
  ├── listing_facts classifier
  ├── 14 core factual fields in extractListingFields()
  ├── buildFaqAnswers() (basic raw-string shape)
  ├── listing_facts contract in TYPE_CONTRACTS
  └── static listing_facts suggested question chips
             │
             ▼
┌─────────────────────────────────────────────────────────────────┐
│  Phase 2-1: Ship A + B together (both independent, both low-risk)│
│                                                                   │
│  Task A — FAQ Enrichment                Task B — Extended Fields  │
│  • AskAiFaqEnrichmentService            • Seller: +21 fields      │
│  • Artisan backfill command             • Buyer: +8 fields        │
│  • Enriched faq_answers shape           • Landlord: +16 EAV keys  │
│  • Backward-compatible                  • Tenant: +9 EAV keys     │
└───────────────────────┬─────────────────────────────────────────┘
                        │ A+B complete
                        ▼
┌─────────────────────────────────────────────────────────────────┐
│  Phase 2-2: Task D — Context-Aware Suggested Questions           │
│  • forListing() accepts optional context array                   │
│  • listing_facts chips filtered by field presence                │
│  • Expanded chip pools for B's new fields                        │
│  • required_context_path on every chip                           │
└───────────────────────┬─────────────────────────────────────────┘
                        │ D complete
                        ▼
┌─────────────────────────────────────────────────────────────────┐
│  Phase 2-3: Task C — OpenAI Intent Normalization (FLAGGED OFF)   │
│  • AskAiIntentNormalizerService                                  │
│  • config('ask_ai.enable_openai_intent_normalization') = false   │
│  • Governance review REQUIRED before enabling in production      │
│  • Fair housing check on all normalization prompts               │
└───────────────────────┬─────────────────────────────────────────┘
                        │ C implemented (flag stays off until governance sign-off)
                        ▼
┌─────────────────────────────────────────────────────────────────┐
│  Phase 2-4: Task E — Channel-Agnostic API                        │
│  • docs/specs/ASK_AI_CHANNEL_AGNOSTIC_CONTRACT.md               │
│  • AskAiApiController with ask() method                          │
│  • POST /ask-ai/ask (web) + POST /api/ask-ai/ask (sanctum)       │
│  • Rate limiting: 20 req/min (configurable)                      │
└─────────────────────────────────────────────────────────────────┘

  Task F — QA Plan & Master Plan Document (this document)
  ← No dependencies; can be written and committed at any time →
```

### 3.1 Recommended Shipping Order

| Rollout Phase | Tasks | Risk | Prerequisite | Notes |
|---|---|---|---|---|
| **Phase 2-1** | A + B (together) | Low | Phase 1 baseline | Both are independent of each other; shipping together maximizes value |
| **Phase 2-2** | D | Low | A + B complete | Data-aware chips require the extended context paths from B |
| **Phase 2-3** | C | Medium | A + B complete; governance review (D shipped first for rollout sequencing, not as a hard code dependency) | Flag ships as `false`; must not be enabled in production without governance sign-off |
| **Phase 2-4** | E | Low | Hard: A + B complete. Recommended: C + D also complete before launch (not code-blocking, but raise answer quality meaningfully) | Channel API can function on A + B alone; C + D are not blockers |
| **Anytime** | F | None | — | Documentation; no code changes |

---

## Section 4 — Fair Housing Governance Notes

### 4.1 Layer 1 Compliance Blocker — Must Not Be Modified

The `prohibited` check in `AskAiQuestionClassifierService::KEYWORD_RULES` is the platform's primary fair housing compliance gate. It runs before every other classification layer.

**Rule:** No Phase 2 task may modify, reorder, disable, or bypass the `prohibited` keyword bucket or its classification behavior. All new features (Task C normalizer, Task E API routes) must confirm that the Layer 1 compliance blocker fires before any new logic is invoked.

### 4.2 Task C — Governance Review Required Before Enabling

Task C introduces the `AskAiIntentNormalizerService`, which calls OpenAI as part of the classification pipeline — a first for the Ask AI system. This creates a new attack surface where user-crafted questions could potentially influence the classification result.

**Requirements before enabling `config('ask_ai.enable_openai_intent_normalization')` in production:**

1. **Prompt audit:** The normalizer prompt must be reviewed by a person responsible for fair housing compliance. The prompt must:
   - Explicitly prohibit OpenAI from returning any protected-class field key
   - Explicitly prohibit OpenAI from generating a final answer (key or `unknown` only)
   - List only pre-approved field keys from the `listing_facts` allowed context

2. **Adversarial test coverage:** At least 10 adversarial inputs involving protected-class language (race, national origin, religion, familial status, disability, sex) must be confirmed to either (a) be blocked by Layer 1 before reaching the normalizer, or (b) cause the normalizer to return `null` rather than a classification.

3. **Logged audit trail:** The `normalized_field_key` returned by the normalizer must be logged (not stored in DB; application log only) so production behavior can be audited before the flag is enabled broadly.

4. **Feature flag comment in config:** The `ask_ai.enable_openai_intent_normalization` config key must have an inline code comment stating governance review is required.

### 4.3 Suggested Question Chips — Protected-Class Exclusions

Task D expands the suggested question chip pools. The following strings must never appear in any chip, label, or question text in `AskAiSuggestedQuestionsService::POOLS`:

- Any reference to race, color, national origin, religion, sex, familial status, or disability
- Any reference to school district quality ratings (school district as a buyer-stated criterion is allowed; school quality judgments are not)
- Any reference to neighborhood demographics

Chip additions in Task D must be reviewed for compliance before merging.

---

## Section 5 — Source Documents

| Document | Purpose | Location |
|---|---|---|
| Natural Language Routing Audit | Root-cause analysis; 15-question failure trace; classifier keyword map; three-layer architecture recommendation | [`docs/audits/ASK_AI_NATURAL_LANGUAGE_ROUTING_AUDIT.md`](../audits/ASK_AI_NATURAL_LANGUAGE_ROUTING_AUDIT.md) |
| Full Context Map | Field-by-field coverage table for all four roles; classification legend; PII exclusion list; FAQ config key enumeration | [`docs/audits/ASK_AI_FULL_CONTEXT_MAP.md`](../audits/ASK_AI_FULL_CONTEXT_MAP.md) |
| Channel Contract Spec | Canonical input/output contract; status mapping table; channel adapter responsibilities; versioning policy | [`docs/specs/ASK_AI_CHANNEL_AGNOSTIC_CONTRACT.md`](../specs/ASK_AI_CHANNEL_AGNOSTIC_CONTRACT.md) *(produced by Task E)* |
| Phase 2 QA Plan | Acceptance test scenarios for Tasks A–E; regression suite (15 audited questions) | [`docs/plans/ASK_AI_PHASE_2_QA_PLAN.md`](ASK_AI_PHASE_2_QA_PLAN.md) |

### 5.1 Key Sections in the Routing Audit

| Audit Section | Content |
|---|---|
| Section 1.1 | The 15 audited questions — exact classification failures (regression baseline) |
| Section 2 | Context gap — 10 current fields vs. all missing factual fields |
| Section 3 | `buyer_tenant_match` keyword-trap failure mode |
| Section 4 | AI FAQ knowledge base disconnection |
| Section 6 | Three-layer architecture (Option C) — design rationale |
| Section 9.4 | Channel-agnostic API contract design |
| Section 10 | Final deliverable — readiness score, root cause, task list |
| Appendix A | Pipeline stage map |
| Appendix B | FAQ infrastructure map |

---

## Section 6 — Architecture Decisions and Constraints

### 6.1 Three-Layer Pipeline — Fixed Order

```
Layer 1:  prohibited check (existing, unchanged — always runs first)
Layer 2:  listing_facts deterministic keyword router (Phase 1)
Layer 3:  OpenAI intent normalizer (Task C — feature-flagged off)
```

No Phase 2 task may alter this ordering.

### 6.2 Answer Grounding — No Hallucination

For `listing_facts` responses, OpenAI is never permitted to invent data. Every answer must be grounded in:

1. Native listing fields from `extractListingFields()` (Phase 1 + Task B)
2. EAV meta fields via `$listing->info($key)` (Phase 1 + Task B)
3. FAQ answers from `buildFaqAnswers()` (Phase 1 + Task A enrichment)

If no value exists for a queried field, the response must state "this information is not available in the listing data" — never a hallucinated estimate.

### 6.3 Backward Compatibility — Enriched faq_answers Shape

Task A changes `buildFaqAnswers()` output from raw strings to enriched objects. Both shapes must be accepted by the contract service and prompt builder until all data is fully backfilled:

```
Legacy:   "roof_age_and_condition": "Roof replaced 2019"
Enriched: "roof_age_and_condition": { "answer_text": "...", "question_label": "...", ... }
```

All downstream consumers must handle both shapes without error.

### 6.4 Schema — No New Migrations in Task A

The `ai_faq_answers` table already has native `question_group` and `intelligence_category` columns. `question_label` and `config_key` are not native columns — they are stored in the existing `answer_normalized` JSON column or derived at read time. Task A must not add database migrations.

### 6.5 Encapsulation — TYPE_CONTRACTS Must Stay Private

`TYPE_CONTRACTS` in `AskAiResponseContractService` is `private const`. Task C must not make it public to read `listing_facts` allowed paths. Instead, Task C requires the addition of a focused public method (e.g., `getListingFactsAllowedPaths(): array`) that exposes only what the normalizer needs.

### 6.6 Route Safety — Existing Route Unchanged

The existing `POST /ask-ai/listing-question` route must not be removed or modified by Task E. The new `POST /ask-ai/ask` web route coexists alongside it.

---

## Section 7 — Rollout Readiness Criteria

Before any Phase 2 task is considered production-ready:

1. `php artisan test --filter AskAi` passes clean with no skipped tests (except the two known skips for the absent `tenant_criteria_auctions` table)
2. The 15 audited regression questions (Section 1.1 of the routing audit) produce `listing_facts` classifications — confirmed via unit test
3. All new fields added by Task B appear in `context['listing']` for a seeded test listing
4. No PII fields (`user_id`, `seller_id`, phone, email, `ai_share_token`) appear in any context block passed to the prompt builder
5. Flood-zone fields appear with `listing.disclosure_flags.flood_zone = true` — not embedded in the flat field value

Task C additionally requires:
6. Governance review completed and documented
7. `config('ask_ai.enable_openai_intent_normalization')` verified as `false` in production config before merge

---

## Section 8 — Open Questions and Deferred Items

| Item | Status | Notes |
|---|---|---|
| Twilio SMS adapter | Out of Phase 2 scope | Task E produces only the contract spec and web + API routes; SMS implementation is a separate task |
| Facebook Messenger adapter | Out of Phase 2 scope | Same as above |
| WhatsApp adapter | Out of Phase 2 scope | Same as above |
| Performance / load testing | Out of Phase 2 scope | Not applicable until multi-channel is live |
| Automatic FAQ enrichment on listing save | Out of Task A scope | Event hook to trigger `AskAiFaqEnrichmentService` on listing save is a separate follow-on task |
| `AskAiKnowledgeSourceRegistry` | Not yet implemented | Referenced in Section 9.5 of the routing audit as a future governance registry; not required for Phase 2 |
| `showing_calendar`, `comparable_sales`, `price_history` context sources | Future (post-Phase 2) | Documented in routing audit Section 9.5; each requires its own task and governance review |
