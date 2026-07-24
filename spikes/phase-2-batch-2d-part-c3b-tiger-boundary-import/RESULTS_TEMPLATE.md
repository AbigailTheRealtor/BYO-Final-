# Batch 2D Part C3b — Census TIGER boundary import · RESULTS (template)

Fill in at **Class-2**, after the boundaries are staged, loaded, and subdivided against a real
cluster. All values are PENDING until a cluster exists.

## Environment
- corpus_version: `PENDING`
- TIGER/Line vintage: `PENDING` (e.g. 2024); ZCTA vintage: `PENDING` (e.g. 2020)
- PostGIS version: `PENDING`

## Import outcome (per layer)
| source | kind | raw rows | kept | rejected_invalid_geometry | rejected_invalid_field | staged |
|---|---|---:|---:|---:|---:|---:|
| tiger_county | county | | | | | |
| tiger_place | place | | | | | |
| tiger_zcta | zcta | | | | | |
| tiger_school_district | school_district | | | | | |

## Load / subdivide outcome
| kind | boundaries rows | boundaries_parts rows (post ST_Subdivide) | max vertices/part (≤256) |
|---|---:|---:|---:|
| county | | | |
| place | | | |
| zcta | | | |
| school_district | | | |

## Geometry validity (verification only)
- ST_IsValid failures (per kind): `PENDING`
- ST_MakeValid remediation applied? (evidence-gated Class-2 decision): `PENDING`

## Acceptance (BoundaryImportAcceptance parity)
- geometry_multipolygon: `PENDING`
- ref_unique (GEOID): `PENDING`
- rings closed / coordinates valid: `PENDING`

## Notes
- GEOID zero-padding verified (county 5, place 7, ZCTA 5, district STATEFP+UNSDLEA): `PENDING`

---

## Florida county load (C3d-c) — operator record

### Provenance
- Source URL: `PENDING` (e.g. https://www2.census.gov/geo/tiger/TIGER2024/COUNTY/tl_2024_us_county.zip)
- Retrieval date: `PENDING`
- SHA-256 (`tl_<vintage>_us_county.zip`): `PENDING`
- TIGER/Line vintage: `PENDING` (e.g. 2024)
- corpus_version: `PENDING` (e.g. tiger-2024)

### Convert + author
- Converter row count (`county_fl_raw.ndjson`): `PENDING` (expected 67)
- Acceptance result (`kept` / rejects): `PENDING` (expected kept 67, 0 rejects)
- `boundaries_payload.txt` row count: `PENDING` (expected 67)
- Payload corpus_version matches load `-v`: `PENDING`

### Load outcome
- `boundaries` rows (county, this corpus_version): `PENDING` (expected 67)
- `boundaries_parts` rows: `PENDING` (expected ≥ 67)
- Invalid geometry count (`ST_IsValid`): `PENDING` (expected 0)
- Max part vertex count (`ST_NPoints`): `PENDING` (expected ≤ 256)

### Proofs
- No non-FL rows (`external_ref ~ ^12[0-9]{3}$`, `state_fips = 12`): `PENDING` (expected 0 offending)
- Ledger row (`census-tiger-county-fl`, status `active`): `PENDING` (expected exactly 1)
- Sibling table deltas (places/addresses/place_*/listing_locations/isochrone_cache): `PENDING` (expected 0)

### Close-out
- Staging table dropped: `PENDING`
- Rollback status (if any): `PENDING`
- Operator sign-off (name / date): `PENDING`
