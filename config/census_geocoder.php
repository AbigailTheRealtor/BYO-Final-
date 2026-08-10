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

/**
 * A ceiling that may legitimately be "none".
 *
 * `(int) null` is 0, and a cap of 0 would block every request rather than
 * permitting them all — the exact inverse of what an unset value means. This
 * keeps that footgun in one place.
 */
$capOrNull = static function ($value): ?int {
    return ($value === null || $value === '' || $value === 'null') ? null : (int) $value;
};

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

    /*
    |--------------------------------------------------------------------------
    | Request ceilings (G4)
    |--------------------------------------------------------------------------
    | Operational discipline that is deliberately independent of price.
    |
    | The Census Geocoder is free and publishes no rate limit, which is exactly
    | why these exist. Cost is not the only thing a runaway loop spends: an
    | observer firing per save, a queue that retries, or a page that resolves on
    | every render can each turn one user action into thousands of requests.
    | Against a paid provider that produces a visible bill. Against a free one it
    | produces nothing at all until the Bureau stops answering us — damage to a
    | shared public service and to our own access, neither of which is undone by
    | noticing quickly.
    |
    | The defaults are conservative on purpose. 500/hour and 5,000/day is far
    | more than any legitimate current workload — nothing calls this adapter at
    | all today — and far less than a loop would reach in minutes. They are a
    | backstop against a bug, not a capacity plan; raise them when a real
    | workload needs it, with evidence from telemetry.
    |
    | null disables a ceiling entirely. Prefer a high number to null: a ceiling
    | you can see in config is easier to reason about than one that is absent.
    */
    'hourly_cap' => $capOrNull(env('CENSUS_GEOCODER_HOURLY_CAP', 500)),
    'daily_cap'  => $capOrNull(env('CENSUS_GEOCODER_DAILY_CAP', 5000)),

    /*
    |--------------------------------------------------------------------------
    | Circuit breaker (G4)
    |--------------------------------------------------------------------------
    | When the provider is down, each further request costs a full timeout to
    | learn what the previous one already established. Five faults inside ten
    | minutes opens the circuit for five minutes, during which no request is
    | attempted and the rung fails closed.
    |
    | Five rather than one: an isolated timeout is normal internet weather and
    | must not take the provider out of service. Five inside a ten-minute window
    | is a pattern rather than noise.
    |
    | Five minutes rather than an hour: long enough for a transient outage to
    | pass and for retry pressure on a struggling service to drop, short enough
    | that a brief blip does not disable geocoding for the rest of the day.
    |
    | The local rungs are unaffected by any of this — an open Census circuit
    | must never stop a coordinate we already hold from being returned.
    */
    'breaker' => [
        'failure_threshold' => (int) env('CENSUS_GEOCODER_BREAKER_THRESHOLD', 5),
        'cooldown_seconds'  => (int) env('CENSUS_GEOCODER_BREAKER_COOLDOWN', 300),
        'window_seconds'    => (int) env('CENSUS_GEOCODER_BREAKER_WINDOW', 600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Ambiguity cache TTL (G4)
    |--------------------------------------------------------------------------
    | An ambiguous match is a real, deterministic answer about this address —
    | ask again in a minute and the corpus returns the same two candidates — so
    | re-requesting on every page render would spend the budget learning the
    | same thing repeatedly.
    |
    | But it is not as settled as a clean miss. Ambiguity is usually the symptom
    | of a thin address (a missing ZIP, a fixable typo), and the corpus is
    | revised on a survey cadence; caching it for the full 30 days would keep
    | answering "we cannot tell" long after either changed. One day is the
    | compromise: cheap enough to stop a loop, short enough that a fix is picked
    | up the next day without an operator having to know a cache exists.
    */
    'ambiguous_cache_ttl' => (int) env('CENSUS_GEOCODER_AMBIGUOUS_CACHE_TTL', 86400),

];
