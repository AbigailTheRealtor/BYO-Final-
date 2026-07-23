# B2D Part C3d-a — Gate 2 corpus-coverage evidence schema (offline)

**Phase 2 · Batch 2D Part C3d-a · Spatial Intelligence Platform**
Branch: `phase-2-batch-2d-part-c3d-a-gate2-evidence-schema`
Status: **cluster-free authoring complete. No PostGIS, no `SPATIAL_*` secrets, no migrations, no
corpus, no downloads, no infrastructure.**

C3 (= "boundaries + Gate 2") is split; C3a/C3b/C3c shipped the boundary importers (PAD-US, Census
TIGER, FEMA NFHL), and **this is C3d-a — the offline evidence schema for Gate 2 (corpus coverage).**
It authors the dataset × territory coverage matrix as a deterministic, license-clean, CI-safe schema
and defers the real measurement and the product-owner acceptance to **C3d-b (Class-2)**.

## What Gate 2 is (SSOT)

Gate 2 = **"corpus coverage"**: *per-category coverage of the owned corpus vs the Google baseline
across the Florida footprint* (ROADMAP L522; ARCH §14.2), **plus a dataset × territory matrix reported
separately for FL, PR, AK, and rural CONUS** (PLATFORM §5 L230, §18 L1241/L1361; E-32). Four datasets
must be verified explicitly for Puerto Rico: **EPA Walkability, USGS Boat Ramps, NOAA CUSP, DOT NAD**.
It blocks Phase 3a and is accepted **per category by the product owner** — there is **no numeric
threshold** in the SSOT (contrast Gate 1's `≤3%`).

## Why C3d-a is an evidence schema, not a harness

A Gate 1-style pass/fail harness cannot be honestly built for Gate 2: the SSOT defines **no coverage
numerator, denominator, percentage, or threshold**, and acceptance is a manual product-owner
decision. Inventing any of those would fabricate a gate the SSOT never specified. So C3d-a authors the
**container** — the dataset × territory matrix, its completeness guarantees, and the evidence
artifacts — and stops exactly where product judgement begins. This mirrors the Part B precedent
(offline Gate 1 harness authored ahead of the Class-2 corpus run) while respecting that Gate 2 has no
automatable metric.

## Approved decisions

| # | Decision |
|---|---|
| **D-C3d-a-1** | C3d-a authors the **evidence schema only**. No coverage numerator, denominator, percentage, threshold, or automated pass/fail is defined or computed. Real measurement + product-owner acceptance are **C3d-b**. |
| **D-C3d-a-2** | **"Not measured" is distinct from "measured zero"** at every layer. `CoverageCell` forbids a not-measured cell from carrying a count; the matrix renders every expected cell, defaulting omissions to `unmeasured`; a measured zero is `absent` (an honest gap). |
| **D-C3d-a-3** | **FL, PR, AK, rural_CONUS are reported separately** (E-32). The assembler **fails closed** if the matrix omits any required territory — PR and AK can never be folded into CONUS. |
| **D-C3d-a-4** | The **four PR watch datasets** (EPA Walkability, USGS Boat Ramps, NOAA CUSP, DOT NAD) must be declared datasets, so their PR cells are always in the matrix and surfaced in a dedicated verification block. |
| **D-C3d-a-5** | **`rural_CONUS` is not defined here.** Which counties are "rural" is a C3d-b product/data decision; the SQL uses a placeholder parameter and invents no set. |
| **D-C3d-a-6** | Provider-agnostic assembler (mirrors the Gate 1 evaluator): it consumes an observation input shape, from a synthetic fixture now and from `coverage_matrix.sql` against a real corpus at C3d-b — the pipeline does not move. |

## Data contract

- **CoverageCell** — `{dataset, category, territory, measured, present_count, status, note}`. `status`
  ∈ `{unmeasured, absent, present}` is a factual DESCRIPTION of the observation, never a score or a
  verdict. `present_count` is null iff `measured` is false.
- **CoverageMatrix** — the full cartesian grid (dataset × its categories × territories) plus the
  declared axes and the PR watch datasets. Every expected cell is present.
- **Gate2CoverageAssembler** — builds the matrix from a decoded input; fails closed on empty axes,
  missing required territories, undeclared PR watch datasets, or stray observations.
- **Gate2CoverageReport** — per-territory roll-ups, the PR watch verification block, honest gap /
  unmeasured lists, and an `acceptance` block whose automated flags are all false and whose
  disposition is "deferred to C3d-b". **No `passed` key exists.**

## What's here

**Services (`app/Services/Spatial/Gate2/`):** `CoverageCell`, `CoverageMatrix`,
`Gate2CoverageAssembler`, `Gate2CoverageReport`.
**Config:** `config/spatial_gate2.php` — fixture path, out-dir, required territories, PR territory, PR
watch datasets.
**Command:** `spatial:gate2-matrix` — offline; refuses production; no DB/secret/network; writes
`matrix.json` + `summary.json`; no `--threshold` option by design.
**Fixture (synthetic):** `tests/Fixtures/Spatial/Gate2/synthetic-coverage-inputs.json` +
committed deterministic `expected-matrix.json` / `expected-summary.json`.
**Spike:** `spikes/phase-2-batch-2d-part-c3d-a-gate2-evidence-schema/` (`sql/coverage_matrix.sql`
AUTHORED-NOT-RUN, README, RESULTS_TEMPLATE).

## Command

```bash
php artisan spatial:gate2-matrix
# → storage/app/spatial/gate2/{matrix.json, summary.json}
```

Exit 0 means the evidence schema assembled — **NOT** that Gate 2 passed.

## Class-2 handoff (C3d-b)

Provision `pgsql_spatial` + PostGIS → set `SPATIAL_*` → load the Phase 2 corpus → run
`coverage_matrix.sql` per (dataset × category × territory) → feed the counts to the SAME assembler →
write `corpus_imports.territory_coverage` → fill `RESULTS_TEMPLATE.md` → present the dataset × territory
matrix (FL/PR/AK/rural CONUS, PR/AK separate, four PR datasets verified) to the product owner → per-
category acceptance → Gate 2 disposition.

## Deferred to C3d-b / product owner (not failures)

Live corpus measurement · real per-cell `present_count`s · the coverage numerator/denominator/threshold
(if any — undefined in the SSOT) · the `rural_CONUS` county definition · writing
`corpus_imports.territory_coverage` · per-category Gate 2 acceptance · the post-de-Google "Google
baseline" denominator resolution.
