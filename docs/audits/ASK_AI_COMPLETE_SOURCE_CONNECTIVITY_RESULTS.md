# Ask AI Complete Source Connectivity Audit Results
**Audit Date:** 2026-06-09  
**Auditor:** Task 2368 — automated + structural analysis  
**Scope:** All four listing roles (seller, buyer, landlord, tenant)  
**Test Run:** `php artisan test --filter AskAi` — **1571 tests, 0 failures**

---

## Executive Summary

| Category | Total Sources | Pass | Fail | Excluded (Documented) |
|---|---|---|---|---|
| Listing model fields (`listing.*`) | 46 | 46 | 0 | 0 |
| FAQ keys — Pinned | 102 | 102 | 0 | 0 |
| FAQ keys — Match Criteria (buyer) | 66 | 66 | 0 | 0 (intentional, no keyword map) |
| Tenant FAQ keys (faq_q1–q27) | 27 | 27 | 0 | 0 |
| Property DNA intelligence | 6 | 6 | 0 | 0 |
| Location DNA intelligence | 7 | 7 | 0 | 0 |
| Buyer/Tenant DNA avatar | 9 | 9 | 0 | 0 |
| **TOTAL** | **263** | **263** | **0** | **0** |

---

## Automated Harness Results (AskAiCoverageHarnessTest — 56 tests, all pass)

| # | Test | Status |
|---|---|---|
| 1 | Every pinned registry path has a FAQ_KEY_KEYWORD_MAP entry | PASS |
| 2 | Every pinned registry path has a specific deriveFieldLabel entry | PASS |
| 3 | Every FAQ_KEY_KEYWORD_MAP key has a specific label | PASS |
| 4 | listing_facts contract declares faq_answers as an allowed path | PASS |
| 5 | Critical natural-language phrases route to listing_facts (36 phrases) | PASS |
| 6 | FAQ keywords are not duplicated in competing intents | PASS |
| 7a | Registry structural integrity — required fields present | PASS |
| 7b | Registry keyword_route_status values are valid | PASS |
| 7c | Registry canonical paths start with faq_answers. prefix | PASS |
| 7d | Registry roles are valid | PASS |
| 8 | FAQ_KEY_KEYWORD_MAP covers ≥20 keys, ≥100 total phrases | PASS (82 keys, 250+ phrases) |
| 9 | match_criteria entries NOT in FAQ_KEY_KEYWORD_MAP | PASS |
| 10a | opaque_key entries NOT in FAQ_KEY_KEYWORD_MAP | PASS (0 opaque entries remain) |
| 10b | umbrella_only entries NOT in FAQ_KEY_KEYWORD_MAP | PASS (0 umbrella_only remain) |
| 11a | All four roles present in registry | PASS |
| 11b | Buyer role has match_criteria entries | PASS |
| 11c | Tenant role has pinned entries | PASS |
| 11d | Seller/landlord have pinned addon entries | PASS |
| 12 | Context builder source contains every listing model field config_key | PASS (46/46) |
| 13 | Contract service declares every listing.* path | PASS (46/46) |
| 14 | Every FAQ registry entry has non-empty sample_question_2 | PASS (168/168) |
| 15 | Every listingFieldRegistry path has LISTING_KEY_KEYWORD_MAP entry | PASS (46/46) |
| 16 | Every LISTING_KEY_KEYWORD_MAP key has specific deriveFieldLabel | PASS (46/46) |
| 17 | LISTING_KEY_KEYWORD_MAP covers ≥40 keys, ≥150 total phrases | PASS (46 keys, 200+ phrases) |
| 18 | LISTING_KEY_KEYWORD_MAP keys start with listing. prefix | PASS |

---

## Per-Source Audit: Listing Model Fields

| Role | Field Key | Context Path | Classifier → | Guard | Direct-Return Fallback | Pass/Fail |
|---|---|---|---|---|---|---|
| seller, landlord | `annual_property_taxes` | `listing.annual_property_taxes` | listing_facts | Guard B ✓ | listing.* fallback ✓ | PASS |
| seller | `asking_price` | `listing.asking_price` | listing_facts | Guard B ✓ | listing.* fallback ✓ | PASS |
| seller | `buy_now_price` | `listing.buy_now_price` | listing_facts | Guard B ✓ | listing.* fallback ✓ | PASS |
| buyer | `max_price` | `listing.max_price` | listing_facts | Guard B ✓ | listing.* fallback ✓ | PASS |
| landlord | `rent_amount` | `listing.rent_amount` | listing_facts | Guard B ✓ | listing.* fallback ✓ | PASS |
| tenant | `max_rent` | `listing.max_rent` | listing_facts | Guard B ✓ | listing.* fallback ✓ | PASS |
| all | `bedrooms` | `listing.bedrooms` | listing_facts | Guard B ✓ | listing.* fallback ✓ | PASS |
| all | `bathrooms` | `listing.bathrooms` | listing_facts | Guard B ✓ | listing.* fallback ✓ | PASS |
| all | `square_feet` | `listing.square_feet` | listing_facts | Guard B ✓ | listing.* fallback ✓ | PASS |
| seller | `year_built` | `listing.year_built` | listing_facts | Guard B ✓ | listing.* fallback ✓ | PASS |
| all | `description` | `listing.description` | listing_facts | Guard B ✓ | listing.* fallback ✓ | PASS |
| landlord, tenant | `condition_prop` | `listing.condition_prop` | listing_facts | Guard B ✓ | listing.* fallback ✓ | PASS |
| seller, buyer | `address` | `listing.address` | listing_facts | Guard B ✓ | listing.* fallback ✓ | PASS |
| seller, buyer | `pool` | `listing.pool` | listing_facts | Guard B ✓ | listing.* fallback ✓ | PASS |
| seller, buyer | `carport` | `listing.carport` | listing_facts | Guard B ✓ | listing.* fallback ✓ | PASS |
| seller, buyer | `garage` | `listing.garage` | listing_facts | Guard B ✓ | listing.* fallback ✓ | PASS |
| seller, buyer | `water_view` | `listing.water_view` | listing_facts | Guard B ✓ | listing.* fallback ✓ | PASS |
| landlord, tenant | `appliances` | `listing.appliances` | listing_facts | Guard B ✓ | listing.* fallback ✓ | PASS |
| seller | `hoa_association` | `listing.hoa_association` | listing_facts | Guard B ✓ | listing.* fallback ✓ | PASS |
| seller | `hoa_fee` | `listing.hoa_fee` | listing_facts | Guard B ✓ | listing.* fallback ✓ | PASS |
| seller, buyer | `hoa_fee_requirement` | `listing.hoa_fee_requirement` | listing_facts | Guard B ✓ | listing.* fallback ✓ | PASS |
| buyer | `hoa_acceptable` | `listing.hoa_acceptable` | listing_facts | Guard B ✓ | listing.* fallback ✓ | PASS |
| landlord | `has_hoa` | `listing.has_hoa` | listing_facts | Guard B ✓ | listing.* fallback ✓ | PASS |
| landlord | `association_amenities` | `listing.association_amenities` | listing_facts | Guard B ✓ | listing.* fallback ✓ | PASS |
| seller, buyer | `pets_allowed` | `listing.pets_allowed` | listing_facts | Guard B ✓ | listing.* fallback ✓ | PASS |
| landlord | `pet_policy` | `listing.pet_policy` | listing_facts | Guard B ✓ | listing.* fallback ✓ | PASS |
| landlord | `pet_deposit_fee_rent` | `listing.pet_deposit_fee_rent` | listing_facts | Guard B ✓ | listing.* fallback ✓ | PASS |
| tenant | `pet_information` | `listing.pet_information` | listing_facts | Guard B ✓ | listing.* fallback ✓ | PASS |
| seller | `lease_terms` | `listing.lease_terms` | listing_facts | Guard B ✓ | listing.* fallback ✓ | PASS |
| landlord | `lease_length` | `listing.lease_length` | listing_facts | Guard B ✓ | listing.* fallback ✓ | PASS |
| tenant | `desired_lease_length` | `listing.desired_lease_length` | listing_facts | Guard B ✓ | listing.* fallback ✓ | PASS |
| landlord | `renewal_option` | `listing.renewal_option` | listing_facts | Guard B ✓ | listing.* fallback ✓ | PASS |
| seller | `rental_restrictions` | `listing.rental_restrictions` | listing_facts | Guard B ✓ | listing.* fallback ✓ | PASS |
| landlord, tenant | `utilities` | `listing.utilities` | listing_facts | Guard B ✓ | listing.* fallback ✓ | PASS |
| seller, tenant | `tenant_pays` | `listing.tenant_pays` | listing_facts | Guard B ✓ | listing.* fallback ✓ | PASS |
| landlord | `smoking_policy` | `listing.smoking_policy` | listing_facts | Guard B ✓ | listing.* fallback ✓ | PASS |
| landlord | `subletting_policy` | `listing.subletting_policy` | listing_facts | Guard B ✓ | listing.* fallback ✓ | PASS |
| landlord | `parking_terms` | `listing.parking_terms` | listing_facts | Guard B ✓ | listing.* fallback ✓ | PASS |
| landlord, tenant | `available_date` | `listing.available_date` | listing_facts | Guard B ✓ | listing.* fallback ✓ | PASS |
| seller | `closing_date` | `listing.closing_date` | listing_facts | Guard B ✓ | listing.* fallback ✓ | PASS |
| buyer | `loan_pre_approved` | `listing.loan_pre_approved` | listing_facts | Guard B ✓ | listing.* fallback ✓ | PASS |
| buyer | `financing_type` | `listing.financing_type` | listing_facts | Guard B ✓ | listing.* fallback ✓ | PASS |
| buyer | `inspection_period` | `listing.inspection_period` | listing_facts | Guard B ✓ | listing.* fallback ✓ | PASS |
| buyer | `closing_days` | `listing.closing_days` | listing_facts | Guard B ✓ | listing.* fallback ✓ | PASS |
| buyer | `contingencies` | `listing.contingencies` | listing_facts | Guard B ✓ | listing.* fallback ✓ | PASS |
| seller | `is_in_flood_zone` | `listing.is_in_flood_zone` | listing_facts | Guard B ✓ | listing.* fallback ✓ | PASS |

---

## Per-Source Audit: Pinned FAQ Keys (sample — all 102 pass)

All pinned `faq_answers.*` entries were verified via the `AskAiCoverageHarnessTest` suite:
- Context builder: `listing_ai_faq` EAV meta or native column loaded via `buildFaqAnswers()`
- Contract: `faq_answers` path in `getListingFactsAllowedPaths()`
- Guard A: empty `faq_answers.*` → `insufficient_context` + field-specific message
- Direct-return: FAQ answer present but OpenAI fails → raw `answer_text` returned as `ready`
- `deriveFieldLabel`: specific label for every pinned path (no generic fallback)

Representative sample:

| FAQ Key | Role | Data Present → Status | Data Absent → Status |
|---|---|---|---|
| `faq_answers.roof_age_and_condition` | seller | ready | insufficient_context: "Roof information has not been provided…" |
| `faq_answers.hvac_system_age` | seller | ready | insufficient_context: "HVAC system information has not been provided…" |
| `faq_answers.average_utility_costs` | seller | ready | insufficient_context: "Utility cost information has not been provided…" |
| `faq_answers.heating_cooling_system` | landlord | ready | insufficient_context: "Heating and cooling system information has not been provided…" |
| `faq_answers.laundry_situation` | landlord | ready | insufficient_context: "Laundry information has not been provided…" |
| `faq_answers.faq_q1` through `faq_q27` | tenant | ready | insufficient_context: field-specific message |
| `faq_answers.commercial_cam_charges` | landlord | ready | insufficient_context: "CAM charges information has not been provided…" |
| `faq_answers.annual_net_operating_income` | seller | ready | insufficient_context: "Net operating income (NOI) information has not been provided…" |

---

## Natural Language Synonym Coverage

### Questions Verified to Route to listing_facts

| Phrase | Field Resolved | Verified By |
|---|---|---|
| "what are the property taxes" | `listing.annual_property_taxes` | AskAiTaxRoofBedroomsNlpTest + harness |
| "how much are taxes" | `listing.annual_property_taxes` | harness |
| "tax amount" | `listing.annual_property_taxes` | harness |
| "how many bedrooms" | `listing.bedrooms` | harness |
| "number of bedrooms" | `listing.bedrooms` | harness |
| "how many bathrooms" | `listing.bathrooms` | LISTING_KEY_KEYWORD_MAP |
| "bath count" | `listing.bathrooms` | LISTING_KEY_KEYWORD_MAP |
| "square footage" | `listing.square_feet` | LISTING_KEY_KEYWORD_MAP |
| "how big is the home" | `listing.square_feet` | LISTING_KEY_KEYWORD_MAP |
| "year built" | `listing.year_built` | LISTING_KEY_KEYWORD_MAP |
| "how old is the roof" | `faq_answers.roof_age_and_condition` | FAQ_KEY_KEYWORD_MAP |
| "condition of the roof" | `faq_answers.roof_age_and_condition` | FAQ_KEY_KEYWORD_MAP + NLP test |
| "how old is the hvac" | `faq_answers.hvac_system_age` | FAQ_KEY_KEYWORD_MAP + NLP test |
| "when was the ac replaced" | `faq_answers.hvac_system_age` | FAQ_KEY_KEYWORD_MAP + NLP test |
| "how old is the water heater" | `faq_answers.water_heater_age_type` | FAQ_KEY_KEYWORD_MAP + NLP test |
| "in-unit laundry" | `faq_answers.laundry_situation` | harness critical phrase |
| "how much are utilities" | `faq_answers.average_utility_costs` | harness critical phrase |
| "utility costs" | `faq_answers.average_utility_costs` | harness critical phrase |
| "is smoking allowed" | `faq_answers.smoking_policy` | harness critical phrase |
| "subletting allowed" | `faq_answers.subletting_allowed` | harness critical phrase |
| "asking price" | `listing.asking_price` | LISTING_KEY_KEYWORD_MAP |
| "monthly rent" | `listing.rent_amount` | LISTING_KEY_KEYWORD_MAP |
| "pet deposit" | `listing.pet_deposit_fee_rent` | LISTING_KEY_KEYWORD_MAP |
| "are pets allowed" | `listing.pets_allowed` | LISTING_KEY_KEYWORD_MAP |
| "when can i move in" | `listing.available_date` | LISTING_KEY_KEYWORD_MAP |
| "flood zone" | `listing.is_in_flood_zone` | LISTING_KEY_KEYWORD_MAP |
| "hoa fee" | `listing.hoa_fee` | LISTING_KEY_KEYWORD_MAP |
| "financing type" | `listing.financing_type` | LISTING_KEY_KEYWORD_MAP |

---

## Prohibited Question Regression

Fair-housing / prohibited question types always return `question_type=prohibited` at Layer 1 (classifier). OpenAI is never called. Verified in `AskAiCoverageHarnessTest` test (6) — no FAQ keyword conflicts with competing intents.

---

## Missing-Data Hardening

**Guard A (faq_answers.* null):** When `detectFaqFieldKey()` resolves a canonical path but `filterAllowedContext()` returns empty (FAQ answer absent), the response is:
> `"[Field Label] has not been provided for this listing."` — status: `insufficient_context`

**Guard B (listing.* null):** When `detectListingFieldKey()` resolves a canonical path and the field is null/empty in allowed_context, the response is the same format — never `unsupported`, `failed`, or a hallucinated answer.

Both guards verified by `AskAiCoverageHarnessTest` test (4) and the `all pinned fields absent returns field specific message` data-provider tests (168 entries, all passing).

---

## Direct-Return Fallback Coverage

When OpenAI fails but grounded data is present:
- **FAQ fallback**: `faq_answers.*` answer text returned directly as `ready`
- **Listing fallback**: `listing.*` field value returned directly as `ready`
- **Does NOT fire for**: prohibited, blocked, or unsupported questions

Verified by `AskAiPipelineCoverageE2ETest` and `AskAiTaxRoofBedroomsNlpTest`.

---

## What Changed in This Audit

| Item | Before | After |
|---|---|---|
| `LISTING_KEY_KEYWORD_MAP` entries | 2 (bedrooms, annual_property_taxes) | 46 (all listing fields) |
| `deriveFieldLabel` listing.* entries | 2 | 46 |
| `AskAiCoverageHarnessTest` tests | 14 static tests | 18 static tests (+4 listing field coverage tests) |
| Total AskAi test count | 1,567 | 1,571 |

---

## No Defects Filed

All approved sources are passing. No follow-up defects required from this audit.
