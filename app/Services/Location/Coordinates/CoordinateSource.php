<?php

namespace App\Services\Location\Coordinates;

/**
 * Which tier of the resolution ladder produced a coordinate.
 *
 * Distinct from {@see CoordinatePrecision}, which says how good the point is,
 * and from the free-form provider string, which says who supplied it. A single
 * precision can arrive from several sources — an MLS feed and a geocoder can
 * both yield `rooftop` — and the source is what tells you whether a re-resolve
 * would even reach the network.
 *
 * Ordered as the resolver will consult them (G6 precedence):
 *
 *   1. Existing   coordinates already stored for this property, address unchanged
 *   2. Mls        the MLS/Bridge feed's own Latitude/Longitude
 *   3. Geocoder   an address geocoder (US Census first; commercial fallback later)
 *   4. Centroid   ZIP or city centroid — coarse by construction
 *   5. Manual     hand-placed by a person
 *
 * @see PropertyCoordinateResolverInterface
 */
enum CoordinateSource: string
{
    case Existing = 'existing';
    case Mls      = 'mls';
    case Geocoder = 'geocoder';
    case Centroid = 'centroid';
    case Manual   = 'manual';

    /** True when producing this source required no outbound provider call. */
    public function isLocal(): bool
    {
        return match ($this) {
            self::Existing, self::Mls, self::Centroid, self::Manual => true,
            self::Geocoder => false,
        };
    }
}
