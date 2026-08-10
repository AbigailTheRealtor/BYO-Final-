<?php

/*
|--------------------------------------------------------------------------
| US Census Geocoder — the first non-Google coordinate provider
|--------------------------------------------------------------------------
|
| Consumed by App\Services\Location\Coordinates\Adapters\CensusGeocoderAdapter
| and by nothing else. Every value below was verified against the live service
| on 2026-08-10; the probe results are recorded in the adapter's docblock so a
| future reader can tell what was checked from what was assumed.
|
| INERT BY DEFAULT
| ----------------
| `enabled` defaults to false. The US Census Geocoder needs no API key, which
| removes the accident that normally keeps an unfinished integration quiet: a
| missing credential. Nothing here is secret, so nothing here fails closed on
| its own — this flag is the only thing standing between the adapter and an
| outbound request, which is why it defaults off and why the adapter reads it
| through isAvailable() rather than checking it mid-request.
|
| G3 ships the adapter and stops. It is not on the local ladder, not bound in
| any container, and not referenced by any listing flow.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Master switch
    |--------------------------------------------------------------------------
    | False = the adapter reports itself unavailable and is skipped by the
    | resolver without being called. Turning this on is a rollout decision that
    | belongs with the phase that has something to call it from (G4/G5), not a
    | code change.
    */
    'enabled' => env('CENSUS_GEOCODER_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Endpoint
    |--------------------------------------------------------------------------
    | The "locations" service answers "where is this address" and returns
    | coordinates. The sibling "geographies" service additionally returns census
    | tract/block membership and is deliberately NOT used: this adapter's job is
    | a coordinate, and asking for geography we do not consume would cost a
    | larger response for no gain.
    |
    | No authentication. No API key. Public domain data.
    */
    'base_url' => env(
        'CENSUS_GEOCODER_BASE_URL',
        'https://geocoding.geo.census.gov/geocoder/locations/onelineaddress'
    ),

    /*
    |--------------------------------------------------------------------------
    | Benchmark
    |--------------------------------------------------------------------------
    | Which vintage of the address-range corpus to match against. Verified live
    | at /geocoder/benchmarks?format=json on 2026-08-10:
    |
    |   Public_AR_Current      id 4     current ranges   (service default)
    |   Public_AR_ACS2025      id 8     ACS 2025
    |   Public_AR_LUCA         id 11    LUCA
    |   Public_AR_Census2020   id 2020  Census 2020
    |
    | Current is the right default for live listings: we are locating a property
    | that exists today, not reproducing a historical tabulation. Pinned
    | explicitly rather than relying on the service default so that a change on
    | the Census side shows up as a config diff instead of as coordinates that
    | quietly moved.
    |
    | An unrecognised value is rejected by the service with HTTP 400 and
    | {"errors":["Invalid benchmark in request"]} — a misconfiguration, which the
    | adapter surfaces as a fault rather than as "this address does not exist".
    */
    'benchmark' => env('CENSUS_GEOCODER_BENCHMARK', 'Public_AR_Current'),

    /*
    |--------------------------------------------------------------------------
    | Request timeout (seconds)
    |--------------------------------------------------------------------------
    | Matches the 10s used by the FEMA and TIGERweb adapters. This is a free
    | government service with no published SLA; the ceiling exists so a slow
    | response degrades to "no coordinate" instead of holding a request open.
    */
    'timeout' => (int) env('CENSUS_GEOCODER_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Cache TTL (seconds)
    |--------------------------------------------------------------------------
    | 30 days. Address ranges are revised on a survey cadence measured in
    | months, so a coordinate does not go stale in a week, and the cache key is
    | the unit-free lookup line — every unit in a building shares one entry and
    | therefore one provider call.
    |
    | Successful matches AND definitive misses are both cached: an address the
    | corpus does not contain will still not be there in an hour, and re-asking
    | on every page load is how a free service stops being free. Faults are
    | never cached — see the adapter.
    */
    'cache_ttl' => (int) env('CENSUS_GEOCODER_CACHE_TTL', 2592000),

    /*
    |--------------------------------------------------------------------------
    | Address length ceiling
    |--------------------------------------------------------------------------
    | The service enforces this itself, rejecting anything longer with HTTP 400
    | and {"errors":["Address cannot be empty and cannot exceed 100 characters"]}
    | (verified live, 2026-08-10, with a 101-character address).
    |
    | Mirrored here so the adapter can decline before spending a request on a
    | call it knows will be refused, and — more importantly — so that refusal is
    | reported as its own reason rather than arriving as a generic 400 that
    | looks identical to a real misconfiguration.
    */
    'max_address_length' => (int) env('CENSUS_GEOCODER_MAX_ADDRESS_LENGTH', 100),

];
