# B2D Part C3a — PAD-US boundary import authoring (offline)

**Phase 2 · Batch 2D Part C3a · Spatial Intelligence Platform**
Branch: `phase-2-batch-2d-part-c3a-padus-boundary-import`
Status: **cluster-free authoring complete. No PostGIS, no `SPATIAL_*` secrets, no migrations, no
downloads, no infrastructure.**

C3 (= "boundaries + Gate 2") is split; this is **C3a — the generic boundary-import framework plus the
PAD-US protected-area source**, authored offline following the 2A/2C/C1/C2 pattern. It produces the
canonical `BoundaryRecord` NDJSON that a Class-2 recipe loads into `boundaries` (B1.2 migration 06)
and subdivides into `boundaries_parts` (migration 07). Boundaries are the first **polygon** geometry
in the pipeline — everything prior (2A/2C/C1/C2) was Point-only.

## Approved decisions

| # | Decision |
|---|---|
| **D1** | Geometry is **canonical GeoJSON MultiPolygon** in the DTO, NDJSON, and fixtures (never WKT). WKT/EWKT is produced only by `BoundaryRowMaterializer` for the COPY payload. |
| **D2** | A structurally-valid GeoJSON **Polygon is wrapped deterministically into a one-member MultiPolygon**; a MultiPolygon passes through; any other type (or an invalid Polygon) is rejected — never wrapped. |
| **D3** | **No centroid.** `boundaries` / `boundaries_parts` require none; any future centroid is authored in Class-2 SQL (PostGIS), never synthesized offline. |
| **D4** | PAD-US **acreage lives only in `attrs.acres`** — no place row, no `places.authority_metric`, no authority-link or ranking change, no duplication. Absent/non-numeric acreage → `acres = null` (identity/geometry still authoritative). |
| **D5** | Offline validation is **structural only** (type, closure, ≥4 positions/ring, finite in-range coordinates). Topological validity is Class-2: the load recipe runs a read-only **`ST_IsValid`** verification; **`ST_MakeValid` is documented but never applied automatically** — remediation needs a later Class-2 operational decision with evidence, and geometry is never silently altered. |
| **D6** | Duplicate `external_ref` is a **hard-fail** acceptance invariant. PAD-US multi-row/unit aggregation is **deferred** — duplicates are never merged, concatenated, arbitrarily picked, or given invented part ids (examined against the real schema in C3d / a later slice). |

## What's here (no migration — `boundaries` / `boundaries_parts` already exist, B1.2 06/07)

**Framework (`app/Services/Spatial/`):** `BoundaryGeometry` (D1/D2/D5 validation + normalization),
`BoundaryRecord` (polygon DTO), `BoundaryNormalizationResult` (exhaustive buckets), `BoundarySource`
(interface), `BoundaryImportAcceptance` (6 invariants), `BoundaryRowMaterializer` (GeoJSON→MultiPolygon
EWKT; COLUMNS == migration 06).
**Source (`app/Services/Spatial/Boundary/`):** `PadUsBoundarySource` (protected_area; acres → attrs).
**Config:** `config/spatial_boundaries.php` (source registry).
**Command:** `corpus:import-boundaries --source=padus` — offline dry-run; refuses production; no DB/network.
**Spike:** `spikes/phase-2-batch-2d-part-c3a-padus-boundary-import/` (`sql/stage_boundaries.sql`,
`sql/load_padus_boundaries.sql` — AUTHORED-NOT-RUN, with `ST_Subdivide` + `ST_IsValid` — README,
RESULTS_TEMPLATE).
**Fixtures (synthetic):** `tests/fixtures/spatial/boundaries/padus/{padus_raw,expected_boundaries}.ndjson`.

## Command

```bash
php artisan corpus:import-boundaries --source=padus
# → storage/app/spatial/boundaries/padus/{boundaries.ndjson, staging.json, summary.json, rejects.json}
```

On the shipped fixture: **3 kept** (PADUS-0001/0002/0004), **1 rejected_invalid_geometry** (unclosed
ring), **1 rejected_invalid_field** (missing unit id). `boundaries.ndjson` byte-matches
`expected_boundaries.ndjson`.

## Acceptance invariants

`non_empty`, `kind_valid`, `geometry_multipolygon`, `ref_present`, `ref_unique`, `acres_non_negative`
(+ optional `row_count_reconciles`).

## Class-2 handoff

Download real PAD-US 4.1 → run the offline importer → `stage_boundaries.sql` → `load_padus_boundaries.sql`
(append to `boundaries`, `ST_Subdivide` → `boundaries_parts`, `ST_IsValid` verification) → decide any
`ST_MakeValid` remediation with evidence → validate against `BoundaryImportAcceptance`.

## Deferred (not failures)

Real PAD-US download + live load/subdivide (Class-2) · `ST_IsValid`/`ST_MakeValid` execution (Class-2)
· PAD-US multi-unit aggregation (D6) · Census TIGER (C3b) · FEMA NFHL (C3c) · Gate 2 (C3d) ·
centroid (if ever required — Class-2).
