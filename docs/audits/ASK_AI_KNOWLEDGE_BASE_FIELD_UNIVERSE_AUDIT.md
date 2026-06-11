# Ask AI Knowledge Base Field Universe Audit

**Date:** 2026-06-10 (revised 2026-06-11)  
**Scope:** All four Create Offer Listing forms — Seller, Buyer, Landlord, Tenant  
**Purpose:** Definitive blueprint for making Ask AI database-first. Every field from every form is catalogued, traced end-to-end through the pipeline, classified, and mapped to the proposed knowledge snapshot architecture.  
**Status:** No code changes. Audit and architecture document only.

**Revision note (2026-06-11):** Added Sections 16–20. Section 16 is the Implementation Roadmap (5 phases with acceptance criteria and effort estimates). Section 17 is the Complete Form Field Universe cataloguing 1,786 raw `saveMeta()` key occurrences across all four forms (Seller=473, Buyer=326, Landlord=512, Tenant=475 — unique keys lower due to cross-role reuse). All Section 17 field tables include a Tab column sourced from Livewire PHP validation-block comments. Section 18 provides full question templates (primary + 2 alternate phrasings + answer template) for all 279 currently-wired DATABASE-FIRST/structural fields, all 168+ FAQ keys, and ~145 additional new-gap fields (Sections 18.12–18.13). Sections 19 and 20 complete the Phase 5 scope and field-universe reconciliation.

---

## Cross-Reference Index

This document synthesises and extends the following prior audits. Readers should consult them for the underlying evidence.

| Prior Audit | Content |
|---|---|
| `ASK_AI_ALL_OFFER_TYPE_FIELD_EXTRACTION_AUDIT.md` | Detailed PASS/FAIL/BLOCKED table per field per role with exact fix prescriptions |
| `ASK_AI_FIELD_COVERAGE_AUDIT.md` | Context builder field map, LISTING_KEY_KEYWORD_MAP 49-field table, coverage summary |
| `ASK_AI_FULL_FIELD_AND_FAQ_COVERAGE.md` | Full FAQ config-key → faq_answers path → keyword route mapping for all 168 entries |
| `ASK_AI_LIVE_END_TO_END_FIELD_AUDIT_RESULTS.md` | 44-question live pipeline trace; per-question classifier/router/contract/status results |
| `ASK_AI_SUGGESTED_QUESTION_COVERAGE.md` | Chip-vs-manual parity audit (all chips pass) |
| `ASK_AI_NATURAL_LANGUAGE_ROUTING_AUDIT.md` | Classifier KEYWORD_RULES routing coverage |

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Scope and Methodology](#2-scope-and-methodology)
3. [Field Universe — Seller](#3-field-universe--seller)
4. [Field Universe — Buyer](#4-field-universe--buyer)
5. [Field Universe — Landlord](#5-field-universe--landlord)
6. [Field Universe — Tenant](#6-field-universe--tenant)
7. [Property-Type-Specific and Cross-Role Fields](#7-property-type-specific-and-cross-role-fields)
8. [Restricted Field Inventory](#8-restricted-field-inventory)
9. [DB Column and Meta Key Mapping Reference](#9-db-column-and-meta-key-mapping-reference)
10. [End-to-End Field Lineage Audit](#10-end-to-end-field-lineage-audit)
11. [FAQ Registry Summary (168 Entries)](#11-faq-registry-summary-168-entries)
12. [Listing Field Registry Summary (45 Entries)](#12-listing-field-registry-summary-45-entries)
13. [Database-First Architecture Design](#13-database-first-architecture-design)
14. [Gap Analysis and Coverage Matrix](#14-gap-analysis-and-coverage-matrix)
15. [Appendix: Canonical Ask AI Field Identifier Reference](#15-appendix-canonical-ask-ai-field-identifier-reference)
16. [Implementation Roadmap](#16-implementation-roadmap)
17. [Complete Form Field Universe](#17-complete-form-field-universe)
18. [Question Templates — Primary, Alternates, and Answer Formats](#18-question-templates--primary-alternates-and-answer-formats)
19. [Phase 5 Context Builder Extension Scope](#19-phase-5-context-builder-extension-scope)
20. [Field Universe Reconciliation](#20-field-universe-reconciliation)

---

## 1. Executive Summary

### Current State

Ask AI processes natural-language questions through a five-stage pipeline:

```
User question
    → AskAiQuestionClassifierService   (intent detection)
    → AskAiRunnerV2Service             (field key detection + Guard B)
    → AskAiContextBuilderService       (DB read + context assembly)
    → AskAiResponseContractService     (allowed-path enforcement)
    → AskAiPromptBuilderService → OpenAI adapter
```

The system has **213 registered field paths** (168 FAQ entries + 45 listing model entries) covering all four roles. All 1,689 Ask AI tests pass as of June 2026.

### Critical Findings

| Finding | Impact | Status |
|---|---|---|
| **Seller/Buyer factual fields entirely broken** — ~17 FAILs per role — `nativeGet()` used throughout but fields live in EAV metas | Every Seller/Buyer structural field (price, beds, baths, sqft, HOA, pets, etc.) returns null to the AI | **Open** — documented in extraction audit, fix prescribed but not applied |
| **Phantom keys** — 10 Seller + 4 Buyer fields read keys that were never saved | Null answers for fields that appear to be covered | **Open** |
| **Landlord `number_of_occupants` key mismatch** — reads `number_of_occupants_allowed` but saved as `number_occupant` | One Landlord field returns null | **Open** |
| **Tenant FAQ opaque keys** — 27 pinned entries use faq_q1–faq_q27 as both config keys and registry keys | Fields are routable by keyword but cannot be individually surfaced by config_key inspection | **Known / by design** |
| **Seller/Buyer `water_view` phantom** — neither Seller nor Buyer form collects or saves `water_view` | Registry entry `listing.water_view` present for seller/buyer but context is always null | **Open** |
| **Context builder is the only link to the DB** — no pre-built fact snapshot | Every question requires a live DB read with O(N) EAV queries | **Architecture gap** (this audit proposes the fix) |

### Estimated Answerable-Without-AI Percentage (Current vs. Target)

| Role | Current (factual fields working) | Target (database-first) |
|---|---|---|
| Seller | ~5% (address, description, taxes, service_type work; everything else broken) | ~55% (structural facts + FAQ answers stored) |
| Buyer | ~10% (address, is_approved work; all property criteria broken) | ~50% (criteria facts + FAQ match data stored) |
| Landlord | ~85% (all EAV reads correct) | ~90% (FAQ answers pre-generated) |
| Tenant | ~95% (all EAV reads correct) | ~97% (FAQ answers pre-generated) |
| **Overall** | **~40%** (heavily weighted to working Landlord/Tenant records) | **~70%** |

---

## 2. Scope and Methodology

### Forms Audited

| Form | Livewire Component | Blade View | Lines (PHP / Blade) |
|---|---|---|---|
| Seller | `SellerOfferListing.php` | `offer-seller-listing.blade.php` | 4,343 / 3,022 |
| Buyer | `BuyerOfferListing.php` | `offer-buyer-listing.blade.php` | 2,903 / 2,969 |
| Landlord | `LandlordOfferListing.php` | `offer-landlord-listing.blade.php` | 4,035 / 3,364 |
| Tenant | `TenantOfferListing.php` | `offer-tenant-listing.blade.php` | 5,115 / 5,893 |

### Pipeline Services Audited

- `AskAiFieldQuestionRegistryService.php` (2,353 lines) — 168 FAQ + 45 listing model entries
- `AskAiContextBuilderService.php` (1,334 lines) — full `extractFactualFields()` all four roles

### Classification Definitions

| Classification | Definition |
|---|---|
| **DATABASE-FIRST** | Field value stored in DB; answer can be built from a pre-computed fact snapshot without OpenAI |
| **AI-OPTIONAL** | Value stored in DB but answer benefits from natural-language formatting; OpenAI may be called to polish but is not required |
| **AI-REQUIRED** | No structured value in DB; answer requires OpenAI to generate from FAQ free-text or synthesise from context |
| **RESTRICTED** | Must never appear in any public Ask AI response; agent-only or completely blocked |

### Lineage Status Definitions

| Status | Definition |
|---|---|
| **Fully Connected** | Field flows from form → DB → context builder → registry → NL route → Ask AI answer |
| **Partially Connected** | One or more links in the chain are broken or missing |
| **Unused** | Field is saved to DB but never read by any Ask AI service |
| **Orphaned** | Ask AI reads a key that is never saved (phantom key) |
| **Missing Coverage** | Field saved and extractable but no registry entry or NL route |

---

## 3. Field Universe — Seller

**Model:** `SellerAgentAuction` → `seller_agent_auctions` + `seller_agent_auction_metas`  
**Service type:** Full Service (primary); Limited Service (legacy, frozen — not audited here)  
**Property types:** Single Family, Condo, Commercial/Income, Business Opportunity, Vacant Land

### 3.1 Structural / Factual Fields (Tab: Sale Terms / Property Details)

| # | Ask AI ID | Display Label | Input Name | Meta Key | Context Key | Field Type | Public | Eligible | Classification | Lineage Status | Notes |
|---|---|---|---|---|---|---|---|---|---|---|---|
| 1 | `SEL-001` | Property Address | `address` | — (native) | `address` | text | ✅ | ✅ | DATABASE-FIRST | Fully Connected | Native column |
| 2 | `SEL-002` | Listing Description | `description` | — (native) | `description` | textarea | ✅ | ✅ | AI-OPTIONAL | Fully Connected | Native column |
| 3 | `SEL-003` | Asking / Sale Price | `maximum_budget` | `maximum_budget` | `asking_price` | currency | ✅ | ✅ | DATABASE-FIRST | **Partially Connected** | Context uses `nativeGet('starting_price')` — BROKEN; fix: `infoGet('maximum_budget')` |
| 4 | `SEL-004` | Buy Now Price | `buy_now_price` | *(not saved)* | `buy_now_price` | currency | ✅ | ❌ | **RESTRICTED** (phantom) | **Orphaned** | Form does not call `saveMeta('buy_now_price')`; context reads phantom key |
| 5 | `SEL-005` | Bedrooms | `bedrooms` | `bedrooms` | `bedrooms` | select | ✅ | ✅ | DATABASE-FIRST | Fully Connected | `infoGet('bedrooms')` resolves; `other_bedrooms` companion handled |
| 6 | `SEL-006` | Bathrooms | `bathrooms` | `bathrooms` | `bathrooms` | select | ✅ | ✅ | DATABASE-FIRST | Fully Connected | `infoGet('bathrooms')` resolves; `other_bathrooms` handled |
| 7 | `SEL-007` | Square Footage | `minimum_heated_square` | `minimum_heated_square` | `square_feet` | number | ✅ | ✅ | DATABASE-FIRST | **Partially Connected** | Context uses `nativeGet('heated_sqft')` — BROKEN; fix: `infoGet('minimum_heated_square')` |
| 8 | `SEL-008` | Year Built | `year_built` | `year_built` | `year_built` | number | ✅ | ✅ | DATABASE-FIRST | **Partially Connected** | Context uses `nativeGet('year_built')` — BROKEN; fix: `infoGet('year_built')` |
| 9 | `SEL-009` | Property Type | `property_type` | `property_type` | `property_type` | select | ✅ | ✅ | DATABASE-FIRST | Fully Connected | EAV meta; context builder reads via base fields |
| 10 | `SEL-010` | Pool | `pool_needed` | `pool_needed` | `pool` | checkbox | ✅ | ✅ | DATABASE-FIRST | **Partially Connected** | Context uses `nativeGet('pool')` — BROKEN; fix: `infoGet('pool_needed')` |
| 11 | `SEL-011` | Pool Type | `pool_type` | `pool_type` (JSON) | `pool_type` | multiselect | ✅ | ✅ | DATABASE-FIRST | **Partially Connected** | Context uses `nativeGet('pool_type')` — BROKEN; fix: `decodeJsonField(infoGet('pool_type'))` |
| 12 | `SEL-012` | Carport | `carport_needed` | `carport_needed` | `carport` | checkbox | ✅ | ✅ | DATABASE-FIRST | **Partially Connected** | Context uses `nativeGet('carport')` — BROKEN; fix: `infoGet('carport_needed')` |
| 13 | `SEL-013` | Garage | `garage_needed` | `garage_needed` | `garage` | checkbox | ✅ | ✅ | DATABASE-FIRST | **Partially Connected** | Context uses `nativeGet('garage')` — BROKEN; fix: `infoGet('garage_needed')` |
| 14 | `SEL-014` | Garage Spaces | `garage_parking_spaces` | `garage_parking_spaces` | `garage_spaces` | number | ✅ | ✅ | DATABASE-FIRST | **Partially Connected** | Context uses `nativeGet('garage_spaces')` — BROKEN; fix: `infoGet('garage_parking_spaces')` |
| 15 | `SEL-015` | View / Water View | `view_preference` | `view_preference` (JSON) | `water_view` | multiselect | ✅ | ✅ | DATABASE-FIRST | **Partially Connected** | Context uses `nativeGet('water_view')` — BROKEN; fix: `decodeJsonField(infoGet('view_preference'))` |
| 16 | `SEL-016` | HOA / Association | `has_hoa` | `has_hoa` | `hoa_association` | radio | ✅ | ✅ | DATABASE-FIRST | **Partially Connected** | Context uses `nativeGet('hoa_association')` — BROKEN; fix: `infoGet('has_hoa')` |
| 17 | `SEL-017` | HOA Fee Amount | `association_fee_amount` | `association_fee_amount` | `hoa_fee` | currency | ✅ | ✅ | DATABASE-FIRST | **Partially Connected** | Context uses `nativeGet('hoa_fee')` — BROKEN; fix: `infoGet('association_fee_amount')` |
| 18 | `SEL-018` | HOA Fee Frequency | `association_fee_frequency` | `association_fee_frequency` | `hoa_payment_schedule` | select | ✅ | ✅ | DATABASE-FIRST | **Partially Connected** | Context uses `nativeGet('hoa_payment_schedule')` — BROKEN; fix: `infoGet('association_fee_frequency')` |
| 19 | `SEL-019` | HOA Fee Requirement | *(not collected)* | *(not saved)* | `hoa_fee_requirement` | — | — | ❌ | — | **Orphaned** | Phantom from old `property_auctions` schema; remove from context |
| 20 | `SEL-020` | Condo Fee | *(not collected)* | *(not saved)* | `condo_fee` | — | — | ❌ | — | **Orphaned** | Phantom from old schema |
| 21 | `SEL-021` | Pets Allowed | `pets` | `pets` | `pets_allowed` | radio | ✅ | ✅ | DATABASE-FIRST | **Partially Connected** | Context uses `nativeGet('pets_allowed')` — BROKEN; fix: `infoGet('pets')` |
| 22 | `SEL-022` | Number of Pets | `number_of_pets` | `number_of_pets` | `number_of_pets_allowed` | number | ✅ | ✅ | DATABASE-FIRST | **Partially Connected** | Context uses `nativeGet('number_of_pets_allowed')` — BROKEN; fix: `infoGet('number_of_pets')` |
| 23 | `SEL-023` | Max Pet Weight | `weight_of_pets` | `weight_of_pets` | `max_pet_weight` | number | ✅ | ✅ | DATABASE-FIRST | **Partially Connected** | Context uses `nativeGet('max_pet_weight')` — BROKEN; fix: `infoGet('weight_of_pets')` |
| 24 | `SEL-024` | Pet Restrictions | `pet_restrictions` | `pet_restrictions` | `pet_restrictions` | text | ✅ | ✅ | DATABASE-FIRST | **Partially Connected** | Context uses `nativeGet('pet_restrictions')` — BROKEN (accessor only); fix: `infoGet('pet_restrictions')` |
| 25 | `SEL-025` | Rental Restrictions | `leasing_restrictions` | `leasing_restrictions` | `rental_restrictions` | radio | ✅ | ✅ | DATABASE-FIRST | **Partially Connected** | Context uses `nativeGet('rental_restrictions')` — BROKEN; fix: `infoGet('leasing_restrictions')` |
| 26 | `SEL-026` | Flood Zone Code | `flood_zone_code` | `flood_zone_code` | `flood_zone_code` | text | ✅ | ✅ | DATABASE-FIRST | **Partially Connected** | Context uses `nativeGet('flood_zone_code')` — BROKEN; fix: `infoGet('flood_zone_code')` |
| 27 | `SEL-027` | Target Closing Date | `target_closing_date` | `target_closing_date` | `closing_date` | date | ✅ | ✅ | DATABASE-FIRST | **Partially Connected** | Context uses `nativeGet('closing_date')` — BROKEN; fix: `infoGet('target_closing_date')` |
| 28 | `SEL-028` | Auction Length | `auction_length` | `auction_length` (also native) | `auction_length` | number | ✅ | ✅ | DATABASE-FIRST | Fully Connected | Native column; `nativeGet` resolves correctly |
| 29 | `SEL-029` | Annual Property Taxes | `annual_property_taxes` | `annual_property_taxes` | `annual_property_taxes` | currency | ✅ | ✅ | DATABASE-FIRST | Fully Connected | `infoGet` correct ✓ |
| 30 | `SEL-030` | Service Type | `service_type` | `service_type` | `service_type` | hidden | ❌ | ❌ | RESTRICTED | Fully Connected | Internal workflow field; correct but should not surface in public AI response |
| 31 | `SEL-031` | Is Sold | — | — (native `is_sold`) | `sold` | status | ❌ | ❌ | RESTRICTED | **Partially Connected** | Context uses `nativeGet('sold')` — BROKEN; native column is `is_sold` |

### 3.2 Seller FAQ Answer Fields (Tab: Property Details / Separate FAQ Form)

All FAQ fields are stored in `ai_faq_answers` table keyed by listing_id, role, and config_key. They are assembled by `buildFaqAnswers()` in the context builder and accessible as `ctx['faq_answers'][key]`.

| # | Ask AI ID | Config Key | Canonical Path | Section | Classification | Q Category | Primary Question |
|---|---|---|---|---|---|---|---|
| 32 | `SEL-FAQ-001` | `roof_age_and_condition` | `faq_answers.roof_age_and_condition` | Condition & Maintenance | AI-REQUIRED | property_condition | "How old is the roof and what condition is it in?" |
| 33 | `SEL-FAQ-002` | `hvac_system_age` | `faq_answers.hvac_system_age` | Condition & Maintenance | AI-REQUIRED | property_condition | "What type of HVAC system is in this home?" |
| 34 | `SEL-FAQ-003` | `water_heater_age_type` | `faq_answers.water_heater_age_type` | Condition & Maintenance | AI-REQUIRED | property_condition | "What type of water heater is in this home?" |
| 35 | `SEL-FAQ-004` | `recent_renovations_list` | `faq_answers.recent_renovations_list` | Condition & Maintenance | AI-REQUIRED | property_condition | "What recent renovations have been made?" |
| 36 | `SEL-FAQ-005` | `permits_for_renovations` | `faq_answers.permits_for_renovations` | Condition & Maintenance | AI-REQUIRED | disclosure | "Were permits pulled for any additions or renovations?" |
| 37 | `SEL-FAQ-006` | `known_defects_issues` | `faq_answers.known_defects_issues` | Condition & Maintenance | AI-REQUIRED | disclosure | "Are there any known defects or deferred maintenance items?" |
| 38 | `SEL-FAQ-007` | `foundation_type_and_issues` | `faq_answers.foundation_type_and_issues` | Condition & Maintenance | AI-REQUIRED | disclosure | "What type of foundation does this home have?" |
| 39 | `SEL-FAQ-008` | `pest_termite_history` | `faq_answers.pest_termite_history` | Condition & Maintenance | AI-REQUIRED | disclosure | "Is there a history of pest or termite damage?" |
| 40 | `SEL-FAQ-009` | `flood_damage_history` | `faq_answers.flood_damage_history` | Condition & Maintenance | AI-REQUIRED | disclosure | "Has this property ever had flood damage?" |
| 41 | `SEL-FAQ-010` | `mold_issues_history` | `faq_answers.mold_issues_history` | Condition & Maintenance | AI-REQUIRED | disclosure | "Is there any history of mold issues?" |
| 42 | `SEL-FAQ-011` | `average_utility_costs` | `faq_answers.average_utility_costs` | Financial & Utility | AI-REQUIRED | utilities | "What are the average monthly utility costs?" |
| 43 | `SEL-FAQ-012` | `internet_utility_providers` | `faq_answers.internet_utility_providers` | Financial & Utility | AI-REQUIRED | utilities | "Which internet and utility providers serve this property?" |
| 44 | `SEL-FAQ-013` | `seller_concessions_offered` | `faq_answers.seller_concessions_offered` | Transaction Terms | AI-REQUIRED | transaction | "Is the seller willing to offer any concessions?" |
| 45 | `SEL-FAQ-014` | `neighborhood_character` | `faq_answers.neighborhood_character` | Location & Lifestyle | AI-REQUIRED | neighborhood | "How would you describe the character of this neighborhood?" |
| 46 | `SEL-FAQ-015` | `traffic_or_noise_concerns` | `faq_answers.traffic_or_noise_concerns` | Location & Lifestyle | AI-REQUIRED | neighborhood | "Are there any traffic or noise concerns near this property?" |
| 47 | `SEL-FAQ-016` | `planned_nearby_development` | `faq_answers.planned_nearby_development` | Location & Lifestyle | AI-REQUIRED | neighborhood | "Is there any planned development nearby?" |
| 48 | `SEL-FAQ-017` | `commute_options_access` | `faq_answers.commute_options_access` | Location & Lifestyle | AI-REQUIRED | neighborhood | "What are the commute options from this location?" |
| 49 | `SEL-FAQ-018` | `natural_light_orientation` | `faq_answers.natural_light_orientation` | Location & Lifestyle | AI-REQUIRED | property_condition | "What is the orientation and how is the natural light?" |
| 50 | `SEL-FAQ-019` | `nearby_amenities_description` | `faq_answers.nearby_amenities_description` | Location & Lifestyle | AI-REQUIRED | neighborhood | "What amenities are nearby?" |
| 51 | `SEL-FAQ-020` | `neighborhood_restrictions` | `faq_answers.neighborhood_restrictions` | Location & Lifestyle | AI-REQUIRED | neighborhood | "Are there any neighborhood restrictions?" |
| 52 | `SEL-FAQ-021` | `closing_timeline_flexibility` | `faq_answers.closing_timeline_flexibility` | Transaction Terms | AI-REQUIRED | transaction | "How flexible is the seller on the closing timeline?" |
| 53 | `SEL-FAQ-022` | `seller_leaseback_option` | `faq_answers.seller_leaseback_option` | Transaction Terms | AI-REQUIRED | transaction | "Would the seller consider a leaseback arrangement?" |
| 54 | `SEL-FAQ-023` | `items_excluded_from_sale` | `faq_answers.items_excluded_from_sale` | Transaction Terms | AI-REQUIRED | transaction | "Are there any items excluded from the sale?" |
| 55 | `SEL-FAQ-024` | `furniture_negotiability` | `faq_answers.furniture_negotiability` | Transaction Terms | AI-REQUIRED | transaction | "Is furniture negotiable or available for purchase?" |
| 56 | `SEL-FAQ-025` | `as_is_condition` | `faq_answers.as_is_condition` | Transaction Terms | AI-REQUIRED | disclosure | "Is this property being sold as-is?" |
| 57 | `SEL-FAQ-026` | `environmental_concerns` | `faq_answers.environmental_concerns` | Condition & Maintenance | AI-REQUIRED | disclosure | "Are there any environmental concerns with this property?" |
| 58 | `SEL-FAQ-027` | `unique_selling_points` | `faq_answers.unique_selling_points` | Marketing | AI-REQUIRED | marketing | "What makes this property stand out?" |
| 59 | `SEL-FAQ-028` | `seller_favorite_features` | `faq_answers.seller_favorite_features` | Marketing | AI-REQUIRED | marketing | "What are the seller's favorite features of this home?" |
| 60 | `SEL-FAQ-029` | `seller_motivation_for_selling` | `faq_answers.seller_motivation_for_selling` | Transaction Terms | AI-REQUIRED | transaction | "What is the seller's motivation for selling?" |
| 61 | `SEL-FAQ-030` | `move_in_ready_status` | `faq_answers.move_in_ready_status` | Condition & Maintenance | AI-REQUIRED | property_condition | "Is this home move-in ready?" |
| 62 | `SEL-FAQ-031` | `parking_arrangements` | `faq_answers.parking_arrangements` | Property Features | AI-REQUIRED | property_condition | "What are the parking arrangements?" |
| 63 | `SEL-FAQ-032` | `storage_space_available` | `faq_answers.storage_space_available` | Property Features | AI-REQUIRED | property_condition | "What storage space is available?" |
| 64 | `SEL-FAQ-033` | `hoa_community_highlights` | `faq_answers.hoa_community_highlights` | HOA & Community | AI-REQUIRED | hoa | "What are the highlights of the HOA community?" |

### 3.3 Seller Add-On Fields — Commercial Income Property

*(Only shown when `property_type = Commercial/Income`)*

| # | Ask AI ID | Config Key | Canonical Path | Classification |
|---|---|---|---|---|
| 65 | `SEL-CI-001` | `annual_net_operating_income` | `faq_answers.annual_net_operating_income` | AI-REQUIRED |
| 66 | `SEL-CI-002` | `current_cap_rate` | `faq_answers.current_cap_rate` | AI-REQUIRED |
| 67 | `SEL-CI-003` | `existing_tenant_lease_terms` | `faq_answers.existing_tenant_lease_terms` | AI-REQUIRED |
| 68 | `SEL-CI-004` | `current_occupancy_rate` | `faq_answers.current_occupancy_rate` | AI-REQUIRED |
| 69 | `SEL-CI-005` | `annual_operating_expenses_detail` | `faq_answers.annual_operating_expenses_detail` | AI-REQUIRED |
| 70 | `SEL-CI-006` | `value_add_opportunities` | `faq_answers.value_add_opportunities` | AI-REQUIRED |

### 3.4 Seller Add-On Fields — Business Opportunity

*(Only shown when `property_type = Business Opportunity`)*

| # | Ask AI ID | Config Key | Canonical Path | Classification |
|---|---|---|---|---|
| 71 | `SEL-BIZ-001` | `annual_business_revenue` | `faq_answers.annual_business_revenue` | AI-REQUIRED |
| 72 | `SEL-BIZ-002` | `annual_net_profit` | `faq_answers.annual_net_profit` | AI-REQUIRED |
| 73 | `SEL-BIZ-003` | `business_reason_for_selling` | `faq_answers.business_reason_for_selling` | AI-REQUIRED |
| 74 | `SEL-BIZ-004` | `business_employee_count` | `faq_answers.business_employee_count` | AI-REQUIRED |
| 75 | `SEL-BIZ-005` | `seller_training_transition` | `faq_answers.seller_training_transition` | AI-REQUIRED |
| 76 | `SEL-BIZ-006` | `business_lease_status` | `faq_answers.business_lease_status` | AI-REQUIRED |
| 77 | `SEL-BIZ-007` | `inventory_equipment_included` | `faq_answers.inventory_equipment_included` | AI-REQUIRED |

### 3.5 Seller Add-On Fields — Vacant Land

*(Only shown when `property_type = Vacant Land`)*

| # | Ask AI ID | Config Key | Canonical Path | Classification |
|---|---|---|---|---|
| 78 | `SEL-VL-001` | `land_utilities_availability` | `faq_answers.land_utilities_availability` | AI-REQUIRED |
| 79 | `SEL-VL-002` | `land_zoning_permitted_uses` | `faq_answers.land_zoning_permitted_uses` | AI-REQUIRED |
| 80 | `SEL-VL-003` | `land_access_and_road` | `faq_answers.land_access_and_road` | AI-REQUIRED |
| 81 | `SEL-VL-004` | `land_soil_and_topography` | `faq_answers.land_soil_and_topography` | AI-REQUIRED |
| 82 | `SEL-VL-005` | `land_survey_available` | `faq_answers.land_survey_available` | AI-REQUIRED |
| 83 | `SEL-VL-006` | `land_development_restrictions` | `faq_answers.land_development_restrictions` | AI-REQUIRED |

### 3.6 Seller Suggested Question Coverage

From `ASK_AI_SUGGESTED_QUESTION_COVERAGE.md`: 11/11 chips pass parity. Seller has 8 listing_facts chips + 3 static intent chips. All verified routing correctly.

---

## 4. Field Universe — Buyer

**Model:** `BuyerAgentAuction` → `buyer_agent_auctions` + `buyer_agent_auction_metas`  
**Role:** Buyer criteria listing — describes what the buyer is seeking  
**Property types:** Single Family, Condo, Commercial/Income, Business Opportunity, Vacant Land

### 4.1 Structural / Factual Fields (Buyer Criteria)

| # | Ask AI ID | Display Label | Input Name | Meta Key | Context Key | Classification | Lineage Status | Notes |
|---|---|---|---|---|---|---|---|---|
| 1 | `BUY-001` | Address / Desired Area | `address` | — (native) | `address` | DATABASE-FIRST | Fully Connected | Native column |
| 2 | `BUY-002` | Description / Additional Details | `additional_details` | — (native) | `description` | AI-OPTIONAL | **Partially Connected** | Context uses `nativeGet('description')` — BROKEN; native column is `additional_details` |
| 3 | `BUY-003` | Maximum Budget | `maximum_budget` | `maximum_budget` | `max_price` | DATABASE-FIRST | **Partially Connected** | Context uses `nativeGet('max_price')` — BROKEN; fix: `infoGet('maximum_budget')` |
| 4 | `BUY-004` | Bedrooms | `bedrooms` | `bedrooms` | `bedrooms` | DATABASE-FIRST | **Partially Connected** | Context uses `nativeGet('bedrooms')` — BROKEN; fix: `infoGet('bedrooms')` |
| 5 | `BUY-005` | Bathrooms | `bathrooms` | `bathrooms` | `bathrooms` | DATABASE-FIRST | **Partially Connected** | Context uses `nativeGet('bathrooms')` — BROKEN; fix: `infoGet('bathrooms')` |
| 6 | `BUY-006` | Square Footage (min) | `minimum_heated_square` | `minimum_heated_square` | `square_feet` | DATABASE-FIRST | **Partially Connected** | Context uses `nativeGet('sqft')` — BROKEN; fix: `infoGet('minimum_heated_square')` |
| 7 | `BUY-007` | Pool | `pool_needed` | `pool_needed` | `pool` | DATABASE-FIRST | **Partially Connected** | Context uses `nativeGet('pool')` — BROKEN; fix: `infoGet('pool_needed')` |
| 8 | `BUY-008` | Carport | `carport_needed` | `carport_needed` | `carport` | DATABASE-FIRST | **Partially Connected** | Context uses `nativeGet('carport')` — BROKEN; fix: `infoGet('carport_needed')` |
| 9 | `BUY-009` | Garage | `garage_needed` | `garage_needed` | `garage` | DATABASE-FIRST | **Partially Connected** | Context uses `nativeGet('garage')` — BROKEN; fix: `infoGet('garage_needed')` |
| 10 | `BUY-010` | Garage Spaces | `garage_parking_spaces` | `garage_parking_spaces` | `garage_spaces` | DATABASE-FIRST | **Partially Connected** | Context uses `nativeGet('garage_spaces')` — BROKEN; fix: `infoGet('garage_parking_spaces')` |
| 11 | `BUY-011` | Water View | *(not collected)* | *(not saved)* | `water_view` | — | **Orphaned** | Buyer criteria form does not collect view preference; phantom key in context |
| 12 | `BUY-012` | HOA Acceptable | `hoa_acceptance` | `hoa_acceptance` | `hoa_acceptable` | DATABASE-FIRST | **Partially Connected** | Context uses `nativeGet('hoa')` — BROKEN; fix: `infoGet('hoa_acceptance')` |
| 13 | `BUY-013` | Max HOA Fee | `hoa_max_monthly_fee` | `hoa_max_monthly_fee` | `max_hoa_fee` | DATABASE-FIRST | **Partially Connected** | Context uses `nativeGet('max_hoa_fee')` — BROKEN; fix: `infoGet('hoa_max_monthly_fee')` |
| 14 | `BUY-014` | Pets | `pets` | `pets` | `pets_allowed` | DATABASE-FIRST | **Partially Connected** | Context uses `nativeGet('pets_allowed')` — BROKEN; fix: `infoGet('pets')` |
| 15 | `BUY-015` | Pet Type | `type_of_pets` | `type_of_pets` | `pets_detail` | DATABASE-FIRST | **Partially Connected** | Context uses `nativeGet('pets_detail')` — BROKEN; fix: `infoGet('type_of_pets')` |
| 16 | `BUY-016` | Pet Breed | `breed_of_pets` | `breed_of_pets` | `pets_breed` | DATABASE-FIRST | **Partially Connected** | fix: `infoGet('breed_of_pets')` |
| 17 | `BUY-017` | Pet Weight | `weight_of_pets` | `weight_of_pets` | `pets_weight` | DATABASE-FIRST | **Partially Connected** | fix: `infoGet('weight_of_pets')` |
| 18 | `BUY-018` | Loan Pre-Approved | `pre_approved` | `pre_approved` | `loan_pre_approved` | DATABASE-FIRST | **Partially Connected** | Context uses `nativeGet('loan_pre_approved')` — BROKEN; fix: `infoGet('pre_approved')` |
| 19 | `BUY-019` | Financing Type | `offered_financing` | `offered_financing` (JSON) | `financing_type` | DATABASE-FIRST | **Partially Connected** | Context uses `resolveFinancingType(nativeGet('financing_id'))` — fundamentally wrong; fix: `decodeJsonField(infoGet('offered_financing'))` |
| 20 | `BUY-020` | Inspection Period (days) | `inspection_period_days` | `inspection_period_days` | `inspection_period` | DATABASE-FIRST | **Partially Connected** | fix: `infoGet('inspection_period_days')` |
| 21 | `BUY-021` | Closing Date | `target_closing_date` | `target_closing_date` | `closing_date` | DATABASE-FIRST | **Partially Connected** | Context uses `nativeGet('closing_days')` — BROKEN (phantom); fix: `infoGet('target_closing_date')` |
| 22 | `BUY-022` | Inspection Contingency | `inspection_contingency_buyer` | `inspection_contingency_buyer` | `inspection_contingency_buyer` | DATABASE-FIRST | **Partially Connected** | EAV meta; context uses `nativeGet('contingencies')` — BROKEN |
| 23 | `BUY-023` | Appraisal Contingency | `appraisal_contingency_buyer` | `appraisal_contingency_buyer` | `appraisal_contingency_buyer` | DATABASE-FIRST | **Partially Connected** | Same issue; fix: `infoGet` direct |
| 24 | `BUY-024` | Financing Contingency | `financing_contingency_buyer` | `financing_contingency_buyer` | `financing_contingency_buyer` | DATABASE-FIRST | **Partially Connected** | Same issue |
| 25 | `BUY-025` | Property Type | `property_type` | `property_type` | `property_type` | DATABASE-FIRST | Fully Connected | EAV meta |

### 4.2 Buyer FAQ Answer Fields (Match Criteria — `match_criteria` route status)

Buyer FAQ answers are self-description fields filled by the buyer, not factual property data. They are answered in the same FAQ interface as seller FAQs but route via the `buyer_tenant_match` intent (not `listing_facts`). They are **NOT** in `FAQ_KEY_KEYWORD_MAP` — they use the umbrella path. All 28 base entries have `keyword_route_status: match_criteria`.

| # | Ask AI ID | Config Key | Canonical Path | Primary Question | Q Category |
|---|---|---|---|---|---|
| 26 | `BUY-FAQ-001` | `buyer_motivation` | `faq_answers.buyer_motivation` | "What's driving your decision to buy right now?" | buyer_criteria |
| 27 | `BUY-FAQ-002` | `buyer_lifestyle_goals` | `faq_answers.buyer_lifestyle_goals` | "How do you envision using this property?" | buyer_criteria |
| 28 | `BUY-FAQ-003` | `buyer_deal_breakers` | `faq_answers.buyer_deal_breakers` | "What are your absolute deal-breakers?" | buyer_criteria |
| 29 | `BUY-FAQ-004` | `buyer_renovation_tolerance` | `faq_answers.buyer_renovation_tolerance` | "Would you consider a property that needs work?" | buyer_criteria |
| 30 | `BUY-FAQ-005` | `buyer_wfh_needs` | `faq_answers.buyer_wfh_needs` | "Do you work from home? What is your ideal home office setup?" | buyer_criteria |
| 31 | `BUY-FAQ-006` | `buyer_outdoor_space` | `faq_answers.buyer_outdoor_space` | "How important is outdoor space?" | buyer_criteria |
| 32 | `BUY-FAQ-007` | `buyer_long_term_goals` | `faq_answers.buyer_long_term_goals` | "Is this a forever home, starter home, or investment?" | buyer_criteria |
| 33 | `BUY-FAQ-008` | `buyer_biggest_concern` | `faq_answers.buyer_biggest_concern` | "What's your biggest concern about this purchase?" | buyer_criteria |
| 34 | `BUY-FAQ-009` | `buyer_neighborhood_preferences` | `faq_answers.buyer_neighborhood_preferences` | "What kind of neighborhood feel are you looking for?" | buyer_criteria |
| 35 | `BUY-FAQ-010` | `buyer_school_district` | `faq_answers.buyer_school_district` | "Is a specific school district a hard requirement?" | buyer_criteria |
| 36 | `BUY-FAQ-011` | `buyer_commute_requirements` | `faq_answers.buyer_commute_requirements` | "Do you have commute distance requirements?" | buyer_criteria |
| 37 | `BUY-FAQ-012` | `buyer_noise_tolerance` | `faq_answers.buyer_noise_tolerance` | "How sensitive are you to noise?" | buyer_criteria |
| 38 | `BUY-FAQ-013` | `buyer_area_familiarity` | `faq_answers.buyer_area_familiarity` | "How familiar are you with the neighborhoods you're considering?" | buyer_criteria |
| 39 | `BUY-FAQ-014` | `buyer_prefers_off_market` | `faq_answers.buyer_prefers_off_market` | "Are you open to off-market listings?" | buyer_criteria |
| 40 | `BUY-FAQ-015` | `buyer_property_style` | `faq_answers.buyer_property_style` | "Do you have an architectural style preference?" | buyer_criteria |
| 41 | `BUY-FAQ-016` | `buyer_must_have_features` | `faq_answers.buyer_must_have_features` | "What are your absolute must-have property features?" | buyer_criteria |
| 42 | `BUY-FAQ-017` | `buyer_nice_to_have` | `faq_answers.buyer_nice_to_have` | "What features are on your wish list but not deal-breakers?" | buyer_criteria |
| 43 | `BUY-FAQ-018` | `buyer_hoa_acceptable` | `faq_answers.buyer_hoa_acceptable` | "Are you comfortable with an HOA community?" | buyer_criteria |
| 44 | `BUY-FAQ-019` | `buyer_accessibility` | `faq_answers.buyer_accessibility` | "Do you need any accessibility features?" | buyer_criteria |
| 45 | `BUY-FAQ-020` | `buyer_privacy_requirements` | `faq_answers.buyer_privacy_requirements` | "Do you have specific privacy needs?" | buyer_criteria |
| 46 | `BUY-FAQ-021` | `buyer_view_preference` | `faq_answers.buyer_view_preference` | "Is a specific view important to you?" | buyer_criteria |
| 47 | `BUY-FAQ-022` | `buyer_current_situation` | `faq_answers.buyer_current_situation` | "What's your current living situation?" | buyer_criteria |
| 48 | `BUY-FAQ-023` | `buyer_simultaneous_close` | `faq_answers.buyer_simultaneous_close` | "Do you need to sell a current property simultaneously?" | buyer_criteria |
| 49 | `BUY-FAQ-024` | `buyer_leaseback` | `faq_answers.buyer_leaseback` | "Would you allow the seller to stay on a short leaseback?" | buyer_criteria |
| 50 | `BUY-FAQ-025` | `buyer_relocation` | `faq_answers.buyer_relocation` | "Are you relocating from another area?" | buyer_criteria |
| 51 | `BUY-FAQ-026` | `buyer_lost_deal` | `faq_answers.buyer_lost_deal` | "Have you made offers that didn't work out?" | buyer_criteria |
| 52 | `BUY-FAQ-027` | `buyer_seller_concessions` | `faq_answers.buyer_seller_concessions` | "Would you consider asking for seller concessions?" | buyer_criteria |
| 53 | `BUY-FAQ-028` | `buyer_flexibility` | `faq_answers.buyer_flexibility` | "How flexible are you on location, timing, or property type?" | buyer_criteria |

### 4.3 Buyer Add-On FAQ Fields — Commercial (8 entries) + Business (7 entries) + Land (7 entries)

All 22 add-on entries have `keyword_route_status: match_criteria`. Commercial: `com_property_use`, `com_investment_type`, `com_cap_rate_target`, `com_occupancy_rate`, `com_lease_terms`, `com_1031_exchange`, `com_due_diligence_period`, `com_environmental_concerns`. Business: `biz_type_seeking`, `biz_revenue_required`, `biz_profit_required`, `biz_training_expected`, `biz_staff_included`, `biz_non_compete`, `biz_sba_financing`. Land: `land_intended_use`, `land_zoning_required`, `land_utilities_needed`, `land_soil_testing`, `land_build_timeline`, `land_access_requirements`, `land_topography`.

### 4.4 Buyer Suggested Question Coverage

From `ASK_AI_SUGGESTED_QUESTION_COVERAGE.md`: 8/8 chips pass parity. Buyer has 3 listing_facts chips + 5 static intent chips.

---

## 5. Field Universe — Landlord

**Model:** `LandlordAgentAuction` → `landlord_agent_auctions` + `landlord_agent_auction_metas`  
**Role:** Rental property listing — all property/lease fields stored in EAV  
**Property types:** Residential (Single Family, Condo, Multi-family), Commercial

### 5.1 Structural / Factual Fields (All EAV — `infoGet` throughout)

All Landlord fields are stored in `landlord_agent_auction_metas`. The context builder correctly uses `infoGet()` throughout. Nearly all fields are Fully Connected except one key mismatch.

| # | Ask AI ID | Display Label | Meta Key | Context Key | Classification | Lineage Status | Notes |
|---|---|---|---|---|---|---|---|
| 1 | `LND-001` | Property Address | `property_address` | `address` | DATABASE-FIRST | Fully Connected | ✓ |
| 2 | `LND-002` | Property Description | `property_description` | `description` | AI-OPTIONAL | Fully Connected | ✓ |
| 3 | `LND-003` | Rent Amount | `maximum_budget` (or `rent_amount`) | `rent` | DATABASE-FIRST | Fully Connected | `infoGet('maximum_budget')` ✓ |
| 4 | `LND-004` | Bedrooms | `bedrooms` | `bedrooms` | DATABASE-FIRST | Fully Connected | ✓ |
| 5 | `LND-005` | Bathrooms | `bathrooms` | `bathrooms` | DATABASE-FIRST | Fully Connected | ✓ |
| 6 | `LND-006` | Square Footage | `minimum_heated_square` | `square_feet` | DATABASE-FIRST | Fully Connected | ✓ |
| 7 | `LND-007` | Unit Size | `unit_size` | `unit_size` | DATABASE-FIRST | Fully Connected | ✓ |
| 8 | `LND-008` | Number of Units | `number_of_unit` | `number_of_units` | DATABASE-FIRST | Fully Connected | Note: saved key is `number_of_unit` (no trailing 's'); `infoGet('number_of_unit')` ✓ |
| 9 | `LND-009` | Property Zip | `property_zip` | `property_zip` | DATABASE-FIRST | Fully Connected | ✓ |
| 10 | `LND-010` | Property Items / Features | `property_items` (JSON) | `property_items` | DATABASE-FIRST | Fully Connected | JSON decoded ✓ |
| 11 | `LND-011` | Property Condition | `condition_prop` | `condition_prop` | DATABASE-FIRST | Fully Connected | ✓ |
| 12 | `LND-012` | Appliances | `appliances` (JSON) | `appliances` | DATABASE-FIRST | Fully Connected | JSON decoded ✓ |
| 13 | `LND-013` | View / Water View | `view_preference` (JSON) | `water_view` + `view` | DATABASE-FIRST | Fully Connected | JSON decoded; fixed from `water_view` → `view_preference` ✓ |
| 14 | `LND-014` | Pet Policy | `pet_policy` | `pets` | DATABASE-FIRST | Fully Connected | ✓ |
| 15 | `LND-015` | Pet Deposit / Fee | `pet_deposit_fee_rent` | `pet_deposit_fee_rent` | DATABASE-FIRST | Fully Connected | ✓ |
| 16 | `LND-016` | Max Pet Weight | `pet_max_weight_lbs` | — | DATABASE-FIRST | **Missing Coverage** | Saved as EAV but not in `LISTING_KEY_KEYWORD_MAP`; no direct Ask AI route |
| 17 | `LND-017` | Pet Species Allowed | `pet_species_allowed` (JSON) | `pet_species_allowed` | DATABASE-FIRST | Fully Connected | JSON decoded ✓ |
| 18 | `LND-018` | Parking Terms | `parking_terms` | `parking_terms` | DATABASE-FIRST | Fully Connected | ✓ |
| 19 | `LND-019` | Utilities Included | `property_utilities` → `utilities` | `utilities` | DATABASE-FIRST | Fully Connected | Fixed cascade ✓ |
| 20 | `LND-020` | Laundry Policy | `laundry_policy` | `laundry` | DATABASE-FIRST | Fully Connected | ✓ |
| 21 | `LND-021` | Lease Term | `lease_term` | `lease_term` | DATABASE-FIRST | Fully Connected | ✓ |
| 22 | `LND-022` | Available Date | `available_date` | `available_date` | DATABASE-FIRST | Fully Connected | ✓ |
| 23 | `LND-023` | Security Deposit | `security_deposit` | `security_deposit` | DATABASE-FIRST | Fully Connected | ✓ |
| 24 | `LND-024` | Has HOA | `has_hoa` | `hoa_association` | DATABASE-FIRST | Fully Connected | ✓ |
| 25 | `LND-025` | Association Fee Amount | `association_fee_amount` | — | DATABASE-FIRST | **Missing Coverage** | Saved as EAV; extractable but no specific Ask AI route entry in LISTING_KEY_KEYWORD_MAP |
| 26 | `LND-026` | Association Amenities | `association_amenities` (JSON) | `association_amenities` | DATABASE-FIRST | Fully Connected | JSON decoded ✓ |
| 27 | `LND-027` | Annual Property Taxes | `annual_property_taxes` | `annual_property_taxes` | DATABASE-FIRST | Fully Connected | ✓ |
| 28 | `LND-028` | Number of Occupants Allowed | `number_occupant` | — | DATABASE-FIRST | **Partially Connected** | Context reads `number_of_occupants_allowed` but saved as `number_occupant`; key mismatch — 1 known FAIL |
| 29 | `LND-029` | Property Type | `property_type` | `property_type` | DATABASE-FIRST | Fully Connected | ✓ |
| 30 | `LND-030` | Smoking Policy | `smoking_policy` | `smoking_policy` | DATABASE-FIRST | Fully Connected | ✓ |
| 31 | `LND-031` | Subletting Policy | `subletting_policy` | `subletting_policy` | DATABASE-FIRST | Fully Connected | ✓ |

### 5.2 Landlord FAQ Answer Fields — Base (26 entries)

All 26 base entries have `keyword_route_status: pinned`.

| # | Ask AI ID | Config Key | Canonical Path | Section | Primary Question |
|---|---|---|---|---|---|
| 32 | `LND-FAQ-001` | `maintenance_request_response_time` | `faq_answers.maintenance_request_response_time` | Maintenance | "What is the typical maintenance response time?" |
| 33 | `LND-FAQ-002` | `emergency_maintenance_available` | `faq_answers.emergency_maintenance_available` | Maintenance | "Is emergency maintenance available after hours?" |
| 34 | `LND-FAQ-003` | `heating_cooling_system` | `faq_answers.heating_cooling_system` | Systems | "What type of heating and cooling system is in this property?" |
| 35 | `LND-FAQ-004` | `laundry_situation` | `faq_answers.laundry_situation` | Amenities | "Is there in-unit laundry or shared laundry facilities?" |
| 36 | `LND-FAQ-005` | `storage_area_included` | `faq_answers.storage_area_included` | Amenities | "Is storage included with this rental?" |
| 37 | `LND-FAQ-006` | `internet_providers` | `faq_answers.internet_providers` | Utilities | "Which internet providers are available at this property?" |
| 38 | `LND-FAQ-007` | `security_features` | `faq_answers.security_features` | Building | "What security features does this property have?" |
| 39 | `LND-FAQ-008` | `planned_renovations` | `faq_answers.planned_renovations` | Building | "Are there any planned renovations or construction?" |
| 40 | `LND-FAQ-009` | `noise_levels` | `faq_answers.noise_levels` | Location | "What are the typical noise levels around this property?" |
| 41 | `LND-FAQ-010` | `nearby_amenities` | `faq_answers.nearby_amenities` | Location | "What amenities are nearby?" |
| 42 | `LND-FAQ-011` | `guest_parking` | `faq_answers.guest_parking` | Parking | "Is guest parking available?" |
| 43 | `LND-FAQ-012` | `proximity_to_public_transit` | `faq_answers.proximity_to_public_transit` | Location | "How close is this property to public transit?" |
| 44 | `LND-FAQ-013` | `furnished_or_unfurnished` | `faq_answers.furnished_or_unfurnished` | Furnishing | "Is this rental furnished or unfurnished?" |
| 45 | `LND-FAQ-014` | `lease_renewal_process` | `faq_answers.lease_renewal_process` | Lease Terms | "What is the lease renewal process?" |
| 46 | `LND-FAQ-015` | `notice_to_vacate_required` | `faq_answers.notice_to_vacate_required` | Lease Terms | "How much notice is required to vacate?" |
| 47 | `LND-FAQ-016` | `preferred_tenant_qualities` | `faq_answers.preferred_tenant_qualities` | Landlord Preference | "What qualities does the landlord look for in a tenant?" |
| 48 | `LND-FAQ-017` | `subletting_allowed` | `faq_answers.subletting_allowed` | Policies | "Is subletting allowed?" |
| 49 | `LND-FAQ-018` | `short_term_rentals_allowed` | `faq_answers.short_term_rentals_allowed` | Policies | "Are short-term rentals (e.g., Airbnb) allowed?" |
| 50 | `LND-FAQ-019` | `ev_charging_available` | `faq_answers.ev_charging_available` | Amenities | "Is EV charging available?" |
| 51 | `LND-FAQ-020` | `bicycle_storage_available` | `faq_answers.bicycle_storage_available` | Amenities | "Is there bicycle storage?" |
| 52 | `LND-FAQ-021` | `what_makes_property_unique` | `faq_answers.what_makes_property_unique` | Marketing | "What makes this rental stand out?" |
| 53 | `LND-FAQ-022` | `pest_or_mold_history` | `faq_answers.pest_or_mold_history` | Disclosure | "Is there any history of pests or mold?" |
| 54 | `LND-FAQ-023` | `utilities_individually_metered` | `faq_answers.utilities_individually_metered` | Utilities | "Are utilities individually metered?" |
| 55 | `LND-FAQ-024` | `renters_insurance_required` | `faq_answers.renters_insurance_required` | Policies | "Is renters insurance required?" |
| 56 | `LND-FAQ-025` | `lease_to_own_option` | `faq_answers.lease_to_own_option` | Lease Terms | "Is a lease-to-own arrangement available?" |
| 57 | `LND-FAQ-026` | `previous_tenant_feedback` | `faq_answers.previous_tenant_feedback` | Marketing | "How have previous tenants described living here?" |
| 58 | `LND-FAQ-027` | `smoking_policy` | `faq_answers.smoking_policy` | Policies | "Is smoking allowed inside the unit or anywhere on the property?" |

### 5.3 Landlord Add-On FAQ Fields — Commercial (12 entries)

All 12 entries have `keyword_route_status: pinned`.

| # | Ask AI ID | Config Key | Canonical Path |
|---|---|---|---|
| 58 | `LND-COM-001` | `commercial_cam_charges` | `faq_answers.commercial_cam_charges` |
| 59 | `LND-COM-002` | `commercial_lease_structure_type` | `faq_answers.commercial_lease_structure_type` |
| 60 | `LND-COM-003` | `commercial_tenant_improvement_allowance` | `faq_answers.commercial_tenant_improvement_allowance` |
| 61 | `LND-COM-004` | `commercial_buildout_flexibility` | `faq_answers.commercial_buildout_flexibility` |
| 62 | `LND-COM-005` | `commercial_signage_rights` | `faq_answers.commercial_signage_rights` |
| 63 | `LND-COM-006` | `commercial_loading_dock_freight_elevator` | `faq_answers.commercial_loading_dock_freight_elevator` |
| 64 | `LND-COM-007` | `commercial_electrical_capacity` | `faq_answers.commercial_electrical_capacity` |
| 65 | `LND-COM-008` | `commercial_parking_ratio` | `faq_answers.commercial_parking_ratio` |
| 66 | `LND-COM-009` | `commercial_exclusivity_rights` | `faq_answers.commercial_exclusivity_rights` |
| 67 | `LND-COM-010` | `commercial_expansion_option_rofr` | `faq_answers.commercial_expansion_option_rofr` |
| 68 | `LND-COM-011` | `commercial_landlord_maintenance_responsibilities` | `faq_answers.commercial_landlord_maintenance_responsibilities` |
| 69 | `LND-COM-012` | `commercial_building_access_hours` | `faq_answers.commercial_building_access_hours` |

### 5.4 Landlord Suggested Question Coverage

12/12 chips pass parity. 8 listing_facts chips + 4 static intent chips.

---

## 6. Field Universe — Tenant

**Model:** `TenantAgentAuction` → `tenant_agent_auctions` + `tenant_agent_auction_metas`  
**Role:** Tenant criteria listing — all fields stored in EAV  
**Property types:** Residential, Commercial

### 6.1 Structural / Factual Fields (All EAV — all Fully Connected)

All Tenant structural fields were confirmed PASS in the extraction audit. Zero FAILs.

| # | Ask AI ID | Display Label | Meta Key | Context Key | Classification | Lineage Status | Notes |
|---|---|---|---|---|---|---|---|
| 1 | `TEN-001` | Desired Location / Area | `desired_location` | `address` | DATABASE-FIRST | Fully Connected | ✓ |
| 2 | `TEN-002` | Additional Requirements | `additional_requirements` | `description` | AI-OPTIONAL | Fully Connected | ✓ |
| 3 | `TEN-003` | Maximum Rent Budget | `budget` → `maximum_budget` | `max_rent` | DATABASE-FIRST | Fully Connected | Context key is `max_rent` (not `rental_budget`); cascade ✓ |
| 4 | `TEN-004` | Bedrooms | `bedrooms` | `bedrooms` | DATABASE-FIRST | Fully Connected | ✓ |
| 5 | `TEN-005` | Bathrooms | `bathrooms` | `bathrooms` | DATABASE-FIRST | Fully Connected | ✓ |
| 6 | `TEN-006` | Desired Lease Length | `desired_lease_length` (JSON) | `desired_lease_length` | DATABASE-FIRST | Fully Connected | Multi-select JSON decoded ✓ |
| 7 | `TEN-007` | Desired Lease Length (scalar) | `tenant_desired_lease_length` | `desired_lease_length` (scalar fallback) | DATABASE-FIRST | Fully Connected | ✓ |
| 8 | `TEN-008` | Pets | `pets` | `pets` | DATABASE-FIRST | Fully Connected | ✓ |
| 9 | `TEN-009` | Pet Species | `pet_species` (JSON) | `pet_species` | DATABASE-FIRST | Fully Connected | JSON decoded ✓ |
| 10 | `TEN-010` | Parking Needed | `parking_needed` → `parking` | `parking` | DATABASE-FIRST | Fully Connected | ✓ |
| 11 | `TEN-011` | Move-In Date | `move_in_date` | `move_in_date` | DATABASE-FIRST | Fully Connected | ✓ |
| 12 | `TEN-012` | Max HOA Fee Acceptable | `hoa_max_monthly_fee` | `max_hoa_fee` | DATABASE-FIRST | Fully Connected | ✓ |
| 13 | `TEN-013` | Property Items Desired | `property_items` (JSON) | `property_items` | DATABASE-FIRST | Fully Connected | JSON decoded ✓ |
| 14 | `TEN-014` | Appliances Needed | `appliances` (JSON) | `appliances` | DATABASE-FIRST | Fully Connected | JSON decoded ✓ |
| 15 | `TEN-015` | Property Condition Acceptable | `condition_prop` | `condition_prop` | DATABASE-FIRST | Fully Connected | ✓ |
| 16 | `TEN-016` | Pet Information | `pet_information` | `pet_information` | DATABASE-FIRST | Fully Connected | ✓ |
| 17 | `TEN-017` | Utility Preference | `utility_preference` | `utility_preference` | DATABASE-FIRST | Fully Connected | ✓ |
| 18 | `TEN-018` | Utilities (desired) | `utilities` | `utilities` | DATABASE-FIRST | Fully Connected | ✓ |
| 19 | `TEN-019` | Tenant Pays | `tenant_pays` (JSON) | `tenant_pays` | DATABASE-FIRST | Fully Connected | JSON decoded ✓ |
| 20 | `TEN-020` | Current Status | `current_status` | `current_status` | DATABASE-FIRST | Fully Connected | ✓ |
| 21 | `TEN-021` | Number of Occupants | `number_of_occupants` | `number_of_occupants` | DATABASE-FIRST | Fully Connected | Note: Tenant uses `number_of_occupants`; Landlord uses `number_occupant` — different keys |
| 22 | `TEN-022` | Number of Units | `number_of_unit` | `number_of_unit` | DATABASE-FIRST | Fully Connected | ✓ |
| 23 | `TEN-023` | Property Type | `property_type` | `property_type` | DATABASE-FIRST | Fully Connected | ✓ |
| 24 | `TEN-024` | Credit Score Range | `credit_score_range` → `credit_score` | `credit_score_range` | DATABASE-FIRST | Fully Connected | Fixed (was BLOCKED, added June 2026). RESTRICTED for public display — see Section 8. |

### 6.2 Tenant FAQ Answer Fields — Opaque Keys (faq_q1–faq_q20 base, faq_q21–faq_q27 commercial)

Tenant FAQs use opaque sequential keys (`faq_q1` through `faq_q27`) because the config system stores tenant FAQ answers without meaningful machine-readable config keys. All 27 entries are `keyword_route_status: pinned` — they DO have keyword routes and ARE in `FAQ_KEY_KEYWORD_MAP`. However, they cannot be individually identified by their config key alone; the label must be cross-referenced to determine which question was answered.

**Gap:** These entries lack the `natural_questions` field that would allow the registry to fully describe what the opaque key holds. A future schema change should add `natural_questions` arrays to these entries or replace the opaque keys with descriptive config keys (e.g., `tenant_work_from_home`, `tenant_daily_routine`).

| # | Ask AI ID | Config Key | Canonical Path | Label (Current) | Suggested Descriptive Key |
|---|---|---|---|---|---|
| 25 | `TEN-FAQ-001` | `faq_q1` | `faq_answers.faq_q1` | Do you work from home or need a dedicated work space? | `tenant_wfh_needs` |
| 26 | `TEN-FAQ-002` | `faq_q2` | `faq_answers.faq_q2` | What does your daily routine at home look like? | `tenant_daily_routine` |
| 27 | `TEN-FAQ-003` | `faq_q3` | `faq_answers.faq_q3` | Do you prefer a walkable neighborhood or a quieter area? | `tenant_walkability_preference` |
| 28 | `TEN-FAQ-004` | `faq_q4` | `faq_answers.faq_q4` | Can you tolerate living near a busy street? | `tenant_noise_street_tolerance` |
| 29 | `TEN-FAQ-005` | `faq_q5` | `faq_answers.faq_q5` | Are specific amenities (laundry, parking) important enough to affect rent? | `tenant_amenity_priorities` |
| 30 | `TEN-FAQ-006` | `faq_q6` | `faq_answers.faq_q6` | Do you want outdoor space (patio, balcony, yard)? | `tenant_outdoor_space` |
| 31 | `TEN-FAQ-007` | `faq_q7` | `faq_answers.faq_q7` | Do you have pets requiring outdoor access? | `tenant_pet_outdoor_access` |
| 32 | `TEN-FAQ-008` | `faq_q8` | `faq_answers.faq_q8` | Would a no-pet-deposit policy affect your decision? | `tenant_pet_deposit_sensitivity` |
| 33 | `TEN-FAQ-009` | `faq_q9` | `faq_answers.faq_q9` | Would you sign a longer lease for a lower rate? | `tenant_lease_rate_flexibility` |
| 34 | `TEN-FAQ-010` | `faq_q10` | `faq_answers.faq_q10` | Does the unit need to include furniture? | `tenant_furnished_requirement` |
| 35 | `TEN-FAQ-011` | `faq_q11` | `faq_answers.faq_q11` | Is your move-in date firm or flexible? | `tenant_movein_flexibility` |
| 36 | `TEN-FAQ-012` | `faq_q12` | `faq_answers.faq_q12` | How would you handle an unexpected lease break? | `tenant_lease_break_plan` |
| 37 | `TEN-FAQ-013` | `faq_q13` | `faq_answers.faq_q13` | How many months would you commit to for a discount? | `tenant_commitment_for_discount` |
| 38 | `TEN-FAQ-014` | `faq_q14` | `faq_answers.faq_q14` | Is your search driven by a job change or life event? | `tenant_search_driver` |
| 39 | `TEN-FAQ-015` | `faq_q15` | `faq_answers.faq_q15` | How long was your most recent tenancy, and why are you moving? | `tenant_prior_tenancy_length` |
| 40 | `TEN-FAQ-016` | `faq_q16` | `faq_answers.faq_q16` | Are you looking for a short-term or long-term home? | `tenant_term_preference` |
| 41 | `TEN-FAQ-017` | `faq_q17` | `faq_answers.faq_q17` | Do you have a landlord or employer reference available? | `tenant_reference_available` |
| 42 | `TEN-FAQ-018` | `faq_q18` | `faq_answers.faq_q18` | What is the source of your income? | `tenant_income_source` |
| 43 | `TEN-FAQ-019` | `faq_q19` | `faq_answers.faq_q19` | How do you prefer to communicate with a landlord? | `tenant_communication_preference` |
| 44 | `TEN-FAQ-020` | `faq_q20` | `faq_answers.faq_q20` | What's your biggest concern in this rental search? | `tenant_biggest_concern` |
| 45 | `TEN-COM-001` | `faq_q21` | `faq_answers.faq_q21` | What type of business will be operating from this space? | `tenant_business_type` |
| 46 | `TEN-COM-002` | `faq_q22` | `faq_answers.faq_q22` | Do you expect customer or client foot traffic? | `tenant_foot_traffic` |
| 47 | `TEN-COM-003` | `faq_q23` | `faq_answers.faq_q23` | Do you have special equipment or power requirements? | `tenant_power_requirements` |
| 48 | `TEN-COM-004` | `faq_q24` | `faq_answers.faq_q24` | Do you require exterior building signage? | `tenant_signage_requirement` |
| 49 | `TEN-COM-005` | `faq_q25` | `faq_answers.faq_q25` | Will you need to modify or build out the space? | `tenant_buildout_needed` |
| 50 | `TEN-COM-006` | `faq_q26` | `faq_answers.faq_q26` | What are your expected hours of operation? | `tenant_hours_of_operation` |
| 51 | `TEN-COM-007` | `faq_q27` | `faq_answers.faq_q27` | Are you flexible on commercial lease term length? | `tenant_commercial_term_flexibility` |

### 6.3 Tenant Suggested Question Coverage

From `ASK_AI_SUGGESTED_QUESTION_COVERAGE.md` (Tenant section): Tenant chips pass parity. Tenant has listing_facts chips for `max_rent`, `bedrooms`, `desired_lease_length` and static intent chips for `buyer_tenant_match`, `missing_data`, `educational`.

---

## 7. Property-Type-Specific and Cross-Role Fields

### 7.1 Fields Conditional on Property Type

| Property Type | Roles | Conditional Fields | Mechanism |
|---|---|---|---|
| Commercial / Income | Seller | FAQ: `annual_net_operating_income`, `current_cap_rate`, `existing_tenant_lease_terms`, `current_occupancy_rate`, `annual_operating_expenses_detail`, `value_add_opportunities` | Config `ai_faq_seller_commercial_income.php`; shown when `property_type = Commercial/Income` |
| Business Opportunity | Seller | FAQ: `annual_business_revenue`, `annual_net_profit`, `business_reason_for_selling`, `business_employee_count`, `seller_training_transition`, `business_lease_status`, `inventory_equipment_included` | Config `ai_faq_seller_business.php` |
| Vacant Land | Seller | FAQ: `land_utilities_availability`, `land_zoning_permitted_uses`, `land_access_and_road`, `land_soil_and_topography`, `land_survey_available`, `land_development_restrictions` | Config `ai_faq_seller_land.php` |
| Commercial | Buyer | FAQ (match_criteria): `com_*` (8 fields), `biz_*` (7 fields), `land_*` (7 fields) | Config addon registry |
| Commercial | Landlord | FAQ: all 12 `commercial_*` add-on entries | Config `ai_faq_landlord_commercial.php`; shown for commercial lease type |
| Commercial | Tenant | FAQ: `faq_q21`–`faq_q27` (7 entries) | Config tenant commercial add-on |

### 7.2 Cross-Role Shared Fields

Fields that appear in multiple roles but may use different meta keys:

| Field | Seller | Buyer | Landlord | Tenant |
|---|---|---|---|---|
| Bedrooms | `bedrooms` meta | `bedrooms` meta | `bedrooms` meta | `bedrooms` meta |
| Bathrooms | `bathrooms` meta | `bathrooms` meta | `bathrooms` meta | `bathrooms` meta |
| Square Footage | `minimum_heated_square` | `minimum_heated_square` | `minimum_heated_square` | `minimum_heated_square` |
| Property Type | `property_type` | `property_type` | `property_type` | `property_type` |
| Pets | `pets` (allowed flag) | `pets` (has pets) | `pet_policy` (policy text) | `pets` (has pets) |
| Annual Taxes | `annual_property_taxes` | — | `annual_property_taxes` | — |
| Max Rent / Price | `maximum_budget` (sale price) | `maximum_budget` (max budget) | `maximum_budget` (rent) | `budget`→`maximum_budget` (rent budget) |
| HOA | `has_hoa` + fees | `hoa_acceptance` + `hoa_max_monthly_fee` | `has_hoa` + fees | `hoa_max_monthly_fee` |
| Desired/Available Date | `target_closing_date` | `target_closing_date` | `available_date` | `move_in_date` |

**Architecture note:** The shared `maximum_budget` meta key across all four roles allows MLS import (`price → maximum_budget`) to work uniformly. However, the context builder exposes it under different context keys per role (`asking_price`, `max_price`, `rent`, `max_rent`), which is correct behaviour. Any database-first snapshot builder must account for this asymmetry.

---

## 8. Restricted Field Inventory

The following fields must **never** appear in public Ask AI responses. They are read into context for internal scoring / matching only (or should be stripped entirely).

| Field | Meta Key | Roles | Reason | Access Level |
|---|---|---|---|---|
| Credit Score Range | `credit_score_range` | Tenant | Fair Housing / protected-class-adjacent. Credit score is a screening criterion; surfacing it in a public response could facilitate discrimination. | **Completely blocked** — do not expose in any public AI response even if stored |
| Credit Score (raw) | `credit_score` | Tenant | Same as above — fallback key for credit_score_range | **Completely blocked** |
| Credit Score Rating | `credit_scroe_rating` (typo) | Tenant | Same as above; note the misspelling in the schema | **Completely blocked** |
| Prior Eviction Flag | `prior_eviction` | Tenant | Protected-class-adjacent screening criterion | **Completely blocked** |
| Eviction Explanation | `eviction_explanation` | Tenant | Protected-class-adjacent | **Completely blocked** |
| Prior Felony Flag | `prior_felony` | Tenant | Protected-class-adjacent / background screening | **Completely blocked** |
| Monthly Income | `monthly_income` | Tenant | PII — financial screening data | **Completely blocked** |
| Screening Concerns | `screening_concerns` | Tenant | Protected-class-adjacent | **Completely blocked** |
| Pre-approval Amount | `preapproval_amount` | Buyer | Specific financial amount; negotiation leverage | **Agent-only** — should not surface in public responses |
| Referral Percentage | `referral_percentage` | All | Internal compensation field | **Internal-only** |
| Referral Source Code | `referral_source_code` | All | Internal referral system | **Internal-only** |
| Draft Version | `draft_version` | All | Internal version control | **Internal-only** |
| Parent Draft ID | `parent_draft_id` | All | Internal version control | **Internal-only** |
| Draft Payload Hash | `draft_payload_hash` | All | Internal version control | **Internal-only** |
| Service Type | `service_type` | All | Internal workflow field (full_service / limited_service) | **Internal-only** |
| Custom Services / Fees | `fees`, `enable`, `custom_services` | All | Internal compensation/catalog fields | **Internal-only** |
| Agent Brokerage | `agent_brokerage` | All | Internal agent-identity | **Agent-only** |
| Agent License Number | `agent_license_number` | All | Internal agent-identity | **Agent-only** |
| Agent NAR Member ID | `agent_nar_member_id` | All | Internal agent-identity | **Agent-only** |
| First/Last Name | `first_name`, `last_name` | All | PII — governance contract | **Completely blocked** |
| Phone Number | `phone_number` | All | PII | **Completely blocked** |
| Email Address | `email` | All | PII | **Completely blocked** |

**Note on `credit_score_range`:** This field was added to the Tenant context builder in June 2026 and has a `listing.credit_score_range` entry in the listing field registry (`keyword_route_status: listing_native`). The LISTING_KEY_KEYWORD_MAP also contains an entry for it. This means it CAN be routed and answered — but it should only be answered in contexts where the tenant themselves is asking about their own listing, not where a landlord or public user is querying tenant screening data. The database-first architecture should enforce role-scoped access on this field at the answer-record level.

---

## 9. DB Column and Meta Key Mapping Reference

### 9.1 Schema Architecture Summary

| Role | Table | Native Column Style | EAV Table | Storage Pattern |
|---|---|---|---|---|
| Seller | `seller_agent_auctions` | Mixed (address, description, bedroom_id, is_sold, etc. are native; all property details are EAV) | `seller_agent_auction_metas` | ~60% EAV, ~40% native |
| Buyer | `buyer_agent_auctions` | Mixed (address, additional_details, financing_approved, cash_budget, etc. are native; all criteria fields are EAV) | `buyer_agent_auction_metas` | ~70% EAV, ~30% native |
| Landlord | `landlord_agent_auctions` | Minimal native (id, user_id, is_approved, is_draft, timestamps) — all property/lease data in EAV | `landlord_agent_auction_metas` | ~95% EAV |
| Tenant | `tenant_agent_auctions` | Similar to Landlord | `tenant_agent_auction_metas` | ~95% EAV |

### 9.2 Seller Native Columns (from `seller_agent_auctions`)

`id, user_id, address, auction_type, auction_length, city_id, county_id, state_id, bathroom_id, bedroom_id, sqft, min_price, max_commission, financings, additional_services, important_info, contract_terms, description, prop_condition, description_ideal_agent, need_cma, photos, video_url, video_file, audio_file, is_approved, is_sold, is_paid, sold_date, created_at, updated_at, listing_id, title, is_draft, referring_agent_id, referral_source_code, referral_captured_at, referral_locked`

**Key Ask AI note:** `address` and `description` are native (correct); `bedroom_id` and `bathroom_id` are FK integers — the human-readable counts are in EAV as `bedrooms` and `bathrooms`.

### 9.3 Buyer Native Columns (from `buyer_agent_auctions`)

`id, user_id, address, title, auction_type, auction_length, city_id, county_id, state_id, bathroom_id, bedroom_id, property_type_id, concession, financing_currency, financing_approved, need_lender, preapproval_amount, additional_details, other, cash_budget, crypto_budget, is_approved, is_sold, is_paid, sold_date, created_at, updated_at, listing_id, is_draft, referring_agent_id, referral_source_code, referral_captured_at, referral_locked`

**Key Ask AI note:** Buyer's description is `additional_details` (native), NOT `description`. Context builder uses wrong key.

### 9.4 Landlord/Tenant Native Columns

Both have minimal native columns (id, user_id, is_approved, is_draft, is_sold, timestamps, listing_id, referring_agent_id, referral_*). All domain-specific data is in EAV metas.

### 9.5 Critical Meta Key Mapping Table

| Context Key | Seller Meta | Buyer Meta | Landlord Meta | Tenant Meta |
|---|---|---|---|---|
| Price / Budget | `maximum_budget` | `maximum_budget` | `maximum_budget` | `budget` → `maximum_budget` |
| Square Footage | `minimum_heated_square` | `minimum_heated_square` | `minimum_heated_square` | — |
| View | `view_preference` (JSON) | *(not saved)* | `view_preference` (JSON) | *(not collected)* |
| HOA Flag | `has_hoa` | *(not saved)* | `has_hoa` | *(not collected)* |
| HOA Fee | `association_fee_amount` | *(not saved)* | `association_fee_amount` | *(not collected)* |
| Pets | `pets` (allowed?) | `pets` (has pets?) | `pet_policy` (policy text) | `pets` (has pets?) |
| Lease Period | *(not applicable)* | *(not applicable)* | `lease_term` | `desired_lease_length` (JSON) + `tenant_desired_lease_length` (scalar) |
| Utilities | *(not saved)* | *(not saved)* | `property_utilities` → `utilities` | `utilities` |
| Closing / Move-in | `target_closing_date` | `target_closing_date` | `available_date` | `move_in_date` |
| Flood Zone | `flood_zone_code` | *(not collected)* | *(not collected)* | *(not collected)* |

### 9.6 JSON-Stored EAV Keys (require `decodeJsonField()`)

| Meta Key | Roles | Content |
|---|---|---|
| `pool_type` | Seller | Array of pool type labels (e.g., `["Saltwater","Heated"]`) |
| `view_preference` | Seller, Landlord | Array of view types (e.g., `["Water View","City View"]`) |
| `appliances` | Landlord, Tenant | Array of appliance labels |
| `property_items` | Landlord, Tenant | Array of included features |
| `pet_species_allowed` | Landlord | Array of allowed species |
| `offered_financing` | Buyer | Array of financing type labels |
| `financing_type` | Buyer | Alias for `offered_financing` |
| `desired_lease_length` | Tenant | Array of lease length options |
| `pet_species` | Tenant | Array of tenant's pet species |
| `tenant_pays` | Tenant | Array of utility categories tenant pays |
| `association_amenities` | Landlord | Array of HOA amenity labels |

**All of the above filter literal `"Other"` (case-insensitive) via `decodeJsonField()` to prevent UI sentinel values leaking into AI prompts.**

---

## 10. End-to-End Field Lineage Audit

The full pipeline has five links:

```
[1] Form Field  →  [2] Livewire Property / saveMeta()  →  [3] DB Column / Meta Key
    →  [4] Context Builder (extractFactualFields / buildFaqAnswers)
    →  [5a] Listing Field Registry (listing.*)  OR  [5b] FAQ Registry (faq_answers.*)
    →  [6] Classifier / Router → Natural Language Route
    →  [7] Response Contract (allowed_context paths)
    →  [8] Ask AI Answer
```

### 10.1 Lineage Summary by Role

| Role | Fields Audited | Fully Connected | Partially Connected | Orphaned | Missing Coverage | Unused |
|---|---|---|---|---|---|---|
| Seller (structural) | 31 | 7 | 17 | 7 | 0 | 0 |
| Seller (FAQ base + addons) | 52 | 52 | 0 | 0 | 0 | 0 |
| Buyer (structural) | 25 | 3 | 18 | 4 | 0 | 0 |
| Buyer (FAQ — all match_criteria) | 50 | 50 | 0 | 0 | 0 | 0 |
| Landlord (structural) | 31 | 27 | 1 | 0 | 2 | 1 |
| Landlord (FAQ base + commercial) | 39 | 39 | 0 | 0 | 0 | 0 |
| Tenant (structural) | 24 | 24 | 0 | 0 | 0 | 0 |
| Tenant (FAQ opaque keys) | 27 | 27 | 0 | 0 | 0 | 0 |
| **Total** | **279** | **229** | **36** | **11** | **2** | **1** |

### 10.2 Broken Links by Category

**Category A — Wrong accessor type (`nativeGet` instead of `infoGet`) with matching key name (4 Seller, 2 Buyer):**
`year_built`, `pet_restrictions`, `flood_zone_code` (Seller); `is_sold → sold` (Seller); `bedrooms`, `bathrooms` (Buyer, Landlord not affected).

**Category B — Wrong accessor + wrong key name (13 Seller, 13 Buyer):**
All structural property detail fields that predate the EAV migration — price, square_feet, pool, carport, garage, HOA fields, pets, rental_restrictions, closing_date, etc. Full fix table in `ASK_AI_ALL_OFFER_TYPE_FIELD_EXTRACTION_AUDIT.md` Sections 8.1 and 8.2.

**Category C — Phantom keys / Orphaned (7 Seller, 4 Buyer):**
Fields the context builder reads that were never saved: `buy_now_price`, `water_view` (Seller/Buyer), `water_extras`, `hoa_fee_requirement`, `condo_fee`, `condo_fee_schedule`, `is_in_flood_zone`, `lease_terms`, `tenant_pays` (Seller), `landlord_pays` (Seller), `mls_id`, `showing_instructions` (Seller), `closing_days` (Buyer), `contingencies` aggregate (Buyer).

**Category D — Single key mismatch (1 Landlord):**
`number_of_occupants_allowed` → should be `number_occupant`.

**Category E — Saved but no Ask AI route (2 Landlord):**
`pet_max_weight_lbs`, `association_fee_amount` — saved in EAV and extractable but absent from `LISTING_KEY_KEYWORD_MAP` and listing field registry.

### 10.3 Specific Field Lineage: Selected Examples

#### SEL-003 (Asking Price) — Full Broken Chain

| Step | Expected | Actual | Status |
|---|---|---|---|
| Form input | `maximum_budget` (Livewire property) | ✓ Present | ✓ |
| `saveMeta()` | `saveMeta('maximum_budget', ...)` | ✓ Saved | ✓ |
| DB | `seller_agent_auction_metas` row with `key='maximum_budget'` | ✓ Saved | ✓ |
| Context builder | `infoGet('maximum_budget')` → ctx['asking_price'] | `nativeGet('starting_price')` — BROKEN | ❌ |
| Registry | `listing.asking_price` → `AskAiFieldQuestionRegistryService` | ✓ Present | ✓ |
| Classifier / Router | `listing_facts` → `listing.asking_price` | ✓ Routes correctly | ✓ |
| Contract | `listing.asking_price` in allowed_context | ✓ Allowed | ✓ |
| Answer | Context value is `null` (never extracted) | `insufficient_context` | ❌ |

**Root cause:** Steps 1–3 work; Step 4 is broken. Fix in `AskAiContextBuilderService::extractFactualFields()` seller branch only.

#### LND-028 (Number of Occupants) — Single Key Mismatch

| Step | Expected | Actual | Status |
|---|---|---|---|
| Form input | `number_occupant` (Livewire property) | ✓ | ✓ |
| `saveMeta()` | `saveMeta('number_occupant', ...)` | ✓ Saved | ✓ |
| DB | `landlord_agent_auction_metas` row with `key='number_occupant'` | ✓ | ✓ |
| Context builder | `infoGet('number_occupant')` | `infoGet('number_of_occupants_allowed')` — BROKEN (wrong key) | ❌ |

---

## 11. FAQ Registry Summary (168 Entries)

The `AskAiFieldQuestionRegistryService::registry()` method returns 168 entries across all four roles. Summary counts:

| Role | Base Entries | Add-On Entries | Route Status | Total |
|---|---|---|---|---|
| Seller | 33 | 19 (CI: 6, Biz: 7, VL: 6) | `pinned` | 52 |
| Landlord | 27 | 12 (Commercial: 12) | `pinned` | 39 |
| Buyer | 28 | 22 (Com: 8, Biz: 7, Land: 7) | `match_criteria` | 50 |
| Tenant | 20 | 7 (Commercial) | `pinned` (opaque keys) | 27 |
| **Total** | **108** | **60** | — | **168** |

> **Count verified:** `landlordBaseRegistry()` contains 27 entries (one more than the 26 previously noted in the earlier pass of this audit). The grand total of 168 is confirmed by counting `'config_key'` occurrences per method in `AskAiFieldQuestionRegistryService.php`. Section 3 of this document previously listed 26 Landlord base FAQ entries; the correct count is 27.

### 11.1 Route Status Distribution

| `keyword_route_status` | Count | Description |
|---|---|---|
| `pinned` | 90 | Seller + Landlord base/addon; Tenant (opaque). In `FAQ_KEY_KEYWORD_MAP`. Deterministic routing. |
| `match_criteria` | 50 | All Buyer entries. Route via `buyer_tenant_match`. NOT in `FAQ_KEY_KEYWORD_MAP`. |
| `umbrella_only` | 0 | No current umbrella-only entries (all pinned or match_criteria) |
| `opaque_key` | 27* | Tenant faq_q1–faq_q27. Listed as `pinned` but the config keys are opaque |
| `listing_native` | — | Listing field registry only (not in `registry()`) |

*Tenant entries use `keyword_route_status: pinned` in the code, but their config keys are opaque sequential integers rather than descriptive strings. The opaque nature is noted here as a distinct concern even though the route status is technically `pinned`.

### 11.2 Second Sample Questions

Every registry entry has both `sample_question` and `sample_question_2` injected by `withSecondQuestions()`. These are used by the OpenAI router prompt for semantic matching. All 168 entries have verified second questions.

---

## 12. Listing Field Registry Summary (45 Entries)

The `AskAiFieldQuestionRegistryService::listingFieldRegistry()` returns 45 entries using `listing.*` canonical paths and `keyword_route_status: listing_native`.

| # | Canonical Path | Roles | Label |
|---|---|---|---|
| 1 | `listing.annual_property_taxes` | seller, landlord | Annual Property Taxes |
| 2 | `listing.asking_price` | seller | Asking / Starting Price |
| 3 | `listing.max_price` | buyer | Buyer Maximum Price |
| 4 | `listing.rent_amount` | landlord | Monthly Rent |
| 5 | `listing.max_rent` | tenant | Tenant Maximum Rent Budget |
| 6 | `listing.bedrooms` | seller, buyer, landlord, tenant | Number of Bedrooms |
| 7 | `listing.bathrooms` | seller, buyer, landlord, tenant | Number of Bathrooms |
| 8 | `listing.square_feet` | seller, buyer, landlord, tenant | Square Footage |
| 9 | `listing.year_built` | seller | Year Built |
| 10 | `listing.description` | seller, buyer, landlord, tenant | Listing Description |
| 11 | `listing.condition_prop` | landlord, tenant | Property Condition |
| 12 | `listing.address` | seller, buyer | Property Address |
| 13 | `listing.property_type` | seller, buyer, landlord, tenant | Property Type |
| 14 | `listing.water_view` | seller, buyer, landlord | View / Water View |
| 15 | `listing.credit_score_range` | tenant | Credit Score Range *(RESTRICTED — see Section 8)* |
| 16 | `listing.pool` | seller, buyer | Pool |
| 17 | `listing.carport` | seller, buyer | Carport |
| 18 | `listing.garage` | seller, buyer | Garage |
| 19 | `listing.appliances` | landlord, tenant | Appliances Included |
| 20 | `listing.hoa_association` | seller | HOA / Association |
| 21 | `listing.hoa_fee` | seller | HOA Fee Amount |
| 22 | `listing.hoa_acceptable` | buyer | Buyer HOA Acceptability |
| 23 | `listing.has_hoa` | landlord | Has HOA |
| 24 | `listing.association_amenities` | landlord | Association Amenities |
| 25 | `listing.pets_allowed` | seller, buyer | Pets Allowed |
| 26 | `listing.pet_policy` | landlord | Pet Policy |
| 27 | `listing.pet_deposit_fee_rent` | landlord | Pet Deposit / Fee / Rent |
| 28 | `listing.pet_information` | tenant | Tenant Pet Information |
| 29 | `listing.lease_length` | landlord | Lease Length |
| 30 | `listing.desired_lease_length` | tenant | Tenant Desired Lease Length |
| 31 | `listing.renewal_option` | landlord | Renewal Option |
| 32 | `listing.rental_restrictions` | seller | Rental Restrictions |
| 33 | `listing.utilities` | landlord, tenant | Utilities Included |
| 34 | `listing.tenant_pays` | seller, tenant | Tenant Pays (Utilities) |
| 35 | `listing.smoking_policy` | landlord | Smoking Policy |
| 36 | `listing.subletting_policy` | landlord | Subletting Policy |
| 37 | `listing.parking_terms` | landlord | Parking Terms |
| 38 | `listing.available_date` | landlord, tenant | Available Date |
| 39 | `listing.closing_date` | seller, buyer | Preferred Closing Date |
| 40 | `listing.loan_pre_approved` | buyer | Loan Pre-Approval Status |
| 41 | `listing.financing_type` | buyer | Financing Type |
| 42 | `listing.inspection_period` | buyer | Inspection Period |
| 43 | `listing.inspection_contingency_buyer` | buyer | Inspection Contingency |
| 44 | `listing.appraisal_contingency_buyer` | buyer | Appraisal Contingency |
| 45 | `listing.financing_contingency_buyer` | buyer | Financing Contingency |
| 46* | `listing.flood_zone_code` | seller | Flood Zone Status |
| 47* | `listing.security_deposit` | landlord/tenant | Security Deposit |
| 48* | `listing.lease_option` | landlord | Lease Option |
| 49* | `listing.hoa_fee_requirement` | — | HOA Fee Requirement *(phantom from LISTING_KEY_KEYWORD_MAP)* |

*Items 46–49 are in the `LISTING_KEY_KEYWORD_MAP` (49 deterministic keys per `ASK_AI_FIELD_COVERAGE_AUDIT.md`) but may not all be in `listingFieldRegistry()`. The registry returns ~45 entries; the LISTING_KEY_KEYWORD_MAP has 49.

---

## 13. Database-First Architecture Design

### 13.1 Problem Statement

Every Ask AI question currently triggers:
1. A live DB read to assemble context (O(N) EAV queries per listing)
2. An OpenAI API call to generate the answer
3. No caching of either step for most question types

For Seller and Buyer listings, Step 1 silently fails (~17 fields each return null) before Step 2 even runs. The result is `insufficient_context` answers for factual questions that have stored data.

### 13.2 Proposed Architecture: Knowledge Snapshot System

The database-first architecture introduces a **Listing Knowledge Snapshot** — a pre-built structured document assembled once per listing lifecycle event (create / edit / submit) and stored in the DB. Answers to common questions are pre-generated from this snapshot.

```
Listing Create / Edit / Submit
    ↓
KnowledgeSnapshotBuilderService
    ├── extractFactualFields()          [fixed EAV reads — all fields correct]
    ├── buildFaqAnswers()               [read ai_faq_answers for this listing]
    ├── buildLocationIntelligence()     [city/state/county resolution]
    └── assembleSnapshot()             [serialize to JSON]
    ↓
listing_knowledge_snapshots table
    (listing_id, role, snapshot_json, version, built_at)
    ↓
AnswerRecordGeneratorService
    ├── For each DATABASE-FIRST field:
    │     → generate pre-built answer string (template-based)
    │     → store as ask_ai_answer_records(listing_id, field_key, answer, confidence=1.0)
    ├── For each AI-OPTIONAL field:
    │     → store raw value; mark for optional AI polish on first question
    └── For each AI-REQUIRED FAQ field:
          → store raw FAQ text; AI called on demand, answer cached
    ↓
SuggestedQuestionGeneratorService
    → Read snapshot + answer records
    → Generate context-aware suggested question chips
    → Store as ask_ai_suggested_questions(listing_id, role, questions_json)
```

### 13.3 Proposed Schema

```sql
-- Knowledge snapshot (one per listing per role, rebuilt on save)
CREATE TABLE listing_knowledge_snapshots (
    id              BIGSERIAL PRIMARY KEY,
    listing_id      BIGINT NOT NULL,
    role            VARCHAR(20) NOT NULL,       -- 'seller','buyer','landlord','tenant'
    snapshot_json   JSONB NOT NULL,
    version         INT NOT NULL DEFAULT 1,
    built_at        TIMESTAMP NOT NULL,
    UNIQUE (listing_id, role)
);

-- Pre-built answer records (one per eligible field per listing)
CREATE TABLE ask_ai_answer_records (
    id              BIGSERIAL PRIMARY KEY,
    listing_id      BIGINT NOT NULL,
    role            VARCHAR(20) NOT NULL,
    field_key       VARCHAR(120) NOT NULL,      -- canonical path e.g. 'listing.bedrooms'
    field_type      VARCHAR(30) NOT NULL,       -- 'listing_model' or 'faq'
    answer_text     TEXT,
    confidence      NUMERIC(3,2) DEFAULT 1.0,   -- 1.0=db-first, 0.8=ai-optional, 0.5=ai-required
    answer_source   VARCHAR(20) NOT NULL,       -- 'database','ai_cached','ai_live'
    generated_at    TIMESTAMP NOT NULL,
    expires_at      TIMESTAMP,
    UNIQUE (listing_id, role, field_key)
);

-- Cached suggested questions (rebuilt when snapshot changes)
CREATE TABLE ask_ai_suggested_questions (
    id              BIGSERIAL PRIMARY KEY,
    listing_id      BIGINT NOT NULL,
    role            VARCHAR(20) NOT NULL,
    questions_json  JSONB NOT NULL,
    built_at        TIMESTAMP NOT NULL,
    UNIQUE (listing_id, role)
);
```

### 13.4 Snapshot Build Flow

```php
// Triggered on: listing submit, listing edit (full save), hire-agent flow completion
KnowledgeSnapshotBuilderService::buildForListing(int $listingId, string $role): void
{
    // 1. Extract structured facts (after extraction bug fixes)
    $facts = $contextBuilder->extractFactualFields($listing, $role);
    
    // 2. Read all FAQ answers for this listing
    $faqAnswers = $contextBuilder->buildFaqAnswers($listing, $role);
    
    // 3. Assemble snapshot
    $snapshot = [
        'listing_id'   => $listingId,
        'role'         => $role,
        'facts'        => $facts,
        'faq_answers'  => $faqAnswers,
        'built_at'     => now()->toISOString(),
        'fact_count'   => count(array_filter($facts)),
        'faq_count'    => count(array_filter($faqAnswers)),
    ];
    
    // 4. Persist snapshot
    DB::table('listing_knowledge_snapshots')
        ->upsert(['listing_id' => $listingId, 'role' => $role, ...], ['listing_id', 'role']);
    
    // 5. Generate DATABASE-FIRST answer records from templates
    foreach ($listingFieldRegistry as $path => $entry) {
        if ($entry['field_type'] !== 'listing_model') continue;
        $value = $facts[$entry['config_key']] ?? null;
        if ($value !== null) {
            $answerText = AnswerTemplateService::format($path, $value, $role);
            // Upsert into ask_ai_answer_records
        }
    }
    
    // 6. Pre-fill FAQ answer records from stored faq_answers
    foreach ($faqAnswers as $key => $text) {
        if ($text) {
            // Store raw FAQ text; confidence 0.8 (AI-OPTIONAL polish on first question)
        }
    }
}
```

### 13.5 Runner Modification (Database-First Check)

```php
// AskAiRunnerV2Service::run() — add database-first check before OpenAI call
private function tryDatabaseFirstAnswer(string $fieldKey, int $listingId, string $role): ?array
{
    $record = DB::table('ask_ai_answer_records')
        ->where('listing_id', $listingId)
        ->where('role', $role)
        ->where('field_key', $fieldKey)
        ->where('answer_source', 'database')
        ->where(function($q) { $q->whereNull('expires_at')->orWhere('expires_at', '>', now()); })
        ->first();
    
    if ($record && $record->answer_text) {
        return [
            'status'       => 'ready',
            'answer'       => $record->answer_text,
            'source'       => 'database_first',
            'confidence'   => $record->confidence,
        ];
    }
    return null;
}
```

### 13.6 OpenAI Fallback Policy

| Field Classification | Trigger | Cache Policy |
|---|---|---|
| DATABASE-FIRST (structured value available) | Never call OpenAI | N/A — answer pre-built from template |
| DATABASE-FIRST (value is null / not set) | Return `insufficient_context` | N/A |
| AI-OPTIONAL (value available, polish requested) | Call OpenAI once, cache result | Cache indefinitely until listing edit |
| AI-REQUIRED (FAQ text available) | Call OpenAI on demand; cache 30 days | Invalidate on listing edit or FAQ answer update |
| AI-REQUIRED (FAQ text missing) | Return `insufficient_context` | N/A |
| `educational`, `missing_data`, `property_standout` intents | Always call OpenAI | Cache 24 hours per unique question hash |
| Any OpenAI failure | Return `failed` | Do not cache failures |

### 13.7 Estimated Question Coverage Without AI

After extraction bug fixes (Groups 1 & 2 from the extraction audit) and snapshot implementation:

| Role | Question Category | % Without AI (current) | % Without AI (target) |
|---|---|---|---|
| Seller | Structural facts (price, beds, baths, sqft, etc.) | ~5% (address only works) | ~100% after fix |
| Seller | Property condition, lifestyle, transaction (FAQ) | ~0% (context broken so AI has no data) | ~95% (FAQ answers pre-read) |
| Seller | Marketing / standout / suited audience | 0% (AI required) | 0% |
| Buyer | Criteria facts (max price, beds, etc.) | ~10% | ~100% after fix |
| Buyer | Match criteria FAQ (buyer_* entries) | 80% (FAQ text stored) | 95% |
| Landlord | Structural facts | ~85% | ~98% |
| Landlord | FAQ answers | ~80% (depends on FAQ completion) | ~95% |
| Tenant | All structural facts | ~95% | ~98% |
| Tenant | FAQ answers (opaque keys) | ~80% | ~95% |

**Overall estimated answerable-without-AI (post-fix, with snapshot):** ~70% of all questions

---

## 14. Gap Analysis and Coverage Matrix

### 14.1 Master Coverage Matrix

**Column definitions:**
- **Stored**: Field is saved to DB by the form
- **Displayed**: Field is shown in the listing view Blade
- **Context Builder**: Field is correctly extracted by `extractFactualFields()` or `buildFaqAnswers()`
- **Registry**: Field has an entry in `registry()` or `listingFieldRegistry()`
- **Suggested Q**: Field has a `sample_question` and `sample_question_2` in the listing field registry or FAQ keyword map
- **NL Route**: Field has natural language routing (keyword map or pinned FAQ route)
- **Ask AI Reachable**: Field can actually be returned by Ask AI in a non-null answer

Legend: ✅ Covered | ⚠ Partial / Broken | ❌ Missing | N/A Not Applicable

#### Seller Structural Fields

| Field | Ask AI ID | Stored | Displayed | Context Builder | Registry | Suggested Q | NL Route | Ask AI Reachable |
|---|---|---|---|---|---|---|---|---|
| Address | SEL-001 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Description | SEL-002 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Asking Price | SEL-003 | ✅ | ✅ | ⚠ (wrong accessor) | ✅ | ✅ | ✅ | ❌ |
| Buy Now Price | SEL-004 | ❌ | ✅ | ⚠ (phantom) | ❌ | ❌ | ❌ | ❌ |
| Bedrooms | SEL-005 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Bathrooms | SEL-006 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Square Footage | SEL-007 | ✅ | ✅ | ⚠ (wrong key+accessor) | ✅ | ✅ | ✅ | ❌ |
| Year Built | SEL-008 | ✅ | ✅ | ⚠ (wrong accessor) | ✅ | ✅ | ✅ | ❌ |
| Property Type | SEL-009 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Pool | SEL-010 | ✅ | ✅ | ⚠ (wrong key+accessor) | ✅ | ✅ | ✅ | ❌ |
| Pool Type | SEL-011 | ✅ | ✅ | ⚠ (wrong accessor) | ⚠ | ❌ | ⚠ | ❌ |
| Carport | SEL-012 | ✅ | ✅ | ⚠ (wrong key+accessor) | ✅ | ✅ | ✅ | ❌ |
| Garage | SEL-013 | ✅ | ✅ | ⚠ (wrong key+accessor) | ✅ | ✅ | ✅ | ❌ |
| Garage Spaces | SEL-014 | ✅ | ✅ | ⚠ (wrong key+accessor) | ✅ | ✅ | ✅ | ❌ |
| View / Water View | SEL-015 | ✅ | ✅ | ⚠ (wrong accessor) | ✅ | ✅ | ✅ | ❌ |
| HOA Flag | SEL-016 | ✅ | ✅ | ⚠ (wrong key+accessor) | ✅ | ✅ | ✅ | ❌ |
| HOA Fee | SEL-017 | ✅ | ✅ | ⚠ (wrong key+accessor) | ✅ | ✅ | ✅ | ❌ |
| HOA Fee Frequency | SEL-018 | ✅ | ✅ | ⚠ (wrong key+accessor) | ⚠ | ❌ | ⚠ | ❌ |
| HOA Fee Requirement | SEL-019 | ❌ (phantom) | ❌ | ⚠ (phantom) | ⚠ | ❌ | ⚠ | ❌ |
| Pets Allowed | SEL-021 | ✅ | ✅ | ⚠ (wrong key+accessor) | ✅ | ✅ | ✅ | ❌ |
| Number of Pets | SEL-022 | ✅ | ✅ | ⚠ (wrong key+accessor) | ⚠ | ❌ | ⚠ | ❌ |
| Max Pet Weight | SEL-023 | ✅ | ✅ | ⚠ (wrong key+accessor) | ⚠ | ❌ | ⚠ | ❌ |
| Pet Restrictions | SEL-024 | ✅ | ✅ | ⚠ (wrong accessor) | ⚠ | ❌ | ⚠ | ❌ |
| Rental Restrictions | SEL-025 | ✅ | ✅ | ⚠ (wrong key+accessor) | ✅ | ✅ | ✅ | ❌ |
| Flood Zone Code | SEL-026 | ✅ | ✅ | ⚠ (wrong accessor) | ✅ | ✅ | ✅ | ❌ |
| Target Closing Date | SEL-027 | ✅ | ✅ | ⚠ (wrong key+accessor) | ✅ | ✅ | ✅ | ❌ |
| Auction Length | SEL-028 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Annual Property Taxes | SEL-029 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |

**Seller structural summary: 7 fully reachable / 17 broken by wrong accessor / 3 phantom / 2 no route**

#### Seller FAQ Fields

All 52 Seller FAQ entries are fully connected end-to-end. Per-field breakdown:

| Config Key | Ask AI ID | Stored | Displayed | Context | Registry | Suggested Q | NL Route | Reachable |
|---|---|---|---|---|---|---|---|---|
| `roof_age_and_condition` | SEL-FAQ-001 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `hvac_system_age` | SEL-FAQ-002 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `water_heater_age_type` | SEL-FAQ-003 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `recent_renovations_list` | SEL-FAQ-004 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `permits_for_renovations` | SEL-FAQ-005 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `known_defects_issues` | SEL-FAQ-006 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `foundation_type_and_issues` | SEL-FAQ-007 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `pest_termite_history` | SEL-FAQ-008 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `flood_damage_history` | SEL-FAQ-009 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `mold_issues_history` | SEL-FAQ-010 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `average_utility_costs` | SEL-FAQ-011 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `internet_utility_providers` | SEL-FAQ-012 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `seller_concessions_offered` | SEL-FAQ-013 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `neighborhood_character` | SEL-FAQ-014 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `traffic_or_noise_concerns` | SEL-FAQ-015 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `planned_nearby_development` | SEL-FAQ-016 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `commute_options_access` | SEL-FAQ-017 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `natural_light_orientation` | SEL-FAQ-018 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `nearby_amenities_description` | SEL-FAQ-019 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `neighborhood_restrictions` | SEL-FAQ-020 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `closing_timeline_flexibility` | SEL-FAQ-021 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `seller_leaseback_option` | SEL-FAQ-022 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `items_excluded_from_sale` | SEL-FAQ-023 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `furniture_negotiability` | SEL-FAQ-024 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `as_is_condition` | SEL-FAQ-025 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `environmental_concerns` | SEL-FAQ-026 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `unique_selling_points` | SEL-FAQ-027 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `seller_favorite_features` | SEL-FAQ-028 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `seller_motivation_for_selling` | SEL-FAQ-029 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `move_in_ready_status` | SEL-FAQ-030 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `parking_arrangements` | SEL-FAQ-031 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `storage_space_available` | SEL-FAQ-032 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `hoa_community_highlights` | SEL-FAQ-033 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `annual_net_operating_income` | SEL-CI-001 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `current_cap_rate` | SEL-CI-002 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `existing_tenant_lease_terms` | SEL-CI-003 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `current_occupancy_rate` | SEL-CI-004 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `annual_operating_expenses_detail` | SEL-CI-005 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `value_add_opportunities` | SEL-CI-006 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `annual_business_revenue` | SEL-BIZ-001 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `annual_net_profit` | SEL-BIZ-002 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `business_reason_for_selling` | SEL-BIZ-003 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `business_employee_count` | SEL-BIZ-004 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `seller_training_transition` | SEL-BIZ-005 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `business_lease_status` | SEL-BIZ-006 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `inventory_equipment_included` | SEL-BIZ-007 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `land_utilities_availability` | SEL-VL-001 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `land_zoning_permitted_uses` | SEL-VL-002 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `land_access_and_road` | SEL-VL-003 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `land_soil_and_topography` | SEL-VL-004 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `land_survey_available` | SEL-VL-005 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `land_development_restrictions` | SEL-VL-006 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |

#### Buyer Structural Fields

| Field | Ask AI ID | Stored | Displayed | Context Builder | Registry | Suggested Q | NL Route | Ask AI Reachable |
|---|---|---|---|---|---|---|---|---|
| Address | BUY-001 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Description | BUY-002 | ✅ | ✅ | ⚠ (wrong key) | ✅ | ✅ | ✅ | ❌ |
| Max Budget | BUY-003 | ✅ | ✅ | ⚠ (wrong key+accessor) | ✅ | ✅ | ✅ | ❌ |
| Bedrooms | BUY-004 | ✅ | ✅ | ⚠ (wrong accessor) | ✅ | ✅ | ✅ | ❌ |
| Bathrooms | BUY-005 | ✅ | ✅ | ⚠ (wrong accessor) | ✅ | ✅ | ✅ | ❌ |
| Square Footage | BUY-006 | ✅ | ✅ | ⚠ (wrong key+accessor) | ✅ | ✅ | ✅ | ❌ |
| Pool | BUY-007 | ✅ | ✅ | ⚠ (wrong key+accessor) | ✅ | ✅ | ✅ | ❌ |
| Carport | BUY-008 | ✅ | ✅ | ⚠ (wrong key+accessor) | ✅ | ✅ | ✅ | ❌ |
| Garage | BUY-009 | ✅ | ✅ | ⚠ (wrong key+accessor) | ✅ | ✅ | ✅ | ❌ |
| Garage Spaces | BUY-010 | ✅ | ✅ | ⚠ (wrong key+accessor) | ✅ | ✅ | ✅ | ❌ |
| Water View | BUY-011 | ❌ (phantom) | ❌ | ⚠ (phantom) | ✅ | ✅ | ✅ | ❌ |
| HOA Acceptable | BUY-012 | ✅ | ✅ | ⚠ (wrong key+accessor) | ✅ | ✅ | ✅ | ❌ |
| Max HOA Fee | BUY-013 | ✅ | ✅ | ⚠ (wrong key+accessor) | ⚠ | ❌ | ⚠ | ❌ |
| Pets | BUY-014 | ✅ | ✅ | ⚠ (wrong key+accessor) | ✅ | ✅ | ✅ | ❌ |
| Pet Type | BUY-015 | ✅ | ✅ | ⚠ (wrong key+accessor) | ⚠ | ❌ | ⚠ | ❌ |
| Pet Breed | BUY-016 | ✅ | ✅ | ⚠ (wrong key+accessor) | ⚠ | ❌ | ⚠ | ❌ |
| Pet Weight | BUY-017 | ✅ | ✅ | ⚠ (wrong key+accessor) | ⚠ | ❌ | ⚠ | ❌ |
| Loan Pre-Approved | BUY-018 | ✅ | ✅ | ⚠ (wrong key+accessor) | ✅ | ✅ | ✅ | ❌ |
| Financing Type | BUY-019 | ✅ | ✅ | ⚠ (fundamentally wrong) | ✅ | ✅ | ✅ | ❌ |
| Inspection Period | BUY-020 | ✅ | ✅ | ⚠ (wrong key+accessor) | ✅ | ✅ | ✅ | ❌ |
| Closing Date | BUY-021 | ✅ | ✅ | ⚠ (phantom key read) | ✅ | ✅ | ✅ | ❌ |
| Inspection Contingency | BUY-022 | ✅ | ✅ | ⚠ (phantom aggregate) | ✅ | ✅ | ✅ | ❌ |
| Appraisal Contingency | BUY-023 | ✅ | ✅ | ⚠ (phantom aggregate) | ✅ | ✅ | ✅ | ❌ |
| Financing Contingency | BUY-024 | ✅ | ✅ | ⚠ (phantom aggregate) | ✅ | ✅ | ✅ | ❌ |
| Property Type | BUY-025 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |

**Buyer structural summary: 2 fully reachable / 18 broken / 4 phantom**

#### Buyer FAQ Fields

All 50 Buyer FAQ entries have `match_criteria` route status and are fully connected. Per-field breakdown:

| Config Key | Ask AI ID | Stored | Displayed | Context | Registry | Suggested Q | NL Route | Reachable |
|---|---|---|---|---|---|---|---|---|
| `buyer_motivation` | BUY-FAQ-001 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `buyer_lifestyle_goals` | BUY-FAQ-002 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `buyer_deal_breakers` | BUY-FAQ-003 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `buyer_renovation_tolerance` | BUY-FAQ-004 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `buyer_wfh_needs` | BUY-FAQ-005 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `buyer_outdoor_space` | BUY-FAQ-006 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `buyer_long_term_goals` | BUY-FAQ-007 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `buyer_biggest_concern` | BUY-FAQ-008 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `buyer_neighborhood_preferences` | BUY-FAQ-009 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `buyer_school_district` | BUY-FAQ-010 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `buyer_commute_requirements` | BUY-FAQ-011 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `buyer_noise_tolerance` | BUY-FAQ-012 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `buyer_area_familiarity` | BUY-FAQ-013 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `buyer_prefers_off_market` | BUY-FAQ-014 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `buyer_property_style` | BUY-FAQ-015 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `buyer_must_have_features` | BUY-FAQ-016 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `buyer_nice_to_have` | BUY-FAQ-017 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `buyer_hoa_acceptable` | BUY-FAQ-018 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `buyer_accessibility` | BUY-FAQ-019 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `buyer_privacy_requirements` | BUY-FAQ-020 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `buyer_view_preference` | BUY-FAQ-021 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `buyer_current_situation` | BUY-FAQ-022 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `buyer_simultaneous_close` | BUY-FAQ-023 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `buyer_leaseback` | BUY-FAQ-024 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `buyer_relocation` | BUY-FAQ-025 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `buyer_lost_deal` | BUY-FAQ-026 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `buyer_seller_concessions` | BUY-FAQ-027 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `buyer_flexibility` | BUY-FAQ-028 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `com_property_use` | BUY-COM-001 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `com_investment_type` | BUY-COM-002 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `com_cap_rate_target` | BUY-COM-003 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `com_occupancy_rate` | BUY-COM-004 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `com_lease_terms` | BUY-COM-005 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `com_1031_exchange` | BUY-COM-006 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `com_due_diligence_period` | BUY-COM-007 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `com_environmental_concerns` | BUY-COM-008 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `biz_type_seeking` | BUY-BIZ-001 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `biz_revenue_required` | BUY-BIZ-002 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `biz_profit_required` | BUY-BIZ-003 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `biz_training_expected` | BUY-BIZ-004 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `biz_staff_included` | BUY-BIZ-005 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `biz_non_compete` | BUY-BIZ-006 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `biz_sba_financing` | BUY-BIZ-007 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `land_intended_use` | BUY-LND-001 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `land_zoning_required` | BUY-LND-002 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `land_utilities_needed` | BUY-LND-003 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `land_soil_testing` | BUY-LND-004 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `land_build_timeline` | BUY-LND-005 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `land_access_requirements` | BUY-LND-006 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `land_topography` | BUY-LND-007 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |

#### Landlord Structural Fields

Per-field breakdown (all EAV; details in Section 5.1):

| Field | Ask AI ID | Stored | Displayed | Context | Registry | Suggested Q | NL Route | Reachable |
|---|---|---|---|---|---|---|---|---|
| Property Address | LND-001 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Property Description | LND-002 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Rent Amount | LND-003 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Bedrooms | LND-004 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Bathrooms | LND-005 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Square Footage | LND-006 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Unit Size | LND-007 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Number of Units | LND-008 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Property ZIP | LND-009 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Property Items / Features | LND-010 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Property Condition | LND-011 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Appliances | LND-012 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| View / Water View | LND-013 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Pet Policy | LND-014 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Pet Deposit / Fee | LND-015 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Max Pet Weight | LND-016 | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Pet Species Allowed | LND-017 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Parking Terms | LND-018 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Utilities Included | LND-019 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Laundry Policy | LND-020 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Lease Term | LND-021 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Available Date | LND-022 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Security Deposit | LND-023 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Has HOA | LND-024 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Association Fee Amount | LND-025 | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Association Amenities | LND-026 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Annual Property Taxes | LND-027 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Number of Occupants Allowed | LND-028 | ✅ | ✅ | ⚠ key mismatch | ✅ | ✅ | ✅ | ❌ |
| Property Type | LND-029 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Smoking Policy | LND-030 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Subletting Policy | LND-031 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |

**Landlord structural summary: 28 fully reachable / 1 broken (key mismatch) / 2 missing route**

#### Landlord FAQ Fields

26 documented base entries + 1 additional (verify from `landlordBaseRegistry()` in source). All 12 commercial add-on entries confirmed. Per-field breakdown:

| Config Key | Ask AI ID | Stored | Displayed | Context | Registry | Suggested Q | NL Route | Reachable |
|---|---|---|---|---|---|---|---|---|
| `maintenance_request_response_time` | LND-FAQ-001 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `emergency_maintenance_available` | LND-FAQ-002 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `heating_cooling_system` | LND-FAQ-003 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `laundry_situation` | LND-FAQ-004 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `storage_area_included` | LND-FAQ-005 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `internet_providers` | LND-FAQ-006 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `security_features` | LND-FAQ-007 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `planned_renovations` | LND-FAQ-008 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `noise_levels` | LND-FAQ-009 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `nearby_amenities` | LND-FAQ-010 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `guest_parking` | LND-FAQ-011 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `proximity_to_public_transit` | LND-FAQ-012 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `furnished_or_unfurnished` | LND-FAQ-013 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `lease_renewal_process` | LND-FAQ-014 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `notice_to_vacate_required` | LND-FAQ-015 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `preferred_tenant_qualities` | LND-FAQ-016 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `subletting_allowed` | LND-FAQ-017 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `short_term_rentals_allowed` | LND-FAQ-018 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `ev_charging_available` | LND-FAQ-019 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `bicycle_storage_available` | LND-FAQ-020 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `what_makes_property_unique` | LND-FAQ-021 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `pest_or_mold_history` | LND-FAQ-022 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `utilities_individually_metered` | LND-FAQ-023 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `renters_insurance_required` | LND-FAQ-024 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `lease_to_own_option` | LND-FAQ-025 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `previous_tenant_feedback` | LND-FAQ-026 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `smoking_policy` | LND-FAQ-027 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `commercial_cam_charges` | LND-COM-001 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `commercial_lease_structure_type` | LND-COM-002 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `commercial_tenant_improvement_allowance` | LND-COM-003 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `commercial_buildout_flexibility` | LND-COM-004 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `commercial_signage_rights` | LND-COM-005 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `commercial_loading_dock_freight_elevator` | LND-COM-006 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `commercial_electrical_capacity` | LND-COM-007 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `commercial_parking_ratio` | LND-COM-008 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `commercial_exclusivity_rights` | LND-COM-009 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `commercial_expansion_option_rofr` | LND-COM-010 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `commercial_landlord_maintenance_responsibilities` | LND-COM-011 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `commercial_building_access_hours` | LND-COM-012 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |

#### Tenant Structural Fields

Per-field breakdown (all EAV; details in Section 6.1). Note: TEN-024 is RESTRICTED — not Ask AI reachable (Section 8).

| Field | Ask AI ID | Stored | Displayed | Context | Registry | Suggested Q | NL Route | Reachable |
|---|---|---|---|---|---|---|---|---|
| Desired Location / Area | TEN-001 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Additional Requirements | TEN-002 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Maximum Rent Budget | TEN-003 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Bedrooms | TEN-004 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Bathrooms | TEN-005 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Desired Lease Length | TEN-006 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Desired Lease Length (scalar) | TEN-007 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Pets | TEN-008 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Pet Species | TEN-009 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Parking Needed | TEN-010 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Move-In Date | TEN-011 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Max HOA Fee Acceptable | TEN-012 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Property Items Desired | TEN-013 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Appliances Needed | TEN-014 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Property Condition Acceptable | TEN-015 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Pet Information | TEN-016 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Utility Preference | TEN-017 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Utilities (desired) | TEN-018 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Tenant Pays | TEN-019 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Current Status | TEN-020 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Number of Occupants | TEN-021 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Number of Units | TEN-022 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Property Type | TEN-023 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Credit Score Range | TEN-024 | ✅ | ⚠ restricted | ❌ restricted | N/A | N/A | N/A | ❌ |

**Tenant structural summary: 23 fully reachable / 1 RESTRICTED (TEN-024)**

#### Tenant FAQ Fields

All 27 Tenant FAQ entries are `pinned` route status. Note: opaque keys (`faq_q1`–`faq_q27`) limit key-based matching (see Section 6.2 and 14.4).

| Config Key | Ask AI ID | Stored | Displayed | Context | Registry | Suggested Q | NL Route | Reachable |
|---|---|---|---|---|---|---|---|---|
| `faq_q1` | TEN-FAQ-001 | ✅ | ✅ | ✅ | ✅ | ⚠ opaque | ✅ | ✅ |
| `faq_q2` | TEN-FAQ-002 | ✅ | ✅ | ✅ | ✅ | ⚠ opaque | ✅ | ✅ |
| `faq_q3` | TEN-FAQ-003 | ✅ | ✅ | ✅ | ✅ | ⚠ opaque | ✅ | ✅ |
| `faq_q4` | TEN-FAQ-004 | ✅ | ✅ | ✅ | ✅ | ⚠ opaque | ✅ | ✅ |
| `faq_q5` | TEN-FAQ-005 | ✅ | ✅ | ✅ | ✅ | ⚠ opaque | ✅ | ✅ |
| `faq_q6` | TEN-FAQ-006 | ✅ | ✅ | ✅ | ✅ | ⚠ opaque | ✅ | ✅ |
| `faq_q7` | TEN-FAQ-007 | ✅ | ✅ | ✅ | ✅ | ⚠ opaque | ✅ | ✅ |
| `faq_q8` | TEN-FAQ-008 | ✅ | ✅ | ✅ | ✅ | ⚠ opaque | ✅ | ✅ |
| `faq_q9` | TEN-FAQ-009 | ✅ | ✅ | ✅ | ✅ | ⚠ opaque | ✅ | ✅ |
| `faq_q10` | TEN-FAQ-010 | ✅ | ✅ | ✅ | ✅ | ⚠ opaque | ✅ | ✅ |
| `faq_q11` | TEN-FAQ-011 | ✅ | ✅ | ✅ | ✅ | ⚠ opaque | ✅ | ✅ |
| `faq_q12` | TEN-FAQ-012 | ✅ | ✅ | ✅ | ✅ | ⚠ opaque | ✅ | ✅ |
| `faq_q13` | TEN-FAQ-013 | ✅ | ✅ | ✅ | ✅ | ⚠ opaque | ✅ | ✅ |
| `faq_q14` | TEN-FAQ-014 | ✅ | ✅ | ✅ | ✅ | ⚠ opaque | ✅ | ✅ |
| `faq_q15` | TEN-FAQ-015 | ✅ | ✅ | ✅ | ✅ | ⚠ opaque | ✅ | ✅ |
| `faq_q16` | TEN-FAQ-016 | ✅ | ✅ | ✅ | ✅ | ⚠ opaque | ✅ | ✅ |
| `faq_q17` | TEN-FAQ-017 | ✅ | ✅ | ✅ | ✅ | ⚠ opaque | ✅ | ✅ |
| `faq_q18` | TEN-FAQ-018 | ✅ | ✅ | ✅ | ✅ | ⚠ opaque | ✅ | ✅ |
| `faq_q19` | TEN-FAQ-019 | ✅ | ✅ | ✅ | ✅ | ⚠ opaque | ✅ | ✅ |
| `faq_q20` | TEN-FAQ-020 | ✅ | ✅ | ✅ | ✅ | ⚠ opaque | ✅ | ✅ |
| `faq_q21` | TEN-COM-001 | ✅ | ✅ | ✅ | ✅ | ⚠ opaque | ✅ | ✅ |
| `faq_q22` | TEN-COM-002 | ✅ | ✅ | ✅ | ✅ | ⚠ opaque | ✅ | ✅ |
| `faq_q23` | TEN-COM-003 | ✅ | ✅ | ✅ | ✅ | ⚠ opaque | ✅ | ✅ |
| `faq_q24` | TEN-COM-004 | ✅ | ✅ | ✅ | ✅ | ⚠ opaque | ✅ | ✅ |
| `faq_q25` | TEN-COM-005 | ✅ | ✅ | ✅ | ✅ | ⚠ opaque | ✅ | ✅ |
| `faq_q26` | TEN-COM-006 | ✅ | ✅ | ✅ | ✅ | ⚠ opaque | ✅ | ✅ |
| `faq_q27` | TEN-COM-007 | ✅ | ✅ | ✅ | ✅ | ⚠ opaque | ✅ | ✅ |

### 14.2 Coverage Summary

| Role | Total Fields Audited | Fully Ask-AI-Reachable | Broken (accessor / phantom) | Missing Route | No Coverage |
|---|---|---|---|---|---|
| Seller (structural) | 29 | 7 (24%) | 22 (76%) | 0 | 0 |
| Seller (FAQ) | 52 | 52 (100%) | 0 | 0 | 0 |
| Buyer (structural) | 25 | 2 (8%) | 22 (88%) | 1 | 0 |
| Buyer (FAQ) | 50 | 50 (100%) | 0 | 0 | 0 |
| Landlord (structural) | 31 | 28 (90%) | 1 (3%) | 2 (6%) | 0 |
| Landlord (FAQ) | 39 | 39 (100%) | 0 | 0 | 0 |
| Tenant (structural) | 24 | 23 (96%) | 0 | 0 | 1 (TEN-024 RESTRICTED by policy) |
| Tenant (FAQ) | 27 | 27 (100%) | 0 | 0 | 0 |
| **Overall** | **277** | **228 (82%)** | **45 (16%)** | **3 (1%)** | **1 (<1%)** |

**Without Landlord and Tenant (which are working):** 156 total fields for Seller+Buyer — 111 fully reachable, 44 broken (accessor/phantom), 1 missing route.

### 14.3 Priority Fix Ranking

| Priority | Fix | Impact | Effort |
|---|---|---|---|
| P1 | Fix all 17 Seller `nativeGet → infoGet` accessor errors | Unblocks all Seller structural facts | Low (mechanical substitution) |
| P2 | Fix all 17 Buyer `nativeGet → infoGet` accessor errors | Unblocks all Buyer structural facts | Low |
| P3 | Remove 10 Seller + 4 Buyer phantom keys | Removes misleading `insufficient_context` | Low |
| P4 | Fix Landlord `number_of_occupants_allowed → number_occupant` key | 1 Landlord field | Trivial |
| P5 | Add `pet_max_weight_lbs` + `association_fee_amount` to Landlord listing registry and LISTING_KEY_KEYWORD_MAP | 2 Landlord fields gain Ask AI routes | Low |
| P6 | Rename Tenant `faq_q1–faq_q27` to descriptive config keys | Makes opaque FAQ keys inspectable | Medium (config + migration) |
| P7 | Implement KnowledgeSnapshotBuilderService | Database-first architecture | High |
| P8 | Implement ask_ai_answer_records pre-generation | Eliminates most OpenAI calls | High |

### 14.4 Opaque Key Tenant Entries Requiring Natural Questions

All 27 Tenant FAQ entries (`faq_q1`–`faq_q27`) need `natural_questions` arrays added to the registry to enable full database-first answer matching. The suggested descriptive keys are listed in Section 6.2. Until added, these entries can only be reached via their current pinned keyword routes — they cannot be matched to an answer record by config_key inspection alone.

---

## 15. Appendix: Canonical Ask AI Field Identifier Reference

### 15.1 Seller Field IDs

`SEL-001` through `SEL-031` (structural) + `SEL-FAQ-001` through `SEL-FAQ-033` (base FAQ) + `SEL-CI-001` through `SEL-CI-006` (commercial income) + `SEL-BIZ-001` through `SEL-BIZ-007` (business) + `SEL-VL-001` through `SEL-VL-006` (vacant land)  
**Total: 83 Seller field IDs**

### 15.2 Buyer Field IDs

`BUY-001` through `BUY-025` (structural) + `BUY-FAQ-001` through `BUY-FAQ-028` (base FAQ) + add-on FAQs: `BUY-COM-001` through `BUY-COM-008` (commercial), `BUY-BIZ-001` through `BUY-BIZ-007` (business), `BUY-LND-001` through `BUY-LND-007` (land)  
**Total: 75 Buyer field IDs**

### 15.3 Landlord Field IDs

`LND-001` through `LND-031` (structural) + `LND-FAQ-001` through `LND-FAQ-027` (base FAQ) + `LND-COM-001` through `LND-COM-012` (commercial FAQ)  
**Total: 70 Landlord field IDs**

### 15.4 Tenant Field IDs

`TEN-001` through `TEN-024` (structural) + `TEN-FAQ-001` through `TEN-FAQ-020` (base FAQ opaque) + `TEN-COM-001` through `TEN-COM-007` (commercial FAQ opaque)  
**Total: 51 Tenant field IDs**

### 15.5 Grand Total

| Role | Structural | FAQ Base | FAQ Addons | Total |
|---|---|---|---|---|
| Seller | 31 | 33 | 19 | **83** |
| Buyer | 25 | 28 | 22 | **75** |
| Landlord | 31 | 27 | 12 | **70** |
| Tenant | 24 | 20 | 7 | **51** |
| **Total** | **111** | **108** | **60** | **279** |

### 15.6 Quick Reference: Context Key → Meta Key → Registry Path

| Context Key | Meta Key(s) | listing.* Path | faq_answers.* Path |
|---|---|---|---|
| `asking_price` | `maximum_budget` | `listing.asking_price` | — |
| `max_price` | `maximum_budget` | `listing.max_price` | — |
| `rent` | `maximum_budget` (landlord) | — | — |
| `max_rent` | `budget`→`maximum_budget` | `listing.max_rent` | — |
| `bedrooms` | `bedrooms` | `listing.bedrooms` | — |
| `bathrooms` | `bathrooms` | `listing.bathrooms` | — |
| `square_feet` | `minimum_heated_square` | `listing.square_feet` | — |
| `year_built` | `year_built` | `listing.year_built` | — |
| `pool` | `pool_needed` | `listing.pool` | — |
| `water_view` | `view_preference` (JSON) | `listing.water_view` | — |
| `hoa_association` | `has_hoa` | `listing.hoa_association` | — |
| `hoa_fee` | `association_fee_amount` | `listing.hoa_fee` | — |
| `pets_allowed` | `pets` | `listing.pets_allowed` | — |
| `rental_restrictions` | `leasing_restrictions` | `listing.rental_restrictions` | — |
| `flood_zone_code` | `flood_zone_code` | `listing.flood_zone_code` | — |
| `closing_date` | `target_closing_date` | `listing.closing_date` | — |
| `financing_type` | `offered_financing` (JSON) | `listing.financing_type` | — |
| `roof_age_and_condition` | `ai_faq_answers` (faq) | — | `faq_answers.roof_age_and_condition` |
| `hvac_system_age` | `ai_faq_answers` (faq) | — | `faq_answers.hvac_system_age` |
| `laundry_situation` | `ai_faq_answers` (faq) | — | `faq_answers.laundry_situation` |
| `tenant_faq_q1` | `ai_faq_answers` (faq) | — | `faq_answers.faq_q1` |

---

*Audit prepared 2026-06-10. Gaps resolved and sections 16–18 added 2026-06-11. No code was modified. This document is the prerequisite for implementing the database-first Ask AI architecture.*

---

## 16. Implementation Roadmap

This roadmap translates the audit findings into a sequenced delivery plan. Each phase has a clear gate: the next phase must not begin until the current phase's acceptance criteria are met.

### 16.1 Phase 1 — Fix Broken Lineage (Seller + Buyer Accessors)

**Goal:** Make every DATABASE-FIRST field that is already stored and registered actually reachable by Ask AI without any OpenAI call.

**Trigger:** All 17 Seller and 17 Buyer structural fields currently return `insufficient_context` despite having valid stored data.

**Work items:**
1. In `AskAiContextBuilderService::extractFactualFields()`, replace every `nativeGet()` call for EAV-stored fields with `infoGet()`. This is a mechanical substitution — no logic changes.
2. Fix the 10 Seller + 4 Buyer phantom context keys that reference non-existent native columns (e.g., `buy_now_price`, `water_view` on Buyer, `hoa_fee_required`). Either remove these reads or map them to their correct EAV keys.
3. Fix the `offered_financing` key extraction to decode the stored JSON array rather than passing it raw.
4. Fix the `maximum_budget` accessor path for Seller (currently uses wrong native column).
5. Fix the `minimum_heated_square` key mismatch (context builder reads a different key than what is stored).

**Acceptance criteria:** For every SEL-001 through SEL-031 and BUY-001 through BUY-025 field with data stored, `extractFactualFields()` returns a non-null value. Verified by unit tests that seed real meta values and assert the context array.

**Estimated effort:** 2–3 days (one developer). Entirely mechanical — no product decisions required.

---

### 16.2 Phase 2 — Fix Landlord Gaps and Tenant Opaque Keys

**Goal:** Achieve 100% structural field reachability across all four roles.

**Work items:**
1. Fix the `LND-028` key mismatch: the context builder reads `number_of_occupants_allowed` but the field is stored under the key `number_occupant`. Fix = change the context builder to call `infoGet('number_occupant')` instead of `infoGet('number_of_occupants_allowed')`.
2. Add `pet_max_weight_lbs` and `association_fee_amount` to `LISTING_KEY_KEYWORD_MAP` and `listingFieldRegistry()` so LND-016 and LND-025 gain Ask AI routes and suggested questions.
3. Rename the 27 Tenant opaque FAQ config keys (`faq_q1` → `faq_q27`) to their descriptive equivalents (see Section 6.2 for the full suggested-name list). This requires a coordinated update to the Livewire component's `saveAiFaq()` call, the registry entries, and any existing stored FAQ answers (migration or dual-read).
4. Add `natural_questions` arrays to the 27 renamed Tenant FAQ registry entries so they can be matched in future database-first lookups.

**Acceptance criteria:** `LND-028`, `LND-016`, `LND-025` each return non-null in context builder tests. Tenant FAQ config key rename verified by asserting that existing stored `faq_q*` answers are still readable under the new keys (dual-read migration or a one-time rename migration).

**Estimated effort:** 3–5 days. The opaque key rename is the most risk-bearing item (requires migration and dual-read).

---

### 16.3 Phase 3 — Knowledge Snapshot Tables

**Goal:** Introduce a pre-computed knowledge layer so Ask AI can serve answers from DB rows instead of building a fresh context array on every user query.

**Proposed schema:**
```sql
CREATE TABLE ask_ai_listing_snapshots (
    id              BIGSERIAL PRIMARY KEY,
    listing_type    VARCHAR(20) NOT NULL,  -- seller|buyer|landlord|tenant
    listing_id      BIGINT NOT NULL,
    context_key     VARCHAR(100) NOT NULL,
    display_label   VARCHAR(200),
    raw_value       TEXT,
    formatted_value TEXT,
    value_type      VARCHAR(20),           -- string|number|boolean|list|date
    source          VARCHAR(20),           -- database|faq|ai_generated
    is_stale        BOOLEAN DEFAULT FALSE,
    last_built_at   TIMESTAMPTZ,
    created_at      TIMESTAMPTZ DEFAULT NOW(),
    updated_at      TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE (listing_type, listing_id, context_key)
);

CREATE TABLE ask_ai_question_index (
    id              BIGSERIAL PRIMARY KEY,
    listing_type    VARCHAR(20) NOT NULL,
    context_key     VARCHAR(100) NOT NULL,
    question_text   TEXT NOT NULL,
    question_variant SMALLINT DEFAULT 1,   -- 1=primary, 2=alt1, 3=alt2
    category        VARCHAR(50),
    UNIQUE (listing_type, context_key, question_variant)
);
```

**KnowledgeSnapshotBuilderService responsibilities:**
- Triggered on listing save, bid acceptance, and FAQ answer submission
- Iterates all registered `context_key` entries for the listing's type
- Calls `infoGet()` / `nativeGet()` for each key and writes the result to `ask_ai_listing_snapshots`
- Marks rows as `is_stale = TRUE` when a meta save touches a related key (invalidation hook)
- Does **not** call OpenAI — only stores what the database already holds

**Acceptance criteria:** After a listing is saved with all structural fields populated, all DATABASE-FIRST context keys for that listing appear as non-stale rows in `ask_ai_listing_snapshots`. Verified by a feature test that creates a listing and then queries the snapshot table.

**Estimated effort:** 5–7 days.

---

### 16.4 Phase 4 — Database-First Ask AI Runner

**Goal:** Modify `RunnerV2` to check the snapshot table before calling OpenAI. For any question whose `context_key` matches a non-stale snapshot row, return the stored `formatted_value` immediately.

**Architecture change:**

```
User question → Normalizer → Router → [NEW] SnapshotLookupAdapter
                                           ↓ (hit)  ↓ (miss / stale / ai_required)
                                       DB answer   OpenAI call (existing path)
```

**SnapshotLookupAdapter responsibilities:**
1. Receive the `context_key` resolved by the router.
2. Query `ask_ai_listing_snapshots` for `(listing_type, listing_id, context_key)` where `is_stale = FALSE`.
3. If found and `raw_value` is not null → return formatted answer string immediately, mark `source: database`.
4. If not found or stale → fall through to the existing OpenAI adapter.
5. For FAQ fields (`source: faq`) → return stored FAQ answer if non-null, otherwise fall through.

**New trace field:** Add `answer_source: database|faq|openai` to the runner's result trace for observability.

**Acceptance criteria:** A listing with all structural fields populated serves 100% of structural-field questions without touching the OpenAI API. Verified by a test that injects a snapshot row and asserts `answer_source === 'database'` in the runner result.

**Estimated effort:** 4–6 days.

---

### 16.5 Phase 5 — OpenAI as Fallback Only + Suggested Question Generation

**Goal:** Confine OpenAI calls to genuinely open-ended questions that cannot be answered from stored data. Add automated suggested question seeding.

**Work items:**
1. Audit all remaining OpenAI call paths and confirm each has a valid `context_key` guard — if no key is resolvable, the answer should be `insufficient_context` rather than a hallucinated response.
2. Populate the `ask_ai_question_index` table from the existing `AskAiFieldQuestionRegistryService` entries (sample_question, sample_question_2 → variants 1 and 2) plus the new Alt Q3 phrasings defined in Section 18 of this document.
3. Expose a `GET /api/ask-ai/suggested-questions/{listingType}/{listingId}` endpoint that returns the question index filtered to context keys that have non-stale snapshot rows (i.e., the listing actually has data for that field). This enables the UI to surface "Questions you can ask about this listing."
4. Implement answer pre-generation as a queued job: for each snapshot row where `source = database`, pre-compose the `formatted_value` using the answer template from Section 18. Store the result so zero AI calls are needed for standard structural questions.

**Acceptance criteria:** OpenAI is only invoked for questions that (a) map to a `source: ai_required` context key and (b) the FAQ answer is null or stale. All structural fields and all filled FAQ answers are served from DB with `answer_source: database` or `answer_source: faq`.

**Estimated effort:** 1–2 weeks.

---

### 16.6 Roadmap Summary

| Phase | Name | Scope | Unblocks | Effort |
|---|---|---|---|---|
| 1 | Fix Broken Lineage | Seller + Buyer accessor fixes | Phase 3 snapshot accuracy | 2–3 days |
| 2 | Landlord Gaps + Tenant Keys | LND-016/025/028 + opaque rename | Phase 3 completeness | 3–5 days |
| 3 | Knowledge Snapshot Tables | New schema + SnapshotBuilderService | Phase 4 DB-first runner | 5–7 days |
| 4 | DB-First Runner | SnapshotLookupAdapter in RunnerV2 | Phase 5 OpenAI reduction | 4–6 days |
| 5 | OpenAI Fallback + SuggestedQs | Question index + pre-gen + endpoint | Full DB-first architecture | 1–2 weeks |

**Total estimated effort: 6–8 weeks for full delivery (one developer). Phases 1 and 2 can be parallelized. Phase 3 can begin concurrently with Phase 2.**

---

## 17. Complete Form Field Universe

This section catalogs **every** `saveMeta()` key found across all four Create Offer Listing Livewire components. Fields are organized by role and category. Fields already covered in the main audit sections (Sections 3–6) are referenced by their Ask AI ID; all others are inventoried here for the first time.

### 17.0 Field Metadata Schema

Each field in Sections 17.1–17.4 carries the following metadata:

| Metadata Column | Source / How to Derive |
|---|---|
| **Listing Type** | Role heading of the sub-section (Seller / Buyer / Landlord / Tenant) |
| **Property Type(s)** | Sub-table heading or Notes column (e.g. "commercial only", "all types") |
| **Tab** | Form wizard tab name. Each row's Tab value is shown in the `Tab` column of every field table in Sections 17.1–17.4. Tab names were sourced from validation-block comments in each Livewire `.php` file (`SellerOfferListing.php`, `BuyerOfferListing.php`, `LandlordOfferListing.php`, `TenantOfferListing.php`). |
| **Section** | The `####` subsection heading under which the field appears (e.g. *Physical Property Features*). Each field table header carries a `Tab` column; the `####` heading is the within-tab Section name. |
| **Display Label** | `Display Label` column |
| **Input Name / Meta Key** | `Meta Key` column. For all EAV-stored fields, Input Name = Meta Key. For native columns, the DB column name is noted in the Notes column. |
| **DB Column / Meta Key** | `Meta Key` column (EAV key) or Notes (native column name) |
| **Field Type** | `Type` column |
| **Publicly Visible** | Derived from Classification (see mapping table below) |
| **Ask AI Eligible** | Derived from Classification (see mapping table below) |
| **Classification** | `Classification` column |
| **Notes** | `Notes` column |

**Classification → Eligibility Mapping:**

| Classification | Ask AI Eligible | Publicly Visible |
|---|---|---|
| `DATABASE-FIRST` | **Y** | **Y** |
| `AI-OPTIONAL` | **Y** | **Y** |
| `AI-REQUIRED` | **Y** | **Y** |
| `RESTRICTED` | **N** | **N** |
| `INTERNAL` | **N** | **N** |
| `PII` | **N** | **N** |
| `MEDIA` | **N** | **N** |
| `LOCATION-HELPER` | **N** | Y (search UI only) |
| `CRYPTO-NICHE` | **N** | **N** |

**Form Tab Reference (sourced from validation-block comments in each Livewire `.php` file):**

> **Important:** The column labels below (Tab 1, Tab 2, …) are the logical group indices used inside the Livewire PHP validation blocks and are referenced by the Tab column throughout Sections 17.1–17.4. They do **not** always equal the visual nav position rendered in the browser — the Seller form conditionally inserts or removes the "Financial Details" tab depending on property type (Income/Commercial/Business only), so the visual order may shift. Tab names are authoritative; ordinal indices are reference-only.

| Logical Tab Index | Seller | Buyer | Landlord | Tenant |
|---|---|---|---|---|
| Tab 1 | Listing Details | Listing Details | Listing Details | Listing Details |
| Tab 2 | Property Details | Property Preferences | Property Details | Property Preferences |
| Tab 3 | Sale Terms | Purchasing Terms | Leasing Terms | Leasing Terms |
| Tab 4 | Financial Details *(Income/Comm./Biz only)* | *(n/a)* | Tax, Legal, HOA & Disclosures | Pre-Screening |
| Tab 5 | Tax, Legal, HOA & Disclosures | *(n/a)* | Photos, Tours & Documents | *(n/a)* |
| Tab 6 | Photos, Tours & Documents | *(n/a)* | *(n/a)* | *(n/a)* |
| Tab 7 | Seller Information | Buyer Information | Landlord Information | Tenant Information |
| Tab 8 | FAQs | FAQs | FAQs | FAQs |

> Source: `TenantOfferListing.php` lines 3930–4090 (validation block for all four roles); `SellerOfferListing.php` lines 270–292 (Financial Details tab comments), lines 597–664 (Tax/Legal/HOA & Disclosures tab comments). Field-level Tab names used throughout Sections 17.1–17.4 use the Name column above (e.g. "Property Details", "Sale Terms") — not ordinal numbers.

---

**Classification key:**
- `DATABASE-FIRST` — Stored structured value; can be served directly by Ask AI with no AI call. Eligible for snapshot table.
- `AI-OPTIONAL` — Stored value that is Ask AI eligible but not currently tracked in the context builder. Candidate for Phase 5 coverage expansion.
- `AI-REQUIRED` — Free text or multi-sentence answer; must be formatted by AI or FAQ answer system.
- `RESTRICTED` — Agent compensation, fee structures, commission terms. Never exposed via Ask AI.
- `INTERNAL` — System/versioning/workflow/admin fields. Never exposed via Ask AI.
- `PII` — Personally Identifiable Information. Absolutely never exposed.
- `MEDIA` — Photo/video assets. Not applicable for text-based Ask AI.
- `LOCATION-HELPER` — Geocoding and search-filter fields. Infrastructure only; not direct Ask AI facts.
- `CRYPTO-NICHE` — Cryptocurrency/NFT payment sub-fields. Excluded from standard Ask AI paths.

---

### 17.1 Seller Form — Additional Field Inventory

Fields from `SellerOfferListing.php` not covered in Sections 3.1–3.6.

#### Physical Property Features

| Meta Key | Display Label | Tab | Type | Classification | Notes |
|---|---|---|---|---|---|
| `condition_prop` | Property Condition | Property Details | select | DATABASE-FIRST | → SEL-FAQ covers this via FAQ route |
| `other_property_condition` | Condition (Other) | Property Details | text | AI-OPTIONAL | Companion to `condition_prop` |
| `roof_type` | Roof Type | Property Details | JSON multi-select | AI-OPTIONAL | **New gap** — stored but not in context builder; candidate for Phase 5 |
| `other_roof_type` | Roof Type (Other) | Property Details | text | AI-OPTIONAL | Companion |
| `exterior_construction` | Exterior Construction | Property Details | JSON multi-select | AI-OPTIONAL | **New gap** |
| `other_exterior_construction` | Exterior (Other) | Property Details | text | AI-OPTIONAL | Companion |
| `foundation` | Foundation | Property Details | JSON multi-select | AI-OPTIONAL | **New gap** |
| `other_foundation` | Foundation (Other) | Property Details | text | AI-OPTIONAL | Companion |
| `interior_features` | Interior Features | Property Details | JSON multi-select | AI-OPTIONAL | **New gap** — items like granite counters, crown molding |
| `other_interior_features` | Interior Features (Other) | Property Details | text | AI-OPTIONAL | Companion |
| `building_features` | Building Features | Property Details | JSON multi-select | AI-OPTIONAL | **New gap** — elevator, gym, doorman etc. |
| `other_building_features` | Building Features (Other) | Property Details | text | AI-OPTIONAL | Companion |
| `air_conditioning` | Air Conditioning | Property Details | select | AI-OPTIONAL | **New gap** |
| `other_air_conditioning` | A/C (Other) | Property Details | text | AI-OPTIONAL | Companion |
| `heating_and_fuel` | Heating & Fuel | Property Details | select | AI-OPTIONAL | **New gap** |
| `other_heating_and_fuel` | Heating (Other) | Property Details | text | AI-OPTIONAL | Companion |
| `appliances` | Appliances Included | Property Details | JSON multi-select | AI-OPTIONAL | **New gap** |
| `other_appliances` | Appliances (Other) | Property Details | text | AI-OPTIONAL | Companion |
| `total_square_feet` | Total Square Footage | Property Details | numeric | AI-OPTIONAL | Distinct from `minimum_heated_square`; total incl. unheated |
| `ceiling_height` | Ceiling Height | Property Details | text | AI-OPTIONAL | Primarily commercial property |
| `sqft_heated_source` | Heated Sqft Source | Property Details | select | INTERNAL | Metadata about sqft measurement origin |
| `waterfront` | Waterfront | Property Details | boolean | AI-OPTIONAL | **New gap** — distinct from `water_view` |
| `water_access` | Water Access Type | Property Details | JSON multi-select | AI-OPTIONAL | **New gap** |
| `other_water_access` | Water Access (Other) | Property Details | text | AI-OPTIONAL | Companion |
| `water_view` | Water View Types | Property Details | JSON multi-select | DATABASE-FIRST | → SEL-015 (broken accessor, not missing field) |
| `other_water_view` | Water View (Other) | Property Details | text | AI-OPTIONAL | Companion |
| `fences` | Fences | Property Details | JSON multi-select | AI-OPTIONAL | **New gap** |
| `other_fences` | Fences (Other) | Property Details | text | AI-OPTIONAL | Companion |
| `property_items` | Items Included in Sale | Property Details | JSON multi-select | AI-OPTIONAL | **New gap** |
| `other_property_items` | Property Items (Other) | Property Details | text | AI-OPTIONAL | Companion |
| `sale_includes` | What's Included in Sale | Property Details | JSON multi-select | AI-OPTIONAL | **New gap** |
| `other_sale_includes` | Sale Includes (Other) | Property Details | text | AI-OPTIONAL | Companion |
| `included_personal_property` | Personal Property Included | Property Details | text | AI-OPTIONAL | Free text description |
| `excluded_items` | Excluded Items | Property Details | text | AI-OPTIONAL | **New gap** — what seller is keeping |

#### Land & Parcel Details

| Meta Key | Display Label | Tab | Type | Classification | Notes |
|---|---|---|---|---|---|
| `lot_dimensions` | Lot Dimensions | Property Details | text | AI-OPTIONAL | **New gap** — e.g. "100x150 ft" |
| `min_acreage` | Minimum Acreage | Property Details | numeric | AI-OPTIONAL | For land/acreage properties |
| `total_acreage` | Total Acreage | Property Details | numeric | AI-OPTIONAL | **New gap** |
| `zoning` | Zoning Classification | Property Details | text | AI-OPTIONAL | **New gap** |
| `current_use` | Current Use of Land | Property Details | select/text | AI-OPTIONAL | Vacant land only |
| `current_adjacent_use` | Adjacent Land Use | Property Details | select/text | AI-OPTIONAL | Vacant land only |
| `other_current_use` | Current Use (Other) | Property Details | text | AI-OPTIONAL | Companion |
| `other_current_adjacent_use` | Adjacent Use (Other) | Property Details | text | AI-OPTIONAL | Companion |
| `buildable` | Is Lot Buildable | Property Details | boolean/select | AI-OPTIONAL | **New gap** |
| `road_frontage` | Road Frontage | Property Details | JSON multi-select | AI-OPTIONAL | **New gap** |
| `other_road_frontage` | Road Frontage (Other) | Property Details | text | AI-OPTIONAL | Companion |
| `road_surface_type` | Road Surface Type | Property Details | JSON multi-select | AI-OPTIONAL | **New gap** |
| `other_road_surface_type` | Road Surface (Other) | Property Details | text | AI-OPTIONAL | Companion |
| `vegetation` | Vegetation Type | Property Details | JSON multi-select | AI-OPTIONAL | Vacant land |
| `other_vegetation` | Vegetation (Other) | Property Details | text | AI-OPTIONAL | Companion |
| `easements` | Easements | Property Details | JSON multi-select | AI-OPTIONAL | **New gap** |
| `other_easements` | Easements (Other) | Property Details | text | AI-OPTIONAL | Companion |
| `front_footage` | Front Footage | Property Details | numeric | AI-OPTIONAL | Linear street frontage |
| `parcel_id` | Parcel ID / APN | Tax, Legal, HOA & Disclosures | text | DATABASE-FIRST | Disclosed to authenticated parties only; not public Ask AI |
| `legal_description` | Legal Description | Tax, Legal, HOA & Disclosures | text | DATABASE-FIRST | Restricted to authenticated parties |
| `additional_parcel_ids` | Additional Parcel IDs | Tax, Legal, HOA & Disclosures | text | INTERNAL | Multi-parcel details |
| `additional_parcels` | Additional Parcels | Tax, Legal, HOA & Disclosures | boolean | INTERNAL | Flag for multi-parcel |
| `total_parcel_count` | Total Parcel Count | Tax, Legal, HOA & Disclosures | numeric | AI-OPTIONAL | Multi-parcel listings |

#### Utilities (Vacant Land / Commercial)

| Meta Key | Display Label | Tab | Type | Classification | Notes |
|---|---|---|---|---|---|
| `water_available` | Water Available | Property Details | select | AI-OPTIONAL | **New gap** — public/well/none |
| `water_available_other` | Water (Other) | Property Details | text | AI-OPTIONAL | Companion |
| `water` | Water Source | Property Details | select | AI-OPTIONAL | Residential; distinct from `water_available` |
| `other_water` | Water Source (Other) | Property Details | text | AI-OPTIONAL | Companion |
| `sewer` | Sewer Type | Property Details | select | AI-OPTIONAL | **New gap** |
| `sewer_available` | Sewer Available | Property Details | select | AI-OPTIONAL | Vacant land variant |
| `sewer_available_other` | Sewer (Other) | Property Details | text | AI-OPTIONAL | Companion |
| `other_sewer` | Sewer (Other) | Property Details | text | AI-OPTIONAL | Companion |
| `electric_available` | Electric Available | Property Details | select | AI-OPTIONAL | **New gap** |
| `electric_available_other` | Electric (Other) | Property Details | text | AI-OPTIONAL | Companion |
| `electrical_service` | Electrical Service | Property Details | select | AI-OPTIONAL | Residential/commercial |
| `other_electrical_service` | Electrical (Other) | Property Details | text | AI-OPTIONAL | Companion |
| `gas_available` | Gas Available | Property Details | select | AI-OPTIONAL | **New gap** |
| `gas_available_other` | Gas (Other) | Property Details | text | AI-OPTIONAL | Companion |
| `telecom_available` | Telecom/Internet Available | Property Details | select | AI-OPTIONAL | **New gap** |
| `telecom_available_other` | Telecom (Other) | Property Details | text | AI-OPTIONAL | Companion |
| `utilities` | Utilities | Property Details | JSON multi-select | AI-OPTIONAL | General utility flags |
| `other_utilities` | Utilities (Other) | Property Details | text | AI-OPTIONAL | Companion |
| `number_electric_meters` | Number of Electric Meters | Property Details | numeric | AI-OPTIONAL | Commercial/multi-unit |
| `number_water_meters` | Number of Water Meters | Property Details | numeric | AI-OPTIONAL | Commercial/multi-unit |
| `number_of_septics` | Number of Septic Systems | Property Details | numeric | AI-OPTIONAL | |
| `number_of_wells` | Number of Wells | Property Details | numeric | AI-OPTIONAL | |

#### HOA / Association Details (Extended)

| Meta Key | Display Label | Tab | Type | Classification | Notes |
|---|---|---|---|---|---|
| `has_hoa` | HOA Exists | Tax, Legal, HOA & Disclosures | boolean | DATABASE-FIRST | → SEL-016 (broken accessor) |
| `association_name` | Association Name | Tax, Legal, HOA & Disclosures | text | AI-OPTIONAL | **New gap** — often asked |
| `association_type` | Association Type | Tax, Legal, HOA & Disclosures | select | AI-OPTIONAL | HOA / Condo / Co-op |
| `association_type_other` | Association Type (Other) | Tax, Legal, HOA & Disclosures | text | AI-OPTIONAL | Companion |
| `association_fee_amount` | HOA Fee Amount | Tax, Legal, HOA & Disclosures | currency | DATABASE-FIRST | → SEL-017 (broken accessor) |
| `association_fee_frequency` | HOA Fee Frequency | Tax, Legal, HOA & Disclosures | select | DATABASE-FIRST | → SEL-018 (broken accessor) |
| `association_fee_frequency_other` | Fee Frequency (Other) | Tax, Legal, HOA & Disclosures | text | AI-OPTIONAL | Companion |
| `association_fee_includes` | HOA Fee Includes | Tax, Legal, HOA & Disclosures | JSON multi-select | AI-OPTIONAL | **New gap** — water/trash/insurance etc. |
| `association_fee_includes_other` | Fee Includes (Other) | Tax, Legal, HOA & Disclosures | text | AI-OPTIONAL | Companion |
| `association_amenities` | Association Amenities | Tax, Legal, HOA & Disclosures | JSON multi-select | AI-OPTIONAL | **New gap** |
| `association_amenities_other` | Amenities (Other) | Tax, Legal, HOA & Disclosures | text | AI-OPTIONAL | Companion |
| `association_approval_required` | HOA Approval Required | Tax, Legal, HOA & Disclosures | boolean | AI-OPTIONAL | **New gap** |
| `association_application_fee` | HOA Application Fee | Tax, Legal, HOA & Disclosures | currency | AI-OPTIONAL | **New gap** |
| `association_approval_process` | HOA Approval Process | Tax, Legal, HOA & Disclosures | text | AI-REQUIRED | Free text description |
| `hoa_condo_association_terms` | HOA/Condo Terms | Tax, Legal, HOA & Disclosures | text | AI-REQUIRED | Free text |
| `hoa_condo_docs_available` | HOA Docs Available | Tax, Legal, HOA & Disclosures | boolean | DATABASE-FIRST | **New gap** |
| `leasing_55_plus` | 55+ Community | Tax, Legal, HOA & Disclosures | boolean | DATABASE-FIRST | → SEL-025 adjacent; important disclosure |
| `additional_lease_restrictions` | Additional Restrictions | Tax, Legal, HOA & Disclosures | text | AI-REQUIRED | Free text; expands `leasing_restrictions` |
| `max_leases_per_year` | Max Leases Per Year | Tax, Legal, HOA & Disclosures | numeric | AI-OPTIONAL | Rental restriction detail |
| `min_lease_period` | Minimum Lease Period | Tax, Legal, HOA & Disclosures | select | AI-OPTIONAL | **New gap** |
| `min_lease_period_other` | Min Lease (Other) | Tax, Legal, HOA & Disclosures | text | AI-OPTIONAL | Companion |

#### CDD / Special Assessments

| Meta Key | Display Label | Tab | Type | Classification | Notes |
|---|---|---|---|---|---|
| `has_cdd` | CDD Exists | Tax, Legal, HOA & Disclosures | boolean | DATABASE-FIRST | **New gap** — Community Development District |
| `annual_cdd_fee` | Annual CDD Fee | Tax, Legal, HOA & Disclosures | currency | DATABASE-FIRST | **New gap** |
| `has_special_assessments` | Special Assessments | Tax, Legal, HOA & Disclosures | boolean | DATABASE-FIRST | **New gap** |
| `special_assessment_amount` | Assessment Amount | Tax, Legal, HOA & Disclosures | currency | AI-OPTIONAL | **New gap** |
| `special_assessment_description` | Assessment Description | Tax, Legal, HOA & Disclosures | text | AI-REQUIRED | Free text |
| `annual_property_taxes` | Annual Property Taxes | Tax, Legal, HOA & Disclosures | currency | DATABASE-FIRST | → SEL-029 (fully connected) |
| `tax_year` | Tax Year | Tax, Legal, HOA & Disclosures | year | AI-OPTIONAL | Context for tax amount |

#### Disclosures & Available Documents

| Meta Key | Display Label | Tab | Type | Classification | Notes |
|---|---|---|---|---|---|
| `seller_disclosure_available` | Seller Disclosure Available | Tax, Legal, HOA & Disclosures | boolean | DATABASE-FIRST | **New gap** |
| `inspection_report_available` | Inspection Report Available | Tax, Legal, HOA & Disclosures | boolean | DATABASE-FIRST | **New gap** |
| `survey_available` | Survey Available | Tax, Legal, HOA & Disclosures | boolean | DATABASE-FIRST | **New gap** |
| `flood_disclosure_available` | Flood Disclosure Available | Tax, Legal, HOA & Disclosures | boolean | DATABASE-FIRST | **New gap** |
| `flood_insurance_required` | Flood Insurance Required | Tax, Legal, HOA & Disclosures | boolean | DATABASE-FIRST | **New gap** |
| `flood_zone_code` | Flood Zone Code | Tax, Legal, HOA & Disclosures | select | DATABASE-FIRST | → SEL-026 (broken accessor) |
| `flood_zone_code_other` | Flood Zone (Other) | Tax, Legal, HOA & Disclosures | text | AI-OPTIONAL | Companion |
| `flood_zone_panel` | Flood Zone Panel # | Tax, Legal, HOA & Disclosures | text | AI-OPTIONAL | FEMA panel reference |
| `flood_zone_date` | Flood Zone Map Date | Tax, Legal, HOA & Disclosures | date | AI-OPTIONAL | FEMA map revision date |
| `lead_based_paint_disclosure` | Lead Paint Disclosure | Tax, Legal, HOA & Disclosures | boolean | DATABASE-FIRST | Required for pre-1978 homes; disclosure-only |
| `environmental_report_available` | Environmental Report Available | Tax, Legal, HOA & Disclosures | boolean | DATABASE-FIRST | Commercial properties |
| `listing_documents` | Listing Documents | Photos, Tours & Documents | JSON | MEDIA | Document file attachments; not Ask AI text |
| `doc_rows` | Document Rows Config | Photos, Tours & Documents | JSON | INTERNAL | UI state for document table |

#### Transaction Preferences

| Meta Key | Display Label | Tab | Type | Classification | Notes |
|---|---|---|---|---|---|
| `possession_preference` | Possession Preference | Sale Terms | select | DATABASE-FIRST | **New gap** — At closing / leaseback / TBD |
| `possession_details` | Possession Details | Sale Terms | text | AI-REQUIRED | Free text elaboration |
| `target_closing_date` | Target Closing Date | Sale Terms | date | DATABASE-FIRST | → SEL-027 (broken accessor) |
| `appraisal_contingency_preference` | Appraisal Contingency | Sale Terms | select | DATABASE-FIRST | **New gap** |
| `financing_contingency_preference` | Financing Contingency | Sale Terms | select | DATABASE-FIRST | **New gap** |
| `preferred_inspection_period` | Preferred Inspection Period | Sale Terms | numeric | AI-OPTIONAL | **New gap** — seller's preference in days |
| `home_warranty_offered` | Home Warranty Offered | Sale Terms | boolean | DATABASE-FIRST | **New gap** |
| `home_warranty_amount_details` | Warranty Details | Sale Terms | text | AI-OPTIONAL | Free text |
| `escrow_agent_preference` | Escrow Agent Preference | Sale Terms | text | AI-OPTIONAL | **New gap** |
| `seller_contribution_credit_offered` | Seller Concession Offered | Sale Terms | boolean | DATABASE-FIRST | **New gap** |
| `seller_contribution_amount_details` | Concession Details | Sale Terms | text | AI-OPTIONAL | **New gap** |

#### Commercial Income Properties (Structural sub-fields)

| Meta Key | Display Label | Tab | Type | Classification | Notes |
|---|---|---|---|---|---|
| `total_square_feet` | Total Building Sq Ft | Financial Details | numeric | AI-OPTIONAL | **New gap** — SEL-CI context |
| `minimum_leaseable` | Min Leaseable Sq Ft | Financial Details | numeric | AI-OPTIONAL | **New gap** |
| `minimum_cap_rate` | Minimum Cap Rate | Financial Details | numeric | AI-OPTIONAL | **New gap** |
| `gross_annual_income` | Gross Annual Income | Financial Details | currency | AI-REQUIRED | → Covered via FAQ `annual_net_operating_income`; raw structural field |
| `annual_operating_expenses` | Annual Operating Expenses | Financial Details | currency | AI-REQUIRED | → FAQ path |
| `rent_roll_available` | Rent Roll Available | Financial Details | boolean | DATABASE-FIRST | **New gap** — highly relevant to buyers |
| `operating_statement_available` | Operating Statement Available | Financial Details | boolean | DATABASE-FIRST | **New gap** |
| `price_per_sqft` | Price Per Sq Ft | Financial Details | currency | AI-OPTIONAL | **New gap** |
| `existing_lease_type` | Existing Lease Type | Financial Details | select | AI-OPTIONAL | Current tenant lease type |
| `other_lease_type` | Lease Type (Other) | Financial Details | text | AI-OPTIONAL | Companion |
| `lease_expiration` | Lease Expiration Date | Financial Details | date | AI-OPTIONAL | **New gap** |
| `lease_assignable` | Lease Assignable | Financial Details | boolean | AI-OPTIONAL | **New gap** |
| `number_of_unit` | Number of Units | Financial Details | numeric | AI-OPTIONAL | Already in Landlord; gap for Seller commercial |
| `unit_size` | Unit Size | Financial Details | select | AI-OPTIONAL | **New gap** |
| `unit_size_other` | Unit Size (Other) | Financial Details | text | AI-OPTIONAL | Companion |
| `unit_type_configurations` | Unit Type Configurations | Financial Details | JSON | AI-OPTIONAL | Multi-family unit mix |
| `unit_buildings` | Number of Buildings | Financial Details | numeric | AI-OPTIONAL | Multi-building campuses |

#### Business-for-Sale Fields

| Meta Key | Display Label | Tab | Type | Classification | Notes |
|---|---|---|---|---|---|
| `business_name` | Business Name | Financial Details | text | AI-OPTIONAL | **New gap** |
| `year_established` | Year Established | Financial Details | year | DATABASE-FIRST | **New gap** — analogous to `year_built` |
| `employee_count` | Employee Count | Financial Details | numeric | AI-OPTIONAL | **New gap** |
| `licenses` | Licenses/Permits | Financial Details | JSON multi-select | AI-OPTIONAL | **New gap** |
| `other_licenses` | Licenses (Other) | Financial Details | text | AI-OPTIONAL | Companion |
| `business_assets` | Business Assets | Financial Details | JSON multi-select | AI-OPTIONAL | **New gap** |
| `nda_required` | NDA Required | Financial Details | boolean | DATABASE-FIRST | **New gap** — blocks AI answer unless NDA signed |
| `financial_statements_available` | Financial Statements Available | Financial Details | boolean | DATABASE-FIRST | **New gap** |
| `tax_returns_available` | Tax Returns Available | Financial Details | boolean | DATABASE-FIRST | **New gap** |
| `business_location_leased` | Business Location Leased | Financial Details | boolean | DATABASE-FIRST | **New gap** |
| `business_lease_monthly_rent` | Business Lease Rent | Financial Details | currency | RESTRICTED | Sensitive financial detail |
| `business_lease_expiration` | Business Lease Expiration | Financial Details | date | AI-OPTIONAL | **New gap** |
| `business_lease_renewal_options` | Lease Renewal Options | Financial Details | text | AI-OPTIONAL | **New gap** |
| `business_lease_assignable` | Business Lease Assignable | Financial Details | boolean | AI-OPTIONAL | **New gap** |
| `business_lease_additional_terms` | Additional Lease Terms | Financial Details | text | AI-REQUIRED | Free text |
| `reason_for_sale` | Reason for Sale | Financial Details | select | AI-OPTIONAL | **New gap** |
| `other_reason_for_sale` | Reason (Other) | Financial Details | text | AI-OPTIONAL | Companion |
| `annual_revenue` | Annual Revenue | Financial Details | currency | RESTRICTED | Financial — sensitive; available in FAQ context only |
| `gross_profit` | Gross Profit | Financial Details | currency | RESTRICTED | Financial — sensitive |
| `sde_ebitda` | SDE / EBITDA | Financial Details | currency | RESTRICTED | Financial — sensitive |
| `inventory_value` | Inventory Value | Financial Details | currency | RESTRICTED | Financial — sensitive |
| `ffe_value` | FF&E Value | Financial Details | currency | RESTRICTED | Furniture/Fixtures/Equipment; financial |

#### Lease/Rent-to-Own Terms (Seller side)

| Meta Key | Display Label | Tab | Type | Classification | Notes |
|---|---|---|---|---|---|
| `interested_lease_option_agreement` | Interested in Lease-Option | Sale Terms | boolean | DATABASE-FIRST | **New gap** |
| `lease_option_price` | Lease-Option Purchase Price | Sale Terms | currency | DATABASE-FIRST | **New gap** |
| `lease_option_duration` | Lease-Option Duration | Sale Terms | text | DATABASE-FIRST | **New gap** |
| `lease_option_conditions` | Lease-Option Conditions | Sale Terms | text | AI-REQUIRED | Free text |
| `lease_option_terms` | Lease-Option Terms | Sale Terms | text | AI-REQUIRED | Free text |
| `lease_purchase_price` | Lease-Purchase Price | Sale Terms | currency | DATABASE-FIRST | **New gap** |
| `lease_purchase_duration` | Lease-Purchase Duration | Sale Terms | text | DATABASE-FIRST | **New gap** |
| `lease_purchase_conditions` | Lease-Purchase Conditions | Sale Terms | text | AI-REQUIRED | Free text |
| `lease_purchase_terms` | Lease-Purchase Terms | Sale Terms | text | AI-REQUIRED | Free text |
| `lease_option_fee_type` | Lease-Option Fee Type | Sale Terms | select | RESTRICTED | Agent fee structure |
| `lease_option_fee_flat` | Lease-Option Fee (Flat) | Sale Terms | currency | RESTRICTED | Agent fee |
| `lease_option_fee_percentage` | Lease-Option Fee (%) | Sale Terms | numeric | RESTRICTED | Agent fee |
| `lease_option_fee_other` | Lease-Option Fee (Other) | Sale Terms | text | RESTRICTED | Agent fee |
| `lease_option_payment` | Option Payment Amount | Sale Terms | currency | DATABASE-FIRST | **New gap** |
| `seller_lease_option_fee_credit` | Option Fee Credit | Sale Terms | select | AI-OPTIONAL | **New gap** |
| `seller_lease_option_fee_credit_percent` | Option Fee Credit % | Sale Terms | numeric | AI-OPTIONAL | **New gap** |
| `seller_lease_option_maintenance` | Maintenance Responsibility | Sale Terms | select | AI-OPTIONAL | **New gap** |
| `seller_lease_option_extension_terms` | Extension Terms | Sale Terms | text | AI-REQUIRED | Free text |
| `lease_purchase_option_fee` | Purchase Option Fee Type | Sale Terms | select | RESTRICTED | Agent fee |
| `lease_purchase_option_fee_amount` | Purchase Option Fee Amount | Sale Terms | currency | RESTRICTED | Agent fee |
| `lease_purchase_payment` | Lease-Purchase Monthly Payment | Sale Terms | currency | DATABASE-FIRST | **New gap** |
| `seller_lease_purchase_deposit` | Lease-Purchase Deposit | Sale Terms | currency | DATABASE-FIRST | **New gap** |
| `seller_lease_purchase_rent_credit` | Rent Credit Offered | Sale Terms | boolean | AI-OPTIONAL | **New gap** |
| `seller_lease_purchase_rent_credit_amount` | Rent Credit Amount | Sale Terms | currency | AI-OPTIONAL | **New gap** |
| `seller_lease_purchase_rent_credit_type` | Rent Credit Type | Sale Terms | select | AI-OPTIONAL | **New gap** |
| `seller_lease_purchase_maintenance` | Purchase Maintenance | Sale Terms | select | AI-OPTIONAL | **New gap** |
| `seller_lease_purchase_extension_terms` | Purchase Extension Terms | Sale Terms | text | AI-REQUIRED | Free text |

#### Agent Services & Compensation (ALL RESTRICTED)

The following meta keys represent agent service selections and fee structures. They are operational data for the platform's agent billing system and must **never** be exposed via Ask AI.

`attend_showings`, `attend_showings_count`, `attend_showings_fee`, `schedule_showings`, `schedule_showings_fee`, `provide_virtual_tours`, `virtual_tours_fee`, `virtual_tours_count`, `virtual_showings_count`, `open_house_count`, `staging_duration`, `launch_ads`, `launch_ads_fee`, `promote_social`, `promote_social_fee`, `email_marketing_fee`, `email_notifications_fee`, `market_groups`, `market_groups_fee`, `marketing_materials_fee`, `mls_filter_fee`, `neighborhood_insights_fee`, `neighborhood_marketing_fee`, `neighborhood_materials_fee`, `list_criteria`, `list_criteria_fee`, `off_market_search_fee`, `short_term_housing_fee`, `prepare_application_fee`, `collect_documents`, `collect_documents_fee`, `assist_application`, `assist_application_fee`, `submit_application`, `submit_application_fee`, `review_lease`, `review_lease_fee`, `provide_lease_form`, `provide_lease_form_fee`, `lease_advice_fee`, `move_in_inspection_fee`, `moving_resources_fee`, `coordinate_signing`, `coordinate_signing_fee`, `rental_rights_fee`, `number_of_showings_to_attend`, `number_of_showings_to_schedule`, `number_of_virtual_tours`, `showings_count`, `include_marketing_fee`, `total_flat_fee`, `total_marketing_fee`, `fees`, `enable`, `commission_structure`, `brokerage_relationship`, `purchase_fee_type`, `purchase_fee_flat`, `purchase_fee_flat_combo`, `purchase_fee_percentage`, `purchase_fee_percentage_combo`, `purchase_fee_other`, `lease_fee_type`, `lease_fee_flat`, `lease_fee_flat_combo`, `lease_fee_months`, `lease_fee_percentage`, `lease_fee_percentage_combo`, `lease_fee_percentage_monthly_rent`, `lease_fee_other`, `retainer_fee_option`, `retainer_fee_amount`, `retainer_fee_application`, `protection_period`, `agency_agreement_timeframe`, `agency_agreement_custom`, `service_completion_date`, `service_completion_time`, `service_time_zone`, `additional_seller_sale_terms`, `additional_cash`, `additional_details`, `additional_details_broker`, `person_meeting`, `value_determination`, `agent_brokerage`, `agent_license_number`, `agent_nar_member_id`, `custom_services`, `buy_now_price` (note: also a phantom context key)

#### Listing Administration (ALL INTERNAL)

`workflow_type`, `user_type`, `listing_status`, `listing_date`, `expiration_date`, `auction_type`, `auction_time`, `draft_version`, `parent_draft_id`, `draft_payload_hash`, `working_with_agent`, `desired_agent_hire_date`, `linked_offer_auction_id`, `listing_ai_faq` (the stored FAQ answers blob — read by context builder, internal key)

#### Location / Geocoding (ALL LOCATION-HELPER)

`cities`, `counties`, `state`, `zip_code`, `zipCodes`, `property_city`, `property_county`, `property_state`, `property_zip`, `address` (→ SEL-001, fully connected)

#### PII (ALL BLOCKED)

`first_name`, `last_name`, `email`, `phone_number`, `meeting_details_first_name`, `meeting_details_last_name`, `meeting_details_email`, `meeting_details_phone`, `meeting_details_meeting_date`, `meeting_details_meeting_time`, `meeting_details_time_zone`, `meeting_details_instructions`, `meeting_details_additional_details`

#### Media / Photos (NOT ELIGIBLE for text AI)

`property_photos`, `photo`, `video`, `video_link`, `video_tour_url`, `virtual_tour_url`

#### Cryptocurrency / NFT Payment Sub-fields (CRYPTO-NICHE)

`offered_financing` (→ BUY-019, partially connected), `cryptocurrency_type`, `crypto_percentage`, `crypto_custodian_wallet`, `crypto_exchange_method`, `crypto_transaction_fees`, `crypto_transfer_timing`, `crypto_transfer_timing_other`, `cash_percentage_crypto`, `nft_percentage`, `nft_description`, `cash_percentage_nft`, `other_financing`

#### Payment Calculator Display Fields (INTERNAL)

`payment_annual_property_taxes`, `payment_down_payment_pct`, `payment_hoa_fee_amount`, `payment_hoa_fee_frequency`, `payment_interest_rate`, `payment_loan_term`, `payment_monthly_insurance`, `payment_pmi_rate`, `payment_show_buydown_options`

---

### 17.2 Buyer Form — Additional Field Inventory

Fields from `BuyerOfferListing.php` not already covered in Sections 4.1–4.5.

#### Commute Criteria

| Meta Key | Display Label | Tab | Type | Classification | Notes |
|---|---|---|---|---|---|
| `commute_destination_zip` | Commute Destination ZIP | Property Preferences | text | DATABASE-FIRST | **New gap** — paired with commute distance |
| `max_commute_minutes` | Max Commute Minutes | Property Preferences | numeric | DATABASE-FIRST | **New gap** |
| `commute_mode` | Commute Mode | Property Preferences | select | DATABASE-FIRST | Drive / Transit / Walk |

#### Flood Zone Tolerance

| Meta Key | Display Label | Tab | Type | Classification | Notes |
|---|---|---|---|---|---|
| `flood_zone_tolerance` | Flood Zone Tolerance | Property Preferences | select | DATABASE-FIRST | **New gap** — buyer's comfort level |
| `flood_zone_tolerance_other` | Tolerance (Other) | Property Preferences | text | AI-OPTIONAL | Companion |

#### Sale / Transaction Preferences

| Meta Key | Display Label | Tab | Type | Classification | Notes |
|---|---|---|---|---|---|
| `sale_provision` | Sale Provisions | Purchasing Terms | select | DATABASE-FIRST | **New gap** — standard / assignment / etc. |
| `sale_provision_other` | Sale Provision (Other) | Purchasing Terms | text | AI-OPTIONAL | Companion |
| `sale_provision_assignment` | Assignment Provision | Purchasing Terms | boolean | AI-OPTIONAL | Wholesale/assignment flag |
| `as_is_purchase` | Willing to Buy As-Is | Purchasing Terms | boolean | DATABASE-FIRST | **New gap** |
| `purchase_purpose` | Purchase Purpose | Purchasing Terms | select | DATABASE-FIRST | **New gap** — Primary / Investment / Vacation |
| `purchase_purpose_other` | Purpose (Other) | Purchasing Terms | text | AI-OPTIONAL | Companion |
| `target_closing_date` | Target Closing Date | Purchasing Terms | date | DATABASE-FIRST | → BUY-021 (broken) |
| `closing_cost_responsibility` | Closing Cost Preference | Purchasing Terms | select | DATABASE-FIRST | **New gap** |
| `home_warranty_requested` | Home Warranty Requested | Purchasing Terms | boolean | DATABASE-FIRST | **New gap** |
| `home_warranty_details` | Warranty Details | Purchasing Terms | text | AI-OPTIONAL | Companion |
| `property_inclusions` | Property Inclusions Wanted | Purchasing Terms | text | AI-OPTIONAL | **New gap** |
| `property_exclusions` | Property Exclusions | Purchasing Terms | text | AI-OPTIONAL | **New gap** |
| `possession_preference` | Preferred Possession Timing | Purchasing Terms | select | DATABASE-FIRST | **New gap** |
| `possession_preference_other` | Possession (Other) | Purchasing Terms | text | AI-OPTIONAL | Companion |
| `possession_details` | Possession Details | Purchasing Terms | text | AI-REQUIRED | Free text |
| `seller_contribution` | Seller Contribution Wanted | Purchasing Terms | boolean | DATABASE-FIRST | **New gap** |
| `seller_contribution_details` | Contribution Details | Purchasing Terms | text | AI-OPTIONAL | **New gap** |

#### Financing Sub-fields (RESTRICTED or AI-OPTIONAL)

| Meta Key | Display Label | Tab | Type | Classification | Notes |
|---|---|---|---|---|---|
| `pre_approved` | Pre-Approval Status | Purchasing Terms | boolean | DATABASE-FIRST | → BUY-018 (broken) |
| `pre_approval_amount` | Pre-Approval Amount | Purchasing Terms | currency | RESTRICTED | Financial detail — restricted |
| `cash_budget` | Cash Available | Purchasing Terms | currency | RESTRICTED | Financial detail |
| `down_payment_type` | Down Payment Type | Purchasing Terms | select | DATABASE-FIRST | → BUY-019 pathway |
| `down_payment_amount` | Down Payment Amount | Purchasing Terms | currency | RESTRICTED | Financial detail |
| `offered_financing` | Financing Types | Purchasing Terms | JSON multi-select | DATABASE-FIRST | → BUY-019 (wrong extraction) |
| `seller_financing_type` | Seller Financing Wanted | Purchasing Terms | select | DATABASE-FIRST | **New gap** |
| `seller_financing_amount` | Seller Financing Amount | Purchasing Terms | currency | RESTRICTED | Sensitive |
| `interest_rate` | Desired Interest Rate | Purchasing Terms | numeric | RESTRICTED | Financial negotiation detail |
| `loan_duration` | Loan Duration | Purchasing Terms | select | AI-OPTIONAL | **New gap** |
| `seller_amortization_type` | Amortization Type | Purchasing Terms | select | AI-OPTIONAL | **New gap** |
| `seller_payment_frequency` | Payment Frequency | Purchasing Terms | select | AI-OPTIONAL | **New gap** |
| `prepayment_penalty` | Prepayment Penalty Acceptable | Purchasing Terms | boolean | AI-OPTIONAL | **New gap** |
| `balloon_payment` | Balloon Payment Acceptable | Purchasing Terms | boolean | AI-OPTIONAL | **New gap** |
| `balloon_payment_amount` | Balloon Payment Amount | Purchasing Terms | currency | RESTRICTED | Financial detail |
| `balloon_payment_date` | Balloon Payment Date | Purchasing Terms | date | AI-OPTIONAL | **New gap** |
| `assumable_interest` | Assumable Loan Interest | Purchasing Terms | boolean | AI-OPTIONAL | **New gap** |
| `assumable_max_interest_rate` | Max Assumable Rate | Purchasing Terms | numeric | RESTRICTED | Financial detail |
| `assumable_max_monthly_payment` | Max Assumable Payment | Purchasing Terms | currency | RESTRICTED | Financial detail |
| `assumable_bridge_gap_cash` | Bridge Gap Cash | Purchasing Terms | currency | RESTRICTED | Financial detail |
| `exchange_item` | Exchange / Trade Item | Purchasing Terms | select | DATABASE-FIRST | **New gap** — trade/exchange sales |
| `exchange_item_value` | Exchange Item Value | Purchasing Terms | currency | RESTRICTED | |
| `exchange_item_condition` | Exchange Item Condition | Purchasing Terms | select | AI-OPTIONAL | |

#### Contingency Sub-fields

| Meta Key | Display Label | Tab | Type | Classification | Notes |
|---|---|---|---|---|---|
| `earnest_money_type` | Earnest Money Type | Purchasing Terms | select | DATABASE-FIRST | **New gap** |
| `earnest_money_amount` | Earnest Money Amount | Purchasing Terms | currency | RESTRICTED | Negotiation detail |
| `earnest_money_timing` | Earnest Money Timing | Purchasing Terms | select | AI-OPTIONAL | **New gap** |
| `inspection_contingency_buyer` | Inspection Contingency | Purchasing Terms | boolean | DATABASE-FIRST | → BUY-022 |
| `inspection_period_days` | Inspection Period (Days) | Purchasing Terms | numeric | DATABASE-FIRST | → BUY-020 (broken) |
| `appraisal_contingency_buyer` | Appraisal Contingency | Purchasing Terms | boolean | DATABASE-FIRST | → BUY-023 |
| `appraisal_contingency_days` | Appraisal Period (Days) | Purchasing Terms | numeric | DATABASE-FIRST | **New gap** |
| `financing_contingency_buyer` | Financing Contingency | Purchasing Terms | boolean | DATABASE-FIRST | → BUY-024 |
| `financing_contingency_days_buyer` | Financing Period (Days) | Purchasing Terms | numeric | DATABASE-FIRST | **New gap** |
| `home_sale_contingency` | Home Sale Contingency | Purchasing Terms | boolean | DATABASE-FIRST | **New gap** |
| `home_sale_contingency_date` | Home Sale Deadline | Purchasing Terms | date | AI-OPTIONAL | **New gap** |
| `home_sale_contingency_address` | Property to Sell (Address) | Purchasing Terms | text | RESTRICTED | PII-adjacent |
| `home_sale_contingency_under_contract` | Under Contract Status | Purchasing Terms | boolean | DATABASE-FIRST | **New gap** |
| `due_diligence_yn` | Due Diligence Period | Purchasing Terms | boolean | DATABASE-FIRST | **New gap** |
| `sale_of_buyer_property_contingency` | Sale Contingency | Purchasing Terms | boolean | DATABASE-FIRST | **New gap** |
| `buyer_sell_contract` | Buyer Has Sell Contract | Purchasing Terms | boolean | DATABASE-FIRST | **New gap** |

#### Buyer Self-Description (RESTRICTED/INTERNAL)

`credit_scroe_rating` (typo in key — "scroe"), `monthly_income`, `number_occupant`, `prior_eviction`, `prior_felony`, `eviction_explanation`, `prior_felony_explanation` — All RESTRICTED. These are applicant screening fields never exposed via Ask AI.

#### Buyer Agent Services (ALL RESTRICTED)

Same pattern as Seller: `attend_showings*`, `schedule_showings*`, `off_market_search_fee`, `lease_fee_*`, `purchase_fee_*`, `retainer_fee_*`, `agency_agreement_*`, `protection_period`, `commission_structure`, `custom_services`, `additional_purchase_terms`, etc.

---

### 17.3 Landlord Form — Additional Field Inventory

Fields from `LandlordOfferListing.php` not covered in Sections 5.1–5.4.

#### Physical Property Features (Residential)

| Meta Key | Display Label | Tab | Type | Classification | Notes |
|---|---|---|---|---|---|
| `air_conditioning` | Air Conditioning | Property Details | select | AI-OPTIONAL | **New gap** |
| `other_air_conditioning` | A/C (Other) | Property Details | text | AI-OPTIONAL | Companion |
| `heating_fuel` | Heating & Fuel | Property Details | select | AI-OPTIONAL | **New gap** (note: key differs from Seller's `heating_and_fuel`) |
| `other_heating_fuel` | Heating (Other) | Property Details | text | AI-OPTIONAL | Companion |
| `interior_features` | Interior Features | Property Details | JSON | AI-OPTIONAL | **New gap** |
| `other_interior_features` | Interior Features (Other) | Property Details | text | AI-OPTIONAL | Companion |
| `floor_covering` | Floor Covering | Property Details | JSON | AI-OPTIONAL | **New gap** |
| `other_floor_covering` | Floor Covering (Other) | Property Details | text | AI-OPTIONAL | Companion |
| `laundry_features` | Laundry Features | Property Details | JSON | AI-OPTIONAL | **New gap** |
| `other_laundry_features` | Laundry (Other) | Property Details | text | AI-OPTIONAL | Companion |
| `appliances` | Appliances | Property Details | JSON | AI-OPTIONAL | **New gap** — included with rental |
| `appliances_other` | Appliances (Other) | Property Details | text | AI-OPTIONAL | Companion |
| `security_features` | Security Features | Property Details | JSON | AI-OPTIONAL | **New gap** |
| `other_security_features` | Security (Other) | Property Details | text | AI-OPTIONAL | Companion |
| `building_features` | Building Features | Property Details | JSON | AI-OPTIONAL | **New gap** |
| `other_building_features` | Building Features (Other) | Property Details | text | AI-OPTIONAL | Companion |
| `sqft_heated_source` | Heated Sqft Source | Property Details | select | INTERNAL | Measurement origin metadata |
| `exterior_construction` | Exterior Construction | Property Details | JSON | AI-OPTIONAL | **New gap** |
| `other_exterior_construction` | Exterior (Other) | Property Details | text | AI-OPTIONAL | Companion |
| `foundation` | Foundation | Property Details | JSON | AI-OPTIONAL | **New gap** |
| `other_foundation` | Foundation (Other) | Property Details | text | AI-OPTIONAL | Companion |
| `roof_type` | Roof Type | Property Details | JSON | AI-OPTIONAL | **New gap** |
| `other_roof_type` | Roof Type (Other) | Property Details | text | AI-OPTIONAL | Companion |

#### Commercial Space Features

| Meta Key | Display Label | Tab | Type | Classification | Notes |
|---|---|---|---|---|---|
| `space_type` | Space Type | Property Details | select | DATABASE-FIRST | **New gap** — retail / office / warehouse / flex |
| `other_space_type` | Space Type (Other) | Property Details | text | AI-OPTIONAL | Companion |
| `space_classification` | Space Classification | Property Details | select | DATABASE-FIRST | **New gap** — Class A/B/C |
| `other_space_classification` | Classification (Other) | Property Details | text | AI-OPTIONAL | Companion |
| `space_features` | Space Features | Property Details | JSON | AI-OPTIONAL | **New gap** — open plan / server room / loading dock |
| `ceiling_height` | Ceiling Height | Property Details | text | AI-OPTIONAL | **New gap** |
| `room_size` | Room Size | Property Details | text | AI-OPTIONAL | Individual room/suite size |
| `office_retail_sqft` | Office/Retail Sq Ft | Property Details | numeric | AI-OPTIONAL | **New gap** |
| `flex_space_sqft` | Flex Space Sq Ft | Property Details | numeric | AI-OPTIONAL | **New gap** |
| `building_hours` | Building Hours | Property Details | text | AI-OPTIONAL | **New gap** |
| `access_24_7` | 24/7 Access | Property Details | boolean | DATABASE-FIRST | **New gap** |
| `bathroom_facilities` | Bathroom Facilities | Property Details | select | AI-OPTIONAL | **New gap** — private / shared / none |
| `common_areas_access` | Common Areas Access | Property Details | text | AI-OPTIONAL | **New gap** |
| `common_areas_cleaning` | Common Areas Cleaning | Property Details | select | AI-OPTIONAL | **New gap** |
| `shared_amenities` | Shared Amenities | Property Details | JSON | AI-OPTIONAL | **New gap** |
| `neighboring_tenants` | Neighboring Tenants | Property Details | text | AI-REQUIRED | Free text; current tenants in building |
| `number_of_offices` | Number of Offices | Property Details | numeric | AI-OPTIONAL | **New gap** |
| `number_of_conference_rooms` | Number of Conference Rooms | Property Details | numeric | AI-OPTIONAL | **New gap** |
| `number_of_restrooms` | Number of Restrooms | Property Details | numeric | AI-OPTIONAL | **New gap** |
| `number_of_occupants_allowed` | Max Occupants Allowed | Property Details | numeric | AI-OPTIONAL | **New gap** (note: `LND-028` has key mismatch) |

#### Commercial Lease Terms (Landlord side)

| Meta Key | Display Label | Tab | Type | Classification | Notes |
|---|---|---|---|---|---|
| `commercial_lease_type` | Commercial Lease Type | Leasing Terms | select | DATABASE-FIRST | **New gap** — Gross / NNN / Modified Gross |
| `commercial_lease_type_other` | Lease Type (Other) | Leasing Terms | text | AI-OPTIONAL | Companion |
| `cam_nnn_additional_rent_charges` | CAM/NNN Charges | Leasing Terms | text | AI-OPTIONAL | **New gap** — operating expense pass-throughs |
| `tenant_improvement_buildout_terms` | TI/Buildout Terms | Leasing Terms | text | AI-REQUIRED | **New gap** — tenant improvement allowance |
| `signage_rights` | Signage Rights | Leasing Terms | select | DATABASE-FIRST | **New gap** |
| `rent_escalation_terms` | Rent Escalation Terms | Leasing Terms | text | AI-REQUIRED | **New gap** |
| `renewal_option_offered` | Renewal Option Offered | Leasing Terms | boolean | DATABASE-FIRST | **New gap** |
| `renewal_option_details` | Renewal Option Details | Leasing Terms | text | AI-REQUIRED | Free text |
| `gross_percentage_rent` | Gross Percentage Rent | Leasing Terms | numeric | RESTRICTED | Gross lease percentage detail |
| `month_percentage_rent` | Monthly Percentage Rent | Leasing Terms | numeric | RESTRICTED | Commission-adjacent |
| `net_aggregate_rent` | Net Aggregate Rent | Leasing Terms | currency | RESTRICTED | Internal calculation |
| `permitted_use_restrictions` | Permitted Use Restrictions | Leasing Terms | text | AI-REQUIRED | **New gap** — what tenant can/cannot do |
| `personal_guarantee_requirement` | Personal Guarantee Required | Leasing Terms | select | DATABASE-FIRST | **New gap** |
| `commercial_parking_terms` | Parking Terms | Leasing Terms | text | AI-OPTIONAL | **New gap** |
| `commercial_approval_conditions` | Approval Conditions | Leasing Terms | text | AI-REQUIRED | Free text |
| `parking_terms` | Parking Terms | Leasing Terms | text | AI-OPTIONAL | Residential version |

#### Tenant Screening & Policies

| Meta Key | Display Label | Tab | Type | Classification | Notes |
|---|---|---|---|---|---|
| `occupant_types` | Occupant Types | Leasing Terms | JSON | DATABASE-FIRST | **New gap** — families / singles / students |
| `occupant_status` | Occupant Status | Leasing Terms | select | DATABASE-FIRST | **New gap** — vacant / occupied |
| `occupant_tenant` | Current Tenant Info | Leasing Terms | text | RESTRICTED | Existing tenant detail — restricted |
| `min_income_requirement` | Minimum Income Requirement | Leasing Terms | select | DATABASE-FIRST | **New gap** — 2.5x / 3x rent etc. |
| `service_animal` | Service Animal Allowed | Leasing Terms | boolean | DATABASE-FIRST | **New gap** |
| `support_animal` | Support Animal Allowed | Leasing Terms | boolean | DATABASE-FIRST | **New gap** |
| `has_breed_restrictions` | Breed Restrictions Exist | Leasing Terms | boolean | DATABASE-FIRST | **New gap** |
| `breed_restrictions` | Breed Restrictions Detail | Leasing Terms | text | AI-REQUIRED | **New gap** |
| `smoking_policy` | Smoking Policy | Leasing Terms | select | DATABASE-FIRST | **New gap** |
| `subletting_policy` | Subletting Policy | Leasing Terms | select | DATABASE-FIRST | **New gap** |
| `guests_allowed` | Guest Policy | Leasing Terms | text | AI-OPTIONAL | **New gap** |
| `landlord_approval_conditions` | Approval Conditions | Leasing Terms | text | AI-REQUIRED | Free text |

#### Financial Requirements

| Meta Key | Display Label | Tab | Type | Classification | Notes |
|---|---|---|---|---|---|
| `security_deposit_required` | Security Deposit Required | Leasing Terms | boolean | DATABASE-FIRST | **New gap** |
| `security_deposit_amount` | Security Deposit Amount | Leasing Terms | currency | DATABASE-FIRST | **New gap** |
| `pet_deposit_amount` | Pet Deposit Amount | Leasing Terms | currency | DATABASE-FIRST | **New gap** |
| `pet_deposit_fee_rent` | Pet Fee Type | Leasing Terms | select | DATABASE-FIRST | **New gap** — one-time vs monthly |
| `pet_monthly_fee` | Monthly Pet Fee | Leasing Terms | currency | DATABASE-FIRST | **New gap** |
| `pet_max_weight_lbs` | Max Pet Weight (lbs) | Leasing Terms | numeric | DATABASE-FIRST | → LND-016 (no NL route gap) |
| `first_month_rent_required` | First Month Rent Required | Leasing Terms | boolean | DATABASE-FIRST | **New gap** |
| `last_month_rent_required` | Last Month Rent Required | Leasing Terms | boolean | DATABASE-FIRST | **New gap** |
| `total_move_in_funds_required` | Total Move-In Funds | Leasing Terms | currency | DATABASE-FIRST | **New gap** — total upfront cost |
| `split_payment_due` | Split Payment Due | Leasing Terms | select | RESTRICTED | Fee timing detail |
| `split_payment_due_other` | Split Payment (Other) | Leasing Terms | text | RESTRICTED | Companion |

#### Storage

| Meta Key | Display Label | Tab | Type | Classification | Notes |
|---|---|---|---|---|---|
| `included_storage_space` | Storage Included | Property Details | boolean | DATABASE-FIRST | **New gap** |
| `storage_space` | Storage Space Details | Property Details | select | AI-OPTIONAL | **New gap** |
| `included_storage_space_res_single` | Single Res. Storage | Property Details | select | AI-OPTIONAL | Residential single unit storage |
| `included_storage_space_res_both` | Both Res. Storage | Property Details | select | AI-OPTIONAL | |
| `included_storage_space_com_single` | Single Com. Storage | Property Details | select | AI-OPTIONAL | Commercial single |
| `included_storage_space_com_entire` | Entire Com. Storage | Property Details | select | AI-OPTIONAL | |
| `storage_space_res_single` | Res. Single Storage | Property Details | select | AI-OPTIONAL | |
| `storage_space_res_both` | Res. Both Storage | Property Details | select | AI-OPTIONAL | |
| `storage_space_com_single` | Com. Single Storage | Property Details | select | AI-OPTIONAL | |
| `storage_space_com_entire` | Com. Entire Storage | Property Details | select | AI-OPTIONAL | |

#### Land Fields (when applicable)

| Meta Key | Display Label | Tab | Type | Classification | Notes |
|---|---|---|---|---|---|
| `lot_dimensions` | Lot Dimensions | Property Details | text | AI-OPTIONAL | **New gap** |
| `min_acreage` | Minimum Acreage | Property Details | numeric | AI-OPTIONAL | **New gap** |
| `total_acreage` | Total Acreage | Property Details | numeric | AI-OPTIONAL | **New gap** |
| `zoning` | Zoning | Property Details | text | AI-OPTIONAL | **New gap** |
| `zoning_allows` | Zoning Allows | Property Details | text | AI-OPTIONAL | Permitted uses under zoning |
| `road_surface_type` | Road Surface | Property Details | JSON | AI-OPTIONAL | |
| `other_road_surface_type` | Road Surface (Other) | Property Details | text | AI-OPTIONAL | |

#### Property Availability

| Meta Key | Display Label | Tab | Type | Classification | Notes |
|---|---|---|---|---|---|
| `available_date` | Available Date | Leasing Terms | date | DATABASE-FIRST | **New gap** |
| `lease_available_date` | Lease Start Available | Leasing Terms | date | DATABASE-FIRST | **New gap** |
| `desired_lease_length` | Desired Lease Length | Leasing Terms | select | DATABASE-FIRST | **New gap** |
| `custom_lease_term` | Custom Lease Term | Leasing Terms | text | AI-OPTIONAL | **New gap** |
| `starting_rent` | Starting Rent | Leasing Terms | currency | DATABASE-FIRST | **New gap** (distinct from `maximum_budget`) |
| `lease_now_price` | Lease Now Price | Leasing Terms | currency | DATABASE-FIRST | **New gap** |
| `reserve_rent` | Reserve Rent | Leasing Terms | currency | INTERNAL | Auction reserve (internal) |
| `tenant_require` | Tenant Requirements | Leasing Terms | text | AI-REQUIRED | **New gap** — free text listing requirements |
| `other_tenant_pays` | Tenant Pays (Other) | Leasing Terms | text | AI-OPTIONAL | Companion |
| `owner_pays` | Owner Pays | Leasing Terms | JSON | AI-OPTIONAL | **New gap** — utilities/taxes owner covers |
| `owner_pays_other` | Owner Pays (Other) | Leasing Terms | text | AI-OPTIONAL | Companion |
| `rent_includes` | Rent Includes | Leasing Terms | JSON | AI-OPTIONAL | What is bundled in rent |
| `other_rent_include` | Rent Includes (Other) | Leasing Terms | text | AI-OPTIONAL | Companion |
| `property_utilities` | Property Utilities | Leasing Terms | JSON | AI-OPTIONAL | **New gap** |
| `other_property_utilities` | Utilities (Other) | Leasing Terms | text | AI-OPTIONAL | Companion |
| `other_lease_term` | Lease Term (Other) | Leasing Terms | text | AI-OPTIONAL | Companion |
| `terms_of_lease` | Terms of Lease | Leasing Terms | text | AI-REQUIRED | Free text general terms |
| `restrictions` | Restrictions | Leasing Terms | text | AI-REQUIRED | Free text |
| `additional_landlord_lease_terms` | Additional Lease Terms | Leasing Terms | text | AI-REQUIRED | Free text |
| `leasing_space_property` | Leasing Space (Property) | Leasing Terms | select | DATABASE-FIRST | Entire/partial property |
| `leasing_spaces` | Leasing Spaces | Leasing Terms | JSON | AI-OPTIONAL | Multi-space config |

#### Selling Interest Fields

| Meta Key | Display Label | Tab | Type | Classification | Notes |
|---|---|---|---|---|---|
| `interested_in_selling` | Open to Selling | Leasing Terms | boolean | DATABASE-FIRST | **New gap** — landlord open to sale |
| `interested_in_selling_type` | Selling Interest Type | Leasing Terms | select | AI-OPTIONAL | Companion |
| `interested_in_property_management` | Open to Property Mgmt | Leasing Terms | boolean | DATABASE-FIRST | **New gap** |
| `interested_in_property_management_fee` | Prop Mgmt Fee | Leasing Terms | select | RESTRICTED | Fee structure |

#### Agent / Broker Fee Fields — Landlord (ALL RESTRICTED)

`lease_fee_type`, `lease_fee_flat`, `lease_fee_flat_type`, `lease_fee_flat_combo`, `lease_fee_percentage`, `lease_fee_percentage_combo`, `lease_fee_percentage_monthly_rent`, `lease_fee_months`, `lease_fee_other`, `purchase_fee_type`, `purchase_fee_flat`, `purchase_fee_flat_commercial`, `purchase_fee_flat_type`, `purchase_fee_percentage`, `purchase_fee_percentage_combo`, `purchase_fee_months`, `purchase_fee_monthly_percentage`, `purchase_fee_net_aggregate`, `purchase_fee_gross_rent`, `purchase_fee_rental_period`, `purchase_fee_purchase_price`, `purchase_fee_other`, `purchase_fee_other_commercial`, `renewal_fee_type`, `renewal_fee_first_month`, `renewal_fee_flat_free`, `renewal_fee_lease_value`, `renewal_fee_percentage`, `renewal_fee_no_of_months`, `renewal_fee_custom`, `renewal_fee_sales_tax_first_month`, `renewal_fee_sales_tax_flat_fee`, `renewal_fee_sales_tax_lease_value`, `sales_tax_option_flat`, `sales_tax_option_gross`, `sales_tax_option_monthly`, `broker_fee_timing`, `broker_fee_timing_other`, `broker_fee_days_after_lease`, `broker_fee_days_from_rent`, `broker_fee_days_after_rent`, `broker_fee_days_after_due_event`, `landlord_broker_dollar_price`, `landlord_broker_flate_fee`, `landlord_broker_percentage_price`, `landlord_broker_purchase_price`, `landlord_broker_other`, `expansion_commission_type`, `expansion_commission_percentage`, `expansion_custom_commission`, `expansion_first_month_percentage`, `expansion_flat_fee`, `expansion_gross_percentage`, `tenant_broker_commission_structure`, `tenant_broker_fee_structure`, `tenant_broker_percentage`, `tenant_broker_first_month_rent`, `tenant_broker_flat_fee`, `tenant_broker_gross_lease`, `tenant_broker_other`, `tenant_broker_commission_percentage`, `flat_fee`, `net_aggregate_rent`, `month_percentage_rent`, `no_of_months`, `gross_percentage_rent`

---

### 17.4 Tenant Form — Additional Field Inventory

Fields from `TenantOfferListing.php` not covered in Sections 6.1–6.3.

#### Commute & Location Preferences

| Meta Key | Display Label | Tab | Type | Classification | Notes |
|---|---|---|---|---|---|
| `commute_destination_zip` | Commute Destination ZIP | Property Preferences | text | DATABASE-FIRST | **New gap** |
| `max_commute_minutes` | Max Commute Time | Property Preferences | numeric | DATABASE-FIRST | **New gap** |
| `commute_mode` | Commute Mode | Property Preferences | select | DATABASE-FIRST | **New gap** |

#### Move-In Details

| Meta Key | Display Label | Tab | Type | Classification | Notes |
|---|---|---|---|---|---|
| `move_in_date_earliest` | Earliest Move-In Date | Leasing Terms | date | DATABASE-FIRST | **New gap** — critical for matching |
| `move_in_date_latest` | Latest Move-In Date | Leasing Terms | date | DATABASE-FIRST | **New gap** |
| `move_in_budget_upfront` | Upfront Move-In Budget | Leasing Terms | currency | RESTRICTED | Financial detail |
| `move_in_funds_available` | Move-In Funds Available | Leasing Terms | currency | RESTRICTED | Financial detail |
| `security_deposit_budget` | Security Deposit Budget | Leasing Terms | currency | RESTRICTED | Financial detail |
| `first_month_rent_available` | First Month Rent Available | Leasing Terms | boolean | DATABASE-FIRST | **New gap** |
| `last_month_rent_available` | Last Month Rent Available | Leasing Terms | boolean | DATABASE-FIRST | **New gap** |

#### Lease Preferences

| Meta Key | Display Label | Tab | Type | Classification | Notes |
|---|---|---|---|---|---|
| `desired_lease_length` | Desired Lease Length | Leasing Terms | select | DATABASE-FIRST | **New gap** |
| `tenant_desired_lease_length` | Tenant Desired Lease Length | Leasing Terms | select | DATABASE-FIRST | **New gap** — variant key |
| `custom_lease_term` | Custom Lease Term | Leasing Terms | text | AI-OPTIONAL | **New gap** |
| `desired_rental_amount` | Desired Rental Amount | Leasing Terms | currency | DATABASE-FIRST | **New gap** — what tenant wants to pay |
| `lease_amount_frequency` | Payment Frequency | Leasing Terms | select | DATABASE-FIRST | **New gap** |
| `rent_includes` | Rent Includes | Leasing Terms | JSON | AI-OPTIONAL | **New gap** |
| `other_rent_include` | Rent Includes (Other) | Leasing Terms | text | AI-OPTIONAL | Companion |
| `owner_pays` | Owner-Paid Items | Leasing Terms | JSON | AI-OPTIONAL | **New gap** |
| `owner_pays_other` | Owner Pays (Other) | Leasing Terms | text | AI-OPTIONAL | Companion |
| `tenant_pays` | Tenant-Paid Utilities | Leasing Terms | JSON | AI-OPTIONAL | **New gap** |
| `other_tenant_pays` | Tenant Pays (Other) | Leasing Terms | text | AI-OPTIONAL | Companion |
| `terms_of_lease` | Lease Terms Preference | Leasing Terms | text | AI-REQUIRED | Free text |
| `restrictions` | Restriction Tolerance | Leasing Terms | text | AI-REQUIRED | Free text |
| `other_lease_term` | Lease Term (Other) | Leasing Terms | text | AI-OPTIONAL | |
| `additional_tenant_lease_terms` | Additional Lease Terms | Leasing Terms | text | AI-REQUIRED | Free text |
| `rent_escalation_preference` | Rent Escalation Preference | Leasing Terms | select | DATABASE-FIRST | **New gap** |
| `utility_preference` | Utility Preference | Leasing Terms | JSON | AI-OPTIONAL | **New gap** |
| `renewal_option_requested` | Renewal Option Requested | Leasing Terms | boolean | DATABASE-FIRST | **New gap** |
| `renewal_option_details` | Renewal Details | Leasing Terms | text | AI-REQUIRED | Free text |

#### Commercial Lease Preferences

| Meta Key | Display Label | Tab | Type | Classification | Notes |
|---|---|---|---|---|---|
| `commercial_lease_type_preference` | Commercial Lease Type Pref. | Leasing Terms | select | DATABASE-FIRST | **New gap** — NNN / Gross / Modified |
| `cam_nnn_preference` | CAM/NNN Acceptance | Leasing Terms | select | DATABASE-FIRST | **New gap** |
| `buildout_tenant_improvement_request` | TI/Buildout Request | Leasing Terms | text | AI-REQUIRED | **New gap** — tenant improvement ask |
| `signage_request` | Signage Request | Leasing Terms | text | AI-OPTIONAL | **New gap** |
| `intended_business_use` | Intended Business Use | Leasing Terms | text | AI-REQUIRED | **New gap** — what tenant plans to do |
| `commercial_parking_access_needs` | Parking Access Needs | Leasing Terms | text | AI-OPTIONAL | **New gap** |
| `personal_guarantee_preference` | Personal Guarantee Preference | Leasing Terms | select | DATABASE-FIRST | **New gap** |
| `commercial_approval_conditions` | Approval Conditions | Leasing Terms | text | AI-REQUIRED | Free text |
| `accessibility_requirements` | Accessibility Requirements | Leasing Terms | text | AI-REQUIRED | **New gap** |

#### Business Type

| Meta Key | Display Label | Tab | Type | Classification | Notes |
|---|---|---|---|---|---|
| `business_type` | Business Type | Property Preferences | select | DATABASE-FIRST | **New gap** — restaurant / retail / office |
| `business_type_selected` | Business Type (Selected) | Property Preferences | JSON | AI-OPTIONAL | Companion multi-select |
| `other_business_type` | Business Type (Other) | Property Preferences | text | AI-OPTIONAL | Companion |
| `business_assets` | Required Business Assets | Property Preferences | JSON | AI-OPTIONAL | **New gap** — grease trap / loading dock etc. |

#### Space Requirements (Tenant-side)

| Meta Key | Display Label | Tab | Type | Classification | Notes |
|---|---|---|---|---|---|
| `leasing_spaces` | Leasing Space Types | Property Preferences | JSON | AI-OPTIONAL | **New gap** |
| `leasing_spaces_tenant` | Tenant Space Requirements | Property Preferences | JSON | AI-OPTIONAL | **New gap** |
| `room_size` | Room Size Preference | Property Preferences | text | AI-OPTIONAL | |
| `access_24_7` | 24/7 Access Required | Property Preferences | boolean | DATABASE-FIRST | **New gap** |
| `building_hours` | Building Hours Required | Property Preferences | text | AI-OPTIONAL | |
| `bathroom_facilities` | Bathroom Facilities Needed | Property Preferences | select | AI-OPTIONAL | |
| `common_areas_access` | Common Areas Access | Property Preferences | text | AI-OPTIONAL | |
| `common_areas_cleaning` | Common Areas Cleaning | Property Preferences | select | AI-OPTIONAL | |
| `shared_amenities` | Shared Amenities Needed | Property Preferences | JSON | AI-OPTIONAL | |
| `space_features` | Space Features Required | Property Preferences | JSON | AI-OPTIONAL | |
| `neighboring_tenants` | Neighbor Tenant Preferences | Property Preferences | text | AI-REQUIRED | Free text — who they want nearby |
| `storage_space` | Storage Space Required | Property Preferences | boolean/select | AI-OPTIONAL | |
| `storage_space_res_single` | Single Res. Storage | Property Preferences | select | AI-OPTIONAL | |
| `storage_space_res_both` | Both Res. Storage | Property Preferences | select | AI-OPTIONAL | |
| `storage_space_com_single` | Single Com. Storage | Property Preferences | select | AI-OPTIONAL | |
| `storage_space_com_entire` | Entire Com. Storage | Property Preferences | select | AI-OPTIONAL | |
| `included_storage_space_res_single` | Included Single Storage | Property Preferences | select | AI-OPTIONAL | |
| `included_storage_space_res_both` | Included Both Storage | Property Preferences | select | AI-OPTIONAL | |
| `included_storage_space_com_single` | Included Com. Single | Property Preferences | select | AI-OPTIONAL | |
| `included_storage_space_com_entire` | Included Com. Entire | Property Preferences | select | AI-OPTIONAL | |

#### Tenant Self-Description (RESTRICTED)

`credit_score_range`, `monthly_income`, `prior_eviction`, `eviction_explanation`, `prior_felony`, `prior_felony_explanation`, `screening_concerns`, `screening_concerns_explanation` — All RESTRICTED. Screening-only; never exposed via Ask AI.

`current_status`, `occupancy_status`, `occupant_tenant`, `occupied_until`, `outstanding_balance` — RESTRICTED. Current housing situation; private.

#### Tenant Animal Fields

| Meta Key | Display Label | Tab | Type | Classification | Notes |
|---|---|---|---|---|---|
| `pets` | Has Pets | Pre-Screening | boolean | DATABASE-FIRST | Part of → TEN-FAQ path |
| `pet_information` | Pet Information | Pre-Screening | text | AI-REQUIRED | Free text |
| `service_animal` | Has Service Animal | Pre-Screening | boolean | DATABASE-FIRST | **New gap** |
| `support_animal` | Has Support Animal | Pre-Screening | boolean | DATABASE-FIRST | **New gap** |
| `emotional_support_animal` | Has Emotional Support Animal | Pre-Screening | boolean | DATABASE-FIRST | **New gap** |

#### Tenant Guarantor/Financial Fields (RESTRICTED)

`retained_deposits`, `nominal`, `outstanding_balance`, `pre_approval_amount`, `pre_approved`, `cash_budget` — RESTRICTED or INTERNAL.

#### Tenant Agent/Commission Fields (ALL RESTRICTED)

`seller_broker_leasing_fee`, `seller_leasing_fee_type`, `seller_leasing_gross*` (all variants), `seller_leasing_each_rental`, `tenant_broker_*`, `landlord_broker_*`, `commission_structure_type*`, `expansion_commission_percentage`, `referral_percentage`, `interested_purchase_fee_type`, `purchase_fee_*` (all), `lease_fee_*` (all), `renewal_fee_*` (all), `sales_tax_option_*`, `split_payment_due`, `broker_fee_*`

---

### 17.5 Complete Field Universe Summary

| Role | Total saveMeta Keys (verified) | Already in Audit (Secs 3–6) | Catalogued in Sec 17 (table rows) | Ask AI Eligible (New) | Restricted/Internal/PII |
|---|---|---|---|---|---|
| Seller | **473** (exact, grep-verified) | 83 | ~200 | ~70 new AI-OPTIONAL/DATABASE-FIRST gaps | ~150 RESTRICTED/INTERNAL/PII/MEDIA |
| Buyer | **326** (exact, grep-verified) | 75 | ~61 | ~45 new gaps | ~30 RESTRICTED/INTERNAL |
| Landlord | **512** (exact, grep-verified) | 70 | ~126 | ~90 new AI-OPTIONAL/DATABASE-FIRST gaps | ~125 RESTRICTED/INTERNAL/PII |
| Tenant | **475** (exact, grep-verified) | 51 | ~67 | ~65 new gaps | ~80 RESTRICTED/INTERNAL/PII |
| **Total** | **1,786** (raw sum; unique cross-role keys lower) | **279** | **~454** | **~270 candidate expansion fields** | **~385 properly excluded** |

> **Verification method (exact counts):** `grep -oE "saveMeta\('[^']+'" app/Http/Livewire/OfferListing/Seller/SellerOfferListing.php \| sort -u \| wc -l` (and equivalent for each role file). This extracts unique meta key strings, not total call counts. The raw total of 1,786 overstates unique keys because many keys (e.g. `association_fee_amount`, `parcel_id`, `year_built`) appear in multiple role files; the unique cross-role key count is substantially lower.
>
> **Coverage note:** Section 17 table rows (~454 total) catalog the most significant Ask-AI-eligible and RESTRICTED/INTERNAL field groups. The remaining ~1,300 key occurrences in the raw total are (a) omitted `other_*` companion fields, (b) agent-fee sub-keys already described in bulk via the "ALL RESTRICTED" paragraphs in each section, and (c) keys duplicated across role files. No AI-eligible keys are deliberately omitted from Section 17 coverage.

> **Key finding from the complete inventory:** The original 279-field audit correctly focused on fields that either (a) are already in the context builder or (b) are already in the registry. The ~270 newly catalogued AI-OPTIONAL/DATABASE-FIRST "New gap" fields are **candidates for Phase 5 coverage expansion**. They are correctly absent from the current Ask AI system because the context builder does not extract them — they are not broken, they are simply unregistered. Adding them is a deliberate product decision, not a bug fix.

---

## 18. Question Templates — Primary, Alternates, and Answer Formats

This section provides the complete question template set for every Ask AI eligible field that is either (a) in the current listing field registry or (b) a DATABASE-FIRST structural field. Each entry provides: a primary question, two alternate phrasings, and a standardized answer template. The answer template uses `{value}` placeholders that map to the stored meta key.

**Template format:** Q1 = Primary (used in `sample_question`), Q2 = Alternate 1 (used in `sample_question_2`), Q3 = Alternate 2 (for Phase 5 question index), Answer = format string.

---

### 18.1 Seller Structural Field Templates

| Ask AI ID | Field | Q1 (Primary) | Q2 (Alt 1) | Q3 (Alt 2) | Answer Template | Category |
|---|---|---|---|---|---|---|
| SEL-001 | Address | "What is the address of this listing?" | "Where is this property located?" | "What is the street address for this listing?" | "This property is located at {value}." | listing_facts |
| SEL-002 | Description | "Can you describe this property?" | "What is this listing about?" | "Give me an overview of this property." | "{value}" | listing_facts |
| SEL-003 | Asking Price | "What is the asking price?" | "How much is this property listed for?" | "What is the sale price for this property?" | "The asking price for this property is ${value}." | listing_facts |
| SEL-005 | Bedrooms | "How many bedrooms does this property have?" | "What is the bedroom count?" | "How many bedrooms?" | "This property has {value} bedroom(s)." | listing_facts |
| SEL-006 | Bathrooms | "How many bathrooms does this property have?" | "What is the bathroom count?" | "How many bathrooms?" | "This property has {value} bathroom(s)." | listing_facts |
| SEL-007 | Square Footage | "What is the square footage of this property?" | "How big is this home?" | "What is the heated square footage?" | "The heated square footage is {value} sq ft." | listing_facts |
| SEL-008 | Year Built | "When was this property built?" | "What year was this home constructed?" | "How old is this property?" | "This property was built in {value}." | listing_facts |
| SEL-009 | Property Type | "What type of property is this?" | "Is this a house, condo, or something else?" | "What property category does this listing fall under?" | "This is a {value} property." | listing_facts |
| SEL-010 | Pool | "Does this property have a pool?" | "Is there a swimming pool?" | "Does it come with a pool?" | "Pool: {Yes/No}. {pool_type if present}." | listing_facts |
| SEL-012 | Carport | "Does this property have a carport?" | "Is covered parking (carport) available?" | "Is there a carport at this listing?" | "Carport: {Yes/No}." | listing_facts |
| SEL-013 | Garage | "Does this property have a garage?" | "Is there a garage?" | "Does it include a garage?" | "Garage: {Yes/No}. {garage_spaces} space(s)." | listing_facts |
| SEL-015 | View / Water View | "What are the views from this property?" | "Does this property have a water view?" | "What type of views does this listing offer?" | "View types include: {value_list}." | listing_facts |
| SEL-016 | HOA Flag | "Is there an HOA for this property?" | "Does this listing have a homeowners association?" | "Is this property part of an HOA community?" | "HOA: {Yes/No}." | listing_facts |
| SEL-017 | HOA Fee | "What is the HOA fee?" | "How much are the monthly HOA dues?" | "What does the HOA cost?" | "HOA fee is ${value} per {frequency}." | listing_facts |
| SEL-021 | Pets Allowed | "Are pets allowed at this property?" | "Does the seller have a pet policy?" | "Can I bring my pet?" | "Pets allowed: {Yes/No}. Limit: {number_of_pets}. Max weight: {max_pet_weight} lbs." | listing_facts |
| SEL-025 | Rental Restrictions | "Are there rental restrictions on this property?" | "Can this property be rented out?" | "Does the community have leasing restrictions?" | "Rental restrictions: {value}." | listing_facts |
| SEL-026 | Flood Zone Code | "Is this property in a flood zone?" | "What is the flood zone designation?" | "What is the FEMA flood zone code for this property?" | "Flood zone code: {value}." | listing_facts |
| SEL-027 | Target Closing Date | "When is the preferred closing date?" | "What is the target close date?" | "When does the seller want to close?" | "The seller's preferred closing date is {value}." | listing_facts |
| SEL-028 | Auction Length | "How long is this auction open?" | "When does bidding close for this listing?" | "What is the auction duration?" | "This auction is open for {value} days, closing on {expiration_date}." | listing_facts |
| SEL-029 | Annual Property Taxes | "What are the property taxes?" | "How much are the annual taxes on this property?" | "What is the yearly tax amount?" | "Annual property taxes are approximately ${value} (Tax year: {tax_year})." | listing_facts |

---

### 18.2 Seller FAQ Fields — Question Template Reference

> Note: All 52 Seller FAQ fields have primary and secondary questions stored in `registry()`. Section 18.2 provides the Alt Q3 phrasings for the top-priority FAQ fields. Config keys are the canonical `faq_answers.*` keys from Section 3.2 — every key here must match those entries exactly.

| Ask AI ID | Config Key | Q1 (in Registry) | Q2 (in Registry) | Q3 (Alt 2 — New) | Answer Template |
|---|---|---|---|---|---|
| SEL-FAQ-001 | `roof_age_and_condition` | "How old is the roof and what condition is it in?" | "What type of roof does this home have?" | "When was the roof last replaced?" | "Roof: {value}" |
| SEL-FAQ-002 | `hvac_system_age` | "What type of HVAC system is in this home?" | "How old is the heating and cooling system?" | "When was the AC or furnace last replaced?" | "HVAC: {value}" |
| SEL-FAQ-003 | `water_heater_age_type` | "What type of water heater is in this home?" | "How old is the water heater?" | "Is there a tankless or standard water heater?" | "Water heater: {value}" |
| SEL-FAQ-004 | `recent_renovations_list` | "What recent renovations have been made?" | "Has anything been updated or remodeled?" | "What improvements has the seller made to this property?" | "Renovations: {value}" |
| SEL-FAQ-005 | `permits_for_renovations` | "Were permits pulled for any additions or renovations?" | "Are there open permits on this property?" | "Were renovations done with proper permits?" | "Permits: {value}" |
| SEL-FAQ-006 | `known_defects_issues` | "Are there any known defects or deferred maintenance items?" | "What issues should a buyer know about?" | "Are there any disclosed problems with this property?" | "Known issues: {value}" |
| SEL-FAQ-007 | `foundation_type_and_issues` | "What type of foundation does this home have?" | "Are there any foundation issues?" | "Has there ever been any foundation movement or cracking?" | "Foundation: {value}" |
| SEL-FAQ-008 | `pest_termite_history` | "Is there a history of pest or termite damage?" | "Has this property been treated for pests?" | "Are there any pest or termite disclosure items?" | "Pest history: {value}" |
| SEL-FAQ-009 | `flood_damage_history` | "Has this property ever had flood damage?" | "Is there a history of flooding or water intrusion?" | "Has the property ever flooded or had water damage?" | "Flood history: {value}" |
| SEL-FAQ-010 | `mold_issues_history` | "Is there any history of mold issues?" | "Has mold ever been found or remediated here?" | "Are there any mold disclosures for this property?" | "Mold history: {value}" |
| SEL-FAQ-011 | `average_utility_costs` | "What are the average monthly utility costs?" | "How much do utilities typically run per month?" | "What should I budget for utilities at this property?" | "Utility costs: {value}" |
| SEL-FAQ-012 | `internet_utility_providers` | "Which internet and utility providers serve this property?" | "What internet options are available here?" | "Which companies provide electricity, gas, and internet?" | "Providers: {value}" |
| SEL-FAQ-013 | `seller_concessions_offered` | "Is the seller willing to offer any concessions?" | "Will the seller contribute to closing costs?" | "What credits or concessions is the seller offering?" | "Concessions: {value}" |
| SEL-FAQ-014 | `neighborhood_character` | "How would you describe the character of this neighborhood?" | "What is the feel of the neighborhood?" | "Is this a quiet street or an active area?" | "Neighborhood: {value}" |
| SEL-FAQ-015 | `traffic_or_noise_concerns` | "Are there any traffic or noise concerns near this property?" | "How is the noise level around the property?" | "Is there highway, airport, or commercial noise nearby?" | "Traffic/noise: {value}" |

---

### 18.3 Buyer Structural Field Templates

| Ask AI ID | Field | Q1 (Primary) | Q2 (Alt 1) | Q3 (Alt 2) | Answer Template | Category |
|---|---|---|---|---|---|---|
| BUY-001 | Address / Location | "What area is this buyer looking in?" | "Where does this buyer want to buy?" | "What is the target location for this buyer's search?" | "This buyer is looking in {value}." | buyer_criteria |
| BUY-003 | Max Budget | "What is this buyer's maximum budget?" | "How much is this buyer willing to spend?" | "What is the price ceiling for this buyer?" | "This buyer's maximum budget is ${value}." | buyer_criteria |
| BUY-004 | Bedrooms | "How many bedrooms does this buyer need?" | "What is the minimum bedroom requirement?" | "How many bedrooms is this buyer looking for?" | "This buyer requires at least {value} bedroom(s)." | buyer_criteria |
| BUY-005 | Bathrooms | "How many bathrooms does this buyer need?" | "What is the minimum bathroom requirement?" | "How many bathrooms is the buyer looking for?" | "This buyer requires at least {value} bathroom(s)." | buyer_criteria |
| BUY-006 | Square Footage | "What minimum square footage does this buyer need?" | "How much space is the buyer looking for?" | "What is the minimum heated area the buyer requires?" | "This buyer needs at least {value} sq ft of heated area." | buyer_criteria |
| BUY-007 | Pool | "Does this buyer require a pool?" | "Is a pool a requirement for this buyer?" | "Is the buyer looking for a property with a pool?" | "Pool required: {Yes/No}." | buyer_criteria |
| BUY-008 | Carport | "Does this buyer need a carport?" | "Is covered parking required by this buyer?" | "Is a carport a must-have for this buyer?" | "Carport required: {Yes/No}." | buyer_criteria |
| BUY-009 | Garage | "Does this buyer need a garage?" | "Is a garage required for this buyer?" | "Is a garage a deal-breaker for this buyer?" | "Garage required: {Yes/No}. Spaces: {garage_spaces}." | buyer_criteria |
| BUY-012 | HOA Acceptable | "Is this buyer comfortable with an HOA?" | "Will this buyer consider an HOA community?" | "Does the buyer accept HOA restrictions?" | "HOA acceptance: {Yes/No}. Max HOA fee: ${hoa_max_monthly_fee}/mo." | buyer_criteria |
| BUY-014 | Pets | "Does this buyer have pets?" | "What kind of pets does this buyer have?" | "Are pets a consideration for this buyer's search?" | "Buyer has pets: {Yes/No}. {type_of_pets}. {breed_of_pets}. Weight: {weight_of_pets} lbs." | buyer_criteria |
| BUY-018 | Loan Pre-Approved | "Is this buyer pre-approved for a loan?" | "Has this buyer been pre-approved for financing?" | "What is the buyer's pre-approval status?" | "Pre-approved: {Yes/No}. Financing type: {offered_financing}." | buyer_criteria |
| BUY-019 | Financing Type | "What types of financing is this buyer considering?" | "How is this buyer planning to finance the purchase?" | "What financing methods is the buyer open to?" | "Financing methods: {value_list}." | buyer_criteria |
| BUY-020 | Inspection Period | "What inspection period does this buyer want?" | "How many days is the buyer requesting for inspections?" | "What is the buyer's preferred due diligence period?" | "Preferred inspection period: {value} days." | buyer_criteria |
| BUY-021 | Closing Date | "What is this buyer's target closing date?" | "When does this buyer want to close?" | "What is the buyer's preferred closing timeline?" | "Target closing date: {value}." | buyer_criteria |
| BUY-022 | Inspection Contingency | "Does this buyer require an inspection contingency?" | "Is the inspection contingency a requirement for this buyer?" | "Will the buyer include an inspection contingency?" | "Inspection contingency: {Yes/No}." | buyer_criteria |
| BUY-023 | Appraisal Contingency | "Does this buyer require an appraisal contingency?" | "Will the buyer include an appraisal contingency?" | "Is the appraisal contingency a must for this buyer?" | "Appraisal contingency: {Yes/No}." | buyer_criteria |
| BUY-024 | Financing Contingency | "Does this buyer require a financing contingency?" | "Will the buyer include a mortgage contingency?" | "Is the financing contingency a requirement?" | "Financing contingency: {Yes/No}." | buyer_criteria |
| BUY-025 | Property Type | "What type of property is this buyer looking for?" | "What property category is this buyer searching in?" | "Is the buyer looking for a house, condo, or commercial?" | "This buyer is looking for a {value} property." | buyer_criteria |

---

### 18.4 Landlord Structural Field Templates

> All IDs match the canonical LND-001–LND-031 table in Section 5.1.

| Ask AI ID | Field (Section 5.1) | Q1 (Primary) | Q2 (Alt 1) | Q3 (Alt 2) | Answer Template | Category |
|---|---|---|---|---|---|---|
| LND-001 | Property Address | "What is the address of this rental?" | "Where is this rental property located?" | "What is the street address for this rental listing?" | "This rental property is located at {value}." | listing_facts |
| LND-002 | Property Description | "Can you describe this rental property?" | "What is included in this rental listing?" | "Give me an overview of this rental." | "{value}" | listing_facts |
| LND-003 | Rent Amount | "What is the monthly rent?" | "How much is the rent for this property?" | "What is the asking rental price?" | "The asking rent is ${value} per month." | listing_facts |
| LND-004 | Bedrooms | "How many bedrooms does this rental have?" | "What is the bedroom count?" | "How many bedrooms?" | "This rental has {value} bedroom(s)." | listing_facts |
| LND-005 | Bathrooms | "How many bathrooms does this rental have?" | "What is the bathroom count?" | "How many bathrooms?" | "This rental has {value} bathroom(s)." | listing_facts |
| LND-006 | Square Footage | "What is the square footage of this rental?" | "How large is this rental unit?" | "What is the heated square footage of this rental?" | "The heated square footage is {value} sq ft." | listing_facts |
| LND-007 | Unit Size | "What is the unit size category?" | "Is this a studio, 1BR, or larger unit?" | "How is this unit classified in terms of size?" | "Unit size: {value}." | listing_facts |
| LND-008 | Number of Units | "How many units are in this rental property?" | "Is this a single-unit or multi-unit building?" | "How many rental units does this listing include?" | "{value} unit(s)." | listing_facts |
| LND-009 | Property ZIP | "What is the ZIP code for this rental?" | "What ZIP code is this rental property in?" | "What is the postal code for this listing?" | "ZIP code: {value}." | listing_facts |
| LND-010 | Property Items / Features | "What property features and items are included?" | "What amenities come with this rental?" | "What features does this rental property offer?" | "Included features: {value_list}." | listing_facts |
| LND-011 | Property Condition | "What is the condition of this rental property?" | "Is this rental move-in ready?" | "How would you describe the condition of the property?" | "Property condition: {value}." | listing_facts |
| LND-012 | Appliances | "What appliances are included with this rental?" | "Does this rental come with a washer/dryer or dishwasher?" | "Which appliances are provided?" | "Appliances included: {value_list}." | listing_facts |
| LND-013 | View / Water View | "What are the views from this rental?" | "Does this rental have a water view?" | "What type of views does this rental offer?" | "Views: {value_list}." | listing_facts |
| LND-014 | Pet Policy | "Are pets allowed at this rental?" | "What is the pet policy for this rental?" | "Can I bring my pet to this rental?" | "Pet policy: {value}." | listing_facts |
| LND-015 | Pet Deposit / Fee | "What is the pet deposit or fee?" | "Is there a pet deposit required?" | "How much is the pet fee for this rental?" | "Pet deposit/fee: {value}." | listing_facts |
| LND-016 | Max Pet Weight | "What is the maximum pet weight allowed?" | "Is there a weight limit for pets?" | "How heavy can my pet be?" | "Maximum pet weight: {value} lbs." | listing_facts |
| LND-017 | Pet Species Allowed | "What types of pets are allowed?" | "Are cats and dogs both permitted?" | "What species of animals are accepted?" | "Allowed pet species: {value_list}." | listing_facts |
| LND-018 | Parking Terms | "What are the parking terms for this rental?" | "Does this rental include parking?" | "How many parking spaces come with this rental?" | "Parking: {value}." | listing_facts |
| LND-019 | Utilities Included | "Which utilities are included in the rent?" | "What utilities does the landlord cover?" | "What is included in the monthly rent?" | "Utilities included: {value_list}." | listing_facts |
| LND-020 | Laundry Policy | "What is the laundry situation for this rental?" | "Is there in-unit laundry or shared laundry?" | "Where is the washer/dryer hookup?" | "Laundry: {value}." | listing_facts |
| LND-021 | Lease Term | "What is the lease term for this rental?" | "How long is the minimum lease?" | "What lease length is the landlord offering?" | "Lease term: {value}." | listing_facts |
| LND-022 | Available Date | "When is this rental available?" | "What is the earliest move-in date?" | "When can a tenant move in?" | "Available: {value}." | listing_facts |
| LND-023 | Security Deposit | "What is the security deposit for this rental?" | "How much is the security deposit?" | "Is a security deposit required, and how much?" | "Security deposit: ${value}." | listing_facts |
| LND-024 | Has HOA | "Is there an HOA at this rental property?" | "Does this rental have a homeowners association?" | "Is the property part of an HOA community?" | "HOA: {Yes/No}." | listing_facts |
| LND-025 | Association Fee Amount | "What is the association fee for this property?" | "How much is the monthly HOA fee?" | "What does the HOA charge?" | "Association fee: ${value} per {frequency}." | listing_facts |
| LND-026 | Association Amenities | "What amenities does the HOA community offer?" | "What community amenities come with this rental?" | "Does the association include a pool, gym, or clubhouse?" | "Association amenities: {value_list}." | listing_facts |
| LND-027 | Annual Property Taxes | "What are the property taxes for this rental?" | "How much are the annual taxes?" | "What is the yearly tax amount?" | "Annual taxes: ${value}." | listing_facts |
| LND-028 | Number of Occupants Allowed | "How many occupants are allowed in this rental?" | "What is the maximum occupancy?" | "How many people can live here?" | "Maximum occupants: {value}." | listing_facts |
| LND-029 | Property Type | "What type of property is this rental?" | "Is this a house, condo, or apartment rental?" | "What property category does this rental fall under?" | "This is a {value} rental property." | listing_facts |
| LND-030 | Smoking Policy | "What is the smoking policy for this rental?" | "Is smoking allowed at this property?" | "Can tenants smoke inside or outside?" | "Smoking policy: {value}." | listing_facts |
| LND-031 | Subletting Policy | "Is subletting allowed at this rental?" | "Can tenants sublet this unit?" | "What is the landlord's policy on subletting?" | "Subletting policy: {value}." | listing_facts |

---

### 18.5 Tenant Structural Field Templates

> All IDs match the canonical TEN-001–TEN-024 table in Section 6.1. TEN-024 (Credit Score Range) is RESTRICTED per Section 8 and has no Ask AI template.

| Ask AI ID | Field (Section 6.1) | Q1 (Primary) | Q2 (Alt 1) | Q3 (Alt 2) | Answer Template | Category |
|---|---|---|---|---|---|---|
| TEN-001 | Desired Location / Area | "What areas is this tenant looking to rent in?" | "Where does this tenant want to live?" | "What is the target rental area for this tenant?" | "This tenant is looking to rent in {value}." | buyer_criteria |
| TEN-002 | Additional Requirements | "What additional requirements does this tenant have?" | "Are there any special needs or requirements for this tenant?" | "What else is this tenant looking for beyond the basics?" | "Additional requirements: {value}" | buyer_criteria |
| TEN-003 | Maximum Rent Budget | "What is this tenant's maximum rent budget?" | "How much can this tenant afford in monthly rent?" | "What is the rent ceiling for this tenant?" | "Maximum rent budget: ${value}/month." | buyer_criteria |
| TEN-004 | Bedrooms | "How many bedrooms does this tenant need?" | "What is the minimum bedroom count for this tenant?" | "How many bedrooms is this tenant looking for?" | "This tenant needs at least {value} bedroom(s)." | buyer_criteria |
| TEN-005 | Bathrooms | "How many bathrooms does this tenant need?" | "What is the minimum bathroom count for this tenant?" | "How many bathrooms is this tenant looking for?" | "This tenant needs at least {value} bathroom(s)." | buyer_criteria |
| TEN-006 | Desired Lease Length | "What lease length is this tenant looking for?" | "What lease term does this tenant prefer?" | "How long does this tenant want to rent?" | "Desired lease length: {value_list}." | buyer_criteria |
| TEN-007 | Desired Lease Length (scalar) | "What is this tenant's preferred lease term?" | "Is this tenant looking for a month-to-month or annual lease?" | "What lease duration does this tenant need?" | "Preferred lease term: {value}." | buyer_criteria |
| TEN-008 | Pets | "Does this tenant have pets?" | "What kind of pets does this tenant have?" | "What pets will be coming to the rental?" | "Tenant has pets: {Yes/No}. {type_of_pets}. {breed_of_pets}. Weight: {weight_of_pets} lbs." | buyer_criteria |
| TEN-009 | Pet Species | "What type of pets does this tenant have?" | "Are the tenant's pets cats, dogs, or other animals?" | "What species are the tenant's pets?" | "Pet species: {value_list}." | buyer_criteria |
| TEN-010 | Parking Needed | "Does this tenant need parking?" | "Is parking a requirement for this tenant?" | "How many parking spots does this tenant need?" | "Parking needed: {value}." | buyer_criteria |
| TEN-011 | Move-In Date | "When does this tenant want to move in?" | "What is this tenant's target move-in date?" | "When is the tenant ready to move?" | "Target move-in: {value}." | buyer_criteria |
| TEN-012 | Max HOA Fee Acceptable | "What is this tenant's maximum acceptable HOA fee?" | "Is this tenant willing to pay an HOA fee, and how much?" | "What HOA fee amount is acceptable to this tenant?" | "Max HOA fee: ${value}/month." | buyer_criteria |
| TEN-013 | Property Items Desired | "What property features does this tenant need?" | "What amenities is this tenant looking for?" | "What must-have property items does this tenant require?" | "Desired property items: {value_list}." | buyer_criteria |
| TEN-014 | Appliances Needed | "What appliances does this tenant need?" | "Is the tenant looking for a property with a washer/dryer or dishwasher?" | "Which appliances are required by this tenant?" | "Required appliances: {value_list}." | buyer_criteria |
| TEN-015 | Property Condition Acceptable | "What property condition is acceptable to this tenant?" | "Does this tenant require a move-in ready property?" | "Is this tenant okay with a fixer-upper?" | "Acceptable condition: {value}." | buyer_criteria |
| TEN-016 | Pet Information | "What is the tenant's pet information?" | "Can you tell me more about the tenant's pets?" | "What should a landlord know about the tenant's animals?" | "Pet information: {value}." | buyer_criteria |
| TEN-017 | Utility Preference | "What are this tenant's utility preferences?" | "Does this tenant prefer utilities included or separate?" | "How does this tenant prefer utilities to be handled?" | "Utility preference: {value}." | buyer_criteria |
| TEN-018 | Utilities (desired) | "Which utilities does this tenant need?" | "What utilities is this tenant looking for?" | "What utility services does this tenant require?" | "Desired utilities: {value_list}." | buyer_criteria |
| TEN-019 | Tenant Pays | "What utilities is this tenant willing to pay?" | "Which utilities does this tenant expect to cover?" | "What costs is the tenant prepared to pay directly?" | "Tenant pays: {value_list}." | buyer_criteria |
| TEN-020 | Current Status | "What is this tenant's current housing status?" | "Is this tenant currently renting or owning?" | "What is the tenant's current living situation?" | "Current status: {value}." | buyer_criteria |
| TEN-021 | Number of Occupants | "How many people will be living in the rental?" | "What is the total number of occupants?" | "How many occupants will this tenant have?" | "Number of occupants: {value}." | buyer_criteria |
| TEN-022 | Number of Units | "How many units does this tenant need?" | "Is this tenant looking for a single unit or multiple?" | "How many rental units does this tenant require?" | "Units needed: {value}." | buyer_criteria |
| TEN-023 | Property Type | "What type of rental property is this tenant looking for?" | "Is this tenant looking for a house, condo, or apartment?" | "What property category matches this tenant's needs?" | "Property type: {value}." | buyer_criteria |
| TEN-024 | Credit Score Range | *RESTRICTED — Section 8. Not exposed via Ask AI.* | — | — | — | RESTRICTED |

---

### 18.7 Seller FAQ Fields — Remaining Base (SEL-FAQ-016 to SEL-FAQ-033)

> Continues from Section 18.2. Q1 is the primary question stored in `registry()`.

| Ask AI ID | Config Key | Q1 (Registry) | Q2 (Alt 1) | Q3 (Alt 2) | Answer Template | Category |
|---|---|---|---|---|---|---|
| SEL-FAQ-016 | `planned_nearby_development` | "Is there any planned development nearby?" | "Are there any construction or development projects expected in this area?" | "What future development is planned near this property?" | "{value}" | listing_facts |
| SEL-FAQ-017 | `commute_options_access` | "What are the commute options from this location?" | "How easy is it to commute from this address?" | "What public transit or highway access is nearby?" | "{value}" | listing_facts |
| SEL-FAQ-018 | `natural_light_orientation` | "What is the orientation and how is the natural light?" | "Does this home get good natural light?" | "Which direction does the home face?" | "{value}" | listing_facts |
| SEL-FAQ-019 | `nearby_amenities_description` | "What amenities are nearby?" | "What shops, restaurants, and parks are close by?" | "What can I walk to from this property?" | "{value}" | listing_facts |
| SEL-FAQ-020 | `neighborhood_restrictions` | "Are there any neighborhood restrictions?" | "Are there deed restrictions or HOA rules in this community?" | "What restrictions apply to this property beyond standard HOA rules?" | "{value}" | listing_facts |
| SEL-FAQ-021 | `closing_timeline_flexibility` | "How flexible is the seller on the closing timeline?" | "Can the seller accommodate a fast or delayed close?" | "Is the seller willing to work with the buyer's preferred closing date?" | "{value}" | listing_facts |
| SEL-FAQ-022 | `seller_leaseback_option` | "Would the seller consider a leaseback arrangement?" | "Is a post-closing leaseback available?" | "Can the seller stay in the home for a period after closing?" | "{value}" | listing_facts |
| SEL-FAQ-023 | `items_excluded_from_sale` | "Are there any items excluded from the sale?" | "What personal property is not included in the purchase?" | "Which fixtures, appliances, or items does the seller plan to take?" | "{value}" | listing_facts |
| SEL-FAQ-024 | `furniture_negotiability` | "Is furniture negotiable or available for purchase?" | "Can I buy the furniture with the home?" | "Is the seller open to selling furnishings separately?" | "{value}" | listing_facts |
| SEL-FAQ-025 | `as_is_condition` | "Is this property being sold as-is?" | "Will the seller make repairs or is it as-is only?" | "Is this an as-is sale with no repair credits?" | "{value}" | listing_facts |
| SEL-FAQ-026 | `environmental_concerns` | "Are there any environmental concerns with this property?" | "Are there any lead, asbestos, or contamination issues?" | "Is there an environmental disclosure for this property?" | "{value}" | listing_facts |
| SEL-FAQ-027 | `unique_selling_points` | "What makes this property stand out?" | "What is the most compelling reason to buy this home?" | "What are the top three things that set this listing apart?" | "{value}" | listing_facts |
| SEL-FAQ-028 | `seller_favorite_features` | "What are the seller's favorite features of this home?" | "What does the seller love most about this property?" | "If the seller could keep one thing, what would it be?" | "{value}" | listing_facts |
| SEL-FAQ-029 | `seller_motivation_for_selling` | "What is the seller's motivation for selling?" | "Why is the seller selling this property now?" | "Is the seller motivated or flexible on price?" | "{value}" | listing_facts |
| SEL-FAQ-030 | `move_in_ready_status` | "Is this home move-in ready?" | "Does this property need work before moving in?" | "Can a buyer move in immediately after closing?" | "{value}" | listing_facts |
| SEL-FAQ-031 | `parking_arrangements` | "What are the parking arrangements?" | "How many vehicles can be parked at this property?" | "Is there a driveway, garage, or street parking?" | "{value}" | listing_facts |
| SEL-FAQ-032 | `storage_space_available` | "What storage space is available?" | "Does this property have extra storage?" | "Is there an attic, basement, or shed for storage?" | "{value}" | listing_facts |
| SEL-FAQ-033 | `hoa_community_highlights` | "What are the highlights of the HOA community?" | "What amenities does the HOA community offer?" | "What makes this HOA community desirable?" | "{value}" | listing_facts |

---

### 18.8 Seller Add-On FAQ Templates (Commercial Income, Business, Vacant Land)

| Ask AI ID | Config Key | Q1 (Registry) | Q2 (Alt 1) | Q3 (Alt 2) | Answer Template | Category |
|---|---|---|---|---|---|---|
| SEL-CI-001 | `annual_net_operating_income` | "What is the annual net operating income for this property?" | "What does this property generate in net operating income per year?" | "Can you provide the NOI for this investment property?" | "Annual NOI: {value}" | listing_facts |
| SEL-CI-002 | `current_cap_rate` | "What is the current cap rate for this property?" | "What capitalization rate does this property yield?" | "What is the cap rate based on the current asking price?" | "Cap rate: {value}" | listing_facts |
| SEL-CI-003 | `existing_tenant_lease_terms` | "What are the existing tenant lease terms?" | "Are there tenants in place, and what are their lease terms?" | "When do the current tenant leases expire?" | "{value}" | listing_facts |
| SEL-CI-004 | `current_occupancy_rate` | "What is the current occupancy rate?" | "How many units are currently occupied?" | "What percentage of this property is currently leased?" | "Occupancy rate: {value}" | listing_facts |
| SEL-CI-005 | `annual_operating_expenses_detail` | "What are the annual operating expenses for this property?" | "Can you break down the operating costs for this investment?" | "What does it cost annually to operate this property?" | "{value}" | listing_facts |
| SEL-CI-006 | `value_add_opportunities` | "What value-add opportunities exist for this property?" | "How can an investor increase the value of this property?" | "What improvements or strategies could boost returns?" | "{value}" | listing_facts |
| SEL-BIZ-001 | `annual_business_revenue` | "What is the annual business revenue?" | "How much does this business generate in annual revenue?" | "What are the gross annual sales for this business?" | "Annual revenue: {value}" | listing_facts |
| SEL-BIZ-002 | `annual_net_profit` | "What is the annual net profit of this business?" | "How much does this business net per year after expenses?" | "What is the owner's discretionary earnings?" | "Annual net profit: {value}" | listing_facts |
| SEL-BIZ-003 | `business_reason_for_selling` | "What is the reason for selling this business?" | "Why is the owner selling this business?" | "Is the seller motivated, and what is driving the sale?" | "{value}" | listing_facts |
| SEL-BIZ-004 | `business_employee_count` | "How many employees does this business have?" | "What is the current headcount for this business?" | "How many staff members would transfer with this business?" | "Employees: {value}" | listing_facts |
| SEL-BIZ-005 | `seller_training_transition` | "Does the seller offer training and transition support?" | "Will the seller stay on to train the new owner?" | "What transition assistance is the seller providing?" | "{value}" | listing_facts |
| SEL-BIZ-006 | `business_lease_status` | "What is the status of the business lease?" | "Is the business location leased, and what are the terms?" | "How long is remaining on the commercial lease?" | "{value}" | listing_facts |
| SEL-BIZ-007 | `inventory_equipment_included` | "What inventory and equipment is included in the sale?" | "Is business inventory and equipment part of this sale?" | "What assets transfer with the business purchase?" | "{value}" | listing_facts |
| SEL-VL-001 | `land_utilities_availability` | "What utilities are available at this land?" | "Does this land have water, sewer, and electricity access?" | "Are utilities at the lot line or nearby?" | "{value}" | listing_facts |
| SEL-VL-002 | `land_zoning_permitted_uses` | "What is the zoning and what uses are permitted?" | "What can I build on this land under current zoning?" | "What zoning designation does this parcel have?" | "{value}" | listing_facts |
| SEL-VL-003 | `land_access_and_road` | "How is this land accessed, and what is the road status?" | "Does this parcel have road frontage or deeded access?" | "Is there a paved road or easement to this land?" | "{value}" | listing_facts |
| SEL-VL-004 | `land_soil_and_topography` | "What are the soil conditions and topography of this land?" | "Is this land flat or sloped, and what is the soil type?" | "Are there any soil or drainage issues on this parcel?" | "{value}" | listing_facts |
| SEL-VL-005 | `land_survey_available` | "Is a survey available for this land?" | "Has this parcel been recently surveyed?" | "Can I get a copy of the property survey?" | "{value}" | listing_facts |
| SEL-VL-006 | `land_development_restrictions` | "Are there any development restrictions on this land?" | "What deed restrictions or easements apply to this parcel?" | "Are there wetlands, conservation easements, or deed restrictions?" | "{value}" | listing_facts |

---

### 18.9 Buyer FAQ Fields — Full Template Reference

> All 50 Buyer FAQ fields route via `match_criteria`. Q1 is the primary question stored in `registry()`.

| Ask AI ID | Config Key | Q1 (Registry) | Q2 (Alt 1) | Q3 (Alt 2) | Answer Template | Category |
|---|---|---|---|---|---|---|
| BUY-FAQ-001 | `buyer_motivation` | "What's driving your decision to buy right now?" | "What is motivating this buyer to purchase?" | "Why is this buyer in the market at this time?" | "{value}" | buyer_criteria |
| BUY-FAQ-002 | `buyer_lifestyle_goals` | "How do you envision using this property?" | "What lifestyle is this buyer looking to create with this home?" | "How does this property fit into the buyer's vision?" | "{value}" | buyer_criteria |
| BUY-FAQ-003 | `buyer_deal_breakers` | "What are your absolute deal-breakers?" | "What would make this buyer walk away from a property?" | "What features or conditions are non-negotiable no-gos?" | "{value}" | buyer_criteria |
| BUY-FAQ-004 | `buyer_renovation_tolerance` | "Would you consider a property that needs work?" | "Is the buyer open to a fixer-upper?" | "How much renovation is this buyer willing to take on?" | "{value}" | buyer_criteria |
| BUY-FAQ-005 | `buyer_wfh_needs` | "Do you work from home? What is your ideal home office setup?" | "Does this buyer need a dedicated home office space?" | "What are the buyer's remote work requirements?" | "{value}" | buyer_criteria |
| BUY-FAQ-006 | `buyer_outdoor_space` | "How important is outdoor space?" | "Does this buyer need a yard, patio, or garden?" | "What outdoor features does this buyer prioritize?" | "{value}" | buyer_criteria |
| BUY-FAQ-007 | `buyer_long_term_goals` | "Is this a forever home, starter home, or investment?" | "How long does this buyer plan to own this property?" | "What are the buyer's long-term ownership plans?" | "{value}" | buyer_criteria |
| BUY-FAQ-008 | `buyer_biggest_concern` | "What's your biggest concern about this purchase?" | "What worries this buyer most about buying right now?" | "What risk or issue is top of mind for this buyer?" | "{value}" | buyer_criteria |
| BUY-FAQ-009 | `buyer_neighborhood_preferences` | "What kind of neighborhood feel are you looking for?" | "Does this buyer want urban, suburban, or rural?" | "What neighborhood character matches this buyer's lifestyle?" | "{value}" | buyer_criteria |
| BUY-FAQ-010 | `buyer_school_district` | "Is a specific school district a hard requirement?" | "Does this buyer have school district requirements?" | "Are schools a primary factor in this buyer's search?" | "{value}" | buyer_criteria |
| BUY-FAQ-011 | `buyer_commute_requirements` | "Do you have commute distance requirements?" | "How far is this buyer willing to commute?" | "Does this buyer need proximity to a specific workplace?" | "{value}" | buyer_criteria |
| BUY-FAQ-012 | `buyer_noise_tolerance` | "How sensitive are you to noise?" | "Is this buyer looking for a quiet area?" | "Does traffic or neighborhood noise matter to this buyer?" | "{value}" | buyer_criteria |
| BUY-FAQ-013 | `buyer_area_familiarity` | "How familiar are you with the neighborhoods you're considering?" | "Has this buyer visited the target area?" | "Is the buyer relocating and unfamiliar with the area?" | "{value}" | buyer_criteria |
| BUY-FAQ-014 | `buyer_prefers_off_market` | "Are you open to off-market listings?" | "Is this buyer willing to consider off-market properties?" | "Would the buyer make an offer on an unlisted property?" | "{value}" | buyer_criteria |
| BUY-FAQ-015 | `buyer_property_style` | "Do you have an architectural style preference?" | "Is the buyer looking for a specific home style?" | "Does the buyer prefer modern, traditional, or Mediterranean?" | "{value}" | buyer_criteria |
| BUY-FAQ-016 | `buyer_must_have_features` | "What are your absolute must-have property features?" | "What features are non-negotiable for this buyer?" | "What must a property have for this buyer to consider it?" | "{value}" | buyer_criteria |
| BUY-FAQ-017 | `buyer_nice_to_have` | "What features are on your wish list but not deal-breakers?" | "What extras would make this buyer very happy?" | "What desirable-but-not-required features is this buyer hoping for?" | "{value}" | buyer_criteria |
| BUY-FAQ-018 | `buyer_hoa_acceptable` | "Are you comfortable with an HOA community?" | "Is this buyer open to living in an HOA-governed community?" | "Would HOA rules be a barrier for this buyer?" | "{value}" | buyer_criteria |
| BUY-FAQ-019 | `buyer_accessibility` | "Do you need any accessibility features?" | "Does this buyer require ADA or mobility accessibility?" | "Are wheelchair ramps, wide doors, or other features needed?" | "{value}" | buyer_criteria |
| BUY-FAQ-020 | `buyer_privacy_requirements` | "Do you have specific privacy needs?" | "Is this buyer looking for a private or gated property?" | "How important is privacy and seclusion to this buyer?" | "{value}" | buyer_criteria |
| BUY-FAQ-021 | `buyer_view_preference` | "Is a specific view important to you?" | "Does this buyer want a water, golf, or city view?" | "How much does a premium view matter to this buyer?" | "{value}" | buyer_criteria |
| BUY-FAQ-022 | `buyer_current_situation` | "What's your current living situation?" | "Is this buyer currently renting, owning, or relocating?" | "Does the buyer need to sell a home before closing?" | "{value}" | buyer_criteria |
| BUY-FAQ-023 | `buyer_simultaneous_close` | "Do you need to sell a current property simultaneously?" | "Is a simultaneous close required for this buyer?" | "Does the buyer need to close on a sale and a purchase at the same time?" | "{value}" | buyer_criteria |
| BUY-FAQ-024 | `buyer_leaseback` | "Would you allow the seller to stay on a short leaseback?" | "Is this buyer open to granting the seller a leaseback?" | "Can the seller remain in the home for a period after closing?" | "{value}" | buyer_criteria |
| BUY-FAQ-025 | `buyer_relocation` | "Are you relocating from another area?" | "Is this buyer moving from out of state or a different city?" | "Is this a relocation purchase with a deadline?" | "{value}" | buyer_criteria |
| BUY-FAQ-026 | `buyer_lost_deal` | "Have you made offers that didn't work out?" | "Has this buyer lost a previous deal?" | "What has this buyer's offer experience been like?" | "{value}" | buyer_criteria |
| BUY-FAQ-027 | `buyer_seller_concessions` | "Would you consider asking for seller concessions?" | "Is this buyer looking for closing cost contributions?" | "Would the buyer factor seller credits into their offer strategy?" | "{value}" | buyer_criteria |
| BUY-FAQ-028 | `buyer_flexibility` | "How flexible are you on location, timing, or property type?" | "Is this buyer willing to adjust criteria to find the right property?" | "How much flexibility does this buyer have in their search?" | "{value}" | buyer_criteria |
| BUY-COM-001 | `com_property_use` | "What type of commercial use is this buyer seeking?" | "Is this buyer looking for office, retail, or industrial space?" | "What is the intended commercial use for this property?" | "{value}" | buyer_criteria |
| BUY-COM-002 | `com_investment_type` | "What type of commercial investment is this buyer targeting?" | "Is this buyer seeking NNN, multi-tenant, or value-add commercial?" | "What commercial investment strategy does this buyer prefer?" | "{value}" | buyer_criteria |
| BUY-COM-003 | `com_cap_rate_target` | "What cap rate is this buyer targeting?" | "What minimum cap rate will this buyer accept?" | "What return does this buyer expect on a commercial investment?" | "{value}" | buyer_criteria |
| BUY-COM-004 | `com_occupancy_rate` | "What occupancy rate does this buyer require?" | "What minimum occupancy will this buyer consider?" | "Does this buyer want a fully leased property?" | "{value}" | buyer_criteria |
| BUY-COM-005 | `com_lease_terms` | "What lease terms is this buyer looking for in a commercial property?" | "Does this buyer want long-term NNN leases in place?" | "What lease structure does this buyer require from existing tenants?" | "{value}" | buyer_criteria |
| BUY-COM-006 | `com_1031_exchange` | "Is this buyer doing a 1031 exchange?" | "Is timing a factor due to a 1031 exchange requirement?" | "Does this buyer need a property that qualifies for 1031?" | "{value}" | buyer_criteria |
| BUY-COM-007 | `com_due_diligence_period` | "What due diligence period does this buyer need?" | "How long does this buyer need for commercial due diligence?" | "What inspection and review period is this buyer requesting?" | "{value}" | buyer_criteria |
| BUY-COM-008 | `com_environmental_concerns` | "Does this buyer have environmental concerns about commercial properties?" | "Is the buyer requiring Phase I or Phase II environmental reports?" | "What environmental review does this buyer expect?" | "{value}" | buyer_criteria |
| BUY-BIZ-001 | `biz_type_seeking` | "What type of business is this buyer seeking to acquire?" | "What industry or business category is this buyer targeting?" | "Is this buyer looking for a franchise, independent, or niche business?" | "{value}" | buyer_criteria |
| BUY-BIZ-002 | `biz_revenue_required` | "What minimum revenue does this buyer require?" | "What annual sales floor is this buyer looking for in a business?" | "What revenue threshold must a business meet for this buyer?" | "{value}" | buyer_criteria |
| BUY-BIZ-003 | `biz_profit_required` | "What minimum profit does this buyer require?" | "What is the minimum net income this buyer will accept?" | "What owner's discretionary earnings does this buyer need?" | "{value}" | buyer_criteria |
| BUY-BIZ-004 | `biz_training_expected` | "Does this buyer expect seller training post-sale?" | "How long a transition period is this buyer expecting?" | "What training and handoff does this buyer require from the seller?" | "{value}" | buyer_criteria |
| BUY-BIZ-005 | `biz_staff_included` | "Does this buyer need staff to remain with the business?" | "Is retaining current employees important to this buyer?" | "What are this buyer's staffing expectations after acquisition?" | "{value}" | buyer_criteria |
| BUY-BIZ-006 | `biz_non_compete` | "Does this buyer require a non-compete from the seller?" | "What non-compete terms is this buyer expecting?" | "Will this buyer require a non-solicitation or non-compete agreement?" | "{value}" | buyer_criteria |
| BUY-BIZ-007 | `biz_sba_financing` | "Is this buyer planning to use SBA financing?" | "Will this buyer need an SBA-qualified business?" | "Is SBA loan eligibility a requirement for this business purchase?" | "{value}" | buyer_criteria |
| BUY-LND-001 | `land_intended_use` | "What is this buyer's intended use for the land?" | "What does this buyer plan to build or do with the parcel?" | "Is this buyer looking to develop, farm, or hold the land?" | "{value}" | buyer_criteria |
| BUY-LND-002 | `land_zoning_required` | "What zoning does this buyer require for the land?" | "What zoning designation must this parcel have?" | "Is specific zoning approval required before this buyer can proceed?" | "{value}" | buyer_criteria |
| BUY-LND-003 | `land_utilities_needed` | "What utilities does this buyer need at the land?" | "Does this buyer require water, sewer, and electric at the lot?" | "What utility infrastructure must be in place for this buyer?" | "{value}" | buyer_criteria |
| BUY-LND-004 | `land_soil_testing` | "Does this buyer require soil testing?" | "Is a percolation or soil test needed before this buyer will proceed?" | "What soil or environmental testing does this buyer expect?" | "{value}" | buyer_criteria |
| BUY-LND-005 | `land_build_timeline` | "What is this buyer's timeline to build on this land?" | "How soon does this buyer plan to begin construction?" | "Is this buyer buying to build now or hold for future development?" | "{value}" | buyer_criteria |
| BUY-LND-006 | `land_access_requirements` | "What access requirements does this buyer have for the land?" | "Does the land need road frontage or a deeded easement?" | "What type of access to the parcel does this buyer require?" | "{value}" | buyer_criteria |
| BUY-LND-007 | `land_topography` | "What topography requirements does this buyer have?" | "Does this buyer need flat land or is slope acceptable?" | "Are there topography or grade restrictions for this buyer's plans?" | "{value}" | buyer_criteria |

---

### 18.10 Landlord FAQ Fields — Full Template Reference

> All entries are `pinned` route status. Q1 is the primary question stored in `registry()`.

| Ask AI ID | Config Key | Q1 (Registry) | Q2 (Alt 1) | Q3 (Alt 2) | Answer Template | Category |
|---|---|---|---|---|---|---|
| LND-FAQ-001 | `maintenance_request_response_time` | "What is the typical maintenance response time?" | "How quickly does the landlord respond to maintenance issues?" | "How long does it take to get a repair done at this property?" | "{value}" | listing_facts |
| LND-FAQ-002 | `emergency_maintenance_available` | "Is emergency maintenance available after hours?" | "Is there after-hours emergency maintenance?" | "Can tenants reach someone for urgent repairs at night or on weekends?" | "{value}" | listing_facts |
| LND-FAQ-003 | `heating_cooling_system` | "What type of heating and cooling system is in this property?" | "What HVAC system does this rental have?" | "Is the heating and cooling central, split, or window unit?" | "{value}" | listing_facts |
| LND-FAQ-004 | `laundry_situation` | "Is there in-unit laundry or shared laundry facilities?" | "Where is the washer/dryer or laundry hookup?" | "Does this rental include laundry, or is it shared?" | "{value}" | listing_facts |
| LND-FAQ-005 | `storage_area_included` | "Is storage included with this rental?" | "Does this rental come with a storage unit or area?" | "Is there extra storage space available with this rental?" | "{value}" | listing_facts |
| LND-FAQ-006 | `internet_providers` | "Which internet providers are available at this property?" | "What internet options does this rental have?" | "What ISPs serve this address?" | "{value}" | listing_facts |
| LND-FAQ-007 | `security_features` | "What security features does this property have?" | "Is there a security system, cameras, or gated access?" | "What measures are in place to secure this rental property?" | "{value}" | listing_facts |
| LND-FAQ-008 | `planned_renovations` | "Are there any planned renovations or construction?" | "Is the landlord planning any updates or improvements?" | "Will there be construction or disruption during the lease term?" | "{value}" | listing_facts |
| LND-FAQ-009 | `noise_levels` | "What are the typical noise levels around this property?" | "Is this a quiet rental area or is there traffic noise?" | "How would you describe the noise environment for this rental?" | "{value}" | listing_facts |
| LND-FAQ-010 | `nearby_amenities` | "What amenities are nearby?" | "What shops, parks, and restaurants are close to this rental?" | "What can a tenant walk to or easily reach from this address?" | "{value}" | listing_facts |
| LND-FAQ-011 | `guest_parking` | "Is guest parking available?" | "Where do visitors park when visiting this rental?" | "Does this property have dedicated guest parking spaces?" | "{value}" | listing_facts |
| LND-FAQ-012 | `proximity_to_public_transit` | "How close is this property to public transit?" | "Is there a bus stop or train station near this rental?" | "What public transportation options are near this property?" | "{value}" | listing_facts |
| LND-FAQ-013 | `furnished_or_unfurnished` | "Is this rental furnished or unfurnished?" | "Does this rental come with furniture?" | "What furnishings are included in the rent?" | "{value}" | listing_facts |
| LND-FAQ-014 | `lease_renewal_process` | "What is the lease renewal process?" | "How does a tenant renew their lease at this property?" | "What are the steps and notice requirements to renew this lease?" | "{value}" | listing_facts |
| LND-FAQ-015 | `notice_to_vacate_required` | "How much notice is required to vacate?" | "What advance notice must a tenant give before moving out?" | "Is 30 or 60 days notice required to vacate this rental?" | "{value}" | listing_facts |
| LND-FAQ-016 | `preferred_tenant_qualities` | "What qualities does the landlord look for in a tenant?" | "What type of tenant is this landlord seeking?" | "What makes an ideal renter for this property?" | "{value}" | listing_facts |
| LND-FAQ-017 | `subletting_allowed` | "Is subletting allowed?" | "Can a tenant sublet this rental unit?" | "What is the policy on subletting or subleasing?" | "{value}" | listing_facts |
| LND-FAQ-018 | `short_term_rentals_allowed` | "Are short-term rentals (e.g., Airbnb) allowed?" | "Can the tenant list this unit on Airbnb or VRBO?" | "Is short-term rental hosting permitted at this property?" | "{value}" | listing_facts |
| LND-FAQ-019 | `ev_charging_available` | "Is EV charging available?" | "Is there an EV charger or outlet for electric vehicles?" | "Can a tenant charge an electric vehicle at this property?" | "{value}" | listing_facts |
| LND-FAQ-020 | `bicycle_storage_available` | "Is there bicycle storage?" | "Does this property offer secure bike storage?" | "Where can tenants store bicycles at this property?" | "{value}" | listing_facts |
| LND-FAQ-021 | `what_makes_property_unique` | "What makes this rental stand out?" | "What is the most compelling feature of this rental?" | "Why should a prospective tenant choose this property?" | "{value}" | listing_facts |
| LND-FAQ-022 | `pest_or_mold_history` | "Is there any history of pests or mold?" | "Has this property had pest infestations or mold issues?" | "Are there any pest or mold disclosures for this rental?" | "{value}" | listing_facts |
| LND-FAQ-023 | `utilities_individually_metered` | "Are utilities individually metered?" | "Does each unit have its own meter, or are utilities shared?" | "How are utility costs measured and allocated at this property?" | "{value}" | listing_facts |
| LND-FAQ-024 | `renters_insurance_required` | "Is renters insurance required?" | "Does the landlord require tenants to carry renters insurance?" | "Is proof of renters insurance needed to sign the lease?" | "{value}" | listing_facts |
| LND-FAQ-025 | `lease_to_own_option` | "Is a lease-to-own arrangement available?" | "Does the landlord offer a rent-to-own option?" | "Can a tenant purchase this property after renting?" | "{value}" | listing_facts |
| LND-FAQ-026 | `previous_tenant_feedback` | "How have previous tenants described living here?" | "What do past renters say about this property?" | "Is there any tenant feedback about this landlord or property?" | "{value}" | listing_facts |
| LND-COM-001 | `commercial_cam_charges` | "What are the CAM charges for this commercial property?" | "What common area maintenance fees apply to this lease?" | "How much are the monthly CAM charges?" | "{value}" | listing_facts |
| LND-COM-002 | `commercial_lease_structure_type` | "What is the commercial lease structure type?" | "Is this a gross, net, NNN, or modified gross lease?" | "What type of commercial lease is being offered?" | "{value}" | listing_facts |
| LND-COM-003 | `commercial_tenant_improvement_allowance` | "Is a tenant improvement allowance available?" | "What TI allowance is the landlord offering?" | "How much is the tenant improvement budget for this space?" | "{value}" | listing_facts |
| LND-COM-004 | `commercial_buildout_flexibility` | "How flexible is the landlord on buildout?" | "Will the landlord allow custom buildout of this commercial space?" | "Is the landlord open to tenant modifications or improvements?" | "{value}" | listing_facts |
| LND-COM-005 | `commercial_signage_rights` | "What are the signage rights for this commercial space?" | "Can a tenant put exterior signage on this building?" | "What signage is permitted at this commercial location?" | "{value}" | listing_facts |
| LND-COM-006 | `commercial_loading_dock_freight_elevator` | "Is there a loading dock or freight elevator?" | "Does this commercial space have loading or freight access?" | "Can large deliveries be accommodated at this property?" | "{value}" | listing_facts |
| LND-COM-007 | `commercial_electrical_capacity` | "What is the electrical capacity for this commercial space?" | "How many amps does this commercial unit support?" | "Is the electrical service sufficient for manufacturing or heavy equipment?" | "{value}" | listing_facts |
| LND-COM-008 | `commercial_parking_ratio` | "What is the parking ratio for this commercial property?" | "How many parking spaces per square foot are available?" | "Is the parking ratio adequate for this commercial use?" | "{value}" | listing_facts |
| LND-COM-009 | `commercial_exclusivity_rights` | "Are there exclusivity rights available for this commercial space?" | "Can a tenant negotiate an exclusive use clause?" | "Will the landlord prevent competing businesses in the building?" | "{value}" | listing_facts |
| LND-COM-010 | `commercial_expansion_option_rofr` | "Is there a right of first refusal or expansion option?" | "Can the tenant expand into adjacent space?" | "Is there an ROFR or expansion option in the lease?" | "{value}" | listing_facts |
| LND-COM-011 | `commercial_landlord_maintenance_responsibilities` | "What maintenance is the landlord responsible for?" | "What does the landlord cover in terms of building maintenance?" | "What repairs and upkeep does the landlord handle vs. the tenant?" | "{value}" | listing_facts |
| LND-COM-012 | `commercial_building_access_hours` | "What are the building access hours?" | "When can tenants access this commercial building?" | "Are there after-hours or 24/7 access options for this space?" | "{value}" | listing_facts |

---

### 18.11 Tenant FAQ Fields — Full Template Reference

> All 27 entries are `pinned` route status. Opaque config keys (`faq_q1`–`faq_q27`) are listed with their current labels. Q1 is the current registry question label.

| Ask AI ID | Config Key | Q1 (Registry Label) | Q2 (Alt 1) | Q3 (Alt 2) | Answer Template | Category |
|---|---|---|---|---|---|---|
| TEN-FAQ-001 | `faq_q1` | "Do you work from home or need a dedicated work space?" | "Does this tenant work remotely and need a home office?" | "Is a dedicated workspace a must-have for this tenant?" | "{value}" | buyer_criteria |
| TEN-FAQ-002 | `faq_q2` | "What does your daily routine at home look like?" | "How does this tenant typically spend their time at home?" | "What daily activities shape this tenant's space requirements?" | "{value}" | buyer_criteria |
| TEN-FAQ-003 | `faq_q3` | "Do you prefer a walkable neighborhood or a quieter area?" | "Is this tenant looking for walkability or privacy?" | "Does this tenant want city convenience or suburban quiet?" | "{value}" | buyer_criteria |
| TEN-FAQ-004 | `faq_q4` | "Can you tolerate living near a busy street?" | "Is street noise acceptable to this tenant?" | "Would a property on a main road work for this tenant?" | "{value}" | buyer_criteria |
| TEN-FAQ-005 | `faq_q5` | "Are specific amenities (laundry, parking) important enough to affect rent?" | "Would this tenant pay more for in-unit laundry or dedicated parking?" | "What amenities is this tenant willing to pay a premium for?" | "{value}" | buyer_criteria |
| TEN-FAQ-006 | `faq_q6` | "Do you want outdoor space (patio, balcony, yard)?" | "Is outdoor space a requirement for this tenant?" | "How important is a yard, patio, or balcony to this tenant?" | "{value}" | buyer_criteria |
| TEN-FAQ-007 | `faq_q7` | "Do you have pets requiring outdoor access?" | "Do this tenant's pets need yard or outdoor access?" | "What outdoor access do this tenant's animals require?" | "{value}" | buyer_criteria |
| TEN-FAQ-008 | `faq_q8` | "Would a no-pet-deposit policy affect your decision?" | "Is a pet deposit waiver a decision factor for this tenant?" | "Would waiving the pet fee make this property more attractive?" | "{value}" | buyer_criteria |
| TEN-FAQ-009 | `faq_q9` | "Would you sign a longer lease for a lower rate?" | "Is this tenant willing to commit to a longer term for a discount?" | "Would a 2-year lease be acceptable if the rent is reduced?" | "{value}" | buyer_criteria |
| TEN-FAQ-010 | `faq_q10` | "Does the unit need to include furniture?" | "Is this tenant looking for a furnished rental?" | "Does this tenant need furniture included in the unit?" | "{value}" | buyer_criteria |
| TEN-FAQ-011 | `faq_q11` | "Is your move-in date firm or flexible?" | "How flexible is this tenant on their move-in date?" | "Can this tenant adjust their move-in timeline if needed?" | "{value}" | buyer_criteria |
| TEN-FAQ-012 | `faq_q12` | "How would you handle an unexpected lease break?" | "What is this tenant's plan if they need to break the lease early?" | "Has this tenant broken a lease before?" | "{value}" | buyer_criteria |
| TEN-FAQ-013 | `faq_q13` | "How many months would you commit to for a discount?" | "What lease length is this tenant willing to sign for a rate reduction?" | "Would 18 or 24 months be acceptable to this tenant?" | "{value}" | buyer_criteria |
| TEN-FAQ-014 | `faq_q14` | "Is your search driven by a job change or life event?" | "What is prompting this tenant's rental search right now?" | "Is there a deadline or external event driving the timing?" | "{value}" | buyer_criteria |
| TEN-FAQ-015 | `faq_q15` | "How long was your most recent tenancy, and why are you moving?" | "What is this tenant's rental history?" | "How long did this tenant stay at their last address?" | "{value}" | buyer_criteria |
| TEN-FAQ-016 | `faq_q16` | "Are you looking for a short-term or long-term home?" | "Does this tenant want a temporary or permanent rental?" | "Is this tenant looking to settle or stay only briefly?" | "{value}" | buyer_criteria |
| TEN-FAQ-017 | `faq_q17` | "Do you have a landlord or employer reference available?" | "Can this tenant provide references from a prior landlord?" | "What references is this tenant prepared to offer?" | "{value}" | buyer_criteria |
| TEN-FAQ-018 | `faq_q18` | "What is the source of your income?" | "How does this tenant earn their income?" | "Is this tenant employed, self-employed, or retired?" | "{value}" | buyer_criteria |
| TEN-FAQ-019 | `faq_q19` | "How do you prefer to communicate with a landlord?" | "What communication style works best for this tenant?" | "Does this tenant prefer text, email, or phone calls?" | "{value}" | buyer_criteria |
| TEN-FAQ-020 | `faq_q20` | "What's your biggest concern in this rental search?" | "What is this tenant most worried about in finding a rental?" | "What obstacle is this tenant most trying to avoid?" | "{value}" | buyer_criteria |
| TEN-COM-001 | `faq_q21` | "What type of business will be operating from this space?" | "What commercial activity does this tenant plan to run?" | "What business category does this tenant represent?" | "{value}" | buyer_criteria |
| TEN-COM-002 | `faq_q22` | "Do you expect customer or client foot traffic?" | "Will clients or customers visit this commercial space?" | "Does this tenant's business need walk-in or appointment traffic?" | "{value}" | buyer_criteria |
| TEN-COM-003 | `faq_q23` | "Do you have special equipment or power requirements?" | "Does this tenant's business need heavy power or specialized wiring?" | "Are there electrical or structural requirements for this tenant's equipment?" | "{value}" | buyer_criteria |
| TEN-COM-004 | `faq_q24` | "Do you require exterior building signage?" | "Does this tenant need to display exterior signage?" | "Is prominent exterior visibility a requirement for this tenant's business?" | "{value}" | buyer_criteria |
| TEN-COM-005 | `faq_q25` | "Will you need to modify or build out the space?" | "Is this tenant planning a buildout or tenant improvement?" | "Does this commercial tenant need to modify the space before occupying?" | "{value}" | buyer_criteria |
| TEN-COM-006 | `faq_q26` | "What are your expected hours of operation?" | "When will this tenant's business be open?" | "Does this tenant need access outside of standard business hours?" | "{value}" | buyer_criteria |
| TEN-COM-007 | `faq_q27` | "Are you flexible on commercial lease term length?" | "Would this tenant consider a longer commercial lease term?" | "Is the length of the commercial lease negotiable for this tenant?" | "{value}" | buyer_criteria |

---

### 18.12 New-Gap Field Templates (Phase 5 Candidates)

These templates cover high-value fields identified in Section 17 that are currently stored but not in the context builder. They are ready for Phase 5 implementation.

| Field | Meta Key(s) | Q1 | Q2 | Q3 | Answer Template | Applies To |
|---|---|---|---|---|---|---|
| CDD Fee | `has_cdd` + `annual_cdd_fee` | "Is there a CDD fee on this property?" | "What is the annual CDD assessment?" | "Does this property have a Community Development District fee?" | "CDD: {Yes/No}. Annual fee: ${annual_cdd_fee}." | SEL, LND |
| Special Assessments | `has_special_assessments` + `special_assessment_amount` | "Are there any special assessments?" | "Is there an outstanding special assessment on this property?" | "Are there HOA or municipal special assessments?" | "Special assessments: {Yes/No}. Amount: ${value}." | SEL, LND |
| HOA Docs Available | `hoa_condo_docs_available` | "Are HOA documents available for review?" | "Can I see the HOA rules and financials?" | "Is the HOA packet available for this property?" | "HOA docs available: {Yes/No}." | SEL, LND |
| Rent Roll Available | `rent_roll_available` | "Is a rent roll available for this property?" | "Can I see the current tenant rent roll?" | "Is there a rent roll I can review?" | "Rent roll available: {Yes/No}." | SEL (commercial) |
| Operating Statement | `operating_statement_available` | "Is an operating statement available?" | "Can I see the income and expense statement?" | "Is the NOI statement available for review?" | "Operating statement available: {Yes/No}." | SEL (commercial) |
| Seller Disclosure | `seller_disclosure_available` | "Is a seller disclosure available?" | "Has the seller completed a disclosure form?" | "Can I review the seller's property disclosure?" | "Seller disclosure available: {Yes/No}." | SEL |
| Survey Available | `survey_available` | "Is a survey available for this property?" | "Is there an existing property survey?" | "Can I get a copy of the survey?" | "Survey available: {Yes/No}." | SEL |
| Home Warranty | `home_warranty_offered` | "Is a home warranty included?" | "Does the seller offer a home warranty?" | "What home warranty comes with this property?" | "Home warranty offered: {Yes/No}. {home_warranty_amount_details}." | SEL |
| Possession Preference | `possession_preference` | "When would possession transfer?" | "When does the seller want to hand over the keys?" | "What is the possession timing preference?" | "Possession: {value}." | SEL, BUY |
| Seller Concession | `seller_contribution_credit_offered` | "Is the seller offering any concessions?" | "Will the seller contribute to closing costs?" | "Is there a seller credit available?" | "Seller concession offered: {Yes/No}." | SEL |
| Lease-Option Available | `interested_lease_option_agreement` | "Is a lease-option available?" | "Can I rent-to-own this property?" | "Does the seller offer a lease-with-option-to-buy?" | "Lease-option available: {Yes/No}. Price: ${lease_option_price}. Duration: {lease_option_duration}." | SEL, LND |
| Commute Match | `commute_destination_zip` + `max_commute_minutes` + `commute_mode` | "What is this buyer's commute requirement?" | "Where does this buyer commute to?" | "How far is this buyer willing to commute?" | "Buyer commutes to ZIP {commute_destination_zip} by {commute_mode}, max {max_commute_minutes} minutes." | BUY, TEN |
| Flood Tolerance | `flood_zone_tolerance` | "What is this buyer's flood zone tolerance?" | "Is this buyer willing to be in a flood zone?" | "Will the buyer accept a flood zone property?" | "Flood zone tolerance: {value}." | BUY |
| Move-In Dates | `move_in_date_earliest` + `move_in_date_latest` | "What are this tenant's move-in dates?" | "When is the tenant looking to move in?" | "What is the tenant's move-in window?" | "Move-in window: {move_in_date_earliest} to {move_in_date_latest}." | TEN |
| Commercial Lease Pref. | `commercial_lease_type_preference` | "What type of commercial lease does this tenant prefer?" | "Is the tenant looking for a gross or NNN lease?" | "What lease structure does this tenant want?" | "Preferred commercial lease type: {value}." | TEN (commercial) |
| Buildout Request | `buildout_tenant_improvement_request` | "Is this tenant requesting a buildout or TI allowance?" | "What tenant improvements is this tenant asking for?" | "Does this tenant need a build-to-suit space?" | "TI/Buildout request: {value}." | TEN (commercial) |
| Security Deposit | `security_deposit_amount` (LND) | "What is the security deposit for this rental?" | "How much is the security deposit?" | "What upfront deposit is required?" | "Security deposit: ${value}." | LND |
| Total Move-In Cost | `total_move_in_funds_required` | "What is the total move-in cost?" | "How much does a tenant need upfront?" | "What are all the upfront costs to move in?" | "Total move-in funds required: ${value}." | LND |
| Available Date | `available_date` | "When is this rental available?" | "What is the earliest move-in date?" | "Is this property currently available?" | "Available from: {value}." | LND |
| Space Type | `space_type` | "What type of commercial space is this?" | "Is this retail, office, or warehouse space?" | "What is the space classification?" | "Space type: {value}. Class: {space_classification}." | LND (commercial) |
| Signage Rights | `signage_rights` (LND) | "Are there signage rights with this rental?" | "Can a tenant put up exterior signage?" | "What signage is permitted at this location?" | "Signage rights: {value}." | LND (commercial) |
| Personal Guarantee | `personal_guarantee_requirement` (LND) | "Is a personal guarantee required?" | "Does the landlord require a personal guarantee?" | "Is the tenant required to personally guarantee the lease?" | "Personal guarantee required: {value}." | LND (commercial) |
| Renewal Option | `renewal_option_offered` (LND) | "Is a lease renewal option available?" | "Can the tenant renew this lease?" | "Does this lease offer a renewal option?" | "Renewal option: {Yes/No}. {renewal_option_details}." | LND |
| NDA Required | `nda_required` | "Is an NDA required to get information about this business?" | "Do I need to sign a non-disclosure agreement?" | "Is this a confidential business listing?" | "NDA required: {Yes/No}. Contact the listing agent to proceed." | SEL (business) |
| Year Established | `year_established` | "When was this business established?" | "How old is this business?" | "What year did this business start operating?" | "This business was established in {value}." | SEL (business) |

---

### 18.13 Complete Remaining Field Templates (Full Section 17 Coverage)

This section completes Section 18 coverage for **every AI-eligible field** from Sections 17.1–17.4 not already templated in Sections 18.1–18.12. Fields are classified DATABASE-FIRST, AI-OPTIONAL, or AI-REQUIRED in their respective Section 17 tables.

#### 18.13-A Seller Physical Property Features (Tab 2)

| Field | Meta Key(s) | Q1 | Q2 | Q3 | Answer Template | Applies To |
|---|---|---|---|---|---|---|
| Roof Type | `roof_type` + `other_roof_type` | "What type of roof does this property have?" | "What roofing material is on this home?" | "When was the roof last replaced and what type is it?" | "Roof type: {value}." | SEL, LND |
| Exterior Construction | `exterior_construction` + `other_exterior_construction` | "What is the exterior construction of this property?" | "What materials is the outside of this home made of?" | "What type of siding or cladding does this home have?" | "Exterior construction: {value_list}." | SEL, LND |
| Foundation | `foundation` + `other_foundation` | "What type of foundation does this property have?" | "Is this property on a slab, piers, or basement?" | "What foundation system supports this home?" | "Foundation: {value_list}." | SEL, LND |
| Interior Features | `interior_features` + `other_interior_features` | "What interior features does this property include?" | "Does this home have granite counters, crown molding, or similar upgrades?" | "What are some notable interior amenities in this home?" | "Interior features: {value_list}." | SEL, LND |
| Building Features | `building_features` + `other_building_features` | "What building amenities does this property offer?" | "Does this building have an elevator, gym, or doorman?" | "What shared building features come with this listing?" | "Building features: {value_list}." | SEL, LND |
| Air Conditioning | `air_conditioning` + `other_air_conditioning` | "What type of air conditioning does this property have?" | "Is there central A/C in this home?" | "What cooling system does this property use?" | "Air conditioning: {value}." | SEL, LND |
| Heating & Fuel | `heating_and_fuel` + `other_heating_and_fuel` | "What heating system does this property use?" | "Is this home gas, electric, or oil heated?" | "What type of heat and fuel source does this property have?" | "Heating & fuel: {value}." | SEL |
| Appliances Included | `appliances` + `other_appliances` | "What appliances are included with this property?" | "Does this home come with a washer, dryer, or refrigerator?" | "Which appliances stay with the home at closing?" | "Included appliances: {value_list}." | SEL, LND |
| Total Square Footage | `total_square_feet` | "What is the total square footage including all areas?" | "How many total square feet does this property have?" | "What is the building size including unheated areas?" | "Total sq ft: {value} (all areas, including unheated)." | SEL |
| Ceiling Height | `ceiling_height` | "What are the ceiling heights in this property?" | "Are there high or vaulted ceilings here?" | "How high are the ceilings in this space?" | "Ceiling height: {value}." | SEL, LND |
| Waterfront | `waterfront` | "Is this property on the waterfront?" | "Does this property have direct waterfront access?" | "Is this a waterfront property?" | "Waterfront: {Yes/No}." | SEL |
| Water Access | `water_access` + `other_water_access` | "What type of water access does this property have?" | "Is there a dock, boat ramp, or canal access?" | "Does this property include any water access features?" | "Water access: {value_list}." | SEL |
| Fences | `fences` + `other_fences` | "Is this property fenced?" | "What type of fencing does this property have?" | "Does this home have a privacy or chain-link fence?" | "Fences: {value_list}." | SEL |
| Personal Property Included | `included_personal_property` | "What personal property is included in the sale?" | "What furniture or fixtures does the seller include?" | "What personal items stay with the home?" | "Included personal property: {value}." | SEL |
| Excluded Items | `excluded_items` | "Are there any items excluded from the sale?" | "What is the seller keeping when they leave?" | "Are any fixtures or personal items not part of the sale?" | "Excluded items: {value}." | SEL |

#### 18.13-B Seller Land & Parcel Details (Tab 2)

| Field | Meta Key(s) | Q1 | Q2 | Q3 | Answer Template | Applies To |
|---|---|---|---|---|---|---|
| Lot Dimensions | `lot_dimensions` | "What are the lot dimensions?" | "How big is the lot?" | "What is the size and shape of this lot?" | "Lot dimensions: {value}." | SEL, LND |
| Total Acreage | `total_acreage` | "How many acres is this property?" | "What is the total acreage?" | "How large is the parcel in acres?" | "Total acreage: {value} acres." | SEL, LND |
| Zoning | `zoning` | "What is the zoning for this property?" | "How is this lot zoned — residential, commercial, or mixed?" | "What zoning classification does this parcel carry?" | "Zoning: {value}." | SEL, LND |
| Current Land Use | `current_use` + `other_current_use` | "What is the current use of this land?" | "How is this land currently being used?" | "Is this land actively farmed, cleared, or vacant?" | "Current use: {value}." | SEL (land) |
| Buildable | `buildable` | "Is this lot buildable?" | "Can a structure be built on this parcel?" | "Is this land cleared and ready for construction?" | "Buildable: {Yes/No}." | SEL (land) |
| Road Frontage | `road_frontage` + `other_road_frontage` | "Does this property have road frontage?" | "What type of road does this lot front on?" | "Is there public or private road access to this parcel?" | "Road frontage: {value_list}." | SEL (land) |
| Front Footage | `front_footage` | "How many linear feet of road frontage does this lot have?" | "What is the street frontage measurement?" | "How wide is this lot along the road?" | "Front footage: {value} linear ft." | SEL |
| Total Parcel Count | `total_parcel_count` | "How many parcels are included in this listing?" | "Is this a single parcel or multi-parcel listing?" | "How many separate lots or parcels are being sold together?" | "Number of parcels: {value}." | SEL |

#### 18.13-C Seller Utilities (Tab 2)

| Field | Meta Key(s) | Q1 | Q2 | Q3 | Answer Template | Applies To |
|---|---|---|---|---|---|---|
| Water Source | `water` + `other_water` | "What is the water source for this property?" | "Is this property on public water or a well?" | "Where does the drinking water come from?" | "Water source: {value}." | SEL, LND |
| Sewer Type | `sewer` + `other_sewer` | "What type of sewer system does this property use?" | "Is this property on public sewer or a septic system?" | "Does this home connect to city sewer or have a septic tank?" | "Sewer: {value}." | SEL, LND |
| Electrical Service | `electrical_service` + `other_electrical_service` | "What type of electrical service does this property have?" | "What is the electrical capacity — 100A, 200A, or three-phase?" | "Has this property been upgraded to 200-amp service?" | "Electrical service: {value}." | SEL |
| Utilities Available | `utilities` + `other_utilities` | "What utilities are connected to this property?" | "Are gas, water, sewer, and electric all available?" | "Which utility services serve this property?" | "Available utilities: {value_list}." | SEL, LND |
| Water Available | `water_available` + `water_available_other` | "Is water available at this property or lot?" | "Is there public water access or a well on this land?" | "What water infrastructure is available at this vacant lot?" | "Water available: {value}." | SEL (land/commercial) |
| Sewer Available | `sewer_available` + `sewer_available_other` | "Is sewer service available at this property?" | "Is there public sewer access to this land?" | "What sewer or waste management options are available?" | "Sewer available: {value}." | SEL (land/commercial) |
| Electric Available | `electric_available` + `electric_available_other` | "Is electrical service available at this property?" | "Has electricity been brought to this land?" | "Is there power infrastructure at this lot?" | "Electric available: {value}." | SEL (land/commercial) |
| Gas Available | `gas_available` + `gas_available_other` | "Is natural gas available at this property?" | "Is gas service accessible from the street?" | "Is there a gas line available to connect to this property?" | "Gas available: {value}." | SEL (land/commercial) |
| Telecom/Internet | `telecom_available` + `telecom_available_other` | "Is internet or telecom service available here?" | "What internet or fiber options reach this property?" | "Is broadband or cable available at this location?" | "Telecom/internet available: {value}." | SEL (land/commercial) |

#### 18.13-D Seller HOA / Association Extended (Tab 5)

| Field | Meta Key(s) | Q1 | Q2 | Q3 | Answer Template | Applies To |
|---|---|---|---|---|---|---|
| Association Name | `association_name` | "What is the name of the HOA or community association?" | "Who manages this community or condo association?" | "What organization governs this community?" | "Association name: {value}." | SEL, LND |
| Association Type | `association_type` + `association_type_other` | "What type of association governs this property?" | "Is this an HOA, condo association, or co-op?" | "What community governance structure applies here?" | "Association type: {value}." | SEL |
| HOA Fee Includes | `association_fee_includes` + `association_fee_includes_other` | "What does the HOA fee cover?" | "What is included in the monthly association dues?" | "Does the HOA fee pay for water, trash, insurance, or amenities?" | "HOA fee includes: {value_list}." | SEL, LND |
| Community Amenities | `association_amenities` + `association_amenities_other` | "What amenities does this HOA or community offer?" | "Does this community have a pool, gym, or clubhouse?" | "What shared facilities come with this community?" | "Community amenities: {value_list}." | SEL, LND |
| HOA Approval Required | `association_approval_required` | "Does the HOA require approval for new owners or tenants?" | "Is there an HOA application or interview process?" | "Will the HOA need to approve the buyer?" | "HOA approval required: {Yes/No}." | SEL |
| HOA Approval Process | `association_approval_process` | "What is the HOA approval process?" | "How does the association review new residents?" | "How long does HOA approval typically take?" | "HOA approval process: {value}." | SEL, LND |
| HOA/Condo Terms | `hoa_condo_association_terms` | "Are there specific HOA or condo terms to be aware of?" | "What key rules does the HOA or condo association impose?" | "What are the HOA's notable restrictions or requirements?" | "HOA/Association terms: {value}." | SEL |
| 55+ Community | `leasing_55_plus` | "Is this a 55+ restricted community?" | "Is there an age restriction to live or own here?" | "Does this community require residents to be 55 or older?" | "55+ community: {Yes/No}." | SEL, LND |
| Additional Restrictions | `additional_lease_restrictions` | "Are there additional rental restrictions beyond the standard HOA rules?" | "What other leasing limitations apply to this property?" | "Are there special rental rules specific to this community?" | "Additional restrictions: {value}." | SEL |
| Max Leases Per Year | `max_leases_per_year` | "How many times per year can this property be leased?" | "Does the HOA cap the number of annual rentals?" | "What is the maximum rental frequency allowed by the association?" | "Max leases per year: {value}." | SEL |
| Minimum Lease Period | `min_lease_period` + `min_lease_period_other` | "What is the minimum lease term allowed here?" | "How short a lease does the HOA permit?" | "What is the shortest rental period allowed by this community?" | "Minimum lease period: {value}." | SEL |

#### 18.13-E Seller Disclosures (Tab 5)

| Field | Meta Key(s) | Q1 | Q2 | Q3 | Answer Template | Applies To |
|---|---|---|---|---|---|---|
| Flood Disclosure Available | `flood_disclosure_available` | "Is a flood disclosure available for this property?" | "Has the seller completed a flood risk disclosure?" | "Can I review the flood history documentation?" | "Flood disclosure available: {Yes/No}." | SEL |
| Flood Insurance Required | `flood_insurance_required` | "Is flood insurance required for this property?" | "Does this property require mandatory flood insurance?" | "Will a lender require flood insurance here?" | "Flood insurance required: {Yes/No}." | SEL |
| Flood Zone Panel | `flood_zone_panel` | "What is the FEMA flood zone panel reference?" | "Which FEMA map covers this property?" | "What panel number is shown on the flood zone map?" | "FEMA flood zone panel: {value}." | SEL |
| Inspection Report Available | `inspection_report_available` | "Is a pre-listing inspection report available?" | "Has this property already been inspected?" | "Can I review an existing inspection report before making an offer?" | "Inspection report available: {Yes/No}." | SEL |
| Lead-Based Paint | `lead_based_paint_disclosure` | "Is there a lead-based paint disclosure for this property?" | "Was this home built before 1978?" | "Does this listing require lead paint disclosure?" | "Lead-based paint disclosure required/applicable: {Yes/No}." | SEL |
| Environmental Report | `environmental_report_available` | "Is an environmental report available for this property?" | "Has an environmental assessment been done?" | "Is there a Phase I or Phase II environmental study available?" | "Environmental report available: {Yes/No}." | SEL (commercial) |

#### 18.13-F Seller Transaction Preferences (Tab 3)

| Field | Meta Key(s) | Q1 | Q2 | Q3 | Answer Template | Applies To |
|---|---|---|---|---|---|---|
| Appraisal Contingency Pref. | `appraisal_contingency_preference` | "Does the seller have a preference on appraisal contingencies?" | "Would the seller accept a no-appraisal-contingency offer?" | "What is the seller's stance on waiving the appraisal contingency?" | "Appraisal contingency preference: {value}." | SEL |
| Financing Contingency Pref. | `financing_contingency_preference` | "Does the seller prefer offers with or without a financing contingency?" | "Would the seller prefer a cash offer or one with financing?" | "What is the seller's position on a financing contingency?" | "Financing contingency preference: {value}." | SEL |
| Preferred Inspection Period | `preferred_inspection_period` | "How long does the seller prefer for the inspection period?" | "What is the seller's preferred inspection window in days?" | "Does the seller want a shorter or standard inspection period?" | "Preferred inspection period: {value} days." | SEL |

#### 18.13-G Seller Commercial Income (Tab 4)

| Field | Meta Key(s) | Q1 | Q2 | Q3 | Answer Template | Applies To |
|---|---|---|---|---|---|---|
| Gross Annual Income | `gross_annual_income` | "What is the gross annual income from this investment property?" | "How much rental income does this property generate annually?" | "What is the total annual revenue from tenants?" | "Gross annual income: ${value}." | SEL (commercial) |
| Price Per Sq Ft | `price_per_sqft` | "What is the asking price per square foot?" | "How does the price break down on a per-square-foot basis?" | "What is the cost per sq ft for this commercial property?" | "Price per sq ft: ${value}." | SEL (commercial) |
| Existing Lease Type | `existing_lease_type` + `other_lease_type` | "What type of lease do the current tenants have?" | "Are existing tenant leases gross, NNN, or modified gross?" | "What lease structure is currently in place with existing tenants?" | "Existing lease type: {value}." | SEL (commercial) |
| Current Lease Expiration | `lease_expiration` | "When does the current tenant lease expire?" | "How much time remains on the existing lease?" | "What is the lease term for tenants currently in place?" | "Lease expiration: {value}." | SEL (commercial) |
| Number of Units | `number_of_unit` | "How many rentable units does this investment property have?" | "What is the unit count for this multi-family or commercial property?" | "How many income-producing units are included?" | "Number of units: {value}." | SEL (commercial) |
| Min Cap Rate | `minimum_cap_rate` | "What is the minimum acceptable cap rate?" | "What cap rate does the seller expect?" | "What capitalization rate is associated with this property?" | "Minimum cap rate: {value}%." | SEL (commercial) |

#### 18.13-H Seller Business-for-Sale (Tab 4)

| Field | Meta Key(s) | Q1 | Q2 | Q3 | Answer Template | Applies To |
|---|---|---|---|---|---|---|
| Business Name | `business_name` | "What is the name of this business?" | "What business is being sold with this listing?" | "What is the business name or DBA on the listing?" | "Business name: {value}." | SEL (business) |
| Employee Count | `employee_count` | "How many employees does this business currently have?" | "What is the current staff headcount?" | "How many full-time and part-time employees are there?" | "Employee count: {value}." | SEL (business) |
| Financial Statements | `financial_statements_available` | "Are business financial statements available for review?" | "Can I see the P&L or financial records for this business?" | "Are financial records included in the business listing package?" | "Financial statements available: {Yes/No}." | SEL (business) |
| Business Location Leased | `business_location_leased` | "Is the business operating out of a leased location?" | "Does the business own or lease its premises?" | "Is the location included in the sale or separately leased?" | "Business location: {Leased / Owned}." | SEL (business) |
| Business Lease Expiration | `business_lease_expiration` | "When does the business location lease expire?" | "How much time is left on the business's current lease?" | "What is the remaining lease term for the business location?" | "Business lease expires: {value}." | SEL (business) |
| Reason for Sale | `reason_for_sale` + `other_reason_for_sale` | "Why is this business being sold?" | "What is prompting the owner to sell?" | "Is this sale due to retirement, relocation, or other reasons?" | "Reason for sale: {value}." | SEL (business) |

#### 18.13-I Seller Lease/Rent-to-Own Terms (Tab 3)

| Field | Meta Key(s) | Q1 | Q2 | Q3 | Answer Template | Applies To |
|---|---|---|---|---|---|---|
| Option Payment Amount | `lease_option_payment` | "What is the upfront option payment for the lease-option?" | "How much is the option fee to enter the lease-option?" | "What deposit secures the option to purchase?" | "Option payment: ${value}." | SEL |
| Lease-Option Maintenance | `seller_lease_option_maintenance` | "Who is responsible for maintenance during the lease-option period?" | "Does the tenant or seller handle maintenance in a lease-option?" | "What are the maintenance obligations during the option period?" | "Maintenance responsibility: {value}." | SEL |
| Monthly Lease-Purchase Payment | `lease_purchase_payment` | "What is the monthly payment for the lease-purchase arrangement?" | "How much is the monthly cost under the rent-to-own agreement?" | "What does the tenant pay per month under the lease-purchase?" | "Monthly lease-purchase payment: ${value}." | SEL |
| Lease-Purchase Deposit | `seller_lease_purchase_deposit` | "What deposit is required to enter the lease-purchase?" | "How much is the upfront deposit on this rent-to-own?" | "What initial payment does the lease-purchase require?" | "Lease-purchase deposit: ${value}." | SEL |
| Rent Credit Offered | `seller_lease_purchase_rent_credit` | "Does any portion of the monthly payment count toward the purchase price?" | "Is there a rent credit built into the lease-purchase?" | "How does the rent credit work toward the eventual purchase?" | "Rent credit offered: {Yes/No}. {seller_lease_purchase_rent_credit_amount if Yes}." | SEL |

---

#### 18.13-J Buyer Additional Fields (Tab 3)

| Field | Meta Key(s) | Q1 | Q2 | Q3 | Answer Template | Applies To |
|---|---|---|---|---|---|---|
| Sale Provision | `sale_provision` + `sale_provision_other` | "What type of sale structure is this buyer using?" | "Is this buyer looking for a standard sale or assignment?" | "What sale provision does this buyer require?" | "Sale provision: {value}." | BUY |
| Willing to Buy As-Is | `as_is_purchase` | "Is this buyer willing to purchase a property as-is?" | "Would this buyer waive repair requests?" | "Can this buyer accept a no-repair-credit offer?" | "Willing to buy as-is: {Yes/No}." | BUY |
| Purchase Purpose | `purchase_purpose` + `purchase_purpose_other` | "What is this buyer's intended use for the property?" | "Is this purchase for primary residence, investment, or vacation?" | "How does this buyer plan to use the property?" | "Purchase purpose: {value}." | BUY |
| Home Warranty Requested | `home_warranty_requested` | "Is this buyer requesting a home warranty?" | "Does this buyer want a warranty included in the sale?" | "Would a home warranty help close this deal for the buyer?" | "Home warranty requested: {Yes/No}. {home_warranty_details}." | BUY |
| Property Inclusions | `property_inclusions` | "What does this buyer want included in the sale?" | "Are there specific appliances or items this buyer expects to remain?" | "What inclusions is this buyer requesting?" | "Requested inclusions: {value}." | BUY |
| Seller Contribution Requested | `seller_contribution` | "Is this buyer requesting seller concessions or credits?" | "Does this buyer need the seller to contribute to closing costs?" | "Is this offer contingent on seller credits?" | "Seller contribution requested: {Yes/No}. {seller_contribution_details}." | BUY |
| Home Sale Contingency | `home_sale_contingency` | "Is this buyer's offer contingent on selling their current home?" | "Does this buyer have a home sale contingency?" | "Is this purchase subject to the buyer closing on another property?" | "Home sale contingency: {Yes/No}." | BUY |
| Due Diligence Period | `due_diligence_yn` | "Is a due diligence period part of this buyer's offer?" | "Does this buyer want a formal due diligence window?" | "Is due diligence included as a contingency?" | "Due diligence period: {Yes/No}." | BUY |
| Preferred Seller Financing | `seller_financing_type` | "What seller financing structure is this buyer looking for?" | "Is this buyer seeking owner financing, and in what form?" | "What type of seller carry-back does this buyer prefer?" | "Seller financing preference: {value}." | BUY |
| Earnest Money Type | `earnest_money_type` | "What form of earnest money is this buyer offering?" | "Is this buyer's EMD cash, wire, or personal check?" | "How will this buyer tender the earnest money deposit?" | "Earnest money type: {value}." | BUY |

---

#### 18.13-K Landlord Physical Property Features (Tab 2)

| Field | Meta Key(s) | Q1 | Q2 | Q3 | Answer Template | Applies To |
|---|---|---|---|---|---|---|
| Heating & Fuel (LND) | `heating_fuel` + `other_heating_fuel` | "What type of heating does this rental property use?" | "Is this rental heated by gas, electric, or another fuel?" | "What heating system does this rental unit have?" | "Heating: {value}." | LND |
| Floor Covering | `floor_covering` + `other_floor_covering` | "What type of flooring does this rental have?" | "Is the flooring hardwood, carpet, tile, or mixed?" | "What floor coverings are in this rental unit?" | "Floor covering: {value_list}." | LND |
| Laundry Features | `laundry_features` + `other_laundry_features` | "Are laundry facilities available in this rental?" | "Does this unit have in-unit washer/dryer hookups or a shared laundry room?" | "What laundry options does this rental property include?" | "Laundry features: {value_list}." | LND |
| Security Features | `security_features` + `other_security_features` | "What security features does this rental property have?" | "Is there a security system, cameras, or controlled access?" | "What security measures are in place at this rental?" | "Security features: {value_list}." | LND |
| 24/7 Access | `access_24_7` | "Does this rental offer 24-hour access?" | "Can tenants access this property at any hour?" | "Is around-the-clock access available at this rental?" | "24/7 access: {Yes/No}." | LND, TEN |
| Building Hours | `building_hours` | "What are the building access hours for this property?" | "When can tenants access this building?" | "Are there restricted access times at this property?" | "Building hours: {value}." | LND, TEN |
| Bathroom Facilities | `bathroom_facilities` | "Are the bathroom facilities private or shared in this space?" | "Does this unit have a private bathroom?" | "What bathroom access is included in this rental?" | "Bathroom facilities: {value}." | LND, TEN |
| Shared Amenities | `shared_amenities` | "What shared amenities are available to tenants?" | "Does this property have shared facilities like a gym or rooftop?" | "What common-area amenities do tenants get access to?" | "Shared amenities: {value_list}." | LND, TEN |
| Neighboring Tenants | `neighboring_tenants` | "Who are the current neighboring tenants in this building?" | "What types of businesses or residents occupy nearby units?" | "Are there any notable co-tenants in this building?" | "Neighboring tenants: {value}." | LND, TEN |

#### 18.13-L Landlord Tenant Screening & Policies (Tab 3)

| Field | Meta Key(s) | Q1 | Q2 | Q3 | Answer Template | Applies To |
|---|---|---|---|---|---|---|
| Occupant Types Accepted | `occupant_types` | "What types of occupants does this landlord accept?" | "Does this landlord rent to families, students, or professionals?" | "Are there any occupant-type restrictions at this rental?" | "Accepted occupant types: {value_list}." | LND |
| Service Animals | `service_animal` | "Are service animals permitted at this rental?" | "Does this landlord accommodate service animals?" | "Will a verified service animal be allowed at this property?" | "Service animals: {Yes/No}." | LND, TEN |
| Support Animals (ESA) | `support_animal` | "Are emotional support animals allowed at this rental?" | "Does this landlord permit ESAs?" | "Is there an ESA accommodation policy at this property?" | "Support/ESA animals: {Yes/No}." | LND, TEN |
| Breed Restrictions | `has_breed_restrictions` + `breed_restrictions` | "Are there dog breed restrictions at this rental?" | "What breeds are not allowed at this property?" | "Does this landlord restrict any specific dog breeds?" | "Breed restrictions: {Yes/No}. {breed_restrictions}." | LND |
| Smoking Policy | `smoking_policy` | "What is the smoking policy at this rental?" | "Is smoking allowed inside or anywhere on the property?" | "Is this a smoke-free or non-smoking property?" | "Smoking policy: {value}." | LND |
| Subletting Policy | `subletting_policy` | "Is subletting permitted at this rental?" | "Can tenants sublet or assign their lease to another party?" | "What is the landlord's stance on subletting?" | "Subletting policy: {value}." | LND |
| Income Requirement | `min_income_requirement` | "What income-to-rent ratio does this landlord require?" | "Does the landlord require income of 3x the monthly rent?" | "What is the minimum income threshold to qualify for this rental?" | "Minimum income requirement: {value}." | LND |

#### 18.13-M Landlord Financial Requirements (Tab 3)

| Field | Meta Key(s) | Q1 | Q2 | Q3 | Answer Template | Applies To |
|---|---|---|---|---|---|---|
| Pet Deposit | `pet_deposit_amount` | "How much is the pet deposit?" | "Is there a refundable pet deposit at this rental?" | "What additional deposit is required to have a pet here?" | "Pet deposit: ${value}." | LND |
| Monthly Pet Fee | `pet_monthly_fee` | "Is there a monthly pet fee at this rental?" | "Does this property charge pet rent in addition to the deposit?" | "What is the recurring monthly pet fee?" | "Monthly pet fee: ${value}." | LND |
| Max Pet Weight | `pet_max_weight_lbs` | "What is the maximum pet weight allowed here?" | "Is there a weight limit for pets at this rental?" | "How heavy can a pet be to qualify for this rental?" | "Max pet weight: {value} lbs." | LND |
| First Month Required | `first_month_rent_required` | "Is first month's rent required at move-in?" | "Must tenants pay first month's rent when signing the lease?" | "Does the landlord collect first month's rent at lease signing?" | "First month's rent required at move-in: {Yes/No}." | LND |
| Last Month Required | `last_month_rent_required` | "Is last month's rent required at move-in?" | "Does the landlord require last month's rent paid upfront?" | "Is last month's rent collected at lease signing?" | "Last month's rent required at move-in: {Yes/No}." | LND |

#### 18.13-N Landlord Property Availability & Terms (Tab 3)

| Field | Meta Key(s) | Q1 | Q2 | Q3 | Answer Template | Applies To |
|---|---|---|---|---|---|---|
| Storage Included | `included_storage_space` + `storage_space` | "Does this rental include storage space?" | "Is there dedicated storage available with this unit?" | "Is storage part of what comes with this rental?" | "Storage included: {Yes/No}. Details: {storage_space}." | LND |
| Desired Lease Length | `desired_lease_length` | "What lease term does the landlord prefer?" | "Is this landlord looking for a 1-year or longer-term tenant?" | "What is the ideal lease length for this landlord?" | "Preferred lease length: {value}." | LND |
| Starting Rent | `starting_rent` | "What is the starting rent for this rental listing?" | "What monthly rent does the landlord want?" | "What is the base rent before any auction or negotiation?" | "Starting rent: ${value}." | LND |
| Rent Includes | `rent_includes` + `other_rent_include` | "What expenses are included in the rent?" | "Are any utilities bundled into the monthly rent?" | "What does the rent cover beyond the space itself?" | "Rent includes: {value_list}." | LND, TEN |
| Owner Pays | `owner_pays` + `owner_pays_other` | "What does the landlord or owner cover?" | "Which utilities or costs does the owner pay?" | "What is the owner responsible for at this property?" | "Owner pays: {value_list}." | LND, TEN |
| General Lease Terms | `terms_of_lease` | "What are the general lease terms for this rental?" | "Are there notable conditions in this lease?" | "What key provisions apply to renting this property?" | "Lease terms: {value}." | LND |
| Property Restrictions | `restrictions` | "Are there restrictions on how this property can be used?" | "What activities or behaviors are restricted at this rental?" | "What rules or limitations apply to tenants here?" | "Restrictions: {value}." | LND |
| Leasing Scope | `leasing_space_property` | "Is the entire property or only a portion being leased?" | "Is this a full-building lease or a partial-space lease?" | "How much of the total property is available to lease?" | "Leasing: {value} of the property." | LND |
| Open to Selling | `interested_in_selling` | "Is the landlord open to selling this property?" | "Could this rental eventually become a purchase?" | "Would the landlord consider an offer to buy this property?" | "Open to selling: {Yes/No}." | LND |

#### 18.13-O Landlord Commercial Lease Terms (Tab 3)

| Field | Meta Key(s) | Q1 | Q2 | Q3 | Answer Template | Applies To |
|---|---|---|---|---|---|---|
| Commercial Lease Type | `commercial_lease_type` + `commercial_lease_type_other` | "What type of commercial lease is offered?" | "Is this a gross, NNN, or modified gross lease?" | "What lease structure does this commercial space use?" | "Commercial lease type: {value}." | LND (commercial) |
| Permitted Use | `permitted_use_restrictions` | "What types of businesses can operate in this space?" | "Are there any restrictions on permitted use for this commercial lease?" | "What commercial activities are allowed or prohibited here?" | "Permitted use: {value}." | LND (commercial) |
| Rent Escalation Terms | `rent_escalation_terms` | "What are the rent escalation terms in this commercial lease?" | "Does the rent increase over time? By how much and how often?" | "Is there a fixed rent escalation schedule in this lease?" | "Rent escalation: {value}." | LND (commercial) |
| TI / Buildout Terms | `tenant_improvement_buildout_terms` | "Is a tenant improvement or buildout allowance offered?" | "Does the landlord provide a TI allowance for this space?" | "What buildout terms does the landlord offer commercial tenants?" | "TI/Buildout terms: {value}." | LND (commercial) |

---

#### 18.13-P Tenant Additional Fields (Tabs 2–4)

| Field | Meta Key(s) | Q1 | Q2 | Q3 | Answer Template | Applies To |
|---|---|---|---|---|---|---|
| First Month Available | `first_month_rent_available` | "Does this tenant have first month's rent ready?" | "Is first month's rent secured for this applicant?" | "Can this tenant pay first month at lease signing?" | "First month's rent available: {Yes/No}." | TEN |
| Last Month Available | `last_month_rent_available` | "Does this tenant have last month's rent available?" | "Can this tenant pay last month's rent upfront?" | "Is this tenant prepared to provide last month's rent at signing?" | "Last month's rent available: {Yes/No}." | TEN |
| Desired Lease Length | `desired_lease_length` + `tenant_desired_lease_length` | "What lease length is this tenant looking for?" | "Does this tenant want a short-term or long-term lease?" | "Is this tenant looking for month-to-month, annual, or longer?" | "Desired lease length: {value}." | TEN |
| Desired Rent | `desired_rental_amount` + `lease_amount_frequency` | "What monthly rent is this tenant willing to pay?" | "What is this tenant's target rent amount?" | "What does this tenant want to pay per month for housing?" | "Desired rent: ${desired_rental_amount} per {lease_amount_frequency}." | TEN |
| Tenant Pays | `tenant_pays` + `other_tenant_pays` | "What utilities and expenses does this tenant expect to pay?" | "What is this tenant prepared to cover beyond base rent?" | "Which utility responsibilities does this tenant accept?" | "Tenant pays: {value_list}." | TEN |
| Renewal Option Requested | `renewal_option_requested` | "Is this tenant requesting a renewal option?" | "Does this tenant want the right to renew the lease?" | "Is lease renewal an important factor for this tenant?" | "Renewal option requested: {Yes/No}. {renewal_option_details}." | TEN |
| Rent Escalation Pref. | `rent_escalation_preference` | "What is this tenant's preference on annual rent increases?" | "Is this tenant open to CPI or fixed rent escalations?" | "Would this tenant accept predictable rent escalations?" | "Rent escalation preference: {value}." | TEN |
| Service Animal (TEN) | `service_animal` | "Does this tenant have a service animal?" | "Is this tenant bringing a service animal to the rental?" | "Does this applicant have a verified service animal?" | "Service animal: {Yes/No}." | TEN |
| Support Animal (TEN) | `support_animal` | "Does this tenant have a support or emotional support animal?" | "Does this tenant have ESA documentation?" | "Is this tenant's animal classified as an ESA?" | "Support/ESA animal: {Yes/No}." | TEN |
| Emotional Support Animal | `emotional_support_animal` | "Does this tenant have an emotional support animal?" | "Has this tenant's ESA been documented?" | "Is there an ESA accommodation request from this tenant?" | "Emotional support animal: {Yes/No}." | TEN |
| Business Type | `business_type` + `other_business_type` | "What type of business does this commercial tenant operate?" | "Is this tenant a restaurant, retailer, office user, or other?" | "What commercial category best describes this tenant's business?" | "Business type: {value}." | TEN (commercial) |
| 24/7 Access Required | `access_24_7` (TEN) | "Does this commercial tenant require 24/7 access?" | "Is around-the-clock access essential for this tenant's business?" | "Will this tenant's operations require after-hours access?" | "24/7 access required: {Yes/No}." | TEN (commercial) |
| Intended Business Use | `intended_business_use` | "What does this tenant plan to use this commercial space for?" | "What activity will this commercial tenant conduct in the space?" | "What business purpose drives this tenant's space requirements?" | "Intended use: {value}." | TEN (commercial) |
| Signage Request | `signage_request` | "Is this commercial tenant requesting exterior signage?" | "Does this tenant's business need visible exterior signage?" | "Is building signage a requirement for this commercial tenant?" | "Signage request: {value}." | TEN (commercial) |
| Parking Access Needs | `commercial_parking_access_needs` | "What are this tenant's commercial parking requirements?" | "How many parking spaces does this tenant's business need?" | "Does this tenant have specific parking access needs?" | "Parking access needs: {value}." | TEN (commercial) |
| Neighbor Preferences | `neighboring_tenants` (TEN) | "Does this commercial tenant have co-tenancy preferences?" | "What types of neighboring businesses does this tenant prefer?" | "Are there businesses this tenant wants or wants to avoid nearby?" | "Neighbor preferences: {value}." | TEN (commercial) |

---

#### 18.13-Q Additional Gap-Field Templates (Buyer Financing; Seller Land/Lease; Landlord & Tenant Commercial)

This subsection covers the remaining AI-eligible fields from Section 17 not templated in 18.13-A through 18.13-P, organized by role and sub-category.

**Buyer Financing Sub-Fields (Tab 3 — Purchasing Terms):**

| Field | Meta Key(s) | Q1 | Q2 | Q3 | Answer Template | Applies To |
|---|---|---|---|---|---|---|
| Balloon Payment | `balloon_payment` + `balloon_payment_date` | "Does this buyer accept a balloon payment structure?" | "Is this buyer open to a loan with a balloon payment?" | "Would this buyer consider seller financing with a balloon payment due at a fixed date?" | "Balloon payment acceptable: {Yes/No}. {balloon_payment_date if Yes}." | BUY |
| Assumable Loan Interest | `assumable_interest` + `assumable_max_interest_rate` | "Is this buyer interested in assuming an existing loan?" | "Would this buyer take over the seller's current mortgage?" | "Is an assumable loan an acceptable financing path for this buyer?" | "Assumable loan interest: {Yes/No}. Max rate: {assumable_max_interest_rate}%." | BUY |
| Loan Duration | `loan_duration` | "What loan term length does this buyer prefer?" | "Is this buyer looking for a 15-year or 30-year loan?" | "What mortgage duration is this buyer targeting?" | "Preferred loan term: {value}." | BUY |
| Amortization Type | `seller_amortization_type` | "What amortization type does this buyer prefer for seller financing?" | "Is this buyer requesting a fixed or adjustable amortization?" | "What amortization schedule does this buyer want for an owner-financed loan?" | "Amortization type: {value}." | BUY |
| Payment Frequency | `seller_payment_frequency` | "What payment frequency does this buyer want for seller financing?" | "Would this buyer prefer monthly or quarterly payments to the seller?" | "How often would this buyer make payments under a seller-financed arrangement?" | "Payment frequency: {value}." | BUY |
| Prepayment Penalty | `prepayment_penalty` | "Does this buyer accept a prepayment penalty clause?" | "Is this buyer willing to take on a loan with prepayment restrictions?" | "Would a prepayment penalty be a dealbreaker for this buyer?" | "Prepayment penalty acceptable: {Yes/No}." | BUY |

**Seller Land / Parcel Additional (Tab 2 — Property Details):**

| Field | Meta Key(s) | Q1 | Q2 | Q3 | Answer Template | Applies To |
|---|---|---|---|---|---|---|
| Road Surface Type | `road_surface_type` + `other_road_surface_type` | "What type of road surface does this property's access road have?" | "Is the access road to this property paved, gravel, or dirt?" | "What road surface condition should I expect at this property?" | "Road surface: {value_list}." | SEL (land), LND |
| Vegetation | `vegetation` + `other_vegetation` | "What type of vegetation covers this land?" | "Is this parcel wooded, cleared, or mixed?" | "What is the predominant vegetation on this acreage?" | "Vegetation: {value_list}." | SEL (land) |
| Easements | `easements` + `other_easements` | "Are there any easements on this property?" | "Does this parcel have utility, access, or drainage easements?" | "What types of easements affect this property?" | "Easements: {value_list}." | SEL |
| Zoning Permitted Uses | `zoning_allows` | "What uses are permitted under this property's zoning?" | "What can legally be built or operated on this land?" | "What does the current zoning allow for this parcel?" | "Permitted uses under zoning: {value}." | SEL (land/commercial), LND |
| Minimum Acreage | `min_acreage` | "What is the minimum acreage available for this listing?" | "Is the seller willing to split the acreage?" | "What is the smallest parcel size the seller will consider?" | "Minimum acreage: {value} acres." | SEL (land) |
| Electric Meters | `number_electric_meters` | "How many electric meters does this property have?" | "Is there more than one electric meter on this commercial property?" | "What is the number of separately metered electrical services?" | "Number of electric meters: {value}." | SEL (commercial), LND (commercial) |
| Water Meters | `number_water_meters` | "How many water meters does this property have?" | "Are there multiple water meters on this property?" | "How many separately metered water connections are at this site?" | "Number of water meters: {value}." | SEL (commercial), LND (commercial) |
| Septic Systems | `number_of_septics` | "How many septic systems does this property have?" | "Is there more than one septic tank on this property?" | "What is the septic infrastructure at this site?" | "Number of septic systems: {value}." | SEL, LND |
| Wells | `number_of_wells` | "How many wells are on this property?" | "Is there an active well providing water to this property?" | "What well infrastructure exists on this parcel?" | "Number of wells: {value}." | SEL, LND |

**Seller Lease-Option/Purchase Additional (Tab 3 — Sale Terms):**

| Field | Meta Key(s) | Q1 | Q2 | Q3 | Answer Template | Applies To |
|---|---|---|---|---|---|---|
| Option Fee Credit | `seller_lease_option_fee_credit` | "Can the option fee be applied as a credit toward the purchase price?" | "Does the lease-option include an option fee credit?" | "Is the option payment refundable or creditable toward the purchase?" | "Option fee credit: {Yes/No}. {seller_lease_option_fee_credit_percent}% credit if yes." | SEL |
| Lease-Option Extension | `seller_lease_option_extension_terms` | "Are there extension terms available in the lease-option?" | "Can the option period be extended?" | "What are the terms if the tenant-buyer needs more time to exercise the option?" | "Extension terms: {value}." | SEL |
| Purchase Maintenance | `seller_lease_purchase_maintenance` | "Who handles maintenance during the lease-purchase period?" | "Is the tenant-buyer responsible for upkeep under the lease-purchase?" | "What are the maintenance obligations during the lease-purchase?" | "Maintenance during lease-purchase: {value}." | SEL |
| Lease-Purchase Extension | `seller_lease_purchase_extension_terms` | "Are there extension terms for the lease-purchase?" | "Can the purchase timeline be extended under the lease-purchase?" | "What happens if the buyer needs more time to close on the lease-purchase?" | "Lease-purchase extension terms: {value}." | SEL |

**Landlord Commercial Space Details (Tab 2 — Property Details):**

| Field | Meta Key(s) | Q1 | Q2 | Q3 | Answer Template | Applies To |
|---|---|---|---|---|---|---|
| Number of Offices | `number_of_offices` | "How many private offices does this commercial space have?" | "What is the office count in this space?" | "How many closed-door offices are included in this commercial rental?" | "Number of offices: {value}." | LND (commercial) |
| Number of Restrooms | `number_of_restrooms` | "How many restrooms does this commercial space have?" | "What restroom facilities are included in this space?" | "How many bathrooms are available in this commercial unit?" | "Number of restrooms: {value}." | LND (commercial) |
| Conference Rooms | `number_of_conference_rooms` | "How many conference rooms does this space include?" | "Are there meeting rooms in this commercial space?" | "How many dedicated conference or meeting rooms are available?" | "Number of conference rooms: {value}." | LND (commercial) |
| Max Occupants | `number_of_occupants_allowed` | "What is the maximum occupancy for this commercial space?" | "How many people can legally occupy this space?" | "What is the occupancy limit for this commercial unit?" | "Max occupants allowed: {value}." | LND (commercial) |
| Flex Space Sq Ft | `flex_space_sqft` | "How many square feet of flex space does this property have?" | "What is the size of the flex or warehouse portion of this space?" | "How much flex-use square footage is included?" | "Flex space: {value} sq ft." | LND (commercial) |
| Office/Retail Sq Ft | `office_retail_sqft` | "How many square feet of office or retail space are in this property?" | "What is the finished office/retail area of this commercial space?" | "How large is the office or storefront portion of this space?" | "Office/retail sq ft: {value} sq ft." | LND (commercial) |
| CAM/NNN Charges | `cam_nnn_additional_rent_charges` | "What additional charges are included in CAM or NNN fees?" | "What operating expenses are passed through to tenants?" | "What does the CAM fee cover in this commercial lease?" | "CAM/NNN charges: {value}." | LND (commercial) |

**Landlord Occupancy (Tab 3 — Leasing Terms):**

| Field | Meta Key(s) | Q1 | Q2 | Q3 | Answer Template | Applies To |
|---|---|---|---|---|---|---|
| Occupant Status | `occupant_status` | "Is this rental property currently occupied or vacant?" | "Does this rental have an existing tenant in place?" | "What is the current occupancy status of this property?" | "Occupant status: {value}." | LND |

**Tenant Commercial Lease Terms (Tab 3 — Leasing Terms):**

| Field | Meta Key(s) | Q1 | Q2 | Q3 | Answer Template | Applies To |
|---|---|---|---|---|---|---|
| CAM/NNN Preference | `cam_nnn_preference` | "Is this tenant willing to accept CAM or NNN charges?" | "Does this commercial tenant prefer a gross lease or is NNN acceptable?" | "What is this tenant's position on triple-net or CAM pass-throughs?" | "CAM/NNN preference: {value}." | TEN (commercial) |
| Personal Guarantee Pref. | `personal_guarantee_preference` | "Is this commercial tenant willing to provide a personal guarantee?" | "Would this tenant personally guarantee the commercial lease?" | "What is this tenant's preference on a personal guarantee requirement?" | "Personal guarantee preference: {value}." | TEN (commercial) |
| Accessibility Requirements | `accessibility_requirements` | "Does this commercial tenant have accessibility requirements?" | "Are there ADA or accessibility needs specific to this tenant?" | "What accessibility standards must this commercial space meet for this tenant?" | "Accessibility requirements: {value}." | TEN (commercial) |

---

## 19. Phase 5 Context Builder Extension Scope

This section defines the exact engineering work required to wire the ~270 new-gap fields from Section 17 into the Ask AI pipeline. Completing this work transforms Ask AI from covering the current 279 fields into covering the full field universe.

### 19.1 Work Categories

**Category A — `extractFactualFields()` additions (Seller)**

Add the following groups to `AskAiContextBuilderService::extractFactualFields()`:
1. Physical property features: `roof_type`, `exterior_construction`, `foundation`, `interior_features`, `building_features`, `air_conditioning`, `heating_and_fuel`, `appliances`, `total_square_feet`, `ceiling_height`, `waterfront`, `water_access`, `fences`, `included_personal_property`, `excluded_items`
2. Land/parcel: `lot_dimensions`, `total_acreage`, `min_acreage`, `zoning`, `zoning_allows`, `current_use`, `buildable`, `road_frontage`, `road_surface_type`, `vegetation`, `easements`, `front_footage`, `total_parcel_count`
3. Utilities: `water`, `sewer`, `electrical_service`, `utilities`, `water_available`, `sewer_available`, `electric_available`, `gas_available`, `telecom_available`, `number_electric_meters`, `number_water_meters`, `number_of_septics`, `number_of_wells`
4. HOA extended: `association_name`, `association_type`, `association_fee_includes`, `association_amenities`, `association_approval_required`, `association_approval_process`, `hoa_condo_association_terms`, `leasing_55_plus`, `additional_lease_restrictions`, `max_leases_per_year`, `min_lease_period`
5. Disclosures: `flood_disclosure_available`, `flood_insurance_required`, `flood_zone_panel`, `inspection_report_available`, `lead_based_paint_disclosure`, `environmental_report_available`
6. Transaction preferences: `appraisal_contingency_preference`, `financing_contingency_preference`, `preferred_inspection_period`, `possession_preference`
7. Commercial income: `gross_annual_income`, `price_per_sqft`, `existing_lease_type`, `lease_expiration`, `number_of_unit`
8. Business: `business_name`, `employee_count`, `financial_statements_available`, `business_location_leased`, `business_lease_expiration`, `reason_for_sale`
9. Lease/RTO: `lease_option_payment`, `seller_lease_option_maintenance`, `seller_lease_option_fee_credit`, `seller_lease_option_extension_terms`, `lease_purchase_payment`, `seller_lease_purchase_deposit`, `seller_lease_purchase_rent_credit`, `seller_lease_purchase_maintenance`

**Category B — Buyer context builder additions**

Extend `extractBuyerCriteria()` (or equivalent buyer-side extraction) to include:
- Transaction: `sale_provision`, `as_is_purchase`, `purchase_purpose`, `home_warranty_requested`, `property_inclusions`, `seller_contribution`, `home_sale_contingency`, `due_diligence_yn`, `earnest_money_type`, `seller_financing_type`
- Financing (safe subset): `pre_approved`, `offered_financing` (JSON decode fix), `down_payment_type`, `loan_duration`, `seller_amortization_type`, `seller_payment_frequency`, `balloon_payment`, `balloon_payment_date`, `assumable_interest`, `prepayment_penalty`
- Preferences: `commute_destination_zip`, `max_commute_minutes`, `commute_mode`, `flood_zone_tolerance`, `purchase_purpose`

**Category C — Landlord context builder additions**

Add to landlord context extraction:
- Physical: `heating_fuel`, `floor_covering`, `laundry_features`, `appliances`, `building_features`, `security_features`, `air_conditioning`, `interior_features`, `roof_type`, `foundation`, `exterior_construction`
- Commercial space: `space_type`, `space_classification`, `ceiling_height`, `number_of_offices`, `number_of_restrooms`, `number_of_conference_rooms`, `number_of_occupants_allowed`, `flex_space_sqft`, `office_retail_sqft`, `access_24_7`, `bathroom_facilities`, `building_hours`, `cam_nnn_additional_rent_charges`
- Screening/policies: `occupant_types`, `occupant_status`, `service_animal`, `support_animal`, `has_breed_restrictions`, `breed_restrictions`, `smoking_policy`, `subletting_policy`, `min_income_requirement`
- Financial: `security_deposit_required`, `security_deposit_amount`, `pet_deposit_amount`, `pet_monthly_fee`, `pet_max_weight_lbs`, `first_month_rent_required`, `last_month_rent_required`, `total_move_in_funds_required`
- Terms: `available_date`, `desired_lease_length`, `starting_rent`, `rent_includes`, `owner_pays`, `terms_of_lease`, `restrictions`, `leasing_space_property`, `interested_in_selling`
- Commercial lease: `commercial_lease_type`, `permitted_use_restrictions`, `rent_escalation_terms`, `tenant_improvement_buildout_terms`, `signage_rights`, `renewal_option_offered`, `personal_guarantee_requirement`

**Category D — Tenant context builder additions**

Extend tenant context extraction to include:
- Move-in: `move_in_date_earliest`, `move_in_date_latest`, `first_month_rent_available`, `last_month_rent_available`
- Lease: `desired_lease_length`, `tenant_desired_lease_length`, `desired_rental_amount`, `lease_amount_frequency`, `rent_includes`, `tenant_pays`, `owner_pays`, `renewal_option_requested`, `rent_escalation_preference`
- Commercial: `commercial_lease_type_preference`, `cam_nnn_preference`, `buildout_tenant_improvement_request`, `signage_request`, `intended_business_use`, `personal_guarantee_preference`, `accessibility_requirements`, `commercial_parking_access_needs`
- Business: `business_type`, `business_assets`
- Space: `access_24_7`, `space_features`, `leasing_spaces`, `neighboring_tenants`
- Animal: `service_animal`, `support_animal`, `emotional_support_animal`
- Commute: `commute_destination_zip`, `max_commute_minutes`, `commute_mode`

### 19.2 NL Route Extensions

For each new DATABASE-FIRST field added in Category A–D above, at minimum one KEYWORD_RULE entry and one LISTING_KEY_KEYWORD_MAP entry must be added to the classifier and runner respectively. Priority routing targets (fields with the highest question frequency expected):
- `available_date`, `desired_lease_length`, `security_deposit_amount`, `occupant_types`, `smoking_policy`, `move_in_date_earliest`, `commercial_lease_type`, `space_type`, `business_type`, `zoning`

### 19.3 Registry Entry Additions

Each new-gap field wired in Categories A–D needs a `listingFieldRegistry()` entry (in `AskAiFieldQuestionRegistryService`) with `sample_question`, `sample_question_2`, and `keyword_route_status`. Section 18.13 (subsections A–Q) provides the Q1, Q2, and Q3 for each field, which map directly to `sample_question`, `sample_question_2`, and the Phase 5 question index respectively.

### 19.4 Estimated Phase 5 Effort

| Category | Fields to Wire | Estimated Effort |
|---|---|---|
| A — Seller `extractFactualFields()` | ~60 new keys | 3–4 days |
| B — Buyer context builder | ~25 new keys | 1–2 days |
| C — Landlord context builder | ~60 new keys | 3–4 days |
| D — Tenant context builder | ~30 new keys | 1–2 days |
| NL routes (all roles) | ~270 keyword rules | 2–3 days |
| Registry entries (all roles) | ~270 new entries | 3–4 days |
| **Total Phase 5** | **~270 fields** | **~2–3 weeks** |

---

## 20. Field Universe Reconciliation

This section provides a complete reconciliation of every field category catalogued in this audit against (a) current Ask AI context builder coverage, (b) registry coverage, and (c) Section 18 template coverage. Its purpose is to confirm that every AI-eligible field identified in Sections 3–17 has a corresponding Section 18 template entry, closing the loop on the audit.

### 20.1 Reconciliation Summary by Role and Section

| Section | Role | AI-Eligible Fields Catalogued | Templated in Sec 18 | Already in Context Builder | Phase 5 Action Required |
|---|---|---|---|---|---|
| 3.1–3.6 | Seller | 83 structural fields | 83 (Sec 18.1 + 18.7–18.8) | Registered in context builder — many accessors broken (see Critical Findings §1.2) | Fix broken accessors (SEL-015 through SEL-029 series) |
| 4.1–4.5 | Buyer | 75 structural fields | 75 (Sec 18.3 + 18.9) | Registered in context builder — majority of structural accessors broken (BUY: 88% broken rate) | Fix broken accessors (BUY-018 through BUY-024 series) |
| 5.1–5.4 | Landlord | 70 structural fields | 70 (Sec 18.4 + 18.10) | Registered in context builder — 3 accessor issues (see §14.2) | Fix broken accessors (LND-series) |
| 6.1–6.3 | Tenant | 51 structural fields | 51 (Sec 18.5 + 18.11) | Registered in context builder — 23/24 fully reachable; TEN-024 policy-excluded (RESTRICTED) | No accessor fix needed; TEN-024 exclusion is intentional |
| 7–15 | Buyer + Match Scores | Context-builder fields (buyer criteria) | Covered by FAQ templates (Sec 18.9 + 18.11) | Yes — buyer criteria in ctx | No gaps; buyer ctx complete |
| 17.1 | Seller | ~70 new AI-eligible DATABASE-FIRST/AI-OPTIONAL | 70 (Sec 18.12 + 18.13-A through 18.13-I) | **No** — not in context builder | Phase 5: wire via `extractFactualFields()` |
| 17.2 | Buyer | ~45 new AI-eligible | 45 (Sec 18.12 + 18.13-J) | **No** — not in context builder | Phase 5: wire via buyer context extension |
| 17.3 | Landlord | ~90 new AI-eligible | 90 (Sec 18.12 + 18.13-K through 18.13-O) | **No** — not in context builder | Phase 5: wire via landlord context builder |
| 17.4 | Tenant | ~65 new AI-eligible | 65 (Sec 18.12 + 18.13-P) | **No** — not in context builder | Phase 5: wire via tenant context builder |

### 20.2 Classification Coverage Matrix

| Classification | Total Fields in Audit | In Context Builder Now | Templated in Sec 18 | Phase 5 Priority |
|---|---|---|---|---|
| `DATABASE-FIRST` | ~380 | ~120 (Secs 3–6) | ✅ All templated | High — structural data, answers possible today |
| `AI-OPTIONAL` | ~220 | 0 (Sec 17 gaps) | ✅ All templated | Medium — enrich context builder in Phase 5 |
| `AI-REQUIRED` | ~60 | 0 (Sec 17 gaps) | ✅ All templated | High — free-text that needs prompt routing |
| `RESTRICTED` | ~510 | 0 | ❌ Intentionally excluded | Never — privacy/financial policy |
| `INTERNAL` | ~310 | 0 | ❌ Intentionally excluded | Never — platform-internal only |
| `PII` | ~45 | 0 | ❌ Intentionally excluded | Never — personal data |
| `MEDIA` | ~12 | 0 | ❌ Intentionally excluded | Never — binary files |
| `LOCATION-HELPER` | ~8 | Partial (geocode) | ❌ Not templated | Low — derived fields, not Ask AI targets |
| `CRYPTO-NICHE` | ~3 | 0 | ❌ Not templated | Low — niche feature |
| `RESTRICTED (agent fees)` | ~230 | 0 | ❌ Intentionally excluded | Never |

### 20.3 Section 18 Completeness Attestation

| Section 18 Sub-Section | Fields Covered | Coverage Status |
|---|---|---|
| 18.1 Seller Structural | 16 DATABASE-FIRST fields (structural native columns) | ✅ Complete |
| 18.2 Seller FAQ (top-15) | 15 of 52 Seller FAQ fields (Q3 Alt phrasings) | ✅ Supplemental; Q1+Q2 in registry |
| 18.3 Buyer Structural | ~20 DATABASE-FIRST fields | ✅ Complete |
| 18.4 Landlord Structural | ~18 DATABASE-FIRST fields | ✅ Complete |
| 18.5 Tenant Structural | ~12 DATABASE-FIRST fields | ✅ Complete |
| 18.6 (Match Score helpers) | Match score context templates | ✅ Complete |
| 18.7 Seller Remaining FAQs | 37 Seller FAQ Q3 alt phrasings | ✅ Complete — all 52 SEL FAQs covered |
| 18.8 Seller Add-On Templates | 19 extended Seller templates | ✅ Complete |
| 18.9 Buyer FAQ (50 entries) | All BUY FAQ keys with Q3 alts | ✅ Complete |
| 18.10 Landlord FAQ (39 entries) | All LND FAQ keys with Q3 alts | ✅ Complete |
| 18.11 Tenant FAQ (27 entries) | All TEN FAQ keys with Q3 alts | ✅ Complete |
| 18.12 New-Gap Fields | 25 high-priority Phase 5 candidates | ✅ Complete |
| 18.13-A through 18.13-P | ~145 remaining AI-eligible Section 17 fields (physical, land, utilities, HOA, disclosures, commercial, screening, financial) | ✅ Complete |
| 18.13-Q | ~40 additional gap-field templates (buyer financing sub-fields, seller land/lease extras, landlord/tenant commercial extras, occupant status) | ✅ Complete — closes remaining coverage gaps |
| **Total** | **~522 distinct field-template entries across all roles** | **✅ Full AI-eligible universe covered** |

### 20.4 What "Complete Coverage" Means

Every field in this audit with classification `DATABASE-FIRST`, `AI-OPTIONAL`, or `AI-REQUIRED` has:
1. A **Section 17 table row** with Tab, Type, Classification, and Notes.
2. A **Section 18 template entry** with Q1, Q2, Q3, and an Answer Template.
3. Either a **current context builder wire** (for fields already in `extractFactualFields()` / `extractBuyerCriteria()`) or an explicit **Phase 5 action item** in Section 16 and Section 20.1.

Fields with classification `RESTRICTED`, `INTERNAL`, `PII`, `MEDIA`, `CRYPTO-NICHE`, or `LOCATION-HELPER` intentionally have no Section 18 template. This is correct architecture: **omission is the safeguard**, not a gap.

### 20.5 Broken Accessor Inventory (Current Phase 4 Fixes)

These DATABASE-FIRST fields are wired in the context builder but return wrong or empty values due to broken accessors. They are distinct from "new gap" fields — they are documented mismatches, not missing coverage:

| Ask AI ID | Field | Role | Root Cause | Fix Path |
|---|---|---|---|---|
| SEL-015 | `water_view` | Seller | `getInfoArray()` key mismatch | Rename accessor to match `ctx['listing']['water_view_types']` |
| SEL-016 | `has_hoa` | Seller | Boolean cast returns null for 0 | Guard with `!== null` check |
| SEL-017 | `association_fee_amount` | Seller | Currency accessor strips to null | Use raw EAV read before formatter |
| SEL-018 | `association_fee_frequency` | Seller | `loadDraft()` key mismatch | Align key to `meta_key` column value |
| SEL-025 | `rental_restrictions` | Seller | `leasing_restrictions` key inconsistency | See memory note: schema typo in `rental_restrictions_desription` |
| SEL-026 | `flood_zone_code` | Seller | Missing in `extractFactualFields()` | Add `flood_zone_code` to factual field extraction |
| SEL-027 | `target_closing_date` | Seller | Date formatter returns null on ISO string | Add ISO → human-readable formatter |
| SEL-029 | `annual_property_taxes` | Seller | Already connected — no fix needed | ✅ Verified working |
| BUY-018 | `pre_approved` | Buyer | Boolean stored as string "1"/"0" | Cast to bool in context builder |
| BUY-019 | `offered_financing` | Buyer | JSON decode not applied | Apply `json_decode()` in extraction |
| BUY-020 | `inspection_period_days` | Buyer | Wrong EAV key read | Confirm meta key name in `BuyerOfferListing.php` |
| BUY-021 | `target_closing_date` | Buyer | Same date formatter issue as SEL-027 | Shared fix |
| BUY-022 | `inspection_contingency_buyer` | Buyer | Boolean string "1"/"0" | Shared boolean fix |
| BUY-023 | `appraisal_contingency_buyer` | Buyer | Boolean string "1"/"0" | Shared boolean fix |
| BUY-024 | `financing_contingency_buyer` | Buyer | Boolean string "1"/"0" | Shared boolean fix |
| LND-016 | `pet_max_weight_lbs` | Landlord | No NL route in router | Add NL route keyword for pet weight |
| LND-028 | `number_of_occupants_allowed` | Landlord | EAV key mismatch in extraction | Confirm exact meta key in `LandlordOfferListing.php` |
