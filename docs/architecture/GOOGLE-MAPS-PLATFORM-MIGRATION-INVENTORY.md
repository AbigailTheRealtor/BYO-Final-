# Google Maps Platform — Migration Inventory & Master Checklist

> ## 🟢 ACTIVE — but subordinate, and phase labels are re-keyed — 2026-07-09
>
> **This document is NOT superseded.** It remains the live execution checklist for tracking Google dependencies to zero. It is now **subordinate to [`SPATIAL-INTELLIGENCE-PLATFORM.md`](./SPATIAL-INTELLIGENCE-PLATFORM.md)**, which governs architecture and phase numbering.
>
> **Its inventory and evidence are sound** — including the corrections in its own Appendix A, three of which the successor adopts (A-1 maps reversibility, A-2 the missed consumer-facing results map, A-3 the 49-file autocomplete undercount).
>
> **Two things in it are overridden:**
>
> 1. **Its §10 "interim state at V1" is obsolete, but its header instinct was right.** Product-owner decision **SIA-D25 (2026-07-09): the platform is Google-free by design.** Google is a **legacy dependency to be removed**, never an approved fallback. **No credential is assumed to exist.** Consequently *all* of this inventory now lands **inside Version 1**.
> 2. **GMP-04's replacement chain** (self-hosted Nominatim) is superseded by MLS coords → NAD → OpenAddresses → TIGER interpolation → Census Geocoder.
>
> **Legacy ≠ fallback.** A row may remain ⬜ Not Started while its Google code still runs, *because we have not yet built the replacement.* It may **never** remain reachable *after* its replacement ships. See successor §4.
>
> **Phase label re-key** — replace the `Phase` column values throughout:
>
> | This doc says | Read as (successor §17) |
> |---|---|
> | 0 | **0** — Infrastructure, Safety & Graceful Degradation |
> | 1 | **1** — Provider Abstraction |
> | 2 | **2** — Spatial Foundation (+ address corpus) |
> | 3 | **3a** shadow · **3b** cutover |
> | 5a + 5b | **4** — Address Resolution & Entry ← *now in V1* |
> | 5c | **5** — Map Rendering ← *now in V1* |
> | *(no equivalent)* | **6** — Routing & Travel Time |
> | 6a | **7** — Certification, V1 Launch & Google Code Deletion |
> | 6b | **10** — Schema Teardown (post-launch) |
>
> **Also add, as a Phase 0 precondition on every GMP row backed by the credential (GMP-02 … GMP-11):** the surface must degrade honestly when `services.google.places_key` is absent (successor SIP-P15 / INV-12). Today they all fail invisibly.
>
> Update **Status** as work lands; append to Appendix B. Do not delete rows — a Complete row is the audit trail proving the dependency is gone. Re-run §8 at every phase gate.

**Status:** INVENTORY / TRACKING DOCUMENT — **documentation only. No production code, tests, configuration, database, routes, Git state, or existing roadmap documents changed by this document.**
**Date:** 2026-07-09
**Owner:** Platform Architecture · Product owner: Abigail
**Product direction (approved 2026-07-09):** **Version 1 targets a zero-Google Spatial Intelligence platform.** Google Maps Platform is to be eliminated as the operational foundation for Location DNA, Property DNA, Target Market Intelligence, mapping, geocoding, and address search. Buyer/Tenant commute routing (Valhalla) remains a later phase — it is net-new capability, not a Google replacement.
**No long-term dormant fallback.** A temporary fallback is acceptable *during* a controlled migration window. The architectural goal is complete independence.
**Governing documents:** [`MASTER-SPATIAL-INTELLIGENCE-ARCHITECTURE.md`](./MASTER-SPATIAL-INTELLIGENCE-ARCHITECTURE.md) · [`MASTER-SPATIAL-INTELLIGENCE-EXECUTION-ROADMAP.md`](./MASTER-SPATIAL-INTELLIGENCE-EXECUTION-ROADMAP.md)
**Scope:** BidYourOffer and BidYourAgent only.

> **Purpose.** A complete, verifiable inventory of every Google Maps Platform dependency in the codebase, tracked to zero. This is the execution checklist. When every row reads **Complete**, the platform has zero operational dependence on Google Maps Platform, and that fact is provable by the commands in §8.

---

## Table of Contents

- [1. Summary dashboard](#1-summary-dashboard)
- [2. Scope boundary — what is and is not Google Maps Platform](#2-scope-boundary--what-is-and-is-not-google-maps-platform)
- [3. Migration sequence (data before pixels)](#3-migration-sequence-data-before-pixels)
- [4. Inventory — data-plane dependencies](#4-inventory--data-plane-dependencies)
- [5. Inventory — presentation-plane dependencies](#5-inventory--presentation-plane-dependencies)
- [6. Inventory — configuration, credentials, and persisted Google content](#6-inventory--configuration-credentials-and-persisted-google-content)
- [7. Adjacent items removed by the same work](#7-adjacent-items-removed-by-the-same-work)
- [8. Verification — proving zero dependence](#8-verification--proving-zero-dependence)
- [9. Rollups](#9-rollups)
- [10. Definition of "zero operational dependence"](#10-definition-of-zero-operational-dependence)
- [Appendix A — Corrections to earlier audits](#appendix-a--corrections-to-earlier-audits)
- [Appendix B — Change log](#appendix-b--change-log)

---

## 1. Summary dashboard

**All measurements taken read-only against the working tree on 2026-07-09.** Reproduce with §8.

| Google Maps Platform service | Call sites | Files | Complexity | Phase | Status |
|---|---:|---:|---|---|---|
| Places — Nearby Search | 2 | 2 | High | 1 → 3 | ⬜ Not Started |
| Places — Autocomplete (server proxy) | 12 | 12 | Medium | 5b | ⬜ Not Started |
| Places — Autocomplete (browser widget) | **52** | **49** | High | 5b | ⬜ Not Started |
| Geocoding API (server) | 8 | 6 | Low | 5a | ⬜ Not Started |
| Geocoding (browser `google.maps.Geocoder`) | 2 | 1 | Low | 5b | ⬜ Not Started |
| Maps JavaScript API — loader | 8 | 8 | Medium | 5c | ⬜ Not Started |
| Maps JavaScript API — render surfaces | 6 | 4 | High | 5c | ⬜ Not Started |
| Maps Embed API (iframe) | 1 | 1 | Low | 5c | ⬜ Not Started |
| API key / config | — | 4 | Low | 0 → 6b | ⬜ Not Started |
| Persisted Google Maps Content | — | 3 | Medium | 3 → 6b | ⬜ Not Started |

**Totals: 18 tracked dependencies · ~91 call sites · ~73 distinct files · 0 of 18 Complete.**

**Services NOT used at all** (verified zero occurrences): Place Details, Distance Matrix, Directions/Routes, Reverse Geocoding, Street View, Static Maps, Elevation, Time Zone, Roads, `DrawingManager`. The entire Google surface is **four HTTP endpoints plus the Maps JS SDK**.

**Status legend:** ⬜ Not Started · 🟨 In Progress · ✅ Complete

---

## 2. Scope boundary — what is and is not Google Maps Platform

**In scope (this document tracks these to zero):** everything served from `maps.googleapis.com` or `google.com/maps`, the Maps JS SDK, the `GOOGLE_PLACES_API_KEY` credential, and any Google Maps Content persisted in our database.

**Explicitly out of scope — not Google Maps Platform, not tracked here:**

| Item | Where | Why out of scope |
|---|---|---|
| **Google OAuth / social sign-in** | `config/services.google.client_id`, `redirect`; `app/Http/Controllers/SocialAuth.php` | Identity provider, not Maps Platform. Distinct credential (`GOOGLE_CLIENT_ID`). Removing it is a separate product decision. |
| **Google Fonts** (`fonts.googleapis.com`) | 12 Blade/admin asset files | Static font CDN. No Maps Platform relationship. *(Worth self-hosting for privacy/GDPR, but that is unrelated work.)* |
| Census TIGERweb calls | `map-input.blade.php` | Public-domain federal API. Retained by design. |

**Adjacent but non-Google, removed by the same work:** browser-side calls to `nominatim.openstreetmap.org` (§7, GMP-A1).

---

## 3. Migration sequence (data before pixels)

The ordering below is **load-bearing and legal, not stylistic.** Google's Maps Service Terms forbid using Google Maps *Content* in conjunction with a *non-Google map*. Two states are lawful:

- MapLibre renders **and** zero Google data is consulted → lawful
- Google Maps JS renders **and** zero other Google data is consulted → lawful

Therefore, if all Google **data** (Places, Autocomplete, Geocoding) is removed *before* the basemap is swapped, the renderer swap becomes a **reversible feature flag** rather than a one-way door.

```
Phase 0    Infrastructure, cost protection, test isolation
Phase 1    Provider abstraction (Google isolated behind an interface)
Phase 2    Owned spatial corpus + PostGIS
Phase 3    ► Google DATA leaves Location DNA          (GMP-01, GMP-14)
Phase 5a   ► Google DATA leaves address resolution    (GMP-04, GMP-05*, GMP-16, GMP-17)
Phase 5b   ► Google DATA leaves address entry         (GMP-02, GMP-03, GMP-05)
           ══ ZERO Google Maps Content in the system ══
Phase 5c   ► Google PIXELS leave  (reversible flag)   (GMP-06 … GMP-11)
Phase 6a   Certification → Version 1 launch
Phase 6b   Credential + schema teardown               (GMP-12, GMP-13, GMP-14, GMP-15)
```

`*` `google.maps.Geocoder` (GMP-05) is Google Maps **Content** delivered via the JS SDK. It must be removed in 5b — *before* the renderer swap — even though it lives inside a Google map. Running it inside a Google map until 5c is lawful; running it inside MapLibre would not be.

**Fallback policy (per approved direction).** A temporary Google fallback may be retained *only* within an active migration window, behind an explicit flag, and must be removed at Phase 6b. No dependency in this inventory may reach **Complete** while a Google fallback for it remains reachable in production.

---

## 4. Inventory — data-plane dependencies

*These consume Google Maps Content. All must reach Complete before the basemap is swapped (§3).*

---

### GMP-01 · Places API — Nearby Search

| Field | Value |
|---|---|
| **Current Google service** | Places API — Nearby Search (`maps/api/place/nearbysearch/json`). Pro SKU, **$32/1,000**. |
| **Purpose** | Location DNA POI discovery: 19 categories (16 API calls after `CATEGORY_GROUPS` sharing) per listing. Sole driver of Google spend. |
| **Files / components** | `app/Services/LocationDna/LocationDnaPoiDistanceService.php:50` (production path — raw Guzzle, **bypasses the adapter seam**)<br>`app/Services/LocationDna/GooglePlacesPoiAdapter.php:12` (buyer/tenant path, behind `PoiLookupAdapterInterface`) |
| **Call sites** | **2** endpoint constants; 16 requests per listing enrichment; 7 per buyer/tenant area lookup |
| **Recommended replacement** | **Overture Places** (CDLA-Permissive) in PostGIS + authority overlays (CMS, PAD-US, USGS, NCES, FAA, GTFS/NTD), queried by LATERAL KNN. Ranking switches from Google star ratings to **authority-first ranking** (SSOT §9.2; the six-term prominence prior is retired — E-12). |
| **Complexity** | **High** — `LocationDnaPoiDistanceService` is 1,584 LOC; `LocationDnaRankingEngine` consumes raw Google JSON (`$place['geometry']['location']['lat']`, `$place['types']`) and must be normalised first. |
| **Dependencies** | Phase 0 (queue, test isolation) · Phase 1 (adapter seam, engine normalisation) · Phase 2 (corpus, **Gate 1** authority-first ranking validation, **Gate 2** coverage) |
| **Testing requirements** | Golden-master: `ranking_score`/`rank`/`ranking_reasons_json` byte-identical across the Phase-1 refactor for all 1,090 persisted POI rows · **Gate 3** dual-run diff of rank-1 selection, product-owner signed off · Contract tests: `summary_json`/`lifestyle_json` keys are a superset · Per-role matrix (`seller`, `landlord`, `seller_agent`, `landlord_agent`, `bridge`) · **Network guard: `ComputeLocationDna` makes zero outbound calls** |
| **Rollback strategy** | Config flip: `capabilities['poi.default']` base → `google_places`, `enabled => true`, re-enable key. `fetch_version` mismatch triggers refetch (~$628 at current volume). **No schema revert required.** Window closes at Phase 6b. |
| **Effort** | **4–6 wks** (1–2 seam + engine normalisation; 2–3 corpus adapter + taxonomy; 1 validation) |
| **Phase** | **1** (abstraction) → **3** (removal) |
| **Status** | ⬜ Not Started |

---

### GMP-02 · Places API — Autocomplete (server-side proxy)

| Field | Value |
|---|---|
| **Current Google service** | Places Autocomplete (`maps/api/place/autocomplete/json`) via server-side Guzzle. **No `sessiontoken` is passed**, so this bills at the per-request SKU ($2.83/1,000) rather than the **free, unlimited per-session** SKU. |
| **Purpose** | Address and ZIP type-ahead in Livewire components. Returns only `prediction['description']` strings; the selected address is then resolved by a **separate** Geocoding call (GMP-04). **Place Details is never called.** |
| **Files / components** | 12 Livewire components: `TenantAgentAuction.php:2455` · `TenantAgentAuctionEdit.php:2076` · `HireBuyerAgent/BuyerAgentAuction.php:1059` · `HireBuyerAgent/BuyerAgentAuctionEdit.php:965` · `HireLandLordAgent/LandLordAgentAuctionEdit.php:1203` · `HireSellerAgent/SellerAgentAuctionEdit.php:943` · `OfferListing/Tenant/TenantOfferListing.php:2275` · `OfferListing/Tenant/TenantOfferListingEdit.php:1898` · `OfferListing/Buyer/BuyerOfferListing.php:1152` · `OfferListing/Buyer/BuyerOfferListingEdit.php:1002` · `OfferListing/Seller/SellerOfferListingEdit.php:1382` · `OfferListing/Landlord/LandlordOfferListingEdit.php:1388` |
| **Call sites** | **12** |
| **Recommended replacement** | Self-hosted **Photon** (typeahead index over a Nominatim US import). City/county/ZIP suggestions are **already DB-backed** today (`us_cities` 25,830 · `us_counties` 3,067 · `us_states` 56) — only **street-address** type-ahead is a genuine gap. Longer term: owned NAD + OpenAddresses + TIGER corpus in PostGIS with `pg_trgm` (available, uninstalled). |
| **Complexity** | **Medium** — a single shared helper shape (`getPlaceSuggestions($input, $type)`) repeated across 4 roles. |
| **Dependencies** | Phase 0 (session tokens — an interim cost fix, not a migration step) · Phase 5a (geocoding chain must exist first) · self-hosted geocoder service standing up |
| **Testing requirements** | Suggestion-quality regression across 4 roles · address + `postal_code` type variants · **pin-confirmation UX** exercised (the mitigation for open-geocoder accuracy) · preserve the git-C14.2 posture: *an honest NOT_FOUND is preferable to a confident wrong-city result* |
| **Rollback strategy** | Adapter re-point (config). Temporary Google fallback permitted **only** within the migration window; removed at 6b. |
| **Effort** | **1–2 wks** |
| **Phase** | **5b** |
| **Status** | ⬜ Not Started |

---

### GMP-03 · Places API — Autocomplete (browser widget)

| Field | Value |
|---|---|
| **Current Google service** | `new google.maps.places.Autocomplete` — the browser widget, talking directly to Google from the client. Returns `address_components`, `geometry`, and `place_id` in one call (no separate geocode needed on this path). |
| **Purpose** | Street-address entry on legacy Blade forms and the shared `byo-address-autocomplete` component. **The first field of every listing-creation flow — conversion-critical.** |
| **Files / components** | **49 files, 52 instantiations.** Shared: `components/byo-address-autocomplete.blade.php:88` (143 LOC). Concentrations: `partials/location-dna/map-input.blade.php` (**3 instances** — cities 751/758, counties 800/806, radius address 844/849) · all `seller_property/*` · `buyer_criteria/*` · `tenant_criteria/*` · `landlord_auction/*` · `hire_*_agent/*` · `agent_service/*` · `agent_counter_terms/*` · `auth/signup.blade.php:436` · `offers/_property_being_offered_form.blade.php`<br>**2 orphaned dead files** (zero references): `hire_tenant_agent/add.blade09012024.php`, `buyer_criteria/add-bid.blade1.php` — **delete, do not port.** |
| **Call sites** | **52** (49 files) |
| **Recommended replacement** | Self-hosted Photon-backed type-ahead behind a single shared Blade component + Alpine/vanilla adapter. **Consolidate all 49 files onto the shared component first**, then swap the provider once. |
| **Complexity** | **High** — breadth, not depth. 49 files × 4 roles × create/edit/bid/counter/renew. This is the largest surface in the inventory by file count. |
| **Dependencies** | GMP-02 (same backing service) · Phase 5a · pin-confirmation UX |
| **Testing requirements** | **Browser QA across all 49 files × 4 roles.** Address components extracted (city/state/zip/county) match today's · lat/lng written to hidden fields · pin-confirmation drag persists corrected coordinates · `auth/signup` flow unaffected · no console errors after SDK removal |
| **Rollback strategy** | Feature flag on the shared component (`ADDRESS_PROVIDER=photon|google`). Once consolidated, rollback is one flag rather than 49 reverts. **This consolidation is the single highest-leverage de-risking step in the programme.** |
| **Effort** | **2–3 wks** (1–1.5 consolidation onto the shared component; 1–1.5 provider swap + QA) |
| **Phase** | **5b** |
| **Status** | ⬜ Not Started |

---

### GMP-04 · Geocoding API (server-side)

| Field | Value |
|---|---|
| **Current Google service** | Geocoding API (`maps/api/geocode/json`). Essentials SKU, $5/1,000. |
| **Purpose** | Address → lat/lng, and extraction of `locality`, `administrative_area_level_1/2`, `postal_code` from `address_components`. |
| **Files / components** | `LocationDna/LocationDnaGeocodeService.php:29` (pipeline) · `TenantAgentAuction.php:2222, 2332` · `TenantAgentAuctionEdit.php:1918` · `OfferListing/Tenant/TenantOfferListing.php:2020, 2130` · `OfferListing/Tenant/TenantOfferListingEdit.php:1720` · `Console/Commands/GeocodeSelleryLandlordListings.php:127` |
| **Call sites** | **8** across 6 files |
| **Recommended replacement** | Chain: **MLS coordinates → DOT National Address Database → Census Geocoder → self-hosted Nominatim**. |
| **Complexity** | **Low** — near-vestigial in practice. **100% of the 667 `bridge_properties` rows already carry `latitude`/`longitude`**; 10 of 11 `property_location_dna` rows used `geocode_source='saved_meta'`, only 1 used Google. `LocationDnaGeocodeService:98-120` already short-circuits on supplied coordinates. |
| **Dependencies** | NAD + Census import (Phase 2/5a) |
| **Testing requirements** | Fallback-chain coverage: MLS hit / NAD hit / Census hit / Nominatim hit / total miss · **accuracy regression is expected and accepted**: Census is address-range interpolated, not rooftop (published median error ~90 m urban, **~147 m rural**) · pin-confirmation UX must be live before this ships · assert **no silent ZIP-centroid fallback** (historically a kilometre-scale error mode) |
| **Rollback strategy** | Adapter re-point. Existing `geocode_source` column already discriminates provenance, so mixed-source data is safe. |
| **Effort** | **1–2 wks** |
| **Phase** | **5a** |
| **Status** | ⬜ Not Started |

---

### GMP-05 · Geocoding (browser `google.maps.Geocoder`)

| Field | Value |
|---|---|
| **Current Google service** | `new google.maps.Geocoder` via the Maps JS SDK. Returns Google Maps Content. |
| **Purpose** | Client-side address → coordinate resolution inside the drawing map, for radius-search anchoring. |
| **Files / components** | `resources/views/partials/location-dna/map-input.blade.php:649` |
| **Call sites** | **2** |
| **Recommended replacement** | A Livewire/HTTP call to the same self-hosted geocoding chain as GMP-04. |
| **Complexity** | **Low** |
| **Dependencies** | GMP-04 (the chain must exist) |
| **Testing requirements** | Radius-search anchor resolves identically; map recentres correctly; failure renders an honest "address not found" rather than a silent default centre |
| **Rollback strategy** | Feature flag inside `map-input.blade.php`. |
| **Effort** | **0.5 wk** |
| **Phase** | **5b** — **must precede the renderer swap.** It is Google Maps Content and may not run inside a MapLibre map (§3). |
| **Status** | ⬜ Not Started |

---

## 5. Inventory — presentation-plane dependencies

*These render pixels. Once §4 is Complete, the swap is a reversible flag.*

---

### GMP-06 · Maps JavaScript API — SDK loader

| Field | Value |
|---|---|
| **Current Google service** | `maps.googleapis.com/maps/api/js?key=…&libraries=places[,drawing]` |
| **Purpose** | Loads the Maps JS SDK. The `drawing` library is requested in some views but **`DrawingManager` is never instantiated** — the four `DrawingManager` occurrences in `map-input.blade.php` are comments explicitly stating it is *not* used. The `drawing` library param is dead weight. |
| **Files / components** | Canonical: `components/google-maps-script.blade.php:29` (59 LOC). Ad-hoc loaders: `components/location-dna-map.blade.php:794` · `add_listing.blade.php` · `hire_landlord_agent/edit.blade.php:1327` · `landlord_auction/add.blade.php:1340` · `landlord_auction/edit.blade.php` · `seller_property/edit.blade.php` · `hire_tenant_agent/add.blade09012024.php` *(dead)* |
| **Call sites** | **8** |
| **Recommended replacement** | Delete. MapLibre GL is bundled via Laravel Mix, not loaded from a CDN. |
| **Complexity** | **Medium** — the ad-hoc loaders must be consolidated onto the shared component before removal. |
| **Dependencies** | GMP-07 … GMP-11 |
| **Testing requirements** | No `maps.googleapis.com` request in any page load (browser network tab + automated check) |
| **Rollback strategy** | `MAP_PROVIDER=google` restores the component. Lawful because zero Google data remains (§3). |
| **Effort** | **0.5 wk** |
| **Phase** | **5c** |
| **Status** | ⬜ Not Started |

---

### GMP-07 · Maps JS — interactive drawing map

| Field | Value |
|---|---|
| **Current Google service** | Maps JS: `Map`, `Marker`, `Circle`, `Polyline`, `Geocoder`, `Data` layer, 3 × `Autocomplete` |
| **Purpose** | Buyer/tenant search-area definition: custom click-based polygon drawing, radius circles, city/county/ZIP boundary overlays. Output persists to the `location_dna_preferences` meta key. |
| **Files / components** | `resources/views/partials/location-dna/map-input.blade.php` — **1,586 LOC.** `new google.maps.Map` at :1403. Embedded in **13 forms** (buyer/tenant criteria add/edit, hire-buyer-agent, tenant-agent-auction, offer-listing buyer/tenant tabs, `search-areas-bridge`). |
| **Call sites** | 1 `Map`; 3 `Autocomplete`; 1 `Geocoder`; `Data` layer ×1 |
| **Recommended replacement** | MapLibre GL. Boundary geometry **already comes from Nominatim + Census TIGERweb, not Google** (header comment, lines 11–21) — so the data layer is half-migrated. Drawing is already custom and framework-agnostic. |
| **Complexity** | **High** — the single largest file in the programme, larger than the POI swap. |
| **Dependencies** | GMP-03, GMP-05 (its Autocomplete and Geocoder must move first) · GMP-A1 (browser-Nominatim removal) |
| **Testing requirements** | **Browser QA across all 13 embedding forms × 4 roles.** Polygon draw/edit/delete · radius circle · city/county/ZIP overlay · `location_dna_preferences` JSON round-trips byte-identically · saved geometries render on reload |
| **Rollback strategy** | `MAP_PROVIDER` flag. Keep the Google implementation in-tree until 6b, then delete. |
| **Effort** | **2–3 wks** |
| **Phase** | **5c** |
| **Status** | ⬜ Not Started |

---

### GMP-08 · Maps JS — Location DNA display map

| Field | Value |
|---|---|
| **Current Google service** | Maps JS: `Map`, `Marker`, `Polygon`, `Circle`, `InfoWindow`, `LatLngBounds` |
| **Purpose** | Read-only rendering of saved polygons, radii, and pins on criteria/listing detail views. |
| **Files / components** | `resources/views/components/location-dna-map.blade.php` — **800 LOC**; `new google.maps.Map` at :228, :411, :614. Consumed by `buyer_criteria/view`, `tenant_criteria/view`, and the four `offer-listing/{buyer,tenant,seller,landlord}/view` pages. |
| **Call sites** | **3** `Map` instantiations |
| **Recommended replacement** | MapLibre GL, read-only. |
| **Complexity** | **Medium** — no editing, no autocomplete. |
| **Dependencies** | GMP-06 |
| **Testing requirements** | Visual parity across 6 consuming views × 4 roles; bounds fit; InfoWindow content |
| **Rollback strategy** | `MAP_PROVIDER` flag. |
| **Effort** | **1 wk** |
| **Phase** | **5c** |
| **Status** | ⬜ Not Started |

---

### GMP-09 · Maps JS — buyer search-results map

| Field | Value |
|---|---|
| **Current Google service** | Maps JS: `Map`, `Marker`, `InfoWindow`, `LatLngBounds` |
| **Purpose** | **Consumer-facing** buyer search-results map with per-listing markers and info windows. |
| **Files / components** | `resources/views/stellar/buyer/results.blade.php` — 349 LOC; `new google.maps.Map` at :315, `Marker` :326, `InfoWindow` :335 |
| **Call sites** | **1** `Map` |
| **Recommended replacement** | MapLibre GL + marker layer (cluster layer optional). |
| **Complexity** | **Medium** — but **consumer-facing and revenue-adjacent**; treat regression risk as High. |
| **Dependencies** | GMP-06 |
| **Testing requirements** | Marker count matches result count; bounds fit; info window content; mobile viewport; empty-results state |
| **Rollback strategy** | `MAP_PROVIDER` flag. |
| **Effort** | **1 wk** |
| **Phase** | **5c** |
| **Status** | ⬜ Not Started |
| **Note** | ⚠️ **Missed by two earlier audits**, which asserted "no search-results cluster map exists." It does. See Appendix A. |

---

### GMP-10 · Maps JS — vestigial map (dead code)

| Field | Value |
|---|---|
| **Current Google service** | Maps JS `Map` |
| **Purpose** | **None.** An unused `myMap()` function centred on London (51.5, −0.12). The page's live code uses only the `Autocomplete` at :1656. |
| **Files / components** | `resources/views/add_listing.blade.php:1644-1648` |
| **Call sites** | 1 (unreachable) |
| **Recommended replacement** | **Delete.** Do not port. |
| **Complexity** | **Low** |
| **Dependencies** | None |
| **Testing requirements** | Confirm `myMap()` has no caller; page renders unchanged |
| **Rollback strategy** | Git revert. |
| **Effort** | **<0.5 day** |
| **Phase** | **5c** (or opportunistically earlier) |
| **Status** | ⬜ Not Started |

---

### GMP-11 · Maps Embed API (iframe)

| Field | Value |
|---|---|
| **Current Google service** | Maps Embed API — `https://www.google.com/maps/embed/v1/place?key=…` |
| **Purpose** | Single-pin property location map on the MLS property detail page. Degrades to a "View on Google Maps" link. |
| **Files / components** | `resources/views/components/stellar/property-map.blade.php:46` (61 LOC); used at `stellar/property/detail.blade.php:113` |
| **Call sites** | **1** |
| **Recommended replacement** | MapLibre GL single-marker map, or a static PMTiles-rendered image. |
| **Complexity** | **Low** |
| **Dependencies** | GMP-06 |
| **Testing requirements** | Pin position matches listing coordinates; graceful state when coordinates are absent |
| **Rollback strategy** | `MAP_PROVIDER` flag. |
| **Effort** | **0.5 wk** |
| **Phase** | **5c** |
| **Status** | ⬜ Not Started |
| **Note** | This is the **only** surface exposing the API key in an `iframe` `src`, and the only use of the Embed API. |

---

## 6. Inventory — configuration, credentials, and persisted Google content

---

### GMP-12 · API key (`GOOGLE_PLACES_API_KEY`)

| Field | Value |
|---|---|
| **Current Google service** | A single API key serving **all** Maps Platform usage. |
| **Purpose** | Authenticates Nearby Search, Autocomplete, Geocoding, Maps JS, and the Embed iframe. |
| **Files / components** | `config/services.php:37` (`services.google.places_key`) · emitted into browser HTML by `components/google-maps-script.blade.php:29`, `components/location-dna-map.blade.php:794`, `components/stellar/property-map.blade.php:46`, and inline loaders · used server-side by all 20 Guzzle call sites |
| **Call sites** | ~24 references across 4 config/component surfaces |
| **Recommended replacement** | **Delete.** No credential replaces it — self-hosted services require none. |
| **Complexity** | **Low** to segregate; trivial to delete once §4 and §5 are Complete. |
| **Dependencies** | Phase 0: segregate into a referrer-restricted browser key and an IP-restricted server key. Phase 6b: delete both. |
| **Testing requirements** | Phase 0: browser key rejects server-side calls; server key rejects browser referrers · Phase 6b: application boots and all suites pass with the key absent |
| **Rollback strategy** | Phase 0 is reversible. **Deletion at 6b is final and intentional.** |
| **Effort** | 0.5 wk (Phase 0) + <0.5 day (6b) |
| **Phase** | **0** (segregate) → **6b** (delete) |
| **Status** | ⬜ Not Started |
| **Risk today** | 🔴 The **same secret** is shipped to every browser **and** used for server-side billing. A referrer-restricted key cannot serve Guzzle; an unrestricted key must not be embedded. This is a live security and billing exposure. |

---

### GMP-13 · `config/google_places.php` — the inert kill switch

| Field | Value |
|---|---|
| **Current Google service** | Cost-control configuration for Nearby Search. |
| **Purpose** | `enabled` (default `false`), `daily_limit=100`, `hourly_limit=25` — a circuit breaker added after the 2026-07-05 incident. |
| **Files / components** | `config/google_places.php` |
| **Call sites** | **Zero.** `grep -rn "google_places\." app/` returns no hits. **The kill switch is wired to nothing.** |
| **Recommended replacement** | Phase 0: **wire it** (interim protection). Phase 6b: delete the file with the last Google reference. |
| **Complexity** | **Low** |
| **Dependencies** | Phase 0 |
| **Testing requirements** | `GOOGLE_PLACES_ENABLED=false` demonstrably short-circuits **both** Nearby Search callers without an HTTP call · daily/hourly caps trip and log |
| **Rollback strategy** | Config revert. |
| **Effort** | 0.5 wk (wire) + <0.5 day (delete) |
| **Phase** | **0** (wire) → **6b** (delete) |
| **Status** | ⬜ Not Started |
| **Risk today** | 🔴 The guard written in response to a $1,223 incident does not exist in code. |

---

### GMP-14 · Persisted Google Maps Content — POI ratings

| Field | Value |
|---|---|
| **Current Google service** | Places `rating` and `user_ratings_total`. |
| **Purpose** | Feed `LocationDnaRankingEngine`'s `review_confidence_score` and `consumer_relevance_score`. Rendered as stars in **agent/admin views only** — never to a consumer. |
| **Files / components** | Written: `LocationDnaPoiDistanceService.php:1385-1386` · Model: `PropertyLocationPoi.php:24-25` · Rendered: `admin/dna/partials/location-dna-card.blade.php:168-176, 271-284`, `partials/location-dna-agent-panel.blade.php:148-151` · Schema: migration `2026_06_22_000001` |
| **Call sites** | 2 write sites; 4 render sites |
| **Recommended replacement** | Derived **prominence** + **confidence** signals, composed under **authority-first ranking** (SSOT §9.2; the six-term prominence-prior formula is retired — E-12): authority membership, brand, corpus confidence, source agreement, geometry. The `confidence` / `provenance_json` columns already exist (migration `2026_07_05_000001`) and have **never been written**. |
| **Complexity** | **Medium** — the ranking weights re-tune; **Gate 1** validates. |
| **Dependencies** | GMP-01 · Gate 1 |
| **Testing requirements** | Gate 1 (≤3% embarrassment rate; authority-first ranking vs the **844** rated rows across 13 listings — E-10) · Gate 3 dual-run diff · admin/agent views render prominence instead of stars |
| **Rollback strategy** | Columns stop being **written** in Phase 3 and hold stale, ignored data until dropped in 6b. Dropping early would foreclose the GMP-01 rollback. |
| **Effort** | Included in GMP-01 |
| **Phase** | **3** (stop writing) → **6b** (drop columns) |
| **Status** | ⬜ Not Started |
| **Compliance** | 🔴 **Live violation.** Google's terms permit caching Place content for **no more than 30 days**; only `place_id` may be stored indefinitely. These rows are persisted without expiry. Closed in Phase 3. |

---

### GMP-15 · Persisted Google Maps Content — `google_place_id`

| Field | Value |
|---|---|
| **Current Google service** | Places `place_id`. |
| **Purpose** | Stored on accepted-bid summaries alongside `property_lat`/`property_lng`. |
| **Files / components** | Migration `2026_06_15_000005_add_location_columns_to_accepted_bid_summaries.php:19` · `app/Models/AcceptedBidSummary.php:28` · written by `GeocodeSelleryLandlordListings.php:142` and `HasMlsImport.php:476` |
| **Call sites** | 2 write sites; 1 column |
| **Recommended replacement** | Drop, or repurpose as a provider-agnostic `external_place_ref` keyed on the Overture **GERS ID**. |
| **Complexity** | **Low** — the only Google field lawfully storable indefinitely, and no read path depends on it. |
| **Dependencies** | GMP-04, GMP-16, GMP-17 |
| **Testing requirements** | Confirm zero readers before dropping; accepted-bid summary render and PDF unaffected |
| **Rollback strategy** | Column retained (nullable) until 6b. |
| **Effort** | <0.5 day |
| **Phase** | **6b** |
| **Status** | ⬜ Not Started |

---

### GMP-16 · `GeocodeSelleryLandlordListings` console command

| Field | Value |
|---|---|
| **Current Google service** | Geocoding API (direct `Http::get`, not via `LocationDnaGeocodeService`). |
| **Purpose** | One-off backfill of `property_lat`/`property_lng`/`google_place_id` for seller and landlord listings with an address but no coordinates. `--limit=50`, `--dry-run`. |
| **Files / components** | `app/Console/Commands/GeocodeSelleryLandlordListings.php:127` |
| **Call sites** | 1 |
| **Recommended replacement** | **Retire.** Superseded by the Phase 5a geocoding chain plus `listing_locations` backfill. |
| **Complexity** | **Low** — not scheduled; manual invocation only. |
| **Dependencies** | GMP-04 |
| **Testing requirements** | Confirm no scheduler entry and no caller before deletion |
| **Rollback strategy** | Git revert. |
| **Effort** | <0.5 day |
| **Phase** | **5a** |
| **Status** | ⬜ Not Started |

---

### GMP-17 · `HasMlsImport` save-time geocode fallback

| Field | Value |
|---|---|
| **Current Google service** | Geocoding via `LocationDnaGeocodeService`, plus `place_id` capture. |
| **Purpose** | After MLS fields populate a Livewire form, geocode the address if `property_lat` is absent on both model and component. |
| **Files / components** | `app/Http/Livewire/OfferListing/Concerns/HasMlsImport.php:361, 388-389, 454, 476` |
| **Call sites** | 1 guarded path |
| **Recommended replacement** | Inherits the GMP-04 chain automatically — it calls `LocationDnaGeocodeService`, not Google directly. **No independent work.** |
| **Complexity** | **Low** |
| **Dependencies** | GMP-04 |
| **Testing requirements** | MLS import with and without coordinates; the "only geocode when `property_lat` is missing" guard preserved |
| **Rollback strategy** | Inherits GMP-04. |
| **Effort** | Included in GMP-04 |
| **Phase** | **5a** |
| **Status** | ⬜ Not Started |
| **Note** | A comment at :476 promises to store `place_id` "when available", but `property_location_dna` has **no `place_id` column**. Dead intent; remove with GMP-15. |

---

### GMP-18 · Test-suite Google references

| Field | Value |
|---|---|
| **Current Google service** | All of the above, reachable from tests. |
| **Purpose** | None intended. **This is the cause of the 2026-07-05 incident: 38,236 live Nearby Search requests (~$1,223) over six days, generated by the PHPUnit suite.** |
| **Files / components** | 30 test files reference `google`. Root causes: `QUEUE_CONNECTION=sync` (jobs run inline); the real key present as a system env var that `.env.testing` cannot override; POI callers use bare `new Client()` which `Http::fake()` **cannot** intercept; only 4 of 140 feature-test files call `Bus::fake()`/`Queue::fake()`. |
| **Call sites** | 30 files; a full suite run makes **60** outbound attempts |
| **Recommended replacement** | The four RCA remediations, **none of which has been implemented**: (1) bind `PoiLookupAdapterInterface` → `StubPoiLookupAdapter` and a no-network `LocationDnaPoiDistanceService` in `tests/TestCase.php`; (2) blank the key under `APP_ENV=testing` via `phpunit.xml` + a guard test; (3) resolve the HTTP client from the container in both POI callers; (4) a global stray-request guard that fails loudly. |
| **Complexity** | **Low** to fix; **Critical** to fix first. |
| **Dependencies** | None. **Blocks every other item.** |
| **Testing requirements** | Full suite green with the stray-request guard active and **zero** outbound attempts · guard test asserting the key is blank under `APP_ENV=testing` |
| **Rollback strategy** | N/A — pure safety addition. |
| **Effort** | **1 wk** |
| **Phase** | **0** |
| **Status** | ⬜ Not Started |
| **Risk today** | 🔴 A migration multiplies test runs. Fixing this **before** increasing blast radius is non-negotiable. |

---

## 7. Adjacent items removed by the same work

*Not Google, but they are policy violations or dead code touched by the same files. Tracked so they are not orphaned.*

| ID | Item | Where | Action | Phase | Status |
|---|---|---|---|---|---|
| **GMP-A1** | Browser-side calls to `nominatim.openstreetmap.org` | `map-input.blade.php:860, 874` (cities, counties) | 🔴 **Policy violation.** OSMF forbids autocomplete use and requires an identifying User-Agent a browser cannot set. The platform's own git-C14.2 note states: *"Do NOT use the public `nominatim.openstreetmap.org` service in production."* Replace with the self-hosted geocoder. | 5b | ⬜ |
| **GMP-A2** | Census TIGERweb calls from the browser | `map-input.blade.php:895` | ✅ **Keep.** Public-domain federal API, no policy restriction. Optionally proxy server-side for caching. | — | N/A |
| **GMP-A3** | Orphaned Blade backups | `hire_tenant_agent/add.blade09012024.php`, `buyer_criteria/add-bid.blade1.php` | **Delete.** Zero references from routes, controllers, or views. Both contain Google Autocomplete. | 5b | ⬜ |
| **GMP-A4** | Vestigial London map | `add_listing.blade.php:1644` | **Delete** (= GMP-10). | 5c | ⬜ |
| **GMP-A5** | `libraries=drawing` requested, never used | Several loaders | Drop the param. `DrawingManager` is never instantiated — all four occurrences are comments saying so. | 5c | ⬜ |
| **GMP-A6** | `LocationDnaPoiTileCache` | `app/Services/LocationDna/` | Retire. Exists only to avoid metered calls; disabled today (`tile_precision => null`). Its benchmark doc records every value as **TBD**, so the "0.005 recommended production default" in `config/location_dna.php` was never measured. | 3 | ⬜ |
| **GMP-A7** | `LdnaBenchmarkTilePrecision`, `LdnaPoiCostReport` | `app/Console/Commands/` | Retire. Instruments for a cost that will no longer exist. | 3 | ⬜ |

---

## 8. Verification — proving zero dependence

Run these to regenerate the dashboard, and again at each phase gate. **All must return zero before GMP-12 (key deletion) may proceed.**

```bash
# 1. Any Google Maps Platform endpoint in application code or views
grep -rn "maps.googleapis.com\|google.com/maps" app/ resources/ config/ \
  | grep -v "fonts.googleapis.com"                       # expect: 0

# 2. Any Maps JS SDK symbol
grep -rn "google\.maps\." resources/                     # expect: 0

# 3. The credential
grep -rn "places_key\|GOOGLE_PLACES_API_KEY" app/ config/ resources/   # expect: 0

# 4. Persisted Google Maps Content
grep -rn "user_ratings_total\|google_place_id" app/ resources/ database/  # expect: 0

# 5. Public Nominatim from the browser (GMP-A1)
grep -rn "nominatim.openstreetmap.org" resources/        # expect: 0

# 6. Runtime proof — no outbound call from the enrichment path
php artisan test --filter=NetworkGuard                   # expect: pass, 0 attempts

# 7. Production proof — 7-day sustained metric
#    outbound_google_requests_total == 0
```

**Out-of-scope greps that will still return hits, correctly:** `fonts.googleapis.com` (12 files), `GOOGLE_CLIENT_ID` / OAuth (`config/services.php:34-36`, `SocialAuth.php`).

---

## 9. Rollups

### By phase

| Phase | Items | Effort | Gate |
|---|---|---:|---|
| **0** — Infrastructure | GMP-12 (segregate), GMP-13 (wire), GMP-18 | 1.5–2 wks | Suite makes zero outbound calls |
| **1** — Provider abstraction | GMP-01 (seam + engine normalisation) | 2–3 wks | Golden-master ranking parity, 1,090/1,090 rows |
| **2** — Spatial foundation | *(corpus; no Google removal)* | 3–4 wks | **Gate 1** authority-first ranking · **Gate 2** coverage |
| **3** — Location DNA | GMP-01 (removal), GMP-14 (stop writing), GMP-A6, GMP-A7 | 2–3 wks | **Gate 3** dual-run diff · zero outbound calls |
| **5a** — Geocoding | GMP-04, GMP-16, GMP-17 | 1–2 wks | Fallback chain coverage · pin-confirmation live |
| **5b** — Address entry | GMP-02, GMP-03, GMP-05, GMP-A1, GMP-A3 | 3–5 wks | **Zero Google Maps Content in the system** |
| **5c** — Maps | GMP-06 … GMP-11, GMP-A4, GMP-A5 | 4–5 wks | Browser QA, 13 forms × 4 roles |
| **6a** — Certification | *(verification only)* | 2–3 wks | §8 all zero → **V1 launch** |
| **6b** — Teardown | GMP-12 (delete), GMP-13 (delete), GMP-14/15 (drop columns) | 0.5 wk | Destructive migration, backed up |
| **Total** | **18 items + 7 adjacent** | **20–28 wks** | |

*(Phase 4 — Buyer/Tenant commute routing via Valhalla — contains **no Google dependency** and is therefore absent from this inventory. It is net-new capability, scheduled post-launch.)*

### By complexity

| Complexity | Items |
|---|---|
| **High** (3) | GMP-01 (Nearby Search) · GMP-03 (49-file autocomplete) · GMP-07 (1,586-LOC drawing map) |
| **Medium** (5) | GMP-02 · GMP-06 · GMP-08 · GMP-09 *(consumer-facing — treat risk as High)* · GMP-14 |
| **Low** (10) | GMP-04 · GMP-05 · GMP-10 · GMP-11 · GMP-12 · GMP-13 · GMP-15 · GMP-16 · GMP-17 · GMP-18 |

**The three High items account for roughly 60% of total effort.** They are also the three where a staged, flag-gated approach pays for itself.

### Live risks carried today (all closed by this migration)

| Risk | Item | Severity |
|---|---|---|
| Google ratings persisted indefinitely (30-day ToS cap) | GMP-14 | 🔴 Compliance |
| Public Nominatim called from end-user browsers | GMP-A1 | 🔴 Compliance |
| One un-segregated key: browser HTML **and** server billing | GMP-12 | 🔴 Security / billing |
| Kill switch and rate caps wired to nothing | GMP-13 | 🔴 Cost |
| Test suite reaches live Google (cause of the $1,223 incident) | GMP-18 | 🔴 Cost |

---

## 10. Definition of "zero operational dependence"

The migration is **Complete** when **all** of the following hold:

1. Every row in §4, §5, and §6 reads ✅ **Complete**.
2. All seven checks in §8 return zero.
3. **No Google fallback is reachable in production** — no dormant flag, no disabled-but-present adapter, no retained credential. *(Per the approved product direction: a temporary fallback is acceptable only inside an active migration window and must be removed at Phase 6b.)*
4. `outbound_google_requests_total == 0` sustained for 7 days in production.
5. The `GOOGLE_PLACES_API_KEY` credential is deleted from the environment **and** revoked in Google Cloud.
6. `config/google_places.php` no longer exists.
7. Google OAuth (`GOOGLE_CLIENT_ID`) may remain — it is not Maps Platform (§2).

**Interim state at Version 1 launch.** Phases 0–3 and 5a/5b/5c complete; §8 checks 1–6 return zero; the credential is revoked at 6b after a 30-day soak. If the product owner elects to revoke at launch rather than after the soak, the GMP-06…GMP-11 renderer rollback is forfeited — a tradeoff to be recorded explicitly, not assumed.

---

## Appendix A — Corrections to earlier audits

Recorded so the errors are not propagated into implementation.

| # | Earlier claim | Correction | Evidence |
|---|---|---|---|
| **A-1** | "The maps cut is inherently one-way." | **False.** Google's clause forbids Google Maps *Content* with a *non-Google map*. With zero Google data, **both** renderer states are lawful, so the swap is a reversible flag. This is why §3 sequences data before pixels. | Maps Service Terms; §3 |
| **A-2** | "No search-results cluster map exists." | **False.** `stellar/buyer/results.blade.php:315` renders a consumer-facing marker map. | GMP-09 |
| **A-3** | "~25 legacy Blade forms use client-side Autocomplete." | **Undercount.** **49 files, 52 instantiations.** | GMP-03 |
| **A-4** | "Three map render surfaces." | **Four** (`map-input`, `location-dna-map`, `stellar/buyer/results`, plus the dead `add_listing`), and a fifth Embed iframe. | GMP-07…GMP-11 |
| **A-5** | Architecture §13: "Persist rank-1 only (19 rows/listing)." | Would silently regress the agent panel, which fetches all ranks and displays **top-3**. Persist top-N, default 3. | Roadmap Erratum E-1 |
| **A-6** | `PHASE_8_...RECOMMENDATION.md`: "the intelligence engine is already fully decoupled." | **False.** `LocationDnaPoiDistanceService` calls Google inline via raw Guzzle, bypassing the adapter seam entirely; `LocationDnaRankingEngine` consumes raw Google JSON. | GMP-01 |
| **A-7** | `config/location_dna.php`: "0.005 = recommended production default." | Never measured. `ldna-tile-precision-benchmark.md` records every value as **TBD**. Moot once the tile cache is retired. | GMP-A6 |

---

## Appendix B — Change log

| Date | Change | By |
|---|---|---|
| 2026-07-09 | Initial inventory. 18 dependencies + 7 adjacent items catalogued. All measurements verified read-only against the working tree. Status: 0 of 18 Complete. | Platform Architecture |

---

**Maintenance rule.** This document is the execution checklist. Update **Status** as work lands; append to Appendix B. Do not delete rows — a Complete row is the audit trail proving the dependency is gone. Re-run §8 at every phase gate.
