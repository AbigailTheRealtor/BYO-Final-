<?php

namespace App\Services\Stellar\Matching;

use App\Models\BridgeProperty;
use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;
use App\Services\Stellar\Matching\DTO\BuyerMatchResult;

class BuyerMatchResultBuilder
{
    private const STALE_DAYS_THRESHOLD = 60;

    public function buildAll(array $results, BuyerCriteriaPayload $criteria): array
    {
        return array_map(fn(BuyerMatchResult $r) => $this->build($r, $criteria), $results);
    }

    public function build(BuyerMatchResult $result, BuyerCriteriaPayload $criteria): BuyerMatchResult
    {
        $listing = $result->listing;
        $rawJson = $listing->raw_json ? json_decode($listing->raw_json, true) : [];
        if (!is_array($rawJson)) {
            $rawJson = [];
        }

        $result->whyThisMatches = $this->buildWhyThisMatches($result);
        $result->tradeoffs      = $this->buildTradeoffs($result, $criteria, $rawJson);
        $result->cautionFlags   = $this->buildCautionFlags($result, $criteria, $rawJson);
        $result->missingData    = $this->buildMissingData($result, $criteria);

        return $result;
    }

    /**
     * build() PLUS the git-C10 report blocks (Plan-C6, F3): whyNot / confidence / recommendations.
     *
     * Used by the Match Check *detailed* path only (git-C11 mapper / git-C13 orchestrator). The
     * live batch path uses build()/buildAll() and is deliberately left untouched, so it neither
     * computes nor carries these blocks. Additive and inert: no flag, no I/O beyond the raw_json
     * decode the existing build() already performs, no now(), no writes.
     */
    public function buildDetailed(BuyerMatchResult $result, BuyerCriteriaPayload $criteria): BuyerMatchResult
    {
        // Populate the four existing blocks exactly as the batch path does — unchanged behavior.
        $this->build($result, $criteria);

        $rawJson = $this->decodeRawJson($result->listing);

        $result->whyNot          = $this->buildWhyNot($result);
        $result->confidence      = $this->buildConfidence($result, $rawJson);
        $result->recommendations = $this->buildRecommendations($result, $criteria);

        return $result;
    }

    // =========================================================================
    // Block 1: why_this_matches
    // =========================================================================

    private function buildWhyThisMatches(BuyerMatchResult $result): array
    {
        $entries = [];
        $listing = $result->listing;
        $scores  = $result->categoryScores;

        $dimensionMeta = [
            'location'      => ['fields' => ['city', 'postal_code', 'latitude', 'longitude', 'county_or_parish']],
            'price'         => ['fields' => ['list_price']],
            'size'          => ['fields' => ['living_area', 'lot_size_sqft', 'year_built']],
            'property_type' => ['fields' => ['property_type', 'property_sub_type']],
            'amenities'     => ['fields' => ['pool_private_yn', 'garage_yn', 'waterfront_yn', 'view_yn', 'water_view_yn']],
            'financial'     => ['fields' => ['association_fee', 'tax_annual_amount', 'association_yn']],
            'lifestyle'     => ['fields' => ['new_construction_yn', 'pets_allowed']],
        ];

        foreach ($dimensionMeta as $dimension => $meta) {
            $score = $scores[$dimension] ?? 0;
            if ($score > 0) {
                $entries[] = [
                    'dimension'          => $dimension,
                    'label'              => $this->buildWhyLabel($dimension, $listing, $score),
                    'fields_used'        => $meta['fields'],
                    'score_contribution' => $score,
                ];
            }
        }

        usort($entries, fn($a, $b) => $b['score_contribution'] <=> $a['score_contribution']);

        return $entries;
    }

    private function buildWhyLabel(string $dimension, BridgeProperty $listing, int $score): string
    {
        switch ($dimension) {
            case 'location':
                $parts = array_filter([$listing->city, $listing->state_or_province]);
                $loc   = implode(', ', $parts) ?: 'your preferred area';
                return "Located in {$loc} — {$score} location points";
            case 'price':
                $price = $listing->list_price ? number_format((float) $listing->list_price, 0) : 'N/A';
                return "Listed at \${$price} — within your budget";
            case 'size':
                $sqft = $listing->living_area ? number_format((int) $listing->living_area) : 'N/A';
                return "Living area: {$sqft} sqft";
            case 'property_type':
                $sub = $listing->property_sub_type ?: $listing->property_type;
                return "Property type matches: {$sub}";
            case 'amenities':
                return "Amenities match your preferences — {$score} points";
            case 'financial':
                return "Monthly financial burden fits your tolerance";
            case 'lifestyle':
                return "Community lifestyle features match your preferences";
            default:
                return "Matches your {$dimension} preference";
        }
    }

    // =========================================================================
    // Block 2: tradeoffs
    // =========================================================================

    private function buildTradeoffs(BuyerMatchResult $result, BuyerCriteriaPayload $criteria, array $rawJson): array
    {
        $tradeoffs = [];
        $listing   = $result->listing;
        $scores    = $result->categoryScores;

        // Price tradeoff.
        // The full price category weight is 25 pts; in Phase 1 the maximum achievable
        // proximity score is 20 pts (price-reduction signal of 5 pts is Phase 2 only).
        // A tradeoff exists whenever the buyer expressed a price preference and the
        // listing didn't earn the full Phase-1-achievable proximity points.
        if ($criteria->idealPrice !== null || $criteria->maxPrice !== null) {
            // Phase-1 max: 20 price-proximity points (price-reduction signal = 0 until Phase 2).
            $phase1PriceProximityMax = BuyerMatchScorer::PRICE_PROXIMITY_MAX_PTS;
            $priceMax = $phase1PriceProximityMax;
            if (($scores['price'] ?? 0) < $priceMax) {
                $listPrice = $listing->list_price !== null ? (float) $listing->list_price : null;
                if ($listPrice !== null && $criteria->idealPrice !== null) {
                    $diffPct = round(abs($listPrice - $criteria->idealPrice) / $criteria->idealPrice * 100, 0);
                    $dir     = $listPrice > $criteria->idealPrice ? 'above' : 'below';
                    $tradeoffs[] = [
                        'dimension'   => 'price',
                        'label'       => "Price is {$diffPct}% {$dir} your ideal — at the upper end of your range",
                        'fields_used' => ['list_price'],
                        'deviation'   => "{$diffPct}%_{$dir}_ideal",
                    ];
                } elseif ($listPrice !== null && $criteria->maxPrice !== null) {
                    $tradeoffs[] = [
                        'dimension'   => 'price',
                        'label'       => 'Price is near the top of your budget',
                        'fields_used' => ['list_price'],
                        'deviation'   => 'near_max_price',
                    ];
                }
            }
        }

        // Size tradeoffs
        $livingArea = $listing->living_area;
        if ($livingArea !== null && ($criteria->minSqft !== null || $criteria->maxSqft !== null)) {
            $min = $criteria->minSqft ?? 0;
            $max = $criteria->maxSqft ?? PHP_INT_MAX;
            if ($livingArea < $min) {
                $diff = $min - $livingArea;
                $tradeoffs[] = [
                    'dimension'   => 'size',
                    'label'       => "Living area is {$livingArea} sqft — {$diff} sqft below your {$min} sqft minimum",
                    'fields_used' => ['living_area'],
                    'deviation'   => "-{$diff}_sqft",
                ];
            } elseif ($livingArea > $max && $max !== PHP_INT_MAX) {
                $diff = $livingArea - $max;
                $tradeoffs[] = [
                    'dimension'   => 'size',
                    'label'       => "Living area is {$livingArea} sqft — {$diff} sqft above your {$max} sqft maximum",
                    'fields_used' => ['living_area'],
                    'deviation'   => "+{$diff}_sqft",
                ];
            }
        }

        // Amenity tradeoffs
        if ($criteria->wantsPool === true && $listing->pool_private_yn !== true) {
            $tradeoffs[] = [
                'dimension'   => 'amenities',
                'label'       => 'No private pool listed — community pool may be available',
                'fields_used' => ['pool_private_yn'],
                'deviation'   => 'pool_absent',
            ];
        }

        if ($criteria->wantsGarage === true && $listing->garage_yn !== true) {
            $tradeoffs[] = [
                'dimension'   => 'amenities',
                'label'       => 'No garage listed',
                'fields_used' => ['garage_yn'],
                'deviation'   => 'garage_absent',
            ];
        }

        if ($criteria->wantsWaterfront === true && $listing->waterfront_yn !== true) {
            if ($listing->water_view_yn === true) {
                $tradeoffs[] = [
                    'dimension'   => 'amenities',
                    'label'       => 'No waterfront — water view is available',
                    'fields_used' => ['waterfront_yn', 'water_view_yn'],
                    'deviation'   => 'water_view_only',
                ];
            } else {
                $tradeoffs[] = [
                    'dimension'   => 'amenities',
                    'label'       => 'No waterfront access listed',
                    'fields_used' => ['waterfront_yn'],
                    'deviation'   => 'waterfront_absent',
                ];
            }
        }

        // Pet policy tradeoff
        if ($criteria->wantsPetFriendly === true) {
            $petsAllowed = $listing->pets_allowed;
            if ($petsAllowed !== null && strtolower(trim($petsAllowed)) === 'no') {
                $tradeoffs[] = [
                    'dimension'   => 'lifestyle',
                    'label'       => 'Pet policy restricts pets in this community',
                    'fields_used' => ['pets_allowed'],
                    'deviation'   => 'pets_not_allowed',
                ];
            }
        }

        return $tradeoffs;
    }

    // =========================================================================
    // Block 3: caution_flags
    // =========================================================================

    private function buildCautionFlags(BuyerMatchResult $result, BuyerCriteriaPayload $criteria, array $rawJson): array
    {
        $flags   = [];
        $listing = $result->listing;

        // CDD present
        if ($listing->cdd_yn === true) {
            $flags[] = [
                'type'     => 'cdd_present',
                'severity' => 'info',
                'label'    => 'This community has a Community Development District (CDD). Annual CDD fees apply in addition to HOA and property taxes.',
            ];
        }

        // CDD status unknown
        if ($listing->cdd_yn === null) {
            $flags[] = [
                'type'     => 'cdd_status_unknown',
                'severity' => 'info',
                'label'    => 'CDD status not confirmed in listing data — verify with listing agent.',
            ];
        }

        // Reduced confidence geo match
        if ($listing->latitude === null || $listing->longitude === null) {
            $flags[] = [
                'type'     => 'reduced_confidence_geo_match',
                'severity' => 'info',
                'label'    => 'Exact location could not be confirmed — matched by ZIP code only.',
            ];
        }

        // Pet policy unknown
        if ($criteria->wantsPetFriendly === true && $listing->pets_allowed === null) {
            $flags[] = [
                'type'     => 'pet_policy_unknown',
                'severity' => 'info',
                'label'    => 'Pet policy not confirmed in listing data — verify with listing agent or HOA.',
            ];
        }

        // HOA fee not listed
        if ($listing->association_yn === true && $listing->association_fee === null) {
            $flags[] = [
                'type'     => 'hoa_fee_not_listed',
                'severity' => 'info',
                'label'    => 'HOA association exists but fee amount is not listed — verify with listing agent.',
            ];
        }

        // Listing stale (DaysOnMarket >= 60)
        $dom = $rawJson['DaysOnMarket'] ?? null;
        if ($dom !== null && (int) $dom >= self::STALE_DAYS_THRESHOLD) {
            $flags[] = [
                'type'     => 'listing_stale',
                'severity' => 'warning',
                'label'    => "This listing has been on the market for {$dom} days.",
            ];
        }

        // Flood zone data absent — fire when flood zone code is NOT present in raw_json,
        // meaning the listing carries no flood zone information at all.
        if (!isset($rawJson['STELLAR_FloodZoneCode'])) {
            $flags[] = [
                'type'     => 'flood_zone_data_absent',
                'severity' => 'info',
                'label'    => 'Flood zone data not available from listing. Verify flood zone designation with the listing agent before making an offer.',
            ];
        }

        // School district not normalized
        $hasSchoolPreference = false; // Phase 3 feature; no buyer school criteria in Phase 1
        if ($hasSchoolPreference && (isset($rawJson['ElementarySchool']) || isset($rawJson['HighSchool']))) {
            $flags[] = [
                'type'     => 'school_district_not_normalized',
                'severity' => 'info',
                'label'    => 'School information is from the listing and has not been independently verified. Confirm school assignments directly with the school district.',
            ];
        }

        return $flags;
    }

    // =========================================================================
    // Block 4: missing_data
    // =========================================================================

    private function buildMissingData(BuyerMatchResult $result, BuyerCriteriaPayload $criteria): array
    {
        $missing = [];
        $listing = $result->listing;

        // HOA fee missing when buyer expressed an HOA ceiling
        if ($criteria->maxMonthlyHoa !== null && $listing->association_fee === null && $listing->association_yn === true) {
            $missing[] = [
                'field' => 'AssociationFee',
                'label' => 'HOA fee amount not listed — verify with listing agent',
            ];
        }

        // HOA fee missing when financial burden ceiling specified and association exists
        if ($criteria->maxMonthlyTotalBurden !== null && $listing->association_fee === null && $listing->association_yn === true) {
            $alreadyAdded = array_filter($missing, fn($m) => $m['field'] === 'AssociationFee');
            if (empty($alreadyAdded)) {
                $missing[] = [
                    'field' => 'AssociationFee',
                    'label' => 'HOA fee amount not listed — verify with listing agent',
                ];
            }
        }

        // Year built missing when buyer expressed preference
        if (($criteria->yearBuiltMin !== null || $criteria->yearBuiltMax !== null) && $listing->year_built === null) {
            $missing[] = [
                'field' => 'YearBuilt',
                'label' => 'Year built not listed',
            ];
        }

        // Lot size missing when buyer expressed preference
        if (($criteria->minLotSqft !== null || $criteria->maxLotSqft !== null) && $listing->lot_size_sqft === null) {
            $missing[] = [
                'field' => 'LotSizeSquareFeet',
                'label' => 'Lot size not listed',
            ];
        }

        // List price missing
        if ($listing->list_price === null) {
            $missing[] = [
                'field' => 'ListPrice',
                'label' => 'List price not available',
            ];
        }

        return $missing;
    }

    // =========================================================================
    // git-C10 (Plan-C6, F3) — detailed report blocks. Reached ONLY via buildDetailed();
    // the batch build()/buildAll() path never invokes them.
    // =========================================================================

    /**
     * Block 5: why_not — one entry per zero-scoring dimension (v1). Shaped like why_this_matches.
     * Only dimensions the scorer actually evaluated (present in categoryScores) and that earned
     * exactly zero are included — the unambiguous "why not" signal. "Low-but-nonzero" gradation is
     * a deferred refinement.
     */
    private function buildWhyNot(BuyerMatchResult $result): array
    {
        $entries = [];
        $scores  = $result->categoryScores;

        // Same dimension → fields map buildWhyThisMatches uses (kept local; the existing builder is
        // not modified).
        $dimensionFields = [
            'location'      => ['city', 'postal_code', 'latitude', 'longitude', 'county_or_parish'],
            'price'         => ['list_price'],
            'size'          => ['living_area', 'lot_size_sqft', 'year_built'],
            'property_type' => ['property_type', 'property_sub_type'],
            'amenities'     => ['pool_private_yn', 'garage_yn', 'waterfront_yn', 'view_yn', 'water_view_yn'],
            'financial'     => ['association_fee', 'tax_annual_amount', 'association_yn'],
            'lifestyle'     => ['new_construction_yn', 'pets_allowed'],
        ];

        foreach ($dimensionFields as $dimension => $fields) {
            if (array_key_exists($dimension, $scores) && (int) $scores[$dimension] === 0) {
                $entries[] = [
                    'dimension'          => $dimension,
                    'label'              => $this->buildWhyNotLabel($dimension),
                    'fields_used'        => $fields,
                    'score_contribution' => 0,
                ];
            }
        }

        return $entries;
    }

    private function buildWhyNotLabel(string $dimension): string
    {
        switch ($dimension) {
            case 'location':
                return 'Location does not match your preferred areas';
            case 'price':
                return 'Priced outside your budget';
            case 'size':
                return 'Size does not match your preferences';
            case 'property_type':
                return 'Property type does not match your preferences';
            case 'amenities':
                return 'Key amenities you want are not present';
            case 'financial':
                return 'Monthly financial burden exceeds your tolerance';
            case 'lifestyle':
                return 'Community lifestyle features do not match';
            default:
                return "Does not match your {$dimension} preference";
        }
    }

    /**
     * Block 6: confidence — data-completeness + the geo signal. Deterministic; never now()/random.
     * Shape: ['level' => high|medium|low, 'score' => 0.0–1.0, 'factors' => [geo_precise, completeness]].
     */
    private function buildConfidence(BuyerMatchResult $result, array $rawJson): array
    {
        $listing = $result->listing;

        $geoPrecise = $listing->latitude !== null && $listing->longitude !== null;

        $keyFields = [
            $listing->list_price,
            $listing->living_area,
            $listing->year_built,
            $listing->lot_size_sqft,
            $listing->property_type,
        ];
        $present      = count(array_filter($keyFields, fn ($v) => $v !== null && $v !== ''));
        $completeness = round($present / count($keyFields), 2);

        // Blend: completeness, with a fixed penalty when exact geo is unavailable (mirrors the
        // reduced_confidence_geo_match caution signal). Clamped to [0, 1].
        $score = max(0.0, min(1.0, round($completeness - ($geoPrecise ? 0.0 : 0.2), 2)));

        $level = $score >= 0.8 ? 'high' : ($score >= 0.5 ? 'medium' : 'low');

        return [
            'level'   => $level,
            'score'   => $score,
            'factors' => [
                'geo_precise'  => $geoPrecise,
                'completeness' => $completeness,
            ],
        ];
    }

    /**
     * Block 7: recommendations — rule-based v1. widen_price (price under-scored and listing exceeds
     * the buyer's ideal/max) and consider_adjacent_area (location scored zero). No external lookup.
     */
    private function buildRecommendations(BuyerMatchResult $result, BuyerCriteriaPayload $criteria): array
    {
        $recommendations = [];
        $listing = $result->listing;
        $scores  = $result->categoryScores;

        // widen_price
        $priceScore = (int) ($scores['price'] ?? 0);
        $listPrice  = $listing->list_price !== null ? (float) $listing->list_price : null;
        if ($priceScore < BuyerMatchScorer::PRICE_PROXIMITY_MAX_PTS && $listPrice !== null) {
            $reference = $criteria->idealPrice ?? $criteria->maxPrice;
            if ($reference !== null && $listPrice > $reference) {
                $gap = number_format((int) round($listPrice - $reference));
                $recommendations[] = [
                    'type'      => 'widen_price',
                    'dimension' => 'price',
                    'label'     => "Consider widening your price range by ~\${$gap}",
                ];
            }
        }

        // consider_adjacent_area
        if (array_key_exists('location', $scores) && (int) $scores['location'] === 0) {
            $city  = $criteria->preferredCities[0] ?? null;
            $label = ($city !== null && $city !== '')
                ? "Consider areas adjacent to {$city} to find more matches"
                : 'Consider nearby areas to find more matches';
            $recommendations[] = [
                'type'      => 'consider_adjacent_area',
                'dimension' => 'location',
                'label'     => $label,
            ];
        }

        return $recommendations;
    }

    /**
     * Decode a listing's raw_json into an array (empty on absent/invalid). Used by buildDetailed()
     * so build() stays untouched.
     */
    private function decodeRawJson(BridgeProperty $listing): array
    {
        $rawJson = $listing->raw_json ? json_decode($listing->raw_json, true) : [];

        return is_array($rawJson) ? $rawJson : [];
    }
}
