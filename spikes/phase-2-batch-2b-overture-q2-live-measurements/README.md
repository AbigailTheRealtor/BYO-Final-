# Phase 2 · Batch 2B — Overture Q2 LIVE measurements

Branch: `phase-2-batch-2b-overture-q2-live-measurements`
Status: **complete.** Ran the Batch 2A Q2 recipes against the live Overture
GeoParquet on S3 and filled `RESULTS.md`.

## Guardrails honored (this batch touched none of these)

- **No PostGIS** provisioned; **no `SPATIAL_*`** configured (spatial DB connection
  stays inert/fail-closed).
- **No guarded seeders** run; **no Gate 1A / 1B / Class-2 import** begun.
- DuckDB CLI v1.5.4 installed **session-locally** under a gitignored
  `.tools/duckdb/` (official GitHub release; sha256 verified before extract).
  Not committed. No project deps / composer / Nix / prod config changed.
- Overture bucket read **anonymously** (public, us-west-2); no credentials added.
- Raw NDJSON extract kept **out of git** (scratchpad only).

## What's here

```
RESULTS.md                              # filled measurements, sizing, schema verdict, findings
sql/smoke_extract_pinellas.sql          # LIVE Pinellas smoke extract (2A recipe + geometry fix)
sql/diagnostic_per_category_prefloor.sql# LIVE diagnostic that resolved the fitness_center anomaly
```

The Q2 count/histogram measurements were run **unchanged** from the committed 2A
SQL (`spikes/phase-2-batch-2a-overture-first-slice/sql/q2/*.sql`) — they use only
`bbox` + `categories.primary` + `confidence`, never geometry, so no adaptation was
needed. Sizing came from `App\Services\Spatial\CorpusSizingProjector` (450/94 B/row).

## Reproduce (needs DuckDB + network)

```bash
DB=.tools/duckdb/duckdb   # or any duckdb v1.5.x
$DB -c ".read spikes/phase-2-batch-2a-overture-first-slice/sql/q2/count_conus.sql"
$DB -c ".read spikes/phase-2-batch-2b-overture-q2-live-measurements/sql/smoke_extract_pinellas.sql"
```

## Headline

CONUS first-slice (confidence ≥ 0.90, 7 populated primary categories) =
**479,042 rows** → ~205.6 MiB table + ~42.9 MiB composite GiST. Live schema is
**compatible** with Batch 2A; `fitness_center` is empty in `2026-06-17.0`. See
`RESULTS.md` for the full breakdown.
