# Match Check — Phase 4 · git-C8 Scope

**Status:** Scope / design doc — **no implementation is changed by this doc commit (docs-only).**
**Purpose:** Define git-C8 — the slice that **wires `MatchCheckCriteriaLoader` (git-C7) into
`MatchCheckOrchestrator::evaluate()`**, closing the `CRITERIA_NOT_LOADED` seam so the backend
pipeline can produce a `SCORED` result end-to-end without an externally-supplied payload.

> **Numbering:** This uses the reconciled **git-C\<n\>** convention (see
> `docs/match-check-phase4-numbering-reconciliation.md`, approved). git-C8 has **no** build-plan
> equivalent — it is a consequence of git having decomposed the loader out of the orchestrator.
> It is **not** the Matching V2 series and touches none of it.

Baseline: HEAD **`3847d52cd`** (git-C7, `MatchCheckCriteriaLoader`, inert/unwired).
Architectural stance: **(i)** — grow the committed `MatchCheckOrchestrator` into the Plan-C9
orchestrator (approved), rather than replacing it with a separate service.

---

## 1. The seam being closed

git-C6 defined `MatchCheckResult::CRITERIA_NOT_LOADED` as an explicit deferred seam, and
`evaluate()` still takes the payload as an **external, nullable** argument:

```php
// current (git-C6/C7)
public function evaluate(BridgeProperty $listing, User $user, ?BuyerCriteriaPayload $criteria = null): MatchCheckResult
{
    $preparation = $this->prepare($listing, $user);
    $scorer = $this->scorer ?? new MatchCheckScorer(new BuyerMatchScorer());
    return $scorer->score($preparation, $listing, $criteria);   // $criteria === null ⇒ CRITERIA_NOT_LOADED
}
```

git-C7 built `MatchCheckCriteriaLoader::load(MatchCheckPreparation, User): ?BuyerCriteriaPayload`
— the producer of that payload — but **nothing calls it**. git-C8 connects the two so that, when
no payload is supplied and the preparation actually has a preferred criteria record, `evaluate()`
loads it and scores.

---

## 2. What git-C8 does

Two surgical edits to **one committed file** (`MatchCheckOrchestrator.php`), plus tests.

### 2.1 Constructor — add a nullable loader (backward-compatible)

Add a **5th, nullable, default-null** constructor argument, after the existing nullable `$scorer`:

```php
public function __construct(
    private readonly ListingVisibilityGate $visibilityGate,
    private readonly CriteriaIntentDetector $intentDetector,
    private readonly CriteriaListingResolver $criteriaResolver,
    private readonly ?MatchCheckScorer $scorer = null,
    private readonly ?MatchCheckCriteriaLoader $criteriaLoader = null,   // ← new
) {}
```

The existing 3-arg (`prepare()`-only tests) and 4-arg (`$scorer`-injected) constructions remain
valid and unchanged.

### 2.2 `evaluate()` — auto-load only in the exact seam state

Signature is **unchanged**. The `?BuyerCriteriaPayload $criteria = null` parameter stays and keeps
its meaning: an explicitly-supplied payload is used verbatim.

```php
public function evaluate(BridgeProperty $listing, User $user, ?BuyerCriteriaPayload $criteria = null): MatchCheckResult
{
    $preparation = $this->prepare($listing, $user);

    // git-C8: close the CRITERIA_NOT_LOADED seam. Auto-load a payload ONLY when the caller
    // supplied none AND the preparation actually resolved a preferred criteria record. Any
    // explicit $criteria is honored as-is (override preserved). Disabled/blocked/no-criteria
    // preparations have hasPreferredCriteria() === false, so the loader is never constructed
    // or called — the inert guarantee is intact while the flag is OFF.
    if ($criteria === null && $preparation->hasPreferredCriteria()) {
        $loader = $this->criteriaLoader ?? new MatchCheckCriteriaLoader(
            new BuyerCriteriaLoader(),
            new TenantCriteriaLoader(),
            new BuyerOfferListingCriteriaLoader(),
            new TenantOfferListingCriteriaLoader(),
            $this->criteriaResolver,               // reuse the already-injected resolver
        );
        $criteria = $loader->load($preparation, $user);
    }

    $scorer = $this->scorer ?? new MatchCheckScorer(new BuyerMatchScorer());
    return $scorer->score($preparation, $listing, $criteria);
}
```

**The auto-load guard** — `$criteria === null && $preparation->hasPreferredCriteria()` — is the
whole slice. Its two clauses guarantee:
- **Override preserved:** a non-null `$criteria` skips the loader entirely (identical to today).
- **Inert / minimal:** `hasPreferredCriteria()` is true only in the `READY`-with-record state
  (`disabled`/`blocked`/`READY`-without-record all carry `preferredCriteria === null`). So with
  the flag OFF (default) → `prepare()` returns `disabled` → guard false → **loader never built or
  called, no DB read**. Same for a blocked listing or a user with no criteria.

**Default-loader construction** mirrors the existing scorer default (inline `new`, no container),
and is built **lazily** only when an auto-load is actually needed — so existing tests that never
reach this branch need no container and no loader. All five dependencies are no-arg constructible
(verified), and the already-injected `CriteriaListingResolver` is reused for the resolver arg.

### 2.3 End-to-end result after git-C8 (flag ON, visible, has preferred record)

| Loader outcome | `evaluate()` result |
|----------------|---------------------|
| returns a valid `BuyerCriteriaPayload` | **`SCORED`** (engine runs) — seam closed |
| returns `null` (record gone / inaccessible / incomplete) | **`CRITERIA_NOT_LOADED`** (unchanged, scoreless) |

All other preparation states (`disabled`, `blocked`, `READY`-no-record) are unchanged.

---

## 3. Files changed / added

| File | Kind | Purpose |
|------|------|---------|
| `app/Services/Stellar/MatchCheck/MatchCheckOrchestrator.php` | **edit** | Add nullable `$criteriaLoader` ctor arg (§2.1) + the auto-load guard in `evaluate()` (§2.2). Adds 5 `use` imports (the loader + the four criteria loaders). `prepare()` untouched. |
| `tests/Unit/Stellar/MatchCheck/MatchCheckOrchestratorTest.php` | **edit** | Update the one test whose contract changes; add auto-load path tests (§5). |

No new production class. **No** config, migration, route, controller, Blade, Livewire, job,
provider, `.env`, or CLAUDE.md change. `MatchCheckCriteriaLoader` (git-C7) is unchanged — only
now consumed.

---

## 4. Backward compatibility / preserved override

- `evaluate($listing, $user, $explicitPayload)` with a **non-null** payload behaves **exactly** as
  today — the loader is never consulted. (Existing test
  `evaluate_visible_with_criteria_and_payload_returns_a_scored_result` stays green, unmodified.)
- The 3-arg and 4-arg constructor forms remain valid; the new arg is nullable/defaulted.
- Flag-OFF behavior is byte-for-byte identical (`evaluate_with_flag_off_…` and
  `evaluate_defaults_the_scorer_when_none_is_injected` stay green, unmodified).

---

## 5. Test plan

> **Test-double note:** `MatchCheckCriteriaLoader` is `final` (git-C7, not modified here), so it
> cannot be mocked directly. Tests instead inject a **real** `MatchCheckCriteriaLoader` wrapping
> **mocked leaf dependencies** (the four criteria loaders + `CriteriaListingResolver`, none of which
> are final) — the same idiom these tests already use for a real `MatchCheckScorer` around a mock
> `BuyerMatchScorer`. Two helpers: `loaderReturning($type, $flat)` (leaf `loadById` returns a flat
> array or null) and `neverCalledLoader()` (every dependency asserts it is never touched).

**One existing test changes** (its contract genuinely changes — a no-payload READY case now
auto-loads):
- `evaluate_visible_with_criteria_but_no_payload_returns_criteria_not_loaded` — inject a loader
  (`loaderReturning('tenant', null)`) whose leaf yields no record; assert the loader was consulted
  and the result is still `CRITERIA_NOT_LOADED`. (Without the injected loader, the default loader
  would attempt a real DB read via the reused resolver — so injection is required and is the point.)

**New tests:**
1. `evaluate_auto_loads_payload_when_none_supplied_and_scores` — loader mock returns a valid
   `BuyerCriteriaPayload`; scorer engine mock returns a `BuyerMatchResult`; assert **`SCORED`** with
   the mapped score fields. (The core git-C8 behavior.)
2. `evaluate_with_explicit_payload_never_calls_the_loader` — pass an explicit payload; loader mock
   `shouldNotReceive('load')`; assert `SCORED`. (Override preserved.)
3. `evaluate_flag_off_never_constructs_or_calls_the_loader` — flag OFF; loader mock
   `shouldNotReceive('load')`; assert `DISABLED`. (Inertness.)
4. `evaluate_blocked_listing_never_calls_the_loader` — flag ON, not IDX-visible; loader mock
   `shouldNotReceive('load')`; assert `BLOCKED`.
5. `evaluate_ready_without_preferred_record_never_calls_the_loader` — flag ON, visible,
   `resolvePreferred` → `null`; loader mock `shouldNotReceive('load')`; assert `NO_CRITERIA`.

Full MatchCheck suite must stay green; the git-C7 `MatchCheckCriteriaLoaderTest` is unaffected.

---

## 6. Out of scope for git-C8

- MLS#/address **lookup** front-end (`BridgeListingLookupService`) — that is **git-C13** (Plan-C9).
- The rich `MatchReport` DTO / builder blocks / detailed mapper (git-C9/C10/C11 = Plan-C5/C6/C7).
- `LocationDnaEnrichmentGuard` / any Location DNA work (git-C12 = Plan-C8, F6).
- Any **route, controller, middleware wiring, Livewire, or Blade/UI**.
- Any change to scoring math (`BuyerMatchScorer`) or `BuyerMatchService::match()`.
- **Enabling** `mls_match_check.enabled` or any `.env` change.
- Persistence, caching, queues, events, external/Bridge API calls, writes of any kind.
- The Matching V2 series — separate track, untouched.

---

## 7. Inertness / gating guarantees

- **Flag OFF (default) ⇒ fully inert.** `prepare()` returns `disabled` → the auto-load guard is
  false → the loader is **neither constructed nor called**; no DB read occurs. `evaluate()` returns
  `MatchCheckResult::disabled()` exactly as today.
- **No new reachability.** git-C8 adds no route/controller/UI; `MatchCheckOrchestrator` remains
  unwired to any consumer surface. The only new runtime edge is *internal* to `evaluate()` and only
  fires under flag ON + visible + a resolved preferred record.
- **Reads only.** The sole new work is the loader's already-scoped `SELECT` (via `loadById`) — no
  writes, persistence, queue, event, external API, or enrichment.
- **Fails closed.** A loader miss keeps the pre-git-C8 `CRITERIA_NOT_LOADED` outcome; nothing throws.

---

## 8. Risk / rollback

- **Blast radius:** one file's `evaluate()` gains a guarded branch; `prepare()` and all other states
  are untouched. Live consumer surface: still none (unwired). Risk: **low**, and zero while OFF.
- **Rollback:** revert the git-C8 commit — removes the ctor arg + the guard branch and restores the
  externally-supplied-payload-only behavior; the git-C7 loader returns to being an unused component.
- **Numbering guard:** commit as `feat(match-check): Phase 4 git-C8 — wire MatchCheckCriteriaLoader
  into evaluate() (close CRITERIA_NOT_LOADED seam)`; do not reuse the retired bare "C5/C6/C7" labels.

---

## 9. Open decision for the owner (before coding)

None required beyond the already-approved stance (i) and the git-C\<n\> convention. Confirm to
proceed to implementation, or redirect.

---

*This document changes no production code, tests, or config, and stages no unrelated file. It is to
be committed path-scoped as the only file in its commit (when git-C8 lands, alongside its two code
files).*
