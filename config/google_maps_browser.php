<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Browser-side Google Maps — master switch
    |--------------------------------------------------------------------------
    |
    | Governs every Google credential this application emits into a PAGE: the Maps
    | JavaScript SDK loaders, the Location DNA map injector and the Stellar Maps
    | Embed iframe. Off means no SDK tag, no iframe, and no key in the HTML.
    |
    | Fail-safe default: DISABLED, and PARSED STRICTLY like GOOGLE_PLACES_ENABLED.
    | ON: `true`, `1`, `on`, `yes` (any case). OFF: unset, empty, `false`, `0`,
    | `off`, `no` — and anything else. A plain (bool) cast reads `off` and `no` as
    | ON, which is the wrong answer for the switch an operator reaches for while
    | watching a bill.
    |
    | This is also the emergency stop: browser Google can be switched off without
    | deleting a key in the Google Cloud console.
    |
    */
    'enabled' => filter_var(env('GOOGLE_MAPS_BROWSER_ENABLED', false), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true,

    /*
    |--------------------------------------------------------------------------
    | The BROWSER key — a different credential from GOOGLE_PLACES_API_KEY
    |--------------------------------------------------------------------------
    |
    | GOOGLE_PLACES_API_KEY is a SERVER key: it authenticates Nearby Search and
    | Geocoding from this application's own HTTP client, behind the shared
    | admission budgets. It was ALSO being emitted into every page that loads the
    | Maps SDK — including the public Offer Listing detail pages — so anyone could
    | read it from the page source and spend it against our account, outside every
    | server-side ceiling. A key used from both places cannot be restricted to our
    | websites, which is the only restriction that protects a browser key.
    |
    | So this key is separate, and there is NO FALLBACK between them: with this
    | value absent the browser surfaces degrade (they already have a degraded
    | state) rather than reaching for the server credential.
    |
    | It must be restricted in Google Cloud to our exact origins and to the Maps
    | JavaScript / Places / Maps Embed APIs, with its own quotas. Each environment
    | gets its own key; a development key must never be the production one.
    |
    | Absent by default. Nothing here creates, enables or requires a credential.
    |
    */
    'key' => env('GOOGLE_MAPS_BROWSER_KEY'),

];
