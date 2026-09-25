<?php

namespace App\Services\Stellar\Matching;

use App\Models\BridgeProperty;
use App\Services\Bridge\BridgeListingMatchFactsBuilder;
use App\Services\SmartTags\Seeker\ListingSmartTagIndex;
use App\Services\SmartTags\Seeker\SeekerSmartTagMatch;
use App\Services\SmartTags\Seeker\SeekerSmartTagMatcher;
use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;
use App\Services\Stellar\Matching\DTO\BuyerMatchResult;
use App\Support\Geo\GreatCircleDistance;
use App\Support\Matching\MonthlyEquivalent;

class BuyerMatchScorer
{
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

    /**
     * The seeker's selected Smart Tags ("Property Features You Want") as ONE expressed amenity
     * inside the 10-pt Amenities category — weighted like its largest existing item (pool, 4).
     *
     * They join the category's own normalisation rather than adding points beside it, so:
     *   • Amenities still tops out at 10 and the total at 100, however many tags are picked;
     *   • the pick set earns 4 × (matched ÷ checkable) before normalisation, so each tag's share
     *     shrinks as more are picked — ten tags cannot outweigh one pool;
     *   • with no other amenity expressed, the picks ARE the category (10 × matched ÷ checkable);
     *   • with no picks — or none checkable on this listing — the category is computed exactly as
     *     before, with no bonus and no penalty.
     * They never filter.
     *
     * CHECKABLE PICKS ONLY. Each pick is present, known absent, or unknown on each listing
     * ({@see \App\Services\SmartTags\Seeker\BridgeSmartTagCheckability}): known absent means a
     * governed structured rule for the tag read a populated field on this listing and did not find
     * it; unknown means nothing could check it — no rule, an empty field, a stale or missing
     * derivation. Unknown is in neither numerator nor denominator. Scoring it as a miss would
     * mark a listing down for data the MLS never sent; scoring a known miss as unknown would take
     * away the picks' ranking power, because most tags are vocabulary tags that never store an
     * explicit "absent". Inventory-wide missing enrichment remains a ROLLOUT question, answered by
     * the matching gates, not by scoring.
     */
    public const SEEKER_FEATURES_MAX_PTS = 4.0;

    /**
     * DEDUPLICATION between the legacy structured criteria and canonical Smart Tags — nothing else.
     *
     * One customer preference must not be credited twice because the form asked it once as a
     * structured field and the picker offered it again as a tag. When the structured criterion is
     * expressed (as the category that scores it reads "expressed"), it stays authoritative for its
     * concept and the equivalent tag leaves the pick set — denominator and contribution — for that
     * search. Unrelated picks are untouched. Each tag here is derived from the very column the
     * structured criterion scores (config/smart_tag_sources.php), which is what makes it the SAME
     * preference rather than a neighbouring one.
     *
     * Deliberately NOT equivalent: `water_view` (narrower than "any view", which has no generic
     * tag), `heated_pool` / `community_pool` / `oversized_garage` / `carport` (different features),
     * `solar_power` (generation, not "energy efficient"). Free-text community keywords are not
     * mapped: interpreting text is not a deterministic equivalence.
     *
     * @var array<string, array{0: string, 1: string}> tag => [payload property, 'expressed'|'true']
     */
    public const STRUCTURED_TAG_EQUIVALENTS = [
        'private_pool'     => ['wantsPool', 'expressed'],        // Amenities: pool_private_yn
        'garage'           => ['wantsGarage', 'expressed'],      // Amenities: garage_yn
        'waterfront'       => ['wantsWaterfront', 'expressed'],  // Amenities: waterfront_yn
        'new_construction' => ['wantsNewConstruction', 'true'],  // Lifestyle: new_construction_yn
        'pets_allowed'     => ['wantsPetFriendly', 'true'],      // Lifestyle: pets_allowed
    ];

    /**
     * The picks this search scores: the payload's picks minus any whose structured equivalent is
     * expressed ({@see STRUCTURED_TAG_EQUIVALENTS}). Order preserved.
     *
     * @return list<string>
     */
    public static function scoredSeekerTags(BuyerCriteriaPayload $criteria): array
    {
        return array_values(array_filter($criteria->seekerSmartTags, static function (string $key) use ($criteria): bool {
            if (! isset(self::STRUCTURED_TAG_EQUIVALENTS[$key])) {
                return true;
            }

            [$property, $when] = self::STRUCTURED_TAG_EQUIVALENTS[$key];
            $value = $criteria->{$property};

            return $when === 'true' ? $value !== true : $value === null;
        }));
    }

    public function scoreAll(iterable $candidates, BuyerCriteriaPayload $criteria): array
    {
        $candidates = is_array($candidates) ? $candidates : iterator_to_array($candidates, false);

        // Input construction, not scoring: one batch read of the candidates' resolved tags, and only
        // when the seeker picked any — so the loop below never queries and a search with no picks
        // queries nothing. Each listing's share of it is handed to score() as a fact.
        $tags = ListingSmartTagIndex::forCandidates($candidates, $criteria);

        $results = [];

        foreach ($candidates as $listing) {
            $results[] = $this->score($listing, $criteria, $tags->factsFor($listing));
        }

        return $results;
    }

    /**
     * The Bridge entry point: one `bridge_properties` row scored against one criteria set.
     *
     * This method only ADAPTS. The row becomes {@see ListingMatchFacts} at the provider
     * boundary ({@see BridgeListingMatchFactsBuilder}); every rule below reads those facts
     * through {@see scoreFacts()} and never the row. The row itself is carried onto the
     * result for the presenters that render it, and the facts travel with it so the
     * explanation blocks read the same values the score did.
     *
     * The listing's resolved Smart Tags are not in the row, so they arrive as a fact of their own,
     * read by the caller through {@see ListingSmartTagIndex} — in batch by {@see scoreAll()},
     * `ListingSmartTagIndex::forCandidates([$listing], $criteria)->factsFor($listing)` for one row.
     * Omitted, every pick is unknown: none is checkable, so the picks leave this listing's Amenities
     * exactly as though none were selected, and they are worded as "could not be checked".
     */
    public function score(BridgeProperty $listing, BuyerCriteriaPayload $criteria, ?ListingSmartTagFacts $smartTags = null): BuyerMatchResult
    {
        $facts = BridgeListingMatchFactsBuilder::build($listing)->withSmartTags($smartTags);
        $score = $this->scoreFacts($facts, $criteria);

        $result = new BuyerMatchResult(
            listingKey:     $facts->listingKey,
            totalScore:     $score->totalScore,
            categoryScores: $score->categoryScores,
            listing:        $listing,
            whyThisMatches: [],
            tradeoffs:      [],
            cautionFlags:   [],
            missingData:    []
        );
        $result->importantPlaceMatches = $score->importantPlaceMatches;
        $result->seekerFeatureMatch    = $score->seekerFeatureMatch;
        $result->facts                 = $facts;

        return $result;
    }

    /**
     * The scoring engine: one listing's facts against one criteria set. Pure — no model,
     * no feed record, no query.
     */
    public function scoreFacts(ListingMatchFacts $facts, BuyerCriteriaPayload $criteria): ListingMatchScore
    {
        // Important Places: measured once per listing — used by the location score and carried on
        // the result for display. The rows name a category and a distance, never the place itself.
        $importantPlaceMatches = ImportantPlaceMatcher::evaluate(
            $facts->latitude !== null ? (float) $facts->latitude : null,
            $facts->longitude !== null ? (float) $facts->longitude : null,
            $criteria->importantPlaces
        );

        // The seeker's picks against the listing's resolved tags — both already in hand, so this
        // is a comparison, not a read. No picks: no match, and the score is the pre-feature one.
        $seekerFeatureMatch = null;
        $scoredPicks        = self::scoredSeekerTags($criteria);

        if ($scoredPicks !== []) {
            $seekerFeatureMatch = SeekerSmartTagMatcher::evaluate(
                $scoredPicks,
                $facts->smartTags?->presentKeys,
                $facts->smartTags?->knownAbsentKeys ?? [],
            );
        }

        $locationScore        = $this->scoreLocation($facts, $criteria, $importantPlaceMatches);
        $priceScore           = $this->scorePrice($facts, $criteria);
        $sizeScore            = $this->scoreSize($facts, $criteria);
        $propertyTypeScore    = $this->scorePropertyType($facts, $criteria);
        $amenityScore         = $this->scoreAmenities($facts, $criteria, $seekerFeatureMatch);
        $financialScore       = $this->scoreFinancial($facts, $criteria);
        $lifestyleScore       = $this->scoreLifestyle($facts, $criteria);
        $nonResidentialScore  = $this->scoreNonResidential($facts, $criteria);

        $total = (int) round(
            $locationScore['score'] +
            $priceScore['score'] +
            $sizeScore['score'] +
            $propertyTypeScore['score'] +
            $amenityScore['score'] +
            $financialScore['score'] +
            $lifestyleScore['score'] +
            $nonResidentialScore['score']
        );

        $total = max(0, min(100, $total));

        $categoryScores = [
            'location'        => (int) round($locationScore['score']),
            'price'           => (int) round($priceScore['score']),
            'size'            => (int) round($sizeScore['score']),
            'property_type'   => (int) round($propertyTypeScore['score']),
            'amenities'       => (int) round($amenityScore['score']),
            'financial'       => (int) round($financialScore['score']),
            'lifestyle'       => (int) round($lifestyleScore['score']),
            'non_residential' => (int) round($nonResidentialScore['score']),
        ];

        return new ListingMatchScore($total, $categoryScores, $importantPlaceMatches, $seekerFeatureMatch);
    }

    // =========================================================================
    // Category 1: Location (30 pts)
    // =========================================================================

    private function scoreLocation(ListingMatchFacts $facts, BuyerCriteriaPayload $criteria, array $importantPlaceMatches = []): array
    {
        $proximityScore = 0.0;
        $hasRadiusCriteria = !empty($criteria->radiusSearches);

        $lat = $facts->latitude !== null ? (float) $facts->latitude : null;
        $lng = $facts->longitude !== null ? (float) $facts->longitude : null;

        if ($hasRadiusCriteria) {
            if ($lat !== null && $lng !== null) {
                foreach ($criteria->radiusSearches as $search) {
                    // Support flat {lat, lng} (canonical) and legacy {center: {lat, lng}}
                    $centerLat   = (float) self::extractRadiusLat($search);
                    $centerLng   = (float) self::extractRadiusLng($search);
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

        // Polygon match: award 18 pts (max proximity) when the listing falls inside a
        // drawn polygon area. This is an exact PIP test — the bounding-box pre-filter
        // in BuyerMatchQueryBuilder widens the candidate set; the scorer narrows it here.
        $hasPolygonCriteria = !empty($criteria->polygons);
        if ($hasPolygonCriteria && $lat !== null && $lng !== null) {
            foreach ($criteria->polygons as $polygon) {
                if (!is_array($polygon) || !isset($polygon['path']) || !is_array($polygon['path'])) {
                    continue;
                }
                $path = $polygon['path'];
                if (count($path) < 3) {
                    continue;
                }
                if ($this->pointInPolygon($lat, $lng, $path)) {
                    // Being inside a drawn polygon is as strong as being at the center of a
                    // radius search — award full 18 proximity pts and stop checking further.
                    $proximityScore = 18.0;
                    break;
                }
            }
        }

        // Important Places — "within N miles of Work", measured straight-line from this listing.
        // They share the 18-pt proximity slot with radius and polygon criteria, combined by max():
        // a listing meeting every measured requirement earns the full 18 (as a polygon hit does),
        // meeting some earns that share. Max, not a sum or a replacement, so a place can only RAISE
        // proximity — never lower a score a radius or polygon already earned — and the location cap
        // below still applies. They select and exclude nothing: the SQL geography is unchanged, and
        // a requirement that could not be measured (no listing or place coordinate) earns nothing.
        $importantPlaceShare = ImportantPlaceMatcher::satisfiedShare($importantPlaceMatches);
        if ($importantPlaceShare !== null) {
            $proximityScore = max($proximityScore, 18.0 * $importantPlaceShare);
        }

        // City / ZIP exact match (6 pts) or county match (3 pts)
        $cityZipScore = 0;
        $cityMatch = !empty($criteria->preferredCities) && in_array($facts->city, $criteria->preferredCities);
        $zipMatch  = !empty($criteria->preferredZipCodes) && in_array($facts->postalCode, $criteria->preferredZipCodes);

        if ($cityMatch || $zipMatch) {
            $cityZipScore = 6;
        } elseif (!empty($criteria->preferredCounties) && in_array($facts->countyOrParish, $criteria->preferredCounties)) {
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

    private function scorePrice(ListingMatchFacts $facts, BuyerCriteriaPayload $criteria): array
    {
        $listPrice = $facts->listPrice !== null ? (float) $facts->listPrice : null;

        if ($listPrice === null) {
            return ['score' => 0.0];
        }

        // On a LEASE record the list price is the periodic rent and the lease
        // frequency is its period. The seeker's budget is monthly, so the listing
        // must be expressed per month before the two are comparable.
        //
        // A period we cannot establish earns NOTHING rather than being assumed
        // monthly. That is the same rule ImportantPlaceMatcher already applies to
        // a distance it cannot measure — "never a match, and no credit" — and it
        // is the whole point of the fix: an unverified rent must not be presented
        // as though it had been checked against a budget. The listing is not
        // dropped (a feed omission is not evidence that a rental is unaffordable);
        // it simply cannot earn price points, and BuyerMatchResultBuilder says so.
        // Only when there is actually a figure to compare against. A seeker who named
        // no budget has nothing that could be misjudged, so the dimension keeps its
        // existing "no preference" answer below rather than being penalised for a
        // period the feed happened to omit.
        $hasPricePreference = $criteria->idealPrice !== null || $criteria->maxPrice !== null;

        if ($criteria->isLeaseSearch() && $hasPricePreference) {
            $monthly = MonthlyEquivalent::lease(
                $listPrice,
                $facts->leaseFrequency
            );

            if ($monthly === null) {
                return ['score' => 0.0];
            }

            $listPrice = $monthly;
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

    private function scoreSize(ListingMatchFacts $facts, BuyerCriteriaPayload $criteria): array
    {
        // Living area (7 pts)
        $livingAreaScore = $this->rangeScore(
            value:  $facts->livingArea !== null ? (float) $facts->livingArea : null,
            min:    $criteria->minSqft !== null ? (float) $criteria->minSqft : null,
            max:    $criteria->maxSqft !== null ? (float) $criteria->maxSqft : null,
            maxPts: 7.0,
            hasPreference: ($criteria->minSqft !== null || $criteria->maxSqft !== null),
            nullScore: 7.0
        );

        // Lot size (4 pts) — null listing value → 2 (neutral mid-score)
        $lotScore = $this->rangeScore(
            value:  $facts->lotSizeSqft !== null ? (float) $facts->lotSizeSqft : null,
            min:    $criteria->minLotSqft !== null ? (float) $criteria->minLotSqft : null,
            max:    $criteria->maxLotSqft !== null ? (float) $criteria->maxLotSqft : null,
            maxPts: 4.0,
            hasPreference: ($criteria->minLotSqft !== null || $criteria->maxLotSqft !== null),
            nullScore: 2.0
        );

        // Year built (4 pts)
        $yearBuiltScore = $this->scoreYearBuilt($facts, $criteria);

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

    private function scoreYearBuilt(ListingMatchFacts $facts, BuyerCriteriaPayload $criteria): float
    {
        $hasPreference = ($criteria->yearBuiltMin !== null || $criteria->yearBuiltMax !== null);

        if (!$hasPreference) {
            return 4.0;
        }

        $yearBuilt = $facts->yearBuilt;

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

    private function scorePropertyType(ListingMatchFacts $facts, BuyerCriteriaPayload $criteria): array
    {
        // Type exact match (5 pts) — always 5 for listings in candidate set (type is hard filter)
        $typeScore = 5.0;

        // Subtype match (5 pts)
        $subTypeScore = 0.0;

        if (empty($criteria->propertySubTypes)) {
            $subTypeScore = 2.0;
        } elseif ($facts->propertySubType !== null &&
                  in_array($facts->propertySubType, $criteria->propertySubTypes)) {
            $subTypeScore = 5.0;
        } else {
            $subTypeScore = 0.0;
        }

        return ['score' => $typeScore + $subTypeScore];
    }

    // =========================================================================
    // Category 5: Amenities (10 pts)
    // =========================================================================

    private function scoreAmenities(ListingMatchFacts $facts, BuyerCriteriaPayload $criteria, ?SeekerSmartTagMatch $seekerFeatures = null): array
    {
        $expressed = [];

        if ($criteria->wantsPool !== null) {
            $expressed['pool'] = [
                'max'    => 4.0,
                'earned' => ($facts->poolPrivate === true) ? 4.0 : 0.0,
            ];
        }

        if ($criteria->wantsGarage !== null) {
            $expressed['garage'] = [
                'max'    => 3.0,
                'earned' => ($facts->garage === true) ? 3.0 : 0.0,
            ];
        }

        if ($criteria->wantsWaterfront !== null) {
            $waterfront = 0.0;
            if ($facts->waterfront === true) {
                $waterfront = 2.0;
            } elseif ($facts->waterView === true) {
                $waterfront = 1.0;
            }
            $expressed['waterfront'] = ['max' => 2.0, 'earned' => $waterfront];
        }

        if ($criteria->wantsAnyView !== null) {
            $viewEarned = ($facts->view === true || $facts->waterView === true) ? 1.0 : 0.0;
            $expressed['any_view'] = ['max' => 1.0, 'earned' => $viewEarned];
        }

        // Selected Smart Tags — see SEEKER_FEATURES_MAX_PTS. Absent when nothing was picked, and
        // when nothing picked could be checked on this listing: unknown is neither credit nor a miss.
        if ($seekerFeatures !== null && $seekerFeatures->hasCheckablePicks()) {
            $expressed['seeker_features'] = [
                'max'    => self::SEEKER_FEATURES_MAX_PTS,
                'earned' => self::SEEKER_FEATURES_MAX_PTS * $seekerFeatures->share(),
            ];
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

    private function scoreFinancial(ListingMatchFacts $facts, BuyerCriteriaPayload $criteria): array
    {
        if ($criteria->maxMonthlyTotalBurden === null) {
            return ['score' => 5.0];
        }

        // The fee amount and its billing period are separate facts. Reading the
        // amount as a monthly figure made an annually billed $2,400 fee and a
        // monthly $2,400 fee the same number, and the ceiling this is scored
        // against is explicitly a MONTHLY one.
        //
        // A fee whose period cannot be established is NOT assumed monthly and is
        // NOT silently counted as zero — either would be a fabricated figure. The
        // dimension instead returns its neutral score, the same answer a seeker
        // who expressed no ceiling receives, because the codebase's established
        // rule is that missing feed data never penalises a listing. The gap is
        // reported to the seeker through BuyerMatchResultBuilder's missing-data
        // block rather than buried in a number.
        $feeAmount = $facts->associationFee !== null ? (float) $facts->associationFee : null;

        if ($feeAmount === null || $feeAmount == 0.0) {
            $hoaMonthly = 0.0;
        } else {
            $hoaMonthly = MonthlyEquivalent::associationFee(
                $feeAmount,
                $facts->associationFeeFrequency
            );

            if ($hoaMonthly === null) {
                return ['score' => 5.0];
            }
        }

        $taxAnnual     = $facts->taxAnnualAmount !== null ? (float) $facts->taxAnnualAmount : 0.0;
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
    // Category 7: Lifestyle / Context (5 pts)
    // =========================================================================

    private function scoreLifestyle(ListingMatchFacts $facts, BuyerCriteriaPayload $criteria): array
    {
        // Community features overlap (2 pts)
        $communityScore = 0.0;
        if (!empty($criteria->communityFeatureKeywords)) {
            $features   = array_merge(
                $this->extractStringArray($facts->communityFeatures),
                $this->extractStringArray($facts->associationAmenities)
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
            $greenFeatures = $this->extractStringArray($facts->greenEnergyEfficient);
            $greenBuild    = $this->extractStringArray($facts->greenBuildingVerificationType);
            if (!empty($greenFeatures) || !empty($greenBuild)) {
                $greenScore = 1.0;
            }
        }

        // New construction preference (1 pt)
        $newConstructionScore = 0.0;
        if ($criteria->wantsNewConstruction === true && $facts->newConstruction === true) {
            $newConstructionScore = 1.0;
        }

        // Pet-friendly community (1 pt)
        $petScore = 0.0;
        if ($criteria->wantsPetFriendly === true) {
            $petsAllowed = $facts->petsAllowed;
            if ($petsAllowed !== null && strtolower(trim($petsAllowed)) !== 'no') {
                $petScore = 1.0;
            }
        }

        // Lease term preference (2 pts) — primary commercial lease scoring dimension.
        // Compares EAV 'desired_lease_length' (via preferredLeaseTerms) against the
        // listing's stated lease term (e.g. "24 Months", "Month-to-Month").
        // No preference → 0 pts (inactive). Missing listing value → neutral 2 pts.
        $leaseTermScore = $this->scoreLeaseTermPreference($facts->leaseTerm, $criteria);

        $total = $communityScore + $greenScore + $newConstructionScore + $petScore + $leaseTermScore;

        return ['score' => min(5.0, $total)];
    }

    /**
     * Score lease-term alignment between tenant preference and the listing's stated lease term.
     *
     * Returns 0 pts when no preference is expressed (dimension is inactive — existing
     * buyer flow scores are preserved exactly as before this field was added).
     * Returns 2 pts when preference and listing agree, or when a preference is set
     * but the listing states no lease term (neutral — don't penalise a
     * listing for missing data). Returns 0 pts when preference exists but does not match.
     *
     * Tenant form → canonical month buckets:
     *   'Month-to-Month'  → matched against a stated "Month-to-Month" term
     *   '6 Months'        → 6 months
     *   '1 Year'          → 12 months
     *   '2 Years'         → 24 months
     *   '3-5 Years'       → 36–60 months
     *   '6+ Years'        → 72+ months
     *
     * Stated lease term examples: "24 Months", "12 Months", "Month-to-Month", "Annual".
     */
    private function scoreLeaseTermPreference(mixed $statedLeaseTerm, BuyerCriteriaPayload $criteria): float
    {
        // No preference expressed — dimension is inactive.
        // Returning 0 preserves pre-existing buyer/residential scoring: callers
        // that never set preferredLeaseTerms score exactly the same as before
        // this dimension was introduced. (#3177 intentional design decision.)
        if (empty($criteria->preferredLeaseTerms)) {
            return 0.0;
        }

        if ($statedLeaseTerm === null || $statedLeaseTerm === '') {
            // Preference set but the listing states no lease term → neutral:
            // don't penalise the listing for missing data, award full 2 pts.
            return 2.0;
        }

        $statedTerm = strtolower(trim((string) $statedLeaseTerm));

        // Resolve the stated lease term to a canonical bucket for comparison.
        $statedMonths = null;
        $statedIsMtm  = false;

        if (str_contains($statedTerm, 'month-to-month') || str_contains($statedTerm, 'monthly') || $statedTerm === 'mtm') {
            $statedIsMtm = true;
        } elseif (preg_match('/(\d+)\s*months?/i', $statedLeaseTerm, $m)) {
            $statedMonths = (int) $m[1];
        } elseif (preg_match('/(\d+)\s*years?/i', $statedLeaseTerm, $m)) {
            $statedMonths = (int) $m[1] * 12;
        } elseif (str_contains($statedTerm, 'annual')) {
            $statedMonths = 12;
        }

        // If we couldn't parse the stated value, award neutral points.
        if ($statedMonths === null && !$statedIsMtm) {
            return 2.0;
        }

        // Check if any tenant preference bucket overlaps with the stated value.
        foreach ($criteria->preferredLeaseTerms as $pref) {
            $pref = trim((string) $pref);
            if ($statedIsMtm && strtolower($pref) === 'month-to-month') {
                return 2.0;
            }
            if ($statedMonths !== null) {
                $matched = match ($pref) {
                    '6 Months'    => $statedMonths === 6,
                    '1 Year'      => $statedMonths === 12,
                    '2 Years'     => $statedMonths === 24,
                    '3-5 Years'   => $statedMonths >= 36 && $statedMonths <= 60,
                    '6+ Years'    => $statedMonths >= 72,
                    default       => false,
                };
                if ($matched) {
                    return 2.0;
                }
            }
        }

        return 0.0;
    }

    // =========================================================================
    // Category 8: Non-Residential Alignment (0–10 pts, additive)
    //
    // Dispatches to a type-specific scorer for each non-residential property
    // type. Scores are purely additive — missing data never produces a penalty.
    // Residential listings (and unrecognised types) return 0.
    //
    // Commercial Lease is intentionally absent: that property type is matched
    // by Tenant matching, not Buyer matching, and must not be scored here.
    // =========================================================================

    private function scoreNonResidential(
        ListingMatchFacts $facts,
        BuyerCriteriaPayload $criteria
    ): array {
        return match ($facts->propertyType) {
            'Income'               => $this->scoreIncomeProperty($facts, $criteria),
            'Commercial Sale'      => $this->scoreCommercialSale($facts, $criteria),
            'Business Opportunity' => $this->scoreBusinessOpportunity($facts, $criteria),
            'Vacant Land'          => $this->scoreVacantLand($facts, $criteria),
            default                => ['score' => 0.0],
        };
    }

    // -------------------------------------------------------------------------
    // Income Property — building area alignment using existing buyer criteria.
    //
    // UNIT-COUNT AUDIT (confirmed against codebase):
    //   buyer_criteria_auctions has a `units_needed` nullable column, but
    //   BuyerOfferListingCriteriaLoader never reads it — the field is never
    //   populated in BuyerCriteriaPayload. BuyerCriteriaPayload has no
    //   minUnits / maxUnits / unitsNeeded properties of any kind.
    //   Unit-count scoring is therefore intentionally skipped per task
    //   instructions ("if no buyer unit-count field exists, document and skip").
    //   When `units_needed` is wired into the loader and payload, align the
    //   listing's total unit count against that range here instead.
    //
    // Current approach: align the stated building area (falling back to the
    // living area) against buyer's existing minSqft / maxSqft.
    // No preference expressed → full neutral points (10). Data absent → 5.
    // -------------------------------------------------------------------------

    private function scoreIncomeProperty(
        ListingMatchFacts $facts,
        BuyerCriteriaPayload $criteria
    ): array {
        $hasSizePref = ($criteria->minSqft !== null || $criteria->maxSqft !== null);
        if (!$hasSizePref) {
            return ['score' => 10.0]; // no preference → full neutral points
        }

        $buildingArea = $facts->buildingAreaTotal
            ?? ($facts->livingArea !== null ? (float) $facts->livingArea : null);

        if ($buildingArea === null) {
            return ['score' => 5.0]; // size data absent → reduced neutral
        }

        $min = $criteria->minSqft !== null ? (float) $criteria->minSqft : 0.0;
        $max = $criteria->maxSqft !== null ? (float) $criteria->maxSqft : PHP_FLOAT_MAX;
        if ($buildingArea >= $min && $buildingArea <= $max) {
            return ['score' => 10.0];
        }

        $deviationRatio = 0.0;
        if ($min > 0.0 && $buildingArea < $min) {
            $deviationRatio = ($min - $buildingArea) / $min;
        } elseif ($max < PHP_FLOAT_MAX && $buildingArea > $max) {
            $deviationRatio = ($buildingArea - $max) / $max;
        }
        return $deviationRatio <= 0.20
            ? ['score' => 5.0]  // close to range → partial
            : ['score' => 0.0];
    }

    // -------------------------------------------------------------------------
    // Commercial Sale — building size and lot size using existing buyer criteria.
    //
    // The stated building area is preferred over the living area for commercial
    // listings. Both size dimensions use existing BuyerCriteriaPayload fields.
    // -------------------------------------------------------------------------

    private function scoreCommercialSale(
        ListingMatchFacts $facts,
        BuyerCriteriaPayload $criteria
    ): array {
        $score = 0.0;

        // Building size alignment (up to 5 pts)
        $buildingArea = $facts->buildingAreaTotal
            ?? ($facts->livingArea !== null ? (float) $facts->livingArea : null);

        $hasSizePref = ($criteria->minSqft !== null || $criteria->maxSqft !== null);
        if (!$hasSizePref) {
            $score += 5.0; // no preference → neutral
        } elseif ($buildingArea !== null) {
            $min = $criteria->minSqft !== null ? (float) $criteria->minSqft : 0.0;
            $max = $criteria->maxSqft !== null ? (float) $criteria->maxSqft : PHP_FLOAT_MAX;
            if ($buildingArea >= $min && $buildingArea <= $max) {
                $score += 5.0;
            } else {
                $deviationRatio = 0.0;
                if ($min > 0.0 && $buildingArea < $min) {
                    $deviationRatio = ($min - $buildingArea) / $min;
                } elseif ($max < PHP_FLOAT_MAX && $buildingArea > $max) {
                    $deviationRatio = ($buildingArea - $max) / $max;
                }
                if ($deviationRatio <= 0.20) {
                    $score += 3.0;
                }
            }
        } else {
            $score += 3.0; // size data absent → reduced neutral
        }

        // Lot size alignment (up to 5 pts)
        $lotSqft    = $facts->lotSizeSqft !== null ? (float) $facts->lotSizeSqft : null;
        $hasLotPref = ($criteria->minLotSqft !== null || $criteria->maxLotSqft !== null);
        if (!$hasLotPref) {
            $score += 5.0; // no preference → neutral
        } elseif ($lotSqft !== null) {
            $lotMin = $criteria->minLotSqft !== null ? (float) $criteria->minLotSqft : 0.0;
            $lotMax = $criteria->maxLotSqft !== null ? (float) $criteria->maxLotSqft : PHP_FLOAT_MAX;
            if ($lotSqft >= $lotMin && $lotSqft <= $lotMax) {
                $score += 5.0;
            }
            // Outside range → 0 pts, no penalty
        }
        // Lot data absent → 0 pts, no penalty

        return ['score' => min(10.0, $score)];
    }

    // -------------------------------------------------------------------------
    // Business Opportunity — no buyer-specific preference fields applicable.
    //
    // Generic scoring categories (location, price, property_type) already rank
    // Business Opportunity listings using existing criteria. No additional
    // non-residential signals are added here pending dedicated buyer fields.
    // -------------------------------------------------------------------------

    private function scoreBusinessOpportunity(
        ListingMatchFacts $facts,
        BuyerCriteriaPayload $criteria
    ): array {
        return ['score' => 0.0];
    }

    // -------------------------------------------------------------------------
    // Vacant Land — lot size alignment using existing buyer criteria.
    //
    // Acreage / lot size is the primary dimension for land buyers. Uses the
    // existing minLotSqft / maxLotSqft fields from BuyerCriteriaPayload.
    // When no preference is expressed, award full neutral points.
    // -------------------------------------------------------------------------

    private function scoreVacantLand(
        ListingMatchFacts $facts,
        BuyerCriteriaPayload $criteria
    ): array {
        $hasLotPref = ($criteria->minLotSqft !== null || $criteria->maxLotSqft !== null);
        if (!$hasLotPref) {
            return ['score' => 10.0]; // no preference → full neutral points
        }

        $lotSqft = $facts->lotSizeSqft !== null ? (float) $facts->lotSizeSqft : null;
        if ($lotSqft === null) {
            return ['score' => 5.0]; // lot size absent → reduced neutral
        }

        $min = $criteria->minLotSqft !== null ? (float) $criteria->minLotSqft : 0.0;
        $max = $criteria->maxLotSqft !== null ? (float) $criteria->maxLotSqft : PHP_FLOAT_MAX;
        if ($lotSqft >= $min && $lotSqft <= $max) {
            return ['score' => 10.0];
        }

        $deviationRatio = 0.0;
        if ($min > 0.0 && $lotSqft < $min) {
            $deviationRatio = ($min - $lotSqft) / $min;
        } elseif ($max < PHP_FLOAT_MAX && $lotSqft > $max) {
            $deviationRatio = ($lotSqft - $max) / $max;
        }
        return $deviationRatio <= 0.20
            ? ['score' => 5.0]  // close to range → partial
            : ['score' => 0.0];
    }

    // =========================================================================
    // Helpers
    // =========================================================================

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

    /** Radius searches and Important Places are measured by one definition of a mile. */
    private function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        return GreatCircleDistance::miles($lat1, $lng1, $lat2, $lng2);
    }

    /**
     * Ray-casting point-in-polygon test.
     *
     * Counts eastward horizontal ray crossings from the test point against each
     * polygon edge. An odd crossing count means the point is inside.
     *
     * @param  float  $lat  Test point latitude.
     * @param  float  $lng  Test point longitude.
     * @param  array  $path Array of {lat, lng} associative arrays (≥3 points).
     * @return bool
     */
    private function pointInPolygon(float $lat, float $lng, array $path): bool
    {
        $n      = count($path);
        $inside = false;
        $j      = $n - 1;

        for ($i = 0; $i < $n; $i++) {
            $xi = (float) ($path[$i]['lng'] ?? 0);
            $yi = (float) ($path[$i]['lat'] ?? 0);
            $xj = (float) ($path[$j]['lng'] ?? 0);
            $yj = (float) ($path[$j]['lat'] ?? 0);

            $intersects = (($yi > $lat) !== ($yj > $lat))
                && ($lng < ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi);

            if ($intersects) {
                $inside = !$inside;
            }

            $j = $i;
        }

        return $inside;
    }

    private function extractStringArray(mixed $val): array
    {
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
