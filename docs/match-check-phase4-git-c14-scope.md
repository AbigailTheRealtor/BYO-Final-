# Match Check — Phase 4 · git-C14 Scope

**Status:** Scope / design doc — **no implementation is changed by this doc commit (docs-only).**
**Purpose:** Define git-C14 — the **first consumer-facing surface** for Match Check: a **flag-gated
route group + a thin controller + a minimal Blade view** that consume the already-built
`MatchCheckOrchestrator::analyzeBy*()` composition and render each `MatchCheckAnalysis` status. This
is the "**still no route**" line from git-C13 being crossed — deliberately, minimally, and **entirely
behind `mls_match_check.enabled` (default OFF → every route 404s)**.

> **Numbering:** git-C\<n\> convention. git-C14 == the route/controller/UI slice deferred by git-C13
> (see `docs/match-check-phase4-git-c13-scope.md` §6 line 1: *"Any route/controller/Blade/Livewire
> (`/match-check`) — git-C14"*, and the orchestrator docblock: *"still wired to no route, controller,
> or UI (git-C14)"*). Not the Matching V2 series; touches none of it.

**Baseline:** HEAD **`171f9974d`** (adopted as the corrected git-C14 baseline; owner-confirmed the 4
commits after `4704ee6f`/git-C13b are unrelated to Match Check — offers Batch D, Google Places
safeguards, docs/tooling — and the Match Check **source / flag / routes surface is byte-identical to
git-C13b**). `mls_match_check.enabled` default **OFF**. No `/match-check` route exists yet.

---

## 0. What already exists (so git-C14 wires a surface, it does not build a backend)

The checkpoint at HEAD `171f9974d` confirms the entire backend + the gate primitive are already
built. git-C14 is **surface-only wiring** onto a finished composition root:

| Piece | Where | git-C14 uses it for |
|-------|-------|---------------------|
| `MatchCheckOrchestrator::analyzeByMlsNumber()` / `analyzeByListingKey()` / `analyzeByAddress()` | `app/Services/Stellar/MatchCheck/` | the **single call** the controller makes; returns a `MatchCheckAnalysis` |
| `MatchCheckAnalysis` (+ `->toArray()`, `is*()` predicates, `STATUS_*`) | `…/MatchCheck/` | the **render model** — data-only, already F7-safe (excludes `$raw`) |
| `MatchReport` (`->toArray()`; `narrative` slot **stays null**) | `…/MatchCheck/` | the SCORED-state report body the view renders (never the `narrative` slot — F8 out of scope) |
| `CheckMatchCheckEnabled` middleware | `app/Http/Middleware/` | the 404 gate — **already written** (mirrors `CheckAgentAiV2Enabled`) |
| `'match-check'` route-middleware alias | `app/Http/Kernel.php:84` | **already registered** — the route group just references it by name |
| `agent-ai-v2` gated route group | `routes/web.php:276–287` | the **precedent pattern** git-C14 mirrors verbatim (`Route::middleware(...)->group()`, 404 when OFF) |

**Two facts that make this slice low-risk:**
1. The gate already exists and is already registered. git-C14 adds **no** middleware and **no** config
   key — it only *references* the `match-check` alias from a new route group.
2. The orchestrator's `analyzeBy*()` entries **already** short-circuit to `MatchCheckAnalysis::disabled()`
   on `!isEnabled()` before any lookup. So even if a route were reached with the flag OFF, the backend
   returns DISABLED. The middleware 404 is the primary gate; the orchestrator's own gate is
   defense-in-depth. Two independent OFF guarantees.

---

## 1. The three things git-C14 delivers

### 1.1 A flag-gated route group (mirrors `agent-ai-v2`)

```php
// routes/web.php — new group, additive. Mirrors the agent-ai-v2 group at line 279.
// Requires auth (analyzeBy*() needs a User for per-user criteria resolution) AND the master flag.
// When mls_match_check.enabled=false (default), every route below 404s via the middleware.
Route::middleware(['auth', 'match-check'])->group(function () {
    Route::get('/match-check', [MatchCheckController::class, 'show'])->name('match-check.show');
    Route::post('/match-check', [MatchCheckController::class, 'lookup'])->name('match-check.lookup');
});
```

- **Gate order:** `auth` first (a guest gets the normal login redirect), then `match-check` (404 when
  OFF). With the flag at its default OFF, both routes are invisible — identical in spirit to the
  `agent-ai-v2` precedent.
- **Throttle (§7 decision 4):** the POST can trigger a Bridge lookup + (guarded) enrichment, so a
  Laravel `throttle:` middleware is added to the POST to bound consumer-driven external calls. The
  *enrichment* itself is already independently throttled by the git-C12 guard + git-C13 store; this is
  a coarse per-user request cap on top.

### 1.2 A thin controller

`app/Http/Controllers/MatchCheck/MatchCheckController.php` — the **only** new backend file, and it
holds **no business logic**. It:

- `show()` → renders the lookup form (empty state).
- `lookup(Request)` → validates the identifier, dispatches to the right orchestrator entry, and passes
  the returned `MatchCheckAnalysis` to the view:

```php
public function lookup(MatchCheckLookupRequest $request, MatchCheckOrchestrator $orchestrator): View
{
    $user = $request->user();

    $analysis = match ($request->validated('mode')) {
        'mls'     => $orchestrator->analyzeByMlsNumber($request->validated('mls_number'), $user),
        'address' => $orchestrator->analyzeByAddress($request->validated('address'), $user),
        // ListingKey endpoint exists on the orchestrator; whether it gets a UI field is §7 decision 2.
    };

    return view('match-check.result', ['analysis' => $analysis]);
}
```

- **No direct service instantiation, no scoring, no lookup, no enrichment** in the controller — it
  delegates 100% to the orchestrator, exactly as the orchestrator was designed to be consumed.
- Belt-and-braces: because the route is already behind the middleware, and `analyzeBy*()` self-gates,
  the controller needs no explicit flag check.

### 1.3 A minimal Blade surface (one form + one status-driven result view)

A small `resources/views/match-check/` set that renders **from `MatchCheckAnalysis` only**:

| `MatchCheckAnalysis` status | Rendered state |
|-----------------------------|----------------|
| `SCORED` | the match result: `report.total_score`, `category_scores`, `why_this_matches`, `why_not`, `tradeoffs`, `missing_data`, `confidence`, `recommendations` |
| `AMBIGUOUS` | a **disambiguation list** from `$analysis->candidates` (factual `PropertyCandidate::toArray()` rows — no `$raw`); each row re-submits a precise identifier |
| `NOT_FOUND` | "no listing found for that identifier" empty state |
| `BLOCKED` | generic "not available" state (F9 — never expose *why* a non-IDX listing is hidden beyond the neutral copy) |
| `NO_CRITERIA` | "set up your search criteria first" empty state (links to existing criteria flows) |
| `CRITERIA_NOT_LOADED` | neutral "we couldn't load your criteria this time" retry state |
| `DISABLED` | **unreachable via HTTP** (middleware 404s first) — no template branch required, but the view is written to degrade to the NOT_FOUND/neutral state if ever hit from a test |

**Hard UI exclusions (enforced at the template boundary):**
- **F8 (no narrative AI):** the view **never** references `report.narrative`. That slot is null through
  git-C13 and stays null; git-C14 does not render it, link it, or add a "generate narrative" affordance.
- **F7 (no restricted fields):** the view renders **only** `MatchCheckAnalysis->toArray()` output,
  which is already scalars/arrays with `$raw` excluded. No template reads `raw_json`, `PublicRemarks`,
  contact/media, or lockbox data — there is no path to it, since the analysis never carries it.
- **No Matching V2 surface:** the view does not touch `matching_v2_*`, `MatchResultPersister`, or any
  V2 read/write path. It renders the git-C13 `MatchReport` projection and nothing else.

---

## 2. Inertness / gating model for git-C14

git-C14 adds a reachable surface, so "inert" now means **"invisible and non-functional while the flag
is OFF (its default)"**, guaranteed at two independent layers:

- **Middleware 404 (primary):** `Route::middleware('match-check')` → `CheckMatchCheckEnabled` → `abort(404)`
  whenever `config('mls_match_check.enabled')` is false. Default OFF ⇒ **both routes 404 for everyone**,
  including authenticated users. No form, no controller, no orchestrator call is reached.
- **Orchestrator self-gate (defense-in-depth):** even if the route were somehow reached with the flag
  OFF, `analyzeBy*()` returns `MatchCheckAnalysis::disabled()` **before** any Bridge call, DB read, or
  enrichment dispatch. Zero external I/O while OFF.
- **No flag flip:** git-C14 does **not** set `MLS_MATCH_CHECK_ENABLED`; `config/mls_match_check.php`
  is **unchanged**; `.env` is untouched. Enabling the feature remains a separate, owner-driven step.
- **No new gate primitive:** the middleware and its alias already exist; git-C14 adds neither.

---

## 3. Files changed / added

| File | Kind | Purpose |
|------|------|---------|
| `routes/web.php` | **edit (additive)** | Add the `['auth','match-check']` route group with `show` + `lookup`. Mirrors the `agent-ai-v2` group. No existing route touched. |
| `app/Http/Controllers/MatchCheck/MatchCheckController.php` | **new** | Thin controller: `show()` + `lookup()`. Delegates 100% to `MatchCheckOrchestrator`; no business logic. |
| `app/Http/Requests/MatchCheck/MatchCheckLookupRequest.php` | **new** | FormRequest: validate `mode` (`mls`/`address`[/`listing_key`]) + the identifier fields. |
| `resources/views/match-check/show.blade.php` | **new** | The lookup form (identifier input + mode). |
| `resources/views/match-check/result.blade.php` | **new** | Status-driven result view (the §1.3 table). Renders `MatchCheckAnalysis` only. |
| `resources/views/match-check/partials/*` | **new (optional)** | Small partials for the SCORED report block + the AMBIGUOUS candidate list, if `result.blade.php` grows past readability. |
| `tests/Feature/MatchCheck/MatchCheckRouteGateTest.php` | **new** | Flag OFF → both routes 404; guest → login redirect; flag ON + auth → 200. |
| `tests/Feature/MatchCheck/MatchCheckControllerTest.php` | **new** | Each `MatchCheckAnalysis` status renders its state; F7/F8 assertions (no `narrative`, no `raw_json`/`PublicRemarks`/PII/lockbox in the HTML). |
| `docs/match-check-phase4-git-c14-scope.md` | **new** | This doc. |

**No** change to: `app/Http/Middleware/CheckMatchCheckEnabled.php`, `app/Http/Kernel.php` (alias already
registered), `config/mls_match_check.php` (flag stays OFF; no key added), `.env`, any
`app/Services/**` file (the orchestrator + analysis are consumed as-is), any migration, any Matching V2
file, any Offer/hire-agent Blade, any Google Places / safeguard file, or CLAUDE.md.

---

## 4. Test plan

- **Route gate** — with `mls_match_check.enabled=false` (default): `GET /match-check` and
  `POST /match-check` both return **404** (authenticated and guest alike). With the flag ON: a guest is
  redirected to login; an authenticated user gets **200** on `show`.
- **Controller dispatch** — with the flag ON, `lookup` in `mls` mode calls `analyzeByMlsNumber`, in
  `address` mode calls `analyzeByAddress`; the returned `MatchCheckAnalysis` reaches the view. Assert
  via a bound orchestrator test double (no real Bridge/DB/enrichment).
- **Status rendering matrix** — the result view renders the correct state for each of: `SCORED`
  (report block populated), `AMBIGUOUS` (candidate list, N rows, re-submittable), `NOT_FOUND`,
  `BLOCKED` (neutral copy only), `NO_CRITERIA`, `CRITERIA_NOT_LOADED`.
- **F8 — no narrative** — the rendered HTML for a SCORED analysis contains **no** `narrative` content
  and no narrative-generation affordance (the `report.narrative` slot is null and unreferenced).
- **F7 — no restricted fields** — the rendered HTML (incl. the AMBIGUOUS candidate list) contains **no**
  `raw_json` / `PublicRemarks` / PII / lockbox strings, across a residential + a non-residential fixture.
- **Flag OFF fully inert** — with `Http`/`Queue` fakes, hitting either route while OFF makes **zero**
  Bridge calls and **zero** `ComputeLocationDna` dispatches (the 404 fires before the controller).
- **Throttle** — the POST enforces its per-user request cap (decision 4) once the flag is ON.

Full MatchCheck + `tests/Feature/` suites stay green (modulo the pre-existing, unrelated
`LocationDnaRoundTripTest` failure noted in the git-C13 doc).

---

## 5. Out of scope for git-C14

- **Enabling `mls_match_check.enabled`** or any `config`/`.env`/migration change. The feature ships
  invisible; the owner flips it later.
- **Matching V2** — no `matching_v2_*`, `MatchResultPersister`, `MATCHING_V2_*` flag, or V2 read/write
  path. Separate track, untouched.
- **Narrative AI (F8)** — the `MatchReport.narrative` slot stays null and **unrendered**; no OpenAI /
  DNA-narrative call, affordance, or copy.
- **The "better matches" low-score tail (F2)** — git-C16; git-C14 does not read `better_matches_enabled`
  or surface a discovery section.
- **The standalone compliance regression test** — git-C15. git-C14 asserts F7/F8 inline at the view
  boundary but does not add the dedicated cross-cutting regression suite.
- **Any Offer / hire-agent Blade, Google Places / safeguard, docs-index, or scratchpad/tooling work** —
  none of the concurrent-commit surface area is touched.
- **Editing the orchestrator, analysis, report, scorer, builder, lookup service, or the throttle store**
  — all consumed as-is. git-C14 changes no `app/Services/**` file.
- **New middleware or a new config key** — the `match-check` alias + `CheckMatchCheckEnabled` already
  exist and are reused.

---

## 6. Compliance & symmetry notes

- **Role symmetry:** Match Check is a **Buyer/Tenant consumer** feature (the orchestrator resolves
  buyer *or* tenant criteria via `CriteriaIntentDetector`/`CriteriaListingResolver`). git-C14 exposes a
  **single** identifier-driven surface that is intent-agnostic at the HTTP layer — the backend already
  auto-selects the buyer-vs-tenant engine. **No** Seller/Landlord variant is created (they are not the
  consumers of this feature), so the usual quadruplication does **not** apply here — this is called out
  explicitly so a reviewer does not flag a "missing" Seller/Landlord route.
- **F9 visibility** is enforced in the backend (`ListingVisibilityGate` inside `prepare()`), so a
  non-IDX listing reaches the view only as `BLOCKED` with neutral copy — the UI never explains the
  block beyond "not available."

---

## 7. Open decisions for the owner (before coding)

1. **Plain controller + Blade vs. Livewire component.** The codebase uses both; the gated `agent-ai-v2`
   precedent is a plain controller with POST endpoints. Recommendation: **plain controller + Blade**
   for C14 (stateless lookup → render; the Livewire wizards are for multi-tab draft/submit forms, which
   this is not). Confirm.
2. **Which identifiers get a UI field.** The orchestrator exposes MLS#, ListingKey, and address. RESO
   `ListingKey` is an internal/opaque key a consumer won't type. Recommendation: **MLS# + address** as
   the two UI modes; keep `analyzeByListingKey` available but **not** surfaced as a primary field (it
   can back a deep-link later). Confirm — or expose all three.
3. **Auth requirement.** `analyzeBy*()` needs a `User` for per-user criteria resolution. Recommendation:
   **require `auth`** (stack it before `match-check`); a guest gets the normal login redirect.
   Confirm — or allow a guest path that always renders `NO_CRITERIA`.
4. **Throttle on the POST.** Recommendation: add `throttle:<n,1>` (per-user/min) to `POST /match-check`
   to cap consumer-driven Bridge lookups, on top of the git-C12/C13 enrichment guard. Confirm the rate
   (suggest a conservative default, e.g. `throttle:20,1`, aligned with the C8 forward-declared
   `dna_rate_limit_per_user_hourly`).
5. **Route paths / names.** `GET /match-check` = `match-check.show`, `POST /match-check` =
   `match-check.lookup`. Confirm the URI and route-name convention (these become the public surface).
6. **AMBIGUOUS re-submission shape.** When the address matches N candidates, each disambiguation row
   re-submits — via `ListingKey` (precise, but see decision 2) or via the fully-qualified address of the
   chosen unit. Recommendation: re-submit the chosen candidate's **`ListingKey`** through the (kept)
   `analyzeByListingKey` path for an exact, no-re-ambiguity resolution. Confirm.

---

*This document changes no production code, tests, or config, and stages no unrelated file. It is to be
committed path-scoped alongside git-C14's code + tests when that slice lands. Implementation begins
only after the owner reviews this scope and resolves §7.*
