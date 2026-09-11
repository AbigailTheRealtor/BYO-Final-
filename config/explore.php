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

        /*
        | Independent kill switch for the 3D renderer.
        |
        | THE ONE EMERGENCY STOP THAT DID NOT EXIST. `EXPLORE_ENABLED` takes the
        | whole surface down and `EXPLORE_DISCOVERY_ENABLED` stops Stellar
        | traffic, but until this flag the only way to stop Google was to delete
        | the browser key — which is a credential change, not an operational one,
        | and it takes the map down for everyone with no record of why.
        |
        | Off means the loader NEVER RUNS: no <script> is inserted and
        | maps.googleapis.com is never contacted. It deliberately does not mean
        | "load Google and then hide the map" — the expensive provider must be
        | untouched when it is switched off, or the switch protects nothing.
        |
        | Defaults TRUE because it is an emergency stop rather than a rollout
        | dial: the feature is already gated by EXPLORE_ENABLED and by the
        | credential's own absence, and a kill switch that ships killed is a
        | third thing to remember rather than a lever to pull. Same posture as
        | REQUIRED_PRODUCTION_FLAGS_ENFORCED.
        */
        'enabled' => (bool) env('EXPLORE_GOOGLE_3D_ENABLED', true),

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
    | Viewport discovery — CURRENT Stellar inventory
    |--------------------------------------------------------------------------
    |
    | Without this, Explore can only render Stellar records some earlier
    | workflow happened to import, so a neighbourhood nobody had searched looked
    | empty. That is a statement about our cache presented as a statement about
    | the market.
    |
    | With it, a viewport request asks the application's ONE existing MLS
    | ingestion pipeline — LazyBridgeImportService, the same advisory lock, fetch
    | cache, pagination, normalizer and Location DNA dispatch that the criteria
    | searches use — for the current eligible listings in that area. Explore adds
    | no client, no importer and no storage of its own.
    |
    | SHIPS FALSE, AND FAILS CLOSED, for the same reason `mls_sync.enabled` does:
    | deploying this code must not by itself begin unattended traffic to a
    | third-party provider. Merging and activating are two decisions.
    |
    | With it off, Explore serves the shared MLS cache and SAYS SO in the
    | response (`discovery.status = "disabled"`), because a cache-only answer
    | must not be mistaken for a complete one.
    |
    */

    'discovery' => [

        'enabled' => (bool) env('EXPLORE_DISCOVERY_ENABLED', false),

        /*
        | Tile size, in degrees, that a discovery bounding box is snapped
        | OUTWARDS to before it is hashed into a fetch-cache key.
        |
        | THIS IS WHAT MAKES CACHE REUSE REAL. The fetch cache is keyed on the
        | payload, so an unsnapped viewport mints a new key on every pixel of
        | pan and every camera nudge becomes a provider request. Snapped,
        | neighbouring viewports share one entry and a pan within a tile costs
        | nothing.
        |
        | 0.05° is roughly 5.5 km of latitude — comfortably larger than a
        | street-level viewport, comfortably smaller than the 1.0° span ceiling.
        | Larger tiles mean fewer, bigger passes; smaller tiles mean more,
        | cheaper ones that are likelier to hit a pagination ceiling.
        */
        'tile_degrees' => (float) env('EXPLORE_DISCOVERY_TILE_DEGREES', 0.05),

        /*
        | Per-pass pagination ceilings, CLAMPED DOWNWARDS against the global
        | BRIDGE_LAZY_* envelope by the importer — a call site can lower a spend
        | limit, never raise one.
        |
        | Lower than the criteria-search defaults on purpose: that pipeline runs
        | on a results page somebody is waiting for, while this runs inside a
        | request made as somebody moves a camera. 5 pages × 200 records is
        | 1,000 listings per tile, well beyond any 0.05° tile in this market.
        */
        'max_pages'   => (int) env('EXPLORE_DISCOVERY_MAX_PAGES', 5),
        'max_records' => (int) env('EXPLORE_DISCOVERY_MAX_RECORDS', 500),

    ],

    /*
    |--------------------------------------------------------------------------
    | Provider request budget — the ceiling on what Explore can spend
    |--------------------------------------------------------------------------
    |
    | `throttle:120,1` on the data routes bounds REQUESTS, not PROVIDER SPEND.
    | One unfiltered viewport request can cost up to five provider pages per
    | transaction type, so a caller staying comfortably inside that throttle
    | could reach roughly 72,000 Bridge requests an hour by traversing distinct
    | cold tiles. Tile snapping and the fetch cache make REPEAT visits free;
    | nothing made DISTINCT tiles bounded. These ceilings do.
    |
    | The accounting is entirely
    | App\Services\Location\Coordinates\Guards\ProviderRequestBudget — the
    | existing provider-neutral component, asked at two scopes. No second budget
    | system was written, and none should be: two mechanisms counting "a
    | request" would eventually disagree about what one is.
    |
    | THE TWO SCOPES FAIL IN OPPOSITE DIRECTIONS. The actor ceiling stops one
    | browser traversing unlimited tiles. The global ceiling stops what the
    | actor ceiling cannot see — many actors, or one actor arriving from many
    | addresses — and is the ceiling that would actually have caught the
    | ~16,000-request incident this work exists because of.
    |
    | Deliberately conservative, and deliberately not a capacity plan. 600
    | requests an hour is far more than a real user browsing a map generates
    | through a 60-minute tile cache, and far less than an unbudgeted actor can
    | reach. Raise them from telemetry (`explore_provider` log lines), not from
    | optimism.
    |
    | There is NO WAY TO CONFIGURE "unlimited". A zero, negative, missing or
    | non-numeric value falls back to the shipped default rather than to no
    | ceiling: an unbudgeted public path to a paid provider is the failure being
    | fixed, and a config value that restores it is that failure with an extra
    | step.
    |
    */

    'provider_budget' => [

        /*
        | The guard itself. Defaults TRUE, and switching it OFF does not
        | unleash traffic — ExploreProviderBudget treats a disabled guard as
        | "do not call the provider", so this is a second way to stop spending
        | and never a way to start it.
        */
        'enabled' => (bool) env('EXPLORE_PROVIDER_BUDGET_ENABLED', true),

        /*
        | Emergency stop for outbound Stellar/Bridge traffic caused by Explore,
        | leaving the rest of Explore and the whole of the application serving.
        | Distinct from EXPLORE_DISCOVERY_ENABLED only in intent: that one is
        | the feature gate, this one is the thing you set at 2am.
        */
        'kill_switch' => (bool) env('EXPLORE_PROVIDER_KILL_SWITCH', false),

        // Ceiling across every caller. The bill's backstop.
        'global_hourly' => (int) env('EXPLORE_PROVIDER_GLOBAL_HOURLY', 600),
        'global_daily'  => (int) env('EXPLORE_PROVIDER_GLOBAL_DAILY', 5000),

        // Ceiling per actor — `user id, else IP`, the identity every throttled
        // route in this application already uses. Nothing new is fingerprinted.
        'actor_hourly' => (int) env('EXPLORE_PROVIDER_ACTOR_HOURLY', 60),
        'actor_daily'  => (int) env('EXPLORE_PROVIDER_ACTOR_DAILY', 300),

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
