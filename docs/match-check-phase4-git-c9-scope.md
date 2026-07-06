# Match Check — Phase 4 · git-C9 Scope

**Status:** Scope / design doc — **no implementation is changed by this doc commit (docs-only).**
**Purpose:** Define git-C9 — the **`MatchReport` DTO** (F3/F8), the typed, serializable, rich
single-property report model, plus the additive nullable slots on `BuyerMatchResult` that a later
slice populates. This is **Plan-C5** re-cut onto the as-built base (see
`docs/match-check-phase4-numbering-reconciliation.md`, approved).

> **Numbering:** git-C\<n\> convention. git-C9 == **Plan-C5**. Not the Matching V2 series; touches
> none of it.

Baseline: HEAD **`968554812`** (git-C8, criteria loader wired into `evaluate()`).
Architectural stance: **(i)** — grow `MatchCheckOrchestrator` into the orchestrator; git-C9 supplies
the report *data model* that the orchestrator (git-C13) will eventually emit.

---

## 1. Why this is the next slice

git-C5…C8 produced a working backend scoring **pipeline** whose current terminal output is the
**lean** `MatchCheckResult` (status + `totalScore` + `categoryScores`). The consumer-facing feature
(F3) needs a **rich** report — why / why-not / tradeoffs / missing / confidence / recommendations,
with the criteria and listing identity and a nullable AI slot (F8). git-C9 introduces that model as
a pure, inert data object, **without** building or wiring it. Every downstream slice (the report
builder git-C10, the detailed mapper git-C11, the orchestrator emit git-C13, the view git-C14)
depends on this shape existing first.

`MatchReport` is **distinct from** and does not replace `MatchCheckResult`: the latter stays the
lean scoring VO; `MatchReport` is the presentation-grade report. (Consistent with git-C6's decoupling
ethos, `MatchReport` holds **primitives/arrays only** — no `BuyerMatchResult` or `BridgeProperty`
reference — so it stays fully serializable per F8.)

---

## 2. What git-C9 delivers

### 2.1 `MatchReport` — new immutable, serializable DTO

`app/Services/Stellar/MatchCheck/MatchReport.php` — readonly value object (matching the MatchCheck
namespace style of `MatchCheckResult`/`MatchCheckPreparation`), holding only serializable scalars/
arrays, with a `toArray()`:

| Field | Type | Notes |
|-------|------|-------|
| `criteriaId` | `int` | The scored criteria record id. |
| `criteriaType` | `string` | `buyer` \| `tenant` \| `buyer_offer` \| `tenant_offer`. |
| `listingKey` | `string` | Bridge listing key. |
| `source` | `string` | Data source, e.g. `bridge` (caller-supplied; no lookup here). |
| `totalScore` | `int` | 0–100, already clamped upstream. |
| `categoryScores` | `array<string,int>` | Per-category breakdown (F4 reconciliation is a later render concern). |
| `whyThisMatches` | `array` | Positive contributors. |
| `whyNot` | `array` | Low/zero-scoring detractors (F3). |
| `tradeoffs` | `array` | |
| `missingData` | `array` | |
| `confidence` | `?array` | Nullable structured confidence block (data-completeness + geo signal); shape finalized when git-C10 builds it. |
| `recommendations` | `array` | Rule-based v1 suggestions (F3). |
| `generatedAt` | `string` | **Injected** ISO-8601 timestamp — never `now()` inside (deterministic tests, F8). |
| `narrative` | `?array` | **Nullable** AI/narrative slot (F8); default `null`. AI is an additive decorator in a much later, out-of-scope slice. |

API:
- Constructor taking the fields above (`narrative` defaulted `null`).
- `toArray(): array` — snake_case, fully serializable (round-trips; contains nothing non-serializable).
- No behavior, no I/O, no `now()`, no scoring, no persistence. Pure data.

> **No named "builder" or `fromResult()` factory in git-C9.** Mapping a fully-built
> `BuyerMatchResult` into a `MatchReport` is a **later** slice (git-C11/C13), because it depends on
> the detailed-mapper output shape that does not exist yet. Coupling the DTO to a mapper now would be
> premature. git-C9 ships the *shape* only.

### 2.2 `BuyerMatchResult` — additive nullable slots (Plan-C5 edit)

`app/Services/Stellar/Matching/DTO/BuyerMatchResult.php` gains **three optional, default-`null`**
properties + constructor params, appended **after** the existing `$missingData` param so every
existing positional caller is unaffected:

```php
public ?array $whyNot = null;
public ?array $confidence = null;
public ?array $recommendations = null;
```

- **`toArray()` is left UNCHANGED in git-C9** (conservative, zero-risk): the batch buyer-results
  page and any consumer of `BuyerMatchResult::toArray()` see byte-identical output. The new slots are
  object-level carriers only, surfaced later by the detailed mapper (git-C11) / report build. This
  guarantees the batch path is unaffected **without** needing a golden-snapshot change in git-C9.
- The slots stay `null` until git-C10's builder (`buildWhyNot/buildConfidence/buildRecommendations`)
  populates them; `mapOne()` (batch card) never reads them.

---

## 3. Files changed / added

| File | Kind | Purpose |
|------|------|---------|
| `app/Services/Stellar/MatchCheck/MatchReport.php` | **new** | The report DTO (§2.1). |
| `app/Services/Stellar/Matching/DTO/BuyerMatchResult.php` | **edit** | Append three nullable slots + ctor params (§2.2). `toArray()` unchanged. |
| `tests/Unit/Stellar/MatchCheck/MatchReportTest.php` | **new** | Construct + `toArray()` round-trip; `narrative` defaults null; nothing non-serializable. |
| `tests/Unit/Stellar/Matching/BuyerMatchResultOptionalFieldsTest.php` | **new** | New slots default `null`; existing positional construction still valid; `toArray()` output unchanged (snapshot of the 7 existing keys). |
| `docs/match-check-phase4-git-c9-scope.md` | **new** | This doc. |

No config, migration, route, controller, Blade, Livewire, job, provider, `.env`, or CLAUDE.md change.
No change to `MatchCheckResult`, the scorer, the orchestrator, or the git-C7 loader.

---

## 4. Inertness / additivity guarantees

- **Not a gated/inert-flag concern — it is pure data.** `MatchReport` has no behavior to gate and no
  caller; it executes in tests only. `mls_match_check.enabled` is **not read, referenced, or
  changed** (stays default OFF).
- **Zero behavioral change to the live batch path.** The only live-class edit is three appended
  default-`null` fields on `BuyerMatchResult` with `toArray()` untouched ⇒ existing output identical;
  `mapOne()` unaffected.
- **Backward compatible construction.** New ctor params are trailing and defaulted; all existing
  `new BuyerMatchResult(...)` positional calls compile and behave identically.
- **No I/O of any kind.** No writes, persistence, queues, events, external/Bridge API, Location DNA,
  or scoring math. No `now()` (timestamp injected).
- **Serializable (F8).** `MatchReport` holds only scalars/arrays; `toArray()` round-trips.

---

## 5. Test plan

**`MatchReportTest`:**
1. Construct with all fields → getters/props hold them; `toArray()` returns the expected snake_case
   map (round-trip equality).
2. `narrative` defaults to `null` when omitted; present when supplied.
3. `generatedAt` is exactly the injected string (proves no internal `now()`).
4. `toArray()` is JSON-encodable (assert `json_encode` !== false) — the F8 "nothing non-serializable"
   guarantee.

**`BuyerMatchResultOptionalFieldsTest`:**
1. Constructing with the pre-git-C9 positional signature leaves `whyNot/confidence/recommendations`
   all `null`.
2. Supplying them sets them.
3. `toArray()` returns exactly the seven pre-existing keys (no new keys leaked) — batch-output guard.

Full MatchCheck + Matching unit suites stay green.

---

## 6. Out of scope for git-C9

- **Building/populating** the report or the new fields — that is git-C10 (Plan-C6:
  `buildWhyNot/buildConfidence/buildRecommendations`).
- The detailed view mapper / F4 reconciliation — git-C11 (Plan-C7).
- Emitting a `MatchReport` from the orchestrator, and MLS#/address lookup — git-C13 (Plan-C9).
- Any route/controller/UI (git-C14), compliance test (git-C15), or better-matches tail (git-C16).
- Any **AI** narrative population (F8) — only the nullable slot exists; no OpenAI/DNA wiring.
- Any change to scoring math, `MatchCheckResult`, the scorer, orchestrator, or git-C7 loader.
- Enabling `mls_match_check.enabled`; any `.env` change; persistence/`match_reports` table.
- The Matching V2 series — separate track, untouched.

---

## 7. Risk / rollback

- **Blast radius:** one new DTO (no callers) + three appended nullable fields on a shared DTO with
  `toArray()` unchanged. Live-path behavior change: **none**. Risk: **low**.
- **Rollback:** delete `MatchReport.php` + its test; remove the three `BuyerMatchResult` fields/params
  (positional callers already omit them) + its test. Clean, no dependents.
- **Commit label:** `feat(match-check): Phase 4 git-C9 — MatchReport DTO + BuyerMatchResult slots
  (inert, additive)`. Do not reuse the retired bare "C5" label.

---

## 8. Open decisions for the owner (before coding)

1. **Bundle vs. split (recommended: bundle, plan-faithful).** git-C9 = `MatchReport` DTO **and** the
   `BuyerMatchResult` nullable slots together (Plan-C5 as written; the field add is zero-risk with
   `toArray()` unchanged). Alternative: ship only the `MatchReport` DTO now and defer the
   `BuyerMatchResult` slots to git-C10 where the builder populates them. **Recommendation: bundle.**
2. **`confidence` slot type.** Proposed `?array` (structured block) for both `MatchReport` and
   `BuyerMatchResult`, since git-C10 will synthesize data-completeness + geo signals. If you prefer a
   scalar (`?int`/`?string` level), say so; cosmetic and easily changed now, costly later.
3. **`generatedAt` type.** Proposed injected `string` (ISO-8601) for maximal serializability.
   Alternative: `\DateTimeInterface` with `toArray()` formatting. Recommendation: `string`.

---

*This document changes no production code, tests, or config, and stages no unrelated file. It is to
be committed path-scoped alongside git-C9's code files when that slice lands.*
