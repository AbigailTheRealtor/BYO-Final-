# Match Check — Phase 4 · git-C11 Scope

**Status:** Scope / design doc — **no implementation is changed by this doc commit (docs-only).**
**Purpose:** Define git-C11 — `BuyerResultViewMapper::mapOneDetailed()`: the additive, detailed,
compliance-preserving view mapper that (a) keeps the richer explanation detail `mapOne()` strips,
(b) surfaces the git-C9/git-C10 detailed blocks (`why_not` / `confidence` / `recommendations`), and
(c) renders **every contributing category including `non_residential` with reconciled totals** (F4).
This is **Plan-C7** re-cut onto the as-built base (see
`docs/match-check-phase4-numbering-reconciliation.md`, approved).

> **Numbering:** git-C\<n\> convention. git-C11 == **Plan-C7**. Not the Matching V2 series; touches
> none of it.

Baseline: HEAD **`2db1b1fba`** (git-C10, `buildDetailed()` + the three report blocks).
Architectural stance: **(i)** — grow toward the Plan-C9 orchestrator.

---

## 1. Why this is the next slice, and the exact defects it fixes

`mapOne()` is the batch buyer-results mapper. It is deliberately lossy for card display:

- It **strips** `fields_used`, `dimension`, and `deviation` from the explanation blocks (§`mapOne`
  helpers) — good for a compact card, too little for a detailed "why / why-not" report (F3).
- Its `category_bars` loop iterates **only the seven residential `CATEGORY_WEIGHTS`**
  (`location…lifestyle`, summing 100). The scorer, however, emits **eight** category scores — the
  seven residential **plus `non_residential`** — and `total = round(Σ all eight)` (clamped 0–100).
  For an Income / Commercial Sale / Business Opportunity / Vacant Land listing, `non_residential`
  carries real points that **never appear** in `category_bars`, so the visible breakdown does **not**
  reconcile to the shown score — the exact **F4** defect.
- It has no knowledge of the git-C9/git-C10 slots (`why_not` / `confidence` / `recommendations`).

git-C11 adds a **new `mapOneDetailed()`** that fixes all three for the Match Check detailed view,
while `mapOne()` and the batch card path stay **byte-for-byte unchanged** (F4 is **display-only** —
no scorer/weight change).

---

## 2. `mapOneDetailed(BuyerMatchResult): array`

Additive public method. **Reuses `mapOne()`** for the compliance-safe scalar fields (score, price,
address, city/state/zip, beds/baths/sqft, type/subtype, lat/lng, hero photo), then **overrides** the
explanation blocks with detail-preserving versions and **adds** the new blocks + reconciled category
breakdown:

```php
public function mapOneDetailed(BuyerMatchResult $result): array
{
    $base = $this->mapOne($result);                 // compliance-safe scalars (reused, not duplicated)

    return array_merge($base, [
        'category_bars'    => $this->mapDetailedCategoryBars($result),   // 8 categories, reconciled (F4)
        'category_totals'  => $this->reconcileCategoryTotals($result),   // contributed / available / adjustment
        'why_this_matches' => $this->mapWhyDetailed($result->whyThisMatches),  // keep fields_used
        'why_not'          => $this->mapWhyDetailed($result->whyNot ?? []),     // git-C10 (keep fields_used)
        'tradeoffs'        => $this->mapTradeoffsDetailed($result->tradeoffs),  // keep deviation + fields_used
        'confidence'       => $this->mapConfidence($result->confidence),        // git-C10 (nullable)
        'recommendations'  => $this->mapRecommendations($result->recommendations ?? []), // git-C10
    ]);
}
```

- The git-C9 slots (`why_not`, `confidence`, `recommendations`) may be **null** when a result only
  went through `build()` (not `buildDetailed()`); `mapOneDetailed()` handles null gracefully
  (→ `[]` / `null`), so it never assumes the git-C10 build ran.
- `caution_flags` and `missing_data` are carried through from `$base` unchanged (already useful and
  safe as mapped by `mapOne`).

### 2.1 Detail kept vs. `mapOne`

| Block | `mapOne` (card) | `mapOneDetailed` (report) |
|-------|-----------------|---------------------------|
| `why_this_matches` | label + score only | **+ `fields_used`** (which listing fields drove it), `dimension` |
| `tradeoffs` | label + machine keys stripped | **+ `deviation`** (magnitude, e.g. `12%_above_ideal`) + `fields_used` |
| `why_not` | — (absent) | label + `fields_used` + `dimension` (git-C10) |
| `confidence` | — | `{level, score, factors}` (git-C10) |
| `recommendations` | — | `{type, dimension, label}` (git-C10) |
| `category_bars` | 7 residential only | **8 categories, contributing ones shown, reconciled (F4)** |

`fields_used`/`deviation`/`dimension` are **field-name / magnitude strings — not values, not PII** —
so keeping them preserves the compliance contract (§4).

---

## 3. F4 — non-residential reconciliation (display-only)

**Goal:** the visible category breakdown must reconcile to the shown `total_score`, and every
**contributing** category (including `non_residential`) must be visible.

Design:
- **Which categories show:** every category whose **contributed** score `> 0` (F4 "every contributing
  category visible"), across all **eight** keys. `non_residential` therefore appears for the property
  types that earn it and is absent for residential listings (where it is 0). *(Alternative in §8:
  always show all eight.)*
- **Per-category shape:** `{key, label, contributed, available, pct}` where `contributed` = the
  clamped integer category score and `available` = that category's max.
  - `available` for the seven residential categories = the existing `CATEGORY_WEIGHTS`.
  - `available` for `non_residential` = its scorer max (the neutral-points value, currently **10**;
    §8 flags sourcing this from a scorer-side constant rather than a literal).
- **Sum reconciliation (the core F4 guarantee):** `contributed` points sum to the **authoritative**
  `total_score`. Because the scorer computes `total = round(Σ raw)` while each category is
  `round(raw_i)` independently, `Σ round(raw_i)` can differ from `total` by a small rounding delta.
  `reconcileCategoryTotals()` therefore returns:
  ```php
  ['contributed_sum' => int, 'total_score' => int, 'rounding_adjustment' => int]  // adjustment = total_score - contributed_sum
  ```
  so the view can render `Σ contributed + rounding_adjustment == total_score` **exactly**. The
  headline stays the authoritative `total_score`; no scorer math changes.

Non-residential category **label**: add a `'non_residential' => 'Property Fit'` (or similar) entry to
a detail-only label map so the bar has a human label; the seven residential labels reuse the existing
`CATEGORY_LABELS`.

---

## 4. Compliance (F7/F9) — preserved in the detailed path

`mapOneDetailed()` starts from `mapOne()` (already compliance-safe) and only **adds field-name-level
detail + the git-C10 label blocks**. It therefore continues to **never** emit `raw_json`,
`PublicRemarks`, agent/brokerage PII, lockbox, or showing instructions. The git-C11 test asserts this
explicitly across residential **and** all four non-residential fixtures (the standalone compliance
regression is git-C15/Plan-C11; git-C11 carries its own inline assertion too).

---

## 5. Files changed / added

| File | Kind | Purpose |
|------|------|---------|
| `app/Services/Stellar/BuyerResultViewMapper.php` | **edit (additive)** | Add `mapOneDetailed()` + private helpers (`mapDetailedCategoryBars`, `reconcileCategoryTotals`, `mapWhyDetailed`, `mapTradeoffsDetailed`, `mapConfidence`, `mapRecommendations`) + a detail-only non-residential label. **`mapOne()`, `map()`, and all existing private helpers unchanged.** |
| `tests/Unit/Stellar/BuyerResultViewMapperDetailedTest.php` | **new** | Detail retained; git-C10 blocks surfaced; **non-residential reconciliation**; `mapOne()` output unchanged; **no restricted keys**; null-slot graceful handling. |
| `docs/match-check-phase4-git-c11-scope.md` | **new** | This doc. |

No config, migration, route, controller, Blade, Livewire, job, provider, `.env`, or CLAUDE.md change.
No change to the scorer, `BuyerMatchResultBuilder`, `MatchReport`, `MatchCheckResult`, orchestrator,
or git-C7 loader. `mapOneDetailed()` is **unwired** (git-C13 orchestrator will call it).

---

## 6. Test plan

`BuyerResultViewMapperDetailedTest`:
1. **Superset of safe scalars** — `mapOneDetailed()` contains the same compliance-safe scalar fields
   as `mapOne()` (score/price/address/beds/…); reuse verified.
2. **Detail retained** — `why_this_matches` entries include `fields_used`; `tradeoffs` include
   `deviation` (both stripped by `mapOne`).
3. **git-C10 blocks surfaced** — after `buildDetailed()`, `why_not` / `confidence` / `recommendations`
   are present and correctly shaped; **null-slot** case (only `build()` ran) → `why_not`/`recommendations`
   `[]`, `confidence` `null` (no crash).
4. **F4 reconciliation (residential)** — the seven residential contributing categories' `contributed`
   sum + `rounding_adjustment` == `total_score`.
5. **F4 reconciliation (non-residential)** — for an Income / Commercial Sale / Vacant Land /
   Business Opportunity fixture, `non_residential` appears in `category_bars`, and
   `contributed_sum + rounding_adjustment == total_score`.
6. **`mapOne()` unchanged** — golden assertion that `mapOne()` output is byte-identical to pre-git-C11
   (batch card guard).
7. **Compliance** — the detailed output (recursively) contains **no** `raw_json`, `public_remarks`/
   `PublicRemarks`, agent/brokerage PII, lockbox, or showing-instruction keys — across residential +
   all four non-residential fixtures.

Full MatchCheck + Matching + `tests/Unit/Stellar/` suites stay green (modulo the pre-existing,
unrelated `LocationDnaRoundTripTest` failure).

---

## 7. Out of scope for git-C11

- Wiring `mapOneDetailed()` into any caller — git-C13 (orchestrator) / git-C14 (view).
- Emitting a `MatchReport` / MLS# lookup — git-C13 (Plan-C9).
- Any route/controller/Blade/UI, the standalone compliance test (git-C15), or better-matches (git-C16).
- **Any scorer/weight change** — F4 is display reconciliation only; `total_score` and category scores
  are consumed as-is.
- Any change to `mapOne()` / `map()` / the batch buyer-results page behavior.
- AI narrative (F8); enabling `mls_match_check.enabled`; `.env`/config/persistence.
- The Matching V2 series — separate track, untouched.

---

## 8. Open decisions for the owner (before coding)

1. **Category display set:** show **contributing categories only** (score > 0, recommended — matches
   F4 "every contributing category visible" and avoids noisy 0/max bars) vs. always show all eight.
   Recommendation: contributing-only, but always include `non_residential` when the property_type is
   non-residential even if 0, so the class is explicit. Confirm.
2. **`non_residential` available/max source:** literal `10` (the current neutral value) vs. expose a
   `BuyerMatchScorer` constant to read. Recommendation: read a scorer-side constant if one is added;
   otherwise document the `10` literal in the mapper. (Note: reconciliation of the **sum** depends
   only on `contributed`, not `available`, so this choice affects bar width only.)
3. **Rounding reconciliation:** surface an explicit `rounding_adjustment` so pieces + adjustment ==
   `total_score` (recommended, keeps `total_score` authoritative) vs. redefine the detailed total as
   `Σ contributed`. Recommendation: `rounding_adjustment`.
4. **Non-residential bar label:** proposed `'Property Fit'` for the `non_residential` key — confirm or
   rename.

---

*This document changes no production code, tests, or config, and stages no unrelated file. It is to
be committed path-scoped alongside git-C11's code + test when that slice lands.*
