# Stage 0b Provider Result — <PROVIDER>

> Copy to `results/<provider>/RESULT.md` and fill from the captured `.out` files.
> Record only non-secret evidence (versions, plans, sizes, timings). Never paste
> a host password, connection secret, or `.pgpass` line.

## Environment (from `70_measurements.sql`)
- Provider / region:
- PostgreSQL version:
- PostGIS version (`postgis_full_version()`):
- Extensions installed: postgis __ · btree_gist __ · pg_trgm __
- Connection type: **direct session** (confirm NOT a transaction-mode pooler)

## Tier 1 (176,560 rows) — parity
- Distribution matches committed Stage 0a? (Y/N)
- Composite (A): Index Scan `places_cat_geom` · category Index Cond · in-scan `<->` · 0 rows removed · no sort · no seqscan → PASS/FAIL
- Partitioned @v2: pruned to v2 partition local composite index → PASS/FAIL
- Geography-only control (B): rows removed by filter (boat_ramp / airport / marina / urgent_care):
- Partial fallback (C): Index Scan `places_geom_<cat>` · 0 rows removed → PASS/FAIL
- KNN correctness: exact_order_match / same_set (all four) → PASS/FAIL

## Tier 2 (~5,000,200 rows) — scale / plan stability
- Composite still chosen (no seq-scan/sort crossover)? → PASS/FAIL
- Warm p50 / p95 (ms) composite sparse KNN:
- Cold (post-`DISCARD ALL`; Neon: post-resume) p50 (ms):
- Buffers (shared hit / read) per sparse category:
- Composite index bytes/row · heap bytes/row:
- Projected composite index size @ 150 GB heap:

## Cost & operability
- Temp-test spend (this run):
- Autosuspend / cold-start behavior observed:
- Backup / PITR window:

## Verdict
- [ ] PASS (composite)  [ ] CAVEATED (partial-C only)  [ ] REJECTED
- Notes / anomalies:

## Cleanup confirmation
- [ ] spike objects dropped  [ ] instance/cluster/project destroyed
- [ ] storage/snapshots deleted  [ ] firewall entries removed
- [ ] `.pgpass` / `.env.local` shredded  [ ] $0 residual resources confirmed
