<?php

namespace App\Services\Dna\Relevance;

/**
 * ScoredEntityCandidateSource — Matching V2 consumption slice 2 (Candidate Discovery).
 *
 * The one candidate source shipped in slice 2. It resolves the candidate universe
 * directly from the unified dna_scores layer: any entity that has counterpart-side
 * DNA is a candidate, regardless of which provider produced it. There is NO
 * per-provider branching here — that is the anti-coupling guarantee of §0.2.
 *
 * GOVERNANCE: PURE READ-ONLY. All reads go through DnaScoreRepository (the single
 * dna_scores read seam). This source never writes, never scores, and never ranks —
 * ranking belongs to the §F6 kernels downstream.
 *
 * The pool is bounded by CandidateQuery::$cap. The source over-fetches by one row
 * so it can report truncation truthfully (a silently capped pool must never be
 * mistaken for "the whole market").
 *
 * @see docs/matching-v2-consumption-slice-2-candidate-discovery-scope.md §5.1 (Stage A)
 */
class ScoredEntityCandidateSource implements CandidateSourceInterface
{
    public function __construct(
        private readonly DnaScoreRepository $repository,
    ) {
    }

    public function resolve(CandidateQuery $query): CandidateSet
    {
        // Over-fetch by one to detect truncation without a second COUNT query.
        $fetched = $this->repository->distinctSubjects(
            $query->counterpartSide,
            $query->allowedListingTypes,
            $query->cap + 1,
            $query->excludeListingType,
            $query->excludeListingId,
        );

        $truncated = count($fetched) > $query->cap;

        return new CandidateSet(
            array_slice($fetched, 0, $query->cap),
            $truncated,
        );
    }
}
