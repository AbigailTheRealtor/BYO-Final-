# Q2 Measurement Results — Overture First Slice (Batch 2B, LIVE)

Batch: `phase-2-batch-2b-overture-q2-live-measurements`
Status: **LIVE measurements complete.** DuckDB installed locally (session-scoped,
gitignored `.tools/`), public Overture GeoParquet read from S3. **No PostGIS, no
`SPATIAL_*`, no seeders, no import.** Fills the Batch 2A template
(`spikes/phase-2-batch-2a-overture-first-slice/q2/RESULTS_TEMPLATE.md`).

| Field | Value |
|---|---|
| Overture release (pinned) | `2026-06-17.0` (confirmed present & readable) |
| Confidence floor | `>= 0.90` |
| Primary categories (2A pins) | grocery_store, restaurant, pharmacy, shopping_center, coffee_shop, gym, fitness_center, gas_station |
| Storage proxy (total) | 450 bytes / row |
| Storage proxy (composite GiST) | 94 bytes / row |
| Measured on | 2026-07-17 |
| DuckDB version | v1.5.4 (Variegata) 08e34c447b — official release, sha256 verified |
| Extensions | `httpfs` + `spatial` loaded OK |
| S3 source | `s3://overturemaps-us-west-2/release/2026-06-17.0/theme=places/type=place/*.parquet` (region us-west-2, anonymous) |

## Schema probe verdict — COMPATIBLE with Batch 2A (no conflict)

All Batch 2A field paths resolve in the live release:

| Field path | Live type | OK |
|---|---|---|
| `id` | VARCHAR | ✅ |
| `categories.primary` | STRUCT("primary" VARCHAR, alternate VARCHAR[]) — **deprecated struct still present** | ✅ |
| `confidence` | DOUBLE | ✅ |
| `names.primary` | VARCHAR | ✅ |
| `brand.names.primary` | VARCHAR | ✅ |
| `sources` | STRUCT(...)[] — is a list, so `length()` works | ✅ |
| `bbox` | STRUCT(xmin,xmax,ymin,ymax DOUBLE) | ✅ |
| `basic_category` / `taxonomy` | present (successor fields) | ✅ |

The central 2A bet holds: the deprecated `categories.primary` **coexists** with
`basic_category` + `taxonomy` in `2026-06-17.0`, exactly as
`config/overture_places.php` predicted. Migrate the primary read to
`basic_category` before adopting any release `>= 2026-09`.

## Q2.1 — First-slice row counts (count-only, post-floor)

Run from the committed 2A SQL (`sql/q2/count_*.sql`) — unchanged.

| Scope | Rows (measured) | Total = rows×450 | GiST = rows×94 |
|---|---|---|---|
| Pinellas | 1,675 | 736.08 KiB | 153.76 KiB |
| Florida  | 29,434 | 12.63 MiB | 2.64 MiB |
| CONUS    | **479,042** | **205.58 MiB** | **42.94 MiB** |

Headline (Appendix E, Q2): **CONUS first-slice = 479,042 rows** → ~205.6 MiB
table + ~42.9 MiB composite GiST (~248.5 MiB combined at 544 B/row).
Pinellas count (1,675) equals the smoke-extract line count exactly (cross-check).

## Q2.2 — Per-category counts (CONUS)

`sql/q2/count_per_category.sql` — unchanged. Post-floor. Sum = 479,042 (matches headline).

| Primary category | Rows (post-floor) | Rows (pre-floor) |
|---|---|---|
| gas_station | 120,221 | 197,127 |
| gym | 83,388 | 133,518 |
| restaurant | 80,060 | 150,454 |
| coffee_shop | 74,318 | 101,448 |
| grocery_store | 53,504 | 102,366 |
| pharmacy | 53,040 | 84,354 |
| shopping_center | 14,511 | 27,544 |
| **fitness_center** | **0** | **0** |

## Q2.3 — Confidence histogram (CONUS, pre-floor)

`sql/q2/confidence_histogram.sql` — unchanged. 8-category set, `confidence IS NOT NULL`.

| Bucket (low) | Rows | Kept by 0.90 floor? |
|---|---|---|
| 0.00 | 226 | no |
| 0.05 | 855 | no |
| 0.10 | 1,163 | no |
| 0.15 | 1,552 | no |
| 0.20 | 3,196 | no |
| 0.25 | 16,845 | no |
| 0.30 | 11,530 | no |
| 0.35 | 10,289 | no |
| 0.40 | 8,920 | no |
| 0.45 | 8,303 | no |
| 0.50 | 14,374 | no |
| 0.55 | 16,939 | no |
| 0.60 | 13,593 | no |
| 0.65 | 26,787 | no |
| 0.70 | 12,464 | no |
| 0.75 | 75,494 | no |
| 0.80 | 52,918 | no |
| 0.85 | 42,321 | no |
| 0.90 | 236,957 | yes |
| 0.95 | 228,642 | yes |
| 1.00 | 13,443 | yes |

**Floor cost:** pre-floor total = **796,811**; kept (≥0.90) = **479,042 (60.12%)**;
dropped (<0.90) = **317,769 (39.88%)**. The 0.90 floor removes ~2 in 5 candidate rows.

## Notes / anomalies

1. **`fitness_center` yields 0 rows** in `2026-06-17.0` — confirmed pre-floor
   (true taxonomy absence, not a confidence-floor artifact). Every gym-type place
   carries `categories.primary = 'gym'`. The 8-source-token → 7-canonical-key
   collapse in `App\Services\Spatial\OvertureCategoryMap` remains correct
   (`fitness_center → gym` simply maps 0 rows); empirically the first slice is
   **7 populated source tokens** in this release. Successor batch should not rely
   on `fitness_center` as a populated primary token.

2. **Geometry is native `GEOMETRY('OGC:CRS84')`, not WKB.** With `LOAD spatial`
   active, read_parquet auto-decodes the GeoParquet geometry, so the committed 2A
   `sql/extract_places.sql` line `ST_X(ST_GeomFromWKB(geometry))` must become
   `ST_X(geometry)` / `ST_Y(geometry)`. This affects the smoke-extract ONLY — the
   Q2 count/histogram SQL never touches geometry (bbox struct), so they ran
   unchanged. See `sql/smoke_extract_pinellas.sql` for the adapted recipe.
   **Recommended follow-up (not done here):** apply the same one-line change to the
   committed `extract_places.sql`, or drop `LOAD spatial` from it and keep
   `ST_GeomFromWKB` — either is valid; deferred to the owner.

3. Sizing proxies (450 / 94 B per row) are unchanged planning constants — live Q2
   replaced only the row counts, via `App\Services\Spatial\CorpusSizingProjector`.
