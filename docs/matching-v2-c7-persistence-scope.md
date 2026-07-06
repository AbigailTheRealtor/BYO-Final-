# Matching V2 — C7: Persistence Scope — Scope Proposal

**Status:** Approved scope — implementation to follow (docs first)
**Date:** 2026-07-06
**Type:** Backend-only persistence slice. Read-only over `dna_scores`; writes ONLY to two new, additive Matching V2 tables. No UI/API/routes, no Match Check wiring, no compatibility-engine changes, no consumer exposure.
**Depends on (merged):** C1 `DnaScoreRepository`, C2 `DnaMatchService`/§F6, C3 discovery Stage A, C4 narrowing/compliance Stage B, C5 55+ remediation, C6 `MatchingV2Service` facade + `OrchestratedMatchResult`, C6.1 validation harness.

---

## 0. What C7 delivers

The C6 facade (`MatchingV2Service::matchForSubject`) computes a ranked, tiered,
compliance-gated `OrchestratedMatchResult` **per subject, on demand, in memory** and
persists nothing. The pre-C7 validation runbook decided C7 = **persistence/caching**:
materialize the ranked result offline so reads are cheap and stable, ahead of any
future exposure.

C7 delivers exactly that, and nothing more:

1. **Two additive tables** — a per-subject **summary** table and a child **matches**
   table (OD-1).
2. **A gated, production-refusing writer** (`MatchResultPersister`) that materializes an
   `OrchestratedMatchResult` into those tables idempotently.
3. **Two ways to drive the writer** (OD-2): a **batch materialization command** and a
   **per-subject queued job**.
4. **An internal reader** (`PersistedMatchReader`) that re-gates at read time and returns
   a read model — **shipped now but left unwired and non-consumer-facing** (OD-7).
5. **A dedicated feature flag** `MATCHING_V2_PERSISTENCE_ENABLED`, default **off** (OD-4),
   layered under the existing `matching.v2_enabled` master gate.

```
                    (offline / staging only)
MatchingV2Service::matchForSubject(type,id)  ──►  OrchestratedMatchResult (in memory)
                                                          │
                        MatchResultPersister.persist(...) │  gated: v2_enabled AND persistence.enabled AND NOT production
                                                          ▼
                          matching_v2_match_runs (summary, 1 row/subject/version)
                          matching_v2_matches   (children, 1 row/ranked match)
                                                          │
                        PersistedMatchReader.read(type,id)│  read-time RE-GATE (flag + version); UNWIRED
                                                          ▼
                                 PersistedMatchResult (internal read model)
```

---

## 1. Non-goals / guardrails (hard boundaries)

These are invariants the implementation and its tests must prove, not aspirations:

- **No UI, no API, no routes.** C7 adds no controller, no Livewire component, no route,
  no Blade. The reader exists but nothing in a request path calls it.
- **No Match Check wiring.** C7 touches nothing under `app/Services/Stellar/MatchCheck/`
  or the staged Match Check Phase 4 files (C1–C5 of that separate track). No shared edits.
- **No compatibility-engine changes.** `config/bya_compatibility.php`,
  `ComputeCompatibilityScore`, `listing_compatibility_scores` are untouched.
- **No production enablement.** The new flag defaults off; the writer additionally
  **refuses to run in `production`** regardless of flag state (OD-5). Staging/dev only.
- **No confidence display.** Confidence/coverage may be persisted internally (§4.2) for
  fidelity, but the reader is internal-only and no consumer-facing surface renders them.
- **No changes to generation or `dna_scores`.** C7 is a pure read-only consumer of
  `dna_scores` (via the C6 facade). It never writes, migrates, or alters `dna_scores`,
  the generation observers, `ComputeLocationDna`, or `dna:generate-scores`.
- **Additive only.** Two brand-new tables; no column added to or dropped from any
  existing table. Nothing existing is renamed.

---

## 2. Approved design decisions

| # | Decision | Resolution |
|---|----------|-----------|
| **OD-1** | Table shape | **Two tables** — summary (`matching_v2_match_runs`) + child matches (`matching_v2_matches`). |
| **OD-2** | Drive mechanism | **Both** a batch materialization command **and** a per-subject queued job, over one shared writer. |
| **OD-3** | Staleness strategy | **Version + read-time re-gate.** Event-driven invalidation is deferred as a fast-follow (§6). |
| **OD-4** | Flag | Dedicated `MATCHING_V2_PERSISTENCE_ENABLED`, default **off**, under `matching.v2_enabled`. |
| **OD-5** | Production writes | **Refused.** Writer/command/job hard-refuse in the `production` environment. |
| **OD-6** | Zero-determined subjects | **Persist an empty summary row** (determined_count = 0, no children). |
| **OD-7** | Reader | **Ship now, unwired**, internal, non-consumer-facing. |

---

## 3. Gating model (three independent gates, all must pass to WRITE)

A write occurs only when **all** hold. Any one false ⇒ the writer is inert (no DB write):

1. `config('matching.v2_enabled')` — the existing master gate (the engine may run).
2. `config('matching.persistence.enabled')` — the new C7 gate (persistence is allowed).
3. `! app()->environment('production')` — the hard staging/dev-only refusal (OD-5),
   mirroring the precedent in `MatchingValidate::handle()` (`app/Console/Commands/MatchingValidate.php:49`).

**Read gate (re-gate, OD-3):** the reader returns an empty/absent result whenever
`config('matching.v2_enabled')` is false **or** the persisted `version` does not equal the
current configured version. So even if stale rows exist, a disabled engine or a version
bump makes them invisible — persistence can never leak results the live gate would deny.

> The writer's production refusal is intentionally **not** overridable by config, so a
> mis-set flag in prod still cannot write. The reader has no production refusal (reads are
> harmless and it is unwired anyway) but is fully re-gated.

---

## 4. Schema (two additive tables)

Addressing follows the canonical `(listing_type, listing_id)` convention used by
`dna_scores` and `property_location_dna`. Here the **subject** is the thing matched *for*;
each child row is one ranked **counterpart**.

### 4.1 `matching_v2_match_runs` — summary (one row per subject per version)

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigIncrements | |
| `subject_type` | string | e.g. `buyer_agent`, `seller_agent`, `landlord_agent`, `tenant_agent` |
| `subject_id` | unsignedBigInteger | |
| `direction` | string, nullable | `ListingToDemands` \| `DemandToListings` (from `MatchDirection`) |
| `version` | string | materialization version tag (§5); part of the uniqueness key |
| `determined_count` | unsignedInteger | ranked matches persisted as children |
| `undetermined_count` | unsignedInteger | from `OrchestratedMatchResult::undeterminedCount()` |
| `candidates_considered` | unsignedInteger | discovery metadata |
| `candidate_pool_truncated` | boolean | discovery metadata |
| `tier_counts` | json | zero-filled 4 tiers `{exact,strong,similar,opportunity}` |
| `computed_at` | timestamp, nullable | when the underlying result was computed |
| `created_at`/`updated_at` | timestamps | |

- `unique(['subject_type','subject_id','version'])` — one summary per subject per version;
  the writer upserts on this key (idempotent re-materialization).
- `index(['subject_type','subject_id'])` — reader lookup.

**OD-6:** a zero-determined subject still gets exactly one summary row here
(`determined_count = 0`, `tier_counts` all zero) and **no** child rows. This records
"we ran it and there were no matches" distinctly from "never materialized."

### 4.2 `matching_v2_matches` — children (one row per ranked match)

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigIncrements | |
| `match_run_id` | foreignId → `matching_v2_match_runs`, `cascadeOnDelete` | |
| `subject_type` | string | denormalized (reader can query children without a join) |
| `subject_id` | unsignedBigInteger | denormalized |
| `counterpart_type` | string, nullable | preserves listing_type (C6 Design T); nullable mirrors `RankedMatch::counterpartType()` |
| `counterpart_id` | unsignedBigInteger | the ranked counterpart's `listing_id` |
| `position` | unsignedInteger | 0-based best-first rank within the run |
| `tier` | string | `exact`\|`strong`\|`similar`\|`opportunity` (`MatchTier::value`) |
| `value` | unsignedSmallInteger, nullable | overall relevance (0–100) |
| `confidence` | unsignedSmallInteger, nullable | internal fidelity only — never displayed (§1) |
| `coverage` | unsignedSmallInteger, nullable | internal fidelity only — never displayed (§1) |
| `created_at`/`updated_at` | timestamps | |

- `index(['match_run_id','position'])` — ordered read-back.
- Children are always rewritten wholesale with their parent run (delete + insert inside the
  writer's transaction); no partial child updates.

> `counterpart_id` is stored as `unsignedBigInteger`: in practice counterpart ids are
> integer `listing_id`s (`RankedMatch::counterpartId` is typed `int|string` only because the
> generic §F6 sorter never interprets it; the Matching V2 path always supplies an int).

Both migrations are idempotent (`Schema::hasTable(...)` guard) like the `dna_scores`
migration, and fully reversible (`dropIfExists`).

---

## 5. Versioning (OD-3)

- New config key `matching.persistence.version` (env `MATCHING_V2_PERSISTENCE_VERSION`,
  default e.g. `'c7-v1'`). The writer stamps every summary row with the **current** value.
- The reader accepts only rows whose `version` equals the current configured value; older
  rows are ignored (treated as stale/absent), never deleted at read time.
- Bumping the version is the coarse invalidation lever: after a scoring/engine change, bump
  the tag and re-materialize; old rows fall out of read visibility immediately and can be
  pruned separately. This is deliberately simple and stateless — **event-driven
  invalidation (per-subject dirty tracking on `dna_scores` writes) is deferred to §6.**

---

## 6. Deferred (explicit fast-follow, NOT in C7)

- **Event-driven invalidation.** Observers on `dna_scores` (or the generation lifecycle)
  marking affected subjects dirty / re-enqueuing the per-subject job. C7 relies on version
  bump + re-run instead. Documented here so the seam is intentional, not forgotten.
- **Any consumer exposure.** Wiring the reader into Match Check, a route, or a UI is a
  separate, separately-approved slice.
- **Pruning/GC of stale-version rows.** A future `matching:prune` style command.

---

## 7. Components & files

**New (additive):**

- `database/migrations/2026_07_06_000001_create_matching_v2_match_runs_table.php`
- `database/migrations/2026_07_06_000002_create_matching_v2_matches_table.php`
- `app/Models/Matching/MatchRun.php` — summary Eloquent model (`hasMany` matches).
- `app/Models/Matching/PersistedMatch.php` — child Eloquent model (`belongsTo` run).
- `app/Services/Dna/Relevance/Persistence/MatchResultPersister.php` — the gated,
  production-refusing writer; `persist(OrchestratedMatchResult): ?MatchRun` (returns null
  when inert). Wraps summary + children in one DB transaction; idempotent upsert on the
  unique key; writes an empty summary for zero-determined subjects (OD-6).
- `app/Services/Dna/Relevance/Persistence/PersistedMatchReader.php` — internal reader;
  `read(string $subjectType, int $subjectId): ?PersistedMatchResult`; applies the read-time
  re-gate (§3); **unwired**.
- `app/Services/Dna/Relevance/Persistence/PersistedMatchResult.php` — immutable read model
  returned by the reader (mirrors the honest shape of `OrchestratedMatchResult::toArray()`).
- `app/Jobs/MaterializeMatchesForSubject.php` — per-subject queued job (OD-2): resolves the
  facade, computes, persists; inert under the same gates.
- `app/Console/Commands/MatchingMaterialize.php` — `matching:materialize` batch command
  (OD-2): iterates a roster/discovered subjects, computes + persists each; production- and
  flag-refusing with a clear message and non-zero exit, mirroring `matching:validate`.

**Modified (additive only):**

- `config/matching.php` — add a `persistence` block:
  ```php
  'persistence' => [
      'enabled' => env('MATCHING_V2_PERSISTENCE_ENABLED', false),
      'version' => env('MATCHING_V2_PERSISTENCE_VERSION', 'c7-v1'),
  ],
  ```
- `CLAUDE.md` / `.env` key table — document the two new env keys.

**Explicitly NOT touched:** anything under `app/Services/Stellar/MatchCheck/`, the C6
facade internals, `dna_scores` and its migration/model/generation, the compatibility
engine, any route/controller/view.

---

## 8. Tests (prove the safety invariants)

New tests under `tests/Feature/Dna/` and `tests/Unit/Dna/`:

**Gating / safety invariants (the point of C7):**

1. Writer is inert when `matching.v2_enabled` is off — **zero rows** in both tables.
2. Writer is inert when `matching.persistence.enabled` is off (master on) — zero rows.
3. Writer **refuses in production** even with both flags on — zero rows (OD-5).
4. Reader returns empty when `matching.v2_enabled` is off, **even though rows exist**
   (read-time re-gate, OD-3).
5. Reader ignores rows whose `version` ≠ current configured version (OD-3).

**Correctness:**

6. With both flags on and non-prod, persisting a result with N determined matches writes
   exactly one summary row (correct counts/tier_counts/metadata) and N children in
   best-first `position` order, preserving `counterpart_type`.
7. Zero-determined subject persists exactly one empty summary row and **no** children (OD-6).
8. Re-persisting the same subject/version is idempotent — one summary, children replaced
   not duplicated (unique-key upsert; child count stable).
9. Reader round-trips a persisted run back into a `PersistedMatchResult` matching the
   written summary + ordered children.
10. Per-subject job and the batch command each honor all three write gates (at least a
    smoke test that both refuse under a disabled flag / production).

**Isolation guard:**

11. Assert no write touches `dna_scores` (count unchanged across a persist) — proves the
    read-only-over-generation invariant.

All tests use the SQLite in-memory suite (`php artisan test`), consistent with the repo.

---

## 9. Rollout

1. Land migrations + models + writer + reader + job + command + config + tests (this slice),
   flag **off**, in one commit on the `launch-audit-remediation` branch.
2. In **staging**, enable `MATCHING_V2_ENABLED=true` (already required for the engine) and
   `MATCHING_V2_PERSISTENCE_ENABLED=true`, ensure `dna_scores` populated, run
   `php artisan matching:materialize` (or enqueue the per-subject job) and inspect the two
   tables. Never in production.
3. Fast-follow (separate approvals): event-driven invalidation (§6), then reader exposure.

---

## 10. Open questions (none blocking)

- Roster source for `matching:materialize`: reuse the C6.1 `ValidationRosterBuilder`
  auto-discovery vs. an explicit `--roster` file. **Proposed:** accept both, defaulting to
  discovery, exactly as `matching:validate` does — no new discovery logic.
- Default `version` string value (`'c7-v1'`) — cosmetic; owner may rename.
