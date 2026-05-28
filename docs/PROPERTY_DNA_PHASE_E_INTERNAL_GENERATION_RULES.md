# Property DNA Phase E — Internal Generation Rules

## Overview

Phase E introduces the first internal intelligence persistence layer for the Property DNA system. It reads already-collected listing data from the four source-of-truth listing models (`PropertyAuction`, `LandlordAuction`, `BuyerCriteriaAuction`, `TenantCriteriaAuction`) and writes normalized, deterministic DNA profile snapshots into the two Phase A computed tables: `property_dna_profiles` and `buyer_tenant_dna_profiles`.

Phase E produces no public-facing output of any kind. All generated records are internal only.

---

## 1. Deterministic-Only Generation Rule

Every dimension value written into a DNA profile record must be derived by an **explicit, rule-based mapping** from a named source field value. No probability estimates, no weighted formulas, no inference engines, no machine learning, and no AI calls are permitted in Phase E.

**Explainability requirement:** For every dimension value that appears in a generated profile, there must be a directly traceable named source field in the source listing's native columns or EAV meta table. If a field is unavailable or empty, the corresponding dimension slot is left `null`. A null slot does not abort generation — the remaining slots are computed normally.

---

## 2. Prohibited Inference Categories

The following categories are **permanently prohibited** from DNA generation at any phase, and must never be introduced in any future extension of Phase E logic:

- **Protected class inference:** Race, color, national origin, religion, sex, familial status, disability, or any characteristic protected under the Fair Housing Act or applicable state law.
- **Behavioral prediction:** Predicted payment behavior, likelihood to vacate, likelihood to default, likelihood to renew, or any predictive scoring about future tenant/buyer/seller conduct.
- **Demographic inference:** Age estimation from occupant count, income-level inference, household composition inference, or any surrogate for protected characteristics.
- **Creditworthiness scoring:** No credit risk model, no scoring derived from eviction history fields, prior felony fields, or income fields. These fields must never be read by any generator.
- **Accessibility classification:** The `accessibility_requirements` field is permanently off-limits and must never be read, tokenized, mapped, or referenced in any generator, job, observer, or downstream phase.

---

## 3. The `accessibility_requirements` Hard Exclusion

The meta key `accessibility_requirements` **must never be read** by `PropertyDnaGenerator`, `BuyerTenantDnaGenerator`, or any future generator in the DNA system. This field contains free-form user-authored text describing disability-related needs and is subject to Fair Housing protections. Reading, tokenizing, mapping, or including this field in any DNA profile column in any form is a governance violation.

---

## 4. No Raw Free-Form Text in DNA Payloads

The JSON columns `ai_buyer_archetype_tags`, `ai_marketing_hooks`, `lifestyle_tags`, and `deal_breaker_flags` must contain only **structured, deterministically derived values**: booleans, enums, numeric ranges, and explicit key-value trait pairs. Raw listing descriptions, agent remarks, property notes, neighborhood commentary, or any other free-form user-authored text must never appear in these columns.

When a source field (e.g. `restrictions`) is a structured multi-select enum, only its **presence** (yes/null) is recorded — the raw field value is not stored in any DNA payload column.

---

## 5. Append-Only Persistence Mechanics

DNA profile records are **never overwritten or deleted**. The persistence contract for both `PropertyDnaProfile` and `BuyerTenantDnaProfile` is:

1. Within a `DB::transaction()`, acquire a per-listing mutual exclusion lock (`acquireListingLock`) before any read or write (see below).
2. Query for the highest-versioned existing record matching `listing_type` + `listing_id`, **regardless of `archived_at`**.
3. If a prior record exists and its `archived_at` is null, set its `archived_at` to the current timestamp and save it.
4. Create a new record with:
   - `version` = prior version + 1 (or 1 if no prior record existed)
   - `computed_at` = current timestamp
   - `source_listing_updated_at` = the source listing's `updated_at`
   - `archived_at` = null

**Per-listing lock (`acquireListingLock`):** On PostgreSQL (the project's primary database), `pg_advisory_xact_lock` is acquired at the start of the transaction. The lock integer is `crc32('pdna/btdna:{listing_type}:{listing_id}')`. It is released automatically when the transaction commits or rolls back. This serializes concurrent jobs even when no prior profile row exists — the gap that `SELECT … FOR UPDATE` alone cannot cover. On non-PostgreSQL drivers the lock is a no-op; the wrapping transaction provides best-effort ordering.

**Idempotency (rule #13):** Highest-version query (regardless of `archived_at`) ensures a partial prior run (row archived, new row not yet created) produces the correct next version on retry without re-archiving an already-archived row.

---

## 6. Non-Public Restriction — How to Verify

DNA profile data must never appear in any public page, API response, PDF, email, admin screen, or dashboard.

**Verification steps:**
- `php artisan route:list` — confirm zero new routes reference `property_dna_profiles` or `buyer_tenant_dna_profiles`.
- `git diff --name-only` — confirm no Blade views, controllers, API resources, PDF generators, or email templates were modified.
- Review `app/Services/Dna/`, `app/Jobs/`, and `app/Observers/Dna/` — none of these classes expose data to any HTTP response.

---

## 7. Coverage Metrics — Not Scores

The columns named `physical_score`, `financial_score`, `flexibility_score`, `occupant_qualification_score`, `marketing_score`, and `commercial_score` are **dimension coverage metrics only**. Despite being named `*_score` in the Phase A schema (column names are not changed in Phase E), they represent nothing other than the fraction of relevant dimension slots that were populated (non-null) for that group:

```
coverage_metric = (count of non-null populated slots in group) / (total slots in group) × 100
```

This is a **deterministic field-presence completeness measure**. It is not a weighted formula, not a quality assessment, not a desirability ranking, not a valuation, and not a recommendation signal. These values must never be used to rank listings against each other, compare listings for buyer-seller or landlord-tenant matching, or assess occupant suitability.

**`occupant_qualification_score` — important naming caveat:** Despite the column name, this value is the coverage metric for the occupant-policy dimension group (55+ flag, pet policy, occupancy status, restrictions presence, furnishing indicator). It is **not** a tenant screening score, occupant qualification assessment, risk evaluation, or approval metric. Future developers and any downstream consumers must treat it only as a field-completeness indicator for this dimension group. It must never be used or referenced for any occupant screening, qualification, or selection purpose.

**`is_commercial` — deterministic keyword classification:** The `is_commercial` dimension is set by matching explicit listing category terminology (`property_type`, `leasing_space` enum values) against a fixed keyword list (`commercial`, `office`, `retail`, `industrial`, `warehouse`, `mixed`). This is deterministic string classification derived only from the listing's own stated category. It is not AI classification, behavioral inference, probabilistic categorization, or demographic inference. Future contributors must not extend this mapping with inferred or probabilistic logic.

---

## 8. Write-Isolation Rule

Generators and jobs read exclusively from source-of-truth listing tables (`PropertyAuction`, `LandlordAuction`, `BuyerCriteriaAuction`, `TenantCriteriaAuction`) and their associated EAV meta tables. They write exclusively into `property_dna_profiles` and `buyer_tenant_dna_profiles`.

**Generators must never:**
- Write back to any source listing table or meta table.
- Modify any Livewire property, session variable, or form state.
- Trigger any additional `saved` events that could cause recursive observer loops.

`PropertyDnaProfile` and `BuyerTenantDnaProfile` model saves must never dispatch generation jobs. Only the four source listing observers (`PropertyAuctionDnaObserver`, `LandlordAuctionDnaObserver`, `BuyerCriteriaAuctionDnaObserver`, `TenantCriteriaAuctionDnaObserver`) may dispatch generation jobs.

---

## 9. Asynchronous-Only Generation

DNA generation must never be triggered synchronously during an HTTP request lifecycle or Blade rendering pass. All generation must occur exclusively through queued jobs.

The observer `saved` hook dispatches a job only. It never calls the generator inline. This ensures listing save operations have zero latency dependency on DNA generation.

**Queue failure isolation:** If the queue driver is unavailable, if job dispatch fails, or if an exception is thrown inside a generator, the error is silently logged and discarded. It is never surfaced to the user session, never blocks a listing save, and never produces a user-visible error state.

---

## 10. Fail-Closed Dimension Handling

If a source field is missing, null, empty, or malformed, the corresponding dimension slot is left `null`. A missing dimension never aborts the profile record — generation continues for all remaining dimensions. No uncaught exceptions may propagate from dimension mapping. All dimension mapping code is wrapped in per-slot `try/catch` blocks.

---

## 11. No Bulk Backfill in Phase E

Phase E does not implement and must not invoke any command, seeder, Artisan command, scheduled job, or loop that performs bulk DNA generation across all listings. Generation in Phase E occurs only for explicitly triggered listings (via observer-dispatched jobs or direct Tinker invocation during QA). Any future backfill capability belongs to a separately approved phase.

---

## 12. Agent-Only Fields — Internal Restriction

The fields `seller_motivation_category` and `min_income_requirement` are agent-only fields that must not appear in any publicly accessible output. If these fields are ever written to a profile JSON column in a future phase, the profile record itself must not be accessible publicly. In Phase E these fields are not mapped.

---

## 13. Intentionally Skipped Dimensions

The following dimensions were specified as allowed but are skipped in Phase E because no structured source field exists in the current listing schema. Each skip is documented here per governance rule ("skip that dimension and document it — do not bend the rule").

| Dimension | Reason Skipped | Future Resolution |
|---|---|---|
| `commute_preferences` (buyer/tenant) | No structured commute source field exists in `buyer_criteria_auctions` or `tenant_criteria_auctions` meta. `commute_polygon_cache` on `buyer_tenant_dna_profiles` is reserved for a future phase. | Implement when commute origin/mode fields are added to buyer/tenant listing forms. |
| `hoa_preference_specified` (buyer/tenant) | No dedicated HOA source field exists for buyer or tenant listings. Candidate proxies (`leasing_55_plus`, `non_negotiable_amenities`) were rejected because they produce systematic false positives and do not reliably represent HOA preference. | Implement when a dedicated `hoa_preference` or `hoa_tolerance` field is added to buyer/tenant listing forms. |

---

## 14. Source Field Traceability Index

### `PropertyDnaGenerator` (seller / landlord listings)

| Dimension Slot | Source Field(s) | Mapping Rule |
|---|---|---|
| `property_type` | `property_type` meta key | Raw enum value |
| `property_style` | `property_items` meta key | Raw enum value |
| `property_condition` | `condition_prop` meta key | Raw enum value |
| `bedrooms` | `bedrooms` meta key | Raw value |
| `bathrooms` | `bathrooms` meta key | Raw value |
| `minimum_sqft` | `minimum_heated_square` meta key | Raw numeric |
| `total_acreage` | `total_acreage` meta key | Raw numeric |
| `has_pool` | `pool_needed` meta key | Truthy → yes, falsy → no |
| `has_garage` | `garage_needed` meta key | Truthy → yes, falsy → no |
| `has_carport` | `carport_needed` meta key | Truthy → yes, falsy → no |
| `has_storage` | `storage_space` meta key | Truthy → yes, falsy → no |
| `pets_allowed` | `pets` meta key | Truthy → yes, falsy → no |
| `is_55_plus` | `leasing_55_plus` meta key | Truthy → yes, falsy → no |
| `is_commercial` | `property_type`, `leasing_space` meta keys | Keyword match against commercial terms (commercial, office, retail, industrial, warehouse, mixed) |
| `smoking_policy_specified` | `restrictions` meta key | Non-null presence → yes; raw value NOT stored (structured select field) |
| `has_hoa` | `hoa_association` native column (seller) or `hoa_association` meta key (landlord) | Truthy → yes, falsy → no |
| `furnishing_indicator` | `tenant_require` meta key | Non-null presence → "specified" |
| `move_in_timing` | `occupied_until` meta key (landlord), `target_closing_date` meta key | Either non-null → "specified" |
| `occupant_status` | `occupant_status` meta key | Raw value |
| `lease_length_flexibility` | `desired_lease_length` meta key | Raw value |
| `has_lease_option` | `lease_option_price` meta key, `interested_lease_option_agreement` meta key | Non-null price OR truthy agreement → yes |
| `has_lease_purchase` | `lease_purchase_price` meta key | Non-null → yes |
| `has_seller_financing` | `seller_financing_type` meta key | Non-null, non-"none" → yes |
| `has_assumable_loan` | `assumable_terms` meta key | Non-null, non-"none" → yes |
| `sale_provision_type` | `sale_provision` meta key | Raw value |
| `offered_financing_types` | `offered_financing` meta key | Raw value |
| `interested_in_selling` | `interested_in_selling` meta key | Truthy → yes, falsy → no |
| `has_video_tour` | `video_link` meta key | Non-empty → yes |
| `view_preference` | `view_preference` meta key | Raw value |

### `BuyerTenantDnaGenerator` (buyer / tenant listings)

| Dimension Slot | Source Field(s) | Mapping Rule |
|---|---|---|
| `property_type_preference` | `property_type` meta key | Raw value |
| `property_style_preference` | `property_items` meta key | Raw value |
| `property_condition_preference` | `condition_prop_buyer` meta key (fallback: `condition_prop`) | First non-null value |
| `bedroom_preference` | `bedrooms` meta key | Raw value |
| `bathroom_preference` | `bathrooms` meta key | Raw value |
| `minimum_sqft_preference` | `minimum_heated_square` meta key | Raw numeric |
| `budget` | `maximum_budget`, `budget`, `desired_rental_amount` meta keys | First non-null of these three |
| `has_preapproval` | `pre_approved` meta key | Truthy → yes, falsy → no |
| `financing_preference` | `offered_financing` meta key | Raw value |
| `down_payment_type` | `down_payment_type` meta key | Raw value |
| `has_seller_financing_interest` | `seller_financing_type` meta key | Non-null, non-"none" → yes |
| `has_assumable_loan_interest` | `assumable_terms` meta key | Non-null, non-"none" → yes |
| `has_lease_option_interest` | `interested_lease_option` or `interested_lease_option_agreement` meta keys | Either truthy → yes |
| `has_lease_purchase_interest` | `lease_purchase_price` meta key | Non-null → yes |
| `has_pets` | `pets` meta key | Truthy → yes, falsy → no |
| `is_55_plus_preference` | `leasing_55_plus` meta key | Truthy → yes, falsy → no |
| `pool_preference` | `pool_needed` meta key | Truthy → "required", falsy → "not-required" |
| `garage_preference` | `garage_needed` meta key | Truthy → "required", falsy → "not-required" |
| `carport_preference` | `carport_needed` meta key | Truthy → "required", falsy → "not-required" |
| `desired_lease_length` | `desired_lease_length` meta key | Raw value |
| `occupant_status_preference` | `occupant_status` meta key | Raw value |
| `sale_provision_interest` | `sale_provision` meta key | Raw value |
| `view_preference` | `view_preference` meta key | Raw value |
| `timeline_flexibility` | `expiration_date`, `listing_date` meta keys | Either non-null → "specified" |
| `smoking_preference_specified` | `restrictions` meta key | Non-null presence → yes; raw value NOT stored (structured select field) |
| `hoa_preference_specified` | — (SKIPPED) | No dedicated HOA source field exists for buyer/tenant listings. Proxies evaluated and rejected (see Section 13). Always null. |

---

## 15. Phase E File Inventory

| File | Role |
|---|---|
| `app/Services/Dna/PropertyDnaGenerator.php` | Reads Seller/Landlord listings, maps 29 dimension slots, persists `property_dna_profiles` via idempotent append-only transaction |
| `app/Services/Dna/BuyerTenantDnaGenerator.php` | Reads Buyer/Tenant listings, maps 26 dimension slots, persists `buyer_tenant_dna_profiles` via idempotent append-only transaction |
| `app/Jobs/ComputePropertyDnaProfile.php` | Queued job invoking `PropertyDnaGenerator`; wraps execution in try/catch |
| `app/Jobs/ComputeBuyerTenantDnaProfile.php` | Queued job invoking `BuyerTenantDnaGenerator`; wraps execution in try/catch |
| `app/Observers/Dna/PropertyAuctionDnaObserver.php` | Hooks `PropertyAuction::saved`, dispatches `ComputePropertyDnaProfile` in try/catch |
| `app/Observers/Dna/LandlordAuctionDnaObserver.php` | Hooks `LandlordAuction::saved`, dispatches `ComputePropertyDnaProfile` in try/catch |
| `app/Observers/Dna/BuyerCriteriaAuctionDnaObserver.php` | Hooks `BuyerCriteriaAuction::saved`, dispatches `ComputeBuyerTenantDnaProfile` in try/catch |
| `app/Observers/Dna/TenantCriteriaAuctionDnaObserver.php` | Hooks `TenantCriteriaAuction::saved`, dispatches `ComputeBuyerTenantDnaProfile` in try/catch |
| `app/Providers/AppServiceProvider.php` | Registers all four DNA observers; no other existing logic changed |
