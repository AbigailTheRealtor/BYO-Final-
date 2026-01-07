<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Landlord Agent Broker Compensation Terms Field Order
    |--------------------------------------------------------------------------
    |
    | This config defines the canonical order of fields in the Broker Compensation
    | & Agency Agreement Terms section for Hire a Landlord's Agent listings.
    | Used across: Listing Creation, Listing View, Agent Bid, Counter Bid,
    | View Bid/Counter screens, and PDF summaries.
    |
    */

    'field_order' => [
        'landlord_broker_lease_fee',
        'tenant_broker_commission_structure',    // Residential only
        'tenant_broker_commission_fee',          // Residential only
        'payment_timing_broker_fees',
        'lease_renewal_extension_fee',
        'expansion_commission_lease_amendment',  // Commercial only
        'interested_property_management',
        'interested_lease_option_agreement',
        'interested_in_selling',
        'landlord_broker_purchase_fee',
        'protection_period_days',
        'early_termination_fee',                 // Residential only
        'landlord_agency_agreement_timeframe',
        'acceptable_brokerage_relationship',
        'additional_terms',
    ],

    'residential_order' => [
        'landlord_broker_lease_fee',
        'tenant_broker_commission_structure',
        'tenant_broker_commission_fee',
        'payment_timing_broker_fees',
        'lease_renewal_extension_fee',
        'interested_property_management',
        'interested_lease_option_agreement',
        'interested_in_selling',
        'landlord_broker_purchase_fee',
        'protection_period_days',
        'early_termination_fee',
        'landlord_agency_agreement_timeframe',
        'acceptable_brokerage_relationship',
        'additional_terms',
    ],

    'commercial_order' => [
        'landlord_broker_lease_fee',
        'payment_timing_broker_fees',
        'lease_renewal_extension_fee',
        'expansion_commission_lease_amendment',
        'interested_property_management',
        'interested_lease_option_agreement',
        'interested_in_selling',
        'landlord_broker_purchase_fee',
        'protection_period_days',
        'landlord_agency_agreement_timeframe',
        'acceptable_brokerage_relationship',
        'additional_terms',
    ],

    'labels' => [
        'landlord_broker_lease_fee' => "Landlord's Broker Lease Fee",
        'tenant_broker_commission_structure' => "Tenant's Broker Commission Structure",
        'tenant_broker_commission_fee' => "Tenant's Broker Commission Fee",
        'payment_timing_broker_fees' => 'Payment Timing for Broker Fees',
        'lease_renewal_extension_fee' => 'Lease Renewal/Extension Fee',
        'expansion_commission_lease_amendment' => 'Expansion Commission for Lease Amendment',
        'interested_property_management' => 'Interested in Property Management',
        'interested_lease_option_agreement' => 'Interested in Offering a Lease-Option Agreement',
        'interested_in_selling' => 'Interested in Selling',
        'landlord_broker_purchase_fee' => "Landlord's Broker Purchase Fee",
        'protection_period_days' => 'Protection Period Timeframe (Days)',
        'early_termination_fee' => 'Early Termination Fee',
        'landlord_agency_agreement_timeframe' => 'Landlord Agency Agreement Timeframe',
        'acceptable_brokerage_relationship' => 'Acceptable Brokerage Relationship',
        'additional_terms' => 'Additional Terms',
    ],

    'visibility' => [
        'tenant_broker_commission_structure' => 'residential_only',
        'tenant_broker_commission_fee' => 'residential_only',
        'expansion_commission_lease_amendment' => 'commercial_only',
        'early_termination_fee' => 'residential_only',
    ],
];
