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
        $hasCity    = !empty($criteria->preferredCities);
        $hasZip     = !empty($criteria->preferredZipCodes);
        $hasCounty  = !empty($criteria->preferredCounties);

        if (!$hasRadius && !$hasCity && !$hasZip && !$hasCounty) {
            return;
        }

        if ($hasRadius) {
            // Apply bounding box for each radius search (OR across multiple radii).
            $query->where(function (Builder $outer) use ($criteria, $hasCity, $hasZip, $hasCounty) {
                foreach ($criteria->radiusSearches as $radiusSearch) {
                    $centerLat   = (float) ($radiusSearch['center']['lat']  ?? 0);
                    $centerLng   = (float) ($radiusSearch['center']['lng']  ?? 0);
                    $radiusMiles = (float) ($radiusSearch['radius_miles']   ?? 0);

                    if ($radiusMiles <= 0) {
                        continue;
                    }

                    $latDelta = $radiusMiles / self::LAT_MILES_PER_DEGREE;

                    // Longitude degrees per mile shrinks as latitude increases:
                    //   miles_per_lng_degree ≈ 69.0 × cos(lat)
                    // Using the center latitude of each radius search gives a much
                    // better bounding box than a fixed constant (53.0 was a rough
                    // Florida-specific approximation). The bounding box is still a
                    // coarse pre-filter; Haversine in the scorer is the exact gate.
                    $lngMilesPerDegree = self::LAT_MILES_PER_DEGREE * cos(deg2rad(abs($centerLat)));
                    $lngDelta          = ($lngMilesPerDegree > 0)
                        ? $radiusMiles / $lngMilesPerDegree
                        : $radiusMiles / 53.0; // fallback for equatorial/zero-lat edge case

                    $outer->orWhere(function (Builder $q) use ($centerLat, $centerLng, $latDelta, $lngDelta) {
                        $q->whereBetween('latitude',  [$centerLat - $latDelta, $centerLat + $latDelta])
                          ->whereBetween('longitude', [$centerLng - $lngDelta, $centerLng + $lngDelta]);
                    });
                }

                // City/ZIP/county fallback within the outer OR
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
            // No radius — city/ZIP/county filter
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
