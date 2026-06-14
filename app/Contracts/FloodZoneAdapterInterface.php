<?php

namespace App\Contracts;

interface FloodZoneAdapterInterface
{
    /**
     * Look up FEMA flood zone polygons that intersect a bounding envelope.
     *
     * @param  array  $bbox  Bounding envelope as [minLng, minLat, maxLng, maxLat]
     * @return array  Indexed array of flood zone features, each containing:
     *                  - 'zone_designation' string  (e.g. "AE", "X", "VE")
     *                  - 'rings'           array    GeoJSON-compatible coordinate rings
     *                                               in [lng, lat] pair order
     *                Returns [] on any API failure so callers degrade gracefully.
     */
    public function lookup(array $bbox): array;
}
