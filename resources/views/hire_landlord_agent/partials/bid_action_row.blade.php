{{--
    Shared Landlord bid action row partial.

    Expected variables (all must be passed via @include):
        $bid                  – the LandlordAgentAuctionBid model / data-get target
        $auction              – the LandlordAgentAuction model / data-get target
        $isOwner              – bool: current user is the listing (auction) owner
        $state                – string: '0' | 'accepted' | 'rejected' | 'countered'
        $isSold               – bool: listing has been sold/closed
        $isExpired            – bool: traditional listing has expired
        $isTraditionalListing – bool: listing is traditional (not open-bid / auction)
        $latestOwnerCounter   – the latest counter model or null
        $ownerFirst           – string: auction owner first name
        $ownerLast            – string: auction owner last name
        $agentFirst           – string: bid agent first name
        $agentLast            – string: auction agent last name
--}}

{{-- ── Listing owner: action buttons when bid is undecided ── --}}
@if ($state === '0' && $isOwner && !$isSold)
    @if ($isTraditionalListing && $isExpired)
    <div class="w-100 p-2 text-center" style="background: #ffc107; border-radius: 6px; color: #856404;">
        <i class="fa fa-clock me-1"></i> Listing has expired — no further actions available. You can extend the expiration date by editing the listing.
    </div>
    @else
    <div class="d-flex gap-3 justify-content-center align-items-center w-100" style="flex-wrap: nowrap;">
        <form action="{{ route('agent.landlord.auction.bid.accept', ['id' => data_get($bid, 'id')]) }}" method="POST" style="margin: 0;"
              onsubmit="return confirm('Are you sure you want to accept this bid? This will reject all other bids.');">
            @csrf
            <button type="submit" class="btn btn-success" style="padding: 10px 20px; font-size: 0.95rem; background-color: #28a745 !important; border-color: #28a745 !important; color: #fff !important; min-width: 130px; height: 42px; display: inline-flex; align-items: center; justify-content: center;">
                <i class="fa fa-check me-1"></i> Accept Bid
            </button>
        </form>
        <a href="{{ route('landlord.counter-terms', ['id' => data_get($bid, 'id')]) }}"
           class="btn btn-primary" style="padding: 10px 20px; font-size: 0.95rem; background-color: #0d6efd !important; border-color: #0d6efd !important; color: #fff !important; min-width: 130px; height: 42px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none;">
            <i class="fa fa-right-left me-1"></i> Counter Bid
        </a>
        <form action="{{ route('agent.landlord.auction.bid.reject', ['id' => data_get($bid, 'id')]) }}" method="POST" style="margin: 0;"
              onsubmit="return confirm('Are you sure you want to reject this bid?');">
            @csrf
            <button type="submit" class="btn btn-danger" style="padding: 10px 20px; font-size: 0.95rem; background-color: #dc3545 !important; border-color: #dc3545 !important; color: #fff !important; min-width: 130px; height: 42px; display: inline-flex; align-items: center; justify-content: center;">
                <i class="fa fa-times me-1"></i> Reject Bid
            </button>
        </form>
    </div>
    @endif
@endif

{{-- ── Accepted state ── --}}
@if ($state === 'accepted')
<div class="w-100 p-2 text-center" style="background: #d4edda; border-radius: 6px; color: #155724;">
    <i class="fa fa-circle-check me-1"></i>
    @if ($isOwner) This bid has been accepted.
    @else {{ trim($ownerFirst . ' ' . $ownerLast) }} accepted this bid.
    @endif
</div>
@php $__partialBidSummary = \App\Models\AcceptedBidSummary::where('accepted_bid_id', data_get($bid, 'id'))->where('agent_user_id', data_get($bid, 'user_id'))->first(); @endphp
@if ($__partialBidSummary && ($isOwner || data_get($bid, 'user_id') == Auth::id()))
<div class="d-flex gap-2 flex-wrap justify-content-center w-100 mt-2">
    <a href="{{ route('accepted-bid-summary.view', $__partialBidSummary->id) }}" class="btn btn-outline-primary btn-sm">
        <i class="fa fa-file-lines me-1"></i> View Accepted Bid Summary
    </a>
    @if (data_get($bid, 'user_id') == Auth::id() && !$__partialBidSummary->isAgentSigned())
    <a href="{{ route('accepted-bid-summary.sign-form', $__partialBidSummary->id) }}" class="btn btn-primary btn-sm">
        <i class="fa fa-signature me-1"></i> Agent: E-Sign Acknowledgement
    </a>
    @endif
    @if ($isOwner && !$__partialBidSummary->isTenantSigned())
    <a href="{{ route('accepted-bid-summary.sign-form', $__partialBidSummary->id) }}" class="btn btn-primary btn-sm">
        <i class="fa fa-signature me-1"></i> Landlord: E-Sign Acknowledgement
    </a>
    @endif
    @if ($__partialBidSummary->isFullySigned())
    <a href="{{ route('accepted-bid-summary.download-pdf', $__partialBidSummary->id) }}" class="btn btn-success btn-sm">
        <i class="fa fa-download me-1"></i> Download Signed PDF
    </a>
    @endif
</div>
@endif

{{-- ── Rejected state ── --}}
@elseif ($state === 'rejected')
<div class="w-100 p-2 text-center" style="background: #f8d7da; border-radius: 6px; color: #721c24;">
    <i class="fa fa-circle-xmark me-1"></i>
    @if ($isOwner) This bid has been rejected.
    @else {{ trim($ownerFirst . ' ' . $ownerLast) }} rejected this bid.
    @endif
</div>

{{-- ── Countered state ── --}}
@elseif ($state === 'countered')
@php
    $isCounterFromOwner = $latestOwnerCounter && ($latestOwnerCounter->user_id == data_get($auction, 'user_id'));
    // Viewer sent the latest counter = owner viewer + owner sent latest, OR agent viewer + agent sent latest.
    $_landlordViewerSentLatest = ($isOwner && $isCounterFromOwner) || (!$isOwner && !$isCounterFromOwner);
@endphp
<div class="w-100 p-2 text-center" style="background: #fff3cd; border-radius: 6px; color: #856404;">
    <i class="fa fa-right-left me-1"></i>
    @if ($_landlordViewerSentLatest)
        <strong>Counter Offer Sent.</strong>
    @else
        <strong>Counter Offer Received.</strong>
    @endif
</div>
<div class="d-flex gap-2 flex-wrap justify-content-center w-100 mt-2">
    {{-- View Counter Terms — always shown --}}
    <a href="{{ route('landlord.hire.agent.auction.bid.view-counter', data_get($bid, 'id')) }}" class="btn" style="background-color:#fff;border:2px solid #049399;color:#049399;padding:5px 12px;font-weight:600;font-size:0.85rem;">
        <i class="fa fa-eye me-1"></i> View Counter Terms
    </a>
    @if ($_landlordViewerSentLatest)
    {{-- Viewer sent latest — waiting: show Edit Counter Terms --}}
    @if ($isOwner)
    <a href="{{ route('landlord.edit-counter-terms', ['id' => data_get($bid, 'id')]) }}" class="btn" style="background-color:#049399;border:2px solid #049399;color:#fff;padding:5px 12px;font-weight:600;font-size:0.85rem;">
        <i class="fa fa-pen-to-square me-1"></i> Edit Counter Terms
    </a>
    @else
    <a href="{{ route('landlord.agent.auction.counter-bid', ['id' => data_get($auction, 'id'), 'bid_id' => data_get($bid, 'id')]) }}" class="btn" style="background-color:#049399;border:2px solid #049399;color:#fff;padding:5px 12px;font-weight:600;font-size:0.85rem;">
        <i class="fa fa-pen-to-square me-1"></i> Edit Counter Terms
    </a>
    @endif
    {{-- Accept / Counter Back / Reject are on View Counter Terms page only --}}
    @endif
</div>

{{-- ── Pending / undecided state (non-owner sees waiting message) ── --}}
@elseif ($state === '0')
@if (data_get($bid, 'user_id') == Auth::id())
<div class="alert alert-secondary mt-2 w-100 mb-0 py-1 small">
    ⏳ Waiting for a response from {{ trim($ownerFirst . ' ' . $ownerLast) }}...
</div>
@else
<div class="alert alert-light mt-2 w-100 mb-0 py-1 small">
    ⏳ Bid from {{ trim($agentFirst . ' ' . $agentLast) }} is pending.
</div>
@endif
@endif
