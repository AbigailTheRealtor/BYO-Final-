# Phase 2 · Batch 2D Part C3b — Census TIGER boundary import (spike)

**Status: cluster-free authoring. Nothing here runs against a cluster.**

This spike holds the **Class-2 recipes** for staging and loading Census TIGER/Line boundaries
(county, place, ZCTA, unified school district) into the `boundaries` table (SSOT §7.2 deliverable #7).
The offline, deterministic counterpart reuses the C3a boundary framework unchanged
(`BoundaryGeometry`, `BoundaryRecord`, `BoundarySource`, `BoundaryImportAcceptance`,
`BoundaryRowMaterializer`) plus the C3b adapters in `app/Services/Spatial/Boundary/`
(`CensusTigerBoundarySource` base + county/place/ZCTA/school-district subclasses), exercised by
`corpus:import-boundaries` over the synthetic fixtures in `tests/fixtures/spatial/boundaries/tiger/`.

## Contract (owner decisions, inherited from C3a)

- Canonical **GeoJSON MultiPolygon** everywhere offline; a valid `Polygon` is wrapped deterministically.
- **No centroid**; **structural validation only** (topology / `ST_IsValid` / `ST_MakeValid` is Class-2).
- external_ref = the TIGER **GEOID** (zero-padded stable code) — read from the extract, never rebuilt
  from unpadded FIPS. ZCTAs carry no name (`attrs.name = null`).
- attrs carries `source = 'census_tiger'` and **no `acres`**.
- **Scope (R10):** TIGER district *boundaries* only; NCES SABS attendance zones are a separate,
  deferred dataset.

## Files

- `sql/stage_boundaries.sql` — **AUTHORED, NOT RUN.** Creates `boundaries_staging` (source-neutral)
  and stages the offline `boundaries.ndjson`. COPY column list mirrors `BoundaryRowMaterializer::COLUMNS`.
- `sql/load_tiger_boundaries.sql` — **AUTHORED, NOT RUN.** Appends the four TIGER kinds to
  `boundaries`, derives `boundaries_parts` via `ST_Subdivide(geom::geometry, 256)` → `ST_Dump` →
  `::geography` (E-24), and ends with a read-only `ST_IsValid` verification query. `ST_MakeValid` is
  documented as possible remediation but **never applied automatically**.

## Offline dry-run

```bash
php artisan corpus:import-boundaries --source=tiger_county
php artisan corpus:import-boundaries --source=tiger_place
php artisan corpus:import-boundaries --source=tiger_zcta
php artisan corpus:import-boundaries --source=tiger_school_district
# → storage/app/spatial/boundaries/<source>/{boundaries.ndjson, staging.json, summary.json, rejects.json}
```

Refuses production; opens no `pgsql_spatial` connection; reads no `SPATIAL_*` secret; makes no network
call; downloads nothing.

## Fixture outcome (per layer)

Each raw fixture has 4 rows: **2 kept** (a Polygon wrapped to MultiPolygon + a native MultiPolygon),
**1 rejected_invalid_geometry** (unclosed ring), **1 rejected_invalid_field** (missing GEOID).

## Blocked / pending (not failures)

| Item | Why | Unblocks in |
|---|---|---|
| Running the stage/load recipes | No cluster / `boundaries` not created | Class-2 |
| Live `ST_Subdivide` execution + `ST_IsValid` / `ST_MakeValid` | No PostGIS offline; validity is evidence-gated | Class-2 |
| Real TIGER/Line + ZCTA download (2–5 GB) | No downloads in Class-1 | Class-2 |
| NCES SABS attendance zones | Separate dataset; stale (2015-16), advisory-only (R10) | later / NCES slice |
| FEMA NFHL (flood_zone / flood_coverage) | Separate source | C3c |
| Gate 2 (corpus coverage) | Metric undefined; needs loaded corpus | C3d / Class-2 |
