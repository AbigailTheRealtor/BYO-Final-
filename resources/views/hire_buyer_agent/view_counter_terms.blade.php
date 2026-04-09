@extends('layouts.main')

@section('content')
<div class="container py-5">
    <div class="row">
        <div class="col-12">
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                    @if($viewerRole === 'agent')
                    <li class="breadcrumb-item"><a href="{{ route('myBids', 'buyer-agent') }}">My Bids</a></li>
                    @else
                    <li class="breadcrumb-item"><a href="{{ route('buyer.view-auction', $auction->id) }}">Listing</a></li>
                    @endif
                    <li class="breadcrumb-item active">Counter Terms</li>
                </ol>
            </nav>

            <div class="card mb-4" style="border: 2px solid #049399; border-radius: 8px;">
                <div class="card-header" style="background: linear-gradient(135deg, #049399 0%, #037a7f 100%); color: white;">
                    <h4 class="mb-0">
                        <i class="fas fa-exchange-alt me-2"></i>
                        Agent's Counter Terms
                    </h4>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6 style="color: #049399; font-weight: 600;">Listing Information</h6>
                            <p><strong>Title:</strong> {{ $auction->title ?? 'Hire a Buyer\'s Agent' }}</p>
                            <p><strong>Address:</strong> {{ $auction->get->address ?? 'N/A' }}</p>
                            <p><strong>Listing Owner:</strong> {{ $auction->user->name ?? 'N/A' }}</p>
                        </div>
                        <div class="col-md-6">
                            <h6 style="color: #049399; font-weight: 600;">Bid Information</h6>
                            @php
                                $bidStatusRaw = $bid->accepted ?? '0';
                                $bidStatusLabel = match($bidStatusRaw) {
                                    'accepted' => 'Accepted',
                                    'rejected' => 'Rejected',
                                    default => 'Active',
                                };
                                $statusColors = [
                                    'Active'    => 'background-color: #007bff; color: #fff;',
                                    'Accepted'  => 'background-color: #28a745; color: #fff;',
                                    'Rejected'  => 'background-color: #dc3545; color: #fff;',
                                ];
                            @endphp
                            <p><strong>Status:</strong>
                                <span class="badge" style="{{ $statusColors[$bidStatusLabel] ?? $statusColors['Active'] }} padding: 6px 12px; border-radius: 4px;">
                                    {{ $bidStatusLabel }}
                                </span>
                            </p>
                            <p><strong>Agent:</strong> {{ $bid->user->name ?? 'N/A' }}</p>
                            <p><strong>Submitted:</strong> {{ $bid->created_at->format('M d, Y h:i A') }}</p>
                        </div>
                    </div>

                    @php
                        $awaitingCounterResponse = false;
                        if ($viewerRole === 'agent') {
                            $activeCounter    = $buyerCounter;
                            $counterPartyName = 'Buyer';
                        } else {
                            if ($agentCounter) {
                                $activeCounter    = $agentCounter;
                                $counterPartyName = 'Agent';
                            } elseif ($buyerCounter) {
                                // Buyer submitted a counter but agent hasn't responded yet — show buyer's own counter
                                $activeCounter    = $buyerCounter;
                                $counterPartyName = 'Your Submitted';
                                $awaitingCounterResponse = true;
                            } else {
                                $activeCounter    = null;
                                $counterPartyName = '';
                            }
                        }
                    @endphp

                    @if($activeCounter)
                    @php
                        $counterData = $activeCounter->getAllMeta();

                        $fmtMoney = function($v) {
                            if ($v === null || $v === '') return null;
                            $raw = preg_replace('/[^0-9.]/', '', (string)$v);
                            if ($raw === '' || !is_numeric($raw)) return null;
                            return '$' . number_format((float)$raw, 2);
                        };
                        $fmtPercent = function($v) {
                            if ($v === null || $v === '') return null;
                            $raw = preg_replace('/[^0-9.]/', '', (string)$v);
                            if ($raw === '' || !is_numeric($raw)) return null;
                            return rtrim(rtrim(number_format((float)$raw, 2), '0'), '.') . '%';
                        };
                        $joinParts = function(array $parts) {
                            return implode(' + ', array_filter($parts)) ?: null;
                        };

                        if ($viewerRole === 'agent') {
                            $baselineData  = (array) $bid->get;
                            $baselineLabel = 'Your Original Bid';
                        } else {
                            $baselineData = (array) $auction->get;
                            $baselineLabel = 'Your Original Listing Terms';
                        }

                        $counterPropType = $auction->get->property_type ?? 'Residential Property';
                        $score = \App\Helpers\BuyerBidMatchScoreHelper::calculate($baselineData, $counterData, null, $counterPropType);

                        $showDualScore      = false;
                        $latestCounterScore = null;
                        if ($viewerRole === 'buyer' && $buyerCounter && !$awaitingCounterResponse) {
                            $latestCounterScore = \App\Helpers\BuyerBidMatchScoreHelper::calculate(
                                $buyerCounter->getAllMeta(), $counterData, null, $counterPropType
                            );
                            $showDualScore = true;
                        }

                        $brokerScore       = $score['broker_comp_percent'] ?? $score['terms_match_percent'] ?? 100;
                        $brokerMatched     = $score['broker_comp_matched'] ?? $score['terms_matched_count'] ?? 0;
                        $brokerTotal       = $score['broker_comp_total'] ?? $score['terms_baseline_total'] ?? 0;
                        $brokerMismatches  = $score['changed_terms'] ?? [];
                        $termsChangedCount = $score['terms_changed_count'] ?? 0;
                        $termsAddedCount   = $score['terms_added_count'] ?? 0;

                        $servicesScore        = $score['services_percent'] ?? $score['services_match_percent'] ?? 100;
                        $servicesMatched      = $score['services_matched'] ?? $score['services_matched_count'] ?? 0;
                        $servicesTotal        = $score['services_total'] ?? $score['services_baseline_total'] ?? 0;
                        $servicesMissingCount = $score['services_missing_count'] ?? 0;
                        $servicesExtraCount   = $score['services_extra_count'] ?? 0;
                        $servicesMissing      = $score['missing_services'] ?? [];
                        $servicesAdded        = $score['extra_services'] ?? [];

                        $totalScore      = $score['overall_percent'];
                        $getScoreColor   = fn($s) => \App\Helpers\BuyerBidMatchScoreHelper::scoreColor((int)$s);
                        $totalScoreColor = $getScoreColor($totalScore);
                        /**
                         * ZERO-BASELINE / NO-DATA GUARD
                         *
                         * If there is no comparable baseline match data, do not display 100%.
                         * Render "No match data available" instead.
                         *
                         * This behavior is locked by QA baseline documentation.
                         * Reference: qa_reports/QA_LOCK_BidComparison_v1.md
                         */
                        $hasAnyBaseline  = ($brokerTotal > 0 || $servicesTotal > 0);

                        $normalizeService = fn($s) => \App\Helpers\BuyerBidMatchScoreHelper::normalizeService((string)$s);
                        $baselineNorm     = array_merge($score['matched_services'] ?? [], $score['missing_services'] ?? []);
                        $displayCatalog   = \App\Helpers\BuyerBidMatchScoreHelper::getCatalog($counterPropType);

                        $csRaw = $counterData['services'] ?? [];
                        if (is_string($csRaw)) $csRaw = json_decode($csRaw, true) ?? [];
                        $csRaw = is_array($csRaw) ? array_values(array_filter($csRaw)) : [];
                        $csRaw = array_values(array_filter(
                            $csRaw,
                            fn($s) => in_array($normalizeService((string)$s), $displayCatalog, true)
                        ));
                        $counterOtherRaw = $counterData['other_services'] ?? [];
                        if (is_string($counterOtherRaw)) $counterOtherRaw = json_decode($counterOtherRaw, true) ?? [];
                        $counterOtherRaw      = is_array($counterOtherRaw) ? array_values(array_filter($counterOtherRaw, fn($s) => is_string($s) && !empty(trim($s)))) : [];
                        $allCounterServices   = array_merge($csRaw, $counterOtherRaw);
                        $counterOtherServices = $counterOtherRaw;

                        $bsRaw = $baselineData['services'] ?? [];
                        if (is_string($bsRaw)) $bsRaw = json_decode($bsRaw, true) ?? [];
                        $bsRaw = is_array($bsRaw) ? array_values(array_filter($bsRaw)) : [];
                        $bsRaw = array_values(array_filter(
                            $bsRaw,
                            fn($s) => in_array($normalizeService((string)$s), $displayCatalog, true)
                        ));
                        $bsOtherRaw = $baselineData['other_services'] ?? [];
                        if (is_string($bsOtherRaw)) $bsOtherRaw = json_decode($bsOtherRaw, true) ?? [];
                        $bsOtherRaw          = is_array($bsOtherRaw) ? array_values(array_filter($bsOtherRaw, fn($s) => is_string($s) && !empty(trim($s)))) : [];
                        $allBaselineServices  = array_merge($bsRaw, $bsOtherRaw);

                        $mismatchStyle = 'background-color: #ffe6e6; padding: 2px 6px; border-radius: 4px; border-left: 3px solid #dc3545;';
                        $mismatchBadge = '<span class="badge" style="background-color: #dc3545; color: white; font-size: 0.7rem; vertical-align: middle; margin-left: 8px;">Changed</span>';
                        $addedStyle    = 'background-color: #e6ffe6; padding: 2px 6px; border-radius: 4px; border-left: 3px solid #28a745;';
                        $addedBadge    = '<span class="badge" style="background-color: #28a745; color: white; font-size: 0.7rem; vertical-align: middle; margin-left: 8px;">Added</span>';
                        $missingStyle  = 'background-color: #fff3cd; padding: 2px 6px; border-radius: 4px; border-left: 3px solid #ffc107;';
                        $missingBadge  = '<span class="badge" style="background-color: #ffc107; color: #000; font-size: 0.7rem; vertical-align: middle; margin-left: 8px;">Removed</span>';

                        $flowKey = \App\Support\ServicesFormatter::keyForBuyerAgent($counterPropType);
                        $orderedCounterServices = !empty($allCounterServices)
                            ? \App\Support\ServicesFormatter::orderSelectedServices($allCounterServices, $flowKey)
                            : [];
                    @endphp

                    <div class="border rounded p-4 mb-4" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 style="color: #049399; font-weight: 600; margin: 0;">
                                <i class="fas fa-file-contract me-2"></i>
                                @if($awaitingCounterResponse)
                                Your Submitted Counter Offer
                                @else
                                Agent's Counter Terms
                                @endif
                            </h5>
                            <span class="text-muted small">Last updated: {{ $activeCounter->updated_at->format('M d, Y h:i A') }}</span>
                        </div>

                        @if($awaitingCounterResponse)
                        <div class="alert alert-warning mb-3 py-2" style="border-radius: 8px; border-left: 4px solid #ffc107; background: #fff9e6;">
                            <i class="fas fa-clock me-2"></i><strong>Your counter offer has been submitted.</strong> Awaiting the agent's response.
                        </div>
                        @endif

                        @if ($hasAnyBaseline)
                        <div class="match-score-panel mb-4 p-3" style="background: white; border-radius: 10px; border: 1px solid #dee2e6;">
                            @if ($showDualScore && $latestCounterScore)
                            <div class="mb-3">
                                <span style="font-weight: 600; color: #1a3a5c; font-size: 1.1rem;">
                                    <i class="fas fa-chart-pie me-2"></i>Match Summary
                                </span>
                            </div>
                            <p class="small text-muted mb-3">
                                <i class="fas fa-info-circle me-1"></i>
                                <strong>Original Match</strong> compares this response to your original listing request.<br>
                                <strong>Latest Counter Match</strong> compares this response to your most recent counteroffer.<br>
                                Added services or terms are shown for transparency but do not increase either score.
                            </p>
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <div class="p-3 rounded" style="background: #f8f9fa; border: 1px solid #dee2e6; border-top: 3px solid #6c757d;">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="small fw-semibold" style="color: #6c757d;">Original Match</span>
                                            <span class="badge" style="background: {{ $totalScoreColor }}; font-size: 0.95rem; padding: 4px 10px; color: white;">{{ $totalScore }}%</span>
                                        </div>
                                        <div class="small text-muted">vs. {{ $baselineLabel }}</div>
                                        <div class="row g-1 mt-2">
                                            <div class="col-6">
                                                <div class="small fw-semibold" style="color: {{ $getScoreColor($servicesScore) }};">Services {{ $servicesScore }}%</div>
                                                <div class="small text-muted">{{ $servicesTotal > 0 ? $servicesMatched.'/'.$servicesTotal : 'No services requested' }}</div>
                                            </div>
                                            <div class="col-6">
                                                <div class="small fw-semibold" style="color: {{ $getScoreColor($brokerScore) }};">Terms {{ $brokerScore }}%</div>
                                                <div class="small text-muted">{{ $brokerTotal > 0 ? $brokerMatched.'/'.$brokerTotal : 'No terms provided' }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @php
                                    $lcSvcScore = $latestCounterScore['services_percent'] ?? $latestCounterScore['services_match_percent'] ?? 100;
                                    $lcTrmScore = $latestCounterScore['broker_comp_percent'] ?? $latestCounterScore['terms_match_percent'] ?? 100;
                                    $lcOverall  = $latestCounterScore['overall_percent'];
                                    $lcSvcMatch = $latestCounterScore['services_matched'] ?? $latestCounterScore['services_matched_count'] ?? 0;
                                    $lcSvcTotal = $latestCounterScore['services_total'] ?? $latestCounterScore['services_baseline_total'] ?? 0;
                                    $lcTrmMatch = $latestCounterScore['broker_comp_matched'] ?? $latestCounterScore['terms_matched_count'] ?? 0;
                                    $lcTrmTotal = $latestCounterScore['broker_comp_total'] ?? $latestCounterScore['terms_baseline_total'] ?? 0;
                                    $lcSvcExtra = $latestCounterScore['services_extra_count'] ?? 0;
                                    $lcSvcMiss  = $latestCounterScore['services_missing_count'] ?? 0;
                                    $lcTrmChg   = $latestCounterScore['terms_changed_count'] ?? 0;
                                    $lcTrmAdded = $latestCounterScore['terms_added_count'] ?? 0;
                                    $lcColor    = $getScoreColor($lcOverall);
                                @endphp
                                <div class="col-md-6">
                                    <div class="p-3 rounded" style="background: #f0f9ff; border: 1px solid #bde0fe; border-top: 3px solid {{ $lcColor }};">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="small fw-semibold" style="color: #1a3a5c;">Latest Counter Match</span>
                                            <span class="badge" style="background: {{ $lcColor }}; font-size: 0.95rem; padding: 4px 10px; color: white;">{{ $lcOverall }}%</span>
                                        </div>
                                        <div class="small text-muted">vs. Buyer's Most Recent Counter</div>
                                        <div class="row g-1 mt-2">
                                            <div class="col-6">
                                                <div class="small fw-semibold" style="color: {{ $getScoreColor($lcSvcScore) }};">Services {{ $lcSvcScore }}%</div>
                                                <div class="small text-muted">{{ $lcSvcTotal > 0 ? $lcSvcMatch.'/'.$lcSvcTotal : 'No services requested' }}</div>
                                                @if($lcSvcTotal > 0 && $lcSvcExtra > 0)<div class="small" style="color: #6c757d;">+{{ $lcSvcExtra }} added</div>@endif
                                                @if($lcSvcTotal > 0 && $lcSvcMiss > 0)<div class="small" style="color: #dc3545;">{{ $lcSvcMiss }} missing</div>@endif
                                            </div>
                                            <div class="col-6">
                                                <div class="small fw-semibold" style="color: {{ $getScoreColor($lcTrmScore) }};">Terms {{ $lcTrmScore }}%</div>
                                                <div class="small text-muted">{{ $lcTrmTotal > 0 ? $lcTrmMatch.'/'.$lcTrmTotal : 'No terms provided' }}</div>
                                                @if($lcTrmTotal > 0 && $lcTrmChg > 0)<div class="small" style="color: #dc3545;">{{ $lcTrmChg }} changed</div>@endif
                                                @if($lcTrmTotal > 0 && $lcTrmAdded > 0)<div class="small" style="color: #6c757d;">+{{ $lcTrmAdded }} added</div>@endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @else
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span style="font-weight: 600; color: #1a3a5c; font-size: 1.1rem;">
                                    <i class="fas fa-chart-pie me-2"></i>Match Score
                                </span>
                                <span class="badge" style="background: {{ $totalScoreColor }}; font-size: 1.1rem; padding: 8px 16px; color: white;">
                                    {{ $totalScore }}%
                                </span>
                            </div>
                            <p class="small text-muted mb-3">
                                <i class="fas fa-info-circle me-1"></i>Match Score compares this counter only to the baseline. Added services or added terms are shown for transparency but do not increase the score.<br>
                                Compared to: <strong>{{ $baselineLabel }}</strong>
                            </p>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <div class="p-2 rounded" style="background: #f8f9fa; border-left: 4px solid {{ $getScoreColor($servicesScore) }};">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="text-muted small fw-semibold">Services Match</span>
                                            <span style="color: {{ $getScoreColor($servicesScore) }}; font-weight: 600;">{{ $servicesScore }}%</span>
                                        </div>
                                        <div class="text-muted small mt-1">{{ $servicesTotal > 0 ? 'Matched Original: '.$servicesMatched.'/'.$servicesTotal : 'No services requested' }}</div>
                                        @if ($servicesTotal > 0 && $servicesExtraCount > 0)
                                        <div class="small mt-1" style="color: #6c757d;">Extra (Added): {{ $servicesExtraCount }}</div>
                                        @endif
                                        @if ($servicesTotal > 0 && $servicesMissingCount > 0)
                                        <div class="small mt-1" style="color: #dc3545;">Missing: {{ $servicesMissingCount }}</div>
                                        @endif
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-2 rounded" style="background: #f8f9fa; border-left: 4px solid {{ $getScoreColor($brokerScore) }};">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="text-muted small fw-semibold">Terms Match</span>
                                            <span style="color: {{ $getScoreColor($brokerScore) }}; font-weight: 600;">{{ $brokerScore }}%</span>
                                        </div>
                                        <div class="text-muted small mt-1">{{ $brokerTotal > 0 ? 'Matched Original: '.$brokerMatched.'/'.$brokerTotal : 'No terms provided' }}</div>
                                        @if ($brokerTotal > 0 && $termsChangedCount > 0)
                                        <div class="small mt-1" style="color: #dc3545;">Changed: {{ $termsChangedCount }}</div>
                                        @endif
                                        @if ($brokerTotal > 0 && $termsAddedCount > 0)
                                        <div class="small mt-1" style="color: #6c757d;">Added: {{ $termsAddedCount }}</div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            @endif
                        </div>
                        @else
                        <div class="alert alert-secondary mb-4" style="border-radius: 10px; border: 1px solid #dee2e6;">
                            <i class="fas fa-info-circle me-2"></i>No match score available — no requirements were provided in the baseline.
                        </div>
                        @endif

                        <div class="mb-4">
                            <h6 class="mb-3" style="color: #049399; font-weight: 600; border-bottom: 2px solid #049399; padding-bottom: 8px;">
                                <i class="fas fa-handshake me-2"></i>Broker Compensation & Agency Agreement Terms
                            </h6>

                            @if (!empty($counterData['commission_structure']) || !empty($counterData['purchase_fee_type']))
                            <div class="mb-4">
                                <h6 class="mb-2" style="color: #049399; font-weight: 600;">A) Buyer's Broker Compensation</h6>
                                <ul class="list-unstyled ps-3 mb-0">
                                    @if (!empty($counterData['commission_structure']))
                                    <li class="mb-2" style="{{ isset($brokerMismatches['commission_structure']) ? $mismatchStyle : '' }}">
                                        <span class="fw-semibold">Buyer's Broker Commission Structure:</span>
                                        {{ $counterData['commission_structure'] }}
                                        {!! isset($brokerMismatches['commission_structure']) ? $mismatchBadge : '' !!}
                                    </li>
                                    @endif
                                    @if (!empty($counterData['purchase_fee_type']))
                                    @php
                                        $purchaseFeeType = $counterData['purchase_fee_type'] ?? '';
                                        $purchaseFeeDisplay = '—';
                                        if ($purchaseFeeType === 'Flat Fee') {
                                            $purchaseFeeDisplay = $fmtMoney($counterData['purchase_fee_flat'] ?? null) ?? '—';
                                        } elseif ($purchaseFeeType === 'Percentage of the Total Purchase Price') {
                                            $pct = $counterData['purchase_fee_percentage'] ?? null;
                                            $purchaseFeeDisplay = $pct ? ($fmtPercent($pct) . ' of Total Purchase Price') : '—';
                                        } elseif ($purchaseFeeType === 'Percentage of the Total Purchase Price + Flat Fee') {
                                            $purchaseFeeDisplay = $joinParts([
                                                $fmtMoney($counterData['purchase_fee_flat_combo'] ?? null),
                                                !empty($counterData['purchase_fee_percentage_combo']) ? ($fmtPercent($counterData['purchase_fee_percentage_combo']) . ' of Total Purchase Price') : null,
                                            ]) ?? '—';
                                        } elseif ($purchaseFeeType === 'other') {
                                            $purchaseFeeDisplay = $counterData['purchase_fee_other'] ?? '—';
                                        } else {
                                            $purchaseFeeDisplay = $purchaseFeeType;
                                        }
                                    @endphp
                                    <li class="mb-2" style="{{ isset($brokerMismatches['purchase_fee_type']) ? $mismatchStyle : '' }}">
                                        <span class="fw-semibold">Buyer's Broker Purchase Fee:</span> {{ $purchaseFeeDisplay }}
                                        {!! isset($brokerMismatches['purchase_fee_type']) ? $mismatchBadge : '' !!}
                                    </li>
                                    @endif
                                </ul>
                            </div>
                            @endif

                            @if (!empty($counterData['interested_lease_option']))
                            <div class="mb-4">
                                <h6 class="mb-2" style="color: #049399; font-weight: 600;">B) Lease Fee</h6>
                                <ul class="list-unstyled ps-3 mb-0">
                                    <li class="mb-2">
                                        <span class="fw-semibold">Interested in a Lease Agreement:</span> {{ $counterData['interested_lease_option'] }}
                                    </li>
                                    @if ($counterData['interested_lease_option'] === 'Yes' && !empty($counterData['lease_fee_type']))
                                    @php
                                        $leaseFeeType = $counterData['lease_fee_type'] ?? '';
                                        $leaseFeeCombined = $leaseFeeType;
                                        if ($leaseFeeType === 'flat' && !empty($counterData['lease_fee_flat'])) {
                                            $leaseFeeCombined = $fmtMoney($counterData['lease_fee_flat']);
                                        } elseif ($leaseFeeType === 'Percentage of the Gross Lease Value' && !empty($counterData['lease_fee_percentage'])) {
                                            $leaseFeeCombined = $fmtPercent($counterData['lease_fee_percentage']) . ' of Gross Lease Value';
                                        }
                                    @endphp
                                    <li class="mb-2" style="{{ isset($brokerMismatches['lease_fee_type']) ? $mismatchStyle : '' }}">
                                        <span class="fw-semibold">Lease Fee:</span> {{ $leaseFeeCombined }}
                                        {!! isset($brokerMismatches['lease_fee_type']) ? $mismatchBadge : '' !!}
                                    </li>
                                    @endif
                                </ul>
                            </div>
                            @endif

                            @if (!empty($counterData['interested_lease_option_agreement']))
                            <div class="mb-4">
                                <h6 class="mb-2" style="color: #049399; font-weight: 600;">C) Lease-Option Details</h6>
                                <ul class="list-unstyled ps-3 mb-0">
                                    <li class="mb-2" style="{{ isset($brokerMismatches['interested_lease_option_agreement']) ? $mismatchStyle : '' }}">
                                        <span class="fw-semibold">Interested in a Lease-Option Agreement:</span> {{ $counterData['interested_lease_option_agreement'] }}
                                        {!! isset($brokerMismatches['interested_lease_option_agreement']) ? $mismatchBadge : '' !!}
                                    </li>
                                    @if ($counterData['interested_lease_option_agreement'] === 'Yes')
                                        @if (!empty($counterData['lease_value']))
                                        <li class="mb-2" style="{{ (isset($brokerMismatches['lease_type']) || isset($brokerMismatches['lease_value'])) ? $mismatchStyle : '' }}">
                                            <span class="fw-semibold">Compensation for Lease-Option Agreement:</span>
                                            {{ ($counterData['lease_type'] ?? '') === 'percent' ? $fmtPercent($counterData['lease_value']) : $fmtMoney($counterData['lease_value']) }}
                                            {!! (isset($brokerMismatches['lease_type']) || isset($brokerMismatches['lease_value'])) ? $mismatchBadge : '' !!}
                                        </li>
                                        @elseif (isset($brokerMismatches['lease_type']) || isset($brokerMismatches['lease_value']))
                                        <li class="mb-2" style="{{ $mismatchStyle }}"><span class="fw-semibold">Compensation for Lease-Option Agreement:</span> —{!! $mismatchBadge !!}</li>
                                        @endif
                                        @if (!empty($counterData['purchase_value']))
                                        <li class="mb-2" style="{{ (isset($brokerMismatches['purchase_type']) || isset($brokerMismatches['purchase_value'])) ? $mismatchStyle : '' }}">
                                            <span class="fw-semibold">Compensation if Purchase Option Exercised:</span>
                                            {{ ($counterData['purchase_type'] ?? '') === 'percent' ? $fmtPercent($counterData['purchase_value']) : $fmtMoney($counterData['purchase_value']) }}
                                            {!! (isset($brokerMismatches['purchase_type']) || isset($brokerMismatches['purchase_value'])) ? $mismatchBadge : '' !!}
                                        </li>
                                        @elseif (isset($brokerMismatches['purchase_type']) || isset($brokerMismatches['purchase_value']))
                                        <li class="mb-2" style="{{ $mismatchStyle }}"><span class="fw-semibold">Compensation if Purchase Option Exercised:</span> —{!! $mismatchBadge !!}</li>
                                        @endif
                                    @endif
                                </ul>
                            </div>
                            @endif

                            @if (!empty($counterData['protection_period']) || !empty($counterData['early_termination_fee_option']) || !empty($counterData['retainer_fee_option']) || !empty($counterData['agency_agreement_timeframe']))
                            <div class="mb-4">
                                <h6 class="mb-2" style="color: #049399; font-weight: 600;">D) Legal Terms</h6>
                                <ul class="list-unstyled ps-3 mb-0">
                                    @if (!empty($counterData['protection_period']))
                                    <li class="mb-2" style="{{ isset($brokerMismatches['protection_period']) ? $mismatchStyle : '' }}">
                                        <span class="fw-semibold">Protection Period Timeframe:</span> {{ $counterData['protection_period'] }} Days
                                        {!! isset($brokerMismatches['protection_period']) ? $mismatchBadge : '' !!}
                                    </li>
                                    @endif
                                    @if (!empty($counterData['early_termination_fee_option']))
                                    <li class="mb-2" style="{{ isset($brokerMismatches['early_termination_fee_option']) ? $mismatchStyle : '' }}">
                                        <span class="fw-semibold">Early Termination Fee:</span> {{ $counterData['early_termination_fee_option'] }}
                                        {!! isset($brokerMismatches['early_termination_fee_option']) ? $mismatchBadge : '' !!}
                                    </li>
                                    @if (($counterData['early_termination_fee_option'] ?? '') === 'Yes' && !empty($counterData['early_termination_fee_amount']))
                                    <li class="mb-2" style="{{ isset($brokerMismatches['early_termination_fee_amount']) ? $mismatchStyle : '' }}">
                                        <span class="fw-semibold">Termination Fee Amount:</span> {{ $fmtMoney($counterData['early_termination_fee_amount']) }}
                                        {!! isset($brokerMismatches['early_termination_fee_amount']) ? $mismatchBadge : '' !!}
                                    </li>
                                    @elseif (($counterData['early_termination_fee_option'] ?? '') === 'Yes' && isset($brokerMismatches['early_termination_fee_amount']))
                                    <li class="mb-2" style="{{ $mismatchStyle }}"><span class="fw-semibold">Termination Fee Amount:</span> —{!! $mismatchBadge !!}</li>
                                    @endif
                                    @endif
                                    @if (!empty($counterData['retainer_fee_option']))
                                    <li class="mb-2" style="{{ isset($brokerMismatches['retainer_fee_option']) ? $mismatchStyle : '' }}">
                                        <span class="fw-semibold">Retainer Fee:</span> {{ $counterData['retainer_fee_option'] }}
                                        {!! isset($brokerMismatches['retainer_fee_option']) ? $mismatchBadge : '' !!}
                                    </li>
                                    @if (($counterData['retainer_fee_option'] ?? '') === 'Yes')
                                        @if (!empty($counterData['retainer_fee_amount']))
                                        <li class="mb-2" style="{{ isset($brokerMismatches['retainer_fee_amount']) ? $mismatchStyle : '' }}">
                                            <span class="fw-semibold">Retainer Fee Amount:</span> {{ $fmtMoney($counterData['retainer_fee_amount']) }}
                                            {!! isset($brokerMismatches['retainer_fee_amount']) ? $mismatchBadge : '' !!}
                                        </li>
                                        @elseif (isset($brokerMismatches['retainer_fee_amount']))
                                        <li class="mb-2" style="{{ $mismatchStyle }}"><span class="fw-semibold">Retainer Fee Amount:</span> —{!! $mismatchBadge !!}</li>
                                        @endif
                                        @if (!empty($counterData['retainer_fee_application']))
                                        <li class="mb-2" style="{{ isset($brokerMismatches['retainer_fee_application']) ? $mismatchStyle : '' }}">
                                            <span class="fw-semibold">Retainer Fee Application:</span>
                                            {{ $counterData['retainer_fee_application'] === 'applied' ? 'Applied toward final compensation' : 'Charged in addition to final compensation' }}
                                            {!! isset($brokerMismatches['retainer_fee_application']) ? $mismatchBadge : '' !!}
                                        </li>
                                        @elseif (isset($brokerMismatches['retainer_fee_application']))
                                        <li class="mb-2" style="{{ $mismatchStyle }}"><span class="fw-semibold">Retainer Fee Application:</span> —{!! $mismatchBadge !!}</li>
                                        @endif
                                    @endif
                                    @endif
                                    @if (!empty($counterData['agency_agreement_timeframe']))
                                    @php
                                        $agencyTimeframe = $counterData['agency_agreement_timeframe'] ?? '';
                                        $agencyCustom    = $counterData['agency_agreement_custom'] ?? '';
                                        $agencyDisplay   = (strtolower(trim($agencyTimeframe)) === 'custom') ? ($agencyCustom ?: 'Custom') : $agencyTimeframe;
                                    @endphp
                                    <li class="mb-2" style="{{ isset($brokerMismatches['agency_agreement_timeframe']) ? $mismatchStyle : '' }}">
                                        <span class="fw-semibold">Agency Agreement Timeframe:</span> {{ $agencyDisplay }}
                                        {!! isset($brokerMismatches['agency_agreement_timeframe']) ? $mismatchBadge : '' !!}
                                    </li>
                                    @endif
                                </ul>
                            </div>
                            @endif

                            @if (!empty($counterData['brokerage_relationship']))
                            <div class="mb-4">
                                <h6 class="mb-2" style="color: #049399; font-weight: 600;">E) Brokerage Relationship</h6>
                                <ul class="list-unstyled ps-3 mb-0">
                                    <li class="mb-2" style="{{ isset($brokerMismatches['brokerage_relationship']) ? $mismatchStyle : '' }}">
                                        <span class="fw-semibold">Acceptable Brokerage Relationship:</span> {{ $counterData['brokerage_relationship'] }}
                                        {!! isset($brokerMismatches['brokerage_relationship']) ? $mismatchBadge : '' !!}
                                    </li>
                                </ul>
                            </div>
                            @endif

                        </div>

                        <div class="mb-4">
                            <h6 class="mb-3" style="color: #049399; font-weight: 600; border-bottom: 2px solid #049399; padding-bottom: 8px;">
                                <i class="fas fa-list-check me-2"></i>Requested Services
                            </h6>

                            @if (!empty($orderedCounterServices))
                                @foreach ($orderedCounterServices as $categoryName => $categoryServices)
                                    @if (!empty($categoryServices))
                                    <div class="mt-3">
                                        <strong>{{ $categoryName }}</strong>
                                        <ul class="list-unstyled ps-3 mt-2">
                                            @foreach ($categoryServices as $service)
                                            @php $isInBaseline = in_array($normalizeService($service), $baselineNorm); @endphp
                                            <li class="mb-1" style="{{ !$isInBaseline ? $addedStyle : '' }}">
                                                <i class="fas fa-check-circle me-2" style="color: #049399;"></i>{{ $service }}
                                                @if (!$isInBaseline){!! $addedBadge !!}@endif
                                            </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                    @endif
                                @endforeach
                            @elseif (!empty($allCounterServices))
                                <ul class="list-unstyled ps-3">
                                    @foreach ($allCounterServices as $service)
                                    @php $isInBaseline = in_array($normalizeService($service), $baselineNorm); @endphp
                                    <li class="mb-1" style="{{ !$isInBaseline ? $addedStyle : '' }}">
                                        <i class="fas fa-check-circle me-2" style="color: #049399;"></i>{{ $service }}
                                        @if (!$isInBaseline){!! $addedBadge !!}@endif
                                    </li>
                                    @endforeach
                                </ul>
                            @endif

                            @if (!empty($counterOtherServices))
                            <div class="mt-3">
                                <strong>✍️ Additional Services</strong>
                                <ul class="list-unstyled ps-3 mt-2">
                                    @foreach ($counterOtherServices as $otherService)
                                    @php $isInBaseline = in_array($normalizeService($otherService), $baselineNorm); @endphp
                                    <li class="mb-1" style="{{ !$isInBaseline ? $addedStyle : '' }}">
                                        <i class="fas fa-check-circle me-2" style="color: #049399;"></i>{{ $otherService }}
                                        @if (!$isInBaseline){!! $addedBadge !!}@endif
                                    </li>
                                    @endforeach
                                </ul>
                            </div>
                            @endif

                            @if (!empty($servicesMissing))
                            <div class="mt-4 p-3" style="background-color: #fff3cd; border-radius: 8px; border: 1px solid #ffc107;">
                                <strong style="color: #856404;"><i class="fas fa-exclamation-triangle me-2"></i>Services Not Included in Counter:</strong>
                                <ul class="list-unstyled ps-3 mt-2 mb-0">
                                    @foreach ($allBaselineServices as $baselineService)
                                        @if (in_array($normalizeService($baselineService), $servicesMissing))
                                        <li class="mb-1" style="{{ $missingStyle }}">
                                            <i class="fas fa-times-circle me-2" style="color: #ffc107;"></i>{{ $baselineService }}
                                        </li>
                                        @endif
                                    @endforeach
                                </ul>
                            </div>
                            @endif
                        </div>

                        @if (!empty($counterData['additional_details_broker']))
                        <div class="mb-4">
                            <h6 class="mb-2" style="color: #049399; font-weight: 600;">
                                <i class="fa fa-file-alt me-2"></i>Broker Additional Terms
                            </h6>
                            <p class="mb-0 ps-3 text-muted">{{ $counterData['additional_details_broker'] }}</p>
                        </div>
                        @endif

                        @if (!empty($counterData['additional_details']))
                        <div class="mb-4">
                            <h6 class="mb-2" style="color: #049399; font-weight: 600;">
                                <i class="fas fa-info-circle me-2"></i>Additional Details
                            </h6>
                            <p class="mb-0 ps-3">{{ $counterData['additional_details'] }}</p>
                        </div>
                        @endif
                    </div>

                    <div class="d-flex gap-2 flex-wrap">
                        <a href="{{ route('buyer.view-auction', $auction->id) }}" class="btn" style="background-color: #fff; border: 2px solid #049399; color: #049399; padding: 10px 20px; font-weight: 600;">
                            <i class="fas fa-eye me-2"></i>View Listing
                        </a>

                        @if ($viewerRole === 'agent')
                        <a href="{{ route('agent.buyer.agent.auction.bid', ['auctionId' => $auction->id]) }}" class="btn" style="background-color: #ffc107; border: 2px solid #ffc107; color: #000; padding: 10px 20px; font-weight: 600;">
                            <i class="fas fa-reply me-2"></i>Counter Back
                        </a>
                        <form action="{{ route('buyer.hire.agent.auction.buyer.counter.term.accept') }}" method="POST" class="d-inline">
                            @csrf
                            <input type="hidden" name="buyer_counter_term_id" value="{{ $activeCounter->id }}">
                            <input type="hidden" name="auction_id" value="{{ $auction->id }}">
                            <button type="submit" class="btn" style="background-color: #28a745; border: 2px solid #28a745; color: #fff; padding: 10px 20px; font-weight: 600;" onclick="return confirm('Are you sure you want to accept these counter terms?')">
                                <i class="fas fa-check me-2"></i>Accept Counter
                            </button>
                        </form>
                        <form action="{{ route('buyer.hire.agent.auction.buyer.counter.term.reject') }}" method="POST" class="d-inline">
                            @csrf
                            <input type="hidden" name="buyer_counter_term_id" value="{{ $activeCounter->id }}">
                            <input type="hidden" name="auction_id" value="{{ $auction->id }}">
                            <button type="submit" class="btn" style="background-color: #dc3545; border: 2px solid #dc3545; color: #fff; padding: 10px 20px; font-weight: 600;" onclick="return confirm('Are you sure you want to reject these counter terms?')">
                                <i class="fas fa-times me-2"></i>Reject Counter
                            </button>
                        </form>
                        @elseif(!$awaitingCounterResponse)
                        <a href="{{ route('buyer.counter-terms', ['id' => $bid->id]) }}" class="btn" style="background-color: #ffc107; border: 2px solid #ffc107; color: #000; padding: 10px 20px; font-weight: 600;">
                            <i class="fas fa-reply me-2"></i>Counter Back
                        </a>
                        <form action="{{ route('buyer.hire.agent.auction.counter.bid.accept') }}" method="POST" class="d-inline">
                            @csrf
                            <input type="hidden" name="counter_bid_id" value="{{ $activeCounter->id }}">
                            <input type="hidden" name="auction_id" value="{{ $auction->id }}">
                            <button type="submit" class="btn" style="background-color: #28a745; border: 2px solid #28a745; color: #fff; padding: 10px 20px; font-weight: 600;" onclick="return confirm('Accept this counter offer from the agent?')">
                                <i class="fas fa-check me-2"></i>Accept
                            </button>
                        </form>
                        <form action="{{ route('buyer.hire.agent.auction.counter.bid.reject') }}" method="POST" class="d-inline">
                            @csrf
                            <input type="hidden" name="counter_bid_id" value="{{ $activeCounter->id }}">
                            <input type="hidden" name="auction_id" value="{{ $auction->id }}">
                            <button type="submit" class="btn" style="background-color: #dc3545; border: 2px solid #dc3545; color: #fff; padding: 10px 20px; font-weight: 600;" onclick="return confirm('Reject this counter offer from the agent?')">
                                <i class="fas fa-times me-2"></i>Reject
                            </button>
                        </form>
                        @endif
                    </div>
                    @else
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        @if($viewerRole === 'agent')
                        No counter terms have been submitted by the buyer yet. Your bid is still active and awaiting a response.
                        @else
                        No counter terms have been submitted by the agent yet.
                        @endif
                    </div>
                    <a href="{{ route('buyer.view-auction', $auction->id) }}" class="btn" style="background-color: #049399; color: white; padding: 10px 20px; font-weight: 600;">
                        <i class="fas fa-arrow-left me-2"></i>Back to Listing
                    </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
