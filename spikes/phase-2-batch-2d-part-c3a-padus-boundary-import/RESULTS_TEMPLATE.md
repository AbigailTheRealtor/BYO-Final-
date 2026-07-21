# Batch 2D Part C3a — PAD-US boundary import · RESULTS (template)

Fill in at **Class-2**, after the boundaries are staged, loaded, and subdivided against a real
cluster. All values are PENDING until a cluster exists.

## Environment
- corpus_version: `PENDING`
- PAD-US release: `PENDING`
- PostGIS version: `PENDING`

## Import outcome
| source | kind | raw rows | kept | rejected_invalid_geometry | rejected_invalid_field | staged |
|---|---|---:|---:|---:|---:|---:|
| padus | protected_area | | | | | |

## Load / subdivide outcome
| metric | value |
|---|---|
| boundaries rows inserted | `PENDING` |
| boundaries_parts rows (post ST_Subdivide) | `PENDING` |
| max vertices per part (≤256) | `PENDING` |

## Geometry validity (verification only)
- ST_IsValid failures: `PENDING`
- ST_MakeValid remediation applied? (evidence-gated Class-2 decision): `PENDING`

## Acceptance (BoundaryImportAcceptance parity)
- geometry_multipolygon: `PENDING`
- rings closed / coordinates valid: `PENDING`
- ref_unique: `PENDING`
- acres_non_negative: `PENDING`

## Notes
- attrs.acres coverage (present vs null): `PENDING`
