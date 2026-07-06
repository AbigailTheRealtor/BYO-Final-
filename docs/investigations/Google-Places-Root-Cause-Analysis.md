# Google Places `NearbySearch` — Root Cause Analysis

**Subject:** ~38,236 `google.places.NearbySearch.Http` requests over 6 days, incl. a ~21,702-request spike on **2026-07-05**.
**Status:** Investigation complete. Root cause proven at the mechanism/per-unit level; exact request-count reconstruction partially proven (see *Remaining Unknowns*).
**Constraint honored:** No production code modified. All measurement done offline with a network backstop; **zero live Google requests were issued by this investigation.**

---

## 1. Executive Summary

The requests were **not** produced by production user traffic, a real MLS import, a queue worker, the scheduler, or browser page views. They were produced by the **automated PHPUnit test suite executing the real Location DNA POI pipeline against the live Google Places endpoint.**

Four independent conditions combined to let test code make live, billable API calls:

1. **`QUEUE_CONNECTION=sync`** in the test env → a dispatched `ComputeLocationDna` job runs **inline**, immediately, inside the test.
2. **The `GOOGLE_PLACES_API_KEY` is present in the test process** as a system environment variable (a 39-character value — the standard length of a Google Maps API key; the value itself was not printed). `.env.testing` and `phpunit.xml` do **not** unset it, and dotenv cannot override a system env var.
3. **The POI HTTP calls use raw Guzzle (`new Client()`)**, which Laravel's `Http::fake()` **cannot intercept**, and the test base has **no global network guard and no stub binding**.
4. **Almost no feature test isolates the queue**: only **4 of 140** feature-test files call `Bus::fake()`/`Queue::fake()`. Any of the remaining tests that saves an observer-tracked listing model runs the `observer → job → live API` chain synchronously.

Each triggered "generation" makes **exactly 16** NearbySearch requests (measured). Because tests use `RefreshDatabase` + an `array` cache, **no caching survives between tests or runs**, so every generation re-fetches all 16 categories from scratch.

**Measured, proven facts (offline, zero live calls):**
- Path A (property Location DNA) = **16** NearbySearch requests / generation.
- Path B (buyer/tenant lookup) = **7** NearbySearch requests / lookup.
- The current suite still *attempts* live NearbySearch: a full run made **60** attempts (all intercepted/blocked by the backstop; on 2026-07-05 with the live key these would have reached Google).

**Primary root cause (HIGH confidence):** test-environment isolation failure — live API key + sync queue + un-fakeable raw-Guzzle client + side-effecting observers, with no safeguard at any layer.

**What cannot currently be proven (LOW confidence):** the exact arithmetic reconstruction of 21,702 in a single reproducible run. The current suite yields only ~15 generations/run because reproducing more requires letting blocked external dependencies (Bridge OData, Google Geocoding) succeed — which the backstop deliberately prevents. See §11.

---

## 2. Timeline

| Date (2026) | Event | Evidence |
|---|---|---|
| 05-28 | DNA observers first wired (`PropertyAuctionDnaObserver` etc.) | git `ca0b7374a` |
| 05-31 | Path A caller `LocationDnaPoiDistanceService` created | git `61f63cc74` |
| 06-14 | Path B caller `GooglePlacesPoiAdapter` created | git `f949a7da4` |
| 06-23 | Tile cache added (default precision `null` = **disabled**) | git `bf33bb219`; `config/location_dna.php` |
| 06-23…25 | Bridge lazy-import + "prevent duplicate API calls" work | git `1e09cc255`, `19c54cf6e` |
| 06-27 / 06-28 | Genuine production POI batch (5 `seller_agent` listings) | `property_location_pois` created_at: 772 / 193 rows |
| **07-05 16:51–23:34** | **Intensive Location DNA POI/cache/version dev session** (Stage A–E0, provider registry, version stamping) | 15 commits `00e606b8b`…`12692c6a0` |
| **07-05 (7 bursts, peak 22:51–23:13)** | **21,702 NearbySearch requests** | Google Metrics; `laravel-2026-07-05.log` (323 dispatches, 7 bursts) |
| 07-06 | Investigation; API disabled by owner | this doc |

---

## 3. Investigation Process

1. Enumerated **every** code path that can call `…/place/nearbysearch/json` (exhaustive grep).
2. Traced every invoker of the two callers (commands, jobs, observers, controllers, Livewire, bridge services, tests).
3. Pulled production DB state (`heliumdb`) for persistence, cache, and reusability.
4. Parsed the July-5 application log for dispatch source, environment, fixtures, and burst timing.
5. Read `.env.testing` / `phpunit.xml` / `tests/TestCase.php` to establish the test-env configuration.
6. **Measured** per-generation and per-lookup request counts with a counting mock HTTP client (zero network) + transaction rollback.
7. **Ran the full suite** through a counting HTTPS proxy backstop that intercepts and blocks every outbound request while counting it by host.
8. Reconciled measured numbers against the 21,702 / 38,236 metrics.

**Backstop design (safety):** a local CONNECT proxy on `127.0.0.1:8099`; `HTTPS_PROXY`/`HTTP_PROXY` routed all Guzzle traffic to it; it counts each request by host and closes the tunnel so **nothing egresses**. Verified: a direct Guzzle call to the NearbySearch URL returned `cURL error 56: Proxy CONNECT aborted` and was counted — proving interception. Plus a fake key, plus the owner having disabled the API = three independent safety layers.

---

## 4. Hypotheses Tested

| # | Hypothesis | Verdict | Evidence for | Evidence against |
|---|---|---|---|---|
| H1 | Production **browser** traffic (buyer/tenant views, Path B) | **DISPROVEN** | Path B exists (7/view) in 4 controllers | App not launched; July-5 log has **0** local-env LocationDna/compose activity (only landlord-draft Livewire mounts); **0** production persistence on 07-05 |
| H2 | Real **MLS import** of 667 properties | **DISPROVEN** | Importer dispatches per record | **0** `ImportBridgeProperties` log lines on 07-05; `bridge_dna_records = 0` / 667 |
| H3 | **Queue worker** replay | **DISPROVEN (impossible)** | — | `QUEUE_CONNECTION=sync`; no `queue:work`/Horizon/supervisor anywhere |
| H4 | **Scheduler / cron** | **DISPROVEN (impossible)** | — | Only `offers:expire-pending` scheduled; makes no Places call |
| H5 | `ldna:refresh-all` / `rerank-all` / `benchmark` | **Ruled out as major** | Commands can call Path A | `rerank-all` is API-free by design; refresh/backfill bounded by 11 DNA records (≤176); benchmark ≤~880/run; no evidence any ran 07-05 |
| H6 | **Automated test suite** | **SUPPORTED; mechanism & per-unit PROVEN; full count NOT reproducible** | 323 `testing.INFO` dispatches, 7 bursts, synthetic fixtures; measured 60 live attempts even today; key-leak + sync + raw-Guzzle + unfaked observers all confirmed | Current suite yields only 15 generations/run — see §11 for why the exact 21,702 can't be reproduced now |

**By elimination + direct evidence, H6 is the only surviving source.** H1–H5 are each affirmatively excluded by architecture, dataset bounds, or log/DB absence.

---

## 5. Final Root Cause

> The test suite ran the **real** `LocationDnaPoiDistanceService::calculateForListing()` / `GooglePlacesPoiAdapter::search()` code against the **live** Google Places NearbySearch endpoint, because the test environment had a live API key, a synchronous queue, an un-fakeable HTTP client, and side-effecting observers — with no isolation, no cache, and no quota at any layer.

### Exact execution path (Path A — the dominant one)

```
php artisan test
  (APP_ENV=testing, QUEUE_CONNECTION=sync, DB=sqlite :memory:, real GOOGLE_PLACES_API_KEY in env)
   │
   ▼  a feature test saves a PropertyAuction / SellerAgentAuction / LandlordAuction
      (only 4 of 140 feature files fake the queue; the rest run the job synchronously on save)
   │
   ▼  App\Observers\Dna\PropertyAuctionDnaObserver::saved()      [no dirty-check]
        → App\Jobs\ComputeLocationDna::dispatch('seller', id)
   │       QUEUE=sync → job runs INLINE
   ▼  App\Jobs\ComputeLocationDna::handle()
        → App\Services\LocationDna\LocationDnaPipelineRunner::run()
             1. geocode  2. POI  3. summary  4. lifestyle
   │
   ▼  LocationDnaPoiDistanceService::calculateForListing()
        RefreshDatabase → no cached rows; tile cache disabled → full fetch
        loop 19 categories − 3 grouped = 16 × fetchRawCandidates()
   │       $client = $this->httpClient ?? new Client();   ← raw Guzzle; Http::fake() cannot see it
   ▼  GET https://maps.googleapis.com/maps/api/place/nearbysearch/json   ← 16 LIVE requests
```

Bridge-fixture path is identical but reaches POI via `LazyBridgeImportService::…→ ComputeLocationDna::dispatch('bridge', id)` (323 such dispatches logged on 07-05). Bridge fixtures carry lat/lng, so geocode short-circuits and POI always runs → 16 each.

### Exact files & functions
- `app/Services/LocationDna/LocationDnaPoiDistanceService.php` → `calculateForListing()` (L474), `fetchRawCandidates()` (L876) — **the NearbySearch call**.
- `app/Services/LocationDna/GooglePlacesPoiAdapter.php` → `search()` (L57) — Path B NearbySearch call.
- `app/Jobs/ComputeLocationDna.php` → `handle()` — runs inline under sync queue.
- `app/Observers/Dna/PropertyAuctionDnaObserver.php` / `LandlordAuctionDnaObserver.php` → `saved()` — no dirty-check.
- `app/Services/Bridge/LazyBridgeImportService.php` (L150) — bridge dispatch (323× on 07-05).
- `tests/TestCase.php` → `setUpTraits()` — forces sqlite; **no network guard, no stub binding**.
- `.env.testing`, `phpunit.xml` — set sync/array/sqlite; **do not unset the Places key**.

---

## 6. Measured Evidence (the numbers)

All measured **offline** (counting mock/proxy, zero live calls):

| Measurement | Value | Method |
|---|---|---|
| NearbySearch per Location DNA generation (Path A) | **16** | mock client + `calculateForListing('seller_agent', 654)` in a rolled-back transaction; status `completed` |
| NearbySearch per buyer/tenant lookup (Path B) | **7** | mock client + `PoiDistanceLookupService::lookup()` |
| NearbySearch per observer save (that reaches POI) | **16** | = 1 generation; observers dispatch exactly one job, no dirty-check |
| NearbySearch per bridge import record (reaches POI) | **16** | bridge fixtures carry coords → geocode short-circuits → POI runs |
| **Full current suite** (7,643 pass / 356 fail-under-backstop) | **60 attempts / 15 generations** | counting proxy; all blocked |
| Bridge test dir alone | 14 attempts | counting proxy |
| Stellar dir alone | 0 attempts | counting proxy (tests fail before dispatch under backstop) |

**Per-unit is proven and exact.** The 16-per-generation figure independently matches production `location_dna_poi_run_stats` (`categories_fetched_fresh = 16` on every row).

---

## 7. Reconciliation to 21,702 / 38,236

- 21,702 ÷ 16 = **1,356 generations** on 07-05.
- 38,236 ÷ 16 = **2,390 generations** over 6 days.
- July-5 log directly evidences **323** bridge generations (→ ≥5,168 requests) **plus** unlogged observer generations (observer dispatches log nothing on success).

**Honest gap:** the current full suite reproduces only **15 generations / 60 attempts**, not 1,356. This is **not** contradictory — it is a limitation of the safe backstop:

- The backstop **blocks the external dependencies** the July-5 generating tests relied on to *proceed* to the POI step — Google Geocoding (for seller/landlord observer saves) and the Bridge OData API (`api.bridgedataoutput.com`, 15 CONNECTs observed). When those calls fail at the proxy, the pipeline stops **before** NearbySearch, so the attempts never happen under the harness.
- On 07-05, with the live key and live/faked externals succeeding, those same generations completed and reached the 16-call POI step.

So the mechanism and per-unit cost are proven; the **exact** July-5 per-run count is **not reproducible offline** without letting the external calls succeed (which would require modifying test/production code and risking live calls). See §14.

---

## 8. Why the requests repeated

- **`RefreshDatabase` resets the DB before every test** → the per-listing coordinate cache in `property_location_pois` is empty every time → each generation re-fetches all 16 categories.
- **`CACHE_DRIVER=array`** in tests → the tile cache and Path-B `poi_lookup_` cache are in-memory and **discarded per test/run** → no reuse.
- The suite (and individual Location-DNA tests under active development) was **run many times** during the 07-05 session (7 bursts).

## 9. Why caching failed

- **Tile cache disabled in production**: `LOCATION_DNA_POI_TILE_PRECISION` unset → `precision_used = null`, `categories_from_tile_cache = 0` on every run.
- **Tests use `array` cache** → nothing persists across tests or runs.
- **`RefreshDatabase`** wipes the per-listing DB coordinate cache each test.
- Even in production, **1,089 / 1,090 stored POI rows have `pois_fetch_version = NULL`**, which the reader treats as **stale** vs the current `06275e91…` → they would be re-fetched anyway.

## 10. Why testing hit the live API

1. **`QUEUE_CONNECTION=sync`** (`.env.testing`) → dispatch executes the job inline.
2. **Live key leaks in**: `.env.testing` does not set `GOOGLE_PLACES_API_KEY`; it exists as a **39-char system env var** that dotenv cannot override.
3. **Raw Guzzle** (`new Client()`) in both POI callers → `Http::fake()` is powerless.
4. **No global test guard**: `tests/TestCase.php` has no `Http` fake and no stub binding of `PoiLookupAdapterInterface` / `LocationDnaPoiDistanceService`.
5. **Only 4 of 140** feature files fake the queue/bus; the rest execute dispatched jobs synchronously when they save an observer-tracked model.

## 11. Why the API key wasn't blocked in testing
`.env.testing` sets `sqlite/array/sync` but **omits `GOOGLE_PLACES_API_KEY`**; `phpunit.xml` also omits it. The key is injected as a **system-level secret** (same mechanism `tests/TestCase.php` documents for `DB_CONNECTION`), which dotenv's immutable repository will not override. **Proof:** `.env.testing` contains no `GOOGLE_PLACES_API_KEY` line; the test process reports the variable SET at length 39 (the standard length of a Google Maps API key; the value was not inspected).

## 12. Why monitoring didn't catch it
- The POI callers **log nothing** about outbound requests — no URL, no count, no status. Grep of the 07-05 log for `nearbysearch|maps.googleapis|REQUEST_DENIED|guzzle` = **0**.
- There is **no metric/counter/alert** on Places usage in code.
- It was discovered only via the **Google Cloud bill**, days later.

## 13. Why budget controls didn't stop it
- **No code-level quota / budget / circuit-breaker / rate-limit** exists on the Places path (grep confirms none).
- **No Google Cloud budget cap, quota limit, or API-key restriction** was configured (external to this codebase; the owner reports having disabled the Places API after the spike was discovered).

---

## 14. Remediation Checklist (status)

**Legend:** ✅ Completed · ⏳ Planned / in progress · ❌ Not yet implemented

As of 2026-07-06, the only completed items are the containment action taken by the owner (disabling the API) and this investigation. **No source-code fixes have been implemented** — code changes were paused pending review. The statuses below are honest and auditable; move items to ⏳/✅ as they are scheduled and shipped.

### Containment (already done)
| Status | Action | Evidence |
|---|---|---|
| ✅ | **Google Places API disabled** in the affected Google Cloud project | Owner-reported (2026-07-06) |
| ✅ | **Root-cause investigation** completed with offline measurement | This document |

### Prevent live calls from tests (highest priority)
| Status | Action | Owner | Notes |
|---|---|---|---|
| ❌ | Bind `PoiLookupAdapterInterface` → `StubPoiLookupAdapter` **and** bind `LocationDnaPoiDistanceService` to a no-network instance in the test base (`tests/TestCase.php`) | — | Stops the observer→job→API chain in every test |
| ❌ | Set `GOOGLE_PLACES_API_KEY=` (empty) in `.env.testing` and `phpunit.xml`; add a guard test asserting the key is blank under `APP_ENV=testing` | — | Code already no-ops on a blank key |
| ❌ | Resolve the HTTP client from the container in both POI callers (remove bare `new Client()`) so faking works by default | — | Makes `Http`/mock faking effective |
| ❌ | Add a global stray-request guard so any un-mocked outbound call in tests fails loudly | — | Fail-closed instead of fail-to-network |

### Reduce production blast radius
| Status | Action | Owner | Notes |
|---|---|---|---|
| ❌ | Add a **dirty-check** to the DNA observers (dispatch only when address/coords change) | — | Removes redundant regenerations |
| ❌ | Add a **code-level circuit breaker / daily request cap** around the NearbySearch callers | — | Hard ceiling regardless of caller |
| ❌ | **Enable the tile cache** (`LOCATION_DNA_POI_TILE_PRECISION`) and fix `pois_fetch_version` stamping | — | Currently disabled; 1,089/1,090 rows are stale |
| ❌ | **Log/emit a metric** for every Places request (endpoint, listing, count) | — | No outbound-call observability today |

### External (Google Cloud console)
| Status | Action | Owner | Notes |
|---|---|---|---|
| ✅ | Disable the API (containment) | — | Done; see above |
| ❌ | Restrict the API key (API scope + HTTP referrer / IP) | — | Before re-enabling |
| ❌ | Set a **daily quota cap** on NearbySearch | — | Before re-enabling |
| ❌ | Set a **budget alert / threshold** | — | Before re-enabling |

---

## 15. Remaining Unknowns (cannot currently be proven)

- **The exact tests and run-count that produced the 1,356 generations on 07-05.** The POI code logs no HTTP, and the 273 `md5`-style bulk fixture keys from the 22:51–23:13 burst could not be pinned to a specific current test. *Cannot be proven from available evidence.*
- **The precise per-run July-5 request count** — not reproducible offline because the safe backstop blocks the external dependencies (Bridge OData, Geocoding) the generating tests need to proceed. *Provable only by (a) re-running the exact 07-05 tests with faked externals + a counting client — a code change — or (b) Google Cloud per-minute metrics overlaid on the 7 burst windows.*
- **Billable vs `REQUEST_DENIED` split.** Tests that set a fake key would return HTTP 200 `REQUEST_DENIED` (counts as a 2xx "request" in Metrics but is typically unbilled); tests using the leaked real key return billable `OK`/`ZERO_RESULTS`. Only Google's per-response metrics can split the 21,702 into billed vs unbilled.

---

## 16. Confidence Levels

| Conclusion | Confidence | Basis |
|---|---|---|
| Per generation = 16 NearbySearch; per lookup = 7 | **PROVEN** | Direct offline measurement + matches `run_stats` |
| Tests attempt live NearbySearch calls | **PROVEN** | 60 intercepted attempts in a current full run |
| Live key leaks into the test env | **PROVEN** | `.env.testing` omits it; 39-char system env var present |
| Root cause = test suite via sync + leaked key + raw Guzzle + unfaked observers | **HIGH** | All four conditions confirmed; only surviving hypothesis |
| Not production / import / cron / queue / browser | **HIGH** | Each affirmatively disproven (log/DB/architecture) |
| Caching absent/ineffective; no safeguards; no monitoring | **PROVEN** | Config + grep + DB state |
| Exact reconstruction of 21,702 in a single run | **LOW** | Not reproducible offline; documented why (§7, §15) |

---

*Prepared 2026-07-06. No production code was modified. Measurement artifacts (counting proxy, mock-client harness, DB probes) are in the session scratchpad and can be re-run on request.*
