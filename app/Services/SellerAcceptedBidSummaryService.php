<?php

namespace App\Services;

use App\Models\AcceptedBidSummary;
use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionBid;
use App\Models\SellerCounterTerm;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class SellerAcceptedBidSummaryService
{
    protected array $residentialServiceCategories = [
        'Property Marketing & Listing Promotion' => [
            'List the property on the local Multiple Listing Service (MLS)',
            'Syndicate the listing to third-party platforms (e.g., Zillow.com, Realtor.com, Trulia.com, Homes.com)',
            'Create a branded flyer featuring the property\'s key highlights',
            'Post the property on Facebook Marketplace',
            'Post the property on Craigslist under the "Homes for Sale" category',
            'Share the listing on Nextdoor in Neighborhood or Community Groups',
            'Promote the listing on Facebook in Real Estate or Community Groups',
            'Share the listing on Instagram using posts, stories, or reels',
            'Promote the listing on LinkedIn in Professional or Real Estate Groups',
            'Upload a TikTok video walkthrough of the property',
            'Upload a YouTube video walkthrough of the property',
            'Launch a mass email campaign promoting the listing',
            'Distribute printed flyers or postcards in target geographic areas',
            'Launch hyperlocal or interest-based digital ad campaigns promoting the listing',
        ],
        'Listing Preparation & Presentation' => [
            'Conduct a property walkthrough and provide recommendations for listing readiness',
            'Provide a custom listing preparation checklist',
            'Collect property details and prepare MLS remarks and a public listing description',
            'Provide a visual consultation for interior layout, cleanliness, and presentation',
            'Provide a curb appeal consultation focused on exterior presentation',
            'Provide referrals to third-party vendors (e.g., cleaners, handypeople, electricians, landscapers). Vendor fees billed separately. Referrals only \u2014 no endorsement or warranty is made',
        ],
        'Photography, Video & Virtual Media' => [
            'Provide professional property photography',
            'Provide aerial (drone) photography (subject to FAA Part 107 compliance)',
            'Provide a video walkthrough tour',
            'Provide a 3D virtual tour',
            'Provide virtual staging (digital enhancements only; no physical staging)',
            'Provide digital photo enhancements',
            'Create a basic schematic floor plan (non-certified; for marketing purposes only)',
        ],
        'Showings & Access Coordination' => [
            'Ensure proper notice is provided if the property is occupied',
            'Install a real estate sign on the property',
            'Install a lockbox for Agent access',
            'Schedule and attend showings with prospective Buyers',
            'Coordinate showings with Buyer\'s Agents',
            'Collect and relay showing feedback to the Seller',
        ],
        'Offer & Contract Management' => [
            'Present all offers to the Seller and summarize key terms, pricing, and contingencies',
            'Provide the Seller with the necessary disclosure forms required by state or local law',
            'Negotiate price, terms, and contingencies with the Buyer\'s Agent or Buyer',
            'Manage communications with the Buyer\'s Agent or Buyer',
            'Draft and deliver counteroffers and manage revisions to the purchase agreement',
            'Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties',
            'Assist with inspection-related negotiations and Buyer requests for repairs',
            'Monitor contract milestones, contingency periods, and financing deadlines',
            'Provide referrals to Attorneys, Title Companies, and Escrow Professionals (referrals only \u2014 no endorsement or warranty is made)',
        ],
        'Closing Coordination & Transaction Management' => [
            'Coordinate scheduling for inspections, appraisals, and other requested evaluations',
            'Coordinate with the Buyer\'s Agent, Lender, Title, Escrow, and/or Attorney to prepare for Closing',
            'Review the Settlement Statement and coordinate with all parties if corrections are needed',
            'Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties',
            'Schedule and confirm the Final Walkthrough',
            'Schedule and confirm the Closing Appointment',
        ],
        'Selling Strategy & Guidance' => [
            'Provide a Comparative Market Analysis (CMA) with pricing recommendations based on comparable sales, neighborhood trends, and current market conditions',
            'Provide general insight on local market trends, seasonal timing, and pricing thresholds',
            'Recommend adjustments to pricing or marketing strategy if the property is not receiving sufficient interest',
            'Provide general guidance on Seller obligations, required disclosures, and listing preparation',
        ],
    ];

    protected array $incomeServiceCategories = [
        'Property Marketing & Listing Promotion' => [
            'List the property on the local Multiple Listing Service (MLS)',
            'Syndicate the listing to third-party platforms (e.g., Zillow.com, Realtor.com, Trulia.com, Homes.com)',
            'List the property on Crexi.com',
            'List the property on LoopNet.com',
            'Create a branded flyer with key rental data (e.g., unit mix, gross income, occupancy)',
            'Post the property on Craigslist under the "Multi-Family for Sale" category',
            'Share the listing on Nextdoor in Neighborhood or Community Groups',
            'Promote the listing on Facebook in Real Estate Investor or Multi-Family Buyer Groups',
            'Share the listing on Instagram using posts, stories, or reels',
            'Promote the listing on LinkedIn in Investment or Real Estate Groups',
            'Upload a TikTok video walkthrough of the property',
            'Upload a YouTube video walkthrough of the property',
            'Launch a mass email campaign promoting the listing',
            'Distribute printed flyers or postcards in target geographic areas',
            'Launch hyperlocal or interest-based digital ad campaigns promoting the listing',
        ],
        'Listing Preparation & Investment Packaging' => [
            'Conduct a property walkthrough and provide recommendations for listing readiness',
            'Provide a custom listing preparation checklist',
            'Assist with assembling an income property packet, including rent roll, lease copies, and an income/expense summary (as available)',
            'Provide a visual consultation focused on interior layout, cleanliness, and unit presentation',
            'Provide a curb appeal consultation focused on exterior maintenance and first impressions',
            'Provide referrals to third-party vendors (e.g., cleaners, handypeople, electricians, landscapers). Vendor fees billed separately. Referrals only \u2014 no endorsement or warranty is made',
        ],
        'Photography, Video & Virtual Media' => [
            'Provide professional property photography',
            'Provide aerial (drone) photography (subject to FAA Part 107 compliance)',
            'Provide a video walkthrough tour',
            'Provide a 3D virtual tour',
            'Provide virtual staging (digital enhancements only; no physical staging)',
            'Provide digital photo enhancements',
            'Create a basic schematic floor plan (non-certified; for marketing purposes only)',
        ],
        'Showings & Access Coordination' => [
            'Respond to Buyer inquiries and screen for general qualifications',
            'Provide Non-Disclosure Agreement (NDA) templates for confidential showings or document access',
            'Ensure proper notice is provided if the property is occupied',
            'Install a real estate sign on the property',
            'Install a lockbox for Agent access',
            'Schedule and attend showings with prospective Buyers',
            'Coordinate showings with Buyer\'s Agents',
            'Collect and relay showing feedback to the Seller',
        ],
        'Offer & Contract Management' => [
            'Present all offers to the Seller and summarize key terms, pricing, and contingencies',
            'Provide the Seller with the necessary disclosure forms required by state or local law',
            'Negotiate deal structure, deposits, due diligence timelines, and Buyer contingencies',
            'Draft and deliver counteroffers and manage revisions to the purchase agreement',
            'Manage communication with the Buyer\'s Agent or Buyers',
            'Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties',
            'Assist with inspection-related negotiations and Buyer requests for repairs',
            'Monitor contract contingencies, including financing, lease audits, estoppel review, insurance, inspections, and environmental reports',
            'Provide referrals to Attorneys, Title Companies, Escrow Officers, or 1031 Exchange Professionals. Referrals only \u2014 no endorsement or warranty is made',
        ],
        'Closing Coordination & Transaction Management' => [
            'Review and organize due diligence documentation such as lease agreements, estoppel certificates, rent rolls, utility summaries, and operating expense breakdowns (as available)',
            'Coordinate with the Buyer\'s Agent, Buyer\'s Lender, Title, Escrow, and/or Attorney to prepare for Closing',
            'Review the Settlement Statement for accuracy and coordinate with relevant parties if corrections are needed',
            'Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties',
            'Schedule and confirm the Final Walkthrough',
            'Schedule and confirm the Closing Appointment',
        ],
        'Selling Strategy & Guidance' => [
            'Provide a Comparative Market Analysis (CMA) with pricing insights based on recent income property sales, rental income trends, unit mix, and local investor activity',
            'Assist in estimating Gross Rent Multiplier (GRM), Capitalization Rate (Cap Rate), or Price per Unit based on listing details and income property comparables ',
            'Provide general insight on likely Investor Buyer behavior, common value drivers, and investment strategies',
            'Recommend adjustments to pricing or marketing strategy if the property is not receiving sufficient interest',
            'Provide general guidance on lease transfers, rent proration, security deposits, and possession timelines',
        ],
    ];

    protected array $commercialServiceCategories = [
        'Property Marketing & Listing Promotion' => [
            'List the property on the local Multiple Listing Service (MLS)',
            'List the property on Crexi.com',
            'List the property on LoopNet.com',
            'Create a branded flyer summarizing the property\'s investment highlights and key selling points',
            'Post the property on Craigslist under the "Commercial for Sale" category',
            'Promote the listing on Facebook in Commercial or Investor Real Estate Groups',
            'Share the listing on Instagram using posts, stories, or reels',
            'Promote the listing on LinkedIn in Professional, Real Estate, or Commercial Investment Groups',
            'Upload a TikTok video walkthrough of the property',
            'Upload a YouTube video walkthrough of the property',
            'Launch a mass email campaign promoting the listing',
            'Distribute printed flyers or postcards in target geographic areas',
            'Launch hyperlocal or interest-based digital ad campaigns promoting the listing',
        ],
        'Listing Preparation & Asset Presentation' => [
            'Conduct a property walkthrough and provide recommendations for listing readiness',
            'Provide a visual consultation on interior layout, cleanliness, and overall presentation',
            'Provide a curb appeal consultation focused on exterior appearance and first impressions',
            'Provide referrals to third-party vendors such as cleaners, handypeople, electricians, and landscapers (vendor fees billed separately; referrals only \u2014 no endorsement or warranty is made)',
            'Compile essential marketing materials such as rent rolls, lease summaries, financial statements, and operating data (as available)',
            'Organize zoning documentation, surveys, and public record reports (as available)',
        ],
        'Photography, Video & Virtual Media' => [
            'Provide professional property photography',
            'Provide aerial (drone) photography (subject to FAA Part 107 compliance)',
            'Provide a video walkthrough tour',
            'Provide a 3D virtual tour',
            'Provide virtual staging (digital enhancements only; no physical staging)',
            'Provide digital photo enhancements',
            'Create a basic schematic floor plan (non-certified; for marketing purposes only)',
        ],
        'Showings & Access Coordination' => [
            'Respond to Buyer inquiries and screen for general qualifications',
            'Provide Non-Disclosure Agreement (NDA) templates for access to confidential documents or showings',
            'Ensure proper notice is provided if the property is occupied',
            'Install a real estate sign on the property',
            'Install a lockbox for Agent access',
            'Schedule and attend showings with prospective Buyers',
            'Coordinate showings with Buyer\'s Agents',
            'Collect and relay showing feedback to the Seller',
        ],
        'Offer & Contract Management' => [
            'Present all offers to the Seller and summarize key terms, pricing, and contingencies',
            'Provide the Seller with the necessary disclosure forms required by state or local law',
            'Coordinate Letter of Intent (LOI) submissions, counteroffers, and contract revisions',
            'Negotiate deal terms such as pricing, deposit structure, closing timelines, and due diligence periods',
            'Manage communication with the Buyer\'s Agent or Buyer',
            'Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties',
            'Assist with inspection-related negotiations and Buyer requests for repairs or credits',
            'Monitor contract contingencies, including financing, estoppel review, lease audits, and environmental reports',
            'Provide referrals to Attorneys, Title Companies, Escrow Officers, or 1031 Exchange Professionals (referrals only \u2014 no endorsement or warranty is made)',
        ],
        'Closing Coordination & Transaction Management' => [
            'Coordinate inspections, appraisals, and estoppel certificate delivery with the Buyer\'s Agent or Buyer, as applicable',
            'Provide due diligence documentation such as lease agreements, estoppel certificates, rent rolls, utility summaries, and operating expense breakdowns (as available)',
            'Coordinate with the Buyer\'s Agent, Lender, Title, Escrow, and/or Attorney to prepare for Closing',
            'Review the Settlement Statement and coordinate with all parties if corrections are needed',
            'Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties',
            'Schedule and confirm the Final Walkthrough',
            'Schedule and confirm the Closing Appointment',
        ],
        'Selling Strategy & Guidance' => [
            'Provide a Comparative Market Analysis (CMA) with pricing insights based on recent commercial property sales, rental income trends, market cap rates, and investor activity',
            'Assist in estimating Capitalization Rate (Cap Rate), Price per Square Foot, or Gross Rent Multiplier (GRM) based on listing details and commercial comparables',
            'Provide general insight on likely Buyer types (e.g., Owner-User, Investor, 1031 Exchange Buyer), common value drivers, and investment strategies',
            'Recommend adjustments to pricing or marketing strategy if the property is not receiving sufficient interest',
            'Provide general guidance on lease structures, expense ratios, and Tenant impacts',
        ],
    ];

    protected array $businessServiceCategories = [
        'Business Marketing & Listing Promotion' => [
            'List the Business Opportunity on the local Multiple Listing Service (MLS)',
            'List the Business Opportunity on Crexi.com ',
            'List the Business Opportunity on LoopNet.com ',
            'List the Business Opportunity on BizBuySell.com ',
            'List the Business Opportunity on BizQuest.com',
            'List the Business Opportunity on BusinessesForSale.com',
            'Create a branded flyer summarizing the Business\'s key features (e.g., industry, cash flow, assets)',
            'Post the Business Opportunity on Craigslist under the "Business for Sale" category',
            'Promote the listing on Facebook in Business Buyer, Franchise, or Investor Groups',
            'Share the listing on Instagram using posts, stories, or reels',
            'Promote the listing on LinkedIn in Business Acquisition, Startup, or Investor Groups',
            'Upload a TikTok video summarizing the Business Opportunity',
            'Upload a YouTube video summarizing the Business Opportunity',
            'Launch a mass email campaign promoting the listing',
            'Distribute printed flyers or postcards in target geographic areas',
            'Launch hyperlocal or interest-based digital ad campaigns promoting the listing',
        ],
        'Listing Preparation & Confidential Marketing' => [
            'Conduct a preliminary Seller consultation to gather details about the Business\'s operations, assets, and goals',
            'Provide a business sale checklist to collect financials, licenses, lease terms, and key operational details',
            'Assist with preparing a non-confidential teaser or executive summary for marketing purposes',
            'Organize internal documentation such as profit and loss statements, balance sheets, FF&E summaries, inventory lists, and staffing overviews (as available)',
            'Refer third-party professionals such as valuation experts, accountants, or financial consultants, if requested (referrals only \u2014 no endorsement or warranty is made)',
            'Compile essential marketing materials including business overviews, location descriptions, asset lists, and financial summaries',
        ],
        'Photography, Video & Virtual Media' => [
            'Provide professional property photography',
            'Provide aerial (drone) photography (subject to FAA Part 107 compliance)',
            'Provide a video walkthrough tour',
            'Provide a 3D virtual tour',
            'Provide virtual staging (digital enhancements only; no physical staging)',
            'Provide digital photo enhancements',
            'Create a basic schematic floor plan (non-certified; for marketing purposes only)',
        ],
        'Showings & Access Coordination' => [
            'Respond to Buyer inquiries and screen for general qualifications',
            'Provide Non-Disclosure Agreement (NDA) templates for confidential showings or document access',
            'Ensure proper notice is provided if the property or business premises is occupied',
            'Install a real estate sign on the property',
            'Install a lockbox for Agent access',
            'Schedule and attend showings with prospective Buyers',
            'Coordinate showings with Buyer\'s Agents',
            'Coordinate directly with Tenant(s) or business staff to arrange access for showings',
            'Collect and relay showing feedback to the Seller',
        ],
        'Offer & Contract Management' => [
            'Present all Letters of Intent (LOIs) or formal offers to the Seller and summarize key deal terms',
            'Provide the Seller with the necessary disclosure forms required by state or local law',
            'Negotiate deal terms such as purchase price, deposit structure, contingencies, transition period, and asset allocation',
            'Coordinate revisions, counteroffers, and ongoing communication with the Buyer or their representatives',
            'Manage communication with the Buyer\'s Broker or Buyer',
            'Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties',
            'Monitor contract contingencies and organize delivery of due diligence materials such as leases, vendor contracts, tax filings, and financial statements',
            'Refer the Seller to legal counsel for formal contract drafting and execution (referrals only \u2014 no legal advice provided)',
            'Provide referrals to Business Attorneys, Escrow Officers, or Business Transfer Specialists (referrals only \u2014 no endorsement or warranty is made)',
        ],
        'Closing Coordination & Transaction Management' => [
            'Coordinate Buyer inspections, management interviews, and site visits as applicable',
            'Provide a transaction checklist and track key deadlines throughout the escrow period',
            'Coordinate with the Buyer\'s Attorney, Escrow Officer, or designated Closing Facilitator',
            'Review the Settlement Statement and coordinate corrections with relevant parties',
            'Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties',
            'Schedule and confirm the Final Walkthrough',
            'Schedule and confirm the Closing Appointment',
        ],
        'Selling Strategy & Guidance' => [
            'Provide a business market overview with insights from recent comparable listings',
            'Identify likely Buyer types (e.g., Owner-Operator, Investor, Franchisee) and discuss common deal structures (e.g., asset sale, stock sale)',
            'Provide general insight on common value drivers such as cash flow, recurring revenue, transferable licenses, and key staff retention',
            'Provide general guidance on operational transition timelines, staff notifications, lease assignments, and post-sale training periods',
            'Provide referrals to business valuation, accounting, or legal professionals (referrals only \u2014 no endorsement or warranty is made)',
        ],
    ];

    protected array $vacantLandServiceCategories = [
        'Property Marketing & Listing Promotion' => [
            'List the property in the local Multiple Listing Service (MLS)',
            'Syndicate the listing to third-party platforms (e.g., Zillow.com, Realtor.com, Trulia.com, Homes.com)',
            'List the property on LandWatch.com',
            'List the property on Land.com',
            'List the property on LandAndFarm.com',
            'Create a branded flyer highlighting lot features, zoning, and potential use',
            'Post the listing on Facebook Marketplace',
            'Post the listing on Craigslist under the "Land for Sale" category',
            'Share the listing on Nextdoor in Neighborhood or Rural Groups',
            'Promote the listing on Facebook in Land Buyers, Developers, or Homesteader Groups',
            'Share the listing on Instagram using posts, stories, or reels',
            'Promote the listing on LinkedIn in Land Acquisition or Investment Groups',
            'Upload a TikTok video summarizing the land opportunity',
            'Upload a YouTube video summarizing the land opportunity (e.g., drone tour, narrated overview)',
            'Launch a mass email campaign promoting the listing Distribute printed flyers or postcards in target geographic areas',
            'Launch hyperlocal or interest-based digital ad campaigns promoting the listing',
        ],
        'Listing Preparation & Research' => [
            'Provide a checklist to gather parcel data (e.g., APN, lot size, zoning, utilities, and access)',
            'Assist with collecting public records, flood zone data, and land use information (as available)',
            'Provide referrals to surveyors, soil testers, or land service professionals (referrals only \u2014 no endorsement or warranty is made)',
        ],
        'Photography, Video & Virtual Media' => [
            'Provide professional property photography',
            'Provide aerial (drone) photography (subject to FAA Part 107 compliance)',
            'Provide a video overview or narrated walkthrough',
            'Provide a 3D virtual tour (if applicable)',
            'Provide digital enhancements to media assets',
            'Provide a parcel map, topographical image, or plot plan (non-certified; for marketing purposes only)',
        ],
        'Showings & Access Coordination' => [
            'Install a real estate sign on the property',
            'Schedule and attend showings with prospective Buyers',
            'Coordinate showings with Buyer\'s Agents',
            'Collect and relay showing feedback to the Seller',
        ],
        'Offer & Contract Management' => [
            'Present all offers to the Seller and summarize key terms, pricing, and contingencies',
            'Provide the Seller with the necessary disclosure forms required by state or local law',
            'Negotiate price, due diligence timelines, and closing terms',
            'Draft and deliver counteroffers and manage revisions to the purchase agreement',
            'Manage communication with the Buyer\'s Agent or Buyer',
            'Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties',
            'Monitor contract contingencies, including survey, zoning verification, financing, and environmental reviews',
            'Provide referrals to Attorneys, Title Companies, Escrow Officers, or Land Use Professionals (referrals only \u2014 no endorsement or warranty is made)',
        ],
        'Closing Coordination & Transaction Management' => [
            'Coordinate surveys, site visits, or environmental access with the Buyer or Buyer\'s Agent, as applicable',
            'Coordinate with Title, Escrow, and/or Attorney to prepare for Closing',
            'Review the Settlement Statement and coordinate with all parties if corrections are needed',
            'Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties',
            'Schedule and confirm the Final Walkthrough',
            'Schedule and confirm the Closing Appointment',
        ],
        'Selling Strategy & Guidance' => [
            'Provide a Comparative Market Analysis (CMA) with pricing recommendations based on recent land sales, zoning categories, and location-based trends',
            'Provide general insight on permitted uses, utility access, parcel features, and Buyer demand in the area',
            'Recommend adjustments to pricing or marketing strategy if the land is not receiving sufficient interest',
            'Provide general guidance on Seller obligations, disclosure requirements, and listing preparation',
        ],
    ];

    public function generateSummary(SellerAgentAuctionBid $bid, ?SellerCounterTerm $acceptedCounter = null): ?AcceptedBidSummary
    {
        try {
            $listing = $bid->auction;
            if (!$listing) {
                Log::warning('[SellerAcceptedBidSummaryService] Listing not found for bid', ['bid_id' => $bid->id]);
                return null;
            }

            $seller = User::find($listing->user_id);
            $agent  = User::find($bid->user_id);

            if (!$seller || !$agent) {
                Log::warning('[SellerAcceptedBidSummaryService] Seller or agent not found', [
                    'bid_id'           => $bid->id,
                    'listing_user_id'  => $listing->user_id,
                    'bid_user_id'      => $bid->user_id,
                ]);
                return null;
            }

            $sourceData = $acceptedCounter
                ? $this->getCounterData($acceptedCounter)
                : $this->getBidData($bid);

            $html = $this->buildSummaryHtml($listing, $bid, $seller, $agent, $sourceData, $acceptedCounter);

            $summary = AcceptedBidSummary::create([
                'listing_type'        => 'seller',
                'listing_id'          => $listing->id,
                'accepted_bid_id'     => $bid->id,
                'accepted_counter_id' => $acceptedCounter ? $acceptedCounter->id : null,
                'tenant_user_id'      => $seller->id,
                'agent_user_id'       => $agent->id,
                'summary_html'        => $html,
            ]);

            return $summary;

        } catch (\Exception $e) {
            Log::error('[SellerAcceptedBidSummaryService] Failed to generate accepted bid summary', [
                'bid_id' => $bid->id,
                'error'  => $e->getMessage(),
                'trace'  => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    public function getRenderedHtml(AcceptedBidSummary $summary): string
    {
        $html = $summary->summary_html ?? '';

        if ($summary->isTenantSigned()) {
            $sellerSignedDisplay = $this->formatSignatureTimestamp($summary->tenant_signed_at, $summary->tenant_timezone);
            $html = str_replace('{{tenant_signature_name}}', e($summary->tenant_signature_name), $html);
            $html = str_replace('{{tenant_signed_at}}',      $sellerSignedDisplay,                $html);
            $html = str_replace('{{tenant_ip_address}}',     $summary->tenant_ip_address ?: 'Unavailable', $html);
        } else {
            $html = str_replace('{{tenant_signature_name}}', '—',       $html);
            $html = str_replace('{{tenant_signed_at}}',      'Pending', $html);
            $html = str_replace('{{tenant_ip_address}}',     '—',       $html);
        }

        if ($summary->isAgentSigned()) {
            $agentSignedDisplay = $this->formatSignatureTimestamp($summary->agent_signed_at, $summary->agent_timezone);
            $html = str_replace('{{agent_signature_name}}', e($summary->agent_signature_name), $html);
            $html = str_replace('{{agent_signed_at}}',      $agentSignedDisplay,               $html);
            $html = str_replace('{{agent_ip_address}}',     $summary->agent_ip_address ?: 'Unavailable', $html);
        } else {
            $html = str_replace('{{agent_signature_name}}', '—',       $html);
            $html = str_replace('{{agent_signed_at}}',      'Pending', $html);
            $html = str_replace('{{agent_ip_address}}',     '—',       $html);
        }

        return $html;
    }

    public function updateSignature(AcceptedBidSummary $summary, string $role, string $signatureName, ?string $ipAddress, ?string $timezone = null, ?string $userAgent = null): AcceptedBidSummary
    {
        if ($role === 'tenant') {
            $summary->tenant_signature_name = $signatureName;
            $summary->tenant_signed_at      = now();
            $summary->tenant_ip_address     = $ipAddress;
            $summary->tenant_timezone       = $timezone;
            $summary->tenant_user_agent     = $userAgent;
        } else {
            $summary->agent_signature_name = $signatureName;
            $summary->agent_signed_at      = now();
            $summary->agent_ip_address     = $ipAddress;
            $summary->agent_timezone       = $timezone;
            $summary->agent_user_agent     = $userAgent;
        }

        $summary->summary_html = $this->getRenderedHtml($summary);
        $summary->save();

        return $summary;
    }

    private function getBidData(SellerAgentAuctionBid $bid): array
    {
        $meta     = $bid->meta->pluck('meta_value', 'meta_key')->toArray();
        $services = $meta['services'] ?? '[]';
        $result = array_merge($meta, [
            'services'       => is_string($services) ? json_decode($services, true) ?? [] : (array) $services,
            'other_services' => json_decode($meta['other_services'] ?? '[]', true) ?? [],
        ]);

        // Strip referral_fee_percent from summaries for non-agent-created listings
        if (!optional($bid->auction)->isCreatedByAgent()) {
            unset($result['referral_fee_percent']);
        }

        return $result;
    }

    private function getCounterData(SellerCounterTerm $counter): array
    {
        $meta     = $counter->meta->pluck('meta_value', 'meta_key')->toArray();
        $services = $meta['services'] ?? '[]';
        $result = array_merge($meta, [
            'services'       => is_string($services) ? json_decode($services, true) ?? [] : (array) $services,
            'other_services' => json_decode($meta['other_services'] ?? '[]', true) ?? [],
        ]);

        // Strip referral_fee_percent from summaries for non-agent-created listings
        if (!optional(optional($counter->bid)->auction)->isCreatedByAgent()) {
            unset($result['referral_fee_percent']);
        }

        return $result;
    }

    private function buildSummaryHtml(
        SellerAgentAuction $listing,
        SellerAgentAuctionBid $bid,
        User $seller,
        User $agent,
        array $sourceData,
        ?SellerCounterTerm $counter
    ): string {
        $listingData  = $listing->get;
        $propertyType = data_get($listingData, 'property_type', 'Residential Property');

        $sellerName  = trim(($seller->first_name ?? '') . ' ' . ($seller->last_name ?? ''));
        $sellerEmail = $seller->email ?? '';
        $sellerPhone = $seller->phone_number ?? '';

        $agentName      = trim(($sourceData['first_name'] ?? $agent->first_name ?? '') . ' ' . ($sourceData['last_name'] ?? $agent->last_name ?? ''));
        $agentEmail     = $sourceData['email']      ?? $agent->email      ?? '';
        $agentPhone     = $sourceData['phone']      ?? $agent->phone_number ?? '';
        $agentBrokerage = $sourceData['brokerage']  ?? '';
        $agentLicense   = $sourceData['license_no'] ?? '';
        $agentNarId     = $sourceData['nar_id']     ?? '';

        $propertyAddress = $this->buildPropertyAddress($listingData);

        $html = $this->getHtmlTemplate();

        $html = str_replace('{{seller_name}}',  e($sellerName),  $html);
        $html = str_replace('{{seller_email}}', e($sellerEmail), $html);
        $html = str_replace('{{seller_phone}}', e($sellerPhone), $html);

        $html = str_replace('{{agent_name}}',           e($agentName),      $html);
        $html = str_replace('{{agent_email}}',          e($agentEmail),     $html);
        $html = str_replace('{{agent_phone}}',          e($agentPhone),     $html);
        $html = str_replace('{{agent_brokerage_name}}', e($agentBrokerage), $html);
        $html = str_replace('{{agent_license_number}}', e($agentLicense),   $html);
        $html = str_replace('{{agent_nar_id}}',         e($agentNarId),     $html);

        $html = str_replace('{{listing_id}}',       e($listing->listing_id ?? 'SAA-' . $listing->id), $html);
        $html = str_replace('{{property_address}}', e($propertyAddress),  $html);
        $html = str_replace('{{property_type}}',    e($propertyType),     $html);

        $acceptedDateFormatted = $this->formatAcceptedDate($bid->accepted_date ?? null);
        $html = str_replace('{{accepted_date}}', e($acceptedDateFormatted), $html);

        $counterNote = $counter ? '<p style="color:#856404; background:#fff3cd; padding:10px; border-radius:4px; margin-bottom:16px;"><strong>Note:</strong> This agreement was finalized via a counter offer.</p>' : '';
        $html = str_replace('{{counter_note}}', $counterNote, $html);

        $services      = is_array($sourceData['services']) ? $sourceData['services'] : [];
        $otherServices = is_array($sourceData['other_services']) ? $sourceData['other_services'] : [];
        $rawPhotoEnh   = $sourceData['photo_enhancements'] ?? [];
        $photoOptions  = [
            'enhancements' => is_string($rawPhotoEnh) ? (json_decode($rawPhotoEnh, true) ?? []) : (is_array($rawPhotoEnh) ? $rawPhotoEnh : []),
            'custom'       => trim((string) ($sourceData['custom_enhancement'] ?? '')),
        ];
        $servicesHtml  = $this->buildServicesHtml($services, $otherServices, $propertyType, $photoOptions);
        $html = str_replace('{{services_grouped_by_category}}', $servicesHtml, $html);

        $compensationHtml = $this->buildCompensationHtml($sourceData);
        $html = str_replace('{{broker_compensation_and_agency_terms_block}}', $compensationHtml, $html);

        $additionalDetailsHtml = $this->buildAdditionalDetailsHtml($sourceData, $listingData);
        $html = str_replace('{{additional_details_block}}', $additionalDetailsHtml, $html);

        // Leave {{tenant_signature_name}}, {{tenant_signed_at}}, {{tenant_ip_address}},
        // {{agent_signature_name}}, {{agent_signed_at}}, {{agent_ip_address}} as-is.
        // AcceptedBidSummaryService::getRenderedHtml() replaces them universally for all roles.

        return $html;
    }

    protected function buildPropertyAddress($listingData): string
    {
        $parts = [];
        $address = data_get($listingData, 'address') ?: data_get($listingData, 'street_address');
        if ($address) $parts[] = $address;
        $city  = data_get($listingData, 'city');
        $state = data_get($listingData, 'state');
        $zip   = data_get($listingData, 'zip_code') ?: data_get($listingData, 'zipcode');
        if ($city && $state) {
            $parts[] = $city . ', ' . $state . ($zip ? ' ' . $zip : '');
        } elseif ($city) {
            $parts[] = $city;
        } elseif ($state) {
            $parts[] = $state;
        }
        return implode(', ', array_filter($parts)) ?: 'N/A';
    }

    protected function buildServicesHtml(array $services, $otherServices, string $propertyType, array $photoOptions = []): string
    {
        $pt = strtolower($propertyType);

        if (str_contains($pt, 'business')) {
            $categories = $this->businessServiceCategories;
        } elseif (str_contains($pt, 'vacant') || str_contains($pt, 'land')) {
            $categories = $this->vacantLandServiceCategories;
        } elseif (str_contains($pt, 'commercial')) {
            $categories = $this->commercialServiceCategories;
        } elseif (str_contains($pt, 'income')) {
            $categories = $this->incomeServiceCategories;
        } else {
            $categories = $this->residentialServiceCategories;
        }

        if (empty($services) && empty($otherServices)) {
            return '<p><em>No services selected.</em></p>';
        }

        $normalizeStr = function ($str) {
            return str_replace(["\u{2019}", "\u{2018}", "\u{201C}", "\u{201D}"], ["'", "'", '"', '"'], $str);
        };

        $normalizedServices = array_map($normalizeStr, $services);

        $photoEnhancements = is_array($photoOptions['enhancements'] ?? null) ? $photoOptions['enhancements'] : [];
        $customEnhancement = trim((string) ($photoOptions['custom'] ?? ''));

        $html = '';
        foreach ($categories as $categoryName => $categoryServices) {
            $selectedInCategory = [];
            foreach ($categoryServices as $service) {
                if (in_array($normalizeStr($service), $normalizedServices)) {
                    $selectedInCategory[] = $service;
                }
            }
            if (!empty($selectedInCategory)) {
                $html .= '<div class="service-category mb-3">';
                $html .= '<h6 style="font-weight: bold; margin-bottom: 8px;">' . e($categoryName) . '</h6>';
                $html .= '<ul style="margin: 0; padding-left: 25px; list-style-type: disc;">';
                foreach ($selectedInCategory as $service) {
                    $html .= '<li style="margin-bottom: 4px; list-style-type: disc;">' . e($service) . '</li>';
                    if ($normalizeStr($service) === 'Provide digital photo enhancements' && !empty($photoEnhancements)) {
                        $subItems = [];
                        foreach ($photoEnhancements as $opt) {
                            $opt = trim((string) $opt);
                            if ($opt === '' || strtolower($opt) === 'other') {
                                continue;
                            }
                            $subItems[] = $opt;
                        }
                        if (in_array('Other', $photoEnhancements) && $customEnhancement !== '') {
                            $subItems[] = $customEnhancement;
                        }
                        if (!empty($subItems)) {
                            $html .= '<ul style="margin: 4px 0 4px 0; padding-left: 22px; list-style-type: circle;">';
                            foreach ($subItems as $sub) {
                                $html .= '<li style="margin-bottom: 3px; list-style-type: circle;">' . e($sub) . '</li>';
                            }
                            $html .= '</ul>';
                        }
                    }
                }
                $html .= '</ul></div>';
            }
        }

        if (!empty($otherServices)) {
            $html .= '<div class="service-category mb-3">';
            $html .= '<h6 style="font-weight: bold; margin-bottom: 8px;">Additional Services</h6>';
            $html .= '<ul style="margin: 0; padding-left: 25px; list-style-type: disc;">';
            if (is_array($otherServices)) {
                foreach ($otherServices as $service) {
                    if (!empty($service)) {
                        $html .= '<li style="margin-bottom: 4px; list-style-type: disc;">' . e($service) . '</li>';
                    }
                }
            } else {
                $html .= '<li style="margin-bottom: 4px; list-style-type: disc;">' . e($otherServices) . '</li>';
            }
            $html .= '</ul></div>';
        }

        return $html ?: '<p><em>No services selected.</em></p>';
    }

    protected function buildCompensationHtml(array $data): string
    {
        $g       = fn(string $key) => $data[$key] ?? null;
        $money   = fn($v) => $v ? '$' . number_format((float) str_replace(',', '', (string) $v), 0) : null;
        $percent = fn($v) => $v ? rtrim(rtrim(number_format((float) $v, 2), '0'), '.') . '%' : null;
        $yesNo   = fn($v) => in_array(strtolower((string) $v), ['yes', '1', 'true']) ? 'Yes' : 'No';
        $row     = fn(string $label, ?string $value): string =>
            (!empty($value) && $value !== 'N/A')
                ? '<tr>'
                  . '<td style="padding:8px;border-bottom:1px solid #eee;font-weight:bold;width:40%;">' . e($label) . '</td>'
                  . '<td style="padding:8px;border-bottom:1px solid #eee;">' . e($value) . '</td>'
                  . '</tr>'
                : '';

        $rows = '';

        // A) Commission Structure
        $commStructureRaw = str_replace('"', '', (string) ($g('commission_structure') ?? ''));
        $rows .= $row('Commission Structure', $commStructureRaw ?: null);

        // B) Buyer's Broker Compensation (when Commission Structure is not "No Compensation")
        $commStructureType = $g('commission_structure_type');
        if (!empty($commStructureType) && !str_contains(strtolower($commStructureRaw), 'no compensation')) {
            $buyerBrokerFee = null;
            if ($commStructureType === 'Flat Fee' && $g('commission_structure_type_fee_flat')) {
                $buyerBrokerFee = $money($g('commission_structure_type_fee_flat'));
            } elseif ($commStructureType === 'Percentage of the Total Purchase Price' && $g('commission_structure_type_fee_percentage')) {
                $buyerBrokerFee = $percent($g('commission_structure_type_fee_percentage')) . ' of Total Purchase Price';
            } elseif ($commStructureType === 'Flat Fee + Percentage' && ($g('commission_structure_type_fee_flat_combo') || $g('commission_structure_type_fee_percentage_combo'))) {
                $parts = array_filter([
                    $g('commission_structure_type_fee_percentage_combo') ? ($percent($g('commission_structure_type_fee_percentage_combo')) . ' of Total Purchase Price') : null,
                    $money($g('commission_structure_type_fee_flat_combo')),
                ]);
                $buyerBrokerFee = $parts ? implode(' + ', $parts) : null;
            } elseif (strtolower($commStructureType) === 'other' && $g('commission_structure_type_fee_other')) {
                $buyerBrokerFee = $g('commission_structure_type_fee_other');
            }
            $rows .= $row("Buyer's Broker Compensation", $buyerBrokerFee);
        }

        // C) Seller's Purchase Commission
        $purchaseFeeType = (string) ($g('purchase_fee_type') ?? '');
        if (!empty($purchaseFeeType)) {
            $purchaseFeeDisplay = $this->resolvePurchaseFeeDisplay($data, $purchaseFeeType);
            if ($purchaseFeeDisplay) {
                $rows .= $row("Seller's Purchase Commission", $purchaseFeeDisplay);
            }
        }

        // D) Leasing Fee (if interested in leasing)
        $interestedLeasing = $g('interested_purchase_fee_type');
        if (!empty($interestedLeasing)) {
            $rows .= $row('Interested in Offering a Lease Fee', $yesNo($interestedLeasing));
            if (strtolower((string) $interestedLeasing) === 'yes') {
                $leasingFeeDisplay = $this->resolveSellerLeasingFeeDisplay($data);
                $rows .= $row("Seller's Broker Leasing Fee", $leasingFeeDisplay);
            }
        }

        // E) Lease-Option Terms
        $interestedLeaseOption = $g('interested_lease_option_agreement');
        if (!empty($interestedLeaseOption)) {
            $rows .= $row('Interested in Offering a Lease-Option Agreement', $yesNo($interestedLeaseOption));
            if (strtolower((string) $interestedLeaseOption) === 'yes') {
                $leaseOptionDisplay = $this->resolveLeaseOptionCompDisplay($data, 'lease');
                $rows .= $row('Compensation for Creating the Lease-Option Agreement', $leaseOptionDisplay);
                $purchaseOptionDisplay = $this->resolveLeaseOptionCompDisplay($data, 'purchase');
                $rows .= $row('Compensation if Purchase Option is Exercised', $purchaseOptionDisplay);
            }
        }

        // F) Legal Terms
        $protectionPeriod = $g('protection_period');
        if (!empty($protectionPeriod)) {
            $rows .= $row('Protection Period Timeframe', $protectionPeriod . ' Days');
        }

        $agencyTimeframe = (string) ($g('agency_agreement_timeframe') ?? '');
        if (!empty($agencyTimeframe)) {
            if (strtolower($agencyTimeframe) === 'other') {
                $agencyTimeframe = (string) ($g('agency_agreement_custom') ?? $agencyTimeframe);
            }
            $rows .= $row('Seller Agency Agreement Timeframe', $agencyTimeframe);
        }

        $earlyTermination = $g('early_termination_fee_option');
        if (!empty($earlyTermination)) {
            $rows .= $row('Early Termination Fee', $yesNo($earlyTermination));
            if (strtolower((string) $earlyTermination) === 'yes' && $g('early_termination_fee_amount')) {
                $rows .= $row('Early Termination Fee Amount', $money($g('early_termination_fee_amount')));
            }
        }

        $retainerFee = $g('retainer_fee_option');
        if (!empty($retainerFee)) {
            $rows .= $row('Retainer Fee', $yesNo($retainerFee));
            if (strtolower((string) $retainerFee) === 'yes') {
                if ($g('retainer_fee_amount')) {
                    $rows .= $row('Retainer Fee Amount', $money($g('retainer_fee_amount')));
                }
                if (!empty($g('retainer_fee_application'))) {
                    $rows .= $row('Retainer Fee Application', (string) $g('retainer_fee_application'));
                }
            }
        }

        // G) Brokerage Relationship
        $brokerageRelationship = $g('brokerage_relationship');
        if (!empty($brokerageRelationship)) {
            $rows .= $row('Acceptable Brokerage Relationship', (string) $brokerageRelationship);
        }

        // H) Additional Terms (agent's broker notes, never additional_details which is listing owner's text)
        $additionalTerms = $g('additional_details_broker') ?? $g('additional_terms') ?? null;
        $rows .= $row('Additional Terms', $additionalTerms);

        // I) Referral Fee (%) — only on agent-created listings
        $referralFee = $g('referral_fee_percent');
        if (!empty($referralFee)) {
            $rows .= $row('Referral Fee (%) (Agent-to-Agent)', $referralFee . '%');
        }

        if (empty($rows)) {
            return '<p><em>No compensation terms specified.</em></p>';
        }

        return '<table style="width: 100%; border-collapse: collapse;">' . $rows . '</table>';
    }

    protected function resolveSellerLeasingFeeDisplay(array $data): ?string
    {
        $g       = fn(string $key) => $data[$key] ?? null;
        $money   = fn($v) => $v ? '$' . number_format((float) str_replace(',', '', (string) $v), 0) : null;
        $percent = fn($v) => $v ? rtrim(rtrim(number_format((float) $v, 2), '0'), '.') . '%' : null;

        $type = (string) ($g('seller_leasing_fee_type') ?? '');
        if (empty($type)) return null;

        if ($type === 'Flat Fee' && $g('seller_leasing_gross_purchase_fee_flat_amount')) {
            return $money($g('seller_leasing_gross_purchase_fee_flat_amount'));
        }
        if ($type === 'Percentage of the Gross Lease Value' && $g('seller_leasing_gross')) {
            return $percent($g('seller_leasing_gross')) . ' of the Gross Lease Value';
        }
        if ($type === 'Percentage of the Rent Due Each Rental Period' && $g('seller_leasing_gross_rental')) {
            return $percent($g('seller_leasing_gross_rental')) . ' of the Rent Due Each Rental Period';
        }
        if ($type === "Percentage of the First Month's Rent" && $g('seller_leasing_gross_month_rent')) {
            return $percent($g('seller_leasing_gross_month_rent')) . " of the First Month's Rent";
        }
        if ($type === "Percentage of Month's Rent" && $g('seller_leasing_gross_month_rent')) {
            $display = $percent($g('seller_leasing_gross_month_rent')) . " of Month's Rent";
            $months  = $g('seller_leasing_gross_no_of_months');
            if (!empty($months) && $months !== 'null') {
                $display .= ' x ' . intval($months) . ' Months';
            }
            return $display;
        }
        if ($type === 'Percentage of Net Aggregate Rent' && $g('seller_leasing_gross_other')) {
            return $percent($g('seller_leasing_gross_other')) . ' of Net Aggregate Rent';
        }
        if ($type === 'Percentage of Gross Rent') {
            $val = $g('seller_leasing_gross_percentage') ?? $g('seller_leasing_gross_ross_percentage_rent');
            return $val ? ($percent($val) . ' of Gross Rent') : null;
        }
        if ($type === 'Flat Fee + Percentage of the Gross Lease Value') {
            $parts = array_filter([
                $g('seller_leasing_gross_flat_combo') ? $money($g('seller_leasing_gross_flat_combo')) : null,
                $g('seller_leasing_gross_percentage_combo') ? ($percent($g('seller_leasing_gross_percentage_combo')) . ' of Gross Lease Value') : null,
            ]);
            return $parts ? implode(' + ', $parts) : null;
        }
        if ($type === 'Flat Fee + Percentage of the Net Aggregate Rent') {
            $parts = array_filter([
                $g('seller_leasing_gross_flat_net_combo') ? $money($g('seller_leasing_gross_flat_net_combo')) : null,
                $g('seller_leasing_gross_percentage_net_combo') ? ($percent($g('seller_leasing_gross_percentage_net_combo')) . ' of Net Aggregate Rent') : null,
            ]);
            return $parts ? implode(' + ', $parts) : null;
        }
        if (strtolower($type) === 'other' && $g('seller_leasing_gross_purchase_fee_other')) {
            return $g('seller_leasing_gross_purchase_fee_other');
        }
        return $type ?: null;
    }

    protected function resolveLeaseOptionCompDisplay(array $data, string $which): ?string
    {
        $g       = fn(string $key) => $data[$key] ?? null;
        $money   = fn($v) => $v ? '$' . number_format((float) str_replace(',', '', (string) $v), 0) : null;
        $percent = fn($v) => $v ? rtrim(rtrim(number_format((float) $v, 2), '0'), '.') . '%' : null;

        $valueKey = $which === 'lease' ? 'lease_value' : 'purchase_value';
        $typeKey  = $which === 'lease' ? 'lease_type'  : 'purchase_type';

        $value = $g($valueKey);
        $type  = (string) ($g($typeKey) ?? 'flat');

        if (empty($value) || $value === 'null') return null;

        if (in_array($type, ['%', 'percent']) || str_contains((string) $value, '%')) {
            return str_replace('%', '', (string) $value) . '% of Total Purchase Price';
        }
        return $money($value);
    }

    private function makeRow(string $label, string $value): string
    {
        return '<tr>'
            . '<td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold; width: 40%;">' . e($label) . '</td>'
            . '<td style="padding: 8px; border-bottom: 1px solid #eee;">' . e($value) . '</td>'
            . '</tr>';
    }

    private function resolvePurchaseFeeDisplay(array $data, string $type): ?string
    {
        $money   = fn($v) => $v ? '$' . number_format((float) str_replace(',', '', (string) $v), 0) : null;
        $percent = fn($v) => $v ? rtrim(rtrim(number_format((float) $v, 2), '0'), '.') . '%' : null;

        if ($type === 'flat') {
            return $money($data['purchase_fee_flat'] ?? null);
        }
        if ($type === 'percentage') {
            $pct = $data['purchase_fee_percentage'] ?? null;
            return $pct ? ($percent($pct) . ' of Total Purchase Price') : null;
        }
        if ($type === 'combo') {
            $parts = array_filter([
                isset($data['purchase_fee_percentage_combo']) ? ($percent($data['purchase_fee_percentage_combo']) . ' of Total Purchase Price') : null,
                $money($data['purchase_fee_flat_combo'] ?? null),
            ]);
            return $parts ? implode(' + ', $parts) : null;
        }
        if ($type === 'other') {
            return $data['purchase_fee_other'] ?? null;
        }
        return null;
    }

    public function regenerateSummaryHtml(AcceptedBidSummary $summary): bool
    {
        try {
            $bid = SellerAgentAuctionBid::find($summary->accepted_bid_id);
            if (!$bid) return false;

            $listing = $bid->auction;
            if (!$listing) return false;

            $seller = User::find($listing->user_id);
            $agent  = User::find($bid->user_id);
            if (!$seller || !$agent) return false;

            $acceptedCounter = $summary->accepted_counter_id
                ? SellerCounterTerm::find($summary->accepted_counter_id)
                : null;

            $sourceData = $acceptedCounter
                ? $this->getCounterData($acceptedCounter)
                : $this->getBidData($bid);

            $html = $this->buildSummaryHtml($listing, $bid, $seller, $agent, $sourceData, $acceptedCounter);

            $summary->summary_html = $html;

            // Invalidate any cached PDF so the next download regenerates from the updated HTML.
            if ($summary->summary_pdf_path) {
                $oldPdfPath = storage_path('app/' . $summary->summary_pdf_path);
                if (file_exists($oldPdfPath)) {
                    @unlink($oldPdfPath);
                }
                $summary->summary_pdf_path = null;
            }

            $summary->save();

            return true;
        } catch (\Exception $e) {
            Log::error('[SellerAcceptedBidSummaryService] Failed to regenerate summary html', [
                'summary_id' => $summary->id,
                'error'      => $e->getMessage(),
            ]);
            return false;
        }
    }

    protected function buildAdditionalDetailsHtml(array $sourceData, $listingData): string
    {
        $parts = [];

        $sellerDetails = data_get($listingData, 'additional_details');
        if (!empty($sellerDetails)) {
            $parts[] = '<div class="mb-2"><strong>Seller\'s Additional Details:</strong><br>' . nl2br(e($sellerDetails)) . '</div>';
        }

        $agentDetails = $sourceData['additional_details_broker'] ?? $sourceData['additional_details'] ?? null;
        if (!empty($agentDetails)) {
            $parts[] = '<div class="mb-2"><strong>Agent\'s Additional Details:</strong><br>' . nl2br(e($agentDetails)) . '</div>';
        }

        if (empty($parts)) {
            return '<p><em>No additional details provided.</em></p>';
        }

        return implode('', $parts);
    }

    protected function formatAcceptedDate($acceptedDate): string
    {
        if (empty($acceptedDate)) {
            return now()->setTimezone('America/New_York')->format('F j, Y \a\t g:i A') . ' ET';
        }
        try {
            $date = $acceptedDate instanceof \Carbon\Carbon ? $acceptedDate : \Carbon\Carbon::parse($acceptedDate);
            $easternDate = $date->copy()->setTimezone('America/New_York');
            return $easternDate->format('F j, Y') . ' at ' . $easternDate->format('g:i A') . ' ET';
        } catch (\Exception $e) {
            return (string) $acceptedDate;
        }
    }

    protected function formatSignatureTimestamp($datetime, ?string $timezone): string
    {
        if (empty($datetime)) return 'Pending';
        try {
            $dt = $datetime instanceof \Carbon\Carbon ? $datetime : \Carbon\Carbon::parse($datetime);
            $displayTz = (!empty($timezone) && in_array($timezone, timezone_identifiers_list())) ? $timezone : 'America/New_York';
            $local = $dt->copy()->setTimezone($displayTz);
            $abbr  = $this->getTimezoneAbbreviation($displayTz);
            return $local->format('F j, Y') . ' at ' . $local->format('g:i A') . ' ' . $abbr;
        } catch (\Exception $e) {
            return (string) $datetime;
        }
    }

    protected function getTimezoneAbbreviation(string $timezone): string
    {
        $map = [
            'America/New_York'    => 'ET',
            'America/Chicago'     => 'CT',
            'America/Denver'      => 'MT',
            'America/Phoenix'     => 'MST',
            'America/Los_Angeles' => 'PT',
            'America/Anchorage'   => 'AKT',
            'Pacific/Honolulu'    => 'HST',
        ];
        return $map[$timezone] ?? date_create('now', timezone_open($timezone))->format('T');
    }

    protected function getHtmlTemplate(): string
    {
        return <<<'HTML'
<div class="accepted-bid-summary" style="font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px;">
    <div style="text-align: center; margin-bottom: 30px;">
        <h1 style="color: #333; margin-bottom: 10px;">Accepted Bid Summary</h1>
        <p style="color: #666;">Listing ID: {{listing_id}}</p>
        <p style="color: #666;">Accepted Date: {{accepted_date}}</p>
    </div>

    {{counter_note}}

    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
        <h2 style="color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px;">1. Parties</h2>
        <div style="display: flex; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 250px; margin-bottom: 15px;">
                <h4 style="color: #007bff;">Seller</h4>
                <p><strong>Name:</strong> {{seller_name}}</p>
                <p><strong>Email:</strong> {{seller_email}}</p>
                <p><strong>Phone:</strong> {{seller_phone}}</p>
            </div>
            <div style="flex: 1; min-width: 250px; margin-bottom: 15px;">
                <h4 style="color: #007bff;">Agent</h4>
                <p><strong>Name:</strong> {{agent_name}}</p>
                <p><strong>Email:</strong> {{agent_email}}</p>
                <p><strong>Phone:</strong> {{agent_phone}}</p>
                <p><strong>Brokerage:</strong> {{agent_brokerage_name}}</p>
                <p><strong>License #:</strong> {{agent_license_number}}</p>
                <p><strong>NAR/MLS ID:</strong> {{agent_nar_id}}</p>
            </div>
        </div>
    </div>

    <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 20px;">
        <h2 style="color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px;">2. Listing Details</h2>
        <p><strong>Property Type:</strong> {{property_type}}</p>
        <p><strong>Property Address:</strong> {{property_address}}</p>
    </div>

    <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 20px;">
        <h2 style="color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px;">3. Services</h2>
        {{services_grouped_by_category}}
    </div>

    <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 20px;">
        <h2 style="color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px;">4. Agreed Compensation &amp; Agency Terms</h2>
        {{broker_compensation_and_agency_terms_block}}
    </div>

    <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 20px;">
        <h2 style="color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px;">5. Additional Details</h2>
        {{additional_details_block}}
    </div>

    <div style="background: #fff3cd; padding: 20px; border: 1px solid #ffc107; border-radius: 8px; margin-bottom: 20px;">
        <h2 style="color: #856404; border-bottom: 2px solid #ffc107; padding-bottom: 10px;">6. Important Notice</h2>
        <p style="color: #856404;">This Accepted Bid Summary is a record of the terms agreed upon through the Bid Your Offer platform. It does not constitute a legally binding contract. Both parties are encouraged to formalize their agreement through appropriate legal documentation and consult with legal professionals as needed.</p>
    </div>

    <div style="background: #e7f3ff; padding: 20px; border: 1px solid #007bff; border-radius: 8px; margin-bottom: 20px;">
        <h2 style="color: #0056b3; border-bottom: 2px solid #007bff; padding-bottom: 10px;">7. Platform Referral Disclosure</h2>
        <p style="color: #0056b3;">The platform may receive a referral fee from the hired Agent or their brokerage as part of the agent's compensation. The Seller does not pay any fee to the platform.</p>
    </div>

    <div style="background: #f8f9fa; padding: 20px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 20px;">
        <h2 style="color: #333; border-bottom: 2px solid #28a745; padding-bottom: 10px;">8. Signature Acknowledgement</h2>
        <div style="display: flex; flex-wrap: wrap; gap: 20px;">
            <div style="flex: 1; min-width: 250px; padding: 15px; border: 1px solid #ddd; border-radius: 5px;">
                <h4 style="color: #28a745;">Seller Acknowledgement</h4>
                <p><strong>Signature:</strong> {{tenant_signature_name}}</p>
                <p><strong>Date/Time:</strong> {{tenant_signed_at}}</p>
                <p><strong>IP Address:</strong> {{tenant_ip_address}}</p>
            </div>
            <div style="flex: 1; min-width: 250px; padding: 15px; border: 1px solid #ddd; border-radius: 5px;">
                <h4 style="color: #28a745;">Agent Acknowledgement</h4>
                <p><strong>Signature:</strong> {{agent_signature_name}}</p>
                <p><strong>Date/Time:</strong> {{agent_signed_at}}</p>
                <p><strong>IP Address:</strong> {{agent_ip_address}}</p>
            </div>
        </div>
    </div>
</div>
HTML;
    }
}
