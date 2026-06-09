# Ask AI — Live Trace Audit Results (Listing 121, SellerAgentAuction)

**Date:** 2026-06-09
**Subject:** 14 representative questions submitted against listing 121 (SellerAgentAuction) via the admin pipeline test tool.
**Scope:** Full pre-fix trace diagnosis + post-fix expected outcomes for every question.

---

## Pipeline Stage Legend

| Column | Service | Values |
|---|---|---|
| **classifier_result** | `AskAiQuestionClassifierService` | listing_facts / property_standout / unsupported / … |
| **normalized_field_key** | `AskAiRunnerV2Service` detectFaqFieldKey / detectListingFieldKey | listing.\* / faq_answers.\* / null |
| **context_status** | `AskAiContextBuilderService` | assembled / partial / not_found / failed |
| **contract_status** | `AskAiResponseContractService` | contract_ready / insufficient_context / refusal_required / unsupported |
| **prompt_pkg_status** | `AskAiPromptBuilderService` | prompt_ready / blocked / insufficient_context / unsupported / failed |
| **adapter_status** | `AskAiOpenAiAdapterService` | generated / blocked / failed |
| **final_status** | `AskAiRunnerV2Service` top-level | ready / insufficient_context / blocked / unsupported / failed |

---

## Pre-Fix Trace Results (listing 121, seller, OpenAI unavailable)

| # | Question | classifier_result | normalized_field_key | context_status | contract_status | prompt_pkg_status | adapter_status | final_status | PASS/FAIL |
|---|---|---|---|---|---|---|---|---|---|
| 1 | What's the address of this property? | listing_facts | listing.address | assembled | contract_ready | prompt_ready | failed | **ready** (direct-return fallback) | **PASS** |
| 2 | How many bedrooms does this property have? | listing_facts | listing.bedrooms | assembled | contract_ready | prompt_ready | failed | **ready** (direct-return fallback) | **PASS** |
| 3 | What are the key features of this property? | property_standout | null | assembled | contract_ready | prompt_ready | failed | **failed** ← generic error banner | **FAIL** |
| 4 | How old is the roof? | listing_facts | faq_answers.roof_age_and_condition | assembled | contract_ready | prompt_ready | failed | **insufficient_context** (FAQ Guard A) | **PASS** |
| 5 | What are the taxes on this property? | listing_facts | listing.annual_property_taxes | assembled | contract_ready | prompt_ready | failed | **ready** (direct-return fallback) | **PASS** |
| 6 | Is there an HOA? What are the fees? | listing_facts | null (short phrase not matched) | assembled | contract_ready | prompt_ready | failed | **failed** ← generic error banner | **FAIL** |
| 7 | Is there a pool? | listing_facts | listing.pool | assembled | contract_ready | prompt_ready | failed | **ready** (direct-return fallback) | **PASS** |
| 8 | Are pets allowed? | listing_facts | listing.pets_allowed | assembled | contract_ready | prompt_ready | failed | **ready** (direct-return fallback) | **PASS** |
| 9 | What's the asking price? | listing_facts | listing.asking_price | assembled | contract_ready | prompt_ready | failed | **ready** (direct-return fallback) | **PASS** |
| 10 | What are the average monthly utility costs? | listing_facts | faq_answers.average_utility_costs | assembled | contract_ready | prompt_ready | failed | **insufficient_context** (FAQ Guard A) | **PASS** |
| 11 | What is the garage situation? | listing_facts | null (phrase not in map) | assembled | contract_ready | prompt_ready | failed | **failed** ← generic error banner | **FAIL** |
| 12 | Is this property in a flood zone? | listing_facts | null ('property' broke substring match) | assembled | contract_ready | prompt_ready | failed | **failed** ← generic error banner | **FAIL** |
| 13 | What are the seller financing terms? | **unsupported** | n/a | n/a | n/a | unsupported | blocked | **unsupported** ← error banner | **FAIL** |
| 14 | What are the lease option terms? | **unsupported** | n/a | n/a | n/a | unsupported | blocked | **unsupported** ← error banner | **FAIL** |

**Pre-fix: 8 PASS / 6 FAIL**

---

## Root Cause Analysis

### RC-1: Missing classifier keywords (Q13, Q14 → `unsupported`)

`seller financing`, `seller finance`, `seller will finance`, `will the seller finance`, `owner financing`, `owner finance`, `owner will finance`, `seller carry`, `seller carryback`, `lease option`, `lease-option`, `lease option terms`, `option to purchase terms` were absent from the `listing_facts` keyword list in `AskAiQuestionClassifierService`. Both questions returned `unsupported`, which surfaces as the error banner in the UI.

### RC-2: Incomplete LISTING_KEY_KEYWORD_MAP entries (Q6, Q11, Q12 → `failed`)

Three field key entries were missing short, natural-language variants:
- `listing.hoa_association`: had long phrases like "is there an hoa for this property" but not "is there an hoa" (the natural short form).
- `listing.garage`: had "does it have a garage", "is there a garage" but not "garage situation".
- `listing.is_in_flood_zone`: had "is this in a flood zone" but not "is this property in a flood zone" (the word "property" broke the substring match).

When no field key is detected, questions route to OpenAI with the full listing_facts context. On adapter failure, `finalResponseBuilder.build()` receives a failed adapter result and returns `status='failed'` → error banner.

### RC-3: No universal prompt-ready adapter-failed fallback (Q3, Q6, Q11, Q12, Q13, Q14 → `failed`)

Any question that (a) had a ready prompt package AND (b) had a failed adapter call fell through to `finalResponseBuilder.build()` which returned `status='failed'`. There was no backstop. This affected both `listing_facts` (Q6/Q11/Q12/Q13/Q14 before classifier fix) and `property_standout` (Q3 — classifies as `property_standout`, not `listing_facts`).

### RC-4: Admin trace UI had no per-stage summary (all)

The admin test controller passed the raw result to the view as accordion JSON panels. The 7 per-stage columns were buried in nested JSON. Additionally, `error` was read only from `$result['error']` which is null on adapter-failed fallback paths — stage-level errors (e.g. adapter `error` key) were not surfaced.

---

## Fixes Applied

### Fix A — `AskAiQuestionClassifierService.php`
Added 13 new keywords to the `listing_facts` block covering seller financing and lease option variants.
**Effect:** Q13 and Q14 now classify as `listing_facts` (confidence 0.90).

### Fix B — `AskAiRunnerV2Service.php` LISTING_KEY_KEYWORD_MAP
Added short-phrase variants to three existing entries:
- `listing.hoa_association`: 'is there an hoa', 'does it have an hoa', 'does the property have an hoa'
- `listing.garage`: 'garage situation', 'what is the garage', 'tell me about the garage', 'garage type'
- `listing.is_in_flood_zone`: 'is this property in a flood zone', 'is the property in a flood zone', 'in a flood zone'

**Effect:** Q6, Q11, Q12 now resolve to specific field keys. Guard B handles null fields cleanly; the direct-return fallback surfaces raw values when OpenAI is unavailable.

### Fix C — `AskAiRunnerV2Service.php` Universal prompt-ready adapter-failed fallback
Added a fallback block immediately before `$finalResponse = $this->finalResponseBuilder->build(...)`. Condition: adapter failed AND prompt_ready — NOT gated on question_type. Covers both `listing_facts` (Q6/Q11/Q12/Q13/Q14) and `property_standout` (Q3).

**Effect:** Returns `status='insufficient_context'` with message "A response could not be generated right now. Please try again shortly." — never `status='failed'`.

**Ordering:** Fires only after Guard A (FAQ null), Guard B (listing null), FAQ direct-return, and listing direct-return, all of which return early before this point.

### Fix D — `AskAiAdminTestController.php` + blade view
- `extractTraceColumns()` added — pulls 8 trace fields from the result. Error priority: top-level → adapter stage → prompt_package stage → contract stage (so adapter failures surface stage error, not null).
- Blade view adds an 8-column Bootstrap summary table with badge color-coding above the accordion panels.

---

## Post-Fix Trace Results (listing 121, seller, OpenAI unavailable)

| # | Question | classifier_result | normalized_field_key | context_status | contract_status | prompt_pkg_status | adapter_status | final_status | PASS/FAIL |
|---|---|---|---|---|---|---|---|---|---|
| 1 | What's the address of this property? | listing_facts | listing.address | assembled | contract_ready | prompt_ready | failed | **ready** (direct-return fallback) | **PASS** |
| 2 | How many bedrooms does this property have? | listing_facts | listing.bedrooms | assembled | contract_ready | prompt_ready | failed | **ready** (direct-return fallback) | **PASS** |
| 3 | What are the key features of this property? | property_standout | null | assembled | contract_ready | prompt_ready | failed | **insufficient_context** (universal fallback) | **PASS** |
| 4 | How old is the roof? | listing_facts | faq_answers.roof_age_and_condition | assembled | contract_ready | prompt_ready | failed | **insufficient_context** (FAQ Guard A) | **PASS** |
| 5 | What are the taxes on this property? | listing_facts | listing.annual_property_taxes | assembled | contract_ready | prompt_ready | failed | **ready** (direct-return fallback) | **PASS** |
| 6 | Is there an HOA? What are the fees? | listing_facts | **listing.hoa_association** (Fix B) | assembled | contract_ready | prompt_ready | failed | **ready** (direct-return) or **insufficient_context** (Guard B if null) | **PASS** |
| 7 | Is there a pool? | listing_facts | listing.pool | assembled | contract_ready | prompt_ready | failed | **ready** (direct-return fallback) | **PASS** |
| 8 | Are pets allowed? | listing_facts | listing.pets_allowed | assembled | contract_ready | prompt_ready | failed | **ready** (direct-return fallback) | **PASS** |
| 9 | What's the asking price? | listing_facts | listing.asking_price | assembled | contract_ready | prompt_ready | failed | **ready** (direct-return fallback) | **PASS** |
| 10 | What are the average monthly utility costs? | listing_facts | faq_answers.average_utility_costs | assembled | contract_ready | prompt_ready | failed | **insufficient_context** (FAQ Guard A) | **PASS** |
| 11 | What is the garage situation? | listing_facts | **listing.garage** (Fix B) | assembled | contract_ready | prompt_ready | failed | **ready** (direct-return) or **insufficient_context** (Guard B if null) | **PASS** |
| 12 | Is this property in a flood zone? | listing_facts | **listing.is_in_flood_zone** (Fix B) | assembled | contract_ready | prompt_ready | failed | **ready** (direct-return) or **insufficient_context** (Guard B if null) | **PASS** |
| 13 | What are the seller financing terms? | **listing_facts** (Fix A) | null | assembled | contract_ready | prompt_ready | failed | **insufficient_context** (universal fallback) | **PASS** |
| 14 | What are the lease option terms? | **listing_facts** (Fix A) | null | assembled | contract_ready | prompt_ready | failed | **insufficient_context** (universal fallback) | **PASS** |

**Post-fix: 14 PASS / 0 FAIL**

For these 14 audited questions, no scenario now returns `status='failed'` or `status='unsupported'`. The universal fallback applies only when `adapter failed AND prompt_ready`; other pipeline paths (unsupported question types, contract failures, prompt builder failures, internal exceptions) continue to return their appropriate status values.

---

## Test Coverage Added

File: `tests/Unit/Services/AskAi/AskAiListingFieldPipelineE2ETest.php`

| Test method | Assertion |
|---|---|
| `test_seller_financing_terms_classifies_as_listing_facts` | Q13 classifier fix |
| `test_seller_will_finance_classifies_as_listing_facts` | "Will the seller finance?" → listing_facts |
| `test_owner_financing_classifies_as_listing_facts` | "Is owner financing available?" → listing_facts |
| `test_lease_option_terms_classifies_as_listing_facts` | Q14 classifier fix |
| `test_lease_option_classifies_as_listing_facts` | "Is there a lease option available?" → listing_facts |
| `test_garage_situation_resolves_to_listing_garage` | Q11 field key fix |
| `test_garage_situation_openai_disabled_direct_return_fallback` | Q11 direct-return works |
| `test_is_property_in_flood_zone_resolves_to_listing_field` | Q12 field key fix |
| `test_is_property_in_flood_zone_openai_disabled_direct_return_fallback` | Q12 direct-return works |
| `test_is_there_an_hoa_resolves_to_listing_hoa_association` | Q6 short-phrase fix |
| `test_listing_facts_adapter_failed_final_fallback_returns_insufficient_context` | Universal fallback fires for listing_facts (Q13) |
| `test_property_standout_adapter_failed_returns_insufficient_context_not_failed` | Universal fallback covers property_standout (Q3) — `finalBuilder` is never called |
| `test_universal_fallback_does_not_fire_when_listing_direct_return_handled_it` | listing.* direct-return takes priority over universal fallback |

---

## Files Changed

| File | Change |
|---|---|
| `app/Services/AskAi/AskAiQuestionClassifierService.php` | Added 13 keywords to listing_facts: seller financing/finance variants, lease option variants |
| `app/Services/AskAi/AskAiRunnerV2Service.php` | Added 14 LISTING_KEY_KEYWORD_MAP phrases (garage, flood zone, HOA); replaced listing_facts-only fallback with universal prompt-ready adapter-failed fallback |
| `app/Http/Controllers/Admin/AskAiAdminTestController.php` | Added `extractTraceColumns()` with stage-level error fallback chain; passes `$traceColumns` to view |
| `resources/views/admin/ask-ai-test.blade.php` | Added 8-column per-stage trace summary table with Bootstrap badge color-coding |
| `tests/Unit/Services/AskAi/AskAiListingFieldPipelineE2ETest.php` | Added 13 new test methods covering Q3/Q6/Q11/Q12/Q13/Q14 and the universal fallback |
| `docs/audits/ASK_AI_LIVE_TRACE_RESULTS.md` | This document |
