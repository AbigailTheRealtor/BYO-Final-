# Ask AI Knowledge Base — Enhancement Summary

**Date:** 2026-07-01
**Scope:** Verification + fix of the property-type gating defect (Objective 1) and a full
audit / redesign / implementation of the config-driven Ask AI Knowledge Base across all four
user types and every property type (Objective 2).
**Baseline reference:** `docs/ask-ai-question-catalog.md`.

---

## 1. Executive summary

| | Before | After |
|---|---:|---:|
| Total KB question entries | 182 | **174** |
| Distinct question keys | 177 (5 duplicated) | **174 (0 duplicated)** |
| Seller entries | 76 | 76 |
| Buyer entries | 46 (41 distinct) | 47 (**47 distinct**) |
| Landlord entries | 40 | 32 |
| Tenant entries | 20 | 19 |
| Seller/Buyer non-residential groups reachable in UI | **No** (dormant) | **Yes** |

Two things happened: a **critical loading defect was fixed** (≈51 Seller/Buyer questions were
defined but never rendered), and the KB was **de-duplicated against structured listing fields and
against itself**, then **re-invested** in questions that capture context structured data cannot.

---

## 2. Objective 1 — the property-type loading defect

### 2.1 Issue found — CONFIRMED
The catalog's "🛑 verified critical defect" is real in the current repository.

- The Seller and Buyer property-type `<select>` inputs store **short** values —
  `Residential`, `Income`, `Commercial`, `Business`, `Vacant Land`
  (`offer-seller-tabs/commission-based/property-preferences.blade.php:620-625`,
  `offer-buyer-tabs/commission-based/property-preferences.blade.php:233-237`).
- The AI-FAQ `gating` maps (`config/ai_faq_seller.php`, `config/ai_faq_buyer.php`) are keyed by
  the **long** forms `Residential Property`, `Income Property`, `Commercial Property`,
  `Business Opportunity`, `Vacant Land`.
- `resources/views/livewire/offer-listing/shared/ai-questions-input.blade.php` looked up
  `$gating[$property_type]` with **no normalization**, so `Income` / `Commercial` / `Business`
  missed the key and fell back to `['universal','residential']`.

**Consequence:** Seller/Buyer Income, Commercial, and Business listings rendered only the
universal + residential questions; the intended property-specific groups (~51 entries) were
**defined in config but unreachable** in the create/edit UI. `Vacant Land` matched exactly and
`Residential` landed on the correct group by fallback coincidence. Landlord and Tenant selects
already store the long forms, so they were unaffected.

### 2.2 True root cause
Two intentional but unreconciled property-type vocabularies: Seller/Buyer persist **short**
values; Landlord/Tenant and the gating config use **long** values. `config/property_types.php`
confirms this is by design (`buyer.types` are short, `tenant.types` are long). The bug was the
**absence of a normalization step** between the stored value and the gating lookup.

### 2.3 Fix implemented (backward-compatible, no duplicate mappings)
A single canonical alias map was added to the existing source of truth, and the blade normalizes
through it before the lookup:

- `config/property_types.php` → new `ai_faq_gating_aliases` map
  (`Residential → Residential Property`, `Income → Income Property`,
  `Commercial → Commercial Property`, `Business → Business Opportunity`; `Vacant Land` is
  identical in both vocabularies, so no alias). Long-form values pass through unchanged.
- `ai-questions-input.blade.php` resolves `$gatingKey = $aliases[$propertyType] ?? $propertyType`
  and gates on `$gatingKey`.

**Why not change the select values?** `property_type` is persisted and read in its short form by
match scoring, display helpers, and dozens of blade conditionals (e.g.
`in_array($this->property_type, ['Residential','Income','Vacant Land'])`). Rewriting the stored
value would break every existing listing and consumer. The normalization layer preserves 100%
backward compatibility.

### 2.4 Verification
`tests/Feature/AskAi/AskAiKnowledgeBaseRenderTest.php` was rewritten to drive the blade with the
**real select values** (short forms for Seller/Buyer, long forms for Landlord/Tenant) instead of
the long-form gating keys the old test used to bypass the bug. New regression coverage:
- `shortFormMatrix` proves each short value (`Income`/`Commercial`/`Business`) resolves to its own
  group and does **not** fall back to residential.
- `test_gating_alias_map_covers_seller_buyer_short_forms` pins the alias map.

Correct KB now loads for every required combination:
Seller {Residential, Income, Commercial (sale), Business, Vacant Land},
Buyer {Residential, Income, Commercial (sale), Business, Vacant Land},
Landlord {Residential, Commercial Lease}, Tenant {Residential, Commercial Lease}.

---

## 3. Objective 2 — audit and redesign

### 3.1 Method
Two structured-field inventories were compiled from the Livewire components and every tab blade
(Seller/Buyer and Landlord/Tenant), then each KB question was tested against the rule: **if the
platform already collects the fact as a structured field, the KB must not re-ask it** — Ask AI
answers those from the structured data instead.

### 3.2 Questions removed because they duplicate an existing structured field

| Role · Group | Removed key | Structured field Ask AI should use instead |
|---|---|---|
| Seller · Commercial | `commercial_ceiling_height` | `ceiling_height` |
| Seller · Commercial | `commercial_zoning_uses` | `zoning` |
| Landlord · Residential | `heating_cooling_system` | `heating_fuel`, `air_conditioning` |
| Landlord · Residential | `laundry_situation` | `laundry_features` |
| Landlord · Residential | `storage_area_included` | `included_storage_space*` / `storage_space*` |
| Landlord · Residential | `security_features` | `security_features` |
| Landlord · Residential | `average_utility_costs` | `est_electric`, `est_water_sewer_trash`, `est_internet`, `est_cable` |
| Landlord · Commercial | `commercial_restroom_count` | `number_of_restrooms` |
| Landlord · Commercial | `commercial_building_access_hours` | `building_hours`, `access_24_7` |
| Landlord · Commercial | `commercial_permitted_use` | `permitted_use_restrictions`, `zoning` |
| Tenant · Residential | `faq_q10` (furnished pref) | `tenant_require` (Furnishings Needed) |
| Tenant · Commercial | `tenant_commercial_parking` | `commercial_parking_access_needs`, `parking_needed` |

The three removed Landlord residential keys `heating_cooling_system`, `laundry_situation`,
`security_features` are still registered in `AskAiFieldQuestionRegistryService` as
**listing-fact questions answered from the structured fields** — confirming they were redundant as
free-text KB prompts. Removing the KB authoring field eliminates double data entry while the
structured-field answer path is retained.

### 3.3 Duplicate KB keys eliminated (question de-duplication)
The five Buyer `com_*` keys that appeared **identically** in both the Income and Commercial groups
(`com_property_use`, `com_occupancy_rate`, `com_lease_terms`, `com_1031_exchange`,
`com_environmental_concerns`) were the only true intra-config duplicates. They are now **split into
10 property-specific keys** with tailored, non-generic content:

| Old shared key | Income group (buy-and-hold) | Commercial group (owner-occupant / investor) |
|---|---|---|
| `com_property_use` | `buyer_income_intended_use` | `buyer_commercial_intended_use` |
| `com_occupancy_rate` | `buyer_income_occupancy_requirement` | `buyer_commercial_space_requirements` |
| `com_lease_terms` | `buyer_income_rent_roll_expectations` | `buyer_commercial_tenancy_preference` |
| `com_1031_exchange` | `buyer_income_1031_exchange` | `buyer_commercial_1031_exchange` |
| `com_environmental_concerns` | `buyer_income_environmental` | `buyer_commercial_environmental` |

Result: Buyer now has **47 distinct keys with zero duplicated keys** (was 41 distinct across 46
entries).

### 3.4 Questions added (unique value, not derivable from structured data)

| Role · Group | New key | Why it is not a duplicate |
|---|---|---|
| Seller · Residential | `pool_spa_equipment_condition` | `pool_needed`/`pool_type` capture presence/type; equipment **age & condition** is not structured. |
| Seller · Income | `income_rent_roll_context` | Per-unit rent is structured; the owner's **at/below/above-market context and renewal timing narrative** is not. |
| Seller · Commercial | `commercial_zoning_context` | `zoning` captures the code; **variances, conditional/special-use permits, grandfathered uses** are owner knowledge only. |
| Buyer · Universal | `buyer_financing_context` | Financing type & pre-approval are structured; **funds source / documentation narrative** (gift funds, self-employed, sale proceeds) is not. |
| Landlord · Commercial | `commercial_zoning_context` | `permitted_use_restrictions` captures allowed uses; **variances, conditional uses, deed restrictions** are not. |
| Tenant · Commercial | `tenant_growth_plans` | Space size is structured; the applicant's **expected space trajectory over the term** is not. |

All new questions are phrased neutrally/factually and stay inside the compliance guardrail (no
advice, steering, superlatives, protected-class, or leverage/negotiation framing).

### 3.5 Questions reorganized
- Seller Commercial: zoning coverage was two near-duplicate entries (`commercial_zoning_uses`
  common + `commercial_zoning_permitted_summary` insight, both `Field+KB` restating the same
  structured `zoning`). Both were replaced by the single value-add `commercial_zoning_context`.
- Landlord Commercial: the lone `Rental Insights` entry (`commercial_zoning_permitted_summary`)
  duplicated the structured permitted-use field; it was folded into the value-add
  `commercial_zoning_context`. The universal `Rental Insights` group still supplies the AI Insights
  section for commercial listings, so every role × property type still renders both sections.

### 3.6 Topics intentionally NOT added (answered from structured fields)
The catalog's gap list (§6.3) suggested several additions that the field audit showed are already
structured — adding KB prompts would violate the no-duplication rule. Ask AI should answer these
from the fields listed:

| Suggested topic | Answer from structured field(s) |
|---|---|
| HOA / CDD fees, special assessments | `has_hoa`, `association_fee_amount`, `association_fee_frequency`, `association_fee_includes`, `annual_cdd_fee`, `has_special_assessments` |
| Sewer vs. septic | `sewer` (+`number_of_septics` on land) |
| Buyer budget / financing readiness | `maximum_budget`, `pre_approved`, `pre_approval_amount`, `offered_financing` |
| Tenant move-in date | `move_in_date_earliest`, `move_in_date_latest`, `preferred_move_in_timeframe` |
| Tenant number of occupants | `number_occupant` / `number_of_occupants` / `number_of_occupants_allowed` |
| Landlord pet policy / smoking / parking assignment | `pet_policy` (+ `pet_*`), `smoking_policy`, `parking_terms` / `commercial_parking_terms` |
| Unit mix (income) | `number_of_units`, `beds_unit`, `baths_unit`, `unit_type_configurations` |

---

## 4. AI Insight review
The AI Insights layer already spans Property DNA, Location DNA, Buyer/Tenant DNA, Match, and
Description across the universal groups of all four roles, so the "AI Insights" section renders for
every role × property type. The audit found the Insight set adequately covered and, more
importantly, found that most *additional* insight ideas would duplicate an existing insight
(`property_features_buyer_appeal` already pairs `PropDNA+Match`) or a structured field. Rather than
add redundant insights, redundancy was removed and the freed capacity was reinvested into the
context-capturing Common questions in §3.4. No new insight entries were added; one insight that
merely restated a structured field was removed.

**Remaining recommendation:** if a future legally-reviewed phase enables it, a Match-driven insight
that explains *which disclosed features align with common buyer/tenant criteria* (framed as
feature↔need alignment, never desirability/value) could be added for Seller and Landlord. It is
deferred here to avoid brushing the guardrail's advice/steering categories.

---

## 5. Backward compatibility & runtime coupling
- **No stored `property_type` values changed** — the fix is a read-time normalization layer.
- **Removed KB keys** simply stop rendering; any historical answer already stored in the
  `listing_ai_faq` blob is retained harmlessly and is no longer collected going forward.
- **The legacy `buyer_criteria/add.blade.php` flow** (`BuyerCriteriaAuctionController` +
  `AskAiFieldQuestionRegistryService::buyerAddonRegistry` with `com_*`/`biz_*`/`land_*` keys)
  is a **separate parallel system** from the offer-listing KB and was **not** modified; its
  count-pinned tests are unaffected. Reconciling it with the config-driven KB remains a documented
  follow-up (catalog §6.5).
- **Tenant privacy:** no sensitive keys were removed or added, so
  `AskAiViewerAuthorizationService`'s redaction sets are unchanged and still valid.

---

## 6. Tests
- `AskAiKnowledgeBaseRenderTest` — rewritten to use real select values; added `shortFormMatrix`
  regression cases and the alias-map assertion. **21 assertions pass.**
- Full Ask AI suite (`tests/Feature/AskAi` + `tests/Unit/Services/AskAi`): **267 passing**, with
  **no new failures** introduced by this work. The 4 remaining failures are **pre-existing** and
  belong to a different subsystem (context-builder key-count pins around `pet_information`/buyer
  keys in `AskAiRefactorParityTest`, and the agent-profile loader in `AskAiGoldenQaSuiteTest`);
  they are unrelated to the Ask AI Knowledge Base config and predate this change.
- **Verification of "no new failures":** every failing test above was confirmed to fail
  identically with this change stashed out, isolating them as pre-existing branch state. Other
  unrelated pre-existing failures also exist on the `launch-audit-remediation` branch (e.g.
  `AskAiListingQuestionTest`'s controller mock-wiring, 7 failures) and were likewise confirmed to
  fail without this change; they are outside the KB config scope and untouched here.

---

## 7. Coverage improvements summary
- **Reachability:** ~51 previously-dormant Seller/Buyer non-residential questions now render.
- **Redundancy:** 13 field-duplicating / consolidated questions removed; 5 duplicated buyer keys
  eliminated.
- **Specificity:** Buyer Income vs Commercial diligence is now genuinely property-specific
  (rent-roll/occupancy vs owner-occupant/build-out) instead of one shared generic set.
- **Depth:** 6 high-value, non-duplicative questions added that capture owner/buyer/tenant context
  structured data cannot (pool-equipment condition, rent-roll market context, zoning
  variances ×2, financing context, tenant growth plans).
- **Net:** 182 → 174 entries, 177 → 174 distinct keys, **0 duplicate keys**, **0 KB questions that
  duplicate a structured field**.
