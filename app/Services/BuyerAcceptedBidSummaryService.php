<?php

namespace App\Services;

use App\Models\AcceptedBidSummary;
use App\Models\BuyerAgentAuction;
use App\Models\BuyerAgentAuctionBid;
use App\Models\BuyerCounterTerm;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class BuyerAcceptedBidSummaryService
{
    protected array $residentialServiceCategories = [
        'Buyer Criteria Marketing & Promotion' => [
            'Create a branded flyer summarizing the Buyer\'s purchase criteria',
            'Post the Buyer\'s purchase criteria on Craigslist under the "Real Estate Wanted" section',
            'Share the Buyer\'s purchase criteria on Nextdoor in Neighborhood or Community Groups',
            'Promote the Buyer\'s purchase criteria on Facebook in Real Estate or Housing Groups',
            'Share the Buyer\'s purchase criteria on Instagram using posts, stories, or reels',
            'Promote the Buyer\'s purchase criteria on LinkedIn in Real Estate or Housing Groups',
            'Upload a TikTok video summarizing the Buyer\'s purchase criteria',
            'Upload a YouTube video summarizing the Buyer\'s purchase criteria',
            'Launch a mass email campaign promoting the Buyer\'s purchase criteria',
            'Distribute branded postcards or flyers in the Buyer\'s preferred neighborhoods',
            'Launch hyperlocal digital ads targeting the Buyer\'s preferred purchase areas',
        ],
        'Property Search, Alerts & Matching' => [
            'Send email alerts with new listings from the MLS that match the Buyer\'s purchase criteria',
            'Search for off-market, pre-market, distressed, withdrawn, canceled, or expired properties that meet the Buyer\'s purchase criteria',
            'Communicate with the Seller\'s Agent or Seller to confirm availability, purchase terms, and showing instructions',
            'Evaluate properties with the Buyer and provide insights on pricing, terms, potential, and overall fit',
        ],
        'Property Showings & Virtual Tours' => [
            'Schedule and attend property showings with the Buyer',
            'Coordinate or conduct virtual showings via live video or pre-recorded walkthroughs',
            'Preview properties on behalf of the Buyer upon request',
            'Provide factual observations on property layout and condition',
        ],
        'Offer & Contract Coordination' => [
            'Draft and submit offers using state-approved purchase forms',
            'Provide the Buyer with the necessary disclosure forms required by state or local law',
            'Draft and deliver counteroffers and manage revisions to the purchase agreement',
            'Negotiate price, deposits, and contingencies with the Seller\'s Agent or Seller (as permitted under the agency agreement)',
            'Manage communications with the Seller\'s Agent or Seller',
            'Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties',
            'Assist with inspection-related negotiations and Buyer requests for repairs',
            'Monitor contract milestones, contingency periods, and financing deadlines',
            'Provide referrals to Attorneys, Title Companies, Escrow Professionals, or Lenders (referrals only \u2014 no endorsement or warranty is made)',
        ],
        'Closing Coordination & Transaction Management' => [
            'Coordinate inspections, appraisals, and lease audits (if applicable)',
            'Coordinate with the Lender, Title, Escrow, and/or Attorney to prepare for Closing',
            'Review the Settlement Statement for accuracy and coordinate with relevant parties if corrections are needed (no legal or financial advice provided)',
            'Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties',
            'Schedule and confirm the Final Walkthrough',
            'Schedule and confirm the Closing Appointment',
        ],
        'Buying Strategy & Guidance' => [
            'Provide a Comparative Market Analysis (CMA) with pricing recommendations based on comparable sales, neighborhood trends, and current market conditions (for informational purposes only \u2014 not a formal appraisal)',
            'Answer general questions about financing, loan options, property taxes, insurance, and escrow timelines (non-legal guidance)',
            'Provide factual information about neighborhood characteristics, school zones, crime data, and local amenities using third-party sources (no personal opinions or steering)',
            'Offer general guidance on inspection expectations, common repair requests, and contingency planning during the offer process (non-legal advice)',
        ],
    ];

    protected array $incomeServiceCategories = [
        'Buyer Criteria Marketing & Promotion' => [
            'Create a branded flyer summarizing the Buyer\'s purchase criteria',
            'Post the Buyer\'s purchase criteria on Craigslist under the "Real Estate Wanted" section',
            'Share the Buyer\'s purchase criteria on Nextdoor in Neighborhood or Community Groups',
            'Promote the Buyer\'s purchase criteria on Facebook in Real Estate Investor or Multifamily Groups',
            'Share the Buyer\'s purchase criteria on Instagram using posts, stories, or reels',
            'Promote the Buyer\'s purchase criteria on LinkedIn in Investment or Property Management Groups',
            'Upload a TikTok video summarizing the Buyer\'s purchase criteria',
            'Upload a YouTube video summarizing the Buyer\'s purchase criteria',
            'Launch a mass email campaign promoting the Buyer\'s purchase criteria',
            'Distribute branded postcards or flyers in the Buyer\'s preferred neighborhoods',
            'Launch hyperlocal digital ads targeting the Buyer\'s preferred purchase areas',
        ],
        'Property Search, Alerts & Matching' => [
            'Send email alerts with new listings that match the Buyer\'s purchase criteria',
            'Search for off-market, pre-market, distressed, withdrawn, canceled, or expired properties that meet the Buyer\'s purchase criteria',
            'Communicate with the Seller\'s Agent or Sellers to confirm pricing, rental income, expenses, and showing instructions',
            'Evaluate investment properties with the Buyer and provide insights on cash flow, cap rates, and value-add potential',
        ],
        'Property Showings & Virtual Tours' => [
            'Schedule and attend property showings with the Buyer',
            'Coordinate or conduct virtual showings via live video or pre-recorded walkthroughs',
            'Preview properties on behalf of the Buyer upon request',
            'Provide observations on tenant occupancy, building condition, and operating expenses',
        ],
        'Offer & Contract Management' => [
            'Draft and submit offers using state-approved purchase forms',
            'Provide the Buyer with the necessary disclosure forms required by state or local law',
            'Draft and deliver counteroffers and manage revisions to the purchase agreement',
            'Negotiate price, deposits, and contingencies with the Seller\'s Agent or Seller',
            'Manage communication with the Seller\'s Agent or Seller',
            'Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties',
            'Assist with inspection-related negotiations and Buyer requests for repairs',
            'Monitor contract milestones, contingency periods, and financing deadlines',
            'Provide referrals to Attorneys, Title Companies, Escrow Professionals, Lenders, or 1031 Exchange Intermediaries (referrals only \u2014 no endorsement or warranty is made)',
        ],
        'Closing Coordination & Transaction Management' => [
            'Review and provide due diligence documents such as lease agreements, estoppel certificates, rent rolls, utility summaries, and operating expense breakdowns (as available)',
            'Coordinate with the Seller\'s Agent, Buyer\'s Lender, Title, Escrow, and/or Attorney to prepare for Closing',
            'Review the Settlement Statement for accuracy and coordinate with relevant parties if corrections are needed (no legal or financial advice provided)',
            'Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties',
            'Schedule and confirm the Final Walkthrough',
            'Schedule and confirm the Closing Appointment',
        ],
        'Buying Strategy & Guidance' => [
            'Provide a Comparative Market Analysis (CMA) with pricing recommendations, rental comps, and Cap Rate estimates (for informational purposes only \u2014 not a formal appraisal)',
            'Answer general questions about financing options, rent control, property taxes, and Landlord responsibilities',
            'Provide factual information on rental demand, turnover rates, and sub market conditions using third-party sources',
            'Offer general guidance on due diligence steps, lease audits, and estoppel reviews (non-legal advice)',
        ],
    ];

    protected array $commercialServiceCategories = [
        'Buyer Criteria Marketing & Promotion' => [
            'Create a branded flyer summarizing the Buyer\'s purchase criteria',
            'Post the Buyer\'s criteria on Craigslist under "Real Estate Wanted \u2013 Commercial"',
            'Promote the Buyer\'s criteria on Facebook in Commercial Real Estate or Investment Groups',
            'Share the Buyer\'s criteria on Instagram using posts, stories, or reels',
            'Promote the Buyer\'s criteria on LinkedIn in Commercial or Investment Groups',
            'Upload a TikTok video summarizing the Buyer\'s purchase criteria',
            'Upload a YouTube video summarizing the Buyer\'s purchase criteria',
            'Launch a mass email campaign promoting the Buyer\'s purchase criteria',
            'Distribute branded postcards or flyers in the Buyer\'s preferred purchase areas',
            'Launch hyperlocal or interest-based digital ad campaigns targeting desired commercial property types',
        ],
        'Property Search, Alerts & Matching' => [
            'Send property alerts that match the Buyer\'s purchase criteria from the MLS or commercial listing platforms',
            'Search for off-market, pre-market, distressed, withdrawn, canceled, or expired listings that meet the Buyer\'s criteria',
            'Communicate with the Seller\'s Agent or Seller to confirm availability, purchase terms, and showing instructions',
            'Analyze building class, property zoning, income potential, and redevelopment opportunities',
        ],
        'Property Showings & Virtual Tours' => [
            'Schedule and attend property showings with the Buyer',
            'Coordinate or conduct virtual showings via live video or recorded walkthroughs',
            'Preview properties on behalf of the Buyer upon request',
            'Provide insights on layout, access, visibility, tenant mix, and surrounding infrastructure',
        ],
        'Offer & Contract Management' => [
            'Draft and submit offers using state-approved purchase agreements or Letters of Intent (LOIs)',
            'Provide the Buyer with the necessary disclosure forms required by state or local law',
            'Draft and deliver counteroffers and manage revisions to the purchase agreement',
            'Negotiate price, deposit structure, timelines, and contingencies with the Seller or Seller\'s Agent',
            'Manage communication with the Seller\'s Agent or Seller',
            'Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties',
            'Assist with due diligence negotiations, including repair requests or credits',
            'Monitor contract contingencies, including financing, estoppel review, lease audits, and environmental reports',
            'Provide referrals to Attorneys, Title Companies, Escrow Officers, Commercial Lenders, or 1031 Exchange Intermediaries (referrals only \u2014 no endorsement or warranty is made)',
        ],
        'Closing Coordination & Transaction Management' => [
            'Coordinate inspections, appraisals, environmental assessments, and estoppel certificate collection as needed',
            'Review and request due diligence documentation such as lease agreements, estoppel certificates, rent rolls, utility summaries, and operating expense breakdowns (as available)',
            'Coordinate with the Lender, Title Company, Escrow Officer, and/or Attorney to prepare for Closing',
            'Review the Settlement Statement for accuracy and coordinate with all parties if corrections are needed (no legal or financial advice provided)',
            'Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties',
            'Schedule and confirm the Final Walkthrough',
            'Schedule and confirm the Closing Appointment',
        ],
        'Buying Strategy & Guidance' => [
            'Provide a Comparative Market Analysis (CMA) with recent sales comps, lease comps, and an estimated value range (for informational purposes only \u2014 not a formal appraisal)',
            'Answer general questions about zoning regulations, permitted uses, and rental income potential',
            'Provide factual data on traffic counts, commercial market trends, and area demographics using third-party sources (no personal opinions or steering)',
            'Offer general guidance on lease types, contingency timelines, due diligence, and environmental risks (non-legal advice only)',
        ],
    ];

    protected array $businessServiceCategories = [
        'Buyer Criteria Marketing & Promotion' => [
            'Create a branded flyer summarizing the Buyer\'s purchase criteria',
            'Post the Buyer\'s purchase criteria on Craigslist under "Business for Sale" or "Real Estate Wanted \u2013 Commercial"',
            'Promote the Buyer\'s purchase criteria on Facebook in Business Opportunity or Franchise Groups',
            'Share the Buyer\'s purchase criteria on Instagram using posts, stories, or reels',
            'Promote the Buyer\'s purchase criteria on LinkedIn in Business, Commercial, or Startup Groups',
            'Upload a TikTok video summarizing the Buyer\'s purchase criteria',
            'Upload a YouTube video summarizing the Buyer\'s purchase criteria',
            'Launch a mass email campaign promoting the Buyer\'s purchase criteria',
            'Distribute branded postcards or flyers in the Buyer\'s preferred neighborhoods',
            'Launch hyperlocal digital ads targeting the Buyer\'s preferred purchase areas',
        ],
        'Business Search, Alerts & Matching' => [
            'Send alerts for businesses that match the Buyer\'s acquisition criteria from MLS, BizBuySell, or other listing platforms',
            'Search for off-market, pre-market, distressed, or recently closed businesses that meet the Buyer\'s criteria',
            'Communicate with the Seller\'s Broker or Seller to confirm pricing, lease terms, licensing status, and showing availability',
            'Analyze financials, lease assignments, business licensing requirements, and overall market positioning',
        ],
        'Property Showings & Virtual Tours' => [
            'Schedule and attend property or business showings with the Buyer',
            'Coordinate or conduct virtual showings via live video or pre-recorded walkthroughs',
            'Preview properties or business locations on behalf of the Buyer upon request',
            'Provide insights on foot traffic, customer base, operational setup, competitive advantages, and location dynamics',
        ],
        'Offer & Contract Management' => [
            'Draft and submit offers using appropriate business purchase or asset sale forms',
            'Provide the Buyer with required disclosures, financial summaries, and documentation made available by the Seller',
            'Negotiate terms such as purchase price, deposit structure, inventory inclusions, non-compete agreements, and contingencies',
            'Draft and deliver counteroffers and manage revisions to the purchase agreement',
            'Manage communication with the Seller\'s Broker or Seller',
            'Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties',
            'Assist with due diligence coordination, Buyer-requested repairs, and adjustment negotiations',
            'Monitor contingency periods, financing milestones, and deal approval timelines',
            'Provide referrals to Business Attorneys, CPAs, Escrow Officers, or Lenders (referrals only \u2014 no endorsement or warranty is made)',
        ],
        'Closing Coordination & Transaction Management' => [
            'Coordinate inspections, licensing verifications, lease assignments, and inventory counts',
            'Coordinate with Lenders, Attorneys, Escrow Officers, Title Companies, CPAs, and other involved parties to prepare for Closing',
            'Review the Settlement Statement or Closing Worksheet for accuracy and coordinate with all parties if corrections are needed (no legal or financial advice provided)',
            'Confirm delivery of final executed documents, wire instructions, and business transition materials',
            'Schedule and confirm the Final Walkthrough',
            'Schedule and confirm the Closing Appointment',
        ],
        'Buying Strategy & Guidance' => [
            'Provide a Comparative Market Review based on similar business sales, financial performance, and industry benchmarks (for informational purposes only \u2014 not a formal appraisal or valuation)',
            'Answer general questions about licensing, zoning, SBA financing, registration steps, and transition timing (non-legal guidance)',
            'Offer general guidance on due diligence preparation, key documents to review, and red flags during the acquisition process (non-legal advice only)',
        ],
    ];

    protected array $vacantLandServiceCategories = [
        'Buyer Criteria Marketing & Promotion' => [
            'Create a branded flyer summarizing the Buyer\'s purchase criteria',
            'Post the Buyer\'s criteria on Craigslist under "Real Estate Wanted \u2013 Land"',
            'Share the Buyer\'s criteria on Nextdoor in Neighborhood or Rural Groups',
            'Promote the Buyer\'s criteria on Facebook in Land Buyers, Developers, or Homesteader Groups',
            'Share the Buyer\'s criteria on Instagram using posts, stories, or reels',
            'Promote the Buyer\'s criteria on LinkedIn in Land Acquisition or Investment Groups',
            'Upload a TikTok video summarizing the Buyer\'s purchase criteria',
            'Upload a YouTube video summarizing the Buyer\'s purchase criteria',
            'Launch a mass email campaign promoting the Buyer\'s purchase criteria',
            'Distribute branded postcards or flyers in the Buyer\'s preferred neighborhoods',
            'Launch hyperlocal digital ads targeting the Buyer\'s preferred purchase areas',
        ],
        'Property Search, Alerts & Matching' => [
            'Send property alerts for land listings that match the Buyer\'s goals from MLS and land-specific platforms',
            'Search for off-market, pre-market, distressed, withdrawn, canceled, or expired properties that meet the Buyer\'s purchase criteria',
            'Communicate with the Seller\'s Agent or Seller to confirm zoning, access, utilities, and pricing',
            'Assess development feasibility, land use restrictions, or agricultural potential (non-legal advice)',
        ],
        'Property Showings & Virtual Tours' => [
            'Schedule and attend land visits with the Buyer',
            'Coordinate or conduct virtual walkthroughs using maps, aerials, and site photos',
            'Preview parcels on behalf of the Buyer upon request',
            'Provide observations on topography, road frontage, and surrounding land uses',
        ],
        'Offer & Contract Management' => [
            'Draft and submit offers using state-approved purchase forms',
            'Provide the Buyer with required state or local disclosure forms',
            'Draft and deliver counteroffers and manage revisions to the purchase agreement',
            'Negotiate price, deposits, and contingencies (as permitted under the agency agreement)',
            'Manage communication with the Seller\'s Agent or Seller',
            'Assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed documents to all parties',
            'Assist with due diligence coordination, including survey review, soil testing, zoning checks, and permit verification (non-legal guidance only)',
            'Monitor contract milestones, contingency deadlines, and financing timelines',
            'Provide referrals to Attorneys, Title Companies, Escrow Officers, Surveyors, or Land Use Consultants (referrals only \u2014 no endorsement or warranty is made)',
        ],
        'Closing Coordination & Transaction Management' => [
            'Coordinate surveys, appraisals, inspections, and environmental assessments',
            'Coordinate with the Lender, Title Company, Escrow Officer, and/or Attorney to prepare for Closing',
            'Review the Settlement Statement for accuracy and coordinate with all parties if corrections are needed (no legal or financial advice provided)',
            'Confirm delivery of final executed documents, wire instructions, and Closing paperwork to all relevant parties',
            'Schedule and confirm the Final Walkthrough',
            'Schedule and confirm the Closing Appointment',
        ],
        'Buying Strategy & Guidance' => [
            'Provide a Comparative Market Analysis (CMA) based on recent land sales, acreage comps, and price-per-acre benchmarks (for informational purposes only \u2014 not a formal appraisal)',
            'Answer general questions about zoning, utilities, development potential, and environmental constraints (non-legal guidance only)',
            'Provide factual data on flood zones, wetlands, and land use maps using third-party sources (no legal or engineering advice)',
            'Offer general guidance on feasibility timelines, inspection steps, and rural financing considerations (non-legal advice only)',
        ],
    ];

    public function generateSummary(BuyerAgentAuctionBid $bid, ?BuyerCounterTerm $acceptedCounter = null): ?AcceptedBidSummary
    {
        try {
            $listing = $bid->auction;
            if (!$listing) {
                Log::warning('[BuyerAcceptedBidSummaryService] Listing not found for bid', ['bid_id' => $bid->id]);
                return null;
            }

            $buyer = User::find($listing->user_id);
            $agent = User::find($bid->user_id);

            if (!$buyer || !$agent) {
                Log::warning('[BuyerAcceptedBidSummaryService] Buyer or agent not found', [
                    'bid_id'           => $bid->id,
                    'listing_user_id'  => $listing->user_id,
                    'bid_user_id'      => $bid->user_id,
                ]);
                return null;
            }

            $sourceData = $acceptedCounter
                ? $this->getCounterData($acceptedCounter)
                : $this->getBidData($bid);

            $html = $this->buildSummaryHtml($listing, $bid, $buyer, $agent, $sourceData, $acceptedCounter);

            $summary = AcceptedBidSummary::create([
                'listing_id'          => $listing->id,
                'accepted_bid_id'     => $bid->id,
                'accepted_counter_id' => $acceptedCounter ? $acceptedCounter->id : null,
                'tenant_user_id'      => $buyer->id,
                'agent_user_id'       => $agent->id,
                'summary_html'        => $html,
            ]);

            return $summary;

        } catch (\Exception $e) {
            Log::error('[BuyerAcceptedBidSummaryService] Failed to generate accepted bid summary', [
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
            $buyerSignedDisplay = $this->formatSignatureTimestamp($summary->tenant_signed_at, $summary->tenant_timezone);
            $html = str_replace('{{tenant_signature_name}}', e($summary->tenant_signature_name), $html);
            $html = str_replace('{{tenant_signed_at}}',      $buyerSignedDisplay,                $html);
            $html = str_replace('{{tenant_ip_address}}',     $summary->tenant_ip_address ?: 'Unavailable', $html);
        } else {
            $html = str_replace('{{tenant_signature_name}}', '&#8212;',  $html);
            $html = str_replace('{{tenant_signed_at}}',      'Pending',  $html);
            $html = str_replace('{{tenant_ip_address}}',     '&#8212;',  $html);
        }

        if ($summary->isAgentSigned()) {
            $agentSignedDisplay = $this->formatSignatureTimestamp($summary->agent_signed_at, $summary->agent_timezone);
            $html = str_replace('{{agent_signature_name}}', e($summary->agent_signature_name), $html);
            $html = str_replace('{{agent_signed_at}}',      $agentSignedDisplay,               $html);
            $html = str_replace('{{agent_ip_address}}',     $summary->agent_ip_address ?: 'Unavailable', $html);
        } else {
            $html = str_replace('{{agent_signature_name}}', '&#8212;',  $html);
            $html = str_replace('{{agent_signed_at}}',      'Pending',  $html);
            $html = str_replace('{{agent_ip_address}}',     '&#8212;',  $html);
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

    private function getBidData(BuyerAgentAuctionBid $bid): array
    {
        $meta     = $bid->meta->pluck('meta_value', 'meta_key')->toArray();
        $services = $meta['services'] ?? '[]';
        return array_merge($meta, [
            'services'       => is_string($services) ? json_decode($services, true) ?? [] : (array) $services,
            'other_services' => json_decode($meta['other_services'] ?? '[]', true) ?? [],
        ]);
    }

    private function getCounterData(BuyerCounterTerm $counter): array
    {
        $meta     = $counter->meta->pluck('meta_value', 'meta_key')->toArray();
        $services = $meta['services'] ?? '[]';
        return array_merge($meta, [
            'services'       => is_string($services) ? json_decode($services, true) ?? [] : (array) $services,
            'other_services' => json_decode($meta['other_services'] ?? '[]', true) ?? [],
        ]);
    }

    private function buildSummaryHtml(
        BuyerAgentAuction $listing,
        BuyerAgentAuctionBid $bid,
        User $buyer,
        User $agent,
        array $sourceData,
        ?BuyerCounterTerm $counter
    ): string {
        $listingMeta  = $listing->meta->pluck('meta_value', 'meta_key')->toArray();
        $propertyType = $listingMeta['property_type'] ?? 'Residential Property';

        $buyerName  = trim(($buyer->first_name ?? '') . ' ' . ($buyer->last_name ?? ''));
        $buyerEmail = $buyer->email ?? '';
        $buyerPhone = $buyer->phone_number ?? '';

        $agentName      = trim(($sourceData['first_name'] ?? $agent->first_name ?? '') . ' ' . ($sourceData['last_name'] ?? $agent->last_name ?? ''));
        $agentEmail     = $sourceData['email']      ?? $agent->email       ?? '';
        $agentPhone     = $sourceData['phone']      ?? $agent->phone_number ?? '';
        $agentBrokerage = $sourceData['brokerage']  ?? '';
        $agentLicense   = $sourceData['license_no'] ?? '';
        $agentNarId     = $sourceData['nar_id']     ?? '';

        $locationParts = array_filter([
            $listingMeta['city']     ?? null,
            $listingMeta['state']    ?? null,
            $listingMeta['zip_code'] ?? null,
        ]);
        $location = implode(', ', $locationParts) ?: 'N/A';

        $html = $this->getHtmlTemplate();

        $html = str_replace('{{buyer_name}}',  e($buyerName),  $html);
        $html = str_replace('{{buyer_email}}', e($buyerEmail), $html);
        $html = str_replace('{{buyer_phone}}', e($buyerPhone), $html);

        $html = str_replace('{{agent_name}}',           e($agentName),      $html);
        $html = str_replace('{{agent_email}}',          e($agentEmail),     $html);
        $html = str_replace('{{agent_phone}}',          e($agentPhone),     $html);
        $html = str_replace('{{agent_brokerage_name}}', e($agentBrokerage), $html);
        $html = str_replace('{{agent_license_number}}', e($agentLicense),   $html);
        $html = str_replace('{{agent_nar_id}}',         e($agentNarId),     $html);

        $html = str_replace('{{listing_id}}',     e($listing->listing_id ?? 'BAA-' . $listing->id), $html);
        $html = str_replace('{{buyer_location}}', e($location),            $html);
        $html = str_replace('{{property_type}}',  e($propertyType),        $html);

        $acceptedDateFormatted = $this->formatAcceptedDate($bid->accepted_date ?? null);
        $html = str_replace('{{accepted_date}}', e($acceptedDateFormatted), $html);

        $counterNote = $counter ? '<p style="color:#856404; background:#fff3cd; padding:10px; border-radius:4px; margin-bottom:16px;"><strong>Note:</strong> This agreement was finalized via a counter offer.</p>' : '';
        $html = str_replace('{{counter_note}}', $counterNote, $html);

        $services      = is_array($sourceData['services']) ? $sourceData['services'] : [];
        $otherServices = is_array($sourceData['other_services']) ? $sourceData['other_services'] : [];
        $servicesHtml  = $this->buildServicesHtml($services, $otherServices, $propertyType);
        $html = str_replace('{{services_grouped_by_category}}', $servicesHtml, $html);

        $compensationHtml = $this->buildCompensationHtml($sourceData);
        $html = str_replace('{{broker_compensation_and_agency_terms_block}}', $compensationHtml, $html);

        $additionalDetailsHtml = $this->buildAdditionalDetailsHtml($sourceData, $listingMeta);
        $html = str_replace('{{additional_details_block}}', $additionalDetailsHtml, $html);

        $html = str_replace('{{tenant_signature_name}}', '&#8212;',  $html);
        $html = str_replace('{{tenant_signed_at}}',      'Pending',  $html);
        $html = str_replace('{{tenant_ip_address}}',     '&#8212;',  $html);
        $html = str_replace('{{agent_signature_name}}',  '&#8212;',  $html);
        $html = str_replace('{{agent_signed_at}}',       'Pending',  $html);
        $html = str_replace('{{agent_ip_address}}',      '&#8212;',  $html);

        return $html;
    }

    protected function buildServicesHtml(array $services, $otherServices, string $propertyType): string
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

        $normalizeStr = fn($s) => str_replace(
            ["\u{2019}", "\u{2018}", "\u{201C}", "\u{201D}", "\u{2013}", "\u{2014}"],
            ["'",         "'",        '"',        '"',        '-',        '-'],
            $s
        );

        $normalizedServices = array_map($normalizeStr, $services);

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
                $html .= '<ul style="margin: 0; padding-left: 25px;">';
                foreach ($selectedInCategory as $service) {
                    $html .= '<li style="margin-bottom: 4px;">' . e($service) . '</li>';
                }
                $html .= '</ul></div>';
            }
        }

        if (!empty($otherServices)) {
            $html .= '<div class="service-category mb-3">';
            $html .= '<h6 style="font-weight: bold; margin-bottom: 8px;">Additional Services</h6>';
            $html .= '<ul style="margin: 0; padding-left: 25px;">';
            if (is_array($otherServices)) {
                foreach ($otherServices as $service) {
                    if (!empty($service)) {
                        $html .= '<li style="margin-bottom: 4px;">' . e($service) . '</li>';
                    }
                }
            } else {
                $html .= '<li style="margin-bottom: 4px;">' . e($otherServices) . '</li>';
            }
            $html .= '</ul></div>';
        }

        return $html ?: '<p><em>No services selected.</em></p>';
    }

    protected function buildCompensationHtml(array $data): string
    {
        $rows = '';

        $commissionStructure = $data['commission_structure'] ?? null;
        if (!empty($commissionStructure)) {
            $rows .= $this->makeRow('Commission Structure', $commissionStructure);
        }

        $purchaseFeeType = $data['purchase_fee_type'] ?? null;
        if (!empty($purchaseFeeType)) {
            $rows .= $this->makeRow('Purchase Commission Type', $this->formatFeeType($purchaseFeeType));
            $feeDisplay = $this->resolvePurchaseFeeDisplay($data, $purchaseFeeType);
            if (!empty($feeDisplay)) {
                $rows .= $this->makeRow('Purchase Commission Amount', $feeDisplay);
            }
        }

        $leaseFeeType = $data['lease_fee_type'] ?? null;
        if (!empty($leaseFeeType)) {
            $rows .= $this->makeRow('Lease Commission Type', $this->formatFeeType($leaseFeeType));
            $leaseFeeDisplay = $this->resolveLeaseFeeDisplay($data, $leaseFeeType);
            if (!empty($leaseFeeDisplay)) {
                $rows .= $this->makeRow('Lease Commission Amount', $leaseFeeDisplay);
            }
        }

        $agencyTimeframe = $data['agency_agreement_timeframe'] ?? null;
        if (!empty($agencyTimeframe) && strtolower($agencyTimeframe) === 'other') {
            $agencyTimeframe = $data['agency_agreement_custom'] ?? $agencyTimeframe;
        }
        if (!empty($agencyTimeframe)) {
            $rows .= $this->makeRow('Agency Agreement Timeframe', $agencyTimeframe);
        }

        $protectionPeriod = $data['protection_period'] ?? null;
        if (!empty($protectionPeriod)) {
            $rows .= $this->makeRow('Protection Period Timeframe', $protectionPeriod);
        }

        $brokerageRelationship = $data['brokerage_relationship'] ?? null;
        if (!empty($brokerageRelationship)) {
            $rows .= $this->makeRow('Acceptable Brokerage Relationship', $brokerageRelationship);
        }

        $earlyTermination = $data['early_termination_fee_option'] ?? null;
        if (!empty($earlyTermination)) {
            $rows .= $this->makeRow('Early Termination Fee', $earlyTermination);
            if (strtolower($earlyTermination) === 'yes') {
                $earlyAmount = $data['early_termination_fee_amount'] ?? null;
                if (!empty($earlyAmount)) {
                    $rows .= $this->makeRow('Early Termination Fee Amount', '$' . number_format((float) str_replace(',', '', (string) $earlyAmount), 0));
                }
            }
        }

        $retainerFee = $data['retainer_fee_option'] ?? null;
        if (!empty($retainerFee)) {
            $rows .= $this->makeRow('Retainer Fee', $retainerFee);
            if (strtolower($retainerFee) === 'yes') {
                $retainerAmount = $data['retainer_fee_amount'] ?? null;
                if (!empty($retainerAmount)) {
                    $rows .= $this->makeRow('Retainer Fee Amount', '$' . number_format((float) str_replace(',', '', (string) $retainerAmount), 0));
                }
                $retainerApplication = $data['retainer_fee_application'] ?? null;
                if (!empty($retainerApplication)) {
                    $rows .= $this->makeRow('Retainer Fee Application', $retainerApplication);
                }
            }
        }

        $additionalTerms = $data['additional_details'] ?? null;
        if (!empty($additionalTerms)) {
            $rows .= $this->makeRow('Additional Terms', $additionalTerms);
        }

        if (empty($rows)) {
            return '<p><em>No compensation terms specified.</em></p>';
        }

        return '<table style="width: 100%; border-collapse: collapse;">' . $rows . '</table>';
    }

    private function makeRow(string $label, string $value): string
    {
        return '<tr>'
            . '<td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold; width: 40%;">' . e($label) . '</td>'
            . '<td style="padding: 8px; border-bottom: 1px solid #eee;">' . e($value) . '</td>'
            . '</tr>';
    }

    private function formatFeeType(string $type): string
    {
        return match ($type) {
            'flat'       => 'Flat Fee',
            'percentage' => 'Percentage',
            'combo'      => 'Combo (Flat + Percentage)',
            'other'      => 'Other',
            default      => ucfirst($type),
        };
    }

    private function resolvePurchaseFeeDisplay(array $data, string $type): ?string
    {
        $money   = fn($v) => $v ? '$' . number_format((float) str_replace(',', '', (string) $v), 0) : null;
        $percent = fn($v) => $v ? rtrim(rtrim(number_format((float) $v, 2), '0'), '.') . '%' : null;

        if ($type === 'flat')       return $money($data['purchase_fee_flat'] ?? null);
        if ($type === 'percentage') { $pct = $data['purchase_fee_percentage'] ?? null; return $pct ? ($percent($pct) . ' of Total Purchase Price') : null; }
        if ($type === 'combo') {
            $parts = array_filter([
                isset($data['purchase_fee_percentage_combo']) ? ($percent($data['purchase_fee_percentage_combo']) . ' of Total Purchase Price') : null,
                $money($data['purchase_fee_flat_combo'] ?? null),
            ]);
            return $parts ? implode(' + ', $parts) : null;
        }
        if ($type === 'other')      return $data['purchase_fee_other'] ?? null;
        return null;
    }

    private function resolveLeaseFeeDisplay(array $data, string $type): ?string
    {
        $money   = fn($v) => $v ? '$' . number_format((float) str_replace(',', '', (string) $v), 0) : null;
        $percent = fn($v) => $v ? rtrim(rtrim(number_format((float) $v, 2), '0'), '.') . '%' : null;

        if ($type === 'flat')       return $money($data['lease_fee_flat'] ?? null);
        if ($type === 'percentage') { $pct = $data['lease_fee_percentage'] ?? null; return $pct ? ($percent($pct) . ' of Total Lease Value') : null; }
        if ($type === 'combo') {
            $parts = array_filter([
                isset($data['lease_fee_percentage_combo']) ? ($percent($data['lease_fee_percentage_combo']) . ' of Total Lease Value') : null,
                $money($data['lease_fee_flat_combo'] ?? null),
            ]);
            return $parts ? implode(' + ', $parts) : null;
        }
        if ($type === 'other')      return $data['lease_fee_other'] ?? null;
        return null;
    }

    protected function buildAdditionalDetailsHtml(array $sourceData, array $listingMeta): string
    {
        $parts = [];

        $buyerDetails = $listingMeta['additional_details'] ?? null;
        if (!empty($buyerDetails)) {
            $parts[] = '<div class="mb-2"><strong>Buyer\'s Additional Details:</strong><br>' . nl2br(e($buyerDetails)) . '</div>';
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
                <h4 style="color: #007bff;">Buyer</h4>
                <p><strong>Name:</strong> {{buyer_name}}</p>
                <p><strong>Email:</strong> {{buyer_email}}</p>
                <p><strong>Phone:</strong> {{buyer_phone}}</p>
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
        <p><strong>Buyer's Preferred Location:</strong> {{buyer_location}}</p>
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
        <p style="color: #0056b3;">The platform may receive a referral fee from the hired Agent or their brokerage as part of the agent's compensation. The Buyer does not pay any fee to the platform.</p>
    </div>

    <div style="background: #f8f9fa; padding: 20px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 20px;">
        <h2 style="color: #333; border-bottom: 2px solid #28a745; padding-bottom: 10px;">8. Signature Acknowledgement</h2>
        <div style="display: flex; flex-wrap: wrap; gap: 20px;">
            <div style="flex: 1; min-width: 250px; padding: 15px; border: 1px solid #ddd; border-radius: 5px;">
                <h4 style="color: #28a745;">Buyer Acknowledgement</h4>
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
