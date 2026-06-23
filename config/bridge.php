<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bridge API credentials
    |--------------------------------------------------------------------------
    */
    'dataset' => env('BRIDGE_DATASET'),
    'token'   => env('BRIDGE_SERVER_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Lazy-import TTL
    |--------------------------------------------------------------------------
    | How many minutes a criteria fetch-cache entry is considered fresh.
    | After this window expires the next search triggers a fresh API pull.
    */
    'lazy_ttl_minutes' => (int) env('BRIDGE_LAZY_TTL_MINUTES', 60),

    /*
    |--------------------------------------------------------------------------
    | Lazy-import pagination caps
    |--------------------------------------------------------------------------
    | Hard limits applied during a single lazy-fetch cycle.
    | If either cap is reached, pagination stops, a warning is logged, and
    | the partial result set is still upserted and cached.
    |
    | BRIDGE_LAZY_MAX_PAGES   — maximum number of API pages fetched per cycle
    |                           (Bridge API caps each page at 200 records)
    | BRIDGE_LAZY_MAX_RECORDS — maximum total records upserted per cycle
    */
    'lazy_max_pages'   => (int) env('BRIDGE_LAZY_MAX_PAGES', 20),
    'lazy_max_records' => (int) env('BRIDGE_LAZY_MAX_RECORDS', 500),

    /*
    |--------------------------------------------------------------------------
    | Lazy-import page size
    |--------------------------------------------------------------------------
    | Number of records requested per API page. The Bridge API caps this at 200.
    | Lowering this value increases the number of HTTP requests per import cycle.
    */
    'lazy_page_size'   => (int) env('BRIDGE_LAZY_PAGE_SIZE', 200),

    /*
    |--------------------------------------------------------------------------
    | Partial-import TTL
    |--------------------------------------------------------------------------
    | When a max-pages or max-records cap is hit (partial import), the cache
    | row is written with this shorter TTL so the next search retries the
    | Bridge API sooner rather than waiting the full lazy_ttl_minutes window.
    | Set to 0 to skip caching partial results entirely (forces a re-fetch on
    | every request until a full import succeeds).
    */
    'lazy_partial_ttl_minutes' => (int) env('BRIDGE_LAZY_PARTIAL_TTL_MINUTES', 5),

];
