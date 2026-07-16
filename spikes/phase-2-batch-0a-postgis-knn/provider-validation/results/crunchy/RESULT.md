# Stage 0b Provider Result — Crunchy Bridge

Non-secret evidence only. Captured `.out` files under `tier1/`. No credential,
`.pgpass` line, or connection secret appears here or in any captured file.

## Environment (from `tier1/70_measurements.out`)
- Provider / region: Crunchy Bridge (AWS), direct/session endpoint, port **5432**
- Host: `p.a42gumq4xbhf5ifnxfxz32dk3m.db.postgresbridge.com`
- PostgreSQL: **16.14** (aarch64)
- PostGIS: **3.6.3** (GEOS 3.14.0, PROJ 9.6.2) — satisfies the ≥ 3.5 requirement (exceeds the local 3.5.2 baseline; plan shape identical)
- Extensions installed: postgis **3.6.3** · btree_gist **1.7** · pg_trgm **1.6**
- Connection type: **direct session** (not the `:5431` transaction pooler)

## Tier 1 (176,560 rows) — parity
- **Runtime:** 20.6 s (whole 8-step run).
- **Distribution:** matches committed Stage 0a exactly — restaurant 80,000 · retail_store 60,000 · school 20,000 · park 15,000 · urgent_care 900 · marina 300 · boat_ramp 220 · airport 140; total 176,560; partition v1 70,395 / v2 106,165; unpartitioned == partitioned (parity `t`).
- **Composite (A) — PASS.** All four sparse categories → `Index Scan using places_cat_geom` · `Index Cond: category_key = '<cat>'` · `Order By: geom <-> ref` inside the scan · **0** rows removed by filter · **0** top-level sort · **0** seq scan. Buffers: boat_ramp 146 (cold first), airport 8, marina 15, urgent_care 15.
- **Partitioned @v2 — PASS.** Prunes to `places_spike_part_v2`, uses its local composite index `places_spike_part_v2_category_key_geom_idx`, same Index Cond + in-scan KNN. boat_ramp@v2 9 buffers; urgent_care@v2 13 buffers.
- **Geography-only control (B) — degrades as designed.** `Index Scan using places_geom_only` + category post-filter; Rows Removed by Filter: boat_ramp 8,826 (9,113 buf) · airport 14,347 (14,575 buf) · marina 9,243 (9,412 buf) · urgent_care 1,280 (1,343 buf). Composite reads ~62× fewer buffers for boat_ramp (146 vs 9,113).
- **Partial fallback (C) — PASS.** `Index Scan using places_geom_<cat>` per category; no rows removed, no sort, no seq scan.
- **KNN correctness — exact.** `<->` vs `ST_Distance` top-10 identical (order + set) for all four sparse categories: `exact_order_match = t`, `same_set = t`.

## Storage / index measurements (Tier 1)
- `places_spike`: 38 MB total (17 MB heap, 21 MB indexes).
- Composite index `places_cat_geom`: **18 MB** (18,391,040 bytes) = **104.16 bytes/row**; heap 98.78 bytes/row.
- Partitioned: v1 9,000 kB, v2 13 MB.
- **150 GB heap extrapolation** (from `tier1/70_measurements_extrapolation.out`): ~1.63 B rows; projected composite index ≈ **158 GB**.

> Note: the final extrapolation SELECT in the committed `sql/70_measurements.sql`
> errored on this run (`pg_size_pretty(double precision)` / int overflow on
> `150*2^30`). All version and size numbers were captured before it; the
> extrapolation was recomputed read-only with numeric arithmetic and saved to
> `tier1/70_measurements_extrapolation.out`. **Follow-up:** fix the helper
> (cast to numeric/bigint) on a separate scaffolding branch — not on this results
> branch.

## Tier 2 (~5,000,200 rows) — scale / plan stability
- **Runtime:** 4 m 51 s (full 8-step run; corrected `70_measurements.sql`, clean exit).
- **Dataset:** 5,000,200 rows — restaurant 2,265,000 · retail_store 1,700,000 · school 566,000 · park 425,000 · urgent_care 25,500 · marina 8,500 · boat_ramp 6,200 · airport 4,000. Partition v1 1,992,738 / v2 3,007,462; unpartitioned == partitioned (parity `t`). Sparse categories stay < 0.6% each.
- **Composite (A) — PASS at scale.** All four sparse categories → `Index Scan using places_cat_geom` · `Index Cond: category_key='<cat>'` · `Order By: geom <-> ref` in-scan · **0** rows removed · **0** sort (verified: no Sort node anywhere in the plan) · **0** seq scan. No cost-model crossover to seq-scan/sort at 5 M. Composite sparse KNN actual time 0.12–5.34 ms.
- **Partitioned @v2 — PASS.** Prunes to `places_spike_part_v2`, uses local composite `places_spike_part_v2_category_key_geom_idx`, same Index Cond + in-scan KNN.
- **Geography-only control (B) — degrades as designed.** Rows Removed by Filter: boat_ramp 6,081 · airport 12,445 · marina 8,622 · urgent_care 2,339.
- **Partial fallback (C) — PASS.** `Index Scan using places_geom_<cat>` per category; no rows removed, no sort, no seq scan.
- **KNN correctness — SET exact; ORDER caveat (see below).** `same_set = t` for all four categories. `exact_order_match`: boat_ramp/marina/urgent_care `t`; **airport `f`** — two adjacent, near-equidistant ids (4997005 ↔ 4998663) swapped.

## KNN ordering caveat — sphere (`<->`) vs spheroid (`ST_Distance`)
Root cause (evidence: `tier2/knn_ordering_diagnosis.out`): the geography KNN
operator `geom <-> ref` ranks by the **sphere** distance and is bit-for-bit equal
to `ST_Distance(geom, ref, use_spheroid => false)`, whereas the correctness
check's `ST_Distance(geom, ref)` defaults to the **spheroid**. The two differ
~0.1–0.5%; at 5 M density neighbors are close enough that this reorders (and, at
the K-boundary, could in principle include/exclude) near-equidistant points.
Tier 1's sparser corpus did not expose it (all `t`).

For the airport pair: `<->` / sphere = 98,138.463 vs 98,362.171 (→ 4997005 first);
spheroid = 98,039.060 vs 98,004.895 (→ 4998663 first).

**Mitigation (proven, evidence in the same file):** over-fetch top-K×2 by `<->`
then re-rank by exact `ST_Distance` and take top-K → matches brute-force spheroid
top-10 **exactly** (`t`). The production query pattern must over-fetch + re-rank
(or accept sphere ordering) — a single-pass `ORDER BY geom <-> ref LIMIT k` is not
guaranteed to equal exact spheroidal nearest-K for near-equidistant neighbors.

## Storage / index measurements (Tier 2)
- `places_spike`: 1,092 MB total (486 MB heap, 606 MB indexes).
- Composite `places_cat_geom`: **499 MB** (523,091,968 bytes) = **104.61 bytes/row**; heap 101.94 bytes/row; pkey 107 MB.
- Partitions: v1 254 MB, v2 383 MB.
- **150 GB heap extrapolation** (corrected helper, clean): ~1.58 B rows; projected composite index ≈ **154 GB**.

## Environment (Tier 2, re-confirmed)
PostgreSQL 16.14 · PostGIS 3.6.3 · btree_gist 1.7 · pg_trgm 1.6 · direct `:5432` session. No errors/warnings in the run.

## Final verdict — **CAVEATED**
- [x] **PASS (composite plan + set correctness)** at both tiers: composite GiST on
  `geography` gives category `Index Cond` + in-scan `<->` KNN, zero filter-walk,
  no sort, no seq scan, on unpartitioned and partitioned layouts; control degrades;
  partial fallback passes; neighbor **set** exact for all four categories at 5 M.
- [ ] **Caveat:** geography `<->` orders by sphere, not spheroid, so single-pass
  KNN ordering is not guaranteed exact for near-equidistant neighbors at scale
  (observed: airport, same set, one adjacent swap). **Mitigation proven**
  (over-fetch + re-rank by `ST_Distance`). This is a query-pattern requirement, not
  a provider or index defect — it applies to PostGIS geography KNN on any host.
- **Crunchy Bridge is procurement-eligible (CAVEATED):** the caveat is a shared
  PostGIS characteristic to encode in the app's retrieval pattern, not a reason to
  reject the provider.

## Cleanup confirmation (pending — cluster still running)
- [ ] spike objects dropped  [ ] cluster destroyed  [ ] storage/snapshots deleted
- [ ] firewall entries removed  [ ] `.pgpass` / secret shredded  [ ] $0 residual confirmed
