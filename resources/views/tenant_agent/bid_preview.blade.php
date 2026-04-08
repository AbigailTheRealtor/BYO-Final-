{{--
    QA LOCK — BID COMPARISON SCORING

    This file previously used inline scoring logic (union-based).
    It has been replaced with helper-based scoring per Task #31 QA pass.

    RULES:
    - DO NOT reintroduce inline scoring logic
    - All scoring must come from BidMatchScoreHelper classes
    - Any scoring changes must be applied across ALL four roles

    Reference:
    qa_reports/QA_LOCK_BidComparison_v1.md
--}}
@extends('layouts.main')

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

  $joinParts = function($parts) {
    $parts = array_values(array_filter($parts, fn($p) => $p !== null && $p !== ''));
    return count($parts) ? implode(' + ', $parts) : null;
  };

  $basisText = function($basis) {
    return $basis ? ('of ' . $basis) : null;
  };
@endphp

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/listingDescription.css') }}" />
<style>
    .bid-preview-header {
        background: linear-gradient(135deg, #049399 0%, #037a7f 100%);
        color: white;
        padding: 25px 30px;
        border-radius: 12px 12px 0 0;
    }
    .bid-preview-card {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        margin-bottom: 30px;
    }
    .bid-preview-body {
        padding: 30px;
    }
    .section-header {
        color: #049399;
        font-weight: 600;
        border-bottom: 2px solid #049399;
        padding-bottom: 10px;
        margin-bottom: 20px;
        font-size: 1.1rem;
    }
    .field-label {
        font-weight: 600;
        color: #34465c;
    }
    .field-value {
        color: #34465c;
    }
    .status-badge {
        padding: 8px 16px;
        border-radius: 6px;
        font-weight: 600;
        font-size: 0.9rem;
    }
    .match-score-badge {
        padding: 8px 16px;
        border-radius: 6px;
        font-weight: 600;
        font-size: 0.9rem;
        color: white;
    }
    .action-buttons {
        background: #f8f9fa;
        border-top: 1px solid #e9ecef;
        padding: 20px 30px;
        border-radius: 0 0 12px 12px;
    }
    .back-link {
        color: #049399;
        text-decoration: none;
        font-weight: 500;
    }
    .back-link:hover {
        color: #037a7f;
        text-decoration: underline;
    }
    .mismatch-highlight {
        background-color: #ffe6e6;
        padding: 2px 6px;
        border-radius: 4px;
        border-left: 3px solid #dc3545;
    }
    .services-list {
        list-style: none;
        padding-left: 0;
    }
    .services-list li {
        padding: 6px 0;
        border-bottom: 1px solid #f0f0f0;
    }
    .services-list li:last-child {
        border-bottom: none;
    }
    .service-matched {
        color: #28a745;
    }
    .service-extra {
        color: #17a2b8;
    }
    .service-missing {
        color: #dc3545;
    }
</style>
@endpush

@section('content')
<div class="container py-4">
    <div class="mb-3">
        <a href="{{ route('myBids') }}" class="back-link">
            <i class="fas fa-arrow-left me-2"></i>Back to Agent Bids
        </a>
    </div>

    <div class="bid-preview-card">
        <div class="bid-preview-header">
            <div class="d-flex justify-content-between align-items-start flex-wrap">
                <div>
                    <h4 class="mb-2"><i class="fas fa-user-tie me-2"></i>Full Agent Bid Preview</h4>
                    <div class="opacity-75">
                        <span class="me-3"><i class="fas fa-home me-1"></i>Hire a Tenant's Agent</span>
                        <span><i class="fas fa-tag me-1"></i>Listing ID: {{ $listingId }}</span>
                    </div>
                </div>
                <div class="text-end mt-2 mt-md-0">
                    @php
                        $bidStatus = $bid->bid_status ?? 'Active';
                        $statusStyles = [
                            'Countered' => 'background-color: #ffc107; color: #000;',
                            'Active' => 'background-color: #007bff; color: #fff;',
                            'Accepted' => 'background-color: #28a745; color: #fff;',
                            'Rejected' => 'background-color: #dc3545; color: #fff;',
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
                $bidData = json_decode(json_encode($bid->get ?? []), true) ?: [];
                $propType = $auction->get->property_type ?? 'Residential Property';

                // Remap legacy DB keys to canonical helper keys.
                // DB stores broker_fee_timing; helper uses payment_timing.
                if (($auctionBaselineData['payment_timing'] ?? '') === '') {
                    $auctionBaselineData['payment_timing'] = $auctionBaselineData['broker_fee_timing'] ?? null;
                }
                if (($auctionBaselineData['days_to_pay'] ?? '') === '') {
                    $auctionBaselineData['days_to_pay'] = $auctionBaselineData['broker_fee_days_from_rent']
                        ?? $auctionBaselineData['broker_fee_days_after_lease'] ?? null;
                }
                if (($bidData['payment_timing'] ?? '') === '') {
                    $bidData['payment_timing'] = $bidData['broker_fee_timing'] ?? null;
                }
                if (($bidData['days_to_pay'] ?? '') === '') {
                    $bidData['days_to_pay'] = $bidData['broker_fee_days_from_rent']
                        ?? $bidData['broker_fee_days_after_lease'] ?? null;
                }

                $matchScore = \App\Helpers\TenantBidMatchScoreHelper::calculate(
                    $auctionBaselineData, $bidData, null, $propType
                );

                $totalScore      = $matchScore['overall_percent'];
                $brokerScore     = $matchScore['terms_match_percent'];
                $brokerMatched   = $matchScore['terms_matched_count'];
                $brokerTotal     = $matchScore['terms_baseline_total'];
                $brokerMismatches = $matchScore['changed_terms'];
                $servicesScore   = $matchScore['services_match_percent'];
                $servicesMatched = $matchScore['services_matched_count'];
                $servicesTotal   = $matchScore['services_baseline_total'];
                $servicesMissing = $matchScore['missing_services'] ?? [];
                $servicesAdded   = $matchScore['extra_services'] ?? [];
                $servicesMatchedList = $matchScore['matched_services'] ?? [];

                $getScoreColor = function($score) {
                    if ($score >= 80) return '#28a745';
                    if ($score >= 50) return '#ffc107';
                    return '#dc3545';
                };

                $totalScoreColor    = $getScoreColor($totalScore);
                $brokerScoreColor   = $getScoreColor($brokerScore);
                $servicesScoreColor = $getScoreColor($servicesScore);

                $mismatchStyle = 'background-color: #ffe6e6; padding: 2px 6px; border-radius: 4px; border-left: 3px solid #dc3545;';
                $mismatchBadge = '<span class="badge bg-danger ms-2" style="font-size: 0.7rem; vertical-align: middle;">Mismatch</span>';
            @endphp

            <div class="d-flex align-items-center gap-3 mb-4 p-3 rounded" style="background: #f8f9fa;">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px; background: {{ $totalScoreColor }}; color: white; font-weight: 700; font-size: 1.1rem;">
                        {{ $totalScore }}%
                    </div>
                    <div>
                        <div class="fw-bold">{{ $bid->user->name ?? 'Agent' }}</div>
                        <small class="text-muted">{{ $bid->user->email ?? '' }}</small>
                    </div>
                </div>
                <div class="ms-auto d-flex gap-3">
                    <div class="text-center">
                        <small class="text-muted d-block">Broker Terms</small>
                        <span style="color: {{ $brokerScoreColor }}; font-weight: 600;">{{ $brokerScore }}%</span>
                        <small class="text-muted">{{ $brokerTotal > 0 ? '('.$brokerMatched.'/'.$brokerTotal.')' : 'No terms provided' }}</small>
                    </div>
                    <div class="text-center">
                        <small class="text-muted d-block">Services</small>
                        <span style="color: {{ $servicesScoreColor }}; font-weight: 600;">{{ $servicesScore }}%</span>
                        <small class="text-muted">{{ $servicesTotal > 0 ? '('.$servicesMatched.'/'.$servicesTotal.')' : 'No services requested' }}</small>
                    </div>
                </div>
            </div>
            <p class="small text-muted mb-4">Comparing to: <strong>Your Listing Terms</strong></p>

            <div class="mb-5">
                <h6 class="section-header">
                    <i class="fa fa-user-tie me-2"></i>Agent Overview & Qualifications
                </h6>

                @if (data_get($bid, 'get.bio'))
                <div class="mb-3">
                    <div class="field-label" style="color: #049399;">About Agent:</div>
                    <div class="field-value">{{ data_get($bid, 'get.bio') }}</div>
                </div>
                @endif

                @if (data_get($bid, 'get.why_hire_you'))
                <div class="mb-3">
                    <div class="field-label" style="color: #049399;">Why Hire This Agent:</div>
                    <div class="field-value">{{ data_get($bid, 'get.why_hire_you') }}</div>
                </div>
                @endif

                @if (data_get($bid, 'get.what_sets_you_apart'))
                <div class="mb-3">
                    <div class="field-label" style="color: #049399;">What Sets This Agent Apart:</div>
                    <div class="field-value">{{ data_get($bid, 'get.what_sets_you_apart') }}</div>
                </div>
                @endif

                @if (data_get($bid, 'get.marketing_plan'))
                <div class="mb-3">
                    <div class="field-label" style="color: #049399;">Marketing Strategy:</div>
                    <div class="field-value">{{ data_get($bid, 'get.marketing_plan') }}</div>
                </div>
                @endif

                @if (data_get($bid, 'get.reviews_links'))
                <div class="mb-3">
                    <div class="field-label" style="color: #049399;">Review Links:</div>
                    <div>
                        @foreach (data_get($bid, 'get.reviews_links') as $reviewLink)
                        @if (!empty($reviewLink->url))
                        <div class="mb-1">
                            <a href="https://{{ $reviewLink->url }}" target="_blank" class="text-primary text-decoration-none">
                                <i class="fa fa-external-link-alt me-1"></i>
                                {{ !empty($reviewLink->text) ? $reviewLink->text : $reviewLink->url }}
                            </a>
                        </div>
                        @endif
                        @endforeach
                    </div>
                </div>
                @endif

                @if (data_get($bid, 'get.website_link'))
                <div class="mb-3">
                    <div class="field-label" style="color: #049399;">Website Link:</div>
                    <div>
                        @php
                            $websiteLink = data_get($bid, 'get.website_link');
                            if (!empty($websiteLink) && !str_starts_with($websiteLink, 'http://') && !str_starts_with($websiteLink, 'https://')) {
                                $websiteLink = 'https://' . $websiteLink;
                            }
                        @endphp
                        <a href="{{ $websiteLink }}" target="_blank" rel="noopener noreferrer" class="text-primary text-decoration-none">
                            <i class="fa fa-globe me-1"></i>Visit Website
                        </a>
                    </div>
                </div>
                @endif

                @if (data_get($bid, 'get.social_media'))
                <div class="mb-3">
                    <div class="field-label" style="color: #049399;">Social Media Platforms:</div>
                    <div>
                        @foreach (data_get($bid, 'get.social_media') as $social)
                        @php $socialArray = (array) $social; @endphp
                        @if (!empty($socialArray['platform']) && !empty($socialArray['url']))
                        <div class="mb-1">
                            @php
                            $socialUrl = $socialArray['url'];
                            if (!empty($socialUrl) && !str_starts_with($socialUrl, 'http://') && !str_starts_with($socialUrl, 'https://')) {
                                $socialUrl = 'https://' . $socialUrl;
                            }
                            @endphp
                            <a href="{{ $socialUrl }}" target="_blank" class="text-primary text-decoration-none">
                                <i class="fab fa-{{ strtolower($socialArray['platform']) }} me-1"></i>
                                {{ !empty($socialArray['text']) ? $socialArray['text'] : $socialArray['platform'] }}
                            </a>
                        </div>
                        @endif
                        @endforeach
                    </div>
                </div>
                @endif

                @if (data_get($bid, 'get.year_licensed'))
                <div class="mb-3">
                    <div class="field-label" style="color: #049399;">Licensed Year:</div>
                    <div class="field-value">{{ data_get($bid, 'get.year_licensed') }}</div>
                </div>
                @endif
            </div>

            @php
                $commissionFeeDisplay = '—';
                $leaseFeeType = data_get($bid, 'get.lease_fee_type');
                if ($leaseFeeType === 'Flat Fee') {
                    $flatFee = $fmtMoney(data_get($bid, 'get.flat_fee_amount'));
                    $commissionFeeDisplay = $flatFee ?? '—';
                } elseif ($leaseFeeType === '% of Gross Lease Value') {
                    $pct = $fmtPercent(data_get($bid, 'get.percent_gross_lease'));
                    $commissionFeeDisplay = $pct ? $pct . ' of Gross Lease Value' : '—';
                } elseif ($leaseFeeType === 'Flat Fee + % of Gross Lease Value') {
                    $flatPart = $fmtMoney(data_get($bid, 'get.flat_fee_amount'));
                    $pctPart = $fmtPercent(data_get($bid, 'get.percent_gross_lease'));
                    $basisPart = $basisText('Gross Lease Value');
                    $commissionFeeDisplay = $joinParts([$flatPart, $pctPart ? ($pctPart . ($basisPart ? " $basisPart" : '')) : null]) ?? '—';
                } else {
                    $commissionFeeDisplay = $leaseFeeType ?? '—';
                }
                
                $purchaseFeeDisplay = '—';
                $purchaseFeeType = data_get($bid, 'get.purchase_fee_type');
                if ($purchaseFeeType === 'Flat Fee') {
                    $purchaseFeeDisplay = $fmtMoney(data_get($bid, 'get.purchase_flat_fee_amount')) ?? '—';
                } elseif ($purchaseFeeType === '% of Purchase Price') {
                    $pct = $fmtPercent(data_get($bid, 'get.purchase_percent_value'));
                    $purchaseFeeDisplay = $pct ? $pct . ' of Purchase Price' : '—';
                } elseif ($purchaseFeeType === 'Flat Fee + % of Purchase Price') {
                    $flatPart = $fmtMoney(data_get($bid, 'get.purchase_flat_fee_amount'));
                    $pctPart = $fmtPercent(data_get($bid, 'get.purchase_percent_value'));
                    $purchaseFeeDisplay = $joinParts([$flatPart, $pctPart ? ($pctPart . ' of Purchase Price') : null]) ?? '—';
                }
            @endphp

            @if (data_get($bid, 'get.commission_structure') || data_get($bid, 'get.lease_fee_type') || data_get($bid, 'get.interested_purchase_fee_type'))
            <div class="mb-5">
                <h6 class="section-header">
                    <i class="fa fa-handshake me-2"></i>Broker Compensation & Agency Agreement Terms
                </h6>

                @if (data_get($bid, 'get.commission_structure') || data_get($bid, 'get.lease_fee_type'))
                <div class="mb-4">
                    <h6 class="mb-2" style="color: #049399; font-weight: 600;">A) Tenant's Broker Compensation</h6>
                    <ul class="list-unstyled ps-3 mb-0">
                        @if (data_get($bid, 'get.commission_structure'))
                        <li class="mb-1" style="{{ isset($brokerMismatches['commission_structure']) ? $mismatchStyle : '' }}">
                            <span class="fw-semibold">Tenant's Broker Commission Structure:</span> {{ data_get($bid, 'get.commission_structure') }}{!! isset($brokerMismatches['commission_structure']) ? $mismatchBadge : '' !!}
                        </li>
                        @endif
                        @if (data_get($bid, 'get.lease_fee_type'))
                        <li class="mb-1" style="{{ isset($brokerMismatches['lease_fee_type']) ? $mismatchStyle : '' }}">
                            <span class="fw-semibold">Tenant's Broker Commission Fee:</span> {{ $commissionFeeDisplay }}{!! isset($brokerMismatches['lease_fee_type']) ? $mismatchBadge : '' !!}
                        </li>
                        @endif
                        @if (data_get($bid, 'get.broker_fee_timing') || data_get($bid, 'get.payment_timing'))
                        <li class="mb-1" style="{{ isset($brokerMismatches['payment_timing']) ? $mismatchStyle : '' }}">
                            <span class="fw-semibold">Payment Timing for Broker Fees:</span> {{ data_get($bid, 'get.payment_timing') ?? data_get($bid, 'get.broker_fee_timing') }}{!! isset($brokerMismatches['payment_timing']) ? $mismatchBadge : '' !!}
                        </li>
                        @endif
                    </ul>
                </div>
                @endif

                @if (data_get($bid, 'get.interested_purchase_fee_type'))
                <div class="mb-4">
                    <h6 class="mb-2" style="color: #049399; font-weight: 600;">B) Purchase Fee Details</h6>
                    <ul class="list-unstyled ps-3 mb-0">
                        <li class="mb-1" style="{{ isset($brokerMismatches['interested_purchase_fee_type']) ? $mismatchStyle : '' }}">
                            <span class="fw-semibold">Interested in Purchasing a Property:</span> {{ data_get($bid, 'get.interested_purchase_fee_type') }}{!! isset($brokerMismatches['interested_purchase_fee_type']) ? $mismatchBadge : '' !!}
                        </li>
                        @if (data_get($bid, 'get.interested_purchase_fee_type') === 'Yes' && $purchaseFeeDisplay !== '—')
                        <li class="mb-1" style="{{ isset($brokerMismatches['purchase_fee_type']) ? $mismatchStyle : '' }}">
                            <span class="fw-semibold">Purchase Fee:</span> {{ $purchaseFeeDisplay }}{!! isset($brokerMismatches['purchase_fee_type']) ? $mismatchBadge : '' !!}
                        </li>
                        @endif
                    </ul>
                </div>
                @endif

                @if (data_get($bid, 'get.protection_period'))
                <div class="mb-4">
                    <h6 class="mb-2" style="color: #049399; font-weight: 600;">D) Protection Period</h6>
                    <ul class="list-unstyled ps-3 mb-0">
                        <li class="mb-1" style="{{ isset($brokerMismatches['protection_period']) ? $mismatchStyle : '' }}">
                            <span class="fw-semibold">Protection Period:</span> {{ data_get($bid, 'get.protection_period') }}{!! isset($brokerMismatches['protection_period']) ? $mismatchBadge : '' !!}
                        </li>
                    </ul>
                </div>
                @endif

                @if (data_get($bid, 'get.early_termination_fee_option'))
                <div class="mb-4">
                    <h6 class="mb-2" style="color: #049399; font-weight: 600;">E) Early Termination Fee</h6>
                    <ul class="list-unstyled ps-3 mb-0">
                        <li class="mb-1" style="{{ isset($brokerMismatches['early_termination_fee_option']) ? $mismatchStyle : '' }}">
                            <span class="fw-semibold">Early Termination Fee:</span> {{ data_get($bid, 'get.early_termination_fee_option') }}{!! isset($brokerMismatches['early_termination_fee_option']) ? $mismatchBadge : '' !!}
                        </li>
                        @if (data_get($bid, 'get.early_termination_fee_option') === 'Yes' && data_get($bid, 'get.early_termination_fee_amount'))
                        <li class="mb-1">
                            <span class="fw-semibold">Amount:</span> {{ $fmtMoney(data_get($bid, 'get.early_termination_fee_amount')) ?? data_get($bid, 'get.early_termination_fee_amount') }}
                        </li>
                        @endif
                    </ul>
                </div>
                @endif

                @if (data_get($bid, 'get.retainer_fee_option'))
                <div class="mb-4">
                    <h6 class="mb-2" style="color: #049399; font-weight: 600;">F) Retainer Fee</h6>
                    <ul class="list-unstyled ps-3 mb-0">
                        <li class="mb-1" style="{{ isset($brokerMismatches['retainer_fee_option']) ? $mismatchStyle : '' }}">
                            <span class="fw-semibold">Retainer Fee:</span> {{ data_get($bid, 'get.retainer_fee_option') }}{!! isset($brokerMismatches['retainer_fee_option']) ? $mismatchBadge : '' !!}
                        </li>
                        @if (data_get($bid, 'get.retainer_fee_option') === 'Yes')
                            @if (data_get($bid, 'get.retainer_fee_amount'))
                            <li class="mb-1"><span class="fw-semibold">Amount:</span> {{ $fmtMoney(data_get($bid, 'get.retainer_fee_amount')) ?? data_get($bid, 'get.retainer_fee_amount') }}</li>
                            @endif
                            @if (data_get($bid, 'get.retainer_fee_application'))
                            <li class="mb-1"><span class="fw-semibold">Applied To:</span> {{ data_get($bid, 'get.retainer_fee_application') }}</li>
                            @endif
                        @endif
                    </ul>
                </div>
                @endif

                @if (data_get($bid, 'get.agency_agreement_timeframe'))
                <div class="mb-4">
                    <h6 class="mb-2" style="color: #049399; font-weight: 600;">G) Agency Agreement Timeframe</h6>
                    <ul class="list-unstyled ps-3 mb-0">
                        <li class="mb-1" style="{{ isset($brokerMismatches['agency_agreement_timeframe']) ? $mismatchStyle : '' }}">
                            <span class="fw-semibold">Timeframe:</span> {{ data_get($bid, 'get.agency_agreement_timeframe') }}{!! isset($brokerMismatches['agency_agreement_timeframe']) ? $mismatchBadge : '' !!}
                        </li>
                    </ul>
                </div>
                @endif

                @if (data_get($bid, 'get.brokerage_relationship'))
                <div class="mb-4">
                    <h6 class="mb-2" style="color: #049399; font-weight: 600;">H) Brokerage Relationship</h6>
                    <ul class="list-unstyled ps-3 mb-0">
                        <li class="mb-1" style="{{ isset($brokerMismatches['brokerage_relationship']) ? $mismatchStyle : '' }}">
                            <span class="fw-semibold">Relationship Type:</span> {{ data_get($bid, 'get.brokerage_relationship') }}{!! isset($brokerMismatches['brokerage_relationship']) ? $mismatchBadge : '' !!}
                        </li>
                    </ul>
                </div>
                @endif
            </div>
            @endif

            @if (data_get($bid, 'get.additional_details'))
            <div class="mb-5">
                <h6 class="section-header">
                    <i class="fa fa-file-alt me-2"></i>Additional Details
                </h6>
                <div class="field-value">{{ data_get($bid, 'get.additional_details') }}</div>
            </div>
            @endif

            <div class="mb-5">
                <h6 class="section-header">
                    <i class="fa fa-list-check me-2"></i>Offered Services
                </h6>
                
                <div class="row">
                    @if (count($servicesMatchedList) > 0)
                    <div class="col-md-4 mb-3">
                        <div class="p-3 rounded" style="background: #e6f7e6; border: 1px solid #c3e6c3;">
                            <h6 class="mb-2" style="color: #28a745;"><i class="fas fa-check-circle me-1"></i>Matched Services ({{ count($servicesMatchedList) }})</h6>
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
                        <div class="p-3 rounded" style="background: #e6f7ff; border: 1px solid #91d5ff;">
                            <h6 class="mb-2" style="color: #17a2b8;"><i class="fas fa-plus-circle me-1"></i>Extra Services ({{ count($servicesAdded) }})</h6>
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
                        <div class="p-3 rounded" style="background: #fff2e8; border: 1px solid #ffbb96;">
                            <h6 class="mb-2" style="color: #dc3545;"><i class="fas fa-minus-circle me-1"></i>Not Offered ({{ count($servicesMissing) }})</h6>
                            <ul class="services-list mb-0">
                                @foreach($servicesMissing as $svc)
                                <li class="service-missing"><i class="fas fa-times me-1"></i>{{ ucfirst($svc) }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            @if (data_get($bid, 'get.presentation_link') || data_get($bid, 'get.video_upload') || data_get($bid, 'get.business_card_link') || data_get($bid, 'get.business_card') || data_get($bid, 'get.promoMaterials'))
            <div class="mb-5">
                <h6 class="section-header">
                    <i class="fa fa-photo-video me-2"></i>Agent Presentation & Promotional Materials
                </h6>

                @if (data_get($bid, 'get.presentation_link'))
                <div class="mb-3">
                    <div class="field-label" style="color: #049399;">Video Presentation Link:</div>
                    @php
                        $presentationLink = data_get($bid, 'get.presentation_link');
                        if (!empty($presentationLink) && !str_starts_with($presentationLink, 'http://') && !str_starts_with($presentationLink, 'https://')) {
                            $presentationLink = 'https://' . $presentationLink;
                        }
                    @endphp
                    <a href="{{ $presentationLink }}" target="_blank" class="text-primary text-decoration-none">
                        <i class="fa fa-external-link-alt me-1"></i>Watch Presentation
                    </a>
                </div>
                @endif

                @if (data_get($bid, 'get.video_upload'))
                <div class="mb-3">
                    <div class="field-label" style="color: #049399;">Uploaded Video:</div>
                    @if (is_string(data_get($bid, 'get.video_upload')))
                    <video controls style="width: 100%; max-width: 400px; border-radius: 6px; background: #000;">
                        <source src="{{ asset('storage/' . data_get($bid, 'get.video_upload')) }}" type="video/mp4">
                        Your browser does not support the video tag.
                    </video>
                    @endif
                </div>
                @endif

                @if (data_get($bid, 'get.business_card_link') || data_get($bid, 'get.business_card'))
                <div class="mb-3">
                    <div class="field-label" style="color: #049399;">Business Card:</div>
                    @if (data_get($bid, 'get.business_card_link'))
                    @php
                        $businessCardLink = data_get($bid, 'get.business_card_link');
                        if (!empty($businessCardLink) && !str_starts_with($businessCardLink, 'http://') && !str_starts_with($businessCardLink, 'https://')) {
                            $businessCardLink = 'https://' . $businessCardLink;
                        }
                    @endphp
                    <a href="{{ $businessCardLink }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary btn-sm">
                        <i class="fa fa-external-link-alt me-1"></i>View Business Card (Link)
                    </a>
                    @endif

                    @if (data_get($bid, 'get.business_card'))
                    @php
                        $businessCardPath = data_get($bid, 'get.business_card');
                        $businessCardExtension = pathinfo($businessCardPath, PATHINFO_EXTENSION);
                        $businessCardUrl = asset('storage/' . $businessCardPath);
                        $isImage = in_array(strtolower($businessCardExtension), ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                    @endphp
                    <div class="mt-2">
                        @if ($isImage)
                        <a href="{{ $businessCardUrl }}" target="_blank" rel="noopener noreferrer">
                            <img src="{{ $businessCardUrl }}" style="max-width: 300px; height: auto; border-radius: 8px; border: 2px solid #e0e0e0;" alt="Business Card" class="img-fluid">
                        </a>
                        @else
                        <a href="{{ $businessCardUrl }}" download class="btn btn-outline-primary btn-sm">
                            <i class="fa fa-download me-1"></i>Download Business Card
                        </a>
                        @endif
                    </div>
                    @endif
                </div>
                @endif

                @if (data_get($bid, 'get.promoMaterials'))
                @php
                    $promoMaterialsRaw = data_get($bid, 'get.promoMaterials', []);
                    $promoMaterialsNormalized = [];
                    if (is_array($promoMaterialsRaw) || is_object($promoMaterialsRaw)) {
                        foreach($promoMaterialsRaw as $m) {
                            $mArr = is_object($m) ? (array) $m : (is_array($m) ? $m : []);
                            $promoMaterialsNormalized[] = $mArr;
                        }
                    }
                @endphp
                <div class="mb-3">
                    <div class="field-label" style="color: #049399;">Marketing Materials:</div>
                    @foreach ($promoMaterialsNormalized as $index => $material)
                    @php
                        $matType = data_get($material, 'type', '');
                        $matOther = data_get($material, 'other', '');
                        $matLink = data_get($material, 'link', '');
                        $matFiles = data_get($material, 'files', []);
                        if (is_object($matFiles)) { $matFiles = (array) $matFiles; }
                    @endphp
                    @if (!empty($matType) || !empty($matLink) || !empty($matFiles))
                    <div class="mb-3 p-3 border rounded bg-light">
                        @if (!empty($matType))
                        <div class="fw-medium mb-2" style="color: #049399;">
                            <i class="fa fa-folder-open me-1"></i>{{ $matType }}
                            @if ($matType === 'Other' && !empty($matOther)) - {{ $matOther }} @endif
                        </div>
                        @endif

                        @if (!empty($matLink))
                        <div class="mb-2">
                            @php
                                $materialLink = $matLink;
                                if (!empty($materialLink) && !str_starts_with($materialLink, 'http://') && !str_starts_with($materialLink, 'https://')) {
                                    $materialLink = 'https://' . $materialLink;
                                }
                            @endphp
                            <a href="{{ $materialLink }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary btn-sm">
                                <i class="fa fa-external-link-alt me-1"></i>Open Link
                            </a>
                        </div>
                        @endif

                        @if (!empty($matFiles))
                        <div class="row g-2">
                            @foreach ($matFiles as $fileIndex => $filePath)
                            @if (is_string($filePath))
                            @php
                                $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
                                $fileName = basename($filePath);
                                $fileUrl = asset('storage/' . $filePath);
                                $isImage = in_array(strtolower($fileExtension), ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                            @endphp
                            <div class="col-md-6 mb-2">
                                <div class="border rounded p-2 bg-white d-flex align-items-center">
                                    @if ($isImage)
                                    <a href="{{ $fileUrl }}" target="_blank" rel="noopener noreferrer">
                                        <img src="{{ $fileUrl }}" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px; margin-right: 10px;" alt="Material">
                                    </a>
                                    @else
                                    <div class="bg-light rounded d-flex align-items-center justify-content-center me-2" style="width: 50px; height: 50px;">
                                        <i class="fa fa-file fa-lg text-muted"></i>
                                    </div>
                                    @endif
                                    <div class="flex-grow-1 overflow-hidden">
                                        <div class="small text-truncate fw-medium">{{ $fileName }}</div>
                                        <small class="text-muted">{{ strtoupper($fileExtension) }}</small>
                                    </div>
                                    <a href="{{ $fileUrl }}" download class="btn btn-sm btn-outline-success" title="Download">
                                        <i class="fa fa-download"></i>
                                    </a>
                                </div>
                            </div>
                            @endif
                            @endforeach
                        </div>
                        @endif
                    </div>
                    @endif
                    @endforeach
                </div>
                @endif
            </div>
            @endif

            <div class="mb-4">
                <h6 class="section-header">
                    <i class="fa fa-address-card me-2"></i>Agent Credentials & Contact Information
                </h6>

                <div class="row">
                    @if (data_get($bid, 'get.first_name'))
                    <div class="col-md-6 mb-2">
                        <div class="field-label" style="color: #049399;">First Name</div>
                        <div class="field-value">{{ data_get($bid, 'get.first_name') }}</div>
                    </div>
                    @endif

                    @if (data_get($bid, 'get.last_name'))
                    <div class="col-md-6 mb-2">
                        <div class="field-label" style="color: #049399;">Last Name</div>
                        <div class="field-value">{{ data_get($bid, 'get.last_name') }}</div>
                    </div>
                    @endif

                    @if (data_get($bid, 'get.brokerage'))
                    <div class="col-md-6 mb-2">
                        <div class="field-label" style="color: #049399;">Brokerage</div>
                        <div class="field-value">{{ data_get($bid, 'get.brokerage') }}</div>
                    </div>
                    @endif

                    @if (data_get($bid, 'get.license_number'))
                    <div class="col-md-6 mb-2">
                        <div class="field-label" style="color: #049399;">License Number</div>
                        <div class="field-value">{{ data_get($bid, 'get.license_number') }}</div>
                    </div>
                    @endif

                    @if (data_get($bid, 'get.phone'))
                    <div class="col-md-6 mb-2">
                        <div class="field-label" style="color: #049399;">Phone</div>
                        <div class="field-value">{{ data_get($bid, 'get.phone') }}</div>
                    </div>
                    @endif

                    @if (data_get($bid, 'get.email'))
                    <div class="col-md-6 mb-2">
                        <div class="field-label" style="color: #049399;">Email</div>
                        <div class="field-value">{{ data_get($bid, 'get.email') }}</div>
                    </div>
                    @endif

                    @if (data_get($bid, 'get.preferred_contact'))
                    <div class="col-md-6 mb-2">
                        <div class="field-label" style="color: #049399;">Preferred Contact Method</div>
                        <div class="field-value">{{ data_get($bid, 'get.preferred_contact') }}</div>
                    </div>
                    @endif
                </div>
            </div>

        </div>

        <div class="action-buttons d-flex flex-wrap justify-content-between align-items-center gap-2">
            <a href="{{ route('myBids') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>Back to Agent Bids
            </a>
            
            <div class="d-flex gap-2 flex-wrap">
                @if($bidStatus === 'Active')
                    <form action="{{ route('tenant.hire.agent.auction.bid.accept') }}" method="POST" class="d-inline">
                        @csrf
                        <input type="hidden" name="bid_id" value="{{ $bid->id }}">
                        <input type="hidden" name="auction_id" value="{{ $auction->id }}">
                        <button type="submit" class="btn" style="background: #28a745; color: #fff; border: none;" onclick="return confirm('Accept this bid?')">
                            <i class="fas fa-check me-1"></i>Accept
                        </button>
                    </form>
                    <a href="{{ route('tenant.counter-terms', $bid->id) }}" class="btn" style="background: #ffc107; color: #000; border: none;">
                        <i class="fas fa-exchange-alt me-1"></i>Counter
                    </a>
                    <form action="{{ route('tenant.hire.agent.auction.bid.reject') }}" method="POST" class="d-inline">
                        @csrf
                        <input type="hidden" name="bid_id" value="{{ $bid->id }}">
                        <input type="hidden" name="auction_id" value="{{ $auction->id }}">
                        <button type="submit" class="btn" style="background: #dc3545; color: #fff; border: none;" onclick="return confirm('Reject this bid?')">
                            <i class="fas fa-times me-1"></i>Reject
                        </button>
                    </form>
                @elseif($bidStatus === 'Countered')
                    <span class="btn" style="background: #fff3cd; color: #856404; border: 1px solid #ffc107;">
                        <i class="fas fa-clock me-1"></i>Awaiting Response
                    </span>
                @elseif($bidStatus === 'Accepted')
                    <a href="{{ route('tenant.agent.auction.view', $auction->id) }}" class="btn" style="background: #28a745; color: #fff; border: none;">
                        <i class="fas fa-file-contract me-1"></i>View Summary
                    </a>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
