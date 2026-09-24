# Overture corpus v2 — spatial schema and guarded importer

**Status:** IMPLEMENTED, INERT, NOT LOADED. This is PR 4 of `overture-corpus-v2-design.md` §11:
three PostGIS tables and a guarded, local-file importer for one validated `overture-extract-v2`
output (`overture-v2-extraction-recipe.md`). **No real corpus has been loaded** — the 52,566-row
Florida extraction is declared as a contract and imported by nobody. **Nothing here activates v2**:
there is no `active` state, no pin, no provider switch and no reader outside the importer.

| | |
|---|---|
| Migrations | `database/migrations/spatial/2026_09_24_00000{1,2,3}_spatial_overture_v2_*` |
| Importer | `app/Services/Spatial/OvertureV2Import/` — contract, gate, plan, importer |
| Command | `php artisan corpus:import-overture-v2` |
| Contracts | `config/overture_v2_corpus.php` (one reader: `OvertureV2ImportContract`) |

## 1. Side by side with v1, never through it

v1 — `places` (partitioned by `corpus_version`), `place_categories`, `corpus_imports` and the rest —
is **not altered, not written, not read and not referenced**. The v2 migrations name only
`overture_v2_*` tables; the importer reads and writes only those plus `pg_extension`; both facts are
asserted at source (`OvertureV2SchemaStructuralTest`) and against a real PostGIS
(`OvertureV2PostgisIntegrationTest`, which snapshots every v1 table's columns, constraints, indexes
and rows before and after migrating, importing and rolling back).

This **supersedes the §5 "proposed schema diff"** in the design, which added nullable columns to v1
`places` and seeded v1 `place_categories`. A v2 corpus that lives inside the v1 tables can only be
kept away from Location DNA by every reader remembering to filter it; separate tables make that
structural. Location DNA reads `places` only, and nothing under `app/` outside the import path
names an `overture_v2_` table (asserted).

## 2. Tables

### `overture_v2_corpora` — the ledger, one row per corpus version

| Column(s) | Meaning |
|---|---|
| `corpus_version` PK | e.g. `overture-2026-08-19.0-fl-r2` |
| `status` | `preparing` → `ready` \| `failed`. **No `active` value exists** (CHECK). |
| `import_run` | the token of the importer run that owns the current attempt (§5) |
| `source_release`, `extract_recipe_version`, `taxonomy_map_version` | the extraction's pins |
| `registry_version`, `registry_rule_hash`, `registry_match_precedence_version`, `registry_normalizer_version` | the chain registry that produced every membership |
| `manifest_sha256`, `base_sha256`, `supplementary_sha256`, `input_file_sha256` | exactly which files were imported |
| `base_rows`, `supplementary_rows`, `matcher_analysis_rows`, `diagnostic_rows`, `rescue_admitted_rows`, `rescue_refused_rows`, `expected_memberships` | the manifest's accounting |
| `imported_base_rows`, `imported_rescued_rows`, `imported_memberships` | what the database actually holds, re-counted inside the import transaction and written from that count — never copied from the plan |
| `manifest` jsonb | the whole manifest |
| `started_at`, `finished_at`, `failure_reason` | lifecycle |

CHECKs: `matcher_analysis_rows = base + supplementary` and `supplementary = diagnostic + admitted +
refused`; a `failed` row carries a reason; and **`ready` is only reachable when every imported count
equals its expected count and the import finished** (`overture_v2_corpora_ready`). That CHECK is
wrapped in `COALESCE(…, false)`: PostgreSQL passes a CHECK whose expression is NULL, and
`NULL = base_rows` is NULL, so without it a `ready` row with no imported counts would be accepted.
`UNIQUE (corpus_version, registry_version, registry_rule_hash)` exists to anchor the memberships.

### `overture_v2_places` — normalized imported places

One row per imported record. `id bigserial` PK; `UNIQUE (corpus_version, source_ref)` — one Overture
place once **per corpus**, so two corpora hold the same `source_ref` side by side;
`UNIQUE (id, corpus_version)` exists to be the target of the membership FK. `corpus_version`
references the ledger with **no ON DELETE action**: a corpus that still has places cannot be deleted
out from under them.

`geom geography(Point, 4326) NOT NULL`, built from the record's `lon`/`lat`; **GiST index**
`overture_v2_places_geom` (operator class `gist_geography_ops`, asserted by the PostGIS integration test) for the later
site builder's spatial work, and a btree on `(corpus_version, category_key)`. Address columns are
internal (dedup and site identity), never a display contract.

### `overture_v2_chain_memberships` — the matcher's verdicts

One row per (place, chain): `UNIQUE (place_id, brand_key)` — **several chains per place** (a
declared, evidenced co-brand, design decision 3c) but **each chain once**. Two FKs: the composite
`(place_id, corpus_version) → overture_v2_places (id, corpus_version) ON DELETE CASCADE`, so a
membership **can never point across corpora** and dies with its place; and
`(corpus_version, registry_version, registry_rule_hash) → overture_v2_corpora`, so every
membership carries **its own corpus's registry** — one corpus can never hold verdicts from two. The unique index is led by
`place_id`, which serves the FK's cascade lookups; a btree on `(corpus_version, brand_key)` serves
"every member of chain X".

`role` / `storefront_status` / `format_key` are stored exactly as the matcher reported them, and a
CHECK ties the pairs together: `storefront`/`storefront`, `department`/`storefront_unconfirmed`,
`fuel`/`fuel`. Formats such as `store_in_target` (CVS inside Target), `pharmacy_department` and
`fuel` are persisted verbatim. **Whether a SITE is a storefront, fuel-only or co-branded is not
decided here** — that is a statement about all of a site's members, made by the site builder
(`ChainSiteClassifier`) in a later PR. `co_brand_with text[]` and `rescued_from_source_category`
record the matcher's evidence.

## 3. Base vs supplementary — only materialization candidates are stored

The extraction has two lanes (recipe §4). The table stores exactly two shapes, and the database
enforces them (`overture_v2_places_lane_contract`, also `COALESCE(…, false)` so a NULL role or
verdict is a violation, not a pass):

| Lane | Stored as | Must be |
|---|---|---|
| base | `materialization_policy = 'corpus'`, `eligibility = 'base_category_eligible'` | a canonical `category_key`, every rescue field NULL |
| supplementary, **admitted rescue** | `materialization_policy = 'rescued'`, `eligibility = 'supplementary_selector'` | role `rescue_candidate`, verdict `admitted`, **no** `category_key`, and every rescue field present |

**`matcher_only` is never stored.** A diagnostic row (`not_candidate`) or a refused rescue
candidate cannot satisfy either branch — `materialization_policy` is NOT NULL and names neither
value — so the database refuses it even if the importer tried. The gate never carries such rows in
the first place; they are counted in the ledger (`diagnostic_rows`, `rescue_refused_rows`).

**Rescued rows keep their target out of `category_key`.** An admitted rescue (today only the
`cvs_shopping` lane: chain `cvs`, source token `shopping`, target `drugstore`) records
`rescued_lane`, `rescued_chain`, `rescued_as_category` and `rescued_format` (`store` or
`store_in_target`); `category_key` stays NULL, so a category scan over the base lane is never
widened by a rescue. The row's membership carries `rescued_from_source_category = 'shopping'`.

## 4. The gate — every pin must agree before anything is written

`OvertureV2ImportGate::validate()` is pure: it reads `manifest.json`, `base.ndjson` and
`supplementary.ndjson` and is handed no connection, so **every refusal below happens before a
database is touched**. All of these must agree, or nothing is imported:

1. **The declared contract** for `--corpus-version` in `config/overture_v2_corpus.php` — exact keys,
   typed values, sha256-shaped digests, `matcher_analysis = base + supplementary`, a version that
   names its release. An undeclared version is refused. A contract binds counts and checksums to
   ONE version, so a new corpus is a new reviewed entry, never a relaxed check.
2. **The manifest** — `recipe.recipe_version`, `recipe.release`, `recipe.taxonomy_map_version`,
   `chain_registry.registry_version`, `chain_registry.rule_hash`, the counts,
   `counts.fully_accounted`, and both outputs' row counts and SHA-256.
3. **The files** — their actual SHA-256 against the contract.
4. **The running code** — `OvertureTaxonomyMapV2::VERSION`, and **the chain registry's version and
   rule hash**, plus the manifest's precedence and name-normalizer versions. The importer re-runs
   the matcher, so a verdict is only as good as the registry that re-derives it: an extraction
   produced under a different registry is refused **even when its manifest and contract agree with
   each other**.
5. **Every row** — the lane contract, versions, a base category that re-derives from
   `taxonomy.primary`, a point inside the manifest's box, confidence, status, address shape, no
   duplicate `source_ref`. A `pending` (unresolved) verdict refuses the whole import.
6. **The accounting** — supplementary roles, rescue verdicts and base per-category tallies against
   the manifest.
7. **The chain census**, re-run over the rows to be imported: total, from-base, rescued, ambiguous,
   co-branded and per-chain counts must equal the manifest's exactly — strict comparison, so a
   count the manifest omits or states as text does not reconcile — and each admitted rescue must
   re-match its own recorded chain and format.

**Known limit:** the contract pins the two data files' SHA-256, not `manifest.json`'s own. The
manifest's recipe parameters (box, confidence floor, statuses, rescue lanes) and its stored copy are
therefore checked for consistency with the pinned rows and the running registry, not pinned
byte-for-byte; `manifest_sha256` is recorded on the ledger so what was imported stays identifiable.
Row contents cannot be altered without failing the file checksums, and every rescue verdict is
re-derived by the running registry.

## 5. Lifecycle and the import transaction

`OvertureV2CorpusImporter` writes only the three v2 tables, in three steps:

1. **Mark `preparing`** under a fresh random **run token** (`import_run`) — an upsert that refuses
   to touch a `ready` row. Its own short statement, so an interrupted import is visible as
   `preparing` rather than invisible.
2. **One transaction** — lock the ledger row `FOR UPDATE`; require it still `preparing` **under this
   run's token**, with this plan's checksums and **zero** existing place rows; insert every place
   (batches of 500) and membership (batches of 1,000); **re-count from the database** (base,
   rescued, per-category, memberships) against the plan; set `ready`, recording the **database's**
   counts — which the ledger's own CHECK refuses unless each equals its expected count. Any failure
   rolls **all** of it back.
3. **On failure, mark `failed`** with a bounded reason (exception class and message, never bound
   values or connection details) — **only while the row is still this run's `preparing` attempt**.
   If another importer re-armed it meanwhile, the failure is reported to the caller and the other
   run's attempt is left alone; each run can lock, finish or fail only its own attempt. The corpus
   holds no rows and can never read as `ready`.

| Situation | Outcome |
|---|---|
| no row, or `preparing` / `failed` holding no places | imported from zero; a retry cannot duplicate (step 2 proves no rows exist) |
| `preparing` / `failed` that somehow holds places (only by hand-written SQL) | refused, nothing written — never added to |
| `ready`, **same** checksums and pins | `ALREADY_READY` — explicit no-op, nothing written |
| `ready`, **any** different checksum, rule hash, release, recipe or taxonomy | hard failure; a ready corpus is **never** overwritten |
| a concurrent importer made it `ready` first | refused, nothing written |

Every statement is scoped to the one `corpus_version` being imported, so a retry rebuilds only that
version and every other corpus is untouched (the PostGIS integration test keeps a second, ready corpus beside
a failing one).

## 6. The command — IMPORT, never ACTIVATE

```
php artisan corpus:import-overture-v2 --corpus-version=<declared> --extract-dir=<dir>          # dry run
php artisan corpus:import-overture-v2 --corpus-version=<declared> --extract-dir=<dir> \
    --write --database=<connection>                                                          # import
```

* **A dry run is the default** and opens no connection. `--extract-dir` must be a local directory;
  a stream wrapper (`ftp://`, `file://`, `phar://`, …) is refused.
* **`--write` requires an explicit `--database`. There is no default and no fallback.** An absent or
  blank name, an unknown connection, the application's default connection or a non-PostgreSQL
  driver is refused before the extraction is even read; the importer then refuses a PostgreSQL
  connection without PostGIS or without the v2 tables. Writing to the configured spatial database
  is therefore an operator typing `--database=pgsql_spatial` — a conscious operations action, not a
  default.
* **Production is refused twice, with no override**: `RefusesProductionDatabase` as the first
  statement of `handle()` (any production signal in the process environment or in any configured
  connection, the house pattern), and the application environment. Because the guard reads the raw
  process environment, a real load must be run from a shell that carries no production signals.
* **It cannot activate.** No `active` state exists; the command sets no flag, pins no version and
  touches neither `location_providers` nor `overture_corpus_poi`. `ready` is the best state an
  import can reach. Local files only — no network.
* Registered by the Kernel's `$this->load(__DIR__ . '/Commands')` directory scan (asserted).

**Rolling back.** The three v2 migrations drop only their own tables, children first. But
`migrate:rollback` rolls back the last *batch*: on a spatial database that has never been migrated,
`migrate --path=database/migrations/spatial` puts all sixteen spatial migrations — v1 and v2 — in
ONE batch, and a plain rollback would then drop v1 as well. Roll v2 back by count
(`migrate:rollback --database=pgsql_spatial --path=database/migrations/spatial --step=3`), or apply
v2 in a batch of its own (its three files passed as `--path`), which is what the integration test
does.

## 7. What this does not do

No real corpus load. No activation, pin or provider change. **No physical-site materialization, no
proximity grouping and no de-duplication** — the importer stores places and memberships only; sites
are PR 5. No brand-radius query service (PR 6). No Location DNA, Ask AI, Buyer/Tenant or Smart Tags
change; nothing outside the import path reads a v2 table.

## 8. Validation

CI (SQLite, no PostGIS) runs the gate, contract, command and structural tests listed in
`tests/spatial-ci-files.txt`. The schema and importer against a real server are covered by
`OvertureV2PostgisIntegrationTest`, which **skips unless a throwaway server is named** through
`OVERTURE_V2_SCRATCH_PGHOST` (an absolute Unix-socket directory — a network host is refused),
`OVERTURE_V2_SCRATCH_PGPORT`, `OVERTURE_V2_SCRATCH_PGUSER` and `OVERTURE_V2_SCRATCH_PGDATABASE`.
Those are deliberately not the `SPATIAL_*` variables. Each test creates and drops its own `ov2t_*`
database. It covers a fresh migration of all sixteen spatial migrations; an upgrade over a
populated v1 with a before/after snapshot; a rollback of the v2 batch and its re-application; a
valid import through the command; every membership shape; idempotency and version collision; a
deterministic failure injected after the place inserts (a trigger on the membership insert); the
retry of `failed` and `preparing` corpora; and the database's own refusals (`matcher_only`, NULL
role/verdict, NULL or mismatched ready counts, `active`, duplicate and cross-corpus memberships).

The spatial extensions migration pins PostGIS **3.6.3**. On a scratch server carrying a different
version it refuses, as designed (a test asserts the refusal); the integration test then creates the
same three extensions directly and records that migration as run — on the scratch database only.

The local run's results are recorded in the pull request, not here.
