<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Google Places — Master Kill Switch
    |--------------------------------------------------------------------------
    |
    | When false, EVERY Google Places Nearby Search code path returns safe empty
    | results WITHOUT making any HTTP call. This is the primary blast-radius guard
    | added after the 2026-07-05 NearbySearch incident (see
    | docs/investigations/Google-Places-Root-Cause-Analysis.md).
    |
    | Fail-safe default: DISABLED. The Places API only runs when an operator
    | explicitly sets GOOGLE_PLACES_ENABLED=true in the environment — production
    | must opt in; local and testing stay off unless deliberately enabled for a
    | provider-mocked test.
    |
    */
    'enabled' => (bool) env('GOOGLE_PLACES_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Application-level Circuit Breaker (request caps)
    |--------------------------------------------------------------------------
    |
    | Hard ceilings on the number of Google Places Nearby Search requests this
    | application will attempt, regardless of caller. Counters are kept in the
    | cache store keyed by calendar day and clock hour. When a cap is reached the
    | gate stops calling Google, logs/alerts, and returns empty results.
    |
    | These are a code-level backstop; they do NOT replace the Google Cloud
    | console quota + budget caps, which must also be configured before the API
    | is ever re-enabled.
    |
    */
    'daily_limit'  => (int) env('GOOGLE_PLACES_DAILY_LIMIT', 100),
    'hourly_limit' => (int) env('GOOGLE_PLACES_HOURLY_LIMIT', 25),

];
