<?php

namespace App\Http\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\TenantAgentAuction as HireTenantAgentAuction;
use App\Models\BuyerAgentAuction as HireBuyerAgentAuction;
use App\Models\LandlordAgentAuction as HirelandLordAgentAuction;
use App\Models\SellerAgentAuction as HireSellerAgentAuction;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;
use Livewire\Features\SupportRedirects\Redirector;
use GuzzleHttp\Client;
use App\Models\UsState;
use App\Models\UsCounty;
use App\Models\UsCity;
use App\Support\TenantServicesCatalog;

class TenantAgentAuction extends Component
{
    use WithFileUploads;

    // Livewire properties for form fields
    public $hasDrafts = false;
    public $auctionId; // To store the auction ID for editing

    public $listingId = null; // To track existing listings
    public $isDraft = false; // To track draft status
    protected $isLoadingData = false; // Guard flag to prevent updated* hooks from resetting fields during draft load
    public $service_type = 'full_service'; // 'full_service' or 'limited_service'
    public $listing_status = 'Active'; // 'Active', 'Pending', or 'Hired Agent'

    // public $user_type = 'tenant'; // Default to tenant or whatever makes sense
    public $auction_type = '';

    public $workflow_type = 'hire_agent'; // Multi-mode workflow engine: 'hire_agent' | 'offer'
    public $referral_percentage = '';

    public $user_type = '';
    public $listing_title = '';
    public $working_with_agent = '';
    public $listing_date = '';
    public $desired_agent_hire_date = '';
    public $expiration_date = '';
    public $auction_time = '';
    public $agent_bid_visibility = '';
    public $unit_types = '';
    public $meeting_Preference = '';



    public $number_of_unit = '';
    // Location fields
    public $state = '';
    public $property_type = '';
    public $rent_includes =  [];
    public $other_rent_include = '';


    // Property details
    public $property_items = [];
    public $other_property_items = '';
    public $condition_prop_buyer = [];
    public $condition_prop_buyer_json = '[]';
    public $condition_prop = '';

    public $leasing_spaces = '';

    public $leasing_spaces_tenant = [];
    public $business_type = '';
    public $business_type_selected = '';
    public $other_business_type = '';
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
    public $sale_provision = [];
    public $sale_provision_other = '';
    public $sale_provision_assignment = '';
    public $assignment_fee_type = '';
    public $assignment_fee_amount = '';
    public $target_closing_date = '';
    public $buyer_sell_contract = '';
    public $total_square_feet = '';
    public $sqft_heated_source = '';
    public $offered_financing = [];
    public $other_financing = '';
    public $cash_budget = '';
    public $beds_unit = '';
    public $baths_unit = '';
    public $carport_spaces = '';
    public $number_occupied = '';
    public $expected_rent = '';
    public $unit_type_description = '';
    public $number_of_units = '';
    public $number_of_unit_type = [];

    public $Number_of_Garage_Spaces = '';
    public $Number_of_Carport_Spaces = '';
    public $Number_of_Units = '';
    public $Number_Occupied = '';
    public $Expected_Rent = '';
    public $Unit_Type_Description = '';
    public $garage_spaces = '';

    public $pre_approved = '';
    public $loan_duration = '';
    public $nft_description = '';
    public $nft_percentage = '';
    public $cash_percentage_nft = '';
    public $pre_approval_amount = '';
    public $purchase_price = '';
    public $down_payment_type = '$';
    public $down_payment_amount = '';
    public $seller_down_payment_amount = '';
    public $seller_financing_type = '$';
    public $seller_financing_amount = '';
    public $garage_needed = '';
    public $other_garage_needed = '';
    public $other_exchange_item = '';
    public $garage_parking_spaces = '';
    public $garage_parking_spaces_option = [];
    public $garage_parking_spaces_option_buyer = [];
    public $other_parking_space_wrapper = '';
    public $pool_needed = '';
    public $pool_type = [];
    public $view_preference = [];
    public $other_preferences = '';
    public $prepayment_penalty = '';
    public $prepayment_penalty_amount = '';
    public $balloon_payment = '';
    public $balloon_payment_amount = '';
    public $balloon_payment_date = '';
    public $assumable_terms = '';
    public $max_assumable_rate = '';
    public $max_monthly_payment = '';
    public $gap_payment_type = '$';
    public $gap_payment_amount = '';

    public $appliances = [];
    public $other_appliances = '';
    public $assets = [];
    public $assets_other = '';
    public $business_assets = [];
    public $unit_number = '';
    public $unit_size = '';
    public $unit_size_other = '';
    public $minimum_annual_net_income = '';
    public $minimum_cap_rate = '';
    public $lease_purchase_terms = '';
    public $lease_purchase_price = '';
    public $real_estate_purchase = '';
    public $lease_purchase_duration = '';
    public $lease_purchase_conditions = '';
    public $cryptocurrency_type = '';
    public $crypto_percentage = '';
    public $cash_percentage_crypto = '';
    public $value_determination = '';
    public $additional_cash = '';
    public $exchange_item_value = '';
    public $lease_option_duration = '';
    public $lease_option_terms = '';
    public $lease_option_price = '';
    public $option_fee_amount = '';
    public $lease_purchase_payment = '';
    public $lease_purchase_option_fee_amount = '';
    public $lease_option_conditions = '';
    public $lease_option_payment = '';
    public $leasing_55_plus = '';
    public $non_negotiable_amenities = [];
    public $other_non_negotiable_amenities = '';
    public $budget = '';
    public $occupied_until = '';
    public $occupancy_status  = '';
    public $has_storage_space  = '';
    public $lease_terms   = [];
    public $rent_frequency   = '';
    public $lease_length = '';
    public $residential_restrictions   = '';
    public $maintenance_handler  = '';
    public $maintenance_response_time   = '';
    public $included_storage_space = '';
    public $storage_space = '';
    public $included_storage_space_res_both = '';
    public $storage_space_res_both = '';
    public $included_storage_space_res_single = '';
    public $storage_space_res_single = '';
    public $included_storage_space_com_entire = '';
    public $storage_space_com_entire = '';
    public $included_storage_space_com_single = '';
    public $storage_space_com_single = '';
    public $guests_allowed = '';
    public $room_restrictions = '';
    public $common_area_access = '';
    public $utilities = '';
    public $desired_rent = '';
    public $common_area_cleaning = '';
    public $bathroom_type = '';
    public $room_size = '';
    public $shared_amenities = '';
    public $building_hours = '';
    public $unit_buildings = '';
    public $access_24_7 = '';
    public $zoning_allows = '';
    public $space_features = '';
    public $storage_space_details = '';
    public $neighboring_tenants = '';
    public $terms_of_lease = [];
    public $custom_lease_term = '';
    public $tenant_pays = [];
    public $other_tenant_pays = '';
    public $owner_pays = [];
    public $other_owner_pays = '';
    public $owner_pays_other = '';
    public $preference_details = '';
    public $property_criteria = '';
    public $bathroom_facilities = '';
    public $common_areas_cleaning = '';
    public $lease_fee_percentage_combo_net = '';
    public $preferance_details = '';
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
    public $has_breed_restrictions = '';
    public $breed_restrictions = '';
    public $credit_scroe_rating = [];
    public $prior_eviction = '';
    public $eviction_explanation = '';
    public $prior_felony = '';
    public $prior_felony_explanation = '';
    public $monthly_income = '';
    public $number_occupant = '';
    public $services = [];
    public $photo_enhancements = [];
    public $custom_enhancement = '';
    public bool $other_services_enabled = false;
    public array $other_services = []; // Always initialize as an array
    public $service_animal = '';
    public $support_animal = '';
    public $screening_concerns;
    public $screening_concerns_explanation;

    public $flat_fee_services = [];

    public $additional_details = '';

    // Broker compensation
    public $commission_structure = '';
    public $commission_structure_type = '';
    public $commission_structure_type_fee_flat = '';
    public $commission_structure_type_fee_percentage = '';
    public $commission_structure_type_fee_percentage_combo = '';
    public $commission_structure_type_fee_flat_combo = '';
    public $commission_structure_type_fee_other = '';


    public $exchange_item = [];
    public $exchange_item_condition = '';
    public $has_option_fee  = '';
    public $lease_purchase_option_fee = '';
    
    // New properties for financing types (December 2025)
    public $assumable_loan_type = '';
    public $assumable_monthly_escrow = '';
    public $assumable_loan_term_remaining = '';
    public $assumable_loan_origination_date = '';
    public $assumable_loan_servicer = '';
    public $assumable_fee_type = '$';
    public $assumable_fee_amount = '';
    public $assumable_occupancy_requirement = '';
    public $assumable_occupancy_other = '';
    
    // Cryptocurrency additional fields
    public $crypto_exchange_method = '';
    public $crypto_custodian_wallet = '';
    public $crypto_transaction_fees = '';
    public $crypto_transfer_timing = '';
    public $crypto_transfer_timing_other = '';
    
    // Exchange/Trade additional fields
    public $exchange_transfer_method = '';
    public $exchange_liens = '';
    public $exchange_liens_details = '';
    public $exchange_inspection_rights = '';
    
    // NFT additional fields
    public $nft_valuation_method = '';
    public $nft_transfer_method = '';
    public $nft_gas_fees = '';
    
    // Lease Option additional fields
    public $lease_option_fee_credit = '';
    public $lease_option_fee_credit_percentage = '';
    public $lease_option_maintenance = '';
    public $lease_option_extension_terms = '';
    
    // Lease Purchase additional fields
    public $lease_purchase_rent_credit = '';
    public $lease_purchase_rent_credit_amount_type = '$';
    public $lease_purchase_rent_credit_amount = '';
    public $lease_purchase_deposit = '';
    public $lease_purchase_maintenance = '';
    public $lease_purchase_extension_terms = '';
    
    // Seller Financing additional fields
    public $seller_amortization_type = '';
    public $seller_amortization_other = '';
    public $seller_payment_frequency = '';
    public $seller_payment_frequency_other = '';
    public $seller_late_fee_type = '$';
    public $seller_late_fee_amount = '';
    public $lease_fee_type = '';

    public string $lease_fee_flat_type = '$';

    public $lease_fee_flat = '';
    public $lease_fee_flat_combo_net = '';
    public $lease_fee_percentage_net = '';

    public $lease_fee_percentage = '';
    public $lease_fee_months = '';
    public $lease_fee_percentage_monthly_rent = '';
    public $lease_fee_flat_combo = '';
    public $lease_fee_percentage_combo = '';
    public $lease_fee_other = '';
    public $purchase_fee_type = '';
    public $purchase_fee_percentage = '';
    public $purchase_fee_flat = '';
    public $purchase_fee_flat_type = '$';
    public $purchase_fee_rental_period = '';

    public $purchase_fee_percentage_combo = '';
    public $purchase_fee_flat_combo = '';
    public $purchase_fee_other = '';
    public $lease_option_fee_type = '';
    public $lease_option_fee_flat = '';
    public $lease_option_fee_percentage = '';
    public $lease_option_fee_other = '';
    public $early_termination_fee_option = '';
    public $protection_period = '';
    public $lease_fee_percentage_monthly_number = '';
    public $early_termination_fee_amount = '';
    public $retainer_fee_option = '';
    public $retainer_fee_amount = '';
    public $retainer_fee_application = '';
    public $agency_agreement_timeframe = '';
    public $agency_agreement_custom = '';
    public $brokerage_relationship = '';
    public $maximum_budget = '';
    public $interest_rate = '';
    public $occupant_types = '';
    public $occupant_status = '';
    public $occupant_tenant = '';
    public $desired_rental_amount = '';
    public $lease_amount_frequency = '';
    public $desired_lease_length = [];
    public $additional_details_broker = '';
    public $broker_fee_timing  = '';

    public $renewal_fee_type = '';
    public $renewal_fee_percentage = '';
    public $renewal_fee_lease_value = '';
    public $renewal_fee_first_month = '';
    public $renewal_fee_flat_free = '';
    public $renewal_fee_custom = '';
    public $renewal_fee_sales_tax_lease_value = '';
    public $renewal_fee_sales_tax_flat_fee = '';
    public $renewal_fee_sales_tax_first_month = '';
    public $renewal_fee_no_of_months = '';


    public $expansion_commission_type = '';
    public $interested_in_lease_option = '';
    public $tenant_broker_commission_structure = '';
    public $net_aggregate_rent = '';
    public $purchase_fee_net_aggregate = '';
    public $broker_fee_days_from_rent = '';
    public $broker_fee_days_after_lease = '';
    public $broker_fee_days_after_rent = '';
    public $broker_fee_timing_other = '';
    public $expansion_gross_percentage = '';
    public $expansion_first_month_percentage = '';
    public $expansion_flat_fee = '';
    public $expansion_custom_commission = '';
    public $purchase_fee_gross_rent = '';
    public $purchase_fee_other_commercial = '';
    public $purchase_fee_purchase_price = '';
    public $purchase_fee_flat_commercial = '';
    public $purchase_fee_months = '';
    public $purchase_fee_monthly_percentage = '';
    public $sales_tax_option_gross = '';
    public $sales_tax_option_flat = '';
    public $sales_tax_option_monthly = '';
    public $split_payment_due = '';
    public $split_payment_due_other = '';
    public $broker_fee_days_after_due_event = '';
    public $gross_percentage_rent = '';
    public $expansion_commission_percentage = '';
    public $flat_fee = '';
    public $no_of_months = '';
    public $month_percentage_rent = '';
    public $tenant_broker_fee_structure = '';
    public $tenant_broker_percentage = '';
    public $tenant_broker_commission_percentage = '';
    public $tenant_broker_flat_fee = '';
    public $tenant_broker_other = '';
    public $tenant_broker_first_month_rent = '';
    public $tenant_broker_gross_lease = '';
    public $maintenance_by = '';
    public $restrictions = '';
    public $leasing_space = '';
    public $interested_purchase_fee_type = '';
    public $interested_lease_option = '';
    public $interested_lease_option_agreement = '';
    public $common_areas_access = '';
    public $emotional_support_animal = "";
    public $nominal = "";
    public $retained_deposits = "";
    public $outstanding_balance = "";
    public $lease_option_consideration = "";
    public $purchase_fee_flat_exercised = "";
    public $purchase_pice_commercial = "";
    public $interested_in_selling = "";
    public $landlord_broker_purchase_price = "";
    public $landlord_broker_percentage_price = "";
    public $landlord_broker_dollar_price = "";
    public $landlord_broker_flate_fee_type = "$";
    public $landlord_broker_flate_fee = "";
    public $landlord_broker_other = "";
    public $interested_in_selling_type = "";


    // New unified state
    public string $lease_type = 'percent';     // default as required
    public $lease_value = null;

    public string $purchase_type = 'percent';  // default as required
    public $purchase_value = null;

    public $seller_leasing_fee_type = "";
    public $seller_leasing_gross = "";
    public $seller_leasing_gross_month_rent = "";
    public $seller_leasing_gross_rental = "";
    public $seller_broker_leasing_fee = "";
    public $seller_leasing_each_rental = "";
    public $seller_leasing_gross_no_of_months = "";



    public $seller_leasing_gross_flat_combo = "";
    public $seller_leasing_gross_percentage_combo = "";
    public $seller_leasing_gross_flat_net_combo = "";
    public $seller_leasing_gross_percentage_net_combo = "";


    public $seller_leasing_gross_sales_tax_option_gross = "";
    public $seller_leasing_gross_ross_percentage_rent = "";
    public $seller_leasing_gross_sales_tax_flat_free_gross = "";
    public $seller_leasing_gross_sales_tax_first_month = "";
    public $seller_leasing_gross_percentage_no_of_months = "";
    public $seller_leasing_gross_purchase_fee_flat_amount = "";
    public $seller_leasing_gross_purchase_fee_other = "";
    public $seller_leasing_gross_other = "";


    public $interested_in_property_management = "";
    public $interested_in_property_management_fee = "";
    public $interested_in_property_management_fee_gross_lease = "";
    public $interested_in_property_management_fee_rental_periord = "";
    public $interested_in_property_management_fee_flate_free = "";
    public $interested_in_property_management_fee_other = "";

    // Tenant Leasing Terms — 23 new optional fields
    public $tenant_desired_lease_length = '';
    public $security_deposit_budget = '';
    public $move_in_funds_available = '';
    public $first_month_rent_available = '';
    public $last_month_rent_available = '';
    public $pet_information = '';
    public $number_of_occupants = '';
    public $parking_needed = '';
    public $utility_preference = '';
    public $maintenance_preference = '';
    public $renewal_option_requested = '';
    public $renewal_option_details = '';
    public $tenant_conditions = '';
    public $additional_tenant_lease_terms = '';
    public $commercial_lease_type_preference = '';
    public $cam_nnn_preference = '';
    public $rent_escalation_preference = '';
    public $buildout_tenant_improvement_request = '';
    public $intended_business_use = '';
    public $signage_request = '';
    public $commercial_parking_access_needs = '';
    public $personal_guarantee_preference = '';
    public $commercial_approval_conditions = '';

    // Buyer Purchasing Terms — 18 new optional fields (Task #155)
    public $earnest_money_amount = '';
    public $earnest_money_timing = '';
    public $inspection_period_days = '';
    public $inspection_contingency_buyer = '';
    public $appraisal_contingency_buyer = '';
    public $financing_contingency_buyer = '';
    public $financing_contingency_days_buyer = '';
    public $seller_contribution = '';
    public $seller_contribution_details = '';
    public $possession_preference = '';
    public $possession_details = '';
    public $home_warranty_requested = '';
    public $home_warranty_details = '';
    public $as_is_purchase = '';
    public $property_inclusions = '';
    public $property_exclusions = '';
    public $closing_cost_responsibility = '';
    public $additional_purchase_terms = '';

    // Landlord Lease Terms — 22 new optional fields (Task #157)
    public $lease_available_date = '';
    public $security_deposit_required = '';
    public $first_month_rent_required = '';
    public $last_month_rent_required = '';
    public $total_move_in_funds_required = '';
    public $pet_policy = '';
    public $pet_deposit_fee_rent = '';
    public $number_of_occupants_allowed = '';
    public $parking_terms = '';
    public $utility_responsibility = '';
    public $ll_maintenance_responsibility = '';
    public $renewal_option_offered = '';
    public $landlord_approval_conditions = '';
    public $additional_landlord_lease_terms = '';
    public $commercial_lease_type = '';
    public $cam_nnn_additional_rent_charges = '';
    public $rent_escalation_terms = '';
    public $tenant_improvement_buildout_terms = '';
    public $permitted_use_restrictions = '';
    public $signage_rights = '';
    public $commercial_parking_terms = '';
    public $personal_guarantee_requirement = '';

    // Personal information
    public $first_name = '';
    public $last_name = '';
    public $phone_number = '';
    public $email = '';
    public $agent_brokerage = '';
    public $agent_license_number = '';
    public $agent_nar_member_id = '';
    public $current_status = '';
    public $video_link = '';



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
    public $photoDeleted = false;
    public $videoDeleted = false;

    // Tab management
    public $activeTab = 0;

    // Location suggestions
    // Location suggestions
    public $cities = [];
    public $newCity = '';
    public $counties = [];
    public $newCounty = '';
    public $citySuggestions = [];
    public $countySuggestions = [];
    public $stateSuggestions = [];
    public $zipCodes = [];       // Array of all selected zip codes
    public $zip_code = '';       // Current zip code input
    public $zipCodeSuggestions = [];
    public $highlightedZipCodeIndex = -1;
    // Highlight indices for keyboard navigation
    public $highlightedCityIndex = -1;
    public $highlightedCountyIndex = -1;
    public $highlightedStateIndex = -1;
    public $highlightedAddressIndex = -1;
    public $countyFieldVisible = true;
    public $stateFieldVisible = false;
    public  $cityFieldVisible = false;
    public $zipCodeFieldVisible = false;
    public $embedUrl;
    // Form fields
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
    // Add these methods to your Livewire component
    public $showEnhancements = false;
    public $showCustomEnhancement = false;
    public $other_lease_term = null;
    public $unit_type_configurations = [];

    protected $rules = [
        'auction_type' => 'required',
        'appliances' => 'array',
        'other_appliances' => 'required_if:appliances,Other',

    ];



    public $openHouseCount = null;
    public $showOpenHouseInput = false;
    public $showOtherAppliances = false;
    public $showOtherAppliancesSeller = false;

    public $is_other_owner_pays_visible = false;
    public $is_other_tenant_pay_visible = false;
    public $is_rent_include_visible = false;
    public $is_update_lease_term_option_visible = false;
    public $assets_visible = false;

    // Computed Properties
    public function getIsOtherVisibleProperty()
    {
        return is_array($this->view_preference) && in_array('Other', $this->view_preference);
    }

    public function getIsOtherNonNegotiableVisibleProperty()
    {
        return is_array($this->non_negotiable_amenities) && in_array('Other', $this->non_negotiable_amenities);
    }

    public function getIsPropertyItemsOtherVisibleProperty()
    {
        return is_array($this->property_items) && in_array('Other', $this->property_items);
    }

    protected $listeners = [
        'refreshComponent' => '$refresh',
        'updateAppliances',
        'conditionUpdated',
        'updatePreference' => 'updatePreference',
        'updateOtherPreferences' => 'updateOtherPreferences',
        'updateGarageParkingSpaces' => 'updateGarageParkingSpaces',
        'updateOwnerPays' => 'updateOwnerPays',
        'updateTenantPays' => 'updateTenantPays',
        'updateRentIncludes' => 'updateRentIncludes',
        'updateLeaseTermOptions' => 'updateLeaseTermOptions',
        'assetsOption' => 'assetsOption',
        'updateModel' => 'updateModel',
        'setActiveTab' => 'setActiveTab',
    ];

    /**
     * Safe handler for updateModel event from JavaScript
     * Prevents ArgumentCountError by validating property name before updating
     */
    public function updateModel($propertyName, $value)
    {
        // Guard against null or empty property names
        if (empty($propertyName) || !is_string($propertyName)) {
            return;
        }

        // Only update if the property exists on this component
        if (property_exists($this, $propertyName)) {
            $this->{$propertyName} = $value;
        }
    }




    private function resetResidentialFeeFields()
    {
        $this->reset([
            'purchase_fee_flat',
            'purchase_fee_rental_period',
            'purchase_fee_percentage_combo',
            'purchase_fee_flat_combo',
            'purchase_fee_other',
            'purchase_fee_percentage'
        ]);
    }

    // Helper method to reset commercial fee fields
    private function resetCommercialFeeFields()
    {
        $this->reset([
            'purchase_fee_net_aggregate',
            'purchase_fee_gross_rent',
            'sales_tax_option_gross',
            'purchase_fee_monthly_percentage',
            'purchase_fee_months',
            'sales_tax_option_monthly',
            'purchase_fee_flat_commercial',
            'sales_tax_option_flat',
            'purchase_fee_other_commercial'
        ]);
    }
    // Helper method to reset commercial fee fields
    private function resetTenantBrokerLeaseFee()
    {
        $this->reset([
            'lease_fee_flat',
            'lease_fee_percentage',
            'lease_fee_percentage_monthly_rent',
            'lease_fee_flat_combo',
            'lease_fee_percentage_combo',
            'lease_fee_percentage_net',
            'lease_fee_flat_combo_net',
            'lease_fee_percentage_combo_net',
            'lease_fee_other',
        ]);
    }

    // Helper method to reset selling fields
    private function resetSellingFields()
    {
        $this->reset([
            'landlord_broker_purchase_price',
            'landlord_broker_percentage_price',
            'landlord_broker_dollar_price',
            'landlord_broker_flate_fee',
            'landlord_broker_other'
        ]);
    }

    // Helper method to reset renewal fee fields
    private function resetRenewalFeeFields()
    {
        $this->reset([
            'renewal_fee_percentage',
            'renewal_fee_lease_value',
            'renewal_fee_first_month',
            'renewal_fee_flat_free',
            'renewal_fee_custom',
            'renewal_fee_sales_tax_lease_value',
            'renewal_fee_no_of_months',
            'renewal_fee_sales_tax_first_month',
            'renewal_fee_sales_tax_flat_fee'
        ]);
    }

    // Helper method to reset tenant broker fields
    private function resetTenantBrokerFields()
    {
        $this->reset([
            'tenant_broker_percentage',
            'tenant_broker_gross_lease',
            'tenant_broker_first_month_rent',
            'tenant_broker_flat_fee',
            'tenant_broker_other'
        ]);
    }

    // Helper method to reset property management fields
    private function resetPropertyManagementFields()
    {
        $this->reset([
            'interested_in_property_management_fee_gross_lease',
            'interested_in_property_management_fee_rental_periord',
            'interested_in_property_management_fee_flate_free',
            'interested_in_property_management_fee_other'
        ]);
    }

    // Handle purchase fee type changes
    public function updatedPurchaseFeeType($value)
    {
        if ($this->isLoadingData) return;
        if ($this->property_type === 'Residential Property') {
            $this->resetResidentialFeeFields();
        } elseif ($this->property_type === 'Commercial Property') {
            $this->resetCommercialFeeFields();
        }
    }

    // Handle property type changes - clear Residential-only fields when switching to Commercial
    public function updatedPropertyType($value)
    {
        if ($this->isLoadingData) return;

        $petAllowedTypes = ['Residential', 'Income'];
        if (!in_array($value, $petAllowedTypes)) {
            $this->pets = '';
            $this->number_of_pets = '';
            $this->type_of_pets = '';
            $this->breed_of_pets = '';
            $this->weight_of_pets = '';
            $this->service_animal = '';
            $this->support_animal = '';
            $this->emotional_support_animal = '';
            $this->breed_restrictions = '';
        }

        if ($value === 'Commercial Property') {
            $this->pool_needed = '';
            $this->pool_type = [];
            $this->carport_needed = '';
            $this->carport_spaces = '';
            $this->removeAduFromLeasingSpaceSelection();
        }

        if ($value === 'Vacant Land') {
            $this->appliances = [];
            $this->other_appliances = '';
            $this->showOtherAppliances = false;
            $this->pool_needed = '';
            $this->pool_type = [];
        }

        if ($value !== 'Business') {
            $this->business_type_selected = '';
            $this->other_business_type = '';
        }
    }

    /**
     * Remove ADU option from leasing_spaces_tenant when switching to Commercial
     * ADU (Accessory Unit / Guest Suite) is only valid for Residential properties
     */
    private function removeAduFromLeasingSpaceSelection(): void
    {
        $aduLabel = 'Accessory Unit / Guest Suite (ADU)';

        if (is_array($this->leasing_spaces_tenant ?? null)) {
            $this->leasing_spaces_tenant = array_values(array_filter(
                $this->leasing_spaces_tenant,
                fn($v) => $v !== $aduLabel
            ));
        }
    }

    // Handle interested in selling type changes
    public function updatedInterestedInSellingType($value)
    {
        if ($this->isLoadingData) return;
        $this->resetSellingFields();
    }

    // Handle renewal fee type changes
    public function updatedRenewalFeeType($value)
    {
        if ($this->isLoadingData) return;
        $this->resetRenewalFeeFields();
    }
    public function updatedLeaseFeeType($value)
    {
        if ($this->isLoadingData) return;
        $this->resetValidation();
        $this->resetErrorBag();
        $this->resetTenantBrokerLeaseFee();
    }


    // Handle tenant broker fee structure changes
    public function updatedTenantBrokerFeeStructure($value)
    {
        if ($this->isLoadingData) return;
        $this->resetValidation();
        $this->resetErrorBag();
        $this->resetTenantBrokerFields();
    }

    // Handle property management fee changes
    public function updatedInterestedInPropertyManagementFee($value)
    {
        if ($this->isLoadingData) return;
        $this->resetValidation();
        $this->resetErrorBag();
        $this->resetPropertyManagementFields();
    }

    // Handle lease option agreement changes
    public function updatedInterestedLeaseOptionAgreement($value)
    {
        if ($this->isLoadingData) return;
        $this->resetValidation();
        $this->resetErrorBag();

        if ($value !== 'Yes') {
            $this->reset([
                'lease_type',
                'lease_value',
                'purchase_type',
                'purchase_value'
            ]);
        }
    }

    // Handle interested in selling changes
    public function updatedInterestedInSelling($value)
    {
        if ($this->isLoadingData) return;
        $this->resetValidation();
        $this->resetErrorBag();

        if ($value !== 'Yes') {
            $this->reset([
                'interested_in_selling_type',
                'landlord_broker_purchase_price',
                'landlord_broker_percentage_price',
                'landlord_broker_dollar_price',
                'landlord_broker_flate_fee',
                'landlord_broker_other'
            ]);
        }
    }

    // Handle broker fee timing changes
    public function updatedBrokerFeeTiming($value)
    {
        if ($this->isLoadingData) return;
        // Reset validation and error bags first to prevent rendering issues
        $this->resetValidation();
        $this->resetErrorBag();

        // Reset dependent fields
        $this->reset([
            'broker_fee_days_from_rent',
            'broker_fee_days_after_lease',
            'broker_fee_days_after_rent',
            'broker_fee_timing_other',
            'split_payment_due',
            'split_payment_due_other',
            'broker_fee_days_after_due_event'
        ]);
    }

    // Handle early termination fee option changes
    public function updatedEarlyTerminationFeeOption($value)
    {
        if ($this->isLoadingData) return;
        $this->resetValidation();
        $this->resetErrorBag();

        if ($value !== 'yes') {
            $this->reset(['early_termination_fee_amount']);
        }
    }

    // Handle agency agreement timeframe changes
    public function updatedAgencyAgreementTimeframe($value)
    {
        if ($this->isLoadingData) return;
        $this->resetValidation();
        $this->resetErrorBag();

        if ($value !== 'Other') {
            $this->reset(['agency_agreement_custom']);
        }
    }

    // Handle interested in property management changes
    public function updatedInterestedInPropertyManagement($value)
    {
        if ($this->isLoadingData) return;
        $this->resetValidation();
        $this->resetErrorBag();

        if ($value !== 'yes') {
            $this->reset([
                'interested_in_property_management_fee',
                'interested_in_property_management_fee_gross_lease',
                'interested_in_property_management_fee_rental_periord',
                'interested_in_property_management_fee_flate_free',
                'interested_in_property_management_fee_other'
            ]);
        }
    }


    // In your Livewire component
    public function refreshTooltips()
    {
        $this->emit('refreshTooltips'); // Emit the event to re-initialize tooltips
    }

    // Ensure lease_type never becomes empty during transitions
    public function updatedLeaseType($value)
    {
        if ($this->isLoadingData) return;
        $this->resetValidation();
        $this->resetErrorBag();
        
        if (empty($value)) {
            $this->lease_type = 'percent';
        }
        $this->lease_value = ''; // clear when type changes
    }

    // Ensure purchase_type never becomes empty during transitions
    public function updatedPurchaseType($value)
    {
        if ($this->isLoadingData) return;
        $this->resetValidation();
        $this->resetErrorBag();
        
        if (empty($value)) {
            $this->purchase_type = 'percent';
        }
        $this->purchase_value = ''; // clear when type changes
    }

    public function setType(string $which, string $type): void
    {
        if ($which === 'lease') {
            $this->lease_type = $type ?: 'percent';
            $this->lease_value = ''; // clear lease input when switching type
        } elseif ($which === 'purchase') {
            $this->purchase_type = $type ?: 'percent';
            $this->purchase_value = ''; // clear purchase input when switching type
        }

        if ($which === 'purchase_fee_flat') {
            $this->purchase_fee_flat_type = $type;
            $this->purchase_fee_flat = ''; // clear lease input when switching type
        }
        if ($which === 'lease_fee_flat') {
            $this->lease_fee_flat_type = $type;
            $this->lease_fee_flat = ''; // clear lease input when switching type
        }
        if ($which === 'gap_payment_type') {
            $this->gap_payment_type = $type;
            $this->gap_payment_amount = ''; // clear lease input when switching type
        }
        if ($which === 'down_payment_type') {
            $this->down_payment_type = $type;
            $this->down_payment_amount = ''; // clear lease input when switching type
        }
        if ($which === 'seller_financing_type') {
            $this->seller_financing_type = $type;
            $this->seller_financing_amount = ''; // clear lease input when switching type
        }
    }



    // public function updatedLeaseAmountFrequency($value)
    // {
    //     // Convert the selected value (string) into an array if it's not already an array
    //     if (is_string($value)) {
    //         $this->lease_amount_frequency = [$value]; // Convert to array
    //     }
    // }
    public function assetsOption(array $values)
    {
        // update the main array
        $this->assets = $values;

        // toggle the "Other" field
        $this->assets_visible = in_array('Other', $values);

        // clear if user un-checks "Other"
        if (! $this->assets_visible) {
            $this->assets_other = null;
        }
    }

    public function updateLeaseTermOptions($selectedValues)
    {
        if ($this->isLoadingData) return;
        if (in_array('Other', $selectedValues)) {
            $this->is_update_lease_term_option_visible = true;
        } else {
            $this->is_update_lease_term_option_visible = false;
            $this->other_lease_term = '';
        }
    }


    public function updatePreference($selectedValues)
    {
        if ($this->isLoadingData) return;
        $this->view_preference = $selectedValues;

        if (!in_array('Other', $selectedValues)) {
            $this->other_preferences = '';
        }
    }

    public function updateOwnerPays($selectedValues)
    {
        if ($this->isLoadingData) return;
        if (in_array('Other', $selectedValues)) {
            $this->is_other_owner_pays_visible = true;
        } else {
            $this->is_other_owner_pays_visible = false;
            $this->other_owner_pays = '';
        }
    }


    public function updateTenantPays($selectedValues)
    {
        if ($this->isLoadingData) return;
        if (in_array('Other', $selectedValues)) {
            $this->is_other_tenant_pay_visible = true;
        } else {
            $this->is_other_tenant_pay_visible = false;
        }
    }

    public function updateRentIncludes($selectedValues)
    {
        if ($this->isLoadingData) return;
        if (in_array('Other', $selectedValues)) {
            $this->is_rent_include_visible = true;
        } else {
            $this->is_rent_include_visible = false;
            $this->other_rent_include = '';
        }
    }

    public function updateOtherPreferences($value)
    {
        $this->other_preferences = $value;
    }
    public function conditionUpdated($value)
    {
        $this->condition_prop_buyer = $value['value'];
        $this->condition_prop_buyer_json = json_encode($value['value'] ?? []);
    }

    public function updatedConditionPropBuyer($value)
    {
        if ($this->isLoadingData) return;
    }

    public function updatedConditionPropBuyerJson($value)
    {
        if ($this->isLoadingData) return;
        $decoded = json_decode($value, true);
        if (is_array($decoded) && !empty($decoded)) {
            $this->condition_prop_buyer = array_values(array_unique(array_map('trim', $decoded)));
        }
    }


    public function updateAppliances($selectedValues)
    {
        if ($this->isLoadingData) return;
        $this->appliances = $selectedValues ?? [];
        $this->showOtherAppliances = in_array('Other', $this->appliances);
        $this->validateOnly('appliances');
    }


    public function updateGarageParkingSpaces($selectedValues)
    {
        if ($this->isLoadingData) return;
        $this->garage_parking_spaces_option = $selectedValues;

        if (!in_array('Other', $selectedValues)) {
            $this->other_parking_space_wrapper = null;
        }
    }
    public function updated($propertyName)
    {
        if ($this->isLoadingData) return;
        if (empty($propertyName)) {
            return;
        }
        $this->validateOnly($propertyName);
    }


    public function updatedServices()
    {
        if ($this->isLoadingData) return;
        $hasEnhancements = in_array('Provide digital photo enhancements', $this->services)
            || in_array('Provide digital enhancements to media assets', $this->services);
        if ($hasEnhancements) {
            $this->showEnhancements = true;
        } else {
            $this->showEnhancements = false;
            $this->showCustomEnhancement = false;
        }


        $this->showOpenHouseInput = in_array('Host open houses', $this->services);

        // Reset count if deselected
        if (!$this->showOpenHouseInput) {
            $this->openHouseCount = null;
        }
        $this->showOpenHouseInput = in_array('Host broker tours', $this->services);

        // Reset count if deselected
        if (!$this->showOpenHouseInput) {
            $this->openHouseCount = null;
        }
        $this->showOpenHouseInput = in_array('Host site visit event (administrative coordination only)', $this->services);

        // Reset count if deselected
        if (!$this->showOpenHouseInput) {
            $this->openHouseCount = null;
        }
    }

    public function updatedPhotoEnhancements()
    {
        if ($this->isLoadingData) return;
        $this->showCustomEnhancement = in_array('Other', $this->photo_enhancements);
    }
    public function notifyJs($type, $checked)
    {
        $this->dispatchBrowserEvent('visibility-update', [
            'type' => $type,
            'visible' => $checked
        ]);
    }


    public function toggleEnhancementOptions()
    {
        // This forces Livewire to update the view
        $this->emitSelf('updatedServices');
    }

    public function toggleCustomEnhancement()
    {
        // This forces Livewire to update the view
        $this->emitSelf('updatedServices');
    }
    public function setAssignmentFeeType($type)
    {
        $this->assignment_fee_type = $type;
        $this->assignment_fee_amount = ''; // Clear amount when changing type
    }

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
    protected function initializeFeeStructure()
    {
        $baseFees = [
            // Common fields
            'social_media' => null,
            'paid_ads' => null,
            'email_marketing' => null,
            'local_mailers' => null,
            'hyperlocal_ads' => null,
            'schedule_showings' => null,
            'attend_showings' => null,
            'virtual_tours' => null,
            'key_handover' => null,
        ];

        switch ($this->user_type) {
            case 'tenant':
                $this->fees = array_merge($baseFees, [
                    'list_criteria' => null,
                    'email_alerts' => null,
                    'off_market' => null,
                    'filter_listings' => null,
                    'prepare_application' => null,
                    'submit_documents' => null,
                    'follow_up' => null,
                    'state_lease' => null,
                    'lease_overview' => null,
                    'lease_disclosures' => null,
                    'lease_signing' => null,
                    'utility_setup' => null,
                    'rental_analysis' => null,
                    'rental_laws' => null,
                    'lease_options' => null,
                    'short_term_housing' => null,
                    'general_guidance' => null,
                    'move_in_costs' => null,
                ]);
                break;

            case 'seller':
                $this->fees = array_merge($baseFees, [
                    'list_bidyouroffer' => null,
                    'list_mls' => null,
                    'syndicate_listing' => null,
                    'list_crexi' => null,
                    'list_loopnet' => null,
                    'cma' => null,
                    'staging_consultation' => null,
                    'coordinate_staging' => null,
                    'curb_appeal_consultation' => null,
                    'coordinate_curb_appeal' => null,
                    'professional_photography' => null,
                    'drone_photos' => null,
                    'video_walkthrough' => null,
                    'virtual_tour' => null,
                    'virtual_staging' => null,
                    'digital_enhancement' => null,
                    'lockbox' => null,
                    'yard_sign' => null,
                    'organize_offers' => null,
                    'complete_disclosures' => null,
                    'follow_up_offers' => null,
                    'overview_agreement' => null,
                    'coordinate_counteroffer' => null,
                    'esignature_setup' => null,
                    'track_deadlines' => null,
                    'contract_to_close' => null,
                    'vendor_referrals' => null,
                    'escrow_coordination' => null,
                    'final_walkthrough' => null,
                    'closing_appointment' => null,
                    'move_out_checklist' => null,
                    'selling_process' => null,
                    'property_improvements' => null,
                    'general_questions' => null,
                    'closing_costs' => null,
                ]);
                break;

            case 'buyer':
                $this->fees = array_merge($baseFees, [
                    'list_criteria' => null,
                    'email_alerts' => null,
                    'off_market' => null,
                    'filter_listings' => null,
                    'prepare_offer' => null,
                    'complete_disclosures' => null,
                    'follow_up_offer' => null,
                    'overview_agreement' => null,
                    'coordinate_counteroffer' => null,
                    'esignature_setup' => null,
                    'track_deadlines' => null,
                    'contract_to_close' => null,
                    'vendor_referrals' => null,
                    'escrow_coordination' => null,
                    'final_walkthrough' => null,
                    'closing_appointment' => null,
                    'move_in_checklist' => null,
                    'cma' => null,
                    'homebuying_process' => null,
                    'buyer_preparation' => null,
                    'general_questions' => null,
                    'closing_costs' => null,
                ]);
                break;

            case 'landlord':
                $this->fees = array_merge($baseFees, [
                    'list_bidyouroffer' => null,
                    'list_mls' => null,
                    'syndicate_listing' => null,
                    'list_crexi' => null,
                    'list_loopnet' => null,
                    'rma' => null,
                    'curb_appeal_consultation' => null,
                    'coordinate_curb_appeal' => null,
                    'professional_photography' => null,
                    'drone_photos' => null,
                    'video_walkthrough' => null,
                    'virtual_tour' => null,
                    'virtual_staging' => null,
                    'digital_enhancement' => null,
                    'lockbox' => null,
                    'yard_sign' => null,
                    'standard_application' => null,
                    'submit_documents' => null,
                    'follow_up_application' => null,
                    'state_lease' => null,
                    'lease_overview' => null,
                    'lease_disclosures' => null,
                    'lease_signing' => null,
                    'utility_setup' => null,
                    'rental_laws' => null,
                    'property_improvements' => null,
                    'lease_options' => null,
                    'general_questions' => null,
                    'move_in_costs' => null,
                ]);
                break;
        }
    }
    public function addUnitType()
    {
        $this->unit_type_configurations[] = [
            'unit_type' => '',
            'beds_unit' => '',
            'baths_unit' => '',
            'garage_spaces' => '',
            'carport_spaces' => '',
            'other_spaces' => '',
            'number_of_units' => '',
            'number_occupied' => '',
            'expected_rent' => '',
            'unit_type_description' => ''
        ];
    }

    public function removeUnitType($index)
    {
        if (count($this->unit_type_configurations) > 1) {
            unset($this->unit_type_configurations[$index]);
            $this->unit_type_configurations = array_values($this->unit_type_configurations);
        }
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

    // Methods
    public function mount($user_type = null, $listingId = null)
    {
        $this->unit_type_configurations = [
            [
                'unit_type' => '',
                'beds_unit' => '',
                'baths_unit' => '',
                'garage_spaces' => '',
                'carport_spaces' => '',
                'other_spaces' => '',
                'number_of_units' => '',
                'number_occupied' => '',
                'expected_rent' => '',
                'unit_type_description' => ''
            ]
        ];

        $this->listing_date = now()->format('Y-m-d');

        // Set user_type from route parameter, or default to 'tenant'
        $this->user_type = ($user_type !== null) ? $user_type : 'tenant';

        $this->initializeFeeStructure();
        $this->addService();

        // Check for existing drafts based on user_type
        $draftModelClass = match ($this->user_type) {
            'tenant'   => HireTenantAgentAuction::class,
            'landlord' => HireLandLordAgentAuction::class,
            'buyer'    => HireBuyerAgentAuction::class,
            'seller'   => HireSellerAgentAuction::class,
            default    => HireTenantAgentAuction::class,
        };
        $this->hasDrafts = $draftModelClass::where('user_id', Auth::id())
            ->where('is_draft', true)
            ->exists();

        if ($listingId) {
            $draftRecord = $draftModelClass::where('id', $listingId)
                ->where('user_id', Auth::id())
                ->first();

            if (!$draftRecord) {
                session()->flash('error', 'Draft not found. It may have been deleted or belongs to another account.');
                return redirect()->route('hire.agent.auction', ['user_type' => $this->user_type]);
            }

            $this->loadDraft($listingId);

            // Enforce Residential-only field cleanup for Commercial properties after draft load
            if ($this->property_type === 'Commercial Property') {
                $this->removeAduFromLeasingSpaceSelection();
                $this->pool_needed = '';
                $this->pool_type = [];
            }
        }
    }
    public function startNew()
    {
        // Reset all properties to their initial state
        $this->resetExcept(['hasDrafts', 'service_type', 'user_type']);

        // Re-initialize necessary properties
        $this->initializeFeeStructure();
        $this->addService();

        // Clear the listing ID
        $this->listingId = null;
        $this->isDraft = false;
    }
    public function getDrafts()
    {
        // Return drafts from the correct model based on user_type
        $draftModelClass = match ($this->user_type) {
            'tenant'   => HireTenantAgentAuction::class,
            'landlord' => HireLandLordAgentAuction::class,
            'buyer'    => HireBuyerAgentAuction::class,
            'seller'   => HireSellerAgentAuction::class,
            default    => HireTenantAgentAuction::class,
        };
        return $draftModelClass::where('user_id', Auth::id())
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

    // public function removeService($index)
    // {
    //     unset($this->custom_services[$index]);
    //     $this->custom_services = array_values($this->custom_services);
    //     $this->calculateTotals();
    // }

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
        if ($this->isLoadingData) return;
        if ($value === 'Represented') {
            $this->dispatchBrowserEvent('show-representation-notice');
        } else {
            $this->dispatchBrowserEvent('hide-representation-notice');
        }
    }

    public function updatedAuctionType($value)
    {
        if ($this->isLoadingData) return;
        if ($value === 'Auction') {
            $this->dispatchBrowserEvent('show-auction-time');
        } else {
            $this->dispatchBrowserEvent('hide-auction-time');
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

        // Cities are OPTIONAL for buyer and tenant, required for seller/landlord
        if (!in_array($this->user_type, ['buyer', 'tenant'])) {
            $this->validate(['cities' => 'required|array|min:1']);
        }
    }

    public function removeCity($index)
    {
        unset($this->cities[$index]);
        $this->cities = array_values($this->cities);
        
        // Cities are OPTIONAL for buyer and tenant, required for seller/landlord
        if (!in_array($this->user_type, ['buyer', 'tenant'])) {
            $this->validate(['cities' => 'required|array|min:1']);
        }
    }

    public function addCounty()
    {
        $this->validate(['newCounty' => 'required|string|max:255']);
        $county = trim($this->newCounty);

        if (!empty($county) && !in_array($county, $this->counties)) {
            $this->counties[] = $county;
            $this->newCounty = '';
        }

        // Counties are REQUIRED for all user types
        $this->validate(['counties' => 'required|array|min:1']);
    }

    public function addZipCode()
    {
        $zip = trim($this->zip_code);
        if ($zip && !in_array($zip, $this->zipCodes)) {
            $this->zipCodes[] = $zip;
            $this->zip_code = '';
        }
    }

    public function removeZipCode($index)
    {
        unset($this->zipCodes[$index]);
        $this->zipCodes = array_values($this->zipCodes);
        $this->validate(['zipCodes' => 'required|array|min:1']);
    }


    public function removeCounty($index)
    {
        unset($this->counties[$index]);
        $this->counties = array_values($this->counties);
        
        // Counties are REQUIRED for all user types
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
    
    public function updatedPropertyCity($value)
    {
        if (!in_array($this->user_type, ['landlord', 'seller'])) {
            return;
        }
        
        if (strlen($value) > 2) {
            $this->propertyCitySuggestions = $this->getPlaceSuggestions($value, 'city');
        } else {
            $this->propertyCitySuggestions = [];
        }
    }
    
    public function searchPropertyCity($value)
    {
        if (!in_array($this->user_type, ['landlord', 'seller'])) {
            return;
        }
        
        $this->property_city = $value;
        if (strlen($value) > 2) {
            $this->propertyCitySuggestions = $this->getPlaceSuggestions($value, 'city');
        } else {
            $this->propertyCitySuggestions = [];
        }
    }
    
    public function selectPropertyCitySuggestion($suggestion = null)
    {
        if (!in_array($this->user_type, ['landlord', 'seller'])) {
            return;
        }
        
        $suggestion = $suggestion ?? $this->propertyCitySuggestions[$this->highlightedPropertyCityIndex] ?? $this->property_city;
        $this->property_city = $suggestion;
        $this->propertyCitySuggestions = [];
        $this->highlightedPropertyCityIndex = -1;
        
        $this->autoPopulateFromPropertyCity($suggestion);
    }
    
    protected function autoPopulateFromPropertyCity($cityString)
    {
        if (empty($cityString)) {
            return;
        }
        
        $parts = explode(',', $cityString);
        $cityName = trim($parts[0] ?? '');
        $stateAbbrev = trim($parts[1] ?? '');
        
        if (!empty($stateAbbrev) && empty($this->property_state)) {
            $state = \App\Models\UsState::where('abbreviation', 'ILIKE', $stateAbbrev)->first();
            if ($state) {
                $this->property_state = $state->name;
            }
        }
        
        if (!empty($cityName) && !empty($stateAbbrev)) {
            $state = \App\Models\UsState::where('abbreviation', 'ILIKE', $stateAbbrev)->first();
            if ($state) {
                $city = \App\Models\UsCity::where('name', 'ILIKE', $cityName)
                    ->where('state_id', $state->id)
                    ->first();
                
                if ($city && $city->county_id) {
                    $county = \App\Models\UsCounty::find($city->county_id);
                    if ($county) {
                        $countyName = $county->name;
                        if (!str_contains(strtolower($countyName), 'county')) {
                            $countyName .= ' County';
                        }
                        $this->property_county = $countyName . ', ' . $stateAbbrev;
                    }
                }
            }
        }
    }
    
    public function incrementPropertyCityHighlight()
    {
        if (count($this->propertyCitySuggestions) > 0) {
            $this->highlightedPropertyCityIndex = min($this->highlightedPropertyCityIndex + 1, count($this->propertyCitySuggestions) - 1);
        }
    }
    
    public function decrementPropertyCityHighlight()
    {
        if (count($this->propertyCitySuggestions) > 0) {
            $this->highlightedPropertyCityIndex = max($this->highlightedPropertyCityIndex - 1, 0);
        }
    }


    public function updatedAddress($value)
    {
        if (strlen($value) > 1) {
            $this->addressSuggestions = $this->getPlaceSuggestions($value, 'address');
        } else {
            $this->addressSuggestions = [];
        }
    }

    public function updatedZipCode($value)
    {
        if (strlen($value) > 1) {
            $this->zipCodeSuggestions = $this->getPlaceSuggestions($value, 'postal_code');
        } else {
            $this->zipCodeSuggestions = [];
        }
    }

    private function getAddressDetailsFromApi($address)
    {
        $client = new \GuzzleHttp\Client();

        $query = [
            'address' => $address,
            'key' => env('GOOGLE_PLACES_API_KEY'),
        ];

        try {
            $response = $client->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'query' => $query
            ]);

            // Check if the response is successful by checking the status code
            if ($response->getStatusCode() === 200) {
                $data = json_decode($response->getBody(), true);

                // Ensure there are results
                if (isset($data['results'][0])) {
                    $result = $data['results'][0];
                    $addressComponents = collect($result['address_components']);

                    // Extract the relevant components using filter for multiple types
                    $city = $addressComponents->filter(function ($component) {
                        return in_array('locality', $component['types']);
                    })->first()['long_name'] ?? null;

                    $state = $addressComponents->filter(function ($component) {
                        return in_array('administrative_area_level_1', $component['types']);
                    })->first()['long_name'] ?? null;

                    $zipCode = $addressComponents->filter(function ($component) {
                        return in_array('postal_code', $component['types']);
                    })->first()['long_name'] ?? null;

                    $county = $addressComponents->filter(function ($component) {
                        return in_array('administrative_area_level_2', $component['types']);
                    })->first()['long_name'] ?? null;

                    return compact('city', 'state', 'zipCode', 'county');
                }
            }
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            // Handle any errors that may occur during the request
            \Log::error('Geocode API error: ' . $e->getMessage());
        }

        return [];
    }




    // Updated method for county input
    public function updatedNewCounty($value)
    {
        if (strlen($value) > 1) {
            $this->countySuggestions = $this->getPlaceSuggestions($value, 'county');
        } else {
            $this->countySuggestions = [];
        }
    }

    public function selectCountySuggestion($suggestion = null)
    {
        if ($suggestion === null && $this->highlightedCountyIndex >= 0) {
            $suggestion = $this->countySuggestions[$this->highlightedCountyIndex];
        }

        if ($suggestion) {
            if (!in_array($suggestion, $this->counties)) {
                $this->counties[] = $suggestion;
            }
            $this->newCounty = '';
            $this->countySuggestions = [];
            $this->highlightedCountyIndex = -1;

            $this->autoPopulateStateFromCounty($suggestion);
        }
    }
    
    private function autoPopulateStateFromCounty($countyString)
    {
        if (empty($this->state)) {
            $stateAbbr = $this->extractStateFromLocationString($countyString);
            if ($stateAbbr) {
                $stateRecord = UsState::where('abbreviation', strtoupper($stateAbbr))->first();
                if ($stateRecord) {
                    $this->state = $stateRecord->name;
                }
            }
        }
    }

    // New method to extract state from county
    private function extractStateFromCounty($county)
    {
        // County format is usually "County Name, State, USA"
        // Extract the state part
        $parts = explode(',', $county);

        if (count($parts) >= 2) {
            $state = trim($parts[1]); // Get the state part and trim whitespace

            // Remove "USA" if present and any extra spaces
            $state = preg_replace('/\s*USA$/', '', $state);
            $state = trim($state);

            // Set the state
            $this->state = $state;
        }
    }

    // Alternative method using Google Places API for more accuracy
    private function extractStateFromCountyUsingAPI($county)
    {
        try {
            $client = new Client();

            $response = $client->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'query' => [
                    'address' => $county,
                    'key' => env('GOOGLE_PLACES_API_KEY')
                ]
            ]);

            $data = json_decode($response->getBody(), true);

            if (!empty($data['results'][0]['address_components'])) {
                $components = $data['results'][0]['address_components'];

                foreach ($components as $component) {
                    if (in_array('administrative_area_level_1', $component['types'])) {
                        $this->state = $component['long_name'];
                        break;
                    }
                }
            }
        } catch (\Exception $e) {
            // Fallback to string parsing if API fails
            $this->extractStateFromCounty($county);
        }
    }

    protected function getPlaceSuggestions($input, $type = null)
    {
        if ($type === 'state') {
            return $this->getStateSuggestionsFromDb($input);
        } elseif ($type === 'county') {
            return $this->getCountySuggestionsFromDb($input);
        } elseif ($type === 'city') {
            return $this->getCitySuggestionsFromDb($input);
        } elseif ($type === 'address' || $type === 'postal_code') {
            return $this->getPlaceSuggestionsFromApi($input, $type);
        } else {
            return $this->getCitySuggestionsFromDb($input);
        }
    }

    protected function getStateSuggestionsFromDb($input)
    {
        $states = UsState::where('name', 'ILIKE', '%' . $input . '%')
            ->orWhere('abbreviation', 'ILIKE', '%' . $input . '%')
            ->limit(10)
            ->get();

        return $states->map(function ($state) {
            return $state->name;
        })->toArray();
    }

    protected function getCountySuggestionsFromDb($input)
    {
        $counties = UsCounty::with('state')
            ->where('name', 'ILIKE', '%' . $input . '%')
            ->limit(10)
            ->get();

        return $counties->map(function ($county) {
            return $county->name . ', ' . ($county->state ? $county->state->abbreviation : '');
        })->toArray();
    }

    protected function getCitySuggestionsFromDb($input)
    {
        $citiesStartWith = UsCity::with('state')
            ->where('name', 'ILIKE', $input . '%')
            ->orderBy('name')
            ->limit(10)
            ->get();
        
        $citiesContain = UsCity::with('state')
            ->where('name', 'ILIKE', '%' . $input . '%')
            ->where('name', 'NOT ILIKE', $input . '%')
            ->orderBy('name')
            ->limit(10 - $citiesStartWith->count())
            ->get();
        
        $cities = $citiesStartWith->merge($citiesContain);

        return $cities->map(function ($city) {
            return $city->name . ', ' . ($city->state ? $city->state->abbreviation : '');
        })->toArray();
    }

    protected function getPlaceSuggestionsFromApi($input, $type = null)
    {
        $client = new Client();

        $query = [
            'input' => $input,
            'components' => 'country:us',
            'key' => env('GOOGLE_PLACES_API_KEY')
        ];

        if ($type === 'address') {
            $query['types'] = 'address';
        } elseif ($type === 'postal_code') {
            $query['types'] = 'postal_code';
        }

        try {
            $response = $client->get('https://maps.googleapis.com/maps/api/place/autocomplete/json', [
                'query' => $query
            ]);

            $predictions = json_decode($response->getBody(), true)['predictions'] ?? [];

            return array_map(function ($prediction) {
                return $prediction['description'];
            }, $predictions);
        } catch (\Exception $e) {
            Log::error('Google Places API error: ' . $e->getMessage());
            return [];
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

    public function selectStateSuggestion($suggestion = null)
    {
        if ($suggestion === null && $this->highlightedStateIndex >= 0) {
            $suggestion = $this->stateSuggestions[$this->highlightedStateIndex];
        }

        $this->state = $suggestion;
        $this->stateSuggestions = [];
        $this->highlightedStateIndex = -1;
    }


    public function incrementHighlight($type)
    {
        if ($type === 'County') {
            $max = count($this->countySuggestions) - 1;
            $this->highlightedCountyIndex = min($this->highlightedCountyIndex + 1, $max);
        } else {
            $max = count($this->stateSuggestions) - 1;
            $this->highlightedStateIndex = min($this->highlightedStateIndex + 1, $max);
        }
    }

    public function decrementHighlight($type)
    {
        if ($type === 'County') {
            $this->highlightedCountyIndex = max($this->highlightedCountyIndex - 1, -1);
        } else {
            $this->highlightedStateIndex = max($this->highlightedStateIndex - 1, -1);
        }
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
        
        $this->autoPopulateFromCity($suggestion);
    }
    
    private function autoPopulateFromCity($cityString)
    {
        $stateAbbr = $this->extractStateFromLocationString($cityString);
        
        if ($stateAbbr && empty($this->state)) {
            $stateRecord = UsState::where('abbreviation', strtoupper($stateAbbr))->first();
            if ($stateRecord) {
                $this->state = $stateRecord->name;
            }
        }
        
        $cityName = $this->extractNameFromLocationString($cityString);
        if ($cityName && $stateAbbr) {
            $cities = UsCity::with(['state', 'county.state'])
                ->where('name', $cityName)
                ->whereHas('state', function($q) use ($stateAbbr) {
                    $q->where('abbreviation', strtoupper($stateAbbr));
                })
                ->get();
            
            foreach ($cities as $city) {
                if ($city->county) {
                    $countyString = $city->county->name . ', ' . ($city->county->state ? $city->county->state->abbreviation : strtoupper($stateAbbr));
                    
                    if (!$this->countyExistsIgnoreCase($countyString)) {
                        $this->counties[] = $countyString;
                    }
                }
            }
        }
    }
    
    private function countyExistsIgnoreCase($countyString)
    {
        $normalized = strtolower(trim($countyString));
        foreach ($this->counties as $existing) {
            if (strtolower(trim($existing)) === $normalized) {
                return true;
            }
        }
        return false;
    }
    
    private function extractStateFromLocationString($locationString)
    {
        if (empty($locationString)) return null;
        
        $parts = explode(',', $locationString);
        if (count($parts) >= 2) {
            return trim(end($parts));
        }
        return null;
    }
    
    private function extractNameFromLocationString($locationString)
    {
        if (empty($locationString)) return null;
        
        $parts = explode(',', $locationString);
        if (count($parts) >= 1) {
            return trim($parts[0]);
        }
        return null;
    }




    public function selectAddressSuggestion($suggestion)
    {
        $this->address = $suggestion;
        $this->addressSuggestions = [];
        $this->highlightedAddressIndex = -1;

        $this->newCity = '';
        $this->citySuggestions = [];
        $this->newCounty = '';
        $this->countySuggestions = [];
        $this->state = '';
        $this->zip_code = '';
        $this->stateSuggestions = [];
        $this->zipCodeSuggestions = [];
        $this->zipCodes = [];
        $this->counties = [];
        $this->cityFieldVisible = false;
        $this->cities = [];
        // Fetch details from the geocoding API based on the selected address
        $addressDetails = $this->getAddressDetailsFromApi($suggestion);

        // Extract the city, state, ZIP code, and county
        $this->newCity = $addressDetails['city'] ?? '';
        $this->newCounty = $addressDetails['county'] ?? '';
        $this->state = $addressDetails['state'] ?? '';
        $this->zip_code = $addressDetails['zipCode'] ?? '';

        if (!empty($this->newCounty)) {
            $this->countySuggestions = [$this->newCounty];
            $this->selectCountySuggestion($this->newCounty);
            $this->countyFieldVisible = true;
        } else {
            $this->countyFieldVisible = false;
        }

        //auto-populate the city field if available
        if (!empty($this->newCity)) {
            $this->citySuggestions = [$this->newCity];

            $this->selectCitySuggestion($this->newCity);
            $this->cityFieldVisible = true;
        } else {
            $this->cityFieldVisible = false;
        }

        if (!empty($this->state)) {
            $this->stateSuggestions = [$this->state];
            $this->selectStateSuggestion($this->state);
            $this->stateFieldVisible = true;
        } else {
            $this->stateFieldVisible = false;
        }

        if (!empty($this->zip_code)) {
            $this->zipCodeSuggestions = [$this->zip_code];
            $this->selectZipCodeSuggestion($this->zip_code);
            $this->zipCodeFieldVisible = true;
        } else {
            $this->zipCodeFieldVisible = false;
        }
    }
    public function selectZipCodeSuggestion($suggestion = null)
    {
        $suggestion = $suggestion ?? $this->zipCodeSuggestions[$this->highlightedZipCodeIndex] ?? $this->zip_code;
        $this->zip_code = $suggestion;

        if (!in_array(trim($suggestion), $this->zipCodes)) {
            $this->addZipCode();
        }

        $this->zipCodeSuggestions = [];
        $this->highlightedZipCodeIndex = -1;
    }


    // Updated keyboard navigation methods

    // Method to update the active tab
    public function setActiveTab($index)
    {
        $this->activeTab = $index;
    }
    public function updatedOtherServicesEnabled($enabled): void
    {
        // If toggled on and no field exists, create the first one
        if ($enabled && empty($this->other_services)) { // Use empty() to check if array is empty
            $this->other_services[] = '';
        }

        // If toggled off, clear array (optional: keep if you prefer)
        if (! $enabled) {
            $this->other_services = [];
        }
    }

    public function addServiceField(): void
    {
        $this->other_services[] = ''; // Add a new empty field
    }

    public function removeService(int $index): void
    {
        unset($this->other_services[$index]);
        // reindex to 0..n so bindings become other_services.0, .1, .2 …
        $this->other_services = array_values($this->other_services);
    }




    public function updatedVideoLink($value)
    {
        // instantly preview when pasted or typed
        $this->embedUrl = $this->getEmbedUrl($value);
    }

    public function previewVideo()
    {
        $this->embedUrl = $this->getEmbedUrl($this->video_link);
    }

    public function getEmbedUrl($url)
    {
        // ✅ YouTube
        if (str_contains($url, 'youtube.com') || str_contains($url, 'youtu.be')) {
            preg_match('/(?:v=|youtu\.be\/)([A-Za-z0-9_-]{6,})/i', $url, $m);
            $videoId = $m[1] ?? null;
            return $videoId
                ? "https://www.youtube.com/embed/{$videoId}?autoplay=1&mute=1&playsinline=1"
                : null;
        }

        // ✅ Vimeo (handles: /123, /channels/.../123, /groups/.../videos/123, /album/.../video/123)
        if (str_contains($url, 'vimeo.com')) {
            // Grab the last numeric segment anywhere in the path
            preg_match('/vimeo\.com\/(?:.*\/)?(\d+)(?:[\/?#]|$)/i', $url, $m);
            $videoId = $m[1] ?? null;

            // Also handle player links like player.vimeo.com/video/123
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

    // Render the Blade view
    public function render()
    {
        return view('livewire.tenant-agent-auction')->extends('layouts.main')->section('content'); // Define the section
    }


    protected function buildDraftPayloadHash()
    {
        $fields = [
            'listing_title', 'service_type', 'auction_type', 'listing_status',
            'working_with_agent', 'listing_date', 'desired_agent_hire_date',
            'expiration_date', 'auction_time', 'agent_bid_visibility',
            'meeting_Preference', 'number_of_unit', 'state', 'property_type',
            'zip_code', 'address', 'property_city', 'property_state', 'property_zip',
            'nominal', 'expected_rent', 'pool_needed', 'parking', 'pets',
            'number_of_pets', 'pet_type', 'support_animal', 'emotional_support_animal',
            'has_breed_restrictions', 'breed_restrictions', 'minimum_annual_net_income',
            'minimum_cap_rate', 'unit_number', 'unit_buildings', 'assets_other',
            'screening_concerns', 'screening_concerns_explanation', 'preferance_details',
        ];
        $data = [];
        foreach ($fields as $f) {
            $val = $this->{$f} ?? '';
            if (is_string($val)) {
                $val = trim(str_replace(',', '', $val));
            }
            $data[$f] = $val;
        }
        $arrayFields = [
            'cities', 'counties', 'zipCodes', 'property_items', 'amenities',
            'non_negotiable_amenities', 'pool_type', 'assets', 'unit_type_configurations',
            'offered_financing', 'credit_scroe_rating',
        ];
        foreach ($arrayFields as $f) {
            $val = $this->{$f} ?? [];
            if (is_array($val)) {
                $sorted = $val;
                sort($sorted);
                $val = $sorted;
            }
            $data[$f] = $val;
        }
        ksort($data);
        return hash('sha256', json_encode($data));
    }

    public function saveDraft()
    {
        try {
            \Log::info('[SAVE_DRAFT] TenantAgentAuction.php saveDraft fired', [
                'component' => 'App\Http\Livewire\TenantAgentAuction',
                'method' => 'saveDraft',
                'user_type' => $this->user_type,
                'listing_id' => $this->listingId,
                'user_id' => auth()->id(),
                'timestamp' => now()->toDateTimeString(),
            ]);

            $this->isDraft = true;
            $this->validateOnlyFilledFields();

            $auctionClass = match ($this->user_type) {
                'tenant'   => HireTenantAgentAuction::class,
                'landlord' => HireLandLordAgentAuction::class,
                'buyer'    => HireBuyerAgentAuction::class,
                'seller'   => HireSellerAgentAuction::class,
                default    => null,
            };

            if (!$auctionClass) {
                throw new \Exception("Invalid user_type: {$this->user_type}");
            }

            $newPayloadHash = $this->buildDraftPayloadHash();

            $previousDraft = $this->listingId
                ? $auctionClass::find($this->listingId)
                : null;

            $previousVersion = 0;
            $parentDraftId = null;
            if ($previousDraft && $previousDraft->is_draft) {
                $oldHash = $previousDraft->info('draft_payload_hash') ?? '';
                if ($oldHash === $newPayloadHash) {
                    session()->flash('success', 'No changes detected — draft is already up to date.');
                    return redirect()->route('hire.agent.auction.draft', ['user_type' => $this->user_type, 'listingId' => $this->listingId]);
                }
                $previousVersion = (int) ($previousDraft->info('draft_version') ?? 1);
                $parentDraftId = $previousDraft->id;
            }

            $auction = new $auctionClass();

            $auction->user_id = Auth::id();
            $auction->title = $this->listing_title;
            $auction->is_draft = true;

            if ($this->user_type === 'buyer' || $this->user_type === 'seller') {
                $auction->address = $this->address ?? 'N/A';
                $auction->is_approved = false;
                $auction->is_sold = 'false';
                $auction->is_paid = 'false';
            }

            $auction->save();

            // Phase 6 — on append-only draft versioning, only set referral on
            // the very first draft (when there is no parent draft yet).
            if ($parentDraftId === null) {
                \App\Services\ReferralLinkService::persistListingReferral($auction);
            }

            $this->listingId = $auction->id;

            $this->saveAllMetadata($auction);

            $auction->saveMeta('draft_version', $previousVersion + 1);
            $auction->saveMeta('parent_draft_id', $parentDraftId);
            $auction->saveMeta('draft_payload_hash', $newPayloadHash);

            session()->flash('success', 'Draft saved successfully (Version ' . ($previousVersion + 1) . '). You can return later to complete your listing.');
            return redirect()->route('hire.agent.auction.draft', ['user_type' => $this->user_type, 'listingId' => $this->listingId]);
        } catch (\Exception $e) {
            session()->flash('error', 'Error saving draft: ' . $e->getMessage());
        }
    }

    public function loadDraft($listingId)
    {
        $this->isLoadingData = true;

        try {
        // All model classes to search
        $modelClasses = [
            'tenant'   => HireTenantAgentAuction::class,
            'landlord' => HireLandLordAgentAuction::class,
            'buyer'    => HireBuyerAgentAuction::class,
            'seller'   => HireSellerAgentAuction::class,
        ];

        $auction = null;
        $foundUserType = null;

        // If user_type is set, try that model first
        if ($this->user_type && isset($modelClasses[$this->user_type])) {
            $modelClass = $modelClasses[$this->user_type];
            $auction = $modelClass::where('id', $listingId)
                ->where('user_id', Auth::id())
                ->first();
            if ($auction) {
                $foundUserType = $this->user_type;
            }
        }

        // If not found, try all other models (fallback for drafts saved before fix)
        if (!$auction) {
            foreach ($modelClasses as $userType => $modelClass) {
                if ($userType === $this->user_type) continue; // Already tried
                $auction = $modelClass::where('id', $listingId)
                    ->where('user_id', Auth::id())
                    ->first();
                if ($auction) {
                    $foundUserType = $userType;
                    break;
                }
            }
        }

        if ($auction) {
            // Update user_type if found in a different table
            if ($foundUserType && $foundUserType !== $this->user_type) {
                $this->user_type = $foundUserType;
            }

            // Load all metadata fields
            $this->listing_title = $auction->title;
            $this->workflow_type = $auction->get->workflow_type ?? 'hire_agent';
            $this->service_type = $auction->get->service_type;
            $this->user_type = $auction->get->user_type;
            $this->listing_status = $auction->get->listing_status;
            $this->auction_type = $auction->get->auction_type;
            $this->referral_percentage = $auction->get->referral_percentage ?? '';
            $this->working_with_agent = $auction->get->working_with_agent;
            $this->listing_date = $auction->get->listing_date;
            $this->desired_agent_hire_date = $auction->get->desired_agent_hire_date;
            $this->expiration_date = $auction->get->expiration_date;
            $this->auction_time = $auction->get->auction_time;
            $this->agent_bid_visibility = $auction->get->agent_bid_visibility ?? '';
            $this->meeting_Preference   = $auction->get->meeting_Preference   ?? '';


            $this->number_of_unit = $auction->get->number_of_unit;

            $this->state = $auction->get->state;
            $this->property_type = $auction->get->property_type;
            $this->zip_code = $auction->get->zip_code;
            // After loading cities array
            $this->cities = is_string($auction->get->cities) ? json_decode($auction->get->cities, true) ?? [] : (array)$auction->get->cities;
            $this->newCity = $this->cities[0] ?? ''; // set for input display

            // After loading counties array
            $this->counties = is_string($auction->get->counties) ? json_decode($auction->get->counties, true) ?? [] : (array)$auction->get->counties;
            $this->newCounty = $this->counties[0] ?? ''; // set for input display
            $this->zipCodes = is_string($auction->get->zipCodes) ? json_decode($auction->get->zipCodes, true) ?? [] : (array)$auction->get->zipCodes;
            $this->zip_code = $this->zipCodes[0] ?? '';

            $this->property_city = $auction->info('property_city') ?: '';
            $this->property_state = $auction->info('property_state') ?: '';
            $this->property_zip = $auction->info('property_zip') ?: '';
            $this->property_county = $auction->info('property_county') ?: '';

            if (!empty($this->cities)) {
                $this->cityFieldVisible = true;
            }
            if (!empty($this->state)) {
                $this->stateFieldVisible = true;
            }
            if (!empty($this->zip_code) || !empty($this->zipCodes)) {
                $this->zipCodeFieldVisible = true;
            }
            if (!empty($this->counties)) {
                $this->countyFieldVisible = true;
            }

            // Property details

            // $this->property_items = is_string($auction->get->property_items) ? json_decode($auction->get->property_items, true) ?? [] : (array)$auction->get->property_items;

            // Check if the user type is 'seller'


            if ($this->user_type === 'seller' || $this->user_type === 'landlord') {
                // If user type is seller, keep $this->property_items as a string
                $this->property_items = is_string($auction->get->property_items) ? $auction->get->property_items : '';
            } else {
                // Otherwise, treat $this->property_items as JSON and decode it
                $this->property_items = is_string($auction->get->property_items) ? json_decode($auction->get->property_items, true) ?? [] : (array)$auction->get->property_items;
            }
            $this->other_property_items = $auction->get->other_property_items ?? '';
            $rawConditionPropBuyerVal = $auction->get->condition_prop_buyer ?? null;
            $rawConditionPropBuyer = is_string($rawConditionPropBuyerVal) ? json_decode($rawConditionPropBuyerVal, true) ?? [] : (array)($rawConditionPropBuyerVal ?? []);
            $this->condition_prop_buyer = $this->mapLegacyPropertyConditions($rawConditionPropBuyer);
            $this->condition_prop_buyer_json = json_encode($this->condition_prop_buyer ?? []);

            $rawConditionProp = $auction->info('condition_prop') ?? '';
            $this->condition_prop = ($this->user_type === 'seller' || $this->user_type === 'landlord')
                ? $rawConditionProp
                : $this->mapLegacyPropertyConditions($rawConditionProp);
            $this->business_type = $auction->get->business_type ?? '';
            $this->business_type_selected = $auction->get->business_type_selected ?? '';
            $this->other_business_type = $auction->get->other_business_type ?? '';
            $this->leasing_space = $auction->get->leasing_space ?? '';
            $this->restrictions = $auction->get->restrictions ?? '';
            $this->common_areas_access = $auction->get->common_areas_access ?? '';
            $this->maintenance_response_time = $auction->get->maintenance_response_time ?? '';
            $this->storage_space = $auction->get->storage_space ?? '';
            $this->included_storage_space_com_entire = $auction->get->included_storage_space_com_entire ?? '';
            $this->storage_space_com_entire = $auction->get->storage_space_com_entire ?? '';
            $this->utilities = $auction->get->utilities ?? '';
            $this->common_areas_cleaning = $auction->get->common_areas_cleaning ?? '';
            $this->included_storage_space_com_single = $auction->get->included_storage_space_com_single ?? '';
            $this->storage_space_com_single = $auction->get->storage_space_com_single ?? '';
            $this->included_storage_space_res_both = $auction->get->included_storage_space_res_both ?? '';
            $this->storage_space_res_both = $auction->get->storage_space_res_both ?? '';
            $this->included_storage_space_res_single = $auction->get->included_storage_space_res_single ?? '';
            $this->storage_space_res_single = $auction->get->storage_space_res_single ?? '';
            $this->bathroom_facilities = $auction->get->bathroom_facilities ?? '';
            $this->room_size = $auction->get->room_size ?? '';
            $this->building_hours = $auction->get->building_hours ?? '';
            $this->access_24_7 = $auction->get->access_24_7 ?? '';
            $this->zoning_allows = $auction->get->zoning_allows ?? '';
            $this->shared_amenities = $auction->get->shared_amenities ?? '';
            $this->space_features = $auction->get->space_features ?? '';
            $this->neighboring_tenants = $auction->get->neighboring_tenants ?? '';
            $this->guests_allowed = $auction->get->guests_allowed ?? '';
            $this->maintenance_by = $auction->get->maintenance_by ?? '';
            $this->other_property_condition = $auction->get->other_property_condition ?? '';
            $this->bathrooms = $auction->get->bathrooms ?? '';

            $this->tenant_pays = is_string($auction->get->tenant_pays ?? null) ? json_decode($auction->get->tenant_pays, true) ?? [] : (array)($auction->get->tenant_pays ?? []);
            $this->other_tenant_pays = $auction->get->other_tenant_pays ?? '';

            $this->desired_lease_length = is_string($auction->get->desired_lease_length ?? null) ? json_decode($auction->get->desired_lease_length, true) ?? [] : (array)($auction->get->desired_lease_length ?? []);
            $this->other_lease_term = $auction->get->other_lease_term ?? '';
            $this->is_update_lease_term_option_visible = is_array($this->desired_lease_length) && in_array('Other', $this->desired_lease_length);

            $this->rent_includes = is_string($auction->get->rent_includes ?? null) ? json_decode($auction->get->rent_includes, true) ?? [] : (array)($auction->get->rent_includes ?? []);
            $this->other_rent_include = $auction->get->other_rent_include ?? '';

            $this->owner_pays = is_string($auction->get->owner_pays ?? null) ? json_decode($auction->get->owner_pays, true) ?? [] : (array)($auction->get->owner_pays ?? []);
            $this->other_owner_pays = $auction->get->other_owner_pays ?? '';
            $this->owner_pays_other = $auction->get->owner_pays_other ?? '';
            $this->leasing_spaces_tenant = is_string($auction->get->leasing_spaces_tenant ?? null) ? json_decode($auction->get->leasing_spaces_tenant, true) ?? [] : (array)($auction->get->leasing_spaces_tenant ?? []);

            $this->terms_of_lease = is_string($auction->get->terms_of_lease ?? null) ? json_decode($auction->get->terms_of_lease, true) ?? [] : (array)($auction->get->terms_of_lease ?? []);
            $this->custom_lease_term = $auction->get->custom_lease_term ?? '';

            $this->other_bathrooms = $auction->get->other_bathrooms ?? '';
            $this->bedrooms = $auction->get->bedrooms ?? '';
            $this->other_bedrooms = $auction->get->other_bedrooms ?? '';
            $this->minimum_heated_square = $auction->get->minimum_heated_square ?? '';
            $this->minimum_leaseable = $auction->get->minimum_leaseable ?? '';
            $this->min_acreage = $auction->get->min_acreage ?? '';
            $this->total_acreage = $auction->get->total_acreage ?? '';

            // Amenities and features

            $this->tenant_require = is_string($auction->get->tenant_require ?? null) ? json_decode($auction->get->tenant_require, true) ?? [] : (array)($auction->get->tenant_require ?? []);

            $this->garage_parking_spaces_option_buyer = is_string($auction->get->garage_parking_spaces_option_buyer ?? null) ? json_decode($auction->get->garage_parking_spaces_option_buyer, true) ?? [] : (array)($auction->get->garage_parking_spaces_option_buyer ?? []);


            $this->carport_needed = $auction->get->carport_needed ?? '';
            $this->other_carport_needed = $auction->get->other_carport_needed ?? '';
            $this->total_square_feet = $auction->get->total_square_feet ?? '';
            $this->sqft_heated_source = $auction->get->sqft_heated_source ?? '';
            $this->garage_needed = $auction->get->garage_needed ?? '';
            $this->other_garage_needed = $auction->get->other_garage_needed ?? '';
            $this->garage_parking_spaces = $auction->get->garage_parking_spaces ?? '';



            $this->garage_parking_spaces_option = is_string($auction->get->garage_parking_spaces_option ?? null) ? json_decode($auction->get->garage_parking_spaces_option, true) ?? [] : (array)($auction->get->garage_parking_spaces_option ?? []);



            $this->other_parking_space_wrapper = $auction->get->other_parking_space_wrapper ?? '';
            $this->pool_needed = $auction->get->pool_needed ?? '';

            $this->pool_type = is_string($auction->get->pool_type ?? null) ? json_decode($auction->get->pool_type, true) ?? [] : (array)($auction->get->pool_type ?? []);




            $this->view_preference = is_string($auction->get->view_preference ?? null) ? json_decode($auction->get->view_preference, true) ?? [] : (array)($auction->get->view_preference ?? []);



            $this->other_preferences = $auction->get->other_preferences ?? '';

            $this->appliances = is_string($auction->get->appliances ?? null) ? json_decode($auction->get->appliances, true) ?? [] : (array)($auction->get->appliances ?? []);



            $this->other_appliances = $auction->get->other_appliances ?? '';
            $this->leasing_55_plus = $auction->get->leasing_55_plus ?? '';

            $this->non_negotiable_amenities = is_string($auction->get->non_negotiable_amenities ?? null) ? json_decode($auction->get->non_negotiable_amenities, true) ?? [] : (array)($auction->get->non_negotiable_amenities ?? []);

            $this->other_non_negotiable_amenities = $auction->get->other_non_negotiable_amenities ?? '';
            $this->real_estate_purchase = $auction->get->real_estate_purchase ?? '';
            $rawAssets = $auction->get->assets ?? '';
            $this->assets = $rawAssets ? (is_string($rawAssets) ? json_decode($rawAssets, true) ?? [] : (array)$rawAssets) : [];
            $this->assets_other = $auction->get->assets_other ?? '';
            $this->assets_visible = is_array($this->assets) && in_array('Other', $this->assets);
            $this->business_assets = is_string($auction->get->business_assets ?? '') && ($auction->get->business_assets ?? '') !== ''
                ? (json_decode($auction->get->business_assets, true) ?? [])
                : [];
            $this->property_criteria = $auction->get->property_criteria ?? '';
            $this->unit_size = $auction->get->unit_size ?? '';
            $this->unit_size_other = $auction->get->unit_size_other ?? '';
            $this->budget = $auction->get->budget ?? '';
            $this->occupied_until = $auction->get->occupied_until ?? '';
            $this->occupancy_status = $auction->get->occupancy_status ?? '';

            // Lease terms
            $leaseForRaw = $auction->get->lease_for ?? null;
            $this->lease_for = $leaseForRaw ? (is_string($leaseForRaw) ? json_decode($leaseForRaw, true) ?? [] : (array)$leaseForRaw) : [];


            $this->other_lease_for = $auction->get->other_lease_for ?? '';
            $this->lease_by = $auction->get->lease_by ?? '';
            $this->lease_date = $auction->get->lease_date ?? '';

            // Tenant Leasing Terms — 23 new optional fields (draft rehydration)
            $this->tenant_desired_lease_length        = $auction->info('tenant_desired_lease_length') ?: '';
            $this->security_deposit_budget            = $auction->info('security_deposit_budget') ?: '';
            $this->move_in_funds_available            = $auction->info('move_in_funds_available') ?: '';
            $this->first_month_rent_available         = $auction->info('first_month_rent_available') ?: '';
            $this->last_month_rent_available          = $auction->info('last_month_rent_available') ?: '';
            $this->pet_information                    = $auction->info('pet_information') ?: '';
            $this->number_of_occupants                = $auction->info('number_of_occupants') ?: '';
            $this->parking_needed                     = $auction->info('parking_needed') ?: '';
            $this->utility_preference                 = $auction->info('utility_preference') ?: '';
            $this->maintenance_preference             = $auction->info('maintenance_preference') ?: '';
            $this->renewal_option_requested           = $auction->info('renewal_option_requested') ?: '';
            $this->renewal_option_details             = $auction->info('renewal_option_details') ?: '';
            $this->tenant_conditions                  = $auction->info('tenant_conditions') ?: '';
            $this->additional_tenant_lease_terms      = $auction->info('additional_tenant_lease_terms') ?: '';
            $this->commercial_lease_type_preference   = $auction->info('commercial_lease_type_preference') ?: '';
            $this->cam_nnn_preference                 = $auction->info('cam_nnn_preference') ?: '';
            $this->rent_escalation_preference         = $auction->info('rent_escalation_preference') ?: '';
            $this->buildout_tenant_improvement_request= $auction->info('buildout_tenant_improvement_request') ?: '';
            $this->intended_business_use              = $auction->info('intended_business_use') ?: '';
            $this->signage_request                    = $auction->info('signage_request') ?: '';
            $this->commercial_parking_access_needs    = $auction->info('commercial_parking_access_needs') ?: '';
            $this->personal_guarantee_preference      = $auction->info('personal_guarantee_preference') ?: '';
            $this->commercial_approval_conditions     = $auction->info('commercial_approval_conditions') ?: '';

            // Tenant information
            $this->pets = $auction->get->pets ?? '';
            $this->number_of_pets = $auction->get->number_of_pets ?? '';
            $this->breed_of_pets = $auction->get->breed_of_pets ?? '';
            $this->type_of_pets = $auction->get->type_of_pets ?? '';
            $this->weight_of_pets = $auction->get->weight_of_pets ?? '';
            $this->service_animal = $auction->get->service_animal ?? '';
            $this->other_services_enabled = $auction->get->other_services_enabled ?? '';
            $this->support_animal = $auction->get->support_animal ?? '';
            $this->emotional_support_animal = $auction->get->emotional_support_animal ?? '';
            $this->has_breed_restrictions = $auction->get->has_breed_restrictions ?? '';
            $this->breed_restrictions = $auction->get->breed_restrictions ?? '';
            $this->minimum_annual_net_income = $auction->get->minimum_annual_net_income ?? '';
            $this->minimum_cap_rate = $auction->get->minimum_cap_rate ?? '';

            $numUnitTypeRaw = $auction->get->number_of_unit_type ?? null;
            $this->number_of_unit_type = $numUnitTypeRaw ? (is_string($numUnitTypeRaw) ? json_decode($numUnitTypeRaw, true) ?? [] : (array)$numUnitTypeRaw) : [];

            $this->unit_number = $auction->get->unit_number ?? '';
            $this->unit_buildings = $auction->get->unit_buildings ?? '';

            $unitTypeConfigRaw = $auction->get->unit_type_configurations ?? null;
            if ($unitTypeConfigRaw) {
                $decoded = is_string($unitTypeConfigRaw) ? json_decode($unitTypeConfigRaw, true) : (array)$unitTypeConfigRaw;
                if (!empty($decoded) && is_array($decoded)) {
                    $this->unit_type_configurations = array_values($decoded);
                }
            }

            $this->screening_concerns = $auction->get->screening_concerns ?? '';
            $this->screening_concerns_explanation = $auction->get->screening_concerns_explanation ?? '';

            $creditRaw = $auction->get->credit_scroe_rating ?? null;
            $this->credit_scroe_rating = $creditRaw ? (is_string($creditRaw) ? json_decode($creditRaw, true) ?? [] : (array)$creditRaw) : [];
            $this->preferance_details = $auction->get->preferance_details ?? '';


            ///////// Buyer purchasing terms
            $saleProvisionRaw = $auction->get->sale_provision ?? null;
            $this->sale_provision = $saleProvisionRaw ? (is_string($saleProvisionRaw) ? json_decode($saleProvisionRaw, true) ?? [] : (array)$saleProvisionRaw) : [];
            $this->sale_provision_other = $auction->get->sale_provision_other ?? '';
            $this->sale_provision_assignment = $auction->get->sale_provision_assignment ?? '';
            $this->assignment_fee_type = $auction->get->assignment_fee_type ?? '';
            $this->assignment_fee_amount = $auction->get->assignment_fee_amount ?? '';
            $this->occupant_status = $auction->get->occupant_status ?? '';
            $this->leasing_spaces = $auction->get->leasing_spaces ?? '';
            $this->occupant_tenant = $auction->get->occupant_tenant ?? '';
            $this->desired_rental_amount = $auction->get->desired_rental_amount ?? '';
            $this->lease_amount_frequency = $auction->get->lease_amount_frequency ?? '';
            $this->target_closing_date = $auction->get->target_closing_date ?? '';
            $this->maximum_budget = $auction->get->maximum_budget ?? '';
            $offeredFinancingRaw = $auction->get->offered_financing ?? null;
            $this->offered_financing = $offeredFinancingRaw ? (is_string($offeredFinancingRaw) ? json_decode($offeredFinancingRaw, true) ?? [] : (array)$offeredFinancingRaw) : [];
            $this->other_financing = $auction->get->other_financing ?? '';
            $this->cash_budget = $auction->get->cash_budget ?? '';
            $this->pre_approved = $auction->get->pre_approved ?? '';
            $this->pre_approval_amount = $auction->get->pre_approval_amount ?? '';
            $this->purchase_price = $auction->get->purchase_price ?? '';
            $this->down_payment_type = $auction->get->down_payment_type ?? '';
            $this->down_payment_amount = $auction->get->down_payment_amount ?? '';
            $this->seller_down_payment_amount = $auction->get->seller_down_payment_amount ?? '';
            $this->seller_financing_type = $auction->get->seller_financing_type ?? '';
            $this->seller_financing_amount = $auction->get->seller_financing_amount ?? '';
            $this->interest_rate = $auction->get->interest_rate ?? '';
            $this->loan_duration = $auction->get->loan_duration ?? '';
            $this->prepayment_penalty = $auction->get->prepayment_penalty ?? '';
            $this->prepayment_penalty_amount = $auction->get->prepayment_penalty_amount ?? '';
            $this->balloon_payment = $auction->get->balloon_payment ?? '';
            $this->balloon_payment_amount = $auction->get->balloon_payment_amount ?? '';
            $this->balloon_payment_date = $auction->get->balloon_payment_date ?? '';
            $this->assumable_terms = $auction->get->assumable_terms ?? '';
            $this->max_assumable_rate = $auction->get->max_assumable_rate ?? '';
            $this->assumable_loan_type = $auction->info('assumable_loan_type') ?? '';
            $this->assumable_monthly_escrow = $auction->info('assumable_monthly_escrow') ?? '';
            $this->assumable_loan_term_remaining = $auction->info('assumable_loan_term_remaining') ?? '';
            $this->assumable_loan_origination_date = $auction->info('assumable_loan_origination_date') ?? '';
            $this->assumable_loan_servicer = $auction->info('assumable_loan_servicer') ?? '';
            $this->assumable_fee_type = $auction->info('assumable_fee_type') ?: '$';
            $this->assumable_fee_amount = $auction->info('assumable_fee_amount') ?? '';
            $this->assumable_occupancy_requirement = $auction->info('assumable_occupancy_requirement') ?? '';
            $this->assumable_occupancy_other = $auction->info('assumable_occupancy_other') ?? '';
            $this->max_monthly_payment = $auction->get->max_monthly_payment ?? '';
            $this->outstanding_balance = $auction->get->outstanding_balance ?? '';
            $this->gap_payment_type = $auction->get->gap_payment_type ?? '';
            $this->gap_payment_amount = $auction->get->gap_payment_amount ?? '';
            $rawExchangeItem = $auction->get->exchange_item ?? '';
            if (is_array($rawExchangeItem)) {
                $this->exchange_item = $rawExchangeItem;
            } elseif (is_string($rawExchangeItem) && trim($rawExchangeItem) !== '') {
                $decoded = json_decode(str_replace('&quot;', '"', $rawExchangeItem), true);
                $this->exchange_item = is_array($decoded) ? $decoded : [$rawExchangeItem];
            } else {
                $this->exchange_item = [];
            }
            $this->other_exchange_item = $auction->get->other_exchange_item ?? '';
            $this->exchange_item_value = $auction->get->exchange_item_value ?? '';
            $this->exchange_item_condition = $auction->get->exchange_item_condition ?? '';
            $this->additional_cash = $auction->get->additional_cash ?? '';
            $this->value_determination = $auction->get->value_determination ?? '';
            $this->lease_option_price = $auction->get->lease_option_price ?? '';
            $this->lease_option_terms = $auction->get->lease_option_terms ?? '';
            $this->lease_option_duration = $auction->get->lease_option_duration ?? '';
            $this->lease_option_payment = $auction->get->lease_option_payment ?? '';
            $this->lease_option_conditions = $auction->get->lease_option_conditions ?? '';
            $this->has_option_fee = $auction->get->has_option_fee ?? '';
            $this->option_fee_amount = $auction->get->option_fee_amount ?? '';
            $this->lease_purchase_price = $auction->get->lease_purchase_price ?? '';
            $this->lease_purchase_terms = $auction->get->lease_purchase_terms ?? '';
            $this->lease_purchase_duration = $auction->get->lease_purchase_duration ?? '';
            $this->lease_purchase_payment = $auction->get->lease_purchase_payment ?? '';
            $this->lease_purchase_conditions = $auction->get->lease_purchase_conditions ?? '';
            $this->lease_purchase_option_fee = $auction->get->lease_purchase_option_fee ?? '';
            $this->lease_purchase_option_fee_amount = $auction->get->lease_purchase_option_fee_amount ?? '';
            $this->cryptocurrency_type = $auction->get->cryptocurrency_type ?? '';
            $this->crypto_percentage = $auction->get->crypto_percentage ?? '';
            $this->cash_percentage_crypto = $auction->get->cash_percentage_crypto ?? '';
            $this->nft_description = $auction->get->nft_description ?? '';
            $this->nft_percentage = $auction->get->nft_percentage ?? '';
            $this->cash_percentage_nft = $auction->get->cash_percentage_nft ?? '';

            // Seller Financing follow-up fields
            $this->seller_amortization_type = $auction->get->seller_amortization_type ?? '';
            $this->seller_amortization_other = $auction->get->seller_amortization_other ?? '';
            $this->seller_payment_frequency = $auction->get->seller_payment_frequency ?? '';
            $this->seller_payment_frequency_other = $auction->get->seller_payment_frequency_other ?? '';
            $this->seller_late_fee_amount = $auction->get->seller_late_fee_amount ?? '';

            // Lease Option follow-up fields
            $this->lease_option_maintenance = $auction->get->lease_option_maintenance ?? '';
            $this->lease_option_extension_terms = $auction->get->lease_option_extension_terms ?? '';
            $this->lease_option_fee_credit = $auction->get->lease_option_fee_credit ?? '';
            $this->lease_option_fee_credit_percentage = $auction->get->lease_option_fee_credit_percentage ?? '';

            // Lease Purchase follow-up fields
            $this->lease_purchase_maintenance = $auction->get->lease_purchase_maintenance ?? '';
            $this->lease_purchase_extension_terms = $auction->get->lease_purchase_extension_terms ?? '';
            $this->lease_purchase_rent_credit = $auction->get->lease_purchase_rent_credit ?? '';
            $this->lease_purchase_rent_credit_amount = $auction->get->lease_purchase_rent_credit_amount ?? '';
            $this->lease_purchase_deposit = $auction->get->lease_purchase_deposit ?? '';

            // Cryptocurrency follow-up fields
            $this->crypto_exchange_method = $auction->get->crypto_exchange_method ?? '';
            $this->crypto_custodian_wallet = $auction->get->crypto_custodian_wallet ?? '';
            $this->crypto_transaction_fees = $auction->get->crypto_transaction_fees ?? '';
            $this->crypto_transfer_timing = $auction->get->crypto_transfer_timing ?? '';
            $this->crypto_transfer_timing_other = $auction->get->crypto_transfer_timing_other ?? '';

            // NFT follow-up fields
            $this->nft_valuation_method = $auction->get->nft_valuation_method ?? '';
            $this->nft_transfer_method = $auction->get->nft_transfer_method ?? '';
            $this->nft_gas_fees = $auction->get->nft_gas_fees ?? '';

            // Exchange/Trade follow-up fields
            $this->exchange_transfer_method = $auction->get->exchange_transfer_method ?? '';
            $this->exchange_liens = $auction->get->exchange_liens ?? '';
            $this->exchange_liens_details = $auction->get->exchange_liens_details ?? '';
            $this->exchange_inspection_rights = $auction->get->exchange_inspection_rights ?? '';

            ///////////////// Buyer purchasing terms end

            // Buyer Purchasing Terms — 18 new optional fields (Task #155, draft rehydration)
            $this->earnest_money_amount              = $auction->info('earnest_money_amount') ?: '';
            $this->earnest_money_timing              = $auction->info('earnest_money_timing') ?: '';
            $this->inspection_period_days            = $auction->info('inspection_period_days') ?: '';
            $this->inspection_contingency_buyer      = $auction->info('inspection_contingency_buyer') ?: '';
            $this->appraisal_contingency_buyer       = $auction->info('appraisal_contingency_buyer') ?: '';
            $this->financing_contingency_buyer       = $auction->info('financing_contingency_buyer') ?: '';
            $this->financing_contingency_days_buyer  = $auction->info('financing_contingency_days_buyer') ?: '';
            $this->seller_contribution               = $auction->info('seller_contribution') ?: '';
            $this->seller_contribution_details       = $auction->info('seller_contribution_details') ?: '';
            $this->possession_preference             = $auction->info('possession_preference') ?: '';
            $this->possession_details                = $auction->info('possession_details') ?: '';
            $this->home_warranty_requested           = $auction->info('home_warranty_requested') ?: '';
            $this->home_warranty_details             = $auction->info('home_warranty_details') ?: '';
            $this->as_is_purchase                    = $auction->info('as_is_purchase') ?: '';
            $this->property_inclusions               = $auction->info('property_inclusions') ?: '';
            $this->property_exclusions               = $auction->info('property_exclusions') ?: '';
            $this->closing_cost_responsibility       = $auction->info('closing_cost_responsibility') ?: '';
            $this->additional_purchase_terms         = $auction->info('additional_purchase_terms') ?: '';

            // Landlord Lease Terms — 22 new optional fields (Task #157, draft rehydration)
            $this->lease_available_date              = $auction->info('lease_available_date') ?: '';
            $this->security_deposit_required         = $auction->info('security_deposit_required') ?: '';
            $this->first_month_rent_required         = $auction->info('first_month_rent_required') ?: '';
            $this->last_month_rent_required          = $auction->info('last_month_rent_required') ?: '';
            $this->total_move_in_funds_required      = $auction->info('total_move_in_funds_required') ?: '';
            $this->pet_policy                        = $auction->info('pet_policy') ?: '';
            $this->pet_deposit_fee_rent              = $auction->info('pet_deposit_fee_rent') ?: '';
            $this->number_of_occupants_allowed       = $auction->info('number_of_occupants_allowed') ?: '';
            $this->parking_terms                     = $auction->info('parking_terms') ?: '';
            $this->utility_responsibility            = $auction->info('utility_responsibility') ?: '';
            $this->ll_maintenance_responsibility     = $auction->info('ll_maintenance_responsibility') ?: '';
            $this->renewal_option_offered            = $auction->info('renewal_option_offered') ?: '';
            $this->landlord_approval_conditions      = $auction->info('landlord_approval_conditions') ?: '';
            $this->additional_landlord_lease_terms   = $auction->info('additional_landlord_lease_terms') ?: '';
            $this->commercial_lease_type             = $auction->info('commercial_lease_type') ?: '';
            $this->cam_nnn_additional_rent_charges   = $auction->info('cam_nnn_additional_rent_charges') ?: '';
            $this->rent_escalation_terms             = $auction->info('rent_escalation_terms') ?: '';
            $this->tenant_improvement_buildout_terms = $auction->info('tenant_improvement_buildout_terms') ?: '';
            $this->permitted_use_restrictions        = $auction->info('permitted_use_restrictions') ?: '';
            $this->signage_rights                    = $auction->info('signage_rights') ?: '';
            $this->commercial_parking_terms          = $auction->info('commercial_parking_terms') ?: '';
            $this->personal_guarantee_requirement    = $auction->info('personal_guarantee_requirement') ?: '';

            $this->prior_eviction = $auction->get->prior_eviction ?? '';
            $this->eviction_explanation = $auction->get->eviction_explanation ?? '';
            $this->prior_felony = $auction->get->prior_felony ?? '';
            $this->prior_felony_explanation = $auction->get->prior_felony_explanation ?? '';
            $this->monthly_income = $auction->get->monthly_income ?? '';
            $this->number_occupant = $auction->get->number_occupant ?? '';

            // Services
            $servicesRaw = $auction->get->services ?? null;
            $this->services = $servicesRaw ? (is_string($servicesRaw) ? json_decode($servicesRaw, true) ?? [] : (array)$servicesRaw) : [];
            $otherServicesRaw = $auction->get->other_services ?? null;
            $this->other_services = $otherServicesRaw ? (is_string($otherServicesRaw) ? json_decode($otherServicesRaw, true) ?? [] : (array)$otherServicesRaw) : [];


            // $this->other_services = $auction->get->other_services;


            $flatFeeServicesRaw = $auction->get->flat_fee_services ?? null;
            $this->flat_fee_services = $flatFeeServicesRaw ? (is_string($flatFeeServicesRaw) ? json_decode($flatFeeServicesRaw, true) ?? [] : (array)$flatFeeServicesRaw) : [];
            $this->additional_details = $auction->get->additional_details ?? '';

            // Broker compensation
            $this->commission_structure = $auction->get->commission_structure ?? '';


            $this->commission_structure_type = $auction->get->commission_structure_type ?? '';

            $this->commission_structure_type_fee_flat = $auction->get->commission_structure_type_fee_flat ?? '';
            $this->commission_structure_type_fee_percentage = $auction->get->commission_structure_type_fee_percentage ?? '';
            $this->commission_structure_type_fee_percentage_combo = $auction->get->commission_structure_type_fee_percentage_combo ?? '';
            $this->commission_structure_type_fee_flat_combo = $auction->get->commission_structure_type_fee_flat_combo ?? '';
            $this->commission_structure_type_fee_other = $auction->get->commission_structure_type_fee_other ?? '';
            $this->lease_fee_type = $auction->get->lease_fee_type ?? '';
            $this->lease_fee_flat_type = $auction->get->lease_fee_flat_type ?? '';
            $this->lease_fee_flat = $auction->get->lease_fee_flat ?? '';
            $this->lease_fee_percentage = $auction->get->lease_fee_percentage ?? '';
            $this->lease_fee_months = $auction->get->lease_fee_months ?? '';
            $this->lease_fee_percentage_monthly_rent = $auction->get->lease_fee_percentage_monthly_rent ?? '';
            $this->lease_fee_percentage_monthly_number = $auction->get->lease_fee_percentage_monthly_number ?? '';
            $this->lease_fee_percentage_net = $auction->get->lease_fee_percentage_net ?? '';
            $this->lease_fee_flat_combo_net = $auction->get->lease_fee_flat_combo_net ?? '';
            $this->lease_fee_percentage_combo_net = $auction->get->lease_fee_percentage_combo_net ?? '';
            $this->lease_fee_flat_combo = $auction->get->lease_fee_flat_combo ?? '';
            $this->lease_fee_percentage_combo = $auction->get->lease_fee_percentage_combo ?? '';
            $this->lease_fee_other = $auction->get->lease_fee_other ?? '';
            $this->interested_purchase_fee_type = $auction->get->interested_purchase_fee_type ?? '';

            $this->purchase_fee_flat_commercial = $auction->get->purchase_fee_flat_commercial ?? '';


            $this->seller_leasing_fee_type = $auction->get->seller_leasing_fee_type ?? '';
            $this->seller_leasing_gross = $auction->get->seller_leasing_gross ?? '';
            $this->seller_leasing_gross_month_rent = $auction->get->seller_leasing_gross_month_rent ?? '';
            $this->seller_leasing_gross_rental = $auction->get->seller_leasing_gross_rental ?? '';
            $this->seller_broker_leasing_fee = $auction->get->seller_broker_leasing_fee ?? '';
            $this->seller_leasing_each_rental = $auction->get->seller_leasing_each_rental ?? '';
            $this->seller_leasing_gross_no_of_months = $auction->get->seller_leasing_gross_no_of_months ?? '';


            $this->seller_leasing_gross_flat_combo = $auction->get->seller_leasing_gross_flat_combo ?? '';
            $this->seller_leasing_gross_percentage_combo = $auction->get->seller_leasing_gross_percentage_combo ?? '';
            $this->seller_leasing_gross_flat_net_combo = $auction->get->seller_leasing_gross_flat_net_combo ?? '';
            $this->seller_leasing_gross_percentage_net_combo = $auction->get->seller_leasing_gross_percentage_net_combo ?? '';

            // Interested in Selling
            $this->interested_in_selling = $auction->get->interested_in_selling ?? '';
            $this->interested_in_selling_type = $auction->get->interested_in_selling_type ?? '';
            $this->landlord_broker_purchase_price = $auction->get->landlord_broker_purchase_price ?? '';
            $this->landlord_broker_percentage_price = $auction->get->landlord_broker_percentage_price ?? '';
            $this->landlord_broker_dollar_price = $auction->get->landlord_broker_dollar_price ?? '';
            $this->landlord_broker_flate_fee = $auction->get->landlord_broker_flate_fee ?? '';
            $this->landlord_broker_other = $auction->get->landlord_broker_other ?? '';


            //  Payment Timing for Broker Fees:

            $this->broker_fee_timing = $auction->get->broker_fee_timing ?? '';
            $this->broker_fee_days_from_rent = $auction->get->broker_fee_days_from_rent ?? '';
            $this->broker_fee_days_after_lease = $auction->get->broker_fee_days_after_lease ?? '';
            $this->broker_fee_days_after_rent = $auction->get->broker_fee_days_after_rent ?? '';
            $this->split_payment_due = $auction->get->split_payment_due ?? '';
            $this->split_payment_due_other = $auction->get->split_payment_due_other ?? '';
            $this->broker_fee_days_after_due_event = $auction->get->broker_fee_days_after_due_event ?? '';
            $this->broker_fee_timing_other = $auction->get->broker_fee_timing_other ?? '';




            // Lease Renewal/Extension Fee:

            $this->renewal_fee_type = $auction->get->renewal_fee_type ?? '';
            $this->renewal_fee_percentage = $auction->get->renewal_fee_percentage ?? '';
            $this->renewal_fee_lease_value = $auction->get->renewal_fee_lease_value ?? '';
            $this->renewal_fee_first_month = $auction->get->renewal_fee_first_month ?? '';
            $this->renewal_fee_flat_free = $auction->get->renewal_fee_flat_free ?? '';
            $this->renewal_fee_custom = $auction->get->renewal_fee_custom ?? '';

            $this->renewal_fee_sales_tax_lease_value = $auction->get->renewal_fee_sales_tax_lease_value ?? '';
            $this->renewal_fee_sales_tax_flat_fee = $auction->get->renewal_fee_sales_tax_flat_fee ?? '';
            $this->renewal_fee_sales_tax_first_month = $auction->get->renewal_fee_sales_tax_first_month ?? '';
            $this->renewal_fee_no_of_months = $auction->get->renewal_fee_no_of_months ?? '';
            $this->expansion_commission_percentage = $auction->get->expansion_commission_percentage ?? '';


            $this->tenant_broker_commission_structure = $auction->get->tenant_broker_commission_structure ?? '';
            $this->tenant_broker_fee_structure = $auction->get->tenant_broker_fee_structure ?? '';

            $this->tenant_broker_percentage = $auction->get->tenant_broker_percentage ?? '';
            $this->tenant_broker_gross_lease = $auction->get->tenant_broker_gross_lease ?? '';
            $this->tenant_broker_first_month_rent = $auction->get->tenant_broker_first_month_rent ?? '';
            $this->tenant_broker_flat_fee = $auction->get->tenant_broker_flat_fee ?? '';
            $this->tenant_broker_other = $auction->get->tenant_broker_other ?? '';

            $this->seller_leasing_gross_sales_tax_option_gross = $auction->get->seller_leasing_gross_sales_tax_option_gross ?? '';
            $this->seller_leasing_gross_ross_percentage_rent = $auction->get->seller_leasing_gross_ross_percentage_rent ?? '';
            $this->seller_leasing_gross_sales_tax_flat_free_gross = $auction->get->seller_leasing_gross_sales_tax_flat_free_gross ?? '';
            $this->seller_leasing_gross_sales_tax_first_month = $auction->get->seller_leasing_gross_sales_tax_first_month ?? '';
            $this->seller_leasing_gross_percentage_no_of_months = $auction->get->seller_leasing_gross_percentage_no_of_months ?? '';
            $this->seller_leasing_gross_purchase_fee_flat_amount = $auction->get->seller_leasing_gross_purchase_fee_flat_amount ?? '';
            $this->seller_leasing_gross_purchase_fee_other = $auction->get->seller_leasing_gross_purchase_fee_other ?? '';
            $this->seller_leasing_gross_other = $auction->get->seller_leasing_gross_other ?? '';



            $this->interested_in_property_management = $auction->get->interested_in_property_management ?? '';
            $this->interested_in_property_management_fee = $auction->get->interested_in_property_management_fee ?? '';
            $this->interested_in_property_management_fee_gross_lease = $auction->get->interested_in_property_management_fee_gross_lease ?? '';
            $this->interested_in_property_management_fee_rental_periord = $auction->get->interested_in_property_management_fee_rental_periord ?? '';
            $this->interested_in_property_management_fee_flate_free = $auction->get->interested_in_property_management_fee_flate_free ?? '';
            $this->interested_in_property_management_fee_other = $auction->get->interested_in_property_management_fee_other ?? '';



            $this->purchase_fee_type = $auction->get->purchase_fee_type ?? '';
            $this->purchase_fee_gross_rent = $auction->get->purchase_fee_gross_rent ?? '';
            $this->sales_tax_option_gross = $auction->get->sales_tax_option_gross ?? '';
            $this->sales_tax_option_flat = $auction->get->sales_tax_option_flat ?? '';
            $this->sales_tax_option_monthly = $auction->get->sales_tax_option_monthly ?? '';

            $this->purchase_fee_net_aggregate = $auction->get->purchase_fee_net_aggregate ?? '';
            $this->purchase_fee_monthly_percentage = $auction->get->purchase_fee_monthly_percentage ?? '';
            $this->purchase_fee_months = $auction->get->purchase_fee_months ?? '';
            $this->purchase_fee_percentage = $auction->get->purchase_fee_percentage ?? '';
            $this->purchase_fee_flat = $auction->get->purchase_fee_flat ?? '';
            $this->purchase_fee_flat_type = $auction->get->purchase_fee_flat_type ?? '';
            $this->purchase_fee_rental_period = $auction->get->purchase_fee_rental_period ?? '';
            $this->purchase_fee_percentage_combo = $auction->get->purchase_fee_percentage_combo ?? '';
            $this->purchase_fee_flat_combo = $auction->get->purchase_fee_flat_combo ?? '';
            $this->purchase_fee_other = $auction->get->purchase_fee_other ?? '';
            $this->purchase_fee_other_commercial = $auction->get->purchase_fee_other_commercial ?? '';
            $this->purchase_fee_purchase_price = $auction->get->purchase_fee_purchase_price ?? '';
            $this->purchase_fee_flat_commercial = $auction->get->purchase_fee_flat_commercial ?? '';
            $this->nominal = $auction->get->nominal ?? '';


            $this->landlord_broker_flate_fee_type = $auction->get->landlord_broker_flate_fee_type ?? '';


            $this->lease_type = $auction->get->lease_type ?? 'percent';
            $this->lease_value = $auction->get->lease_value ?? '';
            $this->purchase_type = $auction->get->purchase_type ?? 'percent';
            $this->purchase_value = $auction->get->purchase_value ?? '';




            $this->interested_lease_option = $auction->get->interested_lease_option ?? '';
            $this->interested_lease_option_agreement = $auction->get->interested_lease_option_agreement ?? '';
            $this->lease_option_fee_type = $auction->get->lease_option_fee_type ?? '';
            $this->lease_option_fee_flat = $auction->get->lease_option_fee_flat ?? '';
            $this->lease_option_fee_percentage = $auction->get->lease_option_fee_percentage ?? '';
            $this->lease_option_fee_other = $auction->get->lease_option_fee_other ?? '';
            $this->protection_period = $auction->get->protection_period ?? '';
            $this->early_termination_fee_option = $auction->get->early_termination_fee_option ?? '';
            $this->early_termination_fee_amount = $auction->get->early_termination_fee_amount ?? '';
            $this->retainer_fee_option = $auction->get->retainer_fee_option ?? '';
            $this->retainer_fee_amount = $auction->get->retainer_fee_amount ?? '';
            $this->retainer_fee_application = $auction->get->retainer_fee_application ?? '';
            $this->agency_agreement_timeframe = $auction->get->agency_agreement_timeframe ?? '';
            $this->retained_deposits = $auction->get->retained_deposits ?? '';
            $this->agency_agreement_custom = $auction->get->agency_agreement_custom ?? '';
            $this->brokerage_relationship = $auction->get->brokerage_relationship ?? '';
            $this->additional_details_broker = $auction->get->additional_details_broker ?? '';

            // Personal information
            $this->first_name = $auction->get->first_name ?? '';
            $this->last_name = $auction->get->last_name ?? '';
            $this->phone_number = $auction->get->phone_number ?? '';
            $this->email = $auction->get->email ?? '';
            $this->agent_brokerage = $auction->get->agent_brokerage ?? '';
            $this->agent_license_number = $auction->get->agent_license_number ?? '';
            $this->agent_nar_member_id = $auction->get->agent_nar_member_id ?? '';
            $this->video_link = $auction->get->video_link ?? '';
            if (!empty($this->video_link)) {
                $this->embedUrl = $this->getEmbedUrl($this->video_link);
            }
            $this->photo = $auction->get->photo ?? null;
            $this->current_status = $auction->get->current_status ?? '';

            // Location and meeting details
            $this->person_meeting = $auction->get->person_meeting ?? '';
            $this->meeting_details_first_name = $auction->get->meeting_details_first_name ?? '';
            $this->meeting_details_last_name = $auction->get->meeting_details_last_name ?? '';
            $this->meeting_details_phone = $auction->get->meeting_details_phone ?? '';
            $this->meeting_details_email = $auction->get->meeting_details_email ?? '';
            $this->address = $auction->get->address ?? '';
            $this->meeting_details_meeting_time = $auction->get->meeting_details_meeting_time ?? '';
            $this->meeting_details_time_zone = $auction->get->meeting_details_time_zone ?? '';
            $this->meeting_details_meeting_date = $auction->get->meeting_details_meeting_date ?? '';
            $this->meeting_details_instructions = $auction->get->meeting_details_instructions ?? '';
            $this->meeting_details_additional_details = $auction->get->meeting_details_additional_details ?? '';
            $this->service_completion_date = $auction->get->service_completion_date ?? '';
            $this->service_completion_time = $auction->get->service_completion_time ?? '';
            $this->service_time_zone = $auction->get->service_time_zone ?? '';

            // Marketing services
            $this->list_criteria = (bool)($auction->get->list_criteria ?? false);
            $this->list_criteria_fee = $auction->get->list_criteria_fee ?? '';
            $this->market_groups = (bool)($auction->get->market_groups ?? false);
            $this->market_groups_fee = $auction->get->market_groups_fee ?? '';
            $this->promote_social = (bool)($auction->get->promote_social ?? false);
            $this->promote_social_fee = $auction->get->promote_social_fee ?? '';
            $this->launch_ads = (bool)($auction->get->launch_ads ?? false);
            $this->launch_ads_fee = $auction->get->launch_ads_fee ?? '';
            $this->include_marketing_fee = (bool)($auction->get->include_marketing_fee ?? false);
            $this->marketing_materials_fee = $auction->get->marketing_materials_fee ?? '';
            $this->email_notifications_fee = $auction->get->email_notifications_fee ?? '';
            $this->off_market_search_fee = $auction->get->off_market_search_fee ?? '';
            $this->mls_filter_fee = $auction->get->mls_filter_fee ?? '';
            $this->email_marketing_fee = $auction->get->email_marketing_fee ?? '';

            // Property showings
            $this->schedule_showings = (bool)($auction->get->schedule_showings ?? false);
            $this->number_of_showings_to_schedule = $auction->get->number_of_showings_to_schedule ?? '';
            $this->schedule_showings_fee = $auction->get->schedule_showings_fee ?? '';
            $this->attend_showings = (bool)($auction->get->attend_showings ?? false);
            $this->number_of_showings_to_attend = $auction->get->number_of_showings_to_attend ?? '';
            $this->attend_showings_fee = $auction->get->attend_showings_fee ?? '';
            $this->provide_virtual_tours = (bool)($auction->get->provide_virtual_tours ?? false);
            $this->number_of_virtual_tours = $auction->get->number_of_virtual_tours ?? '';
            $this->virtual_tours_fee = $auction->get->virtual_tours_fee ?? '';

            // Application & lease support
            $this->assist_application = (bool)($auction->get->assist_application ?? false);
            $this->assist_application_fee = $auction->get->assist_application_fee ?? '';
            $this->collect_documents = (bool)($auction->get->collect_documents ?? false);
            $this->collect_documents_fee = $auction->get->collect_documents_fee ?? '';
            $this->submit_application = (bool)($auction->get->submit_application ?? false);
            $this->submit_application_fee = $auction->get->submit_application_fee ?? '';
            $this->review_lease = (bool)($auction->get->review_lease ?? false);
            $this->review_lease_fee = $auction->get->review_lease_fee ?? '';
            $this->provide_lease_form = (bool)($auction->get->provide_lease_form ?? false);
            $this->provide_lease_form_fee = $auction->get->provide_lease_form_fee ?? '';
            $this->coordinate_signing = (bool)($auction->get->coordinate_signing ?? false);
            $this->coordinate_signing_fee = $auction->get->coordinate_signing_fee ?? '';
            $this->prepare_application_fee = $auction->get->prepare_application_fee ?? '';

            // Move services
            $this->move_in_inspection_fee = $auction->get->move_in_inspection_fee ?? '';
            $this->moving_resources_fee = $auction->get->moving_resources_fee ?? '';
            $this->short_term_housing_fee = $auction->get->short_term_housing_fee ?? '';

            // Advisory services
            $this->rental_rights_fee = $auction->get->rental_rights_fee ?? '';
            $this->lease_advice_fee = $auction->get->lease_advice_fee ?? '';

            // Neighborhood marketing
            $this->neighborhood_insights_fee = $auction->get->neighborhood_insights_fee ?? '';
            $this->neighborhood_marketing_fee = $auction->get->neighborhood_marketing_fee ?? '';
            $this->neighborhood_materials_fee = $auction->get->neighborhood_materials_fee ?? '';

            // Custom services
            $customServicesRaw = $auction->get->custom_services ?? null;
            $this->custom_services = $customServicesRaw ? (is_string($customServicesRaw) ? json_decode($customServicesRaw, true) ?? [] : (array)$customServicesRaw) : [];

            $this->total_marketing_fee = $auction->get->total_marketing_fee ?? '';
            $this->total_flat_fee = $auction->get->total_flat_fee ?? '';

            // Flat fee agent (limited service) tenant
            $feesRaw = $auction->get->fees ?? null;
            $this->fees = $feesRaw ? (is_string($feesRaw) ? json_decode($feesRaw, true) ?? [] : (array)$feesRaw) : [];
            $enableRaw = $auction->get->enable ?? null;
            $this->enable = $enableRaw ? (is_string($enableRaw) ? json_decode($enableRaw, true) ?? [] : (array)$enableRaw) : [];

            $this->showings_count = $auction->get->showings_count ?? '';
            $this->attend_showings_count = $auction->get->attend_showings_count ?? '';
            $this->virtual_tours_count = $auction->get->virtual_tours_count ?? '';
            $this->understand_terms = (bool)($auction->get->understand_terms ?? false);

            // Seller
            $this->staging_duration = $auction->get->staging_duration ?? '';
            $this->open_house_count = $auction->get->open_house_count ?? '';

            // Landlord
            $this->virtual_showings_count = $auction->get->virtual_showings_count ?? '';

            // Load enable checkboxes
            // $enableFields = json_decode($auction->get->enable);
            // foreach ($enableFields as $field => $value) {
            //     if (property_exists($this, 'enable') && array_key_exists($field, $this->enable)) {
            //         $this->enable[$field] = $value;
            //     }
            // }
            
            // Restore "Other" visibility flags from loaded array values
            $this->is_other_tenant_pay_visible = is_array($this->tenant_pays) && in_array('Other', $this->tenant_pays);
            $this->is_other_owner_pays_visible = is_array($this->owner_pays) && in_array('Other', $this->owner_pays);
            $this->is_rent_include_visible = is_array($this->rent_includes) && in_array('Other', $this->rent_includes);
            $this->showOtherAppliances = is_array($this->appliances) && in_array('Other', $this->appliances);

            // Restore showOpenHouseInput from loaded services
            if (is_array($this->services)) {
                $this->showOpenHouseInput = in_array('Host open houses', $this->services)
                    || in_array('Host broker tours', $this->services)
                    || in_array('Host site visit event (administrative coordination only)', $this->services);
            }

            if (is_array($this->services) && (
                in_array('Provide digital photo enhancements', $this->services)
                || in_array('Provide digital enhancements to media assets', $this->services)
            )) {
                $this->showEnhancements = true;
            }
            $photoEnhRaw = $auction->get->photo_enhancements ?? null;
            $this->photo_enhancements = $photoEnhRaw ? (is_string($photoEnhRaw) ? json_decode($photoEnhRaw, true) ?? [] : (array)$photoEnhRaw) : [];
            $this->custom_enhancement = $auction->get->custom_enhancement ?? '';
            if (!empty($this->photo_enhancements)) {
                $this->showEnhancements = true;
                $this->showCustomEnhancement = in_array('Other', $this->photo_enhancements);
            }

            // Dispatch browser event to sync select values after draft loads
            $this->dispatchBrowserEvent('draftLoaded');
        }
        } finally {
            $this->isLoadingData = false;
        }
    }


    protected function validateOnlyFilledFields()
    {
        $rules = [
            // Basic Information (all user types)
            'service_type'        => 'required',
            'user_type'           => 'required',
            'listing_title'       => 'required|string|max:255',
            'working_with_agent'  => 'required',
            'listing_date'        => 'required|date',
            'expiration_date'     => 'required',
            'auction_type'        => 'required',
        ];

        // Tenant-specific required fields (full_service only)
        if ($this->user_type === 'tenant' && $this->service_type === 'full_service') {
            // Tab 1 – Listing Details
            $rules['desired_agent_hire_date'] = 'required|date';
            $rules['meeting_Preference']      = 'required';

            // Tab 1 – Bidding Period Length (only required when auction type is Bidding Period)
            if ($this->auction_type === 'Bidding Period') {
                $rules['auction_time'] = 'required';
            }

            // Tab 2 – Property Preferences
            $rules['state']         = 'required';
            $rules['property_type'] = 'required';
            // Bedrooms and bathrooms are only rendered (and therefore only fillable)
            // for Residential-type properties. For Commercial, Vacant Land, Business, etc.
            // these fields are never shown, so they must not be required server-side.
            if (in_array($this->property_type, ['Residential Property', 'Residential', 'Income', 'Income Property'])) {
                $rules['bedrooms'] = 'required';
                $rules['bathrooms'] = 'required';
            }
            $rules['counties']      = 'required|array|min:1';

            // Tab 3 – Leasing Terms
            $rules['budget']                = 'required';
            $rules['lease_for']             = 'required|array|min:1';
            $rules['lease_date']            = 'required|date';
            $rules['leasing_spaces_tenant'] = 'required|array|min:1';

            // Tab 3 – Tenant Leasing Terms: new optional fields
            $rules['tenant_desired_lease_length']        = 'nullable|string|max:255';
            $rules['security_deposit_budget']            = 'nullable|numeric|min:0';
            $rules['move_in_funds_available']            = 'nullable|numeric|min:0';
            $rules['first_month_rent_available']         = 'nullable|string|in:,Yes,No,Negotiable';
            $rules['last_month_rent_available']          = 'nullable|string|in:,Yes,No,Negotiable';
            $rules['pet_information']                    = 'nullable|string';
            $rules['number_of_occupants']                = 'nullable|integer|min:0';
            $rules['parking_needed']                     = 'nullable|string|max:500';
            $rules['utility_preference']                 = 'nullable|string|max:500';
            $rules['maintenance_preference']             = 'nullable|string|max:500';
            $rules['renewal_option_requested']           = 'nullable|string|in:,Yes,No,Negotiable';
            $rules['renewal_option_details']             = 'nullable|string';
            $rules['tenant_conditions']                  = 'nullable|string';
            $rules['additional_tenant_lease_terms']      = 'nullable|string';
            $rules['commercial_lease_type_preference']   = 'nullable|string|in:,Gross Lease,Net Lease,NNN (Triple Net),Modified Gross,Absolute Net,Percentage Lease,Negotiable';
            $rules['cam_nnn_preference']                 = 'nullable|string';
            $rules['rent_escalation_preference']         = 'nullable|string|max:500';
            $rules['buildout_tenant_improvement_request']= 'nullable|string|max:500';
            $rules['intended_business_use']              = 'nullable|string|max:500';
            $rules['signage_request']                    = 'nullable|string|max:500';
            $rules['commercial_parking_access_needs']    = 'nullable|string|max:500';
            $rules['personal_guarantee_preference']      = 'nullable|string|in:,Yes,No,Negotiable';
            $rules['commercial_approval_conditions']     = 'nullable|string';

            // Tab 4 – Pre-Screening
            $rules['number_occupant']    = 'required';
            $rules['monthly_income']     = 'required';
            $rules['screening_concerns'] = 'required';

            // Tab 7 – Tenant Information
            $rules['first_name']   = 'required|string|max:255';
            $rules['last_name']    = 'required|string|max:255';
            $rules['phone_number'] = 'required';
            $rules['email']        = 'required|email';
        }

        // Landlord-specific required fields (full_service only)
        if ($this->user_type === 'landlord' && $this->service_type === 'full_service') {
            // Tab 1 – Listing Details
            $rules['desired_agent_hire_date'] = 'required|date';
            $rules['meeting_Preference']      = 'required';

            if ($this->auction_type === 'Bidding Period') {
                $rules['auction_time'] = 'required';
            }

            // Tab 2 – Property Details
            $rules['address']         = 'required|string';
            $rules['property_city']   = 'required|string';
            $rules['property_state']  = 'required|string';
            $rules['property_county'] = 'required|string';
            $rules['property_zip']    = 'required';
            $rules['property_type']   = 'required';

            if (in_array($this->property_type, ['Residential Property', 'Residential'])) {
                $rules['bedrooms']                = 'required';
                $rules['bathrooms']               = 'required';
                $rules['minimum_heated_square']   = 'required';
            }

            // Tab 3 – Leasing Terms
            $rules['occupant_status']        = 'required';
            if (in_array($this->occupant_status ?? '', ['Tenant', 'Owner'])) {
                $rules['occupant_tenant'] = 'required|date';
            }
            $rules['leasing_spaces']         = 'required';
            $rules['desired_rental_amount']  = 'required';
            $rules['lease_amount_frequency'] = 'required';
            $rules['desired_lease_length']   = 'required|array|min:1';

            // Tab 7 – Landlord Information
            $rules['first_name']   = 'required|string|max:255';
            $rules['last_name']    = 'required|string|max:255';
            $rules['phone_number'] = 'required';
            $rules['email']        = 'required|email';
        }

        // Buyer-specific required fields (full_service only)
        if ($this->user_type === 'buyer' && $this->service_type === 'full_service') {
            // Tab 1 – Listing Details
            $rules['desired_agent_hire_date'] = 'required|date';
            $rules['meeting_Preference']      = 'required';

            if ($this->auction_type === 'Bidding Period') {
                $rules['auction_time'] = 'required';
            }

            // Tab 2 – Property Preferences
            $rules['state']         = 'required';
            $rules['property_type'] = 'required';
            $rules['counties']      = 'required|array|min:1';

            if (!empty($this->property_type)) {
                $rules['property_items'] = 'required|array|min:1';
            }
            if (in_array($this->property_type, ['Residential'])) {
                $rules['bedrooms'] = 'required';
            }
            if (in_array($this->property_type, ['Residential', 'Commercial', 'Business'])) {
                $rules['bathrooms'] = 'required';
            }
            if (in_array($this->property_type, ['Residential', 'Income'])) {
                $rules['pets'] = 'required';
            }
            if ($this->property_type === 'Business') {
                $rules['real_estate_purchase'] = 'required';
            }

            // Tab 3 – Purchasing Terms
            $rules['target_closing_date'] = 'required';
            $rules['maximum_budget']      = 'required';
            $rules['offered_financing']   = 'required|array|min:1';

            // Tab 7 – Buyer Information
            $rules['first_name']     = 'required|string|max:255';
            $rules['last_name']      = 'required|string|max:255';
            $rules['phone_number']   = 'required';
            $rules['email']          = 'required|email';
        }

        // Seller-specific required fields (full_service only)
        if ($this->user_type === 'seller' && $this->service_type === 'full_service') {
            // Tab 1 – Listing Details
            $rules['desired_agent_hire_date'] = 'required|date';
            $rules['meeting_Preference']      = 'required';

            if ($this->auction_type === 'Bidding Period') {
                $rules['auction_time'] = 'required';
            }

            // Tab 2 – Property Details
            $rules['address']         = 'required|string';
            $rules['property_city']   = 'required|string';
            $rules['property_state']  = 'required|string';
            $rules['property_county'] = 'required|string';
            $rules['property_zip']    = 'required';
            $rules['property_type']   = 'required';

            if (!empty($this->property_type)) {
                $rules['property_items'] = 'required|string';
            }

            if ($this->property_type !== 'Vacant Land') {
                $rules['condition_prop'] = 'required';
            }

            // Tab 3 – Sale Terms
            $rules['sale_provision']      = 'required|array|min:1';
            $rules['target_closing_date'] = 'required';
            $rules['maximum_budget']      = 'required';
            $rules['offered_financing']   = 'required|array|min:1';

            if ($this->property_type !== 'Vacant Land') {
                $rules['occupant_status'] = 'required';
            }

            // Tab 7 – Seller Information
            $rules['first_name']     = 'required|string|max:255';
            $rules['last_name']      = 'required|string|max:255';
            $rules['phone_number']   = 'required';
            $rules['email']          = 'required|email';
        }

        if ($this->isDraft) {
            $filledRules = [];
            foreach ($rules as $field => $rule) {
                $value = $this->$field ?? null;
                if (!empty($value)) {
                    $filledRules[$field] = $rule;
                }
            }
            $this->validate($filledRules);
        } else {
            $this->validate($rules);
        }
    }

    protected function mapLegacyPropertyConditions($conditions)
    {
        $legacyMap = [
            'Move-in ready' => 'Updated/Renovated',
            'Needs minor updates' => 'Partially Updated',
            'Needs major renovation' => 'Older but Clean',
            'Open to any condition' => 'No Preference',
            'Completely Updated: No updates needed' => 'Updated/Renovated',
            'No updates needed: Completely updated' => 'Updated/Renovated',
            'Not Updated: Requires a complete update' => 'Older but Clean',
            'Not updated: Requires a complete update' => 'Older but Clean',
            'Semi-updated: Needs minor updates' => 'Partially Updated',
            'Currently Being Built' => 'Updated/Renovated',
            'Currently being built' => 'Updated/Renovated',
            'New Construction' => 'Updated/Renovated',
            'Pre-Construction' => 'Updated/Renovated',
            'Tear Down: Requires complete demolition and reconstruction' => 'Older but Clean',
            'Open to any type of property condition' => 'No Preference',
            'Updated / Renovated' => 'Updated/Renovated',
            'Partially updated (some older finishes OK)' => 'Partially Updated',
            'Older but clean & well maintained' => 'Older but Clean',
            'No preference (open to any condition)' => 'No Preference',
        ];
        
        // Handle single value (string)
        if (is_string($conditions) && !empty($conditions)) {
            return $legacyMap[$conditions] ?? $conditions;
        }
        
        // Handle array of values
        if (!is_array($conditions)) {
            return $conditions;
        }
        
        return array_map(function($condition) use ($legacyMap) {
            return $legacyMap[$condition] ?? $condition;
        }, $conditions);
    }

    protected function stripCommas($value)
    {
        if (is_null($value) || $value === '') {
            return $value;
        }
        return str_replace(',', '', $value);
    }

    protected function normalizeOtherInArray(&$array, $otherText)
    {
        if (!is_array($array)) {
            $array = [];
        }
        $hasOther = collect($array)->contains(fn($v) => strtolower(trim((string)$v)) === 'other');
        $hasText = is_string($otherText) && trim($otherText) !== '';

        if ($hasText && !$hasOther) {
            $array[] = 'Other';
        }
        if (!$hasText && $hasOther) {
            $array = array_values(array_filter($array, fn($v) => strtolower(trim((string)$v)) !== 'other'));
        }
    }

    protected function saveAllMetadata($auction)
    {
        $this->normalizeOtherInArray($this->non_negotiable_amenities, $this->other_non_negotiable_amenities);
        $this->normalizeOtherInArray($this->lease_for, $this->other_lease_for);
        $this->normalizeOtherInArray($this->garage_parking_spaces_option, $this->other_parking_space_wrapper);
        $this->normalizeOtherInArray($this->view_preference, $this->other_preferences);
        $this->normalizeOtherInArray($this->appliances, $this->other_appliances);
        $this->normalizeOtherInArray($this->condition_prop_buyer, $this->other_property_condition);

        if (is_array($this->condition_prop_buyer)) {
            $this->condition_prop_buyer = array_values(array_unique(array_map('trim', $this->condition_prop_buyer)));
        } else {
            $fromJsonCpb = json_decode($this->condition_prop_buyer_json, true);
            $this->condition_prop_buyer = (is_array($fromJsonCpb) && !empty($fromJsonCpb))
                ? array_values(array_unique(array_map('trim', $fromJsonCpb)))
                : [];
        }

        $auction->saveMeta('workflow_type', $this->workflow_type ?: 'hire_agent');
        $auction->saveMeta('service_type', $this->service_type);
        $auction->saveMeta('user_type', $this->user_type);
        $auction->saveMeta('listing_status', $this->listing_status);
        $auction->saveMeta('auction_type', $this->auction_type);
        if (auth()->user() && auth()->user()->user_type === 'agent') {
            $auction->saveMeta('referral_percentage', $this->referral_percentage);
        }
        $auction->saveMeta('working_with_agent', $this->working_with_agent);
        $auction->saveMeta('listing_date', $this->listing_date);
        $auction->saveMeta('desired_agent_hire_date', $this->desired_agent_hire_date);
        $auction->saveMeta('expiration_date', $this->expiration_date);
        $auction->saveMeta('auction_time', $this->auction_type === 'Bidding Period' ? $this->auction_time : '');
        $auction->saveMeta('agent_bid_visibility', $this->agent_bid_visibility);
        $auction->saveMeta('meeting_Preference', $this->meeting_Preference);
        $auction->saveMeta('number_of_unit', $this->number_of_unit);

        // Location Information
        $auction->saveMeta('cities', json_encode($this->cities));
        $auction->saveMeta('counties', json_encode($this->counties));
        $auction->saveMeta('zipCodes', json_encode($this->zipCodes));
        $auction->saveMeta('state', $this->state);

        $auction->saveMeta('property_city', $this->property_city);
        $auction->saveMeta('property_state', $this->property_state);
        $auction->saveMeta('property_zip', $this->property_zip);
        $auction->saveMeta('property_county', $this->property_county);

        // Property Details
        $auction->saveMeta('property_type', $this->property_type);
        $auction->saveMeta('zip_code', $this->zip_code);

        // Check user type and set property_items accordingly
        if ($this->user_type === 'seller' || $this->user_type === 'landlord') {
            // If user type is seller, keep $this->property_items as a string
            // Ensure property_items is a string for 'seller'
            $this->property_items = is_string($this->property_items) ? $this->property_items : '';

            // Save it as a string
            $auction->saveMeta('property_items', $this->property_items);
        } else {
            // Otherwise, treat $this->property_items as JSON and decode it
            $this->property_items = is_string($this->property_items) ? json_decode($this->property_items, true) ?? [] : (array)$this->property_items;

            // Save it as a JSON string
            $auction->saveMeta('property_items', json_encode($this->property_items));
        }


        $auction->saveMeta('other_property_items', $this->other_property_items);
        $auction->saveMeta('condition_prop_buyer', json_encode($this->condition_prop_buyer));
        $auction->saveMeta('leasing_spaces_tenant', json_encode($this->leasing_spaces_tenant));
        $auction->saveMeta('tenant_pays', json_encode($this->tenant_pays));
        $auction->saveMeta('leasing_space', $this->leasing_space);
        $auction->saveMeta('restrictions', $this->restrictions);
        $auction->saveMeta('common_areas_access', $this->common_areas_access);
        $auction->saveMeta('maintenance_by', $this->maintenance_by);
        $auction->saveMeta('maintenance_response_time', $this->maintenance_response_time);
        $auction->saveMeta('storage_space', $this->storage_space);
        $auction->saveMeta('included_storage_space_com_entire', $this->included_storage_space_com_entire);
        $auction->saveMeta('storage_space_com_entire', $this->storage_space_com_entire);
        $auction->saveMeta('utilities', $this->utilities);
        $auction->saveMeta('common_areas_cleaning', $this->common_areas_cleaning);
        $auction->saveMeta('included_storage_space_com_single', $this->included_storage_space_com_single);
        $auction->saveMeta('storage_space_com_single', $this->storage_space_com_single);
        $auction->saveMeta('included_storage_space_res_both', $this->included_storage_space_res_both);
        $auction->saveMeta('storage_space_res_both', $this->storage_space_res_both);
        $auction->saveMeta('included_storage_space_res_single', $this->included_storage_space_res_single);
        $auction->saveMeta('storage_space_res_single', $this->storage_space_res_single);
        $auction->saveMeta('bathroom_facilities', $this->bathroom_facilities);
        $auction->saveMeta('room_size', $this->room_size);
        $auction->saveMeta('building_hours', $this->building_hours);
        $auction->saveMeta('access_24_7', $this->access_24_7);
        $auction->saveMeta('zoning_allows', $this->zoning_allows);
        $auction->saveMeta('shared_amenities', $this->shared_amenities);
        $auction->saveMeta('space_features', $this->space_features);
        $auction->saveMeta('neighboring_tenants', $this->neighboring_tenants);
        $auction->saveMeta('guests_allowed', $this->guests_allowed);
        $auction->saveMeta('condition_prop', $this->condition_prop);
        $auction->saveMeta('business_type', $this->business_type);
        $auction->saveMeta('business_type_selected', $this->business_type_selected);
        $auction->saveMeta('other_business_type', $this->other_business_type);
        $auction->saveMeta('other_property_condition', $this->other_property_condition);
        $auction->saveMeta('bathrooms', $this->bathrooms);
        $auction->saveMeta('other_bathrooms', $this->other_bathrooms);
        $auction->saveMeta('bedrooms', $this->bedrooms);
        $auction->saveMeta('other_bedrooms', $this->other_bedrooms);
        $auction->saveMeta('minimum_heated_square', $this->stripCommas($this->minimum_heated_square));
        $auction->saveMeta('minimum_leaseable', $this->stripCommas($this->minimum_leaseable));
        $auction->saveMeta('min_acreage', $this->stripCommas($this->min_acreage));
        $auction->saveMeta('total_acreage', $this->stripCommas($this->total_acreage));
        // Amenities and Features
        $auction->saveMeta('other_tenant_pays', $this->other_tenant_pays);
        $auction->saveMeta('tenant_require', json_encode($this->tenant_require));

        $auction->saveMeta('desired_lease_length', json_encode($this->desired_lease_length));
        $auction->saveMeta('other_lease_term', $this->other_lease_term);
        $auction->saveMeta('rent_includes', json_encode($this->rent_includes));
        $auction->saveMeta('other_rent_include', $this->other_rent_include);


        $auction->saveMeta('owner_pays', json_encode($this->owner_pays));
        $auction->saveMeta('other_owner_pays', $this->other_owner_pays);
        $auction->saveMeta('owner_pays_other', $this->owner_pays_other);

        $auction->saveMeta('terms_of_lease', json_encode($this->terms_of_lease));
        $auction->saveMeta('custom_lease_term', $this->custom_lease_term);


        $auction->saveMeta('garage_parking_spaces_option_buyer', json_encode($this->garage_parking_spaces_option_buyer));
        $auction->saveMeta('carport_needed', $this->carport_needed);
        $auction->saveMeta('other_carport_needed', $this->other_carport_needed);
        $auction->saveMeta('total_square_feet', $this->stripCommas($this->total_square_feet));
        $auction->saveMeta('sqft_heated_source', $this->sqft_heated_source);

        $auction->saveMeta('garage_needed', $this->garage_needed);
        $auction->saveMeta('other_garage_needed', $this->other_garage_needed);
        $auction->saveMeta('garage_parking_spaces', $this->garage_parking_spaces);
        $auction->saveMeta('garage_parking_spaces_option',  json_encode($this->garage_parking_spaces_option));
        $auction->saveMeta('other_parking_space_wrapper', $this->other_parking_space_wrapper);
        if (in_array($this->property_type ?? '', ['Residential Property', 'Residential', 'Income', 'Income Property'])) {
            $auction->saveMeta('pool_needed', $this->pool_needed);
            $auction->saveMeta('pool_type', json_encode($this->pool_type));
        } else {
            $auction->saveMeta('pool_needed', null);
            $auction->saveMeta('pool_type', json_encode([]));
        }
        $auction->saveMeta('view_preference', json_encode($this->view_preference));
        $auction->saveMeta('other_preferences', $this->other_preferences);
        $auction->saveMeta('appliances', json_encode($this->appliances));
        $auction->saveMeta('other_appliances', $this->other_appliances);
        $auction->saveMeta('leasing_55_plus', $this->leasing_55_plus);

        // Requirements
        $auction->saveMeta('non_negotiable_amenities', json_encode($this->non_negotiable_amenities));
        $auction->saveMeta('other_non_negotiable_amenities', $this->other_non_negotiable_amenities);
        $auction->saveMeta('real_estate_purchase', $this->real_estate_purchase);
        $auction->saveMeta('assets', $this->assets);
        $auction->saveMeta('assets_other', $this->assets_other);
        $auction->saveMeta('business_assets', json_encode(is_array($this->business_assets) ? $this->business_assets : []));
        $auction->saveMeta('property_criteria', $this->property_criteria);
        $auction->saveMeta('unit_size', $this->unit_size);
        $auction->saveMeta('unit_size_other', $this->unit_size_other);
        $auction->saveMeta('budget', $this->budget);
        $auction->saveMeta('occupied_until', $this->occupied_until);
        $auction->saveMeta('occupancy_status', $this->occupancy_status);

        // Lease Terms
        $auction->saveMeta('lease_for', json_encode($this->lease_for));
        $auction->saveMeta('other_lease_for', $this->other_lease_for);
        $auction->saveMeta('lease_by', $this->lease_by);
        $auction->saveMeta('lease_date', $this->lease_date);

        // Tenant Leasing Terms — 23 new optional fields
        $auction->saveMeta('tenant_desired_lease_length', $this->tenant_desired_lease_length);
        $auction->saveMeta('security_deposit_budget', $this->stripCommas($this->security_deposit_budget));
        $auction->saveMeta('move_in_funds_available', $this->stripCommas($this->move_in_funds_available));
        $auction->saveMeta('first_month_rent_available', $this->first_month_rent_available);
        $auction->saveMeta('last_month_rent_available', $this->last_month_rent_available);
        $auction->saveMeta('pet_information', $this->pet_information);
        $auction->saveMeta('number_of_occupants', $this->stripCommas($this->number_of_occupants));
        $auction->saveMeta('parking_needed', $this->parking_needed);
        $auction->saveMeta('utility_preference', $this->utility_preference);
        $auction->saveMeta('maintenance_preference', $this->maintenance_preference);
        $auction->saveMeta('renewal_option_requested', $this->renewal_option_requested);
        $auction->saveMeta('renewal_option_details', $this->renewal_option_details);
        $auction->saveMeta('tenant_conditions', $this->tenant_conditions);
        $auction->saveMeta('additional_tenant_lease_terms', $this->additional_tenant_lease_terms);
        $auction->saveMeta('commercial_lease_type_preference', $this->commercial_lease_type_preference);
        $auction->saveMeta('cam_nnn_preference', $this->cam_nnn_preference);
        $auction->saveMeta('rent_escalation_preference', $this->rent_escalation_preference);
        $auction->saveMeta('buildout_tenant_improvement_request', $this->buildout_tenant_improvement_request);
        $auction->saveMeta('intended_business_use', $this->intended_business_use);
        $auction->saveMeta('signage_request', $this->signage_request);
        $auction->saveMeta('commercial_parking_access_needs', $this->commercial_parking_access_needs);
        $auction->saveMeta('personal_guarantee_preference', $this->personal_guarantee_preference);
        $auction->saveMeta('commercial_approval_conditions', $this->commercial_approval_conditions);

        // Buyer Purchasing Terms — 18 new optional fields (Task #155)
        $auction->saveMeta('earnest_money_amount', $this->stripCommas($this->earnest_money_amount));
        $auction->saveMeta('earnest_money_timing', $this->earnest_money_timing);
        $auction->saveMeta('inspection_period_days', $this->inspection_period_days);
        $auction->saveMeta('inspection_contingency_buyer', $this->inspection_contingency_buyer);
        $auction->saveMeta('appraisal_contingency_buyer', $this->appraisal_contingency_buyer);
        $auction->saveMeta('financing_contingency_buyer', $this->financing_contingency_buyer);
        $auction->saveMeta('financing_contingency_days_buyer', $this->financing_contingency_days_buyer);
        $auction->saveMeta('seller_contribution', $this->seller_contribution);
        $auction->saveMeta('seller_contribution_details', $this->seller_contribution_details);
        $auction->saveMeta('possession_preference', $this->possession_preference);
        $auction->saveMeta('possession_details', $this->possession_details);
        $auction->saveMeta('home_warranty_requested', $this->home_warranty_requested);
        $auction->saveMeta('home_warranty_details', $this->home_warranty_details);
        $auction->saveMeta('as_is_purchase', $this->as_is_purchase);
        $auction->saveMeta('property_inclusions', $this->property_inclusions);
        $auction->saveMeta('property_exclusions', $this->property_exclusions);
        $auction->saveMeta('closing_cost_responsibility', $this->closing_cost_responsibility);
        $auction->saveMeta('additional_purchase_terms', $this->additional_purchase_terms);

        // Landlord Lease Terms — 22 new optional fields (Task #157)
        $auction->saveMeta('lease_available_date', $this->lease_available_date);
        $auction->saveMeta('security_deposit_required', $this->stripCommas($this->security_deposit_required));
        $auction->saveMeta('first_month_rent_required', $this->first_month_rent_required);
        $auction->saveMeta('last_month_rent_required', $this->last_month_rent_required);
        $auction->saveMeta('total_move_in_funds_required', $this->stripCommas($this->total_move_in_funds_required));
        $auction->saveMeta('pet_policy', $this->pet_policy);
        $auction->saveMeta('pet_deposit_fee_rent', $this->pet_deposit_fee_rent);
        $auction->saveMeta('number_of_occupants_allowed', $this->number_of_occupants_allowed);
        $auction->saveMeta('parking_terms', $this->parking_terms);
        $auction->saveMeta('utility_responsibility', $this->utility_responsibility);
        $auction->saveMeta('ll_maintenance_responsibility', $this->ll_maintenance_responsibility);
        $auction->saveMeta('renewal_option_offered', $this->renewal_option_offered);
        $auction->saveMeta('landlord_approval_conditions', $this->landlord_approval_conditions);
        $auction->saveMeta('additional_landlord_lease_terms', $this->additional_landlord_lease_terms);
        $auction->saveMeta('commercial_lease_type', $this->commercial_lease_type);
        $auction->saveMeta('cam_nnn_additional_rent_charges', $this->cam_nnn_additional_rent_charges);
        $auction->saveMeta('rent_escalation_terms', $this->rent_escalation_terms);
        $auction->saveMeta('tenant_improvement_buildout_terms', $this->tenant_improvement_buildout_terms);
        $auction->saveMeta('permitted_use_restrictions', $this->permitted_use_restrictions);
        $auction->saveMeta('signage_rights', $this->signage_rights);
        $auction->saveMeta('commercial_parking_terms', $this->commercial_parking_terms);
        $auction->saveMeta('personal_guarantee_requirement', $this->personal_guarantee_requirement);

        // Tenant Information
        $auction->saveMeta('pets', $this->pets);
        $auction->saveMeta('number_of_pets', $this->number_of_pets);
        $auction->saveMeta('breed_of_pets', $this->breed_of_pets);
        $auction->saveMeta('type_of_pets', $this->type_of_pets);
        $auction->saveMeta('weight_of_pets', $this->weight_of_pets);
        $auction->saveMeta('service_animal', $this->service_animal);
        $auction->saveMeta('other_services_enabled', $this->other_services_enabled);
        $auction->saveMeta('support_animal', $this->support_animal);
        $auction->saveMeta('emotional_support_animal', $this->emotional_support_animal);
        $auction->saveMeta('has_breed_restrictions', $this->has_breed_restrictions);
        $auction->saveMeta('breed_restrictions', $this->breed_restrictions);
        $auction->saveMeta('minimum_annual_net_income', $this->stripCommas($this->minimum_annual_net_income));
        $auction->saveMeta('minimum_cap_rate', $this->minimum_cap_rate);
        $auction->saveMeta('number_of_unit_type', json_encode($this->number_of_unit_type));
        $auction->saveMeta('unit_number', $this->unit_number);
        $auction->saveMeta('unit_buildings', $this->unit_buildings);
        $configs = is_array($this->unit_type_configurations) ? $this->unit_type_configurations : [];
        $cleanedConfigs = array_map(function ($config) {
            if (isset($config['expected_rent'])) {
                $config['expected_rent'] = $this->stripCommas($config['expected_rent']);
            }
            return $config;
        }, $configs);
        $auction->saveMeta('unit_type_configurations', json_encode(array_values($cleanedConfigs)));
        $auction->saveMeta('screening_concerns', $this->screening_concerns);
        $auction->saveMeta('screening_concerns_explanation', $this->screening_concerns_explanation);
        $auction->saveMeta('credit_scroe_rating', json_encode($this->credit_scroe_rating));
        $auction->saveMeta('preferance_details', $this->preferance_details);


        /// Buyer purchasing terms
        $auction->saveMeta('sale_provision', json_encode($this->sale_provision));
        $auction->saveMeta('sale_provision_other', $this->sale_provision_other);
        $auction->saveMeta('sale_provision_assignment', $this->sale_provision_assignment);
        $auction->saveMeta('assignment_fee_type', $this->assignment_fee_type);
        $auction->saveMeta('assignment_fee_amount', $this->stripCommas($this->assignment_fee_amount));
        $auction->saveMeta('target_closing_date', $this->target_closing_date);
        $auction->saveMeta('occupant_status', $this->occupant_status);
        $auction->saveMeta('leasing_spaces', $this->leasing_spaces);
        $auction->saveMeta('occupant_tenant', $this->occupant_tenant);
        $auction->saveMeta('desired_rental_amount', $this->stripCommas($this->desired_rental_amount));
        $auction->saveMeta('lease_amount_frequency', $this->lease_amount_frequency);
        $auction->saveMeta('maximum_budget', $this->stripCommas($this->maximum_budget));
        $auction->saveMeta('offered_financing', json_encode($this->offered_financing));
        $auction->saveMeta('other_financing', $this->other_financing);
        $auction->saveMeta('cash_budget', $this->stripCommas($this->cash_budget));
        $auction->saveMeta('pre_approved', $this->pre_approved);
        $auction->saveMeta('pre_approval_amount', $this->stripCommas($this->pre_approval_amount));
        $auction->saveMeta('purchase_price', $this->stripCommas($this->purchase_price));
        $auction->saveMeta('down_payment_type', $this->down_payment_type);
        $auction->saveMeta('down_payment_amount', $this->stripCommas($this->down_payment_amount));
        $auction->saveMeta('seller_down_payment_amount', $this->stripCommas($this->seller_down_payment_amount));
        $auction->saveMeta('seller_financing_type', $this->seller_financing_type);
        $auction->saveMeta('seller_financing_amount', $this->stripCommas($this->seller_financing_amount));
        $auction->saveMeta('interest_rate', $this->interest_rate);
        $auction->saveMeta('loan_duration', $this->loan_duration);
        $auction->saveMeta('prepayment_penalty', $this->prepayment_penalty);
        $auction->saveMeta('prepayment_penalty_amount', $this->stripCommas($this->prepayment_penalty_amount));
        $auction->saveMeta('balloon_payment', $this->balloon_payment);
        $auction->saveMeta('balloon_payment_amount', $this->stripCommas($this->balloon_payment_amount));
        $auction->saveMeta('balloon_payment_date', $this->balloon_payment_date);
        $auction->saveMeta('assumable_terms', $this->assumable_terms);
        $auction->saveMeta('max_assumable_rate', $this->max_assumable_rate);
        $auction->saveMeta('assumable_loan_type', $this->assumable_loan_type);
        $auction->saveMeta('assumable_monthly_escrow', $this->stripCommas($this->assumable_monthly_escrow));
        $auction->saveMeta('assumable_loan_term_remaining', $this->assumable_loan_term_remaining);
        $auction->saveMeta('assumable_loan_origination_date', $this->assumable_loan_origination_date);
        $auction->saveMeta('assumable_loan_servicer', $this->assumable_loan_servicer);
        $auction->saveMeta('assumable_fee_type', $this->assumable_fee_type);
        $auction->saveMeta('assumable_fee_amount', $this->stripCommas($this->assumable_fee_amount));
        $auction->saveMeta('assumable_occupancy_requirement', $this->assumable_occupancy_requirement);
        $auction->saveMeta('assumable_occupancy_other', $this->assumable_occupancy_other);
        $auction->saveMeta('max_monthly_payment', $this->max_monthly_payment);
        $auction->saveMeta('outstanding_balance', $this->outstanding_balance);
        $auction->saveMeta('gap_payment_type', $this->gap_payment_type);
        $auction->saveMeta('gap_payment_amount', $this->stripCommas($this->gap_payment_amount));
        $exchangeItemVal = is_array($this->exchange_item) ? array_values(array_filter((array) $this->exchange_item)) : (is_string($this->exchange_item) && trim($this->exchange_item) !== '' ? [$this->exchange_item] : []);
        $auction->saveMeta('exchange_item', json_encode($exchangeItemVal));
        $auction->saveMeta('other_exchange_item', $this->other_exchange_item);
        $auction->saveMeta('exchange_item_value', $this->exchange_item_value);
        $auction->saveMeta('exchange_item_condition', $this->exchange_item_condition);
        $auction->saveMeta('additional_cash', $this->additional_cash);
        $auction->saveMeta('value_determination', $this->value_determination);
        $auction->saveMeta('lease_option_price', $this->stripCommas($this->lease_option_price));
        $auction->saveMeta('lease_option_terms', $this->lease_option_terms);
        $auction->saveMeta('lease_option_duration', $this->lease_option_duration);
        $auction->saveMeta('lease_option_payment', $this->lease_option_payment);
        $auction->saveMeta('lease_option_conditions', $this->lease_option_conditions);
        $auction->saveMeta('has_option_fee', $this->has_option_fee);
        $auction->saveMeta('option_fee_amount', $this->stripCommas($this->option_fee_amount));
        $auction->saveMeta('lease_purchase_price', $this->stripCommas($this->lease_purchase_price));
        $auction->saveMeta('lease_purchase_terms', $this->lease_purchase_terms);
        $auction->saveMeta('lease_purchase_duration', $this->lease_purchase_duration);
        $auction->saveMeta('lease_purchase_payment', $this->lease_purchase_payment);
        $auction->saveMeta('lease_purchase_conditions', $this->lease_purchase_conditions);
        $auction->saveMeta('lease_purchase_option_fee', $this->lease_purchase_option_fee);
        $auction->saveMeta('lease_purchase_option_fee_amount', $this->stripCommas($this->lease_purchase_option_fee_amount));
        $auction->saveMeta('cryptocurrency_type', $this->cryptocurrency_type);
        $auction->saveMeta('crypto_percentage', $this->crypto_percentage);
        $auction->saveMeta('cash_percentage_crypto', $this->cash_percentage_crypto);
        $auction->saveMeta('nft_description', $this->nft_description);
        $auction->saveMeta('nft_percentage', $this->nft_percentage);
        $auction->saveMeta('cash_percentage_nft', $this->cash_percentage_nft);

        // Seller Financing follow-up fields
        $auction->saveMeta('seller_amortization_type', $this->seller_amortization_type);
        $auction->saveMeta('seller_amortization_other', $this->seller_amortization_other);
        $auction->saveMeta('seller_payment_frequency', $this->seller_payment_frequency);
        $auction->saveMeta('seller_payment_frequency_other', $this->seller_payment_frequency_other);
        $auction->saveMeta('seller_late_fee_amount', $this->stripCommas($this->seller_late_fee_amount));

        // Lease Option follow-up fields
        $auction->saveMeta('lease_option_maintenance', $this->lease_option_maintenance);
        $auction->saveMeta('lease_option_extension_terms', $this->lease_option_extension_terms);
        $auction->saveMeta('lease_option_fee_credit', $this->lease_option_fee_credit);
        $auction->saveMeta('lease_option_fee_credit_percentage', $this->lease_option_fee_credit_percentage);

        // Lease Purchase follow-up fields
        $auction->saveMeta('lease_purchase_maintenance', $this->lease_purchase_maintenance);
        $auction->saveMeta('lease_purchase_extension_terms', $this->lease_purchase_extension_terms);
        $auction->saveMeta('lease_purchase_rent_credit', $this->lease_purchase_rent_credit);
        $auction->saveMeta('lease_purchase_rent_credit_amount', $this->stripCommas($this->lease_purchase_rent_credit_amount));
        $auction->saveMeta('lease_purchase_deposit', $this->lease_purchase_deposit);

        // Cryptocurrency follow-up fields
        $auction->saveMeta('crypto_exchange_method', $this->crypto_exchange_method);
        $auction->saveMeta('crypto_custodian_wallet', $this->crypto_custodian_wallet);
        $auction->saveMeta('crypto_transaction_fees', $this->crypto_transaction_fees);
        $auction->saveMeta('crypto_transfer_timing', $this->crypto_transfer_timing);
        $auction->saveMeta('crypto_transfer_timing_other', $this->crypto_transfer_timing_other);

        // NFT follow-up fields
        $auction->saveMeta('nft_valuation_method', $this->nft_valuation_method);
        $auction->saveMeta('nft_transfer_method', $this->nft_transfer_method);
        $auction->saveMeta('nft_gas_fees', $this->nft_gas_fees);

        // Exchange/Trade follow-up fields
        $auction->saveMeta('exchange_transfer_method', $this->exchange_transfer_method);
        $auction->saveMeta('exchange_liens', $this->exchange_liens);
        $auction->saveMeta('exchange_liens_details', $this->exchange_liens_details);
        $auction->saveMeta('exchange_inspection_rights', $this->exchange_inspection_rights);

        /// Buyer purchasing terms end

        $auction->saveMeta('prior_eviction', $this->prior_eviction);
        $auction->saveMeta('eviction_explanation', $this->eviction_explanation);
        $auction->saveMeta('prior_felony', $this->prior_felony);
        $auction->saveMeta('prior_felony_explanation', $this->prior_felony_explanation);
        $auction->saveMeta('monthly_income', $this->monthly_income);
        $auction->saveMeta('number_occupant', $this->number_occupant);

        // Services
        $auction->saveMeta('services', json_encode($this->services));
        $auction->saveMeta('other_services', json_encode($this->other_services));
        $auction->saveMeta('flat_fee_services', json_encode($this->flat_fee_services));
        $auction->saveMeta('photo_enhancements', json_encode($this->photo_enhancements));
        $auction->saveMeta('custom_enhancement', $this->custom_enhancement);
        
        // Build and save services snapshot for ordered display
        $servicesSnapshot = TenantServicesCatalog::buildSnapshot(
            is_array($this->services) ? $this->services : [],
            $this->property_type ?? 'Residential Property'
        );
        $auction->saveMeta('services_snapshot', json_encode($servicesSnapshot));
        $auction->saveMeta('additional_details', $this->additional_details);

        // Broker Compensation
        $auction->saveMeta('commission_structure', $this->commission_structure);

        $auction->saveMeta('commission_structure_type', $this->commission_structure_type);
        $auction->saveMeta('commission_structure_type_fee_flat', $this->stripCommas($this->commission_structure_type_fee_flat));


        $auction->saveMeta('commission_structure_type_fee_percentage', $this->commission_structure_type_fee_percentage);
        $auction->saveMeta('commission_structure_type_fee_percentage_combo', $this->commission_structure_type_fee_percentage_combo);
        $auction->saveMeta('commission_structure_type_fee_flat_combo', $this->stripCommas($this->commission_structure_type_fee_flat_combo));
        $auction->saveMeta('commission_structure_type_fee_other', $this->commission_structure_type_fee_other);

        // Lease Fee
        $auction->saveMeta('lease_fee_type', $this->lease_fee_type);
        $auction->saveMeta('lease_fee_flat_type', $this->lease_fee_flat_type);
        $auction->saveMeta('lease_fee_flat', $this->stripCommas($this->lease_fee_flat));
        $auction->saveMeta('lease_fee_percentage', $this->lease_fee_percentage);
        $auction->saveMeta('lease_fee_months', $this->lease_fee_months);
        $auction->saveMeta('lease_fee_percentage_monthly_rent', $this->lease_fee_percentage_monthly_rent);
        $auction->saveMeta('lease_fee_percentage_monthly_number', $this->lease_fee_percentage_monthly_number);
        $auction->saveMeta('lease_fee_percentage_net', $this->lease_fee_percentage_net);
        $auction->saveMeta('lease_fee_flat_combo_net', $this->stripCommas($this->lease_fee_flat_combo_net));
        $auction->saveMeta('lease_fee_percentage_combo_net', $this->lease_fee_percentage_combo_net);
        $auction->saveMeta('lease_fee_flat_combo', $this->stripCommas($this->lease_fee_flat_combo));
        $auction->saveMeta('lease_fee_percentage_combo', $this->lease_fee_percentage_combo);
        $auction->saveMeta('lease_fee_other', $this->lease_fee_other);
        $auction->saveMeta('interested_purchase_fee_type', $this->interested_purchase_fee_type);




        $auction->saveMeta('seller_leasing_fee_type', $this->seller_leasing_fee_type);
        $auction->saveMeta('seller_leasing_gross', $this->seller_leasing_gross);
        $auction->saveMeta('seller_leasing_gross_month_rent', $this->seller_leasing_gross_month_rent);
        $auction->saveMeta('seller_leasing_gross_rental', $this->seller_leasing_gross_rental);
        $auction->saveMeta('seller_broker_leasing_fee', $this->seller_broker_leasing_fee);
        $auction->saveMeta('seller_leasing_each_rental', $this->seller_leasing_each_rental);
        $auction->saveMeta('seller_leasing_gross_no_of_months', $this->seller_leasing_gross_no_of_months);


        $auction->saveMeta('purchase_fee_flat_commercial', $this->stripCommas($this->purchase_fee_flat_commercial));


        $auction->saveMeta('seller_leasing_gross_flat_combo', $this->seller_leasing_gross_flat_combo);
        $auction->saveMeta('seller_leasing_gross_percentage_combo', $this->seller_leasing_gross_percentage_combo);
        $auction->saveMeta('seller_leasing_gross_flat_net_combo', $this->seller_leasing_gross_flat_net_combo);
        $auction->saveMeta('seller_leasing_gross_percentage_net_combo', $this->seller_leasing_gross_percentage_net_combo);

        // Interested in Selling
        $auction->saveMeta('interested_in_selling', $this->interested_in_selling);
        $auction->saveMeta('interested_in_selling_type', $this->interested_in_selling_type);
        $auction->saveMeta('landlord_broker_purchase_price', $this->stripCommas($this->landlord_broker_purchase_price));
        $auction->saveMeta('landlord_broker_percentage_price', $this->stripCommas($this->landlord_broker_percentage_price));
        $auction->saveMeta('landlord_broker_dollar_price', $this->stripCommas($this->landlord_broker_dollar_price));
        $auction->saveMeta('landlord_broker_flate_fee', $this->landlord_broker_flate_fee);
        $auction->saveMeta('landlord_broker_other', $this->landlord_broker_other);

        //  Payment Timing for Broker Fees:
        $auction->saveMeta('broker_fee_timing', $this->broker_fee_timing);
        $auction->saveMeta('broker_fee_days_from_rent', $this->broker_fee_days_from_rent);
        $auction->saveMeta('broker_fee_days_after_lease', $this->broker_fee_days_after_lease);
        $auction->saveMeta('broker_fee_days_after_rent', $this->broker_fee_days_after_rent);
        $auction->saveMeta('split_payment_due', $this->split_payment_due);
        $auction->saveMeta('broker_fee_timing_other', $this->broker_fee_timing_other);
        $auction->saveMeta('broker_fee_days_after_due_event', $this->broker_fee_days_after_due_event);
        $auction->saveMeta('split_payment_due_other', $this->split_payment_due_other);



        $auction->saveMeta('renewal_fee_type', $this->renewal_fee_type);
        $auction->saveMeta('renewal_fee_percentage', $this->renewal_fee_percentage);
        $auction->saveMeta('renewal_fee_lease_value', $this->renewal_fee_lease_value);
        $auction->saveMeta('renewal_fee_first_month', $this->renewal_fee_first_month);
        $auction->saveMeta('renewal_fee_flat_free', $this->stripCommas($this->renewal_fee_flat_free));
        $auction->saveMeta('renewal_fee_custom', $this->renewal_fee_custom);

        $auction->saveMeta('renewal_fee_sales_tax_lease_value', $this->renewal_fee_sales_tax_lease_value);
        $auction->saveMeta('renewal_fee_sales_tax_flat_fee', $this->renewal_fee_sales_tax_flat_fee);
        $auction->saveMeta('renewal_fee_sales_tax_first_month', $this->renewal_fee_sales_tax_first_month);
        $auction->saveMeta('renewal_fee_no_of_months', $this->renewal_fee_no_of_months);
        $auction->saveMeta('expansion_commission_percentage', $this->expansion_commission_percentage);


        $auction->saveMeta('tenant_broker_commission_structure', $this->tenant_broker_commission_structure);
        $auction->saveMeta('tenant_broker_fee_structure', $this->tenant_broker_fee_structure);
        $auction->saveMeta('tenant_broker_percentage', $this->tenant_broker_percentage);
        $auction->saveMeta('tenant_broker_gross_lease', $this->tenant_broker_gross_lease);
        $auction->saveMeta('tenant_broker_first_month_rent', $this->tenant_broker_first_month_rent);
        $auction->saveMeta('tenant_broker_flat_fee', $this->tenant_broker_flat_fee);
        $auction->saveMeta('tenant_broker_other', $this->tenant_broker_other);


        $auction->saveMeta('seller_leasing_gross_sales_tax_option_gross', $this->seller_leasing_gross_sales_tax_option_gross);
        $auction->saveMeta('seller_leasing_gross_ross_percentage_rent', $this->seller_leasing_gross_ross_percentage_rent);
        $auction->saveMeta('seller_leasing_gross_sales_tax_flat_free_gross', $this->seller_leasing_gross_sales_tax_flat_free_gross);
        $auction->saveMeta('seller_leasing_gross_sales_tax_first_month', $this->seller_leasing_gross_sales_tax_first_month);
        $auction->saveMeta('seller_leasing_gross_percentage_no_of_months', $this->seller_leasing_gross_percentage_no_of_months);
        $auction->saveMeta('seller_leasing_gross_purchase_fee_flat_amount', $this->stripCommas($this->seller_leasing_gross_purchase_fee_flat_amount));
        $auction->saveMeta('seller_leasing_gross_purchase_fee_other', $this->seller_leasing_gross_purchase_fee_other);
        $auction->saveMeta('seller_leasing_gross_other', $this->seller_leasing_gross_other);

        $auction->saveMeta('interested_in_property_management', $this->interested_in_property_management);
        $auction->saveMeta('interested_in_property_management_fee', $this->interested_in_property_management_fee);
        $auction->saveMeta('interested_in_property_management_fee_gross_lease', $this->interested_in_property_management_fee_gross_lease);
        $auction->saveMeta('interested_in_property_management_fee_rental_periord', $this->interested_in_property_management_fee_rental_periord);
        $auction->saveMeta('interested_in_property_management_fee_flate_free', $this->stripCommas($this->interested_in_property_management_fee_flate_free));
        $auction->saveMeta('interested_in_property_management_fee_other', $this->interested_in_property_management_fee_other);

        // Purchase Fee
        $auction->saveMeta('purchase_fee_type', $this->purchase_fee_type);
        $auction->saveMeta('purchase_fee_gross_rent', $this->purchase_fee_gross_rent);
        $auction->saveMeta('sales_tax_option_gross', $this->sales_tax_option_gross);
        $auction->saveMeta('sales_tax_option_flat', $this->sales_tax_option_flat);

        $auction->saveMeta('purchase_fee_net_aggregate', $this->purchase_fee_net_aggregate);
        $auction->saveMeta('purchase_fee_monthly_percentage', $this->purchase_fee_monthly_percentage);
        $auction->saveMeta('purchase_fee_months', $this->purchase_fee_months);
        $auction->saveMeta('purchase_fee_percentage', $this->purchase_fee_percentage);
        $auction->saveMeta('purchase_fee_flat', $this->stripCommas($this->purchase_fee_flat));
        $auction->saveMeta('purchase_fee_flat_type', $this->purchase_fee_flat_type);
        $auction->saveMeta('purchase_fee_rental_period', $this->purchase_fee_rental_period);
        $auction->saveMeta('purchase_fee_percentage_combo', $this->purchase_fee_percentage_combo);
        $auction->saveMeta('purchase_fee_flat_combo', $this->stripCommas($this->purchase_fee_flat_combo));
        $auction->saveMeta('purchase_fee_other', $this->purchase_fee_other);
        $auction->saveMeta('purchase_fee_other_commercial', $this->purchase_fee_other_commercial);
        $auction->saveMeta('purchase_fee_purchase_price', $this->purchase_fee_purchase_price);
        $auction->saveMeta('sales_tax_option_monthly', $this->sales_tax_option_monthly);
        $auction->saveMeta('nominal', $this->stripCommas($this->nominal));
        $auction->saveMeta('interested_lease_option', $this->interested_lease_option);
        $auction->saveMeta('interested_lease_option_agreement', $this->interested_lease_option_agreement);

        $auction->saveMeta('landlord_broker_flate_fee_type', $this->landlord_broker_flate_fee_type);

        $auction->saveMeta('lease_type', $this->lease_type);
        $auction->saveMeta('lease_value', str_replace(',', '', $this->lease_value ?? ''));
        $auction->saveMeta('purchase_type', $this->purchase_type);
        $auction->saveMeta('purchase_value', str_replace(',', '', $this->purchase_value ?? ''));

        // Lease-Option Fee
        $auction->saveMeta('lease_option_fee_type', $this->lease_option_fee_type);
        $auction->saveMeta('lease_option_fee_flat', $this->lease_option_fee_flat);
        $auction->saveMeta('lease_option_fee_percentage', $this->lease_option_fee_percentage);
        $auction->saveMeta('lease_option_fee_other', $this->lease_option_fee_other);

        // Other Broker Terms
        $auction->saveMeta('protection_period', $this->protection_period);
        $auction->saveMeta('early_termination_fee_option', $this->early_termination_fee_option);
        $auction->saveMeta('early_termination_fee_amount', $this->early_termination_fee_amount);
        $auction->saveMeta('retainer_fee_option', $this->retainer_fee_option);
        $auction->saveMeta('retainer_fee_amount', $this->retainer_fee_amount);
        $auction->saveMeta('retainer_fee_application', $this->retainer_fee_application);
        $auction->saveMeta('agency_agreement_timeframe', $this->agency_agreement_timeframe);
        $auction->saveMeta('retained_deposits', $this->retained_deposits);
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
        if (auth()->user() && auth()->user()->user_type === 'agent') {
            $auction->saveMeta('agent_brokerage', $this->agent_brokerage);
            $auction->saveMeta('agent_license_number', $this->agent_license_number);
            $auction->saveMeta('agent_nar_member_id', $this->agent_nar_member_id);
        }
        $auction->saveMeta('current_status', $this->current_status);
        $auction->saveMeta('video_link', $this->video_link);

        // Save photo - handle new uploads, existing paths, and deletions
        if ($this->photoDeleted) {
            $auction->deleteMeta('photo');
            $this->photoDeleted = false;
        } elseif ($this->photo && !is_string($this->photo)) {
            $extensionPhoto = $this->photo->getClientOriginalExtension();
            $uuid = (string) Str::uuid();
            $photoName = $uuid . '.' . $extensionPhoto;
            $photoPath = $this->photo->storeAs('auction/images', $photoName, 'public');
            $auction->saveMeta('photo', $photoName);
            $this->photo = $photoName;
        } elseif ($this->photo && is_string($this->photo)) {
            $auction->saveMeta('photo', $this->photo);
        }

        // Save video - handle new uploads, existing paths, and deletions
        if ($this->videoDeleted) {
            $auction->deleteMeta('video');
            $this->videoDeleted = false;
        } elseif ($this->video && !is_string($this->video)) {
            $extensionVideo = $this->video->getClientOriginalExtension();
            $uuid = (string) Str::uuid();
            $videoName = $uuid . '.' . $extensionVideo;
            $videoPath = $this->video->storeAs('auction/videos', $videoName, 'public');
            $auction->saveMeta('video', $videoName);
            $this->video = $videoName;
        }
    }

    public function store()
    {
        \Log::info('[STORE METHOD CALLED]', [
            'user_type' => $this->user_type,
            'listing_title' => $this->listing_title ?? 'N/A',
            'counties' => $this->counties ?? [],
            'timestamp' => now()->toDateTimeString(),
        ]);
        
        try {

            $this->isDraft = 0;
            
            \Log::info('[STORE] About to validate', ['user_type' => $this->user_type]);
            
            $this->validateOnlyFilledFields();
            
            \Log::info('[STORE] Validation passed', ['user_type' => $this->user_type]);

            // $auction = $this->listingId
            //     ? HireTenantAgentAuction::find($this->listingId)
            //     : new HireTenantAgentAuction();


            // Map user_type to model class
            $auctionClass = match ($this->user_type) {
                'tenant'   => HireTenantAgentAuction::class,
                'landlord' => HireLandLordAgentAuction::class,
                'buyer'    => HireBuyerAgentAuction::class,
                'seller'   => HireSellerAgentAuction::class,
                default    => null,
            };

            if (!$auctionClass) {
                throw new \Exception("Invalid user_type: {$this->user_type}");
            }

            $auction = $this->listingId
                ? $auctionClass::find($this->listingId)
                : null;
            
            // If listingId was set but record not found, create new instance
            if (!$auction) {
                $auction = new $auctionClass();
            }
            
            $auction->user_id = Auth::id();
            $auction->title = $this->listing_title;
            $auction->is_draft = 0;
            $auction->is_approved = true;

            // Set required fields for buyer/seller types that have NOT NULL constraints
            if ($this->user_type === 'buyer' || $this->user_type === 'seller') {
                $auction->address = $this->address ?? 'N/A';
                if (!$auction->exists || $auction->is_sold === null) {
                    $auction->is_sold = 'false';
                }
                if (!$auction->exists || $auction->is_paid === null) {
                    $auction->is_paid = 'false';
                }
            }
            
            $auction->save();

            // Phase 6 — persist referral attribution on brand-new listing rows.
            \App\Services\ReferralLinkService::persistListingReferral($auction);

            // ── Regression guard ──────────────────────────────────────────────
            // The search query binds PHP `true` as integer 1, which matches the
            // varchar value '1'. If is_approved was ever written as the literal
            // string 'true' (or any other non-'1' truthy form) it would silently
            // disappear from search results. Re-read the stored value immediately
            // after save and emit a warning log if it is not the expected '1'.
            $storedIsApproved = \Illuminate\Support\Facades\DB::table($auction->getTable())
                ->where('id', $auction->id)
                ->value('is_approved');
            if ($storedIsApproved !== '1' && $storedIsApproved !== 1) {
                \Illuminate\Support\Facades\Log::warning(
                    '[TENANT LISTING SEARCH-VISIBILITY REGRESSION] is_approved stored as unexpected value; listing may be invisible on /search/hire/tenant/agent/auctions',
                    [
                        'auction_id'     => $auction->id,
                        'stored_value'   => $storedIsApproved,
                        'expected_value' => '1',
                        'table'          => $auction->getTable(),
                        'user_id'        => $auction->user_id,
                    ]
                );
            }
            // ── End regression guard ──────────────────────────────────────────

            $this->listingId = $auction->id;

            $this->saveAllMetadata($auction);

            \Log::info('[TENANT FORM LISTING SUBMITTED]', [
                'record_id' => $auction->id,
                'listing_id' => $auction->listing_id ?? 'N/A',
                'user_type' => $this->user_type,
                'model_class' => $auctionClass,
                'user_id' => $auction->user_id,
                'is_draft' => $auction->is_draft,
                'is_approved' => $auction->is_approved ?? 'N/A',
            ]);

            session()->flash('success', 'Listing submitted successfully!');

            // Redirect to the correct detail page based on user_type
            $routeName = match ($this->user_type) {
                'tenant'   => 'tenant.agent.auction.view',
                'landlord' => 'landlord.agent.auction.view',
                'buyer'    => 'buyer.view-auction',
                'seller'   => 'seller.agent.auction.detail',
                default    => 'hire.agent.auction',
            };

            $url = route($routeName, ['id' => $auction->id]);

            \Log::info('[TENANT FORM REDIRECT]', [
                'route_name' => $routeName,
                'listing_id' => $auction->id,
                'url' => $url,
            ]);

            // Dispatch browser event to force redirect (Livewire v2 compatible)
            $this->dispatchBrowserEvent('force-redirect', ['url' => $url]);

            // Keep redirect as fallback
            return redirect()->to($url);
        } catch (\Exception $e) {
            \Log::error('[STORE] Exception caught', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'user_type' => $this->user_type,
            ]);

            session()->flash('error', 'Error saving listing: ' . $e->getMessage());
        }
    }


    public function deleteAllDrafts()
    {
        try {
            $modelClass = match ($this->user_type) {
                'tenant'   => HireTenantAgentAuction::class,
                'landlord' => HireLandLordAgentAuction::class,
                'buyer'    => HireBuyerAgentAuction::class,
                'seller'   => HireSellerAgentAuction::class,
                default    => HireTenantAgentAuction::class,
            };
            $metaTable = match ($this->user_type) {
                'tenant'   => 'tenant_agent_auction_metas',
                'landlord' => 'landlord_agent_auction_metas',
                'buyer'    => 'buyer_agent_auction_metas',
                'seller'   => 'seller_agent_auction_metas',
                default    => 'tenant_agent_auction_metas',
            };
            $metaFk = match ($this->user_type) {
                'tenant'   => 'tenant_agent_auction_id',
                'landlord' => 'landlord_agent_auction_id',
                'buyer'    => 'buyer_agent_auction_id',
                'seller'   => 'seller_agent_auction_id',
                default    => 'tenant_agent_auction_id',
            };

            $draftIds = $modelClass::where('user_id', Auth::id())
                ->where('is_draft', true)
                ->pluck('id');

            if ($draftIds->isNotEmpty()) {
                DB::table($metaTable)
                    ->whereIn($metaFk, $draftIds)
                    ->delete();
            }

            $modelClass::where('user_id', Auth::id())
                ->where('is_draft', true)
                ->delete();

            session()->flash('success', 'All drafts and their associated data have been deleted successfully.');
            return redirect()->route('hire.agent.auction', ['user_type' => $this->user_type]);
        } catch (\Exception $e) {
            session()->flash('error', 'Error deleting drafts: ' . $e->getMessage());
        }
    }
    public function deleteDraft($draftId)
    {
        try {
            $modelClass = match ($this->user_type) {
                'tenant'   => HireTenantAgentAuction::class,
                'landlord' => HireLandLordAgentAuction::class,
                'buyer'    => HireBuyerAgentAuction::class,
                'seller'   => HireSellerAgentAuction::class,
                default    => HireTenantAgentAuction::class,
            };
            $metaTable = match ($this->user_type) {
                'tenant'   => 'tenant_agent_auction_metas',
                'landlord' => 'landlord_agent_auction_metas',
                'buyer'    => 'buyer_agent_auction_metas',
                'seller'   => 'seller_agent_auction_metas',
                default    => 'tenant_agent_auction_metas',
            };
            $metaFk = match ($this->user_type) {
                'tenant'   => 'tenant_agent_auction_id',
                'landlord' => 'landlord_agent_auction_id',
                'buyer'    => 'buyer_agent_auction_id',
                'seller'   => 'seller_agent_auction_id',
                default    => 'tenant_agent_auction_id',
            };

            DB::table($metaTable)
                ->where($metaFk, $draftId)
                ->delete();

            $modelClass::where('id', $draftId)
                ->where('user_id', Auth::id())
                ->delete();

            $this->hasDrafts = $modelClass::where('user_id', Auth::id())
                ->where('is_draft', true)
                ->exists();

            session()->flash('success', 'Draft deleted successfully.');
            return redirect()->route('hire.agent.auction', ['user_type' => $this->user_type]);
        } catch (\Exception $e) {
            session()->flash('error', 'Error deleting draft: ' . $e->getMessage());
        }
    }

    public function deletePhoto()
    {
        try {
            $auctionClass = match ($this->user_type) {
                'tenant'   => HireTenantAgentAuction::class,
                'landlord' => HireLandLordAgentAuction::class,
                'buyer'    => HireBuyerAgentAuction::class,
                'seller'   => HireSellerAgentAuction::class,
                default    => null,
            };

            if ($this->listingId && $auctionClass) {
                $auction = $auctionClass::find($this->listingId);
                if ($auction) {
                    if ($this->photo && is_string($this->photo)) {
                        Storage::disk('public')->delete('auction/images/' . $this->photo);
                    }
                    $auction->deleteMeta('photo');
                }
            }

            $this->photo = null;
            $this->photoDeleted = true;
            session()->flash('message', 'Photo deleted successfully.');
        } catch (\Exception $e) {
            session()->flash('error', 'Error deleting photo: ' . $e->getMessage());
        }
    }

    public function deleteVideo()
    {
        try {
            $auctionClass = match ($this->user_type) {
                'tenant'   => HireTenantAgentAuction::class,
                'landlord' => HireLandLordAgentAuction::class,
                'buyer'    => HireBuyerAgentAuction::class,
                'seller'   => HireSellerAgentAuction::class,
                default    => null,
            };

            if ($this->listingId && $auctionClass) {
                $auction = $auctionClass::find($this->listingId);
                if ($auction) {
                    if ($this->video && is_string($this->video)) {
                        Storage::disk('public')->delete('auction/videos/' . $this->video);
                    }
                    $auction->deleteMeta('video');
                }
            }

            $this->video = null;
            $this->videoDeleted = true;
            session()->flash('message', 'Video deleted successfully.');
        } catch (\Exception $e) {
            session()->flash('error', 'Error deleting video: ' . $e->getMessage());
        }
    }
}
