<?php

namespace App\Http\Livewire\OfferListing\Buyer;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\BuyerAgentAuction as HireBuyerAgentAuction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Services\WizardEventService;
use App\Http\Livewire\Concerns\ResolvesOwnedAuction;
use App\Http\Livewire\OfferListing\Concerns\HasImportantPlaces;

class BuyerOfferListingEdit extends Component
{
    use WithFileUploads;
    use ResolvesOwnedAuction;
    use HasImportantPlaces;

    protected $listeners = [
        'setActiveTab' => 'setActiveTab',
    ];

    // Livewire properties for form fields
    public $hasDrafts = false;
    public $auctionId; // To store the auction ID for editing

    public $listingId = null; // To track existing listings
    public $isDraft = false; // To track draft status
    public bool $isListingDraft = false; // Source of truth for button mode (read from DB in mount)
    public $isLoadingData = false; // Flag to prevent reset during draft/edit load
    public $existingLocationDna = [];
    public $location_dna_preferences_json = '';
    public $listing_status = 'Active'; // 'Active', 'Pending', 'Expired', or 'Draft'
    public $meeting_Preference = ''; // Meeting preference field

    public $user_type = 'buyer'; // Default to tenant or whatever makes sense
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
    public $condition_prop_buyer = [];
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
    public $assignment_fee_type = '$';
    public $assignment_fee_amount = '';
    public $buyer_sell_contract = '';

    // Properties
    public $maximum_budget = '';
    public $offered_financing = [];
    public $previousOfferedFinancing = []; // Track previous financing types for smart reset
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
    public $balloon_payment = '';
    public $balloon_payment_amount = '';
    public $balloon_payment_date = '';
    public $assumable_interest = '';
    public $assumable_max_interest_rate = '';
    public $assumable_max_monthly_payment = '';
    public $assumable_bridge_gap_cash = '';
    public $assumption_fee_responsibility = ''; // A6.31-A6.34: who pays the assumption fee (Buyer/Seller/Split)
    
    // Seller Financing Additional Properties
    public $seller_amortization_type = '';
    public $seller_amortization_other = '';
    public $seller_payment_frequency = '';
    public $seller_payment_frequency_other = '';
    public $seller_late_fee_amount = '';

    // Exchange/Trade Properties
    public $exchange_item = [];
    public $other_exchange_item = '';
    public $exchange_item_value = '';
    public $exchange_item_condition = '';
    public $additional_cash = '';
    public $value_determination = '';
    public $exchange_transfer_method = '';
    public $exchange_liens = '';
    public $exchange_liens_details = '';
    public $exchange_inspection_rights = '';

    // Lease Option Properties
    public $interested_lease_option = '';
    public $interested_lease_option_agreement = '';
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

    // Lease Purchase Properties
    public $lease_purchase_price = '';
    public $lease_purchase_terms = '';
    public $lease_purchase_duration = '';
    public $lease_purchase_payment = '';
    public $lease_purchase_conditions = '';
    public $lease_purchase_option_fee = '';
    public $lease_purchase_option_fee_amount = '';
    public $lease_purchase_maintenance = '';
    public $lease_purchase_extension_terms = '';
    public $lease_purchase_rent_credit = '';
    public $lease_purchase_rent_credit_amount = '';
    public $lease_purchase_deposit = '';

    // Cryptocurrency Properties
    public $cryptocurrency_type = '';
    public $crypto_percentage = '';
    public $cash_percentage_crypto = '';
    public $crypto_transfer_timing = '';
    public $crypto_transfer_timing_other = '';
    public $crypto_exchange_method = '';
    public $crypto_custodian_wallet = '';
    public $crypto_transaction_fees = '';

    // NFT Properties
    public $nft_description = '';
    public $nft_percentage = '';
    public $cash_percentage_nft = '';
    public $nft_valuation_method = '';
    public $nft_transfer_method = '';
    public $nft_gas_fees = '';

    public $garage_needed = '';
    public $other_garage_needed = '';
    public $garage_parking_spaces = '';
    public $garage_parking_spaces_option = [];
    public $other_parking_space_wrapper = '';
    public $pool_needed = '';
    public $pool_type = [];
    public $view_preference = [];
    public $other_preferences = '';
    public $real_estate_purchase = '';
    public $number_of_unit = '';
    public $number_of_unit_other = '';
    public $number_of_unit_type = [];
    public $number_of_unit_type_other = '';
    public $minimum_annual_net_income = '';
    public $minimum_cap_rate = '';
    public $assets = [];
    public $assets_other = '';
    public $property_criteria = '';
    public $unit_size = '';
    public $unit_size_other = '';
    public $preferance_details = '';

    // Property DNA Phase C — Buyer Tier 1 EAV fields
    public $purchase_purpose = '';
    public $purchase_purpose_other = '';
    public $commute_destination_zip = '';
    public $max_commute_minutes = '';
    public $commute_mode = '';
    public $hoa_acceptance = '';
    public $hoa_max_monthly_fee = '';
    public $flood_zone_tolerance = [];
    public $flood_zone_tolerance_other = '';

    public $leasing_55_plus = '';
    public $non_negotiable_amenities = [];
    public $other_non_negotiable_amenities = '';
    public $budget = '';

    // Missing properties for Buyer Agent
    public $target_closing_date = '';
    public $service_animal = '';
    public $emotional_support_animal = '';
    public $occupant_types = '';

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

    // Broker compensation
    public $commission_structure = '';
    public $lease_type = '';
    public $lease_type_other = '';
    public $lease_fee_type = '';
    public $lease_fee_flat = '';
    public $lease_fee_percentage = '';
    public $lease_fee_months = '';
    public $lease_fee_percentage_monthly_rent = '';
    public $lease_fee_flat_combo = '';
    public $lease_fee_percentage_combo = '';
    public $lease_fee_other = '';
    public $lease_value = '';
    public $lease_fee_flat_combo_net = '';
    public $lease_fee_percentage_combo_net = '';
    public $lease_fee_percentage_monthly_number = '';
    public $lease_fee_percentage_net = '';
    public $lease_option_consideration = '';
    public $purchase_type = '';
    public $purchase_value = '';
    public $purchase_pice_commercial = '';
    public $purchase_fee_flat_exercised = '';
    public $additional_details_broker = '';
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
    public $lease_option_fee_flat_combo = '';
    public $lease_option_fee_percentage_combo = '';
    public $protection_period = '';
    public $early_termination_fee_option = '';
    public $early_termination_fee_amount = '';
    public $retainer_fee_option = '';
    public $retainer_fee_amount = '';
    public $retainer_fee_application = '';
    public $agency_agreement_timeframe = '';
    public $agency_agreement_custom = '';
    public $brokerage_relationship = '';

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
    public $embedUrl;



    // location and meeting details
    public $person_meeting;
    public $meeting_details_first_name = '';
    public $meeting_details_last_name = '';
    public $meeting_details_phone = '';
    public $meeting_details_email = '';

    public $address = '';
    public $unit_number = '';
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

    // Purchase Terms Questions
    public $earnest_money_amount = '';
    public $earnest_money_type = '$';
    public $earnest_money_timing = '';
    public $due_diligence_yn = '';
    public $inspection_period_days = '';
    public $inspection_period_other = '';
    public $inspection_contingency_buyer = '';
    // Phase 5/6 QA Follow-up (Buyer Inspection Cleanup): see BuyerOfferListing for rationale.
    public $inspection_contingency_period = '';
    public $appraisal_contingency_buyer = '';
    public $appraisal_contingency_days = '';
    public $financing_contingency_buyer = '';
    public $financing_contingency_days_buyer = '';
    public $financing_contingency_period = '';
    public $seller_contribution = '';
    public $seller_contribution_details = '';
    public $possession_preference = '';
    public $possession_preference_other = '';
    public $possession_details = '';
    public $home_warranty_requested = '';
    public $home_warranty_details = '';
    public $as_is_purchase = '';
    public $property_inclusions = '';
    public $property_exclusions = '';
    public $closing_cost_responsibility = '';
    public $additional_purchase_terms = '';
    public $home_sale_contingency = '';
    public $home_sale_contingency_period = '';
    public $home_sale_contingency_address = '';
    public $home_sale_contingency_date = '';
    public $home_sale_contingency_under_contract = '';
    public $home_sale_contingency_details = '';

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
    public $counties = [];
    public $newCounty = '';
    public $countySuggestions = [];
    public $stateSuggestions = [];

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

    // ── Properties ported from BuyerOfferListing (needed by shared blade tabs) ──
    public $condition_prop_buyer_json = '[]';
    public $number_of_unit_type_json = '[]';
    public $property_items_json = '[]';
    public $business_type_selected_json = '[]';
    public $isEditMode = true;
    public $business_type_selected = [];
    public $other_business_type = '';
    public $property_city = '';
    public $property_state = '';
    public $property_zip = '';
    public $property_county = '';
    public $propertyCitySuggestions = [];
    public $highlightedPropertyCityIndex = -1;
    // ── End ported properties ────────────────────────────────────────────────

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

    public function setEarnestMoneyType($type)
    {
        $this->earnest_money_type = $type;
        $this->earnest_money_amount = '';
    }

    public function setType(string $which, string $type): void
    {
        if ($which === 'lease') {
            $this->lease_type = $type ?: 'percent';
            $this->lease_value = '';
        } elseif ($which === 'purchase') {
            $this->purchase_type = $type ?: 'percent';
            $this->purchase_value = '';
        }

        if ($which === 'down_payment_type') {
            $this->down_payment_type = $type;
            $this->down_payment_amount = '';
        }

        if ($which === 'seller_financing_type') {
            $this->seller_financing_type = $type;
            $this->seller_financing_amount = '';
        }

        if ($which === 'earnest_money_type') {
            $this->earnest_money_type = $type;
            $this->earnest_money_amount = '';
        }
    }

    public function updatedBusinessTypeSelectedJson($value)
    {
        $this->business_type_selected = json_decode($value, true) ?? [];
    }

    public function updatedAssumableInterest($value): void
    {
        if ($value !== 'Yes') {
            $this->assumable_max_interest_rate  = '';
            $this->assumable_max_monthly_payment = '';
            $this->assumable_bridge_gap_cash     = '';
            $this->assumption_fee_responsibility = '';
        }
    }

    public function updatedOfferedFinancing($value)
    {
        // Get current financing types as array
        $currentTypes = is_array($this->offered_financing) ? $this->offered_financing : [];
        $previousTypes = $this->previousOfferedFinancing ?? [];
        
        // Skip reset during draft/edit load to preserve loaded data
        // Flag is cleared at the end of loadAuctionData() - do NOT clear it here
        if ($this->isLoadingData) {
            // Still update the previous snapshot before returning so subsequent comparisons work
            $this->previousOfferedFinancing = $currentTypes;
            return;
        }
        
        // Track the new value for next comparison
        $this->previousOfferedFinancing = $currentTypes;
        
        // If values are the same (re-sync), don't reset anything
        $currentSorted = $currentTypes;
        $previousSorted = $previousTypes;
        sort($currentSorted);
        sort($previousSorted);
        if ($currentSorted === $previousSorted) {
            return; // No actual change, skip reset
        }
        
        // Find which financing types were REMOVED (no longer selected)
        $removedTypes = array_diff($previousTypes, $currentTypes);
        
        // Only reset fields for financing types that were removed
        $fieldsToReset = [];
        
        // Map financing types to their dependent fields (using exact property names)
        $financingFieldMap = [
            'Other' => ['other_financing'],
            'Cash' => ['cash_budget', 'pre_approved', 'pre_approval_amount'],
            'Seller Financing' => [
                'purchase_price', 'down_payment_amount', 'down_payment_type',
                'seller_financing_amount', 'seller_financing_type',
                'interest_rate', 'loan_duration', 'prepayment_penalty', 
                'prepayment_penalty_amount', 'balloon_payment', 'balloon_payment_amount', 'balloon_payment_date',
                'seller_amortization_type', 'seller_amortization_other',
                'seller_payment_frequency', 'seller_payment_frequency_other',
                'seller_late_fee_amount'
            ],
            'Assumable' => ['assumable_interest', 'assumable_max_interest_rate', 'assumable_max_monthly_payment', 'assumable_bridge_gap_cash', 'assumption_fee_responsibility'],
            'Exchange/Trade' => [
                'exchange_item', 'other_exchange_item', 'exchange_item_value', 
                'exchange_item_condition', 'additional_cash', 'value_determination',
                'exchange_transfer_method', 'exchange_liens', 'exchange_liens_details', 
                'exchange_inspection_rights'
            ],
            'Cryptocurrency' => [
                'cryptocurrency_type', 'crypto_percentage', 'cash_percentage_crypto',
                'crypto_transfer_timing', 'crypto_transfer_timing_other',
                'crypto_exchange_method', 'crypto_custodian_wallet', 'crypto_transaction_fees'
            ],
            'NFT' => [
                'nft_description', 'nft_percentage', 'cash_percentage_nft',
                'nft_valuation_method', 'nft_transfer_method', 'nft_gas_fees'
            ],
            'Lease Option' => [
                'lease_option_price', 'lease_option_terms', 'lease_option_duration',
                'lease_option_payment', 'lease_option_conditions', 'lease_option_consideration',
                'has_option_fee', 'option_fee_amount',
                'lease_option_fee_credit', 'lease_option_fee_credit_percentage',
                'lease_option_maintenance', 'lease_option_extension_terms',
                'lease_option_fee_type', 'lease_option_fee_flat', 'lease_option_fee_percentage',
                'lease_option_fee_other', 'lease_option_fee_flat_combo', 'lease_option_fee_percentage_combo'
            ],
            'Lease Purchase' => [
                'lease_purchase_price', 'lease_purchase_terms', 'lease_purchase_duration',
                'lease_purchase_payment', 'lease_purchase_conditions', 'lease_purchase_option_fee',
                'lease_purchase_option_fee_amount', 'lease_purchase_maintenance',
                'lease_purchase_extension_terms', 'lease_purchase_rent_credit',
                'lease_purchase_rent_credit_amount', 'lease_purchase_deposit'
            ],
        ];
        
        foreach ($removedTypes as $type) {
            if (isset($financingFieldMap[$type])) {
                $fieldsToReset = array_merge($fieldsToReset, $financingFieldMap[$type]);
            }
        }
        
        // Reset only the fields for removed financing types
        if (!empty($fieldsToReset)) {
            $this->reset(array_unique($fieldsToReset));
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
        if ($this->isLoadingData) return;
        // Reset all dependent fields when main selection changes
        $this->reset([
            'sale_provision_other',
            'sale_provision_assignment',
            'assignment_fee_amount',
            'buyer_sell_contract'
        ]);
    }

    public function updatedSaleProvisionAssignment()
    {
        if ($this->isLoadingData) return;
        $this->reset(['assignment_fee_amount', 'buyer_sell_contract']);
    }

    public function updatedBuyerSellContract()
    {
        if ($this->isLoadingData) return;
        $this->reset(['assignment_fee_amount']);
    }

    public function updatedPurchaseFeeType()
    {
        if ($this->isLoadingData) return;
        $this->reset([
            'purchase_fee_flat',
            'purchase_fee_percentage',
            'purchase_fee_percentage_combo',
            'purchase_fee_flat_combo',
            'purchase_fee_other'
        ]);
    }

    public function updatedLeaseFeeType()
    {
        if ($this->isLoadingData) return;
        $this->reset([
            'lease_fee_flat',
            'lease_fee_percentage',
            'lease_fee_months',
            'lease_fee_percentage_monthly_rent',
            'lease_fee_flat_combo',
            'lease_fee_percentage_combo',
            'lease_fee_other'
        ]);
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
        return HireBuyerAgentAuction::where('user_id', Auth::id())
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
        $this->dispatchBrowserEvent('buyer-auction-type-changed', ['type' => $value]);
        
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
        // Cities are optional - no validation required
    }

    public function removeCity($index)
    {
        unset($this->cities[$index]);
        $this->cities = array_values($this->cities);
        // Cities are optional - no validation required
    }

    public function addCounty()
    {
        $this->validate(['newCounty' => 'required|string|max:255']);
        $county = trim($this->newCounty);

        if (!empty($county) && !in_array($county, $this->counties)) {
            $this->counties[] = $county;
            $this->newCounty = '';
        }
        $this->dispatchBrowserEvent('buyer-counties-updated', ['count' => count($this->counties)]);
    }

    public function removeCounty($index)
    {
        unset($this->counties[$index]);
        $this->counties = array_values($this->counties);
        $this->dispatchBrowserEvent('buyer-counties-updated', ['count' => count($this->counties)]);
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
    public function updatedAddress($value)
    {
        if (strlen($value) > 1) {
            $this->addressSuggestions = $this->getPlaceSuggestions($value, 'address');
        } else {
            $this->addressSuggestions = [];
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
            return $results;
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
            return $results;
        } elseif ($type === 'address') {
            // Phase 0 item 1: no credential -> no suggestions, and no outbound attempt.
            if (\App\Support\Google\GoogleCredential::absent()) {
                return [];
            }

            // Phase 0 / S1b: container-resolved so the call is observable and mockable.
            // No try/catch here; that is pre-existing behaviour, preserved as found.
            $client = app(\GuzzleHttp\ClientInterface::class);
            $response = $client->request('GET', 'https://maps.googleapis.com/maps/api/place/autocomplete/json', [
                'query' => [
                    'input' => $input,
                    'components' => 'country:us',
                    'types' => 'address',
                    'key' => config('services.google.places_key', ''),
                ]
            ]);
            $predictions = json_decode($response->getBody(), true)['predictions'] ?? [];
            return array_map(fn($p) => $p['description'], $predictions);
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
            foreach ($cities as $city) {
                $stateAbbrev = $city->state ? $city->state->abbreviation : '';
                $results[] = $city->name . ', ' . $stateAbbrev;
            }
            return $results;
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

    public function updatedPropertyCity($value)
    {
        if (strlen($value) > 2) {
            $this->propertyCitySuggestions = $this->getPlaceSuggestions($value, 'city');
        } else {
            $this->propertyCitySuggestions = [];
        }
    }

    public function selectPropertyCitySuggestion($suggestion = null)
    {
        $suggestion = $suggestion ?? ($this->highlightedPropertyCityIndex >= 0 ? ($this->propertyCitySuggestions[$this->highlightedPropertyCityIndex] ?? null) : null) ?? $this->property_city;
        $this->property_city = $suggestion;
        $this->propertyCitySuggestions = [];
        $this->highlightedPropertyCityIndex = -1;
        $this->autoPopulateFromPropertyCity($suggestion);
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

            if (empty($this->counties)) {
                $zipCode = \App\Models\UsZipCode::where(function ($q) use ($cityName, $normalizedCityName) {
                    $q->where('city', 'ILIKE', $cityName)
                      ->orWhere('city', 'ILIKE', $normalizedCityName)
                      ->orWhereRaw("REPLACE(city, '.', '') ILIKE ?", [$normalizedCityName]);
                })
                ->where('state_abbrev', strtoupper($stateAbbr))
                ->first();
                if ($zipCode && !empty($zipCode->county)) {
                    $countyString = $zipCode->county . ', ' . strtoupper($stateAbbr);
                    if (!$this->countyExistsIgnoreCase($countyString)) {
                        $this->counties[] = $countyString;
                    }
                }
            }
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
        if (preg_match('/,\s*([A-Z]{2})(?:\s|$|,)/', $locationString, $matches)) {
            return $matches[1];
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
    public function selectAddressSuggestion($suggestion)
    {
        $this->address = $suggestion;
        $this->addressSuggestions = [];
        $this->highlightedAddressIndex = -1;
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
            'edit',
            session()->getId()
        );
    }

    // Navigate to next tab
    public function nextTab()
    {
        $maxTabs = 6;
        
        if ($this->activeTab < $maxTabs) {
            $this->activeTab++;
        }
    }

    // Navigate to previous tab
    public function prevTab()
    {
        if ($this->activeTab > 0) {
            $this->activeTab--;
        }
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

   
    /**
     * Re-verifies ownership on every subsequent Livewire action request so no
     * write action is reachable for an unauthorized user. Buyer listings are
     * owner-only (no assigned-agent allowance). See ResolvesOwnedAuction.
     */
    public function hydrate()
    {
        $this->assertCanManageAuction(
            HireBuyerAgentAuction::class,
            $this->auctionId ?: $this->listingId,
            null
        );
    }

    public function mount($auctionId = null)
    {

        if ($auctionId) {
            $this->auctionId = $auctionId;
            $this->listingId = $auctionId;
            $this->assertCanManageAuction(HireBuyerAgentAuction::class, $auctionId, null);
            $this->loadAuctionData($auctionId); // Load auction data if auctionId is provided
            $this->isListingDraft = (bool) $this->isDraft;
        }

        // Emit initial state for frontend validation
        $this->dispatchBrowserEvent('buyer-state-init', [
            'countiesCount' => count($this->counties ?? []),
            'auctionType' => $this->auction_type ?? ''
        ]);
    }
    public function render()
    {

        return view('livewire.offer-listing.buyer.offer-buyer-listing-edit')->extends('layouts.main')->section('content'); // Define the section
    }
    private function decodeJsonArray($value): array
    {
        if (is_array($value)) return $value;
        if (!is_string($value) || trim($value) === '') return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    protected function buildDraftPayload(): array
    {
        $isAgent = auth()->user() && auth()->user()->user_type === 'agent';

        $fromJsonCpb = $this->decodeJsonArray($this->condition_prop_buyer_json);
        $condPropBuyer = !empty($fromJsonCpb) ? $fromJsonCpb : (is_array($this->condition_prop_buyer) ? $this->condition_prop_buyer : []);

        $fromJsonPi = $this->decodeJsonArray($this->property_items_json);
        $propertyItems = !empty($fromJsonPi) ? $fromJsonPi : (is_array($this->property_items) ? $this->property_items : []);

        $fromJsonNut = $this->decodeJsonArray($this->number_of_unit_type_json);
        $numberOfUnitType = !empty($fromJsonNut) ? $fromJsonNut : (is_array($this->number_of_unit_type) ? $this->number_of_unit_type : []);

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
            'property_type'                   => $this->property_type,
            'property_items'                  => json_encode($propertyItems),
            'condition_prop_buyer'            => json_encode($condPropBuyer),
            'leasing_space'                   => $this->leasing_space,
            'other_property_items'            => $this->other_property_items,
            'condition_prop'                  => $this->condition_prop,
            'other_property_condition'        => $this->other_property_condition,
            'bathrooms'                       => $this->bathrooms,
            'other_bathrooms'                 => $this->other_bathrooms,
            'bedrooms'                        => $this->bedrooms,
            'other_bedrooms'                  => $this->other_bedrooms,
            'minimum_heated_square'           => $this->stripCommas($this->minimum_heated_square),
            'minimum_leaseable'               => $this->stripCommas($this->minimum_leaseable),
            'min_acreage'                     => $this->stripCommas($this->min_acreage),
            'total_acreage'                   => $this->total_acreage,
            'minimum_cap_rate'                => $this->stripCommas($this->minimum_cap_rate),
            'assets'                          => json_encode($this->assets ?? []),
            'assets_other'                    => $this->assets_other,
            'property_criteria'               => $this->property_criteria,
            'unit_size'                       => $this->unit_size,
            'unit_size_other'                 => $this->unit_size_other,
            'preferance_details'              => $this->preferance_details,
            'sale_provision'                  => json_encode($this->sale_provision ?? []),
            'sale_provision_other'            => $this->sale_provision_other,
            'sale_provision_assignment'       => $this->sale_provision_assignment,
            'assignment_fee_type'             => $this->assignment_fee_type,
            'assignment_fee_amount'           => $this->stripCommas($this->assignment_fee_amount),
            'buyer_sell_contract'             => $this->buyer_sell_contract,
            'maximum_budget'                  => $this->stripCommas($this->maximum_budget),
            'offered_financing'               => json_encode($this->offered_financing),
            'other_financing'                 => $this->other_financing,
            'cash_budget'                     => $this->stripCommas($this->cash_budget),
            'pre_approved'                    => $this->pre_approved,
            'pre_approval_amount'             => $this->stripCommas($this->pre_approval_amount),
            'purchase_price'                  => $this->stripCommas($this->purchase_price),
            'down_payment_type'               => $this->down_payment_type,
            'down_payment_amount'             => $this->stripCommas($this->down_payment_amount),
            'seller_financing_type'           => $this->seller_financing_type,
            'seller_financing_amount'         => $this->stripCommas($this->seller_financing_amount),
            'interest_rate'                   => $this->stripCommas($this->interest_rate),
            'loan_duration'                   => $this->loan_duration,
            'prepayment_penalty'              => $this->prepayment_penalty,
            'prepayment_penalty_amount'       => $this->stripCommas($this->prepayment_penalty_amount),
            'balloon_payment'                 => $this->balloon_payment,
            'balloon_payment_amount'          => $this->stripCommas($this->balloon_payment_amount),
            'balloon_payment_date'            => $this->balloon_payment_date,
            'assumable_interest'              => $this->assumable_interest,
            'assumable_max_interest_rate'     => $this->stripCommas($this->assumable_max_interest_rate),
            'assumable_max_monthly_payment'   => $this->stripCommas($this->assumable_max_monthly_payment),
            'assumable_bridge_gap_cash'       => $this->stripCommas($this->assumable_bridge_gap_cash),
            'assumption_fee_responsibility'   => $this->assumption_fee_responsibility,
            'seller_amortization_type'        => $this->seller_amortization_type,
            'seller_amortization_other'       => $this->seller_amortization_other,
            'seller_payment_frequency'        => $this->seller_payment_frequency,
            'seller_payment_frequency_other'  => $this->seller_payment_frequency_other,
            'seller_late_fee_amount'          => $this->stripCommas($this->seller_late_fee_amount),
            'exchange_item'                   => $this->exchange_item,
            'other_exchange_item'             => $this->other_exchange_item,
            'exchange_item_value'             => $this->stripCommas($this->exchange_item_value),
            'exchange_item_condition'         => $this->exchange_item_condition,
            'additional_cash'                 => $this->stripCommas($this->additional_cash),
            'value_determination'             => $this->value_determination,
            'exchange_transfer_method'        => $this->exchange_transfer_method,
            'exchange_liens'                  => $this->exchange_liens,
            'exchange_liens_details'          => $this->exchange_liens_details,
            'exchange_inspection_rights'      => $this->exchange_inspection_rights,
            'interested_lease_option'         => $this->interested_lease_option,
            'interested_lease_option_agreement' => $this->interested_lease_option_agreement,
            'lease_option_price'              => $this->stripCommas($this->lease_option_price),
            'lease_option_terms'              => $this->lease_option_terms,
            'lease_option_duration'           => $this->lease_option_duration,
            'lease_option_payment'            => $this->stripCommas($this->lease_option_payment),
            'lease_option_conditions'         => $this->lease_option_conditions,
            'has_option_fee'                  => $this->has_option_fee,
            'option_fee_amount'               => $this->stripCommas($this->option_fee_amount),
            'lease_option_fee_credit'         => $this->lease_option_fee_credit,
            'lease_option_fee_credit_percentage' => $this->stripCommas($this->lease_option_fee_credit_percentage),
            'lease_option_maintenance'        => $this->lease_option_maintenance,
            'lease_option_extension_terms'    => $this->lease_option_extension_terms,
            'lease_purchase_price'            => $this->stripCommas($this->lease_purchase_price),
            'lease_purchase_terms'            => $this->lease_purchase_terms,
            'lease_purchase_duration'         => $this->lease_purchase_duration,
            'lease_purchase_payment'          => $this->stripCommas($this->lease_purchase_payment),
            'lease_purchase_conditions'       => $this->lease_purchase_conditions,
            'lease_purchase_option_fee'       => $this->lease_purchase_option_fee,
            'lease_purchase_option_fee_amount' => $this->stripCommas($this->lease_purchase_option_fee_amount),
            'lease_purchase_maintenance'      => $this->lease_purchase_maintenance,
            'lease_purchase_extension_terms'  => $this->lease_purchase_extension_terms,
            'lease_purchase_rent_credit'      => $this->lease_purchase_rent_credit,
            'lease_purchase_rent_credit_amount' => $this->stripCommas($this->lease_purchase_rent_credit_amount),
            'lease_purchase_deposit'          => $this->stripCommas($this->lease_purchase_deposit),
            'cryptocurrency_type'             => $this->cryptocurrency_type,
            'crypto_percentage'               => $this->stripCommas($this->crypto_percentage),
            'cash_percentage_crypto'          => $this->stripCommas($this->cash_percentage_crypto),
            'crypto_transfer_timing'          => $this->crypto_transfer_timing,
            'crypto_transfer_timing_other'    => $this->crypto_transfer_timing_other,
            'crypto_exchange_method'          => $this->crypto_exchange_method,
            'crypto_custodian_wallet'         => $this->crypto_custodian_wallet,
            'crypto_transaction_fees'         => $this->crypto_transaction_fees,
            'nft_description'                 => $this->nft_description,
            'nft_percentage'                  => $this->stripCommas($this->nft_percentage),
            'cash_percentage_nft'             => $this->stripCommas($this->cash_percentage_nft),
            'nft_valuation_method'            => $this->nft_valuation_method,
            'nft_transfer_method'             => $this->nft_transfer_method,
            'nft_gas_fees'                    => $this->nft_gas_fees,
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
            'real_estate_purchase'            => $this->real_estate_purchase,
            'number_of_unit'                  => $this->number_of_unit,
            'number_of_unit_other'            => $this->number_of_unit_other,
            'number_of_unit_type'             => json_encode($numberOfUnitType),
            'number_of_unit_type_other'       => $this->number_of_unit_type_other,
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
            'monthly_income'                  => $this->stripCommas($this->monthly_income),
            'number_occupant'                 => $this->number_occupant,
            'service_animal'                  => $this->service_animal,
            'emotional_support_animal'        => $this->emotional_support_animal,
            'target_closing_date'             => $this->target_closing_date,
            'occupant_types'                  => $this->occupant_types,
            'other_services'                  => $this->other_services,
            'additional_details'              => $this->additional_details,
            'commission_structure'            => $this->commission_structure,
            'lease_type'                      => $this->lease_type,
            'lease_type_other'                => $this->lease_type_other,
            'lease_value'                     => $this->lease_value,
            'lease_fee_type'                  => $this->lease_fee_type,
            'lease_fee_flat'                  => $this->stripCommas($this->lease_fee_flat),
            'lease_fee_percentage'            => $this->stripCommas($this->lease_fee_percentage),
            'lease_fee_months'                => $this->lease_fee_months,
            'lease_fee_percentage_monthly_rent' => $this->stripCommas($this->lease_fee_percentage_monthly_rent),
            'lease_fee_flat_combo'            => $this->stripCommas($this->lease_fee_flat_combo),
            'lease_fee_percentage_combo'      => $this->stripCommas($this->lease_fee_percentage_combo),
            'lease_fee_other'                 => $this->lease_fee_other,
            'lease_fee_flat_combo_net'        => $this->stripCommas($this->lease_fee_flat_combo_net),
            'lease_fee_percentage_combo_net'  => $this->stripCommas($this->lease_fee_percentage_combo_net),
            'lease_fee_percentage_monthly_number' => $this->stripCommas($this->lease_fee_percentage_monthly_number),
            'lease_fee_percentage_net'        => $this->stripCommas($this->lease_fee_percentage_net),
            'lease_option_consideration'      => $this->stripCommas($this->lease_option_consideration),
            'additional_details_broker'       => $this->additional_details_broker,
            'purchase_type'                   => $this->purchase_type,
            'purchase_value'                  => $this->purchase_value,
            'purchase_pice_commercial'        => $this->stripCommas($this->purchase_pice_commercial),
            'purchase_fee_flat_exercised'     => $this->stripCommas($this->purchase_fee_flat_exercised),
            'purchase_fee_type'               => $this->purchase_fee_type,
            'purchase_fee_percentage'         => $this->stripCommas($this->purchase_fee_percentage),
            'purchase_fee_flat'               => $this->stripCommas($this->purchase_fee_flat),
            'purchase_fee_percentage_combo'   => $this->stripCommas($this->purchase_fee_percentage_combo),
            'purchase_fee_flat_combo'         => $this->stripCommas($this->purchase_fee_flat_combo),
            'purchase_fee_other'              => $this->purchase_fee_other,
            'lease_option_fee_type'           => $this->lease_option_fee_type,
            'lease_option_fee_flat'           => $this->stripCommas($this->lease_option_fee_flat),
            'lease_option_fee_percentage'     => $this->stripCommas($this->lease_option_fee_percentage),
            'lease_option_fee_other'          => $this->lease_option_fee_other,
            'lease_option_fee_flat_combo'     => $this->stripCommas($this->lease_option_fee_flat_combo),
            'lease_option_fee_percentage_combo' => $this->stripCommas($this->lease_option_fee_percentage_combo),
            'protection_period'               => $this->protection_period,
            'early_termination_fee_option'    => $this->early_termination_fee_option,
            'early_termination_fee_amount'    => $this->stripCommas($this->early_termination_fee_amount),
            'retainer_fee_option'             => $this->retainer_fee_option,
            'retainer_fee_amount'             => $this->stripCommas($this->retainer_fee_amount),
            'retainer_fee_application'        => $this->retainer_fee_application,
            'agency_agreement_timeframe'      => $this->agency_agreement_timeframe,
            'agency_agreement_custom'         => $this->agency_agreement_custom,
            'brokerage_relationship'          => $this->brokerage_relationship,
            'person_meeting'                  => $this->person_meeting,
            'meeting_details_first_name'      => $this->meeting_details_first_name,
            'meeting_details_last_name'       => $this->meeting_details_last_name,
            'meeting_details_phone'           => $this->meeting_details_phone,
            'meeting_details_email'           => $this->meeting_details_email,
            'address'                         => $this->address,
            'meeting_details_meeting_time'    => $this->meeting_details_meeting_time,
            'meeting_details_meeting_date'    => $this->meeting_details_meeting_date,
            'meeting_details_time_zone'       => $this->meeting_details_time_zone,
            'meeting_details_instructions'    => $this->meeting_details_instructions,
            'service_completion_date'         => $this->service_completion_date,
            'service_completion_time'         => $this->service_completion_time,
            'service_time_zone'               => $this->service_time_zone,
            'meeting_details_additional_details' => $this->meeting_details_additional_details,
            'list_criteria'                   => $this->list_criteria,
            'list_criteria_fee'               => $this->stripCommas($this->list_criteria_fee),
            'market_groups'                   => $this->market_groups,
            'market_groups_fee'               => $this->stripCommas($this->market_groups_fee),
            'promote_social'                  => $this->promote_social,
            'promote_social_fee'              => $this->stripCommas($this->promote_social_fee),
            'launch_ads'                      => $this->launch_ads,
            'launch_ads_fee'                  => $this->stripCommas($this->launch_ads_fee),
            'include_marketing_fee'           => $this->include_marketing_fee,
            'marketing_materials_fee'         => $this->stripCommas($this->marketing_materials_fee),
            'email_notifications_fee'         => $this->stripCommas($this->email_notifications_fee),
            'off_market_search_fee'           => $this->stripCommas($this->off_market_search_fee),
            'mls_filter_fee'                  => $this->stripCommas($this->mls_filter_fee),
            'email_marketing_fee'             => $this->stripCommas($this->email_marketing_fee),
            'schedule_showings'               => $this->schedule_showings,
            'number_of_showings_to_schedule'  => $this->number_of_showings_to_schedule,
            'schedule_showings_fee'           => $this->stripCommas($this->schedule_showings_fee),
            'attend_showings'                 => $this->attend_showings,
            'number_of_showings_to_attend'    => $this->number_of_showings_to_attend,
            'attend_showings_fee'             => $this->stripCommas($this->attend_showings_fee),
            'provide_virtual_tours'           => $this->provide_virtual_tours,
            'number_of_virtual_tours'         => $this->number_of_virtual_tours,
            'virtual_tours_fee'               => $this->stripCommas($this->virtual_tours_fee),
            'assist_application'              => $this->assist_application,
            'assist_application_fee'          => $this->stripCommas($this->assist_application_fee),
            'collect_documents'               => $this->collect_documents,
            'collect_documents_fee'           => $this->stripCommas($this->collect_documents_fee),
            'submit_application'              => $this->submit_application,
            'submit_application_fee'          => $this->stripCommas($this->submit_application_fee),
            'review_lease'                    => $this->review_lease,
            'review_lease_fee'                => $this->stripCommas($this->review_lease_fee),
            'provide_lease_form'              => $this->provide_lease_form,
            'provide_lease_form_fee'          => $this->stripCommas($this->provide_lease_form_fee),
            'coordinate_signing'              => $this->coordinate_signing,
            'coordinate_signing_fee'          => $this->stripCommas($this->coordinate_signing_fee),
            'prepare_application_fee'         => $this->stripCommas($this->prepare_application_fee),
            'move_in_inspection_fee'          => $this->stripCommas($this->move_in_inspection_fee),
            'moving_resources_fee'            => $this->stripCommas($this->moving_resources_fee),
            'short_term_housing_fee'          => $this->stripCommas($this->short_term_housing_fee),
            'rental_rights_fee'               => $this->stripCommas($this->rental_rights_fee),
            'lease_advice_fee'                => $this->stripCommas($this->lease_advice_fee),
            'neighborhood_insights_fee'       => $this->stripCommas($this->neighborhood_insights_fee),
            'neighborhood_marketing_fee'      => $this->stripCommas($this->neighborhood_marketing_fee),
            'neighborhood_materials_fee'      => $this->stripCommas($this->neighborhood_materials_fee),
            'custom_services'                 => json_encode($this->custom_services),
            'total_marketing_fee'             => $this->stripCommas($this->total_marketing_fee),
            'total_flat_fee'                  => $this->stripCommas($this->total_flat_fee),
            'fees'                            => json_encode($this->fees),
            'enable'                          => json_encode($this->enable),
            'showings_count'                  => $this->showings_count,
            'attend_showings_count'           => $this->attend_showings_count,
            'virtual_tours_count'             => $this->virtual_tours_count,
            'understand_terms'                => $this->understand_terms,
            'staging_duration'                => $this->staging_duration,
            'open_house_count'                => $this->open_house_count,
            'virtual_showings_count'          => $this->virtual_showings_count,
            'earnest_money_amount'            => $this->earnest_money_amount,
            'earnest_money_type'              => $this->earnest_money_type,
            'earnest_money_timing'            => $this->earnest_money_timing,
            'due_diligence_yn'                => $this->due_diligence_yn,
            'inspection_period_days'          => $this->inspection_period_days,
            'inspection_period_other'         => $this->inspection_period_other,
            'inspection_contingency_buyer'    => $this->inspection_contingency_buyer,
            'inspection_contingency_period'   => $this->inspection_contingency_period,
            'appraisal_contingency_buyer'     => $this->appraisal_contingency_buyer,
            'appraisal_contingency_days'      => $this->appraisal_contingency_days,
            'financing_contingency_buyer'     => $this->financing_contingency_buyer,
            'financing_contingency_days_buyer' => $this->financing_contingency_days_buyer,
            'financing_contingency_period'    => $this->financing_contingency_period,
            'seller_contribution'             => $this->seller_contribution,
            'seller_contribution_details'     => $this->seller_contribution_details,
            'possession_preference'           => $this->possession_preference,
            'possession_preference_other'     => $this->possession_preference_other,
            'possession_details'              => $this->possession_details,
            'home_warranty_requested'         => $this->home_warranty_requested,
            'home_warranty_details'           => $this->home_warranty_details,
            'as_is_purchase'                  => $this->as_is_purchase,
            'property_inclusions'             => $this->property_inclusions,
            'property_exclusions'             => $this->property_exclusions,
            'closing_cost_responsibility'     => $this->closing_cost_responsibility,
            'additional_purchase_terms'       => $this->additional_purchase_terms,
            'home_sale_contingency'           => $this->home_sale_contingency,
            'home_sale_contingency_period'        => $this->home_sale_contingency_period,
            'home_sale_contingency_address'       => $this->home_sale_contingency_address,
            'home_sale_contingency_date'          => $this->home_sale_contingency_date,
            'home_sale_contingency_under_contract' => $this->home_sale_contingency_under_contract,
            'home_sale_contingency_details'       => $this->home_sale_contingency_details,
            'first_name'                      => $this->first_name,
            'last_name'                       => $this->last_name,
            'phone_number'                    => preg_replace('/\D/', '', $this->phone_number),
            'email'                           => $this->email,
            'video_link'                      => $this->video_link,
            'listing_ai_faq'                  => json_encode($this->listing_ai_faq ?: []),
            'photo'                           => is_string($this->photo) ? $this->photo : '',
            'business_type_selected'          => json_encode($this->business_type_selected ?? []),
            'purchase_purpose_other'          => $this->purchase_purpose_other,
            'flood_zone_tolerance'            => json_encode($this->flood_zone_tolerance ?? []),
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

    public function saveDraftOnly()
    {
        try {
            if (!$this->auctionId) {
                session()->flash('error', 'No listing found to save.');
                return;
            }

            $auction = HireBuyerAgentAuction::find($this->auctionId);
            if (!$auction) {
                session()->flash('error', 'Listing not found.');
                return;
            }

            // Draft records must go through the versioning path to avoid
            // overwriting an existing draft in place.
            if ($auction->is_draft) {
                if (!$this->listingId) {
                    $this->listingId = $this->auctionId;
                }
                return $this->saveDraft();
            }

            $auction->title    = $this->listing_title;
            $auction->is_draft = 1;
            $auction->save();

            $this->listingId = $auction->id;
            $this->saveAllMetadata($auction);

            $this->isDraft        = true;
            $this->isListingDraft = true;

            app(\App\Services\AskAi\AskAiKnowledgeSnapshotBuilderService::class)->buildSilently('buyer', $this->listingId);

            app(WizardEventService::class)->record(
                (string) $this->user_type,
                $this->listingId ? (int) $this->listingId : null,
                auth()->id() ? (int) auth()->id() : null,
                'save_draft',
                'tab_' . $this->activeTab,
                'edit',
                session()->getId()
            );
            session()->flash('success', 'Draft saved successfully.');
        } catch (\Exception $e) {
            session()->flash('error', 'Error saving draft: ' . $e->getMessage());
        }
    }

    public function saveDraft()
    {
        try {
            $this->isDraft = true;

            $newPayloadHash = $this->buildDraftPayloadHash();

            $previousDraft = $this->listingId
                ? HireBuyerAgentAuction::find($this->listingId)
                : null;

            // Guard: do not create a draft version of a published listing.
            if ($previousDraft && !$previousDraft->is_draft) {
                session()->flash('error', 'Cannot create a new draft version of a published listing.');
                return;
            }

            $previousVersion = 0;
            $parentDraftId   = null;
            if ($previousDraft && $previousDraft->is_draft) {
                $oldHash = $previousDraft->info('draft_payload_hash') ?? '';
                if ($oldHash === $newPayloadHash) {
                    session()->flash('success', 'No changes detected — draft is already up to date.');
                    return redirect()->route('offer.listing.buyer.edit', ['auctionId' => $this->listingId]);
                }
                $previousVersion = (int) ($previousDraft->info('draft_version') ?? 1);
                $parentDraftId   = $previousDraft->id;
            }

            $auction = new HireBuyerAgentAuction();
            $auction->user_id  = Auth::id();
            $auction->title    = $this->listing_title;
            $auction->is_draft = true;
            $auction->save();

            $this->listingId = $auction->id;
            $this->auctionId = $auction->id;

            $this->saveAllMetadata($auction);

            $auction->saveMeta('draft_version',      $previousVersion + 1);
            $auction->saveMeta('parent_draft_id',    $parentDraftId);
            $auction->saveMeta('draft_payload_hash', $newPayloadHash);

            app(\App\Services\AskAi\AskAiKnowledgeSnapshotBuilderService::class)->buildSilently('buyer', $this->listingId);

            app(WizardEventService::class)->record(
                (string) $this->user_type,
                $this->listingId ? (int) $this->listingId : null,
                auth()->id() ? (int) auth()->id() : null,
                'save_draft',
                'tab_' . $this->activeTab,
                'edit',
                session()->getId()
            );
            session()->flash('success', 'Draft saved successfully (Version ' . ($previousVersion + 1) . '). You can return later to complete your listing.');
            return redirect()->route('offer.listing.buyer.edit', ['auctionId' => $this->listingId]);
        } catch (\Exception $e) {
            session()->flash('error', 'Error saving draft: ' . $e->getMessage());
        }
    }

    public function loadAuctionData($listingId)
    {
        $auction = HireBuyerAgentAuction::where('id', $listingId)
            ->where('user_id', Auth::id())
            ->first();
        if ($auction) {
            // Set flag to prevent updatedOfferedFinancing() from resetting loaded data
            $this->isLoadingData = true;

            $this->isDraft = (bool)($auction->is_draft);

            // Load all metadata fields
            $this->listing_title = $auction->title ?? '';
            $this->user_type = $auction->get->user_type ?? 'buyer';
            $this->listing_status = $auction->get->listing_status ?? 'Active';
            $this->meeting_Preference = $auction->get->meeting_Preference ?? '';
            $this->auction_type = $auction->get->auction_type ?? '';
            $this->working_with_agent = $auction->get->working_with_agent ?? '';
            $this->listing_date = $auction->get->listing_date ?? '';
            $this->desired_agent_hire_date = $auction->get->desired_agent_hire_date ?? '';
            $this->expiration_date = $auction->get->expiration_date ?? '';
            $this->auction_time = $auction->get->auction_time ?? '';

            $this->state = $auction->get->state ?? '';
            $ldnaRaw = $auction->info('location_dna_preferences');
            $ldna    = $ldnaRaw ? (json_decode($ldnaRaw, true) ?? []) : [];

            // Migrate legacy `cities` meta into the in-memory $ldna used to pre-populate
            // the LDNA map widget. Does NOT touch $ldnaRaw so the DB value is unchanged
            // until the user explicitly saves.
            if (empty($ldna['cities'] ?? [])) {
                $legacyCitiesRaw = $auction->info('cities');
                if ($legacyCitiesRaw) {
                    $legacyCities = is_string($legacyCitiesRaw)
                        ? (json_decode($legacyCitiesRaw, true) ?? [])
                        : (array) $legacyCitiesRaw;
                    $legacyCities = array_values(array_filter($legacyCities, fn($c) => is_string($c) && trim($c) !== ''));
                    if (!empty($legacyCities)) {
                        $ldna['cities'] = $legacyCities;
                        // $ldnaRaw is intentionally left unchanged here.
                    }
                }
            }

            $this->existingLocationDna = $ldna;
            $this->location_dna_preferences_json = $ldnaRaw ?? '';
            // 9B-2 prefill for Preferred State / counties runs after $this->counties is
            // loaded below (the JS bridge carries the merged blob back on save).
            $this->property_type = $auction->get->property_type ?? '';

            $countiesRaw = $auction->get->counties ?? null;
            $this->counties = $countiesRaw ? (is_string($countiesRaw) ? json_decode($countiesRaw, true) ?? [] : (array)$countiesRaw) : [];

            // 9B-2 prefill (applied here because $this->counties loads after the LDNA blob).
            if (empty($this->existingLocationDna['state'] ?? '') && !empty($this->state)) {
                $this->existingLocationDna['state'] = $this->state;
            }
            if (empty($this->existingLocationDna['counties'] ?? []) && !empty($this->counties)) {
                $this->existingLocationDna['counties'] = array_values(array_filter(
                    (array) $this->counties,
                    fn($c) => is_string($c) && trim($c) !== ''
                ));
            }
            // Property details
            $propertyItemsRaw = $auction->get->property_items ?? null;
            $this->property_items = $propertyItemsRaw ? (is_string($propertyItemsRaw) ? json_decode($propertyItemsRaw, true) ?? [] : (array)$propertyItemsRaw) : [];
            $this->property_items_json = json_encode($this->property_items ?? []);

            $this->other_property_items = $auction->get->other_property_items ?? '';
            $this->condition_prop = $auction->get->condition_prop ?? '';
            $conditionPropBuyerRaw = $auction->get->condition_prop_buyer ?? null;
            $this->condition_prop_buyer = $conditionPropBuyerRaw ? (is_string($conditionPropBuyerRaw) ? json_decode($conditionPropBuyerRaw, true) ?? [] : (array)$conditionPropBuyerRaw) : [];
            $this->condition_prop_buyer_json = json_encode($this->condition_prop_buyer ?? []);
            $this->leasing_space = $auction->get->leasing_space ?? '';
            $this->other_property_condition = $auction->get->other_property_condition ?? '';
            $this->bathrooms = $auction->get->bathrooms ?? '';
            $this->other_bathrooms = $auction->get->other_bathrooms ?? '';
            $this->bedrooms = $auction->get->bedrooms ?? '';
            $this->other_bedrooms = $auction->get->other_bedrooms ?? '';
            $this->minimum_heated_square = $auction->get->minimum_heated_square ?? '';
            $this->minimum_leaseable = $auction->get->minimum_leaseable ?? '';
            $this->min_acreage = $auction->get->min_acreage ?? '';
            $this->total_acreage = $auction->get->total_acreage ?? '';
            $this->minimum_cap_rate = $auction->get->minimum_cap_rate ?? '';
            $rawAssets = $auction->get->assets ?? [];
            $this->assets = is_string($rawAssets) ? (json_decode($rawAssets, true) ?? []) : (is_array($rawAssets) ? $rawAssets : []);
            $this->assets_other = $auction->get->assets_other ?? '';
            $this->property_criteria = $auction->get->property_criteria ?? '';
            $this->unit_size = $auction->get->unit_size ?? '';
            $this->unit_size_other = $auction->get->unit_size_other ?? '';
            $this->preferance_details = $auction->get->preferance_details ?? '';

            // Property DNA Phase C — Buyer Tier 1 EAV fields
            $this->purchase_purpose = $auction->get->purchase_purpose ?? '';
            $this->purchase_purpose_other = $auction->get->purchase_purpose_other ?? '';
            $this->commute_destination_zip = $auction->get->commute_destination_zip ?? '';
            $this->max_commute_minutes = $auction->get->max_commute_minutes ?? '';
            $this->commute_mode = $auction->get->commute_mode ?? '';
            // 9C: Important Places (additive; separate meta key — commute fields above untouched)
            $this->loadImportantPlaces($auction);
            $this->hoa_acceptance = $auction->get->hoa_acceptance ?? '';
            $this->hoa_max_monthly_fee = $auction->get->hoa_max_monthly_fee ?? '';
            $floodZoneRaw = $auction->get->flood_zone_tolerance ?? null;
            $this->flood_zone_tolerance = $floodZoneRaw ? (is_string($floodZoneRaw) ? json_decode($floodZoneRaw, true) ?? [] : (array)$floodZoneRaw) : [];
            $this->flood_zone_tolerance_other = $auction->get->flood_zone_tolerance_other ?? '';


            // Sale Provision
            $saleProvisionRaw = $auction->get->sale_provision ?? null;
            $this->sale_provision = $saleProvisionRaw ? (is_string($saleProvisionRaw) ? json_decode($saleProvisionRaw, true) ?? [] : (array)$saleProvisionRaw) : [];
            $this->sale_provision_other = $auction->get->sale_provision_other ?? '';
            $this->sale_provision_assignment = $auction->get->sale_provision_assignment ?? '';
            $this->assignment_fee_type = $auction->get->assignment_fee_type ?? '$';
            $this->assignment_fee_amount = $auction->get->assignment_fee_amount ?? '';
            $this->buyer_sell_contract = $auction->get->buyer_sell_contract ?? '';

            // Budget & Financing
            $this->maximum_budget = $auction->get->maximum_budget ?? '';
            $offeredFinancingRaw = $auction->get->offered_financing ?? null;
            $this->offered_financing = $offeredFinancingRaw ? (is_string($offeredFinancingRaw) ? json_decode($offeredFinancingRaw, true) ?? [] : (array)$offeredFinancingRaw) : [];
            $this->previousOfferedFinancing = $this->offered_financing; // Initialize for smart reset comparison
            $this->other_financing = $auction->get->other_financing ?? '';
            $this->cash_budget = $auction->get->cash_budget ?? '';
            $this->pre_approved = $auction->get->pre_approved ?? '';
            $this->pre_approval_amount = $auction->get->pre_approval_amount ?? '';
            $this->purchase_price = $auction->get->purchase_price ?? '';
            $this->down_payment_type = $auction->get->down_payment_type ?? '%';
            $this->down_payment_amount = $auction->get->down_payment_amount ?? '';
            $this->seller_financing_type = $auction->get->seller_financing_type ?? '$';
            $this->seller_financing_amount = $auction->get->seller_financing_amount ?? '';
            $this->interest_rate = $auction->get->interest_rate ?? '';
            $this->loan_duration = $auction->get->loan_duration ?? '';
            $this->prepayment_penalty = $auction->get->prepayment_penalty ?? '';
            $this->prepayment_penalty_amount = $auction->get->prepayment_penalty_amount ?? '';
            $this->balloon_payment = $auction->get->balloon_payment ?? '';
            $this->balloon_payment_amount = $auction->get->balloon_payment_amount ?? '';
            $this->balloon_payment_date = $auction->get->balloon_payment_date ?? '';
            $this->assumable_interest = $auction->get->assumable_interest ?? '';
            $this->assumable_max_interest_rate = $auction->get->assumable_max_interest_rate ?? '';
            $this->assumable_max_monthly_payment = $auction->get->assumable_max_monthly_payment ?? '';
            $this->assumable_bridge_gap_cash = $auction->get->assumable_bridge_gap_cash ?? '';
            $this->assumption_fee_responsibility = $auction->get->assumption_fee_responsibility ?? '';

            // Seller Financing Additional Fields
            $this->seller_amortization_type = $auction->get->seller_amortization_type ?? '';
            $this->seller_amortization_other = $auction->get->seller_amortization_other ?? '';
            $this->seller_payment_frequency = $auction->get->seller_payment_frequency ?? '';
            $this->seller_payment_frequency_other = $auction->get->seller_payment_frequency_other ?? '';
            $this->seller_late_fee_amount = $auction->get->seller_late_fee_amount ?? '';

            // Exchange/Trade
            // Canonical type: array. Create may persist as raw string (legacy) or JSON-encoded
            // array; normalize to array here so saveAllMetadata always writes a consistent value.
            $rawExchangeItem = $auction->get->exchange_item ?? null;
            if (is_string($rawExchangeItem)) {
                $decoded = json_decode($rawExchangeItem, true);
                $this->exchange_item = is_array($decoded) ? $decoded : ($rawExchangeItem !== '' ? [$rawExchangeItem] : []);
            } elseif (is_array($rawExchangeItem)) {
                $this->exchange_item = $rawExchangeItem;
            } else {
                $this->exchange_item = [];
            }
            $this->other_exchange_item = $auction->get->other_exchange_item ?? '';
            $this->exchange_item_value = $auction->get->exchange_item_value ?? '';
            $this->exchange_item_condition = $auction->get->exchange_item_condition ?? '';
            $this->additional_cash = $auction->get->additional_cash ?? '';
            $this->value_determination = $auction->get->value_determination ?? '';
            $this->exchange_transfer_method = $auction->get->exchange_transfer_method ?? '';
            $this->exchange_liens = $auction->get->exchange_liens ?? '';
            $this->exchange_liens_details = $auction->get->exchange_liens_details ?? '';
            $this->exchange_inspection_rights = $auction->get->exchange_inspection_rights ?? '';

            // Lease Option
            $this->interested_lease_option = $auction->get->interested_lease_option ?? '';
            $this->interested_lease_option_agreement = $auction->get->interested_lease_option_agreement ?? '';
            $this->lease_option_price = $auction->get->lease_option_price ?? '';
            $this->lease_option_terms = $auction->get->lease_option_terms ?? '';
            $this->lease_option_duration = $auction->get->lease_option_duration ?? '';
            $this->lease_option_payment = $auction->get->lease_option_payment ?? '';
            $this->lease_option_conditions = $auction->get->lease_option_conditions ?? '';
            $this->has_option_fee = $auction->get->has_option_fee ?? '';
            $this->option_fee_amount = $auction->get->option_fee_amount ?? '';
            $this->lease_option_fee_credit = $auction->get->lease_option_fee_credit ?? '';
            $this->lease_option_fee_credit_percentage = $auction->get->lease_option_fee_credit_percentage ?? '';
            $this->lease_option_maintenance = $auction->get->lease_option_maintenance ?? '';
            $this->lease_option_extension_terms = $auction->get->lease_option_extension_terms ?? '';

            // Lease Purchase
            $this->lease_purchase_price = $auction->get->lease_purchase_price ?? '';
            $this->lease_purchase_terms = $auction->get->lease_purchase_terms ?? '';
            $this->lease_purchase_duration = $auction->get->lease_purchase_duration ?? '';
            $this->lease_purchase_payment = $auction->get->lease_purchase_payment ?? '';
            $this->lease_purchase_conditions = $auction->get->lease_purchase_conditions ?? '';
            $this->lease_purchase_option_fee = $auction->get->lease_purchase_option_fee ?? '';
            $this->lease_purchase_option_fee_amount = $auction->get->lease_purchase_option_fee_amount ?? '';
            $this->lease_purchase_maintenance = $auction->get->lease_purchase_maintenance ?? '';
            $this->lease_purchase_extension_terms = $auction->get->lease_purchase_extension_terms ?? '';
            $this->lease_purchase_rent_credit = $auction->get->lease_purchase_rent_credit ?? '';
            $this->lease_purchase_rent_credit_amount = $auction->get->lease_purchase_rent_credit_amount ?? '';
            $this->lease_purchase_deposit = $auction->get->lease_purchase_deposit ?? '';

            // Cryptocurrency
            $this->cryptocurrency_type = $auction->get->cryptocurrency_type ?? '';
            $this->crypto_percentage = $auction->get->crypto_percentage ?? '';
            $this->cash_percentage_crypto = $auction->get->cash_percentage_crypto ?? '';
            $this->crypto_transfer_timing = $auction->get->crypto_transfer_timing ?? '';
            $this->crypto_transfer_timing_other = $auction->get->crypto_transfer_timing_other ?? '';
            $this->crypto_exchange_method = $auction->get->crypto_exchange_method ?? '';
            $this->crypto_custodian_wallet = $auction->get->crypto_custodian_wallet ?? '';
            $this->crypto_transaction_fees = $auction->get->crypto_transaction_fees ?? '';

            // NFT
            $this->nft_description = $auction->get->nft_description ?? '';
            $this->nft_percentage = $auction->get->nft_percentage ?? '';
            $this->cash_percentage_nft = $auction->get->cash_percentage_nft ?? '';
            $this->nft_valuation_method = $auction->get->nft_valuation_method ?? '';
            $this->nft_transfer_method = $auction->get->nft_transfer_method ?? '';
            $this->nft_gas_fees = $auction->get->nft_gas_fees ?? '';

            // Amenities and features
            $tenantRequireRaw = $auction->get->tenant_require ?? null;
            $this->tenant_require = $tenantRequireRaw ? (is_string($tenantRequireRaw) ? json_decode($tenantRequireRaw, true) ?? [] : (array)$tenantRequireRaw) : [];

            $this->carport_needed = $auction->get->carport_needed ?? '';
            $this->other_carport_needed = $auction->get->other_carport_needed ?? '';
            $this->garage_needed = $auction->get->garage_needed ?? '';
            $this->other_garage_needed = $auction->get->other_garage_needed ?? '';
            $this->garage_parking_spaces = $auction->get->garage_parking_spaces ?? '';
            $gpsRaw = $auction->get->garage_parking_spaces_option ?? null;
            $this->garage_parking_spaces_option = $gpsRaw ? (is_string($gpsRaw) ? json_decode($gpsRaw, true) ?? [] : (array)$gpsRaw) : [];
            $this->other_parking_space_wrapper = $auction->get->other_parking_space_wrapper ?? '';
            $this->pool_needed = $auction->get->pool_needed ?? '';

            $poolTypeRaw = $auction->get->pool_type ?? null;
            $this->pool_type = $poolTypeRaw ? (is_string($poolTypeRaw) ? json_decode($poolTypeRaw, true) ?? [] : (array)$poolTypeRaw) : [];

            $viewPreferenceRaw = $auction->get->view_preference ?? null;
            $this->view_preference = $viewPreferenceRaw ? (is_string($viewPreferenceRaw) ? json_decode($viewPreferenceRaw, true) ?? [] : (array)$viewPreferenceRaw) : [];



            $this->other_preferences = $auction->get->other_preferences ?? '';
            $this->real_estate_purchase = $auction->get->real_estate_purchase ?? '';
            $this->number_of_unit = $auction->get->number_of_unit ?? '';
            $this->number_of_unit_other = $auction->get->number_of_unit_other ?? '';
            $numberUnitTypeRaw = $auction->get->number_of_unit_type ?? null;
            $this->number_of_unit_type = $numberUnitTypeRaw ? (is_string($numberUnitTypeRaw) ? json_decode($numberUnitTypeRaw, true) ?? [] : (array)$numberUnitTypeRaw) : [];
            $this->number_of_unit_type_json = json_encode($this->number_of_unit_type ?? []);
            $this->number_of_unit_type_other = $auction->get->number_of_unit_type_other ?? '';
            $businessTypeRaw = $auction->get->business_type_selected ?? null;
            $this->business_type_selected = $businessTypeRaw ? (is_string($businessTypeRaw) ? json_decode($businessTypeRaw, true) ?? [] : (array)$businessTypeRaw) : [];
            $this->business_type_selected_json = json_encode($this->business_type_selected ?? []);
            $this->other_business_type = $auction->get->other_business_type ?? '';
            $this->minimum_annual_net_income = $auction->get->minimum_annual_net_income ?? '';
            $this->leasing_55_plus = $auction->get->leasing_55_plus ?? '';

            $nonNegotiableAmenitiesRaw = $auction->get->non_negotiable_amenities ?? null;
            $this->non_negotiable_amenities = $nonNegotiableAmenitiesRaw ? (is_string($nonNegotiableAmenitiesRaw) ? json_decode($nonNegotiableAmenitiesRaw, true) ?? [] : (array)$nonNegotiableAmenitiesRaw) : [];

            $this->other_non_negotiable_amenities = $auction->get->other_non_negotiable_amenities ?? '';
            $this->budget = $auction->get->budget ?? '';

            // Missing buyer agent fields
            $this->target_closing_date = $auction->get->target_closing_date ?? '';
            $this->service_animal = $auction->get->service_animal ?? '';
            $this->emotional_support_animal = $auction->get->emotional_support_animal ?? '';
            $this->occupant_types = $auction->get->occupant_types ?? '';

            // Lease terms
            $leaseForRaw = $auction->get->lease_for ?? null;
            $this->lease_for = $leaseForRaw ? (is_string($leaseForRaw) ? json_decode($leaseForRaw, true) ?? [] : (array)$leaseForRaw) : [];

            $this->other_lease_for = $auction->get->other_lease_for ?? '';
            $this->lease_by = $auction->get->lease_by ?? '';
            $this->lease_date = $auction->get->lease_date ?? '';

            // Tenant information
            $this->pets = $auction->get->pets ?? '';
            $this->number_of_pets = $auction->get->number_of_pets ?? '';
            $this->breed_of_pets = $auction->get->breed_of_pets ?? '';
            $this->type_of_pets = $auction->get->type_of_pets ?? '';
            $this->weight_of_pets = $auction->get->weight_of_pets ?? '';

            $creditScoreRatingRaw = $auction->get->credit_scroe_rating ?? null;
            $this->credit_scroe_rating = $creditScoreRatingRaw ? (is_string($creditScoreRatingRaw) ? json_decode($creditScoreRatingRaw, true) ?? [] : (array)$creditScoreRatingRaw) : [];

            $this->prior_eviction = $auction->get->prior_eviction ?? '';
            $this->eviction_explanation = $auction->get->eviction_explanation ?? '';
            $this->prior_felony = $auction->get->prior_felony ?? '';
            $this->prior_felony_explanation = $auction->get->prior_felony_explanation ?? '';
            $this->monthly_income = $auction->get->monthly_income ?? '';
            $this->number_occupant = $auction->get->number_occupant ?? '';

            $this->other_services = $auction->get->other_services ?? '';
            $this->other_services_enabled = (bool)($auction->get->other_services_enabled ?? false);
            $this->additional_details = $auction->get->additional_details ?? '';

            // Broker compensation
            $this->commission_structure = $auction->get->commission_structure ?? '';
            $this->lease_type = $auction->get->lease_type ?? '';
            $this->lease_type_other = $auction->get->lease_type_other ?? '';
            $this->lease_value = $auction->get->lease_value ?? '';
            $this->lease_fee_type = $auction->get->lease_fee_type ?? '';
            $this->lease_fee_flat = $auction->get->lease_fee_flat ?? '';
            $this->lease_fee_percentage = $auction->get->lease_fee_percentage ?? '';
            $this->lease_fee_months = $auction->get->lease_fee_months ?? '';
            $this->lease_fee_percentage_monthly_rent = $auction->get->lease_fee_percentage_monthly_rent ?? '';
            $this->lease_fee_flat_combo = $auction->get->lease_fee_flat_combo ?? '';
            $this->lease_fee_percentage_combo = $auction->get->lease_fee_percentage_combo ?? '';
            $this->lease_fee_other = $auction->get->lease_fee_other ?? '';
            $this->lease_fee_flat_combo_net = $auction->get->lease_fee_flat_combo_net ?? '';
            $this->lease_fee_percentage_combo_net = $auction->get->lease_fee_percentage_combo_net ?? '';
            $this->lease_fee_percentage_monthly_number = $auction->get->lease_fee_percentage_monthly_number ?? '';
            $this->lease_fee_percentage_net = $auction->get->lease_fee_percentage_net ?? '';
            $this->lease_option_consideration = $auction->get->lease_option_consideration ?? '';
            $this->additional_details_broker = $auction->get->additional_details_broker ?? '';
            $this->purchase_type = $auction->get->purchase_type ?? '';
            $this->purchase_value = $auction->get->purchase_value ?? '';
            $this->purchase_pice_commercial = $auction->get->purchase_pice_commercial ?? '';
            $this->purchase_fee_flat_exercised = $auction->get->purchase_fee_flat_exercised ?? '';
            $this->purchase_fee_type = $auction->get->purchase_fee_type ?? '';
            $this->purchase_fee_percentage = $auction->get->purchase_fee_percentage ?? '';
            $this->purchase_fee_flat = $auction->get->purchase_fee_flat ?? '';
            $this->purchase_fee_percentage_combo = $auction->get->purchase_fee_percentage_combo ?? '';
            $this->purchase_fee_flat_combo = $auction->get->purchase_fee_flat_combo ?? '';
            $this->purchase_fee_other = $auction->get->purchase_fee_other ?? '';
            $this->lease_option_fee_type = $auction->get->lease_option_fee_type ?? '';
            $this->lease_option_fee_flat = $auction->get->lease_option_fee_flat ?? '';
            $this->lease_option_fee_percentage = $auction->get->lease_option_fee_percentage ?? '';
            $this->lease_option_fee_other = $auction->get->lease_option_fee_other ?? '';
            $this->lease_option_fee_flat_combo = $auction->get->lease_option_fee_flat_combo ?? '';
            $this->lease_option_fee_percentage_combo = $auction->get->lease_option_fee_percentage_combo ?? '';
            $this->protection_period = $auction->get->protection_period ?? '';
            $this->early_termination_fee_option = $auction->get->early_termination_fee_option ?? '';
            $this->early_termination_fee_amount = $auction->get->early_termination_fee_amount ?? '';
            $this->retainer_fee_option = $auction->get->retainer_fee_option ?? '';
            $this->retainer_fee_amount = $auction->get->retainer_fee_amount ?? '';
            $this->retainer_fee_application = $auction->get->retainer_fee_application ?? '';
            $this->agency_agreement_timeframe = $auction->get->agency_agreement_timeframe ?? '';
            $this->agency_agreement_custom = $auction->get->agency_agreement_custom ?? '';
            $this->brokerage_relationship = $auction->get->brokerage_relationship ?? '';

            // Personal information
            $this->first_name = $auction->get->first_name ?? '';
            $this->last_name = $auction->get->last_name ?? '';
            $this->phone_number = $auction->get->phone_number ?? '';
            $this->email = $auction->get->email ?? '';
            $this->agent_brokerage = $auction->get->agent_brokerage ?? '';
            $this->agent_license_number = $auction->get->agent_license_number ?? '';
            $this->agent_nar_member_id = $auction->get->agent_nar_member_id ?? '';
            $this->video_link = $auction->get->video_link ?? '';
            $this->listing_ai_faq = json_decode($auction->info('listing_ai_faq') ?: '{}', true) ?? [];
            $this->photo = $auction->get->photo ?? null;

            // Location and meeting details
            $this->person_meeting = $auction->get->person_meeting ?? '';
            $this->meeting_details_first_name = $auction->get->meeting_details_first_name ?? '';
            $this->meeting_details_last_name = $auction->get->meeting_details_last_name ?? '';
            $this->meeting_details_phone = $auction->get->meeting_details_phone ?? '';
            $this->meeting_details_email = $auction->get->meeting_details_email ?? '';
            $this->address = $auction->get->address ?? '';
            $this->unit_number = $auction->info('unit_number') ?? '';
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

            // Purchase Terms Questions
            $this->earnest_money_amount = $auction->get->earnest_money_amount ?? '';
            $this->earnest_money_type = $auction->get->earnest_money_type ?? '$';
            $this->earnest_money_timing = $auction->get->earnest_money_timing ?? '';
            $this->due_diligence_yn = $auction->get->due_diligence_yn ?? '';
            $this->inspection_period_days = $auction->get->inspection_period_days ?? '';
            // Backward compat: if due_diligence_yn was never saved but inspection_period_days was, infer Yes
            if ($this->due_diligence_yn === '' && $this->inspection_period_days !== '') {
                $this->due_diligence_yn = 'Yes';
            }
            $this->inspection_period_other = $auction->get->inspection_period_other ?? '';
            $this->inspection_contingency_buyer = $auction->get->inspection_contingency_buyer ?? '';
            $this->inspection_contingency_period = $auction->get->inspection_contingency_period
                ?? $auction->get->inspection_period_days
                ?? '';
            $this->appraisal_contingency_buyer = $auction->get->appraisal_contingency_buyer ?? '';
            // Note: legacy "Waived" value is preserved as-is; blade renders a conditional
            // fallback option so existing records remain editable without data loss.
            $this->appraisal_contingency_days = $auction->get->appraisal_contingency_days ?? '';
            $this->financing_contingency_buyer = $auction->get->financing_contingency_buyer ?? '';
            $this->financing_contingency_days_buyer = $auction->get->financing_contingency_days_buyer ?? '';
            $this->financing_contingency_period = $auction->get->financing_contingency_period
                ?? $auction->get->financing_contingency_days_buyer
                ?? '';
            $this->seller_contribution = $auction->get->seller_contribution ?? '';
            $this->seller_contribution_details = $auction->get->seller_contribution_details ?? '';
            $this->possession_preference = $auction->get->possession_preference ?? '';
            $this->possession_preference_other = $auction->get->possession_preference_other ?? '';
            $this->possession_details = $auction->get->possession_details ?? '';
            $this->home_warranty_requested = $auction->get->home_warranty_requested ?? '';
            $this->home_warranty_details = $auction->get->home_warranty_details ?? '';
            $this->as_is_purchase = $auction->get->as_is_purchase ?? '';
            $this->property_inclusions = $auction->get->property_inclusions ?? '';
            $this->property_exclusions = $auction->get->property_exclusions ?? '';
            $this->closing_cost_responsibility = $auction->get->closing_cost_responsibility ?? '';
            $this->additional_purchase_terms = $auction->get->additional_purchase_terms ?? '';
            $this->home_sale_contingency = $auction->get->home_sale_contingency ?? '';
            $this->home_sale_contingency_period = $auction->get->home_sale_contingency_period ?? '';
            $this->home_sale_contingency_address = $auction->get->home_sale_contingency_address ?? '';
            $this->home_sale_contingency_date = $auction->get->home_sale_contingency_date ?? '';
            $this->home_sale_contingency_under_contract = $auction->get->home_sale_contingency_under_contract ?? '';
            $this->home_sale_contingency_details = $auction->get->home_sale_contingency_details ?? '';

            // All data loaded; clear the flag so subsequent updated* hooks run normally
            $this->isLoadingData = false;

            // Dispatch browser event to sync Select2 elements after loading.
            // The JS handler uses @this.get() directly; payload is included for
            // clarity and to ensure the event fires with the correct data snapshot.
            $this->dispatchBrowserEvent('buyer-agent-select2-sync', [
                'view_preference'           => $this->view_preference,
                'other_preferences'         => $this->other_preferences,
                'non_negotiable_amenities'  => $this->non_negotiable_amenities,
                'offered_financing'         => $this->offered_financing,
                'sale_provision'            => $this->sale_provision,
                'garage_parking_spaces_option' => $this->garage_parking_spaces_option,
                'assets'                    => $this->assets,
                'property_items'            => $this->property_items,
                'condition_prop_buyer'      => $this->condition_prop_buyer,
                'pool_type'                 => $this->pool_type,
                'lease_for'                 => $this->lease_for,
                'credit_scroe_rating'       => $this->credit_scroe_rating,
                'number_of_unit_type'       => $this->number_of_unit_type,
                'flood_zone_tolerance'       => $this->flood_zone_tolerance,
                'flood_zone_tolerance_other' => $this->flood_zone_tolerance_other,
                'purchase_purpose_other'    => $this->purchase_purpose_other,
                // New fields — not Select2, but included so JS can restore wrapper visibility
                'earnest_money_type'        => $this->earnest_money_type,
                'due_diligence_yn'          => $this->due_diligence_yn,
                'appraisal_contingency_days' => $this->appraisal_contingency_days,
                'possession_preference_other' => $this->possession_preference_other,
                'financing_contingency_period' => $this->financing_contingency_period,
            ]);
            
            // Note: isLoadingData flag will be cleared by updatedOfferedFinancing() 
            // when the Select2 sync triggers @this.set()

            // Load enable checkboxes
            // $enableFields = json_decode($auction->get->enable);
            // foreach ($enableFields as $field => $value) {
            //     if (property_exists($this, 'enable') && array_key_exists($field, $this->enable)) {
            //         $this->enable[$field] = $value;
            //     }
            // }
        }
    }

    protected function stripCommas($value)
    {
        if ($value === null || $value === '') {
            return $value;
        }
        return str_replace(',', '', $value);
    }

    /**
     * 9B-3: mirror the Search Areas blob's state/counties into the discrete $state/$counties
     * props. Called before validation (the discrete Acceptable State/Counties UI was removed
     * in 9B-3, so the blob is now the editing surface) and again before the discrete saveMeta
     * write-back. Non-empty guards preserve backward compatibility — an empty blob value never
     * wipes an existing discrete value.
     */
    protected function hydrateDiscreteLocationFromBlob(): void
    {
        $ldna = json_decode($this->location_dna_preferences_json ?? '', true);
        if (!is_array($ldna)) {
            return;
        }
        if (trim((string) ($ldna['state'] ?? '')) !== '') {
            $this->state = trim((string) $ldna['state']);
        }
        if (!empty($ldna['counties'] ?? [])) {
            $this->counties = array_values(array_filter(
                (array) $ldna['counties'],
                fn($c) => is_string($c) && trim($c) !== ''
            ));
        }
    }

    protected function saveAllMetadata($auction)
    {
        $auction->saveMeta('workflow_type', 'offer_listing');
        $auction->saveMeta('user_type', $this->user_type);
        $auction->saveMeta('listing_status', $this->listing_status);
        $auction->saveMeta('meeting_Preference', $this->meeting_Preference);
        $auction->saveMeta('auction_type', $this->auction_type);
        $auction->saveMeta('working_with_agent', $this->working_with_agent);
        $auction->saveMeta('listing_date', $this->listing_date);
        $auction->saveMeta('desired_agent_hire_date', $this->desired_agent_hire_date);
        $auction->saveMeta('expiration_date', $this->expiration_date);
        $auction->saveMeta('auction_time', $this->auction_type === 'Bidding Period' ? $this->auction_time : '');

        // Location Information
        // 9B-2 write-back: mirror the Search Areas blob's state / counties into the
        // discrete meta (read by Ask AI, public views, the match engine). Non-empty
        // guards keep this backward compatible — an empty blob value never wipes an
        // existing discrete value (the discrete UI was removed in 9B-3).
        $this->hydrateDiscreteLocationFromBlob();
        $auction->saveMeta('counties', json_encode($this->counties));
        $auction->saveMeta('state', $this->state);

        // 9C Important Places — additive, separate meta key; commute fields untouched.
        $this->saveImportantPlaces($auction);

        // Property Details
        $auction->saveMeta('property_type', $this->property_type);
        $auction->saveMeta('property_items', json_encode($this->property_items));
        $auction->saveMeta('leasing_space', $this->leasing_space);
        $auction->saveMeta('other_property_items', $this->other_property_items);
        $auction->saveMeta('condition_prop', $this->condition_prop);
        $auction->saveMeta('condition_prop_buyer', json_encode($this->condition_prop_buyer));
        $auction->saveMeta('other_property_condition', $this->other_property_condition);
        $auction->saveMeta('bathrooms', $this->bathrooms);
        $auction->saveMeta('other_bathrooms', $this->other_bathrooms);
        $auction->saveMeta('bedrooms', $this->bedrooms);
        $auction->saveMeta('other_bedrooms', $this->other_bedrooms);
        $auction->saveMeta('minimum_heated_square', $this->stripCommas($this->minimum_heated_square));
        $auction->saveMeta('minimum_leaseable', $this->stripCommas($this->minimum_leaseable));
        $auction->saveMeta('min_acreage', $this->stripCommas($this->min_acreage));
        $auction->saveMeta('total_acreage', $this->total_acreage);
        $auction->saveMeta('minimum_cap_rate', $this->stripCommas($this->minimum_cap_rate));
        $auction->saveMeta('assets', json_encode($this->assets ?? []));
        $auction->saveMeta('assets_other', $this->assets_other);
        $auction->saveMeta('property_criteria', $this->property_criteria);
        $auction->saveMeta('unit_size', $this->unit_size);
        $auction->saveMeta('unit_size_other', $this->unit_size_other);
        $auction->saveMeta('preferance_details', $this->preferance_details);

        // Property DNA Phase C — Buyer Tier 1 EAV fields
        $auction->saveMeta('purchase_purpose', $this->purchase_purpose);
        $auction->saveMeta('purchase_purpose_other', $this->purchase_purpose_other);
        $auction->saveMeta('commute_destination_zip', $this->commute_destination_zip);
        $auction->saveMeta('max_commute_minutes', $this->max_commute_minutes);
        $auction->saveMeta('commute_mode', $this->commute_mode);
        $auction->saveMeta('hoa_acceptance', $this->hoa_acceptance);
        $auction->saveMeta('hoa_max_monthly_fee', $this->hoa_max_monthly_fee);
        $auction->saveMeta('flood_zone_tolerance', json_encode($this->flood_zone_tolerance ?? []));
        $auction->saveMeta('flood_zone_tolerance_other', $this->flood_zone_tolerance_other ?? '');

        // Sale Provisions
        $auction->saveMeta('sale_provision', json_encode($this->sale_provision ?? []));
        $auction->saveMeta('sale_provision_other', $this->sale_provision_other);
        $auction->saveMeta('sale_provision_assignment', $this->sale_provision_assignment);
        $auction->saveMeta('assignment_fee_type', $this->assignment_fee_type);
        $auction->saveMeta('assignment_fee_amount', $this->stripCommas($this->assignment_fee_amount));
        $auction->saveMeta('buyer_sell_contract', $this->buyer_sell_contract);

        // Budget & Financing
        $auction->saveMeta('maximum_budget', $this->stripCommas($this->maximum_budget));
        $auction->saveMeta('offered_financing', json_encode($this->offered_financing));
        $auction->saveMeta('other_financing', $this->other_financing);
        $auction->saveMeta('cash_budget', $this->stripCommas($this->cash_budget));
        $auction->saveMeta('pre_approved', $this->pre_approved);
        $auction->saveMeta('pre_approval_amount', $this->stripCommas($this->pre_approval_amount));
        $auction->saveMeta('purchase_price', $this->stripCommas($this->purchase_price));
        $auction->saveMeta('down_payment_type', $this->down_payment_type);
        $auction->saveMeta('down_payment_amount', $this->stripCommas($this->down_payment_amount));
        $auction->saveMeta('seller_financing_type', $this->seller_financing_type);
        $auction->saveMeta('seller_financing_amount', $this->stripCommas($this->seller_financing_amount));
        $auction->saveMeta('interest_rate', $this->stripCommas($this->interest_rate));
        $auction->saveMeta('loan_duration', $this->loan_duration);
        $auction->saveMeta('prepayment_penalty', $this->prepayment_penalty);
        $auction->saveMeta('prepayment_penalty_amount', $this->stripCommas($this->prepayment_penalty_amount));
        $auction->saveMeta('balloon_payment', $this->balloon_payment);
        $auction->saveMeta('balloon_payment_amount', $this->stripCommas($this->balloon_payment_amount));
        $auction->saveMeta('balloon_payment_date', $this->balloon_payment_date);
        $auction->saveMeta('assumable_interest', $this->assumable_interest);
        $auction->saveMeta('assumable_max_interest_rate', $this->stripCommas($this->assumable_max_interest_rate));
        $auction->saveMeta('assumable_max_monthly_payment', $this->stripCommas($this->assumable_max_monthly_payment));
        $auction->saveMeta('assumable_bridge_gap_cash', $this->stripCommas($this->assumable_bridge_gap_cash));
        $auction->saveMeta('assumption_fee_responsibility', $this->assumption_fee_responsibility);
        
        // Seller Financing Additional Fields
        $auction->saveMeta('seller_amortization_type', $this->seller_amortization_type);
        $auction->saveMeta('seller_amortization_other', $this->seller_amortization_other);
        $auction->saveMeta('seller_payment_frequency', $this->seller_payment_frequency);
        $auction->saveMeta('seller_payment_frequency_other', $this->seller_payment_frequency_other);
        $auction->saveMeta('seller_late_fee_amount', $this->stripCommas($this->seller_late_fee_amount));

        // Exchange / Trade
        $auction->saveMeta('exchange_item', $this->exchange_item);
        $auction->saveMeta('other_exchange_item', $this->other_exchange_item);
        $auction->saveMeta('exchange_item_value', $this->stripCommas($this->exchange_item_value));
        $auction->saveMeta('exchange_item_condition', $this->exchange_item_condition);
        $auction->saveMeta('additional_cash', $this->stripCommas($this->additional_cash));
        $auction->saveMeta('value_determination', $this->value_determination);
        $auction->saveMeta('exchange_transfer_method', $this->exchange_transfer_method);
        $auction->saveMeta('exchange_liens', $this->exchange_liens);
        $auction->saveMeta('exchange_liens_details', $this->exchange_liens_details);
        $auction->saveMeta('exchange_inspection_rights', $this->exchange_inspection_rights);

        // Lease Option
        $auction->saveMeta('interested_lease_option', $this->interested_lease_option);
        $auction->saveMeta('interested_lease_option_agreement', $this->interested_lease_option_agreement);
        $auction->saveMeta('lease_option_price', $this->stripCommas($this->lease_option_price));
        $auction->saveMeta('lease_option_terms', $this->lease_option_terms);
        $auction->saveMeta('lease_option_duration', $this->lease_option_duration);
        $auction->saveMeta('lease_option_payment', $this->stripCommas($this->lease_option_payment));
        $auction->saveMeta('lease_option_conditions', $this->lease_option_conditions);
        $auction->saveMeta('has_option_fee', $this->has_option_fee);
        $auction->saveMeta('option_fee_amount', $this->stripCommas($this->option_fee_amount));
        $auction->saveMeta('lease_option_fee_credit', $this->lease_option_fee_credit);
        $auction->saveMeta('lease_option_fee_credit_percentage', $this->stripCommas($this->lease_option_fee_credit_percentage));
        $auction->saveMeta('lease_option_maintenance', $this->lease_option_maintenance);
        $auction->saveMeta('lease_option_extension_terms', $this->lease_option_extension_terms);

        // Lease Purchase
        $auction->saveMeta('lease_purchase_price', $this->stripCommas($this->lease_purchase_price));
        $auction->saveMeta('lease_purchase_terms', $this->lease_purchase_terms);
        $auction->saveMeta('lease_purchase_duration', $this->lease_purchase_duration);
        $auction->saveMeta('lease_purchase_payment', $this->stripCommas($this->lease_purchase_payment));
        $auction->saveMeta('lease_purchase_conditions', $this->lease_purchase_conditions);
        $auction->saveMeta('lease_purchase_option_fee', $this->lease_purchase_option_fee);
        $auction->saveMeta('lease_purchase_option_fee_amount', $this->stripCommas($this->lease_purchase_option_fee_amount));
        $auction->saveMeta('lease_purchase_maintenance', $this->lease_purchase_maintenance);
        $auction->saveMeta('lease_purchase_extension_terms', $this->lease_purchase_extension_terms);
        $auction->saveMeta('lease_purchase_rent_credit', $this->lease_purchase_rent_credit);
        $auction->saveMeta('lease_purchase_rent_credit_amount', $this->stripCommas($this->lease_purchase_rent_credit_amount));
        $auction->saveMeta('lease_purchase_deposit', $this->stripCommas($this->lease_purchase_deposit));

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
        $auction->saveMeta('nft_percentage', $this->stripCommas($this->nft_percentage));
        $auction->saveMeta('cash_percentage_nft', $this->stripCommas($this->cash_percentage_nft));
        $auction->saveMeta('nft_valuation_method', $this->nft_valuation_method);
        $auction->saveMeta('nft_transfer_method', $this->nft_transfer_method);
        $auction->saveMeta('nft_gas_fees', $this->nft_gas_fees);

        // Amenities and Features
        $auction->saveMeta('tenant_require', json_encode($this->tenant_require));
        $auction->saveMeta('carport_needed', $this->carport_needed);
        $auction->saveMeta('other_carport_needed', $this->other_carport_needed);
        $auction->saveMeta('garage_needed', $this->garage_needed);
        $auction->saveMeta('other_garage_needed', $this->other_garage_needed);
        $auction->saveMeta('garage_parking_spaces', $this->garage_parking_spaces);
        $auction->saveMeta('garage_parking_spaces_option', json_encode($this->garage_parking_spaces_option ?? []));
        $auction->saveMeta('other_parking_space_wrapper', $this->other_parking_space_wrapper);
        $auction->saveMeta('pool_needed', $this->pool_needed);
        $auction->saveMeta('pool_type', json_encode($this->pool_type));
        $auction->saveMeta('view_preference', json_encode($this->view_preference));
        $auction->saveMeta('other_preferences', $this->other_preferences);
        $auction->saveMeta('real_estate_purchase', $this->real_estate_purchase);
        $auction->saveMeta('number_of_unit', $this->number_of_unit);
        $auction->saveMeta('number_of_unit_other', $this->number_of_unit_other);
        $auction->saveMeta('number_of_unit_type', json_encode($this->number_of_unit_type));
        $auction->saveMeta('number_of_unit_type_other', $this->number_of_unit_type_other);
        $auction->saveMeta('business_type_selected', json_encode($this->business_type_selected ?? []));
        $auction->saveMeta('other_business_type', $this->other_business_type ?? '');
        $auction->saveMeta('minimum_annual_net_income', $this->stripCommas($this->minimum_annual_net_income));
        $auction->saveMeta('leasing_55_plus', $this->leasing_55_plus);

        // Requirements
        $auction->saveMeta('non_negotiable_amenities', json_encode($this->non_negotiable_amenities));
        $auction->saveMeta('other_non_negotiable_amenities', $this->other_non_negotiable_amenities);
        $auction->saveMeta('budget', $this->budget);

        // Missing Buyer Agent fields
        $auction->saveMeta('target_closing_date', $this->target_closing_date);
        $auction->saveMeta('service_animal', $this->service_animal);
        $auction->saveMeta('emotional_support_animal', $this->emotional_support_animal);
        $auction->saveMeta('occupant_types', $this->occupant_types);

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
        $auction->saveMeta('monthly_income', $this->stripCommas($this->monthly_income));
        $auction->saveMeta('number_occupant', $this->number_occupant);

        // Services
        $auction->saveMeta('other_services', $this->other_services);
        $auction->saveMeta('other_services_enabled', $this->other_services_enabled);
        $auction->saveMeta('additional_details', $this->additional_details);

        // Broker Compensation
        $auction->saveMeta('commission_structure', $this->commission_structure);
        $auction->saveMeta('lease_type', $this->lease_type);
        $auction->saveMeta('lease_type_other', $this->lease_type_other);
        $auction->saveMeta('lease_value', $this->lease_value);

        // Lease Fee
        $auction->saveMeta('lease_fee_type', $this->lease_fee_type);
        $auction->saveMeta('lease_fee_flat', $this->stripCommas($this->lease_fee_flat));
        $auction->saveMeta('lease_fee_percentage', $this->stripCommas($this->lease_fee_percentage));
        $auction->saveMeta('lease_fee_months', $this->lease_fee_months);
        $auction->saveMeta('lease_fee_percentage_monthly_rent', $this->stripCommas($this->lease_fee_percentage_monthly_rent));
        $auction->saveMeta('lease_fee_flat_combo', $this->stripCommas($this->lease_fee_flat_combo));
        $auction->saveMeta('lease_fee_percentage_combo', $this->stripCommas($this->lease_fee_percentage_combo));
        $auction->saveMeta('lease_fee_other', $this->lease_fee_other);
        $auction->saveMeta('lease_fee_flat_combo_net', $this->stripCommas($this->lease_fee_flat_combo_net));
        $auction->saveMeta('lease_fee_percentage_combo_net', $this->stripCommas($this->lease_fee_percentage_combo_net));
        $auction->saveMeta('lease_fee_percentage_monthly_number', $this->stripCommas($this->lease_fee_percentage_monthly_number));
        $auction->saveMeta('lease_fee_percentage_net', $this->stripCommas($this->lease_fee_percentage_net));
        $auction->saveMeta('lease_option_consideration', $this->stripCommas($this->lease_option_consideration));
        $auction->saveMeta('additional_details_broker', $this->additional_details_broker);

        // Purchase Fee
        $auction->saveMeta('purchase_type', $this->purchase_type);
        $auction->saveMeta('purchase_value', $this->purchase_value);
        $auction->saveMeta('purchase_pice_commercial', $this->stripCommas($this->purchase_pice_commercial));
        $auction->saveMeta('purchase_fee_flat_exercised', $this->stripCommas($this->purchase_fee_flat_exercised));
        $auction->saveMeta('purchase_fee_type', $this->purchase_fee_type);
        $auction->saveMeta('purchase_fee_percentage', $this->stripCommas($this->purchase_fee_percentage));
        $auction->saveMeta('purchase_fee_flat', $this->stripCommas($this->purchase_fee_flat));
        $auction->saveMeta('purchase_fee_percentage_combo', $this->stripCommas($this->purchase_fee_percentage_combo));
        $auction->saveMeta('purchase_fee_flat_combo', $this->stripCommas($this->purchase_fee_flat_combo));
        $auction->saveMeta('purchase_fee_other', $this->purchase_fee_other);

        // Lease-Option Fee
        $auction->saveMeta('lease_option_fee_type', $this->lease_option_fee_type);
        $auction->saveMeta('lease_option_fee_flat', $this->stripCommas($this->lease_option_fee_flat));
        $auction->saveMeta('lease_option_fee_percentage', $this->stripCommas($this->lease_option_fee_percentage));
        $auction->saveMeta('lease_option_fee_other', $this->lease_option_fee_other);
        $auction->saveMeta('lease_option_fee_flat_combo', $this->stripCommas($this->lease_option_fee_flat_combo));
        $auction->saveMeta('lease_option_fee_percentage_combo', $this->stripCommas($this->lease_option_fee_percentage_combo));

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

        // 2nd tab limited services
        // Meeting details
        $auction->saveMeta('person_meeting', $this->person_meeting);
        $auction->saveMeta('meeting_details_first_name', $this->meeting_details_first_name);
        $auction->saveMeta('meeting_details_last_name', $this->meeting_details_last_name);
        $auction->saveMeta('meeting_details_phone', $this->meeting_details_phone);
        $auction->saveMeta('meeting_details_email', $this->meeting_details_email);


        // Meeting details yes
        $auction->saveMeta('address', $this->address);
        $auction->saveMeta('unit_number', $this->unit_number);
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
        $auction->saveMeta('list_criteria_fee', $this->stripCommas($this->list_criteria_fee));
        $auction->saveMeta('market_groups', $this->market_groups);
        $auction->saveMeta('market_groups_fee', $this->stripCommas($this->market_groups_fee));
        $auction->saveMeta('promote_social', $this->promote_social);
        $auction->saveMeta('promote_social_fee', $this->stripCommas($this->promote_social_fee));
        $auction->saveMeta('launch_ads', $this->launch_ads);
        $auction->saveMeta('launch_ads_fee', $this->stripCommas($this->launch_ads_fee));
        $auction->saveMeta('include_marketing_fee', $this->include_marketing_fee);
        $auction->saveMeta('marketing_materials_fee', $this->stripCommas($this->marketing_materials_fee));

        $auction->saveMeta('email_notifications_fee', $this->stripCommas($this->email_notifications_fee));
        $auction->saveMeta('off_market_search_fee', $this->stripCommas($this->off_market_search_fee));
        $auction->saveMeta('mls_filter_fee', $this->stripCommas($this->mls_filter_fee));
        $auction->saveMeta('email_marketing_fee', $this->stripCommas($this->email_marketing_fee));

        // Property Showings
        $auction->saveMeta('schedule_showings', $this->schedule_showings);
        $auction->saveMeta('number_of_showings_to_schedule', $this->number_of_showings_to_schedule);
        $auction->saveMeta('schedule_showings_fee', $this->stripCommas($this->schedule_showings_fee));
        $auction->saveMeta('attend_showings', $this->attend_showings);
        $auction->saveMeta('number_of_showings_to_attend', $this->number_of_showings_to_attend);
        $auction->saveMeta('attend_showings_fee', $this->stripCommas($this->attend_showings_fee));
        $auction->saveMeta('provide_virtual_tours', $this->provide_virtual_tours);
        $auction->saveMeta('number_of_virtual_tours', $this->number_of_virtual_tours);
        $auction->saveMeta('virtual_tours_fee', $this->stripCommas($this->virtual_tours_fee));

        // Application & Lease Support
        $auction->saveMeta('assist_application', $this->assist_application);
        $auction->saveMeta('assist_application_fee', $this->stripCommas($this->assist_application_fee));
        $auction->saveMeta('collect_documents', $this->collect_documents);
        $auction->saveMeta('collect_documents_fee', $this->stripCommas($this->collect_documents_fee));
        $auction->saveMeta('submit_application', $this->submit_application);
        $auction->saveMeta('submit_application_fee', $this->stripCommas($this->submit_application_fee));
        $auction->saveMeta('review_lease', $this->review_lease);
        $auction->saveMeta('review_lease_fee', $this->stripCommas($this->review_lease_fee));
        $auction->saveMeta('provide_lease_form', $this->provide_lease_form);
        $auction->saveMeta('provide_lease_form_fee', $this->stripCommas($this->provide_lease_form_fee));
        $auction->saveMeta('coordinate_signing', $this->coordinate_signing);
        $auction->saveMeta('coordinate_signing_fee', $this->stripCommas($this->coordinate_signing_fee));
        $auction->saveMeta('prepare_application_fee', $this->stripCommas($this->prepare_application_fee));

        // Move Services
        $auction->saveMeta('move_in_inspection_fee', $this->stripCommas($this->move_in_inspection_fee));
        $auction->saveMeta('moving_resources_fee', $this->stripCommas($this->moving_resources_fee));
        $auction->saveMeta('short_term_housing_fee', $this->stripCommas($this->short_term_housing_fee));

        // Advisory Services
        $auction->saveMeta('rental_rights_fee', $this->stripCommas($this->rental_rights_fee));
        $auction->saveMeta('lease_advice_fee', $this->stripCommas($this->lease_advice_fee));

        // Neighborhood Marketing
        $auction->saveMeta('neighborhood_insights_fee', $this->stripCommas($this->neighborhood_insights_fee));
        $auction->saveMeta('neighborhood_marketing_fee', $this->stripCommas($this->neighborhood_marketing_fee));
        $auction->saveMeta('neighborhood_materials_fee', $this->stripCommas($this->neighborhood_materials_fee));



        $auction->saveMeta('custom_services', json_encode($this->custom_services));
        $auction->saveMeta('total_marketing_fee', $this->stripCommas($this->total_marketing_fee));
        $auction->saveMeta('total_flat_fee', $this->stripCommas($this->total_flat_fee));


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

        // Purchase Terms Questions
        $auction->saveMeta('earnest_money_amount', $this->earnest_money_amount);
        $auction->saveMeta('earnest_money_type', $this->earnest_money_type);
        $auction->saveMeta('earnest_money_timing', $this->earnest_money_timing);
        $auction->saveMeta('due_diligence_yn', $this->due_diligence_yn);
        $auction->saveMeta('inspection_period_days', $this->inspection_period_days);
        $auction->saveMeta('inspection_period_other', $this->inspection_period_other);
        $auction->saveMeta('inspection_contingency_buyer', $this->inspection_contingency_buyer);
        $auction->saveMeta('inspection_contingency_period', $this->inspection_contingency_period);
        $auction->saveMeta('appraisal_contingency_buyer', $this->appraisal_contingency_buyer);
        $auction->saveMeta('appraisal_contingency_days', $this->appraisal_contingency_days);
        $auction->saveMeta('financing_contingency_buyer', $this->financing_contingency_buyer);
        $auction->saveMeta('financing_contingency_days_buyer', $this->financing_contingency_period ?: $this->financing_contingency_days_buyer);
        $auction->saveMeta('financing_contingency_period', $this->financing_contingency_period);
        $auction->saveMeta('seller_contribution', $this->seller_contribution);
        $auction->saveMeta('seller_contribution_details', $this->seller_contribution_details);
        $auction->saveMeta('possession_preference', $this->possession_preference);
        $auction->saveMeta('possession_preference_other', $this->possession_preference_other);
        $auction->saveMeta('possession_details', $this->possession_details);
        $auction->saveMeta('home_warranty_requested', $this->home_warranty_requested);
        $auction->saveMeta('home_warranty_details', $this->home_warranty_details);
        $auction->saveMeta('as_is_purchase', $this->as_is_purchase);
        $auction->saveMeta('property_inclusions', $this->property_inclusions);
        $auction->saveMeta('property_exclusions', $this->property_exclusions);
        $auction->saveMeta('closing_cost_responsibility', $this->closing_cost_responsibility);
        $auction->saveMeta('additional_purchase_terms', $this->additional_purchase_terms);
        $auction->saveMeta('home_sale_contingency', $this->home_sale_contingency);
        $auction->saveMeta('home_sale_contingency_period', $this->home_sale_contingency_period);
        $auction->saveMeta('home_sale_contingency_address', $this->home_sale_contingency_address);
        $auction->saveMeta('home_sale_contingency_date', $this->home_sale_contingency_date);
        $auction->saveMeta('home_sale_contingency_under_contract', $this->home_sale_contingency_under_contract);
        $auction->saveMeta('home_sale_contingency_details', $this->home_sale_contingency_details);

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
        $auction->saveMeta('location_dna_preferences', $this->location_dna_preferences_json);
        // Keep `cities` meta in sync with the LDNA blob.
        $ldnaDecoded = json_decode($this->location_dna_preferences_json, true);
        $auction->saveMeta('cities', json_encode($ldnaDecoded['cities'] ?? []));

        // Save photo - only process if it's a new upload (UploadedFile), not an existing string path
        if ($this->photo && !is_string($this->photo)) {
            $extensionPhoto = $this->photo->getClientOriginalExtension(); // Get file extension
            $uuid = (string) Str::uuid(); // Generate a unique UUID
            $photoName = $uuid . '.' . $extensionPhoto; // Create a unique file name

            // Save file to public/auction/images using Livewire's store method
            $photoPath = $this->photo->storeAs('auction/images', $photoName, 'public');

            // Save file name to database
            $auction->saveMeta('photo', $photoName);
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

    public function update()
    {
        \Log::info('[BUYER UPDATE START]', [
            'user_id' => auth()->id(),
            'listing_date' => $this->listing_date ?? null,
            'auction_type' => $this->auction_type ?? null,
            'counties' => $this->counties ?? [],
            'state' => $this->state ?? null,
        ]);

        try {
            // 9B-3: hydrate state/counties from the Search Areas blob before validation,
            // since the discrete Acceptable State/Counties inputs were removed.
            $this->hydrateDiscreteLocationFromBlob();

            // Validate required fields: Counties and State are required, Cities are optional
            $validationRules = [
                'counties' => 'required|array|min:1',
                'state' => 'required|string',
            ];
            
            // Add Bidding Period specific validation
            if ($this->auction_type === 'Bidding Period') {
                $validationRules['auction_time'] = 'required|string';
            }
            
            $this->validate($validationRules, [
                'counties.required' => 'At least one county is required.',
                'counties.min' => 'At least one county is required.',
                'state.required' => 'State is required.',
                'auction_time.required' => 'Bidding Period Length is required.',
            ]);

            // 9C: block submit when any Important Place row is partially completed.
            $this->assertImportantPlacesValid();

            $this->isDraft = 0;

            $auction =$this->auctionId
                ? HireBuyerAgentAuction::find($this->auctionId)
                : new HireBuyerAgentAuction();
            $auction->title = $this->listing_title;
            $auction->is_draft = 0;
            $auction->save();

            $this->listingId = $auction->id;

            $this->saveAllMetadata($auction);

            app(\App\Services\AskAi\AskAiKnowledgeSnapshotBuilderService::class)->buildSilently('buyer', $this->listingId);

            \Log::info('[BUYER LISTING UPDATED]', [
                'record_id' => $auction->id,
                'listing_id' => $auction->listing_id ?? 'N/A',
                'user_id' => $auction->user_id,
                'is_draft' => $auction->is_draft,
                'is_approved' => $auction->is_approved,
                'is_sold' => $auction->is_sold,
            ]);

            app(WizardEventService::class)->record(
                (string) $this->user_type,
                $this->listingId ? (int) $this->listingId : null,
                auth()->id() ? (int) auth()->id() : null,
                'submit',
                'tab_' . $this->activeTab,
                'edit',
                session()->getId()
            );
            session()->flash('success', 'Listing updated successfully!');

            return redirect()->route('offer.listing.buyer.view', ['id' => $auction->id]);

        } catch (\Exception $e) {
            \Log::error('[BUYER UPDATE ERROR]', ['error' => $e->getMessage()]);
            session()->flash('error', 'Error saving listing: ' . $e->getMessage());
        }
    }


}
