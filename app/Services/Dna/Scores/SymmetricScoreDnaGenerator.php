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
 * existing table. (The Phase-2 PetFriendlinessDnaGenerator remains as-is; new
 * scores use this generic path.)
 */
class SymmetricScoreDnaGenerator
{
    private const DEMAND_TYPES = ['tenant_agent', 'buyer_agent'];

    private CanonicalListingResolver $resolver;

    public function __construct(CanonicalListingResolver $resolver)
    {
        $this->resolver = $resolver;
    }

    public function generateForListing(SymmetricScoreService $score, string $listingType, int $listingId): ?DnaScore
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
                'computed_at'       => Carbon::now(),
            ]
        );
    }
}
