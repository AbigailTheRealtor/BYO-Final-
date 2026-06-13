<?php

namespace App\Contracts;

interface BoundaryAdapterInterface
{
    /**
     * Look up geographic polygon coordinates for a set of named boundaries.
     *
     * @param  string       $type        One of: 'city', 'zip', 'county'
     * @param  array        $names       List of names (city names, ZIP codes, or county names)
     * @param  string|null  $stateAbbrev Optional 2-letter state abbreviation to narrow results
     * @return array  Indexed array, one entry per name. Each entry is either:
     *                  - an array of GeoJSON-compatible coordinate rings (non-empty = found), or
     *                  - an empty array [] (= not found / no data for that name)
     *                The returned array preserves the same order as $names.
     */
    public function lookup(string $type, array $names, ?string $stateAbbrev): array;
}
