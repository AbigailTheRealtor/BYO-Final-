<?php

namespace App\Helpers;

class BuyerBidMatchScoreHelper
{
    private const RESIDENTIAL_SERVICES_CATALOG = [
        "create a branded flyer summarizing the buyer's purchase criteria",
        "post the buyer's purchase criteria on craigslist under the \"real estate wanted\" section",
        "share the buyer's purchase criteria on nextdoor in neighborhood or community groups",
        "promote the buyer's purchase criteria on facebook in real estate or housing groups",
        "share the buyer's purchase criteria on instagram using posts, stories, or reels",
        "promote the buyer's purchase criteria on linkedin in real estate or housing groups",
        "upload a tiktok video summarizing the buyer's purchase criteria",
        "upload a youtube video summarizing the buyer's purchase criteria",
        "launch a mass email campaign promoting the buyer's purchase criteria",
        "distribute branded postcards or flyers in the buyer's preferred neighborhoods",
        "launch hyperlocal digital ads targeting the buyer's preferred purchase areas",
        "send email alerts with new listings from the mls that match the buyer's purchase criteria",
        "search for off-market, pre-market, distressed, withdrawn, canceled, or expired properties that meet the buyer's purchase criteria",
        "communicate with the seller's agent or seller to confirm availability, purchase terms, and showing instructions",
        "evaluate properties with the buyer and provide insights on pricing, terms, potential, and overall fit",
        "schedule and attend property showings with the buyer",
        "coordinate or conduct virtual showings via live video or pre-recorded walkthroughs",
        "preview properties on behalf of the buyer upon request",
        "provide factual observations on property layout and condition",
        "draft and submit offers using state-approved purchase forms",
        "provide the buyer with the necessary disclosure forms required by state or local law",
        "draft and deliver counteroffers and manage revisions to the purchase agreement",
        "negotiate price, deposits, and contingencies with the seller's agent or seller (as permitted under the agency agreement)",
        "manage communications with the seller's agent or seller",
        "assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties",
        "assist with inspection-related negotiations and buyer requests for repairs",
        "monitor contract milestones, contingency periods, and financing deadlines",
        "provide referrals to attorneys, title companies, escrow professionals, or lenders (referrals only — no endorsement or warranty is made)",
        "coordinate inspections, appraisals, and lease audits (if applicable)",
        "coordinate with the lender, title, escrow, and/or attorney to prepare for closing",
        "review the settlement statement for accuracy and coordinate with relevant parties if corrections are needed (no legal or financial advice provided)",
        "confirm delivery of final executed documents, wire instructions, and closing paperwork to all relevant parties",
        "schedule and confirm the final walkthrough",
        "schedule and confirm the closing appointment",
        "provide a comparative market analysis (cma) with pricing recommendations based on comparable sales, neighborhood trends, and current market conditions (for informational purposes only — not a formal appraisal)",
        "answer general questions about financing, loan options, property taxes, insurance, and escrow timelines (non-legal guidance)",
        "provide factual information about neighborhood characteristics, school zones, crime data, and local amenities using third-party sources (no personal opinions or steering)",
        "offer general guidance on inspection expectations, common repair requests, and contingency planning during the offer process (non-legal advice)",
    ];

    private const INCOME_SERVICES_CATALOG = [
        "create a branded flyer summarizing the buyer's purchase criteria",
        "post the buyer's purchase criteria on craigslist under the \"real estate wanted\" section",
        "share the buyer's purchase criteria on nextdoor in neighborhood or community groups",
        "promote the buyer's purchase criteria on facebook in real estate investor or multifamily groups",
        "share the buyer's purchase criteria on instagram using posts, stories, or reels",
        "promote the buyer's purchase criteria on linkedin in investment or property management groups",
        "upload a tiktok video summarizing the buyer's purchase criteria",
        "upload a youtube video summarizing the buyer's purchase criteria",
        "launch a mass email campaign promoting the buyer's purchase criteria",
        "distribute branded postcards or flyers in the buyer's preferred neighborhoods",
        "launch hyperlocal digital ads targeting the buyer's preferred purchase areas",
        "send email alerts with new listings that match the buyer's purchase criteria",
        "search for off-market, pre-market, distressed, withdrawn, canceled, or expired properties that meet the buyer's purchase criteria",
        "communicate with the seller's agent or sellers to confirm pricing, rental income, expenses, and showing instructions",
        "evaluate investment properties with the buyer and provide insights on cash flow, cap rates, and value-add potential",
        "schedule and attend property showings with the buyer",
        "coordinate or conduct virtual showings via live video or pre-recorded walkthroughs",
        "preview properties on behalf of the buyer upon request",
        "provide observations on tenant occupancy, building condition, and operating expenses",
        "draft and submit offers using state-approved purchase forms",
        "provide the buyer with the necessary disclosure forms required by state or local law",
        "draft and deliver counteroffers and manage revisions to the purchase agreement",
        "negotiate price, deposits, and contingencies with the seller's agent or seller",
        "manage communication with the seller's agent or seller",
        "assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties",
        "assist with inspection-related negotiations and buyer requests for repairs",
        "monitor contract milestones, contingency periods, and financing deadlines",
        "provide referrals to attorneys, title companies, escrow professionals, lenders, or 1031 exchange intermediaries (referrals only — no endorsement or warranty is made)",
        "review and provide due diligence documents such as lease agreements, estoppel certificates, rent rolls, utility summaries, and operating expense breakdowns (as available)",
        "coordinate with the seller's agent, buyer's lender, title, escrow, and/or attorney to prepare for closing",
        "review the settlement statement for accuracy and coordinate with relevant parties if corrections are needed (no legal or financial advice provided)",
        "confirm delivery of final executed documents, wire instructions, and closing paperwork to all relevant parties",
        "schedule and confirm the final walkthrough",
        "schedule and confirm the closing appointment",
        "provide a comparative market analysis (cma) with pricing recommendations, rental comps, and cap rate estimates (for informational purposes only — not a formal appraisal)",
        "answer general questions about financing options, rent control, property taxes, and landlord responsibilities",
        "provide factual information on rental demand, turnover rates, and sub market conditions using third-party sources",
        "offer general guidance on due diligence steps, lease audits, and estoppel reviews (non-legal advice)",
    ];

    private const COMMERCIAL_SERVICES_CATALOG = [
        "create a branded flyer summarizing the buyer's purchase criteria",
        "post the buyer's criteria on craigslist under \"real estate wanted – commercial\"",
        "promote the buyer's criteria on facebook in commercial real estate or investment groups",
        "share the buyer's criteria on instagram using posts, stories, or reels",
        "promote the buyer's criteria on linkedin in commercial or investment groups",
        "upload a tiktok video summarizing the buyer's purchase criteria",
        "upload a youtube video summarizing the buyer's purchase criteria",
        "launch a mass email campaign promoting the buyer's purchase criteria",
        "distribute branded postcards or flyers in the buyer's preferred purchase areas",
        "launch hyperlocal or interest-based digital ad campaigns targeting desired commercial property types",
        "send property alerts that match the buyer's purchase criteria from the mls or commercial listing platforms",
        "search for off-market, pre-market, distressed, withdrawn, canceled, or expired listings that meet the buyer's criteria",
        "communicate with the seller's agent or seller to confirm availability, purchase terms, and showing instructions",
        "analyze building class, property zoning, income potential, and redevelopment opportunities",
        "schedule and attend property showings with the buyer",
        "coordinate or conduct virtual showings via live video or recorded walkthroughs",
        "preview properties on behalf of the buyer upon request",
        "provide insights on layout, access, visibility, tenant mix, and surrounding infrastructure",
        "draft and submit offers using state-approved purchase agreements or letters of intent (lois)",
        "provide the buyer with the necessary disclosure forms required by state or local law",
        "draft and deliver counteroffers and manage revisions to the purchase agreement",
        "negotiate price, deposit structure, timelines, and contingencies with the seller or seller's agent",
        "manage communication with the seller's agent or seller",
        "assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties",
        "assist with due diligence negotiations, including repair requests or credits",
        "monitor contract contingencies, including financing, estoppel review, lease audits, and environmental reports",
        "provide referrals to attorneys, title companies, escrow officers, commercial lenders, or 1031 exchange intermediaries (referrals only — no endorsement or warranty is made)",
        "coordinate inspections, appraisals, environmental assessments, and estoppel certificate collection as needed",
        "review and request due diligence documentation such as lease agreements, estoppel certificates, rent rolls, utility summaries, and operating expense breakdowns (as available)",
        "coordinate with the lender, title company, escrow officer, and/or attorney to prepare for closing",
        "review the settlement statement for accuracy and coordinate with all parties if corrections are needed (no legal or financial advice provided)",
        "confirm delivery of final executed documents, wire instructions, and closing paperwork to all relevant parties",
        "schedule and confirm the final walkthrough",
        "schedule and confirm the closing appointment",
        "provide a comparative market analysis (cma) with recent sales comps, lease comps, and an estimated value range (for informational purposes only — not a formal appraisal)",
        "answer general questions about zoning regulations, permitted uses, and rental income potential",
        "provide factual data on traffic counts, commercial market trends, and area demographics using third-party sources (no personal opinions or steering)",
        "offer general guidance on lease types, contingency timelines, due diligence, and environmental risks (non-legal advice only)",
    ];

    private const BUSINESS_SERVICES_CATALOG = [
        "create a branded flyer summarizing the buyer's purchase criteria",
        "post the buyer's purchase criteria on craigslist under \"business for sale\" or \"real estate wanted – commercial\"",
        "promote the buyer's purchase criteria on facebook in business opportunity or franchise groups",
        "share the buyer's purchase criteria on instagram using posts, stories, or reels",
        "promote the buyer's purchase criteria on linkedin in business, commercial, or startup groups",
        "upload a tiktok video summarizing the buyer's purchase criteria",
        "upload a youtube video summarizing the buyer's purchase criteria",
        "launch a mass email campaign promoting the buyer's purchase criteria",
        "distribute branded postcards or flyers in the buyer's preferred neighborhoods",
        "launch hyperlocal digital ads targeting the buyer's preferred purchase areas",
        "send alerts for businesses that match the buyer's acquisition criteria from mls, bizbuysell, or other listing platforms",
        "search for off-market, pre-market, distressed, or recently closed businesses that meet the buyer's criteria",
        "communicate with the seller's broker or seller to confirm pricing, lease terms, licensing status, and showing availability",
        "analyze financials, lease assignments, business licensing requirements, and overall market positioning",
        "schedule and attend property or business showings with the buyer",
        "coordinate or conduct virtual showings via live video or pre-recorded walkthroughs",
        "preview properties or business locations on behalf of the buyer upon request",
        "provide insights on foot traffic, customer base, operational setup, competitive advantages, and location dynamics",
        "draft and submit offers using appropriate business purchase or asset sale forms",
        "provide the buyer with required disclosures, financial summaries, and documentation made available by the seller",
        "negotiate terms such as purchase price, deposit structure, inventory inclusions, non-compete agreements, and contingencies",
        "draft and deliver counteroffers and manage revisions to the purchase agreement",
        "manage communication with the seller's broker or seller",
        "assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties",
        "assist with due diligence coordination, buyer-requested repairs, and adjustment negotiations",
        "monitor contingency periods, financing milestones, and deal approval timelines",
        "provide referrals to business attorneys, cpas, escrow officers, or lenders (referrals only — no endorsement or warranty is made)",
        "coordinate inspections, licensing verifications, lease assignments, and inventory counts",
        "coordinate with lenders, attorneys, escrow officers, title companies, cpas, and other involved parties to prepare for closing",
        "review the settlement statement or closing worksheet for accuracy and coordinate with all parties if corrections are needed (no legal or financial advice provided)",
        "confirm delivery of final executed documents, wire instructions, and business transition materials",
        "schedule and confirm the final walkthrough",
        "schedule and confirm the closing appointment",
        "provide a comparative market review based on similar business sales, financial performance, and industry benchmarks (for informational purposes only — not a formal appraisal or valuation)",
        "answer general questions about licensing, zoning, sba financing, registration steps, and transition timing (non-legal guidance)",
        "offer general guidance on due diligence preparation, key documents to review, and red flags during the acquisition process (non-legal advice only)",
    ];

    private const VACANT_LAND_SERVICES_CATALOG = [
        "create a branded flyer summarizing the buyer's purchase criteria",
        "post the buyer's criteria on craigslist under \"real estate wanted – land\"",
        "share the buyer's criteria on nextdoor in neighborhood or rural groups",
        "promote the buyer's criteria on facebook in land buyers, developers, or homesteader groups",
        "share the buyer's criteria on instagram using posts, stories, or reels",
        "promote the buyer's criteria on linkedin in land acquisition or investment groups",
        "upload a tiktok video summarizing the buyer's purchase criteria",
        "upload a youtube video summarizing the buyer's purchase criteria",
        "launch a mass email campaign promoting the buyer's purchase criteria",
        "distribute branded postcards or flyers in the buyer's preferred neighborhoods",
        "launch hyperlocal digital ads targeting the buyer's preferred purchase areas",
        "send property alerts for land listings that match the buyer's goals from mls and land-specific platforms",
        "search for off-market, pre-market, distressed, withdrawn, canceled, or expired properties that meet the buyer's purchase criteria",
        "communicate with the seller's agent or seller to confirm zoning, access, utilities, and pricing",
        "assess development feasibility, land use restrictions, or agricultural potential (non-legal advice)",
        "schedule and attend land visits with the buyer",
        "coordinate or conduct virtual walkthroughs using maps, aerials, and site photos",
        "preview parcels on behalf of the buyer upon request",
        "provide observations on topography, road frontage, and surrounding land uses",
        "draft and submit offers using state-approved purchase forms",
        "provide the buyer with required state or local disclosure forms",
        "draft and deliver counteroffers and manage revisions to the purchase agreement",
        "negotiate price, deposits, and contingencies (as permitted under the agency agreement)",
        "manage communication with the seller's agent or seller",
        "assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed documents to all parties",
        "assist with due diligence coordination, including survey review, soil testing, zoning checks, and permit verification (non-legal guidance only)",
        "monitor contract milestones, contingency deadlines, and financing timelines",
        "provide referrals to attorneys, title companies, escrow officers, surveyors, or land use consultants (referrals only — no endorsement or warranty is made)",
        "coordinate surveys, appraisals, inspections, and environmental assessments",
        "coordinate with the lender, title company, escrow officer, and/or attorney to prepare for closing",
        "review the settlement statement for accuracy and coordinate with all parties if corrections are needed (no legal or financial advice provided)",
        "confirm delivery of final executed documents, wire instructions, and closing paperwork to all relevant parties",
        "schedule and confirm the final walkthrough",
        "schedule and confirm the closing appointment",
        "provide a comparative market analysis (cma) based on recent land sales, acreage comps, and price-per-acre benchmarks (for informational purposes only — not a formal appraisal)",
        "answer general questions about zoning, utilities, development potential, and environmental constraints (non-legal guidance only)",
        "provide factual data on flood zones, wetlands, and land use maps using third-party sources (no legal or engineering advice)",
        "offer general guidance on feasibility timelines, inspection steps, and rural financing considerations (non-legal advice only)",
    ];

    /**
     * Logical field groups for match scoring.
     * Each group = ONE logical decision (contributes 1 to denominator).
     */
    const LOGICAL_FIELD_GROUPS = [
        // 1. Commission Structure
        [
            'key'    => 'commission_structure',
            'fields' => ['commission_structure'],
        ],

        // 2. Purchase Fee — type + sub-value = ONE logical decision
        [
            'key'    => 'purchase_fee_type',
            'fields' => [
                'purchase_fee_type',
                'purchase_fee_flat',
                'purchase_fee_percentage',
                'purchase_fee_flat_combo',
                'purchase_fee_percentage_combo',
                'purchase_fee_other',
            ],
        ],

        // 3. Interested in a Lease-Option (Yes/No parent)
        [
            'key'    => 'interested_lease_option',
            'fields' => ['interested_lease_option'],
        ],

        // 4. Lease Fee — only when both parties indicate lease option interest
        [
            'key'    => 'lease_fee_type',
            'fields' => [
                'lease_fee_type',
                'lease_fee_flat',
                'lease_fee_percentage',
                'lease_fee_percentage_monthly_rent',
                'lease_fee_percentage_monthly_number',
                'lease_fee_flat_combo',
                'lease_fee_percentage_combo',
                'lease_fee_percentage_net',
                'lease_fee_flat_combo_net',
                'lease_fee_percentage_combo_net',
                'lease_fee_other',
            ],
            'baseline_active_when' => ['interested_lease_option' => 'Yes'],
            'bid_active_when'      => ['interested_lease_option' => 'Yes'],
        ],

        // 5. Interested in a Lease-Option Agreement (parent)
        [
            'key'    => 'interested_lease_option_agreement',
            'fields' => ['interested_lease_option_agreement'],
        ],

        // 6. Lease-Option Creation Compensation — type + value = ONE
        [
            'key'    => 'lease_type',
            'fields' => ['lease_type', 'lease_value'],
            'baseline_active_when' => ['interested_lease_option_agreement' => 'Yes'],
            'bid_active_when'      => ['interested_lease_option_agreement' => 'Yes'],
        ],

        // 7. Purchase Option Compensation — type + value = ONE
        [
            'key'    => 'purchase_type',
            'fields' => ['purchase_type', 'purchase_value'],
            'baseline_active_when' => ['interested_lease_option_agreement' => 'Yes'],
            'bid_active_when'      => ['interested_lease_option_agreement' => 'Yes'],
        ],

        // 8. Protection Period Timeframe
        [
            'key'    => 'protection_period',
            'fields' => ['protection_period'],
        ],

        // 9. Early Termination Fee (Yes/No parent)
        [
            'key'    => 'early_termination_fee_option',
            'fields' => ['early_termination_fee_option'],
        ],

        // 10. Termination Fee Amount — only when parent = Yes in BOTH baseline and bid
        [
            'key'    => 'early_termination_fee_amount',
            'fields' => ['early_termination_fee_amount'],
            'baseline_active_when' => ['early_termination_fee_option' => 'Yes'],
            'bid_active_when'      => ['early_termination_fee_option' => 'Yes'],
        ],

        // 11. Retainer Fee (Yes/No parent)
        [
            'key'    => 'retainer_fee_option',
            'fields' => ['retainer_fee_option'],
        ],

        // 12. Retainer Fee Amount — only when parent = Yes
        [
            'key'    => 'retainer_fee_amount',
            'fields' => ['retainer_fee_amount'],
            'baseline_active_when' => ['retainer_fee_option' => 'Yes'],
            'bid_active_when'      => ['retainer_fee_option' => 'Yes'],
        ],

        // 13. Retainer Fee Application — only when parent = Yes
        [
            'key'    => 'retainer_fee_application',
            'fields' => ['retainer_fee_application'],
            'baseline_active_when' => ['retainer_fee_option' => 'Yes'],
            'bid_active_when'      => ['retainer_fee_option' => 'Yes'],
        ],

        // 14. Agency Agreement Timeframe
        [
            'key'    => 'agency_agreement_timeframe',
            'fields' => ['agency_agreement_timeframe'],
        ],

        // 15. Brokerage Relationship
        [
            'key'    => 'brokerage_relationship',
            'fields' => ['brokerage_relationship'],
        ],

        // 16. Referral Fee (%) — only present on agent-created listings
        [
            'key'    => 'referral_fee_percent',
            'fields' => ['referral_fee_percent'],
        ],
    ];

    /**
     * Normalize a scalar value for comparison (strips whitespace, $, %, commas).
     */
    public static function normalizeForMatch($v): string
    {
        if (is_null($v) || $v === '') return '';
        if (is_array($v) || is_object($v)) return json_encode($v);
        $v = trim((string) $v);
        $v = preg_replace('/[\x{2018}\x{2019}]/u', "'", $v);
        $v = preg_replace('/[\x{201C}\x{201D}]/u', '"', $v);
        return preg_replace('/[\s$,%]/', '', strtolower($v));
    }

    /**
     * Parse services + other_services from a data array into a flat, normalized list.
     * If $catalog is provided, only standard services in the catalog are kept.
     */
    public static function parseServices(array $data, ?array $catalog = null): array
    {
        $services = $data['services'] ?? [];
        if (is_string($services)) $services = json_decode($services, true) ?? [];
        $services = is_array($services) ? array_values(array_filter($services)) : [];

        $other = $data['other_services'] ?? [];
        if (is_string($other)) $other = json_decode($other, true) ?? [];
        $other = is_array($other)
            ? array_values(array_filter($other, fn($s) => is_string($s) && !empty(trim($s))))
            : [];

        $servicesNorm = array_map([self::class, 'normalizeService'], $services);
        if ($catalog !== null) {
            $resolved = [];
            foreach ($servicesNorm as $s) {
                if (self::matchesCatalog($s, $catalog)) {
                    $resolved[] = self::resolveToCanonical($s, $catalog);
                }
            }
            $servicesNorm = $resolved;
        }
        $servicesNorm = array_unique($servicesNorm);

        $otherNorm = array_values(array_filter(
            array_map([self::class, 'normalizeService'], $other),
            fn($s) => $s !== ''
        ));

        return array_values(array_unique(array_merge($servicesNorm, $otherNorm)));
    }

    /**
     * Build a normalized composite value from a list of DB field names.
     */
    public static function buildCompositeValue(array $data, array $fields): string
    {
        $parts = [];
        foreach ($fields as $f) {
            $v = $data[$f] ?? null;
            if (self::isFilled($v)) {
                $parts[] = self::normalizeForMatch($v);
            }
        }
        return implode('|', $parts);
    }

    /**
     * Check whether a logical group's condition is satisfied for a given data array.
     */
    private static function checkGroupCondition(array $conditionMap, array $data): bool
    {
        foreach ($conditionMap as $condField => $condValue) {
            if (strtolower((string) ($data[$condField] ?? '')) !== strtolower((string) $condValue)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Whether a value is considered "filled" (not blank).
     */
    private static function isFilled($v): bool
    {
        if (is_null($v)) return false;
        if (is_bool($v)) return $v;
        if (is_array($v)) return !empty($v);
        return $v !== '';
    }

    /**
     * Calculate baseline-driven match score for buyer agent bids.
     *
     * @param array       $baselineData   The source-of-truth (listing data or counter)
     * @param array       $comparedData   The bid/counter data being evaluated
     * @param array|null  $brokerFields   Ignored (kept for signature compat)
     * @param string|null $propertyType   When provided, services are filtered to buyer catalog
     * @return array  Rich result object
     */
    public static function calculate(
        array $baselineData,
        array $comparedData,
        ?array $brokerFields = null,
        ?string $propertyType = null
    ): array {
        $catalog = ($propertyType !== null) ? self::getCatalog($propertyType) : null;

        $matchedTerms      = [];
        $changedTerms      = [];
        $addedByAgentTerms = [];

        $groupMatchCount   = 0;
        $groupChangedCount = 0;
        $groupAddedCount   = 0;
        $groupTotalCount   = 0;

        foreach (self::LOGICAL_FIELD_GROUPS as $group) {
            $key    = $group['key'];
            $fields = $group['fields'];

            if (isset($group['baseline_active_when'])) {
                if (!self::checkGroupCondition($group['baseline_active_when'], $baselineData)) {
                    continue;
                }
            }

            $bComposite = self::buildCompositeValue($baselineData, $fields);
            $cComposite = self::buildCompositeValue($comparedData, $fields);

            if (!self::isFilled($bComposite)) {
                if (self::isFilled($cComposite)) {
                    $groupAddedCount++;
                    $addedByAgentTerms[$key] = ['baseline' => null, 'compared' => $cComposite];
                    foreach ($fields as $f) {
                        if ($f !== $key) {
                            $cv = $comparedData[$f] ?? null;
                            if (self::isFilled($cv)) {
                                $addedByAgentTerms[$f] = ['baseline' => $baselineData[$f] ?? null, 'compared' => $cv];
                            }
                        }
                    }
                }
                continue;
            }

            if (isset($group['bid_active_when'])) {
                if (!self::checkGroupCondition($group['bid_active_when'], $comparedData)) {
                    continue;
                }
            }

            $groupTotalCount++;

            if (self::normalizeForMatch($cComposite) === self::normalizeForMatch($bComposite)) {
                $groupMatchCount++;
                $matchedTerms[$key] = ['baseline' => $bComposite, 'compared' => $cComposite];
                foreach ($fields as $f) {
                    if ($f !== $key) {
                        $matchedTerms[$f] = [
                            'baseline' => $baselineData[$f] ?? null,
                            'compared' => $comparedData[$f] ?? null,
                        ];
                    }
                }
            } else {
                $groupChangedCount++;
                $changedTerms[$key] = ['baseline' => $bComposite, 'compared' => $cComposite];
                foreach ($fields as $f) {
                    if ($f !== $key) {
                        $changedTerms[$f] = [
                            'baseline' => $baselineData[$f] ?? null,
                            'compared' => $comparedData[$f] ?? null,
                        ];
                    }
                }
            }
        }

        $termsBaselineTotal = $groupTotalCount;
        $termsMatchedCount  = $groupMatchCount;
        $termsChangedCount  = $groupChangedCount;
        $termsAddedCount    = $groupAddedCount;
        $termsMatchPercent  = $termsBaselineTotal > 0
            ? (int) round(($termsMatchedCount / $termsBaselineTotal) * 100)
            : 100;

        $baselineNorm  = self::parseServices($baselineData, $catalog);
        $comparedNorm  = self::parseServices($comparedData, $catalog);

        $matchedServices = [];
        $missingServices = [];
        $extraServices   = [];

        foreach ($baselineNorm as $svc) {
            if (in_array($svc, $comparedNorm, true)) {
                $matchedServices[] = $svc;
            } else {
                $missingServices[] = $svc;
            }
        }
        foreach ($comparedNorm as $svc) {
            if (!in_array($svc, $baselineNorm, true)) {
                $extraServices[] = $svc;
            }
        }

        $servicesBaselineTotal = count($matchedServices) + count($missingServices);
        $servicesMatchedCount  = count($matchedServices);
        $servicesMissingCount  = count($missingServices);
        $servicesExtraCount    = count($extraServices);
        $servicesMatchPercent  = $servicesBaselineTotal > 0
            ? (int) round(($servicesMatchedCount / $servicesBaselineTotal) * 100)
            : 100;

        $hasTerms    = $termsBaselineTotal > 0;
        $hasServices = $servicesBaselineTotal > 0;

        if ($hasTerms && $hasServices) {
            $overallPercent = (int) round(($termsMatchPercent + $servicesMatchPercent) / 2);
        } elseif ($hasTerms) {
            $overallPercent = $termsMatchPercent;
        } elseif ($hasServices) {
            $overallPercent = $servicesMatchPercent;
        } else {
            $overallPercent = 100;
        }

        return [
            'overall_percent'         => $overallPercent,
            'terms_baseline_total'    => $termsBaselineTotal,
            'terms_matched_count'     => $termsMatchedCount,
            'terms_changed_count'     => $termsChangedCount,
            'terms_added_count'       => $termsAddedCount,
            'terms_match_percent'     => $termsMatchPercent,
            'matched_terms'           => $matchedTerms,
            'changed_terms'           => $changedTerms,
            'added_terms'             => $addedByAgentTerms,
            'services_baseline_total' => $servicesBaselineTotal,
            'services_matched_count'  => $servicesMatchedCount,
            'services_missing_count'  => $servicesMissingCount,
            'services_extra_count'    => $servicesExtraCount,
            'services_match_percent'  => $servicesMatchPercent,
            'matched_services'        => $matchedServices,
            'missing_services'        => $missingServices,
            'extra_services'          => $extraServices,
            'broker_comp_percent'     => $termsMatchPercent,
            'broker_comp_matched'     => $termsMatchedCount,
            'broker_comp_total'       => $termsBaselineTotal,
            'services_percent'        => $servicesMatchPercent,
            'services_matched'        => $servicesMatchedCount,
            'services_total'          => $servicesBaselineTotal,
        ];
    }

    /**
     * Return a CSS color string for a given score percentage.
     */
    public static function scoreColor(int $score): string
    {
        if ($score >= 80) return '#28a745';
        if ($score >= 50) return '#ffc107';
        return '#dc3545';
    }

    /**
     * Return the normalized Buyer-only services catalog for a given property type.
     * All returned strings are already normalized (lowercase, straight apostrophes, trimmed).
     */
    public static function getCatalog(string $propertyType): array
    {
        $pt = strtolower(trim($propertyType));

        if (str_contains($pt, 'commercial')) {
            return self::COMMERCIAL_SERVICES_CATALOG;
        }
        if (str_contains($pt, 'income')) {
            return self::INCOME_SERVICES_CATALOG;
        }
        if (str_contains($pt, 'business')) {
            return self::BUSINESS_SERVICES_CATALOG;
        }
        if (str_contains($pt, 'vacant') || str_contains($pt, 'land')) {
            return self::VACANT_LAND_SERVICES_CATALOG;
        }

        return self::RESIDENTIAL_SERVICES_CATALOG;
    }

    /**
     * Normalize a service string (curly quotes → straight, lowercase, trim).
     */
    public static function normalizeService(string $s): string
    {
        $s = preg_replace('/[\x{2018}\x{2019}]/u', "'", $s);
        $s = preg_replace('/[\x{201C}\x{201D}]/u', '"', $s);
        return strtolower(trim($s));
    }

    /**
     * Aliases map: normalized old listing-form text → normalized canonical catalog text.
     * Handles cases where the listing form historically stored shorter or differently-worded
     * service strings that are semantically equivalent to the bid form / catalog entries.
     */
    private const SERVICE_ALIASES = [
        // Residential — guidance services
        "provide general guidance on financing, loan options, property taxes, insurance, and escrow timelines"
            => "answer general questions about financing, loan options, property taxes, insurance, and escrow timelines (non-legal guidance)",
        "provide general guidance on inspection expectations, common repair requests, and contingency planning"
            => "offer general guidance on inspection expectations, common repair requests, and contingency planning during the offer process (non-legal advice)",
        // Residential — closing
        "coordinate scheduling for inspections, appraisals, and other requested evaluations"
            => "coordinate inspections, appraisals, and lease audits (if applicable)",
        // Income — guidance
        "provide general guidance on financing options, rent control, property taxes, and landlord responsibilities"
            => "answer general questions about financing options, rent control, property taxes, and landlord responsibilities",
        "provide general guidance on due diligence steps, lease audits, and estoppel reviews"
            => "offer general guidance on due diligence steps, lease audits, and estoppel reviews (non-legal advice)",
        // Income — factual data (space difference)
        "provide factual information on rental demand, turnover rates, and submarket conditions using third-party sources"
            => "provide factual information on rental demand, turnover rates, and sub market conditions using third-party sources",
        // Commercial — guidance
        "provide general guidance on zoning regulations, permitted uses, and rental income potential"
            => "answer general questions about zoning regulations, permitted uses, and rental income potential",
        "provide general guidance on lease types, contingency timelines, due diligence, and environmental risks"
            => "offer general guidance on lease types, contingency timelines, due diligence, and environmental risks (non-legal advice only)",
        // Business — guidance
        "provide general guidance on licensing, zoning, sba financing, registration steps, and transition timing"
            => "answer general questions about licensing, zoning, sba financing, registration steps, and transition timing (non-legal guidance)",
        "provide general guidance on due diligence preparation, key documents to review, and red flags during the acquisition process"
            => "offer general guidance on due diligence preparation, key documents to review, and red flags during the acquisition process (non-legal advice only)",
        // Vacant Land — property evaluation
        "evaluate development feasibility, land use restrictions, and agricultural potential with the buyer"
            => "assess development feasibility, land use restrictions, or agricultural potential (non-legal advice)",
        // Vacant Land — CMA (word-order and preposition differ, not just parenthetical)
        "provide a comparative market analysis (cma) with acreage comps, recent land sales, and price-per-acre benchmarks"
            => "provide a comparative market analysis (cma) based on recent land sales, acreage comps, and price-per-acre benchmarks (for informational purposes only — not a formal appraisal)",
        // Vacant Land — guidance
        "provide general guidance on zoning, utilities, development potential, and environmental constraints"
            => "answer general questions about zoning, utilities, development potential, and environmental constraints (non-legal guidance only)",
        "provide general guidance on feasibility timelines, inspection steps, and rural financing considerations"
            => "offer general guidance on feasibility timelines, inspection steps, and rural financing considerations (non-legal advice only)",
    ];

    /**
     * Resolve a normalized service string through aliases, then check catalog membership.
     * Accepts:
     *   1. Exact catalog match
     *   2. Alias → canonical catalog match
     *   3. Prefix match: service is a leading substring of a catalog entry (handles services
     *      stored without trailing legal disclaimers/parentheticals)
     */
    private static function matchesCatalog(string $normalizedService, array $catalog): bool
    {
        // 1. Exact match
        if (in_array($normalizedService, $catalog, true)) {
            return true;
        }

        // 2. Alias map match
        $canonical = self::SERVICE_ALIASES[$normalizedService] ?? null;
        if ($canonical !== null && in_array($canonical, $catalog, true)) {
            return true;
        }

        // 3. Prefix match: the stored service is the beginning of a catalog entry
        //    (handles services stored without the trailing parenthetical disclaimer)
        foreach ($catalog as $catEntry) {
            if (str_starts_with($catEntry, $normalizedService)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve a normalized service to its canonical catalog form (for consistent comparison).
     * If the service matches via alias or prefix, return the canonical catalog string.
     * Otherwise return the original normalized string.
     */
    private static function resolveToCanonical(string $normalizedService, array $catalog): string
    {
        // Exact match — already canonical
        if (in_array($normalizedService, $catalog, true)) {
            return $normalizedService;
        }

        // Alias match
        $canonical = self::SERVICE_ALIASES[$normalizedService] ?? null;
        if ($canonical !== null && in_array($canonical, $catalog, true)) {
            return $canonical;
        }

        // Prefix match — find the full catalog entry
        foreach ($catalog as $catEntry) {
            if (str_starts_with($catEntry, $normalizedService)) {
                return $catEntry;
            }
        }

        return $normalizedService;
    }
}
