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
  $isTraditionalListing = ($listingType === 'traditional listing');
  $isBiddingPeriodListing = ($listingType === 'bidding period');
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

<div class="container py-4">
    <div class="mb-3">
        <a href="{{ route('landlord.agent.auction.view', $auction->id) }}" class="back-link">
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
                        <span class="me-3"><i class="fas fa-home me-1"></i>Hire a Landlord's Agent</span>
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
            @include('partials.bid_detail_body.landlord')
        </div>

        {{-- ===== ACTIONS (mirrors modal-footer) ===== --}}
        <div style="background: #fafafa; border-top: 1px solid #e0e0e0; padding: 20px; display: flex; flex-wrap: wrap; gap: 12px;">

            <div class="w-100 p-3 text-center" style="background: #e8f4f5; border-radius: 6px; color: #049399;">
                <i class="fa fa-shield-alt me-2"></i>
                <strong>Confidential:</strong> This information is private and only visible to you.
            </div>

            @include('hire_landlord_agent.partials.bid_action_row', [
                'bid'                  => $bid,
                'auction'              => $auction,
                'isOwner'              => $mfIsOwnerL,
                'state'                => $mfStateL,
                'isSold'               => $isSold,
                'isExpired'            => $isExpired,
                'isTraditionalListing' => $isTraditionalListing,
                'latestOwnerCounter'   => $latestOwnerCounter,
                'ownerFirst'           => $mfOwnerFirstL,
                'ownerLast'            => $mfOwnerLastL,
                'agentFirst'           => $mfAgentFirstL,
                'agentLast'            => $mfAgentLastL,
            ])

            <div class="w-100 d-flex justify-content-end mt-2">
                <a href="{{ route('landlord.agent.auction.view', $auction->id) }}"
                   class="btn btn-secondary"
                   style="background: #6c757d; border: none; border-radius: 6px; padding: 8px 20px;">
                    Back to Listing
                </a>
            </div>
        </div>

    </div>
</div>
@endsection
