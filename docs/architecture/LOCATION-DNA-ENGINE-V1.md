# Location DNA Engine v1 — Engineering Architecture

**Status:** Design proposal · **not authorised** · no code written
**Author:** prepared 2026-07-29 · **Owner decision required before any implementation**
**Supersedes the narrow label:** "Phase 2C — MapLibre integration"
**Baseline commits (untouched):** `248403874` city-mirror fix · `6fd0dae80` Phase 2B characterisation

---

## 1. Executive summary

The Location DNA Engine should be **one canonical state-and-contract core with role-configured UI surfaces** — not one universal map component, and not eight map implementations.

Four conclusions drive the design, and two of them contradict the framing of the request:

1. **The provider architecture largely already exists and is already non-Google.** `app/Contracts/` holds six adapter interfaces; `app/Services/LocationDna/` holds 25 services including `CensusTigerBoundaryAdapter`, `CensusSchoolDistrictAdapter` and `FemaFloodZoneAdapter`. v1 should **extend** this, not redesign it. Designing a new provider layer would discard working, tested, licence-clean infrastructure.

2. **Google coupling is overwhelmingly a *frontend* problem, not a platform problem.** The backend is already provider-abstracted; Google survives in POI fetching and geocoding behind interfaces. The hard coupling is 49 Google references inside one 1,641-line Blade partial.

3. **Only 4 of the 8 workflows have Search Areas at all.** Seller and Landlord — both Offer and Hire — are subject-property-address only. Building one engine that forces all eight through the same map surface would add risk to four workflows that need none of it.

4. **The 1,641-line partial should be wrapped, not rewritten.** It is included by 8 Blade files across 4 roles, with 2 structurally different transports. A rewrite is the single riskiest action available, and Phase 2B's characterisation exists precisely so an adapter can be proven equivalent before the legacy path is retired.

**Recommended v1:** canonical renderer-independent state + the data-loss fix + a MapLibre **read-only** viewer as the pilot. Editable migration follows, one workflow at a time. AI, demographics, routing and target-market planning are deferred — they are *consumers* of the contract, not parts of it.

---

## 2. Current-state findings (all verified at `248403874`)

### 2.1 Which workflows have what

`@include('partials.location-dna.map-input')` appears in **8 Blade files**:

| Workflow | Search Areas | Mechanism |
|---|---|---|
| Buyer Create Offer Listing | ✅ | `offer-buyer-tabs/…/property-preferences.blade.php` |
| Tenant Create Offer Listing | ✅ | `offer-tenant-tabs/…/property-details.blade.php` |
| Buyer Hire Agent Listing | ✅ | `hire-buyer-agent/buyer-agent-auction-tabs/…/property-preferences.blade.php` |
| Tenant Hire Agent Listing | ✅ | `tenant-agent-auction-tabs/…/property-details.blade.php` |
| **Seller Create Offer Listing** | ❌ | `byo-address-autocomplete` only |
| **Landlord Create Offer Listing** | ❌ | `byo-address-autocomplete` only |
| **Seller Hire Agent Listing** | ❌ | address only |
| **Landlord Hire Agent Listing** | ❌ | address only |
| *(legacy)* `buyer_criteria` add/edit | ✅ | plain form POST |
| *(legacy)* `tenant_criteria` add/edit | ✅ | plain form POST |

**This asymmetry is the single most important input to the design.** Seller/Landlord location work went through Phase 0/1 (`AddressShapeValidator`, `ZipCodeLookupService`, `ValidatesPropertyAddress`, `HandlesGooglePlacesAddress`, `byo-address-autocomplete`) and is a *different problem* — validating and resolving one subject property, not authoring a multi-part search envelope.

### 2.2 Read-only surface

`resources/views/components/location-dna-map.blade.php` — 810 lines — renders a read-only map on **6 view pages**: `offer-listing/{buyer,tenant,seller,landlord}/view.blade.php` and `{buyer,tenant}_criteria/view.blade.php`. It loads Google independently of `map-input`.

### 2.3 Transport fragmentation (Phase 2B findings 2B-4, 2B-5)

| Transport | Sites | Detail |
|---|---|---|
| Shared bridge partial | 2 | `search-areas-bridge.blade.php`, guard `_ldnaSearchAreasBridgeReady` |
| Inline bridge copies | 2 | guards `_ldnaBuyerBridgeReady`, `_ldnaTenantBridgeReady` |
| Plain form POST | 4 | legacy criteria pages, `<textarea name="location_dna_preferences">` |

The Livewire path injects a `syncInput` into `updateQueue` on `message.sent` because `serverMemo` is HMAC-signed. This works but is **fragile and undocumented Livewire v2 internals**.

### 2.4 Domain layer fragmentation (finding 2B-2)

Five implementations of the discrete-mirror contract: `HasSearchAreas` (4 Hire components) plus four byte-identical inline copies in the Offer components.

### 2.5 Backend — already good

`app/Contracts/`: `BoundaryAdapterInterface`, `CommuteTimeAdapterInterface`, `FloodZoneAdapterInterface`, `NearbyPoiFetcherInterface`, `PoiLookupAdapterInterface`, `SchoolDistrictAdapterInterface`.

Non-Google implementations already shipped: Census TIGER boundaries, Census school districts, FEMA flood zones, commute stub. Google remains in `GooglePlacesPoiAdapter`, `NearbyPoiFetcherFactory`, `LocationDnaGeocodeService`.

Spatial corpus (Crunchy Bridge): `places` 29,434 · `boundaries` **67 — county only** · `addresses` **0 rows**.

### 2.6 Renderer status

MapLibre exists as **9 proof-only files**. `map-input.blade.php` contains **0** MapLibre references — asserted by a committed test. Phase 2C never started.

---

## 3. Eight-workflow capability matrix

**S** = Seller, **B** = Buyer, **L** = Landlord, **T** = Tenant. ✅ v1 · ○ v2+ · — not applicable.

| Capability | B Offer | S Offer | L Offer | T Offer | B Hire | S Hire | L Hire | T Hire |
|---|:--:|:--:|:--:|:--:|:--:|:--:|:--:|:--:|
| Subject-property address | — | ✅ | ✅ | — | — | ✅ | ✅ | — |
| Property marker on map | — | ✅ | ✅ | — | — | ○ | ○ | — |
| Desired cities | ✅ | — | ○ | ✅ | ✅ | — | ○ | ✅ |
| ZIP codes | ✅ | — | ○ | ✅ | ✅ | — | ○ | ✅ |
| Counties | ✅ | — | ○ | ✅ | ✅ | — | ○ | ✅ |
| States | ✅ | — | ○ | ✅ | ✅ | — | ○ | ✅ |
| Neighborhoods / communities | ○ | ○ | ○ | ○ | ○ | ○ | ○ | ○ |
| Polygon drawing | ✅ | — | — | ✅ | ✅ | — | — | ✅ |
| Radius search | ✅ | — | — | ✅ | ✅ | — | — | ✅ |
| Multiple search areas | ✅ | — | — | ✅ | ✅ | — | — | ✅ |
| Commute constraints | ○ | — | — | ○ | ○ | — | — | ○ |
| Important Places | ✅ | — | — | ✅ | ○ | — | — | ○ |
| Editable map | ✅ | ○ | ○ | ✅ | ✅ | — | — | ✅ |
| Read-only map | ✅ | ✅ | ✅ | ✅ | ✅ | ○ | ○ | ✅ |
| Location DNA analysis | ○ | ✅ | ✅ | ○ | ○ | ○ | ○ | ○ |
| Target-market planning | — | ○ | ○ | — | — | ○ | ○ | — |
| Audience targeting | — | ○ | ○ | — | — | ○ | ○ | — |
| AI matching | ○ | ○ | ○ | ○ | ○ | ○ | ○ | ○ |

**Reading of this matrix:** two distinct capability *profiles* exist — a **Search-Envelope profile** (B/T, both families) and a **Subject-Property profile** (S/L, both families). `neighborhoods` is already stored and preserved by the contract but has **no UI in any workflow** and no consumer; it stays contract-only in v1.

---

## 4. Proposed architecture

### 4.1 Layering and responsibility

```
┌─ Blade page (8 workflows) ──────────────────────────────────────┐
│  declares: listingFamily · role · mode · capability profile     │
│  owns: NO map logic, NO serialization, NO Google                 │
└───────────────────────┬─────────────────────────────────────────┘
                        │
┌─ Livewire host component ───────────────────────────────────────┐
│  HasLocationDna (ONE trait, replaces 5 implementations)         │
│  hydrate · receive changes · validate · persist · mirror        │
└───────────────────────┬─────────────────────────────────────────┘
                        │  LocationDnaState (PHP value object)
┌─ Domain (server, framework-free) ───────────────────────────────┐
│  LocationDnaState        canonical, versioned, immutable        │
│  LocationDnaSerializer   byte-stable encode/decode              │
│  LocationDnaHydrator     legacy mirrors, absent-vs-empty        │
│  DimensionPatch          partial update — THE data-loss fix     │
│  CapabilityPolicy        role × family × mode → capabilities    │
└───────────────────────┬─────────────────────────────────────────┘
                        │
┌─ Backend services (EXISTING — extend, do not rebuild) ──────────┐
│  BoundaryLookupService · CommuteTimeLookupService               │
│  FloodZoneLookupService · LocationDnaRankingEngine · …          │
└───────────────────────┬─────────────────────────────────────────┘
                        │  app/Contracts/*AdapterInterface (EXISTING)
┌─ Provider adapters ────────────────────────────────────────────┐
│  PMTiles/R2 · Census TIGER · FEMA · Overture · Nominatim-self   │
└─────────────────────────────────────────────────────────────────┘

┌─ Frontend (client) ─────────────────────────────────────────────┐
│  LocationDnaStore     canonical client state — renderer-free    │
│  RendererAdapter      interface: MapLibre | Null | Legacy       │
│  Editors              city/zip/county/state/polygon/radius      │
│  LayerManager         registry-driven overlays                  │
│  LivewireBridge       ONE implementation, replaces 3            │
└─────────────────────────────────────────────────────────────────┘
```

### 4.2 The load-bearing decisions

**`LocationDnaStore` is renderer-independent and authoritative on the client.** Today geometry lives *inside Google overlay objects* and is reconstructed from them on every serialize — which is the direct cause of the data-loss hazard (§8). The store owns state; the renderer only *reflects* it. A renderer that fails to initialise can then never affect state.

**`RendererAdapter` is an interface with a `NullRenderer`.** Editors and serialization keep working with no renderer at all — which makes headless testing possible and degraded operation safe by construction.

**`CapabilityPolicy` is server-side and authoritative.** A page requests capabilities; the policy decides. Client-side capability config alone is an authorization hole (a Seller page could request polygon editing).

**One trait, not five.** `HasLocationDna` supersedes `HasSearchAreas` and the four inline copies — but only *after* parity is proven per workflow.

---

## 5. Canonical data contract

### 5.1 Schema v2 (superset of the Phase 2B nine keys)

```jsonc
{
  "schema_version": 2,                    // NEW — absent means v1
  "subject_property": {                   // NEW — Seller/Landlord
    "formatted": "…", "street": "…", "unit": "…",
    "city": "…", "county": "…", "state": "FL", "zip": "33708",
    "lat": null, "lng": null,             // null until D1 geocoder
    "resolution": "unresolved|zip_centroid|rooftop",
    "provider": "census|nad|…"
  },
  "cities": ["St. Petersburg, FL"],       // v1 — unchanged
  "zip_codes": ["33708"],                 // v1 — unchanged
  "neighborhoods": [],                    // v1 — contract-only, no UI
  "counties": ["Pinellas County, FL"],    // v1 — unchanged
  "state": "Florida",                     // v1 — unchanged
  "polygons": [                           // v1 — unchanged shape
    { "label": "…", "path": [{ "lat": 0, "lng": 0 }] }
  ],
  "radius_searches": [                    // v1 — unchanged shape
    { "lat": 0, "lng": 0, "radius_miles": 5, "address": "…" }  // address XOR label
  ],
  "flexible_location": false,             // v1 — unchanged
  "location_notes": "",                   // v1 — unchanged
  "commute": {                            // NEW — v2+
    "destinations": [
      { "label": "…", "lat": 0, "lng": 0, "mode": "drive|walk|bike|transit",
        "max_minutes": 30, "arrive_by": "09:00" }
    ]
  },
  "dimension_meta": {                     // NEW — absent-vs-empty, see §8
    "polygons": { "state": "authored|absent|cleared", "updated_at": "…" }
  }
}
```

`important_places_json` stays a **separate meta key** — it already is, and merging it would be a migration with no benefit.

### 5.2 Compatibility classification

| Class | Fields | Notes |
|---|---|---|
| **Byte-compatible** | all nine v1 keys | Phase 2B proved the PHP save path is byte-opaque. Preserved exactly |
| **Semantically compatible** | `state` (name vs abbreviation both accepted) | reader normalises, writer preserves what it was given |
| **Additive, no migration** | `subject_property`, `commute`, `dimension_meta`, `schema_version` | absent = v1 semantics |
| **Requires migration** | **none for v1** | deliberate. Any migration is separately gated |
| **Legacy mirrors** | `cities`, `counties`, `state` discrete meta | continue to be written; hydration consults them only when the blob key is *absent* |

### 5.3 Absent vs. empty — promoted to a first-class rule

The rule established in `248403874` becomes contract law across every dimension:

> **A dimension key that is absent means "never authored — legacy fallback may apply."**
> **A dimension key present but empty means "explicitly cleared — no fallback may apply."**

Implementation rule: **`array_key_exists()`, never `empty()`.** Serialization must therefore be able to emit an empty array *distinguishably from omitting the key* — which the current serializer cannot do, because it always writes all keys.

---

## 6. Provider strategy

**Do not rebuild `app/Contracts/`.** Extend it.

| Capability | Interface | v1 provider | Status |
|---|---|---|---|
| Basemap tiles | **new** `BasemapTileProviderInterface` | Protomaps PMTiles on R2 | archive built + verified (Phase 2A) |
| Admin boundaries | `BoundaryAdapterInterface` ✅ exists | Census TIGER → own PostGIS | adapter exists; city/ZIP layers **not imported** |
| Flood zones | `FloodZoneAdapterInterface` ✅ exists | FEMA NFHL | exists |
| School districts | `SchoolDistrictAdapterInterface` ✅ exists | Census TIGER | exists |
| POI / places | `PoiLookupAdapterInterface` ✅ exists | **Overture** (29,434 FL rows loaded) | ⚠️ default is `GooglePlacesPoiAdapter` — must flip |
| Address autocomplete | **new** `AddressAutocompleteProviderInterface` | Census Geocoder → own `addresses` | ⛔ blocked on **D1** |
| Forward/reverse geocoding | **new** `GeocodingProviderInterface` | Census Geocoder, then OpenAddresses/NAD | ⛔ blocked on **D1** |
| Travel-time routing | `CommuteTimeAdapterInterface` ✅ exists | Valhalla/OSRM self-host *(evaluation needed)* | stub only |
| Transit routing | same interface | GTFS + OpenTripPlanner | v2+ |
| Demographics / Census | **new** `DemographicsProviderInterface` | Census ACS | v2+ |
| Crime / safety | — | **recommend against** | see §12 |

**MapLibre supplies rendering only.** It provides no search, no geocoding, no boundaries. Every one of those is a separate provider — this is the most common architectural mistake in a Google→MapLibre migration and the design names it explicitly.

**Standing requirement preserved: no Google fallback anywhere.** A provider that fails must degrade visibly, never silently substitute Google.

---

## 7. Google removal strategy

| Google capability | Current location | Replacement | Gate |
|---|---|---|---|
| Maps rendering | `map-input` (49 refs), `location-dna-map`, `<x-google-maps-script>` in 20+ blades | MapLibre + PMTiles | v1 |
| Places autocomplete | `map-input` cities/counties/radius, `byo-address-autocomplete`, 12 server-side proxies | Census/NAD autocomplete | **D1** |
| Markers | `google.maps.Marker` | `maplibregl.Marker` | v1 |
| Polygons / circles | `google.maps.Polyline/Polygon/Circle` | MapLibre GeoJSON sources + `@turf/circle` or own Haversine | v1 |
| Data layers | `google.maps.Data` | MapLibre GeoJSON layers | v1 |
| Geocoding | `LocationDnaGeocodeService` | `GeocodingProviderInterface` | **D1** |
| POI data | `GooglePlacesPoiAdapter` | Overture (already loaded) | v1 — flip factory default |
| Telemetry | `google-maps-auth-telemetry`, `gm_authFailure` | renderer-agnostic `RendererHealth` events | v1 |
| Dead references | `hire_tenant_agent/add.blade09012024.php` | delete | v1 |

### The licence-ordering constraint

**Google Maps Content may not be displayed over a non-Google basemap.** Consequence, stated precisely:

> Any workflow that switches to MapLibre must **simultaneously** stop rendering Google-derived data on that map. Because Places autocomplete currently supplies city lat/lng bias and radius-search coordinates, **the renderer swap and the autocomplete swap cannot be separated for editable Search Areas.**

This is exactly why the **read-only viewer is the correct pilot** (§10): the read-only map draws *user-drawn geometry and our own boundaries only* — no Google Content — so it can move to MapLibre **without waiting for D1**.

That is the single most valuable finding in this document.

---

## 8. Data-loss protection strategy

### 8.1 The live hazard

`ldnaSerialize()` rebuilds `polygons` and `radius_searches` **from Google overlay objects** on every call:

```js
ldnaState.polygons = [];  ldnaState.radius_searches = [];
ldnaOverlays.forEach(…)   // empty when the map never initialised
```

With the Google credential dead, `ldnaOverlays` is `[]`. So **any city/state/notes interaction serializes empty geometry and the next save destroys saved polygons and radius searches.** Page load alone is safe — `ldnaSerialize()` runs only from interaction handlers, so save-with-no-changes preserves the server-rendered blob.

**Severity:** high; silent; currently reachable in production for all four Search-Areas workflows.

### 8.2 Smallest immediate protective fix — separate from the migration

**Not to be bundled with the renderer work, and not implemented yet.**

Make geometry rebuild conditional on the renderer actually owning that geometry:

```js
if (ldnaMapInitialized) {
    ldnaState.polygons = [];  ldnaState.radius_searches = [];
    ldnaOverlays.forEach(…);
}
// else: leave the server-hydrated values untouched
```

One file (`map-input.blade.php`), a few lines, no contract change, protects all eight include sites. Tests: change a city with the map uninitialised → polygons and radius survive.

**Trade-off to state honestly:** this makes an *intentional* geometry clear impossible while the renderer is dead. That is the correct trade — silently destroying geometry is far worse than temporarily being unable to delete it — but it is a real behaviour change and needs its own approval.

### 8.3 Permanent design

1. **Canonical client state** — `LocationDnaStore` owns geometry; renderers reflect it. Geometry cannot be lost by a renderer failing to mount.
2. **Dimension patches** — the bridge sends `{dimension, op, value}`, not a whole-blob replace. An unmounted dimension emits no patch.
3. **Server merge is authoritative** — `DimensionPatch::applyTo(LocationDnaState)` merges server-side. The client cannot clear a dimension it never loaded.
4. **Explicit clear** — clearing is `{dimension, op: 'clear'}`, never "absent from payload".
5. **`dimension_meta` state machine** — `authored | absent | cleared` makes intent auditable.

---

## 9. Public component API

Server-authoritative, Livewire-native. Pages declare context; they never implement map behaviour.

### 9.1 Page (Blade)

```blade
<x-location-dna
    :context="LocationDnaContext::for(listingFamily: 'offer', role: 'tenant', mode: 'edit')"
    :state="$locationDnaState"
    wire:model.defer="location_dna_patches" />
```

### 9.2 Host component (PHP)

```php
class TenantOfferListing extends Component
{
    use HasLocationDna;   // ONE trait — supersedes HasSearchAreas + 4 inline copies

    public function locationDnaContext(): LocationDnaContext
    {
        return LocationDnaContext::for(
            listingFamily: ListingFamily::Offer,
            role: Role::Tenant,
            mode: $this->isDraft ? Mode::Draft : Mode::Edit,
        );
        // capabilities resolved SERVER-SIDE by CapabilityPolicy — never client-declared
    }
}
```

### 9.3 Domain surface (framework-free)

```php
final class LocationDnaState
{
    public static function fromJson(?string $json, int $assumeVersion = 1): self;
    public function toJson(): string;                       // byte-stable
    public function has(Dimension $d): bool;                // absent vs empty
    public function isCleared(Dimension $d): bool;
    public function get(Dimension $d): mixed;
    public function with(DimensionPatch $patch): self;      // immutable
    public function withLegacyFallback(LegacyMirrors $m): self;  // absent keys ONLY
    public function forAi(): NormalizedLocationDna;         // §9.5
}

interface CapabilityPolicy {
    public function allows(LocationDnaContext $c, Capability $cap): bool;
    public function capabilities(LocationDnaContext $c): CapabilitySet;
}
```

### 9.4 Client surface

```ts
interface RendererAdapter {
  mount(el: HTMLElement, opts: RendererOptions): Promise<void>;
  isReady(): boolean;
  renderGeometry(g: Geometry): void;      // reflect store; never source of truth
  onGeometryEdited(cb: (p: DimensionPatch) => void): void;
  addLayer(l: LayerDescriptor): void;
  destroy(): void;
}

interface LocationDnaStore {
  hydrate(state: CanonicalState): void;
  patch(p: DimensionPatch): void;
  clear(d: Dimension): void;              // explicit, distinct from absent
  serialize(): DimensionPatch[];          // patches, NOT whole blob
  snapshot(): CanonicalState;
}
```

### 9.5 AI boundary

```php
final class NormalizedLocationDna {
    public array $deterministic;  // computed spatial facts — reproducible
    public array $context;        // labels/notes for interpretation
}
```

**Deterministic (never AI):** boundary containment, distances, drive-time isochrones, POI counts, flood zone, school district, area/overlap.
**AI-interpretable:** ideal buyer/tenant narrative, neighbourhood similarity prose, marketing recommendations, audience plans, campaign suggestions.

**Hard rule:** AI consumes `NormalizedLocationDna` only. AI is never inside the renderer, never inside serialization, and never a source of persisted geometry.

---

## 10. Migration phases

**Pilot recommendation: the read-only Location DNA viewer — not an editable workflow.**

Rationale, in priority order: (1) it writes nothing, so data-loss risk is structurally zero; (2) it draws only user geometry and our own boundaries, so it displays **no Google Content** and is therefore **not blocked on D1**; (3) it proves MapLibre + PMTiles + layers end-to-end on 6 real pages; (4) `location-dna-map.blade.php` is 810 lines, half the editable partial, and has no serialization or transport.

| Gate | Objective | Workflows | Blocked by | Rollback |
|---|---|---|---|---|
| **G0** | Data-loss protective fix (§8.2) | all 4 Search-Areas | — | revert 1 file |
| **G1** | Domain core: `LocationDnaState`, serializer, hydrator, `DimensionPatch`, `CapabilityPolicy`. **No UI.** | none (additive) | — | delete, unused |
| **G2** | MapLibre `RendererAdapter` + **read-only viewer** behind flag | 6 view pages | G1 | flag off |
| **G3** | Unified `LivewireBridge` (replaces 3) + patch transport | 4 Search-Areas | G1 | flag off |
| **G4** | Editable pilot: **Buyer Hire Agent Listing** | 1 | G2, G3, **D1** | flag off |
| **G5** | Roll out remaining editable: T Hire → B Offer → T Offer | 3 | G4 parity | per-workflow flag |
| **G6** | Subject-property profile: S/L Offer + S/L Hire | 4 | G1, **D1** | flag off |
| **G7** | Retire legacy `map-input`, `location-dna-map`, 5 mirror impls | all | G5, G6 parity | — |
| **G8+** | Commute, demographics, target-market, AI | — | G7 | — |

**G4 pilot = Buyer Hire Agent Listing**, because it uses the shared `HasSearchAreas` trait *and* the shared bridge partial — the least bespoke editable path, so the work generalises. Explicitly **not** Tenant Offer: it carries the inline bridge, the inline mirror copy, and the just-landed city-mirror fix.

**Legacy is not removed until parity is proven per workflow** (G7).

---

## 11. Testing strategy

Built on Phase 2B's 55 tests as the frozen baseline.

| Layer | Coverage |
|---|---|
| Contract | byte-stability of v1 keys; `schema_version` absent = v1; round-trip idempotence |
| **Absent vs empty** | every dimension × {absent, empty, populated} — the §5.3 rule |
| **Clear-all** | explicit clear persists; legacy fallback never resurrects it |
| **Cross-dimension preservation** | ⚠️ **required:** changing cities must not alter polygons, radius, notes, state, counties — with renderer mounted **and unmounted** |
| Legacy hydration | pre-blob records; mirror-only; corrupt blob; `info()` returning `false` (finding 2B-1) |
| Role capability | all 8 workflows × capability set; **server refuses** out-of-profile patches (authorization, not UI) |
| Provider contract | one shared suite per interface, run against every adapter incl. `Null` |
| Renderer | `NullRenderer` proves editors + serialization work with no map |
| Livewire | mount/hydrate/patch/validate/persist per workflow |
| Browser (**new capability required**) | Playwright or Dusk — neither is installed today. Covers: legacy record opens populated · save-with-no-changes · add · remove · clear · reload after each · draft + versioned clone |
| Visual | basemap render, layer paint, degraded state |
| A11y | keyboard polygon editing, screen-reader labels, focus order, contrast |
| Mobile | touch drawing, viewport, tile budget |
| Performance | 1,200-vertex polygon (already characterised), 50 areas, tile cache hit rate |

**Browser automation is a v1 prerequisite, not an optional extra.** Phase 2B proved by measurement that the JS layer is untestable here, and G0–G7 all depend on JS behaviour. Installing Playwright is a gate in its own right.

---

## 12. Security, privacy, licensing, compliance

**Licensing.** OSM/Protomaps require attribution — already implemented in `config/spatial_basemap.php`. Census/TIGER and FEMA are public domain. Overture is ODbL/CDLA — attribution required. **Google-derived data must be physically separated**: Google-sourced POI or coordinates may never be stored in the same records rendered over PMTiles. Recommend a `provider` column on any cached place/coordinate so provenance is queryable, and a test asserting no Google-provenance row is served to a MapLibre surface.

**Privacy.** Precise home addresses stay agent-only-after-hire (Phase 0 behaviour — must not regress). User-drawn polygons are **personally revealing** — they disclose where someone wants to live, near which schools, on what budget. Treat `location_dna_preferences` as sensitive: never in public listing display, never in client-side logs, never in AI prompts sent to third parties without explicit consent. Read-only public viewers must render a **generalised** envelope, not exact vertices.

**Authorization.** `CapabilityPolicy` server-side closes the current gap where capability is implied by which Blade file rendered. Every patch is authorised against `(user, record, dimension)` — this is the IDOR boundary. Note `loadDraft()` already scopes by `Auth::id()`; the new engine must preserve that and add per-dimension checks.

**Crime/safety data — recommend against for v1.** Crime data is geographically biased, correlates with protected characteristics, and in a housing context risks steering and Fair Housing exposure. If ever added, it must be reviewed by counsel first. I would not build it.

**Telemetry/retention.** Renderer health only — never geometry payloads. Define retention for drawn geometry on listing deletion.

---

## 13. Performance and scalability

**Tiles.** Florida PMTiles is 1.07 GiB, z0–15, HTTP range reads verified. Nationwide at this zoom is ~30–60 GiB — viable on R2 but needs a **custom domain** (the `r2.dev` URL is dev-only and 403s non-browser agents) and cache headers. Per-state archives keep range reads local and let coverage grow incrementally.

**Bundle.** `maplibre-gl` is ~250 KB gzipped. **Lazy-load on first map view**, never in `app.js` — 4 of 8 workflows and 4 of 8 page types never show a map. Worker assets must ship beside the bundle (Phase 2A learned this: the bundler cannot resolve the worker URL, and without it the map silently never requests a tile).

**Geometry.** Cap vertices (~1,000) with Douglas–Peucker simplification at capture, storing both simplified and original. Server-side simplification for display; full precision for matching. Boundaries as vector tiles, never raw GeoJSON — Florida's 67 counties are fine, but nationwide ZIP boundaries (~33,000) would be tens of MB.

**Degraded/offline.** `NullRenderer` + cached last-known state; editors remain usable; **no writes that could clear unloaded dimensions** (§8).

**Cost.** Self-hosted tiles have no per-request vendor fee — the main reason D2 chose them. Routing is the cost risk: cache isochrones aggressively, rate-limit per user, and prefer self-hosted Valhalla over a metered API.

---

## 14. Risks and unresolved decisions

| # | Risk / decision | Impact | Owner call needed |
|---|---|---|---|
| **D1** | No-Google geocoder — still unanswered | Blocks G4, G6, all autocomplete, all coordinate-based matching | ✅ **the critical path** |
| R1 | Editable renderer swap cannot be separated from autocomplete swap (licence) | G4 waits on D1 | — |
| R2 | Browser automation absent | Cannot verify any JS work | ✅ approve Playwright |
| R3 | `boundaries` = county only; city/ZIP not imported | City/ZIP boundary display impossible | ✅ authorise import |
| R4 | `addresses` = 0 rows | No subject-property coordinates | follows D1 |
| R5 | Livewire v2 `updateQueue` internals | Fragile transport | evaluate Livewire 3 |
| R6 | 1,641-line partial has no JS tests | Adapter equivalence unprovable without R2 | — |
| R7 | Trait still uses `empty()` (deferred defect) | Hire flows can resurrect cleared cities | ✅ separately scoped |
| R8 | Nationwide tile cost/coverage | Beyond-Florida rollout | v2 |
| R9 | Custom domain not configured | Blocks production tiles | ✅ owner |

---

## 15. Recommended v1 scope

**In:**
1. G0 data-loss protective fix
2. G1 domain core — canonical state, absent-vs-empty, dimension patches, capability policy
3. Playwright browser automation
4. G2 MapLibre read-only viewer (6 pages) — **not blocked on D1**
5. G3 unified Livewire bridge
6. Flip POI default from Google to Overture
7. City + ZIP boundary import using existing importers

**Deliberately out of v1** — every one is a *consumer* of the contract, not part of it: editable renderer migration (needs D1), subject-property profile (needs D1), commute/routing, demographics/Census, target-market planning, audience targeting, neighbourhood similarity, AI outputs, transit, crime/safety (recommend never), MLS-imported listings, nationwide coverage, legacy removal.

**Why this scope:** it delivers real value (a working non-Google map on 6 pages, plus the data-loss fix) without waiting on D1, while building the contract everything else will need. It touches no editable save path except the protective fix.

---

## 16. Explicitly deferred beyond v1

Commute constraints · demographics · target-market planning · audience targeting · AI interpretation layer · neighbourhood similarity · transit routing · crime/safety (recommend against) · `neighborhoods` UI · MLS-imported listings · nationwide tiles · Landlord/Seller search-envelope capabilities (○ in matrix) · legacy `map-input` removal · trait `empty()` alignment (R7) · 2B-1 persistence fix · bridge/mirror consolidation beyond what G3 requires.

---

## 17. Engineering recommendation — including where I disagree

**Is a single shared engine correct? Partly — and the distinction matters.**

✅ **Yes for the core:** canonical state, serialization, absent-vs-empty semantics, persistence, capability policy, provider adapters. Phase 2B found *five* implementations of the mirror contract and *three* bridges; that fragmentation is the root cause of finding 2B-3. One core is unambiguously right.

❌ **No for the UI.** Two capability profiles exist, and pretending otherwise adds risk. Seller/Landlord need one validated subject property; Buyer/Tenant need a multi-part search envelope. Forcing four address-only workflows through a Search-Areas engine would add a map, a renderer and a geometry serializer to pages that need none — for zero user benefit. **Recommend: one core, two UI profiles, shared editors.**

**Should the 1,641-line partial be adapted or replaced? Adapted behind an adapter — replaced last.** A rewrite touches 8 include sites across 4 roles and 2 transports, with no JS test coverage. Phase 2B's characterisation exists to make an adapter *provably* equivalent. Replace only at G7, after per-workflow parity.

**Is Livewire the right boundary? Yes — but not the current bridge.** Livewire is correct for server-authoritative state. But `message.sent` `updateQueue` injection depends on Livewire v2 internals, is duplicated three ways, and is the fragile point in the whole system. G3 should replace it with one implementation using a documented mechanism, and the patch protocol should be explicit rather than whole-blob replacement.

**Server vs client.** Server: capability policy, authorization, validation, canonical merge, boundary/geocode/routing queries, all provider calls, AI. Client: rendering, geometry capture, optimistic UI. **Never client-authoritative:** capability, authorization, or the canonical merge.

**Where I'd push back hardest on the framing:** the request treats this as a mapping problem. The genuinely dangerous defects Phase 2B found — the mirror going stale, geometry being wiped by an uninitialised renderer, absent-vs-empty being indistinguishable — are **state-management defects, not rendering defects.** A beautiful MapLibre renderer on top of the current state model would still lose data. **Fix the state model first (G0, G1); the renderer is the easier half.**

And the honest bottom line: **D1 is the critical path, not the renderer.** Until a non-Google geocoder exists, editable Search Areas cannot legally move to MapLibre, polygon/circle/radius matching cannot work at all, and subject-property coordinates cannot exist. If only one decision gets made from this document, it should be D1.

---

## 18. Authorization gates — next recommended step

**Recommended next step: authorise G0 and G1 only.**

| Gate | Deliverable | Complexity | Decision needed |
|---|---|---|---|
| **G0** | Data-loss protective fix — 1 file, few lines, + regression tests | **Low** | Accept the trade-off in §8.2 |
| **G1** | Domain core, additive, no UI, no page touched | **Medium** | Approve schema v2 (§5) |
| G-Test | Playwright installation | Low–Med | Approve new dev dependency |
| G2 | Read-only MapLibre viewer, flagged | Medium | After G1 |
| G3+ | Everything else | High | Requires **D1** |

**Stop point:** report after G0 and G1 with diffs, tests, and no page behaviour changed. Nothing else begins without separate approval.

Three decisions I'd ask for now, in order: **(1) D1 geocoder. (2) Playwright. (3) G0 trade-off acceptance.**
