# Location DNA Engine v1.1 — Governing Architecture (proposal)

**Status:** revision proposal · **not yet adopted** · no code written
**Supersedes:** `LOCATION-DNA-ENGINE-V1.md` (v1.0, 2026-07-29)
**Prepared:** 2026-07-29 · incorporates seven approved architectural decisions from owner review
**Baseline commits (untouched):** `387a971d8` G0 guard · `248403874` city-mirror fix · `6fd0dae80` Phase 2B characterisation

> **Evidence convention used throughout this document.**
> **[MEASURED]** — verified in this repository at `387a971d8`.
> **[DERIVED]** — computed from a measured value; method shown.
> **[ESTIMATE]** — engineering judgement, unverified. Do not plan budgets on these.
> **[UNVERIFIED]** — an external claim that must be confirmed before it is relied on.
>
> v1.0 presented several estimates as facts. That is corrected here.

---

## 1. Executive summary

The Location DNA Engine is **one canonical, server-owned state contract with role-configured UI surfaces** — not one universal map component, and not eight map implementations.

Five findings drive the design. Three contradict the framing this work started from:

1. **These are state-management defects, not rendering defects.** Three of Phase 2B's six findings were state bugs, and G0 fixed a live data-loss path with no renderer involvement. A perfect MapLibre renderer on today's state model would still lose data.

2. **The provider architecture is far more built than v1.0 credited.** `config/location_providers.php` **[MEASURED]** is a declarative provider registry with `tier`, `adapter`, `cost_per_1k`, `regions`, `cache_policy`, `license`, `serves` and `enabled` per provider — plus `LocationProviderRegistry`, `CanonicalLocationMerger`, `CanonicalPoiAssembler` and `CanonicalField` **[MEASURED]**, and two supporting specs. v1.0 proposed *designing* this. **v1.1 proposes wiring what exists.**

3. **Only 4 of 8 workflows have Search Areas** **[MEASURED]**. Seller and Landlord — both families — are subject-property-address only. Two capability profiles, not one.

4. **The read-only viewer is the only migration step not blocked by D1**, because it renders no Google Content over a non-Google basemap.

5. **The 1,641-line partial must be wrapped, not rewritten** — 8 include sites, 4 roles, 2 transports, zero JS test coverage **[MEASURED]**.

**Recommended v1 scope:** G0 (done) → G1 domain core *including mirror consolidation* → read-only MapLibre viewer. AI, demographics, routing and target-market planning are consumers of the contract, deferred.

---

## 2. What changed from v1.0

| # | Change | Rationale |
|---|---|---|
| 1 | **`dimension_meta` removed entirely** | Duplicated a fact JSON already expresses. See §5.3 |
| 2 | **Client/server ownership split made explicit** — client owns UI state and intent; server owns canonical semantics. Client is *not* dumb | v1.0's two-language state model duplicated semantics across an untestable boundary |
| 3 | **Mirror + trait consolidation moved from G7 → G1** | The fragmentation *caused* finding 2B-3. G1 writes the domain core; that is when to end it |
| 4 | **New §8 Storage Evolution** | The JSON blob is a transport/UI contract, not the permanent matching substrate |
| 5 | **Capability policy is config-driven**, not class-heavy | Matches the house idiom **[MEASURED]**: `match_scoring.php`, `location_providers.php`, `*_services_order.php`. Only one policy class exists in the repo |
| 6 | **Every estimate labelled**; bundle size corrected | v1.0 said "~250 KB gzipped"; **[MEASURED]** ~404 KB gz delivered |
| 7 | **Nationwide architecture / Florida data separated** — new §9 | Architecture must be nationwide; datasets may start Florida-only |
| 8 | **Provider section rewritten** as "wire the existing registry" | See finding 2 above |
| 9 | **POI flip downgraded** from "one config flip" to a gated task | `google_places` is `enabled => true`, annotated "do not disturb" **[MEASURED]** |

**Deliberately unchanged** (owner-designated core principles): state-management before renderer · renderer-independent canonical state · two capability profiles · extend the existing provider architecture · wrap don't rewrite · read-only viewer first · Fair Housing avoidance for crime/safety scoring.

---

## 3. Current-state findings

All **[MEASURED]** at `387a971d8`.

### 3.1 Workflow inventory

`@include('partials.location-dna.map-input')` appears in **8 Blade files**:

| Workflow | Profile | Search Areas |
|---|---|---|
| Buyer Create Offer Listing | Search Envelope | ✅ |
| Tenant Create Offer Listing | Search Envelope | ✅ |
| Buyer Hire Agent Listing | Search Envelope | ✅ |
| Tenant Hire Agent Listing | Search Envelope | ✅ |
| Seller Create Offer Listing | Subject Property | ❌ address only |
| Landlord Create Offer Listing | Subject Property | ❌ address only |
| Seller Hire Agent Listing | Subject Property | ❌ address only |
| Landlord Hire Agent Listing | Subject Property | ❌ address only |
| *(legacy)* `buyer_criteria` add/edit | Search Envelope | ✅ form POST |
| *(legacy)* `tenant_criteria` add/edit | Search Envelope | ✅ form POST |

### 3.2 Fragmentation to be eliminated in G1

| Concern | Count | Detail |
|---|---|---|
| Mirror-contract implementations | **5** | `HasSearchAreas` (4 Hire) + 4 byte-identical inline copies in Offer components |
| Livewire bridge implementations | **3** | shared partial + 2 inline copies, 3 distinct guard flags |
| Transports | **2** | Livewire bridge ×4, plain form POST ×4 |

### 3.3 Backend — substantially built

- **6 adapter interfaces**: `BoundaryAdapterInterface`, `CommuteTimeAdapterInterface`, `FloodZoneAdapterInterface`, `NearbyPoiFetcherInterface`, `PoiLookupAdapterInterface`, `SchoolDistrictAdapterInterface`
- **25 services** in `app/Services/LocationDna/`, incl. `CensusTigerBoundaryAdapter`, `CensusSchoolDistrictAdapter`, `FemaFloodZoneAdapter`
- **Provider registry** `config/location_providers.php`, **inert** — documented "Nothing in the runtime path reads it yet"

| Provider | `enabled` | Licence key |
|---|---|---|
| `google_places` | **true** — "active production provider — do not disturb" | `google-tos` |
| `fema` | true | `public-domain` |
| `census_tiger` | true | `public-domain` |
| `stub` | true | — |
| `osm_overpass` | false — adapter **not yet implemented** | `odbl` |
| `geoapify` | false | `geoapify-tos` |
| `openrouteservice` | false | `ors-tos` |

### 3.4 Spatial corpus

`places` 29,434 (Florida Overture, conf ≥ 0.90) · `boundaries` **67 — county only**; city and ZIP layers not imported · `addresses` **0 rows**.

### 3.5 Renderer

MapLibre exists as **9 proof-only files**. `map-input.blade.php` contains **zero** executable MapLibre references, asserted by a committed test.

### 3.6 G0 guard (shipped `387a971d8`)

`ldnaGeometryHydrated` gates geometry rebuild on editor hydration. **Interim limitation:** while the editor cannot hydrate, geometry cannot be intentionally cleared. G1 removes this.

---

## 4. Architecture

### 4.1 Layers

```
┌─ Blade page (8 workflows) ──────────────────────────────────────┐
│  declares: listingFamily · role · mode                          │
│  owns: NO map logic, NO serialization, NO Google                 │
└───────────────────────┬─────────────────────────────────────────┘
┌─ Livewire host ─────────────────────────────────────────────────┐
│  HasLocationDna — ONE trait, replaces 5 implementations (G1)     │
└───────────────────────┬─────────────────────────────────────────┘
┌─ Domain — SERVER OWNS ALL SEMANTICS ────────────────────────────┐
│  LocationDnaState      canonical, versioned, immutable           │
│  LocationDnaSerializer byte-stable encode/decode                 │
│  LocationDnaHydrator   legacy mirrors, presence-vs-absence       │
│  DimensionPatch        partial update — applied server-side      │
│  CapabilityResolver    thin reader over config (§7)              │
└───────────────────────┬─────────────────────────────────────────┘
┌─ Backend services (EXISTING — extend) ──────────────────────────┐
│  BoundaryLookupService · CommuteTimeLookupService · …            │
└───────────────────────┬─────────────────────────────────────────┘
┌─ Providers — EXISTING registry, currently inert ────────────────┐
│  config/location_providers.php → LocationProviderRegistry        │
└─────────────────────────────────────────────────────────────────┘

┌─ Client — owns INTERACTION, not semantics ──────────────────────┐
│  EditorState      transient UI state (in-progress drawing, …)    │
│  RendererAdapter  MapLibre | Legacy | Null                       │
│  IntentEmitter    emits DimensionPatch intents to the server     │
│  LayerManager · Editors · LivewireBridge (ONE, replaces 3)       │
└─────────────────────────────────────────────────────────────────┘
```

### 4.2 Client and server ownership — explicit

This replaces v1.0's `LocationDnaStore`, which duplicated business semantics in JavaScript. The client is **capable, not authoritative**.

| The **client** owns | The **server** owns |
|---|---|
| Editor interaction and input handling | **Canonical state** |
| Transient UI state (in-progress drawing, hover, selection, tab visibility) | **Presence-vs-absence semantics** |
| Drawing polygons | **Intentional-clear semantics** |
| Drawing radius searches | **Merge behaviour** |
| Displaying overlays and layers | **Patch application** |
| Collecting and expressing user intent | **Validation** |
| Optimistic UI where appropriate | **Normalisation** |
| Local undo of an in-progress edit | **Persistence** |
| Rendering read-only geometry | **Legacy-data compatibility** |
| | **Business rules & capability enforcement** |

**The dividing line:** the client may hold a *provisional* view of canonical state for optimistic rendering, but it never *decides* what canonical state means. Absent-vs-empty, clear-vs-unmounted, and merge precedence are resolved **once**, server-side, in the language that has test coverage.

**Why this matters concretely.** The three transport implementations and five mirror implementations exist because semantics leaked outward. G0's bug was a client inferring "cleared" from "not loaded". Both are the same root cause: **semantic authority in the wrong layer.**

### 4.3 UI state vs canonical business state

| | UI / editor state | Canonical business state |
|---|---|---|
| Lives | client memory | server, persisted |
| Lifetime | until page unload | until explicitly changed |
| Examples | half-drawn polygon, active drawing mode, which tab is open, hover target, map centre/zoom | `cities`, `zip_codes`, `counties`, `state`, `polygons`, `radius_searches`, `flexible_location`, `location_notes`, `subject_property` |
| Lost on refresh | yes, harmlessly | **never** |
| Sent to server | only as *intent* | is the record |
| Authoritative | no | yes |

**A renderer failing to mount destroys UI state only.** That is the invariant G0 approximated with a guard and G1 makes structural.

---

## 5. Canonical data contract

### 5.1 Schema v2 (superset of the nine v1 keys)

```jsonc
{
  "schema_version": 2,                    // absent ⇒ v1
  "subject_property": {                   // NEW — Subject Property profile
    "formatted": "…", "street": "…", "unit": "…",
    "city": "…", "county": "…", "state": "FL", "zip": "33708",
    "lat": null, "lng": null,             // null until D1
    "resolution": "unresolved|zip_centroid|rooftop",
    "provider": "census|nad|…"            // provenance — see §12
  },
  "cities": ["St. Petersburg, FL"],
  "zip_codes": ["33708"],
  "neighborhoods": [],                    // stored, no UI, no consumer
  "counties": ["Pinellas County, FL"],
  "state": "Florida",
  "polygons":        [ { "label": "…", "path": [{ "lat": 0, "lng": 0 }] } ],
  "radius_searches": [ { "lat": 0, "lng": 0, "radius_miles": 5, "address": "…" } ],
  "flexible_location": false,
  "location_notes": "",
  "commute": {                            // contract lands G1; UI deferred (§10)
    "destinations": [
      { "label": "…", "lat": 0, "lng": 0,
        "mode": "drive|walk|bike|transit",
        "max_minutes": 30, "arrive_by": "09:00" }
    ]
  }
}
```

`important_places_json` remains a **separate meta key** — it already is, and merging it would be a migration with no benefit.

**`radius_searches` entries carry `address` XOR `label`, never both.** Radius is **miles**, converted from metres by `1609.34` **[MEASURED]** — a renderer reporting metres would multiply every saved radius by ~1,609.

### 5.2 Compatibility

| Class | Fields |
|---|---|
| **Byte-compatible** | all nine v1 keys — Phase 2B proved the PHP save path is byte-opaque **[MEASURED]** |
| **Semantically compatible** | `state` (name or abbreviation both read; writer preserves input) |
| **Additive, no migration** | `schema_version`, `subject_property`, `commute` |
| **Requires migration** | **none** — deliberate |
| **Legacy mirrors** | `cities`, `counties`, `state` discrete meta continue to be written; consulted on hydration **only when the canonical key is absent** |

### 5.3 Presence vs absence — the single source of truth

> **A canonical key that is ABSENT means "never authored" — legacy fallback may apply.**
> **A canonical key PRESENT but empty means "explicitly cleared" — no fallback may apply.**

**Mechanism: `array_key_exists()`. Never `empty()`.**

**v1.0's `dimension_meta` is removed.** It proposed a parallel `{state: authored|absent|cleared}` structure, and that was a design error worth naming plainly:

- **JSON already expresses this distinction.** Key absent vs key present-but-empty is exactly two states, natively representable.
- **It created two sources of truth for one fact.** When `dimension_meta.polygons.state = "cleared"` disagreed with `polygons` being absent, which wins? Every answer is a bug and a reconciliation rule nobody would remember.
- **It required synchronisation** on every write, in both languages, forever.
- **It was already unnecessary in practice** — the city-mirror fix (`248403874`) and the G0 guard (`387a971d8`) both work today on `array_key_exists()` alone, verified by 26 passing tests **[MEASURED]**.

The general principle, and the reason this matters beyond one field: **duplicate sources of truth do not reduce ambiguity, they relocate it.** They convert a question the data can answer into a question only a convention can answer — and conventions drift, silently, exactly the way five mirror implementations drifted into finding 2B-3. Long-term complexity is reduced by having *fewer* places where a fact is recorded, not more.

The serializer must therefore be able to **omit a key** distinguishably from **emitting an empty array**. Today's serializer always writes all keys **[MEASURED]** — that is a G1 change.

---

## 6. Provider strategy — wire what exists

v1.0 proposed designing a provider layer. **It already exists and is inert.**

| Capability | Interface | Status | v1 action |
|---|---|---|---|
| POI / places | `PoiLookupAdapterInterface`, `NearbyPoiFetcherInterface` | registry present, `google_places` active | **Gated task**, not a flip — see below |
| Admin boundaries | `BoundaryAdapterInterface` | `census_tiger` enabled | import city + ZIP layers |
| Flood zones | `FloodZoneAdapterInterface` | `fema` enabled | none |
| School districts | `SchoolDistrictAdapterInterface` | Census adapter present | none |
| Travel-time routing | `CommuteTimeAdapterInterface` | stub; `openrouteservice` declared, disabled | contract only in G1 |
| Basemap tiles | **new** `BasemapTileProviderInterface` | PMTiles archive built | G2 |
| Address autocomplete | **new** | — | ⛔ **D1** |
| Forward/reverse geocoding | **new** | `geocode` capability class exists in config | ⛔ **D1** |
| Demographics | **new** | — | deferred |
| Crime / safety | — | — | **recommend never** (§12) |

**The POI flip is not trivial.** `config/location_providers.php` marks `google_places` as `enabled => true` with the annotation *"remains the active production provider — do not disturb"*, and the file header states the registry is inert **[MEASURED]**. `osm_overpass`'s adapter is annotated **NOT YET IMPLEMENTED**. So moving POI off Google requires: implement an Overture-backed adapter, wire the registry into the runtime path, then flip. That is its own gate, and v1.0 was wrong to call it a one-line change.

**MapLibre supplies rendering only** — no search, no geocoding, no boundaries. Each is a separate provider. This is the most common failure mode of a Google→MapLibre migration.

**Standing requirement: no Google fallback.** A failed provider degrades visibly; it never silently substitutes Google.

---

## 7. Capability policy — configuration-driven

**Decision: configuration, not classes.** I reviewed the repository conventions as instructed and changed my recommendation.

Evidence **[MEASURED]**:
- `config/match_scoring.php` — weights + per-dimension `enabled` flags; the canonical example of policy-in-config
- `config/location_providers.php` — a full capability map with per-provider `enabled`
- `config/{buyer,seller,landlord,tenant}_services_order.php` — role-differentiated behaviour in config
- `app/Policies/` contains exactly **one** class (`ShowingPolicy.php`) — policy classes are the exception here, not the idiom
- CLAUDE.md states display and ordering decisions are config-driven by design

Proposed `config/location_dna_capabilities.php`, mirroring the registry's shape:

```php
return [
    'profiles' => [
        'search_envelope' => [
            'cities' => true, 'zip_codes' => true, 'counties' => true, 'state' => true,
            'polygons' => true, 'radius_searches' => true, 'multiple_areas' => true,
            'important_places' => true, 'editable_map' => true, 'read_only_map' => true,
            'subject_property' => false, 'property_marker' => false,
            'commute' => false,          // contract exists; UI deferred
            'neighborhoods' => false,    // stored, no UI
        ],
        'subject_property' => [
            'subject_property' => true, 'property_marker' => true, 'read_only_map' => true,
            'cities' => false, 'zip_codes' => false, 'counties' => false, 'state' => false,
            'polygons' => false, 'radius_searches' => false, 'multiple_areas' => false,
            'important_places' => false, 'editable_map' => false, 'commute' => false,
        ],
    ],

    // family.role → profile.  Verified against the 8 workflows in §3.1.
    'workflows' => [
        'offer.buyer'    => 'search_envelope',
        'offer.tenant'   => 'search_envelope',
        'hire.buyer'     => 'search_envelope',
        'hire.tenant'    => 'search_envelope',
        'offer.seller'   => 'subject_property',
        'offer.landlord' => 'subject_property',
        'hire.seller'    => 'subject_property',
        'hire.landlord'  => 'subject_property',
    ],

    // Per-workflow overrides, so divergence is explicit and diffable.
    'overrides' => [],
];
```

Read by a thin `CapabilityResolver` — no class hierarchy, no enums, no `CapabilitySet`.

**Enforcement remains server-side and authoritative.** Config declares; the resolver decides; the server **rejects** any patch touching a dimension the workflow does not enable. A page cannot grant itself a capability. This closes the current gap where capability is implied by which Blade file happened to render.

---

## 8. Storage Evolution

**Architectural guidance only. No migration, no PostGIS prerequisite, no redesign.**

- **The canonical JSON contract is the transport and UI contract.** It is what the client exchanges with the server, what Livewire binds, and what persists in `location_dna_preferences`.
- **It is NOT assumed to be the permanent matching substrate.** A JSON blob in EAV meta cannot be spatially indexed. Point-in-polygon and radius matching over many candidates need indexed geometry.
- **Future matching engines may project canonical data into optimised spatial storage** — e.g. a derived PostGIS table with real geometry columns and a spatial index, written *from* the canonical blob.
- **Any such projection must preserve compatibility with the canonical transport contract.** The projection is a read-optimised derivative, never a second source of truth (§5.3). If they disagree, the canonical blob wins and the projection is rebuilt.

**Why state this now without acting on it:** so G1's contract is designed as a *transport* contract rather than accidentally as a storage schema, and so a future spatial projection is an additive read path rather than a rewrite. **PostGIS is explicitly not a prerequisite for anything in v1.**

---

## 9. Nationwide architecture, Florida data

**The architecture is nationwide. The datasets may begin Florida-only.** These are separate concerns and v1.0 conflated them.

**Architecturally nationwide — required now:**
- No Florida-specific branching anywhere in domain, capability, provider or renderer code
- Provider selection is already region-aware: `config/location_providers.php` carries a `regions` key accepting `['*']` or region/state codes **[MEASURED]**
- Boundary, geocode and tile lookups take a region argument; no hardcoded bbox outside configuration
- Basemap archives are addressed by configuration (`SPATIAL_PMTILES_URL` / composed object path) **[MEASURED]** — adding a state means adding an archive and a config entry
- Tests must include at least one non-Florida fixture to prevent Florida assumptions creeping in

**Data may begin Florida-only — acceptable:**
- `places` 29,434 Florida rows; `boundaries` 67 Florida counties **[MEASURED]**
- PMTiles archive covers the Florida bbox, z0–15 **[MEASURED]**

**Expansion must require configuration and data, not architectural change.** Adding Georgia should mean: build a PMTiles archive, import boundaries, add a config entry. If it ever requires touching the domain model, the architecture has failed and that is a defect.

**Explicitly not assumed:** that nationwide coverage is needed on day one. Today's corpus is Florida, and that is a legitimate starting point.

---

## 10. Deferred work — re-evaluated

Per instruction, work moved earlier **only where it materially reduces architectural risk.** Each decision carries its rationale.

### Moved into G1

| Item | Rationale for moving |
|---|---|
| **Mirror + trait consolidation (5 → 1)** | This fragmentation **caused** finding 2B-3, a live data-correctness defect. G1 introduces the domain core those five copies would call. Leaving them in place means adding a sixth abstraction over five divergent implementations, and every later gate inherits the divergence. **Materially reduces risk.** |
| **`commute` contract shape** (schema only, no UI, no provider) | `CommuteTimeAdapterInterface`, `CommuteTimeLookupService`, `CommuteTimeStubAdapter` and `important_places_json` destinations already exist **[MEASURED]**. Adding the key shape now costs almost nothing; adding it later is a schema change to a live contract. **Cheap insurance against a later migration.** |
| **`HasSearchAreas` `empty()` → presence semantics** | Directly contradicts §5.3 in the same codebase. Two opposing rules for one contract is exactly the drift that produced 2B-3. Must be resolved *with* consolidation, not after. |

### Kept deferred — and why moving them would NOT reduce risk

| Item | Why it stays deferred |
|---|---|
| Read-only MapLibre viewer | Genuinely low risk, but it is *renderer* work. Moving it into G1 would mix renderer and domain changes in one gate and blur attribution if something breaks. **May run in parallel with G1 as its own gate.** |
| Bridge consolidation (3 → 1) | Transport, not semantics. Needs the patch protocol from G1 to exist first. Doing it earlier means doing it twice. |
| Editable renderer migration | ⛔ **D1** — licence-blocked, not a scheduling choice |
| Subject-property profile UI | ⛔ **D1** — needs geocoding |
| Commute **UI and provider** | Needs a routing provider evaluation not yet done. Contract ≠ implementation |
| Demographics, target-market, audience, AI, neighbourhood similarity | Consumers of the contract. Cannot reduce architectural risk by arriving before it |
| Transit routing | Needs GTFS ingestion; no dependency on the core |
| `neighborhoods` UI | Stored, no UI, no consumer **[MEASURED]**. Unclear whether feature or dead weight — **owner question** |
| Legacy `map-input` removal | Requires proven parity per workflow. Removing early is the single most dangerous option available |
| Nationwide datasets | Configuration + data, by design (§9) |
| Crime / safety | **Recommend never** (§12) |
| Finding 2B-1 (`false` → `"0"`) | Cosmetic; no consumer affected. Fold into G1 only if the hydrator touches that path anyway |

---

## 11. Roadmap and authorisation gates

| Gate | Objective | Workflows | Blocked by | Rollback |
|---|---|---|---|---|
| **G0** ✅ | Geometry-preservation guard | 4 Search-Envelope + 4 legacy | — | done (`387a971d8`) |
| **G1** | Domain core: `LocationDnaState`, serializer, hydrator, `DimensionPatch`, `CapabilityResolver` + config · **mirror/trait consolidation 5 → 1** · `commute` contract shape · removes G0's interim limitation | additive; consolidation touches 4 Offer components + trait | — | revert; config default preserves current behaviour |
| **G-Test** | Browser automation proposal, then installation | — | owner | — |
| **G2** | MapLibre `RendererAdapter` + **read-only viewer**, flagged. *May run parallel to G1* | 6 view pages | G1 (or standalone) | flag off |
| **G3** | Unified `LivewireBridge` + patch transport | 4 Search-Envelope | G1 | flag off |
| **G4** | Editable pilot: **Buyer Hire Agent Listing** | 1 | G2, G3, **D1** | flag off |
| **G5** | Roll out: Tenant Hire → Buyer Offer → Tenant Offer | 3 | G4 parity | per-workflow flag |
| **G6** | Subject-property profile | 4 | G1, **D1** | flag off |
| **G7** | Retire legacy `map-input`, `location-dna-map` | all | G5 + G6 parity | — |
| **G8+** | Commute UI, demographics, target-market, AI | — | G7 | — |

**G4 pilot rationale:** Buyer Hire Agent Listing uses the shared trait *and* the shared bridge partial **[MEASURED]** — the least bespoke editable path, so the work generalises. Explicitly **not** Tenant Offer, which carries an inline bridge, an inline mirror copy, and the recently landed city-mirror fix.

**Legacy is never removed before parity is proven per workflow.**

---

## 12. Security, privacy, licensing, compliance

**Licensing.**
- OSM/Protomaps attribution — implemented in `config/spatial_basemap.php` **[MEASURED]**
- Census/TIGER, FEMA — public domain **[MEASURED]** via the registry's `license` key
- **Overture licence: [UNVERIFIED].** v1.0 asserted "ODbL/CDLA". The distinction is material — ODbL share-alike would attach obligations to derived datasets that CDLA-Permissive would not. **Must be confirmed before Overture POI data is relied on.**
- **OSM-derived geocoding: [UNVERIFIED] obligations.** If we self-host Nominatim or derive coordinates from OSM, ODbL attribution and possibly share-alike apply to derived geocodes. Not addressed in v1.0. Must be resolved as part of **D1**.
- The registry already carries `cache_policy: attribution-required` **[MEASURED]** — the right hook for enforcing this.

**Google-derived data separation — hard requirement.** Google Maps Content may not be displayed over a non-Google basemap. Provenance must be recorded per record (`provider` field, §5.1) and **enforced by a test** asserting no Google-provenance row is served to a MapLibre surface. This is the constraint that makes the read-only viewer the only unblocked migration step.

**Privacy.** User-drawn polygons are personally revealing — they disclose where someone wants to live, near which schools, at what budget. **Hard requirements, not recommendations:** `location_dna_preferences` is sensitive; never in public listing display; never in client-side logs; never in third-party AI prompts without explicit consent; public read-only viewers render a **generalised envelope**, never exact vertices. Precise street addresses remain agent-only-after-hire (Phase 0 behaviour — must not regress).

**Authorization.** Server-side capability enforcement (§7) is the IDOR boundary. Every patch authorised against `(user, record, dimension)`. `loadDraft()` already scopes by `Auth::id()` **[MEASURED]**; that must be preserved and extended per-dimension.

**Crime / safety scoring — recommend never.** In a housing context this correlates with protected characteristics and carries Fair Housing and steering exposure. Recorded as a **documented refusal**, not a deferral. If ever revisited, counsel reviews first.

**Telemetry.** Renderer health only — never geometry payloads. Retention policy required for drawn geometry on listing deletion.

---

## 13. Performance and scalability

**Bundle — corrected.**

| Asset | Raw | Gzipped |
|---|---|---|
| `maplibre-proof.js` | 1,015.5 KB | **256.0 KB** |
| `maplibre-gl-shared.mjs` | 466.6 KB | **129.3 KB** |
| `maplibre-gl-worker.mjs` | 18.7 KB | ~19 KB |
| **Delivered total** | | **~404 KB gz** |

All **[MEASURED]** at `387a971d8`. **v1.0 claimed "~250 KB gzipped" — a ~1.6× understatement.** Consequence: **lazy-load on first map view, never in `app.js`.** 4 of 8 workflows and most page types never show a map. Worker assets must ship beside the bundle — Phase 2A established that the bundler cannot resolve the worker URL, and without it the map silently never requests a tile **[MEASURED]**.

**Tiles.** Florida archive: **1,119,503,390 bytes (1.07 GiB)**, z0–15, PMTiles spec 3, vector **[MEASURED]**. HTTP range reads (206), `Accept-Ranges`, ETag all verified **[MEASURED]**.

Nationwide: **[DERIVED] ~35–70 GiB.** Method: Florida land area ≈ 65,758 sq mi; CONUS ≈ 3,119,885 sq mi → ratio ≈ 47×; 1.07 GiB × 47 ≈ 50 GiB, widened for variable feature density and shared low-zoom tiles. **This is a derived estimate, not a measurement.** Per-state archives keep range reads local and let coverage grow incrementally.

**Known operational defect [MEASURED]:** the `r2.dev` public URL returns `403 / error code 1010` to clients without a browser-like user-agent. Browser rendering is unaffected; **CI smoke tests and uptime probes are broken by this.** Independent of CORS. A custom domain is required for production — **owner decision, still open.**

**Archive freshness [MEASURED gap]:** the archive pins OSM data at `2026-07-26T04:00:00Z` and **no refresh pipeline exists.** Basemaps will silently age.

**Geometry.** Cap vertices (~1,000 **[ESTIMATE]**) with simplification at capture, retaining full precision for matching. A 1,200-vertex polygon already round-trips **[MEASURED]**. Boundaries as vector tiles, never raw GeoJSON — 67 Florida counties are trivial; nationwide ZIP boundaries (~33,000 **[UNVERIFIED]**) would be tens of MB.

**Routing cost — [ESTIMATE].** Self-hosted tiles carry no per-request fee. Routing is the cost risk: cache isochrones, rate-limit per user. `openrouteservice` is declared in the registry with `cost_per_1k` **[MEASURED]**, but **no routing provider has been evaluated** — v1.0 named Valhalla and OSRM without assessment. Treat as an open task.

**Effort estimates: none offered.** v1.0 implied complexity ratings with no basis. Any estimate here would be a guess and is deliberately omitted.

---

## 14. Risks and unresolved decisions

| # | Item | Impact | Owner call |
|---|---|---|---|
| **D1** | Non-Google geocoder + autocomplete | Blocks G4, G6, all coordinate matching, all autocomplete. **The critical path** | ✅ |
| R1 | Browser automation absent | No JS behaviour verifiable; G0 unverified behaviourally | ✅ |
| R2 | Editable renderer + autocomplete inseparable (licence) | G4 waits on D1 | — |
| R3 | `boundaries` county-only | No city/ZIP boundary display | ✅ authorise import |
| R4 | `addresses` 0 rows | No subject-property coordinates | follows D1 |
| R5 | Registry inert; `google_places` active "do not disturb" | POI stays Google until wired | ✅ authorise wiring |
| R6 | Overture licence **[UNVERIFIED]** | Could attach share-alike | ✅ verify |
| R7 | Livewire v2 internals in transport; v2 EOL | Fragile; upgrade unplanned | ✅ is L3 planned? |
| R8 | Custom domain not configured; `r2.dev` 403s non-browsers | Blocks production tiles + CI probes | ✅ |
| R9 | No PMTiles refresh pipeline | Basemaps age silently | ✅ |
| R10 | Routing provider unevaluated | Commute cost/latency unknown | — |
| R11 | `neighborhoods` stored, no UI, no consumer | Feature or dead weight? | ✅ |
| R12 | No external API consumer assumed | If one is coming, contract should be API-first | ✅ |

---

## 15. Recommended v1 scope

**In:** G0 (done) · G1 domain core **including mirror consolidation** · `commute` contract shape · browser-automation proposal · G2 read-only MapLibre viewer (parallel-capable) · G3 unified bridge · city + ZIP boundary import · Overture adapter + registry wiring **as its own gate**.

**Out of v1:** editable renderer migration (D1) · subject-property profile (D1) · commute UI/provider · demographics · target-market planning · audience targeting · AI outputs · neighbourhood similarity · transit · crime/safety (never) · `neighborhoods` UI · MLS-imported listings · nationwide datasets · legacy removal · PostGIS projection (§8).

---

## 16. Confidence after revision

| Area | Confidence | Change from v1.0 |
|---|---|---|
| State-management-first diagnosis | **High** | — |
| Two capability profiles | **High** | — |
| Config-driven capability policy | **High** | ↑ was Medium — now grounded in repo conventions |
| Provider strategy | **High** | ↑ was Medium — registry found, section rewritten |
| Read-only pilot not D1-blocked | **High** | — |
| Wrap-don't-rewrite | **High** | — |
| Presence-vs-absence as sole mechanism | **High** | ↑ `dimension_meta` removed |
| Client/server ownership split | **High** | ↑ was the weakest part of v1.0 |
| Gate sequencing | **Medium-High** | ↑ consolidation moved into G1 |
| Storage evolution framing | **Medium-High** | new |
| Bundle / tile figures | **High** for measured, **Low** for derived | ↑ now labelled |
| Provider selections (routing) | **Low** | unchanged — still unevaluated |
| Effort estimates | **None offered** | ↑ removed rather than guessed |

**Overall: high confidence in the architecture; low confidence remains only where the document now says so.**

---

## 17. Is v1.1 ready to govern?

**Yes, with two caveats that do not block adoption:**

1. **Overture licence [UNVERIFIED] (R6)** must be confirmed before any Overture data is relied on. It does not block G1, which touches no provider.
2. **No routing provider has been evaluated (R10).** Only the `commute` *contract shape* enters G1; no implementation depends on this.

Both are labelled rather than hidden, which is what makes the document safe to adopt.

**Next recommended step: adopt v1.1, then authorise G1** — domain core plus mirror consolidation. Stop and report before G2.

**The single most important decision remains D1.** Until a non-Google geocoder exists, editable Search Areas cannot legally move to MapLibre, polygon/circle/radius matching cannot work at all, and no listing can carry coordinates.
