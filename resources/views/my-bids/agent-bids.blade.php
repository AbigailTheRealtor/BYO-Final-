{{--
    QA LOCK — BID COMPARISON SCORING

    This file previously used inline scoring logic (union-based).
    It has been replaced with helper-based scoring per Task #31 QA pass.

    RULES:
    - DO NOT reintroduce inline scoring logic
    - All scoring must come from BidMatchScoreHelper classes
    - Any scoring changes must be applied across ALL four roles

    Reference:
    qa_reports/QA_LOCK_BidComparison_v1.md
--}}
@extends('my-bids.layout')
@section('bids-content')
<div class="p-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0" style="color: #049399;"><i class="fas fa-users me-2"></i>Agent Bids on Your Listings</h5>
        <a href="{{ route('tenant.agent.auctions.list') }}" class="btn btn-sm" style="background: #049399; color: #fff;">
            <i class="fas fa-list me-1"></i>View All Listings
        </a>
    </div>
    
    @if($pendingAgentBids->count() > 0)
        @foreach($pendingAgentBids as $agentBid)
        @php
            $agentName = $agentBid->user->name ?? 'Agent';
            $agentEmail = $agentBid->user->email ?? '';
            $listingAddress = $agentBid->auction->get->address ?? 'N/A';
            $listingId = $agentBid->auction->listing_id ?? 'TAA-'.$agentBid->auction->id;
            $bidStatus = $agentBid->bid_status ?? 'Active';
            $statusStyles = [
                'Countered' => 'background-color: #ffc107; color: #000;',
                'Active' => 'background-color: #007bff; color: #fff;',
                'Accepted' => 'background-color: #28a745; color: #fff;',
                'Rejected' => 'background-color: #dc3545; color: #fff;',
            ];
            
            $auctionBaselineData = json_decode(json_encode($agentBid->auction->get ?? []), true) ?: [];
            $bidData = json_decode(json_encode($agentBid->get ?? []), true) ?: [];
            $propType = $agentBid->auction->get->property_type ?? 'Residential Property';

            // Remap legacy DB keys to canonical helper keys.
            // DB stores broker_fee_timing; helper uses payment_timing.
            if (($auctionBaselineData['payment_timing'] ?? '') === '') {
                $auctionBaselineData['payment_timing'] = $auctionBaselineData['broker_fee_timing'] ?? null;
            }
            if (($auctionBaselineData['days_to_pay'] ?? '') === '') {
                $auctionBaselineData['days_to_pay'] = $auctionBaselineData['broker_fee_days_from_rent']
                    ?? $auctionBaselineData['broker_fee_days_after_lease'] ?? null;
            }
            if (($bidData['payment_timing'] ?? '') === '') {
                $bidData['payment_timing'] = $bidData['broker_fee_timing'] ?? null;
            }
            if (($bidData['days_to_pay'] ?? '') === '') {
                $bidData['days_to_pay'] = $bidData['broker_fee_days_from_rent']
                    ?? $bidData['broker_fee_days_after_lease'] ?? null;
            }

            $matchScore = \App\Helpers\TenantBidMatchScoreHelper::calculate(
                $auctionBaselineData, $bidData, null, $propType
            );

            $totalScore      = $matchScore['overall_percent'];
            $brokerScore     = $matchScore['terms_match_percent'];
            $brokerMatched   = $matchScore['terms_matched_count'];
            $brokerTotal     = $matchScore['terms_baseline_total'];
            $brokerMismatches = $matchScore['changed_terms'];
            $servicesScore   = $matchScore['services_match_percent'];
            $servicesMatched = $matchScore['services_matched_count'];
            $servicesTotal   = $matchScore['services_baseline_total'];
            $servicesMissing = $matchScore['missing_services'] ?? [];
            $servicesAdded   = $matchScore['extra_services'] ?? [];

            $getScoreColor = function($score) {
                if ($score >= 80) return '#28a745';
                if ($score >= 50) return '#ffc107';
                return '#dc3545';
            };

            $totalScoreColor    = $getScoreColor($totalScore);
            $brokerScoreColor   = $getScoreColor($brokerScore);
            $servicesScoreColor = $getScoreColor($servicesScore);
        @endphp
        
        <div class="card mb-3" style="border: 1px solid #dee2e6; border-radius: 8px;">
            <div class="card-header d-flex justify-content-between align-items-center" style="background: #f8f9fa; border-bottom: 1px solid #dee2e6;">
                <div>
                    <span class="fw-bold">{{ $agentName }}</span>
                    <small class="text-muted d-block">{{ $agentEmail }}</small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge" style="{{ $statusStyles[$bidStatus] ?? $statusStyles['Active'] }} padding: 6px 12px; border-radius: 4px;">{{ $bidStatus }}</span>
                    <span class="badge" style="background: {{ $totalScoreColor }}; color: white; padding: 6px 12px; border-radius: 4px;">
                        <i class="fas fa-chart-pie me-1"></i>{{ $totalScore }}% Match
                    </span>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <small class="text-muted">Listing</small>
                        <div>{{ Str::limit($listingAddress, 40) }}</div>
                        <small class="text-muted">{{ $listingId }}</small>
                    </div>
                    <div class="col-md-4 mb-2">
                        <small class="text-muted">Submitted</small>
                        <div>{{ $agentBid->created_at->format('M d, Y') }}</div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <div class="d-flex gap-3">
                            <div>
                                <small class="text-muted d-block">Broker Terms</small>
                                <span style="color: {{ $brokerScoreColor }}; font-weight: 600;">{{ $brokerScore }}%</span>
                                <small class="text-muted">{{ $brokerTotal > 0 ? '('.$brokerMatched.'/'.$brokerTotal.')' : 'No terms provided' }}</small>
                            </div>
                            <div>
                                <small class="text-muted d-block">Services</small>
                                <span style="color: {{ $servicesScoreColor }}; font-weight: 600;">{{ $servicesScore }}%</span>
                                <small class="text-muted">{{ $servicesTotal > 0 ? '('.$servicesMatched.'/'.$servicesTotal.')' : 'No services requested' }}</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                @if(count($brokerMismatches) > 0 || count($servicesAdded) > 0 || count($servicesMissing) > 0)
                <div class="mt-3 pt-3" style="border-top: 1px solid #eee;">
                    <div class="row">
                        @if(count($brokerMismatches) > 0)
                        <div class="col-md-4 mb-2">
                            <small class="fw-semibold text-danger"><i class="fas fa-exclamation-triangle me-1"></i>Term Differences ({{ count($brokerMismatches) }})</small>
                            <div class="mt-1" style="max-height: 80px; overflow-y: auto;">
                                @foreach(array_slice($brokerMismatches, 0, 5) as $field => $vals)
                                    <span class="badge me-1 mb-1" style="background: #ffe6e6; color: #dc3545; font-size: 0.7rem;">{{ ucwords(str_replace('_', ' ', $field)) }}</span>
                                @endforeach
                                @if(count($brokerMismatches) > 5)
                                    <span class="badge" style="background: #f8d7da; color: #721c24; font-size: 0.7rem;">+{{ count($brokerMismatches) - 5 }} more</span>
                                @endif
                            </div>
                        </div>
                        @endif
                        @if(count($servicesAdded) > 0)
                        <div class="col-md-4 mb-2">
                            <small class="fw-semibold" style="color: #28a745;"><i class="fas fa-plus-circle me-1"></i>Extra Services ({{ count($servicesAdded) }})</small>
                            <div class="mt-1" style="max-height: 80px; overflow-y: auto;">
                                @foreach(array_slice($servicesAdded, 0, 3) as $svc)
                                    <span class="badge me-1 mb-1" style="background: #e6ffe6; color: #28a745; font-size: 0.7rem;">{{ Str::limit($svc, 30) }}</span>
                                @endforeach
                                @if(count($servicesAdded) > 3)
                                    <span class="badge" style="background: #d4edda; color: #155724; font-size: 0.7rem;">+{{ count($servicesAdded) - 3 }} more</span>
                                @endif
                            </div>
                        </div>
                        @endif
                        @if(count($servicesMissing) > 0)
                        <div class="col-md-4 mb-2">
                            <small class="fw-semibold" style="color: #ffc107;"><i class="fas fa-minus-circle me-1"></i>Not Offered ({{ count($servicesMissing) }})</small>
                            <div class="mt-1" style="max-height: 80px; overflow-y: auto;">
                                @foreach(array_slice($servicesMissing, 0, 3) as $svc)
                                    <span class="badge me-1 mb-1" style="background: #fff3cd; color: #856404; font-size: 0.7rem;">{{ Str::limit($svc, 30) }}</span>
                                @endforeach
                                @if(count($servicesMissing) > 3)
                                    <span class="badge" style="background: #ffeeba; color: #856404; font-size: 0.7rem;">+{{ count($servicesMissing) - 3 }} more</span>
                                @endif
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
                @endif
            </div>
            <div class="card-footer d-flex justify-content-end gap-2" style="background: #f8f9fa;">
                <a href="{{ route('tenant.agent.bid.preview', $agentBid->id) }}" class="btn btn-sm" style="background: #fff; border: 1px solid #049399; color: #049399;">
                    <i class="fas fa-eye me-1"></i>View Bid
                </a>
                @if($bidStatus === 'Active')
                    <form action="{{ route('tenant.hire.agent.auction.bid.accept') }}" method="POST" class="d-inline">
                        @csrf
                        <input type="hidden" name="bid_id" value="{{ $agentBid->id }}">
                        <input type="hidden" name="auction_id" value="{{ $agentBid->auction->id }}">
                        <button type="submit" class="btn btn-sm" style="background: #28a745; color: #fff; border: none;" onclick="return confirm('Accept this bid?')">
                            <i class="fas fa-check me-1"></i>Accept
                        </button>
                    </form>
                    <a href="{{ route('tenant.counter-terms', $agentBid->id) }}" class="btn btn-sm" style="background: #ffc107; color: #000; border: none;">
                        <i class="fas fa-exchange-alt me-1"></i>Counter
                    </a>
                    <form action="{{ route('tenant.hire.agent.auction.bid.reject') }}" method="POST" class="d-inline">
                        @csrf
                        <input type="hidden" name="bid_id" value="{{ $agentBid->id }}">
                        <input type="hidden" name="auction_id" value="{{ $agentBid->auction->id }}">
                        <button type="submit" class="btn btn-sm" style="background: #dc3545; color: #fff; border: none;" onclick="return confirm('Reject this bid?')">
                            <i class="fas fa-times me-1"></i>Reject
                        </button>
                    </form>
                @elseif($bidStatus === 'Countered')
                    <span class="btn btn-sm" style="background: #fff3cd; color: #856404; border: 1px solid #ffc107;">
                        <i class="fas fa-clock me-1"></i>Awaiting Response
                    </span>
                @elseif($bidStatus === 'Accepted')
                    <a href="{{ route('tenant.agent.auction.view', $agentBid->auction->id) }}" class="btn btn-sm" style="background: #28a745; color: #fff; border: none;">
                        <i class="fas fa-file-contract me-1"></i>View Summary
                    </a>
                @endif
            </div>
        </div>
        @endforeach
    @else
        <div class="text-center py-5">
            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">No Agent Bids Yet</h5>
            <p class="text-muted">When agents bid on your listings, they will appear here.</p>
            <a href="{{ route('hire.agent.auction', ['user_type' => 'tenant']) }}" class="btn" style="background: #049399; color: #fff;">
                <i class="fas fa-plus me-1"></i>Create a Listing
            </a>
        </div>
    @endif
</div>
@endsection
