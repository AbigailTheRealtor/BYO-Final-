<?php

namespace App\Http\Livewire\Buyer;

use Livewire\Component;
use App\Models\BuyerCounterTerm;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BuyerAgentAuctionCounterTerm extends Component
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

    public $additional_details = '';
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


    // Broker
public $interested_purchase_fee_type = '';
public $commission_structure = '';
public $purchase_fee_type = '';
public $purchase_fee_flat = '';
public $purchase_fee_percentage = '';
public $purchase_fee_percentage_combo = '';
public $purchase_fee_flat_combo = '';
public $purchase_fee_other = '';

public $interested_lease_option = ''; // Yes/No for Lease Agreement
public $lease_fee_type = '';
public $lease_fee_flat = '';
public $lease_fee_percentage = '';
public $lease_fee_percentage_monthly_rent = '';
public $lease_fee_percentage_monthly_number = '';
public $lease_fee_flat_combo = '';
public $lease_fee_percentage_combo = '';
public $lease_fee_percentage_net = '';
public $lease_fee_flat_combo_net = '';
public $lease_fee_percentage_combo_net = '';
public $lease_fee_other = '';

public $interested_lease_option_agreement = ''; // Yes/No for Lease-Option Agreement
public $lease_type = 'percent';     // 'percent' | 'flat'
public $lease_value = '';    // numeric
public $purchase_type = 'percent';  // 'percent' | 'flat'
public $purchase_value = ''; // numeric

public $protection_period = '';
public $early_termination_fee_option = '';  // yes | no
public $early_termination_fee_amount = '';
public $retainer_fee_option = '';           // yes | no
public $retainer_fee_amount = '';
public $retainer_fee_application = '';      // Applied toward final compensation | Charged in addition to final compensation
public $agency_agreement_timeframe = '';    // 3 Months | 6 Months | 9 Months | 12 Months | custom
public $agency_agreement_custom = '';
public $brokerage_relationship = '';
public $additional_details_broker = '';
 public $purchase_fee_flat_type = '$';
    public string $lease_fee_flat_type = '$';
    public $gap_payment_type = '$';

        public $gap_payment_amount = '';
  public $down_payment_type = '$';
    public $down_payment_amount = '';
      public $seller_financing_type = '$';
    public $seller_financing_amount = '';
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


    public ?int $counterTermId = null;   // <— track existing record for edit

    public function mount($pab, $bidId)
    {
        $this->pab   = $pab;
        $this->bidId = $bidId;
        $this->property_type = $pab->get->property_type;

        // EDIT MODE: Try load existing counter term by auction id
        $existing = BuyerCounterTerm::with('meta')
            ->where('buyer_agent_auction_id', $this->pab->id)
            ->first();

        if ($existing) {
            $this->counterTermId = $existing->id;
            $this->hydrateFromMetaMap($existing->meta->pluck('meta_value', 'meta_key')->toArray());
        }
    }



    public function render()
    {
        return view('livewire.buyer.buyer-agent-auction-counter-term', [
            'pab' => $this->pab, // explicitly pass if you like
            'bidId' => $this->bidId, // explicitly pass if you like
            'property_type' => $this->property_type, // explicitly pass if you like
            'parent_counter_id' => $this->parent_counter_id, // explicitly pass if you like
        ])->extends('layouts.main')
            ->section('content');
    }

    /**
     * Hydrate Livewire properties from meta map (key => value).
     * Only assigns keys that exist as public properties.
     */
    private function hydrateFromMetaMap(array $m): void
    {
        // Simple scalar/meta -> property mapping
        $assign = [
            'additional_details',
            'commission_structure',
            'lease_fee_type',
            'lease_fee_flat',
            'lease_fee_percentage',
            'interested_lease_option',
            'lease_fee_percentage_monthly_rent',
            'lease_fee_flat_combo',
            'lease_fee_percentage_combo',
            'lease_fee_percentage_net',
            'lease_fee_flat_combo_net',
            'lease_fee_percentage_combo_net',
            'lease_fee_other',
            'lease_fee_percentage_monthly_number',
            'interested_purchase_fee_type',
            'purchase_fee_type',
            'purchase_fee_flat',
            'purchase_fee_percentage',
            'purchase_fee_percentage_combo',
            'purchase_fee_flat_combo',
            'purchase_fee_other',
            'interested_lease_option_agreement',
            'lease_type',
            'lease_value',
            'purchase_type',
            'purchase_value',
            'protection_period',
            'early_termination_fee_option',
            'early_termination_fee_amount',
            'retainer_fee_option',
            'retainer_fee_amount',
            'retainer_fee_application',
            'agency_agreement_timeframe',
            'agency_agreement_custom',
            'brokerage_relationship',
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
    public function submit()
    {
        try {
            DB::beginTransaction();



            if ($this->counterTermId) {
                // UPDATE same record
                $counterTerm = BuyerCounterTerm::findOrFail($this->counterTermId);
                // ensure base columns still correct if you want
                $counterTerm->update([
                    'property_type' => $this->property_type,
                    'parent_counter_id' => $this->parent_counter_id,
                ]);
            } else {
                // CREATE new record
                $counterTerm = BuyerCounterTerm::create([
                    'user_id' => Auth::id(),
                    'buyer_agent_auction_id' => $this->pab->id,
                    'property_type' => $this->property_type,
                    'parent_counter_id' => $this->parent_counter_id,
                ]);
                $this->counterTermId = $counterTerm->id; // track after create
            }

            // 2. Save all meta data
            $this->saveAllMetaData($counterTerm);

            DB::commit();
            // session()->flash('success', 'Counter terms has been submitted successfully!');

            session()->flash('success', $this->counterTermId ? 'Counter terms updated!' : 'Counter terms submitted!');
            // ✅ Redirect to homepage
            return redirect()->route('buyer.agent.auctions.list');
            // Optional: reset form or redirect
            // $this->resetForm();

        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', 'Error saving counter bid: ' . $e->getMessage());
        }
    }


    private function saveAllMetaData($counterTerm)
    {
        // Broker Compensation & Fees
        $counterTerm->saveMeta('additional_details', $this->additional_details);
        // Tenant Services
        $counterTerm->saveMeta('services', json_encode($this->services));
        $counterTerm->saveMeta('other_services_enabled', $this->other_services_enabled);
        $counterTerm->saveMeta('other_services', json_encode($this->other_services ?? []));


        $counterTerm->saveMeta('commission_structure', $this->commission_structure);

        // Lease Fee Structure (cover residential & commercial variants)
        $counterTerm->saveMeta('interested_lease_option', $this->interested_lease_option);
        $counterTerm->saveMeta('lease_fee_type', $this->lease_fee_type);
        $counterTerm->saveMeta('lease_fee_flat', $this->lease_fee_flat);
        $counterTerm->saveMeta('lease_fee_percentage', $this->lease_fee_percentage);
        $counterTerm->saveMeta('lease_fee_percentage_monthly_rent', $this->lease_fee_percentage_monthly_rent);
        $counterTerm->saveMeta('lease_fee_flat_combo', $this->lease_fee_flat_combo);
        $counterTerm->saveMeta('lease_fee_percentage_combo', $this->lease_fee_percentage_combo);
        $counterTerm->saveMeta('lease_fee_percentage_net', $this->lease_fee_percentage_net);
        $counterTerm->saveMeta('lease_fee_flat_combo_net', $this->lease_fee_flat_combo_net);
        $counterTerm->saveMeta('lease_fee_percentage_combo_net', $this->lease_fee_percentage_combo_net);
        $counterTerm->saveMeta('lease_fee_other', $this->lease_fee_other);
        $counterTerm->saveMeta('lease_fee_percentage_monthly_number', $this->lease_fee_percentage_monthly_number);


        // Purchase Fee Structure
        $counterTerm->saveMeta('interested_purchase_fee_type', $this->interested_purchase_fee_type);
        $counterTerm->saveMeta('purchase_fee_type', $this->purchase_fee_type);
        $counterTerm->saveMeta('purchase_fee_flat', $this->purchase_fee_flat);
        $counterTerm->saveMeta('purchase_fee_percentage', $this->purchase_fee_percentage);
        $counterTerm->saveMeta('purchase_fee_percentage_combo', $this->purchase_fee_percentage_combo);
        $counterTerm->saveMeta('purchase_fee_flat_combo', $this->purchase_fee_flat_combo);
        $counterTerm->saveMeta('purchase_fee_other', $this->purchase_fee_other);


        // Lease-Option Agreement
        $counterTerm->saveMeta('interested_lease_option_agreement', $this->interested_lease_option_agreement);
        $counterTerm->saveMeta('lease_type', $this->lease_type);
        $counterTerm->saveMeta('lease_value', $this->lease_value);
        $counterTerm->saveMeta('purchase_type', $this->purchase_type);
        $counterTerm->saveMeta('purchase_value', $this->purchase_value);

        $counterTerm->saveMeta('purchase_fee_flat_type', $this->purchase_fee_flat_type);

        // Broker Terms & Agreements
        $counterTerm->saveMeta('protection_period', $this->protection_period);
        $counterTerm->saveMeta('early_termination_fee_option', $this->early_termination_fee_option);
        $counterTerm->saveMeta('early_termination_fee_amount', $this->early_termination_fee_amount);
        $counterTerm->saveMeta('retainer_fee_option', $this->retainer_fee_option);
        $counterTerm->saveMeta('retainer_fee_amount', $this->retainer_fee_amount);
        $counterTerm->saveMeta('retainer_fee_application', $this->retainer_fee_application);
        $counterTerm->saveMeta('agency_agreement_timeframe', $this->agency_agreement_timeframe);
        $counterTerm->saveMeta('agency_agreement_custom', $this->agency_agreement_custom);
        $counterTerm->saveMeta('brokerage_relationship', $this->brokerage_relationship);


        // Additional Details
        $counterTerm->saveMeta('additional_details_broker', $this->additional_details_broker ?? null);
    }
}
