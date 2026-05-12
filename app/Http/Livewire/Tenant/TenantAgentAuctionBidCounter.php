<?php

namespace App\Http\Livewire\Tenant;

use Livewire\Component;
use App\Models\TenantCounterBidding;
use App\Models\TenantAgentAuctionBid as TenantAgentAuctionBidModel;
use App\Models\User;
use App\Notifications\CounterBidSubmittedNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TenantAgentAuctionBidCounter extends Component
{
    /**
     * Override syncInput to prevent Str::studly() crash when Livewire passes NULL/empty property names.
     * This is a known Livewire issue that can occur during form submissions.
     */
    public function syncInput($name, $value, $rehash = true)
    {
        if (empty($name)) {
            return;
        }
        parent::syncInput($name, $value, $rehash);
    }

    public $pab;
    public $bidId;
    public $counterPrice;

    public $auctionId;
    public $user_id;
    public $parent_counter_id = '';
    public $service_type; // 'full_service' or 'limited_service'
    public $user_type;
    public $property_type;

    public $activeTab = 0;



    public $lease_fee_flat_amount;
    public $lease_fee_percentage_amount;
    public $lease_fee_mixed_flat;
    public $lease_fee_mixed_percentage;
    public $other_lease_fee;
    public $purchase_fee_mixed_percentage;
    public $purchase_fee_mixed_flat;




    public $early_termination_fee;
    public $retainer_fee = 'No';
    public $agreement_timeframe;
    public $agreement_timeframe_custom;
    public $additional_terms;

    // Additional Details
    public $additional_details;




    public $lease_fee_months = '';

    public $lease_option_fee_type = '';
    public $lease_option_fee_flat = '';
    public $lease_option_fee_percentage = '';
    public $lease_option_fee_other = '';



    // Tenant Services
    public $services = [];
    public bool $other_services_enabled = false;
    public array $other_services = []; // Always initialize as an array
    // public $flat_fee_services = [];
    public $total_flat_fee = 0;
    public $total_marketing_fee = 0;


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


    // Broker

    public $commission_structure = '';
    public $lease_fee_type = '';
    public $lease_fee_flat = '';
    public $lease_fee_percentage = '';
    public $lease_fee_percentage_monthly_rent = '';
    public $lease_fee_flat_combo = '';
    public $lease_fee_percentage_combo = '';
    public $lease_fee_percentage_net = '';
    public $lease_fee_flat_combo_net = '';
    public $lease_fee_percentage_combo_net = '';
    public $lease_fee_other = '';

    public $interested_purchase_fee_type = '';   // Yes / No
    public $purchase_fee_type = '';
    public $purchase_fee_flat = '';
    public $purchase_fee_percentage = '';
    public $purchase_fee_percentage_combo = '';
    public $purchase_fee_flat_combo = '';
    public $purchase_fee_other = '';

    public $interested_lease_option_agreement = ''; // Yes / No

    public $protection_period = '';
    public $early_termination_fee_option = '';  // yes | no
    public $early_termination_fee_amount = '';
    public $retainer_fee_option = '';           // yes | no
    public $retainer_fee_amount = '';
    public $retainer_fee_application = '';      // applied | additional
    public $agency_agreement_timeframe = '';    // preset | custom
    public $agency_agreement_custom = '';
    public $brokerage_relationship = '';
    public $additional_details_broker = '';
    public $referral_fee_percent = '';
    public $isListingCreatedByAgent = false;

    // Offer listing preset context flag
    public bool $isOfferListing = false;

    // Client Contact Info (offer listing preset context)
    public string $client_name = '';
    public string $client_phone = '';
    public string $client_email = '';
    public string $client_target_city = '';
    public string $client_target_state = '';
    public string $client_target_zip = '';

    // Broker Fee Timing
    public $broker_fee_timing = '';
    public $broker_fee_days_from_rent = '';
    public $broker_fee_days_after_lease = '';
    public $broker_fee_days_after_rent = '';
    public $broker_fee_timing_other = '';

    public string $lease_type = 'percent';     // default as required
    public $lease_value = null;

    public string $purchase_type = 'percent';  // default as required
    public $purchase_value = null;
    public $purchase_fee_flat_type = '$';
    public string $lease_fee_flat_type = '$';

    protected function rules(): array
    {
        $rules = [
            'referral_fee_percent' => ['nullable', 'numeric', 'between:0,100'],
        ];
        if ($this->isOfferListing) {
            $rules['client_name']        = ['required', 'string', 'max:255'];
            $rules['client_phone']       = ['required', 'string', 'max:50'];
            $rules['client_email']       = ['required', 'email', 'max:255'];
            $rules['client_target_city'] = ['required', 'string', 'max:100'];
            $rules['client_target_state']= ['required', 'string', 'max:100'];
            $rules['client_target_zip']  = ['required', 'string', 'max:20'];
        }
        return $rules;
    }

    protected $messages = [
        'referral_fee_percent.numeric'  => 'Referral fee must be a number.',
        'referral_fee_percent.between'  => 'Referral fee must be between 0 and 100.',
        'client_name.required'         => 'Client name is required for offer listings.',
        'client_phone.required'        => 'Client phone is required for offer listings.',
        'client_email.required'        => 'Client email is required for offer listings.',
        'client_email.email'           => 'Please enter a valid email address.',
        'client_target_city.required'  => 'Target city is required for offer listings.',
        'client_target_state.required' => 'Target state is required for offer listings.',
        'client_target_zip.required'   => 'Target ZIP code is required for offer listings.',
    ];

    public function getServicesConfigProperty(): array
    {
        $q = "\u{2019}";

        $residential = [
            [
                'title'    => '📢 Tenant Criteria Marketing & Promotion',
                'prefix'   => 'ctr-mktg',
                'services' => [
                    "Create a branded flyer summarizing the Tenant{$q}s rental criteria",
                    "Post the Tenant{$q}s rental criteria on Craigslist under the \u{201C}Real Estate Wanted\u{201D} section",
                    "Share the Tenant{$q}s rental criteria on Nextdoor in Neighborhood or Community Groups",
                    "Promote the Tenant{$q}s rental criteria on Facebook in Rental or Housing Groups",
                    "Share the Tenant{$q}s rental criteria on Instagram using posts, stories, or reels",
                    "Promote the Tenant{$q}s rental criteria on LinkedIn in Real Estate or Housing Groups",
                    "Upload a TikTok video summarizing the Tenant{$q}s rental criteria",
                    "Upload a YouTube video summarizing the Tenant{$q}s rental criteria",
                    "Launch a mass email campaign promoting the Tenant{$q}s rental criteria",
                    "Distribute branded postcards or flyers in the Tenant{$q}s preferred neighborhoods",
                    "Launch hyperlocal digital ads targeting the Tenant{$q}s preferred rental areas",
                ],
            ],
            [
                'title'    => '🔍 Property Search, Alerts & Matching',
                'prefix'   => 'ctr-srch',
                'services' => [
                    "Send email alerts with new listings from the MLS that match the Tenant{$q}s rental criteria",
                    "Search for off-market, pre-market, withdrawn, canceled, or expired properties that meet the Tenant{$q}s rental criteria",
                    "Communicate with the Landlord{$q}s Agent, Landlord, or Property Manager to confirm availability, lease terms, and showing instructions",
                    "Evaluate properties with the Tenant and provide insights on pricing, lease terms, and overall fit",
                ],
            ],
            [
                'title'    => '🏡 Property Showings & Virtual Tours',
                'prefix'   => 'ctr-show',
                'services' => [
                    "Schedule and attend property showings with the Tenant",
                    "Coordinate or conduct virtual showings via live video or pre-recorded walkthroughs",
                    "Preview properties on behalf of the Tenant upon request",
                    "Provide factual observations on property layout and condition",
                ],
            ],
            [
                'title'    => '📝 Tenant Application Support',
                'prefix'   => 'ctr-app',
                'services' => [
                    "Provide the Tenant with application instructions or links to an online rental application platform",
                    "Gather and organize required supporting documents (e.g., identification, income verification, reference letters)",
                    "Submit complete and organized application packages to the Landlord{$q}s Agent, Landlord, or Property Manager for review",
                    "Answer questions about the application process, screening timelines, and required documentation",
                ],
            ],
            [
                'title'    => '📃 Lease Preparation & Execution',
                'prefix'   => 'ctr-lease',
                'services' => [
                    "Review lease offers and assist the Tenant in preparing questions or requested changes",
                    "Coordinate lease negotiation with the Landlord{$q}s Agent, Landlord, or Property Manager",
                    "Assist with completing required lease disclosures and reviewing key lease terms",
                    "Assist with in-person or electronic lease signing, including e-signature setup and secure delivery of executed lease documents, addenda, and disclosures to all parties",
                ],
            ],
            [
                'title'    => '🚚 Move-In Support & Coordination',
                'prefix'   => 'ctr-move',
                'services' => [
                    "Coordinate move-in date and key handoff logistics with the Landlord{$q}s Agent, Landlord or Property Manager",
                    "Confirm completion of any agreed-upon pre-move-in cleaning or repairs",
                    "Provide a utility setup checklist and local provider resources",
                    "Share a move-in checklist for documentation and property condition review",
                    "Confirm required move-in payments and assist the Tenant with tracking amounts due, deadlines, and accepted payment methods",
                ],
            ],
            [
                'title'    => '💡 Leasing Strategy & Guidance',
                'prefix'   => 'ctr-strat',
                'services' => [
                    "Provide a Rental Market Analysis (RMA) with pricing insights based on comparable rentals, neighborhood trends, and current market conditions",
                    "Advise on lease types and structures (e.g., month-to-month, annual, furnished, lease-option)",
                    "Provide general guidance on Tenant rights and Landlord responsibilities under state law",
                    "Provide general guidance on lease clauses, payment terms, and renewal options",
                ],
            ],
        ];

        $commercial = [
            [
                'title'    => '📢 Tenant Criteria Marketing & Promotion',
                'prefix'   => 'ctr-mktg',
                'services' => [
                    "Create a branded flyer summarizing the Tenant{$q}s leasing criteria",
                    "Post the Tenant{$q}s leasing criteria on Craigslist under the \u{201C}Office/Commercial\u{201D} or \u{201C}Retail\u{201D} section",
                    "Promote the Tenant{$q}s leasing criteria on Facebook in Commercial Leasing or Business Groups",
                    "Share the Tenant{$q}s leasing criteria on Instagram using posts, stories, or reels",
                    "Promote the Tenant{$q}s leasing criteria on LinkedIn in Professional, Real Estate, or Commercial Investment Groups",
                    "Upload a TikTok video summarizing the Tenant{$q}s leasing criteria",
                    "Upload a YouTube video summarizing the Tenant{$q}s leasing criteria",
                    "Launch a mass email campaign promoting the Tenant{$q}s leasing criteria",
                    "Distribute branded postcards or flyers in the Tenant{$q}s preferred neighborhoods",
                    "Launch hyperlocal digital ads targeting the Tenant{$q}s preferred leasing areas",
                ],
            ],
            [
                'title'    => '🔍 Property Search, Alerts & Matching',
                'prefix'   => 'ctr-srch',
                'services' => [
                    "Send listing alerts from real estate platforms that match the Tenant{$q}s leasing criteria",
                    "Search for off-market, pre-market, withdrawn, canceled, or expired properties that meet the Tenant{$q}s rental criteria",
                    "Communicate with the Landlord{$q}s Agent, Landlord, or Property Manager to confirm availability, lease terms, and showing instructions",
                    "Evaluate properties for layout efficiency, building specs, logistics, zoning fit, and operational alignment",
                ],
            ],
            [
                'title'    => '🏢 Property Showings & Virtual Tours',
                'prefix'   => 'ctr-show',
                'services' => [
                    "Schedule and attend property tours with the Tenant",
                    "Coordinate or conduct virtual showings via live video or pre-recorded walkthroughs",
                    "Preview properties on behalf of the Tenant upon request",
                    "Provide factual notes on layout, access, parking, visibility, and other operational considerations",
                ],
            ],
            [
                'title'    => '📝 Tenant Application Support',
                'prefix'   => 'ctr-app',
                'services' => [
                    "Provide the Tenant with application instructions or links to online platforms",
                    "Gather and organize required supporting documents (e.g., business licenses, financials, references)",
                    "Submit complete and organized application packages to the Landlord{$q}s Agent, Landlord, or Property Manager",
                ],
            ],
            [
                'title'    => '📃 Lease Preparation, LOI & Execution',
                'prefix'   => 'ctr-lease',
                'services' => [
                    "Draft or assist with preparing a Letter of Intent (LOI) summarizing the Tenant{$q}s business needs and proposed terms",
                    "Assist with negotiating rent, CAM, lease term, TI allowance, exclusivity clauses, renewal options, and other provisions (as permitted under the agency agreement)",
                    "Coordinate with the Landlord{$q}s Agent, Landlord or Property Manager to finalize lease terms",
                    "Review lease drafts and coordinate revisions through appropriate channels",
                    "Assist with in-person or electronic lease signing, including e-signature setup and secure delivery of executed lease documents, addenda, and disclosures to all parties",
                    "Track required deposits, rent commencement, and key lease dates to ensure move-in readiness",
                ],
            ],
            [
                'title'    => '🚚 Move-In Support & Coordination',
                'prefix'   => 'ctr-move',
                'services' => [
                    "Coordinate move-in date and key handoff logistics with the Landlord, Landlord{$q}s Agent, or Property Manager",
                    "Confirm completion of any agreed-upon pre-move-in repairs, cleaning, or buildout",
                    "Provide a utility setup checklist and local provider resources",
                    "Share a move-in checklist for documentation and property condition review",
                    "Confirm required move-in payments and assist the Tenant with tracking amounts due, deadlines, and accepted payment methods",
                ],
            ],
            [
                'title'    => '💡 Leasing Strategy & Guidance',
                'prefix'   => 'ctr-strat',
                'services' => [
                    "Provide a Comparative Lease Market Analysis (CLMA) with pricing insights, comps, and vacancy trends",
                    "Advise on lease types and structures (e.g., NNN, Modified Gross, Full Service) with general explanations of differences",
                    "Provide general guidance on Tenant rights and Landlord responsibilities under commercial leasing law",
                    "Provide general guidance on lease clauses, escalation terms, and space usage considerations",
                ],
            ],
        ];

        return $this->property_type === 'Commercial Property' ? $commercial : $residential;
    }

    public function updatedReferralFeePercent(): void
    {
        $this->validateOnly('referral_fee_percent');
    }

    // Ensure lease_type never becomes empty during transitions
    public function updatedLeaseType($value)
    {
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
    }



    /* ------------------------------------------------------
     * RESET HELPERS
     * ------------------------------------------------------ */

    private function resetLeaseFeeFields()
    {
        $this->reset([
            'lease_fee_flat_type',
            'lease_fee_flat',
            'lease_fee_percentage',
            'lease_fee_percentage_monthly_rent',
            'lease_fee_flat_combo',
            'lease_fee_percentage_combo',
            'lease_fee_percentage_net',
            'lease_fee_percentage_combo_net',
            'lease_fee_other',
        ]);
    }

    private function resetPurchaseFeeFields()
    {
        $this->reset([
            'purchase_fee_flat_type',
            'purchase_fee_flat',
            'purchase_fee_percentage',
            'purchase_fee_percentage_combo',
            'purchase_fee_flat_combo',
            'purchase_fee_other',
        ]);
    }

    private function resetLeaseOptionFields()
    {
        $this->reset([
            'lease_type',
            'lease_value',
            'purchase_type',
            'purchase_value',
        ]);
    }


    /* ------------------------------------------------------
     * LIVEWIRE UPDATED METHODS (Triggers Reset)
     * ------------------------------------------------------ */

    public function updatedLeaseFeeType($value)
    {
        $this->resetLeaseFeeFields();
    }

    public function updatedInterestedPurchaseFeeType($value)
    {
        $this->resetPurchaseFeeFields();
        $this->purchase_fee_type = '';
    }

    public function updatedPurchaseFeeType($value)
    {
        $this->resetPurchaseFeeFields();
    }

    public function updatedInterestedLeaseOptionAgreement($value)
    {
        $this->resetLeaseOptionFields();
    }

    public function updatedEarlyTerminationFeeOption($value)
    {
        $this->early_termination_fee_amount = '';
    }

    public function updatedRetainerFeeOption($value)
    {
        $this->reset([
            'retainer_fee_amount',
            'retainer_fee_application'
        ]);
    }

    public function updatedAgencyAgreementTimeframe($value)
    {
        $this->agency_agreement_custom = '';
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
    public function setActiveTab($index)
    {
        $this->activeTab = $index;
    }
    public function mount($pab, $bidId, $parent_counter_id = null)
    {
        $this->pab   = $pab;
        $this->bidId = $bidId;
        $this->parent_counter_id = $parent_counter_id;
        
        $sourceData = null;
        
        // First, get the auction to load property_type from the original listing
        $auction = $pab->auction ?? \App\Models\TenantAgentAuction::find($pab->tenant_agent_auction_id);
        
        // Default property_type from the listing (not from the agent bid)
        if ($auction && $auction->get) {
            $this->property_type = $auction->get->property_type ?? '';
            $this->service_type = $auction->get->service_type ?? '';
        } else {
            $this->property_type = $pab->get->property_type ?? '';
        }
        $this->isListingCreatedByAgent = optional($auction)->isCreatedByAgent() ?? false;
        $this->isOfferListing = $pab->info('workflow_type') === 'offer_listing';
        
        // COUNTER BID PREFILL RULE: Agent counters with Tenant's latest terms for THIS bid thread
        // TenantCounterTerm stores bid ID in tenant_agent_auction_id field
        // First try to load the Tenant's latest counter terms for this specific bid
        $tenantCounter = \App\Models\TenantCounterTerm::where('tenant_agent_auction_id', $bidId)
            ->orderBy('created_at', 'desc')
            ->first();
        
        if ($tenantCounter && $tenantCounter->get) {
            // Use Tenant's latest counter terms via the get accessor (properly decodes JSON)
            $sourceData = $tenantCounter->get;
            
            // Override property_type and service_type from tenant counter if available
            if (!empty($sourceData->property_type)) {
                $this->property_type = $sourceData->property_type;
            }
            if (!empty($sourceData->service_type)) {
                $this->service_type = $sourceData->service_type;
            }
        }
        
        // If no Tenant counter exists, fall back to the Tenant listing's original terms
        if (!$sourceData) {
            if ($auction && $auction->get) {
                $sourceData = $auction->get;
                // Load property_type and service_type from auction
                $this->property_type = $sourceData->property_type ?? $this->property_type;
                $this->service_type = $sourceData->service_type ?? '';
            }
        }
        
        // Final fallback: Use original bid terms (Agent's own bid if no other source)
        if (!$sourceData) {
            $originalBid = \App\Models\TenantAgentAuctionBid::find($bidId);
            if ($originalBid) {
                $sourceData = $originalBid->get;
            }
        }
        
        if ($sourceData) {
            $this->commission_structure = $sourceData->commission_structure ?? '';
            $this->lease_fee_type = $sourceData->lease_fee_type ?? '';
            $this->lease_fee_flat = $sourceData->lease_fee_flat ?? '';
            $this->lease_fee_percentage = $sourceData->lease_fee_percentage ?? '';
            $this->lease_fee_flat_amount = $sourceData->lease_fee_flat_amount ?? '';
            $this->lease_fee_percentage_amount = $sourceData->lease_fee_percentage_amount ?? '';
            $this->lease_fee_mixed_flat = $sourceData->lease_fee_mixed_flat ?? '';
            $this->lease_fee_mixed_percentage = $sourceData->lease_fee_mixed_percentage ?? '';
            $this->lease_fee_percentage_monthly_rent = $sourceData->lease_fee_percentage_monthly_rent ?? '';
            $this->lease_fee_flat_combo = $sourceData->lease_fee_flat_combo ?? '';
            $this->lease_fee_percentage_combo = $sourceData->lease_fee_percentage_combo ?? '';
            $this->lease_fee_percentage_net = $sourceData->lease_fee_percentage_net ?? '';
            $this->lease_fee_flat_combo_net = $sourceData->lease_fee_flat_combo_net ?? '';
            $this->lease_fee_percentage_combo_net = $sourceData->lease_fee_percentage_combo_net ?? '';
            $this->lease_fee_other = $sourceData->lease_fee_other ?? '';
            $this->lease_fee_months = $sourceData->lease_fee_months ?? '';
            
            $this->interested_purchase_fee_type = $sourceData->interested_purchase_fee_type ?? '';
            $this->purchase_fee_type = $sourceData->purchase_fee_type ?? '';
            $this->purchase_fee_flat = $sourceData->purchase_fee_flat ?? '';
            $this->purchase_fee_percentage = $sourceData->purchase_fee_percentage ?? '';
            $this->purchase_fee_percentage_combo = $sourceData->purchase_fee_percentage_combo ?? '';
            $this->purchase_fee_flat_combo = $sourceData->purchase_fee_flat_combo ?? '';
            $this->purchase_fee_mixed_percentage = $sourceData->purchase_fee_mixed_percentage ?? '';
            $this->purchase_fee_mixed_flat = $sourceData->purchase_fee_mixed_flat ?? '';
            $this->purchase_fee_other = $sourceData->purchase_fee_other ?? '';
            
            $this->interested_lease_option_agreement = $sourceData->interested_lease_option_agreement ?? '';
            $this->lease_option_fee_type = $sourceData->lease_option_fee_type ?? '';
            $this->lease_option_fee_flat = $sourceData->lease_option_fee_flat ?? '';
            $this->lease_option_fee_percentage = $sourceData->lease_option_fee_percentage ?? '';
            $this->lease_option_fee_other = $sourceData->lease_option_fee_other ?? '';
            $this->lease_type = $sourceData->lease_type ?? 'percent';
            $this->lease_value = $sourceData->lease_value ?? '';
            $this->purchase_type = $sourceData->purchase_type ?? 'percent';
            $this->purchase_value = $sourceData->purchase_value ?? '';
            
            $this->protection_period = $sourceData->protection_period ?? '';
            $this->early_termination_fee = $sourceData->early_termination_fee ?? '';
            $this->early_termination_fee_option = $sourceData->early_termination_fee_option ?? '';
            $this->early_termination_fee_amount = $sourceData->early_termination_fee_amount ?? '';
            $this->retainer_fee = $sourceData->retainer_fee ?? 'No';
            $this->retainer_fee_option = $sourceData->retainer_fee_option ?? '';
            $this->retainer_fee_amount = $sourceData->retainer_fee_amount ?? '';
            $this->retainer_fee_application = $sourceData->retainer_fee_application ?? '';
            $this->agreement_timeframe = $sourceData->agreement_timeframe ?? '';
            $this->agreement_timeframe_custom = $sourceData->agreement_timeframe_custom ?? '';
            $this->agency_agreement_timeframe = $sourceData->agency_agreement_timeframe ?? '';
            $this->agency_agreement_custom = $sourceData->agency_agreement_custom ?? '';
            $this->brokerage_relationship = $sourceData->brokerage_relationship ?? '';
            // Payment timing: try current field name first, fall back to legacy name
            $this->broker_fee_timing = $sourceData->payment_timing ?? $sourceData->broker_fee_timing ?? '';
            $this->broker_fee_timing_other = $sourceData->broker_fee_timing_other ?? '';
            $this->broker_fee_days_from_rent = $sourceData->broker_fee_days_from_rent ?? '';
            $this->broker_fee_days_after_lease = $sourceData->broker_fee_days_after_lease ?? '';
            $this->broker_fee_days_after_rent = $sourceData->broker_fee_days_after_rent ?? '';
            // Additional terms: try current field name first, fall back to legacy names
            $this->additional_details_broker = $sourceData->additional_details_broker
                ?? $sourceData->additional_terms
                ?? $sourceData->additional_details
                ?? '';
            $this->additional_terms = $sourceData->additional_terms ?? '';
            if ($this->isListingCreatedByAgent) {
                $this->referral_fee_percent = $sourceData->referral_fee_percent ?? '';
            }
            $this->additional_details = $sourceData->additional_details ?? '';

            // Client Contact Info (offer listing preset context)
            $this->client_name         = $sourceData->counter_client_name  ?? '';
            $this->client_phone        = $sourceData->counter_client_phone ?? '';
            $this->client_email        = $sourceData->counter_client_email ?? '';
            $this->client_target_city  = $sourceData->counter_target_city  ?? '';
            $this->client_target_state = $sourceData->counter_target_state ?? '';
            $this->client_target_zip   = $sourceData->counter_target_zip   ?? '';
            
            // Load service_type if available in sourceData
            if (!empty($sourceData->service_type)) {
                $this->service_type = $sourceData->service_type;
            }
            
            // Load property_type if available (for fallback cases)
            if (!empty($sourceData->property_type)) {
                $this->property_type = $sourceData->property_type;
            }
            
            $services = $sourceData->services ?? '';
            $this->services = is_string($services) ? json_decode($services, true) ?? [] : (array) $services;
            // Filter to only services that belong to the current catalog for this property type.
            // This discards stale Buyer, mixed, or legacy catalog entries from old submissions.
            $this->services = $this->filterServicesToCurrentCatalog($this->services);
            
            $otherServices = $sourceData->other_services ?? '';
            $this->other_services = is_string($otherServices) ? json_decode($otherServices, true) ?? [] : (array) $otherServices;
            $this->other_services_enabled = !empty($this->other_services);
        }

        // Normalize flat-fee type props to '$' — the counter UI is $-only for flat fee inputs.
        // This guards against any legacy '%' values that may have been stored in older records.
        $this->lease_fee_flat_type    = '$';
        $this->purchase_fee_flat_type = '$';
    }

    /**
     * Filter a raw services array to only include strings that exist in the
     * current catalog (determined by $this->property_type via getServicesConfigProperty).
     * Discards Buyer, legacy, or cross-property-type services and maps each
     * kept service to the catalog's canonical string (correct apostrophe encoding).
     */
    private function filterServicesToCurrentCatalog(array $services): array
    {
        $normalize = function (string $s): string {
            return mb_strtolower(trim(str_replace(
                ["\u{2018}", "\u{2019}", "\u{201C}", "\u{201D}", "'"],
                ["'",        "'",        '"',        '"',        "'"],
                $s
            )));
        };

        $catalogLookup = [];
        foreach ($this->servicesConfig as $category) {
            foreach ($category['services'] as $catSvc) {
                $catalogLookup[$normalize($catSvc)] = $catSvc;
            }
        }

        $filtered = [];
        foreach ($services as $svc) {
            $norm = $normalize((string) $svc);
            if (isset($catalogLookup[$norm])) {
                $filtered[] = $catalogLookup[$norm];
            }
        }
        return $filtered;
    }



    public function render()
    {
        $flowKey = \App\Support\ServicesFormatter::keyForTenantAgent($this->property_type ?: 'Residential');
        return view('livewire.tenant.tenant-agent-auction-bid-counter', [
            'pab'              => $this->pab,
            'bidId'            => $this->bidId,
            'property_type'    => $this->property_type,
            'parent_counter_id'=> $this->parent_counter_id,
            'servicesConfig'   => $this->servicesConfig,
            'groupedServices'  => \App\Support\ServicesFormatter::orderSelectedServices($this->services, $flowKey),
        ])->extends('layouts.main')
            ->section('content');
    }


    public function submit()
    {
        $this->validate();

        try {
            $endDate = strtotime($this->pab->end_date . ' ' . ($this->pab->end_time ?? '23:59:59'));
            if (time() > $endDate) {
                session()->flash('error', 'This auction has ended. Counter bids are no longer available.');
                return redirect()->route('tenant.agent.auction.view', $this->pab->id);
            }
            
            if ($this->pab->is_sold) {
                session()->flash('error', 'This listing has been sold. Counter bids are no longer available.');
                return redirect()->route('tenant.agent.auction.view', $this->pab->id);
            }
            
            DB::beginTransaction();

            // 1. Create main counter bidding record
            $counterBid = TenantCounterBidding::create([
                'user_id' => Auth::id(),
                'tenant_agent_auction_id' => $this->pab->id,
                'tenant_agent_auction_bid_id' => $this->bidId,
                'property_type' => $this->property_type,
                'parent_counter_id' => $this->parent_counter_id,
            ]);

            // 2. Save all meta data
            $this->saveAllMetaData($counterBid);

            DB::commit();
            
            // Send notification to the listing owner (Tenant)
            try {
                $originalBid = TenantAgentAuctionBidModel::find($this->bidId);
                if ($originalBid) {
                    $listingOwner = User::find($this->pab->user_id);
                    if ($listingOwner) {
                        $listingOwner->notify(new CounterBidSubmittedNotification(
                            $originalBid,
                            $this->pab,
                            Auth::user(),
                            $this->pab->user_id
                        ));
                    }
                }
            } catch (\Exception $e) {
                Log::error('Failed to send counter bid notification', [
                    'bid_id' => $this->bidId,
                    'error' => $e->getMessage()
                ]);
            }
            
            session()->flash('success', 'Counter bid has been submitted successfully!');

            return redirect()->route('tenant.hire.agent.auction.bid.view-counter', $this->bidId);
            // Optional: reset form or redirect
            // $this->resetForm();

        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', 'Error saving counter bid: ' . $e->getMessage());
        }
    }


    private function saveAllMetaData($counterBid)
    {
        // Broker Compensation & Fees
        $counterBid->saveMeta('commission_structure', $this->commission_structure);

        // Lease Fee Structure
        $counterBid->saveMeta('lease_fee_type', $this->lease_fee_type);
        $counterBid->saveMeta('lease_fee_flat', $this->lease_fee_flat);
        $counterBid->saveMeta('lease_fee_percentage', $this->lease_fee_percentage);
        $counterBid->saveMeta('lease_fee_flat_amount', $this->lease_fee_flat_amount);
        $counterBid->saveMeta('lease_fee_percentage_amount', $this->lease_fee_percentage_amount);
        $counterBid->saveMeta('lease_fee_mixed_flat', $this->lease_fee_mixed_flat);
        $counterBid->saveMeta('lease_fee_mixed_percentage', $this->lease_fee_mixed_percentage);
        $counterBid->saveMeta('lease_fee_percentage_monthly_rent', $this->lease_fee_percentage_monthly_rent);
        $counterBid->saveMeta('lease_fee_flat_combo', $this->lease_fee_flat_combo);
        $counterBid->saveMeta('lease_fee_percentage_combo', $this->lease_fee_percentage_combo);
        $counterBid->saveMeta('lease_fee_percentage_net', $this->lease_fee_percentage_net);
        $counterBid->saveMeta('lease_fee_flat_combo_net', $this->lease_fee_flat_combo_net);
        $counterBid->saveMeta('lease_fee_percentage_combo_net', $this->lease_fee_percentage_combo_net);
        $counterBid->saveMeta('other_lease_fee', $this->other_lease_fee);
        $counterBid->saveMeta('lease_fee_other', $this->lease_fee_other);
        $counterBid->saveMeta('lease_fee_months', $this->lease_fee_months);

        // Normalize and persist flat-fee type keys so any legacy '%' values in meta are overwritten.
        $counterBid->saveMeta('lease_fee_flat_type', '$');
        $counterBid->saveMeta('purchase_fee_flat_type', '$');

        // Purchase Fee Structure
        $counterBid->saveMeta('interested_purchase_fee_type', $this->interested_purchase_fee_type);
        $counterBid->saveMeta('purchase_fee_type', $this->purchase_fee_type);
        $counterBid->saveMeta('purchase_fee_flat', $this->purchase_fee_flat);
        $counterBid->saveMeta('purchase_fee_percentage', $this->purchase_fee_percentage);
        $counterBid->saveMeta('purchase_fee_percentage_combo', $this->purchase_fee_percentage_combo);
        $counterBid->saveMeta('purchase_fee_flat_combo', $this->purchase_fee_flat_combo);
        $counterBid->saveMeta('purchase_fee_mixed_percentage', $this->purchase_fee_mixed_percentage);
        $counterBid->saveMeta('purchase_fee_mixed_flat', $this->purchase_fee_mixed_flat);
        $counterBid->saveMeta('purchase_fee_other', $this->purchase_fee_other);

        // Lease-Option Agreement
        $counterBid->saveMeta('interested_lease_option_agreement', $this->interested_lease_option_agreement);
        $counterBid->saveMeta('lease_option_fee_type', $this->lease_option_fee_type);
        $counterBid->saveMeta('lease_option_fee_flat', $this->lease_option_fee_flat);
        $counterBid->saveMeta('lease_option_fee_percentage', $this->lease_option_fee_percentage);
        $counterBid->saveMeta('lease_option_fee_other', $this->lease_option_fee_other);
        $counterBid->saveMeta('lease_type', $this->lease_type);
        $counterBid->saveMeta('lease_value', str_replace(',', '', $this->lease_value ?? ''));
        $counterBid->saveMeta('purchase_type', $this->purchase_type);
        $counterBid->saveMeta('purchase_value', str_replace(',', '', $this->purchase_value ?? ''));

        // Broker Terms & Agreements
        $counterBid->saveMeta('protection_period', $this->protection_period);
        $counterBid->saveMeta('early_termination_fee', $this->early_termination_fee);
        $counterBid->saveMeta('early_termination_fee_option', $this->early_termination_fee_option);
        $counterBid->saveMeta('early_termination_fee_amount', $this->early_termination_fee_amount);
        $counterBid->saveMeta('retainer_fee', $this->retainer_fee);
        $counterBid->saveMeta('retainer_fee_option', $this->retainer_fee_option);
        $counterBid->saveMeta('retainer_fee_amount', $this->retainer_fee_amount);
        $counterBid->saveMeta('retainer_fee_application', $this->retainer_fee_application);
        $counterBid->saveMeta('agreement_timeframe', $this->agreement_timeframe);
        $counterBid->saveMeta('agreement_timeframe_custom', $this->agreement_timeframe_custom);
        $counterBid->saveMeta('agency_agreement_timeframe', $this->agency_agreement_timeframe);
        $counterBid->saveMeta('agency_agreement_custom', $this->agency_agreement_custom);
        $counterBid->saveMeta('brokerage_relationship', $this->brokerage_relationship);
        $counterBid->saveMeta('additional_terms', $this->additional_terms);

        // Additional Details
        $counterBid->saveMeta('additional_details', $this->additional_details ?? null);
        $counterBid->saveMeta('additional_details_broker', $this->additional_details_broker ?? null);
        if ($this->isListingCreatedByAgent) {
            $counterBid->saveMeta('referral_fee_percent', $this->referral_fee_percent);
        }

        // Tenant Services
        $counterBid->saveMeta('services', json_encode($this->services));
        $counterBid->saveMeta('other_services', json_encode($this->other_services ?? []));
        $counterBid->saveMeta('other_services_enabled', $this->other_services_enabled);
        $counterBid->saveMeta('total_flat_fee', $this->total_flat_fee);
        $counterBid->saveMeta('total_marketing_fee', $this->total_marketing_fee);

        // Marketing Services
        $counterBid->saveMeta('list_criteria', $this->list_criteria);
        $counterBid->saveMeta('list_criteria_fee', $this->list_criteria_fee);
        $counterBid->saveMeta('market_groups', $this->market_groups);
        $counterBid->saveMeta('market_groups_fee', $this->market_groups_fee);
        $counterBid->saveMeta('promote_social', $this->promote_social);
        $counterBid->saveMeta('promote_social_fee', $this->promote_social_fee);
        $counterBid->saveMeta('launch_ads', $this->launch_ads);
        $counterBid->saveMeta('launch_ads_fee', $this->launch_ads_fee);
        $counterBid->saveMeta('include_marketing_fee', $this->include_marketing_fee);
        $counterBid->saveMeta('marketing_materials_fee', $this->marketing_materials_fee);
        $counterBid->saveMeta('email_notifications_fee', $this->email_notifications_fee);
        $counterBid->saveMeta('off_market_search_fee', $this->off_market_search_fee);
        $counterBid->saveMeta('mls_filter_fee', $this->mls_filter_fee);
        $counterBid->saveMeta('email_marketing_fee', $this->email_marketing_fee);

        // Property Showings
        $counterBid->saveMeta('schedule_showings', $this->schedule_showings);
        $counterBid->saveMeta('number_of_showings_to_schedule', $this->number_of_showings_to_schedule);
        $counterBid->saveMeta('schedule_showings_fee', $this->schedule_showings_fee);
        $counterBid->saveMeta('attend_showings', $this->attend_showings);
        $counterBid->saveMeta('number_of_showings_to_attend', $this->number_of_showings_to_attend);
        $counterBid->saveMeta('attend_showings_fee', $this->attend_showings_fee);
        $counterBid->saveMeta('provide_virtual_tours', $this->provide_virtual_tours);
        $counterBid->saveMeta('number_of_virtual_tours', $this->number_of_virtual_tours);
        $counterBid->saveMeta('virtual_tours_fee', $this->virtual_tours_fee);

        // Application & Lease Support
        $counterBid->saveMeta('assist_application', $this->assist_application);
        $counterBid->saveMeta('assist_application_fee', $this->assist_application_fee);
        $counterBid->saveMeta('collect_documents', $this->collect_documents);
        $counterBid->saveMeta('collect_documents_fee', $this->collect_documents_fee);
        $counterBid->saveMeta('submit_application', $this->submit_application);
        $counterBid->saveMeta('submit_application_fee', $this->submit_application_fee);
        $counterBid->saveMeta('review_lease', $this->review_lease);
        $counterBid->saveMeta('review_lease_fee', $this->review_lease_fee);
        $counterBid->saveMeta('provide_lease_form', $this->provide_lease_form);
        $counterBid->saveMeta('provide_lease_form_fee', $this->provide_lease_form_fee);
        $counterBid->saveMeta('coordinate_signing', $this->coordinate_signing);
        $counterBid->saveMeta('coordinate_signing_fee', $this->coordinate_signing_fee);
        $counterBid->saveMeta('prepare_application_fee', $this->prepare_application_fee);

        // Move Services
        $counterBid->saveMeta('move_in_inspection_fee', $this->move_in_inspection_fee);
        $counterBid->saveMeta('moving_resources_fee', $this->moving_resources_fee);
        $counterBid->saveMeta('short_term_housing_fee', $this->short_term_housing_fee);

        // Advisory Services
        $counterBid->saveMeta('rental_rights_fee', $this->rental_rights_fee);
        $counterBid->saveMeta('lease_advice_fee', $this->lease_advice_fee);

        // Neighborhood Marketing
        $counterBid->saveMeta('neighborhood_insights_fee', $this->neighborhood_insights_fee);
        $counterBid->saveMeta('neighborhood_marketing_fee', $this->neighborhood_marketing_fee);
        $counterBid->saveMeta('neighborhood_materials_fee', $this->neighborhood_materials_fee);

        // Client Contact Info (offer listing preset context)
        $counterBid->saveMeta('counter_client_name', $this->client_name);
        $counterBid->saveMeta('counter_client_phone', $this->client_phone);
        $counterBid->saveMeta('counter_client_email', $this->client_email);
        $counterBid->saveMeta('counter_target_city', $this->client_target_city);
        $counterBid->saveMeta('counter_target_state', $this->client_target_state);
        $counterBid->saveMeta('counter_target_zip', $this->client_target_zip);
    }
}
