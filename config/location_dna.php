<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Flood Zone FEMA API — Bounding Area Threshold
    |--------------------------------------------------------------------------
    |
    | Maximum allowed bounding area before the FloodZoneLookupService skips
    | the FEMA NFHL API call entirely. The value is expressed in SQUARE DEGREES
    | of latitude × longitude — NOT in square miles or square kilometers.
    |
    | Important: square degrees are not geographically consistent across
    | latitudes. One degree of longitude is narrower at higher latitudes
    | (≈ 69 mi at the equator, ≈ 54 mi at latitude 38°). For Florida (≈ 25-31°N)
    | one longitudinal degree ≈ 60-62 statute miles, so:
    |
    |   1 sq-degree  ≈ 69 mi × 61 mi  ≈ 4,200 sq miles
    |   2 sq-degrees ≈ roughly a 1° × 2° rectangle (~8,400 sq miles)
    |
    | The default value of 2.0 is calibrated for Florida use:
    |   - A single large Florida county (e.g. Alachua ≈ 0.35 sq-deg): ✔ allowed
    |   - Two adjacent large counties: ✔ allowed
    |   - A multi-county search spanning 3°+ bbox: ✗ skipped (excessive FEMA load)
    |
    | To tune the threshold set LOCATION_DNA_FLOOD_ZONE_MAX_AREA in your .env.
    | Always prefer a tighter (smaller) value rather than a looser one — the
    | FEMA NFHL endpoint is a shared public service that should not be hammered
    | with continent-scale bounding-box queries.
    |
    */
    'flood_zone_max_area_sq_degrees' => (float) env('LOCATION_DNA_FLOOD_ZONE_MAX_AREA', 2.0),

];
