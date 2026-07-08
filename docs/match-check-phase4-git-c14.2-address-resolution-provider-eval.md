# Match Check — Phase 4 · git-C14.2 · Address-Resolution Provider Evaluation & Decision

**Status:** Decision note — **docs-only. No code behavior changes with this commit.**
**Decision date:** 2026-07-08
**Owner sign-off:** Abigail (product owner)
**Relates to:** `app/Http/Controllers/MatchCheck/MatchCheckController.php` (git-C14.2 TODO on `lookup()`),
`docs/match-check-phase4-git-c14-scope.md`, `docs/match-check-phase4-git-c15-scope.md`.

> **Numbering:** git-C\<n\> convention. git-C14.2 == the *locality-preserving free-text address
> resolution* slice deferred by git-C14 (the C1 gap). This note **defers that slice to post-launch**
> and records the provider evaluation behind that decision. It is not part of the Matching V2 series
> and touches none of it.

---

## 0. TL;DR (the decision)

git-C14.2 — robust, **locality-preserving** free-text address resolution for Match Check — is
**deferred as a post-launch enhancement**. For launch we make **no change** to address handling,
geocoding, autocomplete, maps, POI logic, or Match Check lookup behavior.

Concretely, for now:

- **Do NOT** add Google Geocoding to the Match Check address path for C14.2.
- **Do NOT** use the public `nominatim.openstreetmap.org` service in production.
- **Do NOT** change Location DNA, address autocomplete, maps, POI logic, or Match Check lookup
  behavior.
- **Keep the current safe baseline:** free-text address lookup stays on the orchestrator's forgiving
  whole-string substring match, which yields an honest **NOT_FOUND** on a miss. We prefer NOT_FOUND
  over any confident **wrong-city SCORED** result, and over any new external-API cost/latency/uptime
  risk at launch.

This note exists so the C14.2 slice, when it is picked up, starts from a recorded evaluation instead
of re-deriving it.

---

## 1. What C14.2 actually needs (the problem, restated)

Today `MatchCheckController::lookup()` passes the whole typed address string to
`MatchCheckOrchestrator::analyzeByAddress(['address' => …])`, which does a forgiving substring match
through `BridgeListingLookupService`. This is **safe but lossy**: it misses real listings whose stored
address differs in its city/state/ZIP tail (the original **C1 gap**).

The fix C14.2 must deliver is **locality-preserving resolution**: parse/resolve the typed address into
structured components that **preserve city/state/ZIP**, so a street-level match can never silently
score a listing in the *wrong city*. A heuristic street-term parser was explored for C1 and
**deliberately reverted** — dropping city/state risked a confident wrong-city SCORED result, a worse
failure mode than the baseline NOT_FOUND.

Note the downstream seam already fits this shape: `BridgeListingLookupService` does **not** geocode; it
consumes a caller-supplied component whitelist
(`street_number, street_name, city, state, postal_code`, + freeform `address`) and needs **no lat/lng
and no county**. So C14.2 is fundamentally a *resolver* problem (typed string → structured, locality-
preserving parts), not a mapping or POI problem.

---

## 2. Provider evaluation (OpenStreetMap / Nominatim vs. Google vs. alternatives)

We assessed whether OpenStreetMap/Nominatim could serve C14.2 and, more broadly, replace existing
Google usage. Summary of the read-only feasibility pass:

| Capability | Where it lives today | OSM/Nominatim fit | Notes |
|---|---|---|---|
| **C14.2 address resolution** (typed string → structured parts) | *not built yet* | ✅ **Good** | Nominatim structured search + `addressdetails=1` returns house_number/road/city/county/state/postcode — maps 1:1 to the BLLS whitelist. ~70% US typed-address accuracy vs. ~90% Google; a miss degrades to the safe NOT_FOUND. |
| **Address geocoding** (address → lat/lng) | `LocationDnaGeocodeService` | ✅ Possible | OSM-replaceable, but `ldna:refresh-all` is a **bulk/systematic** pattern the public Nominatim policy forbids. |
| **POI / Nearby Search** | `LocationDnaPoiDistanceService`, `GooglePlacesPoiAdapter` | ❌ **Prohibited / poor** | Nominatim is not a nearby-POI engine and its policy explicitly forbids "downloading all POIs in an area." OSM also lacks the `rating`/`user_ratings_total` our POI `confidence` derives from. |
| **Address autocomplete** (~10 Livewire proxies + `byo-address-autocomplete` widget) | Google Places Autocomplete | ❌ **Prohibited** | Nominatim policy: "Auto-complete search … you must not implement such a service." Would need **Photon** or a commercial autocomplete. |
| **Maps** (drawing map, Location DNA maps, results map) | Google Maps JS | ❌ Out of scope | Nominatim is geocoding-only. Maps would need **Leaflet / MapLibre GL + OSM/vendor tiles** — a separate workstream. |

### 2.1 Public Nominatim usage-policy constraints (why not in production)

The public `nominatim.openstreetmap.org` service ([usage policy](https://operations.osmfoundation.org/policies/nominatim/))
requires, at minimum: **max 1 request/second, single thread**; **results must be cached**; a valid
identifying **User-Agent** (stock library UAs are rejected); visible **attribution**; and it **forbids
autocomplete, systematic/bulk queries, and POI-area downloads**. It offers **no uptime or performance
guarantee** and discourages "serious business usage." That combination makes it unsuitable for a
production real-estate app — several of our existing patterns (autocomplete, bulk Location DNA
geocoding, POI-in-area) would violate the policy outright, and the SLA gap is unacceptable for a
consumer-facing lookup.

---

## 3. Future options (for the post-launch slice — not a commitment)

When C14.2 is scheduled, evaluate these paths. None is adopted by this note.

1. **Paid OSM-backed geocoder** — e.g. **LocationIQ** (free ~5k/day, then ~$49/mo) or **Geoapify**
   (free ~3k/day, then ~$59/mo). Same OSM data quality as Nominatim but with an SLA, higher rate
   limits, and no policy-violation risk. Would still require our own caching, a custom User-Agent, and
   attribution. Slots into the existing `LocationDnaGeocodeService` cache seam. **Lowest-effort C14.2
   path.**
2. **Google Geocoding** for the C14.2 path only — best US accuracy, reuses the existing key/seam, but
   adds per-lookup API cost and keeps us on Google. Explicitly **not** chosen for now per this
   decision.
3. **Local parser (no external call)** — e.g. **libpostal** — resolves typed strings to structured
   parts with no vendor/uptime/cost dependency. Viable if the goal is purely "preserve the locality
   tail" rather than validate against a global gazetteer.
4. **Larger self-hosted OSM stack** — **Nominatim** (geocoding) + **Photon** (autocomplete) +
   **Overpass**/commercial (POI) + **Leaflet/MapLibre** (maps). This is the only path that also
   displaces Google *autocomplete, POI, and maps*, i.e. the real cost-reduction migration. It is a
   **project, not a launch swap** — ~$200–500/mo infra + ops, its own testing and rollback, and a
   POI data-model gap (no OSM ratings) to solve.

**Guiding principle for whichever path is chosen:** preserve the current safety posture — an honest
**NOT_FOUND** is always preferable to a wrong-city SCORED result or to new, unbudgeted API-cost /
latency / uptime risk.

---

## 4. Scope boundary of this decision (what does NOT change)

- No new provider, key, dependency, or config is added.
- `MatchCheckController::lookup()` address handling is unchanged — still whole-string substring match
  → NOT_FOUND on miss.
- `LocationDnaGeocodeService`, `LocationDnaPoiDistanceService`, `GooglePlacesPoiAdapter`, the
  autocomplete Livewire proxies, `byo-address-autocomplete`, and all Google Maps JS embeds are
  untouched.
- The only code-adjacent change accompanying this note is a **comment/wording update** to the existing
  git-C14.2 TODO and controller docblock, so they read as a *deliberate post-launch deferral* pointing
  at this note — no behavior change.

---

## 5. Sources

- [Nominatim Usage Policy](https://operations.osmfoundation.org/policies/nominatim/) — rate limit,
  mandatory caching, User-Agent, attribution, autocomplete / systematic-query / POI-download
  prohibitions, self-host guidance.
- [Nominatim Search API — structured queries & address output](https://nominatim.org/release-docs/latest/api/Search/).
- [Geoapify: OpenStreetMap geocoding overview](https://www.geoapify.com/openstreetmap-geocoding/) and
  [Nominatim accuracy notes](https://www.geoapify.com/nominatim-geocoder/).
- [Geocoding API pricing / free-tier comparison](https://www.bitoff.org/geocoding-apis-comparison/) ·
  [OpenCage: alternatives to self-hosting Nominatim](https://opencagedata.com/alternatives/self-hosting-nominatim).
