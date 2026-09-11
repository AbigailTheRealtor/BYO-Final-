<?php

namespace App\Support\Geo;

/**
 * Straight-line (great-circle) distance between two coordinates, in miles — computed locally.
 *
 * ONE definition for the Stellar matcher. Radius searches and Important Places are measured with
 * the same formula and the same Earth radius, so "within 3 miles" means the same thing whichever
 * criterion asked. The Haversine form, R = 3958.8 mi — the value the radius scoring has always
 * used, so moving it here changed no radius score.
 *
 * No provider, no network, no routing. A straight line is exactly what "within N miles" promises,
 * and it costs nothing per property; a routed or travel-time distance would need an engine this
 * application does not have (see the retired "within minutes" Important Place option).
 */
final class GreatCircleDistance
{
    public const EARTH_RADIUS_MILES = 3958.8;

    public static function miles(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return self::EARTH_RADIUS_MILES * 2 * asin(sqrt(min(1.0, $a)));
    }

    /**
     * A coordinate a distance may be measured from: numeric, finite, in range, and not 0,0.
     *
     * 0,0 is refused because it is what a missing value becomes after a `(float)` cast — it is in
     * the Gulf of Guinea, never a listing or a client's workplace — and a distance measured from it
     * is thousands of miles of fiction presented as a fact.
     */
    public static function isCoordinate($lat, $lng): bool
    {
        if (!is_numeric($lat) || !is_numeric($lng)) {
            return false;
        }

        $lat = (float) $lat;
        $lng = (float) $lng;

        return is_finite($lat) && is_finite($lng)
            && $lat >= -90.0 && $lat <= 90.0
            && $lng >= -180.0 && $lng <= 180.0
            && !($lat == 0.0 && $lng == 0.0);
    }
}
