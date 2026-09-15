<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Google Geocoding — master switch (server-side)
    |--------------------------------------------------------------------------
    |
    | Governs every server-side request to the Google Geocoding API
    | (`maps/api/geocode/json`): the Location DNA geocode step, the
    | `app:geocode-seller-landlord-listings` backfill, and the Tenant address
    | pickers that fill in city / state / ZIP / county from a chosen address.
    |
    | Enforced where every one of those requests passes — the admission
    | middleware on the shared server HTTP client
    | (App\Support\Google\GoogleProviderAdmissionMiddleware) — so a caller cannot
    | forget it. A present GOOGLE_PLACES_API_KEY is NOT permission to geocode.
    |
    | Deliberately separate from GOOGLE_PLACES_ENABLED. That switch governs Nearby
    | Search; sharing it would mean switching Location DNA's POIs on also switched
    | tenant geocoding on, and the reverse. The Location DNA geocode step and the
    | backfill command still require GOOGLE_PLACES_ENABLED as well, as they always
    | have — this switch is an additional gate there, never a replacement one.
    |
    | Fail-safe default: DISABLED. PARSED STRICTLY, FAILING CLOSED, exactly like
    | GOOGLE_PLACES_ENABLED. ON: `true`, `1`, `on`, `yes` (any case). OFF: unset,
    | empty, `false`, `0`, `off`, `no` — and anything else.
    |
    | Off means ZERO Geocoding requests. The Tenant pickers then leave city /
    | state / ZIP / county for the user to fill in, and Location DNA records the
    | coordinate as unresolved ('skipped'), never as a failed lookup.
    |
    | Browser-side geocoding (`google.maps.Geocoder` in the Maps JavaScript API)
    | never reaches this server and is not governed here — that needs Google Cloud
    | controls.
    |
    */
    'enabled' => filter_var(env('GOOGLE_GEOCODING_ENABLED', false), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true,

    /*
    |--------------------------------------------------------------------------
    | Geocoding request ceilings — HARD, enforced before each request
    |--------------------------------------------------------------------------
    |
    | One unit per outbound Geocoding HTTP request, admitted immediately BEFORE
    | it is sent, through the shared ProviderRequestBudget. Request 26 in a UTC
    | clock hour and request 101 in a UTC day are refused without reaching Google.
    | A stored coordinate or a cached geocode sends nothing and costs nothing; a
    | retry is a new request and is admitted again; a request that was sent and
    | failed still counted.
    |
    | Its OWN allowance: independent of the Nearby Search ceilings in
    | config/google_places.php. Spending one never spends the other.
    |
    | A zero, negative or malformed value is a ceiling of zero: it blocks
    | Geocoding entirely rather than unleashing it.
    |
    | A code-level backstop, not a replacement for Google Cloud quotas and billing
    | alerts on the Geocoding API.
    |
    */
    'hourly_limit' => (int) env('GOOGLE_GEOCODING_HOURLY_LIMIT', 25),
    'daily_limit'  => (int) env('GOOGLE_GEOCODING_DAILY_LIMIT', 100),

];
