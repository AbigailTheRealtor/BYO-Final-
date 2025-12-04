<?php

namespace App\Http\Livewire\Landlord;

use Livewire\Component;
use App\Models\LandlordCounterTerm;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LandlordAgentAuctionCounterTerm extends Component
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

    public $services = [];
    public bool $other_services_enabled = false;
    public array $other_services = []; // Always initialize as an array

    // ===== Broker Lease Fee (Residential + Commercial shared selector) =====
    public $purchase_fee_type = '';

    // Residential variants
    public $purchase_fee_flat_type = '$';   // $ | %
    public $purchase_fee_flat = '';
    public $purchase_fee_rental_period = '';         // %
    public $purchase_fee_percentage_combo = '';      // %
    public $purchase_fee_flat_combo = '';            // % (first month)
    public $purchase_fee_other = '';                 // text

    // Commercial variants
    public $purchase_fee_net_aggregate = '';         // %
    public $purchase_fee_gross_rent = '';            // %
    public $sales_tax_option_gross = '';             // including | excluding
    public $purchase_fee_monthly_percentage = '';    // %
    public $purchase_fee_months = '';                // #
    public $sales_tax_option_monthly = '';           // including | excluding
    public $purchase_fee_flat_commercial = '';       // $
    public $sales_tax_option_flat = '';              // including | excluding
    public $purchase_fee_purchase_price = '';        // (kept if you later un-comment)
    public $purchase_fee_other_commercial = '';      // text

    // ===== Lease-Option Agreement =====
    public $interested_lease_option_agreement = '';  // Yes | No
    public string $lease_type = 'percent';           // percent | flat
    public $lease_value = null;                      // number
    public string $purchase_type = 'percent';        // percent | flat
    public $purchase_value = null;                   // number

    // ===== Interested in Selling (Purchase Fee) =====
    public $interested_in_selling = '';              // Yes | No
    public $interested_in_selling_type = '';         // select
    public $landlord_broker_purchase_price = '';     // %
    public $landlord_broker_percentage_price = '';   // %
    public $landlord_broker_dollar_price = '';       // $
    public string $lease_fee_flat_type = '$';        // used as suffix chooser in Flat Fee
    public $landlord_broker_flate_fee = '';          // $ or %
    public $landlord_broker_other = '';              // text

    // ===== Payment Timing for Broker Fees =====
    // Residential
    public $broker_fee_timing = '';                  // from_rent | after_lease | after_rent | other (or commercial values below)
    public $broker_fee_days_from_rent = '';
    public $broker_fee_days_after_lease = '';
    public $broker_fee_days_after_rent = '';
    public $broker_fee_timing_other = '';

    // Commercial extra fields (UI has a “split” idea even if not fully used)
    public $split_payment_due = '';                  // optional
    public $split_payment_due_other = '';
    public $broker_fee_days_after_due_event = '';

    // ===== Lease Renewal / Extension Fee =====
    public $renewal_fee_type = '';
    // Residential
    public $renewal_fee_percentage = '';             // %
    public $renewal_fee_lease_value = '';            // %
    public $renewal_fee_first_month = '';            // %
    public $renewal_fee_flat_free = '';              // $
    public $renewal_fee_custom = '';                 // text
    // Commercial extras
    public $renewal_fee_sales_tax_lease_value = '';  // including | excluding
    public $renewal_fee_no_of_months = '';           // #
    public $renewal_fee_sales_tax_first_month = '';  // including | excluding
    public $renewal_fee_sales_tax_flat_fee = '';     // including | excluding

    // ===== Expansion Commission (Commercial only) =====
    public $expansion_commission_percentage = '';    // %

    // ===== Tenant’s Broker (Residential only) =====
    public $tenant_broker_commission_structure = ''; // select
    public $tenant_broker_fee_structure = '';        // select
    public $tenant_broker_percentage = '';           // %
    public $tenant_broker_gross_lease = '';          // %
    public $tenant_broker_first_month_rent = '';     // %
    public $tenant_broker_flat_fee = '';             // $
    public $tenant_broker_other = '';                // text

    // ===== Agreement Settings =====
    public $protection_period = '';                  // #
    public $early_termination_fee_option = '';       // yes | no
    public $early_termination_fee_amount = '';       // $

    // Agency Agreement
    public $agency_agreement_timeframe = '';         // 3/6/9/12 Months | Other
    public $agency_agreement_custom = '';            // when Other

    // Property Management
    public $interested_in_property_management = '';  // yes | no
    public $interested_in_property_management_fee = ''; // select
    public $interested_in_property_management_fee_gross_lease = '';   // %
    public $interested_in_property_management_fee_rental_periord = ''; // %
    public $interested_in_property_management_fee_flate_free = '';     // $
    public $interested_in_property_management_fee_other = '';          // text

    // Brokerage relationship + Additional terms
    public $brokerage_relationship = '';             // select
    public $additional_details_broker = '';          // textarea
    public $showEnhancements = false;
    public $showCustomEnhancement = false;
    public ?int $counterTermId = null;   // <— track existing record for edit
    public $lease_fee_flat = '';

    public $photo_enhancements = [];
    public $custom_enhancement = '';


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

        // EDIT MODE: Try load existing counter term by auction id
        $existing = LandlordCounterTerm::with('meta')
            ->where('landlord_agent_auction_id', $this->pab->id)
            ->first();

        if ($existing) {
            $this->counterTermId = $existing->id;
            $this->hydrateFromMetaMap($existing->meta->pluck('meta_value', 'meta_key')->toArray());
        } else {
            $auction = \App\Models\LandlordAgentAuction::find($this->pab->id);
            // $this->additional_details = $auction->get->additional_details ?? '';

            $this->services = is_string($auction->get->services) ? json_decode($auction->get->services, true) ?? [] : (array)$auction->get->services;
            $this->other_services = is_string($auction->get->other_services) ? json_decode($auction->get->other_services, true) ?? [] : (array)$auction->get->other_services;

            $this->other_services_enabled = $auction->get->other_services_enabled;
            $this->service_type = $auction->get->service_type;
            $this->user_type = $auction->get->user_type ?? '';
            $this->property_type = $auction->get->property_type ?? '';

            // Lease Option Agreement
            $this->interested_lease_option_agreement = $auction->get->interested_lease_option_agreement ?? '';
            $this->lease_type = $auction->get->lease_type ?? 'percent';
            $this->lease_value = $auction->get->lease_value ?? '';
            $this->purchase_type = $auction->get->purchase_type ?? 'percent';
            $this->purchase_value = $auction->get->purchase_value ?? '';

            // Residential Property Lease Fee
            $this->purchase_fee_type = $auction->get->purchase_fee_type ?? '';
            $this->purchase_fee_flat = $auction->get->purchase_fee_flat ?? '';
            $this->purchase_fee_rental_period = $auction->get->purchase_fee_rental_period ?? '';
            $this->purchase_fee_percentage_combo = $auction->get->purchase_fee_percentage_combo ?? '';
            $this->purchase_fee_flat_combo = $auction->get->purchase_fee_flat_combo ?? '';
            $this->purchase_fee_other = $auction->get->purchase_fee_other ?? '';

            // Commercial Property Lease Fee
            $this->purchase_fee_net_aggregate = $auction->get->purchase_fee_net_aggregate ?? '';
            $this->purchase_fee_gross_rent = $auction->get->purchase_fee_gross_rent ?? '';
            $this->sales_tax_option_gross = $auction->get->sales_tax_option_gross ?? '';
            $this->purchase_fee_monthly_percentage = $auction->get->purchase_fee_monthly_percentage ?? '';
            $this->purchase_fee_months = $auction->get->purchase_fee_months ?? '';
            $this->sales_tax_option_monthly = $auction->get->sales_tax_option_monthly ?? '';
            $this->purchase_fee_flat_commercial = $auction->get->purchase_fee_flat_commercial ?? '';
            $this->sales_tax_option_flat = $auction->get->sales_tax_option_flat ?? '';
            $this->purchase_fee_purchase_price = $auction->get->purchase_fee_purchase_price ?? '';
            $this->purchase_fee_other_commercial = $auction->get->purchase_fee_other_commercial ?? '';

            // Interested in Selling
            $this->interested_in_selling = $auction->get->interested_in_selling ?? '';
            $this->interested_in_selling_type = $auction->get->interested_in_selling_type ?? '';
            $this->landlord_broker_purchase_price = $auction->get->landlord_broker_purchase_price ?? '';
            $this->landlord_broker_percentage_price = $auction->get->landlord_broker_percentage_price ?? '';
            $this->landlord_broker_dollar_price = $auction->get->landlord_broker_dollar_price ?? '';
            $this->landlord_broker_flate_fee = $auction->get->landlord_broker_flate_fee ?? '';
            $this->landlord_broker_other = $auction->get->landlord_broker_other ?? '';

            // Payment Timing
            $this->broker_fee_timing = $auction->get->broker_fee_timing ?? '';
            $this->broker_fee_days_from_rent = $auction->get->broker_fee_days_from_rent ?? '';
            $this->broker_fee_days_after_lease = $auction->get->broker_fee_days_after_lease ?? '';
            $this->broker_fee_days_after_rent = $auction->get->broker_fee_days_after_rent ?? '';
            $this->broker_fee_timing_other = $auction->get->broker_fee_timing_other ?? '';
            $this->split_payment_due = $auction->get->split_payment_due ?? '';
            $this->split_payment_due_other = $auction->get->split_payment_due_other ?? '';
            $this->broker_fee_days_after_due_event = $auction->get->broker_fee_days_after_due_event ?? '';

            // Renewal/Extension Fees
            $this->renewal_fee_type = $auction->get->renewal_fee_type ?? '';
            $this->renewal_fee_percentage = $auction->get->renewal_fee_percentage ?? '';
            $this->renewal_fee_lease_value = $auction->get->renewal_fee_lease_value ?? '';
            $this->renewal_fee_first_month = $auction->get->renewal_fee_first_month ?? '';
            $this->renewal_fee_flat_free = $auction->get->renewal_fee_flat_free ?? '';
            $this->renewal_fee_custom = $auction->get->renewal_fee_custom ?? '';
            $this->renewal_fee_sales_tax_lease_value = $auction->get->renewal_fee_sales_tax_lease_value ?? '';
            $this->renewal_fee_no_of_months = $auction->get->renewal_fee_no_of_months ?? '';
            $this->renewal_fee_sales_tax_first_month = $auction->get->renewal_fee_sales_tax_first_month ?? '';
            $this->renewal_fee_sales_tax_flat_fee = $auction->get->renewal_fee_sales_tax_flat_fee ?? '';

            // Commercial Expansion Commission
            $this->expansion_commission_percentage = $auction->get->expansion_commission_percentage ?? '';

            // Tenant's Broker Commission (Residential)
            $this->tenant_broker_commission_structure = $auction->get->tenant_broker_commission_structure ?? '';
            $this->tenant_broker_fee_structure = $auction->get->tenant_broker_fee_structure ?? '';
            $this->tenant_broker_percentage = $auction->get->tenant_broker_percentage ?? '';
            $this->tenant_broker_gross_lease = $auction->get->tenant_broker_gross_lease ?? '';
            $this->tenant_broker_first_month_rent = $auction->get->tenant_broker_first_month_rent ?? '';
            $this->tenant_broker_flat_fee = $auction->get->tenant_broker_flat_fee ?? '';
            $this->tenant_broker_other = $auction->get->tenant_broker_other ?? '';

            // Other important properties
            $this->protection_period = $auction->get->protection_period ?? '';
            $this->early_termination_fee_option = $auction->get->early_termination_fee_option ?? '';
            $this->early_termination_fee_amount = $auction->get->early_termination_fee_amount ?? '';
            $this->agency_agreement_timeframe = $auction->get->agency_agreement_timeframe ?? '';
            $this->agency_agreement_custom = $auction->get->agency_agreement_custom ?? '';
            $this->interested_in_property_management = $auction->get->interested_in_property_management ?? '';
            $this->interested_in_property_management_fee = $auction->get->interested_in_property_management_fee ?? '';
            $this->interested_in_property_management_fee_gross_lease = $auction->get->interested_in_property_management_fee_gross_lease ?? '';
            $this->interested_in_property_management_fee_rental_periord = $auction->get->interested_in_property_management_fee_rental_periord ?? '';
            $this->interested_in_property_management_fee_flate_free = $auction->get->interested_in_property_management_fee_flate_free ?? '';
            $this->interested_in_property_management_fee_other = $auction->get->interested_in_property_management_fee_other ?? '';
            $this->brokerage_relationship = $auction->get->brokerage_relationship ?? '';
            $this->additional_details_broker = $auction->get->additional_details_broker ?? '';
        }
    }

    private function hydrateFromMetaMap(array $m): void
    {
        // Simple scalar/meta -> property mapping
        $assign = [
            'additional_details',
            // === Purchase / Lease Fee Section ===
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

            // === Lease-Option Agreement ===
            'interested_lease_option_agreement',
            'lease_type',
            'lease_value',
            'purchase_type',
            'purchase_value',

            // === Interested in Selling ===
            'interested_in_selling',
            'interested_in_selling_type',
            'landlord_broker_purchase_price',
            'landlord_broker_percentage_price',
            'landlord_broker_dollar_price',
            'landlord_broker_flate_fee',
            'landlord_broker_flate_fee_type',
            'landlord_broker_other',
            'lease_fee_flat_type', // reused for flat/percent toggle

            // === Payment Timing ===
            'broker_fee_timing',
            'broker_fee_days_from_rent',
            'broker_fee_days_after_lease',
            'broker_fee_days_after_rent',
            'broker_fee_timing_other',
            'split_payment_due',
            'split_payment_due_other',
            'broker_fee_days_after_due_event',

            // === Renewal / Extension Fee ===
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

            // === Expansion Commission (Commercial Only) ===
            'expansion_commission_percentage',

            // === Tenant Broker Fee ===
            'tenant_broker_commission_structure',
            'tenant_broker_fee_structure',
            'tenant_broker_percentage',
            'tenant_broker_gross_lease',
            'tenant_broker_first_month_rent',
            'tenant_broker_flat_fee',
            'tenant_broker_other',

            // === Protection Period & Termination ===
            'protection_period',
            'early_termination_fee_option',
            'early_termination_fee_amount',

            // === Agency Agreement ===
            'agency_agreement_timeframe',
            'agency_agreement_custom',

            // === Property Management ===
            'interested_in_property_management',
            'interested_in_property_management_fee',
            'interested_in_property_management_fee_gross_lease',
            'interested_in_property_management_fee_rental_periord',
            'interested_in_property_management_fee_flate_free',
            'interested_in_property_management_fee_other',

            // === Brokerage Relationship ===
            'brokerage_relationship',

            // === Additional Terms ===
            'additional_details_broker',
            'other_services_enabled',
        ];


        foreach ($assign as $key) {
            if (array_key_exists($key, $m)) {
                $this->$key = $m[$key];
            }
        }

        // Arrays (JSON) — services, other_services
        if (isset($m['services'])) {
            $decoded = json_decode($m['services'], true);
            $this->services = is_array($decoded) ? $decoded : [];
        }

        if (isset($m['other_services'])) {
            $decoded = json_decode($m['other_services'], true);
            $this->other_services = is_array($decoded) ? array_values($decoded) : [];
        }

        // Booleans or flags that may be stored as strings
        if (isset($m['other_services_enabled'])) {
            $this->other_services_enabled = filter_var($m['other_services_enabled'], FILTER_VALIDATE_BOOLEAN)
                || $m['other_services_enabled'] === '1';
        }
    }

    public function render()
    {
        return view('livewire.landlord.landlord-agent-auction-counter-term', [
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

            if ($this->counterTermId) {
                // UPDATE same record
                $counterTerm = LandlordCounterTerm::findOrFail($this->counterTermId);
                // ensure base columns still correct if you want
                $counterTerm->update([
                    'property_type' => $this->property_type,
                    'parent_counter_id' => $this->parent_counter_id,
                ]);
            } else {
                $counterTerm = LandlordCounterTerm::create([
                    'user_id' => Auth::id(),
                    'landlord_agent_auction_id' => $this->pab->id,
                    'property_type' => $this->property_type,
                    'parent_counter_id' => $this->parent_counter_id,
                ]);

                $this->counterTermId = $counterTerm->id; // track after create
            }


            // 2. Save all meta data
            $this->saveAllMetaData($counterTerm);

            DB::commit();
            session()->flash('success', $this->counterTermId ? 'Counter terms updated!' : 'Counter terms submitted!');
            return redirect()->route('landlord.agent.auctions.list');
        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', 'Error saving counter bid: ' . $e->getMessage());
        }
    }



    private function saveAllMetaData($counterTerm)
    {
        $counterTerm->saveMeta('services', json_encode($this->services));
        $counterTerm->saveMeta('other_services', json_encode($this->other_services ?? null));

        $counterTerm->saveMeta('other_services_enabled', $this->other_services_enabled);
        $counterTerm->saveMeta('additional_details', $this->additional_details);

        // ===== Broker Lease Fee (shared selector) =====
        $counterTerm->saveMeta('purchase_fee_type', $this->purchase_fee_type);

        // Residential
        $counterTerm->saveMeta('purchase_fee_flat_type', $this->purchase_fee_flat_type);
        $counterTerm->saveMeta('purchase_fee_flat', $this->purchase_fee_flat);
        $counterTerm->saveMeta('purchase_fee_rental_period', $this->purchase_fee_rental_period);
        $counterTerm->saveMeta('purchase_fee_percentage_combo', $this->purchase_fee_percentage_combo);
        $counterTerm->saveMeta('purchase_fee_flat_combo', $this->purchase_fee_flat_combo);
        $counterTerm->saveMeta('purchase_fee_other', $this->purchase_fee_other);

        // Commercial
        $counterTerm->saveMeta('purchase_fee_net_aggregate', $this->purchase_fee_net_aggregate);
        $counterTerm->saveMeta('purchase_fee_gross_rent', $this->purchase_fee_gross_rent);
        $counterTerm->saveMeta('sales_tax_option_gross', $this->sales_tax_option_gross);
        $counterTerm->saveMeta('purchase_fee_monthly_percentage', $this->purchase_fee_monthly_percentage);
        $counterTerm->saveMeta('purchase_fee_months', $this->purchase_fee_months);
        $counterTerm->saveMeta('sales_tax_option_monthly', $this->sales_tax_option_monthly);
        $counterTerm->saveMeta('purchase_fee_flat_commercial', $this->purchase_fee_flat_commercial);
        $counterTerm->saveMeta('sales_tax_option_flat', $this->sales_tax_option_flat);
        $counterTerm->saveMeta('purchase_fee_purchase_price', $this->purchase_fee_purchase_price);
        $counterTerm->saveMeta('purchase_fee_other_commercial', $this->purchase_fee_other_commercial);

        // ===== Lease-Option Agreement =====
        $counterTerm->saveMeta('interested_lease_option_agreement', $this->interested_lease_option_agreement);
        $counterTerm->saveMeta('lease_type', $this->lease_type);
        $counterTerm->saveMeta('lease_value', $this->lease_value);
        $counterTerm->saveMeta('purchase_type', $this->purchase_type);
        $counterTerm->saveMeta('purchase_value', $this->purchase_value);

        // ===== Interested in Selling =====
        $counterTerm->saveMeta('interested_in_selling', $this->interested_in_selling);
        $counterTerm->saveMeta('interested_in_selling_type', $this->interested_in_selling_type);
        $counterTerm->saveMeta('landlord_broker_purchase_price', $this->landlord_broker_purchase_price);
        $counterTerm->saveMeta('landlord_broker_percentage_price', $this->landlord_broker_percentage_price);
        $counterTerm->saveMeta('landlord_broker_dollar_price', $this->landlord_broker_dollar_price);
        $counterTerm->saveMeta('lease_fee_flat_type', $this->lease_fee_flat_type); // used as suffix chooser
        $counterTerm->saveMeta('landlord_broker_flate_fee', $this->landlord_broker_flate_fee);
        $counterTerm->saveMeta('landlord_broker_other', $this->landlord_broker_other);

        // ===== Payment Timing for Broker Fees =====
        $counterTerm->saveMeta('broker_fee_timing', $this->broker_fee_timing);
        $counterTerm->saveMeta('broker_fee_days_from_rent', $this->broker_fee_days_from_rent);
        $counterTerm->saveMeta('broker_fee_days_after_lease', $this->broker_fee_days_after_lease);
        $counterTerm->saveMeta('broker_fee_days_after_rent', $this->broker_fee_days_after_rent);
        $counterTerm->saveMeta('broker_fee_timing_other', $this->broker_fee_timing_other);
        // Commercial extras
        $counterTerm->saveMeta('split_payment_due', $this->split_payment_due);
        $counterTerm->saveMeta('split_payment_due_other', $this->split_payment_due_other);
        $counterTerm->saveMeta('broker_fee_days_after_due_event', $this->broker_fee_days_after_due_event);

        // ===== Lease Renewal / Extension Fee =====
        $counterTerm->saveMeta('renewal_fee_type', $this->renewal_fee_type);
        // Residential
        $counterTerm->saveMeta('renewal_fee_percentage', $this->renewal_fee_percentage);
        $counterTerm->saveMeta('renewal_fee_lease_value', $this->renewal_fee_lease_value);
        $counterTerm->saveMeta('renewal_fee_first_month', $this->renewal_fee_first_month);
        $counterTerm->saveMeta('renewal_fee_flat_free', $this->renewal_fee_flat_free);
        $counterTerm->saveMeta('renewal_fee_custom', $this->renewal_fee_custom);
        // Commercial extras
        $counterTerm->saveMeta('renewal_fee_sales_tax_lease_value', $this->renewal_fee_sales_tax_lease_value);
        $counterTerm->saveMeta('renewal_fee_no_of_months', $this->renewal_fee_no_of_months);
        $counterTerm->saveMeta('renewal_fee_sales_tax_first_month', $this->renewal_fee_sales_tax_first_month);
        $counterTerm->saveMeta('renewal_fee_sales_tax_flat_fee', $this->renewal_fee_sales_tax_flat_fee);

        // ===== Expansion (Commercial) =====
        $counterTerm->saveMeta('expansion_commission_percentage', $this->expansion_commission_percentage);

        // ===== Tenant’s Broker (Residential) =====
        $counterTerm->saveMeta('tenant_broker_commission_structure', $this->tenant_broker_commission_structure);
        $counterTerm->saveMeta('tenant_broker_fee_structure', $this->tenant_broker_fee_structure);
        $counterTerm->saveMeta('tenant_broker_percentage', $this->tenant_broker_percentage);
        $counterTerm->saveMeta('tenant_broker_gross_lease', $this->tenant_broker_gross_lease);
        $counterTerm->saveMeta('tenant_broker_first_month_rent', $this->tenant_broker_first_month_rent);
        $counterTerm->saveMeta('tenant_broker_flat_fee', $this->tenant_broker_flat_fee);
        $counterTerm->saveMeta('tenant_broker_other', $this->tenant_broker_other);
        $counterTerm->saveMeta('lease_fee_flat', $this->lease_fee_flat);

        // ===== Agreement Settings =====
        $counterTerm->saveMeta('protection_period', $this->protection_period);
        $counterTerm->saveMeta('early_termination_fee_option', $this->early_termination_fee_option);
        $counterTerm->saveMeta('early_termination_fee_amount', $this->early_termination_fee_amount);
        $counterTerm->saveMeta('agency_agreement_timeframe', $this->agency_agreement_timeframe);
        $counterTerm->saveMeta('agency_agreement_custom', $this->agency_agreement_custom);

        // Property Management
        $counterTerm->saveMeta('interested_in_property_management', $this->interested_in_property_management);
        $counterTerm->saveMeta('interested_in_property_management_fee', $this->interested_in_property_management_fee);
        $counterTerm->saveMeta('interested_in_property_management_fee_gross_lease', $this->interested_in_property_management_fee_gross_lease);
        $counterTerm->saveMeta('interested_in_property_management_fee_rental_periord', $this->interested_in_property_management_fee_rental_periord);
        $counterTerm->saveMeta('interested_in_property_management_fee_flate_free', $this->interested_in_property_management_fee_flate_free);
        $counterTerm->saveMeta('interested_in_property_management_fee_other', $this->interested_in_property_management_fee_other);

        // Brokerage + Additional
        $counterTerm->saveMeta('brokerage_relationship', $this->brokerage_relationship);
        $counterTerm->saveMeta('additional_details_broker', $this->additional_details_broker);
    }
}
