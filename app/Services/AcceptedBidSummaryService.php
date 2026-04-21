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
    public function getRenderedHtml(AcceptedBidSummary $summary): string
    {
        $html = $summary->summary_html ?? '';

        $html = $this->fixAcceptedDateFormat($html, $summary);

        // ── Unconditional placeholder replacement (works for ALL roles) ────────
        // New-format summaries keep {{...}} placeholders in stored HTML.
        // Signed   → replace with the actual value.
        // Unsigned → replace with the pending indicator.
        if ($summary->isTenantSigned()) {
            $tenantSignedDisplay = $this->formatSignatureTimestamp($summary->tenant_signed_at, $summary->tenant_timezone);
            $html = str_replace('{{tenant_signature_name}}', e($summary->tenant_signature_name), $html);
            $html = str_replace('{{tenant_signed_at}}',      $tenantSignedDisplay,                $html);
            $html = str_replace('{{tenant_ip_address}}',     $summary->tenant_ip_address ?: 'Unavailable', $html);
            // Legacy regex path: old Tenant summaries stored "—" instead of placeholder
            $html = $this->updateTenantSignatureInHtml($html, $summary, $tenantSignedDisplay);
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
            // Legacy regex path: old Tenant summaries stored "—" instead of placeholder
            $html = $this->updateAgentSignatureInHtml($html, $summary, $agentSignedDisplay);
        } else {
            $html = str_replace('{{agent_signature_name}}', '—',       $html);
            $html = str_replace('{{agent_signed_at}}',      'Pending', $html);
            $html = str_replace('{{agent_ip_address}}',     '—',       $html);
        }

        return $html;
    }
    
    protected function updateTenantSignatureInHtml(string $html, AcceptedBidSummary $summary, string $signedDisplay): string
    {
        $html = preg_replace(
            '/(<h4[^>]*>Tenant Acknowledgement<\/h4>[\s\S]*?<strong>Signature:<\/strong>)\s*—/',
            '$1 ' . e($summary->tenant_signature_name),
            $html
        );
        
        $html = preg_replace(
            '/(<h4[^>]*>Tenant Acknowledgement<\/h4>[\s\S]*?<strong>Date\/Time:<\/strong>)\s*Pending/',
            '$1 ' . $signedDisplay,
            $html
        );
        
        $html = preg_replace(
            '/(<h4[^>]*>Tenant Acknowledgement<\/h4>[\s\S]*?<strong>IP Address:<\/strong>)\s*—/',
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
        $pattern = '/Accepted Date:\s*(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})/';
        
        if (preg_match($pattern, $html, $matches)) {
            try {
                $oldDate = $matches[1];
                $date = \Carbon\Carbon::parse($oldDate);
                $easternDate = $date->copy()->setTimezone('America/New_York');
                $formattedDate = $easternDate->format('F j, Y') . ' at ' . $easternDate->format('g:i A') . ' ET';
                
                $html = preg_replace($pattern, 'Accepted Date: ' . $formattedDate, $html);
            } catch (\Exception $e) {
            }
        }
        
        return $html;
    }

    public function updateSignature(AcceptedBidSummary $summary, string $role, string $signatureName, ?string $ipAddress, ?string $timezone = null, ?string $userAgent = null): AcceptedBidSummary
    {
        $now = now();
        
        if ($role === 'tenant') {
            $summary->tenant_signature_name = $signatureName;
            $summary->tenant_signed_at = $now;
            $summary->tenant_ip_address = $ipAddress ?: null;
            $summary->tenant_timezone = $timezone ?: 'UTC';
            $summary->tenant_user_agent = $userAgent;
        } else {
            $summary->agent_signature_name = $signatureName;
            $summary->agent_signed_at = $now;
            $summary->agent_ip_address = $ipAddress ?: null;
            $summary->agent_timezone = $timezone ?: 'UTC';
            $summary->agent_user_agent = $userAgent;
        }
        
        $summary->save();
        
        return $summary;
    }

    protected function formatSignatureTimestamp($datetime, ?string $timezone): string
    {
        if (!$datetime) {
            return 'Pending';
        }
        
        try {
            $tz = $timezone ?: 'UTC';
            $dt = $datetime instanceof \Carbon\Carbon ? $datetime : \Carbon\Carbon::parse($datetime);
            $localTime = $dt->setTimezone($tz);
            
            $tzAbbr = $this->getTimezoneAbbreviation($tz);
            
            return $localTime->format('M j, Y g:i A') . ' (' . $tzAbbr . ')';
        } catch (\Exception $e) {
            return $datetime->format('M j, Y g:i A') . ' (UTC)';
        }
    }

    protected function getTimezoneAbbreviation(string $timezone): string
    {
        $abbreviations = [
            'America/New_York' => 'ET',
            'America/Chicago' => 'CT',
            'America/Denver' => 'MT',
            'America/Los_Angeles' => 'PT',
            'America/Phoenix' => 'MST',
            'America/Anchorage' => 'AKT',
            'Pacific/Honolulu' => 'HST',
        ];
        
        return $abbreviations[$timezone] ?? $timezone;
    }

    protected $residentialServiceCategories = [
        'Tenant Criteria Marketing & Promotion' => [
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
            "Launch hyperlocal digital ads targeting the Tenant's preferred rental areas"
        ],
        'Property Search, Alerts & Matching' => [
            "Send email alerts with new listings from the MLS that match the Tenant's rental criteria",
            "Search for off-market, pre-market, withdrawn, canceled, or expired properties that meet the Tenant's rental criteria",
            "Communicate with the Landlord's Agent, Landlord, or Property Manager to confirm availability, lease terms, and showing instructions",
            "Evaluate properties with the Tenant and provide insights on pricing, lease terms, and overall fit"
        ],
        'Property Showings & Virtual Tours' => [
            "Schedule and attend property showings with the Tenant",
            "Coordinate or conduct virtual showings via live video or pre-recorded walkthroughs",
            "Preview properties on behalf of the Tenant upon request",
            "Provide factual observations on property layout and condition"
        ],
        'Tenant Application Support' => [
            "Provide the Tenant with application instructions or links to an online rental application platform",
            "Gather and organize required supporting documents (e.g., identification, income verification, reference letters)",
            "Submit complete and organized application packages to the Landlord's Agent, Landlord, or Property Manager for review",
            "Answer questions about the application process, screening timelines, and required documentation"
        ],
        'Lease Preparation & Execution' => [
            "Review lease offers and assist the Tenant in preparing questions or requested changes",
            "Coordinate lease negotiation with the Landlord's Agent, Landlord, or Property Manager",
            "Assist with completing required lease disclosures and reviewing key lease terms",
            "Assist with in-person or electronic lease signing, including e-signature setup and secure delivery of executed lease documents, addenda, and disclosures to all parties",
        ],
        'Move-In Support & Coordination' => [
            "Coordinate move-in date and key handoff logistics with the Landlord's Agent, Landlord or Property Manager",
            "Confirm completion of any agreed-upon pre-move-in cleaning or repairs",
            "Provide a utility setup checklist and local provider resources",
            "Share a move-in checklist for documentation and property condition review",
            "Confirm required move-in payments and assist the Tenant with tracking amounts due, deadlines, and accepted payment methods"
        ],
        'Leasing Strategy & Guidance' => [
            "Provide a Rental Market Analysis (RMA) with pricing insights based on comparable rentals, neighborhood trends, and current market conditions",
            "Advise on lease types and structures (e.g., month-to-month, annual, furnished, lease-option)",
            "Provide general guidance on Tenant rights and Landlord responsibilities under state law",
            "Provide general guidance on lease clauses, payment terms, and renewal options",
        ],
    ];

    protected $commercialServiceCategories = [
        'Tenant Criteria Marketing & Promotion' => [
            "Create a branded flyer summarizing the Tenant's leasing criteria",
            "Post the Tenant's leasing criteria on Craigslist under the \"Office/Commercial\" or \"Retail\" section",
            "Promote the Tenant's leasing criteria on Facebook in Commercial Leasing or Business Groups",
            "Share the Tenant's leasing criteria on Instagram using posts, stories, or reels",
            "Promote the Tenant's leasing criteria on LinkedIn in Professional, Real Estate, or Commercial Investment Groups",
            "Upload a TikTok video summarizing the Tenant's leasing criteria",
            "Upload a YouTube video summarizing the Tenant's leasing criteria",
            "Launch a mass email campaign promoting the Tenant's leasing criteria",
            "Distribute branded postcards or flyers in the Tenant's preferred neighborhoods",
            "Launch hyperlocal digital ads targeting the Tenant's preferred leasing areas"
        ],
        'Property Search, Alerts & Matching' => [
            "Send listing alerts from real estate platforms that match the Tenant's leasing criteria.",
            "Search for off-market, pre-market, withdrawn, canceled, or expired properties that meet the Tenant's rental criteria",
            "Communicate with the Landlord's Agent, Landlord, or Property Manager to confirm availability, lease terms, and showing instructions",
            "Evaluate properties for layout efficiency, building specs, logistics, zoning fit, and operational alignment"
        ],
        'Property Showings & Virtual Tours' => [
            "Schedule and attend property tours with the Tenant",
            "Coordinate or conduct virtual showings via live video or pre-recorded walkthroughs",
            "Preview properties on behalf of the Tenant upon request",
            "Provide factual notes on layout, access, parking, visibility, and other operational considerations"
        ],
        'Tenant Application Support' => [
            "Provide the Tenant with application instructions or links to online platforms",
            "Gather and organize required supporting documents (e.g., business licenses, financials, references)",
            "Submit complete and organized application packages to the Landlord's Agent, Landlord, or Property Manager"
        ],
        'Lease Preparation, LOI & Execution' => [
            "Draft or assist with preparing a Letter of Intent (LOI) summarizing the Tenant's business needs and proposed terms",
            "Assist with negotiating rent, CAM, lease term, TI allowance, exclusivity clauses, renewal options, and other provisions (as permitted under the agency agreement)",
            "Coordinate with the Landlord's Agent, Landlord or Property Manager to finalize lease terms",
            "Review lease drafts and coordinate revisions through appropriate channels",
            "Assist with in-person or electronic lease signing, including e-signature setup and secure delivery of executed lease documents, addenda, and disclosures to all parties",
            "Track required deposits, rent commencement, and key lease dates to ensure move-in readiness"
        ],
        'Move-In Support & Coordination' => [
            "Coordinate move-in date and key handoff logistics with the Landlord, Landlord's Agent, or Property Manager",
            "Confirm completion of any agreed-upon pre-move-in repairs, cleaning, or buildout",
            "Provide a utility setup checklist and local provider resources",
            "Share a move-in checklist for documentation and property condition review",
            "Confirm required move-in payments and assist the Tenant with tracking amounts due, deadlines, and accepted payment methods"
        ],
        'Leasing Strategy & Guidance' => [
            "Provide a Comparative Lease Market Analysis (CLMA) with pricing insights, comps, and vacancy trends",
            "Advise on lease types and structures (e.g., NNN, Modified Gross, Full Service) with general explanations of differences",
            "Provide general guidance on Tenant rights and Landlord responsibilities under commercial leasing law",
            "Provide general guidance on lease clauses, escalation terms, and space usage considerations",
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
                'listing_type'        => 'tenant',
                'listing_id'          => $listing->id,
                'accepted_bid_id'     => $bid->id,
                'accepted_counter_id' => $acceptedCounter ? $acceptedCounter->id : null,
                'tenant_user_id'      => $tenant->id,
                'agent_user_id'       => $agent->id,
                'summary_html'        => $html,
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

    public function regenerateSummary(AcceptedBidSummary $summary): ?AcceptedBidSummary
    {
        try {
            $bid = TenantAgentAuctionBid::find($summary->accepted_bid_id);
            if (!$bid) {
                Log::warning('Cannot regenerate summary - bid not found', ['summary_id' => $summary->id]);
                return null;
            }

            $listing = $bid->auction;
            if (!$listing) {
                Log::warning('Cannot regenerate summary - listing not found', ['summary_id' => $summary->id]);
                return null;
            }

            $tenant = $listing->user;
            $agent = $bid->user;

            $acceptedCounter = $summary->accepted_counter_id 
                ? TenantCounterBidding::find($summary->accepted_counter_id) 
                : null;

            $sourceData = $acceptedCounter ? $this->getCounterData($acceptedCounter) : $this->getBidData($bid);

            $html = $this->buildSummaryHtml($listing, $bid, $tenant, $agent, $sourceData, $acceptedCounter);

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

            return $summary;
        } catch (\Exception $e) {
            Log::error('Failed to regenerate accepted bid summary', [
                'summary_id' => $summary->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    public function regenerateAllSummaries(): int
    {
        $count = 0;
        $summaries = AcceptedBidSummary::all();
        
        foreach ($summaries as $summary) {
            if ($this->regenerateSummary($summary)) {
                $count++;
            }
        }
        
        return $count;
    }

    protected function getBidData(TenantAgentAuctionBid $bid): array
    {
        $bidData = $bid->get;
        $fields = $this->extractCompensationFields($bidData);

        // Strip referral_fee_percent from summaries for non-agent-created listings
        if (!optional($bid->auction)->isCreatedByAgent()) {
            unset($fields['referral_fee_percent']);
        }

        return $fields + [
            'services'      => $this->parseServices(data_get($bidData, 'services', [])),
            'other_services' => data_get($bidData, 'other_services', ''),
            'agent_name'    => data_get($bidData, 'name'),
            'agent_email'   => data_get($bidData, 'email'),
            'agent_phone'   => data_get($bidData, 'phone'),
            'agent_brokerage' => data_get($bidData, 'brokerage'),
            'agent_license' => data_get($bidData, 'license_no') ?: data_get($bidData, 'agent_license'),
            'agent_nar_id'  => data_get($bidData, 'mls_id'),
        ];
    }

    protected function getCounterData(TenantCounterBidding $counter): array
    {
        $counterData = $counter->get;
        $fields = $this->extractCompensationFields($counterData);

        // Strip referral_fee_percent from summaries for non-agent-created listings
        if (!optional($counter->auction)->isCreatedByAgent()) {
            unset($fields['referral_fee_percent']);
        }

        return $fields + [
            'services'      => $this->parseServices(data_get($counterData, 'services', [])),
            'other_services' => data_get($counterData, 'other_services', ''),
            'agent_name'    => null,
            'agent_email'   => null,
            'agent_phone'   => null,
            'agent_brokerage' => null,
            'agent_license' => null,
            'agent_nar_id'  => null,
        ];
    }

    /**
     * Extract all broker compensation & agency terms from a bid/counter data object.
     * Reads from current field names first (lease_fee_type, payment_timing, etc.),
     * then falls back to legacy field names stored by older form versions.
     *
     * @param  object|array $data  The ->get accessor result (stdClass or array).
     */
    protected function extractCompensationFields($data): array
    {
        $g = fn(string $key) => is_object($data) ? ($data->$key ?? null) : ($data[$key] ?? null);

        // Commission Structure: current=commission_structure, legacy=broker_fee_type
        $commissionStructure = $g('commission_structure') ?: $g('broker_fee_type');
        $commissionStructureDisplay = match($commissionStructure) {
            'Out-of-Pocket Payment' => 'Tenant Pays Out-of-Pocket',
            'Included in Offer'     => 'Requested From Landlord in the Offer',
            default                  => $commissionStructure,
        };

        // Commission Fee: current=lease_fee_type + sub-fields, legacy=broker_fee_amount
        $brokerFeeAmount = $g('broker_fee_amount');
        if (empty($brokerFeeAmount)) {
            $brokerFeeAmount = $this->resolveCommissionFeeDisplay($data);
        }

        // Payment Timing: current=payment_timing, legacy=broker_fee_timing
        $paymentTiming = $g('payment_timing') ?: $g('broker_fee_timing');

        // Days to Pay: current=days_to_pay, legacy=broker_fee_days
        $daysToPay = $g('days_to_pay') ?: $g('broker_fee_days');

        // Interested in Purchase: current field only
        $interestedPurchase = $g('interested_purchase_fee_type');

        // Purchase Fee
        $purchaseFeeType = $g('purchase_fee_type');
        $purchaseFeeAmount = $this->resolvePurchaseFeeDisplay($data);

        // Lease-Option: resolve type ('percent'/'flat') + value into single display string
        $leaseType  = $g('lease_type')  ?: $g('lease_option_fee_type');
        $leaseValue = $g('lease_value') ?: $g('lease_option_fee_amount');
        $leaseOptionDisplay = null;
        if ($leaseType === 'percent' && $leaseValue !== null && $leaseValue !== '') {
            $leaseOptionDisplay = rtrim(rtrim(number_format((float) $leaseValue, 2), '0'), '.') . '% of Lease Value';
        } elseif ($leaseValue !== null && $leaseValue !== '') {
            $leaseOptionDisplay = '$' . number_format((float) str_replace(',', '', (string) $leaseValue), 0);
        }
        $leaseOptionType   = $leaseOptionDisplay;
        $leaseOptionAmount = null;

        // Purchase-Option (exercised): resolve type+value into single display string
        $purchaseType  = $g('purchase_type');
        $purchaseValue = $g('purchase_value');
        $purchaseOptionDisplay = null;
        if ($purchaseType === 'percent' && $purchaseValue !== null && $purchaseValue !== '') {
            $purchaseOptionDisplay = rtrim(rtrim(number_format((float) $purchaseValue, 2), '0'), '.') . '% of Purchase Price';
        } elseif ($purchaseValue !== null && $purchaseValue !== '') {
            $purchaseOptionDisplay = '$' . number_format((float) str_replace(',', '', (string) $purchaseValue), 0);
        }
        $purchaseOptionType   = $purchaseOptionDisplay;
        $purchaseOptionAmount = null;

        // Early Termination: current=early_termination_fee_option, legacy=early_termination_fee
        $earlyTermFee = $g('early_termination_fee_option') ?: $g('early_termination_fee');

        // Retainer: current=retainer_fee_option, legacy=retainer_fee
        $retainerFee = $g('retainer_fee_option') ?: $g('retainer_fee');
        $retainerFeeApplication = $g('retainer_fee_application');
        if ($retainerFeeApplication !== null && $retainerFeeApplication !== '') {
            $retainerFeeApplication = $retainerFeeApplication === 'applied'
                ? 'Applied toward final compensation'
                : 'Charged in addition to final compensation';
        }

        // Additional Terms: current=additional_details_broker, legacy=additional_terms/additional_details
        $additionalTerms = $g('additional_details_broker')
            ?: $g('additional_terms')
            ?: $g('additional_details');

        return [
            'broker_fee_type'           => $commissionStructureDisplay,
            'broker_fee_amount'         => $brokerFeeAmount,
            'broker_fee_timing'         => $paymentTiming,
            'broker_fee_days'           => $daysToPay,
            'interested_purchase_fee_type' => $interestedPurchase,
            'purchase_fee_type'         => $purchaseFeeType,
            'purchase_fee_amount'       => $purchaseFeeAmount,
            'purchase_fee_other'        => $g('purchase_fee_other'),
            'lease_option_fee_type'     => $leaseOptionType,
            'lease_option_fee_amount'   => $leaseOptionAmount,
            'purchase_option_type'      => $purchaseOptionType,
            'purchase_option_amount'    => $purchaseOptionAmount,
            'lease_fee_other'           => $g('lease_fee_other'),
            'protection_period'         => $g('protection_period'),
            'protection_period_other'   => $g('protection_period_other'),
            'early_termination_fee'     => $earlyTermFee,
            'early_termination_fee_amount' => $g('early_termination_fee_amount'),
            'retainer_fee'              => $retainerFee,
            'retainer_fee_amount'       => $g('retainer_fee_amount'),
            'retainer_fee_application'  => $retainerFeeApplication,
            'agency_agreement_timeframe' => $g('agency_agreement_timeframe'),
            'agency_agreement_custom'   => $g('agency_agreement_custom'),
            'brokerage_relationship'    => $g('brokerage_relationship'),
            'brokerage_relationship_other' => $g('brokerage_relationship_other'),
            'additional_terms'          => $additionalTerms,
            'referral_fee_percent'      => $g('referral_fee_percent'),
        ];
    }

    /**
     * Build a human-readable commission fee display from lease_fee_type + sub-fields.
     * Mirrors the display logic used in hire_tenant_agent/view.blade.php.
     */
    protected function resolveCommissionFeeDisplay($data): ?string
    {
        $g = fn(string $key) => is_object($data) ? ($data->$key ?? null) : ($data[$key] ?? null);

        $lft  = (string) ($g('lease_fee_type') ?? '');
        $flat = $g('lease_fee_flat');
        $pct  = $g('lease_fee_percentage');
        $pctMonthly = $g('lease_fee_percentage_monthly_rent');
        $monthlyNum = $g('lease_fee_percentage_monthly_number');
        $flatCombo  = $g('lease_fee_flat_combo');
        $pctCombo   = $g('lease_fee_percentage_combo');
        $pctNet     = $g('lease_fee_percentage_net');
        $flatComboNet = $g('lease_fee_flat_combo_net');
        $pctComboNet  = $g('lease_fee_percentage_combo_net');
        $other = $g('lease_fee_other');

        $money = fn($v) => $v ? '$' . number_format((float) str_replace(',', '', (string) $v), 0) : null;
        $percent = fn($v) => $v ? rtrim(rtrim(number_format((float) $v, 2), '0'), '.') . '%' : null;
        $join = fn(array $parts) => implode(' + ', array_filter($parts));

        if ($lft === 'Flat Fee' && $flat) {
            return $money($flat);
        }
        if ($lft === 'Percentage of the Gross Lease Value' && $pct) {
            return $percent($pct) . ' of Gross Lease Value';
        }
        if ($lft === 'Percentage of Monthly Rent' && $pctMonthly) {
            $s = $percent($pctMonthly) . ' of Monthly Rent';
            if ($monthlyNum) $s .= ' x ' . $monthlyNum . ' Months';
            return $s;
        }
        if ($lft === 'Flat Fee + Percentage of the Gross Lease Value') {
            $parts = array_filter([$money($flatCombo), $pctCombo ? ($percent($pctCombo) . ' of Gross Lease Value') : null]);
            return $parts ? $join($parts) : null;
        }
        if ($lft === 'Percentage of the Net Aggregate Rent' && $pctNet) {
            return $percent($pctNet) . ' of Net Aggregate Rent';
        }
        if ($lft === 'Flat Fee + Percentage of the Net Aggregate Rent') {
            $parts = array_filter([$money($flatComboNet), $pctComboNet ? ($percent($pctComboNet) . ' of Net Aggregate Rent') : null]);
            return $parts ? $join($parts) : null;
        }
        if (strtolower($lft) === 'other' && $other) {
            return $other;
        }
        return $lft ?: null;
    }

    /**
     * Build a human-readable purchase fee display from purchase_fee_type + sub-fields.
     */
    protected function resolvePurchaseFeeDisplay($data): ?string
    {
        $g = fn(string $key) => is_object($data) ? ($data->$key ?? null) : ($data[$key] ?? null);

        $type = (string) ($g('purchase_fee_type') ?? '');
        $money = fn($v) => $v ? '$' . number_format((float) str_replace(',', '', (string) $v), 0) : null;
        $percent = fn($v) => $v ? rtrim(rtrim(number_format((float) $v, 2), '0'), '.') . '%' : null;

        if ($type === 'Flat Fee') {
            return $money($g('purchase_fee_flat'));
        }
        if ($type === 'Percentage of the Total Purchase Price') {
            $pct = $g('purchase_fee_percentage');
            return $pct ? $percent($pct) . ' of Total Purchase Price' : null;
        }
        // Handle both field-ordering variants — form stores "Percentage + Flat" order
        if ($type === 'Percentage of the Total Purchase Price + Flat Fee'
            || $type === 'Flat Fee + Percentage of the Total Purchase Price') {
            $parts = array_filter([
                $money($g('purchase_fee_flat_combo')),
                $g('purchase_fee_percentage_combo') ? ($percent($g('purchase_fee_percentage_combo')) . ' of Total Purchase Price') : null,
            ]);
            return $parts ? implode(' + ', $parts) : null;
        }
        if (strtolower($type) === 'other') {
            return $g('purchase_fee_other');
        }
        return null;
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
        
        $acceptedDateFormatted = $this->formatAcceptedDate($bid->accepted_date);
        $html = str_replace('{{accepted_date}}', e($acceptedDateFormatted), $html);

        $servicesHtml = $this->buildServicesHtml($sourceData['services'], $sourceData['other_services'], $propertyType);
        $html = str_replace('{{services_grouped_by_category}}', $servicesHtml, $html);

        $compensationHtml = $this->buildCompensationHtml($sourceData);
        $html = str_replace('{{broker_compensation_and_agency_terms_block}}', $compensationHtml, $html);

        $additionalDetailsHtml = $this->buildAdditionalDetailsHtml($sourceData, $listingData);
        $html = str_replace('{{additional_details_block}}', $additionalDetailsHtml, $html);

        $html = str_replace('{{tenant_signature_name}}', '—', $html);
        $html = str_replace('{{tenant_signed_at}}', 'Pending', $html);
        $html = str_replace('{{tenant_ip_address}}', '—', $html);
        $html = str_replace('{{agent_signature_name}}', '—', $html);
        $html = str_replace('{{agent_signed_at}}', 'Pending', $html);
        $html = str_replace('{{agent_ip_address}}', '—', $html);

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
            return str_replace(["\u{2019}", "\u{2018}", "\u{201C}", "\u{201D}"], ["'", "'", '"', '"'], $str);
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
            'broker_fee_type'              => "Tenant's Broker Commission Structure",
            'broker_fee_amount'            => "Tenant's Broker Commission Fee",
            'broker_fee_timing'            => 'Payment Timing for Broker Fees',
            'broker_fee_days'              => 'Calendar Days to Pay',
            'interested_purchase_fee_type' => 'Interested in Purchasing a Property',
            'purchase_fee_amount'          => 'Purchase Fee',
            'lease_option_fee_type'        => 'Compensation for Creating the Lease-Option Agreement',
            'purchase_option_type'         => 'Compensation if Purchase Option is Exercised',
            'protection_period'            => 'Protection Period Timeframe',
            'early_termination_fee'        => 'Early Termination Fee',
            'early_termination_fee_amount' => 'Early Termination Fee Amount',
            'retainer_fee'                 => 'Retainer Fee',
            'retainer_fee_amount'          => 'Retainer Fee Amount',
            'retainer_fee_application'     => 'Retainer Fee Application',
            'agency_agreement_timeframe'   => 'Tenant Agency Agreement Timeframe',
            'brokerage_relationship'       => 'Acceptable Brokerage Relationship',
            'additional_terms'             => 'Additional Terms',
            'referral_fee_percent'         => 'Referral Fee (%) (Agent-to-Agent)',
        ];

        $hasContent = false;
        foreach ($fields as $key => $label) {
            $value = $data[$key] ?? null;
            if (!empty($value) && $value !== 'N/A') {
                $displayValue = $this->formatCompensationValue($key, $value, $data);
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

    protected function formatCompensationValue(string $key, $value, array $data): string
    {
        if (is_array($value)) {
            return implode(', ', $value);
        }

        $value = $this->resolveOtherValue($key, $value, $data);
        
        if ($value === null || $value === '') {
            return '';
        }

        if (is_string($value) && (strpos($value, '$') !== false || strpos($value, '%') !== false)) {
            return $value;
        }

        $currencyFields = [
            'early_termination_fee_amount',
            'retainer_fee_amount',
        ];

        $daysFields = [
            'broker_fee_days',
            'protection_period',
        ];

        $percentFields = [];

        $feeAmountFields = [
            'broker_fee_amount',
        ];

        if (in_array($key, $currencyFields)) {
            return $this->formatAsCurrency($value);
        }

        if (in_array($key, $daysFields)) {
            if (is_numeric($value)) {
                return $value . ' days';
            }
            return $value;
        }

        if (in_array($key, $feeAmountFields)) {
            return $this->formatFeeAmount($key, $value, $data);
        }

        return (string) $value;
    }

    protected function resolveOtherValue(string $key, $value, array $data)
    {
        if (!is_string($value)) {
            return $value;
        }

        $valueStr = strtolower(trim($value));
        if ($valueStr !== 'other') {
            return $value;
        }

        $otherFieldMappings = [
            'agency_agreement_timeframe' => 'agency_agreement_custom',
            'protection_period' => 'protection_period_other',
            'brokerage_relationship' => 'brokerage_relationship_other',
            'purchase_fee_type' => 'purchase_fee_other',
            'lease_option_fee_type' => 'lease_fee_other',
            'broker_fee_type' => 'broker_fee_other',
            'broker_fee_timing' => 'broker_fee_timing_other',
        ];

        $otherKey = $otherFieldMappings[$key] ?? ($key . '_other');
        $customValue = $data[$otherKey] ?? null;

        if (!empty($customValue) && $customValue !== 'N/A') {
            return $customValue;
        }

        return null;
    }

    protected function formatFeeAmount(string $key, $value, array $data): string
    {
        if (is_string($value) && (strpos($value, '$') !== false || strpos($value, '%') !== false)) {
            return $value;
        }

        $typeKey = str_replace('_amount', '_type', $key);
        $type = $data[$typeKey] ?? '';

        $typeStr = strtolower((string) $type);

        if (strpos($typeStr, 'percent') !== false || strpos($typeStr, '%') !== false) {
            if (is_numeric($value)) {
                return $value . '%';
            }
            return $value . '%';
        }

        if (strpos($typeStr, 'flat') !== false || strpos($typeStr, 'dollar') !== false || strpos($typeStr, '$') !== false) {
            return $this->formatAsCurrency($value);
        }

        if (strpos($typeStr, '+') !== false || strpos($typeStr, 'plus') !== false || strpos($typeStr, 'combined') !== false) {
            return $value;
        }

        if (is_numeric($value)) {
            $numVal = floatval($value);
            if ($numVal > 100) {
                return $this->formatAsCurrency($value);
            } elseif ($numVal <= 100 && $numVal > 0) {
                return $value . '%';
            }
        }

        return (string) $value;
    }

    protected function formatAsCurrency($value): string
    {
        if (is_string($value) && strpos($value, '$') !== false) {
            return $value;
        }

        $cleanValue = str_replace(',', '', (string) $value);

        if (!is_numeric($cleanValue)) {
            return '$' . (string) $value;
        }

        $numVal = floatval($cleanValue);
        $hasDecimals = ($numVal != floor($numVal));

        if ($hasDecimals) {
            return '$' . number_format($numVal, 2);
        }

        return '$' . number_format($numVal, 0);
    }

    protected function formatAcceptedDate($acceptedDate): string
    {
        if (empty($acceptedDate)) {
            return now()->setTimezone('America/New_York')->format('F j, Y \a\t g:i A') . ' ET';
        }

        try {
            $date = $acceptedDate instanceof \Carbon\Carbon 
                ? $acceptedDate 
                : \Carbon\Carbon::parse($acceptedDate);
            
            $easternDate = $date->copy()->setTimezone('America/New_York');
            
            return $easternDate->format('F j, Y') . ' at ' . $easternDate->format('g:i A') . ' ET';
        } catch (\Exception $e) {
            return (string) $acceptedDate;
        }
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
                <h4 style="color: #007bff;">Tenant</h4>
                <p><strong>Name:</strong> {{tenant_name}}</p>
                <p><strong>Email:</strong> {{tenant_email}}</p>
                <p><strong>Phone:</strong> {{tenant_phone}}</p>
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
        <h2 style="color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px;">2. Listing/Criteria Details</h2>
        <p><strong>Property Type:</strong> {{property_type}}</p>
        <p><strong>Target Areas:</strong> {{target_areas}}</p>
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
        <p style="color: #0056b3;">The platform may receive a referral fee from the hired Agent or their brokerage as part of the agent's compensation. The Tenant does not pay any fee to the platform.</p>
    </div>

    <div style="background: #f8f9fa; padding: 20px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 20px;">
        <h2 style="color: #333; border-bottom: 2px solid #28a745; padding-bottom: 10px;">8. Signature Acknowledgement</h2>
        <div style="display: flex; flex-wrap: wrap; gap: 20px;">
            <div style="flex: 1; min-width: 250px; padding: 15px; border: 1px solid #ddd; border-radius: 5px;">
                <h4 style="color: #28a745;">Tenant Acknowledgement</h4>
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
