{{--
    QA LOCK — BID COMPARISON SCORING
    DO NOT reintroduce inline scoring logic.
    All scoring must come from LandlordBidMatchScoreHelper.
--}}
@extends('layouts.main')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/listingDescription.css') }}" />
@include('partials.bid_detail_styles')
@endpush

@section('content')
@php
  // ── Helper closures (mirrors hire_landlord_agent/view.blade.php top @php block) ──
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
  $rentalPeriodSuffix = 'of Rent Due Each Rental Period';
  $joinParts = function($parts) {
    $parts = array_values(array_filter($parts, fn($p) => $p !== null && $p !== ''));
    return count($parts) ? implode(' + ', $parts) : null;
  };
  $basisText = function($basis) {
    return $basis ? ('of ' . $basis) : null;
  };
  $canon = function($str) {
    if (!is_string($str)) return $str;
    return str_replace(["\xe2\x80\x99", "\xe2\x80\x98", "\xe2\x80\x9c", "\xe2\x80\x9d"], ["'", "'", '"', '"'], $str);
  };

  // ── Auth & ownership ──
  $auth_id        = Auth::id();
  $isListingOwner = ($auth_id == data_get($auction, 'user_id'));
  $isBidOwner     = (data_get($bid, 'user_id') == $auth_id);
  $bidAccepted    = data_get($bid, 'accepted');

  // ── Listing state ──
  $listingType          = strtolower(trim(data_get($auction, 'get.listing_type', '')));
  $auctionType          = strtolower(trim(data_get($auction, 'get.auction_type', '')));
  $isTraditionalListing = ($auctionType === 'traditional');
  $isBiddingPeriodListing = in_array($auctionType, ['bidding period', 'auction (timer)']);
  $isSold = in_array(data_get($auction, 'is_sold'), [true,'true',1,'1'], true);
  $carbon = \Carbon\Carbon::class;
  $expiration = data_get($auction, 'get.ends_at') ?? null;
  $isExpired  = $expiration ? $carbon::now()->gte($carbon::parse($expiration)) : false;

  // ── Property type (residential / commercial) ──
  $propertyType  = strtolower(trim($auction->get->property_type ?? ''));
  $isResidential = str_contains($propertyType, 'residential')
                || str_contains($propertyType, 'single-family')
                || str_contains($propertyType, 'single family')
                || str_contains($propertyType, 'condo')
                || str_contains($propertyType, 'townhouse')
                || str_contains($propertyType, 'apartment');
  $isCommercial  = str_contains($propertyType, 'commercial')
                || str_contains($propertyType, 'industrial')
                || str_contains($propertyType, 'office')
                || str_contains($propertyType, 'retail')
                || str_contains($propertyType, 'warehouse');
  if (!$isResidential && !$isCommercial && !empty($propertyType)) {
      $isResidential = true;
  }

  // ── Score helpers ──
  $getScoreColor       = fn($s) => \App\Helpers\LandlordBidMatchScoreHelper::scoreColor((int)$s);
  $auctionPropType     = $auction->get->property_type ?? '';
  $landlordBaselineData = json_decode(json_encode($auction->get ?? []), true) ?: [];
  $currentBidData      = json_decode(json_encode(data_get($bid, 'get', [])), true) ?: [];

  // ── Original score ──
  $originalScore = \App\Helpers\LandlordBidMatchScoreHelper::calculate(
      $landlordBaselineData, $currentBidData, null, $auctionPropType
  );

  // ── Counter terms ──
  $counterBids = \App\Models\LandlordCounterTerm::with(['meta','user'])
      ->where('landlord_agent_auction_id', data_get($bid, 'id'))
      ->orderBy('created_at', 'desc')
      ->get();

  $latestOwnerCounter = \App\Models\LandlordCounterTerm::where('landlord_agent_auction_id', data_get($bid, 'id'))
      ->orderBy('created_at', 'desc')
      ->first();

  $latestActiveCounter = $counterBids->filter(fn($c) => !in_array((string)$c->status, ['accepted','rejected'], true))->first();

  if ($latestActiveCounter && $latestActiveCounter->meta->count()) {
      $counterBaselineData = $latestActiveCounter->meta->pluck('meta_value', 'meta_key')->toArray();
      $latestCounterScore  = \App\Helpers\LandlordBidMatchScoreHelper::calculate(
          $counterBaselineData, $currentBidData, null, $auctionPropType
      );
      $showDualScore = true;
  } else {
      $latestCounterScore = null;
      $showDualScore      = false;
  }

  // ── Unpack match score ──
  $matchScore         = $originalScore;
  $totalScore         = $matchScore['overall_percent'];
  $totalScoreColor    = $getScoreColor($totalScore);
  $servicesScore      = $matchScore['services_match_percent'];
  $servicesMatched    = $matchScore['services_matched_count'];
  $servicesTotal      = $matchScore['services_baseline_total'];
  $servicesMissingCount = $matchScore['services_missing_count'];
  $servicesExtraCount = $matchScore['services_extra_count'];
  $brokerScore        = $matchScore['terms_match_percent'];
  $brokerMatched      = $matchScore['terms_matched_count'];
  $brokerTotal        = $matchScore['terms_baseline_total'];
  $brokerMismatches   = $matchScore['changed_terms'];
  $termsChangedCount  = $matchScore['terms_changed_count'];
  $termsAddedCount    = $matchScore['terms_added_count'];
  $baselineLabel      = "Landlord's Original Listing";
  $hasAnyBaseline     = ($brokerTotal > 0 || $servicesTotal > 0);

  // ── Modal-footer state variables ──
  $_mfRawL    = data_get($bid, 'accepted', '0');
  $_mfTermL   = in_array((string)$_mfRawL, ['accepted', 'rejected'], true);
  $_mfActiveL = isset($latestOwnerCounter) && $latestOwnerCounter !== null;
  $mfStateL   = (!$_mfTermL && $_mfActiveL)
      ? 'countered'
      : ($_mfTermL ? (string)$_mfRawL : '0');
  $mfOwnerIdL    = data_get($auction, 'user_id');
  $mfOwnerFirstL = data_get($auction, 'user.first_name', '');
  $mfOwnerLastL  = data_get($auction, 'user.last_name', '');
  $mfAgentFirstL = data_get($bid, 'user.first_name', '');
  $mfAgentLastL  = data_get($bid, 'user.last_name', '');
  $mfIsOwnerL    = ((int)$auth_id === (int)$mfOwnerIdL);

  // ── Bid status for header badge ──
  $hasCounterBids = $counterBids->isNotEmpty();
  $_isTerminalCard = in_array((string)data_get($bid,'accepted','0'), ['accepted','rejected'], true);
  $_cardState = $_isTerminalCard ? (string)data_get($bid,'accepted') : '0';
  $bidStatusLabel = match($_cardState) {
      'accepted' => 'Accepted',
      'rejected' => 'Rejected',
      default    => $hasCounterBids ? 'Countered' : 'Active',
  };
  $bidStatusColor = match($bidStatusLabel) {
      'Accepted' => '#28a745',
      'Rejected' => '#dc3545',
      'Countered'=> '#ffc107',
      default    => '#1a4a6e',
  };
@endphp
@php
    $_lcCounterFromOwner  = $latestOwnerCounter && ($latestOwnerCounter->user_id == data_get($auction, 'user_id'));
    $_lcViewerSentLatest  = ($mfIsOwnerL && $_lcCounterFromOwner) || (!$mfIsOwnerL && !$_lcCounterFromOwner);
    $__landlordBidSummary = ($mfStateL === 'accepted')
        ? \App\Models\AcceptedBidSummary::where('accepted_bid_id', data_get($bid, 'id'))
            ->where('agent_user_id', data_get($bid, 'user_id'))->first()
        : null;
@endphp

<x-bid-detail-layout
    :backUrl="route('landlord.agent.auction.view', $auction->id)"
    backLabel="Back to Listing"
    roleLabel="Hire a Landlord's Agent"
    :listingId="$auction->listing_id ?? $auction->id"
    :address="$auction->address ?? null"
    :bidStatus="$bidStatusLabel"
    :bidStatusColor="$bidStatusColor">

    {{-- ===== BODY ===== --}}
    @include('partials.bid_detail_body.landlord')

    {{-- ===== FOOTER BANNERS (w-100 status rows) ===== --}}
    <x-slot name="footerBanners">
        {{-- Expired --}}
        @if ($mfStateL === '0' && $mfIsOwnerL && !$isSold && $isTraditionalListing && $isExpired)
        <div class="w-100 p-2 text-center" style="background: #ffc107; border-radius: 6px; color: #856404;">
            <i class="fa-solid fa-clock me-1"></i> Listing has expired — no further actions available. You can extend the expiration date by editing the listing.
        </div>
        @endif

        {{-- Accepted: banner + summary links --}}
        @if ($mfStateL === 'accepted')
        <div class="w-100 p-2 text-center" style="background: #d4edda; border-radius: 6px; color: #155724;">
            <i class="fa-solid fa-circle-check me-1"></i>
            @if ($mfIsOwnerL) This bid has been accepted.
            @else {{ trim($mfOwnerFirstL . ' ' . $mfOwnerLastL) }} accepted this bid.
            @endif
        </div>
        @if ($__landlordBidSummary && ($mfIsOwnerL || data_get($bid, 'user_id') == Auth::id()))
        <div class="w-100 d-flex gap-2 flex-wrap justify-content-center">
            <a href="{{ route('accepted-bid-summary.view', $__landlordBidSummary->id) }}" class="btn btn-outline-primary btn-sm">
                <i class="fa-solid fa-file-lines me-1"></i> View Accepted Bid Summary
            </a>
            @if (data_get($bid, 'user_id') == Auth::id() && !$__landlordBidSummary->isAgentSigned())
            <a href="{{ route('accepted-bid-summary.sign-form', $__landlordBidSummary->id) }}" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-signature me-1"></i> Agent: E-Sign Acknowledgement
            </a>
            @endif
            @if ($mfIsOwnerL && !$__landlordBidSummary->isTenantSigned())
            <a href="{{ route('accepted-bid-summary.sign-form', $__landlordBidSummary->id) }}" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-signature me-1"></i> Landlord: E-Sign Acknowledgement
            </a>
            @endif
            @if ($__landlordBidSummary->isFullySigned())
            <a href="{{ route('accepted-bid-summary.download-pdf', $__landlordBidSummary->id) }}" class="btn btn-success btn-sm">
                <i class="fa-solid fa-download me-1"></i> Download Signed PDF
            </a>
            @endif
        </div>
        @endif

        {{-- Rejected --}}
        @elseif ($mfStateL === 'rejected')
        <div class="w-100 p-2 text-center" style="background: #f8d7da; border-radius: 6px; color: #721c24;">
            <i class="fa-solid fa-circle-xmark me-1"></i>
            @if ($mfIsOwnerL) This bid has been rejected.
            @else {{ trim($mfOwnerFirstL . ' ' . $mfOwnerLastL) }} rejected this bid.
            @endif
        </div>

        {{-- Countered --}}
        @elseif ($mfStateL === 'countered')
        <div class="w-100 p-2 text-center" style="background: #fff3cd; border-radius: 6px; color: #856404;">
            <i class="fa-solid fa-right-left me-1"></i>
            @if ($_lcViewerSentLatest) <strong>Counter Offer Sent.</strong>
            @else <strong>Counter Offer Received.</strong>
            @endif
        </div>

        {{-- Pending --}}
        @elseif ($mfStateL === '0')
        @if (data_get($bid, 'user_id') == Auth::id())
        <div class="w-100 alert alert-secondary mb-0 py-1 small">
            ⏳ Waiting for a response from {{ trim($mfOwnerFirstL . ' ' . $mfOwnerLastL) }}...
        </div>
        @elseif (!$mfIsOwnerL)
        <div class="w-100 alert alert-light mb-0 py-1 small">
            ⏳ Bid from {{ trim($mfAgentFirstL . ' ' . $mfAgentLastL) }} is pending.
        </div>
        @endif
        @endif
    </x-slot>

    {{-- ===== FOOTER ACTIONS (right-side buttons) ===== --}}
    <x-slot name="footerActions">
        {{-- Active + owner: Accept / Counter / Reject --}}
        @if ($mfStateL === '0' && $mfIsOwnerL && !$isSold && !($isTraditionalListing && $isExpired))
        <form action="{{ route('agent.landlord.auction.bid.accept', ['id' => data_get($bid, 'id')]) }}" method="POST" class="m-0"
              onsubmit="return confirm('Accept this bid? This will reject all other bids.');">
            @csrf
            <button type="submit" class="btn btn-success"
                    style="min-width: 120px; height: 40px; display: inline-flex; align-items: center; justify-content: center;">
                <i class="fa-solid fa-check me-1"></i> Accept Bid
            </button>
        </form>
        <a href="{{ route('landlord.counter-terms', ['id' => data_get($bid, 'id')]) }}" class="btn btn-primary"
           style="min-width: 120px; height: 40px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none;">
            <i class="fa-solid fa-right-left me-1"></i> Counter Bid
        </a>
        <form action="{{ route('agent.landlord.auction.bid.reject', ['id' => data_get($bid, 'id')]) }}" method="POST" class="m-0"
              onsubmit="return confirm('Reject this bid?');">
            @csrf
            <button type="submit" class="btn btn-danger"
                    style="min-width: 120px; height: 40px; display: inline-flex; align-items: center; justify-content: center;">
                <i class="fa-solid fa-xmark me-1"></i> Reject Bid
            </button>
        </form>
        @endif
        {{-- Countered: View / Edit counter terms --}}
        @if ($mfStateL === 'countered')
        <a href="{{ route('landlord.hire.agent.auction.bid.view-counter', data_get($bid, 'id')) }}"
           class="btn" style="background-color:#fff;border:2px solid #049399;color:#049399;padding:5px 14px;font-weight:600;font-size:0.85rem;">
            <i class="fa-solid fa-eye me-1"></i> View Counter Terms
        </a>
        @if ($_lcViewerSentLatest && $mfIsOwnerL)
        <a href="{{ route('landlord.edit-counter-terms', ['id' => data_get($bid, 'id')]) }}"
           class="btn" style="background-color:#049399;border:2px solid #049399;color:#fff;padding:5px 14px;font-weight:600;font-size:0.85rem;">
            <i class="fa-solid fa-pen-to-square me-1"></i> Edit Counter Terms
        </a>
        @elseif ($_lcViewerSentLatest && !$mfIsOwnerL)
        <a href="{{ route('landlord.agent.auction.counter-bid', ['id' => data_get($auction, 'id'), 'bid_id' => data_get($bid, 'id')]) }}"
           class="btn" style="background-color:#049399;border:2px solid #049399;color:#fff;padding:5px 14px;font-weight:600;font-size:0.85rem;">
            <i class="fa-solid fa-pen-to-square me-1"></i> Edit Counter Terms
        </a>
        @endif
        @endif
    </x-slot>

</x-bid-detail-layout>
@endsection
