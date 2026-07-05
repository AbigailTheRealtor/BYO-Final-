<?php

namespace App\Services\Dna\Relevance;

use App\Models\DnaScore;

/**
 * DnaScoreRepository — the read-only loader that feeds the §F6 relevance kernels
 * from the dna_scores production artifact (Matching V2, consumption slice 1).
 *
 * GOVERNANCE (core rule): Matching V2 is a PURE READ-ONLY CONSUMER of dna_scores.
 * This repository ONLY reads — no writes, no updateOrCreate, no caching, and it
 * never touches any generation table. Generation and matching stay fully
 * separated.
 *
 * Returns plain arrays (not lazy generators) so the batch matcher can re-read a
 * subject's scores across every counterpart pairing.
 */
class DnaScoreRepository
{
    /**
     * The property-side (supply) scores for a listing.
     *
     * @return array<int,DnaScore>
     */
    public function propertyScores(string $listingType, int $listingId): array
    {
        return $this->scores($listingType, $listingId, 'property');
    }

    /**
     * The demand-side (searcher preference) scores for a listing.
     *
     * @return array<int,DnaScore>
     */
    public function demandScores(string $listingType, int $listingId): array
    {
        return $this->scores($listingType, $listingId, 'demand');
    }

    /**
     * The distinct (listing_type, listing_id) subjects that carry scores on the
     * given side — the candidate universe for Matching V2 candidate discovery
     * (consumption slice 2). Provider-agnostic: an EMPTY $listingTypes returns
     * every scored subject on that side regardless of provider; a non-empty
     * allowlist scopes to those types. The subject itself (when both exclude
     * params are given) is filtered out of its own candidate set. Deterministically
     * ordered by (listing_type, listing_id) and hard-capped by $limit.
     *
     * PURE READ-ONLY — a plain SELECT, consistent with this repository's governance.
     *
     * @param array<int,string> $listingTypes Empty = all types.
     * @return array<int,array{listing_type:string,listing_id:int}>
     */
    public function distinctSubjects(
        string $side,
        array $listingTypes,
        int $limit,
        ?string $excludeType = null,
        ?int $excludeId = null,
    ): array {
        return DnaScore::query()
            ->where('side', $side)
            ->when(! empty($listingTypes), function ($q) use ($listingTypes) {
                $q->whereIn('listing_type', $listingTypes);
            })
            ->when($excludeType !== null && $excludeId !== null, function ($q) use ($excludeType, $excludeId) {
                // Exclude only the exact (type, id) subject: NOT (type = ? AND id = ?)
                // → (type != ?) OR (id != ?). Portable on Laravel 8 (no whereNot).
                $q->where(function ($w) use ($excludeType, $excludeId) {
                    $w->where('listing_type', '!=', $excludeType)
                      ->orWhere('listing_id', '!=', $excludeId);
                });
            })
            ->orderBy('listing_type')
            ->orderBy('listing_id')
            ->distinct()
            ->limit($limit)
            ->get(['listing_type', 'listing_id'])
            ->map(fn (DnaScore $row) => [
                'listing_type' => (string) $row->listing_type,
                'listing_id'   => (int) $row->listing_id,
            ])
            ->all();
    }

    /**
     * @return array<int,DnaScore>
     */
    private function scores(string $listingType, int $listingId, string $side): array
    {
        return DnaScore::query()
            ->where('listing_type', $listingType)
            ->where('listing_id', $listingId)
            ->where('side', $side)
            ->get()
            ->all();
    }
}
