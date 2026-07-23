<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Spatial Intelligence Platform — Phase 2 Batch 2D Part C3d-a
    | Gate 2 — corpus coverage EVIDENCE SCHEMA (offline, Class-1 only)
    |--------------------------------------------------------------------------
    |
    | This config drives the CLUSTER-FREE Gate 2 coverage-matrix authoring tool.
    | It NEVER opens a PostGIS connection, reads NO SPATIAL_* secret, downloads
    | nothing, and imports no corpus. It assembles a dataset × territory evidence
    | MATRIX from a synthetic fixture and reports it for a PRODUCT OWNER to read.
    |
    | It deliberately defines NO coverage metric: no numerator, no denominator,
    | no percentage, no threshold, no automated pass/fail. The SSOT does not
    | specify a Gate 2 formula and states acceptance is per-category by the
    | product owner (ROADMAP L561; PLATFORM §18). That real measurement + the
    | product-owner acceptance are C3d-b (Class-2) — see docs/spatial/B2D-part-c3d-a-*.md.
    |
    */

    /*
    | The synthetic coverage inputs (illustrative observation counts — NOT real
    | corpus measurements). License-clean, deterministic, CI-safe. The offline
    | `spatial:gate2-matrix` command reads this via base_path(); the unit suite
    | reads the same file, so there is one source of truth.
    */
    'fixture' => 'tests/Fixtures/Spatial/Gate2/synthetic-coverage-inputs.json',

    /*
    | Where the deterministic evidence artifacts (matrix.json, summary.json) are
    | written on an offline dry run.
    */
    'out_dir' => 'app/spatial/gate2',

    /*
    | Canonical territory axis (SSOT §5 L230, §18 L1361, E-32). Gate 2 MUST report
    | per-category coverage for these four separately — Puerto Rico and Alaska are
    | never folded into CONUS. The assembler fails closed if the matrix omits any
    | of these, so "report PR and AK separately" is enforced structurally, not by
    | convention. Hawaii / USVI / Guam / American Samoa / N. Mariana are in the
    | nationwide extent (§5) but are NOT mandated as separate matrix columns.
    */
    'required_territories' => ['FL', 'PR', 'AK', 'rural_CONUS'],

    /*
    | The territory that PR-specific verification keys on (§5: PR is reported
    | separately; 76 live Bridge listings; thin OSM/Overture; no NCES SABS).
    */
    'pr_territory' => 'PR',

    /*
    | The four datasets E-32 says to VERIFY EXPLICITLY for Puerto Rico — the ones
    | most likely to be silently missing for PR. The assembler requires each to be
    | a declared dataset so its PR cells are always in the matrix and can never be
    | quietly dropped from the evidence.
    */
    'pr_watch_datasets' => ['epa_walkability', 'usgs_boat_ramps', 'noaa_cusp', 'dot_nad'],
];
