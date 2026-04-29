@extends('my-bids.layout')
@section('bids-content')
<div class="p-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0" style="color: #049399;"><i class="fa-solid fa-users me-2"></i>Agent Bids on Your Listings</h5>
        <a href="{{ route('seller.agents') }}" class="btn btn-sm" style="background: #049399; color: #fff;">
            <i class="fa-solid fa-list me-1"></i>View All Listings
        </a>
    </div>

    @if($pendingAgentBids->count() > 0)
        @foreach($pendingAgentBids as $agentBid)
        @php
            $agentName = $agentBid->user->name ?? 'Agent';
            $agentEmail = $agentBid->user->email ?? '';
            $listingAddress = $agentBid->auction->get->address ?? 'N/A';
            $listingId = $agentBid->auction->listing_id ?? 'SAA-'.$agentBid->auction->id;
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

            $matchScore = \App\Helpers\SellerBidMatchScoreHelper::calculate(
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
                        <i class="fa-solid fa-chart-pie me-1"></i>{{ $totalScore }}% Match
                    </span>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <small class="text-muted">Listing</small>
                        <div>{{ Str::limit($listingAddress, 40) }}</div>
                        <small class="text-muted">ID: {{ $listingId }}</small>
                    </div>
                    <div class="col-md-4 mb-2">
                        <small class="text-muted">Terms Match</small>
                        <div class="fw-bold" style="color: {{ $brokerScoreColor }};">{{ $brokerScore }}%</div>
                        <small class="text-muted">{{ $brokerTotal > 0 ? $brokerMatched.'/'.$brokerTotal.' fields' : 'No terms provided' }}</small>
                    </div>
                    <div class="col-md-4 mb-2">
                        <small class="text-muted">Services Match</small>
                        <div class="fw-bold" style="color: {{ $servicesScoreColor }};">{{ $servicesScore }}%</div>
                        <small class="text-muted">{{ $servicesTotal > 0 ? $servicesMatched.'/'.$servicesTotal.' services' : 'No services requested' }}</small>
                    </div>
                </div>
                @if(count($servicesMissing) > 0)
                <div class="mt-2 p-2" style="background: #fff5f5; border-radius: 4px; border-left: 3px solid #dc3545;">
                    <small class="text-danger fw-bold">Missing Services: </small>
                    <small class="text-danger">{{ implode(', ', array_slice($servicesMissing, 0, 3)) }}{{ count($servicesMissing) > 3 ? ' +'.( count($servicesMissing)-3).' more' : '' }}</small>
                </div>
                @endif
            </div>
            <div class="card-footer d-flex justify-content-end gap-2" style="background: #f8f9fa;">
                <a href="{{ route('seller.agent.auction.detail', $agentBid->auction->id) }}" class="btn btn-sm" style="background: #fff; border: 1px solid #049399; color: #049399;">
                    <i class="fa-solid fa-eye me-1"></i>View Bid
                </a>
                @if($bidStatus === 'Active')
                    <form action="{{ route('acceptSABid') }}" method="POST" class="d-inline">
                        @csrf
                        <input type="hidden" name="bid_id" value="{{ $agentBid->id }}">
                        <input type="hidden" name="auction_id" value="{{ $agentBid->auction->id }}">
                        <button type="submit" class="btn btn-sm" style="background: #28a745; color: #fff; border: none;" onclick="return confirm('Accept this bid?')">
                            <i class="fa-solid fa-check me-1"></i>Accept
                        </button>
                    </form>
                    <a href="{{ route('seller.counter-terms', $agentBid->id) }}" class="btn btn-sm" style="background: #ffc107; color: #000; border: none;">
                        <i class="fa-solid fa-right-left me-1"></i>Counter
                    </a>
                    <form action="{{ route('rejectSABid') }}" method="POST" class="d-inline">
                        @csrf
                        <input type="hidden" name="bid_id" value="{{ $agentBid->id }}">
                        <input type="hidden" name="auction_id" value="{{ $agentBid->auction->id }}">
                        <button type="submit" class="btn btn-sm" style="background: #dc3545; color: #fff; border: none;" onclick="return confirm('Reject this bid?')">
                            <i class="fa-solid fa-times me-1"></i>Reject
                        </button>
                    </form>
                @elseif($bidStatus === 'Countered')
                    <span class="btn btn-sm" style="background: #fff3cd; color: #856404; border: 1px solid #ffc107;">
                        <i class="fa-solid fa-clock me-1"></i>Awaiting Response
                    </span>
                @elseif($bidStatus === 'Accepted')
                    <a href="{{ route('seller.agent.auction.detail', $agentBid->auction->id) }}" class="btn btn-sm" style="background: #28a745; color: #fff; border: none;">
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
            <a href="{{ route('hire.agent.auction', ['user_type' => 'seller']) }}" class="btn" style="background: #049399; color: #fff;">
                <i class="fa-solid fa-plus me-1"></i>Create a Listing
            </a>
        </div>
    @endif
</div>
@endsection
