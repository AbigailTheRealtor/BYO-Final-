@extends('my-bids.layout')
@section('bids-content')
<div class="p-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0" style="color: #049399;"><i class="fa-solid fa-users me-2"></i>Agent Bids on Your Listings</h5>
        <a href="{{ route('landlord.agent.auctions.list') }}" class="btn btn-sm" style="background: #049399; color: #fff;">
            <i class="fa-solid fa-list me-1"></i>View All Listings
        </a>
    </div>

    @if($pendingAgentBids->count() > 0)
        @foreach($pendingAgentBids as $agentBid)
        @php
            $agentName = $agentBid->user->name ?? 'Agent';
            $agentEmail = $agentBid->user->email ?? '';
            $listingAddress = $agentBid->auction->get->address ?? 'N/A';
            $listingId = $agentBid->auction->listing_id ?? 'LAA-'.$agentBid->auction->id;
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

            $matchScore = \App\Helpers\LandlordBidMatchScoreHelper::calculate(
                $auctionBaselineData, $bidData, null, $propType
            );

            $totalScore     = $matchScore['overall_percent'];
            $brokerScore    = $matchScore['terms_match_percent'];
            $brokerMatched  = $matchScore['terms_matched_count'];
            $brokerTotal    = $matchScore['terms_baseline_total'];
            $brokerMismatches = $matchScore['changed_terms'];
            $servicesScore  = $matchScore['services_match_percent'];
            $servicesMatched = $matchScore['services_matched_count'];
            $servicesTotal  = $matchScore['services_baseline_total'];
            $servicesMissing = $matchScore['missing_services'] ?? [];
            $servicesAdded   = $matchScore['extra_services'] ?? [];

            $getScoreColor = function($score) {
                if ($score >= 80) return '#28a745';
                if ($score >= 50) return '#ffc107';
                return '#dc3545';
            };

            $totalScoreColor   = $getScoreColor($totalScore);
            $brokerScoreColor  = $getScoreColor($brokerScore);
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
                        <i class="fa-solid fa-chart-pie me-1"></i>{{ $totalScore }}% Match
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
                            <small class="fw-semibold text-danger"><i class="fa-solid fa-triangle-exclamation me-1"></i>Term Differences ({{ count($brokerMismatches) }})</small>
                            <div class="mt-1" style="max-height: 80px; overflow-y: auto;">
                                @foreach(array_slice(array_keys($brokerMismatches), 0, 5) as $field)
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
                            <small class="fw-semibold" style="color: #28a745;"><i class="fa-solid fa-plus-circle me-1"></i>Extra Services ({{ count($servicesAdded) }})</small>
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
                            <small class="fw-semibold" style="color: #ffc107;"><i class="fa-solid fa-minus-circle me-1"></i>Not Offered ({{ count($servicesMissing) }})</small>
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
                <a href="{{ route('landlord.agent.auction.bid.view', $agentBid->id) }}" class="btn btn-sm" style="background: #fff; border: 1px solid #049399; color: #049399;">
                    <i class="fa-solid fa-eye me-1"></i>View Bid
                </a>
                @if($bidStatus === 'Countered')
                    <a href="{{ route('landlord.hire.agent.auction.bid.view-counter', $agentBid->id) }}" class="btn btn-sm" style="background:#fff;border:2px solid #049399;color:#049399;padding:5px 12px;font-weight:600;font-size:0.85rem;">
                        <i class="fa-solid fa-eye me-1"></i>View Counter Terms
                    </a>
                @elseif($bidStatus === 'Accepted')
                    <a href="{{ route('landlord.agent.auction.view', $agentBid->auction->id) }}" class="btn btn-sm" style="background: #28a745; color: #fff; border: none;">
                        <i class="fa-solid fa-file-contract me-1"></i>View Summary
                    </a>
                @endif
            </div>
        </div>
        @endforeach
    @else
        <div class="text-center py-5">
            <i class="fa-solid fa-inbox fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">No Agent Bids Yet</h5>
            <p class="text-muted">When agents bid on your listings, they will appear here.</p>
            <a href="{{ route('hire.agent.auction', ['user_type' => 'landlord']) }}" class="btn" style="background: #049399; color: #fff;">
                <i class="fa-solid fa-plus me-1"></i>Create a Listing
            </a>
        </div>
    @endif
</div>
@endsection
