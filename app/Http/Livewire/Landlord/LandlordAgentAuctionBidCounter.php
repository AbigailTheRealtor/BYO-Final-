<?php

namespace App\Http\Livewire\Landlord;

use Livewire\Component;
use App\Models\LandlordCounterBidding;
use App\Models\LandlordCounterTerm;
use App\Notifications\CounterBidSubmittedNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\LandlordAgentAuctionBid;

class LandlordAgentAuctionBidCounter extends Component
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




    public $additional_details;


    // Tenant Services
    public $services = [];
    public bool $other_services_enabled = false;
    public array $other_services = []; // Always initialize as an array
    // public $flat_fee_services = [];
    public $total_flat_fee = 0;
    public $total_marketing_fee = 0;




    // For Lease Option Agreement
    public $interested_lease_option_agreement = '';
    public $lease_type = 'percent'; // 'percent' or 'flat'
    public $lease_value = '';
    public $purchase_type = 'percent'; // 'percent' or 'flat'
    public $purchase_value = '';

    // For Residential Property Lease Fee
    public $purchase_fee_type = '';
    public $purchase_fee_flat = '';
    public $purchase_fee_rental_period = '';
    public $purchase_fee_percentage_combo = '';
    public $purchase_fee_flat_combo = '';
    public $purchase_fee_other = '';

    // For Commercial Property Lease Fee
    public $purchase_fee_net_aggregate = '';
    public $purchase_fee_gross_rent = '';
    public $sales_tax_option_gross = '';
    public $purchase_fee_monthly_percentage = '';
    public $purchase_fee_months = '';
    public $sales_tax_option_monthly = '';
    public $purchase_fee_flat_commercial = '';
    public $sales_tax_option_flat = '';
    public $purchase_fee_purchase_price = '';
    public $purchase_fee_other_commercial = '';

    // For Interested in Selling
    public $interested_in_selling = '';
    public $interested_in_selling_type = '';
    public $landlord_broker_purchase_price = '';
    public $landlord_broker_percentage_price = '';
    public $landlord_broker_dollar_price = '';
    public $landlord_broker_flate_fee = '';
    public $landlord_broker_other = '';

    // For Payment Timing
    public $broker_fee_timing = '';
    public $broker_fee_days_from_rent = '';
    public $broker_fee_days_after_lease = '';
    public $broker_fee_days_after_rent = '';
    public $broker_fee_timing_other = '';
    public $split_payment_due = '';
    public $split_payment_due_other = '';
    public $broker_fee_days_after_due_event = '';

    // For Renewal/Extension Fees
    public $renewal_fee_type = '';
    public $renewal_fee_percentage = '';
    public $renewal_fee_lease_value = '';
    public $renewal_fee_first_month = '';
    public $renewal_fee_flat_free = '';
    public $renewal_fee_custom = '';
    public $renewal_fee_sales_tax_lease_value = '';
    public $renewal_fee_no_of_months = '';
    public $renewal_fee_sales_tax_first_month = '';
    public $renewal_fee_sales_tax_flat_fee = '';

    // For Commercial Expansion Commission
    public $expansion_commission_percentage = '';

    // For Tenant's Broker Commission (Residential)
    public $tenant_broker_commission_structure = '';
    public $tenant_broker_fee_structure = '';
    public $tenant_broker_percentage = '';
    public $tenant_broker_gross_lease = '';
    public $tenant_broker_first_month_rent = '';
    public $tenant_broker_flat_fee = '';
    public $tenant_broker_other = '';

    // Other important properties
    public $protection_period = '';
    public $early_termination_fee_option = '';
    public $early_termination_fee_amount = '';
    public $agency_agreement_timeframe = '';
    public $agency_agreement_custom = '';
    public $interested_in_property_management = '';
    public $interested_in_property_management_fee = '';
    public $interested_in_property_management_fee_gross_lease = '';
    public $interested_in_property_management_fee_rental_periord = '';
    public $interested_in_property_management_fee_flate_free = '';
    public $interested_in_property_management_fee_other = '';
    public $brokerage_relationship = '';
    public $additional_details_broker = '';


    // Presentation & Promotional Materials
    public $presentation_link;
    public $video_upload;
    public $business_card_link;
    public $business_card;
    public $promo_material_type;
    public $promo_materials = [];
    public $promo_materials_link;

    public array $promoMaterials = [];
    // Broker
    public $commission_structure = '';
    public $purchase_fee_flat_type = '$';   // $ | %
    public string $lease_fee_flat_type = '$';        // used as suffix chooser in Flat Fee
    public $lease_fee_flat = '';

    public $photo_enhancements = [];

    public $custom_enhancement = '';

    public $showEnhancements = false;
    public $showCustomEnhancement = false;


    // Reset methods for each main field change
    public function updatedPurchaseFeeType()
    {
        $this->resetPurchaseFeeFields();
    }

    public function updatedInterestedLeaseOptionAgreement()
    {
        $this->resetLeaseOptionFields();
    }

    public function updatedInterestedInSelling()
    {
        $this->resetSellingFields();
    }

    public function updatedInterestedInSellingType()
    {
        $this->resetSellingTypeFields();
    }

    public function updatedBrokerFeeTiming()
    {
        $this->resetBrokerFeeTimingFields();
    }

    public function updatedRenewalFeeType()
    {
        $this->resetRenewalFeeFields();
    }

    public function updatedTenantBrokerCommissionStructure()
    {
        $this->resetTenantBrokerFields();
    }

    public function updatedTenantBrokerFeeStructure()
    {
        $this->resetTenantBrokerFeeFields();
    }

    public function updatedEarlyTerminationFeeOption()
    {
        $this->resetEarlyTerminationFields();
    }

    public function updatedAgencyAgreementTimeframe()
    {
        $this->resetAgencyAgreementFields();
    }

    public function updatedInterestedInPropertyManagement()
    {
        $this->resetPropertyManagementFields();
    }

    public function updatedInterestedInPropertyManagementFee()
    {
        $this->resetPropertyManagementFeeFields();
    }

    // Individual reset methods
    private function resetPurchaseFeeFields()
    {
        $fields = [
            'purchase_fee_flat',
            'purchase_fee_rental_period',
            'purchase_fee_percentage_combo',
            'purchase_fee_flat_combo',
            'purchase_fee_other',
            'purchase_fee_net_aggregate',
            'purchase_fee_gross_rent',
            'sales_tax_option_gross',
            'purchase_fee_monthly_percentage',
            'purchase_fee_months',
            'sales_tax_option_monthly',
            'purchase_fee_flat_commercial',
            'sales_tax_option_flat',
            'purchase_fee_purchase_price',
            'purchase_fee_other_commercial'
        ];

        $this->reset($fields);
    }

    private function resetLeaseOptionFields()
    {
        $this->reset([
            'lease_type',
            'lease_value',
            'purchase_type',
            'purchase_value'
        ]);
    }

    private function resetSellingFields()
    {
        $this->reset([
            'interested_in_selling_type',
            'landlord_broker_purchase_price',
            'landlord_broker_percentage_price',
            'landlord_broker_dollar_price',
            'landlord_broker_flate_fee',
            'landlord_broker_other'
        ]);
    }

    private function resetSellingTypeFields()
    {
        $this->reset([
            'landlord_broker_purchase_price',
            'landlord_broker_percentage_price',
            'landlord_broker_dollar_price',
            'landlord_broker_flate_fee',
            'landlord_broker_other'
        ]);
    }

    private function resetBrokerFeeTimingFields()
    {
        $fields = [
            'broker_fee_days_from_rent',
            'broker_fee_days_after_lease',
            'broker_fee_days_after_rent',
            'broker_fee_timing_other',
            'split_payment_due',
            'split_payment_due_other',
            'broker_fee_days_after_due_event'
        ];

        $this->reset($fields);
    }

    private function resetRenewalFeeFields()
    {
        $fields = [
            'renewal_fee_percentage',
            'renewal_fee_lease_value',
            'renewal_fee_first_month',
            'renewal_fee_flat_free',
            'renewal_fee_custom',
            'renewal_fee_sales_tax_lease_value',
            'renewal_fee_no_of_months',
            'renewal_fee_sales_tax_first_month',
            'renewal_fee_sales_tax_flat_fee'
        ];

        $this->reset($fields);
    }

    private function resetTenantBrokerFields()
    {
        $this->reset([
            'tenant_broker_fee_structure',
            'tenant_broker_percentage',
            'tenant_broker_gross_lease',
            'tenant_broker_first_month_rent',
            'tenant_broker_flat_fee',
            'tenant_broker_other'
        ]);
    }

    private function resetTenantBrokerFeeFields()
    {
        $this->reset([
            'tenant_broker_percentage',
            'tenant_broker_gross_lease',
            'tenant_broker_first_month_rent',
            'tenant_broker_flat_fee',
            'tenant_broker_other'
        ]);
    }

    private function resetEarlyTerminationFields()
    {
        $this->reset(['early_termination_fee_amount']);
    }

    private function resetAgencyAgreementFields()
    {
        $this->reset(['agency_agreement_custom']);
    }

    private function resetPropertyManagementFields()
    {
        $this->reset([
            'interested_in_property_management_fee',
            'interested_in_property_management_fee_gross_lease',
            'interested_in_property_management_fee_rental_periord',
            'interested_in_property_management_fee_flate_free',
            'interested_in_property_management_fee_other'
        ]);
    }

    private function resetPropertyManagementFeeFields()
    {
        $this->reset([
            'interested_in_property_management_fee_gross_lease',
            'interested_in_property_management_fee_rental_periord',
            'interested_in_property_management_fee_flate_free',
            'interested_in_property_management_fee_other'
        ]);
    }
    public function setType(string $which, string $type): void
    {
        if ($which === 'lease') {
            $this->lease_type = $type;
            $this->lease_value = ''; // clear lease input when switching type
        } elseif ($which === 'purchase') {
            $this->purchase_type = $type;
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

    public function updatedPhotoEnhancements()
    {
        // Automatically show custom field when "Other" is selected
        $this->showCustomEnhancement = in_array('Other', $this->photo_enhancements);
    }

    public function updatedServices()
    {
        // Automatically show enhancements when main option is selected
        if (in_array('Provide digital photo enhancements', $this->services)) {
            $this->showEnhancements = true;
        } else {
            $this->showEnhancements = false;
            $this->showCustomEnhancement = false;
        }
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
    public function mount($pab, $bidId)
    {
        $this->pab   = $pab;
        $this->bidId = $bidId;
        $this->property_type = $pab->get->property_type;

        // COUNTER BID PREFILL RULE: Agent counters with the Landlord's latest counter terms.
        // landlord_counter_terms.landlord_agent_auction_id stores BID IDs (not auction IDs).
        $landlordCounter = LandlordCounterTerm::with('meta')
            ->where('landlord_agent_auction_id', $this->bidId)
            ->where('user_id', '!=', Auth::id())
            ->latest()
            ->first();

        if ($landlordCounter) {
            $this->hydrateFromMetaMap(
                $landlordCounter->meta->pluck('meta_value', 'meta_key')->toArray()
            );
        } else {
            // No landlord counter yet — prefill from the original agent bid terms.
            $bid = LandlordAgentAuctionBid::with('meta')->find($this->bidId);
            if ($bid) {
                $this->hydrateFromMetaMap(
                    $bid->meta->pluck('meta_value', 'meta_key')->toArray()
                );
            }
        }
    }

    private function hydrateFromMetaMap(array $m): void
    {
        $assign = [
            'additional_details',
            'custom_enhancement',
            'purchase_fee_type',
            'purchase_fee_flat_type',
            'purchase_fee_flat',
            'purchase_fee_rental_period',
            'purchase_fee_percentage_combo',
            'purchase_fee_flat_combo',
            'purchase_fee_other',
            'purchase_fee_gross_rent',
            'purchase_fee_net_aggregate',
            'purchase_fee_monthly_percentage',
            'purchase_fee_months',
            'purchase_fee_flat_commercial',
            'purchase_fee_purchase_price',
            'purchase_fee_other_commercial',
            'sales_tax_option_gross',
            'sales_tax_option_flat',
            'sales_tax_option_monthly',
            'interested_lease_option_agreement',
            'lease_type',
            'lease_value',
            'purchase_type',
            'purchase_value',
            'interested_in_selling',
            'interested_in_selling_type',
            'landlord_broker_purchase_price',
            'landlord_broker_percentage_price',
            'landlord_broker_dollar_price',
            'landlord_broker_flate_fee',
            'landlord_broker_flate_fee_type',
            'landlord_broker_other',
            'lease_fee_flat_type',
            'broker_fee_timing',
            'broker_fee_days_from_rent',
            'broker_fee_days_after_lease',
            'broker_fee_days_after_rent',
            'broker_fee_timing_other',
            'split_payment_due',
            'split_payment_due_other',
            'broker_fee_days_after_due_event',
            'renewal_fee_type',
            'renewal_fee_percentage',
            'renewal_fee_lease_value',
            'renewal_fee_first_month',
            'renewal_fee_flat_free',
            'renewal_fee_custom',
            'renewal_fee_sales_tax_lease_value',
            'renewal_fee_sales_tax_flat_fee',
            'renewal_fee_sales_tax_first_month',
            'renewal_fee_no_of_months',
            'expansion_commission_percentage',
            'tenant_broker_commission_structure',
            'tenant_broker_fee_structure',
            'tenant_broker_percentage',
            'tenant_broker_gross_lease',
            'tenant_broker_first_month_rent',
            'tenant_broker_flat_fee',
            'tenant_broker_other',
            'protection_period',
            'early_termination_fee_option',
            'early_termination_fee_amount',
            'agency_agreement_timeframe',
            'agency_agreement_custom',
            'interested_in_property_management',
            'interested_in_property_management_fee',
            'interested_in_property_management_fee_gross_lease',
            'interested_in_property_management_fee_rental_periord',
            'interested_in_property_management_fee_flate_free',
            'interested_in_property_management_fee_other',
            'brokerage_relationship',
            'additional_details_broker',
            'other_services_enabled',
            'commission_structure',
        ];

        foreach ($assign as $key) {
            if (array_key_exists($key, $m) && property_exists($this, $key)) {
                $this->$key = $m[$key];
            }
        }

        if (isset($m['services'])) {
            $raw = $m['services'];
            $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
            $this->services = is_array($decoded) ? $decoded : [];
        }

        if (isset($m['other_services'])) {
            $raw = $m['other_services'];
            $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
            $this->other_services = is_array($decoded) ? array_values($decoded) : [];
        }

        if (isset($m['photo_enhancements'])) {
            $raw = $m['photo_enhancements'];
            $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
            if (is_array($decoded) && property_exists($this, 'photo_enhancements')) {
                $this->photo_enhancements = $decoded;
            }
        }

        if (isset($m['other_services_enabled'])) {
            $this->other_services_enabled = filter_var($m['other_services_enabled'], FILTER_VALIDATE_BOOLEAN)
                || $m['other_services_enabled'] === '1';
        }
    }



    public function render()
    {
        return view('livewire.landlord.landlord-agent-auction-bid-counter', [
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
            DB::beginTransaction();

            $counterBid = LandlordCounterBidding::create([
                'user_id' => Auth::id(),
                'landlord_agent_auction_id' => $this->pab->id,
                'landlord_agent_auction_bid_id' => $this->bidId,
                'property_type' => $this->property_type,
                'parent_counter_id' => $this->parent_counter_id,
            ]);

            $auction = \App\Models\LandlordAgentAuction::find($this->pab->id);
            $bid = LandlordAgentAuctionBid::find($this->bidId);

            $sender = Auth::user();

            if ($sender->id == $auction->user_id) {
                $recipient = $bid->user;
            } else {
                $recipient = $auction->user;
            }

            $recipient->notify(
                new CounterBidSubmittedNotification(
                    $bid,
                    $auction,
                    $sender,
                    $recipient->id  // 👈 required
                )
            );


            $this->saveAllMetaData($counterBid);

            DB::commit();
            session()->flash('success', 'Counter bid has been submitted successfully!');
            return redirect()->route('landlord.agent.auction.view', $this->pab->id);
        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', 'Error saving counter bid: ' . $e->getMessage());
        }
    }



    private function saveAllMetaData($counterBid)
    {



        // Save Services
        $counterBid->saveMeta('services', json_encode($this->services));
        $counterBid->saveMeta('other_services', json_encode($this->other_services ?? null));

        $counterBid->saveMeta('other_services_enabled', $this->other_services_enabled);
        $counterBid->saveMeta('additional_details', $this->additional_details);


        // Lease Option Agreement
        $counterBid->saveMeta('interested_lease_option_agreement', $this->interested_lease_option_agreement);
        $counterBid->saveMeta('lease_type', $this->lease_type);
        $counterBid->saveMeta('lease_value', str_replace(',', '', $this->lease_value ?? ''));
        $counterBid->saveMeta('purchase_type', $this->purchase_type);
        $counterBid->saveMeta('purchase_value', str_replace(',', '', $this->purchase_value ?? ''));

        // Landlord’s Broker Lease Fee (Residential and Commercial)
        $counterBid->saveMeta('purchase_fee_type', $this->purchase_fee_type);
        $counterBid->saveMeta('purchase_fee_flat', $this->purchase_fee_flat);
        $counterBid->saveMeta('purchase_fee_rental_period', $this->purchase_fee_rental_period);
        $counterBid->saveMeta('purchase_fee_percentage_combo', $this->purchase_fee_percentage_combo);
        $counterBid->saveMeta('purchase_fee_flat_combo', $this->purchase_fee_flat_combo);
        $counterBid->saveMeta('purchase_fee_other', $this->purchase_fee_other);

        // Commercial fields
        $counterBid->saveMeta('purchase_fee_net_aggregate', $this->purchase_fee_net_aggregate);
        $counterBid->saveMeta('purchase_fee_gross_rent', $this->purchase_fee_gross_rent);
        $counterBid->saveMeta('sales_tax_option_gross', $this->sales_tax_option_gross);
        $counterBid->saveMeta('purchase_fee_monthly_percentage', $this->purchase_fee_monthly_percentage);
        $counterBid->saveMeta('purchase_fee_months', $this->purchase_fee_months);
        $counterBid->saveMeta('sales_tax_option_monthly', $this->sales_tax_option_monthly);
        $counterBid->saveMeta('purchase_fee_flat_commercial', $this->purchase_fee_flat_commercial);
        $counterBid->saveMeta('sales_tax_option_flat', $this->sales_tax_option_flat);
        $counterBid->saveMeta('purchase_fee_purchase_price', $this->purchase_fee_purchase_price);
        $counterBid->saveMeta('purchase_fee_other_commercial', $this->purchase_fee_other_commercial);

        // Interested in Selling
        $counterBid->saveMeta('interested_in_selling', $this->interested_in_selling);
        $counterBid->saveMeta('interested_in_selling_type', $this->interested_in_selling_type);
        $counterBid->saveMeta('landlord_broker_purchase_price', $this->landlord_broker_purchase_price);
        $counterBid->saveMeta('landlord_broker_percentage_price', $this->landlord_broker_percentage_price);
        $counterBid->saveMeta('landlord_broker_dollar_price', $this->landlord_broker_dollar_price);
        $counterBid->saveMeta('landlord_broker_flate_fee', $this->landlord_broker_flate_fee);
        $counterBid->saveMeta('landlord_broker_other', $this->landlord_broker_other);

        // Payment Timing for Broker Fees
        $counterBid->saveMeta('broker_fee_timing', $this->broker_fee_timing);
        $counterBid->saveMeta('broker_fee_days_from_rent', $this->broker_fee_days_from_rent);
        $counterBid->saveMeta('broker_fee_days_after_lease', $this->broker_fee_days_after_lease);
        $counterBid->saveMeta('broker_fee_days_after_rent', $this->broker_fee_days_after_rent);
        $counterBid->saveMeta('broker_fee_timing_other', $this->broker_fee_timing_other);
        $counterBid->saveMeta('split_payment_due', $this->split_payment_due);
        $counterBid->saveMeta('split_payment_due_other', $this->split_payment_due_other);
        $counterBid->saveMeta('broker_fee_days_after_due_event', $this->broker_fee_days_after_due_event);

        // Lease Renewal/Extension Fee
        $counterBid->saveMeta('renewal_fee_type', $this->renewal_fee_type);
        $counterBid->saveMeta('renewal_fee_percentage', $this->renewal_fee_percentage);
        $counterBid->saveMeta('renewal_fee_lease_value', $this->renewal_fee_lease_value);
        $counterBid->saveMeta('renewal_fee_first_month', $this->renewal_fee_first_month);
        $counterBid->saveMeta('renewal_fee_flat_free', $this->renewal_fee_flat_free);
        $counterBid->saveMeta('renewal_fee_custom', $this->renewal_fee_custom);
        $counterBid->saveMeta('renewal_fee_sales_tax_lease_value', $this->renewal_fee_sales_tax_lease_value);
        $counterBid->saveMeta('renewal_fee_sales_tax_flat_fee', $this->renewal_fee_sales_tax_flat_fee);
        $counterBid->saveMeta('renewal_fee_sales_tax_first_month', $this->renewal_fee_sales_tax_first_month);
        $counterBid->saveMeta('renewal_fee_no_of_months', $this->renewal_fee_no_of_months);

        // Expansion Commission for Lease Amendment (commercial only)
        $counterBid->saveMeta('expansion_commission_percentage', $this->expansion_commission_percentage);

        // Tenant's Broker Commission Fee (residential only)
        $counterBid->saveMeta('tenant_broker_commission_structure', $this->tenant_broker_commission_structure);
        $counterBid->saveMeta('tenant_broker_fee_structure', $this->tenant_broker_fee_structure);
        $counterBid->saveMeta('tenant_broker_percentage', $this->tenant_broker_percentage);
        $counterBid->saveMeta('tenant_broker_gross_lease', $this->tenant_broker_gross_lease);
        $counterBid->saveMeta('tenant_broker_first_month_rent', $this->tenant_broker_first_month_rent);
        $counterBid->saveMeta('tenant_broker_flat_fee', $this->tenant_broker_flat_fee);
        $counterBid->saveMeta('tenant_broker_other', $this->tenant_broker_other);

        // Protection Period Timeframe
        $counterBid->saveMeta('protection_period', $this->protection_period);

        // Early Termination Fee
        $counterBid->saveMeta('early_termination_fee_option', $this->early_termination_fee_option);
        $counterBid->saveMeta('early_termination_fee_amount', $this->early_termination_fee_amount);

        // Landlord Agency Agreement Timeframe
        $counterBid->saveMeta('agency_agreement_timeframe', $this->agency_agreement_timeframe);
        $counterBid->saveMeta('agency_agreement_custom', $this->agency_agreement_custom);

        // Interested in Property Management
        $counterBid->saveMeta('interested_in_property_management', $this->interested_in_property_management);
        $counterBid->saveMeta('interested_in_property_management_fee', $this->interested_in_property_management_fee);
        $counterBid->saveMeta('interested_in_property_management_fee_gross_lease', $this->interested_in_property_management_fee_gross_lease);
        $counterBid->saveMeta('interested_in_property_management_fee_rental_periord', $this->interested_in_property_management_fee_rental_periord);
        $counterBid->saveMeta('interested_in_property_management_fee_flate_free', $this->interested_in_property_management_fee_flate_free);
        $counterBid->saveMeta('interested_in_property_management_fee_other', $this->interested_in_property_management_fee_other);

        // Acceptable Brokerage Relationship
        $counterBid->saveMeta('brokerage_relationship', $this->brokerage_relationship);

        // Additional Terms
        $counterBid->saveMeta('additional_details_broker', $this->additional_details_broker);
    }
}
