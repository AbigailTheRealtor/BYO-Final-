@extends('layouts.main')
@push('styles')
<style>
.agent-bid-page h4 { font-weight: 700; }
.agent-bid-page .page-subtitle { font-size: .85rem; color: #6c757d; }
.agent-bid-page .back-link { font-size: .8rem; color: #049399; text-decoration: none; }
.agent-bid-page .back-link:hover { text-decoration: underline; }
.agent-bid-card { border-left: 3px solid #198754 !important; }
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
                        <div class="container mt-4 agent-bid-page">

                            {{-- Page Header --}}
                            <div class="mb-3">
                                <a href="{{ route('agent.hire-listings') }}" class="back-link">
                                    <i class="fa fa-arrow-left me-1"></i> My Hire Agent Listings
                                </a>
                                <h4 class="mt-2 mb-0">Hire Landlord's Agent — My Bids</h4>
                                <p class="page-subtitle mb-0">Listings where you've placed bids to represent Landlords.</p>
                            </div>
                            <hr class="mt-2 mb-3">

                            {{-- Status Filter --}}
                            <div class="d-flex gap-2 mb-4 flex-wrap">
                                <a href="{{ route('landlord.biding.auctions.list') }}?status=2"
                                   class="btn btn-sm {{ $status == '2' ? 'text-white' : 'btn-outline-secondary' }}"
                                   style="{{ $status == '2' ? 'background:#049399;border-color:#049399;' : '' }}">
                                    Live <span class="badge bg-white text-dark ms-1">{{ $liveCount }}</span>
                                </a>
                                <a href="{{ route('landlord.biding.auctions.list') }}?status=1"
                                   class="btn btn-sm {{ $status == '1' ? 'text-white' : 'btn-outline-secondary' }}"
                                   style="{{ $status == '1' ? 'background:#6c757d;border-color:#6c757d;' : '' }}">
                                    Bidding Lost <span class="badge bg-white text-dark ms-1">{{ $notWonCount }}</span>
                                </a>
                                <a href="{{ route('landlord.biding.auctions.list') }}?status=3"
                                   class="btn btn-sm {{ $status == '3' ? 'text-white' : 'btn-outline-secondary' }}"
                                   style="{{ $status == '3' ? 'background:#28a745;border-color:#28a745;' : '' }}">
                                    Awarded <span class="badge bg-white text-dark ms-1">{{ $soldCount }}</span>
                                </a>
                            </div>

                            @if($auctions->isEmpty())
                                <div class="text-center text-muted py-5">
                                    <i class="fa-solid fa-inbox fa-3x mb-3 d-block opacity-25"></i>
                                    <p class="fw-semibold mb-1">No listings found for this status.</p>
                                    <p class="small mb-0">Switch the filter above or browse live Landlord's Agent listings to place a bid.</p>
                                </div>
                            @else
                                @foreach ($auctions as $auction)
                                @php
                                    $userBid = $auction->bids->where('user_id', auth()->id())->first();

                                    // Bid status
                                    $bidStateRaw = $userBid ? strtolower($userBid->accepted ?? '') : '';
                                    $bidStatus = $userBid
                                        ? ($userBid->bid_status ?? match($bidStateRaw) {
                                            'accepted' => 'Accepted',
                                            'rejected' => 'Rejected',
                                            'countered' => 'Countered',
                                            default => 'Active',
                                          })
                                        : 'No Bid';
                                    $statusStyles = [
                                        'Active'   => 'background-color:#007bff;color:#fff;',
                                        'Countered'=> 'background-color:#ffc107;color:#000;',
                                        'Accepted' => 'background-color:#28a745;color:#fff;',
                                        'Rejected' => 'background-color:#dc3545;color:#fff;',
                                        'No Bid'   => 'background-color:#6c757d;color:#fff;',
                                    ];

                                    // Listing info
                                    $listingAddress = $auction->get->address ?? $auction->title ?? 'N/A';
                                    $listingId = $auction->listing_id ?? 'LAA-'.$auction->id;

                                    // Match score
                                    $auctionBaselineData = json_decode(json_encode($auction->get ?? []), true) ?: [];
                                    $bidData = $userBid ? (json_decode(json_encode($userBid->get ?? []), true) ?: []) : [];
                                    $propType = $auction->get->property_type ?? 'Residential Property';

                                    $matchScore = \App\Helpers\LandlordBidMatchScoreHelper::calculate(
                                        $auctionBaselineData, $bidData, null, $propType
                                    );
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

                                    $getScoreColor = fn($s) => $s >= 80 ? '#28a745' : ($s >= 50 ? '#ffc107' : '#dc3545');
                                    $totalScoreColor    = $getScoreColor($totalScore);
                                    $brokerScoreColor   = $getScoreColor($brokerScore);
                                    $servicesScoreColor = $getScoreColor($servicesScore);
                                @endphp

                                <div class="card mb-3 agent-bid-card" style="border-radius:8px;border:1px solid #dee2e6;">
                                    {{-- Card Header --}}
                                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2"
                                         style="background:#f8f9fa;border-bottom:1px solid #dee2e6;">
                                        <div>
                                            <div class="fw-bold" style="font-size:.95rem;">{{ Str::limit($listingAddress, 50) }}</div>
                                            <small class="text-muted" style="font-family:monospace;font-size:.75rem;">{{ $listingId }}</small>
                                        </div>
                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                            @if($userBid)
                                            <span class="badge" style="{{ $statusStyles[$bidStatus] ?? $statusStyles['Active'] }}padding:6px 12px;border-radius:4px;">
                                                {{ $bidStatus }}
                                            </span>
                                            <span class="badge" style="background:{{ $totalScoreColor }};color:#fff;padding:6px 12px;border-radius:4px;">
                                                <i class="fa-solid fa-chart-pie me-1"></i>{{ $totalScore }}% Match
                                            </span>
                                            @else
                                            <span class="badge bg-secondary" style="padding:6px 12px;border-radius:4px;">No Bid Placed</span>
                                            @endif
                                        </div>
                                    </div>

                                    @if($userBid)
                                    {{-- Card Body --}}
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-4 mb-2">
                                                <small class="text-muted d-block">Bid Submitted</small>
                                                <div>{{ $userBid->created_at->format('M d, Y') }}</div>
                                            </div>
                                            <div class="col-md-4 mb-2">
                                                <small class="text-muted d-block">Broker Terms</small>
                                                <span style="color:{{ $brokerScoreColor }};font-weight:600;">{{ $brokerScore }}%</span>
                                                <small class="text-muted ms-1">{{ $brokerTotal > 0 ? '('.$brokerMatched.'/'.$brokerTotal.')' : 'No terms provided' }}</small>
                                            </div>
                                            <div class="col-md-4 mb-2">
                                                <small class="text-muted d-block">Services</small>
                                                <span style="color:{{ $servicesScoreColor }};font-weight:600;">{{ $servicesScore }}%</span>
                                                <small class="text-muted ms-1">{{ $servicesTotal > 0 ? '('.$servicesMatched.'/'.$servicesTotal.')' : 'No services requested' }}</small>
                                            </div>
                                        </div>

                                        @if(count($brokerMismatches) > 0 || count($servicesAdded) > 0 || count($servicesMissing) > 0)
                                        <div class="mt-3 pt-3" style="border-top:1px solid #eee;">
                                            <div class="row">
                                                @if(count($brokerMismatches) > 0)
                                                <div class="col-md-4 mb-2">
                                                    <small class="fw-semibold text-danger">
                                                        <i class="fa-solid fa-exclamation-triangle me-1"></i>Term Differences ({{ count($brokerMismatches) }})
                                                    </small>
                                                    <div class="mt-1">
                                                        @foreach(array_slice(array_keys($brokerMismatches), 0, 5) as $field)
                                                            <span class="badge me-1 mb-1" style="background:#ffe6e6;color:#dc3545;font-size:.7rem;">
                                                                {{ ucwords(str_replace('_', ' ', $field)) }}
                                                            </span>
                                                        @endforeach
                                                        @if(count($brokerMismatches) > 5)
                                                            <span class="badge" style="background:#f8d7da;color:#721c24;font-size:.7rem;">+{{ count($brokerMismatches) - 5 }} more</span>
                                                        @endif
                                                    </div>
                                                </div>
                                                @endif
                                                @if(count($servicesAdded) > 0)
                                                <div class="col-md-4 mb-2">
                                                    <small class="fw-semibold" style="color:#28a745;">
                                                        <i class="fa-solid fa-plus-circle me-1"></i>Extra Services ({{ count($servicesAdded) }})
                                                    </small>
                                                    <div class="mt-1">
                                                        @foreach(array_slice($servicesAdded, 0, 3) as $svc)
                                                            <span class="badge me-1 mb-1" style="background:#e6ffe6;color:#28a745;font-size:.7rem;">{{ Str::limit($svc, 30) }}</span>
                                                        @endforeach
                                                        @if(count($servicesAdded) > 3)
                                                            <span class="badge" style="background:#d4edda;color:#155724;font-size:.7rem;">+{{ count($servicesAdded) - 3 }} more</span>
                                                        @endif
                                                    </div>
                                                </div>
                                                @endif
                                                @if(count($servicesMissing) > 0)
                                                <div class="col-md-4 mb-2">
                                                    <small class="fw-semibold" style="color:#ffc107;">
                                                        <i class="fa-solid fa-minus-circle me-1"></i>Not Offered ({{ count($servicesMissing) }})
                                                    </small>
                                                    <div class="mt-1">
                                                        @foreach(array_slice($servicesMissing, 0, 3) as $svc)
                                                            <span class="badge me-1 mb-1" style="background:#fff3cd;color:#856404;font-size:.7rem;">{{ Str::limit($svc, 30) }}</span>
                                                        @endforeach
                                                        @if(count($servicesMissing) > 3)
                                                            <span class="badge" style="background:#ffeeba;color:#856404;font-size:.7rem;">+{{ count($servicesMissing) - 3 }} more</span>
                                                        @endif
                                                    </div>
                                                </div>
                                                @endif
                                            </div>
                                        </div>
                                        @endif
                                    </div>

                                    {{-- Card Footer --}}
                                    <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2"
                                         style="background:#f8f9fa;">
                                        <div>
                                            <a href="{{ route('landlord.agent.auction.view', $auction->id) }}"
                                               class="btn btn-sm btn-outline-secondary" style="font-size:.8rem;">
                                                <i class="fa-solid fa-building me-1"></i>View Listing
                                            </a>
                                        </div>
                                        <div class="d-flex gap-2 flex-wrap justify-content-end">
                                            <a href="{{ route('landlord.agent.auction.bid.view', $userBid->id) }}"
                                               class="btn btn-sm" style="background:#fff;border:1px solid #049399;color:#049399;font-size:.8rem;">
                                                <i class="fa-solid fa-eye me-1"></i>View Bid
                                            </a>
                                            @if($bidStatus === 'Accepted')
                                                <a href="{{ route('landlord.agent.auction.view', $auction->id) }}"
                                                   class="btn btn-sm" style="background:#28a745;color:#fff;font-size:.8rem;">
                                                    <i class="fa-solid fa-file-contract me-1"></i>View Summary
                                                </a>
                                            @elseif($bidStatus === 'Countered')
                                                <a href="{{ route('landlord.hire.agent.auction.bid.view-counter', $userBid->id) }}"
                                                   class="btn btn-sm" style="background:#fff3cd;color:#856404;border:1px solid #ffc107;font-size:.8rem;">
                                                    <i class="fa-solid fa-exchange-alt me-1"></i>View Counter Terms
                                                </a>
                                            @elseif($bidStatus === 'Active')
                                                <a href="{{ route('agent.landlord.agent.auction.bid', $auction->id) }}"
                                                   class="btn btn-sm btn-outline-secondary" style="font-size:.8rem;">
                                                    <i class="fa-solid fa-edit me-1"></i>Edit Bid
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                    @endif
                                </div>
                                @endforeach

                                {{-- Pagination --}}
                                @if($auctions instanceof \Illuminate\Pagination\LengthAwarePaginator && $auctions->hasPages())
                                    <div class="mt-3">{{ $auctions->links() }}</div>
                                @endif
                            @endif

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
