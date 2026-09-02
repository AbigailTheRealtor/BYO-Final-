# Deployment (G4.5)

How this application reaches production, who applies migrations, and the one
environment question that is still open.

## The processes

Production runs **two independent processes**. They are configured separately
and neither restarts the other.

| Process | Started by | Command |
|---|---|---|
| Web | `.replit` → `[deployment] run` | `bash deploy/start-production.sh` |
| Scheduler | manual Replit setup (see `SCHEDULER.md`) | `bash deploy/scheduler.sh` |

A **supervisor restart** of the web process is a third start path and a different
event from a deployment — see "Two entry points" below.

## The canonical serving worktree

The port-5000 workflow serves from exactly one path:

```
/home/runner/workspace/.worktrees/production-serve
```

**CURRENT — what the committed configuration targets.** This path is pinned in
`.replit`, so it is reviewable in git rather than living only on a disk somewhere.
`CanonicalServingWorktreeTest` asserts it, and asserts that the workflow never
again points at a transient tree.

### Why a dedicated worktree at all

The workflow used to serve from `$PWD` — whatever directory the supervisor
started in, which is the repository root, a development workspace that routinely
sits on a feature branch. It had also been hand-edited on disk to serve from
`.worktrees/postmerge-validate-current-main`, a tree whose name declares it a
disposable post-merge validation checkout. That edit was in no commit, so nothing
reviewed it and nothing would restore it.

Neither is a production target. One follows whoever last checked out a branch at
the root; the other is a scratch tree sitting among dozens of siblings, and a
single cleanup pass over stale validation worktrees would have deleted it.

### How the canonical worktree behaves

| Property | Rule |
|---|---|
| HEAD | **detached**, pinned to an explicit approved deploy SHA |
| Branch | none — never on a branch, never follows `main` |
| `git pull` | never |
| Lock | `git worktree lock` while it is serving |
| Purpose | production serving only |
| Feature work | never |
| Tests | never run inside it |
| Composer | never experimented with inside it |
| Promotion | explicit `git checkout --detach <approved-sha>` |
| Code rollback | re-pin the previous known-good SHA, then restart the supervisor workflow |
| Schema rollback | **not automatic** — restore from a dump under `.ops-backups/`, as a deliberate incident procedure |
| After a supervisor restart | must come back to this same canonical path |

Detached rather than a branch on purpose: production identity is *which commit is
live*, not which branch. A branch invites accidental commits and a stray `pull`;
a detached SHA makes "what is running" unambiguous and makes promotion an
explicit, reviewable act. The lock is the concrete defence against a worktree
cleanup removing production.

Backups stay under `/home/runner/workspace/.ops-backups/` — never `/tmp`, which
does not survive a container restart.

### Not yet done

Committing this path does **not** switch production to it. Creating the
`production-serve` worktree and repointing the live supervisor is a separate,
controlled deployment event.

## Two entry points: deployment vs supervisor restart

Two different events start a web server, and they are not the same event.

| | Explicit deployment | Supervisor restart |
|---|---|---|
| Triggered by | `.replit` → `[deployment] run` | port-5000 workspace workflow |
| Script | `deploy/start-production.sh` | `deploy/start-serving.sh` |
| Ships a new commit | yes | **no** — the commit is already pinned |
| Deploy lock | `acquire_deploy_lock`, 30s bound | same lock, **180s** bound |
| Preflight report | yes (`deploy:preflight`) | no |
| Migrations | **applies them** | **never** |
| Schema check | migration *is* the check | `deploy:migrations-pending`, read-only |
| Records deploy SHA | yes, after migrations succeed | **never** |
| Ends with | `exec php artisan serve …` | `exec php artisan serve …` |

### Why a restart may not migrate

The supervisor restarts the port-5000 process for reasons that have nothing to do
with shipping: a container restart, a workflow re-run, someone pressing the
button. Before this, that path ran a bare `php artisan serve`, which will happily
serve new code against an old schema — the exact failure the rest of this
document exists to close.

The obvious fix, pointing the restart at `start-production.sh`, is worse: it
would make **migrating production a side effect of a container restart**, at a
moment nobody chose and in a log nobody is reading. Migration is a deployment
decision. A restart is not entitled to make it.

So the restart verifies instead, and refuses rather than improvising.

### The readiness gate

```
php artisan deploy:migrations-pending
    0  ready         every migration this code ships is applied
    1  pending       at least one is not
    2  undetermined  the answer could not be established
```

The **exit code is the contract**; nothing parses the command's text. That is not
a stylistic preference — it is the only option available:

- `migrate:status` cannot gate anything on Laravel 8. Its handler renders a table
  and returns `null`, so Symfony exits `0` whether nothing is pending or
  everything is. It exits non-zero for exactly one condition, a missing
  `migrations` table, and this Laravel version has no `--pending` option.
- `deploy:preflight` always exits `0` by design — it reports, it does not gate,
  and `start-production.sh` even calls it with `|| true`.

`deploy:migrations-pending` (merged in PR #113) exists for this call site. It is
read-only: two `SELECT`s, no writes, no migration, no filesystem change.

**Anything that is not `0` refuses to serve**, including exit codes nobody has
defined yet. A restart that serves while the schema state is unknown is, from
outside, indistinguishable from one that serves while the schema is known-bad.

### Why the restart waits longer than a deployment

`DEPLOY_LOCK_TIMEOUT` defaults to **30s** in `start-production.sh` and **180s**
in `start-serving.sh`, and the asymmetry is deliberate. A deployment queued
behind another deployment is an outage with extra steps, so it should fail fast
and be visible. A restart queued behind a deployment is simply a restart that
ought to wait for the deploy to finish — waiting is the correct answer, and 180s
covers a normal migration run while staying bounded.

Both take the **same** `flock`, through the same `deploy/lib/deploy-state.sh`, so
a restart cannot read a half-applied schema mid-deployment.

### The deploy SHA is a deployment record, not a restart record

`start-serving.sh` never calls `record_deploy_sha` and never writes
`.ops-backups/current-deploy-sha`. Exactly one script writes that file, and a
test asserts the inventory.

> **Only an explicit, controlled deployment may repin production to a new SHA.**
> An ordinary supervisor restart merely serves the already-pinned, schema-ready
> commit.

Enforcing that at runtime — comparing the worktree's HEAD against the recorded
SHA and refusing on a mismatch — is **not implemented** and is deliberately out
of scope here. For now the invariant is carried by the fact that nothing on the
restart path can change either value.

### What `start-serving.sh` must never do

`migrate` in any form; write the deploy SHA; terminate whatever currently holds
port 5000 (the supervisor owns that process, and a script reaching for its own
predecessor races the supervisor's own restart logic); background the server.
`DeployStartServingTest` asserts each of these, and runs the real script against a
fake `php` to prove that a non-zero readiness result actually stops it reaching
`serve`.

## The production environment contract

**Every production Laravel process sets its own environment before Laravel boots.**

| Process | Script | Sets |
|---|---|---|
| Web (deployment) | `deploy/start-production.sh` | `APP_ENV=production`, `APP_DEBUG=false` |
| Web (supervisor restart) | `deploy/start-serving.sh` | `APP_ENV=production`, `APP_DEBUG=false` |
| Scheduler | `deploy/scheduler.sh` | `APP_ENV=production`, `APP_DEBUG=false` |

Set **unconditionally** — never `${APP_ENV:-production}`. A `:-` default keeps
whatever the parent already supplied, and *a parent value being present and wrong*
is exactly the situation this exists for.

### What went wrong

`.replit` declared `APP_ENV = "local"` and `APP_DEBUG = "true"` under
`[userenv.shared]`, with no production override, and the live web process was
verified running with both. `APP_DEBUG=true` renders full stack traces — including
database credentials and environment values — to anyone who triggers an error.

Note that `config/app.php` was never the problem:

```php
'env'   => env('APP_ENV', 'production'),
'debug' => (bool) env('APP_DEBUG', false),
```

Absent those variables the framework already defaults to production/false. The
danger was not a missing default — it was an **ambient value that was present and
wrong**, supplied by a shared, development-oriented setting that nothing on the
deployment path overrode.

### Why exporting is enough

`Illuminate\Support\Env` builds its repository with `$builder->immutable()`, and
on phpdotenv v5 an immutable repository will not overwrite a variable already
present in the process environment. So a variable exported by the start script
beats the same key in `.env`. Verified, not assumed — see
`ProductionRuntimeEnvironmentTest`, which boots a real Laravel process with
hostile parent values and reads `config('app.debug')` back.

### Why `[userenv.shared]` no longer declares them

Shared userenv reaches development and workspace shells too, so it is the wrong
authority in **both** directions: `local`/`true` endangers production, and
`production`/`false` would push every development shell into production mode. The
keys belong to neither — the entrypoints own them, and development keeps using its
own `.env`.

`.env.example` still reads `APP_ENV=local` / `APP_DEBUG=true` and is **unchanged
on purpose**. It is a development template; nothing in `.replit`, `deploy/` or
`scripts/` consumes it as production configuration.

## Configuration cache policy

**The production deployment build does NOT run `php artisan config:cache`.**

It used to. `[deployment] build` ended with `config:cache`, `route:cache`,
`view:cache`, and the build runs *before* `[deployment] run` invokes the start
script — so a cache always existed by the time anything exported anything.

That makes the environment contract above completely inert:

- `LoadEnvironmentVariables::bootstrap()` returns early when
  `configurationIsCached()`, so **`.env` is never loaded at all**;
- `LoadConfiguration::bootstrap()` `require`s the cached array and skips
  `loadConfigurationFiles()`, so **`config/app.php` is never re-evaluated** — every
  `env()` call was already resolved at BUILD time.

Demonstrated rather than argued:

```
$ env APP_ENV=local APP_DEBUG=true   php artisan config:cache
$ env APP_ENV=production APP_DEBUG=false php -r '<boot>; echo config("app.debug");'
  config(app.env)=local  config(app.debug)=true      ← export ignored

$ php artisan config:clear
$ env APP_ENV=production APP_DEBUG=false php -r '<boot>; …'
  config(app.env)=production  config(app.debug)=false ← export honoured
```

Had the exports shipped while the build still cached, the change would have passed
every shell-level test, passed a fresh-process Laravel test (a fresh worktree has
no cache), and still served `APP_DEBUG=true` to visitors. That is worse than the
original bug, because it would look solved.

**Correctness takes precedence over the boot-time optimisation.** Config caching
may be reintroduced later, in a separate PR, and only once the *build phase* has a
proven production-scoped environment contract of its own — that is, only once we
can show what the build bakes in. `route:cache` and `view:cache` are unaffected and
remain in the build; neither freezes `env()` values.

`ProductionRuntimeEnvironmentTest` asserts no production path creates
`bootstrap/cache/config.php`. `scripts/post-merge.sh` still runs `config:clear`;
that is the Replit **workspace** hook, it does not fire on deploy, and clearing is
not creating.

### Not yet applied to the running process

This is a change to committed configuration only. **The live process has not been
restarted and still runs `APP_ENV=local` / `APP_DEBUG=true`.** The values above
take effect on the next controlled deployment or supervisor restart of the
canonical worktree — a separate, deliberate promotion event. Do not read this
section as describing what is currently serving.

## Migrations: the web start script owns them

`deploy/start-production.sh` is the **only** thing that migrates:

```
deploy:preflight   (report; never fails the deploy)
      ↓
migrate --force --no-interaction
      ↓  (only if that succeeded)
serve
```

**Nothing else may run migrations.** Not the scheduler, not the build phase, not
a second web process, and **not the Replit `[postMerge]` hook**.

### The `[postMerge]` hook does not migrate

`scripts/post-merge.sh` — the script Replit's `[postMerge]` hook runs — used to
call `php artisan migrate --force --no-interaction`. It no longer does, and must
not again.

That hook fires on a **platform merge in the workspace**, from whatever branch
happens to be checked out there, with no backup, no preflight and no lock. It is
not the deployment path. A migration applied that way changes the production
schema at a moment nobody chose, from a tree nobody reviewed.

It still refreshes dependencies, assets and caches. It simply may not alter the
schema. Migrations happen during an **explicit deployment**, never as a side
effect of a merge.

### Why single ownership matters more here than usual

This application is on **Laravel 8**, which has **no `migrate --isolated`** —
the built-in migration lock arrived in Laravel 9. There is no mutex, so two
processes migrating concurrently is a real race with no framework protection.
Safety comes entirely from exactly one process owning the job.

That is the whole reason the `[postMerge]` migration had to go: it was a second
owner, and there is no lock underneath it to make a second owner survivable.

`DeploymentMigrationReadinessTest` asserts the scheduler contains no
`artisan migrate`, that no other `deploy/*.sh` does, and that the build phase
does not. `PostMergeMigrationOwnershipTest` covers the hook: it resolves the
script path out of `.replit` rather than assuming one, ignores commented lines,
and asserts positively that `deploy/start-production.sh` still owns the job.

### Current state vs planned hardening

**CURRENT — implemented and asserted by tests:**

| Control | Where | Notes |
|---|---|---|
| Single migration owner | `deploy/start-production.sh` | `[postMerge]` no longer migrates |
| Exclusive deploy lock | `flock` on `.ops-backups/deploy.lock` | bounded wait, fails closed |
| Recorded deploy SHA | `.ops-backups/current-deploy-sha` | written after migrations succeed |
| Persistent backups | `.ops-backups/` | never `/tmp` |
| Startup ordering | preflight → migrate → record → serve | a failed migration never reaches `serve` |
| Non-mutating restart path | `deploy/start-serving.sh` | same lock, read-only readiness gate, never migrates |
| Restart readiness gate | `deploy:migrations-pending` | fail-closed: only exit `0` may serve |
| Single deploy-SHA writer | `deploy/start-production.sh` | the restart path never repins production |
| Production environment contract | all three entrypoints | `APP_ENV=production`, `APP_DEBUG=false`, set unconditionally |
| No build-time config cache | `[deployment] build` | a cached config would freeze build-time `APP_DEBUG` |
| Code rollback | re-pin previous SHA, restart | schema rollback is separate and manual |

Single ownership stops a *second script* migrating. It never stopped *this
script* running twice — a redeploy overlapping a restart, or two deploys
triggered close together. The lock is the mechanism Laravel 8 does not provide.

**PLANNED — not yet live. Do not read these as done:**

- **Automated post-start smoke check.** See "What verifies a start" below — not
  implemented, deliberately.
- **Canonical `production-serve` worktree and the serve-only wiring** (PR #110).
  The committed `.replit` change and `deploy/start-serving.sh` are open but
  unmerged, the worktree does not exist, and production still serves from
  `.worktrees/postmerge-validate-current-main` via a hand-edited local `.replit`.
  Nothing in this PR switches live traffic.
- **HEAD-vs-recorded-SHA enforcement on restart.** Not implemented. The restart
  path cannot change either value, but it does not yet refuse a worktree whose
  HEAD disagrees with `current-deploy-sha`.
- **`APP_DEBUG=false` on the RUNNING process.** The contract is now committed (see
  "The production environment contract"), but the live process has not been
  restarted and still runs `APP_ENV=local` / `APP_DEBUG=true`. It changes at the
  next controlled promotion, not before.

### What verifies a start, and what does not

The start script guarantees **ordering**: preflight runs, migrations apply, the
SHA is recorded, and only then is the port bound. `set -euo pipefail` means a
failed migration exits non-zero without ever reaching `serve`, so a broken
release fails to start rather than serving new code against an old schema.

The platform verifies **that the port binds and the process stays up**. There is
no `healthCheckPath` configured under `[deployment]`, and `waitForPort = 5000`
belongs to the workspace workflow, not the deployment.

Nothing verifies that the application *answers correctly* after boot — for either
entry point. That gap is known and **still not implemented**; do not read the
readiness gate as a smoke check, because it inspects the schema and never issues
a request. Both scripts end with

```
exec php artisan serve …
```

and `exec` replaces the shell, so no command can run after it. Adding a smoke
check would mean backgrounding the server, polling it, and hand-forwarding
SIGTERM/SIGINT — changing the process-supervision model so the supervisor no
longer talks to the server directly. Getting that subtly wrong orphans the
production process or double-binds the port. A missing smoke check is a smaller
problem than a mis-supervised production server, so it stays a follow-up rather
than a rushed addition.

### The deploy lock

```
flock -w ${DEPLOY_LOCK_TIMEOUT:-30}  →  .ops-backups/deploy.lock
```

Held on file descriptor 9 for the critical section — preflight, migration, SHA
recording — and **released before `exec`**. Holding it across `exec` would leave
the serving process owning the lock for its whole life, so no later deploy could
ever acquire it.

Bounded on purpose: a deploy queued forever behind a stuck one is an outage with
extra steps. After the timeout it refuses and exits non-zero.

`DEPLOY_STATE_DIR` relocates the lock and SHA record; it exists so tests can use
a temporary directory. Production leaves it unset.

### The recorded deploy SHA

`.ops-backups/current-deploy-sha` holds the commit this release is serving,
`0600`, written atomically (temp file + rename) so a reader can never see a
half-written value.

It is written **after** migrations succeed, never before. Recording earlier would
name a SHA that never reached a healthy schema — a rollback target that was never
good. If the SHA cannot be determined the deploy refuses to start: `DEPLOY_SHA`
overrides the lookup for environments shipped without a `.git` directory.

### Why migrations run at start and not at build

The build phase runs before the runtime environment is assembled and is not
guaranteed to hold database credentials. It is also the wrong moment: the schema
must be current when *this release starts serving*, not when its artifact was
compiled.

### Why a failed migration must stop the deploy

`set -euo pipefail` plus ordering means a failed migration never reaches
`serve`. The deployment fails its health check instead of starting.

That is deliberate. A half-migrated deployment that answers requests is worse
than one that never starts: it looks healthy while rejecting or corrupting
writes. Failing loudly is recoverable; serving against the wrong schema quietly
is not.

### Re-running is safe

Laravel skips migrations already recorded in the `migrations` table, so restarts
and crash-loops replay nothing.

### Never in a start command

`migrate:fresh`, `migrate:reset`, `migrate:refresh`, `db:wipe`. These destroy
data. A test asserts none of them appear in the start script.

## How this was missed before

A migration hook did exist — `scripts/post-merge.sh` **used to** run
`migrate --force`. But it is wired to Replit's `[postMerge]` hook, so it fired
when a merge happened **in the workspace**, not on deploy and not when a pull
request was merged on GitHub. (That call has since been removed; see "The
`[postMerge]` hook does not migrate" above.)

The one mechanism that migrated was the one that does not run on the path we
actually ship through. G4's provenance migration reached `main`, was released,
and was still absent from the deployed schema, with nothing reporting it.

That is also why `ProvenanceSchemaReadiness` exists as a second line of defence:
the deploy step makes the schema correct, the guard makes being wrong
survivable, and neither substitutes for the other.

## Environment mode — RESOLVED IN CONFIG, still open at the platform level

**Resolved:** `.replit` no longer declares `APP_ENV` or `APP_DEBUG` under
`[userenv.shared]`, and all three production entrypoints now set
`APP_ENV=production` / `APP_DEBUG=false` unconditionally before Laravel boots.
See "The production environment contract" above. The committed configuration can
no longer put a production process into debug mode.

**Still open, and worth keeping:** whether a Replit VM deployment inherits
`[userenv]` at all is still undocumented — Replit's published `.replit` reference
does not mention it. That question no longer decides whether production runs in
debug mode, because the entrypoints override whatever they inherit. It still
matters for two things:

1. **Anything else `[userenv.shared]` supplies** — the database connection among
   them — is inherited on the same unknown terms.
2. **The build phase**, which runs before any entrypoint and inherits the same
   unknown environment. That is a second reason `config:cache` was removed: a
   build that bakes an unknown environment into a frozen config array is a
   contract nobody can state. See "Configuration cache policy".

**What used to be the danger, recorded for history:** `[userenv.shared]` declared
`APP_ENV = "local"` and `APP_DEBUG = "true"` with no production override, and the
live process was verified running with both. `APP_DEBUG=true` renders full stack
traces to visitors, including database credentials and environment values.

### How to settle it

`deploy:preflight` prints `APP_ENV` and `APP_DEBUG` at the top of every deploy
and warns when debug is on. **Read the next deployment log.** One line answers
it permanently:

```
── deploy preflight ────────────────────────────────
  APP_ENV        local
  APP_DEBUG      true
  DB_CONNECTION  pgsql
  DB_DATABASE    <name>
  pending migrs  0
  provenance     ready
────────────────────────────────────────────────────
```

With the contract in place, a deployment log should now read `APP_ENV production`
/ `APP_DEBUG false`. **Read the next deployment log to confirm it.** If it still
shows `local` / `true`, the entrypoint is not the process that started the
application — investigate the platform's run command before changing anything
else, because that would mean something is starting Laravel outside
`deploy/start-production.sh`.

The same line also answers question (1) above, since it prints the resolved
database name.

The preflight never prints credentials, and a test asserts that.

## Verifying

```bash
php artisan deploy:preflight     # environment, database, pending count, schema state
php artisan migrate:status       # what has and has not run
bash deploy/start-production.sh  # the full production start sequence, locally
```
