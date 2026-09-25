# RUNBOOK — Overture v2 corpus load (Class-2 operator procedure)

**Status: AUTHORED. Read-only preflight is the only stage that may run. The write stages are
disabled** by `REHEARSAL.md` (`rehearsal_status: NOT_RUN`) and will refuse until the full-size
rehearsal in §4 has passed and been recorded through a reviewed commit.

Loads the validated Overture v2 corpus **`overture-2026-08-19.0-fl-r2`** into the live
`pgsql_spatial` cluster (Crunchy Bridge PostgreSQL 16 + PostGIS 3.6) beside the active v1 corpus.
**Import only — it never activates v2.** Nothing here touches Location DNA, provider routing,
`overture_corpus_poi`, `location_providers`, `corpus_imports` or any v1 row.

| | |
|---|---|
| Corpus version | `overture-2026-08-19.0-fl-r2` (`config/overture_v2_corpus.php`) |
| Release / recipe / taxonomy | `2026-08-19.0` / `overture-extract-v2` / `overture-taxonomy-v2.0` |
| Registry / rule hash | `chain-registry-v2` / `b5920a1c73199a0d8e030e5281018baa763438eb7ad02aee76f7ba3cbc0b151f` |
| base.ndjson SHA-256 | `bb9e77c13790897155f847fa3a7ed48067930cf08257e227bee60bf96c91a168` |
| supplementary.ndjson SHA-256 | `edeed1435d707912dedd08d642cf1088e669cf4dc329ea3248a289be3e91c8e4` |
| Extraction | release 73,631,092 · bbox 1,245,925 · base 52,566 · supplementary 2,962 (228 rescue candidates, 2,734 diagnostic) · matcher-analysis 55,528 (**not** a corpus count) |
| Planned load | **52,716 places** (52,566 base + 150 rescued) · **11,082 memberships** · 47,639 open / 5,077 unknown |
| Never stored | 2,734 diagnostic rows and 78 refused rescue candidates — `matcher_only`, counted in the ledger only |
| v1 (must not change) | `overture-2026-06-17.0-fl`, 29,434 rows, the one `active` overture-places corpus |

## 1. Why this shape

The merged tools refuse production and CLAUDE.md forbids adding a production override to anything
that writes. The Replit workspace carries the application database's production signals
(`PGHOST=helium`, `DATABASE_URL`, `.env` `APP_ENV=production`), so the tools refuse there —
correctly. `docs/spatial/overture-v2-corpus-schema.md` §6 already states the answer: *a real load
must be run from a shell that carries no production signals.*

So the tools run **unchanged** from a shell that genuinely carries none: a GitHub Actions runner
under the protected Environment `overture-v2-spatial` (primary), or a dedicated operator container
(fallback, §6). Such a shell has no application database, no helium credential — and no `APP_ENV`
of its own. Because `config/app.php` **defaults an unset `APP_ENV` to `production`**, the jobs that
run artisan **declare `APP_ENV=operator`**, in reviewed YAML: a true statement of what that process
is (an operator runner, not the production application), not a value changed to get past a guard.
Nothing inherited is unset or masked, no production override is added, and no production code
changes; with that declaration the tools' guards pass on what is actually true of the runner. The
operator container of §6 makes the same declaration, for the same reason. This follows the house Class-2 pattern (v1:
`spikes/phase-2-batch-2c-overture-import-framework/`): a committed runbook, guarded read-only SQL
checks run by `psql`, explicit human gates, and verification before and after every write. What
differs from v1 is that the write itself is the existing importer's, so its run token,
`preparing → ready | failed` lifecycle, single transaction, database recounts, same-files no-op and
changed-files refusal are reused exactly rather than re-implemented in SQL.

## 2. Target identity (the fingerprint)

`bin/preflight.sh` refuses any `SPATIAL_DATABASE_URL` that has no explicit host (libpq would fall
back to an ambient `PGHOST`), is not on `*.db.postgresbridge.com`, or names `helium` / `heliumdb`.
It then proves identity by fingerprint:

```
sha256( <host> | <port> | current_database() | <v1 overture-places ledger started_at, UTC, µs> )
= 835261fd2b754974525e2963cd97471776a2a2d45e8062374ae4a17ece965b5f
```

Recorded from the 2026-09-24 read-only audit. Two of its inputs come from the SERVER
(`current_database()` and the v1 ledger row's creation time) and two from the URL the client was
given (host and port), so it proves "the host we asked for serves the database whose v1 ledger row
was created at that instant". A different cluster, a different database or a copy served under a
different host fails it; a restored copy served under the SAME hostname would pass it, so the
preflight's v1 and ledger checks (P05–P08) and the reviewer's reading of the log remain the guard
against that case.
It hashes non-secret identity only — rotating the
password does not change it — and no hostname or credential is committed. A different cluster, a
restored copy or a changed v1 ledger row all fail `P01`. **If P01 fails, STOP: do not edit the
expected value to make it pass.** Investigate, and change it only through a reviewed commit that
explains why the target legitimately changed.

## 3. One-time setup of the protected Environment (repository admin)

Done in GitHub settings, not in this repository — so verify it by eye before the first run:

1. **Settings → Environments → New environment** `overture-v2-spatial`.
2. **Required reviewers:** at least one named person; enable **Prevent self-review**.
   **If the Required reviewers rule is not available** on this repository's GitHub plan (for a
   private repository it may require GitHub Enterprise), **STOP — run no stage**: without it the
   approval gate this procedure depends on does not exist. Use §6 instead only after a separate
   decision about how its writes are approved.
   **The reviewer must be a different GitHub account from the one that dispatches the run.** With
   *Prevent self-review* on, the dispatcher cannot approve its own run; with it off, the approval
   gate is the dispatcher agreeing with itself. A repository with a single collaborator therefore
   cannot use this path — STOP and use §6 (the position on 2026-09-24: one collaborator).
3. **Deployment branches and tags:** *Selected branches* → `main` only.
4. **Environment secret** `SPATIAL_DATABASE_URL` = the Crunchy spatial connection URI, with an
   explicit host and `sslmode=require`. **Environment secret only** — never a repository or
   organization secret, so no other workflow and no job without this environment can read it.
5. **No other secret or variable** in this environment. In particular no `DATABASE_URL`, no `DB_*`,
   no `PG*`, no application or helium credential of any kind. The URL may carry only `sslmode`
   (`require` or stronger), `connect_timeout` and `application_name` — `bin/preflight.sh` refuses any
   other parameter, because `host=` / `hostaddr=` / `options=` would reroute the connection.
6. Confirm Actions may not approve their own runs and that no bypass list is configured.
7. **Recommended:** give the preflight and verify steps a separate **read-only database role**. The
   SQL files make their own sessions read-only, but only a role makes that enforcement rather than
   a promise the files keep (the test scans them for anything that could undo it).

**This repository is public, so its Actions logs are world-readable.** GitHub masks a secret's
whole value, but a failed connection prints the host, its address and the username on their own.
Every job that reads the secret therefore runs `bin/mask_log_identity.sh` first, which registers
those values with the runner's `::add-mask::` command (and does nothing outside Actions), and
`bin/preflight.sh` never prints `psql`'s raw error text — a connection failure is a fixed message,
and a failed check shows only the committed script's own error line.

**Do not change the Crunchy Bridge network allowlist from this procedure.** If runners cannot reach
the cluster, §6 applies.

## 4. Full-size rehearsal — REQUIRED before any write stage

The write stages refuse until `REHEARSAL.md` records a passed rehearsal of **this code** (the gate
also diffs `app/ config/ database/migrations/spatial/ composer.lock scripts/overture-v2/` and this
directory's `bin/ sql/` between the rehearsed commit and the commit being run).

**Where.** A **throwaway** PostgreSQL 16 + **PostGIS 3.6.x** instance — preferably 3.6.3, matching
live (e.g. a `postgis/postgis:16-3.6` container, or a local build; confirm with
`SELECT postgis_full_version();`). **Never the live spatial cluster. Never a reduced dataset.** Run
from a shell with no production signals (a GitHub Actions job with a PostGIS service container and
no secrets, or the operator container of §6), with `SPATIAL_DATABASE_URL` pointing at the scratch
instance.

**What it must exercise** (record each result in `REHEARSAL.md` → Evidence):

1. **Schema.** Apply the eleven v1 core migrations to the scratch instance, then seed a v1 fixture
   under the real name — either the v1 pipeline, or a synthetic `places_p_overture_2026_06_17_0_fl`
   partition plus an `active` `overture-places` `corpus_imports` row; record which. Then apply the
   **three v2 migrations with the exact command of §7** (one batch). Take `sql/v1_snapshot.sql`
   before the v2 migrations.
2. **Full import.** The real extraction (§8 steps 1–3, byte-identical SHA-256s), the dry run, then
   `--write --database=pgsql_spatial`. Expect `IMPORTED`.
3. **Reconciliation.** `sql/verify_v2_load.sql` → every `PASS`, `VERIFY OK` (52,716 places, 11,082
   memberships, per-category, per-chain, integrity, no activation).
4. **Idempotency.** The identical import again → `ALREADY READY`; the `LEDGER` line unchanged.
5. **Fidelity.** `sql/sample_fidelity.sql` → `bin/compare_sample.py` → `SAMPLE FIDELITY OK`.
6. **v1 untouched.** `sql/v1_snapshot.sql` after → `diff` with the before snapshot is empty.
7. **Recovery.** On a fresh scratch database (or after the rollback in step c):
   a. terminate the importer's backend mid-insert (`pg_terminate_backend`) → the corpus is
      `preparing` or `failed` with **zero** places; the identical rerun then imports cleanly;
   b. roll back the v2 batch with the three `--path` values (§7 rollback) → only the three v2
      tables are dropped, `v1_snapshot` unchanged;
   c. re-apply the three migrations and re-import → `VERIFY OK` again.

Then fill the gate block in `REHEARSAL.md` (every field, `rehearsal_status: PASSED`,
`rehearsed_commit` = the full SHA you ran) and land it through a reviewed PR.

## 5. Stage `preflight` — READ-ONLY (the first, and currently the only, usable stage)

**Actions → Overture v2 operator load → Run workflow** (branch `main`):

- `stage`: `preflight`
- `commit_sha`: the full 40-hex SHA of current `main`
- leave every `confirm_*` input empty

A reviewer approves the `preflight` job (the Environment pauses it). It proves, writing nothing:

| Check | Proves |
|---|---|
| connect | the runner can reach Crunchy Bridge (exit 4 = cannot connect → §6) |
| host guard | explicit host, `*.db.postgresbridge.com`, not helium / heliumdb |
| P00 | the session is read-only |
| P01 | the fingerprint matches §2 |
| P02 | not the application database |
| P03 / P04 | PostgreSQL 16; PostGIS 3.6.x (versions printed, no credential) |
| P05 / P06 | v1 `overture-2026-06-17.0-fl` is the one active overture-places corpus, 29,434 in the ledger and in `places` |
| P07 | no `overture_v2_*` table exists |
| P08 | the migration ledger is exactly the eleven applied core migrations — the two August address migrations are still pending |
| file guard | the repository's spatial migrations are exactly the known sixteen; the only write-phase candidates are the three `2026_09_24` v2 migrations |

`PREFLIGHT PASSED` is the whole output of this stage. Record the run URL.

## 6. Connectivity failure — STOP, then the operator-container fallback

If `preflight` exits **4** (`CONNECTIVITY FAILED`): **stop.** Do not change the Crunchy allowlist,
do not retry with a different credential, do not move the secret elsewhere. Report the run.

The fallback is a **dedicated operator container or machine**, never the Replit workspace:

- a clean checkout of the pinned SHA; PHP 8.2 (`pdo_pgsql`, `intl`), Composer, `psql`, Python 3.11;
- **only** `SPATIAL_DATABASE_URL` in its environment, plus the same declared `APP_ENV=operator`,
  `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` as the workflow jobs — no `DATABASE_URL`, `DB_HOST`,
  any `PG*`, no `REPLIT_DEPLOYMENT` (`bin/preflight.sh` refuses otherwise; do not work around a
  refusal by unsetting anything — use a clean shell);
- its egress address allowed by Crunchy through the normal, separately-approved infrastructure
  process — not from this procedure.

Then run exactly the commands of the corresponding workflow job, in the same order, with the same
gates applied **by hand**: `REHEARSAL.md` PASSED for this code, the typed confirmations of §10, and
a second person reviewing each write. Record every command and its output.

## 7. Stage `migrate` — WRITE (disabled until §4)

Dispatch with `stage: migrate`, the pinned `commit_sha`, and exactly:

- `confirm_fingerprint`: `835261fd2b754974525e2963cd97471776a2a2d45e8062374ae4a17ece965b5f`
- `confirm_migrations`: `2026_09_24_000001,2026_09_24_000002,2026_09_24_000003`
- `confirm_no_activation`: `THIS DOES NOT ACTIVATE OVERTURE V2`

Sequence: `gate` (confirmations, rehearsal evidence) → `preflight` (`v2_state=absent`, reviewer
approval) → `migrate` (reviewer approval):

1. `v1_snapshot.sql` → before.
2. `migrate --pretend` with the three paths — the exact DDL, printed for the reviewer.
3. `migrate --database=pgsql_spatial` with **exactly** the three `--path` values
   (`…000001_spatial_overture_v2_create_corpora.php`, `…000002_…_create_places.php`,
   `…000003_…_create_chain_memberships.php`) → one new batch holding only them.
4. `preflight.sh --v2-state=empty` → three empty v2 tables; ledger = the eleven + the three.
5. `v1_snapshot.sql` → after; `diff` must be empty.

**Never** `--path=database/migrations/spatial` (that would also apply the two August address
migrations), never `--step`, never a rollback without the three paths. (`docs/spatial/overture-v2-corpus-schema.md`
§6 mentions `--step=3` for a database migrated in one batch; this cluster's v2 migrations are
applied as their own batch, so the path-scoped rollback below is the only one this procedure uses.)
The `migrate` job re-runs the read-only preflight inside itself immediately before writing, because
the reviewer's approval may have waited long after the first preflight.

**Rollback** (only if a v2 migration must be undone; the tables are empty at this point):
```
php artisan migrate:rollback --database=pgsql_spatial \
  --path=database/migrations/spatial/2026_09_24_000001_spatial_overture_v2_create_corpora.php \
  --path=database/migrations/spatial/2026_09_24_000002_spatial_overture_v2_create_places.php \
  --path=database/migrations/spatial/2026_09_24_000003_spatial_overture_v2_create_chain_memberships.php
```
Each migration runs in its own transaction: if **1** fails nothing was applied — diagnose and rerun;
if **2** or **3** fails the earlier ones stay applied and empty — fix and rerun the same command
(every statement is `IF NOT EXISTS`), or roll back with the three paths. This rollback is a manual,
reviewed operator action; the workflow never performs it.

## 8. Stage `import` — WRITE (disabled until §4 and §7)

Dispatch with `stage: import`, the pinned `commit_sha`, `confirm_fingerprint` (as §7),
`confirm_corpus_version`: `overture-2026-08-19.0-fl-r2`, `confirm_no_activation` (as §7).

Sequence: `gate` → `preflight` (`v2_state=empty`, approval) → `import` (approval):

1. DuckDB 1.5.5 runs `scripts/overture-v2/count_release_rows.sql` and `extract_bbox_raw.sql`
   (anonymous public bucket) → must be 73,631,092 and 1,245,925. Overture keeps releases in the
   public bucket for a limited time: **if `2026-08-19.0` is no longer readable, STOP** — a different
   release is a different corpus and needs its own contract, extraction and rehearsal.
2. `corpus:extract-overture-v2` (unchanged).
3. Both output SHA-256s must equal the contract — read from `config/overture_v2_corpus.php`.
4. Dry run (`corpus:import-overture-v2` without `--write`; opens no connection) → `VALIDATED`.
5. The read-only preflight again (`v2_state=empty`) immediately before the write, then
   `v1_snapshot.sql` → before.
6. `corpus:import-overture-v2 --write --database=pgsql_spatial` → `IMPORTED`.
7. `verify_v2_load.sql` → `VERIFY OK`.
8. The identical import again → `ALREADY READY`; verify again; exactly one `LEDGER ready` line,
   unchanged.
9. `sample_fidelity.sql` → `compare_sample.py` → `SAMPLE FIDELITY OK`. (It compares each sampled
   place field by field with the NDJSON and each membership's registry pins with the contract; the
   membership totals themselves are proven by V08–V11.)
10. `v1_snapshot.sql` → after; `diff` must be empty.

The extraction files live only in the runner's temporary directory and are discarded with it:
never committed, never uploaded.

## 9. Stage `verify` — READ-ONLY

`stage: verify` re-runs `preflight` (`v2_state=loaded`), `verify_v2_load.sql`, the sample and the v1
snapshot against a completed load, at any time.

## 10. Human confirmation gates

| # | Fact | Where it is enforced |
|---|---|---|
| 1 | Target is the Crunchy spatial cluster, not helium | `preflight.sh` host guard + P02; reviewer reads the preflight log |
| 2 | Exact fingerprint | P01; typed `confirm_fingerprint` for writes |
| 3 | v1 healthy | P05 / P06; `v1_snapshot` before/after diff |
| 4 | Only the three v2 migrations | typed `confirm_migrations`; P08; reviewer reads the `--pretend` DDL |
| 5 | Artifact hashes and counts match | extraction step checks; the importer's own gate |
| 6 | Corpus version | typed `confirm_corpus_version`; the importer's contract |
| 7 | Activation is not part of this | typed `confirm_no_activation`; V12 |
| 8 | Code = rehearsed code | the `gate` job's diff against `rehearsed_commit` |
| 9 | A second person approves each write job | the Environment's required reviewers |

## 11. Recovery

| Situation | Safe action | Manual step? | Never |
|---|---|---|---|
| Artifact count / SHA-256 differs | nothing was written; STOP and investigate the source | yes | relax a contract value to make it match |
| A v2 migration fails | §7 | diagnose | a plain spatial rollback, `--step` |
| Import fails before any place row | rerun the identical import (a new run token re-arms the row) | no | hand-edit `overture_v2_corpora` |
| Import fails after partial inserts | the transaction rolled back; the row is `failed` with a reason; rerun | no | touch any v1 row |
| Connection drops mid-import | PostgreSQL aborts the transaction; the row may stay `preparing`; rerun | no | set `status` by hand |
| Corpus stuck `preparing` / `failed` | rerun the identical import — it proves zero places inside its transaction | read `failure_reason` first | insert places by hand (the importer then refuses forever) |
| Load rerun by accident | `ALREADY READY`, nothing written | no | re-import changed files into the same version (a hard refusal; a new corpus is a new contract entry) |
| `main` changed between rehearsal and load | the `gate` job refuses if load-relevant code changed | re-rehearse | reuse evidence for different code |

Never deleted or changed by any recovery: the v1 partition and ledger row, `corpus_imports`,
`place_categories`, `place_category_mappings`, a `ready` v2 corpus, and the two pending August
address migrations.

## 12. What this procedure never does

Activate v2 · set any `OVERTURE_CORPUS_POI_*` variable · change `location_providers` /
`overture_corpus_poi` · write `corpus_imports` · touch v1 · apply the August address migrations ·
materialize or de-duplicate sites · build brand queries · deploy. The v2 schema has no `active`
state; activation is a separate, reviewed decision.
