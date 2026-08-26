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
    public $proposedServices = [];
    public bool $other_services_enabled = false;
    public array $other_services = []; // Always initialize as an array
    // public $flat_fee_services = [];
    public $total_flat_fee = 0;
    public $total_marketing_fee = 0;

    // Client Contact Info
    public $client_name = '';
    public $client_phone = '';
    public $client_email = '';

    // Client Property Address
    public $client_property_address = '';
    public $client_property_city = '';
    public $client_property_state = '';
    public $client_property_zip = '';


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
public bool $isOfferListing = false;
 public $purchase_fee_flat_type = '$';
    public string $lease_fee_flat_type = '$';
    public $gap_payment_type = '$';

        public $gap_payment_amount = '';
  public $down_payment_type = '$';
    public $down_payment_amount = '';
      public $seller_financing_type = '$';
    public $seller_financing_amount = '';

    // Counter-specific deal negotiation fields
    public $areas_of_interest = '';
    public $target_purchase_price = '';
    public $timeline_to_purchase = '';
    public $pre_approval_status = '';
    public $cash_buyer = '';
    public $estimated_down_payment = '';

    protected function rules(): array
    {
        $rules = [
            'referral_fee_percent' => ['nullable', 'numeric', 'between:0,100'],
        ];
        if ($this->isOfferListing) {
            $rules['client_name']       = ['required', 'string', 'max:255'];
            $rules['client_phone']      = ['required', 'string', 'max:50'];
            $rules['client_email']      = ['required', 'email', 'max:255'];
            $rules['areas_of_interest'] = ['required', 'string', 'max:500'];
        }
        return $rules;
    }

    protected array $messages = [
        'referral_fee_percent.numeric'   => 'Referral fee must be a number.',
        'referral_fee_percent.between'   => 'Referral fee must be between 0 and 100.',
        'client_name.required'           => 'Client name is required for offer listings.',
        'client_phone.required'          => 'Client phone is required for offer listings.',
        'client_email.required'          => 'Client email is required for offer listings.',
        'client_email.email'             => 'Please enter a valid email address.',
        'areas_of_interest.required'     => 'Areas of interest are required for offer listings.',
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



    public function updatedReferralFeePercent(): void
    {
        $this->validateOnly('referral_fee_percent');
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

    /**
     * Re-resolve the bid and its auction from the database and admit only a party
     * to that negotiation — the listing owner (the buyer) or the bidding agent.
     *
     * Those two parties are not invented here: BuyerAgentAuctionBidController
     * ::view_counter_terms() — the page that links to this screen — already admits
     * exactly `isAgent || isBuyer` and 403s everyone else.
     *
     * Always re-queries rather than reading `$pab->auction`: that relation is declared
     * `->withDefault()`, so a missing auction yields an empty model whose `user_id` is
     * null instead of null itself, which would read as "owned by nobody" rather than
     * failing closed.
     *
     * Authentication alone is deliberately not sufficient: the buyer route prefix
     * carries only `auth` + `verified`, so before this guard every verified user of
     * any role could reach this component for any bid id.
     *
     * @return array{0: BuyerAgentAuctionBid, 1: BuyerAgentAuction}
     */
    private function authorizeParty($bidId): array
    {
        $bid     = BuyerAgentAuctionBid::find($bidId);
        $auction = $bid ? BuyerAgentAuction::find($bid->buyer_agent_auction_id) : null;

        $isBuyer = $auction && (int) $auction->user_id === (int) Auth::id();
        $isAgent = $bid && (int) $bid->user_id === (int) Auth::id();

        abort_unless(Auth::check() && $bid && $auction && ($isBuyer || $isAgent), 403, 'You are not authorized to submit counter terms for this bid.');

        return [$bid, $auction];
    }

    public function mount($pab, $bidId)
    {
        // Guard independently of the controller: a Livewire component can be mounted
        // from anywhere, so it cannot assume its caller checked. It especially cannot
        // assume it here — the controller's only guards sit on store()/update(), and
        // neither of those is routed.
        [$bid, $auction] = $this->authorizeParty($bidId);

        // $pab is a BuyerAgentAuctionBid (the specific agent bid being countered).
        // It is supplied separately from $bidId, so each can be individually valid
        // while naming different negotiations — admit the pair only if coherent.
        if (is_object($pab) && isset($pab->id)) {
            abort_unless((int) $pab->id === (int) $bid->id, 403, 'You are not authorized to submit counter terms for this bid.');
        }

        $this->pab   = $pab;
        $this->bidId = $bidId;

        // Auction taken from the authorized bid, never from $pab.
        $this->auctionId = $auction->id;
        $this->property_type = $auction ? ($auction->get->property_type ?? '') : '';
        $this->isListingCreatedByAgent = optional($auction)->isCreatedByAgent() ?? false;
        $this->isOfferListing = $auction ? ($auction->info('workflow_type') === 'offer_listing') : false;

        // Always load proposed services from the agent's original bid (immutable reference)
        $bidData = $pab->get ?? null;
        if ($bidData) {
            $rawSvc = is_object($bidData) ? ($bidData->services ?? null) : ($bidData['services'] ?? null);
            $rawProposed = [];
            if ($rawSvc !== null) {
                $rawProposed = is_string($rawSvc) ? (json_decode($rawSvc, true) ?? []) : (is_array($rawSvc) ? $rawSvc : []);
            }
            $this->proposedServices = $this->filterServicesToCurrentCatalog(array_values(array_filter((array) $rawProposed)));
        }

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
            // New counters always start with a blank Additional Details field
            $this->additional_details = '';
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
        $flowKey = \App\Support\ServicesFormatter::keyForBuyerAgent($this->property_type ?: 'Residential');
        return view('livewire.buyer.buyer-agent-auction-counter-term', [
            'pab'               => $this->pab,
            'bidId'             => $this->bidId,
            'property_type'     => $this->property_type,
            'parent_counter_id' => $this->parent_counter_id,
            'groupedServices'   => \App\Support\ServicesFormatter::orderSelectedServices($this->proposedServices, $flowKey),
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

        // Source-of-truth: if other_services has non-empty text, force other_services_enabled on
        if (!$this->other_services_enabled && !empty(array_filter($this->other_services, fn($s) => trim((string) $s) !== ''))) {
            $this->other_services_enabled = true;
        }

        // Counter-specific client contact fields
        if (array_key_exists('counter_client_name', $m)) {
            $this->client_name = $m['counter_client_name'];
        }
        if (array_key_exists('counter_client_phone', $m)) {
            $this->client_phone = $m['counter_client_phone'];
        }
        if (array_key_exists('counter_client_email', $m)) {
            $this->client_email = $m['counter_client_email'];
        }
        if (array_key_exists('counter_property_address', $m)) {
            $this->client_property_address = $m['counter_property_address'];
        }
        if (array_key_exists('counter_property_city', $m)) {
            $this->client_property_city = $m['counter_property_city'];
        }
        if (array_key_exists('counter_property_state', $m)) {
            $this->client_property_state = $m['counter_property_state'];
        }
        if (array_key_exists('counter_property_zip', $m)) {
            $this->client_property_zip = $m['counter_property_zip'];
        }
        if (array_key_exists('counter_areas_of_interest', $m)) {
            $this->areas_of_interest = $m['counter_areas_of_interest'];
        }
        if (array_key_exists('counter_target_purchase_price', $m)) {
            $this->target_purchase_price = $m['counter_target_purchase_price'];
        }
        if (array_key_exists('counter_timeline_to_purchase', $m)) {
            $this->timeline_to_purchase = $m['counter_timeline_to_purchase'];
        }
        if (array_key_exists('counter_pre_approval_status', $m)) {
            $this->pre_approval_status = $m['counter_pre_approval_status'];
        }
        if (array_key_exists('counter_cash_buyer', $m)) {
            $this->cash_buyer = $m['counter_cash_buyer'];
        }
        if (array_key_exists('counter_estimated_down_payment', $m)) {
            $this->estimated_down_payment = $m['counter_estimated_down_payment'];
        }
    }
    public function submit()
    {
        // REAUTHORIZE. mount()'s guard ran on an earlier request; every public property
        // below arrived from the client on THIS one. Livewire v2 has no locked
        // properties and its payload checksum does not cover client-initiated property
        // updates, so $bidId, $auctionId, $pab and $counterTermId are all
        // attacker-controlled here regardless of what mount() saw.
        [$bid, $auction] = $this->authorizeParty($this->bidId);

        // $bidId and $auctionId are SEPARATE public properties, and the create below
        // reads both. Left unchecked, a caller party to one negotiation could forge a
        // row whose auction and bid belong to different negotiations. Require both to
        // agree with the authorized pair.
        abort_unless((int) $this->bidId === (int) $bid->id, 403, 'You are not authorized to submit counter terms for this bid.');
        abort_unless((int) $this->auctionId === (int) $auction->id, 403, 'You are not authorized to submit counter terms for this listing.');

        // $counterTermId is a plain public property, so the client can set it to any
        // value it likes. Updating whatever row it names would let an authenticated
        // party rewrite another user's counter terms — and because the update leaves
        // user_id alone, the rewrite would still be attributed to the victim.
        //
        // Buyer scoping differs from Landlord. `buyer_counter_terms.buyer_agent_auction_id`
        // is a genuine AUCTION id (its FK references buyer_agent_auctions.id), and the
        // bid is carried in `parent_counter_id` — despite that column's "counter-back
        // chain" name, both write paths here store $this->bidId in it, and mount()'s
        // edit lookup keys off it. So the negotiation is pinned by BOTH columns, which
        // together also exclude a sibling bid's row on the same listing.
        //
        // A legacy row (parent_counter_id NULL) fails this comparison and is refused,
        // which is deliberate: the retired controller store() wrote rows with no bid at
        // all, and an unscoped historical row must never be adopted as the editable
        // counter for a specific bid.
        if ($this->counterTermId) {
            $existing = BuyerCounterTerm::find($this->counterTermId);
            abort_unless(
                $existing
                    && (int) $existing->user_id === (int) Auth::id()
                    && (int) $existing->buyer_agent_auction_id === (int) $auction->id
                    && $existing->parent_counter_id !== null
                    && (int) $existing->parent_counter_id === (int) $bid->id,
                403,
                'You are not authorized to modify these counter terms.'
            );
        }

        $this->validate();

        try {
            DB::beginTransaction();



            if ($this->counterTermId) {
                // UPDATE the row already authorized above, rather than a second lookup
                // from client state, so nothing can change between check and write.
                $counterTerm = $existing;
                $counterTerm->update([
                    'property_type' => $this->property_type,
                    'parent_counter_id' => $bid->id,
                    'status' => 1,
                ]);
            } else {
                // CREATE new record. Both FK values come from the authorized,
                // database-re-read models — not from the mutable Livewire ids.
                $counterTerm = BuyerCounterTerm::create([
                    'user_id' => Auth::id(),
                    'buyer_agent_auction_id' => $auction->id,
                    'property_type' => $this->property_type,
                    'parent_counter_id' => $bid->id,
                    'status' => 1,
                ]);
                $this->counterTermId = $counterTerm->id; // track after create
            }

            // 2. Save all meta data
            $this->saveAllMetaData($counterTerm);

            DB::commit();

            // Notify the agent (bid owner) that the buyer submitted counter terms
            try {
                // Recipient derived from the authorized $bid and $auction, not from
                // $this->bidId / $this->auctionId. Those are client-supplied, and a
                // tampered bid id would address the notification to an agent outside
                // this negotiation. The rule itself is unchanged: the bid's agent is
                // the recipient.
                $agent  = User::find($bid->user_id);
                $sender = Auth::user();
                if ($agent && $sender) {
                    $agent->notify(new CounterBidSubmittedNotification(
                        $bid,
                        $auction,
                        $sender,
                        $agent->id,
                        'buyer_agent'
                    ));
                }
            } catch (\Exception $e) {
                Log::error('Failed to send counter terms notification for buyer agent listing', [
                    'bid_id' => $this->bidId,
                    'error'  => $e->getMessage(),
                ]);
            }

            session()->flash('success', $this->counterTermId ? 'Counter terms updated!' : 'Counter terms submitted!');
            return redirect()->route('buyer.hire.agent.auction.bid.view-counter', $this->bidId);
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

        // Offer-listing-only: client contact and buyer qualification fields.
        // Only written when $isOfferListing is true to avoid polluting meta for
        // normal hire-agent counter bids with empty strings.
        if ($this->isOfferListing) {
            $counterTerm->saveMeta('counter_client_name', $this->client_name);
            $counterTerm->saveMeta('counter_client_phone', $this->client_phone);
            $counterTerm->saveMeta('counter_client_email', $this->client_email);
            $counterTerm->saveMeta('counter_areas_of_interest', $this->areas_of_interest);
            $counterTerm->saveMeta('counter_target_purchase_price', $this->target_purchase_price);
            $counterTerm->saveMeta('counter_timeline_to_purchase', $this->timeline_to_purchase);
            $counterTerm->saveMeta('counter_pre_approval_status', $this->pre_approval_status);
            $counterTerm->saveMeta('counter_cash_buyer', $this->cash_buyer);
            $counterTerm->saveMeta('counter_estimated_down_payment', $this->estimated_down_payment);
        }
    }
}
