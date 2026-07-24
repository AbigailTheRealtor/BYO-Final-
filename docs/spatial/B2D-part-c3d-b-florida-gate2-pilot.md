# B2D Part C3d-b — Gate 2 Florida pilot: live corpus-coverage measurement (Group A)

**Phase 2 · Batch 2D Part C3d-b · Spatial Intelligence Platform**
Branch: `phase-2-batch-2d-part-c3d-b-florida-gate2-pilot`
Status: **Group A (code before data) complete. Cluster provisioned; corpus NOT loaded; no real Gate 2
measurement run outside controlled tests.**

C3d-a authored the **offline** Gate 2 evidence schema (`app/Services/Spatial/Gate2/{CoverageCell,
CoverageMatrix,Gate2CoverageAssembler,Gate2CoverageReport}`, `spatial:gate2-matrix`, synthetic
fixture) and the **AUTHORED-NOT-RUN** recipe `spikes/…c3d-a/sql/coverage_matrix.sql`. **C3d-b is the
live half** that measures the real corpus and feeds the *same* assembler unchanged (D-C3d-a-6). This
document covers **Group A only**: the code that must exist *before* any Florida dataset is loaded.

## Scope of Group A (this slice)

| Artifact | Path | Role |
|---|---|---|
| Corpus config | `config/spatial_gate2_corpus.php` | connection, FIPS map, boundary-attribution kind, measurable-dataset registry, rural_CONUS placeholder |
| Query catalog | `app/Services/Spatial/Gate2/CoverageQueryCatalog.php` | measurable (dataset × category × territory) → **read-only** `count(*)`; FL bound to FIPS `12` |
| Observation source | `app/Services/Spatial/Gate2/CorpusCoverageObservationSource.php` | runs the catalog against pgsql_spatial; emits assembler-shape observations; measured cells only |
| Ledger writer | `app/Services/Spatial/Gate2/TerritoryCoverageLedgerWriter.php` | the **only** live writer — one idempotent `corpus_imports` row |
| Command | `app/Console/Commands/Gate2MeasureCoverage.php` | `spatial:gate2-measure-coverage` — orchestrates the above; FL-only; refuses production & unconfigured cluster |
| Tests | `tests/Unit/Spatial/Gate2/*`, `tests/Feature/Spatial/Gate2MeasureCoverageCommandTest.php` | unit (pure/fake) + feature (fake runner + controlled SQLite ledger) |

## What C3d-b still does NOT do (unchanged from the SSOT / C3d-a)

No coverage numerator, denominator, ratio, percentage, threshold, or automated pass/fail. The SSOT
defines **no Gate 2 formula** and states acceptance is **per-category by the product owner**. The
command's SUCCESS means *"the evidence was measured and assembled"* — it is **not** a Gate 2 pass, and
there is deliberately **no `--threshold` option** and **no `passed` key**.

## Florida-only semantics

- The command measures **FL alone** (state FIPS `12`); asking for any non-FL territory is **refused**.
- A place has **no `state_fips` column** (SSOT §7.2). It is attributed to FL by **spatial containment**
  within a `place_territory_boundary_kind` boundary (default `county`, Census TIGER / C3b) whose
  `attrs->>'state_fips'` is `12`. → **the FL county boundary must be the first dataset loaded** (see
  Group C below); until then every FL cell is a measured zero (`absent`) — honest, but empty.
- **PR / AK / rural_CONUS stay `unmeasured`** — never queried, never coerced to zero. `rural_CONUS` has
  **no FIPS** (an open product decision, D-C3d-a-5); `rural_conus_fips` is an explicit **empty
  placeholder** and must not be invented.
- The four E-32 **PR watch datasets** (`epa_walkability`, `usgs_boat_ramps`, `noaa_cusp`, `dot_nad`)
  remain **declared but unmeasured** — their cells are always in the matrix, never fabricated.

The distinction the whole schema rests on: **absent** = measured, zero features (an honest gap);
**unmeasured** = never queried (unknown). Group A proves both against the empty cluster: FL
overture cells come back `absent`, everything else `unmeasured`.

## Command

```bash
php artisan spatial:gate2-measure-coverage            # FL only; --corpus-version / --out-dir optional
# → storage/app/spatial/gate2-corpus/{matrix.json, summary.json}
# → one corpus_imports row (idempotent on corpus_version + dataset)
```

Guards: refuses production · refuses an unconfigured spatial connection · refuses any non-FL
`--territory` · writes the ledger row **only after** successful assembly.

## Data separation (per the C3d-b plan)

- **Group A — code before data (this slice):** everything above. No dataset loaded; feature tests use a
  fake count runner and a controlled SQLite ledger.
- **Group B — operational loading (separate, later):** load FL Census TIGER county boundary → load
  Overture Places (FL) → run `spatial:gate2-measure-coverage` → present the matrix to the product owner.
- **Group C — minimum FL pilot datasets:** (1) **Census TIGER FL county boundaries** (territory anchor)
  then (2) **Overture Places FL** (the owned corpus). The boundary is first because territory
  attribution depends on it.
- **Deferred:** all PR / AK / rural_CONUS loads, the four PR watch datasets, authority overlays,
  PAD-US, FEMA NFHL, and the `rural_CONUS` county definition.

## Verification (Group A)

PHP `-l` on every new file · `CoverageQueryCatalogTest`, `CorpusCoverageObservationSourceTest`,
`TerritoryCoverageLedgerWriterTest`, `Gate2MeasureCoverageCommandTest` · the existing C3d-a Gate 2
suite unchanged · full `tests/Unit/Spatial` + `tests/Feature/Spatial`. A one-off **live** run against
the empty cluster confirms `absent` vs `unmeasured` and the single ledger row; the known test row is
then removed and the corpus tables proven empty again. **No datasets loaded; no commit in this slice.**

## ⚠️ Live-environment test hazard (record — do not silently rely on it)

`phpunit.xml` forces the DEFAULT database to SQLite (`DATABASE_URL=""`, `DB_CONNECTION=sqlite`,
`DB_DATABASE=:memory:`) but does **not** neutralize the `SPATIAL_*` variables. If
`SPATIAL_DATABASE_URL` is exported in the shell that runs the suite (as it is on a provisioned pilot
box), the `pgsql_spatial` connection resolves to the **live cluster** during tests. The pre-existing
`SpatialFirstSliceSeederIsolationTest` seeders — written to *fail closed when `SPATIAL_*` is unset* —
then execute against Crunchy Bridge and insert the 7 `place_categories` + 8 `place_category_mappings`
seed rows. This was observed once in C3d-b Group A and remediated (the known rows were deleted; all 10
tables returned to empty).

**Safe way to run any spatial suite on a box where the cluster is configured:** disable the spatial
connection for the run, e.g.

```bash
env -u SPATIAL_DATABASE_URL php artisan test tests/Feature/Spatial   # pgsql_spatial inert → seeders fail closed
```

Verified: with `SPATIAL_DATABASE_URL` unset, `tests/Unit/Spatial` (317) and `tests/Feature/Spatial`
(73) all pass and the cluster is untouched (before/after row totals both 0). A durable fix — forcing
`SPATIAL_*` empty in `phpunit.xml` — is **out of scope for this batch** (C3d-b Group A adds no
environment/config change); it is flagged here for a later, dedicated harness change.

The C3d-b command's own tests carry no such hazard: they inject a fake count runner and a controlled
in-memory SQLite ledger, so they never reach the cluster in any environment.

## Handoff to Group B (loading)

Load FL county boundary (C3b importer) → load Overture Places FL (2C framework) → set
`--corpus-version` to the loaded snapshot → run the command → fill the C3d-a `RESULTS_TEMPLATE.md` →
per-category product-owner acceptance → Gate 2 disposition. The measurement pipeline does not move —
only real data arrives.
