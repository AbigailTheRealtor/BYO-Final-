@extends('layouts.main')
@push('styles')
<style>
    .services ul {
        --icon-size: 1em;
        --gutter: .5em;
        padding: 0 0 0 calc(var(--icon-size) + 2em);
    }
    .services ul li {
        padding-left: var(--gutter);
        color: #34465c;
    }
    .services ul li::marker {
        content: "\f101";
        font-family: FontAwesome;
        font-size: var(--icon-size);
        color: #11b7cf;
    }
    .listing-group-card {
        border: 2px solid #049399;
        border-radius: 10px;
        overflow: hidden;
        margin-bottom: 1.5rem;
    }
    .listing-group-header {
        background: #049399;
        color: #fff;
        padding: 12px 16px;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
    }
    .listing-group-actions {
        background: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
        padding: 8px 16px;
        display: flex;
        gap: 8px;
        align-items: center;
        flex-wrap: wrap;
    }
    .bid-card {
        border: 1px solid #dee2e6;
        border-radius: 8px;
        margin-bottom: 12px;
    }
    .bid-card:last-child {
        margin-bottom: 0;
    }
    .bids-area {
        padding: 16px;
        background: #fff;
    }
    .no-bids-placeholder {
        text-align: center;
        padding: 24px 16px;
        color: #6c757d;
    }
</style>
@endpush

@section('content')
<div class="mainDashboard">
    <div class="container">
        @include('layouts.partials.dashboard_user_section')
        <div class="dashboardContentDetails mt-3">
            <div class="card">
                <div class="row">
                    @include('layouts.partials.sidenav')
                    <div class="rightCol col-sm-12 col-md-9 col-lg-9">
                        <div class="container mt-5 myAuctions">
                            <h1>Hire Seller's Agent Listings</h1>

                            <select class="form-select mt-4 mb-4 w-25 auction-type">
                                <option value="2" {{ $type == '2' ? 'selected' : '' }}>Live ({{ $liveCount }})</option>
                                <option value="1" {{ $type == '1' ? 'selected' : '' }}>Pending Approval ({{ $pendingApprovalCount }})</option>
                                <option value="3" {{ $type == '3' ? 'selected' : '' }}>Awarded ({{ $soldCount }})</option>
                            </select>

                            @if($auctions->isEmpty())
                                <div class="text-center py-5">
                                    <i class="fa-solid fa-clipboard-list fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No listings found</h5>
                                    <p class="text-muted">You have no listings in this category yet.</p>
                                </div>
                            @else
                                @foreach($auctions as $auction)
                                <div class="listing-group-card" x-data="{ open: true }">

                                    {{-- Listing header --}}
                                    <div class="listing-group-header" @click="open = !open">
                                        <div>
                                            <div class="fw-bold" style="font-size: 1rem;">{{ $auction->title }}</div>
                                            <small style="opacity: 0.88;">
                                                {{ $auction->get->cities[0] ?? '' }}{{ ($auction->get->cities[0] ?? '') && ($auction->get->state ?? '') ? ', ' : '' }}{{ $auction->get->state ?? '' }}
                                            </small>
                                        </div>
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="text-end">
                                                <div style="font-size: 12px; opacity: 0.85;">{{ \Carbon\Carbon::parse($auction->created_at)->format('M d, Y') }}</div>
                                                <span class="badge" style="background: rgba(255,255,255,0.25); color: #fff; font-size: 12px;">
                                                    {{ $auction->bids->count() }} Bid{{ $auction->bids->count() != 1 ? 's' : '' }}
                                                </span>
                                            </div>
                                            <i class="fas" :class="open ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                                        </div>
                                    </div>

                                    {{-- Listing action buttons --}}
                                    <div class="listing-group-actions">
                                        <a href="{{ route('seller.agent.auction.detail', $auction->id) }}" class="btn btn-sm" style="background:#049399;color:#fff;border:none;">
                                            <i class="fa-solid fa-eye me-1"></i>View Listing
                                        </a>
                                        @if(!$auction->is_approved)
                                        <a href="{{ route('hire.agent.auction.edit', ['auctionId' => $auction->id, 'user_type' => $auction->get->user_type ?? '']) }}" class="btn btn-sm" style="border:1px solid #6c757d;color:#6c757d;background:transparent;">
                                            <i class="fa-solid fa-pencil-alt me-1"></i>Edit Listing
                                        </a>
                                        @endif
                                    </div>

                                    {{-- Bid cards area --}}
                                    <div x-show="open" x-transition class="bids-area">
                                        @if($auction->bids->isEmpty())
                                            <div class="no-bids-placeholder">
                                                <i class="fa-solid fa-inbox fa-2x mb-2"></i>
                                                <p class="mb-0">No bids yet on this listing.</p>
                                            </div>
                                        @else
                                            @foreach($auction->bids as $bid)
                                            @php
                                                $agentName  = $bid->user->name ?? 'Agent';
                                                $agentEmail = $bid->user->email ?? '';
                                                $bidStatus  = $bid->bid_status ?? 'Active';
                                                $statusStyles = [
                                                    'Active'    => 'background-color:#007bff;color:#fff;',
                                                    'Countered' => 'background-color:#ffc107;color:#000;',
                                                    'Accepted'  => 'background-color:#28a745;color:#fff;',
                                                    'Rejected'  => 'background-color:#dc3545;color:#fff;',
                                                ];
                                                $auctionBaselineData = json_decode(json_encode($auction->get ?? []), true) ?: [];
                                                $bidData    = json_decode(json_encode($bid->get ?? []), true) ?: [];
                                                $propType   = $auction->get->property_type ?? 'Residential Property';
                                                $matchScore = \App\Helpers\SellerBidMatchScoreHelper::calculate($auctionBaselineData, $bidData, null, $propType);
                                                $totalScore       = $matchScore['overall_percent'];
                                                $brokerScore      = $matchScore['terms_match_percent'];
                                                $brokerMatched    = $matchScore['terms_matched_count'];
                                                $brokerTotal      = $matchScore['terms_baseline_total'];
                                                $brokerMismatches = $matchScore['changed_terms'];
                                                $servicesScore    = $matchScore['services_match_percent'];
                                                $servicesMatched  = $matchScore['services_matched_count'];
                                                $servicesTotal    = $matchScore['services_baseline_total'];
                                                $servicesMissing  = $matchScore['missing_services'] ?? [];
                                                $servicesAdded    = $matchScore['extra_services'] ?? [];
                                                $getScoreColor    = function($s) {
                                                    if ($s >= 80) return '#28a745';
                                                    if ($s >= 50) return '#ffc107';
                                                    return '#dc3545';
                                                };
                                                $totalScoreColor    = $getScoreColor($totalScore);
                                                $brokerScoreColor   = $getScoreColor($brokerScore);
                                                $servicesScoreColor = $getScoreColor($servicesScore);
                                            @endphp
                                            <div class="bid-card">
                                                <div class="card-header d-flex justify-content-between align-items-center" style="background:#f8f9fa;border-bottom:1px solid #dee2e6;">
                                                    <div>
                                                        <span class="fw-bold">{{ $agentName }}</span>
                                                        <small class="text-muted d-block">{{ $agentEmail }}</small>
                                                    </div>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <span class="badge" style="{{ $statusStyles[$bidStatus] ?? $statusStyles['Active'] }} padding:6px 12px;border-radius:4px;">{{ $bidStatus }}</span>
                                                        <span class="badge" style="background:{{ $totalScoreColor }};color:#fff;padding:6px 12px;border-radius:4px;">
                                                            <i class="fa-solid fa-chart-pie me-1"></i>{{ $totalScore }}% Match
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="card-body">
                                                    <div class="row">
                                                        <div class="col-md-4 mb-2">
                                                            <small class="text-muted">Submitted</small>
                                                            <div>{{ $bid->created_at->format('M d, Y') }}</div>
                                                        </div>
                                                        <div class="col-md-4 mb-2">
                                                            <small class="text-muted d-block">Broker Terms</small>
                                                            <span style="color:{{ $brokerScoreColor }};font-weight:600;">{{ $brokerScore }}%</span>
                                                            <small class="text-muted">{{ $brokerTotal > 0 ? '('.$brokerMatched.'/'.$brokerTotal.')' : 'No terms provided' }}</small>
                                                        </div>
                                                        <div class="col-md-4 mb-2">
                                                            <small class="text-muted d-block">Services</small>
                                                            <span style="color:{{ $servicesScoreColor }};font-weight:600;">{{ $servicesScore }}%</span>
                                                            <small class="text-muted">{{ $servicesTotal > 0 ? '('.$servicesMatched.'/'.$servicesTotal.')' : 'No services requested' }}</small>
                                                        </div>
                                                    </div>
                                                    @if(count($brokerMismatches) > 0 || count($servicesAdded) > 0 || count($servicesMissing) > 0)
                                                    <div class="mt-2 pt-2" style="border-top:1px solid #eee;">
                                                        <div class="row">
                                                            @if(count($brokerMismatches) > 0)
                                                            <div class="col-md-4 mb-2">
                                                                <small class="fw-semibold text-danger"><i class="fa-solid fa-exclamation-triangle me-1"></i>Term Differences ({{ count($brokerMismatches) }})</small>
                                                                <div class="mt-1" style="max-height:80px;overflow-y:auto;">
                                                                    @foreach(array_slice(array_keys($brokerMismatches), 0, 5) as $field)
                                                                        <span class="badge me-1 mb-1" style="background:#ffe6e6;color:#dc3545;font-size:0.7rem;">{{ ucwords(str_replace('_', ' ', $field)) }}</span>
                                                                    @endforeach
                                                                    @if(count($brokerMismatches) > 5)
                                                                        <span class="badge" style="background:#f8d7da;color:#721c24;font-size:0.7rem;">+{{ count($brokerMismatches) - 5 }} more</span>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                            @endif
                                                            @if(count($servicesAdded) > 0)
                                                            <div class="col-md-4 mb-2">
                                                                <small class="fw-semibold" style="color:#28a745;"><i class="fa-solid fa-plus-circle me-1"></i>Extra Services ({{ count($servicesAdded) }})</small>
                                                                <div class="mt-1" style="max-height:80px;overflow-y:auto;">
                                                                    @foreach(array_slice($servicesAdded, 0, 3) as $svc)
                                                                        <span class="badge me-1 mb-1" style="background:#e6ffe6;color:#28a745;font-size:0.7rem;">{{ Str::limit($svc, 30) }}</span>
                                                                    @endforeach
                                                                    @if(count($servicesAdded) > 3)
                                                                        <span class="badge" style="background:#d4edda;color:#155724;font-size:0.7rem;">+{{ count($servicesAdded) - 3 }} more</span>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                            @endif
                                                            @if(count($servicesMissing) > 0)
                                                            <div class="col-md-4 mb-2">
                                                                <small class="fw-semibold" style="color:#ffc107;"><i class="fa-solid fa-minus-circle me-1"></i>Not Offered ({{ count($servicesMissing) }})</small>
                                                                <div class="mt-1" style="max-height:80px;overflow-y:auto;">
                                                                    @foreach(array_slice($servicesMissing, 0, 3) as $svc)
                                                                        <span class="badge me-1 mb-1" style="background:#fff3cd;color:#856404;font-size:0.7rem;">{{ Str::limit($svc, 30) }}</span>
                                                                    @endforeach
                                                                    @if(count($servicesMissing) > 3)
                                                                        <span class="badge" style="background:#ffeeba;color:#856404;font-size:0.7rem;">+{{ count($servicesMissing) - 3 }} more</span>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    @endif
                                                </div>
                                                <div class="card-footer d-flex justify-content-end gap-2" style="background:#f8f9fa;">
                                                    <a href="{{ route('seller.agent.bid.detail', $bid->id) }}" class="btn btn-sm" style="background:#fff;border:1px solid #049399;color:#049399;">
                                                        <i class="fa-solid fa-eye me-1"></i>View Bid
                                                    </a>
                                                    @if($bidStatus === 'Active')
                                                        <form action="{{ route('acceptSABid') }}" method="POST" class="d-inline">
                                                            @csrf
                                                            <input type="hidden" name="bid_id" value="{{ $bid->id }}">
                                                            <input type="hidden" name="auction_id" value="{{ $auction->id }}">
                                                            <button type="submit" class="btn btn-sm" style="background:#28a745;color:#fff;border:none;" onclick="return confirm('Accept this bid?')">
                                                                <i class="fa-solid fa-check me-1"></i>Accept
                                                            </button>
                                                        </form>
                                                        <a href="{{ route('seller.counter-terms', $bid->id) }}" class="btn btn-sm" style="background:#ffc107;color:#000;border:none;">
                                                            <i class="fa-solid fa-exchange-alt me-1"></i>Counter
                                                        </a>
                                                        <form action="{{ route('rejectSABid') }}" method="POST" class="d-inline">
                                                            @csrf
                                                            <input type="hidden" name="bid_id" value="{{ $bid->id }}">
                                                            <input type="hidden" name="auction_id" value="{{ $auction->id }}">
                                                            <button type="submit" class="btn btn-sm" style="background:#dc3545;color:#fff;border:none;" onclick="return confirm('Reject this bid?')">
                                                                <i class="fa-solid fa-times me-1"></i>Reject
                                                            </button>
                                                        </form>
                                                    @elseif($bidStatus === 'Countered')
                                                        <span class="btn btn-sm" style="background:#fff3cd;color:#856404;border:1px solid #ffc107;">
                                                            <i class="fa-solid fa-clock me-1"></i>Awaiting Response
                                                        </span>
                                                    @elseif($bidStatus === 'Accepted')
                                                        <a href="{{ route('seller.agent.auction.detail', $auction->id) }}" class="btn btn-sm" style="background:#28a745;color:#fff;border:none;">
                                                            <i class="fa-solid fa-file-contract me-1"></i>View Summary
                                                        </a>
                                                    @endif
                                                </div>
                                            </div>
                                            @endforeach
                                        @endif
                                    </div>

                                </div>
                                @endforeach
                            @endif

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(function() {
        $('.auction-type').on('change', function() {
            var val = $(this).val();
            window.location.href = '{{ route('hireSellerAgentHireAuctions') }}?type=' + val;
        });
    });
</script>
@endpush
