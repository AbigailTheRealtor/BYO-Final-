<?php

namespace App\Contracts;

interface SchoolDistrictAdapterInterface
{
    /**
     * Look up school district polygons that intersect a bounding envelope.
     *
     * @param  array  $bbox  Bounding envelope as [minLng, minLat, maxLng, maxLat]
     * @return array  Indexed array of school district features, each containing:
     *                  - 'district_name' string  (e.g. "Hillsborough County School District")
     *                  - 'rings'         array   GeoJSON-compatible coordinate rings
     *                                            in [lng, lat] pair order
     *                Returns [] on any API failure so callers degrade gracefully.
     */
    public function lookup(array $bbox): array;
}
