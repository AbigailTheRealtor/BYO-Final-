<?php

namespace App\Services\Stellar\Matching;

use App\Services\Bridge\BridgeListingMatchFactsBuilder;
use App\Services\SmartTags\Seeker\SeekerSmartTagMatch;
use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;
use App\Services\Stellar\Matching\DTO\BuyerMatchResult;
use App\Support\Matching\MonthlyEquivalent;

class BuyerMatchResultBuilder
{
    private const STALE_DAYS_THRESHOLD = 60;

    public function buildAll(array $results, BuyerCriteriaPayload $criteria): array
    {
        return array_map(fn(BuyerMatchResult $r) => $this->build($r, $criteria), $results);
    }

    public function build(BuyerMatchResult $result, BuyerCriteriaPayload $criteria): BuyerMatchResult
    {
        $facts = $this->factsFor($result);

        $result->whyThisMatches = $this->buildWhyThisMatches($result, $facts);
        $result->tradeoffs      = $this->buildTradeoffs($result, $criteria, $facts);
        $result->cautionFlags   = $this->buildCautionFlags($criteria, $facts);
        $result->missingData    = $this->buildMissingData($criteria, $facts, $result->seekerFeatureMatch);

        return $result;
    }

    /**
     * build() PLUS the git-C10 report blocks (Plan-C6, F3): whyNot / confidence / recommendations.
     *
     * Used by the Match Check *detailed* path only (git-C11 mapper / git-C13 orchestrator). The
     * live batch path uses build()/buildAll() and is deliberately left untouched, so it neither
     * computes nor carries these blocks. Additive and inert: no flag, no I/O, no now(), no writes.
     */
    public function buildDetailed(BuyerMatchResult $result, BuyerCriteriaPayload $criteria): BuyerMatchResult
    {
        // Populate the four existing blocks exactly as the batch path does — unchanged behavior.
        $this->build($result, $criteria);

        $facts = $this->factsFor($result);

        $result->whyNot          = $this->buildWhyNot($result);
        $result->confidence      = $this->buildConfidence($facts);
        $result->recommendations = $this->buildRecommendations($result, $criteria, $facts);

        return $result;
    }

    // =========================================================================
    // Block 1: why_this_matches
    // =========================================================================

    private function buildWhyThisMatches(BuyerMatchResult $result, ListingMatchFacts $facts): array
    {
        $entries = [];
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
                    'label'              => $this->buildWhyLabel($dimension, $facts, $score),
                    'fields_used'        => $meta['fields'],
                    'score_contribution' => $score,
                ];
            }
        }

        // The seeker's selected property features are scored inside Amenities, so they are
        // explained there: which of their picks this home has, by name. Labels only — never a
        // canonical key, and no weight beyond the category points the entry already shows.
        $features = $result->seekerFeatureMatch;
        if ($features !== null && $features->matchedCount() > 0) {
            foreach ($entries as $i => $entry) {
                if ($entry['dimension'] === 'amenities') {
                    $entries[$i]['label'] = sprintf(
                        'Has %d of your %d selected features: %s',
                        $features->matchedCount(),
                        $features->selectedCount(),
                        self::featureList($features->matchedLabels())
                    );
                }
            }
        }

        usort($entries, fn($a, $b) => $b['score_contribution'] <=> $a['score_contribution']);

        return $entries;
    }

    /**
     * "A, B and C" — at most three names, then "and N more", so a long selection stays readable.
     *
     * @param list<string> $labels
     */
    private static function featureList(array $labels): string
    {
        $shown = array_slice($labels, 0, 3);
        $more  = count($labels) - count($shown);

        if ($more > 0) {
            return implode(', ', $shown) . " and {$more} more";
        }

        if (count($shown) <= 1) {
            return (string) ($shown[0] ?? '');
        }

        return implode(', ', array_slice($shown, 0, -1)) . ' and ' . end($shown);
    }

    private function buildWhyLabel(string $dimension, ListingMatchFacts $facts, int $score): string
    {
        switch ($dimension) {
            case 'location':
                $parts = array_filter([$facts->city, $facts->stateOrProvince]);
                $loc   = implode(', ', $parts) ?: 'your preferred area';
                return "Located in {$loc} — {$score} location points";
            case 'price':
                $price = $facts->listPrice ? number_format((float) $facts->listPrice, 0) : 'N/A';
                return "Listed at \${$price} — within your budget";
            case 'size':
                $sqft = $facts->livingArea ? number_format((int) $facts->livingArea) : 'N/A';
                return "Living area: {$sqft} sqft";
            case 'property_type':
                $sub = $facts->propertySubType ?: $facts->propertyType;
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

    private function buildTradeoffs(BuyerMatchResult $result, BuyerCriteriaPayload $criteria, ListingMatchFacts $facts): array
    {
        $tradeoffs = [];
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
                $listPrice = $facts->listPrice !== null ? (float) $facts->listPrice : null;
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
        $livingArea = $facts->livingArea;
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
        if ($criteria->wantsPool === true && $facts->poolPrivate !== true) {
            $tradeoffs[] = [
                'dimension'   => 'amenities',
                'label'       => 'No private pool listed — community pool may be available',
                'fields_used' => ['pool_private_yn'],
                'deviation'   => 'pool_absent',
            ];
        }

        if ($criteria->wantsGarage === true && $facts->garage !== true) {
            $tradeoffs[] = [
                'dimension'   => 'amenities',
                'label'       => 'No garage listed',
                'fields_used' => ['garage_yn'],
                'deviation'   => 'garage_absent',
            ];
        }

        if ($criteria->wantsWaterfront === true && $facts->waterfront !== true) {
            if ($facts->waterView === true) {
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

        // Selected property features this home does not list. Only when the home HAS resolved
        // feature data — with none, the gap is missing data (below), not a known absence.
        $features = $result->seekerFeatureMatch;
        if ($features !== null && $features->hasListingData) {
            $unmatched = $features->unmatchedLabels();
            if ($unmatched !== []) {
                $tradeoffs[] = [
                    'dimension'   => 'amenities',
                    'label'       => 'Does not list: ' . self::featureList($unmatched),
                    'fields_used' => [],
                    'deviation'   => 'selected_features_not_listed',
                ];
            }
        }

        // Pet policy tradeoff
        if ($criteria->wantsPetFriendly === true) {
            $petsAllowed = $facts->petsAllowed;
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

    private function buildCautionFlags(BuyerCriteriaPayload $criteria, ListingMatchFacts $facts): array
    {
        $flags   = [];

        // CDD present
        if ($facts->cdd === true) {
            $flags[] = [
                'type'     => 'cdd_present',
                'severity' => 'info',
                'label'    => 'This community has a Community Development District (CDD). Annual CDD fees apply in addition to HOA and property taxes.',
            ];
        }

        // CDD status unknown
        if ($facts->cdd === null) {
            $flags[] = [
                'type'     => 'cdd_status_unknown',
                'severity' => 'info',
                'label'    => 'CDD status not confirmed in listing data — verify with listing agent.',
            ];
        }

        // Reduced confidence geo match
        if ($facts->latitude === null || $facts->longitude === null) {
            $flags[] = [
                'type'     => 'reduced_confidence_geo_match',
                'severity' => 'info',
                'label'    => 'Exact location could not be confirmed — matched by ZIP code only.',
            ];
        }

        // Pet policy unknown
        if ($criteria->wantsPetFriendly === true && $facts->petsAllowed === null) {
            $flags[] = [
                'type'     => 'pet_policy_unknown',
                'severity' => 'info',
                'label'    => 'Pet policy not confirmed in listing data — verify with listing agent or HOA.',
            ];
        }

        // HOA fee not listed
        if ($facts->association === true && $facts->associationFee === null) {
            $flags[] = [
                'type'     => 'hoa_fee_not_listed',
                'severity' => 'info',
                'label'    => 'HOA association exists but fee amount is not listed — verify with listing agent.',
            ];
        }

        // Listing stale (days on market >= 60)
        $dom = $facts->daysOnMarket;
        if ($dom !== null && (int) $dom >= self::STALE_DAYS_THRESHOLD) {
            $flags[] = [
                'type'     => 'listing_stale',
                'severity' => 'warning',
                'label'    => "This listing has been on the market for {$dom} days.",
            ];
        }

        // Flood zone data absent — fire when the listing states no flood zone designation,
        // meaning it carries no flood zone information at all.
        if (!$facts->floodZoneStated) {
            $flags[] = [
                'type'     => 'flood_zone_data_absent',
                'severity' => 'info',
                'label'    => 'Flood zone data not available from listing. Verify flood zone designation with the listing agent before making an offer.',
            ];
        }

        // School district not normalized
        $hasSchoolPreference = false; // Phase 3 feature; no buyer school criteria in Phase 1
        if ($hasSchoolPreference && $facts->schoolsListed) {
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

    private function buildMissingData(BuyerCriteriaPayload $criteria, ListingMatchFacts $facts, ?SeekerSmartTagMatch $seekerFeatures = null): array
    {
        $missing = [];

        // A rent whose PERIOD the feed did not state, on a lease search where the
        // seeker gave a monthly budget. The scorer refuses to assume monthly and
        // awards no price points; without this row the seeker would see a listing
        // ranked low with nothing saying why, and — worse — would have no signal
        // that the advertised figure may not be a monthly one.
        if ($criteria->isLeaseSearch()
            && $criteria->maxPrice !== null
            && $facts->listPrice !== null
            && MonthlyEquivalent::leaseFactor($facts->leaseFrequency) === null) {
            $missing[] = [
                'field' => ListingPeriodFacts::LEASE_FREQUENCY_FIELD,
                'label' => 'Rent period not stated — this figure may not be monthly; verify before comparing to your budget',
            ];
        }

        // An association fee whose BILLING PERIOD the feed did not state, where the
        // seeker gave a monthly ceiling. The fee is present, so the existing
        // "amount not listed" rows below do not fire, and the scorer returned its
        // neutral score rather than a fabricated monthly figure. Say so.
        if (($criteria->maxMonthlyHoa !== null || $criteria->maxMonthlyTotalBurden !== null)
            && $facts->associationFee !== null
            && (float) $facts->associationFee != 0.0
            && MonthlyEquivalent::associationFeeFactor(
                $facts->associationFeeFrequency
            ) === null) {
            $missing[] = [
                'field' => ListingPeriodFacts::ASSOCIATION_FEE_FREQUENCY_FIELD,
                'label' => 'HOA fee billing period not stated — the amount could not be compared to a monthly ceiling',
            ];
        }

        // HOA fee missing when buyer expressed an HOA ceiling
        if ($criteria->maxMonthlyHoa !== null && $facts->associationFee === null && $facts->association === true) {
            $missing[] = [
                'field' => 'AssociationFee',
                'label' => 'HOA fee amount not listed — verify with listing agent',
            ];
        }

        // HOA fee missing when financial burden ceiling specified and association exists
        if ($criteria->maxMonthlyTotalBurden !== null && $facts->associationFee === null && $facts->association === true) {
            $alreadyAdded = array_filter($missing, fn($m) => $m['field'] === 'AssociationFee');
            if (empty($alreadyAdded)) {
                $missing[] = [
                    'field' => 'AssociationFee',
                    'label' => 'HOA fee amount not listed — verify with listing agent',
                ];
            }
        }

        // Year built missing when buyer expressed preference
        if (($criteria->yearBuiltMin !== null || $criteria->yearBuiltMax !== null) && $facts->yearBuilt === null) {
            $missing[] = [
                'field' => 'YearBuilt',
                'label' => 'Year built not listed',
            ];
        }

        // Lot size missing when buyer expressed preference
        if (($criteria->minLotSqft !== null || $criteria->maxLotSqft !== null) && $facts->lotSizeSqft === null) {
            $missing[] = [
                'field' => 'LotSizeSquareFeet',
                'label' => 'Lot size not listed',
            ];
        }

        // Selected property features that could not be checked: this home has no resolved
        // feature data at all. It earned nothing for them — unknown is never a match — and the
        // seeker is told why rather than left with an unexplained lower score.
        if ($seekerFeatures !== null && ! $seekerFeatures->hasListingData) {
            $missing[] = [
                'field' => 'selected_features',
                'label' => 'Feature details not available — your selected features could not be checked for this home',
            ];
        }

        // List price missing
        if ($facts->listPrice === null) {
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
    private function buildConfidence(ListingMatchFacts $facts): array
    {
        $geoPrecise = $facts->latitude !== null && $facts->longitude !== null;

        $keyFields = [
            $facts->listPrice,
            $facts->livingArea,
            $facts->yearBuilt,
            $facts->lotSizeSqft,
            $facts->propertyType,
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
    private function buildRecommendations(BuyerMatchResult $result, BuyerCriteriaPayload $criteria, ListingMatchFacts $facts): array
    {
        $recommendations = [];
        $scores  = $result->categoryScores;

        // widen_price
        $priceScore = (int) ($scores['price'] ?? 0);
        $listPrice  = $facts->listPrice !== null ? (float) $facts->listPrice : null;
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
     * The facts the score was computed from. A result from BuyerMatchScorer::score() carries
     * them; one built by hand does not, and gets them from its listing through the same
     * provider boundary the scorer uses — once, then kept on the result.
     */
    private function factsFor(BuyerMatchResult $result): ListingMatchFacts
    {
        return $result->facts ??= BridgeListingMatchFactsBuilder::build($result->listing);
    }
}
