# Match Check — Phase 4 · git-C15 Scope

**Status:** Scope / design doc — **no implementation is changed by this doc commit (docs-only).**
**Purpose:** Define git-C15 — the **standalone compliance regression suite (F7 + F9)** for the now
consumer-reachable Match Check surface. This slice adds **tests only**: it consolidates the scattered
per-surface compliance checks into one authoritative cross-cutting regression, strengthens them with a
**non-residential** fixture and a **real restricted-data payload swept across every output surface**,
and adds the **end-to-end F9 non-IDX block** through the HTTP surface. **No production code changes.**

> **Numbering:** git-C\<n\> convention. git-C15 == **Plan-C11** == **F7/F9** (see
> `docs/match-check-phase4-numbering-reconciliation.md` §6, row "git-C15 · Compliance regression test ·
> = Plan-C11 (F7/F9) · test only · No `raw_json`/PII/PublicRemarks; non-IDX fully blocked"). It is the
> direct successor of git-C14 on the dependency spine (`… ► git-C14 ► git-C15`). Not the Matching V2
> series; touches none of it. git-C16 ("better matches" low-score tail, F2) remains after this.

**Baseline:** HEAD **`50c9f2fe2`** (git-C14 — gated route/controller/UI). Match Check chain confirmed
at checkpoint:

| Slice | Commit |
|-------|--------|
| git-C12 | `12bac2af7` |
| git-C13a | `a83d34745` |
| git-C13b | `4704ee6f4` |
| git-C14 | `50c9f2fe2` (HEAD) |

Concurrent commits between C13b and C14 (offers Batch D `47407e37f`, docs `aa7b06b12`, safeguards
`0d0c5a1c0`, `171f9974d`, Hire-Tenant-currency `55e8fd5ec`) are all **unrelated to Match Check**. No
commit has landed **after** git-C14. Working tree **clean**; nothing staged; `mls_match_check.enabled`
default **OFF** (config + runtime `false`); both `/match-check` routes gated behind
`Authenticate` + `CheckMatchCheckEnabled` (POST also `throttle:20,1`).

---

## 0. What already exists (so git-C15 consolidates + strengthens, it does not restart)

Compliance is already partially asserted, but **scattered across surfaces, residential-only, and never
swept end-to-end**. git-C15 unifies and hardens this:

| Existing check | Where | What it covers | Gap git-C15 closes |
|----------------|-------|----------------|--------------------|
| F7 on rendered HTML (AMBIGUOUS) | `tests/Feature/MatchCheck/MatchCheckControllerTest.php:110` | `assertDontSee('raw_json'/'PublicRemarks')` for one candidate | Only AMBIGUOUS; only two literal keys; residential |
| F7 + F8 on rendered partial (SCORED) | `tests/Feature/MatchCheck/MatchCheckResultRenderTest.php` | narrative + `raw_json`/`PublicRemarks` absent in `_result_body` | Partial render only (not full HTTP); residential; one status |
| F7 at serialization boundary | `tests/Unit/Stellar/MatchCheck/MatchCheckAnalysisCompositionTest.php:237` | encoded analysis has no `PublicRemarks`/`RESTRICTED`; AMBIGUOUS candidates restricted-free | Backend only (no rendered surface); residential |
| F9 gate policy | `tests/Unit/Stellar/MatchCheck/ListingVisibilityGateTest.php` | `IDXParticipationYN=false → blocked('idx_participation_false')` | Unit of the gate **primitive**, not end-to-end through analyze*/HTTP |

**The consolidated gap git-C15 fills** (why it is its own slice, per Plan-C11):
1. **No single authoritative F7 sweep.** No test plants **one** restricted payload and proves it leaks
   through **none** of the four output surfaces at once (analysis `toArray()`, report `toArray()`,
   candidates, and the **full HTTP-rendered** page).
2. **No non-residential coverage.** Every existing compliance fixture is `Residential`. A `Commercial
   Sale`/`Commercial Lease` listing must be equally leak-proof (ties off the git-C11 non-residential
   reconciliation at the compliance boundary).
3. **No end-to-end F9.** The gate is unit-tested, but nothing asserts the whole path — a non-IDX
   listing through `analyzeBy*()` → `BLOCKED` (no score, `report === null`) → **HTTP** render shows the
   neutral blocked state, **never the block reason** (`idx_participation_false`), and dispatches **no**
   Location DNA enrichment.

---

## 1. What git-C15 delivers (tests only)

### 1.1 F7 — no restricted fields, swept across every output surface

`BridgeProperty.raw_json` (a fillable JSON column) is the sole carrier of restricted MLS data:
**PublicRemarks**, agent/office **PII** (`ListAgentKey`, names, phone, email), **lockbox / showing**
instructions, and **media** URLs. Every Match Check *output* surface is built from explanation blocks +
factual scalars — **never** from `raw_json` / `PropertyCandidate.$raw`. git-C15 proves this with a
**canary payload**: a fixture whose `raw_json` embeds a unique sentinel string (e.g.
`CANARY_RESTRICTED_DO_NOT_LEAK`) inside PublicRemarks, a PII field, and a lockbox field. The test then
asserts the sentinel — and the literal restricted keys — appear in **none** of:

| Surface | Accessor under test |
|---------|---------------------|
| Composed analysis (incl. nested `result`) | `MatchCheckAnalysis::toArray()` |
| Rich report (SCORED) | `MatchReport::toArray()` (also confirms `narrative === null`) |
| Disambiguation candidates (AMBIGUOUS) | `MatchCheckAnalysis->candidates` (`PropertyCandidate::toArray()`, `$raw` excluded by default) |
| Full rendered page | the `GET`→`POST /match-check` HTML response body |

Asserted for a **residential** and a **non-residential** (`Commercial Sale`) listing, across the SCORED
and AMBIGUOUS statuses.

### 1.2 F9 — non-IDX listing fully blocked, end to end

A fixture whose `raw_json` sets `IDXParticipationYN=false` (the one explicit block condition in
`ListingVisibilityGate::decide()`) is driven through the real orchestrator + HTTP surface. The suite
asserts:

- `MatchCheckOrchestrator::analyzeBy*()` → status **BLOCKED**, `result` not scored, `report === null`.
- The **HTTP** render shows the neutral `data-status="blocked"` state — **no score, no category
  breakdown, and the block reason string `idx_participation_false` is absent** (F9: never explain why).
- **No** `ComputeLocationDna` dispatch and **no** restricted data in the blocked page
  (`Queue::fake()` / canary assertions).

### 1.3 F8 re-assertion at the output boundary (defense-in-depth)

Although F8 (no AI narrative) is git-C14/C16 territory, the F7 sweep already serializes `MatchReport`;
git-C15 additionally asserts the `narrative` slot is **null and unrendered** wherever a report is
produced, so the compliance suite is the single place that fails if a future change surfaces it.

---

## 2. Files added / changed

| File | Kind | Purpose |
|------|------|---------|
| `tests/Feature/MatchCheck/MatchCheckComplianceTest.php` | **new (test only)** | The authoritative F7/F9 cross-cutting regression: canary-payload sweep across all output surfaces (analysis, report, candidates, full HTTP render) + end-to-end non-IDX block, for residential **and** non-residential fixtures. |
| `docs/match-check-phase4-git-c15-scope.md` | **new** | This doc. |

**No** production change: **no** `app/**`, route, controller, view, middleware, config, `.env`,
migration, or CLAUDE.md edit. `mls_match_check.enabled` stays OFF (the flag-ON path tests toggle it via
`Config::set(...)` in-test only, exactly as the existing C14 feature tests do — no persisted change).

> **Decision point (see §5.1):** whether git-C15 is **one** consolidated Feature test, or a Feature
> test (HTTP F7/F9) plus a small Unit test (surface-serialization sweep). Recommendation: **one Feature
> file** — the sweep needs the persisted `BridgeProperty` + HTTP render anyway, so a single file keeps
> the "one authoritative regression" property.

---

## 3. Fixtures

- **Residential IDX fixture** — `BridgeProperty::create([...])` with `property_type = 'Residential'`,
  a resolvable MLS#/ListingKey, `raw_json` = `{"IDXParticipationYN": true, "PublicRemarks":
  "CANARY_RESTRICTED_DO_NOT_LEAK …", "ListAgentKey": "CANARY_PII", "…lockbox…": "CANARY_LOCKBOX"}`,
  plus a user with matching preferred criteria so `analyzeBy*()` reaches **SCORED**.
- **Non-residential IDX fixture** — same shape with `property_type = 'Commercial Sale'` (exercises the
  git-C11 non-residential path) and its own canary payload.
- **Non-IDX fixture** — `raw_json` = `{"IDXParticipationYN": false, …canary…}` for the F9 block path.
- **Ambiguous fixture** — a multi-candidate address resolution (reusing the composition-test pattern at
  `MatchCheckAnalysisCompositionTest.php:96`) carrying restricted `$raw`, to sweep the candidate list.

All fixtures are built with the **existing** `BridgeProperty::create([...])` idiom used across the
MatchCheck/Bridge suites (there is no BridgeProperty factory; §5.3). The lookup is bound to a fake/double
so **no** real Bridge API call occurs, mirroring `MatchCheckAnalysisCompositionTest`.

---

## 4. Test plan

- **F7 sweep — residential, SCORED:** analyze the IDX residential fixture → `SCORED`; assert the canary
  sentinel + restricted keys (`raw_json`, `PublicRemarks`, `ListAgentKey`, lockbox) appear in **none**
  of `MatchCheckAnalysis::toArray()` (JSON-encoded, recursive), `MatchReport::toArray()`, or the full
  `POST /match-check` HTML body.
- **F7 sweep — non-residential, SCORED:** same for the `Commercial Sale` fixture.
- **F7 sweep — AMBIGUOUS candidates:** multi-candidate address → `AMBIGUOUS`; the encoded `candidates`
  and the rendered disambiguation list contain **no** canary/restricted data (`$raw` excluded).
- **F9 end-to-end block:** non-IDX fixture → `analyzeBy*()` is **BLOCKED**, `report === null`, not
  scorable; `POST /match-check` renders `data-status="blocked"` with **no** score and **no**
  `idx_participation_false`; `Queue::assertNothingPushed()` (no enrichment).
- **F8 slot:** wherever a `MatchReport` is produced, `narrative === null` and no narrative text renders.
- **Flag-OFF inertness (regression guard):** with `mls_match_check.enabled` OFF, `POST /match-check`
  → **404** and the canary appears nowhere (belt-and-braces with the C14 gate test).

Full MatchCheck + `tests/Feature/` + `tests/Unit/Stellar/MatchCheck/` suites stay green (modulo the
pre-existing, unrelated `LocationDnaRoundTripTest` failure, and the session-level Google Places env
guard which must be run with the key unset — neither is touched here).

---

## 5. Open decisions for the owner (before coding)

1. **One file vs. two.** One consolidated `MatchCheckComplianceTest` (Feature), or split into a Feature
   test (HTTP F7/F9) + a Unit test (pure surface-serialization sweep). Recommendation: **one Feature
   file** — keeps a single authoritative regression; the sweep needs a persisted listing + render
   anyway.
2. **Canary strategy.** A single unique sentinel embedded in several restricted fields (assert its
   total absence) vs. enumerating each restricted key by name. Recommendation: **both** — the sentinel
   catches value leakage, the key-name list catches structural leakage; cheap to assert together.
3. **BridgeProperty fixture builder.** There is no factory; the suite uses inline `BridgeProperty::
   create([...])` like every existing MatchCheck test. Recommendation: **keep the inline idiom**
   (a private `residential()/commercial()/nonIdx()` helper in the test), not a new shared factory —
   fixtures are test-only and localized. Confirm you don't want a reusable factory introduced here.
4. **Non-residential type.** Use `Commercial Sale` (sale intent) as the non-residential fixture, or add
   `Commercial Lease` (rental intent) too. Recommendation: **`Commercial Sale`** for the primary sweep;
   note `Commercial Lease` as optional if you want both intents covered.
5. **F9 reason-hiding assertion strength.** Assert only that `idx_participation_false` is absent, or
   also assert none of the gate's other reason tokens (`idx_*`) render. Recommendation: assert the
   **whole `idx_` reason family** is absent from the blocked page, so any future reason string is caught.

---

## 6. Out of scope for git-C15

- **Any production code** — this slice is **tests only**. No `app/**`, route, controller, view,
  middleware, config, `.env`, or migration change.
- **Enabling `mls_match_check.enabled`** (flag-ON paths are toggled in-test only).
- **The "better matches" low-score tail (F2)** — git-C16, gated behind `better_matches_enabled`.
- **AI narrative (F8) production** — git-C15 only *asserts its absence*; it builds no narrative surface.
- **Matching V2** — separate track, untouched.
- **Refactoring the visibility gate, orchestrator, analysis, report, or lookup** — all exercised as-is.

---

*This document changes no production code, tests, or config, and stages no unrelated file. It is to be
committed path-scoped alongside git-C15's test file when that slice lands. Implementation begins only
after the owner reviews this scope and resolves §5.*
