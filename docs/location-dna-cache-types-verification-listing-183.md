# Location DNA Cache & Types Fix — Verification Report: Listing #183

**Date:** 2026-06-23  
**Task:** Location DNA Cache & Types Persistence Fix  
**Listing:** seller_agent / listing_id=183  

---

## Root Cause Summary

Two compounding bugs prevented hardening rules from being applied to Listing #183 after tasks #3150 / #3157 / #3165 / #3176 were deployed:

1. **Cache bypass** — `calculateForListing()` returned cached `property_location_pois` rows when source coordinates matched, skipping `passesExclusionFilter()` entirely. Any listing with a stable address was immune to rule improvements after its first POI fetch.

2. **Stale pre-ranking rows** — Listing #183's POI rows were written on 2026-06-19 before the ranking engine (task #3157) was deployed. All rows had `types_json = NULL`. Because `exclude_if_types_include` rules (e.g. the grocery_store gas-station guard) depend on `types_json`, these rows could never be caught by type-based exclusions. The name-pattern fallback rules (`exclude_if_name_matches_when_types_empty`) were also bypassed by the cache path.

---

## Before State (rank-1 rows — 2026-06-19 snapshot)

| Category       | Rank-1 Name                                                 | types_json | Exclusion Reason                     |
|----------------|-------------------------------------------------------------|------------|--------------------------------------|
| grocery_store  | bp                                                          | NULL       | ❌ `exclude_if_name_matches_when_types_empty` — BP matches gas-station name guard |
| pharmacy       | Animal Hospital of Seminole, A Thrive Pet Healthcare Partner | NULL      | ❌ `exclude_if_name_matches` — matches `animal\s+hospital` pattern |
| golf_course    | Smugglers Cove Adventure Golf                               | NULL       | ❌ `exclude_if_name_matches` — matches `adventure\s+golf` pattern |

---

## Fixes Applied

### 1. `passesExclusionFilter()` — NULL `types_json` handling (comment + clarification)

The method already correctly coerced `null` via `$place['types'] ?? []`. A clarifying comment was added documenting that null (from pre-ranking-engine DB rows) is handled identically to an absent API field. This ensures:
- `exclude_if_types_include` rules produce no match (correct — can't exclude on absent types)
- `exclude_if_name_matches` fires unconditionally (correct)
- `exclude_if_name_matches_when_types_empty` fires when types is null/empty (correct — the name-pattern fallback)

### 2. Cache path re-applies exclusions per category (`calculateForListing()`)

On coordinate-match cache hit, the service now:
1. Groups cached rows by `poi_category`
2. Inspects the rank-1 row per category using `passesExclusionFilter()`
3. Deletes all rows for any category whose rank-1 fails current rules
4. Also detects categories with **no rows at all** (e.g. deleted externally by the backfill command) and marks them for re-fetch
5. Re-fetches only the affected categories — clean categories are reused without any Google API call
6. If all categories pass, returns `status='cached'` as before (no change to the happy path)

### 3. `ldna:backfill-exclusions` artisan command (new)

Iterates all rank-1 `property_location_pois` rows, applies current exclusion rules against stored `poi_name` and `types_json`, and deletes the full category's rows for any listing where rank-1 fails.

Options:
- `--listing-id=183` — targeted single-listing run
- `--dry-run` — preview deletions without writing
- `--force-rerun` — dispatch `ComputeLocationDna` jobs after deletion

---

## Backfill Run — Listing #183

```
php artisan ldna:backfill-exclusions --listing-id=183 --dry-run

[DRY-RUN] No rows will be deleted or jobs dispatched.
Inspecting 19 rank-1 row(s)...
3 rank-1 row(s) fail current exclusion rules:

  [seller_agent / listing_id=183] category=golf_course     rank1="Smugglers Cove Adventure Golf"                        types=[NULL]
  [seller_agent / listing_id=183] category=grocery_store   rank1="bp"                                                   types=[NULL]
  [seller_agent / listing_id=183] category=pharmacy        rank1="Animal Hospital of Seminole, A Thrive Pet Healthcare Partner" types=[NULL]

[DRY-RUN] Would delete all category rows for 3 rank-1 failure(s) across 1 listing(s).
```

```
php artisan ldna:backfill-exclusions --listing-id=183

✓ Deleted 1 row(s): seller_agent / 183 / golf_course
✓ Deleted 1 row(s): seller_agent / 183 / grocery_store
✓ Deleted 1 row(s): seller_agent / 183 / pharmacy

Deleted 3 total row(s) across 1 listing(s).
```

---

## After State (rank-1 rows — 2026-06-23 post-pipeline)

After the backfill and pipeline re-run, the 3 target categories returned correct results:

| Category      | Rank-1 Name                        | types_json (excerpt)                                   | Result  |
|---------------|------------------------------------|--------------------------------------------------------|---------|
| grocery_store | Publix Super Market on 113th St    | supermarket, grocery_or_supermarket, bakery, store ... | ✅ PASS |
| pharmacy      | CVS Pharmacy                       | pharmacy, store, health, point_of_interest ...          | ✅ PASS |
| golf_course   | Seminole Lake Country Club         | health, point_of_interest, establishment                | ✅ PASS |

**BP no longer appears as Grocery Store.**  
**Animal Hospital of Seminole no longer appears as Pharmacy.**  
**Smugglers Cove Adventure Golf no longer appears as Golf Course.**

---

## Test Coverage Added

8 new unit tests in `LocationDnaPoiDistanceServiceTest` (31 total, all passing):

| Test | Scenario | Outcome |
|------|----------|---------|
| `passes_exclusion_filter_excludes_bp_name_when_types_json_is_null` | BP + null types → excluded via name fallback | ✅ |
| `passes_exclusion_filter_does_not_exclude_real_grocery_when_types_json_is_null` | Publix + null types → not excluded | ✅ |
| `passes_exclusion_filter_excludes_animal_hospital_when_types_json_is_null` | Animal Hospital + null types → excluded | ✅ |
| `passes_exclusion_filter_excludes_adventure_golf_when_types_json_is_null` | Smugglers Cove + null types → excluded | ✅ |
| `cache_path_deletes_stale_category_and_fetches_fresh_when_rank1_fails_exclusion` | Cached BP row → detected → deleted → 1 API call only | ✅ |
| `cache_path_refetches_categories_absent_from_db_after_external_deletion` | Category absent after backfill → detected → fetched | ✅ |
| `cache_path_returns_cached_when_all_rank1_rows_pass_exclusions` | All clean rows → status=cached, 0 API calls | ✅ |
| (existing) `it_returns_cached_rows_without_api_call_when_coordinates_match` | Clean cache path unaffected | ✅ |
