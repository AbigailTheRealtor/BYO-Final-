# Match Check — Phase 4 · Wave 2 / C7 Scope

**Status:** Scope / design doc — **no implementation is changed by this doc commit (docs-only).**
**Purpose:** Define the next inert, additive Match Check slice (**C7**) against the current
committed boundary, before any code is written. This document changes no production code, test,
or config.

> ⚠️ **Naming collision (same warning as the C5 doc):** there are **two independent `C<n>`
> series** in this repo. This doc concerns the **Match Check** series
> (`feat(match-check): … Phase 4 Wave N/CN`). It is **NOT** the **Matching V2** series
> (`feat(matching): … §MatchingV2 C6/C6.1/C7`), which numbers its own C7 (a gated,
> validation-blocked persistence slice). **Do not conflate the two.** C7 here has nothing to do
> with Matching V2 persistence and touches none of it.

---

## 1. Current committed boundary

C7 is based on the current committed Match Check tip:

| C# | Wave | Commit | State |
|----|------|--------|-------|
| C1 | 0 | `30b1bbca1` | master flag + `CheckMatchCheckEnabled` 404 gate (inert) |
| C2 | 1 | `957e0ddc9` | `ListingVisibilityGate` (F9) |
| C3 | 1 | `da091f84d` | `CriteriaIntentDetector` (F5) |
| C4 | 1 | `081a351c4` | `CriteriaListingResolver::resolvePreferred` (F5) |
| C5 | 2 | `e755ccbc9` | `MatchCheckOrchestrator::prepare()` + `MatchCheckPreparation` (inert) |
| **C6** | **2** | **`3384cc0a2`** | **`MatchCheckScorer` + `MatchCheckResult`, `evaluate()` (inert) ← C7 builds on this** |

C7 is authored on top of **C6 `3384cc0a2`** (the `read-only scoring/output layer`). It adds no
new dependency on any commit past that boundary.

---

## 2. The seam C7 fills (declared by C6 itself)

C6 already names its own next step. `MatchCheckResult` defines five terminal states; one of them
is an explicit deferred seam:

> `CRITERIA_NOT_LOADED` — flag ON, listing visible, a preferred criteria record exists, **but its
> scorable payload was not supplied to the scorer. This is the seam a later wave fills in
> (criteria → `BuyerCriteriaPayload` loading); until then the feature stays scoreless here.**
> — `app/Services/Stellar/MatchCheck/MatchCheckResult.php`

And `MatchCheckScorer::score()` / `MatchCheckOrchestrator::evaluate()` take the payload as an
**externally-supplied, nullable argument** (`?BuyerCriteriaPayload $criteria = null`); when it is
`null` the scorer short-circuits to `CRITERIA_NOT_LOADED` without touching the engine:

```php
// MatchCheckOrchestrator::evaluate()
public function evaluate(BridgeProperty $listing, User $user, ?BuyerCriteriaPayload $criteria = null): MatchCheckResult
```

Today nothing in the Match Check namespace can **produce** that `BuyerCriteriaPayload` from the
preferred-criteria descriptor that `prepare()` already resolves. **C7 supplies exactly that
producer** — and nothing else.

---

## 3. What C7 is

**C7 = `MatchCheckCriteriaLoader` — a standalone, read-only adapter** that converts the preferred
criteria *descriptor* carried on a `MatchCheckPreparation` into a `?BuyerCriteriaPayload`, by
**delegating to the existing per-type criteria loaders**. It invents no new field mapping.

### 3.1 What the preparation already carries

`MatchCheckPreparation` (C5) carries the resolved preferred-criteria **descriptor**, not a payload:

```php
public readonly ?array $preferredCriteria;  // array{id:int, type:string, label:string, created_at:Carbon}|null
public function hasPreferredCriteria(): bool // preferredCriteria !== null
```

`type` is one of `buyer` | `buyer_offer` | `tenant` | `tenant_offer` (the same vocabulary
`CriteriaListingResolver::resolveAccessible()` emits). The descriptor is an **id + type**, i.e.
enough to load the record but not the record itself.

### 3.2 The adapter

Proposed signature (read-only; exact naming to be confirmed at implementation):

```php
namespace App\Services\Stellar\MatchCheck;

final class MatchCheckCriteriaLoader
{
    public function __construct(
        private readonly BuyerCriteriaLoader              $buyerCriteriaLoader,
        private readonly TenantCriteriaLoader             $tenantCriteriaLoader,
        private readonly BuyerOfferListingCriteriaLoader  $buyerOfferLoader,
        private readonly TenantOfferListingCriteriaLoader $tenantOfferLoader,
        private readonly CriteriaListingResolver          $criteriaResolver,
    ) {}

    /** Read-only. Returns null (never throws) when no scorable payload can be produced. */
    public function load(MatchCheckPreparation $preparation, User $user): ?BuyerCriteriaPayload;
}
```

Behavior — a thin composition of machinery that **already exists**:

1. If `! $preparation->hasPreferredCriteria()` → return `null` (there is nothing to load; the
   scorer will map this to `NO_CRITERIA` on its own — the loader does not distinguish those states).
2. Resolve access scope via the existing `CriteriaListingResolver::resolveAllowedUserIds($user)`
   (already used across the criteria stack; agent → self + client ids, consumer → `[self]`).
3. Dispatch by `preferredCriteria['type']` to the matching existing loader's
   `loadById($id, $allowedUserIds): ?array` — **exactly the `match($type)` dispatch already used by
   `StellarBuyerResultsController::loadCriteriaById()`**. If the loader returns `null` (record gone
   / access denied / `property_types` unresolvable) → return `null`.
4. Wrap the returned flat array in `new BuyerCriteriaPayload($array)` inside a `try/catch
   (\InvalidArgumentException)` — mirroring `StellarBuyerResultsController` lines 108–117 — and
   return `null` on failure instead of letting the exception escape.
5. Otherwise return the constructed `BuyerCriteriaPayload`.

The adapter **reuses** `BuyerCriteriaLoader`, `TenantCriteriaLoader`,
`BuyerOfferListingCriteriaLoader`, `TenantOfferListingCriteriaLoader`,
`CriteriaListingResolver::resolveAllowedUserIds()`, and the `BuyerCriteriaPayload` constructor.
It duplicates **no** `mapRecord()` logic and adds **no** new EAV/native mapping.

### 3.3 Wiring: DEFERRED (recommended)

**C7 ships the loader UNWIRED.** It is not injected into `MatchCheckOrchestrator::evaluate()` and
is not referenced by any route, controller, Livewire, Blade, job, or other service. This mirrors
the C2–C4 discipline (independent inert input pieces landed *before* the composition step that
consumes them). The one-line composition — having `evaluate()` call the loader to fill its own
`?BuyerCriteriaPayload` seam — is a **separate, later slice** (provisionally "C7-wire" / the C8
approach), because that step is what first makes an ON-flagged, visible listing reach the scoring
engine end-to-end, and it deserves its own isolated commit + tests.

> **Rationale for unwired:** keeps C7 purely additive — **zero edits to any file committed at or
> before C6**, so every existing `prepare()`/`evaluate()`/scorer test is untouched, and the change
> cannot alter any runtime path (the class has no callers). Lowest possible blast radius.

An alternative (wire-in-now) is viable and would still be inert while the flag is OFF, but it edits
`MatchCheckOrchestrator` (a committed file) and touches `evaluate()`'s behavior in the ON state.
**Recommendation: unwired.** Flag for owner confirmation in §7.

---

## 4. Files C7 will add (on approval)

Purely additive — **no existing file is modified** under the recommended unwired form:

| File | Kind | Note |
|------|------|------|
| `app/Services/Stellar/MatchCheck/MatchCheckCriteriaLoader.php` | **new** | The read-only adapter described in §3.2. |
| `tests/Unit/Stellar/MatchCheck/MatchCheckCriteriaLoaderTest.php` | **new** | Unit coverage (§6); injected loaders faked/mocked, no DB. |

No config change. No migration. No route/controller/Blade/Livewire. No `.env` change. No CLAUDE.md
change (C7 introduces no new env var or gate; it reuses the C1 master flag semantics indirectly and
adds none of its own).

---

## 5. Inertness / gating guarantees

- **No new flag, no flag flip.** C7 adds no config and does **not** enable `mls_match_check.enabled`
  (default remains OFF). It reuses no gate of its own.
- **Never reached at runtime.** Under the recommended unwired form the class has **no callers** —
  no route, job, or service references it — so it executes in tests only.
- **Even once wired (later slice), still gated.** The loader only becomes reachable through
  `evaluate()`, which short-circuits to `disabled()` while the master flag is OFF; and it is only
  invoked in the flag-ON + visible + `hasPreferredCriteria()` path.
- **Reads only.** `loadById()` performs a scoped `SELECT`; the payload constructor is pure. **No
  writes, no persistence, no queue dispatch, no external/Bridge API, no Location DNA enrichment.**
- **Fails closed.** Any inability to produce a valid payload (missing record, access mismatch,
  incomplete criteria) returns `null` → the downstream scorer stays in a scoreless state. The
  adapter never throws to its caller.

---

## 6. Test plan (C7)

`MatchCheckCriteriaLoaderTest` — pure unit, injected loaders faked/mocked, no DB, no scoring:

1. **No preferred criteria** (`hasPreferredCriteria() === false`) → returns `null`; no loader called.
2. **`type = buyer`** → dispatches to `BuyerCriteriaLoader::loadById()` with the resolved
   allowed-user-ids; a valid flat array yields a `BuyerCriteriaPayload`.
3. **`type = tenant` / `buyer_offer` / `tenant_offer`** → each dispatches to its matching loader
   (one case per branch; asserts the correct loader receives the call, others do not).
4. **Loader returns `null`** (record gone / access denied / `property_types` empty) → returns `null`.
5. **`BuyerCriteriaPayload` throws `InvalidArgumentException`** (incomplete data) → caught; returns
   `null`, exception does not escape.
6. **Access scope** → asserts `resolveAllowedUserIds($user)` output is the `allowedUserIds` passed
   to `loadById()` (consumer = `[self]`; agent = self + clients).

---

## 7. Out of scope for C7

- **Wiring the loader into `MatchCheckOrchestrator::evaluate()`** (the seam-closing composition) —
  deferred to a later slice per §3.3.
- Any **route, controller, middleware wiring, Livewire, or Blade/UI** for `/match-check`.
- Any change to **scoring math** (`BuyerMatchScorer`) or to `BuyerMatchService::match()`.
- Any **Location DNA enrichment** or its throttle (F6 — **Wave 4 / C8**).
- The **"better matches" low-score fallback** (F2 — **Wave 7 / C12**).
- **Enabling** the master flag or any `.env` change.
- Persistence, caching, or writes of any kind.
- **The Matching V2 series (its C6/C6.1/C7)** — a wholly separate track; untouched.

---

## 8. Risk / rollback

- **Blast radius: none.** Under the unwired form C7 adds two new files and edits nothing; with no
  callers it cannot alter any existing behavior, and every prior test is unaffected.
- **Rollback** = delete the two new files (or `git revert` the C7 commit) — clean, no dependents.
- **Naming-collision guard:** commit with the `feat(match-check): … Phase 4 Wave 2/C7` prefix to
  keep it distinct from any Matching V2 `C7` in history.

---

## 9. Open decision for the owner (before coding)

1. **Unwired (recommended) vs. wire-into-`evaluate()` now.** §3.3. Recommendation: **unwired** —
   maximally additive, zero edits to committed files. Confirm.
2. **Class name** `MatchCheckCriteriaLoader` (vs. e.g. `MatchCheckCriteriaPayloadLoader`). Cosmetic;
   default to the shorter name unless you prefer otherwise.

---

*This document changes no production code, tests, or config, and stages no unrelated file. It is to
be committed path-scoped as the only file in its commit. The pre-existing staged
`docs/match-check-phase4-c5-scope.md` is left untouched (see the checkpoint note below).*
