# Property DNA & Buyer/Tenant DNA — Phase 2 Database & Field Architecture Plan

**PLANNING DOCUMENT ONLY — NO CODE, NO MIGRATIONS, NO IMPLEMENTATION CHANGES AUTHORIZED.**

**Document version:** 1.1  
**Plan date:** May 27, 2026  
**Controlling source document:** `docs/PROPERTY_DNA_BUYER_TENANT_DNA_PHASE_1_AUDIT.md`  
**Scope:** Database field architecture, computed table design, AI answer storage, compatibility scoring framework, and marketing intelligence output storage for Property DNA, Buyer/Tenant DNA, and associated intelligence layers.

---

## Table of Contents

1. [Purpose & Scope](#1-purpose--scope)
2. [Storage Strategy Decision Guide](#2-storage-strategy-decision-guide)
3. [Field Groups & Storage Decisions](#3-field-groups--storage-decisions)
   - [3.1 Property DNA — Seller (S-01 through S-07)](#31-property-dna--seller-s-01-through-s-07)
   - [3.2 Property DNA — Landlord (L-01 through L-07)](#32-property-dna--landlord-l-01-through-l-07)
   - [3.3 Buyer DNA (B-01 through B-09)](#33-buyer-dna-b-01-through-b-09)
   - [3.4 Tenant DNA (T-01 through T-07)](#34-tenant-dna-t-01-through-t-07)
   - [3.5 AI Tags & Answer Storage](#35-ai-tags--answer-storage)
   - [3.6 Compatibility Scoring Inputs](#36-compatibility-scoring-inputs)
   - [3.7 Marketing Intelligence Outputs](#37-marketing-intelligence-outputs)
   - [3.8 Campaign Output Storage](#38-campaign-output-storage)
   - [3.9 Area Compatibility Metadata](#39-area-compatibility-metadata)
   - [3.10 Canonical Naming Conventions](#310-canonical-naming-conventions)
4. [New Table Definitions](#4-new-table-definitions)
   - [4.1 property\_dna\_profiles](#41-property_dna_profiles)
   - [4.2 buyer\_tenant\_dna\_profiles](#42-buyer_tenant_dna_profiles)
   - [4.3 listing\_compatibility\_scores](#43-listing_compatibility_scores)
   - [4.4 ai\_faq\_answers](#44-ai_faq_answers)
   - [4.5 dna\_marketing\_outputs](#45-dna_marketing_outputs)
5. [Existing Fields That Can Be Reused As-Is](#5-existing-fields-that-can-be-reused-as-is)
6. [Missing Fields Priority Tiers](#6-missing-fields-priority-tiers)
7. [AI FAQ Fields Keep vs Promote](#7-ai-faq-fields-keep-vs-promote)
8. [Structured Field Upgrade List (FAQ → Dropdown/Checkbox)](#8-structured-field-upgrade-list-faq--dropdowncheckbox)
9. [Phased Migration Order](#9-phased-migration-order)
10. [Risks & Safeguards](#10-risks--safeguards)
11. [Phase 3 Target Files](#11-phase-3-target-files)
12. [Governance, Revision Rules & Controlling Source Document](#12-governance-revision-rules--controlling-source-document)

---

## 1. Purpose & Scope

This document defines the complete database and field architecture required to support Property DNA profiles, Buyer/Tenant DNA profiles, AI answer tagging, compatibility scoring, and marketing intelligence outputs for the Bid Your Offer platform. It covers all 11 required architecture sections plus governance closing language. No code, migrations, UI changes, or database schema changes are applied in this phase.

### What this document covers

- Storage decisions for all 29 new user-input fields identified in the Phase 1 audit (S-01 through S-07, L-01 through L-07, B-01 through B-09, T-01 through T-07)
- Column inventory for five new computed/storage tables: `property_dna_profiles`, `buyer_tenant_dna_profiles`, `listing_compatibility_scores`, `ai_faq_answers`, and `dna_marketing_outputs`
- AI FAQ answer tagging and normalization schema
- Compatibility scoring inputs and match logic between supply and demand workflows
- Marketing intelligence output structure and Fair Housing compliance requirements
- Phase 7 reserved area-compatibility metadata columns
- Canonical naming conventions for all new fields, tables, and keys
- An inventory of existing fields immediately usable for Phase 5 compatibility scoring
- A prioritized tier list of all 29 new fields plus five reserved future fields
- An AI FAQ promotion analysis (keep as free-text vs. promote to structured)
- A complete Phase 3 implementation handoff table (one row per new form control)
- A six-phase migration order with risk ratings and validation gates
- Eight numbered risks with named safeguards
- A Phase 3 target file inventory organized by file type

### What this document does not cover or authorize

- No PHP code, Livewire components, Blade templates, migration files, or configuration files are written, created, or modified.
- No database schema changes are applied.
- No existing forms are changed.
- No implementation decisions beyond explicitly listed Phase 3 targets are derived or implied.
- No newly invented infrastructure (queues, caches, embeddings, vector search, ML pipelines, websockets) appears unless named in the Phase 1 audit.

---

### Four Data Layers — Definitions and Write-Isolation Rules

Throughout this document, four distinct data layers are maintained. They must never be conflated. Computed layers are write-isolated from source-of-truth tables.

**Layer 1 — Source-of-Truth User Input Fields**  
Data entered directly by agents or clients through the offer listing forms. Stored in native columns or EAV meta tables associated with each workflow (e.g., `seller_agent_auction_metas`, `landlord_agent_auction_metas`, `buyer_agent_auctions`, `tenant_agent_auction_metas`). These fields are the authoritative record of what a user has declared. No computed value may silently replace, overwrite, or take precedence over a user-entered value.

**Layer 2 — Computed AI-Derived Metadata**  
Completeness scores, archetype tags, marketing hooks, and DNA profiles calculated asynchronously from user input. Stored in `property_dna_profiles` and `buyer_tenant_dna_profiles`. These values are advisory only. They describe the completeness and character of the user's own data — they do not add facts the user did not provide.

**Layer 3 — Compatibility-Scoring Artifacts**  
Cross-listing match scores and deal-breaker flags computed by the scoring engine. Stored in `listing_compatibility_scores`. Computed by comparing a demand-side profile (Buyer or Tenant) against a supply-side profile (Seller or Landlord). These scores are advisory only and never gate user access, listing visibility, offer submission, or negotiation eligibility.

**Layer 4 — Generated Marketing Outputs**  
AI-produced copy (headlines, social captions, email subjects, match explanations) stored in `dna_marketing_outputs`. Generated entirely from structured user input fields and Phase 1-defined AI FAQ answers. Generated copy does not overwrite, replace, or take precedence over any user-authored content.

**Write-isolation rule (applicable to Layers 2, 3, and 4):** Computed layers may read from source-of-truth tables. They must never write back into source-of-truth tables, overwrite user-entered values, or trigger cascading writes to any other user-input workflow table. This rule is absolute and applies independently of the computed value or AI recommendation.

---

### Governing Principles (Section 1 Summary)

All of the following rules apply throughout this document and all future implementation phases derived from it:

1. **User-authored source fields are the authoritative record.** Computed AI-derived metadata is advisory only. Human override always wins.

2. **All DNA computation, compatibility scoring, AI tagging, and marketing output generation is asynchronous post-save only.** No user-facing save or edit workflow may depend on synchronous AI computation. If the AI system is delayed, queued, unavailable, or disabled, listing creation, editing, public viewing, and negotiation workflows remain fully operational.

3. **All newly introduced AI-derived metadata, compatibility signals, DNA scores, and marketing outputs default to non-public visibility.** None of these fields are exposed on public-facing listing pages, anonymous API responses, or client-visible interfaces unless a separately approved and versioned implementation phase explicitly engineers that exposure.

4. **Fields labeled "Reserved / Future Use Only — Not Implemented" must not appear in any form, public API, admin dashboard, export, search filter, or AI prompt input** until their designated implementation phase is separately approved. This rule is stated here and restated in Section 3.9.

5. **Compatibility scores, DNA profiles, archetype tags, and marketing outputs are advisory assistance tools only.** They must never automatically approve, reject, suppress, prioritize, rank-lock, or gate user access, listing visibility, message visibility, offer ordering, bid comparison ranking, or negotiation eligibility without explicit human action.

6. **No user-authored content, AI FAQ answers, compatibility outputs, or generated marketing copy may be repurposed as training data for future AI systems** unless separately disclosed, approved, and versioned under a future governance framework.

---

## 2. Storage Strategy Decision Guide

The following four rules govern all storage decisions in this document. Where the current storage mechanism for an existing field is unknown from the Phase 1 audit, this plan labels it "confirm before migration" rather than asserting a mechanism.

### Rule 1 — Use a Native Column When

The field is:
- A high-frequency SQL filter criterion (searched, sorted, or joined in listing queries)
- A scalar value (single string, integer, or decimal)
- Present on every record of that workflow (not conditionally shown)

Applies to: `purchase_purpose` (B-02) and the three commute fields `commute_destination_zip`, `max_commute_minutes`, `commute_mode` (B-01) on the Buyer table — since these fields are candidates for geographic and preference filtering in listing search operations. See Section 3.3 for the per-field decision.

### Rule 2 — Use an EAV Meta Key When

The field is:
- Conditional (shown only for certain property types, roles, or user responses)
- On a table architecture already using EAV (e.g., `landlord_agent_auction_metas`, `tenant_agent_auction_metas`)
- An optional enrichment field not required for every record

Applies to: all seven Seller new fields (S-01 through S-07), all seven Landlord new fields (L-01 through L-07), and all seven Tenant new fields (T-01 through T-07). Also applies to any Buyer fields not elevated to native columns per Rule 1.

> **Architecture note on workflow schema asymmetry:** The `buyer_agent_auctions` table uses native columns per the platform's documented architecture. The `landlord_agent_auctions` and `tenant_agent_auctions` tables use EAV meta (stored in `landlord_agent_auction_metas` and `tenant_agent_auction_metas`). Do not assume uniformity across workflows. New field storage must match the architecture of the respective table, not a generalized pattern.

### Rule 3 — Use a JSON Column When

The field is:
- An array or keyed object read as a complete unit
- Never filtered at the element level in SQL queries
- Better expressed as a structured bag of sub-values than as individual scalar columns

Applies to: `pet_species_allowed` (L-03, stored as a JSON array in its EAV meta value), `deal_breaker_flags` on `listing_compatibility_scores`, `score_explanation` on `listing_compatibility_scores`, and all array-valued computed columns on the DNA profile tables.

### Rule 4 — Use a Dedicated New Table When

The data is:
- Computed rather than user-entered
- Append-versioned (historical rows must be retained)
- Accessed across listings of different types (e.g., cross-workflow join)
- Has an independent lifecycle from the source listing record

Applies to: `property_dna_profiles`, `buyer_tenant_dna_profiles`, `listing_compatibility_scores`, `ai_faq_answers` (restructured/extended), and `dna_marketing_outputs`.

---

## 3. Field Groups & Storage Decisions

### 3.1 Property DNA — Seller (S-01 through S-07)

**Storage location:** EAV meta table — `seller_agent_auction_metas`  
**Access pattern:** Written and read via the existing `saveMeta` / `loadDraft` pattern.  
**Special access controls:** `seller_motivation_category` (S-03) is agent-visible only and encryption-recommended. It must not appear in public listing API responses or any output accessible to opposing parties.

All fields in this section trace to Phase 1 audit Section 7.1 and Section 10.1.

| Phase 1 Ref | Meta Key | Data Type | Column / Key Name | Conditionality | Notes |
|-------------|----------|-----------|-------------------|----------------|-------|
| S-01 | `avg_monthly_utility_cost` | Decimal (currency) | `avg_monthly_utility_cost` | Residential / Income only | Estimated monthly average; feeds AI total-cost-of-ownership narrative |
| S-02 | `year_last_renovated` | Integer (4-digit year) or null | `year_last_renovated` | All non-vacant land | "Effective age" signal; pairs with existing `year_built` |
| S-03 | `seller_motivation_category` | String (select value) | `seller_motivation_category` | All | Agent-visible only; encryption-recommended; excluded from all public-facing API responses and AI prompts for public outputs |
| S-04 | `inspection_contingency_acceptance` | String (Yes / No / Negotiable) | `inspection_contingency_acceptance` | All | Enables compatibility matching with B-03 |
| S-05 | `appraisal_contingency_acceptance` | String (Yes / No / Negotiable) | `appraisal_contingency_acceptance` | All | Enables compatibility matching with B-04 |
| S-06 | `leaseback_required` | String (Yes / No / Negotiable) | `leaseback_required` | All | Paired with sub-field below |
| S-06 | `leaseback_days_needed` | Integer (number of days) | `leaseback_days_needed` | Conditional: only when `leaseback_required` = Yes | Number of post-close leaseback days needed |
| S-07 | `occupancy_rate` | Decimal (percentage) | `occupancy_rate` | Income / Commercial property types only | Structured field to complement existing AI FAQ faq_ci_q1 narrative |

### 3.2 Property DNA — Landlord (L-01 through L-07)

**Storage location:** EAV meta table — `landlord_agent_auction_metas`  
**Access pattern:** Written and read via the existing `saveMeta` / `loadDraft` pattern.  
**Special access controls:** `min_income_requirement` (L-06) is agent-visible only. It must not appear on the public listing card or in any API response accessible to prospective tenants.

All fields in this section trace to Phase 1 audit Section 7.2 and Section 10.2.

| Phase 1 Ref | Meta Key | Data Type | Column / Key Name | Conditionality | Notes |
|-------------|----------|-----------|-------------------|----------------|-------|
| L-01 | `year_built` | Integer (4-digit year) | `year_built` | All residential and commercial | Entirely absent from landlord form; present on seller form; foundational condition-intelligence field |
| L-02 | `available_date` | Date | `available_date` | All | Required tier; essential for move-in date matching against tenant `lease_date` |
| L-03 | `pet_policy` | String (Allowed / Not Allowed / Case by Case) | `pet_policy` | Residential | Core deal-breaker signal |
| L-03 | `pet_max_weight_lbs` | Integer (pounds) | `pet_max_weight_lbs` | Conditional: when `pet_policy` ≠ Not Allowed | Maximum allowable pet weight |
| L-03 | `pet_species_allowed` | JSON array of strings | `pet_species_allowed` | Conditional: when `pet_policy` ≠ Not Allowed | Values: Dog, Cat, Bird, Small caged animal |
| L-03 | `pet_deposit_amount` | Decimal (currency) | `pet_deposit_amount` | Conditional: when `pet_policy` ≠ Not Allowed | One-time pet deposit amount |
| L-03 | `pet_monthly_fee` | Decimal (currency) | `pet_monthly_fee` | Conditional: when `pet_policy` ≠ Not Allowed | Monthly recurring pet fee |
| L-04 | `smoking_policy` | String (No smoking / Outdoor or patio only / Allowed) | `smoking_policy` | All | Enables filtering against T-07 smoking preference |
| L-05 | `security_deposit_amount` | Decimal (currency) | `security_deposit_amount` | All | Structured replacement for AI FAQ faq_q25 narrative |
| L-06 | `min_income_requirement` | String (2x / 2.5x / 3x / No minimum / Other) | `min_income_requirement` | All | Agent-visible only; used in pre-screening match scoring; excluded from public listing display and public API responses |
| L-07 | `subletting_policy` | String (Not Allowed / With Approval / Allowed) | `subletting_policy` | All | Structured replacement for AI FAQ faq_q20 narrative |

### 3.3 Buyer DNA (B-01 through B-09)

**Storage location:** The `buyer_agent_auctions` table uses native columns per the platform architecture. New buyer fields are evaluated below for native column vs. EAV. Fields anticipated to be used in listing search/filter queries are assigned to native columns. All others are marked "confirm before migration."

> **Critical caveat:** If `BuyerOfferListing.php` uses `loadMeta()` for any of these fields rather than direct property assignment, those fields must follow the same EAV pattern instead of native columns. This must be confirmed by reading the Livewire component before any Phase C migration is written.

All fields in this section trace to Phase 1 audit Section 7.3 and Section 10.3.

| Phase 1 Ref | Field / Key | Recommended Storage | Column / Key Name | Data Type | Conditionality | Notes |
|-------------|------------|--------------------|--------------------|-----------|----------------|-------|
| B-01 | Commute destination ZIP | Native column (search candidate) | `commute_destination_zip` | String | Optional | Preferred commute origin ZIP or city; Phase 7 drive-time polygon uses this as input |
| B-01 | Max commute time | Native column (search candidate) | `max_commute_minutes` | Integer | Optional | Maximum acceptable commute time in minutes |
| B-01 | Commute mode | Native column (search candidate) | `commute_mode` | String | Optional | Drive / Transit / Walk / Bike / Remote — no commute |
| B-02 | Purchase purpose / archetype | Native column (search candidate) | `purchase_purpose` | String | Required | Primary Residence / Vacation / Second Home / Investment / Business Use / Development / Other; must not include family-composition options |
| B-03 | Inspection contingency required | EAV or native (confirm before migration) | `inspection_contingency_required` | String | Optional | Yes / No / Negotiable; matches against S-04 |
| B-04 | Appraisal contingency required | EAV or native (confirm before migration) | `appraisal_contingency_required` | String | Optional | Yes / No / Negotiable; matches against S-05 |
| B-05 | Sale of current home contingency | EAV or native (confirm before migration) | `home_sale_contingency` | String | Optional | Yes / No / Bridge Loan Available |
| B-06 | HOA acceptance | EAV or native (confirm before migration) | `hoa_acceptance` | String | Optional | Yes / No / Flexible; matches against existing `has_hoa` on seller listings |
| B-06 | Maximum HOA fee tolerance | EAV or native (confirm before migration) | `hoa_max_monthly_fee` | Decimal (currency) | Conditional: when `hoa_acceptance` = Yes or Flexible | Matches against existing `association_fee_amount` on seller listings |
| B-07 | Fixer-upper tolerance | EAV or native (confirm before migration) | `fixer_upper_tolerance` | String | Optional | Move-in ready / Light cosmetic / Moderate renovation / Full renovation / Investment-grade fixer; matches against existing `condition_prop` |
| B-08 | Flood zone tolerance | EAV or native (confirm before migration) | `flood_zone_tolerance` | String | Optional | No flood zone / Zone X only / Moderate risk / Any; matches against existing `flood_zone_code` |
| B-09 | Minimum cap rate target | EAV or native (confirm before migration) | `min_cap_rate_target` | Decimal (percentage) | Conditional: Income / Commercial property type only | Matches against existing `minimum_cap_rate` on seller listings |

### 3.4 Tenant DNA (T-01 through T-07)

**Storage location:** EAV meta table — `tenant_agent_auction_metas`  
**Access pattern:** Written and read via the existing `saveMeta` / `loadDraft` pattern.  
**Special access control for T-05:** `accessibility_requirements` is collected solely as a preference filter for what listings to surface to the tenant. It must never be exposed to landlords, used as a screening criterion, included in match score explanations shown to landlords, or injected into any AI prompt producing landlord-visible output.

All fields in this section trace to Phase 1 audit Section 7.4 and Section 10.4.

| Phase 1 Ref | Meta Key | Data Type | Column / Key Name | Conditionality | Notes |
|-------------|----------|-----------|-------------------|----------------|-------|
| T-01 | `commute_destination_zip` | String | `commute_destination_zip` | Optional | Absent from both tenant form and tenant AI FAQ; most critical missing location signal for tenant matching |
| T-01 | `max_commute_minutes` | Integer | `max_commute_minutes` | Optional | Maximum acceptable commute time in minutes |
| T-01 | `commute_mode` | String | `commute_mode` | Optional | Drive / Transit / Walk / Bike / Remote |
| T-02 | `rental_purpose` | String | `rental_purpose` | Optional | Personal residence / Student / Corporate relocation / Vacation / Short-term / Business use / Other; must not include family-composition options |
| T-03 | `move_in_budget_upfront` | Decimal (currency) | `move_in_budget_upfront` | Optional | Total upfront move-in budget (first month + last month + deposit combined ceiling) |
| T-04 | `move_in_date_earliest` | Date | `move_in_date_earliest` | Optional | Earliest acceptable move-in date; supplements existing single `lease_date` field |
| T-04 | `move_in_date_latest` | Date | `move_in_date_latest` | Optional | Latest acceptable move-in date |
| T-05 | `accessibility_requirements` | String | `accessibility_requirements` | Optional | No special requirements / Ground floor or elevator required / Wheelchair accessible required / ADA compliant features required; tenant-preference filter only — must never surface to landlords or function as a screening criterion |
| T-06 | `credit_score_range` | String | `credit_score_range` | Optional | Excellent 750+ / Good 700–749 / Fair 650–699 / Below 650 / Prefer not to disclose; reinstates commented-out `credit_score_rating` field with compliance framing; must never gate listing access |
| T-07 | `smoking_preference` | String | `smoking_preference` | Optional | Non-smoker / Smoker — outdoor allowed / Smoker — indoor allowed; matches against L-04 `smoking_policy` |

### 3.5 AI Tags & Answer Storage

The existing AI FAQ answer storage must be extended to support downstream intelligence aggregation, category tagging, and normalized value extraction. No new questions are added to the four existing AI FAQ config files. No questions are removed. The change is to the storage schema only.

**Confirm before migration:** The current storage mechanism for AI FAQ answers is unknown from the Phase 1 audit alone. The Livewire save/load logic for the AI FAQ form must be read before any Phase F migration is written. If answers are currently stored as EAV meta keys (key = question_key, value = answer_text), a restructure migration differs significantly from an ALTER to an existing dedicated table. See Section 4.4 for the full column inventory and the "confirm before migration" note.

**Table: `ai_faq_answers`** — Column summary for planning purposes:

| Column Name | Data Type | Notes |
|-------------|-----------|-------|
| `listing_type` | Enumerated string (`seller`, `landlord`, `buyer`, `tenant`) | Which workflow this answer belongs to |
| `listing_id` | 64-bit integer | Which listing record this answer belongs to |
| `question_key` | String | e.g., `faq_q1`, `faq_bo_q3`, `faq_c_q11`; maps to the key in the existing AI FAQ config files |
| `question_group` | String | Logical group within the config file; e.g., `condition_maintenance`, `location_neighborhood`, `commercial` |
| `intelligence_category` | String (C1–C10) | Applied at storage time using the mappings in Phase 1 audit Sections 3.8, 4.5, 5.5, 6.6 |
| `answer_text` | Long text | The raw user-entered answer; unchanged from current storage |
| `answer_normalized` | JSON object or null | Extracted structured values; populated asynchronously; null until normalization runs |

**`answer_normalized` examples** (extracted from raw text; never hallucinated or inferred beyond the text):
- Commute destination text (Buyer faq_q9) → `{ "zip": "32801", "city": "Orlando" }`
- Monthly utility cost text (Seller faq_q11) → `{ "amount": 185.00, "currency": "USD" }`
- HVAC age text (Landlord faq_q1) → `{ "estimated_year": 2019 }`
- Appliance age text (Landlord faq_q4) → `{ "estimated_year": 2021 }`

Full question-to-intelligence-category mappings are the authoritative reference in Phase 1 audit Sections 3.8 (Seller), 4.5 (Landlord), 5.5 (Buyer), and 6.6 (Tenant). They are not reproduced here to avoid duplication.

### 3.6 Compatibility Scoring Inputs

The compatibility scoring engine reads from supply-side source fields and compares them against demand-side counterpart fields. No supply-side or demand-side field in these tables is written to by the scoring engine. All inputs are read-only from the scoring layer's perspective.

Fields marked with an asterisk (\*) are confirmed present in the Phase 1 audit with no schema change required. Fields without an asterisk are new fields defined in Sections 3.1–3.4.

**Seller → Buyer Matching Pairs**

| Supply Field (Seller) | Demand Field (Buyer) | Match Logic |
|-----------------------|----------------------|-------------|
| `property_type`\* | `property_type`\* | Exact match required |
| `property_items`\* | `property_items`\* | Supply value contained in buyer's acceptable list |
| `bedrooms`\* | `bedrooms`\* | Supply ≥ buyer minimum |
| `bathrooms`\* | `bathrooms`\* | Supply ≥ buyer minimum |
| `minimum_heated_square`\* | `minimum_sqft`\* | Supply ≥ buyer minimum |
| `garage_needed`\* | `garage`\* | Match or buyer accepts either |
| `pool_needed`\* | `pool`\* | Match or buyer accepts either |
| `view_preference`\* | `view_preferences`\* | Overlap of at least one value |
| `non_negotiable_amenities`\* | `non_negotiable_amenities`\* | All buyer non-negotiables present in supply set |
| `number_of_units`\* | `number_of_units`\* | Supply ≥ buyer requirement (Income only) |
| `condition_prop`\* | `fixer_upper_tolerance` (B-07) | Supply condition within buyer's stated tolerance range |
| `maximum_budget`\* (desired price) | `maximum_budget`\* (buyer ceiling) | Supply price ≤ buyer maximum |
| `offered_financing`\* | `offered_financing`\* | At least one financing type overlaps |
| `minimum_cap_rate`\* | `min_cap_rate_target` (B-09) | Supply cap rate ≥ buyer minimum (Income/Commercial) |
| `has_hoa`\* | `hoa_acceptance` (B-06) | If supply has HOA and buyer `hoa_acceptance` = No → deal-breaker |
| `association_fee_amount`\* | `hoa_max_monthly_fee` (B-06) | Supply fee ≤ buyer maximum monthly tolerance |
| `flood_zone_code`\* | `flood_zone_tolerance` (B-08) | Supply zone within buyer's stated acceptable zones |
| `inspection_contingency_acceptance` (S-04) | `inspection_contingency_required` (B-03) | If buyer requires and seller rejects → deal-breaker |
| `appraisal_contingency_acceptance` (S-05) | `appraisal_contingency_required` (B-04) | If buyer requires and seller rejects → deal-breaker |
| `target_closing_date`\* | `target_closing_date`\* | Compatible timeframe ranges |
| `sale_provision`\* | `sale_provision`\* | Supply provision acceptable to buyer's list |
| `property_city`\*, `property_county`\*, `property_state`\*, `property_zip`\* | `cities`\*, `counties`\*, `state`\* | Supply location within buyer's acceptable geography |

**Landlord → Tenant Matching Pairs**

| Supply Field (Landlord) | Demand Field (Tenant) | Match Logic |
|-------------------------|----------------------|-------------|
| `property_type`\* | `property_type`\* | Exact match required |
| `property_items`\* | `property_items`\* | Supply value in tenant's acceptable list |
| `bedrooms`\* | `bedrooms`\* | Supply ≥ tenant minimum |
| `bathrooms`\* | `bathrooms`\* | Supply ≥ tenant minimum |
| `total_square_feet`\* | `minimum_sqft`\* | Supply ≥ tenant minimum |
| `non_negotiable_amenities`\* | `non_negotiable_amenities`\* | All tenant non-negotiables present in supply set |
| `leasing_spaces`\* | `leasing_spaces_tenant`\* | At least one leasing space type overlaps |
| `desired_lease_length`\* | `lease_for`\* | At least one lease term option overlaps |
| `desired_lease_price`\* | `budget`\* | Supply price ≤ tenant maximum monthly budget |
| `available_date` (L-02) | `lease_date`\* / `move_in_date_earliest` (T-04) / `move_in_date_latest` (T-04) | Supply available date within tenant's acceptable move-in window |
| `pet_policy` (L-03) | `pets`\* / `type_of_pets`\* | If tenant has pets and landlord = Not Allowed → deal-breaker |
| `pet_max_weight_lbs` (L-03) | `weight_of_pets`\* | Tenant pet weight ≤ landlord maximum |
| `smoking_policy` (L-04) | `smoking_preference` (T-07) | If tenant requires indoor smoking and landlord = No smoking → deal-breaker |
| `property_city`\*, `property_county`\*, `property_state`\* | `cities`\*, `counties`\*, `state`\* | Supply location within tenant's acceptable geography |

### 3.7 Marketing Intelligence Outputs

**Structural split between the two AI output tables:**

- **`property_dna_profiles`** holds `ai_buyer_archetype_tags` and `ai_marketing_hooks` as short JSON arrays — compact classification signals computed from the DNA profile. These are attached to the listing's DNA record, not to individual copy variants.
- **`dna_marketing_outputs`** holds full generated copy variants — one row per output type per listing per generation round. Each row contains the full text of one copy output (headline, caption, email subject, etc.) along with the Fair Housing review status and version metadata.

**Four-layer separation rule:** Neither table's columns overlap with user-input source fields. The `ai_buyer_archetype_tags` in `property_dna_profiles` are derived from user inputs but are distinct computed values — they do not replace or shadow any user-entered field. The copy stored in `dna_marketing_outputs` is generated, not user-authored. Neither table may be read as a source of truth for listing facts.

**Fair Housing compliance requirement (Constraint 8):** All archetype labels in `ai_buyer_archetype_tags` must be derived exclusively from use-case and financial signals. Protected-class characteristics (race, color, national origin, religion, sex, familial status, disability) must never be inferred, targeted, or used as segmentation inputs. The nine compliant archetype labels are:

| Archetype Label | Derivation Signal |
|----------------|------------------|
| Remote Worker | WFH response in Buyer faq_q3 or Tenant faq_q1 |
| Investor — Cash Flow | `purchase_purpose` = Investment + cap rate target (B-09) + financing = Cash or Conventional |
| Investor — Value-Add | `fixer_upper_tolerance` (B-07) = Moderate or Full + `purchase_purpose` = Investment |
| Business Owner | `purchase_purpose` = Business Use + property type = Commercial or Business |
| Land Developer | `purchase_purpose` = Development + property type = Vacant Land |
| First-Time Buyer | Buyer faq_q22 answer indicating first-time buyer status |
| Relocating Professional | `commute_destination_zip` (B-01) in different state or region + relocation reason from AI FAQ |
| Vacation / Second Home | `purchase_purpose` = Vacation or Second Home |
| Short-Term Rental Investor | `purchase_purpose` = Investment + property type = Residential |

No archetype beyond these nine (defined in Phase 1 audit Section 13.2) may be introduced without a governance revision.

**Reference:** Phase 1 audit Section 14 contains the complete Fair Housing compliance framework applicable to all archetype tagging and marketing copy generation.

### 3.8 Campaign Output Storage

The `dna_marketing_outputs` table stores all AI-generated copy variants. No copy is displayed to any user until `fair_housing_reviewed = true`. Every row produced by an AI generation call begins with `fair_housing_reviewed = false` and routes to a Fair Housing word-filter before any display is permitted.

**Column list for `dna_marketing_outputs`:**

| Column | Type | Default | Notes |
|--------|------|---------|-------|
| `id` | 64-bit integer PK | Auto-increment | Row identifier |
| `listing_type` | String (seller / landlord / buyer / tenant) | Required | Which workflow |
| `listing_id` | 64-bit integer | Required | Source listing |
| `output_type` | String | Required | See output type values below |
| `variant_index` | Integer | 1 | Allows multiple variants per output_type (e.g., 3 social captions) |
| `content` | Long text | Required | Full generated copy text for this variant |
| `fair_housing_reviewed` | Boolean | **false** | Must be set true by the word-filter before any display |
| `fair_housing_flags` | JSON array | null | Word-filter hits that triggered manual review; empty array when clean |
| `generated_by` | String | Required | Identifier of the generation model or engine version |
| `generated_at` | Timestamp | Required | When this output was generated |
| `archived_at` | Nullable timestamp | null | Set when superseded by a newer generation; never hard-deleted |

**Output type values** (from Phase 1 audit Section 13.1):

| Output Type Value | Description | Role |
|------------------|-------------|------|
| `listing_narrative` | AI-generated property description for MLS, PDFs, and listing cards | Seller Agent |
| `buyer_archetype_brief` | "This property appeals most to: [archetypes]" with supporting reasons | Seller Agent |
| `social_caption_pack` | Up to 5 caption variants for social media | Seller Agent |
| `email_subject_lines` | A/B-testable email subject lines | Seller Agent |
| `rental_listing_narrative` | Property description optimized for rental platforms | Landlord Agent |
| `tenant_persona_brief` | Ideal tenant profile based on landlord criteria | Landlord Agent |
| `buyer_brief_summary` | Formatted buyer preference summary for inter-agent sharing | Buyer Agent |
| `tenant_brief_summary` | Formatted tenant profile for landlord agents | Tenant Agent |
| `match_alert` | Notification content for compatibility-threshold match alerts | Platform |

**Fair Housing word-filter lifecycle:** The word-filter runs synchronously at generation time before the row's `fair_housing_reviewed` is set. If any blocklist term is detected (see Phase 1 audit Section 14.3 for seed terms), `fair_housing_reviewed` remains false and `fair_housing_flags` is populated with the matched terms. The row is created but is not surfaced in any interface until a human reviewer clears the flags and sets `fair_housing_reviewed = true`. Generated copy must not imply certainty, guarantees, or factual claims beyond the underlying structured input data.

### 3.9 Area Compatibility Metadata

The following columns are Phase 7 reserved placeholders on `property_dna_profiles`. They are defined in the table schema now so that Phase 7 implementation adds no schema change — only the data population logic. Each column is nullable by default and must remain empty (null) until its designated Phase 7 integration is separately approved.

**Reserved columns on `property_dna_profiles`:**

| Column | Source API | Phase 1 Ref | Status |
|--------|-----------|-------------|--------|
| `walk_score` | Walk Score API | F-01 | **Reserved / Future Use Only — Not Implemented** |
| `transit_score` | Walk Score API | F-01 | **Reserved / Future Use Only — Not Implemented** |
| `bike_score` | Walk Score API | F-01 | **Reserved / Future Use Only — Not Implemented** |
| `school_rating` | GreatSchools API | F-02 | **Reserved / Future Use Only — Not Implemented** |
| `flood_zone_verified` | FEMA NFIP API | F-03 | **Reserved / Future Use Only — Not Implemented** |
| `estimated_monthly_utilities` | EIA regional data / Arcadia API | F-05 | **Reserved / Future Use Only — Not Implemented** |

**Constraint 16 restatement:** These reserved columns must not appear in any form, public API response, admin dashboard, export function, search filter UI, or AI prompt input until their designated Phase 7 implementation is separately approved under a versioned governance revision. This prohibition applies without exception regardless of implementation convenience. Each reserved integration also requires a separate Fair Housing proxy-risk review, privacy review, and explainability review before the implementation phase for that integration is approved.

### 3.10 Canonical Naming Conventions

The following conventions apply to all new fields, tables, and keys introduced in Phases 3–7. Any deviation from these conventions requires a governance revision before implementation.

**Suffix standards:**
- `_json` — JSON-encoded values stored in a varchar or text column (distinct from native JSON column types; use when the JSON is opaque to SQL filtering)
- `_score` — all decimal 0–100 computed scores (e.g., `physical_score`, `overall_dna_completeness`, `financial_match_score`)
- `_flags` — JSON arrays of reason or warning codes (e.g., `deal_breaker_flags`, `fair_housing_flags`)
- `_tags` — JSON arrays of label strings (e.g., `ai_buyer_archetype_tags`, `lifestyle_tags`)
- `_at` — all timestamp columns (e.g., `computed_at`, `generated_at`, `archived_at`)
- `_version` — integer version counters on computed profile rows when a separate version column is needed alongside the main `version` integer

**EAV meta key naming standards:**
- All EAV meta keys use `snake_case` with no spaces, no hyphens, no camelCase
- No table prefix in the key name itself; the table is identified by the meta table context
- Keys must be descriptive, not abbreviated: `pet_max_weight_lbs` not `pet_wt`; `min_income_requirement` not `min_inc_req`
- Keys must be unique within the EAV table for that workflow

**Compatibility key naming:**
- Demand-side field names mirror their supply-side counterparts with a tolerance or requirement suffix
- Examples: supply `flood_zone_code` → demand `flood_zone_tolerance`; supply `association_fee_amount` → demand `hoa_max_monthly_fee`; supply `inspection_contingency_acceptance` → demand `inspection_contingency_required`

**AI-generated field prefixes:**
- All computed or AI-derived columns on the DNA profile tables use the `ai_` prefix: `ai_buyer_archetype_tags`, `ai_marketing_hooks`
- User-input source fields never carry the `ai_` prefix regardless of whether they feed AI pipelines

**Archived and deprecated row lifecycle:**
- `archived_at` is set (not null) when a computed row is superseded by a newer version
- No computed or generated row is ever hard-deleted; only rows with a null `archived_at` value are treated as the current active record — all queries must exclude rows that have a non-null `archived_at`
- The `version` integer increments on each recomputation of the same listing's profile

---

## 4. New Table Definitions

All five tables in this section share the following universal rules:

- **Computed/generated asynchronously post-save only.** No user-facing form save or edit workflow triggers synchronous computation against these tables.
- **Append-versioned, never overwritten.** When a profile, score, or output is recomputed, a new row is inserted. The prior row's `archived_at` timestamp is set. No row is ever silently updated in place.
- **Advisory only.** Values in these tables never gate user actions, alter offer ordering, suppress bids, or influence negotiation eligibility without explicit human action.
- **Write-isolated.** These tables may read from source-of-truth listing tables. They never write back to source-of-truth tables.
- **Non-public by default.** No column in these tables is exposed in public-facing interfaces unless a separately versioned implementation phase explicitly engineers and approves that exposure.
- **Operationally independent.** Listing workflows remain fully functional if these tables are empty, delayed, or unavailable.

### 4.1 `property_dna_profiles`

**Purpose:** Computed supply-side intelligence profile for Seller and Landlord listings. Stores per-category completeness scores (C1–C10), an overall weighted composite score, AI-generated archetype tags and marketing hooks, and Phase 7 reserved area-compatibility columns. One active row per listing; historical rows are retained with `archived_at` set.

**This is a computed table — user input is never written here.** Source listing data is read at computation time via the supply-side EAV meta and native column fields. No value from this table is displayed as a user-visible fact; all values are advisory.

**DNA completeness score weighting:** The `overall_dna_completeness` score is a weighted composite of per-category fill rates. The per-category weight applied by workflow is:

| Intelligence Category | Seller Weight | Landlord Weight |
|----------------------|--------------|----------------|
| C1 — Physical Attributes | 25 | 25 |
| C2 — Financial Intelligence | 20 | 15 |
| C3 — Location & Lifestyle | 10 | 10 |
| C4 — Condition & Maintenance | 15 | 10 |
| C5 — Legal & Compliance | 10 | 10 |
| C6 — Flexibility & Negotiation | 10 | 10 |
| C7 — Occupant / Tenant Qualification | 0 | 10 |
| C8 — Marketing & Uniqueness (conditional) | 5 | 5 |
| C9 — Compatibility & Matching | 0 | 5 |
| C10 — Commercial & Investment (conditional) | 5 | 0 |

C8 contributes only for non-vacant-land property types. C10 contributes only for Income, Commercial, or Business property types.

**Source:** Phase 1 audit Section 10.5.

| Column Name | Type | Nullable | Notes |
|-------------|------|----------|-------|
| `id` | 64-bit integer, primary key | No | Auto-incrementing |
| `listing_type` | Enumerated string | No | `seller` or `landlord` |
| `listing_id` | 64-bit integer | No | References the respective auction table; resolved via `listing_type` |
| `version` | Integer | No | Increments on each recomputation; starts at 1 |
| `source_listing_updated_at` | Timestamp | No | Snapshot of source listing's `updated_at` at computation time; immutable after row creation |
| `physical_score` | Decimal (5,2) | Yes | C1 category fill-rate score, 0.00–100.00; advisory only |
| `financial_score` | Decimal (5,2) | Yes | C2 category fill-rate score; advisory only |
| `location_score` | Decimal (5,2) | Yes | C3 category fill-rate score; advisory only |
| `condition_score` | Decimal (5,2) | Yes | C4 category fill-rate score; advisory only |
| `legal_score` | Decimal (5,2) | Yes | C5 category fill-rate score; advisory only |
| `flexibility_score` | Decimal (5,2) | Yes | C6 category fill-rate score; advisory only |
| `occupant_qualification_score` | Decimal (5,2) | Yes | C7 category fill-rate score; advisory only; Landlord only |
| `marketing_score` | Decimal (5,2) | Yes | C8 category fill-rate score; conditional on non-vacant-land property type |
| `compatibility_score` | Decimal (5,2) | Yes | C9 category fill-rate score; advisory only |
| `commercial_score` | Decimal (5,2) | Yes | C10 category fill-rate score; conditional on Income/Commercial/Business property type |
| `overall_dna_completeness` | Decimal (5,2) | Yes | Weighted composite of per-category scores per the weight table above; advisory only |
| `ai_buyer_archetype_tags` | JSON array of strings | Yes | Suggested buyer archetype labels from the nine-label registry in Section 3.7; no protected-class labels |
| `ai_marketing_hooks` | JSON array of strings | Yes | Short marketing hook strings derived from structured inputs; non-public by default; advisory only |
| `walk_score` | Integer | Yes | Walk Score API value — **Reserved / Future Use Only — Not Implemented** (F-01) |
| `transit_score` | Integer | Yes | Walk Score API transit value — **Reserved / Future Use Only — Not Implemented** (F-01) |
| `bike_score` | Integer | Yes | Walk Score API bike value — **Reserved / Future Use Only — Not Implemented** (F-01) |
| `school_rating` | Decimal (5,2) | Yes | GreatSchools or equivalent API score — **Reserved / Future Use Only — Not Implemented** (F-02) |
| `flood_zone_verified` | String | Yes | FEMA NFIP API auto-populated flood zone — **Reserved / Future Use Only — Not Implemented** (F-03) |
| `estimated_monthly_utilities` | Decimal (10,2) | Yes | EIA regional / Arcadia API estimate — **Reserved / Future Use Only — Not Implemented** (F-05) |
| `computed_at` | Timestamp | No | When this row was computed |
| `archived_at` | Timestamp | Yes | Set when superseded by a newer version row; null = current active row |
| `created_at` | Timestamp | No | Row creation timestamp |
| `updated_at` | Timestamp | No | Row last-touch timestamp |

**Index recommendations (plain English):** A composite index on (`listing_type`, `listing_id`, `archived_at`) supports fast retrieval of the current active row per listing. An index on `overall_dna_completeness` supports completeness-based filtering in agent-facing dashboards.

### 4.2 `buyer_tenant_dna_profiles`

**Purpose:** Computed demand-side intelligence profile for Buyer and Tenant listings. Stores preference completeness, lifestyle tags, deal-breaker signals, and the primary archetype label. One active row per listing; historical rows retained.

**This is a computed table — user input is never written here.**

**DNA completeness score weighting for demand-side:**

| Intelligence Category | Buyer Weight | Tenant Weight |
|----------------------|-------------|--------------|
| C1 — Physical Attributes | 25 | 25 |
| C2 — Financial Intelligence | 20 | 15 |
| C3 — Location & Lifestyle | 15 | 15 |
| C4 — Condition & Maintenance | 5 | 0 |
| C5 — Legal & Compliance | 5 | 0 |
| C6 — Flexibility & Negotiation | 15 | 10 |
| C7 — Occupant / Tenant Qualification | 0 | 20 |
| C8 — Marketing & Uniqueness (conditional) | 5 | 5 |
| C9 — Compatibility & Matching | 10 | 10 |

**Source:** Phase 1 audit Section 10.6.

| Column Name | Type | Nullable | Notes |
|-------------|------|----------|-------|
| `id` | 64-bit integer, primary key | No | Auto-incrementing |
| `listing_type` | Enumerated string | No | `buyer` or `tenant` |
| `listing_id` | 64-bit integer | No | References the respective auction table; resolved via `listing_type` |
| `version` | Integer | No | Increments on each recomputation |
| `source_listing_updated_at` | Timestamp | No | Snapshot of source listing's `updated_at` at computation time; immutable after row creation |
| `preference_completeness` | Decimal (5,2) | Yes | Percentage of optional profile fields filled, 0.00–100.00; advisory only; drives "Improve your DNA" prompts |
| `lifestyle_tags` | JSON array of strings | Yes | Inferred lifestyle signals from AI FAQ answer intelligence-category tagging; C3 and C9 answers; no protected-class tags |
| `deal_breaker_flags` | JSON object | Yes | Structured deal-breaker signals from user input (e.g., flood zone tolerance, pet requirements, smoking preference); no protected-class proxy inputs |
| `archetype_label` | String | Yes | Primary archetype label from the nine-label registry in Section 3.7; advisory only |
| `commute_polygon_cache` | Long text | Yes | Cached drive-time polygon for commute destination — **Reserved / Future Use Only — Not Implemented** (F-03); must not appear in any UI, API, export, filter, or AI prompt until F-03 phase is separately approved |
| `computed_at` | Timestamp | No | When this row was computed |
| `archived_at` | Timestamp | Yes | Set when superseded by a newer version row |
| `created_at` | Timestamp | No | Row creation timestamp |
| `updated_at` | Timestamp | No | Row last-touch timestamp |

**Index recommendations (plain English):** Composite index on (`listing_type`, `listing_id`, `archived_at`) for fast active-row retrieval. Index on `archetype_label` for filtering demand profiles by archetype in agent dashboards.

### 4.3 `listing_compatibility_scores`

**Purpose:** Cross-listing junction table recording the computed compatibility score between one demand-side listing (Buyer or Tenant) and one supply-side listing (Seller or Landlord). This is a scoring artifact — it is never user-authored. Every score is attributable to a specific scoring framework version and retains immutable references to source listing state at computation time.

**Source:** Phase 1 audit Section 10.7.

**Scoring dimension weights stored in score column structure:**
- Physical Match: 30%
- Financial Match: 25%
- Location Match: 20%
- Terms Match: 15%
- Deal-Breaker Gates: 10% (any trigger zeroes the affected dimension score)

| Column Name | Type | Nullable | Notes |
|-------------|------|----------|-------|
| `id` | 64-bit integer, primary key | No | Auto-incrementing |
| `demand_listing_type` | Enumerated string | No | `buyer` or `tenant` |
| `demand_listing_id` | 64-bit integer | No | Resolved via `demand_listing_type` |
| `supply_listing_type` | Enumerated string | No | `seller` or `landlord` |
| `supply_listing_id` | 64-bit integer | No | Resolved via `supply_listing_type` |
| `version` | Integer | No | Increments with each recomputation of this pair |
| `scoring_framework_version` | String | No | Named version of the scoring algorithm that produced this row; required for Constraint 28 traceability |
| `demand_listing_updated_at_snapshot` | Timestamp | No | Snapshot of demand listing's `updated_at` at computation time; immutable |
| `supply_listing_updated_at_snapshot` | Timestamp | No | Snapshot of supply listing's `updated_at` at computation time; immutable |
| `overall_score` | Decimal (5,2) | Yes | Composite score 0.00–100.00; advisory only; never gates user action |
| `physical_match_score` | Decimal (5,2) | Yes | C1 dimension match score (weight: 30%); advisory only |
| `financial_match_score` | Decimal (5,2) | Yes | C2 dimension match score (weight: 25%); advisory only |
| `location_match_score` | Decimal (5,2) | Yes | C3 dimension match score (weight: 20%); advisory only |
| `terms_match_score` | Decimal (5,2) | Yes | C6 dimension match score (weight: 15%); advisory only |
| `deal_breaker_triggered` | Boolean | No | True if any deal-breaker mismatch zeroed one or more sub-scores; default false |
| `deal_breaker_flags` | JSON array of objects | Yes | Each entry identifies the triggering supply/demand field pair and a human-readable mismatch reason; no protected-class content |
| `score_explanation` | JSON object | Yes | Human-readable per-dimension explanation traceable to named structured source fields; satisfies Constraint 19 explainability requirement |
| `computed_at` | Timestamp | No | When this row was computed |
| `archived_at` | Timestamp | Yes | Set when superseded by a newer version row |
| `created_at` | Timestamp | No | Row creation timestamp |

**Score display rules** (from Phase 1 audit Section 12.2):

| Score Band | Label | Color |
|------------|-------|-------|
| 80–100 | Strong Match | Green |
| 60–79 | Possible Match | Yellow |
| 40–59 | Partial Match | Orange |
| 0–39 | Low Match | Red |

**Index recommendations (plain English):** Composite index on (`demand_listing_type`, `demand_listing_id`, `archived_at`) and a separate composite index on (`supply_listing_type`, `supply_listing_id`, `archived_at`) for bidirectional active-row retrieval. Index on `overall_score` for score-sorted display.

### 4.4 `ai_faq_answers`

**Purpose:** Structured storage of AI FAQ answers with intelligence category tagging and normalized value extraction. This table enables downstream DNA profile computation, compatibility scoring, and marketing intelligence generation from the existing AI FAQ answer corpus.

> **CONFIRM BEFORE MIGRATION — CRITICAL:** The current storage mechanism for AI FAQ answers is unknown from the Phase 1 audit. The table name, column structure, and storage pattern (dedicated table vs. EAV meta keys vs. JSON blob) must be confirmed by reading the Livewire save/load logic for the AI FAQ form before any Phase F migration is written. If answers are currently stored as EAV meta keys (key = question_key, value = answer_text), this represents a restructure migration that requires a backfill script to preserve all existing answers before any old storage rows are altered or removed. Do not assume this is a new table.

| Column Name | Type | Nullable | Notes |
|-------------|------|----------|-------|
| `id` | 64-bit integer, primary key | No | Auto-incrementing |
| `listing_type` | Enumerated string | No | `seller`, `landlord`, `buyer`, or `tenant` |
| `listing_id` | 64-bit integer | No | References the source listing record |
| `question_key` | String | No | e.g., `faq_q1`, `faq_bo_q3`, `faq_c_q11`; maps to the key in the existing AI FAQ config files |
| `question_group` | String | Yes | Logical group within the config file; e.g., `condition_maintenance`, `location_neighborhood`, `commercial`; derived from group headings in each `ai_faq_*.php` config file |
| `intelligence_category` | String | Yes | One of C1 through C10; applied at storage time using mappings in Phase 1 audit Sections 3.8, 4.5, 5.5, 6.6; store primary category for multi-category questions |
| `answer_text` | Long text | Yes | Raw user-entered answer; unchanged from current storage form |
| `answer_normalized` | JSON object | Yes | Extracted structured values from raw answer text; populated asynchronously during Phase B normalization; null until normalization runs; values are extracted from user text — never hallucinated or inferred beyond what the text directly states |
| `created_at` | Timestamp | No | Row creation timestamp |
| `updated_at` | Timestamp | No | Row last-touch timestamp |

**`answer_normalized` structure examples** (extracted from raw text; never hallucinated):
- Buyer faq_q9 commute text → `{ "zip": "32801", "city": "Orlando" }`
- Seller faq_q11 utility cost text → `{ "amount": 185.00, "currency": "USD" }`
- Landlord faq_q1 HVAC age text → `{ "estimated_year": 2019 }`
- Landlord faq_q4 appliance age text → `{ "estimated_year": 2021 }`

**Three high-priority normalization targets** (from Phase 1 audit Section 11.2 — Phase B):
1. Buyer faq_q9 — commute destination and acceptable time → extract ZIP/city, time integer
2. Seller faq_q11 — average monthly utilities → extract dollar amount
3. Landlord faq_q1 (HVAC), faq_q4 (appliances) — age references → extract year integer

**Index recommendations (plain English):** Composite index on (`listing_type`, `listing_id`) for per-listing answer retrieval. Index on `intelligence_category` for category-aggregated DNA computation.

### 4.5 `dna_marketing_outputs`

**Purpose:** Stores all AI-generated copy variants for listing marketing. One row per output type per variant per listing per generation round. No copy is displayed to any user until `fair_housing_reviewed = true`. All rows are append-versioned — superseded rows have `archived_at` set and are never hard-deleted.

**This is a generated output table — it is not user-authored.** The content in this table is advisory and supplementary. It does not replace any user-authored listing description or agent-entered field. Generated copy must not imply certainty, guarantees, factual approvals, or claims beyond what the underlying structured input data directly supports.

| Column Name | Type | Nullable | Notes |
|-------------|------|----------|-------|
| `id` | 64-bit integer, primary key | No | Auto-incrementing |
| `listing_type` | Enumerated string | No | `seller`, `landlord`, `buyer`, or `tenant` |
| `listing_id` | 64-bit integer | No | Source listing reference |
| `output_type` | String | No | Values defined in Section 3.8: `listing_narrative`, `buyer_archetype_brief`, `social_caption_pack`, `email_subject_lines`, `rental_listing_narrative`, `tenant_persona_brief`, `buyer_brief_summary`, `tenant_brief_summary`, `match_alert` |
| `variant_index` | Integer | No | Allows multiple variants per output_type per listing; starts at 1 (e.g., caption variant 1, 2, 3) |
| `content` | Long text | No | Full generated copy text for this variant |
| `fair_housing_reviewed` | Boolean | No | **Defaults to false**; must be set true by the word-filter process before any display is permitted |
| `fair_housing_flags` | JSON array | Yes | Word-filter hits that triggered manual review; empty array when clean; populated before `fair_housing_reviewed` can be set true |
| `generated_by` | String | No | Identifier of the generation model or engine version used |
| `version` | Integer | No | Increments with each regeneration of this output_type + variant_index pair for this listing |
| `source_listing_updated_at` | Timestamp | No | Snapshot of source listing's `updated_at` at generation time; immutable; satisfies Constraint 29 |
| `scoring_version` | String | Yes | Framework version that informed this output; for traceability per Constraint 28 |
| `generated_at` | Timestamp | No | When this output was generated |
| `archived_at` | Timestamp | Yes | Set when superseded by a newer generation; null = current active output |
| `created_at` | Timestamp | No | Row creation timestamp |

**Fair Housing review workflow:**
1. Content is generated by the AI engine and stored with `fair_housing_reviewed = false`
2. The word-filter runs synchronously at generation time against the Phase 1 audit Section 14.3 seed blocklist
3. If no blocklist terms are detected, `fair_housing_reviewed` is set to true and the row becomes displayable
4. If blocklist terms are detected, `fair_housing_flags` is populated with matched terms and the row enters a human review queue
5. A human reviewer may clear the flags and set `fair_housing_reviewed = true` or mark the row for regeneration
6. No content with `fair_housing_reviewed = false` is rendered in any user-facing interface

**Index recommendations (plain English):** Composite index on (`listing_type`, `listing_id`, `output_type`, `archived_at`) for fast active-row retrieval per output type. Index on `fair_housing_reviewed` to support review queue queries.

---

## 5. Existing Fields That Can Be Reused As-Is

The following tables list fields confirmed present in the Phase 1 audit that are immediately usable for Phase 5 compatibility scoring without any schema change. Only fields explicitly confirmed in the Phase 1 audit source file inventory are listed. Fields are not listed unless their presence was confirmed by the audit's direct reading of the Blade tab partials or Livewire components.

### 5.1 Seller Supply-Side (Phase 1 audit Sections 3.2–3.7)

| Field (wire:model) | Location Confirmed | Scoring Use |
|-------------------|-------------------|------------|
| `property_type` | Property Preferences tab (§3.2) | Type exact match against buyer `property_type` |
| `property_items` | Property Preferences tab (§3.2) | Subtype overlap match |
| `bedrooms` | Property Preferences tab (§3.2) | Seller supply ≥ buyer minimum |
| `bathrooms` | Property Preferences tab (§3.2) | Seller supply ≥ buyer minimum |
| `minimum_heated_square` | Property Preferences tab (§3.2) | Seller heated sqft ≥ buyer `minimum_sqft` |
| `total_square_feet` | Property Preferences tab (§3.2) | Total sqft reference for scoring |
| `garage_needed` | Property Preferences tab (§3.2) | Garage match against buyer garage preference |
| `pool_needed` | Property Preferences tab (§3.2) | Pool match against buyer pool preference |
| `view_preference` | Property Preferences tab (§3.2) | View overlap match |
| `non_negotiable_amenities` | Property Preferences tab (§3.2) | All buyer non-negotiables must appear in supply set |
| `number_of_units` | Property Preferences tab (§3.2) | Unit count ≥ buyer requirement (Income only) |
| `condition_prop` | Property Preferences tab (§3.2) | Condition compared against new buyer `fixer_upper_tolerance` (B-07) |
| `year_built` | Property Preferences tab (§3.2) | Condition age signal for C4 scoring |
| `sale_provision` | Seller Terms tab (§3.3) | Buyer's acceptable sale provisions overlap |
| `target_closing_date` | Seller Terms tab (§3.3) | Closing timeframe compatibility |
| `offered_financing` | Seller Terms tab (§3.3) | At least one financing type overlaps buyer's list |
| `maximum_budget` | Seller Terms tab (§3.3) | Seller desired price ≤ buyer `maximum_budget` |
| `occupant_status` | Seller Terms tab (§3.3) | Occupancy signal for C6 scoring |
| `minimum_cap_rate` | Financial Details tab (§3.4) | Supply cap rate ≥ buyer `min_cap_rate_target` (B-09; Income/Commercial) |
| `minimum_annual_net_income` | Financial Details tab (§3.4) | Income/investment scoring (C10) |
| `has_hoa` | Tax/Legal/HOA tab (§3.7) | HOA presence match against buyer `hoa_acceptance` (B-06) |
| `association_fee_amount` | Tax/Legal/HOA tab (§3.7) | Fee ≤ buyer `hoa_max_monthly_fee` (B-06) |
| `association_fee_frequency` | Tax/Legal/HOA tab (§3.7) | Normalize to monthly for HOA fee comparison |
| `flood_zone_code` | Tax/Legal/HOA tab (§3.7) | Zone within buyer `flood_zone_tolerance` (B-08) |
| `leasing_restrictions` | Tax/Legal/HOA tab (§3.7) | Rental restriction signal for C5 scoring |

### 5.2 Landlord Supply-Side (Phase 1 audit Sections 4.2–4.3)

| Field (wire:model) | Location Confirmed | Scoring Use |
|-------------------|-------------------|------------|
| `property_type` | Property Preferences tab (§4.2 via §4.6 gap confirmation) | Type exact match |
| `property_items` | Property Preferences tab (§4.2) | Subtype overlap |
| `bedrooms` | Property Preferences tab (§4.2 — confirmed in §4.6) | Landlord supply ≥ tenant minimum |
| `bathrooms` | Property Preferences tab (§4.2 — confirmed in §4.6) | Landlord supply ≥ tenant minimum |
| `non_negotiable_amenities` | Property Preferences tab (§4.2 — confirmed in §4.6) | All tenant non-negotiables present |
| `leasing_spaces` | Lease Terms tab (§4.3) | Overlaps with tenant `leasing_spaces_tenant` |
| `desired_lease_length` | Lease Terms tab (§4.3) | At least one term overlaps tenant `lease_for` |
| `desired_lease_price` | Lease Terms tab (§4.3) | Landlord price ≤ tenant `budget` |
| `utilities` | Lease Terms tab (§4.3) | Utility inclusion signal for C2 scoring |
| `occupancy_status` | Lease Terms tab (§4.3 confirmed in §4.6) | Availability signal for C6 |
| `occupied_until_date` | Lease Terms tab (§4.3 confirmed in §4.6) | Paired with tenant move-in window |
| `property_city` | Listing Details (confirmed §4.6 gap context) | Geographic match against tenant `cities` |
| `property_county` | Listing Details (confirmed §4.6 gap context) | Geographic match against tenant `counties` |
| `property_state` | Listing Details (confirmed §4.6 gap context) | Geographic match against tenant `state` |

### 5.3 Buyer Demand-Side (Phase 1 audit Sections 5.2–5.3)

| Field (wire:model) | Location Confirmed | Scoring Use |
|-------------------|-------------------|------------|
| `cities` | Property Preferences tab (§5.2) | Buyer acceptable city list; supply city must appear |
| `counties` | Property Preferences tab (§5.2) | Buyer acceptable county list (required field) |
| `state` | Property Preferences tab (§5.2) | State match (required field) |
| `property_type` | Property Preferences tab (§5.2) | Type exact match (required field) |
| `property_items` | Property Preferences tab (§5.2) | Acceptable subtype list (required field) |
| `bedrooms` | Property Preferences tab (§5.2) | Buyer minimum bedrooms |
| `bathrooms` | Property Preferences tab (§5.2) | Buyer minimum bathrooms |
| `minimum_sqft` | Property Preferences tab (§5.2) | Buyer minimum square footage |
| `garage` | Property Preferences tab (§5.2) | Yes / No / Either preference |
| `pool` | Property Preferences tab (§5.2) | Yes / No / Either preference |
| `view_preferences` | Property Preferences tab (§5.2) | View overlap match |
| `non_negotiable_amenities` | Property Preferences tab (§5.2) | Must all be present in supply |
| `number_of_units` | Property Preferences tab (§5.2) | Minimum units required (Income) |
| `condition_prop_buyer_json` | Property Preferences tab (§5.2) | Hidden JSON of acceptable condition values |
| `sale_provision` | Purchasing Terms tab (§5.3) | Acceptable sale provisions overlap |
| `target_closing_date` | Purchasing Terms tab (§5.3) | Closing timeframe (required field) |
| `maximum_budget` | Purchasing Terms tab (§5.3) | Buyer maximum budget (required field) |
| `offered_financing` | Purchasing Terms tab (§5.3) | Buyer financing type (required field) |

### 5.4 Tenant Demand-Side (Phase 1 audit Sections 6.2–6.4)

| Field (wire:model) | Location Confirmed | Scoring Use |
|-------------------|-------------------|------------|
| `cities` | Property Details tab (§6.2) | Tenant acceptable city list |
| `counties` | Property Details tab (§6.2) | Tenant acceptable county list (required field) |
| `state` | Property Details tab (§6.2) | State match (required field) |
| `property_type` | Property Details tab (§6.2) | Type match |
| `property_items` | Property Details tab (§6.2) | Acceptable subtype list |
| `bedrooms` | Property Details tab (§6.2) | Tenant minimum bedrooms |
| `bathrooms` | Property Details tab (§6.2) | Tenant minimum bathrooms |
| `minimum_sqft` | Property Details tab (§6.2) | Tenant minimum square footage |
| `garage` | Property Details tab (§6.2) | Yes / No / Either preference |
| `pool` | Property Details tab (§6.2) | Yes / No / Either preference |
| `non_negotiable_amenities` | Property Details tab (§6.2) | Must all be present in landlord supply set |
| `budget` | Leasing Terms tab (§6.3) | Tenant maximum monthly rent (required field) |
| `lease_for` | Leasing Terms tab (§6.3) | Acceptable lease terms (required field) |
| `lease_date` | Leasing Terms tab (§6.3) | Proposed lease start date (required field) |
| `leasing_spaces_tenant` | Leasing Terms tab (§6.3) | Acceptable leasing space types (required field) |
| `number_occupant` | Pre-Screening tab (§6.4) | Occupant count (required field) |
| `monthly_income` | Pre-Screening tab (§6.4) | Tenant income for pre-qualification scoring (required field) |
| `pets` | Pre-Screening tab (§6.4) | Pet presence match against landlord `pet_policy` (L-03) |
| `type_of_pets` | Pre-Screening tab (§6.4) | Pet species match against landlord `pet_species_allowed` (L-03) |
| `weight_of_pets` | Pre-Screening tab (§6.4) | Pet weight match against landlord `pet_max_weight_lbs` (L-03) |
| `service_animal` | Pre-Screening tab (§6.4) | Service animal flag; cannot be restricted per FHA |
| `support_animal` | Pre-Screening tab (§6.4) | ESA flag; cannot be restricted per FHA |
| `screening_concerns` | Pre-Screening tab (§6.4) | Pre-screening disclosure flag |

---

## 6. Missing Fields Priority Tiers

All 29 new fields (S-01 through T-07) plus five reserved future fields (F-01 through F-05) are organized into four tiers. Tier assignment is derived from the Phase 1 audit's Final Recommendations (Section 16) and Required vs Optional vs Premium vs Future Matrix (Section 9).

### Tier 1 — Highest Priority (do first; highest impact relative to effort)

| Phase 1 Ref | Field | One-Line Rationale |
|-------------|-------|-------------------|
| L-02 | Available Date | Only Required-tier field in the entire list; tenant move-in matching is impossible without a structured available date on the landlord listing (Phase 1 §16 Priority 1 item 1) |
| B-02 | Buyer Purchase Purpose / Archetype | One Required-tier select unlocks all downstream buyer-side segmentation, archetype tagging, and compatibility scoring (Phase 1 §16 Priority 1 item 2) |
| B-01 | Commute Destination + Max Time + Mode | Most actionable location signal for buyer geographic matching; entirely absent from both buyer structured form and buyer AI FAQ (Phase 1 §16 Priority 1 item 3) |
| T-01 | Commute Destination + Max Time + Mode | Commute question is entirely absent from all 27 tenant AI FAQ questions; the single most critical missing tenant signal (Phase 1 §6.7 gap and §16 Priority 1 item 3) |
| T-06 | Credit Score Range (Reinstatement) | Field already exists in commented-out code; compliance framing update is the only requirement; provides pre-screening transparency (Phase 1 §16 Priority 1 item 4) |
| B-08 | Flood Zone Tolerance | Supply-side `flood_zone_code` already exists with full FEMA designations; this demand-side tolerance enables immediate automated filtering with zero new supply-side work required (Phase 1 §16 Priority 2 item 4) |
| B-06 | HOA Acceptance + Maximum HOA Fee Tolerance | Supply-side `has_hoa`, `association_fee_amount`, `association_fee_frequency` already exist in full structured detail; demand-side tolerance creates immediate high-value matching (Phase 1 §16 Priority 2 item 3) |

### Tier 2 — High Impact, Moderate Effort

| Phase 1 Ref | Field(s) | Rationale |
|-------------|----------|-----------|
| L-03 | Pet Policy (5 sub-keys) | Pet mismatches are among the most common early tenancy failures; existing tenant pre-screening already collects pet data (§6.4), creating immediate matching opportunity |
| L-01 | Year Built (Landlord) | Seller form collects year_built for four property types; absence from Landlord is an anomaly; foundational C4 condition-scoring input |
| S-04 + S-05 | Inspection Contingency Acceptance + Appraisal Contingency Acceptance | Seller Tax/Legal/HOA tab already rich; adding two select fields to Seller Terms enables direct compatibility matching against corresponding buyer fields (B-03, B-04) |
| B-03 + B-04 | Inspection Contingency Required + Appraisal Contingency Required | Demand-side mirrors of S-04 and S-05; must be added together with supply-side fields for the matching pair to function |
| B-07 | Fixer-Upper Tolerance | Existing `condition_prop` on seller side is immediately usable; buyer tolerance enables automatic filtering of as-is and distressed listings |
| T-02 | Rental Purpose / Tenant Archetype | No structured rental-purpose field exists for tenants; enables marketing segmentation and matching without Fair Housing concerns |
| L-05 + T-03 | Security Deposit Amount + Move-In Budget for Upfront Costs | Paired fields: landlord's deposit amount (L-05) matched against tenant's upfront budget (T-03); addresses a common early-tenancy friction point |

### Tier 3 — Enrichment (optional; improves intelligence quality)

| Phase 1 Ref | Field | Category |
|-------------|-------|----------|
| S-01 | Average Monthly Utility Cost | C2; feeds AI cost-of-ownership narrative |
| S-02 | Year of Last Significant Renovation | C4, C8; "effective age" signal; pairs with existing `year_built` |
| S-03 | Seller Motivation Category (agent-only) | C6, C8; encrypted; agent-visible only; feeds negotiation coaching |
| S-06 | Leaseback Required | C6; post-close leaseback structured capture |
| S-07 | Occupancy Rate (Income/Commercial) | C10; structured complement to AI FAQ faq_ci_q1 narrative |
| L-04 | Smoking Policy (Landlord) | C5, C9; demand-side counterpart T-07 created simultaneously |
| L-06 | Minimum Income Requirement (agent-only) | C7; agent-visible only; pre-qualification scoring without public disclosure |
| L-07 | Subletting Policy | C5; structured replacement for AI FAQ faq_q20 |
| B-05 | Sale of Current Home Contingency | C6; common seller dealbreaker |
| B-09 | Minimum Cap Rate Target (Income/Commercial) | C10; mirrors existing seller `minimum_cap_rate` |
| T-04 | Move-In Date Range (Earliest + Latest) | C6, C9; supplements single `lease_date` with a window |
| T-05 | Accessibility Requirements (tenant-only filter) | C1, C9; preference-filter only — never surfaced to landlords |
| T-07 | Smoking Preference | C9; matches against new landlord L-04 |

### Tier 4 — Future / External — Reserved

All five fields require third-party API integration and separate governance approval before implementation. Each carries the label: **Reserved / Future Use Only — Not Implemented**

| Phase 1 Ref | Feature | Source API | Status |
|-------------|---------|-----------|--------|
| F-01 | Walk Score / Transit Score / Bike Score | Walk Score API | Reserved / Future Use Only — Not Implemented |
| F-02 | School Rating | GreatSchools API | Reserved / Future Use Only — Not Implemented |
| F-03 | Drive-Time API (Commute Polygon) | Google Maps or HERE Maps drive-time API | Reserved / Future Use Only — Not Implemented |
| F-04 | AI-Enhanced Compatibility Scoring Engine (Phase 5) | Prerequisite: Phases 1–4 complete | Reserved / Future Use Only — Not Implemented |
| F-05 | Historical Utility Cost Estimates | EIA regional data / Arcadia API | Reserved / Future Use Only — Not Implemented |

---

## 7. AI FAQ Fields Keep vs Promote

The following analysis determines which existing AI FAQ questions provide greatest value as free-text narrative (keep) and which provide a concrete matchable structured value that warrants a new form control (promote). Promoted questions retain their free-text FAQ version alongside the new structured field — the FAQ answer provides supplementary narrative context; the structured field provides the machine-readable value.

### 7.1 Keep as Free-Text

These questions require narrative answers where structured capture would lose meaningful value or where no single matchable field adequately represents the answer.

| Workflow | Question Key | Topic | Reason to Keep as Free-Text |
|----------|-------------|-------|------------------------------|
| Seller | faq_q1 | Roof age and condition | Multi-aspect narrative (age, material, warranty, issues) that structured capture would over-simplify |
| Seller | faq_q2 | HVAC systems | Multi-system narrative; repair history context required |
| Seller | faq_q5 | Known issues or required repairs | Legal disclosure narrative; must remain open-ended for completeness |
| Seller | faq_q9 | Neighborhood character and walkability | Subjective; no structured equivalent captures the nuance |
| Seller | faq_q12 | Best feature / hidden gem | Marketing narrative; structured capture loses the storytelling value |
| Seller | faq_q13 | Curb appeal and exterior condition | Subjective marketing description |
| Landlord | faq_q1 | AC / HVAC age and service history | Multi-system narrative; pairs with C4 scoring but cannot be reduced to a single value |
| Landlord | faq_q9 | Neighborhood safety and character | Subjective; no structured match counterpart |
| Landlord | faq_q13 | Noise level | Subjective; context-dependent |
| Landlord | faq_q22 | Showing availability and scheduling | Operational; not a matchable field |
| Buyer | faq_q4 | Lifestyle priorities | Nuanced multi-value preference narrative |
| Buyer | faq_q5 | Deal-breakers | Open-ended; partial coverage from structured deal-breaker fields but narrative captures edge cases |
| Buyer | faq_q10 | Preferred neighborhood vibe | Subjective; no structured supply-side counterpart |
| Buyer | faq_q27 | Relocation motivation | Context narrative; not a match signal |
| Tenant | faq_q2 | Day-to-day living priorities | Nuanced lifestyle narrative |
| Tenant | faq_q3 | Ideal neighborhood vibe | Subjective |
| Tenant | faq_q20 | Biggest concern in the rental search | Narrative; context-dependent |

### 7.2 Promote to Structured

These questions contain a concrete matchable value that benefits from structured capture. The FAQ question is retained as supplementary narrative alongside the new structured field — it is not removed or retired (except where noted).

| Workflow | Question Key | Topic | New Structured Field | Phase 1 Ref | Note |
|----------|-------------|-------|---------------------|-------------|------|
| Buyer | faq_q9 | Commute destination and acceptable time | `commute_destination_zip`, `max_commute_minutes`, `commute_mode` | B-01 | FAQ retained for commute context narrative |
| Buyer | faq_q1 | Primary use / purpose for purchase | `purchase_purpose` | B-02 | FAQ retained for expanded intent narrative |
| Buyer | faq_q17 | Fixer-upper / renovation tolerance | `fixer_upper_tolerance` | B-07 | FAQ retained for renovation detail narrative |
| Buyer | faq_q13 | HOA acceptance level | `hoa_acceptance`, `hoa_max_monthly_fee` | B-06 | FAQ retained for HOA preference context |
| Buyer | faq_q14 | Flood zone concern / tolerance | `flood_zone_tolerance` | B-08 | FAQ retained for flood zone reasoning narrative |
| Buyer | faq_q22 | First-time buyer status | Signals `purchase_purpose` value | B-02 | Archetype derivation input; FAQ retained |
| Buyer | faq_ci_q5 | Target cap rate | `min_cap_rate_target` | B-09 | Income/Commercial only; FAQ retained for investment criteria narrative |
| Landlord | faq_q27 | When unit will be available | `available_date` | L-02 | FAQ retained as supplementary narrative; `available_date` structured field is the authoritative matchable record |
| Landlord | faq_q14 | Pet policy details | `pet_policy`, `pet_max_weight_lbs`, `pet_species_allowed`, `pet_deposit_amount`, `pet_monthly_fee` | L-03 | FAQ retained for pet policy context; structured fields drive matching |
| Landlord | faq_q15 | Smoking policy | `smoking_policy` | L-04 | FAQ retained for smoking policy context |
| Landlord | faq_q20 | Subletting policy | `subletting_policy` | L-07 | FAQ retained for subletting detail |
| Landlord | faq_q25 | Security deposit amount and structure | `security_deposit_amount` | L-05 | FAQ retained for deposit structure narrative |
| Landlord | faq_q18 | Tenant screening criteria (income, credit) | `min_income_requirement` | L-06 | FAQ retained; structured field is agent-visible only |
| Seller | faq_q11 | Average monthly utility costs | `avg_monthly_utility_cost` | S-01 | FAQ retained for seasonal utility detail |
| Seller | faq_q21 | Seller motivation / reason for selling | `seller_motivation_category` | S-03 | FAQ retained for narrative; structured field is agent-visible only |
| Seller | faq_q24 | Contingency philosophy | `inspection_contingency_acceptance`, `appraisal_contingency_acceptance` | S-04, S-05 | FAQ retained for contingency context narrative |
| Seller | faq_ci_q1 | Occupancy rate (Income properties) | `occupancy_rate` | S-07 | Income/Commercial only; FAQ retained for occupancy detail |
| Tenant | faq_q14 | What is driving the rental search | `rental_purpose` | T-02 | FAQ retained for motivation narrative |

---

## 8. Structured Field Upgrade List (FAQ → Dropdown/Checkbox)

This table is the Phase 3 implementation handoff checklist. Every row becomes one new form control in Phase 3. Rows are organized by workflow and tab placement. No field in this table is added that is not referenced by a Phase 1 ID.

### 8.1 Seller — New Form Controls

| Phase 1 Ref | Meta Key Name | Tab Placement | Control Type | Full Option Set |
|-------------|---------------|--------------|-------------|----------------|
| S-01 | `avg_monthly_utility_cost` | Financial Details — Residential/Income section | Text input (currency) | Free-form dollar amount; labeled "Estimated Average Monthly Utility Cost" |
| S-02 | `year_last_renovated` | Property Preferences — after Year Built | Number input (4-digit year) or Select | Year field or "Not Renovated / Original Condition" |
| S-03 | `seller_motivation_category` | Seller Terms — after Target Closing Timeframe | Select (agent-visible only) | Relocating / Downsizing / Upsizing / Estate / Inherited / Financial Pressure / Retirement / Divorce / Separation / Job Change / Investment Exit / Other — labeled "Shared with your Agent only" |
| S-04 | `inspection_contingency_acceptance` | Seller Terms | Select | Yes — will accept / No — will not accept / Negotiable |
| S-05 | `appraisal_contingency_acceptance` | Seller Terms | Select | Yes — will accept / No — will not accept / Negotiable |
| S-06 | `leaseback_required` | Seller Terms | Select | Yes — leaseback needed / No / Negotiable |
| S-06 | `leaseback_days_needed` | Seller Terms (conditional on S-06 = Yes) | Number input (days) | Integer days; shown only when `leaseback_required` = Yes |
| S-07 | `occupancy_rate` | Financial Details — Income/Commercial section | Text input (percentage) or Select | Percentage text or: 100% / 75–99% / 50–74% / Under 50% — shown only for Income/Commercial property types |

### 8.2 Landlord — New Form Controls

| Phase 1 Ref | Meta Key Name | Tab Placement | Control Type | Full Option Set |
|-------------|---------------|--------------|-------------|----------------|
| L-01 | `year_built` | Property Preferences — after Minimum Square Footage | Number input (4-digit year) | Year field only |
| L-02 | `available_date` | Lease Terms | Date picker | Calendar date; Required field |
| L-03 | `pet_policy` | Lease Terms | Select | Allowed / Not Allowed / Case by Case |
| L-03 | `pet_max_weight_lbs` | Lease Terms (conditional on L-03 ≠ Not Allowed) | Number input (lbs) | Pounds integer |
| L-03 | `pet_species_allowed` | Lease Terms (conditional on L-03 ≠ Not Allowed) | Multi-select checkboxes | Dog / Cat / Bird / Small caged animal |
| L-03 | `pet_deposit_amount` | Lease Terms (conditional on L-03 ≠ Not Allowed) | Text input (currency) | Dollar amount |
| L-03 | `pet_monthly_fee` | Lease Terms (conditional on L-03 ≠ Not Allowed) | Text input (currency) | Dollar amount; labeled "Monthly Pet Fee" |
| L-04 | `smoking_policy` | Lease Terms | Select | No smoking anywhere / Outdoor or patio only / Allowed |
| L-05 | `security_deposit_amount` | Lease Terms — after desired lease price | Text input (currency) or Select | Dollar amount or: 1 month's rent / 1.5 months / 2 months / No deposit / Negotiable |
| L-06 | `min_income_requirement` | Lease Terms (agent-visible only) | Select | 2× monthly rent / 2.5× monthly rent / 3× monthly rent / No minimum / Other — labeled "Required income to qualify (visible to your agent only)" |
| L-07 | `subletting_policy` | Lease Terms | Select | Not Allowed / Allowed with Landlord Approval / Allowed |

> **Fair Housing note for L-03 pet fields:** The form must display a visible note near the pet policy controls: "Service Animals and Emotional Support Animals are not pets and cannot be restricted under the Fair Housing Act and ADA." This note must appear regardless of the selected `pet_policy` value.

### 8.3 Buyer — New Form Controls

| Phase 1 Ref | Column Name | Tab Placement | Control Type | Full Option Set |
|-------------|-------------|--------------|-------------|----------------|
| B-02 | `purchase_purpose` | Property Preferences — top (above property type) | Select | Primary Residence / Vacation Home / Second Home / Investment / Business Use / Development / Other — Required field; must not include family-composition options |
| B-01 | `commute_destination_zip` | Property Preferences — after State field | Text input | ZIP code or city name; labeled "Preferred Commute Origin (optional)" |
| B-01 | `max_commute_minutes` | Property Preferences — after commute destination | Select | 15 minutes / 20 minutes / 30 minutes / 45 minutes / 60+ minutes |
| B-01 | `commute_mode` | Property Preferences — after max commute time | Select | Drive / Transit / Walk / Bike / Remote — no commute |
| B-06 | `hoa_acceptance` | Property Preferences | Select | Yes — will accept HOA / No — HOA-free only / Flexible |
| B-06 | `hoa_max_monthly_fee` | Property Preferences (conditional on B-06 = Yes or Flexible) | Text input (currency) | Maximum acceptable monthly HOA fee |
| B-07 | `fixer_upper_tolerance` | Property Preferences | Select | Move-in ready only / Light cosmetic work acceptable / Moderate renovation acceptable / Full renovation acceptable / Investment-grade fixer acceptable |
| B-08 | `flood_zone_tolerance` | Property Preferences | Select | No flood zone preferred / Minimal risk only — Zone X / Will accept moderate risk / Will accept any zone |
| B-03 | `inspection_contingency_required` | Purchasing Terms | Select | Yes — required / No — will waive / Negotiable |
| B-04 | `appraisal_contingency_required` | Purchasing Terms | Select | Yes — required / No — will waive / Negotiable |
| B-05 | `home_sale_contingency` | Purchasing Terms | Select | Yes — need to sell current home first / No — already sold or renting / No — cash or bridge loan available |
| B-09 | `min_cap_rate_target` | Purchasing Terms (conditional on Income/Commercial property type) | Text input (percentage) | Minimum cap rate required; shown only when property type = Income or Commercial |

### 8.4 Tenant — New Form Controls

| Phase 1 Ref | Meta Key Name | Tab Placement | Control Type | Full Option Set |
|-------------|---------------|--------------|-------------|----------------|
| T-02 | `rental_purpose` | Property Details tab or Listing Details tab | Select | Personal residence / Student / Corporate relocation / Vacation / Short-term / Business use / Other — must not include family-composition options |
| T-01 | `commute_destination_zip` | Property Details — after State field | Text input | ZIP code or city name; labeled "Preferred Commute Origin (optional)" |
| T-01 | `max_commute_minutes` | Property Details — after commute destination | Select | 15 minutes / 20 minutes / 30 minutes / 45 minutes / 60+ minutes |
| T-01 | `commute_mode` | Property Details — after max commute time | Select | Drive / Transit / Walk / Bike / Remote — no commute |
| T-05 | `accessibility_requirements` | Property Details | Select | No special requirements / Ground floor or elevator required / Wheelchair accessible required / ADA compliant features required + optional detail text — labeled "Accessibility Preferences — filters which properties are shown to you" |
| T-03 | `move_in_budget_upfront` | Leasing Terms — after maximum monthly budget | Text input (currency) | Total upfront move-in budget (first + last + deposit combined); labeled "Total Move-In Budget (optional)" |
| T-04 | `move_in_date_earliest` | Leasing Terms | Date picker | Earliest acceptable move-in date; supplements existing `lease_date` field |
| T-04 | `move_in_date_latest` | Leasing Terms | Date picker | Latest acceptable move-in date |
| T-06 | `credit_score_range` | Pre-Screening tab — reinstate commented-out field | Select or Multi-select | Excellent — 750+ / Good — 700–749 / Fair — 650–699 / Below 650 / Prefer not to disclose — labeled "Self-disclosed for matching purposes — landlords must apply credit standards uniformly per Fair Housing guidelines" |
| T-07 | `smoking_preference` | Pre-Screening tab | Select | Non-smoker / Smoker — need outdoor smoking allowed / Smoker — need indoor smoking allowed |

> **Fair Housing note for T-05:** This field must be presented as a preference filter for which listings the system surfaces to the tenant. It must never appear in any output visible to landlords, any API response accessible to landlords, or any compatibility score explanation shown to landlords.

> **Fair Housing note for T-06:** The field must include "Prefer not to disclose" as an option. The compliance note must be visible on the form. Credit score must never gate listing access or be used as a basis for rejecting a tenant application without uniform application per Fair Housing guidelines.

---

## 9. Phased Migration Order

Six sequential phases (A through F) govern the order in which the five new tables and 29 new user-input fields are introduced. Each phase has a risk rating and a validation gate that must pass before proceeding to the next phase. No phase may begin until its preceding phase's validation gate has passed.

### Phase A — Create Five New Tables (Risk: Zero)

**Scope:** Create all five new computed and storage tables with all columns nullable by default. No existing application code reads from or writes to these tables yet. No user-input fields are added in this phase.

**Tables created:**
1. `property_dna_profiles`
2. `buyer_tenant_dna_profiles`
3. `listing_compatibility_scores`
4. `ai_faq_answers` (or restructured; see Phase F)
5. `dna_marketing_outputs`

**Validation gate:** All five tables exist in the database. All nullable columns accept null without constraint violations. All existing listing workflows (Seller, Landlord, Buyer, Tenant create and edit flows) complete without error. No existing data is altered. No application code reads from these tables yet.

---

### Phase B — Tier 1 EAV Meta Keys: Landlord and Tenant (Risk: Very Low)

**Scope:** Add the highest-priority EAV meta keys for Landlord and Tenant workflows. All new keys are additive; no existing meta keys are renamed or removed.

**Landlord keys added:**
- `available_date` (L-02)
- `pet_policy`, `pet_max_weight_lbs`, `pet_species_allowed`, `pet_deposit_amount`, `pet_monthly_fee` (L-03)

**Tenant keys added:**
- `commute_destination_zip`, `max_commute_minutes`, `commute_mode` (T-01)
- `credit_score_range` (T-06)

**Validation gate:** Existing Landlord and Tenant listing save/edit flows complete without error. New meta keys save and load correctly via the existing `saveMeta` / `loadDraft` pattern. No EAV key naming collision with any existing key in `landlord_agent_auction_metas` or `tenant_agent_auction_metas` — confirm by grepping `saveMeta` and `loadMeta` calls in the affected Livewire components before writing any migration.

---

### Phase C — Tier 1 Native Columns: Buyer (Risk: Low)

**Scope:** Add the highest-priority native columns to `buyer_agent_auctions`. All new columns are nullable with null defaults.

**Columns added:**
- `purchase_purpose` (B-02)
- `commute_destination_zip`, `max_commute_minutes`, `commute_mode` (B-01)
- `hoa_acceptance`, `hoa_max_monthly_fee` (B-06)
- `flood_zone_tolerance` (B-08)

**Critical prerequisite:** Confirm that `BuyerOfferListing.php` uses direct property assignment for these fields and not `loadMeta()`. If any field uses `loadMeta()`, that field must follow the EAV pattern instead of a native column. Do not write Phase C migrations before confirming the Buyer Livewire component's save/load architecture.

**Validation gate:** Existing Buyer listing save/edit flows complete without error. New columns accept null without constraint violations. No existing buyer listing data is altered or lost. Schema inspection confirms columns are present with correct types.

---

### Phase D — Tier 2 and Tier 3 EAV Meta Keys: Landlord and Tenant (Risk: Very Low)

**Scope:** Add the remaining EAV meta keys for Landlord and Tenant workflows. All additive.

**Landlord keys added:**
- `year_built` (L-01)
- `smoking_policy` (L-04)
- `security_deposit_amount` (L-05)
- `min_income_requirement` (L-06)
- `subletting_policy` (L-07)

**Tenant keys added:**
- `rental_purpose` (T-02)
- `move_in_budget_upfront` (T-03)
- `move_in_date_earliest`, `move_in_date_latest` (T-04)
- `accessibility_requirements` (T-05)
- `smoking_preference` (T-07)

**Validation gate:** Existing Landlord and Tenant listing save/edit flows continue without error. No naming collision with Phase B keys or any existing meta key. Agent-only field `min_income_requirement` (L-06) confirmed absent from all public listing API responses.

---

### Phase E — Tier 2 and Tier 3: Buyer Native Columns + Seller EAV Meta Keys (Risk: Low)

**Scope:** Add remaining Buyer columns (confirm native vs. EAV per Phase C prerequisite process) and all Seller EAV meta keys.

**Buyer columns or meta keys added (confirm storage before migration):**
- `inspection_contingency_required` (B-03)
- `appraisal_contingency_required` (B-04)
- `home_sale_contingency` (B-05)
- `fixer_upper_tolerance` (B-07)
- `min_cap_rate_target` (B-09)

**Seller EAV meta keys added (on `seller_agent_auction_metas`):**
- `avg_monthly_utility_cost` (S-01)
- `year_last_renovated` (S-02)
- `seller_motivation_category` (S-03)
- `inspection_contingency_acceptance` (S-04)
- `appraisal_contingency_acceptance` (S-05)
- `leaseback_required`, `leaseback_days_needed` (S-06)
- `occupancy_rate` (S-07)

**Validation gate:** Existing Seller and Buyer listing save/edit flows complete without error. Agent-only field `seller_motivation_category` (S-03) confirmed absent from all public listing API responses and any AI prompt inputs for consumer-facing outputs.

---

### Phase F — AI FAQ Answer Table Restructure (Risk: Medium)

**Scope:** Add intelligence category tagging and normalized value columns to the existing AI FAQ answer storage mechanism.

**CRITICAL PREREQUISITE — confirm before writing any migration:** The current storage mechanism for AI FAQ answers is unknown from the Phase 1 audit alone. The Livewire save/load logic for the AI FAQ form must be read before designing this migration. Two significantly different paths apply:

- **Path 1 — Existing dedicated table:** If a dedicated `ai_faq_answers` (or equivalent) table already exists, Phase A's table creation is skipped for this table and Phase F adds `question_group`, `intelligence_category`, and `answer_normalized` columns to the existing table.
- **Path 2 — Answers stored as EAV meta keys:** If answers are stored as EAV meta keys (key = question_key, value = answer_text), a backfill script must extract all existing answers into the new structured table before any old EAV rows are altered or removed. The backfill must preserve all existing answer text.

**Validation gate:** All existing AI FAQ question forms save answers correctly after restructure. All existing answer rows are preserved and accessible. A manual check of at least one listing per workflow confirms `intelligence_category` is correctly populated for all existing answers. The three high-priority normalization targets (faq_q9 Buyer, faq_q11 Seller, faq_q1/faq_q4 Landlord) have non-null `answer_normalized` values after backfill.

---

## 10. Risks & Safeguards

Eight numbered risks with named safeguards, covering the primary failure modes identified for the Phase 3 implementation.

**Risk 1 — EAV Meta Key Naming Collision**  
A new meta key name may already be in use by another feature in the same workflow's meta table, causing silent data corruption or unexpected load behavior.  
**Safeguard:** Before writing any EAV migration (Phases B, D, E), grep all `saveMeta()` and `loadMeta()` calls in the affected Livewire components and blade partials for the target workflow. Confirm no existing key uses the same name as any planned new key. Add the new key only after confirming no collision exists.

**Risk 2 — Buyer Table Native Column vs. Meta Architecture Mismatch**  
If `BuyerOfferListing.php` uses `loadMeta()` for any of the fields planned as native columns, adding native columns without updating the Livewire component's load/save logic will result in the data never being saved or read.  
**Safeguard:** Before writing any Phase C migration, audit `BuyerOfferListing.php` and `BuyerOfferListingEdit.php` for all `loadMeta()` calls. For any planned field that is found to use `loadMeta()`, the storage decision must be switched to EAV meta (not native column) and the Phase C migration updated accordingly.

**Risk 3 — AI FAQ Answer Storage Mechanism Unknown**  
The Phase 1 audit confirmed AI FAQ answers exist but did not confirm how they are stored (dedicated table, EAV meta, JSON blob on the listing record, or other). Designing Phase F without confirming this leads to a migration that either fails to preserve existing answers or restructures the wrong storage mechanism.  
**Safeguard:** Before writing any Phase F migration, read the Livewire save/load logic for the AI FAQ form for all four workflows. Identify exactly where and how `answer_text` is persisted after the user submits the AI FAQ form. Document the confirmed storage mechanism before any migration code is written.

**Risk 4 — Compatibility Score Compute Volume on Initial Deployment**  
When the Phase 5 compatibility scoring engine is first deployed, a backfill computation across all existing listing pairs could produce an extremely large job volume, saturating the queue and blocking normal application traffic.  
**Safeguard:** The initial backfill computation must be chunked into a background queue job with configurable batch sizes and rate limiting. It must never run inline during a migration seeder, synchronously during a request, or as part of a scheduled job that runs at peak hours. The backfill must be dispatched manually by an operator after Phase 5 deployment, not triggered automatically.

**Risk 5 — Agent-Only Fields Leaking to Public Views**  
`seller_motivation_category` (S-03), `min_income_requirement` (L-06), and `accessibility_requirements` (T-05) must never appear in public listing responses. Accidental inclusion in `toArray()` output or serialized API responses creates legal and business exposure.  
**Safeguard:** Naming convention alone is insufficient. These fields must be: (1) explicitly stripped from any `toArray()` or API resource transformation applied to public routes; (2) covered by an integration test that asserts each field's absence from the public listing JSON response; and (3) documented in the respective model's `$hidden` array or equivalent access control mechanism for the platform's serialization pattern.

**Risk 6 — Fair Housing Compliance for AI-Generated Copy**  
An AI language model generating listing narratives, archetype briefs, or social captions may produce output containing protected-class adjacent language (familial status triggers, religious location references, disability characterizations). Displaying such copy creates legal liability under the Fair Housing Act.  
**Safeguard:** `fair_housing_reviewed` defaults to false on every generated row in `dna_marketing_outputs`. The Fair Housing word-filter (seeded from the blocklist in Phase 1 audit Section 14.3) runs synchronously at generation time before the row is marked reviewable. No content with `fair_housing_reviewed = false` is rendered in any user-facing interface under any code path. All filter hits are stored in `fair_housing_flags` for human review. The word-filter blocklist must be expanded with legal counsel before the marketing output generation feature goes live.

**Risk 7 — DNA Score Staleness**  
When a user edits their listing after a DNA profile has been computed, the displayed `overall_dna_completeness` score reflects the old state until the background job recomputes it. A stale score showing higher completeness than the actual current state misleads agents.  
**Safeguard:** The DNA compute job must be dispatched on the model's `updated` event (post-save, async). The UI displaying `overall_dna_completeness` must compare `property_dna_profiles.computed_at` against the source listing's `updated_at`. If `computed_at` < `updated_at`, display a "Refreshing..." or "Score pending update" state rather than the stale value.

**Risk 8 — Scope Creep on Commented-Out Tenant Pre-Screening Fields**  
The Phase 1 audit identified three commented-out fields in `pre-screening.blade.php`: `credit_score_rating`, `prior_eviction`, and `prior_felony`. Only `credit_score_range` (T-06) is in scope for Phase 3 reinstatement.  
**Safeguard:** `prior_eviction` and `prior_felony` fields remain commented out and are not part of this architecture plan. Their reinstatement requires separate legal counsel per jurisdiction (HUD guidance on eviction records and criminal history screening varies significantly by state and city), a separate compliance review, and a separately versioned architecture document before any reinstatement is permitted. Phase 3 implementation must not touch these commented-out fields.

---

## 11. Phase 3 Target Files

A read-only planning inventory of every file Phase 3 will create or modify, organized by file type. This is a planning reference only — no file in this list is edited during Phase 2 (this document's phase). Placeholder migration timestamps use `YYYY_MM_DD` format; actual timestamps are assigned at implementation time.

### New Migration Files

**Phase A — Table creation:**
- `YYYY_MM_DD_000001_create_property_dna_profiles_table`
- `YYYY_MM_DD_000002_create_buyer_tenant_dna_profiles_table`
- `YYYY_MM_DD_000003_create_listing_compatibility_scores_table`
- `YYYY_MM_DD_000004_create_or_alter_ai_faq_answers_table` (path depends on Phase F investigation)
- `YYYY_MM_DD_000005_create_dna_marketing_outputs_table`

**Phase B — Tier 1 Landlord and Tenant EAV meta keys:**
- `YYYY_MM_DD_000006_add_landlord_tier1_eav_meta_keys`
- `YYYY_MM_DD_000007_add_tenant_tier1_eav_meta_keys`

**Phase C — Tier 1 Buyer native columns:**
- `YYYY_MM_DD_000008_add_buyer_tier1_native_columns`

**Phase D — Tier 2 and Tier 3 Landlord and Tenant EAV meta keys:**
- `YYYY_MM_DD_000009_add_landlord_tier2_tier3_eav_meta_keys`
- `YYYY_MM_DD_000010_add_tenant_tier2_tier3_eav_meta_keys`

**Phase E — Tier 2 and Tier 3 Buyer columns + Seller EAV meta keys:**
- `YYYY_MM_DD_000011_add_buyer_tier2_tier3_columns`
- `YYYY_MM_DD_000012_add_seller_eav_meta_keys`

**Phase F — AI FAQ answer table restructure:**
- `YYYY_MM_DD_000013_restructure_ai_faq_answer_storage` (path confirmed in Phase F)

### Livewire Components (create and edit variants — dispatch hook added; no other changes in Phase 3)

- `app/Http/Livewire/OfferListing/Seller/SellerOfferListing.php`
- `app/Http/Livewire/OfferListing/Seller/SellerOfferListingEdit.php`
- `app/Http/Livewire/OfferListing/Landlord/LandlordOfferListing.php`
- `app/Http/Livewire/OfferListing/Landlord/LandlordOfferListingEdit.php`
- `app/Http/Livewire/OfferListing/Buyer/BuyerOfferListing.php`
- `app/Http/Livewire/OfferListing/Buyer/BuyerOfferListingEdit.php`
- `app/Http/Livewire/OfferListing/Tenant/TenantOfferListing.php`
- `app/Http/Livewire/OfferListing/Tenant/TenantOfferListingEdit.php`

### Blade Tab Partials (new form controls added per Section 8)

**Seller:**
- `resources/views/livewire/offer-listing/offer-seller-tabs/commission-based/property-preferences.blade.php` — S-02 (year last renovated)
- `resources/views/livewire/offer-listing/offer-seller-tabs/commission-based/seller-terms.blade.php` — S-03 (motivation, agent-only), S-04 (inspection contingency), S-05 (appraisal contingency), S-06 (leaseback)
- `resources/views/livewire/offer-listing/offer-seller-tabs/commission-based/financial-details.blade.php` — S-01 (utility cost), S-07 (occupancy rate)

**Landlord:**
- `resources/views/livewire/offer-listing/offer-landlord-tabs/commission-based/property-preferences.blade.php` — L-01 (year built)
- `resources/views/livewire/offer-listing/offer-landlord-tabs/commission-based/lease-terms.blade.php` — L-02 (available date), L-03 (pet policy + sub-fields), L-04 (smoking policy), L-05 (security deposit), L-06 (income requirement, agent-only), L-07 (subletting policy)

**Buyer:**
- `resources/views/livewire/offer-listing/offer-buyer-tabs/commission-based/property-preferences.blade.php` — B-01 (commute), B-02 (purchase purpose), B-06 (HOA), B-07 (fixer-upper), B-08 (flood zone)
- `resources/views/livewire/offer-listing/offer-buyer-tabs/commission-based/purchasing-terms.blade.php` — B-03 (inspection contingency), B-04 (appraisal contingency), B-05 (home sale contingency), B-09 (cap rate target)

**Tenant:**
- `resources/views/livewire/offer-listing/offer-tenant-tabs/commission-based/property-details.blade.php` — T-01 (commute), T-02 (rental purpose), T-05 (accessibility requirements)
- `resources/views/livewire/offer-listing/offer-tenant-tabs/commission-based/leasing-terms.blade.php` — T-03 (upfront budget), T-04 (move-in date range)
- `resources/views/livewire/offer-listing/offer-tenant-tabs/commission-based/pre-screening.blade.php` — T-06 (credit score reinstatement), T-07 (smoking preference)

### New Model Files (one per new table)

- `app/Models/PropertyDnaProfile.php`
- `app/Models/BuyerTenantDnaProfile.php`
- `app/Models/ListingCompatibilityScore.php`
- `app/Models/AiFaqAnswer.php`
- `app/Models/DnaMarketingOutput.php`

### New Jobs and Services Files

- `app/Jobs/ComputePropertyDnaProfile.php` — dispatched after Seller or Landlord listing save; writes new row to `property_dna_profiles`
- `app/Jobs/ComputeBuyerTenantDnaProfile.php` — dispatched after Buyer or Tenant listing save; writes new row to `buyer_tenant_dna_profiles`
- `app/Services/DnaCompletenessCalculator.php` — per-category fill-rate computation logic using the weights in Sections 4.1 and 4.2; used by both background job classes

### AI FAQ Config Files (read-only — not modified)

These files are read by the intelligence category tagging system to map question keys to their C1–C10 categories. They are never modified in Phase 3.

- `config/ai_faq_seller.php`
- `config/ai_faq_landlord.php`
- `config/ai_faq_buyer.php`
- `config/tenant_ai_faq.php`

---

## 12. Governance, Revision Rules & Controlling Source Document

### Architecture Revision Requirement

Any future modification to the following decisions requires a new versioned architecture revision document before implementation begins. This document (Phase 2) cannot be silently overridden by implementation decisions made in code:

- Field naming conventions defined in Section 3.10
- Scoring dimension weights defined in Sections 4.1, 4.2, and 4.3
- DNA profile table structures defined in Section 4
- Compatibility scoring logic defined in Sections 3.6 and 4.3
- AI-generated output storage architecture defined in Sections 3.7, 3.8, and 4.5
- The archetype tag registry defined in Section 3.7
- The reserved field registry defined in Section 3.9 and Section 6 Tier 4

### AI Governance Versioning

Any future introduction of new scoring dimensions, inference categories, recommendation logic, archetype systems, or AI-generated output classes requires a separately versioned governance revision document before implementation. No scoring or inference expansion may be added as a code change without a corresponding architecture document update.

### Human Review Authority

Human reviewers, compliance reviewers, moderators, and listing owners retain full authority to archive, suppress, reject, or override any computed output, generated marketing content, compatibility score, or archetype tag regardless of the computed value or AI recommendation. Computed outputs are overridable, not authoritative. Override actions are audit-traceable through reviewer identity and timestamp metadata — this traceability mechanism must be explicitly engineered in future implementation phases.

### Workflow Independence

Listing creation, editing, public viewing, and all negotiation workflows must remain fully operational even when DNA computation, compatibility scoring, AI tagging, or marketing generation systems are delayed, queued, unavailable, or disabled. No described workflow may block on AI output. Async post-save dispatch is the only acceptable pattern.

### No Feedback Loops

Compatibility scores, archetype tags, engagement metrics, click-through rates, and generated marketing outputs must not be used to automatically retrain or reweight future scoring logic. Feedback loops between AI outputs and scoring inputs are out of scope for this platform. Any future consideration of such feedback mechanisms requires a separately approved and versioned governance revision.

### Competitive Neutrality

AI systems, scoring, and ranking must not favor specific agents, brokerages, listings, or offer participants based on platform monetization status, subscription tier, advertising participation, engagement metrics, or any business relationship with the platform operator, unless explicitly disclosed and separately governed under a future commercial policy framework.

### No AI Training Reuse

User-authored content, AI FAQ answers, compatibility outputs, negotiation metadata, marketing outputs, and DNA profiles must not be repurposed as training data for future AI systems unless separately disclosed, approved, and versioned under a future governance framework. This prohibition applies to both internal model fine-tuning and third-party training pipelines.

### Fair Housing and Compliance Isolation

Fair Housing review fields, moderation flags, `fair_housing_flags` column contents, compliance-review notes, and internal risk annotations must remain operationally isolated from consumer-facing recommendation logic. They must never appear in public outputs, public API responses, or prompts producing consumer-visible content. Agent-only fields (`seller_motivation_category`, `min_income_requirement`, `accessibility_requirements`) are excluded from all consumer-facing AI prompts regardless of the computed context.

### No Consumer Risk Classification

DNA profiles, compatibility systems, and AI-generated metadata must not classify users, listings, or offer participants as "high risk," "low quality," "undesirable," or any equivalent reputational label. Scoring is limited strictly to transactional compatibility, preference alignment, and stated logistical or financial criteria.

### Visibility Tier Separation (Future Engineered Policy — Not Schema-Inferred)

The schema and table structures defined in this Phase 2 plan establish a non-public-by-default baseline for all computed tables (`property_dna_profiles`, `buyer_tenant_dna_profiles`, `listing_compatibility_scores`, `ai_faq_answers`, `dna_marketing_outputs`). However, the schema design alone does not define or imply a complete visibility tier model. The following audience tiers exist on the platform — public anonymous users, authenticated consumers, buyer and tenant clients, seller and landlord clients, agents, moderators, compliance reviewers, and platform administrators — and each tier will require different levels of access to different subsets of computed fields.

This visibility tier policy is a future engineering requirement. It must be explicitly designed, documented, and approved in a separately versioned implementation phase before any computed field, DNA score, compatibility result, archetype tag, or marketing output is surfaced beyond its current non-public default. The absence of a row-level permission control in the Phase 2 schema does not grant implied access at any tier. Visibility access logic must be built into API route middleware, view-layer access checks, and resource serialization — it cannot be inferred from the presence or absence of a column in a table definition.

---

### Controlling Source Document Restatement

**`docs/PROPERTY_DNA_BUYER_TENANT_DNA_PHASE_1_AUDIT.md` is the controlling source document for this Phase 2 architecture plan.**

If any conflict exists between this Phase 2 architecture plan and future implementation assumptions — including assumptions embedded in code, migration files, Blade templates, Livewire components, or service classes — `docs/PROPERTY_DNA_BUYER_TENANT_DNA_PHASE_1_AUDIT.md` remains the authoritative reference until it is superseded by an approved and versioned architecture revision document (Constraint 17).

This Phase 2 plan is authoritative for field naming, computed table structure, scoring weights, archetype registry, reserved field registry, and governance rules — but only within the boundaries established by the Phase 1 audit. Any future modification to scoring dimension weights, DNA profile table structures, compatibility scoring logic, or AI-generated output storage architecture requires a new versioned architecture revision document before implementation (Constraint 26).

---

*End of Phase 2 Database & Field Architecture Plan*  
*Document: `docs/PROPERTY_DNA_PHASE_2_DATABASE_FIELD_ARCHITECTURE_PLAN.md`*  
*Plan date: May 27, 2026*  
*Controlling source: `docs/PROPERTY_DNA_BUYER_TENANT_DNA_PHASE_1_AUDIT.md`*  
*Constraint verified: No application code, migrations, Blade files, or configuration files were created or modified in producing this document.*
