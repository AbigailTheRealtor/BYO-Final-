# Ask AI Phase 2 — QA Acceptance Test Plan

**Date:** 2026-06-05  
**Scope:** Manual and automated acceptance tests for Phase 2 Tasks A–E, plus Phase 1 regression baseline.  
**Source:** Derived from task specs in `.local/tasks/ask-ai-phase2-task-*.md` and audit documents in `docs/audits/`.

---

## How to Use This Document

Each test scenario below defines:
- **Scenario ID** — unique reference (e.g., `A-01`)
- **Input** — exact or representative values to supply
- **Expected Output** — what must be observed
- **Pass Criterion** — unambiguous yes/no gate
- **Test Type** — `unit` (PHPUnit), `integration` (PHPUnit with DB), or `manual`

Tests marked `(automated)` correspond to test coverage that each implementation task is responsible for writing. Tests marked `(manual)` require a human tester with a seeded database.

---

## Section 1 — Task A: FAQ Answer Enrichment

**Scope:** `AskAiFaqEnrichmentService`, `buildFaqAnswers()` enriched shape, Artisan command `ask-ai:sync-faq-answers`.

### A-01 — Enrichment creates correct ai_faq_answers row (integration)

| | |
|---|---|
| **Scenario** | A seller listing has `listing_ai_faq` EAV meta with `roof_age_and_condition = "8-year-old architectural shingles"`. `AskAiFaqEnrichmentService` is called for that listing. |
| **Input** | `$service->sync('seller', $listingId)` with a listing whose `listing_ai_faq` JSON contains `"roof_age_and_condition": "8-year-old architectural shingles"`. |
| **Expected Output** | `AiFaqAnswer::where('listing_type','seller')->where('listing_id', $listingId)->where('question_key','roof_age_and_condition')->first()` returns a model with: `answer_text = "8-year-old architectural shingles"`, `question_group = "Property Condition & Maintenance"`, `intelligence_category` populated (non-null). |
| **Pass Criterion** | Row exists in `ai_faq_answers` with all three fields correct. |

### A-02 — Blank FAQ fields produce no rows (unit/integration)

| | |
|---|---|
| **Scenario** | A listing's `listing_ai_faq` JSON contains `"hvac_system_age": ""` (empty string) and `"water_heater_age_type": null`. |
| **Input** | `$service->sync('seller', $listingId)` |
| **Expected Output** | No `ai_faq_answers` row is created for `hvac_system_age` or `water_heater_age_type`. |
| **Pass Criterion** | `AiFaqAnswer::where('question_key','hvac_system_age')->count()` returns 0. |

### A-03 — All four listing types resolve labels correctly (unit)

| | |
|---|---|
| **Scenario** | For each of `seller`, `landlord`, `buyer`, `tenant`: provide a mock `listing_ai_faq` payload with one answered key for that role. Confirm the enrichment service resolves `question_label` and `question_group` from the correct config file. |
| **Input** | Seller: `roof_age_and_condition` → from `ai_faq_seller.php`. Landlord: `heating_cooling_system` → from `ai_faq_landlord.php`. Buyer: `buyer_motivation` → from `ai_faq_buyer.php`. Tenant: `faq_q1` → from `tenant_ai_faq.php`. |
| **Expected Output** | Each resolved label matches the `question_label` defined in the corresponding config file. No "key not found" errors. |
| **Pass Criterion** | All four role variants pass without exception. |

### A-04 — buildFaqAnswers() returns enriched shape (unit)

| | |
|---|---|
| **Scenario** | `buildFaqAnswers()` is called for a seller listing with an answered `roof_age_and_condition` key. |
| **Input** | Mocked listing with `listing_ai_faq` JSON containing one answered key. |
| **Expected Output** | The returned array entry for `roof_age_and_condition` is an object (array) with keys: `answer_text`, `question_label`, `question_group`, `intelligence_category`, `config_key`. None of these fields are null. |
| **Pass Criterion** | All five keys present and non-null in the enriched entry. |

### A-05 — Backward compatibility: legacy raw-string shape accepted (unit)

| | |
|---|---|
| **Scenario** | The contract service and prompt builder receive a `faq_answers` block that mixes legacy raw strings and enriched objects — simulating a partially-backfilled state. |
| **Input** | `faq_answers = [ "roof_age_and_condition" => "Roof replaced 2019", "hvac_system_age" => ["answer_text" => "5 years", "question_label" => "...", ...] ]` |
| **Expected Output** | No exception is thrown. The prompt builder safely extracts `answer_text` from enriched entries and uses the string directly for legacy entries. |
| **Pass Criterion** | Both entries are included in the final prompt package without error. |

### A-06 — Artisan command backfills all answered keys (integration)

| | |
|---|---|
| **Scenario** | A seeded seller listing has 5 answered FAQ keys. `php artisan ask-ai:sync-faq-answers seller {id}` is run. |
| **Input** | `php artisan ask-ai:sync-faq-answers seller 1` |
| **Expected Output** | Command exits with code 0. Console output contains a summary indicating 5 keys synced. `ai_faq_answers` table has 5 new rows for this listing. |
| **Pass Criterion** | Exit code 0; row count = 5; no rows created for empty keys. |

### A-07 — Prompt builder safety: internal fields excluded (unit)

| | |
|---|---|
| **Scenario** | The prompt builder receives enriched `faq_answers` entries and builds the prompt package. |
| **Input** | Enriched entry with keys: `id`, `listing_id`, `listing_type`, `created_at`, `updated_at`, `answer_text`, `question_label`, `question_group`, `intelligence_category`. |
| **Expected Output** | The prompt string sent to OpenAI contains `answer_text`, `question_label`, `question_group`, `intelligence_category`. It does NOT contain `id`, `listing_id`, `listing_type`, `created_at`, or `updated_at`. |
| **Pass Criterion** | None of the internal fields appear in the assembled prompt. |

---

## Section 2 — Task B: Extended Factual Field Coverage

**Scope:** Extended `extractListingFields()` in `AskAiContextBuilderService`; extended `allowed_context` in `AskAiResponseContractService` for the `listing_facts` contract.

### B-01 — Seller extended fields appear in context (integration)

| | |
|---|---|
| **Scenario** | A seller listing has `address`, `description`, `buy_now_price`, `is_in_flood_zone = 'Yes'`, `flood_zone_code = 'AE'`, `hoa_association`, `condo_fee`, `pet_restrictions`, `rental_restrictions`, `lease_terms`, `closing_date`, `auction_length` populated. |
| **Input** | `contextBuilder->buildForListing('seller', $listingId, [])` |
| **Expected Output** | `context['listing']` includes non-null values for each of those fields. `context['listing']['disclosure_flags']['flood_zone']` equals `true`. |
| **Pass Criterion** | All listed fields present; `disclosure_flags.flood_zone = true`. |

### B-02 — Buyer extended fields appear in context (integration)

| | |
|---|---|
| **Scenario** | A buyer listing has `max_price`, `loan_pre_approved`, `financing_id` (FK to a financing label), `inspection_period`, `closing_days`, `contingencies`, `pets_detail`, `pets_breed` populated. |
| **Input** | `contextBuilder->buildForListing('buyer', $listingId, [])` |
| **Expected Output** | `context['listing']` includes all listed fields. `financing_type` is resolved to a human-readable label (not the raw FK integer). |
| **Pass Criterion** | All listed fields present; `financing_type` is a string label, not an integer. |

### B-03 — Landlord EAV fields appear in context (integration)

| | |
|---|---|
| **Scenario** | A landlord listing has `property_zip`, `condition_prop`, `pet_policy`, `pet_deposit_fee_rent`, `available_date`, `parking_terms`, `smoking_policy`, `subletting_policy`, `utilities`, `has_hoa`, `association_name`, `association_fee_amount` populated via EAV meta. |
| **Input** | `contextBuilder->buildForListing('landlord', $listingId, [])` |
| **Expected Output** | `context['listing']` includes all listed fields with non-null values. JSON fields (`property_items`, `appliances`, `pet_species_allowed`, `association_amenities`) are decoded — not raw JSON strings. |
| **Pass Criterion** | All listed fields present; JSON fields decoded. |

### B-04 — Tenant EAV fields appear in context (integration)

| | |
|---|---|
| **Scenario** | A tenant listing has `maximum_budget`, `parking_needed`, `pet_information`, `utility_preference`, `current_status`, `number_of_unit` populated. JSON fields `property_items`, `appliances`, `tenant_pays` populated. |
| **Input** | `contextBuilder->buildForListing('tenant', $listingId, [])` |
| **Expected Output** | `context['listing']` includes all listed fields. JSON fields decoded. |
| **Pass Criterion** | All listed fields present; JSON fields decoded. |

### B-05 — Excluded fields are absent from context (unit/integration)

| | |
|---|---|
| **Scenario** | A seller listing is built into context. The listing has `user_id`, `seller_id`, `phone_number`, `email`, `brokerage`, `ai_share_token`, `hoa_manager_contact`, `reserve_price` populated in the database. |
| **Input** | `contextBuilder->buildForListing('seller', $listingId, [])` |
| **Expected Output** | None of those field names appear in `context['listing']` or any other context block. |
| **Pass Criterion** | All seven excluded fields absent from all context keys. |

### B-06 — Flood-zone fields carry disclosure flag (unit/integration)

| | |
|---|---|
| **Scenario** | A seller listing with `is_in_flood_zone = 'Yes'` and `flood_zone_code = 'AE'`. |
| **Input** | `contextBuilder->buildForListing('seller', $listingId, [])` |
| **Expected Output** | `context['listing']['is_in_flood_zone']` is `'Yes'`. `context['listing']['flood_zone_code']` is `'AE'`. `context['listing']['disclosure_flags']['flood_zone']` is `true`. |
| **Pass Criterion** | Both fields present as plain scalars; `disclosure_flags.flood_zone` present and `true`. |

### B-07 — New allowed_context paths pass through prompt builder (unit)

| | |
|---|---|
| **Scenario** | The prompt builder filters context for a `listing_facts` contract. It receives a context that includes one of the newly added Task B fields (e.g., `rental_restrictions`, `available_date`). |
| **Input** | Context with `listing.rental_restrictions = "No short-term rentals"`. `listing_facts` contract. |
| **Expected Output** | `rental_restrictions` appears in the assembled prompt. It was not filtered out by `filterAllowedContext()`. |
| **Pass Criterion** | New field appears in prompt string. |

---

## Section 3 — Task C: OpenAI Intent Normalization

**Scope:** `AskAiIntentNormalizerService`, integration into `AskAiRunnerV2Service`, feature flag behavior, Layer 1 protection.

### C-01 — 5 ambiguous paraphrases normalize to correct field keys (unit)

| | |
|---|---|
| **Scenario** | The normalizer is called with five sample paraphrases that the deterministic classifier cannot match but which map to known context paths. |
| **Input (with mocked OpenAI returning expected key):** | 1. "Does the A/C work well?" → `faq_answers.heating_cooling_system` 2. "Tell me about the mechanical systems" → `faq_answers.hvac_system_age` 3. "Any concerns about water?" → `faq_answers.flood_damage_history` 4. "What's included in monthly costs?" → `listing.utilities_included` 5. "How's the laundry situation?" → `faq_answers.laundry_situation` |
| **Expected Output** | Each call returns the correct canonical path string. |
| **Pass Criterion** | All five return the expected path without exception. |

### C-02 — Prohibited questions never reach the normalizer (unit)

| | |
|---|---|
| **Scenario** | A question containing a protected-class term (e.g., "What school district is this in?" — school district is a prohibited keyword) is submitted. |
| **Input** | `AskAiRunnerV2Service::run('seller', $id, 'What school district is this in?', [])` with the feature flag enabled. |
| **Expected Output** | The classifier returns `question_type = 'prohibited'`. The runner returns `status = 'blocked'`. `AskAiIntentNormalizerService::normalize()` is never called. |
| **Pass Criterion** | Normalizer call count is zero (mock assertion); response status is `blocked`. |

### C-03 — Flag-off produces identical output to Phase 1 baseline (unit)

| | |
|---|---|
| **Scenario** | An ambiguous question is submitted with `config('ask_ai.enable_openai_intent_normalization') = false`. |
| **Input** | `run('seller', $id, 'Does the A/C work well?', [])` with flag off. |
| **Expected Output** | Response is identical to what Phase 1 returns for `unsupported` — `status = 'unsupported'`, no OpenAI call by the normalizer, no crash. |
| **Pass Criterion** | Status is `unsupported`; normalizer is never called (mock assertion). |

### C-04 — OpenAI timeout returns null gracefully (unit)

| | |
|---|---|
| **Scenario** | The normalizer calls OpenAI but OpenAI times out or throws a connection exception. |
| **Input** | Mock `OpenAiClientService` to throw a `ConnectException` or `TimeoutException`. Flag is on. Question is `'How old is the roof?'` (a question the classifier does not match). |
| **Expected Output** | `AskAiIntentNormalizerService::normalize()` returns `null`. The runner continues on the `unsupported` path. No exception surfaces to the caller. |
| **Pass Criterion** | No exception thrown; response status is `unsupported` (not `failed`). |

### C-05 — Normalized result re-enters listing_facts contract (integration)

| | |
|---|---|
| **Scenario** | Flag is on. A question maps to a field key via normalization. The normalizer returns `listing.hoa_fee`. The listing has `hoa_fee` populated in context. |
| **Input** | `run('seller', $id, 'Tell me about the fees for the community', [])` with mocked normalizer returning `listing.hoa_fee`. |
| **Expected Output** | Response follows the `listing_facts` contract path. `question_type = 'listing_facts'`. `normalized_field_key = 'listing.hoa_fee'` is present in the debug/log metadata (not necessarily in the API response). |
| **Pass Criterion** | Response `question_type` is `listing_facts`; answer text references HOA fee. |

### C-06 — Config entry has governance comment (manual)

| | |
|---|---|
| **Scenario** | Review `config/ask_ai.php`. |
| **Expected Output** | Key `enable_openai_intent_normalization` exists with value `false`. An inline comment above the key states that governance review is required before enabling in production. |
| **Pass Criterion** | Key present; default `false`; comment present. |

---

## Section 4 — Task D: Context-Aware Suggested Questions

**Scope:** `AskAiSuggestedQuestionsService::forListing()` signature, context-aware chip filtering, expanded chip pools, 5-chip cap, protected-class exclusion.

### D-01 — FAQ-driven chip appears only when FAQ key has an answer (unit)

| | |
|---|---|
| **Scenario** | "How old is the roof?" chip has `required_context_path = faq_answers.roof_age_and_condition`. Context A has this key populated; context B does not. |
| **Input A** | `forListing('seller', ['listing' => [...], 'faq_answers' => ['roof_age_and_condition' => ['answer_text' => '...']]])` |
| **Input B** | `forListing('seller', ['listing' => [...], 'faq_answers' => []])` |
| **Expected Output A** | "How old is the roof?" chip is in the returned list. |
| **Expected Output B** | "How old is the roof?" chip is NOT in the returned list. |
| **Pass Criterion** | Chip present in A; absent in B. |

### D-02 — New pool chips are present for each role (unit)

| | |
|---|---|
| **Scenario** | Confirm Task D's new chips exist in the pool for each role: Seller: "What's the address?", "Are there rental restrictions?", "What are the lease terms?". Landlord: "What's the pet policy?", "When is it available?", "What utilities are included?", "Is smoking allowed?". Buyer: "What's the max budget?", "What financing is accepted?". Tenant: "What's the max rent?", "What appliances are required?" |
| **Input** | `forListing($role, [])` for each role (empty context returns full static pool). |
| **Expected Output** | Each of the listed new chips is present in the full static pool for its role. |
| **Pass Criterion** | All new chips present in their respective role pools. |

### D-03 — 5-chip cap is respected with context filtering (unit)

| | |
|---|---|
| **Scenario** | A seller listing has 10 fields populated in context, giving 10 eligible chips. |
| **Input** | `forListing('seller', $richContext)` where `$richContext` has 10 relevant fields. |
| **Expected Output** | The returned chip list has exactly 5 items. |
| **Pass Criterion** | `count($chips) === 5`. |

### D-04 — Empty context returns full static pool (unit)

| | |
|---|---|
| **Scenario** | `forListing()` is called with an empty context array (backward-compatible default). |
| **Input** | `forListing('seller', [])` and `forListing('seller')` (no second argument). |
| **Expected Output** | Both calls return the same full static listing_facts chip set (up to 5); no filtering is applied; no exception thrown. |
| **Pass Criterion** | Both calls return the same result; no exception; count ≤ 5. |

### D-05 — No protected-class strings in any chip (manual)

| | |
|---|---|
| **Scenario** | Review all `listing_facts` chips for all four roles in `AskAiSuggestedQuestionsService::POOLS`. |
| **Expected Output** | No chip question text references race, color, national origin, religion, sex, familial status, disability, or neighborhood demographics. School district references are limited to buyer-stated criteria ("What school districts does this buyer want?") not quality judgments. |
| **Pass Criterion** | Zero chips with protected-class language. |

### D-06 — Context-aware filtering for listing.* paths (unit)

| | |
|---|---|
| **Scenario** | A chip with `required_context_path = listing.available_date` is tested. Context A has `listing.available_date` non-null; context B has it null. |
| **Input A** | Context where `$context['listing']['available_date'] = '2026-08-01'`. |
| **Input B** | Context where `$context['listing']['available_date'] = null`. |
| **Expected Output A** | "When is it available?" chip is included. |
| **Expected Output B** | "When is it available?" chip is excluded. |
| **Pass Criterion** | Present in A; absent in B. |

---

## Section 5 — Task E: Channel-Agnostic API

**Scope:** `AskAiApiController`, route registration (`POST /ask-ai/ask`, `POST /api/ask-ai/ask`), rate limiting, canonical status mapping, contract spec document.

### E-01 — Canonical output contract returned on valid web-route request (integration)

| | |
|---|---|
| **Scenario** | Authenticated web session posts a valid factual question about a seller listing with bedrooms populated. |
| **Input** | `POST /ask-ai/ask` with body: `{ "listing_type": "seller", "listing_id": 1, "question": "How many bedrooms?", "channel": "web" }`. Session-authenticated. |
| **Expected Output** | HTTP 200. Response has keys: `success = true`, `status = "answered"`, `answer_text` (non-empty string), `question_type = "listing_facts"`, `follow_up_questions` (array), `disclosures` (array), `attribution` (object), `error = null`. |
| **Pass Criterion** | All seven canonical output keys present; `status = "answered"`. |

### E-02 — Valid API-route request returns answered (integration)

| | |
|---|---|
| **Scenario** | Sanctum-authenticated request to the API route. |
| **Input** | `POST /api/ask-ai/ask` with Bearer token and body: `{ "listing_type": "seller", "listing_id": 1, "question": "How many bedrooms?", "channel": "crm" }`. |
| **Expected Output** | HTTP 200. `status = "answered"`. Same canonical output shape as E-01. |
| **Pass Criterion** | HTTP 200; `status = "answered"`. |

### E-03 — Missing listing_id returns HTTP 422 (unit/integration)

| | |
|---|---|
| **Scenario** | Request is posted without the `listing_id` field. |
| **Input** | `POST /ask-ai/ask` body: `{ "listing_type": "seller", "question": "How many bedrooms?", "channel": "web" }` |
| **Expected Output** | HTTP 422. Response contains a structured validation error for `listing_id`. |
| **Pass Criterion** | HTTP 422; error references `listing_id`. |

### E-04 — Rate limit exceeded returns HTTP 429 with Retry-After (integration)

| | |
|---|---|
| **Scenario** | The same authenticated user sends 21 requests to `POST /ask-ai/ask` within one minute, exceeding the `config('ask_ai.rate_limit_per_minute', 20)` threshold. |
| **Input** | 21 sequential valid requests from the same user/IP. |
| **Expected Output** | The 21st request returns HTTP 429. The response includes a `Retry-After` header. |
| **Pass Criterion** | 21st request: HTTP 429; `Retry-After` header present. |

### E-05 — Unauthenticated request to /api/ask-ai/ask returns HTTP 401 (unit/integration)

| | |
|---|---|
| **Scenario** | Request is posted to the API route without a Bearer token or session. |
| **Input** | `POST /api/ask-ai/ask` with no `Authorization` header. |
| **Expected Output** | HTTP 401. |
| **Pass Criterion** | HTTP 401. |

### E-06 — All five runner output statuses map correctly to API statuses (unit)

| | |
|---|---|
| **Scenario** | The controller receives mocked `AskAiRunnerV2Service` outputs with each of the five runner-level status values and applies the status mapping. **Note:** `prompt_ready` and `refusal_required` are intermediate prompt-package statuses consumed inside the pipeline; they never appear in the runner's return value and must never reach the API controller. The five valid runner output statuses are exactly: `ready`, `insufficient_context`, `blocked`, `unsupported`, `failed`. |
| **Input** | Mock runner returning each of `status = 'ready'`, `'insufficient_context'`, `'blocked'`, `'unsupported'`, `'failed'` in separate test cases. |
| **Expected Output** | `'ready'` → API `status = 'answered'`; `'insufficient_context'` → `'insufficient_context'`; `'blocked'` → `'blocked'`; `'unsupported'` → `'unsupported'`; `'failed'` → `'failed'`. |
| **Pass Criterion** | All five runner-to-API mappings correct; `ready → answered` is the only transformation; all other four statuses pass through unchanged; if `prompt_ready` or `refusal_required` ever appear in the runner output the test should fail (they indicate a pipeline contract violation). |

### E-07 — Web route parity with existing pipeline (integration)

| | |
|---|---|
| **Scenario** | The same question is submitted to both the existing `POST /ask-ai/listing-question` route and the new `POST /ask-ai/ask` route for the same listing. |
| **Input** | Identical `listing_type`, `listing_id`, and `question` on both routes. Authenticated session. |
| **Expected Output** | Both responses have: `status` identical; `question_type` identical; `source_attribution` identical; `answer_text` derived from the same pipeline result (same runner output fed to both responses). The existing route's behavior is unchanged — it returns the same result it returned before Task E. |
| **Pass Criterion** | `status` identical; `question_type` identical; `source_attribution` identical; `answer_text` produced from the same runner output object (assert via test that a single `AskAiRunnerV2Service::run()` call underlies both, or mock the runner to return a fixed result and confirm both routes surface it without modification). |

### E-08 — Contract spec document exists and is complete (manual)

| | |
|---|---|
| **Scenario** | Review `docs/specs/ASK_AI_CHANNEL_AGNOSTIC_CONTRACT.md`. |
| **Expected Output** | Document contains: (a) canonical input contract with all six fields (`listing_type`, `listing_id`, `question`, `options`, `channel`, `session_id`); (b) canonical output contract with all seven fields; (c) status mapping table (ready → answered, plus 4 pass-throughs); (d) channel adapter responsibilities including length limits (SMS ≤160 chars, Messenger ≤2,000 chars); (e) versioning strategy; (f) source hierarchy. |
| **Pass Criterion** | All six content areas present. |

---

## Section 6 — Phase 1 Regression Baseline

**Source:** Routing audit Section 1.1 — exact classification results for the 15 audited questions.  
**Purpose:** This suite must pass after every Phase 2 task to confirm no regressions.

All 15 questions must produce `question_type = 'listing_facts'` after Phase 1. Phase 2 must not change that.

| # | Question | Expected question_type | Previously Wrong |
|---|---|---|---|
| 1 | "How many bedrooms does this have?" | `listing_facts` | Was `buyer_tenant_match` |
| 2 | "How many bathrooms?" | `listing_facts` | Was `buyer_tenant_match` |
| 3 | "What is the monthly rent?" | `listing_facts` | Was `unsupported` |
| 4 | "What is the asking price?" | `listing_facts` | Was `unsupported` |
| 5 | "Is there an HOA? What is the fee?" | `listing_facts` | Was `unsupported` |
| 6 | "Are pets allowed?" | `listing_facts` | Was `unsupported` |
| 7 | "What is the lease length?" | `listing_facts` | Was `buyer_tenant_match` |
| 8 | "What appliances are included?" | `listing_facts` | Was `unsupported` |
| 9 | "What utilities are included?" | `listing_facts` | Was `unsupported` |
| 10 | "How old is the roof?" | `listing_facts` | Was `unsupported` |
| 11 | "Is there a pool?" | `listing_facts` | Was `unsupported` |
| 12 | "What are the parking options?" | `listing_facts` | Was `buyer_tenant_match` |
| 13 | "How do I schedule a showing?" | `listing_facts` | Was `unsupported` |
| 14 | "What is the average monthly electric bill?" | `listing_facts` | Was `unsupported` |
| 15 | "Is the property move-in ready?" | `listing_facts` | Was `unsupported` |

### R-01 — All 15 audited questions classify as listing_facts (automated)

| | |
|---|---|
| **Input** | Each of the 15 question strings above, passed to `AskAiQuestionClassifierService::classify()`. |
| **Expected Output** | All 15 return `['question_type' => 'listing_facts', ...]`. |
| **Pass Criterion** | Zero regressions across all 15. |

### R-02 — Prohibited questions still blocked (automated)

| | |
|---|---|
| **Scenario** | Questions containing protected-class keywords that Phase 1 correctly blocked remain blocked after Phase 2. |
| **Input** | At least 3 questions from the existing `prohibited` test suite (e.g., school district, neighborhood racial composition, religious community). |
| **Expected Output** | All return `question_type = 'prohibited'`; pipeline returns `status = 'blocked'`. |
| **Pass Criterion** | All blocked; none reclassified as `listing_facts`. |

### R-03 — Intelligence question types unchanged (automated)

| | |
|---|---|
| **Scenario** | Questions that correctly routed to intelligence types in Phase 1 continue to do so after Phase 2. |
| **Input** | At least 3 questions from each of: `property_standout`, `suited_audience`, `buyer_tenant_match` (pair-context), `educational`, `marketing_angles`. |
| **Expected Output** | Each question returns the same `question_type` it returned before Phase 2 tasks began. |
| **Pass Criterion** | No intelligence-type question is reclassified as `listing_facts` or `unsupported`. |

### R-04 — buyer_tenant_match still works when pair context is provided (automated)

| | |
|---|---|
| **Scenario** | A `buyer_tenant_match` question is submitted with all four pair options present (`demand_listing_type`, `demand_listing_id`, `supply_listing_type`, `supply_listing_id`). |
| **Input** | Question: `"How well does this tenant's criteria match this landlord listing?"`. Options include all four pair keys. |
| **Expected Output** | `question_type = 'buyer_tenant_match'`. Contract receives the `compatibility` source. Pipeline proceeds to OpenAI if compatibility data is present. |
| **Pass Criterion** | `buyer_tenant_match` path still functional end-to-end when pair context is supplied. |

### R-05 — missing_data questions unchanged (automated)

| | |
|---|---|
| **Scenario** | Questions that ask about what information is absent from a listing must continue to classify as `missing_data` after Phase 2 field expansion. Phase 2 adds many new field names to keyword maps; this test guards against accidental keyword collisions that would reclassify a `missing_data` question as `listing_facts`. |
| **Input** | At least 3 representative `missing_data` questions: `"What information is missing from this listing?"`, `"What should the seller add to this listing?"`, `"What additional details would help buyers evaluate this property?"`. |
| **Expected Output** | All three return `question_type = 'missing_data'`. None are reclassified as `listing_facts`, `unsupported`, or any other type. |
| **Pass Criterion** | All three classify as `missing_data`; zero reclassifications. |

---

## Section 7 — QA Execution Checklist

Use this checklist when signing off a Phase 2 release.

### Pre-merge automated gates

```bash
php artisan test --filter AskAi
php artisan test --filter AskAiFaqEnrichmentServiceTest
php artisan test --filter AskAiIntentNormalizerServiceTest
php artisan test --filter AskAiSuggestedQuestionsServiceTest
php artisan test --filter AskAiApiControllerTest
```

All must pass with zero failures. The two known skips for `tenant_criteria_auctions` missing table are acceptable.

### Manual verification steps (staging environment)

1. Seed a seller listing with 5 FAQ answers answered in the "AI Questions" tab.
2. Run `php artisan ask-ai:sync-faq-answers seller {id}` — confirm row count in `ai_faq_answers`.
3. Submit each of the 15 regression questions via the listing page "Ask AI" widget — confirm all return a factual answer.
4. Submit `"How do I schedule a showing?"` for a listing where `showing_instructions` is empty — confirm response says data is not available (not a hallucinated answer).
5. Submit a protected-class question (e.g., "Are there any families with children in this neighborhood?") — confirm `status = blocked` and no answer is returned.
6. Verify `POST /api/ask-ai/ask` returns HTTP 401 when called without a Bearer token.
7. Verify `POST /ask-ai/ask` returns HTTP 422 when `listing_id` is omitted.
8. Verify suggested question chips on a listing page update based on which fields are populated (D-01 scenario).
9. Confirm `config('ask_ai.enable_openai_intent_normalization')` is `false` in the production config dump.
10. Review `docs/specs/ASK_AI_CHANNEL_AGNOSTIC_CONTRACT.md` exists and covers all six content areas (E-08).
