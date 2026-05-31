<?php

namespace App\Exports\ListingFieldMaps;

class SellerFieldMap
{
    public static function sections(): array
    {
        return [
            'Listing Details' => [
                'Service Type' => 'service_type',
                'Listing Status' => 'listing_status',
                'Listing Type' => 'auction_type',
                'Currently Working with an Agent?' => 'working_with_agent',
                'Current Status' => 'current_status',
                'Listing Date' => 'listing_date',
                'Desired Agent Hire Date' => 'desired_agent_hire_date',
                'Expiration Date' => 'expiration_date',
                'Auction Length' => 'auction_time',
            ],

            'Location' => [
                'Property Address' => 'address',
                'City' => 'property_city',
                'County' => 'property_county',
                'State' => 'property_state',
                'ZIP Code' => 'property_zip',
                'Acceptable Cities' => 'cities',
                'Acceptable Counties' => 'counties',
                'Acceptable ZIP Codes' => 'zipCodes',
            ],

            'Property Details' => [
                'Property Type' => 'property_type',
                'Property Style' => 'property_items',
                'Leasing Space' => 'leasing_space',
                'Property Condition' => 'condition_prop',
                'Bedrooms' => 'bedrooms',
                'Bathrooms' => 'bathrooms',
                'Minimum Heated Sq Ft' => 'minimum_heated_square',
                'Minimum Leaseable Sq Ft' => 'minimum_leaseable',
                'Minimum Acreage' => 'min_acreage',
                'Total Acreage' => 'total_acreage',
                'Appliances' => 'appliances',
                'Property Criteria' => 'property_criteria',
                'Unit Size' => 'unit_size',
                'Preference Details' => 'preferance_details',
            ],

            'Income & Investment Metrics' => [
                'Minimum Annual Net Income' => 'minimum_annual_net_income',
                'Minimum Cap Rate' => 'minimum_cap_rate',
                'Included Assets' => 'assets',
            ],

            'Additional Property Preferences' => [
                'Tenant Requirement' => 'tenant_require',
                'Carport Needed' => 'carport_needed',
                'Garage Needed' => 'garage_needed',
                'Garage/Parking Spaces' => 'garage_parking_spaces',
                'Garage/Parking Spaces Option' => 'garage_parking_spaces_option',
                'Pool Needed' => 'pool_needed',
                'Pool Type' => 'pool_type',
                'View' => 'view_preference',
                'Real Estate Purchase' => 'real_estate_purchase',
                'Number of Units' => 'number_of_unit',
                'Unit Number' => 'unit_number',
                'Number of Buildings' => 'unit_buildings',
                '55+ Community' => 'leasing_55_plus',
                'Non-Negotiable Amenities' => 'non_negotiable_amenities',
            ],

            'Pets' => [
                'Pets' => 'pets',
                'Number of Pets' => 'number_of_pets',
                'Breed of Pets' => 'breed_of_pets',
                'Pet Types' => 'type_of_pets',
                'Pet Weight (lbs)' => 'weight_of_pets',
                'Number of Occupants' => 'number_occupant',
            ],

            'Sale Terms' => [
                'Sale Provision' => 'sale_provision',
                'Assignment Allowed' => 'sale_provision_assignment',
                'Assignment Fee Type' => 'assignment_fee_type',
                'Assignment Fee Amount' => 'assignment_fee_amount',
                'Buyer May Sell Contract' => 'buyer_sell_contract',
            ],

            'Budget & Financing' => [
                'Maximum Budget' => 'maximum_budget',
                'Offered Financing' => 'offered_financing',
                'Cash Budget' => 'cash_budget',
                'Pre-Approved' => 'pre_approved',
                'Pre-Approval Amount' => 'pre_approval_amount',
                'Purchase Price' => 'purchase_price',
                'Budget' => 'budget',
                'Monthly Income' => 'monthly_income',
                'Credit Score Rating' => 'credit_scroe_rating',
                'Prior Eviction' => 'prior_eviction',
                'Eviction Explanation' => 'eviction_explanation',
                'Prior Felony' => 'prior_felony',
                'Prior Felony Explanation' => 'prior_felony_explanation',
            ],

            'Down Payment' => [
                'Down Payment Type' => 'down_payment_type',
                'Down Payment Amount' => 'down_payment_amount',
            ],

            'Seller Financing' => [
                'Seller Financing Type' => 'seller_financing_type',
                'Seller Financing Amount' => 'seller_financing_amount',
                'Interest Rate' => 'interest_rate',
                'Loan Duration' => 'loan_duration',
                'Prepayment Penalty' => 'prepayment_penalty',
                'Prepayment Penalty Amount' => 'prepayment_penalty_amount',
                'Balloon Payment Amount' => 'balloon_payment_amount',
                'Balloon Payment Date' => 'balloon_payment_date',
            ],

            'Assumable Loan' => [
                'Assumable Terms' => 'assumable_terms',
                'Max Assumable Rate' => 'max_assumable_rate',
                'Assumable Monthly Escrow' => 'assumable_monthly_escrow',
                'Assumable Loan Term Remaining' => 'assumable_loan_term_remaining',
                'Assumable Loan Origination Date' => 'assumable_loan_origination_date',
                'Assumable Loan Servicer' => 'assumable_loan_servicer',
                'Assumable Fee Type' => 'assumable_fee_type',
                'Assumable Fee Amount' => 'assumable_fee_amount',
                'Assumable Occupancy Requirement' => 'assumable_occupancy_requirement',
            ],

            'Gap / Additional Payments' => [
                'Max Monthly Payment' => 'max_monthly_payment',
                'Gap Payment Type' => 'gap_payment_type',
                'Gap Payment Amount' => 'gap_payment_amount',
                'Additional Cash' => 'additional_cash',
            ],

            'Exchange / Trade' => [
                'Exchange Item' => 'exchange_item',
                'Exchange Item Value' => 'exchange_item_value',
                'Exchange Item Condition' => 'exchange_item_condition',
                'Value Determination' => 'value_determination',
                'Exchange Transfer Method' => 'exchange_transfer_method',
                'Exchange Liens Disclosure' => 'exchange_liens_disclosure',
                'Exchange Liens Details' => 'exchange_liens_details',
                'Exchange Inspection Rights' => 'exchange_inspection_rights',
            ],

            'Lease Option' => [
                'Lease Option Price' => 'lease_option_price',
                'Lease Option Terms' => 'lease_option_terms',
                'Lease Option Duration' => 'lease_option_duration',
                'Lease Option Payment' => 'lease_option_payment',
                'Lease Option Conditions' => 'lease_option_conditions',
                'Has Option Fee' => 'has_option_fee',
                'Option Fee Amount' => 'option_fee_amount',
                'Seller Lease Option Fee Credit' => 'seller_lease_option_fee_credit',
                'Seller Lease Option Fee Credit %' => 'seller_lease_option_fee_credit_percent',
                'Seller Lease Option Maintenance' => 'seller_lease_option_maintenance',
                'Seller Lease Option Extension Terms' => 'seller_lease_option_extension_terms',
            ],

            'Lease Purchase' => [
                'Lease Purchase Price' => 'lease_purchase_price',
                'Lease Purchase Terms' => 'lease_purchase_terms',
                'Lease Purchase Duration' => 'lease_purchase_duration',
                'Lease Purchase Payment' => 'lease_purchase_payment',
                'Lease Purchase Conditions' => 'lease_purchase_conditions',
                'Seller Lease Purchase Rent Credit' => 'seller_lease_purchase_rent_credit',
                'Seller Lease Purchase Rent Credit Type' => 'seller_lease_purchase_rent_credit_type',
                'Seller Lease Purchase Rent Credit Amount' => 'seller_lease_purchase_rent_credit_amount',
                'Seller Lease Purchase Deposit' => 'seller_lease_purchase_deposit',
                'Seller Lease Purchase Maintenance' => 'seller_lease_purchase_maintenance',
                'Seller Lease Purchase Extension Terms' => 'seller_lease_purchase_extension_terms',
                'Lease Purchase Option Fee' => 'lease_purchase_option_fee',
                'Lease Purchase Option Fee Amount' => 'lease_purchase_option_fee_amount',
            ],

            'Cryptocurrency' => [
                'Cryptocurrency Type' => 'cryptocurrency_type',
                'Crypto Percentage' => 'crypto_percentage',
                'Cash Percentage (Crypto)' => 'cash_percentage_crypto',
            ],

            'NFT' => [
                'NFT Description' => 'nft_description',
                'NFT Percentage' => 'nft_percentage',
                'Cash Percentage (NFT)' => 'cash_percentage_nft',
            ],

            'Lease Details' => [
                'Lease For' => 'lease_for',
                'Lease By' => 'lease_by',
                'Lease Date' => 'lease_date',
            ],

            'Services' => [
                'Services' => 'services',
                'Custom Services' => 'custom_services',
                'Marketing: Include Marketing Fee' => 'include_marketing_fee',
                'Marketing: Email Marketing' => 'email_marketing_fee',
                'Marketing: Email Notifications' => 'email_notifications_fee',
                'Marketing: Launch Ads' => 'launch_ads',
                'Marketing: Launch Ads Fee' => 'launch_ads_fee',
                'Marketing: Market Groups' => 'market_groups',
                'Marketing: Market Groups Fee' => 'market_groups_fee',
                'Marketing: Marketing Materials Fee' => 'marketing_materials_fee',
                'Marketing: MLS Filter Fee' => 'mls_filter_fee',
                'Marketing: Off-Market Search Fee' => 'off_market_search_fee',
                'Marketing: Promote Social' => 'promote_social',
                'Marketing: Promote Social Fee' => 'promote_social_fee',
                'Marketing: Neighborhood Marketing Fee' => 'neighborhood_marketing_fee',
                'Marketing: Neighborhood Materials Fee' => 'neighborhood_materials_fee',
                'Flat Fee Services' => 'flat_fee_services',
                'Schedule Showings' => 'schedule_showings',
                'Schedule Showings Fee' => 'schedule_showings_fee',
                'Number of Showings to Schedule' => 'number_of_showings_to_schedule',
                'Attend Showings' => 'attend_showings',
                'Number of Showings to Attend' => 'number_of_showings_to_attend',
                'Attend Showings Fee' => 'attend_showings_fee',
                'Provide Virtual Tours' => 'provide_virtual_tours',
                'Number of Virtual Tours' => 'number_of_virtual_tours',
                'Virtual Tours Fee' => 'virtual_tours_fee',
                'Assist with Application' => 'assist_application',
                'Assist Application Fee' => 'assist_application_fee',
                'Collect Documents' => 'collect_documents',
                'Collect Documents Fee' => 'collect_documents_fee',
                'Submit Application' => 'submit_application',
                'Submit Application Fee' => 'submit_application_fee',
                'Review Lease' => 'review_lease',
                'Review Lease Fee' => 'review_lease_fee',
                'Provide Lease Form' => 'provide_lease_form',
                'Provide Lease Form Fee' => 'provide_lease_form_fee',
                'Coordinate Signing' => 'coordinate_signing',
                'Coordinate Signing Fee' => 'coordinate_signing_fee',
                'Prepare Application Fee' => 'prepare_application_fee',
                'Move-In Inspection Fee' => 'move_in_inspection_fee',
                'Moving Resources Fee' => 'moving_resources_fee',
                'Short-Term Housing Fee' => 'short_term_housing_fee',
                'Rental Rights Fee' => 'rental_rights_fee',
                'Lease Advice Fee' => 'lease_advice_fee',
                'Neighborhood Insights Fee' => 'neighborhood_insights_fee',
                'List Criteria' => 'list_criteria',
                'List Criteria Fee' => 'list_criteria_fee',
                'Total Marketing Fee' => 'total_marketing_fee',
                'Total Flat Fee' => 'total_flat_fee',
                'Staging Duration' => 'staging_duration',
                'Open House Count' => 'open_house_count',
                'Virtual Showings Count' => 'virtual_showings_count',
            ],

            'Broker Compensation & Agency Agreement' => [
                'Commission Structure' => 'commission_structure',
                "Buyer's Broker Commission Fee Type" => 'commission_structure_type',
                "Buyer's Broker Commission Fee (Flat)" => 'commission_structure_type_fee_flat',
                "Buyer's Broker Commission Fee (%)" => 'commission_structure_type_fee_percentage',
                "Buyer's Broker Commission Fee (Flat Combo)" => 'commission_structure_type_fee_flat_combo',
                "Buyer's Broker Commission Fee (% Combo)" => 'commission_structure_type_fee_percentage_combo',
                "Buyer's Broker Commission Fee (Other)" => 'commission_structure_type_fee_other',
            ],

            'Additional Details' => [
                'Additional Details' => 'additional_details',
                'Video Link' => 'video_link',
            ],

            'Meeting Details' => [
                'Person Meeting' => 'person_meeting',
                'First Name' => 'meeting_details_first_name',
                'Last Name' => 'meeting_details_last_name',
                'Phone' => 'meeting_details_phone',
                'Email' => 'meeting_details_email',
                'Meeting Date' => 'meeting_details_meeting_date',
                'Meeting Time' => 'meeting_details_meeting_time',
                'Time Zone' => 'meeting_details_time_zone',
                'Meeting Instructions' => 'meeting_details_instructions',
                'Additional Meeting Details' => 'meeting_details_additional_details',
                'Service Completion Date' => 'service_completion_date',
                'Service Completion Time' => 'service_completion_time',
                'Service Time Zone' => 'service_time_zone',
            ],

            'Contact Information' => [
                'First Name' => 'first_name',
                'Last Name' => 'last_name',
                'Phone' => 'phone_number',
                'Email' => 'email',
            ],
        ];
    }

    public static function otherPairs(): array
    {
        return [
            'property_items' => 'other_property_items',
            'condition_prop' => 'other_property_condition',
            'assets' => 'assets_other',
            'sale_provision' => 'sale_provision_other',
            'offered_financing' => 'other_financing',
            'services' => 'other_services',
            'exchange_item' => 'other_exchange_item',
            'bathrooms' => 'other_bathrooms',
            'bedrooms' => 'other_bedrooms',
            'unit_size' => 'unit_size_other',
            'carport_needed' => 'other_carport_needed',
            'garage_needed' => 'other_garage_needed',
            'garage_parking_spaces_option' => 'other_parking_space_wrapper',
            'view_preference' => 'other_preferences',
            'appliances' => 'other_appliances',
            'number_of_unit' => 'number_of_unit_other',
            'non_negotiable_amenities' => 'other_non_negotiable_amenities',
            'lease_for' => 'other_lease_for',
            'assumable_occupancy_requirement' => 'assumable_occupancy_other',
        ];
    }
}
