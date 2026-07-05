<?php

namespace App\Services\Dna\Relevance\Narrowers;

use App\Services\Dna\Relevance\CandidateNarrower;
use App\Services\Dna\Relevance\NarrowingContext;

/**
 * ListingEligibilityGate — Matching V2 consumption slice 2B — MANDATORY gate.
 *
 * The DNA score observers fire on EVERY save of the four *_agent types, so the
 * raw dna_scores universe contains drafts, sold/inactive records, and
 * Hire-an-Agent-only records — none of which are marketplace listings. This gate
 * keeps only candidates that are an approved, active, offer-listing auction
 * (CandidateAttributeProfile::$isEligibleListing). It always runs when Matching V2
 * is on, independent of hard_filters_enabled.
 *
 * A candidate with no resolved profile is dropped (cannot confirm eligibility).
 *
 * @see docs/matching-v2-consumption-slice-2b-narrowing-compliance-scope.md §4.1
 */
class ListingEligibilityGate implements CandidateNarrower
{
    public function narrow(array $tuples, NarrowingContext $context): array
    {
        return array_values(array_filter($tuples, function (array $tuple) use ($context) {
            $profile = $context->profileFor($tuple);
            return $profile !== null && $profile->isEligibleListing;
        }));
    }
}
