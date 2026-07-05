<?php

namespace App\Services\Dna\Relevance;

/**
 * CandidateAttributeResolverInterface — Matching V2 consumption slice 2B.
 *
 * The seam that isolates all provider-specific attribute/geo/compliance reads
 * behind one BATCH call. Implementations MUST be read-only and MUST batch (no
 * per-candidate N+1). A future BridgeCandidateAttributeResolver plugs in here
 * without any narrower change.
 *
 * @see docs/matching-v2-consumption-slice-2b-narrowing-compliance-scope.md §5
 */
interface CandidateAttributeResolverInterface
{
    /**
     * Resolve a normalized profile for every tuple, keyed by "listing_type:listing_id".
     *
     * @param string $side  the dna_scores side these tuples sit on ('property'|'demand')
     * @param array<int,array{listing_type:string,listing_id:int}> $tuples
     * @return array<string,CandidateAttributeProfile>
     */
    public function resolveMany(string $side, array $tuples): array;
}
