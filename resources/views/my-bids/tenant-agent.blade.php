@extends('my-bids.layout')
@section('bids-content')
    <div class="container mt-5 myBid">
        <!-- Section 1  -->
        {{-- <select class="form-select mt-4 mb-3 w-25">
            <option value="all">All (6)</option>
            <option value="3">Won/Sold (2)</option>
            <option value="2">Lost (2)</option>
            <option value="1">Bidding (2)</option>
            <option value="4">Finished (0)</option>
        </select> --}}
        <!-- End  -->
        <div class="accordion" id="accordionExample">
            <!-- Section 2 -->
            @foreach ($bids as $bid)
                @php
                    $bidStatus = $bid->bid_status;
                    $statusClass = match($bidStatus) {
                        'Accepted' => 'bg-success',
                        'Countered' => 'bg-warning text-dark',
                        'Rejected' => 'bg-danger',
                        'Active' => 'bg-primary',
                        default => 'bg-secondary',
                    };
                    $acceptedSummary = $bid->acceptedBidSummary;
                    $isActive = ($bidStatus === 'Active');
                    $auctionType = $bid->auction->get->auction_type ?? 'Traditional';
                    $isBiddingPeriod = ($auctionType === 'Bidding Period');
                    $biddingEndTime = $bid->auction->get->bidding_end_time ?? null;
                @endphp
                {{-- All bids: static cards (no collapse) --}}
                <div class="card p-3 mb-3">
                    <div class="row myBidsDetails align-items-center">
                        <div class="col-12 col-md-4 col-lg-4">
                            <div class="fw-bold">{{ $bid->auction->get->address ?? 'Listing' }}</div>
                            <small class="text-muted">Listing ID: {{ $bid->auction->listing_id ?? 'TAA-'.$bid->auction->id }}</small>
                        </div>
                        <div class="col-12 col-md-2 col-lg-2 text-center">
                            <span class="badge {{ $statusClass }} p-2">{{ $bidStatus }}</span>
                        </div>
                        <div class="col-12 col-md-6 col-lg-6 text-end">
                            @if($bidStatus === 'Accepted' && $acceptedSummary)
                                <a href="{{ route('accepted-bid-summary.view', $acceptedSummary->id) }}" class="btn btn-sm btn-success">
                                    View Summary
                                </a>
                            @elseif($bidStatus === 'Countered')
                                <a href="{{ route('tenant.agent.auction.view', $bid->auction->id) }}" class="btn btn-sm btn-warning text-dark">
                                    View Counter
                                </a>
                            @elseif($bidStatus === 'Rejected')
                                <a href="{{ route('tenant.agent.auction.view', $bid->auction->id) }}" class="btn btn-sm btn-outline-secondary">
                                    View Listing
                                </a>
                            @elseif($bidStatus === 'Active')
                                @if($isBiddingPeriod && $biddingEndTime)
                                    <span class="me-3 text-muted small">
                                        <i class="fas fa-clock me-1"></i>Timer Ends: 
                                        <span class="countdown-timer fw-bold" data-end="{{ $biddingEndTime }}">--:--:--</span>
                                    </span>
                                @endif
                                <a href="{{ route('tenant.agent.auction.view', $bid->auction->id) }}" class="btn btn-sm btn-primary">
                                    Visit Listing
                                </a>
                            @endif
                        </div>
                    </div>
                     @if(!empty($bid->get->offering_price))
                        <div class="fw-bold mt-2">
                            {{$bid->get->offering_price}}
                            <span class="text-sm-end opacity-5 badge bg-secondary">Current price</span>
                        </div>
                    @endif
                </div>
                <!-- End  -->
            @endforeach
            <!-- End  -->
        </div>

    </div>
@endsection
