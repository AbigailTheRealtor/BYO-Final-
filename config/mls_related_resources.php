<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Related Bridge resources — Member, Office, OpenHouse
    |--------------------------------------------------------------------------
    |
    | The Property resource does not carry everything a complete listing wants.
    | The 2026-09-04 payload audit found, across 1,224 cached records, that these
    | columns are absent from Property entirely — not null, ABSENT:
    |
    |   ListAgentDirectPhone, ListAgentMobilePhone, ListAgentOfficePhone,
    |   ListAgentURL, ListAgentStateLicense, ListOfficeURL, ListOfficeEmail,
    |   ListOfficeFax, ListOfficeAddress1/City/StateOrProvince/PostalCode,
    |   CoListAgentEmail, CoListAgentPreferredPhone, CoListOfficePhone,
    |   and every open-house row (only STELLAR_OpenHouseCount is on Property).
    |
    | A live probe on 2026-09-04 (`php artisan mls:probe-resources --force-probe`)
    | confirmed this dataset exposes:
    |
    |   Member    — 79 fields, HTTP 200
    |   Office    — 55 fields, HTTP 200
    |   OpenHouse — 36 fields, HTTP 200
    |   Room      — HTTP 404, not exposed
    |   Unit      — HTTP 404, not exposed
    |
    | Room and Unit are therefore NOT implemented and must not be faked from
    | Property columns. `RoomsTotal` and `NumberOfUnitsTotal` are counts, and a
    | count is not a roster.
    |
    | Enabled by default because the capability is verified and because the
    | enrichment is only reachable from the MLS import path, which is itself
    | behind two flags that default OFF. Set false to stop every secondary
    | request without touching the import.
    |
    */

    'enabled' => env('MLS_RELATED_RESOURCES_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Which resources to fetch
    |--------------------------------------------------------------------------
    |
    | Individually switchable so one misbehaving resource can be turned off
    | without losing the other two.
    |
    */

    'member'     => env('MLS_RELATED_MEMBER_ENABLED', true),
    'office'     => env('MLS_RELATED_OFFICE_ENABLED', true),
    'open_house' => env('MLS_RELATED_OPEN_HOUSE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Cache lifetime, in minutes
    |--------------------------------------------------------------------------
    |
    | Keyed on the Member/Office KEY, not on the listing, which is what makes
    | this safe at volume: one brokerage lists hundreds of properties, and every
    | one of them resolves to the same cached Office row. That is the whole
    | answer to "avoid N+1 Bridge calls" — the second listing from an office
    | costs nothing.
    |
    | Agent and office records change rarely; a day is generous and still
    | picks up a phone-number correction without anyone clearing anything.
    | Open houses are dated events and expire much sooner.
    |
    */

    'member_ttl_minutes'     => (int) env('MLS_RELATED_MEMBER_TTL', 1440),
    'office_ttl_minutes'     => (int) env('MLS_RELATED_OFFICE_TTL', 1440),
    'open_house_ttl_minutes' => (int) env('MLS_RELATED_OPEN_HOUSE_TTL', 60),

    /*
    |--------------------------------------------------------------------------
    | Per-import request ceiling
    |--------------------------------------------------------------------------
    |
    | A hard cap on secondary requests for one import, counted AFTER the cache.
    | One import asks for at most: list agent, list office, co-list agent,
    | co-list office, open houses — five. The ceiling is a backstop against a
    | future caller looping, not a capacity plan.
    |
    */

    'max_requests_per_import' => (int) env('MLS_RELATED_MAX_REQUESTS', 6),

    /*
    |--------------------------------------------------------------------------
    | Open houses kept per listing
    |--------------------------------------------------------------------------
    */

    'max_open_houses' => (int) env('MLS_RELATED_MAX_OPEN_HOUSES', 10),

    /*
    |--------------------------------------------------------------------------
    | Request timeout, in seconds
    |--------------------------------------------------------------------------
    |
    | Deliberately short. This is additive data on a path a user is waiting on,
    | so a slow provider must cost a few seconds and a missing section, never a
    | hung import.
    |
    */

    'timeout' => (int) env('MLS_RELATED_TIMEOUT', 10),

];
