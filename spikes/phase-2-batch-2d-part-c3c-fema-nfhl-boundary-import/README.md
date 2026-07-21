# Phase 2 · Batch 2D Part C3c — FEMA NFHL boundary import (spike)

**Status: cluster-free authoring. Nothing here runs against a cluster.**

This spike holds the **Class-2 recipes** for staging and loading FEMA National Flood Hazard Layer
boundaries — flood hazard areas (`S_FLD_HAZ_AR`) and the effective FIRM panel footprint
(`S_FIRM_PAN`) — into the `boundaries` table (SSOT §7.2 / §10). The offline, deterministic
counterpart reuses the C3a boundary framework unchanged (`BoundaryGeometry`, `BoundaryRecord`,
`BoundarySource`, `BoundaryImportAcceptance`, `BoundaryRowMaterializer`) plus the C3c adapters in
`app/Services/Spatial/Boundary/` (`FemaNfhlBoundarySource` base + flood-zone / flood-coverage
subclasses), exercised by `corpus:import-boundaries` over the synthetic fixtures in
`tests/fixtures/spatial/boundaries/fema/`.

This slice does **not** touch the live `App\Services\LocationDna\FemaFloodZoneAdapter`. That adapter
keeps working exactly as it does today.

## Contract (owner decisions)

- external_ref is the **source-native key, never a composite**: `FLD_AR_ID` for `flood_zone`,
  `FIRM_PAN` for `flood_coverage`. `ref_present` and `ref_unique` both remain **enforced** (a
  duplicate is a hard fail — never merged, concatenated, or given an invented part id).
- **Real-data key validation is deferred to Class-2.** Whether real nationwide NFHL extracts populate
  those keys uniquely is a question only a real download can answer; offline authoring proves the
  contract on synthetic fixtures.
- Canonical **GeoJSON MultiPolygon** everywhere offline; a valid `Polygon` is wrapped deterministically.
- **No centroid**; **structural validation only** (topology / `ST_IsValid` / `ST_MakeValid` is Class-2).
- attrs carries `source = 'fema_nfhl'` and **no `acres`**. Values are **raw passthrough**: `sfha` keeps
  FEMA's `'T'`/`'F'` token, `eff_date` keeps its raw date string. Nothing is coerced or parsed.
- Both kinds are peers. Hazard areas without the coverage footprint cannot satisfy INV-5 (SSOT §10).

## Files

- `sql/stage_boundaries.sql` — **AUTHORED, NOT RUN.** Creates `boundaries_staging` (source-neutral)
  and stages the offline `boundaries.ndjson`. COPY column list mirrors `BoundaryRowMaterializer::COLUMNS`.
- `sql/load_fema_boundaries.sql` — **AUTHORED, NOT RUN.** Appends the two FEMA kinds to `boundaries`,
  derives `boundaries_parts` via `ST_Subdivide(geom::geometry, 256)` → `ST_Dump` → `::geography`
  (E-24), and ends with two read-only verification queries (`ST_IsValid`, per-kind co-presence
  counts). `ST_MakeValid` is documented as possible remediation but **never applied automatically**.

## Offline dry-run

```bash
php artisan corpus:import-boundaries --source=fema_flood_zone
php artisan corpus:import-boundaries --source=fema_flood_coverage
# → storage/app/spatial/boundaries/<source>/{boundaries.ndjson, staging.json, summary.json, rejects.json}
```

Refuses production; opens no `pgsql_spatial` connection; reads no `SPATIAL_*` secret; makes no network
call; downloads nothing.

## Fixture outcome (per layer)

Each raw fixture has 4 rows: **2 kept** (a Polygon wrapped to MultiPolygon + a native MultiPolygon),
**1 rejected_invalid_geometry** (unclosed ring), **1 rejected_invalid_field** (missing `FLD_AR_ID` /
`FIRM_PAN`, counted under a per-layer reason token).

## Blocked / pending (not failures)

| Item | Why | Unblocks in |
|---|---|---|
| Running the stage/load recipes | No cluster / `boundaries` not created | Class-2 |
| Live `ST_Subdivide` execution + `ST_IsValid` / `ST_MakeValid` | No PostGIS offline; validity is evidence-gated | Class-2 |
| Real NFHL download (10–40 GB, the largest single import) | No downloads in Class-1 | Class-2 |
| Real-data `FLD_AR_ID` / `FIRM_PAN` uniqueness + coverage checks | Needs a real national extract | Class-2 |
| Effective-vs-preliminary panel selection; LOMR handling | Data decision, needs real vintages | Class-2 |
| Flood resolution (zone / outside-SFHA / not-mapped / live fallback) | Downstream of the import | later slice |
| "Not an official FEMA flood determination" display label (SIA-D18, §22) | Presentation layer, launch-gate item | later slice |
| Gate 2 (corpus coverage) | Metric undefined; needs loaded corpus | C3d / Class-2 |
