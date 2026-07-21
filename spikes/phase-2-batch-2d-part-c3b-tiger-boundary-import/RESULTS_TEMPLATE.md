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
