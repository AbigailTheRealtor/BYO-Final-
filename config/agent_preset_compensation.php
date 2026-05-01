<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Agent Preset Compensation Options
    |--------------------------------------------------------------------------
    |
    | Canonical option arrays for all dropdown fields in the Agent Profile
    | Offer Preset editor (resources/views/agent-presets/edit.blade.php).
    |
    | Each entry is an associative array: [stored_value => display_label].
    | Stored values are never changed here — aliases are handled by including
    | both the legacy value and the current value as separate keyed entries,
    | each with the correct display label.
    |
    | See .local/agent_preset_compensation_phase3_report.md for full audit.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Common (all roles)
    |--------------------------------------------------------------------------
    */
    'common' => [

        /*
         * Acceptable Brokerage Relationship — same for all four roles.
         */
        'brokerage_relationship' => [
            'Transaction Broker Representation' => 'Transaction Broker Representation',
            'Single Agent Representation'       => 'Single Agent Representation',
            'Dual Agency Representation'        => 'Dual Agency Representation',
            'No Brokerage Relationship'         => 'No Brokerage Relationship',
        ],

        /*
         * Agency Agreement Timeframe — standard options (months).
         * The "Other / Custom" option is NOT included here because its stored
         * value differs by role: buyer stores 'custom', all others store 'Other'.
         * That option is left hardcoded in the blade with an inline comment.
         */
        'agency_agreement_timeframe' => [
            '3 Months'  => '3 Months',
            '6 Months'  => '6 Months',
            '9 Months'  => '9 Months',
            '12 Months' => '12 Months',
        ],

        /*
         * Sales Tax sub-select — reused wherever sales tax appears inline.
         */
        'sales_tax' => [
            'including' => 'Including Sales Tax',
            'excluding' => 'Excluding Sales Tax',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Buyer Agent
    |--------------------------------------------------------------------------
    */
    'buyer' => [

        /*
         * Buyer's Broker Commission Structure.
         */
        'commission_structure' => [
            'Buyer Pays Out-of-Pocket'          => "Buyer Pays Out-of-Pocket",
            'Requested From Seller in the Offer' => "Requested From Seller in the Offer",
        ],

        /*
         * Buyer's Broker Purchase Fee — stored values use full text labels.
         * (Contrast: seller.purchase_fee_type uses short slugs.)
         */
        'purchase_fee_type' => [
            'Flat Fee'                                        => 'Flat Fee',
            'Percentage of the Total Purchase Price'          => 'Percentage of the Total Purchase Price',
            'Percentage of the Total Purchase Price + Flat Fee' => 'Percentage of the Total Purchase Price + Flat Fee',
            'other'                                           => 'Other',
        ],

        /*
         * Buyer's Broker Lease Fee (shown when "Interested in a Lease Agreement" = Yes).
         * Options differ by property type: residential vs everything else (commercial).
         * NOTE: The flat-fee stored value for buyers is the slug 'flat' (not 'Flat Fee').
         * This differs from tenant lease_fee_type which stores 'Flat Fee'. See report.
         */
        'lease_fee_type' => [
            'residential' => [
                'flat'                                            => 'Flat Fee',
                'Percentage of Monthly Rent'                      => 'Percentage of Monthly Rent',
                'Percentage of the Gross Lease Value'             => 'Percentage of the Gross Lease Value',
                'Flat Fee + Percentage of the Gross Lease Value'  => 'Flat Fee + Percentage of the Gross Lease Value',
                'other'                                           => 'Other',
            ],
            'commercial' => [
                'flat'                                           => 'Flat Fee',
                'Percentage of the Net Aggregate Rent'           => 'Percentage of the Net Aggregate Rent',
                'Flat Fee + Percentage of the Net Aggregate Rent' => 'Flat Fee + Percentage of the Net Aggregate Rent',
                'other'                                          => 'Other',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Seller Agent
    |--------------------------------------------------------------------------
    */
    'seller' => [

        /*
         * Seller's Broker Purchase Fee.
         * IMPORTANT: Seller uses SHORT SLUG values ('percentage', 'flat', 'combo'),
         * not full-text labels like buyer. Do not change these stored values.
         */
        'purchase_fee_type' => [
            'percentage' => 'Percentage of the Total Purchase Price',
            'flat'       => 'Flat Fee',
            'combo'      => 'Percentage of the Total Purchase Price + Flat Fee',
            'other'      => 'Other',
        ],

        /*
         * Buyer's Broker Commission Structure (shown on Seller preset).
         */
        'commission_structure' => [
            "Seller's Broker to Compensate Buyer's Broker from Seller's Broker Commission"
                => "Seller's Broker to Compensate Buyer's Broker from Seller's Broker Commission",
            "Seller to Pay Buyer's Broker Separately"
                => "Seller to Pay Buyer's Broker Separately",
            "No Compensation Offered to the Buyer's Broker"
                => "No Compensation Offered to the Buyer's Broker",
        ],

        /*
         * Buyer's Broker Commission Fee type (sub-select under commission_structure).
         */
        'commission_structure_type' => [
            'Percentage of the Total Purchase Price' => 'Percentage of the Total Purchase Price',
            'Flat Fee'                               => 'Flat Fee',
            'other'                                  => 'Other',
        ],

        /*
         * Seller's Broker Leasing Fee (when interested in offering a lease agreement).
         * Options differ by property type group:
         *   residential_income_vacant_land — gross-lease / monthly-rent based options
         *   commercial_business           — net-aggregate / gross-rent / month's rent options
         */
        'seller_leasing_fee_type' => [
            'residential_income_vacant_land' => [
                'Percentage of the Rent Due Each Rental Period' => 'Percentage of the Rent Due Each Rental Period',
                'Percentage of the Gross Lease Value'           => 'Percentage of the Gross Lease Value',
                "Percentage of the First Month's Rent"          => "Percentage of the First Month's Rent",
                'Flat Fee'                                      => 'Flat Fee',
                'other'                                         => 'Other',
            ],
            'commercial_business' => [
                'Percentage of Net Aggregate Rent'  => 'Percentage of Net Aggregate Rent',
                'Percentage of Gross Rent'          => 'Percentage of Gross Rent',
                "Percentage of Month's Rent"        => "Percentage of Month's Rent",
                'Flat Fee'                          => 'Flat Fee',
                // Phase 3: no 'other' here — the commercial/business seller leasing block
                // in the blade has no Other option. Left as-is to match original.
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Landlord Agent
    |--------------------------------------------------------------------------
    */
    'landlord' => [

        /*
         * Landlord's Broker Lease Fee.
         * Stored in DB as 'purchase_fee_type' (same field name as buyer/seller purchase fee).
         * Options differ by property type: residential vs commercial.
         */
        'purchase_fee_type' => [
            'residential' => [
                'Percentage of the Rent Due Each Rental Period' => 'Percentage of the Rent Due Each Rental Period',
                'Percentage of the Gross Lease Value'           => 'Percentage of the Gross Lease Value',
                "Percentage of the First Month’s Rent"          => "Percentage of the First Month’s Rent",
                'Flat Fee'                                      => 'Flat Fee',
                'other'                                         => 'Other',
            ],
            'commercial' => [
                'Percentage of the Net Aggregate Rent' => 'Percentage of the Net Aggregate Rent',
                'Percentage of the Gross Rent'         => 'Percentage of the Gross Rent',
                "Percentage of Month’s Rent"           => "Percentage of Month’s Rent",
                'Flat Fee'                             => 'Flat Fee',
                'other'                                => 'Other',
            ],
        ],

        /*
         * Tenant's Broker Commission Structure (Landlord Residential only).
         */
        'tenant_broker_commission_structure' => [
            "Landlord's Broker to Compensate Tenant's Broker from Landlord's Broker Commission"
                => "Landlord's Broker to Compensate Tenant's Broker from Landlord's Broker Commission",
            "Landlord to Pay Tenant's Broker Separately"
                => "Landlord to Pay Tenant's Broker Separately",
            "No Compensation Offered to the Tenant's Broker"
                => "No Compensation Offered to the Tenant's Broker",
        ],

        /*
         * Tenant's Broker Commission Fee (Landlord Residential only).
         * LEGACY NOTE: The stored value for flat fee is 'Flat fee' (lowercase 'f' in 'fee').
         * The display label shows 'Flat Fee' (uppercase F). Only the legacy stored value is
         * kept as the option key. The blade normalizes any 'Flat Fee' DB value to 'Flat fee'
         * before the @selected comparison, so both variants render the option as selected.
         */
        'tenant_broker_fee_structure' => [
            'Percentage of the Rent Due Each Rental Period' => 'Percentage of the Rent Due Each Rental Period',
            'Percentage of the Gross Lease Value'           => 'Percentage of the Gross Lease Value',
            "Percentage of the First Month’s Rent"          => "Percentage of the First Month’s Rent",
            // Legacy stored value is 'Flat fee' (lowercase 'f'). Some records may have 'Flat Fee'.
            // The blade normalizes the stored value to 'Flat fee' before @selected comparison
            // so both cases render the single option as selected. See edit.blade.php.
            'Flat fee'                                      => 'Flat Fee',
            'Other'                                         => 'Other',
        ],

        /*
         * Payment Timing for Broker Fees — differs by property type.
         * Residential: full-text stored values (same as landlord split_payment_due style).
         * Commercial:  mixed — first option is the slug 'full_execution', rest are full text.
         *              This inconsistency is a known legacy issue; preserved exactly.
         */
        'broker_fee_timing' => [
            'residential' => [
                'Deducted from Rent Collected'                    => 'Deducted from Rent Collected',
                'Paid Within Calendar Days After Executed Lease'  => 'Paid Within Calendar Days After Executed Lease',
                'Paid Within Calendar Days of Tenant Rent Payment' => 'Paid Within Calendar Days of Tenant Rent Payment',
                'other'                                           => 'Other',
            ],
            'commercial' => [
                // First option uses a slug; remaining use full text. Preserved as-is (legacy).
                'full_execution'                                              => 'Full amount upon execution of lease, sales contract, or other transfer agreement',
                '50% due upon execution, 50% due upon commencement of agreement' => '50% due upon execution, 50% due upon commencement of agreement',
                '50% due upon execution, 50% due upon occupancy of premises'     => '50% due upon execution, 50% due upon occupancy of premises',
                'Other'                                                           => 'Other',
            ],
        ],

        /*
         * Lease Renewal/Extension Fee — differs by property type.
         */
        'renewal_fee_type' => [
            'residential' => [
                'Percentage of the Rent Due Each Rental Period' => 'Percentage of the Rent Due Each Rental Period',
                'Percentage of the Gross Lease Value'           => 'Percentage of the Gross Lease Value',
                "Percentage of the First Month's Rent"          => "Percentage of the First Month's Rent",
                'Flat Fee'                                      => 'Flat Fee',
                'other'                                         => 'Other',
            ],
            'commercial' => [
                'Percentage of the Net Aggregate Rent' => 'Percentage of the Net Aggregate Rent',
                'Percentage of the Gross Rent'         => 'Percentage of the Gross Rent',
                "Percentage of Month's Rent"           => "Percentage of Month's Rent",
                'Flat Fee'                             => 'Flat Fee',
                'other'                                => 'Other',
            ],
        ],

        /*
         * Property Management Fee type (when interested_in_property_management = 'yes').
         */
        'property_management_fee_type' => [
            'Percentage of the Gross Lease Value'           => 'Percentage of the Gross Lease Value',
            'Percentage of the Rent Due Each Rental Period' => 'Percentage of the Rent Due Each Rental Period',
            'Flat Fee'                                      => 'Flat Fee',
            'Other'                                         => 'Other',
        ],

        /*
         * Landlord's Broker Purchase Fee type (when interested_in_selling = 'Yes').
         */
        'selling_fee_type' => [
            'Percentage of the Total Purchase Price'          => 'Percentage of the Total Purchase Price',
            'Percentage of the Total Purchase Price + Flat Fee' => 'Percentage of the Total Purchase Price + Flat Fee',
            'Flat Fee'                                        => 'Flat Fee',
            'Other'                                           => 'Other',
        ],

        /*
         * Payment Timing for Broker Fees — Landlord-section split_payment_due field.
         * NOTE: The third option has a KNOWN TYPO in its stored value ('uponoccupancy'
         * — missing space). The display label shows the corrected text with a space.
         * The typo-value is the key; it must be preserved so existing saved data
         * still renders as @selected. Do NOT fix the key without a migration.
         */
        'split_payment_due' => [
            'Full amount upon execution of lease, sales contract, or other transfer agreement'
                => 'Full amount upon execution of lease, sales contract, or other transfer agreement',
            '50% due upon execution, 50% due upon commencement of agreement'
                => '50% due upon execution, 50% due upon commencement of agreement',
            '50% due upon execution, 50% due uponoccupancy of premises'
                => '50% due upon execution, 50% due upon occupancy of premises', // display corrects typo in key
            'Other'
                => 'Other',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Tenant Agent
    |--------------------------------------------------------------------------
    */
    'tenant' => [

        /*
         * Tenant's Broker Commission Structure.
         * NOTE: Stored values ('Out-of-Pocket Payment', 'Included in Offer') differ from
         * their display labels ('Tenant Pays Out-of-Pocket', 'Requested From Landlord in
         * the Offer'). Preserved exactly as in the original blade.
         */
        'commission_structure' => [
            'Out-of-Pocket Payment' => "Tenant Pays Out-of-Pocket",
            'Included in Offer'     => "Requested From Landlord in the Offer",
        ],

        /*
         * Tenant's Broker Lease Fee — differs by property type.
         * NOTE: Tenant stores 'Flat Fee' (capital F), whereas buyer stores 'flat' (slug).
         * This is a known cross-role inconsistency. Preserved exactly.
         */
        'lease_fee_type' => [
            'residential' => [
                'Flat Fee'                                        => 'Flat Fee',
                'Percentage of Monthly Rent'                      => 'Percentage of Monthly Rent',
                'Percentage of the Gross Lease Value'             => 'Percentage of the Gross Lease Value',
                'Flat Fee + Percentage of the Gross Lease Value'  => 'Flat Fee + Percentage of the Gross Lease Value',
                'other'                                           => 'Other',
            ],
            'commercial' => [
                'Flat Fee'                                       => 'Flat Fee',
                'Percentage of the Net Aggregate Rent'           => 'Percentage of the Net Aggregate Rent',
                'Flat Fee + Percentage of the Net Aggregate Rent' => 'Flat Fee + Percentage of the Net Aggregate Rent',
                'other'                                          => 'Other',
            ],
        ],

        /*
         * Payment Timing for Broker Fees — differs by property type.
         * Residential: full-text stored values (same as landlord.broker_fee_timing.residential).
         * Commercial:  all SHORT SLUGS (unlike landlord commercial which uses mixed styles).
         *              This cross-role inconsistency is preserved exactly.
         */
        'broker_fee_timing' => [
            'residential' => [
                'Deducted from Rent Collected'                    => 'Deducted from Rent Collected',
                'Paid Within Calendar Days After Executed Lease'  => 'Paid Within Calendar Days After Executed Lease',
                'Paid Within Calendar Days of Tenant Rent Payment' => 'Paid Within Calendar Days of Tenant Rent Payment',
                'other'                                           => 'Other',
            ],
            'commercial' => [
                // All options use short slugs for tenant commercial (legacy — see report).
                'full_execution'                  => 'Full amount upon execution of lease, sales contract, or other transfer agreement',
                'half_execution_half_commencement' => '50% due upon execution, 50% due upon commencement of agreement',
                'half_execution_half_occupancy'    => '50% due upon execution, 50% due upon occupancy of premises',
                'other'                            => 'Other',
            ],
        ],

        /*
         * Tenant's Broker Purchase Fee (when interested_purchase_fee_type = 'Yes').
         * Uses full-text stored values (same as buyer.purchase_fee_type).
         */
        'purchase_fee_type' => [
            'Flat Fee'                                        => 'Flat Fee',
            'Percentage of the Total Purchase Price'          => 'Percentage of the Total Purchase Price',
            'Percentage of the Total Purchase Price + Flat Fee' => 'Percentage of the Total Purchase Price + Flat Fee',
            'other'                                           => 'Other',
        ],

    ],

];
