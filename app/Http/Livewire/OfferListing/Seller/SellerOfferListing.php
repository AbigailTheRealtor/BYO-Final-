<?php

namespace App\Http\Livewire\OfferListing\Seller;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\OfferAuction;
use App\Models\SellerAgentAuction as SellerAgentAuctionModel;
use App\Models\AcceptedBidSummary;
use App\Models\PropertyLocationDna;
use App\Models\PropertyLocationPoi;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Http\Livewire\OfferListing\Concerns\HasMlsImport;
use App\Services\WizardEventService;
use App\Http\Livewire\Concerns\ResolvesOwnedAuction;
use App\Http\Livewire\OfferListing\Concerns\SellerPublishValidation;

class SellerOfferListing extends Component
{
    use WithFileUploads, HasMlsImport;
    use ResolvesOwnedAuction;
    use SellerPublishValidation; // BYO-H1: shared publish rules (create + edit)

    // TODO: set to false before production launch
    const SAVE_AS_NEW_DRAFT = true;

    protected $listeners = [
        'updatePreference' => 'handleUpdatePreference',
        'setActiveTab'     => 'setActiveTab',
    ];

    // Livewire properties for form fields
    public $hasDrafts = false;
    public $auctionId; // To store the auction ID for editing

    public $listingId = null; // To track existing listings
    public $isDraft = false; // To track draft status
    public $isResumingDraft = false; // True when a draft has been loaded via loadDraft()
    public $listing_status = 'Active'; // 'Active', 'Pending', 'Expired', or 'Draft'

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
    public $starting_price = '';
    public $reserve_price = '';
    public $buy_now_price = '';
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
    public $assumption_fee_responsibility = ''; // A6.31-A6.34: who pays the assumption fee (Buyer/Seller/Split)
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
    public $garage_parking_spaces_option = [];
    public $other_parking_space_wrapper = '';
    public $pool_needed = '';
    public $pool_type = [];
    public $view_preference = [];
    public $other_preferences = '';
    public $appliances = [];
    public $other_appliances = '';
    public $showOtherAppliances = false;
    public $showEnhancements = false;
    public $showPaymentAssumptions = false;
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

    // Financial Details tab — Income property fields
    public $gross_annual_income = '';
    public $annual_operating_expenses = '';
    public $rent_roll_available = '';
    public $operating_statement_available = '';

    // Financial Details tab — Commercial property fields
    public $price_per_sqft = '';
    public $existing_lease_type = '';
    public $other_lease_type = '';
    public $lease_expiration = '';
    public $lease_assignable = '';

    // Financial Details tab — Business property fields
    public $annual_revenue = '';
    public $gross_profit = '';
    public $sde_ebitda = '';
    public $inventory_value = '';
    public $ffe_value = '';
    public $reason_for_sale = '';
    public $other_reason_for_sale = '';
    public $employee_count = '';
    public $financial_statements_available = '';
    public $tax_returns_available = '';
    public $nda_required = '';
    public $business_location_leased = '';
    public $business_lease_monthly_rent = '';
    public $business_lease_expiration = '';
    public $business_lease_renewal_options = '';
    public $business_lease_assignable = '';
    public $business_lease_additional_terms = '';

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
    public $unit_type_configurations = [];
    public $breed_restrictions = '';

    // MLS property detail fields
    public $year_built = '';
    public $zoning = '';
    public $roof_type = [];
    public $exterior_construction = [];
    public $foundation = [];
    public $heating_and_fuel = [];
    public $air_conditioning = [];
    public $water = [];
    public $sewer = [];
    public $utilities = [];
    public $road_frontage = [];
    public $road_surface_type = [];
    public $electrical_service = [];
    public $ceiling_height = '';
    public $building_features = [];
    public $number_water_meters = '';
    public $number_electric_meters = '';
    public $business_name = '';
    public $year_established = '';
    public $licenses = [];
    public $sale_includes = [];
    public $current_use = [];
    public $lot_dimensions = '';
    public $front_footage = '';
    public $number_of_wells = '';
    public $number_of_septics = '';
    public $current_adjacent_use = [];
    public $fences = [];
    public $vegetation = [];
    public $buildable = '';
    public $easements = [];
    public $other_roof_type = '';
    public $other_exterior_construction = '';
    public $other_foundation = '';
    public $other_heating_and_fuel = '';
    public $other_air_conditioning = '';
    public $other_water = '';
    public $other_sewer = '';
    public $other_utilities = '';
    public $other_road_frontage = '';
    public $other_road_surface_type = '';
    public $other_electrical_service = '';
    public $other_building_features = '';
    public $other_licenses = '';
    public $other_sale_includes = '';
    public $other_current_use = '';
    public $other_current_adjacent_use = '';
    public $other_fences = '';
    public $other_vegetation = '';
    public $other_easements = '';
    public $water_available = '';
    public $water_available_other = '';
    public $sewer_available = '';
    public $sewer_available_other = '';
    public $electric_available = '';
    public $electric_available_other = '';
    public $gas_available = '';
    public $gas_available_other = '';
    public $telecom_available = '';
    public $telecom_available_other = '';

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
    public $other_services_enabled = false;
    public $other_services = '';

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
    public $agent_brokerage = '';
    public $agent_license_number = '';
    public $agent_nar_member_id = '';
    public $current_status = '';
    public $video_link = '';
    public array $listing_ai_faq = [];
    public $embedUrl = null;



    // location and meeting details
    public $person_meeting;
    public $meeting_details_first_name = '';
    public $meeting_details_last_name = '';
    public $meeting_details_phone = '';
    public $meeting_details_email = '';

    public $address = '';
    public $unit_address = '';
    // Hotfix #2851 — coordinates wired via Google Places autocomplete callback.
    // Populated by fillFromGooglePlaces() Livewire method (single atomic call).
    // Persisted to seller_agent_auction_metas EAV via saveMeta() and reloaded
    // in loadDraft().  Copied into accepted_bid_summaries at acceptance time by
    // SellerAcceptedBidSummaryService::extractPropertyLocationData().
    public $property_lat = '';
    public $property_lng = '';
    public $google_place_id = '';
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

    // Sale Terms Questions
    public $initial_deposit_type = '$';
    public $initial_deposit_requested = '';
    public $initial_deposit_timeframe = '';
    public $initial_deposit_timeframe_other = '';
    public $additional_deposit_type = '$';
    public $additional_deposit_requested = '';
    public $additional_deposit_timeframe = '';
    public $additional_deposit_timeframe_other = '';
    public $escrow_agent_preference = '';
    public $preferred_inspection_period = '';
    // Phase 5/6 QA Follow-up (Seller Contingency UX): per-contingency preference select +
    // conditional "Preferred … Period (Days)" companions. Inspection now has an explicit
    // preference select; appraisal/financing/sale-of-buyer each gain a period field.
    public $inspection_contingency_preference = '';
    public $appraisal_contingency_preference = '';
    public $appraisal_contingency_period = '';
    public $financing_contingency_preference = '';
    public $financing_contingency_period = '';
    public $sale_of_buyer_property_contingency = '';
    public $sale_of_buyer_property_period = '';
    public $seller_contribution_credit_offered = '';
    public $seller_contribution_amount_details = '';
    public $possession_preference = '';
    public $possession_details = '';
    public $included_personal_property = '';
    public $excluded_items = '';
    public $home_warranty_offered = '';
    public $home_warranty_amount_details = '';
    public $hoa_condo_association_terms = '';
    public $additional_seller_sale_terms = '';

    // Estimated Payment Assumptions (agent-editable calculator defaults)
    public $payment_down_payment_pct = '';
    public $payment_interest_rate = '';
    public $payment_loan_term = '';
    public $payment_annual_property_taxes = '';
    public $payment_monthly_insurance = '';
    public $payment_hoa_fee_amount = '';
    public $payment_hoa_fee_frequency = '';
    public $payment_pmi_rate = '';
    public $payment_show_buydown_options = true;

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
    public $flood_zone_date = '';

    // Waterfront
    public $waterfront = '';
    public $water_frontage = '';
    public $waterfront_feet = '';
    public $water_access = [];
    public $water_view = [];

    // Interior
    public $interior_features = [];
    public $other_water_access = '';
    public $other_water_view = '';
    public $other_interior_features = '';

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
    public $seller_disclosure_available = '';
    public $survey_available = '';
    public $inspection_report_available = '';
    public $hoa_condo_docs_available = '';
    public $flood_disclosure_available = '';
    public $lead_based_paint_disclosure = '';
    public $environmental_report_available = '';
    public $additional_documents = [];
    public $other_document_type = '';
    public $doc_rows = [];

    // Per-row additional document uploads
    public $docFileUpload;
    public $docFileUploadIndex = null;

    // Disclosure file uploads (temporary Livewire upload objects)
    public $seller_disclosure_file;
    public $survey_file;
    public $inspection_report_file;
    public $hoa_condo_docs_file;
    public $flood_disclosure_file;
    public $lead_based_paint_file;
    public $environmental_report_file;

    // Disclosure file stored paths (persisted via meta)
    public $seller_disclosure_file_path = '';
    public $survey_file_path = '';
    public $inspection_report_file_path = '';
    public $hoa_condo_docs_file_path = '';
    public $flood_disclosure_file_path = '';
    public $lead_based_paint_file_path = '';
    public $environmental_report_file_path = '';

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
    public $cityFieldVisible = false;
    public $stateFieldVisible = false;
    public $zipCodeFieldVisible = false;
    public $counties = [];
    public $newCounty = '';
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
    /**
     * Owner-only guard for the create/draft path. listingId is null for a brand
     * new listing (allowed) and set only when resuming the owner's own draft, so
     * this blocks a tampered listingId in a hydration payload (e.g. the resume-
     * draft write path). See ResolvesOwnedAuction.
     */
    public function hydrate()
    {
        $this->assertCanManageAuction(SellerAgentAuctionModel::class, $this->listingId, null);
    }

    public function mount($listingId = null)
    {
        $this->unit_type_configurations = [
            [
                'unit_type'          => '',
                'beds_unit'          => '',
                'baths_unit'         => '',
                'garage_spaces'      => '',
                'carport_spaces'     => '',
                'other_spaces'       => '',
                'number_of_units'    => '',
                'number_occupied'    => '',
                'expected_rent'      => '',
                'unit_type_description' => '',
                'sqft_heated'        => '',
            ]
        ];

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

        // When Bidding Period is disabled, force auction_type to Traditional so
        // the server-side property is set correctly.  A Blade hidden-input with
        // wire:model does NOT fire input events, so it cannot update the property.
        // loadDraft() will have already overwritten this for existing listings.
        if (!config('bya_beta.bidding_period_enabled') && empty($this->auction_type)) {
            $this->auction_type = 'Traditional';
        }
    }
    public function addUnitType()
    {
        $this->unit_type_configurations[] = [
            'unit_type'          => '',
            'beds_unit'          => '',
            'baths_unit'         => '',
            'garage_spaces'      => '',
            'carport_spaces'     => '',
            'other_spaces'       => '',
            'number_of_units'    => '',
            'number_occupied'    => '',
            'expected_rent'      => '',
            'unit_type_description' => '',
            'sqft_heated'        => '',
        ];
    }

    public function removeUnitType($index)
    {
        if (count($this->unit_type_configurations) > 1) {
            unset($this->unit_type_configurations[$index]);
            $this->unit_type_configurations = array_values($this->unit_type_configurations);
        }
    }

    public function startNew()
    {
        // Reset all properties to their initial state
        $this->resetExcept(['hasDrafts', 'user_type']);

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
    }

    public function removeCity($index)
    {
        unset($this->cities[$index]);
        $this->cities = array_values($this->cities);
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
    
    public function fillFromGooglePlaces(
        string $street,
        string $city,
        string $county,
        string $state,
        string $zip,
        string $lat,
        string $lng,
        string $placeId
    ): void {
        $this->address         = $street;
        $this->property_city   = $city;
        $this->property_county = $county;
        $this->property_state  = $state;
        $this->property_zip    = $zip;
        $this->property_lat    = $lat;
        $this->property_lng    = $lng;
        $this->google_place_id = $placeId;
        $this->propertyCitySuggestions      = [];
        $this->highlightedPropertyCityIndex = -1;
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
        
        $variants = \App\Services\CityNameNormalizer::searchVariants($cityName);

        $city = \App\Models\UsCity::where(function ($q) use ($variants) {
                foreach ($variants as $v) {
                    $stripped = str_replace('.', '', $v);
                    $q->orWhere('name', 'ILIKE', $v)
                      ->orWhereRaw("REPLACE(name, '.', '') ILIKE ?", [$stripped]);
                }
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
            $variants = \App\Services\CityNameNormalizer::searchVariants($input);
            $citiesStartWith = \App\Models\UsCity::with('state')
                ->where(function ($q) use ($variants) {
                    foreach ($variants as $v) {
                        $stripped = str_replace('.', '', $v);
                        $q->orWhere('name', 'ILIKE', $v . '%')
                          ->orWhereRaw("REPLACE(name, '.', '') ILIKE ?", [$stripped . '%']);
                    }
                })
                ->orderBy('name')
                ->limit(10)
                ->get();
            
            $citiesContain = \App\Models\UsCity::with('state')
                ->where(function ($q) use ($variants) {
                    foreach ($variants as $v) {
                        $stripped = str_replace('.', '', $v);
                        $q->orWhere('name', 'ILIKE', '%' . $v . '%')
                          ->orWhereRaw("REPLACE(name, '.', '') ILIKE ?", ['%' . $stripped . '%']);
                    }
                })
                ->where(function ($q) use ($variants) {
                    foreach ($variants as $v) {
                        $stripped = str_replace('.', '', $v);
                        $q->where('name', 'NOT ILIKE', $v . '%')
                          ->whereRaw("REPLACE(name, '.', '') NOT ILIKE ?", [$stripped . '%']);
                    }
                })
                ->orderBy('name')
                ->limit(max(0, 10 - $citiesStartWith->count()))
                ->get();
            
            $cities = $citiesStartWith->merge($citiesContain);

            // Fallback: city absent from us_cities (e.g. USPS-alias cities).
            // Query us_zip_code_cities so suggestion dropdown still renders.
            if ($cities->isEmpty()) {
                $aliasRows = \Illuminate\Support\Facades\DB::table('us_zip_code_cities')
                    ->where('city', 'ILIKE', $input . '%')
                    ->orderBy('city')
                    ->limit(10)
                    ->get(['city', 'state_abbrev']);

                $seen = [];
                foreach ($aliasRows as $row) {
                    $key = strtolower($row->city . ',' . $row->state_abbrev);
                    if (!in_array($key, $seen)) {
                        $seen[]    = $key;
                        $results[] = $row->city . ', ' . $row->state_abbrev;
                    }
                }
            } else {
                foreach ($cities as $city) {
                    $stateAbbrev = $city->state ? $city->state->abbreviation : '';
                    $results[] = $city->name . ', ' . $stateAbbrev;
                }
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
        $this->cityFieldVisible = !empty($this->cities);
        
        $this->autoPopulateZipCodesFromCity($suggestion);
        $this->autoPopulateFromCity($suggestion);
    }
    
    protected function autoPopulateZipCodesFromCity($cityWithState)
    {
        $cityName = $this->extractNameFromLocationString($cityWithState);
        $stateAbbrev = $this->extractStateFromLocationString($cityWithState);

        if (empty($cityName)) return;

        $q = \App\Models\UsZipCode::where('city', 'ILIKE', $cityName);

        if ($stateAbbrev) {
            $q->where('state_abbrev', strtoupper($stateAbbrev));
        }

        $foundZips = $q->orderBy('zip_code')->limit(20)->pluck('zip_code')->toArray();

        // Fallback: some cities (e.g. Treasure Island) are alias cities whose
        // primary USPS row in us_zip_codes has a different city name.
        // Check us_zip_code_cities for alias entries.
        if (empty($foundZips)) {
            $aliasQ = \Illuminate\Support\Facades\DB::table('us_zip_code_cities')
                ->whereRaw('LOWER(city) = ?', [strtolower($cityName)]);

            if ($stateAbbrev) {
                $aliasQ->where('state_abbrev', strtoupper($stateAbbrev));
            }

            $foundZips = $aliasQ->orderBy('zip_code')->limit(20)->pluck('zip_code')->toArray();
        }

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
        if ($cityName && $stateAbbr) {
            $variants = \App\Services\CityNameNormalizer::searchVariants((string) $cityName);
            $cities = \App\Models\UsCity::with(['state', 'county.state'])
                ->where(function ($q) use ($variants) {
                    foreach ($variants as $v) {
                        $stripped = str_replace('.', '', $v);
                        $q->orWhere('name', 'ILIKE', $v)
                          ->orWhereRaw("REPLACE(name, '.', '') ILIKE ?", [$stripped]);
                    }
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
        $type = ucfirst($type);
        $property = "highlighted{$type}Index";
        $suggestions = lcfirst("{$type}Suggestions");

        if (count($this->$suggestions)) {
            $this->$property = min($this->$property + 1, count($this->$suggestions) - 1);
        }
    }

    public function decrementHighlight($type)
    {
        $type = ucfirst($type);
        $property = "highlighted{$type}Index";
        $this->$property = max($this->$property - 1, -1);
    }

    // Method to update the active tab
    public function setActiveTab($index)
    {
        $this->activeTab = $index;
        app(WizardEventService::class)->record(
            (string) $this->user_type,
            $this->listingId ? (int) $this->listingId : null,
            auth()->id() ? (int) auth()->id() : null,
            'tab_visited',
            'tab_' . $index,
            'create',
            session()->getId()
        );
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
        $canViewLocationDnaPanel = false;
        if ($this->listingId) {
            $isOwner = SellerAgentAuctionModel::where('id', $this->listingId)
                ->where('user_id', Auth::id())
                ->exists();
            $isAssigned = !$isOwner && AcceptedBidSummary::where('listing_type', 'seller_agent')
                ->where('listing_id', $this->listingId)
                ->where('agent_user_id', Auth::id())
                ->exists();
            $canViewLocationDnaPanel = $isOwner || $isAssigned;
        }

        $locationDna = $canViewLocationDnaPanel
            ? PropertyLocationDna::where('listing_type', 'seller_agent')->where('listing_id', $this->listingId)->first()
            : null;
        $locationPois = $canViewLocationDnaPanel
            ? PropertyLocationPoi::where('listing_type', 'seller_agent')->where('listing_id', $this->listingId)->orderBy('poi_category')->orderBy('rank')->get()
            : collect();
        $canGenerateLocationDna = false;
        if ($canViewLocationDnaPanel) {
            $dnaAddress = $this->address        ?: '';
            $dnaCity    = $this->property_city  ?: '';
            $dnaState   = $this->property_state ?: '';
            if ((empty($dnaAddress) || empty($dnaCity) || empty($dnaState)) && $this->listingId) {
                $m = SellerAgentAuctionModel::find($this->listingId);
                if ($m) {
                    if (empty($dnaAddress)) $dnaAddress = $m->info('address') ?: ($m->address ?? '');
                    if (empty($dnaCity))    $dnaCity    = $m->info('property_city') ?: '';
                    if (empty($dnaState))   $dnaState   = $m->info('property_state') ?: '';
                }
            }
            $canGenerateLocationDna = !empty($dnaAddress) && !empty($dnaCity) && !empty($dnaState);
        }

        return view('livewire.offer-listing.seller.offer-seller-listing', compact('locationDna', 'locationPois', 'canGenerateLocationDna', 'canViewLocationDnaPanel'))
            ->extends('layouts.main')->section('content');
    }
    protected function buildDraftPayload(): array
    {
        $isAgent = auth()->user() && auth()->user()->user_type === 'agent';

        $exchangeItemVal = $this->exchange_item;
        if (is_null($exchangeItemVal)) $exchangeItemVal = [];
        if (is_string($exchangeItemVal)) $exchangeItemVal = json_decode($exchangeItemVal, true) ?? [];
        $exchangeItemEncoded = json_encode(array_values(array_filter((array) $exchangeItemVal)));

        $configs = is_array($this->unit_type_configurations) ? $this->unit_type_configurations : [];
        $assetsToSave = (!empty($this->business_assets) && is_array($this->business_assets))
            ? json_encode($this->business_assets)
            : (is_array($this->assets) ? json_encode($this->assets) : $this->assets);

        $data = [
            'listing_title'                   => $this->listing_title,
            'user_type'                       => $this->user_type,
            'listing_status'                  => $this->listing_status,
            'auction_type'                    => $this->auction_type,
            'working_with_agent'              => $this->working_with_agent,
            'listing_date'                    => $this->listing_date,
            'desired_agent_hire_date'         => $this->desired_agent_hire_date,
            'expiration_date'                 => $this->expiration_date,
            'auction_time'                    => $this->auction_type === 'Bidding Period' ? $this->auction_time : '',
            'counties'                        => json_encode($this->counties),
            'state'                           => $this->state,
            'zip_code'                        => $this->zip_code,
            'zipCodes'                        => json_encode($this->zipCodes),
            'property_city'                   => $this->property_city,
            'property_county'                 => $this->property_county,
            'property_state'                  => $this->property_state,
            'property_zip'                    => $this->property_zip,
            'property_type'                   => $this->property_type,
            'property_items'                  => json_encode($this->property_items),
            'leasing_space'                   => $this->leasing_space,
            'other_property_items'            => $this->other_property_items,
            'condition_prop'                  => $this->condition_prop,
            'other_property_condition'        => $this->other_property_condition,
            'bathrooms'                       => $this->bathrooms,
            'other_bathrooms'                 => $this->other_bathrooms,
            'bedrooms'                        => $this->bedrooms,
            'other_bedrooms'                  => $this->other_bedrooms,
            'minimum_heated_square'           => $this->stripCommas($this->minimum_heated_square),
            'total_square_feet'               => $this->stripCommas($this->total_square_feet),
            'minimum_leaseable'               => $this->minimum_leaseable,
            'min_acreage'                     => $this->min_acreage,
            'total_acreage'                   => $this->total_acreage,
            'minimum_cap_rate'                => $this->stripCommas($this->minimum_cap_rate),
            'gross_annual_income'             => $this->stripCommas($this->gross_annual_income),
            'annual_operating_expenses'       => $this->stripCommas($this->annual_operating_expenses),
            'rent_roll_available'             => $this->rent_roll_available,
            'operating_statement_available'   => $this->operating_statement_available,
            'price_per_sqft'                  => $this->stripCommas($this->price_per_sqft),
            'existing_lease_type'             => $this->existing_lease_type,
            'other_lease_type'                => $this->other_lease_type,
            'lease_expiration'                => $this->lease_expiration,
            'lease_assignable'                => $this->lease_assignable,
            'annual_revenue'                  => $this->stripCommas($this->annual_revenue),
            'gross_profit'                    => $this->stripCommas($this->gross_profit),
            'sde_ebitda'                      => $this->stripCommas($this->sde_ebitda),
            'inventory_value'                 => $this->stripCommas($this->inventory_value),
            'ffe_value'                       => $this->stripCommas($this->ffe_value),
            'reason_for_sale'                 => $this->reason_for_sale,
            'other_reason_for_sale'           => $this->other_reason_for_sale,
            'employee_count'                  => $this->employee_count,
            'financial_statements_available'  => $this->financial_statements_available,
            'tax_returns_available'           => $this->tax_returns_available,
            'nda_required'                    => $this->nda_required,
            'business_location_leased'        => $this->business_location_leased,
            'business_lease_monthly_rent'     => $this->stripCommas($this->business_lease_monthly_rent),
            'business_lease_expiration'       => $this->business_lease_expiration,
            'business_lease_renewal_options'  => $this->business_lease_renewal_options,
            'business_lease_assignable'       => $this->business_lease_assignable,
            'business_lease_additional_terms' => $this->business_lease_additional_terms,
            'assets'                          => $assetsToSave,
            'assets_other'                    => $this->assets_other,
            'property_criteria'               => $this->property_criteria,
            'unit_size'                       => $this->unit_size,
            'unit_size_other'                 => $this->unit_size_other,
            'preferance_details'              => $this->preferance_details,
            'unit_type_configurations'        => json_encode(array_values($configs)),
            'year_built'                      => $this->year_built,
            'zoning'                          => $this->zoning,
            'roof_type'                       => json_encode($this->roof_type),
            'other_roof_type'                 => $this->other_roof_type,
            'exterior_construction'           => json_encode($this->exterior_construction),
            'other_exterior_construction'     => $this->other_exterior_construction,
            'foundation'                      => json_encode($this->foundation),
            'other_foundation'                => $this->other_foundation,
            'heating_and_fuel'                => json_encode($this->heating_and_fuel),
            'other_heating_and_fuel'          => $this->other_heating_and_fuel,
            'air_conditioning'                => json_encode($this->air_conditioning),
            'other_air_conditioning'          => $this->other_air_conditioning,
            'water'                           => json_encode($this->water),
            'other_water'                     => $this->other_water,
            'sewer'                           => json_encode($this->sewer),
            'other_sewer'                     => $this->other_sewer,
            'utilities'                       => json_encode($this->utilities),
            'other_utilities'                 => $this->other_utilities,
            'road_frontage'                   => json_encode($this->road_frontage),
            'other_road_frontage'             => $this->other_road_frontage,
            'road_surface_type'               => json_encode($this->road_surface_type),
            'other_road_surface_type'         => $this->other_road_surface_type,
            'electrical_service'              => json_encode($this->electrical_service),
            'other_electrical_service'        => $this->other_electrical_service,
            'ceiling_height'                  => $this->ceiling_height,
            'building_features'               => json_encode($this->building_features),
            'other_building_features'         => $this->other_building_features,
            'number_water_meters'             => $this->number_water_meters,
            'number_electric_meters'          => $this->number_electric_meters,
            'business_name'                   => $this->business_name,
            'year_established'                => $this->year_established,
            'licenses'                        => json_encode($this->licenses),
            'other_licenses'                  => $this->other_licenses,
            'sale_includes'                   => json_encode($this->sale_includes),
            'other_sale_includes'             => $this->other_sale_includes,
            'current_use'                     => json_encode($this->current_use),
            'other_current_use'               => $this->other_current_use,
            'lot_dimensions'                  => $this->lot_dimensions,
            'front_footage'                   => $this->front_footage,
            'number_of_wells'                 => $this->number_of_wells,
            'number_of_septics'               => $this->number_of_septics,
            'current_adjacent_use'            => json_encode($this->current_adjacent_use),
            'other_current_adjacent_use'      => $this->other_current_adjacent_use,
            'fences'                          => json_encode($this->fences),
            'other_fences'                    => $this->other_fences,
            'vegetation'                      => json_encode($this->vegetation),
            'other_vegetation'                => $this->other_vegetation,
            'buildable'                       => $this->buildable,
            'easements'                       => json_encode($this->easements),
            'other_easements'                 => $this->other_easements,
            'water_available'                 => $this->water_available,
            'water_available_other'           => $this->water_available_other,
            'sewer_available'                 => $this->sewer_available,
            'sewer_available_other'           => $this->sewer_available_other,
            'electric_available'              => $this->electric_available,
            'electric_available_other'        => $this->electric_available_other,
            'gas_available'                   => $this->gas_available,
            'gas_available_other'             => $this->gas_available_other,
            'telecom_available'               => $this->telecom_available,
            'telecom_available_other'         => $this->telecom_available_other,
            'sale_provision'                  => $this->sale_provision,
            'sale_provision_other'            => $this->sale_provision_other,
            'sale_provision_assignment'       => $this->sale_provision_assignment,
            'assignment_fee_type'             => $this->assignment_fee_type,
            'assignment_fee_amount'           => $this->stripCommas($this->assignment_fee_amount),
            'buyer_sell_contract'             => $this->buyer_sell_contract,
            'maximum_budget'                  => $this->stripCommas($this->maximum_budget),
            'starting_price'                  => $this->stripCommas($this->starting_price),
            'reserve_price'                   => $this->stripCommas($this->reserve_price),
            'buy_now_price'                   => $this->stripCommas($this->buy_now_price),
            'offered_financing'               => json_encode($this->offered_financing),
            'other_financing'                 => $this->other_financing,
            'cash_budget'                     => $this->cash_budget,
            'pre_approved'                    => $this->pre_approved,
            'pre_approval_amount'             => $this->pre_approval_amount,
            'purchase_price'                  => $this->stripCommas($this->purchase_price),
            'down_payment_type'               => $this->down_payment_type,
            'down_payment_amount'             => $this->stripCommas($this->down_payment_amount),
            'seller_financing_type'           => $this->seller_financing_type,
            'seller_financing_amount'         => $this->stripCommas($this->seller_financing_amount),
            'interest_rate'                   => $this->interest_rate,
            'loan_duration'                   => $this->loan_duration,
            'prepayment_penalty'              => $this->prepayment_penalty,
            'prepayment_penalty_amount'       => $this->stripCommas($this->prepayment_penalty_amount),
            'balloon_payment_amount'          => $this->stripCommas($this->balloon_payment_amount),
            'balloon_payment_date'            => $this->balloon_payment_date,
            'assumable_terms'                 => $this->assumable_terms,
            'max_assumable_rate'              => $this->stripCommas($this->max_assumable_rate),
            'assumable_monthly_escrow'        => $this->stripCommas($this->assumable_monthly_escrow),
            'assumable_loan_term_remaining'   => $this->assumable_loan_term_remaining,
            'assumable_loan_origination_date' => $this->assumable_loan_origination_date,
            'assumable_loan_servicer'         => $this->assumable_loan_servicer,
            'assumable_fee_type'              => $this->assumable_fee_type,
            'assumable_fee_amount'            => $this->stripCommas($this->assumable_fee_amount),
            'assumption_fee_responsibility'  => $this->assumption_fee_responsibility,
            'assumable_occupancy_requirement' => $this->assumable_occupancy_requirement,
            'assumable_occupancy_other'       => $this->assumable_occupancy_other,
            'max_monthly_payment'             => $this->stripCommas($this->max_monthly_payment),
            'gap_payment_type'                => $this->gap_payment_type,
            'gap_payment_amount'              => $this->stripCommas($this->gap_payment_amount),
            'exchange_item'                   => $exchangeItemEncoded,
            'other_exchange_item'             => $this->other_exchange_item,
            'exchange_item_value'             => $this->stripCommas($this->exchange_item_value),
            'exchange_item_condition'         => $this->exchange_item_condition,
            'additional_cash'                 => $this->stripCommas($this->additional_cash),
            'value_determination'             => $this->value_determination,
            'exchange_transfer_method'        => $this->exchange_transfer_method,
            'exchange_liens_disclosure'       => $this->exchange_liens_disclosure,
            'exchange_liens_details'          => $this->exchange_liens_details,
            'exchange_inspection_rights'      => $this->exchange_inspection_rights,
            'lease_option_price'              => $this->stripCommas($this->lease_option_price),
            'lease_option_terms'              => $this->lease_option_terms,
            'lease_option_duration'           => $this->lease_option_duration,
            'lease_option_payment'            => $this->stripCommas($this->lease_option_payment),
            'lease_option_conditions'         => $this->lease_option_conditions,
            'has_option_fee'                  => $this->has_option_fee,
            'option_fee_amount'               => $this->stripCommas($this->option_fee_amount),
            'seller_lease_option_fee_credit'  => $this->seller_lease_option_fee_credit,
            'seller_lease_option_fee_credit_percent' => $this->seller_lease_option_fee_credit_percent,
            'seller_lease_option_maintenance' => $this->seller_lease_option_maintenance,
            'seller_lease_option_extension_terms' => $this->seller_lease_option_extension_terms,
            'lease_purchase_price'            => $this->stripCommas($this->lease_purchase_price),
            'lease_purchase_terms'            => $this->lease_purchase_terms,
            'lease_purchase_duration'         => $this->lease_purchase_duration,
            'lease_purchase_payment'          => $this->stripCommas($this->lease_purchase_payment),
            'lease_purchase_conditions'       => $this->lease_purchase_conditions,
            'seller_lease_purchase_rent_credit' => $this->seller_lease_purchase_rent_credit,
            'seller_lease_purchase_rent_credit_type' => $this->seller_lease_purchase_rent_credit_type,
            'seller_lease_purchase_rent_credit_amount' => $this->stripCommas($this->seller_lease_purchase_rent_credit_amount),
            'seller_lease_purchase_deposit'   => $this->stripCommas($this->seller_lease_purchase_deposit),
            'seller_lease_purchase_maintenance' => $this->seller_lease_purchase_maintenance,
            'seller_lease_purchase_extension_terms' => $this->seller_lease_purchase_extension_terms,
            'lease_purchase_option_fee'       => $this->lease_purchase_option_fee,
            'lease_purchase_option_fee_amount' => $this->stripCommas($this->lease_purchase_option_fee_amount),
            'cryptocurrency_type'             => $this->cryptocurrency_type,
            'crypto_percentage'               => $this->stripCommas($this->crypto_percentage),
            'cash_percentage_crypto'          => $this->stripCommas($this->cash_percentage_crypto),
            'nft_description'                 => $this->nft_description,
            'nft_percentage'                  => $this->nft_percentage,
            'cash_percentage_nft'             => $this->cash_percentage_nft,
            'tenant_require'                  => json_encode($this->tenant_require),
            'carport_needed'                  => $this->carport_needed,
            'other_carport_needed'            => $this->other_carport_needed,
            'garage_needed'                   => $this->garage_needed,
            'other_garage_needed'             => $this->other_garage_needed,
            'garage_parking_spaces'           => $this->garage_parking_spaces,
            'garage_parking_spaces_option'    => $this->garage_parking_spaces_option,
            'other_parking_space_wrapper'     => $this->other_parking_space_wrapper,
            'pool_needed'                     => $this->pool_needed,
            'pool_type'                       => json_encode($this->pool_type),
            'view_preference'                 => json_encode($this->view_preference),
            'other_preferences'               => $this->other_preferences,
            'business_assets'                 => json_encode($this->business_assets),
            'appliances'                      => json_encode($this->appliances),
            'other_appliances'                => $this->other_appliances,
            'real_estate_purchase'            => $this->real_estate_purchase,
            'number_of_unit'                  => $this->number_of_unit,
            'number_of_unit_other'            => $this->number_of_unit_other,
            'unit_number'                     => $this->unit_number,
            'unit_buildings'                  => $this->unit_buildings,
            'minimum_annual_net_income'       => $this->stripCommas($this->minimum_annual_net_income),
            'leasing_55_plus'                 => $this->leasing_55_plus,
            'non_negotiable_amenities'        => json_encode($this->non_negotiable_amenities),
            'other_non_negotiable_amenities'  => $this->other_non_negotiable_amenities,
            'budget'                          => $this->budget,
            'lease_for'                       => json_encode($this->lease_for),
            'other_lease_for'                 => $this->other_lease_for,
            'lease_by'                        => $this->lease_by,
            'lease_date'                      => $this->lease_date,
            'pets'                            => $this->pets,
            'number_of_pets'                  => $this->number_of_pets,
            'breed_of_pets'                   => $this->breed_of_pets,
            'type_of_pets'                    => $this->type_of_pets,
            'weight_of_pets'                  => $this->weight_of_pets,
            'credit_scroe_rating'             => json_encode($this->credit_scroe_rating),
            'prior_eviction'                  => $this->prior_eviction,
            'eviction_explanation'            => $this->eviction_explanation,
            'prior_felony'                    => $this->prior_felony,
            'prior_felony_explanation'        => $this->prior_felony_explanation,
            'monthly_income'                  => $this->monthly_income,
            'number_occupant'                 => $this->number_occupant,
            'other_services'                  => $this->other_services,
            'additional_details'              => $this->additional_details,
            'commission_structure'            => $this->commission_structure,
            // BYO-C4: keep the commission-structure-type group in the draft payload
            // so a saved draft preserves broker compensation on resume.
            'commission_structure_type'       => $this->commission_structure_type,
            'commission_structure_type_fee_flat' => $this->stripCommas($this->commission_structure_type_fee_flat),
            'commission_structure_type_fee_percentage' => $this->commission_structure_type_fee_percentage,
            'commission_structure_type_fee_other' => $this->commission_structure_type_fee_other,
            'commission_structure_type_fee_flat_combo' => $this->stripCommas($this->commission_structure_type_fee_flat_combo),
            'commission_structure_type_fee_percentage_combo' => $this->commission_structure_type_fee_percentage_combo,
            'lease_fee_type'                  => $this->lease_fee_type,
            'lease_fee_flat'                  => $this->stripCommas($this->lease_fee_flat),
            'lease_fee_percentage'            => $this->lease_fee_percentage,
            'lease_fee_months'                => $this->lease_fee_months,
            'lease_fee_percentage_monthly_rent' => $this->lease_fee_percentage_monthly_rent,
            'lease_fee_flat_combo'            => $this->stripCommas($this->lease_fee_flat_combo),
            'lease_fee_percentage_combo'      => $this->lease_fee_percentage_combo,
            'lease_fee_other'                 => $this->lease_fee_other,
            'purchase_fee_type'               => $this->purchase_fee_type,
            'purchase_fee_percentage'         => $this->purchase_fee_percentage,
            'purchase_fee_flat'               => $this->stripCommas($this->purchase_fee_flat),
            'purchase_fee_percentage_combo'   => $this->purchase_fee_percentage_combo,
            'purchase_fee_flat_combo'         => $this->stripCommas($this->purchase_fee_flat_combo),
            'purchase_fee_other'              => $this->purchase_fee_other,
            'lease_option_fee_type'           => $this->lease_option_fee_type,
            'lease_option_fee_flat'           => $this->stripCommas($this->lease_option_fee_flat),
            'lease_option_fee_percentage'     => $this->lease_option_fee_percentage,
            'lease_option_fee_other'          => $this->lease_option_fee_other,
            'protection_period'               => $this->protection_period,
            'early_termination_fee_option'    => $this->early_termination_fee_option,
            'early_termination_fee_amount'    => $this->stripCommas($this->early_termination_fee_amount),
            'lease_type'                      => $this->lease_type,
            'lease_value'                     => $this->stripCommas($this->lease_value),
            'purchase_type'                   => $this->purchase_type,
            'purchase_value'                  => $this->stripCommas($this->purchase_value),
            'interested_lease_option_agreement' => $this->interested_lease_option_agreement,
            'retainer_fee_option'             => $this->retainer_fee_option,
            'retainer_fee_amount'             => $this->stripCommas($this->retainer_fee_amount),
            'retainer_fee_application'        => $this->retainer_fee_application,
            'agency_agreement_timeframe'      => $this->agency_agreement_timeframe,
            'agency_agreement_custom'         => $this->agency_agreement_custom,
            'brokerage_relationship'          => $this->brokerage_relationship,
            'additional_details_broker'       => $this->additional_details_broker,
            'person_meeting'                  => $this->person_meeting,
            'meeting_details_first_name'      => $this->meeting_details_first_name,
            'meeting_details_last_name'       => $this->meeting_details_last_name,
            'meeting_details_phone'           => $this->meeting_details_phone,
            'meeting_details_email'           => $this->meeting_details_email,
            'address'                         => $this->address,
            'unit_address'                    => $this->unit_address,
            'property_lat'                    => $this->property_lat,
            'property_lng'                    => $this->property_lng,
            'google_place_id'                 => $this->google_place_id,
            'meeting_details_meeting_time'    => $this->meeting_details_meeting_time,
            'meeting_details_meeting_date'    => $this->meeting_details_meeting_date,
            'meeting_details_time_zone'       => $this->meeting_details_time_zone,
            'meeting_details_instructions'    => $this->meeting_details_instructions,
            'service_completion_date'         => $this->service_completion_date,
            'service_completion_time'         => $this->service_completion_time,
            'service_time_zone'               => $this->service_time_zone,
            'meeting_details_additional_details' => $this->meeting_details_additional_details,
            'list_criteria'                   => $this->list_criteria,
            'list_criteria_fee'               => $this->list_criteria_fee,
            'market_groups'                   => $this->market_groups,
            'market_groups_fee'               => $this->market_groups_fee,
            'promote_social'                  => $this->promote_social,
            'promote_social_fee'              => $this->promote_social_fee,
            'launch_ads'                      => $this->launch_ads,
            'launch_ads_fee'                  => $this->launch_ads_fee,
            'include_marketing_fee'           => $this->include_marketing_fee,
            'marketing_materials_fee'         => $this->marketing_materials_fee,
            'email_notifications_fee'         => $this->email_notifications_fee,
            'off_market_search_fee'           => $this->off_market_search_fee,
            'mls_filter_fee'                  => $this->mls_filter_fee,
            'email_marketing_fee'             => $this->email_marketing_fee,
            'schedule_showings'               => $this->schedule_showings,
            'number_of_showings_to_schedule'  => $this->number_of_showings_to_schedule,
            'schedule_showings_fee'           => $this->schedule_showings_fee,
            'attend_showings'                 => $this->attend_showings,
            'number_of_showings_to_attend'    => $this->number_of_showings_to_attend,
            'attend_showings_fee'             => $this->attend_showings_fee,
            'provide_virtual_tours'           => $this->provide_virtual_tours,
            'number_of_virtual_tours'         => $this->number_of_virtual_tours,
            'virtual_tours_fee'               => $this->virtual_tours_fee,
            'assist_application'              => $this->assist_application,
            'assist_application_fee'          => $this->assist_application_fee,
            'collect_documents'               => $this->collect_documents,
            'collect_documents_fee'           => $this->collect_documents_fee,
            'submit_application'              => $this->submit_application,
            'submit_application_fee'          => $this->submit_application_fee,
            'review_lease'                    => $this->review_lease,
            'review_lease_fee'                => $this->review_lease_fee,
            'provide_lease_form'              => $this->provide_lease_form,
            'provide_lease_form_fee'          => $this->provide_lease_form_fee,
            'coordinate_signing'              => $this->coordinate_signing,
            'coordinate_signing_fee'          => $this->coordinate_signing_fee,
            'prepare_application_fee'         => $this->prepare_application_fee,
            'move_in_inspection_fee'          => $this->move_in_inspection_fee,
            'moving_resources_fee'            => $this->moving_resources_fee,
            'short_term_housing_fee'          => $this->short_term_housing_fee,
            'rental_rights_fee'               => $this->rental_rights_fee,
            'lease_advice_fee'                => $this->lease_advice_fee,
            'neighborhood_insights_fee'       => $this->neighborhood_insights_fee,
            'neighborhood_marketing_fee'      => $this->neighborhood_marketing_fee,
            'neighborhood_materials_fee'      => $this->neighborhood_materials_fee,
            'custom_services'                 => json_encode($this->custom_services),
            'total_marketing_fee'             => $this->total_marketing_fee,
            'total_flat_fee'                  => $this->total_flat_fee,
            'fees'                            => json_encode($this->fees),
            'enable'                          => json_encode($this->enable),
            'showings_count'                  => $this->showings_count,
            'attend_showings_count'           => $this->attend_showings_count,
            'virtual_tours_count'             => $this->virtual_tours_count,
            'understand_terms'                => $this->understand_terms,
            'staging_duration'                => $this->staging_duration,
            'open_house_count'                => $this->open_house_count,
            'virtual_showings_count'          => $this->virtual_showings_count,
            'initial_deposit_requested'       => $this->initial_deposit_requested,
            'initial_deposit_timeframe'       => $this->initial_deposit_timeframe,
            'initial_deposit_timeframe_other' => $this->initial_deposit_timeframe_other,
            'additional_deposit_requested'    => $this->additional_deposit_requested,
            'additional_deposit_timeframe'    => $this->additional_deposit_timeframe,
            'additional_deposit_timeframe_other' => $this->additional_deposit_timeframe_other,
            'escrow_agent_preference'         => $this->escrow_agent_preference,
            'preferred_inspection_period'     => $this->preferred_inspection_period,
            'inspection_contingency_preference' => $this->inspection_contingency_preference,
            'appraisal_contingency_preference' => $this->appraisal_contingency_preference,
            'appraisal_contingency_period'    => $this->appraisal_contingency_period,
            'financing_contingency_preference' => $this->financing_contingency_preference,
            'financing_contingency_period'    => $this->financing_contingency_period,
            'sale_of_buyer_property_contingency' => $this->sale_of_buyer_property_contingency,
            'sale_of_buyer_property_period'   => $this->sale_of_buyer_property_period,
            'seller_contribution_credit_offered' => $this->seller_contribution_credit_offered,
            'seller_contribution_amount_details' => $this->seller_contribution_amount_details,
            'possession_preference'           => $this->possession_preference,
            'possession_details'              => $this->possession_details,
            'included_personal_property'      => $this->included_personal_property,
            'excluded_items'                  => $this->excluded_items,
            'home_warranty_offered'           => $this->home_warranty_offered,
            'home_warranty_amount_details'    => $this->home_warranty_amount_details,
            'hoa_condo_association_terms'     => $this->hoa_condo_association_terms,
            'additional_seller_sale_terms'    => $this->additional_seller_sale_terms,
            'parcel_id'                       => $this->parcel_id,
            'tax_year'                        => $this->tax_year,
            'annual_property_taxes'           => $this->stripCommas($this->annual_property_taxes),
            'additional_parcels'              => $this->additional_parcels,
            'total_parcel_count'              => $this->total_parcel_count,
            'additional_parcel_ids'           => $this->additional_parcel_ids,
            'legal_description'               => $this->legal_description,
            'flood_zone_code'                 => $this->flood_zone_code,
            'flood_zone_code_other'           => $this->flood_zone_code_other,
            'flood_insurance_required'        => $this->flood_insurance_required,
            'flood_zone_panel'                => $this->flood_zone_panel,
            'flood_zone_date'                 => $this->flood_zone_date,
            'waterfront'                      => $this->waterfront,
            'water_frontage'                  => $this->water_frontage,
            'waterfront_feet'                 => $this->waterfront_feet,
            'water_access'                    => json_encode($this->water_access),
            'water_view'                      => json_encode($this->water_view),
            'interior_features'               => json_encode($this->interior_features),
            'other_water_access'              => $this->other_water_access,
            'other_water_view'                => $this->other_water_view,
            'other_interior_features'         => $this->other_interior_features,
            'has_cdd'                         => $this->has_cdd,
            'annual_cdd_fee'                  => $this->stripCommas($this->annual_cdd_fee),
            'has_special_assessments'         => $this->has_special_assessments,
            'special_assessment_amount'       => $this->stripCommas($this->special_assessment_amount),
            'special_assessment_description'  => $this->special_assessment_description,
            'has_hoa'                         => $this->has_hoa,
            'association_type'                => $this->association_type,
            'association_type_other'          => $this->association_type_other,
            'association_name'                => $this->association_name,
            'association_fee_amount'          => $this->stripCommas($this->association_fee_amount),
            'association_fee_frequency'       => $this->association_fee_frequency,
            'association_fee_frequency_other' => $this->association_fee_frequency_other,
            'association_approval_required'   => $this->association_approval_required,
            'association_approval_process'    => $this->association_approval_process,
            'association_application_fee'     => $this->stripCommas($this->association_application_fee),
            'association_fee_includes'        => json_encode($this->association_fee_includes),
            'association_fee_includes_other'  => $this->association_fee_includes_other,
            'association_amenities'           => json_encode($this->association_amenities),
            'association_amenities_other'     => $this->association_amenities_other,
            'leasing_restrictions'            => $this->leasing_restrictions,
            'min_lease_period'                => $this->min_lease_period,
            'min_lease_period_other'          => $this->min_lease_period_other,
            'max_leases_per_year'             => $this->max_leases_per_year,
            'additional_lease_restrictions'   => $this->additional_lease_restrictions,
            'pet_restrictions'                => $this->pet_restrictions,
            'pet_restrictions_detail'         => $this->pet_restrictions_detail,
            'seller_disclosure_available'     => $this->seller_disclosure_available,
            'survey_available'                => $this->survey_available,
            'inspection_report_available'     => $this->inspection_report_available,
            'hoa_condo_docs_available'        => $this->hoa_condo_docs_available,
            'flood_disclosure_available'      => $this->flood_disclosure_available,
            'lead_based_paint_disclosure'     => $this->lead_based_paint_disclosure,
            'environmental_report_available'  => $this->environmental_report_available,
            'additional_documents'            => json_encode($this->additional_documents),
            'other_document_type'             => $this->other_document_type,
            'doc_rows'                        => json_encode($this->doc_rows),
            'seller_disclosure_file_path'     => $this->seller_disclosure_file_path,
            'survey_file_path'                => $this->survey_file_path,
            'inspection_report_file_path'     => $this->inspection_report_file_path,
            'hoa_condo_docs_file_path'        => $this->hoa_condo_docs_file_path,
            'flood_disclosure_file_path'      => $this->flood_disclosure_file_path,
            'lead_based_paint_file_path'      => $this->lead_based_paint_file_path,
            'environmental_report_file_path'  => $this->environmental_report_file_path,
            'first_name'                      => $this->first_name,
            'last_name'                       => $this->last_name,
            'phone_number'                    => preg_replace('/\D/', '', $this->phone_number),
            'email'                           => $this->email,
            'current_status'                  => $this->current_status,
            'video_link'                      => $this->video_link,
            'listing_ai_faq'                  => json_encode($this->listing_ai_faq ?: []),
            'photo'                           => is_string($this->photo) ? $this->photo : '',
            'video_tour_url'                  => $this->videoTourUrl ?? '',
            'virtual_tour_url'                => $this->virtualTourUrl ?? '',
            'property_photos'                 => is_array($this->propertyPhotos) ? json_encode($this->propertyPhotos) : ($this->propertyPhotos ?? ''),
            'listing_documents'               => is_string($this->listingDocuments) ? $this->listingDocuments : '',
        ];

        if ($isAgent) {
            $data['agent_brokerage']      = $this->agent_brokerage;
            $data['agent_license_number'] = $this->agent_license_number;
            $data['agent_nar_member_id']  = $this->agent_nar_member_id;
        }

        return $data;
    }

    protected function buildDraftPayloadHash(): string
    {
        $data = $this->buildDraftPayload();
        ksort($data);
        return hash('sha256', json_encode($data));
    }

    public function saveDraft()
    {
        try {
            $this->isDraft = true;

            if (!self::SAVE_AS_NEW_DRAFT && $this->isResumingDraft && $this->listingId) {
                $auction = SellerAgentAuctionModel::find($this->listingId) ?? new SellerAgentAuctionModel();
                $auction->user_id = Auth::id();
                $auction->title = $this->listing_title;
                $auction->is_draft = true;
                if (empty($auction->address)) {
                    $auction->address = !empty($this->listing_title) ? $this->listing_title : 'TBD';
                }
                $auction->save();
                $this->listingId = $auction->id;
                $this->saveAllMetadata($auction);
                app(WizardEventService::class)->record(
                    (string) $this->user_type,
                    $this->listingId ? (int) $this->listingId : null,
                    auth()->id() ? (int) auth()->id() : null,
                    'save_draft',
                    'tab_' . $this->activeTab,
                    'create',
                    session()->getId()
                );
                session()->flash('success', 'Draft updated successfully.');
                return redirect()->route('offer.listing.seller.edit', ['auctionId' => $this->listingId]);
            }

            $newPayloadHash = $this->buildDraftPayloadHash();

            $previousDraft = $this->listingId
                ? SellerAgentAuctionModel::find($this->listingId)
                : null;

            $previousVersion = 0;
            $parentDraftId   = null;
            if ($previousDraft && $previousDraft->is_draft) {
                $oldHash = $previousDraft->info('draft_payload_hash') ?? '';
                if ($oldHash === $newPayloadHash) {
                    session()->flash('success', 'No changes detected — draft is already up to date.');
                    return redirect()->route('offer.listing.seller.edit', ['auctionId' => $this->listingId]);
                }
                $previousVersion = (int) ($previousDraft->info('draft_version') ?? 1);
                $parentDraftId   = $previousDraft->id;
            }

            $auction = new SellerAgentAuctionModel();
            $auction->user_id  = Auth::id();
            $auction->title    = $this->listing_title;
            $auction->is_draft = true;
            $auction->address  = !empty($this->address) ? $this->address : (!empty($this->listing_title) ? $this->listing_title : 'TBD');
            $auction->save();

            if ($parentDraftId === null) {
                \App\Services\ReferralLinkService::persistListingReferral($auction);
            }

            $this->listingId = $auction->id;

            $this->saveAllMetadata($auction);

            $auction->saveMeta('draft_version',      $previousVersion + 1);
            $auction->saveMeta('parent_draft_id',    $parentDraftId);
            $auction->saveMeta('draft_payload_hash', $newPayloadHash);

            app(\App\Services\AskAi\AskAiKnowledgeSnapshotBuilderService::class)->buildSilently('seller', $this->listingId);

            app(WizardEventService::class)->record(
                (string) $this->user_type,
                $this->listingId ? (int) $this->listingId : null,
                auth()->id() ? (int) auth()->id() : null,
                'save_draft',
                'tab_' . $this->activeTab,
                'create',
                session()->getId()
            );
            session()->flash('success', 'Draft saved successfully (Version ' . ($previousVersion + 1) . '). You can return later to complete your listing.');
            return redirect()->route('offer.listing.seller.edit', ['auctionId' => $this->listingId]);
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

            $this->cityFieldVisible = !empty($this->cities);

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

            // Financial Details tab — Income
            $this->gross_annual_income = $auction->get->gross_annual_income ?? '';
            $this->annual_operating_expenses = $auction->get->annual_operating_expenses ?? '';
            $this->rent_roll_available = $auction->get->rent_roll_available ?? '';
            $this->operating_statement_available = $auction->get->operating_statement_available ?? '';

            // Financial Details tab — Commercial
            $this->price_per_sqft = $auction->get->price_per_sqft ?? '';
            $this->existing_lease_type = $auction->get->existing_lease_type ?? '';
            $this->other_lease_type = $auction->get->other_lease_type ?? '';
            $this->lease_expiration = $auction->get->lease_expiration ?? '';
            $this->lease_assignable = $auction->get->lease_assignable ?? '';

            // Financial Details tab — Business
            $this->business_type = $auction->get->business_type ?? '';
            $this->annual_revenue = $auction->get->annual_revenue ?? '';
            $this->gross_profit = $auction->get->gross_profit ?? '';
            $this->sde_ebitda = $auction->get->sde_ebitda ?? '';
            $this->inventory_value = $auction->get->inventory_value ?? '';
            $this->ffe_value = $auction->get->ffe_value ?? '';
            $this->reason_for_sale = $auction->get->reason_for_sale ?? '';
            $this->other_reason_for_sale = $auction->get->other_reason_for_sale ?? '';
            $this->employee_count = $auction->get->employee_count ?? '';
            $this->financial_statements_available = $auction->get->financial_statements_available ?? '';
            $this->tax_returns_available = $auction->get->tax_returns_available ?? '';
            $this->nda_required = $auction->get->nda_required ?? '';
            $this->business_location_leased = $auction->get->business_location_leased ?? '';
            $this->business_lease_monthly_rent = $auction->get->business_lease_monthly_rent ?? '';
            $this->business_lease_expiration = $auction->get->business_lease_expiration ?? '';
            $this->business_lease_renewal_options = $auction->get->business_lease_renewal_options ?? '';
            $this->business_lease_assignable = $auction->get->business_lease_assignable ?? '';
            $this->business_lease_additional_terms = $auction->get->business_lease_additional_terms ?? '';

            $this->unit_number = $auction->get->unit_number ?? '';
            $this->unit_buildings = $auction->get->unit_buildings ?? '';
            $this->assets = $auction->get->assets;
            $this->assets_other = $auction->get->assets_other;
            $this->property_criteria = $auction->get->property_criteria;
            $this->unit_size = $auction->get->unit_size;
            $this->unit_size_other = $auction->get->unit_size_other;
            $this->preferance_details = $auction->get->preferance_details;


            // Income / multi-unit configuration
            $unitTypeConfigRaw = $auction->get->unit_type_configurations ?? null;
            if ($unitTypeConfigRaw) {
                $decoded = is_array($unitTypeConfigRaw) ? $unitTypeConfigRaw : json_decode($unitTypeConfigRaw, true);
                if (is_array($decoded) && count($decoded) > 0) {
                    $this->unit_type_configurations = array_values($decoded);
                }
            }

            // MLS property detail fields
            $this->year_built = $auction->get->year_built ?? '';
            $this->zoning = $auction->get->zoning ?? '';
            $this->roof_type = is_string($auction->get->roof_type) ? json_decode($auction->get->roof_type, true) ?? [] : (array)($auction->get->roof_type ?? []);
            $this->other_roof_type = $auction->get->other_roof_type ?? '';
            $this->exterior_construction = is_string($auction->get->exterior_construction) ? json_decode($auction->get->exterior_construction, true) ?? [] : (array)($auction->get->exterior_construction ?? []);
            $this->other_exterior_construction = $auction->get->other_exterior_construction ?? '';
            $this->foundation = is_string($auction->get->foundation) ? json_decode($auction->get->foundation, true) ?? [] : (array)($auction->get->foundation ?? []);
            $this->other_foundation = $auction->get->other_foundation ?? '';
            $this->heating_and_fuel = is_string($auction->get->heating_and_fuel) ? json_decode($auction->get->heating_and_fuel, true) ?? [] : (array)($auction->get->heating_and_fuel ?? []);
            $this->other_heating_and_fuel = $auction->get->other_heating_and_fuel ?? '';
            $this->air_conditioning = is_string($auction->get->air_conditioning) ? json_decode($auction->get->air_conditioning, true) ?? [] : (array)($auction->get->air_conditioning ?? []);
            $this->other_air_conditioning = $auction->get->other_air_conditioning ?? '';
            $this->water = is_string($auction->get->water) ? json_decode($auction->get->water, true) ?? [] : (array)($auction->get->water ?? []);
            $this->other_water = $auction->get->other_water ?? '';
            $this->sewer = is_string($auction->get->sewer) ? json_decode($auction->get->sewer, true) ?? [] : (array)($auction->get->sewer ?? []);
            $this->other_sewer = $auction->get->other_sewer ?? '';
            $this->utilities = is_string($auction->get->utilities) ? json_decode($auction->get->utilities, true) ?? [] : (array)($auction->get->utilities ?? []);
            $this->other_utilities = $auction->get->other_utilities ?? '';
            $this->road_frontage = is_string($auction->get->road_frontage) ? json_decode($auction->get->road_frontage, true) ?? [] : (array)($auction->get->road_frontage ?? []);
            $this->other_road_frontage = $auction->get->other_road_frontage ?? '';
            $this->road_surface_type = is_string($auction->get->road_surface_type) ? json_decode($auction->get->road_surface_type, true) ?? [] : (array)($auction->get->road_surface_type ?? []);
            $this->other_road_surface_type = $auction->get->other_road_surface_type ?? '';
            $this->electrical_service = is_string($auction->get->electrical_service) ? json_decode($auction->get->electrical_service, true) ?? [] : (array)($auction->get->electrical_service ?? []);
            $this->other_electrical_service = $auction->get->other_electrical_service ?? '';
            $this->ceiling_height = $auction->get->ceiling_height ?? '';
            $this->building_features = is_string($auction->get->building_features) ? json_decode($auction->get->building_features, true) ?? [] : (array)($auction->get->building_features ?? []);
            $this->other_building_features = $auction->get->other_building_features ?? '';
            $this->number_water_meters = $auction->get->number_water_meters ?? '';
            $this->number_electric_meters = $auction->get->number_electric_meters ?? '';
            $this->business_name = $auction->get->business_name ?? '';
            $this->year_established = $auction->get->year_established ?? '';
            $this->licenses = is_string($auction->get->licenses) ? json_decode($auction->get->licenses, true) ?? [] : (array)($auction->get->licenses ?? []);
            $this->other_licenses = $auction->get->other_licenses ?? '';
            $this->sale_includes = is_string($auction->get->sale_includes) ? json_decode($auction->get->sale_includes, true) ?? [] : (array)($auction->get->sale_includes ?? []);
            $this->other_sale_includes = $auction->get->other_sale_includes ?? '';
            $this->current_use = is_string($auction->get->current_use) ? json_decode($auction->get->current_use, true) ?? [] : (array)($auction->get->current_use ?? []);
            $this->other_current_use = $auction->get->other_current_use ?? '';
            $this->lot_dimensions = $auction->get->lot_dimensions ?? '';
            $this->front_footage = $auction->get->front_footage ?? '';
            $this->number_of_wells = $auction->get->number_of_wells ?? '';
            $this->number_of_septics = $auction->get->number_of_septics ?? '';
            $this->current_adjacent_use = is_string($auction->get->current_adjacent_use) ? json_decode($auction->get->current_adjacent_use, true) ?? [] : (array)($auction->get->current_adjacent_use ?? []);
            $this->other_current_adjacent_use = $auction->get->other_current_adjacent_use ?? '';
            $this->fences = is_string($auction->get->fences) ? json_decode($auction->get->fences, true) ?? [] : (array)($auction->get->fences ?? []);
            $this->other_fences = $auction->get->other_fences ?? '';
            $this->vegetation = is_string($auction->get->vegetation) ? json_decode($auction->get->vegetation, true) ?? [] : (array)($auction->get->vegetation ?? []);
            $this->other_vegetation = $auction->get->other_vegetation ?? '';
            $this->buildable = $auction->get->buildable ?? '';
            $this->easements = is_string($auction->get->easements) ? json_decode($auction->get->easements, true) ?? [] : (array)($auction->get->easements ?? []);
            $this->other_easements = $auction->get->other_easements ?? '';
            $this->water_available = $auction->get->water_available ?? '';
            $this->water_available_other = $auction->get->water_available_other ?? '';
            $this->sewer_available = $auction->get->sewer_available ?? '';
            $this->sewer_available_other = $auction->get->sewer_available_other ?? '';
            $this->electric_available = $auction->get->electric_available ?? '';
            $this->electric_available_other = $auction->get->electric_available_other ?? '';
            $this->gas_available = $auction->get->gas_available ?? '';
            $this->gas_available_other = $auction->get->gas_available_other ?? '';
            $this->telecom_available = $auction->get->telecom_available ?? '';
            $this->telecom_available_other = $auction->get->telecom_available_other ?? '';

            // Sale Provision
            $this->sale_provision = is_string($auction->get->sale_provision) ? json_decode($auction->get->sale_provision, true) ?? [] : (array)($auction->get->sale_provision ?? []);
            $this->sale_provision_other = $auction->get->sale_provision_other;
            $this->sale_provision_assignment = $auction->get->sale_provision_assignment;
            $this->assignment_fee_type = $auction->get->assignment_fee_type;
            $this->assignment_fee_amount = $auction->get->assignment_fee_amount;
            $this->buyer_sell_contract = $auction->get->buyer_sell_contract;

            // Budget & Financing
            $this->maximum_budget = $auction->get->maximum_budget;
            $this->starting_price = $auction->get->starting_price ?? '';
            $this->reserve_price = $auction->get->reserve_price ?? '';
            $this->buy_now_price = $auction->get->buy_now_price ?? '';
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
            $this->assumption_fee_responsibility = $auction->get->assumption_fee_responsibility ?? '';
            $this->assumable_occupancy_requirement = $auction->get->assumable_occupancy_requirement ?? '';
            $this->assumable_occupancy_other = $auction->get->assumable_occupancy_other ?? '';
            $this->max_monthly_payment = $auction->get->max_monthly_payment;
            $this->gap_payment_type = $auction->get->gap_payment_type;
            $this->gap_payment_amount = $auction->get->gap_payment_amount;
            $this->target_closing_date = $auction->get->target_closing_date ?? '';
            $this->occupant_status = $auction->get->occupant_status ?? '';

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

            $this->other_services = $auction->get->other_services;
            $this->additional_details = $auction->get->additional_details;

            // Broker compensation
            $this->commission_structure = $auction->get->commission_structure;
            // BYO-C4: load the commission-structure-type group so resuming a draft /
            // re-editing repopulates broker compensation (was never loaded on Create).
            $this->commission_structure_type = $auction->get->commission_structure_type ?? '';
            $this->commission_structure_type_fee_flat = $auction->get->commission_structure_type_fee_flat ?? '';
            $this->commission_structure_type_fee_percentage = $auction->get->commission_structure_type_fee_percentage ?? '';
            $this->commission_structure_type_fee_other = $auction->get->commission_structure_type_fee_other ?? '';
            $this->commission_structure_type_fee_flat_combo = $auction->get->commission_structure_type_fee_flat_combo ?? '';
            $this->commission_structure_type_fee_percentage_combo = $auction->get->commission_structure_type_fee_percentage_combo ?? '';
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
            $this->agent_brokerage = $auction->get->agent_brokerage ?? '';
            $this->agent_license_number = $auction->get->agent_license_number ?? '';
            $this->agent_nar_member_id = $auction->get->agent_nar_member_id ?? '';
            $this->current_status = $auction->get->current_status ?? '';
            $this->video_link = $auction->get->video_link;
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
            $this->person_meeting = $auction->get->person_meeting;
            $this->meeting_details_first_name = $auction->get->meeting_details_first_name;
            $this->meeting_details_last_name = $auction->get->meeting_details_last_name;
            $this->meeting_details_phone = $auction->get->meeting_details_phone;
            $this->meeting_details_email = $auction->get->meeting_details_email;
            $this->address = $auction->get->address;
            $this->unit_address = $auction->get->unit_address ?? '';
            $this->property_lat = $auction->get->property_lat ?? '';
            $this->property_lng = $auction->get->property_lng ?? '';
            $this->google_place_id = $auction->get->google_place_id ?? '';
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

            // Sale Terms Questions
            $this->initial_deposit_type = $auction->get->initial_deposit_type ?? '$';
            $this->initial_deposit_requested = $auction->get->initial_deposit_requested ?? '';
            $this->initial_deposit_timeframe = $auction->get->initial_deposit_timeframe ?? '';
            $this->initial_deposit_timeframe_other = $auction->get->initial_deposit_timeframe_other ?? '';
            $this->additional_deposit_type = $auction->get->additional_deposit_type ?? '$';
            $this->additional_deposit_requested = $auction->get->additional_deposit_requested ?? '';
            $this->additional_deposit_timeframe = $auction->get->additional_deposit_timeframe ?? '';
            $this->additional_deposit_timeframe_other = $auction->get->additional_deposit_timeframe_other ?? '';
            $this->escrow_agent_preference = $auction->get->escrow_agent_preference ?? '';
            $this->preferred_inspection_period = $auction->get->preferred_inspection_period ?? '';
            $this->inspection_contingency_preference = $auction->get->inspection_contingency_preference ?? '';
            $this->appraisal_contingency_preference = $auction->get->appraisal_contingency_preference ?? '';
            $this->appraisal_contingency_period = $auction->get->appraisal_contingency_period ?? '';
            $this->financing_contingency_preference = $auction->get->financing_contingency_preference ?? '';
            $this->financing_contingency_period = $auction->get->financing_contingency_period ?? '';
            $this->sale_of_buyer_property_contingency = $auction->get->sale_of_buyer_property_contingency ?? '';
            $this->sale_of_buyer_property_period = $auction->get->sale_of_buyer_property_period ?? '';
            $this->seller_contribution_credit_offered = $auction->get->seller_contribution_credit_offered ?? '';
            $this->seller_contribution_amount_details = $auction->get->seller_contribution_amount_details ?? '';
            $this->possession_preference = $auction->get->possession_preference ?? '';
            $this->possession_details = $auction->get->possession_details ?? '';
            $this->included_personal_property = $auction->get->included_personal_property ?? '';
            $this->excluded_items = $auction->get->excluded_items ?? '';
            $this->home_warranty_offered = $auction->get->home_warranty_offered ?? '';
            $this->home_warranty_amount_details = $auction->get->home_warranty_amount_details ?? '';
            $this->hoa_condo_association_terms = $auction->get->hoa_condo_association_terms ?? '';
            $this->additional_seller_sale_terms = $auction->get->additional_seller_sale_terms ?? '';

            // Estimated Payment Assumptions
            $this->payment_down_payment_pct         = $auction->get->payment_down_payment_pct ?? '';
            $this->payment_interest_rate            = $auction->get->payment_interest_rate ?? '';
            $this->payment_loan_term                = $auction->get->payment_loan_term ?? '';
            $this->payment_annual_property_taxes    = $auction->get->payment_annual_property_taxes ?? '';
            $this->payment_monthly_insurance        = $auction->get->payment_monthly_insurance ?? '';
            $this->payment_hoa_fee_amount           = $auction->get->payment_hoa_fee_amount ?? '';
            $this->payment_hoa_fee_frequency        = $auction->get->payment_hoa_fee_frequency ?? '';
            $this->payment_pmi_rate                 = $auction->get->payment_pmi_rate ?? '';
            $rawBuydown = $auction->get->payment_show_buydown_options;
            $this->payment_show_buydown_options = ($rawBuydown === null) ? true : ($rawBuydown !== '0' && $rawBuydown !== 'false');

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
            $this->flood_zone_date = $auction->get->flood_zone_date ?? '';
            $this->waterfront = $auction->get->waterfront ?? '';
            $this->water_frontage = $auction->get->water_frontage ?? '';
            $this->waterfront_feet = $auction->get->waterfront_feet ?? '';
            $this->water_access = is_string($auction->get->water_access) ? json_decode($auction->get->water_access, true) ?? [] : (array)($auction->get->water_access ?? []);
            $this->water_view = is_string($auction->get->water_view) ? json_decode($auction->get->water_view, true) ?? [] : (array)($auction->get->water_view ?? []);
            $this->interior_features = is_string($auction->get->interior_features) ? json_decode($auction->get->interior_features, true) ?? [] : (array)($auction->get->interior_features ?? []);
            $this->other_water_access = $auction->get->other_water_access ?? '';
            $this->other_water_view = $auction->get->other_water_view ?? '';
            $this->other_interior_features = $auction->get->other_interior_features ?? '';
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
            $this->seller_disclosure_available = $auction->get->seller_disclosure_available ?? '';
            $this->survey_available = $auction->get->survey_available ?? '';
            $this->inspection_report_available = $auction->get->inspection_report_available ?? '';
            $this->hoa_condo_docs_available = $auction->get->hoa_condo_docs_available ?? '';
            $this->flood_disclosure_available = $auction->get->flood_disclosure_available ?? '';
            $this->lead_based_paint_disclosure = $auction->get->lead_based_paint_disclosure ?? '';
            $this->environmental_report_available = $auction->get->environmental_report_available ?? '';
            $rawAdditionalDocs = $auction->get->additional_documents ?? [];
            $this->additional_documents = is_string($rawAdditionalDocs) ? json_decode($rawAdditionalDocs, true) ?? [] : (array)$rawAdditionalDocs;
            $this->other_document_type = $auction->get->other_document_type ?? '';
            $rawDocRows = $auction->get->doc_rows ?? [];
            $this->doc_rows = is_string($rawDocRows) ? json_decode($rawDocRows, true) ?? [] : (array)$rawDocRows;

            // Disclosure file paths (for "View current file" links when resuming a draft)
            $this->seller_disclosure_file_path    = $auction->get->seller_disclosure_file_path ?? '';
            $this->survey_file_path               = $auction->get->survey_file_path ?? '';
            $this->inspection_report_file_path    = $auction->get->inspection_report_file_path ?? '';
            $this->hoa_condo_docs_file_path       = $auction->get->hoa_condo_docs_file_path ?? '';
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
            
            // Track resumed state for SAVE_AS_NEW_DRAFT guard
            $this->listingId = $auction->id;
            $this->isResumingDraft = true;

            // Dispatch browser event to sync select values after draft loads
            $this->dispatchBrowserEvent('draftLoaded');
        }
    }

    protected function saveAllMetadata($auction)
    {



        $auction->saveMeta('user_type', $this->user_type);
        $auction->saveMeta('workflow_type', 'offer_listing');
        $auction->saveMeta('listing_status', $this->listing_status);
        $auction->saveMeta('auction_type', $this->auction_type);
        $auction->saveMeta('working_with_agent', $this->working_with_agent);
        $auction->saveMeta('listing_date', $this->listing_date);
        $auction->saveMeta('desired_agent_hire_date', $this->desired_agent_hire_date);
        $auction->saveMeta('expiration_date', $this->expiration_date);
        $auction->saveMeta('auction_time', $this->auction_type === 'Bidding Period' ? $this->auction_time : '');

        // Location Information
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

        // Financial Details tab — Income
        $auction->saveMeta('gross_annual_income', $this->stripCommas($this->gross_annual_income));
        $auction->saveMeta('annual_operating_expenses', $this->stripCommas($this->annual_operating_expenses));
        $auction->saveMeta('rent_roll_available', $this->rent_roll_available);
        $auction->saveMeta('operating_statement_available', $this->operating_statement_available);

        // Financial Details tab — Commercial
        $auction->saveMeta('price_per_sqft', $this->stripCommas($this->price_per_sqft));
        $auction->saveMeta('existing_lease_type', $this->existing_lease_type);
        $auction->saveMeta('other_lease_type', $this->other_lease_type);
        $auction->saveMeta('lease_expiration', $this->lease_expiration);
        $auction->saveMeta('lease_assignable', $this->lease_assignable);

        // Financial Details tab — Business
        $auction->saveMeta('business_type', $this->business_type);
        $auction->saveMeta('annual_revenue', $this->stripCommas($this->annual_revenue));
        $auction->saveMeta('gross_profit', $this->stripCommas($this->gross_profit));
        $auction->saveMeta('sde_ebitda', $this->stripCommas($this->sde_ebitda));
        $auction->saveMeta('inventory_value', $this->stripCommas($this->inventory_value));
        $auction->saveMeta('ffe_value', $this->stripCommas($this->ffe_value));
        $auction->saveMeta('reason_for_sale', $this->reason_for_sale);
        $auction->saveMeta('other_reason_for_sale', $this->other_reason_for_sale);
        $auction->saveMeta('employee_count', $this->employee_count);
        $auction->saveMeta('financial_statements_available', $this->financial_statements_available);
        $auction->saveMeta('tax_returns_available', $this->tax_returns_available);
        $auction->saveMeta('nda_required', $this->nda_required);
        $auction->saveMeta('business_location_leased', $this->business_location_leased);
        $auction->saveMeta('business_lease_monthly_rent', $this->stripCommas($this->business_lease_monthly_rent));
        $auction->saveMeta('business_lease_expiration', $this->business_lease_expiration);
        $auction->saveMeta('business_lease_renewal_options', $this->business_lease_renewal_options);
        $auction->saveMeta('business_lease_assignable', $this->business_lease_assignable);
        $auction->saveMeta('business_lease_additional_terms', $this->business_lease_additional_terms);

        $assetsToSave = (!empty($this->business_assets) && is_array($this->business_assets))
            ? json_encode($this->business_assets)
            : (is_array($this->assets) ? json_encode($this->assets) : $this->assets);
        $auction->saveMeta('assets', $assetsToSave);
        $auction->saveMeta('assets_other', $this->assets_other);
        $auction->saveMeta('property_criteria', $this->property_criteria);
        $auction->saveMeta('unit_size', $this->unit_size);
        $auction->saveMeta('unit_size_other', $this->unit_size_other);
        $auction->saveMeta('preferance_details', $this->preferance_details);
        $configs = is_array($this->unit_type_configurations) ? $this->unit_type_configurations : [];
        $auction->saveMeta('unit_type_configurations', json_encode(array_values($configs)));
        $auction->saveMeta('sqft_heated_source', $this->sqft_heated_source);

        // MLS property detail fields
        $auction->saveMeta('year_built', $this->year_built);
        $auction->saveMeta('zoning', $this->zoning);
        $auction->saveMeta('roof_type', json_encode($this->roof_type));
        $auction->saveMeta('other_roof_type', $this->other_roof_type);
        $auction->saveMeta('exterior_construction', json_encode($this->exterior_construction));
        $auction->saveMeta('other_exterior_construction', $this->other_exterior_construction);
        $auction->saveMeta('foundation', json_encode($this->foundation));
        $auction->saveMeta('other_foundation', $this->other_foundation);
        $auction->saveMeta('heating_and_fuel', json_encode($this->heating_and_fuel));
        $auction->saveMeta('other_heating_and_fuel', $this->other_heating_and_fuel);
        $auction->saveMeta('air_conditioning', json_encode($this->air_conditioning));
        $auction->saveMeta('other_air_conditioning', $this->other_air_conditioning);
        $auction->saveMeta('water', json_encode($this->water));
        $auction->saveMeta('other_water', $this->other_water);
        $auction->saveMeta('sewer', json_encode($this->sewer));
        $auction->saveMeta('other_sewer', $this->other_sewer);
        $auction->saveMeta('utilities', json_encode($this->utilities));
        $auction->saveMeta('other_utilities', $this->other_utilities);
        $auction->saveMeta('road_frontage', json_encode($this->road_frontage));
        $auction->saveMeta('other_road_frontage', $this->other_road_frontage);
        $auction->saveMeta('road_surface_type', json_encode($this->road_surface_type));
        $auction->saveMeta('other_road_surface_type', $this->other_road_surface_type);
        $auction->saveMeta('electrical_service', json_encode($this->electrical_service));
        $auction->saveMeta('other_electrical_service', $this->other_electrical_service);
        $auction->saveMeta('ceiling_height', $this->ceiling_height);
        $auction->saveMeta('building_features', json_encode($this->building_features));
        $auction->saveMeta('other_building_features', $this->other_building_features);
        $auction->saveMeta('number_water_meters', $this->number_water_meters);
        $auction->saveMeta('number_electric_meters', $this->number_electric_meters);
        $auction->saveMeta('business_name', $this->business_name);
        $auction->saveMeta('year_established', $this->year_established);
        $auction->saveMeta('licenses', json_encode($this->licenses));
        $auction->saveMeta('other_licenses', $this->other_licenses);
        $auction->saveMeta('sale_includes', json_encode($this->sale_includes));
        $auction->saveMeta('other_sale_includes', $this->other_sale_includes);
        $auction->saveMeta('current_use', json_encode($this->current_use));
        $auction->saveMeta('other_current_use', $this->other_current_use);
        $auction->saveMeta('lot_dimensions', $this->lot_dimensions);
        $auction->saveMeta('front_footage', $this->front_footage);
        $auction->saveMeta('number_of_wells', $this->number_of_wells);
        $auction->saveMeta('number_of_septics', $this->number_of_septics);
        $auction->saveMeta('current_adjacent_use', json_encode($this->current_adjacent_use));
        $auction->saveMeta('other_current_adjacent_use', $this->other_current_adjacent_use);
        $auction->saveMeta('fences', json_encode($this->fences));
        $auction->saveMeta('other_fences', $this->other_fences);
        $auction->saveMeta('vegetation', json_encode($this->vegetation));
        $auction->saveMeta('other_vegetation', $this->other_vegetation);
        $auction->saveMeta('buildable', $this->buildable);
        $auction->saveMeta('easements', json_encode($this->easements));
        $auction->saveMeta('other_easements', $this->other_easements);
        $auction->saveMeta('water_available', $this->water_available);
        $auction->saveMeta('water_available_other', $this->water_available_other);
        $auction->saveMeta('sewer_available', $this->sewer_available);
        $auction->saveMeta('sewer_available_other', $this->sewer_available_other);
        $auction->saveMeta('electric_available', $this->electric_available);
        $auction->saveMeta('electric_available_other', $this->electric_available_other);
        $auction->saveMeta('gas_available', $this->gas_available);
        $auction->saveMeta('gas_available_other', $this->gas_available_other);
        $auction->saveMeta('telecom_available', $this->telecom_available);
        $auction->saveMeta('telecom_available_other', $this->telecom_available_other);

        // Sale Provisions
        $auction->saveMeta('sale_provision', $this->sale_provision);
        $auction->saveMeta('sale_provision_other', $this->sale_provision_other);
        $auction->saveMeta('sale_provision_assignment', $this->sale_provision_assignment);
        $auction->saveMeta('assignment_fee_type', $this->assignment_fee_type);
        $auction->saveMeta('assignment_fee_amount', $this->stripCommas($this->assignment_fee_amount));
        $auction->saveMeta('buyer_sell_contract', $this->buyer_sell_contract);

        // Budget & Financing
        $auction->saveMeta('maximum_budget', $this->stripCommas($this->maximum_budget));
        $auction->saveMeta('starting_price', $this->stripCommas($this->starting_price));
        $auction->saveMeta('reserve_price', $this->stripCommas($this->reserve_price));
        $auction->saveMeta('buy_now_price', $this->stripCommas($this->buy_now_price));
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
        $auction->saveMeta('seller_amortization_type', $this->seller_amortization_type);
        $auction->saveMeta('seller_amortization_other', $this->seller_amortization_other);
        $auction->saveMeta('seller_payment_frequency', $this->seller_payment_frequency);
        $auction->saveMeta('seller_payment_frequency_other', $this->seller_payment_frequency_other);
        $auction->saveMeta('seller_down_payment_amount', $this->stripCommas($this->seller_down_payment_amount));
        $auction->saveMeta('seller_late_fee_amount', $this->stripCommas($this->seller_late_fee_amount));
        $auction->saveMeta('interest_rate', $this->interest_rate);
        $auction->saveMeta('loan_duration', $this->loan_duration);
        $auction->saveMeta('prepayment_penalty', $this->prepayment_penalty);
        $auction->saveMeta('prepayment_penalty_amount', $this->stripCommas($this->prepayment_penalty_amount));
        $auction->saveMeta('balloon_payment_amount', $this->stripCommas($this->balloon_payment_amount));
        $auction->saveMeta('balloon_payment_date', $this->balloon_payment_date);
        $auction->saveMeta('assumable_terms', $this->assumable_terms);
        $auction->saveMeta('assumable_loan_type', $this->assumable_loan_type);
        $auction->saveMeta('max_assumable_rate', $this->stripCommas($this->max_assumable_rate));
        $auction->saveMeta('assumable_monthly_escrow', $this->stripCommas($this->assumable_monthly_escrow));
        $auction->saveMeta('assumable_loan_term_remaining', $this->assumable_loan_term_remaining);
        $auction->saveMeta('assumable_loan_origination_date', $this->assumable_loan_origination_date);
        $auction->saveMeta('assumable_loan_servicer', $this->assumable_loan_servicer);
        $auction->saveMeta('assumable_fee_type', $this->assumable_fee_type);
        $auction->saveMeta('assumable_fee_amount', $this->stripCommas($this->assumable_fee_amount));
        $auction->saveMeta('assumption_fee_responsibility', $this->assumption_fee_responsibility);
        $auction->saveMeta('assumable_occupancy_requirement', $this->assumable_occupancy_requirement);
        $auction->saveMeta('assumable_occupancy_other', $this->assumable_occupancy_other);
        $auction->saveMeta('max_monthly_payment', $this->stripCommas($this->max_monthly_payment));
        $auction->saveMeta('gap_payment_type', $this->gap_payment_type);
        $auction->saveMeta('gap_payment_amount', $this->stripCommas($this->gap_payment_amount));
        $auction->saveMeta('target_closing_date', $this->target_closing_date);
        $auction->saveMeta('occupant_status', $this->occupant_status);

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
        $auction->saveMeta('crypto_transfer_timing', $this->crypto_transfer_timing);
        $auction->saveMeta('crypto_transfer_timing_other', $this->crypto_transfer_timing_other);
        $auction->saveMeta('crypto_exchange_method', $this->crypto_exchange_method);
        $auction->saveMeta('crypto_custodian_wallet', $this->crypto_custodian_wallet);
        $auction->saveMeta('crypto_transaction_fees', $this->crypto_transaction_fees);

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
        $auction->saveMeta('other_services', $this->other_services);
        $auction->saveMeta('additional_details', $this->additional_details);

        // Broker Compensation
        $auction->saveMeta('commission_structure', $this->commission_structure);

        // BYO-C4: persist the buyer-broker commission-structure-type group. These
        // fields are validated on submit but were never saved on Create (only Edit
        // saved them), silently dropping broker compensation. Mirrors Edit exactly.
        $auction->saveMeta('commission_structure_type', $this->commission_structure_type);
        $auction->saveMeta('commission_structure_type_fee_flat', $this->stripCommas($this->commission_structure_type_fee_flat));
        $auction->saveMeta('commission_structure_type_fee_percentage', $this->commission_structure_type_fee_percentage);
        $auction->saveMeta('commission_structure_type_fee_other', $this->commission_structure_type_fee_other);
        $auction->saveMeta('commission_structure_type_fee_flat_combo', $this->stripCommas($this->commission_structure_type_fee_flat_combo));
        $auction->saveMeta('commission_structure_type_fee_percentage_combo', $this->commission_structure_type_fee_percentage_combo);

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
        $auction->saveMeta('unit_address', $this->unit_address);
        $auction->saveMeta('property_lat', $this->property_lat);
        $auction->saveMeta('property_lng', $this->property_lng);
        $auction->saveMeta('google_place_id', $this->google_place_id);
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

        // Sale Terms Questions
        $auction->saveMeta('initial_deposit_type', $this->initial_deposit_type);
        $auction->saveMeta('initial_deposit_requested', $this->initial_deposit_requested);
        $auction->saveMeta('initial_deposit_timeframe', $this->initial_deposit_timeframe);
        $auction->saveMeta('initial_deposit_timeframe_other', $this->initial_deposit_timeframe_other);
        $auction->saveMeta('additional_deposit_type', $this->additional_deposit_type);
        $auction->saveMeta('additional_deposit_requested', $this->additional_deposit_requested);
        $auction->saveMeta('additional_deposit_timeframe', $this->additional_deposit_timeframe);
        $auction->saveMeta('additional_deposit_timeframe_other', $this->additional_deposit_timeframe_other);
        $auction->saveMeta('escrow_agent_preference', $this->escrow_agent_preference);
        $auction->saveMeta('preferred_inspection_period', $this->preferred_inspection_period);
        $auction->saveMeta('inspection_contingency_preference', $this->inspection_contingency_preference);
        $auction->saveMeta('appraisal_contingency_preference', $this->appraisal_contingency_preference);
        $auction->saveMeta('appraisal_contingency_period', $this->appraisal_contingency_period);
        $auction->saveMeta('financing_contingency_preference', $this->financing_contingency_preference);
        $auction->saveMeta('financing_contingency_period', $this->financing_contingency_period);
        $auction->saveMeta('sale_of_buyer_property_contingency', $this->sale_of_buyer_property_contingency);
        $auction->saveMeta('sale_of_buyer_property_period', $this->sale_of_buyer_property_period);
        $auction->saveMeta('seller_contribution_credit_offered', $this->seller_contribution_credit_offered);
        $auction->saveMeta('seller_contribution_amount_details', $this->seller_contribution_amount_details);
        $auction->saveMeta('possession_preference', $this->possession_preference);
        $auction->saveMeta('possession_details', $this->possession_details);
        $auction->saveMeta('included_personal_property', $this->included_personal_property);
        $auction->saveMeta('excluded_items', $this->excluded_items);
        $auction->saveMeta('home_warranty_offered', $this->home_warranty_offered);
        $auction->saveMeta('home_warranty_amount_details', $this->home_warranty_amount_details);
        $auction->saveMeta('hoa_condo_association_terms', $this->hoa_condo_association_terms);
        $auction->saveMeta('additional_seller_sale_terms', $this->additional_seller_sale_terms);

        // Estimated Payment Assumptions
        $auction->saveMeta('payment_down_payment_pct',      $this->stripCommas($this->payment_down_payment_pct));
        $auction->saveMeta('payment_interest_rate',         $this->stripCommas($this->payment_interest_rate));
        $auction->saveMeta('payment_loan_term',             $this->stripCommas($this->payment_loan_term));
        $auction->saveMeta('payment_annual_property_taxes', $this->stripCommas($this->payment_annual_property_taxes));
        $auction->saveMeta('payment_monthly_insurance',     $this->stripCommas($this->payment_monthly_insurance));
        $auction->saveMeta('payment_hoa_fee_amount',        $this->stripCommas($this->payment_hoa_fee_amount));
        $auction->saveMeta('payment_hoa_fee_frequency',     $this->payment_hoa_fee_frequency);
        $auction->saveMeta('payment_pmi_rate',              $this->stripCommas($this->payment_pmi_rate));
        $auction->saveMeta('payment_show_buydown_options',  $this->payment_show_buydown_options ? '1' : '0');

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
        $auction->saveMeta('flood_zone_date', $this->flood_zone_date);
        $auction->saveMeta('waterfront', $this->waterfront);
        $auction->saveMeta('water_frontage', $this->water_frontage);
        $auction->saveMeta('waterfront_feet', $this->waterfront_feet);
        $auction->saveMeta('water_access', json_encode($this->water_access));
        $auction->saveMeta('water_view', json_encode($this->water_view));
        $auction->saveMeta('interior_features', json_encode($this->interior_features));
        $auction->saveMeta('other_water_access', $this->other_water_access);
        $auction->saveMeta('other_water_view', $this->other_water_view);
        $auction->saveMeta('other_interior_features', $this->other_interior_features);
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
        $auction->saveMeta('seller_disclosure_available', $this->seller_disclosure_available);
        $auction->saveMeta('survey_available', $this->survey_available);
        $auction->saveMeta('inspection_report_available', $this->inspection_report_available);
        $auction->saveMeta('hoa_condo_docs_available', $this->hoa_condo_docs_available);
        $auction->saveMeta('flood_disclosure_available', $this->flood_disclosure_available);
        $auction->saveMeta('lead_based_paint_disclosure', $this->lead_based_paint_disclosure);
        $auction->saveMeta('environmental_report_available', $this->environmental_report_available);
        $auction->saveMeta('additional_documents', json_encode($this->additional_documents));
        $auction->saveMeta('other_document_type', $this->other_document_type);
        $auction->saveMeta('doc_rows', json_encode($this->doc_rows));

        // Disclosure file uploads
        $disclosureUploads = [
            ['file' => 'seller_disclosure_file',    'path' => 'seller_disclosure_file_path',    'dir' => 'seller-disclosure'],
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
                $dir      = 'seller-disclosures/' . $auction->id . '/' . $item['dir'];
                Storage::disk('public')->makeDirectory($dir);
                $fileVal->storeAs($dir, $fileName, 'public');
                $storedPath           = $dir . '/' . $fileName;
                $this->{$pathProp}    = $storedPath;
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
        $auction->saveMeta('current_status', $this->current_status);
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

        $this->ensureLinkedOfferAuction($auction);

        $this->saveSnapshotMeta($auction);
    }

    private function ensureLinkedOfferAuction(SellerAgentAuctionModel $auction): void
    {
        $linkedId = $auction->info('linked_offer_auction_id');
        if ($linkedId && OfferAuction::where('id', (int) $linkedId)->exists()) {
            return;
        }
        $offerAuction = OfferAuction::create(['user_id' => $auction->user_id]);
        $auction->saveMeta('linked_offer_auction_id', $offerAuction->id);
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
            $this->validate(
            ['newPropertyPhotos.*' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:51200'],
            ['newPropertyPhotos.*.max' => 'Each photo may not be greater than 50 MB.']
        );
            $incoming = is_array($this->newPropertyPhotos) ? count($this->newPropertyPhotos) : 0;
            if (count($this->propertyPhotos) + $incoming > 50) {
                $this->addError('newPropertyPhotos', 'You may upload up to 50 property photos. You currently have ' . count($this->propertyPhotos) . ' photo(s) uploaded. Please select fewer files.');
                $this->newPropertyPhotos = [];
                return;
            }
            $this->processPendingPhotoUploads();
            if ($this->listingId) {
                $auction = SellerAgentAuctionModel::findOrFail($this->listingId);
                $auction->saveMeta('property_photos', $this->propertyPhotos);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->newPropertyPhotos = [];
            $bag = new \Illuminate\Support\MessageBag;
            foreach ($e->errors() as $field => $msgs) {
                foreach ($msgs as $msg) {
                    $bag->add($field, preg_replace('/\d+ kilobytes/', '50 MB', $msg));
                }
            }
            $this->setErrorBag($bag);
        } catch (\Throwable $e) {
            $this->newPropertyPhotos = [];
            $this->addError('newPropertyPhotos', 'Photo upload failed. Please try again.');
        }
    }

    public function updatedListingDocuments()
    {
        $this->validate(
            ['listingDocuments' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:51200'],
            ['listingDocuments.max' => 'The file may not be greater than 50 MB.']
        );
    }

    public function updatedSellerDisclosureFile()
    {
        $this->validate(
            ['seller_disclosure_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:51200'],
            ['seller_disclosure_file.max' => 'The file may not be greater than 50 MB.']
        );
    }

    public function updatedSurveyFile()
    {
        $this->validate(
            ['survey_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:51200'],
            ['survey_file.max' => 'The file may not be greater than 50 MB.']
        );
    }

    public function updatedInspectionReportFile()
    {
        $this->validate(
            ['inspection_report_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:51200'],
            ['inspection_report_file.max' => 'The file may not be greater than 50 MB.']
        );
    }

    public function updatedHoaCondoDocsFile()
    {
        $this->validate(
            ['hoa_condo_docs_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:51200'],
            ['hoa_condo_docs_file.max' => 'The file may not be greater than 50 MB.']
        );
    }

    public function updatedFloodDisclosureFile()
    {
        $this->validate(
            ['flood_disclosure_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:51200'],
            ['flood_disclosure_file.max' => 'The file may not be greater than 50 MB.']
        );
    }

    public function updatedLeadBasedPaintFile()
    {
        $this->validate(
            ['lead_based_paint_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:51200'],
            ['lead_based_paint_file.max' => 'The file may not be greater than 50 MB.']
        );
    }

    public function updatedEnvironmentalReportFile()
    {
        $this->validate(
            ['environmental_report_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:51200'],
            ['environmental_report_file.max' => 'The file may not be greater than 50 MB.']
        );
    }

    public function updatedDocFileUpload()
    {
        $this->validate(
            ['docFileUpload' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:51200'],
            ['docFileUpload.max' => 'The file may not be greater than 50 MB.']
        );

        if ($this->docFileUploadIndex === null || !$this->docFileUpload) {
            return;
        }

        $index    = (int) $this->docFileUploadIndex;
        $ext      = $this->docFileUpload->getClientOriginalExtension();
        $uuid     = (string) Str::uuid();
        $fileName = $uuid . '.' . $ext;
        $dir      = 'seller-doc-uploads/' . ($this->listingId ?? 'draft');

        Storage::disk('public')->makeDirectory($dir);
        $this->docFileUpload->storeAs($dir, $fileName, 'public');
        $storedPath = $dir . '/' . $fileName;

        $rows = $this->doc_rows;
        if (isset($rows[$index])) {
            $rows[$index]['file_path'] = $storedPath;
        }
        $this->doc_rows          = $rows;
        $this->docFileUpload     = null;
        $this->docFileUploadIndex = null;

        $this->emit('docFileStored', $index, $storedPath);
    }

    public function removeDocRowFile($index)
    {
        $index = (int) $index;
        $rows  = $this->doc_rows;
        if (isset($rows[$index])) {
            $rows[$index]['file_path'] = '';
        }
        $this->doc_rows = $rows;
        $this->emit('docRowFileRemoved', $index);
    }

    public function deletePropertyPhoto($index)
    {
        if (isset($this->propertyPhotos[$index])) {
            $filename = $this->propertyPhotos[$index];
            Storage::disk('public')->delete('auction/images/' . $filename);
            array_splice($this->propertyPhotos, $index, 1);
            if ($this->listingId) {
                $auction = SellerAgentAuctionModel::findOrFail($this->listingId);
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
            $auction = SellerAgentAuctionModel::findOrFail($this->listingId);
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
            $auction = SellerAgentAuctionModel::findOrFail($this->listingId);
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
            $auction = SellerAgentAuctionModel::findOrFail($this->listingId);
            $auction->saveMeta('property_photos', $this->propertyPhotos);
        }
    }

    public function deleteListingDocument()
    {
        if ($this->listingDocuments && is_string($this->listingDocuments)) {
            Storage::disk('public')->delete('auction/documents/' . $this->listingDocuments);
            if ($this->listingId) {
                $auction = SellerAgentAuctionModel::findOrFail($this->listingId);
                $auction->deleteMeta('listing_documents');
            }
        }
        $this->listingDocuments = null;
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
        if ($this->seller_leasing_fee_type !== 'Percentage of the Rent Due Each Rental Period') {
            $this->seller_leasing_gross_rental = '';
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
            $auction->is_approved = true;
            $auction->is_sold = 0;
            $auction->is_paid = 0;
            
            $auction->save();

            // Phase 6 — persist referral attribution on brand-new listing rows.
            \App\Services\ReferralLinkService::persistListingReferral($auction);

            $this->listingId = $auction->id;

            $this->saveAllMetadata($auction);

            if ($this->address) {
                try {
                    \App\Jobs\ComputeLocationDna::dispatch('seller_agent', $this->listingId);
                } catch (\Throwable $dnaEx) {
                    \Log::warning('[SELLER STORE] ComputeLocationDna dispatch skipped', [
                        'listing_id' => $this->listingId,
                        'reason'     => $dnaEx->getMessage(),
                    ]);
                }
            }

            app(\App\Services\AskAi\AskAiKnowledgeSnapshotBuilderService::class)->buildSilently('seller', $this->listingId);

            \Log::info('[SELLER LISTING SUBMITTED]', [
                'record_id' => $auction->id,
                'listing_id' => $auction->listing_id ?? 'N/A',
                'user_id' => $auction->user_id,
                'is_draft' => $auction->is_draft,
                'is_approved' => $auction->is_approved ?? 'N/A',
                'is_sold' => $auction->is_sold ?? 'N/A',
            ]);

            app(WizardEventService::class)->record(
                (string) $this->user_type,
                $this->listingId ? (int) $this->listingId : null,
                auth()->id() ? (int) auth()->id() : null,
                'submit',
                'tab_' . $this->activeTab,
                'create',
                session()->getId()
            );
            session()->flash('success', 'Listing submitted successfully.');

            $url = route('offer.listing.seller.view', ['id' => $auction->id]);

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
            // WF-3: only the draft's owner may delete it (and its meta).
            if (! SellerAgentAuctionModel::where('id', $draftId)->where('user_id', Auth::id())->exists()) {
                session()->flash('error', 'You are not authorized to delete this draft.');
                return;
            }
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
