<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Spatial Intelligence Platform — Phase 2 Batch 2A
    | Overture Places · First Slice · OFFLINE extract
    |--------------------------------------------------------------------------
    |
    | This config drives the CLUSTER-FREE authoring scope (B1–B4): taxonomy,
    | the offline normalizer, and the Q2 sizing harness. It NEVER opens a
    | PostGIS connection and it reads NO SPATIAL_* secrets. Live staging /
    | COPY / partition-flip / acceptance are deferred to the later Class-2
    | phase and are intentionally absent here.
    |
    */

    /*
    | Pinned Overture monthly release.
    |
    | Resolved to the latest stable monthly release available at implementation
    | time (2026-07-17). Overture tags releases yyyy-mm-dd.x. This value is a
    | PIN only — nothing in Batch 2A downloads it. The live extract/measurement
    | harness (spikes/) reads this pin when a cluster+DuckDB become available.
    |
    | Schema note (roadmap): the June-2026 release DEPRECATED the `categories`
    | property (with `categories.primary` / `categories.alternate`) in favour of
    | `basic_category` + `taxonomy`; the old property is scheduled for removal in
    | the September-2026 release. In the pinned 2026-06-17.0 release ALL THREE
    | coexist, so reading `categories.primary` is correct for this slice. The
    | successor batch MUST migrate the primary-category read to `basic_category`
    | before adopting a release >= 2026-09.
    */
    'release' => env('OVERTURE_RELEASE', '2026-06-17.0'),

    // Overture theme/type this slice reads. Places only.
    'theme' => 'places',
    'type'  => 'place',

    // Canonical source tag written into every normalized record + every
    // place_category_mappings.source row. Matches places.source (SSOT §7.2).
    'source' => 'overture',

    /*
    | Corpus filter — existence-confidence floor (SSOT §7 / architecture §D-Q2).
    | Rows below this are dropped at extract time and COUNTED (never silently
    | lost). Overture `confidence` is numeric in [0,1].
    */
    'confidence_min' => 0.90,

    /*
    | Canonical normalized extract format. NDJSON is the single canonical
    | interchange format for Batch 2A (owner decision). One JSON object per
    | line, UTF-8, LF-terminated, keys in NormalizedPlaceRecord field order.
    */
    'extract_format' => 'ndjson',

    /*
    | Q2 sizing planning proxies (ACCEPTED owner decisions). Used by
    | CorpusSizingProjector to turn a measured/So-far-projected row count into a
    | storage estimate WITHOUT a live cluster. These are planning constants, not
    | measurements — live Q2 replaces the row COUNTS, not these per-row proxies.
    */
    'sizing' => [
        'bytes_per_row_total' => 450,  // total on-disk bytes per places row (incl. row + attrs + non-gist indexes)
        'gist_bytes_per_row'  => 94,   // composite gist(category_key, geom) bytes per row
    ],

    /*
    | Region bounding boxes [west, south, east, north] in EPSG:4326 degrees.
    | Used by the DuckDB extract SQL (bbox pushdown) and by the Q2 count
    | harness. Pinellas is the B3 smoke region; Florida and CONUS are Q2
    | measurement scopes only (no data pulled in Batch 2A).
    */
    'regions' => [
        'pinellas' => ['west' => -82.90, 'south' => 27.55, 'east' => -82.53, 'north' => 28.17],
        'florida'  => ['west' => -87.63, 'south' => 24.40, 'east' => -79.97, 'north' => 31.00],
        'conus'    => ['west' => -124.85, 'south' => 24.40, 'east' => -66.88, 'north' => 49.40],
    ],

    /*
    | Object storage is DEFERRED (owner decision). The Overture GeoParquet
    | S3 base is recorded here for the successor batch's live DuckDB read; it is
    | NOT consumed anywhere in Batch 2A.
    */
    'source_s3_base' => 's3://overturemaps-us-west-2/release',

    /*
    | Default committed fixture the offline command falls back to when no
    | --input is given and DuckDB is unavailable. Raw-Overture-shaped NDJSON.
    */
    'default_fixture' => 'tests/fixtures/spatial/overture/pinellas_raw_places.ndjson',
];
