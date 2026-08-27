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

**Current, on `main` today:** exactly one migration owner —
`deploy/start-production.sh`. The `[postMerge]` hook's `migrate` call has been
removed, so the platform's merge action no longer touches the schema.
Concurrency safety rests on that single ownership and on nothing else.

**Planned, not yet implemented:** an explicit deploy lock (e.g. `flock`), a
recorded deploy SHA, and a post-boot smoke check. None of those exist today.
Single ownership is currently the only protection, so treat "only one thing
migrates" as load-bearing rather than as a tidy convention.

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

## Environment mode — OPEN QUESTION

`.replit` declares, under `[userenv.shared]`:

```
APP_ENV   = "local"
APP_DEBUG = "true"
```

There is **no production override** — no `[userenv.production]` or deployment
equivalent anywhere in the file.

**Whether a Replit VM deployment inherits `[userenv.shared]` is undocumented.**
Replit's published `.replit` configuration reference does not mention
`[userenv]` at all, so this could not be settled by reading anything available
to us, and it was deliberately **not** changed on a guess.

**If deployments do inherit it, production runs with `APP_DEBUG=true`**, which
renders full stack traces to visitors — including database credentials and
environment values.

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

If that shows `APP_DEBUG true` on a real deployment, fix it as its own change —
set production values via Replit deployment secrets or a production `userenv`
section. It was kept out of G4.5 on purpose: an unverified environment-mode
rewrite does not belong in a migration-safety change.

The preflight never prints credentials, and a test asserts that.

## Verifying

```bash
php artisan deploy:preflight     # environment, database, pending count, schema state
php artisan migrate:status       # what has and has not run
bash deploy/start-production.sh  # the full production start sequence, locally
```
