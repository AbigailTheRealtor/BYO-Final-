<?php

namespace App\Services;

use App\Models\AcceptedBidSummary;
use App\Models\TenantAgentAuction;
use App\Models\TenantAgentAuctionBid;
use App\Models\TenantCounterBidding;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class AcceptedBidSummaryService
{
    protected $residentialServiceCategories = [
        'Tenant Criteria Marketing & Promotion' => [
            'Create a branded flyer summarizing the Tenant's rental criteria',
            'Post the Tenant's rental criteria on Craigslist under the "Real Estate Wanted" section',
            'Share the Tenant's rental criteria on Nextdoor in Neighborhood or Community Groups',
            'Promote the Tenant's rental criteria on Facebook in Rental or Housing Groups',
            'Share the Tenant's rental criteria on Instagram using posts, stories, or reels',
            'Promote the Tenant's rental criteria on LinkedIn in Real Estate or Housing Groups',
            'Upload a TikTok video summarizing the Tenant's rental criteria',
            'Upload a YouTube video summarizing the Tenant's rental criteria',
            'Launch a mass email campaign promoting the Tenant's rental criteria',
            'Distribute branded postcards or flyers in the Tenant's preferred neighborhoods',
            'Launch hyperlocal digital ads targeting the Tenant's preferred rental areas'
        ],
        'Property Search, Alerts & Matching' => [
            'Send email alerts with new listings from the MLS that match the Tenant's rental criteria',
            'Search for off-market, pre-market, withdrawn, canceled, or expired properties that meet the Tenant's rental criteria',
            'Communicate with the Landlord's Agent, Landlord, or Property Manager to confirm availability, lease terms, and showing instructions',
            'Evaluate properties with the Tenant and provide insights on pricing, lease terms, and overall fit'
        ],
        'Property Showings & Virtual Tours' => [
            'Schedule and attend property showings with the Tenant',
            'Coordinate or conduct virtual showings via live video or pre-recorded walkthroughs',
            'Preview properties on behalf of the Tenant upon request',
            'Provide factual observations on property layout and condition'
        ],
        'Tenant Application Support' => [
            'Provide the Tenant with application instructions or links to an online rental application platform',
            'Gather and organize required supporting documents (e.g., identification, income verification, reference letters)',
            'Submit complete and organized application packages to the Landlord's Agent, Landlord, or Property Manager for review',
            'Answer questions about the application process, screening timelines, and required documentation'
        ],
        'Lease Preparation & Execution' => [
            'Review lease offers and assist the Tenant in preparing questions or requested changes',
            'Coordinate lease negotiation with the Landlord's Agent, Landlord, or Property Manager',
            'Assist with completing required lease disclosures and reviewing key lease terms',
            'Assist with in-person or electronic lease signing, including e-signature setup and secure delivery of executed lease documents, addenda, and disclosures to all parties',
        ],
        'Move-In Support & Coordination' => [
            'Coordinate move-in date and key handoff logistics with the Landlord's Agent, Landlord or Property Manager',
            'Confirm completion of any agreed-upon pre-move-in cleaning or repairs',
            'Provide a utility setup checklist and local provider resources',
            'Share a move-in checklist for documentation and property condition review',
            'Confirm required move-in payments and assist the Tenant with tracking amounts due, deadlines, and accepted payment methods'
        ],
        'Leasing Strategy & Guidance' => [
            'Provide a Rental Market Analysis (RMA) with pricing insights based on comparable rentals, neighborhood trends, and current market conditions',
            'Advise on lease types and structures (e.g., month-to-month, annual, furnished, lease-option)',
            'Provide general guidance on Tenant rights and Landlord responsibilities under state law',
            'Provide general guidance on lease clauses, payment terms, and renewal options',
        ],
    ];

    protected $commercialServiceCategories = [
        'Tenant Criteria Marketing & Promotion' => [
            'Create a branded flyer summarizing the Tenant's leasing criteria',
            'Post the Tenant's leasing criteria on Craigslist under the "Office/Commercial" or "Retail" section',
            'Promote the Tenant's leasing criteria on Facebook in Commercial Leasing or Business Groups',
            'Share the Tenant's leasing criteria on Instagram using posts, stories, or reels',
            'Promote the Tenant's leasing criteria on LinkedIn in Professional, Real Estate, or Commercial Investment Groups',
            'Upload a TikTok video summarizing the Tenant's leasing criteria',
            'Upload a YouTube video summarizing the Tenant's leasing criteria',
            'Launch a mass email campaign promoting the Tenant's leasing criteria',
            'Distribute branded postcards or flyers in the Tenant's preferred neighborhoods',
            'Launch hyperlocal digital ads targeting the Tenant's preferred leasing areas'
        ],
        'Property Search, Alerts & Matching' => [
            'Send listing alerts from commercial platforms (e.g., LoopNet, Crexi, CoStar, or local MLS) that match the Tenant's leasing criteria',
            'Search for off-market, pre-market, withdrawn, canceled, or expired properties that meet the Tenant's rental criteria',
            'Communicate with the Landlord's Agent, Landlord, or Property Manager to confirm availability, lease terms, and showing instructions',
            'Evaluate properties for layout efficiency, building specs, logistics, zoning fit, and operational alignment'
        ],
        'Property Showings & Virtual Tours' => [
            'Schedule and attend property tours with the Tenant',
            'Coordinate or conduct virtual showings via live video or pre-recorded walkthroughs',
            'Preview properties on behalf of the Tenant upon request',
            'Provide factual notes on layout, access, parking, visibility, and other operational considerations'
        ],
        'Tenant Application Support' => [
            'Provide the Tenant with application instructions or links to online platforms',
            'Gather and organize required supporting documents (e.g., business licenses, financials, references)',
            'Submit complete and organized application packages to the Landlord's Agent, Landlord, or Property Manager'
        ],
        'Lease Preparation, LOI & Execution' => [
            'Draft or assist with preparing a Letter of Intent (LOI) summarizing the Tenant's business needs and proposed terms',
            'Assist with negotiating rent, CAM, lease term, TI allowance, exclusivity clauses, renewal options, and other provisions (as permitted under the agency agreement)',
            'Coordinate with the Landlord's Agent, Landlord or Property Manager to finalize lease terms',
            'Review lease drafts and coordinate revisions through appropriate channels',
            'Assist with in-person or electronic lease signing, including e-signature setup and secure delivery of executed lease documents, addenda, and disclosures to all parties',
            'Track required deposits, rent commencement, and key lease dates to ensure move-in readiness'
        ],
        'Move-In Support & Coordination' => [
            'Coordinate move-in date and key handoff logistics with the Landlord, Landlord's Agent, or Property Manager',
            'Confirm completion of any agreed-upon pre-move-in repairs, cleaning, or buildout',
            'Provide a utility setup checklist and local provider resources',
            'Share a move-in checklist for documentation and property condition review',
            'Confirm required move-in payments and assist the Tenant with tracking amounts due, deadlines, and accepted payment methods'
        ],
        'Leasing Strategy & Guidance' => [
            'Provide a Comparative Lease Market Analysis (CLMA) with pricing insights, comps, and vacancy trends',
            'Advise on lease types and structures (e.g., NNN, Modified Gross, Full Service) with general explanations of differences',
            'Provide general guidance on Tenant rights and Landlord responsibilities under commercial leasing law',
            'Provide general guidance on lease clauses, escalation terms, and space usage considerations',
        ],
    ];

    public function generateSummary(TenantAgentAuctionBid $bid, ?TenantCounterBidding $acceptedCounter = null): ?AcceptedBidSummary
    {
        try {
            $listing = $bid->auction;
            $tenant = $listing->user;
            $agent = $bid->user;

            $sourceData = $acceptedCounter ? $this->getCounterData($acceptedCounter) : $this->getBidData($bid);

            $html = $this->buildSummaryHtml($listing, $bid, $tenant, $agent, $sourceData, $acceptedCounter);

            $summary = AcceptedBidSummary::create([
                'listing_id' => $listing->id,
                'accepted_bid_id' => $bid->id,
                'accepted_counter_id' => $acceptedCounter ? $acceptedCounter->id : null,
                'tenant_user_id' => $tenant->id,
                'agent_user_id' => $agent->id,
                'summary_html' => $html,
            ]);

            return $summary;
        } catch (\Exception $e) {
            Log::error('Failed to generate accepted bid summary', [
                'bid_id' => $bid->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    protected function getBidData(TenantAgentAuctionBid $bid): array
    {
        $bidData = $bid->get;
        return [
            'services' => $this->parseServices(data_get($bidData, 'services', [])),
            'other_services' => data_get($bidData, 'other_services', ''),
            'broker_fee_type' => data_get($bidData, 'broker_fee_type'),
            'broker_fee_amount' => data_get($bidData, 'broker_fee_amount'),
            'broker_fee_timing' => data_get($bidData, 'broker_fee_timing'),
            'broker_fee_days' => data_get($bidData, 'broker_fee_days'),
            'purchase_fee_type' => data_get($bidData, 'purchase_fee_type'),
            'purchase_fee_amount' => data_get($bidData, 'purchase_fee_amount'),
            'lease_option_fee_type' => data_get($bidData, 'lease_option_fee_type'),
            'lease_option_fee_amount' => data_get($bidData, 'lease_option_fee_amount'),
            'protection_period' => data_get($bidData, 'protection_period'),
            'early_termination_fee' => data_get($bidData, 'early_termination_fee'),
            'early_termination_fee_amount' => data_get($bidData, 'early_termination_fee_amount'),
            'retainer_fee' => data_get($bidData, 'retainer_fee'),
            'retainer_fee_amount' => data_get($bidData, 'retainer_fee_amount'),
            'agency_agreement_timeframe' => data_get($bidData, 'agency_agreement_timeframe'),
            'brokerage_relationship' => data_get($bidData, 'brokerage_relationship'),
            'additional_terms' => data_get($bidData, 'additional_terms'),
            'additional_details' => data_get($bidData, 'additional_details'),
            'agent_name' => data_get($bidData, 'name'),
            'agent_email' => data_get($bidData, 'email'),
            'agent_phone' => data_get($bidData, 'phone'),
            'agent_brokerage' => data_get($bidData, 'brokerage'),
            'agent_license' => data_get($bidData, 'license_no') ?: data_get($bidData, 'agent_license'),
            'agent_nar_id' => data_get($bidData, 'mls_id'),
        ];
    }

    protected function getCounterData(TenantCounterBidding $counter): array
    {
        $counterData = $counter->get;
        return [
            'services' => $this->parseServices(data_get($counterData, 'services', [])),
            'other_services' => data_get($counterData, 'other_services', ''),
            'broker_fee_type' => data_get($counterData, 'broker_fee_type'),
            'broker_fee_amount' => data_get($counterData, 'broker_fee_amount'),
            'broker_fee_timing' => data_get($counterData, 'broker_fee_timing'),
            'broker_fee_days' => data_get($counterData, 'broker_fee_days'),
            'purchase_fee_type' => data_get($counterData, 'purchase_fee_type'),
            'purchase_fee_amount' => data_get($counterData, 'purchase_fee_amount'),
            'lease_option_fee_type' => data_get($counterData, 'lease_option_fee_type'),
            'lease_option_fee_amount' => data_get($counterData, 'lease_option_fee_amount'),
            'protection_period' => data_get($counterData, 'protection_period'),
            'early_termination_fee' => data_get($counterData, 'early_termination_fee'),
            'early_termination_fee_amount' => data_get($counterData, 'early_termination_fee_amount'),
            'retainer_fee' => data_get($counterData, 'retainer_fee'),
            'retainer_fee_amount' => data_get($counterData, 'retainer_fee_amount'),
            'agency_agreement_timeframe' => data_get($counterData, 'agency_agreement_timeframe'),
            'brokerage_relationship' => data_get($counterData, 'brokerage_relationship'),
            'additional_terms' => data_get($counterData, 'additional_terms'),
            'additional_details' => data_get($counterData, 'additional_details'),
            'agent_name' => null,
            'agent_email' => null,
            'agent_phone' => null,
            'agent_brokerage' => null,
            'agent_license' => null,
            'agent_nar_id' => null,
        ];
    }

    protected function parseServices($services): array
    {
        if (is_string($services)) {
            $decoded = json_decode($services, true);
            return is_array($decoded) ? $decoded : [];
        }
        return is_array($services) ? $services : [];
    }

    protected function buildSummaryHtml(
        TenantAgentAuction $listing,
        TenantAgentAuctionBid $bid,
        User $tenant,
        User $agent,
        array $sourceData,
        ?TenantCounterBidding $acceptedCounter
    ): string {
        $listingData = $listing->get;
        $bidData = $bid->get;
        $propertyType = data_get($listingData, 'property_type', 'Residential Property');

        $tenantName = trim(($tenant->first_name ?? '') . ' ' . ($tenant->last_name ?? ''));
        $tenantEmail = $tenant->email ?? '';
        $tenantPhone = $tenant->phone_number ?? '';

        $agentName = $sourceData['agent_name'] ?: trim(($agent->first_name ?? '') . ' ' . ($agent->last_name ?? ''));
        $agentEmail = $sourceData['agent_email'] ?: ($agent->email ?? '');
        $agentPhone = $sourceData['agent_phone'] ?: ($agent->phone_number ?? '');
        $agentBrokerage = $sourceData['agent_brokerage'] ?: data_get($bidData, 'brokerage', '');
        $agentLicense = $sourceData['agent_license'] ?: data_get($bidData, 'license_no', '');
        $agentNarId = $sourceData['agent_nar_id'] ?: data_get($bidData, 'mls_id', '');

        $targetAreas = $this->buildTargetAreas($listingData);

        $html = $this->getHtmlTemplate();

        $html = str_replace('{{tenant_name}}', e($tenantName), $html);
        $html = str_replace('{{tenant_email}}', e($tenantEmail), $html);
        $html = str_replace('{{tenant_phone}}', e($tenantPhone), $html);

        $html = str_replace('{{agent_name}}', e($agentName), $html);
        $html = str_replace('{{agent_email}}', e($agentEmail), $html);
        $html = str_replace('{{agent_phone}}', e($agentPhone), $html);
        $html = str_replace('{{agent_brokerage_name}}', e($agentBrokerage), $html);
        $html = str_replace('{{agent_license_number}}', e($agentLicense), $html);
        $html = str_replace('{{agent_nar_id}}', e($agentNarId), $html);

        $html = str_replace('{{listing_id}}', e($listing->listing_id ?? 'TAA-' . $listing->id), $html);
        $html = str_replace('{{target_areas}}', e($targetAreas), $html);
        $html = str_replace('{{property_type}}', e($propertyType), $html);
        $html = str_replace('{{accepted_date}}', e($bid->accepted_date ?? now()->format('Y-m-d H:i:s')), $html);

        $servicesHtml = $this->buildServicesHtml($sourceData['services'], $sourceData['other_services'], $propertyType);
        $html = str_replace('{{services_grouped_by_category}}', $servicesHtml, $html);

        $compensationHtml = $this->buildCompensationHtml($sourceData);
        $html = str_replace('{{broker_compensation_and_agency_terms_block}}', $compensationHtml, $html);

        $additionalDetailsHtml = $this->buildAdditionalDetailsHtml($sourceData, $listingData);
        $html = str_replace('{{additional_details_block}}', $additionalDetailsHtml, $html);

        $html = str_replace('{{tenant_signature_name}}', '', $html);
        $html = str_replace('{{tenant_signed_at}}', '', $html);
        $html = str_replace('{{tenant_ip_address}}', '', $html);
        $html = str_replace('{{agent_signature_name}}', '', $html);
        $html = str_replace('{{agent_signed_at}}', '', $html);
        $html = str_replace('{{agent_ip_address}}', '', $html);

        return $html;
    }

    protected function buildTargetAreas($listingData): string
    {
        $parts = [];

        $cities = data_get($listingData, 'acceptable_cities');
        if ($cities) {
            if (is_array($cities)) {
                $parts[] = implode(', ', $cities);
            } else {
                $parts[] = $cities;
            }
        }

        $counties = data_get($listingData, 'acceptable_counties');
        if ($counties) {
            if (is_array($counties)) {
                $parts[] = implode(', ', $counties);
            } else {
                $parts[] = $counties;
            }
        }

        $zips = data_get($listingData, 'acceptable_zip_codes');
        if ($zips) {
            if (is_array($zips)) {
                $parts[] = implode(', ', $zips);
            } else {
                $parts[] = $zips;
            }
        }

        $state = data_get($listingData, 'state');
        if ($state) {
            $parts[] = $state;
        }

        return implode(', ', array_filter($parts));
    }

    protected function buildServicesHtml(array $services, $otherServices, string $propertyType): string
    {
        if (empty($services) && empty($otherServices)) {
            return '<p><em>No services selected.</em></p>';
        }

        $categories = $propertyType === 'Commercial Property' 
            ? $this->commercialServiceCategories 
            : $this->residentialServiceCategories;

        $normalizeStr = function($str) {
            return str_replace([''', ''', '"', '"'], ["'", "'", '"', '"'], $str);
        };

        $normalizedServices = array_map($normalizeStr, $services);

        $html = '';
        foreach ($categories as $categoryName => $categoryServices) {
            $selectedInCategory = [];
            foreach ($categoryServices as $service) {
                $normalizedService = $normalizeStr($service);
                if (in_array($normalizedService, $normalizedServices)) {
                    $selectedInCategory[] = $service;
                }
            }

            if (!empty($selectedInCategory)) {
                $html .= '<div class="service-category mb-3">';
                $html .= '<h6 style="font-weight: bold; margin-bottom: 8px;">' . e($categoryName) . '</h6>';
                $html .= '<ul style="margin: 0; padding-left: 20px;">';
                foreach ($selectedInCategory as $service) {
                    $html .= '<li>' . e($service) . '</li>';
                }
                $html .= '</ul></div>';
            }
        }

        if (!empty($otherServices)) {
            $html .= '<div class="service-category mb-3">';
            $html .= '<h6 style="font-weight: bold; margin-bottom: 8px;">Additional Services</h6>';
            $html .= '<ul style="margin: 0; padding-left: 20px;">';
            
            if (is_array($otherServices)) {
                foreach ($otherServices as $service) {
                    if (!empty($service)) {
                        $html .= '<li>' . e($service) . '</li>';
                    }
                }
            } else {
                $html .= '<li>' . e($otherServices) . '</li>';
            }
            $html .= '</ul></div>';
        }

        return $html ?: '<p><em>No services selected.</em></p>';
    }

    protected function buildCompensationHtml(array $data): string
    {
        $html = '<table style="width: 100%; border-collapse: collapse;">';

        $fields = [
            'broker_fee_type' => 'Tenant\'s Broker Commission Structure',
            'broker_fee_amount' => 'Tenant\'s Broker Commission Fee',
            'broker_fee_timing' => 'Payment Timing for Broker Fees',
            'broker_fee_days' => 'Calendar Days to Pay',
            'purchase_fee_type' => 'Purchase Fee Type',
            'purchase_fee_amount' => 'Purchase Fee Amount',
            'lease_option_fee_type' => 'Lease-Option Fee Type',
            'lease_option_fee_amount' => 'Lease-Option Fee Amount',
            'protection_period' => 'Protection Period Timeframe',
            'early_termination_fee' => 'Early Termination Fee',
            'early_termination_fee_amount' => 'Early Termination Fee Amount',
            'retainer_fee' => 'Retainer Fee',
            'retainer_fee_amount' => 'Retainer Fee Amount',
            'agency_agreement_timeframe' => 'Tenant Agency Agreement Timeframe',
            'brokerage_relationship' => 'Brokerage Relationship Type',
            'additional_terms' => 'Additional Terms',
        ];

        $hasContent = false;
        foreach ($fields as $key => $label) {
            $value = $data[$key] ?? null;
            if (!empty($value) && $value !== 'N/A') {
                $hasContent = true;
                $displayValue = is_array($value) ? implode(', ', $value) : $value;
                $html .= '<tr>';
                $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold; width: 40%;">' . e($label) . '</td>';
                $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">' . e($displayValue) . '</td>';
                $html .= '</tr>';
            }
        }

        $html .= '</table>';

        if (!$hasContent) {
            return '<p><em>No broker compensation terms specified.</em></p>';
        }

        return $html;
    }

    protected function buildAdditionalDetailsHtml(array $sourceData, $listingData): string
    {
        $parts = [];

        $tenantDetails = data_get($listingData, 'additional_details');
        if (!empty($tenantDetails)) {
            $parts[] = '<div class="mb-2"><strong>Tenant\'s Additional Details:</strong><br>' . nl2br(e($tenantDetails)) . '</div>';
        }

        $agentDetails = $sourceData['additional_details'] ?? null;
        if (!empty($agentDetails)) {
            $parts[] = '<div class="mb-2"><strong>Agent\'s Additional Details:</strong><br>' . nl2br(e($agentDetails)) . '</div>';
        }

        if (empty($parts)) {
            return '<p><em>No additional details provided.</em></p>';
        }

        return implode('', $parts);
    }

    protected function getHtmlTemplate(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accepted Bid Summary</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        h1 {
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
        h2 {
            color: #2c3e50;
            margin-top: 30px;
            border-bottom: 1px solid #bdc3c7;
            padding-bottom: 5px;
        }
        .section {
            margin-bottom: 25px;
        }
        .party-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .party-info h3 {
            margin-top: 0;
            color: #2c3e50;
        }
        .info-row {
            margin-bottom: 5px;
        }
        .info-label {
            font-weight: bold;
            display: inline-block;
            min-width: 180px;
        }
        .notice {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
        }
        .disclosure {
            background: #e7f3ff;
            border: 1px solid #007bff;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
        }
        .signature-section {
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        .signature-block {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px dashed #ccc;
        }
        .signature-block:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
    </style>
</head>
<body>
    <h1>Accepted Bid Summary</h1>
    <p>This document summarizes the Services, Broker Compensation & Agency Agreement Terms, and related details for the bid accepted on the BidYourAgent platform. It is intended for transparency and recordkeeping and may be attached to the Agent's and Client's state-approved brokerage forms and agreements.</p>

    <div class="section">
        <h2>1. Parties</h2>
        
        <div class="party-info">
            <h3>Client (Tenant):</h3>
            <div class="info-row"><span class="info-label">First & Last Name:</span> {{tenant_name}}</div>
            <div class="info-row"><span class="info-label">Email:</span> {{tenant_email}}</div>
            <div class="info-row"><span class="info-label">Phone:</span> {{tenant_phone}}</div>
        </div>

        <div class="party-info">
            <h3>Agent:</h3>
            <div class="info-row"><span class="info-label">First & Last Name:</span> {{agent_name}}</div>
            <div class="info-row"><span class="info-label">Email:</span> {{agent_email}}</div>
            <div class="info-row"><span class="info-label">Phone:</span> {{agent_phone}}</div>
            <div class="info-row"><span class="info-label">Brokerage:</span> {{agent_brokerage_name}}</div>
            <div class="info-row"><span class="info-label">Real Estate License #:</span> {{agent_license_number}}</div>
            <div class="info-row"><span class="info-label">NAR Member ID:</span> {{agent_nar_id}}</div>
        </div>
    </div>

    <div class="section">
        <h2>2. Listing / Criteria Details</h2>
        <div class="info-row"><span class="info-label">Listing ID:</span> {{listing_id}}</div>
        <div class="info-row"><span class="info-label">Target Areas:</span> {{target_areas}}</div>
        <div class="info-row"><span class="info-label">Property Type Sought:</span> {{property_type}}</div>
        <div class="info-row"><span class="info-label">Date Bid Accepted:</span> {{accepted_date}}</div>
    </div>

    <div class="section">
        <h2>3. Services Selected for This Bid</h2>
        {{services_grouped_by_category}}
    </div>

    <div class="section">
        <h2>4. Broker Compensation & Agency Agreement Terms</h2>
        {{broker_compensation_and_agency_terms_block}}
    </div>

    <div class="section">
        <h2>5. Additional Details</h2>
        {{additional_details_block}}
    </div>

    <div class="section notice">
        <h2>6. Important Notice</h2>
        <p>This Accepted Bid Summary is provided for convenience and transparency. It is not a substitute for state-approved brokerage agreements, representation agreements, leases, or purchase contracts.</p>
        <p>The Client and Agent are responsible for incorporating these terms into their official agreements in accordance with state law and brokerage policy.</p>
    </div>

    <div class="section disclosure">
        <h2>7. Platform Referral & Recordkeeping Disclosure</h2>
        <p>This Accepted Bid Summary documents the service terms and broker compensation accepted through the BidYourAgent platform for this transaction.</p>
        <p>The parties acknowledge that BidYourAgent facilitated this transaction and may be entitled to a referral fee or platform facilitation fee from the Agent or Brokerage pursuant to a separate referral, participation, or platform agreement. This document is provided for transparency and recordkeeping only and does not create, modify, or replace any referral, commission, or brokerage agreement.</p>
    </div>

    <div class="section signature-section">
        <h2>8. Signature Acknowledgement</h2>
        <p>By signing below, the parties acknowledge receipt and review of this Accepted Bid Summary.</p>

        <div class="signature-block">
            <h4>Tenant Signature:</h4>
            <div class="info-row"><span class="info-label">Name:</span> {{tenant_signature_name}}</div>
            <div class="info-row"><span class="info-label">Date Signed:</span> {{tenant_signed_at}}</div>
            <div class="info-row"><span class="info-label">IP Address:</span> {{tenant_ip_address}}</div>
        </div>

        <div class="signature-block">
            <h4>Agent Signature:</h4>
            <div class="info-row"><span class="info-label">Name:</span> {{agent_signature_name}}</div>
            <div class="info-row"><span class="info-label">Date Signed:</span> {{agent_signed_at}}</div>
            <div class="info-row"><span class="info-label">IP Address:</span> {{agent_ip_address}}</div>
        </div>
    </div>
</body>
</html>
HTML;
    }

    public function updateSignature(AcceptedBidSummary $summary, string $role, string $signatureName, string $ipAddress): AcceptedBidSummary
    {
        $timestamp = now();

        if ($role === 'tenant') {
            $summary->tenant_signature_name = $signatureName;
            $summary->tenant_signed_at = $timestamp;
            $summary->tenant_ip_address = $ipAddress;
        } elseif ($role === 'agent') {
            $summary->agent_signature_name = $signatureName;
            $summary->agent_signed_at = $timestamp;
            $summary->agent_ip_address = $ipAddress;
        }

        $summary->save();

        $html = $summary->summary_html;
        
        if ($role === 'tenant') {
            $html = str_replace('{{tenant_signature_name}}', e($signatureName), $html);
            $html = str_replace('{{tenant_signed_at}}', e($timestamp->format('Y-m-d H:i:s T')), $html);
            $html = str_replace('{{tenant_ip_address}}', e($ipAddress), $html);
        } elseif ($role === 'agent') {
            $html = str_replace('{{agent_signature_name}}', e($signatureName), $html);
            $html = str_replace('{{agent_signed_at}}', e($timestamp->format('Y-m-d H:i:s T')), $html);
            $html = str_replace('{{agent_ip_address}}', e($ipAddress), $html);
        }

        $summary->summary_html = $html;
        $summary->save();

        return $summary;
    }

    public function getRenderedHtml(AcceptedBidSummary $summary): string
    {
        $html = $summary->summary_html;

        if ($summary->isTenantSigned()) {
            $html = str_replace('{{tenant_signature_name}}', e($summary->tenant_signature_name), $html);
            $html = str_replace('{{tenant_signed_at}}', e($summary->tenant_signed_at->format('Y-m-d H:i:s T')), $html);
            $html = str_replace('{{tenant_ip_address}}', e($summary->tenant_ip_address), $html);
        }

        if ($summary->isAgentSigned()) {
            $html = str_replace('{{agent_signature_name}}', e($summary->agent_signature_name), $html);
            $html = str_replace('{{agent_signed_at}}', e($summary->agent_signed_at->format('Y-m-d H:i:s T')), $html);
            $html = str_replace('{{agent_ip_address}}', e($summary->agent_ip_address), $html);
        }

        $html = preg_replace('/\{\{[^}]+\}\}/', '', $html);

        return $html;
    }
}
