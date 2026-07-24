# Batch 2C — Overture Import RESULTS (template)

> Copy to `RESULTS.md` and fill in when the Class-2 live load runs. Batch 2C
> authored the framework only — **no cluster, no import, nothing executed**.

## Run context

| Field | Value |
|---|---|
| Date (UTC) | _pending_ |
| Operator | _pending_ |
| Spatial cluster | _pending (Class-2)_ |
| Overture release | `2026-06-17.0` |
| Region | _pending_ |
| corpus_version | _pending_ |
| Partition name | _pending_ |

## Acceptance gate (`acceptance_checks.sql` — every "must be 0" is 0)

| Check | Expected | Observed |
|---|---|---|
| staged_rows | > 0 | _pending_ |
| bad_source | 0 | _pending_ |
| missing_source_ref | 0 | _pending_ |
| unregistered_category | 0 | _pending_ |
| below_floor | 0 | _pending_ |
| bad_coords | 0 | _pending_ |

## Reconciliation

| Count | Value |
|---|---|
| Extract records (NDJSON) | _pending_ |
| COPY payload lines | _pending_ |
| Staged partition rows | _pending_ |
| Ledger `row_count` | _pending_ |

All four MUST be equal before `attach_activate.sql`.

## Storage (measured vs planning proxy)

| Metric | Proxy (×row_count) | Measured |
|---|---|---|
| Table total (~450 B/row) | _pending_ | _pending_ |
| Composite GiST (~94 B/row) | _pending_ | _pending_ |

## Ledger row (`corpus_imports`)

| Column | Value |
|---|---|
| dataset | _pending_ |
| status | staging → active |
| bytes | _pending_ |
| territory_coverage | _pending_ |
| started_at / finished_at | _pending_ |

## Findings / deviations

_pending_

---

## Florida Overture places load (C3d-d) — operator record

### Run context
- Date (UTC): `PENDING`
- Operator: `PENDING`
- Overture release: `2026-06-17.0`
- Region: `florida`
- corpus_version: `overture-2026-06-17.0-fl`
- Partition: `places_p_overture_2026_06_17_0_fl`
- DuckDB version (temporary): `PENDING`

### Extract → author
- FL raw NDJSON row count: `PENDING` (2B live measurement ≈ 29,434)
- Normalized extract rows: `PENDING`
- `copy_payload.txt` lines: `PENDING`
- All three equal before load: `PENDING`

### Taxonomy seed
- `place_categories` (7 Gate-2 keys): `PENDING`
- `place_category_mappings` (8 Overture mappings): `PENDING`

### Load / verify (`verify_overture_fl.sql`)
- Staged partition rows: `PENDING`
- Places loaded (corpus_version): `PENDING`
- Per-category counts (coffee_shop / gas_station / grocery_store / gym / pharmacy / restaurant / shopping_center): `PENDING`
- Unregistered category (check 05, expect 0): `PENDING`
- Below confidence floor (check 06, expect 0): `PENDING`
- SRID 4326 offending (check 07, expect 0): `PENDING`
- Places in FL counties (check 08, informational): `PENDING`
- Partition attached (check 09, expect 1): `PENDING`
- Active ledger row (check 10, expect 1): `PENDING`

### Gate 2 impact
- FL `overture_places` cells present after load: `PENDING` (was 0 present / 7 absent)

### Close-out
- Rollback status (if any): `PENDING`
- Operator sign-off (name / date): `PENDING`
