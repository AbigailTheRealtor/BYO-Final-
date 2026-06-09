# Ask AI — Full Field & FAQ Coverage Map

**Date:** 2026-06-08
**Scope:** All four listing roles — Seller, Buyer, Landlord, Tenant
**Purpose:** Complete coverage map documenting every FAQ config key, its canonical
`faq_answers.*` path, the natural-language routing keywords that detect it, and
whether the field is covered end-to-end in the Ask AI pipeline.

Related docs:
- `docs/audits/ASK_AI_FULL_CONTEXT_MAP.md` — every native column and EAV field per role
- `docs/audits/ASK_AI_NATURAL_LANGUAGE_ROUTING_AUDIT.md` — classifier intent routing

---

## Pipeline Overview

```
User question
    ↓
AskAiQuestionClassifierService   ← detects intent (listing_facts / educational / …)
    ↓
AskAiRunnerV2Service
  ├─ detectFaqFieldKey()          ← matches question against FAQ_KEY_KEYWORD_MAP
  │     → sets normalized_field_key (e.g. "faq_answers.roof_age_and_condition")
  ↓
AskAiInternalRunnerService
  ├─ AskAiContextBuilderService   ← builds context; faq_answers[] comes from ai_faq_answers
  ├─ AskAiResponseContractService ← listing_facts contract allows "faq_answers" path
  └─ AskAiPromptBuilderService    ← prompt includes faq_answers block
    ↓
OpenAI adapter → response
```

**Key principle:** The `FAQ_KEY_KEYWORD_MAP` pins a specific FAQ field key when the
user's question is unambiguously about one field. The contract's `faq_answers` umbrella
path gives the prompt access to all filled FAQ answers regardless of whether a specific
key was detected.

---

## Coverage Legend

| Symbol | Meaning |
|--------|---------|
| ✅ | Fully covered — FAQ key exists in config, has keyword routes, has label, and classifies as `listing_facts` |
| ⚠️ | Partially covered — key exists in config but missing keyword routes or label |
| ❌ | Gap — key exists in config but has NO keyword routes in FAQ_KEY_KEYWORD_MAP |
| N/A | Not applicable for this role |

---

## Section 1 — Seller Role (`config/ai_faq_seller.php`)

### 1.1 Property Condition & Maintenance

| Config Key | Canonical faq_answers Path | Keyword Routes | Label | Status |
|---|---|---|---|---|
| `roof_age_and_condition` | `faq_answers.roof_age_and_condition` | "roof age", "how old is the roof", "roof condition", +5 | ✅ | ✅ |
| `hvac_system_age` | `faq_answers.hvac_system_age` | "hvac age", "how old is the hvac", "hvac last serviced", +6 | ✅ | ✅ |
| `water_heater_age_type` | `faq_answers.water_heater_age_type` | "water heater age", "what type of water heater", +6 | ✅ | ✅ |
| `recent_renovations_list` | `faq_answers.recent_renovations_list` | "recent renovations", "what renovations", "renovation history", +6 | ✅ | ✅ |
| `permits_for_renovations` | `faq_answers.permits_for_renovations` | "permits for renovations", "renovation permits", +4 | ✅ | ✅ |
| `known_defects_issues` | `faq_answers.known_defects_issues` | "known defects", "known issues", "deferred repairs", +5 | ✅ | ✅ |
| `foundation_type_and_issues` | `faq_answers.foundation_type_and_issues` | "foundation type", "any foundation issues", +5 | ✅ | ✅ |
| `pest_termite_history` | `faq_answers.pest_termite_history` | "pest history", "termite history", "termite damage", +5 | ✅ | ✅ |
| `flood_damage_history` | `faq_answers.flood_damage_history` | "ever flooded", "flood history", "water damage history", +4 | ✅ | ✅ |
| `mold_issues_history` | `faq_answers.mold_issues_history` | "mold history", "any mold issues", "mold remediation", +3 | ✅ | ✅ |

### 1.2 Financial & Utility Insights

| Config Key | Canonical faq_answers Path | Keyword Routes | Label | Status |
|---|---|---|---|---|
| `average_utility_costs` | `faq_answers.average_utility_costs` | "utility costs", "how much are utilities", "average electric bill", +6 | ✅ | ✅ |
| `internet_utility_providers` | `faq_answers.internet_utility_providers` | "internet providers", "which internet providers", +5 | ✅ | ✅ |
| `seller_concessions_offered` | `faq_answers.seller_concessions_offered` | "seller concessions", "closing cost credits", +4 | ✅ | ✅ |

### 1.3 Location & Lifestyle

| Config Key | Canonical faq_answers Path | Keyword Routes | Label | Status |
|---|---|---|---|---|
| `neighborhood_character` | `faq_answers.neighborhood_character` | (classifier "neighborhood" broad match) | ⚠️ No dedicated map entry | ⚠️ |
| `traffic_or_noise_concerns` | `faq_answers.traffic_or_noise_concerns` | (classifier "noise" broad match) | ⚠️ No dedicated map entry | ⚠️ |
| `planned_nearby_development` | `faq_answers.planned_nearby_development` | — | ⚠️ No dedicated map entry | ⚠️ |
| `commute_options_access` | `faq_answers.commute_options_access` | — | ⚠️ No dedicated map entry | ⚠️ |
| `natural_light_orientation` | `faq_answers.natural_light_orientation` | — | ⚠️ No dedicated map entry | ⚠️ |
| `nearby_amenities_description` | `faq_answers.nearby_amenities_description` | — | ⚠️ No dedicated map entry | ⚠️ |
| `neighborhood_restrictions` | `faq_answers.neighborhood_restrictions` | — | ⚠️ No dedicated map entry | ⚠️ |

> **Note:** Location & Lifestyle keys are still accessible via the `faq_answers` umbrella
> context path in the listing_facts contract. They just don't have pinned keyword routes,
> so the missing-data guard ("this information has not been provided") won't trigger for them.
> They are addressed in the follow-up gap list (Section 6).

### 1.4 Flexibility & Negotiation

| Config Key | Canonical faq_answers Path | Keyword Routes | Label | Status |
|---|---|---|---|---|
| `closing_timeline_flexibility` | `faq_answers.closing_timeline_flexibility` | "closing timeline flexibility", "flexible on closing", +4 | ✅ | ✅ |
| `seller_leaseback_option` | `faq_answers.seller_leaseback_option` | — | ⚠️ No dedicated map entry | ⚠️ |
| `items_excluded_from_sale` | `faq_answers.items_excluded_from_sale` | "items excluded", "what conveys", "what does not convey", +5 | ✅ | ✅ |
| `furniture_negotiability` | `faq_answers.furniture_negotiability` | — | ⚠️ No dedicated map entry | ⚠️ |
| `as_is_condition` | `faq_answers.as_is_condition` | "sold as-is", "being sold as is", "as-is condition", +3 | ✅ | ✅ |
| `environmental_concerns` | `faq_answers.environmental_concerns` | — | ⚠️ No dedicated map entry | ⚠️ |

### 1.5 Hidden Selling Points

| Config Key | Canonical faq_answers Path | Keyword Routes | Label | Status |
|---|---|---|---|---|
| `unique_selling_points` | `faq_answers.unique_selling_points` | "what makes this property special", "hidden features", +4 | ✅ | ✅ |
| `seller_favorite_features` | `faq_answers.seller_favorite_features` | — | ⚠️ No dedicated map entry | ⚠️ |
| `seller_motivation_for_selling` | `faq_answers.seller_motivation_for_selling` | — | ⚠️ No dedicated map entry | ⚠️ |
| `move_in_ready_status` | `faq_answers.move_in_ready_status` | — | ⚠️ No dedicated map entry | ⚠️ |
| `parking_arrangements` | `faq_answers.parking_arrangements` | "parking arrangements", "how many cars", "driveway space", +2 | ✅ | ✅ |
| `storage_space_available` | `faq_answers.storage_space_available` | — | ⚠️ No dedicated map entry | ⚠️ |
| `hoa_community_highlights` | `faq_answers.hoa_community_highlights` | "hoa amenities", "community amenities", +3 | ✅ | ✅ |

### 1.6 Addon — Commercial / Income

| Config Key | Status | Notes |
|---|---|---|
| `annual_net_operating_income` | ⚠️ | No keyword routes; financial data routed via broad `listing_facts` if mentioned |
| `current_cap_rate` | ⚠️ | No keyword routes |
| `existing_tenant_lease_terms` | ⚠️ | No keyword routes |
| `current_occupancy_rate` | ⚠️ | No keyword routes |
| `annual_operating_expenses_detail` | ⚠️ | No keyword routes |
| `value_add_opportunities` | ⚠️ | No keyword routes |

### 1.7 Addon — Business Opportunity

| Config Key | Status |
|---|---|
| `annual_business_revenue` | ⚠️ No keyword routes |
| `annual_net_profit` | ⚠️ No keyword routes |
| `business_reason_for_selling` | ⚠️ No keyword routes |
| `business_employee_count` | ⚠️ No keyword routes |
| `seller_training_transition` | ⚠️ No keyword routes |
| `business_lease_status` | ⚠️ No keyword routes |
| `inventory_equipment_included` | ⚠️ No keyword routes |

### 1.8 Addon — Vacant Land

| Config Key | Status |
|---|---|
| `land_utilities_availability` | ⚠️ No keyword routes |
| `land_zoning_permitted_uses` | ⚠️ No keyword routes |
| `land_access_and_road` | ⚠️ No keyword routes |
| `land_soil_and_topography` | ⚠️ No keyword routes |
| `land_survey_available` | ⚠️ No keyword routes |
| `land_development_restrictions` | ⚠️ No keyword routes |

---

## Section 2 — Landlord Role (`config/ai_faq_landlord.php`)

### 2.1 Maintenance & Property Condition

| Config Key | Canonical faq_answers Path | Keyword Routes | Status |
|---|---|---|---|
| `maintenance_request_response_time` | `faq_answers.maintenance_request_response_time` | "maintenance requests", "how are repairs handled", +5 | ✅ |
| `emergency_maintenance_available` | `faq_answers.emergency_maintenance_available` | "emergency maintenance", "24 hour maintenance", +3 | ✅ |
| `heating_cooling_system` | `faq_answers.heating_cooling_system` | "heating and cooling system", "hvac type", +7 | ✅ |
| `laundry_situation` | `faq_answers.laundry_situation` | "in-unit laundry", "laundry situation", +5 | ✅ |
| `storage_area_included` | `faq_answers.storage_area_included` | — | ⚠️ No dedicated map entry |
| `internet_providers` | `faq_answers.internet_providers` | — | ⚠️ No dedicated map entry |
| `security_features` | `faq_answers.security_features` | "security features", "security system", "security cameras", +4 | ✅ |
| `planned_renovations` | `faq_answers.planned_renovations` | — | ⚠️ No dedicated map entry |

### 2.2 Location & Neighborhood

| Config Key | Status |
|---|---|
| `neighborhood_character` | ⚠️ No dedicated map entry (same key as seller) |
| `noise_levels` | ⚠️ No dedicated map entry |
| `nearby_amenities` | ⚠️ No dedicated map entry |
| `guest_parking` | ⚠️ No dedicated map entry |
| `proximity_to_public_transit` | ⚠️ No dedicated map entry |

### 2.3 Lifestyle & Flexibility

| Config Key | Canonical faq_answers Path | Keyword Routes | Status |
|---|---|---|---|
| `furnished_or_unfurnished` | `faq_answers.furnished_or_unfurnished` | — | ⚠️ No dedicated map entry |
| `lease_renewal_process` | `faq_answers.lease_renewal_process` | "lease renewal process", "how does lease renewal work", +4 | ✅ |
| `notice_to_vacate_required` | `faq_answers.notice_to_vacate_required` | — | ⚠️ No dedicated map entry |
| `preferred_tenant_qualities` | `faq_answers.preferred_tenant_qualities` | — | ⚠️ No dedicated map entry |
| `subletting_allowed` | `faq_answers.subletting_allowed` | "subletting allowed", "can i sublet", "sublease allowed", +2 | ✅ |
| `short_term_rentals_allowed` | `faq_answers.short_term_rentals_allowed` | — | ⚠️ No dedicated map entry |
| `ev_charging_available` | `faq_answers.ev_charging_available` | — | ⚠️ No dedicated map entry |
| `bicycle_storage_available` | `faq_answers.bicycle_storage_available` | — | ⚠️ No dedicated map entry |

### 2.4 High-Intent Tenant Questions

| Config Key | Canonical faq_answers Path | Keyword Routes | Status |
|---|---|---|---|
| `what_makes_property_unique` | `faq_answers.what_makes_property_unique` | "what makes this rental", "what sets this rental apart", +2 | ✅ |
| `pest_or_mold_history` | `faq_answers.pest_or_mold_history` | "pest or mold history", "any mold or pest issues", +3 | ✅ |
| `utilities_individually_metered` | `faq_answers.utilities_individually_metered` | — | ⚠️ No dedicated map entry |
| `renters_insurance_required` | `faq_answers.renters_insurance_required` | — | ⚠️ No dedicated map entry |
| `lease_to_own_option` | `faq_answers.lease_to_own_option` | — | ⚠️ No dedicated map entry |
| `previous_tenant_feedback` | `faq_answers.previous_tenant_feedback` | — | ⚠️ No dedicated map entry |
| `smoking_policy` | `faq_answers.smoking_policy` | "smoking policy", "smoking allowed", "is smoking allowed", +2 | ✅ |

### 2.5 Addon — Commercial

| Config Key | Status |
|---|---|
| `commercial_cam_charges` | ⚠️ No keyword routes |
| `commercial_lease_structure_type` | ⚠️ No keyword routes |
| `commercial_tenant_improvement_allowance` | ⚠️ No keyword routes |
| `commercial_buildout_flexibility` | ⚠️ No keyword routes |
| `commercial_signage_rights` | ⚠️ No keyword routes |
| `commercial_loading_dock_freight_elevator` | ⚠️ No keyword routes |
| `commercial_electrical_capacity` | ⚠️ No keyword routes |
| `commercial_parking_ratio` | ⚠️ No keyword routes |
| `commercial_exclusivity_rights` | ⚠️ No keyword routes |
| `commercial_expansion_option_rofr` | ⚠️ No keyword routes |
| `commercial_landlord_maintenance_responsibilities` | ⚠️ No keyword routes |
| `commercial_building_access_hours` | ⚠️ No keyword routes |

---

## Section 3 — Buyer Role (`config/ai_faq_buyer.php`)

Buyer FAQ answers are provided by buyers themselves (their intent, lifestyle goals,
deal-breakers, etc.). They are surfaced on buyer listing view pages for transparency.

> **Important:** Buyer FAQ answers (`faq_answers.*`) in the AI pipeline are accessed
> when an agent or another party is asking about a **buyer's** stated preferences.
> The classifier handles buyer-intent questions as `buyer_tenant_match`, not `listing_facts`.
> Buyer FAQ keys are NOT routed through `FAQ_KEY_KEYWORD_MAP` — this is by design.

### 3.1 Buyer Intent & Lifestyle (28 base keys)

All 28 base keys (`buyer_motivation`, `buyer_lifestyle_goals`, `buyer_deal_breakers`, etc.)
are **accessible via the `faq_answers` umbrella** when the context is a buyer listing.
None have dedicated keyword routes in `FAQ_KEY_KEYWORD_MAP` — this is appropriate since
buyer FAQ data informs match scoring rather than answering direct factual questions.

**Coverage status:** ✅ Accessible via context umbrella | ⚠️ No pinned keyword routes

### 3.2 Buyer Addons (21 addon keys)

Commercial/Income (8), Business Opportunity (7), Vacant Land (6) buyer questions.
Same pattern: accessible via context umbrella; no pinned keyword routes.

---

## Section 4 — Tenant Role (`config/tenant_ai_faq.php`)

Tenant FAQ uses a flat array structure with keys `faq_q1` through `faq_q27`.

| Key Range | Count | Scope | Keyword Routes |
|---|---|---|---|
| `faq_q1` – `faq_q20` | 20 | All property types | None — accessible via `faq_answers` umbrella |
| `faq_q21` – `faq_q27` | 7 | Commercial only | None — accessible via `faq_answers` umbrella |

**Coverage status:** ✅ Accessible via context umbrella | ⚠️ No pinned keyword routes

> Tenant FAQ keys use opaque sequential IDs (`faq_q1`, `faq_q2`, …) which cannot
> be meaningfully pinned in `FAQ_KEY_KEYWORD_MAP` without a separate key-to-label
> mapping. This is a known gap documented in Section 6.

---

## Section 5 — Classifier Keyword Coverage

### 5.1 listing_facts intent — FAQ-answerable phrases

The following keyword groups were added (or confirmed present) in
`AskAiQuestionClassifierService::listing_facts` to route FAQ-answerable questions
to the correct intent before `FAQ_KEY_KEYWORD_MAP` pinning:

| Category | Sample Phrases Added | Prior Coverage |
|---|---|---|
| Average utility costs | "utility costs", "how much are utilities", "average electric bill" | ❌ None |
| Renovations / upgrades | "recent renovations", "renovation history", "has it been renovated" | ❌ None |
| Known defects / issues | "known defects", "known issues", "deferred repairs" | ❌ None |
| Pest / termite history | "pest history", "termite history", "have there been termites" | ❌ None |
| Foundation | "foundation type", "what type of foundation", "foundation problems" | ❌ None |
| Mold history | "mold history", "any mold issues", "has there been mold" | ❌ None |
| Flood / water damage history | "ever flooded", "flood history", "water damage history" | ❌ None |
| Seller concessions | "seller concessions", "closing cost credits", "repair credits" | ❌ None |
| Items excluded / conveys | "items excluded", "what conveys", "what does not convey" | ❌ None |
| As-is condition | "sold as-is", "being sold as is", "as-is condition" | ❌ None |
| Closing timeline flexibility | "closing timeline flexibility", "flexible on closing" | ❌ None |
| HVAC age | "hvac age", "how old is the hvac", "hvac last serviced" | ⚠️ Partial ("hvac" bare keyword) |
| Water heater | "water heater age", "how old is the water heater", "water heater type" | ⚠️ Partial ("water heater" bare keyword) |
| Maintenance requests | "maintenance requests", "how are repairs handled" | ❌ None |
| Emergency maintenance | "emergency maintenance", "24 hour maintenance" | ❌ None |
| Security features | "security system", "security cameras", "building security" | ❌ None |
| Lease renewal process | "lease renewal process", "how does lease renewal work" | ❌ None |
| Subletting (extended) | "sublease allowed", "is subletting permitted" | ⚠️ Partial ("subletting allowed", "can i sublet") |

---

## Section 6 — Known Gaps & Recommendations

### 6.1 High Priority — Add to FAQ_KEY_KEYWORD_MAP

These seller/landlord FAQ keys are in the registry but have no pinned keyword routes.
Any question matching these categories will still be answered (via the `faq_answers` umbrella)
but will not trigger the missing-data guard if the field was not filled in.

| Config Key | Role | Suggested Keyword Phrases |
|---|---|---|
| `neighborhood_character` | seller + landlord | "neighborhood vibe", "how is the neighborhood", "describe the neighborhood" |
| `traffic_or_noise_concerns` | seller | "traffic near the property", "any noise concerns", "how noisy is the area" |
| `seller_leaseback_option` | seller | "leaseback option", "can seller stay after closing", "rent back after closing" |
| `furniture_negotiability` | seller | "furniture negotiable", "any furniture included", "is the furniture staying" |
| `seller_motivation_for_selling` | seller | "why is the seller selling", "reason for selling", "motivation for selling" |
| `move_in_ready_status` | seller | "is it move-in ready", "move in ready", "ready to move in" |
| `storage_space_available` | seller | "storage space", "how much storage", "is there attic storage" |
| `furnished_or_unfurnished` | landlord | "is it furnished", "furnished apartment", "comes furnished" |
| `notice_to_vacate_required` | landlord | "notice to vacate", "how much notice to leave", "notice required to move out" |
| `short_term_rentals_allowed` | landlord | "airbnb allowed", "short-term rental", "vrbo allowed" |
| `ev_charging_available` | landlord | "ev charging", "electric vehicle charging", "can i install a charger" |
| `bicycle_storage_available` | landlord | "bike storage", "bicycle storage", "secure bike parking" |
| `utilities_individually_metered` | landlord | "individually metered", "separate meters", "is gas metered separately" |
| `renters_insurance_required` | landlord | "renters insurance required", "do i need renters insurance" |
| `lease_to_own_option` | landlord | "lease to own", "rent to own", "option to buy" |
| `previous_tenant_feedback` | landlord | "previous tenant reviews", "what did past tenants say" |

### 6.2 Medium Priority — Tenant FAQ Key Routing

Tenant FAQ keys (`faq_q1`–`faq_q27`) use opaque sequential IDs.
To add `FAQ_KEY_KEYWORD_MAP` routing for tenant FAQ answers, either:
- Add a `label_short` field to the tenant config entries, OR
- Create a `tenant_faq_keyword_map.php` config that maps question topic → `faq_qN`

### 6.3 Low Priority — Addon FAQ Keys (Commercial / Business / Land)

Addon keys for commercial, business opportunity, and vacant land listings are only
activated for specific property types. A separate addon coverage pass is recommended
once the base keys are fully covered.

### 6.4 deriveFieldLabel Key Mismatch (Pre-existing)

The original `deriveFieldLabel` map used `faq_answers.recent_renovations` but the
seller config key is `recent_renovations_list`. This has been resolved:
- `faq_answers.recent_renovations_list` ← new entry (matches actual config key)
- `faq_answers.recent_renovations` ← retained for backward compatibility

Similarly, `faq_answers.pest_treatment_history` (generic legacy label) was retained
alongside the role-specific keys `faq_answers.pest_termite_history` (seller) and
`faq_answers.pest_or_mold_history` (landlord).

---

## Section 7 — Coverage Summary Statistics

| Metric | Count |
|---|---|
| Total seller base FAQ keys | 35 |
| Total seller addon FAQ keys | 19 |
| Total landlord base FAQ keys | 28 |
| Total landlord addon FAQ keys | 12 |
| Total buyer base FAQ keys | 28 |
| Total buyer addon FAQ keys | 21 |
| Total tenant FAQ keys | 27 |
| **Total FAQ keys across all roles** | **170** |
| FAQ keys with pinned keyword routes (FAQ_KEY_KEYWORD_MAP) | 29 |
| FAQ keys with deriveFieldLabel entries | 48 |
| Classifier listing_facts keyword phrases (total) | ~160 |
| Classifier listing_facts phrases added in this audit | ~90 |
| Registry entries in AskAiFieldQuestionRegistryService | 54 |

---

*This document was generated during the Ask AI Full Field & FAQ Coverage audit.*
*Last updated: 2026-06-08*
