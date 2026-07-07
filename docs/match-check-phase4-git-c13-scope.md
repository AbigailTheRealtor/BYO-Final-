# Match Check — Phase 4 · git-C13 Scope

**Status:** Scope / design doc — **no implementation is changed by this doc commit (docs-only).**
**Purpose:** Define git-C13 — the slice that **composes the already-built Match Check pieces
end-to-end**: an **MLS#/ListingKey/address lookup front-end**, wiring the git-C8…C12 pieces
(preparation → visibility → intent → criteria → score) into a single call, **routing Location DNA
enrichment through the git-C12 `LocationDnaEnrichmentGuard`**, and **producing a rich `MatchReport`**
from a scored result. This is **Plan-C9** grown onto the as-built base (stance **(i)** — the committed
`MatchCheckOrchestrator` grows into the Plan-C9 orchestrator).

> **Numbering:** git-C\<n\> convention. git-C13 == **Plan-C9** == **F1/F2/F5/F6/F9** (see
> `docs/match-check-phase4-numbering-reconciliation.md` §6, line "git-C13", approved). Not the
> Matching V2 series; touches none of it.

Baseline: HEAD **`12bac2af`** (git-C12, `LocationDnaEnrichmentGuard` + `EnrichmentGuardDecision` +
`EnrichmentThrottleSnapshot`, inert/unwired).
Reconciliation framing (line 126): *"Grow orchestrator: MLS#/address lookup front-end + emit
`MatchReport` + route enrichment through the guard … Composes git-C8…C12; **still no route**."*

---

## 0. What already exists (so git-C13 composes, it does not rebuild)

The checkpoint from HEAD `12bac2af` confirms every downstream piece is already built; git-C13 is
almost entirely **wiring**:

| Piece | Where | git-C13 uses it for |
|-------|-------|---------------------|
| `BridgeListingLookupService::findByMlsNumber()` / `findByListingKey()` / `searchByAddress()` | `app/Services/Bridge/` | the MLS#/address **lookup front-end** (returns `PropertyCandidate`) |
| `PropertyCandidate` (`sourceRecordId` = `bridge_properties.id`) | `app/Services/Property/` | resolve the looked-up candidate back to a `BridgeProperty` model |
| `MatchCheckOrchestrator::prepare()` / `evaluate()` | `app/Services/Stellar/MatchCheck/` | flag → visibility (F9) → intent (F5) → criteria (F5) → score |
| `MatchCheckScorer` → `BuyerMatchScorer::score()` | `…/MatchCheck/`, `…/Matching/` | the numeric score (already wired via `evaluate()`) |
| `BuyerMatchResultBuilder::buildDetailed()` (git-C10) | `…/Matching/` | the F3 blocks: `whyNot` / `confidence` / `recommendations` (+ `build()`'s `whyThisMatches`/`tradeoffs`/`missingData`) |
| `MatchReport` DTO (git-C9) | `…/MatchCheck/` | the serializable report shape to emit |
| `LocationDnaEnrichmentGuard::decide()` + `EnrichmentThrottleSnapshot` (git-C12) | `…/MatchCheck/` | gate any MatchCheck-initiated enrichment (F6) |

**Field alignment (why MatchReport production is a projection, not new logic):** a
`buildDetailed()` `BuyerMatchResult` exposes exactly `listingKey, totalScore, categoryScores,
whyThisMatches, tradeoffs, missingData, whyNot, confidence, recommendations` — a near 1:1 map onto
`MatchReport`, which adds only `criteriaId`, `criteriaType`, `source`, injected `generatedAt`, and a
null `narrative`.

**Two facts that make the enrichment wiring low-risk:**
1. `BridgeListingLookupService` currently has **zero callers** in the app — it is a built-but-unwired
   seam. Threading a DNA opt-out through it disturbs **no** existing behavior.
2. The 10 existing `ComputeLocationDna::dispatch(...)` sites (ImportBridgeProperties, Seller/Landlord
   Livewire, observers, `AgentLocationDnaController`, backfill command) are **independent paths** that
   do **not** go through the lookup service, so they are untouched by this slice.

---

## 1. The four things git-C13 delivers

### 1.1 MLS#/address lookup front-end

A single entry that accepts a consumer's identifier and resolves it to a `BridgeProperty`:

```php
// on the grown MatchCheckOrchestrator (or a thin MatchCheckLookup collaborator — see §7 decision 1)
public function analyzeByMlsNumber(string $mlsNumber, User $user): MatchCheckAnalysis;
public function analyzeByListingKey(string $listingKey, User $user): MatchCheckAnalysis;
public function analyzeByAddress(array $addressParts, User $user): MatchCheckAnalysis; // 0/1/N candidates
```

- Delegates identifier→candidate resolution to `BridgeListingLookupService` (local-first, API-on-miss),
  then resolves `PropertyCandidate.sourceRecordId` → `BridgeProperty` for the scorer.
- **Address ambiguity (F1):** `searchByAddress()` may return 0/1/N. git-C13 defines the contract —
  **0** → `NOT_FOUND`; **1** → analyze it; **N** → `AMBIGUOUS` carrying the candidate list for the
  caller (git-C14 UI) to disambiguate. No auto-pick of a wrong unit.
- **Flag-gated first:** every entry short-circuits on `! isEnabled()` **before any lookup** — no API
  call, no DB read, no enrichment while the master flag is OFF (its default). This is the inert
  guarantee (§3), identical in spirit to `prepare()`/`evaluate()`.

### 1.2 End-to-end composition

For a resolved `BridgeProperty`, run the existing `evaluate($listing, $user)` (which already chains
prepare → visibility → intent → criteria-load → score) to get a `MatchCheckResult`. git-C13 wraps
that with the lookup outcome + enrichment decision + (when SCORED) a `MatchReport` into a single
composed return value:

```php
final class MatchCheckAnalysis   // new, serializable, data-only (like MatchCheckResult / MatchReport)
{
    public readonly string $status;          // NOT_FOUND | AMBIGUOUS | + the MatchCheckResult statuses
    public readonly ?MatchCheckResult $result;   // lean score VO (null for NOT_FOUND / AMBIGUOUS)
    public readonly ?MatchReport $report;        // rich report — populated ONLY when SCORED (§1.4)
    public readonly array $candidates;           // PropertyCandidate::toArray() list for AMBIGUOUS (facts only)
    public readonly string $enrichmentReason;    // EnrichmentGuardDecision::REASON_* (audit/telemetry)
}
```

### 1.3 Route Location DNA enrichment through the guard (F6)

MatchCheck must never trigger unbounded user-driven enrichment. So on the MatchCheck path:

1. Call the lookup with DNA auto-dispatch **suppressed** — add a **default-preserving opt-out param**
   to `BridgeListingLookupService` (`findBy…(/*…*/, bool $dispatchDna = true)`, threaded to
   `cacheRecord()`). Default `true` keeps the seam's *intended* prefill behavior byte-for-byte; the
   MatchCheck caller passes `false`.
2. Build an `EnrichmentThrottleSnapshot` from a **new production throttle store** (the state I/O
   git-C12 deferred): a per-listing cooldown marker + a per-user hourly counter, via `Cache` /
   `RateLimiter`, mirroring `AskAiRateLimitService`.
3. `LocationDnaEnrichmentGuard::decide('bridge', $listingId, $user->id, $snapshot, $now)`.
4. **Only** on `allowed` → `ComputeLocationDna::dispatch('bridge', $listingId)` **and** record the
   attempt/enrichment in the store (`RateLimiter::hit()` + set the cooldown marker). Otherwise no
   dispatch; the reason is carried on `MatchCheckAnalysis->enrichmentReason`.

The guard governs **only MatchCheck-initiated** enrichment; the lookup's own (unwired) dispatch and
all 10 independent dispatch sites are unchanged.

### 1.4 Produce the `MatchReport`

When `evaluate()` yields **SCORED**, git-C13 produces the rich report:

1. Obtain the `buildDetailed()` `BuyerMatchResult` for the (listing, criteria) pair (F3 blocks).
2. Project it into a `MatchReport` via a small, pure **`MatchReportFactory`** (criteria identity +
   `source='bridge'` + **injected** `generatedAt` — never `now()` inside, per the DTO's contract).

`MatchReport` is emitted **only** for SCORED. Non-scored statuses (disabled/blocked/no-criteria/
criteria-not-loaded/not-found/ambiguous) carry `report = null` and the caller renders the appropriate
empty/blocked/disambiguation state.

**Seam note:** `MatchCheckScorer` currently discards the rich `BuyerMatchResult` (extracts only lean
fields). git-C13 needs the detailed result too. §7 decision 2 covers *where* `buildDetailed()` runs
(extend the scorer to also return the detailed result vs. a dedicated report step in the orchestrator)
— recommendation: a dedicated report step, leaving `MatchCheckScorer` untouched.

---

## 2. Compliance (F7/F9) — enforced at the report boundary

- **F9 visibility** is already enforced *before* scoring by `evaluate()` (`ListingVisibilityGate`), so
  a non-IDX listing never reaches report production.
- **F7 no-restricted-fields:** `PropertyCandidate.$raw` and `BridgeProperty.raw_json` carry restricted
  data (PublicRemarks, contact/media, lockbox). `MatchReport` is built **only** from the
  `BuyerMatchResult` explanation blocks (field-name/magnitude strings) + factual scalars — **never**
  from `$raw`. The `candidates` array on an AMBIGUOUS analysis uses `PropertyCandidate::toArray()`
  (which **excludes `$raw` by default**). git-C13 asserts inline that no report/analysis output
  contains `raw_json` / `PublicRemarks` / PII / lockbox keys; the standalone compliance regression
  remains git-C15.

---

## 3. Inertness model for git-C13 (it shifts from "pure" to "flag-gated + no surface")

git-C8…C12 were pure/no-I/O. git-C13 inherently does I/O (lookup, enrichment) — so its inertness is
**structural**, not purity-based:

- **Flag-gated:** every new entry point returns a disabled analysis on `! isEnabled()` **before** any
  lookup/scoring/enrichment. With `mls_match_check.enabled` at its default **OFF**, git-C13 performs
  **no** Bridge API call, **no** DB read, **no** `ComputeLocationDna` dispatch, **no** `RateLimiter`
  write. Fully inert by default.
- **No consumer surface:** **no route, controller, Blade, or Livewire** — "still no route" (git-C14).
  Nothing reachable by an end user; the composition is callable only from tests.
- **Default-preserving edit:** the sole edit to existing code is the additive `$dispatchDna = true`
  opt-out param on `BridgeListingLookupService`; its default reproduces today's behavior exactly, and
  the service has no callers anyway.
- **No change** to: the 10 existing dispatch sites, `ComputeLocationDna`, `LocationDnaPipelineRunner`,
  the scorer/builder/mapper internals, `config/mls_match_check.php` (enabled stays OFF; no key added),
  migrations, or Matching V2.

---

## 4. Files changed / added

| File | Kind | Purpose |
|------|------|---------|
| `app/Services/Stellar/MatchCheck/MatchCheckOrchestrator.php` | **edit (additive)** | Grow with `analyzeByMlsNumber/ListingKey/Address()` + private compose/enrichment-decision/report helpers. Existing `isEnabled/prepare/evaluate` unchanged. |
| `app/Services/Stellar/MatchCheck/MatchCheckAnalysis.php` | **new** | Composed, serializable result VO (§1.2). |
| `app/Services/Stellar/MatchCheck/MatchReportFactory.php` | **new** | Pure projection: detailed `BuyerMatchResult` (+ identity, source, injected `generatedAt`) → `MatchReport`. |
| `app/Services/Stellar/MatchCheck/EnrichmentThrottleStore.php` | **new** | Production throttle store: build `EnrichmentThrottleSnapshot` (Cache/RateLimiter reads) + record attempt (the git-C12-deferred write side). |
| `app/Services/Bridge/BridgeListingLookupService.php` | **edit (additive, default-preserving)** | Add `bool $dispatchDna = true` to the three public lookups + `cacheRecord()`. Default = today's behavior. |
| `tests/Unit/Stellar/MatchCheck/MatchCheckAnalysisCompositionTest.php` | **new** | End-to-end composition across all statuses; enrichment routed through guard; MatchReport on SCORED; compliance. |
| `tests/Unit/Stellar/MatchCheck/MatchReportFactoryTest.php` | **new** | Projection correctness + injected timestamp + no restricted keys. |
| `tests/Unit/Bridge/BridgeListingLookupDispatchOptOutTest.php` | **new** | `dispatchDna:true` still dispatches (default preserved); `false` suppresses. |
| `docs/match-check-phase4-git-c13-scope.md` | **new** | This doc. |

**No** route, controller, Blade, Livewire, middleware, migration, `.env`, `config/*`, or CLAUDE.md
change. **No** new job; `ComputeLocationDna` reused as-is.

---

## 5. Test plan

- **Composition matrix** — `analyze*()` returns the right status for: flag OFF → DISABLED (no lookup);
  not found → NOT_FOUND; multi-candidate address → AMBIGUOUS (candidates listed, no score); non-IDX →
  BLOCKED; no criteria → NO_CRITERIA; happy path → SCORED with a populated `MatchReport`.
- **Enrichment routed through the guard** — with the flag ON and a fake store: an allowed decision
  dispatches `ComputeLocationDna` **once** and records the attempt; a COOLDOWN_ACTIVE / RATE_LIMITED
  decision dispatches **zero** times; `enrichmentReason` reflects the guard verdict. Use
  `Queue::fake()`/`Bus::fake()` to assert dispatch counts.
- **Lookup opt-out default-preserving** — `dispatchDna` default (true) dispatches on a fresh cache
  (parity with today); `false` never dispatches even on a fresh cache.
- **MatchReportFactory** — every `BuyerMatchResult` block maps to the correct `MatchReport` field;
  `generatedAt` is the injected value (deterministic); `narrative` is null.
- **Flag OFF fully inert** — `Http`/`Queue` fakes assert **zero** Bridge calls and **zero** dispatches
  when disabled, regardless of input.
- **Compliance** — `MatchCheckAnalysis` (recursively, incl. `report` + `candidates`) contains no
  `raw_json` / `PublicRemarks` / PII / lockbox keys, across residential + a non-residential fixture.

Full MatchCheck + Matching + `tests/Unit/Stellar/` + `tests/Unit/Bridge/` suites stay green (modulo
the pre-existing, unrelated `LocationDnaRoundTripTest` failure).

---

## 6. Out of scope for git-C13

- **Any route/controller/Blade/Livewire** (`/match-check`) — git-C14. This slice is callable only
  from tests; "still no route."
- Enabling `mls_match_check.enabled`; any `config`/`.env`/migration/persistence-schema change.
- The standalone compliance regression test — git-C15. The "better matches" low-score tail (F2) —
  git-C16 (this slice does not read `better_matches_enabled`).
- AI narrative (F8) — the `MatchReport.narrative` slot stays null.
- Editing the 10 existing `ComputeLocationDna::dispatch` sites, `ComputeLocationDna`,
  `LocationDnaPipelineRunner`, or the scorer/builder/mapper internals.
- The Matching V2 series — separate track, untouched.

---

## 7. Open decisions for the owner (before coding)

1. **Orchestrator growth vs. thin collaborator.** Grow `MatchCheckOrchestrator` with the `analyze*()`
   methods (stance (i), matches the reconciliation), or add a thin `MatchCheckLookup`/`MatchCheckFacade`
   that composes the orchestrator + lookup + guard + factory. Recommendation: **grow the orchestrator**
   (stance i, as the reconciliation states), keeping lookup/guard/factory as injected collaborators so
   the class stays a composition root, not a god object. Confirm.
2. **Where `buildDetailed()` runs.** Extend `MatchCheckScorer` to also surface the detailed
   `BuyerMatchResult`, or add a **separate report step** in the orchestrator that calls
   `BuyerMatchResultBuilder::buildDetailed()` for the SCORED case. Recommendation: **separate report
   step** — leaves the lean `MatchCheckScorer`/`MatchCheckResult` contract untouched and keeps report
   production opt-in. Confirm.
3. **Cooldown source for the throttle snapshot.** A dedicated per-listing **cooldown marker** we set
   on dispatch (semantics: "did *we* enrich this recently"), vs. the listing's actual Location-DNA
   computed-at timestamp (semantics: "is the data fresh"). Recommendation: **cooldown marker**
   (Cache), so the guard throttles our dispatch cadence independently of data freshness and mirrors
   `AskAiRateLimitService`. Confirm.
4. **Should this slice be split?** git-C13 is the largest slice (lookup + compose + guard-wiring +
   report). Option to land it as **C13a** (lookup + compose + guard-routed enrichment → `MatchCheckAnalysis`
   without `report`) then **C13b** (`MatchReportFactory` + populate `report`). Recommendation: **keep
   as one** doc but land as **two commits** (a then b) for reviewable diffs; or split into two scoped
   slices if you prefer. Confirm your preference.
5. **`analyze*()` naming / `MatchCheckAnalysis` status set.** Confirm the status names
   (`NOT_FOUND` / `AMBIGUOUS` + the five `MatchCheckResult` statuses) and the `analyzeBy*` method names,
   since git-C14's controller/view will consume them.

---

*This document changes no production code, tests, or config, and stages no unrelated file. It is to
be committed path-scoped alongside git-C13's code + tests when that slice lands.*
