<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Matching Engine V2 (Beyond-MLS §F6 consumption)
    |--------------------------------------------------------------------------
    |
    | Master gate for Matching V2 — the read-only consumption layer over the
    | dna_scores production artifact. Default OFF and INDEPENDENT of DNA score
    | generation (config dna_scores.generation_enabled) and the compatibility
    | engine (config bya_compatibility.*).
    |
    | Matching V2 is a PURE READ-ONLY CONSUMER of dna_scores: it never
    | regenerates, modifies, or writes back into any generation table. When this
    | flag is off, the consumption services return an inert (empty) result.
    |
    */

    'v2_enabled' => env('MATCHING_V2_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Candidate Discovery (Matching V2 — consumption slice 2)
    |--------------------------------------------------------------------------
    |
    | Tunes the read-only CandidateDiscoveryService. This is NOT a master gate:
    | discovery is gated by v2_enabled above and returns an empty set (no DB
    | reads) whenever Matching V2 is off. These keys only shape behaviour once
    | V2 is already on.
    |
    | cap
    |   Hard ceiling on the number of candidates returned. Enforced as a SQL
    |   LIMIT, never a post-hoc PHP trim of a large collection. Mirrors the
    |   Stellar DEFAULT_CANDIDATE_CAP (200) for consistency.
    |
    | allowed_listing_types
    |   OPTIONAL listing-type allowlist per counterpart side. EMPTY = all types
    |   (the provider-agnostic default): membership is defined by "has
    |   counterpart-side DNA", never by provider, so a new DNA-enabled provider
    |   becomes discoverable with zero discovery-layer changes. Populate a side
    |   only to deliberately scope discovery (ops/testing).
    |
    | hard_filters_enabled
    |   RESERVED for the deferred Stage B (geo/attribute narrowing + the
    |   mandatory 55+/legal gate). Stage B is NOT built in slice 2, so this flag
    |   is currently inert. Default false.
    |
    */

    'candidate_discovery' => [
        'cap' => (int) env('MATCHING_V2_CANDIDATE_CAP', 200),

        'allowed_listing_types' => [
            'property' => [],
            'demand'   => [],
        ],

        'hard_filters_enabled' => env('MATCHING_V2_HARD_FILTERS_ENABLED', false),
    ],

];
