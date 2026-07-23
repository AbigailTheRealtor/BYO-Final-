# Batch 2D Part C3c — FEMA NFHL boundary import · RESULTS (template)

Fill in at **Class-2**, after the boundaries are staged, loaded, and subdivided against a real
cluster. All values are PENDING until a cluster exists.

## Environment
- corpus_version: `PENDING`
- NFHL vintage / snapshot date: `PENDING`
- Extract scope (national / state / county list): `PENDING`
- PostGIS version: `PENDING`

## Import outcome (per layer)
| source | kind | raw rows | kept | rejected_invalid_geometry | rejected_invalid_field | staged |
|---|---|---:|---:|---:|---:|---:|
| fema_flood_zone | flood_zone | | | | | |
| fema_flood_coverage | flood_coverage | | | | | |

## Load / subdivide outcome
| kind | boundaries rows | boundaries_parts rows (post ST_Subdivide) | max vertices/part (≤256) |
|---|---:|---:|---:|
| flood_zone | | | |
| flood_coverage | | | |

## Geometry validity (verification only)
- ST_IsValid failures (per kind): `PENDING`
- ST_MakeValid remediation applied? (evidence-gated Class-2 decision): `PENDING`

## Acceptance (BoundaryImportAcceptance parity)
- geometry_multipolygon: `PENDING`
- ref_present: `PENDING`
- ref_unique (`FLD_AR_ID` / `FIRM_PAN`): `PENDING`

## Real-data key validation (deferred here from Class-1)
- `FLD_AR_ID` present on every `S_FLD_HAZ_AR` row?: `PENDING`
- `FLD_AR_ID` unique across the national extract (not just within a DFIRM)?: `PENDING`
- `FIRM_PAN` present on every `S_FIRM_PAN` row?: `PENDING`
- `FIRM_PAN` unique across the national extract?: `PENDING`
- If NOT unique: the collision shape, with counts and examples (**do not invent a composite key —
  bring the evidence back for an owner decision**): `PENDING`

## Coverage co-presence (INV-5 precondition)
- Both kinds loaded at the same corpus_version?: `PENDING`
- flood_coverage rows / flood_zone rows: `PENDING`
- Counties in scope with hazard areas but NO coverage footprint: `PENDING`

## Sizing (SSOT §10 predicted 10–40 GB)
- Download size: `PENDING`
- Loaded table size (boundaries + boundaries_parts, per kind): `PENDING`
- Load wall-clock: `PENDING`

## Notes
- Effective vs preliminary panel selection, and LOMR handling: `PENDING`
