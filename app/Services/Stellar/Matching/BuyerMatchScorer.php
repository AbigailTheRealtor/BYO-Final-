<?php

namespace App\Services\Stellar\Matching;

use App\Models\BridgeProperty;
use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;
use App\Services\Stellar\Matching\DTO\BuyerMatchResult;

class BuyerMatchScorer
{
    private const EARTH_RADIUS_MILES = 3958.8;

    /**
     * Phase 1 price proximity maximum (20 pts).
     * Full category weight is 25 pts; the remaining 5 pts are the price-reduction
     * signal added in Phase 2. BuyerMatchResultBuilder references this constant
     * for the tradeoff threshold so there is a single source of truth.
     */
    public const PRICE_PROXIMITY_MAX_PTS = 20;

    /**
     * Phase 1 location category maximum: 24 pts.
     *   Radius proximity:  18 pts
     *   City/ZIP match:     6 pts  (or County: 3 pts)
     *   Sub-market:         0 pts  (Phase 2)
     *   Subdivision:        0 pts  (Phase 2)
     * Full category weight is 30 pts; the 6-pt gap closes in Phase 2.
     * This is intentional per the implementation plan and does NOT indicate a bug.
     */
    public const LOCATION_MAX_PHASE1_PTS = 24;

    public function scoreAll(iterable $candidates, BuyerCriteriaPayload $criteria): array
    {
        $results = [];

        foreach ($candidates as $listing) {
            $results[] = $this->score($listing, $criteria);
        }

        return $results;
    }

    public function score(BridgeProperty $listing, BuyerCriteriaPayload $criteria): BuyerMatchResult
    {
        $rawJson = $listing->raw_json ? json_decode($listing->raw_json, true) : [];
        if (!is_array($rawJson)) {
            $rawJson = [];
        }

        $locationScore     = $this->scoreLocation($listing, $criteria);
        $priceScore        = $this->scorePrice($listing, $criteria);
        $sizeScore         = $this->scoreSize($listing, $criteria);
        $propertyTypeScore = $this->scorePropertyType($listing, $criteria);
        $amenityScore      = $this->scoreAmenities($listing, $criteria);
        $financialScore    = $this->scoreFinancial($listing, $criteria);
        $lifestyleScore    = $this->scoreLifestyle($listing, $criteria, $rawJson);

        $total = (int) round(
            $locationScore['score'] +
            $priceScore['score'] +
            $sizeScore['score'] +
            $propertyTypeScore['score'] +
            $amenityScore['score'] +
            $financialScore['score'] +
            $lifestyleScore['score']
        );

        $total = max(0, min(100, $total));

        $categoryScores = [
            'location'      => (int) round($locationScore['score']),
            'price'         => (int) round($priceScore['score']),
            'size'          => (int) round($sizeScore['score']),
            'property_type' => (int) round($propertyTypeScore['score']),
            'amenities'     => (int) round($amenityScore['score']),
            'financial'     => (int) round($financialScore['score']),
            'lifestyle'     => (int) round($lifestyleScore['score']),
        ];

        return new BuyerMatchResult(
            listingKey:     $listing->listing_key ?? (string) $listing->id,
            totalScore:     $total,
            categoryScores: $categoryScores,
            listing:        $listing,
            whyThisMatches: [],
            tradeoffs:      [],
            cautionFlags:   [],
            missingData:    []
        );
    }

    // =========================================================================
    // Category 1: Location (30 pts)
    // =========================================================================

    private function scoreLocation(BridgeProperty $listing, BuyerCriteriaPayload $criteria): array
    {
        $proximityScore = 0.0;
        $hasRadiusCriteria = !empty($criteria->radiusSearches);

        $lat = $listing->latitude !== null ? (float) $listing->latitude : null;
        $lng = $listing->longitude !== null ? (float) $listing->longitude : null;

        if ($hasRadiusCriteria) {
            if ($lat !== null && $lng !== null) {
                foreach ($criteria->radiusSearches as $search) {
                    $centerLat   = (float) ($search['center']['lat']  ?? 0);
                    $centerLng   = (float) ($search['center']['lng']  ?? 0);
                    $radiusMiles = (float) ($search['radius_miles']   ?? 0);

                    if ($radiusMiles <= 0) {
                        continue;
                    }

                    $distance = $this->haversineDistance($lat, $lng, $centerLat, $centerLng);

                    if ($distance <= $radiusMiles) {
                        $pts = max(0.0, 18.0 * (1 - $distance / $radiusMiles));
                        $proximityScore = max($proximityScore, $pts);
                    }
                }
            }
            // null lat/lng → proximity stays 0; caution flag handled in ResultBuilder
        }

        // City / ZIP exact match (6 pts) or county match (3 pts)
        $cityZipScore = 0;
        $cityMatch = !empty($criteria->preferredCities) && in_array($listing->city, $criteria->preferredCities);
        $zipMatch  = !empty($criteria->preferredZipCodes) && in_array($listing->postal_code, $criteria->preferredZipCodes);

        if ($cityMatch || $zipMatch) {
            $cityZipScore = 6;
        } elseif (!empty($criteria->preferredCounties) && in_array($listing->county_or_parish, $criteria->preferredCounties)) {
            $cityZipScore = 3;
        }

        // Phase 2: sub-market (3 pts) and subdivision (3 pts) → 0 in Phase 1
        $subMarketScore  = 0;
        $subdivisionScore = 0;

        $total = $proximityScore + $cityZipScore + $subMarketScore + $subdivisionScore;

        // Clamp to the Phase-1 achievable maximum (24 pts, not the full 30-pt
        // category weight) so the cap is always consistent with the documented
        // constant. Phase 2 will raise this to LOCATION_MAX_PHASE1_PTS + 6 once
        // sub-market and subdivision dimensions are implemented.
        return ['score' => min((float) self::LOCATION_MAX_PHASE1_PTS, $total)];
    }

    // =========================================================================
    // Category 2: Price (25 pts)
    // =========================================================================

    private function scorePrice(BridgeProperty $listing, BuyerCriteriaPayload $criteria): array
    {
        $listPrice = $listing->list_price !== null ? (float) $listing->list_price : null;

        if ($listPrice === null) {
            return ['score' => 0.0];
        }

        $proximityScore = 0.0;

        if ($criteria->idealPrice !== null) {
            $idealPrice = (float) $criteria->idealPrice;
            if ($idealPrice > 0) {
                $ratio = abs($listPrice - $idealPrice) / $idealPrice;
                $proximityScore = max(0.0, 20.0 * (1 - $ratio));
            }
        } elseif ($criteria->maxPrice !== null) {
            $maxPrice = (float) $criteria->maxPrice;
            if ($maxPrice > 0) {
                if ($listPrice <= 0.9 * $maxPrice) {
                    $proximityScore = 20.0;
                } elseif ($listPrice <= $maxPrice) {
                    $proximityScore = 15.0;
                } else {
                    $proximityScore = 0.0;
                }
            }
        } else {
            $proximityScore = 20.0;
        }

        // Phase 2: price reduction signal (+5) → 0 in Phase 1
        $reductionScore = 0.0;

        return ['score' => min(25.0, $proximityScore + $reductionScore)];
    }

    // =========================================================================
    // Category 3: Size (15 pts)
    // =========================================================================

    private function scoreSize(BridgeProperty $listing, BuyerCriteriaPayload $criteria): array
    {
        // Living area (7 pts)
        $livingAreaScore = $this->rangeScore(
            value:  $listing->living_area !== null ? (float) $listing->living_area : null,
            min:    $criteria->minSqft !== null ? (float) $criteria->minSqft : null,
            max:    $criteria->maxSqft !== null ? (float) $criteria->maxSqft : null,
            maxPts: 7.0,
            hasPreference: ($criteria->minSqft !== null || $criteria->maxSqft !== null),
            nullScore: 7.0
        );

        // Lot size (4 pts) — null listing value → 2 (neutral mid-score)
        $lotScore = $this->rangeScore(
            value:  $listing->lot_size_sqft !== null ? (float) $listing->lot_size_sqft : null,
            min:    $criteria->minLotSqft !== null ? (float) $criteria->minLotSqft : null,
            max:    $criteria->maxLotSqft !== null ? (float) $criteria->maxLotSqft : null,
            maxPts: 4.0,
            hasPreference: ($criteria->minLotSqft !== null || $criteria->maxLotSqft !== null),
            nullScore: 2.0
        );

        // Year built (4 pts)
        $yearBuiltScore = $this->scoreYearBuilt($listing, $criteria);

        return ['score' => $livingAreaScore + $lotScore + $yearBuiltScore];
    }

    private function rangeScore(
        ?float $value,
        ?float $min,
        ?float $max,
        float $maxPts,
        bool $hasPreference,
        float $nullScore = 0.0
    ): float {
        if (!$hasPreference) {
            return $maxPts;
        }

        if ($value === null) {
            return $nullScore;
        }

        $effectiveMin = $min ?? 0.0;
        $effectiveMax = $max ?? PHP_FLOAT_MAX;

        if ($value >= $effectiveMin && $value <= $effectiveMax) {
            return $maxPts;
        }

        // Compute deviation ratio outside range
        $deviationRatio = 0.0;
        if ($value < $effectiveMin && $effectiveMin > 0) {
            $deviationRatio = ($effectiveMin - $value) / $effectiveMin;
        } elseif ($value > $effectiveMax && $effectiveMax > 0 && $effectiveMax !== PHP_FLOAT_MAX) {
            $deviationRatio = ($value - $effectiveMax) / $effectiveMax;
        }

        // Linear decay to 0 at ±30% outside range edge
        $score = $maxPts * max(0.0, 1.0 - ($deviationRatio / 0.30));

        return max(0.0, $score);
    }

    private function scoreYearBuilt(BridgeProperty $listing, BuyerCriteriaPayload $criteria): float
    {
        $hasPreference = ($criteria->yearBuiltMin !== null || $criteria->yearBuiltMax !== null);

        if (!$hasPreference) {
            return 4.0;
        }

        $yearBuilt = $listing->year_built;

        if ($yearBuilt === null) {
            return 0.0;
        }

        $min = $criteria->yearBuiltMin;
        $max = $criteria->yearBuiltMax;

        $effectiveMin = $min ?? 0;
        $effectiveMax = $max ?? 9999;

        if ($yearBuilt >= $effectiveMin && $yearBuilt <= $effectiveMax) {
            return 4.0;
        }

        $gapToMin = $min !== null ? ($effectiveMin - $yearBuilt) : PHP_INT_MAX;
        $gapToMax = $max !== null ? ($yearBuilt - $effectiveMax) : PHP_INT_MAX;
        $gap      = min($gapToMin, $gapToMax);

        if ($gap <= 10) {
            return 2.0;
        }

        return 0.0;
    }

    // =========================================================================
    // Category 4: Property Type (10 pts)
    // =========================================================================

    private function scorePropertyType(BridgeProperty $listing, BuyerCriteriaPayload $criteria): array
    {
        // Type exact match (5 pts) — always 5 for listings in candidate set (type is hard filter)
        $typeScore = 5.0;

        // Subtype match (5 pts)
        $subTypeScore = 0.0;

        if (empty($criteria->propertySubTypes)) {
            $subTypeScore = 2.0;
        } elseif ($listing->property_sub_type !== null &&
                  in_array($listing->property_sub_type, $criteria->propertySubTypes)) {
            $subTypeScore = 5.0;
        } else {
            $subTypeScore = 0.0;
        }

        return ['score' => $typeScore + $subTypeScore];
    }

    // =========================================================================
    // Category 5: Amenities (10 pts)
    // =========================================================================

    private function scoreAmenities(BridgeProperty $listing, BuyerCriteriaPayload $criteria): array
    {
        $expressed = [];

        if ($criteria->wantsPool !== null) {
            $expressed['pool'] = [
                'max'    => 4.0,
                'earned' => ($listing->pool_private_yn === true) ? 4.0 : 0.0,
            ];
        }

        if ($criteria->wantsGarage !== null) {
            $expressed['garage'] = [
                'max'    => 3.0,
                'earned' => ($listing->garage_yn === true) ? 3.0 : 0.0,
            ];
        }

        if ($criteria->wantsWaterfront !== null) {
            $waterfront = 0.0;
            if ($listing->waterfront_yn === true) {
                $waterfront = 2.0;
            } elseif ($listing->water_view_yn === true) {
                $waterfront = 1.0;
            }
            $expressed['waterfront'] = ['max' => 2.0, 'earned' => $waterfront];
        }

        if ($criteria->wantsAnyView !== null) {
            $viewEarned = ($listing->view_yn === true || $listing->water_view_yn === true) ? 1.0 : 0.0;
            $expressed['any_view'] = ['max' => 1.0, 'earned' => $viewEarned];
        }

        if (empty($expressed)) {
            return ['score' => 10.0];
        }

        $maxForExpressed    = array_sum(array_column($expressed, 'max'));
        $earnedForExpressed = array_sum(array_column($expressed, 'earned'));

        if ($maxForExpressed <= 0) {
            return ['score' => 10.0];
        }

        $normalized = ($earnedForExpressed / $maxForExpressed) * 10.0;

        return ['score' => min(10.0, $normalized)];
    }

    // =========================================================================
    // Category 6: Financial / Fees (5 pts)
    // =========================================================================

    private function scoreFinancial(BridgeProperty $listing, BuyerCriteriaPayload $criteria): array
    {
        if ($criteria->maxMonthlyTotalBurden === null) {
            return ['score' => 5.0];
        }

        $hoaMonthly    = $listing->association_fee !== null ? (float) $listing->association_fee : 0.0;
        $taxAnnual     = $listing->tax_annual_amount !== null ? (float) $listing->tax_annual_amount : 0.0;
        $taxMonthly    = $taxAnnual / 12.0;
        $totalBurden   = $hoaMonthly + $taxMonthly;

        $ceiling = (float) $criteria->maxMonthlyTotalBurden;

        if ($ceiling <= 0) {
            return ['score' => 0.0];
        }

        $score = max(0.0, 5.0 * (1.0 - $totalBurden / $ceiling));

        return ['score' => min(5.0, $score)];
    }

    // =========================================================================
    // Category 7: Lifestyle / Context (5 pts) — Tier 2 raw_json extraction
    // =========================================================================

    private function scoreLifestyle(BridgeProperty $listing, BuyerCriteriaPayload $criteria, array $rawJson): array
    {
        // Community features overlap (2 pts)
        $communityScore = 0.0;
        if (!empty($criteria->communityFeatureKeywords)) {
            $features   = array_merge(
                $this->extractStringArray($rawJson, 'CommunityFeatures'),
                $this->extractStringArray($rawJson, 'AssociationAmenities')
            );
            $matchCount = $this->countKeywordMatches($criteria->communityFeatureKeywords, $features);
            if ($matchCount >= 2) {
                $communityScore = 2.0;
            } elseif ($matchCount === 1) {
                $communityScore = 1.0;
            }
        }

        // Green / energy efficiency (1 pt)
        $greenScore = 0.0;
        if ($criteria->wantsEnergyEfficient === true) {
            $greenFeatures = $this->extractStringArray($rawJson, 'GreenEnergyEfficient');
            $greenBuild    = $this->extractStringArray($rawJson, 'GreenBuildingVerificationType');
            if (!empty($greenFeatures) || !empty($greenBuild)) {
                $greenScore = 1.0;
            }
        }

        // New construction preference (1 pt)
        $newConstructionScore = 0.0;
        if ($criteria->wantsNewConstruction === true && $listing->new_construction_yn === true) {
            $newConstructionScore = 1.0;
        }

        // Pet-friendly community (1 pt)
        $petScore = 0.0;
        if ($criteria->wantsPetFriendly === true) {
            $petsAllowed = $listing->pets_allowed;
            if ($petsAllowed !== null && strtolower(trim($petsAllowed)) !== 'no') {
                $petScore = 1.0;
            }
        }

        $total = $communityScore + $greenScore + $newConstructionScore + $petScore;

        return ['score' => min(5.0, $total)];
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        $c = 2 * asin(sqrt($a));

        return self::EARTH_RADIUS_MILES * $c;
    }

    private function extractStringArray(array $data, string $key): array
    {
        $val = $data[$key] ?? null;

        if (is_string($val)) {
            return array_filter([$val], fn($v) => $v !== '');
        }

        if (!is_array($val)) {
            return [];
        }

        return array_values(array_filter(
            array_map('strval', $val),
            fn($v) => $v !== ''
        ));
    }

    private function countKeywordMatches(array $keywords, array $features): int
    {
        $count = 0;
        $normalizedFeatures = array_map('strtolower', $features);

        foreach ($keywords as $keyword) {
            $lowerKeyword = strtolower(trim($keyword));
            foreach ($normalizedFeatures as $feature) {
                if (str_contains($feature, $lowerKeyword)) {
                    $count++;
                    break;
                }
            }
        }

        return $count;
    }
}
