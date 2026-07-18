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
