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
    |   Gates ONLY the optional Stage-B geo + attribute narrowers (slice 2B). The
    |   mandatory eligibility + 55+ compliance gates always run when Matching V2 is
    |   on, independent of this flag. Default false.
    |
    | overfetch_multiplier / overfetch_ceiling
    |   Stage A over-fetches (cap × multiplier, capped at ceiling) so the mandatory
    |   gates have headroom to remove ineligible/senior-mismatched rows before the
    |   final trim to `cap`.
    |
    | senior_unknown_policy
    |   How the mandatory 55+ gate resolves UNKNOWN senior data (OD-1):
    |     'open'   (default) — unknown never causes a drop (fail-open).
    |     'closed'           — unknown resolves toward the restrictive value.
    |
    */

    'candidate_discovery' => [
        'cap' => (int) env('MATCHING_V2_CANDIDATE_CAP', 200),

        'allowed_listing_types' => [
            'property' => [],
            'demand'   => [],
        ],

        'hard_filters_enabled' => env('MATCHING_V2_HARD_FILTERS_ENABLED', false),

        'overfetch_multiplier' => (int) env('MATCHING_V2_OVERFETCH_MULTIPLIER', 3),
        'overfetch_ceiling'    => (int) env('MATCHING_V2_OVERFETCH_CEILING', 1000),

        'senior_unknown_policy' => env('MATCHING_V2_SENIOR_UNKNOWN_POLICY', 'open'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Persistence / caching (Matching V2 — C7)
    |--------------------------------------------------------------------------
    |
    | Gates offline materialization of ranked match results into the additive
    | matching_v2_match_runs + matching_v2_matches tables. This is a SECOND gate
    | UNDER v2_enabled above: a write happens only when BOTH are true AND the app
    | is NOT in the production environment (MatchResultPersister hard-refuses in
    | production regardless of these flags). Default OFF.
    |
    | version
    |   Materialization version tag stamped on every summary row. The reader
    |   (PersistedMatchReader) only trusts rows whose version equals this value —
    |   bumping it invalidates all older rows at read time (the read-time re-gate),
    |   so a scoring/engine change is invalidated by version bump + re-materialize.
    |   Event-driven invalidation is a deferred fast-follow, not part of C7.
    |
    | See docs/matching-v2-c7-persistence-scope.md.
    |
    */

    'persistence' => [
        'enabled' => env('MATCHING_V2_PERSISTENCE_ENABLED', false),
        'version' => env('MATCHING_V2_PERSISTENCE_VERSION', 'c7-v1'),
    ],

];
