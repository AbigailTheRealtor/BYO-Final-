# Property DNA — Phase 3 Execution Plan

**PLANNING DOCUMENT ONLY — NO CODE, NO MIGRATIONS, NO IMPLEMENTATION CHANGES AUTHORIZED.**

**Document version:** 1.0  
**Plan date:** May 27, 2026  
**Derived from:** `docs/PROPERTY_DNA_PHASE_2_DATABASE_FIELD_ARCHITECTURE_PLAN.md` (version 1.1, May 27, 2026)

---

## Table of Contents

1. [Purpose & Scope](#1-purpose--scope)
2. [Architecture Freeze Rule](#2-architecture-freeze-rule)
3. [Phase-by-Phase Execution Order](#3-phase-by-phase-execution-order)
4. [Phase A Detailed Checklist](#4-phase-a-detailed-checklist)
5. [Schema Safety Rules](#5-schema-safety-rules)
6. [File-by-File Change Plan](#6-file-by-file-change-plan)
7. [QA Gates Required After Every Phase](#7-qa-gates-required-after-every-phase)
8. [Delayed Features / Explicitly Not Yet](#8-delayed-features--explicitly-not-yet)
9. [Approval Rule Before Coding](#9-approval-rule-before-coding)

---

## 1. Purpose & Scope

This document is an implementation roadmap only, not implementation authorization. It is derived solely from `docs/PROPERTY_DNA_PHASE_2_DATABASE_FIELD_ARCHITECTURE_PLAN.md`. No code, migrations, Blade files, Livewire components, config files, or database schema changes are applied during this phase.

The purpose of this document is to provide a governance-safe execution order, a detailed Phase A checklist, schema safety rules, QA gates required after every phase, and an explicit list of features that are delayed or not yet authorized for implementation.

This document does not constitute approval to begin implementation. See Section 9.

---

## 2. Architecture Freeze Rule

The Phase 2 architecture document (`docs/PROPERTY_DNA_PHASE_2_DATABASE_FIELD_ARCHITECTURE_PLAN.md`, version 1.1, May 27, 2026) is now frozen as the versioned governance baseline.

No casual edits, no improvisation, and no architecture changes without a new versioned revision document. Any future modification to any of the following requires a new versioned revision document before implementation:

- Field naming conventions
- Computed table structures
- Scoring dimension weights
- Archetype registries
- Reserved field registries

The Phase 2 document defines the authoritative architecture. This Phase 3 execution plan derives all implementation decisions from that document and may not override, extend, or reinterpret any architectural decision without a new versioned revision document that explicitly supersedes the relevant section.

---

## 3. Phase-by-Phase Execution Order

Each phase must not begin until the preceding phase's QA gate has passed. Phases are not interchangeable in order. No phase may be partially completed and advanced past its QA gate.

**Phase A — New Tables and Models Only**  
Create all five new tables and their Eloquent models. No UI, no form controls, no scoring, no AI generation, no public exposure. This is the only phase authorized for consideration after this document receives explicit written approval (see Section 9).

**Phase B — Tier 1 Landlord and Tenant EAV Meta Keys Only**  
Add Tier 1 Landlord and Tenant EAV meta keys only: `available_date`, the pet policy group (L-03: `pet_policy`, `pet_max_weight_lbs`, `pet_species_allowed`, `pet_deposit_amount`, `pet_monthly_fee`), commute fields for Tenant (`commute_destination_zip`, `max_commute_minutes`, `commute_mode`), and `credit_score_range`. Additive only. Phase B must not begin until Phase A's QA gate has passed.

**Phase C — Tier 1 Buyer Native Columns Only**  
Add Tier 1 Buyer native columns only: `purchase_purpose`, commute fields B-01 (`commute_destination_zip`, `max_commute_minutes`, `commute_mode`), HOA fields B-06 (`hoa_acceptance`, `hoa_max_monthly_fee`), and `flood_zone_tolerance` (B-08). Confirm Buyer Livewire architecture before writing any migration: read `BuyerOfferListing.php` and `BuyerOfferListingEdit.php` to determine whether each planned field uses direct property assignment or `loadMeta()`. If `loadMeta()` is found for any field, that field must be switched to EAV, not a native column. Phase C must not begin until Phase B's QA gate has passed.

**Phase D — Tier 2/Tier 3 Landlord and Tenant EAV Keys**  
Add Tier 2/Tier 3 Landlord EAV keys (`year_built`, `smoking_policy`, `security_deposit_amount`, `min_income_requirement`, `subletting_policy`) and Tier 2/Tier 3 Tenant EAV keys (`rental_purpose`, `move_in_budget_upfront`, move-in date range T-04 (`move_in_date_earliest`, `move_in_date_latest`), `accessibility_requirements`, `smoking_preference`). Phase D must not begin until Phase C's QA gate has passed.

**Phase E — Remaining Buyer Columns/Keys and Seller EAV Meta Keys**  
Add remaining Buyer columns/keys (B-03 `inspection_contingency_required`, B-04 `appraisal_contingency_required`, B-05 `home_sale_contingency`, B-07 `fixer_upper_tolerance`, B-09 `min_cap_rate_target` — confirm native vs. EAV per the Phase C architecture confirmation step) and all Seller EAV meta keys S-01 through S-07 on `seller_agent_auction_metas`. Phase E must not begin until Phase D's QA gate has passed.

**Phase F — AI FAQ Answer Storage Restructure**  
Restructure AI FAQ answer storage only after confirming the current storage mechanism by reading the Livewire save/load logic for all four workflows (Seller, Landlord, Buyer, Tenant). Two paths apply:

- **Path A:** If answers are stored in a dedicated table — ALTER the existing table to add the new columns defined in Phase 2 Section 3.5 and 4.4.
- **Path B:** If answers are stored as EAV meta keys — write a backfill migration to move existing answers into the new `ai_faq_answers` table structure.

Do not write any Phase F migration before confirming the path. Phase F must not begin until Phase E's QA gate has passed.

---

## 4. Phase A Detailed Checklist

**Scope: create tables and models only. Nothing else.**

### Migrations

- [ ] Migration: `create_property_dna_profiles_table` — all columns nullable except primary key, `listing_type`, `listing_id`, `version`, `computed_at`, `created_at`, `updated_at`
- [ ] Migration: `create_buyer_tenant_dna_profiles_table` — same nullability rule
- [ ] Migration: `create_listing_compatibility_scores_table` — same nullability rule
- [ ] Migration: `create_or_alter_ai_faq_answers_table` — path confirmed in Phase F investigation; defer if Phase F path is unknown at time of Phase A implementation
- [ ] Migration: `create_dna_marketing_outputs_table` — `fair_housing_reviewed` defaults to false; `archived_at` defaults to null

### Models

- [ ] Model: `app/Models/PropertyDnaProfile.php`
- [ ] Model: `app/Models/BuyerTenantDnaProfile.php`
- [ ] Model: `app/Models/ListingCompatibilityScore.php`
- [ ] Model: `app/Models/AiFaqAnswer.php`
- [ ] Model: `app/Models/DnaMarketingOutput.php`

### Phase A Exclusions (strictly off-limits in this phase)

- No UI changes of any kind
- No form control additions
- No scoring logic
- No AI generation hooks
- No public exposure of any new table or column
- No changes to existing Livewire components or Blade files

### Phase A QA Gate

The Phase A QA gate (Section 7) must pass before proceeding to Phase B.

---

## 5. Schema Safety Rules

The following rules apply to all migrations across all phases. Violation of any rule constitutes a blocking defect that must be resolved before proceeding.

1. **All migrations are additive-only.** No column renames, no table deletions, no constraint removals.

2. **All new columns must be nullable-first with null defaults** unless the Phase 2 plan explicitly marks a column as required (e.g., `listing_type`, `listing_id`, `version`, `computed_at`, `fair_housing_reviewed`).

3. **No destructive migrations.** No `DROP COLUMN`, no `DROP TABLE`, no `RENAME COLUMN`, no `RENAME TABLE`.

4. **No refactors to files outside the scoped file list in Section 6.** If a file is not listed in Section 6 for the current phase, it must not be touched.

5. **No cross-role changes** unless explicitly approved in the Phase 2 plan for that specific field. Seller fields go to Seller tables. Landlord fields go to Landlord tables. Buyer fields go to Buyer tables. Tenant fields go to Tenant tables.

6. **No public exposure of any computed field, DNA score, compatibility score, archetype tag, or marketing output** without a separately approved and versioned exposure phase. This restriction applies across all Phase 3 phases.

7. **Before writing any EAV migration (Phases B, D, E):** grep all `saveMeta()` and `loadMeta()` calls in the affected Livewire components to confirm no key naming collision exists between any new meta key and any existing meta key. Do not proceed with the migration until this confirmation is documented.

8. **Before writing any Phase C migration:** confirm whether `BuyerOfferListing.php` and `BuyerOfferListingEdit.php` use direct property assignment or `loadMeta()` for each planned field. If `loadMeta()` is found for any field, that field must be switched to EAV instead of a native column. This confirmation must be completed before any Phase C migration file is created.

9. **Agent-only fields must be explicitly excluded from all public API responses, `toArray()` serializations, and any AI prompt producing consumer-visible output.** The affected fields are:
   - `seller_motivation_category` (S-03) — excluded from all public listing responses and all AI outputs visible to buyers
   - `min_income_requirement` (L-06) — excluded from all public listing responses and all AI outputs visible to tenants
   - `accessibility_requirements` (T-05) — excluded from all landlord-visible outputs, match score explanations shown to landlords, and all AI prompts producing landlord-visible content

10. **Do not modify, refactor, rename, or reorganize existing Livewire properties, validation arrays, tab structures, route names, Blade section order, or existing save/load behavior** unless explicitly required by an approved implementation phase. Each phase's file scope (Section 6) defines the exact boundaries of permitted changes. Any modification outside that scope is unauthorized regardless of perceived improvement or cleanup opportunity.

---

## 6. File-by-File Change Plan

Derived from Phase 2 Section 11. Organized by file type. Real timestamps must be assigned at implementation time — `YYYY_MM_DD` placeholders below are not valid migration filenames.

### New Migration Files

**Phase A:**
- `YYYY_MM_DD_000001_create_property_dna_profiles_table`
- `YYYY_MM_DD_000002_create_buyer_tenant_dna_profiles_table`
- `YYYY_MM_DD_000003_create_listing_compatibility_scores_table`
- `YYYY_MM_DD_000004_create_or_alter_ai_faq_answers_table` (path confirmed in Phase F investigation)
- `YYYY_MM_DD_000005_create_dna_marketing_outputs_table`

**Phase B:**
- `YYYY_MM_DD_000006_add_landlord_tier1_eav_meta_keys`
- `YYYY_MM_DD_000007_add_tenant_tier1_eav_meta_keys`

**Phase C:**
- `YYYY_MM_DD_000008_add_buyer_tier1_native_columns`

**Phase D:**
- `YYYY_MM_DD_000009_add_landlord_tier2_tier3_eav_meta_keys`
- `YYYY_MM_DD_000010_add_tenant_tier2_tier3_eav_meta_keys`

**Phase E:**
- `YYYY_MM_DD_000011_add_buyer_tier2_tier3_columns`
- `YYYY_MM_DD_000012_add_seller_eav_meta_keys`

**Phase F:**
- `YYYY_MM_DD_000013_restructure_ai_faq_answer_storage`

### New Model Files

- `app/Models/PropertyDnaProfile.php`
- `app/Models/BuyerTenantDnaProfile.php`
- `app/Models/ListingCompatibilityScore.php`
- `app/Models/AiFaqAnswer.php`
- `app/Models/DnaMarketingOutput.php`

### Livewire Components (referenced for planning only — no modifications authorized)

These files are referenced for future implementation planning only. No Livewire modifications are authorized in this document.

- `app/Http/Livewire/OfferListing/Seller/SellerOfferListing.php`
- `app/Http/Livewire/OfferListing/Seller/SellerOfferListingEdit.php`
- `app/Http/Livewire/OfferListing/Landlord/LandlordOfferListing.php`
- `app/Http/Livewire/OfferListing/Landlord/LandlordOfferListingEdit.php`
- `app/Http/Livewire/OfferListing/Buyer/BuyerOfferListing.php`
- `app/Http/Livewire/OfferListing/Buyer/BuyerOfferListingEdit.php`
- `app/Http/Livewire/OfferListing/Tenant/TenantOfferListing.php`
- `app/Http/Livewire/OfferListing/Tenant/TenantOfferListingEdit.php`

### Blade Tab Partials (new form controls per Phase 2 Section 8)

These files receive new form control additions only. No changes to existing controls in these files are authorized unless explicitly required to support a new field's conditional display logic.

**Seller:**
- `resources/views/livewire/offer-listing/offer-seller-tabs/commission-based/property-preferences.blade.php` — S-02 (`year_last_renovated`)
- `resources/views/livewire/offer-listing/offer-seller-tabs/commission-based/seller-terms.blade.php` — S-03 (`seller_motivation_category`), S-04 (`inspection_contingency_acceptance`), S-05 (`appraisal_contingency_acceptance`), S-06 (`leaseback_required`, `leaseback_days_needed`)
- `resources/views/livewire/offer-listing/offer-seller-tabs/commission-based/financial-details.blade.php` — S-01 (`avg_monthly_utility_cost`), S-07 (`occupancy_rate`)

**Landlord:**
- `resources/views/livewire/offer-listing/offer-landlord-tabs/commission-based/property-preferences.blade.php` — L-01 (`year_built`)
- `resources/views/livewire/offer-listing/offer-landlord-tabs/commission-based/lease-terms.blade.php` — L-02 (`available_date`), L-03 group (`pet_policy`, `pet_max_weight_lbs`, `pet_species_allowed`, `pet_deposit_amount`, `pet_monthly_fee`), L-04 (`smoking_policy`), L-05 (`security_deposit_amount`), L-06 (`min_income_requirement`), L-07 (`subletting_policy`)

**Buyer:**
- `resources/views/livewire/offer-listing/offer-buyer-tabs/commission-based/property-preferences.blade.php` — B-01 (`commute_destination_zip`, `max_commute_minutes`, `commute_mode`), B-02 (`purchase_purpose`), B-06 (`hoa_acceptance`, `hoa_max_monthly_fee`), B-07 (`fixer_upper_tolerance`), B-08 (`flood_zone_tolerance`)
- `resources/views/livewire/offer-listing/offer-buyer-tabs/commission-based/purchasing-terms.blade.php` — B-03 (`inspection_contingency_required`), B-04 (`appraisal_contingency_required`), B-05 (`home_sale_contingency`), B-09 (`min_cap_rate_target`)

**Tenant:**
- `resources/views/livewire/offer-listing/offer-tenant-tabs/commission-based/property-details.blade.php` — T-01 (`commute_destination_zip`, `max_commute_minutes`, `commute_mode`), T-02 (`rental_purpose`), T-05 (`accessibility_requirements`)
- `resources/views/livewire/offer-listing/offer-tenant-tabs/commission-based/leasing-terms.blade.php` — T-03 (`move_in_budget_upfront`), T-04 (`move_in_date_earliest`, `move_in_date_latest`)
- `resources/views/livewire/offer-listing/offer-tenant-tabs/commission-based/pre-screening.blade.php` — T-06 (`credit_score_range`), T-07 (`smoking_preference`)

### New Jobs and Services Files

- `app/Jobs/ComputePropertyDnaProfile.php`
- `app/Jobs/ComputeBuyerTenantDnaProfile.php`
- `app/Services/DnaCompletenessCalculator.php`

### AI FAQ Config Files (read-only — never modified in Phase 3)

These files are referenced for question key mappings only. They must not be modified in any Phase 3 phase. No new questions may be added and no existing questions may be removed.

- `config/ai_faq_seller.php`
- `config/ai_faq_landlord.php`
- `config/ai_faq_buyer.php`
- `config/tenant_ai_faq.php`

---

## 7. QA Gates Required After Every Phase

The following checks must all pass after every phase before proceeding to the next phase. A phase is not complete until every item on this list is verified.

1. **Route health check:** All application routes that existed before the phase still return HTTP 200. No 500 errors and no 404 errors introduced by the phase.

2. **Listing workflow integrity:** Create, edit, and Save Draft flows for all four listing types (Seller, Landlord, Buyer, Tenant) complete without error.

3. **Save/load parity:** A listing saved before the phase loads correctly after the phase. No data is lost, corrupted, or mutated by the migration or model changes.

4. **No public exposure:** No new table, column, or field added in the phase is exposed in any public-facing route, API endpoint, or anonymous-accessible JSON response.

5. **No role crossover:** Seller fields do not appear in Buyer forms or responses. Landlord fields do not appear in Tenant forms or responses. Buyer fields do not appear in Seller forms or responses. Tenant fields do not appear in Landlord forms or responses.

6. **No Offer Listing vs. Hire Agent leakage:** New fields do not appear in hire-agent workflows unless explicitly scoped to them in the Phase 2 plan.

7. **No listing-type EAV contamination:** EAV keys added to `landlord_agent_auction_metas` do not appear in `tenant_agent_auction_metas`, and vice versa. EAV keys added to `seller_agent_auction_metas` do not appear in any other meta table.

8. **File scope confirmation:** `git diff --name-only` confirms that only files listed in Section 6 for the completed phase have changed. No unintended file modifications are present.

9. **Route integrity:** `php artisan route:list` confirms no existing route names, URIs, or middleware assignments changed as a result of the phase. Any new routes introduced must be explicitly listed in Section 6.

10. **Migration status verification:** `php artisan migrate:status` run before and after each phase confirms only the intended migrations appear and all migrations for the current phase show as successfully run. No unexpected pending or failed migrations may remain.

---

## 8. Delayed Features / Explicitly Not Yet

The following features are not implemented in any Phase 3 phase. They must not appear in any code, migration, Blade template, route, API endpoint, queue job, config file, or AI prompt input during Phase 3. Implementation of any item on this list requires a separately approved and versioned authorization document.

- **AI-generated marketing copy** — listing narratives, social captions, email subject lines, archetype briefs, or any other AI-produced content for user-facing consumption
- **Compatibility scoring UI** — no compatibility score, match percentage, or scoring explanation may be displayed to any user in any form
- **DNA score display** — no DNA completeness score, profile completeness indicator, or archetype classification may appear on any user-facing page
- **Archetype label display** — no archetype tag, archetype brief, or archetype-derived classification may appear on any user-facing page
- **Public-facing AI outputs of any kind**
- **External API integrations** — Walk Score, GreatSchools, drive-time polygon APIs, EIA utility cost estimates, or any other third-party data enrichment service
- **Automated offer or bid ranking changes** based on compatibility scores, DNA outputs, archetype tags, or any computed value
- **Reserved / Future Use Only fields (F-01 through F-05):**
  - F-01: Walk Score
  - F-02: School Rating
  - F-03: Drive-Time Polygon
  - F-04: AI-Enhanced Compatibility Scoring Engine
  - F-05: Historical Utility Cost Estimates
- **`commute_polygon_cache` column** on `buyer_tenant_dna_profiles` — must not be populated, read, or exposed in any Phase 3 phase
- **Commented-out pre-screening fields** — `prior_eviction` and `prior_felony` in `pre-screening.blade.php` must remain commented out and untouched in all Phase 3 phases
- **Fair Housing review queue UI** — the `fair_housing_reviewed` column may be stored but no review queue, admin workflow, or UI for managing fair housing review status may be built in Phase 3
- **Visibility tier access policy engineering** — no role-based or tier-based access control layer for DNA profiles, compatibility scores, or marketing outputs may be engineered in Phase 3

---

## 9. Approval Rule Before Coding

This Phase 3 Execution Plan is a planning document only.

**After this document is written, stop and wait for explicit approval before implementing Phase A or any subsequent phase.**

No migration file, model file, Livewire change, Blade change, job file, service file, or config change may be created or edited until Phase A has received explicit written approval. Approval must reference this document by filename (`docs/PROPERTY_DNA_PHASE_3_EXECUTION_PLAN.md`) and version date (May 27, 2026) before any implementation work begins.

Any work begun without this approval is unauthorized and must be reverted before it is merged.
