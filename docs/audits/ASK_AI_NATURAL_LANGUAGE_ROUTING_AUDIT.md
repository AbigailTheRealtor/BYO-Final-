# Ask AI Natural Language Routing Audit

**Date:** 2026-06-05  
**Pipeline Version References:**
- Context Builder: `ASK_AI_CONTEXT_V1` (`AskAiContextBuilderService::CONTEXT_VERSION`)
- Response Contract: `ASK_AI_RESPONSE_CONTRACT_V1` (`AskAiResponseContractService::CONTRACT_VERSION`)
- Prompt Package: `ASK_AI_PROMPT_PACKAGE_V1` (`AskAiPromptBuilderService::PROMPT_PACKAGE_VERSION`)

**Scope:** Read-only audit of services in `app/Services/AskAi/`, `app/Models/AiFaqAnswer.php`, `app/Http/Controllers/AiKnowledgeController.php`, and config files `ai_faq_seller.php`, `ai_faq_landlord.php`, `ai_faq_buyer.php`, `tenant_ai_faq.php`. No production code was changed.

---

## Executive Summary

The Ask AI pipeline fails to answer every common factual listing question a user asks — questions like "How many bedrooms?", "What is the rent?", "Are pets allowed?", "Is there an HOA?", "What are the utilities?" — because the pipeline was built exclusively around DNA/intelligence sources (property strengths, buyer avatar, compatibility scores) and has no awareness that listings contain directly answerable factual fields. Three compounding failures drive every observed silent refusal: (1) the classifier either traps factual keywords inside the `buyer_tenant_match` bucket or lets them fall through to `unsupported`; (2) `extractListingFields()` extracts only 10 administrative metadata fields and none of the factual fields users actually ask about; and (3) a rich, seller/landlord-answered FAQ knowledge base (`listing_ai_faq` meta / `ai_faq_answers` table) has been fully built and wired to a dedicated JSON endpoint (`/ai-knowledge/{token}`) but is completely invisible to every Ask AI service. OpenAI is only ever called after a `prompt_ready` package arrives; because factual questions never produce one, OpenAI never fires for any factual query. The fix requires adding a `listing_facts` question type to the classifier, extending context assembly to pull real listing fields, wiring the FAQ bank as a `faq_answers` context source, adding a `listing_facts` contract, and optionally layering OpenAI as an intent normalizer for ambiguous questions — all behind a feature flag.

---

## Section 1 — Classifier Failure Trace

**Service:** `AskAiQuestionClassifierService` (`app/Services/AskAi/AskAiQuestionClassifierService.php`)  
**Method:** `classify(string $question): array`  
**Mechanism:** Ordered keyword scan over `KEYWORD_RULES`; first match wins; no `listing_facts` type exists anywhere.

### 1.1 The 15 Audited Questions — Exact Classification Results

| # | Sample Question | `question_type` Assigned | Matched Keyword | Why It Is Wrong |
|---|---|---|---|---|
| 1 | "How many bedrooms does this have?" | `buyer_tenant_match` | `bedrooms` (line 216) | Routes to compatibility pipeline; no single-listing factual path exists |
| 2 | "How many bathrooms?" | `buyer_tenant_match` | `bathrooms` (line 217) | Same as above |
| 3 | "What is the monthly rent?" | `unsupported` | *(no match)* | "rent" is not in any keyword rule |
| 4 | "What is the asking price?" | `unsupported` | *(no match)* | "price", "asking price" not in any rule |
| 5 | "Is there an HOA? What is the fee?" | `unsupported` | *(no match)* | "HOA", "hoa fee" not in any rule |
| 6 | "Are pets allowed?" | `unsupported` | *(no match)* | "pets", "pet policy" not in any rule |
| 7 | "What is the lease length?" | `buyer_tenant_match` | `lease length` (line 207) | Routes to compatibility pipeline |
| 8 | "What appliances are included?" | `unsupported` | *(no match)* | "appliances" not in any rule |
| 9 | "What utilities are included?" | `unsupported` | *(no match)* | "utilities" not in any rule |
| 10 | "How old is the roof?" | `unsupported` | *(no match)* | "roof" not in any rule |
| 11 | "Is there a pool?" | `unsupported` | *(no match)* | "pool" not in any rule |
| 12 | "What are the parking options?" | `buyer_tenant_match` | `parking preference` (line 214) | Routes to compatibility pipeline |
| 13 | "How do I schedule a showing?" | `unsupported` | *(no match)* | "showing" not in any rule |
| 14 | "What is the average monthly electric bill?" | `unsupported` | *(no match)* | "electric", "utility costs" not in any rule |
| 15 | "Is the property move-in ready?" | `unsupported` | *(no match)* | "move-in ready" not in any rule |

### 1.2 Classification Distribution

- **`buyer_tenant_match` (misrouted):** Questions 1, 2, 7, 12 — four questions
- **`unsupported` (silent dead end):** Questions 3, 4, 5, 6, 8, 9, 10, 11, 13, 14, 15 — eleven questions
- **`listing_facts` (correct route):** Does not exist anywhere in the classifier, contract service, or prompt builder

### 1.3 Root Cause in the Classifier

The `KEYWORD_RULES` constant was designed for DNA/compatibility/intelligence question types. The keywords `bedrooms`, `bathrooms`, `lease length`, `parking preference`, and `location preference` were placed inside `buyer_tenant_match` because those terms appear in buyer/tenant criteria listings — but a user browsing a property page asking "how many bedrooms?" is asking a factual lookup question, not a compatibility question. The correct disposition would be a `listing_facts` type that routes to a deterministic field lookup.

There is **no `listing_facts` keyword rule** and **no `listing_facts` entry** in `KEYWORD_RULES`. The fallback `return ['question_type' => 'unsupported', ...]` at line 359 is the terminal state for eleven of the fifteen audited questions.

---

## Section 2 — Context Gap

**Service:** `AskAiContextBuilderService` (`app/Services/AskAi/AskAiContextBuilderService.php`)  
**Method:** `extractListingFields(object $listing, string $canonicalType, int $listingId): array`

### 2.1 Fields Currently Extracted (All 10)

```
listing_type    listing_id     listing_title
city            state          county
property_type   listing_status created_at    updated_at
```

These are administrative metadata fields. None are factual fields users ask about.

### 2.2 Factual Fields NOT Extracted

The following fields are commonly available on listing models (via native columns or EAV meta using `$listing->info($key)`) but are **completely absent** from `extractListingFields()`:

| Factual Topic | Field Key(s) | Source |
|---|---|---|
| Bedrooms | `bedrooms`, `num_bedrooms` | Native column / EAV |
| Bathrooms | `bathrooms`, `num_bathrooms` | Native column / EAV |
| Asking price | `asking_price`, `list_price` | Native column / EAV |
| Monthly rent | `rent_amount`, `monthly_rent` | Native column / EAV |
| Lease length | `lease_length`, `lease_term` | EAV |
| Pet policy | `pets_allowed`, `pet_policy` | EAV |
| HOA fee | `hoa_fee`, `hoa_monthly_fee` | EAV |
| Pool | `pool`, `has_pool` | EAV |
| Parking | `parking_spaces`, `garage_spaces` | Native column / EAV |
| Appliances | `appliances_included` | EAV |
| Utilities included | `utilities_included` | EAV |
| Move-in ready | `move_in_ready_status` | EAV (FAQ meta) |
| Showing instructions | `showing_instructions` | EAV |
| Square footage | `square_feet`, `sqft` | Native column / EAV |
| Year built | `year_built` | Native column / EAV |

### 2.3 Impact

Even if the classifier were fixed to produce `listing_facts`, the context assembled by `buildForListing()` would contain no factual field values. The contract service would find nothing useful in `context['listing']`, and OpenAI would receive an empty data block.

---

## Section 3 — The `buyer_tenant_match` Keyword-Trap Failure Mode

### 3.1 What Happens for Questions 1, 2, 7, and 12

These four questions match the `buyer_tenant_match` bucket. Here is the exact pipeline trace:

**Step 1 — Classifier** (`AskAiRunnerV2Service::run()` → `classifier->classify()`):  
Returns `question_type = 'buyer_tenant_match'`.

**Step 2 — Internal Runner** (`AskAiInternalRunnerService::run()`):  
Calls `contextBuilder->buildForListing($listingType, $listingId, $options)`.

**Step 3 — Context Builder** (`buildForListing()`):  
Checks `$this->hasPairOptions($options ?? [])` at line 144. `hasPairOptions()` returns `true` only when **all four** of these keys are present in `$options`:
```php
$options['demand_listing_type']
$options['demand_listing_id']
$options['supply_listing_type']
$options['supply_listing_id']
```
When a user is browsing a single property page — the normal use case — none of these pair keys are supplied. `$this->compatibility` remains `null`.

**Step 4 — Contract Service** (`buildContract('buyer_tenant_match', $context)`):  
The `buyer_tenant_match` contract defines `required_sources = ['compatibility']`. The service calls `findMissingRequiredSources(['compatibility'], $context)`. Since `$context['compatibility']` is `null`, it returns `['compatibility']` as missing. The contract returns `status = 'insufficient_context'`.

**Step 5 — Prompt Builder** (`buildPromptPackage()`):  
Receives `contract['status'] = 'insufficient_context'`. Builds an `insufficient_context` package with `success = false`. The `UNAVAILABLE_DATA_DISCLOSURE` string is appended but **OpenAI is never called**.

**Step 6 — OpenAI Adapter** (`generate($promptPackage)`):  
`$promptPackage['status']` is `'insufficient_context'`, not `'prompt_ready'`. The adapter returns immediately with `status = 'blocked'`. **No network call is made.**

**Step 7 — Final Response:**  
The user receives a silent failure. Depending on the UI implementation, they may see a generic "data not available" message or nothing at all.

### 3.2 Why This Is a Trap, Not Just a Gap

The keywords `bedrooms`, `bathrooms`, `lease length`, and `parking preference` were placed in `buyer_tenant_match` because these are fields on buyer/tenant criteria listings used for compatibility matching. The intent was correct for the compatibility use case. But when a user asks "How many bedrooms does this property have?", they are asking a factual lookup question against a single supply listing — not requesting a compatibility analysis. The classifier has no way to distinguish these two intents, and the `buyer_tenant_match` path hard-requires a pair of listings that is never present in the single-listing browse context.

---

## Section 4 — AI FAQ Question Bank Disconnection

### 4.1 What Exists (and Works) Outside Ask AI

The platform has a fully operational FAQ knowledge base system with four components:

**Component A — Config files:** Four rich FAQ config files define question keys, labels, categories, and tooltips:
- `config/ai_faq_seller.php` — 35+ question keys across 5 groups (e.g., `roof_age_and_condition`, `average_utility_costs`, `parking_arrangements`, `hoa_community_highlights`, `average_utility_costs`)
- `config/ai_faq_landlord.php` — 30+ question keys across 4 groups (e.g., `heating_cooling_system`, `laundry_situation`, `pets_and_animals`-adjacent, `furnished_or_unfurnished`)
- `config/ai_faq_buyer.php` — 25+ question keys across 4 groups
- `config/tenant_ai_faq.php` — 27 question entries across 6 categories with `aliases` arrays that explicitly anticipate NLP matching

**Component B — Storage:** Seller and landlord listings store answered FAQ data as JSON in the EAV meta key `listing_ai_faq`. Tenant listings store it in a native column `listing_ai_faq`. Answers are populated by sellers/landlords via the "AI Questions" tab in the offer listing form.

**Component C — Database model:** `AiFaqAnswer` model (`app/Models/AiFaqAnswer.php`) maps to `ai_faq_answers` table with fields: `listing_type`, `listing_id`, `question_key`, `question_group`, `intelligence_category`, `answer_text`, `answer_normalized` (JSON).

**Component D — API endpoint:** `AiKnowledgeController` exposes `GET /ai-knowledge/{token}` which reads `listing_ai_faq` meta, resolves it against the config file for the listing type, and returns a structured JSON knowledge base grouped by category. This endpoint is fully functional.

### 4.2 The Disconnection

**`AiFaqAnswer` model** is **never imported** and **never queried** in any Ask AI service:
- `AskAiContextBuilderService` — no reference
- `AskAiResponseContractService` — no reference
- `AskAiPromptBuilderService` — no reference
- `AskAiInternalRunnerService` — no reference
- `AskAiRunnerV2Service` — no reference

**`listing_ai_faq` meta key** (seller/landlord) and **`listing_ai_faq` column** (tenant) are **never read** in any Ask AI service. The `extractListingFields()` method does not include this key.

**`AiKnowledgeController`** is never called by any Ask AI service. `AskAiRunnerV2Service` has no HTTP client, no reference to `AiKnowledgeController`, and no path to reach the `/ai-knowledge/` endpoint.

**The four FAQ config files** are never loaded (via `config()`) in any Ask AI service.

### 4.3 Concrete FAQ Answers Being Lost

When a seller answers "8-year-old architectural shingles, replaced 2019, no known issues" to `roof_age_and_condition`, and a user then asks "How old is the roof?" — the answer exists in the database and would be returned by the `/ai-knowledge/` endpoint. But Ask AI has no path to retrieve it. The classifier returns `unsupported`, the pipeline terminates, and the user receives nothing.

This same disconnection applies to every answered FAQ question:
- `average_utility_costs` → "What are the monthly utilities?"
- `parking_arrangements` → "What parking is available?"
- `hoa_community_highlights` → "Tell me about the HOA"
- `pets_and_animals` (landlord) → "Are pets allowed?"
- `furnished_or_unfurnished` (landlord) → "Is this furnished?"
- All 27 tenant FAQ answers → Any question about the tenant's preferences

---

## Section 5 — OpenAI's Current Role vs. What It Could Do

### 5.1 Current Role

OpenAI is invoked exclusively in `AskAiOpenAiAdapterService::generate()`, called by `AskAiRunnerV2Service` after the three-phase internal pipeline completes. The gate check is:

```php
if ($status !== 'prompt_ready') {
    return ['success' => false, 'status' => 'blocked', ...];
}
```

OpenAI plays **no role in understanding or normalizing user question intent**. It receives only a fully assembled, governed prompt package from the prompt builder. It cannot reclassify a question, cannot recognize that a `buyer_tenant_match` question was actually a factual lookup, and cannot route around a classifier failure.

### 5.2 What It Could Do

OpenAI could serve as a **third-layer intent normalizer** — a lightweight pre-classification fallback that activates only when the deterministic classifier (Layer 2) produces no `listing_facts` match and the question does not hit `prohibited`. In this role, it would:

1. Receive the raw user question plus a short list of known field topics
2. Return a normalized field key (e.g., `roof_age_and_condition`) or `unknown`
3. Allow the pipeline to look up that field from the context and FAQ bank

This capability is **currently unused**. The OpenAI adapter is a pure output generator; intent normalization would require a new pre-classification service that calls OpenAI before context assembly.

---

## Section 6 — Recommended Architecture: Option C (Hybrid Approach)

### 6.1 Three-Layer Pipeline Design

```
User Question
      │
      ▼
┌─────────────────────────────────────────────────┐
│  LAYER 1: Deterministic Compliance Blocker       │
│  (existing 'prohibited' check — unchanged)       │
│  → If prohibited: refusal_required; stop.        │
└─────────────────────────────────────────────────┘
      │ (not prohibited)
      ▼
┌─────────────────────────────────────────────────┐
│  LAYER 2: Deterministic Listing-Field Router     │
│  (new 'listing_facts' question type)             │
│  Keyword map → factual field key                 │
│  → If match: pull field from listing context     │
│              + FAQ bank; build answer.           │
└─────────────────────────────────────────────────┘
      │ (no Layer 2 match)
      ▼
┌─────────────────────────────────────────────────┐
│  LAYER 3: OpenAI Intent Normalization            │
│  (new, optional, feature-flagged)                │
│  → Normalize ambiguous question to a field key   │
│  → If field key returned: re-enter Layer 2       │
│  → If no match: fall through to existing types   │
└─────────────────────────────────────────────────┘
      │ (existing question types)
      ▼
 Existing pipeline (compatibility, standout, etc.)
```

### 6.2 Layer 1 — Deterministic Compliance Blocker (No Change)

The existing `prohibited` keyword rules remain exactly as-is and always run first. This is the fair housing and protected-class governance gate. It must not be modified, reordered, or bypassed by any new layer.

### 6.3 Layer 2 — Deterministic Listing-Field Intent Router

A new `listing_facts` question type is added to `KEYWORD_RULES` (between `prohibited` and `compatibility_signals` — after compliance, before DNA types). The keyword map covers all 15 audited topics plus common paraphrases:

| Keyword Group | Sample Keywords | Target Field |
|---|---|---|
| Bedrooms | `how many bedrooms`, `number of bedrooms`, `bed count`, `beds` | `bedrooms` |
| Bathrooms | `how many bathrooms`, `number of bathrooms`, `bath count`, `baths` | `bathrooms` |
| Rent | `monthly rent`, `rent price`, `how much is rent`, `what is rent` | `rent_amount` |
| Asking price | `asking price`, `list price`, `sale price`, `how much does it cost` | `asking_price` |
| HOA | `hoa fee`, `hoa cost`, `homeowner association`, `is there an hoa` | `hoa_fee` |
| Pet policy | `are pets allowed`, `pet policy`, `pet friendly`, `dogs allowed`, `cats allowed` | `pets_allowed` |
| Lease length | `lease length`, `lease term`, `how long is the lease` | `lease_length` |
| Appliances | `appliances included`, `what appliances`, `washer dryer`, `refrigerator included` | `appliances_included` |
| Utilities | `utilities included`, `what utilities`, `electric included`, `water included` | `utilities_included` |
| Roof | `how old is the roof`, `roof age`, `roof condition`, `when was the roof replaced` | `roof_age_and_condition` |
| Pool | `is there a pool`, `does it have a pool`, `pool included` | `pool` |
| Parking | `parking available`, `garage spaces`, `how many parking`, `parking options` | `parking_arrangements` |
| Showing | `how do i schedule a showing`, `showing instructions`, `can i see the property` | `showing_instructions` |
| Utilities cost | `average utility`, `monthly electric bill`, `utility costs` | `average_utility_costs` |
| Move-in ready | `is it move-in ready`, `move in ready`, `ready to move in` | `move_in_ready_status` |

The `buyer_tenant_match` bucket must be cleaned of any keywords that overlap with factual single-listing questions (`bedrooms`, `bathrooms`, `lease length`, `parking preference`, `location preference`). Keywords describing a buyer/tenant's *desired* criteria (e.g., `what does this buyer want`, `rent budget`, `would a buyer`) should remain in `buyer_tenant_match`.

**FAQ lookup integration:** When `listing_facts` is matched, the context assembly layer must pull matched FAQ answers from the `listing_ai_faq` / `ai_faq_answers` sources as a `faq_answers` block in the context. The prompt builder's `filterAllowedContext()` must then pass these through.

### 6.4 Layer 3 — OpenAI Intent Normalization (Feature-Flagged)

When Layer 2 produces no `listing_facts` match and Layer 1 did not fire, a new optional pre-classification step can call OpenAI with:
- The raw user question
- A compact list of known factual field keys (e.g., the 15 audited topics)
- A strict prompt instructing OpenAI to return only a field key or `unknown`

If OpenAI returns a recognized field key, the pipeline re-enters Layer 2 with that key as the classification. If it returns `unknown`, the pipeline falls through to the existing question types (`property_standout`, `suited_audience`, etc.).

**Governance constraint:** OpenAI must never be given permission to generate a final answer from un-vetted data in this layer. It is only allowed to normalize intent. The final answer must always be grounded in the structured listing/FAQ context assembled by the context builder.

**Feature flag:** This layer must be gated by a feature flag (e.g., `config('ask_ai.enable_openai_intent_normalization')`) and default to `false`. Governance review should precede enabling it in production.

### 6.5 Final Answer Grounding Requirement

In all layers, the final answer delivered to the user must be grounded exclusively in:
- Native listing fields extracted by an extended `extractListingFields()`
- EAV meta fields accessible via `$listing->info($key)`
- FAQ answers from `listing_ai_faq` / `ai_faq_answers`

OpenAI must never be permitted to invent data for a `listing_facts` response. If no field value exists in the assembled context, the response must explicitly state "this information is not available in the listing data" rather than hallucinating a value.

---

## Section 7 — Exact Next Build Tasks

Tasks are listed in dependency order. Each task is a discrete, shippable unit.

### Build Task A — Add `listing_facts` Question Type to Classifier

**File:** `app/Services/AskAi/AskAiQuestionClassifierService.php`  
**What:** Add `listing_facts` to `KEYWORD_RULES` between `prohibited` and `compatibility_signals`. Include a keyword map covering the 15 audited topics and common paraphrases (see Section 6.3). Add `'listing_facts' => 0.90` to `confidenceFor()`. Remove `bedrooms`, `bathrooms`, `lease length`, `parking preference`, and `location preference` from the `buyer_tenant_match` keyword bucket (keep buyer/tenant-criteria-specific phrases in `buyer_tenant_match`).  
**Acceptance:** `classify('How many bedrooms?')` returns `listing_facts`. `classify('How well does this tenant match the buyer?')` still returns `buyer_tenant_match`. `classify('Tell me about the school district')` still returns `prohibited`.  
**Depends on:** Nothing — can ship independently.

---

### Build Task B — Extend `extractListingFields()` with Factual Fields

**File:** `app/Services/AskAi/AskAiContextBuilderService.php`  
**What:** Extend `extractListingFields()` to pull the factual listing fields listed in Section 2.2 from native columns and EAV meta using the existing `$nativeGet` and `$infoGet` closures. Fields to add: `bedrooms`, `bathrooms`, `asking_price`, `rent_amount`, `lease_length`, `pets_allowed`, `hoa_fee`, `pool`, `parking_spaces`, `appliances_included`, `utilities_included`, `showing_instructions`, `square_feet`, `year_built`. All new fields should be nullable strings using the existing `$resolve()` pattern.  
**Acceptance:** `context['listing']` now includes factual fields for a seller listing with populated EAV meta; all existing tests still pass.  
**Depends on:** Nothing — can ship independently, but has highest value when paired with Task A and Task D.

---

### Build Task C — Wire `listing_ai_faq` / `ai_faq_answers` as `faq_answers` Context Source

**File:** `app/Services/AskAi/AskAiContextBuilderService.php`  
**What:** Add a `buildFaqAnswers()` method that reads `listing_ai_faq` meta (seller, landlord, buyer via `$listing->info('listing_ai_faq')`) or the native column (tenant via `$listing->listing_ai_faq`), decodes the JSON, and returns an array keyed by question key. This `faq_answers` block is populated for all listing types. Also add a fallback that queries `AiFaqAnswer::where('listing_type', $canonical)->where('listing_id', $listingId)->get()` if `listing_ai_faq` is empty. Add `faq_answers` as a top-level key in the `buildForListing()` return array.  
**Acceptance:** `context['faq_answers']['roof_age_and_condition']` returns the seller's stored answer when the field has been filled in. `context['faq_answers']` is an empty array (not null) when no FAQ answers exist.  
**Depends on:** Nothing — can ship independently.

---

### Build Task D — Add `listing_facts` Contract to Response Contract Service

**File:** `app/Services/AskAi/AskAiResponseContractService.php`  
**What:** Add a `listing_facts` entry to `TYPE_CONTRACTS`. Required sources: `['listing']`. Allowed context paths include all factual fields added in Task B plus `faq_answers.*`. Response rules: (1) base response only on field values explicitly present in the listing context and faq_answers; (2) if the field value is null or absent, state it is not available — do not estimate or infer; (3) do not reference protected class characteristics; (4) attribute facts to the listing source. Required disclosures: "Information is sourced directly from the listing data provided by the seller or landlord. Verify all details independently before making any financial or legal decision."  
**Acceptance:** `buildContract('listing_facts', $context)` returns `status = 'contract_ready'` when `context['listing']` is non-null. Returns `insufficient_context` only when the listing itself was not found.  
**Depends on:** Task A (classifier produces `listing_facts`), Task B (fields exist in context).

---

### Build Task E — Add OpenAI Intent Normalization Pre-Classification Step

**File:** New service `app/Services/AskAi/AskAiIntentNormalizerService.php`  
**What:** Create a new service that accepts a raw user question and a list of known field keys, sends a minimal governed prompt to OpenAI, and returns a normalized field key or `null`. Gate the service call behind `config('ask_ai.enable_openai_intent_normalization', false)`. Integrate the normalizer into `AskAiRunnerV2Service::run()`: if the classifier returns `unsupported` and the feature flag is on, call the normalizer and attempt to reclassify with the returned key.  
**Acceptance:** A question like "Does the A/C work well?" normalizes to `hvac_system_age` and routes through `listing_facts`. A question about protected classes triggers Layer 1 before normalization is reached. When the flag is `false`, behavior is identical to current.  
**Depends on:** Task A, Task B, Task C, Task D must all be complete first.

---

### Build Task F — Update `AskAiSuggestedQuestionsService` with Factual Questions

**File:** `app/Services/AskAi/AskAiSuggestedQuestionsService.php`  
**What:** Add `listing_facts` questions to the `POOLS` arrays for each listing type. Suggested factual chips should be listing-type-appropriate: seller/landlord listings should suggest "How many bedrooms?", "What is the asking price/rent?", "Are pets allowed?", "What utilities are included?" etc. Buyer/tenant listings can suggest factual chips about their stated criteria. Add `listing_facts` to `CATEGORY_META` and `CATEGORY_ORDER` (insert between `property_standout` and `suited_audience`). Enforce the 5-chip maximum.  
**Acceptance:** `forListing('seller')` returns at least one `listing_facts` chip. All chips remain compliant (no protected class references).  
**Depends on:** Task A (so the classifier can handle the questions users click).

---

### Build Task G — Design Channel-Agnostic Ask AI API Contract

**File:** `docs/specs/ASK_AI_CHANNEL_AGNOSTIC_CONTRACT.md` (documentation only, no code change)  
**What:** Define a single input/output schema that any channel adapter (website, Twilio SMS, Facebook Messenger, WhatsApp, future mobile apps) can wrap without touching pipeline internals. The schema must specify: input fields (`listing_type`, `listing_id`, `question`, `options`, `channel`, `session_id`), output fields (`success`, `status`, `answer_text`, `follow_up_questions`, `disclosures`, `attribution`, `question_type`, `error`), and the channel adapter contract (channel adapters transform to/from this schema; they never touch pipeline internals). This enables the same Ask AI brain to serve every delivery channel.  
**Acceptance:** Document is complete and reviewed. No code changes required in this task — implementation follows in a separate task.  
**Depends on:** Nothing — can ship as a planning document at any time.

---

## Section 8 — Risk Assessment and Launch Impact

### 8.1 Per-Task Risk Table

| Task | Risk Level | Blast Radius | Fair Housing Compliance | Rollout |
|---|---|---|---|---|
| **A — Classifier** | Low | Classifier only; no DB writes | Not affected — `prohibited` check is unchanged and remains first | Ship directly; add unit tests for all 15 audited questions |
| **B — Context Fields** | Low | Context assembly only; read-only DB reads | Not affected — fields are factual property data, not protected-class data | Ship directly; existing tests must still pass |
| **C — FAQ Wire** | Low | Context assembly only; read-only DB reads | Not affected | Ship directly |
| **D — Contract** | Low-Medium | Contract service + prompt builder receive new type | Low risk — `required_sources = ['listing']` means the contract fails gracefully when listing is absent | Ship after A+B+C pass tests |
| **E — OpenAI Normalizer** | Medium | Adds new OpenAI call; introduces latency for `unsupported` questions | Medium — OpenAI normalization layer must never bypass Layer 1; governance review required before enabling | Feature-flagged; default off; staged rollout |
| **F — Suggested Questions** | Low | Suggested questions UI only | Low — chips are pre-reviewed static strings | Ship after Task A |
| **G — Channel Contract** | None | Documentation only | Not applicable | Ship anytime |

### 8.2 Fair Housing Guardrail Assessment

The Layer 1 compliance blocker (`prohibited` keyword rules) is the platform's primary fair housing governance gate. The recommended architecture does not modify this layer in any way. Specifically:

- The `prohibited` check always runs first, before Layer 2 and Layer 3
- The `listing_facts` question type is entirely factual (bedrooms, price, utilities) — none of these are protected class signals
- The OpenAI intent normalization layer (Task E) must be engineered to call Layer 1 before normalization, so a paraphrased prohibited question cannot sneak through via the normalizer
- Response rules for `listing_facts` explicitly prohibit protected class references and speculation

**Recommendation:** Before enabling Task E (OpenAI normalization) in production, conduct a governance review of the normalization prompt and test against a suite of prohibited-question paraphrases.

### 8.3 Latency Impact

- **Tasks A–D:** Zero additional latency. All are deterministic in-process operations.
- **Task E (disabled):** Zero additional latency when the feature flag is `false`.
- **Task E (enabled):** Adds one OpenAI API call only for questions that are `unsupported` after Layer 2. Questions that match Layer 1 or Layer 2 are unaffected. The `AskAiOpenAiAdapterService` already handles retries via `OpenAiClientService`; the normalizer should use the same client with a shorter timeout and a lower `max_tokens` limit (~20 tokens) since it only returns a field key.

### 8.4 Recommended Rollout Sequence

1. Ship Tasks A, B, C, F together (all low-risk, no external dependencies)
2. Ship Task D after A+B+C pass integration tests
3. Ship Task G (documentation) at any time
4. Governance review of Task E prompt design
5. Enable Task E behind feature flag in staging only
6. Monitor for fair housing compliance, latency, and accuracy before production rollout

---

## Appendix A — Pipeline Stage Map

```
AskAiRunnerV2Service::run()
  │
  ├── AskAiQuestionClassifierService::classify()         [Stage 1 — Classify]
  │     └── Returns: { question_type, confidence, reason }
  │
  ├── AskAiInternalRunnerService::run()                  [Stages 2–4 — Context/Contract/Prompt]
  │     ├── AskAiContextBuilderService::buildForListing() [Stage 2 — Context]
  │     │     └── extractListingFields()   ← CURRENTLY ONLY 10 FIELDS
  │     │     └── buildPropertyIntelligence()
  │     │     └── buildLocationIntelligence()
  │     │     └── buildCompatibility()    ← ONLY WHEN pair options provided
  │     │
  │     ├── AskAiResponseContractService::buildContract() [Stage 3 — Contract]
  │     │     └── TYPE_CONTRACTS lookup   ← NO 'listing_facts' ENTRY
  │     │
  │     └── AskAiPromptBuilderService::buildPromptPackage() [Stage 4 — Prompt]
  │
  ├── AskAiOpenAiAdapterService::generate()              [Stage 5 — OpenAI]
  │     └── ONLY called when status === 'prompt_ready'
  │
  ├── AskAiFinalResponseBuilderService::build()           [Stage 6 — Final]
  │
  └── AskAiFollowUpQuestionService::forResult()           [Stage 7 — Follow-up]
```

## Appendix B — FAQ Infrastructure Map (Disconnected from Ask AI)

```
Seller/Landlord FAQ Entry Flow:
  offer-listing form → "AI Questions" tab
    → saves JSON → EAV meta key 'listing_ai_faq'

Tenant FAQ Entry Flow:
  tenant criteria form → saves JSON → native column 'listing_ai_faq'

Storage → AiFaqAnswer model → ai_faq_answers table
  (fields: listing_type, listing_id, question_key, question_group,
   intelligence_category, answer_text, answer_normalized)

Read path that WORKS (outside Ask AI):
  GET /ai-knowledge/{token}
    → AiKnowledgeController::show()
    → getAiFaqData()   ← reads listing_ai_faq
    → buildKnowledgeBase()  ← joins with config file
    → returns JSON knowledge base

Read path for Ask AI that DOES NOT EXIST:
  AskAiContextBuilderService::buildForListing()
    → [MISSING] buildFaqAnswers()
    → [MISSING] context['faq_answers']
```
