# Phase 8 Addendum — Provider-Agnostic Location Intelligence Architecture

**Date:** 2026-07-05
**Status:** Architectural recommendation. Folds into Phase 8 (Location Intelligence Engine). No code changes proposed here.
**Question:** Should we (a) use OpenStreetMap / free-open datasets as the primary Location Intelligence source, (b) keep Google as premium enrichment/fallback, (c) normalize every provider into the Canonical Data Model, and (d) make providers addable/replaceable/combinable without touching the intelligence engine?

---

## Verdict up front

**Yes — with one important reframe.** The provider-agnostic *seam already exists in this codebase*; Phase 8 should not "decide whether to build it." Phase 8 should **formalize and harden the provider contract, and add the one layer that is genuinely missing — canonical merge + provenance — *before* a second provider is wired.** Retrofitting a canonical contract after five providers are live is the expensive path the v2.1 architecture review already warned about.

Two grounded corrections to the framing in the request, both of which *strengthen* the direction:

1. **"OSM primary, Google fallback" is the right split for *place existence and geometry*, but not for *quality signal*.** Today's ranking engine's core score (`consumer_relevance_score`) is computed from `rating` and `user_ratings_total` (`LocationDnaRankingEngine.php:60–61, 215–249`). OpenStreetMap/Overpass provides neither. The engine degrades gracefully (null rating → neutral midpoint, `"~ no rating available"`), but for OSM-sourced POIs ranking flattens to essentially distance-only. **The correct decomposition is per-attribute, not per-provider:** free sources own *what exists and where* (the bulk, cacheable, cheap); rated sources own *how good it is* (a thin, high-value overlay). Design the canonical model around attributes-with-provenance, and this falls out naturally.

2. **Provider choice is your *second* cost lever, not your first.** Your biggest operating-cost win is already built: the POI tile cache plus recompute-from-cache/rules-versioning (see `location-dna-architecture-review.md §1–2`) already avoids ~$5k–8k per ranking re-tune on a 10k-listing catalog and makes refetch rare. Once caching + freshness-gated refetch are on, the marginal $ of OSM-over-Google shrinks. Free-first routing still matters — decisively at nationwide scale — but "lowest sustainable operating cost" is won *primarily* by cache/recompute/refresh policy and *secondarily* by provider tiering. Don't oversell OSM as the cost story.

---

## What already exists (so we don't rebuild it)

| Layer | State today | Provider-agnostic? |
|---|---|---|
| Adapter interfaces | `PoiLookupAdapterInterface`, `FloodZoneAdapterInterface`, `CommuteTimeAdapterInterface`, `BoundaryAdapterInterface`, `SchoolDistrictAdapterInterface` (`app/Contracts/`) | ✅ Yes |
| Normalized output shape | POI adapter returns a fixed 7-key shape incl. explicit `'source'` provider id | ✅ Partial — has `source`, lacks `confidence`/`freshness` |
| Provider selection | DI binding in `AppServiceProvider` (`location_dna.poi.provider` → Google-or-Stub) | ✅ Swappable, ❌ single-provider only |
| Downstream engine | `LocationDnaRankingEngine` (pure), `…SummaryService`, `…LifestyleScoreService` read *persisted* `property_location_pois`, never a provider | ✅ Yes — already fully decoupled |
| Data-source survey | `LOCATION_DNA_PHASE_A_GOVERNANCE_AND_DATA_SOURCE_PLAN.md` benchmarked OSM/Nominatim/Overpass, Geoapify, Mapbox, OpenRouteService, Transitland vs Google w/ cost tables | ✅ Done |

**Conclusion:** The intelligence engine is *already* independent of any provider — that goal is largely met at the seam. The DNA/matching/marketing layers read persisted canonical rows, not adapters.

## What is missing (this is the real Phase 8 work)

1. **Single-provider binding.** `AppServiceProvider` binds exactly one POI adapter (`if google … else stub`). There is no way to *combine* providers (asks c/d) — no registry, no per-category routing, no tiering.
2. **No canonical Location field spec.** `docs/canonical-field-mapping-spec.md` is referenced repeatedly by the roadmap but **does not exist**. Providers normalize into an *ad-hoc* 7-key array, not a governed canonical model.
3. **No provenance/confidence/freshness per field.** The roadmap (Phase 8 V2.0/V2.2) requires every neighborhood field to carry `confidence` + `provenance` + `last_refreshed` + `human_corroborated`. The adapter shape carries `source` only.
4. **No merge / precedence / contradiction policy.** The v2.1 review flagged this as a real gap: when BYO + Stellar + ATTOM + RentCast + OSM disagree, precedence decides *which wins* but nothing *detects and surfaces* that they disagreed.
5. **No OSM/Geoapify adapters yet** — only Google + stubs are implemented.

---

## Evaluation across the requested dimensions

### Long-term operating cost
- **Free-first routing is the correct nationwide posture.** Google Nearby Search (~$32/1k) at nationwide POI volume is the cost cliff. OSM Overpass / Nominatim (self-host or public) and Geoapify free tier cover *existence + geometry* at ~zero marginal cost.
- **But caching dominates.** With tile cache + recompute-from-cache, most listings never refetch. Provider tiering saves on the *cold-fetch* population only.
- **Licensing is a hidden cost/risk that argues *for* the split.** Google's Places terms restrict caching/persisting most Place content (beyond `place_id`, with limits) — yet we already persist POI candidates in a tile cache. OSM data (ODbL, attribution) is freely cacheable/redistributable. **Moving the cacheable bulk to OSM and using Google only as an ephemeral quality overlay is both cheaper and more license-safe.** *(Action: verify current Google Maps Platform ToS caching clauses against the existing tile cache before nationwide scale — this is a latent compliance item regardless of this recommendation.)*

### Scalability
- Provider-agnostic ingestion is a prerequisite for nationwide: you cannot fund Google-only POI at national volume. Free-first + cache makes the unit economics work.
- **Nationwide amplifies data-quality asymmetry** (below): OSM coverage varies wildly by metro. The capability map should be **region-aware** eventually (dense metros: OSM excellent; rural: sparse — may need paid coverage or graceful "unknown").
- Ties to a v2.1 gap: canonical fields must eventually **materialize into a query-optimized/vector store** for 100k+ listings + matching. Provider-agnostic ingestion *feeds* that store; retrieval reads the store, never a provider — which is exactly the decoupling you want.

### Data quality
- **The decisive constraint.** OSM = strong for *what exists and where* (schools, parks, marinas, trails, water features, transit stops). Weak/absent for *ratings, review counts, hours, business liveness* — the very signals `LocationDnaRankingEngine` ranks on today.
- **Design implication:** the canonical model must represent each field as *value + provenance + confidence*, and the ranking engine must treat "quality signal absent" as a first-class state (it already half-does — see the `null` rating path). Then Google/Foursquare-style rated data becomes an *optional overlay per POI*, not a hard dependency.
- **Cross-source contradiction detection** (v2.1 gap) becomes essential once ≥2 providers feed the same canonical field.

### Performance
- Self-hosted Nominatim/Overpass removes per-call latency variance and rate-limit exposure vs. public Google. Straight-line distance stays local/CPU (already the case).
- Real drive-time (OpenRouteService / self-host) is the only place a provider call is unavoidable for commute — keep it async and cached (already the queued `ComputeLocationDna` model; do not break it).
- Merge adds a small CPU step at ingestion; negligible vs. network I/O and fully offline on recompute.

### Caching strategy
- Keep the existing tile cache + `fetch_version`/`scoring_version` model. **One required change: add provider identity to `fetch_version`** so switching or combining providers correctly invalidates stale tiles. Also stamp `source`/`freshness` per cached candidate so freshness-gated refetch can target only what's stale.
- Cache the *canonical merged* result, not raw per-provider payloads, for consumers — while retaining enough raw provenance to recompute merges when precedence rules change (mirrors the scoring-version recompute path).

### Candidate retrieval
- Retrieval (buyer/tenant search-area matching) must read the **canonical persisted layer**, never a provider — already true. A provider swap must be invisible to retrieval.
- The area-mode/point-mode unification already recommended in `location-dna-architecture-review.md §3` is the natural place to enforce "retrieval reads canonical only."

### Location DNA generation
- Already provider-independent (reads persisted POIs). The change is *input enrichment*, not generation: DNA gets richer/cheaper inputs with provenance, and must tolerate missing quality signal per POI. No change to the generator's contract if the canonical model carries confidence.

### Marketplace Intelligence / Marketing / Matching / Ask AI
- All are downstream consumers of canonical DNA + tags today. If Phase 8 lands the canonical field spec with provenance, these layers get the **"identical output regardless of source"** guarantee the roadmap already asserts (Principle 12 / V2.0 notes) — extended to *place*. This is exactly the user's stated end goal.

### Future nationwide expansion
- Provider-agnostic + free-first + region-aware capability map + cacheable-bulk-on-OSM is the only architecture that expands nationwide without a cost blow-up or a license problem. This is the strongest argument for doing the contract work now.

---

## Recommendation: fold four deliverables into Phase 8, *before runtime wiring*

Sequence matters. Do the **contract** in Phase 8's spec stage; wire providers only after. Order:

1. **`docs/canonical-field-mapping-spec.md` — the missing canonical Location model.** Define each Location field as `{ value, source, confidence, provenance, last_refreshed, human_corroborated }` (matches Phase 8 V2.0/V2.2 requirements). This is the contract every provider normalizes into and every engine reads from. *Blocking prerequisite for all wiring.*

2. **Provider contract hardening.** Extend the adapter interfaces' return shape from the current 7 keys to carry `confidence` + `freshness` (not just `source`). Backward-compatible for the existing Google adapter (it can populate confidence from `user_ratings_total`; OSM populates `source=osm`, `confidence=structural`, no rating).

3. **`LocationProviderRegistry` + `ProviderCapabilityMap`.** Replace the `if-google-else-stub` binding with a registry: per canonical category/attribute, an ordered list of `{provider, tier: free|premium, cost, region-scope}`. This is what makes providers *addable/replaceable/combinable* (asks c/d) with **zero engine change** — you're editing a map, not code paths. Bind it in `AppServiceProvider` the same way adapters are bound today.

4. **`CanonicalLocationMerger` (precedence + contradiction detection).** Given multiple providers' normalized outputs for the same place/field, apply the precedence policy, record the winning provenance, and **flag disagreements** (the v2.1 gap). This is the layer that turns "many providers" into "one canonical truth with an audit trail."

**Then, and only then, wire providers** in tiers, behind the existing DI seam:
- Keep **Google bound as-is** for the launch property pipeline — do **not** refactor the working path pre-launch (consistent with `location-dna-architecture-review.md §3` "unification is a fast-follow, not a launch blocker").
- Add **OSM Overpass/Nominatim** (self-hosted target) as the free-first existence/geometry provider.
- Add **Geoapify / Mapbox / OpenRouteService** per the Phase-A survey as the capability map dictates.
- Route each canonical attribute to its cheapest capable provider; use Google as the *quality overlay*, not the base.

### Cache/versioning changes required by the above
- Add provider identity to `fetch_version` (provider swap ⇒ invalidate).
- Stamp `source` + `freshness` per cached candidate; freshness-gate refetch.
- Cache the canonical merged result for consumers; retain raw provenance to re-merge when precedence changes (parallels scoring-version recompute).

---

## Guardrails / do-not-do
- **Do not touch `initializeLimitedService()` or the frozen legacy paths.** N/A here but standing.
- **Do not refactor the working Google property pipeline pre-launch.** Add the registry/merger *around* it; cut Google over to being "one provider in the map" as a fast-follow.
- **Do not let provider choice introduce demographic inference.** Fair Housing constraint (v2.1 review's largest gap) applies to Location stories regardless of source — merge/provenance must never synthesize protected-characteristic signals.
- **Encode licensing in the registry.** Some providers (ATTOM/public records/Geoapify/Google) carry redistribution/PII/caching constraints. The capability map should carry a `cache_policy` / `redistribute` flag per provider so the cache and Story/Marketing layers respect it automatically.
- **Keep the pipeline async/queued** (`ComputeLocationDna`) — never block on a provider call.

---

## One-paragraph answer to "should this be in Phase 8?"

Yes. But the honest framing is: **the provider-agnostic engine is already real — the intelligence layers already read canonical persisted data, not providers.** What Phase 8 must add before wiring a second provider is the *canonical Location field spec* (currently referenced but missing), *provenance/confidence on the adapter contract*, a *provider registry + capability map* (to combine/replace providers by editing a map, not code), and a *canonical merger with contradiction detection*. Do those four as Phase 8's spec stage; keep Google as the launch provider; then wire OSM-first + premium-overlay behind the unchanged seam. That delivers your end goal — AI, DNA, matching, and intelligence completely independent of any single mapping/data provider — while protecting the working launch pipeline, and it correctly locates your cost win in caching-plus-free-first-routing rather than in a risky provider swap.
