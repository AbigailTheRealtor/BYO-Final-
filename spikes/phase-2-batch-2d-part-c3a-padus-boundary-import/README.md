# Phase 2 · Batch 2D Part C3a — PAD-US boundary import (spike)

**Status: cluster-free authoring. Nothing here runs against a cluster.**

This spike holds the **Class-2 recipes** for staging and loading PAD-US protected-area boundaries
(SSOT §7.2 deliverable #7, boundaries). The offline, deterministic counterpart is authored in
`app/Services/Spatial/` (`BoundaryGeometry`, `BoundaryRecord`, `BoundarySource`,
`Boundary/PadUsBoundarySource`, `BoundaryImportAcceptance`, `BoundaryRowMaterializer`) and exercised
by `corpus:import-boundaries` over the synthetic fixtures in `tests/fixtures/spatial/boundaries/`.

## Geometry (owner decisions)

- Canonical **GeoJSON MultiPolygon** everywhere offline (DTO, NDJSON, fixtures). A structurally-valid
  GeoJSON `Polygon` is wrapped deterministically into a one-member MultiPolygon; anything else is
  rejected. Coordinate order is lon, lat; rings are closed; positions are finite and in-range.
- **No centroid** — `boundaries` / `boundaries_parts` do not require one; none is synthesized offline.
- PAD-US **acreage lives only in `attrs.acres`** — no place row, no `places.authority_metric`, no
  ranking or authority-link change.
- Offline validation is **structural only**. Topological validity (`ST_IsValid` / `ST_MakeValid`) is
  a Class-2 concern (below), never applied offline.

## Files

- `sql/stage_boundaries.sql` — **AUTHORED, NOT RUN.** Creates `boundaries_staging` and stages the
  offline `boundaries.ndjson`. COPY column list mirrors `BoundaryRowMaterializer::COLUMNS`.
- `sql/load_padus_boundaries.sql` — **AUTHORED, NOT RUN.** Appends to `boundaries`, then derives
  `boundaries_parts` via `ST_Subdivide(geom::geometry, 256)` → `ST_Dump` → `::geography` (E-24), and
  ends with a **read-only `ST_IsValid` verification query**. `ST_MakeValid` is documented as a
  possible remediation but is **never applied automatically** — real remediation needs a later
  Class-2 operational decision with evidence.

## Offline dry-run

```bash
php artisan corpus:import-boundaries --source=padus
# → storage/app/spatial/boundaries/padus/{boundaries.ndjson, staging.json, summary.json, rejects.json}
```

Refuses production; opens no `pgsql_spatial` connection; reads no `SPATIAL_*` secret; makes no network
call; downloads nothing.

## Fixture outcome

PAD-US (5 raw rows): **3 kept** (`PADUS-0001` Polygon→MultiPolygon acres 1200.5, `PADUS-0002`
MultiPolygon acres 340.0, `PADUS-0004` acres null — "N/A"), **1 rejected_invalid_geometry** (unclosed
ring), **1 rejected_invalid_field** (missing unit id).

## Blocked / pending (not failures)

| Item | Why | Unblocks in |
|---|---|---|
| Running `stage_boundaries.sql` / `load_padus_boundaries.sql` | No cluster / `boundaries` not created | Class-2 |
| Live `ST_Subdivide` execution | No PostGIS offline | Class-2 |
| `ST_IsValid` verification + any `ST_MakeValid` remediation | Live geometry validity is Class-2, evidence-gated | Class-2 |
| Real PAD-US 4.1 download (3–8 GB) | No downloads in Class-1 | Class-2 |
| PAD-US multi-row/unit aggregation | Duplicate external_ref is a hard-fail here; real schema examined later | C3d / later slice |
| Census TIGER (county/place/ZCTA/school-district) | Separate source | C3b |
| FEMA NFHL (flood_zone / flood_coverage) | Separate source | C3c |
| Gate 2 (corpus coverage) | Metric undefined; needs loaded corpus on a cluster | C3d / Class-2 |
