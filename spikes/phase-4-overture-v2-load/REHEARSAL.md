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
rehearsed_commit: 183375da8ca95f2d94850dbbf9fb50ae7eee8cde
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

## Evidence — current rehearsal of `183375da8`

Rehearsal of RUNBOOK §4, performed 2026-09-25 (UTC) on a **throwaway** scratch instance. The live
spatial cluster was never contacted and no live credential was present in any rehearsal container.
Reviewed by an independent read-only reviewer before this record was written (verdict: CLEAN). Its
one pre-commit request, that the PostgreSQL minor version be measured in this run rather than
carried over, was met.

### Why this commit was re-rehearsed

- `7c6ee26b2` passed the original full-size rehearsal. That record is kept below as history.
- PR #209 (`feat/bridge-smart-tag-coverage-backfill`) then moved `main` to `183375da8`. It changed
  21 Smart Tags / matching files under `app/` and `config/`, and both of those paths are in the
  gate's diff.
- None of the files PR #209 changed mention Overture, spatial code, corpus or `pgsql_spatial`.
- Against `7c6ee26b2`, none of the following changed:
  - `database/migrations/spatial/`, `scripts/overture-v2/`, this directory's `bin/` and `sql/`;
  - the workflow file, `composer.lock`, `artisan`, `bootstrap/`;
  - `config/overture_v2_corpus.php` (the artifact contracts).
- The gate compares paths and does not judge relevance, so it refuses. Nobody waived it:
  `183375da8` was fully re-rehearsed from scratch, with a fresh scratch server, a fresh extraction
  and every recovery exercise repeated.

### Where, and on what

- **Code:** `git archive 183375da8ca95f2d94850dbbf9fb50ae7eee8cde` (origin/main) into a disposable
  directory. After every run, all 24,383 exported files were byte-identical to the commit (blob
  SHA-1), with 0 mismatched.
  - Not exported: the commit's own `export-ignore` paths (`.github/**` and one vendored
    `CHANGELOG.md`), plus `.claude/` (agent settings, excluded on purpose).
  - None of those is executed by the rehearsal.
- **Scratch server:** the same image the `7c6ee26b2` rehearsal validated (not rebuilt), image id
  `sha256:4f10dd85218b12841b801bd809f5f31f70b3aa23d31598330a05185d7f380a45`. Provenance was
  re-verified inside it before use:
  - base `ubuntu:24.04@sha256:008173c23f95b170204355c12626cb5a965d779a7e1283b09e9cffbb1bf33ca3`
    (same root layer);
  - PGDG `noble-pgdg-archive`, key fingerprint `B97B0AFCAA1A47F044F244A07FCC7D46ACCC4CF8`;
  - `postgresql-16=16.14-1.pgdg24.04+1`, `postgresql-16-postgis-3=3.6.3+dfsg-1.pgdg24.04+1`,
    `postgresql-16-postgis-3-scripts=3.6.3+dfsg-1.pgdg24.04+1`, `libpq5=18.6-1.pgdg24.04+2`,
    GEOS `3.14.1-2.pgdg24.04+1`.

  It ran as a **new** container on a **new** Docker `--internal` network: fresh `initdb`, TLS only,
  no published port, no mount, and a scratch-only random password. The password was supplied only
  through `--env-file`; it never appeared in argv, output or evidence, and the URL carried none.
  The earlier rehearsal's stopped container was left untouched.
  - Measured in this run, read-only, on the rehearsal database `ov2_rehearsal2`: `SELECT version()` →
    `PostgreSQL 16.14 (Ubuntu 16.14-1.pgdg24.04+1)`, `postgis_lib_version()` → `3.6.3`.
  - `postgis_full_version()` → `POSTGIS="3.6.3 3d12666" [EXTENSION] PGSQL="160"
    GEOS="3.14.1-CAPI-1.20.5" PROJ="9.8.1"`.
  - Connection `TLSv1.3`.
- **Operator client:** the same validated operator image, image id
  `sha256:21366cde728196a66e77448cd0cc1313a99b62b1c27c0a087a3cc3ea1f958ea0`.
  - Built from `php:8.2-cli-bookworm@sha256:ac125c2d3ce1e8e33b503bcf85bdea9b43ed108515134629163a5971b3c9ac85`
    and `composer@sha256:9715c7f69044da2a212a5fbde29ee7da24e364d426560ae6367b060236f847d7`.
  - Re-verified: PHP 8.2.34, Composer 2.10.3, duckdb 1.5.5, psql 15.19,
    `zend.exception_ignore_args=On`, `memory_limit=2G`.
  - The image contains no code. Its tag names the old commit, but that is only a label; the code
    came from the `183375da8` export.
  - `composer install --prefer-dist` ran inside it.
  - Container hardening: non-root, `--cap-drop ALL`, `no-new-privileges`, read-only root
    filesystem, tmpfs `/tmp`, no Docker socket, no home mount, no `.env`.
- **Environment:** the import job's declared env (`APP_ENV=operator`, `DB_CONNECTION=sqlite`,
  `DB_DATABASE=:memory:`, array cache/session, sync queue, `LOG_CHANNEL=stderr`,
  `PGSSLMODE=require`, an ephemeral `APP_KEY`), plus `PGCONNECT_TIMEOUT=15`.
  - `SPATIAL_DATABASE_URL` named the scratch server and carried no password.
  - The scratch password reached libpq / Laravel only as `PGPASSWORD` / `SPATIAL_PGPASSWORD`, set
    inside the container.
  - Absent (checked by name inside the container, and logged in this run): `DATABASE_URL`, `PGHOST`, `PGHOSTADDR`,
    `PGDATABASE`, `PGUSER`, `PGPORT`, `PGSERVICE`, `PGSERVICEFILE`, `PGOPTIONS`, `DB_HOST`,
    `REPLIT_DEPLOYMENT`, AWS and GitHub credentials, and `SCRATCH_PASSWORD` itself once it was
    mapped.
- **Commands:** identical to the `7c6ee26b2` record below (§8 steps 1–3, §4.1, §7, §8 steps 4–10,
  and the §4.7b three-`--path` rollback).

### Extraction (§4.2) — byte-identical to the contract

```
release rows           : 73631092
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

The extraction files were kept in scratch only and not committed.

### Schema and v1 fixture (§4.1)

- The eleven core migrations went in as batch 1, each named by `--path`, followed by the two v1
  seeders (7 categories, 8 mappings).
- The v2 `--pretend` printed exactly three `CREATE TABLE` and three `CREATE INDEX` statements. The v2
  migrate produced batch 2 holding only `2026_09_24_000001/2/3`. The two August address migrations
  were never applied.
- **v1 fixture: SYNTHETIC** (§4.1's second option), built the same way as for `7c6ee26b2`:
  - a staging partition `LIKE places` (`places_p_overture_2026_06_17_0_fl`);
  - 29,434 deterministic rows inside the FL bbox, in registered categories, confidence ≥ 0.90, with
    `bigint` arithmetic;
  - the batch-2c acceptance gate (all zero);
  - a `staging` ledger row;
  - `CHECK` + `ATTACH PARTITION` + activation in one transaction.

  Result: `overture-2026-06-17.0-fl` is the one `active` overture-places corpus, with 29,434 rows.
  No production row or dump was used.

  The script was regenerated, so the places / ledger hashes differ from the `7c6ee26b2` fixture.
  The taxonomy hashes are identical. What §4.6 requires is before-vs-after identity within this
  rehearsal.
- `v1_before.txt`:
  ```
  v1_places_rows=29434
  v1_places_md5=6b627d18d06c58fcb1340d8f007a0854
  places_partitions=places_p_overture_2026_06_17_0_fl
  corpus_imports_rows=1
  corpus_imports_md5=1cc6df40940270057e85f1cedb96d7ac
  place_categories_md5=cd4c9593da925e87ece2f39b9cd0501e
  place_category_mappings_md5=c736f9cfe28685ce67b09fb0ce7627ba
  place_authority_links_rows=0
  v1_partition_counters=29434/0/0/0
  active_overture_places=overture-2026-06-17.0-fl
  ```

### Import, reconciliation, idempotency, fidelity, v1 (§4.2–§4.6)

Dry run `VALIDATED`, then `IMPORTED — overture-2026-08-19.0-fl-r2 is ready (not active; nothing was
activated).` Registry `chain-registry-v2 b5920a1c73199a0d8e030e5281018baa763438eb7ad02aee76f7ba3cbc0b151f`.

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
LEDGER ready d3779376bc9e84e6e39d0f9fadaff46c 2026-09-25T02:24:28.929756Z 2026-09-25T02:24:28.936098Z
VERIFY OK
```

**Idempotency (second import):**
- It printed `ALREADY READY — overture-2026-08-19.0-fl-r2 was imported from these exact files before;
  nothing written.`
- The second verify was `VERIFY OK`. Exactly one `LEDGER ready` line, identical in both verify
  outputs.
- **Additional consistency check:** a third identical import was also `ALREADY READY`. Across it, the
  v2 content hashes (md5 over every row of all three tables) and the `pg_stat_user_tables` write
  counters were identical before and after.
- Memberships by role: storefront 10,533, fuel 239, department 310, with 7 `store_in_target`
  memberships among them.

`compare_sample.py`: 12/12 `PASS` (the same twelve sampled ids as the `7c6ee26b2` record below),
then `SAMPLE FIDELITY OK`.

`diff -u v1_before.txt v1_after.txt` was empty.

### Recovery (§4.7), same scratch database, order b → re-apply → a → c

- **b. Rollback.**
  - The three-`--path` rollback rolled back `000003`, `000002` and `000001`.
  - A table diff showed exactly `overture_v2_chain_memberships`, `overture_v2_corpora` and
    `overture_v2_places` dropped, and nothing else.
  - The migration ledger was back to the 11 batch-1 rows.
  - `v1_snapshot` was byte-identical to `v1_before.txt`.
- **Re-apply.** The same three-`--path` migrate produced a new batch 2 of 3, with empty tables.
- **a. Interrupted import.**
  - A real full `--write` import was started.
  - A server-side watcher found the importer's backend executing `INSERT INTO overture_v2_places …`
    with an assigned transaction id and ≥ 2 MB of uncommitted heap (observed: 2,220,032 bytes),
    then called `pg_terminate_backend` on it. The call returned `t`.
  - The importer printed `FAILED, rolled back: … no connection to the server` and exited 1.
  - State read immediately afterwards, before any retry:
    - the corpus row was `failed`, with `finished_at` and `failure_reason` recorded;
    - 0 places, 0 memberships, 0 `ready` rows;
    - v1 byte-identical.
- **c. Retry and re-verify.**
  - The identical import printed `IMPORTED` under a new run token
    (`LEDGER ready 6fca674da5bfc3926beff6b264b1f3a7`).
  - Verify V01–V12 all PASS, `VERIFY OK`.
  - Second import `ALREADY READY`, then `VERIFY OK`, with one unchanged `LEDGER ready` line.
  - `SAMPLE FIDELITY OK`. `store_in_target` 7.
  - The final `v1_snapshot` was byte-identical to the pre-v2 `v1_before.txt`.
  - The write counters reconcile: 58,590 place inserts = 5,874 written by the terminated attempt and
    rolled back, plus 52,716 committed by the retry.

### Deviations, recorded so a reader can judge them

- **Networking.** The same as for `7c6ee26b2`:
  - database-phase operator containers ran with `--network host` to reach the internal-only
    scratch server;
  - extraction and `composer install` ran on the default bridge with no database credential.
- **First extraction attempt refused.** The release count and bbox extract had completed when the
  extractor refused with `--output-dir must name an existing directory` (exit 1, nothing written).
  The directory was created, and only the extractor step was rerun on the same raw bbox file. The
  counts and hashes above come from that rerun.
- **Vendor boot side effect.** The same as for `7c6ee26b2`: the export was writable, and after the
  runs every exported file was byte-identical to the commit.
- **First fixture attempt failed.** It ran in scratch database `ov2_rehearsal` and stopped on a
  constraint-name quoting error in the fixture script.
  - This happened after the staging partition and `staging` ledger row were written, and before any
    `CHECK`, attach or activation.
  - That database was left untouched. The `postgis_full_version()` line above was printed there,
    on the same server; the 16.14 / 3.6.3 / TLS reading was taken on `ov2_rehearsal2`.
  - The name was fixed, and the whole rehearsal (core migrations onward) ran in a fresh database,
    `ov2_rehearsal2`.
  - Two helper queries had cosmetic errors. One of them stopped a helper script before its first
    write, and that script was then rerun in full. No rehearsal step failed.
- **An unobserved interruption cycle ran and was discarded.**
  - A malformed orchestration command started two imports back to back and discarded both
    importers' output.
  - The watcher terminated the first mid-insert (2,162,688 bytes, `t`). The second then ran as an
    unlogged retry.
  - The resulting state was consistent: `ready`, 52,716 / 11,082, `VERIFY OK`, v1 byte-identical;
    5,734 terminated inserts were rolled back.
  - Because the post-termination state and the importer output were not captured, that cycle is
    **not** counted as evidence. The full b → re-apply → a → c cycle above was then run again with
    every output captured.
- **Operator image differences from the workflow.** The same as for `7c6ee26b2`:
  - psql client 15.19;
  - `memory_limit=2G`;
  - extra PHP extensions for `composer install`;
  - container uid 1000.
- **Not exercised.** `bin/preflight.sh`, as for `7c6ee26b2`. Its host guard admits only the live
  cluster's domain.

**Evidence outside Git:** a sanitized copy (summary and raw outputs, no credential, URL, host or IP)
is kept in the operator audit store under `overture-v2-rehearsal-20260925-183375da8/`. The earlier
`overture-v2-rehearsal-20260925-7c6ee26b2/` copy is unchanged.

**Validity.** This evidence covers `183375da8` only. If anything under the gate's diffed paths lands
on `main` before the load (`app config database/migrations/spatial composer.lock artisan bootstrap
scripts/overture-v2`, this directory's `bin/` and `sql/`, or the workflow file), the `gate` job
refuses and the rehearsal must be repeated.

## History — earlier rehearsal of `7c6ee26b2` (passed; no longer satisfies the gate)

Its gate block, retained verbatim as history. The markers are removed so that the gate reads only
the current block above:

```
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
```

It was a valid full-size pass of `7c6ee26b2`. It stopped satisfying the gate only because PR #209
advanced `main` through gated `app/` and `config/` paths. Its evidence follows unchanged. "The
commit" and "origin/main at the time" in it mean `7c6ee26b2`.


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
