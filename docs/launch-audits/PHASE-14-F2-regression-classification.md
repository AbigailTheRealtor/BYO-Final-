# Phase 14 · Step 2 — Full Regression Run & Failure Classification

**Date:** 2026-07-02
**Branch:** `launch-audit-remediation`
**Type:** Diagnostic / classification pass. **No production code changed.** No B5.4 fix. No browser QA.
**Command:** `php artisan test` (full suite) + targeted per-suite isolation runs.

> Goal: run the automated regression suite and classify **every** failure as
> (A) Pre-existing / environmental, (B) Real application regression,
> (C) Test-harness issue, or (D) Needs manual/browser verification.
> Per C14: browser-only items are **not** marked PASS.

---

## TL;DR

- **The suite cannot produce a clean green baseline in this environment** because of **two independent harness/environment faults** (below). This is the dominant driver of failures — not Phase 8–13 code.
- **Exactly one genuine application regression** was isolated: an **unguarded `$purchase_purpose`** Blade variable (Phase 12) that 500s the **live/unified Hire-Agent buyer create + edit** flow. It is **not** B5.4.
- Everything else resolves to environmental state-pollution, a Postgres-only migration, external-API dependence, stale test assertions, or out-of-scope consumer features.

---

## Environmental root causes (both pre-date Phase 8–13)

### E1 — Tests run against the **live PostgreSQL** DB, not SQLite in-memory (dominant)
- `config/database.php:18`: `'default' => env('DATABASE_URL') ? 'pgsql' : env('DB_CONNECTION', 'mysql')`.
- `DATABASE_URL` is injected into the Replit shell (`postgresql://…@helium/heliumdb`). Because the ternary checks `DATABASE_URL` **first**, it **overrides** `phpunit.xml`'s `DB_CONNECTION=sqlite` / `DB_DATABASE=:memory:` **and** `.env.testing`.
- Result: the suite executes against the **shared live Postgres database with accumulated data**, producing:
  - `SQLSTATE[23505] … duplicate key value violates unique constraint "users_email_unique"`
  - state-pollution assertion failures (extra rows break count/filter/`assertSee` checks; e.g. `ExpireOffersCommand` facade `expire()` "called 2 times" because *other* expired offers already exist in the shared DB).
- **Proof it is state-pollution, not code:** `Tests\Feature\Showing\ShowingApprovalTest` = **36 failed** inside the full run but **36 passed / 0 failed when run in isolation** (RefreshDatabase rolls back per test).

### E2 — SQLite path is broken by a Postgres-only migration + memory limit
- With `DATABASE_URL` unset (forcing the intended sqlite):
  - `database/migrations/2026_05_31_000002_create_marketing_reports_table.php:51` executes raw `DB::statement()` with Postgres-specific `CONSTRAINT` syntax → `SQLSTATE[HY000]: General error: 1 near "CONSTRAINT": syntax error` → `migrate` aborts → tables missing → every test errors at setup.
  - The in-memory run also hits `Allowed memory size of 536870912 bytes exhausted` (`memory_limit=512M` in `phpunit.xml`).
- Net: the documented "SQLite in-memory" test path is currently **non-functional**.

> **Consequence:** neither DB path yields a trustworthy pass/fail baseline without harness/config/env changes — which are production/config edits and therefore **out of scope for this no-fix step.**

---

## Failure classification

### (B) Real application regression — 1 confirmed
**Unguarded `$purchase_purpose` breaks the live/unified Hire-Agent buyer flow.**
- Source: Phase 12 (`284577b02`) added
  `resources/views/livewire/hire-buyer-agent/buyer-agent-auction-tabs/commission-based/property-preferences.blade.php:1209`
  → `{{ $purchase_purpose === 'Other' ? '' : 'd-none' }}` (bare, unguarded).
- That partial is shared. It is `@include`d by the unified live components via `@case('buyer')`:
  - `TenantAgentAuction` (live create) → `resources/views/livewire/tenant-agent-auction.blade.php:1725`
  - `TenantAgentAuctionEdit` (live edit) → `resources/views/livewire/tenant-agent-auction-edit.blade.php:1682`
- Neither `TenantAgentAuction` nor `TenantAgentAuctionEdit` defines `public $purchase_purpose` → **`Undefined variable $purchase_purpose` (ViewException / 500)** when the Property Preferences tab renders for a buyer.
- The **dedicated** `BuyerAgentAuction` / `BuyerAgentAuctionEdit` flow is **unaffected** (both define the property at lines 196 / 188).
- Reproduced deterministically by `Tests\Feature\Offers\HireSearchAreasParityTest::test_live_buyer_create_renders_search_areas_map` (DB-independent view error).
- **Scope check:** this is the *only* unguarded component-variable of its class. The seller/landlord partials' apparent hits (`$city`, `$zip`, `$suggestion`, `$index`, `$county`, `$message`) are all `@foreach` loop-locals, not component properties — no defect.
- **Not B5.4.** B5.4 is the Hire Buyer bed/bath "Other" select; this is a different field (`purchase_purpose`).

### (C) Test-harness / stale-test issues
- **`OfferDetailPermissionTest`** asserts the message `"counter offer is already pending"`, but production `app/Services/Offers/OfferPermissionService.php` now returns `"Cannot {accept,reject,counter}: this offer has been superseded by a newer counter offer…"`. The **behavior is correct** (action blocked); only the **test's expected string is stale**. The old string no longer exists anywhere in `app/`.
- **AskAi cost/usage suites** (`AskAiCostTrackingTest`, `AskAiListingQuestionTest`, `AskAiUsageLoggingTest`, `AskAiApiTest`, `AskAiRateLimiterTest`) short-circuit to `status='failed'` / `success=false` / 0 tokens despite mocked adapters — the pipeline is not reaching the mocked adapter in this environment (OpenAI config / adapter-mock wiring). Unrelated to Phase 8–13 (placeholder/field-text work never touched AskAi token logging).

### (A) Pre-existing / environmental (out of Phase 8–13 scope)
- **State-pollution cascade** from E1: the large body of `assertSee('<!DOCTYPE html>' …)`, `422 vs 200`, `notifications`-row-match, and Mockery call-count failures across `tests/Feature/Offers/*`, `BuyerWizardTabNavigationTest`, `ShowingApprovalTest` (proven green in isolation), etc.
- **`Tests\Feature\CriteriaSearchSortTest`** — `SQLSTATE[42703]: column "created_at" of relation "tenant_criteria_auction_metas" does not exist`. Live-Postgres schema drift on the shared DB (meta table lacks timestamps there) / ordering query. Environmental; out of scope.
- **`Tests\Feature\Stellar\BuyerMatchingEngineTest`** — 11 failures persist in isolation (`$match` null for pool/geo/gate cases). Consumer matching engine, likely gated by `BYA_COMPATIBILITY` kill switch (default `true`) and/or LocationDna data prerequisites. Out of Phase 8–13 scope; needs its own investigation.
- **LocationDna geocode / POI suites** (`LocationDnaGeocodeServiceTest`, `LdnaPoiCostReportCommandTest`, `LocationDnaRoundTripTest`) — external API (Google / FEMA / Census) dependent; environmental.
- **`NotificationPayloadContractTest`** — 1 contract failure ("all to-database classes are covered by provider"): a notification-class registry/coverage gap. Minor; out of Phase 8–13 scope.

### (D) Needs manual / browser verification (not marked PASS)
- All F.1 🟡 standards: **S3, S4, S7, S10, S11, S14, S16** (runtime/visual/multi-surface dimensions).
- **`HireAgentDirectReadOnlyReviewTest`** accept/counter POST (10 fails): POST redirects but no listing is persisted — most likely the confirm flow's **address validation (external Google Places)** failing in-test, or a genuine create issue. In-domain (hire-agent) → **flag for manual verification**; not confirmed as a Phase 8–13 regression.
- AskAi golden-QA / live-UI suites (need OpenAI + browser).
- **B5.4** remains a held item (S3/S7) — not fixed, not verified.

---

## Recommendation on B5.4 vs. browser QA
- The **`$purchase_purpose` 500** is more severe than B5.4 and **partially blocks browser QA of the buyer Hire-Agent flow** (the live create/edit Property Preferences tab errors out). It should be fixed **before** browser QA of that flow.
- **B5.4** lives in the *same* Hire Buyer surface and touches the same S3/S7 "Other"-field dimension that browser QA will exercise. It is therefore most efficient to fix B5.4 **in the same pre-QA code batch** as `$purchase_purpose` (a one-line guard) — but that is an **owner decision** and outside this no-fix step. Per current instructions, **neither is fixed here.**

---

## Deliverable confirmations
- **Production code modified:** **none** (`git status` shows only `.replit` + `.claude/settings.local.json`, both pre-existing tooling/env changes, plus new docs/scratchpad). No `app/`, `resources/`, `config/`, `database/`, or `routes/` changes.
- **B5.4:** not fixed.
- **Browser QA / C13 / C14:** not started.
- **Browser-only items:** none marked PASS.

> **Note:** The confirmations above describe the **Step 2 diagnostic pass** and were accurate at that time. The subsequent approved fix pass is recorded in **Step 3** below.

---

# Phase 14 · Step 3 — Approved Pre-Browser-QA Hire Buyer Fix Pass

**Date:** 2026-07-02
**Branch:** `launch-audit-remediation`
**Type:** Targeted remediation. Owner-approved scope: fix the `$purchase_purpose` 500; fix B5.4 only if safe within the Hire Buyer surface and verifiable without a browser.

## Fix applied — `$purchase_purpose` 500 (the one confirmed (B) regression)
- **File:** `resources/views/livewire/hire-buyer-agent/buyer-agent-auction-tabs/commission-based/property-preferences.blade.php` (line 1209) — **one line changed, the only product-code edit in this pass.**
- **Change:** `{{ $purchase_purpose === 'Other' ? '' : 'd-none' }}` → `{{ ($purchase_purpose ?? '') === 'Other' ? '' : 'd-none' }}`.
- **Why it works:** This shared partial is `@include`d for the buyer case by the unified live components (`TenantAgentAuction` create → `tenant-agent-auction.blade.php:1725`; `TenantAgentAuctionEdit` → `tenant-agent-auction-edit.blade.php:1682`). Neither declares `public $purchase_purpose`, so the bare variable threw `Undefined variable` (ViewException / 500) when the buyer Property Preferences tab rendered. The `?? ''` null-guard matches the exact idiom already used by every other conditional wrapper in the same partial (lines 258, 545, 561, 705, 914, 1265).
- **No-op for the dedicated flow:** `HireBuyerAgent/BuyerAgentAuction.php` and `HireBuyerAgent/BuyerAgentAuctionEdit.php` both declare `public $purchase_purpose`, so `($x ?? '')` is identical to `$x` there — zero behavior change; only the previously-500ing unified live case is affected.

## Tests now passing (post-fix, isolation)
- `HireSearchAreasParityTest::test_live_buyer_create_renders_search_areas_map` — **PASS** (this was the deterministic reproduction; failing pre-fix).
- Full `HireSearchAreasParityTest` + `HireBuyerPortedFieldsRoundTripTest` — **11 passed / 0 failed**, covering live buyer create, live buyer edit, dedicated buyer create/edit, draft-save, and ported-field round-trip.

## Confirmation — unified live buyer 500 resolved
**Resolved.** The 500 on the unified live Hire-Agent **buyer create and edit** Property Preferences tab is fixed and verified by the previously-failing test now passing, with no behavior change to the dedicated buyer flow.

## Confirmation — B5.4 remains a documented hold
**Held (unchanged).** B5.4 (bed/bath "Other" makes the main select box disappear, only the icon shows) meets **both** hold criteria:
- The disappearing element is the **main plain `has-icon` single select**, not a select2 dependent. `public/js/select2-stable.js` attaches select2 only to `.select2-multiple` / `.select2` / `[data-select2]` — never the bed/bath selects — so the "working garage" pattern (always-render + `d-none` + `wire:ignore`), which fixed a select2-*multiple dependent*, has no safe analogue here. The Phase 12 commit (`284577b02`) reached the same conclusion.
- The real fix is **global `has-icon` JavaScript** (`select2-stable.js`), and the symptom is a rendered-DOM/morphdom artifact that **cannot be safely verified without a browser**. → Both disqualifiers (unsafe global JS + browser-only verification) apply. Carries forward to browser QA (S3/S7).

## Confirmation — no unrelated production code modified
**Confirmed.** The only product-code change is the one-line null-guard above. No Seller, Landlord, Tenant, Ask AI, public-visibility, `initializeLimitedService()`, or other unrelated code was touched. The `resources/views/partials/location-dna/map-input.blade.php` change present in `git status` was **pre-existing in the working tree at the start of this session** (Location DNA work) and was left untouched. No commit was made in this pass.
