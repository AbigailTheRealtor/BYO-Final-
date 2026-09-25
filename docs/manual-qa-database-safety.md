# Manual QA database safety

**Status:** implemented on `fix/manual-qa-production-db-guard` (2026-09-14). Application and repository
safety only. It changes no PostgreSQL setting, no backups or WAL, no production data, and no web runtime.

## The problem

PHPUnit cannot reach the shared database. `tests/bootstrap.php` neutralises `DATABASE_URL`, `DB_*`
and `PG*` before Laravel loads, and `tests/TestCase.php` checks the resolved connection. Nothing else
had that protection. A standalone script, a debug Artisan command or a fixture seeder inherits the
shell, and on this host the shell is production:

| Source | Value |
|---|---|
| `.replit` `[userenv.shared]` | `DB_CONNECTION=pgsql`, `DB_HOST=helium`, `DB_DATABASE=heliumdb` |
| platform-injected | `DATABASE_URL=postgresql://…@helium/heliumdb`, `PGHOST=helium`, `PGDATABASE=heliumdb` |
| workspace `.env` | `APP_ENV=production` |
| `config/app.php` | `APP_ENV` defaults to `production` when unset |

So "run the QA script" meant "run it on production" unless somebody remembered otherwise.

## Audit (2026-09-14): entry points that could reach production

### Standalone PHP: every Laravel-booting one could reach production

| Entry point | What it does | Guard now |
|---|---|---|
| `scripts/ask_ai_normalizer_staging_verify.php` | Ask AI runner on seller #121, live OpenAI. **Was piped into `tinker`** | `ManualScriptBootstrap`, no override |
| `scripts/ask_ai_staging_verify_live.php` | classifier and normaliser, live OpenAI | same |
| `scripts/run_normalizer_verify.php` | reads `AiFaqAnswer` for seller #121, runs the Ask AI runner | same |
| `scripts/debug_minimal_openai.php`, `debug_normalizer_raw.php`, `debug_normalizer_simple.php`, `debug_normalizer_single.php`, `debug_normalizer_verbose.php`, `debug_openai_raw_data.php`, `debug_raw_response.php` | boot the full app, call OpenAI | same |
| `spikes/phase-2-batch-1b-postgis-schema/validate/run_validation.php` | `EXPLAIN ANALYZE` on `pgsql_spatial`. Already refused `APP_ENV=production` | same (APP_ENV check kept) |
| `scripts/ai-faq/{gen_tables,md_tables,parse_faq}.php` | format static config arrays | not applicable (declared, and verified) |
| `spikes/phase-2-batch-2a-overture-first-slice/q2/run_measurements.php` | shells out to a local DuckDB | not applicable (declared, and verified) |

No script needed a production escape hatch. All of them are debugging or staging verification,
so none accepts the override.

### Artisan: QA / debug / fixture commands

| Command | DB effect | Before | Now |
|---|---|---|---|
| `migrate:incremental-fixture {seed\|verify}` | **inserts and deletes** `property_location_dna` fixture rows | nothing (a docblock saying "CI only") | refuses production, no override |
| `ldna:benchmark-tile-precision` | reads listings, flushes the POI tile cache, re-runs POI lookups | nothing | refuses production, no override |
| `matching:materialize` | writes `matching_v2_*` | `APP_ENV` only | also the resolved database, no override |
| `matching:validate` | reads, writes JSON to storage | `APP_ENV` only | also the resolved database, no override |
| `ldna:audit-listing {id}` | read-only JSON dump | nothing | refuses production; **accepts `--i-know-this-is-production`** |
| `matching:preview {type} {id}` | read-only pipeline preview | nothing | refuses production; **accepts `--i-know-this-is-production`** |

The override exists only where a command **only reads** and a production investigation is a real
use (`ldna:audit-listing` is listed in CLAUDE.md as the way to inspect a listing's pipeline state).

Deliberately **not** guarded, because they are production operations or never open a connection:
the deploy commands, backfills, imports, `mls:sync-listings`, `offers:expire-pending`,
`hireagent:retire-tenant-type` (it has its own `--write` / backup protocol), read-only reports
(`ask-ai:snapshot-audit`, `wizard:funnel-report`, `census:verify-geography`, `ldna:poi-cost-report`,
`bridge:validate-phase0`), the Bridge and Census probes (no database; `--force-probe` gates them),
and the OFFLINE corpus and gate commands (no database; they already refuse production).

### Seeders that create QA fixtures

| Seeder | Creates | Before | Now |
|---|---|---|---|
| `UserSeeder` | shared-password accounts, including `admin@exp.com` | `APP_ENV` only | also the resolved database; still **skips** with a warning (post-merge and DatabaseSeeder depend on that) |
| `CriteriaMatchTestSeeder` | criteria metas for test users | `APP_ENV` only | also the resolved database; still prints an error and returns |
| `BridgePropertySeeder` | sample `bridge_properties` rows | `APP_ENV` only | also the resolved database; still prints an error and returns |
| `LocationDnaSellerSeeder` | a factory user and a test listing | **nothing** | **throws** `ProductionDatabaseRefused` |
| `LocationDnaTestSeeder` | criteria for hard-coded user #136 | **nothing** | **throws** `ProductionDatabaseRefused` |

`UserSeeder`'s gap mattered: `scripts/post-merge.sh` runs it when the shell's `APP_ENV` is not
`production`, and the shell does not carry the workspace `.env`.

### Shell scripts

| Script | Reaches | Treatment |
|---|---|---|
| `scripts/post-merge.sh` | `db:seed --class=UserSeeder`, `config:clear`, `view:clear` | unchanged. The seeder now checks the resolved database itself |
| `scripts/run-spatial-tests.sh` | PHPUnit | unchanged. PHPUnit isolation applies |
| `deploy/*.sh` | production, by design | unchanged. These are the production operators |
| `spikes/phase-2-batch-0a-postgis-knn/run_spike.sh` | `psql`; previously `${PGHOST:-172.17.0.2}`, so the ambient `PGHOST=helium` won, and step 00 runs `DROP TABLE IF EXISTS places_spike…` / `CREATE EXTENSION` | **guarded** by `lib/require-isolated-target.sh` (below) |
| `spikes/…/provider-validation/run_provider_spike.sh` | `psql -h … -d …`; previously the ambient `PG*` satisfied its "required" check | **guarded** by the same helper, before `--dry-run` too |
| `spikes/…/load_florida_overture_places.sh`, `load_florida_counties.sh` | `SPATIAL_DATABASE_URL` (the spatial cluster, not heliumdb) | unchanged. They require `--i-understand-live` and refuse `APP_ENV=production` |
| `spikes/phase-4-overture-v2-load/bin/preflight.sh` | `SPATIAL_DATABASE_URL`, read-only (`default_transaction_read_only = on`) | **guarded**: refuses `APP_ENV=production`, `REPLIT_DEPLOYMENT`, any `DATABASE_URL`/`DB_*` naming helium and ANY ambient libpq routing variable (`PGHOST`, `PGHOSTADDR`, `PGSERVICE`, …) — it refuses the shell rather than unsetting them — a URL with no explicit host, a host outside `*.db.postgresbridge.com`, any URL parameter beyond `sslmode`/`connect_timeout`/`application_name`, and a TLS mode weaker than `require`; then proves the target by fingerprint. It never prints `psql`'s raw error text (a connection error names the host and user, and this repository's Actions logs are public); `bin/mask_log_identity.sh` masks the target in every job that reads the secret. `OvertureV2OperatorLoadGuardTest` |
| `.github/workflows/overture-v2-operator-load.yml` | the live spatial cluster, through the **unchanged** `migrate` / `corpus:*-overture-v2` commands, from a GitHub runner that carries no production signals | `workflow_dispatch` only; the one secret lives in the protected Environment `overture-v2-spatial` (required reviewers); the artisan jobs DECLARE `APP_ENV=operator` (the runner has none, and `config/app.php` defaults to production) — a truthful identity in reviewed YAML, nothing unset or masked; pinned SHA on `main`; actions pinned by commit SHA; read-only preflight first; write stages gated on `REHEARSAL.md` and typed confirmations; exactly the three v2 migration paths; no activation. No production override is added anywhere. Runbook: `spikes/phase-4-overture-v2-load/RUNBOOK.md` |

**Stage 0 runner protection.** These are `psql`-only shell tools, so they do not reuse the PHP
guard's resolution logic. They use a smaller rule that fails closed, in
`spikes/phase-2-batch-0a-postgis-knn/lib/require-isolated-target.sh`, sourced and called before
anything else:

* The target must be named in `SPIKE_PGHOST` and `SPIKE_PGDATABASE`. Nothing injects these
  variables, and the ambient `PG*` variables are never read for the target. A blank or unset
  target refuses.
* `REPLIT_DEPLOYMENT` set, or `APP_ENV=production`, refuses.
* Host `helium` / `helium.*` (in any position of a host list, with or without a port), or a
  database containing `heliumdb`, refuses even when named explicitly.
* Every inherited libpq redirect is unset (`PGHOSTADDR`, which overrides `-h`; `PGSERVICE`,
  `PGSERVICEFILE`, `PGOPTIONS`, `PGPASSWORD`), then `PGHOST`/`PGDATABASE` are exported from the
  checked values.
* A refusal exits `3` before the first `psql`. Enforced by `Stage0SpikeRunnerIsolationTest`
  against a stub `psql` in a temp copy of the runners. No database is contacted.

## The shared guard

```
app/Support/Safeguards/ProductionDatabaseGuard.php       pure identity assessment and decision
app/Support/Safeguards/ProductionDatabaseAssessment.php  signals, resolved target, messages
app/Support/Safeguards/ManualScriptBootstrap.php         the one way a script boots Laravel
app/Support/Safeguards/ProductionDatabaseRefused.php     fixture seeders
app/Support/Safeguards/TinkerProductionNotice.php        tinker warning
app/Console/Concerns/RefusesProductionDatabase.php       Artisan commands
```

### Production identity: any one signal refuses

* the application environment (`config('app.env')` or `$app['env']`) is `production`
* `APP_ENV` is `production` on any of `getenv()`, `$_ENV`, `$_SERVER`
* `REPLIT_DEPLOYMENT` is set
* `DATABASE_URL` (raw, parsed) names host `helium` or a database containing `heliumdb`
* `PGHOST` / `PGHOSTADDR` name `helium`, or `PGDATABASE` contains `heliumdb`
* the **default** connection, resolved through `ConfigurationUrlParser` (the parser
  `ConnectionFactory` and `TestCase::resolvedConnection()` use), resolves to host `helium` /
  `helium.*` or a database containing `heliumdb`. This includes a comma-separated libpq host list,
  `read`/`write` hosts, and **libpq's fallback** when the host or database is blank
* any **non-default** connection that resolves to `pgsql` does the same (heliumdb is PostgreSQL, so a
  `mysql`/`sqlsrv` connection that merely names `DB_HOST=helium` cannot open a session on it)
* a target that cannot be verified: an undefined default connection, an unparseable URL, or a
  pgsql connection with no host while `PGSERVICE` is set

The guard opens no connection, runs no query and prints no credential.

### When it runs

* **Scripts:** `ManualScriptBootstrap::boot(__FILE__)` hooks `afterBootstrapping(LoadConfiguration)`.
  That is after env and config load, and before exception handling, facades, providers, observers or
  the script body. It checks again after the full bootstrap. A refusal writes to STDERR and exits `3`.
* **Artisan:** the first statement of `handle()`. No provider in this app queries during
  register or boot (audited), so nothing has touched the database yet. Exit `3`, or the command's own
  existing refusal code where it already had one (`matching:*` use `2`).
* **Seeders:** the first statement of `run()`.

### The explicit override: `--i-know-this-is-production`

* Exact token only. Not `--force`, not `-y`, not `--i-know-this-is-production=1`, not case-folded,
  not abbreviated.
* **Never** read from the environment. There is no variable that enables it.
* **Opt-in per entry point.** A script passes `permitProductionOverride: true` to `boot()`; a command
  declares the option in its signature **and** calls `refusesProductionDatabase(true)`. An undeclared
  option is rejected by Symfony before `handle()` runs.
* When it takes effect, a banner naming the target is printed to STDERR.
* Currently accepted by exactly two read-only commands: `ldna:audit-listing` and `matching:preview`.

### Running a script locally

```bash
APP_ENV=local DATABASE_URL= DB_CONNECTION=sqlite DB_DATABASE=/tmp/qa.sqlite \
PGHOST= PGHOSTADDR= PGDATABASE= PGSERVICE= php scripts/run_normalizer_verify.php
```

Every one of those assignments is needed in the Replit workspace. Leaving out `PGHOST=` still
refuses, because a pgsql connection with no host would go to `helium` through libpq.

## `php artisan tinker`

**Not blocked. It warns.** Tinker is how production has been inspected here:
`docs/runbook-location-seeders.md` and `docs/matching-v2-validation-runbook.md` document
`tinker --execute` read-only checks, run from a workspace connected to production. Blocking it would
break those runbooks with no sanctioned replacement.

What changed: when tinker starts against production it prints `TINKER IS CONNECTED TO PRODUCTION`,
with the resolved target and the signals, to **STDERR**. Output captured from STDOUT by
`--execute` is unchanged. The listener is registered by the console kernel only, so web requests
never see it, and it never throws.

**Remaining risk:** a tinker session can still create fixtures in production. The warning is the
only barrier.

**Proposed follow-up (separate PR):** a `qa:fixture {name}` Artisan command. Named, reviewed fixture
builders would live in one class, and the command would call `refusesProductionDatabase()` with no
override. QA fixture creation then has a sanctioned path that cannot reach production, and tinker is
left for read-only inspection. Once that exists, a restriction such as refusing tinker's interactive
shell on production unless `--i-know-this-is-production` is supplied becomes reasonable to discuss,
because there would be somewhere else to send people.

## Workflow changes

* `scripts/ask_ai_normalizer_staging_verify.php` is run with `php`, no longer piped into `tinker`.
* Every guarded script and command refuses in the Replit workspace unless run with the local
  overrides above. That is intended: those targets were production.
* `ldna:audit-listing` and `matching:preview` against production now need
  `--i-know-this-is-production`.
* `migrate:incremental-fixture` is unchanged in CI (`APP_ENV=testing`, pgsql on 127.0.0.1/`byo_test`).

## Still capable of hitting production accidentally

* `php artisan tinker` (warns only).
* The spatial loaders `load_florida_overture_places.sh` / `load_florida_counties.sh` pass
  `SPATIAL_DATABASE_URL` to `psql`. They require `--i-understand-live` and refuse
  `APP_ENV=production`, but a URL with no host would still fall back to the ambient `PGHOST`.
  The Overture v2 operator load (`spikes/phase-4-overture-v2-load/`) closes that gap for itself:
  `bin/preflight.sh` refuses a URL without an explicit Crunchy host before any `psql`, and its
  workflow writes only after that preflight, a recorded full-size rehearsal and reviewer approval.
* `php artisan db:seed` for reference-data seeders (`UsZipCodesSeeder` truncates `us_zip_codes`,
  `FloridaCitySeeder` deletes Florida cities, `DatabaseSeeder` runs 19 seeders). These are
  production data maintenance and were left alone deliberately.
* Production-maintenance commands (backfills, imports, remediation), by design.
* `psql` typed by hand, and any new entry point outside `scripts/` and `spikes/`. The coverage test
  discovers PHP files in those two directories only.

Enforced by `tests/Feature/Safeguards/ProductionDatabaseGuardTest.php`,
`ManualScriptBootstrapTest.php`, `ManualScriptGuardCoverageTest.php` and
`ProductionDatabaseGuardConsumersTest.php`.
