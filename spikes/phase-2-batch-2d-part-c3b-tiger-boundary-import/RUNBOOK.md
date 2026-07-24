# RUNBOOK — Florida TIGER county boundary load (Class-2)

**Status: AUTHORED, NOT RUN.** This is the operator procedure for loading the 67 Florida Census
TIGER/Line **county** boundaries into the live `pgsql_spatial` (Crunchy Bridge PostGIS) cluster. It
executes **only** the committed recipes in this spike. Nothing here runs in CI. It downloads the
source and writes to the live cluster — run it deliberately, with the guards below.

Scope: **county only, Florida only.** Writes to exactly three tables — `boundaries`,
`boundaries_parts`, and one `corpus_imports` row. No other layer, state, source, or table.

## Committed artifacts this runbook drives

| File | Role |
|---|---|
| `bin/tiger_county_shp_to_ndjson.sh` | Shapefile → FL-only, EPSG:4326 raw NDJSON (offline data-prep) |
| `sql/stage_boundaries.sql` | `\copy` the COPY payload into transient `boundaries_staging` |
| `sql/load_tiger_boundaries.sql` | Append to `boundaries`; derive `boundaries_parts` via `ST_Subdivide(…,256)` |
| `sql/ledger_insert.sql` | Insert one `corpus_imports` row (status `staging`) |
| `sql/ledger_activate.sql` | Flip that row to `active` |
| `sql/verify_boundaries.sql` | Read-only post-load proofs |
| `bin/load_florida_counties.sh` | Guarded orchestrator for steps 13–17 |

## Preconditions (step 1)

- `boundaries`, `boundaries_parts`, `corpus_imports` migrations recorded on the cluster.
- `ogr2ogr` (GDAL) and `jq` installed for the converter.
- `SPATIAL_DATABASE_URL` exported **in the environment only** (never on the command line, never
  committed, never echoed). `psql` receives it directly.
- Choose a vintage and pin `corpus_version=tiger-<vintage>` (e.g. `tiger-2024`). The **same** value
  is used at convert, dry-run, load, ledger, and verify — a mismatch orphans `boundaries_parts`.

## Procedure

2. **Official Census TIGER source URL** (county is a single national file):
   `https://www2.census.gov/geo/tiger/TIGER2024/COUNTY/tl_2024_us_county.zip`
   (pattern: `.../TIGER<vintage>/COUNTY/tl_<vintage>_us_county.zip`).

3. **Download**:
   `curl -fSL -o tl_2024_us_county.zip https://www2.census.gov/geo/tiger/TIGER2024/COUNTY/tl_2024_us_county.zip`

4. **SHA-256 provenance capture**:
   `sha256sum tl_2024_us_county.zip | tee tl_2024_us_county.zip.sha256`
   Record URL, retrieval date, vintage, and digest in `RESULTS.md`.

5. **Unzip**: `unzip -o tl_2024_us_county.zip` → `tl_2024_us_county.shp` (+ sidecars).

6. **Convert to FL-only NDJSON** (reproject + filter, offline):
   `bin/tiger_county_shp_to_ndjson.sh tl_2024_us_county.shp county_fl_raw.ndjson 12`

7. **Confirm 67 rows**: `wc -l county_fl_raw.ndjson` → **67**. Stop if not 67.

8. **Offline dry-run / author the payload**:
   ```
   php artisan corpus:import-boundaries \
     --source=tiger_county \
     --in=county_fl_raw.ndjson \
     --corpus-version=tiger-2024 \
     --out-dir=storage/app/spatial/boundaries/tiger_county_fl
   ```

9. **Verify acceptance passes** — the command output shows `kept : 67`, no `✗`, and
   `Acceptance FAILED` absent.

10. **Verify `boundaries_payload.txt` has 67 rows**:
    `wc -l storage/app/spatial/boundaries/tiger_county_fl/boundaries_payload.txt` → **67**.

11. **Verify payload corpus_version matches** the `-v corpus_version` you will pass:
    every payload line's 5th tab-column == `tiger-2024`.

12. **Snapshot all 10 live Spatial table counts** (baseline for the no-delta proof):
    `boundaries`, `boundaries_parts`, `places`, `addresses`, `place_categories`,
    `place_category_mappings`, `place_authority_links`, `listing_locations`, `isochrone_cache`,
    `corpus_imports`. Record them in `RESULTS.md`.

Steps 13–17 are run together by the guarded orchestrator (each `psql` uses `ON_ERROR_STOP=1`, stops
on first failure), or manually in this order:

```
bin/load_florida_counties.sh \
  --i-understand-live \
  --corpus-version=tiger-2024 \
  --payload=storage/app/spatial/boundaries/tiger_county_fl/boundaries_payload.txt \
  --row-count=67
```

13. **Stage boundaries** — `sql/stage_boundaries.sql` (run from the payload directory; `\copy` reads
    `boundaries_payload.txt` from the CWD).
14. **Load boundaries and boundaries_parts** — `psql -v corpus_version=tiger-2024 -f sql/load_tiger_boundaries.sql`.
15. **Insert ledger staging row** — `psql -v corpus_version=tiger-2024 -v row_count=67 -f sql/ledger_insert.sql`.
16. **Activate ledger row** — `psql -v corpus_version=tiger-2024 -f sql/ledger_activate.sql`.
17. **Run `sql/verify_boundaries.sql`** — `psql -v corpus_version=tiger-2024 -f sql/verify_boundaries.sql`;
    every `pass` column must be `t` and check `05`/`5b` must return zero invalid rows.

18. **Drop staging table** — `psql "$SPATIAL_DATABASE_URL" -c 'DROP TABLE IF EXISTS boundaries_staging;'`
    (transient; the orchestrator leaves this manual on purpose).

19. **Record results** — fill in `RESULTS.md` from `RESULTS_TEMPLATE.md` (source URL, retrieval date,
    SHA-256, vintage, converter row count, acceptance, boundaries / boundaries_parts counts, invalid
    geometry count, max part vertices, ledger row, no-non-FL proof, sibling deltas, sign-off).

20. **Scoped rollback** (this import only — keyed on `corpus_version`, touches nothing else):
    ```sql
    DELETE FROM boundaries_parts
     WHERE boundary_id IN (SELECT id FROM boundaries WHERE corpus_version = 'tiger-2024');
    DELETE FROM boundaries        WHERE corpus_version = 'tiger-2024';
    DELETE FROM corpus_imports    WHERE dataset = 'census-tiger-county-fl'
                                    AND corpus_version = 'tiger-2024';
    DROP TABLE IF EXISTS boundaries_staging;
    ```

## Secret hygiene

`SPATIAL_DATABASE_URL` stays in the environment. Never pass it as a literal argument, never `echo` it,
never write it to `RESULTS.md`, artifacts, or the ledger `notes`. Prefer `.pgpass`/`PGPASSWORD` if the
URL embeds a password, and clear shell history if it ever appears there.
