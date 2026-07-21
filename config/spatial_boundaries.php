<?php

use App\Services\Spatial\Boundary\PadUsBoundarySource;

return [

    /*
    |--------------------------------------------------------------------------
    | Spatial Intelligence Platform — Phase 2 Batch 2D Part C3a
    | PAD-US boundary import authoring (boundaries / boundaries_parts)
    |--------------------------------------------------------------------------
    |
    | Cluster-free authoring config for the offline boundary importers. It NEVER
    | opens a PostGIS connection, reads NO SPATIAL_* secrets, downloads nothing,
    | and imports no data. Each importer transforms a raw source extract into
    | canonical BoundaryRecord NDJSON (GeoJSON MultiPolygon geometry). Live staging
    | + load (COPY into boundaries, then ST_Subdivide into boundaries_parts) are
    | deferred to the Class-2 phase — see
    | spikes/phase-2-batch-2d-part-c3a-padus-boundary-import/sql/.
    |
    | Deferred to later slices: Census TIGER (C3b), FEMA NFHL (C3c), Gate 2 (C3d).
    |
    */

    // Directory (relative to storage/) for the dry-run artifacts, per source.
    'out_dir' => 'app/spatial/boundaries',

    /*
    | Source registry. C3a ships only PAD-US (protected_area polygons). Each entry
    | maps a --source key to its adapter class and default synthetic fixture.
    */
    'sources' => [

        'padus' => [
            'label'   => 'USGS PAD-US 4.1 protected areas',
            'class'   => PadUsBoundarySource::class,
            'kind'    => 'protected_area',
            'fixture' => 'tests/fixtures/spatial/boundaries/padus/padus_raw.ndjson',
        ],

    ],
];
