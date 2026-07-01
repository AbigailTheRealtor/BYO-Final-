<?php
/**
 * Canonical property type / subtype / attribute option lists — single source of truth.
 *
 * Consumed by:
 *   - resources/views/offers/_property_being_offered_form.blade.php  (type/subtype selects)
 *   - resources/views/offers/_property_attributes_form.blade.php      (attribute fields)
 *   - offer-seller-tabs/commission-based/property-preferences.blade.php ($bathroomOptions, $acreageRes)
 *   - offer-landlord-tabs/commission-based/property-preferences.blade.php ($bathroomOptions, $acreageRes)
 *
 * Buyer type/subtype options match offer-seller-listing.blade.php.
 * Tenant type/subtype options match offer-landlord-listing.blade.php.
 * bedroom_options matches $bedroomsRes in property-preferences.blade.php (1-10 + Other).
 * bathroom_options matches $bathroomOptions in property-preferences.blade.php.
 * acreage_options matches $acreageRes in property-preferences.blade.php.
 */
return [

    'buyer' => [
        'types' => [
            'Residential',
            'Income',
            'Commercial',
            'Business',
            'Vacant Land',
        ],
        'subtypes' => [
            'Residential' => [
                '½ Duplex', '1/3 Triplex', '1/4 Quadplex', 'Condo-Hotel', 'Condominium',
                'Dock-Rackominium', 'Farm', 'Garage Condo', 'Manufactured Home- Post 1977',
                'Mobile Home- Pre 1976', 'Modular Home', 'Single Family Residence',
                'Townhouse', 'Villa',
            ],
            'Income' => [
                'Duplex', 'Five or More', 'Quadplex', 'Triplex',
            ],
            'Commercial' => [
                'Agriculture', 'Assembly Building', 'Business', 'Five or More', 'Hotel/Motel',
                'Industrial', 'Mixed Use', 'Office', 'Restaurant', 'Retail', 'Warehouse',
            ],
            'Business' => [
                'Agriculture', 'Assembly Building', 'Business', 'Five or More', 'Hotel/Motel',
                'Industrial', 'Mixed Use', 'Office', 'Restaurant', 'Retail', 'Warehouse',
            ],
            'Vacant Land' => [
                'Agricultural', 'Billboard Site', 'Business', 'Cattle', 'Commercial', 'Farm',
                'Fishery', 'Highway Frontage', 'Horses', 'Industrial', 'Land Fill', 'Livestock',
                'Mixed Use', 'Multi Family', 'Nursery', 'Orchard', 'Pasture', 'Poultry',
                'Ranch', 'Residential', 'Retail', 'Row Crops', 'Sod Farm', 'Subdivision',
                'Timber', 'Tracts', 'Trans/Cell Tower', 'Tree Farm', 'Unimproved Land',
                'Well Field', 'Other',
            ],
        ],
    ],

    /*
     * Ask AI Knowledge Base gating aliases — single source of truth for reconciling the
     * two property-type vocabularies used in the app.
     *
     * Seller and Buyer listings store SHORT property_type values (Residential, Income,
     * Commercial, Business, Vacant Land — see the 'buyer'/seller 'types' above). Landlord
     * and Tenant listings store LONG values (Residential Property, Commercial Property).
     * The AI-FAQ config 'gating' maps (config/ai_faq_*.php) are keyed by the LONG forms.
     *
     * ai-questions-input.blade.php normalizes the stored property_type through this map
     * before the gating lookup so every property type resolves to its intended KB group.
     * Any value NOT listed here (already-long forms, Vacant Land) passes through unchanged.
     *
     * This is intentionally a normalization layer, NOT a change to the stored values:
     * property_type is persisted and consumed (match scoring, display helpers, dozens of
     * blade conditionals) in its native short/long form, so backward compatibility is
     * preserved for every existing listing.
     */
    'ai_faq_gating_aliases' => [
        'Residential' => 'Residential Property',
        'Income'      => 'Income Property',
        'Commercial'  => 'Commercial Property',
        'Business'    => 'Business Opportunity',
        // 'Vacant Land' is identical in both vocabularies — no alias needed.
    ],

    'tenant' => [
        'types' => [
            'Residential Property',
            'Commercial Property',
        ],
        'subtypes' => [
            'Residential Property' => [
                '½ Duplex', '1/3 Triplex', '1/4 Quadplex', 'Apartments', 'Condo-Hotel',
                'Condominium', 'Dock-Rackominium', 'Farm', 'Garage Condo',
                'Manufactured Home- Post 1977', 'Mobile Home- Pre 1976', 'Modular Home',
                'Single Family Residence', 'Townhouse', 'Villa',
            ],
            'Commercial Property' => [
                'Agriculture', 'Assembly Building', 'Business', 'Five or More', 'Hotel/Motel',
                'Industrial', 'Mixed Use', 'Office', 'Restaurant', 'Retail', 'Warehouse',
            ],
        ],
    ],

    /*
     * Bedroom options — matches $bedroomsRes defined in property-preferences.blade.php (line ~194).
     * Values 1-10 + Other; used by _property_attributes_form.blade.php attribute partial.
     */
    'bedroom_options' => ['1','2','3','4','5','6','7','8','9','10','Other'],

    /*
     * Bathroom options — sourced from $bathroomOptions in property-preferences.blade.php (line ~47).
     * Includes half-bath increments to match seller and landlord listing forms exactly.
     * Used by offer-seller-tabs and offer-landlord-tabs property-preferences.blade.php via:
     *   array_map(fn($v) => ['name' => $v], config('property_types.bathroom_options'))
     */
    'bathroom_options' => [
        '1', '1.5', '2', '2.5', '3', '3.5', '4', '4.5',
        '5', '5.5', '6', '6.5', '7', '7.5', '8', '8.5', '9', '9.5', '10', 'Other',
    ],

    /*
     * Acreage options — sourced from $acreageRes in property-preferences.blade.php (line ~227).
     * Used by offer-seller-tabs, offer-landlord-tabs, and _property_attributes_form.blade.php.
     */
    'acreage_options' => [
        '0 to less than 1/4 acre',
        '1/4 to less than 1/2 acre',
        '1/2 to less than 1 acre',
        '1 to less than 2 acres',
        '2 to less than 5 acres',
        '5 to less than 10 acres',
        '10 to less than 20 acres',
        '20 to less than 50 acres',
        '50 to less than 100 acres',
        '100 to less than 200 acres',
        '200 to less than 500 acres',
        '500+ acres',
        'Non-Applicable',
    ],
];
