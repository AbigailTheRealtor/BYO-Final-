# B2D Part C3c — FEMA NFHL boundary import authoring (offline)

**Phase 2 · Batch 2D Part C3c · Spatial Intelligence Platform**
Branch: `phase-2-batch-2d-part-c3c-fema-nfhl-boundary-import`
Status: **cluster-free authoring complete. No PostGIS, no `SPATIAL_*` secrets, no migrations, no
downloads, no infrastructure.**

C3 (= "boundaries + Gate 2") is split; C3a shipped PAD-US protected areas, C3b shipped the four
Census TIGER layers, and **this is C3c — the two FEMA National Flood Hazard Layer boundary layers**,
authored offline. It **reuses the C3a boundary framework unchanged** and produces canonical
`BoundaryRecord` NDJSON that a Class-2 recipe loads into `boundaries` (migration 06) and subdivides
into `boundaries_parts` (migration 07).

Per SSOT §10 / E-11, NFHL is **imported**, reversing the earlier "live API only" posture. The live
`FemaFloodZoneAdapter` is **untouched** by this slice and keeps working exactly as it does today.

## Approved decisions

| # | Decision |
|---|---|
| **D-C3c-1** | A shared abstract `FemaNfhlBoundarySource` base + two thin `final` subclasses (flood-zone / flood-coverage). The normalization loop, geometry handling, and reject accounting live once in the base; each subclass fixes `sourceKey`, `kind`, its external_ref keys, its missing-ref reason token, and `attrs`. Mirrors D-C3b-1. |
| **D-C3c-2** | external_ref is the **source-native key, never a composite**: `flood_zone` = **`FLD_AR_ID`**, `flood_coverage` = **`FIRM_PAN`**. No invented composite identifiers (no DFIRM_ID+PANEL+SUFFIX glue, no synthetic part ids). |
| **D-C3c-3** | The missing-ref reject reason is **per layer** (`invalid_missing_fld_ar_id` / `invalid_missing_firm_pan`), because the two layers key on genuinely different fields and a shared token would erase which key was missing. |
| **D-C3c-4** | **Real-data key validation is deferred to Class-2.** Whether real nationwide extracts populate `FLD_AR_ID` / `FIRM_PAN` uniquely is a question only a real download answers; Class-1 proves the contract on synthetic fixtures. If Class-2 finds collisions, the evidence comes back for an owner decision — it does **not** license an invented composite key. |
| (retained) | **`ref_present` and `ref_unique` remain ENFORCED.** A duplicate external_ref is a hard fail — never merged, concatenated, arbitrarily picked, or given an invented id. |
| (inherited) | Canonical GeoJSON MultiPolygon (Polygon wrapped deterministically); **no centroid**; **structural validation only** (ST_IsValid/ST_MakeValid = Class-2). |

## Data contract

- Boundary kinds: `flood_zone` (← `S_FLD_HAZ_AR`), `flood_coverage` (← `S_FIRM_PAN`, the effective
  FIRM panel footprint).
- external_ref: `FLD_AR_ID` / `FIRM_PAN`, read straight from the extract.
- Geometry: canonical GeoJSON MultiPolygon.
- attrs: `source = 'fema_nfhl'`, no `acres`; flood_zone `{flood_zone, zone_subtype, sfha, dfirm_id}`
  (SSOT §10's `FLD_ZONE` / `ZONE_SUBTY` / `SFHA_TF` plus provenance), flood_coverage
  `{dfirm_id, panel, suffix, panel_type, eff_date}`.
- **Raw passthrough, no coercion.** `sfha` keeps FEMA's `'T'`/`'F'` token; `eff_date` keeps its raw
  date string. Zone **D** (undetermined) and zone **A** without a BFE both need their own downstream
  treatment, and a premature boolean would flatten them.
- **No place rows, no `places.authority_metric`, no authority-link or ranking change, no migration.**

## Why both layers ship together

SSOT §10 / E-11 / INV-5: a hazard polygon alone cannot distinguish *"outside the SFHA"* from *"FEMA
never mapped here"* from *"the lookup failed"*. Only the imported coverage footprint makes
`not mapped` renderable as something other than "no flood risk". So `flood_coverage` is a peer of
`flood_zone`, not an optional extra — importing hazard areas alone would be the exact failure mode
the invariant exists to prevent. The load recipe therefore targets both kinds and ends with a
per-kind co-presence count.

## What's here (framework reused unchanged — B1.2 06/07 exist)

**Adapters (`app/Services/Spatial/Boundary/`):** `FemaNfhlBoundarySource` (base) +
`FemaFloodZoneBoundarySource`, `FemaFloodCoverageBoundarySource`.
**Config:** `config/spatial_boundaries.php` — two new FEMA source rows.
**Command:** `corpus:import-boundaries --source=fema_*` (source-agnostic since D-C3b-2 — **no command
change was needed**).
**Spike:** `spikes/phase-2-batch-2d-part-c3c-fema-nfhl-boundary-import/` (`sql/stage_boundaries.sql`,
`sql/load_fema_boundaries.sql` — AUTHORED-NOT-RUN, `ST_Subdivide` + `ST_IsValid` — README,
RESULTS_TEMPLATE).
**Fixtures (synthetic):** `tests/fixtures/spatial/boundaries/fema/{flood_zone,flood_coverage}_{raw,expected}.ndjson`.

**Reused unchanged from C3a:** `BoundaryGeometry`, `BoundaryRecord`, `BoundaryNormalizationResult`,
`BoundarySource`, `BoundaryImportAcceptance` (its `acres_non_negative` check is a benign no-op for
FEMA), `BoundaryRowMaterializer`.

## Command

```bash
php artisan corpus:import-boundaries --source=fema_flood_zone
php artisan corpus:import-boundaries --source=fema_flood_coverage
```

Each shipped fixture: **2 kept**, **1 rejected_invalid_geometry** (unclosed ring), **1
rejected_invalid_field** (missing `FLD_AR_ID` / `FIRM_PAN`). Each `boundaries.ndjson` byte-matches its
`*_expected.ndjson`.

## Class-2 handoff

Download the real NFHL extract (10–40 GB — the largest single import in the programme) → run the
offline importer per layer → `stage_boundaries.sql` → `load_fema_boundaries.sql` (append `boundaries`,
`ST_Subdivide` → `boundaries_parts`, `ST_IsValid` verification, per-kind co-presence counts) → decide
any `ST_MakeValid` remediation with evidence → **complete the D-C3c-4 real-data key validation** in
`RESULTS_TEMPLATE.md` → validate via `BoundaryImportAcceptance`.

## Deferred (not failures)

Live NFHL download + load/subdivide (Class-2) · `ST_IsValid`/`ST_MakeValid` execution · real-data
`FLD_AR_ID`/`FIRM_PAN` presence + uniqueness (D-C3c-4) · effective-vs-preliminary panel selection and
LOMR handling · downstream flood resolution (zone / outside-SFHA / not-mapped / live fallback) ·
the *"Informational only. Not an official FEMA flood determination"* display label (SIA-D18, §22
launch gate) · Gate 2 (C3d).
