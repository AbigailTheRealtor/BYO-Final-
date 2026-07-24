<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Spatial Intelligence Platform — Phase 2 Batch 2D Part C3d-b
    | Gate 2 — corpus coverage LIVE MEASUREMENT (Class-2, Florida pilot)
    |--------------------------------------------------------------------------
    |
    | This is the CORPUS-SIDE companion to config/spatial_gate2.php (the offline
    | C3d-a evidence schema). C3d-a assembled the dataset × territory matrix from
    | a SYNTHETIC fixture; C3d-b feeds the SAME assembler from real per-cell COUNTs
    | measured against the loaded pgsql_spatial corpus.
    |
    | It STILL defines NO coverage metric — no numerator, denominator, percentage,
    | threshold, or automated pass/fail. The SSOT specifies no Gate 2 formula and
    | states acceptance is per-category by the PRODUCT OWNER. This file only maps
    | each measurable dataset/category to the correct read-only COUNT and binds the
    | Florida territory predicate. See docs/spatial/B2D-part-c3d-b-*.md.
    |
    | The territory AXIS (FL/PR/AK/rural_CONUS) and the four PR watch datasets are
    | NOT re-declared here — they remain owned by config/spatial_gate2.php, so the
    | offline and live pipelines share one source of truth for the matrix shape.
    */

    /*
    | The dedicated PostGIS connection the live measurement reads. The observation
    | source executes ONLY read-only COUNTs against this connection; it is never the
    | app database (see config/database.php `pgsql_spatial`). Overridable so a
    | controlled test connection can stand in for the cluster.
    */
    'connection' => 'pgsql_spatial',

    /*
    | Where the deterministic evidence artifacts (matrix.json, summary.json) are
    | written by `spatial:gate2-measure-coverage`. Distinct from the C3d-a offline
    | out-dir so a live run never clobbers the synthetic reference artifacts.
    */
    'out_dir' => 'app/spatial/gate2-corpus',

    /*
    | Ledger identity for the corpus_imports row a measurement run records. The
    | (corpus_version, dataset) pair is the idempotency key — re-running the same
    | version does NOT write a second row. `corpus_version` is normally overridden
    | per run (--corpus-version) to name the loaded corpus snapshot.
    */
    'ledger_dataset'         => 'gate2_coverage',
    'default_corpus_version' => 'c3d-b-fl-pilot-unversioned',

    /*
    | Territory → state FIPS. FL='12' is the ONLY predicate the Florida pilot uses.
    | PR='72' and AK='02' are declared for when their execution is later approved —
    | the catalog can bind them, but the C3d-b command refuses anything but FL.
    |
    | rural_CONUS is DELIBERATELY ABSENT: which counties are "rural" is an open
    | product/data decision (D-C3d-a-5). A territory with no FIPS here is never
    | queried, so its cells stay `unmeasured` — never fabricated as a zero.
    */
    'state_fips' => [
        'FL' => '12',
        'PR' => '72',
        'AK' => '02',
    ],

    /*
    | PLACEHOLDER — intentionally empty. The rural_CONUS county set is a C3d-b
    | product decision and MUST NOT be invented here. Kept as an explicit key so
    | the omission is visible and auditable, mirroring the spike's :rural_conus_fips.
    */
    'rural_conus_fips' => [],

    /*
    | The boundary `kind` used to attribute an owned place to a territory. A place
    | has no state_fips column (SSOT §7.2); it is bucketed by spatial containment
    | within a boundary whose attrs->>'state_fips' matches the territory. County
    | boundaries (Census TIGER, C3b) carry state_fips in attrs and blanket the
    | state, so they are the attribution layer. This is why the FL county boundary
    | must be the FIRST dataset loaded — without it every place cell is a measured
    | zero (`absent`), which is honest but empty.
    */
    'place_territory_boundary_kind' => 'county',

    /*
    | The measurable-dataset registry for the Florida pilot.
    |
    |   categories : the canonical tokens that become the matrix's category axis for
    |                this dataset (for overture_places these are place_categories.category_key).
    |   measure    : the read-only COUNT binding, or null.
    |                • strategy 'places'   — count owned places of a category_key whose
    |                                        geom is contained by the territory boundary.
    |                • null                — DECLARED but not measurable yet: the dataset
    |                                        appears in the matrix and stays `unmeasured`
    |                                        in every territory. The four E-32 PR watch
    |                                        datasets are declared this way so their cells
    |                                        are always present but never faked to zero.
    |
    | Only `overture_places` is measurable in the minimum Florida pilot — it IS the
    | owned corpus Gate 2 scores. The PR watch datasets are declared-unmeasured; their
    | importers and predicates are deferred (not invented here).
    */
    'datasets' => [

        'overture_places' => [
            'categories' => [
                'grocery_store',
                'restaurant',
                'pharmacy',
                'shopping_center',
                'coffee_shop',
                'gym',
                'gas_station',
            ],
            'measure' => [
                'strategy'        => 'places',
                'table'           => 'places',
                'category_column' => 'category_key',
                'geom_column'     => 'geom',
            ],
        ],

        // ── E-32 PR watch datasets — declared, NOT measurable in the FL pilot. ──
        // Their backing tables/importers are deferred; leaving `measure` null keeps
        // every cell `unmeasured` (never a silent hole, never a fabricated zero).
        'epa_walkability' => [
            'categories' => ['walkability_index'],
            'measure'    => null,
        ],
        'usgs_boat_ramps' => [
            'categories' => ['boat_ramp'],
            'measure'    => null,
        ],
        'noaa_cusp' => [
            'categories' => ['shoreline'],
            'measure'    => null,
        ],
        'dot_nad' => [
            'categories' => ['address_point'],
            'measure'    => null,
        ],
    ],
];
