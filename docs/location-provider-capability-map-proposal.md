# Provider Capability Map — Structure Proposal

**Status:** PROPOSAL / DRAFT. Structure definition for Phase 8. No code changes implied by this document.
**Date:** 2026-07-05
**Companion:** `docs/canonical-field-mapping-spec.md` (canonical envelope + precedence + merge)
**Related:** `docs/LOCATION_DNA_PHASE_A_GOVERNANCE_AND_DATA_SOURCE_PLAN.md` (provider survey + cost tables), `docs/PHASE_8_PROVIDER_AGNOSTIC_LOCATION_INTELLIGENCE_RECOMMENDATION.md`

> **Purpose.** Replace the current single-provider binding (`AppServiceProvider`: `if google … else stub`) with a declarative **registry + capability map** so providers can be added, replaced, or **combined** by editing config — never by touching an intelligence engine. This is the layer that turns "many providers" into "one canonical truth" (merge/precedence live in the canonical spec §6).

---

## 1. Today vs. target

**Today** (`app/Providers/AppServiceProvider.php:95–101`):
```php
$this->app->bind(PoiLookupAdapterInterface::class, function ($app) {
    $provider = config('location_dna.poi.provider', 'google');
    if ($provider === 'google' && !blank(config('services.google.places_key'))) {
        return new GooglePlacesPoiAdapter();
    }
    return new StubPoiLookupAdapter();   // one provider at a time; no combination, no per-category routing
});
```
Interfaces already exist (`PoiLookupAdapterInterface`, `FloodZoneAdapterInterface`, `CommuteTimeAdapterInterface`, `BoundaryAdapterInterface`, `SchoolDistrictAdapterInterface`), and downstream is already provider-independent. **Missing:** a way to route *per category/attribute* and *combine* providers.

**Target:** a `LocationProviderRegistry` resolves, per canonical category, an *ordered set* of `{provider, role}` from config; adapters run in role order; the `CanonicalLocationMerger` (canonical spec §6) merges into one envelope.

---

## 2. Proposed config file: `config/location_providers.php`

Two blocks: **`providers`** (a descriptor per provider) and **`capabilities`** (per canonical category → ordered provider roles). Inert config — safe to land with no active wiring.

```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Provider registry — one descriptor per provider
    |--------------------------------------------------------------------------
    | tier          free | premium
    | adapter       FQCN implementing the relevant *AdapterInterface
    | cost_per_1k   USD per 1,000 calls (0 for free/self-hosted) — for cost-aware routing/reporting
    | regions       ['*'] = global, or ISO regions / states where this provider is preferred
    | cache_policy  cacheable | ephemeral | attribution-required   (drives canonical spec §7/§8)
    | license       odbl | google-tos | public-domain | geoapify-tos | attom-licensed | ors-tos
    | serves        canonical attribute classes this provider can populate:
    |                 existence | geometry | rating | quality | hazard | boundary | commute | geocode
    | enabled       runtime kill switch per provider
    */
    'providers' => [

        'osm_overpass' => [
            'tier'         => 'free',
            'adapter'      => \App\Services\LocationDna\OsmOverpassPoiAdapter::class,   // NOT YET IMPLEMENTED
            'cost_per_1k'  => 0.0,
            'regions'      => ['*'],
            'cache_policy' => 'attribution-required',
            'license'      => 'odbl',
            'serves'       => ['existence', 'geometry'],
            'enabled'      => false,   // wire only after spec sign-off
        ],

        'google_places' => [
            'tier'         => 'premium',
            'adapter'      => \App\Services\LocationDna\GooglePlacesPoiAdapter::class,  // EXISTS — launch provider
            'cost_per_1k'  => 32.0,
            'regions'      => ['*'],
            'cache_policy' => 'ephemeral',        // Google ToS — persist place_id + derived confidence only
            'license'      => 'google-tos',
            'serves'       => ['existence', 'geometry', 'rating', 'quality'],
            'enabled'      => true,               // remains the launch provider — do not disturb
        ],

        'geoapify' => [
            'tier'         => 'free',
            'adapter'      => \App\Services\LocationDna\GeoapifyPoiAdapter::class,      // NOT YET IMPLEMENTED
            'cost_per_1k'  => 0.0,                // free tier; see Phase-A plan
            'regions'      => ['US'],
            'cache_policy' => 'cacheable',
            'license'      => 'geoapify-tos',
            'serves'       => ['existence', 'geometry'],
            'enabled'      => false,
        ],

        'fema' => [
            'tier' => 'free', 'adapter' => \App\Services\LocationDna\FemaFloodZoneAdapter::class,   // EXISTS
            'cost_per_1k' => 0.0, 'regions' => ['US'], 'cache_policy' => 'cacheable',
            'license' => 'public-domain', 'serves' => ['hazard'], 'enabled' => true,
        ],

        'census_tiger' => [
            'tier' => 'free', 'adapter' => \App\Services\LocationDna\CensusTigerBoundaryAdapter::class, // EXISTS
            'cost_per_1k' => 0.0, 'regions' => ['US'], 'cache_policy' => 'cacheable',
            'license' => 'public-domain', 'serves' => ['boundary'], 'enabled' => true,
        ],

        'openrouteservice' => [
            'tier' => 'free', 'adapter' => \App\Services\LocationDna\OpenRouteServiceCommuteAdapter::class, // NOT YET
            'cost_per_1k' => 0.0, 'regions' => ['*'], 'cache_policy' => 'cacheable',
            'license' => 'ors-tos', 'serves' => ['commute'], 'enabled' => false,
        ],

        'stub' => [
            'tier' => 'free', 'adapter' => \App\Services\LocationDna\StubPoiLookupAdapter::class,   // EXISTS
            'cost_per_1k' => 0.0, 'regions' => ['*'], 'cache_policy' => 'cacheable',
            'license' => 'public-domain', 'serves' => ['existence','geometry','commute'], 'enabled' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Capability map — canonical category → ordered provider roles
    |--------------------------------------------------------------------------
    | role: base     supplies the primary value (existence/geometry). One per category.
    |       overlay  merges additional attributes onto the base (e.g. rating) — canonical spec §6.
    |       fallback used only when higher roles return nothing.
    | Registry resolves in this order; disabled providers are skipped.
    | OSM/open-data-first where practical; Google as premium overlay + fallback, not the base dependency.
    */
    'capabilities' => [

        'poi.default' => [                       // applies to all POI categories unless overridden
            ['provider' => 'osm_overpass',  'role' => 'base'],      // free existence/geometry
            ['provider' => 'geoapify',      'role' => 'fallback'],  // free, US
            ['provider' => 'google_places', 'role' => 'overlay'],   // premium: rating/reviews only
        ],

        // Launch reality: OSM/Geoapify not yet wired → until enabled, resolution falls through
        // to google_places (base+overlay). No behavior change vs. today while OSM is disabled.

        'poi.hospitals' => [                     // per-category override: quality matters most here
            ['provider' => 'google_places', 'role' => 'base'],      // keep rated source as base for critical categories
            ['provider' => 'osm_overpass',  'role' => 'fallback'],
        ],

        'hazard.flood_zone'        => [['provider' => 'fema',             'role' => 'base']],
        'boundary.school_district' => [['provider' => 'census_tiger',     'role' => 'base']],
        'boundary.city'            => [['provider' => 'census_tiger',     'role' => 'base']],
        'commute'                  => [
            ['provider' => 'openrouteservice', 'role' => 'base'],
            ['provider' => 'stub',             'role' => 'fallback'],
        ],
        'geocode' => [
            ['provider' => 'google_places',  'role' => 'base'],     // established today
            ['provider' => 'osm_overpass',   'role' => 'fallback'], // Nominatim
        ],
    ],

    /*
    | Region overrides (future / nationwide): swap base per region where OSM coverage is thin.
    | 'regional_overrides' => [ 'poi.default' => [ 'US-MT' => [ ['provider'=>'google_places','role'=>'base'] ] ] ],
    */
    'regional_overrides' => [],
];
```

---

## 3. `LocationProviderRegistry` — resolution contract

```
LocationProviderRegistry {
    // Returns ordered, enabled provider descriptors for a canonical category,
    // applying regional_overrides for the listing's region.
    resolve(canonicalCategory: string, region: string = '*'): ProviderBinding[]

    // Convenience for the enrichment runner: instantiated adapters in role order.
    adaptersFor(canonicalCategory: string, region: string): [{ role, provider, adapter }]

    // For fetch_version (canonical spec §7): a stable hash of the *active* provider set + roles.
    // Changing which providers/roles are active MUST change this hash → cache invalidation.
    capabilityHash(): string
}
```
- Disabled providers are skipped; if no `base` resolves, the highest available role acts as base.
- Bound in `AppServiceProvider` the same way adapters are bound today — the `if-google-else-stub` block is *replaced by* a registry lookup, but **not until Phase 8 wiring stage** (§5).

---

## 4. Adapter contract extension (additive, backward-compatible)

Providers must emit confidence + freshness so the canonical envelope is complete. Extend the normalized POI shape from 7 keys to 9 (additive; existing Google adapter keeps working):

| Existing keys | Added keys |
|---|---|
| category, name, address, latitude, longitude, distance_miles, source | `confidence` (float, per canonical spec §2), `last_refreshed` (timestamp) |

- Google adapter: `confidence` from `user_ratings_total`; `last_refreshed` = fetch time.
- OSM adapter (future): `confidence` = structural base; `rating`/quality keys `null`.
- Flood/School/Boundary/Commute interfaces gain the same two optional keys.
- **Persistence:** additive migration on `property_location_pois` — `confidence`, `provenance_json`, `last_refreshed`, `human_corroborated` (none exist today; `data_source` already covers `source`).

---

## 5. Phasing — what code this proposal touches, and when

| Stage | Artifact | Touches working Google pipeline? |
|---|---|---|
| Spec (now) | This doc + `canonical-field-mapping-spec.md` | **No — docs only** |
| A | `config/location_providers.php` (inert, OSM/etc. `enabled:false`) | No |
| B | `LocationProviderRegistry` + `CanonicalLocationMerger` (new classes, unused by runtime) | No |
| C | Additive migration (confidence/provenance/last_refreshed/human_corroborated) | No (nullable columns) |
| D | Extend adapter shape with optional confidence/freshness keys, populate in Google adapter | Minimal, additive |
| E | Cut `AppServiceProvider` binding over to registry — **Google stays the only enabled provider** | Behavior-preserving swap |
| F (later) | Implement + enable OSM/Geoapify/ORS adapters; flip `enabled` per category | New providers only |

Until stage F, resolution falls through to `google_places` for POI, so **behavior is identical to today**.

---

## 6. How this satisfies the goals

- **OSM/open-data-first:** `poi.default` base = `osm_overpass`; Google demoted to `overlay`/`fallback`.
- **Google as launch + premium/fallback:** `enabled:true` today, `role:overlay` long-term; critical categories (`poi.hospitals`) keep it as base by choice.
- **Add/replace/combine without engine changes:** edit `providers` + `capabilities`; engines read canonical only.
- **Confidence/freshness through adapters:** §4.
- **Precedence + contradiction detection:** canonical spec §6 (merger consumes the resolved role order).
- **Cache invalidation on provider change:** `capabilityHash()` feeds `fetch_version` (canonical spec §7).
- **Licensing + Fair Housing:** `cache_policy`/`license` per provider (§2 block); no demographic/protected fields in the model (canonical spec §8).
