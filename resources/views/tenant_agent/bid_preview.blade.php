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
@include('partials.bid_detail_styles')
@endpush

@section('content')
@php
    $bidStatus = $bid->bid_status ?? 'Active';
    $bidStatusColorForLayout = match($bidStatus) {
        'Countered' => '#ffc107',
        'Accepted'  => '#28a745',
        'Rejected'  => '#dc3545',
        default     => '#1a4a6e',
    };
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

    // ── Shared match_score_panel variables ─────────────────────────────────
    $ms_has_baseline           = ($servicesTotal > 0 || $brokerTotal > 0);
    $ms_overall_pct            = $totalScore;
    $ms_overall_color          = $totalScoreColor;
    $ms_services_pct           = $servicesScore;
    $ms_services_color         = $servicesScoreColor;
    $ms_services_total         = $servicesTotal;
    $ms_services_matched       = $servicesMatched;
    $ms_services_extra_count   = count($servicesAdded);
    $ms_services_missing_count = count($servicesMissing);
    $ms_terms_pct              = $brokerScore;
    $ms_terms_color            = $brokerScoreColor;
    $ms_terms_total            = $brokerTotal;
    $ms_terms_matched          = $brokerMatched;
    $ms_terms_changed_count    = count($brokerMismatches);
    $ms_terms_added_count      = 0;
    $ms_baseline_label         = 'Your Listing Terms';
@endphp

<x-bid-detail-layout
    :backUrl="route('myBids')"
    backLabel="Back to Agent Bids"
    roleLabel="Hire a Tenant's Agent"
    :listingId="$listingId"
    headerTitle="Full Agent Bid Preview"
    :bidStatus="$bidStatus"
    :bidStatusColor="$bidStatusColorForLayout">

            @include('partials.bid_detail_body.match_score_panel')

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

                @php
                    // reviews_links may be stored as array-of-arrays OR array-of-objects; handle both
                    $reviewLinksRaw = data_get($bid, 'get.reviews_links', []);
                    if (!is_array($reviewLinksRaw) && !is_object($reviewLinksRaw)) { $reviewLinksRaw = []; }
                    $hasAnyReviewUrl = !empty(array_filter((array) $reviewLinksRaw, function($rl) {
                        $u = is_object($rl) ? ($rl->url ?? '') : ($rl['url'] ?? '');
                        return !empty(trim((string)$u));
                    }));
                @endphp
                @if ($hasAnyReviewUrl)
                <div class="mb-3">
                    <div class="field-label" style="color: #049399;">Review Links:</div>
                    <div>
                        @foreach ((array) $reviewLinksRaw as $reviewLink)
                        @php
                            $rlUrl  = is_object($reviewLink) ? ($reviewLink->url  ?? '') : ($reviewLink['url']  ?? '');
                            $rlText = is_object($reviewLink) ? ($reviewLink->text ?? '') : ($reviewLink['text'] ?? '');
                            $rlUrl  = trim((string) $rlUrl);
                        @endphp
                        @if (!empty($rlUrl))
                        <div class="mb-1">
                            @php
                                $rlHref = (str_starts_with($rlUrl, 'http://') || str_starts_with($rlUrl, 'https://'))
                                    ? $rlUrl : 'https://' . $rlUrl;
                            @endphp
                            <a href="{{ $rlHref }}" target="_blank" class="text-primary text-decoration-none">
                                <i class="fa fa-arrow-up-right-from-square me-1"></i>
                                {{ !empty($rlText) ? $rlText : $rlUrl }}
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
                                <i class="fa-brands fa-{{ strtolower($socialArray['platform']) }} me-1"></i>
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
                // ── Commission structure alias (stored legacy values → current wording) ────
                $commissionStructure   = data_get($bid, 'get.commission_structure', '');
                $bidCommissionDisplay  = match($commissionStructure) {
                    'Out-of-Pocket Payment' => 'Tenant Pays Out-of-Pocket',
                    'Included in Offer'     => 'Requested From Landlord in the Offer',
                    default                  => $commissionStructure,
                };

                // ── Commission fee display (canonical DB fields) ──────────────────────────
                $leaseFeeType = data_get($bid, 'get.lease_fee_type', '');
                $commissionFeeDisplay = '-';
                if ($leaseFeeType === 'Flat Fee') {
                    $commissionFeeDisplay = $fmtMoney(data_get($bid, 'get.lease_fee_flat')) ?? '-';
                } elseif ($leaseFeeType === 'Percentage of the Gross Lease Value') {
                    $pct = data_get($bid, 'get.lease_fee_percentage');
                    $commissionFeeDisplay = $pct ? ($fmtPercent($pct) . ' of Gross Lease Value') : '-';
                } elseif ($leaseFeeType === 'Percentage of Monthly Rent') {
                    $pct    = data_get($bid, 'get.lease_fee_percentage_monthly_rent');
                    $months = data_get($bid, 'get.lease_fee_percentage_monthly_number');
                    $disp   = $pct ? ($fmtPercent($pct) . ' of Monthly Rent') : null;
                    if ($disp && $months) { $disp .= ' x ' . $months . ' Months'; }
                    $commissionFeeDisplay = $disp ?? '-';
                } elseif ($leaseFeeType === 'Flat Fee + Percentage of the Gross Lease Value') {
                    $commissionFeeDisplay = $joinParts([
                        $fmtMoney(data_get($bid, 'get.lease_fee_flat_combo')),
                        data_get($bid, 'get.lease_fee_percentage_combo')
                            ? ($fmtPercent(data_get($bid, 'get.lease_fee_percentage_combo')) . ' of Gross Lease Value')
                            : null,
                    ]) ?? '-';
                } elseif ($leaseFeeType === 'Percentage of the Net Aggregate Rent') {
                    $pct = data_get($bid, 'get.lease_fee_percentage_net');
                    $commissionFeeDisplay = $pct ? ($fmtPercent($pct) . ' of Net Aggregate Rent') : '-';
                } elseif ($leaseFeeType === 'Flat Fee + Percentage of the Net Aggregate Rent') {
                    $commissionFeeDisplay = $joinParts([
                        $fmtMoney(data_get($bid, 'get.lease_fee_flat_combo_net')),
                        data_get($bid, 'get.lease_fee_percentage_combo_net')
                            ? ($fmtPercent(data_get($bid, 'get.lease_fee_percentage_combo_net')) . ' of Net Aggregate Rent')
                            : null,
                    ]) ?? '-';
                } elseif ($leaseFeeType === 'Other') {
                    $commissionFeeDisplay = data_get($bid, 'get.lease_fee_other') ?? '-';
                } elseif ($leaseFeeType) {
                    $commissionFeeDisplay = $leaseFeeType;
                }

                // ── Purchase fee display (canonical DB fields) ────────────────────────────
                $purchaseFeeType    = data_get($bid, 'get.purchase_fee_type', '');
                $purchaseFeeDisplay = '-';
                if ($purchaseFeeType === 'Flat Fee') {
                    $purchaseFeeDisplay = $fmtMoney(data_get($bid, 'get.purchase_fee_flat')) ?? '-';
                } elseif ($purchaseFeeType === 'Percentage of the Total Purchase Price') {
                    $pct = data_get($bid, 'get.purchase_fee_percentage');
                    $purchaseFeeDisplay = $pct ? ($fmtPercent($pct) . ' of Total Purchase Price') : '-';
                } elseif ($purchaseFeeType === 'Percentage of the Total Purchase Price + Flat Fee') {
                    $purchaseFeeDisplay = $joinParts([
                        $fmtMoney(data_get($bid, 'get.purchase_fee_flat_combo')),
                        data_get($bid, 'get.purchase_fee_percentage_combo')
                            ? ($fmtPercent(data_get($bid, 'get.purchase_fee_percentage_combo')) . ' of Total Purchase Price')
                            : null,
                    ]) ?? '-';
                } elseif ($purchaseFeeType === 'other') {
                    $purchaseFeeDisplay = data_get($bid, 'get.purchase_fee_other') ?? '-';
                }

                // ── Lease-Option display ──────────────────────────────────────────────────
                $leaseOptionCreatedDisplay  = '-';
                $leaseOptionExercisedDisplay = '-';
                if (data_get($bid, 'get.interested_lease_option_agreement') === 'Yes') {
                    $leaseType  = data_get($bid, 'get.lease_type');
                    $leaseValue = data_get($bid, 'get.lease_value');
                    if ($leaseType === 'percent' && $leaseValue) {
                        $leaseOptionCreatedDisplay = $fmtPercent($leaseValue) . ' of Total Purchase Price';
                    } elseif ($leaseValue) {
                        $leaseOptionCreatedDisplay = $fmtMoney($leaseValue) ?? '-';
                    }
                    $purchaseType  = data_get($bid, 'get.purchase_type');
                    $purchaseValue = data_get($bid, 'get.purchase_value');
                    if ($purchaseType === 'percent' && $purchaseValue) {
                        $leaseOptionExercisedDisplay = $fmtPercent($purchaseValue) . ' of Total Purchase Price';
                    } elseif ($purchaseValue) {
                        $leaseOptionExercisedDisplay = $fmtMoney($purchaseValue) ?? '-';
                    }
                }

                // ── Termination fee display ───────────────────────────────────────────────
                $terminationFeeDisplay = '-';
                if (data_get($bid, 'get.early_termination_fee_option') === 'Yes') {
                    $terminationFeeDisplay = $fmtMoney(data_get($bid, 'get.early_termination_fee_amount')) ?? '-';
                }

                // ── Agency agreement timeframe display ────────────────────────────────────
                $agencyTimeframe       = data_get($bid, 'get.agency_agreement_timeframe', '');
                $agencyTimeframeCustom = data_get($bid, 'get.agency_agreement_custom', '');
                $isOtherTimeframe      = is_string($agencyTimeframe) && strtolower(trim($agencyTimeframe)) === 'other';
                $agencyTimeframeDisplay = $isOtherTimeframe ? ($agencyTimeframeCustom ?: 'Other') : ($agencyTimeframe ?: '');
            @endphp

            @if (data_get($bid, 'get.commission_structure') ||
                 data_get($bid, 'get.lease_fee_type') ||
                 data_get($bid, 'get.interested_purchase_fee_type') ||
                 data_get($bid, 'get.interested_lease_option_agreement') ||
                 data_get($bid, 'get.protection_period') ||
                 data_get($bid, 'get.early_termination_fee_option') ||
                 data_get($bid, 'get.retainer_fee_option') ||
                 data_get($bid, 'get.agency_agreement_timeframe') ||
                 data_get($bid, 'get.brokerage_relationship'))
            <div class="mb-5">
                <h6 class="section-header">
                    <i class="fa fa-handshake me-2"></i>Broker Compensation & Agency Agreement Terms
                </h6>

                {{-- A) Tenant's Broker Compensation --}}
                @if (data_get($bid, 'get.commission_structure') || data_get($bid, 'get.lease_fee_type') || data_get($bid, 'get.payment_timing') || data_get($bid, 'get.days_to_pay'))
                <div class="mb-4">
                    <h6 class="mb-2" style="color: #049399; font-weight: 600;">A) Tenant's Broker Compensation</h6>
                    <ul class="list-unstyled ps-3 mb-0">
                        @if (data_get($bid, 'get.commission_structure'))
                        <li class="mb-1" style="{{ isset($brokerMismatches['commission_structure']) ? $mismatchStyle : '' }}">
                            <span class="fw-semibold">Tenant's Broker Commission Structure:</span> {{ $bidCommissionDisplay }}{!! isset($brokerMismatches['commission_structure']) ? $mismatchBadge : '' !!}
                        </li>
                        @endif
                        @if (data_get($bid, 'get.lease_fee_type'))
                        <li class="mb-1" style="{{ isset($brokerMismatches['lease_fee_type']) ? $mismatchStyle : '' }}">
                            <span class="fw-semibold">Tenant's Broker Commission Fee:</span> {{ $commissionFeeDisplay }}{!! isset($brokerMismatches['lease_fee_type']) ? $mismatchBadge : '' !!}
                        </li>
                        @endif
                        @if (data_get($bid, 'get.payment_timing'))
                        <li class="mb-1" style="{{ isset($brokerMismatches['payment_timing']) ? $mismatchStyle : '' }}">
                            <span class="fw-semibold">Payment Timing for Broker Fees:</span> {{ data_get($bid, 'get.payment_timing') }}{!! isset($brokerMismatches['payment_timing']) ? $mismatchBadge : '' !!}
                        </li>
                        @endif
                        @if (data_get($bid, 'get.days_to_pay'))
                        <li class="mb-1" style="{{ isset($brokerMismatches['days_to_pay']) ? $mismatchStyle : '' }}">
                            <span class="fw-semibold">Calendar Days To Pay:</span> {{ data_get($bid, 'get.days_to_pay') }}{!! isset($brokerMismatches['days_to_pay']) ? $mismatchBadge : '' !!}
                        </li>
                        @endif
                    </ul>
                </div>
                @endif

                {{-- B) Purchase Fee Details --}}
                @if (data_get($bid, 'get.interested_purchase_fee_type'))
                <div class="mb-4">
                    <h6 class="mb-2" style="color: #049399; font-weight: 600;">B) Purchase Fee Details</h6>
                    <ul class="list-unstyled ps-3 mb-0">
                        <li class="mb-1" style="{{ isset($brokerMismatches['interested_purchase_fee_type']) ? $mismatchStyle : '' }}">
                            <span class="fw-semibold">Interested in Purchasing a Property:</span> {{ data_get($bid, 'get.interested_purchase_fee_type') }}{!! isset($brokerMismatches['interested_purchase_fee_type']) ? $mismatchBadge : '' !!}
                        </li>
                        @if (data_get($bid, 'get.interested_purchase_fee_type') === 'Yes' && $purchaseFeeDisplay !== '-')
                        <li class="mb-1" style="{{ isset($brokerMismatches['purchase_fee_type']) ? $mismatchStyle : '' }}">
                            <span class="fw-semibold">Purchase Fee:</span> {{ $purchaseFeeDisplay }}{!! isset($brokerMismatches['purchase_fee_type']) ? $mismatchBadge : '' !!}
                        </li>
                        @endif
                    </ul>
                </div>
                @endif

                {{-- C) Lease-Option Details --}}
                @if (data_get($bid, 'get.interested_lease_option_agreement'))
                <div class="mb-4">
                    <h6 class="mb-2" style="color: #049399; font-weight: 600;">C) Lease-Option Details</h6>
                    <ul class="list-unstyled ps-3 mb-0">
                        <li class="mb-1" style="{{ isset($brokerMismatches['interested_lease_option_agreement']) ? $mismatchStyle : '' }}">
                            <span class="fw-semibold">Interested in a Lease-Option Agreement:</span> {{ data_get($bid, 'get.interested_lease_option_agreement') }}{!! isset($brokerMismatches['interested_lease_option_agreement']) ? $mismatchBadge : '' !!}
                        </li>
                        @if (data_get($bid, 'get.interested_lease_option_agreement') === 'Yes')
                            @if ($leaseOptionCreatedDisplay !== '-')
                            <li class="mb-1" style="{{ (isset($brokerMismatches['lease_type']) || isset($brokerMismatches['lease_value'])) ? $mismatchStyle : '' }}">
                                <span class="fw-semibold">Compensation for Creating the Lease-Option Agreement:</span> {{ $leaseOptionCreatedDisplay }}{!! (isset($brokerMismatches['lease_type']) || isset($brokerMismatches['lease_value'])) ? $mismatchBadge : '' !!}
                            </li>
                            @endif
                            @if ($leaseOptionExercisedDisplay !== '-')
                            <li class="mb-1" style="{{ (isset($brokerMismatches['purchase_type']) || isset($brokerMismatches['purchase_value'])) ? $mismatchStyle : '' }}">
                                <span class="fw-semibold">Compensation if Purchase Option is Exercised:</span> {{ $leaseOptionExercisedDisplay }}{!! (isset($brokerMismatches['purchase_type']) || isset($brokerMismatches['purchase_value'])) ? $mismatchBadge : '' !!}
                            </li>
                            @endif
                        @endif
                    </ul>
                </div>
                @endif

                {{-- D) Legal Terms (grouped: protection period + early termination + retainer + agency timeframe) --}}
                @if (data_get($bid, 'get.protection_period') || data_get($bid, 'get.early_termination_fee_option') || data_get($bid, 'get.retainer_fee_option') || data_get($bid, 'get.agency_agreement_timeframe'))
                <div class="mb-4">
                    <h6 class="mb-2" style="color: #049399; font-weight: 600;">D) Legal Terms</h6>
                    <ul class="list-unstyled ps-3 mb-0">
                        @if (data_get($bid, 'get.protection_period'))
                        <li class="mb-1" style="{{ isset($brokerMismatches['protection_period']) ? $mismatchStyle : '' }}">
                            <span class="fw-semibold">Protection Period Timeframe:</span> {{ data_get($bid, 'get.protection_period') }} days{!! isset($brokerMismatches['protection_period']) ? $mismatchBadge : '' !!}
                        </li>
                        @endif
                        @if (data_get($bid, 'get.early_termination_fee_option'))
                        <li class="mb-1" style="{{ isset($brokerMismatches['early_termination_fee_option']) ? $mismatchStyle : '' }}">
                            <span class="fw-semibold">Early Termination Fee:</span> {{ data_get($bid, 'get.early_termination_fee_option') }}{!! isset($brokerMismatches['early_termination_fee_option']) ? $mismatchBadge : '' !!}
                        </li>
                            @if ($terminationFeeDisplay !== '-')
                            <li class="mb-1" style="{{ isset($brokerMismatches['early_termination_fee_amount']) ? $mismatchStyle : '' }}">
                                <span class="fw-semibold">Termination Fee Amount:</span> {{ $terminationFeeDisplay }}{!! isset($brokerMismatches['early_termination_fee_amount']) ? $mismatchBadge : '' !!}
                            </li>
                            @endif
                        @endif
                        @if (data_get($bid, 'get.retainer_fee_option'))
                        <li class="mb-1" style="{{ isset($brokerMismatches['retainer_fee_option']) ? $mismatchStyle : '' }}">
                            <span class="fw-semibold">Retainer Fee:</span> {{ data_get($bid, 'get.retainer_fee_option') }}{!! isset($brokerMismatches['retainer_fee_option']) ? $mismatchBadge : '' !!}
                        </li>
                            @if (data_get($bid, 'get.retainer_fee_option') === 'Yes')
                                @if (data_get($bid, 'get.retainer_fee_amount'))
                                <li class="mb-1" style="{{ isset($brokerMismatches['retainer_fee_amount']) ? $mismatchStyle : '' }}">
                                    <span class="fw-semibold">Retainer Fee Amount:</span> ${{ number_format((float)data_get($bid, 'get.retainer_fee_amount'), 2) }}{!! isset($brokerMismatches['retainer_fee_amount']) ? $mismatchBadge : '' !!}
                                </li>
                                @endif
                                @if (data_get($bid, 'get.retainer_fee_application'))
                                <li class="mb-1" style="{{ isset($brokerMismatches['retainer_fee_application']) ? $mismatchStyle : '' }}">
                                    <span class="fw-semibold">Retainer Fee Application:</span>
                                    @if (data_get($bid, 'get.retainer_fee_application') === 'applied')
                                    Applied toward final compensation
                                    @else
                                    Charged in addition to final compensation
                                    @endif
                                    {!! isset($brokerMismatches['retainer_fee_application']) ? $mismatchBadge : '' !!}
                                </li>
                                @endif
                            @endif
                        @endif
                        @if ($agencyTimeframeDisplay)
                        <li class="mb-1" style="{{ isset($brokerMismatches['agency_agreement_timeframe']) ? $mismatchStyle : '' }}">
                            <span class="fw-semibold">Tenant Agency Agreement Timeframe:</span> {{ $agencyTimeframeDisplay }}{!! isset($brokerMismatches['agency_agreement_timeframe']) ? $mismatchBadge : '' !!}
                        </li>
                        @endif
                    </ul>
                </div>
                @endif

                {{-- E) Brokerage Relationship --}}
                @if (data_get($bid, 'get.brokerage_relationship'))
                <div class="mb-4">
                    <h6 class="mb-2" style="color: #049399; font-weight: 600;">E) Brokerage Relationship</h6>
                    <ul class="list-unstyled ps-3 mb-0">
                        <li class="mb-1" style="{{ isset($brokerMismatches['brokerage_relationship']) ? $mismatchStyle : '' }}">
                            <span class="fw-semibold">Acceptable Brokerage Relationship:</span> {{ data_get($bid, 'get.brokerage_relationship') }}{!! isset($brokerMismatches['brokerage_relationship']) ? $mismatchBadge : '' !!}
                        </li>
                    </ul>
                </div>
                @endif

                {{-- F) Additional Terms --}}
                @if (data_get($bid, 'get.additional_details_broker'))
                <div class="mb-4">
                    <h6 class="mb-2" style="color: #049399; font-weight: 600;">F) Additional Terms</h6>
                    <ul class="list-unstyled ps-3 mb-0">
                        <li class="mb-1" style="{{ isset($brokerMismatches['additional_details_broker']) ? $mismatchStyle : '' }}">
                            <span class="fw-semibold">Additional Terms:</span> {{ data_get($bid, 'get.additional_details_broker') }}{!! isset($brokerMismatches['additional_details_broker']) ? $mismatchBadge : '' !!}
                        </li>
                    </ul>
                </div>
                @endif
            </div>
            @endif

            @if (data_get($bid, 'get.additional_details'))
            <div class="mb-5">
                <h6 class="section-header">
                    <i class="fa fa-file-lines me-2"></i>Additional Details
                </h6>
                <div class="field-value">{{ data_get($bid, 'get.additional_details') }}</div>
            </div>
            @endif

            @php
                // ── Services: canonical category-grouped display (matches hire_tenant_agent/view.blade.php modal) ───
                $normalizeService = fn($s) => \App\Helpers\TenantBidMatchScoreHelper::normalizeService((string)$s);

                // Baseline services from the listing (auction)
                $displayCatalog = \App\Helpers\TenantBidMatchScoreHelper::getCatalog($propType);
                $bsRaw = $auctionBaselineData['services'] ?? [];
                if (is_string($bsRaw)) $bsRaw = json_decode($bsRaw, true) ?? [];
                $bsRaw = is_array($bsRaw) ? array_values(array_filter($bsRaw)) : [];
                $bsRaw = array_values(array_filter($bsRaw, fn($s) => in_array($normalizeService((string)$s), $displayCatalog, true)));
                $bsOtherRaw = $auctionBaselineData['other_services'] ?? [];
                if (is_string($bsOtherRaw)) $bsOtherRaw = json_decode($bsOtherRaw, true) ?? [];
                $bsOtherRaw = is_array($bsOtherRaw) ? array_values(array_filter($bsOtherRaw, fn($s) => is_string($s) && !empty(trim($s)))) : [];
                $baselineServices = array_merge($bsRaw, $bsOtherRaw);

                // Category definitions (residential vs commercial — same as canonical)
                $bidIsCommercial = ($propType === 'Commercial Property');
                $bidResidentialCategories = [
                    '📢 Tenant Criteria Marketing & Promotion' => [
                        'Create a branded flyer summarizing the Tenant\'s rental criteria',
                        'Post the Tenant\'s rental criteria on Craigslist under the "Real Estate Wanted" section',
                        'Share the Tenant\'s rental criteria on Nextdoor in Neighborhood or Community Groups',
                        'Promote the Tenant\'s rental criteria on Facebook in Rental or Housing Groups',
                        'Share the Tenant\'s rental criteria on Instagram using posts, stories, or reels',
                        'Promote the Tenant\'s rental criteria on LinkedIn in Real Estate or Housing Groups',
                        'Upload a TikTok video summarizing the Tenant\'s rental criteria',
                        'Upload a YouTube video summarizing the Tenant\'s rental criteria',
                        'Launch a mass email campaign promoting the Tenant\'s rental criteria',
                        'Distribute branded postcards or flyers in the Tenant\'s preferred neighborhoods',
                        'Launch hyperlocal digital ads targeting the Tenant\'s preferred rental areas',
                    ],
                    '🔍 Property Search, Alerts & Matching' => [
                        'Send email alerts with new listings from the MLS that match the Tenant\'s rental criteria',
                        'Search for off-market, pre-market, withdrawn, canceled, or expired properties that meet the Tenant\'s rental criteria',
                        'Communicate with the Landlord\'s Agent, Landlord, or Property Manager to confirm availability, lease terms, and showing instructions',
                        'Evaluate properties with the Tenant and provide insights on pricing, lease terms, and overall fit',
                    ],
                    '🏡 Property Showings & Virtual Tours' => [
                        'Schedule and attend property showings with the Tenant',
                        'Coordinate or conduct virtual showings via live video or pre-recorded walkthroughs',
                        'Preview properties on behalf of the Tenant upon request',
                        'Provide factual observations on property layout and condition',
                    ],
                    '📝 Tenant Application Support' => [
                        'Provide the Tenant with application instructions or links to an online rental application platform',
                        'Gather and organize required supporting documents (e.g., identification, income verification, reference letters)',
                        'Submit complete and organized application packages to the Landlord\'s Agent, Landlord, or Property Manager for review',
                        'Answer questions about the application process, screening timelines, and required documentation',
                    ],
                    '📃 Lease Preparation & Execution' => [
                        'Review lease offers and assist the Tenant in preparing questions or requested changes',
                        'Coordinate lease negotiation with the Landlord\'s Agent, Landlord, or Property Manager',
                        'Assist with completing required lease disclosures and reviewing key lease terms',
                        'Assist with in-person or electronic lease signing, including e-signature setup and secure delivery of executed lease documents, addenda, and disclosures to all parties',
                    ],
                    '🚚 Move-In Support & Coordination' => [
                        'Coordinate move-in date and key handoff logistics with the Landlord\'s Agent, Landlord or Property Manager',
                        'Confirm completion of any agreed-upon pre-move-in cleaning or repairs',
                        'Provide a utility setup checklist and local provider resources',
                        'Share a move-in checklist for documentation and property condition review',
                        'Confirm required move-in payments and assist the Tenant with tracking amounts due, deadlines, and accepted payment methods',
                    ],
                    '💡 Leasing Strategy & Guidance' => [
                        'Provide a Rental Market Analysis (RMA) with pricing insights based on comparable rentals, neighborhood trends, and current market conditions',
                        'Advise on lease types and structures (e.g., month-to-month, annual, furnished, lease-option)',
                        'Provide general guidance on Tenant rights and Landlord responsibilities under state law',
                        'Provide general guidance on lease clauses, payment terms, and renewal options',
                    ],
                ];
                // ── Commercial categories: exact copy from hire_tenant_agent/view.blade.php (canonical source) ──
                $bidCommercialCategories = [
                    '📢 Tenant Criteria Marketing & Promotion' => [
                        'Create a branded flyer summarizing the Tenant\'s leasing criteria',
                        'Post the Tenant\'s leasing criteria on Craigslist under the "Office/Commercial" or "Retail" section',
                        'Promote the Tenant\'s leasing criteria on Facebook in Commercial Leasing or Business Groups',
                        'Share the Tenant\'s leasing criteria on Instagram using posts, stories, or reels',
                        'Promote the Tenant\'s leasing criteria on LinkedIn in Professional, Real Estate, or Commercial Investment Groups',
                        'Upload a TikTok video summarizing the Tenant\'s leasing criteria',
                        'Upload a YouTube video summarizing the Tenant\'s leasing criteria',
                        'Launch a mass email campaign promoting the Tenant\'s leasing criteria',
                        'Distribute branded postcards or flyers in the Tenant\'s preferred neighborhoods',
                        'Launch hyperlocal digital ads targeting the Tenant\'s preferred leasing areas',
                    ],
                    '🔍 Property Search, Alerts & Matching' => [
                        'Send listing alerts from real estate platforms that match the Tenant\'s leasing criteria.',
                        'Search for off-market, pre-market, withdrawn, canceled, or expired properties that meet the Tenant\'s rental criteria',
                        'Communicate with the Landlord\'s Agent, Landlord, or Property Manager to confirm availability, lease terms, and showing instructions',
                        'Evaluate properties for layout efficiency, building specs, logistics, zoning fit, and operational alignment',
                    ],
                    '🏢 Property Showings & Virtual Tours' => [
                        'Schedule and attend property tours with the Tenant',
                        'Coordinate or conduct virtual showings via live video or pre-recorded walkthroughs',
                        'Preview properties on behalf of the Tenant upon request',
                        'Provide factual notes on layout, access, parking, visibility, and other operational considerations',
                    ],
                    '📝 Tenant Application Support' => [
                        'Provide the Tenant with application instructions or links to online platforms',
                        'Gather and organize required supporting documents (e.g., business licenses, financials, references)',
                        'Submit complete and organized application packages to the Landlord\'s Agent, Landlord, or Property Manager',
                    ],
                    '📃 Lease Preparation, LOI & Execution' => [
                        'Draft or assist with preparing a Letter of Intent (LOI) summarizing the Tenant\'s business needs and proposed terms',
                        'Assist with negotiating rent, CAM, lease term, TI allowance, exclusivity clauses, renewal options, and other provisions (as permitted under the agency agreement)',
                        'Coordinate with the Landlord\'s Agent, Landlord or Property Manager to finalize lease terms',
                        'Review lease drafts and coordinate revisions through appropriate channels',
                        'Assist with in-person or electronic lease signing, including e-signature setup and secure delivery of executed lease documents, addenda, and disclosures to all parties',
                        'Track required deposits, rent commencement, and key lease dates to ensure move-in readiness',
                    ],
                    '🚚 Move-In Support & Coordination' => [
                        'Coordinate move-in date and key handoff logistics with the Landlord, Landlord\'s Agent, or Property Manager',
                        'Confirm completion of any agreed-upon pre-move-in repairs, cleaning, or buildout',
                        'Provide a utility setup checklist and local provider resources',
                        'Share a move-in checklist for documentation and property condition review',
                        'Confirm required move-in payments and assist the Tenant with tracking amounts due, deadlines, and accepted payment methods',
                    ],
                    '💡 Leasing Strategy & Guidance' => [
                        'Provide a Comparative Lease Market Analysis (CLMA) with pricing insights, comps, and vacancy trends',
                        'Advise on lease types and structures (e.g., NNN, Modified Gross, Full Service) with general explanations of differences',
                        'Provide general guidance on Tenant rights and Landlord responsibilities under commercial leasing law',
                        'Provide general guidance on lease clauses, escalation terms, and space usage considerations',
                    ],
                ];
                $bidCategories = $bidIsCommercial ? $bidCommercialCategories : $bidResidentialCategories;

                // Flatten bid services
                $flattenBidServices = function($data) use (&$flattenBidServices) {
                    $result = [];
                    if (is_array($data) || is_object($data)) {
                        foreach ((array)$data as $value) {
                            if (is_string($value) && !empty(trim($value)) && $value !== 'Other') {
                                $result[] = trim($value);
                            } elseif (is_array($value) || is_object($value)) {
                                $result = array_merge($result, $flattenBidServices($value));
                            }
                        }
                    } elseif (is_string($data) && !empty(trim($data)) && $data !== 'Other') {
                        $result[] = trim($data);
                    }
                    return $result;
                };
                $rawBidSvcs = data_get($bid, 'get.services', []);
                if (is_string($rawBidSvcs) && !empty($rawBidSvcs)) {
                    $decoded = json_decode($rawBidSvcs, true);
                    $parsedBidSvcs = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : [];
                } elseif (is_array($rawBidSvcs) || is_object($rawBidSvcs)) {
                    $parsedBidSvcs = $rawBidSvcs;
                } else {
                    $parsedBidSvcs = [];
                }
                $normalizeApostrophes = fn($s) => str_replace(
                    ["\u{2018}", "\u{2019}", "\u{201C}", "\u{201D}"],
                    ["'", "'", '"', '"'], $s
                );
                $bidAllServices = array_unique(array_map($normalizeApostrophes, $flattenBidServices($parsedBidSvcs)));
                $rawBidOtherSvcs = data_get($bid, 'get.other_services', []);
                if (is_string($rawBidOtherSvcs) && !empty($rawBidOtherSvcs)) {
                    $decoded = json_decode($rawBidOtherSvcs, true);
                    $bidOtherServices = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : [];
                } elseif (is_array($rawBidOtherSvcs) || is_object($rawBidOtherSvcs)) {
                    $bidOtherServices = (array)$rawBidOtherSvcs;
                } else {
                    $bidOtherServices = [];
                }
                $bidOtherServices = array_values(array_filter($bidOtherServices, fn($s) => is_string($s) && !empty(trim($s))));
                $hasAnyBidServices = !empty($bidAllServices) || !empty($bidOtherServices);

                // Badge / style vars
                $svcAddedStyle  = 'background-color: #d4edda; padding: 2px 6px; border-radius: 4px; border-left: 3px solid #28a745;';
                $svcAddedBadge  = '<span class="badge bg-success ms-2" style="font-size: 0.65rem; vertical-align: middle;">Extra Service Offered</span>';
                $svcMissingStyle = 'background-color: #ffe6e6; padding: 2px 6px; border-radius: 4px; border-left: 3px solid #dc3545; text-decoration: line-through; color: #721c24;';
                $svcMissingBadge = '<span class="badge bg-danger ms-2" style="font-size: 0.65rem; vertical-align: middle;">Not Offered by Agent</span>';

                // Full normalized sets for badge decisions
                $baselineNormFull = array_unique(array_map($normalizeService, $baselineServices));
                $bidNormFull = array_unique(array_map(
                    $normalizeService,
                    array_merge(array_values($bidAllServices), $bidOtherServices)
                ));
                $checkServiceInBaseline = fn($s) => in_array($normalizeService($s), $baselineNormFull, true);
                $checkServiceInBid      = fn($s) => in_array($normalizeService($s), $bidNormFull, true);

                // Services in baseline but not in bid
                $svsMissingDisplay = [];
                foreach ($baselineServices as $bSvc) {
                    if (!$checkServiceInBid($bSvc)) {
                        $svsMissingDisplay[] = $bSvc;
                    }
                }
            @endphp

            <div class="mb-5">
                <h6 class="section-header">
                    <i class="fa fa-list-check me-2"></i>Offered Services
                </h6>

                @if ($hasAnyBidServices)
                    @foreach ($bidCategories as $bidCategoryName => $bidCategoryServices)
                        @php
                            $bidMatchedInCat = array_filter($bidAllServices, fn($s) => in_array($s, $bidCategoryServices));
                        @endphp
                        @if (!empty($bidMatchedInCat))
                        <div class="mb-3">
                            <div class="fw-bold" style="color: #34465c; font-size: 0.95rem;">{{ $bidCategoryName }}</div>
                            <ul class="mb-0" style="margin-top: 0.25rem; padding-left: 1.5rem; list-style: disc;">
                                @foreach ($bidMatchedInCat as $bidSvc)
                                    @php $svcInBaseline = $checkServiceInBaseline($bidSvc); @endphp
                                    <li style="font-size: 0.9rem; margin-bottom: 4px; {{ !$svcInBaseline ? $svcAddedStyle : '' }}">{{ $bidSvc }}{!! !$svcInBaseline ? $svcAddedBadge : '' !!}</li>
                                @endforeach
                            </ul>
                        </div>
                        @endif
                    @endforeach

                    @if (!empty($bidOtherServices))
                    <div class="mb-3">
                        <div class="fw-bold" style="color: #34465c; font-size: 0.95rem;">✍️ Additional Services</div>
                        <ul class="mb-0" style="margin-top: 0.25rem; padding-left: 1.5rem; list-style: disc;">
                            @foreach ($bidOtherServices as $otherSvc)
                                @php $svcInBaseline = $checkServiceInBaseline($otherSvc); @endphp
                                <li style="font-size: 0.9rem; margin-bottom: 4px; {{ !$svcInBaseline ? $svcAddedStyle : '' }}">{{ $otherSvc }}{!! !$svcInBaseline ? $svcAddedBadge : '' !!}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif

                    @if (!empty($svsMissingDisplay))
                    <div class="mt-4 p-3" style="background-color: #ffe6e6; border-radius: 8px; border: 1px solid #dc3545;">
                        <div class="fw-bold mb-2" style="color: #721c24; font-size: 0.95rem;">
                            <i class="fa fa-circle-xmark me-2"></i>Services Requested But Agent Did Not Include ({{ count($svsMissingDisplay) }})
                        </div>
                        <ul class="mb-0" style="padding-left: 1.5rem; list-style: disc;">
                            @foreach ($svsMissingDisplay as $missingSvc)
                                <li style="font-size: 0.9rem; margin-bottom: 4px; {{ $svcMissingStyle }}">{{ $missingSvc }}{!! $svcMissingBadge !!}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif
                @else
                    <div class="text-muted" style="font-style: italic;">No services selected for this bid.</div>
                @endif
            </div>

            @if (data_get($bid, 'get.presentation_link') || data_get($bid, 'get.video_upload') || data_get($bid, 'get.business_card_link') || data_get($bid, 'get.business_card') || data_get($bid, 'get.promoMaterials'))
            <div class="mb-5">
                <h6 class="section-header">
                    <i class="fa fa-photo-film me-2"></i>Agent Presentation & Promotional Materials
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
                        <i class="fa fa-arrow-up-right-from-square me-1"></i>Watch Presentation
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
                        <i class="fa fa-arrow-up-right-from-square me-1"></i>View Business Card (Link)
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
                                <i class="fa fa-arrow-up-right-from-square me-1"></i>Open Link
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

                    @if (data_get($bid, 'get.license_no'))
                    <div class="col-md-6 mb-2">
                        <div class="field-label" style="color: #049399;">License Number</div>
                        <div class="field-value">{{ data_get($bid, 'get.license_no') }}</div>
                    </div>
                    @endif

                    @if (data_get($bid, 'get.nar_id'))
                    <div class="col-md-6 mb-2">
                        <div class="field-label" style="color: #049399;">NAR Member ID</div>
                        <div class="field-value">{{ data_get($bid, 'get.nar_id') }}</div>
                    </div>
                    @endif

                    @if (data_get($bid, 'get.phone'))
                    <div class="col-md-6 mb-2">
                        <div class="field-label" style="color: #049399;">Phone Number</div>
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


    <x-slot name="footerActions">
        @if($bidStatus === 'Active')
        <form action="{{ route('tenant.hire.agent.auction.bid.accept') }}" method="POST" class="d-inline">
            @csrf
            <input type="hidden" name="bid_id" value="{{ $bid->id }}">
            <input type="hidden" name="auction_id" value="{{ $auction->id }}">
            <button type="submit" class="btn btn-success"
                    style="min-width: 120px; height: 40px; display: inline-flex; align-items: center; justify-content: center;"
                    onclick="return confirm('Accept this bid?')">
                <i class="fa-solid fa-check me-1"></i>Accept Bid
            </button>
        </form>
        <a href="{{ route('tenant.counter-terms', $bid->id) }}" class="btn btn-primary"
           style="min-width: 120px; height: 40px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none;">
            <i class="fa-solid fa-right-left me-1"></i>Counter Bid
        </a>
        <form action="{{ route('tenant.hire.agent.auction.bid.reject') }}" method="POST" class="d-inline">
            @csrf
            <input type="hidden" name="bid_id" value="{{ $bid->id }}">
            <input type="hidden" name="auction_id" value="{{ $auction->id }}">
            <button type="submit" class="btn btn-danger"
                    style="min-width: 120px; height: 40px; display: inline-flex; align-items: center; justify-content: center;"
                    onclick="return confirm('Reject this bid?')">
                <i class="fa-solid fa-xmark me-1"></i>Reject Bid
            </button>
        </form>
        @elseif($bidStatus === 'Countered')
        <span class="btn" style="background: #fff3cd; color: #856404; border: 1px solid #ffc107;">
            <i class="fa-solid fa-clock me-1"></i>Awaiting Response
        </span>
        @elseif($bidStatus === 'Accepted')
        <a href="{{ route('tenant.agent.auction.view', $auction->id) }}" class="btn btn-success">
            <i class="fa-solid fa-file-contract me-1"></i>View Summary
        </a>
        @endif
    </x-slot>

</x-bid-detail-layout>
@endsection
