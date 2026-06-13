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
 *   - any other value → populated
 *
 * Keys in 'array_fields' receive the empty-array check; all others use the
 * scalar (null/empty-string/whitespace) check.
 *
 * Quick Match: high-signal subset; fast initial sort.
 * Full Match:  all scored fields; detailed side-by-side. Supersedes Quick Match.
 */

return [

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
    ],

];
