<?php

namespace App\Services;

use App\Models\AcceptedBidSummary;
use App\Models\LandlordAgentAuction;
use App\Models\LandlordAgentAuctionBid;
use App\Models\LandlordCounterTerm;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class LandlordAcceptedBidSummaryService
{
    // Residential Landlord service categories (source: services.blade.php Residential section)
    protected array $residentialServiceCategories = [
        'Rental Marketing & Listing Promotion' => [
            "List the property on the local Multiple Listing Service (MLS)",
            "Syndicate the listing to third-party platforms (e.g., Zillow.com, Realtor.com, Trulia.com, Homes.com)",
            "Create a branded flyer featuring the property\u2019s key highlights",
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
        'Listing Presentation & Preparation' => [
            "Conduct a property walkthrough and provide recommendations for listing readiness",
            "Provide a custom listing preparation checklist",
            "Collect property details and prepare MLS remarks and a public listing description",
            "Provide a visual consultation for interior layout, cleanliness, and presentation",
            "Provide a curb appeal consultation focused on exterior presentation",
            "Provide referrals to third-party vendors (e.g., cleaners, handypeople, electricians, landscapers). Vendor fees billed separately. Referrals only \u2014 no endorsement or warranty is made",
        ],
        'Photography, Video & Virtual Media' => [
            "Provide professional property photography",
            "Provide aerial (drone) photography (subject to FAA Part 107 compliance)",
            "Provide a video walkthrough tour",
            "Provide a 3D virtual tour",
            "Provide virtual staging (digital enhancements only; no physical staging)",
            "Provide digital photo enhancements",
            "Create a basic schematic floor plan (non-certified; for marketing purposes only)",
        ],
        'Showings & Access Coordination' => [
            "Ensure proper notice is given if the property is occupied",
            "Install a real estate sign on the property",
            "Install a lockbox for Agent access",
            "Schedule and attend showings with prospective Tenants",
            "Coordinate showings with Tenant\u2019s Agents",
            "Collect and relay feedback to the Landlord after showings",
        ],
        'Tenant Application Support' => [
            "Provide a link to an online application platform with third-party screening tools (e.g., credit, background, and eviction checks)",
            "Ensure compliance with Fair Housing laws and screening regulations throughout the application process",
            "Collect and organize application documents submitted by prospective Tenants",
            "Verify basic information provided in the application (e.g., employment, income, and references)",
            "Present complete and organized application packages to the Landlord for review and final selection",
        ],
        'Lease Preparation & Execution' => [
            "Review lease offers submitted by prospective Tenants and summarize key terms",
            "Coordinate lease negotiation with the Tenant or Tenant\u2019s Agent",
            "Prepare a state-specific lease agreement using approved forms or templates",
            "Assist with completing required lease disclosures and reviewing key lease terms",
            "Assist with in-person or electronic lease signing, including e-signature setup and secure delivery of executed lease documents, addenda, and disclosures to all parties",
            "Confirm receipt of required move-in funds and assist the Landlord in verifying amounts due, payment deadlines, and accepted payment methods",
        ],
        'Move-In Support & Coordination' => [
            "Coordinate move-in date and key handoff logistics with the Tenant or Tenant\u2019s Agent",
            "Confirm completion of any agreed-upon pre-move-in cleaning or repairs",
            "Verify receipt of all required move-in funds prior to occupancy (e.g., deposit, rent, pet fees)",
            "Provide a utility setup checklist and local provider resources for the Tenant",
            "Share a move-in checklist for documentation and property condition review",
        ],
        'Property Management' => [
            "Provide ongoing property management services throughout the lease term (rent collection, maintenance coordination, Tenant communications, lease enforcement, renewals, etc.)",
        ],
        'Leasing Strategy & Guidance' => [
            "Provide a Rental Market Analysis (RMA) with pricing insights based on comparable rentals, neighborhood trends, and current market conditions ",
            "Advise on lease types and structures (e.g., month-to-month, annual, furnished, corporate, lease-option)",
            "Provide general guidance on Landlord obligations and Tenant rights under state law",
            "Provide general guidance on rental demand, local market conditions, and Tenant expectations",
        ],
    ];

    // Commercial Landlord service categories (source: services.blade.php Commercial section)
    protected array $commercialServiceCategories = [
        'Rental Marketing & Listing Promotion' => [
            "List the property on the local Multiple Listing Service (MLS)",
            "List the property on Crexi.com",
            "List the property on LoopNet.com",
            "Create a branded flyer featuring the property\u2019s key highlights",
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
        'Listing Presentation & Preparation' => [
            "Conduct a property walkthrough and provide recommendations for listing readiness",
            "Provide a custom listing preparation checklist",
            "Collect property details such as lease terms, square footage, property features, and allowable uses",
            "Prepare a marketing packet including zoning, cap rate references, and permitted uses",
            "Provide a visual consultation focused on interior layout, cleanliness, and presentation",
            "Provide a curb appeal consultation for exterior appearance and signage opportunities",
            "Provide referrals to third-party vendors (e.g., cleaners, sign installers, minor repair vendors). Vendor fees billed separately. Referrals only \u2014 no endorsement or warranty is made",
        ],
        'Photography, Video & Virtual Media' => [
            "Provide professional property photography",
            "Provide aerial (drone) photography (subject to FAA Part 107 compliance)",
            "Provide a video walkthrough tour",
            "Provide a 3D virtual tour",
            "Provide virtual staging (digital enhancements only; no physical staging)",
            "Provide digital photo enhancements",
            "Create a basic schematic floor plan (non-certified; for marketing purposes only)",
        ],
        'Showings & Access Coordination' => [
            "Ensure proper notice is given if the property is occupied",
            "Install a real estate sign on the property",
            "Install a lockbox for Agent access",
            "Schedule and attend showings with prospective Tenants",
            "Coordinate showings with Tenant\u2019s Agents",
            "Collect and relay showing feedback to the Landlord",
        ],
        'Tenant Application Support' => [
            "Provide a link to an online application platform or share instructions with prospective Tenants or Tenant\u2019s Agents",
            "Ensure compliance with applicable federal, state, and local commercial leasing and anti-discrimination laws",
            "Collect and organize application documents (e.g., business licenses, financials, entity records, references)",
            "Verify basic information provided in the application (e.g., business operations, income sources, references)",
            "Present complete application packages to the Landlord for review and final selection",
        ],
        'Lease Preparation, LOI & Execution' => [
            "Coordinate lease negotiation with the Tenant or Tenant\u2019s Agent",
            "Collect and organize Letters of Intent (LOIs) or draft lease proposals",
            "Draft or assist with execution of the final lease agreement using approved forms or templates",
            "Provide and review required lease disclosures and addenda based on state or municipal requirements",
            "Assist with in-person or electronic lease signing, including e-signature setup and secure delivery of executed lease documents, addenda, and disclosures to all parties",
            "Verify receipt of required deposits and track rent commencement and key lease dates to ensure move-in readiness",
        ],
        'Move-In Support & Coordination' => [
            "Coordinate move-in date and key handoff logistics with the Tenant or Tenant\u2019s Agent",
            "Confirm completion of any agreed-upon pre-move-in repairs, cleaning, or improvements",
            "Verify receipt of all required move-in funds and documents prior to occupancy (e.g., rent, security deposit, insurance certificates)",
            "Provide a utility setup checklist and local provider resources for the Tenant",
            "Share a move-in checklist for documentation and property condition review",
            "Assist with coordination of move-in logistics, including Certificate of Insurance (COI) and vendor access (as agreed)",
        ],
        'Property Management' => [
            "Provide ongoing property management services throughout the lease term (rent collection, maintenance coordination, Tenant communications, lease enforcement, renewals, etc.)",
        ],
        'Leasing Strategy & Guidance' => [
            "Provide a Comparable Lease Analysis with pricing recommendations based on similar properties, local vacancy trends, and current market conditions",
            "Advise on lease types and structures (e.g., NNN, Modified Gross, Full Service) with general explanations of differences",
            "Provide general guidance on Landlord obligations and Tenant rights under applicable commercial leasing laws",
            "Provide general guidance on zoning, permitted uses, occupancy standards, or rent escalation terms",
        ],
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // Signature update helpers (reused verbatim from Tenant service)
    // ─────────────────────────────────────────────────────────────────────────

    public function getRenderedHtml(AcceptedBidSummary $summary): string
    {
        $html = $summary->summary_html ?? '';

        $html = $this->fixAcceptedDateFormat($html, $summary);

        if ($summary->isTenantSigned()) {
            $landlordSignedDisplay = $this->formatSignatureTimestamp($summary->tenant_signed_at, $summary->tenant_timezone);
            $html = str_replace('{{tenant_signature_name}}', e($summary->tenant_signature_name), $html);
            $html = str_replace('{{tenant_signed_at}}', $landlordSignedDisplay, $html);
            $html = str_replace('{{tenant_ip_address}}', $summary->tenant_ip_address ?: 'Unavailable', $html);
            $html = $this->updateLandlordSignatureInHtml($html, $summary, $landlordSignedDisplay);
        }

        if ($summary->isAgentSigned()) {
            $agentSignedDisplay = $this->formatSignatureTimestamp($summary->agent_signed_at, $summary->agent_timezone);
            $html = str_replace('{{agent_signature_name}}', e($summary->agent_signature_name), $html);
            $html = str_replace('{{agent_signed_at}}', $agentSignedDisplay, $html);
            $html = str_replace('{{agent_ip_address}}', $summary->agent_ip_address ?: 'Unavailable', $html);
            $html = $this->updateAgentSignatureInHtml($html, $summary, $agentSignedDisplay);
        }

        return $html;
    }

    protected function updateLandlordSignatureInHtml(string $html, AcceptedBidSummary $summary, string $signedDisplay): string
    {
        $html = preg_replace(
            '/(<h4[^>]*>Landlord Acknowledgement<\/h4>[\s\S]*?<strong>Signature:<\/strong>)\s*—/',
            '$1 ' . e($summary->tenant_signature_name),
            $html
        );
        $html = preg_replace(
            '/(<h4[^>]*>Landlord Acknowledgement<\/h4>[\s\S]*?<strong>Date\/Time:<\/strong>)\s*Pending/',
            '$1 ' . $signedDisplay,
            $html
        );
        $html = preg_replace(
            '/(<h4[^>]*>Landlord Acknowledgement<\/h4>[\s\S]*?<strong>IP Address:<\/strong>)\s*—/',
            '$1 ' . e($summary->tenant_ip_address ?: 'Recorded'),
            $html
        );
        return $html;
    }

    protected function updateAgentSignatureInHtml(string $html, AcceptedBidSummary $summary, string $signedDisplay): string
    {
        $html = preg_replace(
            '/(<h4[^>]*>Agent Acknowledgement<\/h4>[\s\S]*?<strong>Signature:<\/strong>)\s*—/',
            '$1 ' . e($summary->agent_signature_name),
            $html
        );
        $html = preg_replace(
            '/(<h4[^>]*>Agent Acknowledgement<\/h4>[\s\S]*?<strong>Date\/Time:<\/strong>)\s*Pending/',
            '$1 ' . $signedDisplay,
            $html
        );
        $html = preg_replace(
            '/(<h4[^>]*>Agent Acknowledgement<\/h4>[\s\S]*?<strong>IP Address:<\/strong>)\s*—/',
            '$1 ' . e($summary->agent_ip_address ?: 'Recorded'),
            $html
        );
        return $html;
    }

    protected function fixAcceptedDateFormat(string $html, AcceptedBidSummary $summary): string
    {
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
        } elseif ($role === 'agent') {
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

    protected function formatSignatureTimestamp($datetime, ?string $timezone): string
    {
        if (empty($datetime)) return 'Pending';
        try {
            $dt = $datetime instanceof \Carbon\Carbon ? $datetime : \Carbon\Carbon::parse($datetime);
            $displayTz = (!empty($timezone) && in_array($timezone, timezone_identifiers_list())) ? $timezone : 'America/New_York';
            $local = $dt->copy()->setTimezone($displayTz);
            $abbr = $this->getTimezoneAbbreviation($displayTz);
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

    // ─────────────────────────────────────────────────────────────────────────
    // Generate / Regenerate
    // ─────────────────────────────────────────────────────────────────────────

    public function generateSummary(LandlordAgentAuctionBid $bid, ?LandlordCounterTerm $acceptedCounter = null): ?AcceptedBidSummary
    {
        try {
            $listing = $bid->auction;
            $landlord = $listing->user;
            $agent = $bid->user;

            $sourceData = $acceptedCounter
                ? $this->getCounterData($acceptedCounter, $bid)
                : $this->getBidData($bid);

            $html = $this->buildSummaryHtml($listing, $bid, $landlord, $agent, $sourceData, $acceptedCounter);

            $summary = AcceptedBidSummary::create([
                'listing_id'        => $listing->id,
                'accepted_bid_id'   => $bid->id,
                'accepted_counter_id' => $acceptedCounter ? $acceptedCounter->id : null,
                'tenant_user_id'    => $landlord->id,
                'agent_user_id'     => $agent->id,
                'summary_html'      => $html,
            ]);

            return $summary;
        } catch (\Exception $e) {
            Log::error('Failed to generate landlord accepted bid summary', [
                'bid_id' => $bid->id,
                'error'  => $e->getMessage(),
                'trace'  => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Data extraction
    // ─────────────────────────────────────────────────────────────────────────

    protected function getBidData(LandlordAgentAuctionBid $bid): array
    {
        $bidData = $bid->get;
        return $this->extractCompensationFields($bidData) + [
            'services'        => $this->parseServices(data_get($bidData, 'services', [])),
            'other_services'  => data_get($bidData, 'other_services', ''),
            'agent_name'      => trim((data_get($bidData, 'first_name', '') ?? '') . ' ' . (data_get($bidData, 'last_name', '') ?? '')),
            'agent_email'     => data_get($bidData, 'email'),
            'agent_phone'     => data_get($bidData, 'phone'),
            'agent_brokerage' => data_get($bidData, 'brokerage'),
            'agent_license'   => data_get($bidData, 'license_no'),
            'agent_nar_id'    => data_get($bidData, 'nar_id'),
            'additional_details' => data_get($bidData, 'additional_details_broker') ?: data_get($bidData, 'additional_details'),
        ];
    }

    protected function getCounterData(LandlordCounterTerm $counter, LandlordAgentAuctionBid $bid): array
    {
        $counterMeta = $counter->getAllMeta();
        $bidData = $bid->get;

        return $this->extractCompensationFields($counterMeta) + [
            'services'        => $this->parseServices($counterMeta['services'] ?? []),
            'other_services'  => $counterMeta['other_services'] ?? '',
            'agent_name'      => trim((data_get($bidData, 'first_name', '') ?? '') . ' ' . (data_get($bidData, 'last_name', '') ?? '')),
            'agent_email'     => data_get($bidData, 'email'),
            'agent_phone'     => data_get($bidData, 'phone'),
            'agent_brokerage' => data_get($bidData, 'brokerage'),
            'agent_license'   => data_get($bidData, 'license_no'),
            'agent_nar_id'    => data_get($bidData, 'nar_id'),
            'additional_details' => $counterMeta['additional_details_broker'] ?? $counterMeta['additional_details'] ?? null,
        ];
    }

    protected function extractCompensationFields($data): array
    {
        $g = fn(string $key) => is_object($data) ? ($data->$key ?? null) : ($data[$key] ?? null);

        // Listing Commission Fee
        $purchaseFeeType   = $g('purchase_fee_type');
        $purchaseFeeAmount = $this->resolveLandlordListingFeeDisplay($data);

        // Payment Timing
        $brokerFeeTiming = $g('broker_fee_timing');
        $brokerFeeTimingResolved = $this->resolveBrokerFeeTimingDisplay($data);

        // Renewal Commission
        $renewalFeeType   = $g('renewal_fee_type');
        $renewalFeeDisplay = $this->resolveRenewalFeeDisplay($data);

        // Expansion Commission
        $expansionCommission = $g('expansion_commission_percentage');
        if ($expansionCommission !== null && $expansionCommission !== '') {
            $expansionCommission = rtrim(rtrim(number_format((float) $expansionCommission, 2), '0'), '.') . '%';
        }

        // Tenant Broker Compensation
        $tenantBrokerStructure = $g('tenant_broker_commission_structure');
        $tenantBrokerDisplay   = $this->resolveTenantBrokerDisplay($data);

        // Lease Option Agreement
        $interestedLeaseOption = $g('interested_lease_option_agreement');
        $leaseType  = $g('lease_type');
        $leaseValue = $g('lease_value');
        $leaseOptionDisplay = null;
        if ($leaseType === 'percent' && $leaseValue !== null && $leaseValue !== '') {
            $leaseOptionDisplay = rtrim(rtrim(number_format((float) $leaseValue, 2), '0'), '.') . '% of Lease Value';
        } elseif ($leaseValue !== null && $leaseValue !== '') {
            $leaseOptionDisplay = '$' . number_format((float) str_replace(',', '', (string) $leaseValue), 0);
        }
        $purchaseType  = $g('purchase_type');
        $purchaseValue = $g('purchase_value');
        $purchaseOptionDisplay = null;
        if ($purchaseType === 'percent' && $purchaseValue !== null && $purchaseValue !== '') {
            $purchaseOptionDisplay = rtrim(rtrim(number_format((float) $purchaseValue, 2), '0'), '.') . '% of Purchase Price';
        } elseif ($purchaseValue !== null && $purchaseValue !== '') {
            $purchaseOptionDisplay = '$' . number_format((float) str_replace(',', '', (string) $purchaseValue), 0);
        }

        // Interested in Selling
        $interestedInSelling = $g('interested_in_selling');
        $interestedInSellingType = $g('interested_in_selling_type');

        // Protection Period
        $protectionPeriod = $g('protection_period');

        // Early Termination
        $earlyTermFeeOption = $g('early_termination_fee_option');
        $earlyTermFeeAmount = $g('early_termination_fee_amount');

        // Agency Agreement
        $agencyAgreementTimeframe = $g('agency_agreement_timeframe');
        if (strtolower((string) $agencyAgreementTimeframe) === 'other') {
            $agencyAgreementTimeframe = $g('agency_agreement_custom') ?: $agencyAgreementTimeframe;
        }

        // Brokerage Relationship
        $brokerageRelationship = $g('brokerage_relationship');

        // Property Management Interest
        $interestedPropMgmt    = $g('interested_in_property_management');
        $interestedPropMgmtFee = $g('interested_in_property_management_fee');

        // Additional Terms
        $additionalTerms = $g('additional_details_broker') ?: $g('additional_details');

        return [
            'purchase_fee_type'                  => $purchaseFeeType,
            'purchase_fee_amount'                => $purchaseFeeAmount,
            'broker_fee_timing'                  => $brokerFeeTimingResolved ?: $brokerFeeTiming,
            'renewal_fee_type'                   => $renewalFeeType,
            'renewal_fee_amount'                 => $renewalFeeDisplay,
            'expansion_commission_percentage'    => $expansionCommission,
            'tenant_broker_commission_structure' => $tenantBrokerStructure,
            'tenant_broker_amount'               => $tenantBrokerDisplay,
            'interested_lease_option_agreement'  => $interestedLeaseOption,
            'lease_option_fee_type'              => $leaseOptionDisplay,
            'purchase_option_type'               => $purchaseOptionDisplay,
            'interested_in_selling'              => $interestedInSelling,
            'interested_in_selling_type'         => $interestedInSellingType,
            'protection_period'                  => $protectionPeriod,
            'early_termination_fee'              => $earlyTermFeeOption,
            'early_termination_fee_amount'       => ($earlyTermFeeOption === 'Yes' && !empty($earlyTermFeeAmount))
                                                        ? $this->formatAsCurrency($earlyTermFeeAmount) : null,
            'agency_agreement_timeframe'         => $agencyAgreementTimeframe,
            'brokerage_relationship'             => $brokerageRelationship,
            'interested_in_property_management'  => $interestedPropMgmt,
            'property_management_fee'            => ($interestedPropMgmt === 'Yes') ? $interestedPropMgmtFee : null,
            'additional_terms'                   => $additionalTerms,
        ];
    }

    protected function resolveLandlordListingFeeDisplay($data): ?string
    {
        $g = fn(string $key) => is_object($data) ? ($data->$key ?? null) : ($data[$key] ?? null);
        $money   = fn($v) => $v ? '$' . number_format((float) str_replace(',', '', (string) $v), 0) : null;
        $percent = fn($v) => $v ? rtrim(rtrim(number_format((float) $v, 2), '0'), '.') . '%' : null;

        $type = (string) ($g('purchase_fee_type') ?? '');

        if ($type === 'Flat Fee') {
            return $money($g('purchase_fee_flat'));
        }
        if ($type === 'Percentage of the Rent Due Each Rental Period') {
            return $g('purchase_fee_gross_rent') ? ($percent($g('purchase_fee_gross_rent')) . ' of Gross Rent') : null;
        }
        if ($type === 'Percentage of the Net Aggregate Rent') {
            return $g('purchase_fee_net_aggregate') ? ($percent($g('purchase_fee_net_aggregate')) . ' of Net Aggregate Rent') : null;
        }
        if ($type === "First Month's Rent") {
            return "First Month's Rent";
        }
        if ($type === 'Monthly Percentage of Rent') {
            $pct    = $g('purchase_fee_monthly_percentage');
            $months = $g('purchase_fee_months');
            $parts  = array_filter([$pct ? ($percent($pct) . '/month') : null, $months ? ($months . ' months') : null]);
            return $parts ? implode(', ', $parts) : null;
        }
        if ($type === 'Flat Fee - Commercial') {
            return $money($g('purchase_fee_flat_commercial'));
        }
        if ($type === 'Percentage of Total Purchase Price') {
            return $g('purchase_fee_purchase_price') ? ($percent($g('purchase_fee_purchase_price')) . ' of Total Purchase Price') : null;
        }
        // Combo types
        if (str_contains($type, '+') || str_contains(strtolower($type), 'combo') || str_contains(strtolower($type), 'percentage') && str_contains(strtolower($type), 'flat')) {
            $parts = array_filter([
                $money($g('purchase_fee_flat_combo')),
                $g('purchase_fee_percentage_combo') ? ($percent($g('purchase_fee_percentage_combo')) . ' of Gross Rent') : null,
            ]);
            return $parts ? implode(' + ', $parts) : null;
        }
        if (strtolower($type) === 'other') {
            return $g('purchase_fee_other_commercial') ?: $g('purchase_fee_other');
        }
        return null;
    }

    protected function resolveBrokerFeeTimingDisplay($data): ?string
    {
        $g = fn(string $key) => is_object($data) ? ($data->$key ?? null) : ($data[$key] ?? null);
        $timing = (string) ($g('broker_fee_timing') ?? '');
        if (empty($timing)) return null;

        $daysMap = [
            'Paid Within Calendar Days After Executed Lease'      => $g('broker_fee_days_after_lease'),
            'Paid Within Calendar Days After Rent Commencement'   => $g('broker_fee_days_after_rent'),
            'Paid Within Calendar Days From First Month Rent Due' => $g('broker_fee_days_from_rent'),
            'Paid Within Calendar Days After Due Event'          => $g('broker_fee_days_after_due_event'),
        ];

        if (isset($daysMap[$timing])) {
            $days = $daysMap[$timing];
            if ($days) {
                return 'Within ' . $days . ' calendar days — ' . $timing;
            }
            return $timing;
        }

        if ($timing === 'Split Payment') {
            $splitDue = $g('split_payment_due');
            if ($splitDue) {
                return 'Split Payment — ' . $splitDue;
            }
            return 'Split Payment';
        }

        if (strtolower($timing) === 'other') {
            return $g('broker_fee_timing_other') ?: $timing;
        }

        return $timing;
    }

    protected function resolveRenewalFeeDisplay($data): ?string
    {
        $g = fn(string $key) => is_object($data) ? ($data->$key ?? null) : ($data[$key] ?? null);
        $money   = fn($v) => $v ? '$' . number_format((float) str_replace(',', '', (string) $v), 0) : null;
        $percent = fn($v) => $v ? rtrim(rtrim(number_format((float) $v, 2), '0'), '.') . '%' : null;

        $type = (string) ($g('renewal_fee_type') ?? '');
        if (empty($type) || strtolower($type) === 'no renewal fee' || $type === 'None') return null;

        if (str_contains(strtolower($type), 'percentage') || str_contains(strtolower($type), '%')) {
            $pct   = $g('renewal_fee_percentage');
            $basis = $g('renewal_fee_lease_value') ?: 'Gross Lease Value';
            return $pct ? ($percent($pct) . ' of ' . $basis) : null;
        }
        if (str_contains(strtolower($type), 'first month')) {
            $val = $g('renewal_fee_first_month');
            return $val ? ('First month — ' . $val) : "First Month's Rent";
        }
        if (str_contains(strtolower($type), 'flat')) {
            return $money($g('renewal_fee_flat_free'));
        }
        if (str_contains(strtolower($type), 'month')) {
            $months = $g('renewal_fee_no_of_months');
            return $months ? ($months . ' months of rent') : null;
        }
        $custom = $g('renewal_fee_custom');
        if ($custom) return $custom;

        return $type;
    }

    protected function resolveTenantBrokerDisplay($data): ?string
    {
        $g = fn(string $key) => is_object($data) ? ($data->$key ?? null) : ($data[$key] ?? null);
        $money   = fn($v) => $v ? '$' . number_format((float) str_replace(',', '', (string) $v), 0) : null;
        $percent = fn($v) => $v ? rtrim(rtrim(number_format((float) $v, 2), '0'), '.') . '%' : null;

        $structure = (string) ($g('tenant_broker_commission_structure') ?? '');
        if (empty($structure)) return null;

        $feeStructure = $g('tenant_broker_fee_structure') ?: '';

        if (str_contains(strtolower($feeStructure), 'percentage') || str_contains(strtolower($feeStructure), '%')) {
            $pct = $g('tenant_broker_percentage');
            $basis = $g('tenant_broker_gross_lease') ?: ($g('tenant_broker_first_month_rent') ?: 'Gross Lease Value');
            return $pct ? ($percent($pct) . ' of ' . $basis) : null;
        }
        if (str_contains(strtolower($feeStructure), 'flat')) {
            return $money($g('tenant_broker_flat_fee'));
        }
        if (strtolower($feeStructure) === 'other') {
            return $g('tenant_broker_other');
        }
        return $feeStructure ?: $structure;
    }

    protected function parseServices($services): array
    {
        if (is_string($services)) {
            $decoded = json_decode($services, true);
            return is_array($decoded) ? $decoded : [];
        }
        return is_array($services) ? $services : [];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HTML generation
    // ─────────────────────────────────────────────────────────────────────────

    protected function buildSummaryHtml(
        LandlordAgentAuction $listing,
        LandlordAgentAuctionBid $bid,
        User $landlord,
        User $agent,
        array $sourceData,
        ?LandlordCounterTerm $acceptedCounter
    ): string {
        $listingData = $listing->get;
        $propertyType = data_get($listingData, 'property_type', 'Residential Property');

        $landlordName  = trim(($landlord->first_name ?? '') . ' ' . ($landlord->last_name ?? ''));
        $landlordEmail = $landlord->email ?? '';
        $landlordPhone = $landlord->phone_number ?? '';

        $agentName     = !empty(trim($sourceData['agent_name'] ?? ''))
            ? $sourceData['agent_name']
            : trim(($agent->first_name ?? '') . ' ' . ($agent->last_name ?? ''));
        $agentEmail    = $sourceData['agent_email'] ?: ($agent->email ?? '');
        $agentPhone    = $sourceData['agent_phone'] ?: ($agent->phone_number ?? '');
        $agentBrokerage = $sourceData['agent_brokerage'] ?: '';
        $agentLicense  = $sourceData['agent_license'] ?: '';
        $agentNarId    = $sourceData['agent_nar_id'] ?: '';

        $propertyAddress = $this->buildPropertyAddress($listingData);

        $html = $this->getHtmlTemplate();

        $html = str_replace('{{landlord_name}}',  e($landlordName),  $html);
        $html = str_replace('{{landlord_email}}', e($landlordEmail), $html);
        $html = str_replace('{{landlord_phone}}', e($landlordPhone), $html);

        $html = str_replace('{{agent_name}}',           e($agentName),      $html);
        $html = str_replace('{{agent_email}}',          e($agentEmail),     $html);
        $html = str_replace('{{agent_phone}}',          e($agentPhone),     $html);
        $html = str_replace('{{agent_brokerage_name}}', e($agentBrokerage), $html);
        $html = str_replace('{{agent_license_number}}', e($agentLicense),   $html);
        $html = str_replace('{{agent_nar_id}}',         e($agentNarId),     $html);

        $html = str_replace('{{listing_id}}',       e($listing->listing_id ?? 'LAA-' . $listing->id), $html);
        $html = str_replace('{{property_address}}', e($propertyAddress),  $html);
        $html = str_replace('{{property_type}}',    e($propertyType),     $html);

        $acceptedDateFormatted = $this->formatAcceptedDate($bid->accepted_date);
        $html = str_replace('{{accepted_date}}', e($acceptedDateFormatted), $html);

        $servicesHtml = $this->buildServicesHtml($sourceData['services'], $sourceData['other_services'], $propertyType);
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
        return implode(', ', array_filter($parts));
    }

    protected function buildServicesHtml(array $services, $otherServices, string $propertyType): string
    {
        if (empty($services) && empty($otherServices)) {
            return '<p><em>No services selected.</em></p>';
        }

        $categories = (str_contains(strtolower($propertyType), 'commercial'))
            ? $this->commercialServiceCategories
            : $this->residentialServiceCategories;

        $normalizeStr = function($str) {
            return str_replace(["\u{2019}", "\u{2018}", "\u{201C}", "\u{201D}"], ["'", "'", '"', '"'], $str);
        };

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
                $html .= '<ul style="margin: 0; padding-left: 25px; list-style-type: disc;">';
                foreach ($selectedInCategory as $service) {
                    $html .= '<li style="margin-bottom: 4px; list-style-type: disc;">' . e($service) . '</li>';
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
        $html = '<table style="width: 100%; border-collapse: collapse;">';

        $fields = [
            'purchase_fee_type'                  => 'Listing Commission Type',
            'purchase_fee_amount'                => 'Listing Commission Fee',
            'broker_fee_timing'                  => 'Payment Timing for Listing Fee',
            'renewal_fee_type'                   => 'Renewal Commission Type',
            'renewal_fee_amount'                 => 'Renewal Commission Fee',
            'expansion_commission_percentage'    => 'Expansion Commission',
            'tenant_broker_commission_structure' => 'Tenant Broker Compensation',
            'tenant_broker_amount'               => 'Tenant Broker Fee',
            'interested_lease_option_agreement'  => 'Interested in Lease-Option Agreement',
            'lease_option_fee_type'              => 'Compensation for Lease-Option Agreement',
            'purchase_option_type'               => 'Compensation if Purchase Option is Exercised',
            'interested_in_selling'              => 'Interested in Selling the Property',
            'interested_in_selling_type'         => 'Sale Commission Structure',
            'protection_period'                  => 'Protection Period Timeframe',
            'early_termination_fee'              => 'Early Termination Fee',
            'early_termination_fee_amount'       => 'Early Termination Fee Amount',
            'agency_agreement_timeframe'         => 'Agency Agreement Timeframe',
            'brokerage_relationship'             => 'Acceptable Brokerage Relationship',
            'interested_in_property_management'  => 'Interested in Property Management',
            'property_management_fee'            => 'Property Management Fee',
            'additional_terms'                   => 'Additional Terms',
        ];

        $hasContent = false;
        foreach ($fields as $key => $label) {
            $value = $data[$key] ?? null;
            if (!empty($value) && $value !== 'N/A') {
                $displayValue = is_array($value) ? implode(', ', $value) : (string) $value;
                if (!empty($displayValue)) {
                    $hasContent = true;
                    $html .= '<tr>';
                    $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold; width: 40%;">' . e($label) . '</td>';
                    $html .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">' . e($displayValue) . '</td>';
                    $html .= '</tr>';
                }
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

        $landlordDetails = data_get($listingData, 'additional_details');
        if (!empty($landlordDetails)) {
            $parts[] = '<div class="mb-2"><strong>Landlord\'s Additional Details:</strong><br>' . nl2br(e($landlordDetails)) . '</div>';
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

    protected function formatAcceptedDate($acceptedDate): string
    {
        if (empty($acceptedDate)) {
            return now()->setTimezone('America/New_York')->format('F j, Y \a\t g:i A') . ' ET';
        }
        try {
            $date       = $acceptedDate instanceof \Carbon\Carbon ? $acceptedDate : \Carbon\Carbon::parse($acceptedDate);
            $easternDate = $date->copy()->setTimezone('America/New_York');
            return $easternDate->format('F j, Y') . ' at ' . $easternDate->format('g:i A') . ' ET';
        } catch (\Exception $e) {
            return (string) $acceptedDate;
        }
    }

    protected function formatAsCurrency($value): string
    {
        if (is_string($value) && strpos($value, '$') !== false) return $value;
        $clean = str_replace(',', '', (string) $value);
        if (!is_numeric($clean)) return '$' . (string) $value;
        $num = floatval($clean);
        return ($num != floor($num)) ? '$' . number_format($num, 2) : '$' . number_format($num, 0);
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

    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
        <h2 style="color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px;">1. Parties</h2>
        <div style="display: flex; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 250px; margin-bottom: 15px;">
                <h4 style="color: #007bff;">Landlord</h4>
                <p><strong>Name:</strong> {{landlord_name}}</p>
                <p><strong>Email:</strong> {{landlord_email}}</p>
                <p><strong>Phone:</strong> {{landlord_phone}}</p>
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
        <h2 style="color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px;">4. Agreed Compensation & Agency Terms</h2>
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
        <p style="color: #0056b3;">The platform may receive a referral fee from the hired Agent or their brokerage as part of the agent's compensation. The Landlord does not pay any fee to the platform.</p>
    </div>

    <div style="background: #f8f9fa; padding: 20px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 20px;">
        <h2 style="color: #333; border-bottom: 2px solid #28a745; padding-bottom: 10px;">8. Signature Acknowledgement</h2>
        <div style="display: flex; flex-wrap: wrap; gap: 20px;">
            <div style="flex: 1; min-width: 250px; padding: 15px; border: 1px solid #ddd; border-radius: 5px;">
                <h4 style="color: #28a745;">Landlord Acknowledgement</h4>
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
