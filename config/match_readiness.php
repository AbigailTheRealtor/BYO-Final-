<?php

/**
 * Match Readiness Configuration
 *
 * Defines the required field sets for Quick Match and Full Match readiness
 * per agent role. Sourced from Section H.2 of the Agent Offer Preset ↔ Bid
 * Field Crosswalk Audit (docs/audits/AGENT_OFFER_PRESET_BID_CROSSWALK_AUDIT.md).
 *
 * Field population rules (enforced by MatchReadinessService):
 *   - null            → not populated
 *   - ''              → not populated
 *   - whitespace-only → not populated
 *   - []              → not populated (array fields)
 *   - global_placeholders (see below) → not populated
 *   - any other value → populated
 *
 * Keys in 'array_fields' receive the empty-array check; all others use the
 * scalar (null/empty-string/whitespace) check.
 *
 * Quick Match: high-signal subset; fast initial sort.
 * Full Match:  all scored fields; detailed side-by-side. Supersedes Quick Match.
 *
 * Weighting framework (P5 — weights inactive until explicitly enabled):
 *   See 'weights' key below. All weights default to 1.0 (equal weighting).
 *   The '_enabled' flag must be set to true in a future phase before weights
 *   are applied to scoring calculations. ScoreBreakdownService and
 *   CompatibilityScoreService must not read or apply these weights until then.
 */

return [

    /*
     * Global placeholder/default values that are treated as "not populated"
     * for all scalar fields across all roles.
     * This covers numeric inputs where a user may have typed 0 without
     * actually intending to specify a zero value.
     */
    'global_placeholders' => ['0', '0.00'],

    // ── Seller ──────────────────────────────────────────────────────────────
    'seller' => [
        'array_fields' => ['services'],

        'quick_match' => [
            'services',
            'commission_structure',
            'purchase_fee_type',
            'purchase_fee_percentage',
            'protection_period',
            'agency_agreement_timeframe',
            'brokerage_relationship',
        ],

        'full_match' => [
            // All Quick Match fields
            'services',
            'commission_structure',
            'purchase_fee_type',
            'purchase_fee_percentage',
            'protection_period',
            'agency_agreement_timeframe',
            'brokerage_relationship',
            // Full Match additions (Seller)
            'purchase_fee_flat',
            'early_termination_fee_option',
            'retainer_fee_option',
            'nominal',
            'commission_structure_type',
            'seller_leasing_fee_type',
        ],
    ],

    // ── Buyer ────────────────────────────────────────────────────────────────
    'buyer' => [
        'array_fields' => ['services'],

        'quick_match' => [
            'services',
            'commission_structure',
            'purchase_fee_type',
            'purchase_fee_percentage',
            'lease_fee_type',
            'protection_period',
            'agency_agreement_timeframe',
            'brokerage_relationship',
        ],

        'full_match' => [
            // All Quick Match fields
            'services',
            'commission_structure',
            'purchase_fee_type',
            'purchase_fee_percentage',
            'lease_fee_type',
            'protection_period',
            'agency_agreement_timeframe',
            'brokerage_relationship',
            // Full Match additions (Buyer)
            'purchase_fee_flat',
            'lease_fee_percentage',
            'early_termination_fee_option',
            'retainer_fee_option',
        ],
    ],

    // ── Landlord ─────────────────────────────────────────────────────────────
    'landlord' => [
        'array_fields' => ['services'],

        'quick_match' => [
            'services',
            'commission_structure',
            'purchase_fee_type',
            'purchase_fee_percentage',
            'protection_period',
            'agency_agreement_timeframe',
            'brokerage_relationship',
        ],

        'full_match' => [
            // All Quick Match fields
            'services',
            'commission_structure',
            'purchase_fee_type',
            'purchase_fee_percentage',
            'protection_period',
            'agency_agreement_timeframe',
            'brokerage_relationship',
            // Full Match additions (Landlord)
            'purchase_fee_flat',
            'early_termination_fee_option',
            'renewal_fee_type',
            'broker_fee_timing',
            'tenant_broker_commission_structure',
            'expansion_commission_percentage',
            'interested_in_property_management',
            'interested_in_selling',
        ],

        /*
         * Conditional groups (Full Match only):
         * If parent_field has one of parent_values, required_children must
         * also be populated or they are added to missing_full.
         */
        'conditional_groups' => [
            [
                'parent_field'      => 'broker_fee_timing',
                'parent_values'     => ['other'],
                'required_children' => ['broker_fee_timing_other'],
            ],
            [
                'parent_field'      => 'interested_in_selling',
                'parent_values'     => ['Yes'],
                'required_children' => ['interested_in_selling_type'],
            ],
        ],
    ],

    // ── Tenant ───────────────────────────────────────────────────────────────
    'tenant' => [
        'array_fields' => ['services'],

        'quick_match' => [
            'services',
            'commission_structure',
            'purchase_fee_type',
            'purchase_fee_percentage',
            'lease_fee_type',
            'protection_period',
            'agency_agreement_timeframe',
            'brokerage_relationship',
        ],

        'full_match' => [
            // All Quick Match fields
            'services',
            'commission_structure',
            'purchase_fee_type',
            'purchase_fee_percentage',
            'lease_fee_type',
            'protection_period',
            'agency_agreement_timeframe',
            'brokerage_relationship',
            // Full Match additions (Tenant)
            'purchase_fee_flat',
            'lease_fee_percentage',
            'early_termination_fee_option',
            'retainer_fee_option',
            'broker_fee_timing',
        ],

        /*
         * Conditional groups (Full Match only):
         * If parent_field has one of parent_values, required_children must
         * also be populated or they are added to missing_full.
         */
        'conditional_groups' => [
            [
                'parent_field'      => 'broker_fee_timing',
                'parent_values'     => ['other'],
                'required_children' => ['broker_fee_timing_other'],
            ],
        ],
    ],

    /*
     * ── Weighting Framework (P5 — INACTIVE) ─────────────────────────────────
     *
     * Per-field weights for future weighted scoring. All values default to 1.0
     * (equal weight across all fields).
     *
     * ACTIVATION RULES:
     *   - '_enabled' is false. Score calculations in CompatibilityScoreService
     *     and ScoreBreakdownService MUST NOT read or apply these weights until
     *     '_enabled' is explicitly set to true in a future phase.
     *   - Changing individual weight values while '_enabled' is false has no
     *     effect on any score.
     *   - To activate: set '_enabled' => true AND update the scoring services
     *     to read config('match_readiness.weights.<role>.<field>').
     *
     * Weight semantics (for future use):
     *   - 1.0  = standard weight (default for all fields)
     *   - >1.0 = field has more influence on the overall score
     *   - <1.0 = field has less influence on the overall score
     *   - 0.0  = field excluded from scoring entirely
     */
    'weights' => [
        '_enabled' => false,

        'seller' => [
            'services'                     => 1.0,
            'commission_structure'         => 1.0,
            'purchase_fee_type'            => 1.0,
            'purchase_fee_percentage'      => 1.0,
            'protection_period'            => 1.0,
            'agency_agreement_timeframe'   => 1.0,
            'brokerage_relationship'       => 1.0,
            'purchase_fee_flat'            => 1.0,
            'early_termination_fee_option' => 1.0,
            'retainer_fee_option'          => 1.0,
            'nominal'                      => 1.0,
            'commission_structure_type'    => 1.0,
            'seller_leasing_fee_type'      => 1.0,
        ],

        'buyer' => [
            'services'                     => 1.0,
            'commission_structure'         => 1.0,
            'purchase_fee_type'            => 1.0,
            'purchase_fee_percentage'      => 1.0,
            'lease_fee_type'               => 1.0,
            'protection_period'            => 1.0,
            'agency_agreement_timeframe'   => 1.0,
            'brokerage_relationship'       => 1.0,
            'purchase_fee_flat'            => 1.0,
            'lease_fee_percentage'         => 1.0,
            'early_termination_fee_option' => 1.0,
            'retainer_fee_option'          => 1.0,
        ],

        'landlord' => [
            'services'                            => 1.0,
            'commission_structure'                => 1.0,
            'purchase_fee_type'                   => 1.0,
            'purchase_fee_percentage'             => 1.0,
            'protection_period'                   => 1.0,
            'agency_agreement_timeframe'          => 1.0,
            'brokerage_relationship'              => 1.0,
            'purchase_fee_flat'                   => 1.0,
            'early_termination_fee_option'        => 1.0,
            'renewal_fee_type'                    => 1.0,
            'broker_fee_timing'                   => 1.0,
            'tenant_broker_commission_structure'  => 1.0,
            'expansion_commission_percentage'     => 1.0,
            'interested_in_property_management'   => 1.0,
            'interested_in_selling'               => 1.0,
        ],

        'tenant' => [
            'services'                     => 1.0,
            'commission_structure'         => 1.0,
            'purchase_fee_type'            => 1.0,
            'purchase_fee_percentage'      => 1.0,
            'lease_fee_type'               => 1.0,
            'protection_period'            => 1.0,
            'agency_agreement_timeframe'   => 1.0,
            'brokerage_relationship'       => 1.0,
            'purchase_fee_flat'            => 1.0,
            'lease_fee_percentage'         => 1.0,
            'early_termination_fee_option' => 1.0,
            'retainer_fee_option'          => 1.0,
            'broker_fee_timing'            => 1.0,
        ],
    ],

];
