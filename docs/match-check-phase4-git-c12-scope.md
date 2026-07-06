# Match Check — Phase 4 · git-C12 Scope

**Status:** Scope / design doc — **no implementation is changed by this doc commit (docs-only).**
**Purpose:** Define git-C12 — `LocationDnaEnrichmentGuard`: the additive, **pure, side-effect-free**
decision service that answers *"is Match Check allowed to trigger Location DNA enrichment for this
(listing, user) right now?"* It encodes the **F6 throttle contract** — per-listing cooldown (dedupe)
+ per-user hourly rate limit — that already lives, forward-declared and inert, in
`config/mls_match_check.php`. The guard is **unwired**: nothing dispatches enrichment through it in
this slice.

> **Numbering:** git-C\<n\> convention. git-C12 == **Plan-C8** == **F6** (see
> `docs/match-check-phase4-numbering-reconciliation.md` §6, line "git-C12", approved). Not the
> Matching V2 series; touches none of it.

Baseline: HEAD **`006973e90`** (git-C11, `mapOneDetailed()` + non-residential reconciliation).
Architectural stance: **(i)** — grow toward the Plan-C9 orchestrator; the guard is a composable
piece git-C13 will route enrichment through.

---

## 0. Divergence from the reconciliation's original C12 sketch (owner decision)

`docs/match-check-phase4-numbering-reconciliation.md` (line 125) sketched git-C12 as
"`LocationDnaEnrichmentGuard` **+ `dispatchDna` opt-out param** — default-preserving edit to lookup."
That sketch bundled **two** things: (a) the new guard service, and (b) an **edit to the enrichment
dispatch path** (adding an opt-out parameter so the lookup can decline enrichment).

Per the owner's instruction for this slice, git-C12 is **narrowed to (a) only** — additive/inert,
no edit to any dispatch, lookup, job, or queue path. The dispatch-side change (routing enrichment
**through** the guard, incl. any `dispatchDna` opt-out param) moves to **git-C13**, which the
reconciliation already frames as *"route enrichment through the guard"* (line 126). This keeps C12
a pure new service with **zero** production behavior change and no queue/dispatch surface touched.

---

## 1. Why this is the next slice, and the contract it fixes

The consumer Match Check flow (`/match-check`, git-C14) will, for a looked-up MLS listing, want fresh
**Location DNA** (POI / flood / school / commute). Enrichment is **external, paid, and rate-bearing**
(Google Places, FEMA, Census, commute providers). A consumer-driven "check this address" surface
that dispatched `ComputeLocationDna` on every view would allow **unbounded user-driven external
enrichment** — the exact hazard the config's F6 block names:

> *"Match Check must never trigger unlimited user-driven external enrichment. Per-listing cooldown
> (dedupe) + per-user hourly rate limit."* — `config/mls_match_check.php`

The config keys exist but **no code reads them**. git-C12 supplies the missing decision logic: a
guard that, given the current throttle state, returns an **allow / deny + reason + retry-after**
decision the eventual dispatcher (git-C13) consults **before** dispatching enrichment. F6 becomes
enforceable; nothing is enforced *yet* because the guard is unwired.

---

## 2. The guard/throttle contract

### 2.1 Decision surface

```php
final class LocationDnaEnrichmentGuard
{
    public function decide(
        string $listingType,
        int $listingId,
        int $userId,
        EnrichmentThrottleSnapshot $state,   // read-only state, supplied by the caller (see §2.4)
        CarbonInterface $now,                 // injected clock — deterministic, testable
    ): EnrichmentGuardDecision;
}
```

- **Pure**: `decide()` performs **no** cache read, no DB read, no `RateLimiter::hit()`, no dispatch,
  no logging. It is a total function of `(listingType, listingId, userId, state, now, config)`. Same
  inputs → same output. This is what makes C12 inert *and* trivially unit-testable, mirroring the
  "pure decision, no I/O" ethos of the C5–C11 pieces.
- **Signature shape** mirrors the codebase's existing throttle idiom (`AskAiRateLimitService::check(
  Request, listingType, listingId)`) and the enrichment job (`ComputeLocationDna(listingType,
  listingId)`), so wiring in git-C13 is a natural fit.

### 2.2 `EnrichmentGuardDecision` (new DTO)

Mirrors the `VisibilityDecision` idiom (`final class`, promoted `public readonly` props, named static
factories):

```php
final class EnrichmentGuardDecision
{
    public function __construct(
        public readonly bool   $allowed,
        public readonly string $reason,               // one of the REASON_* constants below
        public readonly ?int   $retryAfterSeconds,    // null when allowed or when N/A
    ) {}

    public const REASON_ALLOWED          = 'allowed';
    public const REASON_FEATURE_DISABLED = 'feature_disabled';
    public const REASON_COOLDOWN_ACTIVE  = 'cooldown_active';   // per-listing dedupe
    public const REASON_RATE_LIMITED     = 'rate_limited';      // per-user hourly cap

    public static function allow(): self { return new self(true, self::REASON_ALLOWED, null); }
    public static function deny(string $reason, ?int $retryAfterSeconds = null): self
    {
        return new self(false, $reason, $retryAfterSeconds);
    }
}
```

The **machine-readable `reason`** lets git-C13/C14 branch (serve cached vs. show "try again later"
vs. silently no-op) without re-deriving the check — same rationale as `VisibilityDecision::$reason`.

### 2.3 Decision rules & precedence

Evaluated in this fixed order; the **first** matching rule short-circuits:

| # | Rule | Condition | Result |
|---|------|-----------|--------|
| 1 | **Feature gate** | `! config('mls_match_check.enabled')` (default) | `deny(FEATURE_DISABLED)`, `retryAfter = null` |
| 2 | **Per-listing cooldown (dedupe)** | listing was enriched < `dna_cooldown_hours` ago | `deny(COOLDOWN_ACTIVE)`, `retryAfter = cooldownEnd − now` |
| 3 | **Per-user hourly rate limit** | user's enrichment attempts in the trailing hour ≥ `dna_rate_limit_per_user_hourly` | `deny(RATE_LIMITED)`, `retryAfter = windowReset − now` |
| 4 | **Allow** | none of the above | `allow()`, `retryAfter = null` |

**Precedence rationale:**
- **Flag first** — mirrors `MatchCheckOrchestrator::isEnabled()` exactly (single source of truth,
  `config('mls_match_check.enabled', false)`). Guarantees the guard is a no-op-equivalent (always
  denies) while the master flag is OFF, even if a future caller forgets to check the flag itself.
- **Cooldown before rate-limit** — the per-listing cooldown is a **dedupe**: if this listing already
  has fresh Location DNA, the correct outcome is "serve the cached data," and that should **not**
  spend one of the user's scarce hourly enrichment tokens. Checking cooldown first means a user
  repeatedly viewing the same fresh listing never burns rate-limit budget. Rate-limit then guards
  only genuinely *new* enrichment work.

**Config source (reads only, no new keys):** the guard reads the **existing**
`mls_match_check.enabled`, `mls_match_check.dna_cooldown_hours` (24), and
`mls_match_check.dna_rate_limit_per_user_hourly` (20) via `config()`. This is a **read**, consistent
with `MatchCheckOrchestrator` reading `mls_match_check.enabled`; **no config key is added, renamed,
or changed**, and `enabled` stays default **OFF**.

### 2.4 `EnrichmentThrottleSnapshot` — the read-only state input (state I/O deferred to git-C13)

A throttle inherently needs two pieces of *state* to decide: **when the listing was last enriched**
and **how many times this user has attempted enrichment in the current window**. Reading that state
from a cache/RateLimiter store — and **recording** a new attempt/enrichment after an allow — are
**writes/reads against persistence**, which this slice explicitly excludes. So C12 keeps the guard
pure by having the **caller supply the state** as an immutable value object:

```php
final class EnrichmentThrottleSnapshot
{
    public function __construct(
        public readonly ?CarbonInterface $listingLastEnrichedAt,  // null = never enriched
        public readonly int              $userAttemptsInWindow,   // count in the trailing hour
        public readonly ?CarbonInterface $userWindowResetsAt,     // when the oldest attempt ages out (for retryAfter)
    ) {}
}
```

- git-C12 ships the guard + this VO + a **test double / array-driven snapshot** for unit tests.
- git-C13 supplies the **production** snapshot (built from `RateLimiter`/`Cache` reads, following
  the `AskAiRateLimitService` idiom) **and** performs the **`RateLimiter::hit()` recording** after
  an `allow()` — those reads and writes are that slice's job, not this one. C12 does **not** ship a
  cache-backed store, so it adds **zero** live persistence dependency.

*(§8 open-decision 1 records the alternative — an injected `ThrottleStore` read-port interface —
and why the plain snapshot is recommended for the inert slice.)*

---

## 3. What the guard deliberately does NOT do (inert guarantees)

- **Does not dispatch** `ComputeLocationDna` (or any job) — no queue interaction.
- **Does not read or write** cache, `RateLimiter`, DB, or files — pure function; state is passed in.
- **Does not call** Google Places / FEMA / Census / commute providers, directly or transitively.
- **Is not constructed or called** by any route, controller, Livewire component, middleware, job,
  or the `MatchCheckOrchestrator` — it is unwired. (git-C13 wires it.)
- **Changes no existing enrichment behavior**: `ComputeLocationDna`, `LocationDnaPipelineRunner`,
  `LocationDnaEnrichmentRunner`, and every existing dispatch/import path are **byte-for-byte
  unchanged** — the "import paths unchanged by default" property the reconciliation required of C12.

---

## 4. Compliance (F7/F9) — not applicable to this slice

The guard handles no listing content — only `(listingType, listingId, userId)` identifiers, a
throttle-state snapshot, and a clock. It never touches `raw_json`, `PublicRemarks`, PII, lockbox, or
showing instructions, so there is no compliance surface to regress. (The standalone compliance
regression remains git-C15/Plan-C11.)

---

## 5. Files changed / added

| File | Kind | Purpose |
|------|------|---------|
| `app/Services/Stellar/MatchCheck/LocationDnaEnrichmentGuard.php` | **new** | The pure `decide()` guard (§2.1, §2.3). Reads existing config; no I/O. |
| `app/Services/Stellar/MatchCheck/EnrichmentGuardDecision.php` | **new** | Result DTO (§2.2), `VisibilityDecision` idiom. |
| `app/Services/Stellar/MatchCheck/EnrichmentThrottleSnapshot.php` | **new** | Read-only state input VO (§2.4). |
| `tests/Unit/Stellar/MatchCheck/LocationDnaEnrichmentGuardTest.php` | **new** | Contract + precedence + retry-after + flag-OFF tests (§6). |
| `docs/match-check-phase4-git-c12-scope.md` | **new** | This doc. |

**No** config, migration, route, controller, Blade, Livewire, middleware, job, provider, `.env`, or
CLAUDE.md change. **No** edit to `ComputeLocationDna`, `LocationDnaPipelineRunner`,
`LocationDnaEnrichmentRunner`, `MatchCheckOrchestrator`, the scorer, builder, mapper, DTOs, or the
git-C7 loader. The guard is **unwired** (git-C13 routes enrichment through it).

---

## 6. Test plan

`LocationDnaEnrichmentGuardTest` (SQLite in-memory, pure — no cache/DB/RateLimiter needed because
state is injected):

1. **Flag OFF (default) → denied** — with `mls_match_check.enabled` at its default `false`,
   `decide()` returns `deny(FEATURE_DISABLED)`, `retryAfter = null`, **regardless** of a fresh
   snapshot that would otherwise pass (proves rule 1 short-circuits first, matching the orchestrator).
2. **Cooldown active → denied + dedupe** — flag ON, `listingLastEnrichedAt` = `now − 1h`,
   `dna_cooldown_hours` = 24 → `deny(COOLDOWN_ACTIVE)` with `retryAfter ≈ 23h` in seconds; assert the
   user's rate-limit budget is **not** consulted (checked before rate-limit).
3. **Cooldown expired → not blocked by cooldown** — `listingLastEnrichedAt` = `now − 25h` → cooldown
   rule passes; falls through to rate-limit / allow.
4. **Never enriched → not blocked by cooldown** — `listingLastEnrichedAt = null` → cooldown passes.
5. **Rate limit reached → denied** — flag ON, cooldown clear, `userAttemptsInWindow` = 20,
   `dna_rate_limit_per_user_hourly` = 20 → `deny(RATE_LIMITED)`, `retryAfter = windowResetsAt − now`.
6. **Under rate limit → allowed** — `userAttemptsInWindow` = 19, cooldown clear → `allow()`,
   `retryAfter = null`.
7. **Precedence: cooldown wins over rate-limit** — both a fresh cooldown AND an exhausted
   rate-limit hold → reason is `COOLDOWN_ACTIVE` (not `RATE_LIMITED`), proving order.
8. **Config-driven thresholds** — override `dna_cooldown_hours` / `dna_rate_limit_per_user_hourly`
   via `config()->set(...)` in the test and assert the boundary moves accordingly (guard reads
   config, no literals).
9. **Determinism** — same inputs twice → identical decision (no hidden state/clock).
10. **DTO shape** — `EnrichmentGuardDecision::allow()` / `::deny()` factories set `allowed`,
    `reason`, `retryAfterSeconds` as documented.

Full MatchCheck + Matching + `tests/Unit/Stellar/` suites stay green (modulo the pre-existing,
unrelated `LocationDnaRoundTripTest` failure).

---

## 7. Out of scope for git-C12

- **Wiring** the guard into any caller / dispatch — git-C13 (route enrichment through the guard,
  incl. any `dispatchDna` opt-out param).
- The **production throttle store**: `RateLimiter`/`Cache` reads to build the snapshot **and** the
  `RateLimiter::hit()` recording after an allow — git-C13.
- Any edit to `ComputeLocationDna`, `LocationDnaPipelineRunner`, `LocationDnaEnrichmentRunner`, or
  any existing enrichment/import/queue path.
- MLS#/address **lookup** front-end + emitting `MatchReport` — git-C13 (Plan-C9).
- Any route/controller/Blade/UI (git-C14), the standalone compliance test (git-C15), or the
  better-matches tail (git-C16).
- Enabling `mls_match_check.enabled`; any `.env`/config/persistence change; AI narrative (F8).
- The Matching V2 series — separate track, untouched.

---

## 8. Open decisions for the owner (before coding)

1. **State delivery — plain snapshot (recommended) vs. injected read-port.** C12 can pass state as
   the immutable `EnrichmentThrottleSnapshot` (guard stays a pure function; caller owns all I/O), or
   define an `EnrichmentThrottleStore` interface the guard calls (`lastEnrichedAt()`,
   `attemptsInWindow()`). Recommendation: **plain snapshot** — it keeps C12 provably I/O-free and
   maximally testable, and defers *all* cache coupling to git-C13, which needs a store anyway for
   the write/`hit()` side. Confirm.
2. **Cooldown vs. rate-limit precedence.** Recommendation: **cooldown first** (dedupe before spending
   a rate-limit token; §2.3 rationale). Confirm, or invert if you'd rather a fresh-listing re-view
   still counts against the hourly cap.
3. **Rate-limit boundary — `≥` vs. `>`.** Recommendation: deny when `attempts ≥ limit` (the Nth
   attempt is the last *allowed* one; the 21st is blocked at limit 20), matching `RateLimiter::
   tooManyAttempts` semantics used by `AskAiRateLimitService`. Confirm.
4. **`retryAfterSeconds` on `FEATURE_DISABLED`.** Recommendation: `null` (a disabled feature has no
   meaningful retry time; the caller shows a 404/hidden surface, not "try again in N s"). Confirm.
5. **Guard reads `config()` directly vs. constructor-injected thresholds.** Recommendation: read
   `config()` directly (mirrors `MatchCheckOrchestrator::isEnabled()`; single source of truth, no new
   wiring). Confirm.

---

*This document changes no production code, tests, or config, and stages no unrelated file. It is to
be committed path-scoped alongside git-C12's code + test when that slice lands.*
