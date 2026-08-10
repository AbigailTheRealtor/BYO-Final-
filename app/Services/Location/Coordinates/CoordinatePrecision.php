<?php

namespace App\Services\Location\Coordinates;

/**
 * How precisely a coordinate identifies the property it is attached to.
 *
 * WHY THIS IS AN ENUM AND NOT A STRING
 * ------------------------------------
 * The distinction this type carries is a safety boundary, not a label. A ZIP
 * centroid is the middle of a postal area that can span several miles; feeding
 * one into a "how far is the nearest school" calculation produces a number that
 * looks authoritative and is wrong. The old code guarded that with naming
 * conventions and comments. This enum makes it answerable — `isExact()` — so a
 * caller cannot forget.
 *
 * THE TIERS
 * ---------
 *   rooftop       the structure itself
 *   parcel        the parcel/lot centroid; also what a unit-stripped condo
 *                 lookup yields (see PropertyAddress::coordinateLookupLine())
 *   entrance      a mapped building entrance
 *   interpolated  a house number positioned along a street segment's address
 *                 range — what the US Census Geocoder returns on a match
 *   street        the street was matched but the house number was NOT
 *   zip_centroid  the middle of a postal area
 *   city_centroid the middle of a place
 *   unknown       provenance not established
 *
 * WHERE THE LINE SITS (product decision, 2026-08-07)
 * --------------------------------------------------
 * Exact: rooftop, parcel, entrance, interpolated.
 * Coarse: street, zip_centroid, city_centroid, unknown.
 *
 * `street` sits on the coarse side deliberately, and it is the only tier whose
 * placement was not obvious. `interpolated` already covers the ordinary Census
 * success case — a matched house number placed along a segment. `street` means
 * the house number was *not* matched, so the point is somewhere along a block.
 * That is fine for "nearest coffee shop" (the POI radius runs to 25 miles) and
 * not fine for FEMA flood zone, whose polygons follow parcel-scale boundaries:
 * a block-length error can place a property on the wrong side of a flood
 * boundary, which is a disclosure question, not a cosmetic one. Rather than
 * split the tier per consumer, the safer single rule was chosen.
 *
 * @see PropertyCoordinateResult::isUsableForLocationDna()
 */
enum CoordinatePrecision: string
{
    case Rooftop      = 'rooftop';
    case Parcel       = 'parcel';
    case Entrance     = 'entrance';
    case Interpolated = 'interpolated';
    case Street       = 'street';
    case ZipCentroid  = 'zip_centroid';
    case CityCentroid = 'city_centroid';
    case Unknown      = 'unknown';

    /**
     * True when this precision identifies a specific property closely enough to
     * drive distance, travel-time and boundary work.
     *
     * Enumerated positively: a tier added later is coarse until somebody decides
     * otherwise, which is the direction we want to fail in.
     */
    public function isExact(): bool
    {
        return match ($this) {
            self::Rooftop, self::Parcel, self::Entrance, self::Interpolated => true,
            self::Street, self::ZipCentroid, self::CityCentroid, self::Unknown => false,
        };
    }

    /**
     * True when the coordinate may be shown on a map for framing but must not be
     * presented as the property's location or used for measurement.
     */
    public function isCoarse(): bool
    {
        return ! $this->isExact();
    }

    /** Human-facing label for UI that discloses coordinate quality. */
    public function label(): string
    {
        return match ($this) {
            self::Rooftop      => 'Rooftop',
            self::Parcel       => 'Parcel',
            self::Entrance     => 'Building entrance',
            self::Interpolated => 'Interpolated from street range',
            self::Street       => 'Street level (approximate)',
            self::ZipCentroid  => 'ZIP code area (approximate)',
            self::CityCentroid => 'City area (approximate)',
            self::Unknown      => 'Unknown',
        };
    }
}
