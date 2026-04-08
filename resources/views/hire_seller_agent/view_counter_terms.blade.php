@extends('layouts.main')

@section('content')
<div class="container py-5">
    <div class="row">
        <div class="col-12">
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                    @if($viewerRole === 'agent')
                    <li class="breadcrumb-item"><a href="{{ route('myBids', 'seller-agent') }}">My Bids</a></li>
                    @else
                    <li class="breadcrumb-item"><a href="{{ route('seller.agent.auction.detail', $auction->id) }}">Listing</a></li>
                    @endif
                    <li class="breadcrumb-item active">Counter Terms</li>
                </ol>
            </nav>

            <div class="card mb-4" style="border: 2px solid #049399; border-radius: 8px;">
                <div class="card-header" style="background: linear-gradient(135deg, #049399 0%, #037a7f 100%); color: white;">
                    <h4 class="mb-0">
                        <i class="fas fa-exchange-alt me-2"></i>
                        @if($viewerRole === 'agent')
                        Seller's Counter Terms For Your Bid
                        @else
                        Counter Terms For Agent's Bid
                        @endif
                    </h4>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6 style="color: #049399; font-weight: 600;">Listing Information</h6>
                            <p><strong>Address:</strong> {{ $auction->get->address ?? 'N/A' }}</p>
                            <p><strong>Listing Owner:</strong> {{ $auction->user->name ?? 'N/A' }}</p>
                        </div>
                        <div class="col-md-6">
                            <h6 style="color: #049399; font-weight: 600;">Bid Information</h6>
                            @php
                                $bidStatus = $bid->bid_status ?? 'Active';
                                $statusColors = [
                                    'Countered' => 'background-color: #ffc107; color: #000;',
                                    'Accepted'  => 'background-color: #28a745; color: #fff;',
                                    'Rejected'  => 'background-color: #dc3545; color: #fff;',
                                    'Active'    => 'background-color: #007bff; color: #fff;',
                                ];
                            @endphp
                            <p><strong>Status:</strong>
                                <span class="badge" style="{{ $statusColors[$bidStatus] ?? $statusColors['Active'] }} padding: 6px 12px; border-radius: 4px;">
                                    {{ $bidStatus }}
                                </span>
                            </p>
                            <p><strong>Agent:</strong> {{ $bid->user->name ?? 'N/A' }}</p>
                            <p><strong>Submitted:</strong> {{ $bid->created_at->format('M d, Y h:i A') }}</p>
                        </div>
                    </div>

                    @if($sellerCounter || $agentCounterBack)
                    @php
                        // Determine which counter to display as the primary subject.
                        // Agent views the seller's counter; seller views the agent's counter-back (or their own if no counter-back yet).
                        if ($viewerRole === 'agent') {
                            $activeCounter = $sellerCounter ?? $agentCounterBack;
                        } else {
                            $activeCounter = $agentCounterBack ?? $sellerCounter;
                        }

                        $counterData = $activeCounter ? $activeCounter->getAllMeta() : [];

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
                            $num = (float)$raw;
                            return rtrim(rtrim(number_format($num, 2), '0'), '.') . '%';
                        };
                        $joinParts = function(array $parts) {
                            return implode(' + ', array_filter($parts)) ?: null;
                        };

                        // Baseline for Original Match: agent's bid when agent views; listing terms when seller views.
                        if ($viewerRole === 'agent') {
                            $baselineData = (array) $bid->get;
                            $baselineLabel = 'Your Original Bid';
                        } else {
                            $baselineData = (array) $auction->get;
                            $baselineLabel = 'Your Original Listing Terms';
                        }

                        $counterPropType = $auction->get->property_type ?? 'Residential Property';
                        $score = \App\Helpers\SellerBidMatchScoreHelper::calculate(
                            $baselineData,
                            $counterData,
                            null,
                            $counterPropType
                        );

                        $brokerScore       = $score['terms_match_percent'];
                        $brokerMatched     = $score['terms_matched_count'];
                        $brokerTotal       = $score['terms_baseline_total'];
                        $brokerMismatches  = $score['changed_terms'];
                        $termsChangedCount = $score['terms_changed_count'];
                        $termsAddedCount   = $score['terms_added_count'];
                        $servicesScore     = $score['services_match_percent'];
                        $servicesMatched   = $score['services_matched_count'];
                        $servicesTotal     = $score['services_baseline_total'];
                        $servicesMissingCount = $score['services_missing_count'];
                        $servicesExtraCount   = $score['services_extra_count'];
                        $servicesMissing   = $score['missing_services'];
                        $totalScore        = $score['overall_percent'];

                        $getScoreColor   = fn($s) => \App\Helpers\SellerBidMatchScoreHelper::scoreColor((int)$s);
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

                        // Dual score: when seller views the agent's counter-back and has a prior counter, show Latest Counter Match too.
                        $showDualScore      = false;
                        $latestCounterScore = null;
                        if ($viewerRole === 'seller' && $sellerCounter && $agentCounterBack) {
                            $latestCounterScore = \App\Helpers\SellerBidMatchScoreHelper::calculate(
                                $sellerCounter->getAllMeta(),
                                $counterData,
                                null,
                                $counterPropType
                            );
                            $showDualScore = true;
                        }

                        $normalizeService = fn($s) => \App\Helpers\SellerBidMatchScoreHelper::normalizeService((string)$s);
                        $baselineNorm     = array_merge($score['matched_services'], $score['missing_services']);
                        $displayCatalog   = \App\Helpers\SellerBidMatchScoreHelper::getCatalog($counterPropType);

                        $addedStyle   = 'background-color: #e6ffe6; padding: 2px 6px; border-radius: 4px; border-left: 3px solid #28a745;';
                        $missingStyle = 'background-color: #fff3cd; padding: 2px 6px; border-radius: 4px; border-left: 3px solid #ffc107;';
                        $addedBadge    = '<span class="badge" style="background-color: #28a745; color: white; font-size: 0.7rem; vertical-align: middle; margin-left: 8px;">Added</span>';

                        // Counter services (catalog-filtered for this property type)
                        $ctSvcRaw = $counterData['services'] ?? [];
                        if (is_string($ctSvcRaw)) $ctSvcRaw = json_decode($ctSvcRaw, true) ?? [];
                        $ctSvcRaw = is_array($ctSvcRaw) ? array_values(array_filter($ctSvcRaw)) : [];
                        $ctSvcRaw = array_values(array_filter(
                            $ctSvcRaw,
                            fn($s) => !empty(trim((string)$s)) && $s !== 'Other'
                                && in_array($normalizeService((string)$s), $displayCatalog, true)
                        ));
                        $ctOtherSvcRaw = $counterData['other_services'] ?? [];
                        if (is_string($ctOtherSvcRaw)) $ctOtherSvcRaw = json_decode($ctOtherSvcRaw, true) ?? [];
                        $ctOtherSvcRaw = is_array($ctOtherSvcRaw)
                            ? array_values(array_filter($ctOtherSvcRaw, fn($s) => is_string($s) && !empty(trim($s))))
                            : [];
                        $allCounterServices = array_merge($ctSvcRaw, $ctOtherSvcRaw);

                        // Baseline services for "Not Included" list
                        $bsSvcRaw = $baselineData['services'] ?? [];
                        if (is_string($bsSvcRaw)) $bsSvcRaw = json_decode($bsSvcRaw, true) ?? [];
                        $bsSvcRaw = is_array($bsSvcRaw) ? array_values(array_filter($bsSvcRaw)) : [];
                        $bsSvcRaw = array_values(array_filter(
                            $bsSvcRaw,
                            fn($s) => !empty(trim((string)$s)) && $s !== 'Other'
                                && in_array($normalizeService((string)$s), $displayCatalog, true)
                        ));
                        $bsOtherSvcRaw = $baselineData['other_services'] ?? [];
                        if (is_string($bsOtherSvcRaw)) $bsOtherSvcRaw = json_decode($bsOtherSvcRaw, true) ?? [];
                        $bsOtherSvcRaw = is_array($bsOtherSvcRaw)
                            ? array_values(array_filter($bsOtherSvcRaw, fn($s) => is_string($s) && !empty(trim($s))))
                            : [];
                        $allBaselineServices = array_merge($bsSvcRaw, $bsOtherSvcRaw);
                    @endphp

                    {{-- Match Score Panel --}}
                    @if ($hasAnyBaseline)
                    <div class="match-score-panel mb-4 p-3" style="background: white; border-radius: 10px; border: 1px solid #dee2e6;">

                        @if ($showDualScore && $latestCounterScore)
                        {{-- DUAL SCORE: Original Match + Latest Counter Match --}}
                        <div class="mb-3">
                            <span style="font-weight: 600; color: #1a3a5c; font-size: 1.1rem;">
                                <i class="fas fa-chart-pie me-2"></i>Match Summary
                            </span>
                        </div>
                        <p class="small text-muted mb-3">
                            <i class="fas fa-info-circle me-1"></i>
                            <strong>Original Match</strong> compares this response to the Seller's original listing request.<br>
                            <strong>Latest Counter Match</strong> compares this response to the Seller's most recent counteroffer.<br>
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
                                $lcSvcScore = $latestCounterScore['services_match_percent'];
                                $lcTrmScore = $latestCounterScore['terms_match_percent'];
                                $lcOverall  = $latestCounterScore['overall_percent'];
                                $lcSvcMatch = $latestCounterScore['services_matched_count'];
                                $lcSvcTotal = $latestCounterScore['services_baseline_total'];
                                $lcTrmMatch = $latestCounterScore['terms_matched_count'];
                                $lcTrmTotal = $latestCounterScore['terms_baseline_total'];
                                $lcSvcExtra = $latestCounterScore['services_extra_count'];
                                $lcSvcMiss  = $latestCounterScore['services_missing_count'];
                                $lcTrmChg   = $latestCounterScore['terms_changed_count'];
                                $lcTrmAdded = $latestCounterScore['terms_added_count'];
                                $lcColor    = $getScoreColor($lcOverall);
                            @endphp
                            <div class="col-md-6">
                                <div class="p-3 rounded" style="background: #f0f9ff; border: 1px solid #bde0fe; border-top: 3px solid {{ $lcColor }};">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="small fw-semibold" style="color: #1a3a5c;">Latest Counter Match</span>
                                        <span class="badge" style="background: {{ $lcColor }}; font-size: 0.95rem; padding: 4px 10px; color: white;">{{ $lcOverall }}%</span>
                                    </div>
                                    <div class="small text-muted">vs. Seller's Most Recent Counter</div>
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
                        {{-- SINGLE SCORE: agent viewing seller's counter, or seller has no counter-back yet --}}
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
                                    <div class="text-muted small mt-1">{{ $servicesTotal > 0 ? 'Matched: '.$servicesMatched.'/'.$servicesTotal : 'No services requested' }}</div>
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
                                    <div class="text-muted small mt-1">{{ $brokerTotal > 0 ? 'Matched: '.$brokerMatched.'/'.$brokerTotal : 'No terms provided' }}</div>
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

                    {{-- Counter Terms Details --}}
                    <div class="mb-4">
                        <h5 style="color: #049399; font-weight: 600; border-bottom: 2px solid #049399; padding-bottom: 8px;">
                            <i class="fa fa-handshake me-2"></i>Counter Terms Details
                        </h5>
                        <p class="text-muted small">
                            Submitted: {{ $activeCounter?->created_at?->format('M d, Y h:i A') ?? 'N/A' }}
                        </p>

                        @php
                            $ctPurchaseFeeType = data_get($counterData, 'purchase_fee_type', '');
                            $ctPurchaseFeeFlat = data_get($counterData, 'purchase_fee_flat', '');
                            $ctPurchaseFeePerc = data_get($counterData, 'purchase_fee_percentage', '');
                            $ctPurchaseFeePercCombo = data_get($counterData, 'purchase_fee_percentage_combo', '');
                            $ctPurchaseFeeFlatCombo = data_get($counterData, 'purchase_fee_flat_combo', '');
                            $ctPurchaseFeeOther = data_get($counterData, 'purchase_fee_other', '');

                            $ctPurchaseFeeDisplay = '-';
                            if ($ctPurchaseFeeType === 'flat' && $ctPurchaseFeeFlat) {
                                $ctPurchaseFeeDisplay = $fmtMoney($ctPurchaseFeeFlat) ?? '-';
                            } elseif ($ctPurchaseFeeType === 'percentage' && $ctPurchaseFeePerc) {
                                $ctPurchaseFeeDisplay = ($fmtPercent($ctPurchaseFeePerc) ?? '-') . ' of Total Purchase Price';
                            } elseif ($ctPurchaseFeeType === 'combo') {
                                $ctPurchaseFeeDisplay = $joinParts([
                                    $ctPurchaseFeePercCombo ? ($fmtPercent($ctPurchaseFeePercCombo) . ' of Total Purchase Price') : null,
                                    $fmtMoney($ctPurchaseFeeFlatCombo),
                                ]) ?? '-';
                            } elseif ($ctPurchaseFeeType === 'other' && $ctPurchaseFeeOther) {
                                $ctPurchaseFeeDisplay = $ctPurchaseFeeOther;
                            }

                            $ctCommStruct = data_get($counterData, 'commission_structure', '');
                            $ctCommStructType = data_get($counterData, 'commission_structure_type', '');
                            $ctInterestedLease = data_get($counterData, 'interested_purchase_fee_type', '');
                            $ctLeaseOption = data_get($counterData, 'interested_lease_option_agreement', '');
                            $ctLeaseType = data_get($counterData, 'lease_type', '');
                            $ctLeaseValue = data_get($counterData, 'lease_value', '');
                            $ctPurchaseType = data_get($counterData, 'purchase_type', '');
                            $ctPurchaseValue = data_get($counterData, 'purchase_value', '');
                            $ctEarlyTermOpt = data_get($counterData, 'early_termination_fee_option', '');
                            $ctEarlyTermAmt = data_get($counterData, 'early_termination_fee_amount', '');
                            $ctRetainerOpt = data_get($counterData, 'retainer_fee_option', '');
                            $ctRetainerAmt = data_get($counterData, 'retainer_fee_amount', '');
                            $ctRetainerApp = data_get($counterData, 'retainer_fee_application', '');
                            $ctProtPeriod = data_get($counterData, 'protection_period', '');
                            $ctAgencyTf = data_get($counterData, 'agency_agreement_timeframe', '');
                            $ctAgencyCus = data_get($counterData, 'agency_agreement_custom', '');
                            $ctAgencyDsp = strtolower(trim($ctAgencyTf ?? '')) === 'other' ? ($ctAgencyCus ?: 'Other') : ($ctAgencyTf ?: '');
                            $ctBrokerRel = data_get($counterData, 'brokerage_relationship', '');
                            $ctAddlDetails = data_get($counterData, 'additional_details_broker', '');
                            $ctRetainedDep = data_get($counterData, 'retained_deposits', '');
                            $ctNominal = data_get($counterData, 'nominal', '');
                            // Build leasing fee display for B) Lease Terms
                            $ctLeasingFeeType = data_get($counterData, 'seller_leasing_fee_type', '');
                            $ctLeasingFeeAmt = null;
                            if ($ctLeasingFeeType === 'Flat Fee' && data_get($counterData, 'seller_leasing_gross_purchase_fee_flat_amount')) {
                                $ctLeasingFeeAmt = $fmtMoney(data_get($counterData, 'seller_leasing_gross_purchase_fee_flat_amount'));
                            } elseif ($ctLeasingFeeType === 'Percentage of the Gross Lease Value' && data_get($counterData, 'seller_leasing_gross')) {
                                $ctLeasingFeeAmt = ($fmtPercent(data_get($counterData, 'seller_leasing_gross')) ?? '-') . ' of the Gross Lease Value';
                            } elseif ($ctLeasingFeeType === 'Percentage of the Rent Due Each Rental Period' && data_get($counterData, 'seller_leasing_gross_rental')) {
                                $ctLeasingFeeAmt = ($fmtPercent(data_get($counterData, 'seller_leasing_gross_rental')) ?? '-') . ' of the Rent Due Each Rental Period';
                            } elseif ($ctLeasingFeeType === "Percentage of the First Month's Rent" && data_get($counterData, 'seller_leasing_gross_month_rent')) {
                                $ctLeasingFeeAmt = ($fmtPercent(data_get($counterData, 'seller_leasing_gross_month_rent')) ?? '-') . " of the First Month's Rent";
                            } elseif ($ctLeasingFeeType === "Percentage of Month's Rent" && data_get($counterData, 'seller_leasing_gross_month_rent')) {
                                $ctLeasingFeeAmt = ($fmtPercent(data_get($counterData, 'seller_leasing_gross_month_rent')) ?? '-') . " of Month's Rent";
                                $ctLeasingMonths = data_get($counterData, 'seller_leasing_gross_no_of_months');
                                if (!empty($ctLeasingMonths) && $ctLeasingMonths != 'null') {
                                    $ctLeasingFeeAmt .= ' x ' . intval($ctLeasingMonths) . ' Months';
                                }
                            } elseif ($ctLeasingFeeType === 'Percentage of Net Aggregate Rent' && (data_get($counterData, 'seller_leasing_gross_other') ?: data_get($counterData, 'seller_leasing_gross'))) {
                                $netAggVal = data_get($counterData, 'seller_leasing_gross_other') ?: data_get($counterData, 'seller_leasing_gross');
                                $ctLeasingFeeAmt = ($fmtPercent($netAggVal) ?? '-') . ' of Net Aggregate Rent';
                            } elseif ($ctLeasingFeeType === 'Percentage of Gross Rent' && (data_get($counterData, 'seller_leasing_gross_percentage') || data_get($counterData, 'seller_leasing_gross_ross_percentage_rent'))) {
                                $grossRentVal = data_get($counterData, 'seller_leasing_gross_percentage') ?? data_get($counterData, 'seller_leasing_gross_ross_percentage_rent');
                                $ctLeasingFeeAmt = ($fmtPercent($grossRentVal) ?? '-') . ' of Gross Rent';
                            } elseif (strtolower($ctLeasingFeeType ?? '') === 'other' && data_get($counterData, 'seller_leasing_gross_purchase_fee_other')) {
                                $ctLeasingFeeAmt = data_get($counterData, 'seller_leasing_gross_purchase_fee_other');
                            } elseif ($ctLeasingFeeType) {
                                $ctLeasingFeeAmt = $ctLeasingFeeType;
                            }
                        @endphp

                        {{-- A) Seller Broker Compensation --}}
                        @if($ctPurchaseFeeType || $ctCommStruct || $ctCommStructType || $ctNominal)
                        <div class="mb-4">
                            <h6 class="mb-2" style="color: #049399; font-weight: 600;">A) Seller's Broker Compensation</h6>
                            <ul class="list-unstyled ps-3 mb-0">
                                @if($ctPurchaseFeeType)
                                <li class="mb-1 @if(isset($brokerMismatches['purchase_fee_type'])) text-danger @endif">
                                    <span class="fw-semibold">Seller's Broker Purchase Fee:</span>
                                    {{ $ctPurchaseFeeDisplay }}
                                    @if(isset($brokerMismatches['purchase_fee_type']))<span class="badge bg-danger ms-1" style="font-size:0.7rem;">Changed</span>@endif
                                </li>
                                @endif
                                @if($ctNominal)
                                <li class="mb-1 @if(isset($brokerMismatches['nominal'])) text-danger @endif">
                                    <span class="fw-semibold">Nominal Consideration Fee:</span> {{ $fmtMoney($ctNominal) ?? '-' }}
                                    @if(isset($brokerMismatches['nominal']))<span class="badge bg-danger ms-1" style="font-size:0.7rem;">Changed</span>@endif
                                </li>
                                @endif
                                @if($ctCommStruct)
                                <li class="mb-1 @if(isset($brokerMismatches['commission_structure'])) text-danger @endif">
                                    <span class="fw-semibold">Buyer's Broker Commission Structure:</span> {{ $ctCommStruct }}
                                    @if(isset($brokerMismatches['commission_structure']))<span class="badge bg-danger ms-1" style="font-size:0.7rem;">Changed</span>@endif
                                </li>
                                @endif
                                @if($ctCommStructType)
                                <li class="mb-1 @if(isset($brokerMismatches['commission_structure_type'])) text-danger @endif">
                                    <span class="fw-semibold">Buyer's Broker Commission Type:</span> {{ $ctCommStructType }}
                                    @if(isset($brokerMismatches['commission_structure_type']))<span class="badge bg-danger ms-1" style="font-size:0.7rem;">Changed</span>@endif
                                </li>
                                @endif
                            </ul>
                        </div>
                        @endif

                        {{-- B) Lease Terms --}}
                        @if($ctInterestedLease)
                        <div class="mb-4">
                            <h6 class="mb-2" style="color: #049399; font-weight: 600;">B) Lease Terms</h6>
                            <ul class="list-unstyled ps-3 mb-0">
                                <li class="mb-1 @if(isset($brokerMismatches['interested_purchase_fee_type'])) text-danger @endif">
                                    <span class="fw-semibold">Interested in Offering a Lease Agreement:</span> {{ $ctInterestedLease }}
                                    @if(isset($brokerMismatches['interested_purchase_fee_type']))<span class="badge bg-danger ms-1" style="font-size:0.7rem;">Changed</span>@endif
                                </li>
                                @if(strtolower($ctInterestedLease) === 'yes' && $ctLeasingFeeAmt)
                                <li class="mb-1 @if(isset($brokerMismatches['seller_leasing_fee_type'])) text-danger @endif">
                                    <span class="fw-semibold">Seller's Broker Leasing Fee:</span> {{ $ctLeasingFeeAmt }}
                                    @if(isset($brokerMismatches['seller_leasing_fee_type']))<span class="badge bg-danger ms-1" style="font-size:0.7rem;">Changed</span>@endif
                                </li>
                                @elseif(strtolower($ctInterestedLease) === 'yes' && isset($brokerMismatches['seller_leasing_fee_type']))
                                <li class="mb-1 text-danger"><span class="fw-semibold">Seller's Broker Leasing Fee:</span> —<span class="badge bg-danger ms-1" style="font-size:0.7rem;">Changed</span></li>
                                @endif
                            </ul>
                        </div>
                        @endif

                        {{-- C) Lease-Option Terms --}}
                        @if($ctLeaseOption)
                        <div class="mb-4">
                            <h6 class="mb-2" style="color: #049399; font-weight: 600;">C) Lease-Option Terms</h6>
                            <ul class="list-unstyled ps-3 mb-0">
                                <li class="mb-1 @if(isset($brokerMismatches['interested_lease_option_agreement'])) text-danger @endif">
                                    <span class="fw-semibold">Interested in Lease-Option Agreement:</span> {{ $ctLeaseOption }}
                                    @if(isset($brokerMismatches['interested_lease_option_agreement']))<span class="badge bg-danger ms-1" style="font-size:0.7rem;">Changed</span>@endif
                                </li>
                                @if(strtolower($ctLeaseOption) === 'yes' && $ctLeaseValue)
                                <li class="mb-1 @if(isset($brokerMismatches['lease_type']) || isset($brokerMismatches['lease_value'])) text-danger @endif">
                                    <span class="fw-semibold">Compensation for Lease-Option Agreement:</span>
                                    {{ $ctLeaseType === 'percent' ? ($fmtPercent($ctLeaseValue) . ' of Total Purchase Price') : $fmtMoney($ctLeaseValue) }}
                                    @if(isset($brokerMismatches['lease_type']) || isset($brokerMismatches['lease_value']))<span class="badge bg-danger ms-1" style="font-size:0.7rem;">Changed</span>@endif
                                </li>
                                @elseif(strtolower($ctLeaseOption) === 'yes' && (isset($brokerMismatches['lease_type']) || isset($brokerMismatches['lease_value'])))
                                <li class="mb-1 text-danger"><span class="fw-semibold">Compensation for Lease-Option Agreement:</span> —<span class="badge bg-danger ms-1" style="font-size:0.7rem;">Changed</span></li>
                                @endif
                                @if(strtolower($ctLeaseOption) === 'yes' && $ctPurchaseValue)
                                <li class="mb-1 @if(isset($brokerMismatches['purchase_type']) || isset($brokerMismatches['purchase_value'])) text-danger @endif">
                                    <span class="fw-semibold">Compensation if Purchase Option is Exercised:</span>
                                    {{ $ctPurchaseType === 'percent' ? ($fmtPercent($ctPurchaseValue) . ' of Total Purchase Price') : $fmtMoney($ctPurchaseValue) }}
                                    @if(isset($brokerMismatches['purchase_type']) || isset($brokerMismatches['purchase_value']))<span class="badge bg-danger ms-1" style="font-size:0.7rem;">Changed</span>@endif
                                </li>
                                @elseif(strtolower($ctLeaseOption) === 'yes' && (isset($brokerMismatches['purchase_type']) || isset($brokerMismatches['purchase_value'])))
                                <li class="mb-1 text-danger"><span class="fw-semibold">Compensation if Purchase Option is Exercised:</span> —<span class="badge bg-danger ms-1" style="font-size:0.7rem;">Changed</span></li>
                                @endif
                            </ul>
                        </div>
                        @endif

                        {{-- D) Legal Terms --}}
                        @if($ctEarlyTermOpt || $ctRetainerOpt || $ctRetainedDep || $ctProtPeriod || $ctAgencyTf)
                        <div class="mb-4">
                            <h6 class="mb-2" style="color: #049399; font-weight: 600;">D) Legal Terms</h6>
                            <ul class="list-unstyled ps-3 mb-0">
                                @if($ctEarlyTermOpt)
                                <li class="mb-1 @if(isset($brokerMismatches['early_termination_fee_option'])) text-danger @endif">
                                    <span class="fw-semibold">Early Termination Fee:</span>
                                    {{ ucfirst($ctEarlyTermOpt) }}
                                    @if(isset($brokerMismatches['early_termination_fee_option']))<span class="badge bg-danger ms-1" style="font-size:0.7rem;">Changed</span>@endif
                                </li>
                                @if(strtolower($ctEarlyTermOpt) === 'yes' && $ctEarlyTermAmt)
                                <li class="mb-1 @if(isset($brokerMismatches['early_termination_fee_amount'])) text-danger @endif">
                                    <span class="fw-semibold">Termination Fee Amount:</span>
                                    {{ $fmtMoney($ctEarlyTermAmt) ?? $ctEarlyTermAmt }}
                                    @if(isset($brokerMismatches['early_termination_fee_amount']))<span class="badge bg-danger ms-1" style="font-size:0.7rem;">Changed</span>@endif
                                </li>
                                @elseif(strtolower($ctEarlyTermOpt) === 'yes' && isset($brokerMismatches['early_termination_fee_amount']))
                                <li class="mb-1 text-danger"><span class="fw-semibold">Termination Fee Amount:</span> —<span class="badge bg-danger ms-1" style="font-size:0.7rem;">Changed</span></li>
                                @endif
                                @endif
                                @if($ctRetainerOpt)
                                <li class="mb-1 @if(isset($brokerMismatches['retainer_fee_option'])) text-danger @endif">
                                    <span class="fw-semibold">Retainer Fee:</span>
                                    {{ ucfirst($ctRetainerOpt) }}
                                    @if(isset($brokerMismatches['retainer_fee_option']))<span class="badge bg-danger ms-1" style="font-size:0.7rem;">Changed</span>@endif
                                </li>
                                @if(strtolower($ctRetainerOpt) === 'yes' && $ctRetainerAmt)
                                <li class="mb-1 @if(isset($brokerMismatches['retainer_fee_amount'])) text-danger @endif">
                                    <span class="fw-semibold">Retainer Fee Amount:</span>
                                    {{ $fmtMoney($ctRetainerAmt) ?? $ctRetainerAmt }}
                                    @if(isset($brokerMismatches['retainer_fee_amount']))<span class="badge bg-danger ms-1" style="font-size:0.7rem;">Changed</span>@endif
                                </li>
                                @elseif(strtolower($ctRetainerOpt) === 'yes' && isset($brokerMismatches['retainer_fee_amount']))
                                <li class="mb-1 text-danger"><span class="fw-semibold">Retainer Fee Amount:</span> —<span class="badge bg-danger ms-1" style="font-size:0.7rem;">Changed</span></li>
                                @endif
                                @if(strtolower($ctRetainerOpt) === 'yes' && $ctRetainerApp)
                                <li class="mb-1 @if(isset($brokerMismatches['retainer_fee_application'])) text-danger @endif">
                                    <span class="fw-semibold">Retainer Fee Application:</span> {{ $ctRetainerApp }}
                                    @if(isset($brokerMismatches['retainer_fee_application']))<span class="badge bg-danger ms-1" style="font-size:0.7rem;">Changed</span>@endif
                                </li>
                                @elseif(strtolower($ctRetainerOpt) === 'yes' && isset($brokerMismatches['retainer_fee_application']))
                                <li class="mb-1 text-danger"><span class="fw-semibold">Retainer Fee Application:</span> —<span class="badge bg-danger ms-1" style="font-size:0.7rem;">Changed</span></li>
                                @endif
                                @endif
                                @if($ctRetainedDep)
                                <li class="mb-1 @if(isset($brokerMismatches['retained_deposits'])) text-danger @endif">
                                    <span class="fw-semibold">Seller's Broker's Share of Retained Deposits:</span> {{ $fmtPercent($ctRetainedDep) ?? $ctRetainedDep }}
                                    @if(isset($brokerMismatches['retained_deposits']))<span class="badge bg-danger ms-1" style="font-size:0.7rem;">Changed</span>@endif
                                </li>
                                @endif
                                @if($ctProtPeriod)
                                <li class="mb-1 @if(isset($brokerMismatches['protection_period'])) text-danger @endif">
                                    <span class="fw-semibold">Protection Period:</span> {{ $ctProtPeriod }} days
                                    @if(isset($brokerMismatches['protection_period']))<span class="badge bg-danger ms-1" style="font-size:0.7rem;">Changed</span>@endif
                                </li>
                                @endif
                                @if($ctAgencyTf)
                                <li class="mb-1 @if(isset($brokerMismatches['agency_agreement_timeframe'])) text-danger @endif">
                                    <span class="fw-semibold">Seller Agency Agreement Timeframe:</span> {{ $ctAgencyDsp }}
                                    @if(isset($brokerMismatches['agency_agreement_timeframe']))<span class="badge bg-danger ms-1" style="font-size:0.7rem;">Changed</span>@endif
                                </li>
                                @endif
                            </ul>
                        </div>
                        @endif

                        {{-- E) Brokerage Relationship --}}
                        @if($ctBrokerRel)
                        <div class="mb-4">
                            <h6 class="mb-2" style="color: #049399; font-weight: 600;">E) Brokerage Relationship</h6>
                            <ul class="list-unstyled ps-3 mb-0">
                                <li class="mb-1 @if(isset($brokerMismatches['brokerage_relationship'])) text-danger @endif">
                                    <span class="fw-semibold">Acceptable Brokerage Relationship:</span> {{ $ctBrokerRel }}
                                    @if(isset($brokerMismatches['brokerage_relationship']))<span class="badge bg-danger ms-1" style="font-size:0.7rem;">Changed</span>@endif
                                </li>
                            </ul>
                        </div>
                        @endif

                        {{-- Services in Counter (catalog-filtered, with Added/Not-in-Counter badges) --}}
                        @if(!empty($allCounterServices) || !empty($allBaselineServices))
                        <div class="mb-4">
                            <h6 class="mb-2" style="color: #049399; font-weight: 600;">G) Offered Services in Counter</h6>

                            @if(!empty($allCounterServices))
                            <ul class="list-unstyled ps-3 mb-2">
                                @foreach($allCounterServices as $svc)
                                @php
                                    $svcNorm    = $normalizeService((string)$svc);
                                    $isInBaseline = in_array($svcNorm, $baselineNorm, true);
                                @endphp
                                <li class="mb-1" style="{{ !$isInBaseline ? $addedStyle : '' }}">
                                    <i class="fas fa-check-circle me-2" style="color: #049399;"></i>{{ $svc }}
                                    @if (!$isInBaseline)
                                    {!! $addedBadge !!}
                                    @endif
                                </li>
                                @endforeach
                            </ul>
                            @endif

                            @if (!empty($servicesMissing))
                            <div class="mt-3 p-3" style="background-color: #fff3cd; border-radius: 8px; border: 1px solid #ffc107;">
                                <strong style="color: #856404;"><i class="fas fa-exclamation-triangle me-2"></i>Services Not Included in Counter:</strong>
                                <ul class="list-unstyled ps-3 mt-2 mb-0">
                                    @foreach ($allBaselineServices as $bsSvc)
                                        @if (in_array($normalizeService((string)$bsSvc), $servicesMissing, true))
                                        <li class="mb-1" style="{{ $missingStyle }}">
                                            <i class="fas fa-times-circle me-2" style="color: #ffc107;"></i>{{ $bsSvc }}
                                        </li>
                                        @endif
                                    @endforeach
                                </ul>
                            </div>
                            @endif
                        </div>
                        @endif
                    </div>

                    {{-- Broker Additional Terms (standalone, outside Counter Terms Details) --}}
                    @if($ctAddlDetails)
                    <div class="mb-4">
                        <h6 class="mb-2" style="color: #049399; font-weight: 600; border-bottom: 2px solid #049399; padding-bottom: 8px;">
                            <i class="fa fa-file-alt me-2"></i>Broker Additional Terms
                        </h6>
                        <div class="ps-3 text-muted">{{ $ctAddlDetails }}</div>
                    </div>
                    @endif

                    {{-- Agent Counter-Back Section --}}
                    {{-- Only show for agent viewing (seller already sees agent counter-back as main panel above) --}}
                    @if($agentCounterBack && $viewerRole === 'agent')
                    @php
                        $agentCounterData = $agentCounterBack->getAllMeta();
                        $agentPurchaseFeeType = data_get($agentCounterData, 'purchase_fee_type', '');
                        $agentPurchaseFeeFlat = data_get($agentCounterData, 'purchase_fee_flat', '');
                        $agentPurchaseFeePerc = data_get($agentCounterData, 'purchase_fee_percentage', '');
                        $agentPurchaseFeePercCombo = data_get($agentCounterData, 'purchase_fee_percentage_combo', '');
                        $agentPurchaseFeeFlatCombo = data_get($agentCounterData, 'purchase_fee_flat_combo', '');
                        $agentPurchaseFeeOther = data_get($agentCounterData, 'purchase_fee_other', '');
                        $agentPurchaseFeeDisplay = '-';
                        if ($agentPurchaseFeeType === 'flat' && $agentPurchaseFeeFlat) {
                            $agentPurchaseFeeDisplay = $fmtMoney($agentPurchaseFeeFlat) ?? '-';
                        } elseif ($agentPurchaseFeeType === 'percentage' && $agentPurchaseFeePerc) {
                            $agentPurchaseFeeDisplay = ($fmtPercent($agentPurchaseFeePerc) ?? '-') . ' of Total Purchase Price';
                        } elseif ($agentPurchaseFeeType === 'combo') {
                            $agentPurchaseFeeDisplay = $joinParts([
                                $agentPurchaseFeePercCombo ? ($fmtPercent($agentPurchaseFeePercCombo) . ' of Total Purchase Price') : null,
                                $fmtMoney($agentPurchaseFeeFlatCombo),
                            ]) ?? '-';
                        } elseif ($agentPurchaseFeeType === 'other' && $agentPurchaseFeeOther) {
                            $agentPurchaseFeeDisplay = $agentPurchaseFeeOther;
                        }
                    @endphp
                    <div class="mb-4 p-3" style="background: #fff8e1; border-left: 4px solid #ffc107; border-radius: 6px;">
                        <h5 style="color: #7a5f00; font-weight: 600; margin-bottom: 8px;">
                            <i class="fas fa-reply me-2"></i>Agent's Counter-Back
                        </h5>
                        <p class="text-muted small mb-2">Submitted: {{ $agentCounterBack->created_at->format('M d, Y h:i A') }}</p>
                        @if($agentPurchaseFeeType)
                        <p class="mb-1"><span class="fw-semibold">Seller's Broker Purchase Fee:</span> {{ $agentPurchaseFeeDisplay }}</p>
                        @endif
                        @if(data_get($agentCounterData, 'commission_structure'))
                        <p class="mb-1"><span class="fw-semibold">Buyer's Broker Commission Structure:</span> {{ data_get($agentCounterData, 'commission_structure') }}</p>
                        @endif
                        @if(data_get($agentCounterData, 'brokerage_relationship'))
                        <p class="mb-1"><span class="fw-semibold">Brokerage Relationship:</span> {{ data_get($agentCounterData, 'brokerage_relationship') }}</p>
                        @endif
                        @if(data_get($agentCounterData, 'agency_agreement_timeframe'))
                        <p class="mb-1"><span class="fw-semibold">Agency Agreement Timeframe:</span> {{ data_get($agentCounterData, 'agency_agreement_timeframe') }}</p>
                        @endif
                        @if(data_get($agentCounterData, 'additional_details_broker'))
                        <p class="mb-0"><span class="fw-semibold">Additional Terms:</span> {{ data_get($agentCounterData, 'additional_details_broker') }}</p>
                        @endif
                    </div>
                    @endif

                    {{-- Action area --}}
                    @php $bidIsTerminal = in_array($bid->accepted ?? '', ['accepted', 'rejected'], true); @endphp
                    <div class="d-flex gap-3 mt-4 flex-wrap">

                        {{-- AGENT ACTIONS --}}
                        @if($viewerRole === 'agent' && !$bidIsTerminal)
                            @if($activeStage === 'agent_needs_response' && $sellerCounter)
                            {{-- Agent must respond to seller's counter --}}
                            <form action="{{ route('hire.seller.agent.auction.counter.accept') }}" method="post"
                                  onsubmit="return confirm('Accept this counter offer? This will mark your bid as accepted.');">
                                @csrf
                                <input type="hidden" name="counter_term_id" value="{{ $sellerCounter->id }}">
                                <button type="submit" class="btn" style="background-color:#28a745;border:2px solid #28a745;color:#fff;padding:10px 20px;font-weight:600;">
                                    <i class="fas fa-check me-2"></i>Accept Counter
                                </button>
                            </form>
                            <a href="{{ route('seller.counter-terms', ['id' => $bid->id]) }}"
                               class="btn" style="background-color:#ffc107;border:2px solid #ffc107;color:#000;padding:10px 20px;font-weight:600;">
                                <i class="fas fa-reply me-2"></i>Counter Back
                            </a>
                            <form action="{{ route('hire.seller.agent.auction.counter.reject') }}" method="post"
                                  onsubmit="return confirm('Reject this counter offer?');">
                                @csrf
                                <input type="hidden" name="counter_term_id" value="{{ $sellerCounter->id }}">
                                <button type="submit" class="btn" style="background-color:#dc3545;border:2px solid #dc3545;color:#fff;padding:10px 20px;font-weight:600;">
                                    <i class="fas fa-times me-2"></i>Reject Counter
                                </button>
                            </form>
                            @elseif($activeStage === 'seller_needs_response')
                            {{-- Agent already counter-backed; waiting on seller --}}
                            <div class="alert alert-info mb-0">
                                <i class="fas fa-clock me-2"></i>Your counter-back has been submitted. Waiting for the seller to respond.
                            </div>
                            @else
                            <div class="alert alert-secondary mb-0">
                                No counter terms to respond to yet.
                            </div>
                            @endif
                        @elseif($viewerRole === 'agent' && $bidIsTerminal)
                        <div class="alert alert-secondary mb-0">
                            This bid has been {{ ucfirst($bid->accepted ?? 'resolved') }}.
                        </div>
                        @endif

                        {{-- SELLER ACTIONS --}}
                        @if($viewerRole === 'seller' && !$bidIsTerminal)
                            @if($activeStage === 'seller_needs_response' && $agentCounterBack)
                            {{-- Agent counter-backed; seller can accept, counter back, or reject --}}
                            <form action="{{ route('hire.seller.agent.auction.counter.accept') }}" method="post"
                                  onsubmit="return confirm('Accept the agent\'s counter-back? This will finalize the bid.');">
                                @csrf
                                <input type="hidden" name="counter_term_id" value="{{ $agentCounterBack->id }}">
                                <button type="submit" class="btn" style="background-color:#28a745;border:2px solid #28a745;color:#fff;padding:10px 20px;font-weight:600;">
                                    <i class="fas fa-check me-2"></i>Accept Counter-Back
                                </button>
                            </form>
                            <a href="{{ route('seller.counter-terms', ['id' => $bid->id]) }}"
                               class="btn" style="background-color:#049399;border:2px solid #049399;color:#fff;padding:10px 20px;font-weight:600;">
                                <i class="fa fa-edit me-2"></i>Edit/Revise Counter Terms
                            </a>
                            <form action="{{ route('hire.seller.agent.auction.counter.reject') }}" method="post"
                                  onsubmit="return confirm('Reject the agent\'s counter-back?');">
                                @csrf
                                <input type="hidden" name="counter_term_id" value="{{ $agentCounterBack->id }}">
                                <button type="submit" class="btn" style="background-color:#dc3545;border:2px solid #dc3545;color:#fff;padding:10px 20px;font-weight:600;">
                                    <i class="fas fa-times me-2"></i>Reject Counter-Back
                                </button>
                            </form>
                            @elseif($activeStage === 'agent_needs_response')
                            {{-- Seller already countered; agent hasn't responded yet --}}
                            <div class="alert alert-info mb-0">
                                <i class="fas fa-clock me-2"></i>Counter terms sent. Waiting for the agent to respond.
                            </div>
                            <a href="{{ route('seller.counter-terms', ['id' => $bid->id]) }}"
                               class="btn" style="background-color:#049399;border:2px solid #049399;color:#fff;padding:10px 20px;font-weight:600;">
                                <i class="fa fa-edit me-2"></i>Edit Counter Terms
                            </a>
                            @else
                            {{-- No counter yet; seller creates initial counter --}}
                            <a href="{{ route('seller.counter-terms', ['id' => $bid->id]) }}"
                               class="btn" style="background-color:#049399;border:2px solid #049399;color:#fff;padding:10px 20px;font-weight:600;">
                                <i class="fa fa-edit me-2"></i>Submit Counter Terms
                            </a>
                            @endif
                        @elseif($viewerRole === 'seller' && $bidIsTerminal)
                        <div class="alert alert-secondary mb-0">
                            This bid has been {{ ucfirst($bid->accepted ?? 'resolved') }}.
                        </div>
                        @endif

                        <a href="{{ route('seller.agent.auction.detail', $auction->id) }}"
                           class="btn" style="background-color:#fff;border:2px solid #049399;color:#049399;padding:10px 20px;font-weight:600;">
                            <i class="fa fa-arrow-left me-2"></i>View Listing
                        </a>
                    </div>

                    @else
                    <div class="alert alert-info">
                        <i class="fa fa-info-circle me-2"></i>
                        No counter terms have been submitted for this bid yet.
                    </div>
                    @if($viewerRole === 'seller' && !in_array($bid->accepted ?? '', ['accepted', 'rejected'], true))
                    <a href="{{ route('seller.counter-terms', ['id' => $bid->id]) }}"
                       class="btn mt-3 me-2" style="background-color:#049399;border:2px solid #049399;color:#fff;padding:10px 20px;font-weight:600;">
                        <i class="fa fa-edit me-2"></i>Submit Counter Terms
                    </a>
                    @endif
                    <a href="{{ route('seller.agent.auction.detail', $auction->id) }}" class="btn btn-outline-secondary mt-2">
                        <i class="fa fa-arrow-left me-1"></i> Back to Listing
                    </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
