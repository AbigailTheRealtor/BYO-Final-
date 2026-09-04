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

        /*
        | The Overture places corpus we host ourselves on the pgsql_spatial cluster.
        |
        | Unlike every other free provider below, this one is NOT a network service:
        | `serves` is answered by an indexed KNN query against a corpus already loaded
        | and activated (`overture-2026-06-17.0-fl`, 29,434 rows, partition
        | `places_p_overture_2026_06_17_0_fl`). There is no endpoint, no credential and
        | no per-call cost — `cost_per_1k` is 0.0 as a fact, not as a free tier.
        |
        | REGIONS IS A REAL CONSTRAINT HERE, NOT A PREFERENCE. Every other descriptor's
        | `regions` says where that provider is PREFERRED; a provider with `['*']` can
        | answer anywhere. This corpus covers Florida and nothing else, so `['US-FL']`
        | states a limit. The registry does not enforce it (its region argument is unused
        | at today's call sites), which is exactly why the adapter enforces it itself and
        | declines an out-of-region coordinate before querying — a Florida grocery store
        | is a wrong answer for a Georgia address, not a distant one.
        |
        | SEVEN CATEGORIES ONLY. The corpus holds grocery_store, restaurant, pharmacy,
        | shopping_center, coffee_shop, gym and gas_station. Beach, school, park, hospital
        | and transit are declared in CanonicalCategoryRegistry against open-data sources
        | that have not been imported; this provider returns nothing for them rather than
        | a substitute. `CorpusPoiCategoryMap` holds that claim and derives it from the
        | corpus taxonomy.
        |
        | ENABLED => FALSE, DELIBERATELY. Activation is a separate, reviewed step: it
        | needs the corpus verified in the target environment and OVERTURE_CORPUS_POI_*
        | set there. Both this flag and `config/overture_corpus_poi.php` must agree — see
        | the two-gates note in that file.
        */
        /*
        | LICENSE — READ THIS BEFORE CHANGING IT.
        |
        | `odbl` WOULD BE WRONG, and it is the wrong answer people reach for because
        | four of Overture's six themes ARE ODbL. Places is not one of them.
        | https://docs.overturemaps.org/attribution/ lists ODbL for Buildings,
        | Transportation, Divisions and Base — and for Places, three permissive
        | licenses instead. The project's own architecture SSOT says the same thing in
        | as many words: "ODbL share-alike does not govern the Places theme"
        | (docs/architecture/MASTER-SPATIAL-INTELLIGENCE-ARCHITECTURE.md §Data sources).
        |
        | Places is a MIXED-LICENSE AGGREGATE:
        |   CDLA-Permissive-2.0  the bulk — Meta, Microsoft, PinMeTo, Krick,
        |                        RenderSEO, DAC, BrightQuery. Attribution required.
        |   Apache-2.0           the Foursquare slice. Requires their NOTICE file be
        |                        included — an obligation no other member imposes.
        |   CC0-1.0              the AllThePlaces slice. No requirements.
        |
        | AND WE CANNOT TELL WHICH ROW IS WHICH. The raw Overture record carries
        | `sources[].dataset`, but `OverturePlaceNormalizer::sourceCount()` COUNTS those
        | datasets and discards their names; the corpus keeps `source_count` (an integer)
        | and `source = 'overture'`, and `attrs` holds only `geometry_type`. Verified
        | against the loaded corpus: 29,434 rows, zero carrying per-source attribution.
        | So a per-row license is not derivable from the corpus as imported.
        |
        | The provenance contract has ONE license string per provider, so that string has
        | to describe the aggregate rather than name a member. Naming CDLA alone would be
        | false for the Foursquare and AllThePlaces rows and would silently drop the
        | Apache-2.0 NOTICE obligation — the one requirement here that could actually be
        | breached. The compound token below claims none of the three exclusively, names
        | all three that can apply, and splits on `+` if anything ever needs to read it.
        | Nothing does today: `license` is carried into `provenance_json` as an audit
        | string and is never parsed, compared or validated.
        |
        | PER-ROW PRECISION IS A CORPUS CHANGE, NOT A CONFIG CHANGE. Recovering it means
        | teaching the importer to retain `sources[].dataset` and re-importing. Out of
        | scope here, and deliberately not faked in the meantime.
        |
        | ATTRIBUTION IS STILL OWED AND IS NOT YET RENDERED ANYWHERE. CDLA-Permissive-2.0
        | and Apache-2.0 both require it. Nothing in this phase displays a POI, so nothing
        | is yet published without it — but surfacing corpus-backed places to users must
        | carry Overture attribution, and that is a prerequisite of activation, not of
        | this merge.
        */
        'overture_corpus' => [
            'tier'         => 'free',
            'adapter'      => \App\Services\LocationDna\OvertureCorpusPoiAdapter::class, // EXISTS — local corpus
            'cost_per_1k'  => 0.0,
            'regions'      => ['US-FL'],
            'cache_policy' => 'cacheable',
            'license'      => 'cdla-permissive-2.0+apache-2.0+cc0-1.0',
            'serves'       => ['existence', 'geometry'],
            'enabled'      => false,
        ],

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

        // Declared FIRST and as `base`, so that enabling the corpus makes it the effective
        // base outright rather than leaving the choice to which other providers happen to
        // be on. While it is disabled the registry filters it out entirely and resolution
        // is byte-identical to before it was listed.
        //
        // Note what this ordering guarantees at activation: `effectiveBase('poi.default')`
        // returns the corpus, and NearbyPoiFetcherFactory constructs only the effective
        // base — so a corpus miss is a miss, never a silent fall-through to Google. Google
        // stays declared as `overlay` (its historical role for rating/reviews) and is not
        // a fallback for existence.
        'poi.default' => [
            ['provider' => 'overture_corpus', 'role' => 'base'],     // local corpus, $0, US-FL only
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
