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

];
