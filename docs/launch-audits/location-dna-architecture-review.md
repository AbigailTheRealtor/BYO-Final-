# Location DNA — Architecture Review (pre–Phase 2)

**Date:** 2026-06-26
**Status:** Review only — no code changes. Verifies key decisions before committing to Phase 2.

Grounding note: all feasibility claims below were verified by reading the code, not inferred. The decisive fact: `property_location_pois` persists every input the ranking engine needs (`poi_name`, `poi_lat/lng`, `distance_miles`, `rating`, `user_ratings_total`, `types_json`), `LocationDnaRankingEngine` is pure computation (no I/O), `LocationDnaSummaryService` reads from those POI rows, and `LocationDnaLifestyleScoreService` reads from `summary_json`. The whole post-fetch chain is recomputable offline.

---

## 1. Rules versioning

### Mechanism
Store a **version hash** alongside the persisted artifacts and compare it against the *current* hash at enrichment time (or on a reconcile sweep). When they differ, the rows are stale and get recomputed.

The hash is derived from the inputs that actually affect output. Crucially, split it into **two independent versions**, because they have very different costs to satisfy:

| Version | Hashes over | A change here requires |
|---|---|---|
| `fetch_version` | `CATEGORIES` query definitions (google_type / keyword), category set, `CATEGORY_GROUPS`, search radius | A **fresh Google fetch** — new query params can surface candidates that were never stored |
| `scoring_version` | `LocationDnaRankingProfileService` profiles/weights, `CATEGORY_EXCLUSION_RULES`, beach/transit constants | **Recompute from cache only** — no API call |

Conflating them into one stamp is the trap: it would force a full 16-call refetch for a pure weight tweak that needs none.

### How existing records know they're stale
Store both versions on the parent `property_location_dna` row (e.g. `pois_fetch_version`, `pois_scoring_version`). POIs are already replaced as a set per category, so record-level granularity is sufficient; per-row stamping is optional and only buys partial-category precision we don't need.

Detection is a **string comparison**, not a scan:
- On normal re-enrichment (`ComputeLocationDna`): after the existing coords-match check, also compare stored vs current versions. `scoring_version` mismatch → recompute-from-cache. `fetch_version` mismatch (or coords changed) → refetch.
- For bulk propagation after a rules change: a `ldna:reconcile` command selects rows `WHERE pois_scoring_version <> :current` and recomputes them. Cheap to find, cheap to fix.

### Does it require API calls?
- **Scoring/exclusion-rule changes → no.** Recompute from the stored POIs. This is the common case (tuning weights, adding an exclusion).
- **Query/category changes → yes.** Only because candidates beyond the stored top-10 were never persisted. This is rarer and intentional.

This is the single most valuable Phase 2 mechanism: it turns "re-tune ranking" from an expensive, rate-limited, full-catalog refetch into a near-free CPU pass.

---

## 2. Refresh pipeline — recompute from cache

### Can rankings be recomputed without Google? **Yes — confirmed.**
A recompute path would, per listing, entirely in DB + CPU:
1. Load stored `property_location_pois` rows grouped by category.
2. Map each row to the engine's candidate shape (flat `poi_lat/lng` → `geometry.location`, `types_json` → `types`, etc. — a trivial adapter).
3. Re-apply the *current* `passesExclusionFilter()` to the stored set (drops any now-excluded rows).
4. Re-run `LocationDnaRankingEngine::rankCandidates()` → new scores + rank order.
5. Re-persist scores/rank; rebuild `summary_json` (`LocationDnaSummaryService`) and `lifestyle_json` (`LocationDnaLifestyleScoreService`).

No `fetchRawCandidates()` call anywhere in that path.

### One honest caveat
Recompute operates on the **stored ≤10 candidates per category**. It re-orders and re-filters within that set but cannot resurrect an 11th candidate Google originally returned, nor change query params. Since the product displays **top 3 of 10**, this is more than sufficient for weight/exclusion tuning. Only query/category redesign needs a true refetch (the `fetch_version` path).

### Cost savings estimate
A full fetch is **16 Google Places Nearby Search calls per listing** (19 categories − 3 shared via `CATEGORY_GROUPS`). At Google's Nearby Search rate (~$0.032/call; treat as ~$0.03–0.05):

- **Per listing, per re-tune:** ~16 calls ≈ **$0.50–0.80 avoided**.
- **10,000-listing catalog, one ranking re-tune:** ~160,000 calls ≈ **$5,000–8,000 avoided**, plus hours of wall-clock and zero rate-limit exposure.
- **Strategic value > the dollars:** it makes ranking iteration *cheap and reversible*, so weights can be tuned and A/B'd frequently instead of being frozen by refetch cost. Today, re-tuning effectively requires `ldna:refresh-all` (delete-all + full refetch) — the most expensive possible path.

Recommended surface: a `--from-cache` mode on the refresh command (or a dedicated `ldna:rerank-all`) gated on `scoring_version` mismatch.

---

## 3. Unified Location DNA engine

### Current state
Two POI subsystems with divergent taxonomies (property pipeline: 19+1 categories, full exclusion + ranking, persisted; buyer/tenant geometry lookup: 7 categories, **no exclusions**, distance-only sort, not persisted), 4-role symmetry (seller/buyer/landlord/tenant), and ≥5 consumers (Ask AI, Property DNA/Intelligence, Stellar, agent panel, future match scores).

### Recommended architecture
One **canonical core** with thin adapters and a single read model:

```
                ┌─────────────── Canonical Location DNA Core ───────────────┐
  Point input → │  categories + exclusions + LocationDnaRankingEngine +     │ → ranked POIs
  (property)    │  summary + lifestyle + narrative  (pure, shared defs)     │   + scores
  Area input  → │                                                           │   + summary
  (buyer/tenant │                                                           │   + lifestyle
   geometry)    └───────────────────────────────────────────────────────────┘
                                      │
                         Single read model / Presenter facade
                                      │
        ┌──────────┬──────────┬───────────────┬───────────────┬──────────────┐
     Stellar    Agent panel  Ask AI      Property DNA      Match scores (new)
```

- **Single taxonomy + exclusion set + ranking engine** — one place to fix a category or rule, not two.
- **Two entry adapters over the same core:** *point mode* (a property's lat/lng — today's pipeline) and *area mode* (a buyer/tenant search geometry → representative point(s) → same core).
- **One read model.** `LocationDnaPresenter` already exists and is the natural seed; promote it to the single public API every consumer reads. Match-score integration becomes a *new consumer of the read model*, never a new pipeline.
- Retire `GooglePlacesPoiAdapter` / `PoiDistanceLookupService`'s 7-category path; fold its use cases into the core.

### Migration path (incremental, flag-gated)
1. **Extract shared defs** — lift `CATEGORIES`, `CATEGORY_EXCLUSION_RULES`, and ranking profiles into a shared module (they're already isolated constants/services). No behavior change.
2. **Area adapter** — wrap the core for geometry inputs; **dual-run** against the legacy `PoiDistanceLookupService` and diff results in logs.
3. **Cut consumers over** to the read model one at a time (Ask AI and agent panel already read persisted data; buyer/tenant search is the real move).
4. **Deprecate** the legacy adapter once diffs are clean.
5. **Add match-score integration** as a fresh consumer (Phase 5).

### Risks
- **Behavioral drift** for buyer/tenant results (richer taxonomy + exclusions will change which POIs surface) — mitigate with dual-run diffing before cutover.
- **Area-mode accuracy** — a single centroid is a poor proxy for a large polygon; may need multi-point sampling, which raises call volume. Design decision, not a freebie.
- **4-role symmetry** — every consumer change is quadruplicated; budget for it.
- **Touching the working path** — the property pipeline is launch-critical and currently healthy; refactoring underneath it risks regressions.

### Estimated effort
~**4–6 engineer-weeks** + QA: core extraction (1–2 wk), area adapter + dual-run (1–2 wk), consumer cutover + legacy dedupe (1–2 wk). Match-score integration is separate.

### Before or after launch? **After.**
The property pipeline — the launch surface — already works. Unification is a maintainability/consistency win, not a launch blocker, and doing it pre-launch injects risk into the working path. **Launch on the property pipeline; schedule unification as a fast-follow.** The *only* unification worth doing pre-launch is bringing the buyer/tenant adapter's exclusions in line **iff** buyer/tenant search POIs are consumer-visible at launch (a cheap subset, not the full refactor).

---

## 4. Consumer experience — gap analysis

The rich experience lives in the **agent panel** (`partials/location-dna-agent-panel.blade.php`). The **consumer Stellar page** (`stellar/property/detail.blade.php` + `matchmaker-*` components) is a thinner, partly-stubbed reimplementation. The data already exists — this is overwhelmingly a *presentation* gap, not a data gap.

| Element | Agent panel | Consumer Stellar | Gap |
|---|---|---|---|
| Hero / area narrative | ✅ `location_narrative` rendered | ❌ not surfaced | **Missing** — data exists |
| Lifestyle score bars | ✅ scored category table | ⚠️ shows *match-score* bars (wrong data) | **Wrong source** |
| Top-3 per category | ✅ top 3 | ❌ single nearest only | **Missing** — data exists (10 stored) |
| Top-rated dining | ✅ | ❌ | **Missing** — data exists |
| Interactive map | ⚠️ map component | ❌ static iframe single-pin in MLS section | **Missing** (neither has POI-marker map) |
| Flood zone | n/a | ❌ dead "coming soon" placeholder | **Missing** — FEMA data exists in pipeline |
| Commute | n/a | ❌ dead "coming soon" | **Missing** |
| Appreciation | n/a | ❌ dead "coming soon" | **Missing** |
| "Why buyers love this area" | partial | ❌ (`matchmaker-why` is match reasons) | **Missing** |
| Target Market DNA tie-in | richer | ⚠️ flat archetype badges | **Shallow** |
| Agent marketing value | ✅ | ❌ (consumer surface) | By design |

**Highest-ROI consumer work:** extract shared partials from the agent panel and render them on Stellar (hero narrative, top-3/category, lifestyle bars, top-rated dining), and either fill or remove the 3 dead placeholder cards (flood data is already available). Pure presentation, low risk, biggest user-visible improvement.

---

## 5. Product roadmap (priority order)

Prioritized by launch value × user impact ÷ risk.

### P0 — Pre-launch (low risk, high value)
1. **Phase 1 bug fixes — done** (Ask AI keys, tile-cache store, marina/boat_ramp/hospital exclusions).
2. **Elevate the consumer Stellar page to use existing data** — reuse agent-panel partials for hero narrative, top-3 per category, lifestyle bars, top-rated dining; fill/remove the 3 dead placeholder cards (flood data exists). *Biggest user-facing gap, pure presentation, no new pipeline.*
3. **Fix the pre-existing `LocationDnaGeocodeServiceTest` failure** (red on `main`, 9-vs-8 contract key) — small hygiene item so the suite is trustworthy at launch.

### P1 — Pre-launch if time, else immediate fast-follow (medium risk)
4. **Rules-version stamp + recompute-from-cache** (Phase 2 core) — `scoring_version`/`fetch_version`, a `--from-cache` rerank path, and propagation on rule change. **Do this before any heavy ranking tuning** so iteration is cheap and safe.
5. **Engine cleanups** — remove the double-counted confidence param; add urgent-care and airport categories.

### P2 — Post-launch (higher risk / product decisions)
6. **Real interactive map** with POI markers/overlays on the consumer page.
7. **Unified Location DNA engine** (§3) — consolidate the two pipelines; ~4–6 wks.
8. **Match-score integration** (Buyer/Tenant) as a consumer of the unified read model — weights must sum to 100 (`config/match_scoring.php`), coordinate with the kill-switch/GA owner.
9. **Lifestyle refinements** — missing-data sentinel (don't penalize unenriched listings), optional absolute-distance scoring, promote the hidden outdoor score.

### Sequencing logic
Ship the working property pipeline and **make the data already in the database visible to consumers** (item 2) — that's the launch story. Land **rules-versioning** right after so ranking can be tuned without refetch cost. Defer **unification** and **match-score integration** — they carry the most behavioral risk and are not launch blockers.

### On Phase 2 specifically
The proposed Phase 2 (rules-version stamp + recompute-from-cache) is **technically validated and high-value — proceed as designed.** My one resequencing recommendation: if launch is near, do the **consumer-presentation work (P0 item 2) first** — it's the larger user-visible gap, lower risk, and has no dependency on rules-versioning. Rules-versioning is the right *next engineering* investment; consumer presentation is the right *next launch* investment. Which leads depends on the launch date.
