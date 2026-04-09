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
                        Agent's Counter Terms
                    </h4>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6 style="color: #049399; font-weight: 600;">Listing Information</h6>
                            <p><strong>Title:</strong> {{ $auction->title ?? 'Hire a Seller Agent Residential' }}</p>
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
                        $awaitingCounterResponse = false;
                        $previousCounter = null;
                    @endphp

                    @if($sellerCounter || $agentCounterBack)
                    @php
                        // UNIFIED: both parties see the MOST RECENTLY SUBMITTED counter (by updated_at).
                        // previousCounter = the immediately preceding counter used as the unified baseline.
                        if ($sellerCounter && $agentCounterBack) {
                            if ($sellerCounter->updated_at >= $agentCounterBack->updated_at) {
                                $activeCounter   = $sellerCounter;
                                $previousCounter = $agentCounterBack;
                            } else {
                                $activeCounter   = $agentCounterBack;
                                $previousCounter = $sellerCounter;
                            }
                        } elseif ($sellerCounter) {
                            $activeCounter   = $sellerCounter;
                            $previousCounter = null;
                        } else {
                            $activeCounter   = $agentCounterBack;
                            $previousCounter = null;
                        }
                        // Awaiting response: the current user submitted the active counter — they wait for the other party.
                        $awaitingCounterResponse = ($activeCounter && $activeCounter->user_id === Auth::id());
                        $counterPartyName = ($activeCounter && $activeCounter->user_id === $auction->user_id) ? 'Seller' : 'Agent';

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

                        // UNIFIED baseline: immediately previous counter (same for both parties).
                        // Fallback: listing owner's original terms (if this is the first counter in the chain).
                        if ($previousCounter) {
                            $baselineData  = $previousCounter->getAllMeta();
                            $baselineLabel = 'Previous Counter Terms';
                        } else {
                            $baselineData  = (array) $auction->get;
                            $baselineLabel = "Listing Owner's Original Terms";
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
                        $termsChangedCount = $score['terms_changed_count'];
                        $termsAddedCount   = $score['terms_added_count'];
                        $servicesScore     = $score['services_match_percent'];
                        $servicesMatched   = $score['services_matched_count'];
                        $servicesTotal     = $score['services_baseline_total'];
                        $servicesMissingCount = $score['services_missing_count'];
                        $servicesExtraCount   = $score['services_extra_count'];
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

                        // Dual score removed — both parties now see identical score (unified baseline eliminates the need).
                        $showDualScore      = false;
                        $latestCounterScore = null;

                        // diffScore = primary score: baseline is already the immediately previous counter (unified).
                        $diffBaselineData = $baselineData;
                        $diffScore        = $score;
                        $brokerMismatches = $diffScore['changed_terms'];
                        $servicesMissing  = $diffScore['missing_services'];
                        $servicesAdded    = $diffScore['extra_services'] ?? [];

                        $normalizeService = fn($s) => \App\Helpers\SellerBidMatchScoreHelper::normalizeService((string)$s);
                        // baselineNorm uses diffScore so "Added" badges reflect diff vs previous counter
                        $baselineNorm     = array_merge($diffScore['matched_services'], $diffScore['missing_services']);
                        $displayCatalog   = \App\Helpers\SellerBidMatchScoreHelper::getCatalog($counterPropType);

                        $mismatchStyle = 'background-color: #ffe6e6; padding: 2px 6px; border-radius: 4px; border-left: 3px solid #dc3545;';
                        $mismatchBadge = '<span class="badge" style="background-color: #dc3545; color: white; font-size: 0.7rem; vertical-align: middle; margin-left: 8px;">Changed</span>';
                        $addedStyle    = 'background-color: #e6ffe6; padding: 2px 6px; border-radius: 4px; border-left: 3px solid #28a745;';
                        $addedBadge    = '<span class="badge" style="background-color: #28a745; color: white; font-size: 0.7rem; vertical-align: middle; margin-left: 8px;">Added</span>';
                        $missingStyle  = 'background-color: #fff3cd; padding: 2px 6px; border-radius: 4px; border-left: 3px solid #ffc107;';
                        $missingBadge  = '<span class="badge" style="background-color: #ffc107; color: #000; font-size: 0.7rem; vertical-align: middle; margin-left: 8px;">Removed</span>';

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

                        // Baseline services for "Not Included" list — use diffBaselineData (diff vs previous counter)
                        $bsSvcRaw = $diffBaselineData['services'] ?? [];
                        if (is_string($bsSvcRaw)) $bsSvcRaw = json_decode($bsSvcRaw, true) ?? [];
                        $bsSvcRaw = is_array($bsSvcRaw) ? array_values(array_filter($bsSvcRaw)) : [];
                        $bsSvcRaw = array_values(array_filter(
                            $bsSvcRaw,
                            fn($s) => !empty(trim((string)$s)) && $s !== 'Other'
                                && in_array($normalizeService((string)$s), $displayCatalog, true)
                        ));
                        $bsOtherSvcRaw = $diffBaselineData['other_services'] ?? [];
                        if (is_string($bsOtherSvcRaw)) $bsOtherSvcRaw = json_decode($bsOtherSvcRaw, true) ?? [];
                        $bsOtherSvcRaw = is_array($bsOtherSvcRaw)
                            ? array_values(array_filter($bsOtherSvcRaw, fn($s) => is_string($s) && !empty(trim($s))))
                            : [];
                        $allBaselineServices = array_merge($bsSvcRaw, $bsOtherSvcRaw);
                        $counterOtherServices = $ctOtherSvcRaw;

                        // Service categories for grouped display (keyed by property type)
                        $residentialCategories = [
                            "📢 Property Marketing & Listing Promotion" => [
                                "List the property on the local Multiple Listing Service (MLS)",
                                "Syndicate the listing to third-party platforms (e.g., Zillow.com, Realtor.com, Trulia.com, Homes.com)",
                                "Create a branded flyer featuring the property's key highlights",
                                "Post the property on Facebook Marketplace",
                                "Post the property on Craigslist under the \"Homes for Sale\" category",
                                "Share the listing on Nextdoor in Neighborhood or Community Groups",
                                "Promote the listing on Facebook in Real Estate or Community Groups",
                                "Share the listing on Instagram using posts, stories, or reels",
                                "Promote the listing on LinkedIn in Professional or Real Estate Groups",
                                "Upload a TikTok video walkthrough of the property",
                                "Upload a YouTube video walkthrough of the property",
                                "Launch a mass email campaign promoting the listing",
                                "Distribute printed flyers or postcards in target geographic areas",
                                "Launch hyperlocal or interest-based digital ad campaigns promoting the listing",
                            ],
                            "🛠️ Listing Preparation & Presentation" => [
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
                                "Ensure proper notice is provided if the property is occupied",
                                "Install a real estate sign on the property",
                                "Install a lockbox for Agent access",
                                "Schedule and attend showings with prospective Buyers",
                                "Coordinate showings with Buyer's Agents",
                                "Collect and relay showing feedback to the Seller",
                            ],
                            "📑 Offer & Contract Management" => [
                                "Present all offers to the Seller and summarize key terms, pricing, and contingencies",
                                "Provide the Seller with the necessary disclosure forms required by state or local law",
                                "Negotiate price, terms, and contingencies with the Buyer's Agent or Buyer",
                                "Manage communications with the Buyer's Agent or Buyer",
                                "Draft and deliver counteroffers and manage revisions to the purchase agreement",
                                "Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties",
                                "Assist with inspection-related negotiations and Buyer requests for repairs",
                                "Monitor contract milestones, contingency periods, and financing deadlines",
                                "Provide referrals to Attorneys, Title Companies, and Escrow Professionals (referrals only — no endorsement or warranty is made)",
                            ],
                            "🧾 Closing Coordination & Transaction Management" => [
                                "Coordinate scheduling for inspections, appraisals, and other requested evaluations",
                                "Coordinate with the Buyer's Agent, Lender, Title, Escrow, and/or Attorney to prepare for Closing",
                                "Review the Settlement Statement and coordinate with all parties if corrections are needed",
                                "Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties",
                                "Schedule and confirm the Final Walkthrough",
                                "Schedule and confirm the Closing Appointment",
                            ],
                            "💡 Selling Strategy & Guidance" => [
                                "Provide a Comparative Market Analysis (CMA) with pricing recommendations based on comparable sales, neighborhood trends, and current market conditions",
                                "Provide general insight on local market trends, seasonal timing, and pricing thresholds",
                                "Recommend adjustments to pricing or marketing strategy if the property is not receiving sufficient interest",
                                "Provide general guidance on Seller obligations, required disclosures, and listing preparation",
                            ],
                        ];

                        $commercialCategories = [
                            "📢 Property Marketing & Listing Promotion" => [
                                "List the property on the local Multiple Listing Service (MLS)",
                                "List the property on Crexi.com",
                                "List the property on LoopNet.com",
                                "Create a branded flyer summarizing the property's investment highlights and key selling points",
                                "Post the property on Craigslist under the \"Commercial for Sale\" category",
                                "Promote the listing on Facebook in Commercial or Investor Real Estate Groups",
                                "Share the listing on Instagram using posts, stories, or reels",
                                "Promote the listing on LinkedIn in Professional, Real Estate, or Commercial Investment Groups",
                                "Upload a TikTok video walkthrough of the property",
                                "Upload a YouTube video walkthrough of the property",
                                "Launch a mass email campaign promoting the listing",
                                "Distribute printed flyers or postcards in target geographic areas",
                                "Launch hyperlocal or interest-based digital ad campaigns promoting the listing",
                            ],
                            "🛠️ Listing Preparation & Asset Presentation" => [
                                "Conduct a property walkthrough and provide recommendations for listing readiness",
                                "Provide a visual consultation on interior layout, cleanliness, and overall presentation",
                                "Provide a curb appeal consultation focused on exterior appearance and first impressions",
                                "Provide referrals to third-party vendors such as cleaners, handypeople, electricians, and landscapers (vendor fees billed separately; referrals only — no endorsement or warranty is made)",
                                "Compile essential marketing materials such as rent rolls, lease summaries, financial statements, and operating data (as available)",
                                "Organize zoning documentation, surveys, and public record reports (as available)",
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
                                "Respond to Buyer inquiries and screen for general qualifications",
                                "Provide Non-Disclosure Agreement (NDA) templates for access to confidential documents or showings",
                                "Ensure proper notice is provided if the property is occupied",
                                "Install a real estate sign on the property",
                                "Install a lockbox for Agent access",
                                "Schedule and attend showings with prospective Buyers",
                                "Coordinate showings with Buyer's Agents",
                                "Collect and relay showing feedback to the Seller",
                            ],
                            "📉 Offer & Contract Management" => [
                                "Present all offers to the Seller and summarize key terms, pricing, and contingencies",
                                "Provide the Seller with the necessary disclosure forms required by state or local law",
                                "Coordinate Letter of Intent (LOI) submissions, counteroffers, and contract revisions",
                                "Negotiate deal terms such as pricing, deposit structure, closing timelines, and due diligence periods",
                                "Manage communication with the Buyer's Agent or Buyer",
                                "Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties",
                                "Assist with inspection-related negotiations and Buyer requests for repairs or credits",
                                "Monitor contract contingencies, including financing, estoppel review, lease audits, and environmental reports",
                                "Provide referrals to Attorneys, Title Companies, Escrow Officers, or 1031 Exchange Professionals (referrals only — no endorsement or warranty is made)",
                            ],
                            "🧾 Closing Coordination & Transaction Management" => [
                                "Coordinate inspections, appraisals, and estoppel certificate delivery with the Buyer's Agent or Buyer, as applicable",
                                "Provide due diligence documentation such as lease agreements, estoppel certificates, rent rolls, utility summaries, and operating expense breakdowns (as available)",
                                "Coordinate with the Buyer's Agent, Lender, Title, Escrow, and/or Attorney to prepare for Closing",
                                "Review the Settlement Statement and coordinate with all parties if corrections are needed",
                                "Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties",
                                "Schedule and confirm the Final Walkthrough",
                                "Schedule and confirm the Closing Appointment",
                            ],
                            "💡 Selling Strategy & Guidance" => [
                                "Provide a Comparative Market Analysis (CMA) with pricing insights based on recent commercial property sales, rental income trends, market cap rates, and investor activity",
                                "Assist in estimating Capitalization Rate (Cap Rate), Price per Square Foot, or Gross Rent Multiplier (GRM) based on listing details and commercial comparables",
                                "Provide general insight on likely Buyer types (e.g., Owner-User, Investor, 1031 Exchange Buyer), common value drivers, and investment strategies",
                                "Recommend adjustments to pricing or marketing strategy if the property is not receiving sufficient interest",
                                "Provide general guidance on lease structures, expense ratios, and Tenant impacts",
                            ],
                        ];

                        $incomeCategories = [
                            "📢 Property Marketing & Listing Promotion" => [
                                "List the property on the local Multiple Listing Service (MLS)",
                                "Syndicate the listing to third-party platforms (e.g., Zillow.com, Realtor.com, Trulia.com, Homes.com)",
                                "List the property on Crexi.com",
                                "List the property on LoopNet.com",
                                "Create a branded flyer with key rental data (e.g., unit mix, gross income, occupancy)",
                                "Post the property on Craigslist under the \"Multi-Family for Sale\" category",
                                "Share the listing on Nextdoor in Neighborhood or Community Groups",
                                "Promote the listing on Facebook in Real Estate Investor or Multi-Family Buyer Groups",
                                "Share the listing on Instagram using posts, stories, or reels",
                                "Promote the listing on LinkedIn in Investment or Real Estate Groups",
                                "Upload a TikTok video walkthrough of the property",
                                "Upload a YouTube video walkthrough of the property",
                                "Launch a mass email campaign promoting the listing",
                                "Distribute printed flyers or postcards in target geographic areas",
                                "Launch hyperlocal or interest-based digital ad campaigns promoting the listing",
                            ],
                            "🛠️ Listing Preparation & Investment Packaging" => [
                                "Conduct a property walkthrough and provide recommendations for listing readiness",
                                "Provide a custom listing preparation checklist",
                                "Assist with assembling an income property packet, including rent roll, lease copies, and an income/expense summary (as available)",
                                "Provide a visual consultation focused on interior layout, cleanliness, and unit presentation",
                                "Provide a curb appeal consultation focused on exterior maintenance and first impressions",
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
                            "🏘️ Showings & Access Coordination" => [
                                "Respond to Buyer inquiries and screen for general qualifications",
                                "Provide Non-Disclosure Agreement (NDA) templates for confidential showings or document access",
                                "Ensure proper notice is provided if the property is occupied",
                                "Install a real estate sign on the property",
                                "Install a lockbox for Agent access",
                                "Schedule and attend showings with prospective Buyers",
                                "Coordinate showings with Buyer's Agents",
                                "Collect and relay showing feedback to the Seller",
                            ],
                            "📉 Offer & Contract Management" => [
                                "Present all offers to the Seller and summarize key terms, pricing, and contingencies",
                                "Provide the Seller with the necessary disclosure forms required by state or local law",
                                "Negotiate deal structure, deposits, due diligence timelines, and Buyer contingencies",
                                "Draft and deliver counteroffers and manage revisions to the purchase agreement",
                                "Manage communication with the Buyer's Agent or Buyers",
                                "Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties",
                                "Assist with inspection-related negotiations and Buyer requests for repairs",
                                "Monitor contract contingencies, including financing, lease audits, estoppel review, insurance, inspections, and environmental reports",
                                "Provide referrals to Attorneys, Title Companies, Escrow Officers, or 1031 Exchange Professionals. Referrals only — no endorsement or warranty is made",
                            ],
                            "🧾 Closing Coordination & Transaction Management" => [
                                "Review and organize due diligence documentation such as lease agreements, estoppel certificates, rent rolls, utility summaries, and operating expense breakdowns (as available)",
                                "Coordinate with the Buyer's Agent, Buyer's Lender, Title, Escrow, and/or Attorney to prepare for Closing",
                                "Review the Settlement Statement for accuracy and coordinate with relevant parties if corrections are needed",
                                "Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties",
                                "Schedule and confirm the Final Walkthrough",
                                "Schedule and confirm the Closing Appointment",
                            ],
                            "💡 Selling Strategy & Guidance" => [
                                "Provide a Comparative Market Analysis (CMA) with pricing insights based on recent income property sales, rental income trends, unit mix, and local investor activity",
                                "Assist in estimating Gross Rent Multiplier (GRM), Capitalization Rate (Cap Rate), or Price per Unit based on listing details and income property comparables ",
                                "Provide general insight on likely Investor Buyer behavior, common value drivers, and investment strategies",
                                "Recommend adjustments to pricing or marketing strategy if the property is not receiving sufficient interest",
                                "Provide general guidance on lease transfers, rent proration, security deposits, and possession timelines",
                            ],
                        ];

                        $businessCategories = [
                            "📢 Business Marketing & Listing Promotion" => [
                                "List the Business Opportunity on the local Multiple Listing Service (MLS)",
                                "List the Business Opportunity on Crexi.com",
                                "List the Business Opportunity on LoopNet.com",
                                "List the Business Opportunity on BizBuySell.com",
                                "List the Business Opportunity on BizQuest.com",
                                "List the Business Opportunity on BusinessesForSale.com",
                                "Create a branded flyer summarizing the Business's key features (e.g., industry, cash flow, assets)",
                                "Post the Business Opportunity on Craigslist under the \"Business for Sale\" category",
                                "Promote the listing on Facebook in Business Buyer, Franchise, or Investor Groups",
                                "Share the listing on Instagram using posts, stories, or reels",
                                "Promote the listing on LinkedIn in Business Acquisition, Startup, or Investor Groups",
                                "Upload a TikTok video summarizing the Business Opportunity",
                                "Upload a YouTube video summarizing the Business Opportunity",
                                "Launch a mass email campaign promoting the listing",
                                "Distribute printed flyers or postcards in target geographic areas",
                                "Launch hyperlocal or interest-based digital ad campaigns promoting the listing",
                            ],
                            "🛠️ Listing Preparation & Confidential Marketing" => [
                                "Conduct a preliminary Seller consultation to gather details about the Business's operations, assets, and goals",
                                "Provide a business sale checklist to collect financials, licenses, lease terms, and key operational details",
                                "Assist with preparing a non-confidential teaser or executive summary for marketing purposes",
                                "Organize internal documentation such as profit and loss statements, balance sheets, FF&E summaries, inventory lists, and staffing overviews (as available)",
                                "Refer third-party professionals such as valuation experts, accountants, or financial consultants, if requested (referrals only — no endorsement or warranty is made)",
                                "Compile essential marketing materials including business overviews, location descriptions, asset lists, and financial summaries",
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
                                "Respond to Buyer inquiries and screen for general qualifications",
                                "Provide Non-Disclosure Agreement (NDA) templates for confidential showings or document access",
                                "Ensure proper notice is provided if the property or business premises is occupied",
                                "Install a real estate sign on the property",
                                "Install a lockbox for Agent access",
                                "Schedule and attend showings with prospective Buyers",
                                "Coordinate showings with Buyer's Agents",
                                "Coordinate directly with Tenant(s) or business staff to arrange access for showings",
                            ],
                            "📉 Offer & Contract Management" => [
                                "Present all offers to the Seller and summarize key terms, pricing, and contingencies",
                                "Provide the Seller with the necessary disclosure forms required by state or local law",
                                "Negotiate deal terms such as pricing, deposit structure, closing timelines, and due diligence periods",
                                "Coordinate Letter of Intent (LOI) submissions, counteroffers, and revisions",
                                "Manage communication with the Buyer's Agent or Buyer",
                                "Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties",
                                "Assist with due diligence negotiations, Buyer requests, and contingency management",
                                "Provide referrals to Attorneys, Escrow Officers, or Business Brokers (referrals only — no endorsement or warranty is made)",
                            ],
                            "📃 Closing Coordination & Transaction Management" => [
                                "Coordinate Buyer inspections, management interviews, and site visits as applicable",
                                "Provide a transaction checklist and track key deadlines throughout the escrow period",
                                "Coordinate with the Buyer's Attorney, Escrow Officer, or designated Closing Facilitator",
                                "Review the Settlement Statement and coordinate corrections with relevant parties",
                                "Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties",
                                "Schedule and confirm the Final Walkthrough",
                                "Schedule and confirm the Closing Appointment",
                            ],
                            "💡 Selling Strategy & Guidance" => [
                                "Provide a business market overview with insights from recent comparable listings",
                                "Identify likely Buyer types (e.g., Owner-Operator, Investor, Franchisee) and discuss common deal structures (e.g., asset sale, stock sale)",
                                "Provide general insight on common value drivers such as cash flow, recurring revenue, transferable licenses, and key staff retention",
                                "Provide general guidance on operational transition timelines, staff notifications, lease assignments, and post-sale training periods",
                                "Provide referrals to business valuation, accounting, or legal professionals (referrals only — no endorsement or warranty is made)",
                            ],
                        ];

                        $vacantLandCategories = [
                            "📢 Property Marketing & Listing Promotion" => [
                                "List the property in the local Multiple Listing Service (MLS)",
                                "Syndicate the listing to third-party platforms (e.g., Zillow.com, Realtor.com, Trulia.com, Homes.com)",
                                "List the property on LandWatch.com",
                                "List the property on Land.com",
                                "List the property on LandAndFarm.com",
                                "Create a branded flyer highlighting lot features, zoning, and potential use",
                                "Post the listing on Facebook Marketplace",
                                "Post the listing on Craigslist under the \"Land for Sale\" category",
                                "Share the listing on Nextdoor in Neighborhood or Rural Groups",
                                "Promote the listing on Facebook in Land Buyers, Developers, or Homesteader Groups",
                                "Share the listing on Instagram using posts, stories, or reels",
                                "Promote the listing on LinkedIn in Land Acquisition or Investment Groups",
                                "Upload a TikTok video summarizing the land opportunity",
                                "Upload a YouTube video summarizing the land opportunity (e.g., drone tour, narrated overview)",
                                "Launch a mass email campaign promoting the listing Distribute printed flyers or postcards in target geographic areas",
                                "Launch hyperlocal or interest-based digital ad campaigns promoting the listing",
                            ],
                            "🛠️ Listing Preparation & Research" => [
                                "Provide a checklist to gather parcel data (e.g., APN, lot size, zoning, utilities, and access)",
                                "Assist with collecting public records, flood zone data, and land use information (as available)",
                                "Provide referrals to surveyors, soil testers, or land service professionals (referrals only — no endorsement or warranty is made)",
                            ],
                            "📸 Photography, Video & Virtual Media" => [
                                "Provide professional property photography",
                                "Provide aerial (drone) photography (subject to FAA Part 107 compliance)",
                                "Provide a video overview or narrated walkthrough",
                                "Provide a 3D virtual tour (if applicable)",
                                "Provide digital enhancements to media assets",
                                "Provide a parcel map, topographical image, or plot plan (non-certified; for marketing purposes only)",
                            ],
                            "🏡 Showings & Access Coordination" => [
                                "Install a real estate sign on the property",
                                "Schedule and attend showings with prospective Buyers",
                                "Coordinate showings with Buyer's Agents",
                                "Collect and relay showing feedback to the Seller",
                            ],
                            "📉 Offer & Contract Management" => [
                                "Present all offers to the Seller and summarize key terms, pricing, and contingencies",
                                "Provide the Seller with the necessary disclosure forms required by state or local law",
                                "Negotiate price, due diligence timelines, and closing terms",
                                "Draft and deliver counteroffers and manage revisions to the purchase agreement",
                                "Manage communication with the Buyer's Agent or Buyer",
                                "Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties",
                                "Monitor contract contingencies, including survey, zoning verification, financing, and environmental reviews",
                                "Provide referrals to Attorneys, Title Companies, Escrow Officers, or Land Use Professionals (referrals only — no endorsement or warranty is made)",
                            ],
                            "📃 Closing Coordination & Transaction Management" => [
                                "Coordinate surveys, site visits, or environmental access with the Buyer or Buyer's Agent, as applicable",
                                "Coordinate with Title, Escrow, and/or Attorney to prepare for Closing",
                                "Review the Settlement Statement and coordinate with all parties if corrections are needed",
                                "Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties",
                                "Schedule and confirm the Final Walkthrough",
                                "Schedule and confirm the Closing Appointment",
                            ],
                            "💡 Selling Strategy & Guidance" => [
                                "Provide a Comparative Market Analysis (CMA) with pricing recommendations based on recent land sales, zoning categories, and location-based trends",
                                "Provide general insight on permitted uses, utility access, parcel features, and Buyer demand in the area",
                                "Recommend adjustments to pricing or marketing strategy if the land is not receiving sufficient interest",
                                "Provide general guidance on Seller obligations, disclosure requirements, and listing preparation",
                            ],
                        ];

                        $pt = strtolower(trim($counterPropType));
                        if (str_contains($pt, 'income')) {
                            $categories = $incomeCategories;
                        } elseif (str_contains($pt, 'commercial')) {
                            $categories = $commercialCategories;
                        } elseif (str_contains($pt, 'business')) {
                            $categories = $businessCategories;
                        } elseif (str_contains($pt, 'vacant') || str_contains($pt, 'land')) {
                            $categories = $vacantLandCategories;
                        } else {
                            $categories = $residentialCategories;
                        }
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

                        {{-- Broker Compensation & Agency Agreement Terms --}}
                        <div class="mb-4">
                            <h6 class="mb-3" style="color: #049399; font-weight: 600; border-bottom: 2px solid #049399; padding-bottom: 8px;">
                                <i class="fas fa-handshake me-2"></i>Broker Compensation & Agency Agreement Terms
                            </h6>

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
                                // Build full display value for Buyer's Broker Commission Type
                                $ctCommStructTypeDisplay = $ctCommStructType;
                                if ($ctCommStructType === 'Flat Fee' && data_get($counterData, 'commission_structure_type_fee_flat')) {
                                    $ctCommStructTypeDisplay = ($fmtMoney(data_get($counterData, 'commission_structure_type_fee_flat')) ?? '-') . ' (Flat Fee)';
                                } elseif ($ctCommStructType === 'Percentage of the Total Purchase Price' && data_get($counterData, 'commission_structure_type_fee_percentage')) {
                                    $ctCommStructTypeDisplay = ($fmtPercent(data_get($counterData, 'commission_structure_type_fee_percentage')) ?? '-') . ' of Total Purchase Price';
                                } elseif ($ctCommStructType === 'Percentage of the Total Purchase Price + Flat Fee') {
                                    $ctCommStructTypeDisplay = $joinParts([
                                        data_get($counterData, 'commission_structure_type_fee_percentage_combo') ? ($fmtPercent(data_get($counterData, 'commission_structure_type_fee_percentage_combo')) . ' of Total Purchase Price') : null,
                                        $fmtMoney(data_get($counterData, 'commission_structure_type_fee_flat_combo')),
                                    ]) ?? $ctCommStructType;
                                } elseif ($ctCommStructType === 'other' && data_get($counterData, 'commission_structure_type_fee_other')) {
                                    $ctCommStructTypeDisplay = data_get($counterData, 'commission_structure_type_fee_other');
                                }
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
                                    <li class="mb-2" style="{{ isset($brokerMismatches['purchase_fee_type']) ? $mismatchStyle : '' }}">
                                        <span class="fw-semibold">Seller's Broker Purchase Fee:</span>
                                        {{ $ctPurchaseFeeDisplay }}
                                        {!! isset($brokerMismatches['purchase_fee_type']) ? $mismatchBadge : '' !!}
                                    </li>
                                    @endif
                                    @if($ctNominal)
                                    <li class="mb-2" style="{{ isset($brokerMismatches['nominal']) ? $mismatchStyle : '' }}">
                                        <span class="fw-semibold">Nominal Consideration Fee:</span> {{ $fmtMoney($ctNominal) ?? '-' }}
                                        {!! isset($brokerMismatches['nominal']) ? $mismatchBadge : '' !!}
                                    </li>
                                    @endif
                                    @if($ctCommStruct)
                                    <li class="mb-2" style="{{ isset($brokerMismatches['commission_structure']) ? $mismatchStyle : '' }}">
                                        <span class="fw-semibold">Buyer's Broker Commission Structure:</span> {{ $ctCommStruct }}
                                        {!! isset($brokerMismatches['commission_structure']) ? $mismatchBadge : '' !!}
                                    </li>
                                    @endif
                                    @if($ctCommStructType)
                                    <li class="mb-2" style="{{ isset($brokerMismatches['commission_structure_type']) ? $mismatchStyle : '' }}">
                                        <span class="fw-semibold">Buyer's Broker Commission Type:</span> {{ $ctCommStructTypeDisplay }}
                                        {!! isset($brokerMismatches['commission_structure_type']) ? $mismatchBadge : '' !!}
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
                                    <li class="mb-2" style="{{ isset($brokerMismatches['interested_purchase_fee_type']) ? $mismatchStyle : '' }}">
                                        <span class="fw-semibold">Interested in Offering a Lease Agreement:</span> {{ $ctInterestedLease }}
                                        {!! isset($brokerMismatches['interested_purchase_fee_type']) ? $mismatchBadge : '' !!}
                                    </li>
                                    @if(strtolower($ctInterestedLease) === 'yes' && $ctLeasingFeeAmt)
                                    <li class="mb-2" style="{{ isset($brokerMismatches['seller_leasing_fee_type']) ? $mismatchStyle : '' }}">
                                        <span class="fw-semibold">Seller's Broker Leasing Fee:</span> {{ $ctLeasingFeeAmt }}
                                        {!! isset($brokerMismatches['seller_leasing_fee_type']) ? $mismatchBadge : '' !!}
                                    </li>
                                    @elseif(strtolower($ctInterestedLease) === 'yes' && isset($brokerMismatches['seller_leasing_fee_type']))
                                    <li class="mb-2" style="{{ $mismatchStyle }}"><span class="fw-semibold">Seller's Broker Leasing Fee:</span> —{!! $mismatchBadge !!}</li>
                                    @endif
                                </ul>
                            </div>
                            @endif

                            {{-- C) Lease-Option Terms --}}
                            @if($ctLeaseOption)
                            <div class="mb-4">
                                <h6 class="mb-2" style="color: #049399; font-weight: 600;">C) Lease-Option Terms</h6>
                                <ul class="list-unstyled ps-3 mb-0">
                                    <li class="mb-2" style="{{ isset($brokerMismatches['interested_lease_option_agreement']) ? $mismatchStyle : '' }}">
                                        <span class="fw-semibold">Interested in Lease-Option Agreement:</span> {{ $ctLeaseOption }}
                                        {!! isset($brokerMismatches['interested_lease_option_agreement']) ? $mismatchBadge : '' !!}
                                    </li>
                                    @if(strtolower($ctLeaseOption) === 'yes' && $ctLeaseValue)
                                    <li class="mb-2" style="{{ (isset($brokerMismatches['lease_type']) || isset($brokerMismatches['lease_value'])) ? $mismatchStyle : '' }}">
                                        <span class="fw-semibold">Compensation for Lease-Option Agreement:</span>
                                        {{ $ctLeaseType === 'percent' ? ($fmtPercent($ctLeaseValue) . ' of Total Purchase Price') : $fmtMoney($ctLeaseValue) }}
                                        {!! (isset($brokerMismatches['lease_type']) || isset($brokerMismatches['lease_value'])) ? $mismatchBadge : '' !!}
                                    </li>
                                    @elseif(strtolower($ctLeaseOption) === 'yes' && (isset($brokerMismatches['lease_type']) || isset($brokerMismatches['lease_value'])))
                                    <li class="mb-2" style="{{ $mismatchStyle }}"><span class="fw-semibold">Compensation for Lease-Option Agreement:</span> —{!! $mismatchBadge !!}</li>
                                    @endif
                                    @if(strtolower($ctLeaseOption) === 'yes' && $ctPurchaseValue)
                                    <li class="mb-2" style="{{ (isset($brokerMismatches['purchase_type']) || isset($brokerMismatches['purchase_value'])) ? $mismatchStyle : '' }}">
                                        <span class="fw-semibold">Compensation if Purchase Option is Exercised:</span>
                                        {{ $ctPurchaseType === 'percent' ? ($fmtPercent($ctPurchaseValue) . ' of Total Purchase Price') : $fmtMoney($ctPurchaseValue) }}
                                        {!! (isset($brokerMismatches['purchase_type']) || isset($brokerMismatches['purchase_value'])) ? $mismatchBadge : '' !!}
                                    </li>
                                    @elseif(strtolower($ctLeaseOption) === 'yes' && (isset($brokerMismatches['purchase_type']) || isset($brokerMismatches['purchase_value'])))
                                    <li class="mb-2" style="{{ $mismatchStyle }}"><span class="fw-semibold">Compensation if Purchase Option is Exercised:</span> —{!! $mismatchBadge !!}</li>
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
                                    <li class="mb-2" style="{{ isset($brokerMismatches['early_termination_fee_option']) ? $mismatchStyle : '' }}">
                                        <span class="fw-semibold">Early Termination Fee:</span>
                                        {{ ucfirst($ctEarlyTermOpt) }}
                                        {!! isset($brokerMismatches['early_termination_fee_option']) ? $mismatchBadge : '' !!}
                                    </li>
                                    @if(strtolower($ctEarlyTermOpt) === 'yes' && $ctEarlyTermAmt)
                                    <li class="mb-2" style="{{ isset($brokerMismatches['early_termination_fee_amount']) ? $mismatchStyle : '' }}">
                                        <span class="fw-semibold">Termination Fee Amount:</span>
                                        {{ $fmtMoney($ctEarlyTermAmt) ?? $ctEarlyTermAmt }}
                                        {!! isset($brokerMismatches['early_termination_fee_amount']) ? $mismatchBadge : '' !!}
                                    </li>
                                    @elseif(strtolower($ctEarlyTermOpt) === 'yes' && isset($brokerMismatches['early_termination_fee_amount']))
                                    <li class="mb-2" style="{{ $mismatchStyle }}"><span class="fw-semibold">Termination Fee Amount:</span> —{!! $mismatchBadge !!}</li>
                                    @endif
                                    @endif
                                    @if($ctRetainerOpt)
                                    <li class="mb-2" style="{{ isset($brokerMismatches['retainer_fee_option']) ? $mismatchStyle : '' }}">
                                        <span class="fw-semibold">Retainer Fee:</span>
                                        {{ ucfirst($ctRetainerOpt) }}
                                        {!! isset($brokerMismatches['retainer_fee_option']) ? $mismatchBadge : '' !!}
                                    </li>
                                    @if(strtolower($ctRetainerOpt) === 'yes' && $ctRetainerAmt)
                                    <li class="mb-2" style="{{ isset($brokerMismatches['retainer_fee_amount']) ? $mismatchStyle : '' }}">
                                        <span class="fw-semibold">Retainer Fee Amount:</span>
                                        {{ $fmtMoney($ctRetainerAmt) ?? $ctRetainerAmt }}
                                        {!! isset($brokerMismatches['retainer_fee_amount']) ? $mismatchBadge : '' !!}
                                    </li>
                                    @elseif(strtolower($ctRetainerOpt) === 'yes' && isset($brokerMismatches['retainer_fee_amount']))
                                    <li class="mb-2" style="{{ $mismatchStyle }}"><span class="fw-semibold">Retainer Fee Amount:</span> —{!! $mismatchBadge !!}</li>
                                    @endif
                                    @if(strtolower($ctRetainerOpt) === 'yes' && $ctRetainerApp)
                                    <li class="mb-2" style="{{ isset($brokerMismatches['retainer_fee_application']) ? $mismatchStyle : '' }}">
                                        <span class="fw-semibold">Retainer Fee Application:</span>
                                        {{ $ctRetainerApp === 'applied' ? 'Applied toward final compensation' : 'Charged in addition to final compensation' }}
                                        {!! isset($brokerMismatches['retainer_fee_application']) ? $mismatchBadge : '' !!}
                                    </li>
                                    @elseif(strtolower($ctRetainerOpt) === 'yes' && isset($brokerMismatches['retainer_fee_application']))
                                    <li class="mb-2" style="{{ $mismatchStyle }}"><span class="fw-semibold">Retainer Fee Application:</span> —{!! $mismatchBadge !!}</li>
                                    @endif
                                    @endif
                                    @if($ctRetainedDep)
                                    <li class="mb-2" style="{{ isset($brokerMismatches['retained_deposits']) ? $mismatchStyle : '' }}">
                                        <span class="fw-semibold">Seller's Broker's Share of Retained Deposits:</span> {{ $fmtPercent($ctRetainedDep) ?? $ctRetainedDep }}
                                        {!! isset($brokerMismatches['retained_deposits']) ? $mismatchBadge : '' !!}
                                    </li>
                                    @endif
                                    @if($ctProtPeriod)
                                    <li class="mb-2" style="{{ isset($brokerMismatches['protection_period']) ? $mismatchStyle : '' }}">
                                        <span class="fw-semibold">Protection Period:</span> {{ $ctProtPeriod }} days
                                        {!! isset($brokerMismatches['protection_period']) ? $mismatchBadge : '' !!}
                                    </li>
                                    @endif
                                    @if($ctAgencyTf)
                                    <li class="mb-2" style="{{ isset($brokerMismatches['agency_agreement_timeframe']) ? $mismatchStyle : '' }}">
                                        <span class="fw-semibold">Seller Agency Agreement Timeframe:</span> {{ $ctAgencyDsp }}
                                        {!! isset($brokerMismatches['agency_agreement_timeframe']) ? $mismatchBadge : '' !!}
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
                                    <li class="mb-2" style="{{ isset($brokerMismatches['brokerage_relationship']) ? $mismatchStyle : '' }}">
                                        <span class="fw-semibold">Acceptable Brokerage Relationship:</span> {{ $ctBrokerRel }}
                                        {!! isset($brokerMismatches['brokerage_relationship']) ? $mismatchBadge : '' !!}
                                    </li>
                                </ul>
                            </div>
                            @endif
                        </div>

                        {{-- Requested Services (category-grouped, with Added/Not-in-Counter badges) --}}
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
                                            @if (!$serviceData['inBaseline'])
                                                <i class="fas fa-plus-circle me-2" style="color: #28a745;"></i>
                                            @else
                                                <span class="me-2" style="color: #6c757d;">•</span>
                                            @endif
                                            {{ $serviceData['service'] }}
                                            @if (!$serviceData['inBaseline'])
                                            {!! $addedBadge !!}
                                            @endif
                                        </li>
                                        @if (strtolower(trim($serviceData['service'])) === 'provide digital photo enhancements')
                                        @php
                                            $ctPhotoEnhRaw = $counterData['photo_enhancements'] ?? [];
                                            if (is_string($ctPhotoEnhRaw)) $ctPhotoEnhRaw = json_decode($ctPhotoEnhRaw, true) ?: [];
                                            $ctCustomEnh = $counterData['custom_enhancement'] ?? '';
                                            $ctEnhOrder = ['Basic edits (brightness, contrast, cropping)', 'Twilight conversion (convert daytime photo to sunset look)', 'Object removal (e.g., cars, trash cans, furniture, etc.)', 'Virtual twilight photography', 'Color correction or sky replacement', 'Other'];
                                        @endphp
                                        @if (!empty($ctPhotoEnhRaw))
                                        <ul style="padding-left: 1.5rem; margin: 4px 0 4px 0; list-style: disc;">
                                            @foreach ($ctEnhOrder as $ctEnh)
                                                @if (in_array($ctEnh, $ctPhotoEnhRaw))
                                                    @if ($ctEnh === 'Other' && !empty($ctCustomEnh))
                                                        <li style="font-size: 0.85rem;">{{ $ctCustomEnh }}</li>
                                                    @elseif ($ctEnh !== 'Other')
                                                        <li style="font-size: 0.85rem;">{{ $ctEnh }}</li>
                                                    @endif
                                                @endif
                                            @endforeach
                                        </ul>
                                        @endif
                                        @endif
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
                                            <i class="fas fa-plus-circle me-2" style="color: #28a745;"></i>
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
                    </div>{{-- end gradient container --}}

                    {{-- Broker Additional Terms (standalone, outside gradient container) --}}
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
                    <div class="d-flex gap-2 flex-wrap mt-4">

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
                            This bid has been {{ $bidStatus }}.
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
                            This bid has been {{ $bidStatus }}.
                        </div>
                        @endif

                        <a href="{{ route('seller.agent.auction.detail', $auction->id) }}"
                           class="btn" style="background-color:#fff;border:2px solid #049399;color:#049399;padding:10px 20px;font-weight:600;">
                            <i class="fas fa-eye me-2"></i>View Listing
                        </a>
                    </div>

                    @else
                    <div class="alert alert-info">
                        <i class="fa fa-info-circle me-2"></i>
                        No counter terms have been submitted for this bid yet.
                    </div>
                    @if($viewerRole === 'seller' && !$bidIsTerminal)
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
