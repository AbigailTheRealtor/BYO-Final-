# Phase 2 · Batch 2A — Overture First Slice (OFFLINE extract)

Branch: `phase-2-batch-2a-overture-first-slice-offline-extract`
Status: **cluster-free authoring scope (B1–B4) complete; live Q2 measurements PENDING (no DuckDB / no download in this batch).**

This spike holds the **DuckDB recipes** and the **Q2 sizing harness** for the
Overture Places first slice. It is authored, not run:

- **No PostGIS cluster** and **no `SPATIAL_*` secrets** are used.
- **DuckDB is not installed** and **no Overture data is downloaded**.
- **No infrastructure is provisioned.**

The runnable PHP (normalizer, NDJSON IO, sizing projector, the
`corpus:extract-overture` command) lives in the app and is exercised by the
offline test suite against a committed fixture — see the batch doc.

## Pins (SSOT: `config/overture_places.php`)

| Pin | Value |
|---|---|
| Overture release | `2026-06-17.0` (latest stable monthly at 2026-07-17) |
| Confidence floor | `>= 0.90` |
| Primary categories | grocery_store · restaurant · pharmacy · shopping_center · coffee_shop · gym · fitness_center · gas_station |
| Total storage proxy | ~450 B/row |
| Composite GiST proxy | ~94 B/row |

`fitness_center` and `gym` collapse to canonical `gym` (8 source categories → 7
canonical keys). The MAP is `App\Services\Spatial\OvertureCategoryMap` — the SQL
here only *filters* to the 8 source tokens; it never maps.

## Layout

```
sql/
  extract_places.sql            # GeoParquet → filtered raw NDJSON (feeds the PHP normalizer)
  q2/
    count_pinellas.sql          # Q2 count-only, Pinellas bbox
    count_florida.sql           # Q2 count-only, Florida bbox
    count_conus.sql             # Q2 count-only, CONUS bbox (headline)
    count_per_category.sql      # Q2 per primary-category counts
    confidence_histogram.sql    # Q2 confidence distribution (pre-floor)
q2/
  run_measurements.php          # detects missing DuckDB → reports all PENDING
  RESULTS_TEMPLATE.md           # copy to RESULTS.md and fill in when live
```

## Later (Class-2 phase, when DuckDB + network exist)

1. `php spikes/phase-2-batch-2a-overture-first-slice/q2/run_measurements.php`
   → measure Pinellas / Florida / CONUS row counts.
2. Fill `RESULTS.md` from `RESULTS_TEMPLATE.md`; apply the ×450 / ×94 proxies.
3. Optionally run `sql/extract_places.sql` to produce a raw NDJSON, then
   `php artisan corpus:extract-overture --input=… --output=…` to normalize.

Schema note: release `2026-06-17.0` still ships the deprecated `categories`
struct (`categories.primary`). Any release `>= 2026-09` removes it — migrate the
primary read to `basic_category` before adopting a newer release.
