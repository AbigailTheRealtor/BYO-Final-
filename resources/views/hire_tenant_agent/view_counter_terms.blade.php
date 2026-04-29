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
                        <i class="fa-solid fa-right-left me-2"></i>
                        Counter Terms
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
                                $bidStatus    = ucfirst(strtolower($bid->bid_status ?? 'active'));
                                $bidIsTerminal = in_array($bidStatus, ['Accepted', 'Rejected'], true);
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
                        // UNIFIED: both parties see the MOST RECENTLY SUBMITTED counter (by created_at).
                        // previousCounter = the immediately preceding counter used as the unified baseline.
                        $activeCounter          = null;
                        $previousCounter        = null;
                        $counterPartyName       = '';
                        $awaitingCounterResponse = false;

                        if ($tenantCounter && $agentCounter) {
                            if ($tenantCounter->created_at >= $agentCounter->created_at) {
                                $activeCounter    = $tenantCounter;
                                $previousCounter  = $agentCounter;
                                $counterPartyName = 'Tenant';
                            } else {
                                $activeCounter    = $agentCounter;
                                $previousCounter  = $tenantCounter;
                                $counterPartyName = 'Agent';
                            }
                        } elseif ($tenantCounter) {
                            $activeCounter    = $tenantCounter;
                            $counterPartyName = 'Tenant';
                        } elseif ($agentCounter) {
                            $activeCounter    = $agentCounter;
                            $counterPartyName = 'Agent';
                        }
                        // Awaiting response: the current user submitted the active counter.
                        $awaitingCounterResponse = ($activeCounter && $activeCounter->user_id === Auth::id());
                        // The party who must respond is the OTHER side.
                        $awaitingParty = ($viewerRole === 'agent') ? "the tenant's" : "the agent's";
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
                            $num = (float)$raw;
                            return rtrim(rtrim(number_format($num, 2), '0'), '.') . '%';
                        };
                        $joinParts = function(array $parts) {
                            return implode(' + ', array_filter($parts)) ?: null;
                        };
                        
                        // UNIFIED baseline: immediately previous counter (same for both parties).
                        // Fallback: listing owner's original terms (if this is the first counter in the chain).
                        if (isset($previousCounter) && $previousCounter) {
                            $baselineData  = $previousCounter->getAllMeta();
                            $baselineLabel = 'Previous Counter Terms';
                        } else {
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
                                // Referral fee — bid-level field; seed from original bid so first-counter change detection works
                                'referral_fee_percent' => $auction->isCreatedByAgent() ? ($bid->get->referral_fee_percent ?? null) : null,
                            ];
                            $baselineLabel = "Listing Owner's Original Terms";
                        }
                        
                        // === MATCH SCORE — baseline-driven (TenantBidMatchScoreHelper) ===
                        $counterPropType = $auction->get->property_type ?? 'Residential Property';
                        $score = \App\Helpers\TenantBidMatchScoreHelper::calculate($baselineData, $counterData, null, $counterPropType);

                        // Dual score removed — both parties now see identical score (unified baseline eliminates the need).
                        $showDualScore      = false;
                        $latestCounterScore = null;

                        $brokerScore          = $score['terms_match_percent'];
                        $brokerMatched        = $score['terms_matched_count'];
                        $brokerTotal          = $score['terms_baseline_total'];
                        $termsChangedCount    = $score['terms_changed_count'];
                        $termsAddedCount      = $score['terms_added_count'];

                        $servicesScore        = $score['services_match_percent'];
                        $servicesMatched      = $score['services_matched_count'];
                        $servicesTotal        = $score['services_baseline_total'];
                        $servicesMissingCount = $score['services_missing_count'];
                        $servicesExtraCount   = $score['services_extra_count'];

                        // diffScore = primary score: baseline is already the immediately previous counter (unified).
                        $diffBaselineData = $baselineData;
                        $diffScore        = $score;
                        $brokerMismatches = $diffScore['changed_terms'];
                        $servicesMissing  = $diffScore['missing_services'];
                        $servicesAdded    = $diffScore['extra_services'];

                        $totalScore      = $score['overall_percent'];
                        $getScoreColor   = fn($s) => \App\Helpers\TenantBidMatchScoreHelper::scoreColor((int)$s);
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

                        // Variables re-exported for per-service rendering in the Services section
                        $normalizeService = fn($s) => \App\Helpers\TenantBidMatchScoreHelper::normalizeService((string)$s);
                        // $baselineNorm uses diffScore so "Added" badge reflects diff vs previous counter
                        $baselineNorm     = array_merge($diffScore['matched_services'], $diffScore['missing_services']);
                        // Catalog for display-level filtering — prevents Buyer/Seller services from appearing
                        // in "Services Not Included in Counter" and similar comparison lists
                        $displayCatalog2 = \App\Helpers\TenantBidMatchScoreHelper::getCatalog($counterPropType);

                        // Raw counter services for display (catalog-filtered to Tenant-only)
                        $csRaw = $counterData['services'] ?? [];
                        if (is_string($csRaw)) $csRaw = json_decode($csRaw, true) ?? [];
                        $csRaw = is_array($csRaw) ? array_values(array_filter($csRaw)) : [];
                        $csRaw = array_values(array_filter(
                            $csRaw,
                            fn($s) => in_array($normalizeService((string)$s), $displayCatalog2, true)
                        ));
                        $counterOtherRaw = $counterData['other_services'] ?? [];
                        if (is_string($counterOtherRaw)) $counterOtherRaw = json_decode($counterOtherRaw, true) ?? [];
                        $counterOtherRaw = is_array($counterOtherRaw) ? array_values(array_filter($counterOtherRaw, fn($s) => is_string($s) && !empty(trim($s)))) : [];
                        $allCounterServices  = array_merge($csRaw, $counterOtherRaw);
                        $counterOtherServices = $counterOtherRaw;

                        // Raw baseline services for "Not in Counter" display — use diffBaselineData
                        // so "Not Included" reflects services removed since the PREVIOUS counter, not the original
                        $bsRaw2 = $diffBaselineData['services'] ?? [];
                        if (is_string($bsRaw2)) $bsRaw2 = json_decode($bsRaw2, true) ?? [];
                        $bsRaw2 = is_array($bsRaw2) ? array_values(array_filter($bsRaw2)) : [];
                        $bsRaw2 = array_values(array_filter(
                            $bsRaw2,
                            fn($s) => in_array($normalizeService((string)$s), $displayCatalog2, true)
                        ));
                        $bsOtherRaw2 = $diffBaselineData['other_services'] ?? [];
                        if (is_string($bsOtherRaw2)) $bsOtherRaw2 = json_decode($bsOtherRaw2, true) ?? [];
                        $bsOtherRaw2 = is_array($bsOtherRaw2) ? array_values(array_filter($bsOtherRaw2, fn($s) => is_string($s) && !empty(trim($s)))) : [];
                        $allBaselineServices = array_merge($bsRaw2, $bsOtherRaw2);
                        
                        $mismatchStyle = 'background-color: #ffe6e6; padding: 2px 6px; border-radius: 4px; border-left: 3px solid #dc3545;';
                        $mismatchBadge = '<span class="badge" style="background-color: #dc3545; color: white; font-size: 0.7rem; vertical-align: middle; margin-left: 8px;">Changed</span>';
                        $addedStyle = 'background-color: #e6ffe6; padding: 2px 6px; border-radius: 4px; border-left: 3px solid #28a745;';
                        $addedBadge = '<span class="badge" style="background-color: #28a745; color: white; font-size: 0.7rem; vertical-align: middle; margin-left: 8px;">Added</span>';
                        $missingStyle = 'background-color: #fff3cd; padding: 2px 6px; border-radius: 4px; border-left: 3px solid #ffc107;';
                        $missingBadge = '<span class="badge" style="background-color: #ffc107; color: #000; font-size: 0.7rem; vertical-align: middle; margin-left: 8px;">Removed</span>';
                        
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
                                <i class="fa-solid fa-file-contract me-2"></i>
                                @if($awaitingCounterResponse)
                                    Your Submitted Counter Offer
                                @elseif($counterPartyName === 'Agent')
                                    Agent's Counter Terms
                                @else
                                    Listing Owner's Counter Terms
                                @endif
                            </h5>
                            <span class="text-muted small">Last updated: {{ $activeCounter->updated_at->format('M d, Y h:i A') }}</span>
                        </div>

                        @if($awaitingCounterResponse)
                        <div class="alert alert-warning mb-3 py-2" style="border-radius: 8px; border-left: 4px solid #ffc107; background: #fff9e6;">
                            <i class="fa-solid fa-clock me-2"></i><strong>Counter Offer Sent.</strong>
                        </div>
                        @endif

                        @if ($hasAnyBaseline)
                        <div class="match-score-panel mb-4 p-3" style="background: white; border-radius: 10px; border: 1px solid #dee2e6;">

                            @if ($showDualScore && $latestCounterScore)
                            {{-- DUAL SCORE: Original Match + Latest Counter Match --}}
                            <div class="mb-3">
                                <span style="font-weight: 600; color: #1a3a5c; font-size: 1.1rem;">
                                    <i class="fa-solid fa-chart-pie me-2"></i>Match Summary
                                </span>
                            </div>
                            <p class="small text-muted mb-3">
                                <i class="fa-solid fa-circle-info me-1"></i>
                                <strong>Original Match</strong> compares this response to the Tenant's original listing request.<br>
                                <strong>Latest Counter Match</strong> compares this response to the Tenant's most recent counteroffer.<br>
                                Added services or terms are shown for transparency but do not increase either score.
                            </p>
                            <div class="row g-3 mb-3">
                                {{-- Original Match --}}
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
                                {{-- Latest Counter Match --}}
                                @php
                                    $lcSvcScore  = $latestCounterScore['services_match_percent'];
                                    $lcTrmScore  = $latestCounterScore['terms_match_percent'];
                                    $lcOverall   = $latestCounterScore['overall_percent'];
                                    $lcSvcMatch  = $latestCounterScore['services_matched_count'];
                                    $lcSvcTotal  = $latestCounterScore['services_baseline_total'];
                                    $lcTrmMatch  = $latestCounterScore['terms_matched_count'];
                                    $lcTrmTotal  = $latestCounterScore['terms_baseline_total'];
                                    $lcSvcExtra  = $latestCounterScore['services_extra_count'];
                                    $lcSvcMiss   = $latestCounterScore['services_missing_count'];
                                    $lcTrmChg    = $latestCounterScore['terms_changed_count'];
                                    $lcTrmAdded  = $latestCounterScore['terms_added_count'];
                                    $lcColor     = $getScoreColor($lcOverall);
                                @endphp
                                <div class="col-md-6">
                                    <div class="p-3 rounded" style="background: #f0f9ff; border: 1px solid #bde0fe; border-top: 3px solid {{ $lcColor }};">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="small fw-semibold" style="color: #1a3a5c;">Latest Counter Match</span>
                                            <span class="badge" style="background: {{ $lcColor }}; font-size: 0.95rem; padding: 4px 10px; color: white;">{{ $lcOverall }}%</span>
                                        </div>
                                        <div class="small text-muted">vs. Tenant's Most Recent Counter</div>
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
                            {{-- SINGLE SCORE (no counter, or agent viewing) --}}
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span style="font-weight: 600; color: #1a3a5c; font-size: 1.1rem;">
                                    <i class="fa-solid fa-chart-pie me-2"></i>Match Score
                                </span>
                                <span class="badge" style="background: {{ $totalScoreColor }}; font-size: 1.1rem; padding: 8px 16px; color: white;">
                                    {{ $totalScore }}%
                                </span>
                            </div>
                            <p class="small text-muted mb-3">
                                <i class="fa-solid fa-circle-info me-1"></i>Match Score compares this counter only to the baseline. Added services or added terms are shown for transparency but do not increase the score.<br>
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
                            <i class="fa-solid fa-circle-info me-2"></i>No match score available — no requirements were provided in the baseline.
                        </div>
                        @endif
                        
                        <div class="mb-4">
                            <h6 class="mb-3" style="color: #049399; font-weight: 600; border-bottom: 2px solid #049399; padding-bottom: 8px;">
                                <i class="fa-solid fa-handshake me-2"></i>Broker Compensation & Agency Agreement Terms
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
                                        } elseif ($purchaseFeeType === 'Flat Fee + Percentage of the Total Purchase Price') {
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
                                        <span class="fw-semibold">Purchase Fee:</span> {{ $purchaseFeeDisplay }}
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
                                            <span class="fw-semibold">Compensation for Creating the Lease-Option Agreement:</span> 
                                            {{ ($counterData['lease_type'] ?? '') === 'percent' ? $fmtPercent($counterData['lease_value']) : $fmtMoney($counterData['lease_value']) }}
                                            {!! isset($brokerMismatches['lease_value']) ? $mismatchBadge : '' !!}
                                        </li>
                                        @endif
                                        @if (!empty($counterData['purchase_value']))
                                        <li class="mb-2" style="{{ isset($brokerMismatches['purchase_value']) ? $mismatchStyle : '' }}">
                                            <span class="fw-semibold">Compensation if Purchase Option is Exercised:</span> 
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
                                        <span class="fw-semibold">Early Termination Fee:</span> {{ ucfirst($counterData['early_termination_fee_option']) }}
                                        {!! isset($brokerMismatches['early_termination_fee_option']) ? $mismatchBadge : '' !!}
                                    </li>
                                    @if (strtolower($counterData['early_termination_fee_option'] ?? '') === 'yes' && !empty($counterData['early_termination_fee_amount']))
                                    <li class="mb-2" style="{{ isset($brokerMismatches['early_termination_fee_amount']) ? $mismatchStyle : '' }}">
                                        <span class="fw-semibold">Termination Fee Amount:</span> {{ $fmtMoney($counterData['early_termination_fee_amount']) }}
                                        {!! isset($brokerMismatches['early_termination_fee_amount']) ? $mismatchBadge : '' !!}
                                    </li>
                                    @endif
                                    @endif
                                    @if (!empty($counterData['retainer_fee_option']))
                                    <li class="mb-2" style="{{ isset($brokerMismatches['retainer_fee_option']) ? $mismatchStyle : '' }}">
                                        <span class="fw-semibold">Retainer Fee:</span> {{ ucfirst($counterData['retainer_fee_option']) }}
                                        {!! isset($brokerMismatches['retainer_fee_option']) ? $mismatchBadge : '' !!}
                                    </li>
                                    @if (strtolower($counterData['retainer_fee_option'] ?? '') === 'yes')
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

                            @if ($auction->isCreatedByAgent() && !empty($counterData['referral_fee_percent']))
                            <div class="mb-4">
                                <h6 class="mb-2" style="color: #049399; font-weight: 600;">F) Referral Fee</h6>
                                <ul class="list-unstyled ps-3 mb-0">
                                    <li class="mb-2" style="{{ isset($brokerMismatches['referral_fee_percent']) ? $mismatchStyle : '' }}">
                                        <span class="fw-semibold">Referral Fee (%):</span> {{ $counterData['referral_fee_percent'] }}%
                                        {!! isset($brokerMismatches['referral_fee_percent']) ? $mismatchBadge : '' !!}
                                    </li>
                                </ul>
                            </div>
                            @endif
                        </div>
                        
                        @if(!empty($allCounterServices) || !empty($allBaselineServices))
                        <div class="mb-4">
                            <h6 class="mb-3" style="color: #049399; font-weight: 600; border-bottom: 2px solid #049399; padding-bottom: 8px;">
                                <i class="fa-solid fa-list-check me-2"></i>Requested Services
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
                                            @if (!$serviceData['inBaseline'])
                                                <i class="fa-solid fa-plus-circle me-2" style="color: #28a745;"></i>
                                            @else
                                                <span class="me-2" style="color: #6c757d;">•</span>
                                            @endif
                                            {{ $serviceData['service'] }}
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
                                        @if (!$isInBaseline)
                                            <i class="fa-solid fa-plus-circle me-2" style="color: #28a745;"></i>
                                        @else
                                            <span class="me-2" style="color: #6c757d;">•</span>
                                        @endif
                                        {{ $otherService }}
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
                                <strong style="color: #856404;"><i class="fa-solid fa-triangle-exclamation me-2"></i>Services Not Included in Counter:</strong>
                                <ul class="list-unstyled ps-3 mt-2 mb-0">
                                    @foreach ($allBaselineServices as $baselineService)
                                        @if (in_array($normalizeService($baselineService), $servicesMissing))
                                        <li class="mb-1" style="{{ $missingStyle }}">
                                            <i class="fa-solid fa-circle-xmark me-2" style="color: #ffc107;"></i>{{ $baselineService }}
                                        </li>
                                        @endif
                                    @endforeach
                                </ul>
                            </div>
                            @endif
                        </div>
                        @endif
                        
                        @if(!empty($counterData['additional_details']))
                        <div class="mb-4">
                            <h6 class="mb-2" style="color: #049399; font-weight: 600;">
                                <i class="fa-solid fa-circle-info me-2"></i>Additional Details
                            </h6>
                            <p class="mb-0 ps-3">{{ $counterData['additional_details'] }}</p>
                        </div>
                        @endif
                    </div>
                    
                    <div class="d-flex gap-2 flex-wrap mt-4">

                        {{-- AGENT ACTIONS --}}
                        @if($viewerRole === 'agent' && !$bidIsTerminal)
                            @if(!$awaitingCounterResponse)
                            {{-- The listing owner submitted the latest counter — agent can respond --}}
                            <a href="{{ route('tenant.hire.agent.auction.counter-bid', ['id' => $auction->id, 'bid_id' => $bid->id]) }}" class="btn" style="background-color: #ffc107; border: 2px solid #ffc107; color: #000; padding: 10px 20px; font-weight: 600;">
                                <i class="fa-solid fa-reply me-2"></i>Counter Back
                            </a>
                            <form action="{{ route('tenant.hire.agent.auction.counter.bid.accept') }}" method="POST" class="d-inline">
                                @csrf
                                <input type="hidden" name="counter_bid_id" value="{{ $activeCounter->id }}">
                                <input type="hidden" name="auction_id" value="{{ $auction->id }}">
                                <button type="submit" class="btn" style="background-color: #28a745; border: 2px solid #28a745; color: #fff; padding: 10px 20px; font-weight: 600;" onclick="return confirm('Are you sure you want to accept these counter terms?')">
                                    <i class="fa-solid fa-check me-2"></i>Accept
                                </button>
                            </form>
                            <form action="{{ route('tenant.hire.agent.auction.counter.bid.reject') }}" method="POST" class="d-inline">
                                @csrf
                                <input type="hidden" name="counter_bid_id" value="{{ $activeCounter->id }}">
                                <input type="hidden" name="auction_id" value="{{ $auction->id }}">
                                <button type="submit" class="btn" style="background-color: #dc3545; border: 2px solid #dc3545; color: #fff; padding: 10px 20px; font-weight: 600;" onclick="return confirm('Are you sure you want to reject these counter terms?')">
                                    <i class="fa-solid fa-xmark me-2"></i>Reject
                                </button>
                            </form>
                            @else
                            {{-- Agent submitted the latest counter — waiting for the listing owner to respond --}}
                            <div class="alert alert-info mb-0">
                                <i class="fa-solid fa-clock me-2"></i><strong>Counter Offer Sent.</strong>
                            </div>
                            <a href="{{ route('tenant.hire.agent.auction.counter-bid', ['id' => $auction->id, 'bid_id' => $bid->id]) }}" class="btn" style="background-color: #049399; border: 2px solid #049399; color: #fff; padding: 10px 20px; font-weight: 600;">
                                <i class="fa fa-pen-to-square me-2"></i>Edit Counter Terms
                            </a>
                            @endif
                        @elseif($viewerRole === 'agent' && $bidIsTerminal)
                        <div class="alert alert-secondary mb-0">
                            This bid has been {{ $bidStatus }}.
                        </div>
                        @endif

                        {{-- TENANT ACTIONS --}}
                        @if($viewerRole === 'tenant' && !$bidIsTerminal)
                            @if(!$awaitingCounterResponse)
                            <a href="{{ route('tenant.counter-terms', $bid->id) }}" class="btn" style="background-color: #ffc107; border: 2px solid #ffc107; color: #000; padding: 10px 20px; font-weight: 600;">
                                <i class="fa-solid fa-reply me-2"></i>Counter Back
                            </a>
                            <form action="{{ route('tenant.hire.agent.auction.bid.accept') }}" method="POST" class="d-inline">
                                @csrf
                                <input type="hidden" name="bid_id" value="{{ $bid->id }}">
                                <button type="submit" class="btn" style="background-color: #28a745; border: 2px solid #28a745; color: #fff; padding: 10px 20px; font-weight: 600;" onclick="return confirm('Are you sure you want to accept this counter offer from the agent?')">
                                    <i class="fa-solid fa-check me-2"></i>Accept
                                </button>
                            </form>
                            <form action="{{ route('tenant.hire.agent.auction.bid.reject') }}" method="POST" class="d-inline">
                                @csrf
                                <input type="hidden" name="bid_id" value="{{ $bid->id }}">
                                <button type="submit" class="btn" style="background-color: #dc3545; border: 2px solid #dc3545; color: #fff; padding: 10px 20px; font-weight: 600;" onclick="return confirm('Are you sure you want to reject this counter offer from the agent?')">
                                    <i class="fa-solid fa-xmark me-2"></i>Reject
                                </button>
                            </form>
                            @else
                            <div class="alert alert-info mb-0">
                                <i class="fa-solid fa-clock me-2"></i><strong>Counter Offer Sent.</strong>
                            </div>
                            <a href="{{ route('tenant.edit-counter-terms', ['id' => $bid->id]) }}" class="btn" style="background-color: #049399; border: 2px solid #049399; color: #fff; padding: 10px 20px; font-weight: 600;">
                                <i class="fa fa-pen-to-square me-2"></i>Edit Counter Terms
                            </a>
                            @endif
                        @elseif($viewerRole === 'tenant' && $bidIsTerminal)
                        <div class="alert alert-secondary mb-0">
                            This bid has been {{ $bidStatus }}.
                        </div>
                        @endif

                        <a href="{{ route('tenant.agent.auction.view', $auction->id) }}"
                           class="btn" style="background-color: #fff; border: 2px solid #049399; color: #049399; padding: 10px 20px; font-weight: 600;">
                            <i class="fa-solid fa-eye me-2"></i>View Listing
                        </a>
                    </div>
                    @else
                    <div class="alert alert-info">
                        <i class="fa-solid fa-circle-info me-2"></i>
                        No counter terms have been submitted for this bid yet.
                    </div>
                    <a href="{{ route('tenant.agent.auction.view', $auction->id) }}" class="btn btn-outline-secondary mt-2">
                        <i class="fa-solid fa-arrow-left me-2"></i>Back to Listing
                    </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
