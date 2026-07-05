<?php

namespace App\Services\Dna\Scores;

use App\Models\DnaScore;
use App\Services\Canonical\CanonicalListingResolver;
use App\Services\Dna\Scores\Contracts\SymmetricScoreService;
use Illuminate\Support\Carbon;

/**
 * SymmetricScoreDnaGenerator — one generic orchestrator that persists ANY
 * SymmetricScoreService to dna_scores (§F2), proving the Phase-2 pattern
 * generalizes across score types.
 *
 * Resolve → compute (property side for landlord/seller, demand side for
 * tenant/buyer) → upsert on the dna_scores unique key.
 *
 * GOVERNANCE: writes ONLY to dna_scores; no AI, no external calls. Touches no
 * existing table. All scalar scores — including Pet-Friendliness — persist
 * through this single generic path.
 */
class SymmetricScoreDnaGenerator
{
    private const DEMAND_TYPES = ['tenant_agent', 'buyer_agent'];

    private CanonicalListingResolver $resolver;

    public function __construct(CanonicalListingResolver $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * @param array{generated_by?:string} $provenance origin metadata (§ Phase 13);
     *        generated_by defaults to 'system' (lifecycle-generated). Other valid
     *        values: 'ai', 'user', 'imported'.
     */
    public function generateForListing(SymmetricScoreService $score, string $listingType, int $listingId, array $provenance = []): ?DnaScore
    {
        $canonical = $this->resolver->resolve($listingType, $listingId);
        if ($canonical === null) {
            return null;
        }

        $result = in_array($listingType, self::DEMAND_TYPES, true)
            ? $score->scoreDemand($canonical)
            : $score->scoreProperty($canonical);

        return DnaScore::updateOrCreate(
            [
                'listing_type' => $listingType,
                'listing_id'   => $listingId,
                'score_key'    => $result['score_key'],
                'side'         => $result['side'],
            ],
            [
                'value'             => $result['value'],
                'data_completeness' => $result['data_completeness'],
                'confidence'        => $result['confidence'],
                'explanation'       => $result['explanation'],
                'inputs_json'       => $result['inputs'],
                'version'           => $result['version'],
                // Provenance (§ Phase 13). generator_version mirrors `version`;
                // scalar scores have no upstream data source, so source_version is null.
                'generated_by'      => $provenance['generated_by'] ?? 'system',
                'generator_version' => $result['version'],
                'source_version'    => $result['source_version'] ?? null,
                'computed_at'       => Carbon::now(),
            ]
        );
    }
}
