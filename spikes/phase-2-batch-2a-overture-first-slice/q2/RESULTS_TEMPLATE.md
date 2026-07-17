# Q2 Measurement Results — Overture First Slice (Batch 2A)

> Copy this file to `RESULTS.md` and fill it in when a workstation with DuckDB +
> network is available (later Class-2 phase). **In Batch 2A every live number is
> PENDING** — DuckDB is not installed and no Overture data is downloaded.

| Field | Value |
|---|---|
| Overture release (pinned) | `2026-06-17.0` |
| Confidence floor | `>= 0.90` |
| Primary categories | grocery_store, restaurant, pharmacy, shopping_center, coffee_shop, gym, fitness_center, gas_station |
| Storage proxy (total) | ~450 bytes / row |
| Storage proxy (composite GiST) | ~94 bytes / row |
| Measured on | _PENDING_ |
| DuckDB version | _PENDING_ |

## Q2.1 — First-slice row counts (count-only)

| Scope | SQL | Rows (measured) | Total ≈ rows×450 | GiST ≈ rows×94 |
|---|---|---|---|---|
| Pinellas | `sql/q2/count_pinellas.sql` | _PENDING_ | _PENDING_ | _PENDING_ |
| Florida  | `sql/q2/count_florida.sql`  | _PENDING_ | _PENDING_ | _PENDING_ |
| CONUS    | `sql/q2/count_conus.sql`    | _PENDING_ | _PENDING_ | _PENDING_ |

> The CONUS row is the headline Q2 answer (Appendix E, Q2): no official CONUS
> first-slice figure is published — it must be measured.

## Q2.2 — Per-category counts (CONUS)

`sql/q2/count_per_category.sql`

| Primary category | Rows (measured) |
|---|---|
| grocery_store | _PENDING_ |
| restaurant | _PENDING_ |
| pharmacy | _PENDING_ |
| shopping_center | _PENDING_ |
| coffee_shop | _PENDING_ |
| gym | _PENDING_ |
| fitness_center | _PENDING_ |
| gas_station | _PENDING_ |

## Q2.3 — Confidence histogram (CONUS, pre-floor)

`sql/q2/confidence_histogram.sql`

| Bucket (low) | Rows | Kept by 0.90 floor? |
|---|---|---|
| _PENDING_ | _PENDING_ | _PENDING_ |

**Floor cost:** _PENDING_ — rows dropped by the `>= 0.90` cut vs total.

## Notes / anomalies

- _PENDING_
