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

                    @if($sellerCounter)
                    @php
                        $counterData = (array) $sellerCounter->get;

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

                        // Baseline: agent viewing seller's counter vs agent's original bid
                        // Seller viewing: compare counter to original auction listing terms
                        if ($viewerRole === 'agent') {
                            $baselineData = (array) $bid->get;
                            $baselineLabel = 'Your Original Bid';
                        } else {
                            $baselineData = [
                                'commission_structure'          => $auction->get->commission_structure ?? null,
                                'purchase_fee_type'             => $auction->get->purchase_fee_type ?? null,
                                'commission_structure_type'     => $auction->get->commission_structure_type ?? null,
                                'interested_purchase_fee_type'  => $auction->get->interested_purchase_fee_type ?? null,
                                'interested_lease_option_agreement' => $auction->get->interested_lease_option_agreement ?? null,
                                'protection_period'             => $auction->get->protection_period ?? null,
                                'early_termination_fee_option'  => $auction->get->early_termination_fee_option ?? null,
                                'early_termination_fee_amount'  => $auction->get->early_termination_fee_amount ?? null,
                                'retainer_fee_option'           => $auction->get->retainer_fee_option ?? null,
                                'retainer_fee_amount'           => $auction->get->retainer_fee_amount ?? null,
                                'retainer_fee_application'      => $auction->get->retainer_fee_application ?? null,
                                'retained_deposits'             => $auction->get->retained_deposits ?? null,
                                'agency_agreement_timeframe'    => $auction->get->agency_agreement_timeframe ?? null,
                                'brokerage_relationship'        => $auction->get->brokerage_relationship ?? null,
                                'purchase_fee_flat'             => $auction->get->purchase_fee_flat ?? null,
                                'purchase_fee_percentage'       => $auction->get->purchase_fee_percentage ?? null,
                                'purchase_fee_flat_combo'       => $auction->get->purchase_fee_flat_combo ?? null,
                                'purchase_fee_percentage_combo' => $auction->get->purchase_fee_percentage_combo ?? null,
                                'purchase_fee_other'            => $auction->get->purchase_fee_other ?? null,
                                'nominal'                       => $auction->get->nominal ?? null,
                                'additional_details_broker'     => $auction->get->additional_details_broker ?? null,
                                'services'                      => $auction->get->services ?? [],
                                'other_services'                => $auction->get->other_services ?? [],
                            ];
                            $baselineLabel = 'Your Original Listing Terms';
                        }

                        // === MATCH SCORE using SellerBidMatchScoreHelper ===
                        $counterPropType = $auction->get->property_type ?? 'Residential Property';
                        $score = \App\Helpers\SellerBidMatchScoreHelper::calculate(
                            $baselineData,
                            $counterData,
                            null,
                            $counterPropType
                        );

                        $brokerScore      = $score['terms_match_percent'];
                        $brokerMatched    = $score['terms_matched_count'];
                        $brokerTotal      = $score['terms_baseline_total'];
                        $brokerMismatches = $score['changed_terms'];
                        $servicesScore    = $score['services_match_percent'];
                        $servicesMatched  = $score['services_matched_count'];
                        $servicesTotal    = $score['services_baseline_total'];
                        $totalScore       = $score['overall_percent'];

                        $getScoreColor   = fn($s) => \App\Helpers\SellerBidMatchScoreHelper::scoreColor((int)$s);
                        $totalScoreColor = $getScoreColor($totalScore);
                    @endphp

                    {{-- Match Score Panel --}}
                    <div class="match-score-panel mb-4 p-3" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 10px; border: 1px solid #dee2e6;">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0" style="color: #1a3a5c; font-weight: 600;">
                                <i class="fa fa-chart-pie me-2"></i>Match Score
                            </h6>
                            <span class="badge" style="background: {{ $totalScoreColor }}; font-size: 1.1rem; padding: 8px 16px;">
                                {{ $totalScore }}% Match
                            </span>
                        </div>
                        <p class="small text-muted mb-3">
                            Comparing counter terms to: <strong>{{ $baselineLabel }}</strong>
                        </p>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="p-2 bg-white rounded" style="border-left: 4px solid {{ $getScoreColor($brokerScore) }};">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="small fw-semibold">Broker Compensation</span>
                                        <span class="badge" style="background: {{ $getScoreColor($brokerScore) }};">{{ $brokerScore }}%</span>
                                    </div>
                                    <div class="small text-muted mt-1">{{ $brokerMatched }}/{{ $brokerTotal }} fields match</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-2 bg-white rounded" style="border-left: 4px solid {{ $getScoreColor($servicesScore) }};">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="small fw-semibold">Offered Services</span>
                                        <span class="badge" style="background: {{ $getScoreColor($servicesScore) }};">{{ $servicesScore }}%</span>
                                    </div>
                                    <div class="small text-muted mt-1">{{ $servicesMatched }}/{{ $servicesTotal }} services match</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Counter Terms Details --}}
                    <div class="mb-4">
                        <h5 style="color: #049399; font-weight: 600; border-bottom: 2px solid #049399; padding-bottom: 8px;">
                            <i class="fa fa-handshake me-2"></i>Counter Terms Details
                        </h5>
                        <p class="text-muted small">
                            Submitted: {{ $sellerCounter->created_at->format('M d, Y h:i A') }}
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
                                <li class="mb-1">
                                    <span class="fw-semibold">Nominal Consideration Fee:</span> {{ $fmtMoney($ctNominal) ?? '-' }}
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
                            </ul>
                        </div>
                        @endif

                        {{-- C) Lease-Option Terms --}}
                        @if($ctLeaseOption)
                        <div class="mb-4">
                            <h6 class="mb-2" style="color: #049399; font-weight: 600;">C) Lease-Option Terms</h6>
                            <ul class="list-unstyled ps-3 mb-0">
                                <li class="mb-1"><span class="fw-semibold">Interested in Lease-Option Agreement:</span> {{ $ctLeaseOption }}</li>
                            </ul>
                        </div>
                        @endif

                        {{-- D) Legal Terms --}}
                        @if($ctEarlyTermOpt || $ctRetainerOpt || $ctRetainedDep || $ctProtPeriod || $ctAgencyTf)
                        <div class="mb-4">
                            <h6 class="mb-2" style="color: #049399; font-weight: 600;">D) Legal Terms</h6>
                            <ul class="list-unstyled ps-3 mb-0">
                                @if($ctEarlyTermOpt)
                                <li class="mb-1">
                                    <span class="fw-semibold">Early Termination Fee:</span>
                                    {{ ucfirst($ctEarlyTermOpt) }}{{ strtolower($ctEarlyTermOpt) === 'yes' && $ctEarlyTermAmt ? ' (' . ($fmtMoney($ctEarlyTermAmt) ?? $ctEarlyTermAmt) . ')' : '' }}
                                </li>
                                @endif
                                @if($ctRetainerOpt)
                                <li class="mb-1">
                                    <span class="fw-semibold">Retainer Fee:</span>
                                    {{ ucfirst($ctRetainerOpt) }}{{ strtolower($ctRetainerOpt) === 'yes' && $ctRetainerAmt ? ' (' . ($fmtMoney($ctRetainerAmt) ?? $ctRetainerAmt) . ')' : '' }}
                                </li>
                                @if(strtolower($ctRetainerOpt) === 'yes' && $ctRetainerApp)
                                <li class="mb-1 ps-3"><span class="fw-semibold">Retainer Fee Application:</span> {{ $ctRetainerApp }}</li>
                                @endif
                                @endif
                                @if($ctRetainedDep)
                                <li class="mb-1">
                                    <span class="fw-semibold">Seller's Broker's Share of Retained Deposits:</span> {{ $fmtPercent($ctRetainedDep) ?? $ctRetainedDep }}
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

                        {{-- F) Additional Terms --}}
                        @if($ctAddlDetails)
                        <div class="mb-4">
                            <h6 class="mb-2" style="color: #049399; font-weight: 600;">F) Additional Terms</h6>
                            <div class="ps-3 text-muted">{{ $ctAddlDetails }}</div>
                        </div>
                        @endif

                        {{-- Services in Counter (catalog-filtered for property type) --}}
                        @php
                            $ctPropType  = $auction->get->property_type ?? 'Residential Property';
                            $ctCatalog   = \App\Helpers\SellerBidMatchScoreHelper::getCatalog($ctPropType);
                            $ctServices  = $counterData['services'] ?? [];
                            if (is_string($ctServices)) { $ctServices = json_decode($ctServices, true) ?? []; }
                            $ctServices  = array_filter((array)$ctServices, function($s) use ($ctCatalog) {
                                return !empty(trim((string)$s))
                                    && $s !== 'Other'
                                    && in_array(\App\Helpers\SellerBidMatchScoreHelper::normalizeService((string)$s), $ctCatalog, true);
                            });
                            $ctOtherSvcs = $counterData['other_services'] ?? [];
                            if (is_string($ctOtherSvcs)) { $ctOtherSvcs = json_decode($ctOtherSvcs, true) ?? []; }
                            $ctOtherSvcs = array_filter((array)$ctOtherSvcs, fn($s) => is_string($s) && !empty(trim($s)));
                            $hasCtServices = !empty($ctServices) || !empty($ctOtherSvcs);
                        @endphp
                        @if($hasCtServices)
                        <div class="mb-4">
                            <h6 class="mb-2" style="color: #049399; font-weight: 600;">G) Offered Services in Counter</h6>
                            @if(!empty($ctServices))
                            <ul class="ps-3 mb-2">
                                @foreach($ctServices as $svc)
                                <li style="font-size: 0.9rem; margin-bottom: 4px;">{{ $svc }}</li>
                                @endforeach
                            </ul>
                            @endif
                            @if(!empty($ctOtherSvcs))
                            <div class="fw-semibold small" style="color: #34465c;">Additional Services:</div>
                            <ul class="ps-3 mb-0">
                                @foreach($ctOtherSvcs as $svc)
                                <li style="font-size: 0.9rem; margin-bottom: 4px;">{{ $svc }}</li>
                                @endforeach
                            </ul>
                            @endif
                        </div>
                        @endif
                    </div>

                    {{-- Agent Counter-Back Section --}}
                    @if($agentCounterBack)
                    @php
                        $agentCounterData = (array) $agentCounterBack->get;
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
                        <p class="mb-0"><span class="fw-semibold">Additional Details:</span> {{ data_get($agentCounterData, 'additional_details_broker') }}</p>
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
