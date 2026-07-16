# B1.2 — Tier-2 mixed-geometry KNN acceptance (E-50) — RESULT

**Cluster:** Crunchy Bridge verification cluster (`pgsql_spatial`, PostgreSQL 16.14).
**Corpus:** `corpus_version = 'fixture-tier2-v1'`, 5,000,200 rows, true `geography(Geometry,4326)` mix (Point + Polygon + LineString).
**Branch:** `phase-2-batch-1b-postgis-schema-migrations`. **Path taken:** composite-GiST KNN on `geom` (NOT the `centroid` fallback).
**Outcome:** **PRIMARY E-50 GATE — PASS.**

This run is the post-remediation re-run. The first run failed on two spike-artifact defects (spatially degenerate fixture; parent-index-name assertion in the validator), both fixed in commit `e689bd093`. Migrations, schema, SSOT, and the E-50 acceptance criteria are unchanged.

## 1. Fixture integrity
- Total rows: **5,000,200** (matches manifest / `FixtureCorpusPlan`).
- `distinct_locations == rows` for every category (2,265,000 restaurants … 4,000 airports) — spatial degeneracy resolved. Evidence: `reload_distribution.out`.

## 2. Part A — plan shape (`run_validation.php`) — PASS (5/5)
`airport, boat_ramp, marina, urgent_care, park` all PASS. Per category:
`Index Scan using places_fixture_tier2_category_key_geom_idx` (partition-local child of `places_cat_geom`) · `Index Cond: category_key = …` · in-scan `<->` `Order By` · 0 rows removed by recheck · 0 rows removed by filter · no Sort · no Seq Scan. Evidence: `plan_shape_gate_rerun.out`, `knn_explain_raw_rerun.out`.

## 3. Part B — set-exactness + order (`knn_explain.sql`) — PASS (4/4)
Over-fetch(20) + spheroidal re-rank vs brute-force spheroid ground truth:
`same_set = t` AND `exact_order_after_rerank = t` for `airport, boat_ramp, marina, urgent_care`. Evidence: `knn_explain_raw_rerun.out` (Part B).

## 4. Q2 storage (fixture partition, ~5M mixed rows)
| Object | Heap | Indexes | Total | Bytes/row |
|---|---|---|---|---|
| `places_fixture_tier2` | 922 MB | 1225 MB | **2146 MB** | ~450 |

Per-index: `places_fixture_tier2_pkey` 237 MB · `…_corpus_version_source_source_ref_key` (unique) 538 MB · `…_category_key_geom_idx` (composite GiST) **449 MB**. Evidence: `storage_and_pruning.out`.

## 5. KNN p95 timing (advisory, server-side, warm, 100 runs/cat)
| Category | p50 ms | p95 ms | max ms |
|---|---|---|---|
| airport | 0.187 | 2.491 | 4.272 |
| boat_ramp | 0.423 | 0.464 | 0.696 |
| marina | 0.507 | 0.668 | 10.963 |
| urgent_care | 0.465 | 0.675 | 4.978 |
| park | 1.197 | 2.426 | 12.017 |

Measured in-DB via `clock_timestamp()` (excludes client/SSL round-trip). Advisory only. Evidence: `knn_timing_p95.out`.

## 6. Partition pruning
- Matching key (`corpus_version='fixture-tier2-v1'`): scans only `places_fixture_tier2` — no Append over other partitions.
- Non-matching key: `One-Time Filter: false` — all partitions pruned.
Evidence: `storage_and_pruning.out`.

## Status
E-50 caveat closed for mixed `geography(Geometry,4326)` via the composite-GiST path. Cluster left **running** pending teardown approval. Fixture partition **retained**. PR #11 not merged; B1.3 not begun.
