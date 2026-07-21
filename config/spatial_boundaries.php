<?php

use App\Services\Spatial\Boundary\CensusCountyBoundarySource;
use App\Services\Spatial\Boundary\CensusPlaceBoundarySource;
use App\Services\Spatial\Boundary\CensusSchoolDistrictBoundarySource;
use App\Services\Spatial\Boundary\CensusZctaBoundarySource;
use App\Services\Spatial\Boundary\PadUsBoundarySource;

return [

    /*
    |--------------------------------------------------------------------------
    | Spatial Intelligence Platform — Phase 2 Batch 2D Part C3 (boundaries)
    | Boundary import authoring (boundaries / boundaries_parts)
    |--------------------------------------------------------------------------
    |
    | Cluster-free authoring config for the offline boundary importers. It NEVER
    | opens a PostGIS connection, reads NO SPATIAL_* secrets, downloads nothing,
    | and imports no data. Each importer transforms a raw source extract into
    | canonical BoundaryRecord NDJSON (GeoJSON MultiPolygon geometry). Live staging
    | + load (COPY into boundaries, then ST_Subdivide into boundaries_parts) are
    | deferred to the Class-2 phase — see the per-slice spikes under spikes/.
    |
    | Ships: PAD-US protected areas (C3a) + Census TIGER county/place/ZCTA/school
    | district (C3b). Deferred to later slices: FEMA NFHL (C3c), Gate 2 (C3d).
    |
    */

    // Directory (relative to storage/) for the dry-run artifacts, per source.
    'out_dir' => 'app/spatial/boundaries',

    /*
    | Source registry. Each entry maps a --source key to its adapter class, the
    | boundaries.kind it emits, and the default synthetic fixture.
    */
    'sources' => [

        'padus' => [
            'label'   => 'USGS PAD-US 4.1 protected areas',
            'class'   => PadUsBoundarySource::class,
            'kind'    => 'protected_area',
            'fixture' => 'tests/fixtures/spatial/boundaries/padus/padus_raw.ndjson',
        ],

        'tiger_county' => [
            'label'   => 'Census TIGER/Line counties',
            'class'   => CensusCountyBoundarySource::class,
            'kind'    => 'county',
            'fixture' => 'tests/fixtures/spatial/boundaries/tiger/county_raw.ndjson',
        ],

        'tiger_place' => [
            'label'   => 'Census TIGER/Line places (incorporated / CDP)',
            'class'   => CensusPlaceBoundarySource::class,
            'kind'    => 'place',
            'fixture' => 'tests/fixtures/spatial/boundaries/tiger/place_raw.ndjson',
        ],

        'tiger_zcta' => [
            'label'   => 'Census TIGER/Line ZIP Code Tabulation Areas (2020)',
            'class'   => CensusZctaBoundarySource::class,
            'kind'    => 'zcta',
            'fixture' => 'tests/fixtures/spatial/boundaries/tiger/zcta_raw.ndjson',
        ],

        'tiger_school_district' => [
            'label'   => 'Census TIGER/Line unified school districts',
            'class'   => CensusSchoolDistrictBoundarySource::class,
            'kind'    => 'school_district',
            'fixture' => 'tests/fixtures/spatial/boundaries/tiger/school_district_raw.ndjson',
        ],

    ],
];
