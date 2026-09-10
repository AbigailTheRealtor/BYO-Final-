<?php

return [

    /*
    |--------------------------------------------------------------------------
    | BidYourOffer Explore — master gate
    |--------------------------------------------------------------------------
    |
    | Default OFF, and off is the shipped state. With the gate closed every
    | Explore route 404s: the consumer surface does not exist, the viewport API
    | does not answer, and nothing in resources/js/explore is loaded.
    |
    | Mirrors CheckMatchCheckEnabled / CheckAgentAiV2Enabled — one master flag,
    | one middleware, 404 rather than a disabled-looking page, because a feature
    | that is off should be invisible rather than advertised.
    |
    */

    'enabled' => (bool) env('EXPLORE_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | VOW — Property Intelligence
    |--------------------------------------------------------------------------
    |
    | SEPARATE from the master gate, and it must stay separate. The master gate
    | governs a public IDX surface built entirely from data this application is
    | already authorised to display. This one governs an authenticated tier that
    | does not exist yet and for which NO approval, dataset, credential or
    | consumer-registration flow is present in this repository.
    |
    | It ships false and `VowAvailability` refuses regardless of its value, so
    | turning it on does not turn anything on. That redundancy is deliberate: a
    | flag is the wrong place for the only thing standing between an unapproved
    | licence tier and a consumer. See App\Services\Explore\Vow\VowAvailability.
    |
    */

    'vow_enabled' => (bool) env('EXPLORE_VOW_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Google Photorealistic 3D — browser credential
    |--------------------------------------------------------------------------
    |
    | The renderer is the Google Maps JavaScript API `maps3d` library
    | (Map3DElement). It needs a BROWSER key, which is a different credential
    | from GOOGLE_PLACES_API_KEY — that one is a server key used by address
    | validation and POI lookup, it must never be emitted into a page, and this
    | must never fall back to it.
    |
    | Absent by default. Readiness is checked INDEPENDENTLY of the master gate
    | (see ExploreGoogleConfig), so "Explore is off" and "Explore is on but has
    | no map credential" are two distinguishable states and the second renders a
    | deliberate unavailable panel rather than a blank grey box.
    |
    | No Places, Routes, Roads, Directions or Geocoding library is requested.
    | `libraries` is pinned here so adding one is a visible config change.
    |
    */

    'google' => [
        'browser_key' => env('EXPLORE_GOOGLE_MAPS_BROWSER_KEY'),
        'map_id'      => env('EXPLORE_GOOGLE_MAPS_MAP_ID'),
        'api_version' => env('EXPLORE_GOOGLE_MAPS_VERSION', 'alpha'),
        'libraries'   => ['maps3d'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default camera
    |--------------------------------------------------------------------------
    |
    | Where Explore opens. St. Petersburg / Tampa Bay, which is where this
    | dataset's inventory actually is — 1,225 cached Stellar records cluster
    | around 27.6–28.0 N, -82.3 to -82.8 W. Opening anywhere else shows an
    | empty neighbourhood and reads as a broken feature.
    |
    */

    'default_camera' => [
        'latitude'  => (float) env('EXPLORE_DEFAULT_LAT', 27.7676),
        'longitude' => (float) env('EXPLORE_DEFAULT_LNG', -82.6403),
        'altitude'  => (float) env('EXPLORE_DEFAULT_ALTITUDE', 900.0),
        'tilt'      => (float) env('EXPLORE_DEFAULT_TILT', 67.5),
        'heading'   => (float) env('EXPLORE_DEFAULT_HEADING', 0.0),
        'range'     => (float) env('EXPLORE_DEFAULT_RANGE', 2500.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Viewport query limits
    |--------------------------------------------------------------------------
    |
    | `max_results` is what a single viewport response may contain. `overfetch`
    | is how many rows the SQL may read before server-side eligibility filtering,
    | expressed as a multiple of the requested limit: eligibility depends on
    | raw_json (display permissions and lease frequency live only there), so it
    | cannot be a WHERE clause, and a bare SQL LIMIT would silently shrink a
    | page whenever ineligible rows sorted first.
    |
    | `max_span_degrees` refuses a bounding box large enough to be a whole-state
    | scrape rather than a neighbourhood. A refusal is an error, never a silent
    | clamp — a clamped bbox returns markers for somewhere the user is not
    | looking at.
    |
    */

    'viewport' => [
        'max_results'      => (int) env('EXPLORE_MAX_RESULTS', 150),
        'result_ceiling'   => 250,
        'overfetch'        => 3,
        'max_span_degrees' => (float) env('EXPLORE_MAX_SPAN_DEGREES', 1.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Statuses eligible for the public surface
    |--------------------------------------------------------------------------
    |
    | 'Active' alone, and that is not a placeholder for a longer list.
    |
    | It is the status this application already treats as its consumer-facing
    | inventory everywhere else: both OData filter builders emit
    | `StandardStatus eq 'Active'` unconditionally, and
    | StellarPropertyDetailController refuses anything else. Explore adopting a
    | wider set would make Explore the first surface publishing a market status
    | nobody cleared it to publish.
    |
    | 'Coming Soon' is deliberately excluded even though the live probe confirms
    | it: a pre-marketing status is exactly the category whose public
    | distribution is governed by rules this repository has no record of.
    | 'Active Under Contract' and 'Pending' are excluded for the narrower reason
    | that Explore is a discovery surface for property a consumer can act on.
    |
    | Compared against StandardStatus only — never MlsStatus, which is a second
    | vocabulary that disagrees on the same record (Closed ↔ Sold).
    |
    */

    'public_statuses' => ['Active'],

    /*
    |--------------------------------------------------------------------------
    | Transaction type ← PropertyType
    |--------------------------------------------------------------------------
    |
    | The authoritative sale/rent rule, as an EXACT-MATCH allowlist over the RESO
    | PropertyType values this dataset actually emits. All seven appear both in
    | tests/fixtures/mls/bridge/ and in the live cache.
    |
    | Exact match, not substring: 'Commercial Sale' and 'Commercial Lease' differ
    | by one word and a substring rule keyed on 'commercial' puts a building for
    | sale under FOR RENT. PropertyTypeVocabulary uses substring matching because
    | its job is to pick a FORM vocabulary; this decides whether a price is a
    | purchase price or a rent, and it must not be lenient.
    |
    | An unrecognised PropertyType belongs to NEITHER list and is excluded from
    | the public surface entirely. Fail closed: a type we cannot classify is one
    | whose ListPrice we cannot label.
    |
    */

    'transaction_types' => [
        'sale' => [
            'Residential',
            'Income',
            'Commercial Sale',
            'Vacant Land',
            'Business Opportunity',
        ],
        'rent' => [
            'Residential Lease',
            'Commercial Lease',
        ],
    ],

];
