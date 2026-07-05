<?php

namespace App\Services\Dna\Relevance\Narrowers;

use App\Services\Dna\Relevance\CandidateAttributeProfile;
use App\Services\Dna\Relevance\CandidateNarrower;
use App\Services\Dna\Relevance\MatchDirection;
use App\Services\Dna\Relevance\NarrowingContext;

/**
 * SeniorCommunityComplianceGate — Matching V2 consumption slice 2B — MANDATORY legal gate.
 *
 * Enforces the 55+ / senior-community (HOPA / Fair Housing) rule at candidate
 * discovery: a seeker who is NOT 55+ eligible must not be matched to an
 * age-restricted listing (and vice-versa in the reverse direction). Always runs
 * when Matching V2 is on — NEVER behind hard_filters_enabled.
 *
 * The gate DROPS a pairing only when it is CONFIDENT of a mismatch:
 *   property is age-restricted  AND  seeker is not 55+ eligible.
 * Unknown data resolves per policy (OD-1):
 *   - 'open'  (default): unknown never causes a drop (fail-open — the FHA risk is
 *              wrongly EXCLUDING families; matches the Stellar NULL-passes gate).
 *   - 'closed': unknown resolves toward the restrictive value (fail-closed).
 *
 * Direction mapping:
 *   - DemandToListings: property = candidate, seeker = subject.
 *   - ListingToDemands: property = subject,   seeker = candidate.
 *
 * @see docs/matching-v2-consumption-slice-2b-narrowing-compliance-scope.md §4.2
 */
class SeniorCommunityComplianceGate implements CandidateNarrower
{
    public function narrow(array $tuples, NarrowingContext $context): array
    {
        $policyClosed = strtolower($context->seniorUnknownPolicy) === 'closed';
        $subjectFlag  = $context->subjectProfile?->age55; // ?bool

        return array_values(array_filter($tuples, function (array $tuple) use ($context, $policyClosed, $subjectFlag) {
            $candidateFlag = $context->profileFor($tuple)?->age55; // ?bool

            if ($context->direction === MatchDirection::DemandToListings) {
                $propertyFlag = $candidateFlag; // candidate is the property
                $seekerFlag   = $subjectFlag;   // subject is the seeker
            } else {
                $propertyFlag = $subjectFlag;   // subject is the property
                $seekerFlag   = $candidateFlag; // candidate is the seeker
            }

            $restricted  = $this->isRestricted($propertyFlag, $policyClosed);
            $notEligible = $this->isNotEligible($seekerFlag, $policyClosed);

            // Drop only on a confident (policy-resolved) mismatch.
            return ! ($restricted && $notEligible);
        }));
    }

    /** Property is age-restricted: definite true, or unknown under fail-closed. */
    private function isRestricted(?bool $flag, bool $policyClosed): bool
    {
        return $flag === true || ($flag === null && $policyClosed);
    }

    /** Seeker is not 55+ eligible: definite false, or unknown under fail-closed. */
    private function isNotEligible(?bool $flag, bool $policyClosed): bool
    {
        return $flag === false || ($flag === null && $policyClosed);
    }
}
