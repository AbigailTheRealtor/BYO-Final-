<?php

use App\Services\Spatial\Overlay\CmsHospitalOverlaySource;
use App\Services\Spatial\Overlay\UsgsBoatRampOverlaySource;

return [

    /*
    |--------------------------------------------------------------------------
    | Spatial Intelligence Platform — Phase 2 Batch 2D Part C2
    | Authority-overlay importers (offline authoring)
    |--------------------------------------------------------------------------
    |
    | Cluster-free authoring config for the offline authority-overlay importers.
    | It NEVER opens a PostGIS connection, reads NO SPATIAL_* secrets, downloads
    | nothing, and imports no data. Each importer transforms a raw authority
    | source extract into canonical AuthorityRecord NDJSON (the Batch 2D Part C1
    | staging/link input). Live staging + load are deferred to the Class-2 phase
    | — see spikes/phase-2-batch-2d-part-c2-authority-overlay-import/sql/.
    |
    */

    // Directory (relative to storage/) for the dry-run artifacts, per source.
    'out_dir' => 'app/spatial/authority/overlay',

    /*
    | Source registry. `target` decides the Class-2 load path (SSOT §8.2/§9.1):
    |   'link'  — OVERLAY: AuthorityRecords feed the C1 linker (place_authority_links).
    |   'place' — BASE source: AuthorityRecords become `places` rows directly.
    | `metric_*` bounds are transcribed from the SSOT authority-metric definitions,
    | not invented; a null metric domain marks a membership source.
    */
    'sources' => [

        'cms' => [
            'label'        => 'CMS Hospital Overall Star Rating',
            'class'        => CmsHospitalOverlaySource::class,
            'target'       => 'link',
            'metric_label' => 'cms_overall_star_rating', // SSOT §7.2: "CMS stars"
            'fixture'      => 'tests/fixtures/spatial/authority_overlay/cms/cms_hospitals_raw.ndjson',
        ],

        'usgs-boat-ramp' => [
            'label'        => 'USGS Boat Ramp Locations (CC0)',
            'class'        => UsgsBoatRampOverlaySource::class,
            'target'       => 'place',
            'metric_label' => null,                      // SSOT §9.1: ranked by membership, no metric
            'fixture'      => 'tests/fixtures/spatial/authority_overlay/usgs/boat_ramps_raw.ndjson',
        ],

    ],
];
