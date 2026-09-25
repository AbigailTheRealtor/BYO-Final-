# Overture v2 load — full-size rehearsal record

**This file is a GATE, not a note.** The `migrate` and `import` stages of
`.github/workflows/overture-v2-operator-load.yml` read the block below **at the commit being run** and
refuse unless it says `PASSED` with every field satisfied. It changes only through a reviewed commit,
after the rehearsal in `RUNBOOK.md` §4 has actually been performed on a **throwaway** PostgreSQL 16 +
PostGIS **3.6.x** instance — never on the live spatial cluster, and never on a reduced dataset.

The gate also requires that nothing the load depends on changed between `rehearsed_commit` and the
commit being run (`app/`, `config/`, `database/migrations/spatial/`, `composer.lock`,
`scripts/overture-v2/`, and this directory's `bin/` and `sql/`). A rehearsal is evidence for the code
it ran, and for nothing else.

Required values when `PASSED`: `postgis_version` 3.6.x, `postgresql_version` 16.x,
`places_loaded` 52716, `memberships_loaded` 11082, `second_import` ALREADY_READY, `verify_v2_load`
VERIFY_OK, `sample_fidelity` OK, `v1_snapshot_unchanged` yes, `rollback_recovery_exercised` yes,
`rehearsed_commit` a full 40-hex SHA on `main`.

<!-- rehearsal-gate:start -->
rehearsal_status: PASSED
rehearsed_commit: 7c6ee26b26e9547759cd818a64c1865b0b2e834d
performed_on: 2026-09-25
postgresql_version: 16.14
postgis_version: 3.6.3
places_loaded: 52716
memberships_loaded: 11082
second_import: ALREADY_READY
verify_v2_load: VERIFY_OK
sample_fidelity: OK
v1_snapshot_unchanged: yes
rollback_recovery_exercised: yes
<!-- rehearsal-gate:end -->

## Evidence

Rehearsal of RUNBOOK §4, performed 2026-09-25 (UTC) on a **throwaway** scratch instance. The live
spatial cluster was never contacted and no live credential was present in any rehearsal container.
Reviewed by an independent read-only reviewer before this record was written (verdict: CLEAN).

### Where, and on what

- **Code:** `git archive 7c6ee26b26e9547759cd818a64c1865b0b2e834d` (origin/main at the time) into a
  disposable directory; after every run, all tracked files in the export were hash-identical to the
  commit. Chosen over the operator-code pin `2baecc8bf` because the `gate` job diffs `app/` among its
  paths and PR #207 changed `app/` in between.
- **Scratch server:** disposable Docker image built from
  `ubuntu:24.04@sha256:008173c23f95b170204355c12626cb5a965d779a7e1283b09e9cffbb1bf33ca3` plus PGDG's
  signed `apt-archive.postgresql.org` `noble-pgdg-archive` (key fingerprint
  `B97B0AFCAA1A47F044F244A07FCC7D46ACCC4CF8` verified in the build), pinned
  `postgresql-16=16.14-1.pgdg24.04+1`, `postgresql-16-postgis-3=3.6.3+dfsg-1.pgdg24.04+1`,
  `postgresql-16-postgis-3-scripts=3.6.3+dfsg-1.pgdg24.04+1`, `libpq5=18.6-1.pgdg24.04+2`. Fresh
  `initdb` per start, TLS only (`hostnossl … reject`), a scratch-only random password, on a Docker
  `--internal` network (no egress, no published port, no mount).
  `SELECT version()` → `PostgreSQL 16.14 (Ubuntu 16.14-1.pgdg24.04+1)`;
  `postgis_full_version()` → `POSTGIS="3.6.3 3d12666" [EXTENSION] PGSQL="160" GEOS="3.14.1-CAPI-1.20.5"
  PROJ="9.8.1"`. Connection `TLSv1.3`.
- **Operator client:** disposable image from
  `php:8.2-cli-bookworm@sha256:ac125c2d3ce1e8e33b503bcf85bdea9b43ed108515134629163a5971b3c9ac85`
  (PHP 8.2.34 with pdo_pgsql, pgsql, intl, bcmath, mbstring, xml, ctype, fileinfo, json, pdo_sqlite, plus
  gd, zip, pcntl, sockets, exif for `composer install`), `zend.exception_ignore_args=On`, Composer 2.10.3
  (`composer@sha256:9715c7f69044da2a212a5fbde29ee7da24e364d426560ae6367b060236f847d7`), psql 15.19,
  Python 3.11 + `duckdb==1.5.5`. `composer install --prefer-dist --no-progress --no-interaction`.
  Non-root, `--cap-drop ALL`, `no-new-privileges`, read-only root filesystem, tmpfs `/tmp`, no Docker
  socket, no home mount, no `.env`.
- **Environment:** exactly the import job's declared env — `APP_ENV=operator`, `DB_CONNECTION=sqlite`,
  `DB_DATABASE=:memory:`, `CACHE_DRIVER=array`, `SESSION_DRIVER=array`, `QUEUE_CONNECTION=sync`,
  `LOG_CHANNEL=stderr`, `PGSSLMODE=require`, an ephemeral `APP_KEY` — plus `PGCONNECT_TIMEOUT=15` and
  `SPATIAL_DATABASE_URL` naming the scratch server. Supplied only from an env file of explicit scratch
  values. Absent (checked by name inside the container): `DATABASE_URL`, `PGHOST`, `PGHOSTADDR`,
  `PGDATABASE`, `PGUSER`, `PGPORT`, `PGSERVICE`, `PGSERVICEFILE`, `PGOPTIONS`, `DB_HOST`,
  `REPLIT_DEPLOYMENT`, AWS and GitHub credentials.

### Exact commands (in the operator container, cwd = the export)

```
# §8 steps 1–3: real extraction
python spikes/phase-4-overture-v2-load/bin/run_duckdb_sql.py scripts/overture-v2/count_release_rows.sql   # → 73631092
python spikes/phase-4-overture-v2-load/bin/run_duckdb_sql.py scripts/overture-v2/extract_bbox_raw.sql     # → 1245925 lines
php artisan corpus:extract-overture-v2 --input=<dir>/overture_v2_raw_bbox.ndjson --output-dir=<dir>/out --release-row-count=73631092
sha256sum <dir>/out/base.ndjson <dir>/out/supplementary.ndjson   # compared with config/overture_v2_corpus.php

# §4.1 schema: the eleven core migrations, each named (never the directory)
php artisan migrate --no-interaction --database=pgsql_spatial \
  --path=database/migrations/spatial/2026_07_16_000001_spatial_core_enable_extensions.php … (all eleven 2026_07_16_0000NN files)
php artisan db:seed --force --database=pgsql_spatial --class='Database\Seeders\SpatialFirstSliceCategorySeeder'
php artisan db:seed --force --database=pgsql_spatial --class='Database\Seeders\SpatialOvertureCategoryMappingSeeder'
psql "$SPATIAL_DATABASE_URL" -X -q -v ON_ERROR_STOP=1 -f synthetic_v1.sql          # see "v1 fixture" below
psql "$SPATIAL_DATABASE_URL" -X -q -v ON_ERROR_STOP=1 -f sql/v1_snapshot.sql > v1_before.txt

# §7: exactly the three v2 migrations, one batch
php artisan migrate --pretend --no-interaction --database=pgsql_spatial \
  --path=database/migrations/spatial/2026_09_24_000001_spatial_overture_v2_create_corpora.php \
  --path=database/migrations/spatial/2026_09_24_000002_spatial_overture_v2_create_places.php \
  --path=database/migrations/spatial/2026_09_24_000003_spatial_overture_v2_create_chain_memberships.php
php artisan migrate --no-interaction --database=pgsql_spatial <the same three --path values>

# §8 steps 4–10
php artisan corpus:import-overture-v2 --corpus-version=overture-2026-08-19.0-fl-r2 --extract-dir=<dir>/out                                          # VALIDATED
php artisan corpus:import-overture-v2 --corpus-version=overture-2026-08-19.0-fl-r2 --extract-dir=<dir>/out --write --database=pgsql_spatial        # IMPORTED
psql "$SPATIAL_DATABASE_URL" -X -q -v ON_ERROR_STOP=1 -f sql/verify_v2_load.sql > verify_1.txt
php artisan corpus:import-overture-v2 … --write --database=pgsql_spatial                                                                            # ALREADY READY
psql "$SPATIAL_DATABASE_URL" -X -q -v ON_ERROR_STOP=1 -f sql/verify_v2_load.sql > verify_2.txt
diff <(grep '^LEDGER ' verify_1.txt) <(grep '^LEDGER ' verify_2.txt)
psql "$SPATIAL_DATABASE_URL" -X -q -v ON_ERROR_STOP=1 -f sql/sample_fidelity.sql > sample.jsonl
python3 bin/compare_sample.py --sample sample.jsonl --extract-dir <dir>/out
psql "$SPATIAL_DATABASE_URL" -X -q -v ON_ERROR_STOP=1 -f sql/v1_snapshot.sql > v1_after.txt; diff -u v1_before.txt v1_after.txt

# §4.7b rollback — exactly the three --path values of §7
php artisan migrate:rollback --no-interaction --database=pgsql_spatial \
  --path=database/migrations/spatial/2026_09_24_000001_spatial_overture_v2_create_corpora.php \
  --path=database/migrations/spatial/2026_09_24_000002_spatial_overture_v2_create_places.php \
  --path=database/migrations/spatial/2026_09_24_000003_spatial_overture_v2_create_chain_memberships.php
```

### Extraction (§4.2) — byte-identical to the contract

```
bbox input rows        : 1245925
BASE corpus rows       : 52566
supplementary rows     : 2962        (rescue_candidate 228, diagnostic 2734)
matcher-analysis rows  : 55528  (base + supplementary; not a corpus count)
rejected rows          : 1190397     (confidence_below_floor 489699, status_permanently_closed 12451,
                                      taxonomy_null_not_supplementary 17078,
                                      taxonomy_not_allowlisted_not_supplementary 671169, all others 0)
fully accounted        : yes
matcher memberships    : 11082 (ambiguous rows 0, co-branded 0)
rescue verdicts        : 150 admitted, 78 refused
base.ndjson          sha256 bb9e77c13790897155f847fa3a7ed48067930cf08257e227bee60bf96c91a168  = contract
supplementary.ndjson sha256 edeed1435d707912dedd08d642cf1088e669cf4dc329ea3248a289be3e91c8e4  = contract
```
Release rows 73,631,092. The extraction files were kept in scratch only and not committed.

### Schema and v1 fixture (§4.1)

- The eleven core migrations went in as batch 1. The v2 `--pretend` printed exactly three `CREATE TABLE`
  and three `CREATE INDEX` statements. The v2 migrate produced batch 2 holding only
  `2026_09_24_000001/2/3`. The two August address migrations were never applied.
- **v1 fixture: SYNTHETIC** (§4.1's second option). The v1 release is no longer in the public bucket.
  - The taxonomy came from the v1 pipeline's own two seeders (7 categories, 8 mappings).
  - A script mirroring `phase-2-batch-2c-overture-import-framework` then built the rest:
    - a staging table `LIKE places INCLUDING DEFAULTS INCLUDING CONSTRAINTS INCLUDING INDEXES`
      (`places_p_overture_2026_06_17_0_fl`);
    - 29,434 deterministic synthetic rows inside the FL bbox, in registered categories, confidence ≥ 0.90;
    - the v1 loader's acceptance gate;
    - a ledger `staging` row;
    - `CHECK` + `ATTACH PARTITION` + activation in one transaction.
  - Result: `overture-2026-06-17.0-fl` is the one `active` overture-places corpus, with 29,434 rows.
  - No production row or dump was used.
- `v1_before.txt`:
  ```
  v1_places_rows=29434
  v1_places_md5=4cb757e9c00058fe0f4b7000641a3f24
  places_partitions=places_p_overture_2026_06_17_0_fl
  corpus_imports_rows=1
  corpus_imports_md5=38c41864b53fd3d2f7635aa72a4a54e1
  place_categories_md5=cd4c9593da925e87ece2f39b9cd0501e
  place_category_mappings_md5=c736f9cfe28685ce67b09fb0ce7627ba
  place_authority_links_rows=0
  v1_partition_counters=29434/0/0/0
  active_overture_places=overture-2026-06-17.0-fl
  ```

### Import, reconciliation, idempotency, fidelity, v1 (§4.2–§4.6)

Dry run `VALIDATED`, then `IMPORTED — overture-2026-08-19.0-fl-r2 is ready (not active; nothing was activated).`

`verify_v2_load.sql` (first import):
```
PASS V01 ledger: one ready row with the contract pins, checksums and counts
PASS V02 exactly one v2 corpus row exists
PASS V03 places 52,716 (base 52,566 + rescued 150)
PASS V04 no diagnostic, matcher_only or refused row was stored
PASS V05 operating status 47,639 open / 5,077 unknown
PASS V06 base per-category counts match the plan (16 categories)
PASS V07 no duplicate source_ref, no invalid geometry, no out-of-bbox coordinate
PASS V08 memberships 11,082 (storefront 10,533 / fuel 239 / department 310 unconfirmed)
PASS V09 memberships per chain:format match the plan (31 pairs, 20 chains)
PASS V10 rescued memberships 150 CVS (147 store, 3 store_in_target)
PASS V11 no duplicate membership, no orphan, registry hash equals the ledger
PASS V12 v2 is NOT active; v1 remains the one active overture-places corpus
LEDGER ready f09cfe4ce5b98ce907640e097d55e634 2026-09-25T01:12:01.528100Z 2026-09-25T01:12:01.542741Z
VERIFY OK
```

**Idempotency (second import):**
- It printed `ALREADY READY — overture-2026-08-19.0-fl-r2 was imported from these exact files before;
  nothing written.`
- The second verify was `VERIFY OK`. Exactly one `LEDGER ready` line, identical in both verify outputs.
- A third identical import was also `ALREADY READY`. Across it, the v2 content hashes (md5 over every
  row of all three tables) and the `pg_stat_user_tables` write counters were identical before and after.
- Membership totals by role: storefront 10,533, fuel 239, department 310, with 7 `store_in_target`
  memberships among them.

`compare_sample.py`:
```
PASS category:coffee_shop 001043e7-0018-4bc2-8840-f440fd837b31
PASS category:convenience_store 0000389f-004b-4d19-a3f9-d8d17629904f
PASS category:department_store 0031c248-7144-4375-9ecd-bd32b41b340b
PASS category:fast_food_restaurant 00008e3e-d33c-4f2f-bd44-179076723077
PASS category:gas_station 0004ad1d-4272-43ba-b620-280d0e4b9a0f
PASS category:grocery_store 00106452-d3d9-48cd-a67f-490feafe8a00
PASS category:pharmacy 0028157d-637b-4a76-be46-d8a1917e4c0d
PASS category:superstore 0282dd4d-8605-4bea-a41d-cd2bbf99a66d
PASS membership:fuel 00ac1df5-08a4-44a7-8826-6c54e363f406
PASS membership:store_in_target 27fc5886-24b8-4e55-ace7-13af811948ac
PASS membership:storefront_unconfirmed 00b360af-d809-4d4f-a2b4-642a2d927d59
PASS rescued_cvs 01daad2e-fa1f-4f00-8b22-91d89c4a3494
SAMPLE FIDELITY OK
```

`diff -u v1_before.txt v1_after.txt` was empty.

### Recovery (§4.7), run in the order b → re-apply → a → c on the same scratch database

- **b. Rollback.**
  - The three-`--path` rollback rolled back `000003`, `000002` and `000001`.
  - A table diff showed exactly `overture_v2_chain_memberships`, `overture_v2_corpora` and
    `overture_v2_places` dropped, and nothing else added or removed.
  - The migration ledger was back to the 11 batch-1 rows.
  - `v1_snapshot` was byte-identical to `v1_before.txt`.
- **Re-apply.** The same three-`--path` migrate produced a new batch 2 of 3, with empty tables.
- **a. Interrupted import.**
  - A real full `--write` import was started.
  - A server-side watcher found the importer's backend running `INSERT INTO overture_v2_places …` with
    an assigned transaction id and ≥ 2 MB of uncommitted heap (observed: 2,105,344 bytes on the freshly
    re-created table), then called `pg_terminate_backend` on it. The call returned `t`.
  - The importer printed `FAILED, rolled back: … no connection to the server` and exited 1.
  - Resulting state:
    - the corpus row was `failed`, with `finished_at` and `failure_reason` recorded;
    - 0 places, 0 memberships, 0 `ready` rows;
    - v1 byte-identical.
  - The write counters reconcile: 58,310 place inserts = 5,594 written by the terminated attempt and
    rolled back, plus 52,716 committed by the retry.
- **c. Retry and re-verify.**
  - The identical import printed `IMPORTED` under a new run token (`LEDGER ready 4ff05b85e94c411998c809db52e40306`).
  - Verify V01–V12 all PASS, `VERIFY OK`.
  - Second import `ALREADY READY`, then `VERIFY OK`, with one unchanged `LEDGER ready` line.
  - `SAMPLE FIDELITY OK`.
  - The final `v1_snapshot` was byte-identical to the pre-v2 `v1_before.txt`.

### Deviations, recorded so a reader can judge them

- **Networking.** This Docker sandbox refuses container-to-container TCP and joining another
  container's network namespace; host-to-container works. The database-phase operator containers
  therefore ran with `--network host` to reach the internal-only scratch server. They carried no live
  credential; only the scratch URL was ever supplied. Extraction, the only step that needs the
  internet, ran on the default bridge.
- **Vendor boot side effect.** `dipeshsukhia/laravel-country-state-city-data` rewrites
  `app/Models/{Country,State,City}.php` and `database/seeders/CountryStateCityTableSeeder.php` on every
  console boot, so the export had to be writable, as a runner checkout is. The rewritten bytes are
  identical to the commit.
- **First fixture attempt failed.** It ran in scratch database `ov2_rehearsal` and stopped on an int4
  overflow in the coordinate arithmetic, before any attach or ledger row. That database was left
  untouched. The fixture was fixed (a `bigint` series) and the whole rehearsal ran in a fresh database,
  `ov2_rehearsal2`. Two helper psql queries had cosmetic quoting errors; no rehearsal step failed.
- **Operator image differences from the workflow.**
  - psql client 15.19 (the server is 16.14).
  - `memory_limit=2G` (the importer sets the same).
  - Extra PHP extensions for `composer install`.
  - The container ran as uid 1000 so the scratch mounts were writable without changing their
    permissions.
- **PostGIS build.** PostGIS 3.6.3 was compiled against GEOS 3.12.1 and runs on GEOS 3.14.1 from PGDG.
- **Not exercised.** `bin/preflight.sh` cannot target a scratch server: its host guard admits only
  `*.db.postgresbridge.com`. It is not part of §4. It ran separately, read-only, against the live
  cluster on 2026-09-25 from an isolated operator container at `2baecc8bf`: P00–P08 PASS, PostgreSQL
  16.14, PostGIS 3.6.3.

**Validity.** This evidence covers `7c6ee26b2` only. If anything under the gate's diffed paths lands on
`main` before the load (`app config database/migrations/spatial composer.lock artisan bootstrap
scripts/overture-v2`, this directory's `bin/` and `sql/`, or the workflow file), the `gate` job refuses
and the rehearsal must be repeated.
