@extends('my-bids.layout')
@section('bids-content')
    <div class="container mt-5 myBid">
        <div class="accordion" id="accordionExample">
            @foreach ($bids as $bid)
                @php
                    $auction = $bid->auction;
                    if (!$auction || !$auction->id) {
                        continue;
                    }
                    $bidStatus = $bid->bid_status;
                    $statusClass = match($bidStatus) {
                        'Accepted' => 'bg-success',
                        'Countered' => 'bg-warning text-dark',
                        'Rejected' => 'bg-danger',
                        'Active' => 'bg-primary',
                        default => 'bg-secondary',
                    };
                    $acceptedSummary = $bid->acceptedBidSummary;
                    $auctionType = $auction->get->auction_type ?? 'Traditional';
                    $isBiddingPeriod = ($auctionType === 'Bidding Period');
                    $biddingEndTime = $auction->get->bidding_end_time ?? null;
                @endphp
                <div class="card p-3 mb-3">
                    <div class="row myBidsDetails align-items-center">
                        <div class="col-12 col-md-4 col-lg-4">
                            <div class="fw-bold">{{ $auction->get->address ?? 'Listing' }}</div>
                            <small class="text-muted">Listing ID: {{ $auction->listing_id ?? 'LAA-'.$auction->id }}</small>
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
                                <a href="{{ route('landlord.hire.agent.auction.bid.view-counter', $bid->id) }}" class="btn btn-sm btn-warning text-dark">
                                    View Counter
                                </a>
                            @elseif($bidStatus === 'Rejected')
                                <a href="{{ route('landlord.agent.auction.view', $auction->id) }}" class="btn btn-sm btn-outline-secondary">
                                    View Listing
                                </a>
                            @elseif($bidStatus === 'Active')
                                @if($isBiddingPeriod && $biddingEndTime)
                                    <span class="me-3 text-muted small">
                                        <i class="fa-solid fa-clock me-1"></i>Timer Ends:
                                        <span class="countdown-timer fw-bold" data-end="{{ $biddingEndTime }}">--:--:--</span>
                                    </span>
                                @endif
                                <a href="{{ route('agent.landlord.auction.bid', $auction->id) }}?edit={{ $bid->id }}" class="btn btn-sm btn-primary me-2">
                                    <i class="fa-solid fa-edit me-1"></i>View/Edit Bid
                                </a>
                                <a href="{{ route('landlord.agent.auction.view', $auction->id) }}" class="btn btn-sm btn-outline-secondary">
                                    Visit Listing
                                </a>
                            @endif
                        </div>
                    </div>
                    @if(!empty($bid->get->offering_price))
                        <div class="fw-bold mt-2">
                            {{ $bid->get->offering_price }}
                            <span class="text-sm-end opacity-5 badge bg-secondary">Current price</span>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
@endsection
