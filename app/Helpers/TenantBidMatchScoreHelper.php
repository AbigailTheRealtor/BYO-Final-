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
 * Terms (Logical Field Scoring):
 *   Each logical field group (see LOGICAL_FIELD_GROUPS) represents ONE complete
 *   decision/value.  Sub-inputs that belong to the same decision are combined into
 *   a single composite value and scored as ONE field, preventing UI input inflation.
 *
 *   baseline_total = count(logical groups with a filled baseline composite value)
 *   matched        = groups whose baseline composite equals compared composite
 *   changed        = groups whose baseline composite differs from compared composite
 *                    (includes groups that are cascade-deactivated in the bid — they
 *                    are excluded from the denominator entirely so only the parent
 *                    field change counts)
 *   added_by_agent = groups where baseline composite is blank but compared is filled
 *   terms_match_percent = matched / baseline_total * 100
 *
 *   Cascade deactivation: when a parent field in the BID (not baseline) deactivates
 *   its children (e.g., interested_purchase_fee_type = 'No'), those child groups are
 *   completely excluded from the denominator.  The parent field's own change (Yes→No)
 *   is already captured separately.
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
     * Logical field groups — the canonical list of scorable terms.
     *
     * Each entry defines ONE logical decision/value in the Tenant Broker Compensation
     * & Agency Agreement Terms section.  Groups with multiple 'fields' have their
     * individual DB values concatenated into a single composite string for comparison,
     * so sub-inputs for the same concept are never counted as separate changes.
     *
     * 'key'                  — canonical identifier; used as the primary key in
     *                          matched_terms / changed_terms / added_terms output.
     * 'fields'               — ordered list of DB field names that feed this concept.
     * 'baseline_active_when' — optional array: field → required value in BASELINE data.
     *                          When the condition is not met, the group is excluded from
     *                          the denominator (field not active in the listing).
     * 'bid_active_when'      — optional array: field → required value in BID data.
     *                          When the condition is not met, the group is cascade-excluded
     *                          (not counted in denominator, not counted as changed).
     *                          Prevents child fields from showing as separate changes when
     *                          the agent changed a parent to 'No'.
     *
     * Maximum 16 active logical groups (when all parent conditions are 'Yes').
     * additional_details_broker is intentionally excluded — "Additional Details NEVER counted".
     */
    const LOGICAL_FIELD_GROUPS = [
        // 1. Commission Structure
        [
            'key'    => 'commission_structure',
            'fields' => ['commission_structure'],
        ],

        // 2. Tenant's Broker Lease Fee — type + sub-value = ONE logical decision
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
        ],

        // 3. Payment Timing for Broker Fees — timing + calendar days = ONE logical decision
        [
            'key'    => 'payment_timing',
            'fields' => ['payment_timing', 'days_to_pay'],
        ],

        // 4. Interested in Purchasing a Property (parent, always scored)
        [
            'key'    => 'interested_purchase_fee_type',
            'fields' => ['interested_purchase_fee_type'],
        ],

        // 5. Purchase Fee — type + values = ONE logical decision
        //    Only when BASELINE indicates interest AND BID also indicates interest.
        //    Cascade deactivation: if agent bid 'No', parent (#4) captures the change;
        //    these sub-fields are excluded from denominator (not separate changes).
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
            'baseline_active_when' => ['interested_purchase_fee_type' => 'Yes'],
            'bid_active_when'      => ['interested_purchase_fee_type' => 'Yes'],
        ],

        // 6. Interested in a Lease-Option Agreement (parent, always scored)
        [
            'key'    => 'interested_lease_option_agreement',
            'fields' => ['interested_lease_option_agreement'],
        ],

        // 7. Compensation for Creating the Lease-Option Agreement — type + value = ONE
        [
            'key'    => 'lease_type',
            'fields' => ['lease_type', 'lease_value'],
            'baseline_active_when' => ['interested_lease_option_agreement' => 'Yes'],
            'bid_active_when'      => ['interested_lease_option_agreement' => 'Yes'],
        ],

        // 8. Compensation if Purchase Option is Exercised — type + value = ONE
        [
            'key'    => 'purchase_type',
            'fields' => ['purchase_type', 'purchase_value'],
            'baseline_active_when' => ['interested_lease_option_agreement' => 'Yes'],
            'bid_active_when'      => ['interested_lease_option_agreement' => 'Yes'],
        ],

        // 9. Protection Period Timeframe
        [
            'key'    => 'protection_period',
            'fields' => ['protection_period'],
        ],

        // 10. Early Termination Fee (Yes/No parent)
        [
            'key'    => 'early_termination_fee_option',
            'fields' => ['early_termination_fee_option'],
        ],

        // 11. Termination Fee Amount — only when parent = Yes in BOTH baseline and bid
        [
            'key'    => 'early_termination_fee_amount',
            'fields' => ['early_termination_fee_amount'],
            'baseline_active_when' => ['early_termination_fee_option' => 'Yes'],
            'bid_active_when'      => ['early_termination_fee_option' => 'Yes'],
        ],

        // 12. Retainer Fee (Yes/No parent)
        [
            'key'    => 'retainer_fee_option',
            'fields' => ['retainer_fee_option'],
        ],

        // 13. Retainer Fee Amount — only when parent = Yes in BOTH baseline and bid
        [
            'key'    => 'retainer_fee_amount',
            'fields' => ['retainer_fee_amount'],
            'baseline_active_when' => ['retainer_fee_option' => 'Yes'],
            'bid_active_when'      => ['retainer_fee_option' => 'Yes'],
        ],

        // 14. Retainer Fee Application — only when parent = Yes in BOTH baseline and bid
        [
            'key'    => 'retainer_fee_application',
            'fields' => ['retainer_fee_application'],
            'baseline_active_when' => ['retainer_fee_option' => 'Yes'],
            'bid_active_when'      => ['retainer_fee_option' => 'Yes'],
        ],

        // 15. Tenant Agency Agreement Timeframe
        [
            'key'    => 'agency_agreement_timeframe',
            'fields' => ['agency_agreement_timeframe'],
        ],

        // 16. Acceptable Brokerage Relationship
        [
            'key'    => 'brokerage_relationship',
            'fields' => ['brokerage_relationship'],
        ],

        // NOTE: additional_details_broker (free-text Additional Terms) is intentionally
        // excluded from scoring. "Additional Details NEVER counted" per business rule.
    ];

    /**
     * Legacy flat field list — kept for backward compatibility with any external code
     * that passes a custom $brokerFields array to calculate().  The main calculate()
     * method now uses LOGICAL_FIELD_GROUPS and ignores this constant internally.
     *
     * REMOVED from previous version:
     *   - broker_fee_timing     (duplicate alias of payment_timing)
     *   - broker_fee_days_from_rent (duplicate alias of days_to_pay)
     *   - flat_fee_amount, percent_gross_lease, purchase_flat_fee_amount,
     *     purchase_percent_value  (legacy field names not used in current forms)
     */
    const BROKER_FIELDS = [
        'commission_structure',
        'lease_fee_type',
        'payment_timing',
        'days_to_pay',
        'interested_purchase_fee_type',
        'purchase_fee_type',
        'purchase_fee_flat',
        'purchase_fee_percentage',
        'purchase_fee_flat_combo',
        'purchase_fee_percentage_combo',
        'purchase_fee_other',
        'interested_lease_option_agreement',
        'lease_type',
        'lease_value',
        'purchase_type',
        'purchase_value',
        'protection_period',
        'early_termination_fee_option',
        'early_termination_fee_amount',
        'retainer_fee_option',
        'retainer_fee_amount',
        'retainer_fee_application',
        'agency_agreement_timeframe',
        'brokerage_relationship',
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
        // Normalize curly/smart quotes to straight equivalents before comparison
        $v = preg_replace('/[\x{2018}\x{2019}]/u', "'", $v);
        $v = preg_replace('/[\x{201C}\x{201D}]/u', '"', $v);
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
     * If $catalog is provided, only standard services whose normalized form appears in the
     * catalog are kept. This prevents wrong-role services (e.g., Buyer purchase services)
     * from contaminating Tenant scoring when both were accidentally stored together.
     *
     * IMPORTANT: The catalog filter applies ONLY to the standard `services` array. Entries in
     * `other_services` (user-defined custom additional services) always pass through without
     * filtering — they cannot be confused with wrong-role catalog services, and must always
     * contribute to the score so that custom services agreed upon by both parties are counted.
     *
     * @param array      $data    Data array containing 'services' and 'other_services' keys
     * @param array|null $catalog Normalized valid-service strings (from getCatalog()). When
     *                            provided, only catalog-matching standard services are returned
     *                            (custom other_services are always kept).
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
            $servicesNorm = array_values(array_filter(
                $servicesNorm,
                fn($s) => in_array($s, $catalog, true)
            ));
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
     * Non-empty values are joined with '|' in the given field order.
     * This collapses sub-inputs for one logical concept into a single comparable string.
     *
     * @param array $data   The data array (baseline or compared).
     * @param array $fields Ordered list of DB field names that feed this concept.
     * @return string       Normalized composite (may be empty string if all fields blank).
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
     *
     * @param array      $conditionMap  ['field' => 'required_value'] from LOGICAL_FIELD_GROUPS.
     * @param array      $data          The data array to check against (baseline or bid).
     * @return bool
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
     * Legacy getActiveFields — kept for backward compatibility with external callers.
     * The main calculate() method now uses LOGICAL_FIELD_GROUPS directly.
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
     * @param array $baselineData  The baseline data array.
     * @param array $fields        Full field list to filter.
     * @return array               Filtered field list.
     */
    public static function getActiveFields(array $baselineData, array $fields): array
    {
        $b = $baselineData;

        $leaseFeeType    = $b['lease_fee_type'] ?? '';
        $purchaseParent  = $b['interested_purchase_fee_type'] ?? '';
        $purchaseFeeType = $b['purchase_fee_type'] ?? '';
        $leaseOption     = $b['interested_lease_option_agreement'] ?? '';
        $earlyTerm       = $b['early_termination_fee_option'] ?? '';
        $retainer        = $b['retainer_fee_option'] ?? '';
        $payTiming       = strtolower($b['payment_timing'] ?? '');

        $isPurchaseYes    = $purchaseParent === 'Yes';
        $isLeaseOptionYes = $leaseOption === 'Yes';
        $isEarlyTermYes   = $earlyTerm === 'Yes';
        $isRetainerYes    = $retainer === 'Yes';
        $payTimingHasDays = str_contains($payTiming, 'day');

        $leaseFlatTypes     = ['Flat Fee'];
        $leasePctTypes      = ['Percentage of the Gross Lease Value'];
        $leaseMonthlyTypes  = ['Percentage of Monthly Rent'];
        $leaseComboTypes    = ['Flat Fee + Percentage of the Gross Lease Value'];
        $leaseNetTypes      = ['Percentage of Net Aggregate Rent'];
        $leaseComboNetTypes = ['Flat Fee + Percentage of Net Aggregate Rent'];
        $leaseOtherTypes    = ['Other'];

        $purchaseFlatType   = 'Flat Fee';
        $purchasePctType    = 'Percentage of the Total Purchase Price';
        $purchaseComboTypes = [
            'Percentage of the Total Purchase Price + Flat Fee',
            'Flat Fee + Percentage of the Total Purchase Price',
        ];
        $purchaseOtherType  = 'other';

        $conditionalRules = [
            'lease_fee_flat'                      => in_array($leaseFeeType, $leaseFlatTypes, true),
            'lease_fee_percentage'                => in_array($leaseFeeType, $leasePctTypes, true),
            'lease_fee_percentage_monthly_rent'   => in_array($leaseFeeType, $leaseMonthlyTypes, true),
            'lease_fee_percentage_monthly_number' => in_array($leaseFeeType, $leaseMonthlyTypes, true),
            'lease_fee_flat_combo'                => in_array($leaseFeeType, array_merge($leaseComboTypes, $leaseComboNetTypes), true),
            'lease_fee_percentage_combo'          => in_array($leaseFeeType, $leaseComboTypes, true),
            'lease_fee_percentage_net'            => in_array($leaseFeeType, array_merge($leaseNetTypes, $leaseComboNetTypes), true),
            'lease_fee_flat_combo_net'            => in_array($leaseFeeType, $leaseComboNetTypes, true),
            'lease_fee_percentage_combo_net'      => in_array($leaseFeeType, $leaseComboNetTypes, true),
            'lease_fee_other'                     => in_array($leaseFeeType, $leaseOtherTypes, true),
            'purchase_fee_type'                   => $isPurchaseYes,
            'purchase_fee_flat'                   => $isPurchaseYes && $purchaseFeeType === $purchaseFlatType,
            'purchase_fee_percentage'             => $isPurchaseYes && $purchaseFeeType === $purchasePctType,
            'purchase_fee_flat_combo'             => $isPurchaseYes && in_array($purchaseFeeType, $purchaseComboTypes, true),
            'purchase_fee_percentage_combo'       => $isPurchaseYes && in_array($purchaseFeeType, $purchaseComboTypes, true),
            'purchase_fee_other'                  => $isPurchaseYes && $purchaseFeeType === $purchaseOtherType,
            'lease_type'                          => $isLeaseOptionYes,
            'lease_value'                         => $isLeaseOptionYes,
            'purchase_type'                       => $isLeaseOptionYes,
            'purchase_value'                      => $isLeaseOptionYes,
            'early_termination_fee_amount'        => $isEarlyTermYes,
            'retainer_fee_amount'                 => $isRetainerYes,
            'retainer_fee_application'            => $isRetainerYes,
            'days_to_pay'                         => $payTimingHasDays,
        ];

        return array_values(array_filter($fields, function (string $field) use ($conditionalRules) {
            if (array_key_exists($field, $conditionalRules)) {
                return $conditionalRules[$field];
            }
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
     * Terms are scored using LOGICAL_FIELD_GROUPS — each group represents ONE logical
     * decision (e.g., "Tenant's Broker Lease Fee" = fee type + sub-value combined).
     * This prevents individual UI inputs from inflating the changed count or denominator.
     * Cascade deactivation excludes child groups when the agent's bid deactivates
     * their parent (e.g., agent says "No" to purchasing → purchase fee children excluded).
     *
     * @param array       $baselineData   The source-of-truth (Tenant's listing / counter / viewer's bid)
     * @param array       $comparedData   The data being evaluated (Agent's bid / counter / competing bid)
     * @param array|null  $brokerFields   Ignored — kept for signature backward compatibility only.
     *                                    Terms scoring now uses LOGICAL_FIELD_GROUPS exclusively.
     * @param string|null $propertyType   When provided, services are filtered to the Tenant-only catalog
     *                                    for this property type before scoring.
     * @return array  Rich result object (see keys below)
     */
    public static function calculate(
        array $baselineData,
        array $comparedData,
        ?array $brokerFields = null,
        ?string $propertyType = null
    ): array {
        // Build catalog filter for services
        $catalog = ($propertyType !== null) ? self::getCatalog($propertyType) : null;

        // ----------------------------------------------------------------
        // TERMS — Logical Group Scoring
        // ----------------------------------------------------------------
        // For counting purposes, each logical group = 1 field regardless of
        // how many sub-inputs it contains.
        //
        // changed_terms and matched_terms output includes the group primary key
        // PLUS any active individual sub-field keys (for view mismatch badges).
        // ----------------------------------------------------------------
        $matchedTerms      = [];   // key → ['baseline' => …, 'compared' => …]
        $changedTerms      = [];   // key → ['baseline' => …, 'compared' => …]
        $addedByAgentTerms = [];   // key → ['baseline' => …, 'compared' => …]

        $groupMatchCount   = 0;
        $groupChangedCount = 0;
        $groupAddedCount   = 0;
        $groupTotalCount   = 0;    // denominator

        foreach (self::LOGICAL_FIELD_GROUPS as $group) {
            $key    = $group['key'];
            $fields = $group['fields'];

            // ── 1. Baseline condition — is this group active at all? ──────
            if (isset($group['baseline_active_when'])) {
                if (!self::checkGroupCondition($group['baseline_active_when'], $baselineData)) {
                    continue; // Group not applicable for this listing
                }
            }

            // ── 2. Build composite values ────────────────────────────────
            $bComposite = self::buildCompositeValue($baselineData, $fields);
            $cComposite = self::buildCompositeValue($comparedData, $fields);

            // ── 3. Determine denominator membership ──────────────────────
            if (!self::isFilled($bComposite)) {
                // Baseline blank → added by agent (not in denominator)
                if (self::isFilled($cComposite)) {
                    $groupAddedCount++;
                    $addedByAgentTerms[$key] = ['baseline' => null, 'compared' => $cComposite];
                    // Individual sub-field keys for display
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

            // ── 4. Bid cascade deactivation ──────────────────────────────
            // When the bid's parent is 'No' (or missing), child groups are excluded
            // from the denominator entirely.  The parent field's own change is counted
            // separately (it appears as its own group further up or down the list).
            if (isset($group['bid_active_when'])) {
                if (!self::checkGroupCondition($group['bid_active_when'], $comparedData)) {
                    // Skip — do not add to denominator, do not count as change.
                    // The parent group (e.g., interested_purchase_fee_type) already
                    // captures the Yes→No shift as a separate logical change.
                    continue;
                }
            }

            // ── 5. Compare and classify ───────────────────────────────────
            $groupTotalCount++;

            if (self::normalizeForMatch($cComposite) === self::normalizeForMatch($bComposite)) {
                // MATCHED
                $groupMatchCount++;
                $matchedTerms[$key] = ['baseline' => $bComposite, 'compared' => $cComposite];
                // Individual sub-field keys for view display
                foreach ($fields as $f) {
                    if ($f !== $key) {
                        $matchedTerms[$f] = [
                            'baseline' => $baselineData[$f] ?? null,
                            'compared' => $comparedData[$f] ?? null,
                        ];
                    }
                }
            } else {
                // CHANGED
                $groupChangedCount++;
                $changedTerms[$key] = ['baseline' => $bComposite, 'compared' => $cComposite];
                // Individual sub-field keys for view mismatch badges (backward compat)
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
