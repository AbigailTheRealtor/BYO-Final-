# B2D Part C3b — Census TIGER boundary import authoring (offline)

**Phase 2 · Batch 2D Part C3b · Spatial Intelligence Platform**
Branch: `phase-2-batch-2d-part-c3b-tiger-boundary-import`
Status: **cluster-free authoring complete. No PostGIS, no `SPATIAL_*` secrets, no migrations, no
downloads, no infrastructure.**

C3 (= "boundaries + Gate 2") is split; C3a shipped PAD-US protected areas, and **this is C3b — the
four Census TIGER/Line boundary layers** (county, place, ZCTA, unified school district), authored
offline. It **reuses the C3a boundary framework unchanged** and produces canonical `BoundaryRecord`
NDJSON that a Class-2 recipe loads into `boundaries` (migration 06) and subdivides into
`boundaries_parts` (migration 07).

## Approved decisions

| # | Decision |
|---|---|
| **D-C3b-1** | A shared abstract `CensusTigerBoundarySource` base + four thin `final` subclasses (county/place/zcta/school-district). The normalization loop, geometry handling, and reject accounting live once in the base; each subclass fixes `sourceKey`, `kind`, GEOID key candidates, and `attrs`. |
| **D-C3b-2** | The offline `corpus_version` placeholder and the command banner are **derived from the source key** (`"{key}-authoring-fixture"`, neutral `[corpus:import-boundaries]` banner), replacing the C3a-specific literals. The command is now source-agnostic across all boundary sources. |
| (inherited) | Canonical GeoJSON MultiPolygon (Polygon wrapped deterministically); **no centroid**; **structural validation only** (ST_IsValid/ST_MakeValid = Class-2); duplicate `external_ref` is a hard-fail. |

## Data contract

- Boundary kinds: `county`, `place`, `zcta`, `school_district`.
- external_ref = the TIGER **GEOID** (zero-padded): county STATEFP+COUNTYFP (5), place STATEFP+PLACEFP
  (7), ZCTA 5-digit (2020: `ZCTA5CE20`/`GEOID`), school district STATEFP+UNSDLEA. Read from the
  extract — never rebuilt from the live map adapter's unpadded FIPS.
- Geometry: canonical GeoJSON MultiPolygon.
- attrs: `source = 'census_tiger'`, no `acres`; county `{name,namelsad,state_fips}`, place
  `{name,basename,state_fips}`, zcta `{name:null}`, school district `{name,state_fips}`.
- **No place rows, no `places.authority_metric`, no authority-link or ranking change, no migration.**

## Scope (R10)

TIGER **district boundaries** only (current, launch-safe). NCES **SABS attendance zones** (frozen
2015-16) are a separate dataset and are **excluded**.

## What's here (framework reused unchanged — B1.2 06/07 exist)

**Adapters (`app/Services/Spatial/Boundary/`):** `CensusTigerBoundarySource` (base) +
`CensusCountyBoundarySource`, `CensusPlaceBoundarySource`, `CensusZctaBoundarySource`,
`CensusSchoolDistrictBoundarySource`.
**Config:** `config/spatial_boundaries.php` — four new TIGER source rows.
**Command:** `corpus:import-boundaries --source=tiger_*` (D-C3b-2 de-hardcode).
**Spike:** `spikes/phase-2-batch-2d-part-c3b-tiger-boundary-import/` (`sql/stage_boundaries.sql`,
`sql/load_tiger_boundaries.sql` — AUTHORED-NOT-RUN, `ST_Subdivide` + `ST_IsValid` — README,
RESULTS_TEMPLATE).
**Fixtures (synthetic):** `tests/fixtures/spatial/boundaries/tiger/{county,place,zcta,school_district}_{raw,expected}.ndjson`.

**Reused unchanged from C3a:** `BoundaryGeometry`, `BoundaryRecord`, `BoundaryNormalizationResult`,
`BoundarySource`, `BoundaryImportAcceptance` (its `acres_non_negative` check is a benign no-op for
TIGER), `BoundaryRowMaterializer`.

## Command

```bash
php artisan corpus:import-boundaries --source=tiger_county   # and tiger_place / tiger_zcta / tiger_school_district
```

Each shipped fixture: **2 kept**, **1 rejected_invalid_geometry** (unclosed ring), **1
rejected_invalid_field** (missing GEOID). Each `boundaries.ndjson` byte-matches its `*_expected.ndjson`.

## Class-2 handoff

Download real TIGER/Line + ZCTA → run the offline importer per layer → `stage_boundaries.sql` →
`load_tiger_boundaries.sql` (append `boundaries`, `ST_Subdivide` → `boundaries_parts`, `ST_IsValid`
verification) → decide any `ST_MakeValid` remediation with evidence → validate via
`BoundaryImportAcceptance`.

## Deferred (not failures)

NCES SABS attendance zones · elementary/secondary (non-unified) districts · FEMA NFHL (C3c) · live
TIGER download + load/subdivide (Class-2) · `ST_IsValid`/`ST_MakeValid` execution · Gate 2 (C3d).
