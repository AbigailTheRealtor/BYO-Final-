<?php

namespace App\Http\Livewire\Tenant;

use Livewire\Component;
use App\Models\TenantCounterBidding;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TenantAgentAuctionBidCounter extends Component
{

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
        $this->property_type = $pab->get->property_type ?? '';
        
        $sourceData = null;
        
        // COUNTER BID PREFILL RULE: Agent counters with Tenant's latest terms
        // First try to load the Tenant's latest counter terms (TenantCounterTerm)
        $tenantCounter = \App\Models\TenantCounterTerm::with('meta')
            ->where('tenant_agent_auction_id', $pab->tenant_agent_auction_id ?? $pab->id)
            ->orderBy('created_at', 'desc')
            ->first();
        
        if ($tenantCounter && $tenantCounter->meta) {
            // Use Tenant's latest counter terms
            $metaMap = $tenantCounter->meta->pluck('meta_value', 'meta_key')->toArray();
            $sourceData = (object) $metaMap;
        }
        
        // If no Tenant counter exists, fall back to the Tenant listing's original terms
        if (!$sourceData) {
            $auction = $pab->auction ?? \App\Models\HireTenantAgentAuction::find($pab->tenant_agent_auction_id);
            if ($auction && $auction->get) {
                $sourceData = $auction->get;
            }
        }
        
        // Final fallback: Use original bid terms
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
            $this->additional_terms = $sourceData->additional_terms ?? '';
            $this->additional_details = $sourceData->additional_details ?? '';
            $this->additional_details_broker = $sourceData->additional_details_broker ?? '';
            
            $services = $sourceData->services ?? '';
            $this->services = is_string($services) ? json_decode($services, true) ?? [] : (array) $services;
            
            $otherServices = $sourceData->other_services ?? '';
            $this->other_services = is_string($otherServices) ? json_decode($otherServices, true) ?? [] : (array) $otherServices;
            $this->other_services_enabled = !empty($this->other_services);
        }
    }



    public function render()
    {
        return view('livewire.tenant.tenant-agent-auction-bid-counter', [
            'pab' => $this->pab, // explicitly pass if you like
            'bidId' => $this->bidId, // explicitly pass if you like
            'property_type' => $this->property_type, // explicitly pass if you like
            'parent_counter_id' => $this->parent_counter_id, // explicitly pass if you like
        ])->extends('layouts.main')
            ->section('content');
    }


    public function submit()
    {
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
            session()->flash('success', 'Counter bid has been submitted successfully!');

            // ✅ Redirect to homepage
            return redirect()->route('tenant.agent.auction.view', $this->pab->id);
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
        $counterBid->saveMeta('lease_value', $this->lease_value);
        $counterBid->saveMeta('purchase_type', $this->purchase_type);
        $counterBid->saveMeta('purchase_value', $this->purchase_value);

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
    }
}
