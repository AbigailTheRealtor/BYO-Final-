<?php

namespace App\Http\Livewire\OfferListing\Landlord;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\LandlordAgentAuction as HirelandLordAgentAuction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class LandlordOfferListing extends Component
{
    use WithFileUploads;

    protected $listeners = ['setActiveTab' => 'setActiveTab'];


    // Livewire properties for form fields
    public $hasDrafts = false;
    public $auctionId; // To store the auction ID for editing

    public $listingId = null; // To track existing listings
    public $isDraft = false; // To track draft status
    public $isLoadingDraft = false; // Prevents updated* hooks from resetting dependent fields during draft load
    public $service_type = 'full_service'; // 'full_service' or 'limited_service'
    public $listing_status = 'Active'; // 'Active', 'Pending', or 'Hired Agent'

    public $user_type = 'landlord'; // Default to tenant or whatever makes sense
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
    public $occupant_types = '';
    public $occupant_types_tenant = '';
    public $leasing_space_property = '';



    public $lease_amount_frequency = '';
    public $desired_rental_amount = '';
    public $starting_rent = '';
    public $reserve_rent = '';
    public $lease_now_price = '';
    public $desired_rental_amount_tenant = '';
    public $desired_lease_length = [];
    public $rent_includes = []; // Residential only
    public $terms_of_lease = []; // Commercial only
    public $tenant_pays = []; // Commercial only
    public $owner_pays = []; // Commercial only


    // Properties
    public $sale_provision = '';
    public $sale_provision_other = '';
    public $sale_provision_assignment = '';
    public $assignment_fee_type = '$';
    public $assignment_fee_amount = '';
    public $buyer_sell_contract = '';

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
    public $interest_rate = '';
    public $loan_duration = '';
    public $prepayment_penalty = '';
    public $prepayment_penalty_amount = '';
    public $balloon_payment_amount = '';
    public $balloon_payment_date = '';
    public $assumable_terms = '';
    public $max_assumable_rate = '';
    public $max_monthly_payment = '';
    public $gap_payment_type = '$';
    public $gap_payment_amount = '';

    // Exchange/Trade Properties
    public $exchange_item = '';
    public $other_exchange_item = '';
    public $exchange_item_value = '';
    public $exchange_item_condition = '';
    public $additional_cash = '';
    public $value_determination = '';

    // Lease Option Properties
    public $lease_option_price = '';
    public $lease_option_terms = '';
    public $lease_option_duration = '';
    public $lease_option_payment = '';
    public $lease_option_conditions = '';
    public $has_option_fee = '';
    public $option_fee_amount = '';

    // Lease Purchase Properties
    public $lease_purchase_price = '';
    public $lease_purchase_terms = '';
    public $lease_purchase_duration = '';
    public $lease_purchase_payment = '';
    public $lease_purchase_conditions = '';

    public $lease_purchase_option_fee = '';
    public $lease_purchase_option_fee_amount = '';

    // Cryptocurrency Properties
    public $cryptocurrency_type = '';
    public $crypto_percentage = '';
    public $cash_percentage_crypto = '';

    // NFT Properties
    public $nft_description = '';
    public $nft_percentage = '';
    public $cash_percentage_nft = '';

    public $garage_needed = '';
    public $other_garage_needed = '';
    public $garage_parking_spaces = '';
    public $garage_parking_spaces_option = '';
    public $other_parking_space_wrapper = '';
    public $pool_needed = '';
    public $pool_type = [];
    public $view_preference = [];
    public $other_preferences = '';
    public $real_estate_purchase = '';
    public $number_of_unit = '';
    public $number_of_unit_other = '';
    public $minimum_annual_net_income = '';
    public $minimum_cap_rate = '';
    public $assets = '';
    public $assets_other = '';
    public $property_criteria = '';
    public $unit_size = '';
    public $unit_size_other = '';
    public $appliances = [];
    public $appliances_other = '';

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

    // Lease-Option Agreement Compensation
    public $interested_lease_option_agreement = '';
    public $lease_type = 'percent'; // 'percent' or 'flat'
    public $lease_value = '';
    public $purchase_type = 'percent'; // 'percent' or 'flat'
    public $purchase_value = '';

    // Personal information
    public $first_name = '';
    public $last_name = '';
    public $phone_number = '';
    public $email = '';
    public $agent_brokerage = '';
    public $agent_license_number = '';
    public $agent_nar_member_id = '';
    public $video_link = '';
    public array $listing_ai_faq = [];


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

    // Landlord Lease Terms Questions
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
    public $renewal_option_details = '';
    public $landlord_approval_conditions = '';
    public $additional_landlord_lease_terms = '';
    public $commercial_lease_type = '';
    public $commercial_lease_type_other = '';
    public $cam_nnn_additional_rent_charges = '';
    public $rent_escalation_terms = '';
    public $tenant_improvement_buildout_terms = '';
    public $permitted_use_restrictions = '';
    public $signage_rights = '';
    public $commercial_parking_terms = '';
    public $personal_guarantee_requirement = '';
    public $commercial_approval_conditions = '';

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

    // Photos, Tours & Documents
    public $propertyPhotos = [];
    public $newPropertyPhotos = [];
    public $videoTourUrl = '';
    public $virtualTourUrl = '';
    public $listingDocuments = null;

    // Tab management
    public $activeTab = 0;

    // Location suggestions
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
    public $countyFieldVisible = true;
    public $stateFieldVisible = false;
    public $cityFieldVisible = false;
    public $zipCodeFieldVisible = false;
    public $fees = [];



    // In your Livewire component class (e.g., HireLandLordAgent.php)

    public $broker_fee_timing;
    public $broker_fee_timing_other;
    public $broker_fee_days_from_rent;
    public $broker_fee_days_after_lease;
    public $broker_fee_days_after_rent;

    public
        $sales_tax_option_gross, $purchase_fee_monthly_percentage, $purchase_fee_months,
        $sales_tax_option_monthly, $purchase_fee_flat_commercial, $sales_tax_option_flat,
        $purchase_fee_purchase_price, $purchase_fee_other_commercial;


    public $split_payment_due = 'commencement'; // Default to commencement
    public $broker_fee_days_after_due_event = '';
    public $renewal_fee_type = '';
    public $renewal_fee_percentage = '';
    public $renewal_fee_custom = '';

    public $net_aggregate_rent = '';
    public $month_percentage_rent = '';
    public $no_of_months = '';
    public $flat_fee = '';
    public $gross_percentage_rent = '';
    public $purchase_fee_net_aggregate = '';


    public $tenant_broker_commission_structure = ''; // Initialize with empty string or default value
    public $expansion_commission_percentage = '';

    // Tenant's Broker Commission Structure
    public $tenant_broker_commission_percentage = '';
    public $tenant_broker_fee_structure = ''; // Add this line
    public $tenant_broker_percentage = '';
    public $tenant_broker_flat_fee = '';


    public $expansion_commission_type = ''; // dropdown selection

    // Depending on selection, one of the following may be used:
    public $expansion_gross_percentage = null;
    public $expansion_first_month_percentage = null;
    public $expansion_flat_fee = null;
    public $expansion_custom_commission = null;

    // Lease Terms - Leasing Space Sub-Fields
    public $occupant_status = '';
    public $occupant_tenant = '';
    public $leasing_spaces = '';
    public $restrictions = '';
    public $maintenance_by = '';
    public $maintenance_response_time = '';
    public $included_storage_space_res_both = '';
    public $storage_space_res_both = '';
    public $guests_allowed = '';
    public $common_areas_access = '';
    public $utilities = '';
    public $common_areas_cleaning = '';
    public $included_storage_space_res_single = '';
    public $storage_space_res_single = '';
    public $bathroom_facilities = '';
    public $room_size = '';
    public $included_storage_space_com_entire = '';
    public $storage_space_com_entire = '';
    public $shared_amenities = '';
    public $building_hours = '';
    public $access_24_7 = '';
    public $zoning_allows = '';
    public $space_features = '';
    public $neighboring_tenants = '';
    public $included_storage_space_com_single = '';
    public $storage_space_com_single = '';
    public $maintenance_handler = '';
    public $included_storage_space = '';
    public $storage_space = '';

    // "Other" text fields for multi-select dropdowns
    public $other_tenant_pays = '';
    public $other_owner_pays = '';
    public $custom_lease_term = '';
    public $other_lease_term = '';
    public $other_rent_include = '';
    public $other_appliances = '';
    public $tenant_pays_other = '';
    public $owner_pays_other = '';

    // Visibility flags for "Other" text fields
    public $is_other_tenant_pay_visible = false;
    public $is_other_owner_pays_visible = false;
    public $is_rent_include_visible = false;
    public $is_other_appliances_visible = false;
    public $showOtherAppliances = false;

    // Landlord Broker Lease Fee fields
    public $landlord_broker_purchase_price = '';
    public $landlord_broker_percentage_price = '';
    public $landlord_broker_dollar_price = '';
    public $landlord_broker_flate_fee = '';
    public $landlord_broker_other = '';

    // Property Management & Selling Interest
    public $interested_in_property_management = '';
    public $interested_in_property_management_fee = '';
    public $interested_in_property_management_fee_flate_free = '';
    public $interested_in_property_management_fee_gross_lease = '';
    public $interested_in_property_management_fee_other = '';
    public $interested_in_property_management_fee_rental_periord = '';
    public $interested_in_selling = '';
    public $interested_in_selling_type = '';

    // Additional broker compensation sub-fields
    public $lease_fee_flat_type = '';
    public $purchase_fee_flat_type = '';
    public $purchase_fee_gross_rent = '';
    public $purchase_fee_rental_period = '';
    public $split_payment_due_other = '';
    public $tenant_broker_first_month_rent = '';
    public $tenant_broker_gross_lease = '';
    public $tenant_broker_other = '';
    public $renewal_fee_first_month = '';
    public $renewal_fee_flat_free = '';
    public $renewal_fee_lease_value = '';
    public $renewal_fee_no_of_months = '';
    public $renewal_fee_sales_tax_first_month = '';
    public $renewal_fee_sales_tax_flat_fee = '';
    public $renewal_fee_sales_tax_lease_value = '';

    // Listing & property additional fields
    public $additional_details_broker = '';
    public $agent_bid_visibility = '';
    public $total_square_feet = '';
    public $sqft_heated_source = '';
    public $meeting_Preference = '';
    public $photo_enhancements = [];
    public $custom_enhancement = '';
    public $showEnhancements = false;
    public $showCustomEnhancement = false;

    // Pet/animal fields
    public $has_breed_restrictions = '';
    public $breed_restrictions = '';
    public $service_animal = '';
    public $support_animal = '';

    // MLS Property Detail Fields — Residential + Commercial shared
    public $year_built = '';
    public $heating_fuel = [];
    public $other_heating_fuel = '';
    public $air_conditioning = [];
    public $other_air_conditioning = '';
    public $water = [];
    public $other_water = '';
    public $sewer = [];
    public $other_sewer = '';
    public $property_utilities = [];
    public $other_property_utilities = '';

    // MLS Property Detail Fields — Residential only
    public $laundry_features = [];
    public $other_laundry_features = '';
    public $floor_covering = [];
    public $other_floor_covering = '';
    public $security_features = [];
    public $other_security_features = '';

    // MLS Property Detail Fields — Commercial only
    public $zoning = '';
    public $total_buildings = '';
    public $total_units_on_property = '';
    public $office_retail_sqft = '';
    public $flex_space_sqft = '';
    public $road_surface_type = [];
    public $other_road_surface_type = '';
    public $electrical_service = [];
    public $other_electrical_service = '';
    public $ceiling_height = '';
    public $building_features = [];
    public $other_building_features = '';
    public $number_electric_meters = '';
    public $number_water_meters = '';
    public $number_gas_meters = '';
    public $space_type = [];
    public $other_space_type = '';
    public $space_classification = [];
    public $other_space_classification = '';
    public $number_of_restrooms = '';
    public $number_of_offices = '';
    public $number_of_conference_rooms = '';

    // Tax, Legal, HOA & Disclosures tab fields
    // Group 1 — Tax / Legal / Parcel
    public $parcel_id = '';
    public $tax_year = '';
    public $annual_property_taxes = '';
    public $additional_parcels = '';
    public $total_parcel_count = '';
    public $additional_parcel_ids = '';
    public $legal_description = '';

    // Group 2 — Flood Zone
    public $flood_zone_code = '';
    public $flood_zone_code_other = '';
    public $flood_insurance_required = '';
    public $flood_zone_panel = '';

    // Group 3 — CDD / Special Assessments
    public $has_cdd = '';
    public $annual_cdd_fee = '';
    public $has_special_assessments = '';
    public $special_assessment_amount = '';
    public $special_assessment_description = '';

    // Group 4 — Structured HOA
    public $has_hoa = '';
    public $association_type = '';
    public $association_type_other = '';
    public $association_name = '';
    public $association_fee_amount = '';
    public $association_fee_frequency = '';
    public $association_fee_frequency_other = '';
    public $association_approval_required = '';
    public $association_approval_process = '';
    public $association_application_fee = '';
    public $association_fee_includes = [];
    public $association_fee_includes_other = '';
    public $association_amenities = [];
    public $association_amenities_other = '';
    public $leasing_restrictions = '';
    public $min_lease_period = '';
    public $min_lease_period_other = '';
    public $max_leases_per_year = '';
    public $additional_lease_restrictions = '';
    public $pet_restrictions = '';
    public $pet_restrictions_detail = '';

    // Group 5 — Documents & Disclosures
    public $landlord_disclosure_available = '';
    public $survey_available = '';
    public $inspection_report_available = '';
    public $hoa_condo_docs_available = '';
    public $flood_disclosure_available = '';
    public $lead_based_paint_disclosure = '';
    public $environmental_report_available = '';
    public $additional_documents = [];
    public $other_document_type = '';

    // Repeatable document rows (replaces additional_documents Select2)
    public $landlord_doc_rows = [];
    public $landlordDocFileUpload;
    public $landlordDocFileIndex = null;

    // Disclosure file uploads (temporary Livewire upload objects)
    public $landlord_disclosure_file;
    public $survey_file;
    public $inspection_report_file;
    public $hoa_condo_docs_file;
    public $flood_disclosure_file;
    public $lead_based_paint_file;
    public $environmental_report_file;

    // Disclosure file stored paths (persisted via meta)
    public $landlord_disclosure_file_path = '';
    public $survey_file_path = '';
    public $inspection_report_file_path = '';
    public $hoa_condo_docs_file_path = '';
    public $flood_disclosure_file_path = '';
    public $lead_based_paint_file_path = '';
    public $environmental_report_file_path = '';

    // Payment Timing


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

    public function getShowOtherAppliancesProperty()
    {
        return is_array($this->appliances) && in_array('Other', $this->appliances);
    }

    public function getIsOtherTenantPayVisibleProperty()
    {
        return is_array($this->tenant_pays) && in_array('Other', $this->tenant_pays);
    }

    public function getIsOtherOwnerPaysVisibleProperty()
    {
        return is_array($this->owner_pays) && in_array('Other', $this->owner_pays);
    }

    public function getIsUpdateLeaseTermOptionVisibleProperty()
    {
        return is_array($this->desired_lease_length) && in_array('Other', $this->desired_lease_length);
    }

    public function getIsRentIncludeVisibleProperty()
    {
        return is_array($this->rent_includes) && in_array('Other', $this->rent_includes);
    }

    // Methods

    /**
     * Override syncInput to guard against empty property names before Str::studly() is called.
     * This prevents crashes when wire:model.lazy sends empty property names during select transitions.
     */
    public function syncInput($name, $value, $rehash = true)
    {
        if (blank($name)) {
            return;
        }
        return parent::syncInput($name, $value, $rehash);
    }

    /**
     * Override callUpdatedHook to guard against empty property names before Str::studly() is called.
     * This prevents crashes when wire:model.lazy sends empty property names during select transitions.
     */
    protected function callUpdatedHook($name, $value = null)
    {
        if (blank($name)) {
            return;
        }
        return parent::callUpdatedHook($name, $value);
    }

    /**
     * Generic updated hook - additional validation for property changes.
     */
    public function updated($propertyName, $value = null)
    {
        if (empty($propertyName)) {
            return;
        }
    }

    /**
     * Reset dependent fields when lease_fee_type changes to prevent validation errors
     * when switching between fee types (fixes Str::studly() crash).
     */
    public function updatedLeaseFeeType($value)
    {
        if ($this->isLoadingDraft) return;
        $this->reset([
            'lease_fee_flat',
            'lease_fee_percentage',
            'lease_fee_months',
            'lease_fee_percentage_monthly_rent',
            'lease_fee_flat_combo',
            'lease_fee_percentage_combo',
            'lease_fee_other',
        ]);
    }

    /**
     * Reset dependent fields when purchase_fee_type (Landlord's Broker Lease Fee) changes
     * to prevent validation errors when switching between fee types.
     */
    public function updatedPurchaseFeeType($value)
    {
        if ($this->isLoadingDraft) return;
        $this->reset([
            'purchase_fee_flat',
            'purchase_fee_rental_period',
            'purchase_fee_percentage_combo',
            'purchase_fee_flat_combo',
            'purchase_fee_other',
            'purchase_fee_net_aggregate',
            'purchase_fee_gross_rent',
            'purchase_fee_monthly_percentage',
            'purchase_fee_months',
            'purchase_fee_flat_commercial',
            'purchase_fee_purchase_price',
            'purchase_fee_other_commercial',
            'sales_tax_option_gross',
            'sales_tax_option_monthly',
            'sales_tax_option_flat',
        ]);
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

    public function updatedOfferedFinancing()
    {
        if ($this->isLoadingDraft) return;
        $this->reset([
            'other_financing',
            'cash_budget',
            'pre_approved',
            'pre_approval_amount',
            'purchase_price',
            'down_payment_amount',
            'seller_financing_amount',
            'interest_rate',
            'loan_duration',
            'prepayment_penalty',
            'prepayment_penalty_amount',
            'balloon_payment_amount',
            'balloon_payment_date',
            'assumable_terms',
            'max_assumable_rate',
            'max_monthly_payment',
            'gap_payment_amount'
        ]);
    }
    // Methods/ Methods
    public function setAssignmentFeeType($type)
    {
        $this->assignment_fee_type = $type;
        $this->assignment_fee_amount = ''; // Clear amount when changing type
    }

    public function updatedSaleProvision()
    {
        if ($this->isLoadingDraft) return;
        $this->reset([
            'sale_provision_other',
            'sale_provision_assignment',
            'assignment_fee_amount',
            'buyer_sell_contract'
        ]);
    }

    public function updatedSaleProvisionAssignment()
    {
        if ($this->isLoadingDraft) return;
        $this->reset(['assignment_fee_amount', 'buyer_sell_contract']);
    }

    public function updatedBuyerSellContract()
    {
        if ($this->isLoadingDraft) return;
        $this->reset(['assignment_fee_amount']);
    }

    /**
     * Handle type changes for lease-option compensation fields
     */
    public function setType($field, $value)
    {
        if ($field === 'lease') {
            $this->lease_type = $value;
        } elseif ($field === 'purchase') {
            $this->purchase_type = $value;
        }
    }

    // Methods
    public function mount($listingId = null)
    {
        $this->addService();

        // Set listing_date to today's date by default (only if creating new listing)
        // loadDraft() will overwrite this with the saved value if loading a draft
        $this->listing_date = now()->format('Y-m-d');

        // Check for existing drafts using OR logic
        $draftCount = HirelandLordAgentAuction::where('user_id', Auth::id())
            ->where(function ($query) {
                $query->where('is_draft', true)
                      ->orWhereNull('is_draft');
            })
            ->count();
        $this->hasDrafts = $draftCount > 0;

        \Log::info('[LANDLORD DRAFT CHECK] mount()', [
            'user_id' => Auth::id(),
            'draft_count' => $draftCount,
            'hasDrafts' => $this->hasDrafts,
            'listingId_param' => $listingId,
        ]);

        if ($listingId) {
            $this->loadDraft($listingId);
        }
    }
    public function startNew()
    {
        $this->resetExcept(['hasDrafts', 'service_type', 'user_type']);
        $this->addService();
        $this->listingId = null;
        $this->isDraft = false;
    }
    public function getDrafts()
    {
        $drafts = HirelandLordAgentAuction::where('user_id', Auth::id())
            ->where(function ($query) {
                $query->where('is_draft', true)
                      ->orWhereNull('is_draft');
            })
            ->latest()
            ->get();

        \Log::info('[LANDLORD getDrafts]', [
            'user_id' => Auth::id(),
            'total_drafts_returned' => $drafts->count(),
            'draft_ids' => $drafts->pluck('id')->toArray(),
            'draft_titles' => $drafts->pluck('title')->toArray(),
        ]);

        return $drafts;
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
        $suggestion = $suggestion ?? ($this->highlightedPropertyCityIndex >= 0 ? ($this->propertyCitySuggestions[$this->highlightedPropertyCityIndex] ?? null) : null) ?? ($this->propertyCitySuggestions[0] ?? null) ?? $this->property_city;
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
            if ($state) {
                $this->property_state = $state->name;
            }
        }
        
        // Normalize city name to handle punctuation variations (e.g. "St." → "St")
        $normalizedCityName = trim(preg_replace('/\s+/', ' ', preg_replace('/\.+/', '', $cityName)));

        $city = \App\Models\UsCity::where(function ($q) use ($cityName, $normalizedCityName) {
                $q->where('name', 'ILIKE', $cityName)
                  ->orWhere('name', 'ILIKE', $normalizedCityName)
                  ->orWhereRaw("REPLACE(name, '.', '') ILIKE ?", [$normalizedCityName]);
            })
            ->when($stateAbbrev, function ($query) use ($stateAbbrev) {
                return $query->whereHas('state', function ($q) use ($stateAbbrev) {
                    $q->where('abbreviation', strtoupper($stateAbbrev));
                });
            })
            ->with(['county', 'state'])
            ->first();

        if ($city && $city->county) {
            $countyName = $city->county->name;
            if (!str_contains(strtolower($countyName), 'county')) {
                $countyName .= ' County';
            }
            $stateAbbr = $city->state ? $city->state->abbreviation : '';
            $this->property_county = $countyName . ', ' . $stateAbbr;
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
            $normalizedInput = trim(preg_replace('/\s+/', ' ', preg_replace('/\.+/', '', $input)));
            $citiesStartWith = \App\Models\UsCity::with('state')
                ->where(function ($q) use ($input, $normalizedInput) {
                    $q->where('name', 'ILIKE', $input . '%')
                      ->orWhere('name', 'ILIKE', $normalizedInput . '%')
                      ->orWhereRaw("REPLACE(name, '.', '') ILIKE ?", [$normalizedInput . '%']);
                })
                ->orderBy('name')
                ->limit(10)
                ->get();
            
            $citiesContain = \App\Models\UsCity::with('state')
                ->where(function ($q) use ($input, $normalizedInput) {
                    $q->where('name', 'ILIKE', '%' . $input . '%')
                      ->orWhere('name', 'ILIKE', '%' . $normalizedInput . '%')
                      ->orWhereRaw("REPLACE(name, '.', '') ILIKE ?", ['%' . $normalizedInput . '%']);
                })
                ->where(function ($q) use ($input, $normalizedInput) {
                    $q->where('name', 'NOT ILIKE', $input . '%')
                      ->where('name', 'NOT ILIKE', $normalizedInput . '%')
                      ->whereRaw("REPLACE(name, '.', '') NOT ILIKE ?", [$normalizedInput . '%']);
                })
                ->orderBy('name')
                ->limit(max(0, 10 - $citiesStartWith->count()))
                ->get();
            
            $cities = $citiesStartWith->merge($citiesContain);
            
            foreach ($cities as $city) {
                $stateAbbrev = $city->state ? $city->state->abbreviation : '';
                $results[] = $city->name . ', ' . $stateAbbrev;
            }
        }
        
        return $results;
    }

    public function selectCitySuggestion($suggestion = null)
    {
        $suggestion = $suggestion ?? ($this->highlightedCityIndex >= 0 ? ($this->citySuggestions[$this->highlightedCityIndex] ?? null) : null) ?? ($this->citySuggestions[0] ?? null) ?? $this->newCity;
        $this->newCity = $suggestion;

        if (!in_array(trim($suggestion), $this->cities)) {
            $this->addCity();
        }

        $this->citySuggestions = [];
        $this->highlightedCityIndex = -1;
        
        $this->autoPopulateZipCodesFromCity($suggestion);
        $this->autoPopulateFromCity($suggestion);
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

    private function autoPopulateFromCity($cityString)
    {
        $stateAbbr = $this->extractStateFromLocationString($cityString);

        if ($stateAbbr && empty($this->state)) {
            $stateRecord = \App\Models\UsState::where('abbreviation', strtoupper($stateAbbr))->first();
            if ($stateRecord) {
                $this->state = $stateRecord->name;
            }
        }

        $cityName = $this->extractNameFromLocationString($cityString);
        $normalizedCityName = trim(preg_replace('/\s+/', ' ', preg_replace('/\.+/', '', (string) $cityName)));
        if ($cityName && $stateAbbr) {
            $cities = \App\Models\UsCity::with(['state', 'county.state'])
                ->where(function ($q) use ($cityName, $normalizedCityName) {
                    $q->where('name', 'ILIKE', $cityName)
                      ->orWhere('name', 'ILIKE', $normalizedCityName)
                      ->orWhereRaw("REPLACE(name, '.', '') ILIKE ?", [$normalizedCityName]);
                })
                ->whereHas('state', function ($q) use ($stateAbbr) {
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

    public function render()
    {

        return view('livewire.offer-listing.landlord.offer-landlord-listing')->extends('layouts.main')->section('content'); // Define the section
    }

    public function saveDraft()
    {

        try {


            $this->isDraft = true;

            $auction = $this->listingId
                ? HirelandLordAgentAuction::find($this->listingId)
                : new HirelandLordAgentAuction();

            $auction->user_id = Auth::id();
            $auction->title = $this->listing_title;
            $auction->is_draft = true;
            $auction->save();

            // Phase 6 — persist referral attribution on brand-new listing rows.
            \App\Services\ReferralLinkService::persistListingReferral($auction);

            $this->listingId = $auction->id;

            $this->saveAllMetadata($auction);

            $this->hasDrafts = true;

            session()->flash('success', 'Draft saved successfully. You can return later to complete your listing.');
        } catch (\Exception $e) {
            session()->flash('error', 'Error saving draft: ' . $e->getMessage());
        }
    }

    public function finishDraftLoad()
    {
        $this->isLoadingDraft = false;
    }

    public function loadDraft($listingId)
    {
        $auction = HirelandLordAgentAuction::where('id', $listingId)
            ->where('user_id', Auth::id())
            ->first();

        if ($auction) {
            $this->isLoadingDraft = true;

            // Load all metadata fields
            $this->listing_title = $auction->title;
            $this->service_type = $auction->get->service_type ?? null;
            $this->user_type = $auction->get->user_type ?? null;
            $this->listing_status = $auction->get->listing_status ?? null;
            $this->auction_type = $auction->get->auction_type ?? null;
            $this->working_with_agent = $auction->get->working_with_agent ?? null;
            $this->listing_date = $auction->get->listing_date ?? null;
            $this->desired_agent_hire_date = $auction->get->desired_agent_hire_date ?? null;
            $this->expiration_date = $auction->get->expiration_date ?? null;
            $this->auction_time = $auction->get->auction_time ?? null;

            $this->state = $auction->get->state ?? null;
            $this->zipCodes = $this->ensureArray($auction->get->zipCodes ?? null);
            $this->zip_code = $this->zipCodes[0] ?? ($auction->get->zip_code ?? '');
            $this->property_type = $auction->get->property_type ?? null;
            $this->cities = $this->ensureArray($auction->get->cities ?? null);
            $this->counties = $this->ensureArray($auction->get->counties ?? null);

            $this->property_city = $auction->get->property_city ?? null;
            $this->property_county = $auction->get->property_county ?? null;
            $this->property_state = $auction->get->property_state ?? null;
            $this->property_zip = $auction->get->property_zip ?? null;

            $this->cityFieldVisible = !empty($this->cities);
            $this->countyFieldVisible = !empty($this->counties);
            $this->stateFieldVisible = !empty($this->state);
            $this->zipCodeFieldVisible = !empty($this->zipCodes);
            // Property details

            $this->property_items = $this->ensureArray($auction->get->property_items ?? null);

            $this->other_property_items = $auction->get->other_property_items ?? null;
            $this->condition_prop = $auction->get->condition_prop ?? null;
            $this->leasing_space = $auction->get->leasing_space ?? null;
            $this->other_property_condition = $auction->get->other_property_condition ?? null;
            $this->bathrooms = $auction->get->bathrooms ?? null;
            $this->other_bathrooms = $auction->get->other_bathrooms ?? null;
            $this->bedrooms = $auction->get->bedrooms ?? null;
            $this->other_bedrooms = $auction->get->other_bedrooms ?? null;
            $this->minimum_heated_square = $auction->get->minimum_heated_square ?? null;
            $this->minimum_leaseable = $auction->get->minimum_leaseable ?? null;
            $this->min_acreage = $auction->get->min_acreage ?? null;
            $this->total_acreage = $auction->get->total_acreage ?? null;
            $this->minimum_cap_rate = $auction->get->minimum_cap_rate ?? null;
            $this->assets = $auction->get->assets ?? null;
            $this->assets_other = $auction->get->assets_other ?? null;
            $this->property_criteria = $auction->get->property_criteria ?? null;
            $this->unit_size = $auction->get->unit_size ?? null;
            $this->unit_size_other = $auction->get->unit_size_other ?? null;
            $this->appliances = $this->ensureArray($auction->get->appliances ?? null);
            $this->appliances_other = $auction->get->appliances_other ?? null;
            $this->other_appliances = $auction->get->other_appliances ?? ($auction->get->appliances_other ?? null);
            $this->preferance_details = $auction->get->preferance_details ?? null;


            // Sale Provision
            $this->sale_provision = $auction->get->sale_provision ?? null;
            $this->sale_provision_other = $auction->get->sale_provision_other ?? null;
            $this->sale_provision_assignment = $auction->get->sale_provision_assignment ?? null;
            $this->assignment_fee_type = $auction->get->assignment_fee_type ?? null;
            $this->assignment_fee_amount = $auction->get->assignment_fee_amount ?? null;
            $this->buyer_sell_contract = $auction->get->buyer_sell_contract ?? null;

            // Budget & Financing
            $this->maximum_budget = $auction->get->maximum_budget ?? null;
            $this->offered_financing = $this->ensureArray($auction->get->offered_financing ?? null);
            $this->other_financing = $auction->get->other_financing ?? null;
            $this->cash_budget = $auction->get->cash_budget ?? null;
            $this->pre_approved = $auction->get->pre_approved ?? null;
            $this->pre_approval_amount = $auction->get->pre_approval_amount ?? null;
            $this->purchase_price = $auction->get->purchase_price ?? null;
            $this->down_payment_type = $auction->get->down_payment_type ?? null;
            $this->down_payment_amount = $auction->get->down_payment_amount ?? null;
            $this->seller_financing_type = $auction->get->seller_financing_type ?? null;
            $this->seller_financing_amount = $auction->get->seller_financing_amount ?? null;
            $this->interest_rate = $auction->get->interest_rate ?? null;
            $this->loan_duration = $auction->get->loan_duration ?? null;
            $this->prepayment_penalty = $auction->get->prepayment_penalty ?? null;
            $this->prepayment_penalty_amount = $auction->get->prepayment_penalty_amount ?? null;
            $this->balloon_payment_amount = $auction->get->balloon_payment_amount ?? null;
            $this->balloon_payment_date = $auction->get->balloon_payment_date ?? null;
            $this->assumable_terms = $auction->get->assumable_terms ?? null;
            $this->max_assumable_rate = $auction->get->max_assumable_rate ?? null;
            $this->max_monthly_payment = $auction->get->max_monthly_payment ?? null;
            $this->gap_payment_type = $auction->get->gap_payment_type ?? null;
            $this->gap_payment_amount = $auction->get->gap_payment_amount ?? null;

            // Exchange/Trade
            $this->exchange_item = $auction->get->exchange_item ?? null;
            $this->other_exchange_item = $auction->get->other_exchange_item ?? null;
            $this->exchange_item_value = $auction->get->exchange_item_value ?? null;
            $this->exchange_item_condition = $auction->get->exchange_item_condition ?? null;
            $this->additional_cash = $auction->get->additional_cash ?? null;
            $this->value_determination = $auction->get->value_determination ?? null;

            // Lease Option
            $this->lease_option_price = $auction->get->lease_option_price ?? null;
            $this->lease_option_terms = $auction->get->lease_option_terms ?? null;
            $this->lease_option_duration = $auction->get->lease_option_duration ?? null;
            $this->lease_option_payment = $auction->get->lease_option_payment ?? null;
            $this->lease_option_conditions = $auction->get->lease_option_conditions ?? null;
            $this->has_option_fee = $auction->get->has_option_fee ?? null;
            $this->option_fee_amount = $auction->get->option_fee_amount ?? null;

            // Lease Purchase
            $this->lease_purchase_price = $auction->get->lease_purchase_price ?? null;
            $this->lease_purchase_terms = $auction->get->lease_purchase_terms ?? null;
            $this->lease_purchase_duration = $auction->get->lease_purchase_duration ?? null;
            $this->lease_purchase_payment = $auction->get->lease_purchase_payment ?? null;
            $this->lease_purchase_conditions = $auction->get->lease_purchase_conditions ?? null;
            $this->lease_purchase_option_fee = $auction->get->lease_purchase_option_fee ?? null;
            $this->lease_purchase_option_fee_amount = $auction->get->lease_purchase_option_fee_amount ?? null;

            // Cryptocurrency
            $this->cryptocurrency_type = $auction->get->cryptocurrency_type ?? null;
            $this->crypto_percentage = $auction->get->crypto_percentage ?? null;
            $this->cash_percentage_crypto = $auction->get->cash_percentage_crypto ?? null;

            // NFT
            $this->nft_description = $auction->get->nft_description ?? null;
            $this->nft_percentage = $auction->get->nft_percentage ?? null;
            $this->cash_percentage_nft = $auction->get->cash_percentage_nft ?? null;

            // Amenities and features

            $this->tenant_require = $this->ensureArray($auction->get->tenant_require ?? null);


            $this->carport_needed = $auction->get->carport_needed ?? null;
            $this->other_carport_needed = $auction->get->other_carport_needed ?? null;
            $this->occupant_types = $auction->get->occupant_types ?? null;
            $this->occupant_types_tenant = $auction->get->occupant_types_tenant ?? null;
            $this->leasing_space_property = $auction->get->leasing_space_property ?? null;
            $this->lease_amount_frequency = $auction->get->lease_amount_frequency ?? null;
            $this->desired_lease_length = $this->ensureArray($auction->get->desired_lease_length ?? null);
            $this->desired_rental_amount = $auction->get->desired_rental_amount ?? null;
            $this->starting_rent = $auction->get->starting_rent ?? '';
            $this->reserve_rent = $auction->get->reserve_rent ?? '';
            $this->lease_now_price = $auction->get->lease_now_price ?? '';
            $this->desired_rental_amount_tenant = $auction->get->desired_rental_amount_tenant ?? null;


            $this->rent_includes = $this->ensureArray($auction->get->rent_includes ?? null);
            $this->terms_of_lease = $this->ensureArray($auction->get->terms_of_lease ?? null);
            $this->tenant_pays = $this->ensureArray($auction->get->tenant_pays ?? null);
            $this->owner_pays = $this->ensureArray($auction->get->owner_pays ?? null);
            $this->other_tenant_pays = $auction->get->other_tenant_pays ?? null;
            $this->other_owner_pays = $auction->get->other_owner_pays ?? null;
            $this->tenant_pays_other = $auction->get->tenant_pays_other ?? null;
            $this->owner_pays_other = $auction->get->owner_pays_other ?? null;
            $this->custom_lease_term = $auction->get->custom_lease_term ?? null;
            $this->other_lease_term = $auction->get->other_lease_term ?? null;
            $this->other_rent_include = $auction->get->other_rent_include ?? null;

            $this->is_other_tenant_pay_visible = in_array('Other', $this->tenant_pays);
            $this->is_other_owner_pays_visible = in_array('Other', $this->owner_pays);
            $this->is_rent_include_visible = in_array('Other', $this->rent_includes);
            $this->is_other_appliances_visible = in_array('Other', $this->appliances);
            $this->showOtherAppliances = in_array('Other', $this->appliances);


            $this->garage_needed = $auction->get->garage_needed ?? null;
            $this->other_garage_needed = $auction->get->other_garage_needed ?? null;
            $this->garage_parking_spaces = $auction->get->garage_parking_spaces ?? null;
            $this->garage_parking_spaces_option = $auction->get->garage_parking_spaces_option ?? null;
            $this->other_parking_space_wrapper = $auction->get->other_parking_space_wrapper ?? null;
            $this->pool_needed = $auction->get->pool_needed ?? null;

            $this->pool_type = $this->ensureArray($auction->get->pool_type ?? null);




            $this->view_preference = $this->ensureArray($auction->get->view_preference ?? null);



            $this->other_preferences = $auction->get->other_preferences ?? null;
            $this->real_estate_purchase = $auction->get->real_estate_purchase ?? null;
            $this->number_of_unit = $auction->get->number_of_unit ?? null;
            $this->number_of_unit_other = $auction->get->number_of_unit_other ?? null;
            $this->minimum_annual_net_income = $auction->get->minimum_annual_net_income ?? null;
            $this->leasing_55_plus = $auction->get->leasing_55_plus ?? null;
            $this->occupant_status = $auction->get->occupant_status ?? null;
            $this->occupant_tenant = $auction->get->occupant_tenant ?? null;
            $this->leasing_spaces = $auction->get->leasing_spaces ?? null;
            $this->restrictions = $auction->get->restrictions ?? null;
            $this->maintenance_by = $auction->get->maintenance_by ?? null;
            $this->maintenance_response_time = $auction->get->maintenance_response_time ?? null;
            $this->included_storage_space_res_both = $auction->get->included_storage_space_res_both ?? null;
            $this->storage_space_res_both = $auction->get->storage_space_res_both ?? null;
            $this->guests_allowed = $auction->get->guests_allowed ?? null;
            $this->common_areas_access = $auction->get->common_areas_access ?? null;
            $this->utilities = $auction->get->utilities ?? null;
            $this->common_areas_cleaning = $auction->get->common_areas_cleaning ?? null;
            $this->included_storage_space_res_single = $auction->get->included_storage_space_res_single ?? null;
            $this->storage_space_res_single = $auction->get->storage_space_res_single ?? null;
            $this->bathroom_facilities = $auction->get->bathroom_facilities ?? null;
            $this->room_size = $auction->get->room_size ?? null;
            $this->included_storage_space_com_entire = $auction->get->included_storage_space_com_entire ?? null;
            $this->storage_space_com_entire = $auction->get->storage_space_com_entire ?? null;
            $this->shared_amenities = $auction->get->shared_amenities ?? null;
            $this->building_hours = $auction->get->building_hours ?? null;
            $this->access_24_7 = $auction->get->access_24_7 ?? null;
            $this->zoning_allows = $auction->get->zoning_allows ?? null;
            $this->space_features = $auction->get->space_features ?? null;
            $this->neighboring_tenants = $auction->get->neighboring_tenants ?? null;
            $this->included_storage_space_com_single = $auction->get->included_storage_space_com_single ?? null;
            $this->storage_space_com_single = $auction->get->storage_space_com_single ?? null;
            $this->maintenance_handler = $auction->get->maintenance_handler ?? null;
            $this->included_storage_space = $auction->get->included_storage_space ?? null;
            $this->storage_space = $auction->get->storage_space ?? null;

            $this->non_negotiable_amenities = $this->ensureArray($auction->get->non_negotiable_amenities ?? null);

            $this->other_non_negotiable_amenities = $auction->get->other_non_negotiable_amenities ?? null;
            $this->budget = $auction->get->budget ?? null;

            // Lease terms
            $this->additional_details = $auction->get->additional_details ?? null;

            $this->lease_for = $this->ensureArray($auction->get->lease_for ?? null);


            $this->other_lease_for = $auction->get->other_lease_for ?? null;
            $this->lease_by = $auction->get->lease_by ?? null;
            $this->lease_date = $auction->get->lease_date ?? null;

            // Tenant information
            $this->pets = $auction->get->pets ?? null;
            $this->number_of_pets = $auction->get->number_of_pets ?? null;
            $this->breed_of_pets = $auction->get->breed_of_pets ?? null;
            $this->type_of_pets = $auction->get->type_of_pets ?? null;
            $this->weight_of_pets = $auction->get->weight_of_pets ?? null;


            $this->credit_scroe_rating = $this->ensureArray($auction->get->credit_scroe_rating ?? null);

            $this->prior_eviction = $auction->get->prior_eviction ?? null;
            $this->eviction_explanation = $auction->get->eviction_explanation ?? null;
            $this->prior_felony = $auction->get->prior_felony ?? null;
            $this->prior_felony_explanation = $auction->get->prior_felony_explanation ?? null;
            $this->monthly_income = $auction->get->monthly_income ?? null;
            $this->number_occupant = $auction->get->number_occupant ?? null;

            // Services

            $this->services = $this->ensureArray($auction->get->services ?? null);


            $this->other_services = $auction->get->other_services ?? null;


            $this->flat_fee_services = $this->ensureArray($auction->get->flat_fee_services ?? null);
            $this->additional_details = $auction->get->additional_details ?? null;

            // Broker compensation
            $this->commission_structure = $auction->get->commission_structure ?? null;
            $this->lease_fee_type = $auction->get->lease_fee_type ?? null;
            $this->lease_fee_flat = $auction->get->lease_fee_flat ?? null;
            $this->lease_fee_percentage = $auction->get->lease_fee_percentage ?? null;
            $this->lease_fee_months = $auction->get->lease_fee_months ?? null;
            $this->lease_fee_percentage_monthly_rent = $auction->get->lease_fee_percentage_monthly_rent ?? null;
            $this->lease_fee_flat_combo = $auction->get->lease_fee_flat_combo ?? null;
            $this->lease_fee_percentage_combo = $auction->get->lease_fee_percentage_combo ?? null;
            $this->lease_fee_other = $auction->get->lease_fee_other ?? null;
            $this->purchase_fee_type = $auction->get->purchase_fee_type ?? null;
            $this->purchase_fee_percentage = $auction->get->purchase_fee_percentage ?? null;
            $this->purchase_fee_flat = $auction->get->purchase_fee_flat ?? null;
            $this->purchase_fee_percentage_combo = $auction->get->purchase_fee_percentage_combo ?? null;
            $this->purchase_fee_flat_combo = $auction->get->purchase_fee_flat_combo ?? null;
            $this->purchase_fee_other = $auction->get->purchase_fee_other ?? null;
            $this->lease_option_fee_type = $auction->get->lease_option_fee_type ?? null;
            $this->lease_option_fee_flat = $auction->get->lease_option_fee_flat ?? null;
            $this->lease_option_fee_percentage = $auction->get->lease_option_fee_percentage ?? null;
            $this->lease_option_fee_other = $auction->get->lease_option_fee_other ?? null;
            $this->protection_period = $auction->get->protection_period ?? null;
            $this->early_termination_fee_option = $auction->get->early_termination_fee_option ?? null;
            $this->early_termination_fee_amount = $auction->get->early_termination_fee_amount ?? null;
            $this->retainer_fee_option = $auction->get->retainer_fee_option ?? null;
            $this->retainer_fee_amount = $auction->get->retainer_fee_amount ?? null;
            $this->retainer_fee_application = $auction->get->retainer_fee_application ?? null;
            $this->agency_agreement_timeframe = $auction->get->agency_agreement_timeframe ?? null;
            $this->agency_agreement_custom = $auction->get->agency_agreement_custom ?? null;
            $this->brokerage_relationship = $auction->get->brokerage_relationship ?? null;

            // Personal information
            $this->first_name = $auction->get->first_name ?? null;
            $this->last_name = $auction->get->last_name ?? null;
            $this->phone_number = $this->formatPhoneForDisplay($auction->get->phone_number ?? null);
            $this->email = $auction->get->email ?? null;
            $this->agent_brokerage = $auction->get->agent_brokerage ?? '';
            $this->agent_license_number = $auction->get->agent_license_number ?? '';
            $this->agent_nar_member_id = $auction->get->agent_nar_member_id ?? '';
            $this->video_link = $auction->get->video_link ?? null;
            $this->listing_ai_faq = json_decode($auction->info('listing_ai_faq') ?: '{}', true) ?? [];
            $this->photo = $auction->get->photo ?? null;
            $this->video = $auction->get->video ?? null;
            $this->videoTourUrl = $auction->get->video_tour_url ?? '';
            $this->virtualTourUrl = $auction->get->virtual_tour_url ?? '';
            $rawPhotos = $auction->get->property_photos ?? null;
            if (is_array($rawPhotos)) {
                $this->propertyPhotos = $rawPhotos;
            } elseif (is_string($rawPhotos) && $rawPhotos !== '') {
                $this->propertyPhotos = [$rawPhotos];
            } else {
                $this->propertyPhotos = [];
            }
            $this->listingDocuments = $auction->get->listing_documents ?? null;

            // Location and meeting details
            $this->person_meeting = $auction->get->person_meeting ?? null;
            $this->meeting_details_first_name = $auction->get->meeting_details_first_name ?? null;
            $this->meeting_details_last_name = $auction->get->meeting_details_last_name ?? null;
            $this->meeting_details_phone = $auction->get->meeting_details_phone ?? null;
            $this->meeting_details_email = $auction->get->meeting_details_email ?? null;
            $this->address = $auction->get->address ?? null;
            $this->meeting_details_meeting_time = $auction->get->meeting_details_meeting_time ?? null;
            $this->meeting_details_time_zone = $auction->get->meeting_details_time_zone ?? null;
            $this->meeting_details_meeting_date = $auction->get->meeting_details_meeting_date ?? null;
            $this->meeting_details_instructions = $auction->get->meeting_details_instructions ?? null;
            $this->meeting_details_additional_details = $auction->get->meeting_details_additional_details ?? null;
            $this->service_completion_date = $auction->get->service_completion_date ?? null;
            $this->service_completion_time = $auction->get->service_completion_time ?? null;
            $this->service_time_zone = $auction->get->service_time_zone ?? null;

            // Marketing services
            $this->list_criteria = (bool)($auction->get->list_criteria ?? false);
            $this->list_criteria_fee = $auction->get->list_criteria_fee ?? null;
            $this->market_groups = (bool)($auction->get->market_groups ?? false);
            $this->market_groups_fee = $auction->get->market_groups_fee ?? null;
            $this->promote_social = (bool)($auction->get->promote_social ?? false);
            $this->promote_social_fee = $auction->get->promote_social_fee ?? null;
            $this->launch_ads = (bool)($auction->get->launch_ads ?? false);
            $this->launch_ads_fee = $auction->get->launch_ads_fee ?? null;
            $this->include_marketing_fee = (bool)($auction->get->include_marketing_fee ?? false);
            $this->marketing_materials_fee = $auction->get->marketing_materials_fee ?? null;
            $this->email_notifications_fee = $auction->get->email_notifications_fee ?? null;
            $this->off_market_search_fee = $auction->get->off_market_search_fee ?? null;
            $this->mls_filter_fee = $auction->get->mls_filter_fee ?? null;
            $this->email_marketing_fee = $auction->get->email_marketing_fee ?? null;

            // Property showings
            $this->schedule_showings = (bool)($auction->get->schedule_showings ?? false);
            $this->number_of_showings_to_schedule = $auction->get->number_of_showings_to_schedule ?? null;
            $this->schedule_showings_fee = $auction->get->schedule_showings_fee ?? null;
            $this->attend_showings = (bool)($auction->get->attend_showings ?? false);
            $this->number_of_showings_to_attend = $auction->get->number_of_showings_to_attend ?? null;
            $this->attend_showings_fee = $auction->get->attend_showings_fee ?? null;
            $this->provide_virtual_tours = (bool)($auction->get->provide_virtual_tours ?? false);
            $this->number_of_virtual_tours = $auction->get->number_of_virtual_tours ?? null;
            $this->virtual_tours_fee = $auction->get->virtual_tours_fee ?? null;

            // Application & lease support
            $this->assist_application = (bool)($auction->get->assist_application ?? false);
            $this->assist_application_fee = $auction->get->assist_application_fee ?? null;
            $this->collect_documents = (bool)($auction->get->collect_documents ?? false);
            $this->collect_documents_fee = $auction->get->collect_documents_fee ?? null;
            $this->submit_application = (bool)($auction->get->submit_application ?? false);
            $this->submit_application_fee = $auction->get->submit_application_fee ?? null;
            $this->review_lease = (bool)($auction->get->review_lease ?? false);
            $this->review_lease_fee = $auction->get->review_lease_fee ?? null;
            $this->provide_lease_form = (bool)($auction->get->provide_lease_form ?? false);
            $this->provide_lease_form_fee = $auction->get->provide_lease_form_fee ?? null;
            $this->coordinate_signing = (bool)($auction->get->coordinate_signing ?? false);
            $this->coordinate_signing_fee = $auction->get->coordinate_signing_fee ?? null;
            $this->prepare_application_fee = $auction->get->prepare_application_fee ?? null;

            // add by AT


            $this->broker_fee_timing = $auction->get->broker_fee_timing ?? null;
            $this->broker_fee_timing_other = $auction->get->broker_fee_timing_other ?? null;
            $this->broker_fee_days_from_rent = $auction->get->broker_fee_days_from_rent ?? null;
            $this->broker_fee_days_after_lease = $auction->get->broker_fee_days_after_lease ?? null;
            $this->broker_fee_days_after_rent = $auction->get->broker_fee_days_after_rent ?? null;
            $this->sales_tax_option_gross = $auction->get->sales_tax_option_gross ?? null;
            $this->purchase_fee_monthly_percentage = $auction->get->purchase_fee_monthly_percentage ?? null;
            $this->purchase_fee_months = $auction->get->purchase_fee_months ?? null;
            $this->sales_tax_option_monthly = $auction->get->sales_tax_option_monthly ?? null;
            $this->purchase_fee_flat_commercial = $auction->get->purchase_fee_flat_commercial ?? null;
            $this->sales_tax_option_flat = $auction->get->sales_tax_option_flat ?? null;
            $this->purchase_fee_purchase_price = $auction->get->purchase_fee_purchase_price ?? null;
            $this->purchase_fee_other_commercial = $auction->get->purchase_fee_other_commercial ?? null;
            $this->split_payment_due = $auction->get->split_payment_due ?? null;
            $this->broker_fee_days_after_due_event = $auction->get->broker_fee_days_after_due_event ?? null;
            $this->renewal_fee_type = $auction->get->renewal_fee_type ?? null;
            $this->renewal_fee_percentage = $auction->get->renewal_fee_percentage ?? null;
            $this->renewal_fee_custom = $auction->get->renewal_fee_custom ?? null;
            $this->net_aggregate_rent = $auction->get->net_aggregate_rent ?? null;
            $this->month_percentage_rent = $auction->get->month_percentage_rent ?? null;
            $this->no_of_months = $auction->get->no_of_months ?? null;
            $this->flat_fee = $auction->get->flat_fee ?? null;
            $this->gross_percentage_rent = $auction->get->gross_percentage_rent ?? null;
            $this->purchase_fee_net_aggregate = $auction->get->purchase_fee_net_aggregate ?? null;
            $this->tenant_broker_commission_structure = $auction->get->tenant_broker_commission_structure ?? null;
            $this->expansion_commission_percentage = $auction->get->expansion_commission_percentage ?? null;
            $this->tenant_broker_commission_percentage = $auction->get->tenant_broker_commission_percentage ?? null;
            $this->tenant_broker_fee_structure = $auction->get->tenant_broker_fee_structure ?? null;
            $this->tenant_broker_percentage = $auction->get->tenant_broker_percentage ?? null;
            $this->tenant_broker_flat_fee = $auction->get->tenant_broker_flat_fee ?? null;
            $this->expansion_commission_type = $auction->get->expansion_commission_type ?? null;
            $this->expansion_gross_percentage = $auction->get->expansion_gross_percentage ?? null;
            $this->expansion_first_month_percentage = $auction->get->expansion_first_month_percentage ?? null;
            $this->expansion_flat_fee = $auction->get->expansion_flat_fee ?? null;
            $this->expansion_custom_commission = $auction->get->expansion_custom_commission ?? null;
            $this->interested_lease_option_agreement = $auction->get->interested_lease_option_agreement ?? null;
            $this->lease_type = $auction->get->lease_type ?? 'percent';
            $this->lease_value = $auction->get->lease_value ?? null;
            $this->purchase_type = $auction->get->purchase_type ?? 'percent';
            $this->purchase_value = $auction->get->purchase_value ?? null;
            $this->landlord_broker_purchase_price = $auction->get->landlord_broker_purchase_price ?? null;
            $this->landlord_broker_percentage_price = $auction->get->landlord_broker_percentage_price ?? null;
            $this->landlord_broker_dollar_price = $auction->get->landlord_broker_dollar_price ?? null;
            $this->landlord_broker_flate_fee = $auction->get->landlord_broker_flate_fee ?? null;
            $this->landlord_broker_other = $auction->get->landlord_broker_other ?? null;
            $this->interested_in_property_management = $auction->get->interested_in_property_management ?? null;
            $this->interested_in_property_management_fee = $auction->get->interested_in_property_management_fee ?? null;
            $this->interested_in_property_management_fee_flate_free = $auction->get->interested_in_property_management_fee_flate_free ?? null;
            $this->interested_in_property_management_fee_gross_lease = $auction->get->interested_in_property_management_fee_gross_lease ?? null;
            $this->interested_in_property_management_fee_other = $auction->get->interested_in_property_management_fee_other ?? null;
            $this->interested_in_property_management_fee_rental_periord = $auction->get->interested_in_property_management_fee_rental_periord ?? null;
            $this->interested_in_selling = $auction->get->interested_in_selling ?? null;
            $this->interested_in_selling_type = $auction->get->interested_in_selling_type ?? null;
            $this->lease_fee_flat_type = $auction->get->lease_fee_flat_type ?? null;
            $this->purchase_fee_flat_type = $auction->get->purchase_fee_flat_type ?? null;
            $this->purchase_fee_gross_rent = $auction->get->purchase_fee_gross_rent ?? null;
            $this->purchase_fee_rental_period = $auction->get->purchase_fee_rental_period ?? null;
            $this->split_payment_due_other = $auction->get->split_payment_due_other ?? null;
            $this->tenant_broker_first_month_rent = $auction->get->tenant_broker_first_month_rent ?? null;
            $this->tenant_broker_gross_lease = $auction->get->tenant_broker_gross_lease ?? null;
            $this->tenant_broker_other = $auction->get->tenant_broker_other ?? null;
            $this->renewal_fee_first_month = $auction->get->renewal_fee_first_month ?? null;
            $this->renewal_fee_flat_free = $auction->get->renewal_fee_flat_free ?? null;
            $this->renewal_fee_lease_value = $auction->get->renewal_fee_lease_value ?? null;
            $this->renewal_fee_no_of_months = $auction->get->renewal_fee_no_of_months ?? null;
            $this->renewal_fee_sales_tax_first_month = $auction->get->renewal_fee_sales_tax_first_month ?? null;
            $this->renewal_fee_sales_tax_flat_fee = $auction->get->renewal_fee_sales_tax_flat_fee ?? null;
            $this->renewal_fee_sales_tax_lease_value = $auction->get->renewal_fee_sales_tax_lease_value ?? null;
            $this->additional_details_broker = $auction->get->additional_details_broker ?? null;
            $this->agent_bid_visibility = $auction->get->agent_bid_visibility ?? null;
            $this->total_square_feet = $auction->get->total_square_feet ?? null;
            $this->sqft_heated_source = $auction->get->sqft_heated_source ?? null;
            $this->meeting_Preference = $auction->get->meeting_Preference ?? null;

            $this->photo_enhancements = $this->ensureArray($auction->get->photo_enhancements ?? null);

            $this->custom_enhancement = $auction->get->custom_enhancement ?? null;
            $this->showEnhancements = !empty($this->photo_enhancements);
            $this->showCustomEnhancement = in_array('Other', $this->photo_enhancements);
            $this->has_breed_restrictions = $auction->get->has_breed_restrictions ?? null;
            $this->breed_restrictions = $auction->get->breed_restrictions ?? null;
            $this->service_animal = $auction->get->service_animal ?? null;
            $this->support_animal = $auction->get->support_animal ?? null;

            // MLS Property Detail Fields
            $this->year_built = $auction->get->year_built ?? '';
            $this->heating_fuel = $this->ensureArray(json_decode($auction->get->heating_fuel ?? '[]', true));
            $this->other_heating_fuel = $auction->get->other_heating_fuel ?? '';
            $this->air_conditioning = $this->ensureArray(json_decode($auction->get->air_conditioning ?? '[]', true));
            $this->other_air_conditioning = $auction->get->other_air_conditioning ?? '';
            $this->water = $this->ensureArray(json_decode($auction->get->water ?? '[]', true));
            $this->other_water = $auction->get->other_water ?? '';
            $this->sewer = $this->ensureArray(json_decode($auction->get->sewer ?? '[]', true));
            $this->other_sewer = $auction->get->other_sewer ?? '';
            $this->property_utilities = $this->ensureArray(json_decode($auction->get->property_utilities ?? '[]', true));
            $this->other_property_utilities = $auction->get->other_property_utilities ?? '';
            $this->laundry_features = $this->ensureArray(json_decode($auction->get->laundry_features ?? '[]', true));
            $this->other_laundry_features = $auction->get->other_laundry_features ?? '';
            $this->floor_covering = $this->ensureArray(json_decode($auction->get->floor_covering ?? '[]', true));
            $this->other_floor_covering = $auction->get->other_floor_covering ?? '';
            $this->security_features = $this->ensureArray(json_decode($auction->get->security_features ?? '[]', true));
            $this->other_security_features = $auction->get->other_security_features ?? '';
            $this->zoning = $auction->get->zoning ?? '';
            $this->total_buildings = $auction->get->total_buildings ?? '';
            $this->total_units_on_property = $auction->get->total_units_on_property ?? '';
            $this->office_retail_sqft = $auction->get->office_retail_sqft ?? '';
            $this->flex_space_sqft = $auction->get->flex_space_sqft ?? '';
            $this->road_surface_type = $this->ensureArray(json_decode($auction->get->road_surface_type ?? '[]', true));
            $this->other_road_surface_type = $auction->get->other_road_surface_type ?? '';
            $this->electrical_service = $this->ensureArray(json_decode($auction->get->electrical_service ?? '[]', true));
            $this->other_electrical_service = $auction->get->other_electrical_service ?? '';
            $this->ceiling_height = $auction->get->ceiling_height ?? '';
            $this->building_features = $this->ensureArray(json_decode($auction->get->building_features ?? '[]', true));
            $this->other_building_features = $auction->get->other_building_features ?? '';
            $this->number_electric_meters = $auction->get->number_electric_meters ?? '';
            $this->number_water_meters = $auction->get->number_water_meters ?? '';
            $this->number_gas_meters = $auction->get->number_gas_meters ?? '';
            $this->space_type = $this->ensureArray(json_decode($auction->get->space_type ?? '[]', true));
            $this->other_space_type = $auction->get->other_space_type ?? '';
            $this->space_classification = $this->ensureArray(json_decode($auction->get->space_classification ?? '[]', true));
            $this->other_space_classification = $auction->get->other_space_classification ?? '';
            $this->number_of_restrooms = $auction->get->number_of_restrooms ?? '';
            $this->number_of_offices = $auction->get->number_of_offices ?? '';
            $this->number_of_conference_rooms = $auction->get->number_of_conference_rooms ?? '';

            // End by AT

            // Move services
            $this->move_in_inspection_fee = $auction->get->move_in_inspection_fee ?? null;
            $this->moving_resources_fee = $auction->get->moving_resources_fee ?? null;
            $this->short_term_housing_fee = $auction->get->short_term_housing_fee ?? null;

            // Advisory services
            $this->rental_rights_fee = $auction->get->rental_rights_fee ?? null;
            $this->lease_advice_fee = $auction->get->lease_advice_fee ?? null;

            // Neighborhood marketing
            $this->neighborhood_insights_fee = $auction->get->neighborhood_insights_fee ?? null;
            $this->neighborhood_marketing_fee = $auction->get->neighborhood_marketing_fee ?? null;
            $this->neighborhood_materials_fee = $auction->get->neighborhood_materials_fee ?? null;

            // Custom services

            $this->custom_services = $this->ensureArray($auction->get->custom_services ?? null);

            $this->total_marketing_fee = $auction->get->total_marketing_fee ?? null;
            $this->total_flat_fee = $auction->get->total_flat_fee ?? null;

            // Flat fee agent (limited service) tenant

            $this->fees = $this->ensureArray($auction->get->fees ?? null);
            $loadedEnable = $this->ensureArray($auction->get->enable ?? null);
            if (!empty($loadedEnable)) {
                $this->enable = array_replace($this->enable, $loadedEnable);
            }

            $this->showings_count = $auction->get->showings_count ?? null;
            $this->attend_showings_count = $auction->get->attend_showings_count ?? null;
            $this->virtual_tours_count = $auction->get->virtual_tours_count ?? null;
            $this->understand_terms = (bool)($auction->get->understand_terms ?? false);

            // Seller
            $this->staging_duration = $auction->get->staging_duration ?? null;
            $this->open_house_count = $auction->get->open_house_count ?? null;

            // Landlord
            $this->virtual_showings_count = $auction->get->virtual_showings_count ?? null;

            // Landlord Lease Terms Questions
            $this->lease_available_date = $auction->get->lease_available_date ?? '';
            $this->security_deposit_required = $auction->get->security_deposit_required ?? '';
            $this->first_month_rent_required = $auction->get->first_month_rent_required ?? '';
            $this->last_month_rent_required = $auction->get->last_month_rent_required ?? '';
            $this->total_move_in_funds_required = $auction->get->total_move_in_funds_required ?? '';
            $this->pet_policy = $auction->get->pet_policy ?? '';
            $this->pet_deposit_fee_rent = $auction->get->pet_deposit_fee_rent ?? '';
            $this->number_of_occupants_allowed = $auction->get->number_of_occupants_allowed ?? '';
            $this->parking_terms = $auction->get->parking_terms ?? '';
            $this->utility_responsibility = $auction->get->utility_responsibility ?? '';
            $this->ll_maintenance_responsibility = $auction->get->ll_maintenance_responsibility ?? '';
            $this->renewal_option_offered = $auction->get->renewal_option_offered ?? '';
            $this->renewal_option_details = $auction->get->renewal_option_details ?? '';
            $this->landlord_approval_conditions = $auction->get->landlord_approval_conditions ?? '';
            $this->additional_landlord_lease_terms = $auction->get->additional_landlord_lease_terms ?? '';
            $this->commercial_lease_type = $auction->get->commercial_lease_type ?? '';
            $this->commercial_lease_type_other = $auction->get->commercial_lease_type_other ?? '';
            $this->cam_nnn_additional_rent_charges = $auction->get->cam_nnn_additional_rent_charges ?? '';
            $this->rent_escalation_terms = $auction->get->rent_escalation_terms ?? '';
            $this->tenant_improvement_buildout_terms = $auction->get->tenant_improvement_buildout_terms ?? '';
            $this->permitted_use_restrictions = $auction->get->permitted_use_restrictions ?? '';
            $this->signage_rights = $auction->get->signage_rights ?? '';
            $this->commercial_parking_terms = $auction->get->commercial_parking_terms ?? '';
            $this->personal_guarantee_requirement = $auction->get->personal_guarantee_requirement ?? '';
            $this->commercial_approval_conditions = $auction->get->commercial_approval_conditions ?? '';

            // Tax, Legal, HOA & Disclosures tab
            $this->parcel_id = $auction->get->parcel_id ?? '';
            $this->tax_year = $auction->get->tax_year ?? '';
            $this->annual_property_taxes = $auction->get->annual_property_taxes ?? '';
            $this->additional_parcels = $auction->get->additional_parcels ?? '';
            $this->total_parcel_count = $auction->get->total_parcel_count ?? '';
            $this->additional_parcel_ids = $auction->get->additional_parcel_ids ?? '';
            $this->legal_description = $auction->get->legal_description ?? '';
            $this->flood_zone_code = $auction->get->flood_zone_code ?? '';
            $this->flood_zone_code_other = $auction->get->flood_zone_code_other ?? '';
            $this->flood_insurance_required = $auction->get->flood_insurance_required ?? '';
            $this->flood_zone_panel = $auction->get->flood_zone_panel ?? '';
            $this->has_cdd = $auction->get->has_cdd ?? '';
            $this->annual_cdd_fee = $auction->get->annual_cdd_fee ?? '';
            $this->has_special_assessments = $auction->get->has_special_assessments ?? '';
            $this->special_assessment_amount = $auction->get->special_assessment_amount ?? '';
            $this->special_assessment_description = $auction->get->special_assessment_description ?? '';
            $this->has_hoa = $auction->get->has_hoa ?? '';
            $this->association_type = $auction->get->association_type ?? '';
            $this->association_type_other = $auction->get->association_type_other ?? '';
            $this->association_name = $auction->get->association_name ?? '';
            $this->association_fee_amount = $auction->get->association_fee_amount ?? '';
            $this->association_fee_frequency = $auction->get->association_fee_frequency ?? '';
            $this->association_fee_frequency_other = $auction->get->association_fee_frequency_other ?? '';
            $this->association_approval_required = $auction->get->association_approval_required ?? '';
            $this->association_approval_process = $auction->get->association_approval_process ?? '';
            $this->association_application_fee = $auction->get->association_application_fee ?? '';
            $rawFeeIncludes = $auction->get->association_fee_includes ?? [];
            $this->association_fee_includes = is_string($rawFeeIncludes) ? json_decode($rawFeeIncludes, true) ?? [] : (array)$rawFeeIncludes;
            $this->association_fee_includes_other = $auction->get->association_fee_includes_other ?? '';
            $rawAmenities = $auction->get->association_amenities ?? [];
            $this->association_amenities = is_string($rawAmenities) ? json_decode($rawAmenities, true) ?? [] : (array)$rawAmenities;
            $this->association_amenities_other = $auction->get->association_amenities_other ?? '';
            $this->leasing_restrictions = $auction->get->leasing_restrictions ?? '';
            $this->min_lease_period = $auction->get->min_lease_period ?? '';
            $this->min_lease_period_other = $auction->get->min_lease_period_other ?? '';
            $this->max_leases_per_year = $auction->get->max_leases_per_year ?? '';
            $this->additional_lease_restrictions = $auction->get->additional_lease_restrictions ?? '';
            $this->pet_restrictions = $auction->get->pet_restrictions ?? '';
            $this->pet_restrictions_detail = $auction->get->pet_restrictions_detail ?? '';

            // Documents & Disclosures
            $this->landlord_disclosure_available = $auction->get->landlord_disclosure_available ?? '';
            $this->survey_available = $auction->get->survey_available ?? '';
            $this->inspection_report_available = $auction->get->inspection_report_available ?? '';
            $this->hoa_condo_docs_available = $auction->get->hoa_condo_docs_available ?? '';
            $this->flood_disclosure_available = $auction->get->flood_disclosure_available ?? '';
            $this->lead_based_paint_disclosure = $auction->get->lead_based_paint_disclosure ?? '';
            $this->environmental_report_available = $auction->get->environmental_report_available ?? '';
            $rawAdditionalDocs = $auction->get->additional_documents ?? [];
            $this->additional_documents = is_string($rawAdditionalDocs) ? json_decode($rawAdditionalDocs, true) ?? [] : (array)$rawAdditionalDocs;
            $this->other_document_type = $auction->get->other_document_type ?? '';

            // Repeatable document rows
            $rawLandlordDocRows = $auction->get->landlord_doc_rows ?? null;
            if (is_string($rawLandlordDocRows)) {
                $decoded = json_decode($rawLandlordDocRows, true);
                $this->landlord_doc_rows = is_array($decoded) ? $decoded : [];
            } elseif (is_array($rawLandlordDocRows)) {
                $this->landlord_doc_rows = $rawLandlordDocRows;
            }

            // Disclosure file paths (for "View current file" links when resuming a draft)
            $this->landlord_disclosure_file_path  = $auction->get->landlord_disclosure_file_path ?? '';
            $this->survey_file_path               = $auction->get->survey_file_path ?? '';
            $this->inspection_report_file_path    = $auction->get->inspection_report_file_path ?? '';
            $this->hoa_condo_docs_file_path        = $auction->get->hoa_condo_docs_file_path ?? '';
            $this->flood_disclosure_file_path     = $auction->get->flood_disclosure_file_path ?? '';
            $this->lead_based_paint_file_path     = $auction->get->lead_based_paint_file_path ?? '';
            $this->environmental_report_file_path = $auction->get->environmental_report_file_path ?? '';

            // Load enable checkboxes
            // $enableFields = json_decode($auction->get->enable);
            // foreach ($enableFields as $field => $value) {
            //     if (property_exists($this, 'enable') && array_key_exists($field, $this->enable)) {
            //         $this->enable[$field] = $value;
            //     }
            // }
            
            $this->dispatchBrowserEvent('draftLoaded', [
                'appliances' => $this->ensureArray($this->appliances),
                'offered_financing' => $this->ensureArray($this->offered_financing),
                'tenant_pays' => $this->ensureArray($this->tenant_pays),
                'owner_pays' => $this->ensureArray($this->owner_pays),
                'terms_of_lease' => $this->ensureArray($this->terms_of_lease),
                'rent_includes' => $this->ensureArray($this->rent_includes),
                'desired_lease_length' => $this->ensureArray($this->desired_lease_length),
                'view_preference' => $this->ensureArray($this->view_preference),
                'non_negotiable_amenities' => $this->ensureArray($this->non_negotiable_amenities),
                'lease_for' => $this->ensureArray($this->lease_for),
                'credit_scroe_rating' => $this->ensureArray($this->credit_scroe_rating),
                'services' => $this->ensureArray($this->services),
                'pool_type' => $this->ensureArray($this->pool_type),
                'photo_enhancements' => $this->ensureArray($this->photo_enhancements),
                'property_items' => $this->ensureArray($this->property_items),
                'tenant_require' => $this->ensureArray($this->tenant_require),
                // MLS Property Detail Fields
                'heating_fuel' => $this->ensureArray($this->heating_fuel),
                'air_conditioning' => $this->ensureArray($this->air_conditioning),
                'water' => $this->ensureArray($this->water),
                'sewer' => $this->ensureArray($this->sewer),
                'property_utilities' => $this->ensureArray($this->property_utilities),
                'laundry_features' => $this->ensureArray($this->laundry_features),
                'floor_covering' => $this->ensureArray($this->floor_covering),
                'security_features' => $this->ensureArray($this->security_features),
                'road_surface_type' => $this->ensureArray($this->road_surface_type),
                'electrical_service' => $this->ensureArray($this->electrical_service),
                'building_features' => $this->ensureArray($this->building_features),
                'space_type' => $this->ensureArray($this->space_type),
                'space_classification' => $this->ensureArray($this->space_classification),
            ]);
        }
    }

    protected function ensureArray($value)
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }
        return [];
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
        $auction->saveMeta('auction_time', $this->auction_type === 'Bidding Period' ? $this->auction_time : '');

        // Location Information
        $auction->saveMeta('cities', json_encode($this->ensureArray($this->cities)));
        $auction->saveMeta('counties', json_encode($this->ensureArray($this->counties)));
        $auction->saveMeta('state', $this->state);
        $auction->saveMeta('zip_code', $this->zip_code);
        $auction->saveMeta('zipCodes', json_encode($this->ensureArray($this->zipCodes)));
        $auction->saveMeta('property_city', $this->property_city);
        $auction->saveMeta('property_county', $this->property_county);
        $auction->saveMeta('property_state', $this->property_state);
        $auction->saveMeta('property_zip', $this->property_zip);

        // Property Details
        $auction->saveMeta('property_type', $this->property_type);
        $auction->saveMeta('property_items', json_encode($this->ensureArray($this->property_items)));
        $auction->saveMeta('leasing_space', $this->leasing_space);
        $auction->saveMeta('other_property_items', $this->other_property_items);
        $auction->saveMeta('condition_prop', $this->condition_prop);
        $auction->saveMeta('other_property_condition', $this->other_property_condition);
        $auction->saveMeta('bathrooms', $this->bathrooms);
        $auction->saveMeta('other_bathrooms', $this->other_bathrooms);
        $auction->saveMeta('bedrooms', $this->bedrooms);
        $auction->saveMeta('other_bedrooms', $this->other_bedrooms);
        $auction->saveMeta('minimum_heated_square', $this->minimum_heated_square);
        $auction->saveMeta('minimum_leaseable', $this->minimum_leaseable);
        $auction->saveMeta('min_acreage', $this->min_acreage);
        $auction->saveMeta('total_acreage', $this->total_acreage);
        $auction->saveMeta('minimum_cap_rate', $this->minimum_cap_rate);
        $auction->saveMeta('assets', $this->assets);
        $auction->saveMeta('assets_other', $this->assets_other);
        $auction->saveMeta('property_criteria', $this->property_criteria);
        $auction->saveMeta('unit_size', $this->unit_size);
        $auction->saveMeta('unit_size_other', $this->unit_size_other);
        $auction->saveMeta('appliances', json_encode($this->ensureArray($this->appliances)));
        $auction->saveMeta('appliances_other', $this->appliances_other);
        $auction->saveMeta('other_appliances', $this->other_appliances);
        $auction->saveMeta('preferance_details', $this->preferance_details);

        // Sale Provisions
        $auction->saveMeta('sale_provision', $this->sale_provision);
        $auction->saveMeta('sale_provision_other', $this->sale_provision_other);
        $auction->saveMeta('sale_provision_assignment', $this->sale_provision_assignment);
        $auction->saveMeta('assignment_fee_type', $this->assignment_fee_type);
        $auction->saveMeta('assignment_fee_amount', $this->assignment_fee_amount);
        $auction->saveMeta('buyer_sell_contract', $this->buyer_sell_contract);

        // Budget & Financing
        $auction->saveMeta('maximum_budget', $this->maximum_budget);
        $auction->saveMeta('offered_financing', json_encode($this->ensureArray($this->offered_financing)));
        $auction->saveMeta('other_financing', $this->other_financing);
        $auction->saveMeta('cash_budget', $this->cash_budget);
        $auction->saveMeta('pre_approved', $this->pre_approved);
        $auction->saveMeta('pre_approval_amount', $this->pre_approval_amount);
        $auction->saveMeta('purchase_price', $this->purchase_price);
        $auction->saveMeta('down_payment_type', $this->down_payment_type);
        $auction->saveMeta('down_payment_amount', $this->down_payment_amount);
        $auction->saveMeta('seller_financing_type', $this->seller_financing_type);
        $auction->saveMeta('seller_financing_amount', $this->seller_financing_amount);
        $auction->saveMeta('interest_rate', $this->interest_rate);
        $auction->saveMeta('loan_duration', $this->loan_duration);
        $auction->saveMeta('prepayment_penalty', $this->prepayment_penalty);
        $auction->saveMeta('prepayment_penalty_amount', $this->prepayment_penalty_amount);
        $auction->saveMeta('balloon_payment_amount', $this->balloon_payment_amount);
        $auction->saveMeta('balloon_payment_date', $this->balloon_payment_date);
        $auction->saveMeta('assumable_terms', $this->assumable_terms);
        $auction->saveMeta('max_assumable_rate', $this->max_assumable_rate);
        $auction->saveMeta('max_monthly_payment', $this->max_monthly_payment);
        $auction->saveMeta('gap_payment_type', $this->gap_payment_type);
        $auction->saveMeta('gap_payment_amount', $this->gap_payment_amount);

        // Exchange / Trade
        $auction->saveMeta('exchange_item', $this->exchange_item);
        $auction->saveMeta('other_exchange_item', $this->other_exchange_item);
        $auction->saveMeta('exchange_item_value', $this->exchange_item_value);
        $auction->saveMeta('exchange_item_condition', $this->exchange_item_condition);
        $auction->saveMeta('additional_cash', $this->additional_cash);
        $auction->saveMeta('value_determination', $this->value_determination);

        // Lease Option
        $auction->saveMeta('lease_option_price', $this->lease_option_price);
        $auction->saveMeta('lease_option_terms', $this->lease_option_terms);
        $auction->saveMeta('lease_option_duration', $this->lease_option_duration);
        $auction->saveMeta('lease_option_payment', $this->lease_option_payment);
        $auction->saveMeta('lease_option_conditions', $this->lease_option_conditions);
        $auction->saveMeta('has_option_fee', $this->has_option_fee);
        $auction->saveMeta('option_fee_amount', $this->option_fee_amount);

        // Lease Purchase
        $auction->saveMeta('lease_purchase_price', $this->lease_purchase_price);
        $auction->saveMeta('lease_purchase_terms', $this->lease_purchase_terms);
        $auction->saveMeta('lease_purchase_duration', $this->lease_purchase_duration);
        $auction->saveMeta('lease_purchase_payment', $this->lease_purchase_payment);
        $auction->saveMeta('lease_purchase_conditions', $this->lease_purchase_conditions);
        $auction->saveMeta('lease_purchase_option_fee', $this->lease_purchase_option_fee);
        $auction->saveMeta('lease_purchase_option_fee_amount', $this->lease_purchase_option_fee_amount);

        // Cryptocurrency
        $auction->saveMeta('cryptocurrency_type', $this->cryptocurrency_type);
        $auction->saveMeta('crypto_percentage', $this->crypto_percentage);
        $auction->saveMeta('cash_percentage_crypto', $this->cash_percentage_crypto);

        // NFT
        $auction->saveMeta('nft_description', $this->nft_description);
        $auction->saveMeta('nft_percentage', $this->nft_percentage);
        $auction->saveMeta('cash_percentage_nft', $this->cash_percentage_nft);

        // Amenities and Features
        $auction->saveMeta('tenant_require', json_encode($this->ensureArray($this->tenant_require)));
        $auction->saveMeta('carport_needed', $this->carport_needed);
        $auction->saveMeta('other_carport_needed', $this->other_carport_needed);
        $auction->saveMeta('occupant_types', $this->occupant_types);
        $auction->saveMeta('occupant_types_tenant', $this->occupant_types_tenant);
        $auction->saveMeta('leasing_space_property', $this->leasing_space_property);
        $auction->saveMeta('lease_amount_frequency', $this->lease_amount_frequency);
        $auction->saveMeta('desired_lease_length', json_encode($this->ensureArray($this->desired_lease_length)));
        $auction->saveMeta('desired_rental_amount', $this->desired_rental_amount);
        $auction->saveMeta('starting_rent', $this->starting_rent);
        $auction->saveMeta('reserve_rent', $this->reserve_rent);
        $auction->saveMeta('lease_now_price', $this->lease_now_price);
        $auction->saveMeta('desired_rental_amount_tenant', $this->desired_rental_amount_tenant);
        $auction->saveMeta('rent_includes', json_encode($this->ensureArray($this->rent_includes)));
        $auction->saveMeta('terms_of_lease', json_encode($this->ensureArray($this->terms_of_lease)));
        $auction->saveMeta('tenant_pays', json_encode($this->ensureArray($this->tenant_pays)));
        $auction->saveMeta('owner_pays', json_encode($this->ensureArray($this->owner_pays)));
        $auction->saveMeta('other_tenant_pays', $this->other_tenant_pays);
        $auction->saveMeta('other_owner_pays', $this->other_owner_pays);
        $auction->saveMeta('tenant_pays_other', $this->tenant_pays_other);
        $auction->saveMeta('owner_pays_other', $this->owner_pays_other);
        $auction->saveMeta('custom_lease_term', $this->custom_lease_term);
        $auction->saveMeta('other_lease_term', $this->other_lease_term);
        $auction->saveMeta('other_rent_include', $this->other_rent_include);



        $auction->saveMeta('garage_needed', $this->garage_needed);
        $auction->saveMeta('other_garage_needed', $this->other_garage_needed);
        $auction->saveMeta('garage_parking_spaces', $this->garage_parking_spaces);
        $auction->saveMeta('garage_parking_spaces_option', $this->garage_parking_spaces_option);
        $auction->saveMeta('other_parking_space_wrapper', $this->other_parking_space_wrapper);
        $auction->saveMeta('pool_needed', $this->pool_needed);
        $auction->saveMeta('pool_type', json_encode($this->ensureArray($this->pool_type)));
        $auction->saveMeta('view_preference', json_encode($this->ensureArray($this->view_preference)));
        $auction->saveMeta('other_preferences', $this->other_preferences);
        $auction->saveMeta('real_estate_purchase', $this->real_estate_purchase);
        $auction->saveMeta('number_of_unit', $this->number_of_unit);
        $auction->saveMeta('number_of_unit_other', $this->number_of_unit_other);
        $auction->saveMeta('minimum_annual_net_income', $this->minimum_annual_net_income);
        $auction->saveMeta('leasing_55_plus', $this->leasing_55_plus);
        $auction->saveMeta('occupant_status', $this->occupant_status);
        $auction->saveMeta('occupant_tenant', $this->occupant_tenant);
        $auction->saveMeta('leasing_spaces', $this->leasing_spaces);
        $auction->saveMeta('restrictions', $this->restrictions);
        $auction->saveMeta('maintenance_by', $this->maintenance_by);
        $auction->saveMeta('maintenance_response_time', $this->maintenance_response_time);
        $auction->saveMeta('included_storage_space_res_both', $this->included_storage_space_res_both);
        $auction->saveMeta('storage_space_res_both', $this->storage_space_res_both);
        $auction->saveMeta('guests_allowed', $this->guests_allowed);
        $auction->saveMeta('common_areas_access', $this->common_areas_access);
        $auction->saveMeta('utilities', $this->utilities);
        $auction->saveMeta('common_areas_cleaning', $this->common_areas_cleaning);
        $auction->saveMeta('included_storage_space_res_single', $this->included_storage_space_res_single);
        $auction->saveMeta('storage_space_res_single', $this->storage_space_res_single);
        $auction->saveMeta('bathroom_facilities', $this->bathroom_facilities);
        $auction->saveMeta('room_size', $this->room_size);
        $auction->saveMeta('included_storage_space_com_entire', $this->included_storage_space_com_entire);
        $auction->saveMeta('storage_space_com_entire', $this->storage_space_com_entire);
        $auction->saveMeta('shared_amenities', $this->shared_amenities);
        $auction->saveMeta('building_hours', $this->building_hours);
        $auction->saveMeta('access_24_7', $this->access_24_7);
        $auction->saveMeta('zoning_allows', $this->zoning_allows);
        $auction->saveMeta('space_features', $this->space_features);
        $auction->saveMeta('neighboring_tenants', $this->neighboring_tenants);
        $auction->saveMeta('included_storage_space_com_single', $this->included_storage_space_com_single);
        $auction->saveMeta('storage_space_com_single', $this->storage_space_com_single);
        $auction->saveMeta('maintenance_handler', $this->maintenance_handler);
        $auction->saveMeta('included_storage_space', $this->included_storage_space);
        $auction->saveMeta('storage_space', $this->storage_space);

        // Requirements
        $auction->saveMeta('non_negotiable_amenities', json_encode($this->ensureArray($this->non_negotiable_amenities)));
        $auction->saveMeta('other_non_negotiable_amenities', $this->other_non_negotiable_amenities);
        $auction->saveMeta('budget', $this->budget);

        // Lease Terms
        $auction->saveMeta('lease_for', json_encode($this->ensureArray($this->lease_for)));
        $auction->saveMeta('other_lease_for', $this->other_lease_for);
        $auction->saveMeta('lease_by', $this->lease_by);
        $auction->saveMeta('lease_date', $this->lease_date);

        // Tenant Information
        $auction->saveMeta('pets', $this->pets);
        $auction->saveMeta('number_of_pets', $this->number_of_pets);
        $auction->saveMeta('breed_of_pets', $this->breed_of_pets);
        $auction->saveMeta('type_of_pets', $this->type_of_pets);
        $auction->saveMeta('weight_of_pets', $this->weight_of_pets);
        $auction->saveMeta('credit_scroe_rating', json_encode($this->ensureArray($this->credit_scroe_rating)));
        $auction->saveMeta('prior_eviction', $this->prior_eviction);
        $auction->saveMeta('eviction_explanation', $this->eviction_explanation);
        $auction->saveMeta('prior_felony', $this->prior_felony);
        $auction->saveMeta('prior_felony_explanation', $this->prior_felony_explanation);
        $auction->saveMeta('monthly_income', $this->monthly_income);
        $auction->saveMeta('number_occupant', $this->number_occupant);

        // Services
        $auction->saveMeta('services', json_encode($this->ensureArray($this->services)));
        $auction->saveMeta('other_services', $this->other_services);
        $auction->saveMeta('flat_fee_services', json_encode($this->ensureArray($this->flat_fee_services)));
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
        $auction->saveMeta('retainer_fee_option', $this->retainer_fee_option);
        $auction->saveMeta('retainer_fee_amount', $this->stripCommas($this->retainer_fee_amount));
        $auction->saveMeta('retainer_fee_application', $this->retainer_fee_application);
        $auction->saveMeta('agency_agreement_timeframe', $this->agency_agreement_timeframe);
        $auction->saveMeta('agency_agreement_custom', $this->agency_agreement_custom);
        $auction->saveMeta('brokerage_relationship', $this->brokerage_relationship);
        $auction->saveMeta('interested_lease_option_agreement', $this->interested_lease_option_agreement);
        $auction->saveMeta('lease_type', $this->lease_type);
        $auction->saveMeta('lease_value', $this->lease_value);
        $auction->saveMeta('purchase_type', $this->purchase_type);
        $auction->saveMeta('purchase_value', $this->purchase_value);
        $auction->saveMeta('landlord_broker_purchase_price', $this->landlord_broker_purchase_price);
        $auction->saveMeta('landlord_broker_percentage_price', $this->landlord_broker_percentage_price);
        $auction->saveMeta('landlord_broker_dollar_price', $this->landlord_broker_dollar_price);
        $auction->saveMeta('landlord_broker_flate_fee', $this->landlord_broker_flate_fee);
        $auction->saveMeta('landlord_broker_other', $this->landlord_broker_other);
        $auction->saveMeta('interested_in_property_management', $this->interested_in_property_management);
        $auction->saveMeta('interested_in_property_management_fee', $this->interested_in_property_management_fee);
        $auction->saveMeta('interested_in_property_management_fee_flate_free', $this->interested_in_property_management_fee_flate_free);
        $auction->saveMeta('interested_in_property_management_fee_gross_lease', $this->interested_in_property_management_fee_gross_lease);
        $auction->saveMeta('interested_in_property_management_fee_other', $this->interested_in_property_management_fee_other);
        $auction->saveMeta('interested_in_property_management_fee_rental_periord', $this->interested_in_property_management_fee_rental_periord);
        $auction->saveMeta('interested_in_selling', $this->interested_in_selling);
        $auction->saveMeta('interested_in_selling_type', $this->interested_in_selling_type);
        $auction->saveMeta('lease_fee_flat_type', $this->lease_fee_flat_type);
        $auction->saveMeta('purchase_fee_flat_type', $this->purchase_fee_flat_type);
        $auction->saveMeta('purchase_fee_gross_rent', $this->purchase_fee_gross_rent);
        $auction->saveMeta('purchase_fee_rental_period', $this->purchase_fee_rental_period);
        $auction->saveMeta('split_payment_due_other', $this->split_payment_due_other);
        $auction->saveMeta('tenant_broker_first_month_rent', $this->tenant_broker_first_month_rent);
        $auction->saveMeta('tenant_broker_gross_lease', $this->tenant_broker_gross_lease);
        $auction->saveMeta('tenant_broker_other', $this->tenant_broker_other);
        $auction->saveMeta('renewal_fee_first_month', $this->renewal_fee_first_month);
        $auction->saveMeta('renewal_fee_flat_free', $this->renewal_fee_flat_free);
        $auction->saveMeta('renewal_fee_lease_value', $this->renewal_fee_lease_value);
        $auction->saveMeta('renewal_fee_no_of_months', $this->renewal_fee_no_of_months);
        $auction->saveMeta('renewal_fee_sales_tax_first_month', $this->renewal_fee_sales_tax_first_month);
        $auction->saveMeta('renewal_fee_sales_tax_flat_fee', $this->renewal_fee_sales_tax_flat_fee);
        $auction->saveMeta('renewal_fee_sales_tax_lease_value', $this->renewal_fee_sales_tax_lease_value);
        $auction->saveMeta('additional_details_broker', $this->additional_details_broker);
        $auction->saveMeta('agent_bid_visibility', $this->agent_bid_visibility);
        $auction->saveMeta('total_square_feet', $this->total_square_feet);
        $auction->saveMeta('sqft_heated_source', $this->sqft_heated_source);
        $auction->saveMeta('meeting_Preference', $this->meeting_Preference);
        $auction->saveMeta('photo_enhancements', json_encode($this->ensureArray($this->photo_enhancements)));
        $auction->saveMeta('custom_enhancement', $this->custom_enhancement);
        $auction->saveMeta('has_breed_restrictions', $this->has_breed_restrictions);
        $auction->saveMeta('breed_restrictions', $this->breed_restrictions);
        $auction->saveMeta('service_animal', $this->service_animal);
        $auction->saveMeta('support_animal', $this->support_animal);

        // MLS Property Detail Fields
        $auction->saveMeta('year_built', $this->year_built);
        $auction->saveMeta('heating_fuel', json_encode($this->ensureArray($this->heating_fuel)));
        $auction->saveMeta('other_heating_fuel', $this->other_heating_fuel);
        $auction->saveMeta('air_conditioning', json_encode($this->ensureArray($this->air_conditioning)));
        $auction->saveMeta('other_air_conditioning', $this->other_air_conditioning);
        $auction->saveMeta('water', json_encode($this->ensureArray($this->water)));
        $auction->saveMeta('other_water', $this->other_water);
        $auction->saveMeta('sewer', json_encode($this->ensureArray($this->sewer)));
        $auction->saveMeta('other_sewer', $this->other_sewer);
        $auction->saveMeta('property_utilities', json_encode($this->ensureArray($this->property_utilities)));
        $auction->saveMeta('other_property_utilities', $this->other_property_utilities);
        $auction->saveMeta('laundry_features', json_encode($this->ensureArray($this->laundry_features)));
        $auction->saveMeta('other_laundry_features', $this->other_laundry_features);
        $auction->saveMeta('floor_covering', json_encode($this->ensureArray($this->floor_covering)));
        $auction->saveMeta('other_floor_covering', $this->other_floor_covering);
        $auction->saveMeta('security_features', json_encode($this->ensureArray($this->security_features)));
        $auction->saveMeta('other_security_features', $this->other_security_features);
        $auction->saveMeta('zoning', $this->zoning);
        $auction->saveMeta('total_buildings', $this->total_buildings);
        $auction->saveMeta('total_units_on_property', $this->total_units_on_property);
        $auction->saveMeta('office_retail_sqft', $this->office_retail_sqft);
        $auction->saveMeta('flex_space_sqft', $this->flex_space_sqft);
        $auction->saveMeta('road_surface_type', json_encode($this->ensureArray($this->road_surface_type)));
        $auction->saveMeta('other_road_surface_type', $this->other_road_surface_type);
        $auction->saveMeta('electrical_service', json_encode($this->ensureArray($this->electrical_service)));
        $auction->saveMeta('other_electrical_service', $this->other_electrical_service);
        $auction->saveMeta('ceiling_height', $this->ceiling_height);
        $auction->saveMeta('building_features', json_encode($this->ensureArray($this->building_features)));
        $auction->saveMeta('other_building_features', $this->other_building_features);
        $auction->saveMeta('number_electric_meters', $this->number_electric_meters);
        $auction->saveMeta('number_water_meters', $this->number_water_meters);
        $auction->saveMeta('number_gas_meters', $this->number_gas_meters);
        $auction->saveMeta('space_type', json_encode($this->ensureArray($this->space_type)));
        $auction->saveMeta('other_space_type', $this->other_space_type);
        $auction->saveMeta('space_classification', json_encode($this->ensureArray($this->space_classification)));
        $auction->saveMeta('other_space_classification', $this->other_space_classification);
        $auction->saveMeta('number_of_restrooms', $this->number_of_restrooms);
        $auction->saveMeta('number_of_offices', $this->number_of_offices);
        $auction->saveMeta('number_of_conference_rooms', $this->number_of_conference_rooms);

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

        // Add by AT
        $auction->saveMeta('broker_fee_timing', $this->broker_fee_timing);
        $auction->saveMeta('broker_fee_timing_other', $this->broker_fee_timing_other);
        $auction->saveMeta('broker_fee_days_from_rent', $this->broker_fee_days_from_rent);
        $auction->saveMeta('broker_fee_days_after_lease', $this->broker_fee_days_after_lease);
        $auction->saveMeta('broker_fee_days_after_rent', $this->broker_fee_days_after_rent);
        $auction->saveMeta('sales_tax_option_gross', $this->sales_tax_option_gross);
        $auction->saveMeta('purchase_fee_monthly_percentage', $this->purchase_fee_monthly_percentage);
        $auction->saveMeta('purchase_fee_months', $this->purchase_fee_months);
        $auction->saveMeta('sales_tax_option_monthly', $this->sales_tax_option_monthly);
        $auction->saveMeta('purchase_fee_flat_commercial', $this->stripCommas($this->purchase_fee_flat_commercial));
        $auction->saveMeta('sales_tax_option_flat', $this->sales_tax_option_flat);
        $auction->saveMeta('purchase_fee_purchase_price', $this->purchase_fee_purchase_price);
        $auction->saveMeta('purchase_fee_other_commercial', $this->purchase_fee_other_commercial);
        $auction->saveMeta('split_payment_due', $this->split_payment_due);
        $auction->saveMeta('broker_fee_days_after_due_event', $this->broker_fee_days_after_due_event);
        $auction->saveMeta('renewal_fee_type', $this->renewal_fee_type);
        $auction->saveMeta('renewal_fee_percentage', $this->renewal_fee_percentage);
        $auction->saveMeta('renewal_fee_custom', $this->renewal_fee_custom);
        $auction->saveMeta('net_aggregate_rent', $this->net_aggregate_rent);
        $auction->saveMeta('month_percentage_rent', $this->month_percentage_rent);
        $auction->saveMeta('no_of_months', $this->no_of_months);
        $auction->saveMeta('flat_fee', $this->flat_fee);
        $auction->saveMeta('gross_percentage_rent', $this->gross_percentage_rent);
        $auction->saveMeta('purchase_fee_net_aggregate', $this->purchase_fee_net_aggregate);
        $auction->saveMeta('tenant_broker_commission_structure', $this->tenant_broker_commission_structure);
        $auction->saveMeta('expansion_commission_percentage', $this->expansion_commission_percentage);
        $auction->saveMeta('tenant_broker_commission_percentage', $this->tenant_broker_commission_percentage);
        $auction->saveMeta('tenant_broker_fee_structure', $this->tenant_broker_fee_structure);
        $auction->saveMeta('tenant_broker_percentage', $this->tenant_broker_percentage);
        $auction->saveMeta('tenant_broker_flat_fee', $this->tenant_broker_flat_fee);
        $auction->saveMeta('expansion_commission_type', $this->expansion_commission_type);
        $auction->saveMeta('expansion_gross_percentage', $this->expansion_gross_percentage);
        $auction->saveMeta('expansion_first_month_percentage', $this->expansion_first_month_percentage);
        $auction->saveMeta('expansion_flat_fee', $this->expansion_flat_fee);
        $auction->saveMeta('expansion_custom_commission', $this->expansion_custom_commission);


        // Add by AT

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



        $auction->saveMeta('custom_services', json_encode($this->ensureArray($this->custom_services)));
        $auction->saveMeta('total_marketing_fee', $this->total_marketing_fee);
        $auction->saveMeta('total_flat_fee', $this->total_flat_fee);


        //Flat Fee Agent (Limited Service) Tenant
        $auction->saveMeta('fees', json_encode($this->ensureArray($this->fees)));
        $auction->saveMeta('enable', json_encode($this->ensureArray($this->enable)));
        $auction->saveMeta('showings_count', $this->showings_count);
        $auction->saveMeta('attend_showings_count', $this->attend_showings_count);
        $auction->saveMeta('virtual_tours_count', $this->virtual_tours_count);
        $auction->saveMeta('understand_terms', $this->understand_terms);

        //seller
        $auction->saveMeta('staging_duration', $this->staging_duration);
        $auction->saveMeta('open_house_count', $this->open_house_count);



        //Landlord
        $auction->saveMeta('virtual_showings_count', $this->virtual_showings_count);

        // Landlord Lease Terms Questions
        $auction->saveMeta('lease_available_date', $this->lease_available_date);
        $auction->saveMeta('security_deposit_required', $this->security_deposit_required);
        $auction->saveMeta('first_month_rent_required', $this->first_month_rent_required);
        $auction->saveMeta('last_month_rent_required', $this->last_month_rent_required);
        $auction->saveMeta('total_move_in_funds_required', $this->total_move_in_funds_required);
        $auction->saveMeta('pet_policy', $this->pet_policy);
        $auction->saveMeta('pet_deposit_fee_rent', $this->pet_deposit_fee_rent);
        $auction->saveMeta('number_of_occupants_allowed', $this->number_of_occupants_allowed);
        $auction->saveMeta('parking_terms', $this->parking_terms);
        $auction->saveMeta('utility_responsibility', $this->utility_responsibility);
        $auction->saveMeta('ll_maintenance_responsibility', $this->ll_maintenance_responsibility);
        $auction->saveMeta('renewal_option_offered', $this->renewal_option_offered);
        $auction->saveMeta('renewal_option_details', $this->renewal_option_details);
        $auction->saveMeta('landlord_approval_conditions', $this->landlord_approval_conditions);
        $auction->saveMeta('additional_landlord_lease_terms', $this->additional_landlord_lease_terms);
        $auction->saveMeta('commercial_lease_type', $this->commercial_lease_type);
        $auction->saveMeta('commercial_lease_type_other', $this->commercial_lease_type_other);
        $auction->saveMeta('cam_nnn_additional_rent_charges', $this->cam_nnn_additional_rent_charges);
        $auction->saveMeta('rent_escalation_terms', $this->rent_escalation_terms);
        $auction->saveMeta('tenant_improvement_buildout_terms', $this->tenant_improvement_buildout_terms);
        $auction->saveMeta('permitted_use_restrictions', $this->permitted_use_restrictions);
        $auction->saveMeta('signage_rights', $this->signage_rights);
        $auction->saveMeta('commercial_parking_terms', $this->commercial_parking_terms);
        $auction->saveMeta('personal_guarantee_requirement', $this->personal_guarantee_requirement);
        $auction->saveMeta('commercial_approval_conditions', $this->commercial_approval_conditions);

        // Tax, Legal, HOA & Disclosures tab
        $auction->saveMeta('parcel_id', $this->parcel_id);
        $auction->saveMeta('tax_year', $this->tax_year);
        $auction->saveMeta('annual_property_taxes', $this->stripCommas($this->annual_property_taxes));
        $auction->saveMeta('additional_parcels', $this->additional_parcels);
        $auction->saveMeta('total_parcel_count', $this->total_parcel_count);
        $auction->saveMeta('additional_parcel_ids', $this->additional_parcel_ids);
        $auction->saveMeta('legal_description', $this->legal_description);
        $auction->saveMeta('flood_zone_code', $this->flood_zone_code);
        $auction->saveMeta('flood_zone_code_other', $this->flood_zone_code_other);
        $auction->saveMeta('flood_insurance_required', $this->flood_insurance_required);
        $auction->saveMeta('flood_zone_panel', $this->flood_zone_panel);
        $auction->saveMeta('has_cdd', $this->has_cdd);
        $auction->saveMeta('annual_cdd_fee', $this->stripCommas($this->annual_cdd_fee));
        $auction->saveMeta('has_special_assessments', $this->has_special_assessments);
        $auction->saveMeta('special_assessment_amount', $this->stripCommas($this->special_assessment_amount));
        $auction->saveMeta('special_assessment_description', $this->special_assessment_description);
        $auction->saveMeta('has_hoa', $this->has_hoa);
        $auction->saveMeta('association_type', $this->association_type);
        $auction->saveMeta('association_type_other', $this->association_type_other);
        $auction->saveMeta('association_name', $this->association_name);
        $auction->saveMeta('association_fee_amount', $this->stripCommas($this->association_fee_amount));
        $auction->saveMeta('association_fee_frequency', $this->association_fee_frequency);
        $auction->saveMeta('association_fee_frequency_other', $this->association_fee_frequency_other);
        $auction->saveMeta('association_approval_required', $this->association_approval_required);
        $auction->saveMeta('association_approval_process', $this->association_approval_process);
        $auction->saveMeta('association_application_fee', $this->stripCommas($this->association_application_fee));
        $auction->saveMeta('association_fee_includes', json_encode($this->association_fee_includes));
        $auction->saveMeta('association_fee_includes_other', $this->association_fee_includes_other);
        $auction->saveMeta('association_amenities', json_encode($this->association_amenities));
        $auction->saveMeta('association_amenities_other', $this->association_amenities_other);
        $auction->saveMeta('leasing_restrictions', $this->leasing_restrictions);
        $auction->saveMeta('min_lease_period', $this->min_lease_period);
        $auction->saveMeta('min_lease_period_other', $this->min_lease_period_other);
        $auction->saveMeta('max_leases_per_year', $this->max_leases_per_year);
        $auction->saveMeta('additional_lease_restrictions', $this->additional_lease_restrictions);
        $auction->saveMeta('pet_restrictions', $this->pet_restrictions);
        $auction->saveMeta('pet_restrictions_detail', $this->pet_restrictions_detail);

        // Documents & Disclosures
        $auction->saveMeta('landlord_disclosure_available', $this->landlord_disclosure_available);
        $auction->saveMeta('survey_available', $this->survey_available);
        $auction->saveMeta('inspection_report_available', $this->inspection_report_available);
        $auction->saveMeta('hoa_condo_docs_available', $this->hoa_condo_docs_available);
        $auction->saveMeta('flood_disclosure_available', $this->flood_disclosure_available);
        $auction->saveMeta('lead_based_paint_disclosure', $this->lead_based_paint_disclosure);
        $auction->saveMeta('environmental_report_available', $this->environmental_report_available);
        $auction->saveMeta('additional_documents', json_encode($this->additional_documents));
        $auction->saveMeta('other_document_type', $this->other_document_type);
        $auction->saveMeta('landlord_doc_rows', json_encode($this->landlord_doc_rows));

        // Disclosure file uploads
        $disclosureUploads = [
            ['file' => 'landlord_disclosure_file',  'path' => 'landlord_disclosure_file_path',  'dir' => 'landlord-disclosure'],
            ['file' => 'survey_file',               'path' => 'survey_file_path',               'dir' => 'survey'],
            ['file' => 'inspection_report_file',    'path' => 'inspection_report_file_path',    'dir' => 'inspection-report'],
            ['file' => 'hoa_condo_docs_file',       'path' => 'hoa_condo_docs_file_path',       'dir' => 'hoa-condo-docs'],
            ['file' => 'flood_disclosure_file',     'path' => 'flood_disclosure_file_path',     'dir' => 'flood-disclosure'],
            ['file' => 'lead_based_paint_file',     'path' => 'lead_based_paint_file_path',     'dir' => 'lead-based-paint'],
            ['file' => 'environmental_report_file', 'path' => 'environmental_report_file_path', 'dir' => 'environmental-report'],
        ];
        foreach ($disclosureUploads as $item) {
            $fileVal  = $this->{$item['file']};
            $pathProp = $item['path'];
            if ($fileVal && !is_string($fileVal)) {
                $ext      = $fileVal->getClientOriginalExtension();
                $uuid     = (string) Str::uuid();
                $fileName = $uuid . '.' . $ext;
                $dir      = 'landlord-disclosures/' . $auction->id . '/' . $item['dir'];
                Storage::disk('public')->makeDirectory($dir);
                $fileVal->storeAs($dir, $fileName, 'public');
                $storedPath        = $dir . '/' . $fileName;
                $this->{$pathProp} = $storedPath;
                $auction->saveMeta($pathProp, $storedPath);
            } elseif ($this->{$pathProp}) {
                $auction->saveMeta($pathProp, $this->{$pathProp});
            }
        }

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
        $auction->saveMeta('video_link', $this->video_link);
        $auction->saveMeta('listing_ai_faq', json_encode($this->listing_ai_faq ?: []));

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

        // Save Photos, Tours & Documents fields
        $auction->saveMeta('video_tour_url', $this->videoTourUrl ?? '');
        $auction->saveMeta('virtual_tour_url', $this->virtualTourUrl ?? '');

        $this->processPendingPhotoUploads();
        $auction->saveMeta('property_photos', $this->propertyPhotos);

        if ($this->listingDocuments && !is_string($this->listingDocuments)) {
            $allowedMimes = ['application/pdf', 'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'image/jpeg', 'image/png'];
            if (in_array($this->listingDocuments->getMimeType(), $allowedMimes)) {
                $ext = $this->listingDocuments->getClientOriginalExtension();
                $uuid = (string) Str::uuid();
                $fileName = $uuid . '.' . $ext;
                Storage::disk('public')->makeDirectory('auction/documents');
                $this->listingDocuments->storeAs('auction/documents', $fileName, 'public');
                $auction->saveMeta('listing_documents', $fileName);
            }
        } elseif ($this->listingDocuments && is_string($this->listingDocuments)) {
            $auction->saveMeta('listing_documents', $this->listingDocuments);
        }
    }

    private function processPendingPhotoUploads(): void
    {
        if (empty($this->newPropertyPhotos)) {
            return;
        }
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        foreach ($this->newPropertyPhotos as $photo) {
            if (in_array($photo->getMimeType(), $allowedMimes)) {
                $ext = $photo->getClientOriginalExtension();
                $uuid = (string) Str::uuid();
                $fileName = $uuid . '.' . $ext;
                $photo->storeAs('auction/images', $fileName, 'public');
                $this->propertyPhotos[] = $fileName;
            }
        }
        $this->newPropertyPhotos = [];
    }

    public function updatedNewPropertyPhotos()
    {
        try {
            $this->validate(['newPropertyPhotos.*' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:10240']);
            $incoming = is_array($this->newPropertyPhotos) ? count($this->newPropertyPhotos) : 0;
            if (count($this->propertyPhotos) + $incoming > 50) {
                $this->addError('newPropertyPhotos', 'You may upload up to 50 property photos. You currently have ' . count($this->propertyPhotos) . ' photo(s) uploaded. Please select fewer files.');
                $this->newPropertyPhotos = [];
                return;
            }
            $this->processPendingPhotoUploads();
            if ($this->listingId) {
                $auction = HirelandLordAgentAuction::findOrFail($this->listingId);
                $auction->saveMeta('property_photos', $this->propertyPhotos);
            }
        } catch (\Throwable $e) {
            $this->newPropertyPhotos = [];
            $this->addError('newPropertyPhotos', 'Photo upload failed. Please try again.');
        }
    }

    public function updatedListingDocuments()
    {
        $this->validate(['listingDocuments' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240']);
    }

    public function updatedLandlordDisclosureFile()
    {
        $this->validate(['landlord_disclosure_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240']);
    }

    public function updatedSurveyFile()
    {
        $this->validate(['survey_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240']);
    }

    public function updatedInspectionReportFile()
    {
        $this->validate(['inspection_report_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240']);
    }

    public function updatedHoaCondoDocsFile()
    {
        $this->validate(['hoa_condo_docs_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240']);
    }

    public function updatedFloodDisclosureFile()
    {
        $this->validate(['flood_disclosure_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240']);
    }

    public function updatedLeadBasedPaintFile()
    {
        $this->validate(['lead_based_paint_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240']);
    }

    public function updatedEnvironmentalReportFile()
    {
        $this->validate(['environmental_report_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240']);
    }

    public function updatedLandlordDocFileUpload()
    {
        $this->validate(['landlordDocFileUpload' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240']);

        if ($this->landlordDocFileIndex === null || !$this->landlordDocFileUpload) {
            return;
        }

        $index        = (int) $this->landlordDocFileIndex;
        $originalName = $this->landlordDocFileUpload->getClientOriginalName();
        $ext          = $this->landlordDocFileUpload->getClientOriginalExtension();
        $uuid         = (string) Str::uuid();
        $fileName     = $uuid . '.' . $ext;
        $dir          = 'landlord-disclosures/' . ($this->listingId ?? 'draft');

        Storage::disk('public')->makeDirectory($dir);
        $this->landlordDocFileUpload->storeAs($dir, $fileName, 'public');
        $storedPath = $dir . '/' . $fileName;

        $rows = $this->landlord_doc_rows;
        if (isset($rows[$index])) {
            $rows[$index]['stored_path']   = $storedPath;
            $rows[$index]['original_name'] = $originalName;
        }
        $this->landlord_doc_rows     = $rows;
        $this->landlordDocFileUpload = null;
        $this->landlordDocFileIndex  = null;

        $this->emit('landlordDocFileStored', $index, $storedPath, $originalName);
    }

    public function removeLandlordDocRowFile($index)
    {
        $index = (int) $index;
        $rows  = $this->landlord_doc_rows;
        if (isset($rows[$index])) {
            $rows[$index]['stored_path']   = '';
            $rows[$index]['original_name'] = '';
        }
        $this->landlord_doc_rows = $rows;
        $this->emit('landlordDocRowFileRemoved', $index);
    }

    public function deletePropertyPhoto($index)
    {
        if (isset($this->propertyPhotos[$index])) {
            $filename = $this->propertyPhotos[$index];
            Storage::disk('public')->delete('auction/images/' . $filename);
            array_splice($this->propertyPhotos, $index, 1);
            if ($this->listingId) {
                $auction = HirelandLordAgentAuction::findOrFail($this->listingId);
                if (empty($this->propertyPhotos)) {
                    $auction->deleteMeta('property_photos');
                } else {
                    $auction->saveMeta('property_photos', $this->propertyPhotos);
                }
            }
        }
    }

    public function reorderPhotos(array $orderedFilenames): void
    {
        $current  = $this->propertyPhotos;
        $newOrder = [];
        foreach ($orderedFilenames as $fname) {
            if (in_array($fname, $current, true)) {
                $newOrder[] = $fname;
            }
        }
        foreach ($current as $fname) {
            if (!in_array($fname, $newOrder, true)) {
                $newOrder[] = $fname;
            }
        }
        $this->propertyPhotos = $newOrder;
        if ($this->listingId) {
            $auction = HirelandLordAgentAuction::findOrFail($this->listingId);
            $auction->saveMeta('property_photos', $this->propertyPhotos);
        }
    }

    public function movePhotoUp(int $index): void
    {
        if ($index <= 0 || !isset($this->propertyPhotos[$index])) {
            return;
        }
        [$this->propertyPhotos[$index - 1], $this->propertyPhotos[$index]] =
            [$this->propertyPhotos[$index], $this->propertyPhotos[$index - 1]];
        if ($this->listingId) {
            $auction = HirelandLordAgentAuction::findOrFail($this->listingId);
            $auction->saveMeta('property_photos', $this->propertyPhotos);
        }
    }

    public function movePhotoDown(int $index): void
    {
        if (!isset($this->propertyPhotos[$index + 1])) {
            return;
        }
        [$this->propertyPhotos[$index], $this->propertyPhotos[$index + 1]] =
            [$this->propertyPhotos[$index + 1], $this->propertyPhotos[$index]];
        if ($this->listingId) {
            $auction = HirelandLordAgentAuction::findOrFail($this->listingId);
            $auction->saveMeta('property_photos', $this->propertyPhotos);
        }
    }

    public function deleteListingDocument()
    {
        if ($this->listingDocuments && is_string($this->listingDocuments)) {
            Storage::disk('public')->delete('auction/documents/' . $this->listingDocuments);
            if ($this->listingId) {
                $auction = HirelandLordAgentAuction::findOrFail($this->listingId);
                $auction->deleteMeta('listing_documents');
            }
        }
        $this->listingDocuments = null;
    }

    public function store()
    {
        $this->validate([
            'first_name'           => 'required|string',
            'last_name'            => 'required|string',
            'phone_number'         => 'required|string',
            'email'                => 'required|email',
            'desired_lease_length' => 'required|array|min:1',
        ], [
            'first_name.required'           => 'First Name is required.',
            'last_name.required'            => 'Last Name is required.',
            'phone_number.required'         => 'Phone Number is required.',
            'email.required'                => 'Email Address is required.',
            'email.email'                   => 'Please enter a valid email address.',
            'desired_lease_length.required' => 'Desired Lease Term is required.',
            'desired_lease_length.min'      => 'Please select at least one Desired Lease Term.',
        ]);

        try {
            $this->isDraft = 0;

            $auction = $this->listingId
                ? HirelandLordAgentAuction::find($this->listingId)
                : new HirelandLordAgentAuction();

            $auction->user_id = Auth::id();
            $auction->title = $this->listing_title;
            $auction->is_draft = 0;
            $auction->save();

            // Phase 6 — persist referral attribution on brand-new listing rows.
            \App\Services\ReferralLinkService::persistListingReferral($auction);

            $this->listingId = $auction->id;

            $this->saveAllMetadata($auction);

            \Log::info('[LANDLORD LISTING SUBMITTED]', [
                'record_id' => $auction->id,
                'listing_id' => $auction->listing_id ?? 'N/A',
                'user_id' => $auction->user_id,
                'is_draft' => $auction->is_draft,
                'is_approved' => $auction->is_approved,
                'is_sold' => $auction->is_sold,
            ]);

            session()->flash('success', 'Listing submitted successfully!');

            $url = route('landlord.agent.auction.view', ['id' => $auction->id]);

            \Log::info('[LANDLORD FORM REDIRECT]', [
                'route_name' => 'landlord.agent.auction.view',
                'listing_id' => $auction->id,
                'url' => $url,
            ]);

            // Dispatch browser event to force redirect (Livewire v2 compatible)
            $this->dispatchBrowserEvent('force-redirect', ['url' => $url]);

            // Keep redirect as fallback
            return redirect()->to($url);

        } catch (\Exception $e) {
            session()->flash('error', 'Error saving listing: ' . $e->getMessage());
        }
    }

    public function deleteAllDrafts()
    {
        try {
            $draftIds = HirelandLordAgentAuction::where('user_id', Auth::id())
                ->where('is_draft', true)
                ->pluck('id');

            if ($draftIds->isNotEmpty()) {
                DB::table('landlord_agent_auction_metas')
                    ->whereIn('landlord_agent_auction_id', $draftIds)
                    ->delete();
            }

            HirelandLordAgentAuction::where('user_id', Auth::id())
                ->where('is_draft', true)
                ->delete();

            $this->hasDrafts = false;

            session()->flash('success', 'All drafts and their associated data have been deleted successfully.');
        } catch (\Exception $e) {
            session()->flash('error', 'Error deleting drafts: ' . $e->getMessage());
        }
    }
    public function deleteDraft($draftId)
    {
        try {
            DB::table('landlord_agent_auction_metas')
                ->where('landlord_agent_auction_id', $draftId)
                ->delete();

            HirelandLordAgentAuction::where('id', $draftId)
                ->where('user_id', Auth::id())
                ->delete();

            $this->hasDrafts = HirelandLordAgentAuction::where('user_id', Auth::id())
                ->where(function ($query) {
                    $query->where('is_draft', true)
                          ->orWhereNull('is_draft');
                })
                ->exists();

            session()->flash('success', 'Draft deleted successfully.');
        } catch (\Exception $e) {
            session()->flash('error', 'Error deleting draft: ' . $e->getMessage());
        }
    }

    public function updateTenantPays($selectedValues)
    {
        $selectedValues = $this->ensureArray($selectedValues);
        if (in_array('Other', $selectedValues)) {
            $this->is_other_tenant_pay_visible = true;
        } else {
            $this->is_other_tenant_pay_visible = false;
            $this->other_tenant_pays = '';
        }
    }

    public function updateOwnerPays($selectedValues)
    {
        $selectedValues = $this->ensureArray($selectedValues);
        if (in_array('Other', $selectedValues)) {
            $this->is_other_owner_pays_visible = true;
        } else {
            $this->is_other_owner_pays_visible = false;
            $this->other_owner_pays = '';
        }
    }

    public function updateRentIncludes($selectedValues)
    {
        $selectedValues = $this->ensureArray($selectedValues);
        if (in_array('Other', $selectedValues)) {
            $this->is_rent_include_visible = true;
        } else {
            $this->is_rent_include_visible = false;
            $this->other_rent_include = '';
        }
    }

    public function updateAppliances($selectedValues)
    {
        $selectedValues = $this->ensureArray($selectedValues);
        if (in_array('Other', $selectedValues)) {
            $this->is_other_appliances_visible = true;
            $this->showOtherAppliances = true;
        } else {
            $this->is_other_appliances_visible = false;
            $this->showOtherAppliances = false;
            $this->other_appliances = '';
        }
    }

    public function deletePhoto()
    {
        try {
            if ($this->listingId) {
                $auction = HirelandLordAgentAuction::find($this->listingId);
                if ($auction) {
                    if ($this->photo && is_string($this->photo)) {
                        Storage::disk('public')->delete('auction/images/' . $this->photo);
                    }
                    $auction->deleteMeta('photo');
                }
            }
            $this->photo = null;
            session()->flash('success', 'Photo deleted successfully.');
        } catch (\Exception $e) {
            session()->flash('error', 'Error deleting photo: ' . $e->getMessage());
        }
    }

    private function formatPhoneForDisplay($phone)
    {
        if (empty($phone)) {
            return '';
        }
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) !== 10) {
            return $phone;
        }
        return substr($digits, 0, 3) . '-' . substr($digits, 3, 3) . '-' . substr($digits, 6);
    }

    protected function stripCommas($value)
    {
        if ($value === null || $value === '') {
            return $value;
        }
        return str_replace(',', '', $value);
    }
}
