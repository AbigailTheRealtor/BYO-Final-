<?php

namespace App\Http\Livewire\HireBuyerAgent;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\BuyerAgentAuction as HireBuyerAgentAuction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\UsState;
use App\Models\UsCounty;
use App\Models\UsCity;

class BuyerAgentAuction extends Component
{
    use WithFileUploads;


    // Livewire properties for form fields
    public $hasDrafts = false;
    public $auctionId; // To store the auction ID for editing

    public $listingId = null; // To track existing listings
    public $isDraft = false; // To track draft status
    public $service_type = 'full_service'; // 'full_service' or 'limited_service'
    public $listing_status = 'Active'; // 'Active', 'Pending', or 'Hired Agent'

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
    public $assignment_fee_type = '';
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
    public $balloon_payment = '';
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
    public $interested_lease_option = '';
    public $interested_lease_option_agreement = '';
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
    public $crypto_transfer_timing = '';
    public $crypto_transfer_timing_other = '';

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
    public $number_of_unit_type = [];
    public $number_of_unit_type_other = '';
    public $business_type_selected = '';
    public $other_business_type = '';

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
    public $video_link = '';
    public $embedUrl;



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
    public $cities = [];
    public $newCity = '';
    public $counties = [];
    public $newCounty = '';
    public $citySuggestions = [];
    public $countySuggestions = [];
    public $stateSuggestions = [];

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

    public function updatedOfferedFinancing()
    {
        // Reset all dependent fields when financing type changes
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
        $this->reset(['assignment_fee_amount', 'buyer_sell_contract']);
    }

    public function updatedBuyerSellContract()
    {
        $this->reset(['assignment_fee_amount']);
    }

    public function updatedPurchaseFeeType()
    {
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


    // Methods
    public function mount($listingId = null)
    {
        $this->addService();

        // Set listing_date to today's date by default (only if creating new listing)
        // loadDraft() will overwrite this with the saved value if loading a draft
        $this->listing_date = now()->format('Y-m-d');

        // Check for existing drafts
        $this->hasDrafts = HireBuyerAgentAuction::where('user_id', Auth::id())
            ->where('is_draft', true)
            ->exists();

        if ($listingId) {
            $this->loadDraft($listingId);
        }
        
        // Emit initial state for frontend validation
        $this->dispatchBrowserEvent('buyer-state-init', [
            'countiesCount' => count($this->counties ?? []),
            'auctionType' => $this->auction_type ?? ''
        ]);
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
        if ($type === 'state') {
            return $this->getStateSuggestionsFromDb($input);
        } elseif ($type === 'county') {
            return $this->getCountySuggestionsFromDb($input);
        } elseif ($type === 'city') {
            return $this->getCitySuggestionsFromDb($input);
        } elseif ($type === 'address') {
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
        $cities = UsCity::with('state')
            ->where('name', 'ILIKE', '%' . $input . '%')
            ->limit(10)
            ->get();

        return $cities->map(function ($city) {
            return $city->name . ', ' . ($city->state ? $city->state->abbreviation : '');
        })->toArray();
    }

    protected function getPlaceSuggestionsFromApi($input, $type = null)
    {
        $client = new \GuzzleHttp\Client();

        $query = [
            'input' => $input,
            'components' => 'country:us',
            'key' => env('GOOGLE_PLACES_API_KEY')
        ];

        if ($type === 'address') {
            $query['types'] = 'address';
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

    public function selectCountySuggestion($suggestion = null)
    {
        $suggestion = $suggestion ?? $this->countySuggestions[$this->highlightedCountyIndex] ?? $this->newCounty;
        $this->newCounty = $suggestion;

        if (!in_array(trim($suggestion), $this->counties)) {
            $this->addCounty();
        }

        $this->countySuggestions = [];
        $this->highlightedCountyIndex = -1;
        
        $this->autoPopulateStateFromCounty($suggestion);
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

        return view('livewire.hire-buyer-agent.hire-buyer-agent')->extends('layouts.main')->section('content'); // Define the section
    }
    public function saveDraft()
    {

        try {


            $this->isDraft = true;

            $auction = $this->listingId
                ? HireBuyerAgentAuction::find($this->listingId)
                : new HireBuyerAgentAuction();

            $auction->user_id = Auth::id();
            $auction->title = $this->listing_title;
            $auction->is_draft = true;
            $auction->save();

            $this->listingId = $auction->id;

            $this->saveAllMetadata($auction);

            \Log::info('[BUYER DRAFT SAVED]', [
                'record_id' => $auction->id,
                'listing_id' => $auction->listing_id ?? 'N/A',
                'user_id' => $auction->user_id,
                'is_draft' => $auction->is_draft,
                'is_approved' => $auction->is_approved,
            ]);

            $displayId = $auction->listing_id ?? $auction->id;
            session()->flash('success', "Draft saved (Listing ID: {$displayId}). You can return later to complete your listing.");
        } catch (\Exception $e) {
            session()->flash('error', 'Error saving draft: ' . $e->getMessage());
        }
    }

    public function loadDraft($listingId)
    {
        $auction = HireBuyerAgentAuction::where('id', $listingId)
            ->where('user_id', Auth::id())
            ->first();

        if ($auction) {

            // Load all metadata fields
            $this->listing_title = $auction->title ?? '';
            $this->service_type = $auction->get->service_type ?? '';
            $this->user_type = $auction->get->user_type ?? 'buyer';
            $this->listing_status = $auction->get->listing_status ?? 'Active';
            $this->auction_type = $auction->get->auction_type ?? '';
            $this->working_with_agent = $auction->get->working_with_agent ?? '';
            $this->listing_date = $auction->get->listing_date ?? '';
            $this->desired_agent_hire_date = $auction->get->desired_agent_hire_date ?? '';
            $this->expiration_date = $auction->get->expiration_date ?? '';
            $this->auction_time = $auction->get->auction_time ?? '';

            $this->state = $auction->get->state ?? '';
            $this->property_type = $auction->get->property_type ?? '';
            $citiesRaw = $auction->get->cities ?? null;
            $this->cities = $citiesRaw ? (is_string($citiesRaw) ? json_decode($citiesRaw, true) ?? [] : (array)$citiesRaw) : [];
            $countiesRaw = $auction->get->counties ?? null;
            $this->counties = $countiesRaw ? (is_string($countiesRaw) ? json_decode($countiesRaw, true) ?? [] : (array)$countiesRaw) : [];

            // Property details
            $propertyItemsRaw = $auction->get->property_items ?? null;
            $this->property_items = $propertyItemsRaw ? (is_string($propertyItemsRaw) ? json_decode($propertyItemsRaw, true) ?? [] : (array)$propertyItemsRaw) : [];

            $this->other_property_items = $auction->get->other_property_items ?? '';
            $this->condition_prop = $auction->get->condition_prop ?? '';
            $conditionPropBuyerRaw = $auction->get->condition_prop_buyer ?? null;
            $this->condition_prop_buyer = $conditionPropBuyerRaw ? (is_string($conditionPropBuyerRaw) ? json_decode($conditionPropBuyerRaw, true) ?? [] : (array)$conditionPropBuyerRaw) : [];
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
            $this->assets = $auction->get->assets ?? '';
            $this->assets_other = $auction->get->assets_other ?? '';
            $this->property_criteria = $auction->get->property_criteria ?? '';
            $this->unit_size = $auction->get->unit_size ?? '';
            $this->unit_size_other = $auction->get->unit_size_other ?? '';
            $this->preferance_details = $auction->get->preferance_details ?? '';


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
            $this->assumable_terms = $auction->get->assumable_terms ?? '';
            $this->max_assumable_rate = $auction->get->max_assumable_rate ?? '';
            $this->max_monthly_payment = $auction->get->max_monthly_payment ?? '';
            $this->gap_payment_type = $auction->get->gap_payment_type ?? '$';
            $this->gap_payment_amount = $auction->get->gap_payment_amount ?? '';

            // Exchange/Trade
            $this->exchange_item = $auction->get->exchange_item ?? '';
            $this->other_exchange_item = $auction->get->other_exchange_item ?? '';
            $this->exchange_item_value = $auction->get->exchange_item_value ?? '';
            $this->exchange_item_condition = $auction->get->exchange_item_condition ?? '';
            $this->additional_cash = $auction->get->additional_cash ?? '';
            $this->value_determination = $auction->get->value_determination ?? '';

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

            // Lease Purchase
            $this->lease_purchase_price = $auction->get->lease_purchase_price ?? '';
            $this->lease_purchase_terms = $auction->get->lease_purchase_terms ?? '';
            $this->lease_purchase_duration = $auction->get->lease_purchase_duration ?? '';
            $this->lease_purchase_payment = $auction->get->lease_purchase_payment ?? '';
            $this->lease_purchase_conditions = $auction->get->lease_purchase_conditions ?? '';
            $this->lease_purchase_option_fee = $auction->get->lease_purchase_option_fee ?? '';
            $this->lease_purchase_option_fee_amount = $auction->get->lease_purchase_option_fee_amount ?? '';

            // Cryptocurrency
            $this->cryptocurrency_type = $auction->get->cryptocurrency_type ?? '';
            $this->crypto_percentage = $auction->get->crypto_percentage ?? '';
            $this->cash_percentage_crypto = $auction->get->cash_percentage_crypto ?? '';
            $this->crypto_transfer_timing = $auction->get->crypto_transfer_timing ?? '';
            $this->crypto_transfer_timing_other = $auction->get->crypto_transfer_timing_other ?? '';

            // NFT
            $this->nft_description = $auction->get->nft_description ?? '';
            $this->nft_percentage = $auction->get->nft_percentage ?? '';
            $this->cash_percentage_nft = $auction->get->cash_percentage_nft ?? '';

            // Amenities and features
            $tenantRequireRaw = $auction->get->tenant_require ?? null;
            $this->tenant_require = $tenantRequireRaw ? (is_string($tenantRequireRaw) ? json_decode($tenantRequireRaw, true) ?? [] : (array)$tenantRequireRaw) : [];

            $this->carport_needed = $auction->get->carport_needed ?? '';
            $this->other_carport_needed = $auction->get->other_carport_needed ?? '';
            $this->garage_needed = $auction->get->garage_needed ?? '';
            $this->other_garage_needed = $auction->get->other_garage_needed ?? '';
            $this->garage_parking_spaces = $auction->get->garage_parking_spaces ?? '';
            $this->garage_parking_spaces_option = $auction->get->garage_parking_spaces_option ?? '';
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
            $this->number_of_unit_type_other = $auction->get->number_of_unit_type_other ?? '';
            $this->minimum_annual_net_income = $auction->get->minimum_annual_net_income ?? '';
            $this->leasing_55_plus = $auction->get->leasing_55_plus ?? '';

            $nonNegotiableRaw = $auction->get->non_negotiable_amenities ?? null;
            $this->non_negotiable_amenities = $nonNegotiableRaw ? (is_string($nonNegotiableRaw) ? json_decode($nonNegotiableRaw, true) ?? [] : (array)$nonNegotiableRaw) : [];

            $this->other_non_negotiable_amenities = $auction->get->other_non_negotiable_amenities ?? '';
            $this->budget = $auction->get->budget ?? '';
            
            // Lease terms
            $this->additional_details = $auction->get->additional_details ?? '';
            
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

            $creditScoreRaw = $auction->get->credit_scroe_rating ?? null;
            $this->credit_scroe_rating = $creditScoreRaw ? (is_string($creditScoreRaw) ? json_decode($creditScoreRaw, true) ?? [] : (array)$creditScoreRaw) : [];

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

            $flatFeeServicesRaw = $auction->get->flat_fee_services ?? null;
            $this->flat_fee_services = $flatFeeServicesRaw ? (is_string($flatFeeServicesRaw) ? json_decode($flatFeeServicesRaw, true) ?? [] : (array)$flatFeeServicesRaw) : [];
            $this->additional_details = $auction->get->additional_details ?? '';

            // Broker compensation
            $this->commission_structure = $auction->get->commission_structure ?? '';
            $this->lease_fee_type = $auction->get->lease_fee_type ?? '';
            $this->lease_fee_flat = $auction->get->lease_fee_flat ?? '';
            $this->lease_fee_percentage = $auction->get->lease_fee_percentage ?? '';
            $this->lease_fee_months = $auction->get->lease_fee_months ?? '';
            $this->lease_fee_percentage_monthly_rent = $auction->get->lease_fee_percentage_monthly_rent ?? '';
            $this->lease_fee_flat_combo = $auction->get->lease_fee_flat_combo ?? '';
            $this->lease_fee_percentage_combo = $auction->get->lease_fee_percentage_combo ?? '';
            $this->lease_fee_other = $auction->get->lease_fee_other ?? '';
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
            $this->video_link = $auction->get->video_link ?? '';
            $this->photo = $auction->get->photo ?? null;

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
            $this->dispatchBrowserEvent('buyer-agent-select2-sync', [
                'view_preference' => $this->view_preference,
                'non_negotiable_amenities' => $this->non_negotiable_amenities,
                'pool_type' => $this->pool_type,
                'offered_financing' => $this->offered_financing,
                'services' => $this->services,
                'lease_for' => $this->lease_for,
                'credit_scroe_rating' => $this->credit_scroe_rating,
                'flat_fee_services' => $this->flat_fee_services,
                'number_of_unit_type' => $this->number_of_unit_type,
            ]);
        }
    }

    protected function stripCommas($value)
    {
        if ($value === null || $value === '') {
            return $value;
        }
        return str_replace(',', '', $value);
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
        $auction->saveMeta('assets', $this->assets);
        $auction->saveMeta('assets_other', $this->assets_other);
        $auction->saveMeta('property_criteria', $this->property_criteria);
        $auction->saveMeta('unit_size', $this->unit_size);
        $auction->saveMeta('unit_size_other', $this->unit_size_other);
        $auction->saveMeta('preferance_details', $this->preferance_details);

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
        $auction->saveMeta('assumable_terms', $this->assumable_terms);
        $auction->saveMeta('max_assumable_rate', $this->stripCommas($this->max_assumable_rate));
        $auction->saveMeta('max_monthly_payment', $this->stripCommas($this->max_monthly_payment));
        $auction->saveMeta('gap_payment_type', $this->gap_payment_type);
        $auction->saveMeta('gap_payment_amount', $this->stripCommas($this->gap_payment_amount));

        // Exchange / Trade
        $auction->saveMeta('exchange_item', $this->exchange_item);
        $auction->saveMeta('other_exchange_item', $this->other_exchange_item);
        $auction->saveMeta('exchange_item_value', $this->stripCommas($this->exchange_item_value));
        $auction->saveMeta('exchange_item_condition', $this->exchange_item_condition);
        $auction->saveMeta('additional_cash', $this->stripCommas($this->additional_cash));
        $auction->saveMeta('value_determination', $this->value_determination);

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

        // Lease Purchase
        $auction->saveMeta('lease_purchase_price', $this->stripCommas($this->lease_purchase_price));
        $auction->saveMeta('lease_purchase_terms', $this->lease_purchase_terms);
        $auction->saveMeta('lease_purchase_duration', $this->lease_purchase_duration);
        $auction->saveMeta('lease_purchase_payment', $this->stripCommas($this->lease_purchase_payment));
        $auction->saveMeta('lease_purchase_conditions', $this->lease_purchase_conditions);
        $auction->saveMeta('lease_purchase_option_fee', $this->lease_purchase_option_fee);
        $auction->saveMeta('lease_purchase_option_fee_amount', $this->stripCommas($this->lease_purchase_option_fee_amount));

        // Cryptocurrency
        $auction->saveMeta('cryptocurrency_type', $this->cryptocurrency_type);
        $auction->saveMeta('crypto_percentage', $this->stripCommas($this->crypto_percentage));
        $auction->saveMeta('cash_percentage_crypto', $this->stripCommas($this->cash_percentage_crypto));
        $auction->saveMeta('crypto_transfer_timing', $this->crypto_transfer_timing);
        $auction->saveMeta('crypto_transfer_timing_other', $this->crypto_transfer_timing_other);

        // NFT
        $auction->saveMeta('nft_description', $this->nft_description);
        $auction->saveMeta('nft_percentage', $this->stripCommas($this->nft_percentage));
        $auction->saveMeta('cash_percentage_nft', $this->stripCommas($this->cash_percentage_nft));

        // Amenities and Features
        $auction->saveMeta('tenant_require', json_encode($this->tenant_require));
        $auction->saveMeta('carport_needed', $this->carport_needed);
        $auction->saveMeta('other_carport_needed', $this->other_carport_needed);
        $auction->saveMeta('garage_needed', $this->garage_needed);
        $auction->saveMeta('other_garage_needed', $this->other_garage_needed);
        $auction->saveMeta('garage_parking_spaces', $this->garage_parking_spaces);
        $auction->saveMeta('garage_parking_spaces_option', $this->garage_parking_spaces_option);
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
        $auction->saveMeta('monthly_income', $this->stripCommas($this->monthly_income));
        $auction->saveMeta('number_occupant', $this->number_occupant);

        // Services
        $auction->saveMeta('services', json_encode($this->services));
        $auction->saveMeta('other_services', $this->other_services);
        $auction->saveMeta('flat_fee_services', json_encode($this->flat_fee_services));
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

        // Contact Information
        $auction->saveMeta('first_name', $this->first_name);
        $auction->saveMeta('last_name', $this->last_name);
        $phoneDigitsOnly = preg_replace('/\D/', '', $this->phone_number);
        $auction->saveMeta('phone_number', $phoneDigitsOnly);
        $auction->saveMeta('email', $this->email);
        $auction->saveMeta('video_link', $this->video_link);

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

    public function store()
    {
        \Log::info('[BUYER STORE START]', [
            'user_id' => auth()->id(),
            'listing_date' => $this->listing_date ?? null,
            'auction_type' => $this->auction_type ?? null,
            'counties' => $this->counties ?? [],
            'cities' => $this->cities ?? [],
            'state' => $this->state ?? null,
        ]);

        try {
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
            
            $this->isDraft = 0;

            $auction = $this->listingId
                ? HireBuyerAgentAuction::find($this->listingId)
                : new HireBuyerAgentAuction();

            $auction->user_id = Auth::id();
            $auction->title = $this->listing_title;
            $auction->is_draft = 0;
            $auction->save();

            $this->listingId = $auction->id;

            $this->saveAllMetadata($auction);

            \Log::info('[BUYER LISTING SUBMITTED]', [
                'record_id' => $auction->id,
                'listing_id' => $auction->listing_id ?? 'N/A',
                'user_id' => $auction->user_id,
                'is_draft' => $auction->is_draft,
                'is_approved' => $auction->is_approved,
                'is_sold' => $auction->is_sold,
            ]);

            session()->flash('success', 'Listing submitted successfully!');

            $url = route('buyer.view-auction', ['id' => $auction->id]);

            \Log::info('[BUYER STORE BEFORE REDIRECT EVENT]', ['url' => $url]);

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
            // Get all draft IDs first
            $draftIds = HireBuyerAgentAuction::where('user_id', Auth::id())
                ->where('is_draft', true)
                ->pluck('id');

            // Delete all metadata associated with these drafts
            if ($draftIds->isNotEmpty()) {
                DB::table('tenant_agent_auction_metas') // Replace with your actual meta table name
                    ->whereIn('tenant_agent_auction_id', $draftIds)
                    ->delete();
            }

            // Now delete the drafts themselves
            $deleted = HireBuyerAgentAuction::where('user_id', Auth::id())
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
            DB::table('tenant_agent_auction_metas')
                ->where('tenant_agent_auction_id', $draftId)
                ->delete();

            // Delete the draft
            HireBuyerAgentAuction::where('id', $draftId)
                ->where('user_id', Auth::id())
                ->delete();

            // Check if there are any drafts left
            $this->hasDrafts = HireBuyerAgentAuction::where('user_id', Auth::id())
                ->where('is_draft', true)
                ->exists();

            session()->flash('success', 'Draft deleted successfully.');
        } catch (\Exception $e) {
            session()->flash('error', 'Error deleting draft: ' . $e->getMessage());
        }
    }
}
