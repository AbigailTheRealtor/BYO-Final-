# RUNBOOK — Florida Overture places load (Class-2)

**Status: AUTHORED, NOT RUN.** Operator procedure for loading the Florida Overture
`places` corpus into the partitioned `places` table on the live `pgsql_spatial`
(Crunchy Bridge PostGIS) cluster, so the seven Gate-2 categories become present.
Nothing here runs in CI. It downloads Overture and writes the live cluster — run
it deliberately, with the guards below.

Scope: **Florida only, Overture `theme=places` only.** Writes to `places` (+ its
`overture-2026-06-17.0-fl` partition), `place_categories`, `place_category_mappings`,
and one `corpus_imports` row. It does **not** touch `place_authority_links`
(authority overlays are a separate, deferred dataset) or any boundary table.

Pins (SSOT `config/overture_places.php`): release `2026-06-17.0`, confidence floor
`>= 0.90`, FL bbox `regions.florida`. corpus_version = **`overture-2026-06-17.0-fl`**;
partition = **`places_p_overture_2026_06_17_0_fl`**.

## Committed artifacts this runbook drives

| File | Role |
|---|---|
| `sql/extract_places.sql` (2A) | DuckDB GeoParquet → FL raw NDJSON (swap the bbox to `regions.florida`) |
| `corpus:extract-overture` / `corpus:import-overture` | offline normalize + import-plan authoring (emit `partition_load.sql`, `copy_payload.txt`) |
| `bin/load_florida_overture_places.sh` | guarded orchestrator (seed → create+COPY → acceptance → ledger → attach/activate → verify) |
| `sql/verify_overture_fl.sql` | read-only post-load proofs |

## Preconditions (step 1)

- `places`, `place_categories`, `place_category_mappings`, `corpus_imports` migrated on the cluster.
- The Florida TIGER county boundaries (`tiger-2024`) already loaded — the verify's FL attribution uses them.
- `duckdb` available (temporary is fine: `nix shell nixpkgs#duckdb`, or the 2B session-local `.tools/duckdb/`), plus network egress to the public `overturemaps-us-west-2` bucket (anonymous).
- `SPATIAL_DATABASE_URL` exported in the environment only — never on the command line, never echoed.

## Procedure

2. **Extract FL raw NDJSON** (DuckDB, live S3; `regions.florida` bbox, confidence≥0.90, 8 primary tokens):
   ```
   duckdb -c ".read spikes/phase-2-batch-2a-overture-first-slice/sql/extract_places.sql"
   # → florida raw NDJSON (~29,434 rows, per the 2B live measurement)
   ```
3. **Normalize** (offline, no DB): `php artisan corpus:extract-overture --region=florida --input=<raw.ndjson> --output=<florida_normalized_places.ndjson>`
4. **Author the import plan** (offline, no DB):
   ```
   php artisan corpus:import-overture \
     --region=florida \
     --input=<florida_normalized_places.ndjson> \
     --corpus-version=overture-2026-06-17.0-fl \
     --out-dir=<IMPORT_DIR>
   # writes IMPORT_DIR/{partition_load.sql, copy_payload.txt, ledger.json, activate.sql}
   ```
5. **Confirm** `wc -l <IMPORT_DIR>/copy_payload.txt` equals the normalized extract row count.
6. **Snapshot** the live `places` and `corpus_imports` counts (baseline for RESULTS + rollback).
7. **Run the guarded orchestrator** (steps 8–13 in one gated run):
   ```
   bash spikes/phase-2-batch-2c-overture-import-framework/bin/load_florida_overture_places.sh \
     --i-understand-live \
     --corpus-version=overture-2026-06-17.0-fl \
     --import-dir=<IMPORT_DIR>
   ```
   The orchestrator performs, each `psql` with `ON_ERROR_STOP=1`:
   8. **Seed taxonomy** — `SpatialFirstSliceCategorySeeder` (7 `place_categories`) then `SpatialOvertureCategoryMappingSeeder` (8 `place_category_mappings`).
   9. **Create staging + COPY** — runs the generated `partition_load.sql` (creates `places_p_…_fl` `LIKE places`, `\copy`s `copy_payload.txt`).
   10. **Acceptance** — read-only gate over the staged partition (empty / source / source_ref / confidence floor / coordinates / unregistered category); **aborts before attach** on any violation.
   11. **Ledger staging row** — one idempotent `corpus_imports` row (`dataset=overture-places`, status `staging`, `row_count`=staged count).
   12. **Attach + activate** — one transaction: `ADD CONSTRAINT …_ck CHECK` → `ATTACH PARTITION` → ledger `staging → active`.
   13. **Verify** — runs `sql/verify_overture_fl.sql`.
14. **Verify** results: every `pass` column `t`; checks `05`/`06`/`07`/`09`/`10` satisfied; record per-category counts.
15. **Record results** — fill `RESULTS.md` from `RESULTS_TEMPLATE.md`.
16. **Re-run Gate 2** — `php artisan spatial:gate2-measure-coverage --territory=FL --corpus-version=tiger-2024` → the seven FL `overture_places` cells move from `absent` toward `present`.

## Scoped rollback (this import only)

Keyed on the corpus_version partition — touches nothing else:
```sql
ALTER TABLE places DETACH PARTITION places_p_overture_2026_06_17_0_fl;
DROP TABLE IF EXISTS places_p_overture_2026_06_17_0_fl;
DELETE FROM corpus_imports
  WHERE dataset = 'overture-places' AND corpus_version = 'overture-2026-06-17.0-fl';
```
(The seeded `place_categories` / `place_category_mappings` are shared taxonomy — keep them.)

## Secret hygiene

`SPATIAL_DATABASE_URL` stays in the environment. Never pass it as a literal argument, never `echo` it,
never write it to `RESULTS.md` or the ledger `notes`. The orchestrator only ever hands it to `psql`.
