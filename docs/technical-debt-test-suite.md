# Technical Debt — Test Suite

**Raised:** 2026-07-25, while verifying Phase 0 of the [Florida Spatial Integration](./spatial-integration-roadmap.md) project.
**Status:** Backlog. **Not** to be fixed as part of the spatial work — logged so it is visible without derailing the milestone.

Everything below was **verified as pre-existing** by running the suite against a clean worktree (`/home/runner/offer-listing-submit-fix`) checked out at the same commit (`10037715a`) with none of the Phase 0 changes applied. Failure sets were captured on both trees and diffed by test name, not by count.

---

## Summary

| # | Item | Scale | Verified pre-existing |
|---|---|---|---|
| **TD-1** | Ask AI unit-test failures | ~195 tests | ✅ identical on clean tree |
| **TD-2** | SQLite vs PostgreSQL `ILIKE` incompatibility | 36 occurrences, ~15 tests | ✅ identical on clean tree |
| **TD-3** | PHPUnit 512 MB memory ceiling — suite cannot finish in one run | Aborts at ~8,127 / 9,284 | ✅ identical on clean tree |
| **TD-4** | Offer permission / visibility failures | ~24 tests | ✅ identical on clean tree |

**Total pre-existing failures:** 214 in `tests/Unit`, 55 in `tests/Feature/Offers`, 8 in `tests/Feature/Security`.
`tests/Feature/ListingImport`, `Dna`, `Agent`, `OfferListing` and `Location` are fully green on both trees.

---

## TD-1 · Ask AI unit-test failures (~195 tests)

**The single largest block of debt, and nothing to do with the spatial work.** Concentrated almost entirely in the Ask AI coverage harnesses:

| Test class | Failures |
|---|---:|
| `Tests\Unit\Services\AskAi\AskAiPipelineCoverageE2ETest` | 127 |
| `Tests\Unit\Services\AskAi\AskAiListingFieldPipelineE2ETest` | 25 |
| `Tests\Unit\Services\AskAi\AskAiRoofNlpQaTest` | 16 |
| `Tests\Unit\Services\AskAi\AskAiApprovedFieldCoverageHarnessTest` | 13 |
| Other Ask AI classes | ~14 |

Predominant symptom is a bare `Failed asserting that false is true` (180 occurrences suite-wide), which suggests coverage assertions over a field map that has drifted from the harness's expectations rather than a functional break.

**Recommended owner:** whoever owns the Ask AI knowledge-base work.
**Priority:** medium — these are coverage harnesses, so while red they provide no signal, and a real Ask AI regression would be invisible.

> ⚠️ Correction to an earlier verbal summary: these were initially described as mostly an `ILIKE` problem. That was wrong. `ILIKE` is TD-2 and is confined to the Offers feature suite. The Ask AI failures are a separate, larger issue.

---

## TD-2 · SQLite vs PostgreSQL `ILIKE` incompatibility (36 occurrences)

`getPlaceSuggestions()` — duplicated across the Buyer, Seller, Landlord and Tenant offer-listing components — issues raw `ILIKE` queries:

```sql
select * from "us_cities" where ("name" ILIKE Madeira Beach% or REPLACE(name, '.', '') ILIKE ...)
```

`ILIKE` is a PostgreSQL operator. Production runs PostgreSQL, so this works live; the test suite runs in-memory SQLite (see `tests/CreatesApplication.php`), where it is a syntax error. Any test that sets `address`, `property_city` or `state` on one of those components throws `QueryException`.

**Impact beyond the failures themselves:** it makes those components partly untestable. A Phase 0 test had to be rewritten to drive the component instance directly rather than through `set('property_city')`, purely to route around this. Future spatial phases will hit the same wall.

**Fix options:**
1. Replace `ILIKE` with `whereRaw('lower(name) like ?', [strtolower($v).'%'])` — portable, no behaviour change on Postgres.
2. Extract the city-suggestion query into a service with a driver-aware operator.
3. Run the suite against PostgreSQL — larger change, conflicts with the deliberate in-memory isolation described at length in `tests/TestCase.php`.

**Recommendation:** option 1. It is small, portable, and unblocks testing of all four components.
**Priority:** medium-high — this one actively obstructs the spatial roadmap.

---

## TD-3 · PHPUnit 512 MB memory ceiling

The full suite **cannot complete in a single run.** It aborts at roughly test 8,127 of 9,284 while rendering a Landlord Blade view:

```
Fatal error: Allowed memory size of 536870912 bytes exhausted
  in storage/framework/views/381f673df371612042dd35de148c33461cde8205.php on line 2546
```

The limit is pinned in `phpunit.xml`:

```xml
<ini name="memory_limit" value="512M"/>
```

Because PHPUnit's `<ini>` directives override the command line, `php -d memory_limit=…` does **not** raise it. The practical consequences:

- `php artisan test` with no arguments never reports a full result — it dies mid-run with a fatal, not a failure summary.
- Any CI job invoking the whole suite is reporting on a partial run.
- Verifying "no regressions" requires running per-directory and diffing, which is what was done for Phase 0.

**Fix options:**
1. Raise the `phpunit.xml` limit to 2–4 GB. One line; does not address the underlying growth.
2. Investigate the accumulation — 9,284 tests should not need 512 MB. Likely candidates: retained compiled-view caches, Livewire component instances, or static fixtures never released between tests.
3. Split into parallel test suites (`--testsuite`), which also cuts wall-clock time.

**Recommendation:** 1 now so CI reports honestly, then 2 as a follow-up.
**Priority:** high — a suite that cannot finish is a suite nobody can trust.

---

## TD-4 · Offer permission / visibility failures (~24 tests)

| Test class | Failures |
|---|---:|
| `OfferDetailPermissionTest` | 9 |
| `OfferActionVisibilityTest` | 8 |
| `OfferTermsDisplayTest` | 6 |
| `OfferTerminalNegotiationTest` | 3 |

Distinct from TD-2 (these are assertion failures, not `ILIKE` errors) and unrelated to spatial work. Given the subject matter — **who may see and act on an offer** — these deserve a look on their own merits rather than being left red indefinitely.

**Priority:** high, on security grounds. A permanently red permission suite cannot tell you when permissions break.

---

## Method — how "pre-existing" was established

```bash
# Baseline: clean worktree, same commit, none of the Phase 0 changes
cd /home/runner/offer-listing-submit-fix
php artisan test tests/Feature/Offers | grep "⨯" | sort > base.txt

# Changed tree
cd /home/runner/workspace
php artisan test tests/Feature/Offers | grep "⨯" | sort > mine.txt

diff base.txt mine.txt   # must be empty
```

Failure **counts** alone are not sufficient evidence — a fixed test and a newly broken one cancel out. Failures were therefore diffed **by name**. During Phase 0 this caught nine genuine regressions that a count comparison would have partly masked.

One caveat worth recording: the baseline worktree contains two untracked scratch test files (`tests/Feature/Offers/DumpHtmlTest.php`, `ReproTest.php`) left by an earlier session, so its **total** test count runs seven higher than the working tree. The failure sets are unaffected.
