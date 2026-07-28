<?php

return [

    /*
    |--------------------------------------------------------------------------
    | MapLibre + PMTiles proof-of-render — Feature Flag (Phase 2A)
    |--------------------------------------------------------------------------
    |
    | Master gate for the isolated internal proof page at /internal/spatial/
    | maplibre-proof. Default OFF: the route aborts 404 until the owner enables
    | this explicitly. The route is additionally never registered outside a
    | non-production environment, mirroring the double-gating on the dev-login
    | route in routes/web.php.
    |
    | This flag governs a proof-of-render only. It wires nothing into the Seller,
    | Buyer, Landlord or Tenant listing flows, reads no listing data, and opens
    | no database connection. Renderer authorisation for production map surfaces
    | remains an open owner decision — see docs/spatial/
    | basemap-r2-deployment-2026-07-28.md §7 items 3 and 4.
    |
    | Set SPATIAL_MAPLIBRE_PROOF_ENABLED=true in .env to open the proof page.
    |
    */

    'proof_enabled' => (bool) env('SPATIAL_MAPLIBRE_PROOF_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | PMTiles archive URL
    |--------------------------------------------------------------------------
    |
    | The single source of truth for the browser-side archive location. The
    | deployment record deliberately stores NO literal URL — it records only the
    | variable names (§3) — so the URL is composed here from the same two
    | variables that record documents:
    |
    |   BASEMAP_R2_PUBLIC_URL      r2.dev managed public URL (dev/verification)
    |   BASEMAP_PMTILES_OBJECT_PATH object key of the uploaded archive
    |
    | SPATIAL_PMTILES_URL overrides the composed value when a fully-qualified URL
    | is supplied directly (e.g. once a custom domain replaces r2.dev).
    |
    | NOTHING HERE IS A CREDENTIAL. Only the public URL and the object key reach
    | the browser. BASEMAP_R2_ACCESS_KEY_ID and BASEMAP_R2_SECRET_ACCESS_KEY are
    | server-side S3 credentials and are deliberately NOT read by this config —
    | the archive is fetched credential-free over the public URL, which §4 check
    | 8 verified returns 200.
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
    | Attribution
    |--------------------------------------------------------------------------
    |
    | Required on display. The archive carries `© OpenStreetMap` in its own
    | metadata (deployment record §2, Provenance); this is the string rendered in
    | the map control. Protomaps is credited as the basemap build.
    |
    */

    'attribution' => env(
        'SPATIAL_BASEMAP_ATTRIBUTION',
        '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors · <a href="https://protomaps.com">Protomaps</a>'
    ),

    /*
    |--------------------------------------------------------------------------
    | Initial view — Florida
    |--------------------------------------------------------------------------
    |
    | Centre and zoom for the proof render. Derived from the extraction bounding
    | box in the deployment record §2 (-87.634896,24.396308,-79.974306,31.000968);
    | the centre is that box's midpoint. `max_zoom` matches the archive's own
    | maximum (15) — requesting beyond it yields empty tiles.
    |
    */

    'initial_view' => [
        'longitude' => -83.804601,
        'latitude'  => 27.698638,
        'zoom'      => 6,
    ],

    'max_zoom' => 15,
];
