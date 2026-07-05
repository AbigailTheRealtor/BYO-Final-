<?php

namespace App\Services\Dna\Relevance;

use App\Services\Dna\Relevance\Narrowers\AttributeNarrower;
use App\Services\Dna\Relevance\Narrowers\GeoEnvelopeNarrower;
use App\Services\Dna\Relevance\Narrowers\ListingEligibilityGate;
use App\Services\Dna\Relevance\Narrowers\SeniorCommunityComplianceGate;
use App\Services\Stellar\BuyerOfferListingCriteriaLoader;
use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;
use App\Services\Stellar\TenantOfferListingCriteriaLoader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CandidateNarrowingPipeline — Matching V2 consumption slice 2B (Stage B).
 *
 * Runs the ordered narrowers over a Stage-A CandidateSet and trims to the final
 * cap. The two MANDATORY gates (listing eligibility + 55+ compliance) always run
 * when Matching V2 is on; the OPTIONAL geo/attribute narrowers run only when
 * hard_filters_enabled is set.
 *
 * GOVERNANCE: PURE READ-ONLY. It reads candidate profiles (batched, via the
 * resolver) and — only when optional narrowing is active and the subject is a
 * demand offer-listing — the subject's criteria envelope (via the read-only
 * offer-listing loaders). No writes, no scoring.
 *
 * @see docs/matching-v2-consumption-slice-2b-narrowing-compliance-scope.md §4
 */
class CandidateNarrowingPipeline
{
    public function __construct(
        private readonly CandidateAttributeResolverInterface $resolver,
        private readonly BuyerOfferListingCriteriaLoader $buyerCriteria,
        private readonly TenantOfferListingCriteriaLoader $tenantCriteria,
    ) {
    }

    public function narrow(
        CandidateSet $stageA,
        string $subjectType,
        int $subjectId,
        MatchDirection $direction,
        int $finalCap,
    ): CandidateSet {
        $tuples = $stageA->toArray();
        if ($tuples === []) {
            return $stageA;
        }

        $counterpartSide = $direction->counterpartSide();
        $subjectSide     = $counterpartSide === 'property' ? 'demand' : 'property';
        $hardFilters     = (bool) config('matching.candidate_discovery.hard_filters_enabled', false);

        // Batched profile resolution (subject + all candidates).
        $subjectProfiles   = $this->resolver->resolveMany($subjectSide, [
            ['listing_type' => $subjectType, 'listing_id' => $subjectId],
        ]);
        $subjectProfile    = $subjectProfiles[CandidateAttributeProfile::key($subjectType, $subjectId)] ?? null;
        $candidateProfiles = $this->resolver->resolveMany($counterpartSide, $tuples);

        $subjectCriteria = $hardFilters
            ? $this->loadSubjectCriteria($subjectType, $subjectId, $direction)
            : null;

        $context = new NarrowingContext(
            subjectType: $subjectType,
            subjectId: $subjectId,
            direction: $direction,
            counterpartSide: $counterpartSide,
            subjectProfile: $subjectProfile,
            subjectCriteria: $subjectCriteria,
            candidateProfiles: $candidateProfiles,
            seniorUnknownPolicy: (string) config('matching.candidate_discovery.senior_unknown_policy', 'open'),
        );

        foreach ($this->narrowers($hardFilters) as $label => $narrower) {
            $before = count($tuples);
            $tuples = $narrower->narrow($tuples, $context);
            $dropped = $before - count($tuples);
            if ($dropped > 0) {
                Log::debug('[MatchingV2] candidate narrowing', [
                    'gate'    => $label,
                    'subject' => CandidateAttributeProfile::key($subjectType, $subjectId),
                    'dropped' => $dropped,
                    'kept'    => count($tuples),
                ]);
            }
        }

        // A pool larger than the final cap — or a Stage-A pool that was itself
        // truncated (eligible candidates may exist beyond what we over-fetched) —
        // means the returned set is not the whole market.
        $truncated = count($tuples) > $finalCap || $stageA->wasTruncated();

        return new CandidateSet(array_slice($tuples, 0, $finalCap), $truncated);
    }

    /**
     * @return array<string,CandidateNarrower> label => narrower, in run order
     */
    private function narrowers(bool $hardFilters): array
    {
        // Mandatory gates first (cheap, most selective, always on).
        $narrowers = [
            'eligibility' => new ListingEligibilityGate(),
            'senior_55'   => new SeniorCommunityComplianceGate(),
        ];

        // Optional narrowers only when hard filters are enabled.
        if ($hardFilters) {
            $narrowers['geo']       = new GeoEnvelopeNarrower();
            $narrowers['attribute'] = new AttributeNarrower();
        }

        return $narrowers;
    }

    /**
     * Load the subject's criteria envelope — only for a demand subject (buyer/tenant
     * offer-listing) in the DemandToListings direction, where geo/attribute narrowing
     * applies. Returns null (fail-open) on any failure or unsupported subject.
     */
    private function loadSubjectCriteria(
        string $subjectType,
        int $subjectId,
        MatchDirection $direction,
    ): ?BuyerCriteriaPayload {
        if ($direction !== MatchDirection::DemandToListings) {
            return null;
        }

        $loader = match ($subjectType) {
            'buyer_agent'  => $this->buyerCriteria,
            'tenant_agent' => $this->tenantCriteria,
            default        => null,
        };
        if ($loader === null) {
            return null;
        }

        try {
            $userId = DB::table($this->subjectTable($subjectType))
                ->where('id', $subjectId)
                ->value('user_id');
            if ($userId === null) {
                return null;
            }

            $data = $loader->loadById($subjectId, [(int) $userId]);
            return $data !== null ? new BuyerCriteriaPayload($data) : null;
        } catch (\Throwable $e) {
            // Fail-open: geo/attribute narrowing simply does not apply.
            Log::debug('[MatchingV2] subject criteria load failed', [
                'subject' => CandidateAttributeProfile::key($subjectType, $subjectId),
                'error'   => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function subjectTable(string $subjectType): string
    {
        return match ($subjectType) {
            'buyer_agent'  => 'buyer_agent_auctions',
            'tenant_agent' => 'tenant_agent_auctions',
            default        => throw new \InvalidArgumentException("Unsupported demand subject type: {$subjectType}"),
        };
    }
}
