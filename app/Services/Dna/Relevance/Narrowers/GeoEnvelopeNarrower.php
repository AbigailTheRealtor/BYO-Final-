<?php

namespace App\Services\Dna\Relevance\Narrowers;

use App\Services\Dna\Relevance\CandidateNarrower;
use App\Services\Dna\Relevance\MatchDirection;
use App\Services\Dna\Relevance\NarrowingContext;
use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;

/**
 * GeoEnvelopeNarrower — Matching V2 consumption slice 2B — OPTIONAL ("where safe").
 *
 * Keeps only candidate listings that fall within the subject seeker's declared
 * geography: any radius (exact Haversine), any drawn polygon (exact
 * point-in-polygon), or a matching preferred city / ZIP / county. Runs only when
 * hard_filters_enabled is on.
 *
 * "Where safe" = fail-open on every ambiguity:
 *   - Direction is DemandToListings only (OD-5). The reverse direction would need
 *     each candidate seeker's envelope (N criteria loads) and is deferred — this
 *     narrower is a NO-OP for ListingToDemands.
 *   - No subject criteria, or subject declared no geography at all → NO-OP.
 *   - A candidate with no geo signal (no point and no city/zip/county) → KEEP.
 *
 * Exact PHP geometry (Haversine + PIP) is run directly over the already-capped,
 * already-gated set (small N), which subsumes the coarse bounding-box pre-filter
 * used by the SQL/OData paths.
 *
 * @see docs/matching-v2-consumption-slice-2b-narrowing-compliance-scope.md §4.3
 */
class GeoEnvelopeNarrower implements CandidateNarrower
{
    private const EARTH_RADIUS_MILES = 3958.8;

    public function narrow(array $tuples, NarrowingContext $context): array
    {
        // Deferred: reverse-direction geo narrowing (OD-5).
        if ($context->direction !== MatchDirection::DemandToListings) {
            return $tuples;
        }

        $criteria = $context->subjectCriteria;
        if ($criteria === null || ! $this->hasAnyEnvelope($criteria)) {
            return $tuples; // no geographic constraint declared → nothing to narrow
        }

        $cities   = $this->lowerSet($criteria->preferredCities);
        $zips      = $this->stringSet($criteria->preferredZipCodes);
        $counties = $this->lowerSet($criteria->preferredCounties);

        return array_values(array_filter($tuples, function (array $tuple) use ($context, $criteria, $cities, $zips, $counties) {
            $profile = $context->profileFor($tuple);
            if ($profile === null || $profile->hasNoGeoSignal()) {
                return true; // fail-open: cannot evaluate geography
            }

            // Exact geometry against declared radius/polygon envelopes.
            if ($profile->hasGeoPoint()
                && $this->matchesGeometry($criteria, $profile->lat, $profile->lng)) {
                return true;
            }

            // Textual city / ZIP / county match.
            if ($profile->city !== null && isset($cities[strtolower($profile->city)])) {
                return true;
            }
            if ($profile->zip !== null && isset($zips[$profile->zip])) {
                return true;
            }
            if ($profile->county !== null && isset($counties[strtolower($profile->county)])) {
                return true;
            }

            return false;
        }));
    }

    private function hasAnyEnvelope(BuyerCriteriaPayload $c): bool
    {
        return ! empty($c->radiusSearches)
            || ! empty($c->polygons)
            || ! empty($c->preferredCities)
            || ! empty($c->preferredZipCodes)
            || ! empty($c->preferredCounties);
    }

    private function matchesGeometry(BuyerCriteriaPayload $criteria, float $lat, float $lng): bool
    {
        foreach ($criteria->radiusSearches as $radius) {
            $centerLat = $this->coord($radius, 'lat');
            $centerLng = $this->coord($radius, 'lng');
            $miles     = (float) ($radius['radius_miles'] ?? 0);
            if ($centerLat === null || $centerLng === null || $miles <= 0) {
                continue;
            }
            if ($this->haversineMiles($lat, $lng, $centerLat, $centerLng) <= $miles) {
                return true;
            }
        }

        foreach ($criteria->polygons as $polygon) {
            $path = is_array($polygon['path'] ?? null) ? $polygon['path'] : null;
            if ($path !== null && count($path) >= 3 && $this->pointInPolygon($lat, $lng, $path)) {
                return true;
            }
        }

        return false;
    }

    /** Supports flat {lat,lng} and legacy {center:{lat,lng}}. */
    private function coord(array $radius, string $key): ?float
    {
        if (isset($radius[$key]) && is_numeric($radius[$key])) {
            return (float) $radius[$key];
        }
        if (isset($radius['center'][$key]) && is_numeric($radius['center'][$key])) {
            return (float) $radius['center'][$key];
        }
        return null;
    }

    private function haversineMiles(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return self::EARTH_RADIUS_MILES * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Standard ray-casting point-in-polygon over a path of {lat,lng} vertices.
     *
     * @param array<int,array{lat:mixed,lng:mixed}> $path
     */
    private function pointInPolygon(float $lat, float $lng, array $path): bool
    {
        $inside = false;
        $n = count($path);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $yi = (float) ($path[$i]['lat'] ?? 0);
            $xi = (float) ($path[$i]['lng'] ?? 0);
            $yj = (float) ($path[$j]['lat'] ?? 0);
            $xj = (float) ($path[$j]['lng'] ?? 0);

            $intersect = (($yi > $lat) !== ($yj > $lat))
                && ($lng < ($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 1e-12) + $xi);
            if ($intersect) {
                $inside = ! $inside;
            }
        }
        return $inside;
    }

    /** @param array<int,mixed> $values */
    private function lowerSet(array $values): array
    {
        $set = [];
        foreach ($values as $v) {
            if (is_string($v) && trim($v) !== '') {
                $set[strtolower(trim($v))] = true;
            }
        }
        return $set;
    }

    /** @param array<int,mixed> $values */
    private function stringSet(array $values): array
    {
        $set = [];
        foreach ($values as $v) {
            if ((is_string($v) || is_int($v)) && trim((string) $v) !== '') {
                $set[trim((string) $v)] = true;
            }
        }
        return $set;
    }
}
