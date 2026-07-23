# Batch 2D Part C3d-a → C3d-b — Gate 2 corpus coverage · RESULTS (template)

Fill in at **C3d-b (Class-2)**, after the corpus is loaded against a real cluster and
`coverage_matrix.sql` has been run. All values are PENDING until a cluster + corpus exist.

**This template records evidence only. It does not compute a coverage metric or a pass/fail — Gate 2
acceptance is a per-category PRODUCT-OWNER decision (SSOT). Do not invent a numerator, denominator,
percentage, or threshold to fill it in.**

## Environment
- corpus_version: `PENDING`
- Corpus snapshot date / vintages (Overture, TIGER, PAD-US, FEMA, CMS, NCES, USGS, FAA, GTFS, EPA, NOAA, DOT NAD): `PENDING`
- PostGIS version: `PENDING`
- `rural_CONUS` definition used (county/FIPS set — a product decision): `PENDING`

## Dataset × territory matrix (present_count per cell)
> One row per dataset/category. Record the raw count, or `unmeasured` if the cell was not queried.
> A measured zero is `0` (an honest gap), never blank.

| dataset | category | FL | PR | AK | rural_CONUS |
|---|---|---:|---:|---:|---:|
| overture_places | grocery_store | | | | |
| overture_places | restaurant | | | | |
| cms_health | hospital | | | | |
| nces_schools | public_school | | | | |
| epa_walkability | walkability_index | | | | |
| usgs_boat_ramps | boat_ramp | | | | |
| noaa_cusp | coastline | | | | |
| dot_nad | address_point | | | | |
| … (extend to the full Phase 2 dataset catalog) | | | | | |

## PR watch datasets (E-32 — verify explicitly)
| dataset | PR present_count | status (present / absent / unmeasured) | evidence / notes |
|---|---:|---|---|
| epa_walkability | | | |
| usgs_boat_ramps | | | |
| noaa_cusp | | | |
| dot_nad | | | |

## Honesty checks
- Every cell is `present`, `absent` (measured zero), or `unmeasured` — no blanks: `PENDING`
- No `unmeasured` cell was recorded as `0`: `PENDING`
- PR and AK reported separately (never folded into CONUS): `PENDING`
- `corpus_imports.territory_coverage` written from the real run: `PENDING`

## Product-owner acceptance (the actual Gate 2 gate — NOT decided here)
- Matrix presented to product owner: `PENDING`
- Per-category acceptance recorded (accept / reject / remediate), per category: `PENDING`
- Coverage numerator / denominator / threshold the owner chose to apply (if any): `PENDING`
- Gate 2 disposition: `PENDING`
