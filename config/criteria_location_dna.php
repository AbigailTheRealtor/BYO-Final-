<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Criteria Location DNA — geography cascade (Phase 1)
    |--------------------------------------------------------------------------
    |
    | Master gate for the Criteria geography experience: state (required) →
    | counties (required) → ZIPs and cities (optional).
    |
    | DEFAULT OFF, and Phase 1a ships nothing that reads it. The flag is
    | introduced with the foundation so the UI increment (Phase 1c) has a gate
    | already in place and reviewed, rather than adding a switch in the same
    | commit that adds the surface it controls.
    |
    | Turning this on does NOT change how anything is saved. The Phase 1 preview
    | is read-only by construction: `App\Services\LocationDna\Criteria` contains
    | no write path of any kind, and the criteria controllers' write paths are
    | untouched. Writing through the canonical writer is Phase 2, gated
    | separately and authorised separately.
    |
    */

    'geography_preview_enabled' => (bool) env('CRITERIA_LDNA_PREVIEW_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Geography source
    |--------------------------------------------------------------------------
    |
    | Which CriteriaGeographyRepository implementation backs the cascade.
    |
    |   'eloquent'  the `us_states` / `us_counties` / `us_cities` / `us_zip_codes`
    |               reference tables. Real data, already populated, no new
    |               infrastructure.
    |   'fake'      the in-memory fixture repository. For local composition and
    |               demos only — it returns whatever it was handed.
    |
    | A PostGIS-backed implementation joins this list when the spatial corpus is
    | provisioned; that is a map-GEOMETRY concern (later phase), not a selection
    | concern, so the cascade does not wait on it.
    |
    */

    'geography_source' => env('CRITERIA_LDNA_GEOGRAPHY_SOURCE', 'eloquent'),

    /*
    |--------------------------------------------------------------------------
    | Geography cascade — the Phase 1c editing surface
    |--------------------------------------------------------------------------
    |
    | Master gate for replacing a workflow's four free-text geography inputs with
    | the corpus-backed cascade: state → counties → optional cities → optional
    | ZIPs.
    |
    | DEFAULT OFF. With the gate closed every workflow renders exactly what it
    | rendered before — the shared map widget keeps its own geography inputs and
    | its own third-party place autocomplete — and no new query is issued.
    |
    | Turning this on does NOT change how anything is saved. The cascade projects
    | its selection back into the same four canonical keys, in the same label
    | format, and hands them to the same canonical writer. No meta key is added,
    | no mirror set changes, and no schema changes.
    |
    | MANUAL VISUAL VERIFICATION IS A PREREQUISITE before enabling this in any
    | shared environment. There is no automated browser coverage for these tabs,
    | so layout and CSS regressions are not caught by the suite.
    |
    */

    'geography_cascade_enabled' => (bool) env('CRITERIA_LDNA_CASCADE_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Geography cascade scope
    |--------------------------------------------------------------------------
    |
    | Which workflows the cascade applies to while the master gate is open. Both
    | must agree before anything renders, so a single environment variable cannot
    | widen the rollout by accident.
    |
    | Slice 1 is `hire_buyer` alone. The approved rollout order after it is
    | `hire_tenant`, then `create_tenant`, then `create_buyer` — each its own
    | slice, each verified before the next. Widening this list is a rollout
    | decision, not a code change.
    |
    */

    'geography_cascade_workflows' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CRITERIA_LDNA_CASCADE_WORKFLOWS', 'hire_buyer'))
    ))),

];
