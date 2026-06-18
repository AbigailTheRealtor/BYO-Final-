# Ask AI Full Field Connectivity Audit — Phase 1 (Discovery)

**Date:** June 18, 2026  
**Scope:** Discovery only — no code changes made.  
**Methodology:** Source-code static analysis of all five pipeline layers plus complete form field inventories extracted from Livewire components via subagent exploration.

**Files audited:**
- `app/Services/AskAi/AskAiContextBuilderService.php` (2249 lines) — CANONICAL_SOURCE_MAP, extractManualFields, decodeJsonField, resolveOtherValue
- `app/Services/AskAi/AskAiRunnerV2Service.php` (5141 lines) — run(), LISTING_KEY_KEYWORD_MAP (167 keys), FAQ_KEY_KEYWORD_MAP (129 keys)
- `app/Services/AskAi/AskAiQuestionClassifierService.php` — KEYWORD_RULES, listing_facts (lines 141–1267), property_standout (line 1285+)
- `app/Services/AskAi/AskAiFieldQuestionRegistryService.php` (3254 lines) — listingFieldRegistry() (71 entries), registry() (166 FAQ entries)
- `app/Services/AgentAi/Loaders/AgentProfileLoader.php` — buildContent() (47 fields)
- `app/Services/AgentAi/Loaders/AgentPresetLoader.php` — summarizePreset() (≤15 fields per preset)
- `app/Models/AgentDefaultProfile.php` — profile_data schema, roleLabel(), propertyLabel()
- `app/Http/Livewire/OfferListing/Seller/SellerOfferListing.php` — saveAllMetadata(), 12 sections (~400+ EAV keys)
- `app/Http/Livewire/OfferListing/Buyer/BuyerOfferListing.php` — saveAllMetadata()
- `app/Http/Livewire/OfferListing/Landlord/LandlordOfferListing.php` — saveAllMetadata()
- `app/Http/Livewire/OfferListing/Tenant/TenantOfferListing.php` — saveAllMetadata()

---

## Table of Contents

1. [System Architecture and Pipeline Definitions](#1-system-architecture-and-pipeline-definitions)
2. [Field Inventory Methodology — True Denominators](#2-field-inventory-methodology--true-denominators)
3. [Layer Inventory Summary (Exact Counts)](#3-layer-inventory-summary-exact-counts)
4. [Coverage Summary — All 9 Entity Types](#4-coverage-summary--all-9-entity-types)
5. [Source Priority Verification](#5-source-priority-verification)
6. [Per-Chat-Context Loaded-vs-Reachable Audit](#6-per-chat-context-loaded-vs-reachable-audit)
7. [Complete Field Inventories with Status Tags](#7-complete-field-inventories-with-status-tags)
8. [Cross-Layer Gap Table](#8-cross-layer-gap-table)
9. [Priority Issue List](#9-priority-issue-list)
10. [Top 25 Highest-Risk Gaps](#10-top-25-highest-risk-gaps)
11. [Implementation Plan](#11-implementation-plan)
12. [Appendices](#12-appendices)

---

## 1. System Architecture and Pipeline Definitions

### Five-Layer Pipeline

```
User Question
     │
     ▼
[Layer 5] AskAiQuestionClassifierService
     │  Classifies question type:
     │    listing_facts (lines 141–1267) → includes listing + faq_answers context
     │    property_standout (line 1285+) → includes property_intelligence, location_intelligence
     │    agent_profile → includes agent_profile, agent_presets context blocks
     │    compatibility / buyer_tenant_match / suited_audience / educational / prohibited
     ▼
[Layer 4] AskAiRunnerV2Service::run()
     │  Source priority order (early-exit gates — see Section 5):
     │    1. detectFaqFieldKey() → FAQ_KEY_KEYWORD_MAP match → Guard A (faq_answers.* check)
     │       └─ FAQ empty → description fallback → if still empty → insufficient_context
     │    2. detectListingFieldKey() → LISTING_KEY_KEYWORD_MAP match → Guard B (listing.* check)
     │       └─ Field empty → description fallback → if still empty → insufficient_context
     │    3. Knowledge search (cached DB answers)
     │    4. Full LLM synthesis with all context blocks
     ▼
[Layer 3] AskAiFieldQuestionRegistryService
     │  listingFieldRegistry() — 71 approved listing.* paths (router contracts)
     │  registry() — 166 FAQ entries (faq_answers.* paths)
     ▼
[Layer 2] buildFaqAnswers() / buildChipContext()
     │  faq_answers context block from stored agent FAQ responses
     ▼
[Layer 1] AskAiContextBuilderService — CANONICAL_SOURCE_MAP
          Extracts field values from DB into context via:
          • decodeJsonField() — JSON multi-select → comma string, strips "Other"
          • resolveOtherValue() — single-select "Other" → free-text sibling EAV key
```

### Status Tag Definitions

| Tag | Definition |
|---|---|
| **Connected** | Field is context-loaded + keyword-routable + classifier-reachable; pipeline fully functional |
| **Context-loaded-but-unreachable** | In CANONICAL_SOURCE_MAP but no LISTING_KEY_KEYWORD_MAP or FAQ route — falls to OpenAI full synthesis |
| **Missing** | NOT in CANONICAL_SOURCE_MAP — cannot be extracted into context at all |
| **Phantom-routing** | LISTING_KEY_KEYWORD_MAP entry `listing.X` exists but CANONICAL_SOURCE_MAP has `Y` (not `X`) for this role — Guard B reads null even when data exists |
| **Duplicate-conflicting** | Two CANONICAL_SOURCE_MAP keys share the same EAV source — both map entries answer the same question |
| **Other-loss** | Multi-select JSON field where user's "Other" free-text is stripped by `decodeJsonField()` and not cascaded from sibling EAV key |

---

## 2. Field Inventory Methodology — True Denominators

### Why CANONICAL_SOURCE_MAP alone is an insufficient denominator

CANONICAL_SOURCE_MAP is the set of fields already extracted into context. Using it as the denominator measures only "how well are context fields routed?" — not "how much of what users submitted can Ask AI answer?"

This audit uses three denominator levels:

| Level | Definition | Use |
|---|---|---|
| **L1 — Form universe** | Every EAV meta key + native column saved by the form component (includes compensation, admin, draft fields) | Reference baseline |
| **L2 — User-answerable** | Fields representing property characteristics, listing terms, financial terms, or criteria that a client/visitor would reasonably ask Ask AI about. Excludes: agent compensation amounts/types, marketing service fee selections, exchange/crypto/NFT payment mechanics, internal admin (draft_version, workflow_type), personal contact info (email, phone), file paths, meeting scheduling fields. | **Primary denominator for this audit** |
| **L3 — CANONICAL_SOURCE_MAP** | What AskAiContextBuilderService actually extracts | Coverage numerator |

### User-answerable field counts per role (derived from form component analysis)

| Role | L1 (form universe, all EAV keys) | L2 (user-answerable) | L3 (CANONICAL_SOURCE_MAP) | L3/L2 coverage |
|---|---|---|---|---|
| Seller | ~400 EAV keys | **130** | 131 | **100.8%*** |
| Buyer | ~200 EAV keys | **43** | 26 | **60.5%** |
| Landlord | ~400 EAV keys | **132** | 106 | **80.3%** |
| Tenant | ~150 EAV keys | **65** | 17 | **26.2%** |

*Seller CANONICAL_SOURCE_MAP (131) slightly exceeds L2 (130) because some context keys are aliases sourcing from the same EAV key (e.g., `heating_and_fuel` + `heating_fuel` both from the same source). These aliases count once toward L2 but twice toward L3.

**The tenant structural gap is the most severe:** CANONICAL_SOURCE_MAP covers only 17 of 65 user-answerable tenant fields (26.2%). The majority of tenant form submissions are completely invisible to Ask AI.

---

## 3. Layer Inventory Summary (Exact Counts)

### 3.1 CANONICAL_SOURCE_MAP (Layer 1)

Exact key counts per role (source-code line-by-line count from AskAiContextBuilderService.php):

| Role | Context keys declared | File lines |
|---|---|---|
| Seller | **131** | 88–248 |
| Buyer | **26** | 255–292 |
| Landlord | **106** | 298–427 |
| Tenant | **17** | 434–461 |
| Agent Profile (CANONICAL_SOURCE_MAP declared) | **7** | 468–476 |
| **Agent Profile (AgentProfileLoader actual output)** | **47** | AgentProfileLoader lines 150–208 |
| **Agent Presets (per-preset output, AgentPresetLoader)** | **≤15** | AgentPresetLoader lines 157–173 |
| Base fields (all roles, from extractListingFields()) | **10** | listing_type, listing_id, listing_title, city, state, county, property_type, listing_status, created_at, updated_at |

### 3.2 LISTING_KEY_KEYWORD_MAP (Layer 4) — exact counts

| Metric | Count | Source |
|---|---|---|
| Total distinct `listing.*` keys | **167** | `grep "^            'listing\." AskAiRunnerV2Service.php \| wc -l` |
| Total distinct `faq_answers.*` keys in FAQ_KEY_KEYWORD_MAP | **129** | `grep "^        'faq_answers\." \| wc -l` |
| FAQ entries in registry() total | **166** | 33+19+26+12+27+22+27=166 |
| Entries that are pinned (in FAQ_KEY_KEYWORD_MAP) | **129** | 37 entries are `match_criteria` / `umbrella_only` |

### 3.3 listingFieldRegistry (Layer 3)

| Metric | Count |
|---|---|
| Total entries | **71** |
| Seller-applicable entries | **44** |
| Buyer-applicable entries | **16** |
| Landlord-applicable entries | **22** |
| Tenant-applicable entries | **10** |

*(entries span multiple roles; counts overlap)*

### 3.4 FAQ Registry by Role

| Role | Base entries | Addon entries | Total | Pinned in FAQ_KEY_KEYWORD_MAP |
|---|---|---|---|---|
| Seller | 33 | 19 (multifamily 6, business 7, land 6) | **52** | 52 |
| Landlord | 26 | 12 (commercial) | **38** | 38 |
| Buyer | 27 | 22 (commercial 8, business 7, land 7) | **49** | 49 |
| Tenant | 27 | 0 (opaque keys faq_q1–faq_q27) | **27** | 27 |
| **Total** | **113** | **53** | **166** | **129** |

---

## 4. Coverage Summary — All 9 Entity Types

Columns: **L2** = user-answerable fields from form; **L3** = CANONICAL_SOURCE_MAP fields; **L3 Coverage** = L3/L2; **Keyword-Routable** = L3 fields with LISTING_KEY_KEYWORD_MAP or FAQ_KEY_KEYWORD_MAP entry; **Final-Answer Reachability** = keyword-routable / L2 (end-to-end pipeline probability for a random user-answerable question).

---

### 4.1 — Seller Listing Entity

| Metric | Value |
|---|---|
| L2 user-answerable fields | 130 |
| L3 CANONICAL_SOURCE_MAP keys | 131 |
| L3/L2 Context Coverage | 100% (131 aliased ≥ 130 unique) |
| LISTING_KEY_KEYWORD_MAP routes for L3 keys | 112/131 = **85.5%** |
| FAQ entries (pinned) | 52/52 = **100%** |
| Context-loaded-but-unreachable (no keyword route) | 19 |
| Final-Answer Reachability (listing fields only) | **112/130 = 86.2%** |
| Final-Answer Reachability (FAQ fields) | **52/52 = 100%** |
| Missing from CANONICAL_SOURCE_MAP (L2 gaps) | ~3 (association_approval_required, waterfront_feet, home_warranty_offered) |

**Seller is the most complete entity.** The primary gaps are: (1) 19 context-loaded fields without keyword routes, (2) ~3 user-answerable fields missing from context entirely, (3) Other-loss on all multi-select fields.

---

### 4.2 — Buyer Listing Entity

| Metric | Value |
|---|---|
| L2 user-answerable fields | 43 |
| L3 CANONICAL_SOURCE_MAP keys | 26 |
| L3/L2 Context Coverage | **60.5%** |
| LISTING_KEY_KEYWORD_MAP routes for L3 keys | 21/26 = **80.8%** |
| FAQ entries (pinned) | 49/49 = **100%** |
| Context-loaded-but-unreachable (no keyword route) | 5 |
| Final-Answer Reachability (listing fields) | **21/43 = 48.8%** |
| Final-Answer Reachability (FAQ) | **49/49 = 100%** |
| Missing from CANONICAL_SOURCE_MAP (L2 gaps) | 17 user-answerable fields |

**17 missing buyer user-answerable fields (Missing — never extracted into context):**

| Form EAV key | User question it answers |
|---|---|
| `year_built` preference | How old of a home would you consider? |
| `minimum_cap_rate` | What cap rate do you require for investment? |
| `number_of_unit` | How many units are you looking for? |
| `commute_destination_zip` + `max_commute_minutes` | What is your commute limit? |
| `commute_mode` | How do you commute? |
| `flood_zone_tolerance` | Are you open to flood zone properties? |
| `purchase_purpose` | Is this for primary residence or investment? |
| `monthly_income` | What is your monthly income? |
| `number_occupant` | How many people will live there? |
| `credit_scroe_rating` (credit_score_range) | What is your credit score range? |
| `leasing_55_plus` | Are you looking at 55+ communities? |
| `non_negotiable_amenities` | What features are non-negotiable for you? |
| `min_acreage` / `total_acreage` | How many acres do you need? |
| `preferance_details` | Any other preferences you've specified? |
| `business_type_selected` | What type of business are you looking to buy? |
| `hoa_max_monthly_fee` | What's your maximum acceptable HOA fee? |
| `garage_spaces` | How many garage spaces do you need? |

---

### 4.3 — Landlord Listing Entity

| Metric | Value |
|---|---|
| L2 user-answerable fields | 132 |
| L3 CANONICAL_SOURCE_MAP keys | 106 |
| L3/L2 Context Coverage | **80.3%** |
| LISTING_KEY_KEYWORD_MAP routes for L3 keys | 75/106 = **70.8%** |
| FAQ entries (pinned) | 38/38 = **100%** |
| Context-loaded-but-unreachable (no keyword route) | 31 |
| Phantom-routing issue | 1 (listing.heating_and_fuel) |
| Final-Answer Reachability (listing fields) | **75/132 = 56.8%** |
| Final-Answer Reachability (FAQ) | **38/38 = 100%** |
| Missing from CANONICAL_SOURCE_MAP (L2 gaps) | 26 user-answerable fields |

**26 missing landlord user-answerable fields (Missing — never extracted into context):**

| Form EAV key | User question it answers |
|---|---|
| `address` | What is the property address? |
| `association_fee_amount` | What is the HOA fee amount? |
| `association_fee_frequency` | How often is the HOA fee paid? |
| `min_credit_score` | What credit score is required? |
| `income_qualification_method` | How is income verified? |
| `employment_requirement` | Is employment required? |
| `eviction_history_requirement` | Is eviction history checked? |
| `bankruptcy_requirement` | Is bankruptcy history checked? |
| `est_water_sewer_trash` | What are the estimated water/sewer costs? |
| `est_electric` | What are the estimated electric costs? |
| `est_internet` | Is internet included/estimated? |
| `est_cable` | Is cable included/estimated? |
| `leasing_restrictions` | Are there minimum lease requirements? |
| `min_lease_period` | What is the minimum lease period? |
| `max_leases_per_year` | How many times per year can it be re-leased? |
| `additional_lease_restrictions` | Any other lease restrictions? |
| `security_deposit_required` | Is a security deposit required? |
| `leasing_55_plus` | Is this a 55+ community? |
| `guests_allowed` | Are guests allowed to stay? |
| `maintenance_by` | Who handles maintenance? |
| `maintenance_response_time` | What is the maintenance response time? |
| `common_areas_access` | What common areas are accessible? |
| `bathroom_facilities` | What bathroom facilities are shared? |
| `room_size` | What are the room dimensions? |
| `zoning` (landlord form saves `zoning`) | What is the zoning? |
| `additional_parcel_ids` (partially covered) | What are the parcel IDs? |

---

### 4.4 — Tenant Listing Entity

| Metric | Value |
|---|---|
| L2 user-answerable fields | 65 |
| L3 CANONICAL_SOURCE_MAP keys | 17 |
| L3/L2 Context Coverage | **26.2%** |
| LISTING_KEY_KEYWORD_MAP routes for L3 keys | 12/17 = **70.6%** |
| FAQ entries (pinned, opaque keys) | 27/27 = **100%** |
| Context-loaded-but-unreachable (no keyword route) | 5 |
| Final-Answer Reachability (listing fields) | **12/65 = 18.5%** |
| Final-Answer Reachability (FAQ) | **27/27 = 100%** |
| Missing from CANONICAL_SOURCE_MAP (L2 gaps) | 48 user-answerable fields |

**Tenant context is structurally broken.** Only 12 of 65 user-answerable tenant fields have a complete deterministic route. The remaining 53 either produce null (5 unreachable) or are completely invisible to Ask AI (48 missing).

**48 missing tenant user-answerable fields (Missing — never extracted into context):**

*(grouped by category)*

**Location/Property:** `address`, `counties`+`state`+`zipCodes` (partially base fields), `property_type` (base field ✓)

**Property Preferences:** `pool_needed`, `pool_type`, `garage_needed`, `garage_parking_spaces`, `carport_needed`, `view_preference`/`water_view`, `non_negotiable_amenities`, `leasing_55_plus`, `minimum_heated_square` (→ no square_feet in tenant context), `total_acreage`, `min_acreage`

**Lease Preferences (Extended):** `security_deposit_budget`, `move_in_funds_available`, `first_month_rent_available`, `last_month_rent_available`, `renewal_option_requested`, `renewal_option_details`, `tenant_conditions`, `additional_tenant_lease_terms`

**Commercial Tenant:** `commercial_lease_type_preference`, `cam_nnn_preference`, `rent_escalation_preference`, `buildout_tenant_improvement_request`, `intended_business_use`, `signage_request`, `commercial_parking_access_needs`, `personal_guarantee_preference`, `commercial_approval_conditions`

**Screening/Background:** `prior_eviction`, `prior_felony`, `smoking_preference`, `service_animal`, `emotional_support_animal`, `accessibility_requirements`, `rental_purpose`

**Timing/Move-in:** `move_in_date_earliest`, `move_in_date_latest`, `current_status`

**Other Preferences:** `commute_destination_zip`, `max_commute_minutes`, `commute_mode`, `maintenance_preference`, `guests_allowed`, `number_of_units` (number_of_unit EAV key)

**Description/Notes:** `additional_details`

**Note on square_feet:** Tenant CANONICAL_SOURCE_MAP has no `square_feet` key. The form saves `minimum_heated_square`. Questions about minimum square footage always fail for tenant listings.

---

### 4.5 — Agent Profile Entity

| Metric | Value |
|---|---|
| CANONICAL_SOURCE_MAP declared fields | **7** |
| AgentProfileLoader actual output fields | **47** |
| Undocumented (loaded but not declared) | **40** |
| LISTING_KEY_KEYWORD_MAP or FAQ routes | **0** |
| Classifier route | `agent_profile` category (coarse — routes to agent profile context block) |
| Final-Answer Reachability | **0% deterministic** — all 47 fall to OpenAI normalizer |
| `brokerage` field integrity | `users.brokerage` column does not exist; always null unless profile_data.brokerage is set |

All 47 AgentProfileLoader output fields are context-loaded but have zero deterministic keyword routes. Any question about the agent (license number, service areas, availability, commission structure, reviews) relies entirely on OpenAI to synthesize from raw context.

---

### 4.6–4.9 — Agent Preset Entities (4 Roles)

The `AgentDefaultProfile` table stores one record per `(user_id, role_type, property_type)` combination. Possible `role_type` values: `seller`, `buyer`, `landlord`, `tenant`. Possible `property_type` values: `__default__`, `residential`, `income`, `commercial`, `business`, `vacant_land`. Up to 24 preset combinations per agent.

`AgentPresetLoader::summarizePreset()` extracts up to 15 fields per preset, excluding all private/financial fields (filtered by `PRIVATE_KEYS` list of 44 keys).

#### 4.6 — Seller Preset Entity

| Metric | Value |
|---|---|
| Profile_data fields stored (seller-role specific) | ~45 fields including nominal, commission_structure_type, interested_purchase_fee_type, seller_leasing_fee_type, seller_leasing_gross_* |
| AgentPresetLoader context fields (public-safe) | **≤13** (role, property_type, services, other_services, commission_structure, commission_structure_type, purchase_fee_type, lease_fee_type, retainer_fee_option, retainer_fee_application, protection_period, early_termination_fee_option, interested_in_property_management) |
| LISTING_KEY_KEYWORD_MAP routes | **0** |
| FAQ routes | **0** |
| Final-Answer Reachability | **0% deterministic** |

#### 4.7 — Buyer Preset Entity

| Metric | Value |
|---|---|
| Profile_data fields stored (buyer-role specific) | ~40 fields including interested_lease_option, lease_fee_type, lease_fee_flat/percentage cascade |
| AgentPresetLoader context fields (public-safe) | **≤12** (role, property_type, services, other_services, commission_structure, commission_structure_type, purchase_fee_type, lease_fee_type, retainer_fee_option, retainer_fee_application, protection_period, early_termination_fee_option) |
| LISTING_KEY_KEYWORD_MAP routes | **0** |
| Final-Answer Reachability | **0% deterministic** |

#### 4.8 — Landlord Preset Entity

| Metric | Value |
|---|---|
| Profile_data fields stored (landlord-role specific) | ~55 fields including interested_in_property_management, interested_in_selling, renewal_fee_type, tenant_broker_commission_structure, and commercial-specific fee structures |
| AgentPresetLoader context fields (public-safe) | **≤15** (all 15 summarizePreset() fields applicable, including interested_in_selling and interested_in_property_management_fee) |
| LISTING_KEY_KEYWORD_MAP routes | **0** |
| Final-Answer Reachability | **0% deterministic** |

#### 4.9 — Tenant Preset Entity

| Metric | Value |
|---|---|
| Profile_data fields stored (tenant-role specific) | ~40 fields (similar to buyer; interested_lease_option, lease_fee types) |
| AgentPresetLoader context fields (public-safe) | **≤12** (same as buyer preset) |
| LISTING_KEY_KEYWORD_MAP routes | **0** |
| Final-Answer Reachability | **0% deterministic** |

---

### 4.10 — Coverage Summary Matrix (All 9 Entities)

| Entity | L2 User-Answerable | L3 (Context) | L3/L2 | Keyword-Routable | Final-Answer % (listing) | Final-Answer % (FAQ) |
|---|---|---|---|---|---|---|
| Seller listing | 130 | 131 | 100% | 112/131 | 86% | 100% (52/52) |
| Buyer listing | 43 | 26 | 60.5% | 21/26 | 49% | 100% (49/49) |
| Landlord listing | 132 | 106 | 80.3% | 75/106 | 57% | 100% (38/38) |
| Tenant listing | 65 | 17 | 26.2% | 12/17 | 18% | 100% (27/27) |
| Seller preset | ~45 in profile_data | ≤13 | ~29% | 0/13 | 0% | n/a |
| Buyer preset | ~40 in profile_data | ≤12 | ~30% | 0/12 | 0% | n/a |
| Landlord preset | ~55 in profile_data | ≤15 | ~27% | 0/15 | 0% | n/a |
| Tenant preset | ~40 in profile_data | ≤12 | ~30% | 0/12 | 0% | n/a |
| Agent Profile | ~60 in profile_data | 47 | ~78% | 0/47 | 0% | n/a |

**Key takeaway:** FAQ coverage is 100% across all listing roles — the FAQ pipeline is reliable. The structural deficits are in listing.* field coverage, especially for tenant (18%) and buyer (49%). All agent/preset entities have zero deterministic routing.

---

## 5. Source Priority Verification

This section documents the exact source priority order used by `AskAiRunnerV2Service::run()` and identifies where each priority rule creates silent data loss or incorrect answers.

### 5.1 Pipeline Source Priority Order

From `run()` method analysis (read lines 1–2621 of AskAiRunnerV2Service.php):

```
Step 1: Intent normalization (Step 1a/1c) — map question to canonical key
         ├── detectFaqFieldKey() — searches FAQ_KEY_KEYWORD_MAP first
         └── detectListingFieldKey() — searches LISTING_KEY_KEYWORD_MAP second

Step 2: Guard A (FAQ path) — fires if faqFieldKey detected
         ├── ctx['faq_answers'][faqFieldKey] is non-null → return FAQ answer
         └── ctx['faq_answers'][faqFieldKey] is null → Guard A description fallback
              ├── enableDescriptionFallback = true AND description contains answer → return description answer
              └── description fallback empty → return insufficient_context

Step 3: Guard B (structured field path) — fires if listingFieldKey detected
         ├── ctx['listing'][listingFieldKey] is non-null → return structured answer
         └── ctx['listing'][listingFieldKey] is null → Guard B description fallback
              ├── description contains answer → return description answer
              └── description fallback empty → return insufficient_context

Step 4: Knowledge search — check cached DB answers (ask_ai_answers)
         └── High-confidence cache hit → return cached answer

Step 5: Full LLM synthesis — send all context blocks to OpenAI
         └── synthesizes from listing + faq_answers + property_intelligence + location_intelligence + ...
```

**Critical priority rule:** `detectFaqFieldKey()` runs BEFORE `detectListingFieldKey()`. When a user question contains phrases that match BOTH a FAQ key AND a listing.* key, the FAQ route fires first. This has cascading consequences (see Section 5.2).

---

### 5.2 FAQ-Overrides-Structured-Data

**The problem:** FAQ detection has unconditional priority over structured field detection. When a question triggers a FAQ keyword, the pipeline reads `ctx['faq_answers'][key]` first — even when a structured `ctx['listing'][key]` with the same data exists.

If the agent has NOT filled in that FAQ answer (field is null), the pipeline falls to description fallback — it does NOT fall through to the structured listing.* field. The structured data is never consulted.

**Concrete examples of FAQ-overrides-structured priority:**

| Question | FAQ match fires | Structured field bypassed | Impact if FAQ empty |
|---|---|---|---|
| "Is smoking allowed?" | `faq_answers.smoking_policy` | `listing.smoking_policy` (landlord context) | Reads description; `listing.smoking_policy` = "No smoking" ignored |
| "How is the property heated?" | `faq_answers.heating_cooling_system` | `listing.heating_and_fuel` or `listing.heating_fuel` | Description fallback fires; structured heating JSON never consulted |
| "What appliances are included?" | `faq_answers.appliances_included` | `listing.appliances` | Reads description; `listing.appliances` = "Dishwasher, Washer, Dryer" ignored |
| "What is the parking situation?" | `faq_answers.parking_arrangements` | `listing.parking_terms` (landlord) or `listing.parking_spaces` (seller) | Description fallback; structured parking data ignored |
| "Tell me about the HOA" | `faq_answers.hoa_community_highlights` | `listing.hoa_association`, `listing.hoa_fee` | Description fallback; structured HOA fee/schedule never read |
| "Is subletting allowed?" | `faq_answers.subletting_allowed` | `listing.subletting_policy` (landlord) | Description fallback; `listing.subletting_policy` ignored |
| "What are the pet rules?" | `faq_answers.pet_policy_details` | `listing.pet_policy`, `listing.pets_allowed` | Description fallback; structured pet policy never used |
| "Tell me about the neighborhood" | `faq_answers.neighborhood_highlights` | `listing.cities`, `listing.counties` | Description fallback; location data ignored |
| "Any known issues with the property?" | `faq_answers.known_defects_issues` | None (no parallel structured field) | Description fallback; correct if agent filled FAQ |
| "What security features are there?" | `faq_answers.security_features` | None | Works; correct behavior |

**Scope:** All 129 pinned FAQ_KEY_KEYWORD_MAP entries can produce this override effect. The most common overlap areas are HOA, pets, smoking, parking, heating/cooling, appliances, and neighborhood — all have both FAQ entries AND structured listing.* fields.

**Why this matters:** Agents are often slow to fill FAQ answers. When FAQ is unfilled, the structured field value is silently bypassed. Users receive "I don't have that information" or description-synthesized answers even when the structured field is correctly populated.

---

### 5.3 Description-Overrides-Structured-Data

**The problem:** Both Guard A and Guard B include a description fallback path. When the primary field (FAQ or structured) is null, the pipeline synthesizes an answer from the free-text `listing.description` field. The synthesized answer:
1. May contradict the structured field's actual value (description is informal, structured is authoritative)
2. Relies on OpenAI to extract numeric values (like prices or fees) which may be hallucinated
3. Returns an unverified answer with no "source" attribution

**Concrete examples:**

| Scenario | Pipeline path | Risk |
|---|---|---|
| `listing.seller_credit_offered` = null, description says "We'll contribute to closing costs" | Guard B → field null → description fallback → synthesizes answer | Description may state wrong dollar amount; structured `seller_credit_amount` EAV key never consulted |
| `listing.occupancy_requirement` = null, description says "owner-occupied required" | Guard B → field null → description fallback | Synthesized answer may miss occupancy nuance |
| `faq_answers.roof_age_and_condition` = null (FAQ unfilled), description says "new roof 2021" | Guard A → FAQ null → description fallback fires | Correct answer if description is accurate; no year validation |
| `listing.annual_cdd_fee` = null for a non-CDD property | Guard B → field null → description fallback fires | Description fallback may return "no CDD information" or hallucinate a dollar amount if description is ambiguous |
| `listing.lease_terms` = null (landlord), description says "flexible lease terms" | Guard B → null → description fallback | "Flexible" is vague; structured `terms_of_lease` EAV might have specific values |

**Seller-specific overlap:** The seller form stores `seller_contribution_credit_offered` (EAV) which maps to context key `seller_credit_offered`. The separate `seller_credit_amount` context key (maps to `seller_contribution_amount_details` EAV) has NO LISTING_KEY_KEYWORD_MAP route. This means the amount always falls through to OpenAI full synthesis — description-based synthesis with no structured anchor.

**Description fallback activation conditions:**
- Guard A: `enableDescriptionFallback` config is `true` (default) AND `ctx['listing']['description']` is non-null
- Guard B: same conditions
- Note: One FAQ entry (`faq_answers.seller_concessions_offered`) has an explicit docblock comment explaining that description fallback is intentional there, to surface seller concession dollar amounts from informal listing descriptions

---

### 5.4 Unsupported-When-Data-Exists

**The problem:** Data is in context (`CANONICAL_SOURCE_MAP` extracted it correctly) but the keyword chain fails to route to it. The user receives "insufficient context" or a generic LLM synthesis even though the exact answer is available in structured context.

**Category A — No LISTING_KEY_KEYWORD_MAP route (31 landlord + 5 tenant + 19 seller):**

Examples where data exists in context but no keyword fires Guard B:

| Field | Role | In Context? | Guard B fires? | Result |
|---|---|---|---|---|
| `rent_escalation_terms` | landlord | ✓ | ✗ | Falls to LLM synthesis (correct if LLM reads it; no guarantee) |
| `tenant_improvement_buildout_terms` | landlord | ✓ | ✗ | Same — no deterministic route |
| `permitted_use_restrictions` | landlord | ✓ | ✗ | Same |
| `personal_guarantee_requirement` | landlord | ✓ | ✗ | Same |
| `space_type` | landlord | ✓ | ✗ | "What type of space is this?" falls to LLM |
| `space_classification` | landlord | ✓ | ✗ | Same |
| `zoning_allows` | landlord | ✓ | ✗ | "What uses are zoning-permitted?" falls to LLM |
| `ll_maintenance_responsibility` | landlord | ✓ | ✗ | "Who handles maintenance?" falls to LLM |
| `number_of_offices` | landlord | ✓ | ✗ | "How many offices?" falls to LLM |
| `parking_needed` | tenant | ✓ | ✗ | Tenant parking preference falls to LLM |
| `utility_preference` | tenant | ✓ | ✗ | Tenant utility preference falls to LLM |
| `current_status` | tenant | ✓ | ✗ | "Are you currently renting?" falls to LLM |
| `pool_type` | seller | ✓ | ✗ | "What type of pool?" falls to LLM |
| `disclosure_flags` | seller | ✓ | ✗ | Synthetic field; correct behavior — not user-queryable |
| `electrical_service` | seller | ✓ | ✗ | "What electrical service?" falls to LLM |
| `business_lease_expiration` | seller | ✓ | ✗ | Business lease date falls to LLM |

**Category B — Phantom-routing (1 confirmed, landlord only):**

| LISTING_KEY_KEYWORD_MAP entry | Landlord context key | What Guard B reads | Result |
|---|---|---|---|
| `listing.heating_and_fuel` | `heating_fuel` (context has this key, NOT `heating_and_fuel`) | `ctx['listing']['heating_and_fuel']` = null | Guard B fires → finds null → description fallback → may return wrong answer even though `listing.heating_fuel` is correct and non-null |

Confirmed: Seller has both `heating_and_fuel` AND `heating_fuel` as separate context keys (lines 128-129 of CANONICAL_SOURCE_MAP). Landlord has only `heating_fuel` (line 365). So `listing.heating_and_fuel` is only valid for seller; it's a phantom key for landlord.

**Category C — Conditional block misfires:**

Seller LISTING_KEY_KEYWORD_MAP has entries for property-type-conditional fields: `listing.buildable` (vacant land only), `listing.annual_revenue` (business only), `listing.employee_count` (business only), `listing.gross_annual_income` (multifamily only), etc.

For a **residential** seller listing, these context keys are null (conditional block not populated). When a user asks a loosely-worded question that triggers one of these keyword entries:
- Guard B fires → reads null → description fallback → may return description text
- The system should return "not applicable for this property type" — not "insufficient context" or a description synthesis

---

### 5.5 Custom-"Other"-Loss

**The problem:** Forms offer two mechanisms for free-text "Other" values. The context builder handles them differently:

**Mechanism A — Single-select with "Other" (via `resolveOtherValue()`):**
Correctly handled. The function:
1. If primary value ≠ "Other" → returns primary value as-is
2. If primary value = "Other" (or "See Remarks", "Per Remarks", "TBD", "N/A", "None") → iterates through fallback EAV keys (the free-text sibling) → returns first non-empty value
3. If free-text sibling is also empty → returns null (data acknowledged-missing)

Fields using `resolveOtherValue()` correctly: `bedrooms`, `bathrooms`, `carport`, `garage`, `flood_zone_code`, `business_type`, `reason_for_sale`, `occupancy_requirement`

**Mechanism B — Multi-select JSON with "Other" (via `decodeJsonField()`):**
**Data loss occurs.** The function:
1. Decodes JSON array to comma-separated string
2. **Strips the literal string "Other" (case-insensitive) from the array**
3. Does NOT cascade to the sibling `other_*` EAV key
4. Returns remaining items (or null if only "Other" was selected)

The form saves "Other" selections + a companion `other_*` free-text EAV key. But `decodeJsonField()` only reads the primary JSON key, not the companion. The free-text companion key is ONLY preserved if it is explicitly included in the CANONICAL_SOURCE_MAP cascade array for that field.

**Fields with confirmed Other-loss (multi-select JSON, companion key not in CANONICAL_SOURCE_MAP cascade):**

| Form field | Other sibling saved to EAV | CANONICAL_SOURCE_MAP cascade | Result for "Other" selection |
|---|---|---|---|
| `appliances` (JSON) | `other_appliances` | `decodeJsonField($infoGet('appliances'))` only | "Other" stripped; `other_appliances` free-text lost |
| `interior_features` (JSON) | `other_interior_features` | `decodeJsonField($infoGet('interior_features'))` only | Lost |
| `building_features` (JSON) | `other_building_features` | `decodeJsonField($infoGet('building_features'))` only | Lost |
| `roof_type` (JSON) | `other_roof_type` | `decodeJsonField($infoGet('roof_type'))` only | Lost |
| `exterior_construction` (JSON) | `other_exterior_construction` | `decodeJsonField($infoGet('exterior_construction'))` only | Lost |
| `foundation` (JSON) | `other_foundation` | `decodeJsonField($infoGet('foundation'))` only | Lost |
| `water` (JSON) | `other_water` | `decodeJsonField($infoGet('water'))` only | Lost |
| `sewer` (JSON) | `other_sewer` | `decodeJsonField($infoGet('sewer'))` only | Lost |
| `utilities` (JSON) | `other_utilities` | `decodeJsonField($infoGet('utilities'))` only | Lost |
| `pool_type` (JSON) | (no companion key used by form for pool_type) | `decodeJsonField($infoGet('pool_type'))` | No companion key exists; loss N/A |
| `view_preference` / `water_view` (JSON) | `other_preferences` or `other_water_view` | `decodeJsonField` only | Lost |
| `fences` (JSON) | `other_fences` | `decodeJsonField($infoGet('fences'))` only | Lost |
| `vegetation` (JSON) | `other_vegetation` | `decodeJsonField($infoGet('vegetation'))` only | Lost |
| `sale_includes` (JSON) | `other_sale_includes` | `decodeJsonField($infoGet('sale_includes'))` only | Lost |
| `current_use` (JSON) | `other_current_use` | `decodeJsonField($infoGet('current_use'))` only | Lost |
| `landlord:heating_fuel` (JSON) | `other_heating_fuel` | `decodeJsonField($infoGet('heating_fuel'))` only | Lost |
| `landlord:air_conditioning` (JSON) | `other_air_conditioning` | `decodeJsonField($infoGet('air_conditioning'))` only | Lost |
| `landlord:property_utilities` (JSON) | `other_property_utilities` | Not in CANONICAL_SOURCE_MAP | Lost + field missing entirely |

**Fields where Other IS preserved (single-select, resolveOtherValue handles correctly):**
`bedrooms`, `bathrooms`, `carport`, `garage` (via resolveOtherValue with explicit other_* fallback keys)

**Critical example — appliances:** A landlord fills in "Appliances included: Washer, Dryer, Other" and types "custom wine cooler" in the Other field. CANONICAL_SOURCE_MAP extracts `appliances = ["Washer", "Dryer", "Other"]` → `decodeJsonField` → `"Washer, Dryer"`. The free-text "custom wine cooler" is in `other_appliances` EAV key but is never read. Ask AI cannot mention the wine cooler.

---

## 6. Per-Chat-Context Loaded-vs-Reachable Audit

The `buildForListing()` method assembles these top-level context blocks, used to construct the OpenAI payload:

| Block key | Loaded for | Source |
|---|---|---|
| `listing` | All 4 listing roles | CANONICAL_SOURCE_MAP + extractManualFields() |
| `faq_answers` | All 4 listing roles | buildFaqAnswers() — stored agent FAQ responses |
| `property_intelligence` | Seller + Landlord only | PropertyIntelligenceProfileService |
| `location_intelligence` | All 4 roles | LocationDnaIntelligenceContextService |
| `buyer_avatar` | Buyer only | buyerAvatar builder |
| `tenant_avatar` | Tenant only | tenantAvatar builder |
| `agent_profile` | All 4 listing roles | AgentProfileLoader (47 fields) |
| `agent_presets` | All 4 listing roles | AgentPresetLoader (≤15 fields/preset) |
| `compatibility` | When pair options provided | buildCompatibility() |
| `offer_analysis` | All 4 roles | buildOfferAnalysis() |

---

### 6.1 Seller Chat Context

| Block | Loaded? | Fields | Classifier route | Deterministic routes |
|---|---|---|---|---|
| `listing` | ✓ Always | 131+10 base | `listing_facts` | 112/131 (85.5%) |
| `faq_answers` | ✓ When filled | 52 FAQ slots | `listing_facts` → FAQ map | 52/52 (100%) |
| `property_intelligence` | ✓ Seller | Market data | `property_standout`, `marketing_angles` | 0 (OpenAI synthesis) |
| `location_intelligence` | ✓ | Location DNA | `property_standout`, `neighborhood_character` | 0 (OpenAI synthesis) |
| `agent_profile` | ✓ | 47 fields | `agent_profile` classifier | 0 (OpenAI) |
| `agent_presets` | ✓ | ≤13 seller preset fields | `agent_profile` | 0 (OpenAI) |

**Seller context gaps:**
- 19 listing fields unreachable via Guard B; fall to OpenAI full synthesis
- ~3 user-answerable fields missing entirely (association_approval_required, waterfront_feet, home_warranty_offered)
- Multi-select Other-loss on ~18 field types
- Conditional property-type fields (land/business/multifamily) fire Guard B → null → incorrect description fallback for wrong property types

---

### 6.2 Buyer Chat Context

| Block | Loaded? | Fields | Classifier route | Deterministic routes |
|---|---|---|---|---|
| `listing` | ✓ Always | 26+10 base | `listing_facts` | 21/26 (80.8%) |
| `faq_answers` | ✓ When filled | 49 FAQ slots | FAQ map | 49/49 (100%) |
| `buyer_avatar` | ✓ Buyer only | BuyerAvatar fields | `compatibility_signals`, `buyer_tenant_match` | 0 (OpenAI) |
| `location_intelligence` | ✓ | LocationDna | `property_standout` | 0 (OpenAI) |
| `agent_profile` | ✓ | 47 fields | `agent_profile` | 0 (OpenAI) |

**Buyer context gaps:**
- 17 user-answerable fields entirely missing from context (year_built preference, commute, min_cap_rate, flood_zone_tolerance, credit_score, etc.)
- 5 context-loaded fields with no keyword route (garage_spaces, max_hoa_fee, pets_detail/breed/weight)
- `listing.water_view` for buyer correctly sources from `view_preference` EAV (not `water_view`) — this is correct per live-DB audit
- BuyerAvatar fields (commute, purchase_purpose, non_negotiable_amenities) are loaded into buyer_avatar context block but NOT routable from the listing.* keyword map

---

### 6.3 Landlord Chat Context

| Block | Loaded? | Fields | Classifier route | Deterministic routes |
|---|---|---|---|---|
| `listing` | ✓ Always | 106+10 base | `listing_facts` | 75/106 (70.8%) |
| `faq_answers` | ✓ When filled | 38 FAQ slots | FAQ map | 38/38 (100%) |
| `property_intelligence` | ✓ Landlord | Market data | `property_standout` | 0 (OpenAI) |
| `location_intelligence` | ✓ | LocationDna | `property_standout` | 0 (OpenAI) |
| `agent_profile` | ✓ | 47 fields | `agent_profile` | 0 (OpenAI) |

**Landlord context gaps:**
- 26 user-answerable fields missing from context (address, association_fee_amount, estimated utility costs, tenant screening criteria, etc.)
- 31 context-loaded fields unreachable via Guard B (commercial structural fields, commercial lease terms)
- 1 phantom key: `listing.heating_and_fuel` fires Guard B → always reads null for landlord (landlord context has `heating_fuel`, not `heating_and_fuel`)
- `listing.lease_terms` AND `listing.terms_of_lease` are duplicate context keys (same EAV source `terms_of_lease`) — both have LISTING_KEY_KEYWORD_MAP entries — waste of 2 keyword slots
- `listing.address` absent for landlord — most-asked question on rental listings

---

### 6.4 Tenant Chat Context

| Block | Loaded? | Fields | Classifier route | Deterministic routes |
|---|---|---|---|---|
| `listing` | ✓ Always | 17+10 base | `listing_facts` | 12/17 (70.6%) |
| `faq_answers` | ✓ When filled | 27 FAQ slots (opaque) | FAQ map | 27/27 (100%) |
| `tenant_avatar` | ✓ Tenant only | TenantAvatar fields | `compatibility_signals` | 0 (OpenAI) |
| `location_intelligence` | ✓ | LocationDna | `property_standout` | 0 (OpenAI) |
| `agent_profile` | ✓ | 47 fields | `agent_profile` | 0 (OpenAI) |

**Tenant context gaps (critical):**
- 48 user-answerable fields entirely missing from context — fundamental structural gap
- 5 context-loaded fields unreachable via Guard B
- `number_of_units` context key sources from EAV `number_of_unit` (no 's') — must verify Livewire form uses matching key
- Tenant form saves `credit_scroe_rating` (with typo) — CANONICAL_SOURCE_MAP uses `credit_score_range` as context key; source must resolve this typo
- No `address`, `description`, `square_feet`, `year_built`, `smoking_preference`, `view_preference`, `additional_details` in context

---

### 6.5 Agent Profile Chat Context

| Block | Loaded? | Fields | Classifier route | Deterministic routes |
|---|---|---|---|---|
| `agent_profile` | ✓ Always | 47 fields | `agent_profile` classifier | 0 (all OpenAI) |
| `agent_presets` | ✓ | ≤15/preset × N presets | `agent_profile` | 0 (all OpenAI) |
| `listing` | Not loaded (no listing context) | — | — | — |

**Agent context gaps:**
- All 47 agent profile fields and all preset fields are OpenAI-only — no deterministic routing at all
- `brokerage` field: `getBrokerageAttribute(null)` returns null because `users.brokerage` column does not exist; answer is always blank unless profile_data.brokerage is populated
- CANONICAL_SOURCE_MAP declares only 7 agent fields (governance stub) vs. 47 actually loaded — `_sources` key in response is incomplete
- The agent_profile classifier correctly routes agent questions to the agent_profile context block; the gap is in keyword routes within that context

---

## 7. Complete Field Inventories with Status Tags

### 7.1 Seller Field Inventory (131 CANONICAL_SOURCE_MAP keys)

**Connected — 112 keys (keyword route exists):**

All seller keys not listed in the "Context-loaded-but-unreachable" section below are Connected. Key mapping (context key → LISTING_KEY_KEYWORD_MAP entry): `description`→`listing.description`, `address`→`listing.address`, `asking_price`→`listing.asking_price`, `buy_now_price`→`listing.buy_now_price`, `bedrooms`→`listing.bedrooms`, `bathrooms`→`listing.bathrooms`, `square_feet`→`listing.square_feet`, `year_built`→`listing.year_built`, `pool`→`listing.pool`, `pool_type`→*(missing, see below)*, `garage`→`listing.garage`, `garage_spaces`→*(missing)*, `carport`→`listing.carport`, `water_view`→`listing.water_view`, `lot_size`→`listing.lot_size`, `total_acreage`→`listing.total_acreage`, `lot_dimensions`→`listing.lot_dimensions`, `zoning`→`listing.zoning`, `waterfront`→`listing.waterfront`, `water_access`→`listing.water_access`, `interior_features`→`listing.interior_features`, `appliances`→`listing.appliances`, `roof_type`→`listing.roof_type`, `exterior_construction`→`listing.exterior_construction`, `foundation`→`listing.foundation`, `heating_and_fuel`→`listing.heating_and_fuel`, `heating_fuel`→`listing.heating_fuel`, `air_conditioning`→`listing.air_conditioning`, `building_features`→`listing.building_features`, `furnished`→`listing.furnished`, `utilities`→`listing.utilities`, `water`→`listing.water`, `sewer`→`listing.sewer`, `sale_provision`→`listing.sale_provision`, `offered_financing`→`listing.offered_financing`, `occupant_status`→`listing.occupant_status`, `closing_date`→`listing.closing_date`, `hoa_association`→`listing.hoa_association`, `hoa_fee`→`listing.hoa_fee`, `hoa_payment_schedule`→`listing.hoa_payment_schedule`, `association_name`→`listing.association_name`, `hoa_name`→`listing.hoa_name`, `association_fee_includes`→`listing.association_fee_includes`, `has_cdd`→`listing.has_cdd`, `annual_cdd_fee`→`listing.annual_cdd_fee`, `has_special_assessments`→`listing.has_special_assessments`, `additional_parcels`→`listing.additional_parcels`, `total_parcel_count`→`listing.total_parcel_count`, `special_assessment_amount`→`listing.special_assessment_amount`, `special_assessment_description`→`listing.special_assessment_description`, `pets_allowed`→`listing.pets_allowed`, `rental_restrictions`→`listing.rental_restrictions`, `flood_zone_code`→`listing.flood_zone_code`, `flood_zone_panel`→`listing.flood_zone_panel`, `flood_zone_date`→`listing.flood_zone_date`, `flood_insurance_required`→`listing.flood_insurance_required`, `annual_property_taxes`→`listing.annual_property_taxes`, `parcel_id`→`listing.parcel_id`, `tax_year`→`listing.tax_year`, `legal_description`→`listing.legal_description`, `building_sqft`→`listing.building_sqft`, `ceiling_height`→`listing.ceiling_height`, `parking_spaces`→`listing.parking_spaces`, `annual_noi`→`listing.annual_noi`, `price_per_sqft`→`listing.price_per_sqft`, `existing_lease_type`→`listing.existing_lease_type`, `lease_expiration`→`listing.lease_expiration`, `lease_assignable`→`listing.lease_assignable`, `property_items`→`listing.property_items`, `total_units`→`listing.total_units`, `total_buildings`→`listing.total_buildings`, `unit_mix_summary`→`listing.unit_mix_summary`, `gross_annual_income`→`listing.gross_annual_income`, `annual_operating_expenses`→`listing.annual_operating_expenses`, `annual_net_income`→`listing.annual_net_income`, `cap_rate`→`listing.cap_rate`, `rent_roll_available`→`listing.rent_roll_available`, `operating_statement_available`→`listing.operating_statement_available`, `occupancy_requirement`→`listing.occupancy_requirement`, `income_requirement`→`listing.income_requirement`, `seller_credit_offered`→`listing.seller_credit_offered`, `current_adjacent_use`→`listing.current_adjacent_use`, `water_available`→`listing.water_available`, `sewer_available`→`listing.sewer_available`, `electric_available`→`listing.electric_available`, `gas_available`→`listing.gas_available`, `telecom_available`→`listing.telecom_available`, `road_surface_type`→`listing.road_surface_type`, `front_footage`→`listing.front_footage`, `number_of_wells`→`listing.number_of_wells`, `number_of_septics`→`listing.number_of_septics`, `fences`→`listing.fences`, `vegetation`→`listing.vegetation`, `buildable`→`listing.buildable`, `easements`→`listing.easements`, `business_name`→`listing.business_name`, `year_established`→`listing.year_established`, `annual_revenue`→`listing.annual_revenue`, `gross_profit`→`listing.gross_profit`, `sde_ebitda`→`listing.sde_ebitda`, `inventory_value`→`listing.inventory_value`, `ffe_value`→`listing.ffe_value`, `reason_for_sale`→`listing.reason_for_sale`, `employee_count`→`listing.employee_count`, `financial_statements_available`→`listing.financial_statements_available`, `nda_required`→`listing.nda_required`, `business_location_leased`→`listing.business_location_leased`, `business_lease_monthly_rent`→`listing.business_lease_monthly_rent`, `business_lease_assignable`→`listing.business_lease_assignable`, `licenses`→`listing.licenses`, `sale_includes`→`listing.sale_includes`, `business_assets`→`listing.business_assets`, `current_use`→`listing.current_use`, `road_frontage`→`listing.road_frontage`

**Context-loaded-but-unreachable — 19 keys:**

| Context key | EAV source | Issue |
|---|---|---|
| `service_type` | service_type | Internal field; no question expected |
| `sold` | native:is_sold | Status field; no FAQ |
| `auction_length` | native:auction_length | Bidding period; no FAQ |
| `pool_type` | pool_type (JSON) | Sub-detail of pool; no route |
| `garage_spaces` | garage_parking_spaces | Overlaps parking_spaces alias |
| `water_source` | water (alias) | `listing.water` covers this |
| `number_of_pets_allowed` | number_of_pets | Pet sub-detail |
| `max_pet_weight` | weight_of_pets | Pet sub-detail |
| `pet_restrictions` | pet_restrictions | No route |
| `minimum_annual_net_income` | minimum_annual_net_income | Alias covered by `annual_noi` |
| `minimum_cap_rate` | minimum_cap_rate | Alias covered by `cap_rate` |
| `seller_credit_amount` | seller_contribution_amount_details | No route; critical financial field |
| `business_type` | business_type cascade | No route |
| `tax_returns_available` | tax_returns_available | Business diligence; no route |
| `business_lease_expiration` | business_lease_expiration | No route |
| `business_lease_renewal_options` | business_lease_renewal_options | No route |
| `business_lease_additional_terms` | business_lease_additional_terms | No route |
| `electrical_service` | electrical_service (JSON) | No route |
| `disclosure_flags` | synthetic:flood_zone_flag | Intentional; synthetic constant |

**Missing from CANONICAL_SOURCE_MAP — 3 user-answerable seller fields:**

| Form EAV key | Question it answers | Issue |
|---|---|---|
| `association_approval_required` + `association_approval_process` | Does the HOA need to approve buyers? | Not in CANONICAL_SOURCE_MAP |
| `waterfront_feet` | How many feet of water frontage? | Not in CANONICAL_SOURCE_MAP |
| `home_warranty_offered` | Is a home warranty included? | Not in CANONICAL_SOURCE_MAP |

**Other-loss fields (seller multi-select JSON, companion key not cascaded):**
`appliances`/`other_appliances`, `interior_features`/`other_interior_features`, `building_features`/`other_building_features`, `roof_type`/`other_roof_type`, `exterior_construction`/`other_exterior_construction`, `foundation`/`other_foundation`, `water`/`other_water`, `sewer`/`other_sewer`, `utilities`/`other_utilities`, `fences`/`other_fences`, `vegetation`/`other_vegetation`, `sale_includes`/`other_sale_includes`, `current_use`/`other_current_use`, `current_adjacent_use`/`other_current_adjacent_use`, `heating_and_fuel`/`other_heating_and_fuel`

---

### 7.2 Buyer Field Inventory (26 CANONICAL_SOURCE_MAP keys)

**Connected — 21 keys:** `description`, `address`, `max_price`, `bedrooms`, `bathrooms`, `square_feet`, `pool`, `carport`, `garage`, `water_view`, `hoa_acceptable`, `pets_allowed`, `loan_pre_approved`, `financing_type`, `inspection_period`, `closing_date`, `inspection_contingency_buyer`, `appraisal_contingency_buyer`, `financing_contingency_buyer`, `cities`, `counties`

**Context-loaded-but-unreachable — 5 keys:** `garage_spaces`, `max_hoa_fee`, `pets_detail`, `pets_breed`, `pets_weight`

**Missing — 17 user-answerable buyer fields:** year_built preference, minimum_cap_rate, number_of_unit, commute_destination_zip, max_commute_minutes, commute_mode, flood_zone_tolerance, purchase_purpose, monthly_income, number_occupant, credit_score_range, leasing_55_plus, non_negotiable_amenities, min_acreage/total_acreage, preferance_details, business_type_selected, hoa_max_monthly_fee (also in unreachable above — context has it, but no keyword route)

---

### 7.3 Landlord Field Inventory (106 CANONICAL_SOURCE_MAP keys)

**Connected — 75 keys:** `description`, `rent_amount`, `bedrooms`, `bathrooms`, `square_feet`, `year_built`, `property_items`, `condition_prop`, `appliances`, `interior_features`, `building_features`, `water_view`, `view`, `pet_policy`, `pet_deposit_fee_rent`, `parking_terms`, `utilities`, `smoking_policy`, `subletting_policy`, `available_date`, `has_hoa`, `association_name`, `association_amenities`, `annual_property_taxes`, `lease_length`, `renewal_option`, `lease_terms`, `security_deposit_amount`, `terms_of_lease`*(duplicate)*, `tenant_pays`, `rent_includes`, `lease_amount_frequency`, `first_month_rent_required`, `last_month_rent_required`, `total_move_in_funds_required`, `lot_dimensions`, `zoning`, `waterfront`, `water_access`, `roof_type`, `exterior_construction`, `foundation`, `heating_fuel`, `air_conditioning`, `water`, `sewer`, `flood_zone_code`, `flood_zone_panel`, `flood_zone_date`, `flood_insurance_required`, `parcel_id`, `tax_year`, `legal_description`, `additional_parcels`, `total_parcel_count`, `additional_parcel_ids`, `has_cdd`, `annual_cdd_fee`, `has_special_assessments`, `special_assessment_amount`, `special_assessment_description`, `commercial_lease_type`, `cam_nnn_additional_rent_charges`, `signage_rights`, `total_buildings`, `office_retail_sqft`, `ceiling_height`, `number_of_restrooms`, `road_surface_type`, `building_hours`, `access_24_7`, `shared_amenities`, `min_income_requirement`, `renewal_option_details`, `landlord_approval_conditions`, `pet_deposit_amount`, `pet_monthly_fee`, `number_of_occupants_allowed`

**Phantom-routing — 1 key:** `heating_and_fuel` (Guard B reads `ctx['listing']['heating_and_fuel']` = null; landlord context has `heating_fuel` key, not `heating_and_fuel`)

**Duplicate-conflicting — 1 pair:** `lease_terms` + `terms_of_lease` both map to EAV `terms_of_lease`; both in LISTING_KEY_KEYWORD_MAP; same answer from two routes

**Context-loaded-but-unreachable — 31 keys:** `unit_size`, `number_of_units`, `property_zip`, `pet_max_weight_lbs`, `pet_species_allowed`, `association_fee_amount`, `association_fee_frequency`, `leasing_restrictions`, `number_of_occupants`, `additional_lease_terms`, `rent_escalation_terms`, `tenant_improvement_buildout_terms`, `permitted_use_restrictions`, `commercial_parking_terms`, `personal_guarantee_requirement`, `commercial_approval_conditions`, `total_units_on_property`, `flex_space_sqft`, `space_type`, `space_classification`, `space_features`, `number_of_offices`, `number_of_conference_rooms`, `electrical_service`, `number_electric_meters`, `number_water_meters`, `number_gas_meters`, `zoning_allows`, `neighboring_tenants`, `sqft_heated_source`, `ll_maintenance_responsibility`

**Missing — 26 user-answerable fields:** address, association_fee_amount*, association_fee_frequency*, min_credit_score, income_qualification_method, employment_requirement, eviction_history_requirement, bankruptcy_requirement, est_water_sewer_trash, est_electric, est_internet, est_cable, leasing_restrictions, min_lease_period, max_leases_per_year, additional_lease_restrictions, security_deposit_required, leasing_55_plus, guests_allowed, maintenance_by, maintenance_response_time, common_areas_access, bathroom_facilities, room_size, zoning (form saves but CANONICAL_SOURCE_MAP lacks it for landlord), additional_details

*Note: `association_fee_amount` and `association_fee_frequency` ARE in CANONICAL_SOURCE_MAP for seller (as `hoa_fee` and `hoa_payment_schedule`). For landlord, the EAV keys `association_fee_amount` and `association_fee_frequency` are saved by the landlord form but have no corresponding CANONICAL_SOURCE_MAP entries in the landlord section.

---

### 7.4 Tenant Field Inventory (17 CANONICAL_SOURCE_MAP keys)

**Connected — 12 keys:** `max_rent`, `bedrooms`, `bathrooms`, `desired_lease_length`, `property_items`, `appliances`, `condition_prop`, `pet_information`, `utilities`, `tenant_pays`, `credit_score_range`, `monthly_income`

**Context-loaded-but-unreachable — 5 keys:** `parking_needed`, `utility_preference`, `current_status`, `number_of_occupants`, `number_of_units`

**EAV key typo risk:** `credit_score_range` context key sources from EAV key `credit_scroe_rating` (form saves with typo). Verify this EAV key is consistent between form and context builder.

**Missing — 48 user-answerable fields:** (see Section 4.4 for complete list)

---

## 8. Cross-Layer Gap Table

| Gap | Role(s) | Context (L3) | Keyword Route | Registry | Type | Severity |
|---|---|---|---|---|---|---|
| `address` absent from landlord CANONICAL_SOURCE_MAP | landlord | ✗ | Phantom (listing.address exists for other roles) | ✗ | Missing + Phantom | **P0** |
| `address` absent from tenant CANONICAL_SOURCE_MAP | tenant | ✗ | ✗ | ✗ | Missing | **P0** |
| `listing.heating_and_fuel` → null for landlord | landlord | heating_fuel ≠ heating_and_fuel | listing.heating_and_fuel in map | n/a | Phantom-routing | **P0** |
| `brokerage` always null (no users.brokerage column) | all | ✓ (null value) | 0 | 0 | Source dead | **P0** |
| Tenant 48 missing user-answerable fields | tenant | ✗ | n/a | ✗ | Missing (bulk) | **P1** |
| Buyer 17 missing user-answerable fields | buyer | ✗ | n/a | ✗ | Missing (bulk) | **P1** |
| Landlord 26 missing user-answerable fields | landlord | ✗ | n/a | ✗ | Missing (bulk) | **P1** |
| FAQ fires before structured field (10+ topic overlaps) | all | ✓ (structured) | FAQ wins | n/a | FAQ-overrides-structured | **P1** |
| Multi-select Other-loss on ~18 field types | seller/landlord | ✓ (stripped) | n/a | n/a | Other-loss | **P1** |
| Agent Profile 40 undocumented fields | all | ✓ loaded | 0 | 0 | Undocumented | **P1** |
| All agent/preset entities 0 deterministic routes | all | ✓ loaded | 0 | 0 | Fully OpenAI | **P1** |
| Conditional block fields → "insufficient context" wrong prop type | seller | ✓ (null for wrong type) | Present | Present | Phantom misfire | **P2** |
| Landlord 31 commercial fields unreachable | landlord | ✓ | ✗ | ✗ | Unreachable | **P2** |
| Tenant 5 fields unreachable | tenant | ✓ | ✗ | ✗ | Unreachable | **P2** |
| Seller 19 fields unreachable | seller | ✓ | ✗ | ✗ | Unreachable | **P2** |
| `lease_terms` + `terms_of_lease` duplicate (landlord) | landlord | Both ✓ same source | Both in map | Only lease_terms | Duplicate-conflicting | **P2** |
| `listing.lot_size` / `listing.lot_acreage` phrase collision | seller | Both ✓ | Both in map | Both in registry | Collision | **P2** |
| Description fallback returns unverified answers | all | ✓ (structured bypassed) | n/a | n/a | Description-overrides | **P2** |
| Tenant opaque FAQ keys (faq_q1–faq_q27) | tenant | n/a | ✓ pinned | ✓ | Tech debt | **P4** |
| Tenant EAV typo: `credit_scroe_rating` | tenant | ✓ if typo matches | ✓ | ✓ | Source key risk | **P3** |
| Agent profile _sources stub (7 declared vs 47 actual) | all | ✓ loaded | 0 | 0 | Governance gap | **P3** |

---

## 9. Priority Issue List

### P0 — Silent Failures (data exists but pipeline always fails or returns null)

| # | Issue | Root Cause |
|---|---|---|
| P0-1 | `listing.heating_and_fuel` Guard B null for all landlord listings | Landlord CANONICAL_SOURCE_MAP has context key `heating_fuel`, not `heating_and_fuel`; Guard B reads the wrong key |
| P0-2 | "What is the address?" fails for all landlord listings | `address` not in landlord CANONICAL_SOURCE_MAP; landlord form saves native `address` column but context builder never extracts it for landlord |
| P0-3 | "What is the address?" fails for all tenant listings | Same root cause; `address` not in tenant CANONICAL_SOURCE_MAP |
| P0-4 | `agent_profile.brokerage` always null | `getBrokerageAttribute(null)` called on users model; `users.brokerage` native column does not exist |

### P1 — Structural Coverage Gaps

| # | Issue | Roles |
|---|---|---|
| P1-1 | Tenant CANONICAL_SOURCE_MAP covers only 17/65 (26.2%) of user-answerable tenant fields | tenant |
| P1-2 | Buyer CANONICAL_SOURCE_MAP covers only 26/43 (60.5%) of user-answerable buyer fields | buyer |
| P1-3 | Landlord CANONICAL_SOURCE_MAP covers 106/132 (80.3%); 26 user-answerable fields missing | landlord |
| P1-4 | FAQ-overrides-structured priority silently bypasses structured fields when FAQ is null | all |
| P1-5 | Multi-select Other-loss on ~18 field types — free-text silently dropped from context | seller, landlord |
| P1-6 | 40 AgentProfileLoader fields not declared in CANONICAL_SOURCE_MAP (governance gap) | all |
| P1-7 | All agent/preset entities have 0 deterministic routes — fully OpenAI-dependent | all |

### P2 — Pipeline Incomplete

| # | Issue | Roles |
|---|---|---|
| P2-1 | 31 landlord commercial/structural context fields unreachable (no keyword route) | landlord |
| P2-2 | 19 seller context fields unreachable | seller |
| P2-3 | 5 tenant context fields unreachable | tenant |
| P2-4 | Conditional property-type fields fire Guard B → null → description fallback for wrong property type | seller |
| P2-5 | `lease_terms` + `terms_of_lease` duplicate routes for landlord (same EAV source) | landlord |
| P2-6 | `lot_size` vs `lot_acreage` phrase collision | seller |
| P2-7 | Description fallback returns unverified synthesis when structured field is empty | all |

### P3 — Risk / Tech Debt

| # | Issue |
|---|---|
| P3-1 | Tenant EAV key `credit_scroe_rating` (form typo) must match CANONICAL_SOURCE_MAP source key `credit_scroe_rating` exactly — verify both use same spelling |
| P3-2 | Agent profile CANONICAL_SOURCE_MAP stub documents only 7 of 47 actual fields |
| P3-3 | `heating_and_fuel` + `heating_fuel` in seller context are redundant (same EAV source, two routes) — not harmful but confusing |

### P4 — Maintainability

| # | Issue |
|---|---|
| P4-1 | Tenant FAQ uses opaque keys faq_q1–faq_q27; no semantic label in key name |
| P4-2 | Buyer and tenant CANONICAL_SOURCE_MAP sections are structurally thin; no systematic coverage check exists |
| P4-3 | `listing.number_of_units` tenant context key sources from EAV `number_of_unit` — verify form and builder use same EAV key spelling |

---

## 10. Top 25 Highest-Risk Gaps

Ranked by: user-facing question frequency × silence of failure × breadth of affected listings.

| Rank | Gap | Why High Risk |
|---|---|---|
| 1 | **Tenant 48 missing context fields** (P1-1) | 74% of tenant user-answerable fields have zero context. Any question about address, description, smoking preference, furnished preference, view, move-in date, commercial preferences, screening background, or commute has no structured answer. Falls to OpenAI with no grounding. |
| 2 | **FAQ-overrides-structured (10+ topic overlaps)** (P1-4) | Questions about smoking, heating, appliances, HOA, pets, parking, subletting all trigger FAQ keywords first. If the FAQ field is null (agent hasn't filled it), the structured listing field is skipped entirely. Affects ALL listing roles. Structural pipeline flaw. |
| 3 | **"What is the address?" fails for landlord + tenant** (P0-2, P0-3) | Most-asked question on rental listings. `address` is saved to the DB but never extracted into context for either rental role. Ask AI cannot answer this fundamental question. |
| 4 | **Multi-select Other-loss (~18 field types)** (P1-5) | Custom free-text entered into "Other" fields (custom appliances, custom building features, unusual roof materials, etc.) is stripped by `decodeJsonField()` and never appears in context. Affects seller and landlord — any listing with non-standard specifications loses that detail. |
| 5 | **`listing.heating_and_fuel` phantom route — landlord** (P0-1) | "How is the property heated?" is a high-frequency rental question. For landlord listings, the keyword phrase routes Guard B to `ctx['listing']['heating_and_fuel']` = null (landlord has `heating_fuel` not `heating_and_fuel`). Returns "insufficient context" even though `listing.heating_fuel` is populated with correct data. |
| 6 | **Buyer 17 missing context fields** (P1-2) | Year_built preference, commute requirements, flood zone tolerance, income, credit score, investment criteria, non-negotiable amenities — all completely invisible to Ask AI for buyer listings. Buyer context is structurally the second-thinnest after tenant. |
| 7 | **`agent_profile.brokerage` always null** (P0-4) | Brokerage is a common agent profile question. `users.brokerage` column does not exist; field is only non-null when `profile_data.brokerage` is explicitly set. Most agent profiles return null for this field. |
| 8 | **All agent/preset entities: 0 deterministic routes** (P1-7) | Agent profile and preset data (47 profile fields, ≤15 preset fields per role) are loaded into context but every single agent question falls to OpenAI normalizer. No quality guarantees, no Guard B protection, no listingFieldRegistry contract. |
| 9 | **Description fallback returns unverified answers** (P2-7) | When structured/FAQ field is null, description fallback synthesizes from informal listing text. Prices, amounts, and specific terms extracted from description may be hallucinated or imprecise. Currently active for all Guard A + Guard B null cases. |
| 10 | **31 landlord commercial/structural fields unreachable** (P2-1) | TI allowance, rent escalation, permitted use, personal guarantee, space type, space classification, number of offices/conference rooms — 31 fields are in context but no LISTING_KEY_KEYWORD_MAP entry routes to them. Commercial landlord questions fall to OpenAI synthesis. |
| 11 | **Conditional property-type fields → "insufficient context" for wrong type** (P2-4) | A seller listing for a Residential property has null values for `buildable`, `annual_revenue`, `employee_count`, etc. If the question triggers those keyword phrases, Guard B fires, reads null, and returns "insufficient context" instead of "not applicable for this residential property." |
| 12 | **Landlord association_fee_amount/frequency missing** (P1-3) | HOA fee amount and payment schedule are collected by the landlord form but not in landlord CANONICAL_SOURCE_MAP. "What is the HOA fee?" for a landlord listing always returns null. |
| 13 | **Landlord estimated utility costs (4 fields) missing** (P1-3) | `est_water_sewer_trash`, `est_electric`, `est_internet`, `est_cable` — landlords provide these for tenant visibility but Ask AI cannot access them. Common question: "What are the estimated utility costs?" |
| 14 | **Landlord tenant screening criteria missing** (P1-3) | `min_credit_score`, `income_qualification_method`, `employment_requirement`, `eviction_history_requirement`, `bankruptcy_requirement` — screening criteria are filled by landlords and are major tenant decision factors, but all are missing from context. |
| 15 | **Seller `seller_credit_amount` unreachable** (P2-2) | `seller_credit_offered` is connected (listing.seller_credit_offered), but the dollar amount (`seller_credit_amount` → EAV `seller_contribution_amount_details`) has no LISTING_KEY_KEYWORD_MAP route. Seller credit questions get "yes/no" but not the dollar amount. |
| 16 | **Tenant `square_feet` missing from context** (P1-1) | Tenant form collects `minimum_heated_square` but CANONICAL_SOURCE_MAP has no `square_feet` context key for tenant. "What is the minimum square footage you need?" always fails. |
| 17 | **Landlord `address` missing** (P1-3) | Same as rank 3 for tenant — "What is the property address?" fails for landlord despite address being in DB. |
| 18 | **Landlord leasing restrictions missing** (P1-3) | `leasing_restrictions`, `min_lease_period`, `max_leases_per_year`, `additional_lease_restrictions` — HOA/community leasing rules are missing from landlord context. |
| 19 | **`listing.lot_size` / `listing.lot_acreage` phrase collision** (P2-6) | "How many acres?" and "what is the acreage?" match phrases from both map entries. Whichever entry appears first in the PHP array wins. The winner may not be the contextually correct key for the property type. |
| 20 | **Tenant `move_in_date` preferences missing** (P1-1) | `move_in_date_earliest`, `move_in_date_latest` saved by form; not in context. "When are you looking to move in?" always fails for tenant listings. |
| 21 | **Tenant screening background missing** (P1-1) | `prior_eviction`, `prior_felony`, `smoking_preference`, `accessibility_requirements` — background information filled by tenants for landlord match is not in context. |
| 22 | **Landlord `security_deposit_required` boolean missing** (P1-3) | `security_deposit_required` flag is separate from `security_deposit_amount`. The amount is in context but not whether a deposit is actually required. |
| 23 | **40 agent profile fields undocumented in CANONICAL_SOURCE_MAP** (P1-6) | `_sources` key in every Ask AI response is incomplete for agent profile. Tools/tests asserting against `_sources` for agent profile data get incorrect results. |
| 24 | **Tenant commercial lease preferences all missing** (P1-1) | `commercial_lease_type_preference`, `cam_nnn_preference`, `rent_escalation_preference`, `buildout_tenant_improvement_request`, `intended_business_use` — 9 commercial-tenant fields have zero context. Commercial tenant listings are almost entirely unsupported. |
| 25 | **`lease_terms` + `terms_of_lease` duplicate routes (landlord)** (P2-5) | Two LISTING_KEY_KEYWORD_MAP entries answer the same question from the same EAV key. Wastes two keyword routing slots, and the `terms_of_lease` map entry (lines ~350) conflicts with LISTING_KEY_KEYWORD_MAP uniqueness tests. |

---

## 11. Implementation Plan

Phases ordered by severity. No code changes were made during this audit.

### Phase A — P0 Critical Silent Failures (est. ~1 day)

**A1. Fix landlord `heating_and_fuel` phantom key**
- `AskAiContextBuilderService.php` landlord section (line ~365): Add `'heating_and_fuel' => 'heating_and_fuel'` as a context key alongside the existing `heating_fuel`, so landlord context has both keys (same as seller).
- Alternatively: update LISTING_KEY_KEYWORD_MAP to ensure all landlord-facing heating phrases route to `listing.heating_fuel` only (not `listing.heating_and_fuel`).
- Test: assert `ctx['listing']['heating_and_fuel']` is non-null for a landlord listing with heating data.

**A2. Add `address` to landlord and tenant CANONICAL_SOURCE_MAP**
- Landlord section (line ~300): Add `'address' => 'native:address'`
- Tenant section (line ~434): Add `'address' => 'native:address'`
- Extend `listing.address` listingFieldRegistry roles from `['seller', 'buyer']` to all 4 roles.
- Test: assert address is non-null in context for landlord + tenant.

**A3. Fix `brokerage` null source**
- `AgentProfileLoader.php` line ~153: `getBrokerageAttribute(null)` returns null because `users.brokerage` column does not exist. Either: (a) add migration to add `users.brokerage` column, or (b) remove the native column fallback and source from `profile_data.brokerage` only.
- Test: assert brokerage is non-null for agent with `profile_data.brokerage` set.

---

### Phase B — P1 Structural Coverage Expansion (~5 days)

**B1. Expand tenant CANONICAL_SOURCE_MAP (Priority order)**

Add to tenant section (lines 434–461), one at a time with registry + keyword route + test per field:

**Tier 1 (highest impact):** `address`, `description` (→ EAV `additional_details`), `square_feet` (→ `minimum_heated_square`), `smoking_preference`, `current_status`, `move_in_date` (→ `move_in_date_earliest` + `move_in_date_latest`)

**Tier 2:** `view_preference`, `pool`, `garage`, `carport`, `water_view`, `year_built`, `prior_eviction`, `prior_felony`, `accessibility_requirements`, `rental_purpose`

**Tier 3 (commercial tenant):** `commercial_lease_type_preference`, `intended_business_use`, `buildout_tenant_improvement_request`, `cam_nnn_preference`, `rent_escalation_preference`

For each: (a) add CANONICAL_SOURCE_MAP entry, (b) add LISTING_KEY_KEYWORD_MAP entry, (c) add listingFieldRegistry entry with ≥2 natural questions, (d) write pipeline test.

**B2. Expand buyer CANONICAL_SOURCE_MAP (Priority order)**

**Tier 1:** `year_built`, `flood_zone_tolerance`, `purchase_purpose`, `monthly_income`, `number_occupant`, `credit_score_range`

**Tier 2:** `minimum_cap_rate`, `number_of_unit`, `commute_destination_zip` + `max_commute_minutes` + `commute_mode`, `leasing_55_plus`, `non_negotiable_amenities`, `min_acreage`

**B3. Add missing landlord context fields**

**Critical:** `association_fee_amount`, `association_fee_frequency`, `address`*(already in A2)*, `est_water_sewer_trash`, `est_electric`, `est_internet`, `est_cable`

**Screening criteria:** `min_credit_score`, `income_qualification_method`, `employment_requirement`, `eviction_history_requirement`, `bankruptcy_requirement`

**Leasing:** `leasing_restrictions`, `min_lease_period`, `max_leases_per_year`, `additional_lease_restrictions`, `security_deposit_required`

**B4. Fix FAQ-overrides-structured for critical topic overlaps**

For each topic where FAQ and structured fields overlap (smoking, heating, appliances, HOA, pets, parking, subletting), add a fallthrough path: if FAQ field is null, do NOT fire description fallback — instead fall through to Guard B for the parallel structured listing.* field.

Key implementation: after Guard A FAQ-null check, before description fallback, check if a parallel `listing.*` key exists for the same topic. If so, route to Guard B instead of description.

**B5. Document all 47 AgentProfileLoader fields in CANONICAL_SOURCE_MAP**
- Update agent_profile section (lines 468–476) to list all 47 fields with their sources.
- This is documentation only — loader already extracts them correctly.

---

### Phase C — P1 Other-Loss Fixes (~1.5 days)

**C1. Cascade Other-text for multi-select fields**

For each field using `decodeJsonField()`, also read the companion `other_*` EAV key and append it to the decoded string if non-empty. Implementation pattern (from resolveOtherValue for guidance):

```php
// Pattern for multi-select + Other companion:
'appliances' => implode(', ', array_filter([
    $this->decodeJsonField($infoGet('appliances')),
    $infoGet('other_appliances'),
])),
```

Apply to: `appliances`/`other_appliances`, `interior_features`/`other_interior_features`, `building_features`/`other_building_features`, `roof_type`/`other_roof_type`, `exterior_construction`/`other_exterior_construction`, `foundation`/`other_foundation`, `water`/`other_water`, `sewer`/`other_sewer`, `utilities`/`other_utilities`, `fences`/`other_fences`, `vegetation`/`other_vegetation`, `sale_includes`/`other_sale_includes`, `heating_and_fuel`/`other_heating_and_fuel` (seller), `heating_fuel`/`other_heating_fuel` (landlord)

---

### Phase D — P2 Keyword Route Additions (~2 days)

**D1. Add keyword routes for 10 highest-priority landlord commercial fields:**
`listing.rent_escalation_terms`, `listing.tenant_improvement_buildout_terms`, `listing.permitted_use_restrictions`, `listing.personal_guarantee_requirement`, `listing.commercial_parking_terms`, `listing.space_type`, `listing.zoning_allows`, `listing.ll_maintenance_responsibility`, `listing.number_of_offices`, `listing.landlord_approval_conditions`*(already present)*.

For each: add LISTING_KEY_KEYWORD_MAP entry, add listingFieldRegistry entry, write test.

**D2. Fix conditional property-type field misfires**
Add property_type guard in Guard B: before returning `insufficient_context` for null conditional fields, check `ctx['listing']['property_type']` and return "not applicable for [property type] listings" instead.

**D3. Resolve `lot_size` / `lot_acreage` phrase collision**
Assign non-overlapping phrase sets to each entry. Regression test: assert "how many acres" → `listing.lot_acreage` and "lot size in square feet" → `listing.lot_size`.

**D4. Remove `terms_of_lease` duplicate from landlord context**
Remove `'terms_of_lease' => 'terms_of_lease'` from landlord CANONICAL_SOURCE_MAP (keep `lease_terms`). Remove `listing.terms_of_lease` from LISTING_KEY_KEYWORD_MAP or alias it to `listing.lease_terms`. Verify map-key-uniqueness test passes.

---

### Phase E — P3/P4 Tech Debt (~1 day)

**E1. Add semantic alias map for tenant FAQ opaque keys (faq_q1–faq_q27)**

**E2. Verify tenant EAV key spelling: `credit_scroe_rating`**
Grep Livewire form saveMeta calls and CANONICAL_SOURCE_MAP source key — must match exactly.

**E3. Verify `number_of_unit` vs `number_of_units` EAV key**
Confirm tenant Livewire form uses `number_of_unit` (no 's') and CANONICAL_SOURCE_MAP uses the same spelling.

**E4. Add agent preset keyword routes for high-priority fields**
Add LISTING_KEY_KEYWORD_MAP entries for: `agent_presets.commission_structure_type`, `agent_presets.services`, `agent_presets.retainer_fee_option`, `agent_presets.protection_period`.
*(Note: these require extending the LISTING_KEY_KEYWORD_MAP namespace to `agent_presets.*` — design review needed.)*

---

### Phase F — Validation (~1 day)

For each change in Phases A–E:
1. Integration test: assert context key is non-null for seeded listing/agent.
2. Pipeline test: assert user question routes deterministically and returns non-degraded response.
3. Run existing coverage harness, duplicate-key uniqueness test, classifier boundary tests.
4. Run Other-loss regression: assert `ctx['listing']['appliances']` contains "custom" text when form has Other + companion text.

---

### Effort Summary

| Phase | Scope | Estimate |
|---|---|---|
| A | P0 critical silent failures (3 fixes) | ~1 day |
| B | P1 structural coverage + FAQ override fix | ~5 days |
| C | P1 Other-loss multi-select cascade | ~1.5 days |
| D | P2 keyword routes + collision fixes | ~2 days |
| E | P3/P4 tech debt | ~1 day |
| F | Validation and test coverage | ~1 day |
| **Total** | | **~11.5 days** |

---

## 12. Appendices

### Appendix A — CANONICAL_SOURCE_MAP Field Counts by Line Range

| Role | File | Lines | Exact key count |
|---|---|---|---|
| Seller | AskAiContextBuilderService.php | 88–248 | **131** |
| Buyer | AskAiContextBuilderService.php | 255–292 | **26** |
| Landlord | AskAiContextBuilderService.php | 298–427 | **106** |
| Tenant | AskAiContextBuilderService.php | 434–461 | **17** |
| Agent Profile (declared) | AskAiContextBuilderService.php | 468–476 | **7** |
| Agent Profile (actual, AgentProfileLoader) | AgentProfileLoader.php | 150–208 | **47** |

### Appendix B — LISTING_KEY_KEYWORD_MAP (167 distinct keys)

`listing.access_24_7`, `listing.additional_parcel_ids`, `listing.additional_parcels`, `listing.address`, `listing.air_conditioning`, `listing.annual_cdd_fee`, `listing.annual_net_income`, `listing.annual_noi`, `listing.annual_operating_expenses`, `listing.annual_property_taxes`, `listing.annual_revenue`, `listing.appliances`, `listing.appraisal_contingency_buyer`, `listing.asking_price`, `listing.association_amenities`, `listing.association_fee_includes`, `listing.association_name`, `listing.available_date`, `listing.bathrooms`, `listing.bedrooms`, `listing.buildable`, `listing.building_features`, `listing.building_hours`, `listing.building_sqft`, `listing.business_assets`, `listing.business_lease_assignable`, `listing.business_lease_monthly_rent`, `listing.business_location_leased`, `listing.business_name`, `listing.buy_now_price`, `listing.cam_nnn_additional_rent_charges`, `listing.cap_rate`, `listing.carport`, `listing.ceiling_height`, `listing.cities`, `listing.closing_date`, `listing.commercial_lease_type`, `listing.condition_prop`, `listing.counties`, `listing.credit_score_range`, `listing.current_adjacent_use`, `listing.current_use`, `listing.description`, `listing.desired_lease_length`, `listing.easements`, `listing.electric_available`, `listing.employee_count`, `listing.existing_lease_type`, `listing.exterior_construction`, `listing.fences`, `listing.ffe_value`, `listing.financial_statements_available`, `listing.financing_contingency_buyer`, `listing.financing_type`, `listing.first_month_rent_required`, `listing.flood_insurance_required`, `listing.flood_zone_code`, `listing.flood_zone_date`, `listing.flood_zone_panel`, `listing.foundation`, `listing.front_footage`, `listing.furnished`, `listing.garage`, `listing.gas_available`, `listing.gross_annual_income`, `listing.gross_profit`, `listing.has_cdd`, `listing.has_hoa`, `listing.has_special_assessments`, `listing.heating_and_fuel`, `listing.heating_fuel`, `listing.hoa_acceptable`, `listing.hoa_association`, `listing.hoa_fee`, `listing.hoa_name`, `listing.hoa_payment_schedule`, `listing.income_requirement`, `listing.inspection_contingency_buyer`, `listing.inspection_period`, `listing.interior_features`, `listing.inventory_value`, `listing.landlord_approval_conditions`, `listing.last_month_rent_required`, `listing.lease_amount_frequency`, `listing.lease_assignable`, `listing.lease_expiration`, `listing.lease_length`, `listing.lease_terms`, `listing.legal_description`, `listing.licenses`, `listing.loan_pre_approved`, `listing.lot_acreage`, `listing.lot_dimensions`, `listing.lot_size`, `listing.max_price`, `listing.max_rent`, `listing.min_income_requirement`, `listing.monthly_income`, `listing.nda_required`, `listing.number_of_occupants_allowed`, `listing.number_of_restrooms`, `listing.number_of_septics`, `listing.number_of_wells`, `listing.occupancy_requirement`, `listing.occupant_status`, `listing.offered_financing`, `listing.office_retail_sqft`, `listing.operating_statement_available`, `listing.parcel_id`, `listing.parking_spaces`, `listing.parking_terms`, `listing.pet_deposit_amount`, `listing.pet_deposit_fee_rent`, `listing.pet_information`, `listing.pet_monthly_fee`, `listing.pet_policy`, `listing.pets_allowed`, `listing.pool`, `listing.price_per_sqft`, `listing.property_items`, `listing.property_type`, `listing.reason_for_sale`, `listing.renewal_option`, `listing.renewal_option_details`, `listing.rental_restrictions`, `listing.rent_amount`, `listing.rent_includes`, `listing.rent_roll_available`, `listing.road_frontage`, `listing.road_surface_type`, `listing.roof_type`, `listing.sale_includes`, `listing.sale_provision`, `listing.sde_ebitda`, `listing.security_deposit_amount`, `listing.seller_credit_offered`, `listing.sewer`, `listing.sewer_available`, `listing.shared_amenities`, `listing.signage_rights`, `listing.smoking_policy`, `listing.special_assessment_amount`, `listing.special_assessment_description`, `listing.square_feet`, `listing.subletting_policy`, `listing.tax_year`, `listing.telecom_available`, `listing.tenant_pays`, `listing.terms_of_lease`, `listing.total_acreage`, `listing.total_buildings`, `listing.total_move_in_funds_required`, `listing.total_parcel_count`, `listing.total_units`, `listing.unit_mix_summary`, `listing.utilities`, `listing.vegetation`, `listing.view`, `listing.water`, `listing.water_access`, `listing.water_available`, `listing.waterfront`, `listing.water_source`, `listing.water_view`, `listing.year_built`, `listing.year_established`, `listing.zoning`

### Appendix C — listingFieldRegistry Keys (71 total)

`listing.address`, `listing.annual_cdd_fee`, `listing.annual_noi`, `listing.annual_property_taxes`, `listing.annual_revenue`, `listing.appliances`, `listing.appraisal_contingency_buyer`, `listing.asking_price`, `listing.association_amenities`, `listing.available_date`, `listing.bathrooms`, `listing.bedrooms`, `listing.business_assets`, `listing.business_lease_assignable`, `listing.business_lease_monthly_rent`, `listing.business_location_leased`, `listing.business_name`, `listing.carport`, `listing.closing_date`, `listing.condition_prop`, `listing.credit_score_range`, `listing.description`, `listing.desired_lease_length`, `listing.employee_count`, `listing.ffe_value`, `listing.financial_statements_available`, `listing.financing_contingency_buyer`, `listing.financing_type`, `listing.flood_zone_code`, `listing.garage`, `listing.gross_annual_income`, `listing.gross_profit`, `listing.has_hoa`, `listing.hoa_acceptable`, `listing.hoa_association`, `listing.hoa_fee`, `listing.inspection_contingency_buyer`, `listing.inspection_period`, `listing.inventory_value`, `listing.landlord_approval_conditions`, `listing.lease_length`, `listing.lease_terms`, `listing.licenses`, `listing.loan_pre_approved`, `listing.max_price`, `listing.max_rent`, `listing.nda_required`, `listing.parking_terms`, `listing.pet_deposit_fee_rent`, `listing.pet_information`, `listing.pet_policy`, `listing.pets_allowed`, `listing.pool`, `listing.property_type`, `listing.reason_for_sale`, `listing.renewal_option`, `listing.rental_restrictions`, `listing.rent_amount`, `listing.sale_includes`, `listing.sde_ebitda`, `listing.smoking_policy`, `listing.square_feet`, `listing.subletting_policy`, `listing.tenant_pays`, `listing.total_buildings`, `listing.total_units`, `listing.unit_mix_summary`, `listing.utilities`, `listing.water_view`, `listing.year_built`, `listing.year_established`

### Appendix D — Agent Preset Role-to-Context Mapping

| Preset role | Profile_data fields stored (role-specific) | AgentPresetLoader public-safe context fields |
|---|---|---|
| Seller | ~45: nominal, commission_structure_type, interested_purchase_fee_type, seller_leasing_fee_type, seller_leasing_gross_* | ≤13: role, property_type, services, other_services, commission_structure, commission_structure_type, purchase_fee_type, lease_fee_type, retainer_fee_option, retainer_fee_application, protection_period, early_termination_fee_option, interested_in_property_management |
| Buyer | ~40: interested_lease_option, lease_fee_type cascade, purchase_fee_type cascade | ≤12: role, property_type, services, other_services, commission_structure, commission_structure_type, purchase_fee_type, lease_fee_type, retainer_fee_option, retainer_fee_application, protection_period, early_termination_fee_option |
| Landlord | ~55: interested_in_property_management, interested_in_selling, renewal_fee_type, tenant_broker_commission_structure, commercial-specific fee structures | ≤15: all 15 summarizePreset() fields applicable (includes interested_in_selling, interested_in_property_management, interested_in_property_management_fee) |
| Tenant | ~40: similar to buyer | ≤12: same as buyer preset |

**AgentPresetLoader PRIVATE_KEYS (44 keys excluded from all preset context):**
bio, awards_recognition, what_sets_you_apart, why_hire_you, review_1, review_2, review_3, reviews_links, intro_video_url, website_link, presentation_link, social_media, marketing_plan, cities_served, counties_served, neighborhoods_served, primary_areas_served, areas_notes, license_no, nar_id, year_licensed, years_experience, is_full_time, transactions_last_12_months, availability_status, avg_response_time, communication_style, preferred_contact_method, evenings_available, weekends_available, email, phone, business_card_upload_path, business_card_link, purchase_fee_percentage, purchase_fee_flat, lease_fee_percentage, lease_fee_flat, retainer_fee_amount, referral_fee_percent, early_termination_fee_amount, nominal, first_name, last_name

### Appendix E — FAQ Registry Counts by Role

| Role | Base entries | Addon entries | Total | In FAQ_KEY_KEYWORD_MAP | Not pinned |
|---|---|---|---|---|---|
| Seller | 33 | 19 (mf×6, biz×7, land×6) | **52** | 52 | 0 |
| Landlord | 26 | 12 (commercial×12) | **38** | 38 | 0 |
| Buyer | 27 | 22 (com×8, biz×7, land×7) | **49** | 49 | 0 |
| Tenant | 27 | 0 (opaque faq_q1–faq_q27) | **27** | 27 | 0 |
| **Total** | **113** | **53** | **166** | **129**† | **37** |

†37 entries are `match_criteria` or `umbrella_only` status — routed by classifier context, not by keyword phrase; not in FAQ_KEY_KEYWORD_MAP.

---

*End of Phase 1 Discovery Audit. No code changes were made during this audit.*
