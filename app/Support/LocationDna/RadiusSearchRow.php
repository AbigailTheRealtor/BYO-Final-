<?php

namespace App\Support\LocationDna;

/**
 * RadiusSearchRow — the one reading of a stored `radius_searches` entry.
 *
 * TWO SHAPES EXIST IN STORED DATA, AND BOTH ARE LEGITIMATE.
 *
 *   flat    { "address"|"label": "…", "lat": 27.9, "lng": -82.4, "radius_miles": 5 }
 *   nested  { "center": { "lat": 27.9, "lng": -82.4 }, "radius_miles": 5, "label": "…" }
 *
 * Flat is canonical: it is what `map-input`'s serializer writes on every Buyer and Tenant
 * surface, under both renderers, and it is the shape
 * `GeographicMatchingCurrentContractTest` pins for the live matcher. Nested is the older
 * shape, still present in rows written before the widget settled and still produced by a
 * number of fixtures.
 *
 * The matchers (`BuyerMatchQueryBuilder`, `BuyerMatchScorer`, `PolygonBoundingBox`) have
 * always read both. Four other readers read ONLY nested — the Google detail map, the flood
 * and school-district lookups, and the enrichment runner — so every radius search saved by
 * the current UI was silently skipped by all four: no circle drawn, no flood or school
 * overlay derived from it, no POI geometry. Nothing about the stored row was wrong; the
 * readers disagreed about it. This class is the single reading they now share, so they
 * cannot drift apart again.
 *
 * Flat keys are consulted first, then `center` — the same order the matchers use, so a row
 * carrying both answers identically everywhere.
 *
 * READ-ONLY BY DESIGN. It never rewrites, re-serializes or "upgrades" a stored row: a row
 * keeps whichever shape it was saved in, and reading it here changes nothing about it.
 */
final class RadiusSearchRow
{
    /**
     * The centre as `['lat' => float, 'lng' => float]`, or null when the row does not carry
     * a usable one. "Usable" means numeric, finite and on the globe — a row pointing at
     * lat 900 is not a place, and treating it as one would put a circle nowhere.
     */
    public static function center($row): ?array
    {
        if (!is_array($row)) {
            return null;
        }

        if (isset($row['lat'], $row['lng'])) {
            $point = self::point($row['lat'], $row['lng']);
            if ($point !== null) {
                return $point;
            }
        }

        $center = $row['center'] ?? null;
        if (is_array($center) && isset($center['lat'], $center['lng'])) {
            return self::point($center['lat'], $center['lng']);
        }

        return null;
    }

    /** The radius in miles when it is a positive finite number, otherwise null. */
    public static function miles($row): ?float
    {
        if (!is_array($row) || !is_numeric($row['radius_miles'] ?? null)) {
            return null;
        }

        $miles = (float) $row['radius_miles'];

        return is_finite($miles) && $miles > 0 ? $miles : null;
    }

    /**
     * Centre and radius together — `['lat', 'lng', 'radius_miles']` — or null unless BOTH are
     * usable. This is what a geometric consumer wants: a centre with no radius, or a radius
     * with no centre, describes no area.
     */
    public static function circle($row): ?array
    {
        $center = self::center($row);
        $miles  = self::miles($row);

        if ($center === null || $miles === null) {
            return null;
        }

        return $center + ['radius_miles' => $miles];
    }

    /** The human description the row was saved with — its address, else its label — or ''. */
    public static function description($row): string
    {
        if (!is_array($row)) {
            return '';
        }

        foreach (['address', 'label'] as $key) {
            $value = $row[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }

    private static function point($lat, $lng): ?array
    {
        if (!is_numeric($lat) || !is_numeric($lng)) {
            return null;
        }

        $lat = (float) $lat;
        $lng = (float) $lng;

        if (!is_finite($lat) || !is_finite($lng) || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return null;
        }

        return ['lat' => $lat, 'lng' => $lng];
    }
}
