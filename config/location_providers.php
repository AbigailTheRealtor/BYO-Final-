<?php

/*
|--------------------------------------------------------------------------
| Location Intelligence — Provider Registry & Capability Map
|--------------------------------------------------------------------------
|
| Declarative registry that lets the Location Intelligence Engine add,
| replace, or COMBINE data providers by editing configuration rather than
| touching any intelligence engine. Consumed by:
|
|   - App\Services\LocationDna\Providers\LocationProviderRegistry  (resolution)
|   - App\Services\LocationDna\Providers\CanonicalLocationMerger   (precedence/merge)
|
| Contract: docs/canonical-field-mapping-spec.md
| Structure rationale: docs/location-provider-capability-map-proposal.md
|
| STAGE A (this file) is INERT. Nothing in the runtime path reads it yet.
| The existing Google pipeline remains the sole ACTIVE production provider:
| every non-Google/-authoritative provider below is `enabled => false`, so
| once the registry is wired (a later stage) POI resolution still falls
| through to `google_places` — byte-identical to today's behavior.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Provider registry — one descriptor per provider
    |--------------------------------------------------------------------------
    | tier          free | premium
    | adapter       FQCN implementing the relevant *AdapterInterface. May point
    |               at a not-yet-implemented class while `enabled => false`; the
    |               registry never instantiates a disabled provider, so an absent
    |               class is harmless until it is both implemented and enabled.
    | cost_per_1k   USD per 1,000 calls (0.0 for free/self-hosted) — cost-aware
    |               routing/reporting.
    | regions       ['*'] = global, or ISO-3166 regions / US state codes where
    |               this provider is preferred.
    | cache_policy  cacheable | ephemeral | attribution-required
    |               (drives canonical-field-mapping-spec §7 caching + §8 licensing)
    | license       odbl | google-tos | public-domain | geoapify-tos | ors-tos | ...
    | serves        canonical attribute classes this provider can populate:
    |               existence | geometry | rating | quality | hazard | boundary
    |               | commute | geocode
    | enabled       runtime kill switch per provider.
    */
    'providers' => [

        'osm_overpass' => [
            'tier'         => 'free',
            'adapter'      => 'App\\Services\\LocationDna\\OsmOverpassPoiAdapter', // NOT YET IMPLEMENTED
            'cost_per_1k'  => 0.0,
            'regions'      => ['*'],
            'cache_policy' => 'attribution-required',
            'license'      => 'odbl',
            'serves'       => ['existence', 'geometry'],
            'enabled'      => false,
        ],

        'google_places' => [
            'tier'         => 'premium',
            'adapter'      => \App\Services\LocationDna\GooglePlacesPoiAdapter::class, // EXISTS — launch provider
            'cost_per_1k'  => 32.0,
            'regions'      => ['*'],
            'cache_policy' => 'ephemeral', // Google ToS — persist place_id + derived confidence only
            'license'      => 'google-tos',
            'serves'       => ['existence', 'geometry', 'rating', 'quality'],
            'enabled'      => true, // remains the active production provider — do not disturb
        ],

        'geoapify' => [
            'tier'         => 'free',
            'adapter'      => 'App\\Services\\LocationDna\\GeoapifyPoiAdapter', // NOT YET IMPLEMENTED
            'cost_per_1k'  => 0.0,
            'regions'      => ['US'],
            'cache_policy' => 'cacheable',
            'license'      => 'geoapify-tos',
            'serves'       => ['existence', 'geometry'],
            'enabled'      => false,
        ],

        'fema' => [
            'tier'         => 'free',
            'adapter'      => \App\Services\LocationDna\FemaFloodZoneAdapter::class, // EXISTS
            'cost_per_1k'  => 0.0,
            'regions'      => ['US'],
            'cache_policy' => 'cacheable',
            'license'      => 'public-domain',
            'serves'       => ['hazard'],
            'enabled'      => true,
        ],

        'census_tiger' => [
            'tier'         => 'free',
            'adapter'      => \App\Services\LocationDna\CensusTigerBoundaryAdapter::class, // EXISTS
            'cost_per_1k'  => 0.0,
            'regions'      => ['US'],
            'cache_policy' => 'cacheable',
            'license'      => 'public-domain',
            'serves'       => ['boundary'],
            'enabled'      => true,
        ],

        'openrouteservice' => [
            'tier'         => 'free',
            'adapter'      => 'App\\Services\\LocationDna\\OpenRouteServiceCommuteAdapter', // NOT YET IMPLEMENTED
            'cost_per_1k'  => 0.0,
            'regions'      => ['*'],
            'cache_policy' => 'cacheable',
            'license'      => 'ors-tos',
            'serves'       => ['commute'],
            'enabled'      => false,
        ],

        'stub' => [
            'tier'         => 'free',
            'adapter'      => \App\Services\LocationDna\StubPoiLookupAdapter::class, // EXISTS
            'cost_per_1k'  => 0.0,
            'regions'      => ['*'],
            'cache_policy' => 'cacheable',
            'license'      => 'public-domain',
            'serves'       => ['existence', 'geometry', 'commute'],
            'enabled'      => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Capability map — canonical category → ordered provider roles
    |--------------------------------------------------------------------------
    | role: base     supplies the primary value (existence/geometry). One per
    |                category (canonical-field-mapping-spec §6).
    |       overlay  merges additional attributes onto the base (e.g. rating).
    |       fallback used only when higher roles return nothing.
    |
    | The registry resolves in this declared order and skips disabled providers.
    | A `poi.*` category with no explicit entry inherits `poi.default`.
    |
    | Posture: OSM/open-data-first where practical; Google as premium overlay +
    | fallback rather than the long-term base dependency. While OSM/Geoapify are
    | `enabled => false`, POI resolution falls through to `google_places`.
    */
    'capabilities' => [

        'poi.default' => [
            ['provider' => 'osm_overpass',  'role' => 'base'],     // free existence/geometry
            ['provider' => 'geoapify',      'role' => 'fallback'], // free, US
            ['provider' => 'google_places', 'role' => 'overlay'],  // premium: rating/reviews only
        ],

        // Critical category: keep the rated source as base where a wrong/unrated
        // result is most costly. Flip to OSM-base later if desired (one-line change).
        'poi.hospitals' => [
            ['provider' => 'google_places', 'role' => 'base'],
            ['provider' => 'osm_overpass',  'role' => 'fallback'],
        ],

        'hazard.flood_zone'        => [['provider' => 'fema',         'role' => 'base']],
        'boundary.school_district' => [['provider' => 'census_tiger', 'role' => 'base']],
        'boundary.city'            => [['provider' => 'census_tiger', 'role' => 'base']],
        'boundary.zip'             => [['provider' => 'census_tiger', 'role' => 'base']],
        'boundary.county'          => [['provider' => 'census_tiger', 'role' => 'base']],

        'commute' => [
            ['provider' => 'openrouteservice', 'role' => 'base'],
            ['provider' => 'stub',             'role' => 'fallback'],
        ],

        'geocode' => [
            ['provider' => 'google_places', 'role' => 'base'],     // established today
            ['provider' => 'osm_overpass',  'role' => 'fallback'], // Nominatim
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Regional overrides (nationwide expansion)
    |--------------------------------------------------------------------------
    | Per-region replacement of a category's binding list, for regions where the
    | default base (e.g. OSM) has thin coverage. Keyed [category][region].
    |
    |   'poi.default' => [
    |       'US-MT' => [ ['provider' => 'google_places', 'role' => 'base'] ],
    |   ],
    */
    'regional_overrides' => [],
];
