<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Location DNA MapLibre renderer — THE REAL FLAG
    |--------------------------------------------------------------------------
    |
    | Master gate for rendering the Location DNA search-areas widget with
    | MapLibre + PMTiles instead of the Google Maps SDK.
    |
    | DEFAULT OFF, and off is the shipped production state for this entire phase.
    | With the gate closed the widget renders exactly what it renders today: the
    | Google implementation, unchanged, on all eight host surfaces. Nothing in
    | `resources/js/spatial/` is loaded and no PMTiles request is issued.
    |
    | THIS IS NOT `maplibre_proof_enabled` BELOW. That flag governs a standalone
    | internal diagnostic page that reads no listing. This one governs a renderer
    | inside a form that WRITES a listing's geography, so the two must never be a
    | single switch — enabling a read-only diagnostic would otherwise also swap
    | the renderer under an editing surface.
    |
    | ENABLING THIS REQUIRES A BROWSER VERIFICATION PASS. The suite that covers
    | this renderer is Playwright (`tests/browser/`), and it is the only coverage
    | that can see a dead map. PHP tests cannot, which is precisely how the outage
    | that motivated this work went unnoticed.
    |
    | It does NOT require `GOOGLE_PLACES_API_KEY`, and must never come to. A
    | MapLibre map that only initialises when a Google credential is present would
    | defeat the entire purpose of the migration.
    |
    */

    'maplibre_renderer_enabled' => (bool) env('LOCATION_DNA_MAPLIBRE_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Renderer scope
    |--------------------------------------------------------------------------
    |
    | Which host surfaces the MapLibre renderer applies to while the master gate
    | is open. Both must agree, so one environment variable cannot widen the
    | rollout across all eight includes by accident.
    |
    | Empty by default — deliberately. An operator who enables the master switch
    | without naming a surface gets no change, rather than every Buyer, Tenant and
    | Criteria form at once. Recognised values match the widget's own host keys:
    | `hire_buyer`, `hire_tenant`, `create_buyer`, `create_tenant`,
    | `buyer_criteria`, `tenant_criteria`, `display`.
    |
    */

    'maplibre_renderer_surfaces' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('LOCATION_DNA_MAPLIBRE_SURFACES', ''))
    ))),

    /*
    |--------------------------------------------------------------------------
    | MapLibre PMTiles proof-of-render (Phase 2A) — salvaged, unchanged in intent
    |--------------------------------------------------------------------------
    |
    | Gates the internal diagnostic page only. Kept separate from the renderer
    | flag above for the reason stated there.
    |
    */

    'proof_enabled' => (bool) env('SPATIAL_MAPLIBRE_PROOF_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | The archive
    |--------------------------------------------------------------------------
    |
    | `SPATIAL_PMTILES_URL` wins outright when set. Otherwise the URL is composed
    | from the R2 public base and the object path, which is how the deployment
    | record describes the bucket — see
    | docs/spatial/basemap-r2-deployment-2026-07-28.md.
    |
    | A NULL VALUE IS A SUPPORTED STATE, not a misconfiguration to fail on. The
    | renderer initialises without a backdrop and keeps geometry editable, because
    | a renderer that refuses to start without tiles reproduces the exact hazard
    | this whole workstream exists to close: a map that fails to load taking the
    | user's stored geometry with it.
    |
    | The archive is PUBLIC and read-only. No credential is present here, and none
    | may be added — this value reaches the browser.
    |
    */

    'pmtiles_url' => env('SPATIAL_PMTILES_URL') ?: (
        env('BASEMAP_R2_PUBLIC_URL') && env('BASEMAP_PMTILES_OBJECT_PATH')
            ? rtrim((string) env('BASEMAP_R2_PUBLIC_URL'), '/')
                . '/' . ltrim((string) env('BASEMAP_PMTILES_OBJECT_PATH'), '/')
            : null
    ),

    /*
    |--------------------------------------------------------------------------
    | Attribution — required, not decorative
    |--------------------------------------------------------------------------
    |
    | OpenStreetMap data under ODbL and a Protomaps-built archive are a Produced
    | Work: attribution is the licence condition for displaying them. It is
    | configurable so a future archive from a different source can correct it, not
    | so it can be removed.
    |
    */

    'attribution' => env(
        'SPATIAL_BASEMAP_ATTRIBUTION',
        '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors · <a href="https://protomaps.com">Protomaps</a>'
    ),

    /*
    |--------------------------------------------------------------------------
    | Initial view and zoom ceiling
    |--------------------------------------------------------------------------
    |
    | Centred on Florida because the hosted archive is a Florida extract. The
    | ceiling matches the archive's own maximum zoom: requesting tiles beyond what
    | it contains yields empty tiles rather than an error, which reads as a broken
    | map.
    |
    */

    'initial_view' => [
        'longitude' => -83.804601,
        'latitude'  => 27.698638,
        'zoom'      => 6,
    ],

    'max_zoom' => 15,

];
