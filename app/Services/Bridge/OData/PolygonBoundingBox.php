<?php

namespace App\Services\Bridge\OData;

use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;

/**
 * Computes geographic bounding boxes from a BuyerCriteriaPayload for use in
 * OData $filter clauses. Returns one bounding-box array per search area (radius
 * search or drawn polygon), which the caller can combine into OR clauses.
 *
 * ┌─────────────────────────────────────────────────────────────────────────┐
 * │  IMPORTANT — COARSE PRE-FILTER ONLY                                     │
 * │                                                                         │
 * │  All bounding boxes produced here are intentionally over-approximate:   │
 * │                                                                         │
 * │  • Radius search → axis-aligned square, NOT a circle.                   │
 * │    Corner-to-center distance can be ~√2× the radius (e.g. a 25-mile    │
 * │    radius produces a box whose corners are ~35 miles from the center).  │
 * │    Properties in the corners are outside the user's intended area.      │
 * │                                                                         │
 * │  • Polygon → min/max lat/lng envelope of the drawn path.                │
 * │    The rectangle covers the full extent of the polygon but includes     │
 * │    regions outside the actual shape (e.g. the empty arm of an L).      │
 * │                                                                         │
 * │  Exact geographic validation — Haversine distance for radius searches   │
 * │  and point-in-polygon (PIP) tests for drawn areas — is performed by     │
 * │  the matching engine (BuyerMatchService / TenantMatchService) AFTER     │
 * │  Bridge returns the candidate set.  These bounding boxes only limit     │
 * │  the Bridge API candidate set; they are not a geographic gate.          │
 * └─────────────────────────────────────────────────────────────────────────┘
 *
 * This mirrors the logic in BuyerMatchQueryBuilder::applyPolygonBoundingBoxes()
 * and the inline radius-delta calculation in applyGeographicFilter(), but is
 * expressed as pure-PHP value objects that both OData filter builders can share.
 *
 * OData field names (Bridge Interactive API):
 *   Latitude  — decimal degrees, WGS-84
 *   Longitude — decimal degrees, WGS-84
 *
 * Source: Bridge Interactive OData metadata endpoint:
 *   https://api.bridgedataoutput.com/api/v2/OData/{dataset}/$metadata
 */
class PolygonBoundingBox
{
    /**
     * Approximate miles per degree of latitude (constant everywhere).
     */
    private const LAT_MILES_PER_DEGREE = 69.0;

    /**
     * Derive one bounding-box per search area from a payload's radius searches
     * and drawn polygons.
     *
     * Each bounding box is an associative array:
     *   ['min_lat' => float, 'max_lat' => float, 'min_lng' => float, 'max_lng' => float]
     *
     * Returns null when no geometry is present (no radius searches, no polygons).
     * Returns an empty array only when every geometry entry is malformed.
     *
     * @param  BuyerCriteriaPayload $payload
     * @return array[]|null  Array of bounding-box arrays, or null when no geometry.
     */
    public static function fromPayload(BuyerCriteriaPayload $payload): ?array
    {
        $hasRadius  = !empty($payload->radiusSearches);
        $hasPolygon = !empty($payload->polygons);

        if (!$hasRadius && !$hasPolygon) {
            return null;
        }

        $boxes = [];

        foreach ($payload->radiusSearches as $radiusSearch) {
            $box = self::boxFromRadius($radiusSearch);
            if ($box !== null) {
                $boxes[] = $box;
            }
        }

        foreach ($payload->polygons as $polygon) {
            $box = self::boxFromPolygon($polygon);
            if ($box !== null) {
                $boxes[] = $box;
            }
        }

        return $boxes;
    }

    /**
     * Compute a bounding box from a single radius search entry.
     *
     * Supports flat {lat, lng} (canonical) and legacy {center: {lat, lng}} shapes.
     * Returns null when the radius is non-positive OR when no center coordinates
     * are explicitly present in the entry (prevents emitting a bbox around (0,0)
     * — the Gulf of Guinea — for malformed entries that omit coordinates).
     *
     * @param  array $radiusSearch
     * @return array|null
     */
    private static function boxFromRadius(array $radiusSearch): ?array
    {
        $hasFlat   = isset($radiusSearch['lat'], $radiusSearch['lng']);
        $hasNested = isset($radiusSearch['center']['lat'], $radiusSearch['center']['lng']);

        if (!$hasFlat && !$hasNested) {
            return null;
        }

        $centerLat = $hasFlat
            ? (float) $radiusSearch['lat']
            : (float) $radiusSearch['center']['lat'];

        $centerLng = $hasFlat
            ? (float) $radiusSearch['lng']
            : (float) $radiusSearch['center']['lng'];

        $radiusMiles = (float) ($radiusSearch['radius_miles'] ?? 0);

        if ($radiusMiles <= 0) {
            return null;
        }

        $latDelta = $radiusMiles / self::LAT_MILES_PER_DEGREE;

        $lngMilesPerDegree = self::LAT_MILES_PER_DEGREE * cos(deg2rad(abs($centerLat)));
        $lngDelta = ($lngMilesPerDegree > 0)
            ? $radiusMiles / $lngMilesPerDegree
            : $radiusMiles / 53.0;

        return [
            'min_lat' => $centerLat - $latDelta,
            'max_lat' => $centerLat + $latDelta,
            'min_lng' => $centerLng - $lngDelta,
            'max_lng' => $centerLng + $lngDelta,
        ];
    }

    /**
     * Compute a bounding box from the min/max lat/lng envelope of a polygon's path.
     *
     * Expects $polygon['path'] to be an array of {lat, lng} vertex objects.
     * Returns null when fewer than 3 valid vertices are present.
     *
     * @param  array $polygon
     * @return array|null
     */
    private static function boxFromPolygon(array $polygon): ?array
    {
        if (!isset($polygon['path']) || !is_array($polygon['path'])) {
            return null;
        }

        $path = $polygon['path'];
        if (count($path) < 3) {
            return null;
        }

        $lats = array_filter(array_column($path, 'lat'), fn($v) => is_numeric($v));
        $lngs = array_filter(array_column($path, 'lng'), fn($v) => is_numeric($v));

        if (empty($lats) || empty($lngs)) {
            return null;
        }

        return [
            'min_lat' => (float) min($lats),
            'max_lat' => (float) max($lats),
            'min_lng' => (float) min($lngs),
            'max_lng' => (float) max($lngs),
        ];
    }
}
