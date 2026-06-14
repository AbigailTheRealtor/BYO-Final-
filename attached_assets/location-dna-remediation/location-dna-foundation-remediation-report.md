# Location DNA Foundation Remediation — Evidence Report
**Task:** #2753 — Location DNA Foundation Remediation
**Date:** 2026-06-14
**Remediating:** Blockers found in Task #2748 failed audit

---

## 1. `php artisan migrate` Output

All four CREATE TABLE migrations were already applied in batch 39. Running `php artisan migrate` on the current environment confirms nothing is pending:

```
Nothing to migrate.
```

`php artisan migrate:status` confirms all four new migrations ran successfully:

```
| Ran? | Migration                                                | Batch |
|------|----------------------------------------------------------|-------|
| Yes  | 2026_06_14_000001_create_buyer_criteria_auction_metas_table  | 39 |
| Yes  | 2026_06_14_000002_create_tenant_criteria_auctions_table      | 39 |
| Yes  | 2026_06_14_000003_create_tenant_criteria_auction_metas_table | 39 |
| Yes  | 2026_06_14_000004_create_tenant_criteria_auction_bids_table  | 39 |
```

---

## 2. `php artisan db:seed --class=LocationDnaTestSeeder` Output

```
LocationDnaTestSeeder: buyer_criteria_auctions#3 and tenant_criteria_auctions#1 seeded.
Database seeding completed successfully.
```

### Seeded Record Details

| Table | ID | user_id | LDNA meta |
|---|---|---|---|
| `buyer_criteria_auctions` | 3 | 136 | present (211 bytes) |
| `tenant_criteria_auctions` | 1 | 141 | present (246 bytes) |

Both records have a `location_dna_preferences` meta entry containing cities, zip codes, neighborhoods (plain strings), and location notes. The seeder is idempotent (`updateOrInsert`) and safe to re-run.

---

## 3. Six-Route Smoke-Test Results

Tested unauthenticated (no session cookie). Captured after all fixes were applied.

| HTTP | Result | Route |
|------|--------|-------|
| 302 | Redirects → `/login` ✅ | `GET /buyer-agent/auction/add` |
| 302 | Redirects → `/login` ✅ | `GET /tenant/criteria/auction/add` |
| 302 | Redirects → `/login` ✅ | `GET /buyer-agent/auction/edit/3` |
| 302 | Redirects → `/login` ✅ | `GET /tenant/criteria/auction/edit/1` |
| 200 | Public view renders ✅ | `GET /criteria/view/3` |
| 200 | Public view renders ✅ | `GET /tenant/criteria/auction/view/1` |

Both public views render the map canvas, NEIGHBORHOODS section with chips
("Downtown Orlando" for buyer; "Hyde Park", "Downtown Tampa" for tenant),
location notes, and the sidebar bid panel — all without PHP errors.

---

## 4. Schema Justification for All Four New Tables

### 4a. `buyer_criteria_auction_metas`

**Columns:** `id`, `buyer_criteria_auction_id`, `meta_key`, `meta_value`

**Justification:** Mirrors the EAV meta pattern used by every other auction type in the codebase (e.g. `seller_auction_metas`, `landlord_auction_metas`). `BuyerCriteriaAuction::saveMeta()` and `info()` operate on this table exclusively. No native data columns are expected — all listing fields (cities, counties, property types, etc.) are written via `$auction->saveMeta($key, $value)` in the controller. No timestamps by design (consistent with all other `*_metas` tables in the project).

---

### 4b. `tenant_criteria_auctions`

**Columns (20 total):**
`id`, `ai_share_token`, `display_bids`, `user_id`, `is_approved`, `is_draft`,
`listing_date`, `expiration_date`, `referring_agent_id`, `referral_source_code`,
`referral_captured_at`, `referral_locked`, `listing_ai_faq`, `created_at`,
`updated_at`, `listing_id`, `is_sold`, `is_paid`, `auction_type`, `auction_length`

**Justification:** Schema was derived by merging the CREATE base columns (drawn from `BuyerCriteriaAuction` model, which is the parallel buyer-side table) with every existing ALTER migration that targeted `tenant_criteria_auctions`:

| Source migration | Columns added |
|---|---|
| `2024_12_06_171303_add_display_bids_column_to_tenant_criteria_auctions_table` | `display_bids` |
| `2026_04_22_100004_add_referral_columns_to_tenant_criteria_auctions_table` | `referring_agent_id`, `referral_source_code`, `referral_captured_at`, `referral_locked` |
| `2026_04_28_045541_add_listing_ai_faq_to_tenant_criteria_auctions_table` | `listing_ai_faq` |
| `2026_04_28_052331_add_ai_share_token_to_tenant_criteria_auctions_table` | `ai_share_token` |
| Tinker (columns missing from guarded ALTER on pre-existing table) | `is_sold`, `is_paid`, `auction_type`, `auction_length` |

All four ALTER migrations used `Schema::hasTable()` guards, which meant they silently skipped the table that had been created mid-migration-history via tinker. The CREATE migration in #2753 includes all columns inline so no ALTER is needed.

---

### 4c. `tenant_criteria_auction_metas`

**Columns:** `id`, `tenant_criteria_auction_id`, `meta_key`, `meta_value`

**Justification:** Identical EAV structure to `buyer_criteria_auction_metas`. The `TenantCriteriaAuction::saveMeta()` method (and `info()`) operate on this table. All tenant listing fields (cities, bedrooms, lease length, property type, etc.) are stored here via `$auction->saveMeta($key, $value)` — confirmed in `TenantCriteriaAuctionController::store()` which makes 30+ `saveMeta` calls and zero native-column assignments beyond the base fields. No timestamps by design.

---

### 4d. `tenant_criteria_auction_bids`

**Columns (9 total):**
`id`, `tenant_criteria_auction_id`, `user_id`, `listing_id`, `counter_id`,
`is_accepted`, `accepted_date`, `created_at`, `updated_at`

**Justification:** The table appears small because all bid field data (monthly price, bedrooms, lease terms, etc.) flows through `TenantCriteriaAuctionBid::saveMeta()` EAV — exactly the same pattern used by all other bid tables in the project. Native columns cover only structural/relational fields:

- `tenant_criteria_auction_id` — foreign key to parent listing
- `user_id` — bid submitter
- `listing_id` — varchar identifier
- `counter_id` — added by existing ALTER migration `2024_12_11_153127_add_counter_id_column_in_tenant_criteria_auction_bids_table`; included inline in the CREATE to make that ALTER's `Schema::hasTable()` guard a no-op
- `is_accepted`, `accepted_date` — accept/reject state
- `created_at`, `updated_at` — standard timestamps

The `BuyerCriteriaAuctionBid` table (parallel buyer-side) has the identical native-column shape, confirming this is the intended architecture.

---

## 5. Code Fixes Summary

| File | Fix |
|---|---|
| `resources/views/buyer_criteria/view.blade.php` | 3 occurrences of `auth()->user()->id` → `auth()->check() && auth()->id()` (lines 256, 1574, 1616) |
| `resources/views/tenant_criteria/view.blade.php` | 2 occurrences of `auth()->user()->id` → `auth()->check() && auth()->id()` (lines 111, 717) |
| `app/Models/BuyerCriteriaAuction.php` | `getGetAttribute()` returns `new Fluent($data)` instead of `(object)$data` / `Collection::first()` — resolves PHP 8.2 undefined-property warnings |
| `app/Models/TenantCriteriaAuction.php` | Same `getGetAttribute()` Fluent fix |
| `app/Http/Controllers/TenantCriteriaAuctionController.php` | Added `$page_data['lowest_bid_price']` (was undefined variable at view line 708); corrected variable-ordering so `$auction` is assigned before it is referenced |

---

## 6. Historical Context

The original Task #2748 **FAILED** audit report remains in `attached_assets/` as historical evidence of the pre-remediation state. This document is the **post-remediation evidence report** for Task #2753 only.
