# Stage 0b — Provider Comparison Scorecard (fill during runs)

One row per provider, filled from `results/<provider>/tier<N>/`. Verify version
cells live from the run evidence (`70_measurements.sql`), never from memory.

| Provider | PG16 | PostGIS ver | btree_gist | pg_trgm | Direct session conn | Composite T1 | Composite T2 | KNN exact | Warm p95 (ms) | Composite idx bytes/row | Proj. idx @150GB | PITR | Region fit | Temp-test $ | Verdict |
|----------|:----:|-------------|:----------:|:-------:|:-------------------:|:------------:|:------------:|:---------:|:-------------:|------------------------:|-----------------:|:----:|-----------|------------:|---------|
| crunchy       |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |
| digitalocean  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |
| neon          |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |
| rds (ref)     |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |

## Verdict key
- **PASS (composite)** — composite index passes at both tiers; procurement-eligible.
- **CAVEATED (partial-C only)** — composite fails, partial-index fallback passes; eligible with documented N-index burden.
- **REJECTED** — any extension / KNN / plan-at-scale failure, or no direct session connection.

## Procurement-eligibility gate (all must hold)
- PG 16 + PostGIS ≥ 3.5 live; `postgis`, `btree_gist`, `pg_trgm` all `CREATE EXTENSION`-able.
- True direct session connection (not transaction-mode pooler).
- Composite PASS at Tier 1 **and** Tier 2 (or partial-C pass, flagged).
- KNN == brute-force (exact order + set) for all four sparse categories.
- ~150 GB + refresh headroom with PITR; region co-locatable with app + Valhalla.
- Projected steady-state cost within budget.
