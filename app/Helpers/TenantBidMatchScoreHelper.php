<?php

namespace App\Helpers;

/**
 * TenantBidMatchScoreHelper
 *
 * Provides a single, deterministic, baseline-driven match score calculation
 * for Hire a Tenant's Agent bid comparisons. Used by:
 *   - hire_tenant_agent/view.blade.php (bid card, Private Data Modal, Limited Bid Modal)
 *   - hire_tenant_agent/view_counter_terms.blade.php
 *   - App\Services\CompetingBidsService
 *
 * SCORING RULES
 * =============
 * Services:
 *   baseline_total = count(baseline services filtered to valid Tenant catalog)
 *   matched        = baseline items also in compared    // Agent kept them
 *   missing        = baseline items not in compared     // Agent removed them
 *   extra          = compared items not in baseline     // Agent added — NOT in denominator
 *   services_match_percent = matched / baseline_total * 100
 *
 * Terms:
 *   baseline_total = count(fields where baseline value is filled)
 *   matched        = baseline-filled fields with same normalized value in compared
 *   changed        = baseline-filled fields with different value in compared (includes blank compared)
 *   added_by_agent = compared-filled fields where baseline was blank — NOT in denominator
 *   terms_match_percent = matched / baseline_total * 100
 *
 * Overall = 50 % Services + 50 % Terms (only non-zero components averaged)
 *
 * CATALOG FILTERING
 * =================
 * All services are filtered against the valid Tenant-only catalog before scoring.
 * This prevents Buyer, Seller, or Landlord services that may have been stored in
 * the database from contaminating the Tenant match score or comparison output.
 * Pass $propertyType ('Residential Property' or 'Commercial Property') to activate.
 * If $propertyType is null, no catalog filter is applied (backward-compatible).
 */
class TenantBidMatchScoreHelper
{
    /**
     * All candidate broker/terms fields.
     * NOTE: getActiveFields() filters this list per-calculation based on which parent
     * conditions are active in the baseline data.  Sub-fields whose parent is inactive
     * are excluded so they never inflate or deflate the denominator spuriously.
     *
     * REMOVED from previous version:
     *   - broker_fee_timing     (duplicate alias of payment_timing)
     *   - broker_fee_days_from_rent (duplicate alias of days_to_pay)
     *   - flat_fee_amount, percent_gross_lease, purchase_flat_fee_amount,
     *     purchase_percent_value  (legacy field names not used in current forms)
     */
    const BROKER_FIELDS = [
        // Top-level compensation terms
        'commission_structure',
        'lease_fee_type',
        'payment_timing',
        'days_to_pay',
        // Purchase fee (parent + conditional sub-fields)
        'interested_purchase_fee_type',
        'purchase_fee_type',
        'purchase_fee_flat',
        'purchase_fee_percentage',
        'purchase_fee_flat_combo',
        'purchase_fee_percentage_combo',
        'purchase_fee_other',
        // Lease-option (parent + conditional sub-fields)
        'interested_lease_option_agreement',
        'lease_type',
        'lease_value',
        'purchase_type',
        'purchase_value',
        // Legal terms
        'protection_period',
        'early_termination_fee_option',
        'early_termination_fee_amount',
        'retainer_fee_option',
        'retainer_fee_amount',
        'retainer_fee_application',
        'agency_agreement_timeframe',
        'brokerage_relationship',
        // Lease fee sub-fields (conditional on lease_fee_type)
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
    ];

    /**
     * Complete Residential Tenant services catalog.
     * Source of truth: resources/views/livewire/tenant-agent-auction-bid-tabs/commission-based/services.blade.php
     * Stored with straight apostrophes; normalizeService() makes both sides match before comparison.
     */
    const RESIDENTIAL_SERVICES_CATALOG = [
        // Tenant Criteria Marketing & Promotion
        "create a branded flyer summarizing the tenant's rental criteria",
        "post the tenant's rental criteria on craigslist under the \"real estate wanted\" section",
        "share the tenant's rental criteria on nextdoor in neighborhood or community groups",
        "promote the tenant's rental criteria on facebook in rental or housing groups",
        "share the tenant's rental criteria on instagram using posts, stories, or reels",
        "promote the tenant's rental criteria on linkedin in real estate or housing groups",
        "upload a tiktok video summarizing the tenant's rental criteria",
        "upload a youtube video summarizing the tenant's rental criteria",
        "launch a mass email campaign promoting the tenant's rental criteria",
        "distribute branded postcards or flyers in the tenant's preferred neighborhoods",
        "launch hyperlocal digital ads targeting the tenant's preferred rental areas",
        // Property Search, Alerts & Matching
        "send email alerts with new listings from the mls that match the tenant's rental criteria",
        "search for off-market, pre-market, withdrawn, canceled, or expired properties that meet the tenant's rental criteria",
        "communicate with the landlord's agent, landlord, or property manager to confirm availability, lease terms, and showing instructions",
        "evaluate properties with the tenant and provide insights on pricing, lease terms, and overall fit",
        // Property Showings & Virtual Tours
        "schedule and attend property showings with the tenant",
        "coordinate or conduct virtual showings via live video or pre-recorded walkthroughs",
        "preview properties on behalf of the tenant upon request",
        "provide factual observations on property layout and condition",
        // Tenant Application Support
        "provide the tenant with application instructions or links to an online rental application platform",
        "gather and organize required supporting documents (e.g., identification, income verification, reference letters)",
        "submit complete and organized application packages to the landlord's agent, landlord, or property manager for review",
        "answer questions about the application process, screening timelines, and required documentation",
        // Lease Preparation & Execution
        "review lease offers and assist the tenant in preparing questions or requested changes",
        "coordinate lease negotiation with the landlord's agent, landlord, or property manager",
        "assist with completing required lease disclosures and reviewing key lease terms",
        "assist with in-person or electronic lease signing, including e-signature setup and secure delivery of executed lease documents, addenda, and disclosures to all parties",
        // Move-In Support & Coordination
        "coordinate move-in date and key handoff logistics with the landlord's agent, landlord or property manager",
        "confirm completion of any agreed-upon pre-move-in cleaning or repairs",
        "provide a utility setup checklist and local provider resources",
        "share a move-in checklist for documentation and property condition review",
        "confirm required move-in payments and assist the tenant with tracking amounts due, deadlines, and accepted payment methods",
        // Leasing Strategy & Guidance
        "provide a rental market analysis (rma) with pricing insights based on comparable rentals, neighborhood trends, and current market conditions",
        "advise on lease types and structures (e.g., month-to-month, annual, furnished, lease-option)",
        "provide general guidance on tenant rights and landlord responsibilities under state law",
        "provide general guidance on lease clauses, payment terms, and renewal options",
    ];

    /**
     * Complete Commercial Tenant services catalog.
     * Source of truth: resources/views/livewire/tenant-agent-auction-bid-tabs/commission-based/services.blade.php
     */
    const COMMERCIAL_SERVICES_CATALOG = [
        // Tenant Criteria Marketing & Promotion
        "create a branded flyer summarizing the tenant's leasing criteria",
        "post the tenant's leasing criteria on craigslist under the \"office/commercial\" or \"retail\" section",
        "promote the tenant's leasing criteria on facebook in commercial leasing or business groups",
        "share the tenant's leasing criteria on instagram using posts, stories, or reels",
        "promote the tenant's leasing criteria on linkedin in professional, real estate, or commercial investment groups",
        "upload a tiktok video summarizing the tenant's leasing criteria",
        "upload a youtube video summarizing the tenant's leasing criteria",
        "launch a mass email campaign promoting the tenant's leasing criteria",
        "distribute branded postcards or flyers in the tenant's preferred neighborhoods",
        "launch hyperlocal digital ads targeting the tenant's preferred leasing areas",
        // Property Search, Alerts & Matching
        "send listing alerts from real estate platforms that match the tenant's leasing criteria",
        "search for off-market, pre-market, withdrawn, canceled, or expired properties that meet the tenant's rental criteria",
        "communicate with the landlord's agent, landlord, or property manager to confirm availability, lease terms, and showing instructions",
        "evaluate properties for layout efficiency, building specs, logistics, zoning fit, and operational alignment",
        // Property Showings & Virtual Tours
        "schedule and attend property tours with the tenant",
        "coordinate or conduct virtual showings via live video or pre-recorded walkthroughs",
        "preview properties on behalf of the tenant upon request",
        "provide factual notes on layout, access, parking, visibility, and other operational considerations",
        // Tenant Application Support
        "provide the tenant with application instructions or links to online platforms",
        "gather and organize required supporting documents (e.g., business licenses, financials, references)",
        "submit complete and organized application packages to the landlord's agent, landlord, or property manager",
        // Lease Preparation, LOI & Execution
        "draft or assist with preparing a letter of intent (loi) summarizing the tenant's business needs and proposed terms",
        "assist with negotiating rent, cam, lease term, ti allowance, exclusivity clauses, renewal options, and other provisions (as permitted under the agency agreement)",
        "coordinate with the landlord's agent, landlord or property manager to finalize lease terms",
        "review lease drafts and coordinate revisions through appropriate channels",
        "assist with in-person or electronic lease signing, including e-signature setup and secure delivery of executed lease documents, addenda, and disclosures to all parties",
        "track required deposits, rent commencement, and key lease dates to ensure move-in readiness",
        // Move-In Support & Coordination
        "coordinate move-in date and key handoff logistics with the landlord, landlord's agent, or property manager",
        "confirm completion of any agreed-upon pre-move-in repairs, cleaning, or buildout",
        "provide a utility setup checklist and local provider resources",
        "share a move-in checklist for documentation and property condition review",
        "confirm required move-in payments and assist the tenant with tracking amounts due, deadlines, and accepted payment methods",
        // Leasing Strategy & Guidance
        "provide a comparative lease market analysis (clma) with pricing insights, comps, and vacancy trends",
        "advise on lease types and structures (e.g., nnn, modified gross, full service) with general explanations of differences",
        "provide general guidance on tenant rights and landlord responsibilities under commercial leasing law",
        "provide general guidance on lease clauses, escalation terms, and space usage considerations",
    ];

    /**
     * Return the normalized Tenant-only services catalog for a given property type.
     * All returned strings are already normalized (lowercase, straight apostrophes, trimmed).
     * Defaults to Residential when the property type is unknown.
     */
    public static function getCatalog(string $propertyType): array
    {
        $rawCatalog = str_contains(strtolower($propertyType), 'commercial')
            ? self::COMMERCIAL_SERVICES_CATALOG
            : self::RESIDENTIAL_SERVICES_CATALOG;

        // Catalog entries are already lowercase + straight-apostrophe; normalizeService is applied
        // here for safety in case the constant is ever edited with curly quotes.
        return array_map([self::class, 'normalizeService'], $rawCatalog);
    }

    /**
     * Normalize a scalar value for comparison (strips whitespace, $, %, commas).
     */
    public static function normalizeForMatch($v): string
    {
        if (is_null($v) || $v === '') return '';
        if (is_array($v) || is_object($v)) return json_encode($v);
        $v = trim((string) $v);
        return preg_replace('/[\s$,%]/', '', strtolower($v));
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
     * Parse services + other_services from a data array into a flat, normalized list.
     * If $catalog is provided, only services whose normalized form appears in the catalog
     * are kept. This prevents wrong-role services (e.g., Buyer purchase services) from
     * contaminating Tenant scoring when both were accidentally stored together.
     *
     * @param array      $data    Data array containing 'services' and 'other_services' keys
     * @param array|null $catalog Normalized valid-service strings (from getCatalog()). When
     *                            provided, only catalog-matching services are returned.
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

        $all = array_merge($services, $other);
        $normalized = array_unique(array_map([self::class, 'normalizeService'], $all));

        // Catalog filter: discard any service that does not belong to the valid Tenant catalog.
        // This removes Buyer, Seller, or Landlord services that were accidentally stored together
        // with Tenant services in the same JSON array.
        if ($catalog !== null) {
            $normalized = array_values(array_filter(
                $normalized,
                fn($s) => in_array($s, $catalog, true)
            ));
        }

        return $normalized;
    }

    /**
     * Filter the broker fields list to only those that are "active" given the baseline data.
     * Sub-fields whose parent condition is not met in the baseline are excluded so they cannot
     * inflate the denominator with stale or irrelevant values.
     *
     * Rules applied:
     *  - Lease fee sub-fields: only the sub-field matching the selected lease_fee_type counts.
     *  - Purchase fee sub-fields: only when interested_purchase_fee_type === 'Yes' AND the
     *    matching purchase_fee_type variant is selected.
     *  - Lease-option sub-fields: only when interested_lease_option_agreement === 'Yes'.
     *  - Early termination amount: only when early_termination_fee_option === 'Yes'.
     *  - Retainer sub-fields: only when retainer_fee_option === 'Yes'.
     *  - days_to_pay: only when payment_timing contains a day-count variant.
     *
     * @param array $baselineData  The baseline data array (used to check parent conditions).
     * @param array $fields        Full field list to filter.
     * @return array               Filtered field list safe to use as the scoring denominator.
     */
    public static function getActiveFields(array $baselineData, array $fields): array
    {
        $b = $baselineData;

        // Resolve helpers
        $leaseFeeType    = $b['lease_fee_type'] ?? '';
        $purchaseParent  = $b['interested_purchase_fee_type'] ?? '';
        $purchaseFeeType = $b['purchase_fee_type'] ?? '';
        $leaseOption     = $b['interested_lease_option_agreement'] ?? '';
        $earlyTerm       = $b['early_termination_fee_option'] ?? '';
        $retainer        = $b['retainer_fee_option'] ?? '';
        $payTiming       = strtolower($b['payment_timing'] ?? '');

        $isPurchaseYes   = $purchaseParent === 'Yes';
        $isLeaseOptionYes = $leaseOption === 'Yes';
        $isEarlyTermYes  = $earlyTerm === 'Yes';
        $isRetainerYes   = $retainer === 'Yes';
        $payTimingHasDays = str_contains($payTiming, 'day');

        // Lease fee type mapping: type string → which sub-fields are active
        $leaseFlatTypes     = ['Flat Fee'];
        $leasePctTypes      = ['Percentage of the Gross Lease Value'];
        $leaseMonthlyTypes  = ['Percentage of Monthly Rent'];
        $leaseComboTypes    = ['Flat Fee + Percentage of the Gross Lease Value'];
        $leaseNetTypes      = ['Percentage of Net Aggregate Rent'];
        $leaseComboNetTypes = ['Flat Fee + Percentage of Net Aggregate Rent'];
        $leaseOtherTypes    = ['Other'];

        // Purchase fee type mapping
        $purchaseFlatType   = 'Flat Fee';
        $purchasePctType    = 'Percentage of the Total Purchase Price';
        $purchaseComboTypes = [
            'Percentage of the Total Purchase Price + Flat Fee',
            'Flat Fee + Percentage of the Total Purchase Price',
        ];
        $purchaseOtherType  = 'other';

        $conditionalRules = [
            // Lease fee sub-fields
            'lease_fee_flat'                  => in_array($leaseFeeType, $leaseFlatTypes, true),
            'lease_fee_percentage'            => in_array($leaseFeeType, $leasePctTypes, true),
            'lease_fee_percentage_monthly_rent'   => in_array($leaseFeeType, $leaseMonthlyTypes, true),
            'lease_fee_percentage_monthly_number' => in_array($leaseFeeType, $leaseMonthlyTypes, true),
            'lease_fee_flat_combo'            => in_array($leaseFeeType, array_merge($leaseComboTypes, $leaseComboNetTypes), true),
            'lease_fee_percentage_combo'      => in_array($leaseFeeType, $leaseComboTypes, true),
            'lease_fee_percentage_net'        => in_array($leaseFeeType, array_merge($leaseNetTypes, $leaseComboNetTypes), true),
            'lease_fee_flat_combo_net'        => in_array($leaseFeeType, $leaseComboNetTypes, true),
            'lease_fee_percentage_combo_net'  => in_array($leaseFeeType, $leaseComboNetTypes, true),
            'lease_fee_other'                 => in_array($leaseFeeType, $leaseOtherTypes, true),
            // Purchase fee sub-fields
            'purchase_fee_type'               => $isPurchaseYes,
            'purchase_fee_flat'               => $isPurchaseYes && $purchaseFeeType === $purchaseFlatType,
            'purchase_fee_percentage'         => $isPurchaseYes && $purchaseFeeType === $purchasePctType,
            'purchase_fee_flat_combo'         => $isPurchaseYes && in_array($purchaseFeeType, $purchaseComboTypes, true),
            'purchase_fee_percentage_combo'   => $isPurchaseYes && in_array($purchaseFeeType, $purchaseComboTypes, true),
            'purchase_fee_other'              => $isPurchaseYes && $purchaseFeeType === $purchaseOtherType,
            // Lease-option sub-fields
            'lease_type'                      => $isLeaseOptionYes,
            'lease_value'                     => $isLeaseOptionYes,
            'purchase_type'                   => $isLeaseOptionYes,
            'purchase_value'                  => $isLeaseOptionYes,
            // Legal term sub-fields
            'early_termination_fee_amount'    => $isEarlyTermYes,
            'retainer_fee_amount'             => $isRetainerYes,
            'retainer_fee_application'        => $isRetainerYes,
            // Payment timing sub-fields
            'days_to_pay'                     => $payTimingHasDays,
        ];

        return array_values(array_filter($fields, function (string $field) use ($conditionalRules) {
            // If a conditional rule exists for this field, it must be true to include it
            if (array_key_exists($field, $conditionalRules)) {
                return $conditionalRules[$field];
            }
            // No rule → always include (top-level parent fields)
            return true;
        }));
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
     * Calculate baseline-driven match score.
     *
     * @param array       $baselineData   The source-of-truth (Tenant's listing / counter / viewer's bid)
     * @param array       $comparedData   The data being evaluated (Agent's bid / counter / competing bid)
     * @param array|null  $brokerFields   Override the default broker fields list
     * @param string|null $propertyType   When provided, services are filtered to the Tenant-only catalog
     *                                    for this property type before scoring. Pass the listing's
     *                                    property_type meta value (e.g. 'Residential Property').
     * @return array  Rich result object (see keys below)
     */
    public static function calculate(
        array $baselineData,
        array $comparedData,
        ?array $brokerFields = null,
        ?string $propertyType = null
    ): array {
        $brokerFields = $brokerFields ?? self::BROKER_FIELDS;

        // Build catalog filter: when $propertyType is given, restrict services to the valid
        // Tenant-only catalog so wrong-role services are excluded from both baseline and compared.
        $catalog = ($propertyType !== null) ? self::getCatalog($propertyType) : null;

        // Apply conditional field filtering so sub-fields whose parent condition is not active
        // in the baseline are excluded from the denominator.  This prevents stale values from
        // old form states from inflating or shifting the terms count unpredictably.
        $activeFields = self::getActiveFields($baselineData, $brokerFields);

        // ----------------------------------------------------------------
        // TERMS (Broker Compensation & Agency Agreement)
        // ----------------------------------------------------------------
        $matchedTerms      = [];   // baseline filled, compared same value
        $changedTerms      = [];   // baseline filled, compared different/blank
        $addedByAgentTerms = [];   // baseline blank, compared filled

        foreach ($activeFields as $field) {
            $bv = $baselineData[$field] ?? null;
            $cv = $comparedData[$field] ?? null;

            $bFilled = self::isFilled($bv);
            $cFilled = self::isFilled($cv);

            if ($bFilled) {
                if ($cFilled && self::normalizeForMatch($cv) === self::normalizeForMatch($bv)) {
                    $matchedTerms[$field] = ['baseline' => $bv, 'compared' => $cv];
                } else {
                    $changedTerms[$field] = ['baseline' => $bv, 'compared' => $cv];
                }
            } elseif ($cFilled) {
                $addedByAgentTerms[$field] = ['baseline' => $bv, 'compared' => $cv];
            }
            // both blank → skip
        }

        $termsBaselineTotal  = count($matchedTerms) + count($changedTerms);
        $termsMatchedCount   = count($matchedTerms);
        $termsChangedCount   = count($changedTerms);
        $termsAddedCount     = count($addedByAgentTerms);
        $termsMatchPercent   = $termsBaselineTotal > 0
            ? (int) round(($termsMatchedCount / $termsBaselineTotal) * 100)
            : 100;

        // ----------------------------------------------------------------
        // SERVICES (catalog-filtered when $propertyType is provided)
        // ----------------------------------------------------------------
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

        // ----------------------------------------------------------------
        // OVERALL (50 % Services + 50 % Terms, only active components)
        // ----------------------------------------------------------------
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
            // Overall
            'overall_percent'         => $overallPercent,

            // Terms
            'terms_baseline_total'    => $termsBaselineTotal,
            'terms_matched_count'     => $termsMatchedCount,
            'terms_changed_count'     => $termsChangedCount,
            'terms_added_count'       => $termsAddedCount,
            'terms_match_percent'     => $termsMatchPercent,
            'matched_terms'           => $matchedTerms,
            'changed_terms'           => $changedTerms,
            'added_terms'             => $addedByAgentTerms,

            // Services
            'services_baseline_total' => $servicesBaselineTotal,
            'services_matched_count'  => $servicesMatchedCount,
            'services_missing_count'  => $servicesMissingCount,
            'services_extra_count'    => $servicesExtraCount,
            'services_match_percent'  => $servicesMatchPercent,
            'matched_services'        => $matchedServices,
            'missing_services'        => $missingServices,
            'extra_services'          => $extraServices,

            // Legacy-compat aliases (for CompetingBidsService return shape)
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
}
