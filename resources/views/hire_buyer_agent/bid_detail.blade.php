{{--
    QA LOCK — BID COMPARISON SCORING
    DO NOT reintroduce inline scoring logic.
    All scoring must come from BuyerBidMatchScoreHelper.
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
  $isBidOwner     = (data_get($bid, 'user_id') == $auth_id);
  $bidId          = data_get($bid, 'id');
  $bidAccepted    = data_get($bid, 'accepted', '0');

  // ── Listing state ──
  $listingType            = trim($auction->get->auction_type ?? '');
  $isTraditionalListing   = (strtolower($listingType) === 'traditional listing');
  $isBiddingPeriodListing = (strtolower($listingType) === 'bidding period');
  $isSold = in_array(data_get($auction, 'is_sold'), [true,'true',1,'1'], true);
  $carbon = \Carbon\Carbon::class;
  $expiration  = data_get($auction, 'get.ends_at') ?? null;
  $isExpired   = $expiration ? $carbon::now()->gte($carbon::parse($expiration)) : false;
  $isExpiredBid = $isExpired;

  // ── Score setup ──
  $auctionPropType     = data_get($auction, 'get.property_type', '');
  $listingBaselineData = $auction->meta->pluck('meta_value', 'meta_key')->toArray();
  $currentBidData      = (array) $bid->get;
  $baselineData        = $listingBaselineData;

  // ── Counter terms ──
  $latestBuyerCounter = \App\Models\BuyerCounterTerm::with('meta')
      ->where('buyer_agent_auction_id', $auction->id)
      ->where('parent_counter_id', $bidId)
      ->orderBy('created_at', 'desc')
      ->first();

  $latestAgentCounter = \App\Models\BuyerCounterBidding::with('meta')
      ->where('buyer_agent_auction_bid_id', $bidId)
      ->orderBy('created_at', 'desc')
      ->first();

  $hasCounterBids = $latestBuyerCounter || $latestAgentCounter;

  // ── Counter direction ──
  $_buyerCardLatestFromOwner = false;
  if ($latestBuyerCounter && $latestAgentCounter) {
      $_buyerCardLatestFromOwner = $latestBuyerCounter->created_at >= $latestAgentCounter->created_at;
  } elseif ($latestBuyerCounter) {
      $_buyerCardLatestFromOwner = true;
  }

  // ── Match score ──
  $score        = \App\Helpers\BuyerBidMatchScoreHelper::calculate($baselineData, $currentBidData, null, $auctionPropType);
  $overallScore = $score['overall_percent'];
  $scoreColor   = \App\Helpers\BuyerBidMatchScoreHelper::scoreColor((int)$overallScore);
  $brokerMismatches  = $score['changed_terms'] ?? [];
  $brokerAdded       = $score['added_terms'] ?? [];
  $buyerBaselineLabel = $isListingOwner ? 'Your Original Terms' : "Buyer's Original Request";
  $servicesExtraCount = $score['services_extra_count'] ?? 0;
  $matchedServices    = $score['matched_services'] ?? [];
  $missingServices    = $score['missing_services'] ?? [];
  $extraServices      = $score['extra_services'] ?? [];
  $mismatchStyle = 'background-color: #ffe6e6; padding: 2px 6px; border-radius: 4px; border-left: 3px solid #dc3545;';
  $mismatchBadge = '<span class="badge bg-danger ms-2" style="font-size: 0.7rem; vertical-align: middle;">Mismatch</span>';

  // ── Card compact variables ──
  $cardServicesMatched    = $score['services_matched_count'] ?? 0;
  $cardServicesTotal      = $score['services_baseline_total'] ?? 0;
  $cardServicesExtraCount = $score['services_extra_count'] ?? 0;

  $cardShowDualScore      = false;
  $cardOriginalScore      = null;
  $cardLatestCounterScore = null;
  $cardCounterLabel       = 'vs. Latest Counter Terms';

  if ($latestBuyerCounter) {
      $cardOriginalScore      = $score;
      $cardLatestCounterScore = \App\Helpers\BuyerBidMatchScoreHelper::calculate(
          $latestBuyerCounter->getAllMeta(), $currentBidData, null, $auctionPropType
      );
      $cardCounterLabel  = $isListingOwner ? 'vs. Your Counter Terms' : "vs. Buyer's Counter Terms";
      $cardShowDualScore = true;
  } elseif ($latestAgentCounter) {
      $cardOriginalScore      = $score;
      $agentCounterMeta       = $latestAgentCounter->getAllMeta();
      $cardLatestCounterScore = \App\Helpers\BuyerBidMatchScoreHelper::calculate(
          $agentCounterMeta, $currentBidData, null, $auctionPropType
      );
      $cardCounterLabel  = $isListingOwner ? 'vs. Your Counter Offer' : "vs. Owner's Counter Offer";
      $cardShowDualScore = true;
  }

  $cardGetScoreColor        = fn($pct) => \App\Helpers\BuyerBidMatchScoreHelper::scoreColor((int)$pct);
  $cardIsAgentViewer        = $auth_id && auth()->user() && in_array(auth()->user()->user_type ?? '', ['agent']);
  $userHasBid               = true;
  $cardShowMatchScoreOnCard  = $isListingOwner || $isBidOwner;
  $cardHasAnyBaseline        = (($score['broker_comp_total'] ?? 0) > 0 || $cardServicesTotal > 0);

  // ── Modal-footer state variables ──
  $_mfRawB    = data_get($bid, 'accepted', '0');
  $_mfTermB   = in_array((string)$_mfRawB, ['accepted', 'rejected'], true);
  $_mfCounterB = !$_mfTermB && $hasCounterBids;
  $mfStateB   = $_mfCounterB
      ? 'countered'
      : (in_array($_mfRawB, [null, 0, '0', '', 'no'], true) ? '0' : (string)$_mfRawB);
  $mfOwnerIdB    = data_get($auction, 'user_id');
  $mfOwnerFirstB = data_get($auction, 'user.first_name', '');
  $mfOwnerLastB  = data_get($auction, 'user.last_name', '');
  $mfAgentFirstB = data_get($bid, 'user.first_name', '');
  $mfAgentLastB  = data_get($bid, 'user.last_name', '');
  $mfIsOwnerB    = ((int)$auth_id === (int)$mfOwnerIdB);

  // ── Bid status for header badge ──
  $bidStatusDisplay = match($bidAccepted) {
      'accepted' => 'Accepted',
      'rejected' => 'Rejected',
      'countered' => 'Countered',
      default    => $hasCounterBids ? 'Countered' : 'Active',
  };
  $bidStatusColor = match($bidStatusDisplay) {
      'Accepted'  => '#28a745',
      'Rejected'  => '#dc3545',
      'Countered' => '#ffc107',
      default     => '#1a4a6e',
  };
@endphp

<div class="container py-4">
    <div class="mb-3">
        <a href="{{ route('buyer.view-auction', $auction->id) }}" class="back-link">
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
                        <span class="me-3"><i class="fas fa-home me-1"></i>Hire a Buyer's Agent</span>
                        <span><i class="fas fa-tag me-1"></i>Listing #{{ $auction->id }}</span>
                        @if($auction->address)
                        <span class="ms-3"><i class="fas fa-map-marker-alt me-1"></i>{{ $auction->address }}</span>
                        @endif
                    </div>
                </div>
                <div class="text-end mt-2 mt-md-0">
                    <span class="status-badge"
                          style="background-color:{{ $bidStatusColor }};color:{{ $bidStatusDisplay === 'Countered' ? '#000' : '#fff' }};padding:6px 14px;border-radius:20px;font-weight:600;font-size:0.9rem;">
                        {{ $bidStatusDisplay }}
                    </span>
                </div>
            </div>
        </div>

        {{-- ===== BODY — full modal content via shared partial ===== --}}
        <div class="bid-preview-body" style="padding: 0;">
            @include('partials.bid_detail_body.buyer')
        </div>

        {{-- ===== ACTIONS (mirrors buyer modal-footer) ===== --}}
        <div style="background: #fafafa; border-top: 1px solid #e0e0e0; padding: 20px; display: flex; flex-wrap: wrap; gap: 12px;">

            <div class="w-100 p-3 text-center" style="background: #e8f4f5; border-radius: 6px; color: #049399;">
                <i class="fa fa-shield-alt me-2"></i>
                <strong>Confidential:</strong> This information is private and only visible to you.
            </div>

            {{-- ── Listing owner: action buttons when bid is undecided ── --}}
            @if ($mfStateB === '0' && $mfIsOwnerB && !$isSold)
                @if ($isTraditionalListing && $isExpired)
                <div class="w-100 p-2 text-center" style="background: #ffc107; border-radius: 6px; color: #856404;">
                    <i class="fa fa-clock me-1"></i> Listing has expired — no further actions available.
                </div>
                @else
                <div class="d-flex gap-3 justify-content-center align-items-center w-100" style="flex-wrap: nowrap;">
                    <form action="{{ route('buyer.hire.agent.auction.bid.accept') }}" method="POST" style="margin: 0;"
                          onsubmit="return confirm('Are you sure you want to accept this bid? This will reject all other bids.');">
                        @csrf
                        <input type="hidden" name="bid_id" value="{{ data_get($bid, 'id') }}">
                        <input type="hidden" name="auction_id" value="{{ $auction->id }}">
                        <button type="submit" class="btn btn-success"
                                style="padding: 10px 20px; font-size: 0.95rem; background-color: #28a745 !important; border-color: #28a745 !important; color: #fff !important; min-width: 130px; height: 42px; display: inline-flex; align-items: center; justify-content: center;">
                            <i class="fa fa-check me-1"></i> Accept Bid
                        </button>
                    </form>
                    <a href="{{ route('buyer.counter-terms', data_get($bid, 'id')) }}"
                       class="btn btn-primary"
                       style="padding: 10px 20px; font-size: 0.95rem; background-color: #0d6efd !important; border-color: #0d6efd !important; color: #fff !important; min-width: 130px; height: 42px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none;">
                        <i class="fa fa-exchange-alt me-1"></i> Counter Bid
                    </a>
                    <form action="{{ route('buyer.hire.agent.auction.bid.reject') }}" method="POST" style="margin: 0;"
                          onsubmit="return confirm('Are you sure you want to reject this bid?');">
                        @csrf
                        <input type="hidden" name="bid_id" value="{{ data_get($bid, 'id') }}">
                        <input type="hidden" name="auction_id" value="{{ $auction->id }}">
                        <button type="submit" class="btn btn-danger"
                                style="padding: 10px 20px; font-size: 0.95rem; background-color: #dc3545 !important; border-color: #dc3545 !important; color: #fff !important; min-width: 130px; height: 42px; display: inline-flex; align-items: center; justify-content: center;">
                            <i class="fa fa-times me-1"></i> Reject Bid
                        </button>
                    </form>
                </div>
                @endif
            @endif

            {{-- ── Accepted state ── --}}
            @if ($mfStateB === 'accepted')
            <div class="w-100 p-2 text-center" style="background: #d4edda; border-radius: 6px; color: #155724;">
                <i class="fa fa-check-circle me-1"></i>
                @if ($mfIsOwnerB) This bid has been accepted.
                @else {{ trim($mfOwnerFirstB . ' ' . $mfOwnerLastB) }} accepted this bid.
                @endif
            </div>
            @php $mfBidSummaryB = \App\Models\AcceptedBidSummary::where('accepted_bid_id', data_get($bid, 'id'))->where('agent_user_id', data_get($bid, 'user_id'))->first(); @endphp
            @if ($mfBidSummaryB && ($mfIsOwnerB || data_get($bid, 'user_id') == Auth::id()))
            <div class="d-flex gap-2 flex-wrap justify-content-center w-100 mt-2">
                <a href="{{ route('accepted-bid-summary.view', $mfBidSummaryB->id) }}" class="btn btn-outline-primary btn-sm">
                    <i class="fa fa-file-alt me-1"></i> View Accepted Bid Summary
                </a>
                @if (data_get($bid, 'user_id') == Auth::id() && !$mfBidSummaryB->isAgentSigned())
                <a href="{{ route('accepted-bid-summary.sign-form', $mfBidSummaryB->id) }}" class="btn btn-primary btn-sm">
                    <i class="fa fa-signature me-1"></i> Agent: E-Sign Acknowledgement
                </a>
                @endif
                @if ($mfIsOwnerB && !$mfBidSummaryB->isTenantSigned())
                <a href="{{ route('accepted-bid-summary.sign-form', $mfBidSummaryB->id) }}" class="btn btn-primary btn-sm">
                    <i class="fa fa-signature me-1"></i> Buyer: E-Sign Acknowledgement
                </a>
                @endif
                @if ($mfBidSummaryB->isFullySigned())
                <a href="{{ route('accepted-bid-summary.download-pdf', $mfBidSummaryB->id) }}" class="btn btn-success btn-sm">
                    <i class="fa fa-download me-1"></i> Download Signed PDF
                </a>
                @endif
            </div>
            @endif

            {{-- ── Rejected state ── --}}
            @elseif ($mfStateB === 'rejected')
            <div class="w-100 p-2 text-center" style="background: #f8d7da; border-radius: 6px; color: #721c24;">
                <i class="fa fa-times-circle me-1"></i>
                @if ($mfIsOwnerB) This bid has been rejected.
                @else {{ trim($mfOwnerFirstB . ' ' . $mfOwnerLastB) }} rejected this bid.
                @endif
            </div>

            {{-- ── Countered state ── --}}
            @elseif ($mfStateB === 'countered')
            @php
                $_mfBuyerViewerSentLatest = ($isListingOwner && $_buyerCardLatestFromOwner)
                                         || ($isBidOwner   && !$_buyerCardLatestFromOwner);
            @endphp
            <div class="w-100 p-2 text-center" style="background: #fff3cd; border-radius: 6px; color: #856404;">
                <i class="fa fa-exchange-alt me-1"></i>
                @if ($_mfBuyerViewerSentLatest)
                    <strong>Counter Offer Sent.</strong>
                @else
                    <strong>Counter Offer Received.</strong>
                @endif
            </div>
            <div class="d-flex gap-2 flex-wrap justify-content-center w-100 mt-2">
                @if ($_mfBuyerViewerSentLatest)
                <a href="{{ route('buyer.hire.agent.auction.bid.view-counter', data_get($bid, 'id')) }}"
                   class="btn" style="background-color:#fff;border:2px solid #049399;color:#049399;padding:5px 12px;font-weight:600;font-size:0.85rem;">
                    <i class="fa fa-eye me-1"></i> View Counter Terms
                </a>
                <a href="{{ route('buyer.edit-counter-terms', ['id' => data_get($bid, 'id')]) }}"
                   class="btn" style="background-color:#049399;border:2px solid #049399;color:#fff;padding:5px 12px;font-weight:600;font-size:0.85rem;">
                    <i class="fa fa-edit me-1"></i> Edit Counter Terms
                </a>
                @else
                <a href="{{ route('buyer.hire.agent.auction.bid.view-counter', data_get($bid, 'id')) }}"
                   class="btn" style="background-color:#fff;border:2px solid #049399;color:#049399;padding:5px 12px;font-weight:600;font-size:0.85rem;">
                    <i class="fa fa-eye me-1"></i> View Counter Terms
                </a>
                @endif
            </div>

            {{-- ── Pending state ── --}}
            @elseif ($mfStateB === '0')
            @if (data_get($bid, 'user_id') == Auth::id())
            <div class="alert alert-secondary mt-2 w-100 mb-0 py-1 small">
                ⏳ Waiting for a response from {{ trim($mfOwnerFirstB . ' ' . $mfOwnerLastB) }}...
            </div>
            @else
            <div class="alert alert-light mt-2 w-100 mb-0 py-1 small">
                ⏳ Bid from {{ trim($mfAgentFirstB . ' ' . $mfAgentLastB) }} is pending.
            </div>
            @endif
            @endif

            <div class="w-100 d-flex justify-content-end mt-2">
                <a href="{{ route('buyer.view-auction', $auction->id) }}"
                   class="btn btn-secondary"
                   style="background: #6c757d; border: none; border-radius: 6px; padding: 8px 20px;">
                    Back to Listing
                </a>
            </div>
        </div>

    </div>
</div>
@endsection
