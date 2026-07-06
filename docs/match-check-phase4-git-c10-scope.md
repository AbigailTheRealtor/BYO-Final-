# Match Check — Phase 4 · git-C10 Scope

**Status:** Scope / design doc — **no implementation is changed by this doc commit (docs-only).**
**Purpose:** Define git-C10 — the three **report-block builders** on `BuyerMatchResultBuilder`
(`buildWhyNot`, `buildConfidence`, `buildRecommendations`, F3) that populate the nullable
`BuyerMatchResult` slots git-C9 added. This is **Plan-C6** re-cut onto the as-built base (see
`docs/match-check-phase4-numbering-reconciliation.md`, approved).

> **Numbering:** git-C\<n\> convention. git-C10 == **Plan-C6**. Not the Matching V2 series; touches
> none of it. `config/match_scoring.php` is the **agent-services** matcher and is unrelated —
> untouched here.

Baseline: HEAD **`8070ea364`** (git-C9, `MatchReport` DTO + `BuyerMatchResult` nullable slots).
Architectural stance: **(i)** — grow toward the Plan-C9 orchestrator.

---

## 1. Why this is the next slice

git-C9 added three default-`null` slots to `BuyerMatchResult` (`whyNot`, `confidence`,
`recommendations`) but nothing populates them. git-C10 supplies the **rule-based v1** builders that
fill them (F3), reusing the existing `BuyerMatchResultBuilder` alongside its four current blocks
(`buildWhyThisMatches` / `buildTradeoffs` / `buildCautionFlags` / `buildMissingData`) — which stay
**untouched**. The detailed view mapper (git-C11) and the orchestrator emit (git-C13) then consume
these blocks; no AI is involved (F8 narrative slot stays null).

---

## 2. Invocation strategy — keep the live batch path untouched (recommended)

The existing `build()` / `buildAll()` are on the **live** batch buyer-results path. To guarantee that
path is byte-for-byte **and** compute-for-compute unchanged, git-C10 adds a **new public
`buildDetailed()`** that composes the existing `build()` with the three new blocks, and leaves
`build()` / `buildAll()` **exactly as they are**:

```php
// UNCHANGED: build(), buildAll(), and all four existing private block builders.

/**
 * build() + the git-C10 report blocks (F3). Used by the Match Check detailed path only.
 * Populates whyNot / confidence / recommendations on top of the four existing blocks.
 */
public function buildDetailed(BuyerMatchResult $result, BuyerCriteriaPayload $criteria): BuyerMatchResult
{
    $this->build($result, $criteria);                     // existing four blocks, unchanged

    $rawJson = $this->decodeRawJson($result->listing);    // small private helper (or inline decode)
    $result->whyNot          = $this->buildWhyNot($result);
    $result->confidence      = $this->buildConfidence($result, $rawJson);
    $result->recommendations = $this->buildRecommendations($result, $criteria);

    return $result;
}
```

- **Batch path unaffected:** `build()`/`buildAll()` are not edited, so the batch page pays no new
  compute and its output is identical; the git-C9 slots stay `null` there.
- **`buildDetailed()` is unwired** in git-C10 (no caller yet) — the git-C11 mapper / git-C13
  orchestrator will call it. Mirrors the git-C7 "land inert, wire later" discipline.

*Alternative (Plan-C6 literal): populate the three slots inside `build()` itself. Output stays
unchanged (mapOne strips + toArray unchanged from git-C9), but the batch path then computes blocks it
discards. **Recommendation: `buildDetailed()`** — strictly no live-path change. See §8.*

---

## 3. The three new blocks (rule-based v1)

All three are **private, pure** methods returning serializable arrays; no I/O beyond the existing
`raw_json` decode; no `now()`; no flag reads. Category dimensions are the same seven the existing
`buildWhyThisMatches` uses: `location, price, size, property_type, amenities, financial, lifestyle`.

### 3.1 `buildWhyNot(BuyerMatchResult): array`
Emits one entry per **zero-scoring** known dimension (the clearest "why not"), shaped like
`whyThisMatches` for symmetry:
```php
['dimension' => 'price', 'label' => 'Priced outside your budget — 0 points', 'fields_used' => [...], 'score_contribution' => 0]
```
- Iterates the seven dimensions; includes a dimension only when its `categoryScores[dim] === 0`.
- Rule-based labels via a small `buildWhyNotLabel($dimension)` helper (mirrors `buildWhyLabel`).
- Sorted deterministically (dimension order or ascending score). Returns `[]` when nothing is zero.
- **v1 scope:** zero-scoring only. Finer "low-but-nonzero" gradation needs each dimension's max
  (only `PRICE_PROXIMITY_MAX_PTS`/`LOCATION_MAX_PHASE1_PTS` are clean constants today) and is a
  deferred refinement, not git-C10.

### 3.2 `buildConfidence(BuyerMatchResult, array $rawJson): array`
A structured confidence block from **data-completeness + the geo signal** (the same geo signal the
existing `reduced_confidence_geo_match` caution flag uses):
```php
['level' => 'high'|'medium'|'low', 'score' => 0.0–1.0, 'factors' => [
    'geo_precise'  => bool,   // listing latitude AND longitude present
    'completeness' => 0.0–1.0 // fraction of key fields present
]]
```
- `completeness` = fraction of a fixed key-field set present/non-null (e.g. `list_price`,
  `living_area`, `year_built`, `lot_size_sqft`, `property_type`).
- `geo_precise` = both `latitude` and `longitude` present.
- `score` = a simple deterministic blend (e.g. completeness with a fixed penalty when `!geo_precise`);
  `level` from fixed thresholds (e.g. ≥0.8 high, ≥0.5 medium, else low). Always returns a block
  (never null in practice); the slot's nullability just permits "not computed".

### 3.3 `buildRecommendations(BuyerMatchResult, BuyerCriteriaPayload): array`
Rule-based v1 suggestions:
```php
['type' => 'widen_price', 'dimension' => 'price', 'label' => 'Consider widening your price range by ~$25,000']
```
- **widen_price** — when `categoryScores['price']` is low/zero AND the listing `list_price` exceeds
  the buyer's `idealPrice`/`maxPrice`: recommend widening by the (rounded) gap.
- **consider_adjacent_area** — when `categoryScores['location']` is low/zero: suggest considering
  nearby areas (label references the criteria's preferred city if available; no external lookup).
- Returns `[]` when scores are strong. Two–three rules total for v1; each is a pure conditional.

---

## 4. Files changed / added

| File | Kind | Purpose |
|------|------|---------|
| `app/Services/Stellar/Matching/BuyerMatchResultBuilder.php` | **edit** | Add `buildDetailed()` (public) + `buildWhyNot`/`buildConfidence`/`buildRecommendations` + a `buildWhyNotLabel` helper (all private). **`build()`, `buildAll()`, and the four existing block builders are unchanged.** |
| `tests/Unit/Stellar/Matching/BuyerMatchResultBuilderReportBlocksTest.php` | **new** | Cover the three blocks + `buildDetailed()` composition + batch-path regression (§5). |
| `docs/match-check-phase4-git-c10-scope.md` | **new** | This doc. |

No config, migration, route, controller, Blade, Livewire, job, provider, `.env`, or CLAUDE.md change.
No change to `MatchReport`, `MatchCheckResult`, the scorer, the orchestrator, the git-C7 loader, or
`BuyerResultViewMapper`.

---

## 5. Test plan

**`BuyerMatchResultBuilderReportBlocksTest`:**
1. `buildDetailed()` populates all three slots **and** the four existing blocks; returns the same
   mutated instance.
2. **whyNot** — fires for a zero-scoring dimension; absent for a positive-scoring one; `[]` when all
   scored.
3. **confidence** — `high` when geo-precise + complete; downgraded when `latitude`/`longitude`
   missing; structured shape (`level`/`score`/`factors`) asserted; deterministic (no `now()`/random).
4. **recommendations** — `widen_price` fires when price is low and `list_price` > `idealPrice`,
   with the expected gap; `consider_adjacent_area` fires when location is zero; `[]` when scores are
   strong.
5. **Batch regression** — after plain `build()` (not `buildDetailed`), the three slots remain
   **null** and `whyThisMatches/tradeoffs/cautionFlags/missingData` are byte-identical to pre-git-C10
   (golden assertion). Confirms the live path is untouched.

Full MatchCheck + Matching + `tests/Unit/Stellar/` suites stay green (modulo the pre-existing,
unrelated `LocationDnaRoundTripTest` failure).

---

## 6. Out of scope for git-C10

- Wiring `buildDetailed()` into any caller — git-C11 (mapper) / git-C13 (orchestrator).
- The detailed view mapper `mapOneDetailed()` + F4 reconciliation — git-C11 (Plan-C7).
- Emitting a `MatchReport` / MLS# lookup — git-C13 (Plan-C9).
- Any route/controller/UI, compliance test, or better-matches tail (git-C14/C15/C16).
- **AI** narrative population (F8) — the slot stays null.
- Any change to **scoring math** (`BuyerMatchScorer`) or the four existing block builders.
- Any change to `mapOne()` / the batch buyer-results page behavior.
- Enabling `mls_match_check.enabled`; any `.env`/config change; persistence.
- The Matching V2 series — separate track, untouched.

---

## 7. Inertness / additivity guarantees

- **No flag involved / not read.** `mls_match_check.enabled` stays default OFF and is not referenced.
- **Live batch path provably unchanged:** `build()`/`buildAll()`/the four existing builders/`mapOne()`
  are not edited; `buildDetailed()` has no caller yet, so nothing on the batch path invokes the new
  blocks (the git-C9 slots stay null there).
- **Pure / read-only:** the new methods compute from the already-loaded `BuyerMatchResult` +
  `raw_json`; no writes, persistence, queues, events, external/Bridge API, Location DNA, or `now()`.
- **Deterministic:** rule-based labels + fixed thresholds; no randomness/time.

---

## 8. Open decisions for the owner (before coding)

1. **Invocation: `buildDetailed()` wrapper (recommended) vs. populate inside `build()` (Plan-C6
   literal).** Recommendation: `buildDetailed()` — the batch path is then strictly untouched. Confirm.
2. **whyNot threshold: zero-scoring only (recommended v1) vs. low-but-nonzero.** Recommendation:
   zero-only for v1 (unambiguous; no per-dimension max needed).
3. **confidence shape:** `['level','score','factors'=>['geo_precise','completeness']]` as proposed —
   confirm, or specify additional factors.
4. **recommendations rule set:** the two rules above (`widen_price`, `consider_adjacent_area`) for v1
   — confirm, or name others.

---

*This document changes no production code, tests, or config, and stages no unrelated file. It is to
be committed path-scoped alongside git-C10's code + test when that slice lands.*
