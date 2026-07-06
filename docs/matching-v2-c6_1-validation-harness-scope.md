# Matching V2 — C6.1: `matching:validate` Read-Only Validation Harness — Scope

**Status:** Draft for review / approval — **no code yet**
**Date:** 2026-07-06
**Type:** Backend-only, **read-only** diagnostic tooling. Staging/dev only. No DB writes, no match-result persistence, no UI/API, does not enable Matching V2 or generation in production.
**Depends on:** C1–C6 (esp. `MatchingV2Service`), plus a populated `dna_scores` corpus (Phase 0 of the runbook, done manually in staging).
**Executes:** the plan in `docs/matching-v2-validation-runbook.md`.

---

## 0. What it is / is not

**Is:** one artisan command that runs the approved validation roster through the *same backend pipeline* `matching:preview` uses (`MatchingV2Service::matchForSubject`), writes per-scenario JSON to an `out/` dir, prints a summary table, and hard-fails on safety/correctness invariants (compliance, determinism, read-only).

**Is not:** it does **not** enable or run DNA generation (that's the separate, manual Phase-0 staging step), does **not** persist match results to any DB table, adds **no** routes/controllers, and **refuses to run in production**. The only thing it writes is diagnostic JSON files to a local, non-public directory.

---

## 1. How it works (flow)

1. **Guards (fail-closed):**
   - Abort with a clear message + non-zero exit if `app()->environment('production')`. No override flag exists.
   - Pre-flight: if `dna_scores` is empty (or has 0 rows for the sides being tested), print "no corpus — run generation in staging first" and exit non-zero. This prevents an empty corpus reading as a false "all clear."
2. **Resolve the roster** (read-only): auto-discover subjects per category from `dna_scores` + auction meta, or load a pinned `--roster=path.json` for reproducibility.
3. **Force-enable matching in-process** (`config(['matching.v2_enabled' => true])`), exactly like the preview command, and **restore** the original flag in a `finally` at the end.
4. **Run scenarios in order, compliance first.** Each scenario calls `MatchingV2Service::matchForSubject(...)` (optionally toggling `hard_filters_enabled` / `senior_unknown_policy` in-process for the compliance variants, restored after), evaluates its checks, and records the `OrchestratedMatchResult`.
5. **Write outputs:** one `out/<category>-<type>-<id>.json` per scenario (the result plus per-match diagnostics) and an `out/summary.json`.
6. **Print a summary table** to the console: per category — subjects run, PASS/FAIL/ADVISORY counts, and key numbers.
7. **Assert read-only:** snapshot DB row counts before/after the whole run; a change is a hard failure.
8. **Exit code:** non-zero if any *hard* invariant failed (compliance, determinism, read-only, wrong-side types, crash); zero otherwise (advisory quality notes never fail the run).

It calls the backend service directly rather than shelling `matching:preview` (identical result, faster, no per-subject re-boot) — **OD-1**.

---

## 2. Scenarios (all required categories) and the checks each runs

Each scenario records the `OrchestratedMatchResult` and applies **hard** checks (fail the run) and **advisory** checks (reported for human review).

| # | Category | Subjects | Hard checks | Advisory |
|---|---|---|---|---|
| 1 | **Compliance (first)** | non-eligible seekers; a senior-restricted listing subject | returned ids contain **zero** senior-restricted listings for a non-eligible seeker (and no non-eligible seeker for a senior listing); holds with hard filters OFF and ON; `senior_unknown_policy=closed` excludes unknown-senior | — |
| 2 | **Buyer → listings** | scored `buyer_agent` | `direction=DemandToListings`; every match is `seller_agent`/`landlord_agent` | tier/value plausibility |
| 3 | **Tenant → listings** | scored `tenant_agent` | same as #2 | commercial-vs-residential coherence |
| 4 | **Listing → demand** | scored `seller_agent`, `landlord_agent` | `direction=ListingToDemands`; every match is `buyer_agent`/`tenant_agent` | symmetry vs #2/#3 |
| 5 | **Mixed seller/landlord pool** | a buyer/tenant whose pool spans both property types | both types present; **each match's `listing_type` is correct even with colliding ids** (the C6 fix) | balance across types |
| 6 | **No-DNA / low-DNA** | subject with no scores; subject with 1–2 score keys | no-DNA → `determined=0`, `undetermined=considered`, no crash | low-DNA degrades to lower tiers, no fabricated confidence |
| 7 | **Confidence / coverage** | fully-scored vs sparse pairs | — | high tier only with real coverage; confidence tracks completeness (from per-match `confidence`/`coverage`) |
| 8 | **Truncation** | subject with a large eligible pool | `candidate_pool_truncated=true` when pool > cap; flag honest across `--cap` values | pool sizes |
| 9 | **Determinism** | a sample (default 3) from the above | double-run → **identical** JSON | — |
| 10 | **Read-only** | whole run | DB row counts unchanged across `dna_scores` + the four `*_agent_auctions` + their meta tables | — |

Compliance, determinism, read-only, and wrong-side-type are the **hard** gates; everything else is advisory (human judgment on quality).

---

## 3. Roster discovery (read-only queries)

`ValidationRosterBuilder` builds the roster via `SELECT`s (default `--limit=5` subjects per category), or is bypassed by `--roster=path.json`:

- scored demand subjects: `dna_scores` `side='demand'`, types `buyer_agent`/`tenant_agent`.
- scored property subjects: `side='property'`, types `seller_agent`/`landlord_agent`.
- senior-restricted listings + non-eligible/eligible seekers: from `leasing_55_plus` meta on the auction rows.
- low-DNA subjects: `GROUP BY (listing_type, listing_id) HAVING COUNT(DISTINCT score_key) <= 2`.
- no-DNA subject: an approved offer-listing auction with **no** `dna_scores` row (or a synthetic id with none).
- large-pool subject: the demand subject whose discovery returns the most candidates.

---

## 4. Output

- `--out` dir (default `storage/app/matching-validation/`, non-public, git-ignored). **OD-3.**
- Per scenario: `out/<category>-<listing_type>-<listing_id>.json` = `OrchestratedMatchResult::toArray()` **plus** per-match diagnostics (tier, value, **confidence, coverage**, cleared/shortfall/gap keys) via a new additive accessor (see §6).
- `out/summary.json` — machine summary (per-category pass/fail/advisory + key metrics).
- Console: a formatted summary table + a final PASS/FAIL banner.

No output goes to `public/`; nothing is served.

---

## 5. Exit codes & flags

**Flags:** `--out=` · `--roster=` · `--limit=` (subjects/category, default 5) · `--cap=` (discovery cap passthrough) · `--fail-fast` (stop at first compliance failure) · `--sample-determinism=` (default 3). **No production override flag** — deliberately absent.

**Exit codes:** `0` = all hard checks passed. `1` = a hard invariant failed (compliance violation, determinism mismatch, read-only row-count change, wrong-side type, or pipeline error). `2` = pre-flight refusal (production env, or empty corpus).

---

## 6. One small additive change to C6 (for confidence/coverage validation)

`OrchestratedMatchResult::matches()` intentionally returns a slim projection (`listing_type, listing_id, tier, value`). Scenario #7 needs `confidence`/`coverage`. Add an **additive** accessor `OrchestratedMatchResult::rankedMatches(): array` that returns `array_map(fn($m) => $m->toArray(), $ranked->matches)` — full per-match detail (already includes confidence, coverage, cleared/shortfall/gaps via `RankedMatch::toArray()`). **No existing method or test changes** — `matches()` and `toArray()` stay exactly as shipped, so all C6 tests remain green. **OD-4.**

---

## 7. Files to add / modify

### Add
| Path | Purpose |
|---|---|
| `app/Console/Commands/MatchingValidate.php` | The `matching:validate` command: guards, options, orchestrates the runner, writes files, prints the table, sets the exit code. |
| `app/Services/Dna/Relevance/Validation/MatchingValidationRunner.php` | The testable core: roster → scenarios → `MatchingV2Service` → checks → `ValidationReport`. Read-only. |
| `app/Services/Dna/Relevance/Validation/ValidationRosterBuilder.php` | Read-only per-category subject discovery (and `--roster` load). |
| `app/Services/Dna/Relevance/Validation/ValidationReport.php` | Immutable report VO: per-scenario results, summary rows, `toArray()`, `hasHardFailure()`. |
| `tests/Feature/Dna/MatchingValidationRunnerTest.php` | Runner correctness + safety (below). |
| `tests/Feature/Dna/ValidationRosterBuilderTest.php` | Roster discovery per category. |
| `tests/Feature/Dna/MatchingValidateCommandTest.php` | Command end-to-end, guards, exit codes, file output. |

### Modify (additive only)
| Path | Change | Risk |
|---|---|---|
| `app/Services/Dna/Relevance/OrchestratedMatchResult.php` | add `rankedMatches()` accessor (§6). | Low — additive; no existing test touched. |

**Not modified:** `MatchingV2Service`, `DnaMatchService`, the §F6 kernels, discovery/narrowing, config, generators, migrations. No `AppServiceProvider` binding (concrete autowire). No routes.

---

## 8. Tests that prove it is SAFE

- **Read-only invariant** (`MatchingValidationRunnerTest`): seed a full corpus, run the whole roster, assert row counts across `dna_scores`, all four `*_agent_auctions`, and their `*_metas` are **unchanged**.
- **Flag is transient** : after a run, `config('matching.v2_enabled')` equals its pre-run value (force-enable restored).
- **Production guard** (`MatchingValidateCommandTest`): with `$this->app['env'] = 'production'`, the command exits non-zero, calls the pipeline **zero** times, and writes **no** files.
- **Empty-corpus guard**: with 0 `dna_scores` rows, exits `2` with guidance and writes nothing.
- **Compliance check has teeth (both directions):** seed a corpus containing a senior-restricted listing + a non-eligible seeker → the compliance scenario **FAILS** (hard) and the command exits non-zero; seed a clean corpus → it **PASSES**. Proves the safety check catches real violations, not just green-washes.
- **Determinism check has teeth:** double-run identical → determinism PASS; (optionally) a stubbed non-deterministic result → FAIL.
- **Type preservation surfaced:** mixed seller/landlord pool with colliding ids → each match's `listing_type` correct in the written JSON.
- **No-DNA scenario:** subject with no scores → `determined=0`, `undetermined=considered`, PASS (no crash).
- **Writes only to the given `--out`:** assert files appear under a temp `--out` dir and nowhere else; assert no product-table rows created.
- **Exit codes:** clean corpus → `0`; seeded compliance violation → `1`; production/empty → `2`.

SQLite/Postgres per existing `tests/*/Dna` conventions; high ids to avoid seed collisions; `--out` pointed at a temp dir per test.

---

## 9. Owner decisions

| ID | Decision | Options | Recommendation |
|---|---|---|---|
| **OD-1** | Pipeline invocation. | call `MatchingV2Service` directly · shell `matching:preview` per subject. | **Direct** — identical result, faster, no re-boot; still read-only. |
| **OD-2** | Roster source. | auto-discover (default) with optional `--roster` pin · pinned only. | **Auto-discover + optional pin** — works in any staging env, reproducible when pinned. |
| **OD-3** | Output location. | `storage/app/matching-validation/` (non-public, default) · custom `--out`. | **Non-public storage default**, overridable. Never `public/`. |
| **OD-4** | Confidence/coverage access. | additive `rankedMatches()` on the C6 VO · leave the VO, skip confidence detail. | **Additive accessor** — no existing test touched; enables scenario #7. |
| **OD-5** | Hard vs advisory checks. | as tabled (compliance/determinism/read-only/wrong-side = hard; quality = advisory) · make quality hard too. | **As tabled** — quality needs human judgment; only invariants fail the run. |
| **OD-6** | Production safety. | abort on production, no override (recommended) · allow `--force`. | **Abort, no override.** |

---

## 10. Out of scope

- DNA **generation** (separate manual Phase-0 staging step; the harness never enables/runs it).
- **Persisting** match results or any DB writes (C7 territory).
- **Caching**, marketplace-scale batch scoring.
- **UI/API / Match Check** exposure.
- Enabling `MATCHING_V2_ENABLED` in production.
- Automated "ground-truth" quality scoring — the harness surfaces the numbers; humans judge match quality (advisory).

---

## 11. Summary of the ask

Approve **OD-1…OD-6**. On approval, C6.1 ships as one isolated, read-only, staging-only commit: the `matching:validate` command + runner + roster builder + report VO, the single additive `rankedMatches()` accessor, and the §8 safety tests. It enables the runbook to be executed as one batch and produces the evidence that decides whether **C7 = persistence/caching or Match Check exposure**. **No code until approved.**
