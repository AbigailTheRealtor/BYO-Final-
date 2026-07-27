# Runbook: Listing Storage Migration (`listing-storage:migrate`)

> **Tier 3 — subsystem owner document** for listing object-storage migration (HI-05A, R2-C onward). This subsystem sits **outside the numbered blueprint phases**; sequencing is owned by the Master Development Roadmap only where the two touch. This runbook is operational, not architectural.

## Purpose and scope

`listing-storage:migrate` copies **existing** local listing media and documents to the paired object-storage secondary disks, preserving the exact relative key on both sides. `auction/images/a.jpg` on the local disk becomes `auction/images/a.jpg` on the secondary — no re-keying, no re-naming.

It exists so that a later read-side or write-side flip has something to read. Populating the buckets and switching traffic to them are separate operations, deliberately.

**In scope:** copying existing objects, verifying them, and reporting what happened.

**Out of scope — this command does none of these:**

- It does **not** change any disk selector (`LISTING_PUBLIC_DISK`, `LISTING_PRIVATE_DISK`, `LISTING_PRIVATE_READ`, `LISTING_PUBLIC_READ`).
- It does **not** enable or disable dual-write (`STORAGE_DUAL_WRITE`).
- It is independent of the `documents:backfill-private` command.
- It does **not** delete, move, or modify anything on the local disks. Local sources are only ever read.

## What this command never does

The non-destructive guarantee is worth stating precisely, because the rest of this runbook leans on it:

- **Local objects are read-only to this command.** There is no code path that deletes or rewrites a local source object.
- **An existing destination object is never clobbered** unless you pass `--force-conflicts` together with `--confirm`.
- **`--dry-run` and `--verify-only` write nothing at all** — not to the secondary, and not to the private disk. See *Verify-only's no-write guarantee* below.

The worst outcome of an interrupted or repeated run is therefore extra or partial objects on the **secondary**, never data loss on the primary.

## Preconditions

### Environment variables

| Variable | Purpose |
|---|---|
| `AWS_ACCESS_KEY_ID` | Object-storage credentials |
| `AWS_SECRET_ACCESS_KEY` | Object-storage credentials |
| `AWS_DEFAULT_REGION` | Bucket region |
| `AWS_BUCKET` | Private bucket; also the fallback for the public bucket |
| `AWS_PUBLIC_BUCKET` | Dedicated public-media bucket. Optional — falls back to `AWS_BUCKET` |
| `AWS_ENDPOINT` | Custom endpoint (non-AWS S3-compatible storage) |
| `AWS_URL` | Public base URL for the public disk |
| `AWS_USE_PATH_STYLE_ENDPOINT` | Path-style addressing toggle |
| `STORAGE_S3_PREFLIGHT_ENABLED` | Must be `true` for the preflight to do anything |
| `LISTING_PUBLIC_DISK` / `LISTING_PRIVATE_DISK` | Local **source** disks (defaults `public` / `private`) |
| `LISTING_PUBLIC_SECONDARY_DISK` / `LISTING_PRIVATE_SECONDARY_DISK` | **Destination** disks (defaults `s3_public` / `s3_private`) |
| `STORAGE_DUAL_WRITE` | Not required by this command. Relevant to the live-traffic decision below |

### Disk configuration

| Disk | Root | Visibility |
|---|---|---|
| `public` (local source) | `storage/app/public` | public |
| `private` (local source) | `storage/app/private` | private |
| `s3_public` (destination) | bucket prefix `public` | public |
| `s3_private` (destination) | bucket prefix `private` | private |

Two safety rules are enforced in code and will abort the affected object rather than proceed:

- A destination disk that is not defined in `config/filesystems.disks` fails closed.
- **Private objects are refused to any secondary that advertises a `url`.** A private secondary with a public URL configured is treated as a misconfiguration, not as a destination.

### Confirm configuration resolves

Before anything else, confirm the command is registered and its options are what you expect:

```bash
php artisan listing-storage:migrate --help
php artisan storage:s3-preflight --help
```

## Step 0 — S3 preflight

Run the read-only connectivity probe before any migration step.

```bash
STORAGE_S3_PREFLIGHT_ENABLED=true php artisan storage:s3-preflight --confirm

# machine-readable form
STORAGE_S3_PREFLIGHT_ENABLED=true php artisan storage:s3-preflight --confirm --json
```

The preflight is **double-gated**: it performs nothing unless you pass `--confirm` *and* `STORAGE_S3_PREFLIGHT_ENABLED=true`. Without both it prints a disabled status, exits 0, and never builds a client or touches the network.

It validates `key`, `secret`, `region`, and `bucket` for **both** `s3_private` and `s3_public` before any network call, then performs a `HeadBucket` plus a `HeadObject` against a random non-existent key under each disk's own root prefix. It never lists, uploads, or mutates. Output is redacted — it prints a status enum and a message, never bucket names, keys, endpoints, or regions.

| Status | Meaning |
|---|---|
| `OK` | Both buckets reachable and authorized. Exit 0 |
| `DISABLED` | Not enabled or `--confirm` absent; nothing ran. Exit 0 |
| `MISSING_ADAPTER` | S3 Flysystem adapter not installed. Exit 1 |
| `MISSING_CONFIG` | Incomplete configuration on at least one disk. Exit 1 |
| `AUTH_FAILURE` | Credentials rejected. Exit 1 |
| `AUTHZ_DENIED` | Authenticated but not authorized to read. Exit 1 |
| `REGION_OR_ENDPOINT_MISMATCH` | Wrong region, endpoint, or bucket. Exit 1 |
| `NETWORK_ERROR` | Could not reach object storage. Exit 1 |
| `UNKNOWN_ERROR` | Unclassified. Exit 1 |

Do not proceed to migration unless the status is `OK`. A `DISABLED` result also exits 0 — check the printed status, not just the exit code.

## The three modes

| | `--dry-run` | `--verify-only` | write mode (`--confirm`) |
|---|---|---|---|
| Writes to the secondary | never | never | yes |
| Writes a manifest | never | never | yes |
| Needs `--confirm` | no | no | **yes** |
| Record output | capped at 20 | capped at 20 | full list persisted to manifest |
| Answers | "what *would* happen?" | "does what is there match?" | performs the copy |

Write mode **fails closed**: without `--confirm` the command refuses and exits 1 without writing anything. `--confirm` is ignored by `--dry-run` and `--verify-only`.

`--force-conflicts` has no effect without a write run; passing it to a read-only run prints a warning and is otherwise ignored.

`--strict` is orthogonal to all three modes: it changes only the exit code, never what is written or reported. See *Exit codes*.

## Bounded output in read-only modes

A full population enumerates far more objects than a terminal or CI log can absorb, and a read-only run has nowhere to persist the overflow. So in **both** read-only modes the per-object record list is capped at the first **20** records (`MigrateListingStorage::MAX_PRINTED_RECORDS`).

When records are omitted, the run says so explicitly — in the JSON:

```json
"records_truncated": {
    "shown": 20,
    "omitted": 5,
    "total": 25
}
```

and on stdout:

```
Showing first 20 of 25 records (5 not shown). The summary below counts all 25.
Narrow the run with --prefix/--scope/--limit to see the rest.
```

**The summary always counts the complete processed population.** Only the record *list* is bounded. A capped run still reports accurate totals in both the JSON `summary` block and the rendered status table:

```
Migration plan (dry run — no changes):
+---------------+-------+
| status        | count |
+---------------+-------+
| would_migrate | 25    |
+---------------+-------+
```

To see records beyond the cap, narrow the run with `--prefix`, `--scope`, or `--limit` rather than expecting more output from a wide run.

## Verify-only's no-write guarantee

`--verify-only` writes nothing, anywhere. Specifically:

- It never writes to the secondary.
- It never persists a manifest, **even when `--manifest` is given an explicit path.** That option names where a manifest *would* go; it does not authorize writing one.
- It never persists a manifest **when combined with `--resume`.** Resume *reads* a prior manifest to decide what to skip; it does not cause one to be emitted.

Because a verification run has no manifest to point at, its failure message directs you to the records printed above it rather than to a file on disk.

## Safe progression

Run these in order. Do not skip to step 3.

### Step 1 — dry-run

Start narrow, then widen:

```bash
php artisan listing-storage:migrate --scope=public --prefix=auction/images --dry-run
php artisan listing-storage:migrate --scope=all --dry-run
```

This enumerates candidates and decides what *would* happen, without writing. Read the summary counts, not just the exit code.

### Step 2 — verify-only

```bash
php artisan listing-storage:migrate --scope=all --verify-only
```

Before the first migration this will report `missing_on_dest` for everything, which is expected and is **not** a failure. Its value here is confirming that the destination is reachable and that nothing unexpected already occupies the keys you are about to write.

### Step 3 — controlled write run

Begin bounded, and only widen once a small batch has come back clean:

```bash
php artisan listing-storage:migrate --scope=public --prefix=auction/images --confirm --limit=100
php artisan listing-storage:migrate --scope=all --confirm
```

`--limit` caps the number of objects processed **across all scopes** in that run — it stops the whole run, not just the current scope — and warns that the remainder was not processed.

### Step 4 — post-run verification

```bash
php artisan listing-storage:migrate --scope=all --verify-only
```

After a complete population this should report `skipped_identical` for every object and nothing else. Any `conflict` or `missing_on_dest` means the population is not finished or not correct.

### Step 5 — resume

If a write run is interrupted, resume it:

```bash
php artisan listing-storage:migrate --scope=all --confirm --resume
php artisan listing-storage:migrate --scope=all --confirm --resume --manifest=_migration-manifests/migrate-20260727_120000.json
```

Without `--manifest`, resume selects the most recent manifest — manifests sort chronologically because their default names are timestamped. With `--manifest`, it reads exactly the file you name.

Only objects recorded as `migrated` or `skipped_identical` are treated as done. Anything that ended as `conflict`, `needs_review`, or `error` is **reprocessed** on the next run, which is what makes reruns converge.

## Manifests

**Purpose.** The manifest is the durable record of a write run: what was processed, each object's status and error class, its size and local SHA-256, and the destination verification result. It is also the resume ledger.

**Location.** Manifests are written to the **local private disk**, not to object storage:

```
storage/app/private/_migration-manifests/migrate-<Ymd_His>.json
```

Override the path with `--manifest`. The path is relative to the private disk.

**When written.** Only by a write run. A write run also checkpoints the manifest every **25** objects, so an interrupted run leaves a usable resume ledger rather than nothing.

**Excluded from migration.** `_migration-manifests` and `_backfill-manifests` are excluded from enumeration by default, as is any file named `.gitignore`. `--include-manifests` un-excludes **only** `_backfill-manifests`; `_migration-manifests` remains excluded regardless.

**Retention — observed behavior.** The application does **not** prune manifests. They accumulate under `_migration-manifests/` until removed by hand. There is no scheduled cleanup and no retention command. Preserve any manifest you still need for audit or resume; resume picks the most recent one, so deleting the newest changes which ledger a subsequent `--resume` will read.

## Statuses, conflicts, and failure handling

| Status | Meaning | Fails by default? |
|---|---|---|
| `migrated` | Copied and verified | no |
| `skipped_identical` | Destination already byte-identical | no |
| `would_migrate` | Dry-run: would be copied | no |
| `missing_on_dest` | Verify-only: not yet on the destination | **no** |
| `source_vanished` | The local source was deleted during the copy window; the copy was rolled back | **no** — see *Live traffic* |
| `source_changed` | The local source was replaced during the copy window; the copy was rolled back | **no** — see *Live traffic* |
| `needs_review` | Destination has the same size but a different hash | **no** — see the warning below |
| `conflict` | Destination differs and was not overwritten | **yes** |
| `error` | Processing failed | **yes** |

Error classes on a record are redacted and never carry bucket, endpoint, or credential detail: `NONE`, `SOURCE_MISSING`, `NETWORK_TIMEOUT`, `AUTHZ_DENIED`, `PARTIAL_UPLOAD`, `CHECKSUM_MISMATCH`, `UNKNOWN`.

> **Warning — exit status alone is not sufficient by default.** Without `--strict`, only `error` and `conflict` produce a failing exit code. A `needs_review` object — same byte size, different SHA-256, which is exactly the shape of a silently corrupted or divergent copy — leaves the run at **exit 0**, as do `missing_on_dest`, `source_vanished` and `source_changed`. **Read the complete summary table, or run with `--strict` if something automated is gating on the exit code.**

### Exit codes

`--strict` exists because the two audiences want opposite things. An operator at a terminal wants a raced object *reported*, not a failed run — the race is transient and a rerun converges. Automation gating on `$?` needs it to fail. `--strict` only ever **adds** statuses to the failure set; nothing that fails by default passes under it.

| Outcome | Default | `--strict` |
|---|---|---|
| `migrated` / `skipped_identical` / `would_migrate` | 0 | 0 |
| `missing_on_dest` (verify-only, mid-population) | 0 | **0** |
| `source_vanished` | 0 + warning | **1** + warning |
| `source_changed` | 0 + warning | **1** + warning |
| `needs_review` | 0 | **1** |
| `conflict` | 1 | 1 |
| `error` | 1 | 1 |
| Write mode without `--confirm` | 1 | 1 |

`missing_on_dest` stays non-failing in both modes on purpose: mid-population it is the expected state of every object not yet copied, so failing on it would make `--verify-only` useless until the final object landed.

The raced-object warning is printed in **both** modes — `--strict` changes the exit code, not what the operator is told.

```bash
# Operator-friendly default: races are reported, the run still succeeds.
php artisan listing-storage:migrate --scope=all --confirm

# Automation: any raced or needs-review object fails the run.
php artisan listing-storage:migrate --scope=all --confirm --strict

# Assert a completed population in CI (missing_on_dest still exits 0 —
# use this after the population, not during it).
php artisan listing-storage:migrate --scope=all --verify-only --strict
```

**Handling a conflict.** A conflict means the destination holds different bytes under a key you were about to write. The command leaves it untouched. Investigate before doing anything else: identify which copy is authoritative. Only then, and only against a narrow scope, may you overwrite:

```bash
php artisan listing-storage:migrate --scope=public --confirm --force-conflicts --prefix=<narrow-prefix>
```

Never run `--force-conflicts` across a broad scope to clear a conflict report. It overwrites every differing destination object it encounters.

## Rerun and convergence

The command is idempotent. An object already present and byte-identical is reported `skipped_identical` and not re-uploaded. Reruns are the normal mechanism for finishing an incomplete population:

1. Rerun with `--confirm --resume` to skip completed keys.
2. Rerun without `--resume` to reprocess everything — slower, but it re-verifies the full set.
3. Repeat until a `--verify-only` pass reports `skipped_identical` for every object and nothing else.

Verification is genuine, not assumed: every object is compared on **byte size and streamed SHA-256**, with the destination content re-downloaded to compute its hash.

## Cost and duration — read before scheduling a window

Read-only modes are **not** cheap, and this surprises people.

For every enumerated object, the command computes the **full local SHA-256** before deciding anything, and issues an existence check against the destination. If the object is already on the destination, it **downloads the entire object again** to hash it.

The practical consequences:

- A full-population `--dry-run` hashes every local object and issues one destination request per object.
- A full-population `--verify-only` additionally **re-downloads every object already migrated**, incurring real egress cost and time.
- A write run uploads, then re-downloads each object to verify it.
- A write run also **re-reads every local object a second time** after the upload, to revalidate it against live traffic (see *Live traffic*). That roughly doubles local read I/O per migrated object. It costs no additional object-storage traffic, and it is what makes a live-traffic population safe.
- A forced overwrite of an existing destination object additionally performs a server-side move during the final swap.

Size your maintenance window against total bytes, not object count, and expect verification passes to cost roughly a full read of everything already in the bucket.

## Interruption, containment, and rollback

**There is no automated rollback, and no secondary-object cleanup command.** `listing-storage:migrate` and `storage:s3-preflight` are the only storage commands; neither removes objects from a secondary.

This is tolerable because of the non-destructive guarantee: an interrupted write run cannot have damaged the local primary. Containment is therefore:

1. **Stop the run.** The local disks are unaffected; no recovery is needed there.
2. **Leave the secondary as it is.** Objects already copied are verified copies. Partial or unverified objects are re-processed by the next run — a differing destination is reported as `conflict` or `needs_review` rather than silently trusted.
3. **Do not flip any selector.** A partially populated secondary must not be promoted to a read source. Keep `LISTING_PRIVATE_READ` / `LISTING_PUBLIC_READ` at `local` until a clean verify pass.
4. **Converge by rerunning** with `--confirm --resume`, then confirm with `--verify-only`.
5. **If stray objects must be removed** — for example a key migrated under a prefix that should have been excluded — that removal is a **manual bucket operation**. No command in this application performs it. Treat it with the care any manual production bucket deletion deserves.

## Validating counts and samples

Use the command's own summaries. They are the sanctioned counting mechanism, and they remain accurate even when the displayed record list is capped at 20.

**Before a run** — the dry-run summary is your candidate count:

```bash
php artisan listing-storage:migrate --scope=all --dry-run
```

Record the `would_migrate` total (plus any `skipped_identical` from a prior partial run). This is the population you expect to migrate.

**After a run** — the verify-only summary is your completion check:

```bash
php artisan listing-storage:migrate --scope=all --verify-only
```

A finished population reports `skipped_identical` equal to the total and **no** `missing_on_dest`, `conflict`, or `needs_review` rows.

**Sampling.** The first 20 records printed by a read-only run are your sample: each carries the relative key, byte size, local SHA-256, and the destination verification result. To sample a specific area, narrow with `--prefix`:

```bash
php artisan listing-storage:migrate --scope=private --prefix=landlord-disclosures --verify-only
```

Reconcile three numbers across a migration: the dry-run candidate count before, the write run's `migrated` + `skipped_identical` count, and the verify-only `skipped_identical` count after. They should agree.

## Warning — do not delete source objects

**Do not delete, move, or archive local source objects after a migration.** Not after a successful write run, not after a clean verify pass.

The local disks remain authoritative until a read-side flip has been performed, validated, and operated for long enough to trust. Until then, the secondary is a *copy*, and deleting the source converts a redundant copy into a single point of failure with no fallback.

This command will never delete a local object. Neither should you, on the strength of a migration alone.

## Live traffic and the recommended operating posture

**Recommended: run the initial full population during a maintenance window, or with writes otherwise quiesced.** Fewer moving parts is still the safest posture, and it is what we recommend.

Running while writes continue is **supported**. The migrator decides everything about an object *before* the copy — exists, size, SHA-256 — and the upload lands some time later. In that window a live request can delete or replace the same object. Both cases are now detected and handled:

1. **Upload.** The bytes are streamed to the destination.
2. **Revalidate the source.** The local source is re-read *after* the upload and *before* the destination is verified. That order matters: verifying first would compare against the pre-copy hash, which the race made stale, and a raced copy would verify as a success.
3. **Detect.** Source gone → `source_vanished`. Source present but different bytes → `source_changed`. The check is content-based, so a replacement of the same byte length is caught.
4. **Roll back.** The object this run just wrote is removed and confirmed gone. Leaving it would resurrect a deleted object, or leave stale bytes that object-first reads would serve as current. If the rollback itself does not take, the record is `error` rather than the benign raced status — a clean report with an orphan behind it is the exact divergence this prevents.
5. **Record.** The status lands in the manifest and the run warns how many objects were raced.
6. **Rerun to converge.** Raced objects are not in the resume done-set, so `--confirm --resume` reprocesses them against fresh state.

A live-traffic population therefore **may need more than one pass**, and a rerun is the mechanism by which it finishes. That is expected, not a fault. Plan for at least one additional `--confirm --resume` pass followed by a `--verify-only` pass, and treat any `conflict` or `needs_review` as requiring individual investigation rather than a blanket `--force-conflicts`.

Use `--strict` if something automated must notice that a pass did not fully converge.

### Staged forced overwrite (`--force-conflicts`)

A forced overwrite is the only path that reaches the upload with an object already on the destination, and that object is the one thing a failure could destroy. It is therefore **staged**:

- Staging applies **only** when the destination object already exists *and* `--force-conflicts` is active. The normal destination-missing path — every object in a fresh population — is unchanged and writes straight to its final key.
- The new bytes are uploaded to a staging key and verified there (size + SHA-256), and the source is revalidated for a race, **before** the existing destination object is touched.
- **Any failure before the final swap leaves the original destination object exactly as it was** — a race, a failed verification, or a throwing adapter all discard the staged object only.
- Once the staged object is proven good, the original key is deleted and the staged object is moved onto it. The result is verified again at the final key.

**The final swap is delete-plus-move and is not atomic.** There is no overwriting copy or rename available, so the original must be removed before the staged object can take its key, leaving a brief window in which neither holds the final key. It is two metadata operations on already-uploaded bytes rather than the whole upload.

If the move fails, the run reports **`error`** and never `migrated`. The final key is left absent, reads fall back to local (correct bytes, nothing stale served), and a rerun re-copies.

### The staging prefix

```
_migration-staging
```

Staged objects are **internal migration objects, not listing keys**. Nothing reads or serves them, and they are never migration candidates — enumeration reads the local source, and these live only on the destination. They are removed automatically on every handled outcome, success or failure.

A hard process crash between staging and cleanup can leave residue under that prefix. It is inert, but it accumulates: **sweep `_migration-staging` manually after any run that died without exiting cleanly.** No command in this application does it for you.

## Known limitations

**Same-second manifest filename collision.** Default manifest names have **one-second** timestamp granularity (`migrate-<Ymd_His>.json`). Two write runs started concurrently, or within the same second, resolve to the same manifest key, and the second **silently overwrites** the first rather than creating a separate file. Because the manifest is both the audit record and the resume ledger, losing one costs the audit trail and can misdirect a later `--resume`.

> **Operators must not start concurrent write runs, and must not start two write runs within the same second.** Run one migration at a time. If you need parallel runs across disjoint prefixes, give each an explicit distinct `--manifest` path.

This is a documented limitation, not a fixed defect. No code change accompanies this runbook.

**Unbounded in-memory record accumulation.** Every processed object's record is held in memory for the duration of a run, in all modes. At full-population scale this grows with object count. Not yet addressed; narrow a very large run with `--prefix`, `--scope` or `--limit` if memory becomes a concern.

**`needs_review` does not fail the run by default.** Covered above; repeated here because it is the most likely way a problem goes unnoticed. Read the full summary, or use `--strict`.

**Read-only modes are expensive.** Covered under *Cost and duration*. `--dry-run` and `--verify-only` hash local objects, check destination objects, and download destination content to verify — they are not free previews.

**No automated rollback or cleanup.** Covered under *Interruption, containment, and rollback*. There is no command that removes objects from a secondary, including staging residue.

**The staged swap is not atomic, and its behavior is adapter-dependent.** Three things worth knowing before a forced overwrite at scale:

- The final swap is delete-plus-move. A crash precisely between the two leaves the destination key **absent**. Local storage remains authoritative, so reads fall back correctly and a rerun converges — but the key is temporarily missing on the secondary.
- **Move semantics differ by adapter.** On local disks a move is a rename. On real S3 it is a copy followed by a delete, with different failure and consistency characteristics, and single-operation copy size limits apply to very large objects. The staged flow degrades safely either way — a failed move is reported as `error`, never as a success — but the S3 path is not exercised by the automated tests.
- **The automated tests use fake/local adapters**, not a real bucket. Behavior against production object storage should be confirmed on a narrow `--prefix` before a broad forced overwrite.
- A hard crash may leave residue under `_migration-staging` requiring manual cleanup.

## Related files

- `app/Console/Commands/MigrateListingStorage.php` — the command: options, manifest, resume, output
- `app/Support/Storage/ListingObjectMigrator.php` — per-object enumeration, streamed copy, verification
- `app/Console/Commands/S3PreflightCommand.php` — read-only connectivity preflight
- `app/Support/Storage/ListingStorageDisks.php` — disk-name resolution seam
- `config/listing_storage.php` — selectors, dual-write/dual-read flags, migration exclusions
- `config/filesystems.php` — local and object-storage disk definitions
- `tests/Feature/Console/MigrateListingStorageTest.php` — command behavior, including the read-only guarantees
- `tests/Unit/Storage/ListingObjectMigratorTest.php` — per-object migration behavior
