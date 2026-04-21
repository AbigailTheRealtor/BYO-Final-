<?php

namespace App\Services;

use App\Helpers\TenantBidMatchScoreHelper;
use App\Models\TenantAgentAuction;
use App\Models\TenantAgentAuctionBid;
use App\Models\BiddingPeriodAgentMapping;
use Illuminate\Support\Facades\Auth;

class CompetingBidsService
{
    public function canViewCompetingBids($auctionId, $userId = null): bool
    {
        $userId = $userId ?? Auth::id();
        if (!$userId) {
            return false;
        }

        $auction = TenantAgentAuction::find($auctionId);
        if (!$auction) {
            return false;
        }

        $hasSubmittedBid = TenantAgentAuctionBid::where('tenant_agent_auction_id', $auctionId)
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->exists();

        return $hasSubmittedBid;
    }

    public function getCompetingBids($auctionId, $viewerUserId = null): array
    {
        $viewerUserId = $viewerUserId ?? Auth::id();
        
        if (!$this->canViewCompetingBids($auctionId, $viewerUserId)) {
            return [];
        }

        $auction = TenantAgentAuction::find($auctionId);
        
        $viewerBid = TenantAgentAuctionBid::where('tenant_agent_auction_id', $auctionId)
            ->where('user_id', $viewerUserId)
            ->first();

        if (!$viewerBid) {
            return [];
        }

        $competingBids = TenantAgentAuctionBid::where('tenant_agent_auction_id', $auctionId)
            ->where('user_id', '!=', $viewerUserId)
            ->whereDoesntHave('meta', function($q) {
                $q->where('meta_key', 'is_rejected')->where('meta_value', '1');
            })
            ->get();

        $result = [];
        foreach ($competingBids as $bid) {
            $anonymousLabel = BiddingPeriodAgentMapping::getAnonymousLabel($auctionId, 'tenant_agent', $bid->user_id);
            
            $matchScore = $this->calculateMatchScore($bid, $viewerBid, $auction->get->property_type ?? 'Residential Property');
            
            $result[] = [
                'anonymous_label' => $anonymousLabel,
                'broker_compensation' => $this->extractBrokerCompensation($bid),
                'offered_services' => $this->extractOfferedServices($bid),
                'match_score' => $matchScore,
                'is_updated' => $this->checkIfUpdated($bid),
            ];
        }

        return $result;
    }

    private function extractBrokerCompensation($bid): array
    {
        $bidData = (array) $bid->get;
        
        $brokerFields = [
            'commission_structure', 'lease_fee_type', 'broker_fee_timing', 'broker_fee_days_from_rent',
            'interested_purchase_fee_type', 'purchase_fee_type', 'interested_lease_option_agreement',
            'lease_type', 'lease_value', 'purchase_type', 'purchase_value', 'protection_period',
            'early_termination_fee_option', 'early_termination_fee_amount', 'retainer_fee_option',
            'retainer_fee_amount', 'retainer_fee_application', 'agency_agreement_timeframe', 'brokerage_relationship',
            'lease_fee_flat', 'lease_fee_percentage', 'lease_fee_percentage_monthly_rent', 'lease_fee_percentage_monthly_number',
            'lease_fee_flat_combo', 'lease_fee_percentage_combo', 'lease_fee_percentage_net',
            'lease_fee_flat_combo_net', 'lease_fee_percentage_combo_net', 'lease_fee_other',
            'purchase_fee_flat', 'purchase_fee_percentage', 'purchase_fee_flat_combo', 'purchase_fee_percentage_combo', 'purchase_fee_other',
            'flat_fee_amount', 'percent_gross_lease', 'purchase_flat_fee_amount', 'purchase_percent_value',
        ];

        $compensation = [];
        foreach ($brokerFields as $field) {
            if (isset($bidData[$field]) && !empty($bidData[$field])) {
                $compensation[$field] = $bidData[$field];
            }
        }

        return $compensation;
    }

    private function extractOfferedServices($bid): array
    {
        $bidData = (array) $bid->get;
        
        $services = $bidData['services'] ?? [];
        if (is_string($services)) {
            $services = json_decode($services, true) ?? [];
        }
        
        $otherServices = $bidData['other_services'] ?? [];
        if (is_string($otherServices)) {
            $otherServices = json_decode($otherServices, true) ?? [];
        }
        $otherServices = array_filter($otherServices, fn($s) => is_string($s) && !empty(trim($s)));
        
        return [
            'standard' => is_array($services) ? array_values(array_filter($services)) : [],
            'other' => array_values($otherServices),
        ];
    }

    public function calculateMatchScore($competingBid, $viewerBid, ?string $propertyType = null): array
    {
        // Baseline = viewer's own bid; compared = the competing bid being evaluated.
        // Uses the shared baseline-driven helper so Extra/Added items don't inflate scores.
        // $propertyType activates the Tenant-only catalog filter to exclude Buyer/Seller services.
        $viewerData    = (array) $viewerBid->get;
        $competingData = (array) $competingBid->get;

        // Referral fee is only a negotiable term for agent-created listings.
        // Strip it from scoring data when the listing was created by a user to
        // guarantee no impact on match scores for non-agent-created listings.
        $auction = $viewerBid->auction;
        if (!$auction || !$auction->isCreatedByAgent()) {
            unset($viewerData['referral_fee_percent']);
            unset($competingData['referral_fee_percent']);
        }

        $result = TenantBidMatchScoreHelper::calculate($viewerData, $competingData, null, $propertyType);

        return array_merge($result, [
            'compared_to_label' => 'Compared to Your Bid',
        ]);
    }

    private function checkIfUpdated($bid): bool
    {
        return $bid->updated_at->gt($bid->created_at->addMinutes(1));
    }
}
