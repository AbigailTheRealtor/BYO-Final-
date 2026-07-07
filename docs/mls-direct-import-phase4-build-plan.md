# Phase 4 (Buyer/Tenant Match Check) — Implementation Build Plan

> Status: **Plan only — no code written, nothing staged or committed.** Date: 2026-07-05.
> Companion to `docs/mls-direct-import-design-and-plan.md` (locked design) and
> `docs/mls-direct-import-phase4-design-recommendations.md` (locked decisions F1–F9).
> **Do not begin implementation until the owner approves this plan.**

## Scope (locked)

Phase 4 delivers a **consumer-facing Buyer/Tenant Match Check** for **Bridge/Stellar** properties
only: start from ONE known property (MLS# primary, address fallback), score it against the user's
saved Buyer *or* Tenant criteria using the **existing `BridgeProperty` scoring pipeline** (F1), and
render a rich single-property Match Report behind a **default-OFF** feature flag.

Everything is inert until the flag is enabled: no env in this repo turns it on as part of Phase 4.

## Guiding constraints from F1–F9

- **F1** keep BridgeProperty pipeline; no `PropertyCandidate` scorer refactor.
- **F2** score the one property directly; `match()` only as an optional low-score "better matches" tail.
- **F3** Match Report ≫ card: why / why-not / weighting / breakdown / tradeoffs / missing / confidence / recommendations (rule-based v1).
- **F4** non-residential: every contributing category visible; totals reconcile to the score.
- **F5** auto-detect Sale/Rent → Buyer/Tenant for consumers; manual switch for agents.
- **F6** no unlimited enrichment: cache-first read path + per-listing cooldown + per-user rate limit.
- **F7** PublicRemarks internal-only; never rendered.
- **F8** typed serializable `MatchReport` DTO + nullable narrative slot; AI/persistence are additive decorators later.
- **F9** honor IDX/visibility gate; blocked → safe message; internal non-IDX path is a later permission-gated feature.

## Concurrency note

Every file below lives under `app/Services/Stellar/*`, `app/Services/Property`, `app/Http/*`,
`config/`, `routes/`, `resources/views/stellar/*`, `tests/`. **None** overlaps the active Matching V2
work (`app/Services/Dna/Relevance/*`, `config/matching.php`) — so this plan is conflict-free with that
effort. (Whether to start before Matching V2 is isolated remains the owner's earlier call.)

## New namespace

All net-new services go under **`App\Services\Stellar\MatchCheck`** to isolate them from the live
`App\Services\Stellar\Matching` batch pipeline. Additive methods on existing classes
(`CriteriaListingResolver`, `BuyerResultViewMapper`, `BuyerMatchResultBuilder`) are the only edits to
live files, and all are additive + guarded so the batch buyer-results page is byte-for-byte unaffected.

## Test infrastructure

Per `CLAUDE.md`: SQLite in-memory, `php artisan test`. Unit tests for services/DTOs/policies;
HTTP **feature** tests for the route/controller/view (mirroring the existing Stellar controller
tests). Every commit must leave the full suite green.

---

## Commit sequence

Ordered so leaf/inert pieces land first and nothing touches a live rendering path until it is
additive + tested. Each commit is independently revertable and keeps the suite green.

### Wave 0 — Inert flag scaffolding

#### C1 — Feature flag + gate middleware (unwired)
- **Files (new):** `config/mls_match_check.php` (keys: `enabled` → `env('MLS_MATCH_CHECK_ENABLED', false)`; plus placeholders `better_matches_enabled` default false, `better_matches_threshold` default 60, `dna_cooldown_hours` default 24, `dna_rate_limit_per_user_hourly` default TBD); `app/Http/Middleware/CheckMatchCheckEnabled.php` (mirror `CheckAgentAiV2Enabled` — `abort(404)` when off).
- **Files (edit):** `app/Http/Kernel.php` (add `$routeMiddleware` alias `'match-check' => CheckMatchCheckEnabled::class`).
- **Tests:** `tests/Unit/MatchCheck/CheckMatchCheckEnabledTest.php` — 404 when flag off; next() when on.
- **Risk:** Very low. No route uses it yet; pure addition.
- **Rollback:** Delete the two new files + the one Kernel alias line.
- **Note:** No route added here — the middleware is wired to a route in C10.

### Wave 1 — Leaf domain policies/helpers (pure, no live coupling)

#### C2 — ListingVisibilityGate (F9)
- **Files (new):** `app/Services/Stellar/MatchCheck/ListingVisibilityGate.php` — `isConsumerVisible(BridgeProperty $p): bool` (reads `IDXParticipationYN` from `raw_json`; returns a `VisibilityDecision` with a machine reason so the safe message and future internal path can branch). Pure; no DB writes.
- **Tests:** `tests/Unit/Stellar/MatchCheck/ListingVisibilityGateTest.php` — IDX true/false/missing/malformed raw_json.
- **Risk:** Low. Standalone policy; not yet called by anything.
- **Rollback:** Delete file + test.
- **Note:** We do **not** refactor `BuyerMatchService::match()`'s inline IDX filter in this commit (that's a live discovery path). Unifying them is an optional follow-up (C12 note).

#### C3 — CriteriaIntentDetector — Sale/Rent → buyer|tenant (F5)
- **Files (new):** `app/Services/Stellar/MatchCheck/CriteriaIntentDetector.php` — `detect(PropertyCandidate|BridgeProperty): 'buyer'|'tenant'|null` from `standardStatus`/`mlsStatus`/`propertyType` (lease vs sale). Pure function.
- **Tests:** `tests/Unit/Stellar/MatchCheck/CriteriaIntentDetectorTest.php` — sale statuses → buyer; lease/rental → tenant; ambiguous/unknown → null.
- **Risk:** Low.
- **Rollback:** Delete file + test.

#### C4 — `CriteriaListingResolver::resolvePreferred()` (C1/F5)
- **Files (edit):** `app/Services/Stellar/CriteriaListingResolver.php` — **additive** method `resolvePreferred(User $user, ?string $intent = null): ?array` (newest active buyer/tenant criteria for consumers; returns null for agents-with-multiple-clients to force explicit pick). Reuses existing `resolveAccessible()`.
- **Tests:** `tests/Unit/Stellar/CriteriaListingResolverPreferredTest.php` — consumer newest-active by intent; agent multi-client → null; no criteria → null. Plus a snapshot assert that existing `resolveAccessible()`/`resolveAllowedUserIds()` behavior is unchanged.
- **Risk:** Low (additive method on a live class; no existing method touched).
- **Rollback:** Remove the new method + test.

### Wave 2 — Report data model + enrichment blocks (additive, guarded)

#### C5 — `MatchReport` DTO + extend `BuyerMatchResult` (F3/F8)
- **Files (new):** `app/Services/Stellar/MatchCheck/MatchReport.php` — typed, **serializable** DTO: criteria id+type, listing_key, source, total score, category breakdown, why/why-not/tradeoffs/missing/confidence/recommendations, timestamp (injected, not `now()` inside), and a **nullable `narrative`/`ai` slot** (F8). Provides `toArray()`; contains nothing non-serializable.
- **Files (edit):** `app/Services/Stellar/Matching/DTO/BuyerMatchResult.php` — add **optional, default-null** fields for `whyNot`, `confidence`, `recommendations` (batch path leaves them null).
- **Tests:** `tests/Unit/Stellar/MatchCheck/MatchReportTest.php` — construct + `toArray()` round-trips; new `BuyerMatchResult` fields default null.
- **Risk:** Low–medium (shared DTO — additive only).
- **Rollback:** Delete `MatchReport`; remove the three optional fields.

#### C6 — Builder: why-not / confidence / recommendations (F3)
- **Files (edit):** `app/Services/Stellar/Matching/BuyerMatchResultBuilder.php` — **new additive methods** `buildWhyNot()` (all low/zero-scoring categories, not just today's price/size/amenity/pet tradeoffs), `buildConfidence()` (data-completeness + existing geo-confidence signal), `buildRecommendations()` (**rule-based** v1: "widen price ~$X", "consider adjacent city Y"). Populate the new `BuyerMatchResult` fields. **Existing** `buildWhyThisMatches/Tradeoffs/CautionFlags/MissingData` untouched.
- **Tests:** `tests/Unit/Stellar/Matching/BuyerMatchResultBuilderReportBlocksTest.php` — new blocks populate correctly; **regression:** existing batch build output (why/tradeoffs/caution/missing) is unchanged (golden snapshot).
- **Risk:** Medium (edits a live class used by the batch page). Mitigation: additive-only + regression snapshot; new fields ignored by `mapOne()`.
- **Rollback:** Remove the three methods + their field assignments; batch path already ignores the fields.

### Wave 3 — Detailed rendering (additive mapper)

#### C7 — `mapOneDetailed()` + non-residential reconciliation (B1/F3/F4)
- **Files (edit):** `app/Services/Stellar/BuyerResultViewMapper.php` — **new** `mapOneDetailed(BuyerMatchResult): array` that: keeps `fields_used` + `deviation` (which `mapOne` strips), emits the why-not/confidence/recommendations blocks, and **renders every contributing category including `non_residential` with reconciled totals** (F4 — visible breakdown sums to the shown total; expose "points contributed / points available" per category). **`mapOne()` and the batch card path are not modified**, and the compliance stripping (no raw_json/PII) is preserved in the detailed path too.
- **Tests:** `tests/Unit/Stellar/BuyerResultViewMapperDetailedTest.php` — detailed output contains the richer blocks; **non-residential category totals reconcile to `total_score`** across commercial/land/income/business fixtures; `mapOne()` output unchanged (snapshot); **no restricted keys** in detailed output.
- **Risk:** Low–medium (additive method on a live class).
- **Rollback:** Remove `mapOneDetailed()` + test.

### Wave 4 — Enrichment throttle (F6)

#### C8 — LocationDnaEnrichmentGuard + opt-out lookup param
- **Files (new):** `app/Services/Stellar/MatchCheck/LocationDnaEnrichmentGuard.php` — `attempt(BridgeProperty): bool`: per-listing cooldown (cache key `ldna:enqueued:{listing_key}`, TTL from config, default 24h) **and** per-user/global `RateLimiter`; dispatches `ComputeLocationDna` only when material staleness + cooldown + limit allow; otherwise no-op.
- **Files (edit):** `app/Services/Bridge/BridgeListingLookupService.php` — add `dispatchDna: bool = true` param to the lookup/cache path so existing callers (import sweep, lazy import) are **byte-for-byte unchanged by default**; Match Check calls with `dispatchDna:false` and routes enrichment through the guard instead.
- **Tests:** `tests/Unit/Stellar/MatchCheck/LocationDnaEnrichmentGuardTest.php` — cooldown dedupes repeat checks (no second dispatch); rate limit caps per user; below-threshold never hard-fails. `tests/Unit/Bridge/BridgeLookupDispatchParamTest.php` — default `true` preserves current dispatch; `false` suppresses.
- **Risk:** Medium (touches a live service signature). Mitigation: default arg preserves behavior; import paths untouched.
- **Rollback:** Delete guard; remove the default-`true` param (callers revert to prior signature).
- **Note:** This is design-decision F6 **option (a)** (opt-out param) for isolation. F6 **option (b)** (centralize the throttle inside the dispatch path so *all* callers inherit it) is the eventual consolidation and is **out of scope** here to avoid changing import-sweep behavior.

### Wave 5 — Orchestrator

#### C9 — `MlsPropertyMatchAnalysisService` (F1/F2/F5/F6/F9)
- **Files (new):** `app/Services/Stellar/MatchCheck/MlsPropertyMatchAnalysisService.php` — the thin single-property flow:
  `lookup (BridgeListingLookupService, dispatchDna:false)` → `ListingVisibilityGate` (F9; blocked → structured "blocked" report, no details) → `BridgeProperty::find($candidate->sourceRecordId)` (F1 seam) → `CriteriaIntentDetector` + `resolvePreferred`/explicit id → load criteria → `BuyerCriteriaPayload` → `scorer->score()` → `resultBuilder->build()` → `mapper->mapOneDetailed()` → `MatchReport` (F8). Enrichment via `LocationDnaEnrichmentGuard` (F6), never inline. **Does not call `BuyerMatchService::match()`** (F2).
- **Tests:** `tests/Feature/Stellar/MatchCheck/MatchAnalysisServiceTest.php` — happy path (buyer + tenant); blocked-by-visibility → blocked report; MLS# not found; wrong/absent criteria type → empty-state report; verify no inline DNA dispatch (guard invoked).
- **Risk:** Medium (composition), but every dependency is already unit-tested.
- **Rollback:** Delete service + test. Nothing else references it until C10.

### Wave 6 — HTTP surface + compliance

#### C10 — Route + controller + view (mirror `stellar.buyer.results`)
- **Files (new):** `app/Http/Controllers/Stellar/MatchCheckController.php` (mirror `StellarBuyerResultsController`: MLS#/address input, auto-detect criteria F5, switch dropdown via `resolveAccessible()`, blocked → safe message *"This property cannot currently be analyzed or displayed through Match Check."*); `resources/views/stellar/match-check/index.blade.php` (+ partials). View renders **only** `MatchReport`/`mapOneDetailed` output — never the model or `raw_json` (F7).
- **Files (edit):** `routes/web.php` — `Route::get('/match-check', [MatchCheckController::class, 'index'])->middleware(['auth','match-check'])` inside the authed group.
- **Tests:** `tests/Feature/Stellar/MatchCheckTest.php` — flag OFF → 404; flag ON + valid MLS# → report renders with category bars + why/why-not/recommendations; blocked listing → safe message, **no listing specifics leak**; address fallback returns a chooser when multiple match; buyer vs tenant auto-selected by listing status.
- **Risk:** Medium (user-facing surface). Mitigation: flag default OFF; mirrors a proven controller pattern.
- **Rollback:** Remove the route line (page 404s); optionally delete controller/view.

#### C11 — Compliance regression test (E1/F7/F9)
- **Files (new):** `tests/Feature/Stellar/MatchCheckComplianceTest.php` — asserts the `/match-check` response (and `MatchReport::toArray()`) contains **no** `raw_json`, `PublicRemarks`/`public_remarks`, agent/brokerage PII, lockbox, or showing instructions, across residential + all non-residential fixtures; asserts a non-IDX listing is fully blocked (F9) with only the safe message.
- **Risk:** Low (test-only; a guardrail against future leaks).
- **Rollback:** Delete test.
- **Note:** Kept as its own commit (not folded into C10) so the compliance contract is an explicit, standalone artifact.

### Wave 7 — Optional low-score fallback (F2)

#### C12 — "Better matches" tail (F2)
- **Files (edit):** `MlsPropertyMatchAnalysisService` + `MatchCheckController`/view — **only** when the primary score `< config('mls_match_check.better_matches_threshold')` **and** `better_matches_enabled`, call `BuyerMatchService::match()`, run every candidate through `ListingVisibilityGate` (F9), and render a **separate, clearly-labeled** "You may also like…" section (never merged into the primary score).
- **Tests:** `tests/Feature/Stellar/MatchCheckBetterMatchesTest.php` — below threshold triggers the tail; at/above does not; every fallback listing passes the visibility gate; flag off → no tail.
- **Risk:** Medium (invokes the heavy discovery engine). Mitigation: gated by threshold + its own flag (default off), fully separate section.
- **Rollback:** Remove the fallback branch + its flag usage; primary report unaffected.
- **Optional follow-up (not a Phase 4 commit):** refactor `BuyerMatchService::match()`'s inline IDX filter to delegate to `ListingVisibilityGate` so there is one visibility policy in one place (F9 "one policy, one place"). Deferred because it edits the live discovery path.

---

## Dependency order (DAG)

```
C1 ─┐
C2 ─┼─────────────► C9 ──► C10 ──► C11
C3 ─┤               ▲        │
C4 ─┘               │        └────► C12
C5 ──► C6 ──► C7 ───┘
C8 ─────────────────┘
```
C1–C4 and C8 are independent leaves. C5→C6→C7 is the report-rendering chain. C9 composes all of
them; C10 exposes it; C11 hardens compliance; C12 is the optional tail.

## Risk summary

| Commit | Touches live path? | Risk |
|--------|--------------------|------|
| C1 flag+middleware | no | Very low |
| C2 visibility gate | no | Low |
| C3 intent detector | no | Low |
| C4 resolvePreferred | additive on live class | Low |
| C5 MatchReport DTO | additive on shared DTO | Low–med |
| C6 builder blocks | **live builder (additive)** | Medium |
| C7 mapOneDetailed + F4 | **live mapper (additive)** | Low–med |
| C8 DNA guard + param | **live lookup (default-preserving)** | Medium |
| C9 analysis service | no (new composition) | Medium |
| C10 route/controller/view | new user surface (flag-gated) | Medium |
| C11 compliance test | test only | Low |
| C12 better-matches tail | invokes live `match()` (gated) | Medium |

## Global rollback

The entire feature is inert behind `MLS_MATCH_CHECK_ENABLED=false` (default). Fastest kill = ensure
the flag is off (middleware 404s the route). Full removal = revert C12→C1 in reverse; the only live
files edited (C4/C6/C7/C8) are additive, so reverting them restores exact prior behavior.

## Explicitly OUT OF SCOPE for Phase 4

- **No `PropertyCandidate` scorer refactor** (F1) — scorer stays typed to `BridgeProperty`.
- **No AI features** (F8): AI why/tradeoff narratives, Buyer/Tenant DNA compatibility narrative,
  Property DNA summary, marketability insights, Ask AI integration. Only the **nullable slots** exist.
- **No saved / shareable / historical Match Reports** (F8) — the DTO is serializable, but no
  `match_reports` table, persistence, or share UI is built.
- **No agent/admin internal analysis of non-IDX listings** (F9) — reserved as a later
  permission-gated path.
- **No new scoring logic.** F4 is **display reconciliation only**; category weights/caps and the
  scorer algorithm are unchanged.
- **No Seller/Landlord changes; no Phase 3 prefill.** Match Check never writes a form.
- **No Location DNA provider/commute work** (Phase 5); no F6 option-(b) dispatch-path consolidation.
- **No changes to the live batch buyer-results page** behavior or `mapOne()` output.
- **No enabling the flag in any environment** — GA/rollout is a later, separately-coordinated step.
- **No touching Matching V2 files** (`app/Services/Dna/Relevance/*`, `config/matching.php`).
- **No manual-entry / URL-parser / other-MLS sources** — Bridge/Stellar only.

## Pre-flight checklist before C1

- [ ] Confirm the Phase 2 lookup indexes are **run** on the target DB (`php artisan migrate:status` —
      `2026_07_05_000001_add_lookup_indexes_to_bridge_properties`) so `findByMlsNumber` doesn't scan (D2).
- [ ] Confirm Matching V2 work is complete or isolated per the owner's earlier pause (plan is
      conflict-free regardless, but this was the stated gate).
- [ ] Owner sign-off on this build plan.
