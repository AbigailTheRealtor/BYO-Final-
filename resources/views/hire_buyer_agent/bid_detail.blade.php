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

@php
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
@endphp

@section('content')
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
                    @php
                        $bidStatus = $bid->bid_status ?? 'Active';
                        $statusStyles = [
                            'Countered' => 'background-color:#ffc107;color:#000;',
                            'Active'    => 'background-color:#007bff;color:#fff;',
                            'Accepted'  => 'background-color:#28a745;color:#fff;',
                            'Rejected'  => 'background-color:#dc3545;color:#fff;',
                        ];
                    @endphp
                    <span class="status-badge" style="{{ $statusStyles[$bidStatus] ?? $statusStyles['Active'] }}">
                        {{ $bidStatus }}
                    </span>
                </div>
            </div>
        </div>

        <div class="bid-preview-body">
            @php
                $auctionBaselineData = json_decode(json_encode($auction->get ?? []), true) ?: [];
                $bidData             = json_decode(json_encode($bid->get ?? []), true) ?: [];
                $propType            = $auction->get->property_type ?? 'Residential Property';

                $matchScore = \App\Helpers\BuyerBidMatchScoreHelper::calculate(
                    $auctionBaselineData, $bidData, null, $propType
                );

                $totalScore          = $matchScore['overall_percent'];
                $brokerScore         = $matchScore['terms_match_percent'] ?? $matchScore['broker_comp_percent'] ?? 0;
                $brokerMatched       = $matchScore['terms_matched_count'] ?? 0;
                $brokerTotal         = $matchScore['terms_baseline_total'] ?? 0;
                $brokerMismatches    = $matchScore['changed_terms'] ?? [];
                $servicesScore       = $matchScore['services_match_percent'] ?? $matchScore['services_percent'] ?? 0;
                $servicesMatched     = $matchScore['services_matched_count'] ?? 0;
                $servicesTotal       = $matchScore['services_baseline_total'] ?? 0;
                $servicesExtraCount  = $matchScore['services_extra_count'] ?? 0;
                $servicesMatchedList = $matchScore['matched_services'] ?? [];
                $servicesAdded       = $matchScore['extra_services'] ?? [];
                $servicesMissing     = $matchScore['missing_services'] ?? [];

                $getScoreColor = function($score) {
                    if ($score >= 80) return '#28a745';
                    if ($score >= 50) return '#ffc107';
                    return '#dc3545';
                };

                $mismatchStyle = 'background-color:#ffe6e6;padding:2px 6px;border-radius:4px;border-left:3px solid #dc3545;';
                $mismatchBadge = '<span class="badge bg-danger ms-2" style="font-size:0.7rem;vertical-align:middle;">Mismatch</span>';

                $isListingOwner = Auth::id() === (int)$auction->user_id;
                $hasAnyBaseline = ($brokerTotal > 0 || $servicesTotal > 0);
            @endphp

            {{-- Agent Summary Strip --}}
            <div class="d-flex align-items-center gap-3 mb-4 p-3 rounded" style="background:#f8f9fa;">
                <div class="rounded-circle d-flex align-items-center justify-content-center me-2"
                     style="width:50px;height:50px;background:{{ $getScoreColor($totalScore) }};color:#fff;font-weight:700;font-size:1.1rem;flex-shrink:0;">
                    {{ $totalScore }}%
                </div>
                <div>
                    <div class="fw-bold">{{ $bid->user->name ?? 'Agent' }}</div>
                    <small class="text-muted">{{ $bid->user->email ?? '' }}</small>
                </div>
                <div class="ms-auto d-flex gap-3">
                    <div class="text-center">
                        <small class="text-muted d-block">Broker Terms</small>
                        <span style="color:{{ $getScoreColor($brokerScore) }};font-weight:600;">{{ $brokerScore }}%</span>
                        <small class="text-muted">{{ $brokerTotal > 0 ? '('.$brokerMatched.'/'.$brokerTotal.')' : 'No terms' }}</small>
                    </div>
                    <div class="text-center">
                        <small class="text-muted d-block">Services</small>
                        <span style="color:{{ $getScoreColor($servicesScore) }};font-weight:600;">{{ $servicesScore }}%</span>
                        <small class="text-muted">{{ $servicesTotal > 0 ? '('.$servicesMatched.'/'.$servicesTotal.')' : 'No services' }}</small>
                    </div>
                </div>
            </div>
            <p class="small text-muted mb-4">Comparing to: <strong>Your Listing Terms</strong></p>

            {{-- ===== SECTION 1: AGENT OVERVIEW ===== --}}
            <div class="mb-5">
                <h6 class="section-header"><i class="fa fa-user-tie me-2"></i>Agent Overview &amp; Qualifications</h6>

                @if (data_get($bid, 'get.bio'))
                <div class="mb-3">
                    <div class="field-label" style="color:#049399;">About Agent:</div>
                    <div class="field-value">{{ data_get($bid, 'get.bio') }}</div>
                </div>
                @endif

                @if (data_get($bid, 'get.why_hire_you'))
                <div class="mb-3">
                    <div class="field-label" style="color:#049399;">Why Hire This Agent:</div>
                    <div class="field-value">{{ data_get($bid, 'get.why_hire_you') }}</div>
                </div>
                @endif

                @if (data_get($bid, 'get.what_sets_you_apart'))
                <div class="mb-3">
                    <div class="field-label" style="color:#049399;">What Sets This Agent Apart:</div>
                    <div class="field-value">{{ data_get($bid, 'get.what_sets_you_apart') }}</div>
                </div>
                @endif

                @if (data_get($bid, 'get.marketing_plan'))
                <div class="mb-3">
                    <div class="field-label" style="color:#049399;">Marketing Strategy:</div>
                    <div class="field-value">{{ data_get($bid, 'get.marketing_plan') }}</div>
                </div>
                @endif

                @php
                    $buyerReviewLinks = data_get($bid, 'get.reviews_links', []);
                    if (!is_array($buyerReviewLinks)) { $buyerReviewLinks = (array)$buyerReviewLinks; }
                    $hasBuyerReviewLinks = !empty(array_filter($buyerReviewLinks, fn($rl) => !empty(is_object($rl) ? $rl->url : ($rl['url'] ?? ''))));
                @endphp
                @if ($hasBuyerReviewLinks)
                <div class="mb-3">
                    <div class="field-label" style="color:#049399;">Review Links:</div>
                    @foreach ($buyerReviewLinks as $rl)
                    @php $rlUrl = is_object($rl) ? $rl->url : ($rl['url'] ?? ''); $rlText = is_object($rl) ? ($rl->text ?? '') : ($rl['text'] ?? ''); @endphp
                    @if (!empty($rlUrl))
                    <div class="mb-1">
                        @php if (!str_starts_with($rlUrl, 'http')) { $rlUrl = 'https://'.$rlUrl; } @endphp
                        <a href="{{ $rlUrl }}" target="_blank" class="text-primary text-decoration-none"><i class="fa fa-external-link-alt me-1"></i>{{ $rlText ?: $rlUrl }}</a>
                    </div>
                    @endif
                    @endforeach
                </div>
                @endif

                @if (data_get($bid, 'get.website_link'))
                <div class="mb-3">
                    <div class="field-label" style="color:#049399;">Website:</div>
                    @php $wl = data_get($bid,'get.website_link'); if(!str_starts_with($wl,'http')){$wl='https://'.$wl;} @endphp
                    <a href="{{ $wl }}" target="_blank" class="text-primary text-decoration-none"><i class="fa fa-globe me-1"></i>Visit Website</a>
                </div>
                @endif

                @if (data_get($bid, 'get.social_media'))
                <div class="mb-3">
                    <div class="field-label" style="color:#049399;">Social Media:</div>
                    @foreach (data_get($bid, 'get.social_media') as $social)
                    @php $sa=(array)$social; @endphp
                    @if (!empty($sa['platform']) && !empty($sa['url']))
                    <div class="mb-1">
                        @php if(!str_starts_with($sa['url'],'http')){$sa['url']='https://'.$sa['url'];} @endphp
                        <a href="{{ $sa['url'] }}" target="_blank" class="text-primary text-decoration-none">
                            <i class="fab fa-{{ strtolower($sa['platform']) }} me-1"></i>{{ $sa['text'] ?? $sa['platform'] }}
                        </a>
                    </div>
                    @endif
                    @endforeach
                </div>
                @endif

                @if (data_get($bid, 'get.year_licensed'))
                <div class="mb-3">
                    <div class="field-label" style="color:#049399;">Year Licensed:</div>
                    <div class="field-value">{{ data_get($bid, 'get.year_licensed') }}</div>
                </div>
                @endif
            </div>

            {{-- ===== SECTION 2: BUYER BROKER COMPENSATION ===== --}}
            @php
                $bidCommStruct       = data_get($bid, 'get.commission_structure');
                $bidPurchaseFeeType  = data_get($bid, 'get.purchase_fee_type');
                $bidCommStructType   = data_get($bid, 'get.commission_structure_type');
                $bidInterestedLease  = data_get($bid, 'get.interested_lease_option');
                $bidLeaseFeeTy       = data_get($bid, 'get.lease_fee_type');
                $bidLeaseOption      = data_get($bid, 'get.interested_lease_option_agreement');
                $bidProtPeriod       = data_get($bid, 'get.protection_period');
                $bidEarlyTermOpt     = data_get($bid, 'get.early_termination_fee_option');
                $bidRetainerOpt      = data_get($bid, 'get.retainer_fee_option');
                $bidAgencyTf         = data_get($bid, 'get.agency_agreement_timeframe');
                $bidBrokerRelation   = data_get($bid, 'get.brokerage_relationship');
                $bidAddlBroker       = data_get($bid, 'get.additional_details_broker');

                // Build buyer broker commission fee display
                $bidBuyerBrokerFee = null;
                if ($bidCommStructType === 'Flat Fee' && data_get($bid,'get.commission_structure_type_fee_flat')) {
                    $bidBuyerBrokerFee = $fmtMoney(data_get($bid,'get.commission_structure_type_fee_flat'));
                } elseif ($bidCommStructType === 'Percentage of the Total Purchase Price' && data_get($bid,'get.commission_structure_type_fee_percentage')) {
                    $bidBuyerBrokerFee = ($fmtPercent(data_get($bid,'get.commission_structure_type_fee_percentage')) ?? '-') . ' of Total Purchase Price';
                } elseif ($bidCommStructType === 'Flat Fee + Percentage' && (data_get($bid,'get.commission_structure_type_fee_flat_combo') || data_get($bid,'get.commission_structure_type_fee_percentage_combo'))) {
                    $bbfParts = [];
                    if (data_get($bid,'get.commission_structure_type_fee_percentage_combo')) $bbfParts[] = ($fmtPercent(data_get($bid,'get.commission_structure_type_fee_percentage_combo')) ?? '') . ' of Total Purchase Price';
                    if (data_get($bid,'get.commission_structure_type_fee_flat_combo')) $bbfParts[] = $fmtMoney(data_get($bid,'get.commission_structure_type_fee_flat_combo'));
                    $bidBuyerBrokerFee = implode(' + ', array_filter($bbfParts));
                } elseif (strtolower($bidCommStructType ?? '') === 'other' && data_get($bid,'get.commission_structure_type_fee_other')) {
                    $bidBuyerBrokerFee = data_get($bid,'get.commission_structure_type_fee_other');
                } elseif ($bidCommStructType) {
                    $bidBuyerBrokerFee = $bidCommStructType;
                }

                // Lease fee display
                $bidLeaseFeeDisplay = null;
                if ($bidLeaseFeeTy === 'Flat Fee' && data_get($bid,'get.lease_flat_fee')) {
                    $bidLeaseFeeDisplay = $fmtMoney(data_get($bid,'get.lease_flat_fee'));
                } elseif ($bidLeaseFeeTy === 'Percentage of the Gross Lease Value' && data_get($bid,'get.lease_percentage_gross')) {
                    $bidLeaseFeeDisplay = ($fmtPercent(data_get($bid,'get.lease_percentage_gross')) ?? '-') . ' of the Gross Lease Value';
                } elseif ($bidLeaseFeeTy === 'Percentage of the Rent Due Each Rental Period' && data_get($bid,'get.lease_percentage_rental')) {
                    $bidLeaseFeeDisplay = ($fmtPercent(data_get($bid,'get.lease_percentage_rental')) ?? '-') . ' of the Rent Due Each Rental Period';
                } elseif ($bidLeaseFeeTy) {
                    $bidLeaseFeeDisplay = $bidLeaseFeeTy;
                }

                // Lease-option fee display
                $bidLeaseType    = data_get($bid, 'get.lease_type');
                $bidLeaseValue   = data_get($bid, 'get.lease_value');
                $bidPurchaseType = data_get($bid, 'get.purchase_type');
                $bidPurchaseValue= data_get($bid, 'get.purchase_value');
                $bidLeaseOptionFee = null;
                if ($bidLeaseValue) {
                    if (in_array($bidLeaseType, ['%','percent']) || str_contains((string)$bidLeaseValue,'%')) {
                        $bidLeaseOptionFee = str_replace('%','',$bidLeaseValue).'% of Total Purchase Price';
                    } else {
                        $bidLeaseOptionFee = $fmtMoney($bidLeaseValue);
                    }
                }
                $bidPurchaseOptFee = null;
                if ($bidPurchaseValue) {
                    if (in_array($bidPurchaseType, ['%','percent']) || str_contains((string)$bidPurchaseValue,'%')) {
                        $bidPurchaseOptFee = str_replace('%','',$bidPurchaseValue).'% of Total Purchase Price';
                    } else {
                        $bidPurchaseOptFee = $fmtMoney($bidPurchaseValue);
                    }
                }

                $bidEarlyTermAmt  = data_get($bid, 'get.early_termination_fee_amount');
                $bidRetainerAmt   = data_get($bid, 'get.retainer_fee_amount');
                $bidRetainerApp   = data_get($bid, 'get.retainer_fee_application');
                $bidAgencyCus     = data_get($bid, 'get.agency_agreement_custom');
                $bidAgencyDsp     = strtolower(trim($bidAgencyTf ?? '')) === 'other' ? ($bidAgencyCus ?: 'Other') : ($bidAgencyTf ?: '');

                $showBidBrokerComp = $bidCommStruct || $bidPurchaseFeeType || $bidCommStructType;
                $showLeaseTerms    = strtolower(trim($bidInterestedLease ?? '')) === 'yes';
                $showLeaseOption   = strtolower(trim($bidLeaseOption ?? '')) === 'yes';
                $showLegalTerms    = $bidEarlyTermOpt || $bidRetainerOpt || $bidProtPeriod || $bidAgencyTf;
                $hasBrokerSection  = $showBidBrokerComp || $bidInterestedLease || $bidLeaseOption || $showLegalTerms || $bidBrokerRelation || $bidAddlBroker;
            @endphp

            @if ($hasBrokerSection)
            <div class="mb-5">
                <h6 class="section-header"><i class="fa fa-handshake me-2"></i>Broker Compensation &amp; Agency Agreement Terms</h6>

                {{-- A) Buyer Broker Compensation --}}
                @if ($showBidBrokerComp)
                <div class="mb-4">
                    <h6 class="subsection-heading">A) Buyer's Broker Compensation</h6>
                    <ul class="list-unstyled ps-3 mb-0">
                        @if ($bidPurchaseFeeType)
                        <li class="mb-1" style="{{ isset($brokerMismatches['purchase_fee_type']) ? $mismatchStyle : '' }}">
                            <span class="fw-semibold">Buyer's Broker Purchase Fee Type:</span> {{ $bidPurchaseFeeType }}
                            {!! isset($brokerMismatches['purchase_fee_type']) ? $mismatchBadge : '' !!}
                        </li>
                        @endif
                        @if ($bidCommStruct)
                        <li class="mb-1" style="{{ isset($brokerMismatches['commission_structure']) ? $mismatchStyle : '' }}">
                            <span class="fw-semibold">Buyer's Broker Commission Structure:</span> {{ $bidCommStruct }}
                            {!! isset($brokerMismatches['commission_structure']) ? $mismatchBadge : '' !!}
                        </li>
                        @endif
                        @if ($bidBuyerBrokerFee)
                        <li class="mb-1" style="{{ isset($brokerMismatches['commission_structure_type']) ? $mismatchStyle : '' }}">
                            <span class="fw-semibold">Buyer's Broker Commission Fee:</span> {{ $bidBuyerBrokerFee }}
                            {!! isset($brokerMismatches['commission_structure_type']) ? $mismatchBadge : '' !!}
                        </li>
                        @endif
                    </ul>
                </div>
                @endif

                {{-- B) Lease Terms --}}
                @if ($bidInterestedLease)
                <div class="mb-4">
                    <h6 class="subsection-heading">B) Lease Terms</h6>
                    <ul class="list-unstyled ps-3 mb-0">
                        <li class="mb-1" style="{{ isset($brokerMismatches['interested_lease_option']) ? $mismatchStyle : '' }}">
                            <span class="fw-semibold">Interested in Offering a Lease Agreement:</span> {{ $bidInterestedLease }}
                            {!! isset($brokerMismatches['interested_lease_option']) ? $mismatchBadge : '' !!}
                        </li>
                        @if ($showLeaseTerms && $bidLeaseFeeDisplay)
                        <li class="mb-1" style="{{ isset($brokerMismatches['lease_fee_type']) ? $mismatchStyle : '' }}">
                            <span class="fw-semibold">Buyer's Broker Leasing Fee:</span> {{ $bidLeaseFeeDisplay }}
                            {!! isset($brokerMismatches['lease_fee_type']) ? $mismatchBadge : '' !!}
                        </li>
                        @endif
                    </ul>
                </div>
                @endif

                {{-- C) Lease-Option Terms --}}
                @if ($bidLeaseOption)
                <div class="mb-4">
                    <h6 class="subsection-heading">C) Lease-Option Terms</h6>
                    <ul class="list-unstyled ps-3 mb-0">
                        <li class="mb-1" style="{{ isset($brokerMismatches['interested_lease_option_agreement']) ? $mismatchStyle : '' }}">
                            <span class="fw-semibold">Interested in Lease-Option Agreement:</span> {{ $bidLeaseOption }}
                            {!! isset($brokerMismatches['interested_lease_option_agreement']) ? $mismatchBadge : '' !!}
                        </li>
                        @if ($showLeaseOption && $bidLeaseOptionFee)
                        <li class="mb-1" style="{{ (isset($brokerMismatches['lease_type']) || isset($brokerMismatches['lease_value'])) ? $mismatchStyle : '' }}">
                            <span class="fw-semibold">Compensation for Lease-Option Agreement:</span> {{ $bidLeaseOptionFee }}
                            {!! (isset($brokerMismatches['lease_type']) || isset($brokerMismatches['lease_value'])) ? $mismatchBadge : '' !!}
                        </li>
                        @endif
                        @if ($showLeaseOption && $bidPurchaseOptFee)
                        <li class="mb-1" style="{{ (isset($brokerMismatches['purchase_type']) || isset($brokerMismatches['purchase_value'])) ? $mismatchStyle : '' }}">
                            <span class="fw-semibold">Compensation if Purchase Option Exercised:</span> {{ $bidPurchaseOptFee }}
                            {!! (isset($brokerMismatches['purchase_type']) || isset($brokerMismatches['purchase_value'])) ? $mismatchBadge : '' !!}
                        </li>
                        @endif
                    </ul>
                </div>
                @endif

                {{-- D) Legal Terms --}}
                @if ($showLegalTerms)
                <div class="mb-4">
                    <h6 class="subsection-heading">D) Legal Terms</h6>
                    <ul class="list-unstyled ps-3 mb-0">
                        @if ($bidEarlyTermOpt)
                        <li class="mb-1" style="{{ isset($brokerMismatches['early_termination_fee_option']) ? $mismatchStyle : '' }}">
                            <span class="fw-semibold">Early Termination Fee:</span> {{ ucfirst($bidEarlyTermOpt) }}
                            {!! isset($brokerMismatches['early_termination_fee_option']) ? $mismatchBadge : '' !!}
                        </li>
                        @if (strtolower($bidEarlyTermOpt) === 'yes' && $bidEarlyTermAmt)
                        <li class="mb-1" style="{{ isset($brokerMismatches['early_termination_fee_amount']) ? $mismatchStyle : '' }}">
                            <span class="fw-semibold">Termination Fee Amount:</span> {{ $fmtMoney($bidEarlyTermAmt) }}
                            {!! isset($brokerMismatches['early_termination_fee_amount']) ? $mismatchBadge : '' !!}
                        </li>
                        @endif
                        @endif
                        @if ($bidRetainerOpt)
                        <li class="mb-1" style="{{ isset($brokerMismatches['retainer_fee_option']) ? $mismatchStyle : '' }}">
                            <span class="fw-semibold">Retainer Fee:</span> {{ ucfirst($bidRetainerOpt) }}
                            {!! isset($brokerMismatches['retainer_fee_option']) ? $mismatchBadge : '' !!}
                        </li>
                        @if (strtolower($bidRetainerOpt) === 'yes' && $bidRetainerAmt)
                        <li class="mb-1" style="{{ isset($brokerMismatches['retainer_fee_amount']) ? $mismatchStyle : '' }}">
                            <span class="fw-semibold">Retainer Fee Amount:</span> {{ $fmtMoney($bidRetainerAmt) }}
                            {!! isset($brokerMismatches['retainer_fee_amount']) ? $mismatchBadge : '' !!}
                        </li>
                        @endif
                        @if (strtolower($bidRetainerOpt) === 'yes' && $bidRetainerApp)
                        <li class="mb-1" style="{{ isset($brokerMismatches['retainer_fee_application']) ? $mismatchStyle : '' }}">
                            <span class="fw-semibold">Retainer Fee Application:</span> {{ is_array($bidRetainerApp) ? implode(', ', $bidRetainerApp) : $bidRetainerApp }}
                            {!! isset($brokerMismatches['retainer_fee_application']) ? $mismatchBadge : '' !!}
                        </li>
                        @endif
                        @endif
                        @if ($bidProtPeriod)
                        <li class="mb-1" style="{{ isset($brokerMismatches['protection_period']) ? $mismatchStyle : '' }}">
                            <span class="fw-semibold">Protection Period Timeframe:</span> {{ $bidProtPeriod }} days
                            {!! isset($brokerMismatches['protection_period']) ? $mismatchBadge : '' !!}
                        </li>
                        @endif
                        @if ($bidAgencyTf)
                        <li class="mb-1" style="{{ isset($brokerMismatches['agency_agreement_timeframe']) ? $mismatchStyle : '' }}">
                            <span class="fw-semibold">Buyer Agency Agreement Timeframe:</span> {{ $bidAgencyDsp }}
                            {!! isset($brokerMismatches['agency_agreement_timeframe']) ? $mismatchBadge : '' !!}
                        </li>
                        @endif
                    </ul>
                </div>
                @endif

                {{-- E) Brokerage Relationship --}}
                @if ($bidBrokerRelation)
                <div class="mb-4">
                    <h6 class="subsection-heading">E) Brokerage Relationship</h6>
                    <ul class="list-unstyled ps-3 mb-0">
                        <li class="mb-1" style="{{ isset($brokerMismatches['brokerage_relationship']) ? $mismatchStyle : '' }}">
                            <span class="fw-semibold">Acceptable Brokerage Relationship:</span> {{ $bidBrokerRelation }}
                            {!! isset($brokerMismatches['brokerage_relationship']) ? $mismatchBadge : '' !!}
                        </li>
                    </ul>
                </div>
                @endif

                {{-- F) Additional Terms --}}
                @if ($bidAddlBroker)
                <div class="mb-4">
                    <h6 class="subsection-heading">F) Additional Terms</h6>
                    <ul class="list-unstyled ps-3 mb-0">
                        <li class="mb-1"><span class="fw-semibold">Additional Terms:</span> {{ $bidAddlBroker }}</li>
                    </ul>
                </div>
                @endif
            </div>
            @endif

            {{-- ===== ADDITIONAL DETAILS ===== --}}
            @if (data_get($bid, 'get.additional_details'))
            <div class="mb-5">
                <h6 class="section-header"><i class="fa fa-info-circle me-2"></i>Additional Details</h6>
                <div class="field-value" style="font-style:italic;">{{ data_get($bid, 'get.additional_details') }}</div>
            </div>
            @endif

            {{-- ===== SECTION 3: SERVICES ===== --}}
            <div class="mb-5">
                <h6 class="section-header"><i class="fa fa-list-check me-2"></i>Offered Services</h6>
                @if (count($servicesMatchedList) === 0 && count($servicesAdded) === 0 && count($servicesMissing) === 0)
                <p class="text-muted">No services data available.</p>
                @else
                <div class="row">
                    @if (count($servicesMatchedList) > 0)
                    <div class="col-md-4 mb-3">
                        <div class="p-3 rounded" style="background:#e6f7e6;border:1px solid #c3e6c3;">
                            <h6 class="mb-2" style="color:#28a745;"><i class="fas fa-check-circle me-1"></i>Matched ({{ count($servicesMatchedList) }})</h6>
                            <ul class="services-list mb-0">
                                @foreach($servicesMatchedList as $svc)
                                <li class="service-matched"><i class="fas fa-check me-1"></i>{{ ucfirst($svc) }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                    @endif
                    @if (count($servicesAdded) > 0)
                    <div class="col-md-4 mb-3">
                        <div class="p-3 rounded" style="background:#e6f7ff;border:1px solid #91d5ff;">
                            <h6 class="mb-2" style="color:#17a2b8;"><i class="fas fa-plus-circle me-1"></i>Extra Added ({{ count($servicesAdded) }})</h6>
                            <ul class="services-list mb-0">
                                @foreach($servicesAdded as $svc)
                                <li class="service-extra"><i class="fas fa-plus me-1"></i>{{ ucfirst($svc) }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                    @endif
                    @if (count($servicesMissing) > 0)
                    <div class="col-md-4 mb-3">
                        <div class="p-3 rounded" style="background:#fff2e8;border:1px solid #ffbb96;">
                            <h6 class="mb-2" style="color:#dc3545;"><i class="fas fa-minus-circle me-1"></i>Not Offered ({{ count($servicesMissing) }})</h6>
                            <ul class="services-list mb-0">
                                @foreach($servicesMissing as $svc)
                                <li class="service-missing"><i class="fas fa-times me-1"></i>{{ ucfirst($svc) }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                    @endif
                </div>
                @endif
            </div>

            {{-- ===== PRESENTATION MATERIALS ===== --}}
            @if (data_get($bid, 'get.presentation_link') || data_get($bid, 'get.business_card_link'))
            <div class="mb-5">
                <h6 class="section-header"><i class="fa fa-photo-video me-2"></i>Agent Presentation &amp; Promotional Materials</h6>
                @if (data_get($bid, 'get.presentation_link'))
                <div class="mb-3">
                    <div class="field-label" style="color:#049399;">Video Presentation:</div>
                    @php $pl=data_get($bid,'get.presentation_link'); if(!str_starts_with($pl,'http')){$pl='https://'.$pl;} @endphp
                    <a href="{{ $pl }}" target="_blank" class="text-primary text-decoration-none"><i class="fa fa-external-link-alt me-1"></i>Watch Presentation</a>
                </div>
                @endif
                @if (data_get($bid, 'get.business_card_link'))
                <div class="mb-3">
                    <div class="field-label" style="color:#049399;">Business Card:</div>
                    @php $bcl=data_get($bid,'get.business_card_link'); if(!str_starts_with($bcl,'http')){$bcl='https://'.$bcl;} @endphp
                    <a href="{{ $bcl }}" target="_blank" class="btn btn-outline-primary btn-sm"><i class="fa fa-external-link-alt me-1"></i>View Business Card</a>
                </div>
                @endif
            </div>
            @endif

        </div>{{-- end bid-preview-body --}}

        {{-- ===== ACTION BUTTONS ===== --}}
        @if ($isListingOwner && $bidStatus === 'Active')
        <div class="action-buttons d-flex gap-2 flex-wrap">
            <form action="{{ route('buyer.hire.agent.auction.bid.accept') }}" method="POST" class="d-inline">
                @csrf
                <input type="hidden" name="bid_id" value="{{ $bid->id }}">
                <input type="hidden" name="auction_id" value="{{ $auction->id }}">
                <button type="submit" class="btn" style="background:#28a745;color:#fff;border:none;" onclick="return confirm('Accept this bid? This will hire the agent.')">
                    <i class="fas fa-check me-1"></i>Accept Bid
                </button>
            </form>
            <a href="{{ route('agent.buyer.hire.agent.auction.counter-bid', [$auction->id, $bid->id]) }}" class="btn" style="background:#ffc107;color:#000;border:none;">
                <i class="fas fa-exchange-alt me-1"></i>Counter Bid
            </a>
            <form action="{{ route('buyer.hire.agent.auction.bid.reject') }}" method="POST" class="d-inline">
                @csrf
                <input type="hidden" name="bid_id" value="{{ $bid->id }}">
                <input type="hidden" name="auction_id" value="{{ $auction->id }}">
                <button type="submit" class="btn" style="background:#dc3545;color:#fff;border:none;" onclick="return confirm('Reject this bid?')">
                    <i class="fas fa-times me-1"></i>Reject Bid
                </button>
            </form>
        </div>
        @elseif ($isListingOwner && $bidStatus === 'Countered')
        <div class="action-buttons">
            <span class="btn" style="background:#fff3cd;color:#856404;border:1px solid #ffc107;">
                <i class="fas fa-clock me-1"></i>Awaiting Agent Response
            </span>
        </div>
        @elseif ($isListingOwner && $bidStatus === 'Accepted')
        <div class="action-buttons">
            <a href="{{ route('buyer.view-auction', $auction->id) }}" class="btn" style="background:#28a745;color:#fff;border:none;">
                <i class="fas fa-file-contract me-1"></i>View Summary
            </a>
        </div>
        @endif

    </div>{{-- end bid-preview-card --}}
</div>
@endsection
