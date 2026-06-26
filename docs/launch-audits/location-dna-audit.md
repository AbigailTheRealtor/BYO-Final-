# Location DNA — Implementation Audit

**Date:** 2026-06-25
**Scope:** Audit current Location DNA implementation against intended product vision. No refactors performed — findings + phased fix plan only.

---

## Executive summary

Location DNA is a large, mostly-working subsystem: 34 services, 18 migrations, ~40 test files, a queued pipeline, a quality ranking engine, and a rich agent-facing UI panel. The core ranking and exclusion logic is **better than expected** and passes the headline correctness test (a 4.8★/3000-review place reliably outranks a 5.0★/4-review place).

However the audit found **three structural problems** that undermine the premium vision:

1. **Two parallel, divergent POI subsystems.** The persisted "property pipeline" (`LocationDnaPoiDistanceService` → `property_location_pois`, 19+1 categories, full exclusion + ranking) and a second "buyer/tenant geometry lookup" (`PoiDistanceLookupService` → `GooglePlacesPoiAdapter`, 7 categories, **zero exclusion logic**, distance-only sort). They share no taxonomy and behave differently. Most confusion in this audit traced back to conflating them.
2. **Refresh does not propagate ranking-rule changes.** Only `ldna:refresh-all` (delete-all-and-rebuild) re-applies new ranking weights. Normal re-enrichment re-checks **rank-1 only** for exclusions and never re-ranks cached categories. There is no rules-version stamp.
3. **The premium consumer UI is the weak surface.** The rich experience (narrative, lifestyle bars, top-3 POIs/category, top-rated dining, map) is fully built — but only on the **agent/seller/landlord panel**. The consumer Stellar detail page reimplements a thinner version, has 3 dead "coming soon" placeholder cards, no real interactive map, and no area narrative.

Plus two **confirmed data-flow bugs** that silently zero out shipped features (Ask AI lifestyle context; tile cache disabled/ineffective in production).

**Recommendation:** Do not rewrite. Land the high-value bug fixes first (Phase 1), unify the two POI systems and fix refresh propagation (Phase 2), then elevate the consumer UI to parity with the agent panel (Phase 3).

---

## 1. Data pipeline & cache

### Entrypoint chain
Listing save (observers `PropertyAuctionDnaObserver.php:24`, `LandlordAuctionDnaObserver.php:24`), offer-listing Livewire saves, Bridge MLS import (`LazyBridgeImportService.php:150`), agent controller, and CLI (`location-dna:generate`) → `ComputeLocationDna` job (`ComputeLocationDna.php:30-32`) → `LocationDnaPipelineRunner::run()` (`:65-133`) which sequences: geocode → POI distance → summary → lifestyle.

> **⚠️ Correction (re-verified):** the Bridge entrypoint above is **dispatched but non-functional** — see the 🔴 `'bridge'` bug below. Bridge MLS imports currently produce **no** Location DNA.

**Canonical output tables:**
- `property_location_dna` — one row per `(listing_type, listing_id)`; geocode, `summary_json`, `lifestyle_json`, `generated_at`.
- `property_location_pois` — up to 10 ranked rows per category; `rank`, `ranking_score`, sub-scores, `ranking_reasons_json`, `source_lat/lng` (cache key).
- `location_dna_poi_run_stats` — cost telemetry. `property_location_dna_audits` — append-only audit.

### Bugs / gaps
- **🔴 `'bridge'` listing type silently produces no Location DNA.** `LazyBridgeImportService.php:150` dispatches `ComputeLocationDna::dispatch('bridge', id)`, but `LocationDnaPipelineRunner::resolveAddressData()` (`:159-178`) has **no `'bridge'` branch** — it handles only `seller`/`landlord`/`seller_agent`/`landlord_agent` and falls through to `return ['address'=>'','city'=>'','state'=>'']` (`:177`). Empty address → geocode `skipped` → entire pipeline `skipped`. **Every Bridge MLS import gets zero Location DNA.** *(Re-verified by grep + Read; corrects the entrypoint note above and the §1 CLI line, which implied the pipeline supports `bridge`.)*
- **🔴 No rules-version stamp → ranking-rule changes don't propagate.** Coords-unchanged re-enrichment (`LocationDnaPoiDistanceService.php:402-472`) re-checks **rank-1 only** against current exclusion rules; if rank-1 passes, ranks 2–10 are returned as `'cached'` untouched (`:452-465`), and the `LocationDnaRankingEngine` is **never re-invoked**. New ranking weights never re-order persisted rows. New exclusion rules never remove a stale rank-2..10 candidate. Only `ldna:refresh-all` (delete-all, `LdnaRefreshAll.php:75-78`) propagates ranking changes.
- **🟠 Tile cache uses the `array` store** (`LocationDnaPoiTileCache.php:94,111`) → in-process only. Queued jobs run one listing per process, so there is **zero cross-listing reuse in production**. The documented v3 cost optimization is effectively dead.
- **🟠 Tile cache disabled by default** (`tile_precision => null`, `config/location_dna.php:159`) → `categories_from_tile_cache` always 0 out of the box.
- **🟡 `PoiDistanceLookupService` caches adapter errors for full TTL** (`:104`) → a transient Google outage poisons a geometry's results for 24h.
- **🟡 Category query-config changes (`google_type`/`keyword`) don't trigger refetch** on coords-match path (keyed by category name, not params).
- **🟡 `ldna:refresh-all` doesn't flush the tile cache** — benign today (array store), becomes a staleness source if the store is fixed without adding a flush.
- **🟡 CLI/runner type mismatch:** `location-dna:generate` rejects `seller_agent`/`landlord_agent` (`GenerateLocationDna.php:23`) though the pipeline supports them (it cannot target the `*_agent` offer-listing types that are the dominant trigger). `bridge` is rejected by both the CLI **and** the runner (see the 🔴 bridge bug).

---

## 2. POI categories & 5. Exclusion rules

**Property pipeline** (`LocationDnaPoiDistanceService::CATEGORIES`, `:113-139`): 19 fetched + 1 derived (`top_rated_dining`). Cap = **hard-coded 10** (`MAX_CANDIDATES_PER_CATEGORY`, `:58`); the config `category_result_limit=5` is **ignored** here and only used by the other system.

| Target category | Status | Notes |
|---|---|---|
| Beaches, Beach access, Parks, Waterfront parks, Dog parks, Boat ramps, Marinas | ✅ Present | |
| Grocery, Pharmacy, Shopping, Restaurants, Coffee, Hospitals, Golf, Gyms, Schools, Transit | ✅ Present | |
| Top Rated Dining | ✅ Present (derived, ≥10 reviews, capped 3) | |
| **Urgent care** | ❌ **Missing** | only an allowlist token inside `hospital`; no dedicated search |
| **Airports / major roads** | ❌ **Missing** | `airport` exists only in the buyer/tenant adapter; "major roads" unimplemented anywhere |
| _Extra not in target_ | ⚠️ | `gas_station`, `fitness_center` (dup of gym), `downtown` (adapter-only) |

### Exclusion rules (`CATEGORY_EXCLUSION_RULES`, `:178-278`; only 8 categories have any rule)

| Intended exclusion | Status |
|---|---|
| Gas stations ⊄ grocery | ✅ Robust (type + name fallback) |
| Animal hospitals ⊄ **pharmacy** | ✅ Present + regression-tested |
| Animal hospitals ⊄ **hospital** | ❌ **Missing** — hospital regex only targets med-spas; vets only soft-penalized |
| Mini/adventure golf ⊄ golf | ✅ Present |
| Resorts/hotels ⊄ beaches | ✅ Present (lodging type + name) |
| Boat dealers ⊄ **marinas** | ❌ **Missing** — `marina` has no exclusion rule at all |
| General irrelevant-POI deny | ⚠️ Partial — no default deny; 11 categories fall through to `return true` unfiltered |

**Gaps:** urgent care + airports/major-roads unimplemented; marinas/boat ramps completely unfiltered; animal hospitals not excluded from `hospital`; buyer/tenant adapter path has **zero** exclusion logic; dead code branch `exclude_if_types_include_and_lacks` (`:804-814`); config `category_result_limit` silently ignored by primary engine.

---

## 3. Ranking formula & 4. Top-3 logic

**Formula** (`LocationDnaRankingEngine.php:99-104`), normalized weighted sum of four 0–100 sub-scores per category:
```
ranking_score = (match·w_m + review_confidence·w_r + consumer_relevance·w_c + distance·w_d) / Σw
```
- `category_match_score` — base 50, +25/preferred type (cap +40), −30/penalized (cap −50).
- `review_confidence_score` — **logarithmic in review count** (no rating, no Bayesian prior).
- `consumer_relevance_score` — `0.60·(rating·confidenceMult) + 0.40·rating`, `confidenceMult = min(reviews/min_reviews, 1)`.
- `distance_score` — `100 − (dist/maxDistInSet)·80`, clamped [20,100]. **Relative to the farthest candidate in the set, not absolute miles.**

| Factor | Present? |
|---|---|
| Distance | ✅ (relative) | Google rating | ✅ | Review count | ✅ (two channels) |
| Review confidence/shrinkage | ⚠️ count-based only, **no Bayesian/Wilson** | Category relevance / type match | ✅ |
| Exclusion → ranking | runs before ranking | Consumer relevance | ✅ | Composite | ✅ |

### Headline test — 4.8★/3000 vs 5.0★/4: **✅ PASS**
Worked example (restaurant profile, similar distance): 4.8★/3000 → **82.65**; 5.0★/4 → **40.91**. Wins by ~42 pts because the low-review place is penalized on both the review-confidence channel and the relevance confidence-multiplier. Repo test `high_confidence_restaurant_outranks_low_review_five_star_outlier` (4.8★/300 > 5.0★/19) asserts the same direction.

### Top-3 logic
- **Rank 1 = best-ranked, not nearest** ✅ (`usort` by `ranking_score` desc, `:982-985`; comment `:967-968`).
- Stored with `rank` + score columns ✅.
- **Fallback <3 POIs:** no padding/synthesis; stores whatever valid count exists; zero results → single `not_found` placeholder.
- **N mismatch:** main path stores **10**, `top_rated_dining` **3**, separate service **5**. "Top 3" is enforced in this layer only for top-rated dining — other categories rely on an unverified display-layer cap.

**Bugs:** no Bayesian shrinkage (robust for shipped weights, latent risk for future low-`review_weight` profiles); distance is relative not absolute (nearest always = 100 even at 25 mi); **dead/double-counted confidence** — `computeConsumerRelevanceScore` receives the log `$confidenceScore` but never uses it (`:218-253`), recomputing its own; three uncoordinated limits, none = 3 for general categories.

---

## 6. Lifestyle scores & 7. Narrative engine

Five persisted scores (`LocationDnaLifestyleScoreService.php:239-288`), each a weighted average of a shared distance→points ladder (<0.5mi=100 … ≥10mi=10, null=0):

| Score | Status | Notes |
|---|---|---|
| Coastal | ✅ | beach(1.0)+marina(0.5) — **beach-only caps at 67**, below the 70 "Beach Lovers" threshold |
| Walkability | ✅ | grocery/restaurant/coffee/pharmacy |
| Convenience | ✅ | grocery/pharmacy/coffee |
| Family-friendly | ✅ | park/dog_park/waterfront_park/grocery |
| Commuter | ✅ | transit+gas only — **no road/highway POI**, biased to transit-served cores |
| Outdoor/recreation | ⚠️ **Not a real score** | computed inline in `deriveCategories()` (`:363-368`), never persisted/surfaced |

**Bugs:**
- **🔴 Missing data is indistinguishable from "amenity far away."** A null distance scores 0 but still contributes full weight to the denominator (`weightedAvg`, `:316-331`). A home with grocery at 0.3mi but no restaurant/coffee/pharmacy data scores walkability = 33 — identical to a home where those genuinely don't exist. Incomplete enrichment is systematically penalized; no "insufficient data" sentinel.
- Beach-only coastal capped at 67 → "exceptional coastal"/"Beach Lovers" near-unreachable (test masks this by seeding both beach AND marina).
- "marinas and waterways" narrative branch is near-dead code (marina-only coastal maxes at 33, below the 40 gate).
- Coarse tiers can't discriminate within a neighborhood (0.1mi and 0.49mi both = 100).
- All thresholds/weights hardcoded as class `const`, not config.

**Narrative engine:** **100% static string-template concatenation** (`buildNarrative()`, `:411-488`) — governance block explicitly forbids AI. Phrases are fixed literals selected by score buckets; **never references an actual POI name, distance, city, or score number.** Two beachfront condos in different towns get byte-identical narratives if their buckets match. (A separate `LocationIntelligenceSummaryService` does emit POI-name-specific lines, but it's a different pipeline and a flat label list, not prose.)

### Confirmed surfacing bugs
- **🔴 Ask AI lifestyle context is always null.** `AskAiContextBuilderService.php:2105-2108` reads `['scores']`/`['categories']`/`['narrative']`, but the persisted payload (`LocationDnaLifestyleScoreService.php:200-207`) uses top-level scores, `lifestyle_categories`, and `location_narrative`. Only `version` aligns. **Verified by direct inspection.** Lifestyle scores/categories/narrative are perpetually null in every Ask AI answer.
- **🟡 Buyer add-bid view renders the version tag as a lifestyle signal** — `buyer_criteria/add-bid.blade.php:440-442` does `array_slice($lifestyle_json, 0, 6)`; first key is `version`, so UI shows `Version: LDNA_LIFESTYLE_V1` as a badge.

---

## 8. UI/UX vs premium vision

Two surfaces render Location DNA. The rich one is the **agent panel** (`partials/location-dna-agent-panel.blade.php`); the consumer **Stellar detail page** is the weaker reimplementation. Statuses below are for the consumer page.

| Element | Status |
|---|---|
| Hero / area summary | ❌ Missing — `location_narrative` exists but never surfaced on Stellar |
| Lifestyle cards/badges | ⚠️ Partial — archetype/personality badges only; lifestyle scores not shown |
| Interactive map | ⚠️ Partial — static Google Embed iframe, single pin, in MLS section; no POI markers, no map in matchmaker section |
| Category cards | ⚠️ Wrong data — `matchmaker-category-bars` shows **match-score** categories, not lifestyle scores |
| Top 3 per category | ❌ Missing on Stellar (`matchmaker-nearby:97` shows only the single nearest); ✅ present on agent panel |
| "Why buyers love this area" | ❌ Missing — `matchmaker-why` is match-criteria reasons, not area lifestyle |
| Target Market DNA tie-in | ✅ Basic — flat archetype badges |
| Agent marketing value | ❌ Missing on Stellar; ✅ on seller/landlord panel |

**Dead placeholders on Stellar:** `matchmaker-flood-zone` (hardcoded "being integrated", ignores its prop even though FEMA data exists), `matchmaker-commute` ("coming soon"), `matchmaker-appreciation` ("coming soon").

---

## 9. Integrations

| Consumer | Status | Evidence |
|---|---|---|
| Property DNA (generator) | Not wired by design — handled downstream | `PropertyDnaGenerator.php:461-463` |
| Property Intelligence Profile | ✅ Wired | `PropertyIntelligenceProfileService.php:130-218` persists location context |
| **Buyer Match Score** | ❌ **Not wired** | `BuyerMatchScorer::scoreLocation()` (`:99-176`) = geographic proximity only; no POI/walkability/flood/lifestyle |
| **Tenant Match Score** | ❌ **Not wired** | no tenant scorer; routes through `BuyerMatchScorer` |
| Target Market DNA / personality | ✅ Wired | `PropertyPersonalityService::extractLocationSignals()` (`:407-426`) |
| Ask AI answers | ⚠️ Wired but lifestyle keys null (see §7 bug) | `AskAiContextBuilderService:2086-2143` |
| **Agent AI extended knowledge** | ❌ **Wired but dead (status mismatch)** | `ExtendedKnowledgeLoader.php` |
| Seller/Landlord public views | ✅ Wired | full agent panel + map |

**Key gap:** Buyer and Tenant match scores ignore Location DNA entirely — the "location" category measures distance-to-criteria, not location quality.

**🔴 Agent AI never loads Location DNA (confirmed).** `ExtendedKnowledgeLoader::loadLocationDnaSummary()` queries `->where('geocode_status', 'success')`, but the canonical value written by `LocationDnaGeocodeService` is `'geocoded'` (`:115/236`). The query **never matches**, so the Location DNA summary is never surfaced to Agent AI extended knowledge. One-word fix (`'success'` → `'geocoded'`). *(Verified by grep: every other consumer and command uses `'geocoded'`; only this loader uses `'success'`.)*

---

## 10. Testing

Strong breadth (~25 LDNA test files). Well covered: exclusion rules (pharmacy/golf/transit/beach), the 4.8★-beats-5.0★ ordering, pipeline trigger/dispatch, geocode/flood/school/commute adapters, exclusion-rule cache refresh.

| Critical behavior | Coverage |
|---|---|
| Ranking ordering (4.8★ vs 5.0★) | ✅ Covered |
| Exclusion rules | ✅ Covered (4 categories) |
| Pipeline trigger / dispatch | ✅ Covered |
| Ranking composite formula (exact values) | ⚠️ Partial — only `>` deltas, never exact composite |
| **Ranking-rule refresh propagation** | ❌ **Not covered** — no test that a changed profile re-orders persisted rows (matches the §1 bug) |
| Top-N persistence (rank1=best not nearest; cap; <3 fallback) | ⚠️ Partial — fixtures seed 1 POI/category; never asserts multi-POI rank order or caps |
| Lifestyle score formulas | ⚠️ Partial — only all-null→0 pinned; walkability has no magnitude test |
| UI Top-3 rendered | ⚠️ Partial — fixtures seed 1 POI/category; "top 3 rendered" never asserted |

**Top missing tests:** (1) ranking-rule change re-orders persisted rows; (2) multi-POI category → persisted rank1 = highest score not nearest; (3) per-category cap (≤10 / ≤3); (4) sub-score columns persisted; (5) seed ≥4 POIs and assert exactly top-3 render in order; (6) exact composite + shrinkage-curve values; (7) exact lifestyle scores for representative distance vectors + walkability magnitude; (8) grocery-vs-gas-station + remaining categories in exclusion regression.

---

## Recommended implementation plan (safe phases)

### Phase 1 — High-value bug fixes (low risk, isolated)
1. **Fix Ask AI lifestyle key mismatch** — align `AskAiContextBuilderService:2105-2108` to the real payload keys (top-level scores, `lifestyle_categories`, `location_narrative`). Add a regression test. *(Confirmed bug, silently dead feature.)*
2. **Fix buyer add-bid version-badge leak** — drop `version` before the `array_slice` render (`add-bid.blade.php:440`).
3. **Missing-data sentinel in lifestyle scores** — distinguish "no data" from "far away" in `weightedAvg` (exclude null-distance fields from the denominator, or emit an `insufficient_data` flag). Add exact-value tests.
4. **Add missing exclusion rules** — `marina`/`boat_ramp` boat-dealer guard; exclude animal hospitals from `hospital`. Extend `CategoryExclusionRulesRegressionTest`.
5. **Stop caching adapter errors** in `PoiDistanceLookupService:104`.
6. **Fix Agent AI status filter** — `ExtendedKnowledgeLoader` `geocode_status` `'success'` → `'geocoded'`. Add a regression test asserting a geocoded listing's Location DNA loads into extended knowledge. *(Confirmed bug, silently dead integration.)*
7. **Add `'bridge'` support to `resolveAddressData()`** (`LocationDnaPipelineRunner.php:159`) so Bridge MLS imports geocode — or stop dispatching the job for `bridge` records in `LazyBridgeImportService:150`. Add a test. *(Confirmed bug — Bridge imports currently get no Location DNA.)*

### Phase 2 — Pipeline correctness (medium risk)
6. **Rules-version stamp** — add a `rules_version` (config-hash) column to `property_location_pois`; on re-enrichment, if the stamp differs, re-rank/re-filter the full category instead of rank-1-only. Test that ranking-rule changes propagate. *(Closes the §1 + §10 gap.)*
7. **Fix tile cache store** — move off the `array` store to a persistent store (redis/database) so cross-listing reuse actually works; have `ldna:refresh-all` flush it. Enable a sane default `tile_precision` (0.005 per the benchmark doc).
8. **Remove dead/double-counted confidence** in `LocationDnaRankingEngine` (either use the passed log score or delete the param). Pin the composite formula with an exact-value test.
9. **Decide absolute vs relative distance** — relative distance means the nearest always scores 100; consider an absolute-miles decay. (Product call — flag, don't silently change.)

### Phase 3 — Category completeness (medium risk)
10. Add **urgent care** and **airports** as first-class categories with profiles + exclusions. Decide on "major roads" (likely a different data source).
11. **Unify the two POI subsystems** — either retire `PoiDistanceLookupService`/`GooglePlacesPoiAdapter` or bring its taxonomy + exclusions in line with the property engine. (Largest item — scope carefully.)
12. Promote **outdoor/recreation** to a persisted, surfaced score.

### Phase 4 — Premium consumer UI (higher effort, low backend risk)
13. Reuse the agent panel's narrative, lifestyle bars, top-3-per-category, and top-rated-dining on the Stellar detail page (extract shared partials).
14. Replace the static iframe with a real interactive map (POI markers, overlays) in the matchmaker section.
15. Implement (or remove) the 3 dead placeholder cards — flood zone (FEMA data already exists), commute, appreciation.
16. Add a "Why buyers love this area" area-narrative block + UI tests asserting top-3 render in rank order.

### Phase 5 — Match-score integration (product decision required)
17. Feed Location DNA lifestyle/POI quality into Buyer/Tenant match scores (and stand up a dedicated tenant scorer). Coordinate weights with `config/match_scoring.php` (must sum to 100) and the GA/kill-switch owners.
