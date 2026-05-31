# Phase XI — AI Marketing Report Persistence Migrations
# 15-Item Verification Report

**Date:** 2026-05-31
**Environment:** PostgreSQL (pgsql)
**`php artisan migrate --pretend`:** Clean — no errors, all three table DDL statements validated
**`php artisan migrate`:** Succeeded — all three migrations applied without error

---

## Checklist

| # | Item | Result |
|---|---|---|
| 1 | Three migration files exist under `database/migrations/` for `marketing_reports`, `marketing_report_versions`, and `marketing_report_audits` | **PASS** |
| 2 | All three tables exist in the database after `php artisan migrate` | **PASS** |
| 3 | Unique constraint `marketing_report_versions_report_section_version_uq` on `(marketing_report_id, section_key, version_number)` is present | **PASS** |
| 4 | `marketing_report_versions.marketing_report_id` carries FK → `marketing_reports.id` (`marketing_report_versions_marketing_report_id_foreign`) | **PASS** |
| 5 | `marketing_report_audits.report_id` carries a **nullable** FK → `marketing_reports.id` (`marketing_report_audits_report_id_foreign`; column `is_nullable = YES`) | **PASS** |
| 6 | `marketing_report_audits.profile_id` carries FK → `property_dna_profiles.id` (`marketing_report_audits_profile_id_foreign`; both columns are `bigint`) | **PASS** |
| 7 | `listing_id` is indexed on all three tables (`marketing_reports_listing_id_idx`, `marketing_report_audits_listing_id_idx`); no FK exists on any `listing_id` column (polymorphic) | **PASS** |
| 8 | PostgreSQL `BEFORE UPDATE OR DELETE` trigger `marketing_report_audits_no_update_delete` is present and bound to `marketing_report_audits_append_only()` — two trigger rows (UPDATE + DELETE) confirmed in `information_schema.triggers` | **PASS** |
| 9 | Check constraint `marketing_reports_status_check` enforces 6 allowed status values: `pending_review`, `agent_approved`, `seller_approved`, `published`, `rejected`, `held_attribution_failure` | **PASS** |
| 10 | Check constraint `marketing_report_versions_status_check` enforces 5 allowed status values: `pending_review`, `approved`, `revised`, `rejected`, `internal_note` | **PASS** |
| 11 | Check constraint `marketing_report_audits_event_type_check` enforces 4 allowed event_type values: `generation`, `review`, `readiness_failure`, `attribution_failure` | **PASS** |
| 12 | All `created_at` columns (all three tables) and `marketing_reports.updated_at` default to `now()` per XH Sections 4.1, 5.1, 6.1 | **PASS** |
| 13 | `marketing_reports.report_contract_version` defaults to `'phase-w-v1'` per XH Section 4.1 | **PASS** |
| 14 | `marketing_report_versions.draft_text` defaults to `''` and `source_attribution` defaults to `'[]'` per XH Section 5.1 | **PASS** |
| 15 | No routes, controllers, Blade views, Livewire components, JS, seeders, or existing service files were created or modified | **PASS** |

**Overall: ALL 15 ITEMS PASS**

---

## Migration File Index

| File | Table |
|---|---|
| `database/migrations/2026_05_31_000002_create_marketing_reports_table.php` | `marketing_reports` |
| `database/migrations/2026_05_31_000003_create_marketing_report_versions_table.php` | `marketing_report_versions` |
| `database/migrations/2026_05_31_000004_create_marketing_report_audits_table.php` | `marketing_report_audits` |

## Notes

- Trigger creation is driver-gated: the `BEFORE UPDATE OR DELETE` trigger is only emitted when `DB::getDriverName() === 'pgsql'`. On non-PostgreSQL drivers (e.g. SQLite in local dev), the trigger is skipped and the append-only design intent is documented in the migration as a comment. `down()` is likewise gated.
- `marketing_report_audits.profile_id` carries a FK per the Done Looks Like acceptance criteria (item 6), which takes precedence over the Step 3 description that said "indexed only." The Done Looks Like is the authoritative acceptance test.
- `listing_id` on all three tables is index-only with no FK, as required for the polymorphic listing reference.
