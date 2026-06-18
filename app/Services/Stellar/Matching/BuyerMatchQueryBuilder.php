<?php

namespace App\Services\Stellar\Matching;

use App\Models\BridgeProperty;
use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;
use Illuminate\Database\Eloquent\Builder;

class BuyerMatchQueryBuilder
{
    private const DEFAULT_CANDIDATE_CAP = 200;

    /**
     * Approximate miles per degree of latitude (constant everywhere).
     */
    private const LAT_MILES_PER_DEGREE = 69.0;

    /**
     * Over-fetch multiplier applied before IDX filtering.
     *
     * IDXParticipationYN is near-100% true in the live feed, but a small buffer
     * ensures that after BuyerMatchService removes any non-IDX rows the scorer
     * still receives close to `candidate_cap` records.
     */
    public const IDX_OVERFETCH_MULTIPLIER = 1.25;

    public function build(BuyerCriteriaPayload $criteria, int $candidateCap = self::DEFAULT_CANDIDATE_CAP): Builder
    {
        $query = BridgeProperty::query();

        // Step 1: Active status (maximum selectivity first)
        $query->where('standard_status', 'Active');

        // Step 2: Property type match
        $query->whereIn('property_type', $criteria->propertyTypes);

        // Step 3: Price ceiling (only when max_price is set).
        // list_price IS NULL is allowed through — Section 6.4 covers null list_price
        // in scoring; exclusion for null+ceiling is handled in the scorer.
        if ($criteria->maxPrice !== null) {
            $query->where(function (Builder $q) use ($criteria) {
                $q->where('list_price', '<=', $criteria->maxPrice)
                  ->orWhereNull('list_price');
            });
        }

        // Step 4: Minimum bedroom count (only when min_bedrooms is set).
        // Spec (Section 4, Step 4): "AND (bedrooms_total >= ? OR bedrooms_total IS NULL)"
        if ($criteria->minBedrooms !== null) {
            $query->where(function (Builder $q) use ($criteria) {
                $q->where('bedrooms_total', '>=', $criteria->minBedrooms)
                  ->orWhereNull('bedrooms_total');
            });
        }

        // Step 5: Minimum bathroom count (only when min_bathrooms is set).
        // Spec (Section 4, Step 5): "AND (bathrooms_total_integer >= ? OR bathrooms_total_integer IS NULL)"
        if ($criteria->minBathrooms !== null) {
            $query->where(function (Builder $q) use ($criteria) {
                $q->where('bathrooms_total_integer', '>=', $criteria->minBathrooms)
                  ->orWhereNull('bathrooms_total_integer');
            });
        }

        // Step 6: Senior community gate (legal compliance).
        if (!$criteria->is55PlusEligible) {
            $query->where(function (Builder $q) {
                $q->where('senior_community_yn', false)
                  ->orWhereNull('senior_community_yn');
            });
        }

        // Step 7: IDX gate — applied in PHP by BuyerMatchService (not SQL)

        // Step 8: Geographic bounding box or city/ZIP/county clauses
        $this->applyGeographicFilter($query, $criteria);

        // Step 9: ORDER BY price proximity, LIMIT with over-fetch buffer.
        // The buffer compensates for IDX-gate removals; BuyerMatchService trims
        // the result back to candidateCap after filtering.
        $fetchLimit = (int) ceil($candidateCap * self::IDX_OVERFETCH_MULTIPLIER);
        $this->applyOrderAndLimit($query, $criteria, $fetchLimit);

        return $query;
    }

    private function applyGeographicFilter(Builder $query, BuyerCriteriaPayload $criteria): void
    {
        $hasRadius  = !empty($criteria->radiusSearches);
        $hasPolygon = !empty($criteria->polygons);
        $hasCity    = !empty($criteria->preferredCities);
        $hasZip     = !empty($criteria->preferredZipCodes);
        $hasCounty  = !empty($criteria->preferredCounties);

        if (!$hasRadius && !$hasPolygon && !$hasCity && !$hasZip && !$hasCounty) {
            return;
        }

        if ($hasRadius || $hasPolygon) {
            // Apply bounding box for each radius search and/or polygon (OR across all).
            // These are coarse pre-filters; exact Haversine / PIP checks happen in the scorer.
            $query->where(function (Builder $outer) use ($criteria, $hasRadius, $hasPolygon, $hasCity, $hasZip, $hasCounty) {
                if ($hasRadius) {
                    foreach ($criteria->radiusSearches as $radiusSearch) {
                        // Support flat {lat, lng} (canonical) and legacy {center: {lat, lng}}
                        $centerLat   = (float) self::extractRadiusLat($radiusSearch);
                        $centerLng   = (float) self::extractRadiusLng($radiusSearch);
                        $radiusMiles = (float) ($radiusSearch['radius_miles'] ?? 0);

                        if ($radiusMiles <= 0) {
                            continue;
                        }

                        $latDelta = $radiusMiles / self::LAT_MILES_PER_DEGREE;

                        // Longitude degrees per mile shrinks as latitude increases:
                        //   miles_per_lng_degree ≈ 69.0 × cos(lat)
                        $lngMilesPerDegree = self::LAT_MILES_PER_DEGREE * cos(deg2rad(abs($centerLat)));
                        $lngDelta          = ($lngMilesPerDegree > 0)
                            ? $radiusMiles / $lngMilesPerDegree
                            : $radiusMiles / 53.0;

                        $outer->orWhere(function (Builder $q) use ($centerLat, $centerLng, $latDelta, $lngDelta) {
                            $q->whereBetween('latitude',  [$centerLat - $latDelta, $centerLat + $latDelta])
                              ->whereBetween('longitude', [$centerLng - $lngDelta, $centerLng + $lngDelta]);
                        });
                    }
                }

                if ($hasPolygon) {
                    // Bounding box pre-filter for each drawn polygon (OR across all polygons).
                    // Scorer performs exact point-in-polygon; bbox just limits the candidate set.
                    $this->applyPolygonBoundingBoxes($outer, $criteria);
                }

                // City/ZIP/county are always ORed in alongside geometry
                if ($hasCity || $hasZip) {
                    $outer->orWhere(function (Builder $q) use ($criteria, $hasCity, $hasZip) {
                        if ($hasCity) {
                            $q->whereIn('city', $criteria->preferredCities);
                        }
                        if ($hasZip) {
                            $q->orWhereIn('postal_code', $criteria->preferredZipCodes);
                        }
                    });
                }

                if ($hasCounty) {
                    $outer->orWhereIn('county_or_parish', $criteria->preferredCounties);
                }
            });
        } else {
            // No geometry — city/ZIP/county only filter
            $query->where(function (Builder $q) use ($criteria, $hasCity, $hasZip, $hasCounty) {
                if ($hasCity) {
                    $q->whereIn('city', $criteria->preferredCities);
                }
                if ($hasZip) {
                    $q->orWhereIn('postal_code', $criteria->preferredZipCodes);
                }
                if ($hasCounty) {
                    $q->orWhereIn('county_or_parish', $criteria->preferredCounties);
                }
            });
        }
    }

    /**
     * Add OR bounding-box clauses for each drawn polygon.
     *
     * The bounding box is the min/max lat/lng envelope of the polygon's path vertices.
     * It is an intentional over-approximation — the scorer's point-in-polygon test
     * is the exact gate; this just limits the DB candidate set.
     */
    private function applyPolygonBoundingBoxes(Builder $outer, BuyerCriteriaPayload $criteria): void
    {
        foreach ($criteria->polygons as $polygon) {
            if (!is_array($polygon) || !isset($polygon['path']) || !is_array($polygon['path'])) {
                continue;
            }

            $path = $polygon['path'];
            if (count($path) < 3) {
                continue;
            }

            $lats = array_filter(array_column($path, 'lat'), fn ($v) => is_numeric($v));
            $lngs = array_filter(array_column($path, 'lng'), fn ($v) => is_numeric($v));

            if (empty($lats) || empty($lngs)) {
                continue;
            }

            $minLat = (float) min($lats);
            $maxLat = (float) max($lats);
            $minLng = (float) min($lngs);
            $maxLng = (float) max($lngs);

            $outer->orWhere(function (Builder $q) use ($minLat, $maxLat, $minLng, $maxLng) {
                $q->whereBetween('latitude',  [$minLat, $maxLat])
                  ->whereBetween('longitude', [$minLng, $maxLng]);
            });
        }
    }

    /**
     * Extract center latitude from a radius search entry.
     * Supports flat {lat, lng} (canonical) and legacy {center: {lat, lng}}.
     */
    private static function extractRadiusLat(array $search): float
    {
        if (isset($search['lat'])) {
            return (float) $search['lat'];
        }
        return (float) ($search['center']['lat'] ?? 0);
    }

    /**
     * Extract center longitude from a radius search entry.
     * Supports flat {lat, lng} (canonical) and legacy {center: {lat, lng}}.
     */
    private static function extractRadiusLng(array $search): float
    {
        if (isset($search['lng'])) {
            return (float) $search['lng'];
        }
        return (float) ($search['center']['lng'] ?? 0);
    }

    private function applyOrderAndLimit(Builder $query, BuyerCriteriaPayload $criteria, int $limit): void
    {
        $referencePrice = $criteria->idealPrice
            ?? ($criteria->maxPrice !== null ? (int) ($criteria->maxPrice * 0.85) : null);

        if ($referencePrice !== null) {
            // Portable NULL-safe ordering: rows with null list_price sort last on both
            // PostgreSQL and MySQL. (list_price IS NULL) evaluates to 0 (false) for
            // non-null rows and 1 (true) for null rows, so non-null rows sort first.
            $query->orderByRaw('(list_price IS NULL), ABS(list_price - ?)', [$referencePrice]);
        }

        $query->limit($limit);
    }
}
