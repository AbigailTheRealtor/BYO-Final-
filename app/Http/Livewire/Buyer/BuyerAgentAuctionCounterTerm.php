<?php

namespace App\Http\Livewire\Buyer;

use Livewire\Component;
use App\Models\BuyerAgentAuction;
use App\Models\BuyerCounterTerm;
use App\Models\BuyerCounterBidding;
use App\Models\BuyerAgentAuctionBid;
use App\Models\User;
use App\Helpers\BuyerBidMatchScoreHelper;
use App\Notifications\CounterBidSubmittedNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
public $referral_fee_percent = '';
public $isListingCreatedByAgent = false;
 public $purchase_fee_flat_type = '$';
    public string $lease_fee_flat_type = '$';
    public $gap_payment_type = '$';

        public $gap_payment_amount = '';
  public $down_payment_type = '$';
    public $down_payment_amount = '';
      public $seller_financing_type = '$';
    public $seller_financing_amount = '';

    protected function rules(): array
    {
        return [
            'referral_fee_percent' => ['nullable', 'numeric', 'between:0,100'],
        ];
    }

    protected array $messages = [
        'referral_fee_percent.numeric' => 'Referral fee must be a number.',
        'referral_fee_percent.between' => 'Referral fee must be between 0 and 100.',
    ];

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

    private function filterServicesToCurrentCatalog(array $services): array
    {
        $propType = $this->property_type ?: '';
        if ($propType === '') {
            return $services;
        }

        $catalog = BuyerBidMatchScoreHelper::getCatalog($propType);
        if (empty($catalog)) {
            return $services;
        }

        $normalize = static function (string $s): string {
            return mb_strtolower(trim(str_replace(
                ["\u{2018}", "\u{2019}", "\u{201C}", "\u{201D}", "'"],
                ["'",        "'",        '"',        '"',        "'"],
                $s
            )));
        };

        return array_values(array_filter($services, static function ($svc) use ($catalog, $normalize): bool {
            return in_array($normalize((string) $svc), $catalog, true);
        }));
    }

    public function mount($pab, $bidId)
    {
        // $pab is a BuyerAgentAuctionBid (the specific agent bid being countered).
        // We load the parent auction to get listing-level data like property_type.
        $this->pab   = $pab;
        $this->bidId = $bidId;

        $auction = BuyerAgentAuction::find($pab->buyer_agent_auction_id);
        $this->auctionId = $pab->buyer_agent_auction_id;
        $this->property_type = $auction ? ($auction->get->property_type ?? '') : '';
        $this->isListingCreatedByAgent = optional($auction)->isCreatedByAgent() ?? false;

        // EDIT MODE: Try load existing active counter term for this buyer (current user) + specific bid.
        // Only load status=1 (active) records — terminal or stale counters should not be reactivated via edit.
        $existing = BuyerCounterTerm::with('meta')
            ->where('buyer_agent_auction_id', $this->auctionId)
            ->where('parent_counter_id', $this->bidId)
            ->where('user_id', Auth::id())
            ->where('status', 1)
            ->latest()
            ->first();

        if ($existing) {
            $this->counterTermId = $existing->id;
            $this->hydrateFromMetaMap($existing->meta->pluck('meta_value', 'meta_key')->toArray());
        } else {
            // NEW COUNTER: Prefill from the Agent's most recent counter (BuyerCounterBidding).
            // BuyerCounterBidding stores the bid ID in buyer_agent_auction_bid_id.
            $agentCounter = BuyerCounterBidding::where('buyer_agent_auction_bid_id', $this->bidId)
                ->latest()
                ->first();

            if ($agentCounter && $agentCounter->get) {
                $sourceData = $agentCounter->get;
                $m = (array) $sourceData;
                foreach ($m as $key => $value) {
                    if (is_array($value)) {
                        $m[$key] = json_encode($value);
                    }
                }
                $this->hydrateFromMetaMap($m);
            } else {
                // Fall back to agent's original bid terms if no counter exists
                $this->prefillFromAgentBid($pab);
            }
        }
    }

    private function prefillFromAgentBid($bid): void
    {
        $bidData = $bid->get ?? null;
        if (!$bidData) {
            return;
        }
        $m = (array) $bidData;
        // Re-encode any array values to JSON so hydrateFromMetaMap can decode them uniformly
        foreach ($m as $key => $value) {
            if (is_array($value)) {
                $m[$key] = json_encode($value);
            }
        }
        $this->hydrateFromMetaMap($m);
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
            'referral_fee_percent',
        ];

        foreach ($assign as $key) {
            if (array_key_exists($key, $m)) {
                $this->$key = $m[$key];
            }
        }

        // Arrays (JSON) — services, other_services
        if (isset($m['services'])) {
            $decoded = json_decode($m['services'], true);
            $rawServices = is_array($decoded) ? $decoded : [];
            $this->services = $this->filterServicesToCurrentCatalog($rawServices);
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
        $this->validate();

        try {
            DB::beginTransaction();



            if ($this->counterTermId) {
                // UPDATE same record
                $counterTerm = BuyerCounterTerm::findOrFail($this->counterTermId);
                // ensure base columns still correct
                $counterTerm->update([
                    'property_type' => $this->property_type,
                    'parent_counter_id' => $this->bidId,
                    'status' => 1,
                ]);
            } else {
                // CREATE new record
                $counterTerm = BuyerCounterTerm::create([
                    'user_id' => Auth::id(),
                    'buyer_agent_auction_id' => $this->auctionId,
                    'property_type' => $this->property_type,
                    'parent_counter_id' => $this->bidId,
                    'status' => 1,
                ]);
                $this->counterTermId = $counterTerm->id; // track after create
            }

            // 2. Save all meta data
            $this->saveAllMetaData($counterTerm);

            DB::commit();

            // Notify the agent (bid owner) that the buyer submitted counter terms
            try {
                $originalBid = BuyerAgentAuctionBid::find($this->bidId);
                // $this->pab is the bid; load the parent auction for the notification
                $notifyAuction = BuyerAgentAuction::find($this->auctionId);
                if ($originalBid && $notifyAuction) {
                    $agent = User::find($originalBid->user_id);
                    $sender = Auth::user();
                    if ($agent && $sender) {
                        $agent->notify(new CounterBidSubmittedNotification(
                            $originalBid,
                            $notifyAuction,
                            $sender,
                            $agent->id,
                            'buyer_agent'
                        ));
                    }
                }
            } catch (\Exception $e) {
                Log::error('Failed to send counter terms notification for buyer agent listing', [
                    'bid_id' => $this->bidId,
                    'error'  => $e->getMessage(),
                ]);
            }

            session()->flash('success', $this->counterTermId ? 'Counter terms updated!' : 'Counter terms submitted!');
            return redirect()->route('buyer.view-auction', $this->auctionId);
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
        $counterTerm->saveMeta('lease_value', str_replace(',', '', $this->lease_value ?? ''));
        $counterTerm->saveMeta('purchase_type', $this->purchase_type);
        $counterTerm->saveMeta('purchase_value', str_replace(',', '', $this->purchase_value ?? ''));

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
        if ($this->isListingCreatedByAgent) {
            $counterTerm->saveMeta('referral_fee_percent', $this->referral_fee_percent);
        }
    }
}
