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
    | PARSED STRICTLY, FAILING CLOSED. ON: `true`, `1`, `on`, `yes` (any case).
    | OFF: unset, empty, `false`, `0`, `off`, `no` — and anything else. A plain
    | (bool) cast reads `off` and `no` as ON, which is the wrong answer for the
    | switch an operator reaches for mid-incident.
    |
    */
    'enabled' => filter_var(env('GOOGLE_PLACES_ENABLED', false), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true,

    /*
    |--------------------------------------------------------------------------
    | Nearby Search request ceilings — HARD, enforced before each request
    |--------------------------------------------------------------------------
    |
    | Hard ceilings on the number of Google Places NEARBY SEARCH requests this
    | application will send, regardless of caller: one unit per outbound HTTP
    | request, admitted immediately BEFORE it is sent by
    | App\Support\Google\GoogleProviderAdmissionMiddleware on the shared server
    | HTTP client, through the shared ProviderRequestBudget. Request 26 in an hour
    | and request 101 in a day are refused without reaching Google. A cache hit
    | sends nothing and costs nothing; a retry is a new request and is admitted
    | again; a request that was sent and failed still counted.
    |
    | A refusal is NOT an empty result: Location DNA records a failed POI run
    | (the missing categories are fetched on a later run) and the search-area
    | lookup reports the provider as unavailable without caching the answer.
    |
    | NEARBY SEARCH ONLY. Places Autocomplete and Geocoding are not governed by
    | these numbers and are not yet budgeted server-side. Browser-side Places
    | (the Maps JavaScript API) never reaches this server and needs Google Cloud
    | controls instead.
    |
    | A zero, negative or malformed value is a ceiling of zero: it blocks Nearby
    | Search entirely rather than unleashing it.
    |
    | These are a code-level backstop; they do NOT replace the Google Cloud
    | console quota + budget caps, which must also be configured before the API
    | is ever re-enabled.
    |
    */
    'daily_limit'  => (int) env('GOOGLE_PLACES_DAILY_LIMIT', 100),
    'hourly_limit' => (int) env('GOOGLE_PLACES_HOURLY_LIMIT', 25),

];
