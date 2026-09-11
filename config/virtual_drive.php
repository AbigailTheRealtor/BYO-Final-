<?php

/*
|--------------------------------------------------------------------------
| Virtual Drive — street-level provider comparison PROOF
|--------------------------------------------------------------------------
|
| Apple Look Around (MapKit JS) against Google Street View (Maps JavaScript
| API), over the same stored MLS listings and the same listing UI. This is an
| internal, development-only technical proof. It is NOT a feature, NOT a rollout
| surface, and nothing in production may reach it.
|
| That last property does not rest on the flag below. VirtualDriveProofGate
| refuses every environment outside local / development / testing BEFORE it
| reads the flag, so switching the flag on a production host changes nothing.
|
| The proof reads stored listings only (bridge_properties, through Explore's
| eligibility policy and projection allow-list). It sends no Bridge request,
| runs no discovery, and writes nothing.
|
*/

return [

    /*
    | PARSED STRICTLY, FAILING CLOSED — the same rule as EXPLORE_GOOGLE_3D_ENABLED.
    | ON: `true`, `1`, `on`, `yes` (any case). OFF: unset, empty, `false`, `0`,
    | `off`, `no`, and anything else. A (bool) cast reads `off` as ON.
    */
    'proof_enabled' => filter_var(env('VIRTUAL_DRIVE_PROOF_ENABLED', false), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true,

    'apple' => [
        /*
        | A MapKit JS token (a JWT) created in an Apple Developer Program account
        | and restricted to the proof's origin. MapKit JS hands it to Apple from
        | the page, so it is a browser credential by design — but it is still
        | supplied by the environment and never committed. Absent by default:
        | with no token the Apple library is never loaded.
        */
        'mapkit_token' => env('VIRTUAL_DRIVE_MAPKIT_JS_TOKEN'),
        /*
        | MapKit JS 6 — the version Apple's current Look Around sample loads. Its
        | LookAround constructor accepts a plain CoordinateData object, so the
        | stored MLS coordinate goes straight in with no service call.
        */
        'library_url'  => 'https://cdn.apple-mapkit.com/mk/6/mapkit.core.js',
    ],

    'google' => [
        /*
        | Its own browser key, referrer-restricted and API-restricted to the Maps
        | JavaScript API. NEVER GOOGLE_PLACES_API_KEY (a server key that must not
        | be emitted into a page) and never EXPLORE_GOOGLE_MAPS_BROWSER_KEY (which
        | carries Explore's quota). No fallback to either. Absent by default:
        | with no key the Maps JavaScript API is never loaded.
        */
        'browser_key' => env('VIRTUAL_DRIVE_GOOGLE_MAPS_BROWSER_KEY'),
        'api_version' => env('VIRTUAL_DRIVE_GOOGLE_MAPS_VERSION', 'weekly'),
    ],

    /*
    | The curated test set, in Previous/Next order. Stellar ListingKeys —
    | identifiers that already appear in /stellar/property/{key} URLs, not
    | secrets. Chosen in the audit from real stored MLS rows:
    |
    |   1. FOR SALE  condominium, Stones Throw Circle N, St. Petersburg
    |   2. FOR RENT  condominium, same complex, 145 m from (1)
    |   3. FOR RENT  single-family house, Manasota Key Road, Englewood
    |   4. FOR RENT  single-family house next door to (3), 33 m apart
    |   5. FOR RENT  condominium, Siesta Bayside Drive, Sarasota — the
    |                shared-coordinate test (see below)
    |
    | (3) and (4) are the "which house?" test: two adjacent homes whose signs
    | must not be confused. A key that is missing or ineligible where the proof
    | runs is reported as unavailable, never substituted.
    */
    'test_listing_keys' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'VIRTUAL_DRIVE_TEST_LISTING_KEYS',
        'b138f872adb144eb49ba30da22d19829,f238599a97693d7d73868369bcd1d9d9,c1382a833198014114a52e2e737905ad,2f2ae217f92b0696f73f0e68e51bbb87,bfba16667d0ef3b5323c5d5269b82f9e'
    ))))),

    /*
    | The shared-coordinate (condo) test. None of the four homes above shares a
    | coordinate with another listing, so none of them can show what happens
    | when several units sit on one point. This unit can: in stored data 31
    | active Siesta Bayside Drive rentals share one identical coordinate and 5
    | more sit about a metre away. The nearby query around it brings them in.
    | Identified here so the comparison page can label it; it is still a real,
    | eligible stored listing like the other four.
    */
    'shared_coordinate_listing_key' => env('VIRTUAL_DRIVE_SHARED_COORDINATE_LISTING_KEY', 'bfba16667d0ef3b5323c5d5269b82f9e'),

    /*
    | The nearby query a camera move may trigger — against OUR stored rows only.
    | Small on purpose: a proof, not an inventory browser.
    */
    'nearby' => [
        'radius_meters'     => 400,
        'max_radius_meters' => 800,
        'max_results'       => 12,
    ],

];
