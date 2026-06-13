<?php

namespace App\Observers;

use App\Models\AcceptedBidSummary;
use App\Services\BidAnalyticsService;
use Illuminate\Support\Facades\Log;

/**
 * AcceptedBidSummaryAnalyticsObserver
 *
 * Fires on AcceptedBidSummary::created to capture the 'agent_hired' event.
 *
 * AcceptedBidSummary is created when a bid is accepted and both parties
 * have completed the acceptance flow — making it the reliable signal for
 * the "agent hired" funnel stage.
 *
 * listing_type values map to roles:
 *   'seller_offer' | 'buyer_offer' | 'landlord_offer' | 'tenant_offer'
 */
class AcceptedBidSummaryAnalyticsObserver
{
    /**
     * Map listing_type strings to (bidType, role) pairs.
     */
    private const LISTING_TYPE_MAP = [
        'seller_offer'   => ['seller_agent', 'seller'],
        'buyer_offer'    => ['buyer_agent',  'buyer'],
        'landlord_offer' => ['landlord_agent', 'landlord'],
        'tenant_offer'   => ['tenant_agent',  'tenant'],
    ];

    public function created(AcceptedBidSummary $summary): void
    {
        try {
            $listingType  = $summary->listing_type ?? '';
            [$bidType, $role] = self::LISTING_TYPE_MAP[$listingType] ?? ['unknown', 'unknown'];

            $bidId = (int) $summary->accepted_bid_id;

            if ($bidId <= 0) {
                return;
            }

            BidAnalyticsService::captureSnapshot(
                $bidType,
                $bidId,
                $role,
                null,
                BidAnalyticsService::EVENT_AGENT_HIRED,
                [],
                []
            );

            if ($bidType !== 'unknown') {
                BidAnalyticsService::advanceFunnel(
                    $bidType, $bidId, $role, BidAnalyticsService::EVENT_AGENT_HIRED
                );
            }

            // Record recommendation attribution for agent_hired.
            // Reads session context stored when the agent's bid detail was viewed
            // via a recommendation link (?from_rec=1&surface=...).
            $recCtx = BidAnalyticsService::getRecContext($bidType, $bidId);
            BidAnalyticsService::recordRecommendationInteraction(
                'agent_hired', $role,
                $recCtx['from_recommendation'], $recCtx['surface'],
                $bidType, $bidId, null, null
            );
        } catch (\Throwable $e) {
            Log::warning('[AcceptedBidSummaryAnalyticsObserver] created failed', [
                'summary_id' => $summary->id ?? null,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
