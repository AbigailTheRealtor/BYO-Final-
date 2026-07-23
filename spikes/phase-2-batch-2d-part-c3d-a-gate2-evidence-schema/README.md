# Phase 2 · Batch 2D Part C3d-a — Gate 2 corpus-coverage evidence schema (spike)

**Status: cluster-free authoring. Nothing here runs against a cluster or a corpus.**

This spike holds the **Class-2 (C3d-b) recipe** for producing the Gate 2 dataset × territory coverage
matrix from a **real loaded corpus**, plus the **RESULTS template** a product owner fills in. The
offline, deterministic counterpart is `app/Services/Spatial/Gate2/*` +
`config/spatial_gate2.php` + `spatial:gate2-matrix`, exercised over the synthetic fixture in
`tests/Fixtures/Spatial/Gate2/`.

Gate 2 (SSOT §5 / §18 / E-32) is **"corpus coverage"**: per-category coverage across the Florida
footprint, **plus a dataset × territory matrix reported separately for FL, PR, AK, and rural CONUS**,
with four datasets verified explicitly for Puerto Rico (**EPA Walkability, USGS Boat Ramps, NOAA CUSP,
DOT NAD**). C3d-a authors the **evidence schema** for that matrix. It does **not** measure a real
corpus and does **not** decide the gate.

## Contract (owner decisions — C3d-a scope)

- **This is an evidence schema, not a metric.** It defines NO coverage numerator, denominator,
  percentage, or threshold, and performs NO automated pass/fail. The SSOT specifies no Gate 2
  formula and states acceptance is **per-category by the product owner** — that is C3d-b.
- **"Not measured" ≠ "measured zero".** Every expected cell is in the grid; a cell nobody queried is
  `unmeasured` (null count), distinct from `absent` (measured, zero — an honest gap). Cells omitted
  from the input default to `unmeasured`, never a silent hole.
- **FL, PR, AK, rural_CONUS are reported separately** (E-32). The assembler fails closed if any is
  missing — PR and AK can never be folded into CONUS.
- **The four PR watch datasets must be declared**, so their PR cells are always in the matrix and can
  never be quietly dropped.
- **`rural_CONUS` is not defined here.** Which counties count as rural is a C3d-b product/data
  decision; the SQL uses a placeholder parameter and invents nothing.

## Files

- `sql/coverage_matrix.sql` — **AUTHORED, NOT RUN.** Read-only per (dataset × category × territory)
  `count(*)` templates that, at C3d-b, produce the matrix observations. Computes no ratio and no
  pass/fail; writes nothing; SPATIAL_*-guarded (never runs offline).
- `RESULTS_TEMPLATE.md` — the Class-2 fill-in for the real matrix and the product-owner acceptance.

## Offline dry-run

```bash
php artisan spatial:gate2-matrix
# → storage/app/spatial/gate2/{matrix.json, summary.json}
```

Refuses production; opens no `pgsql_spatial` connection; reads no `SPATIAL_*` secret; makes no network
call; downloads nothing; measures no corpus. Exit 0 means **"the evidence schema assembled"** — NOT
"Gate 2 passed".

## Fixture outcome

The synthetic input (`tests/Fixtures/Spatial/Gate2/synthetic-coverage-inputs.json`) declares 7
datasets (8 dataset-category pairs) × 4 territories = 32 cells. It deliberately includes measured-zero
PR cells for the four E-32 watch datasets, one **unmeasured** PR cell (NOAA CUSP — unknown, not zero),
and cells omitted entirely (rendered `unmeasured`) — so every status path is exercised. The counts are
**synthetic and illustrative — not real corpus measurements.**

## Blocked / pending (not failures)

| Item | Why | Unblocks in |
|---|---|---|
| Running `coverage_matrix.sql` | No cluster / no loaded corpus | C3d-b (Class-2) |
| Real per-cell present_counts | Needs Overture/TIGER/PAD-US/FEMA/CMS/NCES/USGS/FAA/GTFS/EPA/NOAA/NAD loaded | C3d-b |
| The coverage numerator / denominator / threshold (if any) | Undefined in the SSOT — a product decision | C3d-b product owner |
| `rural_CONUS` county definition | Product/data decision | C3d-b |
| Writing `corpus_imports.territory_coverage` | Real run only | C3d-b |
| Per-category Gate 2 acceptance | Product-owner decision | C3d-b |
