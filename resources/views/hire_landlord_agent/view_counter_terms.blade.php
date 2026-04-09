@extends('layouts.main')

@section('content')
<div class="container py-5">
    <div class="row">
        <div class="col-12">
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                    @if($viewerRole === 'agent')
                    <li class="breadcrumb-item"><a href="{{ route('myBids', 'landlord-agent') }}">My Bids</a></li>
                    @else
                    <li class="breadcrumb-item"><a href="{{ route('landlord.agent.auction.view', $auction->id) }}">Listing</a></li>
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
                            <p><strong>Title:</strong> {{ $auction->title ?? 'Hire a Landlord Agent' }}</p>
                            <p><strong>Address:</strong> {{ $auction->get->address ?? 'N/A' }}</p>
                            <p><strong>Listing ID:</strong> {{ $auction->listing_id ?? 'LAA-'.$auction->id }}</p>
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

                    @php
                        $activeCounter = null;
                        $counterPartyName = '';
                        $awaitingCounterResponse = false;

                        if ($viewerRole === 'agent') {
                            $activeCounter = $landlordCounter;
                            $counterPartyName = 'Landlord';
                        } else {
                            if ($agentCounter) {
                                $activeCounter = $agentCounter;
                                $counterPartyName = 'Agent';
                            } elseif ($landlordCounter) {
                                // Landlord submitted a counter but agent hasn't responded yet — show landlord's own counter
                                $activeCounter = $landlordCounter;
                                $counterPartyName = 'Your Submitted';
                                $awaitingCounterResponse = true;
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
                            $num = (float)$raw;
                            return rtrim(rtrim(number_format($num, 2), '0'), '.') . '%';
                        };
                        $joinParts = function(array $parts) {
                            return implode(' + ', array_filter($parts)) ?: null;
                        };

                        // Baseline data for match score comparison
                        if ($viewerRole === 'agent') {
                            // Agent: compare landlord's counter to agent's original bid
                            $baselineData = (array) $bid->get;
                            $baselineLabel = 'Your Original Bid';
                        } else {
                            // Landlord: compare agent's counter to landlord's original listing terms
                            $baselineData = [
                                'purchase_fee_type'                   => $auction->get->purchase_fee_type ?? null,
                                'purchase_fee_flat'                   => $auction->get->purchase_fee_flat ?? null,
                                'purchase_fee_flat_type'              => $auction->get->purchase_fee_flat_type ?? null,
                                'purchase_fee_rental_period'          => $auction->get->purchase_fee_rental_period ?? null,
                                'purchase_fee_percentage_combo'       => $auction->get->purchase_fee_percentage_combo ?? null,
                                'purchase_fee_flat_combo'             => $auction->get->purchase_fee_flat_combo ?? null,
                                'broker_fee_timing'                   => $auction->get->broker_fee_timing ?? null,
                                'broker_fee_days_from_rent'           => $auction->get->broker_fee_days_from_rent ?? null,
                                'broker_fee_days_after_lease'         => $auction->get->broker_fee_days_after_lease ?? null,
                                'renewal_fee_type'                    => $auction->get->renewal_fee_type ?? null,
                                'tenant_broker_commission_structure'  => $auction->get->tenant_broker_commission_structure ?? null,
                                'interested_lease_option_agreement'   => $auction->get->interested_lease_option_agreement ?? null,
                                'lease_type'                          => $auction->get->lease_type ?? null,
                                'lease_value'                         => $auction->get->lease_value ?? null,
                                'purchase_type'                       => $auction->get->purchase_type ?? null,
                                'purchase_value'                      => $auction->get->purchase_value ?? null,
                                'interested_in_selling'               => $auction->get->interested_in_selling ?? null,
                                'interested_in_selling_type'          => $auction->get->interested_in_selling_type ?? null,
                                'protection_period'                   => $auction->get->protection_period ?? null,
                                'early_termination_fee_option'        => $auction->get->early_termination_fee_option ?? null,
                                'early_termination_fee_amount'        => $auction->get->early_termination_fee_amount ?? null,
                                'agency_agreement_timeframe'          => $auction->get->agency_agreement_timeframe ?? null,
                                'agency_agreement_custom'             => $auction->get->agency_agreement_custom ?? null,
                                'brokerage_relationship'              => $auction->get->brokerage_relationship ?? null,
                                'interested_in_property_management'   => $auction->get->interested_in_property_management ?? null,
                                'services'                            => $auction->get->services ?? [],
                                'other_services'                      => $auction->get->other_services ?? [],
                            ];
                            $baselineLabel = 'Your Original Listing Terms';
                        }

                        // Match Score
                        $counterPropType = $auction->get->property_type ?? 'Residential Property';
                        $score = \App\Helpers\LandlordBidMatchScoreHelper::calculate($baselineData, $counterData, null, $counterPropType);

                        // Dual score: if landlord viewing agent's counter and landlord also submitted a counter
                        $showDualScore      = false;
                        $latestCounterScore = null;
                        if ($viewerRole === 'landlord' && $landlordCounter && !$awaitingCounterResponse) {
                            $latestCounterScore = \App\Helpers\LandlordBidMatchScoreHelper::calculate(
                                $landlordCounter->getAllMeta(), $counterData, null, $counterPropType
                            );
                            $showDualScore = true;
                        }

                        $brokerScore       = $score['terms_match_percent'];
                        $brokerMatched     = $score['terms_matched_count'];
                        $brokerTotal       = $score['terms_baseline_total'];
                        $brokerMismatches  = $score['changed_terms'];
                        $termsChangedCount = $score['terms_changed_count'];
                        $termsAddedCount   = $score['terms_added_count'];

                        $servicesScore        = $score['services_match_percent'];
                        $servicesMatched      = $score['services_matched_count'];
                        $servicesTotal        = $score['services_baseline_total'];
                        $servicesMissingCount = $score['services_missing_count'];
                        $servicesExtraCount   = $score['services_extra_count'];
                        $servicesMissing      = $score['missing_services'];
                        $servicesAdded        = $score['extra_services'];

                        $totalScore      = $score['overall_percent'];
                        $getScoreColor   = fn($s) => \App\Helpers\LandlordBidMatchScoreHelper::scoreColor((int)$s);
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

                        $normalizeService = fn($s) => \App\Helpers\LandlordBidMatchScoreHelper::normalizeService((string)$s);
                        $baselineNorm     = array_merge($score['matched_services'], $score['missing_services']);
                        $displayCatalog2  = \App\Helpers\LandlordBidMatchScoreHelper::getCatalog($counterPropType);

                        // Counter services
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

                        // Baseline services
                        $bsRaw2 = $baselineData['services'] ?? [];
                        if (is_string($bsRaw2)) $bsRaw2 = json_decode($bsRaw2, true) ?? [];
                        $bsRaw2 = is_array($bsRaw2) ? array_values(array_filter($bsRaw2)) : [];
                        $bsRaw2 = array_values(array_filter(
                            $bsRaw2,
                            fn($s) => in_array($normalizeService((string)$s), $displayCatalog2, true)
                        ));
                        $bsOtherRaw2 = $baselineData['other_services'] ?? [];
                        if (is_string($bsOtherRaw2)) $bsOtherRaw2 = json_decode($bsOtherRaw2, true) ?? [];
                        $bsOtherRaw2 = is_array($bsOtherRaw2) ? array_values(array_filter($bsOtherRaw2, fn($s) => is_string($s) && !empty(trim($s)))) : [];
                        $allBaselineServices = array_merge($bsRaw2, $bsOtherRaw2);

                        $mismatchStyle = 'background-color: #ffe6e6; padding: 2px 6px; border-radius: 4px; border-left: 3px solid #dc3545;';
                        $mismatchBadge = '<span class="badge" style="background-color: #dc3545; color: white; font-size: 0.7rem; vertical-align: middle; margin-left: 8px;">Changed</span>';
                        $addedStyle    = 'background-color: #e6ffe6; padding: 2px 6px; border-radius: 4px; border-left: 3px solid #28a745;';
                        $addedBadge    = '<span class="badge" style="background-color: #28a745; color: white; font-size: 0.7rem; vertical-align: middle; margin-left: 8px;">Added</span>';
                        $missingStyle  = 'background-color: #fff3cd; padding: 2px 6px; border-radius: 4px; border-left: 3px solid #ffc107;';
                        $missingBadge  = '<span class="badge" style="background-color: #ffc107; color: #000; font-size: 0.7rem; vertical-align: middle; margin-left: 8px;">Removed</span>';

                        // Service categories
                        $isCommercial = str_contains(strtolower($counterPropType), 'commercial');

                        $residentialCategories = [
                            "📢 Rental Marketing & Listing Promotion" => [
                                "List the property on the local Multiple Listing Service (MLS)",
                                "Syndicate the listing to third-party platforms (e.g., Zillow.com, Realtor.com, Trulia.com, Homes.com)",
                                "Create a branded flyer featuring the property's key highlights",
                                "Post the property on Facebook Marketplace",
                                "Post the property on Craigslist in the appropriate \"Homes for Rent\" category",
                                "Share the listing on Nextdoor in Neighborhood or Community Groups",
                                "Promote the listing on Facebook in Housing or Rental Groups",
                                "Share the listing on Instagram using posts, stories, or reels",
                                "Promote the listing on LinkedIn in Professional or Real Estate Groups",
                                "Upload a TikTok video walkthrough of the property",
                                "Upload a YouTube video walkthrough of the property",
                                "Launch a mass email campaign promoting the listing",
                                "Distribute printed flyers or postcards in target geographic areas",
                                "Launch hyperlocal or interest-based digital ad campaigns promoting the listing",
                            ],
                            "📋 Listing Presentation & Preparation" => [
                                "Conduct a property walkthrough and provide recommendations for listing readiness",
                                "Provide a custom listing preparation checklist",
                                "Collect property details and prepare MLS remarks and a public listing description",
                                "Provide a visual consultation for interior layout, cleanliness, and presentation",
                                "Provide a curb appeal consultation focused on exterior presentation",
                                "Provide referrals to third-party vendors (e.g., cleaners, handypeople, electricians, landscapers). Vendor fees billed separately. Referrals only — no endorsement or warranty is made",
                            ],
                            "📸 Photography, Video & Virtual Media" => [
                                "Provide professional property photography",
                                "Provide aerial (drone) photography (subject to FAA Part 107 compliance)",
                                "Provide a video walkthrough tour",
                                "Provide a 3D virtual tour",
                                "Provide virtual staging (digital enhancements only; no physical staging)",
                                "Provide digital photo enhancements",
                                "Create a basic schematic floor plan (non-certified; for marketing purposes only)",
                            ],
                            "🏡 Showings & Access Coordination" => [
                                "Ensure proper notice is given if the property is occupied",
                                "Install a real estate sign on the property",
                                "Install a lockbox for Agent access",
                                "Schedule and attend showings with prospective Tenants",
                                "Coordinate showings with Tenant's Agents",
                                "Collect and relay feedback to the Landlord after showings",
                            ],
                            "📝 Tenant Application Support" => [
                                "Provide a link to an online application platform with third-party screening tools (e.g., credit, background, and eviction checks)",
                                "Ensure compliance with Fair Housing laws and screening regulations throughout the application process",
                                "Collect and organize application documents submitted by prospective Tenants",
                                "Verify basic information provided in the application (e.g., employment, income, and references)",
                                "Present complete and organized application packages to the Landlord for review and final selection",
                            ],
                            "📃 Lease Preparation & Execution" => [
                                "Review lease offers submitted by prospective Tenants and summarize key terms",
                                "Coordinate lease negotiation with the Tenant or Tenant's Agent",
                                "Prepare a state-specific lease agreement using approved forms or templates",
                                "Assist with completing required lease disclosures and reviewing key lease terms",
                                "Assist with in-person or electronic lease signing, including e-signature setup and secure delivery of executed lease documents, addenda, and disclosures to all parties",
                                "Confirm receipt of required move-in funds and assist the Landlord in verifying amounts due, payment deadlines, and accepted payment methods",
                            ],
                            "🚚 Move-In Support & Coordination" => [
                                "Coordinate move-in date and key handoff logistics with the Tenant or Tenant's Agent",
                                "Confirm completion of any agreed-upon pre-move-in cleaning or repairs",
                                "Verify receipt of all required move-in funds prior to occupancy (e.g., deposit, rent, pet fees)",
                                "Provide a utility setup checklist and local provider resources for the Tenant",
                                "Share a move-in checklist for documentation and property condition review",
                            ],
                            "📑 Property Management" => [
                                "Provide ongoing property management services throughout the lease term (rent collection, maintenance coordination, Tenant communications, lease enforcement, renewals, etc.)",
                            ],
                            "💡 Leasing Strategy & Guidance" => [
                                "Provide a Rental Market Analysis (RMA) with pricing insights based on comparable rentals, neighborhood trends, and current market conditions",
                                "Advise on lease types and structures (e.g., month-to-month, annual, furnished, corporate, lease-option)",
                                "Provide general guidance on Landlord obligations and Tenant rights under state law",
                                "Provide general guidance on rental demand, local market conditions, and Tenant expectations",
                            ],
                        ];

                        $commercialCategories = [
                            "📢 Rental Marketing & Listing Promotion" => [
                                "List the property on the local Multiple Listing Service (MLS)",
                                "List the property on Crexi.com",
                                "List the property on LoopNet.com",
                                "Create a branded flyer featuring the property's key highlights",
                                "Post the property on Craigslist under the \"Office/Commercial\" category",
                                "Promote the listing on Facebook in Commercial Leasing or Business Startup Groups",
                                "Share the listing on Instagram using photos, stories, or reels",
                                "Promote the listing on LinkedIn in Professional, Real Estate, or Commercial Investment Groups",
                                "Upload a TikTok video walkthrough of the property",
                                "Upload a YouTube video walkthrough of the property",
                                "Launch a mass email campaign promoting the listing",
                                "Distribute printed flyers or postcards in target geographic areas",
                                "Launch hyperlocal or interest-based digital ad campaigns promoting the listing",
                            ],
                            "📋 Listing Presentation & Preparation" => [
                                "Conduct a property walkthrough and provide recommendations for listing readiness",
                                "Provide a custom listing preparation checklist",
                                "Collect property details such as lease terms, square footage, property features, and allowable uses",
                                "Prepare a marketing packet including zoning, cap rate references, and permitted uses",
                                "Provide a visual consultation focused on interior layout, cleanliness, and presentation",
                                "Provide a curb appeal consultation for exterior appearance and signage opportunities",
                                "Provide referrals to third-party vendors (e.g., cleaners, sign installers, minor repair vendors). Vendor fees billed separately. Referrals only — no endorsement or warranty is made",
                            ],
                            "📸 Photography, Video & Virtual Media" => [
                                "Provide professional property photography",
                                "Provide aerial (drone) photography (subject to FAA Part 107 compliance)",
                                "Provide a video walkthrough tour",
                                "Provide a 3D virtual tour",
                                "Provide virtual staging (digital enhancements only; no physical staging)",
                                "Provide digital photo enhancements",
                                "Create a basic schematic floor plan (non-certified; for marketing purposes only)",
                            ],
                            "🏢 Showings & Access Coordination" => [
                                "Ensure proper notice is given if the property is occupied",
                                "Install a real estate sign on the property",
                                "Install a lockbox for agent access",
                                "Schedule and attend showings with prospective tenants",
                                "Coordinate showings with Tenant's Agents",
                                "Collect and relay showing feedback to the Landlord",
                            ],
                            "📝 Tenant Application Support" => [
                                "Provide a link to an online application platform or share instructions with prospective tenants or Tenant's Agents",
                                "Ensure compliance with applicable federal, state, and local commercial leasing and anti-discrimination laws",
                                "Collect and organize application documents (e.g., business licenses, financials, entity records, references)",
                                "Verify basic information provided in the application (e.g., business operations, income sources, references)",
                                "Present complete application packages to the Landlord for review and final selection",
                            ],
                            "📃 Lease Preparation, LOI & Execution" => [
                                "Coordinate lease negotiation with the Tenant or Tenant's Agent",
                                "Collect and organize letters of intent (LOIs) or draft lease proposals",
                                "Draft or assist with execution of the final lease agreement using approved forms or templates",
                                "Provide and review required lease disclosures and addenda based on state or municipal requirements",
                                "Assist with in-person or electronic lease signing, including e-signature setup and secure delivery of executed lease documents, addenda, and disclosures to all parties",
                                "Verify receipt of required deposits and track rent commencement and key lease dates to ensure move-in readiness",
                            ],
                            "🚚 Move-In Support & Coordination" => [
                                "Coordinate move-in date and key handoff logistics with the Tenant or Tenant's Agent",
                                "Confirm completion of any agreed-upon pre-move-in repairs, cleaning, or improvements",
                                "Verify receipt of all required move-in funds and documents prior to occupancy (e.g., rent, security deposit, insurance certificates)",
                                "Provide a utility setup checklist and local provider resources for the Tenant",
                                "Share a move-in checklist for documentation and property condition review",
                                "Assist with coordination of move-in logistics, including certificate of insurance (COI) and vendor access (as agreed)",
                            ],
                            "📑 Property Management" => [
                                "Provide ongoing property management services throughout the lease term (rent collection, maintenance coordination, Tenant communications, lease enforcement, renewals, etc.)",
                            ],
                            "💡 Leasing Strategy & Guidance" => [
                                "Provide a comparable lease analysis with pricing recommendations based on similar properties, local vacancy trends, and current market conditions",
                                "Advise on lease types and structures (e.g., NNN, Modified Gross, Full Service) with general explanations of differences",
                                "Provide general guidance on Landlord obligations and Tenant rights under applicable commercial leasing laws",
                                "Provide general guidance on zoning, permitted uses, occupancy standards, or rent escalation terms",
                            ],
                        ];

                        $categories = $isCommercial ? $commercialCategories : $residentialCategories;
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
                                <strong>Original Match</strong> compares this response to the Landlord's original listing request.<br>
                                <strong>Latest Counter Match</strong> compares this response to the Landlord's most recent counteroffer.<br>
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
                                        <div class="small text-muted">vs. Landlord's Most Recent Counter</div>
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

                        {{-- ===== A) Broker Lease Fee (Landlord's commission from landlord) ===== --}}
                        @if (!empty($counterData['purchase_fee_type']))
                        <div class="mb-4">
                            <h6 class="mb-3" style="color: #049399; font-weight: 600; border-bottom: 2px solid #049399; padding-bottom: 8px;">
                                <i class="fas fa-handshake me-2"></i>Broker Compensation & Agency Agreement Terms
                            </h6>
                            <div class="mb-4">
                                <h6 class="mb-2" style="color: #049399; font-weight: 600;">A) Landlord's Broker Lease Fee</h6>
                                <ul class="list-unstyled ps-3 mb-0">
                                @php
                                    $pft = $counterData['purchase_fee_type'] ?? '';
                                    $pfDisplay = '—';
                                    if ($pft === 'flat') {
                                        $flatVal  = $counterData['purchase_fee_flat'] ?? null;
                                        $flatType = $counterData['purchase_fee_flat_type'] ?? '$';
                                        if ($flatType === '%') {
                                            $pfDisplay = $fmtPercent($flatVal) ?? '—';
                                        } else {
                                            $pfDisplay = $fmtMoney($flatVal) ?? '—';
                                        }
                                        $period = $counterData['purchase_fee_rental_period'] ?? null;
                                        if ($period) $pfDisplay .= ' (' . $period . ')';
                                    } elseif ($pft === 'percentage_gross_lease') {
                                        $pfDisplay = ($fmtPercent($counterData['purchase_fee_rental_period'] ?? null) ?? '—') . ' of Gross Lease Value';
                                    } elseif ($pft === 'percentage_monthly_rent') {
                                        $pfDisplay = ($fmtPercent($counterData['purchase_fee_rental_period'] ?? null) ?? '—') . ' of Monthly Rent';
                                    } elseif ($pft === 'combo') {
                                        $pfDisplay = $joinParts([
                                            $fmtPercent($counterData['purchase_fee_percentage_combo'] ?? null),
                                            $fmtMoney($counterData['purchase_fee_flat_combo'] ?? null),
                                        ]) ?? '—';
                                    } elseif ($pft === 'other') {
                                        $pfDisplay = $counterData['purchase_fee_other'] ?? '—';
                                    } else {
                                        $pfDisplay = $pft;
                                    }
                                @endphp
                                    <li class="mb-2" style="{{ isset($brokerMismatches['purchase_fee_type']) ? $mismatchStyle : '' }}">
                                        <span class="fw-semibold">Broker Lease Fee:</span> {{ $pfDisplay }}
                                        {!! isset($brokerMismatches['purchase_fee_type']) ? $mismatchBadge : '' !!}
                                    </li>
                                </ul>
                            </div>
                        </div>
                        @endif

                        {{-- ===== B) Broker Fee Timing ===== --}}
                        @if (!empty($counterData['broker_fee_timing']))
                        <div class="mb-4">
                            <h6 class="mb-2" style="color: #049399; font-weight: 600;">B) Payment Timing</h6>
                            <ul class="list-unstyled ps-3 mb-0">
                                <li class="mb-2" style="{{ isset($brokerMismatches['broker_fee_timing']) ? $mismatchStyle : '' }}">
                                    <span class="fw-semibold">Payment Timing:</span> {{ $counterData['broker_fee_timing'] }}
                                    {!! isset($brokerMismatches['broker_fee_timing']) ? $mismatchBadge : '' !!}
                                </li>
                                @if (!empty($counterData['broker_fee_days_from_rent']))
                                <li class="mb-2">
                                    <span class="fw-semibold">Days from Rent Receipt:</span> {{ $counterData['broker_fee_days_from_rent'] }}
                                </li>
                                @endif
                                @if (!empty($counterData['broker_fee_days_after_lease']))
                                <li class="mb-2">
                                    <span class="fw-semibold">Days After Lease Signing:</span> {{ $counterData['broker_fee_days_after_lease'] }}
                                </li>
                                @endif
                            </ul>
                        </div>
                        @endif

                        {{-- ===== C) Lease-Option Agreement ===== --}}
                        @if (!empty($counterData['interested_lease_option_agreement']))
                        <div class="mb-4">
                            <h6 class="mb-2" style="color: #049399; font-weight: 600;">C) Lease-Option Details</h6>
                            <ul class="list-unstyled ps-3 mb-0">
                                <li class="mb-2" style="{{ isset($brokerMismatches['interested_lease_option_agreement']) ? $mismatchStyle : '' }}">
                                    <span class="fw-semibold">Interested in a Lease-Option Agreement:</span> {{ $counterData['interested_lease_option_agreement'] }}
                                    {!! isset($brokerMismatches['interested_lease_option_agreement']) ? $mismatchBadge : '' !!}
                                </li>
                                @if (($counterData['interested_lease_option_agreement'] ?? '') === 'Yes')
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

                        {{-- ===== D) Purchase Fee (Interested in Selling) ===== --}}
                        @if (!empty($counterData['interested_in_selling']))
                        <div class="mb-4">
                            <h6 class="mb-2" style="color: #049399; font-weight: 600;">D) Purchase Fee Details</h6>
                            <ul class="list-unstyled ps-3 mb-0">
                                <li class="mb-2" style="{{ isset($brokerMismatches['interested_in_selling']) ? $mismatchStyle : '' }}">
                                    <span class="fw-semibold">Interested in Selling the Property:</span> {{ $counterData['interested_in_selling'] }}
                                    {!! isset($brokerMismatches['interested_in_selling']) ? $mismatchBadge : '' !!}
                                </li>
                                @if (($counterData['interested_in_selling'] ?? '') === 'Yes' && !empty($counterData['interested_in_selling_type']))
                                <li class="mb-2" style="{{ isset($brokerMismatches['interested_in_selling_type']) ? $mismatchStyle : '' }}">
                                    <span class="fw-semibold">Purchase Fee Type:</span> {{ $counterData['interested_in_selling_type'] }}
                                    {!! isset($brokerMismatches['interested_in_selling_type']) ? $mismatchBadge : '' !!}
                                </li>
                                @endif
                            </ul>
                        </div>
                        @endif

                        {{-- ===== E) Legal Terms ===== --}}
                        @if (!empty($counterData['protection_period']) || !empty($counterData['early_termination_fee_option']) || !empty($counterData['agency_agreement_timeframe']))
                        <div class="mb-4">
                            <h6 class="mb-2" style="color: #049399; font-weight: 600;">E) Legal Terms</h6>
                            <ul class="list-unstyled ps-3 mb-0">
                                @if (!empty($counterData['protection_period']))
                                <li class="mb-2" style="{{ isset($brokerMismatches['protection_period']) ? $mismatchStyle : '' }}">
                                    <span class="fw-semibold">Protection Period:</span> {{ $counterData['protection_period'] }} days
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
                                @endif
                                @endif
                                @if (!empty($counterData['agency_agreement_timeframe']))
                                @php
                                    $agencyTimeframe = $counterData['agency_agreement_timeframe'] ?? '';
                                    $agencyCustom    = $counterData['agency_agreement_custom'] ?? '';
                                    $isOtherTf       = is_string($agencyTimeframe) && strtolower(trim($agencyTimeframe)) === 'other';
                                    $agencyDisplay   = $isOtherTf ? ($agencyCustom ?: 'Other') : $agencyTimeframe;
                                @endphp
                                <li class="mb-2" style="{{ isset($brokerMismatches['agency_agreement_timeframe']) ? $mismatchStyle : '' }}">
                                    <span class="fw-semibold">Agency Agreement Timeframe:</span> {{ $agencyDisplay }}
                                    {!! isset($brokerMismatches['agency_agreement_timeframe']) ? $mismatchBadge : '' !!}
                                </li>
                                @endif
                            </ul>
                        </div>
                        @endif

                        {{-- ===== F) Brokerage Relationship ===== --}}
                        @if (!empty($counterData['brokerage_relationship']))
                        <div class="mb-4">
                            <h6 class="mb-2" style="color: #049399; font-weight: 600;">F) Brokerage Relationship</h6>
                            <ul class="list-unstyled ps-3 mb-0">
                                <li class="mb-2" style="{{ isset($brokerMismatches['brokerage_relationship']) ? $mismatchStyle : '' }}">
                                    <span class="fw-semibold">Acceptable Brokerage Relationship:</span> {{ $counterData['brokerage_relationship'] }}
                                    {!! isset($brokerMismatches['brokerage_relationship']) ? $mismatchBadge : '' !!}
                                </li>
                            </ul>
                        </div>
                        @endif

                        {{-- ===== Services ===== --}}
                        @if(!empty($allCounterServices) || !empty($allBaselineServices))
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
                        <a href="{{ route('landlord.agent.auction.view', $auction->id) }}" class="btn" style="background-color: #fff; border: 2px solid #049399; color: #049399; padding: 10px 20px; font-weight: 600;">
                            <i class="fas fa-eye me-2"></i>View Listing
                        </a>

                        @if($viewerRole === 'agent')
                        {{-- Agent: counter back to landlord, or accept/reject landlord's counter --}}
                        <form action="{{ route('landlord.agent.add.counter-bid') }}" method="POST" class="d-inline">
                            @csrf
                            <input type="hidden" name="auction_id" value="{{ $auction->id }}">
                            <input type="hidden" name="bid_id" value="{{ $bid->id }}">
                            <input type="hidden" name="counter_bid_id" value="{{ $activeCounter->id }}">
                            <button type="submit" class="btn" style="background-color: #ffc107; border: 2px solid #ffc107; color: #000; padding: 10px 20px; font-weight: 600;">
                                <i class="fas fa-reply me-2"></i>Counter Back
                            </button>
                        </form>
                        <form action="{{ route('landlord.hire.agent.auction.counter.bid.accept') }}" method="POST" class="d-inline">
                            @csrf
                            <input type="hidden" name="counter_bid_id" value="{{ $activeCounter->id }}">
                            <input type="hidden" name="auction_id" value="{{ $auction->id }}">
                            <button type="submit" class="btn" style="background-color: #28a745; border: 2px solid #28a745; color: #fff; padding: 10px 20px; font-weight: 600;" onclick="return confirm('Are you sure you want to accept these counter terms?')">
                                <i class="fas fa-check me-2"></i>Accept Counter
                            </button>
                        </form>
                        <form action="{{ route('landlord.hire.agent.auction.counter.bid.reject') }}" method="POST" class="d-inline">
                            @csrf
                            <input type="hidden" name="counter_bid_id" value="{{ $activeCounter->id }}">
                            <input type="hidden" name="auction_id" value="{{ $auction->id }}">
                            <button type="submit" class="btn" style="background-color: #dc3545; border: 2px solid #dc3545; color: #fff; padding: 10px 20px; font-weight: 600;" onclick="return confirm('Are you sure you want to reject these counter terms?')">
                                <i class="fas fa-times me-2"></i>Reject Counter
                            </button>
                        </form>
                        @elseif(!$awaitingCounterResponse)
                        {{-- Landlord: counter back to agent, or accept/reject the original bid --}}
                        <form action="{{ route('landlord.agent.add.counter-bid') }}" method="POST" class="d-inline">
                            @csrf
                            <input type="hidden" name="auction_id" value="{{ $auction->id }}">
                            <input type="hidden" name="bid_id" value="{{ $bid->id }}">
                            <input type="hidden" name="counter_bid_id" value="{{ $activeCounter->id }}">
                            <button type="submit" class="btn" style="background-color: #ffc107; border: 2px solid #ffc107; color: #000; padding: 10px 20px; font-weight: 600;">
                                <i class="fas fa-reply me-2"></i>Counter Back
                            </button>
                        </form>
                        <form action="{{ route('landlord.hire.agent.auction.bid.accept') }}" method="POST" class="d-inline">
                            @csrf
                            <input type="hidden" name="bid_id" value="{{ $bid->id }}">
                            <input type="hidden" name="auction_id" value="{{ $auction->id }}">
                            <button type="submit" class="btn" style="background-color: #28a745; border: 2px solid #28a745; color: #fff; padding: 10px 20px; font-weight: 600;" onclick="return confirm('Are you sure you want to accept this counter offer from the agent?')">
                                <i class="fas fa-check me-2"></i>Accept
                            </button>
                        </form>
                        <form action="{{ route('landlord.hire.agent.auction.bid.reject') }}" method="POST" class="d-inline">
                            @csrf
                            <input type="hidden" name="bid_id" value="{{ $bid->id }}">
                            <input type="hidden" name="auction_id" value="{{ $auction->id }}">
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
                        No counter terms have been submitted by the landlord yet. Your bid is still active and awaiting a response.
                        @else
                        No counter terms have been submitted by the agent yet.
                        @endif
                    </div>
                    <a href="{{ $viewerRole === 'agent' ? route('myBids', 'landlord-agent') : route('landlord.agent.auction.view', $auction->id) }}" class="btn" style="background-color: #049399; color: white; padding: 10px 20px; font-weight: 600;">
                        <i class="fas fa-arrow-left me-2"></i>Back
                    </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
