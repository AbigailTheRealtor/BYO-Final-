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
 *   1. Existing     coordinates already stored for this property, address unchanged
 *   2. Mls          the MLS/Bridge feed's own Latitude/Longitude
 *   3. AddressPoint our own imported address-point corpus — local, exact
 *   4. Geocoder     an address geocoder (US Census first; commercial fallback later)
 *   5. Centroid     ZIP or city centroid — coarse by construction
 *   6. Manual       hand-placed by a person
 *
 * WHY AddressPoint SITS WHERE IT DOES
 * -----------------------------------
 * It is a local source — a published address point loaded into our own
 * `addresses` table — so it belongs above every network rung: consulting it
 * costs a local index lookup and spends no request. It sits *below* Mls because
 * the MLS feed's coordinate is the one attached to the actual listing record,
 * and a corpus row matched by normalized address line is a match on the address
 * rather than on the property. Both are exact; the more specific provenance
 * wins.
 *
 * It sits *above* Geocoder because an address point is the surveyed location of
 * an address, while the Census rung interpolates a house number along a street
 * segment's range. When both can answer, the corpus answer is better and free.
 *
 * @see PropertyCoordinateResolverInterface
 */
enum CoordinateSource: string
{
    case Existing     = 'existing';
    case Mls          = 'mls';
    case AddressPoint = 'address_point';
    case Geocoder     = 'geocoder';
    case Centroid     = 'centroid';
    case Manual       = 'manual';

    /**
     * True when producing this source required no outbound provider call.
     *
     * AddressPoint is local: the corpus is imported ahead of time by an
     * operator-run command, so answering from it is a query against a table we
     * host. The import itself reaches the network; a resolution never does, and
     * this method describes resolution.
     */
    public function isLocal(): bool
    {
        return match ($this) {
            self::Existing, self::Mls, self::AddressPoint, self::Centroid, self::Manual => true,
            self::Geocoder => false,
        };
    }
}
