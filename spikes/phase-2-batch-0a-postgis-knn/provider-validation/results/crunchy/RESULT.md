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

## Verdict
- [x] **PASS (composite)** — Tier 1. Procurement-eligibility gate met for Tier 1;
  Tier 2 (scale) still pending.
- Anomalies: only the `70_measurements.sql` pretty-print bug above (cosmetic; does
  not affect the PASS determination).

## Cleanup confirmation (pending — cluster still running)
- [ ] spike objects dropped  [ ] cluster destroyed  [ ] storage/snapshots deleted
- [ ] firewall entries removed  [ ] `.pgpass` / secret shredded  [ ] $0 residual confirmed
