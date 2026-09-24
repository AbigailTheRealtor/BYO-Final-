<?php

namespace App\Services\Stellar\Matching;

use App\Services\SmartTags\Seeker\SeekerSmartTagMatch;

/**
 * What {@see BuyerMatchScorer::scoreFacts()} decides about one listing: the total,
 * the per-category scores, the Important Place rows the location score used and how the
 * listing compared with the seeker's selected Smart Tags (null when none were selected).
 *
 * Pure data. {@see BuyerMatchScorer::score()} wraps it into a BuyerMatchResult.
 */
final class ListingMatchScore
{
    /**
     * @param array<string,int>        $categoryScores
     * @param list<array<string,mixed>> $importantPlaceMatches ImportantPlaceMatcher::evaluate() rows
     */
    public function __construct(
        public readonly int $totalScore,
        public readonly array $categoryScores,
        public readonly array $importantPlaceMatches,
        public readonly ?SeekerSmartTagMatch $seekerFeatureMatch = null,
    ) {}
}
