<?php

namespace App\Services\Dna\Relevance;

/**
 * CandidateQuery — Matching V2 consumption slice 2 (Candidate Discovery).
 *
 * The normalized, immutable description of a candidate-discovery request handed
 * to a CandidateSourceInterface. It is deliberately provider-agnostic: membership
 * is defined by the counterpart `side` and (optionally) a listing-type allowlist,
 * never by which provider produced a candidate's DNA.
 *
 * @see docs/matching-v2-consumption-slice-2-candidate-discovery-scope.md §0.2
 */
final class CandidateQuery
{
    /**
     * @param string        $counterpartSide     The dna_scores side candidates must carry ('property'|'demand').
     * @param array<int,string> $allowedListingTypes Optional listing-type allowlist. EMPTY = all types (provider-agnostic default).
     * @param string|null   $excludeListingType  The subject's listing_type, excluded from its own candidate set.
     * @param int|null      $excludeListingId    The subject's listing_id, excluded from its own candidate set.
     * @param int           $cap                 Hard ceiling on returned candidates.
     */
    public function __construct(
        public readonly string $counterpartSide,
        public readonly array $allowedListingTypes,
        public readonly ?string $excludeListingType,
        public readonly ?int $excludeListingId,
        public readonly int $cap,
    ) {
    }
}
