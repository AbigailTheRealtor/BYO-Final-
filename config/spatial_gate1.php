<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Spatial Intelligence Platform — Phase 2 Batch 2D Part B
    | Hybrid Gate 1 Harness · Option D (synthetic benchmark) + Option E (runtime)
    |--------------------------------------------------------------------------
    |
    | This config drives the CLUSTER-FREE Gate 1 rank-sanity harness. It NEVER
    | opens a PostGIS connection, reads NO SPATIAL_* secrets, downloads nothing,
    | and imports no corpus. Real-corpus Gate 1 (corpus nearest-3 vs ground
    | truth, exclusion-rule integration, corpus-backed embarrassment evaluation)
    | is DEFERRED to the Class-2 phase — see docs/spatial/B2D-part-b-*.md.
    |
    */

    /*
    | How many top-ranked POIs per (listing × category) pair Gate 1 inspects for
    | embarrassments. SSOT §9.3 samples the corpus's NEAREST-3, so the default is
    | 3. An embarrassment is a clearly-wrong POI (a "grocery" that is a gas
    | station; a "hospital" that is a dentist) surfaced within this window.
    */
    'top_n' => 3,

    /*
    | Pass threshold for the embarrassment rate (SSOT §9.3: "Pass ≤3%").
    | Expressed as a fraction of evaluated top-N slots, not a percentage.
    */
    'embarrassment_threshold' => 0.03,

    /*
    | The synthetic benchmark (Option D). License-clean, deterministic, CI-safe:
    | synthetic names, synthetic types, synthetic ratings/review counts, and a
    | per-candidate `legitimate` label. Contains NO Google-derived content. The
    | offline `spatial:gate1-validate` command reads this via base_path(); the
    | unit suite reads the same file, so there is one source of truth.
    */
    'scenario_fixture' => 'tests/Fixtures/Spatial/Gate1/synthetic-gate1-scenarios.json',
];
