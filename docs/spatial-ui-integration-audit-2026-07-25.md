# Spatial UI Integration — Address & Map Repair · Pre-Implementation Audit

**Date:** 2026-07-25
**Branch audited:** `main` @ `10037715a` (clean except `.claude/settings.local.json`, `.replit`)
**Status:** AUDIT ONLY — no production code, config, migrations, or data changed by this document.
**Verdict:** **STOP — two architecture decisions are unresolved.** See §7.

---

## 0. Worktree / concurrency verification (requested first)

| Check | Result |
|---|---|
| Current branch | `main` |
| HEAD | `10037715a` (merge of PR #46) |
| Working tree | Clean apart from `.claude/settings.local.json`, `.replit` (both pre-existing, unrelated) |
| Rebase / merge / cherry-pick in progress | None |
| `.git/index.lock` | Absent |
| Other worktrees | 1 — `/home/runner/worktrees/hi-05a-r2e1-full-population-readiness` on branch `phase-2-batch-hi-05a-r2e1-full-population-readiness`, **also at `10037715a`, clean** |
| Concurrent session risk | **None detected.** The sibling worktree has no uncommitted changes and shares our HEAD. |
| Stashes | 4, all unrelated (tenant accessibility, ask-ai KB, baseline check) |

**This worktree is safe to work in.** No changes have been made.

---

## 1. Root cause of the broken map

**The Google Cloud project behind `GOOGLE_PLACES_API_KEY` does not have the required APIs enabled.** This is not an inference — it is in our own telemetry, from today.

**Browser evidence** (`window.gm_authFailure` → `MapsAuthFailureController`, `storage/logs/laravel-2026-07-25.log`):

```
[2026-07-25 18:35:25] google_maps_auth_failure {"page":"/offer-listing/seller",         "user_id":142}
[2026-07-25 18:50:46] google_maps_auth_failure {"page":"/offer-listing/buyer/edit/3471","user_id":142}
[2026-07-25 18:51:16] google_maps_auth_failure {"page":"/offer-listing/tenant/tenant",  "user_id":142}
[2026-07-25 18:51:46] google_maps_auth_failure {"page":"/hire/agent/auction/tenant",    "user_id":142}
```

**Server evidence** (`GoogleOutboundTelemetryMiddleware`):

```
[2026-07-25 18:44:26] outbound_google_request.credential_rejected
  {"endpoint":"/maps/api/geocode/json","http_status":200,"google_status":"REQUEST_DENIED",
   "google_error":"This API is not activated on your API project…"}
[2026-07-25 18:45:24] AgentLocationDnaController: inline Location DNA run did not succeed
  {"listing_type":"seller_agent","listing_id":14803,"geocode_status":"failed",
   "geocode_error":"Geocoding API returned no results. Status: REQUEST_DENIED"}
```

**The key string exists in `.env` and is well-formed. The APIs are not enabled on the project.** Google answers `HTTP 200` + `REQUEST_DENIED`, so nothing surfaces as an error to the user — the map simply never appears and the address box silently stays dumb.

### Why one credential breaks all eight flows

Every location surface in the product is built on `google.maps.*`:

| Capability | Implementation | State |
|---|---|---|
| Basemap render | `new google.maps.Map` | Dead |
| Street-address suggestions | `google.maps.places.Autocomplete` (**52 browser instantiations across 49 files**) | Dead |
| Address → coordinates | `google.maps.Geocoder` (browser) + `/maps/api/geocode/json` (server, 8 call sites) | Dead |
| Polygon / circle drawing | `google.maps.Polygon` / `Circle` / `Polyline` / `Marker` | Dead (needs the map object) |
| Radius search | `google.maps.Geocoder` → `google.maps.Circle` | Dead |
| Important Places pin | `google.maps.Geocoder` | Dead |
| City / county boundary shapes | Nominatim OSM → `google.maps.Data.Feature` | Data fetch survives, **render dead** |
| ZIP boundary shapes | Census TIGERweb → `google.maps.Data.Feature` | Data fetch survives, **render dead** |

The boundary data sources are already Google-free. Only the **renderer** and the **geocoder/autocomplete** are Google. That is the whole of the breakage.

### Secondary root cause (independent of the credential)

Even with a working key, `Create Seller listing` and `Create Landlord listing` would still behave as reported. Their Street Address input is:

```html
<input type="text" id="seller-offer-street-address" wire:model="address"
       placeholder="Enter street address (e.g., 123 Main Street)" required autocomplete="off">
```

`resources/views/livewire/offer-listing/offer-seller-tabs/commission-based/property-preferences.blade.php:385`

There is **no server-side validation that the value is an address at all.** Typing `43434` passes. The only thing that would have populated City/County/State/ZIP/lat/lng was the `place_changed` listener — a client-side convenience with no server-side counterpart and no "you must select a suggestion" enforcement. **Fixing the Google key would restore autofill but would not fix `43434`.**

---

## 2. Current map / geocoder providers in use

| Concern | Provider today | Approved target | Gap |
|---|---|---|---|
| Basemap tiles | Google Maps JS | **MapLibre GL** (per `GOOGLE-MAPS-PLATFORM-MIGRATION-INVENTORY.md` §3, "Map Rendering" phase) | **Tile source not chosen — see §7.2** |
| Address autocomplete | Google Places Autocomplete | **Photon** (roadmap §11) or the owned address corpus | **Not implemented; corpus empty — see §7.1** |
| Address → coordinates | Google Geocoding API | MLS coords → NAD → OpenAddresses → TIGER interpolation → Census Geocoder (inventory GMP-04, supersedes self-hosted Nominatim) | **Not implemented; `addresses` table has 0 rows** |
| City / county boundaries | `nominatim.openstreetmap.org` (direct from browser) | Owned PostGIS `boundaries` | Counties loaded (FL only); **cities/places not loaded** |
| ZIP boundaries | Census TIGERweb ZCTA2020 REST (browser) | Owned PostGIS `boundaries` | **ZCTA not loaded** |
| POI / category proximity | Google Places Nearby (`$32/1k`) — kill-switched off by default | Overture Places in PostGIS | **Loaded for FL (29,434 rows), zero app read path** |
| Travel time / isochrones | None (stub adapter) | Valhalla / OpenRouteService | Not implemented; `isochrone_cache` empty |

**Governing decision:** SIA-D25 (2026-07-09) — *"the platform is Google-free by design. Google is a legacy dependency to be removed, never an approved fallback. No credential is assumed to exist."*

**A note on Nominatim.** The city/county boundary lookup calls `nominatim.openstreetmap.org` **directly from every user's browser**, rate-limited client-side to 1 req/sec. This violates the OSM Foundation usage policy for an application workload and is already tracked for removal as GMP-A1. It should not be extended, and it must not become the geocoder.

---

## 3. Files and components duplicated across the eight flows

Duplication is **not uniform** — Buyer/Tenant is already well-shared; Seller/Landlord is half-shared.

### Scope B/C (Buyer + Tenant) — already shared, single implementation

| Artifact | LOC | Role |
|---|---:|---|
| `resources/views/partials/location-dna/map-input.blade.php` | 1,586 | **The entire Search Areas + Important Places widget.** Map, tag inputs, draw tools, radius, IP rows. |
| `resources/views/partials/location-dna/search-areas-bridge.blade.php` | 121 | JS → Livewire JSON bridge |
| `app/Http/Livewire/Concerns/HasSearchAreas.php` | 132 | Load / persist / discrete-mirror |
| `app/Http/Livewire/OfferListing/Concerns/HasImportantPlaces.php` | 77 | Important Places load/persist |
| `app/Services/Offers/ImportantPlacesService.php` | 152 | Pure normalizer + validator |
| `resources/views/components/location-dna-map.blade.php` | 810 | **Read-only** map for the 6 public `view.blade.php` pages |

Included by all four target flows plus four legacy criteria pages:

```
livewire/offer-listing/offer-buyer-tabs/commission-based/property-preferences.blade.php:142
livewire/offer-listing/offer-tenant-tabs/commission-based/property-details.blade.php:91
livewire/hire-buyer-agent/buyer-agent-auction-tabs/commission-based/property-preferences.blade.php:138
livewire/tenant-agent-auction-tabs/commission-based/property-details.blade.php:87
buyer_criteria/add.blade.php:439 · buyer_criteria/edit.blade.php:439
tenant_criteria/add.blade.php:427 · tenant_criteria/edit.blade.php:427
```

**Implication: Scope B and C are one file each, not four.** Replacing `map-input.blade.php`'s renderer fixes all eight pages at once. This is the single highest-leverage file in the project.

### Scope A (Seller + Landlord) — shared component exists, two of four flows adopted it

**Shared:** `resources/views/components/byo-address-autocomplete.blade.php` + `app/Http/Livewire/Concerns/HandlesGooglePlacesAddress.php` (`fillFromGooglePlaces()`).

| Flow | Uses shared component? | Evidence |
|---|---|---|
| Hire Seller agent | ✅ | `livewire/hire-seller-agent/…/property-preferences.blade.php:418` |
| Hire Landlord agent | ✅ | `livewire/hire-landlord-agent/…/property-preferences.blade.php:13` |
| Create Seller listing | ❌ **forked copy** | inline `<input>` + ~60 lines of duplicated Autocomplete JS at `offer-seller-listing.blade.php:3085`, `offer-seller-listing-edit.blade.php:2335` |
| Create Landlord listing | ❌ **forked copy** | same pattern at `offer-landlord-listing.blade.php:3525`, `offer-landlord-listing-edit.blade.php:2977` |

The component's own docblock records this: *"Create Offer Seller/Landlord may adopt it in a follow-up cleanup (documented; not a launch blocker)."* `fillFromGooglePlaces()` is consequently defined **five times** — once in the trait, and re-implemented in each of the four Create-listing components.

Server-side Google Autocomplete proxies additionally exist in **12 Livewire components** (Hire Seller/Buyer/Landlord/Tenant, Create Seller/Buyer/Landlord/Tenant, create + edit).

---

## 4. Database fields already available

### 4.1 Application database (`pgsql`, 162 tables)

| Need | Field(s) | Status |
|---|---|---|
| Street address (Seller/Buyer hire) | `seller_agent_auctions.address`, `buyer_agent_auctions.address` + `city_id`/`county_id`/`state_id` | Native columns |
| Street address (Landlord/Tenant hire) | EAV meta — `landlord_agent_auction_metas`, `tenant_agent_auction_metas` | Per documented schema asymmetry |
| Unit / Apt | `unit_address` (meta) | Present, separate from street ✅ |
| **Latitude / longitude** | `property_lat`, `property_lng`, `google_place_id` — **meta keys only** | ⚠️ **No listing table has a lat/lng column.** Only `bridge_properties` (MLS import) and `us_zip_codes` (reference) do. |
| Buyer/Tenant search areas | `location_dna_preferences` meta — one JSON blob: `cities`, `zip_codes`, `neighborhoods`, `counties`, `state`, `polygons`, `radius_searches`, `flexible_location`, `location_notes` | Present. **5 rows exist**, all in `buyer_agent_auction_metas`. |
| Discrete mirrors | `state`, `counties`, `cities` meta, mirrored from the blob on save | Present (`HasSearchAreas::saveSearchAreas`) |
| Important Places | `important_places_json` meta — `type`, `type_other`, `address`, `lat`, `lng`, `distance_pref`, `distance_value`, `travel_mode` | Present. **1 row exists.** |
| Geocode result per listing | `property_location_dna` — `geocoded_lat`, `geocoded_lng`, `geocode_source`, `geocode_status`, `geocode_error` | **16 rows: 11 `geocoded`, 5 `failed`** (the 5 failures are today's `REQUEST_DENIED`) |
| **US gazetteer** | `us_zip_codes` **34,741 rows** (zip, city, state, county, lat, lng) · `us_cities` 25,830 · `us_counties` 3,067 · `us_states` 56 | ✅ **Already loaded, already Google-free, currently unused for geocoding** |

**`us_zip_codes` is the most under-used asset in the codebase.** It already gives ZIP → city / county / state / lat / lng for the whole US with no external call. Today it powers only a ZIP-code type-ahead chip list.

### 4.2 Spatial database (`pgsql_spatial`, PostGIS 3.6) — **live and reachable**

`SPATIAL_DATABASE_URL` is set in the environment (not in `.env`). Connection verified read-only:

| Table | Rows | Assessment |
|---|---:|---|
| `places` (+ partition `places_p_overture_2026_06_17_0_fl`) | **29,434** | ✅ Florida Overture pilot, `confidence ≥ 0.90` |
| `place_categories` | **7** | `coffee_shop`, `gas_station`, `grocery_store`, `gym`, `pharmacy`, `restaurant`, `shopping_center` — exactly the category set named in the request |
| `boundaries` | **67** | ⚠️ **County only, Florida only.** No `place` (city), no `zcta` (ZIP), no `school_district`, no state. |
| `boundaries_parts` | 1,737 | `ST_Subdivide` output for the 67 counties |
| **`addresses`** | **0** | 🔴 **EMPTY — this is the Scope A blocker.** |
| `listing_locations` | **0** | 🔴 **EMPTY.** No listing has ever been written to the spatial DB. |
| `isochrone_cache` | 0 | Travel-time not implemented |
| `place_authority_links` | 0 | Authority overlay not loaded |
| `corpus_imports` | 4 | TIGER county FL, Overture places FL, 2× Gate-2 coverage — all `status: active` |

**Application read path into `pgsql_spatial`: none.** The only code that opens the connection is `spatial:gate2-measure-coverage`, a read-only measurement command. No controller, Livewire component, service, or route consults `places`, `boundaries`, or `listing_locations`. The importers are explicit that they open no connection ("Live staging + load … deferred to the Class-2 phase").

---

## 5. Implemented / partially implemented / disconnected

### ✅ Implemented and working

- Search Areas **data model** — canonical JSON blob, discrete mirroring, draft + submit persistence, edit-page hydration (`HasSearchAreas`)
- Important Places **normalizer** — types, travel modes, `miles`-vs-`minutes` semantics, empty-row tolerance (`ImportantPlacesService`, 152 LOC, pure, tested)
- ZIP / city / county / state **tag inputs** with server-side suggestions from `us_zip_codes` / `us_cities`
- Shared address component for the two Hire flows (`byo-address-autocomplete` + `HandlesGooglePlacesAddress`)
- Honest-degradation notice (`<x-google-maps-unavailable>`) and full auth-failure telemetry — **this is why we could diagnose §1 in minutes with zero billed calls**
- Spatial platform offline authoring: Overture normalizer, boundary importers, Gate 1 / Gate 2 harnesses, acceptance ledgers — all tested
- Florida pilot corpus loaded into live PostGIS (places + county boundaries)

### 🟨 Partially implemented

| Capability | What exists | What's missing |
|---|---|---|
| Polygon / circle / radius drawing | Full custom implementation, serialization, edit/delete, restore-on-edit | Renderer is `google.maps.*` → dead |
| City / county / ZIP boundary display | Nominatim + TIGERweb fetch, caching, viewbox biasing, fallback + inline warning | Renderer dead; sources should move to owned PostGIS; browser-direct Nominatim is policy-noncompliant |
| Important Places | UI rows, types, modes, distance prefs, pin + `miles` circle, save/edit/delete/restore | Geocoding is `google.maps.Geocoder` → dead. **`minutes` mode is stored but never computed** — no routing engine exists |
| Shared address component | Exists, 2 of 4 flows | 2 flows still forked; Google-only; no "must select a suggestion" rule; no server-side address validation |
| Seller/Landlord coordinates | `property_lat` / `property_lng` meta keys, written on save | Only ever populated by the dead Google listener; **never written to `listing_locations`** |
| Category proximity (Overture) | 29,434 FL places, 7 categories, live PostGIS | **Zero application read path** |
| State selection | Dropdown, persisted, mirrored | No state boundary rendering; no state boundaries loaded |

### 🔴 Disconnected

1. **Spatial DB ↔ application.** Two systems, no seam. The corpus cannot influence anything a user sees.
2. **`addresses` table is empty.** The approved geocoder has no data.
3. **Important Places ↔ matching.** `important_places_json` is read by exactly one class — its own normalizer. **No matching consumer exists.**
4. **Search-area geometry ↔ matching.** See §6 — this is the finding the request specifically asked us not to get wrong.
5. **Flood zone / school district** — `FemaFloodZoneBoundarySource`, `CensusSchoolDistrictBoundarySource` write NDJSON; nothing loads them.

---

## 6. Does matching actually read the saved location criteria? — **No.**

The request said: *"Do not claim matching is connected merely because geometry saves."* Traced end to end, **no search-area geometry reaches production matching today.** Two paths exist; both are switched off.

**Path 1 — `service_area` scoring dimension** (`config/match_scoring.php`)

```php
'service_area' => [
    'weight'  => 15,
    'enabled' => false,     // ← OFF
],
```

Even when enabled it reads only the discrete `cities` / `counties` mirrors (`client_location_keys`, lines 313–317). **Polygons, circles, radius searches, ZIP codes, and state are structurally invisible to it.**

**Path 2 — `GeoEnvelopeNarrower`** (`app/Services/Dna/Relevance/Narrowers/GeoEnvelopeNarrower.php`)

This is the real thing: exact Haversine radius, exact point-in-polygon, plus city / ZIP / county set matching. It is well-written and fail-open by design. It is gated twice:

```php
// CandidateNarrowingPipeline.php:110-113
if ($hardFilters) {
    $narrowers['geo'] = new GeoEnvelopeNarrower();
}
```

```php
'v2_enabled'           => env('MATCHING_V2_ENABLED', false),           // ← OFF
'hard_filters_enabled' => env('MATCHING_V2_HARD_FILTERS_ENABLED', false), // ← OFF
```

Neither variable is set in `.env`. It is additionally **`DemandToListings`-only** (reverse direction is a documented no-op, OD-5), and it needs each candidate listing to have a geo point — which requires `property_lat`/`property_lng` to be populated, which requires the geocoder that is currently returning `REQUEST_DENIED`.

**Net:** a user can draw a polygon, save it, reopen the listing and see it restored — and it will change no match result whatsoever. Restoring the map without wiring the consumer would reproduce exactly this, more convincingly.

---

## 7. STOP — decisions required before implementation

> **Update 2026-07-28 — §7.2 is resolved.** The basemap tile source was decided in favour of self-hosted Protomaps PMTiles on Cloudflare R2, and the archive is uploaded and integrity-verified. **§7.1 (geocoding source) remains open and unanswered.** The text below is preserved as the original audit record; see the inline resolution note in §7.2.

The map architecture is settled (**MapLibre GL**, per the migration inventory). Two things underneath it were not — one has since been settled.

### 7.1 🔴 There is no working no-Google geocoding source

Scope A requires "working address suggestions without relying on Google Places" and "resolves and stores latitude and longitude". The approved chain is **MLS coords → NAD → OpenAddresses → TIGER interpolation → Census Geocoder**. The `addresses` table that chain feeds contains **0 rows**, and no importer for it exists (the boundary and Overture importers do; an address importer does not).

The instruction was to use the existing spatial architecture rather than introduce a provider without approval. **The existing architecture is designed but not yet built for addresses.** Options, in the order I'd recommend:

| # | Option | Cost | Coverage | Ongoing |
|---|---|---|---|---|
| **A** | **Load OpenAddresses/NAD for Florida into `addresses`, serve suggestions from our own PostGIS** | ~1 week, one-time | Rooftop-accurate where OA has FL data; needs a measured coverage gate before trusting it | $0, no external dependency, fully aligned with SIA-D25 |
| **B** | **US Census Geocoder API** (public domain, free, no key, no rate limit published) as the resolver, with `us_zip_codes` for ZIP/city/county autofill | ~2 days | Nationwide; TIGER address-range **interpolation**, so points land on the correct block, not the exact rooftop | Free; external dependency but federal and unmetered |
| **C** | **Photon** (roadmap §11) — self-hosted OSM geocoder | ~1 week + a always-on service | Good type-ahead; OSM address coverage in FL suburbs is uneven | Hosting cost |
| **D** | Re-enable the Google APIs in Cloud Console | ~10 minutes | Best quality | Reverses SIA-D25; Places Autocomplete is billed; **and Google Maps Content may not be displayed on a MapLibre basemap** (Maps Service Terms — this is why the roadmap orders data-before-pixels) |

**My recommendation: B now, A next.** Census Geocoder unblocks every flow within days at zero cost and zero licence risk, and `us_zip_codes` (34,741 rows, already loaded) covers the city/county/state/ZIP autofill without any call at all. Option A then upgrades resolution quality behind the same seam, with no UI change. **Option D is a genuine trap** — it would fix today's symptom and then have to be torn out before MapLibre ships, because running Google geocoding under a non-Google basemap is a licence violation, not a style preference.

### 7.2 ✅ **RESOLVED 2026-07-28** — basemap tile source chosen and configured

> **Resolution.** The recommendation below was accepted: **self-hosted Protomaps `.pmtiles` for Florida on Cloudflare R2**. The archive (`basemaps/florida/20260726/florida-z15.pmtiles`, 1.07 GiB, zoom 0–15) is uploaded and verified byte-for-byte, with ranged reads and credential-free public readability confirmed. No vendor and no API key were introduced.
>
> **What this does not unblock.** Two infrastructure prerequisites remain before a browser can render tiles: **CORS is not configured** on the bucket (pending final production origin approval), and **no map library is installed** — `package.json` still has no `maplibre-gl` and no PMTiles client, exactly as this section observed. Full record: [`spatial/basemap-r2-deployment-2026-07-28.md`](./spatial/basemap-r2-deployment-2026-07-28.md).
>
> The original analysis is preserved below.

#### Original audit finding (2026-07-25)

MapLibre GL is a renderer; it does not include tiles. Nothing in `config/`, `package.json` (which has **no map library at all** — no `maplibre-gl`, no `leaflet`), or `.env` names a tile provider. Required decision:

| Option | Cost | Notes |
|---|---|---|
| OSM raster tiles direct | $0 | **Violates OSM tile usage policy at application volume** — same class of problem as the current browser-direct Nominatim calls |
| MapTiler / Protomaps / Stadia (hosted vector) | ~$0–50/mo at pilot volume; free tiers exist | Fastest path; adds a vendor + key; needs approval as a new provider |
| Self-hosted Protomaps `.pmtiles` for Florida | ~$0 + storage | Fully owned, single static file, no vendor; aligns best with SIA-D25; adds a build/refresh step |

**My recommendation: self-hosted Protomaps for the Florida pilot.** A single `.pmtiles` file for FL is a few GB, served from the object storage the R2 work has already stood up, with no vendor, no key, and no usage policy to breach. A hosted vendor free tier is the acceptable fallback if you want it running this week.

**Note:** these two decisions are independent of each other and of the audit. Everything in §8 Phase 0–1 can proceed while you decide. *(2026-07-28: §7.2 resolved; §7.1 still open.)*

---

## 8. Proposed phased repair plan

Sequenced so that each phase is independently shippable and the licence-ordering constraint (data before pixels) is respected. **Phase 0 is unblocked and I can start on your word.**

### Phase 0 — Honest degradation + address validation *(no decision needed, ~2 days)*
Stop `43434` from being accepted, today, with no provider at all.
- Server-side address-shape validation on all four Scope A flows (street number + street name required; reject bare numerals, bare ZIPs, single tokens)
- Extend `<x-google-maps-unavailable>` to the Buyer/Tenant search-area surfaces — the map area currently renders as a silent blank box
- Wire ZIP → city / county / state / lat / lng autofill from **`us_zip_codes`** (already loaded, zero external calls) — restores most of the lost autofill immediately
- Tests: validation rejects `43434`, `33708`, `Main`; accepts `43434 Main Street`

### Phase 1 — Consolidate Scope A onto one component *(no decision needed, ~2 days)*
- Migrate Create Seller + Create Landlord (create **and** edit) onto `<x-byo-address-autocomplete>`
- Collapse the five `fillFromGooglePlaces()` implementations into the single trait
- Rename the seam provider-neutrally (`fillFromAddressResult`) so the provider swap in Phase 2 is a one-file change
- Tests: all four flows render the shared component; the fill method populates city/county/state/ZIP/lat/lng identically across roles

### Phase 2 — Geocoding provider behind a seam *(**needs decision 7.1**, ~3–5 days)*
- `AddressResolverInterface` + registry entry in `config/location_providers.php` (the registry already exists and is designed for exactly this)
- Implement the chosen resolver; Livewire-native suggestion dropdown (same pattern as the existing ZIP type-ahead — no browser SDK, no key in the page)
- Enforce **select-or-confirm**: a typed string that was never resolved cannot be submitted; manual override is explicit and flagged
- Persist lat/lng; **write `listing_locations` in the spatial DB** — the first application write path into PostGIS
- Preserve the existing public-address privacy rule (city/county/state/ZIP public; street revealed only post-hire)
- Tests: Pinellas addresses (see §9), ambiguity, unresolvable + manual override, privacy assertions on public views

### Phase 3 — Boundaries from our own corpus *(no decision needed, ~3 days)*
- Load Census TIGER **place** (city) + **ZCTA** (ZIP) for Florida via the existing `CorpusImportBoundaries` importers — the code is written and tested, the data is simply not loaded
- Boundary lookup endpoint reading `boundaries_parts` from PostGIS
- **Retire browser-direct Nominatim** (GMP-A1) and the TIGERweb browser call
- Tests: Pinellas County, St. Petersburg, 33708 return correct geometry from our DB

### Phase 4 — MapLibre renderer *(~~needs decision 7.2~~ **decision 7.2 resolved 2026-07-28**; now needs CORS origins, ~1–1.5 weeks)*
- Tile source is live: self-hosted Protomaps PMTiles on R2. **CORS must be configured before any browser fetch will succeed.**
- Add `maplibre-gl` **and a PMTiles client** to the Laravel Mix build (note: this repo uses **Laravel Mix**, not Vite, despite `vite.config.js` existing)
- Rewrite `map-input.blade.php`'s renderer — **one file fixes all eight flows plus four legacy criteria pages**
- Rewrite `location-dna-map.blade.php` for the six read-only public views
- Feature-flagged and reversible per the inventory's §3 requirement
- Polygon / circle / radius / boundary overlays / edit / delete / restore, at parity with the current implementation
- Tests: geometry round-trips byte-identically through the new renderer

### Phase 5 — Important Places *(depends on Phase 2, ~3 days)*
- Named-destination resolution through the Phase-2 resolver (**not** the Overture category corpus — these are distinct concepts and must not be conflated)
- Category-based proximity from the **7 loaded Overture categories** via PostGIS LATERAL KNN, where the product design calls for it
- **Be explicit about travel time:** `minutes` mode has no routing engine. Either restrict the UI to `miles` until routing lands, or state plainly in the UI that a travel-time preference is recorded but not yet scored. It must not silently pretend to work.

### Phase 6 — Matching consumption *(the phase that makes any of it matter, ~1 week)*
- Enable `MATCHING_V2_HARD_FILTERS_ENABLED` in non-production and validate `GeoEnvelopeNarrower` against real saved envelopes
- Decide and implement the reverse direction (`ListingToDemands`), currently a documented no-op
- Decide whether `service_area` (weight 15, currently disabled) is enabled — **note the enabled weights must still sum to 100**
- Design Important Places consumption — there is no consumer today, so this is net-new scoring, not a repair
- **Acceptance: a polygon drawn in the UI provably changes a ranked match result.** Nothing less counts as "connected."

**Rough total: 4–6 weeks.** Phases 0, 1, and 3 (~1 week combined) need no decision from you and deliver real user-visible improvement — including making `43434` impossible — before the architecture questions are resolved.

---

## 9. Manual test scenarios (Florida, incl. Pinellas)

| # | Input | Expected |
|---|---|---|
| 1 | `43434` in Street Address | **Rejected** — "Enter a full street address including street name" |
| 2 | `33708` in Street Address | **Rejected** as an address; offered as a ZIP instead |
| 3 | `10 Main` | Suggestions appear; submit blocked until one is selected |
| 4 | `100 2nd Ave N, St. Petersburg` | Autofills St. Petersburg / Pinellas / FL / 33701; lat≈27.7726, lng≈−82.6390 |
| 5 | `1 Beach Dr SE, St Petersburg FL 33701` | Resolves; `unit_address` stays empty and separate |
| 6 | `13801 Walsingham Rd, Largo FL 33774` | Pinellas; unincorporated-adjacent — verify county, not city, drives the boundary |
| 7 | `Seminole, FL` as a city tag | Resolves to **Pinellas** County, not Seminole County near Orlando (the current viewbox-bias behaviour must be preserved) |
| 8 | ZIP `33708` boundary | Renders correct ZCTA polygon from our own corpus |
| 9 | Polygon over downtown St. Pete | Saves; restores on edit; **narrows a match result** |
| 10 | 5-mile radius from #4 | Circle renders; radius edit re-serializes; matching honours it |
| 11 | Important Place: "Tampa International Airport", 30 min driving | Resolves to coords; pin, no circle; UI states travel-time is not yet scored |
| 12 | Public listing view of #4 | Shows **St. Petersburg, Pinellas County, FL 33701** — never `100 2nd Ave N` |
| 13 | Seller listing, no Google key present | Every flow degrades honestly; no silent blank map anywhere |

---

## 10. Summary

The Florida data is real and loaded — 29,434 Overture places, 67 county boundaries, live PostGIS. The application has **no read path to any of it**, an **empty `addresses` table**, and **every location UI in the product renders through a Google credential whose APIs are not enabled**. The Search Areas data model, the Important Places normalizer, and the geo narrower are all well-built and genuinely reusable; they are switched off, starved of coordinates, or drawn by a dead renderer.

The single biggest correction to the framing in the request: this is **not eight page fixes and not one project either — it is one renderer file, one address component, one geocoding decision, and one matching switch.** `map-input.blade.php` alone covers all of Scope B and C.

**Blocking on §7.1 and §7.2. Phases 0, 1, and 3 can start immediately on your word.**
