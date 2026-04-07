<?php

namespace App\Helpers;

/**
 * LandlordBidMatchScoreHelper
 *
 * Baseline-driven match score for Hire a Landlord's Agent bid comparisons.
 * Mirrors TenantBidMatchScoreHelper architecture exactly — only Landlord-specific
 * field names, field groups, and service catalogs differ.
 *
 * SCORING RULES (identical to Tenant)
 * =====================================
 * Services:
 *   baseline_total = count(baseline services filtered to valid Landlord catalog)
 *   matched        = baseline items also in compared
 *   missing        = baseline items not in compared
 *   extra          = compared items not in baseline  (NOT in denominator)
 *   services_match_percent = matched / baseline_total * 100
 *
 * Terms (Logical Field Groups):
 *   Each group = ONE logical decision, regardless of how many sub-inputs.
 *   baseline_total = groups with a filled baseline composite value
 *   matched        = groups where baseline composite = compared composite
 *   changed        = groups where composites differ
 *   added_by_agent = groups where baseline blank but compared filled
 *
 *   Cascade deactivation: if the BID's parent field is 'No', child groups
 *   are excluded entirely from the denominator.
 *
 * Overall = 50% Services + 50% Terms (only non-zero components averaged)
 */
class LandlordBidMatchScoreHelper
{
    /**
     * Logical field groups — Landlord-specific fields.
     * Same structure as TenantBidMatchScoreHelper::LOGICAL_FIELD_GROUPS.
     *
     * Maximum 17 active logical groups (when all parent conditions are 'Yes').
     * Groups 5a/5b: tenant_broker_commission_structure split from tenant_broker_fee_structure.
     * additional_details_broker is intentionally excluded — "Additional Details NEVER counted".
     */
    const LOGICAL_FIELD_GROUPS = [
        // 1. Listing Commission Type — type + sub-values = ONE logical decision
        [
            'key'    => 'purchase_fee_type',
            'fields' => [
                'purchase_fee_type',
                'purchase_fee_flat',
                'purchase_fee_gross_rent',
                'purchase_fee_net_aggregate',
                'purchase_fee_rental_period',
                'purchase_fee_monthly_percentage',
                'purchase_fee_months',
                'purchase_fee_flat_commercial',
                'purchase_fee_purchase_price',
                'purchase_fee_percentage_combo',
                'purchase_fee_flat_combo',
                'purchase_fee_other',
                'purchase_fee_other_commercial',
            ],
        ],

        // 2. Payment Timing for Listing Fee — timing + days = ONE decision
        [
            'key'    => 'broker_fee_timing',
            'fields' => [
                'broker_fee_timing',
                'broker_fee_days_from_rent',
                'broker_fee_days_after_lease',
                'broker_fee_days_after_rent',
                'broker_fee_days_after_due_event',
                'split_payment_due',
                'split_payment_due_other',
                'broker_fee_timing_other',
            ],
        ],

        // 3. Renewal Commission — type + sub-values = ONE decision
        [
            'key'    => 'renewal_fee_type',
            'fields' => [
                'renewal_fee_type',
                'renewal_fee_percentage',
                'renewal_fee_lease_value',
                'renewal_fee_first_month',
                'renewal_fee_flat_free',
                'renewal_fee_custom',
                'renewal_fee_no_of_months',
            ],
        ],

        // 4. Expansion Commission
        [
            'key'    => 'expansion_commission_percentage',
            'fields' => ['expansion_commission_percentage'],
        ],

        // 5a. Tenant Broker Commission Structure — the type/policy (who pays, what structure)
        [
            'key'    => 'tenant_broker_commission_structure',
            'fields' => ['tenant_broker_commission_structure'],
        ],

        // 5b. Tenant Broker Commission Fee — the fee amount details = ONE decision
        //     When no_compensation is selected, these fields are blank → excluded from denominator.
        [
            'key'    => 'tenant_broker_fee_structure',
            'fields' => [
                'tenant_broker_fee_structure',
                'tenant_broker_percentage',
                'tenant_broker_gross_lease',
                'tenant_broker_first_month_rent',
                'tenant_broker_flat_fee',
                'tenant_broker_other',
            ],
        ],

        // 6. Interested in Lease-Option Agreement (parent, always scored)
        [
            'key'    => 'interested_lease_option_agreement',
            'fields' => ['interested_lease_option_agreement'],
        ],

        // 7. Lease-Option Fee — type + value = ONE, only when interest = Yes
        [
            'key'    => 'lease_type',
            'fields' => ['lease_type', 'lease_value'],
            'baseline_active_when' => ['interested_lease_option_agreement' => 'Yes'],
            'bid_active_when'      => ['interested_lease_option_agreement' => 'Yes'],
        ],

        // 8. Purchase-Option Fee — type + value = ONE, only when interest = Yes
        [
            'key'    => 'purchase_type',
            'fields' => ['purchase_type', 'purchase_value'],
            'baseline_active_when' => ['interested_lease_option_agreement' => 'Yes'],
            'bid_active_when'      => ['interested_lease_option_agreement' => 'Yes'],
        ],

        // 9. Interested in Selling (parent)
        [
            'key'    => 'interested_in_selling',
            'fields' => ['interested_in_selling'],
        ],

        // 10. Sale Commission Type — only when interest = Yes
        [
            'key'    => 'interested_in_selling_type',
            'fields' => ['interested_in_selling_type'],
            'baseline_active_when' => ['interested_in_selling' => 'Yes'],
            'bid_active_when'      => ['interested_in_selling' => 'Yes'],
        ],

        // 11. Protection Period Timeframe
        [
            'key'    => 'protection_period',
            'fields' => ['protection_period'],
        ],

        // 12. Early Termination Fee (Yes/No parent)
        [
            'key'    => 'early_termination_fee_option',
            'fields' => ['early_termination_fee_option'],
        ],

        // 13. Termination Fee Amount — only when parent = Yes in BOTH
        [
            'key'    => 'early_termination_fee_amount',
            'fields' => ['early_termination_fee_amount'],
            'baseline_active_when' => ['early_termination_fee_option' => 'Yes'],
            'bid_active_when'      => ['early_termination_fee_option' => 'Yes'],
        ],

        // 14. Agency Agreement Timeframe
        [
            'key'    => 'agency_agreement_timeframe',
            'fields' => ['agency_agreement_timeframe', 'agency_agreement_custom'],
        ],

        // 15. Brokerage Relationship
        [
            'key'    => 'brokerage_relationship',
            'fields' => ['brokerage_relationship'],
        ],

        // 16. Interested in Property Management
        [
            'key'    => 'interested_in_property_management',
            'fields' => ['interested_in_property_management'],
        ],
    ];

    /**
     * Fields included in the BASELINE that feed logical group sub-inputs.
     * Used by legacy getActiveFields() — not by calculate() itself.
     */
    const BASELINE_TERM_FIELDS = [
        'purchase_fee_type',
        'purchase_fee_flat',
        'purchase_fee_gross_rent',
        'purchase_fee_net_aggregate',
        'purchase_fee_rental_period',
        'purchase_fee_monthly_percentage',
        'purchase_fee_months',
        'purchase_fee_flat_commercial',
        'purchase_fee_purchase_price',
        'purchase_fee_percentage_combo',
        'purchase_fee_flat_combo',
        'purchase_fee_other',
        'purchase_fee_other_commercial',
        'broker_fee_timing',
        'broker_fee_days_from_rent',
        'broker_fee_days_after_lease',
        'broker_fee_days_after_rent',
        'broker_fee_days_after_due_event',
        'split_payment_due',
        'broker_fee_timing_other',
        'renewal_fee_type',
        'expansion_commission_percentage',
        'tenant_broker_commission_structure',
        'tenant_broker_fee_structure',
        'interested_lease_option_agreement',
        'lease_type',
        'lease_value',
        'purchase_type',
        'purchase_value',
        'interested_in_selling',
        'interested_in_selling_type',
        'protection_period',
        'early_termination_fee_option',
        'early_termination_fee_amount',
        'agency_agreement_timeframe',
        'agency_agreement_custom',
        'brokerage_relationship',
        'interested_in_property_management',
    ];

    /**
     * Residential Landlord services catalog (source: services.blade.php Residential).
     * All service strings are stored lowercase for comparison.
     */
    const RESIDENTIAL_SERVICES_CATALOG = [
        // Rental Marketing & Listing Promotion
        "list the property on the local multiple listing service (mls)",
        "syndicate the listing to third-party platforms (e.g., zillow.com, realtor.com, trulia.com, homes.com)",
        "create a branded flyer featuring the property's key highlights",
        "post the property on facebook marketplace",
        "post the property on craigslist in the appropriate \"homes for rent\" category",
        "share the listing on nextdoor in neighborhood or community groups",
        "promote the listing on facebook in housing or rental groups",
        "share the listing on instagram using posts, stories, or reels",
        "promote the listing on linkedin in professional or real estate groups",
        "upload a tiktok video walkthrough of the property",
        "upload a youtube video walkthrough of the property",
        "launch a mass email campaign promoting the listing",
        "distribute printed flyers or postcards in target geographic areas",
        "launch hyperlocal or interest-based digital ad campaigns promoting the listing",
        // Listing Presentation & Preparation
        "conduct a property walkthrough and provide recommendations for listing readiness",
        "provide a custom listing preparation checklist",
        "collect property details and prepare mls remarks and a public listing description",
        "provide a visual consultation for interior layout, cleanliness, and presentation",
        "provide a curb appeal consultation focused on exterior presentation",
        "provide referrals to third-party vendors (e.g., cleaners, handypeople, electricians, landscapers). vendor fees billed separately. referrals only — no endorsement or warranty is made",
        // Photography, Video & Virtual Media
        "provide professional property photography",
        "provide aerial (drone) photography (subject to faa part 107 compliance)",
        "provide a video walkthrough tour",
        "provide a 3d virtual tour",
        "provide virtual staging (digital enhancements only; no physical staging)",
        "provide digital photo enhancements",
        "create a basic schematic floor plan (non-certified; for marketing purposes only)",
        // Showings & Access Coordination
        "ensure proper notice is given if the property is occupied",
        "install a real estate sign on the property",
        "install a lockbox for agent access",
        "schedule and attend showings with prospective tenants",
        "coordinate showings with tenant's agents",
        "collect and relay feedback to the landlord after showings",
        // Tenant Application Support
        "provide a link to an online application platform with third-party screening tools (e.g., credit, background, and eviction checks)",
        "ensure compliance with fair housing laws and screening regulations throughout the application process",
        "collect and organize application documents submitted by prospective tenants",
        "verify basic information provided in the application (e.g., employment, income, and references)",
        "present complete and organized application packages to the landlord for review and final selection",
        // Lease Preparation & Execution
        "review lease offers submitted by prospective tenants and summarize key terms",
        "coordinate lease negotiation with the tenant or tenant's agent",
        "prepare a state-specific lease agreement using approved forms or templates",
        "assist with completing required lease disclosures and reviewing key lease terms",
        "assist with in-person or electronic lease signing, including e-signature setup and secure delivery of executed lease documents, addenda, and disclosures to all parties",
        "confirm receipt of required move-in funds and assist the landlord in verifying amounts due, payment deadlines, and accepted payment methods",
        // Move-In Support & Coordination
        "coordinate move-in date and key handoff logistics with the tenant or tenant's agent",
        "confirm completion of any agreed-upon pre-move-in cleaning or repairs",
        "verify receipt of all required move-in funds prior to occupancy (e.g., deposit, rent, pet fees)",
        "provide a utility setup checklist and local provider resources for the tenant",
        "share a move-in checklist for documentation and property condition review",
        // Property Management
        "provide ongoing property management services throughout the lease term (rent collection, maintenance coordination, tenant communications, lease enforcement, renewals, etc.)",
        // Leasing Strategy & Guidance
        "provide a rental market analysis (rma) with pricing insights based on comparable rentals, neighborhood trends, and current market conditions ",
        "advise on lease types and structures (e.g., month-to-month, annual, furnished, corporate, lease-option)",
        "provide general guidance on landlord obligations and tenant rights under state law",
        "provide general guidance on rental demand, local market conditions, and tenant expectations",
    ];

    /**
     * Commercial Landlord services catalog (source: services.blade.php Commercial).
     */
    const COMMERCIAL_SERVICES_CATALOG = [
        // Rental Marketing & Listing Promotion
        "list the property on the local multiple listing service (mls)",
        "list the property on crexi.com",
        "list the property on loopnet.com",
        "create a branded flyer featuring the property's key highlights",
        "post the property on craigslist under the \"office/commercial\" category",
        "promote the listing on facebook in commercial leasing or business startup groups",
        "share the listing on instagram using photos, stories, or reels",
        "promote the listing on linkedin in professional, real estate, or commercial investment groups",
        "upload a tiktok video walkthrough of the property",
        "upload a youtube video walkthrough of the property",
        "launch a mass email campaign promoting the listing",
        "distribute printed flyers or postcards in target geographic areas",
        "launch hyperlocal or interest-based digital ad campaigns promoting the listing",
        // Listing Presentation & Preparation
        "conduct a property walkthrough and provide recommendations for listing readiness",
        "provide a custom listing preparation checklist",
        "collect property details such as lease terms, square footage, property features, and allowable uses",
        "prepare a marketing packet including zoning, cap rate references, and permitted uses",
        "provide a visual consultation focused on interior layout, cleanliness, and presentation",
        "provide a curb appeal consultation for exterior appearance and signage opportunities",
        "provide referrals to third-party vendors (e.g., cleaners, sign installers, minor repair vendors). vendor fees billed separately. referrals only — no endorsement or warranty is made",
        // Photography, Video & Virtual Media
        "provide professional property photography",
        "provide aerial (drone) photography (subject to faa part 107 compliance)",
        "provide a video walkthrough tour",
        "provide a 3d virtual tour",
        "provide virtual staging (digital enhancements only; no physical staging)",
        "provide digital photo enhancements",
        "create a basic schematic floor plan (non-certified; for marketing purposes only)",
        // Showings & Access Coordination
        "ensure proper notice is given if the property is occupied",
        "install a real estate sign on the property",
        "install a lockbox for agent access",
        "schedule and attend showings with prospective tenants",
        "coordinate showings with tenant's agents",
        "collect and relay showing feedback to the landlord",
        // Tenant Application Support
        "provide a link to an online application platform or share instructions with prospective tenants or tenant's agents",
        "ensure compliance with applicable federal, state, and local commercial leasing and anti-discrimination laws",
        "collect and organize application documents (e.g., business licenses, financials, entity records, references)",
        "verify basic information provided in the application (e.g., business operations, income sources, references)",
        "present complete application packages to the landlord for review and final selection",
        // Lease Preparation, LOI & Execution
        "coordinate lease negotiation with the tenant or tenant's agent",
        "collect and organize letters of intent (lois) or draft lease proposals",
        "draft or assist with execution of the final lease agreement using approved forms or templates",
        "provide and review required lease disclosures and addenda based on state or municipal requirements",
        "assist with in-person or electronic lease signing, including e-signature setup and secure delivery of executed lease documents, addenda, and disclosures to all parties",
        "verify receipt of required deposits and track rent commencement and key lease dates to ensure move-in readiness",
        // Move-In Support & Coordination
        "coordinate move-in date and key handoff logistics with the tenant or tenant's agent",
        "confirm completion of any agreed-upon pre-move-in repairs, cleaning, or improvements",
        "verify receipt of all required move-in funds and documents prior to occupancy (e.g., rent, security deposit, insurance certificates)",
        "provide a utility setup checklist and local provider resources for the tenant",
        "share a move-in checklist for documentation and property condition review",
        "assist with coordination of move-in logistics, including certificate of insurance (coi) and vendor access (as agreed)",
        // Property Management
        "provide ongoing property management services throughout the lease term (rent collection, maintenance coordination, tenant communications, lease enforcement, renewals, etc.)",
        // Leasing Strategy & Guidance
        "provide a comparable lease analysis with pricing recommendations based on similar properties, local vacancy trends, and current market conditions",
        "advise on lease types and structures (e.g., nnn, modified gross, full service) with general explanations of differences",
        "provide general guidance on landlord obligations and tenant rights under applicable commercial leasing laws",
        "provide general guidance on zoning, permitted uses, occupancy standards, or rent escalation terms",
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // Public API
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Main entry point — returns the full score array.
     *
     * @param array       $baselineData   Landlord listing (or counter) data as array.
     * @param array       $comparedData   Agent bid data as array.
     * @param array|null  $brokerFields   Ignored (kept for API parity with Tenant helper).
     * @param string|null $propertyType   'Residential Property' | 'Commercial Property'
     */
    public static function calculate(
        array $baselineData,
        array $comparedData,
        ?array $brokerFields = null,
        ?string $propertyType = null
    ): array {
        $catalog = ($propertyType !== null) ? self::getCatalog($propertyType) : null;

        // ── TERMS — Logical Group Scoring ─────────────────────────────────
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

            // 1. Baseline condition check
            if (isset($group['baseline_active_when'])) {
                if (!self::checkGroupCondition($group['baseline_active_when'], $baselineData)) {
                    continue;
                }
            }

            // 2. Build composite values
            $bComposite = self::buildCompositeValue($baselineData, $fields);
            $cComposite = self::buildCompositeValue($comparedData, $fields);

            // 3. Determine denominator membership
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

            // 4. Bid cascade deactivation
            if (isset($group['bid_active_when'])) {
                if (!self::checkGroupCondition($group['bid_active_when'], $comparedData)) {
                    continue;
                }
            }

            // 5. Compare and classify
            $groupTotalCount++;

            if (self::normalizeForMatch($cComposite) === self::normalizeForMatch($bComposite)) {
                $groupMatchCount++;
                $matchedTerms[$key] = ['baseline' => $bComposite, 'compared' => $cComposite];
                foreach ($fields as $f) {
                    if ($f !== $key) {
                        $bv = $baselineData[$f] ?? null;
                        $cv = $comparedData[$f] ?? null;
                        if (self::isFilled($bv)) {
                            $matchedTerms[$f] = ['baseline' => $bv, 'compared' => $cv];
                        }
                    }
                }
            } else {
                $groupChangedCount++;
                $changedTerms[$key] = ['baseline' => $bComposite, 'compared' => $cComposite];
                foreach ($fields as $f) {
                    if ($f !== $key) {
                        $bv = $baselineData[$f] ?? null;
                        $cv = $comparedData[$f] ?? null;
                        if (self::isFilled($bv) || self::isFilled($cv)) {
                            $changedTerms[$f] = ['baseline' => $bv, 'compared' => $cv];
                        }
                    }
                }
            }
        }

        $termsBaselineTotal = $groupTotalCount;
        $termsMatchedCount  = $groupMatchCount;
        $termsChangedCount  = $groupChangedCount;
        $termsAddedCount    = $groupAddedCount;
        $termsMatchPercent  = $termsBaselineTotal > 0
            ? (int) round($termsMatchedCount / $termsBaselineTotal * 100)
            : 100;

        // ── SERVICES — Catalog-Filtered Scoring ──────────────────────────
        $rawBaseline = $baselineData['services'] ?? [];
        if (is_string($rawBaseline)) $rawBaseline = json_decode($rawBaseline, true) ?? [];
        $rawBaseline = is_array($rawBaseline) ? array_values(array_filter($rawBaseline)) : [];

        $rawCompared = $comparedData['services'] ?? [];
        if (is_string($rawCompared)) $rawCompared = json_decode($rawCompared, true) ?? [];
        $rawCompared = is_array($rawCompared) ? array_values(array_filter($rawCompared)) : [];

        // Other services (additional custom services) — always count
        $otherBaseline = $baselineData['other_services'] ?? [];
        if (is_string($otherBaseline)) $otherBaseline = json_decode($otherBaseline, true) ?? [];
        $otherBaseline = is_array($otherBaseline) ? array_values(array_filter($otherBaseline, fn($s) => is_string($s) && !empty(trim($s)))) : [];

        $otherCompared = $comparedData['other_services'] ?? [];
        if (is_string($otherCompared)) $otherCompared = json_decode($otherCompared, true) ?? [];
        $otherCompared = is_array($otherCompared) ? array_values(array_filter($otherCompared, fn($s) => is_string($s) && !empty(trim($s)))) : [];

        // Normalize for comparison
        $normB = array_map(fn($s) => self::normalizeService((string) $s), $rawBaseline);
        $normC = array_map(fn($s) => self::normalizeService((string) $s), $rawCompared);

        if ($catalog !== null) {
            $normB = array_values(array_filter($normB, fn($s) => in_array($s, $catalog, true)));
            $normC = array_values(array_filter($normC, fn($s) => in_array($s, $catalog, true)));
        }

        $normOtherB = array_map(fn($s) => self::normalizeService((string) $s), $otherBaseline);
        $normOtherC = array_map(fn($s) => self::normalizeService((string) $s), $otherCompared);

        $allNormB = array_merge($normB, $normOtherB);
        $allNormC = array_merge($normC, $normOtherC);

        $matchedServices = array_values(array_intersect($allNormB, $allNormC));
        $missingServices = array_values(array_diff($allNormB, $allNormC));
        $extraServices   = array_values(array_diff($allNormC, $allNormB));

        $servicesBaselineTotal = count($allNormB);
        $servicesMatchedCount  = count($matchedServices);
        $servicesMissingCount  = count($missingServices);
        $servicesExtraCount    = count($extraServices);
        $servicesMatchPercent  = $servicesBaselineTotal > 0
            ? (int) round($servicesMatchedCount / $servicesBaselineTotal * 100)
            : 100;

        // ── OVERALL ───────────────────────────────────────────────────────
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
            // Legacy-compat aliases
            'broker_comp_percent'     => $termsMatchPercent,
            'broker_comp_matched'     => $termsMatchedCount,
            'broker_comp_total'       => $termsBaselineTotal,
            'services_percent'        => $servicesMatchPercent,
            'services_matched'        => $servicesMatchedCount,
            'services_total'          => $servicesBaselineTotal,
        ];
    }

    /**
     * Return CSS color for a score percentage.
     */
    public static function scoreColor(int $score): string
    {
        if ($score >= 80) return '#28a745';
        if ($score >= 50) return '#ffc107';
        return '#dc3545';
    }

    /**
     * Return the catalog for the given property type.
     *
     * Entries are normalized (lowercased, trimmed, whitespace-collapsed, smart-quotes
     * replaced) so that the returned catalog can be compared directly against
     * normalized user input without a second normalizeService() pass.
     */
    public static function getCatalog(string $propertyType): array
    {
        $raw = str_contains(strtolower($propertyType), 'commercial')
            ? self::COMMERCIAL_SERVICES_CATALOG
            : self::RESIDENTIAL_SERVICES_CATALOG;
        return array_map(fn($s) => self::normalizeService((string) $s), $raw);
    }

    /**
     * Normalize a service string for catalog comparison.
     * Lowercases, normalizes smart quotes → straight quotes.
     */
    public static function normalizeService(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = str_replace(["\u{2019}", "\u{2018}", "\u{201C}", "\u{201D}"], ["'", "'", '"', '"'], $s);
        $s = preg_replace('/\s+/', ' ', $s);
        return trim($s);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internal helpers
    // ─────────────────────────────────────────────────────────────────────────

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

    private static function checkGroupCondition(array $conditionMap, array $data): bool
    {
        foreach ($conditionMap as $condField => $condValue) {
            if (($data[$condField] ?? '') !== $condValue) {
                return false;
            }
        }
        return true;
    }

    private static function isFilled($v): bool
    {
        if ($v === null || $v === '' || $v === [] || $v === false) return false;
        if (is_string($v)) return trim($v) !== '';
        if (is_array($v)) return count(array_filter($v, fn($i) => $i !== null && $i !== '')) > 0;
        return true;
    }

    private static function normalizeForMatch($v): string
    {
        if (is_array($v)) {
            $parts = array_map(fn($i) => self::normalizeForMatch($i), $v);
            sort($parts);
            return implode(',', $parts);
        }
        $s = mb_strtolower(trim((string) $v));
        $s = str_replace(["\u{2019}", "\u{2018}"], "'", $s);
        $s = preg_replace('/\s+/', ' ', $s);
        return $s;
    }
}
