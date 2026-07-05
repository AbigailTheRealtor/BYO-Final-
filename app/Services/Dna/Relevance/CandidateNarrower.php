<?php

namespace App\Services\Dna\Relevance;

/**
 * CandidateNarrower — Matching V2 consumption slice 2B.
 *
 * A single narrowing step. Given the current candidate tuples and the shared
 * NarrowingContext, it returns a subset (preserving order). Narrowers are pure,
 * read-only, dependency-free, and provider-agnostic — they read only
 * CandidateAttributeProfile / BuyerCriteriaPayload from the context.
 */
interface CandidateNarrower
{
    /**
     * @param array<int,array{listing_type:string,listing_id:int}> $tuples
     * @return array<int,array{listing_type:string,listing_id:int}>
     */
    public function narrow(array $tuples, NarrowingContext $context): array;
}
