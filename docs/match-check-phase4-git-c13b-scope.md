# Match Check — Phase 4 · git-C13b Scope

**Status:** Scope / design doc — **no implementation is changed by this doc commit (docs-only).**
**Purpose:** Define git-C13b — the second half of the git-C13 slice. git-C13a landed the lookup
composition + guarded DNA enrichment and produces a `MatchCheckAnalysis` whose rich `report` slot is
**always null**. git-C13b fills that slot: a small, pure **`MatchReportFactory`** plus a **dedicated
report step** in the orchestrator that projects the git-C10 `buildDetailed()` blocks into a populated
`MatchReport` for the **SCORED** case — leaving `MatchCheckScorer` / `MatchCheckResult` lean and
leaving all route/controller/UI work to git-C14.

> **Numbering:** git-C13 == Plan-C9 == F1/F2/F5/F6/F9 (see
> `docs/match-check-phase4-git-c13-scope.md` and `…-numbering-reconciliation.md` §6). git-C13 was
> approved to land as two implementation commits under one scope: **git-C13a** (committed) and
> **git-C13b** (this doc). Not the Matching V2 series; touches none of it.

Baseline: HEAD **`a83d34745`** (git-C13a — `feat(match-check): Phase 4 git-C13a — lookup composition
and guarded DNA enrichment`).

---

## 0. What git-C13a already established (so git-C13b only projects)

git-C13a committed the composition; the report shape is the only remaining gap:

| Piece | Where | git-C13b uses it for |
|-------|-------|----------------------|
| `MatchCheckAnalysis` with a `?MatchReport $report` slot (always null in C13a) | `…/MatchCheck/` | the slot C13b populates — the DTO shape is already stable across the a→b split |
| `MatchCheckAnalysis::fromResult($result, $reason, ?MatchReport $report = null)` | `…/MatchCheck/` | the third arg C13a left defaulted-null; C13b passes a real report on SCORED |
| `MatchReport` DTO (git-C9) | `…/MatchCheck/` | the serializable target shape (injected `generatedAt`, null `narrative`) |
| `BuyerMatchResultBuilder::buildDetailed()` (git-C10) | `…/Matching/` | the F3 blocks: `whyThisMatches`/`tradeoffs`/`missingData` + `whyNot`/`confidence`/`recommendations` |
| `MatchCheckPreparation->preferredCriteria` `['id','type']` | `…/MatchCheck/` | the report's `criteriaId` + `criteriaType` (type ∈ `buyer`/`tenant`/`buyer_offer`/`tenant_offer`) |
| `MatchCheckCriteriaLoader::load()` → one `BuyerCriteriaPayload` for all four types | `…/MatchCheck/` | the payload `buildDetailed()` needs; one path covers residential + non-residential/tenant |

**Field alignment (why this is a projection, not new logic).** A `buildDetailed()` `BuyerMatchResult`
exposes `listingKey, totalScore, categoryScores, whyThisMatches, tradeoffs, missingData, whyNot,
confidence, recommendations`. `MatchReport` is a near 1:1 superset, adding only `criteriaId`,
`criteriaType`, `source`, an **injected** `generatedAt`, and a null `narrative`. `MatchReport`
deliberately does **not** carry `cautionFlags`; the factory drops it.

---

## 1. The two things git-C13b delivers

### 1.1 `MatchReportFactory` — pure projection

A small, stateless projector, mirroring the DTO/factory idioms already in this package
(`EnrichmentThrottleSnapshot`, `MatchReport`). No flag read, no I/O, no `now()`:

```php
final class MatchReportFactory
{
    public function fromDetailed(
        BuyerMatchResult $detailed,   // AFTER buildDetailed() — blocks populated
        int $criteriaId,
        string $criteriaType,         // 'buyer'|'tenant'|'buyer_offer'|'tenant_offer'
        string $source,               // 'bridge'
        string $generatedAt,          // INJECTED ISO-8601 — never now() inside (DTO contract)
    ): MatchReport;
}
```

**Mapping (exhaustive):**

| `MatchReport` field | Source | Null-handling |
|---------------------|--------|---------------|
| `criteriaId` | `$criteriaId` (from `preferredCriteria['id']`) | required int |
| `criteriaType` | `$criteriaType` (from `preferredCriteria['type']`) | pass-through |
| `listingKey` | `$detailed->listingKey` | — |
| `source` | `$source` (`'bridge'`) | — |
| `totalScore` | `$detailed->totalScore` | — |
| `categoryScores` | `$detailed->categoryScores` | — |
| `whyThisMatches` | `$detailed->whyThisMatches` | — |
| `whyNot` | `$detailed->whyNot` | `?? []` (DTO wants non-null array) |
| `tradeoffs` | `$detailed->tradeoffs` | — |
| `missingData` | `$detailed->missingData` | — |
| `confidence` | `$detailed->confidence` | pass-through `?array` |
| `recommendations` | `$detailed->recommendations` | `?? []` |
| `generatedAt` | `$generatedAt` | injected |
| `narrative` | — | `null` (F8 deferred) |
| ~~`cautionFlags`~~ | — | **dropped** (not a `MatchReport` field) |

The factory is total over its inputs and deterministic (same inputs → same report).

### 1.2 A dedicated report step in the orchestrator (SCORED only)

For a resolved listing that `evaluate()` scores, the orchestrator obtains the **detailed**
`BuyerMatchResult` (which the lean `MatchCheckScorer` discards), runs `buildDetailed()`, and projects
it via the factory — then threads the report into `MatchCheckAnalysis::fromResult(...)`. The report is
produced **only** for SCORED; every other status keeps `report = null`.

The single edit to git-C13a's `analyzeCandidate()` is: compute `report` for the SCORED case and pass
it as the (already-existing) third arg to `fromResult()`. `routeEnrichment()` is unchanged.

---

## 2. The seam: where `buildDetailed()`'s inputs come from

`MatchCheckScorer::score()` runs `BuyerMatchScorer::score($listing, $criteria)` and keeps only lean
fields, so the rich `BuyerMatchResult` is gone by the time `analyzeCandidate()` has a
`MatchCheckResult`. The report step needs three things for the SCORED case: the **preparation** (for
`criteriaId`/`criteriaType`), the **criteria payload** (for `buildDetailed()`), and a
**`BuyerMatchResult`** (to decorate).

**Owner decision 2 (already confirmed): a dedicated report step, `MatchCheckScorer` untouched.** Two
sub-questions remain for the owner to confirm (§7):

- **(A) How to get the `BuyerMatchResult`.** Recommended: the report step re-runs the **pure**
  `BuyerMatchScorer::score($listing, $criteria)` (no DB, no API, no lazy import — it is a
  side-effect-free comparison) to obtain a fresh `BuyerMatchResult`, then `buildDetailed()`. This
  keeps `MatchCheckScorer`/`MatchCheckResult` **byte-for-byte unchanged** at the cost of one extra
  in-memory score pass. Rejected alternative: fatten the scorer to also surface the detailed result
  (breaks the lean contract).
- **(B) Avoiding a double DB read.** `prepare()` resolves preferred criteria (one DB read) and the
  criteria loader loads the payload (one DB read). The report step must **reuse** the preparation and
  payload `analyzeCandidate()` already resolved rather than re-running them. Recommended: extract
  `evaluate()`'s git-C8 auto-load block into a private helper shared by `evaluate()` and the analyze
  path, so `analyzeCandidate()` resolves `prepare()` + payload **once**, passes the payload explicitly
  to the score, and hands both to the report step — `evaluate()`'s public behavior stays identical and
  no DB read is duplicated. Simpler-diff alternative (call `evaluate()` unchanged, then re-derive
  `prepare()`+payload in the report step) is acceptable but doubles the criteria DB reads on the
  SCORED path.

### 2.1 `generatedAt` injection

Per the `MatchReport` contract, the timestamp is injected, never taken inside the DTO or factory. The
orchestrator (the composition root) supplies it once at the report boundary
(`CarbonImmutable::now()->toIso8601String()`), passing it into `fromDetailed()`. Tests inject a fixed
value for determinism. git-C13a already froze `CarbonImmutable` in its composition test; C13b tests do
the same or pass a literal.

---

## 3. Inertness model for git-C13b

git-C13b adds a pure factory and a SCORED-only projection on top of git-C13a's already-flag-gated
path. It stays structurally inert:

- **Flag-gated (inherited):** the report step is reached only from `analyzeCandidate()`, which is
  reached only after `analyzeBy*()` passes the `isEnabled()` gate. With `mls_match_check.enabled` at
  its default **OFF**, no analyze entry runs, so no report is ever produced. A report requires
  SCORED — which requires flag ON + visible + criteria + a loaded payload.
- **Pure factory:** `MatchReportFactory` reads no flag, does no I/O, calls no `now()`, and performs no
  scoring — it only reshuffles an already-computed `BuyerMatchResult` into a `MatchReport`.
- **No new I/O on the enrichment path:** `routeEnrichment()` and `EnrichmentThrottleStore` are
  unchanged; report production is independent of enrichment.
- **No consumer surface:** **no route, controller, Blade, or Livewire** — still git-C14. Callable only
  from tests.
- **Lean contract preserved:** `MatchCheckScorer` and `MatchCheckResult` are **not modified**; the
  report is assembled beside them, not inside them.

---

## 4. Files changed / added

| File | Kind | Purpose |
|------|------|---------|
| `app/Services/Stellar/MatchCheck/MatchReportFactory.php` | **new** | Pure projection: detailed `BuyerMatchResult` (+ identity, source, injected `generatedAt`) → `MatchReport`. |
| `app/Services/Stellar/MatchCheck/MatchCheckOrchestrator.php` | **edit (additive)** | Add the SCORED-only report step + inject the factory/report-builder collaborators (nullable, lazily defaulted like git-C13a's). Populate `MatchCheckAnalysis.report`. `routeEnrichment()`, `prepare()`, `evaluate()` public behavior unchanged. |
| `tests/Unit/Stellar/MatchCheck/MatchReportFactoryTest.php` | **new** | Projection correctness (every block mapped), injected timestamp, null coalescing, null `narrative`, no restricted keys. |
| `tests/Unit/Stellar/MatchCheck/MatchCheckAnalysisReportStepTest.php` | **new** | SCORED → populated report threaded onto the analysis; every non-SCORED status → `report === null`; report identity (`criteriaId`/`criteriaType`/`source`/injected `generatedAt`) correct. |
| `docs/match-check-phase4-git-c13b-scope.md` | **new** | This doc. |

**No** change to `MatchCheckScorer`, `MatchCheckResult`, `MatchCheckAnalysis` (its `report` slot and
`fromResult()` third arg already exist), `BuyerMatchResultBuilder`, `BuyerMatchScorer`,
`BridgeListingLookupService`, `EnrichmentThrottleStore`, the enrichment routing, `config/*`,
migrations, routes, or Matching V2. **No** new job.

> If sub-decision (B) selects the shared-helper refactor, the git-C13a
> `MatchCheckAnalysisCompositionTest` may need a light touch only if the internal composition of
> `analyzeCandidate()` changes observably — it should not, since `evaluate()`'s public behavior is
> preserved. Any such change will be called out at the pre-commit checkpoint.

---

## 5. Test plan

- **`MatchReportFactory`** — a `buildDetailed()` `BuyerMatchResult` projects field-for-field into a
  `MatchReport`; `whyNot`/`recommendations` null → `[]`; `confidence` null passes through as null;
  `narrative` is null; `generatedAt` is exactly the injected value (no internal `now()`);
  `cautionFlags` does not appear; identity fields (`criteriaId`/`criteriaType`/`source`) are the
  injected values. Residential **and** a non-residential/tenant fixture.
- **Report step — SCORED** — a scored analyze path yields `MatchCheckAnalysis` with a populated
  `report`: `total_score`/`category_scores` match the result, F3 blocks present, `criteriaId`/`Type`
  from the preparation's preferred record, `source='bridge'`. `report->generatedAt` is the injected
  value.
- **Report step — non-SCORED** — DISABLED / BLOCKED / NO_CRITERIA / CRITERIA_NOT_LOADED / NOT_FOUND /
  AMBIGUOUS each keep `report === null` (only SCORED produces one).
- **Compliance (inline, F7)** — the produced `MatchReport` (and the analysis carrying it) contains no
  `raw_json` / `PublicRemarks` / PII / lockbox keys — it is built only from explanation blocks +
  factual scalars, never from `$raw`. (The standalone compliance regression remains git-C15.)
- **Inertness** — flag OFF ⇒ no analyze entry runs ⇒ no report produced; the factory touches no flag,
  no clock, no I/O.

Full MatchCheck + Matching + `tests/Unit/Stellar/` + `tests/Unit/Bridge/` + `tests/Feature/Bridge/`
suites stay green (modulo the pre-existing, unrelated `LocationDnaRoundTripTest` failure).

---

## 6. Out of scope for git-C13b

- **Any route/controller/Blade/Livewire** (`/match-check`) — git-C14. Callable only from tests.
- Enabling `mls_match_check.enabled`; any `config`/`.env`/migration/persistence change.
- **AI narrative (F8)** — the `MatchReport.narrative` slot stays null; no OpenAI/DNA call.
- The standalone compliance regression test — git-C15. The "better matches" low-score tail (F2) —
  git-C16.
- Editing `MatchCheckScorer` / `MatchCheckResult` (kept lean), `BuyerMatchResultBuilder` /
  `BuyerMatchScorer` internals, the 10 existing `ComputeLocationDna::dispatch` sites, or the
  enrichment routing landed in git-C13a.
- The Matching V2 series — separate track, untouched.

---

## 7. Open decisions for the owner (before coding)

1. **(A) Source of the detailed `BuyerMatchResult`.** Recommended: the report step re-runs the pure
   `BuyerMatchScorer::score()` (one extra in-memory pass, zero I/O) so `MatchCheckScorer` stays
   untouched. Confirm, or prefer surfacing it from the scorer.
2. **(B) Avoiding the double DB read.** Recommended: extract `evaluate()`'s auto-load into a private
   helper so `analyzeCandidate()` resolves `prepare()` + payload once and shares them with the report
   step. Confirm, or accept the simpler re-derive (double criteria DB read on SCORED).
3. **Factory API name/shape.** `MatchReportFactory::fromDetailed(BuyerMatchResult, int, string,
   string, string): MatchReport` with an injected `generatedAt`. Confirm the method name and the
   injected-timestamp contract.
4. **`generatedAt` format.** ISO-8601 via `CarbonImmutable::now()->toIso8601String()` at the
   orchestrator boundary. Confirm the format (the DTO stores it verbatim).

---

*This document changes no production code, tests, or config, and stages no unrelated file. It is to
be committed path-scoped alongside git-C13b's code + tests when that slice lands, and only after
owner approval of this scope.*
