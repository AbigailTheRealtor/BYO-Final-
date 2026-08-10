<?php

namespace App\Services\Location\Coordinates\Adapters;

/**
 * The one place that decides whether a stored latitude/longitude pair is a real
 * point on the earth.
 *
 * WHY THIS IS SHARED RATHER THAN DUPLICATED
 * -----------------------------------------
 * Both local adapters read coordinates out of a database column that has been
 * accepting whatever was handed to it for as long as the column has existed:
 * empty strings, the string "0", nulls, and — in the case of `decimal(10,7)`
 * columns fed from a feed — values that round-tripped through a cast. If each
 * adapter wrote its own guard, the two would disagree within a release, and the
 * disagreement would show up as "the same property resolves differently
 * depending on which rung answered", which is close to impossible to diagnose
 * from a bug report.
 *
 * WHAT COUNTS AS INVALID
 * ----------------------
 * Out of range, non-finite, or Null Island. The last one deserves a note: (0, 0)
 * is a valid coordinate in the Gulf of Guinea and an invalid coordinate
 * everywhere in this application's problem domain. It is also the single most
 * common corrupt value in geospatial data, because it is what an unset numeric
 * column, a failed cast and a truncated feed all produce. Treating it as a real
 * point would put properties in the ocean; treating it as missing costs us
 * nothing, because this platform lists US real estate.
 *
 * The near-zero tolerance exists for the same reason: a value that survived a
 * float round-trip as 1.0e-14 is the same corruption, not a property.
 */
final class CoordinateValidator
{
    /**
     * How close to (0, 0) still counts as Null Island. Far tighter than any
     * real-world coordinate error and far looser than float noise.
     */
    private const NULL_ISLAND_EPSILON = 0.000001;

    /**
     * Cast a stored value to a float, or null when it cannot honestly be one.
     *
     * Deliberately strict about strings. `(float) 'abc'` is 0.0 in PHP, which
     * would turn unparseable junk into Null Island and then — without this —
     * into a coordinate. Empty strings and nulls are simply "no value".
     */
    public static function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }

        if (is_string($value) && is_numeric(trim($value))) {
            return (float) trim($value);
        }

        return null;
    }

    /** True when both values are present, finite, in range, and not Null Island. */
    public static function isValidPair(?float $latitude, ?float $longitude): bool
    {
        if ($latitude === null || $longitude === null) {
            return false;
        }

        if (! is_finite($latitude) || ! is_finite($longitude)) {
            return false;
        }

        if ($latitude < -90.0 || $latitude > 90.0) {
            return false;
        }

        if ($longitude < -180.0 || $longitude > 180.0) {
            return false;
        }

        return ! self::isNullIsland($latitude, $longitude);
    }

    /** True when the pair is indistinguishable from (0, 0). */
    public static function isNullIsland(float $latitude, float $longitude): bool
    {
        return abs($latitude) < self::NULL_ISLAND_EPSILON
            && abs($longitude) < self::NULL_ISLAND_EPSILON;
    }
}
