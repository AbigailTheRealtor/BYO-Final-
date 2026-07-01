# Ask AI — Master Question Catalog

**Purpose:** A complete inventory of every AI Knowledge Base ("Ask AI") question defined in the
repository, for every user type and every property type. This is the master reference for
reviewing AI coverage, identifying gaps, and understanding exactly what Ask AI knows today.

**Scope note:** This document catalogs the **config-driven Listing AI Knowledge Base** — the
question set that a listing creator fills in and that Ask AI answers at runtime. It is derived
**exclusively from the current implementation** in the repository (the four config files, the
render blade, the runtime services, and their tests). Where a fact could not be verified in
code, it is explicitly marked **"Not Implemented"** or **"Insufficient Context / Documentation
only."** Nothing here is assumed.

**Primary sources examined**
- `config/ai_faq_seller.php`, `config/ai_faq_buyer.php`, `config/ai_faq_landlord.php`, `config/tenant_ai_faq.php`
- `config/ask_ai.php` (feature flags, rate limit)
- `resources/views/livewire/offer-listing/shared/ai-questions-input.blade.php` and `partials/ai-question-field.blade.php`
- `app/Services/AskAi/*` (registry, runner, contract, guardrail, viewer-authorization, faq-config, enrichment)
- `app/Http/Controllers/AskAi/AskAiApiController.php`, `AskAiListingQuestionController.php`, `AiKnowledgeController.php`
- `routes/web.php`, `routes/api.php`
- `docs/ask-ai-kb-replacement-spec.md` (the authoritative design roadmap, "Parts A–J")
- `tests/Feature/AskAi/*`, `tests/Unit/Services/AskAi/*`

---

## 0. v1.0 Final — Applied Change Log

> **Status: APPLIED.** The changes below were implemented in the four `ai_faq_*` configs, the
> shared render blade, and `AskAiKnowledgeBaseRenderTest`. This log is the authoritative record of
> the v1.0 delta; the baseline tables in §4 predate it. **Net: 1 deletion · 3 rewrites · 25
> additions · 1 property-type gating change.** No question IDs were renamed; no routing, scoring,
> normalization, or matching behavior changed. All additions are `category_type: common`,
> `source: KB` unless noted.

### 0.1 Deletion (1 logical, 2 files)
- **`school_district_assignment`** removed from **Seller Residential** (`Location & Neighborhood`)
  and **Landlord Residential** (`Location & Neighborhood`). Location DNA (Census TIGER) resolves the
  assigned district; the KB prompt duplicated structured data.

### 0.2 Rewrites (3) — convert Location-DNA-output duplicators into human-insight prompts
| Key | Group | New label | Source |
|---|---|---|---|
| `nearby_amenities_description` | Seller universal (insight) | "Beyond what a map shows, what do you personally love about this location — the neighbors, the street, the daily routine here?" | `LocDNA` → `KB+LocDNA` |
| `nearby_amenities` | Landlord universal (insight) | "What kind of renter tends to love this location, and what do current/past tenants say they enjoy about the area?" | `LocDNA` → `KB+LocDNA` |
| `commute_options_access` | Seller Residential | "Are there commute or access details a buyer wouldn't discover on a map — a favorite route, a quick highway on-ramp, walkable errands?" | `KB+LocDNA` (unchanged) |

### 0.3 Additions (23)
**Seller (14)** — Residential adds a new `Selling Story & Appeal` subsection:
| Key | Group | Label |
|---|---|---|
| `seller_emotional_hook` | Res › Selling Story & Appeal *(new)* | What first made you fall in love with this home, or what will you miss most about living here? |
| `seller_guest_compliments` | Res › Selling Story & Appeal *(new)* | What do guests or visitors compliment most about this property? |
| `seller_best_showing_moment` | Res › Selling Story & Appeal *(new)* | Is there a particular time of day, season, or moment when the property shows at its best? |
| `seller_ideal_use_fit` | Res › Selling Story & Appeal *(new)* | What type of lifestyle or everyday living do you think this property is especially well suited for? |
| `seller_buyer_feedback` | Res › Selling Story & Appeal *(new)* | If the property has been shown before, what feedback, compliments, or hesitations have buyers or agents shared? |
| `income_ideal_operator_fit` | Income › Operations & Financials | What type of owner or operator would get the most out of this property? |
| `income_value_add_vision` | Income › Operations & Financials | If you had more time or capital, what's the one improvement you'd make to increase income here? |
| `commercial_redevelopment_potential` | Commercial › Building & Use | Is there redevelopment, expansion, or change-of-use potential a buyer should know about? |
| `commercial_visibility_signage` | Commercial › Building & Use | What makes this property's visibility, frontage, signage, or access especially valuable for a business? |
| `commercial_ceiling_height` | Commercial › Building & Use | Beyond the listed ceiling height, what is the clear height to the lowest obstruction, and are there any height limitations a buyer should know about? *(source `KB+Field` — complements the structured `ceiling_height` field; does not re-ask the base measurement)* |
| `business_growth_opportunities` | Business › Business Details | What growth opportunities exist that the current owner hasn't pursued? |
| `business_customer_draw` | Business › Business Details | What keeps customers coming back, and what do they value most about this business? |
| `land_utilities_available` | Land › Site & Access | Which utilities are already available at the site (water, sewer/septic, power, gas, internet), and how close are they? |
| `land_entitlement_status` | Land › Site & Access | Are any entitlements, plats, permits, or development approvals in place or underway? |

**Buyer (2)** — universal, appears in all five property types:
| Key | Group | Label |
|---|---|---|
| `buyer_deal_breakers` | Universal › Buyer Background | Beyond the must-haves already captured in the criteria, what would make the buyer decide a property isn't the right fit? |
| `buyer_compromise_areas` | Universal › Buyer Background | Where is the buyer most willing to compromise if the right opportunity comes along? |

**Landlord (5)** — Residential adds a new `Management & Fit` subsection:
| Key | Group | Label |
|---|---|---|
| `landlord_management_style` | Res › Management & Fit *(new)* | How hands-on is the landlord — self-managed or professionally managed — and what's their communication style with tenants? |
| `landlord_tenant_fit` | Res › Management & Fit *(new)* | What features or characteristics make this rental especially enjoyable for the right tenant? |
| `commercial_cam_structure` | Commercial › Commercial Lease & Space | How are CAM / operating expenses handled (NNN, gross, modified), and what's included? |
| `commercial_target_industries` | Commercial › Commercial Lease & Space | What types of businesses or uses is this space best suited for, and are any uses restricted? |
| `commercial_parking_ratio` | Commercial › Commercial Lease & Space | How many parking spaces are available, or what is the parking ratio for the property? *(no structured commercial parking-count/ratio field exists; residential `garage_parking_spaces` is unrelated)* |

**Tenant (4)** — Residential adds a new `Lifestyle & Fit` subsection:
| Key | Group | Label |
|---|---|---|
| `tenant_work_habits` | Res › Lifestyle & Fit *(new)* | Does the applicant work remotely, on-site, hybrid, or have other space needs that should be considered? |
| `tenant_commute_priorities` | Res › Lifestyle & Fit *(new)* | What locations does the applicant need to be near (work, school, family), and how important is commute? |
| `tenant_deal_breakers` | Res › Lifestyle & Fit *(new)* | Beyond the criteria already captured, what are the applicant's absolute deal-breakers in a rental? |
| `tenant_buildout_needs` | Commercial › Commercial Applicant | What build-out, layout, or improvements would the space need for the applicant's operation? |

The three new Tenant Residential questions are **non-sensitive** (not viewer-redacted); confirm with the privacy owner before GA if `tenant_work_habits` is later treated as employment-adjacent.

### 0.4 Property-type gating change (blade)
`ai-questions-input.blade.php` now resolves an **empty/unselected** `property_type` to `['universal']`
only (previously `['universal','residential']`), so no property-type interview is revealed before a
type is chosen; a selected-but-unrecognized value also falls back to universal-only. Answers already
entered persist in `$listing_ai_faq` across property-type changes (components never prune it), and
selecting a type re-renders reactively via the existing `updatedPropertyType` hook. Regression-guarded
by `test_unselected_property_type_renders_universal_only`.

### 0.5 Deferred to v1.1 (recorded, not implemented)
- **Enrichment questions:** `buyer_future_plans`, `biz_operator_intent`,
  `landlord_long_term_strategy`, `commercial_signage_rights` (landlord), `tenant_signage_needs`.
- **Dropped:** `tenant_lifestyle` (near-duplicate of the existing `tenant_rental_needs` insight).
- **Structured-field conditional display:** show only when the field is truthy —
  `pool_spa_equipment_condition` (pool/spa), `solar_panels_owned_leased` (solar), `faq_q8` pet
  deposit (applicant-has-pets). Requires a `show_if` schema + field data plumbed into the shared
  partial across 8 include sites; deferred as a feature rather than a config change.

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [System Architecture](#2-system-architecture)
   - 2.1 [Two-Axis Gating (role × property type)](#21-two-axis-gating-role--property-type)
   - 2.2 [Two Question Categories: Common vs AI Insights](#22-two-question-categories-common-vs-ai-insights)
   - 2.3 [Knowledge-Source Shorthand Legend](#23-knowledge-source-shorthand-legend)
   - 2.4 [Runtime Answer Pipeline](#24-runtime-answer-pipeline)
   - 2.5 [Answer Format & Insufficient-Context Behavior](#25-answer-format--insufficient-context-behavior)
   - 2.6 [Compliance Guardrails](#26-compliance-guardrails)
   - 2.7 [Tenant Privacy / Viewer Redaction](#27-tenant-privacy--viewer-redaction)
   - 2.8 [Storage of Creator Answers](#28-storage-of-creator-answers)
   - 2.9 [Runtime Knowledge-Source Registry](#29-runtime-knowledge-source-registry)
3. [Per-Question Attribute Conventions](#3-per-question-attribute-conventions)
4. [Catalog](#4-catalog)
   - 4.1 [Seller](#41-seller)
   - 4.2 [Buyer](#42-buyer)
   - 4.3 [Landlord](#43-landlord)
   - 4.4 [Tenant](#44-tenant)
5. [Summary Statistics](#5-summary-statistics)
6. [Coverage Analysis](#6-coverage-analysis)
7. [Knowledge Base Mapping](#7-knowledge-base-mapping)
8. [Appendix](#8-appendix)

---

## 1. Executive Summary

- **⚠️ v1.0 applied:** the counts and tables in §1–§8 describe the pre-v1.0 baseline. For the
  authoritative list of what changed in the production v1.0 KB (1 deletion, 3 rewrites, 25 additions,
  and the empty-property-type gating change), see **§0 (v1.0 Final — Applied Change Log)**.
- **182 knowledge-base question entries** are defined across the four user types (**177 distinct
  question keys** — 5 buyer keys are duplicated across the Income and Commercial groups; see §6).
- Questions are organized on a **two-axis model**: each knowledge base = a **Universal** group
  **plus the one group matching the listing's property type**. Residential questions never leak
  into Income/Commercial/Business/Land, and vice-versa. This gating is enforced in code and
  verified by tests.
- Every question carries a **category** — **Common Questions** (152 entries) or **AI Insights**
  (30 entries) — and a **source** shorthand describing where its answer is drawn from.
- **All four knowledge bases are implemented and render live.** The runtime answer pipeline
  (V1) is wired end-to-end (routes → controllers → `AskAiRunnerV2Service` → OpenAI adapter →
  compliance guardrail) and is **not** feature-flag gated.
- **Property-type applicability differs by role:** Seller and Buyer support all 5 property
  types; **Landlord and Tenant support only Residential and Commercial** (Income, Business, and
  Vacant Land are **Not Applicable** for those roles — no groups exist).
- **The `source` shorthand codes (`KB`, `Field`, `PropDNA`, etc.) are documentation-only
  metadata.** They are **not consumed at runtime** — no code maps them to actual data sources.
  Runtime source eligibility is governed by a separate `AskAiKnowledgeSourceRegistry` (see §2.9).
  This is the single most important "known limitation" in this catalog.
- **Tenant applicant questions carry privacy controls:** sensitive fields are redacted for
  non-owner / unauthorized viewers by `AskAiViewerAuthorizationService` (fail-closed default).
- **🛑 Verified critical defect:** Seller/Buyer property-type select values (`Income`,
  `Commercial`, `Business`) don't match their long-form `gating` keys, so those non-residential
  KB groups (~51 entries) **never render** in the create/edit UI — the form falls back to
  universal + residential. `Vacant Land`/`Residential` are fine; Landlord/Tenant are unaffected.
  See §2.1 and §6.0.

---

## 2. System Architecture

There are effectively **two layers** to Ask AI, and this catalog covers both:

| Layer | What it is | Where it lives |
|---|---|---|
| **A. Listing AI Knowledge Base (this catalog)** | The creator-facing question set, gated by role + property type. Creator fills in optional answers; Ask AI answers these questions for the counterparty audience. | `config/ai_faq_*.php`, `config/tenant_ai_faq.php`, the render blade |
| **B. Runtime knowledge-source registry** | The authoritative list of approved *data sources* the answer engine may draw from, keyed by *question type*. | `app/Services/AskAi/AskAiKnowledgeSourceRegistry.php` |

The config `source` shorthand (Layer A) *describes* which of Layer B's data areas a question was
*designed* to use, but the two are **not wired together** in code (see §2.3, §7).

### 2.1 Two-Axis Gating (role × property type)

Each config file has a `gating` map (`property_type` → ordered list of groups) and a `groups`
map. The render blade resolves `gating[$property_type]` and renders **only** those groups
(`ai-questions-input.blade.php:33-59`). Every knowledge base = **`universal` + one property-type
group**.

**Property-type applicability matrix** (from the `gating` arrays):

| User Type | Residential | Income / Multi-Family | Commercial | Commercial **Lease** | Business Opportunity | Vacant Land |
|---|:---:|:---:|:---:|:---:|:---:|:---:|
| **Seller** | ✅ | ✅ | ✅ (sale) | — (N/A) | ✅ | ✅ |
| **Buyer** | ✅ | ✅ | ✅ (sale) | — (N/A) | ✅ | ✅ |
| **Landlord** | ✅ | ❌ N/A | ✅ (lease) | ✅ *(this is the Commercial group)* | ❌ N/A | ❌ N/A |
| **Tenant** | ✅ | ❌ N/A | ✅ (lease) | ✅ *(this is the Commercial group)* | ❌ N/A | ❌ N/A |

Notes:
- For **Seller/Buyer**, "Commercial Property" is a **sale**. For **Landlord/Tenant**, the
  "Commercial Property" group **is** the commercial-lease knowledge base (there is no separate
  "Commercial Lease" property-type string — the lease context is inherent to the landlord/tenant
  role). The user's requested "Commercial Lease" category maps to the Landlord/Tenant
  **Commercial** group.
- **Income, Business Opportunity, and Vacant Land are Not Applicable for Landlord and Tenant** —
  those `gating` maps contain only `Residential Property` and `Commercial Property`.
- **Business Opportunity renders the `business` group only** (never commercial/income) for
  Seller and Buyer — resolving spec finding F6.
- **Fail-safe:** if a listing's `property_type` is unknown/unexpected, the blade falls back to
  `['universal','residential']` so the universal questions still render
  (`ai-questions-input.blade.php:35`).

> **🛑 VERIFIED CRITICAL DEFECT — Seller/Buyer gating keys do not match the UI select values.**
> The Seller and Buyer property-type `<select>` inputs store **short** values —
> `Residential`, `Income`, `Commercial`, `Business`, `Vacant Land`
> (`offer-seller-tabs/.../property-preferences.blade.php:620-625`,
> `offer-buyer-tabs/.../property-preferences.blade.php:318-322`) — but the Seller/Buyer `gating`
> maps are keyed by the **long** forms `Residential Property`, `Income Property`,
> `Commercial Property`, `Business Opportunity`, `Vacant Land`. The blade looks up
> `$gating[$property_type]` with no normalization (`ai-questions-input.blade.php:35`). Result:
> for Seller and Buyer listings, `Income` / `Commercial` / `Business` **fail the lookup and fall
> back to `universal + residential`** — so the intended **Income, Commercial, and Business KB
> groups never render** in the create/edit UI. `Vacant Land` matches exactly (renders correctly);
> `Residential` misses the key but the fallback lands on the same residential group (correct
> content by coincidence). **Landlord and Tenant selects use the long forms and match their
> gating keys, so their gating works as designed.** This means ~51 property-specific Seller/Buyer
> question entries (Seller Income 11 + Commercial 11 + Business 11; Buyer Income 6 + Commercial 6 +
> Business 6) are **defined but not currently reachable in the standard create/edit flow.** See
> §6.0. *(Verified against the select markup, the gating arrays, and the absence of any
> `property_type` normalization before the include.)*

### 2.2 Two Question Categories: Common vs AI Insights

Every entry has a `category_type` of `common` or `insight` (spec Part D). The blade renders them
as two separate sections with distinct headers:

- **Common Questions** — "Real questions buyers, tenants, and agents commonly ask." Answered from
  the creator's Knowledge Base free-text answer plus structured listing fields.
- **AI Insights** — "Educational prompts users often don't think to ask," drawing on
  platform-generated data (Property DNA, Location DNA, Match, Description). Insights **explain and
  educate only — never advice.**

**Both** categories render as **fillable input fields** for the creator (the same
`ai-question-field` partial is used for both loops); every field is **optional**. Insight fields
additionally draw on platform-generated data at answer time.

### 2.3 Knowledge-Source Shorthand Legend

Each entry's `source` is a shorthand string (or `+`-joined combo). Per the spec's Part F
conventions, the codes mean:

| Code | Meaning | Data origin | Sufficiency |
|---|---|---|---|
| **KB** | Knowledge Base | Creator's free-text answer stored in `listing_ai_faq` | **Conditional** — optional; AI states if not provided |
| **Field** | Structured Field | Native structured listing field (beds, zoning, NOI, HOA, etc.) | **Yes** (present when the listing has the field) |
| **PropDNA** | Property DNA | Platform-generated property intelligence | Yes *when the DNA pipeline has run* |
| **LocDNA** | Location DNA | Platform-generated location/POI/commute/school-district data | Yes *when the pipeline has run* |
| **Match** | Match Scoring | Platform-generated compatibility/match information | Yes *when computed* |
| **Desc** | Description | Property Description text (+ Highlights) | Yes *when present* |
| **BuyerDNA** | Buyer DNA | Platform-generated buyer-profile analysis (Buyer listings — no subject property) | Yes *when the pipeline has run* |
| **TenantDNA** | Tenant DNA | Platform-generated applicant-profile analysis (Tenant listings) | Yes *when the pipeline has run* |

Combos (e.g. `KB+Field`, `PropDNA+Desc`, `BuyerDNA+Match`) mean the answer is intended to draw
from multiple areas.

> **⚠️ Critical limitation — this legend is documentation-only.** A repository-wide search finds
> **zero** runtime references to these shorthand codes. The two config consumers
> (`AskAiFaqConfigService`, `AskAiFaqEnrichmentService`) read only `label`/`category` and **ignore
> `source`**. No mapping table (`'KB' => …`) exists anywhere in `app/`. The codes document
> *intent*; the runtime source system is the independent registry in §2.9. See §7 for the
> loose correspondence.

### 2.4 Runtime Answer Pipeline

The V1 pipeline is **live and functional** (not stubbed, not flag-gated). Three routes:

| Route | Controller | Middleware | Notes |
|---|---|---|---|
| `POST /ask-ai/listing-question` | `AskAiListingQuestionController@run` | `auth`, `throttle:ask-ai-api` | **Owner-only** (creator asking about own listing); scope hardcoded to `OWNER` |
| `POST /ask-ai/ask` | `AskAi\AskAiApiController@ask` | `throttle:ask-ai-api` | Public/guest allowed; viewer scope resolved per request |
| `POST /api/ask-ai/ask` | `AskAi\AskAiApiController@ask` | `auth:sanctum`, `throttle:ask-ai-api` | Sanctum API variant |

Execution chain: controller → `AskAiRunnerV2Service::run()` (live orchestrator) →
`AskAiOpenAiAdapterService::generate()` → `OpenAiClientService::send()` (real HTTP call using
`config('ai.*')` credentials) → `AskAiFinalResponseBuilderService::build()` (normalizes output,
extracts the `answer` key, runs the compliance guardrail). Rate limit: **20 requests/minute** per
user/IP (`config/ask_ai.php`, `RouteServiceProvider`).

**Note on service naming:** `AskAiInternalRunnerService` is only the Phase 1–3 sub-orchestrator
(context → contract → prompt package, no LLM). The runtime entry point that calls OpenAI is
`AskAiRunnerV2Service`.

**A separate "Agent AI Assistant V2"** exists behind feature flags (`AGENT_AI_ASSISTANT_V2` and
per-scope flags), **defaulting off** in all environments. When off, V2 routes 404 and V1 is
unaffected. Two other V1 flags default off: `enable_openai_intent_normalization` and
`enable_description_fallback` (both require governance review before production use).

### 2.5 Answer Format & Insufficient-Context Behavior

**`/ask-ai/ask` response** (contract `ASK_AI_API_V1`):
```
{ success, status, answer_text, question_type, follow_up_questions,
  disclosures, disclaimer, attribution, error, contract_version }
```
`status` ∈ `answered | insufficient_context | blocked | unsupported | failed`.

**`/ask-ai/listing-question` response:**
```
{ success, status, answer, refusal_message, disclosures, disclaimer,
  source_attribution, source, error, follow_up_questions }
```
`source` is a structured provenance object `{answer_source, snapshot_id, canonical_key,
match_type, snapshot_version}`; `answer_source` ∈ `openai | description_fallback |
description_fallback_miss | …`.

**A persistent educational disclaimer is always attached** to every response (the
`EDUCATIONAL_DISCLAIMER` constant — "…not legal, financial, tax…").

**Canned behaviors** (constants in `AskAiFinalResponseBuilderService` / `AskAiResponseContractService`):

| Situation | Behavior |
|---|---|
| **Insufficient context** (required data source missing) | *"The requested information is not available because one or more required data sources are missing for this listing…"* (`success=false`) |
| **Unsupported question type** | *"This question type is not supported. Please select an approved question category…"* |
| **Blocked / prohibited topic (refusal)** | `answer=null`; refusal template *"This question type is not permitted on this platform. No response can be generated."* |
| **Compliance withheld** (guardrail strips all content) | *"Based on the information provided, a compliant answer to that question is not available. Ask AI can share objective details disclosed in the listing, but it cannot provide advice or characterize people, neighborhoods, or outcomes."* |
| **Missing field value** (KB/field blank) | *"This information was not provided in the listing."* (variant per source) |
| **OpenAI error/timeout** | *"Ask AI could not generate a response right now. Please try again later."* |
| **Not owner** (listing-question route) | HTTP 403, *"You can only ask questions about your own listing."* |
| **Rate limited** | HTTP 429 with `Retry-After` |

A **Truth-Source Contract** coerces any non-contract status (and any `ready` answer that fails a
degradation check) down to `insufficient_context`, so callers only ever see the four contract
forms: `direct_fact | synthesis | insufficient_context | refusal`.

### 2.6 Compliance Guardrails

`AskAiComplianceGuardrailService::sanitizeAnswer()` is a deterministic, sentence-level output
sanitizer applied to every `ready` answer. It **drops any sentence** matching a hard-prohibited
category and **neutralizes unquoted superlatives**:

- **Hard-drop categories:** `protected_class` (Fair Housing), `demographic` (people-based
  steering — "perfect for families"), `steering` ("safe/desirable neighborhood", "best schools"),
  `advice` (offer/negotiation/accept-reject recommendations **and** leverage/negotiating-position
  analysis), `legal_conclusion`, `financial_advice` (investment/return/ROI recommendations).
- **Superlative neutralization (C-I):** tokens like `best, safest, perfect, guaranteed, ideal,
  finest, premier, world-class, top-rated, one-of-a-kind` are removed **unless inside double
  quotes** (direct quotes of disclosed listing text are preserved).
- If **all** content is stripped → `withheld=true` and the withheld-fallback message is returned.

Verified by `AskAiComplianceGuardrailServiceTest`.

### 2.7 Tenant Privacy / Viewer Redaction

Only **Tenant** listings carry applicant data. `AskAiViewerAuthorizationService` resolves a
**scope** and redacts the context **at the data layer before the model sees it** (fail-closed to
`public` when scope is absent):

- **`owner`** — no redaction.
- **`authorized`** — an accepted/active in-platform relationship (verified via
  `accepted_bid_summaries` for tenant listings). Keeps the authorized subset; still drops
  never-expose fields.
- **`public` / guest** — redacts **all** applicant-sensitive fields.

| Redaction set | Redacted for | Keys |
|---|---|---|
| **Never-expose** (FCRA-adjacent) | `authorized` **and** `public` (everyone except owner) | `credit_score(_range/_history/_report)`, `eviction(_history)`, `criminal(_history/_record)`, `felony`, `misdemeanor`, `background_check/report`, `bankruptcy` |
| **Applicant-sensitive native** | `public` only | `monthly_income`, `household_income`, `gross_monthly_income`, `annual_income`, `income_requirement`, `income_multiplier`, `employment_status`, `employer`, `employment_type`, `income_source` |
| **Applicant-sensitive FAQ answers** | `public` only | `faq_q12`, `faq_q15`, `faq_q17`, `faq_q18`, `faq_q20`, `tenant_prior_conduct`, `tenant_cosigner`, `tenant_application_readiness` |

The AI **never** frames any answer as an approve/deny recommendation and never references
protected-class characteristics. Verified by `AskAiViewerAuthorizationServiceTest`.
(Note: the same model is flagged in the spec as future work for any sensitive **Buyer** fields.)

### 2.8 Storage of Creator Answers

Creator answers are collected by the four `OfferListing` Livewire components (and their `Edit`
variants) into a `$listing_ai_faq` array, persisted as a **`listing_ai_faq` JSON meta blob** on
the listing. The `ask-ai:sync-faq-answers` command (`AskAiFaqEnrichmentService::sync()`) syncs
non-blank answers from that blob into the **`ai_faq_answers`** table (blank answers skipped). This
is the `KB` source. `AiKnowledgeController` also reads the config `label`s to build the knowledge
base.

### 2.9 Runtime Knowledge-Source Registry

`AskAiKnowledgeSourceRegistry` is the authoritative runtime list of approved data sources. Each
maps to the *question types* allowed to consume it:

| Source key | Label | Allowed question types |
|---|---|---|
| `listing` | Listing | property_standout, suited_audience, marketing_angles, missing_data |
| `property_intelligence` | Property Intelligence (Property DNA) | property_standout, suited_audience, marketing_angles |
| `location_intelligence` | Location Intelligence (Location DNA) | property_standout, suited_audience, marketing_angles, educational |
| `buyer_avatar` | Buyer Avatar (Buyer DNA) | buyer_tenant_match, suited_audience |
| `tenant_avatar` | Tenant Avatar (Tenant DNA) | buyer_tenant_match, suited_audience |
| `compatibility` | Compatibility (Match) | buyer_tenant_match, compatibility_signals |
| `offer_analysis` | Offer Analysis (Accepted Bid Summary) | missing_data |
| `governance_documents` | Governance Documents | educational |
| `agent_profile` | Agent Profile (public-safe) | agent_profile |
| `agent_presets` | Agent Service Presets | agent_profile |

---

## 3. Per-Question Attribute Conventions

The user's requested per-question attributes are **uniform by category and source** — they do not
vary arbitrarily per question. Rather than repeat identical text 182 times, the conventions below
apply to **every** question in the catalog tables; each table then records the attributes that
*do* vary per question (Key, Text, User Type, Property Type, Category, Source).

| Attribute | Convention |
|---|---|
| **Question ID / Key** | The config array key (e.g. `roof_age_and_condition`, `faq_q18`). Shown in every table. These keys are the durable identifiers; answers are bound to them. |
| **Exact question text** | The `label` field, verbatim. Shown in every table. |
| **User type** | The role owning the config file (section heading). |
| **Property type(s)** | Derived from which `gating` group the question sits in: `universal` questions apply to **all** property types for that role; a property-type group applies to **only** that type. Shown per table. |
| **Knowledge source(s)** | The `source` shorthand (see §2.3 legend). Shown in every table. **Documentation-only** — not enforced at runtime (§2.3). |
| **Required data fields** | **None are strictly required.** Every KB field is optional (blade: "all fields are optional"). For **Common/KB** questions, the answer comes from the creator's optional free-text plus structured Fields. For **Insight** questions, the answer comes from platform-generated DNA/Match/Desc **when those pipelines have run**. |
| **Optional data fields** | For `KB`/`KB+Field`: the creator's free-text answer (`listing_ai_faq[<key>]`). For Insights: the underlying DNA/Location-DNA/Match/Description artifacts (generated, not creator-entered). |
| **Conditions that determine appearance** | (1) The listing's **property type** must map (via `gating`) to the group containing the question. (2) The listing's **role** must match the config file. (3) `category_type` routes the question into the **Common Questions** vs **AI Insights** section. No other per-question show/hide conditions exist in the blade. |
| **Unsupported / insufficient-context behavior** | Governed centrally (§2.5). If the required data is absent, Ask AI returns the **insufficient-context** or **"not provided in the listing"** message rather than fabricating. Prohibited topics return a **refusal**; non-compliant content is **sanitized/withheld** (§2.6). |
| **Expected answer format** | A neutral, factual, educational free-text answer plus a persistent disclaimer, wrapped in the JSON contract of §2.5 (`answer_text`/`answer`, `status`, `disclaimer`, `attribution`/`source`, `follow_up_questions`). |
| **Currently implemented?** | **Yes** for all 182 entries — defined in config; the render blade and pipeline exist (blade verified by `AskAiKnowledgeBaseRenderTest`, which drives the blade with the **long-form** property-type keys directly). |
| **Fully functional?** | **Answer pipeline: Yes** (V1 live). **Authoring: caveat** — Seller/Buyer Income/Commercial/Business entries do not render in the real create/edit UI due to the §6.0 select-vs-gating mismatch (the test passes because it bypasses the select and feeds long-form keys). Also **partial** for `source` provenance (shorthand not runtime-wired) and for Insight questions whose DNA/Match pipeline may not yet have run (then: insufficient-context). |
| **Known limitations** | `source` shorthand not enforced (§2.3); Insight answers depend on generated pipelines; tenant sensitive fields redacted by scope (§2.7); OpenAI intent-normalization and description-fallback default **off**. |

Tenant tables additionally flag **🔒 Sensitive** on keys redacted for non-owner viewers (§2.7).

---

## 4. Catalog

Legend for tables: **Category** = Common | Insight. **Source** = shorthand per §2.3.
For each role, the **Universal** group renders for *every* applicable property type; each
property-type group renders *only* for that type.

### 4.1 Seller

**Property types supported:** Residential, Income, Commercial (sale), Business Opportunity, Vacant Land.
**Audience:** buyers / buyers' agents asking about the property.
**Rendered totals:** Residential 34 · Income 25 · Commercial 25 · Business 25 · Vacant Land 23
(each = 14 universal + the property-type group). **76 distinct entries.**

#### 4.1.0 Universal (renders for all 5 Seller property types)

| # | Question Key | Question Text | Category | Source(s) |
|---|---|---|---|---|
| 1 | `seller_motivation_for_selling` | Why is the owner selling the property? | Common | `KB` |
| 2 | `items_excluded_from_sale` | What is included in the sale, and is anything excluded? | Common | `KB+Field` |
| 3 | `furniture_negotiability` | Is any furniture or staging negotiable? | Common | `KB` |
| 4 | `closing_timeline_flexibility` | How flexible is the timing for closing or possession? | Common | `KB+Field` |
| 5 | `seller_leaseback_option` | Would the owner consider a short post-closing leaseback? | Common | `KB` |
| 6 | `known_defects_issues` | Are there any known issues or disclosures the owner has shared? | Common | `KB` |
| 7 | `planned_nearby_development` | Are there planned developments, road projects, or zoning changes nearby? | Common | `KB+LocDNA` |
| 8 | `as_is_condition` | Is the property being sold as-is, or is the owner open to repairs based on inspection? | Common | `KB` |
| 9 | `seller_concessions_offered` | Has the owner indicated openness to concessions or credits? | Common | `KB` |
| 10 | `unique_selling_points` | What features make this property stand out? | Insight | `PropDNA+Desc` |
| 11 | `nearby_amenities_description` | What location features are nearby? | Insight | `LocDNA` |
| 12 | `property_lifestyle_support` | What lifestyle does this property appear to support? | Insight | `PropDNA+LocDNA` |
| 13 | `disclosed_property_information` | What property information has been disclosed? | Insight | `Field+KB` |
| 14 | `property_features_buyer_appeal` | What property features may appeal to different buyers? | Insight | `PropDNA+Match` |

#### 4.1.1 Seller · Residential (universal + these 20)

| # | Question Key | Question Text | Category | Source(s) |
|---|---|---|---|---|
| 1 | `roof_age_and_condition` | How old is the roof, and what condition is it in? | Common | `KB` |
| 2 | `hvac_system_age` | How old is the HVAC system, and when was it last serviced? | Common | `KB` |
| 3 | `water_heater_age_type` | How old is the water heater, and what type is it? | Common | `KB` |
| 4 | `recent_renovations_list` | What renovations or upgrades have been made, and when? | Common | `KB` |
| 5 | `permits_for_renovations` | Were renovations completed with proper permits? | Common | `KB` |
| 6 | `foundation_type_and_issues` | Are there any known foundation or structural issues? | Common | `KB` |
| 7 | `pest_termite_history` | Any pest or termite history, and how was it resolved? | Common | `KB` |
| 8 | `flood_damage_history` | Has the property ever flooded or had water damage? | Common | `KB` |
| 9 | `mold_issues_history` | Any mold history, and how was it addressed? | Common | `KB` |
| 10 | `solar_panels_owned_leased` | Are solar panels present, and are they owned or leased? | Common | `KB+Field` |
| 11 | `smart_home_ev_features` | Are there smart-home or EV-charging features? | Common | `KB` |
| 12 | `average_utility_costs` | What are the average monthly utility costs? | Common | `KB` |
| 13 | `internet_utility_providers` | Which internet/utility providers serve the property, and what speeds are available? | Common | `KB` |
| 14 | `insurance_claims_history` | Has the owner disclosed any insurance claims history for the property? | Common | `KB` |
| 15 | `storage_space_available` | What storage options are available? | Common | `KB` |
| 16 | `natural_light_orientation` | How is the natural light, and which way does the home face? | Common | `KB` |
| 17 | `neighborhood_character` | What can you share about the area's setting and nearby amenities? | Common | `KB` |
| 18 | `traffic_or_noise_concerns` | Are there notable traffic or noise considerations nearby? | Common | `KB` |
| 19 | `commute_options_access` | What are typical commute options and travel times? | Common | `KB+LocDNA` |
| 20 | `school_district_assignment` | Which school district is this property assigned to? | Common | `LocDNA` |

#### 4.1.2 Seller · Income / Multi-Family (universal + these 11)

| # | Question Key | Question Text | Category | Source(s) |
|---|---|---|---|---|
| 1 | `annual_operating_expenses_detail` | What expenses are included in the operating costs? | Common | `KB` |
| 2 | `existing_tenant_lease_terms` | What are the current lease terms and escalations for existing tenants? | Common | `KB` |
| 3 | `value_add_opportunities` | What recent improvements or income changes has the owner disclosed? | Common | `KB` |
| 4 | `tenant_payment_history` | What has the owner disclosed about tenant payment history? | Common | `KB` |
| 5 | `deferred_maintenance_disclosed` | Is there any deferred maintenance or near-term capital work disclosed? | Common | `KB` |
| 6 | `professional_management` | Is the property professionally managed? | Common | `KB` |
| 7 | `income_utilities_split` | How are utilities split between owner and tenants? | Common | `KB+Field` |
| 8 | `income_building_systems_age` | How old are the roof and major building systems? | Common | `KB` |
| 9 | `income_property_standout` | What features make this property stand out to operators? | Insight | `PropDNA+Desc` |
| 10 | `income_operations_disclosed` | What has been disclosed about this property's operations? | Insight | `Field+KB` |
| 11 | `income_location_features` | What location features are nearby? | Insight | `LocDNA` |

#### 4.1.3 Seller · Commercial (sale) (universal + these 11)

| # | Question Key | Question Text | Category | Source(s) |
|---|---|---|---|---|
| 1 | `commercial_zoning_uses` | What is the zoning, and what uses does it permit? | Common | `Field+KB` |
| 2 | `commercial_building_systems` | What are the building systems (HVAC, electrical capacity)? | Common | `KB+Field` |
| 3 | `commercial_ceiling_height` | What is the clear/ceiling height? | Common | `Field` |
| 4 | `commercial_ada_accessibility` | Is the space ADA accessible? | Common | `KB` |
| 5 | `commercial_restroom_count` | How many restrooms are there? | Common | `KB` |
| 6 | `commercial_parking_loading` | What parking, access, and loading are available? | Common | `KB+Field` |
| 7 | `commercial_systems_age` | How old are the roof and major systems? | Common | `KB` |
| 8 | `commercial_recent_improvements` | What recent improvements have been made? | Common | `KB` |
| 9 | `commercial_space_standout` | What features make this space stand out? | Insight | `PropDNA+Desc` |
| 10 | `commercial_location_features` | What location features are nearby? | Insight | `LocDNA` |
| 11 | `commercial_zoning_permitted_summary` | What does the listing state about the zoning and permitted uses? | Insight | `Field+KB` |

#### 4.1.4 Seller · Business Opportunity (universal + these 11)

| # | Question Key | Question Text | Category | Source(s) |
|---|---|---|---|---|
| 1 | `business_reason_for_selling` | Why is the business being sold? | Common | `KB` |
| 2 | `seller_training_transition` | How much training/transition support will the seller provide? | Common | `KB` |
| 3 | `business_staff_retention` | Will existing staff stay on after the sale? | Common | `KB` |
| 4 | `business_customer_concentration` | How concentrated is the customer base? | Common | `KB` |
| 5 | `business_vendor_contracts` | What vendor or supplier contracts are in place? | Common | `KB` |
| 6 | `business_licenses_transferable` | Are licenses, permits, or franchise rights transferable? | Common | `KB` |
| 7 | `business_online_presence` | What is the business's online presence and review profile? | Common | `KB` |
| 8 | `business_seasonality` | Is the business seasonal? | Common | `KB` |
| 9 | `business_owner_involvement` | How involved is the current owner day-to-day? | Common | `KB` |
| 10 | `business_information_disclosed` | What information has been disclosed about this business? | Insight | `Field+KB` |
| 11 | `business_sale_includes` | What does the sale appear to include? | Insight | `Field` |

#### 4.1.5 Seller · Vacant Land (universal + these 9)

| # | Question Key | Question Text | Category | Source(s) |
|---|---|---|---|---|
| 1 | `land_soil_and_topography` | Are there known soil, perc, or topography considerations? | Common | `KB` |
| 2 | `land_survey_available` | Is a current survey available, and has the land been cleared/improved? | Common | `KB` |
| 3 | `land_zoning_permitted_uses` | What uses are permitted under current zoning? | Common | `Field+KB` |
| 4 | `land_development_restrictions` | Are there deed restrictions beyond recorded easements? | Common | `KB` |
| 5 | `land_access_and_road` | Are there access limitations or shared-road maintenance obligations? | Common | `KB` |
| 6 | `land_wetlands_environmental` | Are there wetlands or environmental designations on the parcel? | Common | `KB+LocDNA` |
| 7 | `land_prior_use` | What was the land's prior use? | Common | `KB` |
| 8 | `land_location_features` | What location features are nearby? | Insight | `LocDNA` |
| 9 | `land_site_characteristics_disclosed` | What objective site characteristics has the listing disclosed? | Insight | `Field+KB` |

---

### 4.2 Buyer

**Property types supported:** Residential, Income, Commercial (sale), Business Opportunity, Vacant Land.
**Audience:** sellers / listing agents asking about the buyer. **No subject property** — Insights
educate about the buyer's stated profile/criteria (Buyer DNA + criteria + Match). The AI performs
**no** negotiating-position, leverage, or offer-strategy analysis.
**Rendered totals:** Residential 20 · Income 15 · Commercial 15 · Business 15 · Vacant Land 17
(each = 9 universal + the property-type group). **46 entries / 41 distinct keys** (5 keys are
shared between Income and Commercial — see §6 duplicates).

#### 4.2.0 Universal (renders for all 5 Buyer property types)

| # | Question Key | Question Text | Category | Source(s) |
|---|---|---|---|---|
| 1 | `buyer_motivation` | What's driving the buyer's search right now? | Common | `KB` |
| 2 | `buyer_current_situation` | What's the buyer's current living/ownership situation? | Common | `KB` |
| 3 | `buyer_flexibility` | How flexible is the buyer on timing or terms if the right property comes along? | Common | `KB` |
| 4 | `buyer_biggest_concern` | What's the buyer's biggest concern or hesitation? | Common | `KB` |
| 5 | `buyer_relocation` | Is the buyer relocating or making decisions remotely? | Common | `KB` |
| 6 | `buyer_leaseback` | Would the buyer allow a short seller leaseback after closing? | Common | `KB` |
| 7 | `buyer_property_needs` | What property needs and uses has this buyer described? | Insight | `BuyerDNA+Match` |
| 8 | `buyer_disclosed_summary` | What has this buyer disclosed about their needs and timeline? | Insight | `Field+KB` |
| 9 | `buyer_location_factors` | What location features matter to this buyer? | Insight | `BuyerDNA+LocDNA` |

#### 4.2.1 Buyer · Residential (universal + these 11)

| # | Question Key | Question Text | Category | Source(s) |
|---|---|---|---|---|
| 1 | `buyer_neighborhood_preferences` | What kind of area setting is the buyer looking for? | Common | `KB` |
| 2 | `buyer_school_district` | Is a specific school district a requirement or preference? | Common | `KB` |
| 3 | `buyer_noise_tolerance` | How sensitive is the buyer to noise? | Common | `KB` |
| 4 | `buyer_area_familiarity` | How familiar is the buyer with the area? | Common | `KB` |
| 5 | `buyer_prefers_off_market` | Is the buyer open to off-market/pocket listings? | Common | `KB` |
| 6 | `buyer_property_style` | Does the buyer prefer a particular architectural style or era? | Common | `KB` |
| 7 | `buyer_nice_to_have` | What's on the buyer's wish list (nice-to-haves)? | Common | `KB` |
| 8 | `buyer_accessibility` | Does the buyer need any accessibility features? | Common | `KB` |
| 9 | `buyer_privacy_requirements` | What are the buyer's privacy preferences? | Common | `KB` |
| 10 | `buyer_lifestyle_goals` | How does the buyer envision using the home? | Common | `KB` |
| 11 | `buyer_outdoor_space` | How important is outdoor space to the buyer? | Common | `KB` |

#### 4.2.2 Buyer · Income / Multi-Family (universal + these 6)

| # | Question Key | Question Text | Category | Source(s) |
|---|---|---|---|---|
| 1 | `com_property_use` | What's the buyer's intended use for the property? | Common | `KB` |
| 2 | `com_occupancy_rate` | What minimum occupancy does the buyer require at purchase? | Common | `KB` |
| 3 | `com_lease_terms` | What lease structure does the buyer prefer (NNN/gross/etc.)? | Common | `KB` |
| 4 | `com_1031_exchange` | Is the buyer completing a 1031 exchange with a timing requirement? | Common | `KB` |
| 5 | `com_environmental_concerns` | Will the buyer require environmental studies (Phase I/II)? | Common | `KB` |
| 6 | `buyer_income_property_fit` | What type of income property fits this buyer's criteria? | Insight | `BuyerDNA+Match` |

#### 4.2.3 Buyer · Commercial (sale) (universal + these 6)

> ⚠️ Keys 1–5 are **identical keys** to the Income group above (`com_property_use`,
> `com_occupancy_rate`, `com_lease_terms`, `com_1031_exchange`, `com_environmental_concerns`),
> with slightly reworded labels. See §6 duplicates.

| # | Question Key | Question Text | Category | Source(s) |
|---|---|---|---|---|
| 1 | `com_property_use` | What's the buyer's intended use for the space? | Common | `KB` |
| 2 | `com_occupancy_rate` | What minimum occupancy does the buyer require at purchase? | Common | `KB` |
| 3 | `com_lease_terms` | What lease structure does the buyer prefer (NNN/gross/etc.)? | Common | `KB` |
| 4 | `com_1031_exchange` | Is the buyer completing a 1031 exchange with a timing requirement? | Common | `KB` |
| 5 | `com_environmental_concerns` | Will the buyer require environmental studies (Phase I/II)? | Common | `KB` |
| 6 | `buyer_commercial_space_fit` | What type of commercial space fits this buyer's intended use? | Insight | `BuyerDNA+Match` |

#### 4.2.4 Buyer · Business Opportunity (universal + these 6)

| # | Question Key | Question Text | Category | Source(s) |
|---|---|---|---|---|
| 1 | `biz_revenue_required` | What minimum revenue does the buyer require? | Common | `KB` |
| 2 | `biz_training_expected` | How much seller training/transition does the buyer expect? | Common | `KB` |
| 3 | `biz_staff_included` | Does the buyer want existing staff retained? | Common | `KB` |
| 4 | `biz_non_compete` | Does the buyer require a non-compete from the seller? | Common | `KB` |
| 5 | `biz_sba_financing` | Is the buyer using SBA or seller financing? | Common | `KB` |
| 6 | `buyer_business_type_fit` | What type of business is this buyer seeking? | Insight | `BuyerDNA+Match` |

#### 4.2.5 Buyer · Vacant Land (universal + these 8)

| # | Question Key | Question Text | Category | Source(s) |
|---|---|---|---|---|
| 1 | `land_intended_use` | What's the buyer's intended use for the land? | Common | `KB` |
| 2 | `land_zoning_required` | What zoning classification does the buyer require? | Common | `KB` |
| 3 | `land_utilities_needed` | What utilities does the buyer need available? | Common | `KB` |
| 4 | `land_soil_testing` | Will the buyer require soil/perc/environmental testing? | Common | `KB` |
| 5 | `land_build_timeline` | What's the buyer's build/development timeline? | Common | `KB` |
| 6 | `land_access_requirements` | What road access or easement does the buyer require? | Common | `KB` |
| 7 | `land_topography` | Does the buyer have flood/elevation/topography requirements? | Common | `KB` |
| 8 | `buyer_land_characteristics_fit` | What land characteristics matter most to this buyer? | Insight | `BuyerDNA+Match` |

---

### 4.3 Landlord

**Property types supported:** Residential, Commercial (lease). **Income, Business, Vacant Land =
Not Applicable** (no groups defined).
**Audience:** tenants / tenant agents asking about the rental.
**Rendered totals:** Residential 28 · Commercial 20 (each = 8 universal + the property-type group).
**40 distinct entries.**

#### 4.3.0 Universal (renders for both Landlord property types)

| # | Question Key | Question Text | Category | Source(s) |
|---|---|---|---|---|
| 1 | `maintenance_request_response_time` | How are maintenance requests handled, including emergencies and response times? | Common | `KB` |
| 2 | `planned_renovations` | Are there planned renovations or construction that could affect tenants? | Common | `KB` |
| 3 | `notice_to_vacate_required` | How much notice is required to vacate at lease end? | Common | `KB` |
| 4 | `lease_to_own_option` | Is a lease-to-own or rent-credit arrangement possible? | Common | `KB` |
| 5 | `nearby_amenities` | What location features are nearby? | Insight | `LocDNA` |
| 6 | `what_makes_property_unique` | What makes this rental stand out? | Insight | `PropDNA+Desc` |
| 7 | `rental_lifestyle_support` | What lifestyle does this rental appear to support? | Insight | `PropDNA+LocDNA` |
| 8 | `rental_disclosed_information` | What has been disclosed about this rental? | Insight | `Field+KB` |

#### 4.3.1 Landlord · Residential (universal + these 20)

| # | Question Key | Question Text | Category | Source(s) |
|---|---|---|---|---|
| 1 | `heating_cooling_system` | What heating and cooling system does the property have? | Common | `KB` |
| 2 | `laundry_situation` | Is there in-unit or shared laundry? | Common | `KB` |
| 3 | `storage_area_included` | Is dedicated storage included? | Common | `KB` |
| 4 | `internet_providers` | Which internet providers and speeds are available? | Common | `KB` |
| 5 | `security_features` | What security features does the property have? | Common | `KB` |
| 6 | `furnished_or_unfurnished` | Is the unit furnished, unfurnished, or negotiable? | Common | `KB` |
| 7 | `ev_charging_available` | Is EV charging available or installable? | Common | `KB` |
| 8 | `short_term_rentals_allowed` | Are short-term rentals permitted? | Common | `KB` |
| 9 | `pest_or_mold_history` | Any pest or mold history, and how was it resolved? | Common | `KB` |
| 10 | `utilities_individually_metered` | How are utilities metered and billed? | Common | `KB+Field` |
| 11 | `renters_insurance_required` | Is renter's insurance required, and at what coverage? | Common | `KB` |
| 12 | `application_process` | What's the application process, fee, and timeline? | Common | `KB` |
| 13 | `lawn_landscaping_responsibility` | Who is responsible for lawn/landscaping? | Common | `KB` |
| 14 | `average_utility_costs` | What are typical average utility costs? | Common | `KB` |
| 15 | `typical_tenancy_length` | According to the landlord, what is the typical length of tenancy? | Common | `KB` |
| 16 | `neighborhood_character` | What can you share about the area's setting and nearby amenities? | Common | `KB` |
| 17 | `noise_levels` | What's the noise level like? | Common | `KB` |
| 18 | `proximity_to_public_transit` | How close is public transit? | Common | `KB+LocDNA` |
| 19 | `guest_parking` | Is guest/visitor parking available? | Common | `KB` |
| 20 | `school_district_assignment` | Which school district serves this rental? | Common | `LocDNA` |

#### 4.3.2 Landlord · Commercial Lease (universal + these 12)

| # | Question Key | Question Text | Category | Source(s) |
|---|---|---|---|---|
| 1 | `commercial_loading_dock_freight_elevator` | Is there a loading dock or freight elevator? | Common | `KB` |
| 2 | `commercial_electrical_capacity` | What is the electrical capacity (amperage/voltage/3-phase)? | Common | `KB` |
| 3 | `commercial_exclusivity_rights` | Are exclusivity rights available? | Common | `KB` |
| 4 | `commercial_expansion_option_rofr` | Is there an expansion option or right of first refusal? | Common | `KB` |
| 5 | `commercial_building_access_hours` | What are the building/suite access hours? | Common | `KB` |
| 6 | `commercial_buildout_ti` | What build-out or tenant-improvement support is available beyond what's listed? | Common | `KB+Field` |
| 7 | `commercial_ada_accessibility` | Is the space ADA accessible? | Common | `KB` |
| 8 | `commercial_hvac_zones` | What is the HVAC type, the zoning of zones, and after-hours HVAC availability? | Common | `KB` |
| 9 | `commercial_restroom_count` | How many restrooms are there? | Common | `KB` |
| 10 | `commercial_co_tenancy` | What is the co-tenancy / anchor-tenant situation in the building? | Common | `KB` |
| 11 | `commercial_permitted_use` | What uses does the zoning permit for this space? | Common | `Field+KB` |
| 12 | `commercial_zoning_permitted_summary` | What does the listing state about the zoning and permitted uses? | Insight | `Field+KB` |

---

### 4.4 Tenant

**Property types supported:** Residential, Commercial Lease. **Income, Business, Vacant Land =
Not Applicable** (no groups defined).
**Audience:** landlords / leasing agents asking about the applicant. **No subject property** —
Insights educate about the applicant's stated criteria (Tenant DNA + pre-screening + criteria +
Match).
**Privacy:** 🔒 = redacted for non-owner / unauthorized viewers (§2.7). The AI **never** frames
any answer as an approve/deny recommendation and never references protected-class characteristics.
**Rendered totals:** Residential 15 · Commercial 11 (each = 6 universal + the property-type group).
**20 distinct entries.**

#### 4.4.0 Universal (renders for both Tenant property types)

| # | Question Key | Question Text | Category | Source(s) | Privacy |
|---|---|---|---|---|---|
| 1 | `faq_q14` | What's driving the applicant's rental search? | Common | `KB` | — |
| 2 | `faq_q20` | What's the applicant's biggest concern in this search? | Common | `KB` | 🔒 |
| 3 | `faq_q12` | Is there any chance the applicant would need to break the lease early? | Common | `KB` | 🔒 |
| 4 | `faq_q17` | Does the applicant have landlord or employer references available? | Common | `KB` | 🔒 |
| 5 | `tenant_background_disclosed` | What has this applicant disclosed about their rental background? | Insight | `TenantDNA+Match` | — |
| 6 | `tenant_rental_needs` | What rental needs and uses has the applicant described? | Insight | `TenantDNA+Match` | — |

#### 4.4.1 Tenant · Residential (universal + these 9)

| # | Question Key | Question Text | Category | Source(s) | Privacy |
|---|---|---|---|---|---|
| 1 | `faq_q18` | What is the source and stability of the applicant's income? | Common | `KB` | 🔒 |
| 2 | `faq_q13` | Would the applicant consider a longer lease for a locked-in/reduced rate? | Common | `KB` | — |
| 3 | `faq_q10` | Does the applicant prefer furnished or unfurnished? | Common | `KB` | — |
| 4 | `faq_q8` | Is the applicant willing to pay a pet deposit or pet rent if required? | Common | `KB` | — |
| 5 | `faq_q15` | How long was the most recent tenancy, and why is the applicant moving? | Common | `KB` | 🔒 |
| 6 | `faq_q9` | How flexible is the applicant on lease length? | Common | `KB` | — |
| 7 | `tenant_cosigner` | Is a co-signer or guarantor available if needed? | Common | `KB` | 🔒 |
| 8 | `tenant_application_readiness` | How soon is the applicant ready to apply and provide documentation? | Common | `KB` | 🔒 |
| 9 | `tenant_prior_conduct` | Has the applicant disclosed any prior rental conduct (late payments, notices)? | Common | `KB` | 🔒 |

#### 4.4.2 Tenant · Commercial Lease (universal + these 5)

| # | Question Key | Question Text | Category | Source(s) | Privacy |
|---|---|---|---|---|---|
| 1 | `faq_q22` | Does the applicant expect customer/client foot traffic, and how much? | Common | `KB` | — |
| 2 | `faq_q23` | Does the applicant have special equipment or power requirements? | Common | `KB` | — |
| 3 | `faq_q26` | What are the applicant's expected hours of operation? | Common | `KB` | — |
| 4 | `tenant_commercial_parking` | What are the applicant's parking needs for staff and customers? | Common | `KB` | — |
| 5 | `tenant_business_profile` | What business use and operating profile has this applicant disclosed? | Insight | `TenantDNA+Match` | — |

---

## 5. Summary Statistics

### 5.1 Totals

| Metric | Value |
|---|---|
| **Total question entries defined** | **182** |
| **Distinct question keys** | **177** (5 buyer keys duplicated across Income + Commercial) |
| **Common Questions** | 152 |
| **AI Insights** | 30 |
| **Fully implemented (defined in config)** | 182 / 182 (100%) |
| **Actually reachable in create/edit UI** | ~131 / 182 — **~51 Seller/Buyer Income/Commercial/Business entries do not render** due to the §6.0 select-vs-gating mismatch |
| **Fully functional answer pipeline** | Yes (V1 live) |
| **Partially functional** | (1) §6.0 gating mismatch blocks Seller/Buyer non-residential authoring; (2) Insight entries depend on generated DNA/Match/Desc pipelines; (3) `source` shorthand not runtime-wired |
| **Insufficient-context / refusal handling** | Centralized & implemented (§2.5) |
| **Duplicate keys** | 5 (buyer `com_*`) |
| **Privacy-redacted questions (Tenant)** | 8 FAQ keys (§2.7) |

### 5.2 Questions by User Type

| User Type | Distinct entries | Property types |
|---|---:|---:|
| Seller | 76 | 5 |
| Buyer | 46 (41 distinct keys) | 5 |
| Landlord | 40 | 2 |
| Tenant | 20 | 2 |
| **Total** | **182 (177 keys)** | — |

### 5.3 Questions Rendered by Role × Property Type

(Each cell = universal group + property-type group; "—" = Not Applicable.)

| User Type | Residential | Income | Commercial | Business | Vacant Land |
|---|---:|---:|---:|---:|---:|
| Seller | 34 | 25 | 25 | 25 | 23 |
| Buyer | 20 | 15 | 15 | 15 | 17 |
| Landlord | 28 | — | 20 (lease) | — | — |
| Tenant | 15 | — | 11 (lease) | — | — |

### 5.4 Questions by Knowledge Source (over 182 defined entries)

| Source | Count |
|---|---:|
| `KB` | 134 |
| `Field+KB` | 11 |
| `KB+Field` | 8 |
| `LocDNA` | 7 |
| `BuyerDNA+Match` | 5 |
| `PropDNA+Desc` | 4 |
| `KB+LocDNA` | 4 |
| `TenantDNA+Match` | 3 |
| `PropDNA+LocDNA` | 2 |
| `Field` | 2 |
| `PropDNA+Match` | 1 |
| `BuyerDNA+LocDNA` | 1 |

**Source-family rollup:** ~89% of entries rely on the creator's **KB** free-text (alone or
combined). Only ~16% touch **Location DNA**, ~7% **Property DNA**, ~5% **Buyer DNA**, ~2% **Tenant
DNA**, ~2% **Match**. **Structured Field** appears in ~12% (usually combined with KB).

---

## 6. Coverage Analysis

### 6.0 🛑 Critical functional gap — Seller/Buyer non-residential groups don't render
As detailed in §2.1, the Seller/Buyer property-type `<select>` values (`Income`, `Commercial`,
`Business`) do not match their long-form `gating` keys (`Income Property`, etc.), and the render
blade does no normalization. **Consequence:** creating a Seller or Buyer listing for Income,
Commercial, or Business shows only the **universal + residential** questions; the intended
property-specific groups (~51 entries) are **defined in config but never reached** in the standard
create/edit flow. `Vacant Land` (exact match) and `Residential` (fallback) render correctly.
Landlord/Tenant are unaffected. **This is the highest-priority remediation item** — it means a
large fraction of the cataloged Seller/Buyer questions are effectively dormant until the select
values are aligned to the gating keys (or a normalization step is added at
`ai-questions-input.blade.php:35`). Note this affects **authoring/persistence of KB answers**; the
runtime answer engine remains independent (§2.4).

### 6.1 Excellent coverage
- **Seller Residential** (34 questions) — deep condition/systems, costs, location coverage.
- **Landlord Residential** (28) — systems, policies, costs, neighborhood well covered.
- **Seller Income & Commercial** (25 each) — solid operations/building-systems coverage.
- **Compliance & privacy scaffolding** — guardrail sanitizer, viewer-authorization redaction,
  persistent disclaimer, and gating isolation are all implemented and test-covered.

### 6.2 Weak coverage
- **Buyer Income & Commercial** (15 each) — thinnest sale KBs; rely on 5 shared generic `com_*`
  questions with no property-type-specific depth.
- **Tenant Commercial Lease** (11) — only 5 commercial-specific questions; lightest KB overall.
- **AI Insights are sparse** (30 total / 16%): Seller has the richest Insight set (5 universal);
  Buyer/Landlord/Tenant Insights are mostly 1 per group.
- **Buyer has no universal Insight into financing/budget posture** framed compliantly (the older
  legacy set had these; see §6.5).

### 6.3 Missing high-value questions (gaps)
- **Seller Residential:** no explicit HOA/CDD-fee or special-assessment KB question (may exist as
  native Field but no KB prompt); no pool/spa equipment condition; no sewer vs septic prompt.
- **Seller Income:** no explicit rent-roll / current-vs-market-rent KB prompt (only
  `existing_tenant_lease_terms`); no unit-mix prompt.
- **Buyer (all types):** no budget/financing-readiness question in the compliant new set (only
  `buyer_flexibility`); no timeline/urgency prompt beyond relocation.
- **Landlord Residential:** no pet-policy KB question (native field only); no parking-assignment
  prompt beyond guest parking; no smoking policy.
- **Tenant:** no move-in-date / desired-lease-start prompt; no number-of-occupants prompt (both
  likely native fields, but no KB prompt).
- **Vacant Land (Seller/Buyer):** no utilities-at-road KB prompt on the Seller side (Buyer has
  `land_utilities_needed`, Seller doesn't have the mirror).

### 6.4 Duplicate / overlapping questions
- **Buyer `com_*` (5 keys):** `com_property_use`, `com_occupancy_rate`, `com_lease_terms`,
  `com_1031_exchange`, `com_environmental_concerns` appear **identically** in **both** the Income
  and Commercial groups (labels differ only slightly: "property" vs "space"). Because they share
  the **same key**, a creator's answer is bound once and reused — but this means income and
  commercial buyers get the same 5 generic questions. **Recommend:** either differentiate per type
  or consolidate into a single shared "commercial/income" group.
- **Cross-role reuse (not true duplicates, but shared keys):** `average_utility_costs`,
  `school_district_assignment`, `neighborhood_character` appear in both Seller-Residential and
  Landlord-Residential; `commercial_ada_accessibility`, `commercial_restroom_count`,
  `commercial_zoning_permitted_summary` appear in both Seller-Commercial and
  Landlord-Commercial. These are appropriate role-specific reuse (different config files).

### 6.5 Overlapping *legacy* question set (documentation gap)
`resources/views/buyer_criteria/add.blade.php` still contains a **separate, older inline set** of
`listing_ai_faq_buyer_*` textarea fields (e.g. `buyer_active_now`, `buyer_timeline`,
`buyer_deal_breakers`, `buyer_lost_deal`, `buyer_budget_confirmed`, financing/pre-approval
questions, etc.). These are **not** part of the config-driven two-axis KB cataloged here and are
**not** gated by property type. `buyer_lost_deal` in particular was explicitly slated for removal
in the spec (Part H). **Status: legacy / parallel implementation — recommend reconciling** so the
config-driven KB is the single source of truth.

### 6.6 Questions that should be consolidated
- The 5 Buyer `com_*` questions (§6.4) — one shared group.
- Seller `commercial_zoning_uses` (Common) vs `commercial_zoning_permitted_summary` (Insight) ask
  nearly the same thing from the same source (`Field+KB`); consider merging.

### 6.7 Questions that should be property-specific (currently generic)
- Buyer Income vs Commercial `com_*` set — genuinely different diligence concerns (rent roll /
  cap rate vs owner-occupant fit) collapsed into one generic set.

### 6.8 Questions that should be universal (currently property-specific)
- `average_utility_costs` exists in Seller **Residential** only, but is equally relevant to
  Income and (as tenant-facing cost) other types — candidate to promote to universal.
- `school_district_assignment` (Seller/Landlord) — residential-only today; reasonable, but a
  general "nearby location features" universal Insight already partially covers it.

---

## 7. Knowledge Base Mapping

For every question, the `source` shorthand records the *intended* internal knowledge source(s).
Mapping to the requested canonical source names:

| Requested source name | Config shorthand | Runtime registry equivalent (§2.9) | Wired at runtime? |
|---|---|---|---|
| Listing Details / Property Details / Terms | `Field` | `listing`, `offer_analysis` | Registry: **Yes**; shorthand: **No** (doc-only) |
| Description | `Desc` | (listing description text) | Description-fallback flag **off** by default |
| Highlights | (folded into `Desc`/`PropDNA`) | `property_intelligence` | Registry: Yes |
| Location DNA | `LocDNA` | `location_intelligence` | Registry: Yes |
| Property DNA | `PropDNA` | `property_intelligence` | Registry: Yes |
| Buyer / Tenant Criteria | `BuyerDNA` / `TenantDNA` | `buyer_avatar` / `tenant_avatar` | Registry: Yes |
| Match Scoring | `Match` | `compatibility` | Registry: Yes |
| Knowledge Base (creator answers) | `KB` | (via `ai_faq_answers` / `listing_ai_faq`) | **Yes** (primary answer source) |
| Representation Preferences / Agent Profile | (not tagged in FAQ configs) | `agent_profile`, `agent_presets` | Registry: Yes (separate `agent_profile` question type) |
| Public Listing Data | (folded into `Field`) | `listing` | Registry: Yes |
| Governance / Policy | (not tagged) | `governance_documents` | Registry: Yes (`educational` type) |

**Key finding (repeated):** the config `source` shorthand and the runtime registry are **two
independent layers**. The shorthand documents design intent per question; the registry governs
what the answer engine may actually consult (keyed by *question type*, not by FAQ key). No code
translates a FAQ key's `source` string into a registry source. Any audit that assumes a question's
tagged `source` is what the engine uses at answer time would be mistaken — see §2.3.

---

## 8. Appendix

### 8.1 Feature flags (config/ask_ai.php)

| Flag | Default | Effect |
|---|---|---|
| `rate_limit_per_minute` | 20 | Ask AI API requests/min per user or IP |
| `enable_openai_intent_normalization` | **false** | Off: unmatched questions stay `unsupported` (no OpenAI intent mapping) |
| `enable_description_fallback` | **false** | Off: no description-derived fallback for null fields |
| `agent_ai_v2_enabled` + per-scope flags | **false** | Off: Agent AI V2 routes 404; V1 unaffected |

### 8.2 Compliance guardrail categories (drop patterns)
`protected_class`, `demographic`, `steering`, `advice` (incl. negotiation/leverage),
`legal_conclusion`, `financial_advice`; plus unquoted-superlative neutralization.

### 8.3 Tenant redaction key sets
See §2.7 (never-expose, applicant-sensitive native, applicant-sensitive FAQ).

### 8.4 Test coverage (verifies this catalog)
- `AskAiKnowledgeBaseRenderTest` — 12-case role×property-type render matrix; asserts both
  "Common Questions" and "AI Insights" sections render, positive labels present, cross-property-
  type leakage absent, and the persistent educational disclaimer renders.
- `AskAiComplianceGuardrailServiceTest` — sentence-level sanitization, superlative carve-out,
  withheld fallback, idempotent disclaimer.
- `AskAiFaqEnrichmentServiceTest` — config index shape, group→category normalization, no external
  calls.
- `AskAiViewerAuthorizationServiceTest` — scope resolution + field-level redaction (owner /
  authorized / public).
- Additional: `AskAiGoldenQaSuiteTest`, `AskAiRefactorParityTest`, `AskAiSourceResolverTest`,
  `AskAiResponseContractServiceTest`, `AskAiFinalResponseBuilderServiceTest`,
  `AskAiSuggestedQuestionsServiceTest`, `AskAiKnowledgeSourceRegistryTest`,
  `SyncFaqAnswersCommandTest`, and ~60 files total under the Ask AI test namespaces.

### 8.5 Authoritative design roadmap
`docs/ask-ai-kb-replacement-spec.md` (Parts A–J) is the design source. Its banner still reads
"DRAFT — nothing applied," but the concrete classes and tests above show the spec's Phases
(A gating, B suggested-question dedup, C audience, D two-category, E/E.1 compliance, J tenant
access control) **have since been implemented in code**. Deferred items per the spec: financial-
quality analysis (prohibited unless a future legally-reviewed phase), the Part J.7 relationship-
table selection for `authorized` scope (partially implemented via `accepted_bid_summaries`), and
extending redaction to sensitive Buyer fields.

---

*Generated from the current repository state. Every question, key, category, and source in §4 is
transcribed verbatim from `config/ai_faq_seller.php`, `config/ai_faq_buyer.php`,
`config/ai_faq_landlord.php`, and `config/tenant_ai_faq.php`. Runtime behavior in §2 is traced
from the controllers, services, routes, and tests named above. No values were inferred; gaps are
marked Not Applicable, Not Implemented, or documentation-only.*
