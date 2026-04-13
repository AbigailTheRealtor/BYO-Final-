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

<div class="container py-4">
    <div class="mb-3">
        <a href="{{ route('seller.agent.auction.detail', $auction->id) }}" class="back-link">
            <i class="fas fa-arrow-left me-2"></i>Back to Listing
        </a>
    </div>

    <div class="bid-preview-card">
        {{-- ===== HEADER ===== --}}
        <div class="bid-preview-header">
            <div class="d-flex justify-content-between align-items-start flex-wrap">
                <div>
                    <h4 class="mb-2"><i class="fas fa-user-tie me-2"></i>Agent Bid Detail</h4>
                    <div class="opacity-75">
                        <span class="me-3"><i class="fas fa-home me-1"></i>Hire a Seller's Agent</span>
                        <span><i class="fas fa-tag me-1"></i>Listing #{{ $auction->id }}</span>
                        @if($auction->address)
                        <span class="ms-3"><i class="fas fa-map-marker-alt me-1"></i>{{ $auction->address }}</span>
                        @endif
                    </div>
                </div>
                <div class="text-end mt-2 mt-md-0">
                    <span class="status-badge"
                          style="background-color:{{ $bidStatusColor }};color:{{ $bidStatusLabel === 'Countered' ? '#000' : '#fff' }};padding:6px 14px;border-radius:20px;font-weight:600;font-size:0.9rem;">
                        {{ $bidStatusLabel }}
                    </span>
                </div>
            </div>
        </div>

        {{-- ===== BODY — full modal content via shared partial ===== --}}
        <div class="bid-preview-body" style="padding: 0;">
            @include('partials.bid_detail_body.seller')
        </div>

        {{-- ===== ACTIONS (mirrors seller modal-footer) ===== --}}
        <div style="background: #fafafa; border-top: 1px solid #e0e0e0; padding: 20px; display: flex; flex-wrap: wrap; gap: 12px;">

            <div class="w-100 p-3 text-center" style="background: #e8f4f5; border-radius: 6px; color: #049399;">
                <i class="fa fa-shield-alt me-2"></i>
                <strong>Confidential:</strong> This information is private and only visible to you.
            </div>

            {{-- ── Listing owner: action buttons when bid is undecided ── --}}
            @if ($state === '0' && $isOwnerRow && !$isSold)
                @if ($isTraditionalListing && $isExpired)
                <div class="w-100 p-2 text-center" style="background: #ffc107; border-radius: 6px; color: #856404;">
                    <i class="fa fa-clock me-1"></i> Listing has expired — no further actions available.
                </div>
                @else
                <div class="d-flex gap-3 justify-content-center align-items-center w-100" style="flex-wrap: nowrap;">
                    <form action="{{ route('acceptSABid') }}" method="post" style="margin: 0;"
                          onsubmit="return confirm('Are you sure you want to accept this bid? This will reject all other bids.');">
                        @csrf
                        <input type="hidden" name="auction_id" value="{{ data_get($auction, 'id') }}">
                        <input type="hidden" name="bid_id" value="{{ data_get($bid, 'id') }}">
                        <button type="submit" class="btn btn-success btn-accept"
                                style="padding: 10px 20px; font-size: 0.95rem; min-width: 130px; height: 42px; display: inline-flex; align-items: center; justify-content: center;">
                            <i class="fa fa-check me-1"></i> Accept Bid
                        </button>
                    </form>
                    <a href="{{ route('seller.counter-terms', ['id' => data_get($bid, 'id')]) }}"
                       class="btn btn-primary btn-counter"
                       style="padding: 10px 20px; font-size: 0.95rem; min-width: 130px; height: 42px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none;">
                        <i class="fa fa-exchange-alt me-1"></i> Counter Bid
                    </a>
                    <form action="{{ route('rejectSABid') }}" method="post" style="margin: 0;"
                          onsubmit="return confirm('Are you sure you want to reject this bid?');">
                        @csrf
                        <input type="hidden" name="auction_id" value="{{ data_get($auction, 'id') }}">
                        <input type="hidden" name="bid_id" value="{{ data_get($bid, 'id') }}">
                        <button type="submit" class="btn btn-danger btn-reject"
                                style="padding: 10px 20px; font-size: 0.95rem; min-width: 130px; height: 42px; display: inline-flex; align-items: center; justify-content: center;">
                            <i class="fa fa-times me-1"></i> Reject Bid
                        </button>
                    </form>
                </div>
                @endif
            @endif

            {{-- ── Accepted state ── --}}
            @if ($state === 'accepted')
            <div class="w-100 p-2 text-center" style="background: #d4edda; border-radius: 6px; color: #155724;">
                <i class="fa fa-check-circle me-1"></i>
                @if (Auth::id() == $ownerId) This bid has been accepted.
                @else {{ trim($ownerFirst . ' ' . $ownerLast) }} accepted this bid.
                @endif
            </div>
            @php
                $absFooterBidSummary = \App\Models\AcceptedBidSummary::where('accepted_bid_id', data_get($bid, 'id'))
                    ->where('agent_user_id', data_get($bid, 'user_id'))->first();
            @endphp
            @if ($absFooterBidSummary && (Auth::id() == $ownerId || data_get($bid, 'user_id') == Auth::id()))
            <div class="d-flex gap-2 flex-wrap justify-content-center w-100 mt-2">
                <a href="{{ route('accepted-bid-summary.view', $absFooterBidSummary->id) }}" class="btn btn-outline-primary btn-sm">
                    <i class="fa fa-file-alt me-1"></i> View Accepted Bid Summary
                </a>
                @if (data_get($bid, 'user_id') == Auth::id() && !$absFooterBidSummary->isAgentSigned())
                <a href="{{ route('accepted-bid-summary.sign-form', $absFooterBidSummary->id) }}" class="btn btn-primary btn-sm">
                    <i class="fa fa-signature me-1"></i> E-Sign Acknowledgement
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

            {{-- ── Rejected state ── --}}
            @elseif ($state === 'rejected')
            <div class="w-100 p-2 text-center" style="background: #f8d7da; border-radius: 6px; color: #721c24;">
                <i class="fa fa-times-circle me-1"></i>
                @if (Auth::id() == $ownerId) This bid has been rejected.
                @else {{ trim($ownerFirst . ' ' . $ownerLast) }} rejected this bid.
                @endif
            </div>

            {{-- ── Countered state ── --}}
            @elseif ($state === 'countered')
            <div class="w-100 p-2 text-center" style="background: #fff3cd; border-radius: 6px; color: #856404;">
                <i class="fa fa-exchange-alt me-1"></i>
                @if (($scFooterLatestFromOwner && Auth::id() == $ownerId) || (!$scFooterLatestFromOwner && Auth::id() != $ownerId))
                    <strong>Counter Offer Sent.</strong>
                @else
                    <strong>Counter Offer Received.</strong>
                @endif
            </div>
            <div class="d-flex gap-2 flex-wrap justify-content-center w-100 mt-2">
                @if (($scFooterLatestFromOwner && Auth::id() == $ownerId) || (!$scFooterLatestFromOwner && Auth::id() != $ownerId))
                <a href="{{ route('hire.seller.agent.auction.bid.view-counter', data_get($bid, 'id')) }}"
                   class="btn" style="background-color:#fff;border:2px solid #049399;color:#049399;padding:5px 12px;font-weight:600;font-size:0.85rem;">
                    <i class="fa fa-eye me-1"></i> View Counter Terms
                </a>
                <a href="{{ route('seller.counter-terms', ['id' => data_get($bid, 'id')]) }}"
                   class="btn" style="background-color:#049399;border:2px solid #049399;color:#fff;padding:5px 12px;font-weight:600;font-size:0.85rem;">
                    <i class="fa fa-edit me-1"></i> Edit Counter Terms
                </a>
                @else
                <a href="{{ route('hire.seller.agent.auction.bid.view-counter', data_get($bid, 'id')) }}"
                   class="btn" style="background-color:#fff;border:2px solid #049399;color:#049399;padding:5px 12px;font-weight:600;font-size:0.85rem;">
                    <i class="fa fa-eye me-1"></i> View Counter Terms
                </a>
                @endif
            </div>

            {{-- ── Pending (undecided, non-owner) state ── --}}
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

            <div class="w-100 d-flex justify-content-end mt-2">
                <a href="{{ route('seller.agent.auction.detail', $auction->id) }}"
                   class="btn btn-secondary"
                   style="background: #6c757d; border: none; border-radius: 6px; padding: 8px 20px;">
                    Back to Listing
                </a>
            </div>
        </div>

    </div>
</div>
@endsection
