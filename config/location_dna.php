<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Flood Zone FEMA API — Bounding Area Threshold
    |--------------------------------------------------------------------------
    |
    | Maximum allowed bounding area before the FloodZoneLookupService skips
    | the FEMA NFHL API call entirely. The value is expressed in SQUARE DEGREES
    | of latitude × longitude — NOT in square miles or square kilometers.
    |
    | Important: square degrees are not geographically consistent across
    | latitudes. One degree of longitude is narrower at higher latitudes
    | (≈ 69 mi at the equator, ≈ 54 mi at latitude 38°). For Florida (≈ 25-31°N)
    | one longitudinal degree ≈ 60-62 statute miles, so:
    |
    |   1 sq-degree  ≈ 69 mi × 61 mi  ≈ 4,200 sq miles
    |   2 sq-degrees ≈ roughly a 1° × 2° rectangle (~8,400 sq miles)
    |
    | The default value of 2.0 is calibrated for Florida use:
    |   - A single large Florida county (e.g. Alachua ≈ 0.35 sq-deg): ✔ allowed
    |   - Two adjacent large counties: ✔ allowed
    |   - A multi-county search spanning 3°+ bbox: ✗ skipped (excessive FEMA load)
    |
    | To tune the threshold set LOCATION_DNA_FLOOD_ZONE_MAX_AREA in your .env.
    | Always prefer a tighter (smaller) value rather than a looser one — the
    | FEMA NFHL endpoint is a shared public service that should not be hammered
    | with continent-scale bounding-box queries.
    |
    */
    'flood_zone_max_area_sq_degrees' => (float) env('LOCATION_DNA_FLOOD_ZONE_MAX_AREA', 2.0),

    /*
    |--------------------------------------------------------------------------
    | School District Census TIGER/Line API
    |--------------------------------------------------------------------------
    |
    | Configuration for the CensusSchoolDistrictAdapter and SchoolDistrictLookupService.
    |
    | school_district_endpoint         — Census TIGER School Districts ArcGIS REST URL.
    |
    | school_district_timeout          — HTTP request timeout in seconds (default 15).
    |
    | school_district_cache_ttl        — Cache TTL in seconds for successful responses.
    |                                    Default: 86400 (24 hours). School district
    |                                    boundaries rarely change; a long TTL is safe.
    |
    | school_district_max_area_sq_degrees — Maximum bounding area before the lookup
    |                                       is skipped entirely. Expressed in square
    |                                       degrees (lat × lng). Default 2.0 matches
    |                                       the flood zone threshold — see the flood
    |                                       zone comment above for calibration notes.
    |
    */
    'school_district_endpoint'             => env(
        'LOCATION_DNA_SCHOOL_DISTRICT_ENDPOINT',
        'https://tigerweb.geo.census.gov/arcgis/rest/services/TIGERweb/School_Districts/MapServer/0/query'
    ),
    'school_district_timeout'              => (int)   env('LOCATION_DNA_SCHOOL_DISTRICT_TIMEOUT',      15),
    'school_district_cache_ttl'            => (int)   env('LOCATION_DNA_SCHOOL_DISTRICT_CACHE_TTL',    86400),
    'school_district_max_area_sq_degrees'  => (float) env('LOCATION_DNA_SCHOOL_DISTRICT_MAX_AREA',     2.0),

    /*
    |--------------------------------------------------------------------------
    | Commute Time Lookups
    |--------------------------------------------------------------------------
    |
    | Configuration for the CommuteTimeLookupService and its adapter.
    |
    | provider        — Selects the active adapter implementation. Supported
    |                   values: 'stub' (default, no HTTP calls). Future phases
    |                   will add 'google', 'mapbox', 'here', etc. Changing
    |                   this env variable alone is sufficient to swap providers;
    |                   no code edits are required.
    |
    | timeout         — HTTP request timeout in seconds (used by real provider
    |                   adapters; ignored by the stub adapter).
    |
    | cache_ttl       — How long (in seconds) successful commute-time results
    |                   are cached. Default: 86400 (24 hours).
    |
    | max_destinations — Maximum number of destinations accepted in a single
    |                    resolve() call. Excess entries are silently truncated
    |                    with a Log::warning.
    |
    */
    'commute_time' => [
        'provider'         => env('LOCATION_DNA_COMMUTE_PROVIDER', 'stub'),
        'timeout'          => (int) env('LOCATION_DNA_COMMUTE_TIMEOUT', 10),
        'cache_ttl'        => (int) env('LOCATION_DNA_COMMUTE_CACHE_TTL', 86400),
        'max_destinations' => (int) env('LOCATION_DNA_COMMUTE_MAX_DESTINATIONS', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Buyer/Tenant POI Distance Lookup — Provider & Limits
    |--------------------------------------------------------------------------
    |
    | Configuration for the PoiDistanceLookupService, which resolves points of
    | interest for buyer/tenant search area geometries (not property coordinates).
    |
    | provider
    |   The POI data provider to use. Currently only 'google' is supported.
    |   When the configured API key is absent the service automatically falls
    |   back to StubPoiLookupAdapter, which returns empty results without error.
    |
    | timeout
    |   HTTP request timeout in seconds for outbound provider API calls.
    |
    | cache_ttl
    |   Seconds to cache a lookup result keyed by geometry + categories.
    |   Default 86400 (24 hours).
    |
    | max_radius_miles
    |   Upper bound on the search radius in miles. Any input radius exceeding
    |   this value is silently capped. Polygon centroids always use this value
    |   as the search radius. Default 25 miles.
    |
    | category_result_limit
    |   Maximum number of results returned per category per lookup call.
    |   Default 5.
    |
    */
    'poi' => [
        'provider'              => env('LOCATION_DNA_POI_PROVIDER', 'google'),
        'timeout'               => (int) env('LOCATION_DNA_POI_TIMEOUT', 5),
        'cache_ttl'             => (int) env('LOCATION_DNA_POI_CACHE_TTL', 86400),
        'max_radius_miles'      => (int) env('LOCATION_DNA_POI_MAX_RADIUS_MILES', 25),
        'category_result_limit' => (int) env('LOCATION_DNA_POI_CATEGORY_RESULT_LIMIT', 5),
    ],

];
