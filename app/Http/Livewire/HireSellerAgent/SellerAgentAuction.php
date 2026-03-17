<?php

namespace App\Http\Livewire\HireSellerAgent;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\SellerAgentAuction as SellerAgentAuctionModel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SellerAgentAuction extends Component
{
    use WithFileUploads;

    protected $listeners = [
        'updatePreference' => 'handleUpdatePreference',
    ];

    // Livewire properties for form fields
    public $hasDrafts = false;
    public $auctionId; // To store the auction ID for editing

    public $listingId = null; // To track existing listings
    public $isDraft = false; // To track draft status
    public $service_type = 'full_service'; // 'full_service' or 'limited_service'
    public $listing_status = 'Active'; // 'Active', 'Pending', or 'Hired Agent'

    public $user_type = 'seller'; // Default to tenant or whatever makes sense
    public $auction_type = '';
    public $listing_title = '';
    public $working_with_agent = '';
    public $listing_date = '';
    public $desired_agent_hire_date = '';
    public $expiration_date = '';
    public $auction_time = '';

    // Location fields
    public $state = '';
    public $property_type = '';

    // Property details
    public $property_items = [];
    public $other_property_items = '';
    public $condition_prop = '';
    public $leasing_space = '';
    public $other_property_condition = '';
    public $bathrooms = '';
    public $other_bathrooms = '';
    public $bedrooms = '';
    public $other_bedrooms = '';
    public $minimum_heated_square = '';
    public $minimum_leaseable = '';
    public $min_acreage = '';
    public $tenant_require = '';
    public $carport_needed = '';
    public $other_carport_needed = '';

    // Properties
    public $sale_provision = [];
    public $sale_provision_other = '';
    public $sale_provision_assignment = '';
    public $assignment_fee_type = '';
    public $assignment_fee_amount = '';
    public $buyer_sell_contract = '';

    // Occupant / business type
    public $occupant_status = '';
    public $occupant_tenant = '';
    public $business_type = '';
    public $other_business_type = '';
    public $target_closing_date = '';

    // Properties
    public $maximum_budget = '';
    public $offered_financing = [];
    public $other_financing = '';
    public $cash_budget = '';
    public $pre_approved = '';
    public $pre_approval_amount = '';
    public $purchase_price = '';
    public $down_payment_type = '%';
    public $down_payment_amount = '';
    public $seller_financing_type = '$';
    public $seller_financing_amount = '';
    public $seller_down_payment_amount = '';
    public $seller_late_fee_amount = '';
    public $interest_rate = '';
    public $loan_duration = '';
    public $prepayment_penalty = '';
    public $prepayment_penalty_amount = '';
    public $balloon_payment = '';
    public $balloon_payment_amount = '';
    public $balloon_payment_date = '';
    public $assumable_terms = '';
    public $assumable_loan_type = '';
    public $outstanding_balance = '';
    public $lender_approval_required = '';
    public $max_assumable_rate = '';
    public $assumable_monthly_escrow = '';
    public $assumable_loan_term_remaining = '';
    public $assumable_loan_origination_date = '';
    public $assumable_loan_servicer = '';
    public $assumable_fee_type = '$';
    public $assumable_fee_amount = '';
    public $assumable_occupancy_requirement = '';
    public $assumable_occupancy_other = '';
    public $max_monthly_payment = '';
    public $gap_payment_type = '$';
    public $gap_payment_amount = '';

    // Exchange/Trade Properties
    public $exchange_item = [];
    public $other_exchange_item = '';
    public $exchange_item_value = '';
    public $exchange_item_condition = '';
    public $additional_cash = '';
    public $value_determination = '';
    public $exchange_transfer_method = '';
    public $exchange_liens = '';
    public $exchange_liens_disclosure = '';
    public $exchange_liens_details = '';
    public $exchange_inspection_rights = '';

    // Lease Option Properties
    public $lease_option_price = '';
    public $lease_option_terms = '';
    public $lease_option_duration = '';
    public $lease_option_payment = '';
    public $lease_option_conditions = '';
    public $has_option_fee = '';
    public $option_fee_amount = '';
    public $lease_option_fee_credit = '';
    public $lease_option_fee_credit_percentage = '';
    public $lease_option_maintenance = '';
    public $lease_option_extension_terms = '';
    public $seller_lease_option_fee_credit = '';
    public $seller_lease_option_fee_credit_percent = '';
    public $seller_lease_option_maintenance = '';
    public $seller_lease_option_extension_terms = '';

    // Lease Purchase Properties
    public $lease_purchase_price = '';
    public $lease_purchase_terms = '';
    public $lease_purchase_duration = '';
    public $lease_purchase_payment = '';
    public $lease_purchase_conditions = '';
    public $lease_purchase_rent_credit = '';
    public $lease_purchase_rent_credit_amount = '';
    public $lease_purchase_deposit = '';
    public $lease_purchase_maintenance = '';
    public $lease_purchase_extension_terms = '';
    public $seller_lease_purchase_rent_credit = '';
    public $seller_lease_purchase_rent_credit_type = '$';
    public $seller_lease_purchase_rent_credit_amount = '';
    public $seller_lease_purchase_deposit = '';
    public $seller_lease_purchase_maintenance = '';
    public $seller_lease_purchase_extension_terms = '';

    public $lease_purchase_option_fee = '';
    public $lease_purchase_option_fee_amount = '';

    // Seller financing amortization / payment
    public $seller_amortization_type = '';
    public $seller_amortization_other = '';
    public $seller_payment_frequency = '';
    public $seller_payment_frequency_other = '';

    // Cryptocurrency Properties
    public $crypto_transfer_timing = '';
    public $crypto_transfer_timing_other = '';
    public $crypto_exchange_method = '';
    public $crypto_custodian_wallet = '';
    public $crypto_transaction_fees = '';
    public $cryptocurrency_type = '';
    public $crypto_percentage = '';
    public $cash_percentage_crypto = '';

    // NFT Properties
    public $nft_description = '';
    public $nft_percentage = '';
    public $cash_percentage_nft = '';
    public $nft_gas_fees = '';
    public $nft_transfer_method = '';
    public $nft_valuation_method = '';

    public $garage_needed = '';
    public $other_garage_needed = '';
    public $garage_parking_spaces = '';
    public $garage_parking_spaces_option = '';
    public $other_parking_space_wrapper = '';
    public $pool_needed = '';
    public $pool_type = [];
    public $view_preference = [];
    public $other_preferences = '';
    public $appliances = [];
    public $other_appliances = '';
    public $showOtherAppliances = false;
    public $showEnhancements = false;
    public $showCustomEnhancement = false;
    public $showOpenHouseInput = false;
    public $is_other_visible = false;
    public $business_assets = [];
    // Appliances list matching Landlord Agent with legacy options for backwards compatibility
    public $applianceOptions = [
        ['name' => 'Bar Fridge'],
        ['name' => 'Built-In Oven'],
        ['name' => 'Central Vacuum'],
        ['name' => 'Convection Oven'],
        ['name' => 'Cooktop'],
        ['name' => 'Dishwasher'],
        ['name' => 'Disposal'],
        ['name' => 'Dryer'],
        ['name' => 'Electric Water Heater'],
        ['name' => 'Exhaust Fan'],
        ['name' => 'Freezer'],
        ['name' => 'Garbage Disposal'],
        ['name' => 'Gas Water Heater'],
        ['name' => 'Ice Maker'],
        ['name' => 'Indoor Grill'],
        ['name' => 'Kitchen Reverse Osmosis System'],
        ['name' => 'Microwave'],
        ['name' => 'Oven'],
        ['name' => 'Range Electric'],
        ['name' => 'Range Gas'],
        ['name' => 'Range Hood'],
        ['name' => 'Refrigerator'],
        ['name' => 'Solar Hot Water'],
        ['name' => 'Solar Hot Water Owned'],
        ['name' => 'Solar Hot Water Rented'],
        ['name' => 'Stove/Range'],
        ['name' => 'Tankless Water Heater'],
        ['name' => 'Touchless Faucet'],
        ['name' => 'Trash Compactor'],
        ['name' => 'Washer'],
        ['name' => 'Washer/Dryer Combo'],
        ['name' => 'Water Filtration System'],
        ['name' => 'Water Heater'],
        ['name' => 'Water Purifier'],
        ['name' => 'Water Softener'],
        ['name' => 'Whole House R.O. System'],
        ['name' => 'Wine Cooler'],
        ['name' => 'Wine Refrigerator'],
        ['name' => 'None'],
        ['name' => 'Other'],
    ];
    public $real_estate_purchase = '';
    public $number_of_unit = '';
    public $number_of_unit_other = '';
    public $unit_number = '';
    public $unit_buildings = '';
    public $minimum_annual_net_income = '';
    public $minimum_cap_rate = '';
    public $assets = '';
    public $assets_other = '';
    public $property_criteria = '';
    public $unit_size = '';
    public $unit_size_other = '';

    // Additional property details (Income / multi-unit)
    public $number_of_units = '';
    public $number_occupied = '';
    public $expected_rent = '';
    public $total_square_feet = '';
    public $sqft_heated_source = '';
    public $beds_unit = '';
    public $baths_unit = '';
    public $garage_spaces = '';
    public $carport_spaces = '';
    public $unit_type_description = '';
    public $breed_restrictions = '';

    // Meeting preference
    public $meeting_Preference = '';

    // Services enhancements
    public $custom_enhancement = '';
    public $openHouseCount = '';
    public $photo_enhancements = '';

    public $leasing_55_plus = '';
    public $non_negotiable_amenities = [];
    public $other_non_negotiable_amenities = '';
    public $budget = '';

    // Lease terms
    public $lease_for = [];
    public $other_lease_for = '';
    public $lease_by = '';
    public $lease_date = '';
    public $total_acreage = '';
    public $pets = '';
    public $number_of_pets = '';
    public $breed_of_pets = '';
    public $type_of_pets = '';
    public $weight_of_pets = '';
    public $credit_scroe_rating = [];
    public $prior_eviction = '';
    public $eviction_explanation = '';
    public $prior_felony = '';
    public $prior_felony_explanation = '';
    public $monthly_income = '';
    public $number_occupant = '';
    public $services = [];
    public $other_services_enabled = false;
    public $other_services = '';
    public $flat_fee_services = [];

    public $additional_details = '';
    public $preferance_details = '';

    // Broker compensation
    public $commission_structure = '';
    public $lease_fee_type = '';
    public $lease_fee_flat = '';
    public $lease_fee_percentage = '';
    public $lease_fee_months = '';
    public $lease_fee_percentage_monthly_rent = '';
    public $lease_fee_flat_combo = '';
    public $lease_fee_percentage_combo = '';
    public $lease_fee_other = '';
    public $purchase_fee_type = '';
    public $purchase_fee_percentage = '';
    public $purchase_fee_flat = '';
    public $purchase_fee_percentage_combo = '';
    public $purchase_fee_flat_combo = '';
    public $purchase_fee_other = '';
    public $lease_option_fee_type = '';
    public $lease_option_fee_flat = '';
    public $lease_option_fee_percentage = '';
    public $lease_option_fee_other = '';
    public $protection_period = '';
    public $early_termination_fee_option = '';
    public $early_termination_fee_amount = '';
    public $retainer_fee_option = '';
    public $retainer_fee_amount = '';
    public $retainer_fee_application = '';
    public $agency_agreement_timeframe = '';
    public $agency_agreement_custom = '';
    public $brokerage_relationship = '';

    // Seller-specific broker compensation
    public $nominal = '';
    public $interested_purchase_fee_type = '';
    public $seller_leasing_fee_type = '';
    public $seller_leasing_gross = '';
    public $seller_leasing_gross_rental = '';
    public $seller_leasing_gross_month_rent = '';
    public $seller_leasing_gross_no_of_months = '';
    public $seller_leasing_gross_flat = '';
    public $seller_leasing_gross_other = '';
    public $seller_leasing_each_rental = '';
    public $seller_leasing_gross_percentage = '';
    public $seller_leasing_gross_percentage_combo = '';
    public $seller_leasing_gross_flat_combo = '';
    public $seller_leasing_gross_flat_net_combo = '';
    public $seller_leasing_gross_percentage_net_combo = '';
    public $seller_leasing_gross_purchase_fee_flat_amount = '';
    public $seller_leasing_gross_purchase_fee_other = '';
    public $sales_tax_option_gross = '';
    public $seller_leasing_gross_sales_tax_first_month = '';
    public $seller_leasing_gross_sales_tax_flat_free_gross = '';
    public $seller_leasing_gross_sales_tax_option_gross = '';
    public $commission_structure_type = '';
    public $commission_structure_type_fee_flat = '';
    public $commission_structure_type_fee_percentage = '';
    public $commission_structure_type_fee_other = '';
    public $commission_structure_type_fee_flat_combo = '';
    public $commission_structure_type_fee_percentage_combo = '';
    public $interested_lease_option_agreement = '';
    public $lease_type = 'percent';
    public $purchase_type = 'percent';
    public $lease_value = null;
    public $purchase_value = null;
    public $retained_deposits = '';
    public $additional_details_broker = '';

    // Personal information
    public $first_name = '';
    public $last_name = '';
    public $phone_number = '';
    public $email = '';
    public $current_status = '';
    public $video_link = '';
    public $embedUrl = null;



    // location and meeting details
    public $person_meeting;
    public $meeting_details_first_name = '';
    public $meeting_details_last_name = '';
    public $meeting_details_phone = '';
    public $meeting_details_email = '';

    public $address = '';
    public $property_city = '';
    public $property_state = '';
    public $property_zip = '';
    public $property_county = '';
    public $propertyCitySuggestions = [];
    public $highlightedPropertyCityIndex = -1;
    public $meeting_details_meeting_time = '';
    public $meeting_details_time_zone = '';
    public $meeting_details_meeting_date = '';
    public $meeting_details_instructions = '';
    public $meeting_details_additional_details = '';
    public $addressSuggestions = [];

    // Add these to your component's properties
    public $service_completion_date = '';
    public $service_completion_time = '';
    public $service_time_zone = '';


    // Marketing Services
    public $list_criteria = false;
    public $list_criteria_fee = null;
    public $market_groups = false;
    public $market_groups_fee = null;
    public $promote_social = false;
    public $promote_social_fee = null;
    public $launch_ads = false;
    public $launch_ads_fee = null;
    public $include_marketing_fee = false;
    public $marketing_materials_fee = null;

    public $email_notifications_fee = null;
    public $off_market_search_fee = null;
    public $mls_filter_fee = null;
    public $email_marketing_fee = null;


    //Flat Fee Agent (Limited Service) Tenant
    public $showings_count = '';
    public $attend_showings_count = '';
    public $virtual_tours_count = '';
    public $understand_terms = false;

    //seller
    public $staging_duration = '';
    public $open_house_count = '';

    //Landlord

    public $virtual_showings_count = '';

    // Property Showings
    public $schedule_showings = false;
    public $number_of_showings_to_schedule = 0;
    public $schedule_showings_fee = null;
    public $attend_showings = false;
    public $number_of_showings_to_attend = 0;
    public $attend_showings_fee = null;
    public $provide_virtual_tours = false;
    public $number_of_virtual_tours = 0;
    public $virtual_tours_fee = null;

    // Application & Lease Support
    public $assist_application = false;
    public $assist_application_fee = null;
    public $collect_documents = false;
    public $collect_documents_fee = null;
    public $submit_application = false;
    public $submit_application_fee = null;
    public $review_lease = false;
    public $review_lease_fee = null;
    public $provide_lease_form = false;
    public $provide_lease_form_fee = null;
    public $coordinate_signing = false;
    public $coordinate_signing_fee = null;
    public $prepare_application_fee = '';


    // Move
    public $move_in_inspection_fee = '';
    public $moving_resources_fee = '';
    public $short_term_housing_fee = '';


    // Advisory Services
    public $rental_rights_fee = null;
    public $lease_advice_fee = null;

    //neighborhood Marketing
    public $neighborhood_insights_fee = null;
    public $neighborhood_marketing_fee = '';
    public $neighborhood_materials_fee = '';

    // Properties
    public $custom_services = [];
    public $total_flat_fee = 0;
    public $total_marketing_fee = 0;


    // Media uploads
    public $photo;
    public $video;
    public $photoError = null;
    public $videoError = null;

    // Tab management
    public $activeTab = 0;

    // Location suggestions
    public $cityFieldVisible = false;
    public $stateFieldVisible = false;
    public $zipCodeFieldVisible = false;
    public $cities = [];
    public $newCity = '';
    public $counties = [];
    public $newCounty = '';
    public $citySuggestions = [];
    public $countySuggestions = [];
    public $stateSuggestions = [];
    
    // ZIP code fields
    public $zipCodes = [];
    public $zip_code = '';
    public $zipCodeSuggestions = [];
    public $highlightedZipCodeIndex = -1;
    
    // Address autocomplete with place_id mapping
    public $addressPlaceIds = [];

    // Highlight indices for keyboard navigation
    public $highlightedCityIndex = -1;
    public $highlightedCountyIndex = -1;
    public $highlightedStateIndex = -1;
    public $highlightedAddressIndex = -1;
    public $fees = [];

    // Enable/disable checkboxes
    public $enable = [
        // Marketing & Promotion
        'list_criteria' => false,
        'social_media' => false,
        'paid_ads' => false,
        'email_marketing' => false,
        'local_mailers' => false,
        'hyperlocal_ads' => false,

        // Search & Alerts
        'email_alerts' => false,
        'off_market' => false,
        'filter_listings' => false,

        // Showings & Tours
        'schedule_showings' => false,
        'attend_showings' => false,
        'virtual_tours' => false,

        // Application Support
        'prepare_application' => false,
        'submit_documents' => false,
        'follow_up' => false,

        // Lease Support
        'state_lease' => false,
        'lease_overview' => false,
        'lease_disclosures' => false,
        'lease_signing' => false,

        // Move-In Coordination
        'key_handover' => false,
        'utility_setup' => false,

        // Advisory Support
        'rental_analysis' => false,
        'rental_laws' => false,
        'lease_options' => false,
        'short_term_housing' => false,
        'general_guidance' => false,
        'move_in_costs' => false,

        // Buyer
        'prepare_offer' => false,
        'complete_disclosures' => false,
        'follow_up_offer' => false,
        'overview_agreement' => false,
        'coordinate_counteroffer' => false,
        'esignature_setup' => false,
        'track_deadlines' => false,
        'contract_to_close' => false,
        'vendor_referrals' => false,
        'escrow_coordination' => false,
        'final_walkthrough' => false,
        'closing_appointment' => false,
        'closing_appointment' => false,
        'move_in_checklist' => false,
        'cma' => false,
        'homebuying_process' => false,
        'buyer_preparation' => false,
        'general_questions' => false,
        'closing_costs' => false,

        //Seller
        'list_bidyouroffer' => false,
        'list_mls' => false,
        'syndicate_listing' => false,
        'list_crexi' => false,
        'list_loopnet' => false,
        'staging_consultation' => false,
        'coordinate_staging' => false,
        'curb_appeal_consultation' => false,
        'coordinate_curb_appeal' => false,
        'professional_photography' => false,
        'drone_photos' => false,
        'video_walkthrough' => false,
        'virtual_tour' => false,
        'virtual_staging' => false,
        'digital_enhancement' => false,
        'virtual_showings' => false,
        'open_house' => false,
        'lockbox' => false,
        'yard_sign' => false,
        'organize_offers' => false,
        'follow_up_offers' => false,
        'move_out_checklist' => false,
        'selling_process' => false,
        'property_improvements' => false,
        'rma' => false,
        'standard_application' => false,
        'follow_up_application' => false,

    ];

    // Computed Properties
    public function getIsOtherVisibleProperty()
    {
        return is_array($this->view_preference) && in_array('Other', $this->view_preference);
    }

    public function getIsOtherNonNegotiableVisibleProperty()
    {
        return is_array($this->non_negotiable_amenities) && in_array('Other', $this->non_negotiable_amenities);
    }

    // Methods
    public function setDownPaymentType($type)
    {
        $this->down_payment_type = $type;
        $this->down_payment_amount = '';
    }

    public function setSellerFinancingType($type)
    {
        $this->seller_financing_type = $type;
        $this->seller_financing_amount = '';
    }

    public function setGapPaymentType($type)
    {
        $this->gap_payment_type = $type;
        $this->gap_payment_amount = '';
    }

    public function setType($which, $type)
    {
        if ($which === 'lease') {
            $this->lease_type = $type ?: 'percent';
            $this->lease_value = '';
        } elseif ($which === 'purchase') {
            $this->purchase_type = $type ?: 'percent';
            $this->purchase_value = '';
        }
    }

    public function updatedOfferedFinancing()
    {
        // Only reset fields when their corresponding financing option is deselected
        $financing = $this->offered_financing ?? [];

        // Reset Other financing fields if "Other" is no longer selected
        if (!in_array('Other', $financing)) {
            $this->reset(['other_financing']);
        }

        // Reset Cash fields only if "Cash" is no longer selected
        if (!in_array('Cash', $financing)) {
            $this->reset(['cash_budget']);
        }

        // Reset Conventional loan fields only if "Conventional" is no longer selected
        if (!in_array('Conventional', $financing)) {
            $this->reset([
                'pre_approved',
                'pre_approval_amount',
                'purchase_price',
                'down_payment_type',
                'down_payment_amount'
            ]);
        }

        // Reset Seller Financing fields only if "Seller Financing" is no longer selected
        if (!in_array('Seller Financing', $financing)) {
            $this->reset([
                'seller_financing_type',
                'seller_financing_amount',
                'seller_down_payment_amount',
                'interest_rate',
                'loan_duration',
                'prepayment_penalty',
                'prepayment_penalty_amount',
                'balloon_payment_amount',
                'balloon_payment_date',
                'seller_late_fee_amount'
            ]);
        }

        // Reset Assumable fields only if "Assumable" is no longer selected
        if (!in_array('Assumable', $financing)) {
            $this->reset([
                'assumable_terms',
                'assumable_loan_type',
                'max_assumable_rate',
                'assumable_monthly_escrow',
                'assumable_loan_term_remaining',
                'assumable_loan_origination_date',
                'assumable_loan_servicer',
                'assumable_fee_type',
                'assumable_fee_amount',
                'assumable_occupancy_requirement',
                'assumable_occupancy_other',
                'max_monthly_payment',
                'gap_payment_type',
                'gap_payment_amount',
                'outstanding_balance',
                'lender_approval_required'
            ]);
        }

        // Reset Exchange/Trade fields only if "Exchange/Trade" is no longer selected
        if (!in_array('Exchange/Trade', $financing)) {
            $this->reset([
                'exchange_item',
                'other_exchange_item',
                'exchange_item_value',
                'exchange_item_condition',
                'additional_cash',
                'value_determination',
                'exchange_transfer_method',
                'exchange_liens',
                'exchange_liens_details',
                'exchange_inspection_rights'
            ]);
        }

        // Reset Lease Option fields only if "Lease Option" is no longer selected
        if (!in_array('Lease Option', $financing)) {
            $this->reset([
                'lease_option_price',
                'lease_option_terms',
                'lease_option_duration',
                'lease_option_payment',
                'lease_option_conditions',
                'has_option_fee',
                'option_fee_amount',
                'seller_lease_option_fee_credit',
                'seller_lease_option_fee_credit_percent',
                'seller_lease_option_maintenance',
                'seller_lease_option_extension_terms'
            ]);
        }

        // Reset Lease Purchase fields only if "Lease Purchase" is no longer selected
        if (!in_array('Lease Purchase', $financing)) {
            $this->reset([
                'lease_purchase_price',
                'lease_purchase_terms',
                'lease_purchase_duration',
                'lease_purchase_payment',
                'lease_purchase_rent_credit'
            ]);
        }

        // Reset Cryptocurrency fields only if "Cryptocurrency" is no longer selected
        if (!in_array('Cryptocurrency', $financing)) {
            $this->reset([
                'cryptocurrency_type',
                'crypto_percentage'
            ]);
        }

        // Reset NFT fields only if "Non-Fungible Token (NFT)" is no longer selected
        if (!in_array('Non-Fungible Token (NFT)', $financing)) {
            $this->reset([
                'nft_description',
                'nft_percentage'
            ]);
        }
    }
    // Methods/ Methods
    public function setAssignmentFeeType($type)
    {
        $this->assignment_fee_type = $type;
        $this->assignment_fee_amount = ''; // Clear amount when changing type
    }

    public function updatedSaleProvision()
    {
        $provision = $this->sale_provision ?? [];

        if (!in_array('Other', $provision)) {
            $this->reset(['sale_provision_other']);
        }

        if (!in_array('Assignment Contract', $provision)) {
            $this->reset([
                'sale_provision_assignment',
                'assignment_fee_amount',
                'buyer_sell_contract'
            ]);
        }
    }

    public function updatedSaleProvisionAssignment()
    {
        $this->reset(['assignment_fee_amount', 'buyer_sell_contract']);
    }

    public function updatedBuyerSellContract()
    {
        $this->reset(['assignment_fee_amount']);
    }

    public function updatedViewPreference()
    {
        // Visibility is handled by computed property getIsOtherVisibleProperty()
        // Clear the other_preferences field if "Other" is not selected
        if (!in_array('Other', $this->view_preference ?? [])) {
            $this->other_preferences = '';
        }
    }

    public function updatedAppliances()
    {
        $this->showOtherAppliances = in_array('Other', $this->appliances ?? []);
    }

    public function handleUpdatePreference($selectedValues)
    {
        $this->view_preference = $selectedValues ?? [];
        $this->updatedViewPreference();
    }

    public function syncInput($name, $value, $rehash = true)
    {
        if (blank($name)) {
            return;
        }
        return parent::syncInput($name, $value, $rehash);
    }

    protected function callUpdatedHook($name, $value = null)
    {
        if (blank($name)) {
            return;
        }
        return parent::callUpdatedHook($name, $value);
    }

    public function updated($propertyName, $value = null)
    {
        if (empty($propertyName)) {
            return;
        }
    }

    public function updatedBathrooms($value)
    {
        if ($value !== 'Other') {
            $this->other_bathrooms = null;
        }
    }

    // Methods
    public function mount($listingId = null)
    {
        $this->addService();

        // Set listing_date to today's date by default (only if creating new listing)
        // loadDraft() will overwrite this with the saved value if loading a draft
        $this->listing_date = now()->format('Y-m-d');

        // Check for existing drafts
        $this->hasDrafts = SellerAgentAuctionModel::where('user_id', Auth::id())
            ->where('is_draft', true)
            ->exists();

        if ($listingId) {
            $this->loadDraft($listingId);
        }
    }
    public function startNew()
    {
        // Reset all properties to their initial state
        $this->resetExcept(['hasDrafts', 'service_type', 'user_type']);

        // Re-initialize necessary properties
        $this->addService();

        // Clear the listing ID
        $this->listingId = null;
        $this->isDraft = false;
    }
    public function getDrafts()
    {
        return SellerAgentAuctionModel::where('user_id', Auth::id())
            ->where('is_draft', true)
            ->latest()
            ->get();
    }
    public function updatedFees()
    {
        $this->calculateTotals();
    }

    public function updatedEnable()
    {
        $this->calculateTotals();
    }

    public function addService()
    {
        $this->custom_services[] = [
            'fee' => null,
            'description' => '',
            'marketing_fee' => null,
            'marketing_description' => ''
        ];
    }

    public function removeService($index)
    {
        unset($this->custom_services[$index]);
        $this->custom_services = array_values($this->custom_services);
        $this->calculateTotals();
    }

    public function calculateTotals()
    {
        // Calculate custom services totals
        $this->total_flat_fee = collect($this->custom_services)
            ->sum(function ($service) {
                return isset($service['fee']) ? (float) $service['fee'] : 0;
            });

        $this->total_marketing_fee = collect($this->custom_services)
            ->sum(function ($service) {
                return isset($service['marketing_fee']) ? (float) $service['marketing_fee'] : 0;
            });

        // Calculate fees from the fee structure
        $enabledFeeTotal = 0;

        foreach ($this->fees as $feeKey => $feeValue) {
            // Check if this fee is enabled (exists in $enable array and is true)
            if (isset($this->enable[$feeKey]) && $this->enable[$feeKey] && $feeValue !== null) {
                $enabledFeeTotal += (float) $feeValue;
            }
        }

        // Add the enabled fees to the total flat fee
        $this->total_flat_fee += $enabledFeeTotal;
    }

    public function updatedCustomServices()
    {
        $this->calculateTotals();
    }


    public function add_flat_fee_service()
    {
        $this->flat_fee_services[] = [
            'description' => '',
            'fee' => 0
        ];
    }

    public function remove_flat_fee_service($index)
    {
        unset($this->flat_fee_services[$index]);
        $this->flat_fee_services = array_values($this->flat_fee_services); // Reindex array
    }


    public function updatedWorkingWithAgent($value)
    {
        if ($value === 'Represented') {
            $this->dispatchBrowserEvent('show-representation-notice');
        } else {
            $this->dispatchBrowserEvent('hide-representation-notice');
        }
    }

    public function updatedAuctionType($value)
    {
        if ($value === 'Auction') {
            $this->dispatchBrowserEvent('show-auction-time');
        } else {
            $this->dispatchBrowserEvent('hide-auction-time');
        }
    }

    /**
     * Clear prior inputs when switching property types
     * This ensures Commercial-specific fields are cleared when switching to Residential and vice versa
     */
    public function updatedPropertyType($value)
    {
        // Clear all seller leasing fields when property type changes
        $this->seller_leasing_fee_type = '';
        $this->seller_leasing_gross = '';
        $this->seller_leasing_gross_rental = '';
        $this->seller_leasing_gross_month_rent = '';
        $this->seller_leasing_gross_no_of_months = '';
        $this->seller_leasing_gross_flat = '';
        $this->seller_leasing_gross_other = '';
        $this->seller_leasing_each_rental = '';
        $this->seller_leasing_gross_percentage = '';
        $this->seller_leasing_gross_percentage_combo = '';
        $this->seller_leasing_gross_flat_combo = '';
        $this->seller_leasing_gross_flat_net_combo = '';
        $this->seller_leasing_gross_percentage_net_combo = '';
        $this->seller_leasing_gross_purchase_fee_flat_amount = '';
        $this->seller_leasing_gross_purchase_fee_other = '';
        $this->sales_tax_option_gross = '';
        $this->seller_leasing_gross_sales_tax_first_month = '';
        $this->seller_leasing_gross_sales_tax_flat_free_gross = '';
        $this->seller_leasing_gross_sales_tax_option_gross = '';
        
        // Clear nominal fee for non-applicable property types
        if (!in_array($value, ['Income', 'Commercial', 'Business'])) {
            $this->nominal = '';
        }
    }

    /**
     * Clear prior inputs when switching seller leasing fee type
     */
    public function updatedSellerLeasingFeeType($value)
    {
        // Clear all leasing fee input values when type changes
        $this->seller_leasing_gross = '';
        $this->seller_leasing_gross_rental = '';
        $this->seller_leasing_gross_month_rent = '';
        $this->seller_leasing_gross_no_of_months = '';
        $this->seller_leasing_gross_flat = '';
        $this->seller_leasing_gross_other = '';
        $this->seller_leasing_each_rental = '';
        $this->seller_leasing_gross_percentage = '';
        $this->seller_leasing_gross_percentage_combo = '';
        $this->seller_leasing_gross_flat_combo = '';
        $this->seller_leasing_gross_flat_net_combo = '';
        $this->seller_leasing_gross_percentage_net_combo = '';
        $this->sales_tax_option_gross = '';
        $this->seller_leasing_gross_sales_tax_first_month = '';
        $this->seller_leasing_gross_sales_tax_flat_free_gross = '';
        $this->seller_leasing_gross_sales_tax_option_gross = '';
    }

    /**
     * Clear prior inputs when switching purchase fee type
     */
    public function updatedPurchaseFeeType($value)
    {
        // Clear all purchase fee values when type changes
        $this->purchase_fee_percentage = '';
        $this->purchase_fee_flat = '';
        $this->purchase_fee_percentage_combo = '';
        $this->purchase_fee_flat_combo = '';
        $this->purchase_fee_other = '';
    }

    /**
     * Clear prior inputs when switching commission structure type
     */
    public function updatedCommissionStructureType($value)
    {
        // Clear all commission structure fee values when type changes
        $this->commission_structure_type_fee_flat = '';
        $this->commission_structure_type_fee_percentage = '';
        $this->commission_structure_type_fee_other = '';
        $this->commission_structure_type_fee_flat_combo = '';
        $this->commission_structure_type_fee_percentage_combo = '';
    }

    /**
     * Clear commission structure type fields when main commission structure changes
     */
    public function updatedCommissionStructure($value)
    {
        // Clear sub-fields when switching commission structure
        $this->commission_structure_type = '';
        $this->commission_structure_type_fee_flat = '';
        $this->commission_structure_type_fee_percentage = '';
        $this->commission_structure_type_fee_other = '';
        $this->commission_structure_type_fee_flat_combo = '';
        $this->commission_structure_type_fee_percentage_combo = '';
    }

    /**
     * Clear seller leasing fields when interested in leasing changes
     */
    public function updatedInterestedPurchaseFeeType($value)
    {
        if ($value !== 'Yes') {
            // Clear all leasing fields when not interested in leasing
            $this->seller_leasing_fee_type = '';
            $this->seller_leasing_gross = '';
            $this->seller_leasing_gross_rental = '';
            $this->seller_leasing_gross_month_rent = '';
            $this->seller_leasing_gross_no_of_months = '';
            $this->seller_leasing_gross_flat = '';
            $this->seller_leasing_gross_other = '';
            $this->seller_leasing_each_rental = '';
            $this->seller_leasing_gross_percentage = '';
            $this->seller_leasing_gross_percentage_combo = '';
            $this->seller_leasing_gross_flat_combo = '';
            $this->sales_tax_option_gross = '';
        }
    }

    // Prevent duplicates in addCity()/addCounty()
    public function addCity()
    {
        $this->validate(['newCity' => 'required|string|max:255']);
        $city = trim($this->newCity);

        if (!empty($city) && !in_array($city, $this->cities)) {
            $this->cities[] = $city;
            $this->newCity = '';
        }

        $this->validate(['cities' => 'required|array|min:1']);
    }

    public function removeCity($index)
    {
        unset($this->cities[$index]);
        $this->cities = array_values($this->cities);
        $this->validate(['cities' => 'required|array|min:1']);
    }

    public function addCounty()
    {
        $this->validate(['newCounty' => 'required|string|max:255']);
        $county = trim($this->newCounty);

        if (!empty($county) && !in_array($county, $this->counties)) {
            $this->counties[] = $county;
            $this->newCounty = '';
        }

        $this->validate(['counties' => 'required|array|min:1']);
    }

    public function removeCounty($index)
    {
        unset($this->counties[$index]);
        $this->counties = array_values($this->counties);
        $this->validate(['counties' => 'required|array|min:1']);
    }

    // These methods will trigger Livewire to update the view
    public function validateCityInput()
    {
        $this->validateOnly('newCity');
    }

    public function validateCountyInput()
    {
        $this->validateOnly('newCounty');
    }
    // Listener for tab changes

    public function updatedNewCity($value)
    {
        if (strlen($value) > 2) {
            $this->citySuggestions = $this->getPlaceSuggestions($value, 'city');
        } else {
            $this->citySuggestions = [];
        }
    }

    public function updatedNewCounty($value)
    {
        if (strlen($value) > 2) {
            $this->countySuggestions = $this->getPlaceSuggestions($value, 'county');
        } else {
            $this->countySuggestions = [];
        }
    }
    public function updatedState($value)
    {
        if (strlen($value) > 1) {
            $this->stateSuggestions = $this->getPlaceSuggestions($value, 'state');
        } else {
            $this->stateSuggestions = [];
        }
    }
    
    public function updatedPropertyCity($value)
    {
        if (strlen($value) > 2) {
            $this->propertyCitySuggestions = $this->getPlaceSuggestions($value, 'city');
        } else {
            $this->propertyCitySuggestions = [];
        }
    }
    
    public function searchPropertyCity($value)
    {
        $this->property_city = $value;
        if (strlen($value) > 2) {
            $this->propertyCitySuggestions = $this->getPlaceSuggestions($value, 'city');
        } else {
            $this->propertyCitySuggestions = [];
        }
    }
    
    public function selectPropertyCitySuggestion($suggestion = null)
    {
        $suggestion = $suggestion ?? $this->propertyCitySuggestions[$this->highlightedPropertyCityIndex] ?? $this->property_city;
        $this->property_city = $suggestion;
        $this->propertyCitySuggestions = [];
        $this->highlightedPropertyCityIndex = -1;
        
        $this->autoPopulateFromPropertyCity($suggestion);
    }
    
    public function incrementPropertyCityHighlight()
    {
        if (count($this->propertyCitySuggestions) > 0) {
            $this->highlightedPropertyCityIndex = min($this->highlightedPropertyCityIndex + 1, count($this->propertyCitySuggestions) - 1);
        }
    }

    public function decrementPropertyCityHighlight()
    {
        if ($this->highlightedPropertyCityIndex > 0) {
            $this->highlightedPropertyCityIndex--;
        }
    }
    
    protected function autoPopulateFromPropertyCity($cityWithState)
    {
        $cityName = $this->extractNameFromLocationString($cityWithState);
        $stateAbbrev = $this->extractStateFromLocationString($cityWithState);
        
        if (empty($cityName)) return;
        
        if ($stateAbbrev) {
            $state = \App\Models\UsState::where('abbreviation', strtoupper($stateAbbrev))->first();
            if ($state && empty($this->property_state)) {
                $this->property_state = $state->name;
            }
        }
        
        $city = \App\Models\UsCity::where('name', 'ILIKE', $cityName)
            ->when($stateAbbrev, function ($query) use ($stateAbbrev) {
                return $query->whereHas('state', function ($q) use ($stateAbbrev) {
                    $q->where('abbreviation', strtoupper($stateAbbrev));
                });
            })
            ->with(['county', 'state'])
            ->first();
        
        if ($city) {
            if ($city->county && empty($this->property_county)) {
                $countyName = $city->county->name;
                if (!str_contains(strtolower($countyName), 'county')) {
                    $countyName .= ' County';
                }
                $stateAbbr = $city->state ? $city->state->abbreviation : '';
                $this->property_county = $countyName . ', ' . $stateAbbr;
            }
            
            $zipCode = \App\Models\UsZipCode::where('city', 'ILIKE', $cityName)
                ->when($stateAbbrev, function ($query) use ($stateAbbrev) {
                    return $query->where('state_abbrev', strtoupper($stateAbbrev));
                })
                ->first();
            
            if ($zipCode && empty($this->property_zip)) {
                $this->property_zip = $zipCode->zip_code;
            }
        }
    }
    
    protected function getPlaceSuggestions($input, $type = null)
    {
        $results = [];
        
        if ($type === 'state') {
            $states = \App\Models\UsState::where('name', 'ILIKE', $input . '%')
                ->orWhere('abbreviation', 'ILIKE', $input . '%')
                ->orderBy('name')
                ->limit(10)
                ->get();
            
            foreach ($states as $state) {
                $results[] = $state->name;
            }
        } elseif ($type === 'county') {
            $counties = \App\Models\UsCounty::where('name', 'ILIKE', $input . '%')
                ->with('state')
                ->orderBy('name')
                ->limit(10)
                ->get();
            
            foreach ($counties as $county) {
                $stateAbbrev = $county->state ? $county->state->abbreviation : '';
                $countyName = $county->name;
                if (!str_contains(strtolower($countyName), 'county')) {
                    $countyName .= ' County';
                }
                $results[] = $countyName . ', ' . $stateAbbrev;
            }
        } else {
            $cities = \App\Models\UsCity::where('name', 'ILIKE', $input . '%')
                ->with('state')
                ->orderBy('name')
                ->limit(10)
                ->get();
            
            foreach ($cities as $city) {
                $stateAbbrev = $city->state ? $city->state->abbreviation : '';
                $results[] = $city->name . ', ' . $stateAbbrev;
            }
        }
        
        return $results;
    }

    public function selectCitySuggestion($suggestion = null)
    {
        $suggestion = $suggestion ?? $this->citySuggestions[$this->highlightedCityIndex] ?? $this->newCity;
        $this->newCity = $suggestion;

        if (!in_array(trim($suggestion), $this->cities)) {
            $this->addCity();
        }

        $this->citySuggestions = [];
        $this->highlightedCityIndex = -1;
        
        $this->autoPopulateZipCodesFromCity($suggestion);
    }
    
    protected function autoPopulateZipCodesFromCity($cityWithState)
    {
        $cityName = $this->extractNameFromLocationString($cityWithState);
        $stateAbbrev = $this->extractStateFromLocationString($cityWithState);
        
        if (empty($cityName)) return;
        
        $zipCodes = \App\Models\UsZipCode::where('city', 'ILIKE', $cityName);
        
        if ($stateAbbrev) {
            $zipCodes->where('state_abbrev', strtoupper($stateAbbrev));
        }
        
        $foundZips = $zipCodes->orderBy('zip_code')->limit(20)->pluck('zip_code')->toArray();
        
        foreach ($foundZips as $zip) {
            if (!in_array($zip, $this->zipCodes)) {
                $this->zipCodes[] = $zip;
            }
        }
    }
    
    protected function extractStateFromLocationString($locationString)
    {
        if (preg_match('/,\s*([A-Z]{2})(?:\s|$|,)/', $locationString, $matches)) {
            return $matches[1];
        }
        return null;
    }
    
    protected function extractNameFromLocationString($locationString)
    {
        $parts = explode(',', $locationString);
        return trim($parts[0]);
    }

    public function selectCountySuggestion($suggestion = null)
    {
        $suggestion = $suggestion ?? $this->countySuggestions[$this->highlightedCountyIndex] ?? $this->newCounty;
        $this->newCounty = $suggestion;

        if (!in_array(trim($suggestion), $this->counties)) {
            $this->addCounty();
        }

        $this->countySuggestions = [];
        $this->highlightedCountyIndex = -1;
    }


    public function selectStateSuggestion($suggestion)
    {
        $this->state = $suggestion;
        $this->stateSuggestions = [];
        $this->highlightedStateIndex = -1;
    }
    
    protected function countyExistsIgnoreCase($county)
    {
        $countyLower = strtolower(trim($county));
        foreach ($this->counties as $existingCounty) {
            if (strtolower(trim($existingCounty)) === $countyLower) {
                return true;
            }
        }
        return false;
    }
    
    // ZIP code methods
    public function addZipCode()
    {
        $zip = trim($this->zip_code);
        if (!empty($zip) && !in_array($zip, $this->zipCodes)) {
            $this->zipCodes[] = $zip;
        }
        $this->zip_code = '';
        $this->zipCodeSuggestions = [];
    }
    
    public function removeZipCode($index)
    {
        if (isset($this->zipCodes[$index])) {
            unset($this->zipCodes[$index]);
            $this->zipCodes = array_values($this->zipCodes);
        }
    }
    
    public function selectZipCodeSuggestion($suggestion = null)
    {
        $suggestion = $suggestion ?? $this->zipCodeSuggestions[$this->highlightedZipCodeIndex] ?? $this->zip_code;
        $this->zip_code = $suggestion;
        
        if (!empty(trim($suggestion)) && !in_array(trim($suggestion), $this->zipCodes)) {
            $this->addZipCode();
        }
        
        $this->zipCodeSuggestions = [];
        $this->highlightedZipCodeIndex = -1;
    }

    // Updated keyboard navigation methods
    public function incrementHighlight($type)
    {
        $property = "highlighted{$type}Index";
        $suggestions = "{$type}Suggestions";

        if (count($this->$suggestions)) {
            $this->$property = min($this->$property + 1, count($this->$suggestions) - 1);
        }
    }

    public function decrementHighlight($type)
    {
        $property = "highlighted{$type}Index";
        $this->$property = max($this->$property - 1, -1);
    }

    // Method to update the active tab
    public function setActiveTab($index)
    {
        $this->activeTab = $index;
    }

    public function updatedVideoLink($value)
    {
        $this->embedUrl = $this->getEmbedUrl($value);
    }

    public function previewVideo()
    {
        $this->embedUrl = $this->getEmbedUrl($this->video_link);
    }

    public function getEmbedUrl($url)
    {
        if (str_contains($url, 'youtube.com') || str_contains($url, 'youtu.be')) {
            preg_match('/(?:v=|youtu\.be\/)([A-Za-z0-9_-]{6,})/i', $url, $m);
            $videoId = $m[1] ?? null;
            return $videoId
                ? "https://www.youtube.com/embed/{$videoId}?autoplay=1&mute=1&playsinline=1"
                : null;
        }

        if (str_contains($url, 'vimeo.com')) {
            preg_match('/vimeo\.com\/(?:.*\/)?(\d+)(?:[\/?#]|$)/i', $url, $m);
            $videoId = $m[1] ?? null;

            if (!$videoId && str_contains($url, 'player.vimeo.com')) {
                preg_match('/player\.vimeo\.com\/video\/(\d+)/i', $url, $m2);
                $videoId = $m2[1] ?? null;
            }

            return $videoId
                ? "https://player.vimeo.com/video/{$videoId}?autoplay=1&muted=1&playsinline=1"
                : null;
        }

        return null;
    }

    public function render()
    {

        return view('livewire.hire-seller-agent.hire-seller-agent')->extends('layouts.main')->section('content'); // Define the section
    }
    public function saveDraft()
    {

        try {


            $this->isDraft = true;

            $auction = $this->listingId
                ? SellerAgentAuctionModel::find($this->listingId)
                : new SellerAgentAuctionModel();

            $auction->user_id = Auth::id();
            $auction->title = $this->listing_title;
            $auction->is_draft = true;
            $auction->save();

            $this->listingId = $auction->id;

            $this->saveAllMetadata($auction);

            session()->flash('success', 'Draft saved successfully. You can return later to complete your listing.');
        } catch (\Exception $e) {
            session()->flash('error', 'Error saving draft: ' . $e->getMessage());
        }
    }

    public function loadDraft($listingId)
    {
        $auction = SellerAgentAuctionModel::where('id', $listingId)
            ->where('user_id', Auth::id())
            ->first();

        if ($auction) {

            // Load all metadata fields
            $this->listing_title = $auction->title;
            $this->service_type = $auction->get->service_type;
            $this->user_type = $auction->get->user_type;
            $this->listing_status = $auction->get->listing_status;
            $this->auction_type = $auction->get->auction_type;
            $this->working_with_agent = $auction->get->working_with_agent;
            $this->listing_date = $auction->get->listing_date;
            $this->desired_agent_hire_date = $auction->get->desired_agent_hire_date;
            $this->expiration_date = $auction->get->expiration_date;
            $this->auction_time = $auction->get->auction_time;

            $this->state = $auction->get->state;
            $this->zipCodes = is_string($auction->get->zipCodes) ? json_decode($auction->get->zipCodes, true) ?? [] : (array)($auction->get->zipCodes ?? []);
            $this->zip_code = $this->zipCodes[0] ?? ($auction->get->zip_code ?? '');
            $this->property_type = $auction->get->property_type;
            $this->cities = is_string($auction->get->cities) ? json_decode($auction->get->cities, true) ?? [] : (array)$auction->get->cities;

            $this->counties = is_string($auction->get->counties) ? json_decode($auction->get->counties, true) ?? [] : (array)$auction->get->counties;

            $this->property_city = $auction->get->property_city ?? '';
            $this->property_county = $auction->get->property_county ?? '';
            $this->property_state = $auction->get->property_state ?? '';
            $this->property_zip = $auction->get->property_zip ?? '';

            // Property details

            $this->property_items = is_string($auction->get->property_items) ? json_decode($auction->get->property_items, true) ?? [] : (array)$auction->get->property_items;

            $this->other_property_items = $auction->get->other_property_items;
            $this->condition_prop = $auction->get->condition_prop;
            $this->leasing_space = $auction->get->leasing_space;
            $this->other_property_condition = $auction->get->other_property_condition;
            $this->bathrooms = $auction->get->bathrooms;
            $this->other_bathrooms = $auction->get->other_bathrooms;
            $this->bedrooms = $auction->get->bedrooms;
            $this->other_bedrooms = $auction->get->other_bedrooms;
            $this->minimum_heated_square = $auction->get->minimum_heated_square;
            $this->total_square_feet = $auction->get->total_square_feet ?? '';
            $this->minimum_leaseable = $auction->get->minimum_leaseable;
            $this->min_acreage = $auction->get->min_acreage;
            $this->total_acreage = $auction->get->total_acreage;
            $this->minimum_cap_rate = $auction->get->minimum_cap_rate;
            $this->unit_number = $auction->get->unit_number ?? '';
            $this->unit_buildings = $auction->get->unit_buildings ?? '';
            $this->assets = $auction->get->assets;
            $this->assets_other = $auction->get->assets_other;
            $this->property_criteria = $auction->get->property_criteria;
            $this->unit_size = $auction->get->unit_size;
            $this->unit_size_other = $auction->get->unit_size_other;
            $this->preferance_details = $auction->get->preferance_details;


            // Sale Provision
            $this->sale_provision = is_string($auction->get->sale_provision) ? json_decode($auction->get->sale_provision, true) ?? [] : (array)($auction->get->sale_provision ?? []);
            $this->sale_provision_other = $auction->get->sale_provision_other;
            $this->sale_provision_assignment = $auction->get->sale_provision_assignment;
            $this->assignment_fee_type = $auction->get->assignment_fee_type;
            $this->assignment_fee_amount = $auction->get->assignment_fee_amount;
            $this->buyer_sell_contract = $auction->get->buyer_sell_contract;

            // Budget & Financing
            $this->maximum_budget = $auction->get->maximum_budget;
            $this->offered_financing = is_string($auction->get->offered_financing) ? json_decode($auction->get->offered_financing, true) ?? [] : (array)($auction->get->offered_financing ?? []);
            $this->other_financing = $auction->get->other_financing;
            $this->cash_budget = $auction->get->cash_budget;
            $this->pre_approved = $auction->get->pre_approved;
            $this->pre_approval_amount = $auction->get->pre_approval_amount;
            $this->purchase_price = $auction->get->purchase_price;
            $this->down_payment_type = $auction->get->down_payment_type;
            $this->down_payment_amount = $auction->get->down_payment_amount;
            $this->seller_financing_type = $auction->get->seller_financing_type;
            $this->seller_financing_amount = $auction->get->seller_financing_amount;
            $this->interest_rate = $auction->get->interest_rate;
            $this->loan_duration = $auction->get->loan_duration;
            $this->prepayment_penalty = $auction->get->prepayment_penalty;
            $this->prepayment_penalty_amount = $auction->get->prepayment_penalty_amount;
            $this->balloon_payment_amount = $auction->get->balloon_payment_amount;
            $this->balloon_payment_date = $auction->get->balloon_payment_date;
            $this->assumable_terms = $auction->get->assumable_terms;
            $this->max_assumable_rate = $auction->get->max_assumable_rate;
            $this->assumable_monthly_escrow = $auction->get->assumable_monthly_escrow ?? '';
            $this->assumable_loan_term_remaining = $auction->get->assumable_loan_term_remaining ?? '';
            $this->assumable_loan_origination_date = $auction->get->assumable_loan_origination_date ?? '';
            $this->assumable_loan_servicer = $auction->get->assumable_loan_servicer ?? '';
            $this->assumable_fee_type = $auction->get->assumable_fee_type ?? '$';
            $this->assumable_fee_amount = $auction->get->assumable_fee_amount ?? '';
            $this->assumable_occupancy_requirement = $auction->get->assumable_occupancy_requirement ?? '';
            $this->assumable_occupancy_other = $auction->get->assumable_occupancy_other ?? '';
            $this->max_monthly_payment = $auction->get->max_monthly_payment;
            $this->gap_payment_type = $auction->get->gap_payment_type;
            $this->gap_payment_amount = $auction->get->gap_payment_amount;

            // Exchange/Trade
            $rawExchangeItem = $auction->get->exchange_item ?? '';
            if (is_string($rawExchangeItem)) {
                $decoded = json_decode($rawExchangeItem, true);
                $this->exchange_item = is_array($decoded) ? $decoded : ($rawExchangeItem !== '' ? [$rawExchangeItem] : []);
            } else {
                $this->exchange_item = (array) $rawExchangeItem;
            }
            $this->other_exchange_item = $auction->get->other_exchange_item;
            $this->exchange_item_value = $auction->get->exchange_item_value;
            $this->exchange_item_condition = $auction->get->exchange_item_condition;
            $this->additional_cash = $auction->get->additional_cash;
            $this->value_determination = $auction->get->value_determination;
            $this->exchange_transfer_method = $auction->get->exchange_transfer_method;
            $this->exchange_liens_disclosure = $auction->get->exchange_liens_disclosure;
            $this->exchange_liens_details = $auction->get->exchange_liens_details;
            $this->exchange_inspection_rights = $auction->get->exchange_inspection_rights;

            // Lease Option
            $this->lease_option_price = $auction->get->lease_option_price;
            $this->lease_option_terms = $auction->get->lease_option_terms;
            $this->lease_option_duration = $auction->get->lease_option_duration;
            $this->lease_option_payment = $auction->get->lease_option_payment;
            $this->lease_option_conditions = $auction->get->lease_option_conditions;
            $this->has_option_fee = $auction->get->has_option_fee;
            $this->option_fee_amount = $auction->get->option_fee_amount;
            $this->seller_lease_option_fee_credit = $auction->get->seller_lease_option_fee_credit;
            $this->seller_lease_option_fee_credit_percent = $auction->get->seller_lease_option_fee_credit_percent;
            $this->seller_lease_option_maintenance = $auction->get->seller_lease_option_maintenance;
            $this->seller_lease_option_extension_terms = $auction->get->seller_lease_option_extension_terms;

            // Lease Purchase
            $this->lease_purchase_price = $auction->get->lease_purchase_price;
            $this->lease_purchase_terms = $auction->get->lease_purchase_terms;
            $this->lease_purchase_duration = $auction->get->lease_purchase_duration;
            $this->lease_purchase_payment = $auction->get->lease_purchase_payment;
            $this->lease_purchase_conditions = $auction->get->lease_purchase_conditions;
            $this->seller_lease_purchase_rent_credit = $auction->get->seller_lease_purchase_rent_credit;
            $this->seller_lease_purchase_rent_credit_type = $auction->get->seller_lease_purchase_rent_credit_type ?? '$';
            $this->seller_lease_purchase_rent_credit_amount = $auction->get->seller_lease_purchase_rent_credit_amount;
            $this->seller_lease_purchase_deposit = $auction->get->seller_lease_purchase_deposit;
            $this->seller_lease_purchase_maintenance = $auction->get->seller_lease_purchase_maintenance;
            $this->seller_lease_purchase_extension_terms = $auction->get->seller_lease_purchase_extension_terms;
            $this->lease_purchase_option_fee = $auction->get->lease_purchase_option_fee;
            $this->lease_purchase_option_fee_amount = $auction->get->lease_purchase_option_fee_amount;

            // Cryptocurrency
            $this->cryptocurrency_type = $auction->get->cryptocurrency_type;
            $this->crypto_percentage = $auction->get->crypto_percentage;
            $this->cash_percentage_crypto = $auction->get->cash_percentage_crypto;

            // NFT
            $this->nft_description = $auction->get->nft_description;
            $this->nft_percentage = $auction->get->nft_percentage;
            $this->cash_percentage_nft = $auction->get->cash_percentage_nft;

            // Amenities and features

            $this->tenant_require = is_string($auction->get->tenant_require) ? json_decode($auction->get->tenant_require, true) ?? [] : (array)$auction->get->tenant_require;


            $this->carport_needed = $auction->get->carport_needed;
            $this->other_carport_needed = $auction->get->other_carport_needed;
            $this->garage_needed = $auction->get->garage_needed;
            $this->other_garage_needed = $auction->get->other_garage_needed;
            $this->garage_parking_spaces = $auction->get->garage_parking_spaces;
            $this->garage_parking_spaces_option = $auction->get->garage_parking_spaces_option;
            $this->other_parking_space_wrapper = $auction->get->other_parking_space_wrapper;
            $this->pool_needed = $auction->get->pool_needed ?? '';

            $poolTypeRaw = $auction->get->pool_type ?? [];
            $this->pool_type = is_string($poolTypeRaw) ? json_decode($poolTypeRaw, true) ?? [] : (array)$poolTypeRaw;




            $this->view_preference = is_string($auction->get->view_preference) ? json_decode($auction->get->view_preference, true) ?? [] : (array)$auction->get->view_preference;
            $this->other_preferences = $auction->get->other_preferences;
            $this->business_assets = is_string($auction->get->business_assets) ? json_decode($auction->get->business_assets, true) ?? [] : (array)($auction->get->business_assets ?? []);
            
            $this->appliances = is_string($auction->get->appliances) ? json_decode($auction->get->appliances, true) ?? [] : (array)$auction->get->appliances;
            $this->showOtherAppliances = in_array('Other', $this->appliances ?? []);
            $this->other_appliances = $auction->get->other_appliances;
            $this->real_estate_purchase = $auction->get->real_estate_purchase;
            $this->number_of_unit = $auction->get->number_of_unit;
            $this->number_of_unit_other = $auction->get->number_of_unit_other;
            $this->minimum_annual_net_income = $auction->get->minimum_annual_net_income;
            $this->leasing_55_plus = $auction->get->leasing_55_plus;

            $this->non_negotiable_amenities = is_string($auction->get->non_negotiable_amenities) ? json_decode($auction->get->non_negotiable_amenities, true) ?? [] : (array)$auction->get->non_negotiable_amenities;

            $this->other_non_negotiable_amenities = $auction->get->other_non_negotiable_amenities;
            $this->budget = $auction->get->budget;

            // Lease terms
            $this->additional_details = $auction->get->additional_details;

            $this->lease_for = is_string($auction->get->lease_for) ? json_decode($auction->get->lease_for, true) ?? [] : (array)$auction->get->lease_for;


            $this->other_lease_for = $auction->get->other_lease_for;
            $this->lease_by = $auction->get->lease_by;
            $this->lease_date = $auction->get->lease_date;

            // Tenant information
            $this->pets = $auction->get->pets;
            $this->number_of_pets = $auction->get->number_of_pets;
            $this->breed_of_pets = $auction->get->breed_of_pets;
            $this->type_of_pets = $auction->get->type_of_pets;
            $this->weight_of_pets = $auction->get->weight_of_pets;


            $this->credit_scroe_rating = is_string($auction->get->credit_scroe_rating) ? json_decode($auction->get->credit_scroe_rating, true) ?? [] : (array)$auction->get->credit_scroe_rating;

            $this->prior_eviction = $auction->get->prior_eviction;
            $this->eviction_explanation = $auction->get->eviction_explanation;
            $this->prior_felony = $auction->get->prior_felony;
            $this->prior_felony_explanation = $auction->get->prior_felony_explanation;
            $this->monthly_income = $auction->get->monthly_income;
            $this->number_occupant = $auction->get->number_occupant;

            // Services

            $this->services = is_string($auction->get->services) ? json_decode($auction->get->services, true) ?? [] : (array)$auction->get->services;


            $this->other_services = $auction->get->other_services;


            $this->flat_fee_services = is_string($auction->get->flat_fee_services) ? json_decode($auction->get->flat_fee_services, true) ?? [] : (array)$auction->get->flat_fee_services;
            $this->additional_details = $auction->get->additional_details;

            // Broker compensation
            $this->commission_structure = $auction->get->commission_structure;
            $this->lease_fee_type = $auction->get->lease_fee_type;
            $this->lease_fee_flat = $auction->get->lease_fee_flat;
            $this->lease_fee_percentage = $auction->get->lease_fee_percentage;
            $this->lease_fee_months = $auction->get->lease_fee_months;
            $this->lease_fee_percentage_monthly_rent = $auction->get->lease_fee_percentage_monthly_rent;
            $this->lease_fee_flat_combo = $auction->get->lease_fee_flat_combo;
            $this->lease_fee_percentage_combo = $auction->get->lease_fee_percentage_combo;
            $this->lease_fee_other = $auction->get->lease_fee_other;
            $this->purchase_fee_type = $auction->get->purchase_fee_type;
            $this->purchase_fee_percentage = $auction->get->purchase_fee_percentage;
            $this->purchase_fee_flat = $auction->get->purchase_fee_flat;
            $this->purchase_fee_percentage_combo = $auction->get->purchase_fee_percentage_combo;
            $this->purchase_fee_flat_combo = $auction->get->purchase_fee_flat_combo;
            $this->purchase_fee_other = $auction->get->purchase_fee_other;
            $this->lease_option_fee_type = $auction->get->lease_option_fee_type;
            $this->lease_option_fee_flat = $auction->get->lease_option_fee_flat;
            $this->lease_option_fee_percentage = $auction->get->lease_option_fee_percentage;
            $this->lease_option_fee_other = $auction->get->lease_option_fee_other;
            $this->protection_period = $auction->get->protection_period;
            $this->early_termination_fee_option = $auction->get->early_termination_fee_option;
            $this->early_termination_fee_amount = $auction->get->early_termination_fee_amount;
            $this->lease_type = $auction->get->lease_type ?? 'percent';
            $this->lease_value = $auction->get->lease_value ?? null;
            $this->purchase_type = $auction->get->purchase_type ?? 'percent';
            $this->purchase_value = $auction->get->purchase_value ?? null;
            $this->interested_lease_option_agreement = $auction->get->interested_lease_option_agreement ?? '';
            $this->retainer_fee_option = $auction->get->retainer_fee_option;
            $this->retainer_fee_amount = $auction->get->retainer_fee_amount;
            $this->retainer_fee_application = $auction->get->retainer_fee_application;
            $this->agency_agreement_timeframe = $auction->get->agency_agreement_timeframe;
            $this->agency_agreement_custom = $auction->get->agency_agreement_custom;
            $this->brokerage_relationship = $auction->get->brokerage_relationship;
            $this->additional_details_broker = $auction->get->additional_details_broker ?? '';

            // Personal information
            $this->first_name = $auction->get->first_name;
            $this->last_name = $auction->get->last_name;
            $this->phone_number = $auction->get->phone_number;
            $this->email = $auction->get->email;
            $this->current_status = $auction->get->current_status ?? '';
            $this->video_link = $auction->get->video_link;
            $this->photo = $auction->get->photo ?? null;
            $this->video = $auction->get->video ?? null;

            // Location and meeting details
            $this->person_meeting = $auction->get->person_meeting;
            $this->meeting_details_first_name = $auction->get->meeting_details_first_name;
            $this->meeting_details_last_name = $auction->get->meeting_details_last_name;
            $this->meeting_details_phone = $auction->get->meeting_details_phone;
            $this->meeting_details_email = $auction->get->meeting_details_email;
            $this->address = $auction->get->address;
            $this->meeting_details_meeting_time = $auction->get->meeting_details_meeting_time;
            $this->meeting_details_time_zone = $auction->get->meeting_details_time_zone;
            $this->meeting_details_meeting_date = $auction->get->meeting_details_meeting_date;
            $this->meeting_details_instructions = $auction->get->meeting_details_instructions;
            $this->meeting_details_additional_details = $auction->get->meeting_details_additional_details;
            $this->service_completion_date = $auction->get->service_completion_date;
            $this->service_completion_time = $auction->get->service_completion_time;
            $this->service_time_zone = $auction->get->service_time_zone;

            // Marketing services
            $this->list_criteria = (bool)$auction->get->list_criteria;
            $this->list_criteria_fee = $auction->get->list_criteria_fee;
            $this->market_groups = (bool)$auction->get->market_groups;
            $this->market_groups_fee = $auction->get->market_groups_fee;
            $this->promote_social = (bool)$auction->get->promote_social;
            $this->promote_social_fee = $auction->get->promote_social_fee;
            $this->launch_ads = (bool)$auction->get->launch_ads;
            $this->launch_ads_fee = $auction->get->launch_ads_fee;
            $this->include_marketing_fee = (bool)$auction->get->include_marketing_fee;
            $this->marketing_materials_fee = $auction->get->marketing_materials_fee;
            $this->email_notifications_fee = $auction->get->email_notifications_fee;
            $this->off_market_search_fee = $auction->get->off_market_search_fee;
            $this->mls_filter_fee = $auction->get->mls_filter_fee;
            $this->email_marketing_fee = $auction->get->email_marketing_fee;

            // Property showings
            $this->schedule_showings = (bool)$auction->get->schedule_showings;
            $this->number_of_showings_to_schedule = $auction->get->number_of_showings_to_schedule;
            $this->schedule_showings_fee = $auction->get->schedule_showings_fee;
            $this->attend_showings = (bool)$auction->get->attend_showings;
            $this->number_of_showings_to_attend = $auction->get->number_of_showings_to_attend;
            $this->attend_showings_fee = $auction->get->attend_showings_fee;
            $this->provide_virtual_tours = (bool)$auction->get->provide_virtual_tours;
            $this->number_of_virtual_tours = $auction->get->number_of_virtual_tours;
            $this->virtual_tours_fee = $auction->get->virtual_tours_fee;

            // Application & lease support
            $this->assist_application = (bool)$auction->get->assist_application;
            $this->assist_application_fee = $auction->get->assist_application_fee;
            $this->collect_documents = (bool)$auction->get->collect_documents;
            $this->collect_documents_fee = $auction->get->collect_documents_fee;
            $this->submit_application = (bool)$auction->get->submit_application;
            $this->submit_application_fee = $auction->get->submit_application_fee;
            $this->review_lease = (bool)$auction->get->review_lease;
            $this->review_lease_fee = $auction->get->review_lease_fee;
            $this->provide_lease_form = (bool)$auction->get->provide_lease_form;
            $this->provide_lease_form_fee = $auction->get->provide_lease_form_fee;
            $this->coordinate_signing = (bool)$auction->get->coordinate_signing;
            $this->coordinate_signing_fee = $auction->get->coordinate_signing_fee;
            $this->prepare_application_fee = $auction->get->prepare_application_fee;

            // Move services
            $this->move_in_inspection_fee = $auction->get->move_in_inspection_fee;
            $this->moving_resources_fee = $auction->get->moving_resources_fee;
            $this->short_term_housing_fee = $auction->get->short_term_housing_fee;

            // Advisory services
            $this->rental_rights_fee = $auction->get->rental_rights_fee;
            $this->lease_advice_fee = $auction->get->lease_advice_fee;

            // Neighborhood marketing
            $this->neighborhood_insights_fee = $auction->get->neighborhood_insights_fee;
            $this->neighborhood_marketing_fee = $auction->get->neighborhood_marketing_fee;
            $this->neighborhood_materials_fee = $auction->get->neighborhood_materials_fee;

            // Custom services

            $this->custom_services = is_string($auction->get->custom_services) ? json_decode($auction->get->custom_services, true) ?? [] : (array)$auction->get->custom_services;

            $this->total_marketing_fee = $auction->get->total_marketing_fee;
            $this->total_flat_fee = $auction->get->total_flat_fee;

            // Flat fee agent (limited service) tenant

            $this->fees = is_string($auction->get->fees) ? json_decode($auction->get->fees, true) ?? [] : (array)$auction->get->fees;
            $this->enable = is_string($auction->get->enable) ? json_decode($auction->get->enable, true) ?? [] : (array)$auction->get->enable;

            $this->showings_count = $auction->get->showings_count;
            $this->attend_showings_count = $auction->get->attend_showings_count;
            $this->virtual_tours_count = $auction->get->virtual_tours_count;
            $this->understand_terms = (bool)$auction->get->understand_terms;

            // Seller
            $this->staging_duration = $auction->get->staging_duration;
            $this->open_house_count = $auction->get->open_house_count;

            // Landlord
            $this->virtual_showings_count = $auction->get->virtual_showings_count;

            // Load enable checkboxes
            // $enableFields = json_decode($auction->get->enable);
            // foreach ($enableFields as $field => $value) {
            //     if (property_exists($this, 'enable') && array_key_exists($field, $this->enable)) {
            //         $this->enable[$field] = $value;
            //     }
            // }
            
            // Dispatch browser event to sync select values after draft loads
            $this->dispatchBrowserEvent('draftLoaded');
        }
    }

    protected function saveAllMetadata($auction)
    {



        $auction->saveMeta('service_type', $this->service_type);
        $auction->saveMeta('user_type', $this->user_type);
        $auction->saveMeta('listing_status', $this->listing_status);
        $auction->saveMeta('auction_type', $this->auction_type);
        $auction->saveMeta('working_with_agent', $this->working_with_agent);
        $auction->saveMeta('listing_date', $this->listing_date);
        $auction->saveMeta('desired_agent_hire_date', $this->desired_agent_hire_date);
        $auction->saveMeta('expiration_date', $this->expiration_date);
        $auction->saveMeta('auction_time', $this->auction_time);

        // Location Information
        $auction->saveMeta('cities', json_encode($this->cities));
        $auction->saveMeta('counties', json_encode($this->counties));
        $auction->saveMeta('state', $this->state);
        $auction->saveMeta('zip_code', $this->zip_code);
        $auction->saveMeta('zipCodes', json_encode($this->zipCodes));
        $auction->saveMeta('property_city', $this->property_city);
        $auction->saveMeta('property_county', $this->property_county);
        $auction->saveMeta('property_state', $this->property_state);
        $auction->saveMeta('property_zip', $this->property_zip);

        // Property Details
        $auction->saveMeta('property_type', $this->property_type);
        $auction->saveMeta('property_items', json_encode($this->property_items));
        $auction->saveMeta('leasing_space', $this->leasing_space);
        $auction->saveMeta('other_property_items', $this->other_property_items);
        $auction->saveMeta('condition_prop', $this->condition_prop);
        $auction->saveMeta('other_property_condition', $this->other_property_condition);
        $auction->saveMeta('bathrooms', $this->bathrooms);
        $auction->saveMeta('other_bathrooms', $this->other_bathrooms);
        $auction->saveMeta('bedrooms', $this->bedrooms);
        $auction->saveMeta('other_bedrooms', $this->other_bedrooms);
        $auction->saveMeta('minimum_heated_square', $this->stripCommas($this->minimum_heated_square));
        $auction->saveMeta('total_square_feet', $this->stripCommas($this->total_square_feet));
        $auction->saveMeta('minimum_leaseable', $this->minimum_leaseable);
        $auction->saveMeta('min_acreage', $this->min_acreage);
        $auction->saveMeta('total_acreage', $this->total_acreage);
        $auction->saveMeta('minimum_cap_rate', $this->stripCommas($this->minimum_cap_rate));
        $assetsToSave = (!empty($this->business_assets) && is_array($this->business_assets))
            ? json_encode($this->business_assets)
            : (is_array($this->assets) ? json_encode($this->assets) : $this->assets);
        $auction->saveMeta('assets', $assetsToSave);
        $auction->saveMeta('assets_other', $this->assets_other);
        $auction->saveMeta('property_criteria', $this->property_criteria);
        $auction->saveMeta('unit_size', $this->unit_size);
        $auction->saveMeta('unit_size_other', $this->unit_size_other);
        $auction->saveMeta('preferance_details', $this->preferance_details);

        // Sale Provisions
        $auction->saveMeta('sale_provision', $this->sale_provision);
        $auction->saveMeta('sale_provision_other', $this->sale_provision_other);
        $auction->saveMeta('sale_provision_assignment', $this->sale_provision_assignment);
        $auction->saveMeta('assignment_fee_type', $this->assignment_fee_type);
        $auction->saveMeta('assignment_fee_amount', $this->stripCommas($this->assignment_fee_amount));
        $auction->saveMeta('buyer_sell_contract', $this->buyer_sell_contract);

        // Budget & Financing
        $auction->saveMeta('maximum_budget', $this->stripCommas($this->maximum_budget));
        $auction->saveMeta('offered_financing', json_encode($this->offered_financing));
        $auction->saveMeta('other_financing', $this->other_financing);
        $auction->saveMeta('cash_budget', $this->cash_budget);
        $auction->saveMeta('pre_approved', $this->pre_approved);
        $auction->saveMeta('pre_approval_amount', $this->pre_approval_amount);
        $auction->saveMeta('purchase_price', $this->stripCommas($this->purchase_price));
        $auction->saveMeta('down_payment_type', $this->down_payment_type);
        $auction->saveMeta('down_payment_amount', $this->stripCommas($this->down_payment_amount));
        $auction->saveMeta('seller_financing_type', $this->seller_financing_type);
        $auction->saveMeta('seller_financing_amount', $this->stripCommas($this->seller_financing_amount));
        $auction->saveMeta('interest_rate', $this->interest_rate);
        $auction->saveMeta('loan_duration', $this->loan_duration);
        $auction->saveMeta('prepayment_penalty', $this->prepayment_penalty);
        $auction->saveMeta('prepayment_penalty_amount', $this->stripCommas($this->prepayment_penalty_amount));
        $auction->saveMeta('balloon_payment_amount', $this->stripCommas($this->balloon_payment_amount));
        $auction->saveMeta('balloon_payment_date', $this->balloon_payment_date);
        $auction->saveMeta('assumable_terms', $this->assumable_terms);
        $auction->saveMeta('max_assumable_rate', $this->stripCommas($this->max_assumable_rate));
        $auction->saveMeta('assumable_monthly_escrow', $this->stripCommas($this->assumable_monthly_escrow));
        $auction->saveMeta('assumable_loan_term_remaining', $this->assumable_loan_term_remaining);
        $auction->saveMeta('assumable_loan_origination_date', $this->assumable_loan_origination_date);
        $auction->saveMeta('assumable_loan_servicer', $this->assumable_loan_servicer);
        $auction->saveMeta('assumable_fee_type', $this->assumable_fee_type);
        $auction->saveMeta('assumable_fee_amount', $this->stripCommas($this->assumable_fee_amount));
        $auction->saveMeta('assumable_occupancy_requirement', $this->assumable_occupancy_requirement);
        $auction->saveMeta('assumable_occupancy_other', $this->assumable_occupancy_other);
        $auction->saveMeta('max_monthly_payment', $this->stripCommas($this->max_monthly_payment));
        $auction->saveMeta('gap_payment_type', $this->gap_payment_type);
        $auction->saveMeta('gap_payment_amount', $this->stripCommas($this->gap_payment_amount));

        // Exchange / Trade
        $exchangeItemVal = $this->exchange_item;
        if (is_null($exchangeItemVal)) $exchangeItemVal = [];
        if (is_string($exchangeItemVal)) $exchangeItemVal = json_decode($exchangeItemVal, true) ?? [];
        $auction->saveMeta('exchange_item', json_encode(array_values(array_filter((array) $exchangeItemVal))));
        $auction->saveMeta('other_exchange_item', $this->other_exchange_item);
        $auction->saveMeta('exchange_item_value', $this->stripCommas($this->exchange_item_value));
        $auction->saveMeta('exchange_item_condition', $this->exchange_item_condition);
        $auction->saveMeta('additional_cash', $this->stripCommas($this->additional_cash));
        $auction->saveMeta('value_determination', $this->value_determination);
        $auction->saveMeta('exchange_transfer_method', $this->exchange_transfer_method);
        $auction->saveMeta('exchange_liens_disclosure', $this->exchange_liens_disclosure);
        $auction->saveMeta('exchange_liens_details', $this->exchange_liens_details);
        $auction->saveMeta('exchange_inspection_rights', $this->exchange_inspection_rights);

        // Lease Option
        $auction->saveMeta('lease_option_price', $this->stripCommas($this->lease_option_price));
        $auction->saveMeta('lease_option_terms', $this->lease_option_terms);
        $auction->saveMeta('lease_option_duration', $this->lease_option_duration);
        $auction->saveMeta('lease_option_payment', $this->stripCommas($this->lease_option_payment));
        $auction->saveMeta('lease_option_conditions', $this->lease_option_conditions);
        $auction->saveMeta('has_option_fee', $this->has_option_fee);
        $auction->saveMeta('option_fee_amount', $this->stripCommas($this->option_fee_amount));
        $auction->saveMeta('seller_lease_option_fee_credit', $this->seller_lease_option_fee_credit);
        $auction->saveMeta('seller_lease_option_fee_credit_percent', $this->seller_lease_option_fee_credit_percent);
        $auction->saveMeta('seller_lease_option_maintenance', $this->seller_lease_option_maintenance);
        $auction->saveMeta('seller_lease_option_extension_terms', $this->seller_lease_option_extension_terms);

        // Lease Purchase
        $auction->saveMeta('lease_purchase_price', $this->stripCommas($this->lease_purchase_price));
        $auction->saveMeta('lease_purchase_terms', $this->lease_purchase_terms);
        $auction->saveMeta('lease_purchase_duration', $this->lease_purchase_duration);
        $auction->saveMeta('lease_purchase_payment', $this->stripCommas($this->lease_purchase_payment));
        $auction->saveMeta('lease_purchase_conditions', $this->lease_purchase_conditions);
        $auction->saveMeta('seller_lease_purchase_rent_credit', $this->seller_lease_purchase_rent_credit);
        $auction->saveMeta('seller_lease_purchase_rent_credit_type', $this->seller_lease_purchase_rent_credit_type);
        $auction->saveMeta('seller_lease_purchase_rent_credit_amount', $this->stripCommas($this->seller_lease_purchase_rent_credit_amount));
        $auction->saveMeta('seller_lease_purchase_deposit', $this->stripCommas($this->seller_lease_purchase_deposit));
        $auction->saveMeta('seller_lease_purchase_maintenance', $this->seller_lease_purchase_maintenance);
        $auction->saveMeta('seller_lease_purchase_extension_terms', $this->seller_lease_purchase_extension_terms);
        $auction->saveMeta('lease_purchase_option_fee', $this->lease_purchase_option_fee);
        $auction->saveMeta('lease_purchase_option_fee_amount', $this->stripCommas($this->lease_purchase_option_fee_amount));

        // Cryptocurrency
        $auction->saveMeta('cryptocurrency_type', $this->cryptocurrency_type);
        $auction->saveMeta('crypto_percentage', $this->stripCommas($this->crypto_percentage));
        $auction->saveMeta('cash_percentage_crypto', $this->stripCommas($this->cash_percentage_crypto));

        // NFT
        $auction->saveMeta('nft_description', $this->nft_description);
        $auction->saveMeta('nft_percentage', $this->nft_percentage);
        $auction->saveMeta('cash_percentage_nft', $this->cash_percentage_nft);

        // Amenities and Features
        $auction->saveMeta('tenant_require', json_encode($this->tenant_require));
        $auction->saveMeta('carport_needed', $this->carport_needed);
        $auction->saveMeta('other_carport_needed', $this->other_carport_needed);
        $auction->saveMeta('garage_needed', $this->garage_needed);
        $auction->saveMeta('other_garage_needed', $this->other_garage_needed);
        $auction->saveMeta('garage_parking_spaces', $this->garage_parking_spaces);
        $auction->saveMeta('garage_parking_spaces_option', $this->garage_parking_spaces_option);
        $auction->saveMeta('other_parking_space_wrapper', $this->other_parking_space_wrapper);
        \Log::info('[SELLER POOL DEBUG]', ['pool_needed' => $this->pool_needed, 'pool_type' => $this->pool_type, 'property_type' => $this->property_type]);
        $auction->saveMeta('pool_needed', $this->pool_needed);
        $auction->saveMeta('pool_type', json_encode($this->pool_type));
        $auction->saveMeta('view_preference', json_encode($this->view_preference));
        $auction->saveMeta('other_preferences', $this->other_preferences);
        $auction->saveMeta('business_assets', json_encode($this->business_assets));
        $auction->saveMeta('appliances', json_encode($this->appliances));
        $auction->saveMeta('other_appliances', $this->other_appliances);
        $auction->saveMeta('real_estate_purchase', $this->real_estate_purchase);
        $auction->saveMeta('number_of_unit', $this->number_of_unit);
        $auction->saveMeta('number_of_unit_other', $this->number_of_unit_other);
        $auction->saveMeta('unit_number', $this->unit_number);
        $auction->saveMeta('unit_buildings', $this->unit_buildings);
        $auction->saveMeta('minimum_annual_net_income', $this->stripCommas($this->minimum_annual_net_income));
        $auction->saveMeta('leasing_55_plus', $this->leasing_55_plus);

        // Requirements
        $auction->saveMeta('non_negotiable_amenities', json_encode($this->non_negotiable_amenities));
        $auction->saveMeta('other_non_negotiable_amenities', $this->other_non_negotiable_amenities);
        $auction->saveMeta('budget', $this->budget);

        // Lease Terms
        $auction->saveMeta('lease_for', json_encode($this->lease_for));
        $auction->saveMeta('other_lease_for', $this->other_lease_for);
        $auction->saveMeta('lease_by', $this->lease_by);
        $auction->saveMeta('lease_date', $this->lease_date);

        // Tenant Information
        $auction->saveMeta('pets', $this->pets);
        $auction->saveMeta('number_of_pets', $this->number_of_pets);
        $auction->saveMeta('breed_of_pets', $this->breed_of_pets);
        $auction->saveMeta('type_of_pets', $this->type_of_pets);
        $auction->saveMeta('weight_of_pets', $this->weight_of_pets);
        $auction->saveMeta('credit_scroe_rating', json_encode($this->credit_scroe_rating));
        $auction->saveMeta('prior_eviction', $this->prior_eviction);
        $auction->saveMeta('eviction_explanation', $this->eviction_explanation);
        $auction->saveMeta('prior_felony', $this->prior_felony);
        $auction->saveMeta('prior_felony_explanation', $this->prior_felony_explanation);
        $auction->saveMeta('monthly_income', $this->monthly_income);
        $auction->saveMeta('number_occupant', $this->number_occupant);

        // Services
        $auction->saveMeta('services', json_encode($this->services));
        $auction->saveMeta('other_services', $this->other_services);
        $auction->saveMeta('flat_fee_services', json_encode($this->flat_fee_services));
        $auction->saveMeta('additional_details', $this->additional_details);

        // Broker Compensation
        $auction->saveMeta('commission_structure', $this->commission_structure);

        // Lease Fee
        $auction->saveMeta('lease_fee_type', $this->lease_fee_type);
        $auction->saveMeta('lease_fee_flat', $this->stripCommas($this->lease_fee_flat));
        $auction->saveMeta('lease_fee_percentage', $this->lease_fee_percentage);
        $auction->saveMeta('lease_fee_months', $this->lease_fee_months);
        $auction->saveMeta('lease_fee_percentage_monthly_rent', $this->lease_fee_percentage_monthly_rent);
        $auction->saveMeta('lease_fee_flat_combo', $this->stripCommas($this->lease_fee_flat_combo));
        $auction->saveMeta('lease_fee_percentage_combo', $this->lease_fee_percentage_combo);
        $auction->saveMeta('lease_fee_other', $this->lease_fee_other);

        // Purchase Fee
        $auction->saveMeta('purchase_fee_type', $this->purchase_fee_type);
        $auction->saveMeta('purchase_fee_percentage', $this->purchase_fee_percentage);
        $auction->saveMeta('purchase_fee_flat', $this->stripCommas($this->purchase_fee_flat));
        $auction->saveMeta('purchase_fee_percentage_combo', $this->purchase_fee_percentage_combo);
        $auction->saveMeta('purchase_fee_flat_combo', $this->stripCommas($this->purchase_fee_flat_combo));
        $auction->saveMeta('purchase_fee_other', $this->purchase_fee_other);

        // Lease-Option Fee
        $auction->saveMeta('lease_option_fee_type', $this->lease_option_fee_type);
        $auction->saveMeta('lease_option_fee_flat', $this->stripCommas($this->lease_option_fee_flat));
        $auction->saveMeta('lease_option_fee_percentage', $this->lease_option_fee_percentage);
        $auction->saveMeta('lease_option_fee_other', $this->lease_option_fee_other);

        // Other Broker Terms
        $auction->saveMeta('protection_period', $this->protection_period);
        $auction->saveMeta('early_termination_fee_option', $this->early_termination_fee_option);
        $auction->saveMeta('early_termination_fee_amount', $this->stripCommas($this->early_termination_fee_amount));
        $auction->saveMeta('lease_type', $this->lease_type);
        $auction->saveMeta('lease_value', $this->stripCommas($this->lease_value));
        $auction->saveMeta('purchase_type', $this->purchase_type);
        $auction->saveMeta('purchase_value', $this->stripCommas($this->purchase_value));
        $auction->saveMeta('interested_lease_option_agreement', $this->interested_lease_option_agreement);
        $auction->saveMeta('retainer_fee_option', $this->retainer_fee_option);
        $auction->saveMeta('retainer_fee_amount', $this->stripCommas($this->retainer_fee_amount));
        $auction->saveMeta('retainer_fee_application', $this->retainer_fee_application);
        $auction->saveMeta('agency_agreement_timeframe', $this->agency_agreement_timeframe);
        $auction->saveMeta('agency_agreement_custom', $this->agency_agreement_custom);
        $auction->saveMeta('brokerage_relationship', $this->brokerage_relationship);
        $auction->saveMeta('additional_details_broker', $this->additional_details_broker);

        // 2nd tab limited services
        // Meeting details
        $auction->saveMeta('person_meeting', $this->person_meeting);
        $auction->saveMeta('meeting_details_first_name', $this->meeting_details_first_name);
        $auction->saveMeta('meeting_details_last_name', $this->meeting_details_last_name);
        $auction->saveMeta('meeting_details_phone', $this->meeting_details_phone);
        $auction->saveMeta('meeting_details_email', $this->meeting_details_email);


        // Meeting details yes
        $auction->saveMeta('address', $this->address);
        $auction->saveMeta('meeting_details_meeting_time', $this->meeting_details_meeting_time);
        $auction->saveMeta('meeting_details_meeting_date', $this->meeting_details_meeting_date);
        $auction->saveMeta('meeting_details_time_zone', $this->meeting_details_time_zone);
        $auction->saveMeta('meeting_details_instructions', $this->meeting_details_instructions);
        $auction->saveMeta('service_completion_date', $this->service_completion_date);
        $auction->saveMeta('service_completion_time', $this->service_completion_time);
        $auction->saveMeta('service_time_zone', $this->service_time_zone);
        $auction->saveMeta('meeting_details_additional_details', $this->meeting_details_additional_details);


        // Marketing Services
        $auction->saveMeta('list_criteria', $this->list_criteria);
        $auction->saveMeta('list_criteria_fee', $this->list_criteria_fee);
        $auction->saveMeta('market_groups', $this->market_groups);
        $auction->saveMeta('market_groups_fee', $this->market_groups_fee);
        $auction->saveMeta('promote_social', $this->promote_social);
        $auction->saveMeta('promote_social_fee', $this->promote_social_fee);
        $auction->saveMeta('launch_ads', $this->launch_ads);
        $auction->saveMeta('launch_ads_fee', $this->launch_ads_fee);
        $auction->saveMeta('include_marketing_fee', $this->include_marketing_fee);
        $auction->saveMeta('marketing_materials_fee', $this->marketing_materials_fee);

        $auction->saveMeta('email_notifications_fee', $this->email_notifications_fee);
        $auction->saveMeta('off_market_search_fee', $this->off_market_search_fee);
        $auction->saveMeta('mls_filter_fee', $this->mls_filter_fee);
        $auction->saveMeta('email_marketing_fee', $this->email_marketing_fee);

        // Property Showings
        $auction->saveMeta('schedule_showings', $this->schedule_showings);
        $auction->saveMeta('number_of_showings_to_schedule', $this->number_of_showings_to_schedule);
        $auction->saveMeta('schedule_showings_fee', $this->schedule_showings_fee);
        $auction->saveMeta('attend_showings', $this->attend_showings);
        $auction->saveMeta('number_of_showings_to_attend', $this->number_of_showings_to_attend);
        $auction->saveMeta('attend_showings_fee', $this->attend_showings_fee);
        $auction->saveMeta('provide_virtual_tours', $this->provide_virtual_tours);
        $auction->saveMeta('number_of_virtual_tours', $this->number_of_virtual_tours);
        $auction->saveMeta('virtual_tours_fee', $this->virtual_tours_fee);

        // Application & Lease Support
        $auction->saveMeta('assist_application', $this->assist_application);
        $auction->saveMeta('assist_application_fee', $this->assist_application_fee);
        $auction->saveMeta('collect_documents', $this->collect_documents);
        $auction->saveMeta('collect_documents_fee', $this->collect_documents_fee);
        $auction->saveMeta('submit_application', $this->submit_application);
        $auction->saveMeta('submit_application_fee', $this->submit_application_fee);
        $auction->saveMeta('review_lease', $this->review_lease);
        $auction->saveMeta('review_lease_fee', $this->review_lease_fee);
        $auction->saveMeta('provide_lease_form', $this->provide_lease_form);
        $auction->saveMeta('provide_lease_form_fee', $this->provide_lease_form_fee);
        $auction->saveMeta('coordinate_signing', $this->coordinate_signing);
        $auction->saveMeta('coordinate_signing_fee', $this->coordinate_signing_fee);
        $auction->saveMeta('prepare_application_fee', $this->prepare_application_fee);

        // Move Services
        $auction->saveMeta('move_in_inspection_fee', $this->move_in_inspection_fee);
        $auction->saveMeta('moving_resources_fee', $this->moving_resources_fee);
        $auction->saveMeta('short_term_housing_fee', $this->short_term_housing_fee);

        // Advisory Services
        $auction->saveMeta('rental_rights_fee', $this->rental_rights_fee);
        $auction->saveMeta('lease_advice_fee', $this->lease_advice_fee);

        // Neighborhood Marketing
        $auction->saveMeta('neighborhood_insights_fee', $this->neighborhood_insights_fee);
        $auction->saveMeta('neighborhood_marketing_fee', $this->neighborhood_marketing_fee);
        $auction->saveMeta('neighborhood_materials_fee', $this->neighborhood_materials_fee);



        $auction->saveMeta('custom_services', json_encode($this->custom_services));
        $auction->saveMeta('total_marketing_fee', $this->total_marketing_fee);
        $auction->saveMeta('total_flat_fee', $this->total_flat_fee);


        //Flat Fee Agent (Limited Service) Tenant
        $auction->saveMeta('fees', json_encode($this->fees));
        $auction->saveMeta('enable', json_encode($this->enable));
        $auction->saveMeta('showings_count', $this->showings_count);
        $auction->saveMeta('attend_showings_count', $this->attend_showings_count);
        $auction->saveMeta('virtual_tours_count', $this->virtual_tours_count);
        $auction->saveMeta('understand_terms', $this->understand_terms);

        //seller
        $auction->saveMeta('staging_duration', $this->staging_duration);
        $auction->saveMeta('open_house_count', $this->open_house_count);



        //Landlord
        $auction->saveMeta('virtual_showings_count', $this->virtual_showings_count);

        // Contact Information
        $auction->saveMeta('first_name', $this->first_name);
        $auction->saveMeta('last_name', $this->last_name);
        $phoneDigitsOnly = preg_replace('/\D/', '', $this->phone_number);
        $auction->saveMeta('phone_number', $phoneDigitsOnly);
        $auction->saveMeta('email', $this->email);
        $auction->saveMeta('current_status', $this->current_status);
        $auction->saveMeta('video_link', $this->video_link);

        // Save photo - process new uploads; preserve existing saved filename on re-save
        if ($this->photo && !is_string($this->photo)) {
            $extensionPhoto = $this->photo->getClientOriginalExtension(); // Get file extension
            $uuid = (string) Str::uuid(); // Generate a unique UUID
            $photoName = $uuid . '.' . $extensionPhoto; // Create a unique file name

            // Save file to public/auction/images using Livewire's store method
            $photoPath = $this->photo->storeAs('auction/images', $photoName, 'public');

            // Save file name to database
            $auction->saveMeta('photo', $photoName);
        } elseif ($this->photo && is_string($this->photo)) {
            $auction->saveMeta('photo', $this->photo);
        }

        // Save video - only process if it's a new upload (UploadedFile), not an existing string path
        if ($this->video && !is_string($this->video)) {
            $extensionVideo = $this->video->getClientOriginalExtension(); // Get file extension
            $uuid = (string) Str::uuid(); // Generate a unique UUID
            $videoName = $uuid . '.' . $extensionVideo; // Create a unique file name

            // Save file to public/auction/videos using Livewire's store method
            $videoPath = $this->video->storeAs('auction/videos', $videoName, 'public');

            // Save file name to database
            $auction->saveMeta('video', $videoName);
        }
    }

    /**
     * Sanitize and clear stale values before submission
     * This clears hidden field values that are not applicable based on current selections
     */
    protected function sanitizeBeforeSubmit()
    {
        // Clear bidding period fields if not using Bidding Period listing type
        if ($this->auction_type !== 'Bidding Period') {
            $this->auction_time = '';
        }

        // Clear seller leasing fields if not interested in leasing
        if ($this->interested_purchase_fee_type !== 'Yes') {
            $this->seller_leasing_fee_type = '';
            $this->seller_leasing_gross = '';
            $this->seller_leasing_gross_rental = '';
            $this->seller_leasing_gross_month_rent = '';
            $this->seller_leasing_gross_no_of_months = '';
            $this->seller_leasing_gross_flat = '';
            $this->seller_leasing_gross_other = '';
            $this->seller_leasing_each_rental = '';
            $this->seller_leasing_gross_percentage = '';
            $this->sales_tax_option_gross = '';
        }

        // Clear commission structure type fields if no compensation offered
        if ($this->commission_structure === 'No Compensation Offered to the Buyer\'s Broker') {
            $this->commission_structure_type = '';
            $this->commission_structure_type_fee_flat = '';
            $this->commission_structure_type_fee_percentage = '';
            $this->commission_structure_type_fee_other = '';
        }

        // Clear nominal fee for non-applicable property types
        if (!in_array($this->property_type, ['Income', 'Commercial', 'Business'])) {
            $this->nominal = '';
        }

        // Clear Commercial-specific fields for Residential/Income/Vacant Land
        if (in_array($this->property_type, ['Residential', 'Income', 'Vacant Land'])) {
            $this->seller_leasing_gross_no_of_months = '';
            $this->seller_leasing_gross_sales_tax_first_month = '';
            $this->seller_leasing_gross_sales_tax_flat_free_gross = '';
            $this->seller_leasing_gross_sales_tax_option_gross = '';
            $this->seller_leasing_gross_flat_net_combo = '';
            $this->seller_leasing_gross_percentage_net_combo = '';
        }

        // Clear fee fields based on selected type
        if ($this->seller_leasing_fee_type !== 'Flat Fee') {
            $this->seller_leasing_gross_flat = '';
        }
        if ($this->seller_leasing_fee_type !== 'other') {
            $this->seller_leasing_gross_other = '';
        }
        if ($this->seller_leasing_fee_type !== 'Percentage of the Gross Lease Value') {
            $this->seller_leasing_gross = '';
        }

        // Clear commission structure type fields based on selected type
        if ($this->commission_structure_type !== 'Flat Fee') {
            $this->commission_structure_type_fee_flat = '';
        }
        if ($this->commission_structure_type !== 'Percentage of the Total Purchase Price') {
            $this->commission_structure_type_fee_percentage = '';
        }
        if ($this->commission_structure_type !== 'other') {
            $this->commission_structure_type_fee_other = '';
        }

        // Clear purchase fee fields based on selected type (using actual option values: flat, percentage, combo, other)
        if ($this->purchase_fee_type !== 'flat' && $this->purchase_fee_type !== 'combo') {
            $this->purchase_fee_flat = '';
        }
        if ($this->purchase_fee_type !== 'percentage' && $this->purchase_fee_type !== 'combo') {
            $this->purchase_fee_percentage = '';
        }
        if ($this->purchase_fee_type !== 'combo') {
            $this->purchase_fee_percentage_combo = '';
            $this->purchase_fee_flat_combo = '';
        }
        if ($this->purchase_fee_type !== 'other') {
            $this->purchase_fee_other = '';
        }
    }

    /**
     * Get conditional validation rules based on current selections
     */
    protected function getConditionalRules()
    {
        $rules = [
            'listing_title' => 'required|string|max:255',
            'property_type' => 'required|string',
            'state' => 'required|string',
        ];

        // Bidding Period fields - only validate if listing type is Bidding Period
        if ($this->auction_type === 'Bidding Period') {
            $rules['auction_time'] = 'required|string';
        }

        // Seller leasing fields - only validate if interested in leasing
        if ($this->interested_purchase_fee_type === 'Yes') {
            $rules['seller_leasing_fee_type'] = 'required|string';
            
            // Validate specific fields based on leasing fee type
            if ($this->seller_leasing_fee_type === 'Flat Fee') {
                $rules['seller_leasing_gross_flat'] = 'required|numeric|min:0';
            } elseif ($this->seller_leasing_fee_type === 'Percentage of the Gross Lease Value') {
                $rules['seller_leasing_gross'] = 'required|numeric|min:0|max:100';
            } elseif ($this->seller_leasing_fee_type === 'other') {
                $rules['seller_leasing_gross_other'] = 'required|string';
            }
        }

        // Commission structure validation
        if (in_array($this->commission_structure, [
            'Seller\'s Broker to Compensate Buyer\'s Broker from Seller\'s Broker Commission',
            'Seller to Pay Buyer\'s Broker Separately'
        ])) {
            $rules['commission_structure_type'] = 'required|string';
            
            if ($this->commission_structure_type === 'Flat Fee') {
                $rules['commission_structure_type_fee_flat'] = 'required|string';
            } elseif ($this->commission_structure_type === 'Percentage of the Total Purchase Price') {
                $rules['commission_structure_type_fee_percentage'] = 'required|numeric|min:0|max:100';
            } elseif ($this->commission_structure_type === 'other') {
                $rules['commission_structure_type_fee_other'] = 'required|string';
            }
        }

        // Purchase fee validation (using actual option values: flat, percentage, combo, other)
        if (!empty($this->purchase_fee_type)) {
            if ($this->purchase_fee_type === 'flat') {
                $rules['purchase_fee_flat'] = 'required|string';
            } elseif ($this->purchase_fee_type === 'percentage') {
                $rules['purchase_fee_percentage'] = 'required|numeric|min:0|max:100';
            } elseif ($this->purchase_fee_type === 'combo') {
                $rules['purchase_fee_percentage_combo'] = 'required|numeric|min:0|max:100';
                $rules['purchase_fee_flat_combo'] = 'required|string';
            } elseif ($this->purchase_fee_type === 'other') {
                $rules['purchase_fee_other'] = 'required|string';
            }
        }

        return $rules;
    }

    /**
     * Get custom validation messages
     */
    protected function getValidationMessages()
    {
        return [
            'listing_title.required' => 'Listing Title is required',
            'property_type.required' => 'Property Type is required',
            'state.required' => 'State is required',
            'auction_time.required' => 'Bidding Period Length is required for Bidding Period listings',
            'seller_leasing_fee_type.required' => 'Seller\'s Broker Leasing Fee type is required when offering leasing',
            'seller_leasing_gross_flat.required' => 'Flat Fee amount is required',
            'seller_leasing_gross.required' => 'Percentage of Gross Lease Value is required',
            'seller_leasing_gross_other.required' => 'Custom leasing fee structure is required',
            'commission_structure_type.required' => 'Buyer\'s Broker Commission Fee type is required',
            'commission_structure_type_fee_flat.required' => 'Commission flat fee amount is required',
            'commission_structure_type_fee_percentage.required' => 'Commission percentage is required',
            'commission_structure_type_fee_other.required' => 'Custom commission structure is required',
            'purchase_fee_flat.required' => 'Seller\'s Broker Purchase Fee (flat fee) is required',
            'purchase_fee_percentage.required' => 'Seller\'s Broker Purchase Fee (percentage) is required',
            'purchase_fee_percentage_combo.required' => 'Seller\'s Broker Purchase Fee (percentage) is required',
            'purchase_fee_flat_combo.required' => 'Seller\'s Broker Purchase Fee (flat fee) is required',
            'purchase_fee_other.required' => 'Custom purchase fee structure is required',
        ];
    }

    public function store()
    {
        \Log::info('[SELLER STORE START]', [
            'user_id' => auth()->id(),
            'listing_date' => $this->listing_date ?? null,
        ]);

        try {
            // Sanitize stale values before validation
            $this->sanitizeBeforeSubmit();

            // Validate with conditional rules
            $validatedData = $this->validate(
                $this->getConditionalRules(),
                $this->getValidationMessages()
            );

            $this->isDraft = 0;

            $auction = $this->listingId
                ? SellerAgentAuctionModel::find($this->listingId)
                : new SellerAgentAuctionModel();

            $auction->user_id = Auth::id();
            $auction->title = $this->listing_title;
            $auction->is_draft = 0;
            
            // Set required NOT NULL fields for seller type
            if (empty($auction->address)) {
                $auction->address = $this->listing_title ?? 'Seller Agent Listing';
            }
            $auction->is_approved = 1;
            $auction->is_sold = 0;
            $auction->is_paid = 0;
            
            $auction->save();

            $this->listingId = $auction->id;

            $this->saveAllMetadata($auction);

            \Log::info('[SELLER LISTING SUBMITTED]', [
                'record_id' => $auction->id,
                'listing_id' => $auction->listing_id ?? 'N/A',
                'user_id' => $auction->user_id,
                'is_draft' => $auction->is_draft,
                'is_approved' => $auction->is_approved ?? 'N/A',
                'is_sold' => $auction->is_sold ?? 'N/A',
            ]);

            session()->flash('success', 'Listing submitted successfully!');

            $url = route('seller.agent.auction.detail', ['id' => $auction->id]);

            \Log::info('[SELLER STORE BEFORE REDIRECT EVENT]', ['url' => $url]);

            // Dispatch browser event to force redirect (Livewire v2 compatible)
            $this->dispatchBrowserEvent('force-redirect', ['url' => $url]);

            // Redirect to the Seller listing view page (fallback)
            return redirect()->to($url);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // Log validation errors for debugging
            Log::error('Seller Agent Auction validation failed', [
                'errors' => $e->errors(),
                'user_id' => Auth::id()
            ]);
            
            // Show specific error message to user
            $errorMessages = collect($e->errors())->flatten()->take(3)->implode(' | ');
            session()->flash('error', 'Missing/invalid: ' . $errorMessages);
            
            throw $e; // Re-throw to show field-level errors
        } catch (\Exception $e) {
            Log::error('Seller Agent Auction save error', [
                'message' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            session()->flash('error', 'Error saving listing: ' . $e->getMessage());
        }
    }

    public function deleteAllDrafts()
    {
        try {
            // Get all draft IDs first
            $draftIds = SellerAgentAuctionModel::where('user_id', Auth::id())
                ->where('is_draft', true)
                ->pluck('id');

            // Delete all metadata associated with these drafts
            if ($draftIds->isNotEmpty()) {
                DB::table('seller_agent_auction_metas')
                    ->whereIn('seller_agent_auction_id', $draftIds)
                    ->delete();
            }

            // Now delete the drafts themselves
            $deleted = SellerAgentAuctionModel::where('user_id', Auth::id())
                ->where('is_draft', true)
                ->delete();

            // Reset component state and switch to write mode

            session()->flash('success', 'All drafts and their associated data have been deleted successfully.');
        } catch (\Exception $e) {
            session()->flash('error', 'Error deleting drafts: ' . $e->getMessage());
        }
    }
    public function deleteDraft($draftId)
    {
        try {
            // Delete metadata first
            DB::table('seller_agent_auction_metas')
                ->where('seller_agent_auction_id', $draftId)
                ->delete();

            // Delete the draft
            SellerAgentAuctionModel::where('id', $draftId)
                ->where('user_id', Auth::id())
                ->delete();

            // Check if there are any drafts left
            $this->hasDrafts = SellerAgentAuctionModel::where('user_id', Auth::id())
                ->where('is_draft', true)
                ->exists();

            session()->flash('success', 'Draft deleted successfully.');
        } catch (\Exception $e) {
            session()->flash('error', 'Error deleting draft: ' . $e->getMessage());
        }
    }

    protected function stripCommas($value)
    {
        if ($value === null || $value === '') {
            return $value;
        }
        return str_replace(',', '', $value);
    }
}
