<?php

namespace App\Services\Location\AddressCorpus;

/**
 * Generous per-state bounding boxes, as a coarse sanity check on a coordinate.
 *
 * WHAT THIS IS FOR, AND WHAT IT IS NOT FOR
 * ----------------------------------------
 * `0,0` parses. So does a Florida address carrying a Nevada longitude, and so
 * does a sign-flipped longitude that lands in the Indian Ocean. Numeric validity
 * cannot catch any of those; a bounding box catches all three.
 *
 * It is deliberately loose — it includes offshore keys and territorial water —
 * because it exists to catch failures that are catastrophic and obvious, not to
 * verify a location. A box that hugged the coastline would start rejecting real
 * addresses, which is the expensive direction to be wrong in.
 *
 * A state absent from this list is simply not bounds-checked. That is the right
 * failure direction and it is why this returns true rather than false for an
 * unknown FIPS: a missing box must never reject a valid address.
 *
 * Lifted out of `NadRowNormalizer` when a second source arrived. Both
 * normalizers ask the same question of the same coordinate, and two copies of a
 * box would eventually disagree about where Florida is.
 */
final class StateBounds
{
    /** FIPS => [minLat, maxLat, minLng, maxLng]. */
    public const BOXES = [
        '12' => [24.2, 31.2, -87.8, -79.8],   // Florida, incl. the Keys
    ];

    /** True when the point sits inside the jurisdiction's box, or none is known. */
    public static function contains(float $latitude, float $longitude, string $stateFips): bool
    {
        $box = self::BOXES[StateFips::normalizeFips($stateFips)] ?? null;

        if ($box === null) {
            return true;
        }

        [$minLat, $maxLat, $minLng, $maxLng] = $box;

        return $latitude >= $minLat && $latitude <= $maxLat
            && $longitude >= $minLng && $longitude <= $maxLng;
    }

    /** True when a box is published for this jurisdiction. */
    public static function known(string $stateFips): bool
    {
        return isset(self::BOXES[StateFips::normalizeFips($stateFips)]);
    }
}
