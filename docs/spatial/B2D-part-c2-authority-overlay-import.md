# B2D Part C2 — Authority-overlay importers (offline authoring)

**Phase 2 · Batch 2D Part C2 · Spatial Intelligence Platform**
Branch: `phase-2-batch-2d-part-c2-authority-overlay-import`
Status: **cluster-free authoring complete. No PostGIS, no `SPATIAL_*` secrets, no migrations, no
downloads, no infrastructure.**

This batch authors the offline, deterministic **authority-overlay importer framework** plus its first
two reference importers (SSOT §8.2 deliverable #8 — "authority overlays"), following the 2A/2C/C1
offline-authoring pattern. It produces the canonical `AuthorityRecord` rows that Batch 2D Part C1's
linker (`corpus:link-authority`) consumes. Live staging + load are deferred to the Class-2 phase.

## What an overlay importer does (SSOT §8.2 / §9.1)

Each importer transforms a raw authority-source extract → canonical `AuthorityRecord` NDJSON. The
source's **`target`** decides the Class-2 fate of those records:

- **`target = 'link'` (overlay, e.g. CMS)** → matched to Overture places by the C1 linker →
  `place_authority_links` + the linked place's `authority_metric`. "Overture carries no CCN"; this is
  fuzzy spatial+name matching, never a join (SSOT §8.2).
- **`target = 'place'` (base source, e.g. USGS boat ramps)** → become `places` rows directly
  (`source='usgs'`, `category_key='boat_ramp'`); ranked by membership, `authority_metric` NULL.

Both modes emit the **same** DTO — the difference is Class-2 metadata, not an offline shape.

## Approved decisions

| # | Decision |
|---|---|
| **D1** | A thin `AuthorityOverlaySource` **interface** is the framework's one new abstraction — justified by the SSOT's "eleven mutually independent importers." Concretes stay `final`/bespoke; reuse is by the interface + the shared normalization/verdict shapes, matching the codebase's no-base-class convention. |
| **D2** | **No new place DTO / no change to `NormalizedPlaceRecord`.** Every importer emits the existing C1 `AuthorityRecord`; base-source `places` construction (incl. `authority_metric`) is deferred to the Class-2 `load_*` SQL. Keeps 2A/2C byte-stable. |
| **D3** | A CMS row whose star rating is absent / "Not Available" / non-numeric is **kept with `authority_metric = NULL`** — identity is authoritative even when CMS suppresses the rating. Only structurally-invalid rows (missing CCN/name/coordinates) are rejected. |
| **D4** | A numeric metric **outside its declared domain** (CMS stars ∉ [1,5]) is **rejected (out-of-domain), never clamped**. |
| **D5** | The CMS star file is **address-only**; sourcing lon/lat (geocode, or a coordinate-bearing CMS/POS extract) is a **Class-2** step. The offline adapter consumes rows that already carry coordinates. |
| **D6** | `authority_staging` is a **Class-2 staging table authored in the spike manifest**, not a Class-1 migration (the SSOT defines no such table). C2 adds **zero migrations**. |

## What's here (no migration — `places` / `place_authority_links` already exist, B1.2 04/05)

**Framework (`app/Services/Spatial/`):** `AuthorityOverlaySource` (interface, D1), `AuthorityOverlayNormalizationResult`
(exhaustive buckets), `AuthorityOverlayAcceptance` (7 invariants), `AuthorityStagingMaterializer`
(COLUMNS == manifest COPY order).
**Importers (`app/Services/Spatial/Overlay/`):** `CmsHospitalOverlaySource` (target=link, stars∈[1,5]),
`UsgsBoatRampOverlaySource` (target=place, membership).
**Config:** `config/spatial_authority_overlay.php` (source registry; metric domains transcribed from SSOT §7.2).
**Command:** `corpus:import-authority-overlay --source=<key>` — offline dry-run; refuses production; no DB/network.
**Spike:** `spikes/phase-2-batch-2d-part-c2-authority-overlay-import/` (`sql/stage_authority_overlay.sql`,
`sql/load_usgs_boat_ramps.sql` — AUTHORED-NOT-RUN — README, RESULTS_TEMPLATE).
**Fixtures (synthetic):** `tests/fixtures/spatial/authority_overlay/{cms,usgs}/*.ndjson`.

## Command

```bash
php artisan corpus:import-authority-overlay --source=cms
php artisan corpus:import-authority-overlay --source=usgs-boat-ramp
# → storage/app/spatial/authority/overlay/<source>/{overlay.ndjson, staging.json, summary.json, rejects.json}
```

On the shipped fixtures: **CMS 2 kept** (1 metric, 1 null; 1 invalid + 1 out-of-domain rejected),
**USGS 2 kept** (1 invalid rejected). Each `overlay.ndjson` byte-matches its `expected_overlay.ndjson`.

## Acceptance invariants

`non_empty`, `source_uniform`, `ref_present`, `ref_unique`, `name_present`, `coordinates_valid`,
`metric_in_domain` (+ optional `row_count_reconciles`).

## Class-2 handoff

Download real CMS DKAN / USGS CC0 data → source coordinates for CMS (D5) → run the offline importer →
`stage_authority_overlay.sql` → for `target=link` run C1's `link_authority.sql`; for `target=place`
run `load_usgs_boat_ramps.sql` → validate against `AuthorityOverlayAcceptance`.

## Deferred (not failures)

Real downloads + coordinate sourcing (Class-2) · `authority_staging` as a real table (D6) · NCES · FAA
NASR · GTFS/NTD (per-feed licensing) · PAD-US (polygon → C3-adjacent) · EPA Walkability · NOAA CUSP ·
category-compatibility gate (C1 D4) · boundaries + Gate 2 (C3).
