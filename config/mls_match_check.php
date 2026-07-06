<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Match Check master flag (MLS Direct Import — Phase 4)
    |--------------------------------------------------------------------------
    |
    | Master gate for the consumer-facing Buyer/Tenant Match Check feature
    | (/match-check). Default OFF: the route/middleware/services are additive
    | but inert until the owner enables this. Independent of Matching V2, DNA
    | score generation, and the BYA compatibility flags.
    |
    */

    'enabled' => env('MLS_MATCH_CHECK_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | "Better matches" low-score fallback (Phase 4 · F2)
    |--------------------------------------------------------------------------
    |
    | Forward-declared for Wave 7 / C12. When the primary property scores below
    | the threshold AND this flag is on, Match Check may optionally surface a
    | separate, clearly-labeled "better matching properties" section via the
    | existing BuyerMatchService::match() discovery engine. Inert until C12.
    |
    */

    'better_matches_enabled'   => env('MLS_MATCH_CHECK_BETTER_MATCHES_ENABLED', false),
    'better_matches_threshold' => 60,

    /*
    |--------------------------------------------------------------------------
    | Location DNA enrichment throttle (Phase 4 · F6)
    |--------------------------------------------------------------------------
    |
    | Forward-declared for Wave 4 / C8. Match Check must never trigger unlimited
    | user-driven external enrichment. Per-listing cooldown (dedupe) + per-user
    | hourly rate limit. Inert until C8.
    |
    */

    'dna_cooldown_hours'             => 24,
    'dna_rate_limit_per_user_hourly' => 20,

];
