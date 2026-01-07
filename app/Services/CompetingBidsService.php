<?php

namespace App\Services;

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

        if (!$auction->isBiddingPeriodActive()) {
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
            
            $matchScore = $this->calculateMatchScore($bid, $viewerBid);
            
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

    public function calculateMatchScore($competingBid, $viewerBid): array
    {
        $normalizeForMatch = function($v) {
            if (is_null($v) || $v === '') return '';
            if (is_array($v) || is_object($v)) return json_encode($v);
            $v = trim((string) $v);
            return preg_replace('/[\s$,%]/', '', strtolower($v));
        };

        $competingData = (array) $competingBid->get;
        $viewerData = (array) $viewerBid->get;

        $brokerFields = [
            'commission_structure', 'lease_fee_type', 'broker_fee_timing', 'broker_fee_days_from_rent',
            'interested_purchase_fee_type', 'purchase_fee_type', 'interested_lease_option_agreement',
            'lease_type', 'lease_value', 'purchase_type', 'purchase_value', 'protection_period',
            'early_termination_fee_option', 'early_termination_fee_amount', 'retainer_fee_option',
            'retainer_fee_amount', 'retainer_fee_application', 'agency_agreement_timeframe', 'brokerage_relationship',
        ];

        $brokerMatched = 0;
        $brokerTotal = 0;
        foreach ($brokerFields as $field) {
            $competingVal = $competingData[$field] ?? null;
            $viewerVal = $viewerData[$field] ?? null;
            if (!empty($competingVal) || !empty($viewerVal)) {
                $brokerTotal++;
                if ($normalizeForMatch($competingVal) === $normalizeForMatch($viewerVal)) {
                    $brokerMatched++;
                }
            }
        }
        $brokerPercent = $brokerTotal > 0 ? round(($brokerMatched / $brokerTotal) * 100) : 100;

        $normalizeService = function($s) {
            $s = preg_replace('/[\x{2018}\x{2019}]/u', "'", $s);
            $s = preg_replace('/[\x{201C}\x{201D}]/u', '"', $s);
            return strtolower(trim($s));
        };

        $getServices = function($data) use ($normalizeService) {
            $services = $data['services'] ?? [];
            if (is_string($services)) $services = json_decode($services, true) ?? [];
            $services = is_array($services) ? array_values(array_filter($services)) : [];
            
            $otherServices = $data['other_services'] ?? [];
            if (is_string($otherServices)) $otherServices = json_decode($otherServices, true) ?? [];
            $otherServices = is_array($otherServices) ? array_values(array_filter($otherServices, fn($s) => is_string($s) && !empty(trim($s)))) : [];
            
            return array_map($normalizeService, array_merge($services, $otherServices));
        };

        $competingServices = $getServices($competingData);
        $viewerServices = $getServices($viewerData);

        $allServicesUnion = array_unique(array_merge($competingServices, $viewerServices));
        $servicesTotal = count($allServicesUnion);
        $servicesMatched = 0;
        foreach ($allServicesUnion as $svc) {
            if (in_array($svc, $competingServices) && in_array($svc, $viewerServices)) {
                $servicesMatched++;
            }
        }
        $servicesPercent = $servicesTotal > 0 ? round(($servicesMatched / $servicesTotal) * 100) : 100;

        $hasBroker = $brokerTotal > 0;
        $hasServices = $servicesTotal > 0;
        if ($hasBroker && $hasServices) {
            $overallPercent = round(($brokerPercent + $servicesPercent) / 2);
        } elseif ($hasBroker) {
            $overallPercent = $brokerPercent;
        } elseif ($hasServices) {
            $overallPercent = $servicesPercent;
        } else {
            $overallPercent = 100;
        }

        return [
            'overall_percent' => $overallPercent,
            'broker_comp_percent' => $brokerPercent,
            'broker_comp_matched' => $brokerMatched,
            'broker_comp_total' => $brokerTotal,
            'services_percent' => $servicesPercent,
            'services_matched' => $servicesMatched,
            'services_total' => $servicesTotal,
            'compared_to_label' => 'Compared to Your Bid',
        ];
    }

    private function checkIfUpdated($bid): bool
    {
        return $bid->updated_at->gt($bid->created_at->addMinutes(1));
    }
}
