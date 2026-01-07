@extends('layouts.main')

@section('content')
<div class="container py-5">
    <div class="row">
        <div class="col-12">
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                    @if($viewerRole === 'agent')
                    <li class="breadcrumb-item"><a href="{{ route('myBids', 'tenant-agent') }}">My Bids</a></li>
                    @else
                    <li class="breadcrumb-item"><a href="{{ route('tenant.agent.auction.view', $auction->id) }}">Listing</a></li>
                    @endif
                    <li class="breadcrumb-item active">Counter Terms</li>
                </ol>
            </nav>
            
            <div class="card mb-4" style="border: 2px solid #049399; border-radius: 8px;">
                <div class="card-header" style="background: linear-gradient(135deg, #049399 0%, #037a7f 100%); color: white;">
                    <h4 class="mb-0">
                        <i class="fas fa-exchange-alt me-2"></i>
                        @if($viewerRole === 'agent')
                        Tenant's Counter Terms For Your Bid
                        @else
                        Agent's Counter Terms
                        @endif
                    </h4>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6 style="color: #049399; font-weight: 600;">Listing Information</h6>
                            <p><strong>Title:</strong> {{ $auction->title ?? 'Hire a Tenant Agent Residential' }}</p>
                            <p><strong>Address:</strong> {{ $auction->get->address ?? 'N/A' }}</p>
                            <p><strong>Listing Owner:</strong> {{ $auction->user->name ?? 'N/A' }}</p>
                        </div>
                        <div class="col-md-6">
                            <h6 style="color: #049399; font-weight: 600;">Bid Information</h6>
                            @php
                                $bidStatus = $bid->bid_status ?? 'Active';
                                $statusColors = [
                                    'Countered' => 'background-color: #ffc107; color: #000;',
                                    'Accepted' => 'background-color: #28a745; color: #fff;',
                                    'Rejected' => 'background-color: #dc3545; color: #fff;',
                                    'Active' => 'background-color: #007bff; color: #fff;',
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
                    
                    @php
                        // Determine which counter to show based on viewer role
                        $activeCounter = null;
                        $counterPartyName = '';
                        
                        if ($viewerRole === 'agent') {
                            // Agent viewing - show tenant's counter
                            $activeCounter = $tenantCounter;
                            $counterPartyName = 'Tenant';
                        } else {
                            // Tenant viewing - show agent's counter
                            $activeCounter = $agentCounter;
                            $counterPartyName = 'Agent';
                        }
                    @endphp
                    
                    @if($activeCounter)
                    @php
                        $counterData = $activeCounter->getAllMeta();
                        
                        $fmtMoney = function($v) {
                            if (empty($v) || !is_numeric($v)) return null;
                            return '$' . number_format((float)$v, 2);
                        };
                        $fmtPercent = function($v) {
                            if (empty($v) || !is_numeric($v)) return null;
                            return rtrim(rtrim(number_format((float)$v, 2), '0'), '.') . '%';
                        };
                        $joinParts = function(array $parts) {
                            return implode(' + ', array_filter($parts)) ?: null;
                        };
                        
                        // Baseline data for comparison
                        if ($viewerRole === 'agent') {
                            // Agent comparing tenant's counter to agent's original bid
                            $baselineData = (array) $bid->get;
                            $baselineLabel = 'Your Original Bid';
                        } else {
                            // Tenant comparing agent's counter to tenant's original listing terms
                            $baselineData = [
                                'commission_structure' => $auction->get->commission_structure ?? null,
                                'lease_fee_type' => $auction->get->lease_fee_type ?? null,
                                'lease_fee_flat' => $auction->get->lease_fee_flat ?? null,
                                'lease_fee_percentage' => $auction->get->lease_fee_percentage ?? null,
                                'payment_timing' => $auction->get->broker_fee_timing ?? null,
                                'days_to_pay' => $auction->get->broker_fee_days_from_rent ?? $auction->get->broker_fee_days_after_lease ?? null,
                                'interested_purchase_fee_type' => $auction->get->interested_purchase_fee_type ?? null,
                                'purchase_fee_type' => $auction->get->purchase_fee_type ?? null,
                                'interested_lease_option_agreement' => $auction->get->interested_lease_option_agreement ?? null,
                                'lease_type' => $auction->get->lease_type ?? null,
                                'lease_value' => $auction->get->lease_value ?? null,
                                'purchase_type' => $auction->get->purchase_type ?? null,
                                'purchase_value' => $auction->get->purchase_value ?? null,
                                'protection_period' => $auction->get->protection_period ?? null,
                                'early_termination_fee_option' => $auction->get->early_termination_fee_option ?? null,
                                'early_termination_fee_amount' => $auction->get->early_termination_fee_amount ?? null,
                                'retainer_fee_option' => $auction->get->retainer_fee_option ?? null,
                                'retainer_fee_amount' => $auction->get->retainer_fee_amount ?? null,
                                'retainer_fee_application' => $auction->get->retainer_fee_application ?? null,
                                'agency_agreement_timeframe' => $auction->get->agency_agreement_timeframe ?? null,
                                'brokerage_relationship' => $auction->get->brokerage_relationship ?? null,
                                'services' => $auction->get->services ?? [],
                                'other_services' => $auction->get->other_services ?? [],
                            ];
                            $baselineLabel = 'Your Original Listing Terms';
                        }
                        
                        $normalizeForMatch = function($v) {
                            if (is_null($v) || $v === '') return '';
                            if (is_array($v) || is_object($v)) return json_encode($v);
                            $v = trim((string) $v);
                            return preg_replace('/[\s$,%]/', '', strtolower($v));
                        };
                        
                        $brokerFields = [
                            'commission_structure', 'lease_fee_type', 'payment_timing', 'days_to_pay',
                            'interested_purchase_fee_type', 'purchase_fee_type', 'interested_lease_option_agreement',
                            'lease_type', 'lease_value', 'purchase_type', 'purchase_value', 'protection_period',
                            'early_termination_fee_option', 'early_termination_fee_amount', 'retainer_fee_option',
                            'retainer_fee_amount', 'retainer_fee_application', 'agency_agreement_timeframe', 'brokerage_relationship',
                        ];
                        
                        $brokerMatched = 0;
                        $brokerTotal = 0;
                        $brokerMismatches = [];
                        
                        foreach ($brokerFields as $field) {
                            $counterVal = $counterData[$field] ?? null;
                            $baselineVal = $baselineData[$field] ?? null;
                            if (!empty($counterVal) || !empty($baselineVal)) {
                                $brokerTotal++;
                                if ($normalizeForMatch($counterVal) === $normalizeForMatch($baselineVal)) {
                                    $brokerMatched++;
                                } else {
                                    $brokerMismatches[$field] = ['counter' => $counterVal, 'baseline' => $baselineVal];
                                }
                            }
                        }
                        
                        $brokerScore = $brokerTotal > 0 ? round(($brokerMatched / $brokerTotal) * 100) : 100;
                        
                        $counterServices = $counterData['services'] ?? [];
                        if (is_string($counterServices)) {
                            $counterServices = json_decode($counterServices, true) ?? [];
                        }
                        $counterServices = is_array($counterServices) ? array_values(array_filter($counterServices)) : [];
                        
                        $counterOtherServices = $counterData['other_services'] ?? [];
                        if (is_string($counterOtherServices)) {
                            $counterOtherServices = json_decode($counterOtherServices, true) ?? [];
                        }
                        $counterOtherServices = is_array($counterOtherServices) ? array_values(array_filter($counterOtherServices, fn($s) => is_string($s) && !empty(trim($s)))) : [];
                        $allCounterServices = array_merge($counterServices, $counterOtherServices);
                        
                        $baselineServices = $baselineData['services'] ?? [];
                        if (is_string($baselineServices)) {
                            $baselineServices = json_decode($baselineServices, true) ?? [];
                        }
                        $baselineServices = is_array($baselineServices) ? array_values(array_filter($baselineServices)) : [];
                        
                        $baselineOtherServices = $baselineData['other_services'] ?? [];
                        if (is_string($baselineOtherServices)) {
                            $baselineOtherServices = json_decode($baselineOtherServices, true) ?? [];
                        }
                        $baselineOtherServices = is_array($baselineOtherServices) ? array_values(array_filter($baselineOtherServices, fn($s) => is_string($s) && !empty(trim($s)))) : [];
                        $allBaselineServices = array_merge($baselineServices, $baselineOtherServices);
                        
                        $normalizeService = function($s) {
                            $s = preg_replace('/[\x{2018}\x{2019}]/u', "'", $s);
                            $s = preg_replace('/[\x{201C}\x{201D}]/u', '"', $s);
                            return strtolower(trim($s));
                        };
                        
                        $counterNorm = array_map($normalizeService, $allCounterServices);
                        $baselineNorm = array_map($normalizeService, $allBaselineServices);
                        
                        $allServicesUnion = array_unique(array_merge($counterNorm, $baselineNorm));
                        $servicesTotal = count($allServicesUnion);
                        $servicesMatched = 0;
                        $servicesMissing = [];
                        $servicesAdded = [];
                        
                        foreach ($allServicesUnion as $svc) {
                            $inCounter = in_array($svc, $counterNorm);
                            $inBaseline = in_array($svc, $baselineNorm);
                            if ($inCounter && $inBaseline) {
                                $servicesMatched++;
                            } elseif ($inCounter && !$inBaseline) {
                                $servicesAdded[] = $svc;
                            } elseif (!$inCounter && $inBaseline) {
                                $servicesMissing[] = $svc;
                            }
                        }
                        
                        $servicesScore = $servicesTotal > 0 ? round(($servicesMatched / $servicesTotal) * 100) : 100;
                        
                        $hasBroker = $brokerTotal > 0;
                        $hasServices = $servicesTotal > 0;
                        
                        if ($hasBroker && $hasServices) {
                            $totalScore = round(($brokerScore + $servicesScore) / 2);
                        } elseif ($hasBroker) {
                            $totalScore = $brokerScore;
                        } elseif ($hasServices) {
                            $totalScore = $servicesScore;
                        } else {
                            $totalScore = 100;
                        }
                        
                        $getScoreColor = function($score) {
                            if ($score >= 80) return '#28a745';
                            if ($score >= 50) return '#ffc107';
                            return '#dc3545';
                        };
                        
                        $totalScoreColor = $getScoreColor($totalScore);
                        
                        $mismatchStyle = 'background-color: #ffe6e6; padding: 2px 6px; border-radius: 4px; border-left: 3px solid #dc3545;';
                        $mismatchBadge = '<span class="badge" style="background-color: #dc3545; color: white; font-size: 0.7rem; vertical-align: middle; margin-left: 8px;">Mismatch</span>';
                        $addedStyle = 'background-color: #e6ffe6; padding: 2px 6px; border-radius: 4px; border-left: 3px solid #28a745;';
                        $addedBadge = '<span class="badge" style="background-color: #28a745; color: white; font-size: 0.7rem; vertical-align: middle; margin-left: 8px;">Added</span>';
                        $missingStyle = 'background-color: #fff3cd; padding: 2px 6px; border-radius: 4px; border-left: 3px solid #ffc107;';
                        $missingBadge = '<span class="badge" style="background-color: #ffc107; color: #000; font-size: 0.7rem; vertical-align: middle; margin-left: 8px;">Not in Counter</span>';
                        
                        $residentialCategories = [
                            "📢 Tenant Criteria Marketing & Promotion" => [
                                "Create a branded flyer summarizing the Tenant's rental criteria",
                                "Post the Tenant's rental criteria on Craigslist under the \"Real Estate Wanted\" section",
                                "Share the Tenant's rental criteria on Nextdoor in Neighborhood or Community Groups",
                                "Promote the Tenant's rental criteria on Facebook in Rental or Housing Groups",
                                "Share the Tenant's rental criteria on Instagram using posts, stories, or reels",
                                "Promote the Tenant's rental criteria on LinkedIn in Real Estate or Housing Groups",
                                "Upload a TikTok video summarizing the Tenant's rental criteria",
                                "Upload a YouTube video summarizing the Tenant's rental criteria",
                                "Launch a mass email campaign promoting the Tenant's rental criteria",
                                "Distribute branded postcards or flyers in the Tenant's preferred neighborhoods",
                                "Launch hyperlocal digital ads targeting the Tenant's preferred rental areas",
                            ],
                            "🔍 Property Search, Alerts & Matching" => [
                                "Send email alerts with new listings from the MLS that match the Tenant's rental criteria",
                                "Search for off-market, pre-market, withdrawn, canceled, or expired properties that meet the Tenant's rental criteria",
                                "Communicate with the Landlord's Agent, Landlord, or Property Manager to confirm availability, lease terms, and showing instructions",
                                "Evaluate properties with the Tenant and provide insights on pricing, lease terms, and overall fit",
                            ],
                            "🏡 Property Showings & Virtual Tours" => [
                                "Schedule and attend property showings with the Tenant",
                                "Coordinate or conduct virtual showings via live video or pre-recorded walkthroughs",
                                "Preview properties on behalf of the Tenant upon request",
                                "Provide factual observations on property layout and condition",
                            ],
                            "📝 Tenant Application Support" => [
                                "Provide the Tenant with application instructions or links to an online rental application platform",
                                "Gather and organize required supporting documents (e.g., identification, income verification, reference letters)",
                                "Submit complete and organized application packages to the Landlord's Agent, Landlord, or Property Manager for review",
                                "Answer questions about the application process, screening timelines, and required documentation",
                            ],
                            "📃 Lease Preparation & Execution" => [
                                "Review lease offers and assist the Tenant in preparing questions or requested changes",
                                "Coordinate lease negotiation with the Landlord's Agent, Landlord, or Property Manager",
                                "Assist with completing required lease disclosures and reviewing key lease terms",
                                "Assist with in-person or electronic lease signing, including e-signature setup and secure delivery of executed lease documents, addenda, and disclosures to all parties",
                            ],
                            "🚚 Move-In Support & Coordination" => [
                                "Coordinate move-in date and key handoff logistics with the Landlord's Agent, Landlord or Property Manager",
                                "Confirm completion of any agreed-upon pre-move-in cleaning or repairs",
                                "Provide a utility setup checklist and local provider resources",
                                "Share a move-in checklist for documentation and property condition review",
                                "Confirm required move-in payments and assist the Tenant with tracking amounts due, deadlines, and accepted payment methods",
                            ],
                            "💡 Leasing Strategy & Guidance" => [
                                "Provide a Rental Market Analysis (RMA) with pricing insights based on comparable rentals, neighborhood trends, and current market conditions",
                                "Advise on lease types and structures (e.g., month-to-month, annual, furnished, lease-option)",
                                "Provide general guidance on Tenant rights and Landlord responsibilities under state law",
                                "Provide general guidance on lease clauses, payment terms, and renewal options",
                            ],
                        ];
                        
                        $categories = $residentialCategories;
                    @endphp
                    
                    <div class="border rounded p-4 mb-4" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 style="color: #049399; font-weight: 600; margin: 0;">
                                <i class="fas fa-file-contract me-2"></i>{{ $counterPartyName }}'s Counter Terms
                            </h5>
                            <span class="text-muted small">Last updated: {{ $activeCounter->updated_at->format('M d, Y h:i A') }}</span>
                        </div>
                        
                        <div class="match-score-panel mb-4 p-3" style="background: white; border-radius: 10px; border: 1px solid #dee2e6;">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span style="font-weight: 600; color: #1a3a5c; font-size: 1.1rem;">
                                    <i class="fas fa-chart-pie me-2"></i>Match Score
                                </span>
                                <span class="badge" style="background: {{ $totalScoreColor }}; font-size: 1.1rem; padding: 8px 16px; color: white;">
                                    {{ $totalScore }}%
                                </span>
                            </div>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <div class="d-flex justify-content-between align-items-center p-2" style="background: #f8f9fa; border-radius: 6px;">
                                        <span class="text-muted">Broker Compensation:</span>
                                        <span style="color: {{ $getScoreColor($brokerScore) }}; font-weight: 600;">{{ $brokerScore }}%</span>
                                    </div>
                                    <div class="text-muted small mt-1 ps-2">{{ $brokerMatched }}/{{ $brokerTotal }} fields matched</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex justify-content-between align-items-center p-2" style="background: #f8f9fa; border-radius: 6px;">
                                        <span class="text-muted">Services:</span>
                                        <span style="color: {{ $getScoreColor($servicesScore) }}; font-weight: 600;">{{ $servicesScore }}%</span>
                                    </div>
                                    <div class="text-muted small mt-1 ps-2">{{ $servicesMatched }}/{{ $servicesTotal }} services matched</div>
                                </div>
                            </div>
                            <div class="mt-2 small text-muted">
                                <i class="fas fa-info-circle me-1"></i>Compared to: {{ $baselineLabel }}
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <h6 class="mb-3" style="color: #049399; font-weight: 600; border-bottom: 2px solid #049399; padding-bottom: 8px;">
                                <i class="fas fa-handshake me-2"></i>Broker Compensation & Agency Agreement Terms
                            </h6>
                            
                            @if (!empty($counterData['commission_structure']) || !empty($counterData['lease_fee_type']) || !empty($counterData['payment_timing']))
                            <div class="mb-4">
                                <h6 class="mb-2" style="color: #049399; font-weight: 600;">A) Broker Compensation</h6>
                                <ul class="list-unstyled ps-3 mb-0">
                                    @if (!empty($counterData['commission_structure']))
                                    <li class="mb-2" style="{{ isset($brokerMismatches['commission_structure']) ? $mismatchStyle : '' }}">
                                        <span class="fw-semibold">Broker Commission Structure:</span> 
                                        {{ $counterData['commission_structure'] === 'Out-of-Pocket Payment' ? 'Tenant Pays Out-of-Pocket' : ($counterData['commission_structure'] === 'Included in Offer' ? 'Requested From Landlord in the Offer' : $counterData['commission_structure']) }}
                                        {!! isset($brokerMismatches['commission_structure']) ? $mismatchBadge : '' !!}
                                    </li>
                                    @endif
                                    @if (!empty($counterData['lease_fee_type']))
                                    @php
                                        $leaseFeeType = $counterData['lease_fee_type'] ?? '';
                                        $leaseFeeCombined = '—';
                                        
                                        if ($leaseFeeType === 'Flat Fee' && !empty($counterData['lease_fee_flat'])) {
                                            $leaseFeeCombined = $fmtMoney($counterData['lease_fee_flat']);
                                        } elseif ($leaseFeeType === 'Percentage of the Gross Lease Value' && !empty($counterData['lease_fee_percentage'])) {
                                            $leaseFeeCombined = $fmtPercent($counterData['lease_fee_percentage']) . ' of Gross Lease Value';
                                        } elseif ($leaseFeeType === 'Percentage of Monthly Rent' && !empty($counterData['lease_fee_percentage_monthly_rent'])) {
                                            $display = $fmtPercent($counterData['lease_fee_percentage_monthly_rent']) . ' of Monthly Rent';
                                            if (!empty($counterData['lease_fee_percentage_monthly_number'])) {
                                                $display .= ' x ' . $counterData['lease_fee_percentage_monthly_number'] . ' Months';
                                            }
                                            $leaseFeeCombined = $display;
                                        } elseif ($leaseFeeType === 'Flat Fee + Percentage of the Gross Lease Value') {
                                            $leaseFeeCombined = $joinParts([
                                                $fmtMoney($counterData['lease_fee_flat_combo'] ?? null),
                                                !empty($counterData['lease_fee_percentage_combo']) ? ($fmtPercent($counterData['lease_fee_percentage_combo']) . ' of Gross Lease Value') : null,
                                            ]) ?? '—';
                                        } else {
                                            $leaseFeeCombined = $leaseFeeType;
                                        }
                                    @endphp
                                    <li class="mb-2" style="{{ isset($brokerMismatches['lease_fee_type']) ? $mismatchStyle : '' }}">
                                        <span class="fw-semibold">Broker Commission Fee:</span> {{ $leaseFeeCombined }}
                                        {!! isset($brokerMismatches['lease_fee_type']) ? $mismatchBadge : '' !!}
                                    </li>
                                    @endif
                                    @if (!empty($counterData['payment_timing']))
                                    <li class="mb-2" style="{{ isset($brokerMismatches['payment_timing']) ? $mismatchStyle : '' }}">
                                        <span class="fw-semibold">Payment Timing:</span> {{ $counterData['payment_timing'] }}
                                        {!! isset($brokerMismatches['payment_timing']) ? $mismatchBadge : '' !!}
                                    </li>
                                    @endif
                                    @if (!empty($counterData['days_to_pay']))
                                    <li class="mb-2" style="{{ isset($brokerMismatches['days_to_pay']) ? $mismatchStyle : '' }}">
                                        <span class="fw-semibold">Calendar Days To Pay:</span> {{ $counterData['days_to_pay'] }}
                                        {!! isset($brokerMismatches['days_to_pay']) ? $mismatchBadge : '' !!}
                                    </li>
                                    @endif
                                </ul>
                            </div>
                            @endif
                            
                            @if (!empty($counterData['interested_purchase_fee_type']))
                            <div class="mb-4">
                                <h6 class="mb-2" style="color: #049399; font-weight: 600;">B) Purchase Fee Details</h6>
                                <ul class="list-unstyled ps-3 mb-0">
                                    <li class="mb-2" style="{{ isset($brokerMismatches['interested_purchase_fee_type']) ? $mismatchStyle : '' }}">
                                        <span class="fw-semibold">Interested in Purchasing a Property:</span> {{ $counterData['interested_purchase_fee_type'] }}
                                        {!! isset($brokerMismatches['interested_purchase_fee_type']) ? $mismatchBadge : '' !!}
                                    </li>
                                    @if ($counterData['interested_purchase_fee_type'] === 'Yes' && !empty($counterData['purchase_fee_type']))
                                    <li class="mb-2" style="{{ isset($brokerMismatches['purchase_fee_type']) ? $mismatchStyle : '' }}">
                                        <span class="fw-semibold">Purchase Fee:</span> {{ $counterData['purchase_fee_type'] }}
                                        {!! isset($brokerMismatches['purchase_fee_type']) ? $mismatchBadge : '' !!}
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
                                        <li class="mb-2" style="{{ isset($brokerMismatches['lease_value']) ? $mismatchStyle : '' }}">
                                            <span class="fw-semibold">Compensation (When Option Is Created):</span> 
                                            {{ ($counterData['lease_type'] ?? '') === 'percent' ? $fmtPercent($counterData['lease_value']) : $fmtMoney($counterData['lease_value']) }}
                                            {!! isset($brokerMismatches['lease_value']) ? $mismatchBadge : '' !!}
                                        </li>
                                        @endif
                                        @if (!empty($counterData['purchase_value']))
                                        <li class="mb-2" style="{{ isset($brokerMismatches['purchase_value']) ? $mismatchStyle : '' }}">
                                            <span class="fw-semibold">Compensation (If Purchase Option Exercised):</span> 
                                            {{ ($counterData['purchase_type'] ?? '') === 'percent' ? $fmtPercent($counterData['purchase_value']) : $fmtMoney($counterData['purchase_value']) }}
                                            {!! isset($brokerMismatches['purchase_value']) ? $mismatchBadge : '' !!}
                                        </li>
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
                                        <span class="fw-semibold">Protection Period Timeframe:</span> {{ $counterData['protection_period'] }} days
                                        {!! isset($brokerMismatches['protection_period']) ? $mismatchBadge : '' !!}
                                    </li>
                                    @endif
                                    @if (!empty($counterData['early_termination_fee_option']))
                                    <li class="mb-2" style="{{ isset($brokerMismatches['early_termination_fee_option']) ? $mismatchStyle : '' }}">
                                        <span class="fw-semibold">Early Termination Fee:</span> {{ $counterData['early_termination_fee_option'] }}
                                        {!! isset($brokerMismatches['early_termination_fee_option']) ? $mismatchBadge : '' !!}
                                    </li>
                                    @if ($counterData['early_termination_fee_option'] === 'Yes' && !empty($counterData['early_termination_fee_amount']))
                                    <li class="mb-2" style="{{ isset($brokerMismatches['early_termination_fee_amount']) ? $mismatchStyle : '' }}">
                                        <span class="fw-semibold">Termination Fee Amount:</span> {{ $fmtMoney($counterData['early_termination_fee_amount']) }}
                                        {!! isset($brokerMismatches['early_termination_fee_amount']) ? $mismatchBadge : '' !!}
                                    </li>
                                    @endif
                                    @endif
                                    @if (!empty($counterData['retainer_fee_option']))
                                    <li class="mb-2" style="{{ isset($brokerMismatches['retainer_fee_option']) ? $mismatchStyle : '' }}">
                                        <span class="fw-semibold">Retainer Fee:</span> {{ $counterData['retainer_fee_option'] }}
                                        {!! isset($brokerMismatches['retainer_fee_option']) ? $mismatchBadge : '' !!}
                                    </li>
                                    @if ($counterData['retainer_fee_option'] === 'Yes')
                                        @if (!empty($counterData['retainer_fee_amount']))
                                        <li class="mb-2" style="{{ isset($brokerMismatches['retainer_fee_amount']) ? $mismatchStyle : '' }}">
                                            <span class="fw-semibold">Retainer Fee Amount:</span> {{ $fmtMoney($counterData['retainer_fee_amount']) }}
                                            {!! isset($brokerMismatches['retainer_fee_amount']) ? $mismatchBadge : '' !!}
                                        </li>
                                        @endif
                                        @if (!empty($counterData['retainer_fee_application']))
                                        <li class="mb-2" style="{{ isset($brokerMismatches['retainer_fee_application']) ? $mismatchStyle : '' }}">
                                            <span class="fw-semibold">Retainer Fee Application:</span> 
                                            @if ($counterData['retainer_fee_application'] === 'applied')
                                            Applied toward final compensation
                                            @else
                                            Charged in addition to final compensation
                                            @endif
                                            {!! isset($brokerMismatches['retainer_fee_application']) ? $mismatchBadge : '' !!}
                                        </li>
                                        @endif
                                    @endif
                                    @endif
                                    @if (!empty($counterData['agency_agreement_timeframe']))
                                    @php
                                        $agencyTimeframe = $counterData['agency_agreement_timeframe'] ?? '';
                                        $agencyTimeframeCustom = $counterData['agency_agreement_custom'] ?? '';
                                        $isOtherTimeframe = is_string($agencyTimeframe) && strtolower(trim($agencyTimeframe)) === 'other';
                                        $agencyTimeframeDisplay = $isOtherTimeframe ? ($agencyTimeframeCustom ?: 'Other') : ($agencyTimeframe ?: '');
                                    @endphp
                                    <li class="mb-2" style="{{ isset($brokerMismatches['agency_agreement_timeframe']) ? $mismatchStyle : '' }}">
                                        <span class="fw-semibold">Agency Agreement Timeframe:</span> {{ $agencyTimeframeDisplay }}
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
                            
                            @foreach ($categories as $categoryName => $categoryServices)
                                @php
                                    $matchedServicesInCategory = [];
                                    foreach ($allCounterServices as $service) {
                                        foreach ($categoryServices as $catService) {
                                            if ($normalizeService($service) === $normalizeService($catService)) {
                                                $isInBaseline = in_array($normalizeService($service), $baselineNorm);
                                                $matchedServicesInCategory[] = ['service' => $service, 'inBaseline' => $isInBaseline];
                                                break;
                                            }
                                        }
                                    }
                                @endphp
                                @if (!empty($matchedServicesInCategory))
                                <div class="mt-3">
                                    <strong>{{ $categoryName }}</strong>
                                    <ul class="list-unstyled ps-3 mt-2">
                                        @foreach ($matchedServicesInCategory as $serviceData)
                                        <li class="mb-1" style="{{ !$serviceData['inBaseline'] ? $addedStyle : '' }}">
                                            <i class="fas fa-check-circle me-2" style="color: #049399;"></i>{{ $serviceData['service'] }}
                                            @if (!$serviceData['inBaseline'])
                                            {!! $addedBadge !!}
                                            @endif
                                        </li>
                                        @endforeach
                                    </ul>
                                </div>
                                @endif
                            @endforeach
                            
                            @if (!empty($counterOtherServices))
                            <div class="mt-3">
                                <strong>✍️ Additional Services</strong>
                                <ul class="list-unstyled ps-3 mt-2">
                                    @foreach ($counterOtherServices as $otherService)
                                    @php
                                        $isInBaseline = in_array($normalizeService($otherService), $baselineNorm);
                                    @endphp
                                    <li class="mb-1" style="{{ !$isInBaseline ? $addedStyle : '' }}">
                                        <i class="fas fa-check-circle me-2" style="color: #049399;"></i>{{ $otherService }}
                                        @if (!$isInBaseline)
                                        {!! $addedBadge !!}
                                        @endif
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
                        
                        @if(!empty($counterData['additional_details']))
                        <div class="mb-4">
                            <h6 class="mb-2" style="color: #049399; font-weight: 600;">
                                <i class="fas fa-info-circle me-2"></i>Additional Details
                            </h6>
                            <p class="mb-0 ps-3">{{ $counterData['additional_details'] }}</p>
                        </div>
                        @endif
                    </div>
                    
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="{{ route('tenant.agent.auction.view', $auction->id) }}" class="btn" style="background-color: #fff; border: 2px solid #049399; color: #049399; padding: 10px 20px; font-weight: 600;">
                            <i class="fas fa-eye me-2"></i>View Listing
                        </a>
                        
                        @if($viewerRole === 'agent')
                        <a href="{{ route('tenant.hire.agent.auction.counter-bid', ['id' => $auction->id, 'bid_id' => $bid->id]) }}" class="btn" style="background-color: #ffc107; border: 2px solid #ffc107; color: #000; padding: 10px 20px; font-weight: 600;">
                            <i class="fas fa-reply me-2"></i>Counter Back
                        </a>
                        <form action="{{ route('tenant.hire.agent.auction.counter.bid.accept') }}" method="POST" class="d-inline">
                            @csrf
                            <input type="hidden" name="counter_bid_id" value="{{ $activeCounter->id }}">
                            <input type="hidden" name="auction_id" value="{{ $auction->id }}">
                            <button type="submit" class="btn" style="background-color: #28a745; border: 2px solid #28a745; color: #fff; padding: 10px 20px; font-weight: 600;" onclick="return confirm('Are you sure you want to accept these counter terms?')">
                                <i class="fas fa-check me-2"></i>Accept Counter
                            </button>
                        </form>
                        <form action="{{ route('tenant.hire.agent.auction.counter.bid.reject') }}" method="POST" class="d-inline">
                            @csrf
                            <input type="hidden" name="counter_bid_id" value="{{ $activeCounter->id }}">
                            <input type="hidden" name="auction_id" value="{{ $auction->id }}">
                            <button type="submit" class="btn" style="background-color: #dc3545; border: 2px solid #dc3545; color: #fff; padding: 10px 20px; font-weight: 600;" onclick="return confirm('Are you sure you want to reject these counter terms?')">
                                <i class="fas fa-times me-2"></i>Reject Counter
                            </button>
                        </form>
                        @else
                        <a href="{{ route('tenant.hire.agent.auction.counter-bid', ['id' => $auction->id, 'bid_id' => $bid->id]) }}" class="btn" style="background-color: #ffc107; border: 2px solid #ffc107; color: #000; padding: 10px 20px; font-weight: 600;">
                            <i class="fas fa-reply me-2"></i>Counter Back
                        </a>
                        <form action="{{ route('tenant.hire.agent.auction.bid.accept') }}" method="POST" class="d-inline">
                            @csrf
                            <input type="hidden" name="bid_id" value="{{ $bid->id }}">
                            <button type="submit" class="btn" style="background-color: #28a745; border: 2px solid #28a745; color: #fff; padding: 10px 20px; font-weight: 600;" onclick="return confirm('Are you sure you want to accept this counter offer from the agent?')">
                                <i class="fas fa-check me-2"></i>Accept
                            </button>
                        </form>
                        <form action="{{ route('tenant.hire.agent.auction.bid.reject') }}" method="POST" class="d-inline">
                            @csrf
                            <input type="hidden" name="bid_id" value="{{ $bid->id }}">
                            <button type="submit" class="btn" style="background-color: #dc3545; border: 2px solid #dc3545; color: #fff; padding: 10px 20px; font-weight: 600;" onclick="return confirm('Are you sure you want to reject this counter offer from the agent?')">
                                <i class="fas fa-times me-2"></i>Reject
                            </button>
                        </form>
                        @endif
                    </div>
                    @else
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        @if($viewerRole === 'agent')
                        No counter terms have been submitted by the tenant yet. Your bid is still active and awaiting a response.
                        @else
                        No counter terms have been submitted by the agent yet.
                        @endif
                    </div>
                    <a href="{{ route('tenant.agent.auction.view', $auction->id) }}" class="btn" style="background-color: #049399; color: white; padding: 10px 20px; font-weight: 600;">
                        <i class="fas fa-arrow-left me-2"></i>Back to Listing
                    </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
