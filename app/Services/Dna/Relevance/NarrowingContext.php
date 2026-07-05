<?php

namespace App\Services\Dna\Relevance;

use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;

/**
 * NarrowingContext — Matching V2 consumption slice 2B.
 *
 * The immutable bundle every narrower receives: the subject, both directions'
 * resolved profiles, the subject's optional criteria envelope (for geo/attribute
 * narrowing), and the unknown-data policy for the mandatory 55+ gate.
 *
 * @see docs/matching-v2-consumption-slice-2b-narrowing-compliance-scope.md §2.1
 */
final class NarrowingContext
{
    /**
     * @param array<string,CandidateAttributeProfile> $candidateProfiles keyed "type:id"
     */
    public function __construct(
        public readonly string $subjectType,
        public readonly int $subjectId,
        public readonly MatchDirection $direction,
        public readonly string $counterpartSide,
        public readonly ?CandidateAttributeProfile $subjectProfile,
        public readonly ?BuyerCriteriaPayload $subjectCriteria,
        public readonly array $candidateProfiles,
        public readonly string $seniorUnknownPolicy,
    ) {
    }

    /**
     * @param array{listing_type:string,listing_id:int} $tuple
     */
    public function profileFor(array $tuple): ?CandidateAttributeProfile
    {
        return $this->candidateProfiles[
            CandidateAttributeProfile::key($tuple['listing_type'], (int) $tuple['listing_id'])
        ] ?? null;
    }
}
