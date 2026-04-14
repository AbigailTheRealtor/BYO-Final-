{{--
    QA LOCK — BID COMPARISON SCORING
    DO NOT reintroduce inline scoring logic.
    All scoring must come from SellerBidMatchScoreHelper.
--}}
@extends('layouts.main')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/listingDescription.css') }}" />
@include('partials.bid_detail_styles')
@endpush

@section('content')
@php
  // ── Helper closures ──
  $fmtMoney = function($v) {
    if ($v === null || $v === '') return null;
    $raw = preg_replace('/[^0-9.]/', '', (string)$v);
    if ($raw === '' || !is_numeric($raw)) return null;
    return '$' . number_format((float)$raw, 0);
  };
  $fmtPercent = function($v) {
    if ($v === null || $v === '') return null;
    $raw = preg_replace('/[^0-9.]/', '', (string)$v);
    if ($raw === '' || !is_numeric($raw)) return null;
    $num = (float)$raw;
    return (floor($num) == $num ? (string)(int)$num : (string)$num) . '%';
  };
  $joinParts = function($parts) {
    $parts = array_values(array_filter($parts, fn($p) => $p !== null && $p !== ''));
    return count($parts) ? implode(' + ', $parts) : null;
  };

  // ── Auth & ownership ──
  $auth_id        = Auth::id();
  $isListingOwner = ($auth_id == data_get($auction, 'user_id'));
  $isOwnerRow     = $isListingOwner;
  $isBidOwner     = (data_get($bid, 'user_id') == $auth_id);
  $bidAccepted    = data_get($bid, 'accepted');

  // ── Listing state ──
  $listingType          = trim($auction->get->auction_type ?? '');
  $isTraditionalListing = (strtolower($listingType) === 'traditional listing');
  $isBiddingPeriodListing = (strtolower($listingType) === 'bidding period');
  $isSold = in_array(data_get($auction, 'is_sold'), [true,'true',1,'1'], true);
  $carbon = \Carbon\Carbon::class;
  $expiration = data_get($auction, 'get.ends_at') ?? null;
  $isExpired  = $expiration ? $carbon::now()->gte($carbon::parse($expiration)) : false;

  // ── Seller counter ──
  $hasSellerCounter = \App\Models\SellerCounterTerm::where('seller_agent_auction_bid_id', data_get($bid, 'id'))
      ->where('status', 1)->exists();
  $isTerminalState = in_array((string)$bidAccepted, ['accepted', 'rejected'], true);
  $state = (!$isTerminalState && $hasSellerCounter)
      ? 'countered'
      : ((!$bidAccepted || $bidAccepted === '0' || $bidAccepted === 'no') ? '0' : (string)$bidAccepted);

  // ── Score helpers ──
  $getScoreColor   = fn($s) => \App\Helpers\SellerBidMatchScoreHelper::scoreColor((int)$s);
  $propertyType    = data_get($auction, 'get.property_type', '');
  $bidDataArr      = (array) data_get($bid, 'get', []);
  $auctionDataArr  = (array) data_get($auction, 'get', []);
  $baselineData    = $auctionDataArr;
  $baselineLabel   = $isListingOwner ? 'Your Original Terms' : "Seller's Original Terms";

  $scoreResult     = \App\Helpers\SellerBidMatchScoreHelper::calculate($baselineData, $bidDataArr, null, $propertyType);
  $totalScore      = $scoreResult['overall_percent'] ?? 100;
  $brokerScore     = $scoreResult['broker_comp_percent'] ?? 100;
  $servicesScore   = $scoreResult['services_percent'] ?? 100;
  $brokerTotal     = $scoreResult['broker_comp_total'] ?? 0;
  $brokerMatched   = $scoreResult['broker_comp_matched'] ?? 0;
  $servicesTotal   = $scoreResult['services_baseline_total'] ?? 0;
  $servicesMatched = $scoreResult['services_matched_count'] ?? 0;
  $servicesExtraCount   = $scoreResult['services_extra_count'] ?? 0;
  $servicesMissingCount = $scoreResult['services_missing_count'] ?? 0;
  $brokerMismatches = $scoreResult['changed_terms'] ?? [];
  $termsChangedCount = $scoreResult['terms_changed_count'] ?? 0;
  $termsAddedCount   = $scoreResult['terms_added_count'] ?? 0;
  $mismatchStyle    = 'background-color: #ffe6e6; padding: 2px 6px; border-radius: 4px; border-left: 3px solid #dc3545;';
  $mismatchBadge    = '<span class="badge" style="background-color: #dc3545; color: white; font-size: 0.7rem; vertical-align: middle; margin-left: 8px;">Mismatch</span>';
  $totalScoreColor = \App\Helpers\SellerBidMatchScoreHelper::scoreColor($totalScore);
  $hasAnyBaseline  = ($brokerTotal > 0 || $servicesTotal > 0);

  // ── Counter terms for dual-score ──
  $latestCounter = \App\Models\SellerCounterTerm::with('meta')
      ->where('seller_agent_auction_bid_id', data_get($bid, 'id'))
      ->where('status', 1)
      ->latest('created_at')
      ->first();

  $originalScore = $scoreResult;
  if ($latestCounter && $latestCounter->meta->count()) {
      $latestCounterScore = \App\Helpers\SellerBidMatchScoreHelper::calculate(
          $latestCounter->meta->pluck('meta_value', 'meta_key')->toArray(),
          $bidDataArr, null, $propertyType
      );
      $showDualScore = true;
  } else {
      $latestCounterScore = null;
      $showDualScore      = false;
  }

  // ── Seller Purchase Fee display ──
  $sellerPurchaseFeeType      = data_get($bid, 'get.purchase_fee_type', '');
  $sellerPurchaseFeeFlat      = data_get($bid, 'get.purchase_fee_flat', '');
  $sellerPurchaseFeePerc      = data_get($bid, 'get.purchase_fee_percentage', '');
  $sellerPurchaseFeeFlatCombo = data_get($bid, 'get.purchase_fee_flat_combo', '');
  $sellerPurchaseFeePercCombo = data_get($bid, 'get.purchase_fee_percentage_combo', '');
  $sellerPurchaseFeeOther     = data_get($bid, 'get.purchase_fee_other', '');
  $sellerPurchaseFeeDisplay = '-';
  if ($sellerPurchaseFeeType === 'flat' && $sellerPurchaseFeeFlat) {
      $sellerPurchaseFeeDisplay = $fmtMoney($sellerPurchaseFeeFlat) ?? '-';
  } elseif ($sellerPurchaseFeeType === 'percentage' && $sellerPurchaseFeePerc) {
      $sellerPurchaseFeeDisplay = ($fmtPercent($sellerPurchaseFeePerc) ?? '-') . ' of Total Purchase Price';
  } elseif ($sellerPurchaseFeeType === 'combo') {
      $sellerPurchaseFeeDisplay = $joinParts([
          $sellerPurchaseFeePercCombo ? ($fmtPercent($sellerPurchaseFeePercCombo) . ' of Total Purchase Price') : null,
          $fmtMoney($sellerPurchaseFeeFlatCombo),
      ]) ?? '-';
  } elseif ($sellerPurchaseFeeType === 'other' && $sellerPurchaseFeeOther) {
      $sellerPurchaseFeeDisplay = $sellerPurchaseFeeOther;
  }

  // ── Owner/agent name helpers for footer messages ──
  $ownerId    = data_get($auction, 'user_id');
  $ownerFirst = data_get($auction, 'user.first_name', '');
  $ownerLast  = data_get($auction, 'user.last_name', '');
  $agentFirst = data_get($bid, 'user.first_name', '');
  $agentLast  = data_get($bid, 'user.last_name', '');

  // ── Counter direction ──
  $scFooterLatestFromOwner = $latestCounter && ($latestCounter->user_id == $ownerId);

  // ── Bid status for header badge ──
  $bidStatusLabel = match($state) {
      'accepted'  => 'Accepted',
      'rejected'  => 'Rejected',
      'countered' => 'Countered',
      default     => 'Active',
  };
  $bidStatusColor = match($bidStatusLabel) {
      'Accepted'  => '#28a745',
      'Rejected'  => '#dc3545',
      'Countered' => '#ffc107',
      default     => '#1a4a6e',
  };
@endphp
@php
    $_scViewerSentLatest = ($scFooterLatestFromOwner && Auth::id() == $ownerId)
                        || (!$scFooterLatestFromOwner && Auth::id() != $ownerId);
    $absFooterBidSummary = ($state === 'accepted')
        ? \App\Models\AcceptedBidSummary::where('accepted_bid_id', data_get($bid, 'id'))
            ->where('agent_user_id', data_get($bid, 'user_id'))->first()
        : null;
@endphp

<x-bid-detail-layout
    :backUrl="route('seller.agent.auction.detail', $auction->id)"
    backLabel="Back to Listing"
    roleLabel="Hire a Seller's Agent"
    :listingId="$auction->listing_id ?? $auction->id"
    :address="$auction->address ?? null"
    :bidStatus="$bidStatusLabel"
    :bidStatusColor="$bidStatusColor">

    {{-- ===== BODY ===== --}}
    @include('partials.bid_detail_body.seller')

    {{-- ===== FOOTER BANNERS (w-100 status rows) ===== --}}
    <x-slot name="footerBanners">
        {{-- Expired --}}
        @if ($state === '0' && $isOwnerRow && !$isSold && $isTraditionalListing && $isExpired)
        <div class="w-100 p-2 text-center" style="background: #ffc107; border-radius: 6px; color: #856404;">
            <i class="fa fa-clock me-1"></i> Listing has expired — no further actions available.
        </div>
        @endif

        {{-- Accepted: banner + summary links --}}
        @if ($state === 'accepted')
        <div class="w-100 p-2 text-center" style="background: #d4edda; border-radius: 6px; color: #155724;">
            <i class="fa fa-check-circle me-1"></i>
            @if (Auth::id() == $ownerId) This bid has been accepted.
            @else {{ trim($ownerFirst . ' ' . $ownerLast) }} accepted this bid.
            @endif
        </div>
        @if ($absFooterBidSummary && (Auth::id() == $ownerId || data_get($bid, 'user_id') == Auth::id()))
        <div class="w-100 d-flex gap-2 flex-wrap justify-content-center">
            <a href="{{ route('accepted-bid-summary.view', $absFooterBidSummary->id) }}" class="btn btn-outline-primary btn-sm">
                <i class="fa fa-file-alt me-1"></i> View Accepted Bid Summary
            </a>
            @if (data_get($bid, 'user_id') == Auth::id() && !$absFooterBidSummary->isAgentSigned())
            <a href="{{ route('accepted-bid-summary.sign-form', $absFooterBidSummary->id) }}" class="btn btn-primary btn-sm">
                <i class="fa fa-signature me-1"></i> Agent: E-Sign Acknowledgement
            </a>
            @endif
            @if (Auth::id() == $ownerId && !$absFooterBidSummary->isOwnerSigned())
            <a href="{{ route('accepted-bid-summary.sign-form', $absFooterBidSummary->id) }}" class="btn btn-primary btn-sm">
                <i class="fa fa-signature me-1"></i> Seller: E-Sign Acknowledgement
            </a>
            @endif
            @if ($absFooterBidSummary->isFullySigned())
            <a href="{{ route('accepted-bid-summary.download-pdf', $absFooterBidSummary->id) }}" class="btn btn-success btn-sm">
                <i class="fa fa-download me-1"></i> Download Signed PDF
            </a>
            @endif
        </div>
        @endif

        {{-- Rejected --}}
        @elseif ($state === 'rejected')
        <div class="w-100 p-2 text-center" style="background: #f8d7da; border-radius: 6px; color: #721c24;">
            <i class="fa fa-times-circle me-1"></i>
            @if (Auth::id() == $ownerId) This bid has been rejected.
            @else {{ trim($ownerFirst . ' ' . $ownerLast) }} rejected this bid.
            @endif
        </div>

        {{-- Countered --}}
        @elseif ($state === 'countered')
        <div class="w-100 p-2 text-center" style="background: #fff3cd; border-radius: 6px; color: #856404;">
            <i class="fa fa-exchange-alt me-1"></i>
            @if ($_scViewerSentLatest) <strong>Counter Offer Sent.</strong>
            @else <strong>Counter Offer Received.</strong>
            @endif
        </div>

        {{-- Pending --}}
        @elseif ($state === '0')
        @if (data_get($bid, 'user_id') == Auth::id())
        <div class="w-100 alert alert-secondary mb-0 py-1 small">
            ⏳ Waiting for a response from {{ trim($ownerFirst . ' ' . $ownerLast) }}...
        </div>
        @elseif (!$isOwnerRow)
        <div class="w-100 alert alert-light mb-0 py-1 small">
            ⏳ Bid from {{ trim($agentFirst . ' ' . $agentLast) }} is pending.
        </div>
        @endif
        @endif
    </x-slot>

    {{-- ===== FOOTER ACTIONS (right-side buttons) ===== --}}
    <x-slot name="footerActions">
        {{-- Active + owner: Accept / Counter / Reject --}}
        @if ($state === '0' && $isOwnerRow && !$isSold && !($isTraditionalListing && $isExpired))
        <form action="{{ route('acceptSABid') }}" method="post" class="m-0"
              onsubmit="return confirm('Accept this bid? This will reject all other bids.');">
            @csrf
            <input type="hidden" name="auction_id" value="{{ data_get($auction, 'id') }}">
            <input type="hidden" name="bid_id" value="{{ data_get($bid, 'id') }}">
            <button type="submit" class="btn btn-success"
                    style="min-width: 120px; height: 40px; display: inline-flex; align-items: center; justify-content: center;">
                <i class="fa fa-check me-1"></i> Accept Bid
            </button>
        </form>
        <a href="{{ route('seller.counter-terms', ['id' => data_get($bid, 'id')]) }}" class="btn btn-primary"
           style="min-width: 120px; height: 40px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none;">
            <i class="fa fa-exchange-alt me-1"></i> Counter Bid
        </a>
        <form action="{{ route('rejectSABid') }}" method="post" class="m-0"
              onsubmit="return confirm('Reject this bid?');">
            @csrf
            <input type="hidden" name="auction_id" value="{{ data_get($auction, 'id') }}">
            <input type="hidden" name="bid_id" value="{{ data_get($bid, 'id') }}">
            <button type="submit" class="btn btn-danger"
                    style="min-width: 120px; height: 40px; display: inline-flex; align-items: center; justify-content: center;">
                <i class="fa fa-times me-1"></i> Reject Bid
            </button>
        </form>
        @endif
        {{-- Countered: View / Edit counter terms --}}
        @if ($state === 'countered')
        <a href="{{ route('hire.seller.agent.auction.bid.view-counter', data_get($bid, 'id')) }}"
           class="btn" style="background-color:#fff;border:2px solid #049399;color:#049399;padding:5px 14px;font-weight:600;font-size:0.85rem;">
            <i class="fa fa-eye me-1"></i> View Counter Terms
        </a>
        @if ($_scViewerSentLatest)
        <a href="{{ route('seller.counter-terms', ['id' => data_get($bid, 'id')]) }}"
           class="btn" style="background-color:#049399;border:2px solid #049399;color:#fff;padding:5px 14px;font-weight:600;font-size:0.85rem;">
            <i class="fa fa-edit me-1"></i> Edit Counter Terms
        </a>
        @endif
        @endif
    </x-slot>

</x-bid-detail-layout>
@endsection
