<?php

namespace App\Http\Livewire\Buyer;

use Livewire\Component;
use App\Models\BuyerCounterBidding;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BuyerAgentAuctionBidCounter extends Component
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
    public function mount($pab, $bidId)
    {

        $this->pab   = $pab;
        $this->bidId = $bidId;
        $this->property_type = $pab->get->property_type;
    }



    public function render()
    {
        return view('livewire.buyer.buyer-agent-auction-bid-counter', [
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

            // 1. Create main counter bidding record
            $counterBid = BuyerCounterBidding::create([
                'user_id' => Auth::id(),
                'buyer_agent_auction_id' => $this->pab->id,
                'buyer_agent_auction_bid_id' => $this->bidId,
                'property_type' => $this->property_type,
                'parent_counter_id' => $this->parent_counter_id,
            ]);

            // 2. Save all meta data
            $this->saveAllMetaData($counterBid);

            DB::commit();
            session()->flash('success', 'Counter bid has been submitted successfully!');

            // ✅ Redirect to homepage
            return redirect()->route('buyer.view-auction', $this->pab->id);
            // Optional: reset form or redirect
            // $this->resetForm();

        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', 'Error saving counter bid: ' . $e->getMessage());
        }
    }


    private function saveAllMetaData($counterBid)
    {
       
        // Tenant Services
        $counterBid->saveMeta('services', json_encode($this->services));
        $counterBid->saveMeta('other_services', json_encode($this->other_services ?? []));
        $counterBid->saveMeta('other_services_enabled', $this->other_services_enabled);
        $counterBid->saveMeta('total_flat_fee', $this->total_flat_fee);
        $counterBid->saveMeta('total_marketing_fee', $this->total_marketing_fee);
        $counterBid->saveMeta('additional_details', $this->additional_details);

               // Save method for Broker Compensation
                $counterBid->saveMeta('commission_structure', $this->commission_structure);

                // Purchase Fee
                $counterBid->saveMeta('purchase_fee_type', $this->purchase_fee_type);
                $counterBid->saveMeta('purchase_fee_flat', $this->purchase_fee_flat);
                $counterBid->saveMeta('purchase_fee_percentage', $this->purchase_fee_percentage);
                $counterBid->saveMeta('purchase_fee_percentage_combo', $this->purchase_fee_percentage_combo);
                $counterBid->saveMeta('purchase_fee_flat_combo', $this->purchase_fee_flat_combo);
                $counterBid->saveMeta('purchase_fee_other', $this->purchase_fee_other);

                // Lease Agreement Interest
                $counterBid->saveMeta('interested_lease_option', $this->interested_lease_option);

                // Lease Fee (only if interested_lease_option is 'Yes')
                if ($this->interested_lease_option === 'Yes') {
                    $counterBid->saveMeta('lease_fee_type', $this->lease_fee_type);
                    $counterBid->saveMeta('lease_fee_flat', $this->lease_fee_flat);
                    $counterBid->saveMeta('lease_fee_percentage', $this->lease_fee_percentage);
                    $counterBid->saveMeta('lease_fee_percentage_monthly_rent', $this->lease_fee_percentage_monthly_rent);
                    $counterBid->saveMeta('lease_fee_percentage_monthly_number', $this->lease_fee_percentage_monthly_number);
                    $counterBid->saveMeta('lease_fee_flat_combo', $this->lease_fee_flat_combo);
                    $counterBid->saveMeta('lease_fee_percentage_combo', $this->lease_fee_percentage_combo);
                    $counterBid->saveMeta('lease_fee_percentage_net', $this->lease_fee_percentage_net);
                    $counterBid->saveMeta('lease_fee_flat_combo_net', $this->lease_fee_flat_combo_net);
                    $counterBid->saveMeta('lease_fee_percentage_combo_net', $this->lease_fee_percentage_combo_net);
                    $counterBid->saveMeta('lease_fee_other', $this->lease_fee_other);
                }

                // Lease-Option Agreement Interest
                $counterBid->saveMeta('interested_lease_option_agreement', $this->interested_lease_option_agreement);

                // Lease-Option Agreement Compensation (only if interested_lease_option_agreement is 'Yes')
                if ($this->interested_lease_option_agreement === 'Yes') {
                    $counterBid->saveMeta('lease_type', $this->lease_type);
                    $counterBid->saveMeta('lease_value', str_replace(',', '', $this->lease_value ?? ''));
                    $counterBid->saveMeta('purchase_type', $this->purchase_type);
                    $counterBid->saveMeta('purchase_value', str_replace(',', '', $this->purchase_value ?? ''));
                }

            // Other Broker Terms
            $counterBid->saveMeta('protection_period', $this->protection_period);
            $counterBid->saveMeta('early_termination_fee_option', $this->early_termination_fee_option);
            $counterBid->saveMeta('early_termination_fee_amount', $this->early_termination_fee_amount);
            $counterBid->saveMeta('retainer_fee_option', $this->retainer_fee_option);
            $counterBid->saveMeta('retainer_fee_amount', $this->retainer_fee_amount);
            $counterBid->saveMeta('retainer_fee_application', $this->retainer_fee_application);
            $counterBid->saveMeta('agency_agreement_timeframe', $this->agency_agreement_timeframe);
            $counterBid->saveMeta('agency_agreement_custom', $this->agency_agreement_custom);
            $counterBid->saveMeta('brokerage_relationship', $this->brokerage_relationship);
            $counterBid->saveMeta('additional_details_broker', $this->additional_details_broker);

            // $counterBid->saveMeta('custom_services', json_encode($this->custom_services));
            $counterBid->saveMeta('total_marketing_fee', $this->total_marketing_fee);
            $counterBid->saveMeta('total_flat_fee', $this->total_flat_fee);
            // Save Promotional Materials
          
    }
}
