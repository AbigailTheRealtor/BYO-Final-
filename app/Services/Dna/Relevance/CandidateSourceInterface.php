<?php

namespace App\Services\Dna\Relevance;

/**
 * CandidateSourceInterface — Matching V2 consumption slice 2 (Candidate Discovery).
 *
 * The seam that lets candidate discovery grow from a single source today
 * (ScoredEntityCandidateSource, over the unified dna_scores layer) to additional
 * DNA-enabled sources over time WITHOUT the orchestrator learning provider
 * identities. Implementations MUST be read-only.
 *
 * @see docs/matching-v2-consumption-slice-2-candidate-discovery-scope.md §0.2
 */
interface CandidateSourceInterface
{
    public function resolve(CandidateQuery $query): CandidateSet;
}
