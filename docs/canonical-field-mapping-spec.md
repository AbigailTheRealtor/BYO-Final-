# Canonical Field Mapping Spec — Location Intelligence

**Status:** SKELETON / DRAFT. Contract definition for Phase 8. No code changes implied by this document.
**Date:** 2026-07-05
**Owner:** Location Intelligence Engine (Phase 8)
**Companion:** `docs/location-provider-capability-map-proposal.md` (provider registry + capability map)
**Related:** `docs/PHASE_8_PROVIDER_AGNOSTIC_LOCATION_INTELLIGENCE_RECOMMENDATION.md`, `docs/launch-audits/location-dna-architecture-review.md`, `docs/LOCATION_DNA_PHASE_A_GOVERNANCE_AND_DATA_SOURCE_PLAN.md`

> **Purpose.** Define the single canonical representation that *every* Location data provider normalizes into, and that *every* consumer (DNA engines, Universal Matching, Marketplace/Marketing Intelligence, Ask AI, search facets, Story Engine) reads from. After this contract exists, providers can be added, replaced, or combined by editing configuration and adding an adapter — never by touching an intelligence engine.
>
> **Non-goals.** This does not refactor the working Google property pipeline. It defines the target contract; migration is additive and phased (see §9).

---

## 1. The canonical field envelope

Every canonical Location field is stored and passed as an **envelope**, not a bare value. This is the atomic unit of the contract.

```
CanonicalField {
    value              mixed        // the normalized value (scalar | struct | geometry). null = unknown, NOT zero.
    source             string       // provider id that produced the winning value (e.g. "osm_overpass", "google_places", "fema", "census_tiger"). Matches ProviderCapabilityMap ids.
    confidence         float        // 0.0–1.0. See §2. null only for provider-free/derived-pending fields.
    provenance         Provenance   // how the value was obtained + raw reference + license. See §3.
    last_refreshed     timestamp    // when the winning value was last fetched/derived (UTC ISO-8601). See §4.
    human_corroborated bool         // true iff a human (agent/owner) confirmed or corrected this value. See §5.
}

Provenance {
    provider    string   // same vocabulary as `source`
    method      string   // "api" | "derived" | "manual" | "cache" | "merged"
    raw_ref     string?  // provider's native id (google place_id, osm node/way id, fema DFIRM id) — for audit + re-fetch
    license     string   // "google-tos" | "odbl" | "public-domain" | "geoapify-tos" | "attom-licensed" | ...
    contributors string[] // when method="merged": all providers that were considered (for contradiction audit)
}
```

**Rules**
- `value = null` means *unknown/unavailable*, never *zero/none*. Consumers must distinguish "no rating" from "rated 0."
- The envelope is persisted; `value` alone is never persisted without its provenance.
- On merge (§6), `source`/`confidence`/`last_refreshed` describe the **winning** contributor; `provenance.contributors` lists all considered.

---

## 2. Confidence model

`confidence ∈ [0.0, 1.0]`. Derived deterministically from source class + signal strength — never a free guess.

| Source class | Base confidence | Signal adjustments |
|---|---|---|
| Authoritative government (FEMA flood, Census TIGER boundaries/school districts) | 0.95 | −0.1 if bbox clipped / partial coverage |
| Rated commercial POI (Google Places) | 0.6–0.9 | scales with `user_ratings_total` (review-count confidence multiplier — mirrors `LocationDnaRankingEngine` today) |
| Structural open POI (OSM/Overpass — existence + geometry, no rating) | 0.5 | +0.1 if tagged with name+category+recent edit; quality signal absent → do **not** synthesize a rating |
| Derived / DNA-produced field (lifestyle score, neighborhood tag) | inherit | = min(confidence of inputs) × rule confidence |
| Manual / human-entered | 0.99 | `human_corroborated = true` |

**Critical grounding:** the current ranking engine (`LocationDnaRankingEngine.php:215–249`) computes `consumer_relevance_score` from `rating` + `user_ratings_total` and already handles the null-rating case (`"~ no rating available"` → neutral midpoint). OSM supplies neither, so an OSM-sourced POI carries a *structural* confidence and a `value` with `rating = null`. The engine must continue to treat missing quality signal as a first-class state, not a defect. **Quality signal is an optional per-POI overlay, never a hard dependency.**

---

## 3. Provenance model

`provenance.raw_ref` is mandatory for any API-sourced value so the exact record can be re-fetched or audited. Licensing (`provenance.license`) drives cache/redistribution policy (§7, §8). `contributors` enables contradiction detection (§6).

---

## 4. Freshness model

`last_refreshed` + a TTL **by field class** governs refetch. TTLs live in `config/location_dna.php` / capability map, not hardcoded.

| Field class | Suggested TTL | Rationale |
|---|---|---|
| Government hazard/boundary (flood, school district, city/zip/county polygons) | 180–365 d | Changes rarely; authoritative |
| POI existence/geometry | 30–90 d | Businesses open/close on a weeks-to-months lag (matches current 7-day tile TTL for raw candidates) |
| POI quality (rating/reviews) | 7–30 d | Volatile |
| Derived/DNA | recompute on `scoring_version` change (no TTL — cache-driven) |

Freshness-gated refetch targets **only stale fields**, not whole listings.

---

## 5. `human_corroborated`

`true` when an agent/owner has confirmed or overridden a value (e.g. corrected school district, confirmed waterfront). A corroborated field **wins precedence over any provider** (§6) and is exempt from TTL refetch until explicitly re-opened. Fair-Housing note: human overrides are still bound by the objective-facts rule (§8).

---

## 6. Precedence & contradiction detection

When ≥2 providers supply the same canonical field:

**Precedence order (highest wins):**
1. `human_corroborated = true`
2. Authoritative government source (for the fields it owns — FEMA for flood, Census for districts/boundaries)
3. Capability-map `role: base` provider for the category (§ capability map)
4. `role: overlay` provider (contributes *attributes*, e.g. Google rating onto an OSM POI — merge, don't replace)
5. `role: fallback` provider
6. Highest `confidence` as tiebreak; newer `last_refreshed` as final tiebreak

**Merge, don't just replace:** the common OSM+Google case is *attribute merge* — OSM provides `value.geometry`/`name`/`category` (base), Google provides `value.rating`/`review_count` (overlay). The merged POI carries `source="merged"`, `provenance.contributors=["osm_overpass","google_places"]`.

**Contradiction detection (the v2.1-flagged gap):** when two providers disagree beyond a per-field tolerance (e.g. school district name mismatch; POI displaced >X m; flood zone designation conflict), record a `contradiction` entry (loser + winner + delta) to the DNA audit table (`property_location_dna_audits` already exists) and surface it to internal review. Precedence still resolves *which wins*; detection ensures disagreement is *visible*, not silent.

---

## 7. Cache invalidation contract

Reuses the tile-cache + version-hash model from `location-dna-architecture-review.md §1–2`. **Not yet built on the Location tables** (`fetch_version`/`scoring_version` currently exist only on `bid_score_snapshots` / `dna_marketing_outputs`).

- `fetch_version` MUST include **provider identity / capability-map hash**. Switching or recombining providers changes the fetch version → stale tiles invalidate. *(This is the key addition the provider-agnostic layer requires.)*
- `scoring_version` unchanged — pure recompute-from-cache for weight/rule tuning.
- Cache the **canonical merged** result for consumers; retain raw per-provider provenance so merges can be recomputed when precedence rules change (parallels the scoring-version recompute path).
- Respect `provenance.license` cache policy (§8): some providers' raw payloads may be ephemeral-only.

---

## 8. Licensing & Fair Housing guardrails

**Licensing** (encoded per provider in the capability map, enforced here):
- `google-tos` — restricted caching/persistence of Place content; treat as **ephemeral quality overlay**; persist only `place_id` + derived confidence, not full payloads. *(Verify current Google Maps Platform ToS against the existing tile cache before nationwide scale.)*
- `odbl` (OSM) — freely cacheable/redistributable **with attribution**; the preferred base for the cacheable bulk.
- `attom-licensed` / `public-records` — carry redistribution + PII constraints; may not flow to consumer-facing Stories without a license check.
- Each field's `cache_policy` (`cacheable | ephemeral | attribution-required`) derives from `provenance.license`.

**Fair Housing:**
- Canonical Location fields are **objective, verifiable facts only** (geography, hazards, amenities, distances, boundaries, market characteristics).
- **No provider or merge step may synthesize protected-characteristic or demographic-inference signals.** Demographic feeds (if ever added) may not populate consumer-facing fields; no PII persisted (roadmap Phase 8 "do-not-do").
- Enforced downstream by Phase 14.5 narrative guardrails; this spec forbids such fields from existing in the canonical model in the first place.

---

## 9. Canonical field catalog (skeleton — to be completed in Phase 8)

For each field: canonical key · value type · contributing providers (ordered, role) · precedence · confidence source · freshness class · cache/license policy · consumers. **Fill during Phase 8 spec stage.**

### 9.1 Geo identity (from geocode)
| Canonical key | Type | Providers (role) | TODO |
|---|---|---|---|
| `geo.address` / `geo.lat` / `geo.lng` | string / float / float | Google Geocoding (base, established) → OSM Nominatim (fallback) | precedence, confidence |
| `geo.city` / `geo.county` / `geo.state` / `geo.zip` | string | same | — |

*Current DB source:* `property_location_dna.geocoded_lat/lng`, `geocode_source`, `source_city/county/state/zip`.

### 9.2 POI proximity (per category)
Categories today: schools, parks, shopping, hospitals, gyms, airports, downtown (+ full property-pipeline set: dining, grocery, marinas, boat ramps, transit, etc.).

| Canonical key | Type | Providers (role) | Notes |
|---|---|---|---|
| `poi.{category}.nearest` | POI struct | OSM Overpass (**base**: existence/geometry) + Google Places (**overlay**: rating/reviews) | merge per §6 |
| `poi.{category}.top_n` | POI struct[] | same | ranking reads persisted set |
| `poi.{category}.*.rating` | float? | Google Places (overlay only) | **null when OSM-only** |

*Current DB source:* `property_location_pois` (`poi_category`, `poi_name`, `poi_lat/lng`, `distance_miles`, `data_source`, + `rating`/`user_ratings_total`/ranking-score columns). **Missing today:** `confidence`, `provenance_json`, `last_refreshed`, `human_corroborated` (additive migration in Phase 8).

### 9.3 Hazard
| `hazard.flood_zone` | zone designation + rings | FEMA (authoritative, base) | bbox limits per `config/location_dna.php` |

*Current DB source:* FEMA adapter → `summary_json`. Interface: `FloodZoneAdapterInterface`.

### 9.4 Boundaries
| `boundary.school_district` | name + rings | Census TIGER (authoritative) → Stellar named-school field (precedence policy) |
| `boundary.city` / `.zip` / `.county` | rings | Census TIGER |

*Interfaces:* `SchoolDistrictAdapterInterface`, `BoundaryAdapterInterface`.

### 9.5 Commute
| `commute.{destination}.{mode}.time_minutes` | int? | OpenRouteService (base, real drive-time) → Google (premium) → stub |

*Current DB source:* `property_location_pois.travel_time_minutes`. Interface: `CommuteTimeAdapterInterface`.

### 9.6 Derived / DNA-produced (registered as a canonical *source* per roadmap V2.0)
| `derived.lifestyle.{score}` | 0–100 | Location DNA (derived) | recompute on `scoring_version` |
| `derived.neighborhood.tags[]` | tag[] | Location DNA (derived) | confidence = min(input confidences) |

---

## 10. Consumer contract

All consumers read the canonical layer, never a provider or a raw adapter payload:
- **Universal Matching (Phase 13):** reads canonical fields; provider swap invisible.
- **DNA engines (Phase 5–8.5):** consume canonical inputs incl. confidence; tolerate `value=null`.
- **Marketplace / Marketing Intelligence, Story Engine (9.5), Ask AI:** read canonical + provenance; expose confidence-appropriate framing; obey license/Fair-Housing flags.

---

## 11. Open questions (resolve during Phase 8 spec)
- [ ] Region-aware capability map for nationwide (OSM coverage varies by metro)?
- [ ] Materialize canonical fields into a query-optimized/vector store for 100k+ listings (v2.1 gap) — where does this spec's output land for retrieval?
- [ ] Exact contradiction tolerances per field.
- [ ] Confidence formula constants (shared with `LocationDnaRankingProfileService`?).
- [ ] Attribution surfacing for ODbL/OSM in consumer UI.
